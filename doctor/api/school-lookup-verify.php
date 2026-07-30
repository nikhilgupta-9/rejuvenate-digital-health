<?php
/**
 * Verify the OTP sent by school-lookup-search.php.
 * On success, marks the matched student as "verified" in the doctor's
 * session for a limited time so student-profile.php can be opened.
 */
include_once(__DIR__ . "/../../config/connect.php");
require_once(__DIR__ . "/../auth/guard.php");

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

if (($_SESSION['school_lookup_attempts'] ?? 0) >= 5) {
    unset($_SESSION['school_lookup_member_id'], $_SESSION['school_lookup_attempts'], $_SESSION['school_lookup_started']);
    echo json_encode(['success' => false, 'error' => 'Too many incorrect attempts. Please search again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$otp   = trim($input['otp'] ?? '');

if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'error' => 'Enter the complete 6-digit OTP.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM login_otps
    WHERE entity_type='school_doctor_lookup' AND entity_id=? AND otp_code=? AND used=0 AND otp_expiry > NOW()
    ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('is', $member_id, $otp);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    $_SESSION['school_lookup_attempts'] = ($_SESSION['school_lookup_attempts'] ?? 0) + 1;
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP.']);
    exit;
}

$upd = $conn->prepare("UPDATE login_otps SET used=1 WHERE id=?");
$upd->bind_param('i', $row['id']);
$upd->execute();

$_SESSION['school_verified_members'][$member_id] = time() + 900; // 15 minutes
unset($_SESSION['school_lookup_member_id'], $_SESSION['school_lookup_attempts'], $_SESSION['school_lookup_started']);

echo json_encode([
    'success'  => true,
    'redirect' => BASE_URL . 'doctor/student-profile.php?id=' . $member_id,
]);
