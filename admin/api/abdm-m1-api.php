<?php
/**
 * Admin ABDM Milestone 1 (M1) API Dispatcher.
 *
 * Implements NHA ABDM Milestone 1 requirements:
 * 1. ABHA Creation (Enrollment via Aadhaar OTP)
 * 2. ABHA Verification (Authentication via Aadhaar OTP, ABHA Number, Mobile OTP, or Address search)
 * 3. ABHA Card Generation (High-res PNG & PDF)
 * 4. Automatic Patient Registration & ABHA Account Linking
 *
 * Security: admin_jwt_guard(true), CSRF check, rate limiting, and AuditLogger.
 */

require_once dirname(__DIR__) . '/db-conn.php';
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/AbdmApi.php';
require_once dirname(__DIR__, 2) . '/lib/Abha.php';
require_once dirname(__DIR__, 2) . '/lib/AbhaPatientResolver.php';
require_once dirname(__DIR__, 2) . '/lib/AbhaCardGenerator.php';
require_once dirname(__DIR__, 2) . '/lib/Validator.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';

header('Content-Type: application/json');
Security::setSecurityHeaders();

$payload = admin_jwt_guard(true);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin session expired. Please log in again.']);
    exit;
}
$adminId = (int)($payload['sub'] ?? $payload['admin_id'] ?? 1);

