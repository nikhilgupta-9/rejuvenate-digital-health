<?php
/**
 * ABDM HIP-Initiated Linking — webhook receiver.
 *
 * PUBLIC endpoint (ABDM's gateway is the caller — no login, no session,
 * no CSRF). Register a single BRIDGE URL with ABDM:
 *   https://<host>/?k=<ABDM_WEBHOOK_SECRET>
 * ABDM appends one of three fixed sub-paths, which .htaccess rewrites here:
 *   /api/v3/hip/token/on-generate-token   → link token (or async error)
 *   /api/v3/link/on_carecontext           → care-context linking status
 *   /api/v3/links/context/on-notify       → our notify() ACK
 * (direct hits to this file still work via payload-shape fallback.)
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

/**
 * Route from the request path. ABDM appends one of three fixed sub-paths to
 * the registered bridge URL (M2 spec v2.8); .htaccess rewrites each here.
 * REQUEST_URI keeps the ORIGINAL path across an internal rewrite.
 */
function wh_route(): ?string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (str_ends_with($path, '/api/v3/hip/token/on-generate-token')) return 'link-token';
    if (str_ends_with($path, '/api/v3/link/on_carecontext'))         return 'carecontext';
    if (str_ends_with($path, '/api/v3/links/context/on-notify'))     return 'notify';
    return null;
}

/**
 * Fallback when the path is not one of the three (direct hit / local test).
 * Confirmed shapes: link-token has `linkToken`; on-notify has `acknowledgement`;
 * on_carecontext has a `status` string (or an `error` object).
 */
function wh_classify(array $p): string
{
    if (isset($p['linkToken']) || isset($p['response']['linkToken'])) return 'link-token';
    if (isset($p['acknowledgement'])) return 'notify';
    return 'carecontext';
}

/** ABDM error code carried in a callback (async ABDM-1207 etc. in `error`). */
function wh_error_code(array $p): string
{
    $c = $p['error']['code'] ?? $p['response']['error']['code'] ?? '';
    return is_string($c) ? strtoupper(trim($c)) : '';
}

function wh_link_token(array $p): string
{
    return (string) ($p['linkToken'] ?? $p['response']['linkToken'] ?? '');
}

/**
 * on_carecontext success — EXACT `status` string match (confirmed shapes).
 * Failure variants also contain the words "care context", so no substring test.
 *   success:  "Successfully Linked care context"
 *             "These care contexts have been already linked"  (idempotent → linked)
 *   failure:  "Counter and Care context count mismatch"
 *             "ABHA address and Link token mismatch"
 *             "Dependent service unavailable"
 * Anything else (or an `error` object) → failed.
 */
function wh_carecontext_linked(array $p): bool
{
    return in_array(trim((string) ($p['status'] ?? '')), [
        'Successfully Linked care context',
        'These care contexts have been already linked',
    ], true);
}

/** on-notify success — acknowledgement.status === "SUCCESS" and no error object. */
function wh_notify_ok(array $p): bool
{
    if (isset($p['error']['code'])) return false;
    return strtoupper(trim((string) ($p['acknowledgement']['status'] ?? ''))) === 'SUCCESS';
}

$route     = wh_route() ?? wh_classify($payload);
$requestId = wh_request_id($payload);

// 0. Always record the raw callback first.
try {
    $logId = HipLinking::logWebhook($conn, $requestId, $route, $raw);
} catch (Throwable $e) {
    error_log('[abdm-webhook] logWebhook failed: ' . $e->getMessage());
    webhook_ack(); // can't even log — still ack so ABDM doesn't hammer us
}

// 6. THE REAL GATE + 7. idempotency + processing — all wrapped so nothing
//    below can prevent the 200.
try {
    // context/on-notify — the ACK of our own notify() call. We don't persist its
    // requestId, and the care-context link status is governed by on_carecontext,
    // so this is log-only (no abdm_care_context_links change).
    if ($route === 'notify') {
        $ec = wh_error_code($payload);
        error_log('[abdm-webhook] notify ack ' . (wh_notify_ok($payload)
            ? 'SUCCESS'
            : 'not-ok' . ($ec !== '' ? " code={$ec}" : '')));
        HipLinking::markWebhookProcessed($conn, $logId);
        webhook_ack();
    }

    if ($requestId === null) {
        error_log('[abdm-webhook] no requestId in payload (route=' . $route . ')');
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
    $errCode = wh_error_code($payload);

    if ($tokenRow) {
        // token-generate callback → a linkToken, or an async error (ABDM-1207…).
        $lt = wh_link_token($payload);
        if ($errCode !== '') {
            error_log('[abdm-webhook] token-generate error code=' . $errCode);
            $applied = HipLinking::failLinkToken($conn, $requestId);
        } elseif ($lt !== '') {
            $applied = HipLinking::applyLinkToken($conn, $requestId, $lt);
        } else {
            error_log('[abdm-webhook] link-token callback with neither token nor error');
        }
    } elseif ($ccRow) {
        // on_carecontext → linking status. Error object OR a non-success `status`
        // string → failed; only the two exact success strings → linked.
        if ($errCode !== '') {
            error_log('[abdm-webhook] on_carecontext error code=' . $errCode);
            $linked = false;
        } else {
            $linked = wh_carecontext_linked($payload);
            if (!$linked) {
                error_log('[abdm-webhook] on_carecontext non-success status="'
                    . substr((string) ($payload['status'] ?? ''), 0, 80) . '"');
            }
        }
        $applied = HipLinking::applyLinkingStatus($conn, $requestId, $linked);
    }

    HipLinking::markWebhookProcessed($conn, $logId);
    error_log('[abdm-webhook] processed route=' . $route . ' applied=' . ($applied ? '1' : '0'));

} catch (Throwable $e) {
    error_log('[abdm-webhook] processing error: ' . $e->getMessage());
    // leave processed=0 so it can be reconciled later
}

webhook_ack();
