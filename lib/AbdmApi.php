<?php

/**
 * AbdmApi — ABDM / ABHA API v3 client (spec v1.3, July 2025)
 *
 * OAuth Gateway:    POST https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions
 * ABHA v3 Sandbox: https://abhasbx.abdm.gov.in/abha/api/v3
 * ABHA v3 Prod:    https://abha.abdm.gov.in/abha/api/v3
 *
 * Mandatory headers on every v3 request:
 *   Authorization : Bearer <accessToken>
 *   REQUEST-ID    : UUID v4 (cryptographically random)
 *   TIMESTAMP     : ISO-8601 UTC  e.g. 2025-07-01T10:30:00.000Z
 *   X-CM-ID       : sbx  (sandbox) | abdm (production)
 *   Content-Type  : application/json
 *   Accept        : application/json
 *
 * Sensitive actions additionally need:
 *   T-Token       : Bearer <userToken>  (profile / ABHA-card endpoints)
 */
class AbdmApi
{
    private string $gateway;
    private string $base;
    private string $clientId;
    private string $clientSecret;
    private string $xCmId;
    private bool   $sslVerify;

    public function __construct()
    {
        $this->gateway      = ABDM_GATEWAY_URL;
        $this->base         = rtrim(ABDM_HEALTH_ID_URL, '/');
        $this->clientId     = ABDM_CLIENT_ID;
        $this->clientSecret = ABDM_CLIENT_SECRET;
        $this->xCmId        = ABDM_X_CM_ID;
        $this->sslVerify    = ABDM_SSL_VERIFY;
    }

    /* ═══════════════════════════════════════════════════════════════
       1. OAUTH ACCESS TOKEN
          POST /gateway/v0.5/sessions
          Body: { clientId, clientSecret, grantType: "client_credentials" }
          Response: { accessToken, tokenType, expiresIn, refreshToken }
          Cached 25 min in session (token valid 30 min from ABDM).
    ═══════════════════════════════════════════════════════════════ */

    public function getAccessToken(): string
    {
        if (
            !empty($_SESSION['abdm_token'])
            && !empty($_SESSION['abdm_token_exp'])
            && time() < (int)$_SESSION['abdm_token_exp']
        ) {
            return $_SESSION['abdm_token'];
        }

        $oauthData = [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'grantType' => 'client_credentials'
        ];

        // Log the credentials being used (mask sensitive data)
        error_log("ABDM OAuth Request - Client ID: " . substr($this->clientId, 0, 10) . "...");
        error_log("ABDM OAuth URL: " . $this->gateway);

        $res = $this->rawPost(
            $this->gateway,
            $oauthData,
            '', // No bearer token
            true, // Send v3 headers
            [] // No extra headers
        );

        // Log the full response for debugging
        error_log("ABDM OAuth Full Response: " . json_encode($res));

        // Check for access token in both camelCase and snake_case
        $accessToken = $res['accessToken'] ?? $res['access_token'] ?? '';

        if (!$accessToken) {
            // Build error message safely
            $errorMsg = 'Unknown error';

            if (isset($res['error'])) {
                if (is_array($res['error'])) {
                    $code = $res['error']['code'] ?? '';
                    $message = $res['error']['message'] ?? '';
                    $errorMsg = "[{$code}] {$message}";
                } else {
                    $errorMsg = (string)$res['error'];
                }
            } elseif (isset($res['message']) && is_string($res['message'])) {
                $errorMsg = $res['message'];
            } elseif (isset($res['_raw'])) {
                $errorMsg = 'Raw response: ' . substr($res['_raw'], 0, 200);
            } else {
                $errorMsg = json_encode($res);
            }

            throw new RuntimeException("ABDM OAuth failed: {$errorMsg}");
        }

        // Get expires in (both camelCase and snake_case)
        $expiresIn = (int)($res['expiresIn'] ?? $res['expires_in'] ?? 1200);
        $ttl = max(300, $expiresIn - 300);

        $_SESSION['abdm_token'] = $accessToken;
        $_SESSION['abdm_token_exp'] = time() + $ttl;

        // Log success
        error_log("ABDM OAuth Success - Token: " . substr($accessToken, 0, 30) . "...");
        error_log("ABDM OAuth - Expires in: " . $ttl . " seconds");

        return $accessToken;
    }

