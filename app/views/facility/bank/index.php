<?php
$bodyClass = 'page-catalog';
$facilityId = (int) $facility['ma_co_so'];
?>
<section class="container catalog-page catalog-workspace admin-list">
    <header class="catalog-heading">
        <div>
            <p class="eyebrow">STRYKE / <?= e($facility['ten_co_so']) ?></p>
            <h1>Tài khoản ngân hàng nhận tiền</h1>
            <p class="subtitle" style="color: #666; margin-top: 4px;">Cấu hình tài khoản ngân hàng để tạo mã QR VietQR khi khách thanh toán đặt sân tại cơ sở này.</p>
        </div>
        <a class="btn btn--primary" href="<?= e(url('/facility/bank/create?facility_id=' . $facilityId)) ?>">
            + Thêm tài khoản
        </a>
    </header>

    <?php if (!empty($notice)): ?>
        <p class="alert alert--success" role="status" style="margin-bottom: 20px; padding: 12px 16px; background: #e8f5e9; color: #2e7d32; border-radius: 6px;">
            <?= e($notice) ?>
        </p>
    <?php endif; ?>

    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách tài khoản ngân hàng">
        <table class="catalog-table facility-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; text-align: left;">
                    <th class="list-stt" style="padding: 12px; width: 50px;">STT</th>
                    <th style="padding: 12px;">Ngân hàng</th>
                    <th style="padding: 12px;">Số tài khoản</th>
                    <th style="padding: 12px;">Chủ tài khoản</th>
                    <th style="padding: 12px; text-align: center;">Mặc định</th>
                    <th style="padding: 12px; text-align: center;">Trạng thái</th>
                    <th style="padding: 12px; text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $index => $acc): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td class="list-stt" style="padding: 12px;"><?= $index + 1 ?></td>
                    <td style="padding: 12px; font-weight: 600; color: #1a73e8;">
                        <?= e($acc['ten_ngan_hang']) ?>
                    </td>
                    <td style="padding: 12px; font-family: monospace; font-size: 1.05rem;">
                        <?= e($acc['so_tai_khoan']) ?>
                    </td>
                    <td style="padding: 12px; text-transform: uppercase;">
                        <?= e($acc['ten_chu_tai_khoan']) ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <?php if ($acc['la_mac_dinh'] == 1): ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #e3f2fd; color: #1565c0; font-size: 0.85rem; font-weight: 600; border-radius: 12px;">
                                ★ Nhận tiền chính
                            </span>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/facility/bank/set-default')) ?>" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($acc['ma_tk_ngan_hang']) ?>">
                                <input type="hidden" name="facility_id" value="<?= e($facilityId) ?>">
                                <button type="submit" class="btn btn--link" style="border: none; background: none; color: #666; cursor: pointer; text-decoration: underline; font-size: 0.85rem;">
                                    Đặt làm mặc định
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <?php if ($acc['trang_thai'] === 'HOAT_DONG'): ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #e8f5e9; color: #2e7d32; font-size: 0.85rem; border-radius: 12px;">
                                Hoạt động
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #ffebee; color: #c62828; font-size: 0.85rem; border-radius: 12px;">
                                Ngừng
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <div style="display: inline-flex; gap: 8px; align-items: center; justify-content: flex-end;">
                            <a href="<?= e(url('/facility/bank/edit?id=' . $acc['ma_tk_ngan_hang'])) ?>" style="color: #1a73e8; text-decoration: none; font-size: 0.9rem;">
                                Sửa
                            </a>
                            <span style="color: #ccc;">|</span>
                            <form method="post" action="<?= e(url('/facility/bank/toggle-status')) ?>" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($acc['ma_tk_ngan_hang']) ?>">
                                <input type="hidden" name="facility_id" value="<?= e($facilityId) ?>">
                                <button type="submit" style="border: none; background: none; color: <?= $acc['trang_thai'] === 'HOAT_DONG' ? '#e53935' : '#2e7d32' ?>; cursor: pointer; font-size: 0.9rem;">
                                    <?= $acc['trang_thai'] === 'HOAT_DONG' ? 'Tạm ngừng' : 'Kích hoạt' ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$accounts): ?>
                <tr>
                    <td colspan="7" style="padding: 30px; text-align: center; color: #777;">
                        Cơ sở này chưa cấu hình tài khoản ngân hàng nào. Vui lòng bấm <strong>"+ Thêm tài khoản"</strong> để khách có thể thanh toán qua VietQR.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
