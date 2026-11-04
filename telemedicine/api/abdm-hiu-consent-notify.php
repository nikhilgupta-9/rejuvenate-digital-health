<?php
/**
 * ABDM Milestone 3 — Consent flow (HIU role), step 3: notify callback.
 *
 * PUBLIC endpoint. Own bridge sub-path:
 *   POST  <bridge>/api/v3/hiu/consent/request/notify   (.htaccess → here)
 *
 * ABDM calls this once the patient has GRANTED / DENIED / REVOKED the
 * consent request. On GRANTED, one consent artefact id is delivered per
 * HIP the patient approved against — each is recorded 'pending_fetch' so
 * ConsentApi::hiuConsentFetch() can be called for each one next.
 *
 * Confirmed body: { notification:{consentRequestId, status, reason,
 *   consentArtefacts:[{id}]} }
 *
 * We must always ack back via ConsentApi::hiuConsentOnNotify() — that is
 * step 4 of this flow, done here right after processing.
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';       // shared raw-log
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/HiuConsent.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/lib/ConsentApi.php';

$raw               = AbdmWebhookGuard::pass('hiu_consent', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload           = json_decode($raw, true);

try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'hiu-consent-notify', $raw, 'hiu_consent');
} catch (Throwable $e) {
    error_log('[abdm-hiu-consent-notify] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-hiu-consent-notify] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

/** Ack the gateway. Never blocks the response to ABDM either way. */
function hiu_consent_notify_ack(?string $reqId, string $status, string $consentId): void
{
    if ($reqId === null) {
        return;
    }
    try {
        $capi = new ConsentApi();
        if (!$capi->isHiuConfigured()) {
            error_log('[abdm-hiu-consent-notify] on-notify ack skipped — HIU not configured');
            return;
        }
        $capi->hiuConsentOnNotify($status, $consentId, $reqId);
    } catch (Throwable $e) {
        error_log('[abdm-hiu-consent-notify] on-notify ack failed: ' . $e->getMessage());
    }
}

try {
    $n                 = is_array($payload['notification'] ?? null) ? $payload['notification'] : [];
    $consentRequestId  = trim((string) ($n['consentRequestId'] ?? ''));
    $status            = trim((string) ($n['status'] ?? ''));
    $reason            = isset($n['reason']) && $n['reason'] !== null ? trim((string) $n['reason']) : null;
    $artefacts         = is_array($n['consentArtefacts'] ?? null) ? $n['consentArtefacts'] : [];

    if ($consentRequestId === '') {
        error_log('[abdm-hiu-consent-notify] missing notification.consentRequestId');
        $logger->logHiConsentNotify($status, '', 'FAILURE', ['reason' => 'missing consentRequestId']);
        HipLinking::markWebhookProcessed($conn, $logId);
        hiu_consent_notify_ack($incomingRequestId, 'OK', '');
        AbdmWebhookGuard::ack();
    }

    $row = HiuConsent::findByConsentRequestId($conn, $consentRequestId);
    if (!$row) {
        error_log('[abdm-hiu-consent-notify] unknown consentRequestId=' . $consentRequestId);
        $logger->logHiConsentNotify($status, $consentRequestId, 'FAILURE', ['reason' => 'no matching request']);
        HipLinking::markWebhookProcessed($conn, $logId);
        hiu_consent_notify_ack($incomingRequestId, 'OK', '');
        AbdmWebhookGuard::ack();
    }

    HiuConsent::applyNotifyStatus($conn, $consentRequestId, $status, $reason);

    if (strtoupper($status) === 'GRANTED' && $artefacts) {
        $consentIds = array_map(fn($a) => $a['id'] ?? '', $artefacts);
        HiuConsent::insertArtefactsPending($conn, (int) $row['id'], $consentIds);

        // Fire the fetch request for each artefact right away (best effort —
        // the on-fetch callback is what actually records the result).
        $capi = new ConsentApi();
        if ($capi->isHiuConfigured()) {
            foreach ($consentIds as $cid) {
                if (trim((string) $cid) === '') continue;
                try {
                    $capi->hiuConsentFetch($cid, HiuConsent::newRequestId());
                } catch (Throwable $e) {
                    error_log('[abdm-hiu-consent-notify] fetch dispatch failed for ' . $cid . ': ' . $e->getMessage());
                }
            }
        }
    }

    $logger->logHiConsentNotify($status, $consentRequestId, 'ACKNOWLEDGED', ['artefact_count' => count($artefacts)]);
    error_log('[abdm-hiu-consent-notify] consentRequestId=' . $consentRequestId . ' status=' . $status . ' artefacts=' . count($artefacts));

    HipLinking::markWebhookProcessed($conn, $logId);
    hiu_consent_notify_ack($incomingRequestId, 'OK', $artefacts[0]['id'] ?? '');
} catch (Throwable $e) {
    error_log('[abdm-hiu-consent-notify] processing error: ' . $e->getMessage());
}

AbdmWebhookGuard::ack();
