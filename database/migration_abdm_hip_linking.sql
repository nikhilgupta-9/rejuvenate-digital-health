-- ============================================================
-- Migration: ABDM HIP-Initiated Linking (M3, HIECM V3, async)
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_abdm_hip_linking.sql
--
-- Backs lib/HipApi.php + telemedicine/api/abdm-webhook.php:
--   1. token/generate-token           -> abdm_link_tokens        (link token, async via webhook)
--   2. hip/v3/link/carecontext        -> abdm_care_context_links  (link a visit's care context)
--   3. hip/v3/link/context/notify     -> (notify only, no row)
--   webhook callbacks                 -> abdm_webhook_log         (raw, saved before processing)
-- ============================================================

-- ── 1. Link tokens ──────────────────────────────────────────
-- We POST token/generate-token with our REQUEST-ID; ABDM returns the
-- X-LINK-TOKEN asynchronously to the webhook (callback type "linkToken").
CREATE TABLE IF NOT EXISTS `abdm_link_tokens` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id`   INT(11) NOT NULL,
  `abha_address` VARCHAR(100) NOT NULL,
  `link_token`   TEXT        DEFAULT NULL COMMENT 'X-LINK-TOKEN — populated when the webhook arrives',
  `request_id`   VARCHAR(64) NOT NULL COMMENT 'REQUEST-ID we sent; the webhook matches on this',
  `status`       ENUM('pending','received','expired') NOT NULL DEFAULT 'pending',
  `expires_at`   DATETIME NOT NULL COMMENT 'link token validity — 6 months',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request` (`request_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_abha`    (`abha_address`),
  KEY `idx_status`  (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Care-context links (one per prescription / visit) ─────
CREATE TABLE IF NOT EXISTS `abdm_care_context_links` (
  `id`                     INT(11) NOT NULL AUTO_INCREMENT,
  `prescription_id`        INT(10) UNSIGNED NOT NULL,
  `reference_number`       VARCHAR(100) DEFAULT NULL COMMENT 'patient reference sent to ABDM',
  `care_context_reference` VARCHAR(120) NOT NULL,
  `hi_type`                VARCHAR(40)  DEFAULT NULL COMMENT 'Prescription | OPConsultation | DiagnosticReport | ...',
  `request_id`             VARCHAR(64)  NOT NULL,
  `status`                 ENUM('pending','linked','failed') NOT NULL DEFAULT 'pending',
  `webhook_received_at`    DATETIME     DEFAULT NULL,
  `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request`   (`request_id`),
  KEY `idx_prescription` (`prescription_id`),
  KEY `idx_status`       (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Raw webhook log — written BEFORE any processing/validation ──
CREATE TABLE IF NOT EXISTS `abdm_webhook_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id`    VARCHAR(64)  DEFAULT NULL COMMENT 'extracted from the payload, if present',
  `callback_type` VARCHAR(60)  DEFAULT NULL COMMENT 'linkToken | linking-status | unknown',
  `raw_payload`   LONGTEXT     NOT NULL COMMENT 'exact body received (may be malformed — saved as-is)',
  `processed`     TINYINT(1)   NOT NULL DEFAULT 0,
  `received_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request`   (`request_id`),
  KEY `idx_processed` (`processed`, `received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FKs (separate ALTERs — IF NOT EXISTS is only valid on ALTER ... ADD) ──
-- Transient linking workflow → CASCADE (the clinical records themselves are
-- RESTRICT-protected in migration_core_foreign_keys.sql, so a parent delete
-- can only happen once those are gone).
ALTER TABLE `abdm_link_tokens`
  ADD CONSTRAINT `fk_linktok_patient` FOREIGN KEY IF NOT EXISTS (`patient_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `abdm_care_context_links`
  ADD CONSTRAINT `fk_cclink_rx` FOREIGN KEY IF NOT EXISTS (`prescription_id`)
    REFERENCES `prescriptions`(`id`) ON DELETE CASCADE;
