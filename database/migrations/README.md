# Nâng cấp database đã có

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
