# STRYKE — POST-FIX FULL REGRESSION, INTEGRATION & E2E

Ngày kiểm thử: **06/11/2026**, timezone **Asia/Ho_Chi_Minh (+07:00)**. Chỉ kiểm thử và báo cáo; không sửa lỗi.

## 1. Executive Summary — NOT READY cho nghiệm thu toàn hệ thống

**BUG-001, BUG-002 và BUG-003 đã được kiểm chứng lại bằng test trên MariaDB độc lập, không chỉ dựa vào báo cáo FIXED.** Luồng khách chọn nhiều sân → đăng nhập giữ draft → áp dụng dịch vụ → tạo hold → upload biên lai → giao diện kết quả chờ xác minh → nhân viên duyệt → một hóa đơn/một lần trừ tồn đã hoàn tất trên Chrome với PHP entry thật. Không có chuyển tiền ngân hàng thật.

Tuy nhiên, phát hiện thêm lỗi trên hóa đơn: **tiền từng dòng sân hiển thị 0 dù snapshot và tổng hóa đơn đúng** (POST-001, HIGH) và **in hóa đơn phía khách trả HTTP500** (POST-002, MEDIUM). Vì vậy chưa đủ điều kiện nghiệm thu toàn hệ thống. Không suy diễn từ lỗi hiển thị này thành mất tiền trong database: tổng đã lưu 170.000 đ vẫn đúng trong ca tái hiện.

- Chưa xác nhận lỗi CRITICAL trong phạm vi đã chạy; không khẳng định toàn hệ thống không có lỗi.
- Còn HIGH hiện tại: POST-001 về nội dung tài chính của hóa đơn. BUG-004 là **Historical data anomaly** chưa xử lý, tách khỏi lỗi code mới.
- Còn MEDIUM: POST-002; ngoài ra REG-001 là **Test Infrastructure** khiến regression suite không còn chạy hết.
- BUG-004 vẫn tồn tại ở #84/#86. Upload mới trong R38/R46 chuyển đúng CHO_XAC_NHAN; chưa tái hiện bất nhất cũ bằng workflow hiện tại.
- Có regression ở **bộ test**, cụ thể authorization-unit (trước PASS262) và dashboard-unit (trước PASS101) nay FAIL lúc dựng fixture sau khi main schema được bổ sung. Chưa có bằng chứng quyền/doanh thu ứng dụng bị hỏng do hai FAIL đó.
- Không phát hiện đặt trùng, trừ tồn hai lần hoặc hóa đơn trùng trong những ca concurrency thực sự đã chạy. Đây không phải chứng nhận chịu tải lớn.
- **BUG-007 không còn là lỗi sản phẩm theo quyết định người dùng. Không đề nghị khôi phục `/giờ`.** Assertion cũ còn FAIL được báo đúng là expectation lỗi thời.

## 2. Môi trường, an toàn dữ liệu và Git

| Thuộc tính | Thực tế |
|---|---|
| Workspace | C:/xampp/htdocs/BaiTapNhomPHP |
| Branch / HEAD | kiem-thu-3 / 55623473790089f8229a2bda2b9533f68966f5c5 |
| PHP / Node | PHP 8.2.12; Node 22.14 |
| Database | MariaDB 10.4.32; InnoDB, session timezone +07:00 |
| Browser | Chrome headless thực, CDP; không cài thêm package |
| Dependencies | Composer PSR-4, vlucas/phpdotenv; thiếu playwright-core của một số suite cũ |
| HTTP | PHP entry public/index.php trên server test riêng; Apache local chỉ dùng kiểm tra đọc/HEAD |
| Việc ghi được phép | Chỉ DB dùng tên riêng theo từng lượt, session/log/screenshot/Chrome profile/ảnh upload test |

Đã đọc hai báo cáo trước, routes/controllers/models/services/middleware, views/CSS/JS liên quan và schema/migrations/tests. Đăng ký router hiện tại có **202 method/path entries**; xem phụ lục inventory. Có 5 vai trò thực: KHACH_HANG, QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN.

Workspace đã dirty trước audit, gồm HIGH fixes ở CoSo/San/InvoiceService/main SQL và các thay đổi nhân viên/khách hàng/tab dịch vụ/layout/CSS/routes đang có. Không reset/revert/checkout đè chúng. **Fingerprint trước và sau của 332 file source/schema/tests/config được giữ nguyên:**

```text
HTrGM8JHe4LjbaAGKO77eR51b1qI/Fn3HIK+k0vRzmg=   (SHA-256 aggregate, Base64)
```

Tập fingerprint gồm app, core, routes, config, database, bin, public/assets/css, public/assets/js, tests và entry/.htaccess/composer. Đây là bằng chứng nội dung source/test không bị chỉnh bởi audit, không phải hash của Git commit hay toàn bộ upload.

Tất cả import/migration/INSERT/UPDATE/DDL phục vụ integration đều chạy trên database tên ngẫu nhiên thuộc lượt test. Server test được cấu hình environment trỏ DB đó, không sửa .env. Ví dụ các DB đã dọn đúng ownership: stryke_highfix_test_dc6ead0d74, stryke_postauth_test_08ee69722d, stryke_postrace_test_d5254b55ff, stryke_postrace_test_a977a65b03, stryke_postfix_test_fdec3b8d02. DB ứng dụng chỉ được SELECT/metadata; không gọi job hết hạn trên DB đó.

Cuối audit còn một DB test có sẵn tên stryke_receipt_test_68d63bbf33 không thuộc lượt này: **không xóa**. Giữ log/screenshot chứng cứ; chỉ dọn receipt/test DB do lượt test sở hữu. Không xóa ảnh thật. Không commit/push/merge. Báo cáo cũ được giữ nguyên như lịch sử; những nhận định cũ mâu thuẫn quyết định `/giờ` đã được hiệu chỉnh trong báo cáo mới này.

## 3. Cách đếm và tổng kết kiểm thử

**67 suite/nhóm trong kế hoạch kiểm chứng lần này: 27 PASS, 19 FAIL, 13 BLOCKED, 8 NOT RUN.** Trong đó 46 nhóm đã thực sự thực thi = 27 PASS + 19 FAIL. BLOCKED là kết luận preflight về dependency/setup/an toàn, không giả vờ đã chạy hết suite.

| Nhóm | PASS | FAIL | BLOCKED | NOT RUN |
|---|---:|---:|---:|---:|
| PHP unit/fixture (30 suite) | 14 | 16 | 0 | 0 |
| Node VM (3) | 3 | 0 | 0 | 0 |
| Chrome suite hiện có (4, không đếm child browser hai lần) | 4 | 0 | 0 | 0 |
| MariaDB integration hiện có (5) | 3 | 2 | 0 | 0 |
| PHP stdin HTTP/service/concurrency độc lập (3) | 3 | 0 | 0 | 0 |
| Chrome+MariaDB E2E đầy đủ (1) | 0 | 1 | 0 | 0 |
| Gate dependency/setup/safety | 0 | 0 | 13 | 0 |
| Kịch bản chưa chạy đủ | 0 | 0 | 0 | 8 |
| **Tổng nhóm** | **27** | **19** | **13** | **8** |

Không đổi 19 FAIL thành 19 lỗi production. Nhiều suite dừng trước khi gọi chức năng do fake PDO/fixture/markup expectation. Mỗi điểm dừng được giữ nguyên, không sửa test để làm xanh. Riêng E2E R46 có **52 assertion PASS, 1 FAIL**; kiểm tra nội dung hóa đơn sau đó còn xác nhận POST-001 bằng HTML/log/screenshot. Assertion `owner invoice /invoices/show` chỉ PASS cho HTTP200, không chứng nhận giá dòng đúng.

Kiểm tra kỹ thuật bổ sung (không cộng vào 67 nhóm nghiệp vụ): **257 file PHP lint không lỗi; 34 file JavaScript parse không lỗi; 88 class/interface/trait autoload thành công; Composer validate/platform requirements đạt**, chỉ cảnh báo thiếu khai báo license. 14 PHP suite hoàn tất có 5.480 assertion đạt. Không cộng lại các lần rerun/debug cùng ca vào số test độc lập.

PASS ở VM/mock/SQLite luôn được ghi phương pháp; không thay thế transaction MySQL hoặc browser thật. Không sử dụng PASS cũ làm PASS mới. Các lần lỗi ở chính harness stdin trong quá trình dựng fixture/wait AJAX đã được sửa trong bộ nhớ, chạy lại; không coi lỗi harness đó là bug ứng dụng.

### 3.1. Các suite đã thực thi

