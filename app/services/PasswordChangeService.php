<?php

namespace App\Services;

use App\Models\TaiKhoan;
use PDO;

final class PasswordChangeService
{
    private TaiKhoan $accounts;

    public function __construct(PDO $pdo)
    {
        $this->accounts = new TaiKhoan($pdo);
    }

    public function change(int $accountId, array $data): void
    {
        $errors = [];
        foreach (['current_password', 'new_password', 'new_password_confirmation'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[$field] = 'Thông tin không hợp lệ. Vui lòng nhập lại.';
            }
        }
        $current = is_string($data['current_password'] ?? null) ? $data['current_password'] : '';
        $next = is_string($data['new_password'] ?? null) ? $data['new_password'] : '';
        $confirmation = is_string($data['new_password_confirmation'] ?? null) ? $data['new_password_confirmation'] : '';
        if ($current === '') {
            $errors['current_password'] ??= 'Vui lòng nhập mật khẩu hiện tại.';
        }
        if (!preg_match('/\A(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[0-9])(?=.*[\p{P}\p{S}])[^\p{C}]{8,16}\z/u', $next)) {
            $errors['new_password'] ??= AccountService::PASSWORD_REQUIREMENT;
        }
        if ($confirmation === '') {
            $errors['new_password_confirmation'] ??= 'Vui lòng nhập lại mật khẩu mới.';
        } elseif ($next !== $confirmation) {
            $errors['new_password_confirmation'] ??= 'Mật khẩu nhập lại không khớp.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        $oldHash = $this->accounts->passwordHash($accountId);
        if ($oldHash === null || strlen($current) > 72 || !password_verify($current, $oldHash)) {
            throw new ValidationException(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }
        if (password_verify($next, $oldHash)) {
            throw new ValidationException(['new_password' => 'Mật khẩu mới cần khác mật khẩu hiện tại.']);
        }
        $newHash = password_hash($next, PASSWORD_DEFAULT);
        if (!$this->accounts->replacePassword($accountId, $oldHash, $newHash)) {
            throw new ValidationException(['current_password' => 'Mật khẩu đã thay đổi. Vui lòng thử lại.']);
        }
    }
}
