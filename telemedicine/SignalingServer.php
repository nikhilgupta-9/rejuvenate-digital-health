<?php

namespace Telemedicine;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

require_once __DIR__ . '/../lib/JWT.php';

/**
 * WebRTC signaling for 1:1 doctor/patient video consultations.
 *
 * Auth: the browser connects with ?ticket=<signed JWT> (issued by
 * telemedicine/join.php after verifying the requester owns the
 * appointment). This process never touches PHP sessions/cookies —
 * the ticket is the only thing it trusts.
 *
 * Room state (who is connected) lives in memory only, keyed by the
 * room token (appointments.meeting_event_id). Call lifecycle
 * (created/started/completed) is persisted back to appointments.meeting_*.
 */
class SignalingServer implements MessageComponentInterface
{
    /** @var array<string, array{doctor: ?ConnectionInterface, patient: ?ConnectionInterface}> */
    private array $rooms = [];

    /** @var \SplObjectStorage Connection => ['room','role','appointment_id','entity_id','name'] */
    private \SplObjectStorage $meta;

    private array $dbConfig;
    private ?\mysqli $db = null;
    private string $secret;

    public function __construct(array $dbConfig, string $secret)
    {
        $this->dbConfig = $dbConfig;
        $this->secret = $secret;
        $this->meta = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $query = [];
        if (isset($conn->httpRequest)) {
            parse_str($conn->httpRequest->getUri()->getQuery(), $query);
        }
        $ticket = $query['ticket'] ?? '';

        try {
            $claims = \JWT::verify($ticket, $this->secret);
            if (($claims['purpose'] ?? '') !== 'telemed_join') {
                throw new \RuntimeException('Wrong ticket purpose');
            }
        } catch (\Throwable $e) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Invalid or expired session — please rejoin from your appointments page.']);
            $conn->close();
            return;
        }

        $room = (string) $claims['room'];
        $role = (string) $claims['role'];
        if (!in_array($role, ['doctor', 'patient'], true)) {
            $conn->close();
            return;
        }

