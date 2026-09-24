-- ============================================================
-- Migration: pharmacy_module
-- Run once on: rej_digital_health_db (MariaDB 10.4)
-- Creates pharmacy_orders and pharmacy_medicines
-- ============================================================

CREATE TABLE IF NOT EXISTS `pharmacy_medicines` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150) NOT NULL,
  `generic_name` VARCHAR(150) DEFAULT NULL,
  `strength`     VARCHAR(50)  DEFAULT NULL,
  `dosage_form`  ENUM('Tablet','Capsule','Syrup','Injection','Ointment','Drops','Powder','Device','Other') NOT NULL DEFAULT 'Tablet',
  `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_status` ENUM('in_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
  `description`  TEXT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_generic` (`generic_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed common essential medicines / OTC wellness items if empty
INSERT IGNORE INTO `pharmacy_medicines` (`id`, `name`, `generic_name`, `strength`, `dosage_form`, `price`, `stock_status`, `description`) VALUES
(1, 'Paracetamol Tablets IP', 'Paracetamol', '650 mg', 'Tablet', 35.00, 'in_stock', 'Antipyretic and analgesic for fever and mild to moderate pain relief.'),
(2, 'Amoxicillin & Potassium Clavulanate', 'Amoxicillin + Clavulanic Acid', '625 mg', 'Tablet', 180.00, 'in_stock', 'Broad-spectrum antibiotic for bacterial infections.'),
(3, 'Pantoprazole Gastro-Resistant', 'Pantoprazole', '40 mg', 'Tablet', 95.00, 'in_stock', 'Proton pump inhibitor for acid reflux, GERD, and stomach ulcers.'),
(4, 'Cetirizine Hydrochloride', 'Cetirizine', '10 mg', 'Tablet', 25.00, 'in_stock', 'Antihistamine for allergy, runny nose, sneezing, and skin rashes.'),
(5, 'Azithromycin Tablets IP', 'Azithromycin', '500 mg', 'Tablet', 120.00, 'in_stock', 'Macrolide antibiotic for respiratory tract and skin infections.'),
(6, 'Metformin Hydrochloride (SR)', 'Metformin', '500 mg', 'Tablet', 45.00, 'in_stock', 'Oral anti-diabetic medication for managing Type 2 diabetes.'),
(7, 'Telmisartan Tablets IP', 'Telmisartan', '40 mg', 'Tablet', 75.00, 'in_stock', 'Antihypertensive medication for high blood pressure management.'),
(8, 'Atorvastatin Tablets IP', 'Atorvastatin', '10 mg', 'Tablet', 85.00, 'in_stock', 'Statin for lowering LDL cholesterol and cardiovascular risk.'),
(9, 'Vitamin D3 Chewable (Cholecalciferol)', 'Cholecalciferol', '60000 IU', 'Tablet', 150.00, 'in_stock', 'High-strength Vitamin D3 supplement for bone health and deficiency.'),
(10, 'Multivitamin with Zinc & Minerals', 'Multivitamin + Zinc', 'Standard', 'Capsule', 110.00, 'in_stock', 'Daily essential nutritional supplement to boost immunity and energy.');

CREATE TABLE IF NOT EXISTS `pharmacy_orders` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number`      VARCHAR(40)  NOT NULL,
  `user_id`           INT(11)      DEFAULT NULL,
  `prescription_id`   INT UNSIGNED DEFAULT NULL,
  `patient_name`      VARCHAR(150) NOT NULL,
  `patient_phone`     VARCHAR(20)  NOT NULL,
  `patient_email`     VARCHAR(150) DEFAULT NULL,
  `delivery_address`  TEXT         NOT NULL,
  `city`              VARCHAR(100) NOT NULL,
  `state`             VARCHAR(100) DEFAULT NULL,
  `zip_code`          VARCHAR(20)  DEFAULT NULL,
  `prescription_file` VARCHAR(255) DEFAULT NULL,
  `items_json`        LONGTEXT     DEFAULT NULL CHECK (json_valid(`items_json`)),
  `subtotal`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method`    ENUM('online','cod') NOT NULL DEFAULT 'cod',
  `payment_status`    ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `order_status`      ENUM('placed','verified','dispatched','delivered','cancelled') NOT NULL DEFAULT 'placed',
  `notes`             TEXT         DEFAULT NULL,
  `tracking_number`   VARCHAR(100) DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`order_status`),
  KEY `idx_phone` (`patient_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
