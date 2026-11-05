# STRYKE — kiểm tra và chuẩn bị đồng bộ thanh toán, 04/11/2026

**Cập nhật05/11/2026:** tài liệu dưới đây lưu khảo sát trước khi người dùng chốt xác nhận thủ công
qua ảnh. Modal chờ API đã được thay; luồng UI hiện không phụ thuộc nguồn ngân hàng. Xem
[báo cáo hiện tại](receipt-confirmation-review.md) và migration bắt buộc; không áp dụng mô tả
"modal disabled vì chưa có API" dưới đây cho bản mới.

## Kết luận từ source và cấu hình

**Chưa tự nhận giao dịch ngân hàng thật.** Không có SePay/Casso/API biến động số dư,
webhook/callback, xác minh chữ ký hoặc adapter ngân hàng trong source/routes/composer.
`.env` và `.env.example` hiện chỉ có DB_HOST/PORT/NAME/USER/PASS. Chỉ kiểm tra tên và sự
hiện diện của cấu hình, không xuất thông tin bí mật, không kết nối/ghi database ứng dụng.

Các phần thanh toán của Duy đã có:

- PaymentService tạo/tái sử dụng yêu cầu pending, mã tham chiếu duy nhất, kiểm tra tiền và hạn giữ.
- VietQRService lấy danh sách ngân hàng và tạo ảnh QR QuickLink. Đây không phải API lịch sử giao dịch.
- ThanhToan lưu yêu cầu/tra cứu thanh toán; PaymentController phục vụ QR và polling trạng thái,
  kiểm tra đúng khách sở hữu. Polling chỉ đọc trạng thái đã lưu, không hỏi ngân hàng.
- Tài khoản ngân hàng theo cơ sở; xem/cấu hình theo quyền hiện có; hóa đơn và quản lý booking nội bộ.
- BookingManagementService đối soát, xác nhận thủ công, hủy booking và lập hóa đơn trong transaction.

`thanh_toan` có sẵn số phải thu `so_tien`, tham chiếu, trạng thái, `so_tien_thuc_nhan`,
`ma_giao_dich` UNIQUE, `ngay_thanh_toan` và ghi chú. Không có dấu nguồn đã xác thực, inbox
webhook hay sổ sự kiện ngân hàng. Dữ liệu nhập tay/biên lai không tự thành bằng chứng ngân hàng.
Đặc biệt ngày thanh toán ở luồng CAN_DOI_SOAT có thể là lúc nhân viên ghi nhận, không phải giờ ngân hàng.
Không có snapshot ngân hàng nhận tiền trong yêu cầu QR hiện tại: tài khoản mặc định đang cấu hình
không chứng minh tài khoản đó nhận một khoản chuyển trong quá khứ.

## Phần đã triển khai

- `BankTransactionSource`: hợp đồng adapter phía server, chỉ trả giao dịch vào đã xác thực,
  đã hoàn tất, một tham chiếu duy nhất; nguồn mơ hồ/nhiều giao dịch phải để đối soát.
- `PaymentVerificationService`: mặc định không có adapter và từ chối xác nhận. Kiểm tra
  VND/credit, số tiền chính xác đến cent, tham chiếu, mã giao dịch, ngày giờ thực tế, thời hạn,
  trạng thái booking/payment, tài khoản nhận thuộc cơ sở và đang hoạt động. Không tin cờ verified,
  tiền, mã hoặc ngày giờ từ browser. Adapter lỗi/không có dữ liệu cũng không cho xác nhận.
- Modal thường có hai card chỉ đọc: đơn/khách/cơ sở/sân/lịch/tiền phải thu/tham chiếu và giao dịch
  ngân hàng/thực nhận/mã/thời gian/trạng thái đối chiếu. Dữ liệu nhập tay cũ hiển thị tách biệt.
  Không còn input/checkbox xác minh tay trong modal thường. Nút xác nhận disabled nếu thiếu proof;
  POST vẫn kiểm tra lại nguồn server. Có Đóng/Xem đối soát/Xác nhận.
- Trang chi tiết giữ nguyên quy trình **Đối soát và xác nhận thủ công**, gồm attestation, kiểm tra
  số tiền/ngày giờ/mã ngân hàng, và ghi nhận CAN_DOI_SOAT/chuyển muộn riêng. `confirmation_mode=manual`
  chỉ chọn quy trình hiện có, không cấp thêm quyền; vẫn cần CSRF, scope, trạng thái, hạn giữ và dữ liệu hợp lệ.
