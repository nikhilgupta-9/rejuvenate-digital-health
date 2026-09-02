<?php
/**
 * Telemedicine / WebRTC self-test.
 *
 *  - Server side: config sanity (BASE_URL scheme, JWT secret, vendor, DB tables,
 *    ICE servers, server clock).
 *  - Browser side: secure-context, camera/mic (getUserMedia), a local
 *    RTCPeerConnection loopback, and a STUN/TURN reachability probe using the
 *    exact iceServers the real call uses.
 *
 * Open at:  https://<your-domain>/telemedicine/selftest.php   (admin login required)
 */
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/admin/auth/guard.php';

if (!admin_jwt_guard(true)) {
    http_response_code(403);
    exit('<p style="font-family:sans-serif;padding:24px">Log in to the admin panel first, then reload this page.</p>');
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);

/* ── Server-side checks ── */
$checks = [];

$scheme = parse_url(BASE_URL, PHP_URL_SCHEME);
$checks[] = [
    'label' => 'Site URL (BASE_URL)',
    'ok'    => $scheme === 'https',
    'value' => BASE_URL,
    'hint'  => $scheme === 'https' ? '' : 'Must be https:// on the live server. Set SITE=https://rejuvenatedigitalhealth.com/ in .env — camera access and the signaling fetches are blocked on http.',
];

$checks[] = [
    'label' => 'Ticket signing secret (JWT_SECRET)',
    'ok'    => defined('JWT_SECRET') && JWT_SECRET && JWT_SECRET !== 'change-me-telemed-secret' && strlen(JWT_SECRET) >= 16,
    'value' => (defined('JWT_SECRET') && JWT_SECRET) ? 'set (' . strlen(JWT_SECRET) . ' chars)' : 'MISSING',
    'hint'  => 'Set a long random JWT_SECRET in .env. Join tickets and guest links are signed with it; if it changes, older links stop working.',
];

$checks[] = [
    'label' => 'Composer dependencies (vendor/autoload.php)',
    'ok'    => file_exists(dirname(__DIR__) . '/vendor/autoload.php'),
    'value' => file_exists(dirname(__DIR__) . '/vendor/autoload.php') ? 'present' : 'MISSING',
    'hint'  => 'Run "composer install" on the server (or upload the vendor/ folder).',
];

$needTables = ['telemedicine_rooms', 'telemedicine_signals', 'telemedicine_chat_messages'];
foreach ($needTables as $t) {
    $r = @$conn->query("SELECT COUNT(*) c FROM `$t`");
    $checks[] = [
        'label' => "DB table: $t",
        'ok'    => (bool) $r,
        'value' => $r ? ($r->fetch_assoc()['c'] . ' rows') : 'MISSING',
        'hint'  => $r ? '' : 'Run database/migration_telemedicine.sql and database/migration_telemedicine_polling.sql on the server DB.',
    ];
}

$colChk = @$conn->query("SHOW COLUMNS FROM appointments LIKE 'meeting_event_id'");
$checks[] = [
    'label' => 'appointments.meeting_* columns',
    'ok'    => $colChk && $colChk->num_rows > 0,
    'value' => ($colChk && $colChk->num_rows) ? 'present' : 'MISSING',
    'hint'  => 'These columns hold the room token / status. A full DB import already has them.',
];

$ice = json_decode(TELEMED_ICE_SERVERS, true) ?: [];
$hasTurn = false;
foreach ($ice as $s) {
    $urls = (array) ($s['urls'] ?? []);
    foreach ($urls as $u) if (stripos($u, 'turn:') === 0 || stripos($u, 'turns:') === 0) $hasTurn = true;
}
$checks[] = [
    'label' => 'ICE servers configured',
    'ok'    => count($ice) > 0,
    'value' => count($ice) . ' server(s)' . ($hasTurn ? ' · incl. TURN' : ' · STUN only'),
    'hint'  => $hasTurn ? '' : 'STUN-only is fine for most networks. Add a TURN server in admin → Telemedicine Settings if calls fail across mobile data / strict firewalls.',
];

$dbTime = @$conn->query("SELECT NOW() n")->fetch_assoc()['n'] ?? '';
$checks[] = [
    'label' => 'Server clock',
    'ok'    => true,
    'value' => 'PHP ' . date('Y-m-d H:i:s') . '  ·  MySQL ' . $dbTime,
    'hint'  => 'PHP and MySQL times should match and be roughly correct — presence timeout is 6s.',
];

