<?php
/**
 * Delete a patient document (DB row + underlying file).
 * POST JSON: { doc_id }
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$doc_id = (int)($body['doc_id'] ?? 0);

if (!$doc_id) {
    echo json_encode(['success' => false, 'error' => 'doc_id is required']); exit;
}

$chk = $conn->prepare("SELECT file_path FROM patient_documents WHERE id=? AND doctor_id=? LIMIT 1");
$chk->bind_param('ii', $doc_id, $doctor_id);
$chk->execute();
$doc = $chk->get_result()->fetch_assoc();
if (!$doc) {
    echo json_encode(['success' => false, 'error' => 'Document not found']); exit;
}

$del = $conn->prepare("DELETE FROM patient_documents WHERE id=? AND doctor_id=?");
$del->bind_param('ii', $doc_id, $doctor_id);

if (!$del->execute()) {
    error_log('[patient-document-delete] DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Could not delete the document. Please try again.']); exit;
}

if (!empty($doc['file_path'])) {
    $filePath = dirname(dirname(__DIR__)) . '/' . ltrim($doc['file_path'], '/.');
    if (file_exists($filePath)) @unlink($filePath);
}

echo json_encode(['success' => true, 'message' => 'Document removed']);
