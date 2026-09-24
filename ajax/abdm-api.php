<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/../config/abdm.php';
include_once __DIR__ . '/../lib/AbdmApi.php';
include_once __DIR__ . '/../lib/Validator.php';
include_once __DIR__ . '/../lib/Security.php';
include_once __DIR__ . '/../lib/AuditLogger.php';
include_once __DIR__ . '/../lib/Abha.php';

Security::setSecurityHeaders();

/* ─── Auth: accept patient session OR school member (student/teacher/staff) ── */
$entity_id   = 0;
$entity_type = '';   // 'user' | 'member'
$entity_table= '';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    $entity_id    = (int)$_SESSION['user_id'];
    $entity_type  = 'user';
    $entity_table = 'users';
} elseif (!empty($_SESSION['student_logged_in']) && !empty($_SESSION['student_id'])) {
    $entity_id    = (int)$_SESSION['student_id'];
    $entity_type  = 'member';
    $entity_table = 'school_members';
} elseif (!empty($_SESSION['teacher_logged_in']) && !empty($_SESSION['teacher_id'])) {
    $entity_id    = (int)$_SESSION['teacher_id'];
    $entity_type  = 'member';
    $entity_table = 'school_members';
} elseif (!empty($_SESSION['doctor_logged_in']) && !empty($_SESSION['doctor_id'])) {
    $entity_id    = (int)$_SESSION['doctor_id'];
    $entity_type  = 'doctor';
    $entity_table = 'doctors';
} elseif (!empty($_SESSION['admin_logged_in']) || !empty($_SESSION['admin_id'])) {
    $entity_id    = (int)($_SESSION['admin_id'] ?? 1);
    $entity_type  = 'admin';
    $entity_table = 'admin';
}

if (!$entity_id) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

if ($entity_type === 'user') {
    $chk = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
    $chk->bind_param('i', $entity_id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'Session expired or user account not found. Please log in again.']);
        exit;
    }
    $chk->close();
}

