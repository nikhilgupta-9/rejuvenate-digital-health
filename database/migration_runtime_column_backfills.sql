-- ============================================================
-- Migration: column additions that were being ALTERed in at runtime
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_runtime_column_backfills.sql
--
-- Collected from:
--   doctor/earnings.php      -> doctor_bank_accounts.branch_name / account_type
--   doctor/patient-form.php  -> school_member_prescriptions.vitals
-- ============================================================

ALTER TABLE `doctor_bank_accounts`
  ADD COLUMN IF NOT EXISTS `branch_name`  VARCHAR(150) DEFAULT NULL AFTER `bank_name`,
  ADD COLUMN IF NOT EXISTS `account_type` VARCHAR(20)  DEFAULT NULL AFTER `branch_name`;

ALTER TABLE `school_member_prescriptions`
  ADD COLUMN IF NOT EXISTS `vitals` LONGTEXT DEFAULT NULL COMMENT 'JSON vitals blob' AFTER `symptoms`;