    /* ═══════════════════════════════════════════════════════════════
       2. RSA ENCRYPTION
          GET /profile/public/certificate
          Response: { publicKey, encryptionAlgorithm }
          Algorithm: RSA/ECB/OAEPWithSHA-1AndMGF1Padding
          Used for: Aadhaar numbers, OTP values before sending to ABDM.
    ═══════════════════════════════════════════════════════════════ */

    public function getPublicCert(): string
    {
        if (
            !empty($_SESSION['abdm_cert'])
            && !empty($_SESSION['abdm_cert_exp'])
            && time() < (int)$_SESSION['abdm_cert_exp']
        ) {
            return $_SESSION['abdm_cert'];
        }

        $token = $this->getAccessToken();

        // CORRECT endpoint from Postman
        $res = $this->rawGet($this->base . '/profile/public/certificate', $token);

        $raw = $res['publicKey'] ?? '';
        if (!$raw) {
            throw new RuntimeException('ABDM: Could not fetch public certificate');
        }

        $pem = $this->toPem($raw);

        // Validate key
        $key = openssl_pkey_get_public($pem);
        if (!$key) {
            throw new RuntimeException('ABDM public key is invalid');
        }

        $_SESSION['abdm_cert'] = $pem;
        $_SESSION['abdm_cert_exp'] = time() + 3600;

        return $pem;
    }

