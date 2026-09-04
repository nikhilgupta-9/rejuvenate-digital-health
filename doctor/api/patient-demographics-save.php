<?php
/**
 * Save core demographics (Name / Gender / Blood Group / Email / Phone)
 * for a patient linked to the authenticated doctor.
 * POST JSON: { patient_id, name, gender, blood_group, email, mobile }
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$patient_id = (int)($body['patient_id'] ?? 0);
if (!$patient_id) {
    echo json_encode(['success' => false, 'error' => 'patient_id is required']); exit;
}

$hasAccess = false;
$chk = $conn->prepare("SELECT 1 FROM doctor_patients WHERE doctor_id=? AND patient_id=? LIMIT 1");
$chk->bind_param('ii', $doctor_id, $patient_id);
$chk->execute();
if ($chk->get_result()->fetch_row()) $hasAccess = true;

if (!$hasAccess) {
    $chk2 = $conn->prepare("SELECT 1 FROM appointments WHERE doctor_id=? AND user_id=? LIMIT 1");
    $chk2->bind_param('ii', $doctor_id, $patient_id);
    $chk2->execute();
    if ($chk2->get_result()->fetch_row()) $hasAccess = true;
}

if (!$hasAccess) {
    echo json_encode(['success' => false, 'error' => 'You do not have access to this patient']); exit;
}

$name        = trim($body['name'] ?? '');
$gender      = trim($body['gender'] ?? '');
$bloodGroup  = trim($body['blood_group'] ?? '');
$email       = trim($body['email'] ?? '');
$mobile      = preg_replace('/\D/', '', $body['mobile'] ?? '');

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Name is required']); exit;
}
if (!in_array($gender, ['Male', 'Female', 'Other', ''], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid gender']); exit;
}
if ($mobile !== '' && strlen($mobile) !== 10) {
    echo json_encode(['success' => false, 'error' => 'Mobile must be 10 digits']); exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']); exit;
}

$upd = $conn->prepare("
    UPDATE users SET
        name = ?,
        gender = ?,
        blood_group = ?,
        email = ?,
        mobile = ?
    WHERE id = ?
");
$upd->bind_param('sssssi', $name, $gender, $bloodGroup, $email, $mobile, $patient_id);

if (!$upd->execute()) {
    error_log('[patient-demographics-save] DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Could not save the profile. Please try again.']); exit;
}

echo json_encode(['success' => true, 'message' => 'Profile saved']);
