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
require_once __DIR__ . '/../../lib/DoctorAccess.php';

// Pages reachable even when a doctor hasn't cleared verification +
// subscription yet — the dashboard (to show the gate screen itself),
// the subscribe/payment flow, and account-level actions that don't
// depend on being active.
const DOCTOR_GATE_ALLOWLIST = [
    'doctor-dashboard.php',
    'doctor-logout.php',
    'create-subscription-order.php',
    'verify-subscription-payment.php',
    'payment-history.php',
    'earnings.php',
    'change-password.php',
    'account-settings.php',
    'my-contact.php',
    'delete-account.php',
];

/**
 * @param bool $return_null  If true, return null on auth failure instead of redirecting (for API endpoints).
 */
function doctor_jwt_guard(bool $return_null = false): ?array
{
    $secret = defined('JWT_SECRET') ? JWT_SECRET : '';
    if (!$secret) {
        if ($return_null) return null;
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
        if ($return_null) return null;
        _doctor_redirect_login('session_expired');
    }

    try {
        $payload = JWT::verify($token, $secret);
    } catch (RuntimeException $e) {
        // Try refresh token before giving up
        $payload = _try_refresh_doctor_token($secret);
        if (!$payload) {
            if ($return_null) return null;
            _doctor_redirect_login('session_expired');
        }
    }

    if (($payload['role'] ?? '') !== 'doctor') {
        if ($return_null) return null;
        _doctor_redirect_login('unauthorized');
    }

    // Populate session so session-based helpers (abdm_*.php) can read doctor_id
    if (session_status() === PHP_SESSION_NONE) session_start();
    $doctorId = (int) ($payload['sub'] ?? ($payload['doctor_id'] ?? 0));
    $_SESSION['doctor_id'] = $doctorId;

    // Activation gate — verify -> subscribe -> full access. Doesn't touch
    // login itself (above), only what a signed-in-but-not-yet-active
    // doctor can reach. doctor-dashboard.php renders the gate screen
    // itself when $payload['_active_ok'] is false.
    $payload['_active_ok'] = true;
    if ($doctorId) {
        global $conn;
        $gateStmt = $conn->prepare("SELECT id, is_verified, grace_period_until FROM doctors WHERE id = ? LIMIT 1");
        $gateStmt->bind_param('i', $doctorId);
        $gateStmt->execute();
        $gateDoctor = $gateStmt->get_result()->fetch_assoc();

        if ($gateDoctor) {
            $activeOk = doctor_qualifies_active($conn, $gateDoctor);
            $payload['_active_ok'] = $activeOk;

            if (!$activeOk) {
                $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
                if (!in_array($currentScript, DOCTOR_GATE_ALLOWLIST, true)) {
                    // API endpoints (patient management, document upload, etc.)
                    // are exactly the "sab kuch" a gated doctor shouldn't get —
                    // block them the same way an invalid token would: a graceful
                    // null for $return_null callers (so they emit their own JSON
                    // error instead of receiving a raw redirect), a hard redirect
                    // to the gate screen for full-page loads.
                    if ($return_null) {
                        return null;
                    }
                    header('Location: ' . BASE_URL . 'doctor/doctor-dashboard.php');
                    exit();
                }
            }
        }
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
