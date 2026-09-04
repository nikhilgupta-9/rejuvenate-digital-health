<?php

/**
 * HipApi — ABDM HIP-Initiated Linking (Milestone 3, HIECM V3, async).
 *
 * The HIP (this platform) links a patient's care contexts (visits /
 * prescriptions) to their ABHA so a CM/HIU can later request that data.
 * The V3 flow is asynchronous: each call returns a requestId and ABDM
 * delivers the real result to our webhook (telemedicine/api/abdm-webhook.php).
 *
 *   1. generateLinkToken()  → requestId; webhook returns the X-LINK-TOKEN
 *   2. linkCareContext()    → requestId; webhook returns linking-status
 *   3. notifyCareContext()  → requestId; fire-and-forget notification
 *
 * Follows lib/AbdmApi.php conventions:
 *   - gateway session token reused from AbdmApi (ABDM_CLIENT_ID/SECRET)
 *   - every request: Authorization: Bearer, REQUEST-ID (UUID v4),
 *     TIMESTAMP (ISO-8601 .SSS Z), X-CM-ID (sbx|abdm), X-HIP-ID
 *   - cURL transport with ABDM_SSL_VERIFY
 *   - returns ['success'=>bool, 'data'=>mixed, 'error'=>?string, 'code'=>?string]
 *
 * The REQUEST-ID a method sends is ALSO the id the webhook echoes back — it
 * is returned as data['requestId'] so the caller can persist it
 * (abdm_link_tokens / abdm_care_context_links) for matching.
 *
 * LOGGING: PII-safe — requestId + status + HTTP code only. Never patient
 * name / ABHA number / care-context bodies.
 *
 * Config: config/abdm.php (ABDM_HIECM_BASE_URL, ABDM_HIP_ID, ABDM_HIP_NAME).
 */
class HipApi
{
    private string $base;        // https://dev.abdm.gov.in/api/hiecm
    private string $xCmId;
    private string $hipId;
    private string $hipName;
    private bool   $sslVerify;
    private ?AbdmApi $abdm = null;

    public function __construct()
    {
        $this->base      = rtrim(defined('ABDM_HIECM_BASE_URL') ? ABDM_HIECM_BASE_URL : '', '/');
        $this->xCmId     = defined('ABDM_X_CM_ID') ? ABDM_X_CM_ID : 'sbx';
        $this->hipId     = defined('ABDM_HIP_ID') ? ABDM_HIP_ID : '';
        $this->hipName   = defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health';
        $this->sslVerify = defined('ABDM_SSL_VERIFY') ? (bool) ABDM_SSL_VERIFY : true;
    }

    public function isConfigured(): bool
    {
        return $this->hipId !== ''
            && defined('ABDM_CONFIGURED') && ABDM_CONFIGURED;
    }

    /* ═══════════════════════════════════════════════════════════════
       1. GENERATE LINK TOKEN
          POST {base}/v3/token/generate-token
          Body: { abhaNumber?, abhaAddress, name, gender, yearOfBirth }
          → { } (202) ; the X-LINK-TOKEN arrives at the webhook as
            callback type "linkToken", keyed by our REQUEST-ID.

          Caller persists { requestId } in abdm_link_tokens (status 'pending',
          expires_at = now + 6 months).
    ═══════════════════════════════════════════════════════════════ */

    public function generateLinkToken(
        string $abhaAddress,
        string $abhaNumber,
        string $name,
        string $gender,
        int $yearOfBirth,
        ?string $requestId = null
    ): array {
        [$token, $err] = $this->token();
        if ($err !== null) return $this->fail($err);

        $abhaAddress = trim($abhaAddress);
        if ($abhaAddress === '') return $this->fail('ABHA address is required.');

        $body = [
            'abhaAddress' => $abhaAddress,
            'name'        => trim($name),
            'gender'      => $this->gender($gender),
            'yearOfBirth' => $yearOfBirth,
        ];
        $abhaNumber = preg_replace('/\D/', '', $abhaNumber);
        if (strlen($abhaNumber) === 14) {
            $body['abhaNumber'] = AbdmApi::formatAbhaNumber($abhaNumber);
        }

        $requestId = ($requestId !== null && $requestId !== "") ? $requestId : $this->uuid();
        $r = $this->http('POST', $this->base . '/v3/token/generate-token', $body, $token, $requestId);

        if (!$this->is2xx($r)) {
            $this->logSafe('generate-token failed', ['requestId' => $requestId, 'http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not request an ABDM link token.'));
        }

        $this->logSafe('generate-token accepted', ['requestId' => $requestId, 'http' => $r['_http']]);
        return $this->ok(['requestId' => $requestId, 'accepted' => true]);
    }

