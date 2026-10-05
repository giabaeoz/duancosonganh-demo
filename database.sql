CREATE DATABASE IF NOT EXISTS qlts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qlts;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ho_ten VARCHAR(100) NOT NULL,
  tai_khoan VARCHAR(50) NOT NULL UNIQUE,
  mat_khau VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE borrowers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ma_so VARCHAR(30) NOT NULL UNIQUE,
  ho_ten VARCHAR(100) NOT NULL,
  lien_he VARCHAR(100) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ten VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ten VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ma_tai_san VARCHAR(30) NOT NULL UNIQUE,
  ten VARCHAR(150) NOT NULL,
  category_id INT NULL,
  location_id INT NULL,
  ngay_su_dung DATE NULL,
  trang_thai ENUM('san_sang','dang_muon','bao_tri','ngung_su_dung') NOT NULL DEFAULT 'san_sang',
  mo_ta TEXT NULL,
  hinh_anh VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB;

CREATE TABLE loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  borrower_id INT NOT NULL,
  staff_giao_id INT NOT NULL,
  staff_nhan_id INT NULL,
  ngay_muon DATETIME NOT NULL,
  han_tra DATETIME NOT NULL,
  phong_muon VARCHAR(100) NULL,
  ngay_tra DATETIME NULL,
  tinh_trang_khi_tra ENUM('tot','hu_hong') NULL,
  ghi_chu TEXT NULL,
  trang_thai ENUM('dang_muon','da_tra') NOT NULL DEFAULT 'dang_muon',
  FOREIGN KEY (asset_id) REFERENCES assets(id),
  FOREIGN KEY (borrower_id) REFERENCES borrowers(id),
  FOREIGN KEY (staff_giao_id) REFERENCES users(id),
  FOREIGN KEY (staff_nhan_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  nguoi_giao VARCHAR(100) NOT NULL,
  nguoi_nhan VARCHAR(100) NOT NULL,
  from_location_id INT NULL,
  to_location_id INT NOT NULL,
  staff_id INT NOT NULL,
  thoi_diem DATETIME NOT NULL,
  ghi_chu TEXT NULL,
  FOREIGN KEY (asset_id) REFERENCES assets(id),
  FOREIGN KEY (from_location_id) REFERENCES locations(id),
  FOREIGN KEY (to_location_id) REFERENCES locations(id),
  FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE inventory_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_id INT NOT NULL,
  staff_id INT NOT NULL,
  bat_dau DATETIME NOT NULL,
  ket_thuc DATETIME NULL,
  trang_thai ENUM('dang_kiem','hoan_tat') NOT NULL DEFAULT 'dang_kiem',
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  asset_id INT NOT NULL,
  ket_qua ENUM('dung_vi_tri','sai_vi_tri') NOT NULL,
  thoi_diem DATETIME NOT NULL,
  UNIQUE KEY uq_session_asset (session_id, asset_id),
  FOREIGN KEY (session_id) REFERENCES inventory_sessions(id),
  FOREIGN KEY (asset_id) REFERENCES assets(id)
) ENGINE=InnoDB;

-- Dữ liệu mẫu để có cái chọn
INSERT INTO categories (ten) VALUES ('Máy tính'),('Thiết bị mạng'),('Máy chiếu'),('Dụng cụ thực hành'),('Điều khiển');
INSERT INTO locations (ten) VALUES ('Phòng thực hành 1'),('Phòng thực hành 2'),('Kho thiết bị'),('Văn phòng Khoa');
