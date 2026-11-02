# PR5 Vũ — snapshot, giữ ảo và xuất tồn

## Phạm vi và trạng thái tích hợp

Service nội bộ: `App\Services\BookingInventoryService::deductForPayment(int $bookingId): array`.
Chưa được gọi từ checkout, PaymentService, webhook hay route nào. Chưa phải luồng thanh toán
hoàn chỉnh. Không xác minh giao dịch, đổi trạng thái booking/payment, lập hóa đơn hoặc hoàn kho.
Không thay đổi schema hoặc code booking của Phát.

Đọc cùng [hợp đồng booking/payment hiện có](booking-payment-contract.md). Duy cần tích hợp
hook vào đúng một đường xác nhận thanh toán; không được có đường xác nhận khác bỏ qua xuất tồn.
Hiện chưa có đường xác nhận thành công trong source, nên PR này không thể cưỡng chế yêu cầu
đó cho code thanh toán chưa được viết hoặc cho thao tác SQL ngoài ứng dụng.

## A. Snapshot đã được kiểm tra

- `BookingService::quote/confirm`: tính giá bằng BookingPricingService, kiểm tra lại quote sau
  khi khóa phạm vi và dịch vụ. `Booking::insert` lưu ID, tên, đơn vị, đơn giá, số lượng, thành tiền
  vào `chi_tiet_dich_vu_dat`, cùng ba tổng tiền trong `dat_san`.
- `DichVuService::write` / `DichVu::save` chỉ cập nhật catalog. Không có UPDATE snapshot hoặc
  tính lại tổng booking khi đổi giá/tên. Đổi đơn vị bị chặn khi còn tồn hoặc booking chưa hoàn tất;
  khi đã được phép đổi đơn vị, snapshot cũ vẫn giữ nguyên.
- Unit test chạy checkout và sửa catalog thật trên PDO trong bộ nhớ: 2 chai x 10.000 = 20.000;
  giá catalog đổi thành 15.000 và đổi tên không đổi snapshot/tổng. Kiểm tra cả sau xác nhận,
  đổi đơn vị hợp lệ sau hoàn tất, và giá 0.
- `Booking::visit` ở nhánh hiện tại chưa đọc danh sách dịch vụ, trang chờ chưa hiển thị chi tiết
  snapshot dịch vụ. Đây là khoảng trống đọc/hiển thị, không phải snapshot bị sửa; PR này không
  sửa giao diện hoặc luồng booking theo phạm vi đã chốt.

## B. Giữ tồn ảo hiện có

`Booking::reserved` cộng số lượng theo ID dịch vụ của các booking `CHO_THANH_TOAN` còn hạn.
ID dịch vụ xác định cơ sở; quote chỉ cho chọn dịch vụ đang cung cấp tại cơ sở đang đặt.
`BookingService::quote` tính `max(0, so_luong_ton - reserved)`, với NULL là không theo dõi,
khác tồn bằng 0. Draft session không giữ tồn; booking pending giữ 6 phút, không trừ tồn vật lý.
Hủy/hết hạn chỉ thay trạng thái; booking đó không còn được cộng vào reserved, không cộng tồn vật lý.
Booking quá hạn được loại ngay khỏi reserved dù job chưa cập nhật HET_HAN.

Checkout khóa công ty → cơ sở → bộ môn/loại sân/sân theo ID → dịch vụ theo ID rồi quote lại
trong READ COMMITTED trước khi insert. `DichVuService::write` khóa cha và dịch vụ, chống ghi
đè bằng version chứa tồn, chặn giảm tồn dưới giữ ảo hoặc đổi chế độ theo dõi khi có giữ ảo.
Các kiểm tra hiện có được giữ nguyên. Test quote cũ bị từ chối sau khi khách khác giữ hàng là
mô phỏng tuần tự; không phải bằng chứng hai transaction MySQL chạy đồng thời.

## C. Điều kiện gọi và kết quả xuất tồn

- Chỉ gọi sau khi Duy xác minh thanh toán và xác định booking bằng dữ liệu server.
- Duy sở hữu cùng PDO/transaction, khóa booking `FOR UPDATE`, kiểm tra pending và còn hạn,
  nhận diện lần xử lý đầu tiên trước khi gọi. Service kiểm tra lại pending/thời hạn và khóa
  dịch vụ theo ID tăng dần; kiểm tra cơ sở, đơn vị và số lượng snapshot hợp lệ.
- Không hỗ trợ xuất đồ thuê: checkout hiện cũng không cho đặt CHO_THUE. Dịch vụ ngừng cung cấp
  sau khi giữ không tự làm đổi snapshot; giá/tên catalog không dùng để xuất tồn.
- Đọc snapshot số lượng/đơn vị, bỏ qua stock NULL, kiểm tra toàn bộ dòng trước khi ghi.
  Trừ đúng số lượng bằng UPDATE có điều kiện `so_luong_ton IS NOT NULL AND so_luong_ton >= quantity`;
  số dòng ảnh hưởng phải là 1. Tồn 0 không được xuất số lượng dương. Không cập nhật snapshot.
- Trả `['booking_id' => int, 'deducted' => [...], 'untracked' => [serviceId, ...]]`.
  Mỗi dòng deducted có `service_id`, `quantity`, `stock_before`, `stock_after`.
  Booking không có dịch vụ trả hai mảng rỗng. Đây là kết quả chưa commit, không phải dấu bền vững.
- Không tự begin/commit/rollback hoặc retry. LogicException nếu không có transaction;
  HttpException 404/409 cho dữ liệu không hợp lệ; lỗi PDO truyền cho caller để rollback.
  Lỗi SQL ở dòng sau vẫn có thể để dòng trước đã đổi trong transaction: tuyệt đối không bắt lỗi
  rồi commit phần còn lại. Duy phải rollback toàn bộ.

