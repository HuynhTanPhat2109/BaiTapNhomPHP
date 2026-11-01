# Nâng cấp database đã có

## Booking và khóa chống request lặp

Database đã có: chọn đúng database trong phpMyAdmin, sao lưu bằng Export,
rồi Import **20261031_booking_request_key.sql** đúng một lần trước khi dùng luồng booking.
Không import lại schema chính và không chạy lại các migration địa chỉ/logo đã áp dụng.
Migration này chỉ thêm hai cột nullable `khoa_yeu_cau`, `dau_van_tay_yeu_cau`
và UNIQUE cho khóa yêu cầu; không sửa hoặc xóa bản ghi cũ.
Người cài mới dùng schema chính đã cập nhật thì không chạy migration này.

Tồn dịch vụ được giữ bằng chi tiết dịch vụ của booking `CHO_THANH_TOAN` còn hạn;
không thêm cột tồn giữ và không trừ tồn chính thức trước thanh toán.
Hết hạn thì lượng giữ tự hết hiệu lực khi tính khả dụng, kể cả chưa chạy tác vụ dọn trạng thái.
Có thể cấu hình lịch chạy `php bin/expire-bookings.php --run` mỗi phút để chuyển trạng thái hết hạn.
Không chạy lệnh này nếu chưa muốn ghi trạng thái vào database đang cấu hình.

Giao diện tích hợp thanh toán: `docs/booking-payment-contract.md`.
Kiểm thử riêng: `php tests/booking-integration.php --run` tạo và xóa database test ngẫu nhiên,
không áp dụng migration lên database ứng dụng; cần MySQL local và cổng 18989 trống.

## Logo CLB

Nếu đã chạy migration địa chỉ/ảnh, chỉ chạy **20261023_co_so_logo.sql** một lần
trong database hiện tại. File này chỉ thêm `logo_clb VARCHAR(255) NULL DEFAULT NULL`.
Không chạy lại migration địa chỉ/ảnh. Người cài mới dùng schema chính không chạy hai migration này.

## Địa chỉ và ảnh đại diện (database chưa nâng cấp)

1. Sao lưu database hiện tại bằng Export trong phpMyAdmin.
2. Chọn đúng database của project ở thanh bên trái.
3. Import `20261023_co_so_address_image.sql` (hoặc dán nội dung vào tab SQL).
4. Chạy đúng một lần, sau đó dùng code mới. Không import lại schema chính.

Migration chỉ thêm 5 cột nullable và index tỉnh, không sửa/xóa bản ghi.
Không chứa CREATE DATABASE/USE nên phải chọn đúng database trước khi chạy.
Nếu cột đã tồn tại, dừng và kiểm tra cấu trúc, không chạy lại mù quáng.
Người cài mới import schema chính thì không chạy migration này.

Dataset ở `resources/address/` được sử dụng offline; nguồn và commit ở SOURCE.txt.
Ảnh ở `public/uploads/co-so/` cần quyền ghi cho PHP. Không commit ảnh upload;
khi chuyển máy hoặc sao lưu, sao chép cả thư mục ảnh cùng database.
PHP cần fileinfo, upload_max_filesize >= 3M và post_max_size lớn hơn 3M
(khuyến nghị 8M). Giữ file .htaccess trong thư mục ảnh trên Apache/XAMPP.

Kiểm thử: `php tests/facility-address.php`, `php tests/facility-unit.php`.
MySQL local đang chạy: `php tests/facility-migration-integration.php --run`.
Test tích hợp tạo database ngẫu nhiên riêng, áp dụng migration lên schema cũ,
kiểm tra dữ liệu cũ và HTTP CRUD/upload, rồi xóa database test trong finally.
Không chạy migration trên database ứng dụng. Cổng 18987 phải đang trống.
