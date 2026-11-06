# STRYKE — FULL SYSTEM AUDIT & TEST REPORT

Ngày kiểm thử: **06/11/2026**, múi giờ **Asia/Ho_Chi_Minh (+07:00)**. Đối tượng: working tree hiện tại, gồm cả các thay đổi chưa commit của nhóm. Đây là báo cáo kiểm thử, **không phải bản sửa lỗi**.

## 1. Executive Summary

**Kết luận: NOT READY để nghiệm thu/release toàn hệ thống.** Một số luồng quan trọng đã được kiểm chứng bằng MySQL, HTTP và Chrome thật, nhưng còn lỗi bảo vệ booking chờ xác nhận, bất nhất schema cài mới, thiếu kiểm tra quan hệ booking–payment tại service hóa đơn, dữ liệu cũ cần đối soát và bộ test hồi quy chưa chạy sạch.

- Đã kiểm kê **202 endpoint**, **5 vai trò**, **18 bảng đang có trong database ứng dụng**.
- PHP lint: **256 file đạt**. JavaScript syntax: **34 file đạt**. Composer validate/platform requirements và autoload **88 class/interface đạt** trên Windows hiện tại.
- **50 lượt chạy suite/nhóm kiểm thử**: **30 PASS, 18 FAIL, 2 BLOCKED**. Đây là số lượt chạy, không phải tổng số assertion và không phải tỷ lệ hoàn tất toàn hệ thống.
- Riêng 16 suite unit/fixture hoàn tất có **5.843 assertion đạt**. Không cộng assertion đã chạy dở trong các suite FAIL thành kết quả PASS của suite.
- **9 vấn đề**: **0 CRITICAL, 4 HIGH, 5 LOW** theo Severity. Priority: **6 HIGH, 3 LOW**. Không có kết luận rằng hệ thống không còn lỗ hổng chưa được phát hiện.
- Các kiểm thử tạo dữ liệu đều dùng database test riêng được kiểm tra tên/đích kết nối hoặc SQLite RAM; **không chạy migration, INSERT/UPDATE/DELETE trên database ứng dụng**. Các GET đọc công khai không gọi job hết hạn.
- Người dùng đã cho phép artifact tạm: session, log, Chrome profile, screenshot và ảnh upload thử. Không cài dependency, không sửa source để làm test xanh.

Các kết quả đáng tin cậy đã xác minh:
1. Hai khách tranh cùng sân/giờ: một booking thành công, bên còn lại HTTP/service 409. Khác giờ: cả hai thành công.
2. Cùng idempotency key và nội dung: trả cùng booking ID. Đặt nhiều sân và tranh tồn dịch vụ không làm vượt tài nguyên trong các ca đã chạy.
3. Upload biên lai hợp lệ trước hạn chuyển sang CHO_XAC_NHAN, payment chưa bị đánh dấu thành công; sau một ngày vẫn chờ duyệt và giữ tài nguyên.
4. Upload lúc quá hạn/đợi khóa, expiry và duyệt đồng thời, duyệt lặp: các bộ integration chuyên biệt đạt.
5. Duyệt hợp lệ tạo một hóa đơn, trừ tồn một lần; lỗi hóa đơn rollback trạng thái và tồn.
6. Phân quyền khách hàng quản lý và cấp tài khoản nhân viên được kiểm thử độc lập; ảnh biên lai có kiểm soát quyền, không lấy ảnh theo tên file do client tùy ý chỉ định.

## 2. Phạm vi, môi trường và cách đọc kết quả

### 2.1. Môi trường

| Thành phần | Giá trị quan sát |
|---|---|
| Workspace | C:/xampp/htdocs/BaiTapNhomPHP |
| Git branch / HEAD | kiem-thu-3 / 5562347 — Merge pull request #30 |
| PHP | XAMPP PHP 8.2.12 |
| Database | MariaDB 10.4.32, InnoDB; kết nối ứng dụng +07:00 |
| Node | v22.14.0 |
| Browser | Chrome cài tại C:/Program Files/Google/Chrome/Application/chrome.exe |
| Composer | Composer có sẵn; dependency chính vlucas/phpdotenv theo composer.json/lock |
| Browser automation | Chrome DevTools Protocol của các test sẵn có; playwright-core chưa có |
| Live HTTP entry | http://localhost/BaiTapNhomPHP/public/index.php |
| Test data | Fixture tổng hợp trong DB riêng/RAM; không sao chép dữ liệu cá nhân thật vào seed |

Đã khảo sát cấu trúc app/core/routes/config/database/bin, controller/model/service/middleware, view/layout, JS/CSS và test liên quan theo module. Không khẳng định mọi dòng source hoặc mọi tổ hợp đầu vào đã được kiểm chứng runtime. Danh sách route đầy đủ ở Phụ lục A; các khoảng trống được nêu riêng.

### 2.2. Quy ước

- **PASS**: test thực sự chạy và đạt trong môi trường ghi rõ.
- **FAIL**: test thực sự chạy và không đạt. Cần phân biệt lỗi sản phẩm với lỗi harness/assertion cũ.
- **BLOCKED**: đã thử chạy nhưng thiếu điều kiện, ví dụ module browser automation không có.
- **NOT RUN**: ca/suite chưa chạy; không biến thành PASS chỉ từ đọc code.
- **CODE REVIEW ONLY**: chỉ có bằng chứng source/DDL, chưa xác minh runtime của ca đó.
- Trạng thái bug Confirmed có thể dựa trên runtime hoặc một mâu thuẫn source xác định; loại bằng chứng luôn được ghi rõ. Không coi CODE REVIEW ONLY là test PASS.

### 2.3. An toàn và tính nguyên vẹn

DB ứng dụng chỉ được truy vấn metadata/SELECT trong transaction READ ONLY khi kiểm tra dữ liệu. Các script integration đã được đọc guard, cách chọn DSN, tạo fixture và cleanup trước khi chạy. Hai script auth/catalog vốn dùng DB đang cấu hình đã được bọc bằng orchestrator với DB audit riêng, biến DB_NAME truyền vào cả PHP server và tiến trình test; không chạy chúng trực tiếp với cấu hình ứng dụng.

Các DB tự tạo có tiền tố test và hậu tố ngẫu nhiên; ví dụ các lượt tái hiện bổ sung:
- stryke_audit_test_4ee20871a3: bảo vệ tài nguyên, schema cài mới, quan hệ invoice; đã DROP trong finally.
- stryke_booking_test_c3f7bd11e5: tranh lịch/idempotency/tồn; đã DROP.
- stryke_audit_test_c3cb1eebb7: auth HTTP; đã DROP.
- stryke_audit_test_a31070bf9d: hai suite auth/catalog; đã DROP.

Có một schema mang tên stryke_receipt_test_68d63bbf33 còn hiện diện khi liệt kê read-only cuối lượt. Chưa xác định quyền sở hữu/thời điểm tạo nên **không xóa** và không coi là database ứng dụng.

Fingerprint 252 file source/config/schema/CSS/JS trước và sau test giống nhau:
R/EkDeBXL95pzXcMumI4+31dUSU4RygVB8E29LYlKp8=

Working tree đã có thay đổi trước audit tại các module Employee, Customer, CourtBrowser, detail-services, layout/admin CSS và routes. Không revert hoặc ghi đè chúng. Bản báo cáo này và artifact kiểm thử là đầu ra của audit; không có bản vá PHP/JS/CSS/SQL, commit, push hay merge.

## 3. Danh sách module/chức năng phát hiện từ source

| Module | Chức năng thực sự có | Entry / file chính |
|---|---|---|
| Auth / tài khoản | Đăng ký KH, đăng nhập/đăng xuất, throttle, profile, đổi mật khẩu, điều hướng intended có allowlist | AuthController, AccountController, AuthService, AccountService, Session, CSRF |
| Tập đoàn / công ty | Dashboard, danh sách/tạo/sửa/trạng thái công ty, thông tin công ty, quản lý hợp đồng | CompanyController/Service, ContractController/Service, CongTyNhuongQuyen, HopDongNhuongQuyen |
| Nhân viên | List/filter/pagination, thêm/sửa/chi tiết, trạng thái làm việc, cấp tài khoản mới hoặc cấp bổ sung | EmployeeController/Service, NhanVien, AccountService |
| Danh mục | Bộ môn, loại sân; CRUD/status và kiểm tra tham chiếu | BoMon/LoaiSan controller/model/service |
| Cơ sở / sân | CRUD, trạng thái, giờ hoạt động, địa chỉ offline, ảnh đại diện/logo, scope công ty/cơ sở | CoSo/San controller/model/service, FacilityScope, FacilityImage |
| Giá | Quản lý bảng giá theo cơ sở/loại sân/ngày/giờ/hiệu lực, chống overlap; bảng giá công khai có rowspan/gộp ngày đủ điều kiện | BangGiaService/Model, BookingPricingService, CourtPriceList |
| Dịch vụ | Loại dịch vụ, đơn vị, giá, trạng thái, tồn nullable, version chống ghi đè, giữ tồn qua booking | DichVuService/Model, BookingInventoryService |
| Tìm sân công khai | Search tên, bộ môn, tỉnh/phường; drawer chi tiết, tabs, ảnh hiện có; schedule và realtime polling | CourtBrowserController/Model, detail-* views, courts*.js |
| Booking khách | Quote/start, draft session, details/review, confirm idempotent, giữ 6 phút, wait/result, cancel/expire/retry payment | BookingController/Service/Model, booking.js, schedule.js |
| Thanh toán | Payment request/ref, VietQR proxy/download, trạng thái, upload ảnh, nhận minh chứng, admin duyệt thủ công/đối soát | PaymentController/Service, BookingReceiptService, ReceiptImage, PaymentVerificationService |
| Booking nội bộ | Scoped list/show/filter, xác nhận/đối soát/hủy theo quyền, xem receipt | AdminBookingController, AdminBooking, BookingManagementService |
| Hóa đơn / doanh thu | Hóa đơn từ xác nhận, list/show/print, unique booking/payment, dashboard/revenue theo scope | HoaDon, InvoiceService, AdminInvoiceController, DashboardService |
| Khách hàng quản lý | Chỉ list/search/page/show/statistics và lịch sử trong phạm vi booking của đơn vị | CustomerController, CustomerManagementService, ManagedCustomer |
| Lịch sử khách | Bộ lọc trạng thái/ngày tạo/sort/page, thống kê, snapshot chi phí, ghi chú, ảnh bảo vệ và modal | BookingHistoryController, Booking::history, history.php, booking-history.js |
| Vận hành hết hạn | CLI opt-in, GET_LOCK theo DB, batch/index, transaction ngắn, log và guard biên lai/payment | bin/expire-bookings.php, Booking::expire |

Không tìm thấy chức năng backend hoàn chỉnh để gửi đánh giá, yêu thích, bản đồ, đặt sự kiện, reset mật khẩu/remember-me/MFA. Một số tab/nút giới thiệu là trạng thái chờ phát triển; không coi là chức năng đã hoàn tất.

Không tìm thấy route/hàm cập nhật booking sang HOAN_THANH trong danh sách đường ghi hiện tại; schema, filter và view có hỗ trợ hiển thị trạng thái đó. Ca “hoàn thành booking từ đầu đến cuối” được đánh dấu NOT RUN/chưa có workflow tương ứng, không suy luận từ enum.

Nguồn giao dịch ngân hàng xác thực là interface/adapter; chưa có endpoint callback/provider hoàn chỉnh để audit end-to-end. Duyệt thủ công có code riêng và đã được test; upload ảnh không phải xác nhận tiền tự động.

## 4. Vai trò và ranh giới dữ liệu

| Vai trò | Phạm vi quan sát từ code | Bằng chứng kiểm thử |
|---|---|---|
| KHACH_HANG | Dữ liệu booking/invoice/receipt của ma_khach_hang trong identity; /courts công khai | Auth tùy chỉnh 18 ca; receipt HTTP 101; history fixture; anti-owner HTTP |
| QUAN_TRI_TAP_DOAN | Catalog/company/contracts và phạm vi nội bộ toàn hệ thống; không được suy ra quyền KH từ role root | Authorization unit 262; navigation unit 390; receipt approval HTTP |
| QUAN_LY_CONG_TY | ma_cong_ty từ identity, toàn cơ sở thuộc công ty; giới hạn vai trò nhân viên được cấp | ManagedCustomer 162; employee integration 48; các scoped fixture |
| QUAN_LY_CO_SO | Công ty + ma_co_so phân công từ identity; không tự chọn scope bằng request | Employee integration/authorization và customer browser 29 |
| NHAN_VIEN | Cơ sở được giao; catalog vận hành chỉ đọc; booking confirmation theo scope/can_confirm hiện có | Authorization fixture/unit; customer scoped tests; chưa chạy mọi thao tác ghi MySQL theo role |

