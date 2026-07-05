<?php
// debug_mobile_send.php - Debug the mobile OTP send

require_once __DIR__ . '/config/connect.php';
require_once __DIR__ . '/config/abdm.php';
require_once __DIR__ . '/doctor/api/abdm_rsa.php';
require_once __DIR__ . '/doctor/api/abdm_session.php';
require_once __DIR__ . '/doctor/api/abdm_get_cert.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['doctor_id'] = 1;

$mobile = '8368552640';

echo "=== Debugging Mobile OTP Send ===\n\n";

try {
    echo "1. Getting Access Token...\n";
    $token = abdm_get_access_token();
    echo "✅ Token obtained: " . substr($token, 0, 30) . "...\n\n";
    
    echo "2. Getting Public Key...\n";
    $cert = abdm_get_public_key();
    echo "✅ Public key obtained\n\n";
    
    echo "3. Encrypting Mobile...\n";
    $encryptedMobile = abdm_rsa_encrypt($mobile, $cert);
    echo "✅ Encrypted: " . substr($encryptedMobile, 0, 50) . "...\n\n";
    
    echo "4. Trying different approaches for mobile OTP...\n\n";
    
    // Approach 1: With mobile-verify scope
    echo "Approach 1: scope ['abha-enrol', 'mobile-verify']\n";
    $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/request/otp';
    
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'REQUEST-ID' => abdm_uuid(),
        'TIMESTAMP' => abdm_timestamp(),
        'X-CM-ID' => 'sbx',
    ];
    
    $body = [
        'txnId' => '',
        'scope' => ['abha-enrol', 'mobile-verify'],
        'loginHint' => 'mobile',
        'loginId' => $encryptedMobile,
        'otpSystem' => 'abdm',
    ];
    
    echo "   Request Body: " . json_encode([
        'txnId' => $body['txnId'],
        'scope' => $body['scope'],
        'loginHint' => $body['loginHint'],
        'loginId' => substr($body['loginId'], 0, 30) . '...',
        'otpSystem' => $body['otpSystem']
    ]) . "\n";
    
    [$res1, $http1] = abdm_curl('POST', $url, $headers, $body);
    echo "   HTTP Status: " . $http1 . "\n";
    echo "   Response: " . json_encode($res1) . "\n\n";
    
    // Approach 2: Without mobile-verify scope
    echo "Approach 2: scope ['abha-enrol'] only\n";
    $body2 = [
        'txnId' => '',
        'scope' => ['abha-enrol'],
        'loginHint' => 'mobile',
        'loginId' => $encryptedMobile,
        'otpSystem' => 'abdm',
    ];
    
    echo "   Request Body: " . json_encode([
        'txnId' => $body2['txnId'],
        'scope' => $body2['scope'],
        'loginHint' => $body2['loginHint'],
        'loginId' => substr($body2['loginId'], 0, 30) . '...',
        'otpSystem' => $body2['otpSystem']
    ]) . "\n";
    
    [$res2, $http2] = abdm_curl('POST', $url, $headers, $body2);
    echo "   HTTP Status: " . $http2 . "\n";
    echo "   Response: " . json_encode($res2) . "\n\n";
    
    // Approach 3: Without encryption (plain mobile)
    echo "Approach 3: Plain mobile (no encryption)\n";
    $body3 = [
        'txnId' => '',
        'scope' => ['abha-enrol', 'mobile-verify'],
        'loginHint' => 'mobile',
        'loginId' => $mobile, // Plain mobile
        'otpSystem' => 'abdm',
    ];
    
    echo "   Request Body: " . json_encode([
        'txnId' => $body3['txnId'],
        'scope' => $body3['scope'],
        'loginHint' => $body3['loginHint'],
        'loginId' => $body3['loginId'],
        'otpSystem' => $body3['otpSystem']
    ]) . "\n";
    
    [$res3, $http3] = abdm_curl('POST', $url, $headers, $body3);
    echo "   HTTP Status: " . $http3 . "\n";
    echo "   Response: " . json_encode($res3) . "\n\n";
    
    // Approach 4: Different endpoint - profile/login
    echo "Approach 4: Profile login endpoint\n";
    $url4 = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/login/request/otp';
    $body4 = [
        'loginHint' => 'mobile',
        'loginId' => $encryptedMobile,
        'otpSystem' => 'abdm',
    ];
    
    echo "   Request Body: " . json_encode([
        'loginHint' => $body4['loginHint'],
        'loginId' => substr($body4['loginId'], 0, 30) . '...',
        'otpSystem' => $body4['otpSystem']
    ]) . "\n";
    
    [$res4, $http4] = abdm_curl('POST', $url4, $headers, $body4);
    echo "   HTTP Status: " . $http4 . "\n";
    echo "   Response: " . json_encode($res4) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}