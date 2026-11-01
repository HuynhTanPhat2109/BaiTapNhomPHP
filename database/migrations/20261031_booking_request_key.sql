-- Run once on an existing database. Existing bookings retain NULL values.
-- No changes to payment, invoice or inventory data.
ALTER TABLE dat_san
    ADD COLUMN khoa_yeu_cau CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD COLUMN dau_van_tay_yeu_cau CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD UNIQUE KEY uq_datsan_khoa_yeu_cau (khoa_yeu_cau);