FacilityScope tái sử dụng BookingManagementService::resolveScope; AuthService::isActive kiểm tra tài khoản, hồ sơ nhân viên, công ty/cơ sở còn hoạt động và liên kết khớp. Schema hiện biểu diễn **một cơ sở cho một nhân viên** qua nhan_vien.ma_co_so; không thấy bảng phân công nhiều cơ sở.

ManagedCustomer lọc ngay trong SQL qua booking + co_so, COUNT DISTINCT khách và COUNT booking trong scope. Receipt tải qua controller/service kiểm tra customer hoặc scope nội bộ; đường dẫn private/legacy chỉ được resolve khi liên kết đúng booking. Không xuất password hash/token trong màn hình quản lý.

Đây là kết quả từng ca ở ma trận dưới, không phải chứng nhận không còn IDOR trên mọi tổ hợp 202 endpoint.

## 5. Kết quả kiểm thử và ma trận

### 5.1. Các lượt chạy

| Nhóm | PASS | FAIL | BLOCKED | Tổng |
|---|---:|---:|---:|---:|
| Unit / fixture PHP | 16 | 14 | 0 | 30 |
| Integration MySQL ban đầu | 2 | 2 | 0 | 4 |
| HTTP/Chrome/JS ban đầu | 7 | 0 | 2 | 9 |
| Auth/catalog trên DB audit riêng | 0 | 2 | 0 | 2 |
| Chrome bổ sung | 3 | 0 | 0 | 3 |
| Auth + concurrency tùy chỉnh | 2 | 0 | 0 | 2 |
| **Tổng lượt chạy** | **30** | **18** | **2** | **50** |

18 FAIL không đồng nghĩa 18 lỗi sản phẩm: 17 lượt dừng ở fixture/assertion không phù hợp; một lượt facility-services phát hiện thiếu đơn vị giá thực tế. Các lỗi sản phẩm bổ sung được tái hiện bằng probe riêng trong mục 8, không “chuyển xanh” các suite đang FAIL.

Các kiểm tra nền riêng: PHP lint 256/256; JS parse 34/34; Composer validate (cảnh báo thiếu license, không chặn); check-platform-reqs; autoload 88/88; tất cả đã thực sự chạy. Composer không chạy update/install/audit qua mạng.

| ID | Lượt chạy | Kết quả | Evidence / phạm vi |
| RUN-01 | tests/run.php | **PASS** | PASS: 106 checks. No real database was connected or modified. |
| RUN-02 | tests/admin-navigation-unit.php | **PASS** | PASS: 390 Admin navigation checks; no database. |
| RUN-03 | tests/admin-list-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-04 | tests/admin-management-unit.php | **PASS** | PASS: 142 management pagination/scope/money checks; SQLite :memory: only. |
| RUN-05 | tests/account-layout.php | **PASS** | OK: 310 account layout checks across five roles; no database used. |
| RUN-06 | tests/authorization-unit.php | **PASS** | PASS: 262 authorization checks; disposable SQLite RAM only. |
| RUN-07 | tests/bank-admin-unit.php | **PASS** | PASS: 131 bank Admin scope, routes, CSRF and view checks; SQLite RAM only. |
| RUN-08 | tests/booking-actions-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-09 | tests/booking-history.php | **PASS** | PASS: 48 history checks; read-only SQLite fixture, no application DB. |
| RUN-10 | tests/booking-inventory-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-11 | tests/booking-management-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-12 | tests/booking-pr6-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-13 | tests/booking-pr6-contract.php | **FAIL** | Exit 1; lỗi cụ thể ở mục 5.2 |
| RUN-14 | tests/catalog-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-15 | tests/court-flow-layout.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-16 | tests/court-prices.php | **PASS** | PASS: 21 price-list checks (isolated SQLite). |
| RUN-17 | tests/courts-unit.php | **PASS** | PASS: 38 schedule checks; no database. |
| RUN-18 | tests/customer-management.php | **PASS** | PASS: 162 scoped SQL/controller/middleware/view/read-only checks; SQLite memory only. |
| RUN-19 | tests/dashboard-unit.php | **PASS** | PASS: 101 Dashboard revenue, scope, date, schema uniqueness and real-route checks; SQLite RAM only. |
| RUN-20 | tests/employee-details-unit.php | **PASS** | PASS: 75 employee detail/scope/read-only/no-secret checks; SQLite :memory: only. |
| RUN-21 | tests/facility-address.php | **PASS** | PASS: 3337 address/image checks. No database used. |
| RUN-22 | tests/facility-services.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-23 | tests/facility-unit.php | **PASS** | OK: 109 facility unit checks; no database used. |
| RUN-24 | tests/invoice-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-25 | tests/manual-payment-sql-unit.php | **FAIL** | Exit 1; lỗi cụ thể ở mục 5.2 |
| RUN-26 | tests/payment-verification-unit.php | **FAIL** | Exit 1; lỗi cụ thể ở mục 5.2 |
| RUN-27 | tests/pricing-unit.php | **PASS** | PASS: 567 pricing checks in memory; no MySQL connection or database writes. |
| RUN-28 | tests/receipt-confirmation-unit.php | **FAIL** | Exit 1; lỗi cụ thể ở mục 5.2 |
| RUN-29 | tests/service-unit.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-30 | tests/vietqr-bank-unit.php | **PASS** | PASS: 44 VietQR, Facility Bank, and Anti-IDOR checks passed cleanly in memory! |
| RUN-31 | tests/booking-expiry-integration.php --run | **PASS** | PASS: 33 expiry integration checks; disposable DB removed on exit. |
| RUN-32 | tests/receipt-integration.php --run | **PASS** | 24 receipt integration checks passed in disposable database. |
| RUN-33 | tests/booking-integration.php --run | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-34 | tests/facility-migration-integration.php --run | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-35 | tests/receipt-hold-http.php | **PASS** | PASS 101 HTTP checks |
| RUN-36 | tests/employee-account-integration.php | **PASS** | PASS: 21 actual Chrome desktop/mobile form checks. |
| RUN-37 | tests/customer-management-browser.cjs | **PASS** | PASS: 29 Chrome/HTTP checks. Screenshots: C:\xampp\htdocs\BaiTapNhomPHP\storage\temp\customer-browser-O8sODw |
| RUN-38 | tests/facility-services-browser.cjs | **PASS** | PASS: 32 actual Chrome/HTTP checks; SQLite RAM only. Screenshots: C:\xampp\htdocs\BaiTapNhomPHP\storage\temp\facility-services-cpZgGf |
| RUN-39 | tests/payment-state.cjs | **PASS** | PASS: payment/receipt polling and expiry guards. |
| RUN-40 | tests/schedule-interaction.cjs | **PASS** | PASS: realtime hold/release, valid selection preservation, exact 09:30 lock, future date, offline, tab resume and stale response rejection. |
| RUN-41 | tests/booking-modal.cjs | **PASS** | PASS: delegated booking modal links, facility destination, close button, overlay and focus restoration. Native Escape/focus trap require browser. |
| RUN-42 | tests/repeat-booking-integration.php | **BLOCKED** | MODULE_NOT_FOUND: playwright-core; raw exit 255 |
| RUN-43 | tests/employee-scope-integration.php | **BLOCKED** | MODULE_NOT_FOUND: playwright-core; raw exit 255 |
| RUN-44 | auth-integration.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-45 | catalog-integration.php | **FAIL** | Exit 255; lỗi cụ thể ở mục 5.2 |
| RUN-46 | tests/facility-services-browser.cjs [runtime viewport variant 360/768/1024/1440] | **PASS** | 62 actual Chrome/HTTP checks. SQLite RAM only. Runtime harness viewport array extended without editing test source. Artifacts: storage/temp/facility-services-0miWox. |
| RUN-47 | tests/booking-history-browser.cjs | **PASS** | 75 Chrome checks; 1440/1280/768/390/360, rendered history fixture and live read-only public guest/home/courts GET. Artifacts: storage/temp/history-review. |
| RUN-48 | tests/booking-review.cjs | **PASS** | 31 Chrome DOM/AJAX checks. PHP-rendered view/real JS, mock HTTP only: apply/cancel/delete/rollback/network/CSRF/conflict/idempotent rapid click, note listener and fingerprint. |
| RUN-49 | AUDIT-AUTH (stdin PHP, source không sửa) | **PASS** | 18 assertions; C:\xampp\htdocs\BaiTapNhomPHP/storage/temp/audit-auth-c00b6ecba8 |
| RUN-50 | AUDIT-RACE (stdin PHP + worker hiện có) | **PASS** | 7 assertions; same court/different slots/same idempotency key/multi-court/stock. |

### 5.2. Các suite FAIL/BLOCKED: nguyên nhân, không đánh tráo kết quả

| Suite | Điểm dừng thực tế | Phân loại |
|---|---|---|
| admin-list-unit.php:206 | Mong có action show cho bo-mon; partial hiện chủ động bỏ action view của module đó | Assertion giao diện cũ, chưa chứng minh lỗi route |
| booking-actions-unit.php:21 | Mong CHO_XAC_NHAN hết quyền khi deadline cũ đã qua | Expectation trái nghiệp vụ chờ duyệt không giới hạn đã được chốt |
| booking-inventory-unit.php:115; booking-pr6-unit.php | Fake statement so khớp chuỗi SQL cũ, không nhận predicate CHO_XAC_NHAN mới | Fixture không tương thích; MySQL giữ tồn được test riêng |
| booking-management-unit.php:565; invoice-unit.php | Fake PDO chưa init/không override exec cho SET TRANSACTION | Lỗi harness, không chứng minh MySQL thật lỗi |
| booking-pr6-contract.php; manual-payment-sql-unit.php; payment-verification-unit.php; receipt-confirmation-unit.php | SQLite báo near SET syntax error tại BookingManagementService.php:271 | Dialect fixture không tương thích MySQL isolation statement |
| catalog-unit.php:198 | So khớp markup navbar cũ, trước SVG/span | Assertion cũ |
| court-flow-layout.php:23 | Fixture render home thiếu global app, main.php:82 dùng app | Fixture thiếu context; home HTTP thật vẫn tải |
| facility-services.php:31 | Không có chuỗi đơn vị đ/giờ ở bảng công khai | **Lỗi sản phẩm BUG-007**, không bỏ qua như fixture |
| service-unit.php:252 | Allowlist link/sidebar test chưa chấp nhận /admin/customers dùng chung đã có role guard | Test contract cũ; Customer unit/Chrome đạt |
| booking-integration.php:93 | Mong CHO_THUE luôn bị từ chối; code/contract hiện đã mở loại dịch vụ này | Test chính thức FAIL, các đoạn phía sau trong suite chưa chạy; race được test bổ sung riêng |
| facility-migration-integration.php → facility-integration.php:208 | Test chỉ cho root có admin-sidebar, nhưng layout mới cho các role nội bộ | Fixture expectation cũ; migration-preserve đã đạt, phần sau suite chưa chạy |
| auth-integration.php:96 | So chuỗi lỗi đăng nhập cũ; controller trả thông báo generic mới | Suite FAIL; 18 ca auth HTTP riêng đã kiểm chứng luồng hiện tại |
| catalog-integration.php:163 | Mong các link quản lý cũ trên /admin thay vì sidebar/workspace hiện tại | Suite FAIL/dừng sớm, cleanup hash fixture đã đạt trong DB test |
| repeat-booking-integration.php; employee-scope-integration.php | Cannot find module D:/KiemThuPM/SOT357-65134364/node_modules/playwright-core | **BLOCKED**, giữ raw exit 255, không cài dependency |

Một số harness SQLite cố tình thay dialect/identity để cô lập dữ liệu; PASS ở đó không thay thế bằng chứng transaction/concurrency MySQL. Không sửa test hoặc production source để bỏ qua các assert đang FAIL.

### 5.3. Ma trận theo chức năng