if (!defined('ABDM_CONFIGURED') || !ABDM_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'ABDM credentials are not configured on this server.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = Validator::sanitizeString($data['action'] ?? '');
$logger = new AuditLogger($conn);
$ip     = Security::clientIp();

if (!Security::verifyCsrf($data['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page.']);
    exit;
}

$rlKey = Security::rlKey('admin_abdm_m1_api', $ip, (string)$adminId);
if (Security::isRateLimited($rlKey, 50, 300)) {
    $logger->logApiAnomaly('admin/api/abdm-m1-api.php', $action, 'Rate limit exceeded', $adminId, 429);
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

try {
    $abdm = new AbdmApi();

    switch ($action) {

        /* ══════════════════════════════════════════════════════════════
           M1 CREATION: Step 1 - Send Aadhaar OTP
           ══════════════════════════════════════════════════════════════ */
        case 'create_send_otp':
            $aadhaarRaw = trim($data['aadhaar'] ?? '');
            $consent    = !empty($data['consent']);

            if (!$consent) {
                fail('Patient consent is mandatory for Aadhaar authentication.');
            }

            try {
                $clean = Validator::abdmAadhaarOtpInput(['aadhaar' => $aadhaarRaw, 'consent' => 1]);
            } catch (InvalidArgumentException $e) {
                fail($e->getMessage());
            }

            $res = $abdm->generateAadhaarOtp($clean['aadhaar']);
            if (!AbdmApi::txnOk($res)) {
                $logger->logAbhaAuth($adminId, 'admin', 'AADHAAR_OTP_CREATE', '', 'FAILURE');
                fail(AbdmApi::extractError($res, 'ABDM did not send an OTP for this Aadhaar. Please check number.'));
            }

            $_SESSION['admin_m1_txn_id'] = $res['txnId'];
            $_SESSION['admin_m1_flow']   = 'create_aadhaar';
            $logger->logAbhaAuth($adminId, 'admin', 'AADHAAR_OTP_CREATE', $res['txnId'], 'PENDING');

            ok([
                'txnId'   => $res['txnId'],
                'message' => $res['message'] ?? 'OTP sent successfully to Aadhaar-linked mobile number.'
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 CREATION: Step 2 - Verify Aadhaar OTP & Enrol
           ══════════════════════════════════════════════════════════════ */
        case 'create_verify_otp':
            $otp    = Validator::digitsOnly($data['otp'] ?? '');
            $txnId  = trim($data['txnId'] ?? $_SESSION['admin_m1_txn_id'] ?? '');
            $mobile = Validator::digitsOnly($data['mobile'] ?? '');

            if (!Validator::isValidOtp($otp)) fail('Enter a valid 6-digit OTP.');
            if (!$txnId) fail('Verification session expired. Please send OTP again.');
            if ($mobile && !Validator::isValidMobile($mobile)) {
                fail('Enter a valid 10-digit communication mobile number.');
            }

            $res = $abdm->enrolByAadhaar($otp, $txnId, $mobile);
            $abhaNumber = $res['ABHANumber'] ?? ($res['ABHAProfile']['ABHANumber'] ?? '');

            if (!$abhaNumber) {
                $logger->logAbhaAuth($adminId, 'admin', 'AADHAAR_OTP_CREATE', $txnId, 'FAILURE');
                fail(AbdmApi::extractError($res, 'Aadhaar OTP verification failed.'));
            }

            $nextTxn = $res['txnId'] ?? $txnId;
            $xToken  = $res['tokens']['id_token'] ?? $res['tokens']['token'] ?? $res['token'] ?? '';
            $rawProfile = $res['ABHAProfile'] ?? $res;
            $normalized = AbhaPatientResolver::normalizeAbdmProfile($rawProfile);
            if ($mobile && strlen($normalized['mobile']) !== 10) {
                $normalized['mobile'] = $mobile;
            }

            // Fetch address suggestions
            $suggestions = [];
            try {
                $sugRes = $abdm->getEnrollmentAbhaAddressSuggestions($nextTxn, $xToken);
                if (is_array($sugRes)) {
                    $suggestions = $sugRes['abhaAddressList'] ?? $sugRes['suggestions'] ?? $sugRes;
                    if (!is_array($suggestions)) $suggestions = [];
                }
            } catch (\Throwable $e) {
                // Non-critical, fallback to normalized or name-based
            }

            $_SESSION['admin_m1_enrollment'] = [
                'txnId'       => $nextTxn,
                'xToken'      => $xToken,
                'normalized'  => $normalized,
                'rawProfile'  => $rawProfile,
                'isNew'       => !empty($res['isNew']),
            ];

            $logger->logAbhaAuth($adminId, 'admin', 'AADHAAR_OTP_CREATE', $nextTxn, 'SUCCESS', 'Y', [
                'abha_number' => $normalized['abha_number']
            ]);

            ok([
                'txnId'        => $nextTxn,
                'abhaNumber'   => $normalized['abha_number'],
                'abhaAddress'  => $normalized['abha_address'],
                'profile'      => $normalized,
                'suggestions'  => array_slice($suggestions, 0, 5),
                'isNew'        => !empty($res['isNew']),
                'hasToken'     => !empty($xToken),
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 CREATION: Step 3 - Set Preferred ABHA Address
           ══════════════════════════════════════════════════════════════ */
        case 'create_set_address':
            $txnId = trim($data['txnId'] ?? $_SESSION['admin_m1_enrollment']['txnId'] ?? '');
            $addr  = trim($data['abha_address'] ?? '');
            $xTok  = trim($data['xToken'] ?? $_SESSION['admin_m1_enrollment']['xToken'] ?? '');

            if (!$addr) fail('Please choose or enter an ABHA address.');
            $cleanAddr = str_replace(['@abdm', '@sbx'], '', $addr);

            $setRes = $abdm->setEnrollmentAbhaAddress($txnId, $cleanAddr, $xTok);
            if (!AbdmApi::wasSuccessful($setRes)) {
                fail(AbdmApi::extractError($setRes, 'Could not set this ABHA address. Try another handle.'));
            }

            $suffix = (defined('ABDM_ENV') && ABDM_ENV === 'sandbox') ? '@sbx' : '@abdm';
            $fullAddr = $cleanAddr . $suffix;
            if (isset($_SESSION['admin_m1_enrollment'])) {
                $_SESSION['admin_m1_enrollment']['normalized']['abha_address'] = $fullAddr;
            }

            ok([
                'abha_address' => $fullAddr,
                'message'      => 'ABHA address set successfully!'
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 CREATION: Step 4 - Confirm & Register / Link Patient
           ══════════════════════════════════════════════════════════════ */
        case 'create_confirm_patient':
            $enrollData = $_SESSION['admin_m1_enrollment'] ?? null;
            $profile = $data['profile'] ?? $enrollData['normalized'] ?? null;

            if (!$profile || empty($profile['abha_number'])) {
                fail('No active ABHA enrollment profile found. Please start again.');
            }

            $overrideMobile = Validator::digitsOnly($data['mobile'] ?? '');
            if ($overrideMobile) $profile['mobile'] = $overrideMobile;

            $overrideEmail = trim($data['email'] ?? '');
            if ($overrideEmail) $profile['email'] = $overrideEmail;

            $linkUserId = (int)($data['link_user_id'] ?? 0);

            if ($linkUserId > 0) {
                // Link to an existing patient
                $uChk = $conn->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
                $uChk->bind_param('i', $linkUserId);
                $uChk->execute();
                $uRow = $uChk->get_result()->fetch_assoc();
                $uChk->close();

                if (!$uRow) fail('Selected patient not found.');

                Abha::save($conn, 'patient', $linkUserId, [
                    'abha_number'  => $profile['abha_number'],
                    'abha_address' => $profile['abha_address'] ?? '',
                    'linked'       => 1,
                    'verified'     => 1,
                    'source'       => 'admin_m1_enrol',
                    'profile_data' => $profile,
                ]);

                $patientId = $linkUserId;
                $isNew = false;
            } else {
                // Auto-resolve or register new patient
                $saved = AbhaPatientResolver::resolveFromProfile($conn, $profile, 0);
                $patientId = (int)$saved['patient_id'];
                $isNew = (bool)$saved['is_new'];
            }

            unset($_SESSION['admin_m1_enrollment'], $_SESSION['admin_m1_txn_id'], $_SESSION['admin_m1_flow']);

            ok([
                'patient_id'   => $patientId,
                'is_new'       => $isNew,
                'abha_number'  => $profile['abha_number'],
                'abha_address' => $profile['abha_address'] ?? '',
                'name'         => $profile['name'] ?? '',
                'message'      => $isNew ? 'New patient created and linked to ABHA!' : 'Existing patient profile linked to ABHA!'
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 VERIFY: Step 1 - Send OTP for Existing ABHA Verification
           ══════════════════════════════════════════════════════════════ */
        case 'verify_send_otp':
            $type  = in_array($data['type'] ?? '', ['aadhaar', 'number', 'mobile', 'address'], true)
                ? $data['type'] : 'number';
            $input = trim($data['input'] ?? '');

            if (!$input) fail('Please enter your ABHA number, handle, or mobile number.');

            if ($type === 'address') {
                $addr = strpos($input, '@') === false ? $input . '@abdm' : $input;
                $vRes = $abdm->searchByAbhaAddress($addr);
                $status = strtoupper($vRes['status'] ?? '');
                if ($status === 'ACTIVE' || !empty($vRes['authMethods']) || !empty($vRes['healthIdNumber'])) {
                    ok([
                        'isAddressOnly' => true,
                        'valid'         => true,
                        'status'        => $vRes['status'] ?? 'ACTIVE',
                        'abha_address'  => $addr,
                        'abha_number'   => AbdmApi::formatAbhaNumber($vRes['healthIdNumber'] ?? ''),
                        'name'          => trim(($vRes['firstName'] ?? '') . ' ' . ($vRes['lastName'] ?? '')),
                        'message'       => 'ABHA address is verified and active in ABDM registry!'
                    ]);
                }
                fail(AbdmApi::extractError($vRes, 'ABHA address not found or inactive in ABDM registry.'));
            }

            // Normalise input
            if ($type === 'number') {
                $digits = preg_replace('/\D/', '', $input);
                if (strlen($digits) !== 14) fail('ABHA number must be 14 digits.');
                $loginId = AbdmApi::formatAbhaNumber($digits);
                $loginHint = 'abha-number';
                $otpSystem = 'abdm';
                $scopes = ['abha-login', 'mobile-verify'];
            } elseif ($type === 'mobile') {
                $digits = preg_replace('/\D/', '', $input);
                if (strlen($digits) !== 10) fail('Mobile number must be 10 digits.');
                $loginId = $digits;
                $loginHint = 'mobile';
                $otpSystem = 'abdm';
                $scopes = ['abha-login', 'mobile-verify'];
            } elseif ($type === 'aadhaar') {
                $digits = preg_replace('/\D/', '', $input);
                if (strlen($digits) !== 12) fail('Aadhaar number must be 12 digits.');
                $loginId = $digits;
                $loginHint = 'aadhaar';
                $otpSystem = 'aadhaar';
                $scopes = ['abha-login', 'aadhaar-verify'];
            }

            $res = $abdm->initAuth($loginId, $loginHint, $otpSystem, $scopes);
            if (!AbdmApi::txnOk($res)) {
                $logger->logAbhaAuth($adminId, 'admin', strtoupper($type) . '_OTP_VERIFY', '', 'FAILURE');
                fail(AbdmApi::extractError($res, 'ABDM could not send an OTP for this identifier.'));
            }

            $_SESSION['admin_m1_verify_txn']   = $res['txnId'];
            $_SESSION['admin_m1_verify_type']  = $type;
            $_SESSION['admin_m1_verify_input'] = $input;

            ok([
                'txnId'   => $res['txnId'],
                'message' => $res['message'] ?? 'OTP sent successfully.'
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 VERIFY: Step 2 - Confirm OTP & Retrieve Verified Profile
           ══════════════════════════════════════════════════════════════ */
        case 'verify_confirm_otp':
            $otp   = Validator::digitsOnly($data['otp'] ?? '');
            $txnId = trim($data['txnId'] ?? $_SESSION['admin_m1_verify_txn'] ?? '');
            $type  = $_SESSION['admin_m1_verify_type'] ?? 'number';

            if (!Validator::isValidOtp($otp)) fail('Enter a valid 6-digit OTP.');
            if (!$txnId) fail('Session expired. Please send OTP again.');

            $scopes = ($type === 'aadhaar') ? ['abha-login', 'aadhaar-verify'] : ['abha-login', 'mobile-verify'];
            $res = $abdm->confirmAuth($otp, $txnId, $scopes);

            $verifyTxn   = $res['txnId'] ?? $txnId;
            $transferTok = $res['token'] ?? $res['tokens']['id_token'] ?? $res['tokens']['token'] ?? '';
            $accounts    = $res['accounts'] ?? [];

            if (!$transferTok) {
                $logger->logAbhaAuth($adminId, 'admin', strtoupper($type) . '_OTP_VERIFY', $verifyTxn, 'FAILURE');
                fail(AbdmApi::extractError($res, 'OTP verification failed.'));
            }

            if (count($accounts) > 1) {
                // Multiple accounts discovered on this mobile
                $cleaned = array_map(fn($acc) => [
                    'ABHANumber'           => $acc['ABHANumber'] ?? '',
                    'name'                 => trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? '')),
                    'preferredAbhaAddress' => $acc['preferredAbhaAddress'] ?? '',
                    'gender'               => $acc['gender'] ?? '',
                ], $accounts);
                ok([
                    'needs_select' => true,
                    'txnId'        => $verifyTxn,
                    't_token'      => $transferTok,
                    'accounts'     => $cleaned
                ]);
            }

            // Exactly 1 account or direct token
            $xToken = $transferTok;
            if ($accounts) {
                $firstAbha = $accounts[0]['ABHANumber'] ?? '';
                $sel = $abdm->verifyUserLogin($verifyTxn, AbdmApi::formatAbhaNumber($firstAbha), $transferTok);
                if (AbdmApi::wasSuccessful($sel)) {
                    $xToken = $sel['token'] ?? $sel['tokens']['id_token'] ?? $sel['tokens']['token'] ?? $transferTok;
                }
            }

            // Fetch full verified profile via X-Token
            $profRes = $abdm->getProfile($xToken);
            if (!AbdmApi::wasSuccessful($profRes)) {
                fail(AbdmApi::extractError($profRes, 'Could not fetch full verified ABHA profile.'));
            }

            $normalized = AbhaPatientResolver::normalizeAbdmProfile($profRes);

            // Link to targeted user if passed
            $targetUserId = (int)($data['target_user_id'] ?? 0);
            $linkTargetType = trim($data['link_target_type'] ?? 'patient');

            if ($targetUserId > 0) {
                Abha::save($conn, $linkTargetType, $targetUserId, [
                    'abha_number'  => $normalized['abha_number'],
                    'abha_address' => $normalized['abha_address'],
                    'linked'       => 1,
                    'verified'     => 1,
                    'source'       => 'abdm_m1_verify',
                    'profile_data' => $normalized,
                ]);
                $patientId = $targetUserId;
                $isNew = false;
            } else {
                $saved = AbhaPatientResolver::resolveFromProfile($conn, $normalized, 0);
                $patientId = (int)$saved['patient_id'];
                $isNew = (bool)$saved['is_new'];
            }

            unset($_SESSION['admin_m1_verify_txn'], $_SESSION['admin_m1_verify_type'], $_SESSION['admin_m1_verify_input']);

            $logger->logAbhaAuth($patientId, 'user', strtoupper($type) . '_OTP_VERIFY', $verifyTxn, 'SUCCESS', 'Y', [
                'abha_number' => $normalized['abha_number']
            ]);

            ok([
                'verified'     => true,
                'patient_id'   => $patientId,
                'is_new'       => $isNew,
                'abha_number'  => $normalized['abha_number'],
                'abha_address' => $normalized['abha_address'],
                'profile'      => $normalized,
                'message'      => 'ABHA successfully verified & linked!'
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 CARD: Generate & Download Official ABHA Card (PDF / PNG)
           ══════════════════════════════════════════════════════════════ */
        case 'get_card':
            $patientId  = (int)($data['patient_id'] ?? 0);
            $entityType = trim($data['entity_type'] ?? 'patient');
            $format     = strtolower(trim($data['format'] ?? 'png'));
            if (!in_array($format, ['png', 'pdf'], true)) $format = 'png';

            $cardData = null;

            if ($patientId > 0) {
                $table = ($entityType === 'school_member') ? 'school_members' : 'users';
                $row = $conn->query("SELECT * FROM {$table} WHERE id = {$patientId} LIMIT 1")->fetch_assoc();
                if ($row) {
                    $cardData = [
                        'name'         => trim(($row['name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'ABHA Holder',
                        'abha_id'      => $row['abha_id'] ?? '',
                        'abha_address' => $row['abha_address'] ?? '',
                        'gender'       => $row['gender'] ?? 'Not Specified',
                        'dob'          => $row['dob'] ?? '',
                        'mobile'       => $row['mobile'] ?? ($row['phone'] ?? ''),
                    ];
                }
            }

            // Direct payload fallback
            if (!$cardData && !empty($data['card_data']) && is_array($data['card_data'])) {
                $cd = $data['card_data'];
                $cardData = [
                    'name'         => trim($cd['name'] ?? 'ABHA Holder'),
                    'abha_id'      => trim($cd['abha_number'] ?? ($cd['abha_id'] ?? '')),
                    'abha_address' => trim($cd['abha_address'] ?? ''),
                    'gender'       => trim($cd['gender'] ?? 'Not Specified'),
                    'dob'          => trim($cd['dob'] ?? ''),
                    'mobile'       => trim($cd['mobile'] ?? ''),
                ];
            }

            if (!$cardData || empty($cardData['abha_id'])) {
                fail('Card details not found or ABHA ID is missing.');
            }

            try {
                if ($format === 'pdf') {
                    $bytes = AbhaCardGenerator::generatePdf($cardData);
                    $mime = 'application/pdf';
                    $ext = 'pdf';
                } else {
                    $bytes = AbhaCardGenerator::generatePng($cardData);
                    $mime = 'image/png';
                    $ext = 'png';
                }
            } catch (\Throwable $e) {
                fail('Failed generating ABHA card: ' . $e->getMessage());
            }

            $cleanNum = preg_replace('/\D/', '', $cardData['abha_id']);
            $filename = "ABHA-Card-{$cleanNum}.{$ext}";

            ok([
                'format'   => $format,
                'mimeType' => $mime,
                'filename' => $filename,
                'data'     => base64_encode($bytes),
            ]);

        /* ══════════════════════════════════════════════════════════════
           M1 SANDBOX TEST SIMULATION
           ══════════════════════════════════════════════════════════════ */
        case 'sandbox_simulate_m1':
            if (defined('ABDM_ENV') && ABDM_ENV !== 'sandbox') {
                fail('Sandbox simulation is disabled in production.');
            }

            $simAction = trim($data['sim_action'] ?? 'create'); // 'create' | 'verify'
            $simName   = trim($data['name'] ?? 'Aarav Sharma');
            $simMobile = trim($data['mobile'] ?? '98' . mt_rand(10000000, 99999999));
            $simGender = trim($data['gender'] ?? 'Male');
            $simDob    = trim($data['dob'] ?? '1992-05-14');

            $simAbhaNum = '27-' . mt_rand(1000, 9999) . '-' . mt_rand(1000, 9999) . '-' . mt_rand(1000, 9999);
            $cleanHandle = strtolower(preg_replace('/[^a-z0-9]/', '', explode(' ', $simName)[0])) . mt_rand(10, 99);
            $simAbhaAddr = $cleanHandle . '@sbx';

            $profile = [
                'abha_number'  => $simAbhaNum,
                'abha_address' => $simAbhaAddr,
                'name'         => $simName,
                'mobile'       => $simMobile,
                'email'        => $cleanHandle . '@example.com',
                'gender'       => $simGender,
                'dob'          => $simDob,
                'address'      => 'Civil Lines, Kanpur, Uttar Pradesh - 208001',
                'pincode'      => '208001',
                'state_name'   => 'Uttar Pradesh',
                'district_name'=> 'Kanpur Nagar',
            ];

            $saved = AbhaPatientResolver::resolveFromProfile($conn, $profile, 0);
            $patientId = (int)$saved['patient_id'];

            $logger->logAbhaAuth($patientId, 'user', 'SANDBOX_M1_SIM', 'SBX-' . bin2hex(random_bytes(6)), 'SUCCESS', 'Y', [
                'abha_number' => $simAbhaNum
            ]);

            ok([
                'sandbox'      => true,
                'patient_id'   => $patientId,
                'is_new'       => (bool)$saved['is_new'],
                'abha_number'  => $simAbhaNum,
                'abha_address' => $simAbhaAddr,
                'profile'      => $profile,
                'message'      => 'Sandbox M1 simulated successfully! Patient profile registered and ABHA verified.'
            ]);

        default:
            fail('Unknown action: ' . htmlspecialchars($action));
    }

} catch (RuntimeException $e) {
    $logger->logApiAnomaly('admin/api/abdm-m1-api.php', $action, $e->getMessage(), $adminId, 500);
    fail($e->getMessage());
} catch (\Throwable $e) {
    error_log('Admin M1 API error [' . $action . ']: ' . $e->getMessage());
    $logger->logApiAnomaly('admin/api/abdm-m1-api.php', $action, get_class($e) . ': ' . $e->getMessage(), $adminId, 500);
    fail('ABDM service is temporarily unavailable. Please try again.');
}
