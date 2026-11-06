# STRYKE — Đánh giá an toàn thay đổi đồng bộ hết hạn Booking / Payment

Ngày: **06/11/2026**, timezone **Asia/Ho_Chi_Minh (+07:00)**. Branch **kiem-thu-3**, HEAD **55623473790089f8229a2bda2b9533f68966f5c5**.

**Chỉ phân tích, đọc source/dữ liệu, kiểm thử trên DB riêng và lập báo cáo. Chưa sửa code, cấu hình scheduler hay database ứng dụng.**

## 1. Kết luận và khuyến nghị

**Nên cải thiện theo phương án3: scheduler hiện có + read-model hiệu lực thống nhất, giới hạn vào booking chưa nhận minh chứng/tiền hợp lệ và thực sự quá hạn.** Không cần viết lại writer hết hạn, upload, xác nhận, từ chối, hủy, stock, hóa đơn hoặc công thức doanh thu.

Writer Booking::expireOne()/syncEndedPayment() hiện đã ghi booking/payment hết hạn cùng transaction. Phần còn thiếu là vận hành scheduler được xác minh và thống nhất SELECT/filter/KPI trước khi job commit. Đây là cải thiện có thể làm với phạm vi nhỏ, nhưng **chưa thể khẳng định bản sửa dự kiến đã an toàn ở runtime vì chưa được triển khai**. Bản sửa phải vượt lại các ca bảo toàn trong báo cáo, không chỉ làm3ca FAIL chuyển xanh.

Không cần migration cho mục tiêu A: payment đã có HET_HAN. Có một khác biệt cần xác nhận về cách diễn đạt mục tiêu B: **payment raw trong schema hiện không có CHO_XAC_NHAN**. Luồng đang ổn là booking raw CHO_XAC_NHAN, payment raw CHO_THANH_TOAN và admin hiển thị alias “Chờ xác nhận”. Phương án đề xuất **giữ nguyên luồng này**, không thêm trạng thái, không sửa dữ liệu hay mở rộng scope sang B.

Với scheduler mỗi phút, có thể bảo đảm hiển thị hết hạn ngay theo cutoff, còn database được ghi tại lượt job thành công tiếp theo. **Không thể cam kết commit DB đúng giây360 với một job chạy mỗi phút**, nhất là khi máy ngừng/SQL lỗi/backlog. Đây là giới hạn vận hành cần chấp nhận khi duyệt phương án, không nên dùng UPDATE từ GET để che khoảng trễ.

## 2. Đối chiếu báo cáo trước với source/schema hiện tại

Đã đọc reports/STRYKE_BOOKING_PAYMENT_EXPIRY_AUDIT.md và kiểm tra lại các model/service/controller/routes/view liên quan. Fingerprint332file source/schema/config/test/assets hiện tại giống lúc audit trước: NKU4BbjU+wF26HvOMRH+HPxPAIwSHHiR7W8mOJO11Is=. Không giả định dữ liệu không đổi chỉ vì source không đổi.

### 2.1. Dữ liệu thật — READ ONLY

Snapshot lúc **15:51:17**, kiểm tra query thống kê lúc **16:00:28 +07:00**:

| Nhận định cũ | Kiểm chứng lại |
|---|---|
| #100 raw booking/payment đều pending | Vẫn CHO_THANH_TOAN/CHO_THANH_TOAN; deadline06/11 14:48:28, tạo14:42:28 |
| #100 hiển thị booking hết hạn | AdminBooking::find trả HET_HAN cho booking, payment vẫn CHO_THANH_TOAN |
| Không receipt/paid/invoice #100 | Không ảnh modern/legacy, không thời điểm/mã giao dịch xác minh, không hóa đơn; tổng110.000đ |
| Writer đồng bộ cùng transaction | Source vẫn khóa booking→payment, UPDATE booking có điều kiện, sync payment rồi commit; tests DB riêng đạt |
| Có3FAIL read/KPI/filter | Tái hiện lại đúng3FAIL, không sửa source/test suite để làm xanh |
| Chưa thấy scheduler | Kiểm tra lại Windows task matching: không có; DB EVENT0, trigger booking/payment0 |
| Không phát hiện sai doanh thu | Model Dashboard thật vẫn5booking hợp lệ, tổng725.000đ; #100 không được tính |

Dữ liệu hiện có101booking,66payment,5invoice; báo cáo trước có100/65/5. Hiện có13booking review +payment pending, tổng1.460.000đ, thay vì12ở snapshot trước. Đây là thay đổi dữ liệu sử dụng website giữa hai lượt, không phải dữ liệu do audit tạo trong DB ứng dụng. Cặp trạng thái mới vẫn phù hợp luồng B hiện hành.

Tháng11/2026, chạy trực tiếp model đọc trên DB ứng dụng trong transaction READ ONLY:

| Scope | KPI pending raw →effective | Expired raw →effective | Doanh thu đã thu |
|---|---:|---:|---:|
| Tập đoàn | 16 →2 | 4 →18 | 725.000đ /5booking |
| Công ty3 | 2 →0 | 0 →2 | 130.000đ /1booking |
| Cơ sở6 | 2 →0 | 0 →2 | 130.000đ /1booking |

Hai effective pending còn lại là legacy #84/#86 cần đối soát, không được coi là hai khách vẫn còn thời hạn6phút. Admin history đã dùng effective booking; lịch sử khách hàng lấy badge booking, không phải badge payment. Chưa có bằng chứng current /booking/history của #100 ghi Chờ thanh toán; dashboard “Booking gần đây” dùng raw là một nguồn nhãn khác.

