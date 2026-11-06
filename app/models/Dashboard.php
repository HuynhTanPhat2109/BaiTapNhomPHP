<?php
namespace App\Models;

use PDO;

final class Dashboard
{
    public function __construct(private PDO $pdo) {}

    private ?bool $manualReceipts = null;
    private function manualReceipts(): bool { return $this->manualReceipts ??= (new ThanhToan($this->pdo))->supportsManualReceipt(); }
    private function receivedAt(): string { return $this->manualReceipts() ? 'COALESCE(p.ngay_thanh_toan, p.xac_nhan_thu_cong_luc)' : 'p.ngay_thanh_toan'; }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function scope(array $scope): array
    {
        $where = '1 = 1'; $params = [];
        if ($scope['company'] !== null) { $where .= ' AND f.ma_cong_ty = :company'; $params['company'] = $scope['company']; }
        if ($scope['facility'] !== null) { $where .= ' AND f.ma_co_so = :facility'; $params['facility'] = $scope['facility']; }
        return [$where, $params];
    }

    public function companies(?int $id): array
    {
        return $this->rows('SELECT ma_cong_ty, ten_cong_ty FROM cong_ty_nhuong_quyen'
            . ($id === null ? '' : ' WHERE ma_cong_ty = :id') . ' ORDER BY ten_cong_ty, ma_cong_ty', $id === null ? [] : ['id' => $id]);
    }

