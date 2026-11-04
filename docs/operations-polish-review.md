# STRYKE — Booking, Nhân viên, tồn thuê và ngân hàng

Cập nhật 04/11/2026. Tiếp tục từ working tree đang sửa dở, không reset hoặc ghi đè các PR chưa commit. Bản đối chiếu riêng đặt trong `storage/temp/operations-polish/`.

## 1. Hoàn thành và nguyên nhân

### Booking

- Đã xóa hẳn banner “Xác nhận thanh toán tự động — Đang trong quá trình phát triển” tại danh sách và chi tiết, cho cả bốn vai trò nội bộ.
- Khu vực hành động cũ phụ thuộc phần lớn vào `style` inline. CSP của `public/index.php` dùng `style-src 'self'`, nên các thuộc tính đó bị chặn. Khi áp dụng cùng CSP trên Chrome, form đối soát cũ chỉ còn bố cục block mặc định. Đã chuyển phần hành động sang class và CSS ngoài, giữ nguyên CSP.
- Xác nhận, đối soát, cảnh báo hết hạn và hủy được chia card riêng; có label, khoảng cách 18px giữa trường, footer hành động riêng và responsive. Desktop đủ chỗ dùng hai cột; màn hình hẹp dùng một cột.
- Giữ nguyên các nhánh điều kiện xác nhận hiện có, route, POST, tên trường, CSRF và booking ID. Xác nhận thao tác dùng `data-confirm` và dialog có sẵn, thay cho `onclick` inline vốn cũng bị CSP chặn.
- Không sửa `BookingManagementService`, PaymentService, trạng thái hoặc quy trình hoàn tiền. Những lỗi nghiệp vụ hiện có được ghi ở mục 4.

### Nhân viên và bộ lọc

- Danh sách trước đây in trực tiếp `ma_cong_ty`/`ma_co_so`; truy vấn phân trang chưa chọn tên liên quan. Đã thêm LEFT JOIN lấy `ten_cong_ty`, `ten_co_so` trong cùng COUNT/SELECT, không truy vấn từng dòng.
- JOIN cơ sở kiểm tra cả cơ sở và công ty của nhân viên, tránh làm lộ tên cơ sở công ty khác khi dữ liệu liên kết bị sai. Không đổi khóa ngoại, scope, điều kiện lọc, sắp xếp, quyền cấp tài khoản hoặc nghiệp vụ nhân viên.
- Admin hiển thị “Tên công ty”. QL Công ty hiển thị tên cơ sở; NULL là “Chưa phân công”; quan hệ thiếu có thông báo thay thế. Chi tiết nhân viên hiện đã dùng tên nên không cần sửa.
- Bộ lọc `/company/employees` trước đây không nằm trong wrapper `management-filters`, dẫn tới quy tắc form tài khoản giới hạn chiều rộng 900px. Chrome đo vùng danh sách rộng 1112px nhưng toolbar chỉ 900px ở viewport 1440px.
- Đã dùng wrapper/partial và control của Admin, thêm bố cục riêng cho QL Công ty theo chiều rộng thực tế: một hàng khi đủ chỗ, hai hàng có chủ đích, hai cột hoặc một cột khi hẹp. Sau sửa toolbar và danh sách cùng chiều rộng. Quản lý sân, Công ty và Hợp đồng giữ cấu trúc cũ.

### Tồn thuê: phân biệt tồn và số lượng chọn

