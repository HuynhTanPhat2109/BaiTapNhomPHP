# STRYKE — Dashboard và quản lý nhân viên theo phạm vi

Ngày kiểm tra: 05/11/2026. Các thay đổi để nguyên trong working tree để review; không thực hiện thao tác thay đổi Git.

## Bố cục Dashboard

CSS cũ dùng Flex theo chiều dọc, `margin: auto` cho số liệu và icon đặt absolute. Khoảng trống của mỗi thẻ phụ thuộc chiều dài nội dung riêng, nên các hàng số liệu/mô tả có thể lệch dù chiều cao thẻ bằng nhau. Grid thống kê tài sản dùng `auto-fit`, khác cách chia cột/breakpoint của KPI.

Đối chiếu CSS trước/sau trên **cùng fixture trong bộ nhớ**, tại 1024px, với số tiền `9.999.999.999,99 đ` và một mô tả dài:

| Đo trong cùng hàng | Trước | Sau |
|---|---:|---:|
| Lệch vị trí số liệu | 36px | 0px |
| Lệch vị trí mô tả | 36px | 0px |
| Chênh chiều cao thẻ | 0px | 0px |

Đây là dữ liệu kiểm tra chiều dài nội dung, không phải số liệu database ứng dụng. Fixture thông thường đã căn hàng trước thay đổi; không kết luận mọi trang trước đây đều lệch.

KPI chuyển sang CSS Grid/Subgrid dùng chung ba hàng: tiêu đề/icon, số liệu, mô tả. Icon nằm trong grid. KPI và thống kê công ty/cơ sở/sân/dịch vụ dùng chung gap 18px và chia 3/2/1 cột theo chiều rộng **vùng nội dung**, nên thích ứng khi thu gọn sidebar. Không sửa công thức tiền, query, bộ lọc hay phạm vi thống kê.

**Booking gần đây:** không tái hiện lệch cột trong các fixture được kiểm tra ở bốn kích thước và các vai trò. Header/body khớp vị trí, chiều rộng và số cột. Không sửa partial Dashboard, bảng Booking, CSS của bảng hay dữ liệu Booking để xử lý yêu cầu này.

Ảnh/đo đối chiếu: [trước](../storage/temp/dashboard-employee-review/kpis-before-stress-1024.png), [sau](../storage/temp/dashboard-employee-review/kpis-after-stress-1024.png), [số đo](../storage/temp/dashboard-employee-review/layout-comparison.json).

## Quyền quản lý nhân viên

Module trước đây chỉ có route/menu cho Admin và Quản lý công ty; service từ chối Quản lý cơ sở. Query công ty còn loại hồ sơ quản lý công ty khỏi danh sách đọc. Đã tái sử dụng Controller/Service/Model/view hiện có, thêm bảy route GET/POST dưới `/facility/employees` với RoleMiddleware hiện có.

| Vai trò | Xem | Thêm/sửa/đổi trạng thái | Cấp tài khoản khi thêm |
|---|---|---|---|
| Admin tập đoàn | Toàn hệ thống | Giữ quyền hiện có trên toàn hệ thống | Giữ quy trình thêm quản lý công ty hiện có |
| Quản lý công ty | Toàn bộ nhân viên công ty mình, gồm quản lý công ty và mọi cơ sở trực thuộc | Nhân viên chưa có tài khoản, Nhân viên và Quản lý cơ sở trong công ty; không sửa bản thân/quản lý công ty ngang cấp hoặc Admin | Quản lý cơ sở hoặc Nhân viên; phải có cơ sở hợp lệ trong công ty |
| Quản lý cơ sở | Nhân viên tại cơ sở được phân công, kể cả hồ sơ quản lý chỉ đọc | Nhân viên chưa có tài khoản hoặc vai trò Nhân viên tại cơ sở mình; không sửa bản thân/quản lý ngang hoặc cao hơn | Chỉ Nhân viên tại cơ sở mình |
| Nhân viên thường / Khách hàng | Không có quyền quản lý nhân viên | Bị chặn endpoint | Không |

Scope lấy từ danh tính đăng nhập và kiểm tra quan hệ công ty–cơ sở. Query danh sách/chi tiết gắn điều kiện scope; cập nhật/trạng thái kiểm tra lại target role trong transaction đang khóa hồ sơ. Không dùng ID công ty/cơ sở của client để mở rộng quyền. URL ngoài phạm vi trả 404; yêu cầu nâng quyền/đổi cơ sở bị chặn 403 hoặc validation 422. CSRF và HTTP method giữ nguyên.

