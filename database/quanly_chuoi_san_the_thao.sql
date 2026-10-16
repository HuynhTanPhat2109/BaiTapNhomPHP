-- =========================================================
-- CSDL: HỆ THỐNG QUẢN LÝ CHUỖI SÂN THỂ THAO VÀ NHƯỢNG QUYỀN
-- Công nghệ: MySQL / phpMyAdmin
-- =========================================================

CREATE DATABASE IF NOT EXISTS quanly_chuoi_san_the_thao
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE quanly_chuoi_san_the_thao;

-- QUY UOC CHO PHP BACKEND
-- Thống nhất thời gian PHP và phiên kết nối DB theo Asia/Ho_Chi_Minh (+07:00).
-- CHO_XAC_NHAN: bản nháp, không chiếm lịch.
-- CHO_THANH_TOAN: chiếm lịch tạm thời khi hiện tại < het_han_giu_san_luc.
-- DA_XAC_NHAN: chiếm lịch; HOAN_THANH: lịch sử đã sử dụng sân.
-- HET_HAN, DA_HUY: không chiếm lịch. Đúng thời điểm hết hạn là hết giữ sân,
-- kể cả khi tác vụ dọn dẹp chưa cập nhật trạng thái booking.
-- PHP kiểm tra bước 30 phút, ghép ngay_choi + gio_bat_dau và chỉ chấp nhận
-- thời điểm bắt đầu > hiện tại ở cả ba bước: chọn/khởi tạo booking,
-- xác nhận chuyển sang thanh toán, ngay trước khi bắt đầu giữ sân.
-- Frontend chỉ disabled slot quá giờ; backend luôn kiểm tra lại mọi dòng.
-- Trong transaction: khóa sân theo thứ tự thống nhất, đọc lịch hiện hành,
-- kiểm tra giao nhau, chốt giá/chi tiết, tạo thanh toán và giữ sân 6 phút.
-- Khoảng giao nhau: bat_dau_moi < ket_thuc_cu AND ket_thuc_moi > bat_dau_cu.
-- Tải lại trang/tạo lại QR không gia hạn. Hết hạn muốn đặt lại phải tạo booking mới.
-- Không sửa chi tiết/số tiền từ khi giữ sân; muốn đổi phải hủy và đặt lại.
-- PHP kiểm tra phạm vi vai trò, nhân viên-công ty-cơ sở, sân/dịch vụ cùng cơ sở,
-- giờ mở cửa, trạng thái hoạt động và cập nhật tồn dịch vụ bằng transaction.
-- PHP xác minh thanh toán từ nguồn đáng tin cậy, chống xử lý lặp mã giao dịch;
-- cập nhật thanh toán, xác nhận booking và lập hóa đơn trong cùng transaction.
-- Tiền đến muộn/sai số tiền hoặc thông báo trễ không còn đủ điều kiện xác nhận:
-- ghi CAN_DOI_SOAT, không tự phục hồi booking hay chiếm lại sân.
-- Tổng tiền từ các bảng chi tiết do PHP tính/cập nhật trong cùng transaction.
-- Không dùng trigger/stored procedure/event để thực thi các quy tắc động trên.


-- =========================================================
-- 1. VAI TRO
-- =========================================================

CREATE TABLE vai_tro (
    ma_vai_tro INT AUTO_INCREMENT PRIMARY KEY,
    ten_vai_tro VARCHAR(100) NOT NULL UNIQUE,
    mo_ta VARCHAR(255)
) ENGINE=InnoDB;


-- =========================================================
-- 2. TAI KHOAN
-- Chỉ lưu thông tin đăng nhập và phân quyền
-- =========================================================

