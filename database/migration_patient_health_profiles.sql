-- ============================================================
-- PATIENT HEALTH PROFILE
--
-- Mirrors `member_health_profiles` (school students, database/school_module.sql)
-- for adult patients (`users`). users.{allergies,existing_condition,
-- current_medication,medical_history,blood_group} are simple flat columns
-- that no page actually reads or writes — this table replaces that gap with
-- the same structured shape already used for students: vitals, medical
-- history, vaccination, emergency contact, checkup schedule, insurance.
--
-- One row per patient (UNIQUE patient_id) — accessed via
-- lib/PatientHealthProfile.php from admin (view-customer.php read-only,
-- edit-customer.php editable), doctor/patient-details.php, and the
-- patient's own user/health-profile.php.
-- ============================================================

CREATE TABLE IF NOT EXISTS `patient_health_profiles` (
  `id`                          INT(11)      NOT NULL AUTO_INCREMENT,
  `patient_id`                  INT(11)      NOT NULL,                 -- users.id
  -- Basic Health Info
  `height_cm`                   DECIMAL(5,2) DEFAULT NULL,
  `weight_kg`                   DECIMAL(5,2) DEFAULT NULL,
  `bmi`                         DECIMAL(4,2) DEFAULT NULL,              -- auto-calculated
  `blood_group`                 VARCHAR(10)  DEFAULT NULL,
  `blood_pressure`               VARCHAR(20)  DEFAULT NULL,             -- e.g. 120/80
  `pulse_rate`                  INT(11)      DEFAULT NULL,              -- per minute
  `vision_left`                 VARCHAR(20)  DEFAULT NULL,
  `vision_right`                VARCHAR(20)  DEFAULT NULL,
  -- Medical History
  `known_allergies`             TEXT         DEFAULT NULL,
  `chronic_conditions`          TEXT         DEFAULT NULL,              -- e.g. Asthma, Diabetes
  `current_medications`         TEXT         DEFAULT NULL,
  `past_surgeries`               TEXT         DEFAULT NULL,
  `disability`                  TEXT         DEFAULT NULL,
  -- Vaccination
  `vaccination_details`         TEXT         DEFAULT NULL,
  `is_vaccinated`                TINYINT(1)   DEFAULT 0,
  -- Emergency Contact
  `emergency_contact_name`      VARCHAR(150) DEFAULT NULL,
  `emergency_contact_phone`     VARCHAR(15)  DEFAULT NULL,
  `emergency_contact_relation`  VARCHAR(50)  DEFAULT NULL,
  -- Annual Health Checkup
  `last_checkup_date`           DATE         DEFAULT NULL,
  `next_checkup_date`           DATE         DEFAULT NULL,
  `checkup_notes`                TEXT         DEFAULT NULL,
  -- Insurance
  `insurance_provider`          VARCHAR(150) DEFAULT NULL,
  `insurance_number`            VARCHAR(100) DEFAULT NULL,
  -- Who last touched it — patient can self-edit, so this isn't only staff
  `last_updated_by`             INT(11)      DEFAULT NULL,              -- users.id / doctors.id / admin_user.id depending on role
  `last_updated_role`           ENUM('patient','doctor','admin') DEFAULT NULL,
  `created_at`                  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient` (`patient_id`),
  CONSTRAINT `fk_php_patient` FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
