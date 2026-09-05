-- ============================================================
-- Migration: ABDM HI Consent + Data-Request plumbing (Phase A)
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_abdm_hi_consent.sql
--
-- Phase A = RECEIVE + ACKNOWLEDGE only:
--   /api/v3/consent/request/hip/notify        -> abha_consents      (granted / revoked)
--   /api/v3/hip/health-information/request     -> abha_hi_requests   (acknowledged)
--   raw callbacks                              -> abdm_webhook_log   (channel column below)
--
-- OUT OF SCOPE (Phase B): consent-signature verification, FHIR bundle
-- building, Fidelius encryption, the actual POST to the HIU dataPushUrl.
-- abha_hi_requests only advances to 'ready_for_push' here.
-- ============================================================

-- ── abdm_webhook_log: tag which callback family a row belongs to ──
ALTER TABLE `abdm_webhook_log`
  ADD COLUMN IF NOT EXISTS `channel` VARCHAR(20) NOT NULL DEFAULT 'linking'
    COMMENT 'linking | consent | hi_request'
    AFTER `id`;

-- ── 1. Consent artefacts (one row per consentId) ─────────────
CREATE TABLE IF NOT EXISTS `abha_consents` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consent_id`            VARCHAR(64)  NOT NULL COMMENT 'CM consent artefact id',
  `status`                ENUM('granted','revoked') NOT NULL DEFAULT 'granted',
  `patient_id`            INT(11)      DEFAULT NULL COMMENT 'resolved users.id (NULL if we do not hold this patient)',
  `abha_address`          VARCHAR(100) DEFAULT NULL,
  `hiu_id`                VARCHAR(100) DEFAULT NULL,
  `purpose_text`          VARCHAR(150) DEFAULT NULL,
  `purpose_code`          VARCHAR(30)  DEFAULT NULL,
  `hi_types`              LONGTEXT     DEFAULT NULL COMMENT 'JSON array e.g. ["Prescription","OPConsultation"]',
  `date_range_from`       DATETIME     DEFAULT NULL,
  `date_range_to`         DATETIME     DEFAULT NULL,
  `data_erase_at`         DATETIME     DEFAULT NULL COMMENT 'permission.dataEraseAt',
  `frequency_unit`        VARCHAR(10)  DEFAULT NULL,
  `frequency_value`       INT(11)      DEFAULT NULL,
  `frequency_repeats`     INT(11)      DEFAULT NULL,
  `signature`             TEXT         DEFAULT NULL COMMENT 'detached JWS from the CM (retained for audit; verified in Phase B)',
  `grant_acknowledgement` TEXT         DEFAULT NULL,
  `raw_payload`           LONGTEXT     NOT NULL COMMENT 'exact notify body as received (JSON)',
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'set on a revoke update',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consent` (`consent_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_status`  (`status`),
  KEY `idx_hiu`     (`hiu_id`),
  KEY `idx_erase`   (`data_erase_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Identity ref → RESTRICT (matches migration_core_foreign_keys policy).
-- Nullable: a consent can name a patient we do not hold.
ALTER TABLE `abha_consents`
  ADD CONSTRAINT `fk_consent_patient` FOREIGN KEY IF NOT EXISTS (`patient_id`)
    REFERENCES `users`(`id`) ON DELETE RESTRICT;

-- ── 2. Health-information requests (one row per HIU data request) ──
CREATE TABLE IF NOT EXISTS `abha_hi_requests` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id`   VARCHAR(64)  DEFAULT NULL COMMENT 'gateway transactionId (echoed in on-request)',
  `request_id`       VARCHAR(64)  DEFAULT NULL COMMENT 'gateway REQUEST-ID',
  `consent_id`       VARCHAR(64)  NOT NULL COMMENT 'logical ref → abha_consents.consent_id (no FK)',
  `status`           ENUM('pending','acknowledged','ready_for_push','failed') NOT NULL DEFAULT 'pending',
  `date_range_from`  DATETIME     DEFAULT NULL,
  `date_range_to`    DATETIME     DEFAULT NULL,
  `data_push_url`    VARCHAR(500) DEFAULT NULL COMMENT 'HIU endpoint — Phase B POSTs the encrypted bundle here',
  `key_material`     LONGTEXT     NOT NULL COMMENT 'JSON: HIU {cryptoAlg,curve,dhPublicKey{expiry,parameters,keyValue},nonce} — used by Phase B',
  `error_detail`     VARCHAR(255) DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_txn` (`transaction_id`),
  KEY `idx_consent` (`consent_id`),
  KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
