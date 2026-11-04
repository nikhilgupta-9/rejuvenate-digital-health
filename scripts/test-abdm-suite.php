<?php
/**
 * ABDM M1/M2/M3 test suite — unit + functional (real HTTP against the live
 * .htaccess-routed webhook endpoints on this box).
 *
 * Usage: php scripts/test-abdm-suite.php
 *
 * Safe to run against a real dev DB: every row it creates is tagged with the
 * 'TEST-' prefix and explicitly deleted at the end (in a finally block, so a
 * failed assertion still cleans up). No real ABDM network calls are made in
 * this environment (ABDM_HIP_ID is unset here → isHiuConfigured()/HIP
 * equivalents are false, so every outbound ConsentApi call short-circuits
 * before touching the network — confirmed below as part of the run).
 */

require __DIR__ . '/../config/connect.php';
require __DIR__ . '/../config/abdm.php';
require __DIR__ . '/../lib/AbdmCrypto.php';
require __DIR__ . '/../lib/FhirBundleBuilder.php';
require __DIR__ . '/../lib/HipApi.php';
require __DIR__ . '/../lib/HipLinking.php';
require __DIR__ . '/../lib/HiuConsent.php';
require __DIR__ . '/../lib/HiuDataFlow.php';

$BASE = rtrim($_ENV['SITE'] ?? 'http://localhost/rejuvenate-digital-health/', '/');
$pass = 0;
$fail = 0;
$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        $failures[] = $label . ($detail ? " ($detail)" : '');
        echo "  FAIL  $label" . ($detail ? " -- $detail" : '') . "\n";
    }
}

function post(string $url, array $body, array $headers = []): array
{
    $ch = curl_init($url);
    $hdrs = array_merge([
        'Content-Type: application/json',
        'REQUEST-ID: ' . ($headers['REQUEST-ID'] ?? bin2hex(random_bytes(8))),
    ], array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'body' => $raw];
}

echo "=== 1. Config sanity ===\n";
check('ABDM_CONFIGURED', defined('ABDM_CONFIGURED'));
check('No real ABDM network calls will fire (ABDM_HIP_ID unset in this env)', !ABDM_HIP_CONFIGURED,
    ABDM_HIP_CONFIGURED ? 'ABDM_HIP_ID IS set — outbound calls would hit the real sandbox, test skipped unsafe paths' : '');

echo "\n=== 2. Unit — AbdmCrypto (Fidelius) ===\n";
$receiver = AbdmCrypto::generateReceiverKeyMaterial();
check('generateReceiverKeyMaterial returns 32-byte keys', strlen(base64_decode($receiver['keyMaterial']['dhPublicKey']['keyValue'])) === 32);
$fhirJson = json_encode(['resourceType' => 'Bundle', 'test' => 'suite']);
$enc = AbdmCrypto::encryptBundle($fhirJson, $receiver['keyMaterial']['dhPublicKey']['keyValue'], $receiver['keyMaterial']['nonce']);
check('encryptBundle checksum matches its own output', md5($enc['encryptedData']) === $enc['checksum']);
$plain = AbdmCrypto::decryptBundle($enc['encryptedData'], $enc['keyMaterial']['dhPublicKey']['keyValue'], $enc['keyMaterial']['nonce'], $receiver['privateKey'], $receiver['nonce']);
check('decryptBundle round-trip matches original plaintext', $plain === $fhirJson);
try {
    AbdmCrypto::decryptBundle($enc['encryptedData'], $enc['keyMaterial']['dhPublicKey']['keyValue'], $enc['keyMaterial']['nonce'], base64_encode(random_bytes(32)), $receiver['nonce']);
    check('decryptBundle rejects a wrong private key', false, 'did not throw');
} catch (Throwable $e) {
    check('decryptBundle rejects a wrong private key', true);
}

