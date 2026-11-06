# STRYKE — Kết quả triển khai đồng bộ trạng thái hết hạn booking/payment

Ngày thực hiện: **06/11/2026**, múi giờ Asia/Ho_Chi_Minh (+07:00). Branch: `kiem-thu-3`, HEAD khi bắt đầu: `55623473790089f8229a2bda2b9533f68966f5c5`.

Phạm vi được duyệt: phần đọc trạng thái theo phương án 3 trong hai báo cáo audit/impact. **Không kích hoạt scheduler, không cập nhật database ứng dụng, không sửa writer, giao diện, schema hay các workflow tài chính.**

## 1. Kết luận

Đã sửa phần đọc để booking quá hạn đủ điều kiện và payment pending cùng hiển thị **HET_HAN** ngay tại deadline, trước khi job cập nhật database. Danh sách/chi tiết admin, bộ lọc payment, dashboard và hai API khách hàng dùng quy tắc chung.

**Ba FAIL đã tái hiện trước sửa đều chuyển thành PASS.** Các kiểm tra hợp lệ sau sửa có tổng **250 assertion PASS / 0 FAIL**, thêm nhóm VM JavaScript PASS, PHP lint **6/6 PASS** và `git diff --check` đạt. Có **3 suite legacy bị chặn bởi lỗi fixture/mock có sẵn**; không coi chúng là PASS hoặc tuyên bố toàn bộ test suite đã xanh.

Scheduler/writer hiện tại không cần thay code theo các kiểm tra đã chạy. Bước vận hành thật vẫn **chưa thực hiện**: database ứng dụng sẽ còn raw pending quá hạn cho đến khi job được vận hành hoặc một thao tác ghi hợp lệ xử lý. Phần đọc không ghi thay scheduler.

## 2. Đối chiếu trước khi sửa và bảo vệ repository

Đã đọc lại:
- reports/STRYKE_EXPIRY_CHANGE_IMPACT_ASSESSMENT.md.
- reports/STRYKE_BOOKING_PAYMENT_EXPIRY_AUDIT.md.
- Sáu file trong phạm vi, writer Booking, BookingService, ReceiptService, BookingManagementService, PaymentService, các route/controller/view liên quan và job CLI.

Git đã có **25 file tracked thay đổi**, cùng các file untracked của nhóm trước lượt này. Không reset, checkout đè hay sửa các thay đổi đó. Lưu SHA-256 từng file của **332 file** source/schema/config/test/assets trước khi chỉnh tại storage/temp/expiry-implementation-baseline.json. Sau triển khai, đối chiếu xác nhận **chỉ 6 file PHP được duyệt thay đổi so với baseline của lượt này**. Các service nghiệp vụ, routes, bin/expire-bookings.php, schema, views, CSS, JavaScript và test suite chính giữ nguyên.

Các file báo cáo/artifact tạm là phần bổ sung được phép, không được tính vào 332 file source nói trên.

## 3. File/hàm đã sửa

| File | Hàm / thay đổi | Phạm vi giữ nguyên |
|---|---|---|
| app/models/Booking.php:21 | Thêm effectivePaymentStatusSql(): chỉ chiếu payment pending sang HET_HAN nếu booking hiệu lực HET_HAN và không thuộc điều kiện bảo vệ hiện có | effectiveStatusSql(), unprotectedSql(), mọi writer không đổi |
| app/models/Booking.php:29 | Thêm effectiveStates(): lấy cặp trạng thái trong một SELECT với cùng cutoff DB | Không cấp quyền mới, không UPDATE, không gọi job |
| app/models/AdminBooking.php:29 | page(): cutoff DB chốt một lần cho COUNT/SELECT/filter; SELECT và filter payment hết hạn dùng helper chung | Scope, tìm kiếm, ngày, DISTINCT/GROUP BY, pagination và fields giữ nguyên |
| app/models/AdminBooking.php:195 | find(): dùng cùng cutoff cho trạng thái booking/payment của chi tiết | Ownership/scope và dữ liệu snapshot giữ nguyên |
| app/models/Dashboard.php:84 | bookingStates(): phân nhóm trạng thái hiệu lực | paid/revenue/reviewFunds/transactions/popular không đổi |
| app/models/Dashboard.php:126 | recent(): cặp trạng thái hiệu lực, vẫn tối đa 8 booking và thứ tự cũ | Không nhân bản detail rows, không thay tiền |
| app/services/DashboardService.php:72 | data(): lấy cutoff DB một lần trong read transaction, truyền vào bookingStates()/recent() | Scope, filters và read transaction cũ giữ nguyên |
| app/controllers/BookingController.php:154,192 | waiting(): áp dụng cặp trạng thái đọc cho JSON polling và dữ liệu trình bày GET | POST confirm/upload/cancel/expire/payment retry, HTML và response keys không đổi |
| app/controllers/PaymentController.php:113 | status(): cặp trạng thái thống nhất trước tính flags/JSON | requireAuthorizedPayment(), QR/download/show, route và response keys không đổi |

