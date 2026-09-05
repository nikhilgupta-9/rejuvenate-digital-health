<?php
session_start();
include_once "config/connect.php";
include_once "config/abdm.php";
include_once "util/function.php";
require_once __DIR__ . "/lib/Security.php";

$csrf_token = Security::csrfToken();
$logo       = get_header_logo();
$abdm_on    = ABDM_CONFIGURED;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot ABHA | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    :root {
      --primary: #0C74C5;
      --ab: #00875a;
      --ink: #1c1e21;
      --muted: #65676b;
      --line: #dddfe2;
      --field-bg: #f5f6f7;
    }

    html, body { overflow-x: hidden; }

    .fa-wrap {
      background: #f0f2f5;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      padding: 30px 16px 40px;
    }

    .fa-card {
      max-width: 440px;
      margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .1), 0 2px 12px rgba(0, 0, 0, .08);
      overflow: hidden;
    }

    .fa-head {
      text-align: center;
      padding: 22px 20px 6px;
    }

    .fa-head img { max-height: 44px; margin-bottom: 10px; }
    .fa-head h1 { font-size: 1.12rem; font-weight: 700; color: var(--ink); margin: 0; }
    .fa-head p { font-size: .82rem; color: var(--muted); margin: 4px 0 0; }

    .fa-body { padding: 16px 20px 24px; }

    .fa-body .form-label { font-size: .82rem; font-weight: 600; color: var(--ink); margin-bottom: 4px; }

    .fa-body .form-control {
      font-size: 1rem;
      padding: 10px 14px;
      border-radius: 8px;
      border: 1px solid var(--line);
      background: var(--field-bg);
    }

    .fa-body .form-control:focus {
      background: #fff;
      border-color: var(--ab);
      box-shadow: 0 0 0 3px rgba(0, 135, 90, .15);
    }

    .fa-body small.text-muted { font-size: .74rem; }

    .fa-choice {
      display: flex;
      gap: 8px;
      margin-bottom: 14px;
    }

    .fa-choice label {
      flex: 1;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 9px 8px;
      text-align: center;
      font-size: .84rem;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      background: var(--field-bg);
      transition: .12s;
    }

    .fa-choice input { display: none; }
    .fa-choice input:checked + span { color: var(--ab); }
    .fa-choice label:has(input:checked) {
      border-color: var(--ab);
      background: #f0fdf9;
      color: var(--ab);
    }

    .fa-btn {
      width: 100%;
      padding: 11px;
      border: 0;
      border-radius: 9px;
      font-weight: 600;
      font-size: .95rem;
      background: var(--ab);
      color: #fff;
    }

    .fa-btn:disabled { opacity: .6; }

    .fa-btn-ghost {
      background: #fff;
      color: var(--muted);
      border: 1px solid var(--line);
    }

    .otp-in {
      letter-spacing: .5em;
      text-align: center;
      font-size: 1.3rem !important;
      font-weight: 700;
    }

    #faAlert { display: none; font-size: .85rem; padding: 9px 12px; border-radius: 8px; margin-bottom: 12px; }

    .acct {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 10px;
    }

    .acct .nm { font-weight: 700; color: var(--ink); font-size: .95rem; }
    .acct .row-x { display: flex; justify-content: space-between; gap: 8px; margin-top: 4px; font-size: .82rem; color: var(--muted); }
    .acct .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .badge-st {
      font-size: .68rem;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 10px;
      text-transform: uppercase;
    }

    .badge-st.on { background: #dcfce7; color: #166534; }
    .badge-st.off { background: #fee2e2; color: #991b1b; }

    .fa-foot { text-align: center; font-size: .82rem; color: var(--muted); margin-top: 16px; }
    .fa-foot a { color: var(--ab); font-weight: 600; }
  </style>
</head>

<body>
  <div class="fa-wrap">
    <div class="fa-card">

      <div class="fa-head">
        <?php if ($logo): ?><img src="<?= BASE_URL . htmlspecialchars($logo) ?>" alt="REJUVENATE"><?php endif; ?>
        <h1>Forgot your ABHA?</h1>
        <p>Verify with an OTP to see the ABHA account(s) linked to your Aadhaar or mobile number.</p>
      </div>

      <div class="fa-body">
        <div id="faAlert"></div>

        <?php if (!$abdm_on): ?>
          <p class="text-muted" style="font-size:.88rem;">ABHA recovery is currently unavailable. Please try again later.</p>
        <?php else: ?>

          <!-- Step 1 -->
          <div id="faStep1">
            <div class="fa-choice">
              <label><input type="radio" name="faMethod" value="mobile" checked onchange="faSwitch()"><span>Mobile Number</span></label>
              <label><input type="radio" name="faMethod" value="aadhaar" onchange="faSwitch()"><span>Aadhaar Number</span></label>
            </div>

            <div class="mb-3">
              <label class="form-label" id="faInLabel">Registered Mobile Number</label>
              <input type="text" class="form-control" id="faValue" inputmode="numeric" placeholder="10-digit mobile number" autocomplete="off">
              <small class="text-muted" id="faInHint">OTP goes to this mobile if it is registered with ABDM.</small>
            </div>

            <button class="fa-btn" id="faSendBtn" onclick="faSendOtp()">
              <i class="fas fa-paper-plane me-2"></i>Send OTP
            </button>
          </div>

          <!-- Step 2 -->
          <div id="faStep2" style="display:none;">
            <p style="font-size:.84rem;color:#374151;margin-bottom:12px;" id="faOtpMsg"></p>
            <div class="mb-3">
              <label class="form-label">Enter 6-digit OTP</label>
              <input type="text" class="form-control otp-in" id="faOtp" maxlength="6" inputmode="numeric" placeholder="••••••">
            </div>
            <div class="d-flex gap-2">
              <button class="fa-btn fa-btn-ghost" style="flex:0 0 90px;" onclick="faReset()">
                <i class="fas fa-arrow-left me-1"></i>Back
              </button>
              <button class="fa-btn" style="flex:1;" id="faVerifyBtn" onclick="faVerifyOtp()">
                <i class="fas fa-check me-2"></i>Verify
              </button>
            </div>
          </div>

          <!-- Step 3 — result -->
          <div id="faStep3" style="display:none;">
            <p style="font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:12px;">
              <i class="fas fa-id-card me-2" style="color:var(--ab);"></i>Your ABHA account<span id="faPlural"></span>
            </p>
            <div id="faAccts"></div>
            <a href="<?= BASE_URL ?>login.php" class="fa-btn d-block text-center text-decoration-none mt-3" style="background:var(--primary);">
              <i class="fas fa-sign-in-alt me-2"></i>Go to Login
            </a>
          </div>

        <?php endif; ?>

        <div class="fa-foot">
          Remembered it? <a href="<?= BASE_URL ?>login.php">Back to Login</a>
        </div>
      </div>

    </div>
  </div>

  <script>
    const BASE = '<?= BASE_URL ?>';
    const RDH_CSRF = '<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>';

    function faAlert(msg, kind) {
      const b = document.getElementById('faAlert');
      b.className = '';
      b.style.background = kind === 'ok' ? '#dcfce7' : '#fee2e2';
      b.style.color = kind === 'ok' ? '#166534' : '#991b1b';
      b.innerHTML = msg;
      b.style.display = 'block';
    }
    function faClear() { document.getElementById('faAlert').style.display = 'none'; }

    function faBtn(id, loading) {
      const b = document.getElementById(id);
      if (!b) return;
      b.disabled = loading;
      if (loading) { b.dataset.o = b.innerHTML; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait…'; }
      else if (b.dataset.o) b.innerHTML = b.dataset.o;
    }

    async function faPost(action, body) {
      const r = await fetch(BASE + 'ajax/forgot-abha.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action, _csrf: RDH_CSRF }, body))
      });
      return r.json();
    }

    function faMethod() {
      const el = document.querySelector('input[name="faMethod"]:checked');
      return el ? el.value : 'mobile';
    }

    function faSwitch() {
      const m = faMethod();
      const inp = document.getElementById('faValue');
      inp.value = '';
      if (m === 'aadhaar') {
        document.getElementById('faInLabel').textContent = 'Aadhaar Number';
        inp.placeholder = 'XXXX XXXX XXXX';
        inp.maxLength = 14;
        document.getElementById('faInHint').textContent = 'RSA-encrypted before it reaches ABDM. Never stored.';
      } else {
        document.getElementById('faInLabel').textContent = 'Registered Mobile Number';
        inp.placeholder = '10-digit mobile number';
        inp.maxLength = 10;
        document.getElementById('faInHint').textContent = 'OTP goes to this mobile if it is registered with ABDM.';
      }
    }

    document.getElementById('faValue')?.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '');
      if (faMethod() === 'aadhaar') {
        v = v.substring(0, 12).replace(/(.{4})/g, '$1 ').trim();
      } else {
        v = v.substring(0, 10);
      }
      this.value = v;
    });

    async function faSendOtp() {
      faClear();
      const method = faMethod();
      const value = document.getElementById('faValue').value.replace(/\D/g, '');
      if (method === 'aadhaar' && value.length !== 12) return faAlert('Enter a valid 12-digit Aadhaar number.');
      if (method === 'mobile' && value.length !== 10) return faAlert('Enter a valid 10-digit mobile number.');

      faBtn('faSendBtn', true);
      const res = await faPost('send_otp', { method, value });
      faBtn('faSendBtn', false);

      if (!res.success) return faAlert(res.message || 'Could not send the OTP.');

      document.getElementById('faOtpMsg').innerHTML =
        'OTP sent to <strong>' + (res.target || 'your registered mobile') + '</strong>. Valid for a few minutes.';
      document.getElementById('faStep1').style.display = 'none';
      document.getElementById('faStep2').style.display = 'block';
      document.getElementById('faOtp').focus();
    }

    async function faVerifyOtp() {
      faClear();
      const otp = document.getElementById('faOtp').value.replace(/\D/g, '');
      if (otp.length !== 6) return faAlert('Enter the 6-digit OTP.');

      faBtn('faVerifyBtn', true);
      const res = await faPost('verify_otp', { otp });
      faBtn('faVerifyBtn', false);

      if (!res.success) return faAlert(res.message || 'OTP verification failed.');

      const list = Array.isArray(res.accounts) ? res.accounts : [];
      const box = document.getElementById('faAccts');
      box.innerHTML = '';
      if (!list.length) {
        box.innerHTML = '<p class="text-muted" style="font-size:.86rem;">No ABHA account was found for this ' +
          (faMethod() === 'aadhaar' ? 'Aadhaar' : 'mobile number') + '.</p>';
      }
      list.forEach(a => {
        const off = ['DEACTIVATED', 'DELETED', 'INACTIVE'].includes((a.status || '').toUpperCase());
        const d = document.createElement('div');
        d.className = 'acct';
        d.innerHTML =
          '<div class="nm">' + esc(a.name || '—') +
          ' <span class="badge-st ' + (off ? 'off' : 'on') + '">' + esc(a.status || 'ACTIVE') + '</span></div>' +
          '<div class="row-x"><span>ABHA Number</span><span class="mono">' + esc(a.abha_number || '—') + '</span></div>' +
          (a.abha_address ? '<div class="row-x"><span>ABHA Address</span><span class="mono">' + esc(a.abha_address) + '</span></div>' : '');
        box.appendChild(d);
      });

      document.getElementById('faPlural').textContent = list.length === 1 ? '' : 's';
      document.getElementById('faStep2').style.display = 'none';
      document.getElementById('faStep3').style.display = 'block';
    }

    function faReset() {
      faClear();
      document.getElementById('faOtp').value = '';
      document.getElementById('faStep2').style.display = 'none';
      document.getElementById('faStep1').style.display = 'block';
    }

    function esc(s) {
      return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
  </script>
</body>

</html>
