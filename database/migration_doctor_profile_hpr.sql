-- ============================================================
-- Migration: doctor profile + HPR verification module
-- Run once on: rej_digital_health_db (MariaDB 11.8)
--   mysql -u root rej_digital_health_db < database/migration_doctor_profile_hpr.sql
--
-- - doctors.hfr_id            : Health Facility Registry ID (ABDM, for clinic-based doctors)
-- - doctors.notify_*          : notification channel preferences for the Settings page
-- - hpr_verification_requests : doctor submits HPR/NMC details -> admin reviews -> sets doctors.hpr_verified
-- ============================================================

ALTER TABLE `doctors`
  ADD COLUMN IF NOT EXISTS `hfr_id`         VARCHAR(20) DEFAULT NULL AFTER `hpr_id`,
  ADD COLUMN IF NOT EXISTS `notify_email`   TINYINT(1)  NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `notify_whatsapp` TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `hpr_requested_at` DATETIME  DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `hpr_verification_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`       INT UNSIGNED NOT NULL,
  `hpr_id`          VARCHAR(20)  DEFAULT NULL,
  `hfr_id`          VARCHAR(20)  DEFAULT NULL,
  `nmc_reg_number`  VARCHAR(50)  DEFAULT NULL,
  `council_name`    VARCHAR(100) DEFAULT NULL,
  `year_of_registration` YEAR    DEFAULT NULL,
  `doctor_note`     TEXT         DEFAULT NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`     DATETIME     DEFAULT NULL,
  `reviewed_by`     INT UNSIGNED DEFAULT NULL,
  `review_note`     TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_doctor` (`doctor_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
