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
require_once dirname(dirname(__DIR__)) . '/lib/Abha.php';

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

// $real_email is the address we can actually reach the patient at (null when
// none was given). users.email is NOT NULL + UNIQUE, so when there's no email
// we still store a unique, obviously-synthetic placeholder to keep the row
// valid — and skip the welcome email for that patient.
$real_email = ($email !== null && $email !== '') ? $email : null;

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

    // doctor_patients schema: see database/migration_doctor_abha.sql
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

// Generate a temporary password — sent to the patient over WhatsApp + email
// below so they can sign in and then change it. Keep it short and free of
// look-alike characters (0/O, 1/l/I) since the patient types it by hand.
$_pw_alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
$temp_pass = '';
for ($i = 0; $i < 8; $i++) {
    $temp_pass .= $_pw_alphabet[random_int(0, strlen($_pw_alphabet) - 1)];
}
$hash = password_hash($temp_pass, PASSWORD_BCRYPT, ['cost'=>12]);

// No real email? store a unique synthetic placeholder (users.email is NOT NULL
// + UNIQUE). The patient signs in with their mobile number instead.
$email = $real_email ?? ('noemail.' . $mobile . '@patients.rejuvenatedigitalhealth.com');

// The doctor created this account on the patient's behalf and already verified
// the mobile by OTP, so mark it usable straight away — otherwise process-login
// bounces the patient into an email-OTP loop on first sign-in (and a no-email
// patient, who signs in by mobile, could never clear that loop at all).
$email_verified = 1;

// Insert new user (ABHA identity is stored separately, in abha_accounts)
$ins = $conn->prepare("
    INSERT INTO users
      (name, email, mobile, password, gender, dob, blood_group,
       zip_code, city, state, address, mobile_verified, mobile_verified_at, email_verified, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW(),?,NOW())
");

$ins->bind_param('sssssssssssi',
    $name, $email, $mobile, $hash,
    $gender, $dob, $blood_group,
    $pincode, $city, $state, $address, $email_verified
);

if (!$ins->execute()) {
    error_log('[create-patient-submit] DB error: ' . $conn->error);
    echo json_encode(['success'=>false,'error'=>'Could not create the patient record. Please try again.']); exit;
}

$patient_id = (int)$conn->insert_id;

// ABHA identity -> abha_accounts (authoritative; mirrors legacy users.abha_* cols)
if ($abha_number !== '' || $abha_address !== '') {
    Abha::save($conn, 'patient', $patient_id, [
        'abha_number'  => $abha_number ?: null,
        'abha_address' => $abha_address ?: null,
        'linked'       => 1,
        'verified'     => $abha_verified,
        'source'       => 'doctor_added',
    ]);
}

// doctor_patients schema: see database/migration_doctor_abha.sql
$mode = ($abha_number || $abha_address) ? 'abha' : 'manual';
$link = $conn->prepare("INSERT IGNORE INTO doctor_patients (doctor_id,patient_id,added_via) VALUES (?,?,?)");
$link->bind_param('iis', $doctor_id, $patient_id, $mode);
$link->execute();

// ── Send the patient their login details — WhatsApp + email, best effort ──
$login_url = rtrim(BASE_URL, '/') . '/login.php';
$notify    = ['whatsapp' => false, 'email' => false];

$dn = $conn->prepare("SELECT name FROM doctors WHERE id=? LIMIT 1");
$dn->bind_param('i', $doctor_id);
$dn->execute();
$doctor_name = (string)($dn->get_result()->fetch_assoc()['name'] ?? '');

try {
    $wa = wa_send_account_credentials($mobile, $name ?: 'Patient', $login_url, $real_email ?: $mobile, $temp_pass);
    $notify['whatsapp'] = !empty($wa['ok']);
} catch (\Throwable $e) {
    error_log('[create-patient-submit] WhatsApp welcome failed: ' . $e->getMessage());
}

if ($real_email) {
    try {
        $notify['email'] = send_patient_account_email($real_email, $name ?: 'Patient', $mobile, $temp_pass, $doctor_name);
    } catch (\Throwable $e) {
        error_log('[create-patient-submit] welcome email failed: ' . $e->getMessage());
    }
}

echo json_encode([
    'success'    => true,
    'patient_id' => $patient_id,
    'message'    => 'Patient created and added to your list',
    'notify'     => $notify,
]);
