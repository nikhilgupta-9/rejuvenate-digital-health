-- ============================================================
-- Migration: WhatsApp automation — central dispatcher + logging
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_whatsapp_automation.sql
--
-- Backs lib/WhatsAppNotifier.php — a single dispatcher every event-driven
-- WhatsApp send (welcome, appointment booked/confirmed/rejected/reminder,
-- prescription/report ready, parent-consent request) goes through, on top
-- of the existing OTP-only path in lib/WhatsAppOtp.php.
--
-- whatsapp_templates maps an internal event_type to an approved Meta
-- template name + param order; rows are added as each template clears
-- Meta review. Only otp_verification is seeded here since it is already
-- live (matches WHATSAPP_OTP_TEMPLATE in .env) — the rest ship inactive
-- until approved, see database/MIGRATIONS.md.
-- ============================================================

CREATE TABLE IF NOT EXISTS `whatsapp_message_log` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `direction`       ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
    `event_type`      VARCHAR(60)     NOT NULL,
    `entity_type`     VARCHAR(30)     DEFAULT NULL,
    `entity_id`       INT(11)         DEFAULT NULL,
    `mobile`          VARCHAR(15)     NOT NULL,
    `template_name`   VARCHAR(100)    DEFAULT NULL,
    `message_type`    VARCHAR(20)     NOT NULL DEFAULT 'template',
    `payload`         JSON            DEFAULT NULL,
    `wamid`           VARCHAR(100)    DEFAULT NULL,
    `status`          ENUM('queued','sent','delivered','read','failed','replied') NOT NULL DEFAULT 'queued',
    `error`           VARCHAR(255)    DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_wamid` (`wamid`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_mobile` (`mobile`),
    INDEX `idx_event` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
    `id`            INT(11)         NOT NULL AUTO_INCREMENT,
    `event_type`    VARCHAR(60)     NOT NULL UNIQUE,
    `template_name` VARCHAR(100)    NOT NULL,
    `language`      VARCHAR(10)     NOT NULL DEFAULT 'en',
    `category`      ENUM('AUTHENTICATION','UTILITY','MARKETING') NOT NULL DEFAULT 'UTILITY',
    `param_order`   JSON            NOT NULL,
    `active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `updated_at`    DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_optouts` (
    `mobile`        VARCHAR(15) NOT NULL PRIMARY KEY,
    `opted_out_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `reminder_24h_sent` TINYINT(1) NOT NULL DEFAULT 0;

-- Seed the one template already live in production (matches WHATSAPP_OTP_TEMPLATE
-- in .env). WhatsAppNotifier::sendEvent() doesn't currently route OTP sends —
-- lib/WhatsAppOtp.php::wa_send_otp() still calls Meta directly — this row just
-- keeps the mapping table complete/consistent for when a future caller wants it.
INSERT IGNORE INTO `whatsapp_templates` (`event_type`, `template_name`, `language`, `category`, `param_order`)
VALUES ('otp_verification', 'otp_verification', 'en', 'AUTHENTICATION', '["otp"]');
