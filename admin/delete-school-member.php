<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: schools-list.php"); exit(); }

$id = intval($_POST['id'] ?? 0);
$redirect = $_POST['redirect'] ?? 'schools-list.php';
if (!$id) { header("Location: schools-list.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM school_members WHERE id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) { header("Location: schools-list.php"); exit(); }

if ($member['profile_pic'] && file_exists('../' . $member['profile_pic'])) {
    @unlink('../' . $member['profile_pic']);
}

// FK constraints cascade-delete member_health_profiles and teacher_student_assignments
$del = $conn->prepare("DELETE FROM school_members WHERE id=?");
$del->bind_param('i', $id);
$del->execute();

$_SESSION['success_message'] = ucfirst($member['type']) . " \"" . $member['name'] . "\" has been removed.";

$safe_redirects = ['schools-list.php', 'school-members.php'];
if (strpos($redirect, 'school-view.php?id=') === 0 && ctype_digit(substr($redirect, 20))) {
    header("Location: $redirect");
} elseif (in_array($redirect, $safe_redirects)) {
    header("Location: $redirect");
} else {
    header("Location: school-view.php?id=" . intval($member['school_id']));
}
exit();
