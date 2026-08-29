-- ============================================================
-- TELEMEDICINE — HTTP-POLLING SIGNALING (replaces the Ratchet
-- WebSocket signaling server, which needs a persistent process on
-- a custom port — not available on Hostinger shared/Business
-- hosting). Pure request/response PHP + MySQL from here on.
--
-- `telemedicine_rooms`   — one row per active call room: who's
--                          present (heartbeat via last_seen), and
--                          whether the 'ready' (start-offer) signal
--                          has already been sent for this presence
--                          window.
-- `telemedicine_signals` — the message relay "mailbox". Each
--                          offer/answer/ice-candidate/chat/
--                          toggle-media/end-call is a row; each
--                          side polls for rows from the OTHER role
--                          since the last id it has seen.
-- ============================================================

CREATE TABLE IF NOT EXISTS `telemedicine_rooms` (
    `room`               VARCHAR(64)  NOT NULL,
    `appointment_id`     INT(11)      NOT NULL,
    `doctor_last_seen`   DATETIME(3)  DEFAULT NULL,
    `doctor_entity_id`   INT(11)      DEFAULT NULL,
    `doctor_name`        VARCHAR(150) DEFAULT NULL,
    `patient_last_seen`  DATETIME(3)  DEFAULT NULL,
    `patient_entity_id`  INT(11)      DEFAULT NULL,
    `patient_name`       VARCHAR(150) DEFAULT NULL,
    `ready_sent`         TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`room`),
    INDEX `idx_appointment_id` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `telemedicine_signals` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room`       VARCHAR(64)     NOT NULL,
    `from_role`  VARCHAR(16)     NOT NULL,   -- 'doctor' | 'patient' | 'system'
    `type`       VARCHAR(32)     NOT NULL,   -- offer | answer | ice-candidate | chat | peer-media | ready | call-ended
    `payload`    MEDIUMTEXT      NOT NULL,   -- JSON
    `created_at` DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    INDEX `idx_room_id` (`room`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
