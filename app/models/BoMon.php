<?php

namespace App\Models;

use PDO;

final class BoMon
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT b.*, (SELECT COUNT(*) FROM loai_san l WHERE l.ma_bo_mon = b.ma_bo_mon) AS so_loai_san
             FROM bo_mon b ORDER BY b.ma_bo_mon DESC'
        )->fetchAll();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM bo_mon')->fetchColumn();
    }

    public function options(?int $currentId = null): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ma_bo_mon, ten_bo_mon, trang_thai FROM bo_mon
             WHERE trang_thai = 'HOAT_DONG' OR ma_bo_mon = :current ORDER BY ten_bo_mon"
        );
        $stmt->execute(['current' => $currentId ?? 0]);
        return $stmt->fetchAll();
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bo_mon WHERE ma_bo_mon = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function nameExists(string $name, ?int $exceptId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM bo_mon WHERE ten_bo_mon = :name AND ma_bo_mon <> :id LIMIT 1');
        $stmt->execute(['name' => $name, 'id' => $exceptId ?? 0]);
        return $stmt->fetchColumn() !== false;
    }

    public function lockTypes(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT ma_loai_san FROM loai_san WHERE ma_bo_mon = :id ORDER BY ma_loai_san FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function related(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM loai_san WHERE ma_bo_mon = :all_types) AS loai_san,
                (SELECT COUNT(*) FROM loai_san WHERE ma_bo_mon = :active_types AND trang_thai = 'HOAT_DONG') AS loai_san_dang_dung,
                (SELECT COUNT(*) FROM san s JOIN loai_san l ON l.ma_loai_san = s.ma_loai_san
                 WHERE l.ma_bo_mon = :courts AND s.trang_thai IN ('HOAT_DONG', 'BAO_TRI')) AS san_dang_dung,
                (SELECT COUNT(*) FROM bang_gia g JOIN loai_san l ON l.ma_loai_san = g.ma_loai_san
                 WHERE l.ma_bo_mon = :prices AND g.trang_thai = 'DANG_AP_DUNG') AS bang_gia_dang_dung"
        );
        $stmt->execute(['all_types' => $id, 'active_types' => $id, 'courts' => $id, 'prices' => $id]);
        return array_map('intval', $stmt->fetch());
    }

    public function save(array $data, ?int $id): int
    {
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO bo_mon (ten_bo_mon, mo_ta, trang_thai) VALUES (:ten_bo_mon, :mo_ta, :trang_thai)');
        } else {
            $stmt = $this->pdo->prepare('UPDATE bo_mon SET ten_bo_mon = :ten_bo_mon, mo_ta = :mo_ta, trang_thai = :trang_thai WHERE ma_bo_mon = :id');
            $data['id'] = $id;
        }
        $stmt->execute($data);
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bo_mon WHERE ma_bo_mon = :id');
        $stmt->execute(['id' => $id]);
    }
}
