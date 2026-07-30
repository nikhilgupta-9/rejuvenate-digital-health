<?php
include_once "../config/connect.php";
include_once "../util/function.php";

$logo = get_header_logo();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = false;
$member = null;

if ($token) {
    $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM school_members WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
}

// Validate token
if (!$token || !$member) {
    $error = "Invalid link. Please request a new one.";
} elseif (!$member['reset_token_expiry'] || strtotime($member['reset_token_expiry']) < time()) {
    $error = "This link has expired. Please request a new one.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Set the new password and clear the token so the link can't be reused.
        $upd = $conn->prepare("UPDATE school_members SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $upd->bind_param('si', $hashed, $member['id']);
        $upd->execute();

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set Your Password | Rejuvenate Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>

<body>
  <?php include("../header.php") ?>

  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
          <div class="reg-card">

            <div class="text-center mb-4">
              <img src="<?= BASE_URL . $logo ?>" class="img-fluid mb-3" style="max-height:48px;">
              <h4 class="fw-bold mb-1">Set Your Password</h4>
              <?php if (!$success && !$error): ?>
                <p class="text-muted" style="font-size:.83rem;">Choose a password for your account.</p>
              <?php endif; ?>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">
                <i class="fa fa-check-circle me-2"></i>Password set successfully!
              </div>
              <a href="<?= BASE_URL ?>school-login.php" class="btn btn-primary w-100 py-2 fw-semibold"
                style="border-radius:10px;font-size:.95rem;">
                <i class="fa fa-sign-in-alt me-2"></i>Go to Login
              </a>

            <?php elseif ($error): ?>
              <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;">
                <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
              </div>
              <a href="request-password-link.php" class="btn btn-outline-primary w-100 py-2 fw-semibold"
                style="border-radius:10px;font-size:.95rem;">
                Request New Link
              </a>

            <?php else: ?>
              <form method="POST" autocomplete="off">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="field-group">
                  <label>New Password <span class="text-danger">*</span></label>
                  <div class="pass-wrap">
                    <input type="password" class="form-control" name="password" id="pw1"
                      placeholder="At least 8 characters" required minlength="8" oninput="pwStrength(this)">
                    <button type="button" class="toggle" onclick="togglePw('pw1',this)"><i
                        class="fas fa-eye"></i></button>
                  </div>
                  <div class="pw-bar">
                    <div class="pw-bar-fill" id="pwBar"></div>
                  </div>
                  <div id="pwStrengthTxt" style="font-size:.7rem;color:#9ca3af;margin-top:2px;"></div>
                </div>

                <div class="field-group">
                  <label>Confirm Password <span class="text-danger">*</span></label>
                  <div class="pass-wrap">
                    <input type="password" class="form-control" name="confirm_password" id="pw2"
                      placeholder="Re-enter password" required minlength="8" oninput="checkMatch()">
                    <button type="button" class="toggle" onclick="togglePw('pw2',this)"><i
                        class="fas fa-eye"></i></button>
                  </div>
                  <div id="matchTxt" style="font-size:.73rem;margin-top:3px;"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"
                  style="border-radius:10px;font-size:.95rem;">
                  <i class="fa fa-key me-2"></i>Set Password
                </button>
              </form>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include("../footer.php") ?>

  <script>
    function togglePw(id, btn) {
      const inp = document.getElementById(id);
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function pwStrength(inp) {
      const p = inp.value;
      const bar = document.getElementById('pwBar');
      const txt = document.getElementById('pwStrengthTxt');
      let s = 0;
      if (p.length >= 8) s++;
      if (p.match(/[a-z]/)) s++;
      if (p.match(/[A-Z]/)) s++;
      if (p.match(/[0-9]/)) s++;
      if (p.match(/[^a-zA-Z0-9]/)) s++;
      const levels = [
        { w: '20%', c: '#dc2626', l: 'Very weak' },
        { w: '40%', c: '#ea580c', l: 'Weak' },
        { w: '60%', c: '#d97706', l: 'Fair' },
        { w: '80%', c: '#16a34a', l: 'Good' },
        { w: '100%', c: '#0C74C5', l: 'Strong' },
      ];
      const lv = levels[Math.max(0, s - 1)];
      bar.style.width = lv.w;
      bar.style.background = lv.c;
      txt.textContent = p.length ? lv.l : '';
      txt.style.color = lv.c;
      checkMatch();
    }

    function checkMatch() {
      const p1 = document.getElementById('pw1').value;
      const p2 = document.getElementById('pw2').value;
      const el = document.getElementById('matchTxt');
      if (!p2) { el.textContent = ''; return; }
      if (p1 === p2) {
        el.innerHTML = '<span style="color:#16a34a"><i class="fas fa-check me-1"></i>Passwords match</span>';
      } else {
        el.innerHTML = '<span style="color:#dc2626"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
      }
    }
  </script>
</body>

</html>
