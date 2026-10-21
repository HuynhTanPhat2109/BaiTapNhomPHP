<?php

namespace App\Services;

use App\Models\BoMon;
use App\Models\LoaiSan;
use Core\HttpException;
use PDO;
use PDOException;

final class BoMonService
{
    private PDO $pdo;
    private BoMon $sports;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sports = new BoMon($pdo);
    }

    // Both catalog forms have the same text/status fields and schema limits.
    public static function validate(array $data, string $nameField = 'ten_bo_mon'): array
    {
        $errors = [];
        $limits = [$nameField => $nameField === 'ten_bo_mon' ? 100 : 150, 'mo_ta' => 255];
        foreach ($limits as $field => $max) {
            $value = $data[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || preg_match('/\p{C}/u', $field === 'mo_ta' ? str_replace(["\r", "\n", "\t"], '', $value) : $value)
                || mb_strlen(trim($value), 'UTF-8') > $max
                || ($field === $nameField && trim($value) === '')) {
                $errors[$field] = ($field === $nameField ? 'Tên bắt buộc, ' : 'Mô tả ') . "tối đa $max ký tự, không chứa ký tự điều khiển.";
            }
        }
        if (!in_array($data['trang_thai'] ?? null, ['HOAT_DONG', 'NGUNG'], true)) {
            $errors['trang_thai'] = 'Vui lòng chọn trạng thái hợp lệ.';
        }
        return $errors;
    }

    public static function normalize(array $data, string $nameField = 'ten_bo_mon'): array
    {
        $description = trim($data['mo_ta'] ?? '');
        return [$nameField => trim($data[$nameField]), 'mo_ta' => $description === '' ? null : $description,
            'trang_thai' => $data['trang_thai']];
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($errors = self::validate($data)) {
            throw new ValidationException($errors);
        }
        $data = self::normalize($data);
        $this->pdo->beginTransaction();
        try {
            if ($id !== null) {
                if (!$this->sports->find($id, true)) {
                    throw new HttpException(404, 'Không tìm thấy bộ môn.');
                }
                $types = new LoaiSan($this->pdo);
                foreach ($this->sports->lockTypes($id) as $typeId) {
                    $types->lockReferences((int) $typeId);
                }
                $related = $this->sports->related($id);
                if ($data['trang_thai'] === 'NGUNG' && ($related['loai_san_dang_dung'] || $related['san_dang_dung'] || $related['bang_gia_dang_dung'])) {
                    throw new ValidationException(['trang_thai' => 'Chưa thể ngừng: còn ' . $related['loai_san_dang_dung'] . ' loại sân hoạt động, '
                        . $related['san_dang_dung'] . ' sân hoạt động/bảo trì và ' . $related['bang_gia_dang_dung'] . ' bảng giá đang áp dụng.']);
                }
            }
            if ($this->sports->nameExists($data['ten_bo_mon'], $id)) {
                throw new ValidationException(['ten_bo_mon' => 'Tên bộ môn đã tồn tại.']);
            }
            $id = $this->sports->save($data, $id);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException(['ten_bo_mon' => 'Tên bộ môn đã tồn tại.']);
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            if (!$this->sports->find($id, true)) {
                throw new HttpException(404, 'Không tìm thấy bộ môn.');
            }
            $this->sports->lockTypes($id);
            $count = $this->sports->related($id)['loai_san'];
            if ($count) {
                throw new HttpException(409, "Không thể xóa: còn $count loại sân tham chiếu bộ môn này.");
            }
            $this->sports->delete($id);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw new HttpException(409, 'Không thể xóa bộ môn đang được tham chiếu.');
            }
            throw $e;
        }
    }
}
