-- ============================================================
-- Migration: abha_accounts — single normalised home for ABHA identity
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_abha_accounts.sql
--
-- Replaces the ABHA columns scattered across users / school_members /
-- doctors (abha_id, abha_address, abha_linked, abha_linked_at,
-- abha_verified, abha_profile_data). One row per entity.
--
-- The old columns are NOT dropped here — this migration only adds the new
-- table. Data is copied over by database/migrate-abha-data.php (reviewed &
-- run manually). Code is repointed to this table in a follow-up step; the
-- old columns then stay as deprecated read-only fallbacks for one release
-- before a separate drop migration.
--
-- Naming: the 14-digit ABHA *number* lives in `abha_number` here (the old
-- schema confusingly called it `abha_id` on users/school_members/doctors
-- and `abha_number` on appointments/prescriptions).
-- ============================================================

CREATE TABLE IF NOT EXISTS `abha_accounts` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Which record this ABHA belongs to.
  `entity_type`   ENUM('patient','school_member','doctor') NOT NULL
                  COMMENT 'patient=users.id, school_member=school_members.id, doctor=doctors.id',
  `entity_id`     INT UNSIGNED NOT NULL,

  -- The ABHA identity.
  `abha_number`   VARCHAR(17)  DEFAULT NULL COMMENT '14 digits, formatted XX-XXXX-XXXX-XXXX',
  `abha_address`  VARCHAR(100) DEFAULT NULL COMMENT '@sbx / @abdm handle, e.g. name@abdm',

  -- State (kept separate, mirroring the old abha_linked vs abha_verified).
  `linked`        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = associated with this entity',
  `verified`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = confirmed against ABDM',
  `linked_at`     DATETIME     DEFAULT NULL,
  `verified_at`   DATETIME     DEFAULT NULL,

  -- Provenance.
  `source`        VARCHAR(50)  DEFAULT NULL
                  COMMENT 'aadhaar_otp | mobile_otp | dl | manual | doctor_added | admin | import | migrated',

  -- Last ABDM profile snapshot (was school_members.abha_profile_data).
  `profile_data`  LONGTEXT     DEFAULT NULL,

  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entity`      (`entity_type`, `entity_id`),
  KEY        `idx_abha_number`  (`abha_number`),
  KEY        `idx_abha_address` (`abha_address`),
  KEY        `idx_state`        (`linked`, `verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE on foreign keys: entity_id points at three different tables
-- depending on entity_type, so a classic single-column FK is not possible.
-- Referential integrity for abha_accounts is enforced in application code
-- (and by the per-entity delete/anonymise flows). See
-- database/migration_core_foreign_keys.sql for the FKs that ARE added
-- elsewhere.
