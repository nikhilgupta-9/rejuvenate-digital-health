<?php
/**
 * Doctor HPR-ID verification dispatcher.
 *
 * Mirrors doctor/api/abdm-api.php: JWT guard, CSRF, per-action rate limiting,
 * AuditLogger. Wraps lib/HprApi.php (pure API client) + lib/HprVerification.php
 * (the hpr_verification_txns table).
 *
 * Actions:
 *   start   → generate a 5-min Aadhaar link, persist the txn (status 'pending')
 *   poll    → checkAadhaarAuthStatus; 'authenticated' when the doctor has
 *             completed the Aadhaar step on the ABDM page
 *   finish  → verifyOTP (demographics discarded) → checkHpIdAccountExist →
 *             STRICT check that the HPR ID linked to the Aadhaar equals the
 *             doctor's saved doctors.hpr_id → stamp hpr_verified*
 *
 * PII: verifyOTP returns Aadhaar demographics — never persisted, never logged,
 * never forwarded to the client. Only the HPR-registry name + HPR number
 * (needed for the on-screen confirmation) go back.
 */

require_once __DIR__ . '/../auth/guard.php';
require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/HprApi.php';
require_once dirname(__DIR__, 2) . '/lib/HprVerification.php';
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
$doctorId = (int) ($payload['sub'] ?? $payload['doctor_id'] ?? 0);

if (!defined('ABDM_HPR_CONFIGURED') || !ABDM_HPR_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'HPR verification is not configured. Please contact the administrator.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = Validator::sanitizeString($data['action'] ?? '');
$txnId  = trim((string) ($data['txnId'] ?? ''));
$logger = new AuditLogger($conn);
$ip     = Security::clientIp();

if (!Security::verifyCsrf($data['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh the page.']);
    exit;
}

// Global guard against abuse; the poll action needs a much looser ceiling
// because the browser calls it every ~3.5s while the doctor authenticates.
$globalRl = Security::rlKey('hpr_api', $ip, (string) $doctorId);
if (Security::isRateLimited($globalRl, 200, 300)) {
    $logger->logApiAnomaly('doctor/api/hpr-verify.php', $action, 'Global rate limit exceeded', $doctorId, 429);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a few minutes and try again.']);
    exit;
}

function ok(array $p = []): void {
    echo json_encode(array_merge(['success' => true], $p));
    exit;
}
function fail(string $msg, int $http = 200): void {
    if ($http !== 200) http_response_code($http);
    echo json_encode(['success' => false, 'message' => $msg, 'error' => $msg]);
    exit;
}

/** Normalise an HPR id for comparison: strip separators, lowercase. */
function hpr_norm(string $s): string {
    return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $s));
}