- Một writer chung gọi BookingInventoryService trước khi đổi pending → DA_XAC_NHAN, thay SQL tồn
  cũ. Parent/service/booking/payment locks giữ theo thứ tự cũ. Recheck payment/booking/tham chiếu/tiền
  dưới khóa; chỉ UPDATE booking pending còn hạn đúng một dòng. Payment + tồn + booking + hóa đơn
  commit cùng PDO; bất kỳ lỗi nào rollback toàn bộ. Không gọi API khi đang giữ các khóa này.
- `CHO_THANH_TOAN` giữ tồn ảo 6 phút, không trừ vật lý; hủy/hết hạn không cộng vật lý. Xác nhận trừ snapshot
  một lần rồi booking rời pending trong cùng commit. NULL bỏ qua, 0/thiếu tồn từ chối, không sửa snapshot.
- Retry sau thành công bị từ chối trước hook. Mã giao dịch đã dùng ở payment khác bị chặn, thêm UNIQUE
  hiện có bảo vệ race. Hook riêng vẫn không độc lập idempotent, không dùng để xuất bù booking cũ đã confirmed.

Không sửa PaymentService, PaymentController, BookingService/Booking, hook tồn, routes,
schema, CSRF, middleware, checkout hoặc database thật. Không tạo tài khoản/giao dịch ngân hàng giả.

## Cách Duy nối nguồn thật sau khi nhóm lựa chọn

1. Cần nhà cung cấp/quyền đọc lịch sử tài khoản, credentials ngoài Git, tài khoản nhận + cơ sở
   tương ứng, quy tắc tiền tệ/múi giờ/mã ngân hàng, mẫu payload và tài liệu xác thực/retry chính thức.
   Nếu dùng webhook cần HTTPS callback, xác minh raw body/chữ ký/secret, chống replay và định danh
   sự kiện bền vững; không dùng một flag JSON hay trang redirect của khách làm xác minh.
2. Viết adapter triển khai `BankTransactionSource::findVerifiedByReference()`. Chỉ trả normalized
   proof sau xác minh server; transaction_id phải ổn định, duy nhất theo nguồn, dài tối đa150 ký tự,
   định dạng nhất quán với mã đang đối soát thủ công. Không tuỳ ý thêm tiền/timestamp từ booking.
   Đọc bằng GET phải không ghi dữ liệu; dùng timeout ngắn/batch hoặc kho giao dịch đã xác thực,
   tránh mỗi dòng bảng danh sách tạo nhiều request chậm. Source lỗi phải fail closed.
3. Inject cùng PaymentVerificationService vào AdminBookingController khi khởi tạo tại routes/bookings.php;
   constructor đang có tham số tùy chọn. Không có biến môi trường nào tự bật adapter giả trong production.
   Khi gọi service trực tiếp: `new BookingManagementService($pdo, verification: $verification)` và
   `confirmSyncedBookingAndPayment($bookingId, $trustedUser)`; không truyền proof từ HTTP vào writer.
4. Nguồn được truy vấn lại ở POST; proof được kiểm tra tiếp với payment/booking đã khóa. Chỉ giá trị
   từ proof mới lưu vào ba cột thực nhận/mã/thời gian khi xác nhận thành công. Dữ liệu client bị bỏ qua.
5. Chưa lưu inbox/provenance trước xác nhận: schema hiện tại không đủ phân biệt bền vững webhook
   đã xác thực với bản ghi tay hoặc lưu mọi giao dịch không khớp. Không nhét proof vào ghi chú500 ký tự.
   Nếu chọn webhook cần lưu sự kiện/mismatch, nhóm phải duyệt thiết kế persistence phù hợp trước khi bật.
   Hiện thiếu/thừa tiền, sai tham chiếu, chuyển muộn được nhận diện cần đối soát trên UI và giữ luồng
   đối soát thủ công; không ghi CAN_DOI_SOAT qua GET, không tự xác nhận hoặc hoàn tiền.

Các lớp adapter PHP là ranh giới tin cậy của code server, chưa phải một triển khai xác minh chữ ký.
Chưa có provider/API/key thì nút xác nhận thường vẫn disabled — đây là hành vi an toàn đúng yêu cầu.

## Kiểm thử và giới hạn

Chạy PHP bằng `D:\PHP\php\php.exe`. Các test mới/sửa dùng PDO giả hoặc SQLite `:memory:`,
không đọc .env, không tạo database MySQL, không xác nhận giao dịch trên dữ liệu thật.
Kết quả cuối lưu tại `storage/temp/payment-sync-review/`; ảnh modal tại
`storage/temp/booking-polish-review/modal-*.png` và `modal-verified-*.png`.

- `tests/payment-verification-unit.php`:84 checks về nguồn thiếu/lỗi, tiền đúng/thiếu/thừa,
  tham chiếu/số tài khoản/BIN/loại tiền/chiều tiền/ngày sai, booking hết hạn/hủy, NULL/0,
  không dịch vụ, snapshot, 4 vai trò, phạm vi, transaction lặp, rollback và stale read.
