# STRYKE — Rà soát phân quyền và phạm vi dữ liệu

Ngày: 04/11/2026. Báo cáo đối chiếu source hiện tại, bao gồm thay đổi chưa commit có sẵn. Không thay đổi schema hoặc dữ liệu ứng dụng.

## Phạm vi đã được duyệt

- Admin xem/sửa nhân viên toàn hệ thống, kể cả quản lý cơ sở, nhân viên và người chưa có tài khoản. Giữ nguyên quy tắc cấp vai trò của quản lý công ty.
- Quản lý công ty thêm/sửa cơ sở thuộc công ty; quản lý cơ sở chỉ sửa thông tin vận hành cơ sở được phân công. Hai vai trò quản lý sân và bảng giá trong phạm vi. Nhân viên chỉ xem.
- Xóa cứng cơ sở/sân, chuyển cơ sở sang công ty khác vẫn chỉ Admin. Bảng giá không xóa cứng. Trạng thái cơ sở giữ quyền Admin; giao diện quản lý chỉ sửa thông tin vận hành.
- Giữ nguyên quyền xác nhận/đối soát/hủy booking hiện có của Nhân viên. Không sửa nghiệp vụ booking, thanh toán, hóa đơn, tồn kho hoặc hợp đồng.
- Chỉnh sửa liên hệ công ty và quản trị tài khoản độc lập chỉ được phân tích, chờ Chí/Phát xác nhận.

## Ma trận trước → sau: phần thay đổi

QLCT = Quản lý công ty; QLCS = Quản lý cơ sở; NV = Nhân viên. Các phạm vi lấy từ danh tính đăng nhập, không lấy từ ID client gửi.

| Chức năng | Admin | QLCT | QLCS | NV |
|---|---|---|---|---|
| Danh sách/chi tiết nhân viên | Chỉ QLCT → tất cả nhân sự | Giữ nguyên: QLCS/NV/người chưa có tài khoản thuộc công ty | Không → không | Không → không |
| Sửa/đổi trạng thái nhân viên | Chỉ QLCT → tất cả nhân sự; không đổi vai trò tài khoản | Giữ nguyên phạm vi và giới hạn cấp vai trò | Không → không | Không → không |
| Xem cơ sở | Toàn hệ thống → giữ nguyên | Không có khu vực quản lý → cơ sở thuộc công ty | Không → cơ sở được phân công | Không → chỉ đọc cơ sở được phân công |
| Thêm cơ sở | Có → giữ nguyên | Không → có, chỉ công ty mình | Không → không | Không → không |
| Sửa thông tin vận hành cơ sở | Có → giữ nguyên | Không → cơ sở thuộc công ty | Không → cơ sở được phân công | Không → không |
| Xóa/chuyển công ty/trạng thái cơ sở | Có, theo ràng buộc dữ liệu → giữ nguyên | Không → không | Không → không | Không → không |
| Xem sân | Toàn hệ thống → giữ nguyên | Không → thuộc các cơ sở của công ty | Không → thuộc cơ sở được phân công | Không → chỉ đọc thuộc cơ sở được phân công |
| Thêm/sửa sân và trạng thái sân | Có → giữ nguyên | Không → trong công ty | Không → trong cơ sở | Không → không |
| Xóa cứng sân | Có, theo ràng buộc tham chiếu → giữ nguyên | Không → không | Không → không | Không → không |
| Xem bảng giá | Toàn hệ thống → giữ nguyên | Không → trong công ty | Không → trong cơ sở | Không → chỉ đọc trong cơ sở |
| Thêm/sửa/ngừng/kích hoạt bảng giá | Có → giữ nguyên | Không → trong công ty | Không → trong cơ sở | Không → không |
| Dropdown sân của bộ lọc booking | Toàn hệ thống → giữ nguyên | Có thể lộ tên sân qua facility_id công ty khác → từ chối 403, SQL cũng có scope | Giữ giới hạn cơ sở; bổ sung scope tại SQL dropdown | Giữ giới hạn cơ sở; bổ sung scope tại SQL dropdown |