Toàn thời gian có12payment pending nhưng booking projected expired, tổng1.320.000đ. Không tìm thấy raw booking HET_HAN +raw payment pending ở snapshot; đây chủ yếu là chênh lệch projection trước cleanup. Tổng pending raw hiện27payment/2.980.000đ còn bao gồm13review và2legacy, không được gọi tất cả là khách còn hạn chưa chuyển khoản.

Không phát hiện payment pending có số tiền thực nhận dương/ngày thanh toán/mã giao dịch đã ghi nhận trong scan bất thường lần này. Điều này không bảo đảm dữ liệu bên ngoài/import tương lai cũng không có bất thường. Không xuất tên, điện thoại, thông tin ngân hàng, token, password hay nội dung receipt của khách vào báo cáo.

### 2.2. Schema thực tế

```text
Booking: CHO_XAC_NHAN | CHO_THANH_TOAN | DA_XAC_NHAN | HOAN_THANH | HET_HAN | DA_HUY
Payment: CHO_THANH_TOAN | THANH_CONG | HET_HAN | HUY | CAN_DOI_SOAT
```

dat_san, thanh_toan, hoa_don, dich_vu đều InnoDB. Payment có UNIQUE ma_dat_san và tham chiếu unique; invoice có unique liên kết. Không có TU_CHOI trong schema và không có endpoint từ chối riêng sử dụng trạng thái mới.

**Mục tiêu A phù hợp schema/nghiệp vụ hiện tại. Mục tiêu B nếu hiểu là nhãn hiển thị thì phù hợp; nếu bắt buộc lưu literal payment CHO_XAC_NHAN vào DB thì không phù hợp enum hiện tại và trở thành một yêu cầu migration/workflow riêng.** Vì B đã được người dùng xác nhận ổn, không đề xuất thay đổi phần đó trong phương án A.

## 3. Contract trạng thái phải giữ nguyên

Quy tắc dưới đây là contract của source hiện tại, không phải thiết kế mới:

- Booking::insert() (Booking.php:213–230) chốt deadline từ giờ server ngay trước commit chi tiết; không reset khi GET/refresh.
- Booking::expire()/expireOne()/syncEndedPayment() (233/273/166): batch chọn pending đến hạn, không protected; khóa booking và payment; đồng bộ pending payment sang HET_HAN khi booking đã hết hạn. Hủy hợp lệ dùng HUY.
- unprotectedSql() (Booking.php:20) bảo vệ payment thành công hoặc đường dẫn minh chứng modern/legacy. Không phải hàm tự xác minh tiền hoặc phục hồi lịch.
- BookingReceiptService::upload() (59–113): khóa, kiểm owner/deadline, kiểm lại DB clock sau lưu metadata; review không phụ thuộc deadline cũ; duplicate không thay ảnh.
- BookingManagementService::finalizeBookingAndPayment() (227): kiểm scope, trạng thái/receipt/money, khóa tài nguyên và xác nhận/payment/stock/invoice nguyên tử.
- markNeedsReconciliation() (551): ghi tiền cần xử lý riêng, có thể dùng cho đơn expired/canceled theo điều kiện; không tự giữ lại sân.
- cancelBookingByAdmin() (671): chính là đường “từ chối/hủy” có lý do; không tự hoàn tiền.
- BookingService::cancelHold() (190) và Booking::cancelPending() (152): khách chỉ hủy được pending chưa có receipt/success theo điều kiện; review không bị hủy bởi nhánh này.
- Dashboard::paid()/revenue()/reviewFunds() (44/68/93): doanh thu thành công tách khỏi tiền cần xử lý; không thay đổi chỉ để đồng bộ status.

## 4. Ma trận trạng thái trước/sau và ảnh hưởng dự kiến

Payment trong bảng là **raw persisted status**, không phải alias “Chờ xác nhận” ở admin. “Không đổi” nghĩa không đổi bởi scheduler/read-model dự kiến; thao tác con người hợp lệ vẫn có chuyển trạng thái riêng.

