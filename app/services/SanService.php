<?php

namespace App\Services;

use App\Models\BoMon;
use App\Models\CoSo;
use App\Models\LoaiSan;
use App\Models\San;
use Core\HttpException;
use PDO;
use PDOException;

final class SanService
{
    private PDO $pdo;
    private San $courts;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->courts = new San($pdo);
    }

    public static function validate(array $data): array
    {
        $errors = CoSoService::textErrors($data, ['ten_san' => 150, 'vi_tri' => 150, 'mo_ta' => 65535], ['ten_san']);
        foreach (['ma_co_so' => 'cơ sở', 'ma_loai_san' => 'loại sân'] as $field => $label) {
            if (!CoSoService::validId($data[$field] ?? null)) {
                $errors[$field] = 'Vui lòng chọn ' . $label . ' hợp lệ.';
            }
        }
        if (!in_array($data['trang_thai'] ?? null, ['HOAT_DONG', 'BAO_TRI', 'TAM_NGUNG'], true)) {
            $errors['trang_thai'] = 'Vui lòng chọn trạng thái sân hợp lệ.';
        }
        return $errors;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($errors = self::validate($data)) {
            throw new ValidationException($errors);
        }
        $clean = ['ma_co_so' => (int) $data['ma_co_so'], 'ma_loai_san' => (int) $data['ma_loai_san'],
            'ten_san' => trim($data['ten_san']), 'trang_thai' => $data['trang_thai']];
        foreach (['vi_tri', 'mo_ta'] as $field) {
            $value = trim($data[$field] ?? '');
            $clean[$field] = $value === '' ? null : $value;
        }
        $before = $id === null ? null : $this->courts->find($id);
        if ($id !== null && !$before) {
            throw new HttpException(404, 'Không tìm thấy sân.');
        }
        $facilities = new CoSo($this->pdo);
        $types = new LoaiSan($this->pdo);
        $facilityIds = array_unique([$clean['ma_co_so'], (int) ($before['ma_co_so'] ?? $clean['ma_co_so'])]);
        $typeIds = array_unique([$clean['ma_loai_san'], (int) ($before['ma_loai_san'] ?? $clean['ma_loai_san'])]);
        sort($facilityIds);
        sort($typeIds);
        $oldFacilities = $oldTypes = [];
        foreach ($facilityIds as $parentId) {
            $oldFacilities[$parentId] = $facilities->find($parentId);
            if (!$oldFacilities[$parentId]) {
                throw new ValidationException(['ma_co_so' => 'Cơ sở không tồn tại.']);
            }
        }
        foreach ($typeIds as $parentId) {
            $oldTypes[$parentId] = $types->find($parentId);
            if (!$oldTypes[$parentId]) {
                throw new ValidationException(['ma_loai_san' => 'Loại sân không tồn tại.']);
            }
        }
        $this->pdo->beginTransaction();
        try {
            // Parent locks precede courts; sports precede types, as in PR 1.
            $companyIds = array_unique(array_column($oldFacilities, 'ma_cong_ty'));
            sort($companyIds);
            $companies = $parents = $sports = $lockedTypes = [];
            foreach ($companyIds as $companyId) {
                $companies[$companyId] = $facilities->company((int) $companyId, true);
            }
            foreach ($facilityIds as $parentId) {
                $parents[$parentId] = $facilities->find($parentId, true);
                if (!$parents[$parentId] || (int) $parents[$parentId]['ma_cong_ty'] !== (int) $oldFacilities[$parentId]['ma_cong_ty']) {
                    throw new HttpException(409, 'Liên kết cơ sở vừa thay đổi. Vui lòng tải lại trang.');
                }
            }
            $sportIds = array_unique(array_column($oldTypes, 'ma_bo_mon'));
            sort($sportIds);
            foreach ($sportIds as $sportId) {
                $sports[$sportId] = (new BoMon($this->pdo))->find((int) $sportId, true);
            }
            foreach ($typeIds as $parentId) {
                $lockedTypes[$parentId] = $types->find($parentId, true);
                if (!$lockedTypes[$parentId] || (int) $lockedTypes[$parentId]['ma_bo_mon'] !== (int) $oldTypes[$parentId]['ma_bo_mon']) {
                    throw new HttpException(409, 'Liên kết loại sân vừa thay đổi. Vui lòng tải lại trang.');
                }
            }
            $current = $id === null ? null : $this->courts->find($id, true);
            if ($id !== null && !$current) {
                throw new HttpException(404, 'Không tìm thấy sân.');
            }
            if ($current && ((int) $current['ma_co_so'] !== (int) $before['ma_co_so']
                || (int) $current['ma_loai_san'] !== (int) $before['ma_loai_san'])) {
                throw new HttpException(409, 'Liên kết sân vừa thay đổi. Vui lòng tải lại trang.');
            }
            $movedFacility = $current && (int) $current['ma_co_so'] !== $clean['ma_co_so'];
            $movedType = $current && (int) $current['ma_loai_san'] !== $clean['ma_loai_san'];
            $parent = $parents[$clean['ma_co_so']];
            $type = $lockedTypes[$clean['ma_loai_san']];
            $requiresActive = $id === null || $movedFacility || $movedType || $clean['trang_thai'] !== 'TAM_NGUNG';
            if ($requiresActive && ($parent['trang_thai'] !== 'HOAT_DONG'
                || ($companies[$parent['ma_cong_ty']]['trang_thai'] ?? null) !== 'HOAT_DONG')) {
                throw new ValidationException(['ma_co_so' => 'Phải chọn cơ sở và công ty đang hoạt động.']);
            }
            if ($requiresActive && ($type['trang_thai'] !== 'HOAT_DONG'
                || ($sports[$type['ma_bo_mon']]['trang_thai'] ?? null) !== 'HOAT_DONG')) {
                throw new ValidationException(['ma_loai_san' => 'Phải chọn loại sân và bộ môn đang hoạt động.']);
            }
            if ($current) {
                $this->courts->lockReferences($id);
                $related = $this->courts->related($id);
                if (($movedFacility || $movedType) && $related['chi_tiet_dat_san']) {
                    throw new ValidationException([$movedFacility ? 'ma_co_so' : 'ma_loai_san' => 'Không thể đổi cơ sở hoặc loại sân khi đã có chi tiết đặt sân tham chiếu.']);
                }
                if ($clean['trang_thai'] !== 'HOAT_DONG' && $related['booking_hieu_luc']) {
                    throw new ValidationException(['trang_thai' => 'Chưa thể bảo trì/tạm ngừng: sân còn lịch đã xác nhận hoặc lượt giữ sân chưa hết hạn.']);
                }
            }
            if ($this->courts->nameExists($clean['ma_co_so'], $clean['ten_san'], $id)) {
                throw new ValidationException(['ten_san' => 'Tên sân đã tồn tại trong cơ sở này.']);
            }
            $id = $this->courts->save($clean, $id);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw new ValidationException(['ten_san' => 'Tên sân đã tồn tại trong cơ sở này.']);
                }
                if ((int) ($e->errorInfo[1] ?? 0) === 1452) {
                    throw new ValidationException(['ma_co_so' => 'Cơ sở hoặc loại sân không còn tồn tại. Vui lòng chọn lại.']);
                }
                if (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                    throw new HttpException(409, 'Dữ liệu đang được cập nhật. Vui lòng tải lại và thử lại.');
                }
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $before = $this->courts->find($id);
        if (!$before) {
            throw new HttpException(404, 'Không tìm thấy sân.');
        }
        $facilities = new CoSo($this->pdo);
        $this->pdo->beginTransaction();
        try {
            $facilities->company((int) $before['ma_cong_ty'], true);
            $parent = $facilities->find((int) $before['ma_co_so'], true);
            $current = $this->courts->find($id, true);
            if (!$current) {
                throw new HttpException(404, 'Không tìm thấy sân.');
            }
            if (!$parent || (int) $parent['ma_cong_ty'] !== (int) $before['ma_cong_ty']
                || (int) $current['ma_co_so'] !== (int) $before['ma_co_so']) {
                throw new HttpException(409, 'Liên kết cơ sở vừa thay đổi. Vui lòng tải lại trang.');
            }
            $this->courts->lockReferences($id);
            if ($this->courts->related($id)['chi_tiet_dat_san']) {
                throw new HttpException(409, 'Không thể xóa sân đã có chi tiết đặt sân tham chiếu, kể cả lịch sử đã hủy hoặc hoàn thành.');
            }
            $this->courts->delete($id);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw new HttpException(409, 'Không thể xóa sân đang được tham chiếu.');
            }
            if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw new HttpException(409, 'Dữ liệu đang được cập nhật. Vui lòng thử lại.');
            }
            throw $e;
        }
    }
}
