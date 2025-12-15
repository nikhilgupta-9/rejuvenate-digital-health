<?php
session_start();
include_once "../config/connect.php";
include_once "function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['User not logged in']]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $user_id = $_SESSION['user_id'];
    
    $result = update_profile_picture($user_id, $_FILES['profile_picture']);
    
    header('Content-Type: application/json');
    
    if ($result['success']) {
        // Update session if needed
        $_SESSION['profile_pic_updated'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'image_url' => $result['filename']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $result['errors']
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['Invalid request']]);
}
?>