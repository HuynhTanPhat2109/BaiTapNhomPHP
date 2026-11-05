<?php

namespace App\Models;

use PDO;

final class AdminBooking
{
    public function __construct(private PDO $pdo) {}

    /** IDs come only from the already scoped page; no extra rows are exposed to the client. */
    public function paymentsFor(array $ids): array
    {
        if (!$ids) return [];
        $stmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute(array_map('intval', $ids));
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int) $row['ma_dat_san']] = $row;
        return $rows;
    }

    /**
     * Lấy danh sách booking có phân trang, lọc đa tiêu chí và phân quyền phạm vi.
     *
     * @param array $filters Các tham số lọc đã được validate từ Service
     * @param array $scope Phạm vi phân quyền (company, facility)
     * @return array ['items' => array, 'total' => int, 'page' => int, 'pages' => int]
     */
    public function page(array $filters, array $scope): array
    {
        $where = [];
        $params = [];

        // 1. Phân quyền dữ liệu (Scope) từ Server Identity
        if ($scope['company'] !== null) {
            $where[] = 'cs.ma_cong_ty = :scope_company';
            $params['scope_company'] = $scope['company'];
        }
        if ($scope['facility'] !== null) {
            $where[] = 'b.ma_co_so = :scope_facility';
            $params['scope_facility'] = $scope['facility'];
        }

        // 2. Lọc theo cơ sở cụ thể (nếu có quyền chọn cơ sở)
        if (!empty($filters['facility_id'])) {
            $where[] = 'b.ma_co_so = :facility_id';
            $params['facility_id'] = (int) $filters['facility_id'];
        }

        // 3. Lọc theo trạng thái booking
        if (!empty($filters['booking_status'])) {
            $where[] = 'b.trang_thai_dat_san = :booking_status';
            $params['booking_status'] = $filters['booking_status'];
        }

        // 4. Lọc theo trạng thái thanh toán
        if (!empty($filters['payment_status'])) {
            if ($filters['payment_status'] === 'CHO_XAC_NHAN') {
                $where[] = "b.trang_thai_dat_san = 'CHO_XAC_NHAN' AND tt.trang_thai = 'CHO_THANH_TOAN'";
            } elseif ($filters['payment_status'] === 'CHO_THANH_TOAN') {
                $where[] = "b.trang_thai_dat_san = 'CHO_THANH_TOAN' AND tt.trang_thai = 'CHO_THANH_TOAN'";
            } else {
                $where[] = 'tt.trang_thai = :payment_status';
                $params['payment_status'] = $filters['payment_status'];
            }
        }

        // 5. Lọc theo loại ngày: Ngày chơi (chi_tiet_dat_san.ngay_choi) vs Ngày tạo đơn (dat_san.ngay_tao)
        $dateType = $filters['date_type'] ?? 'ngay_choi';
        if (!empty($filters['date_from'])) {
            if ($dateType === 'ngay_tao') {
                $where[] = 'b.ngay_tao >= :date_from';
                $params['date_from'] = $filters['date_from'] . ' 00:00:00';
            } else {
                $where[] = 'ct.ngay_choi >= :date_from';
                $params['date_from'] = $filters['date_from'];
            }
        }
        if (!empty($filters['date_to'])) {
            if ($dateType === 'ngay_tao') {
                $where[] = 'b.ngay_tao <= :date_to';
                $params['date_to'] = $filters['date_to'] . ' 23:59:59';
            } else {
                $where[] = 'ct.ngay_choi <= :date_to';
                $params['date_to'] = $filters['date_to'];
            }
        }

        // 6. Lọc theo sân cụ thể
        if (!empty($filters['court_id'])) {
            $where[] = 'ct.ma_san = :court_id';
            $params['court_id'] = (int) $filters['court_id'];
        }

        // 7. Tìm kiếm theo mã booking, mã tham chiếu hoặc tên khách hàng
        if (!empty($filters['q'])) {
            $keyword = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(b.ma_dat_san LIKE :q_id ESCAPE '!' OR tt.ma_tham_chieu LIKE :q_ref ESCAPE '!' OR k.ho_ten LIKE :q_name ESCAPE '!' OR k.so_dien_thoai LIKE :q_phone ESCAPE '!')";
            $params['q_id'] = $keyword;
            $params['q_ref'] = $keyword;
            $params['q_name'] = $keyword;
            $params['q_phone'] = $keyword;
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // Đếm tổng số booking phù hợp
        $countSql = "SELECT COUNT(DISTINCT b.ma_dat_san)
                     FROM dat_san b
                     JOIN khach_hang k ON k.ma_khach_hang = b.ma_khach_hang
                     JOIN co_so cs ON cs.ma_co_so = b.ma_co_so
                     LEFT JOIN thanh_toan tt ON tt.ma_dat_san = b.ma_dat_san
                     LEFT JOIN chi_tiet_dat_san ct ON ct.ma_dat_san = b.ma_dat_san" . $whereClause;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = (int) ($filters['per_page'] ?? 10);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $offset = ($page - 1) * $perPage;

        // Truy vấn danh sách booking và tổng hợp sân
        $sql = "SELECT b.ma_dat_san,
                       b.ngay_tao,
                       b.bat_dau_giu_san_luc,
                       b.het_han_giu_san_luc,
                       b.tong_tien_san,
                       b.tong_tien_dich_vu,
                       b.tong_tien_tam_tinh,
                       b.trang_thai_dat_san,
                       b.ghi_chu AS ghi_chu_booking,
                       k.ma_khach_hang,
                       k.ho_ten AS ten_khach_hang,
                       k.so_dien_thoai AS sdt_khach_hang,
                       k.email AS email_khach_hang,
                       cs.ma_co_so,
                       cs.ten_co_so,
                       cs.ma_cong_ty,
                       tt.ma_thanh_toan,
                       tt.ma_tham_chieu,
                       tt.so_tien AS so_tien_thanh_toan,
                       tt.so_tien_thuc_nhan,
                       tt.ma_giao_dich,
                       tt.ngay_thanh_toan,
                       tt.ngay_tao AS ngay_tao_thanh_toan,
                       tt.trang_thai AS trang_thai_thanh_toan,
                       nv.ho_ten AS ten_nhan_vien_xac_nhan,
                       MIN(ct.ngay_choi) AS ngay_choi_dau,
                       MAX(ct.ngay_choi) AS ngay_choi_cuoi,
                       MIN(ct.gio_bat_dau) AS gio_bat_dau_som,
                       MAX(ct.gio_ket_thuc) AS gio_ket_thuc_muon,
                       GROUP_CONCAT(DISTINCT ct.ten_san_snapshot ORDER BY ct.ten_san_snapshot SEPARATOR ', ') AS cac_san
                FROM dat_san b
                JOIN khach_hang k ON k.ma_khach_hang = b.ma_khach_hang
                JOIN co_so cs ON cs.ma_co_so = b.ma_co_so
                LEFT JOIN thanh_toan tt ON tt.ma_dat_san = b.ma_dat_san
                LEFT JOIN nhan_vien nv ON nv.ma_nhan_vien = b.ma_nhan_vien
                LEFT JOIN chi_tiet_dat_san ct ON ct.ma_dat_san = b.ma_dat_san
                $whereClause
                GROUP BY b.ma_dat_san, b.ngay_tao, b.bat_dau_giu_san_luc, b.het_han_giu_san_luc,
                         b.tong_tien_san, b.tong_tien_dich_vu, b.tong_tien_tam_tinh, b.trang_thai_dat_san,
                         b.ghi_chu, k.ma_khach_hang, k.ho_ten, k.so_dien_thoai, k.email,
                         cs.ma_co_so, cs.ten_co_so, cs.ma_cong_ty,
                         tt.ma_thanh_toan, tt.ma_tham_chieu, tt.so_tien, tt.so_tien_thuc_nhan,
                         tt.ma_giao_dich, tt.ngay_thanh_toan, tt.ngay_tao, tt.trang_thai, nv.ho_ten
                ORDER BY b.ma_dat_san DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * Lấy thông tin chi tiết đầy đủ của một booking kèm kiểm tra quyền scope.
     */
    public function find(int $bookingId, array $scope): ?array
    {
        $sql = "SELECT b.*,
                       k.ho_ten AS ten_khach_hang,
                       k.so_dien_thoai AS sdt_khach_hang,
                       k.email AS email_khach_hang,
                       k.gioi_tinh AS gioi_tinh_khach,
                       k.ngay_sinh AS ngay_sinh_khach,
                       cs.ten_co_so,
                       cs.dia_chi AS dia_chi_co_so,
                       cs.so_dien_thoai AS sdt_co_so,
                       cs.email AS email_co_so,
                       cs.ma_cong_ty,
                       c.ten_cong_ty,
                       tt.ma_thanh_toan,
                       tt.ma_tham_chieu,
                       tt.so_tien AS so_tien_can_thanh_toan,
                       tt.so_tien_thuc_nhan,
                       tt.phuong_thuc,
                       tt.trang_thai AS trang_thai_thanh_toan,
                       tt.ngay_tao AS ngay_tao_thanh_toan,
                       tt.ngay_thanh_toan,
                       tt.ma_giao_dich,
                       tt.ghi_chu AS ghi_chu_thanh_toan,
                       nv.ho_ten AS ten_nhan_vien_xac_nhan,
                       nv.chuc_vu AS chuc_vu_nhan_vien
                FROM dat_san b
                JOIN khach_hang k ON k.ma_khach_hang = b.ma_khach_hang
                JOIN co_so cs ON cs.ma_co_so = b.ma_co_so
                JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = cs.ma_cong_ty
                LEFT JOIN thanh_toan tt ON tt.ma_dat_san = b.ma_dat_san
                LEFT JOIN nhan_vien nv ON nv.ma_nhan_vien = b.ma_nhan_vien
                WHERE b.ma_dat_san = :id";

        $params = ['id' => $bookingId];

        // Kiểm tra phân quyền truy cập
        if ($scope['company'] !== null) {
            $sql .= ' AND cs.ma_cong_ty = :scope_company';
            $params['scope_company'] = $scope['company'];
        }
        if ($scope['facility'] !== null) {
            $sql .= ' AND b.ma_co_so = :scope_facility';
            $params['scope_facility'] = $scope['facility'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        return $booking ?: null;
    }

    /**
     * Lấy danh sách các slot sân đã đặt của booking.
     */
    public function courts(int $bookingId): array
    {
        $sql = "SELECT ct.*,
                       s.ten_san,
                       s.vi_tri,
                       l.ten_loai_san,
                       m.ten_bo_mon
                FROM chi_tiet_dat_san ct
                JOIN san s ON s.ma_san = ct.ma_san
                JOIN loai_san l ON l.ma_loai_san = s.ma_loai_san
                JOIN bo_mon m ON m.ma_bo_mon = l.ma_bo_mon
                WHERE ct.ma_dat_san = :id
                ORDER BY ct.ngay_choi, ct.ma_san, ct.gio_bat_dau";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách dịch vụ đính kèm của booking.
     */
    public function services(int $bookingId): array
    {
        $sql = "SELECT ctdv.*,
                       d.loai_dich_vu,
                       d.so_luong_ton
                FROM chi_tiet_dich_vu_dat ctdv
                JOIN dich_vu d ON d.ma_dich_vu = ctdv.ma_dich_vu
                WHERE ctdv.ma_dat_san = :id
                ORDER BY ctdv.ma_chi_tiet_dv";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy thông tin tài khoản ngân hàng nhận tiền của cơ sở.
     */
    public function bankAccount(int $facilityId): ?array
    {
        $sql = "SELECT * FROM tai_khoan_ngan_hang_co_so
                WHERE ma_co_so = :facility_id AND trang_thai = 'HOAT_DONG'
                ORDER BY la_mac_dinh DESC, ma_tk_ngan_hang ASC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['facility_id' => $facilityId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Lấy danh sách các cơ sở được phép xem để hiển thị trong dropdown bộ lọc.
     */
    public function facilities(array $scope): array
    {
        $sql = "SELECT ma_co_so, ten_co_so FROM co_so WHERE trang_thai = 'HOAT_DONG'";
        $params = [];

        if ($scope['company'] !== null) {
            $sql .= ' AND ma_cong_ty = :company';
            $params['company'] = $scope['company'];
        }
        if ($scope['facility'] !== null) {
            $sql .= ' AND ma_co_so = :facility';
            $params['facility'] = $scope['facility'];
        }

        $sql .= ' ORDER BY ten_co_so';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách sân của cơ sở để hiển thị trong dropdown bộ lọc.
     */
    public function courtsByFacility(?int $facilityId, array $scope): array
    {
        if (!$facilityId) {
            return [];
        }
        $sql = 'SELECT s.ma_san, s.ten_san FROM san s JOIN co_so f ON f.ma_co_so = s.ma_co_so WHERE s.ma_co_so = :facility_id';
        $params = ['facility_id' => $facilityId];
        if ($scope['company'] !== null) {
            $sql .= ' AND f.ma_cong_ty = :scope_company';
            $params['scope_company'] = $scope['company'];
        }
        if ($scope['facility'] !== null) {
            $sql .= ' AND s.ma_co_so = :scope_facility';
            $params['scope_facility'] = $scope['facility'];
        }
        $sql .= ' ORDER BY s.ten_san';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
