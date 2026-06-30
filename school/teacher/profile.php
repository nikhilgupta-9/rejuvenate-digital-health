<?php
include_once "../../config/connect.php";
include_once "auth.php";

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password']     ?? '';
        $cnf = $_POST['confirm_password'] ?? '';

        if (!$teacher['password'] || !password_verify($cur, $teacher['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new !== $cnf) {
            $error = "New passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE school_members SET password=? WHERE id=?");
            $upd->bind_param('si', $hash, $teacher_id);
            $upd->execute();
            $success = "Password updated successfully.";
            $stmt->execute();
            $teacher = $stmt->get_result()->fetch_assoc();
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
            $upd->bind_param('ssi', $phone, $email, $teacher_id);
            $upd->execute();
            $success = "Contact details updated.";
            $stmt->execute();
            $teacher = $stmt->get_result()->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile | <?= htmlspecialchars($teacher_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    .sec-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
    .sec-card-head { padding:14px 20px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; gap:10px; }
    .sec-card-head .icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
    .sec-card-head h6 { margin:0; font-size:.9rem; font-weight:700; color:#1f2937; }
    .sec-card-head p  { margin:0; font-size:.73rem; color:#9ca3af; }
    .sec-card-body { padding:20px; }
    .info-row { display:flex; align-items:center; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f3f4f6; }
    .info-row:last-child { border-bottom:none; }
    .info-label { font-size:.77rem; color:#6b7280; }
    .info-val   { font-size:.83rem; font-weight:600; color:#111827; }
    .profile-hero { background:var(--primary); border-radius:14px; color:#fff; padding:22px 24px; margin-bottom:22px; display:flex; align-items:center; gap:18px; position:relative; overflow:hidden; }
    .profile-hero::after { content:''; position:absolute; right:-40px; top:-40px; width:160px; height:160px; background:rgba(255,255,255,.06); border-radius:50%; }
    .p-avatar { width:64px; height:64px; border-radius:16px; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; flex-shrink:0; position:relative; z-index:1; }
    .pass-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:#9ca3af; cursor:pointer; padding:0; }
    .pass-wrap { position:relative; }
  </style>
</head>
<body>
<?php $active_page = 'profile'; $base_path = ''; include '../inc/sidebar-teacher.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</div>
      <div style="font-size:.75rem;color:#6b7280;">Account details &amp; security</div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="abha.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
      <i class="fas fa-id-card"></i><span>My ABHA</span>
    </a>
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <!-- Profile hero -->
  <div class="profile-hero">
    <div class="p-avatar"><?= strtoupper(substr($teacher_name,0,1)) ?></div>
    <div style="position:relative;z-index:1;flex:1;min-width:0;">
      <div style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($teacher_name) ?></div>
      <div style="font-size:.82rem;opacity:.8;margin-top:3px;">
        <?= htmlspecialchars($teacher['designation'] ?? 'Teacher') ?>
        <?= $teacher['assigned_class'] ? ' &middot; ' . htmlspecialchars($teacher['assigned_class']) : '' ?>
      </div>
      <div style="font-size:.72rem;opacity:.6;font-family:monospace;margin-top:4px;"><?= htmlspecialchars($teacher_uid) ?></div>
    </div>
    <div style="position:relative;z-index:1;flex-shrink:0;text-align:right;">
      <span style="background:rgba(255,255,255,.2);border-radius:20px;padding:4px 12px;font-size:.72rem;font-weight:700;">
        <i class="fas fa-circle me-1" style="color:#4ade80;font-size:.55rem;"></i>Active
      </span>
      <?php if ($teacher['abha_linked']): ?>
        <div style="font-size:.7rem;opacity:.75;margin-top:6px;"><i class="fas fa-id-card me-1"></i>ABHA Linked</div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" style="border-radius:12px;">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" style="border-radius:12px;">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-6">

      <!-- Personal Details -->
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#eaf4fd;color:#0C74C5;"><i class="fas fa-user"></i></div>
          <div><h6>Personal Details</h6><p>Your account information</p></div>
        </div>
        <div class="sec-card-body">
          <div class="info-row">
            <span class="info-label"><i class="fas fa-id-badge me-1"></i>Full Name</span>
            <span class="info-val"><?= htmlspecialchars($teacher['name'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="info-label"><i class="fas fa-briefcase me-1"></i>Designation</span>
            <span class="info-val"><?= htmlspecialchars($teacher['designation'] ?? 'Teacher') ?></span>
          </div>
          <div class="info-row">
            <span class="info-label"><i class="fas fa-id-card me-1"></i>Employee ID</span>
            <span class="info-val" style="font-family:monospace;"><?= htmlspecialchars($teacher['employee_id'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="info-label"><i class="fas fa-chalkboard-teacher me-1"></i>Assigned Class</span>
            <span class="info-val"><?= htmlspecialchars($teacher['assigned_class'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="info-label"><i class="fas fa-school me-1"></i>School</span>
            <span class="info-val"><?= htmlspecialchars($teacher_school) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label"><i class="fas fa-clock me-1"></i>Last Login</span>
            <span class="info-val"><?= $teacher['last_login'] ? date('d M Y, h:i A', strtotime($teacher['last_login'])) : 'First login' ?></span>
          </div>
          <div class="info-row">
            <span class="info-label"><i class="fas fa-fingerprint me-1"></i>Member UID</span>
            <span class="info-val" style="font-family:monospace;font-size:.76rem;"><?= htmlspecialchars($teacher_uid) ?></span>
          </div>
        </div>
      </div>

      <!-- Contact Details (editable) -->
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-address-book"></i></div>
          <div><h6>Contact Details</h6><p>Update your email or mobile number</p></div>
        </div>
        <div class="sec-card-body">
          <form method="POST">
            <input type="hidden" name="action" value="update_contact">
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.82rem;">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" name="email"
                  value="<?= htmlspecialchars($teacher['email'] ?? '') ?>" placeholder="you@example.com">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.82rem;">Mobile Number</label>
              <div class="input-group">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control" name="phone" inputmode="numeric"
                  value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" placeholder="10-digit mobile" maxlength="10">
              </div>
            </div>
            <button type="submit" class="btn btn-success btn-sm fw-semibold px-4">
              <i class="fas fa-save me-1"></i>Update Contact
            </button>
          </form>
        </div>
      </div>

    </div>
    <div class="col-lg-6">

      <!-- Change Password -->
      <?php if ($teacher['password']): ?>
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#fff5f5;color:#dc2626;"><i class="fas fa-key"></i></div>
          <div><h6>Change Password</h6><p>Use a strong password of at least 6 characters</p></div>
        </div>
        <div class="sec-card-body">
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.82rem;">Current Password</label>
              <div class="pass-wrap">
                <input type="password" class="form-control" name="current_password" id="cur_pw" required>
                <button type="button" class="pass-toggle" onclick="togglePw('cur_pw',this)"><i class="fas fa-eye"></i></button>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.82rem;">New Password</label>
              <div class="pass-wrap">
                <input type="password" class="form-control" name="new_password" id="new_pw" required minlength="6">
                <button type="button" class="pass-toggle" onclick="togglePw('new_pw',this)"><i class="fas fa-eye"></i></button>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.82rem;">Confirm New Password</label>
              <div class="pass-wrap">
                <input type="password" class="form-control" name="confirm_password" id="cnf_pw" required minlength="6">
                <button type="button" class="pass-toggle" onclick="togglePw('cnf_pw',this)"><i class="fas fa-eye"></i></button>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm fw-semibold px-4">
              <i class="fas fa-key me-1"></i>Update Password
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Account Security Summary -->
      <div class="sec-card" style="border-left:4px solid #0C74C5;">
        <div class="sec-card-body">
          <h6 class="fw-bold mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Account Security</h6>
          <div class="info-row">
            <span class="info-label">Password set</span>
            <span><?php if ($teacher['password']): ?>
              <span style="color:#16a34a;font-size:.78rem;font-weight:600;"><i class="fas fa-check-circle me-1"></i>Yes</span>
            <?php else: ?>
              <span style="color:#9ca3af;font-size:.78rem;"><i class="fas fa-times-circle me-1"></i>No</span>
            <?php endif; ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">ABHA linked</span>
            <span><?php if ($teacher['abha_linked']): ?>
              <span style="color:#00875a;font-size:.78rem;font-weight:600;"><i class="fas fa-id-card me-1"></i>Linked</span>
            <?php else: ?>
              <span style="color:#9ca3af;font-size:.78rem;"><i class="fas fa-times-circle me-1"></i>Not linked</span>
            <?php endif; ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Account status</span>
            <span style="color:#16a34a;font-size:.78rem;font-weight:600;"><i class="fas fa-circle me-1" style="font-size:.55rem;"></i>Active</span>
          </div>
          <?php if (!$teacher['abha_linked']): ?>
          <div class="mt-3">
            <a href="abha.php" class="btn btn-sm btn-outline-success fw-semibold">
              <i class="fas fa-id-card me-1"></i>Link ABHA Now
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
</script>
</body>
</html>