CREATE TABLE tai_khoan (
    ma_tai_khoan INT AUTO_INCREMENT PRIMARY KEY,
    ma_vai_tro INT NOT NULL,
    ten_dang_nhap VARCHAR(100) NOT NULL UNIQUE,
    mat_khau VARCHAR(255) NOT NULL,
    trang_thai ENUM(
        'HOAT_DONG',
        'KHOA'
    ) NOT NULL DEFAULT 'HOAT_DONG',
    ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_taikhoan_vaitro
        FOREIGN KEY (ma_vai_tro)
        REFERENCES vai_tro(ma_vai_tro)
) ENGINE=InnoDB;


-- =========================================================
-- 3. CONG TY NHUONG QUYEN
-- =========================================================

CREATE TABLE cong_ty_nhuong_quyen (
    ma_cong_ty INT AUTO_INCREMENT PRIMARY KEY,
    ten_cong_ty VARCHAR(200) NOT NULL,
    ma_so_thue VARCHAR(30) UNIQUE,
    nguoi_dai_dien VARCHAR(150),
    dia_chi VARCHAR(255) NOT NULL,
    so_dien_thoai VARCHAR(20),
    email VARCHAR(150),
    ngay_tham_gia DATE,

    trang_thai ENUM(
        'HOAT_DONG',
        'NGUNG_HOAT_DONG'
    ) NOT NULL DEFAULT 'HOAT_DONG'
) ENGINE=InnoDB;


-- =========================================================
-- 4. HOP DONG NHUONG QUYEN
-- =========================================================

CREATE TABLE hop_dong_nhuong_quyen (
    ma_hop_dong INT AUTO_INCREMENT PRIMARY KEY,
    ma_cong_ty INT NOT NULL,
    so_hop_dong VARCHAR(50) NOT NULL UNIQUE,
    ngay_ky DATE NOT NULL,
    ngay_bat_dau DATE NOT NULL,
    ngay_ket_thuc DATE NOT NULL,
    phi_nhuong_quyen DECIMAL(15,2) NOT NULL DEFAULT 0,
    ty_le_phi DECIMAL(5,2) NOT NULL DEFAULT 0,
    noi_dung TEXT,
    trang_thai ENUM(
        'HIEU_LUC',
        'HET_HAN',
        'CHAM_DUT'
    ) NOT NULL DEFAULT 'HIEU_LUC',

    CONSTRAINT fk_hopdong_congty
        FOREIGN KEY (ma_cong_ty)
        REFERENCES cong_ty_nhuong_quyen(ma_cong_ty),

    CONSTRAINT chk_hopdong_ngay
        CHECK (ngay_ket_thuc >= ngay_bat_dau),

    CONSTRAINT chk_hopdong_phi
        CHECK (phi_nhuong_quyen >= 0),

    CONSTRAINT chk_hopdong_tyle
        CHECK (ty_le_phi >= 0 AND ty_le_phi <= 100)

) ENGINE=InnoDB;


-- =========================================================
-- 5. CO SO
-- =========================================================

CREATE TABLE co_so (
    ma_co_so INT AUTO_INCREMENT PRIMARY KEY,
    ma_cong_ty INT NOT NULL,
    ten_co_so VARCHAR(200) NOT NULL,
    dia_chi VARCHAR(255) NOT NULL,
    so_dien_thoai VARCHAR(20),
    email VARCHAR(150),
    gio_mo_cua TIME NOT NULL,
    gio_dong_cua TIME NOT NULL,
    mo_ta TEXT,
    trang_thai ENUM(
        'HOAT_DONG',
        'TAM_NGUNG'
    ) NOT NULL DEFAULT 'HOAT_DONG',

    CONSTRAINT fk_coso_congty
        FOREIGN KEY (ma_cong_ty)
        REFERENCES cong_ty_nhuong_quyen(ma_cong_ty),

    CONSTRAINT chk_coso_gio
        CHECK (gio_dong_cua > gio_mo_cua)

) ENGINE=InnoDB;


-- =========================================================
-- 6. NHAN VIEN
-- =========================================================