Diff của 6 file: **62 dòng thêm / 17 dòng bỏ**. Không thêm route, field API, trạng thái enum hay dependency.

Trong helper SQL, alias booking/payment lần lượt là b/tt. Placeholder cutoff được bind hoặc thay bằng literal do PDO::quote() tạo từ thời gian DB nội bộ; không ghép dữ liệu request vào SQL. Các tham số lọc và scope vẫn dùng prepared statements như trước.

## 4. Logic trước/sau

### Trước

- Booking đã có effectiveStatusSql(), nhưng payment ở nhiều query vẫn trả raw pending.
- Admin dùng effective booking cho filter pending, nhưng filter payment HET_HAN dùng raw payment nên đơn quá hạn có thể vắng ở cả hai filter payment.
- Dashboard bookingStates()/recent() dùng raw booking; pending/expired lệch lịch sử khách hàng.
- Hai API trả effective booking cùng raw payment.
- COUNT và SELECT admin có thể lấy CURRENT_TIMESTAMP ở hai thời điểm khác nhau.

### Sau

1. Giữ nguyên điều kiện hết hạn và bảo vệ của effectiveStatusSql()/unprotectedSql() mà writer đang sử dụng.
2. Helper payment chỉ đổi **kết quả đọc** khi payment raw còn CHO_THANH_TOAN, booking hiệu lực HET_HAN và không có protection hiện có.
3. Payment THANH_CONG, CAN_DOI_SOAT, HUY và payment không tồn tại không bị biến thành HET_HAN.
4. Admin COUNT, SELECT và filter dùng cùng cutoff lấy từ DB; không dùng giờ trình duyệt.
5. DashboardService truyền cùng cutoff cho KPI và recent, kể cả khi DB clock đi qua deadline giữa các query.
6. Hai API đọc cặp trạng thái cùng SELECT/cutoff sau bước kiểm quyền hiện có; không đưa các giá trị này vào writer.

**Không UPDATE/INSERT/DELETE từ GET.** Hash dat_san, thanh_toan, hoa_don, dich_vu và hai bảng chi tiết không đổi sau các lượt đọc thật trong fixture HTTP và model/service.

Cặp raw booking HET_HAN/payment pending không được bảo vệ cũng có thể được trình bày là expired/expired; đó không phải repair dữ liệu. Legacy receipt hoặc trạng thái tài chính đặc biệt vẫn giữ ngoại lệ bảo vệ.

## 5. Ba lỗi ban đầu

Đã chạy lại harness trước khi sửa, trên DB ngẫu nhiên độc lập: **49 PASS / 3 FAIL**.

| Ca | Trước sửa | Sau sửa |
|---|---|---|
| Admin find booking/payment đủ điều kiện quá hạn | HET_HAN / CHO_THANH_TOAN — FAIL | HET_HAN / HET_HAN — PASS |
| Dashboard pending bỏ đơn quá hạn trong fixture hai đơn | Đếm 2, mong đợi 1 — FAIL | Đếm 1; đơn quá hạn thuộc HET_HAN — PASS |
| Filter payment HET_HAN trước cleanup | Không tìm thấy đơn — FAIL | Tìm đúng đơn; COUNT/pagination không trùng — PASS |

Artifact trước: storage/temp/expiry-impact-gQwUjD/report.json.
Artifact sau: storage/temp/expiry-impact-rWhYvv/report.json (**52 PASS / 0 FAIL**).

