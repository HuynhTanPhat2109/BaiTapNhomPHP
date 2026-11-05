<?php

namespace App\Models;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class HoaDon
{
    public function __construct(private PDO $pdo) {}

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    /**
     * Tạo bản ghi hóa đơn mới trong Transaction.
     *
     * @param array $data ['so_hoa_don', 'ma_dat_san', 'ma_thanh_toan', 'ma_nhan_vien', 'ngay_lap', 'tien_san', 'tien_dich_vu', 'tong_tien', 'trang_thai', 'ghi_chu']
     * @return int ID hóa đơn vừa tạo (ma_hoa_don)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO hoa_don (
                so_hoa_don,
                ma_dat_san,
                ma_thanh_toan,
                ma_nhan_vien,
                ngay_lap,
                tien_san,
                tien_dich_vu,
                tong_tien,
                trang_thai,
                ghi_chu
            ) VALUES (
                :so_hoa_don,
                :ma_dat_san,
                :ma_thanh_toan,
                :ma_nhan_vien,
                :ngay_lap,
                :tien_san,
                :tien_dich_vu,
                :tong_tien,
                :trang_thai,
                :ghi_chu
            )'
        );

        $now = self::now()->format('Y-m-d H:i:s');
        $stmt->execute([
            'so_hoa_don' => (string) $data['so_hoa_don'],
            'ma_dat_san' => (int) $data['ma_dat_san'],
            'ma_thanh_toan' => !empty($data['ma_thanh_toan']) ? (int) $data['ma_thanh_toan'] : null,
            'ma_nhan_vien' => !empty($data['ma_nhan_vien']) ? (int) $data['ma_nhan_vien'] : null,
            'ngay_lap' => $data['ngay_lap'] ?? $now,
            'tien_san' => $data['tien_san'] ?? '0.00',
            'tien_dich_vu' => $data['tien_dich_vu'] ?? '0.00',
            'tong_tien' => $data['tong_tien'],
            'trang_thai' => $data['trang_thai'] ?? 'DA_LAP',
            'ghi_chu' => $data['ghi_chu'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Tìm hóa đơn theo ID kèm thông tin phạm vi cơ sở/công ty.
     */
    public function find(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT h.*,
                       d.ma_khach_hang,
                       d.ma_co_so,
                       d.trang_thai_dat_san,
                       cs.ma_cong_ty,
                       cs.ten_co_so,
                       k.ho_ten AS ten_khach_hang,
                       k.so_dien_thoai AS sdt_khach_hang,
                       k.email AS email_khach_hang,
                       nv.ho_ten AS ten_nhan_vien,
                       t.ma_giao_dich,
                       t.ma_tham_chieu,
                       t.phuong_thuc AS phuong_thuc_thanh_toan,
                       t.ngay_thanh_toan,
                       t.trang_thai AS trang_thai_thanh_toan
                FROM hoa_don h
                JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san
                JOIN co_so cs ON cs.ma_co_so = d.ma_co_so
                JOIN khach_hang k ON k.ma_khach_hang = d.ma_khach_hang
                LEFT JOIN nhan_vien nv ON nv.ma_nhan_vien = h.ma_nhan_vien
                LEFT JOIN thanh_toan t ON t.ma_thanh_toan = h.ma_thanh_toan
                WHERE h.ma_hoa_don = :id';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Tìm hóa đơn theo mã đặt sân (ma_dat_san).
     */
    public function findByBookingId(int $bookingId, bool $lock = false): ?array
    {
        $sql = 'SELECT h.*,
                       d.ma_khach_hang,
                       d.ma_co_so,
                       d.trang_thai_dat_san,
                       cs.ma_cong_ty,
                       cs.ten_co_so
                FROM hoa_don h
                JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san
                JOIN co_so cs ON cs.ma_co_so = d.ma_co_so
                WHERE h.ma_dat_san = :booking_id';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['booking_id' => $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Tìm hóa đơn theo mã thanh toán (ma_thanh_toan).
     */
    public function findByPaymentId(int $paymentId, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM hoa_don WHERE ma_thanh_toan = :payment_id';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['payment_id' => $paymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Tìm hóa đơn theo số hóa đơn (so_hoa_don).
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?array
    {
        $sql = 'SELECT h.*,
                       d.ma_khach_hang,
                       d.ma_co_so,
                       cs.ma_cong_ty,
                       cs.ten_co_so
                FROM hoa_don h
                JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san
                JOIN co_so cs ON cs.ma_co_so = d.ma_co_so
                WHERE h.so_hoa_don = :num';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['num' => $invoiceNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Kiểm tra booking đã có hóa đơn hay chưa.
     */
    public function existsByBookingId(int $bookingId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM hoa_don WHERE ma_dat_san = :id');
        $stmt->execute(['id' => $bookingId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Kiểm tra thanh toán đã có hóa đơn hay chưa.
     */
    public function existsByPaymentId(int $paymentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM hoa_don WHERE ma_thanh_toan = :id');
        $stmt->execute(['id' => $paymentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Kiểm tra số hóa đơn đã tồn tại hay chưa.
     */
    public function existsInvoiceNumber(string $invoiceNumber): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM hoa_don WHERE so_hoa_don = :num');
        $stmt->execute(['num' => $invoiceNumber]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Sinh số hóa đơn duy nhất chuẩn kế toán:
     * Định dạng: INV + [NămThángNgày: 6 số] + B[Mã Booking: 4 số] + [Chuỗi ngẫu nhiên: 4 ký tự]
     * Ví dụ: INV261102B0015A8F9
     */
    public function generateInvoiceNumber(int $bookingId): string
    {
        $prefix = 'INV' . self::now()->format('ymd') . 'B' . sprintf('%04d', $bookingId % 10000);

        do {
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $candidate = $prefix . $random;
        } while ($this->existsInvoiceNumber($candidate));

        return $candidate;
    }

    /**
     * Lấy đầy đủ thông tin chi tiết một hóa đơn phục vụ in ấn và hiển thị.
     */
    public function details(int $invoiceId): ?array
    {
        $invoice = $this->find($invoiceId);
        if (!$invoice) {
            return null;
        }

        $bookingId = (int) $invoice['ma_dat_san'];

        // Lấy thông tin cơ sở và công ty
        $facStmt = $this->pdo->prepare(
            'SELECT cs.*, ct.ten_cong_ty, ct.dia_chi AS dia_chi_cong_ty, ct.so_dien_thoai AS sdt_cong_ty
             FROM co_so cs
             LEFT JOIN cong_ty_nhuong_quyen ct ON ct.ma_cong_ty = cs.ma_cong_ty
             WHERE cs.ma_co_so = :fac_id'
        );
        $facStmt->execute(['fac_id' => $invoice['ma_co_so']]);
        $facility = $facStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Lấy danh sách sân đã đặt
        $courtStmt = $this->pdo->prepare(
            'SELECT ct.*, s.ten_san, ls.ten_loai_san, bm.ten_bo_mon
             FROM chi_tiet_dat_san ct
             JOIN san s ON s.ma_san = ct.ma_san
             JOIN loai_san ls ON ls.ma_loai_san = s.ma_loai_san
             JOIN bo_mon bm ON bm.ma_bo_mon = ls.ma_bo_mon
             WHERE ct.ma_dat_san = :id
             ORDER BY ct.ngay_choi ASC, ct.gio_bat_dau ASC'
        );
        $courtStmt->execute(['id' => $bookingId]);
        $courts = $courtStmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy danh sách dịch vụ đã đặt
        $svcStmt = $this->pdo->prepare(
            'SELECT cd.*, dv.ten_dich_vu, dv.don_vi_tinh, dv.loai_dich_vu
             FROM chi_tiet_dich_vu_dat cd
             JOIN dich_vu dv ON dv.ma_dich_vu = cd.ma_dich_vu
             WHERE cd.ma_dat_san = :id
             ORDER BY cd.ma_dich_vu ASC'
        );
        $svcStmt->execute(['id' => $bookingId]);
        $services = $svcStmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy ảnh biên lai / chứng từ khách hàng đã tải lên (nếu có)
        $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id');
        $pStmt->execute(['id' => $bookingId]);
        $receiptPayment = $pStmt->fetch(PDO::FETCH_ASSOC);
        $receiptImage = $receiptPayment && (new \App\Services\BookingReceiptService($this->pdo))->info($receiptPayment)
            ? url('/booking/receipt?id=' . $bookingId) : null;

        return [
            'invoice' => $invoice,
            'facility' => $facility,
            'courts' => $courts,
            'services' => $services,
            'receipt_image' => $receiptImage,
        ];
    }

    /**
     * Danh sách hóa đơn có phân trang, lọc đa tiêu chí và phân quyền (Admin).
     *
     * @param array $filters ['per_page', 'page', 'q', 'date_from', 'date_to', 'status', 'facility_id']
     * @param array $scope ['company', 'facility']
     * @return array ['items' => array, 'total' => int, 'page' => int, 'pages' => int, 'per_page' => int]
     */
    public function page(array $filters, array $scope): array
    {
        $where = [];
        $params = [];

        // 1. Phân quyền Scope
        if ($scope['company'] !== null) {
            $where[] = 'cs.ma_cong_ty = :scope_company';
            $params['scope_company'] = $scope['company'];
        }
        if ($scope['facility'] !== null) {
            $where[] = 'd.ma_co_so = :scope_facility';
            $params['scope_facility'] = $scope['facility'];
        }

        // 2. Lọc cơ sở cụ thể
        if (!empty($filters['facility_id'])) {
            $where[] = 'd.ma_co_so = :facility_id';
            $params['facility_id'] = (int) $filters['facility_id'];
        }

        // 3. Lọc theo trạng thái hóa đơn
        if (!empty($filters['status'])) {
            $where[] = 'h.trang_thai = :status';
            $params['status'] = $filters['status'];
        }

        // 4. Lọc theo khoảng ngày lập hóa đơn
        if (!empty($filters['date_from'])) {
            $where[] = 'h.ngay_lap >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'h.ngay_lap <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        // 5. Tìm kiếm từ khóa: số hóa đơn, mã hóa đơn, mã đặt sân, tên khách hàng, số điện thoại
        if (!empty($filters['q'])) {
            $keyword = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(h.so_hoa_don LIKE :q_num ESCAPE '!' OR h.ma_hoa_don LIKE :q_id ESCAPE '!' OR h.ma_dat_san LIKE :q_b ESCAPE '!' OR k.ho_ten LIKE :q_name ESCAPE '!' OR k.so_dien_thoai LIKE :q_phone ESCAPE '!')";
            $params['q_num'] = $keyword;
            $params['q_id'] = $keyword;
            $params['q_b'] = $keyword;
            $params['q_name'] = $keyword;
            $params['q_phone'] = $keyword;
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // Đếm tổng số
        $countSql = "SELECT COUNT(*)
                     FROM hoa_don h
                     JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san
                     JOIN co_so cs ON cs.ma_co_so = d.ma_co_so
                     JOIN khach_hang k ON k.ma_khach_hang = d.ma_khach_hang" . $whereClause;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = (int) ($filters['per_page'] ?? 10);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $offset = ($page - 1) * $perPage;

        // Lấy danh sách
        $sql = "SELECT h.*,
                       d.ma_co_so,
                       d.ma_khach_hang,
                       d.ngay_tao AS ngay_tao_booking,
                       cs.ten_co_so,
                       cs.ma_cong_ty,
                       k.ho_ten AS ten_khach_hang,
                       k.so_dien_thoai AS sdt_khach_hang,
                       k.email AS email_khach_hang,
                       nv.ho_ten AS ten_nhan_vien,
                       t.ma_giao_dich,
                       t.ngay_thanh_toan
                FROM hoa_don h
                JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san
                JOIN co_so cs ON cs.ma_co_so = d.ma_co_so
                JOIN khach_hang k ON k.ma_khach_hang = d.ma_khach_hang
                LEFT JOIN nhan_vien nv ON nv.ma_nhan_vien = h.ma_nhan_vien
                LEFT JOIN thanh_toan t ON t.ma_thanh_toan = h.ma_thanh_toan
                $whereClause
                ORDER BY h.ma_hoa_don DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * Lấy danh sách hóa đơn của một khách hàng (Phía khách hàng).
     */
    public function customerInvoices(int $customerId, array $filters = []): array
    {
        $where = ['d.ma_khach_hang = :cid'];
        $params = ['cid' => $customerId];

        if (!empty($filters['date_from'])) {
            $where[] = 'h.ngay_lap >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'h.ngay_lap <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $keyword = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(h.so_hoa_don LIKE :q_num ESCAPE '!' OR h.ma_dat_san LIKE :q_b ESCAPE '!')";
            $params['q_num'] = $keyword;
            $params['q_b'] = $keyword;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM hoa_don h
                     JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san" . $whereClause;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = (int) ($filters['per_page'] ?? 10);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT h.*,
                       d.ma_co_so,
                       d.ngay_tao AS ngay_tao_booking,
                       cs.ten_co_so,
                       cs.dia_chi AS dia_chi_co_so,
                       t.ma_giao_dich,
                       t.phuong_thuc,
                       t.ngay_thanh_toan
                FROM hoa_don h
                JOIN dat_san d ON d.ma_dat_san = h.ma_dat_san
                JOIN co_so cs ON cs.ma_co_so = d.ma_co_so
                LEFT JOIN thanh_toan t ON t.ma_thanh_toan = h.ma_thanh_toan
                $whereClause
                ORDER BY h.ma_hoa_don DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * Lấy danh sách cơ sở khả dụng theo Scope phục vụ bộ lọc.
     */
    public function facilities(array $scope): array
    {
        if ($scope['facility'] !== null) {
            $stmt = $this->pdo->prepare('SELECT ma_co_so, ten_co_so FROM co_so WHERE ma_co_so = :id');
            $stmt->execute(['id' => $scope['facility']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($scope['company'] !== null) {
            $stmt = $this->pdo->prepare('SELECT ma_co_so, ten_co_so FROM co_so WHERE ma_cong_ty = :cid ORDER BY ten_co_so ASC');
            $stmt->execute(['cid' => $scope['company']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->query('SELECT ma_co_so, ten_co_so FROM co_so ORDER BY ten_co_so ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
