# STRYKE — xác nhận thủ công qua minh chứng, 05/11/2026

## Luồng hiện tại

Không cần SePay/Casso/API ngân hàng. Modal xác nhận cũ đã được sửa trực tiếp thành hai card:
thông tin booking và ảnh minh chứng. Không có card/trạng thái kết nối ngân hàng, input tiền,
mã giao dịch, giờ chuyển khoản hoặc checkbox trong bước xác nhận thông thường.

Khách xem QR trước, chọn JPG/PNG/WebP tối đa5 MB, xem trước/thay ảnh trên máy rồi gửi yêu cầu.
Sau POST hợp lệ: lưu đường dẫn và thời gian gửi trên đúng payment, hiển thị **Đã gửi minh chứng –
Chờ xác nhận thanh toán**. Upload không xác nhận đã thu tiền, không xuất tồn và không gia hạn6 phút.
Booking mới giữ nguyên CHO_THANH_TOAN để cơ chế giữ ảo/expiry hiện có tiếp tục hoạt động.
Trạng thái CHO_XAC_NHAN của dữ liệu cũ cũng giữ sân/tồn ảo trong hạn gốc, hết hạn thì không giữ nữa.

Nhân viên/quản lý/Admin mở cùng modal từ danh sách hoặc trang chi tiết, xem ảnh/phóng to,
đối chiếu tài khoản nhận tiền và bấm xác nhận cuối: **Bạn xác nhận đã kiểm tra tài khoản nhận tiền
và nhận đủ số tiền của booking này?** Có Quay lại/Xác nhận; không thêm modal thanh toán thứ hai.
Viewer ảnh là dialog riêng chỉ dùng xem/phóng ảnh, đóng lại giữ nguyên modal đang xử lý.

Ảnh chỉ là thông tin khách cung cấp. Không OCR số tiền, không xác minh ngân hàng tự động,
không chứng minh khoản chuyển đã thực sự đến tài khoản. Người thao tác chịu trách nhiệm kiểm tra
tiền thực nhận/tham chiếu/tài khoản; dữ liệu ảnh không được dùng làm bằng chứng ngân hàng tự xác thực.

## Migration cần áp dụng trước khi dùng trên database cũ

**Migration chưa được chạy trên database ứng dụng.** Không xác nhận giao dịch thật để thử UI.
Sau khi review và sao lưu, chọn đúng database STRYKE trong phpMyAdmin rồi Import đúng một lần:
`database/migrations/20261105_manual_receipt_confirmation.sql`. Không import lại schema chính,
không chạy lại migration nếu cột/constraint đã tồn tại. Cài mới đã có trong schema chính.

Lý do cần migration: CHECK cũ bắt buộc mã giao dịch và giờ chuyển khoản cho mọi payment thành công.
Không thể đáp ứng quy trình mới bằng cách tự sinh mã hoặc dùng giờ xác nhận làm giờ ngân hàng.
Migration thêm4 cột nullable: `anh_minh_chung`, `minh_chung_gui_luc`,
`ma_tai_khoan_xac_nhan`, `xac_nhan_thu_cong_luc`; FK người xác nhận đến tài khoản và cập nhật
CHECK để chấp nhận nhánh xác nhận thủ công có đủ minh chứng/người/thời gian riêng.

