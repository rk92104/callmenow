-- Prefer ensure_qr_stickers_payment_transaction_id.sql (idempotent: rename or add).
--
-- If you already added `payment_payer_upi_vpa`, rename it to match PHP/API code:
ALTER TABLE `qr_stickers`
  CHANGE COLUMN `payment_payer_upi_vpa` `payment_transaction_id` VARCHAR(120) NULL COMMENT 'Razorpay pay_ id or offline ref';

-- If the column does not exist yet, use instead:
-- ALTER TABLE `qr_stickers` ADD COLUMN `payment_transaction_id` VARCHAR(120) NULL COMMENT 'Razorpay pay_ id or offline ref' AFTER `activated_at`;