### Transaction bắt buộc do Duy triển khai

1. Xác minh chữ ký/nguồn giao dịch, tham chiếu, tiền tệ, số tiền và mã giao dịch ngoài transaction.
2. Mở transaction, khóa booking và payment trên cùng PDO. Kiểm tra payment thuộc booking,
   số tiền khớp tổng đã lưu; khóa và kiểm tra lại trạng thái/thời hạn ở server.
3. Nếu booking đã xác nhận/hoàn tất và payment đã thành công với đúng giao dịch: trả kết quả cũ,
   **không gọi Service**. Nếu trạng thái lệch (paid nhưng pending, confirmed nhưng chưa paid),
   không tự suy ra đã/chưa xuất tồn: chuyển sang đối soát, không thử xuất bù.
4. Chỉ lần đầu, booking pending còn hạn mới gọi `deductForPayment($bookingId)` đúng một lần.
5. Cùng transaction, Duy cập nhật booking có điều kiện pending và `het_han_giu_san_luc > CURRENT_TIMESTAMP`
   sang DA_XAC_NHAN; phải ảnh hưởng đúng 1 dòng. Lưu payment THANH_CONG cùng mã giao dịch duy nhất,
   thời điểm và số tiền đã xác minh. Nếu bất kỳ điều kiện/lệnh nào thất bại: rollback tất cả.
6. Commit chỉ khi tất cả thành công. Hóa đơn do PR4 của Duy phụ trách; không được dùng hóa đơn
   làm dấu xuất tồn trong Service này.

**Giới hạn chống lặp:** schema không có cờ/sổ xuất tồn. Service không độc lập idempotent;
gọi hai lần khi booking vẫn pending (cùng transaction hoặc commit sai hợp đồng) có thể trừ hai lần.
Không dùng biến PHP làm dấu. Chỉ toàn bộ hợp đồng trạng thái + payment + xuất tồn commit nguyên tử,
cùng cổng retry của Duy, mới cung cấp bảo đảm xử lý một lần. Unit test kiểm tra cổng caller mô phỏng;
chưa có webhook thật để khẳng định bảo đảm end-to-end. Không chạy hook bù cho booking cũ đã xác nhận.

### Tương thích giữ ảo

Với tồn vật lý 10, A giữ 6, B giữ 4: trước thanh toán reserved=10. Trong transaction A,
Service chỉ trừ vật lý 6, không trừ thêm reserved hoặc cộng lại số lượng nào. Sau khi A xác nhận
và commit: vật lý=4, reserved=4 của B, khả dụng=0. Các kết nối khác thấy dữ liệu trước hoặc sau
commit; không công khai số khả dụng ở giữa transaction. Nếu rollback, cả vật lý và trạng thái về cũ.
Không được commit xuất tồn trong khi booking vẫn pending: như vậy giữ ảo sẽ bị tính thêm lần nữa.

### Khóa và các rủi ro cần thống nhất trước tích hợp

- Hook lấy booking lock rồi khóa các dịch vụ theo ID tăng dần, phù hợp giao thức booking-first
  vừa chốt. Catalog hiện khóa cha → dịch vụ → tham chiếu/booking, nên có khả năng đảo thứ tự khóa
  và deadlock với callback. Hook không khóa booking khác để tính lại reserved.
- Cần Phát/Duy thống nhất prelock cha/dịch vụ trước booking theo thứ tự chung trong tài liệu cũ,
  hoặc giữ booking-first với rollback toàn transaction và retry có kiểm soát khi MySQL 1213/1205.
  Không retry riêng lệnh trừ tồn; không giữ khóa khi gọi nhà cung cấp thanh toán. Chưa kiểm chứng
  deadlock/race bằng MySQL thật trong PR này.
- NULL/theo dõi tồn chưa có snapshot riêng. Chế độ hiện tại được đọc khi khóa dịch vụ;
  catalog đã chặn đổi chế độ trong lúc có giữ ảo còn hạn. Không dùng Service cho booking đã hết hạn.
- `Booking::expire` hiện vẫn có thể chuyển paid-but-pending thành HET_HAN; nó bảo toàn payment
  thành công nhưng không có điều kiện loại booking paid khỏi UPDATE. Không sửa expire ở PR này.
  Duy phải tránh commit trạng thái trung gian đó và phối hợp Phát xử lý dữ liệu lệch/đối soát.

## D. Kiểm thử và phần dành PR6

Chạy `php tests/booking-inventory-unit.php` và `php tests/courts-unit.php`.
Không đọc .env, không kết nối MySQL, không tạo database, không ghi fixture lên database ứng dụng.
Test dùng code quote/confirm/cancel/expire và catalog writer thật với PDO trong bộ nhớ;
payment caller trong test chỉ mô phỏng hợp đồng, không xác minh hoặc giả lập thanh toán trên website.

PR6 cần kiểm thử MySQL/HTTP độc lập: hai callback cùng booking, hai booking tranh hàng,
callback cùng sửa catalog/cancel/expire, thời hạn trôi qua khi chờ khóa, lỗi sau xuất tồn,
giao dịch nhà cung cấp lặp/muộn và mọi đường xác nhận booking. Không chạy
`booking-integration.php --run` trong phạm vi này vì script đó tạo database/fixture riêng.

PR5 có thể review như một hook chưa nối thanh toán. Chưa đủ căn cứ công bố checkout/thanh toán
đã xuất tồn đúng một lần; cần Duy tích hợp giao thức và PR6 kiểm chứng trước khi bật thực tế.
