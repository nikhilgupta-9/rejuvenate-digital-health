-- ============================================================
-- DOCTOR PANEL — EDIT STUDENT HEALTH PROFILE
-- Allows a doctor to update member_health_profiles, so the
-- audit column last_updated_role needs to accept 'doctor' too.
-- ============================================================

ALTER TABLE `member_health_profiles`
    MODIFY COLUMN `last_updated_role` ENUM('school_admin','teacher','super_admin','doctor') DEFAULT 'school_admin';
