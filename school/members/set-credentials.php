<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

$stmt = $conn->prepare("SELECT id, name, type, email, roll_number, member_uid, password FROM school_members WHERE id=? AND school_id=?");
$stmt->bind_param('ii', $id, $school_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) { header("Location: list.php"); exit(); }

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $action   = $_POST['action']           ?? 'set';

    if ($action === 'revoke') {
        $upd = $conn->prepare("UPDATE school_members SET password=NULL, login_attempts=0, is_locked=0, locked_until=NULL WHERE id=? AND school_id=?");
        $upd->bind_param('ii', $id, $school_id);
        $upd->execute();
        $success = "Login access revoked for {$member['name']}.";
        $member['password'] = null;
    } else {
        if (strlen($new_pass) < 6)     { $error = "Password must be at least 6 characters."; }
        elseif ($new_pass !== $confirm) { $error = "Passwords do not match."; }
        else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd  = $conn->prepare("UPDATE school_members SET password=?, login_attempts=0, is_locked=0, locked_until=NULL WHERE id=? AND school_id=?");
            $upd->bind_param('sii', $hash, $id, $school_id);
            $upd->execute();
            $success = "Password updated successfully for {$member['name']}.";
            $member['password'] = $hash;
        }
    }
}

$type_color = ['Student' => '#0C74C5', 'Teacher' => '#16a34a', 'Staff' => '#7c3aed'];
$type_bg    = ['Student' => '#eaf4fd', 'Teacher' => '#f0fdf4', 'Staff' => '#f5f3ff'];
$type_icon  = ['Student' => 'fa-user-graduate', 'Teacher' => 'fa-chalkboard-teacher', 'Staff' => 'fa-user-tie'];
$tc = $type_color[$member['type']] ?? '#0C74C5';
$tb = $type_bg[$member['type']]    ?? '#eaf4fd';
$ti = $type_icon[$member['type']]  ?? 'fa-user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Set Credentials</title>
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
    .form-label { font-size:.84rem; font-weight:600; color:#374151; margin-bottom:5px; }
  </style>
</head>
<body>
<?php $active_page = 'members'; $base_path = '../'; include '../inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div style="font-size:1rem;font-weight:600;color:#1f2937;">
      <i class="fas fa-key me-2 text-primary"></i>Login Credentials
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-user me-1"></i><span class="d-none d-sm-inline">View Member</span></a>
    <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><span class="d-none d-sm-inline">Back</span></a>
    <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
          <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
          <div class="mt-2">
            <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-success me-2">
              <i class="fas fa-user me-1"></i>View Member
            </a>
            <a href="list.php" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-list me-1"></i>Back to List
            </a>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Member card -->
      <div class="sec-card">
        <div class="sec-card-body" style="padding:16px 20px;">
          <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:13px;background:<?= $tc ?>;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;color:#fff;flex-shrink:0;">
              <?= strtoupper(substr($member['name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:.95rem;color:#1f2937;"><?= htmlspecialchars($member['name']) ?></div>
              <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <span style="background:<?= $tb ?>;color:<?= $tc ?>;border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:700;">
                  <i class="fas <?= $ti ?> me-1"></i><?= $member['type'] ?>
                </span>
                <span style="font-size:.75rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($member['member_uid']) ?></span>
                <?php if ($member['email']): ?>
                  <span style="font-size:.75rem;color:#6b7280;"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($member['email']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="ms-auto flex-shrink-0">
              <?php if ($member['password']): ?>
                <span style="background:#f0fdf4;color:#16a34a;border-radius:20px;padding:4px 10px;font-size:.72rem;font-weight:700;">
                  <i class="fas fa-check me-1"></i>Login Active
                </span>
              <?php else: ?>
                <span style="background:#f9fafb;color:#9ca3af;border-radius:20px;padding:4px 10px;font-size:.72rem;font-weight:700;">
                  <i class="fas fa-times me-1"></i>No Login
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Set / Change password -->
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-lock"></i></div>
          <div>
            <h6><?= $member['password'] ? 'Change Password' : 'Set Password' ?></h6>
            <p><?= $member['password'] ? 'Update the login password for this member' : 'Create a password to enable login access' ?></p>
          </div>
        </div>
        <div class="sec-card-body">
          <form method="POST">
            <input type="hidden" name="action" value="set">
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <div class="input-group">
                <input type="password" class="form-control" id="pwInput" name="new_password"
                  placeholder="Min. 6 characters" required autocomplete="new-password" oninput="checkMatch()">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pwInput','eyeIcon1')">
                  <i id="eyeIcon1" class="fas fa-eye"></i>
                </button>
              </div>
              <div class="form-text">Minimum 6 characters</div>
            </div>
            <div class="mb-4">
              <label class="form-label">Confirm Password</label>
              <div class="input-group">
                <input type="password" class="form-control" id="cpInput" name="confirm_password"
                  placeholder="Re-enter password" required oninput="checkMatch()">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('cpInput','eyeIcon2')">
                  <i id="eyeIcon2" class="fas fa-eye"></i>
                </button>
              </div>
              <div id="matchMsg" style="font-size:.72rem;margin-top:4px;"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
              <i class="fas fa-key me-2"></i><?= $member['password'] ? 'Update Password' : 'Set Password & Enable Login' ?>
            </button>
          </form>
        </div>
      </div>

      <!-- Revoke access -->
      <?php if ($member['password']): ?>
      <div class="sec-card" style="border:1.5px solid #fecaca;">
        <div class="sec-card-head">
          <div class="icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-ban"></i></div>
          <div>
            <h6 style="color:#ef4444;">Revoke Login Access</h6>
            <p>Removes the password and disables login for this member</p>
          </div>
        </div>
        <div class="sec-card-body">
          <p class="text-muted mb-3" style="font-size:.84rem;">
            This will clear the password. The member will no longer be able to log in until a new password is set.
          </p>
          <form method="POST" onsubmit="return confirm('Revoke login access for <?= htmlspecialchars(addslashes($member['name'])) ?>?')">
            <input type="hidden" name="action" value="revoke">
            <button type="submit" class="btn btn-outline-danger btn-sm">
              <i class="fas fa-trash me-1"></i>Revoke Access
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function togglePw(id, iconId) {
    const el = document.getElementById(id);
    const ic = document.getElementById(iconId);
    el.type = el.type === 'password' ? 'text' : 'password';
    ic.classList.toggle('fa-eye');
    ic.classList.toggle('fa-eye-slash');
  }
  function checkMatch() {
    const p = document.getElementById('pwInput').value;
    const c = document.getElementById('cpInput').value;
    const m = document.getElementById('matchMsg');
    if (!p || !c) { m.innerHTML = ''; return; }
    if (p === c) {
      m.innerHTML = '<span style="color:#16a34a;"><i class="fas fa-check me-1"></i>Passwords match</span>';
    } else {
      m.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
    }
  }
</script>
</body>
</html>
