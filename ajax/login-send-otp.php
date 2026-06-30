<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/../util/auth-helper.php';

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$mobile = preg_replace('/\D/', '', trim($data['mobile'] ?? ''));

if (strlen($mobile) !== 10) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 10-digit mobile number']); exit;
}

/* Find user by mobile across all tables */
$found = findByIdentifier($conn, $mobile);

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'No account found with this mobile number']); exit;
}

$user  = $found['user'];
$role  = $found['role'];
$email = $user['email'] ?? '';

if ($lock = checkLock($user)) {
    echo json_encode(['success' => false, 'message' => $lock]); exit;
}

/* Generate & store OTP */
$otp   = generateOtp();
$table = match($role) {
    'patient'                     => 'user',
    'school_admin','school_staff' => 'school_user',
    'student','teacher','staff'   => 'member',
    'doctor'                      => 'doctor',
    default                       => 'user',
};

storeAndSendOtp($conn, $table, $user['id'], $otp, $mobile, $email);

/* Store pending-login info in session so verify step can use it */
$_SESSION['otp_mobile']      = $mobile;
$_SESSION['otp_entity_type'] = $table;
$_SESSION['otp_entity_id']   = $user['id'];

/* Send OTP — email fallback (replace with SMS gateway when available) */
$sent_via = 'email';
if ($email && function_exists('send_otp_email')) {
    @send_otp_email($email, $otp);
}

/* Dev/sandbox mode: include OTP in response for testing */
$debug = ($_ENV['APP_ENV'] ?? 'production') !== 'production';

$resp = [
    'success'    => true,
    'message'    => "OTP sent to your registered email ($email).",
    'role_label' => roleLabel($role),
];
if ($debug) $resp['__debug_otp'] = $otp; // remove in production

echo json_encode($resp);
