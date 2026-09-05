<?php

/**
 * ConsentApi — ABDM HI Consent / data-flow outbound acknowledgements.
 *
 * Phase A: consentHipOnNotify() (reply to the CM's consent-notify) and
 * hiOnRequest() (reply to a HIU's health-information request — task 2d).
 *
 * Mirrors lib/HipApi.php: reuses AbdmApi's gateway session token, the same
 * V3 headers, ['success','data','error','code'] returns, and PII-safe
 * logging (requestId + HTTP code only — never consent bodies / ABHA data).
 *
 * Config: config/abdm.php (ABDM_HIECM_BASE_URL, ABDM_HIP_ID, ABDM_X_CM_ID).
 */
class ConsentApi
{
    private string $base;        // https://dev.abdm.gov.in/api/hiecm
    private string $xCmId;
    private string $hipId;
    private bool   $sslVerify;
    private ?AbdmApi $abdm = null;

    public function __construct()
    {
        $this->base      = rtrim(defined('ABDM_HIECM_BASE_URL') ? ABDM_HIECM_BASE_URL : '', '/');
        $this->xCmId     = defined('ABDM_X_CM_ID') ? ABDM_X_CM_ID : 'sbx';
        $this->hipId     = defined('ABDM_HIP_ID') ? ABDM_HIP_ID : '';
        $this->sslVerify = defined('ABDM_SSL_VERIFY') ? (bool) ABDM_SSL_VERIFY : true;
    }

    public function isConfigured(): bool
    {
        return $this->hipId !== '' && defined('ABDM_CONFIGURED') && ABDM_CONFIGURED;
    }

    /* ═══════════════════════════════════════════════════════════════
       CONSENT NOTIFY — ACK  (task 2b)
         POST {base}/consent/v3/request/hip/on-notify
         Body: { acknowledgement:{status,consentId}, error?, response:{requestId} }
         response.requestId = the REQUEST-ID header of the inbound notify call.
    ═══════════════════════════════════════════════════════════════ */

    public function consentHipOnNotify(string $ackStatus, string $consentId, string $inboundRequestId, ?array $error = null): array
    {
        if (trim($inboundRequestId) === '') {
            return $this->fail('No inbound REQUEST-ID to acknowledge against.', 'no_request_id');
        }

        [$token, $err] = $this->token();
        if ($err !== null) return $this->fail($err);

        $body = [
            'acknowledgement' => [
                'status'    => $ackStatus !== '' ? $ackStatus : 'OK',
                'consentId' => $consentId,
            ],
            'response' => ['requestId' => $inboundRequestId],
        ];
        if ($error !== null) {
            $body['error'] = [
                'code'    => (string) ($error['code'] ?? 'ABDM-9999'),
                'message' => (string) ($error['message'] ?? 'error'),
            ];
        }

        $r = $this->http('POST', $this->base . '/consent/v3/request/hip/on-notify', $body, $token);
        if (!$this->is2xx($r)) {
            $this->logSafe('consent on-notify failed', ['requestId' => $inboundRequestId, 'http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not acknowledge the consent notify.'));
        }
        $this->logSafe('consent on-notify accepted', ['requestId' => $inboundRequestId, 'http' => $r['_http']]);
        return $this->ok(['requestId' => $inboundRequestId, 'accepted' => true]);
    }

    /* ═══════════════════════════════════════════════════════════════
       HEALTH-INFORMATION REQUEST — ACK  (task 2d)
         POST {base}/data-flow/v3/health-information/hip/on-request
         Body: { hiRequest:{transactionId, sessionStatus}, response:{requestId} }
                error case: { error:{code,message}, response:{requestId} }
    ═══════════════════════════════════════════════════════════════ */