Ký hiệu role: PUBLIC, KH, ROOT, CT, CS, NV tương ứng công khai/khách/tập đoàn/công ty/cơ sở/nhân viên. Một dòng là nhóm ca có cùng phương pháp, không gộp toàn module thành PASS.
| ID | Module / vai trò | Route | File chính | Test case | Kết quả | Bằng chứng / giới hạn |
|---|---|---|---|---|---|---|
| A01 | Auth — PUBLIC | /register, /login | AuthController; RegistrationService | Đăng ký sai/đúng/trùng, role injection, hash, login sai/đúng | **PASS** | 18 ca HTTP audit trên MySQL riêng |
| A02 | Auth — KH | /booking/history → /login → /register | AuthMiddleware; AuthController; Session | Intended còn sau register lỗi/thành công và login sai; normal login về mặc định; URL ngoài bị bỏ | **PASS** | HTTP audit tùy chỉnh |
| A03 | Auth — KH | /account; /admin; /logout | AuthService; Router | Profile escape XSS, CSRF register, logout, role deny, khóa account revoke session | **PASS** | HTTP audit tùy chỉnh |
| A04 | Auth — Tất cả | /account/password | PasswordChangeService; Session | Validation/password/session-CSRF contracts | **PASS** | tests/run.php và account-layout; không phải full HTTP đổi mật khẩu |
| A05 | Auth — KH | /account/password | AccountController | Đổi mật khẩu thật + phiên thứ hai sau đổi mật khẩu | **NOT RUN** | Suite HTTP cũ dừng trước đoạn này |
| A06 | Auth — PUBLIC | /login | LoginThrottle | Tấn công phân tán/proxy, nhiều node, rate-limit stress | **NOT RUN** | Không tạo tải hoặc dùng dịch vụ ngoài |
| B01 | Công ty / hợp đồng — ROOT | /admin/companies*, /admin/contracts* | CompanyService; ContractService; models | Validation/money/filter/page/list ownership contracts | **PASS** | admin-management-unit, dashboard-unit; RAM/fixture |
| B02 | Công ty / hợp đồng — ROOT | POST company/contract endpoints | CompanyController; ContractController | Full MySQL CRUD/status/company-account lifecycle qua browser | **NOT RUN** | Chưa có lượt E2E toàn chu trình tương ứng |
| C01 | Phân quyền — ROOT/CT/CS/NV | Admin/company/facility/staff catalog routes | RoleMiddleware; FacilityScope | Tenant IDOR/role/mass-assignment contracts trong fixture | **PASS** | authorization-unit 262; scope unit |
| C02 | Cơ sở — CT/CS | /company/co-so*, /facility/co-so* | CoSoService; FacilityImage | Địa chỉ offline/ảnh/path/MIME validation, scope và rollback contract | **PASS** | facility-address 3337, facility-unit 109 |
| C03 | Cơ sở / sân — ROOT/CT/CS | POST co-so/update; san/update | CoSo::activeBookings; San::related | Đổi giờ hoặc ngừng sân khi còn review tương lai | **FAIL** | AUDIT repro: count=0; cả hai save được chấp nhận |
| C04 | Cơ sở / sân — ROOT/CT/CS | Facility/court CRUD + upload HTTP | CoSo/San controllers | Toàn bộ CRUD/upload sau migration và rollback thực | **NOT RUN** | Suite migration dừng ở layout assertion |
| D01 | Bảng giá — ROOT/CT/CS | /admin/company/facility/bang-gia* | BangGiaService; BookingPricingService | Overlap/hiệu lực/scope/rounding/30 phút, grouping ngày/type | **PASS** | pricing-unit 567; court-prices 21 |
| D02 | Bảng giá — PUBLIC | /courts/prices; /courts/panel | CourtPriceList; CourtBrowser | Dữ liệu các loại/ngày khác nhau không gộp sai | **PASS** | court-prices + fixture/browser; không phải E2E admin nhập giá thật |
| D03 | Bảng giá công khai — PUBLIC | /courts/panel (tab dịch vụ) | detail-services.php | Có hiển thị đơn vị đ/giờ | **FAIL** | facility-services.php:31 và screenshot |
| E01 | Dịch vụ / tồn — KH | /booking/confirm | BookingService; BookingInventoryService | Hai khách tranh 2 đơn vị tồn ở khác sân; chỉ một thành công; tồn vật lý chưa trừ | **PASS** | AUDIT-RACE; held=2, physical=2 |
| E02 | Dịch vụ / tồn — ROOT/CT/CS/NV | Admin dịch vụ endpoints | DichVuService; DichVu | Scope/write guard/version và đơn vị/tồn contract | **CODE REVIEW ONLY** | service-unit dừng sớm; chưa full CRUD MySQL dịch vụ |
| E03 | Dịch vụ / tồn — Nội bộ | Confirm/cancel/expire | BookingInventoryService; BookingManagementService | Duyệt trừ một lần, lỗi invoice rollback, hủy/expiry giải phóng giữ | **PASS** | receipt-integration; receipt HTTP; expiry integration |
| E04 | Dịch vụ bổ sung UI — KH/PUBLIC | /booking/details, /booking/review | booking.js; booking-details.php | Nháp modal không AJAX; Apply/delete/failure/rapid clicks/fingerprint/note | **PASS** | booking-review 31 Chrome checks; response AJAX MOCK |
| E05 | Dịch vụ bổ sung HTTP — KH | /booking/review | BookingController::review | Full DOM + MySQL authoritative quote sau mọi kiểu stock/price mutation | **NOT RUN** | Không nhầm MOCK response với backend tích hợp đầy đủ |
| F01 | Tìm sân/chi tiết — PUBLIC | /courts; /courts/show; /courts/panel | CourtBrowserController; detail-tabs | Đúng cơ sở/empty/active, drawer, 5 tabs/keyboard, không fake catalog | **PASS** | facility-services browser 32 + variant62; RAM |
| F02 | Responsive — PUBLIC | /courts + drawer | courts.js; detail CSS | 360/768/1024/1440 không tràn trang; tab/booking link còn đúng | **PASS** | Chrome variant62, screenshots đã xem 360px |
| F03 | Lịch sân — PUBLIC | /courts/schedule | CourtScheduleService; schedule.js | 30 phút, mở/đóng DB, past/08:31, held/booked/free/private projection | **PASS** | courts-unit 38; schedule interaction VM; HTTP suite chuyên biệt có schedule |
| F04 | Lịch/realtime — PUBLIC | /courts/schedule?status=1 | schedule.js | Multi-court/gaps/grouping/deselect, stale polling/offline/resume/expiry/zoom | **PASS** | schedule-interaction.cjs — VM, chưa đo geometry browser |
| F05 | Lịch geometry — PUBLIC | /courts/schedule | schedule.css/js | Sticky boundary/fit/zoom/horizontal scroll ở tất cả viewport với dữ liệu dài | **NOT RUN** | 36 slot render được kiểm tra; chưa làm đủ kiểm tra geometry tương tác thật |
| G01 | Booking — KH | /booking/confirm | BookingService::confirm | Hai khách cùng slot → đúng một ID; khác slot đều thành công | **PASS** | AUDIT-RACE: id1/409, id2/id3 |
| G02 | Booking — KH | /booking/confirm | BookingService; UNIQUE khoa_yeu_cau | Hai request cùng key/customer/content → cùng ID | **PASS** | AUDIT-RACE: cả hai ID4 |
| G03 | Booking — KH | /booking/confirm | BookingService::lockScope | Multi-court tranh một sân chung, không tạo hai booking chiếm chung | **PASS** | AUDIT-RACE: ID5/409; cần thêm stress nhiều vòng |
| G04 | Booking — KH | /booking/confirm | BookingService | Idempotency key khác khách/nội dung; orphan/partial write toàn bộ lỗi | **CODE REVIEW ONLY** | Có guard; suite general dừng trước các assertion này |
| G05 | Hết hạn — KH/CLI | /booking/expire; CLI | Booking::expireOne; expirePending | Đúng deadline, còn hạn không hết, job chồng, lock-wait/callback, bounded batch/index | **PASS** | booking-expiry-integration 33 |
| G06 | Chờ xác nhận — KH/Nội bộ | receipt/status/history/admin | BookingReceiptService; BookingManagementService | Review sau 1 ngày/3 ngày không hết hạn, giữ sân/tồn, không tự confirm | **PASS** | receipt HTTP101 + integration24 |
| G07 | Dữ liệu cũ — KH/Nội bộ | history/admin/schedule | Booking::effectiveStatusSql | Ảnh cũ đã tiếp nhận nhưng booking vẫn pending #84/#86 | **FAIL** | DB READ ONLY + resolver; xem BUG-004 |
| G08 | Hoàn thành — Nội bộ | Không tìm thấy transition endpoint | dat_san enum; history/dashboard | Chuyển DA_XAC_NHAN → HOAN_THANH qua UI/API | **NOT RUN** | Chưa có workflow tìm được; không sửa trong audit |
| H01 | Upload minh chứng — KH | POST /booking/receipt | BookingReceiptService; ReceiptImage | MIME/size/no-file/CSRF/owner, success/duplicate/late/lock-wait | **PASS** | HTTP101 + receipt integration24; Chrome real upload |
| H02 | Payment result — KH | /booking/result; /booking/wait | BookingController::waiting; payment-result | Sau upload đi result, refresh đúng, không banner00:00, không giả thanh toán | **PASS** | receipt-hold browser 1440/390 + HTTP |
| H03 | Receipt privacy — KH/CT/CS/NV | /booking/receipt; /booking/history/receipt | BookingReceiptService::authorized | Khác customer bị từ chối, legacy/private đúng file | **PASS** | Receipt HTTP/Chrome; read-only live file resolver |
| H04 | VietQR — KH | /payment/qr | PaymentController::qrImage; VietQRService | Header/content/expiry/ownership contract | **PASS** | vietqr-bank-unit44 — memory, không tải provider thật |
| H05 | VietQR thực — KH | /payment/qr?download=1 | VietQRService | Tải ảnh provider, so QR payload và quét bằng banking app | **NOT RUN** | Không giao dịch ngân hàng/không quét thực; general booking suite dừng sớm |
| H06 | Ngân hàng — ROOT/CT/CS/NV | Bank routes | BankController; TaiKhoanNganHang | Scope/roles/CSRF/default-account contracts | **PASS** | bank-admin-unit131; chưa browser CRUD MySQL bank |
| H07 | Provider callback — Nội bộ | Chưa có callback endpoint thực | BankTransactionSource; PaymentVerificationService | Verify chữ ký/provider/late callback ngoài hệ thống | **BLOCKED** | Thiếu nguồn provider/API hoàn chỉnh; không giả lập chuyển tiền thành công |
| I01 | Duyệt booking — Nội bộ | POST /admin/bookings/confirm | BookingManagementService | Concurrent approve một lần, đúng amount, invoice fail rollback, stock once | **PASS** | receipt integration24 + receipt HTTP101 |
| I02 | Duyệt muộn/hủy — Nội bộ | confirm/reconcile/cancel | BookingManagementService | Không revive expiry, reconcile còn giữ review, hủy giải phóng, không erase paid | **PASS** | Integration chuyên biệt; không phải mọi tổ hợp callback thực |
| I03 | Admin scope toàn luồng — CT/CS/NV | /admin/bookings/show/confirm | AdminBooking; resolveScope | Toàn bộ ma trận MySQL ghi cross-company/cross-facility từng role | **CODE REVIEW ONLY** | Có unit/scope fixture; chưa mọi thao tác ghi từng role |
| J01 | Hóa đơn — Nội bộ | createInvoice internal | InvoiceService; HoaDon | UNIQUE invoice và rollback trong luồng duyệt hiện tại | **PASS** | receipt MySQL/HTTP: một invoice |
| J02 | Hóa đơn invariant — Nội bộ | InvoiceService::createInvoice | InvoiceService.php:73 | Payment phải thuộc đúng booking truyền vào | **FAIL** | AUDIT repro: invoice booking2 nhận payment của booking3 |
| J03 | Hóa đơn scope/UI — KH/CT/CS/NV | /invoices*; /admin/invoices* | InvoiceService; invoice controllers | Mọi list/show/print và IDOR qua browser + MySQL | **CODE REVIEW ONLY** | Source ownership có; suite invoice fake PDO bị chặn trước phần sau |
| K01 | Nhân viên — ROOT/CT/CS | Employee show/edit/grant routes | EmployeeService; NhanVien; AccountService | Details/secrets/scope/no-account, grant first/repeat/concurrent, retired/locked | **PASS** | employee-details75; integration48 + Chrome21 |
| K02 | Nhân viên CRUD toàn bộ — ROOT/CT/CS | Employee routes | EmployeeController/Service | Browser full-role scope suite cũ | **BLOCKED** | employee-scope integration thiếu playwright-core; test mới chỉ phủ phần giao tài khoản/details |
| L01 | Khách hàng quản lý — CT/CS/NV | /admin/customers; /show | ManagedCustomer; CustomerManagementService | Distinct khách, counts scoped, cross-tenant deny, search/page/read-only | **PASS** | customer-management162 + Chrome29 |
| M01 | Lịch sử khách — KH | /booking/history | Booking::history; history.php | Filters ngày tạo inclusive/sort/page/owner/snapshot contract | **PASS** | booking-history48 RAM; receipt real HTTP trạng thái/ảnh |
| M02 | Lịch sử UI — KH | History fixture | booking-history.js/css | Accordion độc lập/ảnh/X/Esc/outside, 1440/1280/768/390/360 overflow | **PASS** | Chrome75; screenshot desktop đã xem |
| M03 | Header/transitions — PUBLIC | Home/courts/history/#about | main/courts layouts; public-page-transition.css | Cross-document native transition/Back/Forward/reduced-motion/unsupported browser | **CODE REVIEW ONLY** | CSS/template inspected; không đủ tracing/visual capture để gọi PASS |
| M04 | CSP — Nội bộ/KH | Admin booking và booking-wait | public/index.php + active views | Inline style bị policy self từ chối | **CODE REVIEW ONLY** | Mâu thuẫn source/header xác định; chưa đo tác động từng vùng browser |
| N01 | DB hiện có — READ ONLY | 18 bảng | information_schema + SELECT | 26 FK không mồ côi, không âm tồn/sai tiền/liên kết invoice trong live snapshot | **PASS** | READ ONLY snapshot; không chứng minh mọi race tương lai |
| N02 | Schema cài mới — Test isolated | Main SQL only | HoaDon; TaiKhoanNganHang | Runtime dùng được với schema cài mới | **FAIL** | 1054 ma_thanh_toan; thiếu bank table trong main SQL |
| N03 | Expiry vận hành — READ ONLY | Windows Task Scheduler / CLI | bin/expire-bookings.php | Có job unattended trên máy hiện tại và raw state được cleanup | **FAIL** | Không thấy task match; 13 overdue đủ điều kiện còn pending; job test riêng PASS |
| N04 | Performance — Nội bộ/PUBLIC | Search/quote/history/dashboard | PDO queries/indexes | Tải lớn, p95 latency, 100+ concurrent customers, deadlock stress | **NOT RUN** | Chỉ race 2 worker và EXPLAIN expiry; không benchmark live |
| N05 | Web entry — PUBLIC | /BaiTapNhomPHP/ | root .htaccess | Root đến đúng public folder | **FAIL** | HEAD302 sang /QuanLyDatSan/public/ |
| S01 | Upload/privacy/config — PUBLIC/KH | Sensitive paths/receipts | Apache; ReceiptImage; Router | /.env, SQL, .git/config không đọc công khai; receipt cần owner | **PASS** | HEAD403 các path; receipt HTTP khác owner bị chặn |
| S02 | SQLi/XSS/CSRF — PUBLIC/KH/Nội bộ | Các route được test | Prepared PDO; e(); CSRF | Payloads/invalid inputs trong auth/customer/prices/receipt fixture | **PASS** | Các ca nêu cụ thể đã chạy; không phải toàn bộ input mọi route |
| S03 | Security rộng — Mọi role | 202 endpoints | Toàn hệ thống | Fuzz toàn diện/DAST/dependency CVE scan/TLS/proxy nhiều node | **NOT RUN** | Không cài scanner/dependency, không tấn công ngoài local |
| R01 | Đặt lại — KH | Repeat flow trong history/schedule | repeat-booking browser/integration | Lặp booking qua browser và dữ liệu MySQL riêng | **BLOCKED** | Thiếu playwright-core; không xác nhận test đi tiếp |

