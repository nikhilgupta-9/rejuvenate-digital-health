<?php
/**
 * Fallback for when a browser tab is closed abruptly (e.g. OS killed the
 * process) instead of via the "End call" button, so the peer isn't left
 * waiting out the full presence timeout in poll.php. Called via
 * navigator.sendBeacon() on 'beforeunload'.
 * Only marks the session completed if it had actually started — never
 * destructive, just a best-effort cleanup of appointments.meeting_status.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/JWT.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$ticket = $body['ticket'] ?? '';

try {
    $claims = JWT::verify($ticket, TELEMED_SECRET);
    if (($claims['purpose'] ?? '') !== 'telemed_join') {
        throw new RuntimeException('bad purpose');
    }
} catch (Throwable $e) {
    http_response_code(200); // sendBeacon ignores the response either way
    echo json_encode(['success' => false]);
    exit;
}

$appointment_id = (int) $claims['appointment_id'];
$room = (string) ($claims['room'] ?? '');
$role = (string) ($claims['role'] ?? '');

$stmt = $conn->prepare("UPDATE appointments SET meeting_status='completed', meeting_completed_at=NOW()
    WHERE id=? AND meeting_status='started'");
$stmt->bind_param('i', $appointment_id);
$stmt->execute();

if ($stmt->affected_rows > 0 && $room !== '' && in_array($role, ['doctor', 'patient'], true)) {
    $ins = $conn->prepare("INSERT INTO telemedicine_signals (room, from_role, type, payload) VALUES (?, ?, 'call-ended', ?)");
    $payload = json_encode(['by' => $role]);
    $ins->bind_param('sss', $room, $role, $payload);
    $ins->execute();
}

echo json_encode(['success' => true]);
