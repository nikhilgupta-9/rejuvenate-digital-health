<?php
/**
 * ABDM Milestone 3 — Consent flow (HIU role), final step: on-fetch callback.
 *
 * PUBLIC endpoint. Own bridge sub-path:
 *   POST  <bridge>/api/v3/hiu/consent/on-fetch   (.htaccess → here)
 *
 * Delivers the full consent artefact for a consentId we fetched via
 * ConsentApi::hiuConsentFetch(). Stored as-is for the data-flow phase
 * (not yet implemented — that is what will actually request/decrypt the
 * patient's records using this artefact's hiTypes/dateRange/hip).
 *
 * Confirmed body fields (flattened by ABDM into the callback payload):
 *   status, consentId, hip, patient-id (ABHA address), purpose-*,
 *   patientReference, careContextReference, hiu, requester-*, hiTypes,
 *   permission{accessMode, dateRange, dataEraseAt, frequency}
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';       // shared raw-log
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/HiuConsent.php';
require_once dirname(__DIR__, 2) . '/lib/HiuDataFlow.php';
require_once dirname(__DIR__, 2) . '/lib/AbdmCrypto.php';
require_once dirname(__DIR__, 2) . '/lib/ConsentApi.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';

$raw               = AbdmWebhookGuard::pass('hiu_consent', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload           = json_decode($raw, true);

try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'hiu-consent-on-fetch', $raw, 'hiu_consent');
} catch (Throwable $e) {
    error_log('[abdm-hiu-consent-on-fetch] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-hiu-consent-on-fetch] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

try {
    $consentId = trim((string) ($payload['consentId'] ?? ''));

    if ($consentId === '') {
        error_log('[abdm-hiu-consent-on-fetch] missing consentId');
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    $ok = HiuConsent::applyFetchedArtefact($conn, $consentId, $payload);
    $logger->logHiConsentNotify((string) ($payload['status'] ?? ''), $consentId, $ok ? 'ACKNOWLEDGED' : 'FAILURE', [
        'hi_types' => is_array($payload['hiTypes'] ?? null) ? count($payload['hiTypes']) : 0,
    ]);
    error_log('[abdm-hiu-consent-on-fetch] artefact stored consent_tail=' . substr($consentId, -8) . ' ok=' . ($ok ? '1' : '0'));

    // M3 Phase 2 — now that we hold the full artefact, request the actual
    // records from the HIP. Never blocks acking this webhook back to ABDM.
    if ($ok) {
        try {
            $artefact = HiuConsent::findArtefactByConsentId($conn, $consentId);
            $capi = new ConsentApi();
            if ($artefact && $capi->isHiuConfigured()) {
                $parent = HiuConsent::findById($conn, (int) $artefact['hiu_consent_request_id']);
                if ($parent) {
                    $km = AbdmCrypto::generateReceiverKeyMaterial();
                    $dataReqId = HiuDataFlow::newRequestId();
                    $dataPushUrl = rtrim(BASE_URL, '/') . '/api/v3/hiu/health-information/push';

                    $rowId = HiuDataFlow::insertRequested($conn, [
                        'artefact_id'      => (int) $artefact['id'],
                        'request_id'       => $dataReqId,
                        'our_private_key'  => $km['privateKey'],
                        'our_nonce'        => $km['nonce'],
                        'date_from'        => $parent['date_from'],
                        'date_to'          => $parent['date_to'],
                    ]);

                    if ($rowId > 0) {
                        $dateFromIso = gmdate('Y-m-d\TH:i:s.000\Z', strtotime($parent['date_from']));
                        $dateToIso   = gmdate('Y-m-d\TH:i:s.000\Z', strtotime($parent['date_to']));
                        $capi->hiuDataFlowRequest($consentId, $dateFromIso, $dateToIso, $dataPushUrl, $km['keyMaterial'], $dataReqId);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[abdm-hiu-consent-on-fetch] data-flow request dispatch failed: ' . $e->getMessage());
        }
    }

    HipLinking::markWebhookProcessed($conn, $logId);
} catch (Throwable $e) {
    error_log('[abdm-hiu-consent-on-fetch] processing error: ' . $e->getMessage());
}

AbdmWebhookGuard::ack();
