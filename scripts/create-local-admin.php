<?php

// No web endpoint, credentials, default password or session changes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!in_array('--local', $argv, true)) {
    fwrite(STDERR, "Usage: php scripts/create-local-admin.php --local\nSet LOCAL_ADMIN_USERNAME and LOCAL_ADMIN_PASSWORD in the process environment.\n");
    exit(1);
}
require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = null;
try {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
    $config = require dirname(__DIR__) . '/config/database.php';
    if (!in_array(strtolower($config['host']), ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('Chỉ cho phép tạo Admin trên database local.');
    }
    $pdo = (new Core\Database())->getConnection();
    $pdo->beginTransaction();
    $role = $pdo->query("SELECT ma_vai_tro FROM vai_tro WHERE ten_vai_tro = 'QUAN_TRI_TAP_DOAN' FOR UPDATE")->fetchColumn();
    if ($role === false) {
        throw new RuntimeException('Thiếu vai trò QUAN_TRI_TAP_DOAN. Không tự bổ sung vai trò.');
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tai_khoan WHERE ma_vai_tro = :role');
    $stmt->execute(['role' => $role]);
    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->commit();
        echo "Đã có Admin. Không tạo thêm hoặc đổi mật khẩu.\n";
        return;
    }
    $username = getenv('LOCAL_ADMIN_USERNAME');
    $password = getenv('LOCAL_ADMIN_PASSWORD');
    if (!is_string($username) || !is_string($password) || $username === '' || $password === '') {
        throw new RuntimeException('Thiếu LOCAL_ADMIN_USERNAME hoặc LOCAL_ADMIN_PASSWORD trong môi trường tiến trình.');
    }
    $id = (new App\Services\AccountService($pdo))->create($username, $password, 'QUAN_TRI_TAP_DOAN');
    $pdo->commit();
    unset($password);
    echo 'Đã tạo một Admin local, mã tài khoản: ' . $id . ".\n";
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof App\Services\ValidationException) {
        fwrite(STDERR, implode(PHP_EOL, $e->errors) . PHP_EOL);
    } elseif (get_class($e) === RuntimeException::class) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
    } else {
        fwrite(STDERR, "Không thể tạo Admin local. Kiểm tra cấu hình và kết nối database.\n");
    }
    exit(1);
}

