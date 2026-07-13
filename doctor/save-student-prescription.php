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
$diagnosis = trim($_POST['diagnosis'] ?? '');
$symptoms = trim($_POST['symptoms'] ?? '');
$prescription_text = trim($_POST['prescription_text'] ?? '');
$advice = trim($_POST['advice'] ?? '');
$follow_up_date = trim($_POST['follow_up_date'] ?? '');

$redirect = BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#rx';

if (!$member_id || !$prescription_text) {
    $_SESSION['rx_error'] = 'Prescription text is required.';
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

$follow_up_val = $follow_up_date ?: null;

$ins = $conn->prepare("INSERT INTO school_member_prescriptions
    (member_id, school_id, doctor_id, diagnosis, symptoms, prescription_text, advice, follow_up_date)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$ins->bind_param('iiisssss', $member_id, $member['school_id'], $doctor_id, $diagnosis, $symptoms, $prescription_text, $advice, $follow_up_val);

if (!$ins->execute()) {
    $_SESSION['rx_error'] = 'Failed to save prescription: ' . $conn->error;
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
