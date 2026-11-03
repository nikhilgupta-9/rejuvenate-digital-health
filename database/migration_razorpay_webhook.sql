-- ============================================================
-- RAZORPAY WEBHOOK RECONCILIATION
--
-- Problem: appointments were only ever created by the browser's
-- Razorpay Checkout `handler` callback (see util/appointment-handler.php).
-- If the tab is killed/backgrounded while the user is in a UPI app, or the
-- network drops on that one fetch(), Razorpay has already captured the
-- money but the booking never gets created. There was no server-side
-- reconciliation path.
--
-- Fix: `create-razorpay-order.php` now stores the full booking form
-- payload here, keyed by razorpay_order_id, before Checkout ever opens.
-- A new webhook/razorpay.php endpoint (registered in the Razorpay
-- Dashboard for the `payment.captured` event) uses this payload to create
-- the appointment server-side if the browser-side path never did.
--
-- The UNIQUE key on appointments.razorpay_payment_id is the idempotency
-- guard: whichever path (browser callback or webhook) runs first wins: the
-- second one hits a duplicate-key error on insert and no-ops.
-- ============================================================

CREATE TABLE IF NOT EXISTS `razorpay_pending_bookings` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `razorpay_order_id`  VARCHAR(64) NOT NULL,
  `doctor_id`          INT DEFAULT NULL,
  `amount_paise`       INT UNSIGNED NOT NULL,
  `payload`            TEXT NOT NULL COMMENT 'JSON-encoded booking form data (name/email/phone/department/date/time/doctor_id/...), captured before Checkout opens',
  `status`             ENUM('created','paid','converted','failed','expired') NOT NULL DEFAULT 'created',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_razorpay_order` (`razorpay_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency guard shared by the browser callback and the webhook — a
-- nullable UNIQUE key allows unlimited NULLs (unpaid/free bookings) but
-- blocks a second row from ever reusing the same captured payment.
ALTER TABLE `appointments`
  ADD UNIQUE KEY `uniq_razorpay_payment` (`razorpay_payment_id`);

CREATE TABLE IF NOT EXISTS `razorpay_webhook_log` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event`         VARCHAR(64) NOT NULL,
  `razorpay_order_id`   VARCHAR(64) DEFAULT NULL,
  `razorpay_payment_id` VARCHAR(64) DEFAULT NULL,
  `result`        VARCHAR(255) DEFAULT NULL COMMENT 'e.g. booked / already_exists / pending_not_found / signature_invalid',
  `raw_body`      MEDIUMTEXT DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
