<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/util/function.php';
require_once 'db-conn.php';
// require_once dirname(__DIR__) . '/util/mail_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: auth/login.php'); exit();
}

// Only super_admin can create admin accounts
$my_id = (int)$_SESSION['admin_id'];
$stmt  = $conn->prepare("SELECT role FROM admin_user WHERE id = ?");
$stmt->bind_param('i', $my_id);
$stmt->execute();
$stmt->bind_result($my_role);
$stmt->fetch();
$stmt->close();

if ($my_role !== 'super_admin') {
    $_SESSION['admin_error'] = 'Only Super Admins can create new admin accounts.';
    header('Location: all-admin.php'); exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$old    = [];

// ── POST handler ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {

    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $old['first_name'] = trim($_POST['first_name'] ?? '');
        $old['last_name']  = trim($_POST['last_name']  ?? '');
        $old['email']      = trim($_POST['email']       ?? '');
        $old['username']   = trim($_POST['username']    ?? '');
        $old['role']       = $_POST['role']              ?? '';
        $old['notes']      = trim($_POST['notes']        ?? '');
        $password          = $_POST['password']          ?? '';
        $password2         = $_POST['password2']         ?? '';

        // Validation
        if (empty($old['first_name']))            $errors[] = 'First name is required.';
        if (empty($old['last_name']))             $errors[] = 'Last name is required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($old['username']) < 3)         $errors[] = 'Username must be at least 3 characters.';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $old['username'])) $errors[] = 'Username: letters, numbers, underscores only.';
        if (!in_array($old['role'], ['super_admin','admin','manager'])) $errors[] = 'Please select a valid role.';

        // Password requirements
        if (strlen($password) < 8)                  $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $password))       $errors[] = 'Password needs an uppercase letter.';
        if (!preg_match('/[a-z]/', $password))       $errors[] = 'Password needs a lowercase letter.';
        if (!preg_match('/[0-9]/', $password))       $errors[] = 'Password needs a number.';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password needs a special character.';
        if ($password !== $password2)               $errors[] = 'Passwords do not match.';

        // Uniqueness checks
        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM admin_user WHERE email = ? LIMIT 1");
            $chk->bind_param('s', $old['email']);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'Email address is already in use.';
            $chk->close();

            $chk2 = $conn->prepare("SELECT id FROM admin_user WHERE username = ? LIMIT 1");
            $chk2->bind_param('s', $old['username']);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) $errors[] = 'Username is already taken.';
            $chk2->close();
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins  = $conn->prepare(
                "INSERT INTO admin_user (username, email, password, first_name, last_name, role, status, notes, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())"
            );
            $ins->bind_param(
                'sssssssi',
                $old['username'], $old['email'], $hash,
                $old['first_name'], $old['last_name'], $old['role'],
                $old['notes'], $my_id
            );

            if ($ins->execute()) {
                $new_id = $conn->insert_id;
                $ins->close();

                // Send welcome email
                $fullName = trim($old['first_name'] . ' ' . $old['last_name']);
                $roleLabel = ucfirst(str_replace('_', ' ', $old['role']));
                $loginUrl  = BASE_URL . 'admin/auth/login.php';
                send_welcome_email($old['email'], $fullName, 'admin', [
                    'role'      => $roleLabel,
                    'login_url' => $loginUrl,
                ]);

                error_log("Admin created: {$old['username']} (ID $new_id) by admin ID $my_id");

                $_SESSION['admin_success'] = "Admin account for {$fullName} created successfully.";
                header('Location: all-admin.php'); exit();
            } else {
                $errors[] = 'Database error: ' . $conn->error;
                $ins->close();
            }
        }

        // Rotate CSRF on failed attempt
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$favicon = get_favicon();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Create Admin | REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
  <?php include 'links.php'; ?>
  <style>
    :root { --pri:#0C74C5; --acc:#02c9b8; }
    .form-card { border-radius:14px; border:none; box-shadow:0 2px 16px rgba(0,0,0,.08); }
    .section-divider { border-top:1px solid #f1f5f9; margin:22px 0; }
    .req-list { list-style:none; padding:0; margin:6px 0 0; display:flex; flex-wrap:wrap; gap:6px; }
    .req-list li { font-size:.74rem; padding:2px 8px; border-radius:50px;
                   background:#f1f5f9; color:#9ca3af; transition:.2s; }
    .req-list li.ok  { background:#dcfce7; color:#15803d; }
    .req-list li.bad { background:#fef2f2; color:#dc2626; }
    .pw-bar { height:4px; border-radius:2px; background:#e5e7eb; margin-top:6px; }
    .pw-fill { height:100%; border-radius:2px; width:0; transition:.3s; }
    .role-info { border-radius:10px; border:1px solid #e5e7eb; padding:12px; font-size:.82rem;
                 background:#f8fafc; }
    .role-info .role-row { display:flex; gap:10px; align-items:flex-start; padding:6px 0;
                           border-bottom:1px solid #f1f5f9; }
    .role-info .role-row:last-child { border-bottom:none; }
    .role-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
    .page-header-bar { background:#fff; border-radius:12px; padding:18px 22px;
                       box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:20px; }
  </style>
</head>
<body class="crm_body_bg">
<?php include 'header.php'; ?>

<section class="main_content dashboard_part large_header_bg">
  <div class="container-fluid g-0">
    <div class="row"><div class="col-lg-12 p-0"><?php include 'top_nav.php'; ?></div></div>
  </div>

  <div class="main_content_iner">
    <div class="container-fluid p-0 sm_padding_15px">

      <!-- Page header -->
      <div class="page-header-bar d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-0 fw-700" style="color:#1f2937;">Create Admin Account</h4>
          <p class="mb-0 mt-1" style="font-size:.82rem;color:#6b7280;">
            Add a new administrator to the system
          </p>
        </div>
        <a href="all-admin.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
          <i class="fas fa-arrow-left me-1"></i>Back
        </a>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <i class="fas fa-exclamation-circle me-2"></i>
          <strong>Please fix the following:</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-4">

        <!-- Main Form -->
        <div class="col-lg-8">
          <div class="form-card card">
            <div class="card-body p-4">
              <form method="post" action="" id="createForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <h6 class="mb-3 fw-700" style="color:#374151;">Personal Information</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name"
                           value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                           required placeholder="First name">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name"
                           value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                           required placeholder="Last name">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                      <input type="email" class="form-control" name="email"
                             value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                             required placeholder="admin@example.com">
                    </div>
                    <div class="form-text">A welcome email will be sent to this address.</div>
                  </div>
                </div>

                <div class="section-divider"></div>
                <h6 class="mb-3 fw-700" style="color:#374151;">Account Credentials</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text">@</span>
                      <input type="text" class="form-control" name="username" id="username"
                             value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                             required placeholder="username" pattern="[a-zA-Z0-9_]+"
                             minlength="3" maxlength="50">
                    </div>
                    <div class="form-text">Letters, numbers, underscores only.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" name="role" required>
                      <option value="">Select role…</option>
                      <option value="super_admin" <?= ($old['role']??'')==='super_admin'?'selected':'' ?>>Super Admin</option>
                      <option value="admin"       <?= ($old['role']??'')==='admin'?'selected':'' ?>>Admin</option>
                      <option value="manager"     <?= ($old['role']??'')==='manager'?'selected':'' ?>>Manager</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <input type="password" class="form-control" name="password" id="password"
                             required autocomplete="new-password" placeholder="Strong password"
                             oninput="evalPw(this.value)">
                      <button type="button" class="btn btn-outline-secondary"
                              onclick="toggleVis('password','ei1')"><i class="fas fa-eye" id="ei1"></i></button>
                    </div>
                    <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                    <ul class="req-list" id="reqs">
                      <li id="r-len">8+ chars</li>
                      <li id="r-upper">Uppercase</li>
                      <li id="r-lower">Lowercase</li>
                      <li id="r-num">Number</li>
                      <li id="r-special">Special char</li>
                    </ul>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <input type="password" class="form-control" name="password2" id="password2"
                             required autocomplete="new-password" placeholder="Re-enter password"
                             oninput="checkMatch()">
                      <button type="button" class="btn btn-outline-secondary"
                              onclick="toggleVis('password2','ei2')"><i class="fas fa-eye" id="ei2"></i></button>
                    </div>
                    <div id="matchMsg" class="form-text"></div>
                  </div>
                </div>

                <div class="section-divider"></div>
                <h6 class="mb-3 fw-700" style="color:#374151;">Notes <span class="text-muted fw-400">(optional)</span></h6>
                <textarea class="form-control" name="notes" rows="2"
                          placeholder="Internal notes about this admin account…"
                          maxlength="500"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>

                <div class="d-flex gap-2 mt-4">
                  <button type="submit" name="create_admin" id="submitBtn"
                          class="btn text-white fw-600" style="background:var(--pri);border-radius:8px;padding:10px 24px;" disabled>
                    <i class="fas fa-user-plus me-2"></i>Create Admin Account
                  </button>
                  <a href="all-admin.php" class="btn btn-outline-secondary" style="border-radius:8px;">Cancel</a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Side info -->
        <div class="col-lg-4">
          <div class="form-card card mb-4">
            <div class="card-body p-4">
              <h6 class="fw-700 mb-3" style="color:#374151;"><i class="fas fa-info-circle me-2 text-primary"></i>Role Permissions</h6>
              <div class="role-info">
                <div class="role-row">
                  <div class="role-dot" style="background:#dc2626;"></div>
                  <div>
                    <div class="fw-600 text-danger">Super Admin</div>
                    <div class="text-muted">Full system access, can manage all admins, approve doctors, all settings.</div>
                  </div>
                </div>
                <div class="role-row">
                  <div class="role-dot" style="background:#2563eb;"></div>
                  <div>
                    <div class="fw-600 text-primary">Admin</div>
                    <div class="text-muted">Manage content, patients, appointments. Cannot manage other admins.</div>
                  </div>
                </div>
                <div class="role-row">
                  <div class="role-dot" style="background:#16a34a;"></div>
                  <div>
                    <div class="fw-600 text-success">Manager</div>
                    <div class="text-muted">View reports, manage orders and basic content only.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="form-card card">
            <div class="card-body p-4">
              <h6 class="fw-700 mb-3" style="color:#374151;"><i class="fas fa-shield-alt me-2 text-warning"></i>Security Tips</h6>
              <ul style="font-size:.82rem;color:#6b7280;padding-left:1.2rem;line-height:1.8;">
                <li>Use a strong, unique password</li>
                <li>Assign minimum required role</li>
                <li>The admin will receive a welcome email</li>
                <li>They can reset their password via the login page</li>
                <li>Activity is logged for audit purposes</li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</section>

<script>
const checks = {
  'r-len':     v => v.length >= 8,
  'r-upper':   v => /[A-Z]/.test(v),
  'r-lower':   v => /[a-z]/.test(v),
  'r-num':     v => /[0-9]/.test(v),
  'r-special': v => /[^A-Za-z0-9]/.test(v),
};
const colors = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];

function evalPw(val) {
  let score = 0;
  for (const [id, fn] of Object.entries(checks)) {
    const el = document.getElementById(id);
    const ok = fn(val);
    el.className = ok ? 'ok' : (val.length ? 'bad' : '');
    if (ok) score++;
  }
  const fill = document.getElementById('pwFill');
  fill.style.width      = (score / 5 * 100) + '%';
  fill.style.background = colors[score - 1] || '#e5e7eb';
  checkSubmit();
}

function checkMatch() {
  const p1  = document.getElementById('password').value;
  const p2  = document.getElementById('password2').value;
  const msg = document.getElementById('matchMsg');
  if (!p2) { msg.textContent = ''; }
  else if (p1 === p2) { msg.textContent = '✓ Passwords match'; msg.style.color = '#16a34a'; }
  else                { msg.textContent = '✗ Do not match';    msg.style.color = '#dc2626'; }
  checkSubmit();
}

function checkSubmit() {
  const p1  = document.getElementById('password').value;
  const p2  = document.getElementById('password2').value;
  const allOk = Object.values(checks).every(fn => fn(p1));
  document.getElementById('submitBtn').disabled = !(allOk && p1 === p2 && p2.length > 0);
}

function toggleVis(id, iconId) {
  const el = document.getElementById(id);
  const ic = document.getElementById(iconId);
  el.type = el.type === 'password' ? 'text' : 'password';
  ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash');
}

// Auto-generate username from first+last name
document.querySelector('[name="first_name"]').addEventListener('input', autoUsername);
document.querySelector('[name="last_name"]').addEventListener('input', autoUsername);
function autoUsername() {
  const un = document.getElementById('username');
  if (un.dataset.touched) return;
  const fn = document.querySelector('[name="first_name"]').value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
  const ln = document.querySelector('[name="last_name"]').value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
  if (fn || ln) un.value = fn + (ln ? '_' + ln : '');
}
document.getElementById('username').addEventListener('input', function() {
  this.dataset.touched = '1';
});
</script>
</body>
</html>
