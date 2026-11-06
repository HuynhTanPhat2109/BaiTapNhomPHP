# STRYKE — Sửa lỗi 500 khi thêm công ty nhượng quyền

Ngày: **06/11/2026**, Asia/Ho_Chi_Minh (+07:00).
HEAD khi bắt đầu: aa095a5593a737774ce633d5a96d1d09dddcfcec. Working tree lúc bắt đầu **sạch**.

## 1. Kết quả

Đã sửa lỗi trùng mã số thuế làm POST thêm/sửa công ty trả HTTP 500, đồng thời chặn gửi lại cùng form tạo công ty, kể cả khi không nhập mã số thuế.

- Thêm hợp lệ: HTTP **303**, dữ liệu lưu đúng trên database test.
- Mã số thuế đã được sử dụng: HTTP **422**, thông báo tại trường ma_so_thue, giữ lại nội dung form.
- Gửi lại form đã tạo thành công: HTTP **303** về danh sách, không INSERT lần nữa.
- Form thiếu/giả mạo mã yêu cầu tạo: **422**, không ghi dữ liệu.
- Không thay đổi schema, database ứng dụng, phân quyền, routes, CSS/JS hoặc bố cục hiển thị.
- Kiểm tra cuối: **186 PASS / 0 FAIL / 0 BLOCKED** trong các suite đã chạy hợp lệ; lint **4/4 PASS**, git diff --check đạt.

Đây là kết quả trong phạm vi công ty/hợp đồng và test liên quan; không phải chứng nhận full-system/browser regression.

## 2. Bằng chứng và nguyên nhân

### 2.1. Log ứng dụng thật

Đọc C:/xampp/apache/logs/error.log, thấy:

- 06/11/2026 20:19:10: Application failure: PDOException at CongTyNhuongQuyen.php:56.
- 20:20:03 và 20:21:52: cùng vị trí, referer /admin/companies/create.

Đây là dòng PDOStatement::execute() của INSERT. Global handler ở public/index.php cố ý không ghi SQL, tham số request hoặc stack nhạy cảm; nó trả trang 500 khi PDOException không được bắt.

**Giới hạn bằng chứng lịch sử:** log không lưu nội dung ba POST nói trên nên không thể truy ngược mã số thuế cụ thể hoặc khẳng định cả ba request có dữ liệu giống nhau. Không đọc/lộ thông tin thuế của công ty thật để suy đoán. Nguyên nhân bên dưới được xác minh bằng source, schema và tái hiện cùng luồng HTTP.

### 2.2. Schema thực tế — chỉ đọc

Đã chạy SHOW CREATE TABLE trên database ứng dụng trong transaction READ ONLY, rồi rollback. Bảng cong_ty_nhuong_quyen thực tế khớp schema source:

- ma_cong_ty: INT AUTO_INCREMENT PRIMARY KEY.
- ten_cong_ty VARCHAR(200) NOT NULL, dia_chi VARCHAR(255) NOT NULL.
- ma_so_thue VARCHAR(30) NULL, **UNIQUE KEY ma_so_thue**.
- Các trường người đại diện/điện thoại/email/ngày tham gia cho phép NULL.
- trang_thai ENUM HOAT_DONG/NGUNG_HOAT_DONG, mặc định HOAT_DONG.
- Engine InnoDB, utf8mb4_unicode_ci; MariaDB 10.4.32.

INSERT hiện có dùng đúng cột, kiểu và số placeholder; không thiếu migration. Bảng công ty không có FK đầu vào làm lệnh INSERT phụ thuộc công ty/hợp đồng khác. Hợp đồng, cơ sở, nhân viên tham chiếu công ty theo FK hiện có.

### 2.3. Tái hiện trên database test riêng

Luồng thật: đăng nhập ROOT → GET /admin/companies/create → lấy CSRF → POST /admin/companies.

Trước sửa:
1. POST mới với dữ liệu hợp lệ trả 303, lưu thành công.
2. POST lại mã số thuế đó, hoặc gửi lại form thành công: **500**.
3. Gọi CompanyService::create() trực tiếp trên cùng DB test cho stack trace:

    SQLSTATE[23000]: Integrity constraint violation: 1062
    Duplicate entry 'TEST-COMPANY-001' for key 'ma_so_thue'
    PDOStatement::execute — app/models/CongTyNhuongQuyen.php:56
    CongTyNhuongQuyen::create — app/services/CompanyService.php:95 (trước sửa)

CompanyService chỉ validate kiểu/độ dài/email/ngày/trạng thái, sau đó INSERT. Chưa chuyển lỗi UNIQUE thành ValidationException. Controller::store() chỉ bắt ValidationException; PDOException đi ra global handler → 500.