if (!ABDM_CONFIGURED) {
    echo json_encode(['success' => false, 'message' => 'ABDM credentials not configured. Contact administrator.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = Validator::sanitizeString($data['action'] ?? '');
$logger = new AuditLogger($conn);

// ── CSRF: every state-changing AJAX call must include a valid token ──
if (!Security::verifyCsrf($data['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh the page.']);
    exit;
}

// ── Global rate limit: max 30 ABDM actions per 5 min per session ──
$rlKey = Security::rlKey('abdm_api', Security::clientIp(), (string)$entity_id);
if (Security::isRateLimited($rlKey, 30, 300)) {
    $logger->logApiAnomaly('abdm-api.php', $action, 'Rate limit exceeded', $entity_id, 429);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a few minutes and try again.']);
    exit;
}

/* ─── Helpers ───────────────────────────────────────────────────────── */
function ok(array $payload = []): void {
    echo json_encode(array_merge(['success' => true], $payload)); exit;
}
function fail(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]); exit;
}

function saveAbha(mysqli $conn, string $table, int $id, string $abhaNum, ?string $abhaAddr): void {
    // abha_accounts is now the authoritative store (Abha::save also mirrors
    // the deprecated users/school_members.abha_* columns during the transition).
    $entityType = ($table === 'school_members') ? 'school_member' : (($table === 'doctors') ? 'doctor' : 'patient');
    Abha::save($conn, $entityType, $id, [
        'abha_number'  => $abhaNum,
        'abha_address' => $abhaAddr,
        'linked'       => 1,
        'verified'     => 1,
        'source'       => 'abdm',
    ]);
}

/* ─── Route ─────────────────────────────────────────────────────────── */
try {
    $abdm = new AbdmApi();

    switch ($action) {

        /* ── Verify an existing ABHA number or ABHA address in ABDM registry ── */
        case 'verify_abha':
            $input = trim($data['abha_id'] ?? ($data['abha_address'] ?? ''));
            if (!$input) fail('ABHA number or ABHA address required');

            $clean = preg_replace('/\D/', '', $input);
            if (strlen($clean) === 14 && strpos($input, '@') === false) {
                // Verification by 14-digit ABHA Number
                $healthId = AbdmApi::formatAbhaNumber($clean);
                $res = $abdm->searchByHealthId($healthId);

                if (!empty($res['healthIdNumber']) || !empty($res['healthId']) || !empty($res['ABHANumber'])) {
                    ok([
                        'healthId'    => $res['ABHANumber'] ?? $res['healthIdNumber'] ?? $res['healthId'] ?? $healthId,
                        'name'        => $res['name']        ?? '',
                        'gender'      => $res['gender']      ?? '',
                        'yearOfBirth' => $res['yearOfBirth'] ?? '',
                        'authMethods' => $res['authMethods'] ?? ['MOBILE_OTP'],
                        'status'      => $res['status']      ?? 'ACTIVE',
                    ]);
                }
            } else {
                // Verification by ABHA Address (ABDM v3 VerificationOfAbhaAddress API)
                $addr = (strpos($input, '@') === false) ? ($input . '@abdm') : $input;
                $res  = $abdm->searchByAbhaAddress($addr);

                $status = strtoupper($res['status'] ?? '');
                if ($status === 'ACTIVE' || !empty($res['authMethods']) || !empty($res['ABHANumber']) || !empty($res['healthIdNumber'])) {
                    $num = $res['ABHANumber'] ?? $res['healthIdNumber'] ?? '';
                    ok([
                        'healthId'     => $num ? AbdmApi::formatAbhaNumber($num) : '',
                        'abha_address' => $addr,
                        'status'       => $res['status'] ?? 'ACTIVE',
                        'authMethods'  => $res['authMethods'] ?? ['otp'],
                        'name'         => $res['name'] ?? '',
                        'gender'       => $res['gender'] ?? '',
                        'yearOfBirth'  => $res['yearOfBirth'] ?? '',
                    ]);
                }
            }
            fail(AbdmApi::extractError($res, 'ABHA ID or Address not found in ABDM registry. Note: Real production ABHAs do not exist in the Sandbox test database — to test, use the "Create New ABHA" tab or use Sandbox test data.'));

        /* ── Initiate auth for linking an existing ABHA ─────────────── */
        case 'init_link':
            $healthId   = trim($data['abha_id'] ?? '');
            $authMethod = $data['auth_method'] ?? 'MOBILE_OTP';
            if (!$healthId) fail('ABHA number required');

            // Strip non-digits to validate length, then reformat with hyphens
            // ABDM v3 expects the formatted "XX-XXXX-XXXX-XXXX" as the RSA plaintext
            $healthIdClean = preg_replace('/\D/', '', $healthId);
            if (strlen($healthIdClean) !== 14) fail('Invalid ABHA number — must be 14 digits.');
            $healthIdFormatted = AbdmApi::formatAbhaNumber($healthIdClean); // "XX-XXXX-XXXX-XXXX"

            // v3 API: loginHint='abha-number', otpSystem='abdm' or 'aadhaar'
            $otpSystem = ($authMethod === 'AADHAAR_OTP') ? 'aadhaar' : 'abdm';
            $res = $abdm->initAuth($healthIdFormatted, 'abha-number', $otpSystem);

            if (!empty($res['txnId'])) {
                $_SESSION['abdm_txn_id']      = $res['txnId'];
                $_SESSION['abdm_health_id']   = $healthId;
                $_SESSION['abdm_auth_method'] = $authMethod;
                $_SESSION['abdm_flow']        = 'link_existing';
                ok(['txnId' => $res['txnId']]);
            }
            fail(AbdmApi::extractError($res, 'Could not initiate ABHA auth'));

        /* ── Confirm OTP for linking existing ABHA ───────────────────── */
        case 'confirm_link_otp':
            $otp      = preg_replace('/\D/', '', $data['otp'] ?? '');
            $txnId    = trim($data['txnId'] ?? '') ?: ($_SESSION['abdm_txn_id'] ?? '');
            $healthId = preg_replace('/\D/', '', $data['abha_id'] ?? '') ?: ($_SESSION['abdm_health_id'] ?? '');

            // Fallback: look up linked ABHA if healthId is empty
            if (!$healthId && $entity_id) {
                $row = $conn->query("SELECT abha_id FROM {$entity_table} WHERE id={$entity_id} LIMIT 1")->fetch_assoc();
                $healthId = preg_replace('/\D/', '', $row['abha_id'] ?? '');
            }

            if (!$otp || strlen($otp) !== 6) fail('Please enter a valid 6-digit OTP.');
            if (!$txnId) fail('Session expired or transaction ID missing. Please request OTP again.');

            $res = $abdm->confirmAuth($otp, $txnId);

            if (!AbdmApi::wasSuccessful($res)) {
                fail(AbdmApi::extractError($res, 'OTP incorrect or expired. Please try again.'));
            }

            // /profile/login/verify hands back a short-lived typ:"Transfer" token
            // (+ an accounts[] list), NOT a usable X-token.
            $xToken  = $res['token']
                    ?? $res['tokens']['token']
                    ?? $res['tokens']['id_token']
                    ?? $res['ABHAToken']
                    ?? $res['userToken']
                    ?? '';
            $resTxnId = $res['txnId'] ?? $txnId;

            // Step 3 — exchange the Transfer token for the real X-token via
            // /profile/login/verify/user before any /profile/account call
            // (a Transfer token there returns HTTP 401 ABDM-1094 "X-token expired").
            if ($xToken) {
                $accounts   = $res['accounts'] ?? [];
                $selectAbha = $accounts[0]['ABHANumber'] ?? $healthId;
                if ($selectAbha) {
                    $sel   = $abdm->verifyUserLogin($resTxnId, AbdmApi::formatAbhaNumber((string)$selectAbha), $xToken);
                    $realX = $sel['token'] ?? $sel['tokens']['id_token'] ?? $sel['tokens']['token'] ?? '';
                    if ($realX && AbdmApi::wasSuccessful($sel)) {
                        $xToken = $realX;
                    }
                }
            }

            // Fetch profile if user token obtained
            $profile = [];
            if ($xToken) {
                $profile  = $abdm->getProfile($xToken);
                $abhaNum  = $profile['ABHANumber'] ?? $profile['healthIdNumber'] ?? $healthId;
                $abhaAddr = $profile['preferredAbhaAddress'] ?? $profile['healthId'] ?? null;
                $name     = $profile['name'] ?? $profile['firstName'] ?? '';
                Security::storeToken('abdm_user_token', $xToken, 'user');
            } elseif (!empty($resTxnId) && $healthId) {
                $abhaNum  = $healthId;
                $abhaAddr = null;
                $name     = '';
            } else {
                fail(AbdmApi::extractError($res, 'OTP incorrect or expired'));
            }

            if (empty($abhaNum)) fail('Could not identify ABHA number. Please try again.');

            saveAbha($conn, $entity_table, $entity_id, $abhaNum, $abhaAddr);

            // Record verification in session for downloading card
            $_SESSION['abdm_card_verified_' . $entity_id] = time() + 3600;

            // Auto-approve any pending admin-link requests & sync session
            if ($entity_type === 'user') {
                if (isset($_SESSION['user'])) {
                    $_SESSION['user']['abha_id']       = AbdmApi::formatAbhaNumber($abhaNum);
                    if ($abhaAddr) $_SESSION['user']['abha_address'] = $abhaAddr;
                    $_SESSION['user']['abha_linked']   = 1;
                    $_SESSION['user']['abha_verified'] = 1;
                }
                $conn->query("UPDATE users SET abha_verified=1 WHERE id=$entity_id");
                $conn->query("UPDATE user_abha_requests SET status='Approved', reviewed_at=NOW() WHERE user_id=$entity_id AND status='Pending'");
            } else {
                $conn->query("UPDATE abha_link_requests SET status='Approved', reviewed_at=NOW() WHERE member_id=$entity_id AND status='Pending'");
            }

            $logger->logAbhaAuth($entity_id, $entity_type, 'MOBILE_OTP', $resTxnId, 'SUCCESS', 'Y',
                ['abha_number' => AbdmApi::formatAbhaNumber($abhaNum)]);

            unset($_SESSION['abdm_txn_id'], $_SESSION['abdm_health_id'],
                  $_SESSION['abdm_auth_method'], $_SESSION['abdm_flow']);

            ok([
                'abha_id'      => AbdmApi::formatAbhaNumber($abhaNum),
                'abha_address' => $abhaAddr,
                'name'         => $name,
                'verified'     => true,
            ]);

        /* ═══════════════════════════════════════════════════════════════
           FIND ABHA NUMBER (Official ABDM v3 find-abha-number)
           Allows patients, students, doctors & admins to discover existing
           ABHA numbers via Mobile or Aadhaar OTP authentication
        ═══════════════════════════════════════════════════════════════ */

        /* ── Step 1: Request OTP to Find ABHA (by mobile or aadhaar) ── */
        case 'find_abha_request_otp':
            $authType  = in_array($data['auth_type'] ?? '', ['mobile', 'aadhaar'], true) ? $data['auth_type'] : 'mobile';
            $authValue = preg_replace('/\D/', '', $data['auth_value'] ?? '');

            if ($authType === 'mobile') {
                if (strlen($authValue) !== 10) fail('Please enter a valid 10-digit mobile number');
                $loginHint = 'mobile';
                $otpSystem = 'abdm';
                $scopes    = ['abha-login', 'mobile-verify'];
                $cleanVal  = $authValue;
            } else {
                if (strlen($authValue) !== 12) fail('Please enter a valid 12-digit Aadhaar number');
                if (empty($data['consent'])) fail('Consent is required to verify Aadhaar');
                $loginHint = 'aadhaar';
                $otpSystem = 'aadhaar';
                $scopes    = ['abha-login', 'aadhaar-verify'];
                $cleanVal  = $authValue;
            }

            // Rate limit: max 5 requests per 10 minutes
            $rlKey = Security::rlKey('find_abha_otp', Security::clientIp(), (string)$entity_id);
            if (Security::isRateLimited($rlKey, 5, 600)) {
                $logger->logApiAnomaly('abdm-api.php', 'find_abha_request_otp', 'Rate limit exceeded', $entity_id, 429);
                fail('Too many attempts. Please wait 10 minutes before trying again.');
            }

            $res = $abdm->initAuth($cleanVal, $loginHint, $otpSystem, $scopes);

            if (!empty($res['txnId'])) {
                $_SESSION['find_abha_txn_id'] = $res['txnId'];
                $_SESSION['find_abha_type']   = $authType;
                $_SESSION['find_abha_val']    = $cleanVal;
                $_SESSION['find_abha_scopes'] = $scopes;

                $masked = ($authType === 'mobile')
                    ? ('******' . substr($cleanVal, -4))
                    : ($res['maskedMobile'] ?? $res['mobileNumber'] ?? 'Aadhaar-linked mobile');

                ok([
                    'txnId'        => $res['txnId'],
                    'auth_type'    => $authType,
                    'maskedMobile' => $masked,
                    'message'      => "OTP sent successfully to {$masked}. Valid for 10 minutes."
                ]);
            }

            fail(AbdmApi::extractError($res, 'Failed to send OTP. Please check the details and try again.'));

        /* ── Step 2: Verify OTP and return discovered ABHA accounts ── */
        case 'find_abha_verify_otp':
            $otp      = preg_replace('/\D/', '', $data['otp'] ?? '');
            $txnId    = trim($data['txnId'] ?? ($_SESSION['find_abha_txn_id'] ?? ''));
            $authType = $_SESSION['find_abha_type'] ?? 'mobile';
            $scopes   = $_SESSION['find_abha_scopes'] ?? ['abha-login', 'mobile-verify'];

            if (strlen($otp) !== 6) fail('Please enter a valid 6-digit OTP');
            if (!$txnId) fail('Session expired. Please request OTP again.');

            $res = $abdm->confirmAuth($otp, $txnId, $scopes);
            $resTxnId    = $res['txnId'] ?? $txnId;
            $transferTok = $res['token'] ?? $res['tokens']['id_token'] ?? $res['tokens']['token'] ?? $res['ABHAToken'] ?? '';
            $rawAccounts = $res['accounts'] ?? [];

            // If accounts list returned from ABDM
            $foundAccounts = [];
            if (!empty($rawAccounts)) {
                foreach ($rawAccounts as $acc) {
                    $num = $acc['ABHANumber'] ?? $acc['healthIdNumber'] ?? '';
                    $foundAccounts[] = [
                        'abha_number'  => $num ? AbdmApi::formatAbhaNumber($num) : '',
                        'abha_address' => $acc['preferredAbhaAddress'] ?? $acc['healthId'] ?? '',
                        'name'         => trim(($acc['name'] ?? '') ?: (($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? ''))),
                        'gender'       => $acc['gender'] ?? '',
                        'yearOfBirth'  => $acc['yearOfBirth'] ?? '',
                        'status'       => $acc['status'] ?? 'ACTIVE',
                    ];
                }
            } elseif (!empty($transferTok)) {
                // Single account resolution via verifyUserLogin or getProfile
                $profile = $abdm->getProfile($transferTok);
                if (!empty($profile['ABHANumber']) || !empty($profile['healthIdNumber'])) {
                    $num = $profile['ABHANumber'] ?? $profile['healthIdNumber'];
                    $foundAccounts[] = [
                        'abha_number'  => AbdmApi::formatAbhaNumber($num),
                        'abha_address' => $profile['preferredAbhaAddress'] ?? $profile['healthId'] ?? '',
                        'name'         => $profile['name'] ?? trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? '')),
                        'gender'       => $profile['gender'] ?? '',
                        'yearOfBirth'  => $profile['yearOfBirth'] ?? '',
                        'status'       => $profile['status'] ?? 'ACTIVE',
                    ];
                }
            }

            if (!empty($foundAccounts)) {
                // Store in session for quick 1-click link
                $_SESSION['find_abha_discovered'] = $foundAccounts;
                $_SESSION['find_abha_token']      = $transferTok;

                $logger->logAbhaAuth($entity_id, $entity_type, strtoupper($authType) . '_OTP', $resTxnId, 'SUCCESS', 'Y', [
                    'found_count' => count($foundAccounts),
                ]);

                ok([
                    'found'    => true,
                    'count'    => count($foundAccounts),
                    'accounts' => $foundAccounts,
                    'message'  => count($foundAccounts) . ' ABHA account(s) found.'
                ]);
            }

            fail(AbdmApi::extractError($res, 'No ABHA number registered with this detail in ABDM registry. Note: Sandbox test accounts must be used for testing.'));

        /* ── Step 3: 1-Click Link a discovered ABHA to user profile ── */
        case 'find_abha_link_account':
            $abhaNum  = preg_replace('/\D/', '', $data['abha_number'] ?? '');
            $abhaAddr = trim($data['abha_address'] ?? '');

            if (strlen($abhaNum) !== 14) fail('Invalid 14-digit ABHA number');
            $abhaFormatted = AbdmApi::formatAbhaNumber($abhaNum);
            if ($abhaAddr && strpos($abhaAddr, '@') === false) $abhaAddr .= '@abdm';

            saveAbha($conn, $entity_table, $entity_id, $abhaFormatted, $abhaAddr ?: null);

            // Auto-approve pending requests
            if ($entity_type === 'user') {
                $conn->query("UPDATE user_abha_requests SET status='Approved', reviewed_at=NOW() WHERE user_id=$entity_id AND status='Pending'");
            } elseif ($entity_type === 'member') {
                $conn->query("UPDATE abha_link_requests SET status='Approved', reviewed_at=NOW() WHERE member_id=$entity_id AND status='Pending'");
            }

            $logger->logAbhaAuth($entity_id, $entity_type, 'FIND_ABHA_LINK', '', 'SUCCESS', 'Y', [
                'abha_number' => $abhaFormatted,
            ]);

            ok([
                'linked'       => true,
                'abha_number'  => $abhaFormatted,
                'abha_address' => $abhaAddr,
                'message'      => 'ABHA account successfully linked to your profile!'
            ]);

        /* ── M1 Step 1: Generate Aadhaar OTP ────────────────────────── */
        case 'gen_aadhaar_otp':
            // Validate + consent check (ABDM: server-side, centralized)
            try {
                $clean = Validator::abdmAadhaarOtpInput($data);
            } catch (InvalidArgumentException $e) {
                $logger->logValidationFailure('aadhaar', $e->getMessage(), $entity_id, $entity_type);
                fail($e->getMessage());
            }
            $aadhaar = $clean['aadhaar'];

            $inputMobile = preg_replace('/\D/', '', $data['mobile'] ?? '');
            if (strlen($inputMobile) === 10) {
                $_SESSION['abdm_mobile'] = $inputMobile;
            }

            // Stricter rate limit for OTP generation: 3 per 10 min
            $otpRl = Security::rlKey('aadhaar_otp', Security::clientIp(), (string)$entity_id);
            if (Security::isRateLimited($otpRl, 3, 600)) {
                $logger->logApiAnomaly('abdm-api.php', 'gen_aadhaar_otp', 'OTP rate limit exceeded', $entity_id, 429);
                fail('Too many OTP requests. Please wait 10 minutes before trying again.');
            }

            $res = $abdm->generateAadhaarOtp($aadhaar);

            if (!empty($res['txnId'])) {
                $_SESSION['abdm_txn_id'] = $res['txnId'];
                $_SESSION['abdm_flow']   = 'create_aadhaar';
                ok([
                    'txnId'        => $res['txnId'],
                    'maskedMobile' => $res['mobileNumber'] ?? $res['mobile'] ?? '**XXXXXX**',
                ]);
            }
            fail(AbdmApi::extractError($res, 'Failed to send OTP. Check Aadhaar and try again.'));

        /* ── M1 Step 2: Store OTP in session (v3 verify+create is one call) ── */
        case 'verify_aadhaar_otp':
            $otp   = trim($data['otp'] ?? '');
            $txnId = $_SESSION['abdm_txn_id'] ?? '';
            if (!$otp || !$txnId) fail('OTP and session required');
            if (strlen($otp) !== 6 || !ctype_digit($otp)) fail('Enter a valid 6-digit OTP');

            // In ABDM v3 the OTP is passed directly to enrolByAadhaar (no separate verify call).
            // Store it so the next step (create_abha) can use it.
            $_SESSION['abdm_otp']    = $otp;
            $_SESSION['abdm_txn_id'] = $txnId;

            // Return as if verified — name/profile comes back in the create step
            ok(['txnId' => $txnId, 'mobileLinked' => true]);

        /* ── M1 Step 2b: Store mobile number for enrollment (v3 includes it in enrolByAadhaar) ── */
        case 'gen_linked_mobile_otp':
            $mobile = preg_replace('/\D/', '', $data['mobile'] ?? '');
            if (strlen($mobile) !== 10) fail('Valid 10-digit mobile number required');
            $_SESSION['abdm_mobile'] = $mobile;
            ok(['txnId' => $_SESSION['abdm_txn_id'] ?? '']);

        /* ── M1 Step 2c: No separate mobile OTP step in v3 ─────────── */
        case 'verify_linked_mobile_otp':
            // In v3, mobile is passed inline to enrolByAadhaar — nothing to verify separately
            ok(['txnId' => $_SESSION['abdm_txn_id'] ?? '']);

        /* ── M1 Step 3: Create ABHA (v3: enrolByAadhaar = verify OTP + create) ── */
        case 'create_abha':
            $txnId   = $_SESSION['abdm_txn_id'] ?? '';
            $otp     = $_SESSION['abdm_otp']    ?? '';
            $mobile  = preg_replace('/\D/', '', $data['mobile'] ?? ($_SESSION['abdm_mobile'] ?? ''));
            $address = trim($data['abha_address'] ?? '');
            if (!$txnId || !$otp) fail('Session expired. Please start the Aadhaar process again.');

            // If mobile is still missing, auto-populate from current user's profile
            if (empty($mobile) && $entity_id && $entity_table) {
                $col = ($entity_table === 'users') ? 'mobile' : 'phone';
                $stmt = $conn->prepare("SELECT {$col} FROM {$entity_table} WHERE id=? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $entity_id);
                    $stmt->execute();
                    $uRow = $stmt->get_result()->fetch_assoc();
                    $mobile = preg_replace('/\D/', '', $uRow[$col] ?? '');
                }
            }

            if (strlen($mobile) !== 10) {
                fail('Please provide a valid 10-digit communication mobile number');
            }

            $res = $abdm->enrolByAadhaar($otp, $txnId, $mobile);

            // v3: ABHANumber in root; token nested under tokens.token
            $abhaNum = $res['ABHANumber'] ?? $res['healthIdNumber'] ?? '';
            if (!empty($abhaNum)) {
                // v3 nests token under tokens.token (enrollment); fallback for login-style responses
                $xToken   = $res['tokens']['token'] ?? $res['token'] ?? $res['ABHAToken'] ?? '';
                $resTxnId = $res['txnId'] ?? $txnId;

                // Determine ABHA address — default from API, override if user picked one
                $abhaAddr = $res['preferredAbhaAddress'] ?? $res['healthId'] ?? null;

                // If user chose a preferred ABHA address, set it via the enrollment address API
                if ($address && $xToken) {
                    $addrRes = $abdm->setEnrollmentAbhaAddress($resTxnId, $address, $xToken);
                    if (!empty($addrRes['preferredAbhaAddress'])) {
                        $abhaAddr = $addrRes['preferredAbhaAddress'];
                    } else {
                        // Fallback: use what user typed
                        $abhaAddr = $abhaAddr ?? ($address . (strpos($address, '@') === false ? '@abdm' : ''));
                    }
                }

                saveAbha($conn, $entity_table, $entity_id, $abhaNum, $abhaAddr);

                // Auto-approve pending requests
                if ($entity_type === 'user') {
                    $conn->query("UPDATE user_abha_requests SET status='Approved', reviewed_at=NOW() WHERE user_id=$entity_id AND status='Pending'");
                } else {
                    $conn->query("UPDATE abha_link_requests SET status='Approved', reviewed_at=NOW() WHERE member_id=$entity_id AND status='Pending'");
                }

                // Store user token for profile/card calls (30h TTL per ABDM guideline)
                if ($xToken) {
                    Security::storeToken('abdm_user_token', $xToken, 'user');
                }

                // Audit: ABHA created via Aadhaar (ABDM mandatory — never log raw Aadhaar/OTP)
                $logger->logAbhaAuth(
                    $entity_id, $entity_type, 'AADHAAR_OTP',
                    $resTxnId, 'SUCCESS', 'Y',
                    ['abha_number' => AbdmApi::formatAbhaNumber($abhaNum)]
                );

                unset($_SESSION['abdm_txn_id'], $_SESSION['abdm_flow'],
                      $_SESSION['abdm_otp'], $_SESSION['abdm_mobile']);

                Security::clearRateLimit(Security::rlKey('aadhaar_otp', Security::clientIp(), (string)$entity_id));

                ok([
                    'abha_id'      => AbdmApi::formatAbhaNumber($abhaNum),
                    'abha_address' => $abhaAddr,
                    'name'         => $res['name'] ?? $res['ABHAProfile']['name'] ?? '',
                ]);
            }

            $logger->logAbhaAuth($entity_id, $entity_type, 'AADHAAR_OTP', $txnId, 'FAILURE');
            fail(AbdmApi::extractError($res, 'ABHA creation failed. Check Aadhaar details and try again.'));

        /* ── M2 Step 1: Generate mobile OTP (new ABHA via mobile) ───── */
        case 'gen_mobile_otp_m2':
            $mobile = preg_replace('/\D/', '', $data['mobile'] ?? '');
            if (strlen($mobile) !== 10) fail('Valid 10-digit mobile required');

            $res = $abdm->generateMobileOtp($mobile);

            if (!empty($res['txnId'])) {
                $_SESSION['abdm_txn_id'] = $res['txnId'];
                $_SESSION['abdm_flow']   = 'create_mobile';
                ok(['txnId' => $res['txnId']]);
            }
            fail(AbdmApi::extractError($res, 'Could not send OTP'));

        /* ── M2 Step 2: Verify mobile OTP ───────────────────────────── */
        case 'verify_mobile_otp_m2':
            $otp   = trim($data['otp'] ?? '');
            $txnId = $_SESSION['abdm_txn_id'] ?? '';
            if (!$otp || !$txnId) fail('OTP required');

            $res = $abdm->verifyMobileOtpM2($otp, $txnId);

            if (!empty($res['txnId'])) {
                $_SESSION['abdm_txn_id'] = $res['txnId'];
                // Response may include list of existing ABHAs linked to that mobile
                ok([
                    'txnId'          => $res['txnId'],
                    'existingAbhas'  => $res['mobileLinkedHid'] ?? [],
                    'mobileLinkedHid'=> $res['mobileLinkedHid'] ?? [],
                ]);
            }
            fail(AbdmApi::extractError($res, 'Invalid OTP'));

        /* ── M2 Step 3: Create ABHA with mobile (new user) ──────────── */
        case 'create_abha_mobile':
            $txnId   = $_SESSION['abdm_txn_id'] ?? '';
            $address = trim($data['abha_address'] ?? '');
            if ($address && strpos($address, '@') === false) $address .= '@abdm';

            $profile = [
                'firstName'   => trim($data['first_name']  ?? ''),
                'middleName'  => trim($data['middle_name'] ?? ''),
                'lastName'    => trim($data['last_name']   ?? ''),
                'healthId'    => $address,
                'dayOfBirth'  => trim($data['day']   ?? ''),
                'monthOfBirth'=> trim($data['month'] ?? ''),
                'yearOfBirth' => trim($data['year']  ?? ''),
                'gender'      => trim($data['gender'] ?? ''),
                'email'       => trim($data['email']  ?? ''),
                'stateCode'   => trim($data['state']  ?? ''),
                'districtCode'=> trim($data['district'] ?? ''),
            ];

            if (!$txnId || !$profile['firstName']) fail('Session expired or name missing');

            $res = $abdm->createAbhaMobile($txnId, $profile);

            if (!empty($res['healthIdNumber'])) {
                $abhaNum  = $res['healthIdNumber'];
                $abhaAddr = $res['healthId'] ?? ($address ?: null);

                saveAbha($conn, $entity_table, $entity_id, $abhaNum, $abhaAddr);

                unset($_SESSION['abdm_txn_id'], $_SESSION['abdm_flow']);

                ok([
                    'abha_id'      => AbdmApi::formatAbhaNumber($abhaNum),
                    'abha_address' => $abhaAddr,
                    'name'         => $res['name'] ?? $profile['firstName'],
                ]);
            }
            fail(AbdmApi::extractError($res, 'ABHA creation failed'));

        /* ── DL Step 1: Generate OTP via Driving Licence ───────────── */
        case 'gen_dl_otp':
            try {
                $clean = Validator::abdmDlOtpInput($data);
            } catch (InvalidArgumentException $e) {
                $logger->logValidationFailure('dl_input', $e->getMessage(), $entity_id, $entity_type);
                fail($e->getMessage());
            }
            $dlNum  = $clean['dl_number'];
            $dob    = $clean['dob'];
            $gender = $clean['gender'];
            $mobile = $clean['mobile'];

            // Rate limit: 3 DL OTP requests per 10 min
            $dlRl = Security::rlKey('dl_otp', Security::clientIp(), (string)$entity_id);
            if (Security::isRateLimited($dlRl, 3, 600)) {
                $logger->logApiAnomaly('abdm-api.php', 'gen_dl_otp', 'DL OTP rate limit exceeded', $entity_id, 429);
                fail('Too many OTP requests. Please wait 10 minutes before trying again.');
            }

            // v3 Step 1: only mobile OTP is sent; DL details go in Step 3
            $res = $abdm->generateDlOtp($mobile);

            if (!empty($res['txnId'])) {
                $_SESSION['abdm_txn_id']   = $res['txnId'];
                $_SESSION['abdm_flow']     = 'create_dl';
                $_SESSION['abdm_dl_mobile']= $mobile;
                // Store DL document data for use in Step 3
                $_SESSION['abdm_dl_number'] = $dlNum;
                $_SESSION['abdm_dl_dob']    = $dob;    // YYYY-MM-DD from date input
                $_SESSION['abdm_dl_gender'] = $gender; // M/F
                ok(['txnId' => $res['txnId']]);
            }
            fail(AbdmApi::extractError($res, 'Could not initiate DL verification. Check details and try again.'));

        /* ── DL Step 2: Verify OTP ───────────────────────────────── */
        case 'verify_dl_otp':
            $otp   = trim($data['otp'] ?? '');
            $txnId = $_SESSION['abdm_txn_id'] ?? '';
            if (!$otp || !$txnId) fail('OTP and session required. Please start again.');

            $res = $abdm->verifyDlOtp($otp, $txnId);

            if (!empty($res['txnId'])) {
                $_SESSION['abdm_txn_id'] = $res['txnId'];
                ok([
                    'txnId'       => $res['txnId'],
                    'name'        => $res['name']        ?? '',
                    'gender'      => $res['gender']      ?? '',
                    'yearOfBirth' => $res['yearOfBirth'] ?? '',
                ]);
            }
            fail(AbdmApi::extractError($res, 'OTP incorrect or expired'));

        /* ── DL Step 3: Create ABHA via DL ──────────────────────── */
        case 'create_abha_dl':
            $txnId  = $_SESSION['abdm_txn_id']     ?? '';
            $dlNum  = $_SESSION['abdm_dl_number']  ?? '';
            $dlDob  = $_SESSION['abdm_dl_dob']     ?? ''; // YYYY-MM-DD
            $dlGend = $_SESSION['abdm_dl_gender']  ?? '';
            if (!$txnId || !$dlNum) fail('Session expired. Please start the DL process again.');

            // Build document object for /enrollment/enrol/byDocument
            $dlData = [
                'documentId' => $dlNum,
                'dob'        => $dlDob,
                'gender'     => $dlGend,
            ];

            $res = $abdm->createAbhaDl($txnId, $dlData);

            $abhaNum = $res['ABHANumber'] ?? $res['healthIdNumber'] ?? '';
            if (!empty($abhaNum)) {
                // v3 DL: token also nested under tokens.token
                $xToken   = $res['tokens']['token'] ?? $res['token'] ?? '';
                $resTxnId = $res['txnId'] ?? $txnId;
                $abhaAddr = $res['preferredAbhaAddress'] ?? $res['healthId'] ?? null;

                saveAbha($conn, $entity_table, $entity_id, $abhaNum, $abhaAddr);

                if ($entity_type === 'user') {
                    $conn->query("UPDATE user_abha_requests SET status='Approved', reviewed_at=NOW() WHERE user_id=$entity_id AND status='Pending'");
                } else {
                    $conn->query("UPDATE abha_link_requests SET status='Approved', reviewed_at=NOW() WHERE member_id=$entity_id AND status='Pending'");
                }

                if ($xToken) {
                    Security::storeToken('abdm_user_token', $xToken, 'user');
                }

                $logger->logAbhaAuth($entity_id, $entity_type, 'DL', $resTxnId, 'SUCCESS', 'Y',
                    ['abha_number' => AbdmApi::formatAbhaNumber($abhaNum)]);

                unset($_SESSION['abdm_txn_id'], $_SESSION['abdm_flow'], $_SESSION['abdm_dl_mobile'],
                      $_SESSION['abdm_dl_number'], $_SESSION['abdm_dl_dob'], $_SESSION['abdm_dl_gender']);

                ok([
                    'abha_id'      => AbdmApi::formatAbhaNumber($abhaNum),
                    'abha_address' => $abhaAddr,
                    'name'         => $res['name'] ?? '',
                ]);
            }
            $logger->logAbhaAuth($entity_id, $entity_type, 'DL', $txnId, 'FAILURE');
            fail(AbdmApi::extractError($res, 'ABHA creation via DL failed. Please try again.'));

        /* ── Get full ABHA profile from ABDM ────────────────────────── */
        case 'get_abdm_profile':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please log in to your ABHA again.');

            $res = $abdm->getProfile($xToken);

            if (!empty($res['ABHANumber']) || !empty($res['healthIdNumber'])) {
                $logger->logDataAccess($entity_id, $entity_type, $entity_id, 'abha_profile', 'self_view');
                ok([
                    'abha_number'   => AbdmApi::formatAbhaNumber($res['ABHANumber'] ?? $res['healthIdNumber'] ?? ''),
                    'abha_address'  => $res['preferredAbhaAddress'] ?? $res['healthId'] ?? '',
                    'name'          => $res['name'] ?? ($res['firstName'] . ' ' . $res['lastName']) ?? '',
                    'gender'        => $res['gender']       ?? '',
                    'dob'           => $res['dateOfBirth']  ?? '',
                    'mobile'        => $res['mobile']       ?? '',
                    'email'         => $res['email']        ?? '',
                    'photo'         => $res['profilePhoto'] ?? '',
                    'kycStatus'     => $res['kycStatus']    ?? '',
                    'kycVerified'   => $res['kycVerified']  ?? false,
                ]);
            }
            fail(AbdmApi::extractError($res, 'Could not fetch ABHA profile'));

        /* ── Download ABHA card as base64 PNG/PDF ──────────────────── */
        case 'get_abha_card':
            $isCardVerified = !empty($_SESSION['abdm_card_verified_' . $entity_id]) && $_SESSION['abdm_card_verified_' . $entity_id] > time();
            $xToken         = Security::getToken('abdm_user_token');

            // If not verified in this session and no xToken, require OTP verification first
            if (!$isCardVerified && !$xToken) {
                fail('Identity verification required. Please verify with OTP first.');
            }

            $format = ($data['format'] ?? 'png') === 'pdf' ? 'pdf' : 'png';
            $bytes  = '';

            // 1. Try fetching official card from ABDM if xToken is present
            if ($xToken) {
                try {
                    $bytes = ($format === 'pdf') ? $abdm->getAbhaCardPdf($xToken) : $abdm->getAbhaCard($xToken);
                } catch (\Throwable $e) {
                    error_log('[ABDM get_abha_card error] ' . $e->getMessage());
                    $bytes = '';
                }
            }

            // Verify if bytes are valid image or PDF (not an error JSON like {"error":...})
            $validBytes = false;
            if ($bytes && strlen($bytes) > 200) {
                if ($format === 'png' && substr($bytes, 0, 4) === "\x89PNG") {
                    $validBytes = true;
                } elseif ($format === 'pdf' && substr($bytes, 0, 4) === '%PDF') {
                    $validBytes = true;
                }
            }

            // 2. If ABDM did not return valid bytes (e.g. Sandbox mock or network limitation),
            // dynamically generate the official ABHA card so download ALWAYS succeeds!
            if (!$validBytes) {
                require_once __DIR__ . '/../lib/AbhaCardGenerator.php';

                // Fetch patient/member details from database
                $uRow = $conn->query("SELECT * FROM {$entity_table} WHERE id={$entity_id} LIMIT 1")->fetch_assoc();
                $cardData = [
                    'name'         => trim(($uRow['name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')) ?: 'Authorized User',
                    'abha_id'      => $uRow['abha_id'] ?? '',
                    'abha_address' => $uRow['abha_address'] ?? '',
                    'gender'       => $uRow['gender'] ?? 'Not Specified',
                    'dob'          => $uRow['dob'] ?? '',
                    'mobile'       => $uRow['phone'] ?? ($uRow['mobile'] ?? ''),
                ];

                try {
                    if ($format === 'pdf') {
                        $bytes = AbhaCardGenerator::generatePdf($cardData);
                    } else {
                        $bytes = AbhaCardGenerator::generatePng($cardData);
                    }
                } catch (\Throwable $e) {
                    error_log('[AbhaCardGenerator error] ' . $e->getMessage());
                }
            }

            if (!$bytes || strlen($bytes) < 100) {
                fail('Could not generate ABHA card. Please try again.');
            }

            $logger->logDataAccess($entity_id, $entity_type, $entity_id, 'abha_card', 'download');
            ok([
                'format'   => $format,
                'mimeType' => ($format === 'pdf') ? 'application/pdf' : 'image/png',
                'data'     => base64_encode($bytes),
            ]);

        /* ── Get suggested ABHA addresses ───────────────────────────── */
        case 'get_abha_address_suggestions':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please log in to your ABHA again.');

            $res = $abdm->getAbhaAddressSuggestions($xToken);

            $suggestions = $res['abhaAddressList'] ?? $res['phrAddressList'] ?? $res['suggestions'] ?? [];
            ok(['suggestions' => $suggestions]);

        /* ── Update preferred ABHA address ─────────────────────────── */
        case 'update_abha_address':
            $xToken  = Security::getToken('abdm_user_token');
            $newAddr = trim($data['abha_address'] ?? '');
            if (!$xToken)  fail('ABHA session expired. Please log in to your ABHA again.');
            if (!$newAddr) fail('ABHA address required');
            if (strpos($newAddr, '@') === false) $newAddr .= '@abdm';

            $res = $abdm->updateAbhaAddress($xToken, $newAddr);

            if (empty($res['code']) || ($res['_http'] ?? 200) < 400) {
                // Update local store (address only — keeps linked/verified state)
                Abha::save($conn, ($entity_table === 'school_members') ? 'school_member' : 'patient', (int) $entity_id, [
                    'abha_address' => $newAddr,
                ]);

                ok(['abha_address' => $newAddr]);
            }
            fail(AbdmApi::extractError($res, 'Could not update ABHA address'));

        /* ── Search / Verify by ABHA address (ABDM v3 VerificationOfAbhaAddress) ── */
        case 'search_by_abha_address':
            $addr = trim($data['abha_address'] ?? '');
            if (!$addr) fail('ABHA address required');
            if (strpos($addr, '@') === false) $addr .= '@abdm';

            $res = $abdm->searchByAbhaAddress($addr);

            // In ABDM v3, /search/searchByAbhaAddress returns:
            // { "authMethods": ["otp", ...], "blockedAuthMethods": [], "status": "ACTIVE" }
            $status = strtoupper($res['status'] ?? '');
            if ($status === 'ACTIVE' || !empty($res['authMethods']) || !empty($res['ABHANumber']) || !empty($res['healthIdNumber'])) {
                $abhaNum = $res['ABHANumber'] ?? $res['healthIdNumber'] ?? '';
                ok([
                    'valid'        => true,
                    'status'       => $res['status'] ?? 'ACTIVE',
                    'abha_address' => $addr,
                    'authMethods'  => $res['authMethods'] ?? ['otp'],
                    'abha_number'  => $abhaNum ? AbdmApi::formatAbhaNumber($abhaNum) : '',
                    'name'         => $res['name'] ?? '',
                    'gender'       => $res['gender'] ?? '',
                    'yearOfBirth'  => $res['yearOfBirth'] ?? '',
                ]);
            }
            fail(AbdmApi::extractError($res, 'No active ABHA found with that address in ABDM registry.'));

        /* ── Update ABHA profile fields ─────────────────────────────── */
        case 'update_abdm_profile':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please log in to your ABHA again.');

            // Only allow safe profile fields (never send Aadhaar, OTP, etc.)
            $allowed = ['firstName', 'middleName', 'lastName', 'email',
                        'dayOfBirth', 'monthOfBirth', 'yearOfBirth', 'address',
                        'pinCode', 'profilePhoto', 'districtCode', 'stateCode'];
            $fields  = array_intersect_key($data, array_flip($allowed));

            if (empty($fields)) fail('No valid profile fields provided');

            $res = $abdm->updateProfile($xToken, $fields);

            if (($res['_http'] ?? 200) < 400) {
                ok(['updated' => true]);
            }
            fail(AbdmApi::extractError($res, 'Profile update failed'));

        /* ── Get linked healthcare facilities ───────────────────────── */
        case 'get_linked_facilities':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please log in to your ABHA again.');

            $res = $abdm->getLinkedFacilities($xToken);

            ok(['facilities' => $res['linkedFacilities'] ?? $res['facilities'] ?? []]);

        /* ── M1: Standalone ABHA QR Code ────────────────────────────── */
        case 'get_abha_qr_code':
            $xToken = Security::getToken('abdm_user_token');
            if ($xToken) {
                try {
                    $qrRaw = $abdm->getAbhaQrCode($xToken);
                    if ($qrRaw && strlen($qrRaw) > 50) {
                        ok([
                            'qr_base64' => base64_encode($qrRaw),
                            'mime'      => 'image/png',
                        ]);
                    }
                } catch (Throwable $e) {
                    error_log('[abdm-api] getAbhaQrCode error: ' . $e->getMessage());
                }
            }
            // Return profile payload for client-side QR generation fallback
            $st = $conn->prepare("SELECT name, abha_id, abha_address, gender, dob FROM {$entity_table} WHERE id = ? LIMIT 1");
            $st->bind_param('i', $entity_id);
            $st->execute();
            $u = $st->get_result()->fetch_assoc();
            $st->close();

            ok([
                'qr_data' => [
                    'hidn'    => $u['abha_id'] ?? '',
                    'hid'     => $u['abha_address'] ?? '',
                    'name'    => $u['name'] ?? '',
                    'gender'  => $u['gender'] ?? '',
                    'dob'     => $u['dob'] ?? '',
                ]
            ]);

        /* ── M1: Request OTP for Mobile Update ──────────────────────── */
        case 'req_mobile_update_otp':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please verify your ABHA login first.');

            $newMobile = preg_replace('/\D/', '', $data['new_mobile'] ?? '');
            if (strlen($newMobile) !== 10) fail('Invalid mobile number. Must be 10 digits.');

            $res = $abdm->requestMobileUpdateOtp($xToken, $newMobile);
            if (!empty($res['txnId'])) {
                $_SESSION['abdm_mobile_update_txn'] = $res['txnId'];
                $_SESSION['abdm_new_mobile'] = $newMobile;
                ok(['txnId' => $res['txnId'], 'message' => 'OTP sent to new mobile number']);
            }
            fail(AbdmApi::extractError($res, 'Failed to send OTP for mobile update'));

        /* ── M1: Verify OTP and Change Mobile ────────────────────────── */
        case 'verify_mobile_update_otp':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please verify your ABHA login first.');

            $otp = trim($data['otp'] ?? '');
            $txnId = $data['txnId'] ?? ($_SESSION['abdm_mobile_update_txn'] ?? '');
            if (!$otp || !$txnId) fail('OTP and transaction ID required');

            $res = $abdm->verifyMobileUpdateOtp($xToken, $otp, $txnId);
            if (AbdmApi::wasSuccessful($res)) {
                $newMobile = $_SESSION['abdm_new_mobile'] ?? '';
                if ($newMobile) {
                    $uStmt = $conn->prepare("UPDATE {$entity_table} SET phone = ? WHERE id = ?");
                    $uStmt->bind_param('si', $newMobile, $entity_id);
                    $uStmt->execute();
                    $uStmt->close();
                }
                $logger->logAudit($entity_id, 'ABHA_MOBILE_UPDATE', 'ABHA and account mobile updated successfully');
                ok(['message' => 'Mobile number updated successfully in ABHA records!']);
            }
            fail(AbdmApi::extractError($res, 'OTP verification failed for mobile update'));

        /* ── M1: Request OTP for Email Update ───────────────────────── */
        case 'req_email_update_otp':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please verify your ABHA login first.');

            $newEmail = trim($data['new_email'] ?? '');
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) fail('Invalid email address');

            $res = $abdm->requestEmailUpdateOtp($xToken, $newEmail);
            if (!empty($res['txnId'])) {
                $_SESSION['abdm_email_update_txn'] = $res['txnId'];
                $_SESSION['abdm_new_email'] = $newEmail;
                ok(['txnId' => $res['txnId'], 'message' => 'OTP sent to new email address']);
            }
            fail(AbdmApi::extractError($res, 'Failed to send OTP for email update'));

        /* ── M1: Verify OTP and Change Email ─────────────────────────── */
        case 'verify_email_update_otp':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please verify your ABHA login first.');

            $otp = trim($data['otp'] ?? '');
            $txnId = $data['txnId'] ?? ($_SESSION['abdm_email_update_txn'] ?? '');
            if (!$otp || !$txnId) fail('OTP and transaction ID required');

            $res = $abdm->verifyEmailUpdateOtp($xToken, $otp, $txnId);
            if (AbdmApi::wasSuccessful($res)) {
                $newEmail = $_SESSION['abdm_new_email'] ?? '';
                if ($newEmail) {
                    $uStmt = $conn->prepare("UPDATE {$entity_table} SET email = ? WHERE id = ?");
                    $uStmt->bind_param('si', $newEmail, $entity_id);
                    $uStmt->execute();
                    $uStmt->close();
                }
                $logger->logAudit($entity_id, 'ABHA_EMAIL_UPDATE', 'ABHA and account email updated successfully');
                ok(['message' => 'Email address updated successfully in ABHA records!']);
            }
            fail(AbdmApi::extractError($res, 'OTP verification failed for email update'));

        /* ── M1: Deactivate ABHA Account ────────────────────────────── */
        case 'deactivate_abha':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please verify your ABHA login first.');

            $reason = trim($data['reason'] ?? 'Requested by user');
            $res = $abdm->deactivateAccount($xToken, $reason);

            if (AbdmApi::wasSuccessful($res)) {
                $uStmt = $conn->prepare("UPDATE {$entity_table} SET abha_verified = 0 WHERE id = ?");
                $uStmt->bind_param('i', $entity_id);
                $uStmt->execute();
                $uStmt->close();

                $logger->logAudit($entity_id, 'ABHA_DEACTIVATE', 'ABHA account deactivated: ' . $reason);
                ok(['message' => 'ABHA account has been deactivated successfully.']);
            }
            fail(AbdmApi::extractError($res, 'Deactivation failed'));

        default:
            fail('Unknown action: ' . htmlspecialchars($action));
    }

} catch (RuntimeException $e) {
    $logger->logApiAnomaly('abdm-api.php', $action, $e->getMessage(), $entity_id, 500);
    fail($e->getMessage());
} catch (Throwable $e) {
    $logger->logApiAnomaly('abdm-api.php', $action, get_class($e) . ': ' . $e->getMessage(), $entity_id, 500);
    error_log('ABDM Ajax Error [' . $action . ']: ' . $e->getMessage());
    // ABDM: Never expose stack traces or internal errors to client
    fail('ABDM service temporarily unavailable. Please try again.');
}
