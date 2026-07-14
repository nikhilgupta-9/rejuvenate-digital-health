-- ============================================================
-- ADMIN PANEL — JWT AUTH + RATE LIMITING
-- Migrates admin auth from PHP sessions to JWT (matching the
-- doctor panel), and adds IP-based login rate limiting that
-- survives session/cookie clearing.
--
-- jwt_refresh_tokens already exists (entity_type/entity_id design,
-- reused here with entity_type='admin') — no changes needed there.
-- ============================================================

CREATE TABLE IF NOT EXISTS `login_rate_limits` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`   VARCHAR(20)     NOT NULL,   -- 'admin', 'doctor', ...
    `ip_address`    VARCHAR(45)     NOT NULL,
    `identifier`    VARCHAR(150)    DEFAULT NULL, -- username/email attempted (for forensics only)
    `success`       TINYINT(1)      NOT NULL DEFAULT 0,
    `attempted_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_throttle` (`entity_type`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