**Tổng nhóm ca trong ma trận chức năng (62 dòng):** PASS = 36; NOT RUN = 10; FAIL = 7; CODE REVIEW ONLY = 6; BLOCKED = 3. Đây là nhóm ca nghiệp vụ, độc lập với thống kê 50 lượt chạy suite phía trên; không cộng hai bảng vào cùng một tỷ lệ.

## 6. Database thực tế — chỉ đọc

### 6.1. Bảng và số dòng

| Bảng | Số dòng |
|---|---:|
| bang_gia | 136 |
| bo_mon | 6 |
| chi_tiet_dat_san | 138 |
| chi_tiet_dich_vu_dat | 57 |
| co_so | 24 |
| cong_ty_nhuong_quyen | 9 |
| dat_san | 96 |
| dich_vu | 94 |
| hoa_don | 4 |
| hop_dong_nhuong_quyen | 9 |
| khach_hang | 41 |
| loai_san | 10 |
| nhan_vien | 48 |
| san | 74 |
| tai_khoan | 88 |
| tai_khoan_ngan_hang_co_so | 6 |
| thanh_toan | 61 |
| vai_tro | 5 |

Metadata thu được gồm 57 constraint records và 56 index definitions trong information_schema (có PRIMARY/UNIQUE/FK/CHECK; không đồng nhất các con số này với số FK). Đã kiểm tra **26 liên kết FK**, tất cả số dòng mồ côi bằng 0.

Các ràng buộc đáng chú ý:
- Booking: UNIQUE khoa_yeu_cau; fingerprint/nội dung kiểm tra tại service; tiền tổng/các tiền thành phần có CHECK.
- Account: tên đăng nhập unique; profile khách/nhân viên liên kết account unique.
- Thanh toán: một payment cho booking, tham chiếu unique, mã giao dịch unique khi có.
- Hóa đơn đang dùng: một invoice cho booking/payment, số hóa đơn unique. **FK riêng lẻ không bảo đảm payment thuộc cùng booking của invoice** (BUG-003).
- Giá: điều kiện loại ngày/giờ/ngày hiệu lực và overlap được service kiểm tra; live snapshot chưa có overlap phát hiện được.
- Dịch vụ: tồn có thể NULL (không theo dõi), không âm; tồn giữ được suy ra từ booking đang có hiệu lực.

Kết quả snapshot:
- Không thấy tồn âm; nhân viên gán cơ sở sai công ty; court/service booking khác cơ sở; tổng booking lệch chi tiết; line amount lệch snapshot.
- Không thấy booking đã xác nhận thiếu payment thành công; payment thành công mà booking chưa xác nhận; invoice mismatch trong **4 hóa đơn live hiện có**.
- Không thấy booking tương lai còn hiệu lực trùng nhau trong truy vấn đã chạy.
- 88 hash tài khoản đều được password_get_info nhận diện; không in hash/mật khẩu ra báo cáo.
- Không thấy file minh chứng bị mất trong những liên kết ảnh mà resolver hiện tại chấp nhận.
- Không thấy EVENT/TRIGGER database tự làm tác vụ hết hạn. Ứng dụng dùng CLI/service, không dựa vào event DB.

### 6.2. Trạng thái thô và hiệu lực

| Trạng thái booking | Raw DB | Effective projection |
|---|---:|---:|
| CHO_THANH_TOAN | 15 | 2 |
| CHO_XAC_NHAN | 12 | 12 |
| DA_XAC_NHAN | 4 | 4 |
| HOAN_THANH | 0 | 0 |
| HET_HAN | 5 | 18 |
| DA_HUY | 60 | 60 |
| **Tổng** | **96** | **96** |

Payment raw: 25 CHO_THANH_TOAN; 4 THANH_CONG; 4 HET_HAN; 27 HUY; 1 CAN_DOI_SOAT.

15 raw pending đều đã qua deadline tại snapshot: #1, #41, #44, #60, #62, #63, #64, #67, #68, #73, #84, #86, #89, #90, #93. Trong đó #84/#86 có ảnh liên kết nên guard bảo vệ để đối soát; 13 còn lại được projection xem hết hạn. Không kết luận 15 booking này đều đang chiếm sân: lịch/giữ tồn dùng điều kiện riêng theo trạng thái và deadline.

### 6.3. Booking từng được người dùng nêu — trạng thái hiện tại

| Booking | Raw booking/payment | Hạn gốc | Nguồn receipt thực tế | Kết quả đọc |
|---|---|---|---|---|
| #84 | CHO_THANH_TOAN / CHO_THANH_TOAN | 05/11 11:28:13 | Legacy [BIEN_LAI:...] trong thanh_toan.ghi_chu, timestamp 11:22:21; cột ảnh mới NULL | Resolver tìm được file; cần đối soát dữ liệu cũ |
| #86 | CHO_THANH_TOAN / CHO_THANH_TOAN | 05/11 11:38:00 | Cột ảnh mới + legacy, gửi 11:32:09 | File hợp lệ; cần đối soát dữ liệu cũ |
| #87 | CHO_XAC_NHAN / CHO_THANH_TOAN | 05/11 13:29:48 | Ảnh mới + legacy, gửi 13:23:58 | Resolver tìm được file; không auto expiry review |
| #91 | DA_XAC_NHAN / THANH_CONG | 05/11 14:49:57 | Ảnh mới + legacy, gửi 14:44:12 | Đã duyệt theo dữ liệu hiện có; deadline cũ vẫn được lưu |

Không in tên khách, ghi chú riêng tư, đường dẫn file ảnh cụ thể hay thông tin tài khoản ngân hàng thật. Không có audit trail đầy đủ để kết luận nguyên nhân lịch sử do phiên code nào/ai thao tác; không tự khôi phục các đơn cũ.

## 7. Tổng hợp lỗi theo Severity / Priority

| Bug ID | Module | Lỗi | Severity | Priority | Trạng thái | File |
|---|---|---|---|---|---|---|
| BUG-001 | Cơ sở / Sân | Bỏ sót CHO_XAC_NHAN khi bảo vệ giờ hoạt động và trạng thái sân | HIGH | HIGH | Confirmed — MariaDB test | CoSo.php:129; San.php:73 |
| BUG-002 | Cài mới / DB | Schema chính thiếu cấu trúc ngân hàng và hai cột hóa đơn mà runtime cần | HIGH | HIGH | Confirmed — import schema vào DB test | quanly_chuoi_san_the_thao.sql:556; HoaDon.php:186 |
| BUG-003 | Hóa đơn | createInvoice nhận payment của booking khác nếu số tiền trùng | HIGH | HIGH | Confirmed — gọi service trong transaction test | InvoiceService.php:73 |
| BUG-004 | Dữ liệu booking cũ | Booking #84/#86 có minh chứng liên kết nhưng vẫn CHO_THANH_TOAN | HIGH | HIGH | Confirmed — SELECT + resolver ảnh, chỉ đọc | Booking.php:13; BookingReceiptService.php:12 |
| BUG-005 | Vận hành hết hạn | Chưa thấy scheduled task; 13 booking đủ điều kiện vẫn lưu trạng thái chờ | LOW | HIGH | Confirmed trên máy audit; scheduler ngoài máy chưa biết | bin/expire-bookings.php:2 |
| BUG-006 | Regression tests | Fixture và assertion cũ khiến các suite quan trọng dừng sớm | LOW | HIGH | Confirmed — 17 lượt test lỗi harness/expectation | tests/; xem bảng kết quả |
| BUG-007 | Dịch vụ công khai | Bảng giá trong tab Dịch vụ thiếu đơn vị theo giờ | LOW | LOW | Confirmed — unit FAIL + ảnh Chrome | detail-services.php:13 |
| BUG-008 | Apache / entry point | RewriteBase còn trỏ /QuanLyDatSan/ thay vì thư mục triển khai hiện tại | LOW | LOW | Confirmed — HTTP HEAD 302 | /.htaccess:3 |
| BUG-009 | CSP / giao diện | Inline style trong view đang dùng không phù hợp style-src self | LOW | LOW | Confirmed ở source/header; tác động visual chưa đo trực tiếp | public/index.php:19; admin/bookings/index.php:54 |

**Severity:** HIGH: BUG-001–004. LOW: BUG-005–009. CRITICAL: chưa xác nhận lỗi nào.
**Priority:** HIGH: BUG-001–006. LOW: BUG-007–009.

Hai mục BUG-005/006 được ánh xạ **LOW Severity, HIGH Priority**: chúng không trực tiếp chứng minh sai tiền/đặt trùng ở các luồng hiện tại nhưng chặn độ tin cậy vận hành và nghiệm thu. Không tạo mức MEDIUM ngoài thang yêu cầu.

