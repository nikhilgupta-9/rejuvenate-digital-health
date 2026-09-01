<?php
/**
 * Admin booking — AJAX: POST doctor_id, date, (optional) duration.
 * Slots come from the doctor's weekly schedule (doctor_schedules) via the
 * shared generate_doctor_slots() helper, so admin and the public booking
 * page always agree.
 */
include_once __DIR__ . "/../config/connect.php";
require_once __DIR__ . "/../util/function.php";
require_once __DIR__ . '/auth/guard.php';

header('Content-Type: application/json');

if (!admin_jwt_guard(true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$doctor_id = (int) ($_POST['doctor_id'] ?? 0);
$date      = trim($_POST['date'] ?? '');
$fallback  = (int) ($_POST['duration'] ?? 30);

if (!$doctor_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$slots = generate_doctor_slots($conn, $doctor_id, $date, $fallback > 0 ? $fallback : 30);

echo json_encode([
    'success'   => true,
    'timeSlots' => $slots,
]);
