<?php
/**
 * ABDM Milestone 3 — Scan and Share Patient Profile Receiver.
 *
 * Public endpoint:
 *   POST /api/v3/hip/patient/share
 *
 * When a patient scans the facility's Counter QR code using their ABHA app,
 * the app / ABDM gateway pushes their verified demographic profile here.
 * This endpoint:
 *   1. Generates an OPD counter token (e.g. RJ-001)
 *   2. Saves / updates patient record in users and abha_accounts
 *   3. Enqueues the patient in abdm_scan_share_tokens for reception / OPD
 *   4. Returns token and status
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/lib/Abha.php';

header('Content-Type: application/json');

$raw = AbdmWebhookGuard::pass('scan_share', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload = json_decode($raw, true);

try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'scan-share', $raw, 'scan_share');
} catch (Throwable $e) {
    error_log('[abdm-scan-share] logWebhook error: ' . $e->getMessage());
}

if (!is_array($payload)) {
    echo json_encode(['status' => 'ERROR', 'error' => ['code' => 'ABDM-400', 'message' => 'Invalid JSON payload']]);
    exit;
}

$logger = new AuditLogger($conn);

try {
    $prof = $payload['profile']['patient'] ?? $payload['patient'] ?? [];
    $hipId = trim((string)($payload['metaData']['hipId'] ?? defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : ''));
    $context = trim((string)($payload['metaData']['context'] ?? 'OPD'));

    $name = trim((string)($prof['name'] ?? ''));
    $gender = trim((string)($prof['gender'] ?? ''));
    $abhaNumber = trim((string)($prof['healthIdNumber'] ?? $prof['abhaNumber'] ?? ''));
    $abhaAddress = trim((string)($prof['healthId'] ?? $prof['abhaAddress'] ?? ''));
    $phone = preg_replace('/\D/', '', (string)($prof['phoneNumber'] ?? ''));

    // Construct DOB
    $yob = $prof['yearOfBirth'] ?? null;
    $mob = $prof['monthOfBirth'] ?? null;
    $dobDay = $prof['dayOfBirth'] ?? null;
    $dob = null;
    if ($yob && $mob && $dobDay) {
        $dob = sprintf('%04d-%02d-%02d', $yob, $mob, $dobDay);
    } elseif ($yob) {
        $dob = sprintf('%04d-01-01', $yob);
    }

    // Address string
    $addrObj = $prof['address'] ?? [];
    $addressParts = [];
    if (!empty($addrObj['line']))     $addressParts[] = $addrObj['line'];
    if (!empty($addrObj['district'])) $addressParts[] = $addrObj['district'];
    if (!empty($addrObj['state']))    $addressParts[] = $addrObj['state'];
    if (!empty($addrObj['pincode']))  $addressParts[] = $addrObj['pincode'];
    $fullAddress = implode(', ', $addressParts);

    // Generate Token Number for Today
    $cntStmt = $conn->prepare("SELECT COUNT(*) FROM abdm_scan_share_tokens WHERE DATE(created_at) = CURDATE()");
    $cntStmt->execute();
    $todayCount = (int)$cntStmt->get_result()->fetch_row()[0];
    $cntStmt->close();

    $tokenNumber = 'RJ-' . str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);

    // Insert into abdm_scan_share_tokens
    $ins = $conn->prepare("
        INSERT INTO abdm_scan_share_tokens
          (token_number, hip_id, context_type, abha_number, abha_address, patient_name, gender, dob, phone, address, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting')
    ");
    $ins->bind_param('ssssssssss', $tokenNumber, $hipId, $context, $abhaNumber, $abhaAddress, $name, $gender, $dob, $phone, $fullAddress);
    $ins->execute();
    $tokenId = (int)$conn->insert_id;
    $ins->close();

    // Auto-create or resolve user in users and abha_accounts
    if ($name !== '') {
        $existing = null;
        if ($abhaNumber) {
            $uStmt = $conn->prepare("SELECT id FROM users WHERE abha_id = ? LIMIT 1");
            $uStmt->bind_param('s', $abhaNumber);
            $uStmt->execute();
            $existing = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
        }
        if (!$existing && $phone) {
            $uStmt = $conn->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            $uStmt->bind_param('s', $phone);
            $uStmt->execute();
            $existing = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
        }

        $userId = 0;
        if ($existing) {
            $userId = (int)$existing['id'];
            $upd = $conn->prepare("UPDATE users SET abha_id = COALESCE(NULLIF(abha_id, ''), ?), abha_address = COALESCE(NULLIF(abha_address, ''), ?), abha_linked = 1, abha_verified = 1 WHERE id = ?");
            $upd->bind_param('ssi', $abhaNumber, $abhaAddress, $userId);
            $upd->execute();
            $upd->close();
        } else {
            // Create user
            $tempPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
            $cUser = $conn->prepare("INSERT INTO users (name, phone, gender, dob, address, abha_id, abha_address, abha_linked, abha_verified, password) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?)");
            $cUser->bind_param('ssssssss', $name, $phone, $gender, $dob, $fullAddress, $abhaNumber, $abhaAddress, $tempPass);
            $cUser->execute();
            $userId = (int)$conn->insert_id;
            $cUser->close();
        }

        if ($userId && $abhaNumber) {
            Abha::save($conn, 'patient', $userId, [
                'abha_number'  => $abhaNumber,
                'abha_address' => $abhaAddress,
                'linked'       => 1,
                'verified'     => 1,
                'source'       => 'scan_and_share',
            ]);
        }
    }

    if (isset($logId)) {
        HipLinking::markWebhookProcessed($conn, $logId);
    }

    $logger->logAudit(0, 'ABDM_SCAN_SHARE', "Patient {$name} scanned and checked in with token {$tokenNumber}");

    echo json_encode([
        'status' => 'SUCCESS',
        'token'  => $tokenNumber,
        'patient' => [
            'healthId' => $abhaAddress,
            'token'    => $tokenNumber,
            'name'     => $name,
        ]
    ]);
    exit;

} catch (Throwable $e) {
    error_log('[abdm-scan-share] error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'ERROR',
        'error'  => [
            'code'    => 'ABDM-500',
            'message' => 'Internal processing error'
        ]
    ]);
    exit;
}