| # | Booking trước | Payment trước | Sự kiện | Booking sau | Payment sau | Ảnh hưởng dự kiến / rủi ro |
|---|---|---|---|---|---|---|
| 1 | CHO_THANH_TOAN | CHO_THANH_TOAN | Chưa đến deadline | CHO_THANH_TOAN | CHO_THANH_TOAN | Không đổi; thấp nếu dùng DB clock đúng |
| 2 | CHO_THANH_TOAN | CHO_THANH_TOAN | Đến/quá deadline, không receipt/tiền protected | HET_HAN | HET_HAN | **Mục tiêu sửa**; trung bình vì cần read/count/filter cùng cutoff và job ghi thật |
| 3 | CHO_THANH_TOAN | CHO_THANH_TOAN | Receipt hợp lệ được chấp nhận trước hạn | CHO_XAC_NHAN | CHO_THANH_TOAN | Không đổi; hậu quả cao nếu helper chỉ xét deadline mà quên booking review |
| 4 | CHO_XAC_NHAN | CHO_THANH_TOAN | Qua deadline cũ/1ngày, job chạy | CHO_XAC_NHAN | CHO_THANH_TOAN | Không đổi; vẫn chiếm sân/giữ tồn. Không ghi payment HET_HAN |
| 5 | CHO_XAC_NHAN | CHO_THANH_TOAN | Nhân viên xác minh/duyệt hợp lệ | DA_XAC_NHAN | THANH_CONG | Không đổi; rủi ro tài chính cao nếu chạm writer/stock/invoice, nên giữ nguyên writer |
| 6 | CHO_XAC_NHAN | CHO_THANH_TOAN | Nhân viên từ chối với lý do, đúng scope | DA_HUY | HUY | Không đổi; giữ receipt/reference/amount, nhường sân, không refund/trừ tồn |
| 7 | CHO_THANH_TOAN | CHO_THANH_TOAN | Khách explicit cancel khi còn hạn, chưa receipt | DA_HUY | HUY | Không đổi; không biến thao tác rời trang thành hủy |
| 8 | CHO_THANH_TOAN | CHO_THANH_TOAN | Khách cancel sau cutoff | HET_HAN | HET_HAN | Giữ CASE hiện có; không reset hold |
| 9 | CHO_XAC_NHAN | CHO_THANH_TOAN | Khách gọi cancelHold | CHO_XAC_NHAN | CHO_THANH_TOAN | Không đổi/no-op đối với review; không mở thêm quyền hủy receipt |
| 10 | HOAN_THANH | THANH_CONG | Job hoặc yêu cầu từ chối | HOAN_THANH | THANH_CONG | Job bỏ qua; từ chối bị chặn. Không thêm auto-completion workflow |
| 11 | DA_XAC_NHAN | THANH_CONG | Job hoặc từ chối biên lai | DA_XAC_NHAN | THANH_CONG | Bỏ qua/chặn; cần quy trình hoàn tiền riêng hiện có, không refund giả |
| 12 | CHO_THANH_TOAN bất thường | THANH_CONG | Deadline qua, job chạy | Giữ CHO_THANH_TOAN | THANH_CONG | Bảo vệ để đối soát; không tự expire tiền hoặc approve booking |
| 13 | CHO_XAC_NHAN | CAN_DOI_SOAT | Qua deadline cũ | CHO_XAC_NHAN | CAN_DOI_SOAT | Không đổi, booking review vẫn chiếm lịch |
| 14 | CHO_THANH_TOAN, không receipt | CAN_DOI_SOAT | Deadline qua, job chạy | HET_HAN | CAN_DOI_SOAT | Chính sách hiện có: nhường hold nhưng giữ tiền đối soát; không sửa thành giữ sân vô hạn |
| 15 | HET_HAN | HET_HAN | Có tiền đến muộn, nhân viên ghi nhận hợp lệ | HET_HAN | CAN_DOI_SOAT | Không đổi; không phục hồi sân/invoice/revenue tự động |
| 16 | CHO_XAC_NHAN | CAN_DOI_SOAT | Admin hủy/từ chối theo quyền | DA_HUY | HUY | Không đổi: vẫn giữ số tiền/mã/ngày thực nhận và receipt, cần xử lý tài chính riêng |
| 17 | DA_HUY | HUY | Job chạy lặp | DA_HUY | HUY | Không đổi; không ghi đè HUY thành HET_HAN |
| 18 | CHO_THANH_TOAN legacy #84/#86 | CHO_THANH_TOAN, có receipt link | Job/read projection | Giữ trạng thái thô | Giữ pending | Đối soát thủ công; không phục hồi lịch đã nhường/auto approve |
| 19 | HET_HAN | CHO_THANH_TOAN do dữ liệu cũ giả định | Chưa repair | Không tự phục hồi | Cần kiểm tra riêng | Chưa thấy cặp raw này trong live; bulk không quét lại raw HET_HAN, không repair bừa |
| 20 | CHO_THANH_TOAN | Không có payment | Deadline qua | HET_HAN | Vẫn không có row | Không tạo payment giả để đủ cặp trạng thái |
| 21 | DA_HUY/HET_HAN bất thường | THANH_CONG | Job/read | Giữ trạng thái | THANH_CONG | Không overwrite tiền; tách đối soát/hoàn tiền, không tự tạo invoice mới |

**Lưu ý chính sách admin đã kiểm chứng:** service hủy hiện chấp nhận một lệnh admin có quyền/lý do trên booking HET_HAN chưa paid, và chuyển sang DA_HUY/HUY. UI có thể không cung cấp nút ở trạng thái đó; một form cũ/request trực tiếp vẫn phải qua service. Test riêng đã xác minh hành vi này. Đây là chuyển do lệnh hủy rõ ràng, không phải job tự biến expired thành canceled. Đánh giá này không đề nghị đổi chính sách đó.

Payment pending có tiền thực nhận/mã ngân hàng bất thường nhưng chưa receipt/success là trường hợp cần preflight thủ công: guard hiện tại không bảo vệ mọi dấu hiệu tài chính, chỉ các điều kiện đã nêu. Scan live lần này không có row như vậy. Không tự thêm ngoại lệ mới chỉ ở read-model khiến nó khác writer; nếu phát hiện dữ liệu này trước triển khai phải đối soát/quyết định riêng, không tự thay workflow ổn định.

## 5. Ảnh hưởng theo module

### 5.1. Booking, lịch và nhiều sân

Availability hiện đã dựa booking status và deadline: CourtScheduleService::build():34–42 giữ review không phụ thuộc6phút, giữ pending chỉ trước hạn; Booking::reserved():187–194 suy ra giữ tồn theo cùng nhóm trạng thái. Vì vậy pending quá hạn không còn chiếm lịch ngay cả khi raw row còn pending. Ghi payment HET_HAN **không phải thao tác giải phóng sân riêng**, không được thêm writer giải phóng/trừ/hoàn tồn nữa.

