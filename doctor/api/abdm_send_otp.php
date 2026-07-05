<?php
// doctor/api/abha-otp-send.php - FIXED VERSION

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';
require_once __DIR__ . '/abdm_get_cert.php';

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

debug_log("=== START ABHA OTP REQUEST ===");

// Authentication check
if (empty($_SESSION['doctor_id'])) {
    debug_log("ERROR: Doctor not authenticated");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("ERROR: Method not allowed");
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
debug_log("Parsed Input", $input);

$inputValue = trim($input['abha_input'] ?? '');
$type = $input['type'] ?? 'aadhaar';
$existingTxnId = $input['txnId'] ?? ''; // For mobile verification flow

debug_log("Type: {$type}, Input Value: " . substr($inputValue, 0, 4) . '...');

// Mobile login is a standalone flow (ABDM "login by mobile number") — it does
// not depend on any prior Aadhaar transaction. Each request starts fresh.
$txnIdToUse = '';

// Build request based on type
$loginId = '';
$loginHint = '';
$otpSystem = '';
$scopes = [];

switch($type) {
    case 'aadhaar':
        debug_log("Processing Aadhaar");
        $clean = preg_replace('/\D/', '', $inputValue);
        $validate = abdm_validate_aadhaar($clean);
        if ($validate !== true) {
            debug_log("Aadhaar Validation Failed", $validate);
            echo json_encode(['success' => false, 'error' => $validate]);
            exit;
        }
        $loginId = $clean;
        $loginHint = 'aadhaar';
        $otpSystem = 'aadhaar';
        $scopes = ['abha-enrol'];
        break;
        
    case 'mobile':
        debug_log("Processing Mobile Login");
        $clean = preg_replace('/\D/', '', $inputValue);
        if (strlen($clean) !== 10) {
            debug_log("Mobile Validation Failed - Invalid length: " . strlen($clean));
            echo json_encode(['success' => false, 'error' => 'Mobile must be 10 digits']);
            exit;
        }
        $loginId = $clean;
        $loginHint = 'mobile';
        $otpSystem = 'abdm';
        $scopes = ['abha-login', 'mobile-verify'];
        break;
        
    case 'number':
        debug_log("Processing ABHA Number");
        $clean = preg_replace('/\D/', '', $inputValue);
        if (strlen($clean) !== 14) {
            debug_log("ABHA Number Validation Failed - Invalid length: " . strlen($clean));
            echo json_encode(['success' => false, 'error' => 'ABHA number must be 14 digits']);
            exit;
        }
        $loginId = $clean;
        $loginHint = 'abha-number';
        $otpSystem = 'abdm';
        $scopes = ['abha-enrol'];
        break;
        
    case 'address':
        debug_log("Processing ABHA Address");
        if (empty($inputValue) || !strpos($inputValue, '@')) {
            debug_log("ABHA Address Validation Failed - Invalid format");
            echo json_encode(['success' => false, 'error' => 'Invalid ABHA address format']);
            exit;
        }
        $loginId = $inputValue;
        $loginHint = 'abha-address';
        $otpSystem = 'abdm';
        $scopes = ['abha-enrol'];
        break;
        
    default:
        debug_log("Invalid Type", $type);
        echo json_encode(['success' => false, 'error' => 'Invalid verification type']);
        exit;
}

debug_log("Login Details", [
    'loginId' => substr($loginId, 0, 4) . '...',
    'loginHint' => $loginHint,
    'otpSystem' => $otpSystem,
    'scopes' => $scopes,
    'txnIdToUse' => $txnIdToUse ? substr($txnIdToUse, 0, 8) . '...' : 'empty'
]);

try {
    debug_log("Getting Access Token...");
    $accessToken = abdm_get_access_token();
    debug_log("Access Token Retrieved: " . substr($accessToken, 0, 30) . '...');
    
    debug_log("Getting Public Key...");
    $publicKeyPem = abdm_get_public_key();
    debug_log("Public Key Retrieved: " . substr($publicKeyPem, 0, 50) . '...');
    
    debug_log("Encrypting Login ID...");
    $finalLoginId = abdm_rsa_encrypt($loginId, $publicKeyPem);
    debug_log("Encrypted Login ID Length: " . strlen($finalLoginId));

    // Mobile login is a LOGIN-scope operation (existing ABHA holder), which ABDM
    // routes through a different endpoint than enrollment — /enrollment/request/otp
    // only accepts abha-enrol scope and rejects abha-login with a misleading
    // "Invalid Transaction Id" 400, confirmed against the sandbox.
    $url = ($type === 'mobile')
        ? 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/login/request/otp'
        : 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/request/otp';

    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'REQUEST-ID' => abdm_uuid(),
        'TIMESTAMP' => abdm_timestamp(),
        'X-CM-ID' => defined('ABDM_X_CM_ID_VALUE') ? ABDM_X_CM_ID_VALUE : 'sbx',
    ];
    
    // ============================================
    // FIX: Build body with correct txnId
    // ============================================
    $body = [
        'scope' => $scopes,
        'loginHint' => $loginHint,
        'loginId' => $finalLoginId,
        'otpSystem' => $otpSystem,
        'txnId' => '', // Empty for new request
    ];

    debug_log("Sending Request", [
        'url' => $url,
        'headers' => array_keys($headers),
        'body' => [
            'txnId' => $body['txnId'] ? substr($body['txnId'], 0, 8) . '...' : 'empty',
            'scope' => $body['scope'],
            'loginHint' => $body['loginHint'],
            'loginId' => substr($body['loginId'], 0, 50) . '...',
            'otpSystem' => $body['otpSystem']
        ]
    ]);

    [$res, $http] = abdm_curl('POST', $url, $headers, $body);

    debug_log("Response Received", [
        'http_code' => $http,
        'response' => $res
    ]);

    if ($http < 200 || $http >= 300) {
        $errorMsg = 'Failed to send OTP';
        if (isset($res['error'])) {
            $errorMsg = is_array($res['error']) ? ($res['error']['message'] ?? $errorMsg) : $res['error'];
        } elseif (isset($res['message'])) {
            $errorMsg = $res['message'];
        } elseif (isset($res['code'])) {
            $errorMsg = $res['code'] . ': ' . ($res['message'] ?? 'Unknown error');
        }
        
        debug_log("ERROR: Failed to send OTP", [
            'http' => $http,
            'error' => $errorMsg,
            'full_response' => $res
        ]);
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    if (empty($res['txnId'])) {
        debug_log("ERROR: No txnId in response", $res);
        echo json_encode([
            'success' => false, 
            'error' => 'No transaction ID received from ABDM'
        ]);
        exit;
    }

    debug_log("SUCCESS: OTP Sent", [
        'txnId' => $res['txnId'],
        'message' => $res['message'] ?? 'OTP sent successfully'
    ]);

    // ============================================
    // FIX: Store appropriate txnId in session
    // ============================================
    if ($type === 'aadhaar') {
        // Store Aadhaar txnId for potential mobile verification later
        $_SESSION['abdm_aadhaar_txnId'] = $res['txnId'];
        $_SESSION['abdm_aadhaar_txn_timestamp'] = time();
        debug_log("Stored Aadhaar txnId: " . substr($res['txnId'], 0, 8) . '...');
    }
    
    $_SESSION['abdm_txnId'] = $res['txnId'];
    $_SESSION['abdm_login_type'] = $type;
    $_SESSION['abdm_login_id'] = $loginId;
    $_SESSION['abdm_mobile'] = ($type === 'mobile') ? $loginId : ($_SESSION['abdm_mobile'] ?? '');

    $response = [
        'success' => true,
        'txnId' => $res['txnId'],
        'message' => $res['message'] ?? 'OTP sent successfully',
    ];
    
    if ($type === 'mobile') {
        $response['isMobileVerification'] = true;
        $response['message'] = 'OTP sent to mobile number for ABHA verification';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    debug_log("EXCEPTION", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'code' => 'EXCEPTION'
    ]);
}

debug_log("=== END ABHA OTP REQUEST ===");
?>