# STRYKE — kiểm tra đặt thêm sân, 05/11/2026

## Kết luận cho Hoàng Quân, 13:00–14:00

Không có quy tắc giới hạn mỗi khách chỉ được một booking. Nguyên nhân trường hợp người dùng
xác nhận là **thiếu bảng giá cho khung giờ chọn**, kèm lỗi giao diện khiến thông báo backend không hiện.

Đã kiểm tra database ứng dụng bằng transaction **READ ONLY**, không cập nhật dữ liệu:

- Cơ sở #3 `Hoang Quan CL`, sân #3, loại sân #8 vẫn hoạt động.
- Bảng giá #4 `sân cầu lông`: `NGAY_THUONG`, **06:00–12:00**, `DANG_AP_DUNG`,
  hiệu lực 10/12/2024–10/12/2027. Không có bảng giá tương ứng cho 13:00–14:00.
- Các ô 13:00 và 13:30 có trạng thái `free`. `BookingService::quote()` đi đến
  `BookingPricingService::courtLines()` và trả HTTP 409:
  “Chưa có bảng giá duy nhất cho hoàn quân lúc 13:00. Vui lòng chọn giờ khác hoặc liên hệ cơ sở.”
- Booking #12 đã xác nhận ngày 05/11, **10:00–11:30**, payment thành công.
  Nó chặn đúng khoảng đã đặt, không chặn 13:00–14:00.
- Booking #11 đã quá hạn giữ lúc 09:13:17; các booking chờ xác nhận cũ cũng đã quá hạn.
  Chúng không giữ ô 13:00–14:00. Không sửa trạng thái của các bản ghi thật này.

## Lỗi code và phần đã sửa

`public/assets/js/schedule.js` đã tìm `#selection-feedback` để thông báo lỗi báo giá/kết nối,
nhưng `app/views/courts/schedule.php` không render phần tử đó. Nhánh xử lý lỗi bỏ qua khi target
là null, nên trang chỉ còn dấu `—` và nút Tiếp theo bị vô hiệu hóa. Không có exception JavaScript
để khách nhận biết lý do.

Đã bổ sung vùng thông báo có `role="status"`/`aria-live` ngay trong phần tạm tính và CSS riêng
của lịch sân. Đồng thời giới hạn chiều rộng thanh chú giải mobile để flex tự xuống hàng, xử lý
tràn 5px phát hiện tại 375px; không ẩn overflow của trang hoặc thay đổi lưới slot.

**Không thay đổi** giá, công thức tính tiền, query chống trùng, route, quyền, CSRF hoặc thời hạn giữ.
Không sửa JavaScript chọn giờ, booking/payment/inventory service hay chạy migration trong lượt này.

## Tác vụ minh chứng bị dừng và schema

Đã đối chiếu git diff và lưu bản trước lượt này ở `storage/temp/repeat-booking-review/before`.
Các file đang dở `BookingReceiptService.php`, `BookingManagementService.php`, `ReceiptImage.php`,
cùng `BookingController.php`, `Booking.php`, `booking.js` giữ nguyên byte so với lúc bắt đầu kiểm tra.
`BookingService.php` không có thay đổi chưa commit. Không reset/revert hoặc ghi đè công việc trước.

Các thay đổi minh chứng đang dở xử lý ảnh/ghi chú/thời gian gửi; chúng không tham gia nhánh tính giá
13:00–14:00. Route upload POST vẫn là `/booking/receipt`; route GET ảnh kiểm tra chủ booking/scope.
Database hiện có đủ bốn cột minh chứng/xác nhận sau migration người dùng vừa chạy. Kiểm thử đã dùng
bản sao **DDL thật**, gồm enum/CHECK/FK, với PDO MySQL native; không loại bỏ khóa hoặc dịch SQL sang SQLite.

## Trạng thái lịch và quy tắc đặt tiếp

| Trạng thái | Ý nghĩa |
| --- | --- |
| `free` / Trống | Không bị booking giữ/đặt; vẫn phải có bảng giá hợp lệ để tiếp tục |
| `held` / Đang giữ | Booking pending/chờ xác nhận còn hạn giữ gốc |
| `booked` / Đã đặt | Booking đã xác nhận hoặc hoàn thành; không tự giải phóng theo hạn pending |
| `unavailable` / Khóa | Giờ đã bắt đầu hoặc sân/loại sân/bộ môn không hoạt động |

Kiểm tra khoảng giờ dùng giao nhau thực sự; giờ liền kề không xung đột.
Pending hết hạn ngừng giữ slot ngay khi đọc lịch, không cần chờ job dọn trạng thái.
Hủy pending hợp lệ giải phóng slot. Không trừ/cộng tồn vật lý trong hai thao tác này.
`BookingController::start()` tạo khóa nháp mới; retry cùng khóa trả đúng booking cũ, không phải giới hạn khách.

## Kiểm thử thực tế

