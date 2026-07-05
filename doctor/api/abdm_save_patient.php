<?php
/**
 * ABDM Find Patient — Step 5 (optional): Save fetched patient to DB.
 *
 * POST body (JSON): {} — reads cached profile from session
 * Returns: { "success": true, "patient_id": N, "is_new": true }
 *    or:   { "success": false, "error": "..." }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/abdm_rsa.php'; // loads config/connect.php + abdm.php

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

$profile  = $_SESSION['abdm_fetched_profile'] ?? null;
$doctorId = (int)$_SESSION['doctor_id'];

if (!$profile || empty($profile['ABHANumber'])) {
    echo json_encode(['success' => false, 'error' => 'No patient profile in session. Please search again.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    abdm_log('DB connection not available in abdm_save_patient.php');
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

$abhaNumber  = $profile['ABHANumber'];
$abhaAddress = $profile['abhaAddress'] ?? '';
$name        = $profile['name']        ?? '';
$gender      = $profile['gender']      ?? '';
$mobile      = $profile['mobile']      ?? '';
$email       = $profile['email']       ?? '';
$dob         = $profile['dob']         ?? '';

/* Normalise gender */
if ($gender === 'M') $gender = 'Male';
elseif ($gender === 'F') $gender = 'Female';

/* Convert DOB to DB format (ABDM may return DD-MM-YYYY or YYYY-MM-DD) */
$dobDb = null;
if ($dob) {
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob, $m))       $dobDb = "$m[3]-$m[2]-$m[1]";
    elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob))              $dobDb = $dob;
    elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dob, $m)) $dobDb = "$m[3]-$m[2]-$m[1]";
}

try {
    $patientId = 0;
    $isNew     = false;
    $existing  = null;

    /* Look up by ABHA number → mobile → email */
    $s = $conn->prepare("SELECT id FROM users WHERE abha_id=? LIMIT 1");
    $s->bind_param('s', $abhaNumber);
    $s->execute();
    $existing = $s->get_result()->fetch_assoc();
    $s->close();

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
        $upd->bind_param('sssssi', $abhaNumber, $abhaAddress, $name, $gender, $dobDb, $patientId);
        $upd->execute();
        $upd->close();
    } else {
        $isNew = true;
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
                $gender, $dobDb, $abhaNumber, $abhaAddress
            );
            if ($ins->execute()) {
                $patientId = (int)$conn->insert_id;
            } else {
                abdm_log('DB insert failed: ' . $conn->error);
                echo json_encode(['success' => false, 'error' => 'Failed to create patient record: ' . $conn->error]);
                $ins->close();
                exit;
            }
            $ins->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Cannot create patient: no mobile number in ABHA profile']);
            exit;
        }
    }

    /* Link patient to this doctor */
    if ($patientId) {
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

    /* Clear patient session state after successful save */
    unset(
        $_SESSION['abdm_patient_xtoken'],
        $_SESSION['abdm_fetched_profile'],
        $_SESSION['abdm_login_txnId'],
        $_SESSION['abdm_search_txnId']
    );

    abdm_log('Patient saved', ['patient_id' => $patientId, 'is_new' => $isNew]);
    echo json_encode([
        'success'    => true,
        'patient_id' => $patientId,
        'is_new'     => $isNew,
        'message'    => $isNew ? 'Patient registered from ABHA profile' : 'Existing patient updated and linked',
    ]);

} catch (Exception $e) {
    abdm_log('abdm_save_patient exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
