<?php
include_once "../../config/connect.php";
include_once "auth.php";

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

/* Digital Health ID Card — lazily generate a share token so the QR link
   can't be guessed (unlike the sequential member_uid). */
if (empty($student['share_token'])) {
  $share_token = bin2hex(random_bytes(20));
  $tok_stmt = $conn->prepare("UPDATE school_members SET share_token=? WHERE id=?");
  $tok_stmt->bind_param('si', $share_token, $student_id);
  $tok_stmt->execute();
  $student['share_token'] = $share_token;
}
$health_card_url = rtrim(BASE_URL, '/') . '/school/student/health-card.php?t=' . $student['share_token'];

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'change_password') {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $cnf = $_POST['confirm_password'] ?? '';

    if (!$student['password'] || !password_verify($cur, $student['password'])) {
      $error = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
      $error = "New password must be at least 6 characters.";
    } elseif ($new !== $cnf) {
      $error = "New passwords do not match.";
    } else {
      $hash = password_hash($new, PASSWORD_DEFAULT);
      $upd = $conn->prepare("UPDATE school_members SET password=? WHERE id=?");
      $upd->bind_param('si', $hash, $student_id);
      $upd->execute();
      $success = "Password updated successfully.";
      $stmt->execute();
      $student = $stmt->get_result()->fetch_assoc();
    }
  }

  if ($action === 'update_contact') {
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($phone && !preg_match('/^[6-9]\d{9}$/', $phone)) {
      $error = "Enter a valid 10-digit Indian mobile number.";
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = "Enter a valid email address.";
    } else {
      $upd = $conn->prepare("UPDATE school_members SET phone=?, email=? WHERE id=?");
      $upd->bind_param('ssi', $phone, $email, $student_id);
      $upd->execute();
      $success = "Contact details updated.";
      $stmt->execute();
      $student = $stmt->get_result()->fetch_assoc();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile | <?= htmlspecialchars($student_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root {
      --primary: #0C74C5;
      --accent: #02c9b8;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #f4f7fb;
      margin: 0;
    }

    .s-topnav {
      background: var(--primary);
      color: #fff;
      padding: 0 16px;
      height: 58px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 10px rgba(12, 116, 197, .3);
    }

    .s-topnav .brand {
      font-size: .9rem;
      font-weight: 700;
    }

    .s-topnav .sub {
      font-size: .68rem;
      opacity: .75;
    }

    .s-body {
      max-width: 600px;
      margin: 0 auto;
      padding: 18px 14px 90px;
    }

    /* Hero */
    .profile-hero {
      background: var(--primary);
      border-radius: 16px;
      color: #fff;
      padding: 22px 20px;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 16px;
      position: relative;
      overflow: hidden;
    }

    .profile-hero::after {
      content: '';
      position: absolute;
      right: -30px;
      top: -30px;
      width: 140px;
      height: 140px;
      background: rgba(255, 255, 255, .06);
      border-radius: 50%;
    }

    .p-avatar {
      width: 58px;
      height: 58px;
      border-radius: 15px;
      background: rgba(255, 255, 255, .2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      font-weight: 700;
      flex-shrink: 0;
      position: relative;
      z-index: 1;
    }

    /* Section cards */
    .s-card {
      background: #fff;
      border-radius: 12px;
      padding: 16px 18px;
      margin-bottom: 14px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .07);
    }

    .s-card-title {
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: #6b7280;
      display: flex;
      align-items: center;
      gap: 7px;
      margin-bottom: 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid #f3f4f6;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #f3f4f6;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-row .lbl {
      font-size: .74rem;
      color: #6b7280;
    }

    .info-row .val {
      font-size: .82rem;
      font-weight: 600;
      color: #1f2937;
      text-align: right;
    }

    .pass-wrap {
      position: relative;
    }

    .pass-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #9ca3af;
      cursor: pointer;
      padding: 0;
    }

    /* Digital Health ID Card */
    .id-card {
      background: linear-gradient(135deg, var(--primary), #085a99);
      border-radius: 18px; color: #fff; padding: 22px; position: relative; overflow: hidden;
    }
    .id-card::after { content:''; position:absolute; right:-40px; bottom:-40px; width:170px; height:170px; background:rgba(255,255,255,.07); border-radius:50%; }
    .id-card-top { display: flex; align-items: center; gap: 14px; position: relative; z-index: 1; }
    .id-card-photo { width: 58px; height: 58px; border-radius: 14px; object-fit: cover; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; flex-shrink: 0; }
    .id-card-name { font-size: 1rem; font-weight: 700; }
    .id-card-meta { font-size: .75rem; opacity: .82; margin-top: 2px; }
    .id-card-uid { font-size: .68rem; opacity: .65; font-family: monospace; margin-top: 3px; }
    .id-card-body { display: flex; align-items: center; gap: 16px; margin-top: 18px; position: relative; z-index: 1; }
    .id-card-qr { background: #fff; border-radius: 10px; padding: 8px; flex-shrink: 0; line-height: 0; }
    .id-card-hint { font-size: .72rem; opacity: .85; }
    .id-card-blood { display: inline-flex; align-items: center; background: rgba(255,255,255,.18); border-radius: 20px; padding: 3px 12px; font-size: .72rem; font-weight: 700; margin-top: 6px; }
    .id-card-actions { display: flex; gap: 8px; margin-top: 16px; }
    .id-card-actions button { flex: 1; border: none; border-radius: 9px; padding: 9px 6px; font-size: .74rem; font-weight: 600; background: #fff; color: var(--primary); }
    .id-card-actions button:active { transform: scale(.97); }

    /* Bottom nav */
    .s-bottomnav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #fff;
      border-top: 1px solid #e5e7eb;
      display: flex;
      z-index: 99;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, .06);
    }

    .s-bottomnav a {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 9px 4px;
      text-decoration: none;
      color: #9ca3af;
      font-size: .58rem;
      font-weight: 600;
      gap: 3px;
      transition: color .15s;
    }

    .s-bottomnav a i {
      font-size: 1.05rem;
    }

    .s-bottomnav a.active {
      color: var(--primary);
    }
  </style>
</head>

<body>

  <nav class="s-topnav">
    <div>
      <div class="brand"><i class="fas fa-user-circle me-2" style="color:var(--accent);"></i>My Profile</div>
      <div class="sub"><?= htmlspecialchars($student_school) ?></div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
        style="width:32px;height:32px;background:rgba(255,255,255,.2);font-size:.82rem;flex-shrink:0;">
        <?= strtoupper(substr($student_name, 0, 1)) ?>
      </div>
    </div>
  </nav>

  <div class="s-body">

    <!-- Hero -->
    <div class="profile-hero">
      <div class="p-avatar"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
      <div style="position:relative;z-index:1;flex:1;min-width:0;">
        <div style="font-size:1.02rem;font-weight:700;"><?= htmlspecialchars($student_name) ?></div>
        <div style="font-size:.78rem;opacity:.82;margin-top:3px;">
          <?php if ($student['class']): ?>Class
            <?= htmlspecialchars($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) ?><?php endif; ?>
          <?php if ($student['roll_number']): ?> &middot; Roll:
            <?= htmlspecialchars($student['roll_number']) ?><?php endif; ?>
        </div>
        <div style="font-size:.7rem;opacity:.65;margin-top:3px;font-family:monospace;">
          <?= htmlspecialchars($student_uid) ?></div>
      </div>
      <div style="position:relative;z-index:1;flex-shrink:0;">
        <?php if ($student['abha_linked']): ?>
          <span
            style="background:rgba(255,255,255,.2);border-radius:20px;padding:4px 10px;font-size:.68rem;font-weight:700;white-space:nowrap;">
            <i class="fas fa-id-card me-1"></i>ABHA Linked
          </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show" style="border-radius:12px;font-size:.83rem;">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" style="border-radius:12px;font-size:.83rem;">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Digital Health ID Card -->
    <div class="s-card" style="padding:0;background:transparent;box-shadow:none;">
      <div class="id-card" id="healthIdCard">
        <div class="id-card-top">
          <?php if (!empty($student['profile_pic']) && file_exists('../../' . $student['profile_pic'])): ?>
            <img class="id-card-photo" src="../../<?= htmlspecialchars($student['profile_pic']) ?>" alt="photo">
          <?php else: ?>
            <div class="id-card-photo"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
          <?php endif; ?>
          <div style="flex:1;min-width:0;">
            <div class="id-card-name"><?= htmlspecialchars($student_name) ?></div>
            <div class="id-card-meta">
              <?php if ($student['class']): ?>Class <?= htmlspecialchars($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) ?><?php endif; ?>
              <?php if ($student['roll_number']): ?> &middot; Roll: <?= htmlspecialchars($student['roll_number']) ?><?php endif; ?>
              &middot; <?= htmlspecialchars($student_school) ?>
            </div>
            <div class="id-card-uid"><?= htmlspecialchars($student_uid) ?></div>
            <?php if (!empty($student['blood_group'])): ?>
              <div class="id-card-blood"><i class="fas fa-tint me-1"></i><?= htmlspecialchars($student['blood_group']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="id-card-body">
          <div class="id-card-qr" id="healthQrCode"></div>
          <div class="id-card-hint">
            <i class="fas fa-qrcode me-1"></i>Scan this QR to view <?= htmlspecialchars($student_name) ?>'s emergency health profile &mdash; blood group, allergies &amp; emergency contact.
          </div>
        </div>
        <div class="id-card-actions">
          <button type="button" id="shareIdCardBtn"><i class="fas fa-share-alt me-1"></i>Share</button>
          <button type="button" id="downloadIdCardBtn"><i class="fas fa-download me-1"></i>Download</button>
          <button type="button" id="copyIdCardBtn"><i class="fas fa-link me-1"></i>Copy Link</button>
        </div>
      </div>
    </div>

    <!-- School Info (read-only) -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-user" style="color:var(--primary);"></i>Personal Details</div>
      <div class="info-row"><span class="lbl"><i class="fas fa-school me-1"></i>School</span><span
          class="val"><?= htmlspecialchars($student_school) ?></span></div>
      <div class="info-row"><span class="lbl"><i class="fas fa-chalkboard me-1"></i>Class</span>
        <span class="val">
          <?= $student['class'] ? htmlspecialchars($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) : '—' ?>
        </span>
      </div>
      <div class="info-row"><span class="lbl"><i class="fas fa-hashtag me-1"></i>Roll No.</span><span
          class="val"><?= htmlspecialchars($student['roll_number'] ?? '—') ?></span></div>
      <div class="info-row"><span class="lbl"><i class="fas fa-fingerprint me-1"></i>Member UID</span><span class="val"
          style="font-family:monospace;font-size:.74rem;"><?= htmlspecialchars($student_uid) ?></span></div>
      <div class="info-row"><span class="lbl"><i class="fas fa-clock me-1"></i>Last Login</span><span
          class="val"><?= $student['last_login'] ? date('d M Y, h:i A', strtotime($student['last_login'])) : 'First login' ?></span>
      </div>
    </div>

    <!-- Contact Details (editable) -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-address-book" style="color:#16a34a;"></i>Contact Details</div>
      <form method="POST">
        <input type="hidden" name="action" value="update_contact">
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" class="form-control" name="email"
              value="<?= htmlspecialchars($student['email'] ?? '') ?>" placeholder="you@example.com" readonly>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">Mobile Number</label>
          <div class="input-group">
            <span class="input-group-text">+91</span>
            <input type="text" class="form-control" name="phone" inputmode="numeric" maxlength="10"
              value="<?= htmlspecialchars($student['phone'] ?? '') ?>" placeholder="10-digit mobile">
          </div>
        </div>
        <button type="submit" class="btn btn-success btn-sm fw-semibold px-4">
          <i class="fas fa-save me-1"></i>Update Contact
        </button>
      </form>
    </div>

    <!-- Change Password -->
    <?php if ($student['password']): ?>
      <div class="s-card">
        <div class="s-card-title"><i class="fas fa-key" style="color:#dc2626;"></i>Change Password</div>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Current Password</label>
            <div class="pass-wrap">
              <input type="password" class="form-control" name="current_password" id="cur_pw" required>
              <button type="button" class="pass-toggle" onclick="togglePw('cur_pw',this)"><i
                  class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem;">New Password</label>
            <div class="pass-wrap">
              <input type="password" class="form-control" name="new_password" id="new_pw" required minlength="6">
              <button type="button" class="pass-toggle" onclick="togglePw('new_pw',this)"><i
                  class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Confirm New Password</label>
            <div class="pass-wrap">
              <input type="password" class="form-control" name="confirm_password" id="cnf_pw" required minlength="6">
              <button type="button" class="pass-toggle" onclick="togglePw('cnf_pw',this)"><i
                  class="fas fa-eye"></i></button>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm fw-semibold px-4">
            <i class="fas fa-key me-1"></i>Update Password
          </button>
        </form>
      </div>
    <?php endif; ?>

    <!-- Account Security -->
    <div class="s-card" style="border-left:4px solid var(--primary);">
      <div class="s-card-title"><i class="fas fa-shield-alt" style="color:var(--primary);"></i>Account Security</div>
      <div class="info-row">
        <span class="lbl">Password set</span>
        <?php if ($student['password']): ?>
          <span style="color:#16a34a;font-size:.78rem;font-weight:600;"><i class="fas fa-check-circle me-1"></i>Yes</span>
        <?php else: ?>
          <span style="color:#9ca3af;font-size:.78rem;"><i class="fas fa-times-circle me-1"></i>Not set (login via
            OTP)</span>
        <?php endif; ?>
      </div>
      <div class="info-row">
        <span class="lbl">ABHA linked</span>
        <?php if ($student['abha_linked']): ?>
          <span style="color:#00875a;font-size:.78rem;font-weight:600;"><i class="fas fa-id-card me-1"></i>Linked</span>
        <?php else: ?>
          <a href="abha.php" style="font-size:.78rem;font-weight:600;color:var(--primary);text-decoration:none;">
            <i class="fas fa-plus-circle me-1"></i>Link Now
          </a>
        <?php endif; ?>
      </div>
      <div class="info-row">
        <span class="lbl">Account status</span>
        <span style="color:#16a34a;font-size:.78rem;font-weight:600;"><i class="fas fa-circle me-1"
            style="font-size:.5rem;vertical-align:middle;"></i>Active</span>
      </div>
    </div>

    <div style="text-align:center;font-size:.7rem;color:#9ca3af;padding:4px 0;">
      <i class="fas fa-lock me-1"></i>Your data is secure &amp; private.
    </div>
  </div>

  <!-- Bottom Navigation -->
  <nav class="s-bottomnav">
    <a href="dashboard.php"><i class="fas fa-home"></i>Home</a>
    <a href="health.php"><i class="fas fa-heartbeat"></i>Health</a>
    <a href="records.php"><i class="fas fa-file-medical"></i>Records</a>
    <a href="abha.php"><i class="fas fa-id-card"></i>ABHA</a>
    <a href="profile.php" class="active"><i class="fas fa-user-circle"></i>Profile</a>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script>
    function togglePw(id, btn) {
      const inp = document.getElementById(id);
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    }

    const healthCardUrl = <?= json_encode($health_card_url) ?>;
    const healthCardTitle = <?= json_encode($student_name . ' — Digital Health ID Card') ?>;

    new QRCode(document.getElementById('healthQrCode'), {
      text: healthCardUrl,
      width: 92,
      height: 92,
      colorDark: '#0C74C5',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });

    document.getElementById('copyIdCardBtn').addEventListener('click', function () {
      navigator.clipboard.writeText(healthCardUrl).then(() => {
        const btn = this;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = original; }, 1800);
      });
    });

    document.getElementById('shareIdCardBtn').addEventListener('click', async function () {
      if (navigator.share) {
        try {
          await navigator.share({ title: healthCardTitle, url: healthCardUrl });
        } catch (e) { /* user cancelled */ }
      } else {
        document.getElementById('copyIdCardBtn').click();
      }
    });

    document.getElementById('downloadIdCardBtn').addEventListener('click', function () {
      html2canvas(document.getElementById('healthIdCard'), { backgroundColor: null, scale: 2 }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'health-id-card.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      });
    });
  </script>
</body>

</html>