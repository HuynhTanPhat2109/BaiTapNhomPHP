<?php
$bodyClass = 'page-catalog';
$formatMoney = require dirname(__DIR__) . '/partials/format-money.php';
$active = $item['trang_thai'] === 'DANG_CUNG_CAP';
$detailPath = $listPath; $detailLabel = 'Dịch vụ';
$detailTitle = $item['ten_dich_vu']; $detailId = $item['ma_dich_vu']; $detailCanEdit = $canWrite;
$detailStatusLabel = App\Services\DichVuService::STATES[$item['trang_thai']] ?? 'Không xác định';
?>
<section class="container catalog-page catalog-workspace detail-page service-module">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <?php if ($errors): ?><div class="alert alert--danger" role="alert"><?php foreach ($errors as $message): ?><p><?= e($message) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><p class="alert alert--danger">Cơ sở/công ty đã ngừng hoạt động. Chưa đủ điều kiện kích hoạt dịch vụ.</p><?php endif; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="service-info">
            <h2 id="service-info">Thông tin chung</h2>
            <dl class="detail-fields">
                <div class="detail-field--wide"><dt>Tên dịch vụ</dt><dd><?= e($item['ten_dich_vu']) ?></dd></div>
                <div><dt>Loại dịch vụ</dt><dd><span class="service-type" data-type="<?= e($item['loai_dich_vu']) ?>"><?= e(App\Services\DichVuService::TYPES[$item['loai_dich_vu']] ?? 'Không xác định') ?></span></dd></div>
                <div><dt>Mã dịch vụ</dt><dd>#<?= e($item['ma_dich_vu']) ?></dd></div>
                <div class="detail-field--wide"><dt>Cơ sở cung cấp</dt><dd><?= e($item['ten_co_so']) ?><small class="service-secondary"><?= e($item['ten_cong_ty']) ?></small></dd></div>
                <div class="detail-field--wide"><dt>Mô tả dịch vụ</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="service-price-stock">
            <h2 id="service-price-stock">Giá &amp; tồn kho</h2>
            <div class="detail-highlight"><span>Đơn giá</span><strong><?= e($formatMoney($item['don_gia'])) ?></strong><span>/<?= e($item['don_vi_tinh']) ?></span></div>
            <dl class="detail-fields">
                <div><dt>Đơn vị tính</dt><dd><?= e($item['don_vi_tinh']) ?></dd></div>
                <div><dt><?= $item['loai_dich_vu'] === 'CHO_THUE' ? 'Số lượng quản lý' : 'Số lượng tồn' ?></dt><dd><?= $item['so_luong_ton'] === null ? 'Không theo dõi tồn' : e($item['so_luong_ton']) ?></dd></div>
                <div class="detail-field--wide"><dt>Trạng thái tồn</dt><dd>
                    <?php if ($item['so_luong_ton'] === null): ?><span class="service-stock">Không theo dõi tồn</span>
                    <?php elseif ($item['loai_dich_vu'] === 'CHO_THUE'): ?><span class="service-stock">Theo dõi số lượng quản lý</span>
                    <?php elseif ((int) $item['so_luong_ton'] === 0): ?><span class="service-stock service-stock--empty">Hết hàng</span>
                    <?php else: ?><span class="service-stock service-stock--available">Còn hàng</span><?php endif; ?>
                </dd></div>
            </dl>
            <?php if ($item['loai_dich_vu'] === 'CHO_THUE'): ?><p class="form-hint">Số lượng quản lý chưa phản ánh số dụng cụ sẵn sàng cho thuê, đang thuê hoặc đã trả.</p><?php endif; ?>
        </section>
        <section class="detail-card detail-card--wide" aria-labelledby="service-related">
            <h2 id="service-related">Dữ liệu tham chiếu</h2>
            <dl class="detail-fields">
                <div><dt>Chi tiết dịch vụ đặt</dt><dd><?= e($related['total']) ?> tham chiếu</dd></div>
                <div><dt>Liên quan booking chưa hoàn tất</dt><dd><?= e($related['unfinished']) ?> theo kiểm tra bảo thủ</dd></div>
            </dl>
        </section>
    </div>
    <?php if ($canWrite): ?>
        <section class="account-card detail-action-panel" data-action="<?= $active ? 'stop' : 'activate' ?>" id="status-confirmation" aria-labelledby="service-status-heading">
            <h2 id="service-status-heading"><?= $active ? 'Ngừng cung cấp' : 'Kích hoạt lại' ?></h2>
            <p class="form-hint">Giữ nguyên tồn, giá và snapshot của booking/hóa đơn cũ. Không xóa dịch vụ.</p>
            <?php if (!$confirm): ?><a class="btn btn--outline" href="<?= e(url($listPath . '/show') . '&id=' . $item['ma_dich_vu'] . '&confirm=1#status-confirmation') ?>"><?= $active ? 'Xác nhận ngừng cung cấp…' : 'Xác nhận kích hoạt…' ?></a>
            <?php else: ?>
                <form method="post" action="<?= e(url($listPath . '/status')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($item['ma_dich_vu']) ?>"><input type="hidden" name="version" value="<?= e(App\Services\DichVuService::version($item)) ?>"><input type="hidden" name="trang_thai" value="<?= $active ? 'NGUNG' : 'DANG_CUNG_CAP' ?>">
                    <div class="form-group"><label><input type="checkbox" name="confirmed" value="1" required<?= field_error_attributes('confirmed', $errors) ?>> Tôi xác nhận <?= $active ? 'ngừng cung cấp' : 'kích hoạt lại' ?> dịch vụ.</label><?= field_error('confirmed', $errors) ?></div>
                    <div class="catalog-actions"><button class="btn btn--primary" type="submit"><?= $active ? 'Ngừng cung cấp' : 'Kích hoạt lại' ?></button><a class="btn btn--outline" href="<?= e(url($listPath . '/show') . '&id=' . $item['ma_dich_vu']) ?>">Hủy</a></div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <a class="btn btn--outline" href="<?= e(url($listPath)) ?>">Quay lại danh sách</a>
</section>
