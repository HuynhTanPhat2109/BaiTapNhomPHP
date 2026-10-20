<?php

// Explicitly opt in: creates one uniquely named customer through HTTP, then
// removes ONLY that fixture. Does not import or change the schema.
if (PHP_SAPI !== 'cli' || !in_array('--run', $argv, true)) {
    exit("Usage: php tests/auth-integration.php --run\n");
}
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$pdo = (new Core\Database())->getConnection();
$base = rtrim(getenv('AUTH_TEST_URL') ?: 'http://localhost/BaiTapNhomPHP/public', '/');
$username = 'pr2_' . bin2hex(random_bytes(4));
$password = 'Pr2!' . bin2hex(random_bytes(4));
$email = $username . '@example.test';
$id = null;
$checks = 0;
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
    CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);

function verifyIntegration(bool $value, string $label): void
{
    global $checks;
    if (!$value) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
}
function requestIntegration(string $route, ?array $fields = null): array
{
    global $base, $client;
    curl_setopt($client, CURLOPT_URL, $base . '/index.php?route=' . rawurlencode($route));
    curl_setopt($client, CURLOPT_POST, $fields !== null);
    if ($fields !== null) { curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields)); }
    $raw = curl_exec($client);
    if ($raw === false) { throw new RuntimeException('HTTP connection failed.'); }
    $headerLength = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'headers' => substr($raw, 0, $headerLength), 'body' => substr($raw, $headerLength)];
}
function integrationToken(array $response): string
{
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('Missing CSRF token.');
    }
    return $match[1];
}
function integrationSessionId(): string
{
    global $client;
    foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $cookie) {
        $parts = explode("\t", $cookie);
        if (($parts[5] ?? '') === 'sports_session') { return $parts[6]; }
    }
    return '';
}
function updateFixture(string $field, $value): void
{
    global $pdo, $id, $username;
    if (!in_array($field, ['ma_vai_tro', 'trang_thai'], true) || !$id) {
        throw new RuntimeException('Invalid fixture update.');
    }
    $stmt = $pdo->prepare('UPDATE tai_khoan SET ' . $field . ' = :value WHERE ma_tai_khoan = :id AND ten_dang_nhap = :username');
    $stmt->execute(['value' => $value, 'id' => $id, 'username' => $username]);
}
try {
    $form = requestIntegration('/register');
    verifyIntegration($form['status'] === 200, 'register form');
    $token = integrationToken($form);
    $fields = ['_token' => $token, 'username' => $username, 'name' => '<svg onload=alert(1)>',
        'phone' => '0901234567', 'email' => $email, 'password' => $password,
        'password_confirmation' => $password, 'role' => 'QUAN_TRI_TAP_DOAN'];
    $bad = $fields; $bad['phone'] = '1901234567';
    $response = requestIntegration('/register', $bad);
    verifyIntegration($response['status'] === 422 && strpos($response['body'], 'id="phone-error"') !== false, 'phone must start with zero');
    $bad = $fields; $bad['email'] = ['invalid'];
    $response = requestIntegration('/register', $bad);
    verifyIntegration($response['status'] === 422 && strpos($response['body'], 'id="email-error"') !== false, 'array email rejected without server error');
    $bad = $fields; unset($bad['_token']);
    verifyIntegration(requestIntegration('/register', $bad)['status'] === 403, 'missing CSRF blocks registration');
    verifyIntegration(requestIntegration('/register', $fields)['status'] === 303, 'registration success');
    $stmt = $pdo->prepare('SELECT tk.*, vt.ten_vai_tro FROM tai_khoan tk JOIN vai_tro vt ON vt.ma_vai_tro=tk.ma_vai_tro WHERE ten_dang_nhap = :username');
    $stmt->execute(['username' => $username]);
    $account = $stmt->fetch();
    verifyIntegration((bool) $account, 'account persisted');
    $id = (int) $account['ma_tai_khoan'];
    verifyIntegration($account['ten_vai_tro'] === 'KHACH_HANG', 'role injection ignored');
    verifyIntegration($account['mat_khau'] !== $password && password_verify($password, $account['mat_khau']), 'hash stored and verifiable');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM khach_hang WHERE ma_tai_khoan = :id');
    $stmt->execute(['id' => $id]);
    verifyIntegration((int) $stmt->fetchColumn() === 1, 'one customer profile');
    $duplicate = requestIntegration('/register', $fields);
    verifyIntegration($duplicate['status'] === 422 && strpos($duplicate['body'], 'id="username-error"') !== false, 'duplicate account rejected');
    $before = integrationSessionId();
    $loginForm = requestIntegration('/login');
    $token = integrationToken($loginForm);
    $wrong = requestIntegration('/login', ['_token' => $token, 'username' => $username, 'password' => 'Wrong1!x']);
    verifyIntegration($wrong['status'] === 422 && strpos($wrong['body'], 'Đăng nhập thất bại: Tài khoản hoặc mật khẩu bị sai') !== false, 'generic failed login');
    verifyIntegration(strpos($wrong['body'], 'aria-invalid="true"') === false, 'wrong credentials do not mark password invalid');
    $injection = requestIntegration('/login', ['_token' => $token, 'username' => "' OR 1=1 --", 'password' => 'Wrong1!x']);
    verifyIntegration($injection['status'] === 422, 'SQL injection cannot log in');
    verifyIntegration(requestIntegration('/account')['status'] === 303, 'failed attempts not authenticated');
    verifyIntegration(requestIntegration('/account/settings')['status'] === 303, 'settings requires login');
    verifyIntegration(requestIntegration('/login', ['_token' => $token, 'username' => $username, 'password' => $password])['status'] === 303, 'login success');
    verifyIntegration($before !== integrationSessionId(), 'session ID rotated');
    $accountPage = requestIntegration('/account');
    verifyIntegration($accountPage['status'] === 200 && strpos($accountPage['body'], '&lt;svg onload=alert(1)&gt;') !== false
        && strpos($accountPage['body'], '<svg onload=alert(1)>') === false, 'stored XSS escaped');
    verifyIntegration(strpos($accountPage['body'], 'class="account-menu__name" title="' . $username . '">' . $username . '</span>') !== false,
        'navbar displays escaped login name');
    $settingsPage = requestIntegration('/account/settings');
    verifyIntegration($settingsPage['status'] === 200 && strpos($settingsPage['body'], 'id="settings-title"') !== false
        && strpos($accountPage['body'], 'id="settings-title"') === false,
        'settings and personal information are separate pages');
    $profileToken = integrationToken($accountPage);
    $profile = ['name' => "O'Neil <img src=x onerror=alert(1)>", 'phone' => '0987654321', 'email' => $email,
        'birth_date' => '2000-02-29', 'gender' => 'NU'];
    verifyIntegration(requestIntegration('/account', $profile)['status'] === 403, 'profile update requires CSRF');
    $badProfile = $profile; $badProfile['phone'] = '1987654321';
    $response = requestIntegration('/account', ['_token' => $profileToken] + $badProfile);
    verifyIntegration($response['status'] === 422 && strpos($response['body'], 'id="phone-error"') !== false,
        'profile phone validated');
    $badProfile = $profile; $badProfile['birth_date'] = '2025-02-30';
    verifyIntegration(requestIntegration('/account', ['_token' => $profileToken] + $badProfile)['status'] === 422,
        'invalid profile date rejected');
    verifyIntegration(requestIntegration('/account', ['_token' => $profileToken] + $profile)['status'] === 303,
        'customer profile updated');
    $stmt = $pdo->prepare('SELECT ho_ten, so_dien_thoai, email, ngay_sinh, gioi_tinh FROM khach_hang WHERE ma_tai_khoan = :id');
    $stmt->execute(['id' => $id]);
    $savedProfile = $stmt->fetch();
    verifyIntegration($savedProfile['ho_ten'] === $profile['name'] && $savedProfile['so_dien_thoai'] === $profile['phone']
        && $savedProfile['email'] === $email && $savedProfile['ngay_sinh'] === $profile['birth_date']
        && $savedProfile['gioi_tinh'] === $profile['gender'], 'profile fields persisted on own customer row');
    $accountPage = requestIntegration('/account');
    verifyIntegration(strpos($accountPage['body'], 'value="0987654321"') !== false
        && strpos($accountPage['body'], '&lt;img src=x onerror=alert(1)&gt;') !== false
        && strpos($accountPage['body'], '<img src=x onerror=alert(1)>') === false, 'updated profile escaped when rendered');
    $profileToken = integrationToken($accountPage);
    $newPassword = 'NewSecret1!';
    $passwordFields = ['current_password' => $password, 'new_password' => $newPassword,
        'new_password_confirmation' => $newPassword];
    verifyIntegration(requestIntegration('/account/password', $passwordFields)['status'] === 403,
        'password change requires CSRF');
    $badPassword = $passwordFields; $badPassword['current_password'] = 'Wrong1!x';
    $response = requestIntegration('/account/password', ['_token' => $profileToken] + $badPassword);
    verifyIntegration($response['status'] === 422 && strpos($response['body'], 'id="current_password-error"') !== false,
        'current password checked');
    verifyIntegration(strpos($response['body'], 'id="settings-title"') !== false, 'password errors stay on settings page');
    $badPassword = $passwordFields; $badPassword['new_password'] = 'short';
    verifyIntegration(requestIntegration('/account/password', ['_token' => $profileToken] + $badPassword)['status'] === 422,
        'new password policy checked');
    $sessionBeforePassword = integrationSessionId();
    $passwordChange = requestIntegration('/account/password', ['_token' => $profileToken] + $passwordFields);
    verifyIntegration($passwordChange['status'] === 303
        && strpos($passwordChange['headers'], 'route=%2Faccount%2Fsettings') !== false,
        'password changed and redirects to settings');
    verifyIntegration($sessionBeforePassword !== integrationSessionId(), 'password change rotates session ID');
    verifyIntegration(requestIntegration('/account/password', ['_token' => $profileToken] + $passwordFields)['status'] === 403,
        'password change invalidates previous CSRF token');
    $stmt = $pdo->prepare('SELECT mat_khau FROM tai_khoan WHERE ma_tai_khoan = :id');
    $stmt->execute(['id' => $id]);
    $changedHash = $stmt->fetchColumn();
    verifyIntegration(password_verify($newPassword, $changedHash) && !password_verify($password, $changedHash),
        'new password hash stored, old password rejected');
    $password = $newPassword;
    $accountPage = requestIntegration('/account');
    verifyIntegration(requestIntegration('/customer')['status'] === 200, 'correct role allowed');
    verifyIntegration(requestIntegration('/admin')['status'] === 403, 'wrong role denied');
    verifyIntegration(requestIntegration('/not-a-route')['status'] === 404, '404 page');
    verifyIntegration(requestIntegration('/logout')['status'] === 405, 'GET logout denied');
    verifyIntegration(requestIntegration('/logout', ['_token' => $token])['status'] === 403, 'pre-login CSRF invalidated');
    $token = integrationToken($accountPage);
    verifyIntegration(requestIntegration('/logout', ['_token' => $token])['status'] === 303, 'logout success');
    verifyIntegration(requestIntegration('/account')['status'] === 303, 'logout removes access');
    verifyIntegration(requestIntegration('/logout', ['_token' => $token])['status'] === 403, 'logged-out token rejected');
    $token = integrationToken(requestIntegration('/login'));
    requestIntegration('/login', ['_token' => $token, 'username' => $username, 'password' => $password]);
    updateFixture('trang_thai', 'KHOA');
    verifyIntegration(requestIntegration('/account')['status'] === 303, 'lock revokes active session');
    $token = integrationToken(requestIntegration('/login'));
    verifyIntegration(requestIntegration('/login', ['_token' => $token, 'username' => $username, 'password' => $password])['status'] === 422, 'locked login rejected');
    updateFixture('trang_thai', 'HOAT_DONG');
    $roleStmt = $pdo->prepare('SELECT ma_vai_tro FROM vai_tro WHERE ten_vai_tro = :code');
    $roleStmt->execute(['code' => 'QUAN_TRI_TAP_DOAN']);
    updateFixture('ma_vai_tro', (int) $roleStmt->fetchColumn());
    $token = integrationToken(requestIntegration('/login'));
    requestIntegration('/login', ['_token' => $token, 'username' => $username, 'password' => $password]);
    verifyIntegration(requestIntegration('/admin')['status'] === 200, 'admin role resolved without hardcoded ID');
    $adminAccount = requestIntegration('/account');
    verifyIntegration(requestIntegration('/account', ['_token' => integrationToken($adminAccount)] + $profile)['status'] === 403,
        'non-customer role cannot update customer profile');
    $roleStmt->execute(['code' => 'QUAN_LY_CO_SO']);
    updateFixture('ma_vai_tro', (int) $roleStmt->fetchColumn());
    verifyIntegration(requestIntegration('/account')['status'] === 303, 'role without required employee profile denied');
    echo "PASS: $checks real HTTP/database auth checks.\n";
} finally {
    // Exact random username + ID guards; no existing user is updated/deleted.
    $stmt = $pdo->prepare('SELECT ma_tai_khoan FROM tai_khoan WHERE ten_dang_nhap = :username');
    $stmt->execute(['username' => $username]);
    $fixtureId = $stmt->fetchColumn();
    if ($fixtureId !== false && ($id === null || (int) $fixtureId === $id)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM khach_hang WHERE ma_tai_khoan = :id AND email = :email');
            $stmt->execute(['id' => $fixtureId, 'email' => $email]);
            $stmt = $pdo->prepare('DELETE FROM tai_khoan WHERE ma_tai_khoan = :id AND ten_dang_nhap = :username');
            $stmt->execute(['id' => $fixtureId, 'username' => $username]);
            $pdo->commit();
            echo "Test fixture removed.\n";
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
    curl_close($client);
}
