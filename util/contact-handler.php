<?php
require 'function.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$required = ['fname', 'email', 'phone', 'message'];
foreach ($required as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

$name    = trim($_POST['fname']);
$email   = trim($_POST['email']);
$phone   = trim($_POST['phone']);
$subject = trim($_POST['subject'] ?? '') ?: 'General Inquiry';
$message = trim($_POST['message']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

if (!preg_match('/^[6-9]\d{9}$/', preg_replace('/\D/', '', $phone))) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit phone number.']);
    exit;
}

$data = [
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'subject' => $subject,
    'message' => $message,
];

$contact  = contact_us();
$toEmail  = $contact['email'] ?? '';

$inquiryId = send_inquiry_email($data, $toEmail);

if ($inquiryId) {
    echo json_encode([
        'status'  => 'success',
        'message' => "Thank you, {$name}! Your message has been received — our team will get back to you shortly.",
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Something went wrong while sending your message. Please try again in a moment.',
    ]);
}
