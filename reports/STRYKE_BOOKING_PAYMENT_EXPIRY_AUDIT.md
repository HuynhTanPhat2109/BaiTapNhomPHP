# STRYKE — Audit tính nhất quán hết hạn Booking / Payment

Ngày kiểm tra: **06/11/2026**, múi giờ **Asia/Ho_Chi_Minh (+07:00)**. Nhánh **kiem-thu-3**, HEAD **55623473790089f8229a2bda2b9533f68966f5c5**.

**CHỈ KIỂM TRA, TÁI HIỆN VÀ BÁO CÁO. Không sửa code hoặc dữ liệu ứng dụng.**

## 1. Kết luận chính

**Hiện tượng của #100 được xác nhận, nhưng booking chưa được ghi HET_HAN trong database.** Tại15:15–15:30 ngày06/11, cả dat_san và thanh_toan vẫn CHO_THANH_TOAN. Admin trả HET_HAN cho booking bằng effective projection, đồng thời trả trạng thái payment thô CHO_THANH_TOAN.

Code chuyển trạng thái thực sự **đã có đồng bộ payment cùng transaction** trong Booking::expireOne() → syncEndedPayment(). Test MariaDB riêng xác nhận chạy job xong cả hai bảng cùng HET_HAN. Không có bằng chứng lỗi quên UPDATE payment hoặc race làm commit nửa transaction trong ca #100.

Vấn đề gồm ba lớp:

1. **Chưa materialize hết hạn:** thời gian trôi qua không tự UPDATE MySQL. Cần job hoặc POST hợp lệ. #100 chưa có kết quả ghi của cơ chế này; không thấy Windows task phù hợp trên máy kiểm tra.
2. **Read-model không đồng nhất:** booking dùng effective status, payment dùng raw status; API/admin trả cặp trạng thái khác nhau và bộ lọc payment HET_HAN bỏ sót bản ghi mới chỉ hết hạn theo projection.
3. **Thống kê booking dùng raw status:** Dashboard::bookingStates()/recent() chưa dùng cùng projection như lịch sử/admin, nên KPI chờ thanh toán/hết hạn bị lệch trong thời gian cleanup chưa chạy.

**Chưa phát hiện sai doanh thu thực tế trong phạm vi kiểm tra.** Dashboard chỉ lấy payment THANH_CONG trên booking DA_XAC_NHAN/HOAN_THANH, đủ bằng chứng/khớp tiền. #100 không nằm trong doanh thu. Không kết luận mất tiền, đếm trùng doanh thu hoặc lỗi xác minh thanh toán từ hiện tượng này.

## 2. Booking #100 — dữ liệu thật, READ ONLY

Snapshot đầu lúc **06/11/2026 15:15:33 +07:00**; kiểm tra lại lúc **15:30:56** vẫn giữ nguyên trạng thái và deadline.

| Trường / kiểm tra | Kết quả |
|---|---|
| dat_san.ma_dat_san | 100 |
| Raw trang_thai_dat_san | CHO_THANH_TOAN |
| ngay_tao / bat_dau_giu_san_luc | 06/11/2026 14:42:28 |
| het_han_giu_san_luc | 06/11/2026 14:48:28, đúng6phút |
| Booking effective status | HET_HAN |
| Tiền sân / dịch vụ / tổng snapshot | 110.000 / 0 / 110.000 đ |
| Payment liên quan | ma_thanh_toan65, liên kết ma_dat_san100 |
| Raw payment status | CHO_THANH_TOAN |
| Payment tạo lúc | 06/11/2026 14:42:28 |
| so_tien_thuc_nhan / ngay_thanh_toan | NULL / NULL |
| Ảnh cột mới / tag legacy / file resolver | Không / Không / Không |
| minh_chung_gui_luc / xac_nhan_thu_cong_luc | NULL / NULL |
| Mã giao dịch xác nhận | Không có |
| Hóa đơn | Không có |
| AdminBooking::find(100) trả về | booking HET_HAN, payment CHO_THANH_TOAN |
| Booking::history() của đúng chủ booking | #100 thuộc HET_HAN |

Không xuất tên, số điện thoại, thông tin ngân hàng, nội dung ghi chú riêng, hash/token/session của khách ra báo cáo.

**Không tái hiện được giả thuyết lịch sử hiện tại lấy payment pending làm nhãn chính của #100.** Model history trả HET_HAN và view courts/history.php:87 dùng chính trạng thái booking đó; không dùng payment status cho badge. Đây là kiểm tra model thật + source view, không phải đăng nhập tài khoản thật của khách để browser-test trang của họ. Dashboard mục “Booking gần đây” dùng raw nên có thể hiện Chờ thanh toán; không nhầm mục đó với /booking/history.

