<?php
// config/abdm.php
//
// ABDM / ABHA configuration. Credentials + environment come from .env —
// never hardcode them here (see config/whatsapp.php for the same pattern).
// config/connect.php already loads Dotenv, so including this file after
// connect.php is enough; the safeLoad() below covers the rare case where
// it is included on its own.
//
// Required .env keys:
//   ABDM_CLIENT_ID       — ABDM (ABHA) application client id
//   ABDM_CLIENT_SECRET   — ABDM (ABHA) application client secret
// Optional:
//   ABDM_ENV             — "sandbox" (default) | "production"
//   ABDM_SSL_VERIFY      — "true" (default) | "false"
//   ABDM_HPR_CLIENT_ID     — HPR application client id  (may differ from the ABHA one)
//   ABDM_HPR_CLIENT_SECRET — HPR application client secret

if (class_exists(\Dotenv\Dotenv::class) && !isset($_ENV['ABDM_CLIENT_ID'])) {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

if (!defined('ABDM_ENV')) {
    // Environment — from .env, defaults to the safe "sandbox" value.
    $abdmEnv = strtolower(trim((string)($_ENV['ABDM_ENV'] ?? 'sandbox')));
    if ($abdmEnv !== 'production') {
        $abdmEnv = 'sandbox';
    }
    define('ABDM_ENV', $abdmEnv);

    // ABDM API Endpoints
    if (ABDM_ENV === 'sandbox') {
        define('ABDM_GATEWAY_URL', 'https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions');
        define('ABDM_HEALTH_ID_URL', 'https://abhasbx.abdm.gov.in/abha/api/v3');
        define('ABDM_X_CM_ID', 'sbx');
    } else {
        define('ABDM_GATEWAY_URL', 'https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions');
        define('ABDM_HEALTH_ID_URL', 'https://abha.abdm.gov.in/abha/api/v3');
        define('ABDM_X_CM_ID', 'abdm');
    }

    // ABDM credentials — from .env only.
    define('ABDM_CLIENT_ID',     trim((string)($_ENV['ABDM_CLIENT_ID']     ?? '')));
    define('ABDM_CLIENT_SECRET', trim((string)($_ENV['ABDM_CLIENT_SECRET'] ?? '')));

    // SSL Verification — on unless explicitly disabled in .env.
    define('ABDM_SSL_VERIFY', strtolower((string)($_ENV['ABDM_SSL_VERIFY'] ?? 'true')) !== 'false');

    // Flag — true only when both credentials are present.
    define('ABDM_CONFIGURED', ABDM_CLIENT_ID !== '' && ABDM_CLIENT_SECRET !== '');

    /* ── HPR (Health Professional Registry) — doctor HPR ID verification ── */
    // Gateway session endpoint is shared across ABDM services; the HPR service
    // itself sits on a different host.
    define('ABDM_HPR_GATEWAY_URL', 'https://dev.abdm.gov.in/api/hiecm/gateway');
    if (ABDM_ENV === 'sandbox') {
        define('ABDM_HPR_BASE_URL', 'https://apihspsbx.abdm.gov.in/v4/int');
    } else {
        // Production HPR host — confirm against the NHA docs before go-live.
        define('ABDM_HPR_BASE_URL', 'https://apihsp.abdm.gov.in/v4/int');
    }
    define('ABDM_HPR_CLIENT_ID',     trim((string)($_ENV['ABDM_HPR_CLIENT_ID']     ?? '')));
    define('ABDM_HPR_CLIENT_SECRET', trim((string)($_ENV['ABDM_HPR_CLIENT_SECRET'] ?? '')));
    define('ABDM_HPR_CONFIGURED', ABDM_HPR_CLIENT_ID !== '' && ABDM_HPR_CLIENT_SECRET !== '');

    /* ── HIP-Initiated Linking (M3, HIECM V3) ── */
    // Uses the same ABHA gateway session token (ABDM_CLIENT_ID/SECRET); the
    // HIP identity goes in the X-HIP-ID header.
    define('ABDM_HIECM_BASE_URL', 'https://dev.abdm.gov.in/api/hiecm');
    define('ABDM_HIP_ID',   trim((string)($_ENV['ABDM_HIP_ID']   ?? '')));
    define('ABDM_HIP_NAME', trim((string)($_ENV['ABDM_HIP_NAME'] ?? 'Rejuvenate Digital Health')));
    define('ABDM_HIP_CONFIGURED', ABDM_CONFIGURED && ABDM_HIP_ID !== '');

    // Webhook (telemedicine/api/abdm-webhook.php) — optional hardening.
    define('ABDM_WEBHOOK_SECRET',      trim((string)($_ENV['ABDM_WEBHOOK_SECRET']      ?? '')));
    define('ABDM_WEBHOOK_ALLOWED_IPS', trim((string)($_ENV['ABDM_WEBHOOK_ALLOWED_IPS'] ?? '')));
}
