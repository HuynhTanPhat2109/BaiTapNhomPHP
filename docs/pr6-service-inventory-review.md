# PR6 Vũ — kiểm tra checkout, snapshot, giữ ảo và xuất tồn

Ngày kiểm tra: 03/11/2026. **Chưa đủ điều kiện duyệt PR6 như một tích hợp hoàn chỉnh.**
Các test/tài liệu có thể review riêng; còn bốn test hồi quy eligibility FAIL, test polling gốc FAIL,
hook chưa tích hợp và chưa kiểm chứng đồng thời bằng MySQL. Lượt audit PR6 ban đầu không sửa
source ứng dụng; lượt sửa HY093 sau đó chỉ đổi placeholder/binding trong BookingManagementService,
không đổi nghiệp vụ, inventory hook, quyền, schema hoặc dữ liệu ứng dụng.

## 1. Lỗi HTTP 500: nguyên nhân thật và kiểm tra lại

Chuỗi gọi thực tế:

`admin/bookings/index.php` → liên kết `url('/admin/bookings/show') & id=...` → `Router::dispatch`
→ `AuthMiddleware` + `RoleMiddleware` trong `routes/bookings.php` → `AdminBookingController::show`
→ `BookingManagementService::resolveScope` → `AdminBooking::find/courts/services/bankAccount`
→ `ReceiptImage` → view `admin/bookings/show` → layout Admin.

- Apache ghi nhiều `Application failure: PDOException at AdminBooking.php:282` khi mở chi tiết.
- Chẩn đoán bằng transaction **READ ONLY** tái hiện chính xác:
  `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'quanly_chuoi_san_the_thao.tai_khoan_ngan_hang_co_so' doesn't exist`.
- File lỗi: `app/models/AdminBooking.php:282`, tại `PDO::prepare()` trong `bankAccount()`;
  truy vấn bắt đầu dòng 277. Không phải lỗi format tiền, NULL payment hoặc trạng thái Đã hủy.
  `find`, `courts`, `services` đều đọc thành công trước khi truy vấn ngân hàng thất bại.
- Migration đã có: `database/migrations/20261101_tai_khoan_ngan_hang_co_so.sql`.
  Không chạy migration, không tạo bảng, không catch lỗi để giả thành ngân hàng rỗng.
- **Database thay đổi từ bên ngoài trong thời gian kiểm tra:** lần đọc sau bảng đã tồn tại;
  các trường hóa đơn từ migration 20261102 cũng hiện diện. Lần đọc sau thấy booking Đã hủy
  không có payment; `bankAccount()` trả NULL hợp lệ vì chưa có tài khoản ngân hàng cho cơ sở.
- Chrome đã click nút Chi tiết & Đối soát với dữ liệu local thật: danh sách, chi tiết và tải lại
  đều **HTTP 200**. Dùng server chẩn đoán loopback chỉ cho GET, PDO `READ ONLY`, danh tính Admin
  đang hoạt động trong phiên chẩn đoán riêng. **Không kiểm thử đăng nhập bằng mật khẩu**;
  không sửa auth và không ghi database để mở trang.

Vì nguyên nhân schema đã được cập nhật từ bên ngoài và trang hiện mở bình thường, không cần
patch source cho lỗi 500. Máy khác còn thiếu migration phải được người phụ trách đồng bộ schema;
PR6 không tự áp dụng migration. Thiếu bảng thật vẫn là lỗi cần báo, không phải empty state.

## 2. Tình trạng tích hợp hiện tại

