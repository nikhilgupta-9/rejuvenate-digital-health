<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];

$success_message = '';
$error_message   = '';

$d = $conn->prepare("SELECT name, email, phone, doctor_uid, status, is_verified, verified_at, hpr_verified,
                            mobile_verified, added_on, last_login, notify_email, notify_whatsapp, grace_period_until
                     FROM doctors WHERE id = ? LIMIT 1");
$d->bind_param('i', $doctor_id);
$d->execute();
$doctor = $d->get_result()->fetch_assoc();
if (!$doctor) { header("Location: " . BASE_URL . "doctor-login.php"); exit(); }

/* ── Notification preferences ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    $ne = isset($_POST['notify_email']) ? 1 : 0;
    $nw = isset($_POST['notify_whatsapp']) ? 1 : 0;
    $u = $conn->prepare("UPDATE doctors SET notify_email = ?, notify_whatsapp = ? WHERE id = ?");
    $u->bind_param('iii', $ne, $nw, $doctor_id);
    $u->execute();
    $doctor['notify_email'] = $ne;
    $doctor['notify_whatsapp'] = $nw;
    $success_message = 'Notification preferences saved.';
}

/* ── Log out of all other devices ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_all'])) {
    $r1 = $conn->prepare("UPDATE jwt_refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE entity_type = 'doctor' AND entity_id = ? AND revoked = 0");
    $r1->bind_param('i', $doctor_id);
    $r1->execute();
    $conn->query("UPDATE doctor_sessions SET is_active = 0 WHERE doctor_id = " . $doctor_id);
    $success_message = 'Signed out of all devices. You will be asked to log in again shortly.';
}

/* ── Membership expiry ── */
$sub = $conn->prepare("SELECT MAX(expires_at) exp FROM doctor_subscriptions WHERE doctor_id = ? AND status = 'paid'");
$sub->bind_param('i', $doctor_id);
$sub->execute();
$membership_exp = $sub->get_result()->fetch_assoc()['exp'] ?? null;
$membership_active = $membership_exp && strtotime($membership_exp) > time();

/* ── Sessions ── */
$ses = $conn->prepare("SELECT ip_address, user_agent, login_time, last_activity, is_active
                       FROM doctor_sessions WHERE doctor_id = ? ORDER BY last_activity DESC LIMIT 12");
$ses->bind_param('i', $doctor_id);
$ses->execute();
$sessions = $ses->get_result()->fetch_all(MYSQLI_ASSOC);

function ua_short(string $ua): string
{
    if ($ua === '') return 'Unknown device';
    $b = 'Browser'; $o = '';
    if (stripos($ua, 'Edg') !== false) $b = 'Edge';
    elseif (stripos($ua, 'Chrome') !== false) $b = 'Chrome';
    elseif (stripos($ua, 'Firefox') !== false) $b = 'Firefox';
    elseif (stripos($ua, 'Safari') !== false) $b = 'Safari';
    if (stripos($ua, 'Android') !== false) $o = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua)) $o = 'iOS';
    elseif (stripos($ua, 'Windows') !== false) $o = 'Windows';
    elseif (stripos($ua, 'Mac OS') !== false) $o = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $o = 'Linux';
    return trim($b . ($o ? ' · ' . $o : ''));
}

