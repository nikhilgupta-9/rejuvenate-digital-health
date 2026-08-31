-- ============================================================
-- Migration: WhatsApp + email OTP verification for registration
-- Run once on: rej_digital_health_db (MariaDB 11.8)
--   mysql -u root rej_digital_health_db < database/migration_whatsapp_otp.sql
--
-- Backs util/otp-service.php — a single OTP store keyed by (role, mobile)
-- used before an account/record exists, for every registration entry point:
--   * patient self-signup            (signup.php)
--   * doctor self-signup             (doctor-signup.php)
--   * student / teacher registration (student-register.php, teacher-register.php)
--   * school org signup              (school-register.php)
--   * doctor adds a patient          (doctor/add-patient-manual.php)
--   * super admin adds a patient     (admin/add-customer.php)
--
-- Login OTP keeps using the existing login_otps table (now also sent over
-- WhatsApp) — it is not touched here.
-- ============================================================

CREATE TABLE IF NOT EXISTS `registration_otps` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`           VARCHAR(20)     NOT NULL COMMENT 'patient|doctor|student|teacher|school_admin',
  `mobile`         VARCHAR(15)     NOT NULL COMMENT 'digits only, as entered (10-digit)',
  `email`          VARCHAR(150)    DEFAULT NULL,
  `otp_hash`       CHAR(64)        NOT NULL COMMENT 'sha256(otp | mobile)',
  `channel`        VARCHAR(20)     NOT NULL DEFAULT 'wa+email',
  `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `resend_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `verified`       TINYINT(1)      NOT NULL DEFAULT 0,
  `verified_at`    DATETIME        DEFAULT NULL,
  `verify_token`   CHAR(32)        DEFAULT NULL COMMENT 'short-lived proof passed to the final submit',
  `token_expiry`   DATETIME        DEFAULT NULL,
  `token_consumed` TINYINT(1)      NOT NULL DEFAULT 0,
  `otp_expiry`     DATETIME        NOT NULL,
  `last_sent_at`   DATETIME        DEFAULT NULL COMMENT 'for the 60s resend cooldown',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_mobile` (`role`, `mobile`),
  INDEX `idx_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patients: users.mobile_verified already exists; add the timestamp column
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `mobile_verified_at` DATETIME DEFAULT NULL AFTER `mobile_verified`;

-- Doctors: track WhatsApp mobile verification
ALTER TABLE `doctors`
  ADD COLUMN IF NOT EXISTS `mobile_verified`    TINYINT(1) NOT NULL DEFAULT 0 AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `mobile_verified_at` DATETIME   DEFAULT NULL       AFTER `mobile_verified`;

-- School members (students / teachers)
ALTER TABLE `school_members`
  ADD COLUMN IF NOT EXISTS `mobile_verified`    TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `mobile_verified_at` DATETIME   DEFAULT NULL;

-- School org admin accounts
ALTER TABLE `school_users`
  ADD COLUMN IF NOT EXISTS `mobile_verified`    TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `mobile_verified_at` DATETIME   DEFAULT NULL;
