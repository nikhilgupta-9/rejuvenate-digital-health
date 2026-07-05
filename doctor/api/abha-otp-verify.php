<?php
// doctor/api/abha-otp-verify.php - FIXED WITH PLAIN TEXT MOBILE

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';
require_once __DIR__ . '/abdm_get_cert.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';

/**
 * Normalise an ABDM profile (either the full /profile/account response,
 * or the ABHAProfile block from enrol/byAadhaar) into a flat field set.
 */
function abdm_normalise_profile(array $p): array
{
    $first  = trim($p['firstName']  ?? '');
    $middle = trim($p['middleName'] ?? '');
    $last   = trim($p['lastName']   ?? '');
    $name   = trim($first . ' ' . $middle . ' ' . $last);

    $abhaAddress = $p['preferredAbhaAddress'] ?? '';
    if (!$abhaAddress && !empty($p['phrAddress'][0])) {
        $abhaAddress = $p['phrAddress'][0];
    }

    $gender = $p['gender'] ?? '';
    if ($gender === 'M') $gender = 'Male';
    elseif ($gender === 'F') $gender = 'Female';

    $dobRaw = $p['dob'] ?? ($p['birthdate'] ?? '');
    $dobDb  = null;
    if ($dobRaw) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dobRaw, $m)) {
            $dobDb = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobRaw)) {
            $dobDb = $dobRaw;
        }
    }

    return [
        'abha_number'  => $p['ABHANumber'] ?? '',
        'abha_address' => $abhaAddress,
        'name'         => $name,
        'mobile'       => preg_replace('/\D/', '', $p['mobile'] ?? ''),
        'email'        => $p['email'] ?? '',
        'gender'       => $gender,
        'dob'          => $dobDb,
        'dob_raw'      => $dobRaw,
        'address'      => $p['address'] ?? '',
        'state'        => $p['stateName']    ?? ($p['state']    ?? ''),
        'district'     => $p['districtName'] ?? ($p['district'] ?? ''),
        'pincode'      => $p['pinCode'] ?? '',
    ];
}

/**
 * Upsert a patient row from a normalised ABDM profile and link it to the doctor.
 * Mirrors the logic in doctor/api/abha-select-user.php.
 */
