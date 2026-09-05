<?php
/**
 * ABDM HI Data-Flow — health-information request receiver (Phase A: receive +
 * consent-check + record + ack). NO FHIR build, NO encryption, NO data push —
 * that is Phase B; a matched request only reaches status 'acknowledged' here.
 *
 * PUBLIC endpoint. Own bridge sub-path:
 *   POST  <bridge>/api/v3/hip/health-information/request   (.htaccess → here)
 *
 * Layered defence via AbdmWebhookGuard. Raw body → abdm_webhook_log
 * (channel='hi_request') BEFORE parsing. Always 200.
 *
 * Confirmed body: { hiRequest: { consent:{id}, dateRange:{from,to},
 *   dataPushUrl, keyMaterial:{cryptoAlg,curve,dhPublicKey:{expiry,parameters,
 *   keyValue},nonce} } }  (transactionId / requestId come from the gateway
 *   wrapper / REQUEST-ID header.)
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';       // shared raw-log
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/HiConsent.php';
require_once dirname(__DIR__, 2) . '/lib/HiRequest.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/lib/AbdmApi.php';
require_once dirname(__DIR__, 2) . '/lib/ConsentApi.php';

$raw               = AbdmWebhookGuard::pass('hi_request', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload           = json_decode($raw, true);

try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'hi-request', $raw, 'hi_request');
} catch (Throwable $e) {
    error_log('[abdm-hi-request-webhook] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-hi-request-webhook] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

/** Ack the gateway (task 2d). Skipped silently if HIP not configured. */
function hi_request_ack(?string $reqId, string $txnId, bool $ok, ?array $error = null): void
{
    if ($reqId === null) {
        return;
    }
    try {
        $capi = new ConsentApi();
        if (!$capi->isConfigured()) {
            error_log('[abdm-hi-request-webhook] on-request ack skipped — HIP not configured');
            return;
        }
        $capi->hiOnRequest($txnId, $ok ? 'ACKNOWLEDGED' : '', $reqId, $ok ? null : $error);
    } catch (Throwable $e) {
        error_log('[abdm-hi-request-webhook] on-request ack failed: ' . $e->getMessage());
    }
}

try {
    $hi        = is_array($payload['hiRequest'] ?? null) ? $payload['hiRequest'] : [];
    $consentId = trim((string) ($hi['consent']['id'] ?? ''));
    $txnId     = trim((string) ($payload['transactionId'] ?? $hi['transactionId'] ?? ''));
    $reqId     = trim((string) ($payload['requestId'] ?? $incomingRequestId ?? ''));

    if ($consentId === '') {
        error_log('[abdm-hi-request-webhook] missing hiRequest.consent.id');
        $logger->logHiDataRequest('', $txnId, 'FAILURE', ['reason' => 'missing consent id']);
        HipLinking::markWebhookProcessed($conn, $logId);
        hi_request_ack($incomingRequestId, $txnId, false, ['code' => 'ABDM-1064', 'message' => 'missing consent id']);
        AbdmWebhookGuard::ack();
    }

    $chk = HiConsent::servable($conn, $consentId);
    if (!$chk['ok']) {
        error_log('[abdm-hi-request-webhook] consent not servable: ' . $chk['reason'] . ' (' . substr($consentId, -8) . ')');
        $logger->logHiDataRequest($consentId, $txnId, 'FAILURE', ['reason' => $chk['reason']]);
        HipLinking::markWebhookProcessed($conn, $logId);
        // no abha_hi_requests row on a consent failure (per spec)
        hi_request_ack($incomingRequestId, $txnId, false, ['code' => 'ABDM-1066', 'message' => $chk['reason']]);
        AbdmWebhookGuard::ack();
    }

    $range = is_array($hi['dateRange'] ?? null) ? $hi['dateRange'] : [];
    $km    = is_array($hi['keyMaterial'] ?? null) ? $hi['keyMaterial'] : [];

    $id = HiRequest::insertAcknowledged($conn, [
        'transaction_id'  => $txnId !== '' ? $txnId : null,
        'request_id'      => $reqId !== '' ? $reqId : null,
        'consent_id'      => $consentId,
        'date_range_from' => AbdmWebhookGuard::dt($range['from'] ?? null),
        'date_range_to'   => AbdmWebhookGuard::dt($range['to'] ?? null),
        'data_push_url'   => (string) ($hi['dataPushUrl'] ?? '') ?: null,
        'key_material'    => json_encode($km),   // full HIU key material for Phase B
    ]);

    $ok = $id > 0;
    $logger->logHiDataRequest($consentId, $txnId, $ok ? 'ACKNOWLEDGED' : 'FAILURE', [
        'has_push_url'     => !empty($hi['dataPushUrl']),
        'has_key_material' => !empty($km),
    ]);

    HipLinking::markWebhookProcessed($conn, $logId);
    error_log('[abdm-hi-request-webhook] acknowledged consent_tail=' . substr($consentId, -8) . ' row=' . $id);

    hi_request_ack($incomingRequestId, $txnId, $ok, $ok ? null : ['code' => 'ABDM-9999', 'message' => 'could not record request']);

} catch (Throwable $e) {
    error_log('[abdm-hi-request-webhook] processing error: ' . $e->getMessage());
}

AbdmWebhookGuard::ack();
