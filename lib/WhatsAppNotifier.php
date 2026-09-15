<?php
/**
 * lib/WhatsAppNotifier.php — central dispatcher for event-driven WhatsApp
 * sends (welcome, appointment booked/confirmed/rejected/reminder,
 * prescription/report ready, parent-consent request).
 *
 * Every WhatsApp send outside the OTP flow goes through this class — nothing
 * calls _wa_post() / the Graph API directly except lib/WhatsAppOtp.php itself.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/WhatsAppNotifier.php';
 *   $wa = new WhatsAppNotifier($conn);
 *   $wa->sendEvent('appt_booked_patient', $mobile, ['patient_name' => ..., 'date' => ...],
 *                  'appointment', $appointmentId);
 *
 * Every send — success or failure — is logged to whatsapp_message_log.
 * An event_type with no active row in whatsapp_templates yet (template not
 * submitted/approved in Meta WhatsApp Manager) fails closed with
 * 'template_not_configured' rather than throwing.
 */

require_once __DIR__ . '/WhatsAppOtp.php';

class WhatsAppNotifier
{
    private mysqli $conn;

    public function __construct(mysqli $conn) { $this->conn = $conn; }

    public function sendEvent(string $eventType, string $mobile, array $params,
                               ?string $entityType = null, ?int $entityId = null): array
    {
        $mobile = wa_normalize_number($mobile);
        if ($this->isOptedOut($mobile)) {
            $this->log('outbound', $eventType, $entityType, $entityId, $mobile, null, 'text', $params, null, 'failed', 'recipient_opted_out');
            return ['ok' => false, 'error' => 'recipient_opted_out'];
        }

        $tpl = $this->resolveTemplate($eventType);
        if (!$tpl) {
            error_log("[WhatsAppNotifier] No template mapped for event '{$eventType}'");
            $this->log('outbound', $eventType, $entityType, $entityId, $mobile, null, 'template', $params, null, 'failed', 'template_not_configured');
            return ['ok' => false, 'error' => 'template_not_configured'];
        }

        $orderedParams = $this->orderParams($params, $tpl['param_order']);
        $result = wa_send_template($mobile, $tpl['template_name'], $orderedParams, $tpl['language']);

        $this->log('outbound', $eventType, $entityType, $entityId, $mobile,
            $tpl['template_name'], 'template', $orderedParams,
            $result['wamid'] ?? null, !empty($result['ok']) ? 'sent' : 'failed', $result['error'] ?? null);

        return $result;
    }

    public function sendDocument(string $mobile, string $fileUrl, string $filename, string $caption,
                                  string $eventType, ?string $entityType = null, ?int $entityId = null): array
    {
        $mobile = wa_normalize_number($mobile);
        if ($this->isOptedOut($mobile)) {
            $this->log('outbound', $eventType, $entityType, $entityId, $mobile, null, 'document',
                ['file' => $filename, 'caption' => $caption], null, 'failed', 'recipient_opted_out');
            return ['ok' => false, 'error' => 'recipient_opted_out'];
        }

        $result = wa_send_document_by_link($mobile, $fileUrl, $filename, $caption);

        $this->log('outbound', $eventType, $entityType, $entityId, $mobile, null, 'document',
            ['file' => $filename, 'caption' => $caption],
            $result['wamid'] ?? null, !empty($result['ok']) ? 'sent' : 'failed', $result['error'] ?? null);

        return $result;
    }

    private function resolveTemplate(string $eventType): ?array
    {
        $stmt = $this->conn->prepare("SELECT template_name, language, param_order FROM whatsapp_templates WHERE event_type=? AND active=1 LIMIT 1");
        $stmt->bind_param('s', $eventType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;
        return ['template_name' => $row['template_name'], 'language' => $row['language'] ?? 'en',
                'param_order' => json_decode($row['param_order'] ?? '[]', true) ?: []];
    }

    private function orderParams(array $params, array $order): array
    {
        if (array_keys($params) === range(0, count($params) - 1)) return $params;
        $out = [];
        foreach ($order as $key) $out[] = $params[$key] ?? '';
        return $out;
    }

    private function isOptedOut(string $mobile): bool
    {
        $stmt = $this->conn->prepare("SELECT 1 FROM whatsapp_optouts WHERE mobile=? LIMIT 1");
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    private function log(string $direction, string $eventType, ?string $entityType, ?int $entityId,
                          string $mobile, ?string $templateName, string $messageType, array $payload,
                          ?string $wamid, string $status, ?string $error): void
    {
        $stmt = $this->conn->prepare("INSERT INTO whatsapp_message_log
            (direction, event_type, entity_type, entity_id, mobile, template_name, message_type, payload, wamid, status, error)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $payloadJson = json_encode($payload);
        $stmt->bind_param('sssisssssss', $direction, $eventType, $entityType, $entityId, $mobile,
            $templateName, $messageType, $payloadJson, $wamid, $status, $error);
        $stmt->execute();
    }
}
