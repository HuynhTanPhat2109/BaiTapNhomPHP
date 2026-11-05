<section class="dashboard-card dashboard-report">
    <header><h2>Doanh thu từng cơ sở</h2><p>Cùng phạm vi và kỳ thanh toán; không nhân bản số tiền theo các dòng chi tiết.</p></header>
    <div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Doanh thu cơ sở"><table class="dashboard-table"><thead><tr><th>Công ty / Cơ sở</th><th>Booking đã thu</th><th>Tiền sân</th><th>Dịch vụ</th><th>Tổng đã thu</th></tr></thead><tbody>
        <?php foreach ($dashboard['facilityGroups'] as $row): ?><tr><th scope="row"><?= e($row['name']) ?><small><?= e($row['company']) ?></small></th><td><?= (int) $row['bookings'] ?></td><td><?= e($money($row['courts'])) ?></td><td><?= e($money($row['services'])) ?></td><td><strong><?= e($money($row['total'])) ?></strong></td></tr><?php endforeach; ?>
        <?php if (!$dashboard['facilityGroups']): ?><tr><td colspan="5">Chưa có cơ sở trong phạm vi.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<section class="dashboard-card dashboard-report">
    <header><h2>Khoản tiền cần rà soát</h2><p>Tách riêng khỏi doanh thu đã thu hợp lệ, theo ngày ghi nhận giao dịch.</p></header>
    <ul class="dashboard-top-list"><?php foreach ($dashboard['reviewFunds'] as $row): ?><li><span><?= $row['status'] === 'CAN_DOI_SOAT' ? 'Đang cần đối soát' : 'Đã thu trên booking hủy / hết hạn' ?> · <?= (int) $row['payments'] ?> giao dịch</span><strong><?= e($money($row['total'])) ?></strong></li><?php endforeach; ?></ul>
    <?php if (!$dashboard['reviewFunds']): ?><p class="dashboard-inline-empty">Chưa có khoản tiền cần rà soát được ghi nhận trong kỳ.</p><?php endif; ?>
    <p class="dashboard-disclaimer">Chưa có sổ hoàn tiền để xác định số tiền đã hoàn hoặc còn phải hoàn. Các khoản trên cần đối chiếu, không phải số dư nghĩa vụ hoàn trả.</p>
</section>
<section class="dashboard-card dashboard-report">
    <header><h2>Giao dịch thanh toán hợp lệ</h2><p>Tối đa 50 giao dịch gần nhất trong kỳ. Tổng doanh thu phía trên bao gồm toàn bộ giao dịch hợp lệ.</p></header>
    <div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Giao dịch trong phạm vi"><table class="dashboard-table"><thead><tr><th>Booking / Cơ sở</th><th>Ngày ghi nhận thu</th><th>Mã giao dịch / Cách xác nhận</th><th>Hóa đơn</th><th>Đã thu</th></tr></thead><tbody>
    <?php foreach ($dashboard['transactions'] as $row): ?><tr><th scope="row"><a href="<?= e(url('/admin/bookings/show?id=' . $row['ma_dat_san'])) ?>">#<?= (int) $row['ma_dat_san'] ?></a><small><?= e($row['ten_co_so']) ?></small></th><td><?= e(date('d/m/Y H:i', strtotime($row['ngay_ghi_nhan'] ?? $row['ngay_thanh_toan']))) ?></td><td><?= e($row['ma_giao_dich'] ?: 'Xác nhận thủ công') ?></td><td><?= e($row['so_hoa_don'] ?? 'Chưa lập') ?></td><td><?= e($money($row['so_tien_thuc_nhan'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$dashboard['transactions']): ?><tr><td colspan="5">Chưa có giao dịch thanh toán hợp lệ trong kỳ.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