| ID | Lệnh/nhóm | Kết quả | Bằng chứng/phân loại |
|---|---|---|---|
| R01 | php tests/run.php | PASS | 106 checks, phạm vi theo output gốc. |
| R02 | php tests/admin-navigation-unit.php | PASS | 390 checks, phạm vi theo output gốc. |
| R03 | php tests/admin-list-unit.php | FAIL | Expectation markup/action bo-mon cũ (line103). |
| R04 | php tests/admin-management-unit.php | PASS | 142 checks, phạm vi theo output gốc. |
| R05 | php tests/account-layout.php | PASS | 310 checks, phạm vi theo output gốc. |
| R06 | php tests/authorization-unit.php | FAIL | Fixture CREATE bank table hai lần (booking-pr6.php:33). Trước fix: PASS262. |
| R07 | php tests/bank-admin-unit.php | PASS | 131 checks, phạm vi theo output gốc. |
| R08 | php tests/booking-actions-unit.php | FAIL | Fixture bank table trùng; chưa tới assertions nghiệp vụ. |
| R09 | php tests/booking-history.php | PASS | 48 checks, phạm vi theo output gốc. |
| R10 | php tests/booking-inventory-unit.php | FAIL | Fake-PDO kiểm tra nguyên văn predicate cũ, line115. |
| R11 | php tests/booking-management-unit.php | FAIL | Fake PDO không gọi constructor, exec(SET ...) tại service271. |
| R12 | php tests/booking-pr6-unit.php | FAIL | Kế thừa fixture booking-inventory với predicate cũ. |
| R13 | php tests/booking-pr6-contract.php | FAIL | Fixture bank table trùng. |
| R14 | php tests/catalog-unit.php | FAIL | Expectation navbar cũ, line23. |
| R15 | php tests/court-flow-layout.php | FAIL | Harness không thiết lập $app, main.php:82. |
| R16 | php tests/court-prices.php | PASS | 21 checks, phạm vi theo output gốc. |
| R17 | php tests/courts-unit.php | PASS | 38 checks, phạm vi theo output gốc. |
| R18 | php tests/customer-management.php | PASS | 162 checks, phạm vi theo output gốc. |
| R19 | php tests/dashboard-unit.php | FAIL | Fixture ALTER so_hoa_don dù main SQL đã có, fixture dashboard28. Trước fix: PASS101. |
| R20 | php tests/employee-details-unit.php | PASS | 75 checks, phạm vi theo output gốc. |
| R21 | php tests/facility-address.php | PASS | 3337 checks, phạm vi theo output gốc. |
| R22 | php tests/facility-services.php | FAIL | Expectation nhãn /giờ bị người dùng chủ động bỏ. Không phải product bug; không khôi phục BUG-007. |
| R23 | php tests/facility-unit.php | PASS | 109 checks, phạm vi theo output gốc. |
| R24 | php tests/invoice-unit.php | FAIL | Fake PDO chưa khởi tạo ở BookingManagementService271. |
| R25 | php tests/manual-payment-sql-unit.php | FAIL | Fixture bank table trùng. |
| R26 | php tests/payment-verification-unit.php | FAIL | Fixture bank table trùng. |
| R27 | php tests/pricing-unit.php | PASS | 567 checks, phạm vi theo output gốc. |
| R28 | php tests/receipt-confirmation-unit.php | FAIL | Fixture bank table trùng. |
| R29 | php tests/service-unit.php | FAIL | Sidebar allowlist cũ không chứa module khách hàng mới. |
| R30 | php tests/vietqr-bank-unit.php | PASS | 44 checks, phạm vi theo output gốc. |
| R31 | node tests/payment-state.cjs | PASS | VM: polling/expiry guards; không phải browser ngân hàng. |
| R32 | node tests/schedule-interaction.cjs | PASS | VM: multi-court, grouping, realtime/offline/stale responses/zoom. |
| R33 | node tests/booking-modal.cjs | PASS | VM: links/modal/overlay/focus restore; native keyboard tách riêng. |
| R34 | node tests/customer-management-browser.cjs | PASS | 29 Chrome/HTTP, SQLite RAM. |
| R35 | node tests/facility-services-browser.cjs | PASS | 32 Chrome/HTTP, SQLite RAM. |
| R36 | node tests/booking-review.cjs | PASS | 31 Chrome DOM/AJAX; HTTP báo giá mock, không gọi nhầm là integration MySQL. |
| R37 | node tests/booking-history-browser.cjs | PASS | 75 Chrome checks; 5 widths, PHP fixture + public read-only GET. |
| R38 | php tests/high-bug-fixes-integration.php --run | PASS | 48 checks; main SQL mới + MariaDB riêng + upload HTTP thật. |
| R39 | php tests/booking-expiry-integration.php --run | PASS | 33 checks; CLI thật trỏ DB test, concurrency/expiry/index. |
| R40 | php tests/employee-account-integration.php --run --browser | PASS | 48 MariaDB/HTTP/concurrency + 21 Chrome; không đếm child browser thành suite khác. |
| R41 | php tests/booking-integration.php --run | FAIL | 6 check đầu PASS; dừng assertion cũ rental unsupported lifecycle rejected, line 93. |
| R42 | php tests/facility-migration-integration.php --run | FAIL | Migration preserves legacy row PASS; dừng expectation layout/role tại facility-integration.php:208. |
| R43 | POST-AUTH: PHP stdin, HTTP thật, DB riêng | PASS | 28 checks; đăng ký/login/profile/password/CSRF/session/redirect/khóa. |
| R44 | POST-CRUD: PHP stdin, service thật, DB riêng | PASS | 18 checks; công ty/hợp đồng/cơ sở/sân/giá/dịch vụ, version/scope. |
| R45 | POST-RACE: PHP worker processes, DB riêng | PASS | 19 checks; slots/multi-court/stock/idempotency/approval/rollback/revenue/upgrade. |
| R46 | POST-E2E: Chrome + public/index.php + MariaDB riêng | FAIL | 52 PASS / 1 FAIL (customer print HTTP 500). Xem thêm chẩn đoán tiền sân 0; HTTP200 không đồng nghĩa đúng nội dung. |

### 3.2. BLOCKED và NOT RUN

| ID | Suite/kịch bản | Trạng thái | Lý do |
|---|---|---|---|
| B01 | tests/admin-navigation.cjs | BLOCKED | playwright-core không có; preflight BLOCKED. |
| B02 | tests/authorization-browser.cjs | BLOCKED | playwright-core không có; scope đã có các test riêng, không thay thế suite này. |
| B03 | tests/bank-admin-browser.cjs | BLOCKED | playwright-core không có. |
| B04 | tests/booking-polish-browser.cjs | BLOCKED | playwright-core không có. |
| B05 | tests/booking-pr6-browser.cjs | BLOCKED | playwright-core không có. |
| B06 | tests/dashboard-browser.cjs | BLOCKED | playwright-core không có; không suy ra dashboard visual từ revenue SQL. |
| B07 | tests/operations-polish-browser.cjs | BLOCKED | playwright-core không có. |
| B08 | tests/repeat-booking-integration.php / child browser | BLOCKED | Import playwright-core tại đường dẫn ngoài project không có; không cài. |
| B09 | tests/employee-scope-integration.php / child browser | BLOCKED | Import playwright-core tại đường dẫn ngoài project không có; employee suite khác đã chạy. |
| B10 | tests/receipt-integration.php | BLOCKED | Preflight: main SQL mới rồi ALTER invoice trùng. Không thực thi lại migration đó trên DB mới. |
| B11 | tests/receipt-hold-http.php | BLOCKED | Preflight: main SQL mới rồi migration invoice trùng; current upload đã có R38/R46. |
| B12 | tests/auth-integration.php --run nguyên bản | BLOCKED | Tự đọc .env/DB ứng dụng và ghi fixture. Không chạy nguyên bản; dùng R43 trên DB riêng. |
| B13 | tests/catalog-integration.php --run nguyên bản | BLOCKED | Tự dùng DB ứng dụng và tài khoản admin thật. Không chạy nguyên bản; R44 kiểm service riêng. |
| N01 | Stress/load lớn, deadlock dưới tải hàng trăm/nghìn client | NOT RUN | Chỉ chạy cạnh tranh hai process có barrier và transaction thật, không benchmark tải. |
| N02 | Chuyển tiền ngân hàng thật, provider callback và đối soát ngân hàng bên ngoài | NOT RUN | Không có provider/test tiền thật được cấu hình; không giả lập đã xác minh giao dịch ngân hàng. |
| N03 | File QR tải xuống + giải mã/quét bằng app ngân hàng trên thiết bị thật | NOT RUN | Proxy QR đã trả 200 image/svg+xml; chưa xác minh download/scan end-to-end. |
| N04 | Firefox/Safari/iOS thật, native View Transition/reduced motion đầy đủ | NOT RUN | Chrome headless có; chưa có môi trường các browser/thiết bị đó. |
| N05 | Các entrypoint legacy còn lại: ui-smoke.cjs, booking-browser.cjs, payment-result-browser.cjs, receipt-hold-browser.cjs | NOT RUN | Không chạy từng suite nguyên bản; một phần hành vi được kiểm qua R46, không gọi suite chưa chạy là PASS. |
| N06 | Back/Forward, ngắt mạng ngay lúc commit/upload, khôi phục session nhiều tab hoàn chỉnh | NOT RUN | VM đã kiểm stale/offline; chưa browser-test mọi thời điểm commit/mất kết nối. |
| N07 | Hai HTTP multipart upload/expiry thật cùng chạm deadline trong mọi thứ tự | NOT RUN | SQL khóa expiry/payment + upload trước/sau hạn đã chạy; chưa stress chính request upload đối đầu CLI đúng micro-boundary. |
| N08 | Đặt lại booking và tự chuyển HOAN_THANH end-to-end | NOT RUN | Chưa xác lập workflow triển khai tương ứng từ source hiện tại; không tự thêm chức năng hoặc coi là PASS. |

auth-integration/catalog-integration nguyên bản không được chạy vì mặc định nối database ứng dụng. Đã có kiểm chứng thay thế trên DB riêng ở R43/R44 nhưng không đổi kết quả hai suite nguyên bản thành PASS. courts-integration.php là file include trong facility suite, không một suite độc lập đã hoàn tất.
## 4. Ma trận chức năng và vai trò

Ký hiệu: PUBLIC = chưa đăng nhập; KH = KHACH_HANG; ROOT = QUAN_TRI_TAP_DOAN; CT = QUAN_LY_CONG_TY; CS = QUAN_LY_CO_SO; NV = NHAN_VIEN. PASS chỉ áp dụng ca được mô tả, không bao trùm module. Inventory source không thay kiểm thử runtime mọi route.

