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

// SOFT delete only. school_members carry health data (parent consents,
// health profiles, prescriptions, certificates) and school_members.school_id
// is FK RESTRICT — a hard DELETE FROM schools would fail. Deactivate instead;
// all member records are retained and the school drops out of every
// "status = 'Active'" listing / login.
$del = $conn->prepare("UPDATE schools SET status = 'Inactive' WHERE id = ?");
$del->bind_param('i', $id);
$del->execute();

$_SESSION['success_message'] = "School \"" . $school['school_name'] . "\" has been deactivated. Its member records are retained.";
header("Location: schools-list.php");
exit();
