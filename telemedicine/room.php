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

/* ── Doctor-only: patient snapshot + existing prescription for the in-call panel ── */
$patient_info = null;
$existing_rx  = null;
$past_rx      = [];
$rx_docs      = [];
if ($role === 'doctor') {
    $ps = $conn->prepare("
        SELECT u.id, u.name, u.last_name, u.mobile, u.email, u.dob, u.gender,
               u.blood_group, u.address, u.city, u.state,
               u.abha_id AS abha_number, u.abha_address, u.abha_linked,
               u.allergies, u.existing_condition, u.current_medication, u.medical_history,
               TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) AS age
        FROM users u WHERE u.id = ? LIMIT 1
    ");
    $pid = (int) ($appt['user_id'] ?? 0);
    $ps->bind_param('i', $pid);
    $ps->execute();
    $patient_info = $ps->get_result()->fetch_assoc() ?: null;
    $ps->close();

    $rs = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
    $rs->bind_param('i', $appointment_id);
    $rs->execute();
    $existing_rx = $rs->get_result()->fetch_assoc() ?: null;
    $rs->close();

    if ($pid) {
        $prs = $conn->prepare("
            SELECT visit_date, diagnosis, status
            FROM prescriptions
            WHERE patient_id = ? AND appointment_id <> ?
            ORDER BY visit_date DESC LIMIT 5
        ");
        $prs->bind_param('ii', $pid, $appointment_id);
        $prs->execute();
        $past_rx = $prs->get_result()->fetch_all(MYSQLI_ASSOC);
        $prs->close();

        $ds = $conn->prepare("
            SELECT document_name, document_type, file_path
            FROM patient_documents
            WHERE patient_id = ? AND appointment_id = ?
            ORDER BY uploaded_at DESC
        ");
        $ds->bind_param('ii', $pid, $appointment_id);
        $ds->execute();
        $rx_docs = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
        $ds->close();
    }
}

/** Decode a JSON column to array, tolerating null/garbage. */
function rm_json($v): array { $d = json_decode((string) $v, true); return is_array($d) ? $d : []; }
$rx_vitals = $existing_rx ? rm_json($existing_rx['vitals']) : [];
$rx_meds   = $existing_rx ? array_values(array_filter(rm_json($existing_rx['medications']), fn($m) => trim($m['name'] ?? '') !== '')) : [];
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1">
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
        <div id="chatPanel" class="side-panel chat-panel">
            <div class="chat-head">
                <span><i class="fas fa-comment-dots me-2"></i>Chat</span>
                <button id="closeChatBtn" class="chat-close" data-close-panel><i class="fas fa-times"></i></button>
            </div>
            <div id="chatMessages" class="chat-messages"></div>
            <form id="chatForm" class="chat-form">
                <input type="text" id="chatInput" placeholder="Type a message…" autocomplete="off">
                <button type="submit"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>

        <?php if ($role === 'doctor'): ?>
        <!-- Doctor: patient snapshot + prescription -->
        <div id="docPanel" class="side-panel">
            <div class="sp-head">
                <div class="sp-tabs">
                    <button type="button" class="sp-tab active" data-tab="patient"><i class="fas fa-user"></i> Patient</button>
                    <button type="button" class="sp-tab" data-tab="rx"><i class="fas fa-prescription"></i> Prescription</button>
                </div>
                <button class="chat-close" data-close-panel><i class="fas fa-times"></i></button>
            </div>

            <!-- Patient tab -->
            <div class="sp-body sp-pane active" data-pane="patient">
                <?php if ($patient_info): ?>
                    <div class="pi-name"><?= $e($patient_info['name'] . ' ' . ($patient_info['last_name'] ?? '')) ?></div>
                    <div class="pi-meta">
                        <?php if ($patient_info['age'] !== null): ?><span><?= (int) $patient_info['age'] ?> yrs</span><?php endif; ?>
                        <?php if ($patient_info['gender']): ?><span><?= $e($patient_info['gender']) ?></span><?php endif; ?>
                        <?php if ($patient_info['blood_group']): ?><span class="pi-blood"><i class="fas fa-droplet"></i> <?= $e($patient_info['blood_group']) ?></span><?php endif; ?>
                    </div>

                    <div class="pi-grid">
                        <div><span>Mobile</span><?= $e($patient_info['mobile'] ?: '—') ?></div>
                        <div><span>Email</span><?= $e($patient_info['email'] ?: '—') ?></div>
                        <div><span>City</span><?= $e(trim(($patient_info['city'] ?? '') . ', ' . ($patient_info['state'] ?? ''), ', ') ?: '—') ?></div>
                        <div><span>ABHA</span><?= $e($patient_info['abha_number'] ?: 'Not linked') ?></div>
                    </div>

                    <?php foreach ([
                        'Allergies' => $patient_info['allergies'],
                        'Existing conditions' => $patient_info['existing_condition'],
                        'Current medication' => $patient_info['current_medication'],
                        'Medical history' => $patient_info['medical_history'],
                    ] as $lbl => $val): if (trim((string) $val) === '') continue; ?>
                        <div class="pi-block"><span><?= $lbl ?></span><p><?= nl2br($e($val)) ?></p></div>
                    <?php endforeach; ?>

                    <?php if ($rx_docs): ?>
                        <div class="pi-block"><span>Reports uploaded for this visit</span>
                            <?php foreach ($rx_docs as $d): ?>
                                <a class="pi-doc" href="<?= $e(BASE_URL . ltrim($d['file_path'], '/')) ?>" target="_blank" rel="noopener">
                                    <i class="fas fa-paperclip"></i> <?= $e($d['document_name'] ?: 'Report') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($past_rx): ?>
                        <div class="pi-block"><span>Past prescriptions</span>
                            <ul class="pi-past">
                                <?php foreach ($past_rx as $p): ?>
                                    <li><strong><?= $e(date('d M Y', strtotime($p['visit_date']))) ?></strong>
                                        — <?= $e($p['diagnosis'] ?: 'No diagnosis noted') ?>
                                        <em class="pi-tag <?= $e($p['status']) ?>"><?= $e(ucfirst($p['status'])) ?></em></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="pi-empty">No linked patient account for this appointment (guest booking). Prescription can still be written and will be saved to the appointment.</p>
                <?php endif; ?>
            </div>

            <!-- Prescription tab -->
            <div class="sp-body sp-pane" data-pane="rx">
                <form id="rxForm" class="rx-form">
                    <div class="rx-status-row">
                        <span id="rxStatusPill" class="rx-pill <?= $existing_rx ? $e($existing_rx['status']) : 'none' ?>">
                            <?= $existing_rx ? $e(ucfirst($existing_rx['status'])) : 'Not started' ?>
                        </span>
                        <span id="rxSaveHint" class="rx-hint"></span>
                    </div>

                    <label>Chief complaints</label>
                    <textarea name="chief_complaints" rows="2" placeholder="What the patient reported…"><?= $e($existing_rx['chief_complaints'] ?? '') ?></textarea>

                    <label>Diagnosis</label>
                    <textarea name="diagnosis" rows="2" placeholder="Provisional / final diagnosis"><?= $e($existing_rx['diagnosis'] ?? '') ?></textarea>

                    <details class="rx-vitals">
                        <summary>Vitals (optional)</summary>
                        <div class="rx-vitals-grid">
                            <label>BP sys<input name="bp_sys" inputmode="numeric" value="<?= $e($rx_vitals['bp_systolic'] ?? '') ?>"></label>
                            <label>BP dia<input name="bp_dia" inputmode="numeric" value="<?= $e($rx_vitals['bp_diastolic'] ?? '') ?>"></label>
                            <label>Pulse<input name="pulse" inputmode="numeric" value="<?= $e($rx_vitals['pulse'] ?? '') ?>"></label>
                            <label>Temp °F<input name="temp" inputmode="decimal" value="<?= $e($rx_vitals['temperature'] ?? '') ?>"></label>
                            <label>SpO₂ %<input name="spo2" inputmode="numeric" value="<?= $e($rx_vitals['spo2'] ?? '') ?>"></label>
                            <label>Weight kg<input name="weight" inputmode="decimal" value="<?= $e($rx_vitals['weight_kg'] ?? '') ?>"></label>
                            <label>Height cm<input name="height" inputmode="decimal" value="<?= $e($rx_vitals['height_cm'] ?? '') ?>"></label>
                        </div>
                    </details>

                    <label>Medicines</label>
                    <div id="rxMeds"></div>
                    <button type="button" id="rxAddMed" class="rx-add"><i class="fas fa-plus"></i> Add medicine</button>

                    <label>Advice</label>
                    <textarea name="advice" rows="2" placeholder="Diet, rest, precautions…"><?= $e($existing_rx['advice'] ?? '') ?></textarea>

                    <label>Follow-up date</label>
                    <input type="date" name="follow_up_date" value="<?= $e($existing_rx['follow_up_date'] ?? '') ?>">

                    <div class="rx-actions">
                        <button type="button" id="rxSaveDraft" class="rx-btn draft"><i class="fas fa-save"></i> Save draft</button>
                        <button type="button" id="rxFinal" class="rx-btn final"><i class="fas fa-paper-plane"></i> Sign &amp; send</button>
                    </div>
                    <p class="rx-note">Patient sees it live and it is saved to their records &amp; the admin panel.</p>
                </form>

                <script type="application/json" id="rxSeed"><?= json_encode(['meds' => $rx_meds]) ?></script>
            </div>
        </div>
        <?php else: ?>
        <!-- Patient: prescription viewer -->
        <div id="rxPanel" class="side-panel">
            <div class="sp-head">
                <span><i class="fas fa-prescription me-2"></i>Prescription</span>
                <button class="chat-close" data-close-panel><i class="fas fa-times"></i></button>
            </div>
            <div class="sp-body" id="rxView">
                <p class="pi-empty">Your doctor hasn't shared a prescription yet. It will appear here during the call.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="room-controls">
        <button id="micBtn" class="ctrl-btn" title="Mute / unmute"><i class="fas fa-microphone"></i></button>
        <button id="camBtn" class="ctrl-btn" title="Camera on / off"><i class="fas fa-video"></i></button>
        <button id="chatBtn" class="ctrl-btn" data-panel="chatPanel" title="Chat"><i class="fas fa-comment-dots"></i></button>
        <?php if ($role === 'doctor'): ?>
        <button id="docBtn" class="ctrl-btn" data-panel="docPanel" title="Patient &amp; prescription"><i class="fas fa-notes-medical"></i></button>
        <?php else: ?>
        <button id="rxBtn" class="ctrl-btn" data-panel="rxPanel" title="Prescription" style="display:none"><i class="fas fa-prescription"></i></button>
        <?php endif; ?>
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
            rxUrl: <?= json_encode(BASE_URL . 'telemedicine/api/prescription.php') ?>,
            apptDetailsUrl: <?= json_encode($role === 'doctor' ? '' : (BASE_URL . 'user/appointment-details.php?id=' . $appointment_id)) ?>,
            exitUrl: <?= json_encode($role === 'doctor' ? (BASE_URL . 'doctor/appointments.php') : (BASE_URL . 'user/my-doctor-appointments.php')) ?>,
        };
    </script>
    <script src="assets/js/room.js"></script>
</body>

</html>