Một ALTER được chuẩn bị với cú pháp DROP CONSTRAINT cho MariaDB hoặc DROP CHECK cho MySQL
(theo tài liệu [MariaDB](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table)
và [MySQL](https://dev.mysql.com/doc/refman/8.0/en/alter-table.html));
không có UPDATE/DELETE/INSERT hay backfill dữ liệu cũ. Nếu dữ liệu thành công cũ đã vi phạm
CHECK, dừng kiểm tra dữ liệu thay vì tạo thông tin giả để ép migration qua. Chưa kiểm thử DDL
trên MySQL/MariaDB thật; biểu thức CHECK chính xác từ schema đã được thực thi trong SQLite RAM.
Trước migration, trang vẫn đọc được ảnh cũ liên kết trong ghi chú; POST mới bị chặn với thông báo
cần nâng cấp schema, không bị khóa vì thiếu API ngân hàng.

## Backend, lịch sử và tồn

- Route POST hiện tại `/admin/bookings/confirm` giữ nguyên middleware4 vai trò nội bộ và CSRF.
  Luồng thường chỉ đọc ID/version ảnh, lấy payment/booking/tổng tiền từ server, kiểm tra lại scope,
  tài khoản hoạt động, ảnh liên kết thật, trạng thái chưa xử lý và hạn gốc dưới khóa.
- Version ảnh gắn với đường dẫn/thời gian gửi. Khách thay ảnh trong lúc nhân viên đang review thì
  POST cũ bị từ chối; cần tải lại và xem ảnh mới. Không dùng hidden input làm bằng chứng ngân hàng.
- Một transaction/cùng PDO: khóa cha/dịch vụ → booking/payment, gọi BookingInventoryService đúng
  một lần, UPDATE payment, chuyển pending còn hạn sang DA_XAC_NHAN đúng1 dòng, lập hóa đơn, commit.
  Bất kỳ lỗi nào rollback toàn bộ, kể cả tồn đã cập nhật nhưng lỗi hóa đơn/deadline.
- Lưu số đã thu bằng số phải thu như **lời xác nhận đã nhận đủ của người thao tác**; không phải dữ liệu
  API hoặc số tiền suy ra từ ảnh. Không tạo/điền `ma_giao_dich` hay `ngay_thanh_toan` trong luồng thường.
  Hai cột đó giữ nguyên NULL nếu chưa có dữ liệu thực tế. Người/giờ xác nhận lưu riêng và hiện trong chi tiết.
- Dashboard ghi nhận doanh thu theo ngày thu ngân hàng nếu đã có, hoặc ngày xác nhận thủ công;
  cột hiển thị là Ngày ghi nhận thu. Không ghi ngược giờ xác nhận vào trường giờ ngân hàng.
- Tồn NULL không theo dõi;0/thiếu tồn từ chối; snapshot không sửa. Pending không trừ vật lý;
  khi xác nhận, trừ vật lý và rời giữ ảo cùng commit. Retry sau thành công không gọi hook lần hai.
  Hook tự nó vẫn không độc lập idempotent; không gọi bù cho booking cũ đã xác nhận.
- Payment CAN_DOI_SOAT hoặc đã ghi nhận sai tiền không dùng nút xác nhận thường để bỏ qua ngoại lệ.
  Xem đối soát giữ quy trình ghi nhận/giải quyết ngoại lệ với thông tin thực tế; checkbox đã bỏ.
  Nhánh giải quyết CAN_DOI_SOAT cần ảnh liên kết, scope/hạn hợp lệ, tiền khớp, dữ liệu ngân hàng
  thực nhập hợp lệ và xác nhận cuối. Đối soát chuyển muộn không phục hồi booking hoặc tự hoàn tiền.
- Các API ngân hàng chuẩn bị trước đây vẫn được giữ nhưng không nối vào luồng UI thường.
  Không có webhook/nguồn ngân hàng giả được bật để làm nút hoạt động.

## Bảo vệ ảnh

- File mới nằm tại `storage/receipts`, ngoài public; tên ngẫu nhiên do server tạo, không dùng tên upload.
  Kiểm tra native HTTP upload, kích thước thực, finfo MIME, getimagesize, giới hạn chiều/pixel ảnh.
  Chỉ JPEG/PNG/WebP; không cho SVG/HTML/PHP giả MIME hay đường dẫn tùy ý. Máy hiện có fileinfo,
  không có GD, nên không tuyên bố đã re-encode hoặc xóa EXIF; file chỉ được phục vụ như ảnh, không thực thi.
- GET `/booking/receipt?id=...` kiểm tra tài khoản và chủ booking hoặc phạm vi công ty/cơ sở trước
  khi đọc ảnh; trả MIME thật, nosniff, no-store và CSP sandbox. Không nhận đường dẫn file từ client.
- `.htaccess` chặn đường dẫn ảnh public cũ. Đã kiểm tra HEAD trên Apache local:403.
  Root `.htaccess` hiện có chặn truy cập storage qua web. Khi triển khai khác Apache phải giữ
  DocumentRoot public hoặc cấu hình deny tương đương; không expose storage/uploads như static.
- Ảnh cũ chỉ đọc khi được liên kết rõ qua ghi chú và đúng ID booking; không quét glob lấy file mồ côi.
  Không tự di chuyển/xóa ảnh cũ. Ảnh cũ thiếu thời gian gửi hiển thị chưa ghi nhận, không đoán từ mtime.
- .gitignore loại ảnh thực và ảnh riêng, giữ .htaccess. File lưu mới được dọn nếu transaction upload lỗi;
  ảnh đã lưu trước đó không tự xóa khi thay ảnh. Cần sao lưu cả database và thư mục ảnh.

## Kiểm thử

Chỉ chạy PHP/PDO giả hoặc SQLite `:memory:` và HTTP fixture localhost; file ảnh TEST FIXTURE nằm
trong `storage/temp`. Không tạo database test MySQL, không ghi/đọc giao dịch thật, không chạy migration
trên database đang dùng. PHP kiểm thử cho phép upload40M; ứng dụng vẫn giới hạn5 MB.

`tests/receipt-confirmation-unit.php`:83 checks về ảnh liên kết, chủ/phạm vi5 vai trò, CHECK thật,
metadata riêng, giữ ảo, NULL/0, retry, stale ảnh, deadline, đối soát và rollback.
Chrome dùng test gốc `booking-polish-browser.cjs`, cập nhật assertions theo luồng mới, giữ kiểm tra
route/CSRF/scope/hành động/tồn và password toggle trước đó; bổ sung upload multipart thật, MIME,
JPG/PNG/WebP, xem ảnh lớn, toàn luồng tạo draft/booking/QR/gửi ảnh/xác nhận tại1440/1024/768/375px.

Các kết quả cuối, danh sách file và diff riêng lưu tại `storage/temp/receipt-confirmation-review/`.
Ảnh tại `storage/temp/booking-polish-review/receipt-modal-*.png`, `receipt-customer-*.png`.
Test UI chờ font/ảnh decode trước khi đo, không dùng CSS che tràn. Test operations gốc cập nhật
kiểm tra nút mở modal thay form inline cũ, vẫn kiểm tra POST/CSRF/ID. Test lịch sân cập nhật giữ
CHO_XAC_NHAN còn hạn, thêm kiểm tra hết hạn phải trống. Không bỏ test/assertion bảo vệ.

Chưa chạy: DDL và khóa/race trên hai kết nối MySQL độc lập, giao dịch thật, upload/xác nhận trên
database ứng dụng. Không tuyên bố kiểm chứng chống race end-to-end bằng fixture SQLite.

## Kiểm tra thủ công sau migration

1. Khách đặt sân, thấy QR trước khi chọn ảnh; chọn/thay ảnh rồi gửi trong hạn giữ gốc.
2. Mở lại trang chờ: thấy Đã gửi minh chứng, chưa thành công; xem lại ảnh của chính booking.
3. Admin/Nhân viên đúng cơ sở → Booking → ba chấm → Xác nhận: hai card, ảnh đúng, xem ảnh lớn,
   không có card API/input tay/checkbox. Chưa có ảnh thì không được xác nhận.
4. Kiểm tra tài khoản nhận tiền thật rồi mới bấm xác nhận cuối. Nếu không khớp, dùng Xem đối soát.
5. Khi có một giao dịch thật đủ điều kiện, kiểm tra người/giờ xác nhận riêng, trạng thái, hóa đơn,
   tồn trừ1 lần; các trường ngân hàng không bị điền giả. Không xác nhận giao dịch giả để kiểm thử.
6. Booking hủy/hết hạn không được phục hồi dù có ảnh; người khác công ty/cơ sở/khách khác không đọc ảnh.

Không commit, push, pull, merge hoặc reset. Các thay đổi khác chưa commit vẫn giữ nguyên.

## Kết quả cuối và danh sách file

- Chrome, 4 suite: **2.046 PASS / 0 FAIL** (booking-polish: 587; operations-polish: 1.325; booking-pr6-browser: 103; booking-review: 31).
- PHP, 25 file: **24 PASS / 1 FAIL**. `admin-list-unit.php:206` vẫn FAIL do action Bộ môn mất ID/ngữ cảnh danh sách; lỗi có sẵn, không sửa hoặc vô hiệu assertion. Các assertions sau điểm dừng này chưa chạy.
- Node payment-state: **7 PASS**. Đã sửa điều kiện UI chỉ hiện thành công theo trạng thái booking, không suy diễn từ payment. Test gốc được giữ nguyên.
- PHP lint: **32 file PASS**; JS syntax: **7 file PASS**; `git diff --check`: **PASS**.
- HEAD đường dẫn ảnh public cũ trên Apache local: **403**. Không xác nhận giao dịch thật, không thay đổi database ứng dụng.

Lượt này thay đổi **40 file**, gồm ứng dụng, migration, kiểm thử, tài liệu và chính sách lưu ảnh.
Diff riêng so với trạng thái trước lượt này: `storage/temp/receipt-confirmation-review/task.diff`.

- `.gitignore`
- `app/controllers/BookingController.php`
- `app/controllers/admin/AdminBookingController.php`
- `app/models/AdminBooking.php`
- `app/models/Booking.php`
- `app/models/Dashboard.php`
- `app/models/HoaDon.php`
- `app/models/ThanhToan.php`
- `app/services/BookingManagementService.php`
- `app/services/CourtScheduleService.php`
- `app/services/ReceiptImage.php`
- `app/views/admin/bookings/action-dialog.php`
- `app/views/admin/bookings/index.php`
- `app/views/admin/bookings/show.php`
- `app/views/admin/invoices/show.php`
- `app/views/admin/partials/revenue-tables.php`
- `app/views/courts/booking-wait.php`
- `database/migrations/README.md`
- `database/quanly_chuoi_san_the_thao.sql`
- `docs/booking-inventory-contract.md`
- `docs/booking-payment-contract.md`
- `public/assets/css/admin-catalog.css`
- `public/assets/js/admin.js`
- `public/assets/js/booking-actions.js`
- `public/assets/js/booking.js`
- `public/assets/js/vietqr.js`
- `routes/courts.php`
- `tests/booking-inventory-unit.php`
- `tests/courts-unit.php`
- `tests/fixtures/booking-pr6.php`
- `tests/operations-polish-browser.cjs`
- `app/services/BookingReceiptService.php`
- `app/views/admin/bookings/receipt-viewer.php`
- `database/migrations/20261105_manual_receipt_confirmation.sql`
- `docs/payment-synchronization-review.md`
- `docs/receipt-confirmation-review.md`
- `public/uploads/receipts/.htaccess`
- `tests/booking-polish-browser.cjs`
- `tests/fixtures/booking-actions.php`
- `tests/receipt-confirmation-unit.php`
