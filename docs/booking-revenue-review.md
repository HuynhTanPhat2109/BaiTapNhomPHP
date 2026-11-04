# STRYKE — Booking, doanh thu và giám sát ngân hàng

## Phạm vi hoàn thành

- Danh sách Booking dùng menu ba chấm theo trạng thái và quyền. Xem chi tiết, xem đối soát là GET. Xác nhận, hủy và ghi nhận đối soát mở dialog ngay trên danh sách, gửi tới ba endpoint POST hiện có, giữ CSRF và các trường nghiệp vụ.
- Dialog có mã đơn, khách, cơ sở/sân, khoảng lịch chơi, số tiền, mã chuyển khoản. Có bước xác nhận riêng trước khi gửi. Bấm ngoài/Escape đóng menu; popover thoát vùng cuộn bảng. Khi validation lỗi có thể mở lại dữ liệu đã nhập; redirect từ dialog giữ các tham số danh sách đã được service chuẩn hóa, không nhận URL redirect tự do.
- Không có nghiệp vụ “từ chối thanh toán” độc lập. Nhãn Từ chối/Hủy sử dụng chính luồng hủy booking đang chờ. Không tạo endpoint hoàn tiền hoặc ghi thẳng trạng thái từ view.
- Các trang `/admin/revenue`, `/company/revenue`, `/facility/revenue`, `/staff/revenue` dùng chung định nghĩa doanh thu và kiểm tra phạm vi tại backend. Có lọc kỳ/ngày, nhóm ngày/tháng và công ty/cơ sở theo quyền; biểu đồ, tổng hợp công ty/cơ sở, tiền sân/dịch vụ và 50 giao dịch hợp lệ gần nhất kèm số hóa đơn nếu có.
- Dashboard bỏ card bộ lọc, dùng tháng hiện tại đến hôm nay, ghi rõ kỳ; bộ lọc chi tiết ở trang Thống kê. KPI toàn thời gian vẫn có nhãn riêng.
- Admin giám sát ngân hàng toàn hệ thống ở `/facility/bank`: tìm tên ngân hàng/số tài khoản/chủ tài khoản, lọc công ty/cơ sở/trạng thái, mở cấu hình BIN/ngày tạo ở từng dòng. Admin và Nhân viên không được ghi cả tại route lẫn controller. Hai vai trò quản lý giữ quyền ghi trong phạm vi cũ.
- Thao tác mặc định ngân hàng khóa dòng cơ sở trước khi xử lý, kể cả khi chưa có tài khoản. Chặn kích hoạt dữ liệu cũ gây hai mặc định hoạt động. Không tự chuyển mặc định khi tạm ngừng; tài khoản chính phải được thay bằng một tài khoản hoạt động khác trước. Form sửa giữ cơ chế chỉ sửa thông tin thụ hưởng; không đổi trạng thái/mặc định bằng trường giả mạo.

## Các chặn an toàn được bổ sung ở Service

`BookingManagementService::actions()` trả về khả năng hiển thị; các writer kiểm tra lại dưới row lock:

| Booking / Payment | Thao tác ghi được phép |
|---|---|
| Đang chờ, còn hạn / CHO_THANH_TOAN, CHO_XAC_NHAN | Xác nhận đủ tiền và mã giao dịch; hủy có lý do; ghi nhận đối soát |
| Đang chờ, còn hạn / CAN_DOI_SOAT | Xác nhận sau đối chiếu đủ điều kiện; hủy có lý do; không ghi đè đối soát bằng request lặp |
| Đã xác nhận hoặc hoàn thành / THANH_CONG | Không xác nhận/hủy/ghi đè; chỉ xem |
| Đã hủy, hết hạn hoặc quá deadline | Không xác nhận hay hủy lại; chỉ ghi nhận tiền chuyển muộn nếu payment chưa thành công/chưa đối soát và có tiền thực nhận dương |
| Payment HUY/HET_HAN hoặc thiếu payment | Không thể xác nhận bằng request trực tiếp |

Đã bỏ nhánh kéo dài hạn 30 phút trước xác nhận: CHO_XAC_NHAN chỉ được chuẩn hóa trạng thái tạm trong transaction khi còn hạn; hạn giữ chỗ gốc không thay đổi. CAN_DOI_SOAT không có quyền hồi sinh booking đã hủy/hết hạn. Không thay đổi `Booking::expire()` hoặc cơ chế giữ tồn ảo.

Xác nhận vẫn dùng transaction hiện có gồm payment + booking + trừ tồn inline + hóa đơn. Không gọi thêm `deductForPayment()` bên cạnh writer inline để tránh trừ hai lần. Khóa booking/payment và kiểm tra trạng thái chặn retry cùng đơn; UNIQUE hiện có bảo vệ mã giao dịch. Lỗi tồn/hóa đơn rollback toàn bộ. Kiểm thử SQLite chứng minh retry tuần tự và rollback, **không chứng minh tranh chấp khóa MySQL thực tế**.