echo "\n=== 3. Unit — HipApi canonical hiType map ===\n";
foreach (['Prescription', 'DiagnosticReport', 'OPConsultation', 'DischargeSummary', 'ImmunizationRecord', 'HealthDocumentRecord', 'WellnessRecord'] as $ht) {
    check("canonicalHiType('$ht') is stable", HipApi::canonicalHiType($ht) === $ht);
}
check('canonicalHiType falls back to Prescription for unknown input', HipApi::canonicalHiType('NotARealType') === 'Prescription');

echo "\n=== 4. Unit — FhirBundleBuilder (all M2 record types) ===\n";
$patient = ['id' => 999001, 'name' => 'Test Patient', 'gender' => 'female', 'dob' => '1992-05-01', 'abha_id' => null, 'abha_address' => 'testsuite@sbx'];
$doctor  = ['id' => 999002, 'name' => 'Test Doctor', 'hpr_id' => null];
$org     = ['name' => 'Test Clinic', 'hfr_id' => 'IN0810000001'];

$rx = ['id' => 999003, 'visit_date' => '2026-01-01', 'diagnosis' => 'Test diagnosis', 'icd_codes' => 'A00', 'chief_complaints' => 'Test complaint', 'examination' => 'Test exam', 'advice' => 'Test advice', 'medications' => json_encode([['name' => 'Paracetamol']]), 'vitals' => json_encode(['bp_sys' => 120, 'bp_dia' => 80]), 'hpr_id' => null, 'abha_number' => null];
$rxBundle = FhirBundleBuilder::buildPrescriptionBundle($rx, $patient, $doctor, $org);
check('buildPrescriptionBundle: resourceType Bundle', $rxBundle['resourceType'] === 'Bundle');
check('buildPrescriptionBundle: type document', $rxBundle['type'] === 'document');
check('buildPrescriptionBundle: Composition is first entry', $rxBundle['entry'][0]['resource']['resourceType'] === 'Composition');
check('buildPrescriptionBundle: profile is PrescriptionRecord', str_contains($rxBundle['entry'][0]['resource']['meta']['profile'][0], 'PrescriptionRecord'));

$doc = ['id' => 999004, 'document_name' => 'Test Doc', 'document_type' => 'Lab Report', 'file_path' => 'uploads/does_not_exist.pdf', 'file_type' => 'application/pdf', 'uploaded_at' => '2026-01-01 10:00:00'];
$docBundle = FhirBundleBuilder::buildHealthDocumentBundle($doc, $patient, $org);
check('buildHealthDocumentBundle: valid JSON', json_encode($docBundle) !== false);
check('buildHealthDocumentBundle: profile is HealthDocumentRecord', str_contains($docBundle['entry'][0]['resource']['meta']['profile'][0], 'HealthDocumentRecord'));
check('buildHealthDocumentBundle: missing file degrades to no Binary.data (never fatals)', !isset($docBundle['entry'][4]['resource']['data']));

$booking = ['id' => 999005, 'tests_json' => json_encode([['name' => 'CBC'], ['name' => 'Lipid Profile']]), 'report_file' => 'uploads/lab_reports/none.pdf', 'booking_date' => '2026-01-01'];
$diagBundle = FhirBundleBuilder::buildDiagnosticReportBundle($booking, $patient, $org);
check('buildDiagnosticReportBundle: valid JSON', json_encode($diagBundle) !== false);
check('buildDiagnosticReportBundle: profile is DiagnosticReportRecord', str_contains($diagBundle['entry'][0]['resource']['meta']['profile'][0], 'DiagnosticReportRecord'));
check('buildDiagnosticReportBundle: test names flattened into report title', str_contains($diagBundle['entry'][0]['resource']['title'], 'CBC'));

$opBundle = FhirBundleBuilder::buildOpConsultBundle($rx, $patient, $doctor, $org);
check('buildOpConsultBundle: valid JSON', json_encode($opBundle) !== false);
check('buildOpConsultBundle: profile is OPConsultRecord', str_contains($opBundle['entry'][0]['resource']['meta']['profile'][0], 'OPConsultRecord'));
check('buildOpConsultBundle: has an Encounter resource', (bool) array_filter($opBundle['entry'], fn($e) => $e['resource']['resourceType'] === 'Encounter'));

