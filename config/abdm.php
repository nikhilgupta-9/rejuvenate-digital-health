<?php
$abdm_env = $_ENV['ABDM_ENV'] ?? 'sandbox';

define('ABDM_ENV',           $abdm_env);
define('ABDM_CLIENT_ID',     $_ENV['ABDM_CLIENT_ID']     ?? '');
define('ABDM_CLIENT_SECRET', $_ENV['ABDM_CLIENT_SECRET'] ?? '');
define('ABDM_CONFIGURED',    !empty($_ENV['ABDM_CLIENT_ID']) && $_ENV['ABDM_CLIENT_ID'] !== 'your-sandbox-client-id');

// Gateway — OAuth token endpoint
// Note: ABHA v3 APIs (abhasbx.abdm.gov.in) accept tokens from v0.5 gateway.
// The /api/hiecm/gateway/v3/sessions URL in M1 doc is for HIECM, not ABHA.
define('ABDM_GATEWAY_URL', $abdm_env === 'production'
    ? 'https://live.abdm.gov.in/gateway'
    : 'https://dev.abdm.gov.in/gateway');

// ABHA v3 API base (old healthidsbx.abdm.gov.in is decommissioned; new host is abhasbx)
define('ABDM_HEALTH_ID_URL', $abdm_env === 'production'
    ? 'https://abha.abdm.gov.in/abha/api/v3'
    : 'https://abhasbx.abdm.gov.in/abha/api/v3');

// X-CM-ID header value required by v3 APIs
define('ABDM_X_CM_ID', $abdm_env === 'production' ? 'abdm' : 'sbx');

// Skip SSL peer verification on sandbox (XAMPP/localhost has no CA bundle)
define('ABDM_SSL_VERIFY', $abdm_env === 'production');