- `tests/manual-payment-sql-unit.php`:72 checks, giữ nguyên guard HY093 và test rollback dòng tồn thứ hai/hóa đơn.
- `tests/booking-management-unit.php`:109 checks; cập nhật fixture đáp ứng truy vấn hook thật, giữ assertions nghiệp vụ.
- Test deadline trôi sau xuất tồn dùng trigger RAM; test hai xác nhận xen kẽ dùng callback fixture,
  không gọi đó là hai transaction MySQL đồng thời.
- Chrome kiểm tra source MVC/render thật trên fixture RAM, CSP và 4 kích thước với4 vai trò;
  manual exception, forged POST, CSRF, double click, retry, inventory/invoice và quyền KHACH_HANG.
- Chưa chạy: webhook/signature/replay nhà cung cấp thật, kết nối ngân hàng, hai PDO MySQL cạnh tranh,
  deadlock/retry MySQL, concurrent expire/cancel/payment trên MySQL. Không tuyên bố end-to-end bank sync.

Kết quả cuối: Chrome4 suite = **1.999 PASS /0 FAIL** (booking-polish560, operations-polish1305,
booking-pr6-browser103, booking-review31). PHP24 file = **23 PASS /1 FAIL**. Lỗi có sẵn
`admin-list-unit.php:206`: action xem Bộ môn thiếu ID/ngữ cảnh trang; không sửa module Bộ môn.
`node tests/payment-state.cjs` vẫn **FAIL** ở assertion "payment success alone must not confirm booking":
`public/assets/js/booking.js:397` suy diễn từ payment THANH_CONG. Các assertions tiếp theo của hai
test dừng sớm chưa được chạy; không bỏ/ép PASS test. UI booking PR6 ban đầu fail một assertion ghim
số dòng log cũ282 sau khi model thêm dòng; đã kiểm tra đúng nguồn bankAccount/table bằng regex,
không ghim số dòng, và chạy lại103 PASS. Fixture ghi nhận lỗi thiếu bảng là ca chẩn đoán chủ động,
không phải website bình thường bị lỗi.

Phát/Duy cần chốt thêm trước khi bật production:

- `Booking::reserved()` chỉ cộng CHO_THANH_TOAN còn hạn, không cộng CHO_XAC_NHAN. Upload receipt
  hiện đổi sang CHO_XAC_NHAN, nên giữ ảo dịch vụ có khoảng trống ở trạng thái này. Hook vẫn chặn
  âm tồn/thiếu vật lý và rollback, nhưng không thể tuyên bố giữ ảo trọn luồng receipt đã được giải quyết.
- `BookingController::wait()` có nhánh ghi CHO_XAC_NHAN trên GET từ trạng thái pending/hết hạn;
  writer xác nhận vẫn kiểm tra hạn gốc, không phục hồi giữ sân cho đơn đã hết hạn. Không sửa nhánh này.
- UI chờ thanh toán và code render của khách cần dùng trạng thái booking để công bố giữ sân
  thành công; không coi payment đã nhận là booking đã xác nhận. Đây là phần cần Phát review riêng.

## File thay đổi trong lượt đồng bộ này (19 file)

Ứng dụng: `app/services/BankTransactionSource.php` (mới), `PaymentVerificationService.php` (mới),
`BookingManagementService.php`; `app/models/AdminBooking.php`;
`app/controllers/admin/AdminBookingController.php`; `app/views/admin/bookings/action-dialog.php`,
`show.php`; `public/assets/js/booking-actions.js`.

Kiểm thử: `tests/payment-verification-unit.php` (mới), `manual-payment-sql-unit.php`,
`booking-management-unit.php`, `booking-pr6-contract.php`, `booking-pr6-browser.cjs`,
`booking-polish-browser.cjs`, `operations-polish-browser.cjs`, `tests/fixtures/booking-actions.php`.
Các test/fixture mới từ lượt trước vẫn được giữ và cập nhật tiếp, không tạo bản sao thay test gốc.

Tài liệu: file này (mới), `docs/booking-inventory-contract.md`, `docs/booking-payment-contract.md`.
CSS, menu, employee password toggle, booking index và các thay đổi khác từ lượt trước giữ nguyên.
Ảnh biên lai untracked không thuộc thay đổi này, không xóa hoặc đưa vào diff bàn giao.

Khi review thủ công: vào Booking → ba chấm → Xác nhận để thấy card chờ, không có input tay;
Xem đối soát dẫn về trang chi tiết hiện có. **Không bấm xác nhận/ghi nhận tiền thật chỉ để thử UI.**
Luồng có nguồn ngân hàng chỉ được minh họa bằng fixture test, không xuất hiện giao dịch giả trên website thật.