function abdm_upsert_patient(mysqli $conn, int $doctor_id, array $pr): array
{
    $existing = null;

    if ($pr['abha_number']) {
        $s = $conn->prepare("SELECT id FROM users WHERE abha_id=? LIMIT 1");
        $s->bind_param('s', $pr['abha_number']);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }
    if (!$existing && $pr['mobile']) {
        $s = $conn->prepare("SELECT id FROM users WHERE mobile=? LIMIT 1");
        $s->bind_param('s', $pr['mobile']);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }
    if (!$existing && $pr['email']) {
        $s = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $s->bind_param('s', $pr['email']);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
    }

    $is_new = false;

    if ($existing) {
        $patient_id = (int)$existing['id'];
        $upd = $conn->prepare("
            UPDATE users SET
              abha_id       = ?,
              abha_address  = ?,
              abha_linked   = 1,
              abha_verified = 1,
              name          = CASE WHEN name='' OR name IS NULL THEN ? ELSE name END,
              gender        = CASE WHEN gender='' OR gender IS NULL THEN ? ELSE gender END,
              dob           = CASE WHEN dob IS NULL THEN ? ELSE dob END
            WHERE id = ?
        ");
        $upd->bind_param('sssssi', $pr['abha_number'], $pr['abha_address'], $pr['name'], $pr['gender'], $pr['dob'], $patient_id);
        $upd->execute();
    } else {
        $is_new = true;
        if (!$pr['mobile']) {
            throw new RuntimeException('ABDM profile has no mobile number.');
        }
        $temp_pass = bin2hex(random_bytes(8));
        $hash      = password_hash($temp_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $conn->prepare("
            INSERT INTO users
              (name, email, mobile, password, gender, dob,
               abha_id, abha_address, abha_linked, abha_verified,
               zip_code, city, state, address, created_at)
            VALUES (?,?,?,?,?,?,?,?,1,1,?,?,?,?,NOW())
        ");
        $ins->bind_param('ssssssssssss',
            $pr['name'], $pr['email'], $pr['mobile'], $hash,
            $pr['gender'], $pr['dob'], $pr['abha_number'], $pr['abha_address'],
            $pr['pincode'], $pr['district'], $pr['state'], $pr['address']
        );
        if (!$ins->execute()) {
            throw new RuntimeException('DB error: ' . $conn->error);
        }
        $patient_id = (int)$conn->insert_id;
    }

    $lnk = $conn->prepare("INSERT INTO doctor_patients (doctor_id,patient_id,added_via,abha_fetched) VALUES (?,?,'abha',1)
                           ON DUPLICATE KEY UPDATE added_via='abha', abha_fetched=1");
    $lnk->bind_param('ii', $doctor_id, $patient_id);
    $lnk->execute();

    return ['patient_id' => $patient_id, 'is_new' => $is_new];
}

$logDir = dirname(dirname(dirname(__FILE__))) . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function debug_log($msg, $data = null) {
    global $logDir;
    $logFile = $logDir . '/abha_debug_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$msg}";
    if ($data !== null) {
        $logLine .= " " . (is_array($data) ? json_encode($data) : $data);
    }
    error_log($logLine . PHP_EOL, 3, $logFile);
    error_log($logLine);
}

debug_log("=== START ABHA OTP VERIFY ===");

if (empty($_SESSION['doctor_id'])) {
    debug_log("ERROR: Doctor not authenticated");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$doctor_id = (int)$_SESSION['doctor_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("ERROR: Method not allowed");
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
debug_log("Parsed Input", $input);

$txnId = trim($input['txnId'] ?? $_SESSION['abdm_txnId'] ?? '');
$otp = trim($input['otp'] ?? '');
$type = trim($input['type'] ?? $_SESSION['abdm_login_type'] ?? 'aadhaar');
$mobile = trim($input['mobile'] ?? '');

if (empty($txnId) || empty($otp)) {
    debug_log("ERROR: Missing txnId or OTP");
    echo json_encode(['success' => false, 'error' => 'Transaction ID and OTP are required']);
    exit;
}

if (!ctype_digit($otp) || strlen($otp) !== 6) {
    debug_log("ERROR: Invalid OTP format", ['otp_length' => strlen($otp)]);
    echo json_encode(['success' => false, 'error' => 'OTP must be 6 digits']);
    exit;
}

// ABDM's enrol/byAadhaar rejects the request with "Invalid Mobile Number"
// if this field is missing, so it's required for the aadhaar flow.
if ($type === 'aadhaar' && (!ctype_digit($mobile) || strlen($mobile) !== 10)) {
    debug_log("ERROR: Invalid or missing communication mobile", ['mobile' => $mobile]);
    echo json_encode(['success' => false, 'error' => 'A valid 10-digit mobile number is required for ABHA communication']);
    exit;
}

debug_log("Verifying OTP", [
    'txnId' => $txnId,
    'type' => $type
]);

try {
    debug_log("Getting Access Token...");
    $accessToken = abdm_get_access_token();
    debug_log("Access Token Retrieved: " . substr($accessToken, 0, 30) . '...');
    
    debug_log("Getting Public Key...");
    $publicKeyPem = abdm_get_public_key();
    debug_log("Public Key Retrieved");
    
    debug_log("Encrypting OTP...");
    $encryptedOtp = abdm_rsa_encrypt($otp, $publicKeyPem);
    debug_log("Encrypted OTP Length: " . strlen($encryptedOtp));

    // For Aadhaar verification - use the enrollment/enrol/byAadhaar endpoint
    if ($type === 'aadhaar') {
        $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/byAadhaar';
        
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'REQUEST-ID' => abdm_uuid(),
            'TIMESTAMP' => abdm_timestamp(),
            'X-CM-ID' => defined('ABDM_X_CM_ID_VALUE') ? ABDM_X_CM_ID_VALUE : 'sbx',
        ];
        
        // ABDM v3 sandbox requires 'mobile' here (plain text, not encrypted) —
        // confirmed empirically: omitting it returns 400 {"mobile":"Invalid Mobile Number"}.
        $body = [
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId' => $txnId,
                    'otpValue' => $encryptedOtp,
                    'mobile' => $mobile,
                ]
            ],
            'consent' => [
                'code' => 'abha-enrollment',
                'version' => '1.4'
            ]
        ];

        debug_log("Aadhaar Verification Body", [
            'otp' => substr($encryptedOtp, 0, 30) . '...',
            'mobile' => $mobile,
            'txnId' => $txnId
        ]);
        
    } else {
        // For Mobile verification - use the enrollment/auth/byAbdm endpoint
        $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/auth/byAbdm';
        
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'REQUEST-ID' => abdm_uuid(),
            'TIMESTAMP' => abdm_timestamp(),
            'X-CM-ID' => defined('ABDM_X_CM_ID_VALUE') ? ABDM_X_CM_ID_VALUE : 'sbx',
        ];
        
        $body = [
            'scope' => ['abha-enrol', 'mobile-verify'],
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'timeStamp' => abdm_timestamp(),
                    'txnId' => $txnId,
                    'otpValue' => $encryptedOtp,
                ]
            ]
        ];
    }

    debug_log("Sending Verification Request", [
        'url' => $url,
        'type' => $type
    ]);

    // Add retry logic for 504 errors
    $maxRetries = 3;
    $retryDelay = 3;
    $lastResponse = null;
    $lastHttp = 0;
    $success = false;
    $sawTimeout = false;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        if ($attempt > 1) {
            debug_log("Retry attempt $attempt of $maxRetries");
            sleep($retryDelay);
        }

        // Every attempt needs its own REQUEST-ID + TIMESTAMP — ABDM's gateway
        // requires a fresh crypto-random REQUEST-ID per call and validates
        // TIMESTAMP freshness, so replaying the first attempt's headers on
        // retry can get rejected outright (observed as an empty-body 401).
        $headers['REQUEST-ID'] = abdm_uuid();
        $headers['TIMESTAMP'] = abdm_timestamp();

        [$res, $http] = abdm_curl('POST', $url, $headers, $body);
        $lastResponse = $res;
        $lastHttp = $http;
        
        debug_log("Attempt $attempt Response", [
            'http_code' => $http,
            'is_504' => ($http == 504)
        ]);
        
        if ($http >= 200 && $http < 300) {
            $success = true;
            break;
        }

        if ($http == 504) {
            $sawTimeout = true;
        } else {
            // Only retry on 504 (gateway timeout)
            break;
        }
    }

    if (!$success) {
        $err = abdm_extract_error($lastResponse, $lastHttp, 'OTP verification failed');
        debug_log("ERROR: All attempts failed", [
            'http' => $lastHttp,
            'error' => $err,
            'response' => $lastResponse
        ]);
        
        // Check for 504 timeout
        if ($lastHttp == 504) {
            echo json_encode([
                'success' => false,
                'error' => 'ABDM gateway timeout. Your OTP was authenticated but the service is temporarily unavailable. Please try again in a few minutes.'
            ]);
        } elseif ($sawTimeout && $lastHttp == 401 && empty($lastResponse)) {
            // A 504 followed by an empty-body 401 on retry is an ABDM gateway
            // hiccup, not a real auth failure (the access token was just fetched
            // moments earlier). The original OTP may already be consumed server-side.
            echo json_encode([
                'success' => false,
                'error' => 'ABDM gateway timed out and the retry was rejected. Your OTP may already have been used — please click "Resend OTP" and try again with a new code.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $err]);
        }
        exit;
    }

    // Process successful response
    $res = $lastResponse;
    
    // For Aadhaar verification response
    if ($type === 'aadhaar') {
        if (isset($res['ABHAProfile']) || isset($res['ABHANumber'])) {
            debug_log("SUCCESS: Aadhaar OTP Verified and ABHA Created");
            
            $xToken = $res['tokens']['token'] ?? '';
            $abhaNumber = $res['ABHAProfile']['ABHANumber'] ?? $res['ABHANumber'] ?? '';
            $abhaProfile = $res['ABHAProfile'] ?? [];
            
            // If we have an X-token, fetch the full profile
            if (!empty($xToken)) {
                debug_log("Fetching profile with X-token...");
                
                $profileUrl = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/account';
                $profileHeaders = [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-token' => 'Bearer ' . $xToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'REQUEST-ID' => abdm_uuid(),
                    'TIMESTAMP' => abdm_timestamp(),
                    'X-CM-ID' => defined('ABDM_X_CM_ID_VALUE') ? ABDM_X_CM_ID_VALUE : 'sbx',
                ];

                [$profileRes, $profileHttp] = abdm_curl('GET', $profileUrl, $profileHeaders);

                if ($profileHttp >= 200 && $profileHttp < 300 && !empty($profileRes)) {
                    $pr = abdm_normalise_profile($profileRes);
                    // ABDM sometimes masks mobile (e.g. "******0903"); fall back
                    // to the mobile number the doctor entered on this request.
                    if (strlen($pr['mobile']) !== 10) $pr['mobile'] = $mobile;

                    $saved = abdm_upsert_patient($conn, $doctor_id, $pr);
                    debug_log("Patient saved", $saved);

                    echo json_encode([
                        'success' => true,
                        'profile' => $profileRes,
                        'abha_number' => $profileRes['ABHANumber'] ?? $abhaNumber,
                        'patient_id' => $saved['patient_id'],
                        'is_new' => $saved['is_new'],
                        'message' => 'ABHA verified and created successfully'
                    ]);
                    exit;
                }
            }

            $pr = abdm_normalise_profile($abhaProfile);
            if (strlen($pr['mobile']) !== 10) $pr['mobile'] = $mobile;

            $saved = abdm_upsert_patient($conn, $doctor_id, $pr);
            debug_log("Patient saved (fallback profile)", $saved);

            echo json_encode([
                'success' => true,
                'profile' => $abhaProfile,
                'abha_number' => $abhaNumber,
                'patient_id' => $saved['patient_id'],
                'is_new' => $saved['is_new'],
                'message' => 'ABHA verified successfully'
            ]);
            exit;
        } else {
            $errorMsg = $res['message'] ?? 'OTP verification failed';
            debug_log("ERROR: Auth failed", ['message' => $errorMsg, 'response' => $res]);
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
    } else {
        // For Mobile verification response
        if (isset($res['authResult']) && $res['authResult'] === 'success') {
            debug_log("SUCCESS: Mobile OTP Verified");
            
            $xToken = $res['token'] ?? $res['tokens']['token'] ?? '';
            $accounts = $res['accounts'] ?? [];

            if (count($accounts) > 1) {
                $cleanedAccounts = array_map(function($acc) {
                    return [
                        'ABHANumber' => $acc['ABHANumber'] ?? '',
                        'name' => trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? '')),
                        'preferredAbhaAddress' => $acc['preferredAbhaAddress'] ?? ''
                    ];
                }, $accounts);
                
                echo json_encode([
                    'success' => true,
                    'needs_select' => true,
                    'txnId' => $txnId,
                    't_token' => $xToken,
                    'accounts' => $cleanedAccounts
                ]);
                exit;
            }

            if (!empty($xToken)) {
                debug_log("Fetching profile with X-token...");
                
                $profileUrl = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/account';
                $profileHeaders = [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-token' => 'Bearer ' . $xToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'REQUEST-ID' => abdm_uuid(),
                    'TIMESTAMP' => abdm_timestamp(),
                    'X-CM-ID' => defined('ABDM_X_CM_ID_VALUE') ? ABDM_X_CM_ID_VALUE : 'sbx',
                ];

                [$profileRes, $profileHttp] = abdm_curl('GET', $profileUrl, $profileHeaders);

                if ($profileHttp >= 200 && $profileHttp < 300 && !empty($profileRes)) {
                    echo json_encode([
                        'success' => true,
                        'profile' => $profileRes,
                        'message' => 'ABHA verified successfully'
                    ]);
                    exit;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'OTP verified successfully'
            ]);
            exit;
        } else {
            $errorMsg = $res['message'] ?? 'OTP verification failed';
            debug_log("ERROR: Auth failed", ['message' => $errorMsg]);
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
    }

} catch (Exception $e) {
    debug_log("EXCEPTION", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

debug_log("=== END ABHA OTP VERIFY ===");
?>