<?php
/**
 * The video consultation room. Reached only via join.php, which issues
 * a short-lived signed ticket — this page just needs to verify that
 * ticket (no session/JWT re-check needed, see join.php for why).
 */
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/lib/JWT.php';

$ticket = $_GET['ticket'] ?? '';
try {
    $claims = JWT::verify($ticket, TELEMED_SECRET);
    if (($claims['purpose'] ?? '') !== 'telemed_join') {
        throw new RuntimeException('Wrong ticket purpose');
    } 
} catch (Throwable $e) {
    http_response_code(401);
    exit('This video call link has expired. Please go back and click "Join Video Call" again.');
}

$appointment_id = (int) $claims['appointment_id'];
$role           = $claims['role'];
$room_token     = $claims['room'];
$my_name        = $claims['name'];

$stmt = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.profile_image AS doctor_image,
        u.name AS patient_name, u.profile_pic AS patient_image
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE a.id = ? AND a.meeting_event_id = ? LIMIT 1");
$stmt->bind_param('is', $appointment_id, $room_token);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    http_response_code(404);
    exit('This video call could not be found.');
}

$other_name  = $role === 'doctor' ? ($appt['patient_name'] ?: 'Patient') : ('Dr. ' . $appt['doctor_name']);
$my_display  = $role === 'doctor' ? ('Dr. ' . $appt['doctor_name']) : ($appt['patient_name'] ?: $my_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Video Consultation — <?= htmlspecialchars($other_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/room.css">
</head>

<body>
    <div class="room-topbar">
        <div class="room-title">
            <i class="fas fa-video"></i>
            <div>
                <div class="rt-name"><?= htmlspecialchars($other_name) ?></div>
                <div class="rt-sub"><?= htmlspecialchars($appt['purpose'] ?: 'Video Consultation') ?> &middot; <?= date('d M Y, h:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])) ?></div>
            </div>
        </div>
        <div id="callStatus" class="call-status waiting"><i class="fas fa-circle"></i> Connecting…</div>
    </div>

    <div class="room-stage">
        <div class="video-wrap">
            <video id="remoteVideo" autoplay playsinline></video>
            <div id="waitingOverlay" class="waiting-overlay">
                <i class="fas fa-user-clock"></i>
                <div class="wo-title">Waiting for <?= htmlspecialchars($other_name) ?> to join…</div>
                <div class="wo-sub">You're connected. The call will start automatically once they arrive.</div>
            </div>
            <div class="remote-label"><?= htmlspecialchars($other_name) ?></div>
        </div>
        <div class="local-pip">
            <video id="localVideo" autoplay playsinline muted></video>
            <div class="local-label">You (<?= htmlspecialchars($my_display) ?>)</div>
        </div>

        <!-- Chat panel -->
        <div id="chatPanel" class="chat-panel">
            <div class="chat-head">
                <span><i class="fas fa-comment-dots me-2"></i>Chat</span>
                <button id="closeChatBtn" class="chat-close"><i class="fas fa-times"></i></button>
            </div>
            <div id="chatMessages" class="chat-messages"></div>
            <form id="chatForm" class="chat-form">
                <input type="text" id="chatInput" placeholder="Type a message…" autocomplete="off">
                <button type="submit"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

    <div class="room-controls">
        <button id="micBtn" class="ctrl-btn" title="Mute / unmute"><i class="fas fa-microphone"></i></button>
        <button id="camBtn" class="ctrl-btn" title="Camera on / off"><i class="fas fa-video"></i></button>
        <button id="chatBtn" class="ctrl-btn" title="Chat"><i class="fas fa-comment-dots"></i></button>
        <button id="endBtn" class="ctrl-btn end" title="End call"><i class="fas fa-phone-slash"></i></button>
    </div>

    <script>
        window.TELEMED_CONFIG = {
            pollUrl: <?= json_encode(BASE_URL . 'telemedicine/api/poll.php') ?>,
            sendUrl: <?= json_encode(BASE_URL . 'telemedicine/api/send.php') ?>,
            endSessionUrl: <?= json_encode(BASE_URL . 'telemedicine/api/end-session.php') ?>,
            pollIntervalMs: <?= (int) (defined('TELEMED_POLL_INTERVAL_MS') ? TELEMED_POLL_INTERVAL_MS : 2000) ?>,
            ticket: <?= json_encode($ticket) ?>,
            role: <?= json_encode($role) ?>,
            appointmentId: <?= json_encode($appointment_id) ?>,
            iceServers: <?= TELEMED_ICE_SERVERS ?>,
            exitUrl: <?= json_encode($role === 'doctor' ? (BASE_URL . 'doctor/appointments.php') : (BASE_URL . 'user/my-doctor-appointments.php')) ?>,
        };
    </script>
    <script src="assets/js/room.js"></script>
</body>

</html>