$openAppt = @$conn->query("SELECT a.id, a.appointment_date, a.appointment_time, a.status, d.name dn, u.name un
    FROM appointments a JOIN doctors d ON d.id=a.doctor_id LEFT JOIN users u ON u.id=a.user_id
    WHERE a.appointment_type='online' AND a.status='approved'
    ORDER BY a.appointment_date DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Telemedicine Self-Test</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body { background:#f4f7fb; font-family:'Segoe UI',system-ui,sans-serif; padding:24px 14px 60px; }
  .wrap { max-width:860px; margin:0 auto; }
  h1 { font-size:1.35rem; font-weight:800; }
  .card { border:0; box-shadow:0 1px 8px rgba(0,0,0,.06); border-radius:12px; margin-bottom:18px; }
  .chk { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid #f0f2f5; font-size:.9rem; }
  .chk:last-child { border-bottom:0; }
  .chk .ico { flex:0 0 20px; font-weight:700; }
  .ok { color:#16a34a; } .bad { color:#dc2626; } .warn { color:#d97706; }
  .chk .lbl { flex:0 0 220px; font-weight:600; color:#1f2937; }
  .chk .val { color:#374151; }
  .chk .hint { font-size:.8rem; color:#b45309; margin-top:3px; }
  video { width:100%; max-width:320px; background:#000; border-radius:10px; }
  pre { background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; font-size:.78rem; max-height:240px; overflow:auto; }
  .pill { display:inline-block; font-size:.72rem; font-weight:700; border-radius:20px; padding:3px 10px; }
  .pill.g { background:#dcfce7; color:#15803d; } .pill.r { background:#fee2e2; color:#b91c1c; } .pill.y { background:#fef3c7; color:#92400e; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🩺 Telemedicine / WebRTC self-test</h1>
  <p class="text-muted" style="font-size:.88rem;">Run this on the live server. Green everywhere on the server checks + a working camera + a <code>connected</code> loopback + at least one <code>srflx</code> candidate = WebRTC will work for most patients.</p>

  <div class="card"><div class="card-body">
    <h6 class="fw-bold mb-3">1 · Server configuration</h6>
    <?php foreach ($checks as $c): ?>
      <div class="chk">
        <div class="ico <?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? '✓' : '✕' ?></div>
        <div class="lbl"><?= $e($c['label']) ?></div>
        <div class="val"><?= $e($c['value']) ?>
          <?php if (!empty($c['hint'])): ?><div class="hint">⚠ <?= $e($c['hint']) ?></div><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div></div>

  <div class="card"><div class="card-body">
    <h6 class="fw-bold mb-2">2 · Browser secure context &amp; devices</h6>
    <div id="ctxResult" class="mb-2"></div>
    <div class="d-flex gap-3 flex-wrap align-items-start">
      <video id="preview" autoplay playsinline muted></video>
      <div>
        <button id="camBtn" class="btn btn-primary btn-sm">Test camera &amp; mic</button>
        <div id="camResult" class="mt-2" style="font-size:.85rem;"></div>
        <ul id="devList" style="font-size:.8rem; color:#475569;"></ul>
      </div>
    </div>
  </div></div>

  <div class="card"><div class="card-body">
    <h6 class="fw-bold mb-2">3 · WebRTC stack (local loopback)</h6>
    <p class="text-muted" style="font-size:.82rem;">Connects two peer connections to each other inside this page — proves the browser's WebRTC engine works.</p>
    <button id="loopBtn" class="btn btn-outline-primary btn-sm">Run loopback test</button>
    <span id="loopResult" class="ms-2" style="font-size:.88rem;"></span>
  </div></div>

  <div class="card"><div class="card-body">
    <h6 class="fw-bold mb-2">4 · STUN / TURN reachability</h6>
    <p class="text-muted" style="font-size:.82rem;">Uses the exact ICE servers the real call uses. <b>host</b> = your LAN, <b>srflx</b> = STUN reachable (good), <b>relay</b> = TURN reachable.</p>
    <button id="iceBtn" class="btn btn-outline-primary btn-sm">Probe ICE servers</button>
    <div id="iceSummary" class="mt-2" style="font-size:.88rem;"></div>
    <pre id="iceLog" class="mt-2" style="display:none;"></pre>
  </div></div>

  <div class="card"><div class="card-body">
    <h6 class="fw-bold mb-2">5 · Try a real call</h6>
    <?php if ($openAppt && $openAppt->num_rows): ?>
      <p style="font-size:.85rem;">Open online appointments you can test with (open the doctor link on one device, the patient link on another):</p>
      <table class="table table-sm" style="font-size:.83rem;">
        <thead><tr><th>#</th><th>When</th><th>Doctor</th><th>Patient</th><th>Join</th></tr></thead>
        <tbody>
        <?php while ($a = $openAppt->fetch_assoc()): ?>
          <tr>
            <td>AP<?= str_pad($a['id'], 6, '0', STR_PAD_LEFT) ?></td>
            <td><?= $e(date('d M, h:i A', strtotime($a['appointment_date'] . ' ' . $a['appointment_time']))) ?></td>
            <td><?= $e($a['dn']) ?></td>
            <td><?= $e($a['un'] ?: 'Guest') ?></td>
            <td><a href="<?= $e(BASE_URL) ?>telemedicine/join.php?appointment_id=<?= (int) $a['id'] ?>" target="_blank">join.php</a></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="font-size:.85rem;" class="text-muted">No <b>online + approved</b> appointments found. Book one with mode = "Online Consultation", approve it in admin, then it shows here.</p>
    <?php endif; ?>
    <p style="font-size:.82rem;color:#64748b;margin-bottom:0;">Expected in a real call: both sides land on room.php → "Connecting…" → within ~2–6s "Connected" with two-way video. The doctor's browser always sends the WebRTC offer.</p>
  </div></div>
</div>

<script>
const ICE_SERVERS = <?= TELEMED_ICE_SERVERS ?>;

/* 2 · secure context + devices */
(function () {
  const r = document.getElementById('ctxResult');
  const secure = window.isSecureContext;
  const hasGUM = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  r.innerHTML =
    `<span class="pill ${secure ? 'g' : 'r'}">isSecureContext: ${secure}</span> ` +
    `<span class="pill ${hasGUM ? 'g' : 'r'}">getUserMedia: ${hasGUM ? 'available' : 'missing'}</span>` +
    (secure ? '' : '<div class="hint" style="color:#b91c1c">Not a secure context — camera/mic will not work. The site must be served over HTTPS.</div>');
})();

document.getElementById('camBtn').addEventListener('click', async function () {
  const res = document.getElementById('camResult');
  res.innerHTML = 'Requesting permission…';
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    document.getElementById('preview').srcObject = stream;
    const v = stream.getVideoTracks()[0], a = stream.getAudioTracks()[0];
    res.innerHTML = `<span class="ok">✓ camera + mic OK</span> — video: ${v ? v.label : 'none'} · audio: ${a ? a.label : 'none'}`;
    const devs = await navigator.mediaDevices.enumerateDevices();
    document.getElementById('devList').innerHTML = devs.map(d => `<li>${d.kind}: ${d.label || '(hidden until permission)'} </li>`).join('');
    setTimeout(() => stream.getTracks().forEach(t => t.stop()), 4000);
  } catch (err) {
    res.innerHTML = `<span class="bad">✕ ${err.name}: ${err.message}</span>`;
  }
});

/* 3 · loopback */
document.getElementById('loopBtn').addEventListener('click', async function () {
  const out = document.getElementById('loopResult');
  out.textContent = 'running…';
  try {
    const a = new RTCPeerConnection(), b = new RTCPeerConnection();
    a.onicecandidate = e => e.candidate && b.addIceCandidate(e.candidate);
    b.onicecandidate = e => e.candidate && a.addIceCandidate(e.candidate);
    const dc = a.createDataChannel('t');
    const done = new Promise((res, rej) => {
      dc.onopen = () => res('open');
      setTimeout(() => rej(new Error('timeout')), 8000);
    });
    b.ondatachannel = ev => { ev.channel.onmessage = () => {}; };
    await a.setLocalDescription(await a.createOffer());
    await b.setRemoteDescription(a.localDescription);
    await b.setLocalDescription(await b.createAnswer());
    await a.setRemoteDescription(b.localDescription);
    await done;
    out.innerHTML = '<span class="ok">✓ connected — WebRTC engine works</span>';
    a.close(); b.close();
  } catch (err) {
    out.innerHTML = `<span class="bad">✕ ${err.message}</span>`;
  }
});

/* 4 · ICE probe */
document.getElementById('iceBtn').addEventListener('click', function () {
  const sum = document.getElementById('iceSummary');
  const log = document.getElementById('iceLog');
  log.style.display = 'block'; log.textContent = '';
  sum.textContent = 'gathering candidates (10s)…';
  const seen = { host: 0, srflx: 0, relay: 0, prflx: 0 };
  const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
  pc.createDataChannel('probe');
  pc.onicecandidate = (e) => {
    if (!e.candidate) return;
    const c = e.candidate.candidate;
    const m = c.match(/ typ (\w+)/);
    if (m && seen[m[1]] !== undefined) seen[m[1]]++;
    log.textContent += c + '\n';
  };
  pc.onicegatheringstatechange = () => {
    if (pc.iceGatheringState === 'complete') finish();
  };
  pc.createOffer().then(o => pc.setLocalDescription(o));
  const timer = setTimeout(finish, 10000);
  function finish() {
    clearTimeout(timer);
    try { pc.close(); } catch (e) {}
    const stun = seen.srflx > 0, turn = seen.relay > 0;
    sum.innerHTML =
      `<span class="pill ${seen.host ? 'g' : 'y'}">host: ${seen.host}</span> ` +
      `<span class="pill ${stun ? 'g' : 'r'}">srflx (STUN): ${seen.srflx}</span> ` +
      `<span class="pill ${turn ? 'g' : 'y'}">relay (TURN): ${seen.relay}</span>` +
      (stun ? '' : '<div class="hint" style="color:#b91c1c">No STUN candidate — the browser can\'t reach the STUN server (network blocking UDP, or STUN URL wrong). Calls across different networks may fail.</div>') +
      (!turn ? '<div class="hint">No TURN — fine for most, but calls behind strict NAT / some mobile carriers need a TURN server.</div>' : '');
  }
});
</script>
</body>
</html>
