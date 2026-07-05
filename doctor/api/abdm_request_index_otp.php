<?php
/**
 * ABDM Find Patient — Step 2: Request OTP for selected ABHA index.
 *
 * POST body (JSON): { "index": 1, "txnId": "..." }
 * Returns: { "success": true, "message": "OTP sent to mobile", "txnId": "..." }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: POST https://abhasbx.abdm.gov.in/abha/api/v3/profile/login/request/otp
 *   Body: { "scope":["abha-login","search-abha","mobile-verify"],
 *            "loginHint":"index", "loginId":"<RSA_encrypted_index>",
 *            "otpSystem":"abdm", "txnId":"<txnId>" }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';
require_once __DIR__ . '/abdm_get_cert.php';

/* ── Auth check ── */
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Input ── */
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$index = (int)($input['index'] ?? 0);
$txnId = trim($input['txnId'] ?? $_SESSION['abdm_search_txnId'] ?? '');

if ($index < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid account index']);
    exit;
}
if (!$txnId) {
    echo json_encode(['success' => false, 'error' => 'Session expired. Please search again.']);
    exit;
}

try {
    $accessToken  = abdm_get_access_token();
    $publicKeyPem = abdm_get_public_key();

    /* RSA-OAEP encrypt the index as a string */
    $encryptedIndex = abdm_rsa_encrypt((string)$index, $publicKeyPem);

    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/login/request/otp';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];
    $body = [
        'scope'     => ['abha-login', 'search-abha', 'mobile-verify'],
        'loginHint' => 'index',
        'loginId'   => $encryptedIndex,
        'otpSystem' => 'abdm',
        'txnId'     => $txnId,
    ];

    abdm_log('Requesting OTP for index', ['index' => $index, 'txnId' => $txnId]);
    [$res, $http] = abdm_curl('POST', $url, $headers, $body, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'Failed to request OTP');
        abdm_log('Index OTP request failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    $newTxnId = $res['txnId'] ?? $txnId;
    $_SESSION['abdm_login_txnId'] = $newTxnId;

    abdm_log('Index OTP requested', ['txnId' => $newTxnId]);
    echo json_encode([
        'success' => true,
        'message' => $res['message'] ?? 'OTP sent to registered mobile',
        'txnId'   => $newTxnId,
    ]);

} catch (Exception $e) {
    abdm_log('abdm_request_index_otp exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
