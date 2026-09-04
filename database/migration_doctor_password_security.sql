-- ============================================================
-- Migration: doctor_password_history + doctor_password_logs
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_doctor_password_security.sql
--
-- Previously created at runtime by doctor/change-password.php.
-- ============================================================

CREATE TABLE IF NOT EXISTS `doctor_password_history` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `doctor_password_history_ibfk_1`
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `doctor_password_logs` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT        DEFAULT NULL,
  `action`     VARCHAR(50) DEFAULT NULL,
  `timestamp`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `doctor_password_logs_ibfk_1`
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
