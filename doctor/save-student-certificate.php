<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$member_id        = (int) ($_POST['member_id'] ?? 0);
$certificate_type = trim($_POST['certificate_type'] ?? 'Medical Leave Certificate');
$reason            = trim($_POST['reason'] ?? '');
$leave_from        = trim($_POST['leave_from'] ?? '');
$leave_to          = trim($_POST['leave_to'] ?? '');
$fit_to_join_date  = trim($_POST['fit_to_join_date'] ?? '');
$remarks           = trim($_POST['remarks'] ?? '');

$redirect = BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#cert';

if (!$member_id || !$reason) {
    $_SESSION['cert_error'] = 'Reason / diagnosis is required.';
    header('Location: ' . $redirect);
    exit;
}

if ($leave_from && $leave_to && strtotime($leave_to) < strtotime($leave_from)) {
    $_SESSION['cert_error'] = '"Leave To" date cannot be before "Leave From" date.';
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

$leave_from_val = $leave_from ?: null;
$leave_to_val = $leave_to ?: null;
$fit_to_join_val = $fit_to_join_date ?: null;

$ins = $conn->prepare("INSERT INTO school_member_certificates
    (member_id, school_id, doctor_id, certificate_type, reason, leave_from, leave_to, fit_to_join_date, remarks)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$ins->bind_param('iiissssss', $member_id, $member['school_id'], $doctor_id, $certificate_type, $reason, $leave_from_val, $leave_to_val, $fit_to_join_val, $remarks);

if (!$ins->execute()) {
    $_SESSION['cert_error'] = 'Failed to issue certificate: ' . $conn->error;
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