Đặt mới, kiểm overlap một phần, lock nhiều sân theo thứ tự và idempotency ở BookingService::confirm()/lockScope() phải giữ nguyên. Phương án read-only không thay đổi predicate occupancy hoặc khóa các writer. Không dùng trạng thái payment thay cho trạng thái booking để suy ra sân trống; payment CAN_DOI_SOAT/HUY còn có ý nghĩa tài chính khác.

Rủi ro chính là sửa quá rộng: deadline-only expiration cho mọi booking sẽ giải phóng review sai và có thể gây đặt trùng; hoặc raw helper bị thay trong writer sẽ từ chối một hold còn hợp lệ. Tránh bằng allowlist rõ ràng cho trường hợp A và kiểm lại lock/cutoff khi ghi.

### 5.2. Payment, QR và receipt

PaymentService::createPaymentRequest() kiểm booking status và deadline trước tạo/reuse; khi payment đã kết thúc/đối soát thì không tạo lại. Test reuse đạt: không đổi reference, deadline hoặc các row; retry expired bị từ chối và không reset hold. Đổi read-model không cần sửa PaymentService, ThanhToan::find*(), refreshPaymentRequest(), VietQRService hoặc QR endpoint.

Không đưa payment effective display vào điều kiện ghi/xác nhận: service upload/manual approval phải tiếp tục đọc raw row có khóa. Receipt hợp lệ, duplicate, cutoff, final DB-clock check và rollback ảnh/metadata hiện đang đạt; không thay chúng để “làm khớp nhãn”.

Không thay account ngân hàng, số tiền, mã tham chiếu, ảnh QR hoặc thời hạn. Chuyển payment HET_HAN không xóa row/reference, vì tiền ngân hàng có thể đến sau hạn.

### 5.3. Luồng từ chối/hủy — phân tích riêng

Đường thực tế: POST /admin/bookings/cancel → AdminBookingController::cancel() → BookingManagementService::cancelBookingByAdmin(). Bắt buộc lý do; kiểm role/scope phía service; khóa booking→payment. Với booking chưa xác nhận và payment chưa thành công, cập nhật DA_HUY/HUY, ghi lý do, giữ thông tin receipt theo cơ chế hiện có. Không có TU_CHOI/THAT_BAI mới và không có hoàn tiền tự động.

Test runtime trên DB riêng: từ chối review bởi nhân viên được phân công thành công; công ty ngoài scope403; thiếu lý do bị chặn; lặp bị chặn; receipt/reference/amount còn nguyên; sân về free, không invoice hoặc stock write. Job sau đó không sửa DA_HUY/HUY. Review đã đối soát rồi bị hủy vẫn giữ120.000đ thực nhận và transaction/date; Dashboard::reviewFunds() vẫn liệt kê khoản HUY trên booking hủy riêng với doanh thu.

Booking DA_XAC_NHAN/payment THANH_CONG và HOAN_THANH bị chặn khỏi thao tác từ chối này. Hash các bảng nghiệp vụ không đổi sau yêu cầu bị chặn. Không biến thao tác từ chối thành refund, không xóa chứng từ, không ép HUY sang HET_HAN.

### 5.4. Xác nhận và đối soát — phân tích riêng

Finalize vẫn kiểm scope/receipt version/tiền, khóa tài nguyên, kiểm overlap và chuyển stock/payment/booking/invoice trong một transaction. Read-model không được thay actions(), lockPayable(), requireConfirmation(), final conditional UPDATE hoặc các kiểm tra raw payment.

Late-money HET_HAN/HET_HAN →HET_HAN/CAN_DOI_SOAT đã chạy thực tế, không giữ lại sân hoặc tạo invoice/xuất tồn; doanh thu đã thu không tăng. Có thể ghi nhận tiền trên review thành CAN_DOI_SOAT và vẫn giữ review. Đây là hai trường hợp khác nhau; không gộp CAN_DOI_SOAT thành một “hold status” chung.

Rủi ro cao nếu thêm điều kiện “payment phải luôn CHO_THANH_TOAN khi booking expired” hoặc loại HET_HAN khỏi recordable/reconcile. Source hiện tại không giả định như vậy: actions() cho ghi nhận đối soát với payment HET_HAN/HUY; PaymentService cố ý không tạo lại yêu cầu hết hạn. Không cần mở expired trở lại pending hoặc dùng lockPayable() bằng trạng thái/giờ giả.

### 5.5. Hóa đơn, snapshot, tồn

Job chỉ đổi trạng thái; không gọi InvoiceService hoặc xuất tồn. Giữ tồn được suy ra, nên không “hoàn stock” khi job expired pending — làm vậy sẽ cộng tồn thừa. Khi duyệt hợp lệ, stock chỉ trừ một lần cùng payment/invoice transaction, giữ cơ chế rollback. InvoiceService chỉ tạo cho booking DA_XAC_NHAN/HOAN_THANH và payment THANH_CONG đúng booking.

Test fault injection riêng làm payment UPDATE lỗi sau khi booking bắt đầu chuyển hết hạn: cảhai raw trạng thái rollback về pending; bỏ fault rồi lượt job sau ghi cảhai HET_HAN, không invoice/stock mutation. Không có trạng thái nửa commit quan sát được trong ca này. Không thay tổng tiền hay giá snapshot ở bất kỳ bước nào.

### 5.6. Dashboard, filters và scope