Trong harness sau, assertion polling được đổi từ việc chứng minh split status cũ sang yêu cầu cặp expired/expired; không đổi expectation của ba lỗi cần sửa hoặc nghiệp vụ writer.

## 6. Bảo toàn các trạng thái và workflow

| Trường hợp | Kết quả đọc/ghi được kiểm tra |
|---|---|
| Pending trước deadline | Booking/payment vẫn pending; reference/deadline không reset |
| Đúng deadline hoặc sau deadline, không receipt/protection | Phần đọc expired/expired; raw vẫn pending trước job; job ghi expired/expired trong transaction |
| Receipt được chấp nhận đúng hạn | Booking CHO_XAC_NHAN, payment raw CHO_THANH_TOAN; không đánh dấu tiền thành công |
| Review sau deadline một ngày | Vẫn review/pending, giữ lịch và giữ tồn suy ra |
| Xác nhận hợp lệ | DA_XAC_NHAN/THANH_CONG, đúng một invoice, xuất tồn đúng một lần |
| Từ chối/hủy review đúng quyền, có lý do | DA_HUY/HUY theo service hiện có; giữ chứng từ/reference/amount |
| Khách explicit cancel | Trước hạn dùng DA_HUY/HUY; sau cutoff vẫn HET_HAN/HET_HAN theo quy tắc cũ |
| Khách cancelHold khi đã review | Không tự hủy review |
| HOAN_THANH/THANH_CONG | Job không đổi, từ chối sai quy trình bị chặn |
| Payment thành công nhưng booking bất thường | Bảo vệ để đối soát, không tự expire tiền/approve booking |
| CAN_DOI_SOAT không receipt, booking pending quá hạn | Booking vẫn hết hạn theo policy cũ; payment giữ CAN_DOI_SOAT |
| Review/CAN_DOI_SOAT | Review vẫn giữ lịch; không auto-expire |
| Tiền đến muộn sau HET_HAN/HET_HAN | Vẫn ghi nhận CAN_DOI_SOAT hợp lệ, không hồi sinh sân/invoice/doanh thu |
| Legacy có receipt link | Helper/job bảo vệ theo guard cũ; không tự chuyển/repair dữ liệu |
| Thiếu payment | Không tạo payment giả, trạng thái payment vẫn null |

Không thay đổi chính sách admin hiện có cho phép một lệnh hủy rõ ràng, có quyền/lý do chuyển đơn HET_HAN chưa paid sang DA_HUY/HUY. Không thêm TU_CHOI hoặc raw payment CHO_XAC_NHAN.

**Các test chứng minh bảo toàn trong fixture; không tuyên bố đã vét cạn mọi interleaving hoặc mọi dữ liệu tài chính bất thường.** Guard hiện có bảo vệ receipt/success và các trạng thái tài chính theo workflow đã audit. Nếu có payment raw pending chứa tiền thực nhận/mã giao dịch bất thường nhưng không receipt/success, cần đối soát riêng; lượt này không mở rộng writer hay thêm guard chỉ ở read-model gây lệch writer. Không sửa #84/#86/#100.

## 7. Kiểm thử sau sửa

Mọi ghi dữ liệu dùng DB riêng, tên ngẫu nhiên, có guard prefix và khác DB ứng dụng; server HTTP test dùng DB_NAME riêng, cookie/session riêng. Không chạy CLI/job trên DB ứng dụng.

