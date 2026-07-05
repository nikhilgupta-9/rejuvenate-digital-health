<?php
/**
 * Email a patient document's link to a recipient.
 * POST JSON: { doc_id, recipient_email? } — defaults to the patient's own email on file.
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/util/mail_config.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$doctor_name = $payload['name'] ?? 'Your Doctor';

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$doc_id = (int)($body['doc_id'] ?? 0);
$recipientEmail = trim($body['recipient_email'] ?? '');

if (!$doc_id) {
    echo json_encode(['success' => false, 'error' => 'doc_id is required']); exit;
}

$stmt = $conn->prepare("
    SELECT pd.document_name, pd.file_path, u.id AS patient_id, u.name AS patient_name, u.email AS patient_email
    FROM patient_documents pd
    JOIN users u ON u.id = pd.patient_id
    WHERE pd.id=? AND pd.doctor_id=?
    LIMIT 1
");
$stmt->bind_param('ii', $doc_id, $doctor_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
if (!$doc) {
    echo json_encode(['success' => false, 'error' => 'Document not found']); exit;
}

if ($recipientEmail === '') {
    $recipientEmail = $doc['patient_email'] ?? '';
}
if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'No valid recipient email available. Please provide one.']); exit;
}

$docLink = rtrim(BASE_URL, '/') . '/' . ltrim($doc['file_path'], '/');
$patientName = $doc['patient_name'] ?: 'Patient';

$subject = 'Your health document from Dr. ' . $doctor_name;
$bodyHtml = "
    <p>Hello " . htmlspecialchars($patientName) . ",</p>
    <p>Dr. " . htmlspecialchars($doctor_name) . " has shared a health document with you: <strong>" . htmlspecialchars($doc['document_name']) . "</strong>.</p>
    <p><a href='" . htmlspecialchars($docLink) . "' style='display:inline-block;background:#0C74C5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>View Document</a></p>
    <p>If the button doesn't work, copy this link into your browser:<br>" . htmlspecialchars($docLink) . "</p>
";
$bodyText = "Dr. {$doctor_name} shared a health document with you: {$doc['document_name']}\n\nView it here: {$docLink}";

$mailer = new Mailer();
$sent = $mailer->sendCustom($recipientEmail, $patientName, $subject, $bodyHtml, $bodyText);

if (!$sent) {
    echo json_encode(['success' => false, 'error' => 'Failed to send email. Please check SMTP configuration.']); exit;
}

echo json_encode(['success' => true, 'message' => 'Document link emailed to ' . $recipientEmail]);