Danh sách hiển thị tên công ty, tên cơ sở, tên đăng nhập. Tìm kiếm bao gồm họ tên/tài khoản/email/điện thoại/tên công ty/tên cơ sở; có lọc cơ sở, vai trò, trạng thái, sắp xếp và phân trang server. Thêm/sửa/chi tiết dùng lại UI hiện có. Cơ sở trên form Quản lý cơ sở bị khóa và backend tự gán đúng cơ sở dù client bỏ trường này. Context trang/bộ lọc được bảo toàn qua chi tiết, sửa, validation và đổi trạng thái. Không thay đổi JS lưu vị trí sidebar hiện có.

Trang chi tiết chỉ SELECT thông tin nhân viên và danh tính công khai, không SELECT hoặc hiển thị password hash. Không bổ sung đổi mật khẩu, đổi vai trò, khóa tài khoản độc lập hay xóa nhân viên ngoài workflow hiện có. Đổi trạng thái vẫn là trạng thái nhân viên `DANG_LAM/DA_NGHI`, tuân theo kiểm tra đăng nhập hiện có.

## File thay đổi

| File ứng dụng | Nội dung |
|---|---|
| `public/assets/css/dashboard.css` | Grid/Subgrid, icon trong grid, thống nhất cột/gap/breakpoint |
| `routes/web.php` | Bảy route nhân viên Quản lý cơ sở, RoleMiddleware |
| `config/admin-menu.php` | Menu Quản lý nhân viên của công ty/cơ sở |
| `app/controllers/EmployeeController.php` | Ngữ cảnh actor thật, route/view dùng lại, scope và edit guard |
| `app/services/EmployeeService.php` | Scope cơ sở, quyền target, gán cơ sở phía server, allowlist vai trò cấp mới |
| `app/models/NhanVien.php` | Query scope cơ sở, đọc toàn công ty, tìm tài khoản/tên đơn vị |
| `app/services/AdminListService.php` | Chuẩn hóa filter danh sách cơ sở |
| `app/helpers/functions.php` | Bảo toàn return context cho module nhân viên cơ sở |
| `app/views/admin/partials/management-toolbar.php` | Bộ lọc chung cho công ty/cơ sở và nhãn tìm kiếm |
| `app/views/admin/employees/index.php` | Bổ sung tên cơ sở trong ô công ty |
| `app/views/company/employees/index.php` | View dùng chung theo basePath, tên đơn vị/tài khoản, hành động theo quyền |
| `app/views/company/employees/create.php` | Dùng lại cho cơ sở; khóa assignment và allowlist vai trò trên UI |
| `app/views/company/employees/edit.php` | Dùng lại cho cơ sở; form/back/context đúng khu vực |

Backend liên quan phần nhân viên của Chí cần review: `EmployeeController`, `EmployeeService`, `NhanVien` và các route/filter/helper nói trên.

Test cập nhật: `tests/admin-management-unit.php`, `tests/admin-navigation-unit.php`, `tests/employee-details-unit.php`, `tests/dashboard-browser.cjs`.

Test tạo mới: `tests/employee-scope-integration.php`, `tests/employee-scope-browser.cjs`, `tests/fixtures/employee-scope.php`.

Hai kỳ vọng quyền cũ được cập nhật theo yêu cầu mới: công ty đọc cả hồ sơ quản lý cùng công ty; cơ sở được đọc đúng phạm vi thay vì bị từ chối toàn bộ. Các assertion chặn ngoài phạm vi và kiểm tra secret/CSRF vẫn giữ, đồng thời bổ sung trường hợp mới. `tests/admin-list-unit.php` được giữ nguyên.

## Kiểm thử thực tế

| Bộ kiểm tra cuối | Kết quả |
|---|---|
| Chrome `tests/dashboard-browser.cjs` | **632 PASS / 0 FAIL** |
| Chrome `tests/employee-scope-browser.cjs` qua integration harness | **126 PASS / 0 FAIL** |
| `tests/employee-scope-integration.php --run` | **10 PASS / 0 FAIL**, ngoài các assertion Chrome |
| `tests/run.php` | 106 PASS |
| `tests/dashboard-unit.php` | 101 PASS |
| `tests/employee-details-unit.php` | 75 PASS |
| `tests/admin-management-unit.php` | 142 PASS |
| `tests/authorization-unit.php` | 262 PASS |
| `tests/admin-navigation-unit.php` | 390 PASS |
| `tests/account-layout.php` | 301 PASS |
| `tests/admin-list-unit.php` | **FAIL**, lỗi đã có trước, mô tả bên dưới |
| PHP lint 17 file PHP sửa/tạo; JS syntax hai file CJS; `git diff --check` | PASS |

Dashboard kiểm tra Tổng quan và Doanh thu ở **1440/1024/768/375px**, Admin/Công ty/Cơ sở/Nhân viên, số liệu scope chính xác, nội dung dài, căn hàng, biểu đồ/tooltip/bộ lọc/bảng, sidebar mở/thu gọn/mobile, trạng thái trống/lỗi. Không có lỗi JS/CSP/PHP trong fixture.