| Nhóm | Kết quả hợp lệ sau sửa | Nội dung |
|---|---:|---|
| Harness lifecycle/receipt/expiry concurrency/refusal | **52 PASS / 0 FAIL** | Gồm ba lỗi, multicourt/service, upload/job, approval/job, refusal/cancel/completed, late funds |
| Harness payment retry/fault/rollback | **11 PASS / 0 FAIL** | Reuse không reset, expired request bị chặn, fault write payment rollback cả hai bảng, retry |
| Harness read matrix/service/cutoff/scope/HTTP | **88 PASS / 0 FAIL** | 16 cặp trạng thái, exact deadline, COUNT/pagination, DB clock drift, API contract/IDOR/admin roles |
| tests/booking-expiry-integration.php --run | **33 PASS / 0 FAIL** | Lock, idempotency, cutoff, CLI test DB, backlog hơn 2.000 row, retry |
| tests/high-bug-fixes-integration.php --run --only=BUG-004 | **13 PASS / 0 FAIL** | Real login/upload, CSRF, duplicate, late/review, confirm/invoice/stock |
| tests/high-bug-fixes-integration.php --run --only=BUG-003 | **5 PASS / 0 FAIL** | Invoice đúng payment/booking, duplicate, rollback |
| tests/booking-history.php | **48 PASS / 0 FAIL** | SQLite RAM; history/filter/count/pagination |
| **Tổng assertion các nhóm trên** | **250 PASS / 0 FAIL** | Không cộng lại các lượt tái hiện trước sửa |
| node tests/payment-state.cjs | **PASS — 1 nhóm VM** | Không phải browser E2E |
| PHP lint 6 file sửa | **6/6 PASS** | Không lỗi cú pháp |
| git diff --check | **PASS** | Không lỗi whitespace |

### 7.1. Chi tiết kiểm chứng quan trọng

- **Exact cutoff:** read helper/admin và job cùng coi deadline bằng giờ DB là hết hạn; trước một giây vẫn pending.
- **COUNT/filter/pagination:** thử page size 2, nhiều booking có hai dòng sân; tập ID không thiếu, không trùng. Filter review và pending riêng vẫn theo alias hiện có.
- **Cutoff thống nhất dashboard:** DB test được cố định lúc 12:00:00; giả lập DB clock chuyển 12:00:02 giữa KPI/recent trong PDO fixture. Một booking deadline 12:00:01 vẫn có trạng thái nhất quán theo cutoff 12:00:00 đã chốt. Không đổi đồng hồ hệ điều hành hay DB ứng dụng.
- **HTTP thật:** gọi /booking/wait?status=1 và /payment/status cho fresh/expired/review/confirmed/completed/reconcile/cancelled/paid-anomaly; so khớp cả cặp trạng thái và danh sách JSON keys cũ.
- **Quyền:** khách khác nhận 404 ở hai API; khách không vào admin; root không dùng API customer; payment API chưa login nhận 401. Admin root/company/facility/staff đọc list/detail đúng scope, công ty khác bị 404.
- **Multicourt/services:** deadline giải phóng occupancy/reservation suy ra, không hoàn hay trừ tồn vật lý bằng projection/job; không tạo invoice cho expired.
- **Race:** real multipart upload vs process job, hai thứ tự ai giữ row lock trước; metadata chậm qua cutoff rollback; manual approval vs expiry không tạo paid/review mâu thuẫn hoặc trừ stock lặp.
- **Recovery:** test-only trigger SIGNAL làm payment expiry thất bại; booking UPDATE bị rollback cùng transaction; bỏ fault rồi job tiếp theo xử lý được.
- **Invoice:** invoice đúng payment của booking; lặp không thêm invoice; caller failure rollback. Manual approval đã kiểm stock/invoice đúng một lần.
- **Doanh thu:** projections không sửa paid/revenue/reviewFunds; fixture xác nhận chỉ khoản verified thuộc doanh thu, late/reconciliation không được cộng nhầm.
- Trigger/delay/fault chỉ tạo trong DB test và dọn theo DB sở hữu; không thêm migration hoặc trigger cho ứng dụng.

### 7.2. Suite legacy không chạy đến nghiệp vụ

Đã chạy thật, **exit code khác 0**; không tính là PASS. Phần coverage tương ứng của các suite này là **BLOCKED**:

| Suite | Lỗi harness hiện có | Xử lý trong lượt này |
|---|---|---|
| tests/dashboard-unit.php | fixtures/dashboard.php:28 ALTER lại so_hoa_don đã có trong schema | Không sửa fixture/schema; dashboard thật đã kiểm qua MariaDB/service/HTTP độc lập |
| tests/booking-actions-unit.php | fixtures/booking-pr6.php:33 tạo lại tai_khoan_ngan_hang_co_so | Không sửa; actions/refusal/permissions kiểm qua service/HTTP độc lập |
| tests/booking-inventory-unit.php | :115 fake statement đòi predicate SQL cũ, áp deadline cho cả review | Không sửa; chạy với Booking.php từ HEAD trong bộ nhớ cũng lỗi y hệt, không phải regression do helper mới |

