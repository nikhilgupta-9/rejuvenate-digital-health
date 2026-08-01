<?php
session_start();
include_once "config/connect.php";
include_once "util/function.php";
include_once "util/auth-helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: login.php"); exit(); }

$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password']   ?? '';
$remember   = !empty($_POST['remember']);

$errors = [];

if (!$identifier) $errors['identifier'] = 'Email / mobile / ID is required';
if (!$password)   $errors['password']   = 'Password is required';

if ($errors) {
    $_SESSION['login_errors']      = $errors;
    $_SESSION['login_identifier']  = htmlspecialchars($identifier);
    header("Location: login.php"); exit();
}

/* ── Find entity across all tables ── */
$found = findByIdentifier($conn, $identifier);

if (!$found) {
    $_SESSION['login_errors']     = ['general' => 'No account found with those details.'];
    $_SESSION['login_identifier'] = htmlspecialchars($identifier);
    header("Location: login.php"); exit();
}

if ($found['role'] === 'pending_member') {
    $school = htmlspecialchars($found['user']['school_name'] ?? '');
    $_SESSION['login_errors'] = ['general' => 'Your account is pending approval from ' . ($school ? "<strong>".$school."</strong>" : 'your school admin') . '. Please wait for approval before logging in.'];
    $_SESSION['login_identifier'] = htmlspecialchars($identifier);
    header("Location: login.php"); exit();
}

$user = $found['user'];
$role = $found['role']; 

/* ── Lock check ── */
if ($lock = checkLock($user)) {
    $_SESSION['login_errors'] = ['general' => $lock];
    header("Location: login.php"); exit();
}

/* ── Password verify ── */
if (empty($user['password']) || !password_verify($password, $user['password'])) {
    $table = match($role) {
        'patient'                    => 'users',
        'school_admin', 'school_staff' => 'school_users',
        'student', 'teacher', 'staff'=> 'school_members',
        'doctor'                     => 'doctors',
        default                      => null,
    };
    if ($table) incrementAttempts($conn, $table, $user['id']);
    $attempts = min(($user['login_attempts'] ?? 0) + 1, 5);
    $remaining = 5 - $attempts;
    $_SESSION['login_errors'] = ['general' => 'Incorrect password.' . ($remaining > 0 ? " $remaining attempts remaining." : ' Account will be locked.')];
    $_SESSION['login_identifier'] = htmlspecialchars($identifier);
    header("Location: login.php"); exit();
}

/* ── Doctor: school_status / active checks ── */
if (in_array($role, ['student','teacher','staff'])) {
    if (($user['school_status'] ?? '') !== 'Active') {
        $_SESSION['login_errors'] = ['general' => 'Your school account is not active. Please contact admin.'];
        header("Location: login.php"); exit();
    }
}

/* ── Patient email-verify gate (existing behaviour) ── */
if ($role === 'patient' && empty($user['email_verified'])) {
    $otp_code  = (string)random_int(100000, 999999);
    $otp_expiry= date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $upd = $conn->prepare("UPDATE users SET otp_code=?, otp_expiry=? WHERE id=?");
    $upd->bind_param('ssi', $otp_code, $otp_expiry, $user['id']); $upd->execute();
    send_otp_email($user['email'], $otp_code);
    $_SESSION['verify_email']  = true;
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['otp_code']      = $otp_code;
    $_SESSION['otp_expiry']    = $otp_expiry;
    $_SESSION['otp_sent_time'] = time();
    header("Location: " . BASE_URL . "verify-otp.php?source=login"); exit();
}

/* ── Reset login attempts ── */
$table = match($role) {
    'patient'                     => 'users',
    'school_admin', 'school_staff'=> 'school_users',
    'student', 'teacher', 'staff' => 'school_members',
    'doctor'                      => 'doctors',
    default                       => null,
};
if ($table) resetAttempts($conn, $table, $user['id']);

/* ── Doctor session token ── */
if ($role === 'doctor') {
    $session_token = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ins = $conn->prepare("INSERT INTO doctor_sessions (doctor_id, session_token, ip_address, user_agent) VALUES (?,?,?,?)");
    $ins->bind_param('isss', $user['id'], $session_token, $ip, $ua); $ins->execute();
    $_SESSION['session_token'] = $session_token;
}

/* ── Set session & redirect ── */
$redirect = setRoleSession($user, $role);

/* ── Remember me (patients only for now) ── */
if ($remember && $role === 'patient') {
    $token  = bin2hex(random_bytes(32));
    $expiry = time() + 30 * 86400;
    setcookie('remember_token', $token, $expiry, '/');
    setcookie('user_id', $user['id'], $expiry, '/');
    $conn->query("UPDATE users SET remember_token='$token' WHERE id=" . (int)$user['id']);
}

header("Location: $redirect"); exit();
