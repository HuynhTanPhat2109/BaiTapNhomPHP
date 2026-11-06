# STRYKE — Sửa ba lỗi hóa đơn POST-001, POST-002, POST-003

Ngày thực hiện: **06/11/2026**, timezone **Asia/Ho_Chi_Minh (+07:00)**.
Branch: **kiem-thu-3**. HEAD khi bắt đầu: **55623473790089f8229a2bda2b9533f68966f5c5**.

## 1. Kết quả

| Lỗi | Mức độ | Nguyên nhân thực tế | Thay đổi | Trạng thái |
|---|---|---|---|---|
| POST-001 | HIGH | Ba view đọc key gia_tien không có trong dữ liệu chi tiết sân | Đơn giá đọc don_gia; thành tiền đọc thanh_tien, đều là snapshot đã lưu | **FIXED** |
| POST-002 | MEDIUM | Customer print include nhầm repo/views thay vì repo/app/views | Đổi dirname(__DIR__, 2) thành dirname(__DIR__) trong InvoiceController::print() | **FIXED** |
| POST-003 | LOW | filemtime trong customer view đi lên quá một cấp thư mục | Hai biểu thức dirname(__DIR__, 4) đổi thành dirname(__DIR__, 3) | **FIXED** |

**Chỉ sửa 9 dòng PHP trong 4 file.** Không sửa HTML structure, CSS/JS, model/service, route, schema, công thức tiền, trạng thái hoặc giao dịch. Không sửa những vấn đề khác được nêu trong báo cáo regression.

## 2. Kiểm tra trước khi sửa

- Đã đọc reports/STRYKE_POST_FIX_FULL_REGRESSION_REPORT.md và đối chiếu code hiện tại.
- Đã chạy git status trước sửa. Có sẵn 21 tracked file dirty và các file untracked của nhóm liên quan nhân viên/khách hàng/tab dịch vụ/các HIGH fixes trước đó. Bốn file hóa đơn của nhiệm vụ này chưa có thay đổi. Không reset/revert/ghi đè các thay đổi có sẵn.
- HoaDon::details() SELECT ct.* từ chi_tiet_dat_san, trả đúng don_gia và thanh_tien. Schema và BookingPricingService xác nhận don_gia là giá một giờ; thanh_tien là số tiền snapshot cho khoảng thời gian của dòng đó. Model đã đúng nên chỉ đọc, không sửa.
- Khởi tạo database MariaDB riêng, PHP server và Chrome test riêng để tái hiện trước sửa. Không ghi database ứng dụng.