Lỗi mock inventory đã được nêu ở BUG-006 của full audit; không sửa BUG-006 hay sửa writer cho mock PASS. Các kiểm tra tồn thật ở MariaDB đã PASS nhưng không thay thế việc phải bảo trì suite legacy về sau.

### 7.3. Lượt HTTP harness không hợp lệ đã xử lý riêng

Lượt đầu của harness read bổ sung đăng nhập lại cho từng request, chạm **LoginThrottle HTTP 429**, kéo theo 401/redirect và **51 assertion PASS / 37 assertion FAIL**. Đây là lỗi điều kiện chạy harness, không bằng chứng 37 lỗi nghiệp vụ. Bằng chứng giữ tại storage/temp/expiry-implementation-read-gUMOLB/report.json và server.log.

Đã sửa **chỉ artifact test** để tái sử dụng cookie theo actor và bind địa chỉ loopback riêng cho lượt test; auth/CSRF/throttle của source vẫn bật nguyên trạng. Lượt hợp lệ sau đó đạt **88/88** với login thật. Không xóa/reset trạng thái limiter dùng chung của ứng dụng để vượt giới hạn. Tổng 250 ở trên chỉ dùng các lượt có điều kiện test hợp lệ; không giấu lượt lỗi điều kiện và không gộp 51 PASS của lượt này để tăng số lượng.

## 8. Scheduler: giữ nguyên code, chưa kích hoạt

Đã kiểm source và chạy job CLI **chỉ với DB test riêng**:

- CLI opt-in --run; mỗi booking một transaction ngắn, booking lock trước payment.
- syncEndedPayment ghi payment pending hết hạn trong transaction của booking; không ghi đè THANH_CONG/CAN_DOI_SOAT/HUY.
- Receipt hiện đại/legacy và booking review không bị job sáu phút tự hết hạn theo guard hiện có.
- Advisory GET_LOCK theo database chống hai instance/process trùng; finally giải phóng lock.
- Batch 200, tối đa 10 batch; budget loop khoảng 45 giây; CLI lock wait 5 giây.
- Backlog còn lại được báo more_pending và chọn tiếp ở lượt chạy sau. Lỗi rollback row hiện tại, lần sau retry; không giữ transaction mở sáu phút.
- Không tạo invoice hoặc UPDATE tồn dịch vụ. Log started/completed/failed, protected_sample, overlap_skipped.
- Không có nhu cầu đổi bin/expire-bookings.php/expire()/expireOne()/syncEndedPayment() trong phạm vi đã kiểm chứng.

**Chưa tạo/bật Windows Task Scheduler hoặc cron. Chưa chạy job với .env ứng dụng.** Không dùng log CLI test làm bằng chứng scheduler thật đang chạy.

## 9. Rủi ro còn lại và giới hạn xác minh

1. **Vận hành chưa kích hoạt:** raw pending trên DB thật chưa được dọn tự động chỉ vì sửa phần đọc. Cần bước scheduler riêng được phê duyệt.
2. **Độ trễ:** job mỗi phút thường chờ 0–60 giây tới lượt quét, cộng startup/lock/batch/commit. Lỗi, ngủ/tắt máy hoặc backlog có thể làm trễ hơn; 45 giây không phải hard timeout cho toàn bộ process.
3. **Legacy/anomaly:** #84/#86/#100 không thay đổi dữ liệu. Đơn có receipt legacy hoặc tiền bất thường phải xử lý đúng nghiệp vụ/đối soát riêng; helper không repair hay xác nhận chúng.
4. **Suite cũ:** ba suite bị chặn như mục 7.2 cần công việc riêng được duyệt; không thể tuyên bố full regression suite toàn hệ thống PASS.
5. **Không kiểm browser/device/stress đầy đủ:** đã kiểm HTTP/PHP/SQL/API thật và VM JS, chưa kiểm Chrome/mobile trực quan, không giao dịch ngân hàng thật, không reboot server thật, không kiểm mọi concurrency interleaving.
6. **Read snapshot:** cùng cutoff bảo đảm quy tắc thời gian nhất quán. Hai request HTTP độc lập vẫn có thể thấy hai snapshot khác nhau khi có thao tác xác nhận đồng thời; mỗi cặp trạng thái từ effectiveStates được lấy trong cùng SELECT. Không thêm khóa ghi hoặc thay isolation của admin để ép mọi request đứng yên.

