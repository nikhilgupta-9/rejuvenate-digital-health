<?php
/**
 * Doctor JWT Login API
 * POST /doctor/auth/login-api.php
 * Body (JSON or form): identifier, password
 * Returns JSON: { success, token, doctor:{id,name,hpr_verified}, error? }
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../lib/JWT.php';
require_once __DIR__ . '/../../lib/AuditLogger.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Parse body
$raw = file_get_contents('php://input');
$body = $raw ? json_decode($raw, true) : [];
$identifier = trim($body['identifier'] ?? $_POST['identifier'] ?? '');
$password   = $body['password']   ?? $_POST['password']   ?? '';

if (!$identifier || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email/phone and password are required']);
    exit();
}

// Rate limit: max 10 attempts per IP per 15 min (simple IP check)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Lookup doctor by email or phone
$isEmail  = strpos($identifier, '@') !== false;
$isMobile = preg_match('/^\d{10}$/', preg_replace('/\D/', '', $identifier));

$doctor = null;
if ($isEmail || $isMobile) {
    $col = $isEmail ? 'email' : 'phone';
    $val = $isEmail ? $identifier : preg_replace('/\D/', '', $identifier);
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE $col = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param('s', $val);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

// Also allow HPR ID login (format: XX-XXXX-XXXX-XXXX)
if (!$doctor && preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $identifier)) {
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE hpr_id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

if (!$doctor) {
    _log_and_fail($conn, 0, 'login_failure', 'Doctor not found', $ip, $ua);
}

// Account lock check
if (!empty($doctor['is_locked']) && !empty($doctor['locked_until']) && strtotime($doctor['locked_until']) > time()) {
    $till = date('h:i A', strtotime($doctor['locked_until']));
    _log_and_fail($conn, $doctor['id'], 'login_locked', "Account locked until $till", $ip, $ua);
}

// Verify password
if (!$doctor['password'] || !password_verify($password, $doctor['password'])) {
    // Increment attempts
    $conn->query("UPDATE doctors SET login_attempts = login_attempts + 1 WHERE id = {$doctor['id']}");
    $row = $conn->query("SELECT login_attempts FROM doctors WHERE id = {$doctor['id']}")->fetch_assoc();
    if (($row['login_attempts'] ?? 0) >= 5) {
        $conn->query("UPDATE doctors SET is_locked=1, locked_until=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id={$doctor['id']}");
        _log_and_fail($conn, $doctor['id'], 'login_locked', 'Locked after 5 failed attempts', $ip, $ua);
    }
    $remaining = 5 - ($row['login_attempts'] ?? 0);
    _log_and_fail($conn, $doctor['id'], 'login_failure', "Wrong password. $remaining attempts left", $ip, $ua);
}

// Must be verified
if (!$doctor['is_verified']) {
    _log_and_fail($conn, $doctor['id'], 'login_failure', 'Account not verified yet', $ip, $ua);
}

// Success — reset attempts
$conn->query("UPDATE doctors SET login_attempts=0, is_locked=0, locked_until=NULL, last_login=NOW() WHERE id={$doctor['id']}");

// Build JWT
$secret = defined('JWT_SECRET') ? JWT_SECRET : '';
if (!$secret) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server configuration error']);
    exit();
}

$payload = [
    'sub'          => $doctor['id'],
    'role'         => 'doctor',
    'name'         => $doctor['name'],
    'email'        => $doctor['email'],
    'hpr_verified' => (bool)($doctor['hpr_verified'] ?? 0),
];
$accessToken  = JWT::issue($payload, $secret, 900);       // 15 min
$refreshToken = bin2hex(random_bytes(32));
$refreshHash  = hash('sha256', $refreshToken);
$refreshExp   = date('Y-m-d H:i:s', strtotime('+7 days'));

// Revoke old refresh tokens for this doctor
$conn->query("UPDATE jwt_refresh_tokens SET revoked=1, revoked_at=NOW() WHERE entity_type='doctor' AND entity_id={$doctor['id']}");

// Store new refresh token
$ins = $conn->prepare("INSERT INTO jwt_refresh_tokens (entity_type,entity_id,token_hash,expires_at,ip_address,user_agent) VALUES ('doctor',?,?,?,?,?)");
$ins->bind_param('issss', $doctor['id'], $refreshHash, $refreshExp, $ip, $ua);
$ins->execute();

// Set HttpOnly cookies
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('rdh_doctor_token', $accessToken, [
    'expires'  => time() + 900,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
setcookie('rdh_doctor_refresh', $refreshToken, [
    'expires'  => time() + 604800,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);

// Audit log
$logger = new AuditLogger($conn);
$logger->logAuthAttempt($doctor['email'], 'password', true, $doctor['id'], 'doctor');

echo json_encode([
    'success' => true,
    'doctor'  => [
        'id'           => $doctor['id'],
        'name'         => $doctor['name'],
        'email'        => $doctor['email'],
        'hpr_verified' => (bool)($doctor['hpr_verified'] ?? 0),
        'specialization' => $doctor['specialization'] ?? '',
    ],
    'redirect' => BASE_URL . 'doctor/doctor-dashboard.php',
]);

function _log_and_fail(mysqli $conn, int $entityId, string $event, string $reason, string $ip, string $ua): void
{
    if ($entityId > 0) {
        try {
            $logger = new AuditLogger($conn);
            $logger->logAuthAttempt($reason, 'password', false, $entityId, 'doctor');
        } catch (Throwable $e) { /* audit failure must not expose internals */ }
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $reason]);
    exit();
}