CREATE TABLE nhan_vien (
    ma_nhan_vien INT AUTO_INCREMENT PRIMARY KEY,
    ma_cong_ty INT NOT NULL,
    ma_co_so INT NULL,
    ma_tai_khoan INT NULL UNIQUE,
    ho_ten VARCHAR(150) NOT NULL,
    ngay_sinh DATE,
    gioi_tinh ENUM(
        'NAM',
        'NU',
        'KHAC'
    ),
    so_dien_thoai VARCHAR(20),
    email VARCHAR(150),
    dia_chi VARCHAR(255),
    chuc_vu VARCHAR(100),
    ngay_vao_lam DATE,
    trang_thai ENUM(
        'DANG_LAM',
        'DA_NGHI'
    ) NOT NULL DEFAULT 'DANG_LAM',

    CONSTRAINT fk_nhanvien_congty
        FOREIGN KEY (ma_cong_ty)
        REFERENCES cong_ty_nhuong_quyen(ma_cong_ty),

    CONSTRAINT fk_nhanvien_coso
        FOREIGN KEY (ma_co_so)
        REFERENCES co_so(ma_co_so),

    CONSTRAINT fk_nhanvien_taikhoan
        FOREIGN KEY (ma_tai_khoan)
        REFERENCES tai_khoan(ma_tai_khoan)

) ENGINE=InnoDB;

-- =========================================================
-- 7. KHACH HANG
-- =========================================================

CREATE TABLE khach_hang (
    ma_khach_hang INT AUTO_INCREMENT PRIMARY KEY,
    ma_tai_khoan INT NOT NULL UNIQUE,
    ho_ten VARCHAR(150) NOT NULL,
    so_dien_thoai VARCHAR(20) NOT NULL,
    email VARCHAR(150),
    ngay_sinh DATE,
    gioi_tinh ENUM(
        'NAM',
        'NU',
        'KHAC'
    ),
    ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    trang_thai ENUM(
        'HOAT_DONG',
        'KHOA'
    ) NOT NULL DEFAULT 'HOAT_DONG',

    CONSTRAINT fk_khachhang_taikhoan
        FOREIGN KEY (ma_tai_khoan)
        REFERENCES tai_khoan(ma_tai_khoan)

) ENGINE=InnoDB;


-- =========================================================
-- 8. BO MON
-- Ví dụ: Pickleball, Bóng đá, Cầu lông...
-- =========================================================

CREATE TABLE bo_mon (
    ma_bo_mon INT AUTO_INCREMENT PRIMARY KEY,
    ten_bo_mon VARCHAR(100) NOT NULL UNIQUE,
    mo_ta VARCHAR(255),
    trang_thai ENUM(
        'HOAT_DONG',
        'NGUNG'
    ) NOT NULL DEFAULT 'HOAT_DONG'

) ENGINE=InnoDB;


-- =========================================================
-- 9. LOAI SAN
-- Một bộ môn có thể có nhiều loại sân
-- =========================================================

CREATE TABLE loai_san (
    ma_loai_san INT AUTO_INCREMENT PRIMARY KEY,
    ma_bo_mon INT NOT NULL,
    ten_loai_san VARCHAR(150) NOT NULL,
    mo_ta VARCHAR(255),
    trang_thai ENUM(
        'HOAT_DONG',
        'NGUNG'
    ) NOT NULL DEFAULT 'HOAT_DONG',

    CONSTRAINT uq_loaisan_bomon_ten
        UNIQUE (ma_bo_mon, ten_loai_san),

    CONSTRAINT fk_loaisan_bomon
        FOREIGN KEY (ma_bo_mon)
        REFERENCES bo_mon(ma_bo_mon)

) ENGINE=InnoDB;


-- =========================================================
-- 10. SAN
-- =========================================================

