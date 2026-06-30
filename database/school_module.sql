-- ============================================================
-- SCHOOL MODULE - DATABASE TABLES
-- Project: Rejuvenate Digital Health
-- Phase: 1 - School Module
-- Date: 2026-05-14
-- ============================================================

-- ============================================================
-- TABLE 1: schools
-- School ki basic info store hogi
-- Super Admin isko approve/reject karega
-- ============================================================
CREATE TABLE IF NOT EXISTS `schools` (
    `id`                    INT(11)         NOT NULL AUTO_INCREMENT,
    `school_uid`            VARCHAR(20)     NOT NULL UNIQUE,            -- e.g. SCH-0001
    `school_name`           VARCHAR(255)    NOT NULL,
    `email`                 VARCHAR(150)    NOT NULL UNIQUE,
    `phone`                 VARCHAR(15)     NOT NULL,
    `address`               TEXT            NOT NULL,
    `city`                  VARCHAR(100)    NOT NULL,
    `state`                 VARCHAR(100)    NOT NULL,
    `pincode`               VARCHAR(10)     NOT NULL,
    `board`                 ENUM('CBSE','ICSE','State Board','Other') DEFAULT 'CBSE',
    `school_type`           ENUM('Government','Private','Semi-Government') DEFAULT 'Private',
    `principal_name`        VARCHAR(150)    NOT NULL,
    `logo`                  VARCHAR(255)    DEFAULT NULL,
    `registration_number`   VARCHAR(100)    DEFAULT NULL,               -- School govt reg no
    `status`                ENUM('Pending','Active','Inactive','Rejected') DEFAULT 'Pending',
    `approved_by`           INT(11)         DEFAULT NULL,               -- admin_user id
    `approved_at`           DATETIME        DEFAULT NULL,
    `rejection_reason`      TEXT            DEFAULT NULL,
    `created_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_school_uid` (`school_uid`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE 2: school_users
-- School admin ka login account
-- Ek school ke multiple admin ho sakte hain
-- ============================================================
CREATE TABLE IF NOT EXISTS `school_users` (
    `id`                    INT(11)         NOT NULL AUTO_INCREMENT,
    `school_id`             INT(11)         NOT NULL,                   -- schools.id
    `name`                  VARCHAR(150)    NOT NULL,
    `email`                 VARCHAR(150)    NOT NULL UNIQUE,
    `phone`                 VARCHAR(15)     NOT NULL,
    `password`              VARCHAR(255)    NOT NULL,
    `role`                  ENUM('school_admin','school_staff') DEFAULT 'school_admin',
    `profile_pic`           VARCHAR(255)    DEFAULT NULL,
    `status`                ENUM('Active','Inactive','Suspended') DEFAULT 'Active',
    `otp_code`              VARCHAR(10)     DEFAULT NULL,
    `otp_expiry`            DATETIME        DEFAULT NULL,
    `reset_token`           VARCHAR(255)    DEFAULT NULL,
    `reset_token_expiry`    DATETIME        DEFAULT NULL,
    `last_login`            DATETIME        DEFAULT NULL,
    `login_attempts`        TINYINT(1)      DEFAULT 0,
    `is_locked`             TINYINT(1)      DEFAULT 0,
    `locked_until`          DATETIME        DEFAULT NULL,
    `created_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    INDEX `idx_school_id` (`school_id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE 3: school_members
-- School ke saare members: Teacher, Student, Staff
-- `type` field se alag honge
-- ============================================================
CREATE TABLE IF NOT EXISTS `school_members` (
    `id`                    INT(11)         NOT NULL AUTO_INCREMENT,
    `member_uid`            VARCHAR(20)     NOT NULL UNIQUE,            -- e.g. TCH-0001, STU-0001, STF-0001
    `school_id`             INT(11)         NOT NULL,                   -- schools.id
    `type`                  ENUM('Teacher','Student','Staff')  NOT NULL,
    `name`                  VARCHAR(150)    NOT NULL,
    `email`                 VARCHAR(150)    DEFAULT NULL,
    `phone`                 VARCHAR(15)     DEFAULT NULL,
    `dob`                   DATE            DEFAULT NULL,
    `gender`                ENUM('Male','Female','Other') DEFAULT NULL,
    `blood_group`           VARCHAR(10)     DEFAULT NULL,
    `aadhar_number`         VARCHAR(20)     DEFAULT NULL,
    `address`               VARCHAR(255)    DEFAULT NULL,
    `profile_pic`           VARCHAR(255)    DEFAULT NULL,
    -- Student specific
    `class`                 VARCHAR(20)     DEFAULT NULL,               -- e.g. 10-A (only for Students)
    `section`               VARCHAR(10)     DEFAULT NULL,               -- e.g. A, B (only for Students)
    `roll_number`           VARCHAR(20)     DEFAULT NULL,               -- only for Students
    `admission_number`      VARCHAR(50)     DEFAULT NULL,               -- only for Students
    -- Teacher specific
    `employee_id`           VARCHAR(50)     DEFAULT NULL,               -- only for Teacher/Staff
    `designation`           VARCHAR(100)    DEFAULT NULL,               -- e.g. Math Teacher, Principal
    `assigned_class`        VARCHAR(50)     DEFAULT NULL,               -- Teacher ka assigned class
    `password`              VARCHAR(255)    DEFAULT NULL,               -- hashed login password
    -- ABHA
    `abha_id`               VARCHAR(50)     DEFAULT NULL,               -- ABHA Health ID
    `abha_address`          VARCHAR(100)    DEFAULT NULL,               -- e.g. name@abdm
    `abha_linked`           TINYINT(1)      DEFAULT 0,
    `abha_linked_at`        DATETIME        DEFAULT NULL,
    `abha_verified`         TINYINT(1)      DEFAULT 0,                 -- verified via ABDM
    -- Auth / login
    `last_login`            DATETIME        DEFAULT NULL,
    `login_attempts`        TINYINT(3)      DEFAULT 0,
    `is_locked`             TINYINT(1)      DEFAULT 0,
    `locked_until`          DATETIME        DEFAULT NULL,
    -- Status
    `status`                ENUM('Active','Inactive','Pending') DEFAULT 'Active',
    `added_by`              INT(11)         DEFAULT NULL,               -- school_users.id
    `created_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    INDEX `idx_school_id` (`school_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_member_uid` (`member_uid`),
    INDEX `idx_abha_id` (`abha_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE 4: member_health_profiles
-- Har member (teacher/student/staff) ki health info
-- ============================================================
CREATE TABLE IF NOT EXISTS `member_health_profiles` (
    `id`                    INT(11)         NOT NULL AUTO_INCREMENT,
    `member_id`             INT(11)         NOT NULL,                   -- school_members.id
    `school_id`             INT(11)         NOT NULL,                   -- schools.id
    -- Basic Health Info
    `height_cm`             DECIMAL(5,2)    DEFAULT NULL,               -- in cm
    `weight_kg`             DECIMAL(5,2)    DEFAULT NULL,               -- in kg
    `bmi`                   DECIMAL(4,2)    DEFAULT NULL,               -- auto calculated
    `blood_group`           VARCHAR(10)     DEFAULT NULL,
    `blood_pressure`        VARCHAR(20)     DEFAULT NULL,               -- e.g. 120/80
    `pulse_rate`            INT(11)         DEFAULT NULL,               -- per minute
    `vision_left`           VARCHAR(20)     DEFAULT NULL,
    `vision_right`          VARCHAR(20)     DEFAULT NULL,
    -- Medical History
    `known_allergies`       TEXT            DEFAULT NULL,
    `chronic_conditions`    TEXT            DEFAULT NULL,               -- e.g. Asthma, Diabetes
    `current_medications`   TEXT            DEFAULT NULL,
    `past_surgeries`        TEXT            DEFAULT NULL,
    `disability`            TEXT            DEFAULT NULL,
    -- Vaccination
    `vaccination_details`   TEXT            DEFAULT NULL,               -- JSON or text
    `is_vaccinated`         TINYINT(1)      DEFAULT 0,
    -- Emergency Contact
    `emergency_contact_name`    VARCHAR(150) DEFAULT NULL,
    `emergency_contact_phone`   VARCHAR(15)  DEFAULT NULL,
    `emergency_contact_relation` VARCHAR(50) DEFAULT NULL,
    -- Annual Health Checkup
    `last_checkup_date`     DATE            DEFAULT NULL,
    `next_checkup_date`     DATE            DEFAULT NULL,
    `checkup_notes`         TEXT            DEFAULT NULL,
    -- Insurance
    `insurance_provider`    VARCHAR(150)    DEFAULT NULL,
    `insurance_number`      VARCHAR(100)    DEFAULT NULL,
    -- Documents
    `health_documents`      TEXT            DEFAULT NULL,               -- JSON: file paths
    -- Updated By
    `last_updated_by`       INT(11)         DEFAULT NULL,               -- school_users.id
    `last_updated_role`     ENUM('school_admin','teacher','super_admin') DEFAULT 'school_admin',
    `created_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`member_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    INDEX `idx_member_id` (`member_id`),
    INDEX `idx_school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE 5: teacher_student_assignments
-- Kis teacher ko konse students assign hain (partial access)
-- ============================================================
CREATE TABLE IF NOT EXISTS `teacher_student_assignments` (
    `id`            INT(11) NOT NULL AUTO_INCREMENT,
    `teacher_id`    INT(11) NOT NULL,   -- school_members.id (type=Teacher)
    `student_id`    INT(11) NOT NULL,   -- school_members.id (type=Student)
    `school_id`     INT(11) NOT NULL,
    `assigned_by`   INT(11) DEFAULT NULL, -- school_users.id
    `assigned_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_assignment` (`teacher_id`, `student_id`),
    FOREIGN KEY (`teacher_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher_id` (`teacher_id`),
    INDEX `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- AUTO-INCREMENT for school_uid / member_uid
-- Trigger se UID auto generate hoga
-- ============================================================

DELIMITER $$

CREATE TRIGGER IF NOT EXISTS `before_insert_school`
BEFORE INSERT ON `schools`
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SELECT AUTO_INCREMENT INTO next_id
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools';
    SET NEW.school_uid = CONCAT('SCH-', LPAD(next_id, 4, '0'));
END$$

CREATE TRIGGER IF NOT EXISTS `before_insert_school_member`
BEFORE INSERT ON `school_members`
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    DECLARE prefix VARCHAR(5);
    SELECT AUTO_INCREMENT INTO next_id
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_members';
    IF NEW.type = 'Teacher' THEN SET prefix = 'TCH-';
    ELSEIF NEW.type = 'Student' THEN SET prefix = 'STU-';
    ELSE SET prefix = 'STF-';
    END IF;
    SET NEW.member_uid = CONCAT(prefix, LPAD(next_id, 4, '0'));
END$$

DELIMITER ;


-- ============================================================
-- MIGRATION: Add missing columns to school_members
-- Run this if the table already exists without these columns
-- ============================================================

ALTER TABLE `school_members`
    ADD COLUMN IF NOT EXISTS `abha_verified`     TINYINT(1)  DEFAULT 0     AFTER `abha_linked_at`,
    ADD COLUMN IF NOT EXISTS `last_login`        DATETIME    DEFAULT NULL  AFTER `abha_verified`,
    ADD COLUMN IF NOT EXISTS `login_attempts`    TINYINT(3)  DEFAULT 0     AFTER `last_login`,
    ADD COLUMN IF NOT EXISTS `is_locked`         TINYINT(1)  DEFAULT 0     AFTER `login_attempts`,
    ADD COLUMN IF NOT EXISTS `locked_until`      DATETIME    DEFAULT NULL  AFTER `is_locked`;

-- Extend status enum to include 'Pending' (for self-registered students)
-- Note: ALTER TABLE MODIFY COLUMN is safer than changing ADD COLUMN for ENUMs
ALTER TABLE `school_members`
    MODIFY COLUMN `status` ENUM('Active','Inactive','Pending') DEFAULT 'Active';