| Module / ca kiểm | Vai trò | Route / file chính | Phương pháp và phạm vi thực chạy | Kết quả |
|---|---|---|---|---|
| Auth: đăng ký sai/đúng/trùng, chống gán ROOT, hash | PUBLIC | /register; AuthController, RegistrationService | R43 HTTP + MariaDB, 7 checks đầu | PASS |
| Login sai/đúng/SQLi; session fixation; redirect intended | PUBLIC/KH | /login; AuthService, Session | R43: qua register thất bại/thành công rồi login sai/đúng; session rotate; external redirect bị bỏ qua | PASS |
| Profile + đổi mật khẩu + CSRF + XSS | KH | /account; AccountService | R43: giả ID không đổi chủ hồ sơ; password_verify/new hash; CSRF cũ bị vô hiệu sau đổi mật khẩu | PASS |
| Logout và tài khoản khóa | KH | /logout, AuthMiddleware | R43: GET405, POST mất quyền; session đang có bị thu hồi khi tài khoản khóa trong DB test | PASS |
| Chặn customer vào ROOT | KH | /admin | R43 HTTP403; không thay role/session bằng client | PASS |
| CRUD công ty/hợp đồng | ROOT | AdminCompanyController, ContractService | R44 service thật: tạo/xem/sửa/status, trùng số và ngày hợp đồng không hợp lệ | PASS |
| UI toàn bộ công ty/hợp đồng và inactive tenant chain | ROOT/CT | /admin/companies, /admin/contracts | Service và unit có ca riêng; chưa browser mọi form/chuyển trạng thái dây chuyền | NOT RUN đầy đủ |
| Nhân viên: chi tiết/scope/cấp account/duplicate/đồng thời/login | ROOT/CT/CS | EmployeeController, EmployeeService, AccountService | R40 48 MySQL/HTTP +21 Chrome; R20 75 unit | PASS trong ca suite |
| Nhân viên nghỉ/locked, tránh tự kích hoạt/cấp trùng | ROOT/CT/CS | /employees/status, /employees/account theo role prefix | R40 giữ transaction/khóa, CSRF/scope/role; không sửa dữ liệu thật | PASS trong ca suite |
| Khách hàng chỉ xem và aggregate theo scope | CT/CS/NV | /admin/customers[/show]; ManagedCustomer | R18 162 SQL/controller/middleware; R34 29 Chrome/HTTP; R46 company B detail404 | PASS |
| Facility/court create/update/status | CT | CoSoService, SanService | R44 DB thật; sửa chéo công ty bị từ chối | PASS |
| Guard review khi đổi giờ/tạm ngừng | CT | CoSo::activeBookings, San::related | R38 +R45: review 7 phút/1 ngày, tương lai vs ngày chơi đã qua | PASS |
| Address/image validation | Người quản lý được phép | AdministrativeAddress, FacilityImage | R21 3337 unit; không đồng nhất unit với upload browser toàn bộ gallery | PASS unit |
| Upload cover/logo/gallery thực tế mọi loại file | CT/CS | Form quản lý cơ sở | Không upload asset thật; receipt upload không thay kiểm thử facility upload | NOT RUN đầy đủ |
| Price CRUD/overlap/scope | CT | BangGiaService | R44 MariaDB: create/update/status; overlap/foreign create bị chặn; R27 567 pricing | PASS |
| Public bảng giá grouping/day/type | PUBLIC | /courts/prices; CourtPriceService | R16 21 SQLite; R35 tab services; nhiều nhánh render được fixture kiểm | PASS trong fixture |
| Public filter province/ward/sport/search | PUBLIC | /courts, CourtBrowser | R35/R46 đường công khai +R18/R44 scope khác; facility legacy suite dừng trước một số ca | PASS phần đã chạy; chưa full HTTP filter matrix |
| Drawer/tab5/service table responsive | PUBLIC | /courts/panel; detail-tabs | R46 Chrome 360/390/768/1024/1440; nội dung test DB thuộc đúng cơ sở | PASS |
| Lịch36slot05–23, scroll containment | PUBLIC | /courts/schedule; CourtScheduleService | R46 Chrome 5 widths; R17 38 logic; R32 VM realtime/time cutoff | PASS |
| Multi-court, toggle/gap/group/remove/zoom | PUBLIC/KH | schedule.js | R32 VM; R46 chọn3 slot/2sân → backend150.000 đ | PASS trong ca thực chạy |
| Schedule 08:31 lock, future unaffected | PUBLIC | CourtScheduleService; schedule.js | R17/R32 clock fixture, không sửa đồng hồ hệ thống | PASS unit/VM |
| Realtime hai browser + mất mạng giữa commit | KH | schedule/booking | VM stale/offline PASS; chưa mọi tình huống hai browser thật | NOT RUN đầy đủ |
| Guest details không giữ chỗ; login giữ draft | PUBLIC→KH | /booking/start, /booking/details, /booking/review | R46 browser thật +snapshot DB không hold trước confirm | PASS |
| Giá backend authoritative; duplicate slots | KH | BookingPricingService, BookingService::quote | R27/R38/R44/R46, court/hour/day; không tin client money | PASS trong ca suite |
| Service modal draft/apply/delete/failure/fingerprint/note | KH | /booking/quote; booking.js | R36 31 mock HTTP Chrome; R46 Apply request/backend thật tăng170.000 đ | PASS (lỗi backend thực từng loại chưa test hết) |
| Confirm tạo hold6phút, refresh không reset | KH | /booking/confirm, /booking/wait | R46 DB +reload; R38 idempotency/guards | PASS |
| Same slot/different slots/same key/multi-court overlap | Hai KH | BookingService::confirm | R45 process barrier, actual MySQL locks:409 hoặc cùng booking ID theo ca | PASS |
| Giữ tồn cạnh tranh/cancel/release/không trừ trước duyệt | Hai KH | Booking/BookingService/ServiceInventory | R45 actual process +R46 stock5→5 khi hold/receipt, chỉ3 sau duyệt qty2 | PASS |
| Hết hạn, cutoff, CLI idempotency, review không hết hạn | KH/job | Booking::expire*, bin/expire-bookings.php | R39 33 checks; R38 review+1ngày vẫn giữ lịch/tồn | PASS |
| Job tự chạy khi không có khách | OS/job | Task Scheduler | Script test DB chạy được; máy audit không tìm thấy task phù hợp, chưa kiểm chứng vận hành định kỳ | FAIL vận hành cục bộ / BUG-005 |
| Receipt trống/sai MIME/>5MB | KH | POST /booking/receipt; ReceiptImage | R46 browser required và server invalid/oversize rejection; vẫn pending không có accepted receipt | PASS |
| Receipt hợp lệ/duplicate/late/CSRF/giữdeadline | KH | BookingReceiptService::upload | R38 HTTP thật; R46 actual fileinput; review không phải paid | PASS |
| Receipt IDOR | KH khác / CT khác | GET /booking/receipt | R46 foreign customer404, companyB booking404; R18/customer scope | PASS trong ca đã thử |
| Upload HTTP đối đầu expiry đúng cutoff | KH/job | BookingReceiptService, Booking::expire | R39 khóa +expiry callback model; chưa hai request multipart thật tại mọi interleaving | NOT RUN đầy đủ |
| Result review vs confirmed | KH | /booking/result, BookingController::waiting | R46 upload→không banner chờ; adminduyệt→server confirmed; refresh/GET không phục hồi expired qua R38 | PASS |
| QR cùng giao dịch | KH | /payment/qr; PaymentController | E2E network200 image/svg+xml, không đổi tiền/reference; chưa download/scan | PASS fetch; scan NOT RUN |
| Manual approval cùng booking | ROOT/CT | /admin/bookings; BookingManagementService | R46 thao tácUI root; R45 hai actor khác nhau tranh duyệt:1winner/1invoice/1deduct | PASS |
| Payment source/provider/callback thật | Hệ thống ngân hàng | BankTransactionSource / giao diện tích hợp | Không có chuyển khoản ngân hàng thật; receipt adminapproval không phải callback provider | BLOCKED ngoài môi trường test |
| Reconciliation/cancel mọi nhánh tiền muộn/sai tiền | CT/CS/NV | markNeedsReconciliation, cancelBookingByAdmin | Unit đang dừng fixture; một phần expiry model chạy, chưa xác nhận mọi hành động admin UI | NOT RUN đầy đủ |
| Invoice pair/missing/wrongstate/duplicate/rollback | Hệ thống/ROOT | InvoiceService::createInvoice | R38 +R45; payment chéo cùng tiền bị từ chối; lỗi insert rollback booking/payment/tồn | PASS |
| Nội dung dòng tiền sân hóa đơn | KH/ROOT | HoaDon::details, invoice views | HTTP200 nhưng HTML và screenshot hiển thị0; snapshot150.000 đ đúng | FAIL POST-001 |
| In hóa đơn customer | KH sở hữu | /invoices/print | Chrome/HTTP500, require sai path | FAIL POST-002 |
| Admin invoice list/show/print status | ROOT | /admin/invoices[/show,/print] | R46 HTTP200; **không công nhận dòng tiền đúng** (POST-001 cùng view contract) | PASS HTTP / FAIL nội dung |
| Revenue chỉ approved và đúng company | CT/CTkhác | DashboardService/model | R45 DB thật:60.000 đ, pending/rollback excluded; foreign company0 | PASS trong fixture |
| Dashboard mọi khoảng ngày/biểu đồ/browser | ROOT/CT/CS | DashboardController/views | unit bị duplicate column, browser thiếu dependency | BLOCKED / REG-001 |
| Page transitions/keyboard đầy đủ mọi trang | PUBLIC | public-page-transition/header scripts | JS parse và browser functional nav có chạy; chưa chấm animation/Back trên mọi browser | NOT RUN đầy đủ |

### 4.1. Quyền runtime đã trực tiếp đối chiếu

