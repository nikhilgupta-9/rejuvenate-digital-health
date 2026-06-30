-- ============================================================
-- ABDM Security & Audit Tables
-- Retention: general 3m online / 2y archive
--            Aadhaar auth 2y online / 5y archive
-- ============================================================

-- Online audit log (all events)
CREATE TABLE IF NOT EXISTS `abdm_audit_logs` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type`      VARCHAR(50)  NOT NULL,
  `log_type`        VARCHAR(30)  NOT NULL DEFAULT 'GENERAL',

  -- Entity (who triggered the event)
  `entity_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `entity_type`     VARCHAR(20)  NOT NULL DEFAULT 'user',

  -- ABDM mandatory fields (Aadhaar auth)
  `terminal_id`     VARCHAR(100) DEFAULT NULL,
  `auth_modality`   VARCHAR(30)  DEFAULT NULL,
  `txn_id`          VARCHAR(100) DEFAULT NULL,
  `aua_code`        VARCHAR(50)  DEFAULT NULL,
  `user_consent`    CHAR(1)      DEFAULT NULL,
  `auth_status`     VARCHAR(20)  DEFAULT NULL,

  -- Auth attempt fields
  `identifier`      VARCHAR(255) DEFAULT NULL,
  `auth_method`     VARCHAR(30)  DEFAULT NULL,

  -- Validation / access
  `field_name`      VARCHAR(100) DEFAULT NULL,
  `reason`          VARCHAR(500) DEFAULT NULL,
  `resource`        VARCHAR(255) DEFAULT NULL,

  -- API anomaly
  `endpoint`        VARCHAR(255) DEFAULT NULL,
  `action`          VARCHAR(100) DEFAULT NULL,
  `http_code`       SMALLINT     DEFAULT NULL,

  -- Data access
  `accessor_id`     INT UNSIGNED DEFAULT NULL,
  `accessor_type`   VARCHAR(20)  DEFAULT NULL,
  `patient_id`      INT UNSIGNED DEFAULT NULL,
  `data_type`       VARCHAR(100) DEFAULT NULL,
  `purpose`         VARCHAR(255) DEFAULT NULL,

  -- Extra JSON payload (prohibited keys already stripped)
  `extra_data`      JSON         DEFAULT NULL,

  -- Network
  `ip_address`      VARCHAR(45)  NOT NULL DEFAULT '',
  `user_agent`      VARCHAR(255) DEFAULT NULL,

  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_entity`   (`entity_type`, `entity_id`),
  INDEX `idx_log_type` (`log_type`),
  INDEX `idx_created`  (`created_at`),
  INDEX `idx_txn`      (`txn_id`),
  INDEX `idx_ip`       (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Archive table (logs past online retention period)
CREATE TABLE IF NOT EXISTS `abdm_audit_archive` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_id`              BIGINT UNSIGNED NOT NULL,
  `log_type`            VARCHAR(30)  NOT NULL,
  `log_data`            JSON         NOT NULL,
  `original_created_at` DATETIME     NOT NULL,
  `archived_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY  `uq_log_id`  (`log_id`),
  INDEX       `idx_log_type` (`log_type`),
  INDEX       `idx_original` (`original_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
