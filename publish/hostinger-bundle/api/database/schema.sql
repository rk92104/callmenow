-- MySQL 8+ — tables only. Poora script (DB + user + seed): full_install_mysql.sql

CREATE TABLE IF NOT EXISTS persons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  phone_number_type VARCHAR(16) NOT NULL DEFAULT 'Mobile',
  email VARCHAR(150) NOT NULL,
  address VARCHAR(300) NOT NULL,
  father_name VARCHAR(100) NOT NULL,
  vehicle_registration VARCHAR(20) NOT NULL DEFAULT '',
  emergency_contact_phone VARCHAR(20) NOT NULL DEFAULT '',
  emergency_contact_phone_type VARCHAR(16) NOT NULL DEFAULT 'Mobile',
  payment_completed TINYINT(1) NOT NULL DEFAULT 0,
  payment_reference VARCHAR(120) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_stickers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  public_id VARCHAR(40) NOT NULL,
  ivr_access_code VARCHAR(6) NULL COMMENT '6-digit keypad code for Exotel IVR',
  product_type VARCHAR(50) NOT NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Unused,1=Active',
  person_id INT NULL,
  scan_count INT NOT NULL DEFAULT 0,
  unique_scanner_count INT NOT NULL DEFAULT 0,
  created_at DATETIME(3) NOT NULL,
  activated_at DATETIME(3) NULL,
  payment_transaction_id VARCHAR(120) NULL COMMENT 'Razorpay pay_ id or offline payment ref',
  UNIQUE KEY uq_public_id (public_id),
  UNIQUE KEY uq_qr_stickers_ivr_access_code (ivr_access_code),
  KEY ix_person_id (person_id),
  KEY ix_created_at (created_at),
  CONSTRAINT fk_qr_person FOREIGN KEY (person_id) REFERENCES persons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_scan_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  qr_sticker_id INT NOT NULL,
  visitor_hash VARCHAR(64) NOT NULL,
  scanned_at_utc DATETIME(3) NOT NULL,
  user_agent_snippet VARCHAR(256) NULL,
  KEY ix_sticker_visitor (qr_sticker_id, visitor_hash),
  KEY ix_scanned_at (scanned_at_utc),
  CONSTRAINT fk_scan_sticker FOREIGN KEY (qr_sticker_id) REFERENCES qr_stickers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone_normalized VARCHAR(20) NOT NULL,
  qr_public_id VARCHAR(40) NULL,
  referral_code VARCHAR(32) NULL,
  source VARCHAR(64) NOT NULL DEFAULT 'scan_page_coupon',
  created_at_utc DATETIME(3) NOT NULL,
  KEY ix_created (created_at_utc),
  KEY ix_phone (phone_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
