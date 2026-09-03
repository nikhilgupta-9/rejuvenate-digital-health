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

/**
 * POST a ready-made message payload to the WhatsApp Cloud API.
 * Shared by wa_send_text() / wa_send_template(). Returns the same result
 * shape as wa_send_otp().
 */
function _wa_post(array $payload, string $to): array
{
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
        error_log("[WhatsApp] cURL error for {$to}: {$curlErr}");
        return ['ok' => false, 'wamid' => null, 'error' => 'network_error', 'debug' => false];
    }

    $json = json_decode($body, true) ?: [];

    if ($httpCode >= 200 && $httpCode < 300 && !empty($json['messages'][0]['id'])) {
        return ['ok' => true, 'wamid' => $json['messages'][0]['id'], 'error' => null, 'debug' => false];
    }

    $apiErr = $json['error']['message'] ?? ('HTTP ' . $httpCode);
    error_log("[WhatsApp] send failed for {$to}: {$apiErr} | {$body}");
    return ['ok' => false, 'wamid' => null, 'error' => $apiErr, 'debug' => false];
}

/**
 * Send a plain-text WhatsApp message. Meta only delivers free-form text inside
 * the 24-hour customer-service window (i.e. after the recipient has messaged
 * this business number). For a cold, business-initiated message use
 * wa_send_template() with an approved template instead.
 *
 * When WhatsApp is not configured the message is written to the error log and
 * the call "succeeds" with debug=true, matching wa_send_otp().
 */
function wa_send_text(string $mobile, string $message): array
{
    $to = wa_normalize_number($mobile);

    if (!defined('WHATSAPP_CONFIGURED') || !WHATSAPP_CONFIGURED) {
        error_log("[WhatsApp] (dev/no-config) text to {$to}:\n{$message}");
        return ['ok' => true, 'wamid' => null, 'error' => null, 'debug' => true];
    }

    return _wa_post([
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['preview_url' => true, 'body' => $message],
    ], $to);
}

/**
 * Send an approved template message. $bodyParams fill the body placeholders
 * {{1}}, {{2}}, … in order. $lang defaults to WHATSAPP_TEMPLATE_LANG.
 */
function wa_send_template(string $mobile, string $template, array $bodyParams = [], ?string $lang = null): array
{
    $to = wa_normalize_number($mobile);

    if (!defined('WHATSAPP_CONFIGURED') || !WHATSAPP_CONFIGURED) {
        error_log("[WhatsApp] (dev/no-config) template '{$template}' to {$to}: " . json_encode($bodyParams));
        return ['ok' => true, 'wamid' => null, 'error' => null, 'debug' => true];
    }

    $components = [];
    if ($bodyParams) {
        $components[] = [
            'type'       => 'body',
            'parameters' => array_map(
                static fn($t) => ['type' => 'text', 'text' => (string) $t],
                array_values($bodyParams)
            ),
        ];
    }

    return _wa_post([
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'       => $template,
            'language'   => ['code' => $lang ?: WHATSAPP_TEMPLATE_LANG],
            'components' => $components,
        ],
    ], $to);
}

/**
 * Send a newly-created patient their login details over WhatsApp.
 *
 * This is business-initiated, so for reliable delivery it needs an approved
 * *utility* template. Set its name in WHATSAPP_ACCOUNT_TEMPLATE with four body
 * parameters, in order: {{1}} patient name, {{2}} login URL, {{3}} login id
 * (email or mobile), {{4}} temporary password.
 *
 * Without that env var we fall back to a plain-text message — which only lands
 * if the patient has messaged this number in the last 24h. The welcome email
 * carries the same details, so this stays best-effort.
 */
function wa_send_account_credentials(string $mobile, string $name, string $loginUrl, string $loginId, string $tempPassword): array
{
    $tpl = trim((string) ($_ENV['WHATSAPP_ACCOUNT_TEMPLATE'] ?? ''));
    if ($tpl !== '') {
        return wa_send_template($mobile, $tpl, [$name, $loginUrl, $loginId, $tempPassword]);
    }

    $msg = "*REJUVENATE Digital Health*\n\n"
         . "Hello {$name}, a patient account has been created for you by your doctor so you can view your visits, prescriptions and reports.\n\n"
         . "Sign in: {$loginUrl}\n"
         . "Username: {$loginId}\n"
         . "Temporary password: {$tempPassword}\n\n"
         . "Please sign in and change your password. Do not share this message with anyone.";

    return wa_send_text($mobile, $msg);
}
