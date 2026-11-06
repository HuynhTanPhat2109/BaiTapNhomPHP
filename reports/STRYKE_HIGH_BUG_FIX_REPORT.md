# STRYKE — Báo cáo sửa 4 lỗi HIGH

Ngày thực hiện: **06/11/2026**, múi giờ **Asia/Ho_Chi_Minh (+07:00)**. Nhánh: **kiem-thu-3**, HEAD lúc bắt đầu: **5562347**.

Phạm vi: chỉ BUG-001, BUG-002, BUG-003 và đối soát BUG-004 theo báo cáo STRYKE_FULL_SYSTEM_TEST_REPORT.md. Không sửa BUG-005–009; riêng nội dung đơn vị giá đã chủ động bỏ ở BUG-007 được giữ nguyên.

## 1. Kết quả

| BUG | Severity | Nguyên nhân | File sửa | Test | Trạng thái |
|---|---|---|---|---|---|
| BUG-001 | HIGH | Hai query bảo vệ cơ sở/sân thiếu CHO_XAC_NHAN | app/models/CoSo.php; app/models/San.php | Trước sửa 3 ca tái hiện FAIL; sau sửa nhóm BUG-001 đạt 21/21 | **FIXED** |
| BUG-002 | HIGH | Main SQL thiếu bảng ngân hàng, 2 cột hóa đơn và khóa tương ứng | database/quanly_chuoi_san_the_thao.sql | Trước sửa 2 ca cấu trúc FAIL; sau sửa nhóm BUG-002 đạt 9/9 trên DB trắng | **FIXED** |
| BUG-003 | HIGH | InvoiceService không đối chiếu payment.ma_dat_san với booking | app/services/InvoiceService.php | Trước sửa ca payment chéo FAIL; sau sửa nhóm BUG-003 độc lập đạt 5/5; duyệt/invoice/tồn thật cũng đạt | **FIXED** |
| BUG-004 | HIGH | Dữ liệu cũ còn pending dù đã lưu receipt; phiên upload cũ không chuyển trạng thái | Không sửa backend hiện tại hoặc dữ liệu thật | Upload HTTP hiện tại, duplicate, deadline, review sau 1 ngày và late upload đạt; đối soát READ ONLY #84/#86 | **NEEDS APPROVAL** |

**Không cập nhật dữ liệu #84/#86. Không tự xác nhận tiền, tạo hóa đơn hoặc thay đổi giữ sân/tồn cho hai đơn.**

## 2. File đã sửa/tạo

### Source/schema — 4 file sửa

1. [CoSo.php](../app/models/CoSo.php), activeBookings(), khoảng dòng 131–136.
2. [San.php](../app/models/San.php), related(), khoảng dòng 78–82.
3. [InvoiceService.php](../app/services/InvoiceService.php), createInvoice(), dòng 83–85.
4. [quanly_chuoi_san_the_thao.sql](../database/quanly_chuoi_san_the_thao.sql), hoa_don dòng 556; FK dòng 658; bank table dòng 669.

### File mới

5. [high-bug-fixes-integration.php](../tests/high-bug-fixes-integration.php): regression test chỉ cho BUG-001–004, opt-in và database riêng.
6. Báo cáo này: reports/STRYKE_HIGH_BUG_FIX_REPORT.md.

Không đổi route, response contract, controller, view, CSS, JS frontend, layout hoặc migration cũ. Không sửa báo cáo audit cũ để xóa/đổi các phát hiện lịch sử.

## 3. BUG-001 — Bảo vệ review khi quản lý cơ sở/sân

Thay đúng predicate ở hai model:

- Trước: DA_XAC_NHAN hoặc CHO_THANH_TOAN còn hạn.
- Sau: CHO_XAC_NHAN hoặc DA_XAC_NHAN, hoặc CHO_THANH_TOAN còn hạn.
- Điều kiện ngày chơi/giờ kết thúc còn ở tương lai được giữ nguyên.
- CHO_THANH_TOAN quá hạn, HET_HAN và DA_HUY không ngăn sai thao tác.
- HOAN_THANH giữ cách xử lý trước đây trong hai guard này; không tự mở rộng tập trạng thái ngoài yêu cầu.
- Đây là SELECT kiểm tra tham chiếu; không cập nhật trạng thái booking.

CoSoService::save() và SanService::save() đã dùng hai query này để từ chối thay đổi xung đột nên không cần sửa service hoặc thông báo/view.