## 3. Schema và contract trạng thái

Đã đọc main SQL và information_schema của database đang dùng; ENUM thực tế:

```text
dat_san.trang_thai_dat_san:
  CHO_XAC_NHAN, CHO_THANH_TOAN, DA_XAC_NHAN, HOAN_THANH, HET_HAN, DA_HUY

thanh_toan.trang_thai:
  CHO_THANH_TOAN, THANH_CONG, HET_HAN, HUY, CAN_DOI_SOAT
```

**Payment đã hỗ trợ HET_HAN. Không cần thêm enum, bảng hoặc migration để đạt yêu cầu đồng bộ hiện tại.** CHO_XAC_NHAN trong một số nhãn/nhánh UI payment là alias trình bày theo trạng thái booking, không phải lý do thêm trạng thái này vào enum payment. Booking đã nhận receipt hợp lệ có thể là CHO_XAC_NHAN trong khi payment vẫn CHO_THANH_TOAN — đúng nghiệp vụ chờ nhân viên xác minh.

Thanh toán liên kết booking bằng thanh_toan.ma_dat_san UNIQUE. Khóa duy nhất ngăn nhân bản payment khi JOIN thống kê; số tiền booking/các thành phần có CHECK, hóa đơn có unique liên kết riêng. Không thay các ràng buộc trong lượt kiểm tra này.

## 4. Truy vết luồng hiện tại

| Bước | File / hàm / dòng | Hành vi thực tế |
|---|---|---|
| Tạo mốc6phút | app/models/Booking.php:213–230, insert() | Sau khi ghi chi tiết, đặt ngay_tao/bat_dau=CURRENT_TIMESTAMP, deadline=DATE_ADD(..., INTERVAL6MINUTE), trong transaction confirm |
| Đọc effective booking | Booking.php:13–35, effectiveStatusSql()/findEffective() | CASE pending quá hạn không được bảo vệ → HET_HAN; chỉ SELECT |
| Bảo vệ tiền/receipt legacy | Booking.php:20, unprotectedSql() | Không tự expire pending có payment thành công hoặc đường dẫn receipt modern/legacy liên kết; không tự approve/chuyển review |
| History khách | Booking.php:55–125; BookingHistoryController::index() | Cards/counts/filter dùng cùng cutoff và effective booking; GET không ghi hết hạn |
| Admin | AdminBooking.php:29,192; page()/find() | Booking có CASE effective; payment tt.trang_thai vẫn raw ở149/213 |
| Hiển thị admin | views/admin/bookings/index.php:179–186,254; show.php:4,24,69 | Chỉ đổi alias payment sang Chờ xác nhận khi booking review; không suy ra payment expired từ booking projected expired |
| API trạng thái | BookingController::waiting():137–166; PaymentController::status():102–126 | Booking lấy inspect/effective, payment lấy raw; có thể trả HET_HAN + CHO_THANH_TOAN |
| POST hết hạn | BookingController::expire():337; BookingService::owned():165 | Kiểm owner rồi gọi model expire(id) và trả trạng thái mới |
| Job | bin/expire-bookings.php:22–35 → BookingService::expirePending():173 | Mở DB theo env và gọi Booking::expire(); log/timeout/job lock; CLI opt-in --run |
| Batch hết hạn | Booking.php:233–270, expire() | Chọn raw pending đến hạn, không protected; advisory lock theo DB, giới hạn batch và runtime |
| Ghi hết hạn một đơn | Booking.php:273–300, expireOne() | Transaction, khóa booking rồi payment, kiểm lại, UPDATE booking có điều kiện, sync payment, commit/rollback |
| Đồng bộ payment | Booking.php:166–172, syncEndedPayment() | Booking raw HET_HAN/DA_HUY thì payment CHO_THANH_TOAN→HET_HAN/HUY; không ghi đè THANH_CONG/CAN_DOI_SOAT |
| Upload receipt | BookingReceiptService.php:59–113 | Khóa booking/payment, kiểm deadline, lưu ảnh/metadata, kiểm DB clock lần cuối, chuyển booking review cùng transaction; không payment success |
| Xác nhận/đối soát | BookingManagementService::finalizeBookingAndPayment():227; markNeedsReconciliation():551 | Writer khóa và kiểm trạng thái lại; xác nhận/stock/invoice cùng transaction; late money có nhánh riêng |

### 4.1. Tại sao sau6phút database vẫn pending?