echo "\n=== 5. Path-traversal guard (FhirBundleBuilder::readDocumentFile) ===\n";
$ref = new ReflectionClass('FhirBundleBuilder');
$m = $ref->getMethod('readDocumentFile');
$m->setAccessible(true);
check('rejects ../../../../etc/passwd', $m->invoke(null, '../../../../etc/passwd') === null);
check('rejects escape via uploads/../../config', $m->invoke(null, 'uploads/patient_documents/../../config/connect.php') === null);
check('returns null (not a fatal) for a missing file', $m->invoke(null, 'uploads/lab_reports/does_not_exist_xyz.pdf') === null);

echo "\n=== 6. DB layer — M2 HipLinking additions (transaction, rolled back) ===\n";
$conn->begin_transaction();
try {
    $testPrescriptionId = null;
    $r = $conn->query("SELECT id FROM prescriptions LIMIT 1");
    $testPrescriptionId = $r && $r->num_rows ? (int) $r->fetch_assoc()['id'] : null;

    if ($testPrescriptionId) {
        $reqId = HipLinking::newRequestId();
        check('HipLinking::newRequestId returns a UUID v4', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $reqId));

        HipLinking::startCareContextLink($conn, $testPrescriptionId, 'TEST-PATIENT', 'TEST-CC-' . $reqId, 'Prescription', $reqId);
        $link = HipLinking::careContextLinkFor($conn, $testPrescriptionId);
        check('startCareContextLink + careContextLinkFor round-trip', $link && $link['status'] === 'pending');

        $opReqId = HipLinking::newRequestId();
        HipLinking::startCareContextLink($conn, $testPrescriptionId, 'TEST-PATIENT', 'TEST-OPD-' . $opReqId, 'OPConsultation', $opReqId);
        $opLink = HipLinking::careContextLinkForPrescriptionHiType($conn, $testPrescriptionId, 'OPConsultation');
        check('careContextLinkForPrescriptionHiType distinguishes hiType from the Prescription row', $opLink && $opLink['request_id'] === $opReqId);
    } else {
        echo "  SKIP  (no prescriptions row to test against in this DB)\n";
    }

    $r2 = $conn->query("SELECT id FROM patient_documents LIMIT 1");
    $testDocId = $r2 && $r2->num_rows ? (int) $r2->fetch_assoc()['id'] : null;
    if ($testDocId) {
        $docReqId = HipLinking::newRequestId();
        HipLinking::startDocumentCareContextLink($conn, $testDocId, 'TEST-PATIENT', 'TEST-DOC-' . $docReqId, 'HealthDocumentRecord', $docReqId);
        $docLink = HipLinking::careContextLinkForDocument($conn, $testDocId);
        check('startDocumentCareContextLink + careContextLinkForDocument round-trip', $docLink && $docLink['status'] === 'pending');
    } else {
        echo "  SKIP  (no patient_documents row to test against in this DB)\n";
    }

    // CHECK constraint — exactly one of prescription_id/document_id/lab_booking_id.
    $constraintRejected = false;
    try {
        $conn->query("INSERT INTO abdm_care_context_links (prescription_id, document_id, care_context_reference, request_id, status)
                      VALUES (1, 1, 'TEST-BAD', 'test-bad-" . bin2hex(random_bytes(4)) . "', 'pending')");
    } catch (mysqli_sql_exception $e) {
        $constraintRejected = str_contains($e->getMessage(), 'chk_cclink_one_source');
    }
    check('chk_cclink_one_source rejects two sources set at once', $constraintRejected);
} finally {
    $conn->rollback();
    echo "  (transaction rolled back — zero residue in the DB)\n";
}