CREATE TABLE san (
    ma_san INT AUTO_INCREMENT PRIMARY KEY,
    ma_co_so INT NOT NULL,
    ma_loai_san INT NOT NULL,
    ten_san VARCHAR(150) NOT NULL,
    vi_tri VARCHAR(150),
    mo_ta TEXT,
    trang_thai ENUM(
        'HOAT_DONG',
        'BAO_TRI',
        'TAM_NGUNG'
    ) NOT NULL DEFAULT 'HOAT_DONG',

    CONSTRAINT uq_san_coso_ten
        UNIQUE (ma_co_so, ten_san),

    CONSTRAINT fk_san_coso
        FOREIGN KEY (ma_co_so)
        REFERENCES co_so(ma_co_so),

    CONSTRAINT fk_san_loaisan
        FOREIGN KEY (ma_loai_san)
        REFERENCES loai_san(ma_loai_san)

) ENGINE=InnoDB;


-- =========================================================
-- 11. BANG GIA
-- don_gia là giá 1 giờ; CUOI_TUAN là thứ Bảy và Chủ nhật.
-- PHP kiểm tra ranh giới 30 phút, không chồng lấn giá và phủ đủ khoảng chơi.
-- =========================================================

CREATE TABLE bang_gia (
    ma_bang_gia INT AUTO_INCREMENT PRIMARY KEY,
    ma_co_so INT NOT NULL,
    ma_loai_san INT NOT NULL,
    ten_bang_gia VARCHAR(150) NOT NULL,
    loai_ngay ENUM(
        'NGAY_THUONG',
        'CUOI_TUAN'
    ) NOT NULL,
    gio_bat_dau TIME NOT NULL,
    gio_ket_thuc TIME NOT NULL,
    don_gia DECIMAL(12,2) NOT NULL,
    ngay_bat_dau DATE NOT NULL,
    ngay_ket_thuc DATE NULL,
    trang_thai ENUM(
        'DANG_AP_DUNG',
        'NGUNG'
    ) NOT NULL DEFAULT 'DANG_AP_DUNG',

    CONSTRAINT fk_banggia_coso
        FOREIGN KEY (ma_co_so)
        REFERENCES co_so(ma_co_so),

    CONSTRAINT fk_banggia_loaisan
        FOREIGN KEY (ma_loai_san)
        REFERENCES loai_san(ma_loai_san),

    CONSTRAINT chk_banggia_gio
        CHECK (gio_ket_thuc > gio_bat_dau),

    CONSTRAINT chk_banggia_dongia
        CHECK (don_gia >= 0),

    CONSTRAINT chk_banggia_ngay
        CHECK (
            ngay_ket_thuc IS NULL
            OR ngay_ket_thuc >= ngay_bat_dau
        )

) ENGINE=InnoDB;


-- =========================================================
-- 12. DAT SAN
-- =========================================================

