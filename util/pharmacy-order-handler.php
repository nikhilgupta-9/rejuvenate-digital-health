<?php
/**
 * util/pharmacy-order-handler.php — Handles medicine ordering submissions.
 *
 * Supports:
 *   1. Uploading a physical prescription image/PDF
 *   2. Re-ordering prescribed medicines from an appointment
 *   3. Ordering OTC / catalog medicines
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

// Ensure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$patientName = trim($_POST['name'] ?? '');
$phone        = preg_replace('/\D/', '', $_POST['phone'] ?? '');
$email        = trim($_POST['email'] ?? '');
$address      = trim($_POST['address'] ?? '');
$city         = trim($_POST['city'] ?? '');
$state        = trim($_POST['state'] ?? '');
$zipCode      = trim($_POST['zip_code'] ?? '');
$notes        = trim($_POST['notes'] ?? '');
$paymentMethod= in_array($_POST['payment_method'] ?? 'cod', ['cod', 'online'], true) ? $_POST['payment_method'] : 'cod';
$prescriptionId = !empty($_POST['prescription_id']) ? (int) $_POST['prescription_id'] : null;
$userId         = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

// Basic validation
if ($patientName === '' || $phone === '' || $address === '' || $city === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in your name, contact phone, delivery address, and city.']);
    exit;
}

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit Indian mobile number.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

// Prescription file handling
$prescriptionFileRel = null;
if (!empty($_FILES['prescription_file']) && $_FILES['prescription_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['prescription_file']['tmp_name'];
    $fileSize = $_FILES['prescription_file']['size'];
    $origName = $_FILES['prescription_file']['name'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid prescription file. Allowed formats: JPG, PNG, PDF, WEBP.']);
        exit;
    }

    if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
        echo json_encode(['status' => 'error', 'message' => 'Prescription file size exceeds maximum limit of 10MB.']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/prescriptions';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFilename = 'rx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath  = $uploadDir . '/' . $newFilename;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        $prescriptionFileRel = 'uploads/prescriptions/' . $newFilename;
    }
}

// Items parsing (if catalog selection or consultation items provided)
$items = [];
if (!empty($_POST['items'])) {
    $rawItems = is_array($_POST['items']) ? $_POST['items'] : json_decode($_POST['items'], true);
    if (is_array($rawItems)) {
        foreach ($rawItems as $it) {
            if (!empty($it['name'])) {
                $items[] = [
                    'name'     => trim($it['name']),
                    'qty'      => max(1, (int)($it['qty'] ?? 1)),
                    'strength' => trim($it['strength'] ?? ''),
                    'price'    => (float)($it['price'] ?? 0.00),
                ];
            }
        }
    }
}

// If no file uploaded and no items selected and no prescription ID linked, require at least one
if (!$prescriptionFileRel && empty($items) && !$prescriptionId) {
    echo json_encode(['status' => 'error', 'message' => 'Please upload a prescription or specify medicines to order.']);
    exit;
}

// Calculate estimated subtotal if catalog items were chosen
$subtotal = 0.00;
foreach ($items as $it) {
    $subtotal += ($it['price'] * $it['qty']);
}
$deliveryFee = ($subtotal > 0 && $subtotal < 500) ? 50.00 : 0.00;
$totalAmount = $subtotal + $deliveryFee;

// Generate unique order number: RXO-YYYYMMDD-XXXX
$orderNumber = 'RXO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$itemsJson = json_encode($items);
$orderStatus = 'placed';
$paymentStatus = 'pending';

$stmt = $conn->prepare("
    INSERT INTO pharmacy_orders 
    (order_number, user_id, prescription_id, patient_name, patient_phone, patient_email, 
     delivery_address, city, state, zip_code, prescription_file, items_json, 
     subtotal, delivery_fee, total_amount, payment_method, payment_status, order_status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    'siisssssssssdddssss',
    $orderNumber,
    $userId,
    $prescriptionId,
    $patientName,
    $phone,
    $email,
    $address,
    $city,
    $state,
    $zipCode,
    $prescriptionFileRel,
    $itemsJson,
    $subtotal,
    $deliveryFee,
    $totalAmount,
    $paymentMethod,
    $paymentStatus,
    $orderStatus,
    $notes
);

$ok = $stmt->execute();
$orderId = $conn->insert_id;
$stmt->close();

if (!$ok || !$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to save your order. Please try again.']);
    exit;
}

// Trigger WhatsApp notification to patient
try {
    $wa = new WhatsAppNotifier($conn);
    $wa->sendEvent('pharmacy_order_placed', $phone, [
        'patient_name' => $patientName,
        'order_number' => $orderNumber,
        'date'         => date('d M Y'),
    ], 'pharmacy_order', $orderId);
} catch (Throwable $e) {
    error_log('[Pharmacy Order] WhatsApp notify error: ' . $e->getMessage());
}

echo json_encode([
    'status'       => 'success',
    'message'      => "Your medicine order #{$orderNumber} has been received! Our registered pharmacist will review your prescription and confirm dispatch.",
    'order_number' => $orderNumber,
    'order_id'     => $orderId,
]);
