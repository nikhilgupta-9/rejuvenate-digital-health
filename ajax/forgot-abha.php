<?php
/**
 * "Forgot ABHA" dispatcher — recover which ABHA account(s) sit behind an
 * Aadhaar or a mobile number. OTP-authenticated, read-only.
 *
 * This is NOT a login: no session flag, no cookie, no redirect. On success it
 * only returns the account list so the user can then sign in normally.
 *
 * Backend: reuses lib/AbdmApi.php as-is —
 *   send_otp   → AbdmApi::initAuth()  (/v3/profile/login/request/otp)
 *   verify_otp → AbdmApi::confirmAuth() (/v3/profile/login/verify) → accounts[]
 * No verifyUserLogin() (we never need the X-token).
 *
 * Hardening mirrors ajax/login-abdm.php: CSRF on every call, per-IP rate
 * limits (OTP-send stricter than verify), every attempt → AuditLogger
 * (event_type 'abha_recovery').
 */
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/../config/abdm.php';
include_once __DIR__ . '/../lib/AbdmApi.php';
include_once __DIR__ . '/../lib/Security.php';
include_once __DIR__ . '/../lib/Validator.php';
include_once __DIR__ . '/../lib/AuditLogger.php';

Security::setSecurityHeaders();

if (!ABDM_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'ABDM service is not configured.']);
    exit;
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

/* ── CSRF ── */
if (!Security::verifyCsrf((string) ($data['_csrf'] ?? ''))) {
    $logger->logApiAnomaly('ajax/forgot-abha.php', $action, 'CSRF token invalid/missing', 0, 403);
    fail('Security token invalid. Please refresh the page and try again.', 403);
}

/* ── Rate limiting (per IP) ── */
if (Security::isRateLimited(Security::rlKey('forgot_abha', $ip), 20, 300)) {
    $logger->logApiAnomaly('ajax/forgot-abha.php', $action, 'Rate limit exceeded (global)', 0, 429);
    fail('Too many requests. Please wait a few minutes and try again.', 429);
}
if ($action === 'send_otp'
    && Security::isRateLimited(Security::rlKey('forgot_abha_otp', $ip), 5, 600)) {
    $logger->logApiAnomaly('ajax/forgot-abha.php', $action, 'OTP-send rate limit exceeded', 0, 429);
    fail('Too many OTP requests. Please wait 10 minutes before trying again.', 429);
}
if ($action === 'verify_otp'
    && Security::isRateLimited(Security::rlKey('forgot_abha_verify', $ip), 10, 600)) {
    $logger->logApiAnomaly('ajax/forgot-abha.php', $action, 'OTP-verify rate limit exceeded', 0, 429);
    fail('Too many attempts. Please wait 10 minutes before trying again.', 429);
}

/** 12-3456-7890-1234 → 12-XXXX-XXXX-1234 */
function mask_abha_number(string $raw): string
{
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) !== 14) return $raw !== '' ? '••••' : '';
    return substr($d, 0, 2) . '-XXXX-XXXX-' . substr($d, 10, 4);
}

/** ****1234 for a mobile / Aadhaar-linked target hint. */
function mask_tail(string $raw): string
{
    $d = preg_replace('/\D/', '', $raw);
    return $d === '' ? '' : str_repeat('*', max(0, strlen($d) - 4)) . substr($d, -4);
}