## Ma trận quyền thực tế

| Vai trò | Booking | Doanh thu | Ngân hàng |
|---|---|---|---|
| QUAN_TRI_TAP_DOAN | Toàn hệ thống; thao tác theo trạng thái | Toàn hệ thống | Chỉ giám sát toàn hệ thống |
| QUAN_LY_CONG_TY | Công ty được giao | Công ty và cơ sở trực thuộc | Quản lý cơ sở thuộc công ty |
| QUAN_LY_CO_SO | Cơ sở được giao | Cơ sở được giao | Quản lý cơ sở được giao |
| NHAN_VIEN | Cơ sở được giao; giữ quyền xử lý đang có | Chỉ xem cơ sở được giao | Chỉ xem cơ sở được giao |
| KHACH_HANG | Không truy cập khu vực quản lý | Không truy cập báo cáo nội bộ | Không truy cập cấu hình ngân hàng |

## Định nghĩa và giới hạn số liệu

Doanh thu hợp lệ: payment THANH_CONG, booking DA_XAC_NHAN/HOAN_THANH, có mã giao dịch và ngày thanh toán, số tiền thực nhận khớp payment và tổng snapshot booking, tổng tiền sân/dịch vụ khớp. Cộng một payment cho mỗi booking, không nhân theo các dòng chi tiết hoặc hóa đơn. Dashboard và báo cáo gọi cùng `Dashboard::revenue()`.

Tiền CAN_DOI_SOAT, tiền đã ghi nhận trên booking hủy/hết hạn được trình bày riêng theo ngày ghi nhận; không cộng vào doanh thu. Khoản thiếu ngày hoặc thiếu số tiền không thể tự phân kỳ. Chưa có sổ hoàn tiền đáng tin cậy nên không hiển thị một con số “còn phải hoàn” suy đoán. Hóa đơn không tự coi là tiền đã thu. Lịch sử công ty được quy về công ty hiện sở hữu cơ sở vì schema chưa snapshot chủ sở hữu.

## Kiểm tra và bằng chứng

Toàn bộ dữ liệu có ghi trong test ở SQLite `:memory:` hoặc PDO giả lập. Không đọc `.env` để kết nối database ứng dụng; không tạo database MySQL test hoặc thay schema ứng dụng.

| Bộ Chrome | Kết quả |
|---|---|
| operations-polish-browser.cjs | 1289 PASS / 0 FAIL |
| dashboard-browser.cjs | 456 PASS / 0 FAIL |
| bank-admin-browser.cjs | 329 PASS / 0 FAIL |
| authorization-browser.cjs | 354 PASS / 0 FAIL |
| admin-navigation.cjs | 1240 PASS / 0 FAIL |
| booking-pr6-browser.cjs | 103 PASS / 0 FAIL |
| booking-review.cjs | 31 PASS / 0 FAIL |

Chrome kiểm tra 1440/1024/768/375px ở các suite mới, sidebar mở/thu gọn/mobile, menu, dialog, bộ lọc và quyền. Modal tài chính trên browser chỉ mở/đóng và hủy bước xác nhận; các writer được kiểm tra bằng Service thật trên SQLite trong bộ nhớ, không xác nhận giao dịch giả trên hệ thống local.

PHP: 21/22 file test trong đợt rà soát PASS. `booking-pr6-contract.php`: 10 PASS / 0 FAIL (bốn lỗi trạng thái trước đó đã được sửa). `booking-actions-unit.php`: 154 PASS; `dashboard-unit.php`: 101 PASS; `bank-admin-unit.php`: 131 PASS; `manual-payment-sql-unit.php`: 72 PASS.

`admin-list-unit.php` còn FAIL ở assertion dòng 206: Bộ môn thiếu liên kết Xem chi tiết mang ID/ngữ cảnh phân trang, do `list-actions.php` hiện có ngoại lệ cho `/admin/bo-mon`. Đây là lỗi có trước; lượt này không sửa file đó hoặc làm yếu assertion. Phần test sau assertion đầu tiên chưa chạy. Không báo toàn bộ suite xanh.

Test gốc được điều chỉnh đúng thay đổi UI: liên kết Chi tiết qua menu mới, bộ lọc chuyển sang route revenue, sidebar active ở cuối và Admin ngân hàng chuyển sang chỉ đọc. PDO giả lập VietQR sửa fetchColumn() để trả cột theo vị trí như PDO thật, nhằm hỗ trợ truy vấn khóa cơ sở. Không bỏ/vô hiệu hóa assertion.

PHP lint, JS syntax và `git diff --check` được chạy trên phần thay đổi. Log kết quả, ảnh Chrome và diff riêng lưu trong `storage/temp/booking-revenue-review`, `storage/temp/operations-polish`, `storage/temp/dashboard-review`, `storage/temp/bank-admin-ui` (artifact local, không commit).

