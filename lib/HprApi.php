<?php

/**
 * HprApi — ABDM HPR (Health Professional Registry) v4 client.
 *
 * SCOPE: verify a doctor's *existing* HPR ID through the Aadhaar flow
 *   generateAadhaarLink → (doctor authenticates on the ABDM page) →
 *   checkAadhaarAuthStatus → verifyOTP → checkHpIdAccountExist.
 * Creating a NEW HPR ID is deliberately out of scope.
 *
 * Follows lib/AbdmApi.php conventions:
 *   - gateway access token cached in $_SESSION (keyed 'hpr_*', separate from ABHA)
 *   - every request carries  Authorization: Bearer <token> (when available),
 *     REQUEST-ID: <UUID v4>, TIMESTAMP: <ISO-8601 .SSS Z>, X-CM-ID: sbx|abdm
 *   - cURL transport with ABDM_SSL_VERIFY
 *   - callers get a structured  ['success'=>bool, 'data'=>mixed, 'error'=>?string]
 *
 * LOGGING: PII-safe only. HPR demographic / verifyOTP responses carry the
 * doctor's name, DOB, address and photo — this class logs status + HTTP code
 * + txnId + a short message, never the response body.
 *
 * Endpoints:
 *   Gateway session : POST  {ABDM_HPR_GATEWAY_URL}/v3/sessions
 *   HPR service     : {ABDM_HPR_BASE_URL}  (sandbox: https://apihspsbx.abdm.gov.in/v4/int)
 *
 * Config: config/abdm.php  (ABDM_HPR_* constants, from .env).
 */
class HprApi
{
    private string $gatewayBase;   // e.g. https://dev.abdm.gov.in/api/hiecm/gateway  (NO /v3/sessions)
    private string $hprBase;       // e.g. https://apihspsbx.abdm.gov.in/v4/int
    private string $clientId;
    private string $clientSecret;
    private string $xCmId;
    private bool   $sslVerify;

