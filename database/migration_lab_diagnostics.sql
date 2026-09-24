-- ============================================================
-- Migration: lab_diagnostics
-- Run once on: rej_digital_health_db (MariaDB 10.4)
-- Creates lab_tests_catalog and lab_bookings
-- ============================================================

CREATE TABLE IF NOT EXISTS `lab_tests_catalog` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_code`        VARCHAR(50)  NOT NULL,
  `test_name`        VARCHAR(150) NOT NULL,
  `category`         ENUM('Pathology','Biochemistry','Microbiology','Radiology','Health Package') NOT NULL DEFAULT 'Pathology',
  `sample_type`      VARCHAR(100) NOT NULL DEFAULT 'Blood',
  `fasting_required` TINYINT(1)  NOT NULL DEFAULT 0,
  `turnaround_hours` INT          NOT NULL DEFAULT 24,
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_price`   DECIMAL(10,2) DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `status`           ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_test_code` (`test_code`),
  KEY `idx_cat` (`category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed comprehensive test catalog and preventive packages
INSERT IGNORE INTO `lab_tests_catalog` (`id`, `test_code`, `test_name`, `category`, `sample_type`, `fasting_required`, `turnaround_hours`, `price`, `discount_price`, `description`, `status`) VALUES
(1, 'CBC001', 'Complete Blood Count (CBC) with ESR', 'Pathology', 'Blood (EDTA)', 0, 12, 450.00, 350.00, 'Assesses overall health and detects an array of disorders including anemia and infection.', 'Active'),
(2, 'LIP002', 'Lipid Profile — Complete Cholesterol', 'Biochemistry', 'Blood (Serum)', 1, 24, 750.00, 599.00, 'Measures Total Cholesterol, HDL, LDL, VLDL, and Triglycerides to evaluate cardiovascular risk.', 'Active'),
(3, 'LFT003', 'Liver Function Test (LFT)', 'Biochemistry', 'Blood (Serum)', 0, 24, 850.00, 699.00, 'Evaluates liver health by measuring SGOT, SGPT, Bilirubin, Protein, and Albumin.', 'Active'),
(4, 'KFT004', 'Kidney Function Test (KFT / RFT)', 'Biochemistry', 'Blood (Serum)', 0, 24, 800.00, 649.00, 'Checks renal clearance via Serum Creatinine, Blood Urea Nitrogen (BUN), and Uric Acid.', 'Active'),
(5, 'THY005', 'Thyroid Profile Total (T3, T4, TSH)', 'Biochemistry', 'Blood (Serum)', 0, 24, 650.00, 499.00, 'Comprehensive screening for hyperthyroidism and hypothyroidism.', 'Active'),
(6, 'HBA006', 'HbA1c (Glycated Hemoglobin)', 'Biochemistry', 'Blood (EDTA)', 0, 12, 600.00, 499.00, 'Measures average blood sugar levels over the past 3 months for diabetes monitoring.', 'Active'),
(7, 'VIT007', 'Vitamin D Total (25-Hydroxy)', 'Biochemistry', 'Blood (Serum)', 0, 36, 1400.00, 999.00, 'Detects Vitamin D deficiency vital for bone density and immune regulation.', 'Active'),
(8, 'B12008', 'Vitamin B12 (Cyanocobalamin)', 'Biochemistry', 'Blood (Serum)', 0, 36, 1100.00, 799.00, 'Checks nerve function, brain health, and red blood cell production.', 'Active'),
(9, 'URN009', 'Routine & Microscopic Urine Examination', 'Pathology', 'Urine Sample', 0, 12, 250.00, 199.00, 'Screens for urinary tract infections (UTI), kidney disease, and metabolic disorders.', 'Active'),
(10, 'PKG010', 'Rejuvenate Full Body Health Checkup', 'Health Package', 'Blood + Urine', 1, 24, 3200.00, 1499.00, 'Comprehensive 65+ parameters: CBC, LFT, KFT, Lipid Profile, Thyroid, Blood Glucose, and Urine Analysis.', 'Active'),
(11, 'PKG011', 'Senior Citizen Health Package (Comprehensive)', 'Health Package', 'Blood + Urine', 1, 24, 4500.00, 2199.00, 'Advanced health checkup with CBC, LFT, KFT, Lipid, Vitamin D & B12, HbA1c, and Urine analysis.', 'Active');

CREATE TABLE IF NOT EXISTS `lab_bookings` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_uid`       VARCHAR(40)  NOT NULL,
  `user_id`           INT(11)      DEFAULT NULL,
  `prescription_id`   INT UNSIGNED DEFAULT NULL,
  `patient_name`      VARCHAR(150) NOT NULL,
  `patient_phone`     VARCHAR(20)  NOT NULL,
  `patient_email`     VARCHAR(150) DEFAULT NULL,
  `collection_type`   ENUM('home_collection','visit_lab') NOT NULL DEFAULT 'home_collection',
  `address`           TEXT         NOT NULL,
  `city`              VARCHAR(100) NOT NULL,
  `zip_code`          VARCHAR(20)  DEFAULT NULL,
  `booking_date`      DATE         NOT NULL,
  `time_slot`         VARCHAR(50)  NOT NULL,
  `tests_json`        LONGTEXT     NOT NULL CHECK (json_valid(`tests_json`)),
  `prescription_file` VARCHAR(255) DEFAULT NULL,
  `total_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method`    ENUM('online','cod') NOT NULL DEFAULT 'cod',
  `payment_status`    ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `status`            ENUM('scheduled','sample_collected','processing','report_uploaded','cancelled') NOT NULL DEFAULT 'scheduled',
  `report_file`       VARCHAR(255) DEFAULT NULL,
  `notes`             TEXT         DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_uid` (`booking_uid`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`booking_date`),
  KEY `idx_phone` (`patient_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
