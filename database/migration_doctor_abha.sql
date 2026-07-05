-- ============================================================
-- Migration: Doctor ABHA/HPR fields + JWT refresh tokens
-- Run once on: u950539402_reju_digi_beta
-- Date: 2026-06-30
-- ============================================================

-- Step 1: Add HPR fields to doctors table
ALTER TABLE `doctors`
  ADD COLUMN `hpr_id`               VARCHAR(20)  DEFAULT NULL COMMENT 'HPR Health Professional ID (XX-XXXX-XXXX-XXXX)' AFTER `doctor_uid`,
  ADD COLUMN `nmc_reg_number`       VARCHAR(50)  DEFAULT NULL COMMENT 'NMC or State Council registration number',
  ADD COLUMN `council_name`         VARCHAR(100) DEFAULT NULL COMMENT 'State Medical Council name',
  ADD COLUMN `year_of_registration` YEAR         DEFAULT NULL COMMENT 'Year of medical council registration',
  ADD COLUMN `qualification_year`   YEAR         DEFAULT NULL COMMENT 'Year of primary degree (MBBS/BDS)',
  ADD COLUMN `hpr_verified`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = HPR verified via ABDM',
  ADD COLUMN `hpr_verified_at`      DATETIME     DEFAULT NULL,
  ADD COLUMN `hpr_txn_id`           VARCHAR(100) DEFAULT NULL COMMENT 'ABDM transaction ID for HPR verification';

-- Step 2: JWT refresh tokens (all roles share this table)
CREATE TABLE IF NOT EXISTS `jwt_refresh_tokens` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(20)     NOT NULL COMMENT 'doctor | patient | admin',
  `entity_id`   INT UNSIGNED    NOT NULL,
  `token_hash`  VARCHAR(64)     NOT NULL COMMENT 'SHA-256 of the raw refresh token',
  `issued_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME        NOT NULL,
  `revoked`     TINYINT(1)      NOT NULL DEFAULT 0,
  `revoked_at`  DATETIME        DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(255)    DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_entity`  (`entity_type`, `entity_id`),
  INDEX `idx_token`   (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Ensure doctors table has JWT secret constant support
-- (JWT_SECRET is defined in connect.php via .env)

-- Step 4: Optional — index on hpr_id for fast lookups
ALTER TABLE `doctors`
  ADD INDEX `idx_hpr_id` (`hpr_id`);

-- Step 5: Doctor–Patient explicit link table
-- Tracks patients directly added to a doctor's panel (not just via appointments)
CREATE TABLE IF NOT EXISTS `doctor_patients` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `doctor_id`   INT UNSIGNED    NOT NULL,
  `patient_id`  INT UNSIGNED    NOT NULL,
  `added_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_via`   ENUM('appointment','manual','abha') NOT NULL DEFAULT 'manual',
  `abha_fetched` TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '1 = profile fetched from ABDM',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_link` (`doctor_id`, `patient_id`),
  INDEX `idx_doctor` (`doctor_id`),
  INDEX `idx_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Doctor account deletion requests (self-service request, admin-reviewed)
CREATE TABLE IF NOT EXISTS `doctor_deletion_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`     INT UNSIGNED NOT NULL,
  `reason`        TEXT         DEFAULT NULL,
  `status`        ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `requested_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`   DATETIME     DEFAULT NULL,
  `reviewed_by`   INT UNSIGNED DEFAULT NULL,
  `notes`         TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_doctor` (`doctor_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
