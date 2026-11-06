<?php

namespace App\Models;

use PDO;

final class NhanVien
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM nhan_vien WHERE ma_nhan_vien = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findForCompany(int $id, int $companyId, bool $lock = false, ?int $facilityId = null): ?array
    {
        $stmt = $this->pdo->prepare("SELECT nv.* FROM nhan_vien nv
            LEFT JOIN tai_khoan tk ON tk.ma_tai_khoan = nv.ma_tai_khoan
            LEFT JOIN vai_tro vt ON vt.ma_vai_tro = tk.ma_vai_tro
            WHERE nv.ma_nhan_vien = :id AND nv.ma_cong_ty = :company"
            . ($facilityId === null ? '' : ' AND nv.ma_co_so = :actor_facility') . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id, 'company' => $companyId] + ($facilityId === null ? [] : ['actor_facility' => $facilityId]));
        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM nhan_vien ORDER BY ma_nhan_vien DESC')->fetchAll();
    }

    public function byCompany(int $companyId, ?int $facilityId = null): array
    {
        $stmt = $this->pdo->prepare("SELECT nv.* FROM nhan_vien nv
            LEFT JOIN tai_khoan tk ON tk.ma_tai_khoan = nv.ma_tai_khoan
            LEFT JOIN vai_tro vt ON vt.ma_vai_tro = tk.ma_vai_tro
            WHERE nv.ma_cong_ty = :company" . ($facilityId === null ? '' : ' AND nv.ma_co_so = :actor_facility') . '
            ORDER BY nv.ma_nhan_vien DESC');
        $stmt->execute(['company' => $companyId] + ($facilityId === null ? [] : ['actor_facility' => $facilityId]));
        return $stmt->fetchAll();
    }

    public function companyManagers(): array
    {
        return $this->pdo->query("SELECT nv.* FROM nhan_vien nv JOIN tai_khoan tk ON tk.ma_tai_khoan = nv.ma_tai_khoan
            JOIN vai_tro vt ON vt.ma_vai_tro = tk.ma_vai_tro WHERE vt.ten_vai_tro = 'QUAN_LY_CONG_TY'
            ORDER BY nv.ma_nhan_vien DESC")->fetchAll();
    }

    public function page(array $filters, ?int $companyId = null, ?int $facilityId = null): array
    {
        $where = $companyId === null ? [] : ['nv.ma_cong_ty=:actor_company'];
        $params = $companyId === null ? [] : ['actor_company' => $companyId];
        if ($facilityId !== null) { $where[] = 'nv.ma_co_so=:actor_facility'; $params['actor_facility'] = $facilityId; }
        $order = match ($filters['sort']) {
            'name_asc' => 'nv.ho_ten ASC, nv.ma_nhan_vien DESC', 'name_desc' => 'nv.ho_ten DESC, nv.ma_nhan_vien DESC',
            default => 'nv.ma_nhan_vien DESC',
        };
        $fields = ['q' => ['nv.ho_ten', 'nv.email', 'nv.so_dien_thoai', 'tk.ten_dang_nhap', 'f.ten_co_so', 'c.ten_cong_ty'], 'trang_thai' => 'nv.trang_thai', 'vai_tro' => 'vt.ten_vai_tro'];
        $fields += $companyId === null ? ['ma_cong_ty' => 'nv.ma_cong_ty'] : ['ma_co_so' => 'nv.ma_co_so'];
        return AdminList::page($this->pdo, ' FROM nhan_vien nv LEFT JOIN tai_khoan tk ON tk.ma_tai_khoan=nv.ma_tai_khoan
            LEFT JOIN vai_tro vt ON vt.ma_vai_tro=tk.ma_vai_tro
            LEFT JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty=nv.ma_cong_ty
            LEFT JOIN co_so f ON f.ma_co_so=nv.ma_co_so AND f.ma_cong_ty=nv.ma_cong_ty',
            'nv.*, vt.ten_vai_tro, tk.ten_dang_nhap, c.ten_cong_ty, f.ten_co_so', 'nv.ma_nhan_vien',
            $fields, $filters, $where, $params, $order);
    }

    public function findCompanyManager(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare("SELECT nv.* FROM nhan_vien nv JOIN tai_khoan tk ON tk.ma_tai_khoan = nv.ma_tai_khoan
            JOIN vai_tro vt ON vt.ma_vai_tro = tk.ma_vai_tro WHERE nv.ma_nhan_vien = :id AND vt.ten_vai_tro = 'QUAN_LY_CONG_TY'" . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function company(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ma_cong_ty, ten_cong_ty, trang_thai FROM cong_ty_nhuong_quyen WHERE ma_cong_ty = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function companies(): array
    {
        return $this->pdo->query('SELECT ma_cong_ty, ten_cong_ty, trang_thai FROM cong_ty_nhuong_quyen ORDER BY ten_cong_ty')->fetchAll();
    }

    public function facility(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ma_co_so, ma_cong_ty, ten_co_so, trang_thai FROM co_so WHERE ma_co_so = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function facilitiesByCompany(int $companyId, ?int $currentId = null): array
    {
        $stmt = $this->pdo->prepare("SELECT ma_co_so, ma_cong_ty, ten_co_so, trang_thai FROM co_so
            WHERE ma_cong_ty = :company AND (trang_thai = 'HOAT_DONG'
                OR (ma_co_so = :current AND trang_thai = 'TAM_NGUNG')) ORDER BY ten_co_so");
        $stmt->execute(['company' => $companyId, 'current' => $currentId ?? 0]);
        return $stmt->fetchAll();
    }

    public function accountRoleForEmployee(int $employeeId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT vt.ten_vai_tro FROM nhan_vien nv JOIN tai_khoan tk ON tk.ma_tai_khoan = nv.ma_tai_khoan
            JOIN vai_tro vt ON vt.ma_vai_tro = tk.ma_vai_tro WHERE nv.ma_nhan_vien = :id');
        $stmt->execute(['id' => $employeeId]);
        $role = $stmt->fetchColumn();
        return $role === false ? null : (string) $role;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO nhan_vien
            (ma_cong_ty, ma_co_so, ma_tai_khoan, ho_ten, ngay_sinh, gioi_tinh, so_dien_thoai, email, dia_chi, chuc_vu, ngay_vao_lam, trang_thai)
            VALUES (:ma_cong_ty, :ma_co_so, :ma_tai_khoan, :ho_ten, :ngay_sinh, :gioi_tinh, :so_dien_thoai, :email, :dia_chi, :chuc_vu, :ngay_vao_lam, :trang_thai)');
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $expectedCompanyId, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE nhan_vien SET ma_cong_ty = :ma_cong_ty, ma_co_so = :ma_co_so,
            ho_ten = :ho_ten, ngay_sinh = :ngay_sinh, gioi_tinh = :gioi_tinh, so_dien_thoai = :so_dien_thoai,
            email = :email, dia_chi = :dia_chi, chuc_vu = :chuc_vu, ngay_vao_lam = :ngay_vao_lam, trang_thai = :trang_thai
            WHERE ma_nhan_vien = :id AND ma_cong_ty = :expected_company');
        $stmt->execute($data + ['id' => $id, 'expected_company' => $expectedCompanyId]);
        if ($stmt->rowCount() === 1) {
            return true;
        }
        $check = $this->pdo->prepare('SELECT 1 FROM nhan_vien WHERE ma_nhan_vien = :id AND ma_cong_ty = :company');
        $check->execute(['id' => $id, 'company' => $expectedCompanyId]);
        return $check->fetchColumn() !== false;
    }

    public function changeStatus(int $id, int $companyId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE nhan_vien SET trang_thai = :status WHERE ma_nhan_vien = :id AND ma_cong_ty = :company');
        $stmt->execute(['status' => $status, 'id' => $id, 'company' => $companyId]);
        if ($stmt->rowCount() === 1) {
            return true;
        }
        $check = $this->pdo->prepare('SELECT 1 FROM nhan_vien WHERE ma_nhan_vien = :id AND ma_cong_ty = :company AND trang_thai = :status');
        $check->execute(['id' => $id, 'company' => $companyId, 'status' => $status]);
        return $check->fetchColumn() !== false;
    }

    /** Caller holds the employee row lock and creates the account in the same transaction. */
    public function linkFirstAccount(int $id, int $companyId, int $accountId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE nhan_vien SET ma_tai_khoan=:account
            WHERE ma_nhan_vien=:id AND ma_cong_ty=:company
                AND ma_tai_khoan IS NULL AND trang_thai='DANG_LAM'");
        $stmt->execute(['account' => $accountId, 'id' => $id, 'company' => $companyId]);
        return $stmt->rowCount() === 1;
    }
}
