<?php
/**
 * webhook/whatsapp.php — Inbound webhook for Meta WhatsApp Cloud API.
 *
 * Handles:
 *  1. Verification handshake (GET with hub_mode, hub_verify_token, hub_challenge)
 *  2. Inbound event delivery (POST):
 *     - Status callbacks (delivered, read, failed) -> updates whatsapp_message_log
 *     - STOP / UNSUBSCRIBE keywords -> records in whatsapp_optouts
 *     - Inbound messages -> logs to whatsapp_message_log
 */

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/whatsapp.php';

// 1. Meta Webhook Verification (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode'] ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    $expectedToken = $_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN'] ?? 'rdh_whatsapp_verify_token_2026';

    if ($mode === 'subscribe' && hash_equals($expectedToken, $token)) {
        header('Content-Type: text/plain');
        http_response_code(200);
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// 2. Incoming Notification Handling (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$rawBody = file_get_contents('php://input');

// Verify Meta signature if WHATSAPP_APP_SECRET is configured
if (!empty($_ENV['WHATSAPP_APP_SECRET'])) {
    $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected  = 'sha256=' . hash_hmac('sha256', $rawBody, $_ENV['WHATSAPP_APP_SECRET']);
    if (!hash_equals($expected, $sigHeader)) {
        http_response_code(401);
        error_log('[WhatsApp Webhook] Signature verification failed');
        exit;
    }
}

$data = json_decode($rawBody, true);
http_response_code(200);
echo 'OK';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (!is_array($data) || empty($data['entry'])) {
    exit;
}

foreach ($data['entry'] as $entry) {
    foreach ($entry['changes'] ?? [] as $change) {
        $value = $change['value'] ?? [];

        // Handle delivery status updates (sent, delivered, read, failed)
        foreach ($value['statuses'] ?? [] as $status) {
            $wamid = $status['id'] ?? null;
            $state = $status['status'] ?? null;
            if ($wamid && $state) {
                $err = null;
                if (!empty($status['errors'][0]['title'])) {
                    $err = $status['errors'][0]['title'];
                }
                $stmt = $conn->prepare("UPDATE whatsapp_message_log SET status = ?, error = COALESCE(?, error), updated_at = NOW() WHERE wamid = ?");
                $stmt->bind_param('sss', $state, $err, $wamid);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Handle inbound messages (replies, STOP opt-out)
        foreach ($value['messages'] ?? [] as $msg) {
            $from = $msg['from'] ?? '';
            $type = $msg['type'] ?? 'text';
            $text = strtoupper(trim($msg['text']['body'] ?? ''));

            if (in_array($text, ['STOP', 'UNSUBSCRIBE', 'OPT OUT'], true)) {
                $stmt = $conn->prepare("INSERT IGNORE INTO whatsapp_optouts (mobile) VALUES (?)");
                $stmt->bind_param('s', $from);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO whatsapp_message_log
                (direction, event_type, mobile, message_type, payload, status)
                VALUES ('inbound', 'reply', ?, ?, ?, 'delivered')");
            $payload = json_encode($msg);
            $stmt->bind_param('sss', $from, $type, $payload);
            $stmt->execute();
            $stmt->close();
        }
    }
}