| Kiểm tra | Kết luận theo source/test |
|---|---|
| PaymentService gọi `deductForPayment()`? | **BLOCKED**: không. PaymentService hiện tạo/đọc yêu cầu, không có callback xác minh ngân hàng |
| Có xác nhận thành công khác? | Có: `BookingManagementService::confirmBookingAndPayment()`; tự trừ tồn bằng SQL, không gọi hook |
| Cùng PDO / transaction? | Luồng thủ công dùng cùng PDO cho booking, payment, SQL tồn và InvoiceService; commit cuối, catch rollback toàn bộ |
| Khóa | Cha công ty → cơ sở → bộ môn/loại sân/sân tăng dần → dịch vụ tăng dần → booking → payment; có đọc payment chưa khóa trước bước chuẩn hóa |
| Hook của Vũ | Constructor nhận PDO; yêu cầu transaction đang mở; khóa booking/dịch vụ tăng dần; đọc quantity/unit snapshot; không commit/rollback/đổi booking/payment |
| Tồn NULL/0/âm | Hook bỏ qua NULL, từ chối thiếu/0, UPDATE có điều kiện và rowCount=1; không dùng giá catalog để tính lại booking |
| Retry | Hook không độc lập idempotent. Caller mô phỏng có gate đã paid+confirmed; luồng thủ công hiện từ chối lần xác nhận tuần tự thứ hai. Chưa kiểm chứng callback ngân hàng |
| Rollback | Lỗi SQL từng dòng được truyền ra ngoài; caller phải rollback toàn bộ. Test RAM kiểm tra lỗi sau xuất tồn và lỗi InvoiceService (stock, payment, booking đều rollback) |
| Chống gọi lặp hoàn chỉnh | **BLOCKED**, chưa có hook/callback tích hợp; không coi mock tuần tự là bằng chứng MySQL chạy đồng thời |
| Thời điểm hết hạn | Hook recheck sau chờ khóa; Duy còn phải kiểm tra lại ở conditional UPDATE booking trước commit |

`BookingInventoryService::deductForPayment(int $id): array` chỉ sẵn sàng như hook nội bộ có
điều kiện. Không gọi nó sau khi booking đã đổi sang DA_XAC_NHAN; không gọi thêm vào cạnh SQL
trừ tồn đang có; không gọi bù với dữ liệu đã xác nhận/đã thanh toán lệch trạng thái.

## 3. Các lỗi và lệch hợp đồng cần Phát/Duy xử lý

### A. FAIL: phục hồi booking đã hủy hoặc hết hạn khi đối soát

`BookingManagementService.php:325–329` lấy `isReconcile` từ payment và chuẩn hóa **bất kỳ**
booking đối soát về CHO_THANH_TOAN, đặt expiry thành now+30 phút. Kiểm tra hủy/hết hạn phía sau
nhìn thấy trạng thái đã chuẩn hóa, nên mất tác dụng.

Tái hiện an toàn: `php tests/booking-pr6-contract.php`. Test gọi Service/InvoiceService thật
trên SQL SQLite RAM, không chỉ gán kết quả mong muốn vào mock:

1. Booking DA_HUY, payment CAN_DOI_SOAT, số tiền khớp → xác nhận được, hạn giữ chỗ bị kéo dài.
2. Booking HET_HAN, payment CAN_DOI_SOAT, số tiền khớp → cũng xác nhận được.

Hai assertion phải từ chối hồi sinh/đổi deadline hiện **FAIL**; giữ nguyên để Phát/Duy review.
Đây là bằng chứng chuyển trạng thái, không phải kiểm thử khóa/race MySQL. Không sửa Service này.

Lượt sửa HY093 bổ sung hai regression eligibility: booking pending + payment HUY/HET_HAN
vẫn được xác nhận. Cả hai **FAIL** vì code hiện chưa whitelist trạng thái payment còn được xử lý.
Chỉ sửa SQL binding trong Service; bốn lỗi nghiệp vụ này cần Duy duyệt patch riêng.

### B. Đã sửa lỗi kỹ thuật: lặp named parameter trong SQL xuất tồn

`BookingManagementService.php:465–469`: cùng `:qty` dùng ở SET và điều kiện `>=`, nhưng PDO
trong `Core/Database.php` đặt `ATTR_EMULATE_PREPARES=false`. SELECT chỉ đọc tương đương
`SELECT :qty + :qty` với một binding đã tái hiện `SQLSTATE[HY093]: Invalid parameter number`.
Mock cũ và SQLite cho phép lặp tên nên test 108 check cũ không bắt được lỗi driver này.

Theo yêu cầu sửa HY093 sau báo cáo PR6, đã đổi thành `:qty_deduct`, `:qty_min` và truyền cùng
một số lượng vào hai binding riêng. Không thay transaction, trạng thái, quyền hay tích hợp hook.
Native PDO chạy SELECT chỉ đọc tương đương với các placeholder/binding thực tế đã PASS sau sửa.
Chưa chạy UPDATE xác nhận trên MySQL thật hoặc xác nhận booking #5 trên database ứng dụng.

Diff kỹ thuật đã áp dụng trong `BookingManagementService`:

```diff
- SET so_luong_ton = so_luong_ton - :qty
+ SET so_luong_ton = so_luong_ton - :qty_deduct
  WHERE ma_dich_vu = :service_id
    AND so_luong_ton IS NOT NULL
-   AND so_luong_ton >= :qty
+   AND so_luong_ton >= :qty_min
```