- KH sở hữu: review/confirm/upload/result/history/invoice show hoạt động; KH khác truy cập receipt404.
- CT A và CS A/NV được phân công: GET booking test thuộc A trả200. CT B truy cập booking A và chi tiết khách chỉ thuộc A trả404.
- ROOT: duyệt qua UI thật; CT A là actor thứ hai trong ca cạnh tranh approval.
- PUBLIC: danh sách/drawer/lịch truy cập được; history phải login; customer không vào ROOT được.
- R18/R34 có ca khách nhiều booking/multi-scope và deduplicate aggregate; không lấy hết dữ liệu rồi lọc JavaScript.
- Đây không phải vét cạn mọi cặp vai trò×202routes×mọiID. Các route payment không khai RoleMiddleware vẫn phải kiểm session/ownership ở controller/service; inventory roles rỗng không tự chứng minh lỗ hổng.

## 5. Kiểm chứng lại bốn HIGH

| BUG cũ | Kết luận lần này | Bằng chứng thực chạy | Phạm vi còn lại |
|---|---|---|---|
| BUG-001 | VERIFIED FIXED trong ca regression | R38 guard21check +R45 review7phút/1ngày/past play; đổi giờ và stop bị từ chối đúng, SELECT không đổi trạng thái | Không stress mọi thao tác quản lý đồng thời với booking mới |
| BUG-002 | VERIFIED FIXED | R38 fresh import9checks: invoice columns/types/nullable/unique/FK bank indexes; R46 chuỗi booking/payment/invoice; R45 oldschema upgrade bảo toàn invoice | Suite legacy phải cập nhật setup sau khi được phép; không lặp migration trên main SQL mới |
| BUG-003 | VERIFIED FIXED | R38 payment chéo cùng sốtiền/missing/valid/duplicate/rollback; R45 wrongstate/approvalparallel/invoicefailure rollback | Không sửa quy tắc giá/payment ngoài contract |
| BUG-004 | HISTORICAL — NEEDS MANUAL RECONCILIATION | READ ONLY #84/#86 +receipt resolver/files; R38/R46 booking mới chuyển review atomically | Không tự khôi phục/duyệt/đổi dữ liệu cũ |

### 5.1. Guard lịch sau fix

CoSo::activeBookings() (app/models/CoSo.php:129) và San::related() (app/models/San.php:73) giữ booking tương lai CHO_XAC_NHAN/DA_XAC_NHAN hoặc CHO_THANH_TOAN còn hạn. Deadline6phút không áp dụng review. Cancelled/expired/pending quá hạn không chặn sửa sai; ngày chơi đã qua không bị tính như booking tương lai. HOAN_THANH giữ contract guard trước đó, không mở rộng tùy tiện. R38 kiểm tra giờ không xung đột vẫn sửa được.

Expiry service/CLI chỉ xử lý pending đủ điều kiện; review+1ngày không bị giải phóng lịch hay giữ tồn. R39 có kiểm khóa cạnh tranh, exact cutoff, batch cap, anomaly/protected receipt, job lock, index và CLI đúng DB test. Không đánh đồng việc CLI hoạt động với việc OS đã cấu hình tự chạy.

### 5.2. Đối soát READ ONLY #84/#86 tại 06/11/2026 12:20 (+07:00)

| Dữ kiện | #84 | #86 |
|---|---|---|
| Raw booking / payment | CHO_THANH_TOAN / CHO_THANH_TOAN (payment49) | CHO_THANH_TOAN / CHO_THANH_TOAN (payment51) |
| Tạo/giữ gốc | 05/11 11:22:13 | 05/11 11:32:00 |
| Hạn gốc | 05/11 11:28:13 | 05/11 11:38:00 |
| Receipt nguồn thực | Legacy tag trong thanh_toan.ghi_chu; anh_minh_chung và minh_chung_gui_luc NULL | anh_minh_chung/minh_chung_gui_luc +legacy tag |
| Thời điểm ghi nhận trong dữ liệu | 05/11 11:22:21 (legacy) | 05/11 11:32:09 |
| Lịch | sân43, 05/11 11:30–12:30 | sân43, 05/11 12:00–13:00 |
| Tiền snapshot | sân80.000 +dịch vụ20.000 =100.000 đ | sân80.000 +dịch vụ20.000 =100.000 đ |
| Payment đã đối soát | Chưa, ngay_thanh_toan NULL | Chưa, ngay_thanh_toan NULL |

**Hai ảnh tồn tại, đều15.607byte và cùng SHA-256**, mặc dù đường dẫn riêng theo booking. Đây chỉ là bằng chứng trùng nội dung ảnh, không chứng minh có hai giao dịch hợp lệ. Hai khoảng chơi cùng sân giao nhau12:00–12:30, ngày chơi đã qua. Tuyệt đối không UPDATE hàng loạt sang review/confirmed hoặc suy ra đã nhận đủ tiền từ ảnh.

Giống báo cáo trước, dữ liệu cũ vẫn pending. Booking::unprotectedSql() bảo vệ pending có receipt khỏi job tự động để đối soát, **không tự chuyển sang CHO_XAC_NHAN, không tự xác nhận tiền hoặc phục hồi sân**. Không coi bảo vệ dữ liệu legacy là bằng chứng luồng upload mới còn lỗi.

Phương án xử lý sau phê duyệt: đối chiếu chứng từ ngân hàng/reference/khách hàng cho từng booking; kiểm lịch và việc sân đã sử dụng, các booking liên quan/tồn/hóa đơn; lập quyết định đối soát hoặc hủy/hoàn theo nghiệp vụ; nếu cần repair phải transaction có khóa, điều kiện trạng thái/version và audit trail, không tự cấp lại lịch đã xung đột. Không có audit trail phiên code đầy đủ để khẳng định chính xác phiên/ai đã tạo anomaly; workflow cũ là giải thích phù hợp dữ liệu, không thay chứng cứ runtime quá khứ.

## 6. Database thực tế — SELECT/metadata, không ghi

Số bản ghi cuối audit trùng snapshot đầu/những báo cáo trước; việc đếm không chứng minh mọi byte dữ liệu đều bất biến, nhưng mọi kết nối của harness ghi được tách DB và source unchanged.

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

| Trạng thái | Raw | Projection hiệu lực |
|---|---:|---:|
| CHO_THANH_TOAN | 15 | 2 |
| CHO_XAC_NHAN | 12 | 12 |
| DA_XAC_NHAN | 4 | 4 |
| HOAN_THANH | 0 | 0 |
| HET_HAN | 5 | 18 |
| DA_HUY | 60 | 60 |

13 pending quá hạn đủ điều kiện vẫn chưa được job ghi HET_HAN; projection hiển thị hết hạn. Hai pending có receipt legacy là #84/#86 cần đối soát. Không suy ra cả15 booking vẫn chiếm sân: schedule/inventory có predicate trạng thái/deadline riêng.

Query invariant cuối audit: tồn âm =0; invoice trùng theo booking =0; invoice.ma_dat_san khác payment.ma_dat_san =0. Đây là kiểm chứng trên dữ liệu hiện có (4 invoice), không thay khả năng validation chống mismatch (đã được thử riêng R38). Metadata/FK/index fresh test đạt theo R38; kiểm upgrade cũ chỉ thực hiện trên fixture legacy tự tạo trong DB riêng.

Schema mới đủ các trường hóa đơn/bank. Migration cũ được thiết kế nâng cấp DB cũ, không có lý do chạy lại trên fresh SQL đã có cột. R45 emulates oldschema bằng DDL chỉ ở DB test, chạy migration bank/invoice và xác nhận invoice row/money giữ nguyên; không chạy migration lên DB ứng dụng.

## 7. Danh sách phát hiện

### 7.1. Lỗi sản phẩm mới được xác minh trong audit này

| ID | Module | Lỗi | Severity | Priority | Xác minh |
|---|---|---|---|---|---|
| POST-001 | Hóa đơn | Dòng sân hiển thị đơn giá/thành tiền0 do đọc gia_tien thay snapshot | HIGH | HIGH | Confirmed — MySQL+HTTP+Chrome+server log |
| POST-002 | Hóa đơn khách hàng | In hóa đơn HTTP500 vì require sai thư mục | MEDIUM | HIGH | Confirmed — owner HTTP request và log |
| POST-003 | Asset hóa đơn khách hàng | filemtime trỏ sai filesystem root, mất cache version và phát sinh warning | LOW | LOW | Confirmed — PHP log |

Không có chứng cứ các lỗi invoice này do ba HIGH fixes gây ra: các chỗ render/path không nằm trong diff sửa CoSo/San/InvoiceService/main SQL. Phân loại là **newly discovered current code defects**, không tự khẳng định regression sau fix. Vẫn phải xử lý trước nghiệm thu theo mức ảnh hưởng.

### POST-001 — Nội dung tài chính từng dòng hóa đơn không khớp snapshot