    public function facilities(array $scope): array
    {
        [$where, $params] = $this->scope($scope);
        return $this->rows('SELECT f.ma_co_so, f.ma_cong_ty, f.ten_co_so, f.trang_thai, c.ten_cong_ty
            FROM co_so f JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty WHERE ' . $where . ' ORDER BY f.ten_co_so, f.ma_co_so', $params);
    }

    // One payment per booking (UNIQUE ma_dat_san). Never join invoices or raw detail rows here.
    // Booking totals are the frozen amounts also used by InvoiceService, not current catalog prices.
    private function paid(array $scope, ?array $range): array
    {
        [$where, $params] = $this->scope($scope);
        $receivedAt = $this->receivedAt();
        $evidence = "(p.ngay_thanh_toan IS NOT NULL AND p.ngay_thanh_toan >= '1000-01-01 00:00:00'
            AND p.ma_giao_dich IS NOT NULL AND TRIM(p.ma_giao_dich) <> '')";
        if ($this->manualReceipts()) $evidence = '(' . $evidence . ' OR (p.xac_nhan_thu_cong_luc IS NOT NULL
            AND p.ma_tai_khoan_xac_nhan IS NOT NULL AND p.anh_minh_chung IS NOT NULL))';
        $sql = " FROM thanh_toan p JOIN dat_san d ON d.ma_dat_san = p.ma_dat_san
            JOIN co_so f ON f.ma_co_so = d.ma_co_so
            JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty
            WHERE $where AND p.trang_thai = 'THANH_CONG'
            AND d.trang_thai_dat_san IN ('DA_XAC_NHAN', 'HOAN_THANH')
            AND $evidence
            AND p.so_tien_thuc_nhan = p.so_tien AND p.so_tien = d.tong_tien_tam_tinh
            AND d.tong_tien_tam_tinh = d.tong_tien_san + d.tong_tien_dich_vu
            AND d.tong_tien_san >= 0 AND d.tong_tien_dich_vu >= 0";
        if ($range !== null) {
            $sql .= " AND $receivedAt >= :start AND $receivedAt < :end";
            $params += ['start' => $range['start'], 'end' => $range['end']];
        }
        return [$sql, $params];
    }

    public function revenue(array $scope, ?array $range, string $group = ''): array
    {
        [$from, $params] = $this->paid($scope, $range);
        $receivedAt = $this->receivedAt();
        $key = match ($group) {
            'company' => 'c.ma_cong_ty', 'facility' => 'f.ma_co_so',
            'day' => "SUBSTR($receivedAt, 1, 10)", 'month' => "SUBSTR($receivedAt, 1, 7)",
            default => null,
        };
        return $this->rows('SELECT ' . ($key ? "$key AS bucket, " : '')
            . 'COUNT(*) AS bookings, COALESCE(ROUND(SUM(d.tong_tien_san), 2), 0) AS courts,
            COALESCE(ROUND(SUM(d.tong_tien_dich_vu), 2), 0) AS services,
            COALESCE(ROUND(SUM(p.so_tien_thuc_nhan), 2), 0) AS total'
            . $from . ($key ? " GROUP BY $key ORDER BY $key" : ''), $params);
    }

    public function bookingStates(array $scope, array $range, ?string $cutoff = null): array
    {
        [$where, $params] = $this->scope($scope);
        $cutoff ??= (string) $this->pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        $effectiveStatus = str_replace('?', $this->pdo->quote($cutoff), Booking::effectiveStatusSql());
        return $this->rows('SELECT ' . $effectiveStatus . ' AS status, COUNT(*) AS total
            FROM dat_san b JOIN co_so f ON f.ma_co_so = b.ma_co_so WHERE ' . $where
            . ' AND b.ngay_tao >= :start AND b.ngay_tao < :end GROUP BY status',
            $params + ['start' => $range['start'], 'end' => $range['end']]);
    }

    public function reviewFunds(array $scope, array $range): array
    {
        [$where, $params] = $this->scope($scope);
        // Report recorded amounts only. These are not settled revenue or a refund ledger.
        return $this->rows("SELECT p.trang_thai AS status, COUNT(*) AS payments,
            COALESCE(ROUND(SUM(p.so_tien_thuc_nhan), 2), 0) AS total
            FROM thanh_toan p JOIN dat_san d ON d.ma_dat_san = p.ma_dat_san
            JOIN co_so f ON f.ma_co_so = d.ma_co_so WHERE $where
            AND p.ngay_thanh_toan >= :start AND p.ngay_thanh_toan < :end AND p.so_tien_thuc_nhan > 0
            AND (p.trang_thai = 'CAN_DOI_SOAT' OR (p.trang_thai IN ('THANH_CONG', 'HUY') AND d.trang_thai_dat_san IN ('DA_HUY', 'HET_HAN')))
            GROUP BY p.trang_thai", $params + ['start' => $range['start'], 'end' => $range['end']]);
    }

    public function transactions(array $scope, array $range): array
    {
        [$from, $params] = $this->paid($scope, $range);
        $receivedAt = $this->receivedAt();
        return $this->rows('SELECT d.ma_dat_san, f.ten_co_so, p.ngay_thanh_toan, ' . $receivedAt . ' AS ngay_ghi_nhan, p.ma_giao_dich,
            p.so_tien_thuc_nhan, d.tong_tien_san, d.tong_tien_dich_vu,
            (SELECT MIN(h.so_hoa_don) FROM hoa_don h WHERE h.ma_dat_san = d.ma_dat_san AND h.ma_thanh_toan = p.ma_thanh_toan) AS so_hoa_don'
            . $from . " ORDER BY $receivedAt DESC, p.ma_thanh_toan DESC LIMIT 50", $params);
    }

    public function assets(array $scope): array
    {
        [$where, $params] = $this->scope($scope);
        $courts = $this->rows("SELECT COUNT(*) AS total FROM san s JOIN co_so f ON f.ma_co_so = s.ma_co_so WHERE $where AND s.trang_thai = 'HOAT_DONG'", $params)[0]['total'];
        $services = $this->rows("SELECT COUNT(*) AS total FROM dich_vu v JOIN co_so f ON f.ma_co_so = v.ma_co_so WHERE $where AND v.trang_thai = 'DANG_CUNG_CAP'", $params)[0]['total'];
        return ['courts' => (int) $courts, 'services' => (int) $services];
    }

    public function recent(array $scope, array $range, ?string $cutoff = null): array
    {
        [$where, $params] = $this->scope($scope);
        $cutoff ??= (string) $this->pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        $quotedCutoff = $this->pdo->quote($cutoff);
        $effectiveStatus = str_replace('?', $quotedCutoff, Booking::effectiveStatusSql());
        $effectivePayment = str_replace('?', $quotedCutoff, Booking::effectivePaymentStatusSql());
        return $this->rows('SELECT b.ma_dat_san, b.ngay_tao, ' . $effectiveStatus . ' AS trang_thai_dat_san, b.tong_tien_tam_tinh,
            ' . $effectivePayment . ' AS payment_status, f.ten_co_so FROM dat_san b
            JOIN co_so f ON f.ma_co_so = b.ma_co_so LEFT JOIN thanh_toan tt ON tt.ma_dat_san = b.ma_dat_san
            WHERE ' . $where . ' AND b.ngay_tao >= :start AND b.ngay_tao < :end
            ORDER BY b.ngay_tao DESC, b.ma_dat_san DESC LIMIT 8', $params + ['start' => $range['start'], 'end' => $range['end']]);
    }

    public function popular(array $scope, array $range, bool $services): array
    {
        [$from, $params] = $this->paid($scope, $range);
        // EXISTS filters the already aggregated detail rows; no multiplication by payment or invoice lines.
        $table = $services ? 'chi_tiet_dich_vu_dat' : 'chi_tiet_dat_san';
        $id = $services ? 'ma_dich_vu' : 'ma_san';
        $name = $services ? 'ten_dich_vu_snapshot' : 'ten_san_snapshot';
        $quantity = $services ? 'SUM(ct.so_luong)' : 'COUNT(DISTINCT ct.ma_dat_san)';
        $unit = $services ? ', ct.don_vi_tinh_snapshot' : '';
        return $this->rows("SELECT ct.$id AS id, MIN(ct.$name) AS name$unit, $quantity AS quantity
            FROM $table ct WHERE EXISTS (SELECT 1 $from AND d.ma_dat_san = ct.ma_dat_san)
            GROUP BY ct.$id$unit ORDER BY quantity DESC, ct.$id LIMIT 5", $params);
    }

    public function catalog(): array
    {
        return ['bo_mon' => (int) $this->pdo->query('SELECT COUNT(*) FROM bo_mon')->fetchColumn(),
            'loai_san' => (int) $this->pdo->query('SELECT COUNT(*) FROM loai_san')->fetchColumn()];
    }
}
