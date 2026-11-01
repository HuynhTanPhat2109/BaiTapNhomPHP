<?php $contracts = $contracts ?? []; ?>
<section class="container account-page catalog-workspace">
    <div class="account-page__intro">
        <p class="eyebrow">Quản trị tập đoàn / Hợp đồng nhượng quyền</p>
        <h1 class="account-h1">Quản lý hợp đồng nhượng quyền</h1>
        <p>Danh sách hợp đồng nhượng quyền trong hệ thống.</p>
    </div>
    <p><a class="btn btn--primary" href="<?= e(url('/admin/contracts/create')) ?>">Thêm hợp đồng</a></p>

    <?php if (!$contracts): ?>
        <p role="status">Chưa có hợp đồng nhượng quyền nào.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
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
                            <td class="list-nowrap"><?= e($contract['so_hop_dong'] ?? '') ?></td>
                            <td class="admin-table-name"><?= e($contract['ten_cong_ty'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($contract['ngay_ky'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($contract['ngay_bat_dau'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($contract['ngay_ket_thuc'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($contract['phi_nhuong_quyen'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($contract['ty_le_phi'] ?? '') ?>%</td>
                            <td><span class="catalog-status" data-status="<?= e($status) ?>"><?php
                                if ($status === 'HIEU_LUC') {
                                    echo 'Hiệu lực';
                                } elseif ($status === 'HET_HAN') {
                                    echo 'Hết hạn';
                                } elseif ($status === 'CHAM_DUT') {
                                    echo 'Chấm dứt';
                                } else {
                                    echo 'Không xác định';
                                }
                            ?></span></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a class="btn btn--small" href="<?= e(url('/admin/contracts/edit') . '&id=' . rawurlencode((string) $id)) ?>">Sửa</a>
                                    <form method="post" action="<?= e(url('/admin/contracts/status') . '&id=' . rawurlencode((string) $id)) ?>">
                                        <?= csrf_field() ?>
                                        <label for="trang_thai_<?= e((string) $id) ?>">Trạng thái</label>
                                        <select class="form-control" id="trang_thai_<?= e((string) $id) ?>" name="trang_thai" required>
                                            <option value="HIEU_LUC" <?= $status === 'HIEU_LUC' ? 'selected' : '' ?>>Hiệu lực</option>
                                            <option value="HET_HAN" <?= $status === 'HET_HAN' ? 'selected' : '' ?>>Hết hạn</option>
                                            <option value="CHAM_DUT" <?= $status === 'CHAM_DUT' ? 'selected' : '' ?>>Chấm dứt</option>
                                        </select>
                                        <button class="btn btn--small" type="submit">Đổi trạng thái</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
