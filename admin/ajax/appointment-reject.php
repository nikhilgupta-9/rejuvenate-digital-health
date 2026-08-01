<?php
require_once __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../auth/guard.php';

header('Content-Type: application/json');

if (!admin_jwt_guard(true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointment_id    = intval($_POST['appointment_id'] ?? 0);
$rejection_reason  = trim($_POST['rejection_reason'] ?? '');

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id.']);
    exit;
}

$stmt = $conn->prepare("UPDATE appointments SET status = 'rejected', rejection_reason = ? WHERE id = ?");
$stmt->bind_param('si', $rejection_reason, $appointment_id);

if ($stmt->execute()) {
    echo json_encode([
        'success'      => true,
        'message'      => 'Appointment rejected.',
        'status'       => 'rejected',
        'status_label' => 'Rejected',
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to reject appointment.']);
}
