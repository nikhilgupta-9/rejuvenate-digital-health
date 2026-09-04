-- ============================================================
-- Migration: prescriptions (consultation records)
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_prescriptions.sql
--
-- Base table for a saved consultation / e-prescription. Previously created
-- at runtime by doctor/patient-form.php — moved here so a fresh database has
-- the table before any request hits it.
--
-- Column additions that already had their own migrations still apply on top:
--   migration_consultation_records.sql  -> report_findings
-- ============================================================

CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- FK columns are signed INT(11) to match appointments.id / doctors.id / users.id
  -- (see migration_core_foreign_keys.sql).
  `appointment_id`   INT(11) NOT NULL,
  `doctor_id`        INT(11) NOT NULL,
  `patient_id`       INT(11) NOT NULL,
  `care_context_ref` VARCHAR(120) NOT NULL,
  `visit_date`       DATE         NOT NULL,
  `chief_complaints` TEXT         DEFAULT NULL,
  `vitals`           LONGTEXT     DEFAULT NULL CHECK (json_valid(`vitals`)),
  `examination`      TEXT         DEFAULT NULL,
  `diagnosis`        TEXT         DEFAULT NULL,
  `icd_codes`        VARCHAR(500) DEFAULT NULL,
  `medications`      LONGTEXT     DEFAULT NULL CHECK (json_valid(`medications`)),
  `lab_tests`        TEXT         DEFAULT NULL,
  `radiology`        TEXT         DEFAULT NULL,
  `report_findings`  TEXT         DEFAULT NULL,
  `advice`           TEXT         DEFAULT NULL,
  `follow_up_date`   DATE         DEFAULT NULL,
  `follow_up_notes`  TEXT         DEFAULT NULL,
  `abha_number`      VARCHAR(20)  DEFAULT NULL COMMENT 'per-visit ABHA snapshot (identity lives in abha_accounts)',
  `hpr_id`           VARCHAR(50)  DEFAULT NULL,
  `status`           ENUM('draft','final') NOT NULL DEFAULT 'draft',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment` (`appointment_id`),
  KEY `idx_doctor`  (`doctor_id`),
  KEY `idx_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
