<?php
/**
 * ABHA / Aadhaar login dispatcher (pre-login, called from login.php).
 *
 * Hardened to match ajax/abdm-api.php:
 *   - CSRF token required on every action (Security::verifyCsrf)
 *   - per-IP rate limiting (OTP-send stricter than verify)
 *   - every attempt written to abdm_audit_logs (AuditLogger)
 *   - confirm_*: the ABDM-authenticated identity MUST map to the same
 *     local account we looked up — otherwise the login is rejected.
 */
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/../config/abdm.php';
include_once __DIR__ . '/../lib/AbdmApi.php';
include_once __DIR__ . '/../lib/Security.php';
include_once __DIR__ . '/../lib/AuditLogger.php';
include_once __DIR__ . '/../util/auth-helper.php';

Security::setSecurityHeaders();

if (!ABDM_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'ABDM credentials not configured.']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = is_string($data['action'] ?? null) ? $data['action'] : '';
$logger = new AuditLogger($conn);
$ip     = Security::clientIp();

function ok(array $p = []): void { echo json_encode(['success' => true] + $p); exit; }
function fail(string $m, int $code = 200): void {
    if ($code !== 200) http_response_code($code);
    echo json_encode(['success' => false, 'message' => $m]); exit;
}

/* ── CSRF: every state-changing call must carry a valid token ── */
if (!Security::verifyCsrf((string)($data['_csrf'] ?? ''))) {
    $logger->logApiAnomaly('ajax/login-abdm.php', $action, 'CSRF token invalid/missing', 0, 403);
    fail('Security token invalid. Please refresh the page and try again.', 403);
}

/* ── Rate limiting (per IP, session-backed sliding window) ── */
// Global cap across all ABHA/Aadhaar login traffic from this client.
if (Security::isRateLimited(Security::rlKey('login_abdm', $ip), 20, 300)) {
    $logger->logApiAnomaly('ajax/login-abdm.php', $action, 'Rate limit exceeded (global)', 0, 429);
    fail('Too many requests. Please wait a few minutes and try again.', 429);
}
$isOtpSend = in_array($action, ['init_abha_login', 'init_aadhaar_login'], true);
$isVerify  = in_array($action, ['confirm_abha_login', 'confirm_aadhaar_login'], true);
if ($isOtpSend && Security::isRateLimited(Security::rlKey('login_abdm_otp', $ip), 5, 600)) {
    $logger->logApiAnomaly('ajax/login-abdm.php', $action, 'OTP-send rate limit exceeded', 0, 429);
    fail('Too many OTP requests. Please wait 10 minutes before trying again.', 429);
}
if ($isVerify && Security::isRateLimited(Security::rlKey('login_abdm_verify', $ip), 10, 600)) {
    $logger->logApiAnomaly('ajax/login-abdm.php', $action, 'OTP-verify rate limit exceeded', 0, 429);
    fail('Too many attempts. Please wait 10 minutes before trying again.', 429);
}

/**
 * Best-effort extraction of the ABHA number that ABDM actually authenticated,
 * from the verify response and — as a fallback — the profile endpoint.
 * Returns a normalised XX-XXXX-XXXX-XXXX string, or '' if it cannot be determined.
 */
function abdm_authenticated_abha(AbdmApi $abdm, array $res, string $xToken): string
{
    $candidates = [];
    foreach (['ABHANumber', 'abhaNumber', 'healthIdNumber'] as $k) {
        if (!empty($res[$k])) $candidates[] = (string)$res[$k];
    }
    if (!empty($res['ABHAProfile']['ABHANumber'])) $candidates[] = (string)$res['ABHAProfile']['ABHANumber'];
    foreach (($res['accounts'] ?? []) as $acc) {
        if (!empty($acc['ABHANumber'])) $candidates[] = (string)$acc['ABHANumber'];
    }

    if (!$candidates && $xToken) {
        try {
            $profile = $abdm->getProfile($xToken);
            foreach (['ABHANumber', 'abhaNumber', 'healthIdNumber'] as $k) {
                if (!empty($profile[$k])) { $candidates[] = (string)$profile[$k]; break; }
            }
        } catch (Throwable $e) {
            error_log('login-abdm.php profile fetch failed: ' . $e->getMessage());
        }
    }

    foreach ($candidates as $c) {
        $fmt = AbdmApi::formatAbhaNumber($c);
        if (preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $fmt)) return $fmt;
    }
    return '';
}

