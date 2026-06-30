<?php
/**
 * Doctor JWT Logout
 * Revokes refresh token and clears cookies.
 */
require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../lib/JWT.php';

$secret = defined('JWT_SECRET') ? JWT_SECRET : '';

// Revoke refresh token in DB
$refresh = $_COOKIE['rdh_doctor_refresh'] ?? '';
if ($refresh && $conn) {
    $hash = hash('sha256', $refresh);
    $stmt = $conn->prepare("UPDATE jwt_refresh_tokens SET revoked=1, revoked_at=NOW() WHERE token_hash=? AND entity_type='doctor'");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
}

// Clear cookies
$params = ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict'];
setcookie('rdh_doctor_token',   '', $params);
setcookie('rdh_doctor_refresh', '', $params);

// Audit log
if ($secret && !empty($_COOKIE['rdh_doctor_token'])) {
    try {
        $p = JWT::decode($_COOKIE['rdh_doctor_token']);
        if (!empty($p['sub'])) {
            require_once __DIR__ . '/../../lib/AuditLogger.php';
            $logger = new AuditLogger($conn);
            $logger->logAuthAttempt('logout', 'logout', true, (int)$p['sub'], 'doctor');
        }
    } catch (Throwable $e) { /* ignore */ }
}

header('Location: ' . BASE_URL . 'doctor-login.php');
exit();
