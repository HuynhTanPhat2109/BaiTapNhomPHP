-- Run ONCE on an existing STRYKE database, after backup. No row is updated or deleted.
-- Fresh installations already include these columns/constraints in the main schema.
-- MariaDB spells DROP CHECK as DROP CONSTRAINT; choose the server's syntax in one ALTER.
SET @receipt_drop_kind = IF(VERSION() LIKE '%MariaDB%', 'CONSTRAINT', 'CHECK');
SET @receipt_ddl = CONCAT('ALTER TABLE thanh_toan
 ADD COLUMN anh_minh_chung VARCHAR(255) NULL,
 ADD COLUMN minh_chung_gui_luc DATETIME NULL,
 ADD COLUMN ma_tai_khoan_xac_nhan INT NULL,
 ADD COLUMN xac_nhan_thu_cong_luc DATETIME NULL,
 ADD CONSTRAINT fk_thanhtoan_nguoi_xac_nhan FOREIGN KEY (ma_tai_khoan_xac_nhan) REFERENCES tai_khoan(ma_tai_khoan),
 DROP ', @receipt_drop_kind, ' chk_thanhtoan_thanhcong,
 ADD CONSTRAINT chk_thanhtoan_thanhcong CHECK (
  trang_thai <> ''THANH_CONG'' OR (
   so_tien_thuc_nhan IS NOT NULL AND so_tien_thuc_nhan = so_tien
   AND ((ngay_thanh_toan IS NOT NULL AND ma_giao_dich IS NOT NULL)
    OR (anh_minh_chung IS NOT NULL AND ma_tai_khoan_xac_nhan IS NOT NULL AND xac_nhan_thu_cong_luc IS NOT NULL))
  )
 )');
PREPARE receipt_migration FROM @receipt_ddl;
EXECUTE receipt_migration;
DEALLOCATE PREPARE receipt_migration;
