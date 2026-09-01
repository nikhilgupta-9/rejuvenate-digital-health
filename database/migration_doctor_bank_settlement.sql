-- ============================================================
-- DOCTOR BANK DETAILS + PAYMENT SETTLEMENT (T+2)
--
-- `doctor_bank_accounts` — one row per doctor, filled by the doctor
--   themselves in the doctor panel. Admin can view (and mark verified)
--   in admin/doctor-edit.php.
--
-- `appointment_settlements` — one row per completed, paid appointment.
--   Created automatically (see lib/Settlement.php) whenever an
--   appointment's status becomes 'completed' and it was actually paid
--   for (appointments.payment_status = 'paid'). due_date = completion
--   date + 2 days (T+2). Settlement itself is a manual admin action
--   (bank transfer done offline, then marked 'settled' here) — same
--   manual-payout pattern already used for doctor_referral_earnings.
--   Platform keeps a 10% commission; the doctor is settled the rest.
-- ============================================================

CREATE TABLE IF NOT EXISTS `doctor_bank_accounts` (
    `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
    `doctor_id`           INT(11)       NOT NULL,
    `account_holder_name` VARCHAR(150)  NOT NULL,
    `account_number`      VARCHAR(30)   NOT NULL,
    `ifsc_code`           VARCHAR(11)   NOT NULL,
    `bank_name`           VARCHAR(150)  NOT NULL,
    `upi_id`              VARCHAR(100)  DEFAULT NULL,
    `is_verified`         TINYINT(1)    NOT NULL DEFAULT 0,
    `verified_at`         DATETIME      DEFAULT NULL,
    `verified_by`         INT(11)       DEFAULT NULL,
    `created_at`          DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_doctor` (`doctor_id`),
    CONSTRAINT `fk_bank_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointment_settlements` (
    `id`                    INT(11)        NOT NULL AUTO_INCREMENT,
    `appointment_id`        INT(11)        NOT NULL,
    `doctor_id`             INT(11)        NOT NULL,
    `gross_amount`          DECIMAL(10,2)  NOT NULL,
    `commission_rate`       DECIMAL(5,2)   NOT NULL DEFAULT 10.00,
    `commission_amount`     DECIMAL(10,2)  NOT NULL,
    `settlement_amount`     DECIMAL(10,2)  NOT NULL,
    `status`                ENUM('pending','settled') NOT NULL DEFAULT 'pending',
    `due_date`              DATE           NOT NULL,
    `settled_at`            DATETIME       DEFAULT NULL,
    `settled_by`            INT(11)        DEFAULT NULL,
    `settlement_reference`  VARCHAR(100)   DEFAULT NULL,
    `created_at`            DATETIME       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_appointment` (`appointment_id`),
    CONSTRAINT `fk_settle_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_settle_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    INDEX `idx_doctor` (`doctor_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
