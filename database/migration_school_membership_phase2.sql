-- ============================================================
-- Migration: school membership + parent/school payment model (Phase 2)
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_school_membership_phase2.sql
--
-- Adds:
--   school_health_memberships  - the 12-month paid membership record.
--                                 One row per purchase/renewal (history
--                                 kept). Parent/student pays the platform
--                                 directly; the school never handles the
--                                 payment, it only earns a commission
--                                 (snapshot at purchase time, since the
--                                 admin-configurable rate can change later
--                                 without retroactively touching old rows).
--   school_doctor_assignments  - formal school<->doctor authorization,
--                                 modelled on doctor_patients. Used to
--                                 scope doctor/api/school-lookup-search.php
--                                 so a doctor can only search students at
--                                 schools they are actually assigned to.
--   platform_settings          - generic admin-editable key/value table,
--                                 same shape as telemedicine_settings.
--                                 Seeded with school_commission_percent
--                                 and membership_refund_window_days.
--   school_booking_holds       - a student-initiated booking request that
--                                 is NOT yet a real appointment. Converts
--                                 into an appointments row only once the
--                                 parent confirms consent and pays, via
--                                 school/parent-booking-approval.php.
--
-- Plus additive columns:
--   school_health_plans.applicable_classes - optional class-list filter
--     alongside the existing age_min/age_max band (NULL = all classes).
--   parent_consent_forms.membership_id - links a consent submission to
--     the membership it paid for, when the existing parent-consent plan
--     purchase flow is used.
--   appointments.school_member_id / .membership_id / .booking_source -
--     optional attribution so a school student's OPD booking still flows
--     through the existing appointments/insert_appointment() pipeline
--     untouched, just tagged with who it's really for.
-- ============================================================