try {
    $hpr = new HprApi();

    switch ($action) {

        /* ── START: issue a 5-minute Aadhaar link ──────────────────── */
        case 'start':
            $startRl = Security::rlKey('hpr_start', $ip, (string) $doctorId);
            if (Security::isRateLimited($startRl, 5, 600)) {
                $logger->logApiAnomaly('doctor/api/hpr-verify.php', 'start', 'link rate limit exceeded', $doctorId, 429);
                fail('Too many verification attempts. Please wait 10 minutes before trying again.', 429);
            }

            // Doctor must have entered + saved an HPR ID first.
            $dq = $conn->prepare("SELECT hpr_id, hpr_verified FROM doctors WHERE id = ? LIMIT 1");
            $dq->bind_param('i', $doctorId);
            $dq->execute();
            $doc = $dq->get_result()->fetch_assoc() ?: [];
            $dq->close();
            if (trim((string) ($doc['hpr_id'] ?? '')) === '') {
                fail('Enter your HPR ID and save it before verifying.');
            }
            if (!empty($doc['hpr_verified'])) {
                ok(['state' => 'verified', 'alreadyVerified' => true]);
            }

            $res = $hpr->generateAadhaarLink();
            if (!$res['success']) {
                $logger->logAbhaAuth($doctorId, 'doctor', 'HPR_AADHAAR', '', 'FAILURE');
                fail($res['error']);
            }

            $d = $res['data'];
            HprVerification::start($conn, $doctorId, (string) $d['txnId'], (int) $d['expiresAt']);
            $logger->logAbhaAuth($doctorId, 'doctor', 'HPR_AADHAAR', (string) $d['txnId'], 'PENDING');

            ok([
                'state'     => 'pending',
                'txnId'     => $d['txnId'],
                'url'       => $d['url'],
                'expiresIn' => $d['expiresIn'],
            ]);

        /* ── POLL: has the doctor finished the Aadhaar step? ────────── */
        case 'poll':
            if ($txnId === '') fail('Missing transaction id.');

            $pollRl = Security::rlKey('hpr_poll', $ip, (string) $doctorId);
            if (Security::isRateLimited($pollRl, 120, 300)) {
                fail('Polling too fast. Please wait a moment.', 429);
            }

            HprVerification::expireStale($conn);

            $txn = HprVerification::get($conn, $txnId, $doctorId);
            if (!$txn) fail('Verification session not found. Please start again.');

            if ($txn['status'] === 'verified')                       ok(['state' => 'verified']);
            if (in_array($txn['status'], ['failed', 'expired'], true)) ok(['state' => $txn['status']]);

            if (HprVerification::isExpired($txn)) {
                HprVerification::setStatus($conn, $txnId, 'expired');
                ok(['state' => 'expired']);
            }

            $st = $hpr->checkAadhaarAuthStatus($txnId);
            if (!$st['success']) {
                fail($st['error']);
            }

            if (!empty($st['data']['authenticated'])) {
                HprVerification::setStatus($conn, $txnId, 'authenticated');
                ok(['state' => 'authenticated', 'ready' => true]);
            }

            ok(['state' => 'pending', 'ready' => false]);

        /* ── FINISH: verify the HPR account and stamp the doctor ────── */
        case 'finish':
            if ($txnId === '') fail('Missing transaction id.');

            $finRl = Security::rlKey('hpr_finish', $ip, (string) $doctorId);
            if (Security::isRateLimited($finRl, 10, 600)) {
                $logger->logApiAnomaly('doctor/api/hpr-verify.php', 'finish', 'finish rate limit exceeded', $doctorId, 429);
                fail('Too many attempts. Please wait 10 minutes.', 429);
            }

            $txn = HprVerification::get($conn, $txnId, $doctorId);
            if (!$txn) fail('Verification session not found. Please start again.');
            if ($txn['status'] === 'verified') ok(['state' => 'verified']);   // idempotent
            if (in_array($txn['status'], ['failed', 'expired'], true)) {
                fail('This verification session is closed. Please start again.');
            }
            if (HprVerification::isExpired($txn)) {
                HprVerification::setStatus($conn, $txnId, 'expired');
                fail('The verification link expired. Please start again.');
            }
            if ($txn['status'] !== 'authenticated') {
                fail('Please complete the Aadhaar step first.');
            }

            // The doctor's claimed HPR ID (must be saved on the profile).
            $dq = $conn->prepare("SELECT hpr_id FROM doctors WHERE id = ? LIMIT 1");
            $dq->bind_param('i', $doctorId);
            $dq->execute();
            $claimedHprId = trim((string) ($dq->get_result()->fetch_assoc()['hpr_id'] ?? ''));
            $dq->close();
            if ($claimedHprId === '') {
                HprVerification::setStatus($conn, $txnId, 'failed');
                fail('Enter your HPR ID and save it before verifying.');
            }

            // 5. verifyOTP — completes the Aadhaar auth. Demographics returned
            //    here are TRANSIENT: not stored, not logged, not forwarded.
            $vo = $hpr->verifyOTP($txnId);
            if (!$vo['success']) {
                HprVerification::setStatus($conn, $txnId, 'failed');
                $logger->logAbhaAuth($doctorId, 'doctor', 'HPR_AADHAAR', $txnId, 'FAILURE', 'Y', ['reason' => 'verify_otp']);
                fail($vo['error']);
            }
            $nextTxn = (string) ($vo['data']['txnId'] ?? $txnId);
            unset($vo);   // drop the demographic payload

            // 6. checkHpIdAccountExist — PRIMARY verification.
            $ck = $hpr->checkHpIdAccountExist($nextTxn);
            if (!$ck['success']) {
                HprVerification::setStatus($conn, $txnId, 'failed');
                $logger->logAbhaAuth($doctorId, 'doctor', 'HPR_AADHAAR', $txnId, 'FAILURE', 'Y', [
                    'reason' => $ck['code'] ?? 'check_hpid',
                ]);
                fail($ck['error']);   // "HPR account not found for this Aadhaar…" when code == hpr_account_not_found
            }

            // STRICT: the HPR ID linked to this Aadhaar MUST equal the doctor's
            // saved HPR ID. No fuzzy / partial match.
            $apiHprId = hpr_norm((string) ($ck['data']['hprIdNumber'] ?? ''));
            $claimed  = hpr_norm($claimedHprId);
            if ($apiHprId === '' || !hash_equals($claimed, $apiHprId)) {
                HprVerification::setStatus($conn, $txnId, 'failed');
                $logger->logAbhaAuth($doctorId, 'doctor', 'HPR_AADHAAR', $txnId, 'FAILURE', 'Y', ['reason' => 'hpr_id_mismatch']);
                fail('The HPR ID linked to your Aadhaar does not match the HPR ID on your profile. Fix the HPR ID on your profile and try again.');
            }

            // ✓ Verified.
            $upd = $conn->prepare(
                "UPDATE doctors
                 SET hpr_verified = 1,
                     hpr_verified_at = NOW(),
                     hpr_verification_source = 'aadhaar_hpr_api',
                     hpr_txn_id = ?
                 WHERE id = ?"
            );
            $upd->bind_param('si', $txnId, $doctorId);
            $upd->execute();
            $upd->close();

            HprVerification::setStatus($conn, $txnId, 'verified');
            $logger->logAbhaAuth($doctorId, 'doctor', 'HPR_AADHAAR', $txnId, 'SUCCESS', 'Y', ['hpr_id' => $apiHprId]);
            Security::clearRateLimit(Security::rlKey('hpr_start', $ip, (string) $doctorId));

            ok([
                'state'       => 'verified',
                'hprIdNumber' => (string) ($ck['data']['hprIdNumber'] ?? ''),
                'name'        => trim(($ck['data']['firstName'] ?? '') . ' ' . ($ck['data']['lastName'] ?? '')),
            ]);

        default:
            fail('Unknown action: ' . htmlspecialchars($action));
    }

} catch (RuntimeException $e) {
    $logger->logApiAnomaly('doctor/api/hpr-verify.php', $action, $e->getMessage(), $doctorId, 500);
    fail($e->getMessage());
} catch (Throwable $e) {
    error_log('HPR verify error [' . $action . ']: ' . $e->getMessage());
    $logger->logApiAnomaly('doctor/api/hpr-verify.php', $action, get_class($e) . ': ' . $e->getMessage(), $doctorId, 500);
    fail('HPR verification is temporarily unavailable. Please try again.');
}