- **Module:** hóa đơn. **Severity HIGH / Priority HIGH / Confirmed.** Môi trường: fresh MariaDB, PHP8.2, Chrome thực; fixture riêng, không tiền thật.
- **File/hàm:** HoaDon::details(), app/models/HoaDon.php:243 SELECT ct.*; app/views/invoices/show.php:87–88; app/views/admin/invoices/show.php:140–141; app/views/admin/invoices/print.php:92–93. Schema chi_tiet_dat_san lưu don_gia và thanh_tien, không có gia_tien.
- **Điều kiện:** hóa đơn có ít nhất một dòng tiền sân; giá snapshot dương. Không cần payment mismatch.
- **Tái hiện:** (1) fresh main SQL; (2) khách đặt sânA09:00–10:00 và sânB10:00–10:30, rate100.000/giờ; thêm2chai×10.000; (3) upload hợp lệ và admin duyệt; (4) mở owner /invoices/show?id=1 hoặc admin show/print.
- **Expected:** dòng sânA có amount100.000, sânB50.000; dịch vụ20.000; tổng170.000; unit phải lấy đúng snapshot và thể hiện thời lượng nhất quán.
- **Actual:** cả hai dòng sân hiển thị đơn giá0/thành tiền0; dòng dịch vụ20.000 đúng; phía tổng vẫn tiền sân150.000, dịch vụ20.000, tổng170.000 đúng. Hóa đơn trình bày mâu thuẫn ngay trên một tài liệu.
- **Evidence:** storage/temp/postfix-e2e-QPhcAK/invoice-1440.png; invoice-390.png; customer-invoice-response.log; server.log (Undefined array key gia_tien tại các dòng trên). Đã xem ảnh desktop trực tiếp; Chrome xác nhận render thật. HTML table có các số 1,0,0 cho hai dòng sân, dịch vụ2,10.000,20.000.
- **Nguyên nhân:** view đọc key không được model trả, PHP chuyển null về0. Đây không phải thay đổi price rule hoặc sai tổng database. Không hạ thành cosmetic vì thông tin tài chính của hóa đơn sai; chưa có bằng chứng ghi sai tiền hay thu tiền sai nên không nâng CRITICAL.
- **Phạm vi:** khách xem hóa đơn, admin xem/in dùng cùng contract line. HTTP200 của admin không chứng minh nội dung đúng.
- **Đề xuất tối thiểu (chưa thực hiện):** map đúng snapshot don_gia/thanh_tien riêng biệt, rà semantics hourly rate/duration; giữ nguyên tổng đã lưu và transaction. Không dùng giá catalog hiện tại hay điền lại key giả bằng giá cứng.
- **Regression risk:** dòng30phút, nhiều mức giá/nhiều sân, dịch vụ và hóa đơn cũ; test sum lines vs saved totals và3view sau sửa.

### POST-002 — Khách hàng không in được hóa đơn

- **Module:** hóa đơn khách. **Severity MEDIUM / Priority HIGH / Confirmed.** Môi trường cùng R46.
- **File/hàm:** InvoiceController::print(), app/controllers/InvoiceController.php:135.
- **Điều kiện:** KH đã login, đúng chủ booking/invoice; invoice tồn tại. IDOR không phải nguyên nhân ca này.
- **Tái hiện:** sau khi admin duyệt như POST-001, request index.php?route=/invoices/print&id=1 bằng session KH sở hữu.
- **Expected:**200 HTML bản in đúng dữ liệu, không ghi booking/payment.
- **Actual:**HTTP500. Owner /invoices/show trả200; /admin/invoices/print cùng invoice trả200.
- **Evidence:** report.json check `owner invoice /invoices/print` ok=false data500; server.log có Application failure: Error at InvoiceController.php:135.
- **Nguyên nhân:** dirname(__DIR__,2) từ app/controllers là repo root, nối /views/admin/invoices/print.php thiếu /app. File thật ở app/views/admin/invoices/print.php.
- **Phạm vi/Severity:** chức năng in phía khách bị mất, vẫn có xem và admin print thay thế; không thấy sai dữ liệu lưu nên MEDIUM, ưu tiên HIGH vì luồng nghiệm thu invoice.
- **Đề xuất tối thiểu:** sửa đường dẫn include hoặc cơ chế render đúng convention hiện có; không sửa quyền/invoice service/UI. Chưa thực hiện.
- **Regression risk:** owner/non-owner checks, cả id và booking_id, no-header-output trước bản in; phải test lại200/404 và nội dung POST-001.

### POST-003 — Warning filemtime ở hóa đơn khách

- **Module:** invoice asset loading. **Severity LOW / Priority LOW / Confirmed.** Môi trường same-origin PHP server như trên.
- **File:** app/views/invoices/show.php:4–5. Không quy lỗi này cho admin invoice views: độ sâu thư mục admin khác, dirname4 ở đó phù hợp.
- **Tái hiện:** mở hóa đơn owner hợp lệ; kiểm PHP log.
- **Expected:** cache suffix lấy mtime của repo/public/assets/css/invoice.css và js/invoice.js.
- **Actual:**stat failed for C:/xampp/htdocs/public/assets/...; suffixv= trống/false. Asset HTTP URL vẫn load được trong ca test, không báo sai thành404.
- **Evidence:** server.log PHP Warning filemtime invoice/show.php4,5; network CSS/JS200. display_errors=0 nên warning ở log, không lẫn nội dung ảnh/chứng từ.
- **Nguyên nhân:** dirname(__DIR__,4) đi lên quá một cấp với thư mục app/views/invoices.
- **Đề xuất tối thiểu:** dùng root đúng cho view này; không đổi toàn cục cache/CSP. Chưa thực hiện. Regression risk thấp, cần kiểm URLs/version của riêng invoice.

### 7.2. Regression của test infrastructure và lỗi cũ

| ID | Phân loại | Severity | Priority | Trạng thái hiện tại |
|---|---|---|---|---|
| REG-001 (mở rộng BUG-006) | Test Infrastructure | MEDIUM | HIGH | Confirmed: fixture chưa thích ứng main SQL mới; PASS cũ→FAIL setup |
| BUG-004 | Historical data anomaly | HIGH | HIGH | #84/#86 chưa thay đổi; cần phê duyệt đối soát |
| BUG-005 | Deployment configuration | LOW | HIGH | Máy audit chưa thấy task hết hạn và còn13pending đủ điều kiện chưa ghi hết hạn |
| BUG-008 | Deployment configuration | LOW | LOW | HEAD root302 đến tên thư mục cũ |
| BUG-009 | Current code/CSP compatibility, đã biết | LOW | LOW | Chrome ghi nhận inline style bị chặn; không uncaught JS error |

BUG-007 không nằm trong bảng lỗi còn mở; người dùng chủ động bỏ nhãn. Báo cáo cũ được giữ làm lịch sử, không dùng nhận định BUG-007 cũ để yêu cầu thay giao diện hiện tại.

### REG-001 — Bộ fixture lặp schema và assertion cũ

- **Module:** regression tests. **Severity MEDIUM / Priority HIGH / Test Infrastructure (Confirmed).** Không nâng thành lỗi phân quyền/tiền chỉ do test FAIL.
- **File/cause:** tests/fixtures/booking-pr6.php:35 nối main SQL với migration bank rồi parse từng CREATE TABLE thành SQLite CREATE không IF NOT EXISTS (exec33). Sau fixBUG-002 main đã chứa bank, nên tạo2lần. Sau bước đó còn ALTER invoice ở70–71 sẽ lặp. tests/fixtures/dashboard.php:28–29 ALTER so_hoa_don/ma_thanh_toan khi main đã có.
- **Tái hiện:** php tests/authorization-unit.php; php tests/dashboard-unit.php trên source hiện tại. Không cần MySQL hay tài khoản thật.
- **Expected:** fixture dựng được rồi chạy checks phân quyền/dashboard.
- **Actual/evidence:** authorization-unit dừng General error1 table tai_khoan_ngan_hang_co_so already exists; dashboard-unit dừng duplicate column name:so_hoa_don. Trước fix báo cáo FULL_SYSTEM có PASS262 và PASS101 tương ứng.
- **Ảnh hưởng:** che phủ các ca unit downstream, khiến gate test không tin cậy. Hai suite receipt MySQL cũng BLOCKED preflight do áp migration bổ sung trên schema đã có. Đây là regression liên quan thay đổi contract schema/setup, **không phải import main SQL mới thất bại**.
- **Các FAIL cũ khác:** fakePDO chưa override exec/không constructor; predicates/markup/sidebar allowlist cũ; rental CHO_THUE expectation cũ. Có log điểm dừng trong bảng R01–R42. Không đổi test trong audit, không kết luận mọi ca sau điểm dừng PASS.
- **Đề xuất tối thiểu:** sau khi được phép, phân biệt freshschema vs legacyupgrade trong fixture, tránh duplicate CREATE/ALTER; update fake dialect và assertion đúng quyết định sản phẩm. Giữ ca upgrade riêng. Không thêm IF NOT EXISTS tràn lan che lỗi production; không sửa logic app chỉ để đáp ứng test lỗi thời.
- **Regression risk:** nếu sửa fixture quá thoáng có thể bỏ mất test constraint/migration. Cần chạy lại suite đầy đủ và so expected before/after, không xóa tests để làm xanh.

### BUG-005 — Job tự động hết hạn chưa được chứng minh vận hành

- **Severity LOW / Priority HIGH / Deployment, Confirmed trên máy audit.** Chỉ thấp về ảnh hưởng đã chứng minh: effective history/schedule có guard, chưa thấy doublebooking vì job thiếu; ưu tiên cao vì backend raw trạng thái/backlog cần vận hành tin cậy.
- **File:** bin/expire-bookings.php; Booking expiry batch/report; config/docs liên quan. R39 chứng minh script chạy trên DB test và lock/batch/CLI đúng.
- **Tái hiện:** đọc Get-ScheduledTask, tìm action/php/expire/STRYKE: không có task phù hợp trên máy hiện tại; SELECT live15pending,13đủ expiry chưa materialize. Không đăng ký/chạy task trên DB thật.
- **Expected:** scheduler riêng chạy định kỳ không phụ thuộc khách truy cập, có log/monitoring/anomaly queue.
- **Actual:** CLI có và test đạt nhưng chưa thấy bằng chứng lịch tự chạy. Không khẳng định server/cron ngoài máy này cũng chưa cấu hình.
- **Đề xuất:** cấu hình Task Scheduler/cron theo tài liệu sau phê duyệt, dùng đúng PHP/env/workdir và monitor logs/backlog; không thay GET thành job hoặc repair receipt legacy tự động.
- **Regression risk:** chạy nhầm DB, chồng job, hiểu nhầm pending protectedlegacy. R39 đã có process/job lock và batch tests nhưng triển khai thật vẫn cần xác minh.

### BUG-008 — Root redirect sai thư mục triển khai

