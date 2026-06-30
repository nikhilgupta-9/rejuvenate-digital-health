<?php
/**
 * ABDM Debug Script — remove after testing
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/config/connect.php';
include_once __DIR__ . '/config/abdm.php';
include_once __DIR__ . '/lib/AbdmApi.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ABDM Debug ===\n\n";
echo "ABDM_ENV:            " . ABDM_ENV . "\n";
echo "ABDM_CLIENT_ID:      " . ABDM_CLIENT_ID . "\n";
echo "ABDM_GATEWAY_URL:    " . ABDM_GATEWAY_URL . "\n";
echo "ABDM_HEALTH_ID_URL:  " . ABDM_HEALTH_ID_URL . "\n";
echo "ABDM_X_CM_ID:        " . ABDM_X_CM_ID . "\n";
echo "ABDM_CONFIGURED:     " . (ABDM_CONFIGURED ? 'YES' : 'NO') . "\n\n";

// ── Step 1: Get access token — try v3 first, fall back to v0.5 ───
function tryToken(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'clientId'     => ABDM_CLIENT_ID,
            'clientSecret' => ABDM_CLIENT_SECRET,
            'grantType'    => 'client_credentials',
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body  = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $cerr  = curl_error($ch);
    curl_close($ch);
    return [$http, $body, $errno, $cerr];
}

echo "--- Step 1: OAuth Token ---\n";
$token_url = ABDM_GATEWAY_URL . '/v0.5/sessions';
echo "URL: $token_url\n";
[$http, $body, $errno, $cerr] = tryToken($token_url);
if ($errno) { echo "cURL Error: $cerr\n"; exit; }
echo "HTTP: $http\n";
$tok = json_decode($body, true);
$access_token = $tok['accessToken'] ?? '';
echo "Access Token: " . ($access_token ? substr($access_token, 0, 40) . '...' : 'FAILED') . "\n";
if (!$access_token) { echo "Full response: $body\n"; exit; }

// ── Step 2: Get public cert ───────────────────────────────────────
echo "\n--- Step 2: Public Certificate ---\n";
$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
    mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
    mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
$ts = gmdate('Y-m-d\TH:i:s.000\Z');

$ch = curl_init(ABDM_HEALTH_ID_URL . '/profile/public/certificate');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Authorization: Bearer ' . $access_token,
        'REQUEST-ID: ' . $uuid,
        'TIMESTAMP: ' . $ts,
        'X-CM-ID: ' . ABDM_X_CM_ID,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$body  = curl_exec($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: $http\n";
$cert_data = json_decode($body, true);
$pub_key_raw = $cert_data['publicKey'] ?? '';
$enc_algo    = $cert_data['encryptionAlgorithm'] ?? '';
echo "Algorithm: $enc_algo\n";
echo "PublicKey: " . ($pub_key_raw ? substr($pub_key_raw, 0, 40) . '...' : 'FAILED') . "\n";
if (!$pub_key_raw) { echo "Full response: $body\n"; exit; }

// ── Step 3: RSA Encrypt ───────────────────────────────────────────
echo "\n--- Step 3: RSA Encryption ---\n";
$clean = preg_replace('/\s+/', '', $pub_key_raw);
$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($clean, 64, "\n") . "-----END PUBLIC KEY-----\n";
echo "PEM snippet: " . substr($pem, 0, 80) . "...\n";

$key = openssl_pkey_get_public($pem);
echo "Key loaded: " . ($key ? 'YES' : 'NO — ' . openssl_error_string()) . "\n";

$aadhaar = '543499461515';  // test Aadhaar
$encrypted = '';
$ok = openssl_public_encrypt($aadhaar, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING);
echo "Encrypted: " . ($ok ? 'YES' : 'NO — ' . openssl_error_string()) . "\n";
$b64 = base64_encode($encrypted);
echo "Base64 length: " . strlen($b64) . " chars\n";
echo "Base64 snippet: " . substr($b64, 0, 60) . "...\n";

// ── Step 4: Call enrollment OTP ──────────────────────────────────
echo "\n--- Step 4: Enrollment OTP Request ---\n";
$uuid2 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
    mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
    mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
$ts2 = gmdate('Y-m-d\TH:i:s.000\Z');

$payload = [
    'txnId'      => '',
    'scope'      => ['abha-enrol'],
    'loginHint'  => 'aadhaar',
    'loginId'    => $b64,
    'otpSystem'  => 'aadhaar',
];
$json_payload = json_encode($payload);
echo "Payload: " . substr($json_payload, 0, 120) . "...\n";

$ch = curl_init(ABDM_HEALTH_ID_URL . '/enrollment/request/otp');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json_payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $access_token,
        'REQUEST-ID: ' . $uuid2,
        'TIMESTAMP: ' . $ts2,
        'X-CM-ID: ' . ABDM_X_CM_ID,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_VERBOSE        => false,
]);
$body  = curl_exec($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errno = curl_errno($ch);
$cerr  = curl_error($ch);
curl_close($ch);

echo "HTTP: $http\n";
if ($errno) echo "cURL Error: $cerr\n";
echo "Response: $body\n";

// ── Step 5: Probe /profile/login/request/otp with combinations ───
echo "\n--- Step 5: Profile Login OTP — scope/otpSystem probe ---\n";

// RSA-encrypt the formatted ABHA number (XX-XXXX-XXXX-XXXX)
$abhaRaw = '68624083728259';
$abhaFmt = substr($abhaRaw,0,2).'-'.substr($abhaRaw,2,4).'-'.substr($abhaRaw,6,4).'-'.substr($abhaRaw,10,4);
$encAbha = '';
openssl_public_encrypt($abhaFmt, $encAbha, $key, OPENSSL_PKCS1_OAEP_PADDING);
$b64Abha = base64_encode($encAbha);

$combos = [
    // otpSystem confirmed valid = "abdm" (from combos 3,4 last run)
    // Now probe scope values
    ['scope'=>['abha-enrol'],                       'otpSystem'=>'abdm'],
    ['scope'=>['mobile-verify'],                    'otpSystem'=>'abdm'],
    ['scope'=>['dl-flow'],                          'otpSystem'=>'abdm'],
    ['scope'=>['abha-profile'],                     'otpSystem'=>'abdm'],
    ['scope'=>['abha-account'],                     'otpSystem'=>'abdm'],
    ['scope'=>['abha-enrol','abha-login'],          'otpSystem'=>'abdm'],
    ['scope'=>['abha-address','abha-enrol'],        'otpSystem'=>'abdm'],
    // try loginHint=mobile with abha-enrol
    ['scope'=>['abha-enrol'], 'otpSystem'=>'abdm', 'loginHint'=>'mobile'],
];

foreach ($combos as $i => $extra) {
    $payload = array_merge(['loginHint'=>'abha-number','loginId'=>$b64Abha], $extra);
    $uuid3 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
    $ts3 = gmdate('Y-m-d\TH:i:s.000\Z');
    $ch = curl_init(ABDM_HEALTH_ID_URL . '/profile/login/request/otp');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $access_token,
            'REQUEST-ID: ' . $uuid3,
            'TIMESTAMP: ' . $ts3,
            'X-CM-ID: ' . ABDM_X_CM_ID,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body3  = curl_exec($ch);
    $http3  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $label = json_encode($extra);
    echo "Combo $i $label → HTTP $http3: $body3\n";
    sleep(1); // avoid rate limiting
}

echo "\n=== Done ===\n";