Binding đi kèm đã đổi thành `['qty_deduct'=>$qty, 'qty_min'=>$qty, 'service_id'=>(int)$srv['ma_dich_vu']]`.
Đây chỉ sửa tương thích native prepare, không giải quyết hai lỗi trạng thái hoặc thay cho tích hợp hook.

### C. FAIL: payment thành công bị coi là booking thành công

`public/assets/js/booking.js:397` dùng điều kiện OR payment THANH_CONG để hiện thành công dù
booking vẫn CHO_THANH_TOAN. `tests/payment-state.cjs` FAIL tại assertion gốc
`payment success alone must not confirm booking`. Chỉ bổ sung `style:{}` vào DOM fixture vì
HTMLElement thật có style; không bỏ assertion. Các assertion phía sau chưa chạy vì test dừng tại lỗi này.

Các nhánh PHP tương tự tồn tại trong `BookingController::wait` và `courts/booking-wait.php`.
Phát cần hiển thị trạng thái lệch để đối soát, không tự suy ra đã/chưa xuất tồn hoặc xuất bù.

Điều kiện JS đề xuất cho Phát (chưa áp dụng; PHP phải dùng cùng nguyên tắc):

```diff
- const isConfirmed = (state==='DA_XAC_NHAN' || state==='HOAN_THANH' || paymentStatus === 'THANH_CONG') && !isReconcile;
+ const isConfirmed = (state==='DA_XAC_NHAN' || state==='HOAN_THANH') && !isReconcile;
```

Paid-but-pending cần thông báo đối soát riêng; không được thay điều kiện này bằng một lệnh xuất tồn bù.

### D. BLOCKED: biên lai / CHO_XAC_NHAN / giữ ảo chưa cùng một quy tắc

- `BookingController.php:319` chuyển sang CHO_XAC_NHAN khi gửi biên lai. `Booking::reserved`
  chỉ cộng CHO_THANH_TOAN còn hạn; hook cũng chỉ chấp nhận trạng thái đó.
- `BookingController::wait` còn có UPDATE trạng thái trên GET khi thấy biên lai. PR6 không gọi
  luồng này trên dữ liệu thật và không tự sửa. Phát cần chuyển mutation về POST phù hợp và
  thống nhất thời hạn/trạng thái giữ chỗ, tránh vô tình giải phóng giữ ảo rồi xác nhận muộn.
- Không tự thêm CHO_XAC_NHAN vào query giữ tồn, gia hạn 30 phút, hay mở rộng hook khi chưa chốt nghiệp vụ.
- Admin cancel có thể đổi payment đã thành công sang HUY; không hoàn tồn vật lý. Cần Duy xác
  nhận chính sách hủy đơn đã thanh toán/đối soát, PR6 không tự sửa trạng thái tiền.

## 4. Checkout, snapshot và tồn ảo đã kiểm tra

- `BookingService::quote/confirm` tính giá trên server, quote lại trong transaction và khóa
  cha/dịch vụ trước insert. Snapshot lưu ID, tên, đơn vị, số lượng, đơn giá, thành tiền.
- Admin đổi tên/giá catalog không UPDATE snapshot hoặc ba tổng tiền booking. Lịch sử khách
  đọc snapshot không join giá catalog; Admin chi tiết cũng hiển thị snapshot.
- Trang chờ `Booking::visit`/`booking-wait.php` chưa có danh sách snapshot dịch vụ tương ứng:
  không tuyên bố đã hiển thị đầy đủ checkout → trang chờ. Cần Phát nối dữ liệu, không tính lại giá.
- Trường hợp 200 chai/đặt 2: pending vẫn 200, khả dụng 198. Caller thanh toán mô phỏng giữ
  transaction đúng hợp đồng: vật lý 198, reserved không còn booking đó, khả dụng 198.
- Nhiều booking pending cộng đủ các phần đang giữ. Hủy/hết hạn không cộng lại tồn vật lý,
  không đổi snapshot. Dịch vụ NULL khác 0; hết hàng hoặc chỉ còn 1/đặt 2 bị server từ chối.
- Dịch vụ ngừng cung cấp không được offer/checkout; đồ thuê vẫn ngoài phạm vi checkout.
  Markup chọn dịch vụ khóa input/nút khi available=0; luồng khách hàng đầy đủ trên browser chưa chạy.
