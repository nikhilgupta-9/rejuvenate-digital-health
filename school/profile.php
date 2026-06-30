<?php
include_once "../config/connect.php";
include_once "auth/auth.php";

// ── Fetch school + admin user details ────────────────────────────────────────
$stmt = $conn->prepare("SELECT s.*, su.id as su_id, su.name as admin_name, su.email as admin_email,
    su.phone as admin_phone, su.last_login, su.created_at as admin_since
    FROM schools s
    JOIN school_users su ON su.school_id = s.id AND su.role = 'school_admin'
    WHERE s.id = ? LIMIT 1");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();

// ── Member stats ─────────────────────────────────────────────────────────────
$stats = ['Student'=>0,'Teacher'=>0,'Staff'=>0,'Total'=>0];
$sr = $conn->prepare("SELECT type, COUNT(*) as c FROM school_members WHERE school_id=? AND status='Active' GROUP BY type");
$sr->bind_param('i', $school_id); $sr->execute();
$res = $sr->get_result();
while ($row = $res->fetch_assoc()) {
    $stats[$row['type']] = $row['c'];
    $stats['Total'] += $row['c'];
}

// ── Health profile stats ──────────────────────────────────────────────────────
$hp_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM member_health_profiles WHERE school_id=$school_id"))['c'] ?? 0;

// ── Pending self-registrations ────────────────────────────────────────────────
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE school_id=$school_id AND status='Pending'"))['c'] ?? 0;

$success = $error = '';
$active_tab = $_GET['tab'] ?? 'info';

// ── POST: Update school info ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_school') {
    $active_tab = 'info';
    $school_name_upd = trim($_POST['school_name'] ?? '');
    $principal_name  = trim($_POST['principal_name'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $state           = trim($_POST['state'] ?? '');
    $pincode         = trim($_POST['pincode'] ?? '');
    $website         = trim($_POST['website'] ?? '');

    if (!$school_name_upd) {
        $error = "School name is required.";
    } else {
        // Handle logo upload
        $logo_path = $school['logo'] ?? null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $error = "Logo must be JPG, PNG or WebP.";
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = "Logo must be under 2 MB.";
            } else {
                $upload_dir = '../uploads/schools/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'school_' . $school_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) {
                    // Remove old logo
                    if ($logo_path && file_exists('../' . $logo_path)) @unlink('../' . $logo_path);
                    $logo_path = 'uploads/schools/logos/' . $filename;
                } else {
                    $error = "Logo upload failed.";
                }
            }
        }

        if (!$error) {
            $upd = $conn->prepare("UPDATE schools SET school_name=?,principal_name=?,phone=?,address=?,city=?,state=?,pincode=?,website=?,logo=? WHERE id=?");
            $upd->bind_param('sssssssssi', $school_name_upd,$principal_name,$phone,$address,$city,$state,$pincode,$website,$logo_path,$school_id);
            if ($upd->execute()) {
                $success = "School profile updated successfully.";
                $_SESSION['school_name'] = $school_name_upd;
                $school_name = $school_name_upd;
                $stmt->execute(); $school = $stmt->get_result()->fetch_assoc();
            } else { $error = "Update failed: " . $conn->error; }
        }
    }
}

// ── POST: Update admin account ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_admin') {
    $active_tab = 'account';
    $admin_name_upd  = trim($_POST['admin_name'] ?? '');
    $admin_phone_upd = trim($_POST['admin_phone'] ?? '');

    if (!$admin_name_upd) {
        $error = "Admin name is required.";
    } else {
        $upd3 = $conn->prepare("UPDATE school_users SET name=?, phone=? WHERE id=? AND school_id=?");
        $upd3->bind_param('ssii', $admin_name_upd, $admin_phone_upd, $school['su_id'], $school_id);
        if ($upd3->execute()) {
            $success = "Admin account updated.";
            $_SESSION['school_user_name'] = $admin_name_upd;
            $school_user_name = $admin_name_upd;
            $stmt->execute(); $school = $stmt->get_result()->fetch_assoc();
        } else { $error = "Update failed."; }
    }
}

