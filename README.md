Huỳnh Tấn Phát


Trần Đại Chí Chì Chi

Trần Ngọc Vũ

Nguyễn Đức Duy

## Nền MVC và xác thực

Yêu cầu môi trường phát triển: PHP 8.2 (đã kiểm tra với XAMPP 8.2.12),
PDO MySQL, Composer và Apache. Không dùng framework, không thêm dependency.

### Chạy trên XAMPP

1. Chạy `composer install` theo lock file; nếu đã có vendor, chạy
   `composer dump-autoload`. Không cần `composer update`.
2. Tạo `.env` từ `.env.example` nếu chưa có, điền thông tin database cục bộ.
   Không commit `.env`. Thay đổi này không tự sửa `.env` hiện tại.
3. Database phải được người quản trị chuẩn bị bằng schema hiện có.
   Ứng dụng không tự import, migrate hoặc seed. Cần đủ năm vai trò trong SQL.
4. Bật Apache/MySQL, mở
   `http://localhost/BaiTapNhomPHP/public/`.
5. Form/link dùng `index.php?route=/login` nên không phụ thuộc URL rewrite.
   `.htaccess` hỗ trợ thêm `/login`, `/register`, v.v.
6. Apache phải cho phép `.htaccess` và `mod_rewrite`. File `.htaccess` ở gốc
   chặn truy cập HTTP ngoài `public/`, bao gồm `.env`, database, vendor.
   Kiểm tra URL `/BaiTapNhomPHP/.env` trả 403. Nếu không dùng Apache,
   cấu hình DocumentRoot trực tiếp vào `public/` và chặn đọc file ẩn.
7. `APP_BASE_PATH` là tùy chọn, mặc định lấy thư mục entry point.
   Chỉ đặt khi cần: `/BaiTapNhomPHP/public`, hoặc chuỗi rỗng với virtual host.
   PHP dùng `Asia/Ho_Chi_Minh`, kết nối DB dùng `+07:00`.

### Kiến trúc

`public/index.php → Router → CSRF/middleware → Controller → Service → Model/PDO → View/layout`

- `Core\Application` tạo PDO khi cần và dùng chung trong request.
- `Core\Database` tái sử dụng cấu hình cũ, bổ sung native prepared statements,
  fetch associative và timezone.
- `Router` hỗ trợ GET/POST, middleware, 404/405. Route hiện tại là đường dẫn
  cố định; chưa cần route tham số `{id}` cho phạm vi auth.
- Mọi POST qua router đều phải có CSRF token, kể cả đăng nhập/đăng ký/đăng xuất.
- View nhận đường dẫn do controller định nghĩa, dữ liệu HTML dùng helper `e()`.
- `AuthMiddleware` yêu cầu đăng nhập. `RoleMiddleware` đối chiếu `ten_vai_tro`,
  không hard-code ID và không mặc định quản trị được vượt mọi allowlist.
- Các trang `/admin`, `/company`, `/facility`, `/staff`, `/customer` chỉ là
  landing page kiểm chứng quyền; chưa có nghiệp vụ của các module này.
- 403, 404 và 405 dùng chung error view/layout; lỗi hệ thống trả 500 chung,
  log loại lỗi và vị trí, không xuất SQL/mật khẩu/DSN ra giao diện.

### Quy tắc tài khoản

- Đăng ký công khai chỉ tạo `KHACH_HANG`, tạo tài khoản và hồ sơ trong một
  transaction. Không tự đăng nhập sau đăng ký.
- Tên đăng nhập: 6–15 ký tự ASCII (chữ, số, `.`, `_`, `-`); kiểm tra trùng
  bằng SQL để tuân theo collation database. UNIQUE index xử lý cả tranh chấp.
- Email tùy chọn, kiểm tra định dạng và tối đa 150 byte. Schema không UNIQUE
  email nên cho phép email trùng, không dùng email để đăng nhập hoặc khôi phục.
  Muốn email duy nhất cần thảo luận schema, không chỉ thêm SELECT kiểm tra.