## Cần phối hợp / chưa kiểm chứng

- Phát/Duy review thay đổi guard ở BookingManagementService: không tự gia hạn để xác nhận, không hồi sinh hủy/hết hạn, không hủy booking đã thành công. Quyền hiện có của Nhân viên được giữ nguyên.
- Duy: xác minh giao dịch ngân hàng/provider, callback/webhook, retry đồng thời trên MySQL, deadlock/lock-timeout và sổ hoàn tiền. Không tích hợp thêm writer xuất tồn song song với phần inline đang có.
- Chưa chạy giao dịch ngân hàng thật, callback, chạy đồng thời hai kết nối MySQL hoặc kiểm thử ghi trên database local. Không thay PaymentService, BookingService, BookingInventoryService, Booking::expire, schema, tài khoản, CSRF hay nghiệp vụ nhân viên.
- Báo cáo và modal không thay thế việc nhân sự đối chiếu giao dịch trên ứng dụng ngân hàng. Đối soát booking hết hạn chỉ ghi nhận tiền, không giữ lại sân hoặc thực hiện hoàn tiền.

## Kiểm tra thủ công không ghi dữ liệu

1. Admin mở Quản lý booking, kiểm tra menu đơn đang chờ/đã xác nhận/hủy/hết hạn. Mở các modal và hủy bước xác nhận; đối chiếu thông tin với trang chi tiết.
2. Mở Tổng quan: có kỳ Tháng này và không có form Bộ lọc báo cáo. Mở Thống kê doanh thu, đổi kỳ/công ty/cơ sở, kiểm tra dữ liệu và bảng giao dịch.
3. Admin mở Tài khoản ngân hàng: chỉ có bộ lọc và xem cấu hình. URL thêm/sửa phải trả 403. Kiểm tra từng vai trò quản lý và Nhân viên trong phạm vi được giao.
4. Đổi viewport 1440/1024/768/375, mở/thu gọn sidebar, kiểm tra bảng cuộn riêng, menu và dialog không bị cắt.
5. Chỉ xác nhận/hủy hoặc thay tài khoản ngân hàng với dữ liệu nghiệp vụ thật đã được chủ hệ thống cho phép. Muốn thử thao tác ghi và retry, dùng các test trong bộ nhớ nêu trên.

## File thay đổi

Danh sách bên dưới chỉ tính thay đổi so với snapshot đầu lượt, tách khỏi toàn bộ thay đổi chưa commit có sẵn. Bản diff riêng: `storage/temp/booking-revenue-review/task-only.diff`. `preservation.json` đối chiếu các file lõi giữ nguyên; `git-status.txt` và `git-diff-stat.txt` là trạng thái toàn repository.

<!-- files:start -->
- Sửa: `app/controllers/BankController.php`
- Sửa: `app/controllers/admin/AdminBookingController.php`
- Sửa: `app/controllers/admin/DashboardController.php`
- Sửa: `app/models/Dashboard.php`
- Sửa: `app/models/TaiKhoanNganHang.php`
- Sửa: `app/services/BookingManagementService.php`
- Sửa: `app/services/DashboardService.php`
- Tạo: `app/views/admin/bookings/action-dialog.php`
- Sửa: `app/views/admin/bookings/index.php`
- Sửa: `app/views/admin/bookings/show.php`
- Sửa: `app/views/admin/partials/dashboard.php`
- Tạo: `app/views/admin/partials/revenue-tables.php`
- Tạo: `app/views/admin/revenue.php`
- Sửa: `app/views/facility/bank/index.php`
- Tạo: `app/views/facility/bank/monitor.php`
- Sửa: `app/views/layouts/admin.php`
- Sửa: `config/admin-menu.php`
- Tạo: `docs/booking-revenue-review.md`
- Sửa: `docs/dashboard-reporting.md`
- Sửa: `public/assets/css/admin-catalog.css`
- Sửa: `public/assets/css/dashboard.css`
- Tạo: `public/assets/js/booking-actions.js`
- Sửa: `routes/payment.php`
- Sửa: `routes/web.php`
- Sửa: `tests/authorization-unit.php`
- Sửa: `tests/bank-admin-browser.cjs`
- Sửa: `tests/bank-admin-unit.php`
- Tạo: `tests/booking-actions-unit.php`
- Sửa: `tests/booking-management-unit.php`
- Sửa: `tests/booking-pr6-browser.cjs`
- Sửa: `tests/booking-pr6-contract.php`
- Sửa: `tests/dashboard-browser.cjs`
- Sửa: `tests/dashboard-unit.php`
- Sửa: `tests/fixtures/dashboard.php`
- Sửa: `tests/manual-payment-sql-unit.php`
- Sửa: `tests/operations-polish-browser.cjs`
- Sửa: `tests/vietqr-bank-unit.php`
