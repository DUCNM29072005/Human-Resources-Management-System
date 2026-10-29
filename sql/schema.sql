-- =============================================
-- DATABASE: quanly_nhansu
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. Bảng phong_ban
-- ----------------------------
DROP TABLE IF EXISTS `phong_ban`;
CREATE TABLE `phong_ban` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ma_pb` VARCHAR(20) NOT NULL,
  `ten_pb` VARCHAR(100) NOT NULL,
  `so_dien_thoai` VARCHAR(15) DEFAULT NULL,
  `mo_ta` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ma_pb` (`ma_pb`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 2. Bảng chuc_vu
-- ----------------------------
DROP TABLE IF EXISTS `chuc_vu`;
CREATE TABLE `chuc_vu` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ma_cv` VARCHAR(20) NOT NULL,
  `ten_cv` VARCHAR(100) NOT NULL,
  `phu_cap` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ma_cv` (`ma_cv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 3. Bảng nhan_vien
-- ----------------------------
DROP TABLE IF EXISTS `nhan_vien`;
CREATE TABLE `nhan_vien` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ma_nv` VARCHAR(20) NOT NULL,
  `ho_ten` VARCHAR(100) NOT NULL,
  `gioi_tinh` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1: Nam, 0: Nữ',
  `ngay_sinh` DATE DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `so_dien_thoai` VARCHAR(15) DEFAULT NULL,
  `dia_chi` VARCHAR(255) DEFAULT NULL,
  `luong_co_ban` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `phong_ban_id` INT DEFAULT NULL,
  `chuc_vu_id` INT DEFAULT NULL,
  `ten_dang_nhap` VARCHAR(50) NOT NULL,
  `mat_khau` VARCHAR(255) NOT NULL,
  `vai_tro` ENUM('admin','nhan_vien') NOT NULL DEFAULT 'nhan_vien',
  `trang_thai` TINYINT NOT NULL DEFAULT 1 COMMENT '1: Đang làm, 0: Đã nghỉ',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ma_nv` (`ma_nv`),
  UNIQUE KEY `uk_ten_dang_nhap` (`ten_dang_nhap`),
  KEY `fk_nv_phong_ban` (`phong_ban_id`),
  KEY `fk_nv_chuc_vu` (`chuc_vu_id`),
  CONSTRAINT `fk_nv_phong_ban` FOREIGN KEY (`phong_ban_id`) REFERENCES `phong_ban` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nv_chuc_vu` FOREIGN KEY (`chuc_vu_id`) REFERENCES `chuc_vu` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 4. Bảng cham_cong
-- ----------------------------
DROP TABLE IF EXISTS `cham_cong`;
CREATE TABLE `cham_cong` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nhan_vien_id` INT NOT NULL,
  `ngay_cham` DATE NOT NULL,
  `gio_vao` TIME DEFAULT NULL,
  `gio_ra` TIME DEFAULT NULL,
  `trang_thai` ENUM('dung_gio','di_muon','ve_som','nghi_phep') NOT NULL DEFAULT 'dung_gio',
  `ghi_chu` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nv_ngay` (`nhan_vien_id`, `ngay_cham`),
  KEY `fk_cc_nhan_vien` (`nhan_vien_id`),
  CONSTRAINT `fk_cc_nhan_vien` FOREIGN KEY (`nhan_vien_id`) REFERENCES `nhan_vien` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 5. Bảng don_xin_nghi
-- ----------------------------
DROP TABLE IF EXISTS `don_xin_nghi`;
CREATE TABLE `don_xin_nghi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nhan_vien_id` INT NOT NULL,
  `loai_nghi` VARCHAR(50) NOT NULL,
  `ngay_bat_dau` DATE NOT NULL,
  `ngay_ket_thuc` DATE NOT NULL,
  `ly_do` TEXT NOT NULL,
  `trang_thai` ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
  `nguoi_duyet_id` INT DEFAULT NULL,
  `phan_hoi` VARCHAR(255) DEFAULT NULL,
  `ngay_tao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_duyet` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_don_nhan_vien` (`nhan_vien_id`),
  KEY `fk_don_nguoi_duyet` (`nguoi_duyet_id`),
  CONSTRAINT `fk_don_nhan_vien` FOREIGN KEY (`nhan_vien_id`) REFERENCES `nhan_vien` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_don_nguoi_duyet` FOREIGN KEY (`nguoi_duyet_id`) REFERENCES `nhan_vien` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;