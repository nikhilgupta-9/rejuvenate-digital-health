-- ============================================================
-- PARENT IDENTITY STRENGTHENING (Phase 3)
--
-- `parent_consent_forms.identity_check` — result of comparing the parent's
--   declared Aadhaar-linked mobile (`parent_aadhar_mobile`) against the
--   school's on-record `school_members.parent_mobile` for that student.
--   Only computable in token mode (database/migration_parent_consent_secure_link.sql)
--   where the student — and so the school's own parent_mobile — is known
--   server-side, and only when the parent actually filled the Aadhaar-linked
--   mobile field. A mismatch never blocks submission (school records go
--   stale) — it just flags the row for admin/parent-consents.php review.
--
-- `parent_consent_forms.mobile_otp_verified` / `mobile_otp_verified_at` —
--   the parent's own mobile (`parent_mobile`) is now WhatsApp-OTP verified
--   before submission, reusing the existing registration OTP infrastructure
--   (util/otp-service.php + registration_otps, role 'parent_consent') — the
--   same mechanism as student/teacher/doctor self-registration. Every row
--   from here on is verified=1; the column exists mainly to distinguish new
--   submissions from rows recorded before this migration.
-- ============================================================

ALTER TABLE `parent_consent_forms`
  ADD COLUMN IF NOT EXISTS `identity_check` ENUM('not_applicable','matched','mismatched') NOT NULL DEFAULT 'not_applicable' AFTER `parent_aadhar_mobile`,
  ADD COLUMN IF NOT EXISTS `mobile_otp_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `identity_check`,
  ADD COLUMN IF NOT EXISTS `mobile_otp_verified_at` DATETIME DEFAULT NULL AFTER `mobile_otp_verified`,
  ADD INDEX IF NOT EXISTS `idx_identity_check` (`identity_check`);

-- registration_otps.role now also carries 'parent_consent' (util/otp-service.php OTP_ALLOWED_ROLES)
ALTER TABLE `registration_otps`
  MODIFY COLUMN `role` VARCHAR(20) NOT NULL COMMENT 'patient|doctor|student|teacher|school_admin|parent_consent';
