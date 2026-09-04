<?php
/**
 * Doctor-side ABHA dispatcher — single reusable entry point mirroring
 * ajax/abdm-api.php's pattern (JWT auth, CSRF, rate limiting, Validator,
 * AuditLogger) for the "doctor creates/looks up a patient's ABHA" flow.
 *
 * Replaces the old doctor/api/abdm_*.php and abha-*.php files, which
 * reimplemented OAuth/RSA/cURL from scratch per-endpoint, skipped CSRF
 * and rate limiting, and never wrote to abdm_audit_logs.
 *
 * Actions: send_otp, verify_otp, select_user
 */

require_once __DIR__ . '/../auth/guard.php';
require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/AbdmApi.php';
require_once dirname(__DIR__, 2) . '/lib/AbhaPatientResolver.php';
require_once dirname(__DIR__, 2) . '/lib/Validator.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';

header('Content-Type: application/json');
Security::setSecurityHeaders();

$payload = doctor_jwt_guard(true);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}
$doctorId = (int)($payload['sub'] ?? $payload['doctor_id'] ?? 0);

if (!ABDM_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'ABDM credentials not configured. Contact administrator.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = Validator::sanitizeString($data['action'] ?? '');
$logger = new AuditLogger($conn);

if (!Security::verifyCsrf($data['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh the page.']);
    exit;
}

$rlKey = Security::rlKey('doc_abdm_api', Security::clientIp(), (string)$doctorId);
if (Security::isRateLimited($rlKey, 30, 300)) {
    $logger->logApiAnomaly('doctor/api/abdm-api.php', $action, 'Rate limit exceeded', $doctorId, 429);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a few minutes and try again.']);
    exit;
}

function ok(array $payload = []): void {
    echo json_encode(array_merge(['success' => true], $payload)); exit;
}
function fail(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg, 'error' => $msg]); exit;
}

/**
 * Map a send_otp "type" to ABDM [loginHint, otpSystem, scopes] for
 * initAuth() / confirmAuth(). Existing-ABHA login always pairs abha-login
 * with a verify scope — a bare ["abha-login"] is rejected as "Invalid Scope".
 */
function loginHintFor(string $type): array {
    return match ($type) {
        'mobile'  => ['mobile',      'abdm', ['abha-login', 'mobile-verify']],
        'number'  => ['abha-number', 'abdm', ['abha-login', 'mobile-verify']],
        'aadhaar' => ['aadhaar',     'aadhaar', ['abha-login', 'aadhaar-verify']],
        default   => ['abha-number', 'abdm', ['abha-login', 'mobile-verify']],
    };
}

/**
 * Fetch full profile via X-token, resolve/create the patient, log the
 * event, and build the response payload shared by verify_otp + select_user.
 */
function finishFromXToken(AbdmApi $abdm, mysqli $conn, AuditLogger $logger, int $doctorId, string $modality, string $txnId, string $xToken, string $mobileFallback = ''): void {
    $profileRes = $abdm->getProfile($xToken);
    if (!AbdmApi::wasSuccessful($profileRes)) {
        $logger->logAbhaAuth($doctorId, 'doctor', $modality, $txnId, 'FAILURE');
        fail(AbdmApi::extractError($profileRes, 'Could not fetch ABHA profile'));
    }

    $normalized = AbhaPatientResolver::normalizeAbdmProfile($profileRes);
    if (strlen($normalized['mobile']) !== 10 && strlen($mobileFallback) === 10) {
        $normalized['mobile'] = $mobileFallback;
    }

    try {
        $saved = AbhaPatientResolver::resolveFromProfile($conn, $normalized, $doctorId);
    } catch (RuntimeException $e) {
        $logger->logAbhaAuth($doctorId, 'doctor', $modality, $txnId, 'FAILURE');
        fail($e->getMessage());
    }

    $logger->logAbhaAuth($saved['patient_id'], 'user', $modality, $txnId, 'SUCCESS', 'Y', [
        'abha_number' => $normalized['abha_number'],
    ]);

    ok([
        'profile'     => $profileRes,
        'abha_number' => $normalized['abha_number'],
        'patient_id'  => $saved['patient_id'],
        'is_new'      => $saved['is_new'],
    ]);
}

