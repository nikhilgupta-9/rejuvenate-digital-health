-- ============================================================
-- Migration: ABHA module — manual link-request tables
-- Run once on: u950539402_reju_digi_beta
-- Date: 2026-08-02
--
-- doctor_patients is already created by migration_doctor_abha.sql.
-- These two tables back the "request link, admin reviews" fallback UI
-- (user/my-abha.php, school/student/abha.php, school/teacher/abha.php,
-- admin/abha-management.php) used before/alongside the live OTP flow
-- in ajax/abdm-api.php. They previously existed only via ad-hoc manual
-- DB changes with no migration file.
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_abha_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `abha_id`      VARCHAR(20)  NOT NULL COMMENT 'Formatted XX-XXXX-XXXX-XXXX',
  `abha_address` VARCHAR(100) DEFAULT NULL,
  `status`       ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `requested_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`  DATETIME     DEFAULT NULL,
  `reviewed_by`  INT UNSIGNED DEFAULT NULL,
  `notes`        TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_user`   (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `abha_link_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`    INT UNSIGNED NOT NULL COMMENT 'school_members.id',
  `school_id`    INT UNSIGNED NOT NULL,
  `abha_id`      VARCHAR(20)  NOT NULL COMMENT 'Formatted XX-XXXX-XXXX-XXXX',
  `abha_address` VARCHAR(100) DEFAULT NULL,
  `status`       ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `requested_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`  DATETIME     DEFAULT NULL,
  `reviewed_by`  INT UNSIGNED DEFAULT NULL,
  `notes`        TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_member` (`member_id`),
  INDEX `idx_school` (`school_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
