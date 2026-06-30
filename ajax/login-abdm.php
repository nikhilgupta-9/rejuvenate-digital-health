<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/../config/abdm.php';
include_once __DIR__ . '/../lib/AbdmApi.php';
include_once __DIR__ . '/../util/auth-helper.php';

if (!ABDM_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'ABDM credentials not configured.']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';

function ok(array $p = []): void { echo json_encode(['success' => true] + $p); exit; }
function fail(string $m): void   { echo json_encode(['success' => false, 'message' => $m]); exit; }

try {
    $abdm = new AbdmApi();

    switch ($action) {

        /* ── ABHA Login: Step 1 — initiate auth via ABDM ── */
        case 'init_abha_login':
            $abhaId     = trim($data['abha_id'] ?? '');
            $authMethod = $data['auth_method'] ?? 'MOBILE_OTP';
            if (!$abhaId) fail('ABHA number required');

            /* First check if this ABHA is in our system */
            if (!findByAbha($conn, $abhaId)) {
                fail('No account linked to this ABHA ID on our platform. Please log in with email/password first and link your ABHA.');
            }

            $otpSystem = ($authMethod === 'AADHAAR_OTP') ? 'aadhaar' : 'abdm';
            $res = $abdm->initAuth($abhaId, 'abha-number', $otpSystem);
            if (empty($res['txnId'])) fail(AbdmApi::extractError($res, 'ABDM auth init failed'));

            $_SESSION['abha_login_txn']    = $res['txnId'];
            $_SESSION['abha_login_id']     = $abhaId;
            $_SESSION['abha_login_method'] = $authMethod;

            ok(['txnId' => $res['txnId']]);

        /* ── ABHA Login: Step 2 — verify OTP, set session ── */
        case 'confirm_abha_login':
            $otp        = trim($data['otp'] ?? '');
            $txnId      = $_SESSION['abha_login_txn']    ?? '';
            $authMethod = $_SESSION['abha_login_method'] ?? 'MOBILE_OTP';
            $abhaId     = $_SESSION['abha_login_id']     ?? '';

            if (!$otp || !$txnId) fail('OTP and session required. Please start again.');

            $res    = $abdm->confirmAuth($otp, $txnId);
            $xToken = $res['token'] ?? $res['ABHAToken'] ?? $res['userToken'] ?? '';
            if (!$xToken) fail(AbdmApi::extractError($res, 'OTP verification failed'));

            $found = findByAbha($conn, $abhaId);
            if (!$found) fail('Account not found. Please log in with email/password first and link your ABHA.');

            $table = match($found['role']) {
                'patient'                     => 'users',
                'school_admin','school_staff' => 'school_users',
                'student','teacher','staff'   => 'school_members',
                'doctor'                      => 'doctors',
                default                       => null,
            };
            if ($table) resetAttempts($conn, $table, $found['user']['id']);

            unset($_SESSION['abha_login_txn'], $_SESSION['abha_login_id'], $_SESSION['abha_login_method']);

            $redirect = setRoleSession($found['user'], $found['role']);
            ok(['redirect' => $redirect, 'role' => roleLabel($found['role'])]);

        /* ── Aadhaar Login: Step 1 — generate OTP via ABDM ── */
        case 'init_aadhaar_login':
            $aadhaar = preg_replace('/\D/', '', $data['aadhaar'] ?? '');
            if (strlen($aadhaar) !== 12) fail('Valid 12-digit Aadhaar required');

            /* Check Aadhaar is stored in our system */
            if (!findByAadhaar($conn, $aadhaar)) {
                fail('Aadhaar not registered on our platform. Please log in with email/password instead.');
            }

            $res = $abdm->generateAadhaarOtp($aadhaar);
            if (empty($res['txnId'])) fail(AbdmApi::extractError($res, 'Could not send Aadhaar OTP'));

            $_SESSION['aadhaar_login_txn']     = $res['txnId'];
            $_SESSION['aadhaar_login_number']  = $aadhaar;

            ok(['txnId' => $res['txnId'], 'maskedMobile' => $res['mobileNumber'] ?? $res['mobile'] ?? '**']);

        /* ── Aadhaar Login: Step 2 — verify OTP, set session ── */
        case 'confirm_aadhaar_login':
            $otp     = trim($data['otp'] ?? '');
            $txnId   = $_SESSION['aadhaar_login_txn']    ?? '';
            $aadhaar = $_SESSION['aadhaar_login_number'] ?? '';

            if (!$otp || !$txnId) fail('OTP and session required');

            $res = $abdm->confirmAuth($otp, $txnId);
            $xToken = $res['token'] ?? $res['ABHAToken'] ?? $res['userToken'] ?? '';
            if (!$xToken) fail(AbdmApi::extractError($res, 'Aadhaar OTP invalid'));

            $found = findByAadhaar($conn, $aadhaar);
            if (!$found) fail('Account not found for this Aadhaar.');

            $table = match($found['role']) {
                'patient'                     => 'users',
                'school_admin','school_staff' => 'school_users',
                'student','teacher','staff'   => 'school_members',
                'doctor'                      => 'doctors',
                default                       => null,
            };
            if ($table) resetAttempts($conn, $table, $found['user']['id']);

            unset($_SESSION['aadhaar_login_txn'], $_SESSION['aadhaar_login_number']);

            $redirect = setRoleSession($found['user'], $found['role']);
            ok(['redirect' => $redirect, 'role' => roleLabel($found['role'])]);

        default:
            fail('Unknown action');
    }
} catch (RuntimeException $e) {
    fail($e->getMessage());
} catch (Throwable $e) {
    error_log('login-abdm.php error: ' . $e->getMessage());
    fail('ABDM service unavailable. Please try another login method.');
}