- **Severity LOW / Priority LOW / Deployment, Confirmed HTTP.** File .htaccess:3 RewriteBase /QuanLyDatSan/.
- **Tái hiện:** HEAD http://127.0.0.1/BaiTapNhomPHP/ →302 Location http://127.0.0.1/QuanLyDatSan/public/.
- **Expected:** public entry đúng thư mục hiện tại. **Actual:** URL gốc trỏ tên cũ; vào /BaiTapNhomPHP/public/ bằng GET vẫn là đường test public hợp lệ.
- **Đề xuất tối thiểu:** cấu hình documentroot/public hoặc deployment rewrite đúng environment sau phê duyệt. Không sửa config trong audit.
- **Risk:** base_path/relative asset và session cookie paths khi đổi deploy; cần smoke sau cấu hình.

### BUG-009 — CSP chặn inline style

- **Severity LOW / Priority LOW / Confirmed runtime.** public/index.php:19 style-src self; nhiều style= ở admin booking/invoice/wait view vẫn tồn tại.
- **Tái hiện:** dùng entry public/index.php với CSP thật trong R46, mở admin booking và invoice; thu securitypolicyviolation.
- **Expected:** style implementation tương thích policy. **Actual:**243 observation style-src-attr/inline trong log E2E mới nhất, gồm event lặp/thu nhiều lần, **không phải243bug riêng**. Không có uncaught JS exceptions; tương tác confirm/upload vẫn hoàn tất.
- **Evidence:** storage/temp/postfix-e2e-QPhcAK/report.json csp; sourceheader/line của event; screenshots.
- **Đề xuất:** sau phê duyệt tách style cần thiết sang stylesheet scoped, không nới unsafe-inline để che lỗi. Không thiết kế lại UI chỉ vì cảnh báo.
- **Risk:** đổi style có thể ảnh hưởng spacing; chưa đo mọi inline attribute bị chặn nên không khẳng định mọi trang hiển thị chuẩn hoặc mọi trang bị hỏng.

## 8. Bảo mật, transaction và tính toàn vẹn

### Đã xác minh thực tế

- R43: role injection khi register không lên ROOT; SQLi login không qua; đổi sessionID lúc login/password, thu hồi session account khóa; CSRF, password hiện tại và hash password_verify; external redirect bị bỏ qua; profile XSS escaped và forged ID không thay chủ hồ sơ.
- R38/R46: CSRF upload, MIME/size, owner receipt, duplicate receipt không ghi đè file đã nhận, không tự đánh dấu payment paid từ ảnh.
- R45: lock hàng sân nhiều sân theo transaction, cùngslot chỉ1winner; cùngidempotencykey trả cùngID; stock contention không vượt tồn; cancel giải phóng giữ; manualapproval2actor chỉ1invoice/1deduction. Fault injection ở DB test làm invoice ghi lỗi → rollback booking/payment/stock.
- R38: payment thuộc booking khác dù cùng tiền bị từ chối; missingpayment/duplicateInvoice bị xử lý. FK riêng lẻ không đủ cross-booking consistency, serviceguard mới là phần đã kiểm chứng.
- HTTP Apache read-only: .env, .git/config, database SQL, storage/receipts/ đều403. Root entry redirect sai như BUG-008. HEAD/public trả405 vì router không khai HEAD, không dùng405 này để khẳng định GET/public hỏng.
- Giá và tiền cuối lấy backend; browser draft không quyết định quyền/giá. R36 mockresponse tests không đủ riêng, R46 đã xác nhận apply server thật. Không tìm thấy sai tổng lưu của fixture approved170.000, chỉ sai dòng hiển thị POST-001.

### Giới hạn quan trọng

Đây không phải penetration test vét cạn. Chưa fuzz mọi field/định dạng ảnh/polyglot/path traversal/mọiID/đường export; chưa bank provider/callback production; chưa DOS/stress load. Những query/session/proxy route được review không tự thành PASS runtime toàn module. Quyền CS/NV scope được thử trên fixtures hiện có, không giả định nhiều cơ sở nếu schema không có assignment đó.

Nhận ảnh hợp lệ chỉ chứng minh upload kỹ thuật, không chứng minh số tiền trong ảnh hay ngân hàng đã hạch toán. Nhân viên duyệt trong ca E2E là manual verification workflow được ứng dụng hỗ trợ; không giả vờ đã chuyển tiền thật. Không có QR giả hoặc callback giả tác động dữ liệu thật.

## 9. Bằng chứng, ảnh và khả năng tái hiện

### Artifact chính của lượt kiểm thử

| Nhóm | Artifact |
|---|---|
| E2E PHP+MariaDB+Chrome,53checks | storage/temp/postfix-e2e-QPhcAK/report.json; server.log |
| Invoice lỗi0/print500 | storage/temp/postfix-e2e-QPhcAK/customer-invoice-response.log; invoice-1440.png; invoice-390.png |
| Tab dịch vụ/drawer5widths | storage/temp/postfix-e2e-QPhcAK/services-{360,390,768,1024,1440}.png |
| HIGH regression48checks | storage/temp/high-bug-fixes-a4981f790b/ |
| Employee MySQL/HTTP48 +browser21 | storage/temp/employee-account-a96bf1d18c/ |
| Customer browser29 | storage/temp/customer-browser-q3OEAx/ |
| Services browser32 | storage/temp/facility-services-raqKMj/ |
| History browser75 | storage/temp/history-review/ (fixture test, không ảnh dữ liệu khách thật) |
| Auth HTTP28 | storage/temp/postauth-f4b05315c0/ |
| Output suite/unit/custom +currentrouteinventory | storage/temp/post-fix-audit-20261106/regression-output.log; business-checks.log; routes.log |

Main E2E report có done=true và cleanup.removed=stryke_postfix_test_fdec3b8d02. Các lần thử harness sớm hơn giữ log riêng nhưng không cộng assertions lặp vào tổng. Chỉ dùng run cuối để tính52/1. Snapshot testPNG được dọn theo ownership; screenshot/log không chứa mật khẩu thật hay thông tin ngân hàng cá nhân thật.

Đã trực tiếp xem screenshot invoice1440 và services360, đối chiếu DOM/network ở5widths. Các suite employee/history cũng chụp ảnh. Chưa thể từ đó chứng nhận mọi trang admin/mobile đẹp hoặc mọi animation mượt.

### Chuỗi E2E thực tế (không mock API)

1. GET/courts public, AJAX panel đúng cơ sở,5tab; lịch36slots theo05–23.
2. Chọn3slots/2sân; backendquote150.000; mởdetails chưa tạo hold.
3. Login từreview giữđúngdraft. Modal qty2chỉtemporary; Apply→backend170.000.
4. Confirm→CHO_THANH_TOAN, expiry gốc; refresh không reset; stock5;invoice0.
5. Khôngfile browserrequired chặn; invalid MIME/oversize server từ chối, trạng thái chưa đổi.
6. Upload PNGhợp lệ→CHO_XAC_NHAN, payment vẫnCHO_THANH_TOAN, stock5,invoice0; result riêng, khôngbanner00:00.
7. ROOT mởbooking→modalconfirm→duyệt:DA_XAC_NHAN/paymentTHANH_CONG/invoice1/stock3.
8. Customerresultreflectsconfirmed; historycóbooking; invoiceshowHTTP200 nhưnglines0; invoiceprintHTTP500.
9. LoginactorcompanyB/customerkhác→booking/receipt/customerdetail404;companyA/managerA/staffassigned→booking200.

Như vậy **E2E booking→duyệt→tạo dữ liệu invoice hoàn tất**, nhưng **E2E xem/in invoice đúng nghĩa nghiệp vụ chưa đạt**. Không tô xanh cả luồng vì insertinvoice thành công.

## 10. Đề xuất thứ tự xử lý (không thực hiện trong audit)

1. **POST-001**: sửa mapping snapshot các dòng hóa đơn, test đơn giá/thành tiền và tổng trên customer/admin/print,30phút/multicourt/multirate.
2. **POST-002**: sửa include path customerprint; test owner200/non-owner404, print không ghi DB. POST-003 xử lý cùng file đúng phạm vi khi có phê duyệt.
3. **REG-001/BUG-006**: tách fixture freshschema/legacyupgrade, bỏ migration lặp ởtestsetup, cập nhật assertion theo quyết định thật (không phục hồi BUG-007), chạy lại16suite đangdừng +13gate sauđủđiềukiện.
4. **BUG-004**: đối soát riêng #84/#86 trước bất kỳ repair nào, đặc biệt ảnhgiốngnhau/lịchgiaonhau. Không tự đưa cảhai sangreview hoặcpaid.
5. **BUG-005**: cấu hình/monitor job trên đúng môi trường sau phê duyệt; xác nhận chạy khi không cóvisitor và cólogginganomaly. Dữ liệu pendinglegacy không autoapprove.
6. Hoàn tất những ca N01–N08 theo môi trường, nhất là actualHTTPupload-vs-expiry, rolematrix còn lại, provider/tảiQR/quét/thanh toán thật. Sửa deploymentroot/CSP riêng, không thay logic booking để che vấn đề.

## 11. Trả lời trực tiếp và quyết định nghiệm thu

