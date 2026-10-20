<?php

namespace Core;

use App\Models\TaiKhoan;
use App\Services\AuthService;
use PDO;

final class Application
{
    private ?PDO $pdo = null;
    private ?AuthService $auth = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= (new Database())->getConnection();
    }

    public function auth(): AuthService
    {
        return $this->auth ??= new AuthService(new TaiKhoan($this->pdo()));
    }
}