Sửa công ty sang mã số thuế của công ty khác cũng tái hiện cùng lỗi ở UPDATE.

Ngoài ra, với mã số thuế để trống, normalize chuyển thành NULL. MySQL UNIQUE cho phép nhiều NULL, nên gửi lại form tạo thêm bản ghi thay vì báo trùng. Không thể dùng duy nhất UNIQUE ma_so_thue để chống gửi lại mọi form.

### 2.4. Liên quan merge?

Không có thay đổi chưa commit khi bắt đầu. Không có bằng chứng xung đột merge hoặc schema runtime lệch INSERT. Lỗi là thiếu xử lý ràng buộc UNIQUE trong service và chưa có mã nhận diện lần gửi form. Không quy kết một merge cụ thể nếu không có bằng chứng.

## 3. File đã sửa/thêm

| File | Thay đổi |
|---|---|
| app/services/CompanyService.php:90,107 | create()/update() bắt đúng MySQL duplicate key 1062, đổi thành ValidationException tại ma_so_thue; các PDOException khác vẫn ném tiếp |
| app/controllers/CompanyController.php:63,71,175 | create() cấp mã form; store() kiểm và ghi nhớ kết quả theo mã; issueCreateToken() quản lý tối đa 20 form trong session |
| app/views/admin/companies/create.php:19 | Thêm đúng một hidden input company_create_token; không thay CSS, chữ, bố cục, action/method hoặc CSRF |
| tests/company-create-integration.php | Suite HTTP + MySQL opt-in, tự tạo/dọn DB test riêng, kiểm validation, replay, quyền và các liên kết |
| reports/STRYKE_COMPANY_CREATE_500_FIX_REPORT.md | Báo cáo này |

Model CongTyNhuongQuyen **không sửa**: SQL đúng; UNIQUE tiếp tục là chốt bảo vệ cuối cùng khi nhiều request có cùng mã số thuế. Không thêm SELECT-then-INSERT có race condition và không đổi khóa UNIQUE.

Đã so SHA-256 source trước/sau với storage/temp/company-fix-baseline.json: đúng ba file ứng dụng phía trên thay đổi; ngoài ra chỉ có test mới, báo cáo và artifact tạm. Không chạm booking, payment, scheduler, hóa đơn, tồn kho, Dashboard hoặc các module khác.

## 4. Cách chống gửi lại form

- Mỗi GET form tạo nhận token ngẫu nhiên 24 byte từ server, lưu trong session và hidden input.
- Token vẫn đang chờ nếu validation thất bại; khách sửa dữ liệu rồi gửi lại được.
- Sau INSERT thành công, token được gắn với ID công ty đã tạo.
- POST lặp với token đã hoàn tất chỉ redirect về danh sách; không gọi service tạo lần nữa.
- Nhiều tab có token riêng; tối đa 20 token gần nhất để session không tăng vô hạn.
- PHP session file lock hiện có tuần tự hóa request cùng session; không thay cơ chế session/auth/CSRF.
- Form quá cũ, mất session, thiếu/giả token trả validation 422 và được cấp token mới để thử lại.

Phạm vi này là chống gửi lại **cùng form/session**, không biến tên công ty/địa chỉ/email thành khóa UNIQUE mới. Một form mới hợp lệ có thể tạo công ty khác để trống MST theo schema hiện tại. Không tự suy ra hai công ty có cùng tên luôn là cùng một pháp nhân.

**Giới hạn:** token session không phải idempotency record bền vững trong DB. Nếu process chết chính xác sau INSERT nhưng trước lưu kết quả vào session, form không có MST vẫn có cửa sổ lặp. Loại bỏ cửa sổ sự cố đó cần thiết kế idempotency bền vững/schema riêng, chưa thuộc sửa tối thiểu này. Với MST không rỗng, UNIQUE vẫn bảo vệ ngay cả giữa các session/request chạy đồng thời.

## 5. Kiểm thử và artifact

### Kết quả cuối

| Suite/check | Kết quả | Môi trường |
|---|---:|---|
| php tests/company-create-integration.php --run | **44 PASS / 0 FAIL** | MySQL/MariaDB test ngẫu nhiên + PHP HTTP server riêng + đăng nhập/CSRF thật |
| php tests/admin-management-unit.php | **142 PASS / 0 FAIL** | SQLite :memory:, pagination/scope/money, công ty/hợp đồng và danh sách quản trị hiện có |
| Lint CompanyController, CompanyService, create view, test mới | **4/4 PASS** | PHP CLI |
| git diff --check | **PASS** | Repository |
| Browser trực quan, thiết bị mobile, crash process, tải đồng thời lớn | **NOT RUN** | Không tuyên bố đã E2E browser/stress |

