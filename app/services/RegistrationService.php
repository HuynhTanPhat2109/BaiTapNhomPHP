<?php

namespace App\Services;

use App\Models\KhachHang;
use PDO;

final class RegistrationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function validate(array $data): array
    {
        $typeErrors = [];
        foreach (['username', 'password', 'password_confirmation', 'name', 'phone', 'email'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $typeErrors[$field] = 'Thông tin không hợp lệ. Vui lòng nhập lại.';
            }
        }
        $data = self::normalize($data);
        $errors = AccountService::validateCredentials($data['username'], $data['password']);
        if ($data['password_confirmation'] === '') {
            $errors['password_confirmation'] = 'Vui lòng nhập lại mật khẩu.';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Mật khẩu nhập lại không khớp.';
        }
        if ($data['name'] === '' || !mb_check_encoding($data['name'], 'UTF-8')
            || preg_match('/\p{C}/u', $data['name']) || mb_strlen($data['name'], 'UTF-8') > 150) {
            $errors['name'] = 'Họ tên bắt buộc, tối đa 150 ký tự.';
        }
        if (!preg_match('/\A0[0-9]{9}\z/', $data['phone'])) {
            $errors['phone'] = 'Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.';
        }
        if ($data['email'] !== '' && (strlen($data['email']) > 150
            || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) {
            $errors['email'] = 'Email không hợp lệ hoặc dài quá 150 ký tự.';
        }
        return array_replace($errors, $typeErrors);
    }

    private static function normalize(array $data): array
    {
        $normalized = [];
        foreach (['username', 'password', 'password_confirmation', 'name', 'phone', 'email'] as $field) {
            $value = isset($data[$field]) && is_string($data[$field]) ? $data[$field] : '';
            $normalized[$field] = in_array($field, ['password', 'password_confirmation'], true) ? $value : trim($value);
        }
        return $normalized;
    }

    public function register(array $data): int
    {
        $errors = self::validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $data = self::normalize($data);
        $this->pdo->beginTransaction();
        try {
            // Public registration always creates a customer, regardless of POST role fields.
            $id = (new AccountService($this->pdo))->create($data['username'], $data['password'], 'KHACH_HANG');
            (new KhachHang($this->pdo))->create($id, $data['name'], $data['phone'], $data['email'] ?: null);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
