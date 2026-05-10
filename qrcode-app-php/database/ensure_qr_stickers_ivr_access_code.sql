-- qr_stickers: optional 6-digit IVR code (Exotel Gather → Connect lookup). Idempotent for Hostinger / phpMyAdmin.
DELIMITER $$

DROP PROCEDURE IF EXISTS `_ensure_qr_stickers_ivr_access_code`$$

CREATE PROCEDURE `_ensure_qr_stickers_ivr_access_code`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'qr_stickers'
      AND COLUMN_NAME = 'ivr_access_code'
  ) THEN
    ALTER TABLE `qr_stickers`
      ADD COLUMN `ivr_access_code` VARCHAR(6) NULL COMMENT '6-digit keypad code for Exotel IVR' AFTER `public_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'qr_stickers'
      AND INDEX_NAME = 'uq_qr_stickers_ivr_access_code'
  ) THEN
    ALTER TABLE `qr_stickers`
      ADD UNIQUE KEY `uq_qr_stickers_ivr_access_code` (`ivr_access_code`);
  END IF;
END$$

DELIMITER ;

CALL `_ensure_qr_stickers_ivr_access_code`();

DROP PROCEDURE IF EXISTS `_ensure_qr_stickers_ivr_access_code`;
