<?php

/**
 * ABDM RSA encryption helper.
 * Include this file in other ABDM API files — never call directly.
 *
 * Algorithm: RSA/ECB/OAEPWithSHA-1AndMGF1Padding
 * PHP constant: OPENSSL_PKCS1_OAEP_PADDING
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Load project config (dotenv + DB + BASE_URL + JWT_SECRET)
$_abdmConfigPath = dirname(dirname(dirname(__FILE__))) . '/config/connect.php';
if (file_exists($_abdmConfigPath) && !defined('BASE_URL')) require_once $_abdmConfigPath;
unset($_abdmConfigPath);

// Load ABDM constants (ABDM_CLIENT_ID, ABDM_CLIENT_SECRET, ABDM_GATEWAY_URL, etc.)
$_abdmCfgPath = dirname(dirname(dirname(__FILE__))) . '/config/abdm.php';
if (file_exists($_abdmCfgPath) && !defined('ABDM_CONFIGURED')) require_once $_abdmCfgPath;
unset($_abdmCfgPath);

/* ── Verhoeff algorithm for Aadhaar validation ── */

function abdm_verhoeff_validate(string $num): bool
{
    // Verhoeff multiplication table
    static $d = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
    ];
    // Permutation table
    static $p = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
    ];
    // Inverse table
    static $inv = [0, 4, 3, 2, 1, 9, 8, 7, 6, 5];

    $digits = array_reverse(str_split($num));
    $check  = 0;
    foreach ($digits as $i => $digit) {
        $check = $d[$check][$p[$i % 8][(int)$digit]];
    }
    return $check === 0;
}

/**
 * Validate Aadhaar: 12 digits + Verhoeff checksum.
 * Returns true if valid, string error message if invalid.
 */
function abdm_validate_aadhaar(string $aadhaar): bool|string
{
    $clean = preg_replace('/\D/', '', $aadhaar);
    if (strlen($clean) !== 12) {
        return 'Aadhaar must be exactly 12 digits';
    }
    if (in_array($clean[0], ['0', '1'], true)) {
        return 'Aadhaar cannot start with 0 or 1';
    }
    if (!abdm_verhoeff_validate($clean)) {
        return 'Invalid Aadhaar number (checksum failed)';
    }
    return true;
}

/**
 * RSA-OAEP encrypt plaintext with ABDM public key PEM.
 * Returns base64-encoded ciphertext.
 * Algorithm: RSA/ECB/OAEPWithSHA-1AndMGF1Padding
 */
