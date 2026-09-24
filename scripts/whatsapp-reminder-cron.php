<?php
/**
 * scripts/whatsapp-reminder-cron.php — Automated 24h WhatsApp Appointment Reminders.
 *
 * Runs via CLI cron (e.g. every 15-30 minutes):
 *   php /path/to/scripts/whatsapp-reminder-cron.php
 */

require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/whatsapp.php';
require_once dirname(__DIR__) . '/lib/WhatsAppNotifier.php';
require_once dirname(__DIR__) . '/telemedicine/helpers.php';

if (php_sapi_name() !== 'cli' && empty($_GET['cron_key'])) {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit;
}

$wa = new WhatsAppNotifier($conn);

// Find confirmed appointments scheduled within roughly the next 24 hours that haven't received a reminder
$sql = "
    SELECT a.id, a.appointment_type,
           COALESCE(u.mobile, a.patient_phone) AS mobile,
           COALESCE(u.name, a.patient_name)   AS pname,
           COALESCE(d.name, 'your doctor')    AS doctor_name,
           a.appointment_date, a.appointment_time
    FROM appointments a
    LEFT JOIN users u   ON u.id = a.user_id
    LEFT JOIN doctors d ON d.id = a.doctor_id
    WHERE a.status IN ('approved', 'confirmed')
      AND a.reminder_24h_sent = 0
      AND a.appointment_date = CURDATE() + INTERVAL 1 DAY
";

$result = $conn->query($sql);
if (!$result) {
    error_log("[WhatsApp Reminder Cron] Query error: " . $conn->error);
    exit;
}

$sentCount = 0;
while ($appt = $result->fetch_assoc()) {
    $mobile = trim((string) $appt['mobile']);
    if ($mobile === '') continue;

    $dateStr = date('d M Y', strtotime($appt['appointment_date']));
    $timeStr = date('h:i A', strtotime($appt['appointment_time']));

    $joinLink = '';
    if (strtolower($appt['appointment_type'] ?? '') === 'online') {
        $joinLink = telemedicine_guest_link((int) $appt['id']);
    }

    $params = [
        'patient_name' => $appt['pname'] ?: 'Patient',
        'doctor_name'  => 'Dr. ' . $appt['doctor_name'],
        'date'         => $dateStr,
        'time'         => $timeStr,
        'join_link'    => $joinLink,
    ];

    $res = $wa->sendEvent('appt_reminder_24h', $mobile, $params, 'appointment', (int) $appt['id']);

    // Mark as sent regardless of provider response so we do not spam repeatedly if phone is invalid/opted-out
    $upd = $conn->prepare("UPDATE appointments SET reminder_24h_sent = 1 WHERE id = ?");
    $upd->bind_param('i', $appt['id']);
    $upd->execute();
    $upd->close();

    $sentCount++;
}

echo "WhatsApp Reminder Cron completed. Processed {$sentCount} reminder(s).\n";
