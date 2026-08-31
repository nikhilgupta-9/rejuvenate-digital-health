<?php
/**
 * Upload a document for a patient linked to the authenticated doctor.
 * POST multipart/form-data: patient_id, document_type, description, document_file
 * Returns { success, doc: {id, document_name, description, file_path, file_type, uploaded_at} }
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$patient_id = (int)($_POST['patient_id'] ?? 0);
if (!$patient_id) {
    echo json_encode(['success' => false, 'error' => 'patient_id is required']); exit;
}

// Verify doctor has access to this patient (same check as patient-profile.php)
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

if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Please select a file to upload']); exit;
}

$file = $_FILES['document_file'];
$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_size = 10 * 1024 * 1024; // 10MB

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, DOC, DOCX, JPG, PNG allowed.']); exit;
}
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max size is 10MB.']); exit;
}

$docType     = trim($_POST['document_type'] ?? 'Other');
$description = trim($_POST['description'] ?? '');
$origName    = trim($file['name']) ?: 'document';
$documentName = $docType . ' — ' . $origName;

// Optional: tie this report to a specific visit (from doctor/patient-form.php).
$appointmentId = (int)($_POST['appointment_id'] ?? 0) ?: null;
if ($appointmentId) {
    $av = $conn->prepare("SELECT 1 FROM appointments WHERE id=? AND doctor_id=? AND user_id=? LIMIT 1");
    $av->bind_param('iii', $appointmentId, $doctor_id, $patient_id);
    $av->execute();
    if (!$av->get_result()->fetch_row()) $appointmentId = null;
}

$uploadDir = dirname(dirname(__DIR__)) . '/uploads/patient_documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newFileName = 'patient_' . $patient_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath    = $uploadDir . $newFileName;
$dbPath      = 'uploads/patient_documents/' . $newFileName; // relative to project root, matches BASE_URL usage

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']); exit;
}

$fileTypeMime = $file['type'] ?: $ext;

$ins = $conn->prepare("
    INSERT INTO patient_documents (patient_id, doctor_id, appointment_id, document_name, document_type, description, file_path, file_type, uploaded_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$ins->bind_param('iiisssss', $patient_id, $doctor_id, $appointmentId, $documentName, $docType, $description, $dbPath, $fileTypeMime);

if (!$ins->execute()) {
    @unlink($destPath);
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]); exit;
}

echo json_encode([
    'success' => true,
    'doc' => [
        'id'             => (int)$conn->insert_id,
        'document_name'  => $documentName,
        'document_type'  => $docType,
        'description'    => $description,
        'file_path'      => $dbPath,
        'file_type'      => $fileTypeMime,
        'appointment_id' => $appointmentId,
        'uploaded_at'    => date('Y-m-d H:i:s'),
    ],
]);