try {
    $abdm = new AbdmApi();

    switch ($action) {

        /* ── Step 1: Send OTP (Aadhaar creation OR existing-ABHA login) ── */
        case 'send_otp':
            $type  = in_array($data['type'] ?? '', ['aadhaar', 'mobile', 'number', 'address'], true)
                ? $data['type'] : 'aadhaar';
            $input = trim($data['abha_input'] ?? '');

            $otpRl = Security::rlKey('doc_abdm_otp', Security::clientIp(), (string)$doctorId);
            if (Security::isRateLimited($otpRl, 5, 600)) {
                $logger->logApiAnomaly('doctor/api/abdm-api.php', 'send_otp', 'OTP rate limit exceeded', $doctorId, 429);
                fail('Too many OTP requests. Please wait 10 minutes before trying again.');
            }

            if ($type === 'aadhaar') {
                try {
                    $clean = Validator::abdmAadhaarOtpInput(['aadhaar' => $input, 'consent' => $data['consent'] ?? '']);
                } catch (InvalidArgumentException $e) {
                    $logger->logValidationFailure('aadhaar', $e->getMessage(), $doctorId, 'doctor');
                    fail($e->getMessage());
                }
                $res = $abdm->generateAadhaarOtp($clean['aadhaar']);
                if (!AbdmApi::txnOk($res)) {
                    $logger->logAbhaAuth($doctorId, 'doctor', 'AADHAAR_OTP', '', 'FAILURE');
                    fail(AbdmApi::extractError($res, 'ABDM did not send an OTP for this Aadhaar.'));
                }
                $_SESSION['doc_abdm_txn_id']   = $res['txnId'];
                $_SESSION['doc_abdm_flow']     = 'create_aadhaar';
                ok(['txnId' => $res['txnId'], 'message' => $res['message'] ?? 'OTP sent to Aadhaar-linked mobile number']);
            }

            if ($type === 'mobile' && !Validator::isValidMobile($input)) {
                fail('Please enter a valid 10-digit mobile number');
            }
            if ($type === 'number' && !Validator::isValidAbhaNumber($input)) {
                fail('Please enter a valid 14-digit ABHA number');
            }
            if ($type === 'address') {
                // ABDM v3 /profile/login/request/otp rejects loginHint "abha-address"
                // and the /search endpoints are not available to this credential —
                // no working address→OTP path yet. Route the doctor to a method that works.
                fail('ABHA-address sign-in is not available yet. Use the ABHA Number or Mobile OTP method instead.');
            }

            [$loginHint, $otpSystem, $scopes] = loginHintFor($type);
            $loginId = ($type === 'number') ? AbdmApi::formatAbhaNumber(Validator::digitsOnly($input)) : $input;

            $res = $abdm->initAuth($loginId, $loginHint, $otpSystem, $scopes);
            if (!AbdmApi::txnOk($res)) {
                $logger->logAbhaAuth($doctorId, 'doctor', strtoupper($type) . '_OTP', '', 'FAILURE');
                fail(AbdmApi::extractError($res, 'ABDM did not send an OTP. On the sandbox, only ABDM test identities work.'));
            }

            $_SESSION['doc_abdm_txn_id']    = $res['txnId'];
            $_SESSION['doc_abdm_flow']      = 'login';
            $_SESSION['doc_abdm_login_type']= $type;
            $_SESSION['doc_abdm_login_id']  = $input;
            ok(['txnId' => $res['txnId'], 'message' => $res['message'] ?? 'OTP sent successfully']);

        /* ── Step 2: Verify OTP (creates ABHA, or logs into an existing one) ── */
        case 'verify_otp':
            $otp   = Validator::digitsOnly($data['otp'] ?? '');
            $txnId = trim($data['txnId'] ?? $_SESSION['doc_abdm_txn_id'] ?? '');
            $flow  = $_SESSION['doc_abdm_flow'] ?? '';

            if (!Validator::isValidOtp($otp)) fail('OTP must be exactly 6 digits');
            if (!$txnId) fail('Session expired. Please resend OTP.');

            if ($flow === 'create_aadhaar') {
                $mobile = Validator::digitsOnly($data['mobile'] ?? '');
                if (!Validator::isValidMobile($mobile)) {
                    fail('A valid 10-digit mobile number is required for ABHA communication');
                }

                $res = $abdm->enrolByAadhaar($otp, $txnId, $mobile);
                $abhaNumber = $res['ABHANumber'] ?? ($res['ABHAProfile']['ABHANumber'] ?? '');
                if (!$abhaNumber) {
                    $logger->logAbhaAuth($doctorId, 'doctor', 'AADHAAR_OTP', $txnId, 'FAILURE');
                    fail(AbdmApi::extractError($res, 'OTP verification failed'));
                }

                $xToken = $res['tokens']['id_token'] ?? $res['tokens']['token'] ?? $res['token'] ?? '';
                unset($_SESSION['doc_abdm_txn_id'], $_SESSION['doc_abdm_flow']);
                Security::clearRateLimit(Security::rlKey('doc_abdm_otp', Security::clientIp(), (string)$doctorId));

                if ($xToken) {
                    finishFromXToken($abdm, $conn, $logger, $doctorId, 'AADHAAR_OTP', $res['txnId'] ?? $txnId, $xToken, $mobile);
                }

                // No X-token returned — fall back to the enrolment payload itself
                $normalized = AbhaPatientResolver::normalizeAbdmProfile($res['ABHAProfile'] ?? $res);
                if (strlen($normalized['mobile']) !== 10) $normalized['mobile'] = $mobile;
                try {
                    $saved = AbhaPatientResolver::resolveFromProfile($conn, $normalized, $doctorId);
                } catch (RuntimeException $e) {
                    $logger->logAbhaAuth($doctorId, 'doctor', 'AADHAAR_OTP', $txnId, 'FAILURE');
                    fail($e->getMessage());
                }
                $logger->logAbhaAuth($saved['patient_id'], 'user', 'AADHAAR_OTP', $txnId, 'SUCCESS', 'Y', [
                    'abha_number' => $normalized['abha_number'],
                ]);
                ok([
                    'profile'     => $res['ABHAProfile'] ?? [],
                    'abha_number' => $normalized['abha_number'],
                    'patient_id'  => $saved['patient_id'],
                    'is_new'      => $saved['is_new'],
                ]);
            }

            // Existing-ABHA login (mobile / abha-number)
            $loginType = $_SESSION['doc_abdm_login_type'] ?? 'number';
            [, , $scopes] = loginHintFor($loginType);
            $res = $abdm->confirmAuth($otp, $txnId, $scopes);

            $verifyTxn   = $res['txnId'] ?? $txnId;
            $transferTok = $res['token'] ?? $res['tokens']['id_token'] ?? $res['tokens']['token'] ?? '';
            $accounts    = $res['accounts'] ?? [];
            $mobileFallback = ($loginType === 'mobile') ? Validator::digitsOnly($_SESSION['doc_abdm_login_id'] ?? '') : '';

            if (!$transferTok) {
                $logger->logAbhaAuth($doctorId, 'doctor', strtoupper($loginType) . '_OTP', $verifyTxn, 'FAILURE');
                fail(AbdmApi::extractError($res, 'OTP verification failed'));
            }

            // /profile/login/verify returns a "Transfer" T-token + the ABHAs on
            // this mobile. It must be exchanged for the real X-token via
            // /profile/login/verify/user — even for a single ABHA. More than one
            // → let the doctor pick.
            if (count($accounts) > 1) {
                $cleaned = array_map(fn($acc) => [
                    'ABHANumber'           => $acc['ABHANumber'] ?? '',
                    'name'                 => trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? '')),
                    'preferredAbhaAddress' => $acc['preferredAbhaAddress'] ?? '',
                ], $accounts);
                ok(['needs_select' => true, 'txnId' => $verifyTxn, 't_token' => $transferTok, 'accounts' => $cleaned]);
            }

            unset($_SESSION['doc_abdm_txn_id'], $_SESSION['doc_abdm_flow'], $_SESSION['doc_abdm_login_type'], $_SESSION['doc_abdm_login_id']);

            if ($accounts) {
                // exactly one ABHA — auto-select it
                $onlyAbha = $accounts[0]['ABHANumber'] ?? '';
                $sel = $abdm->verifyUserLogin($verifyTxn, AbdmApi::formatAbhaNumber($onlyAbha), $transferTok);
                if (!AbdmApi::wasSuccessful($sel)) {
                    $logger->logAbhaAuth($doctorId, 'doctor', strtoupper($loginType) . '_OTP', $verifyTxn, 'FAILURE');
                    fail(AbdmApi::extractError($sel, 'Could not open the ABHA account'));
                }
                $xToken = $sel['token'] ?? $sel['tokens']['id_token'] ?? $sel['tokens']['token'] ?? '';
                finishFromXToken($abdm, $conn, $logger, $doctorId, strtoupper($loginType) . '_OTP', $verifyTxn, $xToken, $mobileFallback);
            }

            // No accounts key — treat the returned token as the X-token directly
            finishFromXToken($abdm, $conn, $logger, $doctorId, strtoupper($loginType) . '_OTP', $verifyTxn, $transferTok, $mobileFallback);

        /* ── Select one ABHA when a mobile has more than one linked ── */
        case 'select_user':
            $txnId      = trim($data['txnId']       ?? '');
            $tToken     = trim($data['t_token']     ?? '');
            $abhaNumber = trim($data['abha_number'] ?? '');
            if (!$txnId || !$tToken || !$abhaNumber) fail('txnId, t_token and abha_number are required');

            $res = $abdm->verifyUserLogin($txnId, AbdmApi::formatAbhaNumber($abhaNumber), $tToken);
            $xToken = $res['token'] ?? $res['tokens']['id_token'] ?? $res['tokens']['token'] ?? '';
            if (!$xToken) {
                $logger->logAbhaAuth($doctorId, 'doctor', 'MOBILE_OTP', $txnId, 'FAILURE');
                fail(AbdmApi::extractError($res, 'Could not select ABHA account'));
            }

            finishFromXToken($abdm, $conn, $logger, $doctorId, 'MOBILE_OTP', $txnId, $xToken);

        default:
            fail('Unknown action: ' . htmlspecialchars($action));
    }

} catch (RuntimeException $e) {
    $logger->logApiAnomaly('doctor/api/abdm-api.php', $action, $e->getMessage(), $doctorId, 500);
    fail($e->getMessage());
} catch (Throwable $e) {
    $logger->logApiAnomaly('doctor/api/abdm-api.php', $action, get_class($e) . ': ' . $e->getMessage(), $doctorId, 500);
    error_log('Doctor ABDM Ajax Error [' . $action . ']: ' . $e->getMessage());
    fail('ABDM service temporarily unavailable. Please try again.');
}