- Điện thoại: bắt buộc đúng 10 chữ số và bắt đầu bằng `0`, không cho phép dấu `+`, khoảng trắng bên trong hoặc ký tự khác.
- Mật khẩu mới: 8–16 ký tự Unicode, có ít nhất một chữ hoa, một chữ thường,
  một số `0–9` và một ký tự đặc biệt (dấu câu hoặc ký hiệu; khoảng trắng không
  tính là ký tự đặc biệt). Không nhận ký tự điều khiển, không trim,
  không đưa lại vào view/log. Backend kiểm tra độ dài theo ký tự, không theo byte.
  Đăng nhập vẫn xác minh hash hiện có, không áp quy tắc tạo mới lên tài khoản cũ.
  Dùng `password_hash(PASSWORD_DEFAULT)` và `password_verify`.
- Session lưu ID tài khoản, tên đăng nhập để hiển thị navbar và mốc thời gian (không lưu mật khẩu/vai trò để cấp quyền); đăng nhập thành công đổi session ID và xóa
  trạng thái session cũ, tạo CSRF mới khi render form tiếp theo.
- Backend hết phiên sau 30 phút không hoạt động hoặc 8 giờ kể từ đăng nhập.
  Khi khóa tài khoản/hồ sơ, session và token cũ bị thu hồi ở request tiếp theo.
- Request được bảo vệ đọc lại trạng thái/vai trò từ DB. Tài khoản bị khóa
  mất quyền ở request tiếp theo. Khách hàng còn cần hồ sơ hoạt động.
- Nhân viên cần hồ sơ đang làm và công ty hoạt động; quản lý cơ sở/nhân viên
  cần cơ sở hoạt động, đúng công ty. Quản lý công ty được để cơ sở NULL.
  Quản trị tập đoàn không bắt buộc hồ sơ nhân viên.
- Session cookie có HttpOnly, SameSite=Lax, Secure khi chạy HTTPS.
  Đăng xuất chỉ POST+CSRF, xóa dữ liệu, cookie và hủy session.
- GET `/account` hiển thị hồ sơ; khách hàng cập nhật họ tên, điện thoại, email,
  ngày sinh và giới tính bằng POST `/account` (chỉ vai trò `KHACH_HANG`).
  Tên đăng nhập, vai trò và ngày tạo chỉ đọc. Email vẫn có thể trùng theo schema.
- Mục Cài đặt trong dropdown dẫn đến trang riêng `/account/settings`. POST
  `/account/password` đổi mật khẩu sau khi xác minh mật khẩu hiện tại, áp cùng
  chính sách mật khẩu mới, đổi session ID và CSRF token. Mọi vai trò đã đăng nhập
  dùng được; form không điền lại mật khẩu sau lỗi.
- Đăng nhập có giới hạn 20 lần thử/10 phút cho mỗi IP, tính cả lần thành công,
  lưu bộ đếm không chứa thông tin đăng nhập tại `storage/temp/login-throttle/`.
  `flock` bảo đảm tăng bộ đếm nguyên tử; tạo session mới không vượt qua được giới hạn.
  Khi vượt giới hạn trả HTTP 429 và `Retry-After`, không đánh dấu ô mật khẩu sai.
  Không tin `X-Forwarded-For`. Nếu chạy nhiều server hoặc reverse proxy, cấu hình
  rate limit ở proxy/storage chung và lịch dọn các file bộ đếm cũ trước khi vận hành.
  Chưa có reset mật khẩu, remember-me hoặc MFA.

### AccountService dành cho module nhân viên

`AccountService::create(username, password, roleCode)` nhận cùng PDO với caller,
validate và băm mật khẩu, tra ID theo mã vai trò, trả ID tài khoản.
Service không tự begin/commit/rollback: module nhân viên kiểm tra quyền người
thực hiện và vai trò được phép cấp, rồi mở transaction tạo tài khoản + nhân viên.
Không truyền thẳng roleCode từ request công khai. Không có endpoint công khai
để tạo tài khoản quản trị/nhân viên.

Role middleware chỉ kiểm tra quyền vào route. Module sau phải lọc phạm vi
ma_cong_ty/ma_co_so/ma_khach_hang từ identity phía server và kiểm tra từng
tài nguyên, không tin ID phạm vi do client gửi.

### Kiểm thử

- `php tests/run.php`: kiểm tra validation, router, CSRF, hash/verify, session,
  quyền, transaction/rollback bằng PDO giả lập; không kết nối database thật.
