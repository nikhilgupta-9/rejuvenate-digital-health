<?php
/**
 * util/otp-service.php — one place every registration flow calls to send and
 * verify a mobile OTP over WhatsApp + email, before an account exists.
 *
 *   otp_send($role, $mobile, $email, $name)   -> ['success'=>bool, 'channels'=>[...], 'debug_otp'=>?, 'error'=>?]
 *   otp_verify($role, $mobile, $otp)          -> ['success'=>bool, 'token'=>?, 'error'=>?]
 *   otp_consume_token($role, $mobile, $token) -> bool   (final "create account" gate)
 *
 * Store: registration_otps  (see database/migration_whatsapp_otp.sql)
 * Rules: 6-digit OTP, 10-min expiry, max 5 wrong attempts, 60s resend cooldown,
 *        max 5 sends/hour, verify-token valid 15 min and single-use.
 *
 * Login OTP is a separate flow (login_otps / util/auth-helper.php) and is not
 * handled here.
 */

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/auth-helper.php';           // generateOtp(), send_otp_email() (via mail_config)
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../lib/WhatsAppOtp.php';
require_once __DIR__ . '/../lib/AuditLogger.php';

const OTP_TTL_SECONDS      = 600;   // OTP lifetime
const OTP_TOKEN_TTL_SECONDS = 900;  // verify-token lifetime
const OTP_MAX_ATTEMPTS     = 5;
const OTP_RESEND_COOLDOWN  = 60;    // seconds between sends
const OTP_MAX_SENDS_PER_HR = 5;

const OTP_ALLOWED_ROLES = ['patient', 'doctor', 'student', 'teacher', 'school_admin'];

function otp_valid_role(string $role): bool
{
    return in_array($role, OTP_ALLOWED_ROLES, true);
}

/**
 * Is this mobile already tied to an account for this role?
 * Only patient (users.mobile) and doctor (doctors.phone) are enforced here —
 * school member phones are not unique in this system.
 */
function registration_mobile_exists(string $role, string $mobile): bool
{
    global $conn;
    $mobile = preg_replace('/\D/', '', $mobile);

    if ($role === 'patient') {
        $s = $conn->prepare("SELECT 1 FROM users WHERE mobile=? LIMIT 1");
    } elseif ($role === 'doctor') {
        $s = $conn->prepare("SELECT 1 FROM doctors WHERE phone=? LIMIT 1");
    } else {
        return false;
    }
    $s->bind_param('s', $mobile);
    $s->execute();
    return (bool)$s->get_result()->fetch_row();
}

function _otp_hash(string $otp, string $mobile): string
{
    return hash('sha256', $otp . '|' . $mobile);
}

function _otp_audit(string $method, bool $success, string $mobile, string $role, int $accessorId = 0): void
{
    try {
        global $conn;
        $log = new AuditLogger($conn);
        $log->logAuthAttempt($mobile, $method, $success, $accessorId, $role);
    } catch (\Throwable $e) {
        error_log('[otp-service] audit failed: ' . $e->getMessage());
    }
}

/**
 * Generate + store an OTP and deliver it over WhatsApp and email.
 *
 * @param string      $accessorId  optional staff id (doctor/admin) for the audit trail
 */
