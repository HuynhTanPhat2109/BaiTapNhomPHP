<?php

namespace App\Services;

use App\Models\TaiKhoan;
use Core\Session;

final class AuthService
{
    private TaiKhoan $accounts;
    // Valid dummy bcrypt hash: still verify on nonexistent usernames.
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(TaiKhoan $accounts)
    {
        $this->accounts = $accounts;
    }

    public function attempt(string $username, string $password): bool
    {
        if (strlen($username) > 100 || strlen($password) > 72 || strpos($password, "\0") !== false) {
            return false;
        }
        $account = $this->accounts->findByUsername($username);
        $validPassword = password_verify($password, $account['mat_khau'] ?? self::DUMMY_HASH);
        if (!$validPassword || !$account) {
            return false;
        }
        $identity = $this->accounts->identity((int) $account['ma_tai_khoan']);
        if (!self::isActive($identity)) {
            return false;
        }
        Session::authenticate((int) $identity['ma_tai_khoan']);
        $_SESSION['username'] = $identity['ten_dang_nhap'];
        return true;
    }

    public function user(): ?array
    {
        $id = Session::accountId();
        if ($id === null) {
            return null;
        }
        $identity = $this->accounts->identity($id);
        if (!self::isActive($identity)) {
            Session::forgetAuthentication();
            return null;
        }
        $_SESSION['username'] = $identity['ten_dang_nhap'];
        return $identity;
    }

    public static function isActive(?array $user): bool
    {
        if (!$user || $user['trang_thai'] !== 'HOAT_DONG') {
            return false;
        }
        $role = $user['ten_vai_tro'];
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return true;
        }
        if ($role === 'KHACH_HANG') {
            return $user['ma_khach_hang'] !== null && $user['trang_thai_khach'] === 'HOAT_DONG';
        }
        if (!in_array($role, ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'], true)
            || $user['ma_nhan_vien'] === null || $user['trang_thai_nhan_vien'] !== 'DANG_LAM'
            || $user['ma_cong_ty'] === null || $user['trang_thai_cong_ty'] !== 'HOAT_DONG') {
            return false;
        }
        if ($user['ma_co_so'] !== null
            && (string) $user['ma_cong_ty'] !== (string) $user['cong_ty_cua_co_so']) {
            return false;
        }
        return $role === 'QUAN_LY_CONG_TY'
            || ($user['ma_co_so'] !== null && $user['trang_thai_co_so'] === 'HOAT_DONG');
    }

    public function logout(): void
    {
        Session::logout();
    }
}