CREATE TABLE dat_san (
    ma_dat_san INT AUTO_INCREMENT PRIMARY KEY,
    ma_co_so INT NOT NULL,
    ma_khach_hang INT NOT NULL,
    ma_nhan_vien INT NULL,
    ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bat_dau_giu_san_luc DATETIME NULL,
    het_han_giu_san_luc DATETIME NULL,
    tong_tien_san DECIMAL(12,2) NOT NULL DEFAULT 0,
    tong_tien_dich_vu DECIMAL(12,2) NOT NULL DEFAULT 0,
    tong_tien_tam_tinh DECIMAL(12,2) NOT NULL DEFAULT 0,
    trang_thai_dat_san ENUM(
        'CHO_XAC_NHAN',
        'CHO_THANH_TOAN',
        'DA_XAC_NHAN',
        'HOAN_THANH',
        'HET_HAN',
        'DA_HUY'
    ) NOT NULL DEFAULT 'CHO_XAC_NHAN',

    ghi_chu VARCHAR(500),

    INDEX idx_datsan_trangthai_hethan (trang_thai_dat_san, het_han_giu_san_luc),

    CONSTRAINT fk_datsan_coso
        FOREIGN KEY (ma_co_so)
        REFERENCES co_so(ma_co_so),

    CONSTRAINT fk_datsan_khachhang
        FOREIGN KEY (ma_khach_hang)
        REFERENCES khach_hang(ma_khach_hang),

    CONSTRAINT fk_datsan_nhanvien
        FOREIGN KEY (ma_nhan_vien)
        REFERENCES nhan_vien(ma_nhan_vien),

    CONSTRAINT chk_datsan_tiensan
        CHECK (tong_tien_san >= 0),

    CONSTRAINT chk_datsan_tiendichvu
        CHECK (tong_tien_dich_vu >= 0),

    CONSTRAINT chk_datsan_tamtinh
        CHECK (tong_tien_tam_tinh >= 0),

    CONSTRAINT chk_datsan_tongtien
        CHECK (tong_tien_tam_tinh = tong_tien_san + tong_tien_dich_vu),

    CONSTRAINT chk_datsan_mocgiusan
        CHECK (
            (bat_dau_giu_san_luc IS NULL AND het_han_giu_san_luc IS NULL)
            OR (bat_dau_giu_san_luc IS NOT NULL
                AND het_han_giu_san_luc IS NOT NULL
                AND het_han_giu_san_luc > bat_dau_giu_san_luc)
        ),

    CONSTRAINT chk_datsan_trangthai_giusan
        CHECK (
            trang_thai_dat_san IN ('CHO_XAC_NHAN', 'DA_HUY')
            OR (bat_dau_giu_san_luc IS NOT NULL AND het_han_giu_san_luc IS NOT NULL)
        )

) ENGINE=InnoDB;


-- =========================================================
-- 13. CHI TIET DAT SAN
-- Mỗi dòng là một khoảng cùng đơn giá theo giờ, trong cùng ngày.
-- Qua nhiều khung giá: PHP tách thành nhiều dòng, tính tiền theo thời lượng.
-- Ví dụ 80.000đ/giờ x 30 phút = 40.000đ; dùng số thập phân chính xác,
-- làm tròn từng dòng nhất quán rồi cộng tổng. Không tính tiền bằng float.
-- Snapshot tên/giá và chi tiết được đóng băng từ khi bắt đầu thanh toán,
-- dùng làm chi tiết tiền sân trên hóa đơn, không sửa/xóa sau thanh toán.
-- =========================================================

CREATE TABLE chi_tiet_dat_san (
    ma_chi_tiet INT AUTO_INCREMENT PRIMARY KEY,
    ma_dat_san INT NOT NULL,
    ma_san INT NOT NULL,
    ten_san_snapshot VARCHAR(150) NOT NULL,
    ngay_choi DATE NOT NULL,
    gio_bat_dau TIME NOT NULL,
    gio_ket_thuc TIME NOT NULL,
    don_gia DECIMAL(12,2) NOT NULL,
    thanh_tien DECIMAL(12,2) NOT NULL,

    CONSTRAINT fk_ctdatsan_datsan
        FOREIGN KEY (ma_dat_san)
        REFERENCES dat_san(ma_dat_san),

    INDEX idx_ctdatsan_lich (ma_san, ngay_choi, gio_bat_dau),

    CONSTRAINT fk_ctdatsan_san
        FOREIGN KEY (ma_san)
        REFERENCES san(ma_san),

    CONSTRAINT chk_ctdatsan_gio
        CHECK (gio_ket_thuc > gio_bat_dau),

    CONSTRAINT chk_ctdatsan_dongia
        CHECK (don_gia >= 0),

    CONSTRAINT chk_ctdatsan_thanhtien
        CHECK (thanh_tien >= 0)

) ENGINE=InnoDB;



-- =========================================================
-- 14. DICH VU
-- =========================================================

