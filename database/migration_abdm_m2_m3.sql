-- ============================================================
-- Migration: ABDM M2 Phase B & M3 Scan & Share
-- Run once on: rej_digital_health_db (MariaDB 10.4)
-- ============================================================

-- 1. Extend abha_hi_requests status to include 'delivered'
ALTER TABLE `abha_hi_requests`
  MODIFY COLUMN `status` ENUM('pending','acknowledged','ready_for_push','delivered','failed') NOT NULL DEFAULT 'pending';

-- 2. Scan & Share tokens table for M3 counter check-in
CREATE TABLE IF NOT EXISTS `abdm_scan_share_tokens` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_number` VARCHAR(32) NOT NULL COMMENT 'Display token e.g. RJ-101',
  `hip_id`       VARCHAR(100) NOT NULL COMMENT 'HFR Facility ID',
  `context_type` VARCHAR(50) NOT NULL DEFAULT 'OPD',
  `abha_number`  VARCHAR(20) DEFAULT NULL,
  `abha_address` VARCHAR(100) DEFAULT NULL,
  `patient_name` VARCHAR(150) NOT NULL,
  `gender`       VARCHAR(20) DEFAULT NULL,
  `dob`          VARCHAR(20) DEFAULT NULL,
  `phone`        VARCHAR(20) DEFAULT NULL,
  `address`      TEXT DEFAULT NULL,
  `status`       ENUM('waiting','in_consultation','completed','cancelled') NOT NULL DEFAULT 'waiting',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_abha_address` (`abha_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
