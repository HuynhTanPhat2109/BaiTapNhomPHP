<?php

namespace App\Services;

use App\Models\CongTyNhuongQuyen;
use DateTimeImmutable;
use PDO;

final class CompanyService
{
    private CongTyNhuongQuyen $companies;

    public function __construct(PDO $pdo)
    {
        $this->companies = new CongTyNhuongQuyen($pdo);
    }

    public function all(): array
    {
        return $this->companies->all();
    }

    public function find(int $id): ?array
    {
        return $this->companies->find($id);
    }

    public static function validate(array $data): array
    {
        $errors = [];
        $requiredFields = ['ten_cong_ty' => 200, 'dia_chi' => 255];
        foreach ($requiredFields as $field => $maxLength) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                $errors[$field] = 'Trường này bắt buộc và phải là chuỗi.';
                continue;
            }
            $value = trim($data[$field]);
            if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
                $errors[$field] = 'Trường này bắt buộc, tối đa ' . $maxLength . ' ký tự.';
            }
        }

        $optionalFields = [
            'ma_so_thue' => 30,
            'nguoi_dai_dien' => 150,
            'so_dien_thoai' => 20,
            'email' => 150,
        ];
        foreach ($optionalFields as $field => $maxLength) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }
            if (!is_string($data[$field])) {
                $errors[$field] = 'Trường này phải là chuỗi.';
                continue;
            }
            $value = trim($data[$field]);
            if (mb_strlen($value, 'UTF-8') > $maxLength) {
                $errors[$field] = 'Trường này không được vượt quá ' . $maxLength . ' ký tự.';
            } elseif ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Email không hợp lệ.';
            }
        }

        if (array_key_exists('ngay_tham_gia', $data) && $data['ngay_tham_gia'] !== null) {
            if (!is_string($data['ngay_tham_gia'])) {
                $errors['ngay_tham_gia'] = 'Ngày tham gia phải là chuỗi theo định dạng Y-m-d.';
            } else {
                $value = trim($data['ngay_tham_gia']);
                if ($value !== '') {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    $dateErrors = DateTimeImmutable::getLastErrors();
                    if (!$date || $date->format('Y-m-d') !== $value
                        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                        $errors['ngay_tham_gia'] = 'Ngày tham gia không hợp lệ; vui lòng dùng định dạng Y-m-d.';
                    }
                }
            }
        }

        if (!isset($data['trang_thai']) || !is_string($data['trang_thai'])
            || !in_array($data['trang_thai'], ['HOAT_DONG', 'NGUNG_HOAT_DONG'], true)) {
            $errors['trang_thai'] = 'Trạng thái không hợp lệ.';
        }

        return $errors;
    }

    public function create(array $data): int
    {
        $errors = self::validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $this->companies->create(self::normalize($data));
    }

    public function update(int $id, array $data): bool
    {
        $errors = self::validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $this->companies->update($id, self::normalize($data));
    }

    public function changeStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['HOAT_DONG', 'NGUNG_HOAT_DONG'], true)) {
            throw new ValidationException(['trang_thai' => 'Trạng thái không hợp lệ.']);
        }
        return $this->companies->changeStatus($id, $status);
    }

    private static function normalize(array $data): array
    {
        return [
            'ten_cong_ty' => trim($data['ten_cong_ty']),
            'ma_so_thue' => self::optionalValue($data, 'ma_so_thue'),
            'nguoi_dai_dien' => self::optionalValue($data, 'nguoi_dai_dien'),
            'dia_chi' => trim($data['dia_chi']),
            'so_dien_thoai' => self::optionalValue($data, 'so_dien_thoai'),
            'email' => self::optionalValue($data, 'email'),
            'ngay_tham_gia' => self::optionalValue($data, 'ngay_tham_gia'),
            'trang_thai' => $data['trang_thai'],
        ];
    }

    private static function optionalValue(array $data, string $field): ?string
    {
        if (!isset($data[$field]) || !is_string($data[$field])) {
            return null;
        }
        $value = trim($data[$field]);
        return $value === '' ? null : $value;
    }
}