| Câu hỏi | Kết luận có giới hạn |
|---|---|
| Còn CRITICAL được xác nhận? | Chưa phát hiện trong phạm vi thực chạy. Không khẳng định không tồn tại ở phần chưa kiểm. |
| Còn HIGH? | Có POST-001 nội dung tiền sân trên invoice sai; BUG-004 là dữ liệu lịch sử chưa đối soát, tách code defect. |
| Còn MEDIUM? | Có POST-002 customerprint500; REG-001 là testinfrastructure, không đánh tráo thành lỗi payment. |
| BUG-001/002/003 thực sự đã sửa? | Có trong các ca MariaDB/HTTP/concurrency đã mô tả; test48checks freshschema và guards đủ điều kiện. |
| BUG-004 còn sinh booking mới? | Chưa tái hiện: R38/R46 upload mới vào review atomically, không paid, không resetdeadline. #84/#86 vẫn cần giải quyết riêng. |
| Có PASS cũ nay FAIL? | authorization-unit và dashboard-unit, do setupduplicatebank/invoice; receipt suites bị preflightBLOCKED. Chưa có bằng chứng permission/revenue production hỏng vì chúng. |
| Có lỗi đặttrùng/scope/tồn mới? | Không phát hiện trong ca processrace và scopeHTTP đã chạy; chưa chứng nhận mọi tải/interleaving/route. |
| Có lỗi invoice mới? | Có POST-001/002/003. Total/snapshotDB của fixture đúng; viewlines/print có lỗi. |
| Core E2E có hoàn tất? | Chọn→login→quote→hold→receipt→review→adminapprove→invoiceDB: có. Xem/in hóa đơn hoàn chỉnh: chưa đạt. Không test ngân hàng thật. |
| Những phần còn thiếu? | B01–B13/N01–N08 và các hàng partial trong ma trận; chi tiết dependency/testsetup/thiết bị/provider ghi rõ. |

**Kết luận: NOT READY cho nghiệm thu toàn hệ thống.** Có thể tiếp tục review riêng ba HIGH fixes đã verified, nhưng không dùng kết quả đó làm chứng nhận release tổng thể. Chỉ cân nhắc nghiệm thu sau xử lý các lỗi invoice, khôi phục gate regression, quyết định đối soát dữ liệu lịch sử và xác minh các phần vận hành/coverage còn thiếu.

**Xác nhận phạm vi:** không sửa PHP/SQL/JS/CSS/view/routes/tests/config; không chạy migration hoặc UPDATE/INSERT/DELETE trên DB ứng dụng; không đổi workflow/giá/giữsân/tồn/quyền; không sửa UI; không khôi phục BUG-007; không commit/push/merge. File báo cáo này và log/screenshot/session/profile/ảnh test tạm được phép là sản phẩm duy nhất của lượt audit.


## Phụ lục A. Inventory routes hiện tại (đăng ký runtime, không đồng nghĩa đã E2E toàn bộ)

202 entries: GET 127, POST 75. Thu bằng Reflection trên Core\Router với PDO SQLite memory, không dispatch controller và không nối DB ứng dụng. Roles ở đây là allowlist middleware; roles rỗng có thể là public hoặc controller tự kiểm quyền, phải đọc thêm handler. Kết quả CODE REVIEW/INVENTORY ONLY trừ các route đã ghi trong ma trận thực thi.

