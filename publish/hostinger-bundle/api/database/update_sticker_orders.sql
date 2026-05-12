-- RUN THIS ON YOUR MYSQL DATABASE TO FIX ORDER SAVE FAILED ISSUE
-- This adds the missing columns required by the backend to save Razorpay payment details.

ALTER TABLE `sticker_orders` 
ADD COLUMN `assigned_public_id` VARCHAR(40) NULL AFTER `status`,
ADD COLUMN `razorpay_order_id` VARCHAR(100) NULL AFTER `assigned_public_id`,
ADD COLUMN `razorpay_payment_id` VARCHAR(100) NULL AFTER `razorpay_order_id`,
ADD COLUMN `razorpay_signature` VARCHAR(256) NULL AFTER `razorpay_payment_id`,
MODIFY COLUMN `created_at_utc` DATETIME(3) NOT NULL AFTER `razorpay_signature`;