$sidebar_active = 'settings';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Account Settings — REJUVENATE Doctor Portal</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
<style>
    .as-head h1{font-size:1.2rem;font-weight:800;color:#1f2937;margin:0;}
    .as-head .sub{font-size:.82rem;color:#9ca3af;}
    .kv{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #f3f4f6;font-size:.85rem;}
    .kv:last-child{border-bottom:none;}
    .kv .k{color:#6b7280;}
    .kv .v{font-weight:600;color:#1f2937;text-align:right;}
    .pill{font-size:.7rem;font-weight:700;border-radius:20px;padding:2px 9px;}
    .pill.ok{background:#dcfce7;color:#166534;}
    .pill.warn{background:#fef3c7;color:#92400e;}
    .pill.bad{background:#fee2e2;color:#991b1b;}
    .sess{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:.82rem;}
    .sess:last-child{border-bottom:none;}
    .danger-zone{border:1px solid #fecaca;border-radius:12px;padding:18px 20px;background:#fef2f2;}
    .form-switch .form-check-input{width:2.4em;}
</style>
</head>
<body>
<main class="doctor-content">

    <div class="as-head mb-3">
        <h1>Account Settings</h1>
        <div class="sub">Security, sessions, notifications and account status</div>
    </div>

    <?php foreach (['success' => $success_message, 'danger' => $error_message] as $k => $m): ?>
        <?php if ($m): ?>
            <div class="alert alert-<?= $k ?> alert-dismissible fade show"><?= htmlspecialchars($m) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="row">
        <div class="col-lg-6">

            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-id-badge mr-2" style="color:#0C74C5;"></i>Account</div>
                <div class="kv"><span class="k">Name</span><span class="v"><?= htmlspecialchars($doctor['name']) ?></span></div>
                <div class="kv"><span class="k">Doctor ID</span><span class="v"><?= htmlspecialchars($doctor['doctor_uid'] ?: '—') ?></span></div>
                <div class="kv"><span class="k">Email</span><span class="v"><?= htmlspecialchars($doctor['email']) ?></span></div>
                <div class="kv"><span class="k">Mobile</span><span class="v"><?= htmlspecialchars($doctor['phone']) ?>
                    <span class="pill <?= $doctor['mobile_verified'] ? 'ok' : 'warn' ?>"><?= $doctor['mobile_verified'] ? 'verified' : 'unverified' ?></span></span></div>
                <div class="kv"><span class="k">Account status</span><span class="v">
                    <span class="pill <?= $doctor['status'] === 'Active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars($doctor['status']) ?></span></span></div>
                <div class="kv"><span class="k">Admin verification</span><span class="v">
                    <span class="pill <?= $doctor['is_verified'] ? 'ok' : 'warn' ?>"><?= $doctor['is_verified'] ? 'Verified' : 'Pending' ?></span></span></div>
                <div class="kv"><span class="k">HPR / ABDM</span><span class="v">
                    <span class="pill <?= $doctor['hpr_verified'] ? 'ok' : 'warn' ?>"><?= $doctor['hpr_verified'] ? 'Verified' : 'Not verified' ?></span>
                    <a href="<?= BASE_URL ?>doctor/my-contact.php#hpr" class="ms-1" style="font-size:.75rem;">manage</a></span></div>
                <div class="kv"><span class="k">Membership</span><span class="v">
                    <?php if ($membership_active): ?>
                        <span class="pill ok">Active</span> till <?= date('d M Y', strtotime($membership_exp)) ?>
                    <?php else: ?>
                        <span class="pill warn"><?= $membership_exp ? 'Expired' : 'None' ?></span>
                        <a href="<?= BASE_URL ?>doctor-plans/" class="ms-1" style="font-size:.75rem;">plans</a>
                    <?php endif; ?>
                </span></div>
                <div class="kv"><span class="k">Member since</span><span class="v"><?= $doctor['added_on'] ? date('d M Y', strtotime($doctor['added_on'])) : '—' ?></span></div>
                <div class="kv"><span class="k">Last login</span><span class="v"><?= $doctor['last_login'] ? date('d M Y, h:i A', strtotime($doctor['last_login'])) : 'Never' ?></span></div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-bell mr-2" style="color:#0C74C5;"></i>Notifications</div>
                <form method="POST">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="notify_email" id="ne" <?= $doctor['notify_email'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ne">Email — appointments, prescriptions, account alerts</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="notify_whatsapp" id="nw" <?= $doctor['notify_whatsapp'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="nw">WhatsApp — new booking &amp; reminder messages</label>
                    </div>
                    <button type="submit" name="save_notifications" value="1" class="btn btn-sm btn-success"><i class="fa fa-save mr-1"></i>Save Preferences</button>
                    <div class="text-muted mt-2" style="font-size:.72rem;">Security-critical mails (password changes, verification) are always sent.</div>
                </form>
            </div>

        </div>

        <div class="col-lg-6">

            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-lock mr-2" style="color:#0C74C5;"></i>Login &amp; Security</div>
                <a href="<?= BASE_URL ?>doctor/change-password.php" class="btn btn-outline-primary btn-sm mb-3"><i class="fa fa-key mr-1"></i>Change Password</a>

                <div class="fw-semibold mb-1" style="font-size:.82rem;">Recent sessions</div>
                <?php if ($sessions): ?>
                    <?php foreach ($sessions as $s): ?>
                        <div class="sess">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars(ua_short($s['user_agent'] ?? '')) ?>
                                    <?php if (!empty($s['is_active'])): ?><span class="pill ok ms-1">active</span><?php endif; ?></div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    <?= htmlspecialchars($s['ip_address'] ?: '—') ?> ·
                                    last active <?= $s['last_activity'] ? date('d M, h:i A', strtotime($s['last_activity'])) : '—' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted" style="font-size:.8rem;">No session history recorded.</div>
                <?php endif; ?>

                <form method="POST" class="mt-3" onsubmit="return confirm('Sign out of all devices? You will need to log in again.');">
                    <button type="submit" name="logout_all" value="1" class="btn btn-sm btn-outline-danger">
                        <i class="fa fa-sign-out mr-1"></i>Log out of all devices
                    </button>
                </form>
            </div>

            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-exclamation-triangle mr-2" style="color:#dc2626;"></i>Danger Zone</div>
                <div class="danger-zone">
                    <div class="fw-semibold" style="color:#991b1b;">Delete your account</div>
                    <p class="mb-2" style="font-size:.8rem;color:#7f1d1d;">
                        Requests removal of your profile from the platform. Pending appointments and records are retained
                        per ABDM data-retention rules. An admin reviews every deletion request.
                    </p>
                    <a href="<?= BASE_URL ?>doctor/delete-account.php" class="btn btn-sm btn-outline-danger">
                        <i class="fa fa-trash mr-1"></i>Request Account Deletion
                    </a>
                </div>
            </div>

        </div>
    </div>
</main>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
