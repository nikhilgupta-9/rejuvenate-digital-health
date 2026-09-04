-- ============================================================
-- Migration: school_health_plans
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_school_health_plans.sql
--
-- Marketing / pricing tiers shown on school/parent-consent.php and managed
-- in admin/school-plans.php. Previously created at runtime by BOTH of those
-- pages (duplicated DDL).
-- ============================================================

CREATE TABLE IF NOT EXISTS `school_health_plans` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(120) NOT NULL,
  `tier`            VARCHAR(40)  DEFAULT NULL,
  `tagline`         VARCHAR(200) DEFAULT NULL,
  `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `billing_label`   VARCHAR(40)  NOT NULL DEFAULT 'per student / year',
  `age_min`         TINYINT UNSIGNED DEFAULT NULL,
  `age_max`         TINYINT UNSIGNED DEFAULT NULL,
  `features`        TEXT         DEFAULT NULL,
  `accent_color`    VARCHAR(20)  NOT NULL DEFAULT '#0C74C5',
  `is_popular`      TINYINT(1)   NOT NULL DEFAULT 0,
  `show_on_consent` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`      INT          NOT NULL DEFAULT 0,
  `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`, `show_on_consent`),
  KEY `idx_age`    (`age_min`, `age_max`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