        $appointmentId = (int) $claims['appointment_id'];
        $entityId = (int) $claims['entity_id'];
        $name = (string) ($claims['name'] ?? '');

        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = ['doctor' => null, 'patient' => null];
        }

        // Replace any stale connection for this role (e.g. old tab that hasn't closed yet)
        $existing = $this->rooms[$room][$role];
        if ($existing !== null && $existing !== $conn) {
            $this->meta->detach($existing);
            try { $existing->close(); } catch (\Throwable $e) {}
        }

        $this->rooms[$room][$role] = $conn;
        $this->meta->attach($conn, [
            'room'           => $room,
            'role'           => $role,
            'appointment_id' => $appointmentId,
            'entity_id'      => $entityId,
            'name'           => $name,
        ]);

        $this->sendJson($conn, ['type' => 'joined', 'role' => $role, 'room' => $room]);

        $other = $this->otherConn($room, $role);
        if ($other) {
            $this->sendJson($other, ['type' => 'peer-status', 'peerPresent' => true]);
        }
        $this->sendJson($conn, ['type' => 'peer-status', 'peerPresent' => $other !== null]);

        if ($other) {
            // Doctor is always the WebRTC offer initiator, by convention.
            $this->sendJson($this->rooms[$room]['doctor'], ['type' => 'ready', 'initiator' => true]);
            $this->sendJson($this->rooms[$room]['patient'], ['type' => 'ready', 'initiator' => false]);
            $this->markStarted($appointmentId);
        }
    }

    public function onMessage(ConnectionInterface $from, $msgRaw): void
    {
        if (!$this->meta->contains($from)) {
            return;
        }
        $info = $this->meta[$from];
        $data = json_decode((string) $msgRaw, true);
        if (!is_array($data) || !isset($data['type'])) {
            return;
        }

        $room = $info['room'];
        $role = $info['role'];
        $other = $this->otherConn($room, $role);

        switch ($data['type']) {
            case 'offer':
            case 'answer':
            case 'ice-candidate':
                if ($other) {
                    $this->sendJson($other, $data);
                }
                break;

            case 'toggle-media':
                if ($other) {
                    $this->sendJson($other, [
                        'type'    => 'peer-media',
                        'kind'    => (string) ($data['kind'] ?? ''),
                        'enabled' => (bool) ($data['enabled'] ?? true),
                        'name'    => $info['name'],
                    ]);
                }
                break;

            case 'chat':
                $message = trim((string) ($data['message'] ?? ''));
                if ($message === '') {
                    break;
                }
                $message = mb_substr($message, 0, 2000);
                $this->saveChatMessage($info['appointment_id'], $role, $info['entity_id'], $message);
                $payload = [
                    'type'    => 'chat',
                    'from'    => $role,
                    'name'    => $info['name'],
                    'message' => $message,
                    'time'    => date('h:i A'),
                ];
                $this->sendJson($from, $payload);
                if ($other) {
                    $this->sendJson($other, $payload);
                }
                break;

            case 'end-call':
                $this->endCall($room, $info['appointment_id'], $role);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if (!$this->meta->contains($conn)) {
            return;
        }
        $info = $this->meta[$conn];
        $this->meta->detach($conn);

        $room = $info['room'];
        if (!isset($this->rooms[$room])) {
            return;
        }

        if ($this->rooms[$room][$info['role']] === $conn) {
            $this->rooms[$room][$info['role']] = null;
        }

        $other = $this->otherConn($room, $info['role']);
        if ($other) {
            $this->sendJson($other, ['type' => 'peer-status', 'peerPresent' => false]);
        } else {
            $this->finalizeIfStarted($info['appointment_id']);
            unset($this->rooms[$room]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        error_log('[Telemedicine] WS error: ' . $e->getMessage());
        try { $conn->close(); } catch (\Throwable $ignored) {}
    }

    private function otherConn(string $room, string $myRole): ?ConnectionInterface
    {
        $otherRole = $myRole === 'doctor' ? 'patient' : 'doctor';
        return $this->rooms[$room][$otherRole] ?? null;
    }

    private function sendJson(?ConnectionInterface $conn, array $data): void
    {
        if (!$conn) {
            return;
        }
        try {
            $conn->send(json_encode($data));
        } catch (\Throwable $e) {
            // Peer likely gone — onClose will clean it up shortly.
        }
    }

    private function endCall(string $room, int $appointmentId, string $endedByRole): void
    {
        $this->finalize($appointmentId);

        $payload = ['type' => 'call-ended', 'by' => $endedByRole];
        foreach (['doctor', 'patient'] as $r) {
            $c = $this->rooms[$room][$r] ?? null;
            if ($c) {
                $this->sendJson($c, $payload);
                $this->meta->detach($c);
                try { $c->close(); } catch (\Throwable $e) {}
            }
        }
        unset($this->rooms[$room]);
    }

    /* ── DB (reconnect-safe: this process stays up for a long time) ── */

    private function db(): ?\mysqli
    {
        if ($this->db instanceof \mysqli) {
            if (@$this->db->ping()) {
                return $this->db;
            }
            @$this->db->close();
            $this->db = null;
        }

        $c = @new \mysqli(
            $this->dbConfig['host'],
            $this->dbConfig['user'],
            $this->dbConfig['pass'],
            $this->dbConfig['name']
        );
        if ($c->connect_error) {
            error_log('[Telemedicine] DB connect failed: ' . $c->connect_error);
            return null;
        }
        $c->set_charset('utf8');
        $this->db = $c;
        return $this->db;
    }

    private function markStarted(int $appointmentId): void
    {
        $db = $this->db();
        if (!$db) return;
        $stmt = $db->prepare("UPDATE appointments SET meeting_status='started', meeting_started_at=IFNULL(meeting_started_at, NOW()) WHERE id=? AND meeting_status IN ('created','started')");
        $stmt->bind_param('i', $appointmentId);
        $stmt->execute();
    }

    private function saveChatMessage(int $appointmentId, string $role, int $senderId, string $message): void
    {
        $db = $this->db();
        if (!$db) return;
        $stmt = $db->prepare("INSERT INTO telemedicine_chat_messages (appointment_id, sender_role, sender_id, message) VALUES (?,?,?,?)");
        $stmt->bind_param('isis', $appointmentId, $role, $senderId, $message);
        $stmt->execute();
    }

    private function finalize(int $appointmentId): void
    {
        $db = $this->db();
        if (!$db) return;
        $stmt = $db->prepare("UPDATE appointments SET meeting_status='completed', meeting_completed_at=NOW() WHERE id=? AND meeting_status != 'completed'");
        $stmt->bind_param('i', $appointmentId);
        $stmt->execute();
    }

    private function finalizeIfStarted(int $appointmentId): void
    {
        $db = $this->db();
        if (!$db) return;
        $stmt = $db->prepare("SELECT meeting_status FROM appointments WHERE id=?");
        $stmt->bind_param('i', $appointmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['meeting_status'] === 'started') {
            $this->finalize($appointmentId);
        }
    }
}
