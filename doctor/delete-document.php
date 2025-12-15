<?php
include_once(__DIR__ . "/../config/connect.php");
session_start();

if (!isset($_SESSION['doctor_logged_in'])) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$doc_id = intval($_GET['id']);

// Verify document belongs to doctor
$check_sql = "SELECT file_path FROM doctor_documents WHERE id = ? AND doctor_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('ii', $doc_id, $doctor_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $doc = $result->fetch_assoc();
    
    // Delete file from server
    if (file_exists($doc['file_path'])) {
        unlink($doc['file_path']);
    }
    
    // Delete from database
    $delete_sql = "DELETE FROM doctor_documents WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $doc_id);
    $delete_stmt->execute();
    
    $_SESSION['success_message'] = "Document deleted successfully!";
} else {
    $_SESSION['error_message'] = "Document not found or unauthorized.";
}

header("Location: my-contact.php");
exit();
?>