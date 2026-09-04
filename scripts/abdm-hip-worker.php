<?php
/**
 * ABDM HIP-Initiated Linking — background worker.
 *
 *   php scripts/abdm-hip-worker.php            process the queue once
 *   php scripts/abdm-hip-worker.php --dry-run  show what would happen
 *
 * Cron (every ~5 min) — "5-star" spec, php binary, then this script:
 *   [min=slash-5] [hr=star] [dom=star] [mon=star] [dow=star]
 *   /Applications/XAMPP/xamppfiles/bin/php /path/scripts/abdm-hip-worker.php >> /path/logs/hip-worker.log 2>&1
 *
 * doctor/patient-form.php only drops a 'pending' row in
 * abdm_care_context_links when a prescription is finalised for an
 * ABHA-linked patient. This worker does the actual ABDM calls:
 *
 *   1. ensure the patient has a usable link token
 *        - none / expired      → generateLinkToken(), record 'pending',
 *          skip this care-context until the webhook delivers the token
 *   2. linkCareContext(... , linkToken, storedRequestId)
 *   3. notifyCareContext(...)          (best effort)
 *
 * The webhook (telemedicine/api/abdm-webhook.php) moves rows
 * pending → received / linked / failed.
 *
 * CLI note (XAMPP): run with /Applications/XAMPP/xamppfiles/bin/php or add
 *   -d mysqli.default_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
 */

require __DIR__ . '/../config/connect.php';
require __DIR__ . '/../config/abdm.php';
require __DIR__ . '/../lib/AbdmApi.php';
require __DIR__ . '/../lib/HipApi.php';
require __DIR__ . '/../lib/HipLinking.php';

$DRY = in_array('--dry-run', $argv, true);

function out(string $s = ''): void { fwrite(STDOUT, '[' . date('H:i:s') . '] ' . $s . "\n"); }

if (!defined('ABDM_HIP_CONFIGURED') || !ABDM_HIP_CONFIGURED) {
    out('HIP not configured (ABDM_HIP_ID / ABHA credentials). Nothing to do.');
    exit(0);
}

$hip = new HipApi();

// Housekeeping.
$expired = HipLinking::expireStaleTokens($conn);
if ($expired) out("expired $expired stale link token(s)");

// Give up on care-context links stuck pending for too long.
$GIVE_UP_HOURS = 48;
if (!$DRY) {
    $conn->query("UPDATE abdm_care_context_links
                  SET status = 'failed'
                  WHERE status = 'pending' AND created_at < (NOW() - INTERVAL $GIVE_UP_HOURS HOUR)");
    if ($conn->affected_rows) out("gave up on {$conn->affected_rows} link(s) older than {$GIVE_UP_HOURS}h");
}

// Pending care-context links + the patient behind each prescription.
$rows = $conn->query("
    SELECT ccl.id, ccl.prescription_id, ccl.reference_number, ccl.care_context_reference,
           ccl.hi_type, ccl.request_id,
           p.patient_id,
           u.name, u.last_name, u.gender, YEAR(u.dob) AS yob,
           COALESCE(aa.abha_number,  u.abha_id)      AS abha_number,
           COALESCE(aa.abha_address, u.abha_address) AS abha_address
    FROM abdm_care_context_links ccl
    JOIN prescriptions p ON p.id = ccl.prescription_id
    JOIN users u         ON u.id = p.patient_id
    LEFT JOIN abha_accounts aa ON aa.entity_type = 'patient' AND aa.entity_id = u.id
    WHERE ccl.status = 'pending'
    ORDER BY ccl.id
    LIMIT 100
");

$done = 0; $waiting = 0; $failed = 0;

while ($row = $rows->fetch_assoc()) {
    $ccId       = (int) $row['id'];
    $patientId  = (int) $row['patient_id'];
    $abhaAddr   = trim((string) $row['abha_address']);
    $abhaNumber = (string) $row['abha_number'];
    $name       = trim($row['name'] . ' ' . ($row['last_name'] ?? ''));
    $yob        = (int) $row['yob'];

    if ($abhaAddr === '') {
        out("cc#$ccId: patient has no ABHA address — marking failed");
        if (!$DRY) HipLinking::applyLinkingStatus($conn, $row['request_id'], false);
        $failed++;
        continue;
    }

    // 1. link token
    $token = HipLinking::activeLinkToken($conn, $patientId);
    if (!$token) {
        out("cc#$ccId: no link token for patient#$patientId — requesting one");
        if ($DRY) { $waiting++; continue; }
        $tokReq = HipLinking::newRequestId();
        $res = $hip->generateLinkToken($abhaAddr, $abhaNumber, $name, (string) $row['gender'], $yob, $tokReq);
        if ($res['success']) {
            HipLinking::startLinkToken($conn, $patientId, $abhaAddr, $res['data']['requestId'] ?? $tokReq);
            out("  → generate-token accepted; will retry cc#$ccId after the webhook delivers the token");
        } else {
            out("  → generate-token failed: " . $res['error']);
        }
        $waiting++;
        continue;
    }

    // 2. link the care context (pass the stored request_id so the webhook matches this row)
    out("cc#$ccId: linking care context " . $row['care_context_reference']);
    if ($DRY) { $done++; continue; }

    $link = $hip->linkCareContext(
        $abhaAddr,
        $abhaNumber,
        $row['reference_number'] ?: ('PATIENT-' . $patientId),
        $name,
        $row['hi_type'] ?: 'Prescription',
        [['referenceNumber' => $row['care_context_reference'], 'display' => 'Prescription ' . date('d M Y')]],
        (string) $token['link_token'],
        (string) $row['request_id']
    );

    if (!$link['success']) {
        out("  → link/carecontext failed: " . $link['error'] . " (left pending for retry)");
        continue;
    }

    // 3. notify (best effort — its own request id)
    $notify = $hip->notifyCareContext($abhaAddr, $row['care_context_reference'], [$row['hi_type'] ?: 'Prescription']);
    out("  → link accepted; notify " . ($notify['success'] ? 'accepted' : 'failed: ' . $notify['error']));
    // row stays 'pending' until the linking-status webhook lands
    $done++;
}

out("done — $done linked-request(s) sent, $waiting waiting on token, $failed failed");