Bằng chứng trước sửa tại **storage/temp/invoice-fix-xy4UrY/**:

- Hóa đơn hai sân: các ô đơn giá/thành tiền đều0; dịch vụ2 ×10.000 =20.000; tổng lưu170.000 đúng.
- GET owner /invoices/print trả **HTTP500**.
- PHP log có cả Undefined array key gia_tien và filemtime(): stat failed.
- Đã chụp customer/admin invoice ở1440px và390px trước sửa.

## 3. File và dòng đã sửa

| File | Dòng hiện tại | Thay đổi chính xác |
|---|---|---|
| app/views/invoices/show.php | 4–5 | Chỉ filesystem path của hai filemtime: dirname3, giữ nguyên asset URL/path và tham số v |
| app/views/invoices/show.php | 87–88 | gia_tien → don_gia / thanh_tien |
| app/views/admin/invoices/show.php | 140–141 | gia_tien → don_gia / thanh_tien |
| app/views/admin/invoices/print.php | 92–93 | gia_tien → don_gia / thanh_tien |
| app/controllers/InvoiceController.php | 135 | Include app/views/admin/invoices/print.php bằng dirname(__DIR__) |

Không sửa filemtime ở admin: độ sâu thư mục ở đó khác và dirname4 đang đúng. Giữ nguyên number_format(...,0,',','.'), các cột, ô SL, style attributes, nhãn, totals và logic quyền sở hữu. Không tính lại giá từ catalog hiện tại. Dòng30phút đơn giá100.000/giờ hiển thị thành tiền50.000 từ snapshot; không lấy don_gia làm thành tiền và không tính lại thời lượng trong view.

File mới: **reports/STRYKE_INVOICE_BUG_FIX_REPORT.md**. Không thêm test source vào repository; harness độc lập chạy trong bộ nhớ/stdin, chỉ lưu log/screenshot/profile/session tạm được phép.

## 4. Kiểm thử thực tế sau sửa

| Kiểm tra | Kết quả | Phạm vi/giới hạn |
|---|---|---|
| PHP lint bốn file đã sửa | **PASS 4/4** | Không lỗi cú pháp |
| git diff --check cho bốn file | **PASS** | Không whitespace error |
| Chrome + HTTP PHP thật + MariaDB riêng | **PASS 102/102** | Giá dòng, tổng, quyền, asset, snapshot bất biến, browser ở1440/390 |
| tests/high-bug-fixes-integration.php --run | **PASS 48/48** | Bao gồm invoice pair/missing/duplicate/rollback và manual approval không tạo invoice/trừ tồn lặp |
| tests/invoice-unit.php nguyên bản | **BLOCKED bởi fixture**, raw exit1 | Fake PDO chưa khởi tạo trong booking-management-unit.php; PDO::exec tại BookingManagementService.php:271 dừng trước invoice checks. Không sửa suite hoặc backend để làm PASS |

Không cộng các lần thử harness lặp vào102check cuối. Suite unit cũ không được báo PASS.

### 4.1. Dữ liệu tiền và snapshot

Chạy đủ bốn màn hình: customer show, customer print, admin show, admin print trên mỗi ca:

| Fixture | Snapshot sân | Dịch vụ snapshot | Tổng hóa đơn | Kết quả |
|---|---|---|---:|---|
| Hóa đơn cũ/ngày lập2025 | 60phút, don_gia100.000, thanh_tien100.000 | Không | 100.000 | PASS |
| Một sân30phút | don_gia100.000, thanh_tien50.000 | Không | 50.000 | PASS |
| Hai sân | A60phút:100.000; B30phút:50.000, cùng đơn giá100.000 | 2 ×10.000 =20.000 | 170.000 | PASS |
| Nhiều mức giá trên một sân | 30phút giá60.000→30.000; 30phút giá80.000→40.000 | 1 ×10.000 | 80.000 | PASS |

Trong DB test, giá catalog sân được cố ý đặt987.654 và dịch vụ543.210 sau khi đã tạo snapshot. Cả bốn màn hình vẫn hiển thị các đơn giá/thành tiền snapshot nêu trên; tổng các dòng khớp tiền hóa đơn đã lưu. Không sử dụng giá hiện tại để tính lại đơn cũ.

### 4.2. HTTP và phân quyền

- Chủ hóa đơn: show/print theo **invoice ID** trả200 ở cả bốn fixture; show/print theo **booking ID** cũng200.
- Khách khác: show/print theo invoice ID hoặc booking ID đều **403** theo InvoiceService hiện có.
- Chưa đăng nhập: **303** đến route đăng nhập, kể cả URL route được encode.
- Invoice ID không tồn tại: **404**, không500.
- Công ty ngoài phạm vi: admin show/print **403**.
- Không thay đổi requireCustomer(), middleware, scope hoặc nhánh truy vấn ID/booking_id.

### 4.3. Asset và PHP log

- Customer invoice CSS/JS đều trả **HTTP200**, MIME tương ứng text/css và application/javascript.
- Cả hai asset có cache version v là số nguyên dương, lấy được filemtime thật.
- Log PHP của lượt HTTP/browser sau sửa: **không có warning, fatal hoặc Application failure**, đặc biệt không còn hai warning POST-001/POST-003.
- Không có uncaught JavaScript exception trong lượt kiểm tra cuối.
- Các cảnh báo CSP/inline style đã biết thuộc BUG-009 nằm ngoài nhiệm vụ và không được sửa/nới CSP.

### 4.4. Bảo toàn dữ liệu và giao diện

- Hash toàn bộ các dòng của7bảng test: dat_san, chi_tiet_dat_san, chi_tiet_dich_vu_dat, thanh_toan, hoa_don, dich_vu, bang_gia trước/sau các GET xem/in hóa đơn **giữ nguyên**.
- Cả trạng thái booking/payment, số lượng invoice, tổng tiền và tồn dịch vụ không thay đổi khi xem/in những hóa đơn có sẵn trong ca test.
- **328 file ngoài bốn file được phép sửa** giữ nguyên fingerprint trước/sau:

```text
5oGAS7fMw75OZ91YUXWkbQEWmuLsp6KfmRX45uKsgwU=   SHA-256 aggregate, Base64
```

- Toàn bộ token T_INLINE_HTML của cả ba view có SHA-256 giống hệt trước/sau. Chứng minh không đổi HTML markup/class/style/text ngoài các biểu thức PHP cho giá và asset path được cho phép.
- Không đổi nội dung CSS/JS, layout, header/footer/sidebar. Browser ở1440px giữ nguyên kích thước bảng/tờ hóa đơn và số element. Ở390px, chiều rộng và số element giữ nguyên; bảng auto-layout tự dàn lại khi thay0bằng số tiền dài: chiều cao bảng/tờ hóa đơn giảm16,5px do header không còn xuống dòng như trước. Đã ghi nhận bằng geometry và ảnh; **không khẳng định pixel-identical**, không chỉnh CSS để ép chiều cao cũ.

## 5. Artifact và độ an toàn môi trường

| Nhóm | Đường dẫn |
|---|---|
| Trước sửa: HTML response/log/ảnh | storage/temp/invoice-fix-xy4UrY/before-report.json; server.log; before-*-response.log; before-{customer,admin}-{1440,390}.png |
| Lượt kiểm tra cuối102PASS | storage/temp/invoice-fix-Hlqc7a/report.json; server.log; after-*-response.log |
| Ảnh sau sửa | storage/temp/invoice-fix-Hlqc7a/after-{customer,admin}-{1440,390}.png |
| Regression48PASS | storage/temp/high-bug-fixes-13b352f173/ |
| Output unit bị fixture chặn | storage/temp/invoice-fix-Hlqc7a/unit-and-regression.log |

Database cuối: **stryke_invoicefix_test_538b2274d1**, tách khỏi DB cấu hình ứng dụng, đã dọn đúng ownership (report.json có cleanup.removed). Hai DB fixture thử trước đó cũng đã dọn: stryke_invoicefix_test_eca86bce32 và stryke_invoicefix_test_adfba554ae. Regression dùng riêng stryke_highfix_test_7ca4cd6fa2, đã dọn. Không chạy migration/import/UPDATE/INSERT/DELETE trên DB ứng dụng; không xóa ảnh hoặc database không thuộc lượt test.

Trong quá trình xây harness stdin có điều kiện so sánh ký tự tiền tệ qua PowerShell encoding và URL redirect chưa decode; các điều kiện đó đã được sửa trong bộ nhớ, không sửa test suite cũ. Kiểm tra exact-height cũng được ghi nhận là reflow nội dung, không ép kết quả giống pixel. Các output thử trước giữ lại trong temp;102PASS chỉ của lượt cuối đã kiểm tra numeric total, decoded route, width/DOM invariants và ghi riêng height delta.

## 6. Giới hạn và xác nhận cuối

- Đã kiểm Chrome headless thực ở desktop1440px và mobile emulation390px; chưa kiểm thiết bị điện thoại vật lý, Firefox/Safari hoặc in ra máy in/PDF thật. HTTP trang in và nội dung đã xác minh200, không đồng nghĩa đã thử máy in phần cứng.
- Không chạy lại toàn bộ audit hệ thống; kết quả này chỉ đóng ba lỗi hóa đơn, không thay kết luận nghiệm thu toàn hệ thống của báo cáo trước.
- Không sửa fixture REG-001, BUG-004–009 hoặc những lỗi khác. Không khôi phục `/giờ` (BUG-007).

| Câu hỏi | Trả lời |
|---|---|
| POST-001 đúng tiền từng dòng sân chưa? | Có, đơn giá và thành tiền snapshot riêng biệt, gồm30phút/60phút/multicourt/multirate |
| POST-002 khách hàng in được chưa? | HTTP trang in đã200, cả invoice ID và booking ID; quyền giữ nguyên. Chưa thử máy in vật lý |
| POST-003 hết warning chưa? | Có trong lượt HTTP/browser sau sửa; asset200 và cache version hợp lệ |
| Customer/admin invoice đúng chưa? | Cả ba view và bốn endpoint kiểm tra đều khớp snapshot/tổng trên các fixture đã chạy |
| Có sửa layout/CSS/JS/HTML structure không? | Không. Có reflow tự nhiên ở390px khi số tiền dài hơn; đã báo rõ |
| Có đổi booking/payment/invoice business logic không? | Không; không sửa model/service, transaction, trạng thái, giá hoặc tồn |
| Có ghi DB thật không? | Không; mọi fixture/DDL chỉ trong database test riêng |
| Có sửa file ngoài phạm vi không? | Không; chỉ4file PHP được phép, báo cáo này và artifact tạm |
| Có commit/push/merge không? | Không |

