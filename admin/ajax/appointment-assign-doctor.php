<?php
require_once __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../auth/guard.php';

header('Content-Type: application/json');

if (!admin_jwt_guard(true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointment_id = intval($_POST['appointment_id'] ?? 0);
$new_doctor_id  = intval($_POST['new_doctor_id'] ?? 0);

if (!$appointment_id || !$new_doctor_id) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id or new_doctor_id.']);
    exit;
}

$doc_stmt = $conn->prepare("SELECT name, specialization FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
$doc_stmt->bind_param('i', $new_doctor_id);
$doc_stmt->execute();
$doctor = $doc_stmt->get_result()->fetch_assoc();

if (!$doctor) {
    echo json_encode(['success' => false, 'message' => 'Selected doctor was not found.']);
    exit;
}

$stmt = $conn->prepare("UPDATE appointments SET doctor_id = ? WHERE id = ?");
$stmt->bind_param('ii', $new_doctor_id, $appointment_id);

if ($stmt->execute()) {
    echo json_encode([
        'success'        => true,
        'message'        => 'Doctor assigned successfully.',
        'doctor_id'      => $new_doctor_id,
        'doctor_name'    => $doctor['name'],
        'specialization' => $doctor['specialization'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to assign doctor.']);
}
