<?php

/**
 * AbdmWebhookGuard — shared front-door for the public ABDM callback endpoints
 * (HI consent, HI data-request). ABDM's callbacks carry no per-request auth,
 * so this is layered defence, run BEFORE any body parsing.
 *
 * On any gate failure it answers HTTP 200 (ABDM retries on non-2xx) and
 * exit()s. On success it returns the raw request body.
 *
 * Requires lib/Security.php and (for the rejected-secret log) lib/HipLinking.php
 * to be already loaded by the caller.
 */
class AbdmWebhookGuard
{
    public const MAX_BYTES = 262144; // 256 KB

    /**
     * @param string      $channel  log/rate-limit label ('consent' | 'hi_request')
     * @param mysqli|null $conn     used only to log a rejected-secret hit
     * @return string               the raw request body
     */
    public static function pass(string $channel, ?mysqli $conn = null): string
    {
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::ack('ignored');
        }

        $ip = Security::clientIp();
        @ini_set('session.use_cookies', '0');
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (Security::isRateLimited(Security::rlKey('abdm_hook_' . $channel, $ip), 300, 300)) {
            error_log("[abdm-$channel-webhook] rate limited ip=$ip");
            self::ack('ignored');
        }

        $allowIps = defined('ABDM_WEBHOOK_ALLOWED_IPS') ? ABDM_WEBHOOK_ALLOWED_IPS : '';
        if ($allowIps !== '') {
            $allowed = array_filter(array_map('trim', explode(',', $allowIps)));
            if (!in_array($ip, $allowed, true)) {
                error_log("[abdm-$channel-webhook] ip not allowlisted: $ip");
                self::ack('ignored');
            }
        }

        $declaredLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declaredLen > self::MAX_BYTES) {
            self::ack('ignored');
        }
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BYTES + 1);
        $raw = is_string($raw) ? $raw : '';
        if ($raw === '' || strlen($raw) > self::MAX_BYTES) {
            self::ack('ignored');
        }

        $secret = defined('ABDM_WEBHOOK_SECRET') ? ABDM_WEBHOOK_SECRET : '';
        if ($secret !== '') {
            $given = (string) ($_GET['k'] ?? '');
            if ($given === '' || !hash_equals($secret, $given)) {
                error_log("[abdm-$channel-webhook] bad/missing url secret ip=$ip");
                if ($conn !== null) {
                    try {
                        HipLinking::logWebhook($conn, null, 'rejected_secret', substr($raw, 0, 2048), $channel);
                    } catch (Throwable $e) {
                    }
                }
                self::ack('ignored');
            }
        }

        return $raw;
    }

    /** The gateway REQUEST-ID header of this inbound call (echoed in our on-* acks). */
    public static function requestIdHeader(): ?string
    {
        foreach (['HTTP_REQUEST_ID', 'HTTP_X_REQUEST_ID'] as $k) {
            $v = $_SERVER[$k] ?? null;
            if (is_string($v) && $v !== '') {
                return substr(trim($v), 0, 64);
            }
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $hk => $hv) {
                if (strcasecmp($hk, 'REQUEST-ID') === 0 && is_string($hv) && $hv !== '') {
                    return substr(trim($hv), 0, 64);
                }
            }
        }
        return null;
    }

    /** ISO-8601 → 'Y-m-d H:i:s' (UTC), or null. */
    public static function dt($iso): ?string
    {
        if (!is_string($iso) || trim($iso) === '') {
            return null;
        }
        $ts = strtotime($iso);
        return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    /** Always answer 200 fast — ABDM treats non-2xx as "retry". */
    public static function ack(string $note = 'received'): void
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => $note]);
        exit;
    }
}
