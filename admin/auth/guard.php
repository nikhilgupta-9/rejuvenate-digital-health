<?php
/**
 * JWT Auth Guard for Admin Panel.
 *
 * Call admin_jwt_guard() at the top of every protected admin page,
 * AFTER db-conn.php has been included (needs $conn for refresh lookups
 * and $_ENV['JWT_SECRET'], which db-conn.php's dotenv load populates).
 *
 * On success: populates $_SESSION (admin_logged_in, admin_id, admin_user,
 *             admin_role) so the rest of the page — written against
 *             $_SESSION — works completely unchanged, and returns the
 *             JWT payload.
 * On failure: redirects to auth/login.php (or returns null if $return_null).
 */

if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
}
require_once __DIR__ . '/../../lib/JWT.php';

function admin_jwt_guard(bool $return_null = false): ?array
{
    global $conn;

    if (session_status() === PHP_SESSION_NONE) session_start();

    $secret = defined('JWT_SECRET') ? JWT_SECRET : '';
    if (!$secret) {
        if ($return_null) return null;
        header('Location: auth/login.php?err=config');
        exit();
    }

    $token = $_COOKIE['rdh_admin_token'] ?? '';
    if (!$token) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
        }
    }

    if (!$token) {
        if ($return_null) return null;
        _admin_redirect_login('session_expired');
    }

    try {
        $payload = JWT::verify($token, $secret);
    } catch (RuntimeException $e) {
        // Try refresh token before giving up
        $payload = _try_refresh_admin_token($secret);
        if (!$payload) {
            if ($return_null) return null;
            _admin_redirect_login('session_expired');
        }
    }

    if (($payload['role'] ?? '') !== 'admin') {
        if ($return_null) return null;
        _admin_redirect_login('unauthorized');
    }

    // Populate session so every existing admin page (written against
    // $_SESSION['admin_*']) continues to work without modification.
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']        = $payload['sub'] ?? null;
    $_SESSION['admin_user']      = $payload['username'] ?? '';
    $_SESSION['admin_role']      = $payload['admin_role'] ?? '';

    return $payload;
}

function _try_refresh_admin_token(string $secret): ?array
{
    global $conn;
    $refresh = $_COOKIE['rdh_admin_refresh'] ?? '';
    if (!$refresh) return null;

    $hash = hash('sha256', $refresh);
    $stmt = $conn->prepare("
        SELECT rt.*, a.id as adm_id, a.username, a.role, a.status
        FROM jwt_refresh_tokens rt
        JOIN admin_user a ON rt.entity_id = a.id
        WHERE rt.token_hash = ? AND rt.entity_type = 'admin'
          AND rt.revoked = 0 AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return null;

    if ($row['status'] !== 'active') return null;

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

    $ins = $conn->prepare("INSERT INTO jwt_refresh_tokens (entity_type,entity_id,token_hash,expires_at,ip_address,user_agent) VALUES ('admin',?,?,?,?,?)");
    $ins->bind_param('issss', $row['adm_id'], $newHash, $exp, $ip, $ua);
    $ins->execute();
    $conn->commit();

    $payload = [
        'sub'        => (int) $row['adm_id'],
        'role'       => 'admin',
        'username'   => $row['username'],
        'admin_role' => $row['role'],
    ];
    $newAccessToken = JWT::issue($payload, $secret, 900);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('rdh_admin_token', $newAccessToken, [
        'expires'  => time() + 900,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    setcookie('rdh_admin_refresh', $newRefresh, [
        'expires'  => time() + 604800,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    return array_merge($payload, ['exp' => time() + 900]);
}

function _admin_redirect_login(string $reason): void
{
    setcookie('rdh_admin_token',   '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
    setcookie('rdh_admin_refresh', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
    header('Location: auth/login.php?err=' . $reason);
    exit();
}
