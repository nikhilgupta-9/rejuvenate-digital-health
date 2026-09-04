<?php
/**
 * Add a patient to this doctor's list.
 * Modes:
 *   portal   → patient already exists in DB: link doctor_patients
 *   abha     → new patient via ABHA address: create/fetch user then link
 */
include_once(__DIR__ . "/../../config/connect.php");
require_once(__DIR__ . "/../auth/guard.php");

header('Content-Type: application/json');

$jwt = doctor_jwt_guard(true);
if (!$jwt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$doctor_id = (int)$jwt['sub'];

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$mode       = $body['mode'] ?? '';
$patient_id = isset($body['patient_id']) ? (int)$body['patient_id'] : 0;
$abha_input = trim($body['abha'] ?? '');

// ---- helper: ensure doctor_patients row ----
function link_patient(mysqli $conn, int $doctor_id, int $patient_id, string $via): array {
    $ins = $conn->prepare(
        "INSERT IGNORE INTO doctor_patients (doctor_id, patient_id, added_via) VALUES (?, ?, ?)"
    );
    $ins->bind_param('iis', $doctor_id, $patient_id, $via);
    $ins->execute();
    return ['success' => true, 'patient_id' => $patient_id];
}

// ---- Mode: portal (existing user) ----
if ($mode === 'portal' && $patient_id > 0) {
    $chk = $conn->prepare("SELECT id, name, last_name, abha_address FROM users WHERE id = ?");
    $chk->bind_param('i', $patient_id);
    $chk->execute();
    $user = $chk->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Patient not found']);
        exit;
    }

    $result = link_patient($conn, $doctor_id, $patient_id, 'manual');
    $result['name'] = trim($user['name'] . ' ' . $user['last_name']);
    echo json_encode($result);
    exit;
}

// ---- Mode: abha (new patient via ABHA) ----
if ($mode === 'abha' && $abha_input !== '') {
    // Normalise: strip @abdm suffix if user typed it, or keep full address
    // ABHA address looks like "name@abdm" or 14-digit number
    $is_number  = preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $abha_input) ||
                  preg_match('/^\d{14}$/', $abha_input);
    $is_address = preg_match('/^[a-zA-Z0-9._]+@abdm$/', $abha_input) ||
                  (!$is_number && !str_contains($abha_input, '@'));

    if (!$is_number && !$is_address && !preg_match('/^[a-zA-Z0-9._@]+$/', $abha_input)) {
        echo json_encode(['success' => false, 'error' => 'Invalid ABHA ID or address format']);
        exit;
    }

    // Check if user already in portal with this ABHA
    $col = $is_number ? 'abha_number' : 'abha_address';
    $find = $conn->prepare("SELECT id, name, last_name, abha_address, abha_number FROM users WHERE $col = ? LIMIT 1");
    $find->bind_param('s', $abha_input);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc();

    if ($existing) {
        $result = link_patient($conn, $doctor_id, $existing['id'], 'abha');
        $result['name'] = trim($existing['name'] . ' ' . $existing['last_name']);
        $result['source'] = 'portal';
        echo json_encode($result);
        exit;
    }

    // TODO: Real ABDM API call would go here to fetch patient profile
    // For now: create a placeholder user with abha info
    // In production, call AbdmApi::fetchPatientProfile($abha_input) and populate fields
    $abha_address = $is_address ? (str_contains($abha_input, '@') ? $abha_input : $abha_input . '@abdm') : null;
    $abha_number  = $is_number ? preg_replace('/\D/', '', $abha_input) : null;
    $placeholder_email = ($abha_address ?? ($abha_number . '@abha.gov.in'));
    $placeholder_name  = explode('@', $abha_address ?? 'abha_user')[0];
    $placeholder_pass  = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);

    $ins = $conn->prepare(
        "INSERT INTO users (name, email, password, abha_address, abha_number, abha_linked, abha_verified, created_at)
         VALUES (?, ?, ?, ?, ?, 1, 1, NOW())"
    );
    $ins->bind_param('sssss', $placeholder_name, $placeholder_email, $placeholder_pass, $abha_address, $abha_number);

    if (!$ins->execute()) {
        error_log('[patient-add] DB error: ' . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Could not create the patient record. Please try again.']);
        exit;
    }

    $new_patient_id = $conn->insert_id;
    $result = link_patient($conn, $doctor_id, $new_patient_id, 'abha');
    $result['name']   = $placeholder_name;
    $result['source'] = 'abha_new';
    $result['note']   = 'Patient added via ABHA. Profile will sync once they register on the portal.';
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
