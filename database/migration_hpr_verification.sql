-- ============================================================
-- Migration: HPR ID verification (Aadhaar-link flow)
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_hpr_verification.sql
--
-- Backs lib/HprApi.php — a doctor verifies their EXISTING HPR ID via
-- generateAadhaarLink → checkAadhaarAuthStatus → verifyOTP →
-- checkHpIdAccountExist. One row per link/txn.
--
-- Separate from `hpr_verification_requests` (the manual admin-review queue
-- from migration_doctor_profile_hpr.sql) — that path stays for doctors who
-- can't complete the Aadhaar flow.
-- ============================================================

CREATE TABLE IF NOT EXISTS `hpr_verification_txns` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT(11) NOT NULL,
  `txn_id`     VARCHAR(100) NOT NULL COMMENT 'ABDM HPR transaction id from generateLink',
  `status`     ENUM('pending','authenticated','verified','failed','expired') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL COMMENT 'link valid 5 minutes from creation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_txn` (`txn_id`),
  KEY `idx_doctor` (`doctor_id`),
  KEY `idx_status` (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK as a separate statement (IF NOT EXISTS is only valid on ALTER ... ADD).
ALTER TABLE `hpr_verification_txns`
  ADD CONSTRAINT `fk_hprtxn_doctor` FOREIGN KEY IF NOT EXISTS (`doctor_id`)
    REFERENCES `doctors`(`id`) ON DELETE CASCADE;

-- doctors: how the HPR id was confirmed. `hpr_verified` / `hpr_verified_at`
-- already exist (migration_doctor_abha.sql / migration_doctor_profile_hpr.sql).
ALTER TABLE `doctors`
  ADD COLUMN IF NOT EXISTS `hpr_verification_source` VARCHAR(30) DEFAULT NULL
    COMMENT 'aadhaar_hpr_api | admin_review | NULL';
