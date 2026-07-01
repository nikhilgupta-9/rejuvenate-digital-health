<?php
/**
 * ABHA Enrollment Step 3 — Confirm ABHA address + save patient to DB.
 * POST { txnId, xToken, chosen_address, profile:{...} }
 * Returns { success, patient_id, is_new, message }
 */
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/config/abdm.php';
require_once dirname(dirname(__DIR__)) . '/lib/AbdmApi.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

if (!ABDM_CONFIGURED) {
    echo json_encode(['success'=>false,'error'=>'ABDM not configured']); exit;
}

$body           = json_decode(file_get_contents('php://input'), true) ?? [];
$txnId          = trim($body['txnId']          ?? '');
$xToken         = trim($body['xToken']         ?? '');
$chosen_address = trim($body['chosen_address'] ?? '');
$profile        = $body['profile']             ?? [];
$is_new_abha    = (bool)($body['is_new_abha']  ?? true);

if (!$xToken || !$profile) {
    echo json_encode(['success'=>false,'error'=>'Missing required data']); exit;
}

try {
    $api = new AbdmApi();

    // If new ABHA and address chosen → confirm on ABDM
    if ($is_new_abha && $chosen_address && $txnId) {
        $addrRes = $api->setEnrollmentAbhaAddress($txnId, $chosen_address, $xToken);
        // Update address in profile if ABDM returns the confirmed one
        if (!empty($addrRes['preferredAbhaAddress'])) {
            $profile['abha_address'] = $addrRes['preferredAbhaAddress'];
        } elseif (!empty($addrRes['ABHAAddress'])) {
            $profile['abha_address'] = $addrRes['ABHAAddress'];
        } else {
            $profile['abha_address'] = $chosen_address.(strpos($chosen_address,'@')===false?'@abdm':'');
        }
    }

    // Normalise fields from profile
    $abha_number  = $profile['abha_number']  ?? '';
    $abha_address = $profile['abha_address'] ?? '';
    $full_name    = trim(($profile['first_name']??'').' '.($profile['middle_name']??'').' '.($profile['last_name']??''));
    if (!$full_name) $full_name = $profile['name'] ?? '';
    $mobile       = preg_replace('/\D/', '', $profile['mobile'] ?? '');
    $email        = $profile['email']        ?? '';
    $gender       = $profile['gender']       ?? '';
    $dob_raw      = $profile['dob']          ?? '';

    // Convert DOB to DB format
    $dob_db = null;
    if ($dob_raw) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob_raw, $m))      $dob_db = $m[3].'-'.$m[2].'-'.$m[1];
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob_raw))             $dob_db = $dob_raw;
        elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dob_raw, $m)) $dob_db = $m[3].'-'.$m[2].'-'.$m[1];
    }

    /* ── Find or create patient ── */
    $patient_id = 0;
    $is_new     = false;
    $existing   = null;

    // Match by ABHA number → mobile → email (in order of reliability)
    if ($abha_number) {
        $s = $conn->prepare("SELECT id FROM users WHERE abha_id=? LIMIT 1");
        $s->bind_param('s', $abha_number); $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }
    if (!$existing && $mobile) {
        $s = $conn->prepare("SELECT id FROM users WHERE mobile=? LIMIT 1");
        $s->bind_param('s', $mobile); $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }
    if (!$existing && $email) {
        $s = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $s->bind_param('s', $email); $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }

    if ($existing) {
        $patient_id = (int)$existing['id'];
        // Update ABHA fields + fill any missing basics
        $upd = $conn->prepare("
            UPDATE users SET
              abha_id       = ?,
              abha_address  = ?,
              abha_linked   = 1,
              abha_verified = 1,
              name          = CASE WHEN name='' OR name IS NULL THEN ? ELSE name END,
              gender        = CASE WHEN gender='' OR gender IS NULL THEN ? ELSE gender END,
              dob           = CASE WHEN dob IS NULL THEN ? ELSE dob END
            WHERE id = ?
        ");
        $upd->bind_param('sssssi', $abha_number, $abha_address, $full_name, $gender, $dob_db, $patient_id);
        $upd->execute();

    } else {
        $is_new = true;
        if (!$mobile) {
            echo json_encode(['success'=>false,'error'=>'ABDM profile has no mobile number. Please fill it in manually.']); exit;
        }
        $temp  = bin2hex(random_bytes(8));
        $hash  = password_hash($temp, PASSWORD_BCRYPT, ['cost'=>12]);

        $ins = $conn->prepare("
            INSERT INTO users
              (name, email, mobile, password, gender, dob,
               abha_id, abha_address, abha_linked, abha_verified, created_at)
            VALUES (?,?,?,?,?,?,?,?,1,1,NOW())
        ");
        $ins->bind_param('ssssssss',
            $full_name, $email, $mobile, $hash,
            $gender, $dob_db, $abha_number, $abha_address
        );
        if (!$ins->execute()) {
            echo json_encode(['success'=>false,'error'=>'DB error: '.$conn->error]); exit;
        }
        $patient_id = (int)$conn->insert_id;
    }

    /* ── Link to doctor ── */
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

    $lnk = $conn->prepare("
        INSERT INTO doctor_patients (doctor_id, patient_id, added_via, abha_fetched) VALUES (?,?,'abha',1)
        ON DUPLICATE KEY UPDATE added_via='abha', abha_fetched=1
    ");
    $lnk->bind_param('ii', $doctor_id, $patient_id);
    $lnk->execute();

    echo json_encode([
        'success'    => true,
        'is_new'     => $is_new,
        'patient_id' => $patient_id,
        'abha_number'  => $abha_number,
        'abha_address' => $abha_address,
        'message'    => $is_new
            ? 'ABHA created, patient registered and added to your panel'
            : 'ABHA created, existing patient record updated and linked',
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
