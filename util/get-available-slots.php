<?php
/**
 * Public AJAX endpoint — GET ?doctor_id=<int>&date=<Y-m-d>
 * Returns that doctor's bookable time slots for the given date, with
 * already-booked slots flagged so the UI can grey them out.
 */
include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/function.php';

header('Content-Type: application/json');

$doctorId = intval($_GET['doctor_id'] ?? 0);
$date     = trim($_GET['date'] ?? '');

if (!$doctorId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid doctor or date', 'slots' => []]);
    exit;
}

if ($date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Please choose a current or future date', 'slots' => []]);
    exit;
}

// Confirm the doctor is real & active before doing any slot work
$chk = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
$chk->bind_param('i', $doctorId);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found', 'slots' => []]);
    exit;
}

$slots = get_available_slots($doctorId, $date);

echo json_encode(['success' => true, 'slots' => $slots]);