- `powershell -ExecutionPolicy Bypass -File tests/http-smoke.ps1`: kiểm tra HTTP
  qua Apache đang chạy, không tạo tài khoản; cần DB có schema để thử login sai.
  Thêm `-SkipDatabase` để chỉ kiểm tra HTTP không phụ thuộc database.
- Các test giả lập không thay thế kiểm thử tích hợp MySQL và đăng nhập trình duyệt.
- `php tests/auth-integration.php --run`: chạy luồng HTTP thật với DB đang cấu hình,
  tạo một tài khoản tên ngẫu nhiên `pr2_...`, kiểm tra đăng ký/login/logout/role/CSRF,
  cập nhật hồ sơ, đổi mật khẩu và session,
  khóa và thay vai trò CHỈ tài khoản này, cuối cùng xóa tài khoản và hồ sơ fixture.
  Không import hoặc sửa schema. Có thể đặt `AUTH_TEST_URL` nếu URL khác mặc định.
  Chạy trên DB phát triển; nếu kiểm thử bị dừng đột ngột, kiểm tra fixture còn lại.

Checklist thủ công sau khi chuẩn bị database:

- [ ] Trang chủ, login/register và CSS hoạt động ở URL public.
- [ ] URL không tồn tại trả 404; GET logout trả 405.
- [ ] Chưa đăng nhập vào account/admin chuyển đến login.
- [ ] POST thiếu/sai CSRF trả 403, không tạo dữ liệu.
- [ ] Đăng ký hợp lệ tạo một tài khoản + một hồ sơ khách hàng.
- [ ] DB lưu hash, không lưu mật khẩu thô; vai trò luôn KHACH_HANG dù sửa POST.
- [ ] Tên trùng (kể cả khác hoa/thường theo collation) bị từ chối; hai request
      cùng tên không tạo hai tài khoản; email trùng được phép theo schema.
- [ ] Email/điện thoại sai, username ngoài 6–15 ký tự, password ngoài 8–16 ký tự,
      thiếu hoa/thường/số/ký tự đặc biệt hoặc nhập lại không khớp đều bị từ chối.
- [ ] Mật khẩu không được điền lại sau lỗi; input chứa HTML được escape.
- [ ] Sai username/password hoặc tài khoản khóa dùng cùng thông báo thất bại.
- [ ] Login đúng đổi session ID; cookie có HttpOnly và SameSite.
- [ ] Navbar hiện tên đăng nhập, dropdown mở/đóng bằng click, click ra ngoài hoặc Esc;
      nút và form dùng được ở màn hình điện thoại.
- [ ] Khách sửa hồ sơ và xem lại giá trị đã lưu; số điện thoại phải bắt đầu bằng 0,
      ngày sinh không ở tương lai; nhân viên không được POST sửa hồ sơ khách.
- [ ] Cài đặt đổi mật khẩu: sai mật khẩu hiện tại bị từ chối; thành công đổi
      session ID và token CSRF, mật khẩu cũ không còn đăng nhập được.
- [ ] Khách vào customer được; vào admin/company/facility/staff trả 403.
- [ ] Với tài khoản từng vai trò được chuẩn bị riêng, đúng khu vực trả 200,
      sai khu vực trả 403; thiếu hồ sơ/phạm vi hợp lệ không đăng nhập được.
- [ ] Khóa tài khoản/hồ sơ hoặc đổi vai trò từ công cụ quản trị, refresh trang
      được bảo vệ có hiệu lực ngay (không dựa vai trò cũ trong session).
- [ ] Logout xóa session; trang bảo vệ không dùng lại được; token cũ bị từ chối.
- [ ] Khi DB không khả dụng: trang công khai vẫn hiển thị, request cần DB trả
      lỗi 500 chung, không lộ chi tiết kết nối.
- [ ] HTTP tới `.env`, SQL và mã nguồn ngoài public bị chặn.

Không có tài khoản quản trị mẫu hoặc mật khẩu mặc định trong mã nguồn.

## Giao diện STRYKE