Chạy `php tests/repeat-booking-integration.php --run` với Chrome/Playwright hiện có.
Runner chỉ đọc DDL từ database cấu hình, tạo database tên ngẫu nhiên `stryke_repeat_test_*`,
seed dữ liệu TEST ONLY và mật khẩu ngẫu nhiên trong bộ nhớ. HTTP dùng AuthController/AuthService,
middleware, CSRF, Controller/Service/Model và PDO thật. Session/ảnh test ở `storage/temp`.
Chỉ ảnh QR từ nguồn ngoài được thay bằng ảnh test để không phụ thuộc mạng ngân hàng; không giả response
của bất kỳ route booking/upload/confirmation nào. Database tạm được dọn trong `finally`.

Kết quả cuối:

- **Chrome: 39 PASS / 0 FAIL.** Tái hiện thiếu giá 13:00–14:00; thông báo hiển thị ở
  1440/1024/768/375px, không tràn ngang trang; tạo booking từ thao tác lịch thực tế, tải ảnh PNG bằng
  multipart thật, đọc lại ảnh có quyền, Admin xem ảnh lớn và xác nhận thủ công; quay về lịch qua header.
- **MariaDB: 8 PASS / 0 FAIL.** Một khách tạo năm booking khác nhau: một đã xác nhận, một đã hủy,
  một pending quá hạn, hai pending hợp lệ. Sân đang giữ/đã đặt bị chặn; hủy/hết hạn cho đặt lại.
  Sau khi chỉ thêm giá buổi chiều trong database test, cùng khách đặt được đúng 13:00–14:00.
  Xác nhận lặp không trừ tồn lần hai; tồn 200 → 198, một hóa đơn. Backend từ chối xác nhận booking
  hủy/hết hạn và không xuất tồn thêm.
- Unit liên quan: `courts-unit.php` 38, `receipt-confirmation-unit.php` 83,
  `booking-inventory-unit.php` 92, `booking-pr6-contract.php` 10 checks PASS.
  `manual-payment-sql-unit.php` 72 và `booking-management-unit.php` 109 checks PASS.
  Tổng cộng 404 checks trong 6 file PHP này.
  `schedule-interaction.cjs` và `payment-state.cjs` PASS.
- PHP lint 6 file, JS syntax 2 file và `git diff --check`: PASS.
- Network được lưu có HTTP 409 đúng trường hợp từ chối báo giá, không có HTTP 5xx;
  không có JavaScript runtime exception. **Console chưa sạch:** có 221 cảnh báo CSP inline style
  và một thông báo tài nguyên 404 chưa định vị, không phải nguyên nhân thiếu giá. Các inline style
  còn tồn tại trong view thanh toán, ví dụ `booking-wait.php`; chưa sửa chúng trong phạm vi lần này.

Lần đầu tái hiện trước sửa: 2 kiểm tra Chrome xác nhận nút bị khóa và target thông báo không tồn tại.
Trong quá trình sửa, test phát hiện tràn mobile 5px và cấu hình worker expiry chưa nạp env; đã xử lý
đúng nguyên nhân rồi chạy lại toàn bộ test mới. Không bỏ/vô hiệu hóa assertion.

Chưa chạy lại toàn bộ bộ test Admin, chưa kiểm thử hai kết nối xác nhận đồng thời trong lượt này.
Không dùng kết quả fixture RAM để kết luận luồng HTTP/MySQL đã hoạt động.

Artifacts: `storage/temp/repeat-booking-review/`: `browser-regression-results.json` (Console/Network),
`database-results.json`, ảnh `missing-price-before.png`, `missing-price-after-{1440,1024,768,375}.png`,
`task.diff`, `git-status.txt`, `git-diff-stat.txt`.

## File thay đổi trong lượt này

1. `app/views/courts/schedule.php` — render target thông báo đang thiếu.
2. `public/assets/css/schedule.css` — trình bày phản hồi, giới hạn chiều rộng chú giải mobile.
3. `tests/repeat-booking-integration.php` — test opt-in trên MariaDB riêng, bảo vệ database thật.
4. `tests/fixtures/repeat-booking.php` — máy chủ test chạy route/auth/PDO thật trên database được cô lập.
5. `tests/repeat-booking-browser.cjs` — kiểm thử Chrome và lưu Console/Network/ảnh.
6. `docs/repeat-booking-review.md` — báo cáo này.

## Để đặt được 13:00–14:00 trên website hiện tại

Admin vào `/admin/bang-gia` → Thêm bảng giá (`/admin/bang-gia/create`), chọn cơ sở Hoàng Quân,
đúng loại sân của sân #3 (mã loại #8), loại ngày **Ngày thường**, trạng thái **Đang áp dụng**,
ngày hiệu lực bao gồm ngày muốn đặt và giờ bao phủ **13:00–14:00**. Người quản lý tự nhập đơn giá thực tế.
Không chồng lấn với giá đang áp dụng; giá buổi sáng hiện kết thúc 12:00 nên có thể cấu hình giá liền kề.
Ngày cuối tuần cần cấu hình loại ngày tương ứng nếu muốn khai thác. Tải lại lịch rồi chọn lại hai ô.

Chưa tự bổ sung giá hoặc đặt thử 13:00–14:00 trên database ứng dụng. Không thay đổi dữ liệu/schema thật,
không commit/push/merge/reset/revert.
