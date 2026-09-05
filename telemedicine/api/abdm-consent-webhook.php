<?php
/**
 * ABDM HI Consent — consent-notify receiver (Phase A: receive + record + ack).
 *
 * PUBLIC endpoint. Registered as its own bridge sub-path:
 *   POST  <bridge>/api/v3/consent/request/hip/notify   (.htaccess rewrites here)
 *
 * UNSOLICITED callback — the CM notifies us with no pending row of ours to
 * match, so there is NO "our own requestId" gate. Layered defence via
 * AbdmWebhookGuard (POST-only, size cap, rate limit, IP allowlist, ?k= secret).
 * TODO (Phase B): verify `signature` against the CM public cert before a
 * GRANTED consent is trusted for an actual data push.
 *
 * Raw body → abdm_webhook_log (channel='consent') BEFORE parsing. Always 200.
 * Our on-notify ack echoes the inbound REQUEST-ID header as response.requestId.
 */

require_once dirname(__DIR__, 2) . '/config/connect.php';
require_once dirname(__DIR__, 2) . '/config/abdm.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';
require_once dirname(__DIR__, 2) . '/lib/HipLinking.php';       // shared raw-log
require_once dirname(__DIR__, 2) . '/lib/AbdmWebhookGuard.php';
require_once dirname(__DIR__, 2) . '/lib/HiConsent.php';
require_once dirname(__DIR__, 2) . '/lib/Abha.php';
require_once dirname(__DIR__, 2) . '/lib/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/lib/AbdmApi.php';
require_once dirname(__DIR__, 2) . '/lib/ConsentApi.php';

$raw               = AbdmWebhookGuard::pass('consent', $conn);
$incomingRequestId = AbdmWebhookGuard::requestIdHeader();
$payload           = json_decode($raw, true);

/* 0. Raw log FIRST. */
try {
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'consent-notify', $raw, 'consent');
} catch (Throwable $e) {
    error_log('[abdm-consent-webhook] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-consent-webhook] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

try {
    $consentStatus = strtoupper(trim((string) ($payload['status'] ?? '')));
    $consentId     = trim((string) ($payload['consentId'] ?? ''));

    if ($consentId === '' || !in_array($consentStatus, ['GRANTED', 'REVOKED'], true)) {
        error_log('[abdm-consent-webhook] bad payload: consentId="' . substr($consentId, -8) . '" status="' . $consentStatus . '"');
        $logger->logHiConsentNotify($consentStatus ?: 'UNKNOWN', $consentId, 'FAILURE');
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    if ($consentStatus === 'REVOKED') {
        $applied = HiConsent::markRevoked($conn, $consentId);
        $logger->logHiConsentNotify('REVOKED', $consentId, $applied ? 'SUCCESS' : 'FAILURE',
            $applied ? [] : ['note' => 'consentId unknown — nothing to revoke']);
    } else {
        // ── GRANTED ──
        // TODO (Phase B): verify $payload['signature'] against the CM public
        // cert before this consent is trusted for a data push.
        $patientAddr = trim((string) ($payload['patient'] ?? ''));
        $patientId   = null;
        if ($patientAddr !== '') {
            try {
                $hit = Abha::find($conn, $patientAddr);
                if ($hit && ($hit['entity_type'] ?? '') === 'patient') {
                    $patientId = (int) $hit['entity_id'];
                }
            } catch (Throwable $e) {
                error_log('[abdm-consent-webhook] Abha::find failed: ' . $e->getMessage());
            }
        }

        $perm    = is_array($payload['permission'] ?? null) ? $payload['permission'] : [];
        $range   = is_array($perm['dateRange'] ?? null) ? $perm['dateRange'] : [];
        $freq    = is_array($perm['frequency'] ?? null) ? $perm['frequency'] : [];
        $purpose = is_array($payload['purpose'] ?? null) ? $payload['purpose'] : [];
        $hiuId   = (string) ($payload['hiu']['id'] ?? '');

        $applied = HiConsent::upsertGranted($conn, [
            'consent_id'            => $consentId,
            'patient_id'            => $patientId,
            'abha_address'          => $patientAddr,
            'hiu_id'                => $hiuId,
            'purpose_text'          => (string) ($purpose['text'] ?? ''),
            'purpose_code'          => (string) ($purpose['code'] ?? ''),
            'hi_types'              => json_encode(array_values((array) ($payload['hiTypes'] ?? []))),
            'date_range_from'       => AbdmWebhookGuard::dt($range['from'] ?? null),
            'date_range_to'         => AbdmWebhookGuard::dt($range['to'] ?? null),
            'data_erase_at'         => AbdmWebhookGuard::dt($perm['dataEraseAt'] ?? null),
            'frequency_unit'        => (string) ($freq['unit'] ?? ''),
            'frequency_value'       => isset($freq['value']) ? (int) $freq['value'] : null,
            'frequency_repeats'     => isset($freq['repeats']) ? (int) $freq['repeats'] : null,
            'signature'             => (string) ($payload['signature'] ?? ''),
            'grant_acknowledgement' => (string) ($payload['grantAcknowledgement'] ?? ''),
            'raw_payload'           => $raw,
        ]);

        $logger->logHiConsentNotify('GRANTED', $consentId, $applied ? 'SUCCESS' : 'FAILURE', [
            'patient_resolved' => $patientId !== null,
            'hiu_id'           => $hiuId,
        ]);
    }

    HipLinking::markWebhookProcessed($conn, $logId);
    error_log('[abdm-consent-webhook] processed status=' . $consentStatus . ' consentId_tail=' . substr($consentId, -8));

    /* Acknowledge back to the CM (echoing the inbound REQUEST-ID). */
    if ($incomingRequestId !== null) {
        try {
            $capi = new ConsentApi();
            if ($capi->isConfigured()) {
                $ackErr = $applied ? null : ['code' => 'ABDM-9999', 'message' => 'consent could not be recorded'];
                $capi->consentHipOnNotify($applied ? 'OK' : 'ERRORED', $consentId, $incomingRequestId, $ackErr);
            } else {
                error_log('[abdm-consent-webhook] on-notify ack skipped — HIP not configured');
            }
        } catch (Throwable $e) {
            error_log('[abdm-consent-webhook] on-notify ack failed: ' . $e->getMessage());
        }
    }

} catch (Throwable $e) {
    error_log('[abdm-consent-webhook] processing error: ' . $e->getMessage());
    // leave processed=0 for reconciliation
}

AbdmWebhookGuard::ack();