Đã tái hiện và kiểm tra bằng service thật trên MariaDB:
- Review ngày chơi tương lai nhưng deadline cũ qua một ngày vẫn được tính.
- Thu hẹp giờ cơ sở làm mất khung giờ review bị ValidationException.
- Tạm ngừng sân có review bị ValidationException.
- Thay giờ không xung đột vẫn thành công.
- Pending còn hạn/đã hết hạn, confirmed, completed, cancelled và expired giữ đúng hành vi đã yêu cầu.
- So sánh record trước/sau SELECT guard: không bị sửa.
- Tạo booking mới và tính giá server vẫn thành công.

## 4. BUG-002 — Đồng bộ schema cài mới

Bổ sung vào main SQL, không chạy trên database ứng dụng:

- hoa_don.so_hoa_don: VARCHAR(50) NULL.
- hoa_don.ma_thanh_toan: INT NULL.
- UNIQUE uq_hoadon_so_hoa_don và uq_hoadon_ma_thanh_toan; giữ UNIQUE ma_dat_san hiện có.
- FK fk_hoadon_thanhtoan đến thanh_toan.ma_thanh_toan.
- Bảng tai_khoan_ngan_hang_co_so với kiểu dữ liệu/default/index/FK theo migration 20261101:
  - PK ma_tk_ngan_hang;
  - ma_co_so;
  - ten_ngan_hang, ma_bin, so_tai_khoan, ten_chu_tai_khoan;
  - la_mac_dinh, trang_thai, ngay_tao;
  - idx_tknh_coso (ma_co_so, la_mac_dinh, trang_thai);
  - UNIQUE uq_coso_bin_stk (ma_co_so, ma_bin, so_tai_khoan);
  - FK co_so, ON DELETE RESTRICT, InnoDB/utf8mb4.

Main SQL tạo hoa_don trước thanh_toan. Vì vậy FK hóa đơn được khai báo bằng ALTER ngay sau khi đã tạo thanh_toan, tránh tham chiếu bảng chưa tồn tại. Không đảo lại toàn bộ thứ tự DDL và không tắt FOREIGN_KEY_CHECKS.

Không thêm INSERT seed, không sửa công thức/CHECK tiền, không đổi các cấu trúc module khác. Main SQL có **43 dòng bổ sung**, không xóa seed hoặc cấu trúc cũ.

### Tương thích migration

- **Cài mới:** import main SQL mới một lần vào database trống. Không chạy lại migration 20261102_hoa_don_fields.sql trên schema này: hai cột/index/FK đã có.
- **Database cũ:** tiếp tục dùng migration cũ chỉ khi kiểm tra cấu trúc xác nhận còn thiếu. Không import lại main SQL lên dữ liệu đang sử dụng.
- Migration ngân hàng có IF NOT EXISTS; migration hóa đơn là ALTER một lần, không idempotent.
- Không sửa migration cũ, không tạo migration mới, không tự chạy migration trên database thật.
- Database ứng dụng hiện tại đã có các cột/bảng này từ trước; thay đổi lần này chỉ đồng bộ file cài mới.

**Giới hạn test cũ:** tests/receipt-integration.php vẫn import main SQL rồi chạy lại migration hóa đơn. Lượt chạy nguyên trạng sau sửa dừng tại dòng 58 với 1060 Duplicate column name 'so_hoa_don'. Đây là lỗi setup lặp migration trên schema mới, chưa tới các assertion receipt. Test cũ được giữ nguyên theo giới hạn; không thay assertion hoặc cơ chế migration để ép PASS. Phương án sau khi được duyệt: tách fixture “install mới” khỏi fixture “upgrade DB cũ”; không import main schema mới rồi áp ALTER cũ lần nữa.

## 5. BUG-003 — Ràng buộc payment với booking trước khi ghi hóa đơn

Thêm một kiểm tra sau khi đã SELECT FOR UPDATE được cả hai record:

payment.ma_dat_san phải bằng booking.ma_dat_san.

Nếu sai, ném ValidationException ở trường payment trước INSERT. Không thay trạng thái, số tiền, cơ chế khóa, transaction owner hoặc giá trị trả về thành công.

Các guard hiện có vẫn giữ nguyên: booking/payment tồn tại và đúng trạng thái, tiền khớp, invoice không trùng và UNIQUE tại database. Service vẫn chạy bên trong transaction do luồng xác nhận mở; khi có lỗi, caller rollback như trước.

Kiểm thử thực tế:
1. Payment của booking B nhưng truyền booking A, cùng số tiền: bị từ chối, không có invoice.
2. Payment không tồn tại: bị từ chối an toàn.
3. Cặp hợp lệ: tạo đúng một invoice.
4. Gọi lại cùng cặp: không tạo invoice thứ hai.
5. Tạo invoice rồi mô phỏng caller lỗi: rollback làm invoice biến mất.
6. Upload và duyệt thủ công qua luồng hiện tại: booking/payment xác nhận đúng, invoice được tạo, tồn trừ một lần.
7. Duyệt lại: bị chặn và không trừ thêm tồn.