MySQL không tự chạy UPDATE vì cột deadline đã qua. Hết hạn vật lý chỉ được ghi khi job hoặc POST xử lý đến booking. GET history/admin/inspect cố ý chỉ đọc. Current expireOne() đã gọi syncEndedPayment() trước commit, nên không cần viết lại một UPDATE thanh_toan giống hệt để sửa ca này.

Get-ScheduledTask trên máy audit không tìm thấy tác vụ phù hợp theo tên STRYKE/booking/expire, executable PHP hoặc arguments expire-bookings. Đây là bằng chứng cấu hình trên máy đang kiểm tra, **không chứng minh không có cron/service trên máy khác hoặc wrapper có tên khác**. Dữ liệu cho thấy #100 chưa được cleanup ghi/commit thành công; chưa có bằng chứng để phân biệt tuyệt đối “chưa từng chạy”, “chạy nhầm môi trường” hay “một lượt trước đã lỗi” nếu không có log vận hành tương ứng.

Job được thiết kế chạy mỗi phút: sau khi cấu hình đúng vẫn có độ trễ giữa deadline và lần job kế tiếp; availability/effective status đã khóa/giải phóng theo thời gian ngay khi đọc.

### 4.2. JavaScript không thay thế scheduler

booking.js:517 chỉ POST /booking/expire khi seconds=0, không có receipt, chưa paid và state vẫn CHO_THANH_TOAN. Lỗi request bị catch; không có bảo đảm retry bền vững. Nếu khách đóng tab, mất mạng, hoặc poll đã đổi state thành HET_HAN trước nhánh tick đó, POST có thể không xảy ra. Khi trang mở sau hạn với effective HET_HAN, branch pending này cũng không phải cơ chế dọn backlog.

JS không tự sửa database và không phải nguyên nhân transaction commit nửa chừng. Không nên bỏ read-only GET hoặc phụ thuộc trình duyệt để đảm bảo tác vụ nền.

### 4.3. Có thể raw booking HET_HAN nhưng raw payment pending không?

**Ở snapshot database thật lần này:0 cặp như vậy.** Các bản ghi #100 và12payment pending đang quan tâm là booking raw pending, projected expired. Tất cả4booking đã raw HET_HAN và có payment trong snapshot đều đi cùng payment HET_HAN; một booking HET_HAN khác không có payment.

Về khả năng dữ liệu cũ: nếu từng có writer cũ/manualSQL tạo raw HET_HAN + pending thì có thể tồn tại. Job bulk chỉ quét raw pending nên không tự chọn lại những cặp đã raw HET_HAN để sửa payment; expire(id) hiện tại có sync nhánh đó, nhưng không nên gọi bừa trên dữ liệu receipt/tiền cần đối soát. Chưa có dữ liệu như vậy ở snapshot, không tạo repair hàng loạt hoặc khẳng định nguyên nhân lịch sử không có chứng cứ.

## 5. Ảnh hưởng thống kê — phân biệt chính xác

### 5.1. Doanh thu đã thu: chưa phát hiện sai

Dashboard::paid() (Dashboard.php:44–65) được revenue(), transactions(), popular() dùng chung. Các điều kiện gồm:

- Payment THANH_CONG; booking DA_XAC_NHAN hoặc HOAN_THANH.
- Có ngày/mã giao dịch hợp lệ hoặc bằng chứng manual verification theo schema.
- so_tien_thuc_nhan = so_tien = tổng snapshot booking; tổng bằng tiền sân +dịch vụ.
- Scope theo co_so/cong_ty, thời gian ghi nhận tiền; không JOIN trực tiếp raw court/service lines làm nhân tổng.

Đã gọi model Dashboard thật trên connection READ ONLY. Tháng11/2026:

| Scope | Booking doanh thu hợp lệ | Tiền sân | Dịch vụ | Tổng doanh thu |
|---|---:|---:|---:|---:|
| Toàn hệ thống | 5 | 610.000 | 115.000 | **725.000 đ** |
| Công ty3 | 1 | 110.000 | 20.000 | **130.000 đ** |
| Cơ sở6 thuộc công ty3 | 1 | 110.000 | 20.000 | **130.000 đ** |

IDs trong transactions đã ghi nhận là98,96,94,92,91; **không có #100**. Tổng lifetime ở các scope trên cũng bằng tổng tháng tại thời điểm kiểm tra. Không phát hiện đếm trùng trong các query này. Hai khoản CAN_DOI_SOAT, tổng thực nhận210.000đ, được reviewFunds() báo riêng, không cộng vào doanh thu đã thu.

### 5.2. KPI số booking: có lệch thực tế