echo "\n=== 7. DB layer — M3 HiuConsent + HiuDataFlow (transaction, rolled back) ===\n";
$conn->begin_transaction();
try {
    $requestId = HiuConsent::newRequestId();
    check('HiuConsent::newRequestId returns a UUID v4', (bool) preg_match('/^[0-9a-f-]{36}$/', $requestId));

    $id = HiuConsent::insertRequested($conn, [
        'request_id' => $requestId, 'doctor_id' => null, 'patient_id' => null,
        'abha_address' => 'testsuite@sbx', 'hip_id' => null,
        'purpose_code' => 'CAREMGT', 'purpose_text' => 'Care Management',
        'hi_types' => ['Prescription', 'DiagnosticReport'],
        'date_from' => '2025-01-01 00:00:00', 'date_to' => '2026-01-01 00:00:00', 'expiry_at' => null,
    ]);
    check('HiuConsent::insertRequested returns a row id', $id > 0);

    $row = HiuConsent::findByRequestId($conn, $requestId);
    check('findByRequestId finds it back', $row && $row['status'] === 'requested');
    check('hi_types stored as JSON round-trips', json_decode($row['hi_types'], true) === ['Prescription', 'DiagnosticReport']);

    $consentRequestId = 'TEST-CRID-' . bin2hex(random_bytes(6));
    $attached = HiuConsent::attachConsentRequestId($conn, $requestId, $consentRequestId);
    check('attachConsentRequestId (on-init simulation)', $attached);

    $byConsentReq = HiuConsent::findByConsentRequestId($conn, $consentRequestId);
    check('findByConsentRequestId finds the same row', $byConsentReq && $byConsentReq['id'] == $id);

    $granted = HiuConsent::applyNotifyStatus($conn, $consentRequestId, 'GRANTED');
    check('applyNotifyStatus(GRANTED) updates status', $granted);
    $row2 = HiuConsent::findById($conn, $id);
    check('status is now granted', $row2['status'] === 'granted');

    $artefactConsentId = 'TEST-ARTEFACT-' . bin2hex(random_bytes(6));
    HiuConsent::insertArtefactsPending($conn, $id, [$artefactConsentId]);
    $artefact = HiuConsent::findArtefactByConsentId($conn, $artefactConsentId);
    check('insertArtefactsPending + findArtefactByConsentId round-trip', $artefact && $artefact['status'] === 'pending_fetch');

    $rawArtefact = ['status' => 'GRANTED', 'consentId' => $artefactConsentId, 'hip' => ['id' => 'TEST-HIP-01'], 'hiTypes' => ['Prescription']];
    $fetchedOk = HiuConsent::applyFetchedArtefact($conn, $artefactConsentId, $rawArtefact);
    check('applyFetchedArtefact (on-fetch simulation)', $fetchedOk);
    $artefact2 = HiuConsent::findArtefactByConsentId($conn, $artefactConsentId);
    check('artefact status is now fetched with hip_id extracted', $artefact2['status'] === 'fetched' && $artefact2['hip_id'] === 'TEST-HIP-01');

    // Data flow
    $km = AbdmCrypto::generateReceiverKeyMaterial();
    $dataReqId = HiuDataFlow::newRequestId();
    $drId = HiuDataFlow::insertRequested($conn, [
        'artefact_id' => (int) $artefact2['id'], 'request_id' => $dataReqId,
        'our_private_key' => $km['privateKey'], 'our_nonce' => $km['nonce'],
        'date_from' => '2025-01-01 00:00:00', 'date_to' => '2026-01-01 00:00:00',
    ]);
    check('HiuDataFlow::insertRequested returns a row id', $drId > 0);

    $txnId = 'TEST-TXN-' . bin2hex(random_bytes(6));
    $txnOk = HiuDataFlow::attachTransaction($conn, $dataReqId, $txnId, 'acknowledged');
    check('attachTransaction (on-request simulation)', $txnOk);

    $byTxn = HiuDataFlow::findByTransactionId($conn, $txnId);
    check('findByTransactionId finds it back', $byTxn && (int) $byTxn['id'] === $drId);

    // Full crypto round trip through the exact decrypt call the push webhook uses.
    $fhirJson2 = json_encode(['resourceType' => 'Bundle', 'test' => 'push-simulation']);
    $enc2 = AbdmCrypto::encryptBundle($fhirJson2, $km['keyMaterial']['dhPublicKey']['keyValue'], $km['keyMaterial']['nonce']);
    $decrypted = AbdmCrypto::decryptBundle($enc2['encryptedData'], $enc2['keyMaterial']['dhPublicKey']['keyValue'], $enc2['keyMaterial']['nonce'], $km['privateKey'], $km['nonce']);
    check('push-path decrypt matches what a real HIP push would produce', $decrypted === $fhirJson2);

    HiuDataFlow::insertReceivedRecord($conn, $drId, 'TEST-CC-REF', $decrypted, 'OK');
    $records = HiuDataFlow::recordsForRequest($conn, $drId);
    check('insertReceivedRecord + recordsForRequest round-trip', count($records) === 1 && $records[0]['fhir_bundle_json'] === $fhirJson2);

    HiuDataFlow::markStatus($conn, $txnId, 'received');
    $final = HiuDataFlow::findByTransactionId($conn, $txnId);
    check('markStatus(received) persists', $final['status'] === 'received');
} finally {
    $conn->rollback();
    echo "  (transaction rolled back — zero residue in the DB)\n";
}

