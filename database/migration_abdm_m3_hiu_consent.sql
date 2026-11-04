-- ABDM Milestone 3 — HIU (Health Information User) role: our own system
-- requesting a patient's health records from OTHER providers via the ABDM
-- Consent Manager. This is the reverse direction of M2 (where we are the HIP
-- being asked); the data shape is different enough (we are the requester, not
-- the source) that it genuinely needs its own tables rather than reusing
-- abdm_care_context_links.
--
-- Phase 1 scope: Consent flow only (init / on-init / notify / on-notify ack /
-- status / on-status / fetch / on-fetch). Data flow (actually pulling and
-- decrypting the records) is a later phase, once consent is GRANTED.

CREATE TABLE IF NOT EXISTS `abdm_hiu_consent_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` VARCHAR(64) NOT NULL COMMENT 'our REQUEST-ID for the /consent/v3/request/init call',
  `consent_request_id` VARCHAR(64) DEFAULT NULL COMMENT 'ABDM consentRequest.id, filled by the on-init callback',
  `doctor_id` INT(11) DEFAULT NULL COMMENT 'doctor who initiated this request',
  `patient_id` INT(11) DEFAULT NULL COMMENT 'our internal patient (users.id), when resolvable',
  `abha_address` VARCHAR(100) NOT NULL COMMENT 'patient ABHA address the request targets',
  `hip_id` VARCHAR(64) DEFAULT NULL COMMENT 'target HIP id, optional — ABDM broadcasts to all linked HIPs when omitted',
  `purpose_code` VARCHAR(20) NOT NULL DEFAULT 'CAREMGT',
  `purpose_text` VARCHAR(100) NOT NULL DEFAULT 'Care Management',
  `hi_types` TEXT NOT NULL COMMENT 'JSON array, e.g. ["Prescription","DiagnosticReport"]',
  `date_from` DATETIME NOT NULL,
  `date_to` DATETIME NOT NULL,
  `expiry_at` DATETIME DEFAULT NULL COMMENT 'permission.dataEraseAt',
  `status` ENUM('requested','granted','denied','revoked','failed') NOT NULL DEFAULT 'requested',
  `reason` VARCHAR(255) DEFAULT NULL COMMENT 'denial/revoke reason, when ABDM sends one',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hiu_cr_request_id` (`request_id`),
  KEY `idx_hiu_cr_consent_request_id` (`consent_request_id`),
  KEY `idx_hiu_cr_patient` (`patient_id`),
  KEY `idx_hiu_cr_doctor` (`doctor_id`),
  KEY `idx_hiu_cr_status` (`status`),
  CONSTRAINT `fk_hiu_cr_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hiu_cr_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One consent request can be GRANTED by the patient against multiple HIPs —
-- ABDM generates one consent artefact per HIP, delivered as a list in the
-- 'notify' callback. Each artefact is fetched (on-fetch) independently and
-- is what Phase 2 (data flow) will actually use to request the records.
CREATE TABLE IF NOT EXISTS `abdm_hiu_consent_artefacts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `hiu_consent_request_id` INT(11) NOT NULL COMMENT 'FK to abdm_hiu_consent_requests.id',
  `consent_id` VARCHAR(64) NOT NULL COMMENT 'ABDM consent artefact id',
  `hip_id` VARCHAR(64) DEFAULT NULL COMMENT 'filled from the on-fetch payload',
  `status` ENUM('pending_fetch','fetched','failed') NOT NULL DEFAULT 'pending_fetch',
  `artefact_json` TEXT DEFAULT NULL COMMENT 'raw on-fetch payload — consumed by the later data-flow phase',
  `fetched_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hiu_artefact_consent_id` (`consent_id`),
  KEY `idx_hiu_artefact_request` (`hiu_consent_request_id`),
  KEY `idx_hiu_artefact_status` (`status`),
  CONSTRAINT `fk_hiu_artefact_request` FOREIGN KEY (`hiu_consent_request_id`)
    REFERENCES `abdm_hiu_consent_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
