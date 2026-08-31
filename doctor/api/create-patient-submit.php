<?php
/**
 * API: Create a new patient and link to doctor
 * POST JSON body: { first_name, last_name, email, mobile, gender, dob, blood_group,
 *                   abha_number, abha_address, abha_verified, pincode, city, state,
 *                   address, aadhaar_last4, reference_doctor, middle_name, no_email }
 * Returns { success, patient_id, message }
 */
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/util/otp-service.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$raw  = json_decode(file_get_contents('php://input'), true) ?? [];
$post = array_map('trim', $raw);

// Fast path: just link an existing patient by ID (from mobile search)
if (!empty($raw['link_existing_id'])) {
    $pid = (int)$raw['link_existing_id'];
    $lnk = $conn->prepare("INSERT INTO doctor_patients (doctor_id,patient_id,added_via) VALUES (?,?,'manual')
                           ON DUPLICATE KEY UPDATE added_via='manual'");
    $lnk->bind_param('ii', $doctor_id, $pid);
    $lnk->execute();
    echo json_encode(['success'=>true,'patient_id'=>$pid,'message'=>'Patient linked to your panel']);
    exit;
}

// Required fields
$first_name = $post['first_name'] ?? '';
$last_name  = $post['last_name']  ?? '';
$mobile     = preg_replace('/\D/', '', $post['mobile'] ?? '');

if (!$first_name) { echo json_encode(['success'=>false,'error'=>'First name is required']); exit; }
if (strlen($mobile) !== 10) { echo json_encode(['success'=>false,'error'=>'Valid 10-digit mobile is required']); exit; }

$no_email = !empty($post['no_email']);
$email    = $no_email ? null : ($post['email'] ?? '');
if (!$no_email && $email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'error'=>'Invalid email address']); exit;
}
if ($no_email) $email = null;

$middle_name      = $post['middle_name']      ?? '';
$name             = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
$gender           = $post['gender']           ?? '';
$dob              = $post['dob']              ?? null;
$blood_group      = $post['blood_group']      ?? '';
$abha_number      = $post['abha_number']      ?? '';
$abha_address     = $post['abha_address']     ?? '';
$abha_verified    = !empty($post['abha_verified']) ? 1 : 0;
$pincode          = $post['pincode']          ?? '';
$city             = $post['city']             ?? '';
$state            = $post['state']            ?? '';
$address          = $post['address']          ?? '';
$reference_doctor = $post['reference_doctor'] ?? '';

// ABHA number is recorded as-is for now (no live ABDM verification yet).
// Accept a 14-digit number in any spacing and store it normalised.
if ($abha_number !== '') {
    $abha_digits = preg_replace('/\D/', '', $abha_number);
    if (strlen($abha_digits) !== 14) {
        echo json_encode(['success'=>false,'error'=>'ABHA number must be 14 digits (XX-XXXX-XXXX-XXXX).']); exit;
    }
    $abha_number = substr($abha_digits,0,2).'-'.substr($abha_digits,2,4).'-'.substr($abha_digits,6,4).'-'.substr($abha_digits,10,4);
}
if ($abha_address !== '' && !preg_match('/^[a-zA-Z0-9._]{3,}@[a-zA-Z]+$/', $abha_address)) {
    echo json_encode(['success'=>false,'error'=>'ABHA address looks invalid (expected e.g. name@abdm).']); exit;
}

// Convert dob dd/mm/yyyy → yyyy-mm-dd if needed
if ($dob && strpos($dob, '/') !== false) {
    $parts = explode('/', $dob);
    if (count($parts) === 3) $dob = $parts[2].'-'.$parts[1].'-'.$parts[0];
}
if (!$dob) $dob = null;

// Check if mobile already registered
$chk = $conn->prepare("SELECT id FROM users WHERE mobile=? LIMIT 1");
$chk->bind_param('s', $mobile);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

if ($existing) {
    // Link existing user to this doctor
    $patient_id = (int)$existing['id'];

    // Ensure doctor_patients table exists
    $conn->query("CREATE TABLE IF NOT EXISTS `doctor_patients` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `doctor_id` INT UNSIGNED NOT NULL,
      `patient_id` INT UNSIGNED NOT NULL,
      `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `added_via` ENUM('appointment','manual','abha') NOT NULL DEFAULT 'manual',
      `abha_fetched` TINYINT(1) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_link` (`doctor_id`,`patient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $link = $conn->prepare("INSERT IGNORE INTO doctor_patients (doctor_id,patient_id,added_via) VALUES (?,?,?)");
    $mode = ($abha_number || $abha_address) ? 'abha' : 'manual';
    $link->bind_param('iis', $doctor_id, $patient_id, $mode);
    $link->execute();

    echo json_encode(['success'=>true,'patient_id'=>$patient_id,'message'=>'Existing patient linked to your list']);
    exit;
}

// New patient — the patient's mobile must have been OTP-verified on the form
// (code sent to the patient's WhatsApp/email, read back to the doctor).
if (!otp_consume_token('patient', $mobile, $post['mobile_verify_token'] ?? ($raw['mobile_verify_token'] ?? ''))) {
    echo json_encode(['success'=>false,'error'=>'Patient mobile not verified. Send an OTP to the patient and enter the code before creating the record.']); exit;
}

// Generate a temporary password (patient can reset via OTP)
$temp_pass = bin2hex(random_bytes(8));
$hash      = password_hash($temp_pass, PASSWORD_BCRYPT, ['cost'=>12]);

// Insert new user
$ins = $conn->prepare("
    INSERT INTO users
      (name, email, mobile, password, gender, dob, blood_group,
       abha_id, abha_address, abha_linked, abha_verified,
       zip_code, city, state, address, mobile_verified, mobile_verified_at, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())
");

$abha_linked = ($abha_number || $abha_address) ? 1 : 0;
$ins->bind_param('ssssssssssiisss',
    $name, $email, $mobile, $hash,
    $gender, $dob, $blood_group,
    $abha_number, $abha_address, $abha_linked, $abha_verified,
    $pincode, $city, $state, $address
);

if (!$ins->execute()) {
    echo json_encode(['success'=>false,'error'=>'DB error: '.$conn->error]); exit;
}

$patient_id = (int)$conn->insert_id;

// Ensure doctor_patients table exists
$conn->query("CREATE TABLE IF NOT EXISTS `doctor_patients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` INT UNSIGNED NOT NULL,
  `patient_id` INT UNSIGNED NOT NULL,
  `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_via` ENUM('appointment','manual','abha') NOT NULL DEFAULT 'manual',
  `abha_fetched` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_link` (`doctor_id`,`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mode = ($abha_number || $abha_address) ? 'abha' : 'manual';
$link = $conn->prepare("INSERT IGNORE INTO doctor_patients (doctor_id,patient_id,added_via) VALUES (?,?,?)");
$link->bind_param('iis', $doctor_id, $patient_id, $mode);
$link->execute();

echo json_encode(['success'=>true,'patient_id'=>$patient_id,'message'=>'Patient created and added to your list']);