Nhân viên kiểm tra danh sách/thêm/sửa/chi tiết của công ty và cơ sở ở **1440/1024/768/375px**, sidebar compact/mobile, đăng nhập thật trên database test, tìm tài khoản/tên cơ sở, filter, page size, trang 2, validation, sửa và đổi trạng thái kèm modal xác nhận, giữ context/thông báo thành công. Kiểm tra sửa ID GET/POST, giả công ty/cơ sở/vai trò, self/peer manager, CSRF, nhân viên thường và khách hàng truy cập trực tiếp.

Harness MariaDB tạo database ngẫu nhiên `stryke_employee_test_<suffix>` từ **DDL hiện có**, không sao chép bản ghi ứng dụng. Mọi fixture và HTTP CRUD dùng database này qua entry point `public/index.php`, AuthService, middleware, controller/service/model thật. Không mock kết quả SQL/route/login. Kết nối đọc DDL database ứng dụng ở transaction READ ONLY. Database test được DROP trong `finally` sau mỗi lượt; không chạy migration, không tạo tài khoản thử trên database ứng dụng.

Lượt chạy đầu của test Chrome Nhân viên dừng vì selector tìm menu ba chấm thay vì nút thao tác trực tiếp. Đã sửa selector theo DOM hiện có; không đổi giao diện để ép test. Kết quả bảng trên là lượt chạy cuối đầy đủ.

### FAIL còn tồn tại

`tests/admin-list-unit.php:206` dừng tại `bo-mon action keeps database ID and list page`: test yêu cầu link `/admin/bo-mon/show`, nhưng `app/views/admin/partials/list-actions.php` hiện có ngoại lệ bỏ nút Xem riêng Bộ môn; tên Bộ môn trong bảng cũng không có anchor. Đây là lỗi đã được ghi nhận trước đợt này tại `docs/authorization-review.md`, không phải lỗi từ Dashboard/employee scope. Không sửa module Bộ môn hoặc giảm/bỏ assertion để báo xanh. Các assertion sau điểm dừng của file test này chưa được chạy trong kết quả chính thức.

Tổng PHP liên quan: **7/8 file PASS**, 1 file FAIL; 1.377 assertion thuộc bảy bộ PASS. Không kết luận toàn bộ test dự án PASS.

## Ảnh và log

- [Doanh thu Admin 1440px](../storage/temp/dashboard-review/group-1440-top.png)
- [Tổng quan công ty 1024px](../storage/temp/dashboard-review/company-dashboard-1024.png)
- [Doanh thu cơ sở 768px](../storage/temp/dashboard-review/facility-768-top.png)
- [Doanh thu mobile 375px](../storage/temp/dashboard-review/group-375-top.png)
- [Nhân viên công ty 1440px](../storage/temp/dashboard-employee-review/company-employees-1440.png)
- [Nhân viên cơ sở 1440px](../storage/temp/dashboard-employee-review/facility-employees-1440.png)
- [Thêm nhân viên cơ sở 375px](../storage/temp/dashboard-employee-review/facility-create-375.png)
- [Sửa nhân viên cơ sở 768px](../storage/temp/dashboard-employee-review/facility-edit-768.png)
- [Chi tiết nhân viên cơ sở 375px](../storage/temp/dashboard-employee-review/facility-show-375.png)
- [Kết quả Chrome Dashboard](../storage/temp/dashboard-review/browser-results.json)
- [Kết quả Chrome nhân viên](../storage/temp/dashboard-employee-review/employee-browser-results.json)
- [Kết quả PHP](../storage/temp/dashboard-employee-review/php-results.json)

## Giới hạn

- Schema hiện tại chỉ biểu diễn **một cơ sở được gán** qua `nhan_vien.ma_co_so`, chưa có bảng gán nhiều cơ sở. Quản lý cơ sở hiện quản lý đúng cơ sở này; chưa tự mở quyền sang cơ sở khác hoặc thay schema để hỗ trợ nhiều cơ sở.
- Cần tài khoản đang hoạt động, hồ sơ nhân viên và quan hệ công ty/cơ sở hợp lệ theo AuthService hiện có. Không tạo/sửa các cấu hình này trên database ứng dụng.
- Chrome CRUD được chứng minh trên MariaDB test riêng với schema local hiện tại. Chưa thử tạo/sửa nhân viên thật, môi trường triển khai khác hoặc toàn bộ bộ test booking/payment không thuộc phạm vi.
- Không sửa booking/payment/inventory, schema, công thức doanh thu hay bảng Booking gần đây.
