-- =========================================================
-- Migration: Bổ sung trường số hóa đơn và mã thanh toán cho PR4
-- Ngày tạo: 2026-11-02
-- Mục đích: Mỗi hóa đơn liên kết duy nhất với một booking và một thanh toán,
--          đồng thời có số hóa đơn (so_hoa_don) định danh duy nhất.
-- =========================================================

ALTER TABLE hoa_don
    ADD COLUMN so_hoa_don VARCHAR(50) NULL AFTER ma_hoa_don,
    ADD COLUMN ma_thanh_toan INT NULL AFTER ma_dat_san;

ALTER TABLE hoa_don
    ADD UNIQUE KEY uq_hoadon_so_hoa_don (so_hoa_don),
    ADD UNIQUE KEY uq_hoadon_ma_thanh_toan (ma_thanh_toan),
    ADD CONSTRAINT fk_hoadon_thanhtoan
        FOREIGN KEY (ma_thanh_toan)
        REFERENCES thanh_toan(ma_thanh_toan);

-- Đồng bộ dữ liệu hóa đơn cũ (nếu có)
UPDATE hoa_don h
JOIN thanh_toan t ON t.ma_dat_san = h.ma_dat_san
SET h.ma_thanh_toan = t.ma_thanh_toan,
    h.so_hoa_don = CONCAT('INV', DATE_FORMAT(h.ngay_lap, '%y%m%d'), 'B', LPAD(h.ma_dat_san, 4, '0'), 'INIT')
WHERE h.so_hoa_don IS NULL;
