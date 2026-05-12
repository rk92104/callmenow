-- =============================================================================
-- CallMeNow / QRCode App — FULL MySQL Fix & Install Script
-- =============================================================================
-- Purpose: 
--   1. Create all tables if they don't exist.
--   2. Safely add missing columns to existing tables (sticker_orders).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Table: persons
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 2. Table: qr_stickers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qr_stickers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(40) NOT NULL,
  `ivr_access_code` VARCHAR(6) NULL,
  `ivr_emergency_access_code` VARCHAR(6) NULL,
  `product_type` VARCHAR(50) NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `person_id` INT NULL,
  `scan_count` INT NOT NULL DEFAULT 0,
  `unique_scanner_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  `activated_at` DATETIME(3) NULL,
  `payment_transaction_id` VARCHAR(120) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_id` (`public_id`),
  UNIQUE KEY `uq_ivr_access_code` (`ivr_access_code`),
  UNIQUE KEY `uq_ivr_emergency_access_code` (`ivr_emergency_access_code`),
  CONSTRAINT `fk_qr_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: sticker_orders (The table that needs the fix)
-- -----------------------------------------------------------------------------
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. FIX: Add missing columns to 'sticker_orders' if they don't exist
-- -----------------------------------------------------------------------------
-- We use a stored procedure to safely add columns without errors if they already exist.
DROP PROCEDURE IF EXISTS AlterStickerOrders;
DELIMITER //
CREATE PROCEDURE AlterStickerOrders()
BEGIN
    -- Add assigned_public_id
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='sticker_orders' AND COLUMN_NAME='assigned_public_id' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `sticker_orders` ADD COLUMN `assigned_public_id` VARCHAR(40) NULL AFTER `status`;
    END IF;

    -- Add razorpay_order_id
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='sticker_orders' AND COLUMN_NAME='razorpay_order_id' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `sticker_orders` ADD COLUMN `razorpay_order_id` VARCHAR(100) NULL AFTER `assigned_public_id`;
    END IF;

    -- Add razorpay_payment_id
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='sticker_orders' AND COLUMN_NAME='razorpay_payment_id' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `sticker_orders` ADD COLUMN `razorpay_payment_id` VARCHAR(100) NULL AFTER `razorpay_order_id`;
    END IF;

    -- Add razorpay_signature
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='sticker_orders' AND COLUMN_NAME='razorpay_signature' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `sticker_orders` ADD COLUMN `razorpay_signature` VARCHAR(256) NULL AFTER `razorpay_payment_id`;
    END IF;
END //
DELIMITER ;

CALL AlterStickerOrders();
DROP PROCEDURE IF EXISTS AlterStickerOrders;

-- -----------------------------------------------------------------------------
-- 4b. FIX: Add missing columns to 'qr_stickers' if they don't exist
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS AlterQrStickers;
DELIMITER //
CREATE PROCEDURE AlterQrStickers()
BEGIN
    -- Add ivr_emergency_access_code
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='qr_stickers' AND COLUMN_NAME='ivr_emergency_access_code' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `qr_stickers` ADD COLUMN `ivr_emergency_access_code` VARCHAR(6) NULL AFTER `ivr_access_code`;
    END IF;

    -- Add Unique Key if missing
    IF NOT EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME='qr_stickers' AND INDEX_NAME='uq_ivr_emergency_access_code' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `qr_stickers` ADD UNIQUE KEY `uq_ivr_emergency_access_code` (`ivr_emergency_access_code`);
    END IF;
END //
DELIMITER ;

CALL AlterQrStickers();
DROP PROCEDURE IF EXISTS AlterQrStickers;

-- -----------------------------------------------------------------------------
-- 5. Other Tables
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qr_scan_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `qr_sticker_id` INT NOT NULL,
  `visitor_hash` VARCHAR(64) NOT NULL,
  `scanned_at_utc` DATETIME(3) NOT NULL,
  `user_agent_snippet` VARCHAR(256) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_scan_sticker` FOREIGN KEY (`qr_sticker_id`) REFERENCES `qr_stickers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marketing_leads` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `phone_normalized` VARCHAR(20) NOT NULL,
  `qr_public_id` VARCHAR(40) NULL,
  `referral_code` VARCHAR(32) NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'scan_page_coupon',
  `created_at_utc` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Database fix applied successfully!' AS `Message`;
