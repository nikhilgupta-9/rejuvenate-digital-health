<?php
/**
 * ABDM Enrollment Step 3a — Get ABHA Address Suggestions.
 *
 * GET (no body required — reads txnId from session)
 * Returns: { "success": true, "suggestions": ["name@sbx", ...] }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: GET https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/suggestion
 *   Header: Transaction_Id: {txnId}
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';

/* ── Auth check ── */
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

/* ── Read session state ── */
$txnId  = $_SESSION['abdm_txnId']   ?? '';
$xToken = $_SESSION['abdm_x_token'] ?? '';

if (!$txnId) {
    echo json_encode(['success' => false, 'error' => 'Session expired. Please restart enrollment.']);
    exit;
}

try {
    $accessToken = abdm_get_access_token();

    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/suggestion';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
        'Transaction_Id'=> $txnId,  // spec: header, not query param
    ];

    abdm_log('Fetching ABHA address suggestions', ['txnId' => $txnId]);
    [$res, $http] = abdm_curl('GET', $url, $headers, null, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'Failed to fetch address suggestions');
        abdm_log('Suggestions failed', ['http' => $http, 'response' => $res]);
        // Return empty suggestions gracefully — user can type custom address
        echo json_encode(['success' => true, 'suggestions' => [], 'warning' => $err]);
        exit;
    }

    // ABDM may return suggestions under different keys
    $suggestions = $res['abhaAddressList']
        ?? $res['phrAddressList']
        ?? $res['suggestions']
        ?? [];

    abdm_log('Got suggestions', ['count' => count($suggestions)]);
    echo json_encode([
        'success'     => true,
        'suggestions' => array_values($suggestions),
    ]);

} catch (Exception $e) {
    abdm_log('abdm_suggestions exception: ' . $e->getMessage());
    // Fail gracefully — user can still type a custom address
    echo json_encode(['success' => true, 'suggestions' => [], 'warning' => $e->getMessage()]);
}
