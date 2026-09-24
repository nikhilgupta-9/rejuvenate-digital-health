<?php
/**
 * util/lab-booking-handler.php — Handles diagnostic lab test bookings.
 *
 * Supports:
 *   - Selecting tests from lab_tests_catalog
 *   - Home sample collection vs Clinic/Lab visit
 *   - Uploading doctor's prescription for lab investigations
 */

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/WhatsAppNotifier.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$patientName   = trim($_POST['name'] ?? '');
$phone         = preg_replace('/\D/', '', $_POST['phone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$address       = trim($_POST['address'] ?? '');
$city          = trim($_POST['city'] ?? '');
$zipCode       = trim($_POST['zip_code'] ?? '');
$bookingDate   = trim($_POST['booking_date'] ?? '');
$timeSlot      = trim($_POST['time_slot'] ?? '');
$collectionType= in_array($_POST['collection_type'] ?? 'home_collection', ['home_collection', 'visit_lab'], true) ? $_POST['collection_type'] : 'home_collection';
$paymentMethod = in_array($_POST['payment_method'] ?? 'cod', ['cod', 'online'], true) ? $_POST['payment_method'] : 'cod';
$notes         = trim($_POST['notes'] ?? '');
$userId        = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$prescriptionId= !empty($_POST['prescription_id']) ? (int) $_POST['prescription_id'] : null;

// Validation
if ($patientName === '' || $phone === '' || $bookingDate === '' || $timeSlot === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please provide your name, phone number, preferred date, and time slot.']);
    exit;
}

if ($collectionType === 'home_collection' && ($address === '' || $city === '')) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a full delivery address and city for home sample collection.']);
    exit;
}

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit mobile number.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate) || strtotime($bookingDate) < strtotime(date('Y-m-d'))) {
    echo json_encode(['status' => 'error', 'message' => 'Please choose a valid upcoming booking date.']);
    exit;
}

// Prescription file upload handling
$prescriptionFileRel = null;
if (!empty($_FILES['prescription_file']) && $_FILES['prescription_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['prescription_file']['tmp_name'];
    $fileSize = $_FILES['prescription_file']['size'];
    $origName = $_FILES['prescription_file']['name'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Allowed: JPG, PNG, PDF, WEBP.']);
        exit;
    }

    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File size exceeds maximum limit of 10MB.']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/prescriptions';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFilename = 'lab_rx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath  = $uploadDir . '/' . $newFilename;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        $prescriptionFileRel = 'uploads/prescriptions/' . $newFilename;
    }
}

// Tests parsing
$tests = [];
$totalAmount = 0.00;
if (!empty($_POST['tests'])) {
    $rawTests = is_array($_POST['tests']) ? $_POST['tests'] : json_decode($_POST['tests'], true);
    if (is_array($rawTests)) {
        foreach ($rawTests as $t) {
            if (!empty($t['test_name'])) {
                $cost = (float)($t['price'] ?? 0.00);
                $tests[] = [
                    'test_code' => trim($t['test_code'] ?? ''),
                    'test_name' => trim($t['test_name']),
                    'category'  => trim($t['category'] ?? 'Pathology'),
                    'price'     => $cost,
                ];
                $totalAmount += $cost;
            }
        }
    }
}

if (empty($tests) && !$prescriptionFileRel && !$prescriptionId) {
    echo json_encode(['status' => 'error', 'message' => 'Please select at least one lab test or upload a doctor prescription.']);
    exit;
}

// Generate unique booking UID: LAB-YYYYMMDD-XXXX
$bookingUid = 'LAB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$testsJson = json_encode($tests);
$paymentStatus = 'pending';
$status = 'scheduled';

$stmt = $conn->prepare("
    INSERT INTO lab_bookings 
    (booking_uid, user_id, prescription_id, patient_name, patient_phone, patient_email, 
     collection_type, address, city, zip_code, booking_date, time_slot, 
     tests_json, prescription_file, total_amount, payment_method, payment_status, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    'siissssssssssdsssss',
    $bookingUid,
    $userId,
    $prescriptionId,
    $patientName,
    $phone,
    $email,
    $collectionType,
    $address,
    $city,
    $zipCode,
    $bookingDate,
    $timeSlot,
    $testsJson,
    $prescriptionFileRel,
    $totalAmount,
    $paymentMethod,
    $paymentStatus,
    $status,
    $notes
);

$ok = $stmt->execute();
$bookingId = $conn->insert_id;
$stmt->close();

if (!$ok || !$bookingId) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to schedule lab test. Please try again.']);
    exit;
}

// WhatsApp alert to patient
try {
    $wa = new WhatsAppNotifier($conn);
    $wa->sendEvent('lab_booking_confirmed', $phone, [
        'patient_name' => $patientName,
        'booking_id'   => $bookingUid,
        'date'         => date('d M Y', strtotime($bookingDate)),
        'time'         => $timeSlot,
        'type'         => $collectionType === 'home_collection' ? 'Home Sample Collection' : 'Lab Center Visit',
    ], 'lab_booking', $bookingId);
} catch (Throwable $e) {
    error_log('[Lab Booking] WhatsApp notify error: ' . $e->getMessage());
}

echo json_encode([
    'status'      => 'success',
    'message'     => "Your diagnostic test booking #{$bookingUid} has been scheduled for " . date('d M Y', strtotime($bookingDate)) . " ({$timeSlot}). Our phlebotomist/lab team will contact you shortly.",
    'booking_uid' => $bookingUid,
    'booking_id'  => $bookingId,
]);
