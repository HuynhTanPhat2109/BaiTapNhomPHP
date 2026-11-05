# STRYKE — Hoàn thiện menu Booking, xác nhận thủ công và ô mật khẩu nhân viên

## Kết quả

Menu Booking dùng lại `list-action` và `row-menu` như Bảng giá. Mỗi mục có SVG, nền riêng, cùng chiều rộng và spacing; giữ top-layer popover, bàn phím, Escape, đóng ngoài và điều kiện trạng thái/quyền hiện có. Không thêm endpoint thao tác mới.

Hai form cấp tài khoản nhân viên thêm wrapper `password-control employee-password` và nút `pw-toggle` đã có trong `app.js`. Mật khẩu bắt đầu trống, bị che; click/Space/Enter đổi ẩn/hiện và cập nhật `aria-label`/`aria-pressed` mà không submit hoặc mất giá trị. Không thay code hash, truy vấn tài khoản, cấp vai trò hoặc lưu mật khẩu. CSS chỉ áp dụng wrapper nhân viên; giao diện đăng nhập/cài đặt tài khoản giữ nguyên.

Modal Booking gồm thông tin đơn, thông tin thanh toán và xác minh thủ công. Các thông tin đơn, tổng tiền, số tiền phải thu, mã tham chiếu, ngày tạo, deadline và trạng thái lấy từ kết quả truy vấn hiện có. Không thêm input cho nhân viên sửa tổng tiền hay mã tham chiếu. Dữ liệu giao dịch đã ghi nhận hiển thị riêng nếu có. Giảm các trường rỗng, dùng grid, cuộn nội bộ, header/footer sticky; mobile giữ các nút trong vùng modal.

## Xác nhận và đối soát

Source hiện tại không có nguồn/provider hoặc dấu xác minh giao dịch ngân hàng tự động. `CAN_DOI_SOAT`, mã giao dịch, số tiền hoặc ảnh biên lai đã lưu **không được coi là bằng chứng đã xác minh tự động**. `ngay_thanh_toan` trên giao dịch đối soát còn có thể là lúc nhân viên ghi nhận, không chắc là thời gian ngân hàng chuyển tiền. Vì vậy không tự đưa thời gian này hoặc `ngay_tao` booking vào ô thời gian giao dịch thực tế.

Luồng hiện tại:

1. Xem dữ liệu hệ thống chỉ đọc trong modal hoặc trang chi tiết.
2. Đối chiếu giao dịch trên tài khoản ngân hàng. Nhập/kiểm tra số tiền thực nhận, mã giao dịch và thời gian giao dịch thực tế; ghi chú tùy chọn. Số tiền đã ghi nhận có thể được điền sẵn nhưng vẫn cần đối chiếu. Không tự dùng số tiền phải thu làm bằng chứng tiền đã nhận.
3. Đánh dấu đã đối chiếu. UI chỉ mở nút xác nhận khi các trường hợp lệ và số tiền khớp số phải thu; sau đó vẫn có bước xác nhận cuối.
4. POST `/admin/bookings/confirm` qua controller hiện có. Controller bắt buộc có `da_doi_chieu=1` và ngày giờ giao dịch tồn tại trong miền DATETIME, rồi gọi nguyên `BookingManagementService::confirmBookingAndPayment()`.
5. Service tiếp tục kiểm tra quyền/phạm vi, booking/payment/deadline, tiền/mã giao dịch, chống xử lý lại và transaction payment + booking + xuất tồn + hóa đơn như trước. Không sửa Service hoặc thêm một writer xuất tồn khác.

Nếu sai tiền hoặc cần kiểm tra riêng, dùng nút Chuyển sang đối soát. Số tiền/mã giao dịch vừa nhập được giữ khi chuyển form; không tự đánh dấu đã xác minh. POST `/admin/bookings/reconcile` vẫn chỉ ghi nhận `CAN_DOI_SOAT`, không xác nhận giữ sân/xuất tồn/lập hóa đơn. Booking hủy/hết hạn không có thao tác xác nhận; tiền chuyển muộn đi theo endpoint đối soát hiện có.

