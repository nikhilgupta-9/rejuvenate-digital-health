<?php
require_once __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth/guard.php';

header('Content-Type: application/json');

if (!admin_jwt_guard(true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointment_id = intval($_POST['appointment_id'] ?? 0);
if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id.']);
    exit;
}

$stmt = $conn->prepare("UPDATE appointments SET status = 'approved' WHERE id = ?");
$stmt->bind_param('i', $appointment_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to approve appointment.']);
    exit;
}

$message = 'Appointment approved.';

// Best-effort notification emails — must never make the approval itself
// look like it failed if SMTP has a hiccup.
$appointment_sql = "
    SELECT a.*, u.name as user_name, u.email as user_email, u.dob, u.gender,
           d.name as doctor_name, d.email as doctor_email, d.specialization, d.consultation_fee,
           TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
           DATE_FORMAT(a.appointment_date, '%d %M, %Y') as formatted_date
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.id = ?
";
$appointment_stmt = $conn->prepare($appointment_sql);
$appointment_stmt->bind_param('i', $appointment_id);
$appointment_stmt->execute();
$appointment_row = $appointment_stmt->get_result()->fetch_assoc();
$appointment_stmt->close();

if ($appointment_row) {
    $appointment_details = [
        'appointment_id' => 'AP' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT),
        'date'           => $appointment_row['formatted_date'],
        'time'           => $appointment_row['formatted_time'],
        'fee'            => number_format($appointment_row['consultation_fee']),
        'type'           => 'Clinic Visit',
        'purpose'        => $appointment_row['purpose'],
    ];
    $doctor_details = [
        'name'           => $appointment_row['doctor_name'],
        'specialization' => $appointment_row['specialization'],
    ];
    $patient_details = [
        'name'   => $appointment_row['user_name'],
        'age'    => !empty($appointment_row['dob']) ? date_diff(date_create($appointment_row['dob']), date_create('today'))->y : null,
        'gender' => $appointment_row['gender'],
        'phone'  => 'Not provided',
        'email'  => $appointment_row['user_email'],
    ];

    try {
        if (send_appointment_confirmation_email($appointment_row['user_email'], $appointment_row['user_name'], $appointment_details, $doctor_details)) {
            $message = 'Appointment approved and confirmation email sent to patient.';
        } else {
            $message = 'Appointment approved but the confirmation email failed to send.';
        }
        send_appointment_assignment_email($appointment_row['doctor_email'], $appointment_row['doctor_name'], $appointment_details, $patient_details);
    } catch (Throwable $e) {
        $message = 'Appointment approved but notification emails failed to send.';
    }
}

echo json_encode([
    'success'      => true,
    'message'      => $message,
    'status'       => 'approved',
    'status_label' => 'Approved',
]);
