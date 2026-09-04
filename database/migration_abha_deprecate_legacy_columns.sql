-- ============================================================
-- Migration: mark the per-entity ABHA columns as DEPRECATED
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_abha_deprecate_legacy_columns.sql
--
-- Non-destructive: only adds a COMMENT. The authoritative store is now
-- `abha_accounts` (see migration_abha_accounts.sql + ABHA_MIGRATION_NOTES.md).
-- These columns are still WRITTEN (mirrored by lib/Abha.php) so that read
-- sites not yet repointed keep working; a later migration drops them.
-- ============================================================

SET @dep = 'DEPRECATED — use abha_accounts (see database/ABHA_MIGRATION_NOTES.md); still mirrored by lib/Abha.php during transition';

ALTER TABLE `users`
  MODIFY COLUMN `abha_id`        VARCHAR(50)  DEFAULT NULL COMMENT 'DEPRECATED — 14-digit ABHA number; use abha_accounts.abha_number',
  MODIFY COLUMN `abha_address`   VARCHAR(100) DEFAULT NULL COMMENT 'DEPRECATED — use abha_accounts.abha_address',
  MODIFY COLUMN `abha_linked`    TINYINT(1)   DEFAULT 0    COMMENT 'DEPRECATED — use abha_accounts.linked',
  MODIFY COLUMN `abha_linked_at` DATETIME     DEFAULT NULL COMMENT 'DEPRECATED — use abha_accounts.linked_at',
  MODIFY COLUMN `abha_verified`  TINYINT(1)   DEFAULT 0    COMMENT 'DEPRECATED — use abha_accounts.verified';

ALTER TABLE `school_members`
  MODIFY COLUMN `abha_id`           VARCHAR(50)  DEFAULT NULL COMMENT 'DEPRECATED — 14-digit ABHA number; use abha_accounts.abha_number',
  MODIFY COLUMN `abha_address`      VARCHAR(100) DEFAULT NULL COMMENT 'DEPRECATED — use abha_accounts.abha_address',
  MODIFY COLUMN `abha_linked`       TINYINT(1)   DEFAULT 0    COMMENT 'DEPRECATED — use abha_accounts.linked',
  MODIFY COLUMN `abha_linked_at`    DATETIME     DEFAULT NULL COMMENT 'DEPRECATED — use abha_accounts.linked_at',
  MODIFY COLUMN `abha_verified`     TINYINT(1)   DEFAULT 0    COMMENT 'DEPRECATED — use abha_accounts.verified',
  MODIFY COLUMN `abha_profile_data` TEXT        DEFAULT NULL COMMENT 'DEPRECATED — use abha_accounts.profile_data';

ALTER TABLE `doctors`
  MODIFY COLUMN `abha_id` VARCHAR(20) DEFAULT NULL COMMENT 'DEPRECATED — use abha_accounts (entity_type=doctor).abha_number';

-- appointments.abha_number and prescriptions.abha_number are intentionally
-- NOT deprecated — they are per-visit snapshots, kept by design.