- Source thực tế: `Booking::services()` chỉ lấy dịch vụ đang cung cấp đúng cơ sở; `Booking::reserved()` cộng booking `CHO_THANH_TOAN` chưa hết hạn theo ID dịch vụ. Quote tính `max(0, tồn vật lý - giữ ảo)` và giữ NULL riêng biệt.
- Tuy nhiên `BookingService::quote()` đặt `purchasable=false` cho mọi `CHO_THUE`. View dựa vào cờ này để khóa input/nút; input mặc định là **số lượng khách chọn = 0**, không phải trường tồn khả dụng.
- Tái hiện bằng cùng source và fixture sáu vợt: trước sửa `so_luong_ton=6`, `available=6`, `purchasable=false`; sau sửa hai số vẫn bằng 6 nhưng `purchasable=true`. Bằng chứng: `rental-before.json`, `rental-after.json` trong thư mục báo cáo.
- Người dùng đã duyệt mở CHO_THUE theo hợp đồng giữ tồn hiện có. Đã bỏ chặn loại thuê ở quote và hook xuất tồn; giữ kiểm tra đơn vị snapshot, cơ sở, số lượng, thời hạn, khóa dòng và UPDATE chống tồn âm.
- Giao diện khách tách rõ “Còn hàng/Hết hàng/Không theo dõi tồn”, tồn vật lý, đang giữ và khả dụng. Input vẫn bắt đầu từ 0 vì khách chưa chọn; max dùng tồn khả dụng. Chrome đã kiểm tra nút tăng không vượt sáu và áp dụng quote AJAX qua service thật trên fixture.
- Draft không giữ/trừ tồn. Pending giữ ảo sáu phút; hủy/hết hạn nhả giữ ảo, không cộng tồn vật lý. NULL không thành 0. Không viết giao–nhận, hoàn vợt hoặc quản lý lịch thuê theo giờ.
- **Giới hạn dữ liệu thật:** truy vấn READ ONLY trên database đang cấu hình chưa tìm thấy dòng `CHO_THUE` hoặc tên chứa “vợt”. Không kết luận đã xác minh bản ghi “tồn 6” trong ảnh, không tạo/sửa dịch vụ giả. Cần đối chiếu đúng database, ID dịch vụ và cơ sở trên máy của người dùng.

### Ngân hàng

- Checkbox không chọn không gửi `trang_thai`. Cả `BankController::store()` và `update()` cũ dùng giá trị thiếu mặc định `HOAT_DONG`. Database vì vậy lưu hoạt động; form Sửa đọc trạng thái đó và bật lại checkbox. Đây là lỗi xử lý input backend, không chỉ lỗi hiển thị.
- Bỏ checkbox kích hoạt. Form Thêm dùng select trạng thái ban đầu, mặc định Hoạt động nhưng vẫn cho tạo Tạm ngừng vì source hiện hỗ trợ khả năng này. Tài khoản đầu tiên tạm ngừng không tự thành mặc định.
- Form Sửa chỉ sửa thông tin thụ hưởng. Model không UPDATE `trang_thai` hoặc `la_mac_dinh`, kể cả client gửi giả hai trường đó. Trạng thái/default chỉ thay bằng thao tác danh sách.
- Người dùng đã duyệt chặn tạm ngừng tài khoản nhận tiền chính: cần chủ động đặt tài khoản hoạt động khác làm mặc định trước. Không tự chọn tài khoản thay thế. Đặt mặc định cho NGUNG bị chặn, không tự kích hoạt như SQL cũ.
- Model kiểm tra/khóa lại dòng trong transaction trước khi đặt mặc định hoặc ngừng; lỗi validation rollback transaction do model mở. Controller hiển thị lỗi qua flash khi quay lại danh sách. Không xóa tài khoản hoặc thay đổi dữ liệu đang có khi triển khai.
- Bảng dùng cột và nút có thể xuống hai hàng trong ô; vùng hẹp chuyển thành card theo container, không giấu overflow để che lỗi. Đã kiểm tra cả trường hợp đủ ba nút Sửa/Đặt mặc định/Tạm ngừng.
- Form có lỗi dưới từng trường, giữ dữ liệu nhập và trạng thái khi validation thất bại; thay inline uppercase bằng CSS hiển thị, backend uppercase hiện có vẫn giữ nguyên.
- Scope và CSRF giữ nguyên: Admin toàn hệ thống, QL Công ty trong công ty, QL Cơ sở trong cơ sở; Nhân viên chỉ xem. Checkout vẫn dùng hàm lọc tài khoản hoạt động hiện có; không sửa payment/snapshot nhận tiền.

## 2. File thay đổi trong đợt này