## 8. Chi tiết từng bug

### BUG-001 — Điều kiện bảo vệ cơ sở/sân bỏ sót CHO_XAC_NHAN

- **Module:** Cơ sở, sân và booking. **Severity HIGH / Priority HIGH. Confirmed bằng MariaDB test.**
- **File/hàm:** app/models/CoSo.php:129 activeBookings(), điều kiện SQL:135; hoursConflict():147; app/models/San.php:73 related(), SQL:81; CoSoService::save():143; SanService::save():126.
- **Điều kiện:** Có booking CHO_XAC_NHAN với ngày chơi tương lai; deadline 6 phút ban đầu đã qua. Cơ sở không có bảng giá ngoài giờ mới cản riêng thao tác đổi giờ.
- **Tái hiện:** Tạo fixture cơ sở 05:00–23:00, sân A và review ngày mai 08:00–09:00. Đọc hai active count. Gọi service đổi giờ mở thành 10:00 và đổi sân sang TAM_NGUNG.
- **Mong đợi:** Booking chờ duyệt vẫn được tính là lịch còn hiệu lực; từ chối thu hẹp giờ hoặc ngừng sân xung đột.
- **Thực tế:** Hai count bằng 0; cả hai save đều thành công, DB test lưu giờ 10:00 và sân TAM_NGUNG.
- **Nguyên nhân:** Predicate chỉ gồm DA_XAC_NHAN hoặc CHO_THANH_TOAN còn hạn. Schedule/checkout hiện đã tính CHO_XAC_NHAN vô thời hạn, tạo khác biệt giữa các module.
- **Đề xuất:** Đồng nhất tập trạng thái chiếm lịch trong các truy vấn bảo vệ tài nguyên; test review tương lai/đã quá deadline khi đổi giờ và trạng thái. Không cần sửa dữ liệu hay schema để giải quyết predicate.
- **Rủi ro khi sửa:** Admin CRUD cơ sở/sân, price-hours validation, workflow tạm ngừng. Không thay công thức tính giá hoặc tự hủy booking.
- **Giới hạn:** Tái hiện bằng service thật/MySQL; chưa chạy click form admin cho ca này. Không sửa record live.

### BUG-002 — Schema cài mới không đáp ứng code ngân hàng/hóa đơn

- **Module:** Cài mới, ngân hàng, payment/invoice. **Severity HIGH / Priority HIGH. Confirmed bằng schema import test.**
- **File/hàm:** database/quanly_chuoi_san_the_thao.sql:556 hoa_don; app/models/HoaDon.php:186 existsByPaymentId(); HoaDon::create():28; TaiKhoanNganHang::getDefaultByFacility():72.
- **Điều kiện:** Chỉ import schema chính hiện tại theo luồng chuẩn bị database mới, chưa áp dụng riêng hai migration 01/11 và 02/11.
- **Tái hiện:** Import main SQL vào DB trắng riêng; gọi HoaDon::existsByPaymentId(1) hoặc bắt đầu createInvoice().
- **Mong đợi:** Schema cài mới cung cấp các cấu trúc mà runtime đang truy vấn, hoặc tài liệu chỉ rõ bước bổ sung bắt buộc cho cài mới.
- **Thực tế runtime:** SQLSTATE[42S22], 1054 Unknown column 'ma_thanh_toan' in 'where clause'. DDL hoa_don cũng thiếu so_hoa_don. DDL main không có CREATE TABLE tai_khoan_ngan_hang_co_so.
- **Nguồn khác biệt:** Hai migration tồn tại: 20261101_tai_khoan_ngan_hang_co_so.sql và 20261102_hoa_don_fields.sql. Live DB đã có cấu trúc tương ứng, vì vậy không suy ra live hiện tại cũng lỗi 1054.
- **Đề xuất:** Sau phê duyệt, đồng bộ DDL cài mới và hướng dẫn upgrade/cài mới; giữ migration cũ để upgrade DB cũ, không yêu cầu chạy lại main SQL trên DB có dữ liệu. Test cài trắng phải đi hết tạo payment/duyệt/invoice.
- **Rủi ro khi sửa:** Các máy thành viên khác nhau về phiên bản schema; chạy ALTER lặp sẽ lỗi. Migration hóa đơn có backfill, không được tự chạy trên live.
- **Giới hạn:** Đã chứng minh invoice query lỗi; nhánh thiếu bank table được xác định từ DDL và query source, chưa browser-test toàn luồng cài trắng.

### BUG-003 — Service hóa đơn chưa kiểm tra payment thuộc booking

- **Module:** Invoice/data integrity. **Severity HIGH / Priority HIGH. Confirmed bằng service thật trong transaction MySQL.**
- **File/hàm:** app/services/InvoiceService.php:36 createInvoice(), đọc booking:73 và payment:88; kiểm tra tiền trước insert:95–129.
- **Điều kiện:** Caller nội bộ truyền booking A và payment thành công của B; số tiền hai booking bằng nhau, chưa có invoice.
- **Tái hiện:** Trong DB test đã có migration hóa đơn, tạo A ID2 và B ID3 cùng tổng 100.000; payment ID1 liên kết B. Begin transaction, gọi createInvoice(2, 1).
- **Mong đợi:** ValidationException trước INSERT vì payment.ma_dat_san != bookingId.
- **Thực tế:** Tạo được invoice ID1 lưu ma_dat_san=2, ma_thanh_toan=1; FK riêng lẻ và CHECK tiền đều chấp nhận. Transaction thử đã rollback.
- **Nguyên nhân:** Service kiểm trạng thái/số tiền/unique nhưng thiếu invariant liên kết hai record.
- **Đề xuất:** Kiểm tra relation trên hai hàng đã lock trước mọi ghi; bổ sung negative integration test cho cặp ID chéo. Có thể đánh giá ràng buộc DB bổ sung sau phê duyệt, không bắt buộc đổi schema để vá guard service.
- **Rủi ro khi sửa:** Confirm payment/invoice generation; bảo đảm caller tiếp tục rollback toàn bộ khi bị từ chối.
- **Giới hạn:** Chưa chứng minh endpoint công khai cho phép khách tự truyền cặp ID này; các caller xác nhận hiện tại dùng cặp đúng. Đây là lỗi biên service/tính toàn vẹn, **không khẳng định đã khai thác IDOR qua HTTP**. Không tìm thấy invoice live sai cặp trong 4 record hiện có.

### BUG-004 — Dữ liệu cũ có ảnh hợp lệ nhưng trạng thái vẫn pending

- **Module:** Dữ liệu booking / review. **Severity HIGH / Priority HIGH. Confirmed read-only.**
- **Record:** #84, #86. Nguồn đọc: dat_san + thanh_toan qua ThanhToan::findByBookingId(), BookingReceiptService::info().
- **Mong đợi nghiệp vụ:** Khi backend đã tiếp nhận ảnh trước hạn thành công, booking phải CHO_XAC_NHAN, tiếp tục giữ tài nguyên cho tới xử lý hợp lệ.
- **Thực tế:** Cả hai raw trạng thái vẫn CHO_THANH_TOAN. #84 chỉ có legacy tag, #86 có cả cột mới; resolver tìm được file và thời điểm gửi trước deadline.
- **Tái hiện chỉ đọc:** SELECT hai booking/payment; resolve liên kết qua service hiện có; đối chiếu bảng mục 6.3.
- **Nguyên nhân xác định được:** Dữ liệu lưu mâu thuẫn với lifecycle hiện nay. unprotectedSql() ở Booking.php:20 bảo vệ các pending có receipt khỏi expiry để đối soát, vì vậy chúng cũng còn hiện pending trong effective projection. Predicate schedule/stock chỉ giữ pending còn hạn, nên dữ liệu kiểu này có nguy cơ không bảo lưu tài nguyên đúng như review.
- **Chưa xác định:** Không đủ audit log để kết luận phiên code/transaction nào đã tạo mâu thuẫn. Không quy lỗi cho upload hiện tại: upload HTTP mới và race đã test đạt.
- **Đề xuất:** Review riêng từng đơn theo chứng từ, khả dụng lịch hiện tại và người quản lý có thẩm quyền. Không mass UPDATE, không revive booking hết hạn hoặc chiếm lại sân có người khác đặt. Bổ sung báo cáo anomaly/đối soát có thao tác được duyệt.
- **Rủi ro:** Tồn giữ, lịch sân, lịch sử/admin và xác minh thủ công; các ngày chơi cũ có thể đã qua. Không coi có ảnh là bằng chứng tiền đã xác thực.

### BUG-005 — Chưa thấy tác vụ hết hạn tự động hoạt động trên máy hiện tại

- **Module:** Vận hành. **Severity LOW / Priority HIGH. Confirmed trong phạm vi quan sát máy local.**
- **File/hàm:** bin/expire-bookings.php:2; Booking.php:233 expire(); docs/booking-expiry-scheduler.md.
- **Bằng chứng:** Lệnh đọc Get-ScheduledTask sau khi chạy với quyền đọc phù hợp thành công nhưng không tìm task có action chứa php/expire-bookings/STRYKE. Live raw có 13 pending quá hạn không receipt, ví dụ #1/#41. Không thấy DB EVENT/TRIGGER tự chạy cleanup.
- **Mong đợi:** Có scheduler gọi CLI mỗi phút, kể cả không có khách truy cập; theo dõi exit/log/backlog.
- **Thực tế:** Có script và tài liệu, chưa thấy đăng ký task trên máy audit; trạng thái thô tồn lại nhiều ngày.
- **Nguyên nhân:** Job cần cấu hình vận hành ngoài request; không có sự tự chạy chỉ vì file script tồn tại.
- **Đề xuất:** Người quản trị cấu hình Windows Task Scheduler theo docs (hoặc cron/systemd trên máy triển khai), đúng PHP/cwd/user/.env, no-overlap; theo dõi log và Last Run Result. Chỉ chạy thật sau khi người dùng duyệt vì CLI có UPDATE.
- **Rủi ro khi sửa:** Trạng thái raw/payment chờ được cleanup; không đổi tồn vật lý. Review/paid/receipt cũ được bảo vệ cần đối soát riêng.
- **Giới hạn:** Không thể loại trừ scheduler ngoài máy/server khác. Effective history/schedule đã xử lý 13 pending thành hết hạn theo điều kiện, nên **không kết luận thiếu scheduler đang làm tất cả khung giờ bị khóa**. Chính CLI đã chạy đạt trên DB test riêng.

### BUG-006 — Bộ regression không còn phản ánh source/contract hiện tại

- **Module:** QA/test infrastructure. **Severity LOW / Priority HIGH. Confirmed runtime.**
- **File/hàm:** Các test liệt kê mục 5.2; BookingManagementService.php:271 SET TRANSACTION và fixture Booking PDO/SQLite tương ứng.
- **Điều kiện/tái hiện:** Chạy từng suite với PHP/Node hiện có và môi trường test riêng đúng guard.
- **Mong đợi:** Test mô phỏng đúng dialect/contract hiện tại; fail khi sản phẩm vi phạm nghiệp vụ.
- **Thực tế:** 17 lượt FAIL do expectation/fixture cũ; 2 lượt BLOCKED do đường dẫn playwright-core từ máy khác. Phần cuối nhiều suite không được thực thi.
- **Bằng chứng mẫu:** near SET: syntax error; PDO object is not initialized; Reservation query lost state/expiry predicates; MODULE_NOT_FOUND đường dẫn D:/KiemThuPM/....
- **Nguyên nhân:** Nghiệp vụ review/vai trò/layout đã đổi, test không cập nhật đồng bộ; fake driver chưa hỗ trợ exec isolation statement; browser dependency bị hardcode.
- **Đề xuất:** Sau phê duyệt, sửa harness đúng mục đích; transaction/race chạy trên MariaDB/MySQL, unit không mô phỏng locking bằng SQLite. Cập nhật assertion theo UI/contract đã chốt, dependency path qua cấu hình rõ ràng, không bỏ assert nghiệp vụ để làm xanh.
- **Rủi ro khi sửa:** Dễ vô tình làm yếu test hoặc loại bỏ ca bảo mật. Không sửa production để thỏa expectation cũ trái quy tắc CHO_XAC_NHAN.
- **Giới hạn:** Không gộp facility-services thiếu đơn vị vào lỗi fixture; đó là BUG-007 riêng.

### BUG-007 — Tab Dịch vụ không ghi đơn vị giá sân theo giờ

