<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: auth/login.php"); exit(); }
include "db-conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: medical-records.php"); exit(); }

$id   = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

if (!$id || !in_array($type, ['patients','school'])) { header("Location: medical-records.php"); exit(); }

if ($type === 'patients') {
    $stmt = $conn->prepare("SELECT * FROM patient_documents WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $rec = $stmt->get_result()->fetch_assoc();
    if ($rec) {
        if ($rec['file_path'] && file_exists('../' . $rec['file_path'])) @unlink('../' . $rec['file_path']);
        $del = $conn->prepare("DELETE FROM patient_documents WHERE id=?");
        $del->bind_param('i', $id); $del->execute();
        $_SESSION['success_message'] = "Record \"" . $rec['document_name'] . "\" deleted.";
    }
    header("Location: medical-records.php?tab=patients");
} else {
    $stmt = $conn->prepare("SELECT * FROM school_member_documents WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $rec = $stmt->get_result()->fetch_assoc();
    if ($rec) {
        if ($rec['file_path'] && file_exists('../' . $rec['file_path'])) @unlink('../' . $rec['file_path']);
        $del = $conn->prepare("DELETE FROM school_member_documents WHERE id=?");
        $del->bind_param('i', $id); $del->execute();
        $_SESSION['success_message'] = "Record \"" . $rec['document_name'] . "\" deleted.";
    }
    header("Location: medical-records.php?tab=school");
}
exit();
