<?php
/**
 * ABDM Milestone 3 — Data flow (HIU role), step 2: on-request callback.
 *
 * PUBLIC endpoint. Own bridge sub-path:
 *   POST  <bridge>/api/v3/hiu/health-information/on-request   (.htaccess → here)
 *
 * ABDM calls this right after our ConsentApi::hiuDataFlowRequest() to hand
 * back the real transactionId for the request_id we sent — the HIP will
 * push the actual records against that transactionId next (received at
 * abdm-hiu-health-information-push.php).
 *
 * Confirmed body: { hiRequest:{transactionId,sessionStatus}, error, response:{requestId} }
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';       // shared raw-log
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/HiuDataFlow.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';

$raw               = AbdmWebhookGuard::pass('hiu_consent', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload           = json_decode($raw, true);

try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'hiu-data-on-request', $raw, 'hiu_consent');
} catch (Throwable $e) {
    error_log('[abdm-hiu-hi-on-request] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-hiu-hi-on-request] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

try {
    $ourRequestId  = trim((string) ($payload['response']['requestId'] ?? ''));
    $hi            = is_array($payload['hiRequest'] ?? null) ? $payload['hiRequest'] : [];
    $transactionId = trim((string) ($hi['transactionId'] ?? ''));
    $sessionStatus = trim((string) ($hi['sessionStatus'] ?? ''));
    $error         = is_array($payload['error'] ?? null) ? $payload['error'] : null;

    if ($ourRequestId === '') {
        error_log('[abdm-hiu-hi-on-request] missing response.requestId');
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    if ($error !== null || $transactionId === '') {
        error_log('[abdm-hiu-hi-on-request] request failed for ' . $ourRequestId . ': ' . json_encode($error));
        HiuDataFlow::attachTransaction($conn, $ourRequestId, null, 'failed', (string) ($error['message'] ?? 'no transactionId'));
        $logger->logHiDataRequest('', $ourRequestId, 'FAILURE', ['reason' => $error['message'] ?? 'no transactionId']);
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    $ok = HiuDataFlow::attachTransaction($conn, $ourRequestId, $transactionId, 'acknowledged');
    $logger->logHiDataRequest('', $transactionId, $ok ? 'ACKNOWLEDGED' : 'FAILURE', ['our_request_id' => $ourRequestId, 'session_status' => $sessionStatus]);
    error_log('[abdm-hiu-hi-on-request] attached transactionId=' . $transactionId . ' to request=' . $ourRequestId . ' ok=' . ($ok ? '1' : '0'));

    HipLinking::markWebhookProcessed($conn, $logId);
} catch (Throwable $e) {
    error_log('[abdm-hiu-hi-on-request] processing error: ' . $e->getMessage());
}

AbdmWebhookGuard::ack();