- **Module:** Public facility services tab. **Severity LOW / Priority LOW. Confirmed unit + screenshot.**
- **File:** app/views/courts/detail-services.php:13.
- **Tái hiện:** Mở cơ sở có rate, chọn tab Dịch vụ; chạy php tests/facility-services.php.
- **Mong đợi:** Giá sân thể hiện rõ đơn vị theo giờ theo dữ liệu đang tính.
- **Thực tế:** Header chỉ còn Giá sân + br rỗng; số tiền không có /giờ. Unit dừng ở “real units and hourly price label”. Screenshot 360px cũng cho thấy chỉ có tiền, trong khi đơn vị dịch vụ vẫn có.
- **Nguyên nhân:** View bỏ nhãn đơn vị; BookingPricingService vẫn tính hourly bình thường.
- **Đề xuất:** Khôi phục nhãn đơn vị ở đúng tab, không sửa công thức giá.
- **Nguy cơ ảnh hưởng:** Chỉ public services table; tránh đụng bảng giá admin/booking.

### BUG-008 — Root redirect trỏ tên thư mục cũ

- **Module:** Apache entry. **Severity LOW / Priority LOW. Confirmed HTTP.**
- **File:** .htaccess:3 RewriteBase /QuanLyDatSan/.
- **Tái hiện:** HTTP HEAD http://localhost/BaiTapNhomPHP/.
- **Mong đợi:** Đến /BaiTapNhomPHP/public/ hoặc entry DocumentRoot được cấu hình.
- **Thực tế:** 302 Location http://localhost/QuanLyDatSan/public/.
- **Nguyên nhân:** RewriteBase hardcode tên deploy khác.
- **Đề xuất:** Sau phê duyệt, điều chỉnh quy tắc root tương ứng deployment hoặc dùng vhost trỏ public/. Giữ nguyên chặn private files.
- **Nguy cơ:** Apache subdirectory/vhost; không liên quan URL query route trong app. Entry /BaiTapNhomPHP/public/index.php vẫn hoạt động, nên chưa ánh xạ thành hệ thống không sử dụng được.

### BUG-009 — Một số view active còn inline style trái CSP

- **Module:** UI/CSP compatibility. **Severity LOW / Priority LOW. Confirmed bằng source và HTTP header; CODE REVIEW ONLY cho tác động visual cụ thể.**
- **File:** public/index.php:19 gửi style-src self; app/views/admin/bookings/index.php:54 và nhiều style attribute lân cận; admin/bookings/show.php:97; booking-wait.php:52/334.
- **Điều kiện:** Render qua public/index.php, browser thực thi CSP, view có style attribute.
- **Mong đợi:** Những style cần thiết nằm trong stylesheet được policy cho phép.
- **Thực tế source:** View đặt grid/padding/color trong thuộc tính style nhưng policy không cấp quyền inline style. Header thật đã được kiểm tra trong auth HTTP tùy chỉnh.
- **Đề xuất:** Dời style cần thiết sang class scoped sau khi được duyệt; không nới CSP bằng unsafe-inline để che lỗi.
- **Rủi ro:** Visual admin/payment; phải đối chiếu screenshot từng trang và không sửa nghiệp vụ.
- **Giới hạn:** Chưa capture Console/CSP violation và so computed style trên từng trang affected trong browser thật. Không kết luận toàn bộ giao diện hỏng vì các class CSS ngoài vẫn có thể cung cấp style tương đương. Template payment/checkout có code inline cũ nhưng chưa xác minh được là view active, nên không dùng nó để nâng mức độ lỗi.

## 9. An toàn booking/thanh toán và rủi ro còn lại

### 9.1. Phần đã kiểm chứng

- Booking mới được tạo ở CHO_THANH_TOAN với deadline gốc 360 giây; key unique tại DB, nội dung và customer kiểm tại service. Không giữ transaction 6 phút.
- Khóa company/facility, sport/type, court và dịch vụ theo thứ tự trong checkout; quote phía server kiểm lại availability/price/service stock. Race hai process đã chạy thật, không phải giả concurrency bằng Promise trong một request.
- Upload khóa booking/payment, lưu ảnh và conditional UPDATE sang CHO_XAC_NHAN trong transaction, kiểm lại CURRENT_TIMESTAMP sau file I/O; không đổi deadline hoặc tự đánh dấu payment thành công.
- Review tiếp tục giữ court/tồn dù deadline cũ qua; late upload và expiry cạnh tranh không revive đơn đã hết.
- Admin confirmation khóa/kiểm lại, trừ tồn và tạo invoice cùng transaction; failure rollback. Duplicate approval không trừ hai lần.
- Stock hold suy ra từ detail + trạng thái; expiry/cancel chưa trừ vật lý nên không hoàn tồn thừa.
- GET history/inspect/status không tự hồi sinh booking. Projection hiệu lực dùng chung tại history/admin trong các path đã đọc.
- ReceiptImage kiểm MIME/size/dimensions/upload origin, random filename/private storage, resolve filename đúng booking; resolver tương thích legacy tag và cột mới.

### 9.2. Rủi ro không coi là đã PASS toàn hệ thống

- BUG-001/004 khiến “review chiếm lịch” chưa nhất quán ở mọi module/dữ liệu cũ dù upload/checkout mới đúng.
- BankTransactionSource chưa có provider thực/callback endpoint để kiểm signature, replay và late money end-to-end. Manual approval là thao tác nhân viên chịu trách nhiệm, ảnh không phải bằng chứng tự động.
- Chưa stress deadlock nhiều vòng hoặc hàng trăm request; lock company/facility cũng làm các booking cùng công ty nối hàng, cần benchmark trước khi mở rộng tải.
- Quote đọc từng court/service và tính reserved có thể tăng số query theo số mục lựa chọn/catalog; chưa đo p95 hoặc EXPLAIN toàn bộ search/history/dashboard. Không gọi đây là N+1 đã gây chậm thực tế.
- Customer/history batch details tránh N+1 SQL trong các truy vấn đã kiểm. Receipt resolver có kiểm file theo từng item; không sao chép ảnh ra thư mục public mới.
- .env/.git/SQL qua root Apache bị chặn 403 trong kiểm tra đã chạy. Không xuất secret/hash/session token thật vào báo cáo. Chưa kiểm TLS, proxy, shared-session/throttle nhiều máy.
- Composer dependencies không được tra CVE qua network trong audit này; chưa có kết luận supply-chain sạch.
- PSR-4/autoload đạt trên Windows; case-sensitivity, filesystem permissions và MySQL 8/Linux deployment chưa thử.

Tài liệu/DDL còn mô tả cũ: main SQL:14 gọi CHO_XAC_NHAN là nháp không chiếm lịch; docs scheduler cũng có câu về reconciliation theo contract cũ. Code mới và test cho thấy review giữ vô thời hạn. Cần cập nhật tài liệu sau khi chốt fix, tránh người khác tái dùng predicate cũ; audit không chỉnh các file này.

## 10. Các phần chưa thể kiểm thử / NOT RUN

1. Callback ngân hàng/provider thật, chuyển tiền thật, quét QR bằng ứng dụng ngân hàng, Chrome mobile trên thiết bị vật lý.
2. Browser suite dùng playwright-core: repeat booking và employee-scope đã BLOCKED; admin-navigation/authorization/bank-admin/dashboard/booking-polish/operations-polish/ui-smoke dùng cùng dependency chưa chạy. Không tự cài dependency.
3. Các đoạn suite nằm sau assert hỏng: full auth password HTTP, full catalog CRUD, phần sau facility migration/general booking/invoice test. Các test bổ sung chỉ bù đúng ca nêu trong matrix, không thay thế toàn bộ phần bị bỏ dở.
4. Geometric schedule fit/zoom/sticky timeline và header native transitions/Back/Forward/reduced-motion trên toàn bộ route.
5. Full company/contract lifecycle qua UI/MySQL; full invoice show/print/tenant-IDOR từng vai trò; full service/admin bank CRUD.
6. Thay đổi trạng thái HOAN_THANH qua workflow thật chưa tìm được, không tự tạo endpoint hoặc UPDATE để giả chức năng.
7. DAST/fuzz mọi input/202 route, tải lớn, failover/restart, deadlock stress, disk-full, mất DB/mạng/provider dài, timezone host khác, Linux/MySQL8.
8. Scheduler vận hành thật: không chạy/đăng ký task UPDATE database ứng dụng; chỉ kiểm cấu hình và script trên database riêng.

Không có “PASS toàn bộ UI” hoặc “PASS toàn bộ phân quyền” chỉ vì lint/unit đạt. Độ rộng coverage thể hiện ở matrix và phụ lục route, không phải 100% endpoint đều đã E2E.

## 11. Artifact, screenshot và evidence

Các file dưới storage/temp là artifact tạm được cho phép, không dùng làm dữ liệu production và không đưa credentials/cookie vào report.

| Artifact | Nội dung |
|---|---|
| storage/temp/receipt-hold-A0jFoF/ | Receipt HTTP/browser: result 1440/390, private proof modal, logs |
| storage/temp/employee-account-d4274a7447/ | Employee integration48/Chrome21, grant/details/locked/retired/concurrency |
| storage/temp/customer-browser-O8sODw/ | Customer management Chrome29 |
| storage/temp/facility-services-cpZgGf/ | Services Chrome32 ban đầu |
| storage/temp/facility-services-0miWox/ | Variant62 tại 360/768/1024/1440; ảnh tables-*.png |
| storage/temp/history-review/ | History Chrome75, ảnh 360/390/768/1280/1440 |
| storage/temp/pr6-review/contract-report.json | Failure SQLite SET của contract suite trong lượt này |
| storage/temp/audit-e7ea37e4bd/ | Server log auth/catalog trên database audit riêng |
| storage/temp/audit-auth-c00b6ecba8/ | Server log/session của 18 ca auth tùy chỉnh |

Đã mở và xem ảnh history desktop1440 và services drawer360; thấy bố cục render, các cột/giá và nhãn thiếu đơn vị ở tab Dịch vụ. Không suy ra mọi screenshot/test fixture là ảnh dữ liệu live.

### Evidence tái hiện bổ sung (đã rollback/drop DB riêng)

```json
{
  "database": "stryke_audit_test_4ee20871a3",
  "isolated": true,
  "review_guard": {
    "booking_state": "CHO_XAC_NHAN",
    "future_play": true,
    "facility_active_count": 0,
    "court_active_count": 0,
    "hours_change": "ACCEPTED",
    "court_stop": "ACCEPTED",
    "stored_opening": "10:00:00",
    "stored_court_state": "TAM_NGUNG"
  },
  "fresh_schema_invoice": "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ma_thanh_toan' in 'where clause'",
  "cross_booking_invoice": {
    "accepted": true,
    "requested_booking": 2,
    "payment_booking": 3,
    "invoice_id": 1,
    "stored": {
      "ma_dat_san": 2,
      "ma_thanh_toan": 1
    }
  },
  "test_database_removed": true
}
```

```json
{
  "same_slot": [
    {
      "id": 1
    },
    {
      "error": 409
    }
  ],
  "different_slots": [
    {
      "id": 2
    },
    {
      "id": 3
    }
  ],
  "same_request_key": [
    {
      "id": 4
    },
    {
      "id": 4
    }
  ],
  "multi_court": [
    {
      "id": 5
    },
    {
      "error": 409
    }
  ],
  "stock_race": [
    {
      "id": 6
    },
    {
      "error": 409
    }
  ],
  "physical_stock": 2,
  "held_stock": 2,
  "checks": {
    "same_slot_one_success": true,
    "different_slots_both_success": true,
    "same_key_same_id": true,
    "multi_court_one_success": true,
    "stock_one_success": true,
    "no_physical_deduction": true,
    "reserved_exactly_two": true
  },
  "isolated_database_removed": "stryke_booking_test_c3f7bd11e5"
}
```

### Evidence lỗi suite đặc trưng

```text
SQLSTATE[HY000]: General error: 1 near "SET": syntax error
  tests/fixtures/booking-pr6.php:33 → BookingManagementService.php:271
PDO object is not initialized, constructor was not called
  BookingManagementService.php:271 → tests/booking-management-unit.php:565
FAIL: real units and hourly price label
  tests/facility-services.php:31
Cannot find module 'D:/KiemThuPM/SOT357-65134364/node_modules/playwright-core'
  tests/repeat-booking-browser.cjs; tests/employee-scope-browser.cjs
```

## 12. Đề xuất thứ tự xử lý và điều kiện merge/release

