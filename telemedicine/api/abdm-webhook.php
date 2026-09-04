<?php
/**
 * ABDM HIP-Initiated Linking — webhook receiver.
 *
 * PUBLIC endpoint (ABDM's gateway is the caller — no login, no session,
 * no CSRF). Register the URL with ABDM as the HIP callback:
 *   https://<host>/telemedicine/api/abdm-webhook.php?k=<ABDM_WEBHOOK_SECRET>
 *
 * ── Security model (there is no per-request auth from ABDM in the V3 HIP
 *    callback, so this is layered defence, weakest → strongest) ──
 *
 *  1. HTTP method — POST only. Anything else → 200 empty (no probing signal).
 *  2. Body size cap — refuse bodies over 256 KB before touching the DB, so a
 *     flood can't bloat abdm_webhook_log.
 *  3. Per-IP rate limit — generous (real ABDM traffic is low-volume).
 *  4. Optional URL secret (?k=) — constant-time compared to ABDM_WEBHOOK_SECRET
 *     when set. Wrong/missing → logged + 200 (no error detail leaked).
 *  5. Optional IP allowlist — ABDM_WEBHOOK_ALLOWED_IPS.
 *  6. ⭐ THE REAL GATE: request_id. Every callback must carry a requestId that
 *     matches a row WE created (abdm_link_tokens / abdm_care_context_links) and
 *     that is still pending. requestId is a server-generated UUID v4 that is
 *     never exposed to any client, so it is effectively an unguessable
 *     capability token. No match → save raw, mark processed, silently 200.
 *  7. Idempotency — if a prior callback already moved the target row to a
 *     terminal state, re-processing is skipped (still logged).
 *  8. Fail-open on 200 — every path returns 200 quickly (a non-2xx makes ABDM
 *     retry). All processing is wrapped so an internal error never blocks it.
 *  9. Data-only — payload values are always bound as params / read as scalars;
 *     nothing from the body is executed, concatenated into SQL, or evaluated.
 *
 * The raw body is ALWAYS written to abdm_webhook_log *before* any parsing.
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';

const ABDM_WEBHOOK_MAX_BYTES = 262144; // 256 KB

/** Always answer 200 fast; ABDM treats non-2xx as "retry". */
function webhook_ack(string $note = 'received'): void
{
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => $note]);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// 1. POST only.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    webhook_ack('ignored');
}

$ip = Security::clientIp();

// 3. Per-IP rate limit (session-less — Security uses $_SESSION, so start a
//    throwaway session just for the limiter; no cookie is set for a bot).
@ini_set('session.use_cookies', '0');
if (session_status() === PHP_SESSION_NONE) @session_start();
if (Security::isRateLimited(Security::rlKey('abdm_hook', $ip), 300, 300)) {
    error_log('[abdm-webhook] rate limited ip=' . $ip);
    webhook_ack('ignored');
}

// 5. Optional IP allowlist.
$allowIps = defined('ABDM_WEBHOOK_ALLOWED_IPS') ? ABDM_WEBHOOK_ALLOWED_IPS : '';
if ($allowIps !== '') {
    $allowed = array_filter(array_map('trim', explode(',', $allowIps)));
    if (!in_array($ip, $allowed, true)) {
        error_log('[abdm-webhook] ip not allowlisted: ' . $ip);
        webhook_ack('ignored');
    }
}

// 2. Body size cap — check declared length first, then the real read.
$declaredLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($declaredLen > ABDM_WEBHOOK_MAX_BYTES) {
    error_log('[abdm-webhook] oversized body declared=' . $declaredLen . ' ip=' . $ip);
    webhook_ack('ignored');
}
$raw = file_get_contents('php://input', false, null, 0, ABDM_WEBHOOK_MAX_BYTES + 1);
$raw = is_string($raw) ? $raw : '';
if (strlen($raw) > ABDM_WEBHOOK_MAX_BYTES || $raw === '') {
    error_log('[abdm-webhook] empty or oversized body ip=' . $ip);
    webhook_ack('ignored');
}

// 4. Optional URL secret.
$secret = defined('ABDM_WEBHOOK_SECRET') ? ABDM_WEBHOOK_SECRET : '';
if ($secret !== '') {
    $given = (string) ($_GET['k'] ?? '');
    if ($given === '' || !hash_equals($secret, $given)) {
        error_log('[abdm-webhook] bad/missing url secret ip=' . $ip);
        // still record it (truncated) so the noise is visible, then ack.
        try { HipLinking::logWebhook($conn, null, 'rejected_secret', substr($raw, 0, 2048)); } catch (Throwable $e) {}
        webhook_ack('ignored');
    }
}

