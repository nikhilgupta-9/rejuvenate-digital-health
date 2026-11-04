-- ABDM Milestone 3 Phase 2 — Data flow (HIU role): actually requesting and
-- receiving a patient's records from another provider, once a consent
-- artefact (abdm_hiu_consent_artefacts) has been fetched. New tables because
-- this is data flowing INTO our system from an external HIP — a different
-- shape and direction from everything else we store (our own prescriptions /
-- lab bookings / uploaded documents), so it can't reuse those.

CREATE TABLE IF NOT EXISTS `abdm_hiu_data_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `artefact_id` INT(11) NOT NULL COMMENT 'FK to abdm_hiu_consent_artefacts.id',
  `request_id` VARCHAR(64) NOT NULL COMMENT 'our REQUEST-ID for the /health-information/request call',
  `transaction_id` VARCHAR(64) DEFAULT NULL COMMENT 'ABDM transactionId, filled by the on-request callback',
  `our_private_key` VARCHAR(100) NOT NULL COMMENT 'base64 X25519 private key generated for this request — needed to decrypt the incoming push',
  `our_nonce` VARCHAR(100) NOT NULL COMMENT 'base64 32-byte nonce generated for this request',
  `date_from` DATETIME NOT NULL,
  `date_to` DATETIME NOT NULL,
  `status` ENUM('requested','acknowledged','received','failed') NOT NULL DEFAULT 'requested',
  `error_detail` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hiu_dr_request_id` (`request_id`),
  KEY `idx_hiu_dr_transaction_id` (`transaction_id`),
  KEY `idx_hiu_dr_artefact` (`artefact_id`),
  KEY `idx_hiu_dr_status` (`status`),
  CONSTRAINT `fk_hiu_dr_artefact` FOREIGN KEY (`artefact_id`)
    REFERENCES `abdm_hiu_consent_artefacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per FHIR entry the HIP actually pushed (an artefact/transaction can
-- cover several care contexts, each delivered as its own encrypted entry).
CREATE TABLE IF NOT EXISTS `abdm_hiu_received_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `data_request_id` INT(11) NOT NULL COMMENT 'FK to abdm_hiu_data_requests.id',
  `care_context_reference` VARCHAR(120) DEFAULT NULL,
  `fhir_bundle_json` MEDIUMTEXT NOT NULL COMMENT 'decrypted plaintext FHIR R4 Bundle JSON',
  `hi_status` ENUM('OK','ERRORED') NOT NULL DEFAULT 'OK',
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hiu_rr_request` (`data_request_id`),
  CONSTRAINT `fk_hiu_rr_request` FOREIGN KEY (`data_request_id`)
    REFERENCES `abdm_hiu_data_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