Không thêm composite FK hoặc sửa schema ngoài BUG-002 để xử lý invariant này.

## 6. BUG-004 — Đối soát READ ONLY booking #84 và #86

Snapshot: **06/11/2026 11:30:53 +07:00**, database ứng dụng. Kết nối dùng START TRANSACTION READ ONLY rồi rollback. Không chạy UPDATE/INSERT/DELETE/DDL.

| Thông tin | Booking #84 | Booking #86 |
|---|---|---|
| Trạng thái booking | CHO_THANH_TOAN | CHO_THANH_TOAN |
| Payment | #49 — CHO_THANH_TOAN | #51 — CHO_THANH_TOAN |
| Bắt đầu giữ | 05/11/2026 11:22:13 | 05/11/2026 11:32:00 |
| Deadline gốc | 05/11/2026 11:28:13 | 05/11/2026 11:38:00 |
| Gửi receipt | 05/11/2026 11:22:21 | 05/11/2026 11:32:09 |
| Trước deadline | Có | Có |
| Ảnh mới trong cột | NULL | Có |
| Legacy tag [BIEN_LAI:...] | Có | Có |
| Resolver ảnh hiện tại | Tìm được file JPEG hợp lệ | Tìm được file JPEG hợp lệ |
| Ngày chơi | 05/11/2026 — đã qua | 05/11/2026 — đã qua |
| Cơ sở / sân | ma_co_so=13, ma_san=43 — Sân 2 | ma_co_so=13, ma_san=43 — Sân 2 |
| Khung giờ | 11:30–12:30 | 12:00–13:00 |
| Tiền sân / dịch vụ / tổng | 80.000 / 20.000 / 100.000 ₫ | 80.000 / 20.000 / 100.000 ₫ |
| Tiền thực nhận / ngày xác minh | NULL / NULL | NULL / NULL |
| Hóa đơn | 0 | 0 |

### Xung đột và điểm cần quyết định

- Hai booking **cùng khách hàng** và **nội dung file ảnh giống nhau** (so SHA-256 của file, không đưa ảnh/hash/PII vào báo cáo).
- Chúng trùng trên cùng sân trong **12:00–12:30 ngày 05/11/2026**.
- #84 còn overlap lịch sử với #83 (HET_HAN, 11:00–12:00); #83 không phải booking đang chiếm lịch.
- Không thấy overlap khác ở trạng thái CHO_XAC_NHAN/DA_XAC_NHAN/HOAN_THANH hoặc pending còn hạn tại snapshot này.
- Điều đó **không** làm việc khôi phục cả #84 và #86 an toàn: chúng sẽ xung đột với nhau nếu cùng được phục hồi.
- Dịch vụ của cả hai là một đơn vị mỗi dịch vụ #53/#54. Tồn vật lý hiện tại 119/79; lượng đang giữ bởi các booking khác 5/7. Các số này không chứng minh dịch vụ ngày 05/11 đã được giao hoặc chưa giao, nên không tự giữ/trừ/hoàn tồn.
- Ảnh trùng nhau không tự chứng minh gian lận, cũng không chứng minh đã có hai giao dịch độc lập. Cần kiểm tra ngân hàng/tham chiếu thực tế.

### Nguyên nhân phiên bản cũ và workflow hiện tại

Git diff tại commit **f39cf59**, ngày **05/11/2026 15:55:05 +07:00**, cho thấy BookingReceiptService::upload() trước đó chỉ lưu liên kết ảnh/payment và chủ động giữ nguyên trạng thái pending/deadline. Commit này thêm conditional UPDATE booking sang CHO_XAC_NHAN trong cùng transaction.

Thời điểm hai receipt (11:22/11:32) trước commit đó. Dữ liệu quan sát phù hợp với workflow cũ; đây là cơ chế source cũ đã xác định có thể tạo đúng anomaly. Tuy nhiên không có deployment/access audit log đủ để khẳng định chính xác revision nào đã phục vụ từng request lịch sử.

Workflow hiện tại không tái hiện lỗi này trong kiểm thử HTTP mới:
- Ảnh hợp lệ được upload thật qua PHP/CSRF/session.
- Booking sang CHO_XAC_NHAN cùng lúc lưu liên kết ảnh.
- Payment vẫn CHO_THANH_TOAN, chưa bị xác nhận tiền.
- Deadline không reset; gửi lặp giữ nguyên ảnh.
- Review sau một ngày không hết hạn và vẫn giữ sân/tồn.
- Gửi sau expiry không phục hồi booking.
- Duyệt thủ công sau đó tạo đúng invoice và trừ tồn một lần.