/* ── Parse (defensive) + route ─────────────────────────────────── */
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    // Not JSON — log the raw, mark processed, move on.
    try {
        $id = HipLinking::logWebhook($conn, null, 'unparseable', $raw);
        HipLinking::markWebhookProcessed($conn, $id);
    } catch (Throwable $e) {
        error_log('[abdm-webhook] log(unparseable) failed: ' . $e->getMessage());
    }
    webhook_ack();
}

/** requestId can sit at a few paths across the V3 callback shapes. */
function wh_request_id(array $p): ?string
{
    foreach ([
        $p['response']['requestId'] ?? null,
        $p['requestId'] ?? null,
        $p['resp']['requestId'] ?? null,
        $p['acknowledgement']['requestId'] ?? null,
    ] as $c) {
        if (is_string($c) && $c !== '') return substr(trim($c), 0, 64);
    }
    return null;
}

/** Classify by which keys are present. */
function wh_callback_type(array $p): string
{
    if (isset($p['linkToken']) || isset($p['response']['linkToken'])) return 'linkToken';
    if (isset($p['acknowledgement']) || isset($p['status']) || isset($p['error'])
        || isset($p['response']['status'])) {
        return 'linking-status';
    }
    return 'unknown';
}

function wh_link_token(array $p): string
{
    return (string) ($p['linkToken'] ?? $p['response']['linkToken'] ?? '');
}

function wh_status_ok(array $p): bool
{
    $s = strtoupper((string) (
        $p['acknowledgement']['status']
        ?? $p['status']
        ?? $p['response']['status']
        ?? ($p['error'] ?? null ? 'FAILED' : '')
    ));
    return in_array($s, ['SUCCESS', 'OK', 'ACCEPTED', 'LINKED', 'COMPLETED'], true);
}

$requestId    = wh_request_id($payload);
$callbackType = wh_callback_type($payload);

// 0. Always record the raw callback first.
try {
    $logId = HipLinking::logWebhook($conn, $requestId, $callbackType, $raw);
} catch (Throwable $e) {
    error_log('[abdm-webhook] logWebhook failed: ' . $e->getMessage());
    webhook_ack(); // can't even log — still ack so ABDM doesn't hammer us
}

// 6. THE REAL GATE + 7. idempotency + processing — all wrapped so nothing
//    below can prevent the 200.
try {
    if ($requestId === null) {
        error_log('[abdm-webhook] no requestId in payload (type=' . $callbackType . ')');
        HipLinking::markWebhookProcessed($conn, $logId);
        webhook_ack();
    }

    $tokenRow = HipLinking::findLinkTokenByRequest($conn, $requestId);
    $ccRow    = HipLinking::findCareContextByRequest($conn, $requestId);

    if (!$tokenRow && !$ccRow) {
        // requestId we never issued (or already GC'd) → silently reject.
        error_log('[abdm-webhook] unknown requestId (silent reject)');
        HipLinking::markWebhookProcessed($conn, $logId);
        webhook_ack();
    }

    if (HipLinking::alreadyHandled($conn, $requestId)) {
        error_log('[abdm-webhook] duplicate callback for requestId — skipped');
        HipLinking::markWebhookProcessed($conn, $logId);
        webhook_ack();
    }

    $applied = false;

    if ($tokenRow && ($callbackType === 'linkToken' || wh_link_token($payload) !== '')) {
        $lt = wh_link_token($payload);
        if ($lt === '') {
            error_log('[abdm-webhook] linkToken callback with empty token');
        } else {
            $applied = HipLinking::applyLinkToken($conn, $requestId, $lt);
        }
    } elseif ($ccRow) {
        $applied = HipLinking::applyLinkingStatus($conn, $requestId, wh_status_ok($payload));
    } else {
        error_log('[abdm-webhook] callback type/record mismatch (type=' . $callbackType . ')');
    }

    HipLinking::markWebhookProcessed($conn, $logId);
    error_log('[abdm-webhook] processed type=' . $callbackType . ' applied=' . ($applied ? '1' : '0'));

} catch (Throwable $e) {
    error_log('[abdm-webhook] processing error: ' . $e->getMessage());
    // leave processed=0 so it can be reconciled later
}

webhook_ack();