| Method | Route | Handler | Role allowlist / middleware |
|---|---|---|---|
| GET | / | HomeController::index | Không có role middleware; kiểm handler |
| GET | /account | AccountController::show | AuthMiddleware |
| GET | /account/settings | AccountController::settings | AuthMiddleware |
| GET | /admin/companies | CompanyController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/companies/create | CompanyController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/companies/edit | CompanyController::edit | QUAN_TRI_TAP_DOAN |
| GET | /admin/contracts | ContractController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/contracts/create | ContractController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/contracts/edit | ContractController::edit | QUAN_TRI_TAP_DOAN |
| GET | /admin/employees | EmployeeController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/employees/show | EmployeeController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/employees/create | EmployeeController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/employees/edit | EmployeeController::edit | QUAN_TRI_TAP_DOAN |
| GET | /company/employees | EmployeeController::index | QUAN_LY_CONG_TY |
| GET | /company/employees/show | EmployeeController::show | QUAN_LY_CONG_TY |
| GET | /company/employees/create | EmployeeController::create | QUAN_LY_CONG_TY |
| GET | /company/employees/edit | EmployeeController::edit | QUAN_LY_CONG_TY |
| GET | /facility/employees | EmployeeController::index | QUAN_LY_CO_SO |
| GET | /facility/employees/show | EmployeeController::show | QUAN_LY_CO_SO |
| GET | /facility/employees/create | EmployeeController::create | QUAN_LY_CO_SO |
| GET | /facility/employees/edit | EmployeeController::edit | QUAN_LY_CO_SO |
| GET | /admin/revenue | admin\DashboardController::index | QUAN_TRI_TAP_DOAN |
| GET | /company/revenue | admin\DashboardController::index | QUAN_LY_CONG_TY |
| GET | /facility/revenue | admin\DashboardController::index | QUAN_LY_CO_SO |
| GET | /staff/revenue | admin\DashboardController::index | NHAN_VIEN |
| GET | /company/profile | CompanyController::dashboard | QUAN_LY_CONG_TY |
| GET | /company | admin\DashboardController::index | QUAN_LY_CONG_TY |
| GET | /facility | admin\DashboardController::index | QUAN_LY_CO_SO |
| GET | /staff | admin\DashboardController::index | NHAN_VIEN |
| GET | /customer | closure | KHACH_HANG |
| GET | /login | AuthController::loginForm | Không có role middleware; kiểm handler |
| GET | /register | AuthController::registerForm | Không có role middleware; kiểm handler |
| GET | /admin | admin\DashboardController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/bo-mon | admin\BoMonController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/bo-mon/create | admin\BoMonController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/bo-mon/show | admin\BoMonController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/bo-mon/edit | admin\BoMonController::edit | QUAN_TRI_TAP_DOAN |
| GET | /admin/bo-mon/delete | admin\BoMonController::confirmDelete | QUAN_TRI_TAP_DOAN |
| GET | /admin/loai-san | admin\LoaiSanController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/loai-san/create | admin\LoaiSanController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/loai-san/show | admin\LoaiSanController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/loai-san/edit | admin\LoaiSanController::edit | QUAN_TRI_TAP_DOAN |
| GET | /admin/loai-san/delete | admin\LoaiSanController::confirmDelete | QUAN_TRI_TAP_DOAN |
| GET | /admin/co-so | admin\CoSoController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/co-so/show | admin\CoSoController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/co-so/edit | admin\CoSoController::edit | QUAN_TRI_TAP_DOAN |
| GET | /admin/co-so/create | admin\CoSoController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/co-so/delete | admin\CoSoController::confirmDelete | QUAN_TRI_TAP_DOAN |
| GET | /admin/san | admin\SanController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/san/show | admin\SanController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/san/edit | admin\SanController::edit | QUAN_TRI_TAP_DOAN |
| GET | /admin/san/create | admin\SanController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/san/delete | admin\SanController::confirmDelete | QUAN_TRI_TAP_DOAN |
| GET | /company/co-so | admin\CoSoController::index | QUAN_LY_CONG_TY |
| GET | /company/co-so/show | admin\CoSoController::show | QUAN_LY_CONG_TY |
| GET | /company/co-so/edit | admin\CoSoController::edit | QUAN_LY_CONG_TY |
| GET | /company/co-so/create | admin\CoSoController::create | QUAN_LY_CONG_TY |
| GET | /company/san | admin\SanController::index | QUAN_LY_CONG_TY |
| GET | /company/san/show | admin\SanController::show | QUAN_LY_CONG_TY |
| GET | /company/san/edit | admin\SanController::edit | QUAN_LY_CONG_TY |
| GET | /company/san/create | admin\SanController::create | QUAN_LY_CONG_TY |
| GET | /facility/co-so | admin\CoSoController::index | QUAN_LY_CO_SO |
| GET | /facility/co-so/show | admin\CoSoController::show | QUAN_LY_CO_SO |
| GET | /facility/co-so/edit | admin\CoSoController::edit | QUAN_LY_CO_SO |
| GET | /facility/san | admin\SanController::index | QUAN_LY_CO_SO |
| GET | /facility/san/show | admin\SanController::show | QUAN_LY_CO_SO |
| GET | /facility/san/edit | admin\SanController::edit | QUAN_LY_CO_SO |
| GET | /facility/san/create | admin\SanController::create | QUAN_LY_CO_SO |
| GET | /staff/co-so | admin\CoSoController::index | NHAN_VIEN |
| GET | /staff/co-so/show | admin\CoSoController::show | NHAN_VIEN |
| GET | /staff/san | admin\SanController::index | NHAN_VIEN |
| GET | /staff/san/show | admin\SanController::show | NHAN_VIEN |
| GET | /courts | CourtBrowserController::index | Không có role middleware; kiểm handler |
| GET | /courts/show | CourtBrowserController::show | Không có role middleware; kiểm handler |
| GET | /courts/panel | CourtBrowserController::panel | Không có role middleware; kiểm handler |
| GET | /courts/schedule | CourtBrowserController::schedule | Không có role middleware; kiểm handler |
| GET | /courts/prices | CourtBrowserController::prices | Không có role middleware; kiểm handler |
| GET | /booking/history | BookingHistoryController::index | KHACH_HANG |
| GET | /booking/history/receipt | BookingHistoryController::receipt | KHACH_HANG |
| GET | /booking/details | BookingController::details | Không có role middleware; kiểm handler |
| GET | /booking/wait | BookingController::waiting | KHACH_HANG |
| GET | /booking/result | BookingController::waiting | KHACH_HANG |
| GET | /booking/receipt | BookingController::receipt | KHACH_HANG, QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/bang-gia | admin\BangGiaController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/bang-gia/show | admin\BangGiaController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/bang-gia/create | admin\BangGiaController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/bang-gia/edit | admin\BangGiaController::edit | QUAN_TRI_TAP_DOAN |
| GET | /company/bang-gia | admin\BangGiaController::index | QUAN_LY_CONG_TY |
| GET | /company/bang-gia/show | admin\BangGiaController::show | QUAN_LY_CONG_TY |
| GET | /company/bang-gia/create | admin\BangGiaController::create | QUAN_LY_CONG_TY |
| GET | /company/bang-gia/edit | admin\BangGiaController::edit | QUAN_LY_CONG_TY |
| GET | /facility/bang-gia | admin\BangGiaController::index | QUAN_LY_CO_SO |
| GET | /facility/bang-gia/show | admin\BangGiaController::show | QUAN_LY_CO_SO |
| GET | /facility/bang-gia/create | admin\BangGiaController::create | QUAN_LY_CO_SO |
| GET | /facility/bang-gia/edit | admin\BangGiaController::edit | QUAN_LY_CO_SO |
| GET | /staff/bang-gia | admin\BangGiaController::index | NHAN_VIEN |
| GET | /staff/bang-gia/show | admin\BangGiaController::show | NHAN_VIEN |
| GET | /admin/dich-vu | admin\DichVuController::index | QUAN_TRI_TAP_DOAN |
| GET | /admin/dich-vu/show | admin\DichVuController::show | QUAN_TRI_TAP_DOAN |
| GET | /admin/dich-vu/create | admin\DichVuController::create | QUAN_TRI_TAP_DOAN |
| GET | /admin/dich-vu/edit | admin\DichVuController::edit | QUAN_TRI_TAP_DOAN |
| GET | /company/dich-vu | admin\DichVuController::index | QUAN_LY_CONG_TY |
| GET | /company/dich-vu/show | admin\DichVuController::show | QUAN_LY_CONG_TY |
| GET | /company/dich-vu/create | admin\DichVuController::create | QUAN_LY_CONG_TY |
| GET | /company/dich-vu/edit | admin\DichVuController::edit | QUAN_LY_CONG_TY |
| GET | /facility/dich-vu | admin\DichVuController::index | QUAN_LY_CO_SO |
| GET | /facility/dich-vu/show | admin\DichVuController::show | QUAN_LY_CO_SO |
| GET | /facility/dich-vu/create | admin\DichVuController::create | QUAN_LY_CO_SO |
| GET | /facility/dich-vu/edit | admin\DichVuController::edit | QUAN_LY_CO_SO |
| GET | /staff/dich-vu | admin\DichVuController::index | NHAN_VIEN |
| GET | /staff/dich-vu/show | admin\DichVuController::show | NHAN_VIEN |
| GET | /facility/bank | BankController::index | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /facility/bank/create | BankController::create | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| GET | /facility/bank/edit | BankController::edit | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| GET | /payment | PaymentController::show | KHACH_HANG |
| GET | /payment/status | PaymentController::status | Không có role middleware; kiểm handler |
| GET | /payment/qr | PaymentController::qrImage | Không có role middleware; kiểm handler |
| GET | /admin/bookings | admin\AdminBookingController::index | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/bookings/show | admin\AdminBookingController::show | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/customers | admin\CustomerController::index | QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/customers/show | admin\CustomerController::show | QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/invoices | admin\AdminInvoiceController::index | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/invoices/show | admin\AdminInvoiceController::show | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /admin/invoices/print | admin\AdminInvoiceController::print | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| GET | /invoices | InvoiceController::index | KHACH_HANG |
| GET | /invoices/show | InvoiceController::show | KHACH_HANG |
| GET | /invoices/print | InvoiceController::print | KHACH_HANG |
| POST | /account | AccountController::updateProfile | KHACH_HANG |
| POST | /account/password | AccountController::changePassword | AuthMiddleware |
| POST | /admin/companies | CompanyController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/companies/update | CompanyController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/companies/status | CompanyController::changeStatus | QUAN_TRI_TAP_DOAN |
| POST | /admin/contracts | ContractController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/contracts/update | ContractController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/contracts/status | ContractController::changeStatus | QUAN_TRI_TAP_DOAN |
| POST | /admin/employees | EmployeeController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/employees/update | EmployeeController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/employees/status | EmployeeController::changeStatus | QUAN_TRI_TAP_DOAN |
| POST | /admin/employees/account | EmployeeController::grantAccount | QUAN_TRI_TAP_DOAN |
| POST | /company/employees | EmployeeController::store | QUAN_LY_CONG_TY |
| POST | /company/employees/update | EmployeeController::update | QUAN_LY_CONG_TY |
| POST | /company/employees/status | EmployeeController::changeStatus | QUAN_LY_CONG_TY |
| POST | /company/employees/account | EmployeeController::grantAccount | QUAN_LY_CONG_TY |
| POST | /facility/employees | EmployeeController::store | QUAN_LY_CO_SO |
| POST | /facility/employees/update | EmployeeController::update | QUAN_LY_CO_SO |
| POST | /facility/employees/status | EmployeeController::changeStatus | QUAN_LY_CO_SO |
| POST | /facility/employees/account | EmployeeController::grantAccount | QUAN_LY_CO_SO |
| POST | /login | AuthController::login | Không có role middleware; kiểm handler |
| POST | /register | AuthController::register | Không có role middleware; kiểm handler |
| POST | /logout | AuthController::logout | Không có role middleware; kiểm handler |
| POST | /admin/bo-mon/store | admin\BoMonController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/bo-mon/update | admin\BoMonController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/bo-mon/delete | admin\BoMonController::destroy | QUAN_TRI_TAP_DOAN |
| POST | /admin/loai-san/store | admin\LoaiSanController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/loai-san/update | admin\LoaiSanController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/loai-san/delete | admin\LoaiSanController::destroy | QUAN_TRI_TAP_DOAN |
| POST | /admin/co-so/update | admin\CoSoController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/co-so/store | admin\CoSoController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/co-so/delete | admin\CoSoController::destroy | QUAN_TRI_TAP_DOAN |
| POST | /admin/san/update | admin\SanController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/san/store | admin\SanController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/san/delete | admin\SanController::destroy | QUAN_TRI_TAP_DOAN |
| POST | /company/co-so/update | admin\CoSoController::update | QUAN_LY_CONG_TY |
| POST | /company/co-so/store | admin\CoSoController::store | QUAN_LY_CONG_TY |
| POST | /company/san/update | admin\SanController::update | QUAN_LY_CONG_TY |
| POST | /company/san/store | admin\SanController::store | QUAN_LY_CONG_TY |
| POST | /facility/co-so/update | admin\CoSoController::update | QUAN_LY_CO_SO |
| POST | /facility/san/update | admin\SanController::update | QUAN_LY_CO_SO |
| POST | /facility/san/store | admin\SanController::store | QUAN_LY_CO_SO |
| POST | /booking/quote | BookingController::quote | Không có role middleware; kiểm handler |
| POST | /booking/start | BookingController::start | Không có role middleware; kiểm handler |
| POST | /booking/review | BookingController::review | Không có role middleware; kiểm handler |
| POST | /booking/confirm | BookingController::confirm | Không có role middleware; kiểm handler |
| POST | /booking/receipt | BookingController::uploadReceipt | KHACH_HANG |
| POST | /booking/expire | BookingController::expire | KHACH_HANG |
| POST | /booking/cancel | BookingController::cancel | KHACH_HANG |
| POST | /booking/payment | BookingController::retryPayment | KHACH_HANG |
| POST | /admin/bang-gia/store | admin\BangGiaController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/bang-gia/update | admin\BangGiaController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/bang-gia/status | admin\BangGiaController::changeStatus | QUAN_TRI_TAP_DOAN |
| POST | /company/bang-gia/store | admin\BangGiaController::store | QUAN_LY_CONG_TY |
| POST | /company/bang-gia/update | admin\BangGiaController::update | QUAN_LY_CONG_TY |
| POST | /company/bang-gia/status | admin\BangGiaController::changeStatus | QUAN_LY_CONG_TY |
| POST | /facility/bang-gia/store | admin\BangGiaController::store | QUAN_LY_CO_SO |
| POST | /facility/bang-gia/update | admin\BangGiaController::update | QUAN_LY_CO_SO |
| POST | /facility/bang-gia/status | admin\BangGiaController::changeStatus | QUAN_LY_CO_SO |
| POST | /admin/dich-vu/store | admin\DichVuController::store | QUAN_TRI_TAP_DOAN |
| POST | /admin/dich-vu/update | admin\DichVuController::update | QUAN_TRI_TAP_DOAN |
| POST | /admin/dich-vu/status | admin\DichVuController::changeStatus | QUAN_TRI_TAP_DOAN |
| POST | /company/dich-vu/store | admin\DichVuController::store | QUAN_LY_CONG_TY |
| POST | /company/dich-vu/update | admin\DichVuController::update | QUAN_LY_CONG_TY |
| POST | /company/dich-vu/status | admin\DichVuController::changeStatus | QUAN_LY_CONG_TY |
| POST | /facility/dich-vu/store | admin\DichVuController::store | QUAN_LY_CO_SO |
| POST | /facility/dich-vu/update | admin\DichVuController::update | QUAN_LY_CO_SO |
| POST | /facility/dich-vu/status | admin\DichVuController::changeStatus | QUAN_LY_CO_SO |
| POST | /facility/bank/store | BankController::store | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| POST | /facility/bank/update | BankController::update | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| POST | /facility/bank/set-default | BankController::setDefault | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| POST | /facility/bank/toggle-status | BankController::toggleStatus | QUAN_LY_CONG_TY, QUAN_LY_CO_SO |
| POST | /admin/bookings/confirm | admin\AdminBookingController::confirm | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| POST | /admin/bookings/reconcile | admin\AdminBookingController::reconcile | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |
| POST | /admin/bookings/cancel | admin\AdminBookingController::cancel | QUAN_TRI_TAP_DOAN, QUAN_LY_CONG_TY, QUAN_LY_CO_SO, NHAN_VIEN |

## Phụ lục B. Phạm vi sửa file của chính lượt audit

Chỉ tạo báo cáo này và artifact được phép. Không tạo/sửa test source; các harness mở rộng chạy bằng stdin/bộ nhớ. Các dirty file ứng dụng từ đầu lượt được bảo toàn. Những log command với lỗi cú pháp harness hoặc thiếu file README không được tính là lỗi ứng dụng hay thêm FAIL sản phẩm. Không dọn database/upload không rõ ownership.

