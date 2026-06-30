<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/../util/auth-helper.php';

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$mobile = preg_replace('/\D/', '', trim($data['mobile'] ?? ''));
$otp    = trim($data['otp'] ?? '');

if (!$mobile || !$otp) {
    echo json_encode(['success' => false, 'message' => 'Mobile and OTP are required']); exit;
}

/* Verify stored OTP */
$otpRow = verifyStoredOtp($conn, $mobile, $otp);
if (!$otpRow) {
    echo json_encode(['success' => false, 'message' => 'Incorrect or expired OTP. Please try again.']); exit;
}

$entityType = $otpRow['entity_type'];
$entityId   = (int)$otpRow['entity_id'];

/* Re-fetch user from correct table */
$found = null;
switch ($entityType) {
    case 'user':
        $s = $conn->prepare("SELECT * FROM users WHERE id=? AND status='Active' LIMIT 1");
        $s->bind_param('i', $entityId); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) $found = ['user' => $r, 'role' => 'patient'];
        break;

    case 'school_user':
        $s = $conn->prepare("SELECT su.*, s.school_name FROM school_users su JOIN schools s ON su.school_id=s.id WHERE su.id=? AND su.status='Active' LIMIT 1");
        $s->bind_param('i', $entityId); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) {
            $role  = $r['role'] === 'school_admin' ? 'school_admin' : 'school_staff';
            $found = ['user' => $r, 'role' => $role];
        }
        break;

    case 'member':
        $s = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=? AND sm.status='Active' LIMIT 1");
        $s->bind_param('i', $entityId); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) {
            $role  = match(strtolower($r['type'])) { 'student' => 'student', 'teacher' => 'teacher', default => 'staff' };
            $found = ['user' => $r, 'role' => $role];
        }
        break;

    case 'doctor':
        $s = $conn->prepare("SELECT * FROM doctors WHERE id=? AND status='Active' LIMIT 1");
        $s->bind_param('i', $entityId); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) $found = ['user' => $r, 'role' => 'doctor'];
        break;
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Account not found or inactive.']); exit;
}

/* Reset login attempts & update last_login */
$table = match($found['role']) {
    'patient'                     => 'users',
    'school_admin','school_staff' => 'school_users',
    'student','teacher','staff'   => 'school_members',
    'doctor'                      => 'doctors',
    default                       => null,
};
if ($table) resetAttempts($conn, $table, $found['user']['id']);

/* Doctor session token */
if ($found['role'] === 'doctor') {
    $st = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ins = $conn->prepare("INSERT INTO doctor_sessions (doctor_id, session_token, ip_address, user_agent) VALUES (?,?,?,?)");
    $ins->bind_param('isss', $found['user']['id'], $st, $ip, $ua); $ins->execute();
    $_SESSION['session_token'] = $st;
}

$redirect = setRoleSession($found['user'], $found['role']);

echo json_encode(['success' => true, 'redirect' => $redirect, 'role' => roleLabel($found['role'])]);
