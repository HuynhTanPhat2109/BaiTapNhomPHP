# Tác vụ hết hạn booking STRYKE

Chưa đăng ký hoặc chạy tác vụ trên database thật. File hướng dẫn này không tự cài đặt tác vụ.

## Quy tắc và vận hành

- Deadline lấy từ `het_han_giu_san_luc` đã lưu, không cộng lại 6 phút khi refresh.
- `CHO_THANH_TOAN` có deadline <= giờ MySQL được chuyển `HET_HAN`.
- Khóa booking rồi payment; mỗi booking một transaction ngắn. Payment chờ chuyển hết hạn cùng transaction.
- Payment `THANH_CONG` / `CAN_DOI_SOAT`: giữ nguyên cả booking lẫn payment, ghi cảnh báo ID để Duy đối soát. Không coi tiền đã nhận là chưa trả; không tự xác nhận/khôi phục sân.
- Lịch và giữ tồn ảo vẫn hết hiệu lực tại deadline, kể cả các đơn cần đối soát. Không giữ sân/tồn vô thời hạn cho tiền cần đối soát. Không UPDATE tồn dịch vụ.
- Khóa MySQL `GET_LOCK` riêng từng database chống hai job chạy chồng, kể cả chạy trên hai máy. Khóa tự giải phóng khi kết nối mất; không áp dụng khóa này cho callback.
- Mỗi batch tối đa 200 booking, tối đa 10 batch/lượt hoặc khoảng 45 giây; chờ row lock tối đa 5 giây trong CLI. Lượt tiếp theo tiếp tục những đơn còn lại, không cần cursor lưu trên đĩa. Đơn được bảo vệ không chặn batch xử lý đơn thông thường.
- Index đã có: `idx_datsan_trangthai_hethan (trang_thai_dat_san, het_han_giu_san_luc)`; payment có UNIQUE `ma_dat_san`. Không cần migration mới.
- GET lịch sử/inspect chỉ đọc trạng thái hiệu lực qua `Booking::effectiveStatusSql()`, bảo vệ payment giống job. Card/filter/count/pagination dùng cùng cutoff. GET không cập nhật DB.
- Cặp trạng thái cần đối soát được giữ nguyên trạng thái lưu; không tự gắn `DA_XAC_NHAN`. Cảnh báo trong log không phải xác nhận thanh toán.
- Log theo ngày tại `storage/temp/booking-expiry/YYYY-MM-DD.log` (đã nằm ngoài Git). Có started/completed/failed, thời gian chạy, số đơn/batch, cờ còn việc và mẫu tối đa 100 ID cần đối soát mỗi lượt. Theo dõi `failed`, `more_pending` lặp lâu và cảnh báo; lưu trữ/dọn log theo chính sách máy chủ. Không xóa thư mục này khi job đang chạy.
- Exit code 0: hoàn tất hoặc bỏ qua vì job khác chạy. Exit code 1: lỗi; những transaction đã commit vẫn giữ, những đơn chưa xử lý được thử lại lần sau.

## Windows Task Scheduler — thực hiện thủ công sau khi kiểm thử

1. Mở **Task Scheduler → Create Task**, đặt tên `STRYKE Booking Expiry`.
2. General: chọn tài khoản có quyền đọc project/.env, ghi thư mục log và kết nối MySQL. Chọn **Run whether user is logged on or not** nếu máy cho phép; không cần quyền Administrator cho PHP/job.
3. Triggers → New: Daily, Repeat task every **1 minute**, for a duration **Indefinitely**, Enabled. Thêm trigger **At startup**, delay 1 phút để MySQL kịp khởi động.
4. Actions → Start a program:
   - Program: `C:\xampp\php\php.exe`
   - Add arguments: `"C:\xampp\htdocs\BaiTapNhomPHP\bin\expire-bookings.php" --run`
   - Start in: `C:\xampp\htdocs\BaiTapNhomPHP` (không đặt dấu ngoặc kép ở ô này).
5. Settings: bật **Run task as soon as possible after a scheduled start is missed**; khi đang chạy chọn **Do not start a new instance**. Có thể đặt giới hạn chạy 2 phút để xử lý tiến trình bất thường. Bỏ điều kiện idle; cân nhắc điều kiện nguồn điện nếu dùng laptop.
6. Đảm bảo MySQL XAMPP đang chạy; muốn tự động sau reboot phải cấu hình MySQL khởi động cùng Windows. PHP CLI không cần Apache. Máy tắt/ngủ không thể xử lý đúng phút; lượt chạy bù xử lý các đơn cũ sau khi hoạt động lại.
7. Sau khi bạn cho phép cập nhật DB thật, bấm Run và kiểm tra Last Run Result `0x0`, log ngày hiện tại, sau đó kiểm tra booking/payment bằng SELECT. Không chạy lại schema hoặc migration để bật tác vụ.

Job chạy mỗi phút có độ trễ cập nhật DB thông thường dưới khoảng một phút, phụ thuộc tải. Quyền giữ sân và trạng thái hiệu lực GET kết thúc đúng deadline; không chờ job. Nếu `more_pending=true` kéo dài, cần kiểm tra throughput trước khi tăng giới hạn batch.

## Linux/server

Ví dụ crontab của user ứng dụng (sửa đường dẫn PHP/project phù hợp):

```cron
* * * * * cd /var/www/stryke && /usr/bin/php bin/expire-bookings.php --run >> /var/log/stryke-expiry-launcher.log 2>&1
@reboot sleep 60 && cd /var/www/stryke && /usr/bin/php bin/expire-bookings.php --run >> /var/log/stryke-expiry-launcher.log 2>&1
```

Chuẩn bị quyền ghi launcher log, bật cron và MySQL. Cron mỗi phút tự xử lý backlog sau downtime; không cần MySQL event/trigger hoặc Redis.

## Hợp đồng với Duy và Chí

- Duy: callback phải khóa booking trước payment (và tuân theo thứ tự parent locks trong booking-payment-contract), kiểm tra lại deadline ngay trước conditional UPDATE. Ghi payment + booking + xuất tồn + hóa đơn trong cùng transaction. Callback trễ hoặc payment thành công nhưng booking còn chờ cần đối soát, không hồi sinh hold. Job không thay callback của Duy.
- Chí: hiện repository chưa có màn hình quản lý danh sách booking Admin. Khi thêm, dùng `Booking::effectiveStatusSql()` với alias `b` và bind cùng một mốc giờ MySQL cho cả list/count/filter. Không tính nhãn riêng tại view, không dùng `owned()` trên GET.
- Chưa triển khai chức năng gửi cảnh báo hoặc trang đối soát mới. Mẫu 100 ID trong log không phải toàn bộ danh sách bất thường; Duy cần truy vấn đầy đủ khi xử lý.

## Kiểm thử

`php tests/booking-expiry-integration.php --run` tạo database MySQL test ngẫu nhiên, chạy job CLI với DB_NAME test, thử khóa/đồng thời, rồi xóa database test. Không chạy `bin/expire-bookings.php --run` với .env thật chỉ để kiểm thử.
