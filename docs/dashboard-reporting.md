# Dashboard STRYKE — định nghĩa số liệu và hợp đồng review

## Phạm vi truy cập

| URL | Vai trò | Phạm vi |
|---|---|---|
| `/admin` | `QUAN_TRI_TAP_DOAN` | Tổng quan toàn hệ thống, tháng này |
| `/company` | `QUAN_LY_CONG_TY` | Tổng quan công ty được giao, tháng này |
| `/facility` | `QUAN_LY_CO_SO` | Cơ sở được phân công |
| `/staff` | `NHAN_VIEN` | Chỉ xem cơ sở được phân công |
| `/admin/revenue` | `QUAN_TRI_TAP_DOAN` | Báo cáo toàn hệ thống; lọc công ty/cơ sở |
| `/company/revenue` | `QUAN_LY_CONG_TY` | Báo cáo công ty được giao; lọc cơ sở trực thuộc |
| `/facility/revenue`, `/staff/revenue` | Vai trò tương ứng | Chỉ đọc báo cáo cơ sở được phân công |

`KHACH_HANG` không có quyền truy cập. Middleware bảo vệ từng URL; DashboardService còn gọi `BookingManagementService::resolveScope()` để tái sử dụng kiểm tra tài khoản/phân công đang hoạt động. Không sử dụng `can_confirm` để cấp hành động mới. Công ty/cơ sở truyền qua GET phải là ID dương, nằm trong scope và đúng quan hệ cha–con. Yêu cầu vượt scope trả 403, không âm thầm đổi scope.

Trang thông tin công ty và hợp đồng của Chí vẫn dùng nguyên `CompanyController::dashboard()` và view `company/index`, chuyển liên kết sang `/company/profile`. Không sửa nghiệp vụ công ty/hợp đồng.

Dashboard không có API dữ liệu riêng, không có POST hay thao tác xác nhận thanh toán. Tổng quan dùng tháng hiện tại đến hôm nay và không nhận bộ lọc báo cáo; bộ lọc GET chuyển sang trang Thống kê doanh thu. Biểu đồ SVG và bảng số liệu được render tại server cùng kiểm tra quyền với trang. JavaScript chỉ hỗ trợ chọn cơ sở và trạng thái đang tải. Không thêm thư viện runtime.

## Định nghĩa doanh thu duy nhất

Nguồn thu là `thanh_toan` JOIN `dat_san` JOIN `co_so` JOIN `cong_ty_nhuong_quyen`. Một dòng đủ điều kiện khi:

- `thanh_toan.trang_thai = 'THANH_CONG'`.
- `dat_san.trang_thai_dat_san IN ('DA_XAC_NHAN', 'HOAN_THANH')`.
- Có `ngay_thanh_toan` hợp lệ trong miền DATETIME và `ma_giao_dich` không rỗng.
- `so_tien_thuc_nhan = so_tien = dat_san.tong_tien_tam_tinh`.
- `tong_tien_tam_tinh = tong_tien_san + tong_tien_dich_vu`; hai thành phần không âm.

Tổng đã thu = SUM `so_tien_thuc_nhan`. Tiền sân/dịch vụ = SUM `dat_san.tong_tien_san` / `tong_tien_dich_vu` của cùng tập thanh toán hợp lệ. Đây là các tổng snapshot được BookingService lưu và InvoiceService dùng để lập hóa đơn; không tính lại từ `bang_gia` hay giá catalog `dich_vu` hiện tại.

Không JOIN hóa đơn hay các dòng chi tiết vào truy vấn cộng doanh thu. Schema có UNIQUE `thanh_toan.ma_dat_san`, `ma_giao_dich`, `ma_tham_chieu`; một booking được tính một lần. Retry cập nhật cùng thanh toán không tạo thêm doanh thu. Dashboard không bổ sung hay sửa cơ chế chống callback lặp của bên thanh toán. Các truy vấn top sân/dịch vụ dùng EXISTS trên tập thanh toán hợp lệ; sân đếm DISTINCT booking để không đếm mỗi dòng khung giá thành một lượt đặt. Tên/đơn vị top dịch vụ lấy từ snapshot; nếu tên đã đổi, chọn một tên snapshot đại diện cho cùng ID/đơn vị.

Tính tổng tiền tại SQL trên DECIMAL; định dạng tiền từ chuỗi, hỗ trợ tổng vượt giới hạn tiền của từng booking. Float chỉ phục vụ hình học biểu đồ, tỷ trọng làm tròn và thứ tự hiển thị, không cộng lại doanh thu. Tỷ trọng dùng tổng trong phạm vi và khoảng ngày đang lọc; tỷ lệ hiển thị làm tròn có thể không cộng đúng 100,0% ở từng dòng.

## Mốc thời gian và tính nhất quán

- Timezone: `Asia/Ho_Chi_Minh`, phù hợp PaymentService/InvoiceService và kết nối MySQL `+07:00` hiện có.
- Doanh thu, bảng so sánh và top sân/dịch vụ lọc theo `thanh_toan.ngay_thanh_toan`, tức thời điểm ghi nhận thành công trong source hiện tại.
- KPI/trạng thái và danh sách booking lọc theo `dat_san.ngay_tao`. Vì booking có thể tạo kỳ trước rồi thanh toán kỳ này, số booking đã thu trên bảng doanh thu có thể khác số booking tạo/đã xác nhận trong kỳ.
- Điều kiện ngày là `[đầu ngày bắt đầu, đầu ngày sau ngày kết thúc)`, không làm mất giao dịch lúc 23:59:59.
- Tổng đã thanh toán toàn thời gian giữ cùng phạm vi công ty/cơ sở, không bị giới hạn ngày. Hạ tầng là trạng thái hiện tại theo phạm vi, không giả lập lịch sử hoạt động.
- KPI, biểu đồ, bảng cùng một read snapshot trong transaction REPEATABLE READ trên MySQL; chỉ SELECT. Service chỉ commit/rollback transaction mà chính nó mở. Không gọi các phương thức InvoiceService có thể tự lập hóa đơn khi đọc.

