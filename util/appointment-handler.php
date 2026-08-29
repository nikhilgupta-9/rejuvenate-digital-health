<?php
/**
 * Public appointment booking endpoint. Used by:
 *  - The homepage quick-booking form (name/email/phone/department/date/time only)
 *  - book-appointment.php, the full department -> doctor -> slot -> details flow
 *    (adds doctor_id, abha_number, notes, appointment_type, visit_person, consent)
 */

require 'function.php'; // where send_appointment_email() exists
require_once __DIR__ . '/../lib/AuditLogger.php';
require_once __DIR__ . '/../config/payment.php';

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

$name  = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$date  = trim($_POST['date']);
$time  = trim($_POST['time']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}
if (!preg_match('/^[6-9]\d{9}$/', preg_replace('/\D/', '', $phone))) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit mobile number.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
    echo json_encode(['status' => 'error', 'message' => 'Please choose a valid, current or future date.']);
    exit;
}

$data = [
    'name'       => $name,
    'email'      => $email,
    'phone'      => $phone,
    'department' => trim($_POST['department']),
    'date'       => $date,
    'time'       => $time,
    'notes'      => trim($_POST['notes'] ?? ''),
];

// Optional — set when the request came from a specific doctor's "Book an
// Appointment" button, so the booking is actually tied to that doctor.
$doctorId = null;
if (!empty($_POST['doctor_id'])) {
    $doctorId = (int) $_POST['doctor_id'];
    $data['doctor_id']   = $doctorId;
    $data['doctor_name'] = trim($_POST['doctor_name'] ?? '');
}

// Optional ABHA number — format XX-XXXX-XXXX-XXXX per NHA/ABDM spec.
// Never required: many patients won't have one yet.
if (!empty($_POST['abha_number'])) {
    $abha = trim($_POST['abha_number']);
    if (!preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $abha)) {
        echo json_encode(['status' => 'error', 'message' => 'ABHA number format should be XX-XXXX-XXXX-XXXX.']);
        exit;
    }
    $data['abha_number'] = $abha;
}

// Every booking gets tied to a real users.id — a logged-in patient uses
// their session; anyone else is matched by email/mobile to an existing
// account, or a new one is created for them on the spot (see
// find_or_create_patient_user() in function.php).
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !empty($_SESSION['user_id'])) {
    $data['user_id'] = (int) $_SESSION['user_id'];
} else {
    $match = find_or_create_patient_user($conn, $name, $email, $phone);
    $data['user_id'] = $match['user_id'];
    if ($match['created']) {
        $data['_new_account_temp_password'] = $match['temp_password'];
    }
}

// Visit type — booking for self or on behalf of someone else
$visitPerson = ($_POST['visit_person'] ?? 'self') === 'other' ? 'other' : 'self';
$data['visit_person'] = $visitPerson;
if ($visitPerson === 'other') {
    $visitedName = trim($_POST['visited_person_name'] ?? '');
    if ($visitedName === '') {
        echo json_encode(['status' => 'error', 'message' => 'Please enter the name of the person being visited for.']);
        exit;
    }
    $data['visited_person_name'] = $visitedName;
}

$data['appointment_type'] = ($_POST['appointment_type'] ?? 'online') === 'clinic' ? 'clinic' : 'online';

// Consent — ABDM requires explicit, logged consent before processing health data.
// Only enforced when the submitting form actually asks for it (book-appointment.php
// sends consent_required=1); the older homepage quick form doesn't collect it.
if (!empty($_POST['consent_required'])) {
    if (empty($_POST['consent_given'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide consent to proceed with booking.']);
        exit;
    }
    $data['consent_given'] = true;
}

// Payment (Razorpay) — only applies when a specific doctor was chosen AND
// that doctor has a consultation_fee configured. The fee is re-read from
// the DB here, never trusted from the client, so the amount actually
// charged (fixed at order-creation time in create-razorpay-order.php)
// can be verified against what this doctor is really meant to charge.
// Deliberately the LAST validation before insert: the browser only opens
// Razorpay Checkout (and captures money) after every other field here has
// already passed, so a payment is never taken for a request that then
// turns out to fail some earlier check.
if ($doctorId) {
    $feeStmt = $conn->prepare("SELECT consultation_fee FROM doctors WHERE id = ? LIMIT 1");
    $feeStmt->bind_param('i', $doctorId);
    $feeStmt->execute();
    $feeRow = $feeStmt->get_result()->fetch_assoc();
    $consultationFee = (float) ($feeRow['consultation_fee'] ?? 0);

    if ($consultationFee > 0) {
        $rpOrderId   = trim($_POST['razorpay_order_id'] ?? '');
        $rpPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
        $rpSignature = trim($_POST['razorpay_signature'] ?? '');

        if (!$rpOrderId || !$rpPaymentId || !$rpSignature) {
            echo json_encode(['status' => 'error', 'message' => 'Payment was not completed. Please try again.']);
            exit;
        }

        if (!RAZORPAY_KEY_SECRET) {
            echo json_encode(['status' => 'error', 'message' => 'Online payment is temporarily unavailable. Please try again later or contact us.']);
            exit;
        }

        $expectedSignature = hash_hmac('sha256', $rpOrderId . '|' . $rpPaymentId, RAZORPAY_KEY_SECRET);
        if (!hash_equals($expectedSignature, $rpSignature)) {
            try {
                (new AuditLogger($conn))->logValidationFailure('razorpay_signature', 'Signature mismatch on appointment payment', $data['user_id'] ?? 0, 'patient');
            } catch (Throwable $e) {
            }
            echo json_encode(['status' => 'error', 'message' => 'Payment verification failed. If money was deducted, it will be refunded automatically — please contact us.']);
            exit;
        }

        $data['payment_status']      = 'paid';
        $data['payment_amount']      = $consultationFee;
        $data['razorpay_order_id']   = $rpOrderId;
        $data['razorpay_payment_id'] = $rpPaymentId;
        $data['razorpay_signature']  = $rpSignature;
    }
}

// Prevent double-booking the same doctor's slot (best-effort — same
// check-then-insert pattern already used by the admin booking screen).
if ($doctorId) {
    $chk = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending','approved') LIMIT 1");
    $chk->bind_param('iss', $doctorId, $date, $time);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        echo json_encode(['status' => 'error', 'message' => 'That time slot was just booked by someone else. Please pick another slot.']);
        exit;
    }
}

$appointmentId = send_appointment_email($data);

if ($appointmentId) {
    try {
        $logger = new AuditLogger($conn);
        $logger->logDataAccess(
            $data['user_id'] ?? 0,
            'patient',
            $data['user_id'] ?? 0,
            'appointment_booking',
            'Patient booked an appointment (consent: ' . (!empty($data['consent_given']) ? 'Y' : 'not requested')
                . ', payment: ' . ($data['payment_status'] ?? 'not_required') . ')'
        );
    } catch (Throwable $e) {
        // Audit failure must never block a successful booking
    }

    echo json_encode([
        'status'         => 'success',
        'message'        => 'Appointment request sent successfully.',
        'appointment_id' => 'AP' . str_pad($appointmentId, 6, '0', STR_PAD_LEFT),
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to save your appointment. Please try again.'
    ]);
}
