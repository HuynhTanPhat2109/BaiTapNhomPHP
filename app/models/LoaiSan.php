<?php

namespace App\Models;

use PDO;

final class LoaiSan
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $sportId = null): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*, b.ten_bo_mon FROM loai_san l JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon'
            . ($sportId === null ? '' : ' WHERE l.ma_bo_mon = :sport') . ' ORDER BY l.ma_loai_san DESC'
        );
        $stmt->execute($sportId === null ? [] : ['sport' => $sportId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare($lock
            ? 'SELECT * FROM loai_san WHERE ma_loai_san = :id FOR UPDATE'
            : 'SELECT l.*, b.ten_bo_mon, b.trang_thai AS trang_thai_bo_mon
               FROM loai_san l JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon WHERE l.ma_loai_san = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function nameExists(int $sportId, string $name, ?int $exceptId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM loai_san WHERE ma_bo_mon = :sport AND ten_loai_san = :name AND ma_loai_san <> :id LIMIT 1'
        );
        $stmt->execute(['sport' => $sportId, 'name' => $name, 'id' => $exceptId ?? 0]);
        return $stmt->fetchColumn() !== false;
    }

    public function lockReferences(int $id): void
    {
        // Lock existing children as well as the parent before checking statuses.
        foreach (['san' => 'ma_san', 'bang_gia' => 'ma_bang_gia'] as $table => $key) {
            $stmt = $this->pdo->prepare("SELECT $key FROM $table WHERE ma_loai_san = :id ORDER BY $key FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $stmt->fetchAll();
        }
    }

    public function related(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM san WHERE ma_loai_san = :courts) AS san,
                (SELECT COUNT(*) FROM bang_gia WHERE ma_loai_san = :prices) AS bang_gia,
                (SELECT COUNT(*) FROM san WHERE ma_loai_san = :active_courts AND trang_thai IN ('HOAT_DONG', 'BAO_TRI')) AS san_dang_dung,
                (SELECT COUNT(*) FROM bang_gia WHERE ma_loai_san = :active_prices AND trang_thai = 'DANG_AP_DUNG') AS bang_gia_dang_dung"
        );
        $stmt->execute(['courts' => $id, 'prices' => $id, 'active_courts' => $id, 'active_prices' => $id]);
        return array_map('intval', $stmt->fetch());
    }

    public function save(array $data, ?int $id): int
    {
        if ($id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO loai_san (ma_bo_mon, ten_loai_san, mo_ta, trang_thai) VALUES (:ma_bo_mon, :ten_loai_san, :mo_ta, :trang_thai)'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE loai_san SET ma_bo_mon = :ma_bo_mon, ten_loai_san = :ten_loai_san, mo_ta = :mo_ta, trang_thai = :trang_thai WHERE ma_loai_san = :id'
            );
            $data['id'] = $id;
        }
        $stmt->execute($data);
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM loai_san WHERE ma_loai_san = :id');
        $stmt->execute(['id' => $id]);
    }
}
