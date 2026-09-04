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
}

if (!$entity_id) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
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
    $entityType = ($table === 'school_members') ? 'school_member' : 'patient';
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

        /* ── Verify an existing ABHA number in ABDM registry ────────── */
        case 'verify_abha':
            $healthId = trim($data['abha_id'] ?? '');
            if (!$healthId) fail('ABHA number required');

            $clean = preg_replace('/\D/', '', $healthId);
            if (strlen($clean) === 14) {
                $healthId = AbdmApi::formatAbhaNumber($clean);
            }

            $res = $abdm->searchByHealthId($healthId);

            if (!empty($res['healthIdNumber']) || !empty($res['healthId'])) {
                ok([
                    'healthId'    => $res['healthIdNumber'] ?? $res['healthId'] ?? $healthId,
                    'name'        => $res['name']        ?? '',
                    'gender'      => $res['gender']      ?? '',
                    'yearOfBirth' => $res['yearOfBirth'] ?? '',
                    'authMethods' => $res['authMethods'] ?? ['MOBILE_OTP'],
                ]);
            }
            fail('ABHA ID not found in ABDM registry. Please check the number.');

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
            // DEBUG: expose full ABDM response to diagnose init_link failures
            $dbgRes = array_diff_key($res, ['_http' => 0]);
            fail(AbdmApi::extractError($res, 'Could not initiate ABHA auth') . ' | HTTP:' . ($res['_http'] ?? '?') . ' RAW:' . json_encode($dbgRes));

        /* ── Confirm OTP for linking existing ABHA ───────────────────── */
        case 'confirm_link_otp':
            $otp      = trim($data['otp'] ?? '');
            $txnId    = $_SESSION['abdm_txn_id']    ?? '';
            $healthId = $_SESSION['abdm_health_id'] ?? ''; // raw 14-digit (stripped in init_link)

            if (!$otp || !$txnId) fail('OTP and session required. Please start again.');

            // /enrollment/auth/byAbdm with scope ["abha-login"]
            $res = $abdm->confirmAuth($otp, $txnId);

            // Response can carry token at root level OR nested under tokens.token
            $xToken  = $res['token']
                    ?? $res['tokens']['token']
                    ?? $res['ABHAToken']
                    ?? $res['userToken']
                    ?? '';
            $resTxnId = $res['txnId'] ?? $txnId;

            // Case 1: got a user token — fetch full profile then save
            if ($xToken) {
                $profile  = $abdm->getProfile($xToken);
                $abhaNum  = $profile['ABHANumber'] ?? $profile['healthIdNumber'] ?? $healthId;
                $abhaAddr = $profile['preferredAbhaAddress'] ?? $profile['healthId'] ?? null;
                $name     = $profile['name'] ?? $profile['firstName'] ?? '';
                Security::storeToken('abdm_user_token', $xToken, 'user');
            }
            // Case 2: no token but txnId present — OTP accepted, ABHA from session
            elseif (!empty($resTxnId) && $healthId) {
                $abhaNum  = $healthId;
                $abhaAddr = null;
                $name     = '';
            }
            // Case 3: neither — OTP wrong or session lost
            else {
                // DEBUG: surface raw ABDM response while testing
                $dbgLink = array_diff_key($res, ['_http' => 0]);
                fail(AbdmApi::extractError($res, 'OTP incorrect or expired') . ' | HTTP:' . ($res['_http'] ?? '?') . ' RAW:' . json_encode($dbgLink));
            }

            if (empty($abhaNum)) fail('Could not identify ABHA number. Please try again.');

            saveAbha($conn, $entity_table, $entity_id, $abhaNum, $abhaAddr);

            // Auto-approve any pending admin-link requests
            if ($entity_type === 'user') {
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
            $mobile  = $_SESSION['abdm_mobile'] ?? '';
            $address = trim($data['abha_address'] ?? '');
            if (!$txnId || !$otp) fail('Session expired. Please start the Aadhaar process again.');

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

        /* ── Download ABHA card as base64 PNG ──────────────────────── */
        case 'get_abha_card':
            $xToken = Security::getToken('abdm_user_token');
            if (!$xToken) fail('ABHA session expired. Please log in to your ABHA again.');

            $format = ($data['format'] ?? 'png') === 'pdf' ? 'pdf' : 'png';
            $bytes  = ($format === 'pdf') ? $abdm->getAbhaCardPdf($xToken) : $abdm->getAbhaCard($xToken);

            if (!$bytes || strlen($bytes) < 100) {
                fail('Could not download ABHA card. Please try again.');
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

        /* ── Search by ABHA address ─────────────────────────────────── */
        case 'search_by_abha_address':
            $addr = trim($data['abha_address'] ?? '');
            if (!$addr) fail('ABHA address required');
            if (strpos($addr, '@') === false) $addr .= '@abdm';

            $res = $abdm->searchByAbhaAddress($addr);

            if (!empty($res['healthIdNumber']) || !empty($res['ABHANumber'])) {
                ok([
                    'abha_number' => AbdmApi::formatAbhaNumber($res['ABHANumber'] ?? $res['healthIdNumber'] ?? ''),
                    'name'        => $res['name'] ?? '',
                    'gender'      => $res['gender'] ?? '',
                    'yearOfBirth' => $res['yearOfBirth'] ?? '',
                ]);
            }
            fail('No ABHA found with that address');

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
