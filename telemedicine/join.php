<?php
/**
 * Entry point for both doctor and patient to join a video consultation.
 * Verifies the requester actually owns the appointment, prepares the
 * appointments.meeting_* columns (reused as the session record), then
 * issues a short-lived signed ticket and redirects into room.php.
 *
 * Usage: telemedicine/join.php?appointment_id=123
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/lib/JWT.php';
require_once dirname(__DIR__) . '/doctor/auth/guard.php';

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    http_response_code(400);
    exit('Missing appointment_id.');
}

$role = null;
$entity_id = 0;
$via_guest_token = false;

// Try doctor JWT first (does not redirect — just tells us if a valid doctor session exists)
$doctor_payload = doctor_jwt_guard(true);
if ($doctor_payload && ($doctor_payload['role'] ?? '') === 'doctor') {
    $role = 'doctor';
    $entity_id = (int) ($doctor_payload['doctor_id'] ?? $doctor_payload['sub'] ?? 0);
}

// Fall back to registered patient session
if (!$role) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
        $role = 'patient';
        $entity_id = (int) $_SESSION['user_id'];
    }
}

// Guest patient — booked without an account, so appointments.user_id is
// NULL and there's no session to prove ownership with. Accept the signed
// token mailed to them instead (see telemedicine_guest_link() in helpers.php).
if (!$role) {
    $guest_token = $_GET['guest_token'] ?? '';
    if ($guest_token) {
        try {
            $guestClaims = JWT::verify($guest_token, TELEMED_SECRET);
            if (($guestClaims['purpose'] ?? '') === 'telemed_guest'
                && (int) ($guestClaims['appointment_id'] ?? 0) === $appointment_id) {
                $role = 'patient';
                $via_guest_token = true;
            }
        } catch (Throwable $e) {
            // invalid/expired token — falls through to the login redirect below
        }
    }
}

if (!$role) {
    header('Location: ' . BASE_URL . 'login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$stmt = $conn->prepare("SELECT a.*, d.name AS doctor_name, u.name AS user_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE a.id = ? LIMIT 1");
$stmt->bind_param('i', $appointment_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    http_response_code(404);
    exit('Appointment not found.');
}

if ($via_guest_token) {
    // The token itself is already signed and scoped to this exact
    // appointment_id — but only honor it while the appointment is still
    // guest-owned. If it later gets linked to a real account, the guest
    // link should stop working and the patient should log in instead.
    if (!empty($appt['user_id'])) {
        http_response_code(403);
        exit('This appointment is linked to an account. Please log in to join.');
    }
} else {
    $owns_it = ($role === 'doctor' && (int) $appt['doctor_id'] === $entity_id)
        || ($role === 'patient' && (int) $appt['user_id'] === $entity_id);

    if (!$owns_it) {
        http_response_code(403);
        exit('You are not part of this appointment.');
    }
}

if ($appt['appointment_type'] !== 'online') {
    exit('This appointment is not an online consultation.');
}

if (!in_array($appt['status'], ['approved', 'completed'], true)) {
    exit('This appointment is not approved for a video call yet.');
}

if ($appt['meeting_status'] === 'cancelled') {
    exit('This video consultation was cancelled.');
}

// Reuses the room created at booking time (see util/function.php); this is
// just a safety-net fallback for appointments that don't have one yet.
$room = telemedicine_ensure_room($conn, $appointment_id);
if (!$room) {
    exit('This appointment is not an online consultation.');
}
$room_token = $room['token'];

$entity_name = $role === 'doctor'
    ? $appt['doctor_name']
    : ($appt['user_name'] ?: $appt['patient_name'] ?: 'Patient');

$ticket = JWT::issue([
    'purpose'        => 'telemed_join',
    'appointment_id' => $appointment_id,
    'room'           => $room_token,
    'role'           => $role,
    'entity_id'      => $entity_id,
    'name'           => $entity_name,
], TELEMED_SECRET, 21600); // 6 hours — generous enough to survive a page refresh mid-consultation

header('Location: ' . BASE_URL . 'telemedicine/room.php?ticket=' . urlencode($ticket));
exit;
