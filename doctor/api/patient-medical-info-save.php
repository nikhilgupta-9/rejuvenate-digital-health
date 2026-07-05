<?php
/**
 * Save Allergies / Existing Condition / Current Medication / Medical History
 * for a patient linked to the authenticated doctor.
 * POST JSON: { patient_id, allergies, existing_condition, current_medication, medical_history }
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

$allergies         = trim($body['allergies'] ?? '');
$existingCondition = trim($body['existing_condition'] ?? '');
$currentMedication = trim($body['current_medication'] ?? '');
$medicalHistory    = trim($body['medical_history'] ?? '');

$upd = $conn->prepare("
    UPDATE users SET
        allergies = ?,
        existing_condition = ?,
        current_medication = ?,
        medical_history = ?
    WHERE id = ?
");
$upd->bind_param('ssssi', $allergies, $existingCondition, $currentMedication, $medicalHistory, $patient_id);

if (!$upd->execute()) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]); exit;
}

echo json_encode(['success' => true, 'message' => 'Medical information saved']);
