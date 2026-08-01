-- ============================================================
-- TELEMEDICINE (WebRTC video consultation)
-- Reuses existing appointments.meeting_* columns for session
-- lifecycle (already present but previously unused):
--   meeting_provider   = 'rejuvenate-webrtc'
--   meeting_event_id   = room token (also used as the WebRTC room id)
--   meeting_link       = full join URL
--   meeting_status     = not_created -> created -> started -> completed / cancelled
--   meeting_created_at / meeting_started_at / meeting_completed_at
--
-- Only new table needed is in-call chat history.
-- ============================================================

CREATE TABLE IF NOT EXISTS `telemedicine_chat_messages` (
    `id`             INT(11)      NOT NULL AUTO_INCREMENT,
    `appointment_id` INT(11)      NOT NULL,   -- appointments.id
    `sender_role`    ENUM('doctor','patient') NOT NULL,
    `sender_id`      INT(11)      NOT NULL,   -- doctors.id or users.id depending on sender_role
    `message`        TEXT         NOT NULL,
    `sent_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    INDEX `idx_appointment_id` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
