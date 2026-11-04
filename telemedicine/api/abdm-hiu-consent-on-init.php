<?php
/**
 * ABDM Milestone 3 — Consent flow (HIU role), step 2: on-init callback.
 *
 * PUBLIC endpoint. Own bridge sub-path:
 *   POST  <bridge>/api/v3/hiu/consent/request/on-init   (.htaccess → here)
 *
 * ABDM calls this right after our ConsentApi::hiuConsentRequestInit() to
 * hand back the real consentRequest.id for the request_id we sent.
 *
 * Confirmed body: { consentRequest:{id}, error, response:{requestId} }
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';       // shared raw-log
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/HiuConsent.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';

$raw               = AbdmWebhookGuard::pass('hiu_consent', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload           = json_decode($raw, true);

try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'hiu-consent-on-init', $raw, 'hiu_consent');
} catch (Throwable $e) {
    error_log('[abdm-hiu-consent-on-init] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-hiu-consent-on-init] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

try {
    $ourRequestId     = trim((string) ($payload['response']['requestId'] ?? ''));
    $consentRequestId = trim((string) ($payload['consentRequest']['id'] ?? ''));
    $error            = is_array($payload['error'] ?? null) ? $payload['error'] : null;

    if ($ourRequestId === '') {
        error_log('[abdm-hiu-consent-on-init] missing response.requestId');
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    if ($error !== null || $consentRequestId === '') {
        error_log('[abdm-hiu-consent-on-init] init failed for request ' . $ourRequestId . ': ' . json_encode($error));
        $logger->logHiConsentNotify('init', $ourRequestId, 'FAILURE', ['reason' => $error['message'] ?? 'no consentRequest.id']);
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    $ok = HiuConsent::attachConsentRequestId($conn, $ourRequestId, $consentRequestId);
    $logger->logHiConsentNotify('init', $consentRequestId, $ok ? 'ACKNOWLEDGED' : 'FAILURE', ['our_request_id' => $ourRequestId]);
    error_log('[abdm-hiu-consent-on-init] attached consentRequestId=' . $consentRequestId . ' to request=' . $ourRequestId . ' ok=' . ($ok ? '1' : '0'));

    HipLinking::markWebhookProcessed($conn, $logId);
} catch (Throwable $e) {
    error_log('[abdm-hiu-consent-on-init] processing error: ' . $e->getMessage());
}

AbdmWebhookGuard::ack();
