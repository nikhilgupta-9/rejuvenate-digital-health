-- ============================================================
-- Migration: doctor_plans marketing fields (admin-editable)
-- Run once on: rej_digital_health_db (MariaDB 11.8)
--   mysql -u root rej_digital_health_db < database/migration_doctor_plans_marketing.sql
--
-- Adds the copy + "estimated patient reach" range that admin/doctor-plans.php
-- edits and doctor-network.php / doctor-plans.php display. Patient numbers are
-- shown as a POTENTIAL range, never a guarantee.
-- ============================================================

ALTER TABLE `doctor_plans`
  ADD COLUMN IF NOT EXISTS `tagline`          VARCHAR(150) DEFAULT NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `features`         TEXT         DEFAULT NULL AFTER `price`,
  ADD COLUMN IF NOT EXISTS `est_patients_min` INT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `est_patients_max` INT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `sort_order`       INT          NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `is_highlighted`   TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `updated_at`       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- Backfill the seeded "Doctor Membership" row so the pages have something to show.
UPDATE `doctor_plans`
SET `tagline`          = COALESCE(NULLIF(`tagline`,''), 'Get listed, get discovered'),
    `features`         = COALESCE(NULLIF(`features`,''),
        'Verified public profile on department pages\nOnline + in-clinic appointment bookings\nDigital prescriptions linked to ABHA\nHPR / NMC verification badge\nDoctor dashboard & patient records'),
    `est_patients_min` = COALESCE(`est_patients_min`, 3),
    `est_patients_max` = COALESCE(`est_patients_max`, 12)
WHERE `id` = 1;