AdminBooking::page()/find() đang dùng effective booking nhưng raw payment; filter payment pending xét effective booking, filter HET_HAN lại xét raw payment. Đây là nguồn bỏ sót trước cleanup. Dashboard::bookingStates()/recent() dùng raw; revenue()/paid() kiểm payment success +booking confirmed/completed +bằng chứng +khớp tiền.

Thống nhất read-model sẽ sửa phân bổ pending/expired và filter, không đổi tổng booking hoặc doanh thu. Count/list/filter/KPI cần cùng cutoff **từ DB server**, không lấy ngày chơi/ngày lọc làm thời điểm hết hạn. DashboardService đang có transaction read snapshot; bản sửa cần chốt cutoff một lần cho các query trạng thái trong request để tránh lệch COUNT/SELECT đúng sát deadline.

Giữ WHERE company/facility từ identity service, không nhận phạm vi đáng tin từ client. Role middleware các route admin vẫn ROOT/CT/CS/NV; source resolveScope() bảo toàn company/facility. Query mới không được JOIN raw chi tiết sân/dịch vụ làm nhân dòng tổng hoặc nới scope. Current revenue5booking/725.000đ và reviewFunds210.000đ tách biệt là đường chuẩn để regression, không sửa công thức để khớp một số KPI trạng thái.

## 6. So sánh ba phương án

| Tiêu chí | 1. Chỉ scheduler | 2. Chỉ read-model | 3. Kết hợp — đề xuất |
|---|---|---|---|
| DB thật chuyển booking/payment | Có, khi job thành công | **Không**, raw pending vẫn tồn tại | Có |
| Hiển thị ngay tại deadline | Chưa đồng nhất giữa hai lượt job | Có nếu mọi query dùng đúng rule | Có |
| Dashboard/filter trước cleanup | Có thể vẫn lệch trong khoảng trễ | Sửa được | Sửa được |
| Số file nghiệp vụ cần đổi | 0 nếu writer hiện tại giữ nguyên | Nhóm SELECT/helper/API | Cùng nhóm SELECT/helper/API; không rewrite writers |
| Job thất bại/máy tắt | Raw chậm và read vẫn có thể lệch | UI đúng nhưng DB không hội tụ | UI vẫn đúng; DB hội tụ khi job chạy lại |
| Đạt đủ mục tiêu A | Chưa trọn vẹn về hiển thị tức thời | Không đạt yêu cầu ghi DB | **Phù hợp nhất với thay đổi giới hạn** |
| Rủi ro chính | Sai env/quyền/log/backlog hoặc tưởng mọi nơi đã cùng trạng thái | Lạm dụng projection trong writer, sai ngoại lệ/clock/filter | Các rủi ro của2, cộng vận hành; kiểm soát được bằng scope và regression |

Phương án1 ít code nhất nhưng chưa giải quyết toàn bộ yêu cầu. Phương án3 **ít thay đổi nhất trong các phương án đáp ứng cả DB thật lẫn hiển thị/KPI**, với scheduler tận dụng code đã có và phần sửa chỉ ở đọc.

Không chọn phương án “expire từ GET” hoặc update hàng loạt mọi payment pending quá6phút: chúng làm thay đổi side effects của GET và đe dọa review/tiền đến muộn.

## 7. Scheduler — khả năng, giới hạn và phục hồi

| Kiểm tra | Source/bằng chứng | Đánh giá |
|---|---|---|
| Chạy mỗi phút | CLI opt-in bin/expire-bookings.php --run | Phù hợp; cần cấu hình ngoài request/web |
| Chống chạy trùng | Booking::expire():238–244 GET_LOCK0 theo hash DATABASE(), finally RELEASE_LOCK269 | Có, xuyên process/host dùng cùng DB; instance sau skip |
| Batch |200row/lần, tối đa10batch/lượt | Tối đa2.000 ứng viên/lượt trước giới hạn thời gian |
| Runtime/lock wait | Budget45giây trong loop, CLI innodb_lock_wait_timeout5 | Có giới hạn, nhưng45giây không phải hard wall-clock cho mọi SQL/startup/log |
| Quá backlog | more_pending và lần sau tiếp tục theo deadline/id | Có; không cần visitor |
| Legacy/paid anomaly | protected_sample tối đa100ID/lượt +log | Có bảo vệ; cần queue đối soát con người, không auto approve |
| Lỗi giữa transaction | Catch rollback current booking; CLI log failed/exit1 | Có; các booking đã commit trước đó không bị rollback lại |
| Retry | Lần scheduler tiếp theo chọn lại row còn eligible | Có theo lịch; không có retry/backoff tự chạy nếu OS không gọi lại |
| Máy restart/process chết | Connection đóng giải phóng lock, transaction chưa commit rollback; backlog vẫn nằm DB | Phân tích ACID/source; không reboot server thật trong audit |

Kiểm tra Windows task matching không có kết quả. DB ứng dụng có0EVENT và0trigger booking/payment. Không tạo/bật task. Không dùng log do test CLI tạo để kết luận scheduler DB thật đang chạy. Không loại trừ wrapper/cron/host ngoài máy kiểm tra chưa được nhận diện.

**Khoảng trễ:** nếu chạy đều mỗi60giây, ít tải và không lỗi, một booking chờ khoảng0–60giây tới lượt quét, cộng thời gian startup/batch/lock/commit. Có backlog, task bị bỏ qua, lock timeout, mất kết nối, máy ngủ/tắt hoặc lỗi lặp thì **không có cận trên tuyệt đối** từ code hiện tại. Một row lỗi lặp có thể trì hoãn xử lý phía sau; cần monitor failed/more_pending/protected_sample và số row overdue.

