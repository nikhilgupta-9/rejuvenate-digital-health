<?php
/**
 * ABDM Gateway Access Token — standalone endpoint + shared function.
 *
 * Called directly → returns JSON { accessToken, expiresIn }
 * Included by other files → abdm_get_access_token() is available.
 *
 * Tokens are cached in $_SESSION for up to 25 minutes (ABDM issues 30-min tokens).
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/abdm_rsa.php'; // also loads config/connect.php + config/abdm.php

// Alias the constants defined in config/abdm.php for use in this file.
// ABDM_GATEWAY_URL, ABDM_CLIENT_ID, ABDM_CLIENT_SECRET, ABDM_X_CM_ID are
// all set by config/abdm.php which reads them from .env via dotenv.
if (!defined('ABDM_GATEWAY_ENDPOINT')) {
    define('ABDM_GATEWAY_ENDPOINT', defined('ABDM_GATEWAY_URL') ? ABDM_GATEWAY_URL
        : 'https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions');
}
if (!defined('ABDM_X_CM_ID_VALUE')) {
    define('ABDM_X_CM_ID_VALUE', defined('ABDM_X_CM_ID') ? ABDM_X_CM_ID : 'sbx');
}

function abdm_credentials(): array
{
    // config/abdm.php defines these constants from .env (loaded by dotenv in connect.php)
    $id     = defined('ABDM_CLIENT_ID')     ? ABDM_CLIENT_ID     : ($_ENV['ABDM_CLIENT_ID']     ?? '');
    $secret = defined('ABDM_CLIENT_SECRET') ? ABDM_CLIENT_SECRET : ($_ENV['ABDM_CLIENT_SECRET'] ?? '');
    return [$id, $secret];
}

/**
 * Return a valid ABDM gateway access token.
 * Uses the session cache; fetches a new one only when expired or absent.
 *
 * @throws RuntimeException on ABDM API failure.
 */
function abdm_get_access_token(): string
{
    // Return cached token if still valid (with 5-min buffer)
    if (
        !empty($_SESSION['abdm_access_token']) &&
        !empty($_SESSION['abdm_token_exp'])    &&
        time() < (int)$_SESSION['abdm_token_exp']
    ) {
        return $_SESSION['abdm_access_token'];
    }

    [$clientId, $clientSecret] = abdm_credentials();
    if (!$clientId || !$clientSecret) {
        abdm_log('ABDM credentials not configured');
        throw new RuntimeException('ABDM client credentials are not configured. Set ABDM_CLIENT_ID and ABDM_CLIENT_SECRET.');
    }

    $headers = [
        'Content-Type' => 'application/json',
        'REQUEST-ID'   => abdm_uuid(),
        'TIMESTAMP'    => abdm_timestamp(),
        'X-CM-ID'      => ABDM_X_CM_ID_VALUE,
    ];

    $body = [
        'clientId'     => $clientId,
        'clientSecret' => $clientSecret,
        'grantType'    => 'client_credentials',
    ];

    abdm_log('Requesting gateway token', ['endpoint' => ABDM_GATEWAY_ENDPOINT]);

    [$res, $http] = abdm_curl('POST', ABDM_GATEWAY_ENDPOINT, $headers, $body, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300 || empty($res['accessToken'])) {
        $err = abdm_extract_error($res, $http, 'Failed to get ABDM gateway token');
        abdm_log('Gateway token failed', ['http' => $http, 'response' => $res]);
        throw new RuntimeException($err);
    }

    $ttl = max(300, (int)($res['expiresIn'] ?? 1800) - 300);
    $_SESSION['abdm_access_token'] = $res['accessToken'];
    $_SESSION['abdm_token_exp']    = time() + $ttl;

    abdm_log('Gateway token obtained', ['expiresIn' => $res['expiresIn'] ?? 1800]);
    return $res['accessToken'];
}

/* ── Direct call: return token as JSON ── */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');

    // Auth check
    if (empty($_SESSION['doctor_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    try {
        $token = abdm_get_access_token();
        echo json_encode([
            'success'     => true,
            'accessToken' => $token,
            'expiresIn'   => (int)($_SESSION['abdm_token_exp'] - time()),
        ]);
    } catch (Exception $e) {
        abdm_log('Direct token request failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