    public function __construct()
    {
        $this->gatewayBase  = rtrim(defined('ABDM_HPR_GATEWAY_URL') ? ABDM_HPR_GATEWAY_URL : '', '/');
        $this->hprBase      = rtrim(defined('ABDM_HPR_BASE_URL') ? ABDM_HPR_BASE_URL : '', '/');
        $this->clientId     = defined('ABDM_HPR_CLIENT_ID') ? ABDM_HPR_CLIENT_ID : '';
        $this->clientSecret = defined('ABDM_HPR_CLIENT_SECRET') ? ABDM_HPR_CLIENT_SECRET : '';
        $this->xCmId        = defined('ABDM_X_CM_ID') ? ABDM_X_CM_ID : 'sbx';
        $this->sslVerify    = defined('ABDM_SSL_VERIFY') ? (bool) ABDM_SSL_VERIFY : true;
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /* ═══════════════════════════════════════════════════════════════
       1. GATEWAY SESSION TOKEN
          POST {gateway}/v3/sessions
          Body:     { clientId, clientSecret, grantType: "client_credentials" }
          Response: { accessToken, tokenType, expiresIn, refreshToken }
          Cached in $_SESSION['hpr_token'] until (expiresIn - 300)s.
    ═══════════════════════════════════════════════════════════════ */

    public function generateSession(): array
    {
        // Serve a still-valid cached token without a network round trip.
        if (
            !empty($_SESSION['hpr_token'])
            && !empty($_SESSION['hpr_token_exp'])
            && time() < (int) $_SESSION['hpr_token_exp']
        ) {
            return $this->ok([
                'accessToken' => $_SESSION['hpr_token'],
                'cached'      => true,
                'expiresAt'   => (int) $_SESSION['hpr_token_exp'],
            ]);
        }

        if (!$this->isConfigured()) {
            return $this->fail('HPR API is not configured (set ABDM_HPR_CLIENT_ID / ABDM_HPR_CLIENT_SECRET in .env).');
        }

        $r = $this->http('POST', $this->gatewayBase . '/v3/sessions', [
            'clientId'     => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'grantType'    => 'client_credentials',
        ], null);

        $token = '';
        if (is_array($r['_json'])) {
            $token = (string) ($r['_json']['accessToken'] ?? $r['_json']['access_token'] ?? '');
        }

        if ($token === '' || $r['_http'] < 200 || $r['_http'] >= 300) {
            $this->logSafe('session failed', ['http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not obtain an HPR gateway session token.'));
        }

        $expiresIn = (int) ($r['_json']['expiresIn'] ?? $r['_json']['expires_in'] ?? 1200);
        $ttl       = max(300, $expiresIn - 300);

        $_SESSION['hpr_token']     = $token;
        $_SESSION['hpr_token_exp'] = time() + $ttl;

        $this->logSafe('session ok', ['ttl' => $ttl]);

        return $this->ok([
            'accessToken' => $token,
            'cached'      => false,
            'expiresAt'   => time() + $ttl,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════
       2. PUBLIC CERTIFICATE   (future-proofing — NOT used by the verify flow)
          GET {hprBase}/api/v1/auth/cert   (Bearer token)
          Response: PEM public key, either as raw text or { publicKey: "..." }.
          Cached in $_SESSION['hpr_cert'] for 1 hour.

          Kept so a later flow that must RSA-encrypt a value before sending it
          to HPR has the key ready. The existing-HPR-ID verification path
          (methods 3-6) sends nothing that needs encryption.
    ═══════════════════════════════════════════════════════════════ */

    public function getPublicCertificate(): array
    {
        if (
            !empty($_SESSION['hpr_cert'])
            && !empty($_SESSION['hpr_cert_exp'])
            && time() < (int) $_SESSION['hpr_cert_exp']
        ) {
            return $this->ok(['pem' => $_SESSION['hpr_cert'], 'cached' => true]);
        }

        [$token, $err] = $this->token();
        if ($err !== null) {
            return $this->fail($err);
        }

        $r = $this->http('GET', $this->hprBase . '/api/v1/auth/cert', null, $token);

        if ($r['_http'] < 200 || $r['_http'] >= 300) {
            $this->logSafe('cert failed', ['http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not fetch the HPR public certificate.'));
        }

        // Either a JSON envelope or the raw PEM/base64 body.
        $keyData = '';
        if (is_array($r['_json'])) {
            $keyData = (string) ($r['_json']['publicKey'] ?? $r['_json']['certificate'] ?? '');
        }
        if ($keyData === '') {
            $keyData = trim((string) $r['_raw']);
        }
        if ($keyData === '') {
            return $this->fail('HPR returned an empty certificate.');
        }

        $pem = $this->toPem($keyData);
        if (!openssl_pkey_get_public($pem)) {
            $this->logSafe('cert invalid', ['http' => $r['_http']]);
            return $this->fail('HPR public certificate is not a valid RSA key.');
        }

        $_SESSION['hpr_cert']     = $pem;
        $_SESSION['hpr_cert_exp'] = time() + 3600;
        $this->logSafe('cert ok', []);

        return $this->ok(['pem' => $pem, 'cached' => false]);
    }

    /**
     * RSA-OAEP(SHA-1) encrypt with the HPR public key → base64.
     * Provided for future flows; unused by the verification methods.
     */
    public function rsaEncrypt(string $plaintext): array
    {
        $cert = $this->getPublicCertificate();
        if (!$cert['success']) {
            return $cert;
        }
        $key = openssl_pkey_get_public($cert['data']['pem']);
        $out = '';
        if (!$key || !openssl_public_encrypt($plaintext, $out, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            return $this->fail('HPR RSA encryption failed.');
        }
        return $this->ok(base64_encode($out));
    }

    /* ═══════════════════════════════════════════════════════════════
       3. GENERATE AADHAAR LINK
          POST {hprBase}/aadhaar/generateLink   (Bearer token)
          Body:     { "scopes": ["nhpr-register"], "source": "NHPR" }
          Response: { txnId, url }   — the url is valid for ONLY 5 minutes.

          The caller must persist the txn (status 'pending', expires_at =
          now + 300s) in `hpr_verification_txns` — see
          database/migration_hpr_verification.sql — then open `url` in a new
          tab and poll checkAadhaarAuthStatus($txnId).
    ═══════════════════════════════════════════════════════════════ */

    public function generateAadhaarLink(): array
    {
        [$token, $err] = $this->token();
        if ($err !== null) {
            return $this->fail($err);
        }

        $r = $this->http('POST', $this->hprBase . '/aadhaar/generateLink', [
            'scopes' => ['nhpr-register'],
            'source' => 'NHPR',
        ], $token);

        if ($r['_http'] < 200 || $r['_http'] >= 300) {
            $this->logSafe('generateLink failed', ['http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not start HPR Aadhaar verification.'));
        }

        $j     = is_array($r['_json']) ? $r['_json'] : [];
        $txnId = (string) ($j['txnId'] ?? $j['transactionId'] ?? '');
        $url   = (string) ($j['url'] ?? $j['link'] ?? '');

        if ($txnId === '' || $url === '') {
            $this->logSafe('generateLink incomplete', ['http' => $r['_http'], 'txnId' => $txnId]);
            return $this->fail('HPR did not return a verification link. Please try again.');
        }

        $this->logSafe('generateLink ok', ['txnId' => $txnId]);

        return $this->ok([
            'txnId'     => $txnId,
            'url'       => $url,
            'expiresIn' => 300,                 // seconds — link is valid 5 minutes
            'expiresAt' => time() + 300,        // unix ts, for the hpr_verification_txns row
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════
       4. CHECK AADHAAR AUTH STATUS   (poll while the doctor authenticates)
          POST {hprBase}/aadhaar/isAuthenticated   (Bearer token)
          Body:     { "txnId": "..." }
          Response: a RAW JSON boolean — literally `true` or `false`, NOT an
                    object. json_decode() gives a PHP bool; read it directly.

          Poll this every 3-4s (same pattern as telemedicine/room.js) after
          opening the generateAadhaarLink() url. Stop when authenticated=true,
          or when the hpr_verification_txns row's expires_at has passed
          (mark it 'expired').
    ═══════════════════════════════════════════════════════════════ */

    public function checkAadhaarAuthStatus(string $txnId): array
    {
        $txnId = trim($txnId);
        if ($txnId === '') {
            return $this->fail('Transaction id is required.');
        }

        [$token, $err] = $this->token();
        if ($err !== null) {
            return $this->fail($err);
        }

        $r = $this->http('POST', $this->hprBase . '/aadhaar/isAuthenticated', ['txnId' => $txnId], $token);

        if ($r['_http'] < 200 || $r['_http'] >= 300) {
            $this->logSafe('isAuthenticated failed', ['http' => $r['_http'], 'txnId' => $txnId, 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not check the HPR authentication status.'));
        }

        // The documented shape is a bare boolean. Handle that first, then a
        // couple of defensive fallbacks in case a build wraps it.
        $val = $r['_json'];
        if (is_bool($val)) {
            $authed = $val;
        } elseif (is_array($val)) {
            $authed = !empty($val['authenticated'])
                   || !empty($val['isAuthenticated'])
                   || (($val['status'] ?? '') === 'authenticated');
        } else {
            $authed = strtolower(trim((string) $r['_raw'])) === 'true';
        }

        $this->logSafe('isAuthenticated ok', ['txnId' => $txnId, 'status' => $authed ? 'authenticated' : 'pending']);

        return $this->ok(['authenticated' => $authed, 'txnId' => $txnId]);
    }

    /* ═══════════════════════════════════════════════════════════════
       5. VERIFY OTP  (completes the Aadhaar auth once status = authenticated)
          POST {hprBase}/v2/registration/aadhaar/verifyOTP   (Bearer token)
          Body:     { "txnId": "..." }
          Response: demographic data — name, dob, gender, address,
                    masked mobile, photo (base64).

          ⚠️ TRANSIENT. The caller shows this to the doctor as an
          "is this you?" confirmation and MUST NOT persist any of it
          (PII minimisation). Only `txnId` is carried forward to
          checkHpIdAccountExist().
    ═══════════════════════════════════════════════════════════════ */

    public function verifyOTP(string $txnId): array
    {
        $txnId = trim($txnId);
        if ($txnId === '') {
            return $this->fail('Transaction id is required.');
        }

        [$token, $err] = $this->token();
        if ($err !== null) {
            return $this->fail($err);
        }

        $r = $this->http('POST', $this->hprBase . '/v2/registration/aadhaar/verifyOTP', ['txnId' => $txnId], $token);

        if ($r['_http'] < 200 || $r['_http'] >= 300) {
            $this->logSafe('verifyOTP failed', ['http' => $r['_http'], 'txnId' => $txnId, 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'HPR Aadhaar OTP verification failed.'));
        }

        $j = is_array($r['_json']) ? $r['_json'] : [];

        $name = (string) ($j['name'] ?? '');
        if ($name === '') {
            $name = trim(preg_replace('/\s+/', ' ',
                ($j['firstName'] ?? '') . ' ' . ($j['middleName'] ?? '') . ' ' . ($j['lastName'] ?? '')));
        }

        // TRANSIENT — hand to the UI for confirmation, never to the DB.
        $demographics = [
            'name'         => $name,
            'gender'       => (string) ($j['gender'] ?? ''),
            'dob'          => (string) ($j['dob'] ?? $j['birthDate'] ?? ''),
            'address'      => (string) ($j['address'] ?? ''),
            'pincode'      => (string) ($j['pincode'] ?? $j['pinCode'] ?? ''),
            'maskedMobile' => (string) ($j['mobileNumber'] ?? $j['maskedMobile'] ?? $j['mobile'] ?? ''),
            'photo'        => (string) ($j['photo'] ?? $j['profilePhoto'] ?? ''),
        ];

        $nextTxn = (string) ($j['txnId'] ?? $txnId);   // some builds rotate the txnId here

        $this->logSafe('verifyOTP ok', ['txnId' => $nextTxn]);   // never the demographic fields

        return $this->ok([
            'txnId'        => $nextTxn,
            'demographics' => $demographics,   // TRANSIENT — do NOT persist
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════
       6. CHECK HP-ID ACCOUNT EXIST   ← PRIMARY VERIFICATION STEP
          POST {hprBase}/v1/registration/aadhaar/checkHpIdAccountExist   (Bearer)
          Body:     { "txnId": "..." }
          Response: { new: bool, hprIdNumber, firstName, lastName,
                      categoryId, subCategoryId, ... }

          new = true  →  this Aadhaar has NO HPR account. Return a clear
                         error (code 'hpr_account_not_found') — the doctor
                         must first register on the ABDM HPR portal.
          new = false →  return the HPR profile fields. The caller then
                         checks hprIdNumber matches the doctor's claimed
                         hpr_id and stamps doctors.hpr_verified* + logs it.
    ═══════════════════════════════════════════════════════════════ */

    public function checkHpIdAccountExist(string $txnId): array
    {
        $txnId = trim($txnId);
        if ($txnId === '') {
            return $this->fail('Transaction id is required.');
        }

        [$token, $err] = $this->token();
        if ($err !== null) {
            return $this->fail($err);
        }

        $r = $this->http(
            'POST',
            $this->hprBase . '/v1/registration/aadhaar/checkHpIdAccountExist',
            ['txnId' => $txnId],
            $token
        );

        if ($r['_http'] < 200 || $r['_http'] >= 300) {
            $this->logSafe('checkHpId failed', ['http' => $r['_http'], 'txnId' => $txnId, 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not check the HPR account.'));
        }

        $j = is_array($r['_json']) ? $r['_json'] : [];

        // "new": true → no HPR account for this Aadhaar.
        $isNew = filter_var($j['new'] ?? $j['isNew'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($isNew) {
            $this->logSafe('checkHpId ok', ['txnId' => $txnId, 'status' => 'no_hpr_account']);
            return $this->fail(
                'HPR account not found for this Aadhaar — please register on the ABDM HPR portal first.',
                'hpr_account_not_found'
            );
        }

        $hprId = (string) ($j['hprIdNumber'] ?? $j['hprId'] ?? $j['hpIdNumber'] ?? '');
        if ($hprId === '') {
            $this->logSafe('checkHpId incomplete', ['http' => $r['_http'], 'txnId' => $txnId]);
            return $this->fail('HPR responded without an HPR ID. Please try again.');
        }

        $this->logSafe('checkHpId ok', ['txnId' => $txnId, 'status' => 'hpr_found']);

        return $this->ok([
            'txnId'         => (string) ($j['txnId'] ?? $txnId),
            'hprIdNumber'   => $hprId,
            'firstName'     => (string) ($j['firstName'] ?? ''),
            'lastName'      => (string) ($j['lastName'] ?? ''),
            'categoryId'    => $j['categoryId'] ?? null,
            'subCategoryId' => $j['subCategoryId'] ?? null,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE — token accessor
    ═══════════════════════════════════════════════════════════════ */

    /** @return array{0:string,1:?string} [token, errorOrNull] */
    private function token(): array
    {
        $s = $this->generateSession();
        if (!$s['success']) {
            return ['', $s['error']];
        }
        return [(string) $s['data']['accessToken'], null];
    }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE — transport
    ═══════════════════════════════════════════════════════════════ */

    /**
     * One HTTP call. Always sends REQUEST-ID / TIMESTAMP / X-CM-ID; adds
     * Authorization when $bearer is non-null; merges $extraHeaders.
     *
     * @return array{_http:int,_json:mixed,_raw:string,_curlErr:?string}
     */
    private function http(string $method, string $url, ?array $body, ?string $bearer = null, array $extraHeaders = []): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'REQUEST-ID: ' . $this->uuid(),
            'TIMESTAMP: ' . $this->timestamp(),
            'X-CM-ID: ' . $this->xCmId,
        ];
        if ($bearer !== null && $bearer !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        foreach ($extraHeaders as $k => $v) {
            $headers[] = $k . ': ' . $v;
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
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        } elseif ($method === 'GET') {
            $opts[CURLOPT_HTTPGET] = true;
        }
        curl_setopt_array($ch, $opts);

        $raw     = curl_exec($ch);
        $errno   = curl_errno($ch);
        $errMsg  = curl_error($ch);
        $http    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['_http' => 0, '_json' => null, '_raw' => '', '_curlErr' => "[{$errno}] {$errMsg}"];
        }

        $raw  = is_string($raw) ? $raw : '';
        $json = json_decode($raw, true);
        // Note: json_decode of the literal `true` / `false` returns a PHP bool —
        // callers that expect a raw boolean (checkAadhaarAuthStatus) read _json directly.
        if ($json === null && trim($raw) !== '' && strtolower(trim($raw)) !== 'null') {
            // non-JSON body — keep the raw text for extractError()
            $json = null;
        }

        return ['_http' => $http, '_json' => $json, '_raw' => $raw, '_curlErr' => null];
    }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE — helpers
    ═══════════════════════════════════════════════════════════════ */

    /** @return array{success:true,data:mixed,error:null,code:null} */
    private function ok($data = null): array
    {
        return ['success' => true, 'data' => $data, 'error' => null, 'code' => null];
    }

    /**
     * @param ?string $code machine-readable reason (e.g. 'hpr_account_not_found')
     *        so a caller can branch without string-matching the message.
     * @return array{success:false,data:null,error:string,code:?string}
     */
    private function fail(string $message, ?string $code = null): array
    {
        return ['success' => false, 'data' => null, 'error' => $message, 'code' => $code];
    }

    /**
     * Best-effort human-readable error from an $this->http() result.
     * The HPR docs give no fixed error schema, so try the common ABDM shapes
     * then fall back to HTTP-status hints — same approach as AbdmApi::extractError().
     */
    private function extractError(array $r, string $fallback = 'HPR API error'): string
    {
        if (!empty($r['_curlErr'])) {
            return 'Network error contacting the HPR service. Please try again.';
        }

        $j = $r['_json'];
        if (is_array($j)) {
            foreach ([
                $j['details'][0]['message'] ?? null,
                $j['error']['message'] ?? null,
                $j['error']['code'] ?? null,
                $j['errors'][0]['message'] ?? null,
                $j['message'] ?? null,
                $j['error'] ?? null,
                $j['code'] ?? null,
            ] as $cand) {
                if (is_string($cand) && $cand !== '') {
                    return $cand;
                }
            }
            // ABDM field-error shape: {"txnId":"Invalid Transaction Id", ...}
            $fieldErrs = [];
            foreach ($j as $v) {
                if (is_string($v) && $v !== '' && stripos($v, 'invalid') !== false) {
                    $fieldErrs[] = $v;
                }
            }
            if ($fieldErrs) {
                return implode('; ', array_unique($fieldErrs));
            }
        }

        switch ((int) $r['_http']) {
            case 400: return 'HPR rejected the request. Please check your details and try again.';
            case 401:
            case 403: return 'HPR authorisation failed. Please retry in a moment.';
            case 404: return 'HPR record not found.';
            case 408:
            case 504: return 'The HPR service timed out. Please try again.';
            case 429: return 'Too many requests to HPR. Please wait a minute and try again.';
        }
        if ((int) $r['_http'] >= 500) {
            return 'The HPR service is temporarily unavailable. Please try again later.';
        }

        return $fallback;
    }

    /** PII-safe log line — whitelisted scalars only, never a response body. */
    private function logSafe(string $event, array $ctx = []): void
    {
        $safe = [];
        foreach (['http', 'status', 'txnId', 'message', 'ttl', 'curl'] as $k) {
            if (array_key_exists($k, $ctx) && (is_scalar($ctx[$k]) || $ctx[$k] === null)) {
                $safe[$k] = $ctx[$k];
            }
        }
        error_log('[HprApi] ' . $event . ($safe ? ' ' . json_encode($safe) : ''));
    }

    /** UUID v4 from cryptographically secure random bytes (ABDM REQUEST-ID). */
    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** ISO-8601 UTC with milliseconds, e.g. 2025-07-01T10:30:00.000Z */
    private function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }

    /** Wrap a bare base64 key in PEM markers; pass through if already PEM. */
    private function toPem(string $keyData): string
    {
        if (strpos($keyData, '-----BEGIN') !== false) {
            return $keyData;
        }
        $clean = preg_replace('/\s+/', '', $keyData);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($clean, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