Không có bằng chứng cần sửa code job để đạt mục tiêu A trong điều kiện vận hành đã nêu. Sau phê duyệt cần xác minh PHP CLI, working directory, env/DB đích, quyền đọc config/ghi log, chạy khi không có phiên user, chính sách không tạo instance chồng và chạy bù sau missed start. Các thiết lập này chưa được thực hiện.

## 8. Thiết kế thay đổi giới hạn nếu được phê duyệt

### 8.1. Quy tắc an toàn

1. Writer hiện tại là nguồn cập nhật vật lý; không nhân đôi UPDATE payment hoặc stock.
2. Effective payment chỉ thành HET_HAN cho **raw pending đủ điều kiện hết hạn thuộc trường hợp A**, theo cùng rule deadline/protection với writer.
3. Không đổi review, confirmed, completed, success, canceled payment hoặc reconciliation thành expired chỉ vì deadline cũ qua.
4. Không sửa #84/#86 hoặc tự convert pending+receipt thành review. Raw terminal+pending/missing-payment/bất thường tài chính phải phân biệt, không fabricate row/state.
5. Không đưa display status vào luồng ghi: giữ raw ThanhToan/Booking reads cho upload/confirm/cancel/reconcile.
6. Giữ shape API/alias field hiện có; không thêm route/field, không đổi enum/nhãn/UI/CSS/JS. Chỉ giá trị expired đủ điều kiện được đọc nhất quán.
7. Chụp cutoff DB một lần/request cho COUNT/items/filter/KPI; giữ scope/date/sort/pagination và consistency snapshot.

### 8.2. Kế hoạch file/hàm cụ thể cho phương án3

Đề xuất phạm vi **6file PHP phục vụ đọc**, cùng cấu hình scheduler riêng sau phê duyệt:

| File | Phạm vi cần sửa | Phần trong cùng file phải giữ nguyên |
|---|---|---|
| app/models/Booking.php | Helper projection booking/payment dùng chung, nhận cutoff/alias an toàn theo thiết kế tối thiểu | insert, expire, expireOne, syncEndedPayment, cancelPending, reserved và mọi writer |
| app/models/AdminBooking.php | page()/find(): SELECT payment hiệu lực, filter/count cùng rule | WHERE scope, dữ liệu snapshot, queries receipt/stock |
| app/models/Dashboard.php | bookingStates()/recent(): dùng trạng thái hiệu lực/cutoff chung | paid, revenue, reviewFunds, transactions và định nghĩa tiền |
| app/services/DashboardService.php | data(): chốt/truyền cùng cutoff cho các query trạng thái | Scope, filters, read transaction, KPI tiền và tổng doanh thu |
| app/controllers/BookingController.php | waiting(): nhánh GET/status và dữ liệu trạng thái trình bày từ read-model chung | confirm, uploadReceipt, cancel, expire POST và xử lý thanh toán |
| app/controllers/PaymentController.php | status(): cùng payment effective rule | Authorization, show/QR/download, payment creation và response shape |

Không phải cứ sửa các file trên là tự được thay mọi hàm trong đó. Cần review diff theo đúng cột phạm vi. Helper mới nên tái sử dụng định nghĩa expire/protection hiện có, không tự tạo service/enum/workflow thứhai.

**Nên giữ nguyên tuyệt đối trong gói sửa này:** BookingReceiptService, BookingManagementService, BookingService, PaymentService, ThanhToan raw model, BookingInventoryService, BookingPricingService, InvoiceService/HoaDon, CourtScheduleService và occupancy queries, routes/middleware/Auth, schema/migrations, view/layout/CSS/JS. bin/expire-bookings.php và writer expiry chưa cần đổi code; chỉ vận hành/cấu hình được duyệt.

Nếu trong triển khai phát hiện thật sự cần đổi một writer, dừng phần đó và trình test tái hiện/ảnh hưởng trước, không mở scope chỉ để tiện dùng projection.

## 9. Rủi ro và điều kiện chấp nhận

| Nguy cơ | Tác động nếu làm sai | Kiểm soát bắt buộc |
|---|---|---|
| Deadline-only trên mọi trạng thái | Hết hạn review sai, giải phóng sân/tồn, mất khả năng duyệt | Allowlist A +tests review sau1ngày/receipt/paid |
| Projection rò vào writer | Từ chối/duyệt/hủy sai, sai transaction | Chỉ read boundary; raw locked model giữ nguyên |
| Overwrite THANH_CONG/CAN_DOI_SOAT/HUY | Sai đối soát tiền đến muộn/tiền đã nhận | Không ép terminal/financial states sang expired |
| Thêm hoàn tồn khi expire | Tồn tăng sai hoặc hoàn/trừ hai lần | Giữ reservation-derived model; không stock UPDATE ở job/read |
| Đổi từ chối thành trạng thái mới/refund | Mất tương thích schema và quy trình tài chính | DA_HUY/HUY hiện có, giữ actual funds/receipt, không refund giả |
| COUNT/SELECT dùng cutoff khác | Filter/count/pagination nhấp nháy ở deadline | Một cutoff DB/request; boundary tests |
| Sai scope/Join fan-out | Lộ dữ liệu/cộng trùng thống kê | Giữ WHERE identity, một payment/booking, không JOIN detail vào aggregate tiền |
| Scheduler nhầm DB/env hoặc không chạy | Ghi nhầm dữ liệu hoặc backlog raw kéo dài | Preflight DB đích, quyền/log/monitor trước kích hoạt |
| Sửa dữ liệu legacy bằng job bao quát | Chiếm lại lịch đã nhường, xác nhận tiền không có bằng chứng | Protected sample và manual reconciliation, không bulk repair |

