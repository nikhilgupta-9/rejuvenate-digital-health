-- ============================================================
-- CONSENT EXPIRY + REVOKE (Phase 5)
--
-- `parent_consent_forms.academic_year` / `expires_at` — Indian academic year,
--   fixed April 1 – March 31. Computed once at submission time from
--   `submitted_at`: a consent submitted in Apr(year)–Mar(year+1) gets
--   academic_year = "<year>-<year+1>" and expires_at = <year+1>-03-31.
--   Soft validity only (per product decision) — an expired consent is not
--   blocked anywhere, it's just shown as "Expired" in admin/parent-consents.php,
--   admin/parent-consent-view.php and school/consents.php so staff know to
--   collect a fresh one; existing health records stay fully accessible.
--
-- `parent_consent_forms.revoked` / `revoked_at` — a parent can revoke their
--   own consent from the same signed per-student link
--   (school/parent-consent.php?ctoken=..., lib/ConsentToken.php) after
--   re-verifying the registered parent_mobile on file with a fresh WhatsApp
--   OTP (util/otp-service.php, role 'parent_consent' — same mechanism as
--   Phase 3, no new OTP infra). Independent of expiry: a consent can be
--   revoked before or after its academic-year expiry.
-- ============================================================

ALTER TABLE `parent_consent_forms`
  ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(9) DEFAULT NULL AFTER `student_pincode`,
  ADD COLUMN IF NOT EXISTS `expires_at` DATE DEFAULT NULL AFTER `academic_year`,
  ADD COLUMN IF NOT EXISTS `revoked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `expires_at`,
  ADD COLUMN IF NOT EXISTS `revoked_at` DATETIME DEFAULT NULL AFTER `revoked`,
  ADD INDEX IF NOT EXISTS `idx_expires_at` (`expires_at`),
  ADD INDEX IF NOT EXISTS `idx_revoked` (`revoked`);

-- Backfill existing rows from submitted_at (Apr 1 - Mar 31 Indian academic year)
UPDATE `parent_consent_forms`
SET
  `academic_year` = CASE
    WHEN MONTH(`submitted_at`) >= 4
      THEN CONCAT(YEAR(`submitted_at`), '-', YEAR(`submitted_at`) + 1)
    ELSE CONCAT(YEAR(`submitted_at`) - 1, '-', YEAR(`submitted_at`))
  END,
  `expires_at` = CASE
    WHEN MONTH(`submitted_at`) >= 4
      THEN CAST(CONCAT(YEAR(`submitted_at`) + 1, '-03-31') AS DATE)
    ELSE CAST(CONCAT(YEAR(`submitted_at`), '-03-31') AS DATE)
  END
WHERE `academic_year` IS NULL;
