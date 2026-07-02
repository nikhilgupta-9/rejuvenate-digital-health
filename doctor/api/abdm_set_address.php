<?php
/**
 * ABDM Enrollment Step 4 — Confirm ABHA address + save patient to DB.
 *
 * POST body (JSON): { "abhaAddress": "firstname.lastname@sbx" }
 * Returns: { "success": true, "abhaNumber": "...", "abhaAddress": "...", "patient_id": N }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: POST https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/abha-address
 *   Body: { "txnId": "...", "abhaAddress": "...", "preferred": 1 }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// $conn is set by config/connect.php, which abdm_rsa.php loads automatically

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';

/* ── Auth check ── */
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Input ── */
$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$abhaAddress = trim($input['abhaAddress'] ?? '');
$txnId       = $_SESSION['abdm_txnId']   ?? '';
$xToken      = $_SESSION['abdm_x_token'] ?? '';
$doctorId    = (int)$_SESSION['doctor_id'];

if (!$abhaAddress) {
    echo json_encode(['success' => false, 'error' => 'ABHA address is required']);
    exit;
}
if (!$txnId || !$xToken) {
    echo json_encode(['success' => false, 'error' => 'Session expired. Please restart enrollment.']);
    exit;
}

// Validate ABHA address format (alphanumeric + dots + underscores + @sbx or @abdm)
if (!preg_match('/^[a-zA-Z0-9._-]{3,}@(sbx|abdm)$/', $abhaAddress)) {
    echo json_encode(['success' => false, 'error' => 'Invalid ABHA address format (e.g. firstname.lastname@sbx)']);
    exit;
}

try {
    $accessToken = abdm_get_access_token();

    /* ── Step 4: Set ABHA address ── */
    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/abha-address';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];
    $body = [
        'txnId'       => $txnId,
        'abhaAddress' => $abhaAddress,
        'preferred'   => 1,
    ];

    abdm_log('Setting ABHA address', ['txnId' => $txnId, 'address' => $abhaAddress]);
    [$res, $http] = abdm_curl('POST', $url, $headers, $body, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'Failed to set ABHA address');
        abdm_log('Set address failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    /* ── Extract confirmed address and profile from response ── */
    $confirmedAddress = $res['abhaAddress']
        ?? $res['preferredAbhaAddress']
        ?? $abhaAddress;

    $abhaNumber  = $res['ABHANumber']      ?? ($res['abhaNumber']      ?? '');
    $name        = $res['name']            ?? ($res['fullName']        ?? '');
    $mobile      = preg_replace('/\D/', '', $res['mobile'] ?? ($_SESSION['abdm_mobile'] ?? ''));
    $email       = $res['email']           ?? '';
    $gender      = $res['gender']          ?? '';
    $dob         = $res['dob']             ?? ($res['birthdate']       ?? '');
    $photo       = $res['profilePhoto']    ?? '';

    if ($gender === 'M') $gender = 'Male';
    elseif ($gender === 'F') $gender = 'Female';

    // Convert DOB to DB format (ABDM sends DD-MM-YYYY)
    $dobDb = null;
    if ($dob) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob, $m))       $dobDb = "$m[3]-$m[2]-$m[1]";
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob))              $dobDb = $dob;
        elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dob, $m)) $dobDb = "$m[3]-$m[2]-$m[1]";
    }

    abdm_log('ABHA address confirmed', ['abhaNumber' => $abhaNumber, 'address' => $confirmedAddress]);

    /* ── Save to database ── */
    $patientId = 0;
    $isNewUser = false;

    if (isset($conn) && $conn instanceof mysqli) {
        $existing = null;

        // Look up by ABHA number → mobile → email
        if ($abhaNumber) {
            $s = $conn->prepare("SELECT id FROM users WHERE abha_id=? LIMIT 1");
            $s->bind_param('s', $abhaNumber);
            $s->execute();
            $existing = $s->get_result()->fetch_assoc();
            $s->close();
        }
        if (!$existing && $mobile) {
            $s = $conn->prepare("SELECT id FROM users WHERE mobile=? LIMIT 1");
            $s->bind_param('s', $mobile);
            $s->execute();
            $existing = $s->get_result()->fetch_assoc();
            $s->close();
        }
        if (!$existing && $email) {
            $s = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $s->bind_param('s', $email);
            $s->execute();
            $existing = $s->get_result()->fetch_assoc();
            $s->close();
        }

        if ($existing) {
            $patientId = (int)$existing['id'];
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
            $upd->bind_param('sssssi', $abhaNumber, $confirmedAddress, $name, $gender, $dobDb, $patientId);
            $upd->execute();
            $upd->close();

        } else {
            $isNewUser = true;
            if ($mobile) {
                $tempPass = bin2hex(random_bytes(8));
                $hash     = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);

                $ins = $conn->prepare("
                    INSERT INTO users
                      (name, email, mobile, password, gender, dob,
                       abha_id, abha_address, abha_linked, abha_verified, created_at)
                    VALUES (?,?,?,?,?,?,?,?,1,1,NOW())
                ");
                $ins->bind_param('ssssssss',
                    $name, $email, $mobile, $hash,
                    $gender, $dobDb, $abhaNumber, $confirmedAddress
                );
                if ($ins->execute()) {
                    $patientId = (int)$conn->insert_id;
                } else {
                    abdm_log('DB insert failed: ' . $conn->error);
                }
                $ins->close();
            }
        }

        // Link patient to this doctor
        if ($patientId) {
            // Ensure doctor_patients table exists
            $conn->query("CREATE TABLE IF NOT EXISTS `doctor_patients` (
              `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `doctor_id`    INT UNSIGNED NOT NULL,
              `patient_id`   INT UNSIGNED NOT NULL,
              `added_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `added_via`    ENUM('appointment','manual','abha') NOT NULL DEFAULT 'manual',
              `abha_fetched` TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_link` (`doctor_id`,`patient_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $lnk = $conn->prepare("
                INSERT INTO doctor_patients (doctor_id, patient_id, added_via, abha_fetched)
                VALUES (?, ?, 'abha', 1)
                ON DUPLICATE KEY UPDATE added_via='abha', abha_fetched=1
            ");
            $lnk->bind_param('ii', $doctorId, $patientId);
            $lnk->execute();
            $lnk->close();
        }
    } else {
        abdm_log('DB connection not available in abdm_set_address.php');
    }

    /* ── Clear sensitive session state ── */
    unset(
        $_SESSION['abdm_txnId'],
        $_SESSION['abdm_x_token'],
        $_SESSION['abdm_mobile']
    );

    echo json_encode([
        'success'     => true,
        'abhaNumber'  => $abhaNumber,
        'abhaAddress' => $confirmedAddress,
        'name'        => $name,
        'patient_id'  => $patientId,
        'is_new'      => $isNewUser,
        'message'     => $isNewUser
            ? 'ABHA created and patient registered'
            : 'ABHA created and existing patient updated',
    ]);

} catch (Exception $e) {
    abdm_log('abdm_set_address exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
