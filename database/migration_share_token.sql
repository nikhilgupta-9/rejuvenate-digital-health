-- ============================================================
-- MIGRATION: Digital Health ID Card (QR share link)
-- Adds a random, unguessable token per school_member used to
-- build a public "scan to view health card" QR link.
-- ============================================================

ALTER TABLE `school_members`
    ADD COLUMN IF NOT EXISTS `share_token` VARCHAR(64) DEFAULT NULL AFTER `member_uid`,
    ADD UNIQUE INDEX IF NOT EXISTS `idx_share_token` (`share_token`);
