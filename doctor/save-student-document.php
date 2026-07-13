<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$member_id = (int) ($_POST['member_id'] ?? 0);
$redirect = BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#docs';

$doc_title = trim($_POST['document_title'] ?? '');
$doc_type = trim($_POST['document_type'] ?? 'Other');
$description = trim($_POST['description'] ?? '');
$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_size = 10 * 1024 * 1024;

if (!$member_id || !$doc_title) {
    $_SESSION['doc_error'] = 'Report title is required.';
    header('Location: ' . $redirect);
    exit;
}

$stmt = $conn->prepare("SELECT school_id FROM school_members WHERE id = ?");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['doc_error'] = 'Please select a file to upload.';
    header('Location: ' . $redirect);
    exit;
}

$file = $_FILES['document_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext, true)) {
    $_SESSION['doc_error'] = 'Invalid file type. Only PDF, DOC, DOCX, JPG, PNG allowed.';
    header('Location: ' . $redirect);
    exit;
}
if ($file['size'] > $max_size) {
    $_SESSION['doc_error'] = 'File too large. Max size is 10MB.';
    header('Location: ' . $redirect);
    exit;
}

$document_name = $doc_type . ' — ' . $doc_title;
$file_type_mime = $file['type'] ?: $ext;

$upload_dir = dirname(__DIR__) . '/uploads/school_documents/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$new_file = 'member_' . $member_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_file)) {
    $_SESSION['doc_error'] = 'Failed to save uploaded file.';
    header('Location: ' . $redirect);
    exit;
}
$db_path = 'uploads/school_documents/' . $new_file;

$ins = $conn->prepare("INSERT INTO school_member_documents
    (member_id, school_id, document_name, document_type, description, file_path, file_type, uploaded_by_role, uploaded_by_doctor_id, uploaded_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'doctor', ?, NOW())");
$ins->bind_param('iisssssi', $member_id, $member['school_id'], $document_name, $doc_type, $description, $db_path, $file_type_mime, $doctor_id);

if (!$ins->execute()) {
    @unlink($upload_dir . $new_file);
    $_SESSION['doc_error'] = 'Failed to save record: ' . $conn->error;
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
