<?php
/**
 * Step 3 — Verify OTP, fetch full ABHA profile, create/update patient in DB.
 * POST { txnId, otp, abha_input, type }
 * Returns { success, is_new, patient_id, profile }
 *
 * ABDM flow: confirmAuth → POST /profile/login/verify  → returns X-token
 *            getProfile(xToken)                         → full ABHAProfile
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

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$txnId  = trim($body['txnId'] ?? '');
$otp    = trim($body['otp']   ?? '');

if (!$txnId || !$otp) {
    echo json_encode(['success'=>false,'error'=>'txnId and OTP are required']); exit;
}
if (!ctype_digit($otp) || strlen($otp) < 4) {
    echo json_encode(['success'=>false,'error'=>'Invalid OTP format']); exit;
}

try {
    $api = new AbdmApi();

    /* ── Step A: verify OTP → get X-token ── */
    $authRes = $api->confirmAuth($otp, $txnId, ['abha-login']);

    if (!AbdmApi::wasSuccessful($authRes)) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($authRes,'OTP verification failed')]); exit;
    }

    // X-token can be in token or tokens.token
    $xToken = $authRes['token']
           ?? ($authRes['tokens']['token'] ?? '');

    if (!$xToken) {
        echo json_encode(['success'=>false,'error'=>'ABDM did not return a profile token. OTP may have expired — resend and try again.']); exit;
    }

    /* ── Step B: fetch full profile ── */
    $profileRes = $api->getProfile($xToken);

    if (!AbdmApi::wasSuccessful($profileRes)) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($profileRes,'Could not fetch profile')]); exit;
    }

    /* ── Normalise profile fields ── */
    $abha_number  = AbdmApi::formatAbhaNumber($profileRes['ABHANumber'] ?? '');
    $abha_address = $profileRes['preferredAbhaAddress']
                 ?? ($profileRes['ABHAAddress'] ?? '');
    $first_name   = trim($profileRes['firstName']  ?? '');
    $middle_name  = trim($profileRes['middleName'] ?? '');
    $last_name    = trim($profileRes['lastName']   ?? '');
    $full_name    = trim($first_name.' '.$middle_name.' '.$last_name);
    $mobile       = preg_replace('/\D/', '', $profileRes['mobile'] ?? '');
    $email        = $profileRes['email']       ?? '';
    $gender       = $profileRes['gender']      ?? '';
    $dob_raw      = $profileRes['dob']         ?? ($profileRes['birthdate'] ?? '');
    $state        = $profileRes['stateName']   ?? ($profileRes['state']     ?? '');
    $district     = $profileRes['districtName']?? ($profileRes['district']  ?? '');
    $pincode      = $profileRes['pinCode']     ?? '';
    $address      = $profileRes['address']     ?? '';
    $photo_b64    = $profileRes['profilePhoto']?? ($profileRes['photo']     ?? '');

    // Normalise DOB: ABDM may return DD-MM-YYYY or YYYY-MM-DD
    $dob_db = null;
    if ($dob_raw) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob_raw, $m)) {
            $dob_db = $m[3].'-'.$m[2].'-'.$m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob_raw)) {
            $dob_db = $dob_raw;
        }
    }

    // Normalise gender: M→Male, F→Female
    if ($gender === 'M') $gender = 'Male';
    elseif ($gender === 'F') $gender = 'Female';

    /* ── Step C: find or create user in DB ── */
    $is_new     = false;
    $patient_id = 0;

    // Try to match existing user by ABHA number first, then mobile, then email
    $existing = null;
    if ($abha_number) {
        $s = $conn->prepare("SELECT id FROM users WHERE abha_id=? LIMIT 1");
        $s->bind_param('s', $abha_number);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }
    if (!$existing && $mobile) {
        $s = $conn->prepare("SELECT id FROM users WHERE mobile=? LIMIT 1");
        $s->bind_param('s', $mobile);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }
    if (!$existing && $email) {
        $s = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $s->bind_param('s', $email);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }

    if ($existing) {
        /* UPDATE existing user with ABHA data from ABDM */
        $patient_id = (int)$existing['id'];
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
        /* CREATE new user */
        $is_new = true;
        if (!$mobile) {
            // Should not happen — getProfile should return mobile
            echo json_encode(['success'=>false,'error'=>'ABDM profile does not include mobile number. Cannot create patient.']); exit;
        }
        $temp_pass = bin2hex(random_bytes(8));
        $hash      = password_hash($temp_pass, PASSWORD_BCRYPT, ['cost'=>12]);

        $ins = $conn->prepare("
            INSERT INTO users
              (name, email, mobile, password, gender, dob,
               abha_id, abha_address, abha_linked, abha_verified,
               zip_code, city, state, address, created_at)
            VALUES (?,?,?,?,?,?,?,?,1,1,?,?,?,?,NOW())
        ");
        $ins->bind_param('ssssssssssss',
            $full_name, $email, $mobile, $hash,
            $gender, $dob_db,
            $abha_number, $abha_address,
            $pincode, $district, $state, $address
        );
        if (!$ins->execute()) {
            echo json_encode(['success'=>false,'error'=>'DB error: '.$conn->error]); exit;
        }
        $patient_id = (int)$conn->insert_id;
    }

    /* ── Step D: link to this doctor ── */
    $conn->query("CREATE TABLE IF NOT EXISTS `doctor_patients` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `doctor_id`   INT UNSIGNED NOT NULL,
      `patient_id`  INT UNSIGNED NOT NULL,
      `added_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `added_via`   ENUM('appointment','manual','abha') NOT NULL DEFAULT 'manual',
      `abha_fetched` TINYINT(1) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_link` (`doctor_id`,`patient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $lnk = $conn->prepare("INSERT INTO doctor_patients (doctor_id,patient_id,added_via,abha_fetched) VALUES (?,?,'abha',1)
                           ON DUPLICATE KEY UPDATE added_via='abha', abha_fetched=1");
    $lnk->bind_param('ii', $doctor_id, $patient_id);
    $lnk->execute();

    echo json_encode([
        'success'    => true,
        'is_new'     => $is_new,
        'patient_id' => $patient_id,
        'message'    => $is_new ? 'Patient created from ABDM and linked to your panel' : 'Existing patient updated with ABHA data and linked',
        'profile'    => [
            'name'         => $full_name,
            'abha_number'  => $abha_number,
            'abha_address' => $abha_address,
            'mobile'       => $mobile,
            'email'        => $email,
            'gender'       => $gender,
            'dob'          => $dob_raw,
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
