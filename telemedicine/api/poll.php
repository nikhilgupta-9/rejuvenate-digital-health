<?php
/**
 * HTTP-polling signaling — the receive side.
 *
 * The browser calls this every ~2s with its signed join ticket and the
 * highest signal `id` it has already seen (`since`). Each call:
 *   1. Marks the caller "present" (heartbeat) on this room.
 *   2. Reads the peer's presence to compute peerPresent.
 *   3. First time both sides are simultaneously present, fires the
 *      one-time 'ready' signal (doctor is always the WebRTC offer
 *      initiator, by convention — same as the old WS server) and marks
 *      the appointment meeting_status='started'.
 *   4. Returns any signals from the OTHER role posted since `since`.
 *
 * GET params: ticket, since (default 0)
 */
require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__, 2) . '/lib/JWT.php';

header('Content-Type: application/json');

// The peer is shown as "here" in the UI as long as a poll from them reached
// us within this window. Generous, so a slow poll under load doesn't make a
// live call look dropped (which used to trigger a full WebRTC re-handshake
// and flood telemedicine_signals with a fresh ICE-candidate storm).
const TELEMED_PRESENCE_TIMEOUT_SECONDS = 20;

// Only after the peer has been silent this long do we treat it as a real
// disconnect and reset `ready_sent`, so a genuine rejoin re-negotiates.
const TELEMED_RECONNECT_RESET_SECONDS = 45;

$ticket = $_GET['ticket'] ?? $_POST['ticket'] ?? '';
$since  = (int) ($_GET['since'] ?? 0);

try {
    $claims = JWT::verify($ticket, TELEMED_SECRET);
    if (($claims['purpose'] ?? '') !== 'telemed_join') {
        throw new RuntimeException('Wrong ticket purpose');
    }
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please rejoin the call.']);
    exit;
}

$room          = (string) $claims['room'];
$role          = (string) $claims['role'];
$appointmentId = (int) $claims['appointment_id'];
$entityId      = (int) $claims['entity_id'];
$name          = (string) ($claims['name'] ?? '');

if (!in_array($role, ['doctor', 'patient'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Housekeeping — keep telemedicine_signals from ballooning under a flaky
// connection, but only touch rows old enough that no live handshake could
// still need them (5 min), and never let a cleanup hiccup break the poll.
try {
    if (mt_rand(1, 20) === 1) {
        $sweep = $conn->prepare("DELETE FROM telemedicine_signals WHERE room = ? AND created_at < (NOW() - INTERVAL 5 MINUTE)");
        $sweep->bind_param('s', $room);
        $sweep->execute();
    }
    if (mt_rand(1, 400) === 1) {
        $conn->query("DELETE FROM telemedicine_signals WHERE created_at < (NOW() - INTERVAL 30 MINUTE)");
        $conn->query("DELETE FROM telemedicine_rooms   WHERE created_at < (NOW() - INTERVAL 2 HOUR)");
    }
} catch (Throwable $e) {
    error_log('[telemed poll] sweep: ' . $e->getMessage());
}

$myCol   = $role === 'doctor' ? 'doctor' : 'patient';
$peerCol = $role === 'doctor' ? 'patient' : 'doctor';

// Upsert my presence heartbeat (creates the room row on first poll).
$stmt = $conn->prepare("
    INSERT INTO telemedicine_rooms (room, appointment_id, {$myCol}_last_seen, {$myCol}_entity_id, {$myCol}_name)
    VALUES (?, ?, NOW(3), ?, ?)
    ON DUPLICATE KEY UPDATE
        {$myCol}_last_seen = NOW(3),
        {$myCol}_entity_id = VALUES({$myCol}_entity_id),
        {$myCol}_name = VALUES({$myCol}_name)
");
$stmt->bind_param('siis', $room, $appointmentId, $entityId, $name);
$stmt->execute();

// Compute how stale each side's heartbeat is *inside SQL* so this never
// depends on PHP's timezone matching MySQL's — NOW(3) and *_last_seen are
// both in MySQL's session zone, so their difference is always correct even
// when php.ini and the DB server disagree about the local timezone (which
// they routinely do on shared hosting → the peer looked permanently gone).
$stmt = $conn->prepare("
    SELECT *,
           TIMESTAMPDIFF(SECOND, `doctor_last_seen`,  NOW(3)) AS _doctor_age_s,
           TIMESTAMPDIFF(SECOND, `patient_last_seen`, NOW(3)) AS _patient_age_s
    FROM telemedicine_rooms WHERE room = ? LIMIT 1
");
$stmt->bind_param('s', $room);
$stmt->execute();
$roomRow = $stmt->get_result()->fetch_assoc();

$peerAge = $roomRow["_{$peerCol}_age_s"] ?? null;   // seconds since the peer's last poll, or null if never
$peerPresent  = ($peerAge !== null && (int) $peerAge <= TELEMED_PRESENCE_TIMEOUT_SECONDS);
$peerLongGone = ($peerAge === null || (int) $peerAge > TELEMED_RECONNECT_RESET_SECONDS);

if ($peerPresent && (int) $roomRow['ready_sent'] === 0) {
    // First time both sides are here (or a genuine rejoin after a long gap) —
    // fire the one-time 'ready'. Guarded so only one of the two concurrent
    // pollers wins the race and actually inserts it.
    $upd = $conn->prepare("UPDATE telemedicine_rooms SET ready_sent = 1 WHERE room = ? AND ready_sent = 0");
    $upd->bind_param('s', $room);
    $upd->execute();
    if ($upd->affected_rows === 1) {
        $ins = $conn->prepare("INSERT INTO telemedicine_signals (room, from_role, type, payload) VALUES (?, 'system', 'ready', '{}')");
        $ins->bind_param('s', $room);
        $ins->execute();

        $mark = $conn->prepare("UPDATE appointments SET meeting_status='started', meeting_started_at=IFNULL(meeting_started_at, NOW()) WHERE id=? AND meeting_status IN ('created','started')");
        $mark->bind_param('i', $appointmentId);
        $mark->execute();
    }
} elseif ($peerLongGone && (int) $roomRow['ready_sent'] === 1) {
    // A REAL disconnect (peer silent for 45s+) — reset so that if they rejoin
    // the handshake runs again. A short poll delay no longer does this, which
    // is what stops the repeated ICE-candidate storms.
    $upd = $conn->prepare("UPDATE telemedicine_rooms SET ready_sent = 0 WHERE room = ? AND ready_sent = 1");
    $upd->bind_param('s', $room);
    $upd->execute();
}

// Signals from the OTHER role only — never echo my own posts back to me.
$stmt = $conn->prepare("
    SELECT id, type, payload FROM telemedicine_signals
    WHERE room = ? AND id > ? AND from_role != ?
    ORDER BY id ASC LIMIT 200
");
$stmt->bind_param('sis', $room, $since, $role);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
$lastId = $since;
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id'      => (int) $row['id'],
        'type'    => $row['type'],
        'payload' => json_decode($row['payload'], true),
    ];
    $lastId = (int) $row['id'];
}

echo json_encode([
    'success'     => true,
    'peerPresent' => $peerPresent,
    'lastId'      => $lastId,
    'messages'    => $messages,
]);

$conn->close();
