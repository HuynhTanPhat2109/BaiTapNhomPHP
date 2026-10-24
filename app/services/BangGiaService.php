<?php

namespace App\Services;

use App\Models\BangGia;
use App\Models\BoMon;
use App\Models\CoSo;
use App\Models\LoaiSan;
use Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

final class BangGiaService
{
    private BangGia $prices;

    public function __construct(private PDO $pdo)
    {
        $this->prices = new BangGia($pdo);
    }

    private static function validDate($value): bool
    {
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    public static function validate(array $data): array
    {
        $errors = CoSoService::textErrors($data, ['ten_bang_gia' => 150], ['ten_bang_gia']);
        foreach (['ma_co_so', 'ma_loai_san'] as $field) {
            if (!CoSoService::validId($data[$field] ?? null)) {
                $errors[$field] = 'Vui lòng chọn liên kết hợp lệ.';
            }
        }
        if (!in_array($data['loai_ngay'] ?? null, ['NGAY_THUONG', 'CUOI_TUAN'], true)) {
            $errors['loai_ngay'] = 'Vui lòng chọn loại ngày hợp lệ.';
        }
        if (!in_array($data['trang_thai'] ?? null, ['DANG_AP_DUNG', 'NGUNG'], true)) {
            $errors['trang_thai'] = 'Trạng thái bảng giá không hợp lệ.';
        }
        foreach (['gio_bat_dau', 'gio_ket_thuc'] as $field) {
            if (!is_string($data[$field] ?? null) || !preg_match('/\A(?:[01][0-9]|2[0-3]):(?:00|30)(?::00)?\z/', $data[$field])) {
                $errors[$field] = 'Giờ phải trong ngày, theo bước 30 phút (giây bằng 00).';
            }
        }
        if (!isset($errors['gio_bat_dau']) && !isset($errors['gio_ket_thuc'])
            && self::time($data['gio_bat_dau']) >= self::time($data['gio_ket_thuc'])) {
            $errors['gio_ket_thuc'] = 'Giờ kết thúc phải sau giờ bắt đầu trong cùng ngày.';
        }
        $price = $data['don_gia'] ?? null;
        if (!is_string($price) || !preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', trim($price)) || !preg_match('/[1-9]/', $price)) {
            $errors['don_gia'] = 'Nhập đơn giá lớn hơn 0, tối đa 9999999999.99 và 2 chữ số thập phân; không dùng dấu phân cách hàng nghìn.';
        }
        if (!self::validDate($data['ngay_bat_dau'] ?? null)) {
            $errors['ngay_bat_dau'] = 'Ngày bắt đầu phải là ngày hợp lệ theo định dạng YYYY-MM-DD.';
        }
        $end = $data['ngay_ket_thuc'] ?? null;
        if ($end !== null && $end !== '' && !self::validDate($end)) {
            $errors['ngay_ket_thuc'] = 'Ngày kết thúc không hợp lệ.';
        } elseif ($end && !isset($errors['ngay_bat_dau']) && $end < $data['ngay_bat_dau']) {
            $errors['ngay_ket_thuc'] = 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.';
        }
        return $errors;
    }

    private static function time(string $value): string
    {
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    public static function normalize(array $data): array
    {
        $price = explode('.', trim($data['don_gia']));
        return ['ma_co_so' => (int) $data['ma_co_so'], 'ma_loai_san' => (int) $data['ma_loai_san'],
            'ten_bang_gia' => trim($data['ten_bang_gia']), 'loai_ngay' => $data['loai_ngay'],
            'gio_bat_dau' => self::time($data['gio_bat_dau']), 'gio_ket_thuc' => self::time($data['gio_ket_thuc']),
            'don_gia' => (ltrim($price[0], '0') ?: '0') . '.' . str_pad($price[1] ?? '', 2, '0'),
            'ngay_bat_dau' => $data['ngay_bat_dau'], 'ngay_ket_thuc' => ($data['ngay_ket_thuc'] ?? '') ?: null,
            'trang_thai' => $data['trang_thai']];
    }

    public static function overlaps(array $a, array $b): bool
    {
        foreach (['ma_co_so', 'ma_loai_san', 'loai_ngay'] as $field) {
            if ((string) $a[$field] !== (string) $b[$field]) {
                return false;
            }
        }
        if ($a['trang_thai'] !== 'DANG_AP_DUNG' || $b['trang_thai'] !== 'DANG_AP_DUNG'
            || self::time($a['gio_bat_dau']) >= self::time($b['gio_ket_thuc'])
            || self::time($a['gio_ket_thuc']) <= self::time($b['gio_bat_dau'])) {
            return false;
        }
        $start = max($a['ngay_bat_dau'], $b['ngay_bat_dau']);
        $ends = array_filter([$a['ngay_ket_thuc'] ?? null, $b['ngay_ket_thuc'] ?? null]);
        $end = $ends ? min($ends) : null;
        $date = new DateTimeImmutable($start, new DateTimeZone('Asia/Ho_Chi_Minh'));
        // At most one week is needed to find an actual shared weekday/weekend.
        for ($i = 0; $i < 7 && ($end === null || $date->format('Y-m-d') <= $end); $i++) {
            if (((int) $date->format('N') >= 6) === ($a['loai_ngay'] === 'CUOI_TUAN')) {
                return true;
            }
            if ($date->format('Y-m-d') === '9999-12-31') {
                break;
            }
            $date = $date->modify('+1 day');
        }
        return false;
    }

    public static function filters(array $query): array
    {
        return AdminListService::filters($query, ['ma_co_so', 'ma_loai_san'],
            ['loai_ngay' => ['NGAY_THUONG', 'CUOI_TUAN'], 'trang_thai' => ['DANG_AP_DUNG', 'NGUNG']]);
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($errors = self::validate($data)) {
            throw new ValidationException($errors);
        }
        return $this->write(self::normalize($data), $id);
    }

    public function changeStatus(int $id, $status): void
    {
        if (!in_array($status, ['DANG_AP_DUNG', 'NGUNG'], true)) {
            throw new ValidationException(['trang_thai' => 'Trạng thái không hợp lệ.']);
        }
        $this->write(null, $id, $status);
    }

    private function write(?array $data, ?int $id, ?string $status = null): int
    {
        $before = $id === null ? null : $this->prices->find($id);
        if ($id !== null && !$before) {
            throw new HttpException(404, 'Không tìm thấy bảng giá.');
        }
        $scope = $data ?? $before;
        $this->pdo->beginTransaction();
        try {
            [$facility, $type, $company, $sport] = $this->lockParents($scope, $before);
            $current = $id === null ? null : $this->prices->find($id, true);
            if ($id !== null && !$current) {
                throw new HttpException(404, 'Không tìm thấy bảng giá.');
            }
            if ($current && ((int) $current['ma_co_so'] !== (int) $before['ma_co_so'] || (int) $current['ma_loai_san'] !== (int) $before['ma_loai_san'])) {
                throw new HttpException(409, 'Liên kết bảng giá vừa thay đổi. Vui lòng tải lại trang.');
            }
            if ($status === 'NGUNG') {
                // Stopping never rewrites legacy price fields or booking snapshots.
                $this->prices->changeStatus($id, $status);
                $this->pdo->commit();
                return $id;
            }
            if ($status !== null) {
                $current['ma_co_so'] = (string) $current['ma_co_so'];
                $current['ma_loai_san'] = (string) $current['ma_loai_san'];
                $current['trang_thai'] = $status;
                if ($errors = self::validate($current)) {
                    throw new ValidationException($errors);
                }
                $data = self::normalize($current);
            } elseif ($current && $data['trang_thai'] !== $current['trang_thai']) {
                throw new ValidationException(['trang_thai' => 'Dùng thao tác Ngừng/Kích hoạt ở trang chi tiết; tải lại nếu trạng thái vừa thay đổi.']);
            }
            $moved = $current && ((int) $current['ma_co_so'] !== $data['ma_co_so'] || (int) $current['ma_loai_san'] !== $data['ma_loai_san']);
            if ($id === null || $moved || $data['trang_thai'] === 'DANG_AP_DUNG') {
                if ($facility['trang_thai'] !== 'HOAT_DONG' || $company['trang_thai'] !== 'HOAT_DONG') {
                    throw new ValidationException(['ma_co_so' => 'Phải chọn cơ sở và công ty đang hoạt động.']);
                }
                if ($type['trang_thai'] !== 'HOAT_DONG' || $sport['trang_thai'] !== 'HOAT_DONG') {
                    throw new ValidationException(['ma_loai_san' => 'Phải chọn loại sân và bộ môn đang hoạt động.']);
                }
            }
            if ($data['gio_bat_dau'] < $facility['gio_mo_cua'] || $data['gio_ket_thuc'] > $facility['gio_dong_cua']) {
                throw new ValidationException(['gio_ket_thuc' => 'Khung giờ phải nằm trong giờ hoạt động của cơ sở.']);
            }
            if ($data['trang_thai'] === 'DANG_AP_DUNG') {
                foreach ($this->prices->candidates($data, $id) as $other) {
                    if (self::overlaps($data, $other)) {
                        throw new ValidationException(['gio_bat_dau' => 'Khoảng áp dụng chồng lấn với bảng giá #' . $other['ma_bang_gia'] . '. Vui lòng đổi ngày hoặc khung giờ.']);
                    }
                }
            }
            if ($status !== null) {
                $this->prices->changeStatus($id, $status);
            } else {
                $id = $this->prices->save($data, $id);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1452) {
                throw new ValidationException(['ma_co_so' => 'Cơ sở hoặc loại sân không còn tồn tại. Vui lòng chọn lại.']);
            }
            if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw new HttpException(409, 'Dữ liệu đang được cập nhật. Vui lòng tải lại và thử lại.');
            }
            throw $e;
        }
    }

    private function lockParents(array $data, ?array $before): array
    {
        $facilities = new CoSo($this->pdo);
        $types = new LoaiSan($this->pdo);
        $rows = $typeRows = [];
        $facilityIds = array_unique([(int) $data['ma_co_so'], (int) ($before['ma_co_so'] ?? $data['ma_co_so'])]);
        $typeIds = array_unique([(int) $data['ma_loai_san'], (int) ($before['ma_loai_san'] ?? $data['ma_loai_san'])]);
        sort($facilityIds);
        sort($typeIds);
        foreach ($facilityIds as $parentId) {
            $rows[$parentId] = $facilities->find($parentId);
            if (!$rows[$parentId]) {
                throw new ValidationException(['ma_co_so' => 'Cơ sở không tồn tại.']);
            }
        }
        foreach ($typeIds as $parentId) {
            $typeRows[$parentId] = $types->find($parentId);
            if (!$typeRows[$parentId]) {
                throw new ValidationException(['ma_loai_san' => 'Loại sân không tồn tại.']);
            }
        }
        // All PR 3 writers share existing parent locks, even when no price exists yet.
        $companyIds = array_unique(array_column($rows, 'ma_cong_ty'));
        sort($companyIds);
        $companies = $sports = [];
        foreach ($companyIds as $parentId) {
            $companies[$parentId] = $facilities->company((int) $parentId, true);
        }
        foreach ($facilityIds as $parentId) {
            $locked = $facilities->find($parentId, true);
            if (!$locked || (int) $locked['ma_cong_ty'] !== (int) $rows[$parentId]['ma_cong_ty']) {
                throw new HttpException(409, 'Liên kết cơ sở vừa thay đổi. Vui lòng tải lại trang.');
            }
            $rows[$parentId] = $locked;
        }
        $sportIds = array_unique(array_column($typeRows, 'ma_bo_mon'));
        sort($sportIds);
        foreach ($sportIds as $parentId) {
            $sports[$parentId] = (new BoMon($this->pdo))->find((int) $parentId, true);
        }
        foreach ($typeIds as $parentId) {
            $locked = $types->find($parentId, true);
            if (!$locked || (int) $locked['ma_bo_mon'] !== (int) $typeRows[$parentId]['ma_bo_mon']) {
                throw new HttpException(409, 'Liên kết loại sân vừa thay đổi. Vui lòng tải lại trang.');
            }
            $typeRows[$parentId] = $locked;
        }
        $facility = $rows[$data['ma_co_so']];
        $type = $typeRows[$data['ma_loai_san']];
        return [$facility, $type, $companies[$facility['ma_cong_ty']], $sports[$type['ma_bo_mon']]];
    }
}