try {
    $abdm = new AbdmApi();

    switch ($action) {

        /* ── Step 1 — send OTP ── */
        case 'send_otp':
            $method = ($data['method'] ?? '') === 'aadhaar' ? 'aadhaar' : 'mobile';
            $value  = preg_replace('/\D/', '', (string) ($data['value'] ?? ''));

            if ($method === 'aadhaar') {
                if (!Validator::isValidAadhaar($value)) {
                    $logger->logValidationFailure('aadhaar', 'invalid format', 0, 'user');
                    fail('Enter a valid 12-digit Aadhaar number.');
                }
                $loginHint = 'aadhaar';
                $otpSystem = 'aadhaar';
                $modality  = 'AADHAAR_OTP';
            } else {
                if (!Validator::isValidMobile($value)) {
                    $logger->logValidationFailure('mobile', 'invalid format', 0, 'user');
                    fail('Enter a valid 10-digit mobile number.');
                }
                $loginHint = 'mobile';
                $otpSystem = 'abdm';
                $modality  = 'MOBILE_OTP';
            }

            $res = $abdm->initAuth($value, $loginHint, $otpSystem);
            if (!AbdmApi::txnOk($res)) {
                $logger->logAbhaRecovery($modality, (string) ($res['txnId'] ?? ''), 'FAILURE',
                    ['stage' => 'send_otp', 'method' => $method]);
                $logger->logApiAnomaly('ajax/forgot-abha.php', $action,
                    AbdmApi::extractError($res, 'OTP request failed'), 0, (int) ($res['_http'] ?? 0));
                fail(AbdmApi::extractError($res, 'Could not send the OTP. On the sandbox only ABDM test identities work.'));
            }

            $_SESSION['fabha_txn']    = $res['txnId'];
            $_SESSION['fabha_method'] = $method;

            $logger->logAbhaRecovery($modality, $res['txnId'], 'PENDING',
                ['stage' => 'send_otp', 'method' => $method]);

            ok([
                'txnId'  => $res['txnId'],
                'target' => $method === 'mobile'
                    ? mask_tail($value)
                    : ($res['mobileNumber'] ?? $res['mobile'] ?? '****'),
            ]);

        /* ── Step 2 — verify OTP, return the account list (NO login) ── */
        case 'verify_otp':
            $otp    = preg_replace('/\D/', '', (string) ($data['otp'] ?? ''));
            $txnId  = (string) ($_SESSION['fabha_txn'] ?? '');
            $method = ($_SESSION['fabha_method'] ?? 'mobile') === 'aadhaar' ? 'aadhaar' : 'mobile';

            if (!Validator::isValidOtp($otp)) fail('The OTP must be exactly 6 digits.');
            if ($txnId === '')               fail('Your session expired. Please start again.');

            $modality = $method === 'aadhaar' ? 'AADHAAR_OTP' : 'MOBILE_OTP';
            $scopes   = $method === 'aadhaar'
                ? ['abha-login', 'aadhaar-verify']
                : ['abha-login', 'mobile-verify'];

            $res      = $abdm->confirmAuth($otp, $txnId, $scopes);
            $accounts = is_array($res['accounts'] ?? null) ? $res['accounts'] : [];
            $verified = $accounts || !empty($res['token']) || !empty($res['tokens']['token']);

            if (!$verified) {
                $logger->logAbhaRecovery($modality, $txnId, 'FAILURE', ['stage' => 'verify_otp']);
                fail(AbdmApi::extractError($res, 'OTP verification failed. Please try again.'));
            }

            $out = [];
            foreach ($accounts as $acc) {
                if (!is_array($acc)) continue;
                $name = trim((string) ($acc['name'] ??
                    trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? ''))));
                $status = strtoupper(trim((string) (
                    $acc['status'] ?? $acc['accountStatus'] ?? $acc['kycStatus'] ?? ''
                )));
                $out[] = [
                    'name'          => $name !== '' ? $name : '—',
                    'abha_number'   => mask_abha_number((string) ($acc['ABHANumber'] ?? '')),
                    'abha_address'  => (string) ($acc['preferredAbhaAddress'] ?? $acc['abhaAddress'] ?? ''),
                    'status'        => $status !== '' ? $status : 'ACTIVE',
                ];
            }

            unset($_SESSION['fabha_txn'], $_SESSION['fabha_method']);
            Security::clearRateLimit(Security::rlKey('forgot_abha_verify', $ip));

            $logger->logAbhaRecovery($modality, $txnId, 'SUCCESS',
                ['stage' => 'verify_otp', 'method' => $method, 'accounts' => count($out)]);

            ok(['accounts' => $out]);

        default:
            fail('Unknown action.');
    }
} catch (RuntimeException $e) {
    $logger->logApiAnomaly('ajax/forgot-abha.php', $action, $e->getMessage(), 0, 500);
    fail($e->getMessage());
} catch (Throwable $e) {
    error_log('forgot-abha.php error: ' . $e->getMessage());
    $logger->logApiAnomaly('ajax/forgot-abha.php', $action, get_class($e) . ': ' . $e->getMessage(), 0, 500);
    fail('ABDM service unavailable. Please try again later.');
}