STRYKE là tên công ty/thương hiệu của hệ thống sân thể thao đa bộ môn.
Phạm vi không giới hạn ở bóng đá, tennis, pickleball hoặc cầu lông: các môn có
nhu cầu đặt sân đều thuộc định hướng hệ thống. Danh sách môn trên homepage
chỉ là ví dụ; ảnh tennis là ảnh minh họa, không giới hạn nghiệp vụ.

### Nền ảnh Login/Register

Login và Register dùng layout riêng `app/views/layouts/auth.php`, không có navbar
hoặc footer từ `main.php`. Các trang khác vẫn dùng layout chính.

Để thay ảnh auth, ghi đè **`public/assets/images/auth-background.jpg`** bằng ảnh
thể thao của bạn (khuyên dùng ảnh ngang từ 1920×1080), rồi Ctrl+F5 trình duyệt.
Ảnh mặc định là bản sao ảnh tennis có giấy phép ghi trong `CREDITS.md`.
Ảnh homepage không bị thay theo.

Nếu dùng `.webp` hoặc `.png`, đổi đường dẫn biến `--auth-background` ở đầu
`public/assets/css/auth.css`. File CSS này chỉ được nạp trên Login/Register.
Độ mờ kính chỉnh ở `backdrop-filter: blur(4px)`; độ tối của form chỉnh ở
`background: rgba(3, 14, 8, .64)` trong `.auth-standalone .auth-panel`.
Có nền tối dự phòng khi ảnh hoặc backdrop-filter không hoạt động.

- Theme charcoal / xanh sân thể thao, ảnh tennis local; không tải font hoặc ảnh
  từ bên thứ ba khi người dùng mở trang.
- Ảnh: `public/assets/images/tennis-court.jpg`. Nguồn và giấy phép trong
  `public/assets/images/CREDITS.md`. Có nền màu dự phòng khi thiếu ảnh.
- CSS: `public/assets/css/app.css`; JavaScript thuần: `public/assets/js/app.js`.
- Auth dùng chung visual tại `app/views/components/auth-visual.php`.
- Link Login/Register vẫn chuyển URL qua GET bình thường. JS bổ sung fade/translate
  khi rời trang và vào trang, không fetch form, không lưu mật khẩu, không chặn submit.
  POST có lỗi không chạy animation; `prefers-reduced-motion` tắt chuyển động.
- Không có JS: navigation, link, CSRF và form PHP vẫn hoạt động; nút hiện mật khẩu
  được ẩn vì không có hành vi khi thiếu JS.
- Trang 500 dùng cùng error view, có fallback HTML nếu view/config bị lỗi.
- Response HTML có CSP chỉ cho phép script/style/ảnh local, cấm nhúng iframe
  và form gửi sang origin khác. Module sau dùng JS/CSS file riêng; muốn thêm
  CDN hoặc inline script phải review CSP thay vì tự bỏ chính sách.

Kiểm tra giao diện trên XAMPP:

1. Mở `/BaiTapNhomPHP/public/`, login và register; nhấn Ctrl+F5 nếu còn CSS cũ.
2. DevTools Responsive: 375px, 768px, 1440px. Kiểm tra không tràn ngang, menu mobile,
   form đăng ký dài và toàn bộ thông báo lỗi.
3. Chuyển login/register bằng tab và link cuối form, thử Back/Forward.
4. Bật Reduce motion; tắt JavaScript; link và form vẫn hoạt động.
5. Gửi form rỗng/sai định dạng; lỗi đỏ dưới trường, dữ liệu cũ vẫn được escape,
   mật khẩu không được điền lại. Sai thông tin login không tô đỏ ô mật khẩu.
6. Với tài khoản hợp lệ đã có, kiểm tra login/account/logout. Không có booking giả.

Kiểm thử trình duyệt tùy chọn: `node tests/ui-smoke.cjs`. Cần Playwright được cài
ở môi trường kiểm thử; không thêm package vào project PHP. Có thể đặt biến
`PLAYWRIGHT_PATH` đến package `playwright-core` đã có và `UI_BASE_URL` nếu URL
khác mặc định. Script dùng Chromium headless, chụp vào `storage/temp/ui-review/`,
kiểm tra responsive, JS tắt, reduced motion và ảnh lỗi. Account/role/error được
render bằng dữ liệu mẫu trong bộ nhớ, không tạo tài khoản hoặc endpoint thử nghiệm.
