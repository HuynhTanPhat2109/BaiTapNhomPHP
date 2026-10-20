<?php

namespace App\Services;

use App\Models\TaiKhoan;
use App\Models\VaiTro;
use PDO;
use PDOException;

final class AccountService
{
    public const PASSWORD_REQUIREMENT = 'Mật khẩu của bạn phải dài từ 8 đến 16 ký tự, phải chứa ít nhất 1 ký tự viết hoa, 1 ký tự viết thường, 1 ký tự số và 1 ký tự đặc biệt';

    private TaiKhoan $accounts;
    private VaiTro $roles;

    public function __construct(PDO $pdo)
    {
        $this->accounts = new TaiKhoan($pdo);
        $this->roles = new VaiTro($pdo);
    }

    public static function validateCredentials(string $username, string $password): array
    {
        $errors = [];
        if (!preg_match('/\A[a-zA-Z0-9_.-]{6,15}\z/', $username)) {
            $errors['username'] = 'Tên đăng nhập phải có 6–15 ký tự.';
        }
        // Count Unicode characters, not bytes. Special characters are punctuation
        // or symbols; whitespace does not satisfy that requirement. No controls.
        if (!preg_match('/\A(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[0-9])(?=.*[\p{P}\p{S}])[^\p{C}]{8,16}\z/u', $password)) {
            $errors['password'] = self::PASSWORD_REQUIREMENT;
        }
        return $errors;
    }

    /**
     * Trusted internal API, never bind roleCode directly to public POST input.
     * Employee module must authorize the actor, then create account + profile
     * using the same PDO transaction. This method never begins/commits it.
     */
    public function create(string $username, string $password, string $roleCode): int
    {
        $username = trim($username);
        $errors = self::validateCredentials($username, $password);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $roleId = $this->roles->idByCode($roleCode);
        if ($roleId === null) {
            throw new \RuntimeException('Role seed is missing.');
        }
        if ($this->accounts->findByUsername($username)) {
            throw new ValidationException(['username' => 'Tên đăng nhập đã được sử dụng.']);
        }
        try {
            return $this->accounts->create($username, password_hash($password, PASSWORD_DEFAULT), $roleId);
        } catch (PDOException $e) {
            // The UNIQUE index is the final guard against concurrent registrations.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException(['username' => 'Tên đăng nhập đã được sử dụng.']);
            }
            throw $e;
        }
    }
}