Không thể tuyên bố “không có rủi ro”. Phạm vi hẹp giúp giảm khả năng regression; các nhánh xác nhận/từ chối/cancel có tác động tài chính cao nếu bị đụng vào, vì vậy cách an toàn là không chỉnh chúng và dùng regression để phát hiện ảnh hưởng gián tiếp.

## 10. Kết quả kiểm thử lần này

**Không dùng PASS cũ thay PASS mới.** Tất cả ghi/DDL chỉ trong DB test độc lập, không sửa suite chính.

| Nhóm | Kết quả | Phương pháp |
|---|---|---|
| Harness expiry/HTTP/races +refusal/review/completed/scope/funds | **49PASS /3FAIL**,52checks | MariaDB riêng, PHP HTTP multipart thật, service/process thật |
| Harness policy/payment reuse/failure rollback/retry | **11PASS /0FAIL** | MariaDB riêng, lỗi payment UPDATE bằng trigger test; không lỗi giả ở source |
| tests/booking-expiry-integration.php --run | **33PASS** | Exact cutoff, idempotency, concurrency,2000batchcap, CLI chạy DB test |
| tests/high-bug-fixes-integration.php --run --only=BUG-004 | **13PASS** | Upload thật, duplicate/late, review1ngày, manual approval/invoice/stock |
| tests/booking-history.php | **48PASS** | Fixture SQLite memory |
| node tests/payment-state.cjs | **PASS nhóm VM** | Polling/expiry guards; không gọi là browser live |

Trong5nhóm có đếm assertion: **157checks =154PASS +3FAIL**. VM không có count assertion nên không cộng vào157.

Ba FAIL tiếp tục tái hiện vấn đề cần sửa, không bị che hoặc đổi expectation thành PASS:

- Admin trước cleanup trả HET_HAN/CHO_THANH_TOAN thay vì cặp expired nhất quán cho A.
- Dashboard vẫn đếm2pending khi fixture chỉ có1hold còn hạn và1overdue.
- Payment HET_HAN filter bỏ sót projected-expired trước job.

Tests bảo toàn mới đáng chú ý:

- Staff đúng cơ sở được từ chối; công ty ngoài scope403; thiếu lý do/lặp bị chặn.
- Từ chối review →DA_HUY/HUY, receipt/reference/amount giữ nguyên; grid free, không invoice/stock mutation.
- Customer cancelHold không hủy accepted review; completed và confirmed-paid bị chặn khỏi receipt refusal; hash dữ liệu không đổi.
- Review CAN_DOI_SOAT qua job vẫn review; khi admin hủy hợp lệ, tiền đã ghi nhận vẫn còn và reviewFunds liệt kê riêng HUY.
- Tạo/reuse payment không đổi reference/deadline; retry expired bị chặn, không gia hạn.
- Admin explicit cancellation sau expiry giữ chính sách hiện tại →DA_HUY/HUY; không nhầm với job tự hủy.
- Payment UPDATE bị lỗi trong expiry →booking/payment rollback cùng pending; bỏ fault rồi job kế tiếp →cùng expired, không invoice/xuất tồn.

### 10.1. Concurrency đã chạy

HTTP receipt được chấp nhận trước deadline rồi commit trì hoãn qua cutoff trong DB test; job thực sự chờ khóa và không expire review. Thứ tự job thắng trước upload thì late upload bị từ chối, không phục hồi. Trì hoãn metadata sau lưu file vượt deadline thì rollback và job expire cảhai. Manual approval đối đầu job trên review vẫn confirmed/paid, đúng1invoice và1lần xuấtqty2.

Trigger SLEEP/fault chỉ nằm trong database test theo booking ID, đã bị dọn cùng DB. Không có trigger mới trong ứng dụng. Không tuyên bố đã kiểm mọi interleaving, chuyển khoản ngân hàng thật hay callback provider; simulator callback trong suite33được ghi đúng là contract simulator.

### 10.2. BLOCKED / NOT RUN

- Unit dashboard/booking-actions legacy: **BLOCKED preflight** do fixture CREATE bank/ALTER invoice lặp schema. Đã đọc lại fixture, không sửa hay báo PASS. Coverage tương ứng đã có harness độc lập nhưng không coi nó là chính suite cũ.
- Chưa triển khai phương án read-model/scheduler, nên chưa có test PASS cho bản sửa dự kiến hoặc kiểm actual task chạy định kỳ trên DB ứng dụng.
- Không reboot/kill MariaDB/server thật; recovery khi transaction lỗi được fault-test, còn OS restart là phân tích ACID/source và regression cần xác minh sau cấu hình.
- Không stress tải lớn, không kiểm mọi role×route×ID, không browser/device regression toàn hệ thống, không chuyển tiền/provider ngân hàng thật.

## 11. An toàn dữ liệu và artifact

Git dirty trước audit gồm các thay đổi nhân viên/khách hàng/cơ sở/highfix/invoicefix của nhóm. Không reset/ghi đè. Mọi source/schema/config/test/assets được fingerprint trước/sau332file:

```text
NKU4BbjU+wF26HvOMRH+HPxPAIwSHHiR7W8mOJO11Is=   SHA-256 aggregate, Base64
```

DB ứng dụng chỉ dùng SELECT/information_schema trong transaction READ ONLY rồi rollback. Không chạy job hoặc POST ghi trên đó, không đổi #84/#86/#100. Các connection ghi được chặn bằng prefix DB test ngẫu nhiên và khác DB ứng dụng, server HTTP test dùng environment riêng.