- `Booking::expire` hiện bảo vệ payment THANH_CONG/CAN_DOI_SOAT, không ghi đè các trường hợp
  paid-but-pending; test RAM xác minh phần này. Hàng quá hạn được loại khỏi giữ ảo dù job chưa đổi trạng thái.

## 5. Patch đề xuất — CHƯA ÁP DỤNG

Duy phối hợp Phát sửa trong `BookingManagementService::confirmBookingAndPayment`:

1. Sau prelock cha/dịch vụ, khóa booking và payment; kiểm tra trạng thái **nguyên bản**, mã giao dịch,
   tổng đã lưu và thời hạn. Không chuẩn hóa DA_HUY/HET_HAN hoặc gia hạn để vượt `lockPayable`.
2. Cặp confirmed+paid cùng transaction → trả kết quả cũ, không gọi hook; khác transaction hoặc
   trạng thái lệch → đối soát, không xuất bù. Chốt chính sách CHO_XAC_NHAN trước khi mở hỗ trợ.
3. Khi booking vẫn payable, thay **toàn bộ đoạn SQL xuất tồn cũ** bằng đúng một lời gọi:

```php
// Bên Duy: đã xác minh tiền/giao dịch; cùng PDO, đang trong transaction và giữ các khóa.
$deduction = (new \App\Services\BookingInventoryService($pdo))->deductForPayment($bookingId);
$stmt = $pdo->prepare("UPDATE dat_san SET trang_thai_dat_san='DA_XAC_NHAN'
    WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN'
    AND het_han_giu_san_luc>CURRENT_TIMESTAMP");
$stmt->execute([$bookingId]);
if ($stmt->rowCount() !== 1) throw new \RuntimeException('Booking no longer payable');
// Cập nhật payment đã xác minh + lập invoice bằng API hiện có, rồi commit tất cả.
// Bất kỳ lỗi nào: rollback toàn transaction. Không retry riêng deductForPayment().
```

Đây là đoạn đặt trong transaction của Duy, không phải endpoint hay phương thức mới. Vẫn giữ
ghi nhận nhân viên, kiểm tra tiền/mã giao dịch/permission/CSRF ở code tích hợp; không chép đoạn
rút gọn để thay toàn bộ phương thức. Không giữ khóa trong lúc gọi mạng/ngân hàng.

Phát phối hợp sửa điều kiện success trong JS + wait PHP, thêm read model snapshot dịch vụ trang
chờ và thống nhất receipt/hold. Những file cần review trước sửa:
`BookingController.php`, `Booking.php` (visit/reserved nếu cần), `BookingService.php` nếu đổi hợp đồng,
`courts/booking-wait.php`, `public/assets/js/booking.js`, `BookingManagementService.php`.
Không tự sửa các file này trong PR6 hiện tại.

## 6. Ma trận kết quả cuối

| Nhóm | Kết quả thực tế |
|---|---|
| Chi tiết local thật, phiên Admin chẩn đoán READ ONLY | PASS: danh sách/link chi tiết/reload HTTP 200 sau khi schema được cập nhật từ bên ngoài |
| Chrome fixture pending/hủy/hết hạn/confirmed/thiếu payment/không dịch vụ | PASS: 103 check, 1440/768/375px; query_only=ON |
| Quyền 5 vai trò, scope công ty/cơ sở, CSRF | PASS trong fixture HTTP với Router/Middleware/Controller/SQL thật; identity fixture |
| Checkout/snapshot/hook/giữ ảo 200 chai/NULL/0/rollback | PASS: 80 test gốc + 39 test PR6; payment caller là mô phỏng |
| Snapshot lịch sử/ownership/GET không ghi | PASS: 46 check, SQLite query_only=ON |
| Manual payment service trên SQL RAM | 6 PASS, **2 FAIL** (hồi sinh hủy/hết hạn khi đối soát) |
| Eligibility bổ sung khi sửa HY093 | **2 FAIL** (payment HUY/HET_HAN được xác nhận khi booking pending); tổng contract hiện 6 PASS / 4 FAIL |
| Hồi quy HY093 sau sửa | `tests/manual-payment-sql-unit.php`: **67 PASS**, binding guard mô phỏng native PDO trên SQLite RAM; native MySQL chỉ chạy SELECT |
| Test booking management cũ | PASS 108 check trên mock, không chứng minh native MySQL binding |
| Dịch vụ / lịch sân | PASS 1031 / 36 check trong bộ nhớ |
| Polling payment/booking gốc | **FAIL** tại assertion payment-success-alone; các assertion phía sau NOT RUN |
| Booking modal JS | PASS; không phải bằng chứng checkout ngân hàng thật |
| Hook gọi từ PaymentService/callback | **BLOCKED**, chưa tích hợp; inline SQL hiện có không phải hook |
| Trùng mã giao dịch/callback khác giao dịch/duplicate callback thật | **NOT RUN/BLOCKED**, chưa có provider callback để kiểm tra |
| Hai callback / hai booking tranh tồn / cancel-expire-payment tranh khóa | **NOT RUN**, chưa có MySQL test an toàn |
| Thanh toán sát hạn | PASS hook từ chối khi chờ khóa mô phỏng vượt hạn; payment/khóa MySQL thật **NOT RUN** |
| Deadlock / 1213 / 1205 / retry whole transaction | **NOT RUN**; không suy ra từ rollback đơn luồng |
| Checkout khách hàng đầy đủ, GUI hết hàng và biên lai → xác minh | **NOT RUN** end-to-end; server/markup được kiểm tra riêng |
| Ngân hàng thật | **NOT RUN**, không được dùng để test PR6 |