Khóa gửi của JavaScript chỉ được bật sau khi qua hộp xác nhận cuối, không khóa form khi người dùng bấm Hủy. Nó chặn submit lặp và phục hồi khi quay lại bằng browser history. Backend vẫn là nơi quyết định giao dịch; không dựa vào `disabled`, số tiền từ dataset hoặc checkbox như một bằng chứng xác minh từ ngân hàng.

## File thay đổi

Ứng dụng:

- `app/controllers/admin/AdminBookingController.php`: thêm kiểm tra khai báo đã đối chiếu và ngày giờ giao dịch trước khi gọi Service; giữ flash, dữ liệu nhập, redirect và CSRF hiện có.
- `app/views/admin/bookings/index.php`: icon/class/role của menu, giữ nguyên route và điều kiện hành động.
- `app/views/admin/bookings/action-dialog.php`: nhóm dữ liệu chỉ đọc, khu vực xác minh, chuyển đối soát và giữ giá trị nhập.
- `app/views/admin/bookings/show.php`: đồng bộ trường xác nhận thủ công với modal, bỏ điền sẵn thời gian hiện tại hoặc số tiền phải thu vào trường thực nhận.
- `app/views/admin/employees/create.php`: nút con mắt cho form tạo Quản lý Công ty.
- `app/views/company/employees/create.php`: nút con mắt cho form cấp tài khoản nhân viên.
- `public/assets/css/admin-catalog.css`: style scoped cho menu/modal Booking và wrapper mật khẩu nhân viên.
- `public/assets/js/booking-actions.js`: chuyển form giữ dữ liệu, kiểm tra UI, khóa gửi lặp và khôi phục sau pageshow.

Kiểm thử/tài liệu:

- `tests/booking-polish-browser.cjs` — mới: UI bốn kích thước, password keyboard/accessibility, controller POST thật trên fixture RAM, validation, CSRF, phạm vi, retry, rollback.
- `tests/fixtures/booking-actions.php` — mới: fixture SQLite RAM có session riêng cho các test HTTP; không đọc `.env`, không kết nối MySQL hay nhà cung cấp thanh toán.
- `tests/fixtures/booking-pr6.php`: cho phép fixture mới tái sử dụng schema/dữ liệu RAM; các test cũ giữ guard và chế độ GET chỉ đọc.
- `tests/operations-polish-browser.cjs`: giữ kiểm tra gốc, bổ sung thông tin modal và trường đối chiếu mới.
- `tests/booking-pr6-browser.cjs`: selector của liên kết trong menu chuyển từ role `link` sang `menuitem`; giữ assertion URL/ID và toàn bộ kiểm tra quyền/CSRF/snapshot.
- `docs/booking-polish-review.md` — mới: báo cáo này.

Backend nhân viên của Chí (`EmployeeController`, `EmployeeService`, `NhanVien`, `TaiKhoan`) không thay đổi. Backend thay đổi duy nhất là `AdminBookingController`; Chí chỉ cần review hai view tạo nhân viên. Phát/Duy review bước xác nhận thủ công bổ sung trong controller. PaymentService, BookingManagementService, BookingService, BookingInventoryService, InvoiceService, routes, middleware, session, schema và dữ liệu thật giữ nguyên.

## Kiểm thử thực tế

| Bộ Chrome | PASS | FAIL |
|---|---:|---:|
| booking-polish-browser.cjs | 488 | 0 |
| operations-polish-browser.cjs | 1305 | 0 |
| booking-pr6-browser.cjs | 103 | 0 |
| booking-review.cjs | 31 | 0 |
| admin-navigation.cjs | 1240 | 0 |

Menu/modal/mật khẩu được kiểm tra ở 1440/1024/768/375px. Đã xem ảnh render thực tế, kiểm tra overflow, trạng thái menu, icon, kích thước nút, keyboard, validation giữ dữ liệu, nút xác nhận và chuyển đối soát.