function abdm_rsa_encrypt(string $plaintext, string $publicKeyPem): string
{
    $key = openssl_pkey_get_public($publicKeyPem);
    if (!$key) {
        throw new RuntimeException('ABDM: Cannot load public key — ' . openssl_error_string());
    }
    $encrypted = '';
    if (!openssl_public_encrypt($plaintext, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('ABDM RSA encryption failed — ' . openssl_error_string());
    }
    return base64_encode($encrypted);
}

/** Wrap bare base64 in PEM markers if not already wrapped. */
function abdm_to_pem(string $keyData): string
{
    if (strpos($keyData, '-----BEGIN') !== false) return $keyData;
    $clean = preg_replace('/\s+/', '', $keyData);
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split($clean, 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

/** Generate UUID v4 (cryptographically random). */
function abdm_uuid(): string
{
    $bytes    = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/** ISO-8601 UTC timestamp with milliseconds (ABDM format). */
function abdm_timestamp(): string
{
    return gmdate('Y-m-d\TH:i:s.000\Z');
}

/** Log an error to the ABDM error log file. */
// In abdm_rsa.php
function abdm_log(string $message, array $context = []): void
{
    $logDir = dirname(dirname(dirname(__FILE__))) . '/logs';
    $logFile = $logDir . '/abdm_' . date('Y-m-d') . '.log'; // Daily log files

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logLine = "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL;

    error_log($logLine, 3, $logFile);

    // Also log to PHP error log for immediate visibility
    error_log("ABDM: {$message}" . $contextStr);
}

/**
 * Execute a cURL request and return [decoded_body, http_code].
 * All ABDM requests must use this wrapper to ensure correct options.
 */
function abdm_curl(
    string $method,
    string $url,
    array  $headers = [],
    mixed  $body    = null,
    bool   $verify  = true
): array {
    // Log the request
    abdm_log('cURL Request', [
        'method' => $method,
        'url' => $url,
        'headers' => array_keys($headers),
        'body_size' => $body ? strlen(is_string($body) ? $body : json_encode($body)) : 0
    ]);

    // If body is array, log structure (not values)
    if (is_array($body)) {
        $logBody = [];
        foreach ($body as $key => $value) {
            if (is_string($value) && strlen($value) > 100) {
                $logBody[$key] = substr($value, 0, 50) . '... (truncated)';
            } else {
                $logBody[$key] = $value;
            }
        }
        abdm_log('Request Body Structure', $logBody);
    }

    $ch = curl_init($url);

    $curlHeaders = [];
    foreach ($headers as $k => $v) {
        $curlHeaders[] = "$k: $v";
    }

    $opts = [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_VERBOSE        => true,
    ];

    if ($body !== null) {
        $raw = is_string($body) ? $body : json_encode($body);
        $opts[CURLOPT_POSTFIELDS] = $raw;
        // FIXED: Pass array instead of string
        abdm_log('Request Body (truncated)', ['body' => substr($raw, 0, 500)]);
    }

    // Capture verbose output for debugging
    $verboseFile = fopen('php://temp', 'w+');
    $opts[CURLOPT_STDERR] = $verboseFile;

    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errMsg   = curl_error($ch);
    $http     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Get verbose output
    rewind($verboseFile);
    $verboseOutput = stream_get_contents($verboseFile);
    fclose($verboseFile);

    if ($verboseOutput) {
        abdm_log('cURL Verbose Output', ['output' => $verboseOutput]);
    }

    curl_close($ch);

    if ($errno) {
        abdm_log("cURL error [$errno]: $errMsg → $url");
        throw new RuntimeException("Network error connecting to ABDM: $errMsg");
    }

    abdm_log('cURL Response', [
        'http_code' => $http,
        'response_size' => strlen($response)
    ]);

    $decoded = json_decode($response, true);
    if ($decoded === null && !empty($response)) {
        abdm_log('Non-JSON response', [
            'url' => $url,
            'http' => $http,
            'body' => substr($response, 0, 500)
        ]);
        return [['_raw' => substr($response, 0, 500)], $http];
    }

    return [$decoded ?? [], $http];
}

/**
 * Extract a human-readable error message from an ABDM error response.
 * ABDM v3 error structure: { details:[{message}], message, code }
 */
function abdm_extract_error(array $body, int $http, string $fallback = 'ABDM API error'): string
{
    $msg = $body['details'][0]['message']
        ?? $body['details'][0]['attribute']
        ?? $body['message']
        ?? $body['error']['message']
        ?? $body['errors'][0]['message']
        ?? $body['code']
        ?? null;

    // ABDM's field-validation errors come back as { "<fieldName>": "<message>", "timestamp": "..." }
    // e.g. {"mobile":"Invalid Mobile Number"} or {"txnId":"Invalid Transaction Id"} — no fixed key name.
    if ($msg === null) {
        foreach ($body as $key => $value) {
            if ($key !== 'timestamp' && is_string($value)) {
                $msg = $value;
                break;
            }
        }
    }

    if ($msg) return $msg;

    if ($http === 400) return 'ABDM returned Bad Request — check input values';
    if ($http === 401) return 'ABDM authentication failed — gateway token expired';
    if ($http === 404) return 'ABHA not found in ABDM records';
    if ($http === 422) return 'ABDM rejected input — check format';
    if ($http === 429) return 'Too many ABDM requests — please wait a minute';
    if ($http >= 500)  return 'ABDM server error — please try again later';

    if (!empty($body['_raw'])) return $fallback . ': ' . substr($body['_raw'], 0, 100);
    return $fallback;
}
