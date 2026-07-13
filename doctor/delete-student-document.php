<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$member_id = (int) ($_POST['member_id'] ?? 0);

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM school_member_documents WHERE id = ? AND uploaded_by_doctor_id = ?");
    $stmt->bind_param('ii', $id, $doctor_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    if ($doc) {
        $full_path = dirname(__DIR__) . '/' . $doc['file_path'];
        if ($doc['file_path'] && file_exists($full_path)) @unlink($full_path);
        $del = $conn->prepare("DELETE FROM school_member_documents WHERE id = ? AND uploaded_by_doctor_id = ?");
        $del->bind_param('ii', $id, $doctor_id);
        $del->execute();
    }
}

header('Location: ' . BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#docs');
exit;
