-- ============================================================
-- Migration: telemedicine_settings (key/value)
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_telemedicine_settings.sql
--
-- TURN/STUN + poll-interval config, edited in admin/telemedicine-settings.php
-- and read by telemedicine/config.php. Previously created at runtime.
-- ============================================================

CREATE TABLE IF NOT EXISTS `telemedicine_settings` (
  `setting_key`   VARCHAR(50) NOT NULL,
  `setting_value` TEXT        DEFAULT NULL,
  `updated_at`    DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
