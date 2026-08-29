-- ============================================================
-- APPOINTMENT PAYMENT (Razorpay)
-- Adds payment tracking columns to the existing `appointments`
-- table — no new table needed. A booking with a doctor whose
-- consultation_fee is NULL/0 is marked 'not_required' and skips
-- the Razorpay checkout entirely (see util/appointment-handler.php).
-- ============================================================

ALTER TABLE `appointments`
  ADD COLUMN `payment_status` ENUM('not_required','pending','paid','failed','refunded') NOT NULL DEFAULT 'not_required' AFTER `meeting_completed_at`,
  ADD COLUMN `payment_amount` DECIMAL(10,2) DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `razorpay_order_id` VARCHAR(64) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN `razorpay_payment_id` VARCHAR(64) DEFAULT NULL AFTER `razorpay_order_id`,
  ADD COLUMN `razorpay_signature` VARCHAR(128) DEFAULT NULL AFTER `razorpay_payment_id`,
  ADD COLUMN `paid_at` DATETIME DEFAULT NULL AFTER `razorpay_signature`,
  ADD INDEX `idx_razorpay_order` (`razorpay_order_id`);
