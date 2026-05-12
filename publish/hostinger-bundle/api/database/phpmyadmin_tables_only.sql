-- =============================================================================
-- phpMyAdmin / Hostinger — jab database PEHLE SE BANA ho (jaise u618808554_qrcode)
-- =============================================================================
-- Steps:
--   1) Left sidebar se apna database click karo (selected hona chahiye).
--   2) Top par "Import" tab → ye file choose karo → Go.
--      YA "SQL" tab → poora copy-paste → Go.
-- CREATE DATABASE / USE yahan NAHI hai — jo DB select hai usmein tables ban jayengi.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `persons` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `phone_number_type` VARCHAR(16) NOT NULL DEFAULT 'Mobile',
  `email` VARCHAR(150) NOT NULL,
  `address` VARCHAR(300) NOT NULL,
  `father_name` VARCHAR(100) NOT NULL,
  `vehicle_registration` VARCHAR(20) NOT NULL DEFAULT '',
  `emergency_contact_phone` VARCHAR(20) NOT NULL DEFAULT '',
  `emergency_contact_phone_type` VARCHAR(16) NOT NULL DEFAULT 'Mobile',
  `payment_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `payment_reference` VARCHAR(120) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qr_stickers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(40) NOT NULL,
  `ivr_access_code` VARCHAR(6) NULL COMMENT '6-digit keypad code for Exotel IVR',
  `product_type` VARCHAR(50) NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Unused, 1=Active',
  `person_id` INT NULL,
  `scan_count` INT NOT NULL DEFAULT 0,
  `unique_scanner_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  `activated_at` DATETIME(3) NULL,
  `payment_transaction_id` VARCHAR(120) NULL COMMENT 'Razorpay pay_ id or offline ref',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_id` (`public_id`),
  UNIQUE KEY `uq_qr_stickers_ivr_access_code` (`ivr_access_code`),
  KEY `ix_person_id` (`person_id`),
  KEY `ix_created_at` (`created_at`),
  CONSTRAINT `fk_qr_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qr_scan_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `qr_sticker_id` INT NOT NULL,
  `visitor_hash` VARCHAR(64) NOT NULL,
  `scanned_at_utc` DATETIME(3) NOT NULL,
  `user_agent_snippet` VARCHAR(256) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sticker_visitor` (`qr_sticker_id`, `visitor_hash`),
  KEY `ix_scanned_at` (`scanned_at_utc`),
  CONSTRAINT `fk_scan_sticker` FOREIGN KEY (`qr_sticker_id`) REFERENCES `qr_stickers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marketing_leads` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `phone_normalized` VARCHAR(20) NOT NULL,
  `qr_public_id` VARCHAR(40) NULL,
  `referral_code` VARCHAR(32) NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'scan_page_coupon',
  `created_at_utc` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_created` (`created_at_utc`),
  KEY `ix_phone` (`phone_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sticker_orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `shipping_address` VARCHAR(500) NOT NULL,
  `city` VARCHAR(50) NOT NULL,
  `pincode` VARCHAR(10) NOT NULL,
  `product_id` VARCHAR(50) NOT NULL,
  `product_name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(18, 2) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
  `assigned_public_id` VARCHAR(40) NULL,
  `razorpay_order_id` VARCHAR(100) NULL,
  `razorpay_payment_id` VARCHAR(100) NULL,
  `razorpay_signature` VARCHAR(256) NULL,
  `created_at_utc` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_orders_created` (`created_at_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `qr_stickers` (`public_id`, `product_type`, `status`, `scan_count`, `unique_scanner_count`, `created_at`)
SELECT * FROM (
  SELECT 'CMN-SEED-01' AS `public_id`, 'CarSticker' AS `product_type`, 0 AS `status`, 0 AS `scan_count`, 0 AS `unique_scanner_count`, UTC_TIMESTAMP(3) AS `created_at`
  UNION ALL SELECT 'CMN-SEED-02', 'KeyFinder', 0, 0, 0, UTC_TIMESTAMP(3)
  UNION ALL SELECT 'CMN-SEED-03', 'LuggageTag', 0, 0, 0, UTC_TIMESTAMP(3)
) AS `s`
WHERE (SELECT COUNT(*) FROM `qr_stickers`) = 0;
