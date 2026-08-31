-- ============================================================
-- Migration: consultation records — report findings + visit-linked attachments
-- Run once on: rej_digital_health_db (MariaDB 11.8)
--   mysql -u root rej_digital_health_db < database/migration_consultation_records.sql
--
-- - prescriptions.report_findings : free-text lab/scan report results the
--   doctor records on doctor/patient-form.php
-- - patient_documents.appointment_id : ties an uploaded report file to a
--   specific visit so it shows inline on the prescription (doctor, admin, user)
-- - patient_documents.document_type : label (Lab Report / Scan / Other) —
--   also fixes a latent filter that user/my-reports.php already references
-- ============================================================

ALTER TABLE `prescriptions`
  ADD COLUMN IF NOT EXISTS `report_findings` TEXT NULL AFTER `radiology`;

ALTER TABLE `patient_documents`
  ADD COLUMN IF NOT EXISTS `appointment_id` INT NULL AFTER `doctor_id`,
  ADD COLUMN IF NOT EXISTS `document_type`  VARCHAR(80) NULL AFTER `document_name`;

-- Index (separate statement so re-runs don't choke if it already exists)
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'patient_documents'
               AND index_name = 'idx_pd_appointment');
SET @sql := IF(@idx = 0,
  'ALTER TABLE `patient_documents` ADD INDEX `idx_pd_appointment` (`appointment_id`)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