Các route mới dùng đúng middleware của từng vai trò, không mở các URL quản trị toàn hệ thống cho vai trò khác:

- `/company/co-so`, `/company/san`, `/company/bang-gia`.
- `/facility/co-so`, `/facility/san`, `/facility/bang-gia`.
- `/staff/co-so`, `/staff/san`, `/staff/bang-gia` chỉ GET danh sách/chi tiết.
- Các route `/admin/...` vẫn yêu cầu `QUAN_TRI_TAP_DOAN` như trước.

## Ma trận đã đúng và giữ nguyên

| Chức năng | Admin | QLCT | QLCS | NV |
|---|---|---|---|---|
| Công ty nhượng quyền | Quản lý toàn hệ thống | Xem công ty mình tại `/company/profile` | Không quản lý | Không quản lý |
| Hợp đồng nhượng quyền | Quản lý theo nghiệp vụ hiện có | Xem hợp đồng công ty mình trong hồ sơ công ty | Không | Không |
| Bộ môn/Loại sân | CRUD toàn hệ thống | Không CRUD danh mục; được chọn danh mục hợp lệ khi quản lý sân/giá | Tương tự QLCT | Chỉ đọc tên liên quan trong dữ liệu vận hành |
| Dịch vụ/tồn kho catalog | Quản lý toàn hệ thống | Quản lý trong công ty | Quản lý trong cơ sở | Chỉ đọc trong cơ sở |
| Booking/thanh toán qua màn hình đối soát | Toàn hệ thống | Trong công ty | Trong cơ sở | Trong cơ sở; quyền xử lý hiện có giữ nguyên theo xác nhận |
| Hóa đơn | Xem/chi tiết/in trong toàn hệ thống | Trong công ty | Trong cơ sở | Trong cơ sở |
| Dashboard doanh thu | Toàn hệ thống, lọc phạm vi hợp lệ | Công ty mình | Cơ sở mình | Cơ sở mình |
| Ngân hàng | Xem/quản lý theo phạm vi toàn hệ thống | Xem/quản lý trong công ty | Xem/quản lý trong cơ sở | Chỉ xem; POST bị từ chối |
| Cấp tài khoản qua nhân viên | Giữ nguyên: cấp QLCT | Chỉ cấp QLCS/NV trong công ty, không cấp Admin | Không | Không |

`KHACH_HANG` không được vào các khu vực nội bộ. Booking, thanh toán và hóa đơn khách hàng tiếp tục dùng kiểm tra chủ sở hữu hiện có. Danh sách sân công khai là chức năng có chủ đích, không phải API quản trị trong phạm vi công ty.

## Lỗi và thiếu quyền phát hiện

1. **Lộ tên sân qua bộ lọc booking:** `AdminBookingController::index()` nhận `facility_id`, sau đó gọi `AdminBooking::courtsByFacility()` chưa có scope. Danh sách booking chính vẫn có scope, nhưng dropdown có thể chứa sân công ty khác. Tái hiện trước sửa: HTTP 200 và có tên sân bí mật của fixture công ty khác. Sau sửa: HTTP 403 và không lộ dữ liệu; gọi model trực tiếp cũng trả danh sách rỗng ngoài scope.
2. **Thiếu quyền vận hành đã được duyệt:** Cơ sở/Sân/Bảng giá trước đây chỉ có route và service cho Admin. Chỉ mở middleware sẽ làm thao tác ghi không được giới hạn phạm vi. Đã bổ sung kiểm tra danh sách, bản ghi, lựa chọn cha và transaction ghi, đồng thời tái sử dụng controller/service/view hiện có.
3. **Admin chỉ quản lý QLCT:** truy vấn và service nhân viên lọc vai trò quá hẹp so với yêu cầu mới. Đã mở đọc/sửa/đổi trạng thái cho toàn bộ nhân sự. Không mở quyền cấp Admin, không đọc mật khẩu/hash, không đổi vai trò tài khoản khi cập nhật hồ sơ.
4. **Điều hướng và nút:** bổ sung menu đúng vai trò; Nhân viên không thấy nút thêm/sửa/xóa/ngừng giá, kể cả liên kết “Thêm sân” trong chi tiết cơ sở. Vai trò khác không được đưa tới trang chỉnh sửa danh mục/công ty chỉ dành cho Admin.