    /* ═══════════════════════════════════════════════════════════════
       2. LINK CARE CONTEXT
          POST {base}/hip/v3/link/carecontext   (header X-LINK-TOKEN)
          Body: { abhaNumber?, abhaAddress,
                  patient: { referenceNumber, display, careContexts[], hiType, count } }
          → { } (202) ; linking-status arrives at the webhook keyed by REQUEST-ID.

          $careContexts: [ ['referenceNumber'=>..., 'display'=>...], ... ]
          $linkToken:    the X-LINK-TOKEN from generateLinkToken()'s webhook.
    ═══════════════════════════════════════════════════════════════ */

    public function linkCareContext(
        string $abhaAddress,
        string $abhaNumber,
        string $patientRef,
        string $displayName,
        string $hiType,
        array $careContexts,
        ?string $linkToken = null,
        ?string $requestId = null
    ): array {
        [$token, $err] = $this->token();
        if ($err !== null) return $this->fail($err);

        $linkToken = trim((string) $linkToken);
        if ($linkToken === '') {
            return $this->fail('No ABDM link token for this patient yet. Generate one first.', 'link_token_missing');
        }
        if (trim($abhaAddress) === '' || trim($patientRef) === '') {
            return $this->fail('ABHA address and patient reference are required.');
        }

        $ccs = [];
        foreach ($careContexts as $cc) {
            $ref = trim((string) ($cc['referenceNumber'] ?? ''));
            if ($ref === '') continue;
            $ccs[] = [
                'referenceNumber' => $ref,
                'display'         => trim((string) ($cc['display'] ?? $ref)),
            ];
        }
        if (!$ccs) return $this->fail('At least one care context is required.');

        $body = [
            'abhaAddress' => trim($abhaAddress),
            'patient'     => [
                'referenceNumber' => trim($patientRef),
                'display'         => trim($displayName),
                'careContexts'    => $ccs,
                'hiType'          => $hiType,
                'count'           => count($ccs),
            ],
        ];
        $abhaNumber = preg_replace('/\D/', '', $abhaNumber);
        if (strlen($abhaNumber) === 14) {
            $body['abhaNumber'] = AbdmApi::formatAbhaNumber($abhaNumber);
        }

        $requestId = ($requestId !== null && $requestId !== "") ? $requestId : $this->uuid();
        $r = $this->http(
            'POST',
            $this->base . '/hip/v3/link/carecontext',
            $body,
            $token,
            $requestId,
            ['X-LINK-TOKEN' => $linkToken]
        );

        if (!$this->is2xx($r)) {
            $this->logSafe('link/carecontext failed', ['requestId' => $requestId, 'http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not link the care context.'));
        }

        $this->logSafe('link/carecontext accepted', ['requestId' => $requestId, 'http' => $r['_http'], 'count' => count($ccs)]);
        return $this->ok(['requestId' => $requestId, 'accepted' => true, 'count' => count($ccs)]);
    }

    /* ═══════════════════════════════════════════════════════════════
       3. NOTIFY CARE CONTEXT
          POST {base}/hip/v3/link/context/notify
          Tells the CM that a new care context now exists for this ABHA.
          Fire-and-forget; still returns a requestId.
    ═══════════════════════════════════════════════════════════════ */

    public function notifyCareContext(string $abhaAddress, string $careContextRef, array $hiTypes, ?string $requestId = null): array
    {
        [$token, $err] = $this->token();
        if ($err !== null) return $this->fail($err);

        $abhaAddress    = trim($abhaAddress);
        $careContextRef = trim($careContextRef);
        if ($abhaAddress === '' || $careContextRef === '') {
            return $this->fail('ABHA address and care-context reference are required.');
        }
        $hiTypes = array_values(array_filter(array_map('strval', $hiTypes)));
        if (!$hiTypes) $hiTypes = ['Prescription'];

        $body = [
            'notification' => [
                'patient'     => ['id' => $abhaAddress],
                'careContext' => ['referenceNumber' => $careContextRef],
                'hiTypes'     => $hiTypes,
                'date'        => $this->timestamp(),
                'hip'         => ['id' => $this->hipId, 'name' => $this->hipName],
            ],
        ];

        $requestId = ($requestId !== null && $requestId !== "") ? $requestId : $this->uuid();
        $r = $this->http('POST', $this->base . '/hip/v3/link/context/notify', $body, $token, $requestId);

        if (!$this->is2xx($r)) {
            $this->logSafe('context/notify failed', ['requestId' => $requestId, 'http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not notify the care context.'));
        }

        $this->logSafe('context/notify accepted', ['requestId' => $requestId, 'http' => $r['_http']]);
        return $this->ok(['requestId' => $requestId, 'accepted' => true]);
    }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE
    ═══════════════════════════════════════════════════════════════ */

