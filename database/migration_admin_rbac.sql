-- ============================================================
-- Migration: admin RBAC — roles / permissions / role-permission map
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_admin_rbac.sql
--
-- Schema previously created at runtime by admin/user-roles.php and
-- admin/permissions.php (duplicated DDL).
--
-- SEED DATA (3 system roles, ~40 permissions, default role→permission map)
-- is still bootstrapped by admin/user-roles.php on first load — its
-- `if (COUNT(*) == 0)` guard makes it a one-time no-op once populated.
-- ============================================================

CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(50)  NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `color`        VARCHAR(20)  DEFAULT '#0C74C5',
  `icon`         VARCHAR(50)  DEFAULT 'fa-user-shield',
  `is_system`    TINYINT(1)   DEFAULT 0,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description`  VARCHAR(255) DEFAULT '',
  `module`       VARCHAR(50)  NOT NULL,
  `sort_order`   INT          DEFAULT 0,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `role_id`       INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
