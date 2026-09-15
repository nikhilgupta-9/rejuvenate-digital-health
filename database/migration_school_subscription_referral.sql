-- ============================================================
-- SCHOOL SUBSCRIPTION PLANS + REFERRAL COMMISSION
--
-- `school_plans`               — platform subscription tiers a school
--                                 itself can pay for (Free/Growth/Scale by
--                                 default). Separate from the existing
--                                 per-student `school_health_plans`
--                                 (age-banded, shown on the parent consent
--                                 form) — this is the school's own SaaS
--                                 plan, not something it resells to parents.
-- `school_subscriptions`       — one row per subscription request. Unlike
--                                 the doctor flow, EVERY plan (free or
--                                 paid) needs admin approval, so a row
--                                 moves pending_payment -> pending_approval
--                                 -> active/rejected, never straight to
--                                 active on payment alone.
-- `schools.referred_by`        — which school's referral link (their
--                                 school_uid) this school signed up
--                                 through (NULL if none).
-- `school_referral_earnings`   — ledger: 25% of a referred school's FIRST
--                                 paid+approved subscription credited to
--                                 the referring school. Payout is manual/
--                                 offline (admin "Mark Paid"), tracked here.
-- ============================================================

CREATE TABLE IF NOT EXISTS `school_plans` (
    `id`                 INT(11)        NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(100)   NOT NULL,
    `tier`               VARCHAR(60)    DEFAULT NULL,
    `tagline`            VARCHAR(200)   DEFAULT NULL,
    `price`              DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `billing_cycle_days` INT(11)        NOT NULL DEFAULT 365,
    `max_students`       INT(11)        DEFAULT NULL COMMENT 'NULL = unlimited',
    `features`           TEXT           DEFAULT NULL COMMENT 'one per line',
    `accent_color`       VARCHAR(7)     NOT NULL DEFAULT '#0C74C5',
    `sort_order`         INT(11)        NOT NULL DEFAULT 0,
    `is_popular`         TINYINT(1)     NOT NULL DEFAULT 0,
    `is_active`          TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `school_plans` (`name`, `tier`, `tagline`, `price`, `max_students`, `features`, `sort_order`, `is_popular`)
SELECT * FROM (SELECT
    'Starter' AS name, 'Free' AS tier, 'Get started at no cost' AS tagline,
    0.00 AS price, 50 AS max_students,
    'Digital Health ID for members\nBasic health records\nEmail support' AS features,
    1 AS sort_order, 0 AS is_popular
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `school_plans` WHERE `name` = 'Starter');

INSERT INTO `school_plans` (`name`, `tier`, `tagline`, `price`, `max_students`, `features`, `sort_order`, `is_popular`)
SELECT * FROM (SELECT
    'Growth' AS name, 'Up to 500 students' AS tier, 'For growing schools' AS tagline,
    9999.00 AS price, 500 AS max_students,
    'Everything in Starter\nUp to 500 students\nPriority support\nBulk member import' AS features,
    2 AS sort_order, 1 AS is_popular
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `school_plans` WHERE `name` = 'Growth');

INSERT INTO `school_plans` (`name`, `tier`, `tagline`, `price`, `max_students`, `features`, `sort_order`, `is_popular`)
SELECT * FROM (SELECT
    'Scale' AS name, 'Up to 1000 students' AS tier, 'For large institutions' AS tagline,
    17999.00 AS price, 1000 AS max_students,
    'Everything in Growth\nUp to 1000 students\nDedicated account manager' AS features,
    3 AS sort_order, 0 AS is_popular
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `school_plans` WHERE `name` = 'Scale');

CREATE TABLE IF NOT EXISTS `school_subscriptions` (
    `id`                   INT(11)        NOT NULL AUTO_INCREMENT,
    `school_id`            INT(11)        NOT NULL,
    `plan_id`              INT(11)        NOT NULL,
    `amount`               DECIMAL(10,2)  NOT NULL,
    `razorpay_order_id`    VARCHAR(64)    DEFAULT NULL,
    `razorpay_payment_id`  VARCHAR(64)    DEFAULT NULL,
    `razorpay_signature`   VARCHAR(128)   DEFAULT NULL,
    `status`               ENUM('pending_payment','pending_approval','active','rejected','expired') NOT NULL DEFAULT 'pending_payment',
    `rejection_reason`     TEXT           DEFAULT NULL,
    `approved_by`          INT(11)        DEFAULT NULL,
    `approved_at`          DATETIME       DEFAULT NULL,
    `starts_at`            DATETIME       DEFAULT NULL,
    `expires_at`           DATETIME       DEFAULT NULL,
    `created_at`           DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_schsub_school_id` (`school_id`),
    INDEX `idx_schsub_status` (`status`),
    INDEX `idx_schsub_expires_at` (`expires_at`),
    CONSTRAINT `fk_schsub_school` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_schsub_plan` FOREIGN KEY (`plan_id`) REFERENCES `school_plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `schools`
  ADD COLUMN IF NOT EXISTS `referred_by` INT(11) DEFAULT NULL AFTER `school_uid`;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND CONSTRAINT_NAME = 'fk_schools_referred_by'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `schools` ADD CONSTRAINT `fk_schools_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `schools`(`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `school_referral_earnings` (
    `id`                       INT(11)        NOT NULL AUTO_INCREMENT,
    `referring_school_id`      INT(11)        NOT NULL,
    `referred_school_id`       INT(11)        NOT NULL,
    `school_subscription_id`   INT(11)        NOT NULL,
    `subscription_amount`      DECIMAL(10,2)  NOT NULL,
    `commission_amount`        DECIMAL(10,2)  NOT NULL,
    `payout_status`            ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    `paid_at`                  DATETIME       DEFAULT NULL,
    `paid_by`                  INT(11)        DEFAULT NULL,
    `payout_reference`         VARCHAR(100)   DEFAULT NULL,
    `created_at`               DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_earn_referring_school` (`referring_school_id`),
    INDEX `idx_earn_payout_status` (`payout_status`),
    UNIQUE KEY `uniq_subscription_credit` (`school_subscription_id`),
    CONSTRAINT `fk_sch_earn_referrer` FOREIGN KEY (`referring_school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sch_earn_referred` FOREIGN KEY (`referred_school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sch_earn_subscription` FOREIGN KEY (`school_subscription_id`) REFERENCES `school_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
