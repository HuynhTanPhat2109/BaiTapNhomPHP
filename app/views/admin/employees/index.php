<?php $employees = $employees ?? []; ?>
<section class="container account-page">
    <div class="account-page__intro">
        <p class="eyebrow">Quản trị tập đoàn / Nhân viên</p>
        <h1 class="account-h1">Quản lý công ty</h1>
        <p>Danh sách nhân viên có vai trò quản lý công ty.</p>
    </div>
    <p><a class="btn btn--primary" href="<?= e(url('/admin/employees/create')) ?>">Thêm quản lý công ty</a></p>
    <?php if (!$employees): ?>
        <p role="status">Chưa có quản lý công ty nào.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr>
                    <th scope="col">Họ tên</th><th scope="col">Mã công ty</th><th scope="col">Email</th>
                    <th scope="col">Số điện thoại</th><th scope="col">Chức vụ</th><th scope="col">Ngày vào làm</th>
                    <th scope="col">Trạng thái</th><th scope="col">Thao tác</th>
                </tr></thead>
                <tbody>
                <?php foreach ($employees as $employee): ?>
                    <?php $id = (string) ($employee['ma_nhan_vien'] ?? ''); $status = $employee['trang_thai'] ?? ''; ?>
                    <tr>
                        <td><?= e($employee['ho_ten'] ?? '') ?></td>
                        <td><?= e($employee['ma_cong_ty'] ?? '') ?></td>
                        <td><?= e($employee['email'] ?? '') ?></td>
                        <td><?= e($employee['so_dien_thoai'] ?? '') ?></td>
                        <td><?= e($employee['chuc_vu'] ?? '') ?></td>
                        <td><?= e($employee['ngay_vao_lam'] ?? '') ?></td>
                        <td><?= $status === 'DANG_LAM' ? 'Đang làm' : ($status === 'DA_NGHI' ? 'Đã nghỉ' : 'Không xác định') ?></td>
                        <td>
                            <a class="btn btn--small" href="<?= e(url('/admin/employees/edit') . '&id=' . rawurlencode($id)) ?>">Sửa</a>
                            <?php if (in_array($status, ['DANG_LAM', 'DA_NGHI'], true)): ?>
                                <form method="post" action="<?= e(url('/admin/employees/status')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($id) ?>">
                                    <input type="hidden" name="trang_thai" value="<?= $status === 'DANG_LAM' ? 'DA_NGHI' : 'DANG_LAM' ?>">
                                    <button class="btn btn--small" type="submit"><?= $status === 'DANG_LAM' ? 'Cho nghỉ' : 'Kích hoạt lại' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