Test mới gửi POST qua route/controller/service thật trên SQLite RAM. Nó chứng minh thiếu xác nhận đối chiếu, ngày giờ không hợp lệ, sai tiền, sai CSRF và ngoài phạm vi không ghi dữ liệu. Nhấn nút xác nhận cuối hai lần chỉ gửi một POST; stock giảm đúng 2, booking/payment thành công và chỉ thêm một hóa đơn. HTTP retry không thay đổi kết quả; lỗi lập hóa đơn rollback toàn bộ. Đối soát tiền chuyển muộn không phục hồi booking hết hạn hoặc xuất tồn.

PHP: 21/22 file test được rà soát PASS. Các bộ liên quan: booking actions 154, booking management 109, SQL thanh toán 72, contract trạng thái 10, nhân viên read-only 51, account layout 298 đều PASS. `admin-list-unit.php` còn FAIL ở assertion dòng 206 về liên kết Chi tiết Bộ môn giữ ID/ngữ cảnh phân trang. Đây là lỗi đã có trước, ngoài phạm vi; không bỏ/sửa yếu assertion. Các assertion phía sau điểm dừng đó chưa chạy.

PHP lint 8 file, JS syntax 4 file và `git diff --check` PASS. Khi thu hẹp CSS nút mắt vào đúng form nhân viên, test phát hiện padding bị rule form ghi đè; đã sửa độ ưu tiên selector trong phạm vi wrapper nhân viên, không nới assertion. Những FAIL khác gồm định dạng `datetime-local` chuẩn hóa giây 00 của Playwright, selector chưa giới hạn đúng form, hồ sơ Khách hàng thiếu trường trong fixture và selector role HTML cũ. Đã sửa đúng fixture/selector rồi chạy lại; không bỏ assertion. Các test trạng thái, quyền, tiền, tồn và hóa đơn vẫn giữ nguyên.

Ảnh, log, JSON kết quả và diff đầy đủ (kể cả file mới) ở `storage/temp/booking-polish-review/`. Đây là artifact local không đưa vào Git. File biên lai đã có sẵn trong `public/uploads/receipts/` không bị sửa hoặc xóa.

## Giới hạn / chưa chạy

- Checkbox là khai báo đối chiếu của nhân sự được cấp quyền, không phải chứng thực từ nhà cung cấp ngân hàng. Chưa có backend xác minh tự động để tự điền giao dịch đáng tin cậy và bỏ bước nhập lại; cần Duy cung cấp hợp đồng/nguồn dữ liệu đó trước khi tích hợp.
- Không chạy giao dịch ngân hàng thật, webhook/provider, MySQL nhiều kết nối/lock race/deadlock hoặc kiểm thử ghi trên database ứng dụng. Kiểm tra double-click/retry HTTP dùng fixture PHP và SQLite RAM, không khẳng định đã kiểm chứng cạnh tranh MySQL.
- Không thêm hoàn tiền, không thay đổi thời hạn giữ sân, công thức tiền, xuất tồn hoặc lập hóa đơn. Không sửa module Bộ môn để chữa lỗi test ngoài phạm vi.
- Không reset, checkout, revert, pull, commit, push hoặc merge.

## Kiểm tra thủ công an toàn

1. Mở `/admin/bookings`: so sánh menu đơn đang chờ, thành công, hủy và hết hạn. Mở modal, kiểm tra dữ liệu hệ thống, thử chuyển sang đối soát và đóng; không gửi giao dịch thử lên dữ liệu thật.
2. Mở `/admin/employees/create` hoặc `/company/employees/create`: nhập một mật khẩu mới vào form chưa lưu, bấm mắt/Space/Enter để thử ẩn hiện; kiểm tra không submit và không mất giá trị. Đóng form sau khi kiểm tra.
3. Thử kích thước 1440/1024/768/375px, mở/thu gọn sidebar, cuộn modal và dùng Escape/Tab.
4. Muốn kiểm tra ghi/xác nhận/retry/rollback, chạy các fixture test nêu trên. Chỉ thao tác với giao dịch thật trên website khi đã được chủ hệ thống cho phép.
