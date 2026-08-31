<?php
/**
 * config/whatsapp.php — Meta WhatsApp Cloud API (OTP delivery).
 *
 * Credentials come from .env — never hardcode them here (unlike the legacy
 * config/abdm.php). config/connect.php already loads Dotenv, so including
 * this file after connect.php is enough; the safeLoad() below covers the
 * rare case where it is included on its own.
 *
 * Required .env keys for live sending:
 *   WHATSAPP_PHONE_NUMBER_ID   — the phone-number ID from Meta (not the number)
 *   WHATSAPP_ACCESS_TOKEN      — permanent system-user token
 *
 * Optional:
 *   WHATSAPP_ENABLED           — "true"/"false" master switch (default true)
 *   WHATSAPP_API_VERSION       — Graph API version (default v21.0)
 *   WHATSAPP_OTP_TEMPLATE      — approved authentication template (default otp_verification)
 *   WHATSAPP_TEMPLATE_LANG     — template language code (default en)
 *   WHATSAPP_OTP_HAS_BUTTON    — send the copy-code button param (default true)
 *   WHATSAPP_DEFAULT_COUNTRY_CODE — prefix for 10-digit numbers (default 91)
 */

if (class_exists(\Dotenv\Dotenv::class) && !isset($_ENV['SITE'])) {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

if (!defined('WHATSAPP_API_VERSION')) {
    $waEnabled = strtolower((string)($_ENV['WHATSAPP_ENABLED'] ?? 'true')) !== 'false';

    define('WHATSAPP_ENABLED',              $waEnabled);
    define('WHATSAPP_API_VERSION',          $_ENV['WHATSAPP_API_VERSION']          ?? 'v21.0');
    define('WHATSAPP_PHONE_NUMBER_ID',      $_ENV['WHATSAPP_PHONE_NUMBER_ID']      ?? '');
    define('WHATSAPP_ACCESS_TOKEN',         $_ENV['WHATSAPP_ACCESS_TOKEN']         ?? '');
    define('WHATSAPP_OTP_TEMPLATE',         $_ENV['WHATSAPP_OTP_TEMPLATE']         ?? 'otp_verification');
    define('WHATSAPP_TEMPLATE_LANG',        $_ENV['WHATSAPP_TEMPLATE_LANG']        ?? 'en');
    define('WHATSAPP_OTP_HAS_BUTTON',       strtolower((string)($_ENV['WHATSAPP_OTP_HAS_BUTTON'] ?? 'true')) !== 'false');
    define('WHATSAPP_DEFAULT_COUNTRY_CODE', preg_replace('/\D/', '', (string)($_ENV['WHATSAPP_DEFAULT_COUNTRY_CODE'] ?? '91')) ?: '91');

    define('WHATSAPP_CONFIGURED', WHATSAPP_ENABLED
        && WHATSAPP_PHONE_NUMBER_ID !== ''
        && WHATSAPP_ACCESS_TOKEN !== '');
}
