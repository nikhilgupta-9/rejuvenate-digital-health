<?php
/**
 * Resend the identity-verification OTP for the student currently
 * pending verification in this doctor's session.
 */
include_once(__DIR__ . "/../../config/connect.php");
require_once(__DIR__ . "/../auth/guard.php");
require_once(__DIR__ . "/../../util/auth-helper.php");

header('Content-Type: application/json');

$jwt = doctor_jwt_guard(true);
if (!$jwt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$member_id = (int)($_SESSION['school_lookup_member_id'] ?? 0);
if (!$member_id) {
    echo json_encode(['success' => false, 'error' => 'Your search session has expired. Please search again.']);
    exit;
}

$stmt = $conn->prepare("SELECT name, email FROM school_members WHERE id=? AND type='Student' AND status='Active' LIMIT 1");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student || empty($student['email'])) {
    echo json_encode(['success' => false, 'error' => 'Unable to resend OTP. Please search again.']);
    exit;
}

$otp = generateOtp();
storeAndSendOtp($conn, 'school_doctor_lookup', $member_id, $otp, '', $student['email'], $student['name']);
$_SESSION['school_lookup_attempts'] = 0;

echo json_encode(['success' => true, 'message' => 'A new OTP has been sent.']);