// ── POST: Change password ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $active_tab = 'security';
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $cnf = $_POST['confirm_password'] ?? '';

    $cu = $conn->prepare("SELECT password FROM school_users WHERE id=?");
    $cu->bind_param('i', $school_user_id); $cu->execute();
    $cu_row = $cu->get_result()->fetch_assoc();

    if (!password_verify($cur, $cu_row['password']))   { $active_tab='security'; $error = "Current password is incorrect."; }
    elseif (strlen($new) < 8)                          { $active_tab='security'; $error = "New password must be at least 8 characters."; }
    elseif (!preg_match('/[A-Z]/', $new))              { $active_tab='security'; $error = "Password must contain at least one uppercase letter."; }
    elseif (!preg_match('/[0-9]/', $new))              { $active_tab='security'; $error = "Password must contain at least one number."; }
    elseif ($new !== $cnf)                             { $active_tab='security'; $error = "Passwords do not match."; }
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd2 = $conn->prepare("UPDATE school_users SET password=? WHERE id=?");
        $upd2->bind_param('si', $hash, $school_user_id);
        $upd2->execute();
        $success = "Password changed successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Profile & Settings</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="assets/school.css">
  <style>
    /* ── Profile Header Card ── */
    .profile-header {
      background: var(--primary);
      border-radius: 14px;
      padding: 24px 28px;
      color: #fff;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }
    .profile-header::after {
      content: '';
      position: absolute; right: -30px; top: -30px;
      width: 160px; height: 160px; border-radius: 50%;
      background: rgba(255,255,255,.06);
    }
    .profile-logo {
      width: 72px; height: 72px; border-radius: 16px;
      background: rgba(255,255,255,.18);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; font-weight: 800; color: #fff; flex-shrink: 0;
      overflow: hidden;
    }
    .profile-logo img { width: 100%; height: 100%; object-fit: cover; }
    .stat-pill {
      background: rgba(255,255,255,.15);
      border-radius: 10px; padding: 10px 16px; text-align: center; min-width: 90px;
    }
    .stat-pill .sv { font-size: 1.3rem; font-weight: 700; line-height: 1; }
    .stat-pill .sl { font-size: .65rem; opacity: .82; margin-top: 3px; text-transform: uppercase; letter-spacing: .5px; }

    /* ── Tabs ── */
    .profile-tabs {
      display: flex; gap: 4px; margin-bottom: 20px;
      background: #f3f4f6; border-radius: 10px; padding: 4px;
    }
    .profile-tabs a {
      flex: 1; text-align: center; padding: 9px 10px;
      border-radius: 8px; font-size: .82rem; font-weight: 600;
      color: #6b7280; text-decoration: none; transition: .2s;
      display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .profile-tabs a.active { background: #fff; color: var(--primary); box-shadow: 0 1px 4px rgba(0,0,0,.1); }
    .profile-tabs a:hover:not(.active) { color: var(--primary); }

    /* ── Form Section ── */
    .settings-card {
      background: #fff; border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0,0,0,.06); overflow: hidden; margin-bottom: 20px;
    }
    .settings-card-header {
      padding: 14px 20px; border-bottom: 1px solid #f3f4f6;
      display: flex; align-items: center; gap: 10px;
    }
    .settings-card-header .icon {
      width: 34px; height: 34px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: .9rem; flex-shrink: 0;
    }
    .settings-card-header h6 { margin: 0; font-weight: 700; font-size: .9rem; color: #1f2937; }
    .settings-card-header p  { margin: 0; font-size: .75rem; color: #9ca3af; }
    .settings-card-body { padding: 20px; }

    /* ── Info Row (read-only display) ── */
    .info-row {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 0; border-bottom: 1px solid #f9fafb;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row .lbl { font-size: .72rem; color: #9ca3af; width: 130px; flex-shrink: 0; }
    .info-row .val { font-size: .85rem; font-weight: 500; color: #1f2937; }

    /* ── Logo preview ── */
    .logo-upload-wrap { position: relative; display: inline-block; cursor: pointer; }
    .logo-preview {
      width: 96px; height: 96px; border-radius: 14px;
      border: 2px dashed #d1d5db; display: flex; flex-direction: column;
      align-items: center; justify-content: center; background: #f9fafb;
      overflow: hidden; transition: .2s;
    }
    .logo-preview img { width: 100%; height: 100%; object-fit: cover; }
    .logo-preview:hover { border-color: var(--primary); background: #eaf4fd; }
    .logo-preview .upload-hint { font-size: .68rem; color: #9ca3af; margin-top: 4px; text-align: center; }

    /* ── Password strength bar ── */
    .pw-strength { height: 4px; border-radius: 2px; transition: .3s; margin-top: 6px; }

    /* ── Security info badges ── */
    .sec-badge {
      background: #f3f4f6; border-radius: 8px; padding: 10px 14px;
      display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
    }
    .sec-badge .icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; }
    .sec-badge .text .t { font-size: .78rem; font-weight: 600; color: #1f2937; }
    .sec-badge .text .s { font-size: .72rem; color: #9ca3af; }

    @media (max-width: 575px) {
      .profile-header { padding: 18px 16px; }
      .profile-tabs a span { display: none; }
      .stat-pill { min-width: 70px; padding: 8px 10px; }
    }
  </style>
</head>
<body>
<?php $active_page='profile'; $base_path=''; include 'inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div style="font-size:1rem;font-weight:600;color:#1f2937;">
      <i class="fas fa-cog me-2 text-primary"></i>Profile &amp; Settings
    </div>
  </div>
  <a href="auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt me-1"></i><span class="d-none d-sm-inline">Logout</span></a>
</div>

<main class="school-content">

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- ── Profile Header ── -->
  <div class="profile-header">
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
      <div class="profile-logo">
        <?php if ($school['logo']): ?>
          <img src="../<?= htmlspecialchars($school['logo']) ?>" alt="School Logo">
        <?php else: ?>
          <?= strtoupper(substr($school['school_name'],0,1)) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0;">
        <h5 class="mb-0 fw-bold" style="font-size:1.1rem;"><?= htmlspecialchars($school['school_name']) ?></h5>
        <div style="font-size:.8rem;opacity:.8;margin-top:2px;">
          <?= htmlspecialchars($school['school_uid'] ?? '') ?>
          <?php if ($school['city']): ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($school['city']) ?><?php endif; ?>
          <?php if ($school['board']): ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($school['board']) ?><?php endif; ?>
        </div>
        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
          <span class="badge <?= $school['status']==='Active'?'bg-success':($school['status']==='Pending'?'bg-warning text-dark':'bg-secondary') ?>">
            <i class="fas fa-circle me-1" style="font-size:.5rem;"></i><?= $school['status'] ?>
          </span>
          <span class="badge" style="background:rgba(255,255,255,.2);font-size:.72rem;">
            <?= htmlspecialchars($school['school_type'] ?? 'School') ?>
          </span>
          <?php if ($pending_count > 0): ?>
          <a href="members/list.php" class="badge text-decoration-none" style="background:#ea580c;">
            <i class="fas fa-user-clock me-1"></i><?= $pending_count ?> Pending Approvals
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- Stats row -->
    <div class="d-flex gap-2 flex-wrap">
      <div class="stat-pill">
        <div class="sv"><?= $stats['Student'] ?></div>
        <div class="sl"><i class="fas fa-user-graduate me-1"></i>Students</div>
      </div>
      <div class="stat-pill">
        <div class="sv"><?= $stats['Teacher'] ?></div>
        <div class="sl"><i class="fas fa-chalkboard-teacher me-1"></i>Teachers</div>
      </div>
      <div class="stat-pill">
        <div class="sv"><?= $stats['Staff'] ?></div>
        <div class="sl"><i class="fas fa-user-tie me-1"></i>Staff</div>
      </div>
      <div class="stat-pill">
        <div class="sv"><?= $hp_count ?></div>
        <div class="sl"><i class="fas fa-heartbeat me-1"></i>Health Profiles</div>
      </div>
      <div class="stat-pill" style="background:rgba(2,201,184,.3);">
        <div class="sv"><?= $stats['Total'] ?></div>
        <div class="sl"><i class="fas fa-users me-1"></i>Total</div>
      </div>
    </div>
  </div>

  <!-- ── Tabs ── -->
  <div class="profile-tabs">
    <a href="?tab=info" class="<?= $active_tab==='info'?'active':'' ?>">
      <i class="fas fa-school"></i><span>School Info</span>
    </a>
    <a href="?tab=account" class="<?= $active_tab==='account'?'active':'' ?>">
      <i class="fas fa-user-circle"></i><span>Admin Account</span>
    </a>
    <a href="?tab=security" class="<?= $active_tab==='security'?'active':'' ?>">
      <i class="fas fa-shield-alt"></i><span>Security</span>
    </a>
  </div>

  <!-- ══════════════════════════════════════════════════
       TAB 1 — School Info
       ══════════════════════════════════════════════════ -->
  <?php if ($active_tab === 'info'): ?>
  <div class="row g-4">

    <!-- Left: Editable form -->
    <div class="col-lg-8">

      <!-- Logo upload -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-image"></i></div>
          <div><h6>School Logo</h6><p>JPG, PNG or WebP · Max 2 MB</p></div>
        </div>
        <div class="settings-card-body">
          <form method="POST" enctype="multipart/form-data" id="logoForm">
            <input type="hidden" name="action" value="update_school">
            <!-- Hidden fields to carry other values -->
            <input type="hidden" name="school_name"    value="<?= htmlspecialchars($school['school_name'] ?? '') ?>">
            <input type="hidden" name="principal_name" value="<?= htmlspecialchars($school['principal_name'] ?? '') ?>">
            <input type="hidden" name="phone"          value="<?= htmlspecialchars($school['phone'] ?? '') ?>">
            <input type="hidden" name="address"        value="<?= htmlspecialchars($school['address'] ?? '') ?>">
            <input type="hidden" name="city"           value="<?= htmlspecialchars($school['city'] ?? '') ?>">
            <input type="hidden" name="state"          value="<?= htmlspecialchars($school['state'] ?? '') ?>">
            <input type="hidden" name="pincode"        value="<?= htmlspecialchars($school['pincode'] ?? '') ?>">
            <input type="hidden" name="website"        value="<?= htmlspecialchars($school['website'] ?? '') ?>">

            <div class="d-flex align-items-center gap-4 flex-wrap">
              <label class="logo-upload-wrap" for="logoInput">
                <div class="logo-preview" id="logoPreview">
                  <?php if ($school['logo']): ?>
                    <img src="../<?= htmlspecialchars($school['logo']) ?>" id="logoImg">
                  <?php else: ?>
                    <i class="fas fa-cloud-upload-alt text-muted fa-lg"></i>
                    <div class="upload-hint">Click to upload</div>
                  <?php endif; ?>
                </div>
              </label>
              <div>
                <input type="file" id="logoInput" name="logo" accept=".jpg,.jpeg,.png,.webp" class="d-none" onchange="previewLogo(this)">
                <label for="logoInput" class="btn btn-outline-primary btn-sm mb-2">
                  <i class="fas fa-upload me-1"></i>Choose Logo
                </label>
                <div style="font-size:.75rem;color:#9ca3af;">Recommended: 200×200px square image</div>
                <?php if ($school['logo']): ?>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:4px;">Current logo uploaded</div>
                <?php endif; ?>
              </div>
              <button type="submit" id="logoSaveBtn" class="btn btn-primary btn-sm d-none">
                <i class="fas fa-save me-1"></i>Save Logo
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- School details form -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-school"></i></div>
          <div><h6>School Information</h6><p>Update your school's contact and location details</p></div>
        </div>
        <div class="settings-card-body">
          <form method="POST" id="schoolInfoForm">
            <input type="hidden" name="action" value="update_school">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold" style="font-size:.83rem;">School Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="school_name"
                       value="<?= htmlspecialchars($school['school_name'] ?? '') ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Phone</label>
                <input type="tel" class="form-control" name="phone"
                       value="<?= htmlspecialchars($school['phone'] ?? '') ?>" placeholder="School contact number">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Principal Name</label>
                <input type="text" class="form-control" name="principal_name"
                       value="<?= htmlspecialchars($school['principal_name'] ?? '') ?>" placeholder="Principal's full name">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Website</label>
                <input type="url" class="form-control" name="website"
                       value="<?= htmlspecialchars($school['website'] ?? '') ?>" placeholder="https://yourschool.edu.in">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Address</label>
                <input type="text" class="form-control" name="address"
                       value="<?= htmlspecialchars($school['address'] ?? '') ?>" placeholder="Street / locality">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.83rem;">City</label>
                <input type="text" class="form-control" name="city"
                       value="<?= htmlspecialchars($school['city'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.83rem;">State</label>
                <input type="text" class="form-control" name="state"
                       value="<?= htmlspecialchars($school['state'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Pincode</label>
                <input type="text" class="form-control" name="pincode"
                       value="<?= htmlspecialchars($school['pincode'] ?? '') ?>" placeholder="6-digit pincode">
              </div>
            </div>
            <div class="mt-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Save Changes
              </button>
              <button type="reset" class="btn btn-outline-secondary">
                <i class="fas fa-undo me-1"></i>Reset
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>

    <!-- Right: Read-only school details -->
    <div class="col-lg-4">
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-info-circle"></i></div>
          <div><h6>School Details</h6><p>Registered information (read-only)</p></div>
        </div>
        <div class="settings-card-body">
          <div class="info-row">
            <div class="lbl"><i class="fas fa-hashtag me-1"></i>School UID</div>
            <div class="val">
              <span style="font-family:monospace;background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:.82rem;">
                <?= htmlspecialchars($school['school_uid'] ?? '—') ?>
              </span>
            </div>
          </div>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-chalkboard me-1"></i>Board</div>
            <div class="val"><span class="badge bg-primary"><?= htmlspecialchars($school['board'] ?? '—') ?></span></div>
          </div>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-building me-1"></i>School Type</div>
            <div class="val"><?= htmlspecialchars($school['school_type'] ?? '—') ?></div>
          </div>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-certificate me-1"></i>Reg. Number</div>
            <div class="val" style="font-size:.82rem;"><?= htmlspecialchars($school['registration_number'] ?? '—') ?></div>
          </div>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-check-circle me-1"></i>Status</div>
            <div class="val">
              <span class="badge <?= $school['status']==='Active'?'bg-success':'bg-secondary' ?>">
                <?= $school['status'] ?>
              </span>
            </div>
          </div>
          <?php if ($school['approved_at']): ?>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-calendar-check me-1"></i>Approved On</div>
            <div class="val" style="font-size:.8rem;"><?= date('d M Y', strtotime($school['approved_at'])) ?></div>
          </div>
          <?php endif; ?>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-calendar me-1"></i>Member Since</div>
            <div class="val" style="font-size:.8rem;"><?= date('d M Y', strtotime($school['created_at'])) ?></div>
          </div>
        </div>
      </div>

      <!-- Quick links -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-bolt"></i></div>
          <div><h6>Quick Actions</h6><p>Common tasks</p></div>
        </div>
        <div class="settings-card-body p-3">
          <div class="d-grid gap-2">
            <a href="members/add.php" class="btn btn-sm btn-outline-primary text-start">
              <i class="fas fa-user-plus me-2"></i>Add New Member
            </a>
            <a href="members/list.php" class="btn btn-sm btn-outline-secondary text-start">
              <i class="fas fa-users me-2"></i>View All Members
            </a>
            <a href="health/records.php" class="btn btn-sm btn-outline-danger text-start">
              <i class="fas fa-heartbeat me-2"></i>Health Records
            </a>
            <a href="health/abha.php" class="btn btn-sm btn-outline-success text-start">
              <i class="fas fa-id-card me-2"></i>ABHA Management
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════
       TAB 2 — Admin Account
       ══════════════════════════════════════════════════ -->
  <?php elseif ($active_tab === 'account'): ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-user-edit"></i></div>
          <div><h6>Admin Details</h6><p>Update your display name and contact info</p></div>
        </div>
        <div class="settings-card-body">
          <form method="POST">
            <input type="hidden" name="action" value="update_admin">
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Admin / Contact Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="admin_name"
                       value="<?= htmlspecialchars($school['admin_name'] ?? $school_user_name) ?>" required>
              </div>
              <div class="col-md-12">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Email Address</label>
                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($school['admin_email'] ?? '') ?>" disabled>
                <div class="form-text">Email cannot be changed. Contact super admin if needed.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Phone</label>
                <input type="tel" class="form-control" name="admin_phone"
                       value="<?= htmlspecialchars($school['admin_phone'] ?? '') ?>" placeholder="Admin phone number">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.83rem;">Role</label>
                <input type="text" class="form-control bg-light"
                       value="<?= ucwords(str_replace('_', ' ', $school_user_role)) ?>" disabled>
              </div>
            </div>
            <div class="mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Update Account
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <!-- Account info card -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-id-badge"></i></div>
          <div><h6>Account Summary</h6></div>
        </div>
        <div class="settings-card-body">
          <div class="text-center mb-3">
            <div style="width:60px;height:60px;border-radius:14px;background:var(--primary);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;">
              <?= strtoupper(substr($school_user_name,0,1)) ?>
            </div>
            <div class="fw-bold"><?= htmlspecialchars($school['admin_name'] ?? $school_user_name) ?></div>
            <div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($school['admin_email'] ?? '') ?></div>
            <span class="badge bg-success mt-1" style="font-size:.7rem;">Active</span>
          </div>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-user-shield me-1"></i>Role</div>
            <div class="val"><?= ucwords(str_replace('_', ' ', $school_user_role)) ?></div>
          </div>
          <?php if ($school['last_login']): ?>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-sign-in-alt me-1"></i>Last Login</div>
            <div class="val" style="font-size:.8rem;"><?= date('d M Y, h:i A', strtotime($school['last_login'])) ?></div>
          </div>
          <?php endif; ?>
          <div class="info-row">
            <div class="lbl"><i class="fas fa-calendar me-1"></i>Account Since</div>
            <div class="val" style="font-size:.8rem;"><?= $school['admin_since'] ? date('d M Y', strtotime($school['admin_since'])) : '—' ?></div>
          </div>
          <div class="mt-3">
            <a href="?tab=security" class="btn btn-sm btn-outline-warning w-100">
              <i class="fas fa-key me-1"></i>Change Password
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════
       TAB 3 — Security
       ══════════════════════════════════════════════════ -->
  <?php elseif ($active_tab === 'security'): ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-key"></i></div>
          <div><h6>Change Password</h6><p>Use a strong password to keep your account secure</p></div>
        </div>
        <div class="settings-card-body">
          <form method="POST" id="pwForm">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.83rem;">Current Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" id="cur_pw" name="current_password" required placeholder="Your current password">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('cur_pw','cur_icon')">
                  <i id="cur_icon" class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.83rem;">New Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" id="new_pw" name="new_password" required placeholder="Min 8 chars, 1 uppercase, 1 number"
                       oninput="checkStrength(this.value)">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('new_pw','new_icon')">
                  <i id="new_icon" class="fas fa-eye"></i>
                </button>
              </div>
              <div class="pw-strength mt-2" id="pwStrengthBar" style="width:0;background:#e5e7eb;height:4px;border-radius:2px;"></div>
              <div id="pwStrengthLabel" style="font-size:.72rem;color:#9ca3af;margin-top:3px;"></div>
              <div id="pwRequirements" style="font-size:.72rem;margin-top:6px;display:none;">
                <div id="req-len"  class="text-danger"><i class="fas fa-times me-1"></i>At least 8 characters</div>
                <div id="req-up"   class="text-danger"><i class="fas fa-times me-1"></i>At least one uppercase letter</div>
                <div id="req-num"  class="text-danger"><i class="fas fa-times me-1"></i>At least one number</div>
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold" style="font-size:.83rem;">Confirm New Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" id="cnf_pw" name="confirm_password" required placeholder="Re-enter new password"
                       oninput="checkMatch()">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('cnf_pw','cnf_icon')">
                  <i id="cnf_icon" class="fas fa-eye"></i>
                </button>
              </div>
              <div id="matchMsg" style="font-size:.72rem;margin-top:4px;"></div>
            </div>
            <button type="submit" class="btn btn-warning fw-semibold">
              <i class="fas fa-lock me-2"></i>Update Password
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <!-- Security info -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="icon" style="background:#fef2f2;color:#dc2626;"><i class="fas fa-shield-alt"></i></div>
          <div><h6>Security Status</h6></div>
        </div>
        <div class="settings-card-body">
          <div class="sec-badge">
            <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            <div class="text">
              <div class="t">Account Active</div>
              <div class="s">Your school account is active and verified</div>
            </div>
          </div>
          <?php if ($school['last_login']): ?>
          <div class="sec-badge">
            <div class="icon" style="background:#eaf4fd;color:#0C74C5;"><i class="fas fa-sign-in-alt"></i></div>
            <div class="text">
              <div class="t">Last Login</div>
              <div class="s"><?= date('d M Y · h:i A', strtotime($school['last_login'])) ?></div>
            </div>
          </div>
          <?php endif; ?>
          <div class="sec-badge">
            <div class="icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-user-lock"></i></div>
            <div class="text">
              <div class="t">Password Policy</div>
              <div class="s">Min 8 chars · 1 uppercase · 1 number</div>
            </div>
          </div>
          <div class="mt-3 p-3 rounded" style="background:#fef2f2;font-size:.78rem;color:#991b1b;">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Never share your password. Log out of shared devices after use.
          </div>
        </div>
      </div>

      <!-- Danger zone -->
      <div class="settings-card" style="border:1px solid #fecaca;">
        <div class="settings-card-header" style="background:#fef2f2;">
          <div class="icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
          <div><h6 style="color:#dc2626;">Session Management</h6></div>
        </div>
        <div class="settings-card-body">
          <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">End your current session across this device.</p>
          <a href="auth/logout.php" class="btn btn-sm btn-outline-danger w-100">
            <i class="fas fa-sign-out-alt me-1"></i>Logout Now
          </a>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Password show/hide ──────────────────────────────────────────────────
function togglePw(inputId, iconId) {
  const el = document.getElementById(inputId);
  const ic = document.getElementById(iconId);
  el.type = el.type === 'password' ? 'text' : 'password';
  ic.classList.toggle('fa-eye');
  ic.classList.toggle('fa-eye-slash');
}

// ── Password strength ───────────────────────────────────────────────────
function checkStrength(val) {
  const bar   = document.getElementById('pwStrengthBar');
  const lbl   = document.getElementById('pwStrengthLabel');
  const req   = document.getElementById('pwRequirements');
  req.style.display = val ? 'block' : 'none';

  const hasLen = val.length >= 8;
  const hasUp  = /[A-Z]/.test(val);
  const hasNum = /[0-9]/.test(val);

  document.getElementById('req-len').className = hasLen ? 'text-success' : 'text-danger';
  document.getElementById('req-len').innerHTML = (hasLen ? '<i class="fas fa-check me-1"></i>' : '<i class="fas fa-times me-1"></i>') + 'At least 8 characters';
  document.getElementById('req-up').className  = hasUp  ? 'text-success' : 'text-danger';
  document.getElementById('req-up').innerHTML  = (hasUp  ? '<i class="fas fa-check me-1"></i>' : '<i class="fas fa-times me-1"></i>') + 'At least one uppercase letter';
  document.getElementById('req-num').className = hasNum ? 'text-success' : 'text-danger';
  document.getElementById('req-num').innerHTML = (hasNum ? '<i class="fas fa-check me-1"></i>' : '<i class="fas fa-times me-1"></i>') + 'At least one number';

  const score = [hasLen, hasUp, hasNum, val.length >= 12, /[^a-zA-Z0-9]/.test(val)].filter(Boolean).length;
  const colors = ['#dc2626','#ea580c','#d97706','#16a34a','#0C74C5'];
  const labels = ['Too short','Weak','Fair','Strong','Very strong'];
  const widths = [20,40,60,80,100];
  bar.style.width   = widths[score-1]+'%';
  bar.style.background = colors[score-1] || '#e5e7eb';
  lbl.textContent   = val ? labels[score-1] || '' : '';
  lbl.style.color   = colors[score-1] || '#9ca3af';
}

// ── Password match ──────────────────────────────────────────────────────
function checkMatch() {
  const a = document.getElementById('new_pw').value;
  const b = document.getElementById('cnf_pw').value;
  const m = document.getElementById('matchMsg');
  if (!b) { m.textContent = ''; return; }
  if (a === b) {
    m.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>Passwords match</span>';
  } else {
    m.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
  }
}

// ── Logo preview ────────────────────────────────────────────────────────
function previewLogo(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    const prev = document.getElementById('logoPreview');
    prev.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    document.getElementById('logoSaveBtn').classList.remove('d-none');
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
</body>
</html>
