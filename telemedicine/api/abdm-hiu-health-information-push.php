<?php
/**
 * ABDM Milestone 3 — Data flow (HIU role), final step: the HIP's data push.
 *
 * PUBLIC endpoint — this is the dataPushUrl WE generated and sent in
 * ConsentApi::hiuDataFlowRequest(), not a fixed ABDM-dictated sub-path (same
 * pattern as how a HIU chose the dataPushUrl we received in M2's
 * abdm-hi-request-webhook.php — here we are on the receiving side instead).
 * Routed via .htaccess to:
 *   POST  <bridge>/api/v3/hiu/health-information/push
 *
 * Confirmed body (symmetric to what scripts/abdm-hi-push-worker.php itself
 * sends when WE act as HIP): { pageNumber, pageCount, transactionId,
 *   entries:[{content,media,checksum,careContextReference}],
 *   keyMaterial:{dhPublicKey:{keyValue},nonce} }
 *
 * Decrypts each entry with our stored private key (abdm_hiu_data_requests,
 * set when the request was made) + the sender's public key/nonce carried in
 * this payload, verifies the checksum, stores the plaintext FHIR bundle, and
 * notifies ABDM of the outcome via ConsentApi::hiuDataFlowNotify().
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
    $logId = HipLinking::logWebhook($conn, $incomingRequestId, 'hiu-data-push', $raw, 'hiu_consent');
} catch (Throwable $e) {
    error_log('[abdm-hiu-hi-push] logWebhook failed: ' . $e->getMessage());
    AbdmWebhookGuard::ack();
}

if (!is_array($payload)) {
    error_log('[abdm-hiu-hi-push] unparseable body');
    HipLinking::markWebhookProcessed($conn, $logId);
    AbdmWebhookGuard::ack();
}

$logger = new AuditLogger($conn);

try {
    $transactionId = trim((string) ($payload['transactionId'] ?? ''));
    $entries        = is_array($payload['entries'] ?? null) ? $payload['entries'] : [];
    $senderKm       = is_array($payload['keyMaterial'] ?? null) ? $payload['keyMaterial'] : [];
    $senderPubKeyB64 = (string) ($senderKm['dhPublicKey']['keyValue'] ?? '');
    $senderNonceB64  = (string) ($senderKm['nonce'] ?? '');

    if ($transactionId === '') {
        error_log('[abdm-hiu-hi-push] missing transactionId');
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    $dataReq = HiuDataFlow::findByTransactionId($conn, $transactionId);
    if (!$dataReq) {
        error_log('[abdm-hiu-hi-push] unknown transactionId=' . $transactionId);
        $logger->logHiDataRequest('', $transactionId, 'FAILURE', ['reason' => 'no matching data request']);
        HipLinking::markWebhookProcessed($conn, $logId);
        AbdmWebhookGuard::ack();
    }

    $artefactId = (int) $dataReq['artefact_id'];
    $artefact = null;
    $st = $conn->prepare("SELECT * FROM abdm_hiu_consent_artefacts WHERE id = ? LIMIT 1");
    $st->bind_param('i', $artefactId);
    $st->execute();
    $artefact = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    $consentId = $artefact['consent_id'] ?? '';
    $hipId     = $artefact['hip_id'] ?? (defined('ABDM_HIP_ID') ? ABDM_HIP_ID : '');

    $careContextStatuses = [];
    $overallOk = true;

    if ($senderPubKeyB64 === '' || $senderNonceB64 === '') {
        error_log('[abdm-hiu-hi-push] missing sender keyMaterial for transactionId=' . $transactionId);
        $overallOk = false;
    } else {
        foreach ($entries as $entry) {
            $careRef = (string) ($entry['careContextReference'] ?? '');
            $content = (string) ($entry['content'] ?? '');
            $checksum = (string) ($entry['checksum'] ?? '');

            if ($content === '' || md5($content) !== $checksum) {
                error_log('[abdm-hiu-hi-push] checksum mismatch for careContext=' . $careRef . ' txn=' . $transactionId);
                $careContextStatuses[] = ['careContextReference' => $careRef, 'hiStatus' => 'ERRORED', 'description' => 'Checksum mismatch'];
                $overallOk = false;
                continue;
            }

            try {
                $plaintext = AbdmCrypto::decryptBundle(
                    $content,
                    $senderPubKeyB64,
                    $senderNonceB64,
                    $dataReq['our_private_key'],
                    $dataReq['our_nonce']
                );
                HiuDataFlow::insertReceivedRecord($conn, (int) $dataReq['id'], $careRef !== '' ? $careRef : null, $plaintext, 'OK');
                $careContextStatuses[] = ['careContextReference' => $careRef, 'hiStatus' => 'OK', 'description' => 'Received and decrypted'];
            } catch (Throwable $e) {
                error_log('[abdm-hiu-hi-push] decryption failed for careContext=' . $careRef . ': ' . $e->getMessage());
                $careContextStatuses[] = ['careContextReference' => $careRef, 'hiStatus' => 'ERRORED', 'description' => 'Decryption failed'];
                $overallOk = false;
            }
        }
    }

    $finalStatus = $overallOk && $entries ? 'received' : 'failed';
    HiuDataFlow::markStatus($conn, $transactionId, $finalStatus, $overallOk ? null : 'one or more entries failed');

    $logger->logHiDataRequest($consentId, $transactionId, $overallOk ? 'ACKNOWLEDGED' : 'FAILURE', ['entries' => count($entries)]);
    error_log('[abdm-hiu-hi-push] txn=' . $transactionId . ' entries=' . count($entries) . ' status=' . $finalStatus);

    HipLinking::markWebhookProcessed($conn, $logId);

    // Tell ABDM we received (or failed) — never blocks the ack to the HIP either way.
    try {
        $capi = new ConsentApi();
        if ($capi->isHiuConfigured() && $consentId !== '') {
            $capi->hiuDataFlowNotify($consentId, $transactionId, (string) $hipId, $overallOk ? 'RECEIVED' : 'FAILED', $careContextStatuses);
        }
    } catch (Throwable $e) {
        error_log('[abdm-hiu-hi-push] notify failed: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log('[abdm-hiu-hi-push] processing error: ' . $e->getMessage());
}

AbdmWebhookGuard::ack();
