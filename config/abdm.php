<?php
// config/abdm.php

// Environment
define('ABDM_ENV', 'sandbox');

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

// Your ABDM credentials (HARDCODED - from your .env)
define('ABDM_CLIENT_ID', 'SBXID_038789');
define('ABDM_CLIENT_SECRET', '2a019e04-798c-449e-8aef-c0d2f7a22a93');

// SSL Verification
define('ABDM_SSL_VERIFY', true);

// Flag
define('ABDM_CONFIGURED', !empty(ABDM_CLIENT_ID) && !empty(ABDM_CLIENT_SECRET));