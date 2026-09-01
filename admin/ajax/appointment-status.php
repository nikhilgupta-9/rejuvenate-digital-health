<?php
/**
 * Set an appointment to a terminal visit outcome: completed | no_show.
 * (approve / reject have their own endpoints.)
 */
require_once __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../auth/guard.php';

header('Content-Type: application/json');

if (!admin_jwt_guard(true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointment_id = (int) ($_POST['appointment_id'] ?? 0);
$status         = $_POST['status'] ?? '';

$allowed = ['completed', 'no_show', 'approved'];
if (!$appointment_id || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $appointment_id);

if ($stmt->execute()) {
    echo json_encode([
        'success'      => true,
        'message'      => 'Appointment marked as ' . str_replace('_', ' ', $status) . '.',
        'status'       => $status,
        'status_label' => ucfirst(str_replace('_', ' ', $status)),
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed.']);
}
