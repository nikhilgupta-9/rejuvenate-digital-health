<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: schools-list.php"); exit(); }

$id = intval($_POST['id'] ?? 0);
if (!$id) { header("Location: schools-list.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM schools WHERE id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
if (!$school) { header("Location: schools-list.php"); exit(); }

// Remove uploaded logo file, if any
if ($school['logo'] && file_exists('../' . $school['logo'])) {
    @unlink('../' . $school['logo']);
}

// FK constraints cascade-delete school_users, school_members, member_health_profiles, teacher_student_assignments
$del = $conn->prepare("DELETE FROM schools WHERE id=?");
$del->bind_param('i', $id);
$del->execute();

$_SESSION['success_message'] = "School \"" . $school['school_name'] . "\" and all its related records have been deleted.";
header("Location: schools-list.php");
exit();
