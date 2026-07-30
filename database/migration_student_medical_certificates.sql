-- ============================================================
-- DOCTOR PANEL — MEDICAL / LEAVE CERTIFICATES
-- Lets a doctor issue a formatted, printable medical (leave)
-- certificate for a school member (student/teacher/staff),
-- separate from free-form document uploads.
-- ============================================================

CREATE TABLE IF NOT EXISTS `school_member_certificates` (
    `id`                INT(11)      NOT NULL AUTO_INCREMENT,
    `member_id`         INT(11)      NOT NULL,   -- school_members.id
    `school_id`         INT(11)      NOT NULL,   -- schools.id
    `doctor_id`         INT(11)      NOT NULL,   -- doctors.id
    `certificate_type`  VARCHAR(50)  NOT NULL DEFAULT 'Medical Leave Certificate',
    `reason`            TEXT         NOT NULL,   -- diagnosis / reason for leave or certificate
    `leave_from`        DATE         DEFAULT NULL,
    `leave_to`          DATE         DEFAULT NULL,
    `fit_to_join_date`  DATE         DEFAULT NULL,
    `remarks`           TEXT         DEFAULT NULL,
    `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`member_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    INDEX `idx_member_id` (`member_id`),
    INDEX `idx_doctor_id` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
