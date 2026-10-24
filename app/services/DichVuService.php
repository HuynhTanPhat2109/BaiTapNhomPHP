<?php

namespace App\Services;

use App\Models\{CoSo, DichVu};
use Core\HttpException;
use PDO;
use PDOException;

final class DichVuService
{
    public const TYPES = ['CHO_THUE' => 'Cho thuê', 'DO_UONG' => 'Đồ uống', 'HUAN_LUYEN' => 'Huấn luyện', 'KHAC' => 'Khác'];
    public const STATES = ['DANG_CUNG_CAP' => 'Đang cung cấp', 'NGUNG' => 'Ngừng cung cấp'];
    private const FIELDS = ['ma_co_so', 'ten_dich_vu', 'loai_dich_vu', 'don_vi_tinh', 'don_gia', 'so_luong_ton', 'mo_ta', 'trang_thai'];
    private DichVu $services;
    public readonly array $scope;

    public function __construct(private PDO $pdo, array $user)
    {
        $this->scope = self::scope($user);
        $this->services = new DichVu($pdo);
    }

    public static function scope(array $user): array
    {
        $role = $user['ten_vai_tro'] ?? '';
        if (!in_array($role, ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'], true)) throw new HttpException(403, 'Không có quyền quản lý hoặc xem dịch vụ nội bộ.');
        $user += array_fill_keys(['trang_thai', 'ma_nhan_vien', 'trang_thai_nhan_vien', 'ma_cong_ty', 'ma_co_so', 'cong_ty_cua_co_so', 'trang_thai_cong_ty', 'trang_thai_co_so'], null);
        if ($role !== 'QUAN_TRI_TAP_DOAN') foreach (['ma_nhan_vien', 'ma_cong_ty', 'ma_co_so', 'cong_ty_cua_co_so'] as $field) {
            if ($user[$field] !== null && ((!is_int($user[$field]) && !is_string($user[$field])) || !CoSoService::validId((string) $user[$field]))) throw new HttpException(403, 'Phân công tài khoản không hợp lệ.');
        }
        if (!AuthService::isActive($user)) throw new HttpException(403, 'Tài khoản hoặc phạm vi phân công không hoạt động.');
        $scope = ['company' => null, 'facility' => null, 'write' => $role !== 'NHAN_VIEN'];
        if ($role !== 'QUAN_TRI_TAP_DOAN') {
            foreach (['company' => 'ma_cong_ty', 'facility' => 'ma_co_so'] as $key => $field) {
                if ($key === 'facility' && $role === 'QUAN_LY_CONG_TY') continue;
                if (!CoSoService::validId((string) ($user[$field] ?? ''))) throw new HttpException(403, 'Chưa được phân công phạm vi dịch vụ hợp lệ.');
                $scope[$key] = (int) $user[$field];
            }
        }
        return $scope;
    }

    public static function basePath(string $role): string
    {
        return (['QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'][$role] ?? '/denied') . '/dich-vu';
    }

    public static function validate(array $data): array
    {
        $errors = [];
        foreach (['ten_dich_vu' => 150, 'don_vi_tinh' => 30, 'mo_ta' => 255] as $field => $limit) {
            $value = $data[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen(trim($value)) > $limit
                || preg_match('/\p{C}/u', $field === 'mo_ta' ? str_replace(["\n", "\r", "\t"], '', $value) : $value)
                || ($field !== 'mo_ta' && trim($value) === '')) $errors[$field] = "Thông tin bắt buộc/hợp lệ, tối đa $limit ký tự.";
        }
        if (!CoSoService::validId($data['ma_co_so'] ?? null)) $errors['ma_co_so'] = 'Chọn cơ sở hợp lệ.';
        if (!is_string($data['loai_dich_vu'] ?? null) || !isset(self::TYPES[$data['loai_dich_vu']])) $errors['loai_dich_vu'] = 'Chọn loại dịch vụ hợp lệ.';
        if (!is_string($data['trang_thai'] ?? null) || !isset(self::STATES[$data['trang_thai']])) $errors['trang_thai'] = 'Trạng thái không hợp lệ.';
        if (!is_string($data['don_gia'] ?? null) || !preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', trim($data['don_gia']))) $errors['don_gia'] = 'Giá từ 0 đến 9999999999.99, tối đa 2 số lẻ; không nhập dấu phân cách hàng nghìn.';
        $mode = $data['theo_doi_ton'] ?? null;
        $stock = $data['so_luong_ton'] ?? null;
        if (!in_array($mode, ['0', '1'], true)) $errors['theo_doi_ton'] = 'Chọn chế độ theo dõi tồn.';
        if ($mode === '1' && (!is_string($stock) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/', $stock)
            || filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]) === false)) $errors['so_luong_ton'] = 'Nhập số nguyên từ 0 đến 2147483647.';
        return $errors;
    }

    public static function normalize(array $data): array
    {
        $price = explode('.', trim($data['don_gia']));
        $description = trim($data['mo_ta'] ?? '');
        return ['ma_co_so' => (int) $data['ma_co_so'], 'ten_dich_vu' => trim($data['ten_dich_vu']),
            'loai_dich_vu' => $data['loai_dich_vu'], 'don_vi_tinh' => trim($data['don_vi_tinh']),
            'don_gia' => (ltrim($price[0], '0') ?: '0') . '.' . str_pad($price[1] ?? '', 2, '0'),
            'so_luong_ton' => $data['theo_doi_ton'] === '0' ? null : (int) $data['so_luong_ton'],
            'mo_ta' => $description === '' ? null : $description, 'trang_thai' => $data['trang_thai']];
    }

    public static function version(array $row): string
    {
        $values = [];
        foreach (array_merge(['ma_dich_vu'], self::FIELDS) as $field) $values[$field] = ($row[$field] ?? null) === null ? null : (string) $row[$field];
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function filters(array $query): array
    {
        $filters = AdminListService::filters($query, ['ma_co_so'], ['loai_dich_vu' => array_keys(self::TYPES), 'trang_thai' => array_keys(self::STATES)]);
        $unit = $query['don_vi_tinh'] ?? '';
        if (!is_string($unit) || !mb_check_encoding($unit, 'UTF-8') || mb_strlen($unit) > 30 || preg_match('/\p{C}/u', $unit)) throw new HttpException(422, 'Bộ lọc đơn vị tính không hợp lệ.');
        if ($filters['ma_co_so'] !== '' && !$this->services->facility((int) $filters['ma_co_so'], $this->scope)) throw new HttpException(403, 'Cơ sở nằm ngoài phạm vi được phép.');
        return $filters + ['don_vi_tinh' => trim($unit)];
    }

    public function find(int $id): array
    {
        return $this->services->find($id, $this->scope) ?? throw new HttpException(404, 'Không tìm thấy dịch vụ trong phạm vi được phép.');
    }

    public function save(array $data, ?int $id = null): int
    {
        $this->requireWrite();
        if ($errors = self::validate($data)) throw new ValidationException($errors);
        return $this->write(self::normalize($data), $id, $data['version'] ?? null);
    }

    public function changeStatus(int $id, $status, $version, bool $confirmed): void
    {
        $this->requireWrite();
        if (!is_string($status) || !isset(self::STATES[$status])) throw new ValidationException(['trang_thai' => 'Trạng thái không hợp lệ.']);
        if (!$confirmed) throw new ValidationException(['confirmed' => 'Vui lòng xác nhận thao tác thay đổi trạng thái.']);
        $this->write(null, $id, $version, $status);
    }

    public function requireWrite(): void
    {
        if (!$this->scope['write']) throw new HttpException(403, 'Nhân viên chỉ được xem dịch vụ.');
    }

    private function write(?array $data, ?int $id, $version, ?string $status = null): int
    {
        $before = $id === null ? null : $this->find($id);
        $target = $data['ma_co_so'] ?? (int) $before['ma_co_so'];
        $ids = array_unique([$target, (int) ($before['ma_co_so'] ?? $target)]);
        sort($ids);
        $facilities = new CoSo($this->pdo);
        $this->pdo->beginTransaction();
        try {
            $rows = [];
            foreach ($ids as $facilityId) {
                $rows[$facilityId] = $this->services->facility($facilityId, $this->scope);
                if (!$rows[$facilityId]) throw new HttpException(403, 'Cơ sở nằm ngoài phạm vi được phép.');
            }
            $companyIds = array_unique(array_column($rows, 'ma_cong_ty')); sort($companyIds);
            $companies = [];
            foreach ($companyIds as $companyId) $companies[$companyId] = $facilities->company((int) $companyId, true);
            foreach ($ids as $facilityId) {
                $locked = $facilities->find($facilityId, true);
                if (!$locked || (int) $locked['ma_cong_ty'] !== (int) $rows[$facilityId]['ma_cong_ty']) throw new HttpException(409, 'Phạm vi cơ sở vừa thay đổi. Vui lòng tải lại.');
                $rows[$facilityId] = $locked;
            }
            $current = $id === null ? null : $this->services->find($id, $this->scope, true);
            if ($id !== null && !$current) throw new HttpException(404, 'Không tìm thấy dịch vụ.');
            if ($current && (!is_string($version) || !preg_match('/\A[a-f0-9]{64}\z/', $version) || !hash_equals(self::version($current), $version))) throw new HttpException(409, 'Dịch vụ đã thay đổi sau khi bạn mở form. Dữ liệu vừa nhập chưa được lưu; hãy tải lại để đối chiếu.');
            if ($status === 'NGUNG') {
                $this->services->changeStatus($id, $status); $this->pdo->commit(); return $id;
            }
            if ($status !== null) {
                $input = $current; $input['ma_co_so'] = (string) $current['ma_co_so'];
                $input['theo_doi_ton'] = $current['so_luong_ton'] === null ? '0' : '1';
                $input['so_luong_ton'] = $current['so_luong_ton'] === null ? '' : (string) $current['so_luong_ton'];
                $input['mo_ta'] ??= ''; $input['trang_thai'] = $status;
                if ($errors = self::validate($input)) throw new ValidationException($errors);
                $data = self::normalize($input);
            } elseif ($current && $data['trang_thai'] !== $current['trang_thai']) {
                throw new ValidationException(['trang_thai' => 'Dùng trang xác nhận Ngừng/Kích hoạt để thay đổi trạng thái.']);
            }
            $moved = $current && (int) $current['ma_co_so'] !== $data['ma_co_so'];
            $facility = $rows[$target]; $company = $companies[$facility['ma_cong_ty']];
            if ($id === null || $moved || $data['trang_thai'] === 'DANG_CUNG_CAP') {
                if ($facility['trang_thai'] !== 'HOAT_DONG' || !$company || $company['trang_thai'] !== 'HOAT_DONG') throw new ValidationException(['ma_co_so' => 'Cơ sở và công ty phải đang hoạt động.']);
            }
            if ($current) {
                $references = $this->services->references($id, true);
                if ($moved && $references['total']) throw new ValidationException(['ma_co_so' => 'Không thể chuyển cơ sở vì dịch vụ đã có dữ liệu tham chiếu.']);
                if ($current['don_vi_tinh'] !== $data['don_vi_tinh'] && ($current['so_luong_ton'] > 0 || $references['unfinished'])) throw new ValidationException(['don_vi_tinh' => 'Chưa thể đổi đơn vị: tồn hiện tại phải bằng 0/không theo dõi và không có booking chưa hoàn tất.']);
                if ($current['loai_dich_vu'] !== $data['loai_dich_vu'] && $references['unfinished']) throw new ValidationException(['loai_dich_vu' => 'Không thể đổi loại khi có booking chưa hoàn tất.']);
            }
            if ($this->services->nameExists($data['ma_co_so'], $data['ten_dich_vu'], $id)) throw new ValidationException(['ten_dich_vu' => 'Tên dịch vụ đã tồn tại trong cơ sở này.']);
            if ($status !== null) $this->services->changeStatus($id, $status);
            else $id = $this->services->save($data, $id);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) throw new ValidationException(['ten_dich_vu' => 'Tên dịch vụ đã tồn tại trong cơ sở này.']);
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1452) throw new ValidationException(['ma_co_so' => 'Cơ sở liên kết không còn tồn tại.']);
            if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) throw new HttpException(409, 'Dữ liệu đang được cập nhật. Chưa lưu thay đổi; vui lòng tải lại.');
            throw $e;
        }
    }
}