/** Same normalisation findByAbha() applies, so the two sides compare like-for-like. */
function normalise_abha(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw);
    if (strlen($digits) === 14) {
        return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4) . '-' . substr($digits, 10, 4);
    }
    return trim($raw);
}

/**
 * ABDM v3 login Step 3 — exchange the /profile/login/verify "Transfer" token for
 * the real user X-token via /profile/login/verify/user.
 *
 * confirmAuth() (=> /profile/login/verify) returns a short-lived typ:"Transfer"
 * token plus an `accounts` list, NOT a usable X-token. Handing the Transfer token
 * to /profile/account returns HTTP 401 ABDM-1094 "X-token expired". This picks
 * the ABHA to open (the one we expect, else the first ABDM returned) and returns
 * the real X-token — or '' if the exchange could not be completed.
 */
function abdm_exchange_x_token(AbdmApi $abdm, array $verifyRes, string $txnId, string $transferToken, string $preferredAbha = ''): string
{
    $accounts = $verifyRes['accounts'] ?? [];
    $want     = preg_replace('/\D/', '', $preferredAbha);

    $abha = '';
    foreach ($accounts as $acc) {
        $have = preg_replace('/\D/', '', (string)($acc['ABHANumber'] ?? ''));
        if ($have !== '' && $want !== '' && $have === $want) { $abha = (string)$acc['ABHANumber']; break; }
    }
    if ($abha === '') {
        $abha = (string)($accounts[0]['ABHANumber'] ?? $preferredAbha);
    }
    if ($abha === '') return '';

    $sel = $abdm->verifyUserLogin($txnId, AbdmApi::formatAbhaNumber($abha), $transferToken);
    if (!AbdmApi::wasSuccessful($sel)) return '';
    return $sel['token'] ?? $sel['tokens']['id_token'] ?? $sel['tokens']['token'] ?? '';
}

