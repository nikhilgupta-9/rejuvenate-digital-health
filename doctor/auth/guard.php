<?php
/**
 * JWT Auth Guard for Doctor Panel.
 * Include this at the top of every doctor panel page.
 * On success: sets $jwt_doctor (array of claims).
 * On failure: redirects to doctor-login.php.
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/connect.php';
}
require_once __DIR__ . '/../../lib/JWT.php';

function doctor_jwt_guard(): array
{
    $secret = defined('JWT_SECRET') ? JWT_SECRET : '';
    if (!$secret) {
        header('Location: ' . BASE_URL . 'doctor-login.php?err=config');
        exit();
    }

    $token = $_COOKIE['rdh_doctor_token'] ?? '';

    // Try Bearer header too (for AJAX calls)
    if (!$token) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
        }
    }

    if (!$token) {
        _doctor_redirect_login('session_expired');
    }

    try {
        $payload = JWT::verify($token, $secret);
    } catch (RuntimeException $e) {
        // Try refresh token before giving up
        $payload = _try_refresh_doctor_token($secret);
        if (!$payload) {
            _doctor_redirect_login('session_expired');
        }
    }

    if (($payload['role'] ?? '') !== 'doctor') {
        _doctor_redirect_login('unauthorized');
    }

    return $payload;
}

function _try_refresh_doctor_token(string $secret): ?array
{
    global $conn;
    $refresh = $_COOKIE['rdh_doctor_refresh'] ?? '';
    if (!$refresh) return null;

    $hash = hash('sha256', $refresh);
    $stmt = $conn->prepare("
        SELECT rt.*, d.id as doc_id, d.name, d.email, d.hpr_verified, d.status
        FROM jwt_refresh_tokens rt
        JOIN doctors d ON rt.entity_id = d.id
        WHERE rt.token_hash = ? AND rt.entity_type = 'doctor'
          AND rt.revoked = 0 AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return null;

    if ($row['status'] !== 'Active') return null;

    // Rotate refresh token
    $newRefresh = bin2hex(random_bytes(32));
    $newHash    = hash('sha256', $newRefresh);
    $exp        = date('Y-m-d H:i:s', strtotime('+7 days'));
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua         = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $conn->begin_transaction();
    $rev = $conn->prepare("UPDATE jwt_refresh_tokens SET revoked=1, revoked_at=NOW() WHERE id=?");
    $rev->bind_param('i', $row['id']);
    $rev->execute();

    $ins = $conn->prepare("INSERT INTO jwt_refresh_tokens (entity_type,entity_id,token_hash,expires_at,ip_address,user_agent) VALUES ('doctor',?,?,?,?,?)");
    $ins->bind_param('issss', $row['doc_id'], $newHash, $exp, $ip, $ua);
    $ins->execute();
    $conn->commit();

    $payload = [
        'sub'          => $row['doc_id'],
        'role'         => 'doctor',
        'name'         => $row['name'],
        'email'        => $row['email'],
        'hpr_verified' => (bool)$row['hpr_verified'],
    ];
    $newAccessToken = JWT::issue($payload, $secret, 900);

    // Set new cookies
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('rdh_doctor_token',   $newAccessToken, [
        'expires'  => time() + 900,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    setcookie('rdh_doctor_refresh', $newRefresh, [
        'expires'  => time() + 604800,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    return array_merge($payload, ['exp' => time() + 900]);
}

function _doctor_redirect_login(string $reason): void
{
    // Clear stale cookies
    setcookie('rdh_doctor_token',   '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
    setcookie('rdh_doctor_refresh', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
    header('Location: ' . BASE_URL . 'doctor-login.php?err=' . $reason);
    exit();
}
