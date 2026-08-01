<?php
/**
 * Fallback for when a browser tab is closed abruptly and the WebSocket
 * close event doesn't reach the signaling server in time (e.g. OS killed
 * the process). Called via navigator.sendBeacon() on 'beforeunload'.
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

$stmt = $conn->prepare("UPDATE appointments SET meeting_status='completed', meeting_completed_at=NOW()
    WHERE id=? AND meeting_status='started'");
$stmt->bind_param('i', $appointment_id);
$stmt->execute();

echo json_encode(['success' => true]);