1. **Sửa BUG-001** để mọi guard bảo vệ tài nguyên nhận CHO_XAC_NHAN; bổ sung MySQL test review tương lai/đổi giờ/ngừng sân.
2. **Sửa BUG-003**, thêm kiểm tra relation booking/payment trong invoice và negative test; giữ rollback toàn bộ confirmation.
3. **Giải quyết BUG-002**, thống nhất schema cài trắng và hướng dẫn migration; thử một install mới đi hết payment/invoice bằng DB riêng.
4. **Đối soát BUG-004 thủ công có bằng chứng**, không bulk hồi sinh; thống nhất quyền xử lý và tài nguyên đã bị chiếm bởi booking khác nếu có.
5. **Cấu hình/giám sát BUG-005** sau phê duyệt chạy thật; theo dõi lỗi/log/backlog và anomalies. Không dùng GET của khách để thay scheduler.
6. **Khôi phục regression harness BUG-006** theo contract mới; chuẩn hóa browser dependency được nhóm đồng ý, chạy tiếp toàn bộ các ca đang dừng/chưa chạy.
7. Sửa nhãn đơn vị, deployment rewrite và CSP styles trong phạm vi riêng được duyệt; kiểm tra hồi quy UI sau sửa.
8. Chạy lại ma trận tenant-write, invoice read/print, price/service mutations, callback/reconciliation khi provider sẵn sàng; thêm stress concurrency và schedule geometry.

**NOT READY cho nghiệm thu/release toàn hệ thống.** Có thể tiếp tục review các PR độc lập dựa trên bằng chứng riêng, nhưng không dùng kết quả này để khẳng định toàn bộ STRYKE an toàn hoặc đủ ổn định. Đặc biệt không bỏ qua các invariant tài nguyên/tài chính và các vùng kiểm thử đang BLOCKED/NOT RUN.

Audit dừng ở báo cáo. Không sửa lỗi, không đổi UI/logic/schema/dữ liệu thật, không commit/push/merge.

## Phụ lục A — Inventory route thực tế

Danh sách lấy từ Router sau khi require routes/web.php bằng Application(PDO SQLite RAM), không chỉ grep text. Cột “Role route” rỗng được ghi “controller/public”: phải đọc controller guard, **không tự coi là route mở thiếu bảo mật**. Mỗi endpoint là method + path; danh sách không khẳng định tất cả đã chạy HTTP.