**Đánh giá:** phần code đã sửa đạt các ca mục tiêu và các regression hợp lệ đã chạy. **Chưa hoàn tất bước vận hành scheduler trên môi trường thật; không đánh giá toàn hệ thống READY chỉ dựa lượt này.**

## 10. Artifact và xác nhận an toàn

- storage/temp/expiry-implementation-baseline.json — hashes lúc bắt đầu.
- storage/temp/expiry-implementation-changes.json — đúng sáu file thay đổi.
- storage/temp/expiry-impact-gQwUjD/ — baseline 49 PASS / 3 FAIL.
- storage/temp/expiry-impact-rWhYvv/ — lifecycle sau sửa 52 PASS.
- storage/temp/expiry-impact-policy-vbjV5f/ — rollback/retry 11 PASS.
- storage/temp/expiry-implementation-read-etQp9I/ — read/scope/HTTP/cutoff 88 PASS.
- storage/temp/high-bug-fixes-fedb926eb3/ — receipt/approval 13 PASS.
- storage/temp/high-bug-fixes-b713d083d9/ — invoice 5 PASS.
- storage/temp/expiry-implementation-test-evidence.log — output suite/legacy, kiểm tra cú pháp và bằng chứng so sánh.

DB test do các lượt này tạo được dọn theo tên/prefix sở hữu; không xóa database hay upload thật. Không cài dependency, sửa schema, sửa các test suite chính hoặc giao diện. Không thay nhãn /giờ, sửa BUG-007, commit, push hay merge.

## 11. Hướng dẫn cấu hình mỗi phút — chỉ thực hiện sau phê duyệt kích hoạt riêng

Không thực hiện các bước dưới đây trong lượt triển khai code này.

1. Đảm bảo MySQL XAMPP hoạt động. Tài khoản chạy task cần đọc project/vendor/.env, kết nối MySQL, ghi storage/temp/booking-expiry; không cần cấp quyền admin Windows chỉ để chạy PHP.
2. Mở **Task Scheduler → Create Task**, tên gợi ý **STRYKE Booking Expiry**. Chọn chạy dù user đã đăng nhập hay chưa nếu chính sách máy cho phép.
3. Trigger: chạy hằng ngày, **Repeat every 1 minute / Indefinitely**. Có thể thêm **At startup**, delay 1 phút để MySQL sẵn sàng.
4. Action **Start a program**:
   - Program/script: C:\xampp\php\php.exe
   - Add arguments: "C:\xampp\htdocs\BaiTapNhomPHP\bin\expire-bookings.php" --run
   - Start in: C:\xampp\htdocs\BaiTapNhomPHP
5. Settings: **Run task as soon as possible after a scheduled start is missed**, **Do not start a new instance**. Không phụ thuộc idle; cân nhắc điều kiện pin/nguồn điện nếu laptop.
6. Xác minh .env/workdir trỏ đúng DB ứng dụng mong muốn, không còn DB_NAME test từ shell hoặc environment task. Không import SQL/migration.
7. **Chỉ sau khi được phép ghi thật**, chạy task lần đầu; kiểm Last Run Result 0x0 và storage/temp/booking-expiry/YYYY-MM-DD.log. Theo dõi failed, more_pending lặp lâu, protected_sample và overlap_skipped; dùng SELECT kiểm tra cặp trạng thái đủ điều kiện.
8. Máy ngủ/tắt hoặc MySQL ngừng không thể chạy đúng phút; khi hoạt động lại, task chọn backlog chưa xử lý. Cần log/giám sát lỗi, không chỉ nhìn nhãn giao diện.

PHP CLI không cần Apache để chạy job. Không bật thêm DB EVENT/trigger hoặc lịch thứ hai chồng lên task. Không UPDATE trực tiếp hàng loạt booking/payment để thay bước vận hành này.

