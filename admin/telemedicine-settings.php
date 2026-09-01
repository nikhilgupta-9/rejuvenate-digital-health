<?php
/**
 * Admin → Telemedicine → System Settings
 *
 * Configure the WebRTC video-consultation module (ICE / TURN servers,
 * signaling poll interval) and see an at-a-glance readiness check.
 * The telemedicine module itself lives in /telemedicine/.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

/* ── key/value settings store (created on first visit) ── */
$conn->query("CREATE TABLE IF NOT EXISTS telemedicine_settings (
    setting_key   VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$FIELDS = ['turn_url', 'turn_username', 'turn_credential', 'extra_stun', 'poll_interval_ms'];

/* ── save ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_telemed_settings'])) {
    $stmt = $conn->prepare("INSERT INTO telemedicine_settings (setting_key, setting_value) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($FIELDS as $k) {
        $v = trim($_POST[$k] ?? '');
        if ($k === 'poll_interval_ms') {
            $v = (string) max(500, min(10000, (int) ($v ?: 2000)));
        }
        $stmt->bind_param('ss', $k, $v);
        $stmt->execute();
    }
    $_SESSION['success_message'] = 'Telemedicine settings saved.';
    header('Location: telemedicine-settings.php');
    exit;
}

/* ── current values ── */
$cfg = [];
$res = $conn->query("SELECT setting_key, setting_value FROM telemedicine_settings");
while ($r = $res->fetch_assoc()) $cfg[$r['setting_key']] = $r['setting_value'];
$get = fn($k, $d = '') => htmlspecialchars($cfg[$k] ?? $d);

/* ── readiness checks ── */
$is_https      = stripos(BASE_URL, 'https://') === 0;
$is_localhost  = preg_match('#^https?://(localhost|127\.0\.0\.1)#i', BASE_URL);
$jwt_ok        = defined('JWT_SECRET') && JWT_SECRET !== '' && JWT_SECRET !== 'change-me-telemed-secret';
$tbl = fn($t) => (bool) $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'")->num_rows;
$tables_ok     = $tbl('telemedicine_rooms') && $tbl('telemedicine_signals') && $tbl('telemedicine_chat_messages');
$turn_ok       = !empty($cfg['turn_url']);

/* ── quick stats ── */
$stat = fn($sql) => (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
$s_online    = $stat("SELECT COUNT(*) c FROM appointments WHERE appointment_type='online'");
$s_started   = $stat("SELECT COUNT(*) c FROM appointments WHERE meeting_status IN ('started','completed')");
$s_completed = $stat("SELECT COUNT(*) c FROM appointments WHERE meeting_status='completed'");
$s_live      = $stat("SELECT COUNT(*) c FROM telemedicine_rooms
                      WHERE doctor_last_seen  >= (NOW() - INTERVAL 30 SECOND)
                        AND patient_last_seen >= (NOW() - INTERVAL 30 SECOND)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Telemedicine Settings | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .check-row { display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid #eef0f5; }
        .check-row:last-child { border-bottom:0; }
        .check-ico { width:26px; height:26px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:12px; }
        .check-ok   { background:#e4f6ea; color:#16a34a; }
        .check-warn { background:#fef3e2; color:#d97706; }
        .check-bad  { background:#fdecec; color:#dc2626; }
        .check-row .ct { font-weight:600; font-size:.9rem; }
        .check-row .cd { font-size:.82rem; color:#6b7089; margin-top:2px; }
        code.inline { background:#f1f3f9; padding:1px 6px; border-radius:5px; font-size:.82em; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Telemedicine Settings</h4>
                        <small class="text-muted">WebRTC video consultation — ICE / TURN servers, signaling and health checks</small>
                    </div>
                    <a href="video-call-history.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-history me-1"></i> Call History</a>
                </div>

                <?php if (!empty($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-video big-icon"></i><div class="num"><?= $s_online ?></div><div class="lbl">Online Appointments</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-play-circle big-icon"></i><div class="num"><?= $s_started ?></div><div class="lbl">Calls Started</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-check-double big-icon"></i><div class="num"><?= $s_completed ?></div><div class="lbl">Calls Completed</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-broadcast-tower big-icon"></i><div class="num"><?= $s_live ?></div><div class="lbl">Live Right Now</div></div></div>
                </div>

                <div class="row g-4">
                    <!-- Readiness -->
                    <div class="col-lg-5">
                        <div class="white_card">
                            <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">System Readiness</h3></div></div></div>
                            <div class="white_card_body">

                                <div class="check-row">
                                    <div class="check-ico <?= $is_https || $is_localhost ? 'check-ok' : 'check-bad' ?>">
                                        <i class="fas fa-<?= $is_https || $is_localhost ? 'check' : 'times' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="ct">Secure context (HTTPS)</div>
                                        <div class="cd">
                                            <?php if ($is_https): ?>
                                                Served over HTTPS — camera &amp; microphone access will work.
                                            <?php elseif ($is_localhost): ?>
                                                Running on localhost (browsers treat this as secure). <strong>Production must be HTTPS</strong> or <code class="inline">getUserMedia()</code> is blocked and no call can start.
                                            <?php else: ?>
                                                <strong>Site is on plain HTTP.</strong> Browsers block camera/mic on insecure origins — video calls will not start. Install an SSL certificate and set <code class="inline">SITE=https://…</code> in <code class="inline">.env</code>.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="check-row">
                                    <div class="check-ico <?= $tables_ok ? 'check-ok' : 'check-bad' ?>"><i class="fas fa-<?= $tables_ok ? 'check' : 'times' ?>"></i></div>
                                    <div>
                                        <div class="ct">Database tables</div>
                                        <div class="cd"><?= $tables_ok
                                            ? 'telemedicine_rooms, telemedicine_signals, telemedicine_chat_messages present.'
                                            : 'Missing — run database/migration_telemedicine.sql and database/migration_telemedicine_polling.sql.' ?></div>
                                    </div>
                                </div>

                                <div class="check-row">
                                    <div class="check-ico <?= $jwt_ok ? 'check-ok' : 'check-bad' ?>"><i class="fas fa-<?= $jwt_ok ? 'check' : 'times' ?>"></i></div>
                                    <div>
                                        <div class="ct">Join-ticket signing key</div>
                                        <div class="cd"><?= $jwt_ok ? 'JWT_SECRET is set — join tickets are signed.' : 'JWT_SECRET missing / default in .env — call links cannot be trusted.' ?></div>
                                    </div>
                                </div>

                                <div class="check-row">
                                    <div class="check-ico check-ok"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="ct">STUN server</div>
                                        <div class="cd">Public Google STUN configured. Handles most home / office networks.</div>
                                    </div>
                                </div>

                                <div class="check-row">
                                    <div class="check-ico <?= $turn_ok ? 'check-ok' : 'check-warn' ?>"><i class="fas fa-<?= $turn_ok ? 'check' : 'exclamation-triangle' ?>"></i></div>
                                    <div>
                                        <div class="ct">TURN relay server</div>
                                        <div class="cd"><?= $turn_ok
                                            ? 'Configured — calls behind strict firewalls / mobile networks can still connect.'
                                            : 'Not configured. Calls from strict corporate firewalls or some mobile carriers may fail to connect. Add a TURN server below (e.g. self-hosted coturn or a managed service).' ?></div>
                                    </div>
                                </div>

                                <div class="check-row">
                                    <div class="check-ico check-ok"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="ct">Signaling transport</div>
                                        <div class="cd">HTTP polling (every <?= (int) ($cfg['poll_interval_ms'] ?? 2000) ?> ms) — no WebSocket / background process needed. Works on shared hosting.</div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Settings form -->
                    <div class="col-lg-7">
                        <div class="white_card">
                            <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">ICE / TURN Configuration</h3></div></div></div>
                            <div class="white_card_body">
                                <form method="post" class="row g-3">
                                    <input type="hidden" name="save_telemed_settings" value="1">

                                    <div class="col-md-12">
                                        <label class="form-label">TURN server URL(s)</label>
                                        <input type="text" name="turn_url" class="form-control" value="<?= $get('turn_url') ?>"
                                               placeholder="turn:turn.example.com:3478, turns:turn.example.com:5349">
                                        <small class="text-muted">Comma-separated. Leave blank to use STUN only.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">TURN username</label>
                                        <input type="text" name="turn_username" class="form-control" value="<?= $get('turn_username') ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">TURN credential / password</label>
                                        <input type="text" name="turn_credential" class="form-control" value="<?= $get('turn_credential') ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Additional STUN server(s) <span class="text-muted fw-normal">(optional)</span></label>
                                        <input type="text" name="extra_stun" class="form-control" value="<?= $get('extra_stun') ?>"
                                               placeholder="stun:stun.example.com:3478">
                                        <small class="text-muted">Comma-separated. Google STUN is always included.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Signaling poll interval (ms)</label>
                                        <input type="number" name="poll_interval_ms" class="form-control" min="500" max="10000" step="250"
                                               value="<?= $get('poll_interval_ms', '2000') ?>">
                                        <small class="text-muted">Lower = snappier call setup &amp; chat, more requests. 2000 is a good default.</small>
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Settings</button>
                                    </div>
                                </form>

                                <hr class="my-4">
                                <h6 class="fw-bold mb-2" style="font-size:.85rem;">Effective ICE server list (sent to the browser)</h6>
                                <pre class="mb-0 p-3" style="background:#0f172a;color:#cbd5e1;border-radius:10px;font-size:.8rem;overflow:auto;"><?php
                                    require_once dirname(__DIR__) . '/telemedicine/config.php';
                                    echo htmlspecialchars(json_encode(json_decode(TELEMED_ICE_SERVERS), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                ?></pre>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
</body>
</html>
