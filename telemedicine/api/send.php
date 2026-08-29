<?php
/**
 * HTTP-polling signaling — the send side.
 *
 * POST { ticket, type, payload (JSON string, optional per type) }
 * type: 'offer' | 'answer' | 'ice-candidate' | 'toggle-media' | 'chat' | 'end-call'
 *
 * Just writes a row to telemedicine_signals for the peer's next poll to
 * pick up — this process never talks to the peer directly. Sender
 * identity (role/entity_id/name) always comes from the verified ticket,
 * never trusted from the POST body.
 */
require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__, 2) . '/lib/JWT.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
$type   = (string) ($_POST['type'] ?? '');

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

$allowedTypes = ['offer', 'answer', 'ice-candidate', 'toggle-media', 'chat', 'end-call'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown signal type']);
    exit;
}

$payloadIn = json_decode($_POST['payload'] ?? '{}', true);
if (!is_array($payloadIn)) {
    $payloadIn = [];
}

function telemed_insert_signal(mysqli $conn, string $room, string $fromRole, string $type, array $payload): void
{
    $stmt = $conn->prepare("INSERT INTO telemedicine_signals (room, from_role, type, payload) VALUES (?, ?, ?, ?)");
    $json = json_encode($payload);
    $stmt->bind_param('ssss', $room, $fromRole, $type, $json);
    $stmt->execute();
}

switch ($type) {
    case 'offer':
    case 'answer':
        // Relayed as-is — the SDP itself is opaque to this server.
        telemed_insert_signal($conn, $room, $role, $type, ['sdp' => $payloadIn['sdp'] ?? null]);
        echo json_encode(['success' => true]);
        break;

    case 'ice-candidate':
        telemed_insert_signal($conn, $room, $role, $type, ['candidate' => $payloadIn['candidate'] ?? null]);
        echo json_encode(['success' => true]);
        break;

    case 'toggle-media':
        telemed_insert_signal($conn, $room, $role, 'peer-media', [
            'kind'    => (string) ($payloadIn['kind'] ?? ''),
            'enabled' => (bool) ($payloadIn['enabled'] ?? true),
            'name'    => $name,
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'chat':
        $message = trim((string) ($payloadIn['message'] ?? ''));
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Empty message']);
            exit;
        }
        $message = mb_substr($message, 0, 2000);

        $ins = $conn->prepare("INSERT INTO telemedicine_chat_messages (appointment_id, sender_role, sender_id, message) VALUES (?,?,?,?)");
        $ins->bind_param('isis', $appointmentId, $role, $entityId, $message);
        $ins->execute();

        $chatPayload = [
            'from'    => $role,
            'name'    => $name,
            'message' => $message,
            'time'    => date('h:i A'),
        ];
        telemed_insert_signal($conn, $room, $role, 'chat', $chatPayload);

        // The sender renders their own bubble from this response — polling
        // never delivers a sender's own signals back to them.
        echo json_encode(['success' => true, 'echo' => $chatPayload]);
        break;

    case 'end-call':
        $upd = $conn->prepare("UPDATE appointments SET meeting_status='completed', meeting_completed_at=NOW() WHERE id=? AND meeting_status != 'completed'");
        $upd->bind_param('i', $appointmentId);
        $upd->execute();

        telemed_insert_signal($conn, $room, $role, 'call-ended', ['by' => $role]);

        // Deliberately NOT deleting this room's rows here — the peer still
        // needs to poll and see this exact 'call-ended' signal first.
        // Old rooms/signals are swept up later by the probabilistic
        // cleanup in poll.php.
        echo json_encode(['success' => true]);
        break;
}
