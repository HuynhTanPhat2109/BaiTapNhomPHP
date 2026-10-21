<?php

namespace App\Services;

use App\Models\BoMon;
use App\Models\LoaiSan;
use Core\HttpException;
use PDO;
use PDOException;

final class LoaiSanService
{
    private PDO $pdo;
    private BoMon $sports;
    private LoaiSan $types;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sports = new BoMon($pdo);
        $this->types = new LoaiSan($pdo);
    }

    public static function validate(array $data): array
    {
        $errors = BoMonService::validate($data, 'ten_loai_san');
        $id = $data['ma_bo_mon'] ?? null;
        if (!is_string($id) || !preg_match('/\A[1-9][0-9]*\z/', $id)
            || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            $errors['ma_bo_mon'] = 'Vui lòng chọn bộ môn hợp lệ.';
        }
        return $errors;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($errors = self::validate($data)) {
            throw new ValidationException($errors);
        }
        $sportId = (int) $data['ma_bo_mon'];
        $data = BoMonService::normalize($data, 'ten_loai_san') + ['ma_bo_mon' => $sportId];
        $before = $id === null ? null : $this->types->find($id);
        if ($id !== null && !$before) {
            throw new HttpException(404, 'Không tìm thấy loại sân.');
        }
        $this->pdo->beginTransaction();
        try {
            // All writers lock sports first (ascending ID), then types and children.
            $sportIds = array_unique([$sportId, (int) ($before['ma_bo_mon'] ?? $sportId)]);
            sort($sportIds);
            $parents = [];
            foreach ($sportIds as $parentId) {
                $parents[$parentId] = $this->sports->find($parentId, true);
            }
            $parent = $parents[$sportId];
            if (!$parent) {
                throw new ValidationException(['ma_bo_mon' => 'Bộ môn không tồn tại.']);
            }
            $current = $id === null ? null : $this->types->find($id, true);
            if ($id !== null && !$current) {
                throw new HttpException(404, 'Không tìm thấy loại sân.');
            }
            if ($current && $current['ma_bo_mon'] !== $before['ma_bo_mon']) {
                throw new HttpException(409, 'Liên kết bộ môn vừa thay đổi. Vui lòng tải lại trang.');
            }
            $changedParent = $current && (int) $current['ma_bo_mon'] !== $sportId;
            if ($parent['trang_thai'] !== 'HOAT_DONG' && ($id === null || $changedParent || $data['trang_thai'] === 'HOAT_DONG')) {
                throw new ValidationException(['ma_bo_mon' => 'Phải chọn bộ môn đang hoạt động để tạo, chuyển bộ môn hoặc kích hoạt loại sân.']);
            }
            if ($current) {
                $this->types->lockReferences($id);
                $related = $this->types->related($id);
                if ($changedParent && ($related['san'] || $related['bang_gia'])) {
                    throw new ValidationException(['ma_bo_mon' => 'Không thể đổi bộ môn: có ' . $related['san'] . ' sân và ' . $related['bang_gia'] . ' bảng giá tham chiếu.']);
                }
                if ($data['trang_thai'] === 'NGUNG' && ($related['san_dang_dung'] || $related['bang_gia_dang_dung'])) {
                    throw new ValidationException(['trang_thai' => 'Chưa thể ngừng: còn ' . $related['san_dang_dung'] . ' sân hoạt động/bảo trì và '
                        . $related['bang_gia_dang_dung'] . ' bảng giá đang áp dụng.']);
                }
            }
            if ($this->types->nameExists($sportId, $data['ten_loai_san'], $id)) {
                throw new ValidationException(['ten_loai_san' => 'Tên loại sân đã tồn tại trong bộ môn này.']);
            }
            $id = $this->types->save($data, $id);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw new ValidationException(['ten_loai_san' => 'Tên loại sân đã tồn tại trong bộ môn này.']);
                }
                if ((int) ($e->errorInfo[1] ?? 0) === 1452) {
                    throw new ValidationException(['ma_bo_mon' => 'Bộ môn không còn tồn tại. Vui lòng chọn lại.']);
                }
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $before = $this->types->find($id);
        if (!$before) {
            throw new HttpException(404, 'Không tìm thấy loại sân.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->sports->find((int) $before['ma_bo_mon'], true);
            $current = $this->types->find($id, true);
            if (!$current) {
                throw new HttpException(404, 'Không tìm thấy loại sân.');
            }
            if ($current['ma_bo_mon'] !== $before['ma_bo_mon']) {
                throw new HttpException(409, 'Liên kết bộ môn vừa thay đổi. Vui lòng tải lại trang.');
            }
            $this->types->lockReferences($id);
            $related = $this->types->related($id);
            if ($related['san'] || $related['bang_gia']) {
                throw new HttpException(409, 'Không thể xóa: có ' . $related['san'] . ' sân và ' . $related['bang_gia'] . ' bảng giá tham chiếu.');
            }
            $this->types->delete($id);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw new HttpException(409, 'Không thể xóa loại sân đang được tham chiếu.');
            }
            throw $e;
        }
    }
}
