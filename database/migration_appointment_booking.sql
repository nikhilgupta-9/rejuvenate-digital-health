-- ============================================================
-- PUBLIC APPOINTMENT BOOKING — ABHA / consent fields
-- Supports the new book-appointment.php flow: captures an
-- optional ABHA number at booking time and records explicit
-- patient consent (ABDM requires consent to be logged for every
-- health data interaction).
-- ============================================================

ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `abha_number`   VARCHAR(17)  DEFAULT NULL AFTER `patient_phone`,
    ADD COLUMN IF NOT EXISTS `consent_given` TINYINT(1)   NOT NULL DEFAULT 0 AFTER `visited_person_name`,
    ADD COLUMN IF NOT EXISTS `consent_at`    DATETIME     DEFAULT NULL AFTER `consent_given`;
