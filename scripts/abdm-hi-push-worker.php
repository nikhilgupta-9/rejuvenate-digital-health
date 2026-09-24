<?php
/**
 * ABDM Milestone 2 (Phase B) — Health Information Push Worker.
 *
 * Processes pending 'acknowledged' health data requests from HIUs:
 *   1. Fetches requested health records (prescriptions/consultations) matching consent & date range.
 *   2. Builds NDHM FHIR R4 Document Bundles using FhirBundleBuilder.
 *   3. Encrypts bundles using ABDM Fidelius crypto (Curve25519 ECDH + HKDF-SHA256 + AES-256-GCM) with HIU public key.
 *   4. POSTs encrypted payload to HIU's dataPushUrl.
 *   5. Calls ABDM Gateway hiNotify (/data-flow/v3/health-information/notify) to confirm transfer status.
 *   6. Updates abha_hi_requests status to 'delivered' or 'failed'.
 *
 * Usage:
 *   CLI: php scripts/abdm-hi-push-worker.php [--limit=10]
 *   Auto-include from webhook if immediate push desired.
 */

require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';
require_once dirname(__DIR__) . '/lib/AbdmCrypto.php';
require_once dirname(__DIR__) . '/lib/FhirBundleBuilder.php';
require_once dirname(__DIR__) . '/lib/ConsentApi.php';
require_once dirname(__DIR__) . '/lib/AuditLogger.php';

$isCli = (php_sapi_name() === 'cli');
$limit = 10;
if ($isCli && isset($argv)) {
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--limit=')) {
            $limit = max(1, (int)substr($arg, 8));
        }
    }
}

$logger = new AuditLogger($conn);
$capi = new ConsentApi();

// Fetch acknowledged requests
$query = "
    SELECT r.*, c.patient_id, c.abha_address as consent_abha_address,
           c.date_range_from as c_from, c.date_range_to as c_to
    FROM abha_hi_requests r
    JOIN abha_consents c ON r.consent_id = c.consent_id
    WHERE r.status = 'acknowledged'
    ORDER BY r.id ASC
    LIMIT ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $limit);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$processed = 0;
$delivered = 0;
$failed    = 0;

