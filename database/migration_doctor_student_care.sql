-- ============================================================
-- DOCTOR PANEL — STUDENT PRESCRIPTIONS & MEDICAL REPORTS
-- Lets a doctor write prescriptions for school members
-- (students/teachers/staff) and upload medical reports for them.
-- ============================================================

-- Track doctor-originated uploads on the existing school_member_documents table
-- (admin already uploads into this table; this adds the doctor side).
ALTER TABLE `school_member_documents`
    ADD COLUMN IF NOT EXISTS `uploaded_by_role`     ENUM('admin','doctor') NOT NULL DEFAULT 'admin' AFTER `uploaded_by_admin_id`,
    ADD COLUMN IF NOT EXISTS `uploaded_by_doctor_id` INT(11) NULL AFTER `uploaded_by_role`;

-- ============================================================
-- TABLE: school_member_prescriptions
-- Structured prescriptions written by a doctor for a school member
-- ============================================================
CREATE TABLE IF NOT EXISTS `school_member_prescriptions` (
    `id`                INT(11)         NOT NULL AUTO_INCREMENT,
    `member_id`         INT(11)         NOT NULL,   -- school_members.id
    `school_id`         INT(11)         NOT NULL,   -- schools.id
    `doctor_id`         INT(11)         NOT NULL,   -- doctors.id
    `diagnosis`         VARCHAR(500)    DEFAULT NULL,
    `symptoms`          TEXT            DEFAULT NULL,
    `prescription_text` TEXT            NOT NULL,   -- Rx: medicines, dosage, instructions
    `advice`            TEXT            DEFAULT NULL,
    `follow_up_date`    DATE            DEFAULT NULL,
    `created_at`        DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`member_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    INDEX `idx_member_id` (`member_id`),
    INDEX `idx_doctor_id` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
