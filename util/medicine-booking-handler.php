<?php
require 'function.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$required = ['name', 'email', 'phone', 'date'];
foreach ($required as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

$name    = trim($_POST['name']);
$email   = trim($_POST['email']);
$phone   = trim($_POST['phone']);
$date    = trim($_POST['date']);
$note    = trim($_POST['subject'] ?? '');
$product = trim($_POST['product'] ?? 'Medicine');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

if (!preg_match('/^[6-9]\d{9}$/', preg_replace('/\D/', '', $phone))) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit phone number.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['status' => 'error', 'message' => 'Please choose a valid, upcoming date.']);
    exit;
}

$message = "Product: {$product}\nPreferred Date: {$date}";
if ($note !== '') {
    $message .= "\nNote: {$note}";
}

$data = [
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'subject' => "Medicine Request: {$product}",
    'message' => $message,
];

$contact  = contact_us();
$toEmail  = $contact['email'] ?? '';

$inquiryId = send_inquiry_email($data, $toEmail);

if ($inquiryId) {
    echo json_encode([
        'status'  => 'success',
        'message' => "Thank you, {$name}! Your request for {$product} has been received — our team will contact you shortly.",
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Something went wrong while sending your request. Please try again in a moment.',
    ]);
}
