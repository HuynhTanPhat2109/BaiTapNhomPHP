<?php $bodyClass = 'page-catalog'; $contracts = $contracts ?? []; ?>
<section class="container account-page catalog-page admin-list">
    <header class="catalog-heading"><div>
        <p class="eyebrow">Quản trị tập đoàn / Hợp đồng nhượng quyền</p>
        <h1 class="account-h1">Quản lý hợp đồng nhượng quyền</h1>
        <p>Danh sách hợp đồng nhượng quyền trong hệ thống.</p>
    </div><a class="btn btn--primary" href="<?= e(url('/admin/contracts/create')) ?>">Thêm hợp đồng</a></header>

    <?php if (!$contracts): ?>
        <p role="status">Chưa có hợp đồng nhượng quyền nào.</p>
    <?php else: ?>
        <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách hợp đồng">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th scope="col">Số hợp đồng</th>
                        <th scope="col">Công ty</th>
                        <th scope="col">Ngày ký</th>
                        <th scope="col">Ngày bắt đầu</th>
                        <th scope="col">Ngày kết thúc</th>
                        <th scope="col">Phí nhượng quyền</th>
                        <th scope="col">Tỷ lệ phí</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $contract): ?>
                        <?php
                        $id = $contract['ma_hop_dong'] ?? '';
                        $status = $contract['trang_thai'] ?? '';
                        ?>
                        <tr>
                            <td><?= e($contract['so_hop_dong'] ?? '') ?></td>
                            <td><?= e($contract['ten_cong_ty'] ?? '') ?></td>
                            <td><?= e($contract['ngay_ky'] ?? '') ?></td>
                            <td><?= e($contract['ngay_bat_dau'] ?? '') ?></td>
                            <td><?= e($contract['ngay_ket_thuc'] ?? '') ?></td>
                            <td><?= e($contract['phi_nhuong_quyen'] ?? '') ?></td>
                            <td><?= e($contract['ty_le_phi'] ?? '') ?>%</td>
                            <td><form class="contract-actions" method="post" action="<?= e(url('/admin/contracts/status') . '&id=' . rawurlencode((string) $id)) ?>">
                                <?= csrf_field() ?>
                                <select class="contract-actions__select" id="trang_thai_<?= e((string) $id) ?>" name="trang_thai" aria-label="Trạng thái hợp đồng" required>
                                    <option value="HIEU_LUC" <?= $status === 'HIEU_LUC' ? 'selected' : '' ?>>Hiệu lực</option>
                                    <option value="HET_HAN" <?= $status === 'HET_HAN' ? 'selected' : '' ?>>Hết hạn</option>
                                    <option value="CHAM_DUT" <?= $status === 'CHAM_DUT' ? 'selected' : '' ?>>Chấm dứt</option>
                                </select>
                                <button class="list-action list-action--activate" type="submit">Cập nhật</button>
                            </form></td>
                            <td>
                                <a class="list-action list-action--edit" href="<?= e(url('/admin/contracts/edit') . '&id=' . rawurlencode((string) $id)) ?>">Sửa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
