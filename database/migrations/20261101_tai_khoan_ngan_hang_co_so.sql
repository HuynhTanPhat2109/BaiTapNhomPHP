-- =========================================================
-- Migration: Tạo bảng tài khoản ngân hàng cho cơ sở sân thể thao
-- Ngày tạo: 2026-11-01
-- Mục đích: Lưu tài khoản ngân hàng của từng cơ sở để sinh mã VietQR
-- Hướng dẫn: Chạy file này một lần trong phpMyAdmin hoặc MySQL CLI
--            Không import lại schema chính.
-- =========================================================

CREATE TABLE IF NOT EXISTS tai_khoan_ngan_hang_co_so (
    ma_tk_ngan_hang INT AUTO_INCREMENT PRIMARY KEY,
    ma_co_so INT NOT NULL,

    ten_ngan_hang VARCHAR(100) NOT NULL,          -- Tên rút gọn hoặc đầy đủ: Vietcombank, MBBank...
    ma_bin VARCHAR(10) NOT NULL,                  -- Mã BIN ngân hàng 6 số theo chuẩn VietQR/NAPAS
    so_tai_khoan VARCHAR(50) NOT NULL,            -- Số tài khoản nhận tiền
    ten_chu_tai_khoan VARCHAR(150) NOT NULL,      -- Tên chủ tài khoản: NGUYEN VAN A

    la_mac_dinh TINYINT(1) NOT NULL DEFAULT 1,    -- 1: Tài khoản mặc định nhận thanh toán của cơ sở
    trang_thai ENUM('HOAT_DONG', 'NGUNG')
        NOT NULL DEFAULT 'HOAT_DONG',

    ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tknh_coso (ma_co_so, la_mac_dinh, trang_thai),
    UNIQUE KEY uq_coso_bin_stk (ma_co_so, ma_bin, so_tai_khoan),

    CONSTRAINT fk_tknh_coso
        FOREIGN KEY (ma_co_so)
        REFERENCES co_so(ma_co_so)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
