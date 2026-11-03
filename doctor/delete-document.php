<?php
include_once(__DIR__ . "/../config/connect.php");
require_once __DIR__ . "/auth/guard.php";
$payload = doctor_jwt_guard();

$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? $_SESSION['doctor_id'] ?? 0);
$doc_id = intval($_GET['id'] ?? 0);

// Verify document belongs to doctor
$check_sql = "SELECT file_path FROM doctor_documents WHERE id = ? AND doctor_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('ii', $doc_id, $doctor_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $doc = $result->fetch_assoc();
    
    // Delete file from server
    $fullPath = $doc['file_path'];
    if (!file_exists($fullPath)) {
        $fullPath = dirname(__DIR__) . '/' . ltrim($fullPath, '/');
    }
    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
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

$redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "my-contact.php";
header("Location: " . $redirect);
exit();
?>