103 check browser xác minh HTTP, dữ liệu snapshot, quyền và CSRF; không phải chứng nhận responsive.
Quan sát ảnh 375px: khối thông tin chi tiết vẫn chia hai cột hẹp, có nội dung bị khuất/tràn;
đây là vấn đề layout hiện có, chưa sửa vì ngoài phạm vi PR6 và không gây HTTP 500.

`booking-pr6-contract.php` exit 1 khi phát hiện bốn vi phạm; `payment-state.cjs`
cũng exit 1 thật. Không biến các ca này thành PASS bằng việc nới assertion. Chỉ sửa backend
sau khi người phụ trách duyệt patch và thống nhất quy tắc receipt/hold.

## 7. File và cách chạy

- Sửa `tests/booking-inventory-unit.php`: mock theo expiry job mới, không bỏ assertion cũ.
- Sửa `tests/booking-history.php`: bổ sung cột/fixture snapshot còn thiếu, giữ query_only và assertion cũ.
- Sửa `tests/payment-state.cjs`: DOM fixture có style như trình duyệt thật; giữ nguyên 7 assertion.
- Tạo `tests/booking-pr6-unit.php`, `tests/booking-pr6-contract.php`,
  `tests/booking-pr6-browser.cjs`, `tests/fixtures/booking-pr6.php`.
- Sửa hai hợp đồng `docs/booking-inventory-contract.md`, `docs/booking-payment-contract.md`;
  tạo báo cáo này.

```powershell
D:\PHP\php\php.exe tests/booking-pr6-unit.php
D:\PHP\php\php.exe tests/booking-history.php
D:\PHP\php\php.exe tests/booking-pr6-contract.php # hiện có 4 FAIL, cần Duy/Phát sửa
D:\PHP\php\php.exe tests/manual-payment-sql-unit.php # 67 PASS, chỉ hồi quy sửa SQL
node tests/payment-state.cjs                       # hiện FAIL invariant gốc
$env:PHP_BINARY='D:/PHP/php/php.exe'
$env:PLAYWRIGHT_PATH='D:/KiemThuPM/SOT357-65134364/node_modules/playwright-core'
$env:CHROME_BINARY='C:/Program Files/Google/Chrome/Application/chrome.exe'
node tests/booking-pr6-browser.cjs
```

Playwright path cần trỏ tới bản cài trên máy người chạy; không thêm thư viện vào dự án.
Fixture có guard CLI/server test; không nạp .env/MySQL, không mở thanh toán thật. Hai cột invoice
được mô phỏng trong SQLite RAM theo migration đã có, không chạy migration hoặc backfill MySQL.

Kết quả JSON, ảnh Chrome, chẩn đoán đọc-only và log nằm tại `storage/temp/pr6-review` (Git ignore).
Ảnh actual-local-detail có dữ liệu local, không đưa lên Git. Không reset/checkout/pull/commit/push/merge.
Ở lượt audit ban đầu, PHP lint 5 file test, JS syntax 2 file test và `git diff --check`: PASS;
209 file ứng dụng/core/routes/public/config/database giữ nguyên. Lượt sửa HY093 sau đó chỉ đổi
SQL binding tại một file ứng dụng; cập nhật mock booking-management, contract test và thêm
manual-payment-sql-unit.php. Bằng chứng trước/sau và diff riêng ở `storage/temp/manual-payment-sql`.
