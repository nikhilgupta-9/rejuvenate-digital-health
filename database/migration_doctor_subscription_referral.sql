-- ============================================================
-- DOCTOR SUBSCRIPTION + REFERRAL COMMISSION
--
-- `doctor_plans`             — subscription plan(s) doctors can pay for.
--                               Seeded with one ₹999/month plan; admin can
--                               add/edit more later, code doesn't assume
--                               there's only ever one row.
-- `doctor_subscriptions`     — one row per subscription payment (manual
--                               renewal — no Razorpay auto-recurring here).
-- `doctors.referred_by`      — which doctor's referral link this doctor
--                               signed up through (NULL if none).
-- `doctor_referral_earnings` — ledger: 10% of every paid subscription
--                               credited to the referring doctor. Payout
--                               itself is manual/offline (admin), this is
--                               just the running balance record.
-- ============================================================

CREATE TABLE IF NOT EXISTS `doctor_plans` (
    `id`                 INT(11)        NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(100)   NOT NULL,
    `price`              DECIMAL(10,2)  NOT NULL,
    `billing_cycle_days` INT(11)        NOT NULL DEFAULT 30,
    `is_active`          TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `doctor_plans` (`name`, `price`, `billing_cycle_days`, `is_active`)
VALUES ('Doctor Membership', 999.00, 30, 1);

CREATE TABLE IF NOT EXISTS `doctor_subscriptions` (
    `id`                   INT(11)        NOT NULL AUTO_INCREMENT,
    `doctor_id`            INT(11)        NOT NULL,
    `plan_id`              INT(11)        NOT NULL,
    `amount`               DECIMAL(10,2)  NOT NULL,
    `razorpay_order_id`    VARCHAR(64)    DEFAULT NULL,
    `razorpay_payment_id`  VARCHAR(64)    DEFAULT NULL,
    `razorpay_signature`   VARCHAR(128)   DEFAULT NULL,
    `status`               ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    `starts_at`            DATETIME       DEFAULT NULL,
    `expires_at`           DATETIME       DEFAULT NULL,
    `created_at`           DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_doctor_id` (`doctor_id`),
    INDEX `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_docsub_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_docsub_plan` FOREIGN KEY (`plan_id`) REFERENCES `doctor_plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `doctors`
  ADD COLUMN `referred_by` INT(11) DEFAULT NULL AFTER `doctor_uid`,
  ADD CONSTRAINT `fk_doctors_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `doctors`(`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `doctor_referral_earnings` (
    `id`                      INT(11)        NOT NULL AUTO_INCREMENT,
    `referring_doctor_id`     INT(11)        NOT NULL,
    `referred_doctor_id`      INT(11)        NOT NULL,
    `doctor_subscription_id`  INT(11)        NOT NULL,
    `subscription_amount`     DECIMAL(10,2)  NOT NULL,
    `commission_amount`       DECIMAL(10,2)  NOT NULL,
    `created_at`              DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_referring_doctor` (`referring_doctor_id`),
    UNIQUE KEY `uniq_subscription_credit` (`doctor_subscription_id`),
    CONSTRAINT `fk_earn_referrer` FOREIGN KEY (`referring_doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_earn_referred` FOREIGN KEY (`referred_doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_earn_subscription` FOREIGN KEY (`doctor_subscription_id`) REFERENCES `doctor_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
