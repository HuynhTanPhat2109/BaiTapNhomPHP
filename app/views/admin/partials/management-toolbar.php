<?php
$filters = array_replace(App\Services\AdminListService::managementFilters($listPath, []), $filters ?? []);
$page ??= 1; $pages ??= 1;
$listLabel = match (basename($listPath)) { 'companies' => 'công ty', 'contracts' => 'hợp đồng', default => 'nhân viên' };
$filterFields = ['q' => ['label' => match (basename($listPath)) {
    'companies' => 'Tên hoặc mã công ty', 'contracts' => 'Số hoặc mã hợp đồng', default => 'Tìm kiếm nhân viên',
}]];
if ($listPath === '/admin/contracts' || $listPath === '/admin/employees') {
    $filterCompanies = $listPath === '/admin/contracts' ? ($companies ?? []) : ($options['companies'] ?? []);
    $filterFields['ma_cong_ty'] = ['label' => 'Công ty', 'options' => array_column($filterCompanies, 'ten_cong_ty', 'ma_cong_ty')];
}
if (in_array($listPath, ['/company/employees', '/facility/employees'], true)) {
    $filterFields['ma_co_so'] = ['label' => 'Cơ sở', 'options' => array_column($options['facilities'] ?? [], 'ten_co_so', 'ma_co_so')];
}
if (basename($listPath) === 'employees') {
    $filterFields['vai_tro'] = ['label' => 'Vai trò', 'options' => $listPath === '/admin/employees'
        ? ['QUAN_TRI_TAP_DOAN' => 'Quản trị tập đoàn', 'QUAN_LY_CONG_TY' => 'Quản lý công ty', 'QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên'] : ['QUAN_LY_CONG_TY' => 'Quản lý công ty', 'QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên']];
}
$filterFields['trang_thai'] = ['label' => 'Trạng thái', 'options' => match (basename($listPath)) {
    'companies' => ['HOAT_DONG' => 'Hoạt động', 'NGUNG_HOAT_DONG' => 'Ngừng hoạt động'],
    'contracts' => ['HIEU_LUC' => 'Hiệu lực', 'HET_HAN' => 'Hết hạn', 'CHAM_DUT' => 'Chấm dứt'],
    default => ['DANG_LAM' => 'Đang làm', 'DA_NGHI' => 'Đã nghỉ'],
}];
if ($listPath === '/admin/contracts') {
    $filterFields['tu_ngay'] = ['label' => 'Từ ngày', 'type' => 'date'];
    $filterFields['den_ngay'] = ['label' => 'Đến ngày', 'type' => 'date'];
}
$filterFields['sort'] = ['label' => 'Sắp xếp', 'options' => $listPath === '/admin/contracts'
    ? App\Services\AdminListService::CONTRACT_SORTS : App\Services\AdminListService::NAME_SORTS];
$managementLayout = in_array($listPath, ['/admin/companies', '/admin/contracts', '/admin/employees', '/company/employees', '/facility/employees'], true) ? basename($listPath) : null;
?>
<?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($managementLayout): ?><div class="management-filters management-filters--<?= e($managementLayout) ?><?= in_array($listPath, ['/company/employees', '/facility/employees'], true) ? ' management-filters--company-employees' : '' ?>"><?php endif; ?>
<?php require __DIR__ . '/list-toolbar.php'; ?>
<?php if ($managementLayout): ?></div><?php endif; ?>
<?php if ($listPath === '/admin/contracts'): ?><p class="form-hint">Lọc hợp đồng có thời hạn giao với khoảng ngày đã chọn; có thể để trống một đầu.</p><?php endif; ?>
