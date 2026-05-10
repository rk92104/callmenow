-- qr_stickers: ensure column payment_transaction_id (VARCHAR(120), NULL).
-- - If legacy payment_payer_upi_vpa exists → rename to payment_transaction_id.
-- - Else if payment_transaction_id missing → ADD COLUMN (after activated_at).
-- Safe to run multiple times. Use this on older DBs; new installs already have the column in schema.sql / full_install_mysql.sql.

DELIMITER $$

DROP PROCEDURE IF EXISTS `_ensure_qr_stickers_payment_transaction_id`$$

CREATE PROCEDURE `_ensure_qr_stickers_payment_transaction_id`()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'qr_stickers'
      AND COLUMN_NAME = 'payment_payer_upi_vpa'
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'qr_stickers'
      AND COLUMN_NAME = 'payment_transaction_id'
  ) THEN
    ALTER TABLE `qr_stickers`
      CHANGE COLUMN `payment_payer_upi_vpa` `payment_transaction_id` VARCHAR(120) NULL
      COMMENT 'Razorpay pay_ id or offline ref';
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'qr_stickers'
      AND COLUMN_NAME = 'payment_transaction_id'
  ) THEN
    ALTER TABLE `qr_stickers`
      ADD COLUMN `payment_transaction_id` VARCHAR(120) NULL
      COMMENT 'Razorpay pay_ id or offline ref'
      AFTER `activated_at`;
  END IF;
END$$

DELIMITER ;

CALL `_ensure_qr_stickers_payment_transaction_id`();

DROP PROCEDURE IF EXISTS `_ensure_qr_stickers_payment_transaction_id`;