Artifact chính:

- storage/temp/expiry-impact-IR0rfW/report.json; server.log —49PASS/3FAIL, snapshots và refusal/race evidence.
- storage/temp/expiry-impact-policy-8qKB9a/report.json —11PASS, payment retry/refusal policy/fault rollback/recovery.
- storage/temp/high-bug-fixes-5e4a9aa166/ —13checks receipt integration.
- storage/temp/expiry-impact-IR0rfW/assessment-evidence.log —snapshot đã lược PII, suite output, scheduler result/fingerprint.

DBs stryke_impact_test_* do lượt test sở hữu đã dọn, receipt/proof test chỉ dọn đúng file sở hữu. Không xóa DB/file ảnh thật hoặc artifact không thuộc lượt này. Không cài dependencies, sửa tests chính, commit/push/merge.

## 12. Regression bắt buộc khi được phê duyệt

1. Giữ3ca FAIL để xác nhận trước sửa, sau sửa phải PASS ở read/admin/filter/dashboard mà raw GET vẫn không ghi DB.
2. Trước/đúng/sau deadline, nhiều sân/dịch vụ, cùng cutoff cho count/list/filter và date ranges ở ROOT/CT/CS.
3. Receipt trước hạn/duplicate/late/legacy; B review sau deadline/mộtngày không expired, giữ lịch/tồn, payment raw theo schema hiện tại.
4. Upload/job cảhai thứ tự lock, quá hạn trong file/metadata, approval/job và request lặp; đúng1invoice/1xuất tồn.
5. Từ chối hợp lệ/thiếu lý do/trùng/ngoài scope, receipt/funds retention, canceled/completed/paid không bị expiry sửa.
6. Khách explicit cancel còn hạn/sau hạn/đã review; rời trang/refresh/Back không tự hủy hoặc reset.
7. THANH_CONG/CAN_DOI_SOAT/HUY/legacy và pending bất thường; late-money sauHET_HAN vẫn ghi nhận được, không giữ sân/paid/invoice giả.
8. Payment create/reuse, QR/reference/amount, CSS/HTML/API shapes và auth/CSRF không đổi.
9. Invoice failure rollback, expiry payment write failure rollback, retry/backlog và lock timeout; không sửa snapshot/stock bằng projection.
10. Sau cấu hình: task thực sự chạy mỗi phút/không visitor, đúngDB, không instance chồng, logs/failed/backlog/protected_sample; chạy bù sau missed start. Kiểm revenue/transactions/reviewFunds không đổi định nghĩa.

Không chạy các ca ghi dữ liệu này trên DB ứng dụng để “thử nhanh”; dùng test DB và chỉ vận hành job thật sau phê duyệt riêng.

## 13. Trả lời A–H

**A. Có thể cùng HET_HAN mà không làm hỏng trạng thái khác?** Có cơ sở kỹ thuật và test cho trường hợp A: writer hiện có đã làm nguyên tử. Để nhất quán mọi nơi trước cleanup cần read-model hẹp và regression; không cam kết DB đúng giây360 bằng job phút.

**B. Có ảnh hưởng review/xác nhận/từ chối/hủy/completed/paid/reconciliation?** Không cần thay đổi nghiệp vụ các nhánh này. Rủi ro có nếu áp deadline/projection quá rộng; phương án3phải loại chúng khỏi target. B raw payment vẫn pending theo enum hiện tại, không chuyển thành trạng thái mới.

**C. Có mất khả năng xử lý tiền muộn?** Không nếu giữ row/reference/amount và actions/markNeedsReconciliation hiện có. Test HET_HAN→CAN_DOI_SOAT đạt, không phục hồi sân.

**D. Có sai lịch/stock/invoice/revenue?** Không có lý do phải sửa chúng; tests hiện tại đạt các ca bảo toàn. Bản sửa tương lai vẫn phải chứng minh lại, đặc biệt không cộng tồn lúc pending expire hoặc tính payment chưa xác minh thành revenue.

**E. Cần sửa writer expiry hay scheduler/read-model?** Chưa có bằng chứng cần rewrite expiry. Cần vận hành scheduler và sửa read-model/KPI/filter; giữ transaction/locks/exception guards.

**F. Phương án ít code và an toàn nhất?** Phương án1ít code nhất nhưng không đủ yêu cầu. **Phương án3là lựa chọn ít thay đổi nhất đáp ứng toàn bộ**, dùng job hiện có và giới hạn sửa phần đọc.

**G. Chính xác sửa file/test nào nếu đồng ý?** Sáu file read boundary tại mục8.2, cấu hình scheduler ngoài source; chỉ helper/SELECT/cutoff/status data được nêu. Bộ regression tại mục12 và các harness52/11/33/13/48checks; không sửa fixtures ngoài scope để lấy PASS.

**H. Trường hợp không tự chuyển payment HET_HAN?** Review đã nhận receipt, THANH_CONG, CAN_DOI_SOAT, HUY, paid/confirmed/completed, legacy receipt hoặc bất thường cần đối soát; payment không tồn tại cũng không được tạo giả. Không chuyển toàn bộ pending chỉ dựa tuổi của payment; phải xét booking/deadline/protection cùng contract.

**Dừng ở báo cáo và chờ phê duyệt. Chưa sửa code, schema, dữ liệu ứng dụng hoặc cấu hình/kích hoạt scheduler.**