    public function hiOnRequest(string $transactionId, string $sessionStatus, string $inboundRequestId, ?array $error = null): array
    {
        if (trim($inboundRequestId) === '') {
            return $this->fail('No inbound REQUEST-ID to acknowledge against.', 'no_request_id');
        }

        [$token, $err] = $this->token();
        if ($err !== null) return $this->fail($err);

        $body = ['response' => ['requestId' => $inboundRequestId]];
        if ($error !== null) {
            $body['error'] = [
                'code'    => (string) ($error['code'] ?? 'ABDM-9999'),
                'message' => (string) ($error['message'] ?? 'error'),
            ];
        } else {
            $body['hiRequest'] = [
                'transactionId' => $transactionId,
                'sessionStatus' => $sessionStatus !== '' ? $sessionStatus : 'ACKNOWLEDGED',
            ];
        }

        $r = $this->http('POST', $this->base . '/data-flow/v3/health-information/hip/on-request', $body, $token);
        if (!$this->is2xx($r)) {
            $this->logSafe('hi on-request failed', ['requestId' => $inboundRequestId, 'http' => $r['_http'], 'curl' => $r['_curlErr']]);
            return $this->fail($this->extractError($r, 'Could not acknowledge the health-information request.'));
        }
        $this->logSafe('hi on-request accepted', ['requestId' => $inboundRequestId, 'http' => $r['_http']]);
        return $this->ok(['requestId' => $inboundRequestId, 'accepted' => true]);
    }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE — transport / helpers (mirror lib/HipApi.php)
    ═══════════════════════════════════════════════════════════════ */

    /** @return array{0:string,1:?string} [gateway token, errorOrNull] */
    private function token(): array
    {
        if (!$this->isConfigured()) {
            return ['', 'HI Consent is not configured (set ABDM_HIP_ID and the ABHA credentials in .env).'];
        }
        try {
            if ($this->abdm === null) {
                $this->abdm = new AbdmApi();
            }
            $t = $this->abdm->getAccessToken();
            return $t ? [$t, null] : ['', 'Could not obtain an ABDM gateway session token.'];
        } catch (Throwable $e) {
            error_log('[ConsentApi] token error: ' . $e->getMessage());
            return ['', 'Could not obtain an ABDM gateway session token.'];
        }
    }

    /** @return array{_http:int,_json:mixed,_raw:string,_curlErr:?string} */
    private function http(string $method, string $url, ?array $body, string $bearer, array $extra = []): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $bearer,
            'REQUEST-ID: ' . $this->uuid(),
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
        return ['_http' => $http, '_json' => json_decode($raw, true), '_raw' => $raw, '_curlErr' => null];
    }

    private function is2xx(array $r): bool
    {
        return $r['_curlErr'] === null && $r['_http'] >= 200 && $r['_http'] < 300;
    }

    private function ok($data = null): array
    {
        return ['success' => true, 'data' => $data, 'error' => null, 'code' => null];
    }

    private function fail(string $message, ?string $code = null): array
    {
        return ['success' => false, 'data' => null, 'error' => $message, 'code' => $code];
    }

    private function extractError(array $r, string $fallback = 'ABDM consent API error'): string
    {
        if (!empty($r['_curlErr'])) {
            return 'Network error contacting ABDM. Please try again.';
        }
        $j = $r['_json'];
        if (is_array($j)) {
            foreach ([
                $j['error']['message'] ?? null,
                $j['message'] ?? null,
                $j['error']['code'] ?? null,
            ] as $c) {
                if (is_string($c) && $c !== '') return $c;
            }
        }
        $http = (int) $r['_http'];
        if ($http === 401 || $http === 403) return 'ABDM authorisation failed (gateway token).';
        if ($http === 400) return 'ABDM rejected the acknowledgement body.';
        if ($http === 429) return 'Too many requests to ABDM. Please retry shortly.';
        if ($http >= 500)  return 'ABDM is temporarily unavailable. Please try again later.';
        return $fallback;
    }

    /** PII-safe log line — whitelisted scalars only. */
    private function logSafe(string $event, array $ctx = []): void
    {
        $safe = [];
        foreach (['requestId', 'http', 'status', 'curl'] as $k) {
            if (array_key_exists($k, $ctx) && (is_scalar($ctx[$k]) || $ctx[$k] === null)) {
                $safe[$k] = $ctx[$k];
            }
        }
        error_log('[ConsentApi] ' . $event . ($safe ? ' ' . json_encode($safe) : ''));
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }
}