Vì không tìm thấy lỗi mới tái hiện được ở workflow hiện tại, **không sửa BookingReceiptService/BookingService hoặc controller cho BUG-004**.

### Phương án xử lý cần phê duyệt — chưa thực thi

**Ưu tiên đối soát cùng một nhóm, không xử lý hai booking độc lập:**

1. Sao lưu hai booking, payment, chi tiết sân/dịch vụ và file receipt; xác minh giao dịch thực tế với đúng tham chiếu/mã tiền nhận và việc sử dụng sân/dịch vụ. Không xem ảnh là xác nhận tiền.
2. Xác định booking nào được khách/chủ sân công nhận, có phải một lần gửi ảnh cho hai đơn hay không. Hai ngày chơi đã qua nên không tự dựng lại hold tương lai hoặc giữ tồn vô thời hạn chỉ để đổi nhãn lịch sử.
3. **Đối với #84:** kiểm tra payment #49, receipt legacy, lịch 11:30–12:30 và tranh chấp với #86. Không tự backfill cột ảnh hoặc đổi booking state chỉ vì resolver tìm được ảnh. Nếu được duyệt phục hồi review cho #84 thì phải coi #86 là xung đột cần xử lý trước; không đồng thời phục hồi cả hai.
4. **Đối với #86:** kiểm tra payment #51, ảnh trùng và lịch 12:00–13:00. Chỉ đề xuất phục hồi review nếu không mâu thuẫn với quyết định dành cho #84. Nếu là đơn trùng/không được công nhận, việc từ chối/hủy/đối soát phải có quyết định hợp lệ, giữ bằng chứng, không tự xác nhận hoặc xóa ảnh.
5. Nếu có hai khoản tiền thật cho hai đơn trùng lịch, cần quyết định nghiệp vụ đối soát/hoàn tiền hoặc điều chỉnh được quản lý chấp thuận; không che xung đột bằng hai lần UPDATE state.
6. Sau khi có quyết định cụ thể, kế hoạch sửa dữ liệu phải dùng transaction ngắn, khóa company/facility/court rồi hai booking/payment theo thứ tự ổn định, đọc lại trạng thái/receipt version/overlap và tồn. Chỉ cập nhật có điều kiện đúng record được duyệt, không sửa deadline gốc hoặc tự gia hạn. Nếu snapshot đã thay đổi hoặc xuất hiện xung đột thì rollback và báo lại.
7. Nếu chỉ phục hồi sang review, payment không tự thành THANH_CONG, không tạo invoice, không trừ tồn; các bước xác minh tiếp tục qua workflow hiện có. Với ngày chơi quá khứ cần chốt cách ghi nhận trước khi chọn phương án phục hồi review.
8. Sau thao tác được phê duyệt, kiểm tra lại khách/admin, schedule/history, service reservations và invoice; ghi lại người thực hiện và quyết định.

**Chưa tạo/chạy SQL phục hồi. BUG-004 vẫn NEEDS APPROVAL.**

## 7. Kiểm thử trong lượt sửa này

### Trước sửa

Chạy test mới trên source chưa sửa: **42 PASS / 6 FAIL**, exit 1:

- BUG-001: 3 FAIL — thiếu active review, đổi giờ xung đột được chấp nhận, ngừng sân được chấp nhận.
- BUG-002: 2 FAIL — thiếu invoice columns và bank table.
- BUG-003: 1 FAIL — tạo được invoice sai quan hệ.
- Workflow upload hiện tại đã đạt. Các migration hỗ trợ trước-sửa chỉ được áp dụng trong DB test để những nhóm khác vẫn chạy; các assertion schema thiếu vẫn được giữ FAIL.

### Sau sửa