Ứng dụng — 14 file:

1. `app/controllers/BankController.php`
2. `app/models/NhanVien.php`
3. `app/models/TaiKhoanNganHang.php`
4. `app/services/BookingService.php`
5. `app/services/BookingInventoryService.php`
6. `app/views/admin/bookings/index.php`
7. `app/views/admin/bookings/show.php`
8. `app/views/admin/employees/index.php`
9. `app/views/company/employees/index.php`
10. `app/views/admin/partials/management-toolbar.php`
11. `app/views/courts/booking-details.php`
12. `app/views/facility/bank/index.php`
13. `app/views/facility/bank/form.php`
14. `public/assets/css/admin-catalog.css`

Kiểm thử — 10 file, gồm hai file mới:

- `tests/booking-inventory-unit.php`
- `tests/bank-admin-unit.php`
- `tests/bank-admin-browser.cjs`
- `tests/vietqr-bank-unit.php`
- `tests/admin-management-unit.php`
- `tests/manual-payment-sql-unit.php`
- `tests/fixtures/bank-admin.php`
- `tests/fixtures/booking-pr6.php`
- Mới: `tests/operations-polish-browser.cjs`
- Mới: `tests/fixtures/operations-stock.php`

Tài liệu — hai file:

- `docs/booking-inventory-contract.md`: ghi quyết định mở thuê và giới hạn hook.
- `docs/operations-polish-review.md`: báo cáo này.

Tổng 26 file. `task-only.diff` và `changed-files.json` trong thư mục báo cáo tách riêng đợt sửa khỏi các PR có sẵn. Không đưa file trong `storage/temp`, `.env`, thông tin đăng nhập hoặc receipt local vào commit.

## 3. Kiểm thử đã chạy

Không ghi fixture vào database ứng dụng, không tạo database mới. Test ghi dữ liệu chỉ dùng fake PDO hoặc SQLite `:memory:`; phiên HTTP lưu riêng dữ liệu fixture trong thư mục tạm.

| Kiểm tra | Kết quả |
|---|---|
| `operations-polish-browser.cjs` | **809 PASS / 0 FAIL**, Chrome thật: Booking dưới CSP, label/card/footer, banner, tên công ty/cơ sở, bộ lọc/query/phân trang/quay lại/Sidebar, tồn thuê và AJAX quote |
| `bank-admin-browser.cjs` | **387 PASS / 0 FAIL**, bốn vai trò nội bộ, ba nút thao tác, thêm/sửa, tạo NGUNG, lỗi default, kích hoạt/ngừng, CSRF và vượt scope |
| `admin-navigation.cjs` | **1.240 PASS**, CRUD/ngữ cảnh/quay lại/Sidebar trên toàn bộ module hiện có |
| `authorization-browser.cjs` | **354 PASS / 0 FAIL** |
| `booking-pr6-browser.cjs` | **103 PASS / 0 FAIL**; ca thiếu bảng ngân hàng được kiểm tra như chẩn đoán lỗi đã biết, không chạy migration |
| `booking-review.cjs` | **31 PASS**, PHP view/JS thật, HTTP mô phỏng; giữ nguyên test gốc |
| `booking-inventory-unit.php` | **92 PASS**, gồm thuê 6/4/0/NULL, pending/cancel/expiry, snapshot, rollback, stale request và lặp tuần tự |
| `manual-payment-sql-unit.php` | **72 PASS** trên SQLite RAM và guard native binding, gồm writer thủ công hiện có trừ thuê 6→4, giữ snapshot, từ chối xác nhận lặp tuần tự |
| `bank-admin-unit.php` | **133 PASS**, quyền, CSRF, trạng thái, default, giả mạo flags và bảo toàn dữ liệu |
| `admin-management-unit.php` | **141 PASS**, JOIN tên, không N+1, quan hệ thiếu/sai scope, COUNT/lọc/sắp xếp/phân trang |
| `authorization-unit.php` | **262 PASS** |
| `run.php`, `catalog-unit.php` | **106 / 288 PASS**, bao gồm xác thực/điều hướng năm vai trò trong bộ nhớ |
| `facility-unit.php`, `pricing-unit.php`, `service-unit.php` | **109 / 567 / 1.075 PASS** |
| `employee-details-unit.php`, `admin-navigation-unit.php`, `account-layout.php` | **51 / 372 / 286 PASS** |
| `vietqr-bank-unit.php`, `courts-unit.php`, `booking-pr6-unit.php` | **44 / 36 / 39 PASS** |
| `booking-management-unit.php`, `invoice-unit.php`, `dashboard-unit.php` | **108 / (108 + 43) / 88 PASS** |
| PHP lint | **80 file đang thay đổi/chưa track: PASS** |
| JS syntax | `admin.js`, `booking.js` và hai test Chrome mới/sửa: **PASS** |
| `git diff --check` | **PASS** |

