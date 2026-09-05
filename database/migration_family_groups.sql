-- ============================================================
-- Migration: Family-member grouping for doctor-created ABHA patients
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_family_groups.sql
--
-- Feature: from doctor/add-patient-new-abha.php a doctor can create ABHA
-- accounts for several members of one family in a row, all reachable on the
-- same real phone number.
--
-- ABDM rule: one Aadhaar = one ABHA, so EACH family member is still a
-- separate `users` row created by the SAME existing Aadhaar-OTP flow
-- (send_otp -> verify_otp -> enrolByAadhaar). No new ABDM call.
--
-- Why three columns:
--   `users.mobile` is UNIQUE NOT NULL and `users.email` is UNIQUE NOT NULL.
--   Family members share one real phone, so only ONE row (the "primary")
--   can hold it. Non-primary rows get a SYNTHETIC placeholder in
--   users.mobile (their 14-digit ABHA number, digits only) and in
--   users.email (<abha-number>@abha.invalid) — both guaranteed unique,
--   both impossible to match a real 10-digit login. The real shared phone
--   lives in `primary_contact_mobile` on every member of the group.
--
--   family_group_id        UUID v4 shared by every member of one family
--   primary_contact_mobile the real phone (same value on every member row)
--   is_family_primary      1 on the first member added = the row that owns
--                          the real users.mobile + login for the group
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `family_group_id` CHAR(36) DEFAULT NULL
    COMMENT 'UUID v4 grouping ABHA family members added under one contact mobile'
    AFTER `abha_verified`,
  ADD COLUMN IF NOT EXISTS `primary_contact_mobile` VARCHAR(20) DEFAULT NULL
    COMMENT 'real shared phone for the family group; users.mobile may be a synthetic ABHA-derived placeholder on non-primary members'
    AFTER `family_group_id`,
  ADD COLUMN IF NOT EXISTS `is_family_primary` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = this row owns the real users.mobile + login for the family group (the first member added)'
    AFTER `primary_contact_mobile`;

-- Lookup: "other members of this patient's family"
ALTER TABLE `users`
  ADD INDEX IF NOT EXISTS `idx_family_group` (`family_group_id`);