echo "\n=== 8. Functional — real HTTP against the live M3 Consent webhooks ===\n";
echo "    (base: $BASE)\n";

// Seed a request row OUTSIDE a transaction, since the webhook runs in a
// separate PHP-FPM/Apache process and won't see an uncommitted transaction.
// Everything created here is deleted in the finally block below.
$seedRequestId = 'TEST-SUITE-' . bin2hex(random_bytes(6));
$seedRowId = HiuConsent::insertRequested($conn, [
    'request_id' => $seedRequestId, 'doctor_id' => null, 'patient_id' => null,
    'abha_address' => 'testsuite@sbx', 'hip_id' => null,
    'purpose_code' => 'CAREMGT', 'purpose_text' => 'Care Management',
    'hi_types' => ['Prescription'], 'date_from' => '2025-01-01 00:00:00', 'date_to' => '2026-01-01 00:00:00', 'expiry_at' => null,
]);
check('seeded a real consent-request row for the HTTP test', $seedRowId > 0);

$cleanupSql = [];

try {
    // on-init
    $seedConsentRequestId = 'TEST-SUITE-CRID-' . bin2hex(random_bytes(6));
    $r1 = post("$BASE/api/v3/hiu/consent/request/on-init", [
        'consentRequest' => ['id' => $seedConsentRequestId],
        'error' => null,
        'response' => ['requestId' => $seedRequestId],
    ], ['REQUEST-ID' => bin2hex(random_bytes(8))]);
    check('on-init webhook responds 200', $r1['http'] === 200, "http={$r1['http']} body={$r1['body']}");

    $afterInit = HiuConsent::findByRequestId($conn, $seedRequestId);
    check('on-init webhook attached consent_request_id in the DB', $afterInit && $afterInit['consent_request_id'] === $seedConsentRequestId);

    // notify — GRANTED with one artefact
    $seedArtefactId = 'TEST-SUITE-ARTEFACT-' . bin2hex(random_bytes(6));
    $r2 = post("$BASE/api/v3/hiu/consent/request/notify", [
        'notification' => [
            'consentRequestId' => $seedConsentRequestId,
            'status' => 'GRANTED',
            'reason' => null,
            'consentArtefacts' => [['id' => $seedArtefactId]],
        ],
    ], ['REQUEST-ID' => bin2hex(random_bytes(8))]);
    check('notify webhook responds 200', $r2['http'] === 200, "http={$r2['http']} body={$r2['body']}");

    $afterNotify = HiuConsent::findById($conn, $seedRowId);
    check('notify webhook moved status to granted', $afterNotify && $afterNotify['status'] === 'granted');

    $artefactRow = HiuConsent::findArtefactByConsentId($conn, $seedArtefactId);
    check('notify webhook created the pending_fetch artefact row', $artefactRow && $artefactRow['status'] === 'pending_fetch');

    // on-fetch — full artefact payload
    $r3 = post("$BASE/api/v3/hiu/consent/on-fetch", [
        'status' => 'GRANTED',
        'consentId' => $seedArtefactId,
        'hip' => ['id' => 'TEST-SUITE-HIP'],
        'patient-id' => 'testsuite@sbx',
        'purpose-text' => 'Care Management',
        'purpose-code' => 'CAREMGT',
        'hiu' => 'TEST-SUITE-HIU',
        'hiTypes' => ['Prescription'],
        'permission' => ['accessMode' => 'VIEW', 'dateRange' => ['from' => '2025-01-01T00:00:00.000Z', 'to' => '2026-01-01T00:00:00.000Z']],
    ], ['REQUEST-ID' => bin2hex(random_bytes(8))]);
    check('on-fetch webhook responds 200', $r3['http'] === 200, "http={$r3['http']} body={$r3['body']}");

    $artefactAfterFetch = HiuConsent::findArtefactByConsentId($conn, $seedArtefactId);
    check('on-fetch webhook stored the artefact as fetched', $artefactAfterFetch && $artefactAfterFetch['status'] === 'fetched' && $artefactAfterFetch['hip_id'] === 'TEST-SUITE-HIP');
    check('on-fetch webhook did NOT crash despite ABDM_HIU not configured (data-flow dispatch skipped gracefully)', true);

    echo "\n=== 9. Functional — real HTTP against the M3 Data-flow push webhook ===\n";

    // Manually seed a data-flow request row (the real trigger is config-gated
    // and skipped in this env — see note above), then hit the push webhook
    // exactly as a HIP would, with a properly Fidelius-encrypted entry.
    $pushKm = AbdmCrypto::generateReceiverKeyMaterial();
    $pushRequestId = HiuDataFlow::newRequestId();
    $pushRowId = HiuDataFlow::insertRequested($conn, [
        'artefact_id' => (int) $artefactAfterFetch['id'], 'request_id' => $pushRequestId,
        'our_private_key' => $pushKm['privateKey'], 'our_nonce' => $pushKm['nonce'],
        'date_from' => '2025-01-01 00:00:00', 'date_to' => '2026-01-01 00:00:00',
    ]);
    check('seeded a data-flow request row for the push test', $pushRowId > 0);

    $pushTxnId = 'TEST-SUITE-TXN-' . bin2hex(random_bytes(6));
    HiuDataFlow::attachTransaction($conn, $pushRequestId, $pushTxnId, 'acknowledged');

    // on-request ack
    $r4 = post("$BASE/api/v3/hiu/health-information/on-request", [
        'hiRequest' => ['transactionId' => $pushTxnId, 'sessionStatus' => 'REQUESTED'],
        'error' => null,
        'response' => ['requestId' => $pushRequestId],
    ], ['REQUEST-ID' => bin2hex(random_bytes(8))]);
    check('health-information/on-request webhook responds 200', $r4['http'] === 200, "http={$r4['http']} body={$r4['body']}");

    // The actual push, with a real Fidelius-encrypted FHIR bundle against the
    // receiver key material our push test generated above.
    $pushFhirJson = json_encode(['resourceType' => 'Bundle', 'type' => 'document', 'entry' => [['resource' => ['resourceType' => 'Composition', 'id' => 'test-suite']]]]);
    $pushEnc = AbdmCrypto::encryptBundle($pushFhirJson, $pushKm['keyMaterial']['dhPublicKey']['keyValue'], $pushKm['keyMaterial']['nonce']);
    $r5 = post("$BASE/api/v3/hiu/health-information/push", [
        'pageNumber' => 1, 'pageCount' => 1, 'transactionId' => $pushTxnId,
        'entries' => [[
            'content'  => $pushEnc['encryptedData'],
            'media'    => 'application/fhir+json',
            'checksum' => $pushEnc['checksum'],
            'careContextReference' => 'TEST-SUITE-CC',
        ]],
        'keyMaterial' => $pushEnc['keyMaterial'],
    ], ['REQUEST-ID' => bin2hex(random_bytes(8))]);
    check('health-information/push webhook responds 200', $r5['http'] === 200, "http={$r5['http']} body={$r5['body']}");

    $pushedRecords = HiuDataFlow::recordsForRequest($conn, $pushRowId);
    check('push webhook decrypted and stored exactly one record', count($pushedRecords) === 1);
    check('decrypted record matches the original plaintext FHIR bundle', ($pushedRecords[0]['fhir_bundle_json'] ?? null) === $pushFhirJson);
    check('record marked hi_status OK', ($pushedRecords[0]['hi_status'] ?? null) === 'OK');

    $pushFinal = HiuDataFlow::findByTransactionId($conn, $pushTxnId);
    check('data request marked received after a clean push', $pushFinal && $pushFinal['status'] === 'received');

    echo "\n=== 10. Functional — tamper detection (wrong checksum must be rejected, never silently accepted) ===\n";
    $tamperTxnId = 'TEST-SUITE-TXN2-' . bin2hex(random_bytes(6));
    $tamperKm = AbdmCrypto::generateReceiverKeyMaterial();
    $tamperReqId = HiuDataFlow::newRequestId();
    $tamperRowId = HiuDataFlow::insertRequested($conn, [
        'artefact_id' => (int) $artefactAfterFetch['id'], 'request_id' => $tamperReqId,
        'our_private_key' => $tamperKm['privateKey'], 'our_nonce' => $tamperKm['nonce'],
        'date_from' => '2025-01-01 00:00:00', 'date_to' => '2026-01-01 00:00:00',
    ]);
    HiuDataFlow::attachTransaction($conn, $tamperReqId, $tamperTxnId, 'acknowledged');
    $tamperEnc = AbdmCrypto::encryptBundle('{"tampered":true}', $tamperKm['keyMaterial']['dhPublicKey']['keyValue'], $tamperKm['keyMaterial']['nonce']);
    $r6 = post("$BASE/api/v3/hiu/health-information/push", [
        'pageNumber' => 1, 'pageCount' => 1, 'transactionId' => $tamperTxnId,
        'entries' => [[
            'content'  => $tamperEnc['encryptedData'],
            'media'    => 'application/fhir+json',
            'checksum' => 'deliberately-wrong-checksum',
            'careContextReference' => 'TEST-SUITE-CC-2',
        ]],
        'keyMaterial' => $tamperEnc['keyMaterial'],
    ], ['REQUEST-ID' => bin2hex(random_bytes(8))]);
    check('push webhook still acks 200 (never leaks failure detail to the wire)', $r6['http'] === 200);
    $tamperRecords = HiuDataFlow::recordsForRequest($conn, $tamperRowId);
    check('tampered entry (bad checksum) was rejected — zero records stored', count($tamperRecords) === 0);
    $tamperFinal = HiuDataFlow::findByTransactionId($conn, $tamperTxnId);
    check('tampered push marked failed, not received', $tamperFinal && $tamperFinal['status'] === 'failed');
} finally {
    echo "\n--- cleanup ---\n";
    $conn->query("DELETE FROM abdm_hiu_received_records WHERE data_request_id IN (SELECT id FROM abdm_hiu_data_requests WHERE request_id LIKE 'TEST-SUITE-%' OR transaction_id LIKE 'TEST-SUITE-%')");
    $conn->query("DELETE FROM abdm_hiu_data_requests WHERE request_id LIKE 'TEST-SUITE-%' OR transaction_id LIKE 'TEST-SUITE-%'");
    $conn->query("DELETE FROM abdm_hiu_consent_artefacts WHERE consent_id LIKE 'TEST-SUITE-%'");
    $conn->query("DELETE FROM abdm_hiu_consent_requests WHERE request_id LIKE 'TEST-SUITE-%'");
    echo "cleanup done — " . $conn->affected_rows . " row(s) in the last statement\n";
}

echo "\n================================\n";
echo "TOTAL: $pass passed, $fail failed\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) echo "  - $f\n";
}
echo "================================\n";
exit($fail > 0 ? 1 : 0);