**Tổng assertion:** 44 + 142 = **186 PASS / 0 FAIL / 0 BLOCKED**. Không cộng lint hoặc các lượt chạy lặp vào số assertion.

### Các ca đã chạy

- ROOT xem form, tạo mới, 303 và đọc lại dữ liệu đã lưu.
- Trùng MST: form mới, gửi lại form cũ, service trực tiếp; không còn 500.
- Sửa giữ MST của chính công ty thành công; đổi sang MST công ty khác trả 422 và giữ row cũ.
- Dữ liệu thiếu tên/địa chỉ, email/ngày/trạng thái sai, array input, vượt chiều dài: 422, không ghi dữ liệu.
- Trường tùy chọn rỗng giữ NULL, không bị bắt buộc thêm ngoài schema.
- Gửi lại form NULL MST không tạo thêm công ty.
- Validation thất bại rồi sửa lại thành công.
- Token thiếu/giả bị chặn; hai tab độc lập và replay không INSERT lại.
- Trim/collation của MST vẫn được DB UNIQUE áp dụng.
- Test-only trigger SIGNAL trong DB test xác minh lỗi DB không phải 1062 không bị dịch sai thành lỗi trùng MST; trigger bị dọn ngay trong DB test.
- Danh sách công ty, màn hình thông tin/sửa công ty, danh sách/sửa hợp đồng vẫn render.
- Tạo hợp đồng đúng FK; liên kết cơ sở và tài khoản quản lý đúng công ty.
- Tài khoản quản lý công ty đăng nhập và xem thông tin công ty tại /company/profile.
- Ngừng hoạt động công ty thành công; không xóa/sửa liên kết hợp đồng/cơ sở.
- QUAN_LY_CONG_TY và KHACH_HANG bị 403 khi GET/POST tạo công ty; POST sử dụng CSRF hợp lệ để kiểm quyền thật.
- ROOT thiếu CSRF vẫn bị 403; không thay core CSRF.

Repository không có route xem chi tiết công ty độc lập /admin/companies/show; đã kiểm find()/trang thông tin-sửa và trang thông tin của quản lý công ty, không thêm route mới để phục vụ kiểm thử.

### Bằng chứng trước/sau

- storage/temp/company-create-0598f727e7/report.json: SQLSTATE/driver/stack trace trước sửa. Lượt đầu dừng ở fixture cơ sở thiếu giờ hoạt động hợp lệ; đã sửa **dữ liệu fixture**, không sửa check constraint hay code cơ sở.
- storage/temp/company-create-32b13c1798/: baseline hoàn tất, **5 assertion nghiệp vụ FAIL** do duplicate/replay và một assertion test ban đầu nhầm kỳ vọng CSRF 419.
- Kỳ vọng CSRF được sửa theo Core/Csrf.php đang trả **403**; không đổi source CSRF hoặc expectation của các lỗi duplicate/replay để lấy PASS.
- storage/temp/company-create-56be106169/: sau sửa ban đầu, 34/34.
- storage/temp/company-create-3c29fb05e3/: thêm token/fault tests, 42/42.
- storage/temp/company-create-fc04e10a7b/: lượt cuối 44/44, completed=true; DB stryke_company_test_ebb826289d đã được dọn, xác nhận trong cleanup.removed của JSON.

Mọi DB test có guard prefix stryke_company_test_, khác DB ứng dụng và chỉ dọn DB do chính lượt test tạo. SELECT cuối xác nhận không còn DB prefix test này. Không ghi dữ liệu vào DB ứng dụng, không reset tài khoản thật hoặc dọn upload thật.

## 6. Cần thao tác gì trên môi trường ứng dụng?

**Không cần migration, import SQL hoặc cấu hình database.** Schema runtime đang đúng và UNIQUE phải được giữ nguyên.

Nếu đang mở form từ trước khi triển khai, tải lại trang Thêm công ty để nhận hidden token mới; form cũ gửi lên sẽ nhận validation, không tạo lỗi 500. Với MST đã có, kiểm tra lại công ty hiện hữu thay vì đổi/xóa ràng buộc UNIQUE.

Không thử thêm dữ liệu trên database thật trong lượt sửa này. Nếu lỗi 500 còn xuất hiện với MST thực sự mới và form mới, cần đối chiếu exception/log của request đó; không suy ra mọi PDOException đều là duplicate. Lỗi DB khác được giữ để global handler xử lý an toàn.

Không commit, push, merge; không thay đổi giao diện nhìn thấy hoặc các workflow ngoài phạm vi.

