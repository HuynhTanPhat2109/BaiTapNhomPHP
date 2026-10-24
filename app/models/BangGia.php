<?php

namespace App\Models;

use PDO;

final class BangGia
{
    private const JOINS = ' FROM bang_gia g
        JOIN co_so f ON f.ma_co_so = g.ma_co_so
        JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty
        JOIN loai_san l ON l.ma_loai_san = g.ma_loai_san
        JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon';
    private const COLUMNS = 'g.*, f.ten_co_so, f.trang_thai AS trang_thai_co_so,
        c.ten_cong_ty, c.trang_thai AS trang_thai_cong_ty, l.ten_loai_san, l.ma_bo_mon,
        l.trang_thai AS trang_thai_loai_san, b.ten_bo_mon, b.trang_thai AS trang_thai_bo_mon';

    public function __construct(private PDO $pdo) {}

    public function page(array $filters): array
    {
        return AdminList::page($this->pdo, self::JOINS, self::COLUMNS, 'g.ma_bang_gia',
            ['ma_co_so' => 'g.ma_co_so', 'ma_loai_san' => 'g.ma_loai_san', 'loai_ngay' => 'g.loai_ngay',
                'trang_thai' => 'g.trang_thai', 'q' => 'g.ten_bang_gia'], $filters);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare($lock ? 'SELECT * FROM bang_gia WHERE ma_bang_gia = :id FOR UPDATE'
            : 'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE g.ma_bang_gia = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function types(bool $all = false, ?int $currentId = null): array
    {
        $sql = 'SELECT l.ma_loai_san, l.ten_loai_san, l.trang_thai, b.ten_bo_mon,
            b.trang_thai AS trang_thai_bo_mon FROM loai_san l JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon';
        $stmt = $this->pdo->prepare($sql . ($all ? '' : " WHERE (l.trang_thai = 'HOAT_DONG' AND b.trang_thai = 'HOAT_DONG') OR l.ma_loai_san = :current")
            . ' ORDER BY b.ten_bo_mon, l.ten_loai_san');
        $stmt->execute($all ? [] : ['current' => $currentId ?? 0]);
        return $stmt->fetchAll();
    }

    public function candidates(array $data, ?int $id): array
    {
        $sql = "SELECT * FROM bang_gia WHERE ma_co_so = :facility AND ma_loai_san = :type
            AND loai_ngay = :day AND trang_thai = 'DANG_AP_DUNG' AND ma_bang_gia <> :id
            AND gio_bat_dau < :ending AND gio_ket_thuc > :opening
            AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= :first_date)";
        $params = ['facility' => $data['ma_co_so'], 'type' => $data['ma_loai_san'], 'day' => $data['loai_ngay'],
            'id' => $id ?? 0, 'ending' => $data['gio_ket_thuc'], 'opening' => $data['gio_bat_dau'], 'first_date' => $data['ngay_bat_dau']];
        if ($data['ngay_ket_thuc'] !== null) {
            $sql .= ' AND ngay_bat_dau <= :last_date';
            $params['last_date'] = $data['ngay_ket_thuc'];
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY ma_bang_gia FOR UPDATE');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function save(array $data, ?int $id): int
    {
        if ($id === null) {
            $sql = 'INSERT INTO bang_gia (ma_co_so, ma_loai_san, ten_bang_gia, loai_ngay, gio_bat_dau,
                gio_ket_thuc, don_gia, ngay_bat_dau, ngay_ket_thuc, trang_thai) VALUES (:ma_co_so, :ma_loai_san,
                :ten_bang_gia, :loai_ngay, :gio_bat_dau, :gio_ket_thuc, :don_gia, :ngay_bat_dau, :ngay_ket_thuc, :trang_thai)';
        } else {
            $sql = 'UPDATE bang_gia SET ma_co_so = :ma_co_so, ma_loai_san = :ma_loai_san, ten_bang_gia = :ten_bang_gia,
                loai_ngay = :loai_ngay, gio_bat_dau = :gio_bat_dau, gio_ket_thuc = :gio_ket_thuc, don_gia = :don_gia,
                ngay_bat_dau = :ngay_bat_dau, ngay_ket_thuc = :ngay_ket_thuc, trang_thai = :trang_thai WHERE ma_bang_gia = :id';
            $data['id'] = $id;
        }
        $this->pdo->prepare($sql)->execute($data);
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function changeStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE bang_gia SET trang_thai = :status WHERE ma_bang_gia = :id')
            ->execute(['status' => $status, 'id' => $id]);
    }
}
