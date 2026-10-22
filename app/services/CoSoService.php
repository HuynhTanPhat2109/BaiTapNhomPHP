<?php

namespace App\Services;

use App\Models\CoSo;
use Core\HttpException;
use PDO;
use PDOException;

final class CoSoService
{
    private PDO $pdo;
    private CoSo $facilities;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->facilities = new CoSo($pdo);
    }

    public static function validId($value): bool
    {
        return is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value)
            && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) !== false;
    }

    // Both PR 2 forms use UTF-8 VARCHAR fields and an optional TEXT description.
    public static function textErrors(array $data, array $limits, array $required): array
    {
        $errors = [];
        foreach ($limits as $field => $max) {
            $value = $data[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || preg_match('/\p{C}/u', $field === 'mo_ta' ? str_replace(["\r", "\n", "\t"], '', $value) : $value)
                || ($field === 'mo_ta' ? strlen(trim($value)) : mb_strlen(trim($value), 'UTF-8')) > $max
                || (in_array($field, $required, true) && trim($value) === '')) {
                $errors[$field] = 'Thông tin không hợp lệ; tối đa ' . $max . ($field === 'mo_ta' ? ' byte.' : ' ký tự.');
            }
        }
        return $errors;
    }

    public static function validate(array $data): array
    {
        $errors = self::textErrors($data, ['ten_co_so' => 200, 'dia_chi' => 255, 'so_dien_thoai' => 20,
            'email' => 150, 'mo_ta' => 65535], ['ten_co_so', 'dia_chi']);
        if (!self::validId($data['ma_cong_ty'] ?? null)) {
            $errors['ma_cong_ty'] = 'Vui lòng chọn công ty hợp lệ.';
        }
        $email = $data['email'] ?? '';
        if (is_string($email) && trim($email) !== '' && !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ.';
        }
        foreach (['gio_mo_cua', 'gio_dong_cua'] as $field) {
            if (!is_string($data[$field] ?? null) || !preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $data[$field])) {
                $errors[$field] = 'Vui lòng nhập giờ hợp lệ trong ngày.';
            }
        }
        if (!isset($errors['gio_mo_cua']) && !isset($errors['gio_dong_cua'])
            && self::time($data['gio_dong_cua']) <= self::time($data['gio_mo_cua'])) {
            $errors['gio_dong_cua'] = 'Giờ đóng cửa phải sau giờ mở cửa trong cùng ngày.';
        }
        if (!in_array($data['trang_thai'] ?? null, ['HOAT_DONG', 'TAM_NGUNG'], true)) {
            $errors['trang_thai'] = 'Vui lòng chọn trạng thái cơ sở hợp lệ.';
        }
        return $errors;
    }

    private static function time(string $value): string
    {
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($errors = self::validate($data)) {
            throw new ValidationException($errors);
        }
        $clean = ['ma_cong_ty' => (int) $data['ma_cong_ty'], 'ten_co_so' => trim($data['ten_co_so']),
            'dia_chi' => trim($data['dia_chi']), 'gio_mo_cua' => self::time($data['gio_mo_cua']),
            'gio_dong_cua' => self::time($data['gio_dong_cua']), 'trang_thai' => $data['trang_thai']];
        foreach (['so_dien_thoai', 'email', 'mo_ta'] as $field) {
            $value = trim($data[$field] ?? '');
            $clean[$field] = $value === '' ? null : $value;
        }
        $before = $id === null ? null : $this->facilities->find($id);
        if ($id !== null && !$before) {
            throw new HttpException(404, 'Không tìm thấy cơ sở.');
        }
        $this->pdo->beginTransaction();
        try {
            $companyIds = array_unique([$clean['ma_cong_ty'], (int) ($before['ma_cong_ty'] ?? $clean['ma_cong_ty'])]);
            sort($companyIds);
            $companies = [];
            foreach ($companyIds as $companyId) {
                $companies[$companyId] = $this->facilities->company($companyId, true);
            }
            $parent = $companies[$clean['ma_cong_ty']];
            if (!$parent) {
                throw new ValidationException(['ma_cong_ty' => 'Công ty không tồn tại.']);
            }
            $current = $id === null ? null : $this->facilities->find($id, true);
            if ($id !== null && !$current) {
                throw new HttpException(404, 'Không tìm thấy cơ sở.');
            }
            if ($current && (int) $current['ma_cong_ty'] !== (int) $before['ma_cong_ty']) {
                throw new HttpException(409, 'Công ty của cơ sở vừa thay đổi. Vui lòng tải lại trang.');
            }
            $moved = $current && (int) $current['ma_cong_ty'] !== $clean['ma_cong_ty'];
            if ($parent['trang_thai'] !== 'HOAT_DONG' && ($id === null || $moved || $clean['trang_thai'] === 'HOAT_DONG')) {
                throw new ValidationException(['ma_cong_ty' => 'Phải chọn công ty hoạt động để tạo, chuyển công ty hoặc kích hoạt cơ sở.']);
            }
            if ($current) {
                $this->facilities->lockReferences($id);
                $related = $this->facilities->related($id);
                if ($moved && ($related['san'] || $related['nhan_vien'] || $related['bang_gia'] || $related['dat_san'] || $related['dich_vu'])) {
                    throw new ValidationException(['ma_cong_ty' => 'Không thể đổi công ty khi cơ sở đã có dữ liệu tham chiếu.']);
                }
                if ($clean['trang_thai'] === 'TAM_NGUNG' && ($related['san_dang_dung'] || $related['nhan_vien_dang_lam']
                    || $related['bang_gia_dang_dung'] || $related['dich_vu_dang_dung'] || $related['booking_hieu_luc'])) {
                    throw new ValidationException(['trang_thai' => 'Chưa thể tạm ngừng: còn sân hoạt động/bảo trì, nhân viên đang làm, bảng giá/dịch vụ đang áp dụng hoặc lịch đặt còn hiệu lực.']);
                }
                if (($current['gio_mo_cua'] !== $clean['gio_mo_cua'] || $current['gio_dong_cua'] !== $clean['gio_dong_cua'])
                    && $this->facilities->hoursConflict($id, $clean['gio_mo_cua'], $clean['gio_dong_cua'])) {
                    throw new ValidationException(['gio_dong_cua' => 'Khung giờ mới không bao phủ bảng giá đang áp dụng hoặc lịch đặt còn hiệu lực.']);
                }
            }
            $id = $this->facilities->save($clean, $id);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1452) {
                throw new ValidationException(['ma_cong_ty' => 'Công ty không còn tồn tại. Vui lòng chọn lại.']);
            }
            if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw new HttpException(409, 'Dữ liệu đang được cập nhật. Vui lòng tải lại và thử lại.');
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $before = $this->facilities->find($id);
        if (!$before) {
            throw new HttpException(404, 'Không tìm thấy cơ sở.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->facilities->company((int) $before['ma_cong_ty'], true);
            $current = $this->facilities->find($id, true);
            if (!$current) {
                throw new HttpException(404, 'Không tìm thấy cơ sở.');
            }
            if ((int) $current['ma_cong_ty'] !== (int) $before['ma_cong_ty']) {
                throw new HttpException(409, 'Công ty của cơ sở vừa thay đổi. Vui lòng tải lại trang.');
            }
            $this->facilities->lockReferences($id);
            $r = $this->facilities->related($id);
            if ($r['san'] || $r['nhan_vien'] || $r['bang_gia'] || $r['dat_san'] || $r['dich_vu']) {
                throw new HttpException(409, 'Không thể xóa cơ sở đang được sân, nhân viên, bảng giá, booking hoặc dịch vụ tham chiếu.');
            }
            $this->facilities->delete($id);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw new HttpException(409, 'Không thể xóa cơ sở đang được tham chiếu.');
            }
            if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw new HttpException(409, 'Dữ liệu đang được cập nhật. Vui lòng thử lại.');
            }
            throw $e;
        }
    }
}