function otp_send(string $role, string $mobile, ?string $email, string $name = '', int $accessorId = 0): array
{
    global $conn;

    $mobile = preg_replace('/\D/', '', $mobile);
    $email  = $email ? trim($email) : null;

    if (!otp_valid_role($role)) {
        return ['success' => false, 'error' => 'Invalid role.'];
    }
    if (strlen($mobile) !== 10 || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
        return ['success' => false, 'error' => 'Enter a valid 10-digit mobile number.'];
    }
    if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Enter a valid email address.'];
    }

    // Existing row? enforce cooldown + hourly cap.
    $sel = $conn->prepare("SELECT id, resend_count, last_sent_at,
                                  TIMESTAMPDIFF(SECOND, last_sent_at, NOW()) AS since_sent,
                                  TIMESTAMPDIFF(SECOND, created_at, NOW())   AS since_created
                           FROM registration_otps WHERE role=? AND mobile=? LIMIT 1");
    $sel->bind_param('ss', $role, $mobile);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();

    $resendCount = 0;
    if ($row) {
        if ($row['last_sent_at'] !== null && (int)$row['since_sent'] < OTP_RESEND_COOLDOWN) {
            $wait = OTP_RESEND_COOLDOWN - (int)$row['since_sent'];
            return ['success' => false, 'error' => "Please wait {$wait}s before requesting another code."];
        }
        // Reset the hourly window once an hour has passed since first send.
        $resendCount = ((int)$row['since_created'] < 3600) ? (int)$row['resend_count'] : 0;
        if ($resendCount >= OTP_MAX_SENDS_PER_HR) {
            return ['success' => false, 'error' => 'Too many OTP requests. Please try again after an hour.'];
        }
    }

    $otp     = generateOtp();
    $hash    = _otp_hash($otp, $mobile);
    $expiry  = date('Y-m-d H:i:s', time() + OTP_TTL_SECONDS);
    $newCount = $resendCount + 1;

    if ($row) {
        $upd = $conn->prepare("UPDATE registration_otps
            SET email=?, otp_hash=?, otp_expiry=?, attempts=0, resend_count=?,
                verified=0, verified_at=NULL, verify_token=NULL, token_expiry=NULL,
                token_consumed=0, last_sent_at=NOW()
            WHERE id=?");
        $upd->bind_param('sssii', $email, $hash, $expiry, $newCount, $row['id']);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO registration_otps
            (role, mobile, email, otp_hash, otp_expiry, resend_count, last_sent_at)
            VALUES (?,?,?,?,?,?,NOW())");
        $ins->bind_param('sssssi', $role, $mobile, $email, $hash, $expiry, $newCount);
        $ins->execute();
    }

    // Deliver — WhatsApp + email, both every time.
    $wa = wa_send_otp($mobile, $otp);
    $emailSent = false;
    if ($email) {
        try {
            $emailSent = send_otp_email($email, $otp, $name, 'signup');
        } catch (\Throwable $e) {
            error_log('[otp-service] email send failed: ' . $e->getMessage());
        }
    }

    _otp_audit('otp', true, $mobile, $role, $accessorId);

    $isProd = ($_ENV['APP_ENV'] ?? 'production') === 'production';

    $resp = [
        'success'  => true,
        'channels' => ['whatsapp' => (bool)$wa['ok'], 'email' => $emailSent],
        'message'  => 'Verification code sent.',
    ];
    if (!$isProd) {
        $resp['debug_otp'] = $otp;   // dev only — never in production
    }
    return $resp;
}

/**
 * Check a submitted OTP. On success returns a single-use verify token that the
 * final registration handler must pass to otp_consume_token().
 */
function otp_verify(string $role, string $mobile, string $otp): array
{
    global $conn;

    $mobile = preg_replace('/\D/', '', $mobile);
    $otp    = preg_replace('/\D/', '', $otp);

    if (!otp_valid_role($role) || strlen($mobile) !== 10) {
        return ['success' => false, 'error' => 'Invalid request.'];
    }
    if (strlen($otp) !== 6) {
        return ['success' => false, 'error' => 'Enter the 6-digit code.'];
    }

    $sel = $conn->prepare("SELECT id, otp_hash, attempts,
                                  (otp_expiry > NOW()) AS not_expired
                           FROM registration_otps WHERE role=? AND mobile=? LIMIT 1");
    $sel->bind_param('ss', $role, $mobile);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();

    if (!$row) {
        return ['success' => false, 'error' => 'Request a code first.'];
    }
    if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
        _otp_audit('otp', false, $mobile, $role);
        return ['success' => false, 'error' => 'Too many incorrect attempts. Request a new code.'];
    }
    if (!(int)$row['not_expired']) {
        return ['success' => false, 'error' => 'This code has expired. Request a new one.'];
    }

    if (!hash_equals($row['otp_hash'], _otp_hash($otp, $mobile))) {
        $conn->query("UPDATE registration_otps SET attempts = attempts + 1 WHERE id = " . (int)$row['id']);
        $left = OTP_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
        _otp_audit('otp', false, $mobile, $role);
        try {
            (new AuditLogger($conn))->logValidationFailure('otp', 'incorrect registration OTP', 0, $role);
        } catch (\Throwable $e) { /* non-fatal */ }
        return ['success' => false, 'error' => $left > 0
            ? "Incorrect code. {$left} attempt(s) left."
            : 'Too many incorrect attempts. Request a new code.'];
    }

    $token  = bin2hex(random_bytes(16));
    $texp   = date('Y-m-d H:i:s', time() + OTP_TOKEN_TTL_SECONDS);
    $upd = $conn->prepare("UPDATE registration_otps
        SET verified=1, verified_at=NOW(), verify_token=?, token_expiry=?, token_consumed=0
        WHERE id=?");
    $upd->bind_param('ssi', $token, $texp, $row['id']);
    $upd->execute();

    _otp_audit('otp', true, $mobile, $role);

    return ['success' => true, 'token' => $token, 'message' => 'Mobile number verified.'];
}

/**
 * Final gate — call from the handler that actually creates the account/record.
 * Returns true exactly once for a valid, unexpired, not-yet-consumed token.
 */
function otp_consume_token(string $role, string $mobile, string $token): bool
{
    global $conn;

    $mobile = preg_replace('/\D/', '', $mobile);
    $token  = trim($token);
    if (!otp_valid_role($role) || strlen($mobile) !== 10 || $token === '') {
        return false;
    }

    $sel = $conn->prepare("SELECT id FROM registration_otps
        WHERE role=? AND mobile=? AND verified=1 AND token_consumed=0
          AND verify_token=? AND token_expiry > NOW() LIMIT 1");
    $sel->bind_param('sss', $role, $mobile, $token);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if (!$row) {
        return false;
    }

    $conn->query("UPDATE registration_otps SET token_consumed=1 WHERE id=" . (int)$row['id']);
    return true;
}
