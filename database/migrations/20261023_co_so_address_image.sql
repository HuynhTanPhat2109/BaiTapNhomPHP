-- Select the existing database in phpMyAdmin first. Run ONCE on the old schema.
-- New installations using the main schema must NOT run this migration.
ALTER TABLE co_so
    ADD COLUMN ma_tinh VARCHAR(10) NULL DEFAULT NULL,
    ADD COLUMN ten_tinh VARCHAR(150) NULL DEFAULT NULL,
    ADD COLUMN ma_phuong_xa VARCHAR(10) NULL DEFAULT NULL,
    ADD COLUMN ten_phuong_xa VARCHAR(150) NULL DEFAULT NULL,
    ADD COLUMN anh_dai_dien VARCHAR(255) NULL DEFAULT NULL,
    ADD INDEX idx_coso_tinh (ma_tinh);
