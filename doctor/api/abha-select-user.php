<?php
/**
 * Step 3 (mobile login only) — Select ABHA when multiple are linked to one mobile.
 * POST { txnId, t_token, abha_number }
 * Returns { success, patient_id, profile }
 *
 * ABDM: POST /profile/login/verify/user (T-Token header required)
 *       → returns X-token → getProfile → upsert DB → link to doctor
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

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$txnId       = trim($body['txnId']       ?? '');
$tToken      = trim($body['t_token']     ?? '');
$abha_number = trim($body['abha_number'] ?? '');

if (!$txnId || !$tToken || !$abha_number) {
    echo json_encode(['success'=>false,'error'=>'txnId, t_token and abha_number are required']); exit;
}

try {
    $api = new AbdmApi();

    /* ── Step 3: exchange T-token + selected ABHANumber → X-token ── */
    $selectRes = $api->verifyUserLogin($txnId, AbdmApi::formatAbhaNumber($abha_number), $tToken);

    if (!AbdmApi::wasSuccessful($selectRes)) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($selectRes,'Could not select ABHA account')]); exit;
    }

    $xToken = $selectRes['token'] ?? ($selectRes['tokens']['token'] ?? '');
    if (!$xToken) {
        echo json_encode(['success'=>false,'error'=>'ABDM did not return a profile token after selection.']); exit;
    }

    /* ── Fetch full profile ── */
    $profileRes = $api->getProfile($xToken);
    if (!AbdmApi::wasSuccessful($profileRes)) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($profileRes,'Could not fetch ABHA profile')]); exit;
    }

    /* ── Normalise profile ── */
    $abha_num     = AbdmApi::formatAbhaNumber($profileRes['ABHANumber'] ?? '');
    $abha_address = $profileRes['preferredAbhaAddress'] ?? ($profileRes['ABHAAddress'] ?? '');
    $first_name   = trim($profileRes['firstName']  ?? '');
    $middle_name  = trim($profileRes['middleName'] ?? '');
    $last_name    = trim($profileRes['lastName']   ?? '');
    $full_name    = trim($first_name.' '.$middle_name.' '.$last_name);
    $mobile       = preg_replace('/\D/', '', $profileRes['mobile'] ?? '');
    $email        = $profileRes['email']        ?? '';
    $gender       = $profileRes['gender']       ?? '';
    $dob_raw      = $profileRes['dob']          ?? ($profileRes['birthdate'] ?? '');
    $state        = $profileRes['stateName']    ?? ($profileRes['state']     ?? '');
    $district     = $profileRes['districtName'] ?? ($profileRes['district']  ?? '');
    $pincode      = $profileRes['pinCode']      ?? '';
    $address      = $profileRes['address']      ?? '';

    $dob_db = null;
    if ($dob_raw) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob_raw, $m)) {
            $dob_db = $m[3].'-'.$m[2].'-'.$m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob_raw)) {
            $dob_db = $dob_raw;
        }
    }
    if ($gender === 'M') $gender = 'Male';
    elseif ($gender === 'F') $gender = 'Female';

    /* ── Upsert patient in DB ── */
    $is_new   = false;
    $patient_id = 0;
    $existing = null;

    if ($abha_num) {
        $s = $conn->prepare("SELECT id FROM users WHERE abha_id=? LIMIT 1");
        $s->bind_param('s', $abha_num);
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
        $upd->bind_param('sssssi', $abha_num, $abha_address, $full_name, $gender, $dob_db, $patient_id);
        $upd->execute();
    } else {
        $is_new = true;
        if (!$mobile) {
            echo json_encode(['success'=>false,'error'=>'ABDM profile has no mobile number.']); exit;
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
            $gender, $dob_db, $abha_num, $abha_address,
            $pincode, $district, $state, $address
        );
        if (!$ins->execute()) {
            echo json_encode(['success'=>false,'error'=>'DB error: '.$conn->error]); exit;
        }
        $patient_id = (int)$conn->insert_id;
    }

    /* ── Link to doctor ── */
    $lnk = $conn->prepare("INSERT INTO doctor_patients (doctor_id,patient_id,added_via,abha_fetched) VALUES (?,?,'abha',1)
                           ON DUPLICATE KEY UPDATE added_via='abha', abha_fetched=1");
    $lnk->bind_param('ii', $doctor_id, $patient_id);
    $lnk->execute();

    echo json_encode([
        'success'    => true,
        'is_new'     => $is_new,
        'patient_id' => $patient_id,
        'message'    => $is_new ? 'Patient created from ABDM and linked to your panel' : 'Patient linked with verified ABHA data',
        'profile'    => [
            'name'         => $full_name,
            'abha_number'  => $abha_num,
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