Hai bộ Chrome UI mới đã chạy tại **1440, 1280, 1024, 768, 375px**. Đã kiểm tra trực tiếp các ảnh xuất ra, không chỉ dựa vào assertion kích thước. Quyền KH kiểm tra trong test auth và quote; trình duyệt các vai trò dùng danh tính fixture, không đăng nhập năm tài khoản thật.

Các kỳ vọng test cũ “CHO_THUE luôn bị chặn” và “form Sửa có trường đổi trạng thái” được thay bằng kiểm tra hợp đồng đã duyệt, kèm kiểm tra hành vi thật. Không bỏ assertion về CSRF, scope, snapshot, thiếu tồn hoặc route.

## 4. FAIL hiện có và phần chưa thể kiểm chứng

- `booking-pr6-contract.php`: **6 PASS / 4 FAIL**, vẫn như trước. Booking đã hủy/hết hạn với payment `CAN_DOI_SOAT` có thể được phục hồi/gia hạn; payment `HUY`/`HET_HAN` có thể được xác nhận. Không sửa `BookingManagementService::confirmBookingAndPayment()` trong đợt này. Phát/Duy cần chốt và sửa điều kiện backend; redesign không khắc phục lỗi nghiệp vụ này.
- `admin-list-unit.php`: **FAIL** tại assertion đường dẫn chi tiết Bộ môn kèm ngữ cảnh trang. Partial trước đợt sửa đã bỏ nút Xem, còn tên Bộ môn không có link. Không thuộc sáu nhóm yêu cầu hiện tại; không sửa test để ép PASS. Assertion phía sau lỗi đầu tiên chưa chạy tới.
- Chưa kiểm chứng bản ghi sáu vợt trên DB người dùng vì truy vấn chỉ đọc chưa tìm thấy dữ liệu tương ứng.
- Chưa chạy hai transaction MySQL đồng thời, hai callback thật, giao dịch ngân hàng/provider, toàn bộ integration test cần ghi DB hoặc migration. Test stale quote và retry trong RAM là mô phỏng tuần tự, không phải bằng chứng khóa MySQL đồng thời.
- `BookingInventoryService` vẫn **chưa được gọi** từ PaymentService/webhook/writer thủ công. Writer thủ công tự xuất tồn bằng SQL trong cùng transaction. Không thêm hook cạnh SQL đó để tránh xuất hai lần. Chưa thể tuyên bố hệ thống callback có tính chống lặp hoàn chỉnh; hook yêu cầu caller xác minh, khóa và kiểm tra trạng thái trước khi gọi đúng một lần.
- Không có workflow hoàn vợt. Theo quyết định đã duyệt, thuê hiện dùng tồn chung; không tự hoàn kho sau thời gian chơi.

## 5. Ảnh trước/sau

Tất cả ảnh dùng dữ liệu fixture, không phải số liệu kinh doanh thật. Booking sau sửa chụp riêng khu vực hành động; ảnh danh sách/form chụp toàn trang.