foreach ($requests as $req) {
    $processed++;
    $reqId = (int)$req['id'];
    $consentId = $req['consent_id'];
    $txnId = $req['transaction_id'] ?: '';
    $dataPushUrl = trim($req['data_push_url'] ?? '');
    $kmRaw = json_decode($req['key_material'] ?? '{}', true) ?: [];

    if ($isCli) {
        echo "[ABDM-HIP] Processing request #{$reqId} (Txn: {$txnId}, Consent: " . substr($consentId, 0, 8) . "...)\n";
    }

    if (empty($dataPushUrl)) {
        markHiFailed($conn, $reqId, 'Missing dataPushUrl', $consentId, $txnId, $capi, $logger);
        $failed++;
        continue;
    }

    $hiuPubKeyB64 = $kmRaw['dhPublicKey']['keyValue'] ?? '';
    $hiuNonceB64  = $kmRaw['nonce'] ?? '';

    if (empty($hiuPubKeyB64) || empty($hiuNonceB64)) {
        markHiFailed($conn, $reqId, 'Missing HIU keyMaterial (publicKey or nonce)', $consentId, $txnId, $capi, $logger);
        $failed++;
        continue;
    }

    // Resolve Patient ID
    $patientId = (int)($req['patient_id'] ?? 0);
    if (!$patientId && !empty($req['consent_abha_address'])) {
        $pStmt = $conn->prepare("SELECT id FROM users WHERE abha_address = ? OR abha_id = ? LIMIT 1");
        $pStmt->bind_param('ss', $req['consent_abha_address'], $req['consent_abha_address']);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();
        if ($pRow) {
            $patientId = (int)$pRow['id'];
        }
    }

    if (!$patientId) {
        markHiFailed($conn, $reqId, 'Could not resolve patient for consent', $consentId, $txnId, $capi, $logger);
        $failed++;
        continue;
    }

    // Fetch patient info
    $pInfoStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $pInfoStmt->bind_param('i', $patientId);
    $pInfoStmt->execute();
    $patient = $pInfoStmt->get_result()->fetch_assoc();
    $pInfoStmt->close();

    // Determine effective date range
    $from = $req['date_range_from'] ?: ($req['c_from'] ?: '1970-01-01 00:00:00');
    $to   = $req['date_range_to'] ?: ($req['c_to'] ?: '2099-12-31 23:59:59');

    // Fetch prescriptions for this patient in range
    $rxStmt = $conn->prepare("
        SELECT * FROM prescriptions
        WHERE patient_id = ? AND visit_date >= DATE(?) AND visit_date <= DATE(?)
        ORDER BY visit_date DESC
    ");
    $rxStmt->bind_param('iss', $patientId, $from, $to);
    $rxStmt->execute();
    $prescriptions = $rxStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rxStmt->close();

    $entries = [];
    $careContextStatuses = [];
    $lastSenderKm = null;

    $org = [
        'name'   => defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health',
        'hfr_id' => defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : 'IN0810000001',
    ];

    foreach ($prescriptions as $rx) {
        // Fetch doctor info
        $docId = (int)$rx['doctor_id'];
        $docStmt = $conn->prepare("SELECT * FROM doctors WHERE id = ? LIMIT 1");
        $docStmt->bind_param('i', $docId);
        $docStmt->execute();
        $doctor = $docStmt->get_result()->fetch_assoc() ?: ['id' => $docId, 'name' => 'Doctor', 'degrees' => 'MBBS'];
        $docStmt->close();

        // 1. Build FHIR Bundle
        $fhirBundle = FhirBundleBuilder::buildPrescriptionBundle($rx, $patient, $doctor, $org);
        $fhirJson = json_encode($fhirBundle, JSON_UNESCAPED_SLASHES);

        // 2. Encrypt Bundle via Fidelius
        try {
            $enc = AbdmCrypto::encryptBundle($fhirJson, $hiuPubKeyB64, $hiuNonceB64);
            $lastSenderKm = $enc['keyMaterial'];

            $entries[] = [
                'content'              => $enc['encryptedData'],
                'media'                => 'application/fhir+json',
                'checksum'             => $enc['checksum'],
                'careContextReference' => $rx['care_context_ref'],
            ];

            $careContextStatuses[] = [
                'careContextReference' => $rx['care_context_ref'],
                'hiStatus'             => 'DELIVERED',
                'description'          => 'Delivered successfully',
            ];
        } catch (Throwable $e) {
            error_log("[ABDM-HIP] Encryption error for prescription #{$rx['id']}: " . $e->getMessage());
        }
    }

    if (empty($entries)) {
        // No records found in date range — notify gateway with empty transfer per spec
        if ($capi->isConfigured()) {
            $capi->hiNotify($consentId, $txnId, 'TRANSFERRED', []);
        }
        $upd = $conn->prepare("UPDATE abha_hi_requests SET status = 'delivered', error_detail = 'No matching records in date range' WHERE id = ?");
        $upd->bind_param('i', $reqId);
        $upd->execute();
        $upd->close();
        $delivered++;
        continue;
    }

    // 3. Assemble Push Payload
    $pushPayload = [
        'pageNumber'    => 1,
        'pageCount'     => 1,
        'transactionId' => $txnId,
        'entries'       => $entries,
        'keyMaterial'   => $lastSenderKm,
    ];

    // 4. POST to dataPushUrl
    $ch = curl_init($dataPushUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($pushPayload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'REQUEST-ID: ' . uuidV4(),
            'TIMESTAMP: ' . gmdate('Y-m-d\TH:i:s.000\Z'),
        ],
        CURLOPT_SSL_VERIFYPEER => defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true,
    ]);

    $pushResponse = curl_exec($ch);
    $pushHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($pushHttp >= 200 && $pushHttp < 300) {
        // Transfer successful!
        if ($capi->isConfigured()) {
            $capi->hiNotify($consentId, $txnId, 'TRANSFERRED', $careContextStatuses);
        }

        $upd = $conn->prepare("UPDATE abha_hi_requests SET status = 'delivered', error_detail = NULL WHERE id = ?");
        $upd->bind_param('i', $reqId);
        $upd->execute();
        $upd->close();

        $delivered++;
        if ($isCli) {
            echo "[ABDM-HIP] Successfully delivered {$reqId} ({$pushHttp})\n";
        }
    } else {
        $errMsg = "DataPush failed: HTTP {$pushHttp} " . ($curlErr ? "({$curlErr})" : substr((string)$pushResponse, 0, 100));
        markHiFailed($conn, $reqId, $errMsg, $consentId, $txnId, $capi, $logger);
        $failed++;
        if ($isCli) {
            echo "[ABDM-HIP] Push failed for #{$reqId}: {$errMsg}\n";
        }
    }
}

if ($isCli) {
    echo "Done. Processed: {$processed}, Delivered: {$delivered}, Failed: {$failed}\n";
}

function markHiFailed(mysqli $conn, int $reqId, string $err, string $consentId, string $txnId, ConsentApi $capi, AuditLogger $logger): void
{
    error_log("[abdm-hi-push-worker] Request #{$reqId} failed: {$err}");
    $upd = $conn->prepare("UPDATE abha_hi_requests SET status = 'failed', error_detail = ? WHERE id = ?");
    $trunc = substr($err, 0, 250);
    $upd->bind_param('si', $trunc, $reqId);
    $upd->execute();
    $upd->close();

    if ($capi->isConfigured() && $txnId) {
        $capi->hiNotify($consentId, $txnId, 'FAILED', [], ['code' => 'ABDM-1070', 'message' => $trunc]);
    }
}

function uuidV4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}