Dashboard::bookingStates() (84–91) GROUP BY raw d.trang_thai_dat_san, recent() (124–132) cũng trả raw; DashboardService.php:84–85 dùng số đó; view partial dashboard.php:39–40 hiển thị KPI Chờ thanh toán và Đã hủy/Hết hạn. Khác với admin/history dùng effective.

Khoảng01/11/2026 00:00 đến trước01/12/2026:

| Scope | Chờ thanh toán raw | Theo effective hiện tại | Hết hạn raw | Theo effective hiện tại |
|---|---:|---:|---:|---:|
| Tập đoàn | **16** | **2** | **4** | **18** |
| Công ty3 | **2** | **0** | **0** | **2** |
| Cơ sở6 | **2** | **0** | **0** | **2** |

Tổng booking trong kỳ của tập đoàn vẫn72; sai phân bổ trạng thái, không phải phát sinh booking mới hoặc sai tổng doanh thu. Hai effective pending của toàn hệ thống là legacy receipt cần đối soát (#84/#86), **không đồng nghĩa có2khách còn thời hạn chuyển tiền6phút**. Không tự chuyển chúng sang hết hạn để ép số liệu về0.

Đây là tác động có bằng chứng ở query/model được controller/view thực sự dùng, không chỉ nguy cơ giả định. Chưa đăng nhập tài khoản thật để chụp dashboard live; số liệu lấy trực tiếp từ model READ ONLY.

### 5.3. Giao dịch pending và tiền pending

Toàn thời gian tại snapshot có26payment raw CHO_THANH_TOAN, tổng2.865.000đ. Phân rã:

| Booking hiệu lực của payment pending | Số payment | Số tiền | Ý nghĩa |
|---|---:|---:|---|
| HET_HAN | **12** | **1.320.000 đ** | Không còn là yêu cầu chuyển tiền đang còn hạn |
| CHO_XAC_NHAN | 12 | 1.345.000 đ | Đã nhận receipt, payment pending đúng nghiệp vụ trước xác minh |
| CHO_THANH_TOAN được bảo vệ legacy | 2 | 200.000 đ | Cần đối soát #84/#86 |
| Tổng | 26 | 2.865.000 đ | Không được gọi toàn bộ là “khách còn hạn chưa chuyển tiền” |

Query đếm/tổng pending trên chỉ là **diagnostic SQL** để đánh giá dữ liệu. Không tìm thấy KPI hiện hữu “Tổng tiền chờ thanh toán” dùng SUM(payment.so_tien WHERE pending) trong source đã kiểm; không khẳng định một widget chưa có đang báo sai tiền. Nếu hệ thống/báo cáo ngoài source chỉ lọc payment pending thì sẽ tính cả1.320.000đ projected expired nêu trên.

Ảnh hưởng nằm trong scope hiện có, không có bằng chứng rò rỉ chéo công ty:

| Công ty / cơ sở | Payment pending nhưng booking projected expired | Tiền |
|---|---:|---:|
| CT2 / CS1 | 1 | 270.000 đ |
| CT2 / CS2 | 2 | 140.000 đ |
| CT3 / CS6 | 1 | 110.000 đ |
| CT5 / CS13 | 8 | 800.000 đ |

Đây là số toàn thời gian, không trộn với bảng KPI tháng ở trên.

### 5.4. Bộ lọc quản trị bỏ sót

AdminBooking::page():59–62 đã xét effective booking khi lọc payment CHO_THANH_TOAN, nên #100 không xuất hiện ở đó. Nhưng nhánh payment HET_HAN dùng tt.trang_thai raw, nên #100 cũng **không xuất hiện ở filter payment HET_HAN** trước cleanup. Filter booking HET_HAN vẫn có thể tìm được #100. Đã xác minh query thật với q=100 và tái hiện riêng trong DB test.

Không sửa UI để che khác biệt; nếu được phép, nên thống nhất dữ liệu đọc/filter backend.

## 6. Trạng thái và tài nguyên trong các trường hợp đặc biệt

- **CHO_THANH_TOAN còn hạn:** giữ lịch, giữ tồn suy ra; không reset hạn khi refresh.
- **Pending quá hạn, không receipt:** effective expired và availability/service reservation đã giải phóng theo deadline, kể cả trước khi job ghi trạng thái. Job ghi cả booking/payment HET_HAN; không tạo invoice, không trừ hay cộng tồn vật lý.
- **CHO_XAC_NHAN:** deadline6phút cũ không làm expire; vẫn giữ lịch và tồn dịch vụ cho đến thao tác quản lý hợp lệ. Payment pending là đúng, không tự đánh dấu paid.
- **Payment THANH_CONG:** syncEndedPayment không đổi. Pending booking kèm payment thành công được bảo vệ để đối soát, không tự tạo xác nhận/invoice từ job.
- **CAN_DOI_SOAT không receipt:** payment được giữ CAN_DOI_SOAT; booking pending vẫn có thể hết hạn và nhường lịch theo quy tắc hiện tại. Không nhầm “giữ tiền cần đối soát” với “giữ sân vô hạn”.
- **Legacy receipt khi booking raw pending:** cleanup guard bảo vệ bản ghi, không tự chuyển review, không tự giữ lại lịch đã qua deadline hoặc xác nhận tiền. #84/#86 cần xử lý riêng như các báo cáo trước.
- **DA_HUY:** thao tác hủy hợp lệ đồng bộ payment HUY nếu còn pending, không đổi khoản đã thành công/đối soát ngoài workflow hiện hữu.

## 7. Race condition và bằng chứng runtime

Đã tái hiện **HTTP multipart upload thật** đối đầu **process bulk expiry riêng**, cùng PHP entry và schema hiện tại trong database test độc lập. Để tạo interleaving xác định, chỉ DB test có trigger SLEEP tạm theo đúng booking ID; không thêm trigger/schema/code vào DB ứng dụng. Không sửa đồng hồ hệ thống hoặc giả lập thanh toán thành công qua frontend.

| Interleaving | Bằng chứng/kết quả |
|---|---|
| Receipt UPDATE review hợp lệ trước deadline, commit bị trì hoãn qua deadline; job chọn raw pending cũ và chờ lock | Upload303 đến result; job expire0; sau commit booking CHO_XAC_NHAN, payment pending, receipt tồn tại, giữ deadline gốc và giữ lịch |
| Job UPDATE expired giữ lock trước khi late upload đến | Upload chờ rồi bị từ chối/303 vềwait; cuối cùng HET_HAN/HET_HAN, không receipt/invoice, không phục hồi sân |
| Đã nhận file nhưng ghi metadata bị trì hoãn qua deadline trước final DB-clock check | Upload rollback receipt metadata, booking/payment sau job HET_HAN/HET_HAN; không xác nhận tiền/trừ tồn/invoice |
| Nhân viên duyệt review trong khi job chạy | Review không thuộc tập auto-expire; cuối cùng DA_XAC_NHAN/THANH_CONG, đúng1invoice và trừ quantity2 đúng1lần |
| Ghi nhận tiền đến muộn sau HET_HAN/HET_HAN | markNeedsReconciliation thành công, payment CAN_DOI_SOAT; booking vẫn HET_HAN, lịch free, không invoice/xuất tồn, doanh thu đã thu không tăng |

Trong ca trì hoãn metadata, trigger nằm ở UPDATE payment sau lưu file và trước check DB clock cuối; đây là mô phỏng độ trễ trong transaction, không tuyên bố đã làm chậm thiết bị lưu trữ thật. Gate PROCESSLIST xác nhận các process overlap, không chỉ chạy tuần tự rồi gọi là concurrency.

Không thấy race làm review bị expire hoặc payment thành công bị đổi expired trong các ca đã chạy. Không suy ra mọi tải/interleaving có thể xảy ra đều đã được vét cạn. Batch CLI đặt innodb_lock_wait_timeout=5; nếu chờ quá lâu có thể rollback/báo lỗi và cần lần job sau, không được giải quyết bằng nới quyền/đổi trạng thái giả.

## 8. Kiểm thử — kết quả và giới hạn

| Nhóm | Kết quả lần này |
|---|---|
| Harness độc lập MariaDB +HTTP +process concurrency | **29 PASS /3 FAIL**, completed=true |
| tests/booking-expiry-integration.php --run | **33 PASS**; gồm exact cutoff, transaction lock, CLI trỏ DB riêng, batch/idempotency |
| tests/high-bug-fixes-integration.php --run --only=BUG-004 | **13 PASS**; receipt HTTP, duplicate, deadline, review1ngày, approval/invoice/stock, late upload |
| tests/booking-history.php | **48 PASS**, SQLite memory |
| node tests/payment-state.cjs | **PASS**, nhóm VM polling/expiry guards, không coi là browser thật |
| PHP lint9file model/service/CLI liên quan | **9/9 PASS** |
| Dashboard unit legacy | **BLOCKED ở preflight fixture** đã biết: main schema có invoice columns, fixture ALTER trùng. Không chạy lại/sửa fixture để làm xanh |
| Stress tải lớn, provider ngân hàng thật, mọi browser/thiết bị | **NOT RUN** |

Trong4nhóm có số assertion cụ thể: **126checks =123PASS +3FAIL**. Không cộng VM/lint vào số assertion đó. Ba FAIL là yêu cầu nhất quán được tái hiện có chủ đích, không phải lỗi harness:

1. Admin read trước cleanup mong HET_HAN/HET_HAN, thực tế HET_HAN/CHO_THANH_TOAN.
2. Dashboard pending mong1(còn hạn), thực tế2(vẫn đếm đơn quá hạn trong fixture).
3. Filter expired-payment mong tìm được projected-expired booking, thực tế0 trước cleanup.

Ma trận14trường hợp yêu cầu:

| STT | Ca | Kết quả |
|---|---|---|
| 1 | Booking mới/payment pending | PASS |
| 2 | Chưa receipt quá6phút | PASS cho job ghi cảhai; FAIL read consistency trước job |
| 3 | Gửi receipt hợp lệ trước hạn | PASS HTTP thật |
| 4 | Review qua deadline/mộtngày | PASS, vẫn review/giữ lịch |
| 5 | Xác nhận thanh toán | PASS, confirmed/paid/invoice/stock đúng |
| 6 | Hủy hợp lệ | PASS, DA_HUY/HUY |
| 7 | Hết hạn lặp | PASS, không ghi/trừ tồn lặp |
| 8 | Job đối đầu upload | PASS cả hai thứ tự lock và quá cutoff lúc ghi metadata |
| 9 | Job đối đầu xác nhận | PASS actual manual approval process; callback trong suite33chỉ contract simulator |
| 10 | Nhiều sân/dịch vụ | PASS, fixture2sân+dịch vụquantity2 |
| 11 | Giải phóng giữ khi expired | PASS, grid free/derived reservation giảm, tồn vật lý giữ nguyên |
| 12 | Không invoice/paid khi hết hạn | PASS |
| 13 | Payment thành công không bị expired | PASS, kể cả pendingbooking anomaly |
| 14 | CAN_DOI_SOAT/legacy receipt | PASS theo phân biệt tại mục6; không tự phá thông tin đối soát |

## 9. Phân loại mức độ

| ID audit | Vấn đề | Severity | Priority | Bằng chứng |
|---|---|---|---|---|
| EXP-01 | Booking projection và payment raw không cùng trạng thái trước cleanup | **MEDIUM** | HIGH | Live #100 +API/admin query +HTTP test |
| EXP-02 | KPI/recent booking của dashboard dùng raw, lệch effective history/admin | **MEDIUM** | HIGH | Model thật ROOT/CT/CS +fixture FAIL |
| EXP-03 | Filter payment HET_HAN bỏ sót projected-expired | **MEDIUM** | MEDIUM | Query #100 và fixture FAIL |
| OPS-EXP | Chưa chứng minh scheduler hợp lệ đang materialize expiry trên máy kiểm tra | **LOW** theo tác động đã chứng minh | HIGH | Không task phù hợp; backlog raw; script test DB hoạt động |

Lý do MEDIUM: sai read-state/phân loại thống kê vận hành và có đường thay thế (job/POST/filter booking), chưa thấy sai doanh thu, mất tiền, lộ quyền, doublebooking hoặc corruption invoice. Không nâng CRITICAL/HIGH nếu thiếu bằng chứng tác động đó. Priority cao vì người quản lý đang nhận thông tin không nhất quán và quá hạn chưa được dọn bền vững.

Không sửa các phát hiện này trong audit. Không đánh giá lại hay sửa BUG-007 hoặc những lỗi ngoài phạm vi.

## 10. Phương án tối thiểu đề xuất — CHƯA THỰC HIỆN

### 10.1. Bước vận hành bắt buộc: dùng writer đã có

Cấu hình/xác minh scheduler định kỳ trên đúng PHP, working directory, DB/env; chạy bin/expire-bookings.php --run theo hướng dẫn sau khi được phê duyệt. Theo dõi event failed/completed, protected_sample, backlog/more_pending và overlap_skipped; không lấy log job DB test làm chứng cứ scheduler production đã chạy.

Writer hiện có đã cập nhật booking/payment cùng transaction. **Không cần sửa lại expireOne()/syncEndedPayment()/BookingService::expirePending() chỉ để thêm UPDATE payment trùng chức năng.** Không gọi owned()/visit() hoặc job ghi dữ liệu từ GET để che thiếu tác vụ nền. Nếu cần xử lý cả pending hiện có, áp dụng đúng eligibility của job và bảo vệ legacy, không bulk UPDATE trực tiếp.

### 10.2. Thống nhất read projection trong khoảng giữa hai lượt job

Nếu được phê duyệt, dùng một quy tắc backend chung cho payment hiệu lực: chỉ suy ra HET_HAN khi payment raw còn pending, booking thực sự/hiệu lực đã hết hạn và không có receipt/tiền được bảo vệ cần đối soát. Không áp dụng cho booking CHO_XAC_NHAN, payment THANH_CONG/CAN_DOI_SOAT hoặc các anomaly legacy. Giữ raw state cho audit nội bộ; không thêm field/đổi API contract tùy tiện.

Đồng bộ SELECT/filter/count của admin và API cùng định nghĩa. Không chỉ đổi nhãn view/CSS. Dashboard bookingStates()/recent() cần dùng cùng effective booking để không phụ thuộc job đã chạy hay chưa. Khoảng ngày và scope vẫn theo convention hiện tại. Revenue()/paid()/reviewFunds() giữ nguyên điều kiện tài chính đã kiểm chứng.

### 10.3. File/hàm nào thật sự cần cân nhắc sửa sau phê duyệt?

| File/hàm | Mức cần thiết | Nội dung đề xuất |
|---|---|---|
| Cấu hình scheduler ngoài source | **Bắt buộc xác minh/thiết lập** | Dùng bin/expire-bookings.php hiện có; không đổi deadline |
| app/models/Dashboard.php — bookingStates(), recent() | **Cần cho EXP-02** | Dùng effective booking thay raw trong KPI/recent, giữ nguyên scope/ngày/revenue |
| app/models/AdminBooking.php — page(), find() | **Cần cho EXP-01/03 nếu muốn nhất quán ngay trước cleanup** | Payment projection/filter dùng cùng điều kiện expiry an toàn |
| app/models/Booking.php — helper projection dùng chung | Có thể cần để tránh nhân đôi quy tắc | Bổ sung helper đọc an toàn hoặc tham số alias; không rewrite expiry writer đang đúng |
| app/controllers/BookingController.php — waiting() nhánh status1 | Cần nếu chuẩn hóa API tương tự admin | Trả payment status hiệu lực từ read-model chung, giữ quyền/contract |
| app/controllers/PaymentController.php — status() | Cần nếu chuẩn hóa API | Cùng payment read rule, không đổi xác minh/payment writes |
| Test tương ứng | Bắt buộc khi sửa | Reproduce3FAIL →PASS, giữ đầy đủ transaction/late-money/review guards |

**Không cần sửa trong phương án này:** history view/controller hiện đã dùng effective booking; BookingReceiptService upload, PaymentService tạo/get payment, BookingManagementService xác nhận/late reconcile, InvoiceService, stock/pricing, schema/migration, HTML/CSS/JS. Chỉ cân nhắc thêm khi có test chứng minh một nguyên nhân khác và được duyệt.

### 10.4. Dữ liệu cũ và thanh toán đến muộn

- #100 hiện không receipt/paid/invoice: về điều kiện hiện có là ứng viên cleanup chuẩn, không cần tự xác nhận hoặc repair thủ công sang review. **Lượt audit không chạy cleanup trên DB thật**.
- Chưa thấy raw HET_HAN + payment pending trong live; không có cơ sở UPDATE hàng loạt các cặp giả định.
- #84/#86 vẫn pending với receipt legacy (và #86 có cột ảnh mới); cần đối soát riêng, không auto expire/approve/giữ lại sân. Không sửa hai đơn này.
- Các khoản CAN_DOI_SOAT/paid trên booking bất thường phải theo workflow tài chính riêng; không overwrite để khớp màu giao diện.
- Sau payment HET_HAN, **vẫn giữ nguyên row/reference/amount/ảnh/ghi chú** để đối soát tiền muộn; không xóa payment. BookingManagementService::actions() cho ghi nhận đối soát với expired/HET_HAN, markNeedsReconciliation() chuyển tiền sang CAN_DOI_SOAT và không phục hồi booking/sân.
- Test thực tế đã xác nhận nhánh late-money còn hoạt động sau đồng bộ payment HET_HAN; invoice/revenue không tự tăng. Không nới confirm/lockPayable cho booking expired chỉ để nhận tiền muộn.

## 11. An toàn môi trường và artifact

DB ứng dụng chỉ SELECT/information_schema/model đọc, dùng transaction READ ONLY rồi rollback. Không gọi expirePending()/owned()/job hoặc POST ghi lên DB thật. Không đăng nhập/đổi mật khẩu tài khoản thật.

DB độc lập của harness concurrency: **stryke_expiraudit_test_3a3d1c7649**, đã dọn cùng trigger test (report completed=true, cleanup.removed). Suite receipt dùng **stryke_highfix_test_8ea516bd33**, đã dọn. Suite expiry tự tạo stryke_expiry_test_* và dọn theo ownership. Không còn DB prefix expiraudit của lượt này. Chỉ dọn proof/receipt test được sở hữu; không xóa file thật/DB không rõ chủ.

Artifact:

- storage/temp/expiry-state-audit-ja7O6k/report.json:32checks, snapshots,3FAIL và race outcomes.
- storage/temp/expiry-state-audit-ja7O6k/server.log: HTTP server test, không phải log production.
- storage/temp/high-bug-fixes-5b50bfc8aa/: receipt integration13checks.
- storage/temp/expiry-state-audit-ja7O6k/audit-evidence.log: dữ liệu đã lược PII, output tests và fingerprint.

Không sửa test suite cũ; harness chạy stdin/bộ nhớ. Không cài dependency. Đã kiểm checksum **332 file source/schema/config/tests/assets** trước/sau, giữ nguyên:

```text
NKU4BbjU+wF26HvOMRH+HPxPAIwSHHiR7W8mOJO11Is=   (SHA-256 aggregate, Base64)
```

Chỉ thêm báo cáo này và artifact tạm được phép. Các file dirty của nhóm, gồm ba invoice fixes trước, được giữ nguyên. Không commit/push/merge.

## 12. Trả lời trực tiếp A–H

**A. Có đúng booking hết hạn nhưng payment pending?** Có ở trạng thái **hiệu lực/hiển thị** của #100; raw database cảhai vẫn pending. Chưa tìm thấy raw HET_HAN + raw pending trong snapshot. Job thực sự chạy sẽ đồng bộ cảhai nếu đủ điều kiện.

**B. Có thực sự sai thống kê?** Có sai KPI phân bổ trạng thái booking trên dashboard/recent và bỏ sót filter payment expired trước cleanup. Chưa phát hiện sai doanh thu đã thu. Tổng tiền pending2.865.000đ là diagnostic, không khẳng định có widget pending-money sai nếu source chưa có.

**C. Có thể đồng bộ payment HET_HAN an toàn?** Có; writer hiện tại đã làm trong transaction và các test đã đạt. Cần tác vụ bền vững và read consistency, giữ nguyên exceptions receipt/review/success/reconciliation.

**D. Cần sửa chính xác gì?** Trước hết cấu hình scheduler. Với lỗi đọc/KPI: Dashboard::bookingStates/recent, AdminBooking::page/find; helper read chung và hai status API nếu yêu cầu nhất quán ngay trước cleanup. Không cần rewrite writer hoặc sửa UI; chi tiết mục10.3.

**E. Cần đổi schema?** Không. ENUM payment đã có HET_HAN/HUY/CAN_DOI_SOAT và các quan hệ cần thiết.

**F. Có dữ liệu cũ cần đối soát?** Có #84/#86 và các bất thường tài chính được bảo vệ. Không tự sửa; #100 là pending quá hạn chưa receipt đủ điều kiện cleanup chuẩn sau phê duyệt.

**G. Phương án an toàn nhất?** Bảo toàn transaction/locks/late-money workflow đã chạy đúng; vận hành job có giám sát; thống nhất projection/filter/KPI đọc ở backend. Không UPDATE từ GET, không đổi màu để che dữ liệu, không tự reset deadline/khôi phục expired.

**H. Regression bắt buộc trước áp dụng nếu được duyệt:**

1. Ba test FAIL read/admin-filter/dashboard phải tái hiện trước sửa và PASS sau sửa.
2. Pending trước/đúng/sau deadline; history/count/filter và API dùng cùng cutoff; job/POST đồng bộ cảhai.
3. Receipt hợp lệ/duplicate/legacy, review sau1ngày vẫn giữ lịch/tồn, không paid tự động.
4. Upload/expiry hai thứ tự lock, quá hạn trong metadata/file step, approval/expiry và idempotency.
5. Payment thành công/CAN_DOI_SOAT không bị overwrite, late-money từ HET_HAN vẫn đối soát được mà không giữ lại sân.
6. Multicourt/service, không trừ/hoàn tồn hai lần, không invoice cho expired; amount/reference/snapshot không đổi.
7. Dashboard raw vs effective và scope ROOT/CT/CS, khoảng ngày, doanh thu725.000đ snapshot live chỉ để đối chiếu đọc; fixture độc lập chứng minh pending/expired không vào revenue.
8. Scheduler đúng môi trường, không chồng job, timeout/retry/log/backlog/protected_sample, không visitor vẫn chạy; browser/API regressions và quyền/CSRF không đổi.

**Dừng ở báo cáo. Chưa thực hiện bất kỳ phương án sửa code/cấu hình/dữ liệu thật nào.**