    /**
     * RSA-OAEP (SHA-1) encrypt plaintext with ABDM public key.
     * Used for: Aadhaar number, OTP value, mobile number (M2 enrollment).
     */
    public function rsaEncrypt(string $plaintext): string
    {
        $pem = $this->getPublicCert();
        $key = openssl_pkey_get_public($pem);
        if (!$key) {
            throw new RuntimeException('ABDM: Cannot load public key: ' . openssl_error_string());
        }

        $encrypted = '';
        // RSA/ECB/PKCS1Padding — as required by ABDM v3 spec
        if (!openssl_public_encrypt($plaintext, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('ABDM RSA encryption failed: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    /* ═══════════════════════════════════════════════════════════════
       3. M1 — AADHAAR OTP ENROLLMENT
          Step 1: POST /enrollment/request/otp
            scope=["abha-enrol"], loginHint=aadhaar, loginId=RSA(aadhaar)
            txnId="" on first call, pass previous txnId for resend
            → { txnId, message }
          Step 2: POST /enrollment/enrol/byAadhaar
            scope=["abha-enrol"], txnId, authData.otp.otpValue=RSA(otp)
            → { txnId, ABHAProfile, tokens, isNew }
    ═══════════════════════════════════════════════════════════════ */

    /**
     * M1 Step 1 — Send OTP to Aadhaar-linked mobile.
     * Pass $txnId on resend (from previous call); empty string on first call.
     */
    public function generateAadhaarOtp(string $aadhaar, string $txnId = ''): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/request/otp', [
            'txnId'     => $txnId,
            'scope'     => ['abha-enrol'],
            'loginHint' => 'aadhaar',
            'loginId'   => $this->rsaEncrypt($aadhaar),
            'otpSystem' => 'aadhaar',
        ], $token);
    }

    /**
     * M1 Step 2 — Verify Aadhaar OTP and create ABHA.
     * Response contains ABHAProfile + tokens.token (the X-token for profile calls).
     * $mobile : RSA-encrypted 10-digit mobile (only needed if Aadhaar has no linked mobile).
     */
    public function enrolByAadhaar(string $otp, string $txnId, string $mobile = ''): array
    {
        $token = $this->getAccessToken();

        $otpBlock = [
            'txnId'    => $txnId,
            'otpValue' => $this->rsaEncrypt($otp),
        ];
        if ($mobile) {
            $otpBlock['mobile'] = $this->rsaEncrypt($mobile);
        }

        // Spec: no top-level txnId or scope — txnId lives inside authData.otp
        return $this->post('/enrollment/enrol/byAadhaar', [
            'authData' => [
                'authMethods' => ['otp'],
                'otp'         => $otpBlock,
            ],
            'consent' => [
                'code'    => 'abha-enrollment',
                'version' => '1.4',
            ],
        ], $token);
    }

    /* ═══════════════════════════════════════════════════════════════
       4. MOBILE VERIFICATION DURING ENROLLMENT
          Used when comm. mobile ≠ Aadhaar-linked mobile (post enrolByAadhaar).
          Step 1: POST /enrollment/request/otp  scope=["mobile-verify"]
          Step 2: POST /enrollment/auth/byAbdm  scope=["mobile-verify"]
    ═══════════════════════════════════════════════════════════════ */

    /**
     * Generate OTP for communication mobile verification during enrollment.
     */
    public function generateMobileOtpForEnrollment(string $mobile, string $txnId): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/request/otp', [
            'txnId'     => $txnId,
            'scope'     => ['mobile-verify'],
            'loginHint' => 'mobile',
            'loginId'   => $this->rsaEncrypt($mobile),
            'otpSystem' => 'abdm',
        ], $token);
    }

    /**
     * Verify communication mobile OTP during enrollment.
     */
    public function verifyMobileForEnrollment(string $otp, string $txnId): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/auth/byAbdm', [
            'txnId'    => $txnId,
            'scope'    => ['mobile-verify'],
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId'    => $txnId,
                    'otpValue' => $this->rsaEncrypt($otp),
                ],
            ],
        ], $token);
    }

    /** Backward-compat alias */
    public function generateMobileOtp(string $mobile, string $txnId = ''): array
    {
        return $this->generateMobileOtpForEnrollment($mobile, $txnId);
    }

    /** Backward-compat alias */
    public function verifyMobileOtpM2(string $otp, string $txnId): array
    {
        return $this->verifyMobileForEnrollment($otp, $txnId);
    }

    /* ═══════════════════════════════════════════════════════════════
       5. M3 — DRIVING LICENCE ENROLLMENT  (v3 updated flow)
          Step 1: POST /enrollment/request/otp  scope=["dl-flow"], loginHint=mobile
          Step 2: POST /enrollment/auth/byAbdm  scope=["dl-flow"]
          Step 3: POST /enrollment/enrol/byDocument
    ═══════════════════════════════════════════════════════════════ */

    /**
     * M3 Step 1 — Send mobile OTP to start DL enrollment.
     * $mobile : 10-digit mobile number.
     */
    public function generateDlOtp(string $mobile, string $txnId = ''): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/request/otp', [
            'txnId'     => $txnId,
            'scope'     => ['dl-flow'],
            'loginHint' => 'mobile',
            'loginId'   => $this->rsaEncrypt($mobile),
            'otpSystem' => 'abdm',
        ], $token);
    }

    /**
     * M3 Step 2 — Verify mobile OTP for DL enrollment.
     */
    public function verifyDlOtp(string $otp, string $txnId): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/auth/byAbdm', [
            'txnId'    => $txnId,
            'scope'    => ['dl-flow'],
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId'    => $txnId,
                    'otpValue' => $this->rsaEncrypt($otp),
                ],
            ],
        ], $token);
    }

    /**
     * M3 Step 3 — Enrol ABHA via Driving Licence document.
     * $dlData keys: documentId, firstName, lastName, dob (DD-MM-YYYY),
     *               gender (M/F), address, state, district, pinCode,
     *               frontSidePhoto (base64), backSidePhoto (base64)
     */
    public function createAbhaDl(string $txnId, array $dlData): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/enrol/byDocument', [
            'txnId'    => $txnId,
            'scope'    => ['dl-flow'],
            'authData' => [
                'authMethods' => ['dl'],
                'document'    => array_merge(['documentType' => 'DRIVING_LICENSE'], $dlData),
            ],
            'consent' => ['code' => 'abha-enrollment', 'version' => '1.4'],
        ], $token);
    }

    /** Kept for backward compat; deprecated — use createAbhaDl(txnId, dlData) */
    public function createAbhaMobile(string $txnId, array $profile): array
    {
        $token = $this->getAccessToken();
        return $this->post('/enrollment/enrol/byDocument', array_merge([
            'txnId'   => $txnId,
            'scope'   => ['dl-flow'],
            'consent' => ['code' => 'abha-enrollment', 'version' => '1.4'],
        ], $profile), $token);
    }

    /* ═══════════════════════════════════════════════════════════════
       6. AUTH / LOGIN  (existing ABHA holder)

       IMPORTANT: /profile/login/request/otp rejects scope/otpSystem values
       when credentials are M1-only (sandbox). ABDM v3 routes ALL OTP flows
       through the enrollment endpoint with different scopes:

          Step 1: POST /enrollment/request/otp
            txnId="", scope=["abha-login"], loginHint="abha-number",
            loginId=RSA(14-digit-abha), otpSystem="abdm"
            → { txnId, message }

          Step 2: POST /enrollment/auth/byAbdm
            txnId, scope=["abha-login"],
            authData.authMethods=["otp"], authData.otp.otpValue=RSA(otp)
            → { txnId, token } — token is the user X-token for profile calls

       NOTE: If credentials are upgraded to full ABDM production, switch
       Step 1 back to /profile/login/request/otp and Step 2 to /profile/login/verify.
    ═══════════════════════════════════════════════════════════════ */

    /**
     * Step 1 — Request OTP to verify / link an existing ABHA.
     *
     * Endpoint routing:
     *   scope=["abha-login"]  → POST /profile/login/request/otp  (production M5+; also tried in sandbox)
     *   scope=["abha-enrol"]  → POST /enrollment/request/otp     (M1 sandbox enrollment only)
     *
     * $loginHint : 'abha-number' | 'mobile' | 'aadhaar'
     * $otpSystem : 'abdm' (OTP to registered mobile) | 'aadhaar'
     * $scopes    : ['abha-login'] for linking an existing ABHA
     */
    public function initAuth(string $loginId, string $loginHint = 'abha-number', string $otpSystem = 'abdm', array $scopes = ['abha-login']): array
    {
        $token = $this->getAccessToken();

        // Build the request body
        $body = [
            'txnId' => '',
            'scope' => $scopes,
            'loginHint' => $loginHint,
            'loginId' => $this->rsaEncrypt($loginId),
            'otpSystem' => $otpSystem,
        ];

        // For mobile login, the scope should be ['abha-login', 'mobile-verify']
        if ($loginHint === 'mobile') {
            $body['scope'] = ['abha-login', 'mobile-verify'];
            $body['otpSystem'] = 'abdm';
        }

        // For Aadhaar login
        if ($loginHint === 'aadhaar') {
            $body['scope'] = ['abha-login', 'aadhaar-verify'];
            $body['otpSystem'] = 'aadhaar';
        }

        error_log("ABDM initAuth Request: " . json_encode([
            'url' => $this->base . '/enrollment/request/otp',
            'body' => $body
        ]));

        return $this->post('/enrollment/request/otp', $body, $token);
    }

    /**
     * Step 2 — Verify OTP for existing ABHA holder.
     *
     * Endpoint routing:
     *   scope=["abha-login"] → POST /profile/login/verify  (production M5+)
     *   other scopes         → POST /enrollment/auth/byAbdm (M1 sandbox)
     */
    public function confirmAuth(string $otp, string $txnId, array $scopes = ['abha-login']): array
    {
        $token = $this->getAccessToken();

        if (in_array('abha-login', $scopes, true)) {
            return $this->post('/profile/login/verify', [
                'scope'    => $scopes,
                'authData' => [
                    'authMethods' => ['otp'],
                    'otp' => [
                        'txnId'    => $txnId,
                        'otpValue' => $this->rsaEncrypt($otp),
                    ],
                ],
            ], $token);
        }

        // Enrollment verify
        return $this->post('/enrollment/auth/byAbdm', [
            'txnId'    => $txnId,
            'scope'    => $scopes,
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId'    => $txnId,
                    'otpValue' => $this->rsaEncrypt($otp),
                ],
            ],
        ], $token);
    }

    /**
     * Step 3 (mobile login) — Select ABHA when multiple ABHAs are linked to one mobile.
     * POST /profile/login/verify/user
     * $tToken : T-token (jwtToken) returned by /profile/login/verify (Step 2)
     * Returns { token } — X-token for getProfile calls
     */
    public function verifyUserLogin(string $txnId, string $abhaNumber, string $tToken = ''): array
    {
        $token = $this->getAccessToken();
        $extraHeaders = $tToken ? ['T-Token' => 'Bearer ' . $tToken] : [];

        // Use correct endpoint for sandbox
        return $this->rawPost(
            $this->base . '/enrollment/auth/byAbdm',
            [
                'txnId' => $txnId,
                'scope' => ['abha-login', 'mobile-verify'],
                'ABHANumber' => $abhaNumber,
                'authData' => [
                    'authMethods' => ['abha-number'],
                    'abhaNumber' => $abhaNumber
                ]
            ],
            $token,
            true,
            $extraHeaders
        );
    }

    /* ── Backward-compat aliases ── */
    public function confirmWithMobileOtp(string $otp, string $txnId): array
    {
        return $this->confirmAuth($otp, $txnId);
    }
    public function confirmWithAadhaarOtp(string $otp, string $txnId): array
    {
        return $this->confirmAuth($otp, $txnId, ['abha-login', 'aadhaar-verify']);
    }

    /* ═══════════════════════════════════════════════════════════════
       7. PROFILE
          GET /profile/account          → full ABHA profile (needs T-Token)
          GET /profile/account/abha-card → PNG/PDF card image (needs T-Token)
          PUT /profile/account/update    → update profile fields
          DELETE /profile/account/delete → deactivate ABHA
    ═══════════════════════════════════════════════════════════════ */

    /**
     * Get full ABHA profile.
     * $xToken : user token returned from confirmAuth / enrolByAadhaar.
     */
    public function getProfile(string $xToken): array
    {
        $gToken = $this->getAccessToken();
        return $this->rawGet(
            $this->base . '/profile/account',
            $gToken,
            ['X-token' => 'Bearer ' . $xToken]
        );
    }

    /**
     * Download ABHA card as PNG bytes.
     * v3: POST /profile/account/getAbhaCard  (changed from GET in v2)
     * Returns raw binary string (PNG image).
     */
    public function getAbhaCard(string $xToken): string
    {
        $gToken = $this->getAccessToken();
        return $this->rawPostBinary(
            $this->base . '/profile/account/getAbhaCard',
            [],
            $gToken,
            ['X-token' => 'Bearer ' . $xToken, 'Accept' => 'image/png']
        );
    }

    /**
     * Download ABHA card as PDF bytes.
     * v3: POST /profile/account/getAbhaCard  (changed from GET in v2)
     */
    public function getAbhaCardPdf(string $xToken): string
    {
        $gToken = $this->getAccessToken();
        return $this->rawPostBinary(
            $this->base . '/profile/account/getAbhaCard',
            [],
            $gToken,
            ['X-token' => 'Bearer ' . $xToken, 'Accept' => 'application/pdf']
        );
    }

    /**
     * Update ABHA profile (name, email, address, photo).
     * $xToken : user token.
     * $fields : associative array with any updatable fields.
     */
    public function updateProfile(string $xToken, array $fields): array
    {
        $gToken = $this->getAccessToken();
        return $this->rawPut(
            $this->base . '/profile/account/update',
            $fields,
            $gToken,
            ['X-token' => 'Bearer ' . $xToken]
        );
    }

    /**
     * Deactivate (soft-delete) ABHA account.
     * $xToken : user token.
     * $reason : reason for deletion (optional string).
     */
    public function deleteAccount(string $xToken, string $reason = ''): array
    {
        $gToken = $this->getAccessToken();
        return $this->rawDelete(
            $this->base . '/profile/account/delete',
            $reason ? ['reason' => $reason] : [],
            $gToken,
            ['X-token' => 'Bearer ' . $xToken]
        );
    }

    /* ═══════════════════════════════════════════════════════════════
       8. ABHA ADDRESS — ENROLLMENT TIME
          GET  /enrollment/enrol/suggestion?txnId=  → suggested addresses (needs X-token)
          POST /enrollment/enrol/abha-address       → confirm chosen address (needs X-token)
    ═══════════════════════════════════════════════════════════════ */

    /**
     * Get ABHA address suggestions during enrollment (CRT_ABHA_112).
     * Call right after enrolByAadhaar returns tokens.token.
     * $txnId  : transaction ID from enrolByAadhaar response.
     * $xToken : tokens.token from enrolByAadhaar response.
     */
    public function getEnrollmentAbhaAddressSuggestions(string $txnId, string $xToken = ''): array
    {
        $gToken = $this->getAccessToken();
        // Spec: txnId goes in Transaction_Id header, not as a query param
        return $this->rawGet(
            $this->base . '/enrollment/enrol/suggestion',
            $gToken,
            ['Transaction_Id' => $txnId]
        );
    }

    /**
     * Confirm chosen ABHA address during enrollment (CRT_ABHA_112).
     * $txnId        : transaction ID from enrolByAadhaar response.
     * $abhaAddress  : chosen address WITHOUT @abdm suffix (e.g. "john.doe")
     * $xToken       : tokens.token from enrolByAadhaar response.
     */
    public function setEnrollmentAbhaAddress(string $txnId, string $abhaAddress, string $xToken = ''): array
    {
        $gToken = $this->getAccessToken();
        // Spec body: { txnId, abhaAddress, preferred: 1 }
        // Keep the @sbx / @abdm suffix as-is if already present
        $addr = trim($abhaAddress);
        return $this->rawPost(
            $this->base . '/enrollment/enrol/abha-address',
            ['txnId' => $txnId, 'abhaAddress' => $addr, 'preferred' => 1],
            $gToken,
            true
        );
    }

    /* ═══════════════════════════════════════════════════════════════
       8b. ABHA ADDRESS — POST-ENROLLMENT (PROFILE)
          GET /profile/account/abha-address/suggestions → suggested addresses
          PUT /profile/account/abha-address             → create / update address
    ═══════════════════════════════════════════════════════════════ */

    /**
     * Get suggested ABHA addresses for the authenticated user.
     * $xToken : user token.
     */
    public function getAbhaAddressSuggestions(string $xToken): array
    {
        $gToken = $this->getAccessToken();
        return $this->rawGet(
            $this->base . '/profile/account/abha-address/suggestions',
            $gToken,
            ['X-token' => 'Bearer ' . $xToken]
        );
    }

    /**
     * Create or update the user's preferred ABHA address.
     * $xToken      : user token.
     * $abhaAddress : full address e.g. "john.doe@abdm"
     */
    public function updateAbhaAddress(string $xToken, string $abhaAddress): array
    {
        $gToken = $this->getAccessToken();
        return $this->rawPut(
            $this->base . '/profile/account/abha-address',
            ['preferredAbhaAddress' => $this->normaliseAbhaAddr($abhaAddress)],
            $gToken,
            ['X-token' => 'Bearer ' . $xToken]
        );
    }

    /* ═══════════════════════════════════════════════════════════════
       9. SEARCH
          POST /search/searchByAbhaNumber  → search by 14-digit ABHA number
          POST /search/searchByAbhaAddress → search by ABHA address (@abdm)
    ═══════════════════════════════════════════════════════════════ */

    public function searchByHealthId(string $abhaNumber): array
    {
        $token = $this->getAccessToken();
        return $this->post('/search/searchByAbhaNumber', [
            'abhaNumber' => self::formatAbhaNumber($abhaNumber),
        ], $token);
    }

    public function searchByAbhaAddress(string $abhaAddress): array
    {
        $token = $this->getAccessToken();
        return $this->post('/search/searchByAbhaAddress', [
            'abhaAddress' => $this->normaliseAbhaAddr($abhaAddress),
        ], $token);
    }

    /* ═══════════════════════════════════════════════════════════════
       10. LINKED FACILITIES (Health Locker / HIU)
           GET  /profile/account/linked-facility  → list linked facilities
           POST /profile/account/linked-facility  → link a facility (HIU)
    ═══════════════════════════════════════════════════════════════ */

    public function getLinkedFacilities(string $xToken): array
    {
        $gToken = $this->getAccessToken();
        return $this->rawGet(
            $this->base . '/profile/account/linked-facility',
            $gToken,
            ['X-token' => 'Bearer ' . $xToken]
        );
    }

    /* ═══════════════════════════════════════════════════════════════
       11. STATIC HELPERS
    ═══════════════════════════════════════════════════════════════ */

    /** Format raw 14 digits → XX-XXXX-XXXX-XXXX. */
    public static function formatAbhaNumber(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) !== 14) return $raw;
        return substr($digits, 0, 2) . '-'
            . substr($digits, 2, 4) . '-'
            . substr($digits, 6, 4) . '-'
            . substr($digits, 10, 4);
    }

    /**
     * Extract best user-readable error from ABDM response.
     * ABDM v3 error structure: { details:[{message,code,...}], message, code }
     * Falls back to HTTP status hints, then raw body snippet.
     */
    public static function extractError(array $res, string $fallback = 'ABDM API error'): string
    {
        // ABDM wraps errors in various structures — try each shape in order
        $msg = null;
        if (is_string($res['details'][0]['message'] ?? null))   $msg = $res['details'][0]['message'];
        elseif (is_string($res['details'][0]['attribute'] ?? null)) $msg = $res['details'][0]['attribute'];
        elseif (is_string($res['message'] ?? null))             $msg = $res['message'];
        elseif (is_string($res['error']['message'] ?? null))    $msg = $res['error']['message'];  // {"error":{"code":"...","message":"..."}}
        elseif (is_string($res['error']['code'] ?? null))       $msg = $res['error']['code'];
        elseif (is_string($res['errors'][0]['message'] ?? null)) $msg = $res['errors'][0]['message'];
        elseif (is_string($res['code'] ?? null))                $msg = $res['code'];

        if ($msg) return $msg;

        // Interpret HTTP status if JSON body was empty / unparseable
        $http = (int)($res['_http'] ?? 0);
        if ($http === 404) return 'ABHA not found in ABDM records. Please check the number.';
        if ($http === 422) return 'ABDM rejected the request — check input format or values.';
        if ($http === 400) return 'ABDM returned Bad Request. Check your input details.';
        if ($http === 401) return 'ABDM auth failed — gateway token may have expired. Retry.';
        if ($http === 429) return 'Too many requests to ABDM. Please wait a few minutes.';
        if ($http >= 500)  return 'ABDM server error. Please try again later.';

        // Last resort: show raw snippet (never contains Aadhaar/OTP — only error payloads)
        if (!empty($res['_raw'])) {
            $raw = substr(strip_tags((string)$res['_raw']), 0, 120);
            return $fallback . ' [' . $raw . ']';
        }

        return $fallback;
    }

    /** Extract HTTP status code stored in last rawPost/rawGet call (if available). */
    public static function wasSuccessful(array $res, int $expected = 200): bool
    {
        $http = $res['_http'] ?? 200;
        return $http >= 200 && $http < 300;
    }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE TRANSPORT LAYER
    ═══════════════════════════════════════════════════════════════ */

    /** POST to ABHA v3 endpoint (path relative to base URL). */
    private function post(string $path, array $data, string $bearer): array
    {
        return $this->rawPost($this->base . $path, $data, $bearer, true);
    }

    /** Full-URL POST — returns decoded JSON array. */
    private function rawPost(
        string $url,
        array $data,
        string $bearer = '',
        bool $v3Headers = true,
        array $extraHeaders = []
    ): array {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($bearer) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }

        if ($v3Headers) {
            $headers[] = 'REQUEST-ID: ' . $this->uuid();
            $headers[] = 'TIMESTAMP: ' . $this->timestamp();
            $headers[] = 'X-CM-ID: ' . $this->xCmId;
        }

        foreach ($extraHeaders as $k => $v) {
            $headers[] = "$k: $v";
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_POSTFIELDS => json_encode($data),
        ];

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $errMsg = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException("cURL [{$errno}] {$errMsg} → {$url}");
        }

        // Log the raw response for debugging
        error_log("ABDM Raw Response for {$url}: " . substr($response, 0, 500));

        // Parse JSON response
        $decoded = json_decode($response, true);

        // Check if JSON parsing failed
        if ($decoded === null && !empty($response)) {
            error_log("ABDM JSON Parse Error: " . json_last_error_msg());
            error_log("ABDM Raw Response: " . substr($response, 0, 1000));
            return ['_raw' => substr($response, 0, 500), '_http' => $http];
        }

        // Return the parsed response
        if (is_array($decoded)) {
            $decoded['_http'] = $http;
            return $decoded;
        }

        // If response is empty or not JSON
        return ['_raw' => $response, '_http' => $http];
    }

    /** Full-URL GET — returns decoded JSON array. */
    private function rawGet(string $url, string $bearer = '', array $extra = []): array
    {
        [$body, $http] = $this->curlExec('GET', $url, null, $bearer, $extra);
        $decoded = json_decode($body, true);
        if ($decoded === null) {
            return ['_raw' => substr($body, 0, 500), '_http' => $http];
        }
        $decoded['_http'] = $http;
        return $decoded;
    }

    /** Full-URL GET — returns raw binary (for ABHA card image/PDF). */
    private function rawGetBinary(string $url, string $bearer = '', array $extra = []): string
    {
        [$body] = $this->curlExec('GET', $url, null, $bearer, $extra);
        return $body;
    }

    /** Full-URL POST — returns raw binary (for ABHA card image/PDF via v3 POST endpoint). */
    private function rawPostBinary(string $url, array $data, string $bearer = '', array $extra = []): string
    {
        [$body] = $this->curlExec('POST', $url, json_encode($data), $bearer, $extra);
        return $body;
    }

    /** Full-URL PUT — returns decoded JSON array. */
    private function rawPut(string $url, array $data, string $bearer = '', array $extra = []): array
    {
        [$body, $http] = $this->curlExec('PUT', $url, json_encode($data), $bearer, $extra);
        $decoded = json_decode($body, true);
        if ($decoded === null) {
            return ['_raw' => substr($body, 0, 500), '_http' => $http];
        }
        $decoded['_http'] = $http;
        return $decoded;
    }

    /** Full-URL DELETE — returns decoded JSON array. */
    private function rawDelete(string $url, array $data, string $bearer = '', array $extra = []): array
    {
        [$body, $http] = $this->curlExec('DELETE', $url, $data ? json_encode($data) : null, $bearer, $extra);
        $decoded = json_decode($body, true);
        if ($decoded === null) {
            return ['_raw' => substr($body, 0, 500), '_http' => $http];
        }
        $decoded['_http'] = $http;
        return $decoded;
    }

    /**
     * Core cURL executor.
     * Returns [$responseBody, $httpCode].
     */
    private function curlExec(
        string  $method,
        string  $url,
        ?string $body,
        string  $bearer     = '',
        array   $extraHeaders = [],
        bool    $v3Headers  = true
    ): array {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($bearer) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        if ($v3Headers) {
            $headers[] = 'REQUEST-ID: ' . $this->uuid();
            $headers[] = 'TIMESTAMP: '  . $this->timestamp();
            $headers[] = 'X-CM-ID: '    . $this->xCmId;
        }
        foreach ($extraHeaders as $k => $v) {
            $headers[] = "$k: $v";
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        } elseif ($method === 'GET') {
            $opts[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errMsg   = curl_error($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException("cURL [{$errno}] {$errMsg} → {$url}");
        }

        return [$response, $http];
    }

    /* ── Private utilities ── */

    /** Wrap base64 key string in PEM markers. */
    private function toPem(string $keyData): string
    {
        if (strpos($keyData, '-----BEGIN') !== false) return $keyData;
        $clean = preg_replace('/\s+/', '', $keyData);
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($clean, 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Ensure ABHA address ends with @abdm (not doubled).
     * "john" → "john@abdm"   "john@abdm" → "john@abdm"
     */
    private function normaliseAbhaAddr(string $addr): string
    {
        $addr = trim($addr);
        return strpos($addr, '@') !== false ? $addr : $addr . '@abdm';
    }

    /**
     * Generate UUID v4 using cryptographically secure random bytes.
     * ABDM security spec requires crypto-random REQUEST-IDs.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** ISO-8601 UTC timestamp with milliseconds. */
    private function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }
}