## Cách bảo vệ backend

- `FacilityScope::forUser()` dùng `BookingManagementService::resolveScope()` có sẵn: kiểm tra trạng thái tài khoản, hồ sơ nhân viên và quan hệ công ty/cơ sở từ `AuthService`.
- Middleware theo role vẫn kiểm tra từng route. `Router` giữ nguyên xác minh CSRF trên POST.
- `CoSo::page()`, `San::page()`, `BangGia::page()` đưa điều kiện công ty/cơ sở vào cả COUNT và SELECT phân trang. Bộ lọc không ghi đè các predicate scope. Dropdown chỉ trả cơ sở/công ty được phép.
- Controller kiểm tra sở hữu trước khi trả chi tiết hoặc form. Service kiểm tra cả bản ghi hiện tại và cha đích; cơ sở được kiểm tra lại sau khi khóa trong transaction, tránh chuyển ID ẩn để vượt quyền.
- Các API xóa cơ sở/sân chỉ cho Admin cả ở route, controller và service. Service ghi từ chối Nhân viên. Ràng buộc khóa ngoại, dữ liệu cha hoạt động, giá chồng lấn, snapshot và thứ tự khóa cũ được giữ nguyên.
- `admin_context_url()` chỉ thêm các đường dẫn module chính xác vào allowlist; vẫn tái tạo URL từ tham số đã validation, đúng module, không nhận URL ngoài hoặc đổi sang khu vực vai trò khác.
- Các tham số scope là dữ liệu backend tin cậy; không truyền nguyên mảng POST/GET vào constructor service làm scope. Giá trị mặc định Admin của API nội bộ giữ tương thích các caller cũ; controller mới luôn truyền scope đã xác thực.

## File của đợt sửa này

Backend của Chí cần review:

- `app/controllers/EmployeeController.php`: đọc cơ sở từ form cho cập nhật nhân viên Admin; service kiểm tra quan hệ/role.
- `app/services/EmployeeService.php`: mở Admin đọc/sửa/đổi trạng thái toàn bộ nhân sự; giữ quy tắc cấp vai trò và scope QLCT.
- `app/models/NhanVien.php`: truy vấn danh sách Admin gồm toàn bộ nhân sự; nhánh QLCT giữ điều kiện cũ.
- `app/views/admin/employees/index.php`, `edit.php`: tiêu đề phù hợp và giữ/gán cơ sở theo vai trò tài khoản hiện tại.

Backend vận hành và bản vá bộ lọc booking:

- `app/services/FacilityScope.php` mới: các kiểm tra sở hữu nhỏ dùng chung cho ba module.
- `app/controllers/admin/CoSoController.php`, `SanController.php`, `BangGiaController.php`.
- `app/services/CoSoService.php`, `SanService.php`, `BangGiaService.php`.
- `app/models/CoSo.php`, `San.php`, `BangGia.php`.
- `routes/facilities.php`, `routes/prices.php`.
- `app/controllers/admin/AdminBookingController.php`, `app/models/AdminBooking.php`: chỉ sửa scope dropdown, không sửa quy trình booking/payment.
- `app/services/AdminListService.php`: bộ lọc vai trò nhân viên Admin theo danh sách quyền mới.

Giao diện và ngữ cảnh điều hướng:

- `config/admin-menu.php`, `app/helpers/functions.php`.
- `app/views/admin/co-so/{index,form,show}.php`.
- `app/views/admin/san/{index,form,show}.php`.
- `app/views/admin/bang-gia/{index,form,show}.php`.
- `app/views/admin/partials/list-actions.php`, `management-toolbar.php`.

Kiểm thử và tài liệu:

- Mới: `tests/authorization-unit.php`, `tests/authorization-browser.cjs`, tài liệu này.
- Cập nhật: `tests/fixtures/admin-navigation.php`, `tests/employee-details-unit.php`, `tests/admin-management-unit.php`, `tests/admin-navigation-unit.php`, `tests/admin-navigation.cjs`, `tests/service-unit.php`.
- Các assertion route, CSRF, hành động và giữ query không bị bỏ. Kỳ vọng cũ “Admin chỉ thấy QLCT” được thay bằng kiểm tra tất cả nhân sự; ca 11 QLCT để kiểm tra trang cuối dùng thêm bộ lọc vai trò. Test sidebar dùng ngoại lệ route ngân hàng chính xác, không nới cả `/admin/*`.

`storage/temp/authorization-review/task-only.diff` và `changed-files.json` tách riêng thay đổi lần này khỏi các patch có sẵn. Các log/ảnh/session trong `storage/temp` không đưa vào commit. Không đưa `.env`, mật khẩu, receipt upload hoặc dữ liệu local vào commit.

## Kết quả kiểm thử

Tất cả thao tác ghi kiểm thử thực hiện trong fake PDO hoặc SQLite `:memory:`. Fixture không nạp `.env`, không kết nối MySQL và không tạo database riêng. SQLite bỏ `FOR UPDATE`, vì vậy không thay thế kiểm thử khóa đồng thời trên MySQL.

| Bộ kiểm tra | Kết quả |
|---|---|
| `authorization-unit.php` | 262 PASS: scope, GET/POST trực tiếp, hidden ID, chống nâng quyền, CSRF, đọc/ghi hợp lệ, dữ liệu ngoài scope, COUNT/tìm kiếm/phân trang |
| `authorization-browser.cjs` | 354 PASS / 0 FAIL, Chrome thật, 4 vai trò nội bộ, 1440/1024/768/375px; thao tác tạo/sửa và POST giả mạo trên RAM |
| `admin-navigation.cjs` | 1.240 PASS; CRUD/giữ bộ lọc/phân trang/Sidebar ở 4 kích thước |
| `dashboard-browser.cjs` | 408 PASS / 0 FAIL, bao gồm scope doanh thu và role |
| `bank-admin-browser.cjs` | 176 PASS / 0 FAIL |
| `booking-pr6-browser.cjs` | 103 PASS / 0 FAIL, GET thực tế, dữ liệu RAM chỉ đọc |
| `run.php` | 106 PASS, xác thực và tài khoản trong bộ nhớ |
| `catalog-unit.php` | 288 PASS, bao gồm đăng nhập/điều hướng 5 vai trò |
| `facility-unit.php`, `pricing-unit.php` | 109 / 567 PASS |
| `employee-details-unit.php`, `admin-management-unit.php` | 51 / 137 PASS |
| `admin-navigation-unit.php` | 372 PASS, bao gồm return URL của 9 module theo scope mới |
| `service-unit.php` | 1.075 PASS |
| `dashboard-unit.php`, `bank-admin-unit.php` | 88 / 124 PASS |
| `account-layout.php` | 286 PASS trên 5 vai trò |
| `manual-payment-sql-unit.php`, `booking-inventory-unit.php` | 67 / 80 PASS; không kiểm chứng callback hoặc khóa MySQL |
| `booking-management-unit.php` | 108 PASS |
| `invoice-unit.php` | 108 kiểm tra chung + 43 kiểm tra hóa đơn PASS |
| `booking-pr6-unit.php`, `vietqr-bank-unit.php` | 39 / 44 PASS |
| PHP lint | 72 file PHP đang thay đổi/chưa track: PASS |
| JS syntax | Hai test Chrome được sửa/thêm: PASS |
| `git diff --check` | PASS |