-- ── 1. school_health_memberships ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_health_memberships` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`             INT(11) NOT NULL,
  `school_id`             INT(11) NOT NULL,
  `plan_id`               INT(10) UNSIGNED DEFAULT NULL,
  `plan_name`             VARCHAR(120) DEFAULT NULL,
  `plan_price`            DECIMAL(10,2) DEFAULT NULL,
  `amount_paid`           DECIMAL(10,2) DEFAULT NULL,
  `start_date`            DATE DEFAULT NULL,
  `end_date`              DATE DEFAULT NULL,
  `status`                ENUM('pending_payment','active','expired','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  `razorpay_order_id`     VARCHAR(64) DEFAULT NULL,
  `razorpay_payment_id`   VARCHAR(64) DEFAULT NULL,
  `paid_at`               DATETIME DEFAULT NULL,
  `commission_percent`    DECIMAL(5,2) DEFAULT NULL,
  `commission_amount`     DECIMAL(10,2) DEFAULT NULL,
  `commission_status`     ENUM('held','payable','paid_out') NOT NULL DEFAULT 'held',
  `commission_release_at` DATETIME DEFAULT NULL,
  `payout_ref`            VARCHAR(100) DEFAULT NULL,
  `paid_out_at`           DATETIME DEFAULT NULL,
  `refund_eligible_until` DATETIME DEFAULT NULL,
  `razorpay_refund_id`    VARCHAR(64) DEFAULT NULL,
  `refunded_at`           DATETIME DEFAULT NULL,
  `refund_amount`         DECIMAL(10,2) DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shm_member`      (`member_id`),
  KEY `idx_shm_school`      (`school_id`),
  KEY `idx_shm_status`      (`status`),
  KEY `idx_shm_commission`  (`commission_status`),
  KEY `idx_shm_order`       (`razorpay_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 2. school_doctor_assignments ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_doctor_assignments` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id`    INT(11) NOT NULL,
  `doctor_id`    INT(11) NOT NULL,
  `assigned_by`  INT(11) DEFAULT NULL,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sda_pair` (`school_id`, `doctor_id`),
  KEY `idx_sda_doctor` (`doctor_id`),
  KEY `idx_sda_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 3. platform_settings ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key`   VARCHAR(50) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`    INT(11) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
  ('school_commission_percent',       '10'),
  ('membership_refund_window_days',   '7');

-- ── 4. school_booking_holds ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_booking_holds` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`             INT(11) NOT NULL,
  `school_id`             INT(11) NOT NULL,
  `doctor_id`             INT(11) NOT NULL,
  `department`            VARCHAR(120) DEFAULT NULL,
  `appointment_date`      DATE NOT NULL,
  `appointment_time`      TIME NOT NULL,
  `notes`                 TEXT DEFAULT NULL,
  `status`                ENUM('awaiting_parent','approved','rejected','expired') NOT NULL DEFAULT 'awaiting_parent',
  `requested_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`            DATETIME NOT NULL,
  `resolved_at`           DATETIME DEFAULT NULL,
  `resulting_appointment_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sbh_member` (`member_id`),
  KEY `idx_sbh_school` (`school_id`),
  KEY `idx_sbh_doctor` (`doctor_id`),
  KEY `idx_sbh_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 5. Additive columns ──────────────────────────────────────────────
ALTER TABLE `school_health_plans`
  ADD COLUMN IF NOT EXISTS `applicable_classes` JSON DEFAULT NULL AFTER `age_max`;

ALTER TABLE `parent_consent_forms`
  ADD COLUMN IF NOT EXISTS `membership_id` INT UNSIGNED DEFAULT NULL AFTER `plan_price`;

ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `school_member_id` INT(11) DEFAULT NULL AFTER `visited_person_name`,
  ADD COLUMN IF NOT EXISTS `membership_id`    INT UNSIGNED DEFAULT NULL AFTER `school_member_id`,
  ADD COLUMN IF NOT EXISTS `booking_source`   ENUM('public','patient_panel','school_student_self','school_parent') NOT NULL DEFAULT 'public' AFTER `membership_id`,
  ADD INDEX IF NOT EXISTS `idx_appt_school_member` (`school_member_id`);

-- ── 6. Foreign keys (RESTRICT on identity/clinical, SET NULL on optional attribution) ──
ALTER TABLE `school_health_memberships`
  ADD CONSTRAINT `fk_shm_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_shm_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`)        ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_shm_plan`   FOREIGN KEY IF NOT EXISTS (`plan_id`)   REFERENCES `school_health_plans`(`id`) ON DELETE SET NULL;

ALTER TABLE `school_doctor_assignments`
  ADD CONSTRAINT `fk_sda_school`   FOREIGN KEY IF NOT EXISTS (`school_id`)   REFERENCES `schools`(`id`)     ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sda_doctor`   FOREIGN KEY IF NOT EXISTS (`doctor_id`)   REFERENCES `doctors`(`id`)     ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sda_assigner` FOREIGN KEY IF NOT EXISTS (`assigned_by`) REFERENCES `admin_user`(`id`)  ON DELETE SET NULL;

ALTER TABLE `school_booking_holds`
  ADD CONSTRAINT `fk_sbh_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sbh_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`)        ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sbh_doctor` FOREIGN KEY IF NOT EXISTS (`doctor_id`) REFERENCES `doctors`(`id`)        ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sbh_appt`   FOREIGN KEY IF NOT EXISTS (`resulting_appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL;

ALTER TABLE `parent_consent_forms`
  ADD CONSTRAINT `fk_pcf_membership` FOREIGN KEY IF NOT EXISTS (`membership_id`) REFERENCES `school_health_memberships`(`id`) ON DELETE SET NULL;

ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_school_member` FOREIGN KEY IF NOT EXISTS (`school_member_id`) REFERENCES `school_members`(`id`)            ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appt_membership`    FOREIGN KEY IF NOT EXISTS (`membership_id`)    REFERENCES `school_health_memberships`(`id`) ON DELETE SET NULL;
