<?php

namespace App\Models;

use PDO;

final class DichVu
{
    private const FROM = ' FROM dich_vu d JOIN co_so f ON f.ma_co_so = d.ma_co_so
        JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty';
    private const COLUMNS = 'd.*, f.ten_co_so, f.trang_thai AS trang_thai_co_so,
        c.ten_cong_ty, c.trang_thai AS trang_thai_cong_ty';

    public function __construct(private PDO $pdo) {}

    // Scope is derived by DichVuService from the authenticated identity, never request filters.
    private static function scope(array $scope): array
    {
        $where = [];
        $params = [];
        foreach (['company' => 'f.ma_cong_ty', 'facility' => 'f.ma_co_so'] as $field => $column) {
            if ($scope[$field] !== null) {
                $where[] = "$column = :scope_$field";
                $params['scope_' . $field] = $scope[$field];
            }
        }
        return [$where, $params];
    }

    public function page(array $filters, array $scope): array
    {
        $fields = ['q' => 'd.ten_dich_vu', 'ma_co_so' => 'd.ma_co_so', 'loai_dich_vu' => 'd.loai_dich_vu',
            'trang_thai' => 'd.trang_thai', 'don_vi_tinh' => 'd.don_vi_tinh',
            'scope_company' => 'f.ma_cong_ty', 'scope_facility' => 'f.ma_co_so'];
        $filters = array_replace($filters, ['scope_company' => $scope['company'] ?? '', 'scope_facility' => $scope['facility'] ?? '']);
        return AdminList::page($this->pdo, self::FROM, self::COLUMNS, 'd.ma_dich_vu', $fields, $filters);
    }

    public function find(int $id, array $scope, bool $lock = false): ?array
    {
        [$where, $params] = self::scope($scope);
        $where[] = 'd.ma_dich_vu = :id';
        $stmt = $this->pdo->prepare(($lock ? 'SELECT d.* FROM dich_vu d JOIN co_so f ON f.ma_co_so = d.ma_co_so'
            : 'SELECT ' . self::COLUMNS . self::FROM) . ' WHERE ' . implode(' AND ', $where) . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute($params + ['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function facilities(array $scope, bool $activeOnly = false, ?int $current = null): array
    {
        [$where, $params] = self::scope($scope);
        if ($activeOnly) {
            $where[] = "((f.trang_thai = 'HOAT_DONG' AND c.trang_thai = 'HOAT_DONG') OR f.ma_co_so = :current)";
            $params['current'] = $current ?? 0;
        }
        $stmt = $this->pdo->prepare('SELECT f.ma_co_so, f.ma_cong_ty, f.ten_co_so, f.trang_thai,
            c.ten_cong_ty, c.trang_thai AS trang_thai_cong_ty FROM co_so f
            JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.ten_cong_ty, f.ten_co_so, f.ma_co_so');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function facility(int $id, array $scope): ?array
    {
        [$where, $params] = self::scope($scope);
        $where[] = 'f.ma_co_so = :id';
        $stmt = $this->pdo->prepare('SELECT f.* FROM co_so f WHERE ' . implode(' AND ', $where));
        $stmt->execute($params + ['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function units(array $scope): array
    {
        [$where, $params] = self::scope($scope);
        $stmt = $this->pdo->prepare('SELECT DISTINCT d.don_vi_tinh' . self::FROM
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY d.don_vi_tinh');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function references(int $id, bool $lock = false): array
    {
        $stmt = $this->pdo->prepare('SELECT ct.ma_chi_tiet_dv, b.trang_thai_dat_san
            FROM chi_tiet_dich_vu_dat ct LEFT JOIN dat_san b ON b.ma_dat_san = ct.ma_dat_san
            WHERE ct.ma_dich_vu = :id ORDER BY ct.ma_chi_tiet_dv' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        $rows = $stmt->fetchAll();
        // Conservative: unfinished includes drafts, payment pending and confirmed bookings.
        $unfinished = count(array_filter($rows, static fn($row) => !in_array($row['trang_thai_dat_san'], ['HOAN_THANH', 'DA_HUY', 'HET_HAN'], true)));
        return ['total' => count($rows), 'unfinished' => $unfinished];
    }

    public function nameExists(int $facility, string $name, ?int $except): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM dich_vu WHERE ma_co_so = :facility AND ten_dich_vu = :name AND ma_dich_vu <> :id LIMIT 1');
        $stmt->execute(['facility' => $facility, 'name' => $name, 'id' => $except ?? 0]);
        return $stmt->fetchColumn() !== false;
    }

    public function save(array $data, ?int $id): int
    {
        if ($id === null) {
            $sql = 'INSERT INTO dich_vu (ma_co_so, ten_dich_vu, loai_dich_vu, don_vi_tinh, don_gia, so_luong_ton, mo_ta, trang_thai)
                VALUES (:ma_co_so, :ten_dich_vu, :loai_dich_vu, :don_vi_tinh, :don_gia, :so_luong_ton, :mo_ta, :trang_thai)';
        } else {
            $sql = 'UPDATE dich_vu SET ma_co_so = :ma_co_so, ten_dich_vu = :ten_dich_vu, loai_dich_vu = :loai_dich_vu,
                don_vi_tinh = :don_vi_tinh, don_gia = :don_gia, so_luong_ton = :so_luong_ton, mo_ta = :mo_ta, trang_thai = :trang_thai
                WHERE ma_dich_vu = :id';
            $data['id'] = $id;
        }
        $this->pdo->prepare($sql)->execute($data);
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function changeStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE dich_vu SET trang_thai = :status WHERE ma_dich_vu = :id')->execute(['status' => $status, 'id' => $id]);
    }
}
