<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Đọc file .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Load class Database
require_once __DIR__ . '/../core/Database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    echo "Kết nối database thành công!";
} catch (PDOException $e) {
    echo "Kết nối database thất bại: " . $e->getMessage();
}
?>