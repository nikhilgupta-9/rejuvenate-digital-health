<?php


require 'function.php'; // where send_appointment_email() exists

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$required = ['name', 'email', 'phone', 'department', 'date', 'time'];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'All fields are required.'
        ]);
        exit;
    }
}

$data = [
    'name'       => trim($_POST['name']),
    'email'      => trim($_POST['email']),
    'phone'      => trim($_POST['phone']),
    'department' => trim($_POST['department']),
    'date'       => $_POST['date'],
    'time'       => $_POST['time'],
];

if (send_appointment_email($data)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Appointment request sent successfully.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to send appointment request.'
    ]);
}