| Khu vực | Trước | Sau |
|---|---|---|
| Booking hết hạn | [Desktop](../storage/temp/operations-polish/before-booking-1440.png) | [Desktop](../storage/temp/operations-polish/after-booking-expired-1440.png), [375px](../storage/temp/operations-polish/after-booking-expired-375.png) |
| Xác nhận/đối soát | Ảnh lỗi người dùng đính kèm | [Pending 1440px](../storage/temp/operations-polish/after-booking-pending-1440.png), [Pending 375px](../storage/temp/operations-polish/after-booking-pending-375.png) |
| Đối soát/hủy riêng card | Ảnh lỗi người dùng đính kèm | [1440px](../storage/temp/operations-polish/after-booking-reconcile-1440.png), [375px](../storage/temp/operations-polish/after-booking-reconcile-375.png) |
| Nhân viên Admin | Ảnh ID thô người dùng đính kèm | [1440px](../storage/temp/operations-polish/after-employees-admin-1440.png) |
| Nhân viên QL Công ty/bộ lọc | [1440px](../storage/temp/operations-polish/before-employees-1440.png) | [1440px](../storage/temp/operations-polish/after-employees-company-1440.png), [375px](../storage/temp/operations-polish/after-employees-company-375.png) |
| Bảng ngân hàng | [1440px](../storage/temp/operations-polish/before-bank-1440.png) | [Ba nút 1440px](../storage/temp/bank-admin-ui/QUAN_LY_CONG_TY-three-actions-1440.png), [1024px](../storage/temp/bank-admin-ui/QUAN_LY_CONG_TY-three-actions-1024.png), [375px](../storage/temp/bank-admin-ui/company-375.png) |
| Form ngân hàng | — | [Thêm 1440px](../storage/temp/bank-admin-ui/company-create-1440.png), [Sửa NGUNG 375px](../storage/temp/bank-admin-ui/company-edit-375.png) |
| Tồn thuê fixture | Bằng chứng quote `purchasable=false` | [1440px](../storage/temp/operations-polish/after-rental-1440.png), [375px](../storage/temp/operations-polish/after-rental-375.png) |

Ảnh ở đủ năm kích thước nằm trong hai thư mục cùng bảng trên. Log và kết quả JSON lưu cạnh ảnh.

## 6. Review với các thành viên và kiểm tra thủ công

- **Phát:** review hai dòng mở thuê trong `BookingService` và phần hiển thị tồn khách hàng; không đổi selection/quote totals/confirm/expire hoặc luật giữ sáu phút. View Booking đổi bố cục và xác nhận UI, không đổi nhánh đủ điều kiện.
- **Duy:** review `BankController`, `TaiKhoanNganHang` và giao diện ngân hàng theo hai quyết định đã duyệt. PaymentService và SQL xác nhận thủ công giữ nguyên. Cần phối hợp riêng việc thay writer tồn bằng hook và bốn lỗi trạng thái.
- **Chí:** chỉ sửa truy vấn đọc `NhanVien::page()` và view/partial hiển thị tên/bộ lọc. Không sửa EmployeeController/EmployeeService hoặc cấp vai trò trong đợt này.

Để review UI: mở `/admin/bookings` và chi tiết ở các trạng thái; xem danh sách `/admin/employees`, `/company/employees`; thử lọc từ trang 2, xem chi tiết rồi quay lại; mở `/facility/bank` đúng cơ sở và form Sửa của tài khoản NGUNG. Kiểm tra tại năm kích thước và thử thu gọn/mở Sidebar. Không dùng thanh toán giả trên DB ứng dụng để kiểm tra.

Muốn tái chạy test an toàn: `php tests/booking-inventory-unit.php`, `php tests/bank-admin-unit.php`, `php tests/manual-payment-sql-unit.php`; Chrome dùng `node tests/operations-polish-browser.cjs` và `node tests/bank-admin-browser.cjs` với `PHP_BINARY`, `CHROME_BINARY`, `PLAYWRIGHT_PATH` trỏ tới công cụ local có sẵn. Các fixture có guard môi trường và không nạp cấu hình DB thật.

Không sửa schema, không ghi DB thật, không xóa dữ liệu, không checkout/reset/revert/pull/commit/push/merge. Dừng để người dùng review.
