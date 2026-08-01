<?php
require_once __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../auth/guard.php';

header('Content-Type: application/json');

if (!admin_jwt_guard(true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$appointment_id = intval($_POST['appointment_id'] ?? 0);
if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
$stmt->bind_param('i', $appointment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Appointment deleted.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete appointment.']);
}