CREATE TABLE dich_vu (
    ma_dich_vu INT AUTO_INCREMENT PRIMARY KEY,
    ma_co_so INT NOT NULL,
    ten_dich_vu VARCHAR(150) NOT NULL,
    loai_dich_vu ENUM(
        'CHO_THUE',
        'DO_UONG',
        'HUAN_LUYEN',
        'KHAC'
    ) NOT NULL,

    don_vi_tinh VARCHAR(30) NOT NULL,
    don_gia DECIMAL(12,2) NOT NULL,
    so_luong_ton INT NULL,
    mo_ta VARCHAR(255),
    trang_thai ENUM(
        'DANG_CUNG_CAP',
        'NGUNG'
    ) NOT NULL DEFAULT 'DANG_CUNG_CAP',
    CONSTRAINT uq_dichvu_coso_ten
        UNIQUE (ma_co_so, ten_dich_vu),
    CONSTRAINT fk_dichvu_coso
        FOREIGN KEY (ma_co_so)
        REFERENCES co_so(ma_co_so),
    CONSTRAINT chk_dichvu_dongia
        CHECK (don_gia >= 0),
    CONSTRAINT chk_dichvu_soluongton
        CHECK (
            so_luong_ton IS NULL
            OR so_luong_ton >= 0
        )
) ENGINE=InnoDB;


-- =========================================================
-- 15. CHI TIET DICH VU DAT
-- Snapshot dùng cho hóa đơn; PHP đóng băng cùng chi tiết sân khi thanh toán.
-- =========================================================

CREATE TABLE chi_tiet_dich_vu_dat (
    ma_chi_tiet_dv INT AUTO_INCREMENT PRIMARY KEY,
    ma_dat_san INT NOT NULL,
    ma_dich_vu INT NOT NULL,
    ten_dich_vu_snapshot VARCHAR(150) NOT NULL,
    don_vi_tinh_snapshot VARCHAR(30) NOT NULL,
    so_luong INT NOT NULL,
    don_gia DECIMAL(12,2) NOT NULL,
    thanh_tien DECIMAL(12,2) NOT NULL,

    CONSTRAINT uq_dichvu_dat
        UNIQUE (ma_dat_san, ma_dich_vu),

    CONSTRAINT fk_ctdv_datsan
        FOREIGN KEY (ma_dat_san)
        REFERENCES dat_san(ma_dat_san),

    CONSTRAINT fk_ctdv_dichvu
        FOREIGN KEY (ma_dich_vu)
        REFERENCES dich_vu(ma_dich_vu),

    CONSTRAINT chk_ctdv_soluong
        CHECK (so_luong > 0),

    CONSTRAINT chk_ctdv_dongia
        CHECK (don_gia >= 0),

    CONSTRAINT chk_ctdv_thanhtien
        CHECK (thanh_tien >= 0),

    CONSTRAINT chk_ctdv_tinhtien
        CHECK (thanh_tien = so_luong * don_gia)

) ENGINE=InnoDB;

-- =========================================================
-- 16. HOA DON
-- Chỉ lập khi thanh toán thành công; mỗi booking tối đa một hóa đơn.
-- Chi tiết lấy từ chi_tiet_dat_san và chi_tiet_dich_vu_dat đã đóng băng.
-- Tra thông tin thanh toán qua ma_dat_san; không lưu lặp tại hóa đơn.
-- =========================================================

CREATE TABLE hoa_don (
    ma_hoa_don INT AUTO_INCREMENT PRIMARY KEY,
    ma_dat_san INT NOT NULL UNIQUE,
    ma_nhan_vien INT NULL,
    ngay_lap DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tien_san DECIMAL(12,2) NOT NULL DEFAULT 0,
    tien_dich_vu DECIMAL(12,2) NOT NULL DEFAULT 0,
    tong_tien DECIMAL(12,2) NOT NULL DEFAULT 0,
    trang_thai ENUM(
        'DA_LAP',
        'HUY'
    ) NOT NULL DEFAULT 'DA_LAP',
    ghi_chu VARCHAR(500),

    CONSTRAINT fk_hoadon_datsan
        FOREIGN KEY (ma_dat_san)
        REFERENCES dat_san(ma_dat_san),

    CONSTRAINT fk_hoadon_nhanvien
        FOREIGN KEY (ma_nhan_vien)
        REFERENCES nhan_vien(ma_nhan_vien),

    CONSTRAINT chk_hoadon_tiensan
        CHECK (tien_san >= 0),

    CONSTRAINT chk_hoadon_tiendichvu
        CHECK (tien_dich_vu >= 0),

    CONSTRAINT chk_hoadon_tongtien
        CHECK (tong_tien >= 0),

    CONSTRAINT chk_hoadon_tinhtien
        CHECK (tong_tien = tien_san + tien_dich_vu)

) ENGINE=InnoDB;


