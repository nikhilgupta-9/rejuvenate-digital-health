<?php
// test_otp_verify_final.php

require_once __DIR__ . '/config/connect.php';
require_once __DIR__ . '/config/abdm.php';
require_once __DIR__ . '/doctor/api/abdm_rsa.php';
require_once __DIR__ . '/doctor/api/abdm_session.php';
require_once __DIR__ . '/doctor/api/abdm_get_cert.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['doctor_id'] = 1;

// Use the txnId from your successful OTP send
$txnId = '822ee74a-79f4-4727-a13f-a8e9ee0f83d1';
$otp = '619772';
$mobile = "8368552640";

echo "=== Testing Aadhaar OTP Verification (Mobile without encryption) ===\n\n";

try {
    echo "1. Getting Access Token...\n";
    $token = abdm_get_access_token();
    echo "✅ Token obtained\n\n";
    
    echo "2. Getting Public Key...\n";
    $cert = abdm_get_public_key();
    echo "✅ Public key obtained\n\n";
    
    echo "3. Encrypting OTP only (Mobile in plain text)...\n";
    $encryptedOtp = abdm_rsa_encrypt($otp, $cert);
    echo "✅ Encrypted OTP: " . substr($encryptedOtp, 0, 50) . "...\n";
    echo "✅ Mobile (plain): " . $mobile . "\n\n";
    
    echo "4. Verifying OTP with /enrollment/enrol/byAadhaar endpoint...\n";
    
    $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/byAadhaar';
    
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'REQUEST-ID' => abdm_uuid(),
        'TIMESTAMP' => abdm_timestamp(),
        'X-CM-ID' => 'sbx',
    ];
    
    // Try mobile without encryption
    $body = [
        'authData' => [
            'authMethods' => ['otp'],
            'otp' => [
                'txnId' => $txnId,
                'otpValue' => $encryptedOtp,
                'mobile' => $mobile, // Plain text mobile
            ]
        ],
        'consent' => [
            'code' => 'abha-enrollment',
            'version' => '1.4'
        ]
    ];
    
    echo "   Request Body:\n" . json_encode([
        'authData' => [
            'authMethods' => $body['authData']['authMethods'],
            'otp' => [
                'txnId' => $body['authData']['otp']['txnId'],
                'otpValue' => substr($body['authData']['otp']['otpValue'], 0, 30) . '...',
                'mobile' => $body['authData']['otp']['mobile']
            ]
        ],
        'consent' => $body['consent']
    ], JSON_PRETTY_PRINT) . "\n\n";
    
    [$res, $http] = abdm_curl('POST', $url, $headers, $body);
    
    echo "   HTTP Status: " . $http . "\n";
    echo "   Response:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($http >= 200 && $http < 300) {
        if (isset($res['ABHAProfile']) || isset($res['ABHANumber'])) {
            echo "✅ OTP Verification Successful!\n";
            if (isset($res['ABHAProfile'])) {
                echo "   ABHA Number: " . ($res['ABHAProfile']['ABHANumber'] ?? 'N/A') . "\n";
                echo "   Name: " . ($res['ABHAProfile']['firstName'] ?? '') . " " . ($res['ABHAProfile']['lastName'] ?? '') . "\n";
            }
        } else {
            echo "❌ OTP Verification Failed: " . ($res['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ HTTP Error: " . $http . "\n";
        if (isset($res['error'])) {
            echo "   Error: " . ($res['error']['message'] ?? json_encode($res['error'])) . "\n";
        } else {
            echo "   Response: " . json_encode($res) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}