Kết quả từng vai trò: Admin thao tác toàn hệ thống; QLCT chỉ dữ liệu công ty; QLCS chỉ cơ sở được phân công; NV chỉ đọc ba module mới và bị chặn POST; KH bị chặn các endpoint nội bộ. Có kiểm tra chéo giữa các khu vực vai trò và giữa cơ sở cùng công ty, không chỉ giữa hai công ty.

Ảnh Chrome và log mới: `storage/temp/authorization-review/browser/`. Mẫu: `NHAN_VIEN-bang-gia-1440.png`, `QUAN_LY_CONG_TY-san-375.png`. Đã xem trực tiếp hai ảnh này và kiểm tra không tràn ngang tự động ở bốn kích thước. Đây là danh tính mô phỏng an toàn; chưa đăng nhập năm tài khoản thật trên database ứng dụng.

### Các kiểm tra còn FAIL

- `booking-pr6-contract.php`: **6 PASS / 4 FAIL** đã tồn tại trước đợt sửa quyền. Booking bị hủy/hết hạn có thanh toán `CAN_DOI_SOAT` vẫn có thể được phục hồi/gia hạn giữ chỗ; payment `HUY`/`HET_HAN` vẫn có thể được xác nhận thành công. Vị trí liên quan: `BookingManagementService::confirmBookingAndPayment()`, nhánh reconciliation và điều kiện trạng thái payment. Không sửa vì thuộc nghiệp vụ Phát/Duy và nằm ngoài phạm vi được duyệt.
- `admin-list-unit.php`: FAIL tại assertion giữ link `bo-mon/show`. Partial trước đợt sửa đã bỏ nút Xem cho Bộ môn, trong khi tên bộ môn trong bảng không có liên kết chi tiết. Đợt sửa quyền giữ nguyên hành vi này; không bỏ assertion hoặc sửa test để ép PASS. Bộ test dừng ở lỗi đầu tiên, các assertion phía sau chưa chạy tới.

Chưa chạy: tích hợp MySQL có ghi, kiểm thử tranh chấp transaction thực, callback ngân hàng thật, test tích hợp cần cấu hình DB riêng và đăng nhập thật năm tài khoản. Không kết luận toàn bộ test dự án PASS.

## Cần phối hợp trước khi mở rộng tiếp

1. **Chí/Phát:** xác nhận tập trường công ty QLCT được chỉnh sửa; hiện `/company/profile` là chỉ đọc. Không mở tùy tiện sửa pháp lý/chủ sở hữu/hợp đồng.
2. **Chí/Phát:** quản trị tài khoản độc lập toàn hệ thống, bao gồm tài khoản khách hàng/khóa/mở/đặt lại mật khẩu, chưa có workflow tương ứng được duyệt. Đợt này mở quản lý hồ sơ nhân sự Admin, giữ nguyên chức năng cấp tài khoản hiện có.
3. **Nhóm/Phát/Duy:** NV hiện có quyền xác nhận, đối soát và hủy booking trong cơ sở. Giữ nguyên theo quyết định của người dùng; cần chốt chính sách dài hạn và phân tách quyền nếu nhóm yêu cầu.
4. **Phát/Duy:** xử lý bốn FAIL hợp đồng trạng thái nêu trên trước khi coi xác nhận thanh toán/booking hết hạn là an toàn đầy đủ; không giải quyết bằng cách chỉ ẩn nút.
5. **Vũ/nhóm:** khôi phục đường đi tới chi tiết Bộ môn nếu việc bỏ nút Xem không còn là yêu cầu UI. Chưa thay đổi trong đợt rà soát quyền này.

Không sửa PaymentService, webhook, BookingInventoryService, nghiệp vụ xuất/giữ tồn, migration hoặc schema. Không thực hiện checkout/reset/pull/rebase/commit/push/merge. Dừng ở trạng thái chờ review.