-- =========================================================
-- 17. THANH TOAN
-- Một yêu cầu thanh toán toàn bộ cho mỗi booking, dùng một nguồn thanh toán.
-- QR sinh từ ma_tham_chieu và so_tien; không lưu ảnh QR.
-- Hạn giữ sân lấy từ dat_san, không lưu lặp tại đây.
-- =========================================================

CREATE TABLE thanh_toan (
    ma_thanh_toan INT AUTO_INCREMENT PRIMARY KEY,
    ma_dat_san INT NOT NULL UNIQUE,
    ma_tham_chieu VARCHAR(100) NOT NULL UNIQUE,
    so_tien DECIMAL(12,2) NOT NULL,
    phuong_thuc ENUM('CHUYEN_KHOAN') NOT NULL DEFAULT 'CHUYEN_KHOAN',
    trang_thai ENUM(
        'CHO_THANH_TOAN',
        'THANH_CONG',
        'HET_HAN',
        'HUY',
        'CAN_DOI_SOAT'
    ) NOT NULL DEFAULT 'CHO_THANH_TOAN',
    ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ngay_thanh_toan DATETIME NULL,
    ma_giao_dich VARCHAR(150) NULL UNIQUE,
    so_tien_thuc_nhan DECIMAL(12,2) NULL,
    ghi_chu VARCHAR(500),

    CONSTRAINT fk_thanhtoan_datsan
        FOREIGN KEY (ma_dat_san)
        REFERENCES dat_san(ma_dat_san),

    CONSTRAINT chk_thanhtoan_sotien
        CHECK (so_tien >= 0),

    CONSTRAINT chk_thanhtoan_thucnhan
        CHECK (so_tien_thuc_nhan IS NULL OR so_tien_thuc_nhan >= 0),

    CONSTRAINT chk_thanhtoan_thanhcong
        CHECK (
            trang_thai <> 'THANH_CONG'
            OR (ngay_thanh_toan IS NOT NULL
                AND ma_giao_dich IS NOT NULL
                AND so_tien_thuc_nhan IS NOT NULL
                AND so_tien_thuc_nhan = so_tien)
        )

) ENGINE=InnoDB;


-- =========================================================
-- DỮ LIỆU DANH MỤC BAN ĐẦU
-- =========================================================


-- =========================================================
-- VAI TRO
-- =========================================================

INSERT INTO vai_tro (ten_vai_tro, mo_ta) VALUES
('QUAN_TRI_TAP_DOAN', 'Quản trị toàn bộ hệ thống'),
('QUAN_LY_CONG_TY', 'Quản lý công ty nhận nhượng quyền'),
('QUAN_LY_CO_SO', 'Quản lý một cơ sở sân thể thao'),
('NHAN_VIEN', 'Nhân viên tại cơ sở'),
('KHACH_HANG', 'Khách hàng sử dụng hệ thống');


-- =========================================================
-- BO MON
-- =========================================================

INSERT INTO bo_mon (ten_bo_mon, mo_ta) VALUES
('Pickleball', 'Bộ môn Pickleball'),
('Bóng đá', 'Bộ môn bóng đá'),
('Cầu lông', 'Bộ môn cầu lông'),
('Tennis', 'Bộ môn Tennis');


-- =========================================================
-- KẾT THÚC
-- =========================================================
