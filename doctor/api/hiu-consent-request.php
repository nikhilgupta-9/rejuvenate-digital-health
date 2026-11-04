<?php
/**
 * ABDM Milestone 3 — doctor-initiated consent request (HIU role, step 1).
 * POST JSON: patient_id, hi_types[], date_from, date_to, csrf_token
 * Returns { success, error? }
 *
 * Records the request locally (status 'requested'), then calls
 * ConsentApi::hiuConsentRequestInit(). The real consentRequest.id and the
 * eventual GRANTED/DENIED outcome arrive asynchronously via
 * telemedicine/api/abdm-hiu-consent-on-init.php and -notify.php.
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/config/abdm.php';
require_once dirname(dirname(__DIR__)) . '/lib/Security.php';
require_once dirname(dirname(__DIR__)) . '/lib/HiuConsent.php';
require_once dirname(dirname(__DIR__)) . '/lib/ConsentApi.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$body = json_decode(file_get_contents('php://input'), true) ?: [];

if (!isset($body['csrf_token']) || !Security::verifyCsrf($body['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing security token. Please reload the page and try again.']); exit;
}

$patient_id = (int) ($body['patient_id'] ?? 0);
if (!$patient_id) {
    echo json_encode(['success' => false, 'error' => 'patient_id is required']); exit;
}

// Doctor must actually have access to this patient (same check used by patient-document-upload.php).
$chk = $conn->prepare("SELECT 1 FROM doctor_patients WHERE doctor_id=? AND patient_id=? LIMIT 1");
$chk->bind_param('ii', $doctor_id, $patient_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'error' => 'You do not have access to this patient']); exit;
}

$hiTypesAllowed = ['Prescription', 'DiagnosticReport', 'OPConsultation', 'DischargeSummary', 'ImmunizationRecord', 'HealthDocumentRecord', 'WellnessRecord'];
$hiTypes = array_values(array_intersect($hiTypesAllowed, (array) ($body['hi_types'] ?? [])));
if (!$hiTypes) {
    echo json_encode(['success' => false, 'error' => 'Select at least one valid record type']); exit;
}

$dateFrom = trim((string) ($body['date_from'] ?? ''));
$dateTo   = trim((string) ($body['date_to'] ?? ''));
if ($dateFrom === '' || $dateTo === '' || strtotime($dateFrom) === false || strtotime($dateTo) === false || strtotime($dateFrom) > strtotime($dateTo)) {
    echo json_encode(['success' => false, 'error' => 'Please choose a valid date range']); exit;
}

$pStmt = $conn->prepare("SELECT name, last_name, abha_address FROM users WHERE id = ? LIMIT 1");
$pStmt->bind_param('i', $patient_id);
$pStmt->execute();
$patient = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

$abhaAddress = trim((string) ($patient['abha_address'] ?? ''));
if ($abhaAddress === '') {
    echo json_encode(['success' => false, 'error' => 'This patient has no ABHA address on file']); exit;
}

$capi = new ConsentApi();
if (!$capi->isHiuConfigured()) {
    echo json_encode(['success' => false, 'error' => 'ABDM HIU role is not configured on this server']); exit;
}

$requestId = HiuConsent::newRequestId();
$dateFromIso = gmdate('Y-m-d\TH:i:s.000\Z', strtotime($dateFrom . ' 00:00:00'));
$dateToIso   = gmdate('Y-m-d\TH:i:s.000\Z', strtotime($dateTo . ' 23:59:59'));
$eraseAtIso  = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+180 days'));

HiuConsent::insertRequested($conn, [
    'request_id'   => $requestId,
    'doctor_id'    => $doctor_id,
    'patient_id'   => $patient_id,
    'abha_address' => $abhaAddress,
    'hip_id'       => null, // broadcast to every HIP the patient has linked
    'purpose_code' => 'CAREMGT',
    'purpose_text' => 'Care Management',
    'hi_types'     => $hiTypes,
    'date_from'    => date('Y-m-d H:i:s', strtotime($dateFrom . ' 00:00:00')),
    'date_to'      => date('Y-m-d H:i:s', strtotime($dateTo . ' 23:59:59')),
    'expiry_at'    => date('Y-m-d H:i:s', strtotime('+180 days')),
]);

$consentBody = [
    'purpose' => [
        'text'    => 'Care Management',
        'code'    => 'CAREMGT',
        'refUri'  => 'www.abdm.gov.in',
    ],
    'patient' => ['id' => $abhaAddress],
    'hiu'     => ['id' => defined('ABDM_HIU_ID') ? ABDM_HIU_ID : ''],
    'requester' => [
        'name' => 'Dr. ' . trim((string) ($payload['name'] ?? 'Doctor')),
        'identifier' => [
            'type'   => 'REGN01',
            'value'  => (string) $doctor_id,
            'system' => 'https://www.mciindia.org',
        ],
    ],
    'hiTypes' => $hiTypes,
    'permission' => [
        'accessMode' => 'VIEW',
        'dateRange'  => ['from' => $dateFromIso, 'to' => $dateToIso],
        'dataEraseAt' => $eraseAtIso,
        'frequency'  => ['unit' => 'HOUR', 'value' => 0, 'repeats' => 0],
    ],
];

$res = $capi->hiuConsentRequestInit($consentBody, $requestId);
if (!$res['success']) {
    echo json_encode(['success' => false, 'error' => $res['error']]); exit;
}

echo json_encode(['success' => true]);
