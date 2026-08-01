-- ============================================================
-- ADMIN — Appointment rejection reason
-- admin/all-appointment.php's reject action writes a reason, but
-- the column never existed, so every rejection crashed with a
-- "Unknown column 'rejection_reason'" SQL error.
-- ============================================================

ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `status`;