try {
    $abdm = new AbdmApi();

    switch ($action) {

        /* ── ABHA Login: Step 1 — initiate auth via ABDM ── */
        case 'init_abha_login':
            $abhaId     = trim($data['abha_id'] ?? '');
            $authMethod = ($data['auth_method'] ?? 'MOBILE_OTP') === 'AADHAAR_OTP' ? 'AADHAAR_OTP' : 'MOBILE_OTP';
            if (!$abhaId) fail('ABHA number required');

            /* First check this ABHA is linked to an account on our platform */
            if (!findByAbha($conn, $abhaId)) {
                $logger->logAuthAttempt($abhaId, 'abha', false, 0, 'user');
                fail('No account linked to this ABHA ID on our platform. Please log in with email/password first and link your ABHA.');
            }

            $otpSystem = ($authMethod === 'AADHAAR_OTP') ? 'aadhaar' : 'abdm';
            $res = $abdm->initAuth($abhaId, 'abha-number', $otpSystem);
            if (empty($res['txnId'])) {
                $logger->logApiAnomaly('ajax/login-abdm.php', $action, AbdmApi::extractError($res, 'auth init failed'), 0, (int)($res['_http'] ?? 0));
                fail(AbdmApi::extractError($res, 'ABDM auth init failed'));
            }

            $_SESSION['abha_login_txn']    = $res['txnId'];
            $_SESSION['abha_login_id']     = $abhaId;
            $_SESSION['abha_login_method'] = $authMethod;

            ok(['txnId' => $res['txnId']]);

        /* ── ABHA Login: Step 2 — verify OTP, bind identity, set session ── */
        case 'confirm_abha_login':
            $otp        = trim($data['otp'] ?? '');
            $txnId      = $_SESSION['abha_login_txn'] ?? '';
            $abhaId     = $_SESSION['abha_login_id']  ?? '';

            if (!$otp || !$txnId || !$abhaId) fail('OTP and session required. Please start again.');

            $res         = $abdm->confirmAuth($otp, $txnId);
            $verifyTxn   = $res['txnId'] ?? $txnId;
            $transferTok = $res['token'] ?? $res['ABHAToken'] ?? $res['userToken']
                         ?? $res['tokens']['token'] ?? $res['tokens']['id_token'] ?? '';
            if (!$transferTok) {
                $logger->logAuthAttempt($abhaId, 'abha', false, 0, 'user');
                fail(AbdmApi::extractError($res, 'OTP verification failed'));
            }

            /* ── Step 3 — swap the "Transfer" token for the real X-token.
                  /profile/login/verify never returns a usable X-token; the
                  Transfer token 401s ("X-token expired", ABDM-1094) against
                  /profile/account. Keep the Transfer token as a fallback so the
                  accounts[]-based identity check below can still run. ── */
            $xToken = abdm_exchange_x_token($abdm, $res, $verifyTxn, $transferTok, $abhaId) ?: $transferTok;

            $found = findByAbha($conn, $abhaId);
            if (!$found) {
                $logger->logAuthAttempt($abhaId, 'abha', false, 0, 'user');
                fail('Account not found. Please log in with email/password first and link your ABHA.');
            }

            /* ── Identity binding — the ABHA number ABDM just authenticated MUST
                  be the one on the account we're about to sign in. If we can't
                  determine it, fail closed. ── */
            $authAbha  = abdm_authenticated_abha($abdm, $res, $xToken);
            $storedAbha = normalise_abha((string)($found['user']['abha_id'] ?? ''));
            if ($authAbha === '' || $storedAbha === '' || !hash_equals($storedAbha, $authAbha)) {
                $logger->logApiAnomaly(
                    'ajax/login-abdm.php',
                    $action,
                    'ABHA identity mismatch: authenticated="' . ($authAbha ?: 'unknown') . '" account="' . ($storedAbha ?: 'none') . '"',
                    (int)($found['user']['id'] ?? 0),
                    403
                );
                $logger->logAuthAttempt($abhaId, 'abha', false, (int)($found['user']['id'] ?? 0), $found['role'] === 'patient' ? 'user' : 'member');
                fail('The ABHA account that was verified does not match this login. Please try again.', 403);
            }

            $table = match($found['role']) {
                'patient'                     => 'users',
                'school_admin','school_staff' => 'school_users',
                'student','teacher','staff'   => 'school_members',
                'doctor'                      => 'doctors',
                default                       => null,
            };
            if ($table) resetAttempts($conn, $table, $found['user']['id']);

            unset($_SESSION['abha_login_txn'], $_SESSION['abha_login_id'], $_SESSION['abha_login_method']);
            session_regenerate_id(true);

            $entityType = $found['role'] === 'patient' ? 'user' : ($found['role'] === 'doctor' ? 'doctor' : 'member');
            $logger->logAuthAttempt($abhaId, 'abha', true, (int)$found['user']['id'], $entityType);

            $redirect = setRoleSession($found['user'], $found['role']);
            Security::clearRateLimit(Security::rlKey('login_abdm_verify', $ip));
            ok(['redirect' => $redirect, 'role' => roleLabel($found['role'])]);

        /* ── Aadhaar Login: Step 1 — generate OTP via ABDM ── */
        case 'init_aadhaar_login':
            $aadhaar = preg_replace('/\D/', '', $data['aadhaar'] ?? '');
            if (strlen($aadhaar) !== 12) fail('Valid 12-digit Aadhaar required');

            /* Aadhaar must already be on file against an account */
            if (!findByAadhaar($conn, $aadhaar)) {
                $logger->logAuthAttempt('[MASKED_UID]', 'aadhaar_login', false, 0, 'user');
                fail('Aadhaar not registered on our platform. Please log in with email/password instead.');
            }

            $res = $abdm->generateAadhaarOtp($aadhaar);
            if (empty($res['txnId'])) {
                $logger->logApiAnomaly('ajax/login-abdm.php', $action, AbdmApi::extractError($res, 'Aadhaar OTP failed'), 0, (int)($res['_http'] ?? 0));
                fail(AbdmApi::extractError($res, 'Could not send Aadhaar OTP'));
            }

            $_SESSION['aadhaar_login_txn']    = $res['txnId'];
            $_SESSION['aadhaar_login_number'] = $aadhaar;

            ok(['txnId' => $res['txnId'], 'maskedMobile' => $res['mobileNumber'] ?? $res['mobile'] ?? '**']);

        /* ── Aadhaar Login: Step 2 — verify OTP, bind identity, set session ── */
        case 'confirm_aadhaar_login':
            $otp     = trim($data['otp'] ?? '');
            $txnId   = $_SESSION['aadhaar_login_txn']    ?? '';
            $aadhaar = $_SESSION['aadhaar_login_number'] ?? '';

            if (!$otp || !$txnId || !$aadhaar) fail('OTP and session required');

            $res         = $abdm->confirmAuth($otp, $txnId);
            $verifyTxn   = $res['txnId'] ?? $txnId;
            $transferTok = $res['token'] ?? $res['ABHAToken'] ?? $res['userToken']
                         ?? $res['tokens']['token'] ?? $res['tokens']['id_token'] ?? '';
            if (!$transferTok) {
                $logger->logAuthAttempt('[MASKED_UID]', 'aadhaar_login', false, 0, 'user');
                fail(AbdmApi::extractError($res, 'Aadhaar OTP invalid'));
            }

            $found = findByAadhaar($conn, $aadhaar);
            if (!$found) {
                $logger->logAuthAttempt('[MASKED_UID]', 'aadhaar_login', false, 0, 'user');
                fail('Account not found for this Aadhaar.');
            }

            /* ── Step 3 — swap the "Transfer" token for the real X-token (see
                  confirm_abha_login). Prefer the ABHA already on the account. ── */
            $xToken = abdm_exchange_x_token(
                $abdm, $res, $verifyTxn, $transferTok,
                (string)($found['user']['abha_id'] ?? '')
            ) ?: $transferTok;

            /* ── Identity binding ──
               If the account already has an ABHA number on file, the ABHA that
               ABDM just authenticated must match it. If the account has no ABHA
               on file, we still require ABDM to have returned a real identity
               payload (so a bare/echoed token can't pass). */
            $authAbha   = abdm_authenticated_abha($abdm, $res, $xToken);
            $storedAbha = normalise_abha((string)($found['user']['abha_id'] ?? ''));
            $identityOk = $storedAbha !== ''
                ? ($authAbha !== '' && hash_equals($storedAbha, $authAbha))
                : ($authAbha !== '');
            if (!$identityOk) {
                $logger->logApiAnomaly(
                    'ajax/login-abdm.php',
                    $action,
                    'Aadhaar login identity check failed: authenticated="' . ($authAbha ?: 'unknown') . '" account="' . ($storedAbha ?: 'none') . '"',
                    (int)($found['user']['id'] ?? 0),
                    403
                );
                $logger->logAuthAttempt('[MASKED_UID]', 'aadhaar_login', false, (int)($found['user']['id'] ?? 0), $found['role'] === 'patient' ? 'user' : 'member');
                fail('Could not confirm your ABHA identity. Please try another login method.', 403);
            }

            $table = match($found['role']) {
                'patient'                     => 'users',
                'school_admin','school_staff' => 'school_users',
                'student','teacher','staff'   => 'school_members',
                'doctor'                      => 'doctors',
                default                       => null,
            };
            if ($table) resetAttempts($conn, $table, $found['user']['id']);

            unset($_SESSION['aadhaar_login_txn'], $_SESSION['aadhaar_login_number']);
            session_regenerate_id(true);

            $entityType = $found['role'] === 'patient' ? 'user' : ($found['role'] === 'doctor' ? 'doctor' : 'member');
            $logger->logAuthAttempt('[MASKED_UID]', 'aadhaar_login', true, (int)$found['user']['id'], $entityType);

            $redirect = setRoleSession($found['user'], $found['role']);
            Security::clearRateLimit(Security::rlKey('login_abdm_verify', $ip));
            ok(['redirect' => $redirect, 'role' => roleLabel($found['role'])]);

        default:
            fail('Unknown action');
    }
} catch (RuntimeException $e) {
    $logger->logApiAnomaly('ajax/login-abdm.php', $action, $e->getMessage(), 0, 500);
    fail($e->getMessage());
} catch (Throwable $e) {
    error_log('login-abdm.php error: ' . $e->getMessage());
    $logger->logApiAnomaly('ajax/login-abdm.php', $action, get_class($e) . ': ' . $e->getMessage(), 0, 500);
    fail('ABDM service unavailable. Please try another login method.');
}
