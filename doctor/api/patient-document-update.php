<?php
/**
 * Rename / re-describe an existing patient document.
 * POST JSON: { doc_id, document_name, description }
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$doc_id = (int)($body['doc_id'] ?? 0);
$document_name = trim($body['document_name'] ?? '');
$description = trim($body['description'] ?? '');

if (!$doc_id || $document_name === '') {
    echo json_encode(['success' => false, 'error' => 'doc_id and document_name are required']); exit;
}

$chk = $conn->prepare("SELECT id FROM patient_documents WHERE id=? AND doctor_id=? LIMIT 1");
$chk->bind_param('ii', $doc_id, $doctor_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'error' => 'Document not found']); exit;
}

$upd = $conn->prepare("UPDATE patient_documents SET document_name=?, description=? WHERE id=? AND doctor_id=?");
$upd->bind_param('ssii', $document_name, $description, $doc_id, $doctor_id);

if (!$upd->execute()) {
    error_log('[patient-document-update] DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Could not update the document. Please try again.']); exit;
}

echo json_encode(['success' => true, 'message' => 'Document updated']);
