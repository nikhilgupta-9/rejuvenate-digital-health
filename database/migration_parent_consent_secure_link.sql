-- ============================================================
-- SECURE PER-STUDENT PARENT CONSENT LINK (Phase 1)
--
-- `school_members.parent_mobile` — the registered parent/guardian mobile
--                                   a per-student consent link/WhatsApp
--                                   message is sent to. Previously absent;
--                                   `school_members.phone` is the
--                                   student's own contact, not a parent's.
-- `parent_consent_forms.verified_via` — 'token' when the submission came
--                                   through an HMAC-signed, per-student
--                                   link (lib/ConsentToken.php) that
--                                   pre-identified the student — the row's
--                                   `member_id` is set directly at submit
--                                   time. 'manual' (default) is today's
--                                   generic-link behavior: parent free-
--                                   types the student, `member_id` stays
--                                   NULL until a doctor/admin confirms it.
--
-- Backs the token-mode split in school/parent-consent.php + the
-- "Send Consent Link" action in school/members/list.php.
-- ============================================================

ALTER TABLE `school_members`
  ADD COLUMN IF NOT EXISTS `parent_mobile` VARCHAR(15) DEFAULT NULL AFTER `phone`;

ALTER TABLE `parent_consent_forms`
  ADD COLUMN IF NOT EXISTS `verified_via` ENUM('token','manual') NOT NULL DEFAULT 'manual' AFTER `source`;
