<?php
/**
 * lib/WhatsAppOtp.php — send a one-time password over the Meta WhatsApp Cloud API.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/whatsapp.php';
 *   require_once __DIR__ . '/WhatsAppOtp.php';
 *   $r = wa_send_otp('9876543210', '123456');
 *   // $r = ['ok' => bool, 'wamid' => ?string, 'error' => ?string, 'debug' => bool]
 *
 * When WhatsApp is not configured (no phone-number ID / token) or disabled,
 * the OTP is written to the PHP error log and the call "succeeds" with
 * debug=true so local development flows keep working — this mirrors the
 * existing __debug_otp behaviour in ajax/login-send-otp.php.
 *
 * Requires an approved authentication-category template (default name
 * "otp_verification") with a single body parameter {{1}} and, when
 * WHATSAPP_OTP_HAS_BUTTON is on, a one-tap / copy-code button.
 */

if (!defined('WHATSAPP_API_VERSION')) {
    require_once __DIR__ . '/../config/whatsapp.php';
}

/**
 * Normalise an Indian mobile number to WhatsApp's "E.164 without +" form.
 * "9876543210" -> "919876543210", "+91 98765 43210" -> "919876543210".
 */
function wa_normalize_number(string $mobile): string
{
    $digits = preg_replace('/\D/', '', $mobile);

    if (strlen($digits) === 10) {
        $digits = WHATSAPP_DEFAULT_COUNTRY_CODE . $digits;
    } elseif (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = WHATSAPP_DEFAULT_COUNTRY_CODE . substr($digits, 1);
    }

    return $digits;
}

/**
 * Send the OTP template message. Returns a result array (see file header).
 */
function wa_send_otp(string $mobile, string $otp): array
{
    $to = wa_normalize_number($mobile);

    if (!defined('WHATSAPP_CONFIGURED') || !WHATSAPP_CONFIGURED) {
        error_log("[WhatsAppOtp] (dev/no-config) OTP for {$to}: {$otp}");
        return ['ok' => true, 'wamid' => null, 'error' => null, 'debug' => true];
    }

    $components = [[
        'type'       => 'body',
        'parameters' => [['type' => 'text', 'text' => $otp]],
    ]];

    if (WHATSAPP_OTP_HAS_BUTTON) {
        // Copy-code / one-tap button of an authentication template.
        $components[] = [
            'type'       => 'button',
            'sub_type'   => 'url',
            'index'      => '0',
            'parameters' => [['type' => 'text', 'text' => $otp]],
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'       => WHATSAPP_OTP_TEMPLATE,
            'language'   => ['code' => WHATSAPP_TEMPLATE_LANG],
            'components' => $components,
        ],
    ];

    $url = 'https://graph.facebook.com/' . WHATSAPP_API_VERSION
         . '/' . WHATSAPP_PHONE_NUMBER_ID . '/messages';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
    ]);

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[WhatsAppOtp] cURL error for {$to}: {$curlErr}");
        return ['ok' => false, 'wamid' => null, 'error' => 'network_error', 'debug' => false];
    }

    $json = json_decode($body, true) ?: [];

    if ($httpCode >= 200 && $httpCode < 300 && !empty($json['messages'][0]['id'])) {
        return ['ok' => true, 'wamid' => $json['messages'][0]['id'], 'error' => null, 'debug' => false];
    }

    $apiErr = $json['error']['message'] ?? ('HTTP ' . $httpCode);
    error_log("[WhatsAppOtp] send failed for {$to}: {$apiErr} | {$body}");
    return ['ok' => false, 'wamid' => null, 'error' => $apiErr, 'debug' => false];
}
