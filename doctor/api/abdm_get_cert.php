<?php
/**
 * ABDM RSA Public Key — standalone endpoint + shared function.
 *
 * Called directly → returns JSON { publicKey: "<PEM>" }
 * Included by other files → abdm_get_public_key() is available.
 *
 * Cert endpoint: GET https://healthidsbx.abdm.gov.in/api/v1/auth/cert
 * Cached in $_SESSION for 1 hour.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';

define('ABDM_CERT_URL', 'https://healthidsbx.abdm.gov.in/api/v1/auth/cert');

/**
 * Fetch (or return cached) ABDM RSA public key as a PEM string.
 *
 * @throws RuntimeException on failure.
 */
function abdm_get_public_key(): string
{
    // Return cached key if still valid
    if (
        !empty($_SESSION['abdm_public_key']) &&
        !empty($_SESSION['abdm_cert_exp'])   &&
        time() < (int)$_SESSION['abdm_cert_exp']
    ) {
        return $_SESSION['abdm_public_key'];
    }

    $token   = abdm_get_access_token();
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];

    abdm_log('Fetching ABDM public cert', ['url' => ABDM_CERT_URL]);

    [$res, $http] = abdm_curl('GET', ABDM_CERT_URL, $headers, null, true);

    // Response may be a plain PEM string or { publicKey: "..." }
    $raw = null;
    if (is_string($res)) {
        $raw = $res;
    } elseif (!empty($res['publicKey'])) {
        $raw = $res['publicKey'];
    } elseif (!empty($res['_raw'])) {
        // Some sandbox versions return the PEM directly as plain text
        $raw = $res['_raw'];
    }

    if (!$raw || ($http < 200 || $http >= 300)) {
        $err = is_array($res) ? abdm_extract_error($res, $http, 'Failed to fetch ABDM public cert') : 'Empty cert response';
        abdm_log('Cert fetch failed', ['http' => $http, 'raw' => substr((string)$raw, 0, 200)]);
        throw new RuntimeException($err);
    }

    $pem = abdm_to_pem(trim($raw));

    // Validate the key is loadable before caching
    $key = openssl_pkey_get_public($pem);
    if (!$key) {
        abdm_log('Invalid public key received', ['pem_start' => substr($pem, 0, 100)]);
        throw new RuntimeException('ABDM returned an invalid public key: ' . openssl_error_string());
    }

    $_SESSION['abdm_public_key'] = $pem;
    $_SESSION['abdm_cert_exp']   = time() + 3600; // certs rotate infrequently
    abdm_log('ABDM public cert cached');
    return $pem;
}

/* ── Direct call ── */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');

    if (empty($_SESSION['doctor_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    try {
        $pem = abdm_get_public_key();
        echo json_encode(['success' => true, 'publicKey' => $pem]);
    } catch (Exception $e) {
        abdm_log('Direct cert request failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
