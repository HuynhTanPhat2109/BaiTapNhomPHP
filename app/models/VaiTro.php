<?php

namespace App\Models;

use PDO;

final class VaiTro
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function idByCode(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT ma_vai_tro FROM vai_tro WHERE ten_vai_tro = :code');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
