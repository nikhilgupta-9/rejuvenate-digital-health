-- ============================================================
-- ADMIN MEDICAL RECORDS UPLOAD
-- Lets the admin panel upload medical records/documents for
-- patients (users) and school members (teachers/students/staff).
-- ============================================================

-- patient_documents.doctor_id was NOT NULL (doctor-only uploads).
-- Admin uploads have no associated doctor, so allow NULL and track
-- who uploaded it.
ALTER TABLE `patient_documents`
    MODIFY COLUMN `doctor_id` INT(11) NULL,
    ADD COLUMN IF NOT EXISTS `uploaded_by_role`     ENUM('doctor','admin') NOT NULL DEFAULT 'doctor' AFTER `doctor_id`,
    ADD COLUMN IF NOT EXISTS `uploaded_by_admin_id`  INT(11) NULL AFTER `uploaded_by_role`;

-- ============================================================
-- TABLE: school_member_documents
-- Medical records/documents uploaded for teachers, students, staff
-- ============================================================
CREATE TABLE IF NOT EXISTS `school_member_documents` (
    `id`                    INT(11)         NOT NULL AUTO_INCREMENT,
    `member_id`             INT(11)         NOT NULL,   -- school_members.id
    `school_id`             INT(11)         NOT NULL,   -- schools.id
    `document_name`         VARCHAR(255)    NOT NULL,
    `document_type`         VARCHAR(100)    DEFAULT 'Other',
    `description`           TEXT            DEFAULT NULL,
    `file_path`             VARCHAR(500)    NOT NULL,
    `file_type`             VARCHAR(50)     DEFAULT NULL,
    `uploaded_by_admin_id`  INT(11)         DEFAULT NULL,
    `uploaded_at`           DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`member_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    INDEX `idx_member_id` (`member_id`),
    INDEX `idx_school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
