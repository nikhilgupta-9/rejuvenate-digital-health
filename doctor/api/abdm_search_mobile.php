<?php
/**
 * ABDM Find Patient — Step 1: Search ABHA accounts by mobile.
 *
 * POST body (JSON): { "mobile": "9876543210" }
 * Returns: { "success": true, "accounts": [...], "txnId": "..." }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: POST https://abhasbx.abdm.gov.in/abha/api/v3/profile/account/abha/search
 *   Body: { "scope": ["search-abha"], "mobile": "<RSA_encrypted_mobile>" }
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
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$mobile = preg_replace('/\D/', '', trim($input['mobile'] ?? ''));

if (!$mobile || strlen($mobile) !== 10 || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    echo json_encode(['success' => false, 'error' => 'Enter a valid 10-digit Indian mobile number']);
    exit;
}

try {
    $accessToken  = abdm_get_access_token();
    $publicKeyPem = abdm_get_public_key();

    /* RSA-OAEP encrypt the mobile number */
    $encryptedMobile = abdm_rsa_encrypt($mobile, $publicKeyPem);

    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/account/abha/search';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];
    $body = [
        'scope'  => ['search-abha'],
        'mobile' => $encryptedMobile,
    ];

    abdm_log('Searching ABHA by mobile', ['mobile_last4' => substr($mobile, -4)]);
    [$res, $http] = abdm_curl('POST', $url, $headers, $body, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'Failed to search ABHA accounts');
        abdm_log('Mobile search failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    /* Extract txnId and account list */
    $txnId    = $res['txnId'] ?? '';
    $accounts = $res['ABHAAddresses']
        ?? $res['accounts']
        ?? $res['abhaList']
        ?? [];

    /* Mask ABHA numbers for display: XX-XXXX-XXXX-1234 */
    $masked = array_map(function ($acct, $i) {
        $num = $acct['ABHANumber'] ?? $acct['abhaNumber'] ?? '';
        // Show only last 4 digits
        $display = preg_replace('/^(\d{2}-\d{4}-\d{4}-)(\d{4})$/', 'XX-XXXX-XXXX-$2', $num);
        return [
            'index'      => $i + 1,
            'ABHANumber' => $num,
            'masked'     => $display ?: $num,
            'name'       => $acct['name']   ?? $acct['fullName'] ?? '',
            'gender'     => $acct['gender'] ?? '',
        ];
    }, $accounts, array_keys($accounts));

    if (!$txnId) {
        abdm_log('No txnId in search response', ['response' => $res]);
        echo json_encode(['success' => false, 'error' => 'No transaction ID returned from ABDM']);
        exit;
    }

    $_SESSION['abdm_search_txnId'] = $txnId;

    abdm_log('Mobile search success', ['count' => count($masked), 'txnId' => $txnId]);
    echo json_encode([
        'success'  => true,
        'accounts' => $masked,
        'txnId'    => $txnId,
    ]);

} catch (Exception $e) {
    abdm_log('abdm_search_mobile exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