| # | Method | Route | Handler | Role route |
|---|---|---|---|---|
| 1 | GET | / | HomeController::index | controller/public |
| 2 | GET | /account | AccountController::show | controller/public |
| 3 | GET | /account/settings | AccountController::settings | controller/public |
| 4 | GET | /admin/companies | CompanyController::index | QUAN_TRI_TAP_DOAN |
| 5 | GET | /admin/companies/create | CompanyController::create | QUAN_TRI_TAP_DOAN |
| 6 | GET | /admin/companies/edit | CompanyController::edit | QUAN_TRI_TAP_DOAN |
| 7 | GET | /admin/contracts | ContractController::index | QUAN_TRI_TAP_DOAN |
| 8 | GET | /admin/contracts/create | ContractController::create | QUAN_TRI_TAP_DOAN |
| 9 | GET | /admin/contracts/edit | ContractController::edit | QUAN_TRI_TAP_DOAN |
| 10 | GET | /admin/employees | EmployeeController::index | QUAN_TRI_TAP_DOAN |
| 11 | GET | /admin/employees/show | EmployeeController::show | QUAN_TRI_TAP_DOAN |
| 12 | GET | /admin/employees/create | EmployeeController::create | QUAN_TRI_TAP_DOAN |
| 13 | GET | /admin/employees/edit | EmployeeController::edit | QUAN_TRI_TAP_DOAN |
| 14 | GET | /company/employees | EmployeeController::index | QUAN_LY_CONG_TY |
| 15 | GET | /company/employees/show | EmployeeController::show | QUAN_LY_CONG_TY |
| 16 | GET | /company/employees/create | EmployeeController::create | QUAN_LY_CONG_TY |
| 17 | GET | /company/employees/edit | EmployeeController::edit | QUAN_LY_CONG_TY |
| 18 | GET | /facility/employees | EmployeeController::index | QUAN_LY_CO_SO |
| 19 | GET | /facility/employees/show | EmployeeController::show | QUAN_LY_CO_SO |
| 20 | GET | /facility/employees/create | EmployeeController::create | QUAN_LY_CO_SO |
| 21 | GET | /facility/employees/edit | EmployeeController::edit | QUAN_LY_CO_SO |
| 22 | GET | /admin/revenue | admin\DashboardController::index | QUAN_TRI_TAP_DOAN |
| 23 | GET | /company/revenue | admin\DashboardController::index | QUAN_LY_CONG_TY |
| 24 | GET | /facility/revenue | admin\DashboardController::index | QUAN_LY_CO_SO |
| 25 | GET | /staff/revenue | admin\DashboardController::index | NHAN_VIEN |
| 26 | GET | /company/profile | CompanyController::dashboard | QUAN_LY_CONG_TY |
| 27 | GET | /company | admin\DashboardController::index | QUAN_LY_CONG_TY |
| 28 | GET | /facility | admin\DashboardController::index | QUAN_LY_CO_SO |
| 29 | GET | /staff | admin\DashboardController::index | NHAN_VIEN |
| 30 | GET | /customer | Closure | KHACH_HANG |
| 31 | GET | /login | AuthController::loginForm | controller/public |
| 32 | GET | /register | AuthController::registerForm | controller/public |
| 33 | GET | /admin | admin\DashboardController::index | QUAN_TRI_TAP_DOAN |
| 34 | GET | /admin/bo-mon | admin\BoMonController::index | QUAN_TRI_TAP_DOAN |
| 35 | GET | /admin/bo-mon/create | admin\BoMonController::create | QUAN_TRI_TAP_DOAN |
| 36 | GET | /admin/bo-mon/show | admin\BoMonController::show | QUAN_TRI_TAP_DOAN |
| 37 | GET | /admin/bo-mon/edit | admin\BoMonController::edit | QUAN_TRI_TAP_DOAN |
| 38 | GET | /admin/bo-mon/delete | admin\BoMonController::confirmDelete | QUAN_TRI_TAP_DOAN |
| 39 | GET | /admin/loai-san | admin\LoaiSanController::index | QUAN_TRI_TAP_DOAN |
| 40 | GET | /admin/loai-san/create | admin\LoaiSanController::create | QUAN_TRI_TAP_DOAN |
| 41 | GET | /admin/loai-san/show | admin\LoaiSanController::show | QUAN_TRI_TAP_DOAN |
| 42 | GET | /admin/loai-san/edit | admin\LoaiSanController::edit | QUAN_TRI_TAP_DOAN |
| 43 | GET | /admin/loai-san/delete | admin\LoaiSanController::confirmDelete | QUAN_TRI_TAP_DOAN |
| 44 | GET | /admin/co-so | admin\CoSoController::index | QUAN_TRI_TAP_DOAN |
| 45 | GET | /admin/co-so/show | admin\CoSoController::show | QUAN_TRI_TAP_DOAN |
| 46 | GET | /admin/co-so/edit | admin\CoSoController::edit | QUAN_TRI_TAP_DOAN |
| 47 | GET | /admin/co-so/create | admin\CoSoController::create | QUAN_TRI_TAP_DOAN |
| 48 | GET | /admin/co-so/delete | admin\CoSoController::confirmDelete | QUAN_TRI_TAP_DOAN |
| 49 | GET | /admin/san | admin\SanController::index | QUAN_TRI_TAP_DOAN |
| 50 | GET | /admin/san/show | admin\SanController::show | QUAN_TRI_TAP_DOAN |
| 51 | GET | /admin/san/edit | admin\SanController::edit | QUAN_TRI_TAP_DOAN |
| 52 | GET | /admin/san/create | admin\SanController::create | QUAN_TRI_TAP_DOAN |
| 53 | GET | /admin/san/delete | admin\SanController::confirmDelete | QUAN_TRI_TAP_DOAN |
| 54 | GET | /company/co-so | admin\CoSoController::index | QUAN_LY_CONG_TY |
| 55 | GET | /company/co-so/show | admin\CoSoController::show | QUAN_LY_CONG_TY |
| 56 | GET | /company/co-so/edit | admin\CoSoController::edit | QUAN_LY_CONG_TY |
| 57 | GET | /company/co-so/create | admin\CoSoController::create | QUAN_LY_CONG_TY |
| 58 | GET | /company/san | admin\SanController::index | QUAN_LY_CONG_TY |
| 59 | GET | /company/san/show | admin\SanController::show | QUAN_LY_CONG_TY |
| 60 | GET | /company/san/edit | admin\SanController::edit | QUAN_LY_CONG_TY |
| 61 | GET | /company/san/create | admin\SanController::create | QUAN_LY_CONG_TY |
| 62 | GET | /facility/co-so | admin\CoSoController::index | QUAN_LY_CO_SO |
| 63 | GET | /facility/co-so/show | admin\CoSoController::show | QUAN_LY_CO_SO |
| 64 | GET | /facility/co-so/edit | admin\CoSoController::edit | QUAN_LY_CO_SO |
| 65 | GET | /facility/san | admin\SanController::index | QUAN_LY_CO_SO |
| 66 | GET | /facility/san/show | admin\SanController::show | QUAN_LY_CO_SO |
| 67 | GET | /facility/san/edit | admin\SanController::edit | QUAN_LY_CO_SO |
| 68 | GET | /facility/san/create | admin\SanController::create | QUAN_LY_CO_SO |
| 69 | GET | /staff/co-so | admin\CoSoController::index | NHAN_VIEN |
| 70 | GET | /staff/co-so/show | admin\CoSoController::show | NHAN_VIEN |
| 71 | GET | /staff/san | admin\SanController::index | NHAN_VIEN |
| 72 | GET | /staff/san/show | admin\SanController::show | NHAN_VIEN |
| 73 | GET | /courts | CourtBrowserController::index | controller/public |
| 74 | GET | /courts/show | CourtBrowserController::show | controller/public |
| 75 | GET | /courts/panel | CourtBrowserController::panel | controller/public |
| 76 | GET | /courts/schedule | CourtBrowserController::schedule | controller/public |
| 77 | GET | /courts/prices | CourtBrowserController::prices | controller/public |
| 78 | GET | /booking/history | BookingHistoryController::index | KHACH_HANG |
| 79 | GET | /booking/history/receipt | BookingHistoryController::receipt | KHACH_HANG |
| 80 | GET | /booking/details | BookingController::details | controller/public |
| 81 | GET | /booking/wait | BookingController::waiting | KHACH_HANG |
| 82 | GET | /booking/result | BookingController::waiting | KHACH_HANG |
| 83 | GET | /booking/receipt | BookingController::receipt | KHACH_HANG, QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 84 | GET | /admin/bang-gia | admin\BangGiaController::index | QUAN_TRI_TAP_DOAN |
| 85 | GET | /admin/bang-gia/show | admin\BangGiaController::show | QUAN_TRI_TAP_DOAN |
| 86 | GET | /admin/bang-gia/create | admin\BangGiaController::create | QUAN_TRI_TAP_DOAN |
| 87 | GET | /admin/bang-gia/edit | admin\BangGiaController::edit | QUAN_TRI_TAP_DOAN |
| 88 | GET | /company/bang-gia | admin\BangGiaController::index | QUAN_LY_CONG_TY |
| 89 | GET | /company/bang-gia/show | admin\BangGiaController::show | QUAN_LY_CONG_TY |
| 90 | GET | /company/bang-gia/create | admin\BangGiaController::create | QUAN_LY_CONG_TY |
| 91 | GET | /company/bang-gia/edit | admin\BangGiaController::edit | QUAN_LY_CONG_TY |
| 92 | GET | /facility/bang-gia | admin\BangGiaController::index | QUAN_LY_CO_SO |
| 93 | GET | /facility/bang-gia/show | admin\BangGiaController::show | QUAN_LY_CO_SO |
| 94 | GET | /facility/bang-gia/create | admin\BangGiaController::create | QUAN_LY_CO_SO |
| 95 | GET | /facility/bang-gia/edit | admin\BangGiaController::edit | QUAN_LY_CO_SO |
| 96 | GET | /staff/bang-gia | admin\BangGiaController::index | NHAN_VIEN |
| 97 | GET | /staff/bang-gia/show | admin\BangGiaController::show | NHAN_VIEN |
| 98 | GET | /admin/dich-vu | admin\DichVuController::index | QUAN_TRI_TAP_DOAN |
| 99 | GET | /admin/dich-vu/show | admin\DichVuController::show | QUAN_TRI_TAP_DOAN |
| 100 | GET | /admin/dich-vu/create | admin\DichVuController::create | QUAN_TRI_TAP_DOAN |
| 101 | GET | /admin/dich-vu/edit | admin\DichVuController::edit | QUAN_TRI_TAP_DOAN |
| 102 | GET | /company/dich-vu | admin\DichVuController::index | QUAN_LY_CONG_TY |
| 103 | GET | /company/dich-vu/show | admin\DichVuController::show | QUAN_LY_CONG_TY |
| 104 | GET | /company/dich-vu/create | admin\DichVuController::create | QUAN_LY_CONG_TY |
| 105 | GET | /company/dich-vu/edit | admin\DichVuController::edit | QUAN_LY_CONG_TY |
| 106 | GET | /facility/dich-vu | admin\DichVuController::index | QUAN_LY_CO_SO |
| 107 | GET | /facility/dich-vu/show | admin\DichVuController::show | QUAN_LY_CO_SO |
| 108 | GET | /facility/dich-vu/create | admin\DichVuController::create | QUAN_LY_CO_SO |
| 109 | GET | /facility/dich-vu/edit | admin\DichVuController::edit | QUAN_LY_CO_SO |
| 110 | GET | /staff/dich-vu | admin\DichVuController::index | NHAN_VIEN |
| 111 | GET | /staff/dich-vu/show | admin\DichVuController::show | NHAN_VIEN |
| 112 | GET | /facility/bank | BankController::index | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 113 | GET | /facility/bank/create | BankController::create | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| 114 | GET | /facility/bank/edit | BankController::edit | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| 115 | GET | /payment | PaymentController::show | KHACH_HANG |
| 116 | GET | /payment/status | PaymentController::status | controller/public |
| 117 | GET | /payment/qr | PaymentController::qrImage | controller/public |
| 118 | GET | /admin/bookings | admin\AdminBookingController::index | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 119 | GET | /admin/bookings/show | admin\AdminBookingController::show | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 120 | GET | /admin/customers | admin\CustomerController::index | QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 121 | GET | /admin/customers/show | admin\CustomerController::show | QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 122 | GET | /admin/invoices | admin\AdminInvoiceController::index | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 123 | GET | /admin/invoices/show | admin\AdminInvoiceController::show | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 124 | GET | /admin/invoices/print | admin\AdminInvoiceController::print | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 125 | GET | /invoices | InvoiceController::index | KHACH_HANG |
| 126 | GET | /invoices/show | InvoiceController::show | KHACH_HANG |
| 127 | GET | /invoices/print | InvoiceController::print | KHACH_HANG |
| 128 | POST | /account | AccountController::updateProfile | KHACH_HANG |
| 129 | POST | /account/password | AccountController::changePassword | controller/public |
| 130 | POST | /admin/companies | CompanyController::store | QUAN_TRI_TAP_DOAN |
| 131 | POST | /admin/companies/update | CompanyController::update | QUAN_TRI_TAP_DOAN |
| 132 | POST | /admin/companies/status | CompanyController::changeStatus | QUAN_TRI_TAP_DOAN |
| 133 | POST | /admin/contracts | ContractController::store | QUAN_TRI_TAP_DOAN |
| 134 | POST | /admin/contracts/update | ContractController::update | QUAN_TRI_TAP_DOAN |
| 135 | POST | /admin/contracts/status | ContractController::changeStatus | QUAN_TRI_TAP_DOAN |
| 136 | POST | /admin/employees | EmployeeController::store | QUAN_TRI_TAP_DOAN |
| 137 | POST | /admin/employees/update | EmployeeController::update | QUAN_TRI_TAP_DOAN |
| 138 | POST | /admin/employees/status | EmployeeController::changeStatus | QUAN_TRI_TAP_DOAN |
| 139 | POST | /admin/employees/account | EmployeeController::grantAccount | QUAN_TRI_TAP_DOAN |
| 140 | POST | /company/employees | EmployeeController::store | QUAN_LY_CONG_TY |
| 141 | POST | /company/employees/update | EmployeeController::update | QUAN_LY_CONG_TY |
| 142 | POST | /company/employees/status | EmployeeController::changeStatus | QUAN_LY_CONG_TY |
| 143 | POST | /company/employees/account | EmployeeController::grantAccount | QUAN_LY_CONG_TY |
| 144 | POST | /facility/employees | EmployeeController::store | QUAN_LY_CO_SO |
| 145 | POST | /facility/employees/update | EmployeeController::update | QUAN_LY_CO_SO |
| 146 | POST | /facility/employees/status | EmployeeController::changeStatus | QUAN_LY_CO_SO |
| 147 | POST | /facility/employees/account | EmployeeController::grantAccount | QUAN_LY_CO_SO |
| 148 | POST | /login | AuthController::login | controller/public |
| 149 | POST | /register | AuthController::register | controller/public |
| 150 | POST | /logout | AuthController::logout | controller/public |
| 151 | POST | /admin/bo-mon/store | admin\BoMonController::store | QUAN_TRI_TAP_DOAN |
| 152 | POST | /admin/bo-mon/update | admin\BoMonController::update | QUAN_TRI_TAP_DOAN |
| 153 | POST | /admin/bo-mon/delete | admin\BoMonController::destroy | QUAN_TRI_TAP_DOAN |
| 154 | POST | /admin/loai-san/store | admin\LoaiSanController::store | QUAN_TRI_TAP_DOAN |
| 155 | POST | /admin/loai-san/update | admin\LoaiSanController::update | QUAN_TRI_TAP_DOAN |
| 156 | POST | /admin/loai-san/delete | admin\LoaiSanController::destroy | QUAN_TRI_TAP_DOAN |
| 157 | POST | /admin/co-so/update | admin\CoSoController::update | QUAN_TRI_TAP_DOAN |
| 158 | POST | /admin/co-so/store | admin\CoSoController::store | QUAN_TRI_TAP_DOAN |
| 159 | POST | /admin/co-so/delete | admin\CoSoController::destroy | QUAN_TRI_TAP_DOAN |
| 160 | POST | /admin/san/update | admin\SanController::update | QUAN_TRI_TAP_DOAN |
| 161 | POST | /admin/san/store | admin\SanController::store | QUAN_TRI_TAP_DOAN |
| 162 | POST | /admin/san/delete | admin\SanController::destroy | QUAN_TRI_TAP_DOAN |
| 163 | POST | /company/co-so/update | admin\CoSoController::update | QUAN_LY_CONG_TY |
| 164 | POST | /company/co-so/store | admin\CoSoController::store | QUAN_LY_CONG_TY |
| 165 | POST | /company/san/update | admin\SanController::update | QUAN_LY_CONG_TY |
| 166 | POST | /company/san/store | admin\SanController::store | QUAN_LY_CONG_TY |
| 167 | POST | /facility/co-so/update | admin\CoSoController::update | QUAN_LY_CO_SO |
| 168 | POST | /facility/san/update | admin\SanController::update | QUAN_LY_CO_SO |
| 169 | POST | /facility/san/store | admin\SanController::store | QUAN_LY_CO_SO |
| 170 | POST | /booking/quote | BookingController::quote | controller/public |
| 171 | POST | /booking/start | BookingController::start | controller/public |
| 172 | POST | /booking/review | BookingController::review | controller/public |
| 173 | POST | /booking/confirm | BookingController::confirm | controller/public |
| 174 | POST | /booking/receipt | BookingController::uploadReceipt | KHACH_HANG |
| 175 | POST | /booking/expire | BookingController::expire | KHACH_HANG |
| 176 | POST | /booking/cancel | BookingController::cancel | KHACH_HANG |
| 177 | POST | /booking/payment | BookingController::retryPayment | KHACH_HANG |
| 178 | POST | /admin/bang-gia/store | admin\BangGiaController::store | QUAN_TRI_TAP_DOAN |
| 179 | POST | /admin/bang-gia/update | admin\BangGiaController::update | QUAN_TRI_TAP_DOAN |
| 180 | POST | /admin/bang-gia/status | admin\BangGiaController::changeStatus | QUAN_TRI_TAP_DOAN |
| 181 | POST | /company/bang-gia/store | admin\BangGiaController::store | QUAN_LY_CONG_TY |
| 182 | POST | /company/bang-gia/update | admin\BangGiaController::update | QUAN_LY_CONG_TY |
| 183 | POST | /company/bang-gia/status | admin\BangGiaController::changeStatus | QUAN_LY_CONG_TY |
| 184 | POST | /facility/bang-gia/store | admin\BangGiaController::store | QUAN_LY_CO_SO |
| 185 | POST | /facility/bang-gia/update | admin\BangGiaController::update | QUAN_LY_CO_SO |
| 186 | POST | /facility/bang-gia/status | admin\BangGiaController::changeStatus | QUAN_LY_CO_SO |
| 187 | POST | /admin/dich-vu/store | admin\DichVuController::store | QUAN_TRI_TAP_DOAN |
| 188 | POST | /admin/dich-vu/update | admin\DichVuController::update | QUAN_TRI_TAP_DOAN |
| 189 | POST | /admin/dich-vu/status | admin\DichVuController::changeStatus | QUAN_TRI_TAP_DOAN |
| 190 | POST | /company/dich-vu/store | admin\DichVuController::store | QUAN_LY_CONG_TY |
| 191 | POST | /company/dich-vu/update | admin\DichVuController::update | QUAN_LY_CONG_TY |
| 192 | POST | /company/dich-vu/status | admin\DichVuController::changeStatus | QUAN_LY_CONG_TY |
| 193 | POST | /facility/dich-vu/store | admin\DichVuController::store | QUAN_LY_CO_SO |
| 194 | POST | /facility/dich-vu/update | admin\DichVuController::update | QUAN_LY_CO_SO |
| 195 | POST | /facility/dich-vu/status | admin\DichVuController::changeStatus | QUAN_LY_CO_SO |
| 196 | POST | /facility/bank/store | BankController::store | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| 197 | POST | /facility/bank/update | BankController::update | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| 198 | POST | /facility/bank/set-default | BankController::setDefault | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| 199 | POST | /facility/bank/toggle-status | BankController::toggleStatus | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| 200 | POST | /admin/bookings/confirm | admin\AdminBookingController::confirm | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 201 | POST | /admin/bookings/reconcile | admin\AdminBookingController::reconcile | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| 202 | POST | /admin/bookings/cancel | admin\AdminBookingController::cancel | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |

## Phụ lục B — Lệnh đã dùng và lưu ý chạy lại

- PHP syntax: php -l từng file app/core/routes/config/bin/tests và public/index.php.
- JavaScript syntax: node --check từng .js/.cjs thuộc public/assets/js/tests.
- Composer: --no-plugins --no-scripts validate --no-check-publish; check-platform-reqs. Không update/install.
- Unit/fixture PHP: php tests/<suite>.php, các tên và trạng thái đầy đủ ở mục 5.1.
- Isolated integration: php tests/booking-expiry-integration.php --run; receipt-integration.php --run; receipt-hold-http.php --run --browser; employee-account-integration.php --run --browser.
- General booking/facility/repeat/employee-scope suites đã thử chạy, kết quả FAIL/BLOCKED đúng như bảng; không giấu exit code.
- Auth/catalog integration **không được tự chạy trực tiếp trên DB đang cấu hình** nếu chưa cô lập cả PHP server và client. Audit đã dùng orchestrator stdin kiểm tra prefix/DB_NAME, tạo schema riêng và finally DROP.
- Chrome không dependency mới: node tests/customer-management-browser.cjs, facility-services-browser.cjs, booking-history-browser.cjs, booking-review.cjs. Variant viewport chỉ thay mảng kích thước trong harness nạp vào bộ nhớ, không sửa file test/source.
- Node VM: payment-state.cjs, schedule-interaction.cjs, booking-modal.cjs.
- Hai nhóm probe stdin riêng: auth18; race7. Ba probe lỗi: resource guard, fresh invoice schema, cross-booking invoice. DB test cleanup, không để transaction 6 phút.
- Task Scheduler chỉ đọc; **không** chạy bin/expire-bookings.php --run với cấu hình ứng dụng. READ ONLY metadata/data không thay thế kiểm thử ghi database riêng.

Kết quả trên là của lượt audit này; các con số PASS trong docs/báo cáo cũ không được tính lại như test mới.

