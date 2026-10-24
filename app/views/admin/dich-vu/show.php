<?php
$bodyClass = 'page-catalog';
$formatMoney = require dirname(__DIR__) . '/partials/format-money.php';
$active = $item['trang_thai'] === 'DANG_CUNG_CAP';
?>
<section class="container catalog-page service-module">
    <p class="service-breadcrumb"><a class="admin-table-link" href="<?= e(url($listPath)) ?>">Dịch vụ</a> / Chi tiết</p>
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Chi tiết dịch vụ</p><h1><?= e($item['ten_dich_vu']) ?></h1></div><?php if ($canWrite): ?><a class="btn btn--primary" href="<?= e(url($listPath . '/edit') . '&id=' . $item['ma_dich_vu']) ?>">Sửa dịch vụ</a><?php endif; ?></header>
    <?php if ($errors): ?><div class="alert alert--danger" role="alert"><?php foreach ($errors as $message): ?><p><?= e($message) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><p class="alert alert--danger">Cơ sở/công ty đã ngừng hoạt động. Chưa đủ điều kiện kích hoạt dịch vụ.</p><?php endif; ?>
    <dl class="catalog-details">
        <div><dt>Mã dịch vụ</dt><dd>#<?= e($item['ma_dich_vu']) ?></dd></div>
        <div><dt>Cơ sở / Công ty</dt><dd><?= e($item['ten_co_so'] . ' / ' . $item['ten_cong_ty']) ?></dd></div>
        <div><dt>Loại dịch vụ</dt><dd><?= e(App\Services\DichVuService::TYPES[$item['loai_dich_vu']] ?? 'Không xác định') ?></dd></div>
        <div><dt>Đơn giá</dt><dd><?= e($formatMoney($item['don_gia'])) ?>/<?= e($item['don_vi_tinh']) ?></dd></div>
        <div><dt>Tồn kho</dt><dd><?= $item['so_luong_ton'] === null ? 'Không theo dõi tồn (NULL)' : e($item['so_luong_ton']) ?><?php if ($item['loai_dich_vu'] === 'CHO_THUE'): ?><p class="form-hint">Số lượng quản lý chưa phản ánh số dụng cụ sẵn sàng cho thuê, đang thuê hoặc đã trả.</p><?php endif; ?></dd></div>
        <div><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
        <div><dt>Trạng thái</dt><dd><span class="catalog-status" data-status="<?= e($item['trang_thai']) ?>"><?= e(App\Services\DichVuService::STATES[$item['trang_thai']] ?? 'Không xác định') ?></span></dd></div>
        <div><dt>Dữ liệu tham chiếu</dt><dd><?= e($related['total']) ?> chi tiết dịch vụ đặt; <?= e($related['unfinished']) ?> liên quan booking chưa hoàn tất theo kiểm tra bảo thủ.</dd></div>
    </dl>
    <?php if ($canWrite): ?>
        <section class="account-card" id="status-confirmation" aria-labelledby="service-status-heading">
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