Preset: hôm nay, 7 ngày gồm hôm nay, đầu tháng đến hôm nay, tháng trước đầy đủ, tùy chọn. Khoảng tùy chọn giới hạn kỹ thuật 3.660 ngày để tránh biểu đồ quá lớn; chế độ ngày tối đa 367 mốc. Tự động chuyển sang tháng nếu khoảng dài hơn 62 ngày. Ngày/ID sai trả 422; lỗi truy vấn trả 503 và thông báo, không thay số liệu bằng 0. Không có dữ liệu thật trả 0 và empty state.

## Giới hạn cần phối hợp

1. Chưa có sổ hoàn tiền/trạng thái hoàn tiền đáng tin cậy. Dashboard thống kê **thanh toán thành công hợp lệ**, không phải doanh thu thuần, lợi nhuận hoặc phí nhượng quyền. Hủy hóa đơn không tự động có nghĩa đã hoàn tiền.
2. `CAN_DOI_SOAT`, chờ, hủy, hết hạn và thanh toán thiếu thông tin/không khớp tiền không được cộng. Nếu Duy giải quyết đối soát rồi chuyển chính thức sang THANH_CONG với booking hợp lệ, bản ghi trở thành đủ điều kiện. Không có lịch sử riêng để phân biệt thời điểm chuyển tiền thực tế, lần đối soát trước đó hoặc số tiền đã hoàn ngoài hệ thống; không suy diễn từ deadline giữ sân có thể đã bị luồng khác cập nhật.
   Cổng xác nhận thủ công đã chặn booking hủy/hết hạn và payment kết thúc; chuẩn hóa CHO_XAC_NHAN giữ nguyên hạn cũ. Dữ liệu lịch sử trước bản sửa không thể suy ngược bằng Dashboard. Duy vẫn phải xác minh giao dịch thật; báo cáo không thay thế xác minh thanh toán.
3. Booking chỉ lưu cơ sở, không snapshot công ty sở hữu tại thời điểm thanh toán. Doanh thu lịch sử được quy về công ty hiện sở hữu cơ sở. Chí cần review nếu cho phép chuyển cơ sở giữa công ty.
4. Không có lịch sử trạng thái tài sản để hồi tố số cơ sở/sân hoạt động ở ngày cũ.
5. Không thêm index/migration. Khi dữ liệu lớn, Duy/nhóm cần đánh giá index ngày thanh toán/ngày tạo và kế hoạch truy vấn, được duyệt riêng trước khi thay schema.

## Kiểm tra

`php tests/dashboard-unit.php`: SQLite trong RAM, nhiều công ty/cơ sở, trạng thái tiền/booking, snapshot, callback cập nhật lặp, ràng buộc UNIQUE, ngày/timezone, scope và route thật. `PRAGMA query_only=ON` bảo đảm các lượt render không ghi dữ liệu.

`node tests/dashboard-browser.cjs`: Chrome thật, fixture PHP riêng, 1440/1024/768/375px, sidebar đầy đủ/thu gọn/mở/đóng, bộ lọc phụ thuộc, đồ thị/bảng số liệu, empty/error state. Sử dụng `PLAYWRIGHT_PATH`, `PHP_BINARY`, `CHROME_BINARY` nếu máy chưa có các đường dẫn mặc định; không cần cấu hình database test. Ảnh/log ở `storage/temp/dashboard-review` được Git ignore.

Kiểm tra thủ công: đăng nhập theo vai trò, mở URL tương ứng, thử preset/tùy chọn, đối chiếu tiền sân + dịch vụ với tổng đã thu, thử ID công ty/cơ sở ngoài phạm vi, thu gọn sidebar và xem bảng trên mobile. Quản lý công ty dùng “Thông tin công ty” để mở nội dung công ty/hợp đồng cũ. Không cần tạo hoặc sửa bản ghi để xem Dashboard.

Phát review scope và định nghĩa số booking; Duy review các điều kiện giao dịch thành công/mốc xác nhận/hoàn tiền; Chí review liên kết trang thông tin công ty và quy chủ sở hữu lịch sử. Không thay đổi quy tắc nghiệp vụ của các thành viên này.

## Danh sách file của thay đổi Dashboard

Sửa 6 file: `app/controllers/admin/DashboardController.php`, `app/views/admin/dashboard.php`, `app/views/layouts/admin.php`, `routes/web.php`, `config/admin-menu.php`, `tests/catalog-unit.php`.

Tạo 13 file:

- `app/models/Dashboard.php`, `app/services/DashboardService.php`.
- `app/views/company/dashboard.php`, `app/views/facility/dashboard.php`, `app/views/staff/dashboard.php`.
- `app/views/admin/partials/dashboard.php`, `app/views/admin/partials/dashboard-chart.php`.
- `public/assets/css/dashboard.css`, `public/assets/js/dashboard.js`.
- `tests/dashboard-unit.php`, `tests/dashboard-browser.cjs`, `tests/fixtures/dashboard.php`.
- `docs/dashboard-reporting.md`.