    /** @return array{0:string,1:?string} [gateway token, errorOrNull] */
    private function token(): array
    {
        if (!$this->isConfigured()) {
            return ['', 'HIP linking is not configured (set ABDM_HIP_ID and the ABHA credentials in .env).'];
        }
        try {
            if ($this->abdm === null) {
                $this->abdm = new AbdmApi();
            }
            $t = $this->abdm->getAccessToken();
            return $t ? [$t, null] : ['', 'Could not obtain an ABDM gateway session token.'];
        } catch (Throwable $e) {
            error_log('[HipApi] token error: ' . $e->getMessage());
            return ['', 'Could not obtain an ABDM gateway session token.'];
        }
    }

    /**
     * @return array{_http:int,_json:mixed,_raw:string,_curlErr:?string}
     */
    private function http(string $method, string $url, ?array $body, string $bearer, string $requestId, array $extra = []): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $bearer,
            'REQUEST-ID: ' . $requestId,
            'TIMESTAMP: ' . $this->timestamp(),
            'X-CM-ID: ' . $this->xCmId,
            'X-HIP-ID: ' . $this->hipId,
        ];
        foreach ($extra as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_POSTFIELDS     => $body !== null ? json_encode($body) : null,
        ]);

        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        $errMsg = curl_error($ch);
        $http   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['_http' => 0, '_json' => null, '_raw' => '', '_curlErr' => "[{$errno}] {$errMsg}"];
        }

        $raw  = is_string($raw) ? $raw : '';
        $json = json_decode($raw, true);
        return ['_http' => $http, '_json' => $json, '_raw' => $raw, '_curlErr' => null];
    }

    private function is2xx(array $r): bool
    {
        return $r['_curlErr'] === null && $r['_http'] >= 200 && $r['_http'] < 300;
    }

    /** @return array{success:true,data:mixed,error:null,code:null} */
    private function ok($data = null): array
    {
        return ['success' => true, 'data' => $data, 'error' => null, 'code' => null];
    }

    /** @return array{success:false,data:null,error:string,code:?string} */
    private function fail(string $message, ?string $code = null): array
    {
        return ['success' => false, 'data' => null, 'error' => $message, 'code' => $code];
    }

    /** Best-effort readable error from an $this->http() result (ABDM error shapes → HTTP hints). */
    private function extractError(array $r, string $fallback = 'ABDM HIP API error'): string
    {
        if (!empty($r['_curlErr'])) {
            return 'Network error contacting ABDM. Please try again.';
        }
        $j = $r['_json'];
        if (is_array($j)) {
            foreach ([
                $j['error']['message'] ?? null,
                $j['errorMessage'] ?? null,
                $j['details'][0]['message'] ?? null,
                $j['message'] ?? null,
                $j['error']['code'] ?? null,
                $j['code'] ?? null,
            ] as $c) {
                if (is_string($c) && $c !== '') return $c;
            }
        }
        switch ((int) $r['_http']) {
            case 400: return 'ABDM rejected the linking request. Check the patient / care-context details.';
            case 401:
            case 403: return 'ABDM authorisation failed (gateway token or link token).';
            case 404: return 'ABDM linking endpoint not found.';
            case 422: return 'ABDM could not process the linking request.';
            case 429: return 'Too many requests to ABDM. Please retry shortly.';
        }
        if ((int) $r['_http'] >= 500) return 'ABDM is temporarily unavailable. Please try again later.';
        return $fallback;
    }

    private function gender(string $g): string
    {
        $g = strtoupper(trim($g));
        if (in_array($g, ['M', 'MALE'], true))   return 'M';
        if (in_array($g, ['F', 'FEMALE'], true)) return 'F';
        return 'O';
    }

    /** PII-safe log line — whitelisted scalars only. */
    private function logSafe(string $event, array $ctx = []): void
    {
        $safe = [];
        foreach (['requestId', 'http', 'status', 'count', 'curl'] as $k) {
            if (array_key_exists($k, $ctx) && (is_scalar($ctx[$k]) || $ctx[$k] === null)) {
                $safe[$k] = $ctx[$k];
            }
        }
        error_log('[HipApi] ' . $event . ($safe ? ' ' . json_encode($safe) : ''));
    }

    /** UUID v4 from CSPRNG (ABDM REQUEST-ID). */
    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** ISO-8601 UTC with milliseconds. */
    private function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }
}
