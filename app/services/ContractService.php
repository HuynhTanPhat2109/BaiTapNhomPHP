<?php

namespace App\Services;

use App\Models\CongTyNhuongQuyen;
use App\Models\HopDongNhuongQuyen;
use DateTimeImmutable;
use PDO;

final class ContractService
{
    private HopDongNhuongQuyen $contracts;
    private CongTyNhuongQuyen $companies;

    public function __construct(PDO $pdo)
    {
        $this->contracts = new HopDongNhuongQuyen($pdo);
        $this->companies = new CongTyNhuongQuyen($pdo);
    }

    public function all(): array
    {
        return $this->contracts->all();
    }

    public function byCompany(int $companyId): array
    {
        return $this->contracts->byCompany($companyId);
    }

    public function find(int $id): ?array
    {
        return $this->contracts->find($id);
    }

    public function companies(): array
    {
        return $this->companies->all();
    }

    public function create(array $data): int
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $normalized = $this->normalize($data);
        if ($this->contracts->existsByContractNumber($normalized['so_hop_dong'])) {
            throw new ValidationException(['so_hop_dong' => 'Số hợp đồng đã tồn tại.']);
        }

        return $this->contracts->create($normalized);
    }

    public function update(int $id, array $data): bool
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $normalized = $this->normalize($data);
        if ($this->contracts->existsByContractNumber($normalized['so_hop_dong'], $id)) {
            throw new ValidationException(['so_hop_dong' => 'Số hợp đồng đã tồn tại.']);
        }

        return $this->contracts->update($id, $normalized);
    }

    public function changeStatus(int $id, string $status): bool
    {
        $status = trim($status);
        if (!in_array($status, ['HIEU_LUC', 'HET_HAN', 'CHAM_DUT'], true)) {
            throw new ValidationException(['trang_thai' => 'Trạng thái hợp đồng không hợp lệ.']);
        }
        return $this->contracts->changeStatus($id, $status);
    }

    private function validate(array $data): array
    {
        $errors = [];

        $companyId = $this->integerValue($data['ma_cong_ty'] ?? null);
        if ($companyId === null || $companyId <= 0) {
            $errors['ma_cong_ty'] = 'Phải chọn công ty hợp lệ.';
        } elseif ($this->companies->find($companyId) === null) {
            $errors['ma_cong_ty'] = 'Công ty không tồn tại.';
        }

        $contractNumber = $this->stringValue($data['so_hop_dong'] ?? null);
        if ($contractNumber === null || $contractNumber === '') {
            $errors['so_hop_dong'] = 'Số hợp đồng là bắt buộc.';
        } elseif (mb_strlen($contractNumber, 'UTF-8') > 50) {
            $errors['so_hop_dong'] = 'Số hợp đồng không được vượt quá 50 ký tự.';
        }

        foreach (['ngay_ky' => 'Ngày ký', 'ngay_bat_dau' => 'Ngày bắt đầu', 'ngay_ket_thuc' => 'Ngày kết thúc'] as $field => $label) {
            $value = $this->stringValue($data[$field] ?? null);
            if ($value === null || $value === '') {
                $errors[$field] = $label . ' là bắt buộc.';
            } elseif (!$this->isValidDate($value)) {
                $errors[$field] = $label . ' phải đúng định dạng Y-m-d.';
            }
        }

        $startDate = $this->stringValue($data['ngay_bat_dau'] ?? null);
        $endDate = $this->stringValue($data['ngay_ket_thuc'] ?? null);
        if ($startDate !== null && $endDate !== null
            && $this->isValidDate($startDate) && $this->isValidDate($endDate)
            && $endDate < $startDate) {
            $errors['ngay_ket_thuc'] = 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.';
        }

        foreach (['phi_nhuong_quyen' => ['Phí nhượng quyền', 0, null], 'ty_le_phi' => ['Tỷ lệ phí', 0, 100]] as $field => [$label, $minimum, $maximum]) {
            $value = $data[$field] ?? null;
            if ($value === null || (!is_string($value) && !is_int($value) && !is_float($value))) {
                $errors[$field] = $label . ' là bắt buộc và phải là số.';
                continue;
            }
            $value = is_string($value) ? trim($value) : $value;
            if ($value === '' || !is_numeric($value)) {
                $errors[$field] = $label . ' là bắt buộc và phải là số.';
            } elseif ((float) $value < $minimum || ($maximum !== null && (float) $value > $maximum)) {
                $errors[$field] = $maximum === null
                    ? $label . ' phải lớn hơn hoặc bằng 0.'
                    : $label . ' phải nằm trong khoảng 0 đến 100.';
            }
        }

        $status = $this->stringValue($data['trang_thai'] ?? null);
        if ($status === null || !in_array($status, ['HIEU_LUC', 'HET_HAN', 'CHAM_DUT'], true)) {
            $errors['trang_thai'] = 'Trạng thái hợp đồng không hợp lệ.';
        }

        return $errors;
    }

    private function normalize(array $data): array
    {
        return [
            'ma_cong_ty' => $this->integerValue($data['ma_cong_ty']),
            'so_hop_dong' => trim($data['so_hop_dong']),
            'ngay_ky' => trim($data['ngay_ky']),
            'ngay_bat_dau' => trim($data['ngay_bat_dau']),
            'ngay_ket_thuc' => trim($data['ngay_ket_thuc']),
            'phi_nhuong_quyen' => (float) trim((string) $data['phi_nhuong_quyen']),
            'ty_le_phi' => (float) trim((string) $data['ty_le_phi']),
            'noi_dung' => $this->optionalString($data['noi_dung'] ?? null),
            'trang_thai' => trim($data['trang_thai']),
        ];
    }

    private function integerValue($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || !preg_match('/^[+-]?\d+$/', $value)) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        return $integer === false ? null : $integer;
    }

    private function stringValue($value): ?string
    {
        return is_string($value) ? trim($value) : null;
    }

    private function optionalString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $dateErrors = DateTimeImmutable::getLastErrors();
        return $date !== false
            && $date->format('Y-m-d') === $value
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));
    }
}