| Lệnh / nhóm | Kết quả thực chạy | Ghi chú |
|---|---|---|
| php -l CoSo.php / San.php / InvoiceService.php / high-bug-fixes-integration.php | PASS, không lỗi cú pháp | Chỉ file PHP đã thay đổi |
| high-bug-fixes-integration.php --run --only=BUG-001 | 21 PASS / 0 FAIL | MariaDB riêng |
| high-bug-fixes-integration.php --run --only=BUG-002 | 9 PASS / 0 FAIL | Import schema mới vào DB trắng; không cần nâng cấp |
| high-bug-fixes-integration.php --run --only=BUG-003 | 5 PASS / 0 FAIL | Service invoice thật và transaction |
| high-bug-fixes-integration.php --run | **48 PASS / 0 FAIL** | Schema + guard + invoice + upload HTTP/approval/stock |
| facility-unit.php | 109 PASS | Guard/scope/validation contracts |
| courts-unit.php | 38 PASS | Schedule, thời gian và availability contract |
| pricing-unit.php | 567 PASS | Giá hiện có không đổi |
| booking-expiry-integration.php --run | 33 PASS | Deadline, concurrent expiry/callback guard, batches/stock; chỉ DB test |
| receipt-integration.php --run, nguyên trạng | **FAIL setup**, exit 1 — 1060 Duplicate column so_hoa_don | Assertion nghiệp vụ phía sau NOT RUN; không sửa test/migration cũ |
| git diff --check | PASS | Không có lỗi whitespace ở tracked diff |

Không cộng các lượt --only vào tổng 48 vì chúng lặp ca. Đây không phải tuyên bố toàn bộ suite của dự án PASS. Không sửa BUG-006, không bỏ assertion để đổi kết quả test.

### An toàn test

- Tên database do runner sinh: stryke_highfix_test_<10 hex>, khác DB ứng dụng; kiểm tra local host và SELECT DATABASE() trước ghi.
- Bỏ CREATE DATABASE/USE cố định khỏi bản import chỉ trong runner, không dùng DSN database ứng dụng cho fixture.
- PHP HTTP server nhận DB_NAME riêng qua environment; session/log ở storage/temp/high-bug-fixes-<suffix>.
- Mỗi DB do test tạo đều DROP trong finally; không DROP database có sẵn.
- Ảnh thử được upload riêng, ngẫu nhiên; chỉ dọn ảnh thử đã ghi nhận trong lượt chạy này và proof.png do test tạo. Không xóa receipt thật.
- Không cài dependency hoặc chạy browser automation; test HTTP thật dùng cURL/PHP server. Không đánh dấu browser E2E là đã chạy.
- Test expiry gọi CLI trên database riêng; không đăng ký hay sửa scheduler thực (BUG-005).

Ví dụ artifact:
- Trước sửa: storage/temp/high-bug-fixes-94bb79a972.
- Sau sửa toàn bộ: storage/temp/high-bug-fixes-fea15017f4.
- Server log/session chỉ phục vụ kiểm thử; không đưa cookie/password/hash thật vào báo cáo.

## 8. Kiểm soát phạm vi và giới hạn còn lại

Đã ghi nhận git status trước sửa: 17 tracked file đã thay đổi từ trước, cùng các file untracked Customer/Employee/detail-services/test/report của nhóm. Không revert hoặc ghi đè chúng.

So sánh hash trước/sau cho **329 file ngoài phạm vi được sửa** (app/core/routes/config/migrations/public/existing tests) giữ nguyên:

Pe57hpXC7LOfHrQejl5ZFXDXqT90ObLyq93yWlbOeTk=

Tracked diff mới của nhiệm vụ chỉ gồm 4 file source/schema kể trên: **50 dòng thêm, 3 dòng thay/xóa**. Test mới và báo cáo là file mới. Các CSS/view/JS đang dirty thuộc thay đổi có sẵn, checksum không đổi trong nhiệm vụ này.

Giới hạn:
- Suite receipt cũ cần được nhóm phê duyệt điều chỉnh bootstrap theo schema mới trước khi có thể chạy nguyên trạng; không sửa trong lượt này.
- Không kiểm thử browser/UI hay ngân hàng/provider thực vì không đổi giao diện/tích hợp provider.
- Không chạy lại audit toàn hệ thống và không kết luận release READY.
- Booking #84/#86 cần quyết định đối soát riêng trước mọi sửa dữ liệu; đặc biệt có overlap lẫn nhau và receipt trùng.

## 9. Xác nhận cuối

- **Đã sửa:** BUG-001, BUG-002, BUG-003.
- **Chưa sửa dữ liệu:** BUG-004 — đã đối soát, đề xuất cụ thể, chờ phê duyệt.
- Không chỉnh giao diện, CSS, JS frontend, route hoặc response contract.
- Không thay đổi công thức giá, thời hạn giữ 6 phút, quy trình xác minh/đối soát, quản lý tồn hoặc phân quyền ngoài các guard bắt buộc.
- Không UPDATE database thật, không chạy migration/import schema trên database đang sử dụng.
- Không xử lý BUG-005–009; không khôi phục nội dung BUG-007 đã chủ động bỏ.
- Không commit, push hoặc merge.

