<?php

namespace App\Services;

use App\Models\KhachHang;
use DateTimeImmutable;
use PDO;

final class CustomerProfileService
{
    private KhachHang $customers;

    public function __construct(PDO $pdo)
    {
        $this->customers = new KhachHang($pdo);
    }

    public static function validate(array $data): array
    {
        $errors = [];
        foreach (['name', 'phone', 'email', 'birth_date', 'gender'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[$field] = 'Thông tin không hợp lệ. Vui lòng nhập lại.';
            }
        }
        $name = self::value($data, 'name');
        $phone = self::value($data, 'phone');
        $email = self::value($data, 'email');
        $birthDate = self::value($data, 'birth_date');
        $gender = self::value($data, 'gender');
        if ($name === '' || !mb_check_encoding($name, 'UTF-8')
            || preg_match('/\p{C}/u', $name) || mb_strlen($name, 'UTF-8') > 150) {
            $errors['name'] ??= 'Họ tên bắt buộc, tối đa 150 ký tự.';
        }
        if (!preg_match('/\A0[0-9]{9}\z/', $phone)) {
            $errors['phone'] ??= 'Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.';
        }
        if ($email !== '' && (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors['email'] ??= 'Email không hợp lệ hoặc dài quá 150 ký tự.';
        }
        if ($birthDate !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
            if (!$date || $date->format('Y-m-d') !== $birthDate || $date > new DateTimeImmutable('today')) {
                $errors['birth_date'] ??= 'Ngày sinh không hợp lệ hoặc ở tương lai.';
            }
        }
        if ($gender !== '' && !in_array($gender, ['NAM', 'NU', 'KHAC'], true)) {
            $errors['gender'] ??= 'Vui lòng chọn giới tính hợp lệ.';
        }
        return $errors;
    }

    private static function value(array $data, string $field): string
    {
        return isset($data[$field]) && is_string($data[$field]) ? trim($data[$field]) : '';
    }

    public function update(int $accountId, array $data): void
    {
        $errors = self::validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $this->customers->updateProfile($accountId, self::value($data, 'name'), self::value($data, 'phone'),
            self::value($data, 'email') ?: null, self::value($data, 'birth_date') ?: null,
            self::value($data, 'gender') ?: null);
    }
}
