<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';
require_once dirname(__DIR__) . '/lib/Security.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars(Security::csrfToken()) ?>">
  <title>Verify Patient ABHA — Rejuvenate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
</head>

<body>
  <main class="doctor-content">

    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <a href="<?= BASE_URL ?>doctor/add-patient.php" class="back-link">
          <i class="fa fa-arrow-left mr-1"></i> Back to options
        </a>
        <h5 class="mb-0 font-weight-bold mt-1" style="color:var(--gray-800);">Verify Existing ABHA</h5>
        <div style="font-size:.74rem;color:var(--gray-400);">Find and verify patient's ABHA using ABDM</div>
      </div>
      <div>
        <span class="step-badge pending" id="statusBadge">
          <i class="fa fa-clock-o"></i> Pending
        </span>
      </div>
    </div>

    <?php if (!ABDM_CONFIGURED): ?>
      <div class="alert alert-danger alert-custom">
        <i class="fa fa-times-circle mr-2"></i> ABDM not configured on this server.
      </div>
    <?php else: ?>

      <!-- Wizard -->
      <div class="wizard">
        <div class="wi active" id="stepIndicator1">
          <div class="wc">1</div>
          <span class="wl">Identity</span>
        </div>
        <div class="wi" id="stepIndicator2">
          <div class="wc">2</div>
          <span class="wl">OTP</span>
        </div>
        <div class="wi" id="stepIndicator3">
          <div class="wc">3</div>
          <span class="wl">Verify</span>
        </div>
        <div class="wi" id="stepIndicator4">
          <div class="wc">4</div>
          <span class="wl">Complete</span>
        </div>
      </div>

      <!-- Step 1: Identity -->
      <div class="step-content active" id="step1">
        <div class="cp-card highlight">
          <div class="cp-title"><i class="fa fa-shield mr-2" style="color:var(--primary);"></i>Patient Identity</div>

          <div class="mb-3">
            <label class="font-weight-bold" style="font-size:.8rem;color:var(--gray-600);">Verification Method</label>
            <div>
              <button class="method-tab active" onclick="setMethod('aadhaar', this)">
                <i class="fa fa-id-card"></i> Aadhaar OTP
                <span class="badge-icon">Recommended</span>
              </button>
              <button class="method-tab" onclick="setMethod('mobile', this)">
                <i class="fa fa-mobile"></i> Mobile OTP
              </button>
              <button class="method-tab" onclick="setMethod('number', this)">
                <i class="fa fa-barcode"></i> ABHA Number
              </button>
              <button class="method-tab" onclick="setMethod('address', this)">
                <i class="fa fa-at"></i> ABHA Address
              </button>
            </div>
          </div>

          <div id="methodNote" class="alert alert-info alert-custom mb-3"
            style="background:#eff6ff;border:1px solid #bfdbfe;">
            <i class="fa fa-lock mr-1"></i>
            <span id="methodNoteText">Aadhaar is RSA-encrypted before sending to ABDM. <strong>Never stored</strong> in
              our database.</span>
          </div>

          <div class="form-group">
            <label class="font-weight-bold" id="inputLabel" style="font-size:.85rem;color:var(--gray-700);">
              Aadhaar Number <span style="color:var(--danger);">*</span>
            </label>
            <div class="input-group input-group-custom">
              <div class="input-group-prepend" id="mobilePrefix" style="display:none;">
                <span class="input-group-text">+91</span>
              </div>
              <input type="password" id="mainInput" class="form-control" placeholder="•••• •••• ••••" maxlength="12"
                autocomplete="off">
              <div class="input-group-append" id="qrScanGroup" style="display:none;">
                <button class="btn btn-outline-secondary" type="button" id="btnScanAbhaQr" title="Scan ABHA QR">
                  <i class="fa fa-qrcode"></i>
                </button>
              </div>
            </div>
            <div class="form-hint" id="inputHint">12-digit Aadhaar — OTP sent to Aadhaar-linked mobile</div>
          </div>

          <!-- Patient Consent (ABDM: required before Aadhaar-based ABHA enrolment) — Aadhaar method only -->
          <div id="aadhaarConsentGroup">
            <div class="mb-3 p-3" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;max-height:110px;overflow-y:auto;font-size:.75rem;color:#374151;line-height:1.6;">
              <strong>Patient Consent</strong><br>
              The patient hereby declares that they are voluntarily sharing their Aadhaar number and demographic
              information issued by UIDAI, with the National Health Authority (NHA), for the sole purpose of
              creating an ABHA number, and understands their identifiable information may be shared with the
              treating healthcare professional as per ABDM guidelines.
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="aadhaar_consent" value="1">
              <label class="form-check-label font-weight-bold" for="aadhaar_consent" style="font-size:.82rem;">
                I confirm the patient has read and agreed to the above consent
              </label>
            </div>
          </div>

          <button class="btn btn-primary-custom" id="btnSend">
            <i class="fa fa-paper-plane mr-1"></i> Send OTP
          </button>

          <div id="error1" class="alert alert-danger alert-custom mt-3" style="display:none;"></div>
          <div id="info1" class="alert alert-info alert-custom mt-3" style="display:none;"></div>
        </div>
      </div>

      <!-- Step 2: OTP Entry -->
      <div class="step-content" id="step2">
        <div class="cp-card highlight">
          <div class="cp-title"><i class="fa fa-mobile mr-2" style="color:var(--primary);"></i>Enter Verification Code
          </div>

          <div class="text-center mb-3">
            <p class="text-muted" id="sentMsg" style="font-size:.86rem;margin:0;">
              <i class="fa fa-check-circle" style="color:var(--success);"></i> OTP sent to registered mobile
            </p>
            <div style="font-size:.72rem;color:var(--gray-400);" id="otpExpiryInfo">Valid for 5 minutes</div>
          </div>

          <div class="otp-container" id="otpContainer">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" autofocus>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <button class="resend-btn" id="btnResend" disabled>
              <i class="fa fa-refresh mr-1"></i> Resend OTP
            </button>
            <span class="timer-text" id="timerEl">Resend in 300s</span>
          </div>

          <div class="form-group" id="commMobileGroup" style="display:none;">
            <label class="form-label" style="font-size:.85rem;font-weight:600;">Mobile Number for ABHA Communication
              *</label>
            <input type="text" class="form-control" id="commMobileInput" maxlength="10" inputmode="numeric"
              placeholder="9876543210">
            <div class="form-hint">10-digit mobile number ABDM will link to this ABHA for communication</div>
          </div>

          <div id="error2" class="alert alert-danger alert-custom" style="display:none;"></div>

          <div class="d-flex" style="gap:10px;">
            <button class="btn-outline-custom" onclick="goToStep(1)">
              <i class="fa fa-arrow-left mr-1"></i> Back
            </button>
            <button class="btn btn-primary-custom" id="btnVerify">
              <i class="fa fa-check mr-1"></i> Verify OTP
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2b: ABHA Selection -->
      <div class="step-content" id="step2b">
        <div class="cp-card highlight">
          <div class="cp-title"><i class="fa fa-users mr-2" style="color:var(--primary);"></i>Select Patient's ABHA</div>
          <div class="alert alert-info alert-custom">
            <i class="fa fa-info-circle mr-1"></i>
            Multiple ABHA accounts found linked to this mobile. Please select the correct patient.
          </div>
          <div id="accountList"></div>
          <div id="error2b" class="alert alert-danger alert-custom mt-3" style="display:none;"></div>
        </div>
      </div>

      <!-- Step 3: Result -->
      <div class="step-content" id="step3">
        <div class="cp-card" id="resultCard">
          <div class="result-card" id="resultContent">
            <div class="icon-circle info">
              <i class="fa fa-spinner fa-spin"></i>
            </div>
            <h5 class="font-weight-bold" style="color:var(--gray-800);">Processing...</h5>
            <p class="text-muted" style="font-size:.85rem;">Please wait while we verify the patient</p>
          </div>
        </div>
      </div>

    <?php endif; ?>
  </main>

  <script src="<?= BASE_URL ?>assets/js/abha-qr-scanner.js"></script>
  <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    // State
    let currentStep = 1;
    let verificationType = 'aadhaar';

    document.getElementById('btnScanAbhaQr')?.addEventListener('click', function () {
      AbhaQrScanner.open({
        title: 'Scan Patient ABHA QR',
        onResult(parsed) {
          const input = document.getElementById('mainInput');
          if (verificationType === 'number' && parsed.abha_number) {
            input.value = parsed.abha_number;
          } else if (verificationType === 'address' && parsed.abha_address) {
            input.value = parsed.abha_address;
          } else if (parsed.abha_number || parsed.abha_address) {
            input.value = parsed.abha_number || parsed.abha_address;
          } else {
            showError('error1', 'QR did not contain a recognisable ABHA number or address.');
          }
        },
        onUnsupported() {
          showError('error1', 'QR scanning is not supported in this browser. Please enter the value manually.');
        }
      });
    });
    let txnId = '';
    let timerInterval = null;
    let timerSeconds = 300;

    // DOM refs
    const stepContent = {
      1: document.getElementById('step1'),
      2: document.getElementById('step2'),
      "2b": document.getElementById('step2b'),
      3: document.getElementById('step3')
    };

    const stepIndicators = {
      1: document.getElementById('stepIndicator1'),
      2: document.getElementById('stepIndicator2'),
      3: document.getElementById('stepIndicator3'),
      4: document.getElementById('stepIndicator4')
    };

    // ── Navigation ──
    function goToStep(step) {
      // Hide all steps
      Object.values(stepContent).forEach(el => el.classList.remove('active'));

      // Show target step
      if (stepContent[step]) {
        stepContent[step].classList.add('active');
      }

      // Update indicators
      const stepNum = step === '2b' ? 2 : step;
      const steps = [1, 2, 3, 4];
      steps.forEach((s, index) => {
        const indicator = stepIndicators[s];
        indicator.className = 'wi';
        if (s < stepNum) {
          indicator.classList.add('done');
        } else if (s === stepNum) {
          indicator.classList.add('active');
        }
      });

      currentStep = step;

      // Update status badge
      const badge = document.getElementById('statusBadge');
      if (step === 1) {
        badge.className = 'step-badge pending';
        badge.innerHTML = '<i class="fa fa-clock-o"></i> Pending';
      } else if (step === 2 || step === "2b") {
        badge.className = 'step-badge pending';
        badge.innerHTML = '<i class="fa fa-clock-o"></i> Verifying';
      } else if (step === 3) {
        // Will be updated by result
      }
    }

    // ── Method switcher ──
    function setMethod(type, btn) {
      document.querySelectorAll('.method-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      verificationType = type;

      // Reset
      const input = document.getElementById('mainInput');
      const prefix = document.getElementById('mobilePrefix');
      const note = document.getElementById('methodNoteText');
      const noteBox = document.getElementById('methodNote');
      const label = document.getElementById('inputLabel');
      const hint = document.getElementById('inputHint');
      const qrGroup = document.getElementById('qrScanGroup');

      input.value = '';
      input.type = 'text';
      prefix.style.display = 'none';
      noteBox.className = 'alert alert-info alert-custom mb-3';
      document.getElementById('error1').style.display = 'none';
      qrGroup.style.display = (type === 'number' || type === 'address') ? 'flex' : 'none';
      document.getElementById('aadhaarConsentGroup').style.display = (type === 'aadhaar') ? 'block' : 'none';

      switch (type) {
        case 'aadhaar':
          label.textContent = 'Aadhaar Number *';
          input.placeholder = '•••• •••• ••••';
          input.maxLength = 12;
          input.type = 'password';
          hint.textContent = '12-digit Aadhaar — OTP sent to Aadhaar-linked mobile';
          note.textContent = 'Aadhaar is RSA-encrypted before sending to ABDM. Never stored in our database.';
          noteBox.className = 'alert alert-info alert-custom mb-3';
          break;

        case 'mobile':
          label.textContent = 'Mobile Number *';
          prefix.style.display = 'flex';
          input.placeholder = '9876543210';
          input.maxLength = 10;
          input.inputMode = 'numeric';
          hint.textContent = '10-digit mobile — OTP sent to this number to log in to the linked ABHA';
          note.textContent = 'An OTP will be sent to this mobile number. If it has ABHA account(s) linked, you can log in directly — no Aadhaar needed.';
          noteBox.className = 'alert alert-info alert-custom mb-3';
          break;

        case 'number':
          label.textContent = 'ABHA Number *';
          input.placeholder = 'XX-XXXX-XXXX-XXXX';
          input.maxLength = 17;
          hint.textContent = '14 digits from patient\'s ABHA card (e.g., 91-1234-5678-9012)';
          noteBox.style.display = 'none';
          break;

        case 'address':
          label.textContent = 'ABHA Address *';
          input.placeholder = 'username@abdm';
          input.maxLength = 60;
          hint.textContent = 'Patient\'s ABHA address (e.g., john.doe@abdm)';
          noteBox.style.display = 'none';
          break;
      }
    }

    // ── OTP input handling ──
    document.querySelectorAll('.otp-box').forEach((el, i, all) => {
      el.addEventListener('input', () => {
        el.value = el.value.replace(/\D/g, '').slice(-1);
        if (el.value) {
          el.classList.add('filled');
          if (all[i + 1]) all[i + 1].focus();
        } else {
          el.classList.remove('filled');
        }
      });

      el.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !el.value && all[i - 1]) {
          all[i - 1].focus();
        }
      });

      el.addEventListener('paste', e => {
        e.preventDefault();
        const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        all.forEach((box, idx) => {
          box.value = data[idx] || '';
          box.classList.toggle('filled', !!box.value);
        });
        const nextIndex = Math.min(data.length, all.length - 1);
        if (data.length > 0) {
          all[nextIndex].focus();
        }
      });
    });

    function getOTP() {
      return [...document.querySelectorAll('.otp-box')].map(e => e.value).join('');
    }

    function showError(id, message) {
      const el = document.getElementById(id);
      el.textContent = message;
      el.style.display = 'block';
    }

    function hideError(id) {
      document.getElementById(id).style.display = 'none';
    }

    function showInfo(id, message) {
      const el = document.getElementById(id);
      el.textContent = message;
      el.style.display = 'block';
    }

    function hideInfo(id) {
      document.getElementById(id).style.display = 'none';
    }

    // ── ABHA number formatting ──
    document.getElementById('mainInput').addEventListener('input', function () {
      if (verificationType !== 'number') return;
      let digits = this.value.replace(/\D/g, '').slice(0, 14);
      let formatted = digits;
      if (digits.length > 2) formatted = digits.slice(0, 2) + '-' + digits.slice(2);
      if (digits.length > 6) formatted = digits.slice(0, 2) + '-' + digits.slice(2, 6) + '-' + digits.slice(6);
      if (digits.length > 10) formatted = digits.slice(0, 2) + '-' + digits.slice(2, 6) + '-' + digits.slice(6, 10) + '-' + digits.slice(10);
      this.value = formatted;
    });

    // ── Timer ──
    function startTimer(seconds) {
      timerSeconds = seconds;
      const btn = document.getElementById('btnResend');
      const el = document.getElementById('timerEl');
      btn.disabled = true;

      if (timerInterval) clearInterval(timerInterval);

      timerInterval = setInterval(() => {
        timerSeconds--;
        if (timerSeconds <= 0) {
          clearInterval(timerInterval);
          el.textContent = '';
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-refresh mr-1"></i> Resend OTP';
        } else {
          el.textContent = `Resend in ${timerSeconds}s`;
        }
      }, 1000);
    }

    // ── Send OTP ──
    document.getElementById('btnSend').addEventListener('click', function () {
      const value = document.getElementById('mainInput').value.trim();

      // Validate
      if (verificationType === 'aadhaar') {
        const clean = value.replace(/\D/g, '');
        if (clean.length !== 12) {
          showError('error1', 'Please enter a valid 12-digit Aadhaar number');
          return;
        }
        if (!document.getElementById('aadhaar_consent').checked) {
          showError('error1', 'Please confirm patient consent to continue');
          return;
        }
      } else if (verificationType === 'mobile') {
        const clean = value.replace(/\D/g, '');
        if (clean.length !== 10) {
          showError('error1', 'Please enter a valid 10-digit mobile number');
          return;
        }
      } else if (verificationType === 'number') {
        const clean = value.replace(/\D/g, '');
        if (clean.length !== 14) {
          showError('error1', 'Please enter a valid 14-digit ABHA number');
          return;
        }
      } else if (verificationType === 'address') {
        if (!value || !value.includes('@')) {
          showError('error1', 'Please enter a valid ABHA address (e.g., username@abdm)');
          return;
        }
      }

      hideError('error1');
      hideInfo('info1');

      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Sending...';

      const payload = {
        action: 'send_otp',
        abha_input: value,
        type: verificationType,
        consent: verificationType === 'aadhaar' ? 1 : undefined,
        _csrf: CSRF_TOKEN
      };

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      })
        .then(r => r.json())
        .then(data => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-paper-plane mr-1"></i> Send OTP';

          if (!data.success) {
            showError('error1', data.error || 'Failed to send OTP');
            return;
          }

          txnId = data.txnId;
          sessionStorage.setItem('abdm_txnId', data.txnId);
          sessionStorage.setItem('abdm_input_type', verificationType);
          sessionStorage.setItem('abdm_input_value', value);

          if (verificationType === 'mobile') {
            sessionStorage.setItem('abdm_mobile_txnId', data.txnId);
          }

          document.getElementById('sentMsg').innerHTML = `
          <i class="fa fa-check-circle" style="color:var(--success);"></i>
          ${data.message || 'OTP sent successfully'}
        `;

          // Reset OTP inputs
          document.querySelectorAll('.otp-box').forEach(el => {
            el.value = '';
            el.classList.remove('filled');
          });

          // ABDM's enrol/byAadhaar requires a communication mobile number
          document.getElementById('commMobileGroup').style.display =
            verificationType === 'aadhaar' ? 'block' : 'none';

          goToStep(2);
          startTimer(300);

          // Focus first OTP input
          document.querySelector('.otp-box').focus();
        })
        .catch(err => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-paper-plane mr-1"></i> Send OTP';
          showError('error1', 'Network error: ' + err.message);
        });
    });

    // ── Resend OTP ──
    document.getElementById('btnResend').addEventListener('click', function () {
      document.getElementById('btnSend').click();
    });

    // ── Verify OTP ──
    document.getElementById('btnVerify').addEventListener('click', function () {
      const otp = getOTP();

      if (otp.length < 6) {
        showError('error2', 'Please enter the complete 6-digit OTP');
        return;
      }

      let commMobile = '';
      if (verificationType === 'aadhaar') {
        commMobile = document.getElementById('commMobileInput').value.replace(/\D/g, '');
        if (commMobile.length !== 10) {
          showError('error2', 'Please enter a valid 10-digit mobile number for ABHA communication');
          return;
        }
      }

      hideError('error2');

      // Get txnId from session if not set
      if (!txnId) {
        txnId = sessionStorage.getItem('abdm_txnId') || '';
        if (!txnId) {
          showError('error2', 'Session expired — please resend OTP');
          return;
        }
      }

      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Verifying...';

      const payload = {
        action: 'verify_otp',
        txnId: txnId,
        otp: otp,
        type: verificationType,
        _csrf: CSRF_TOKEN
      };

      if (verificationType === 'aadhaar') {
        payload.mobile = commMobile;
      } else if (verificationType === 'mobile') {
        // Fallback for when ABDM's profile response masks the mobile number
        payload.mobile = sessionStorage.getItem('abdm_input_value') || '';
      }

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      })
        .then(r => r.json())
        .then(data => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check mr-1"></i> Verify OTP';

          if (!data.success) {
            showError('error2', data.error || 'OTP verification failed');
            return;
          }

          // Multiple ABHA accounts
          if (data.needs_select) {
            txnId = data.txnId;
            window.pendingToken = data.t_token;
            renderAccountPicker(data.accounts);
            goToStep('2b');
            return;
          }

          // Success - show result
          showResult(true, data);
        })
        .catch(err => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check mr-1"></i> Verify OTP';
          showError('error2', 'Network error: ' + err.message);
        });
    });

    // ── Account Picker ──
    function renderAccountPicker(accounts) {
      const list = document.getElementById('accountList');
      list.innerHTML = '';

      accounts.forEach(acc => {
        const card = document.createElement('div');
        card.className = 'account-card';
        card.innerHTML = `
          <div>
            <div class="patient-name">${escapeHtml(acc.name || 'Unknown Patient')}</div>
            <div class="abha-number">${escapeHtml(acc.ABHANumber || '')}</div>
            ${acc.preferredAbhaAddress ? `<div style="font-size:.78rem;color:var(--gray-500);">${escapeHtml(acc.preferredAbhaAddress)}</div>` : ''}
          </div>
          <div>
            <button class="btn btn-sm btn-primary-custom" onclick="selectAccount('${acc.ABHANumber}', this)">
              <i class="fa fa-check mr-1"></i> Select
            </button>
          </div>
        `;
        list.appendChild(card);
      });
    }

    function selectAccount(abhaNumber, btn) {
      hideError('error2b');

      const card = btn.closest('.account-card');
      card.style.opacity = '0.6';
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          action: 'select_user',
          txnId: txnId,
          t_token: window.pendingToken,
          abha_number: abhaNumber,
          _csrf: CSRF_TOKEN
        })
      })
        .then(r => r.json())
        .then(data => {
          if (!data.success) {
            showError('error2b', data.error || 'Failed to select account');
            card.style.opacity = '1';
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check mr-1"></i> Select';
            return;
          }

          showResult(true, data);
        })
        .catch(err => {
          showError('error2b', 'Network error: ' + err.message);
          card.style.opacity = '1';
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check mr-1"></i> Select';
        });
    }

    // ── Show Result ──
    function showResult(success, data) {
      goToStep(3);

      const content = document.getElementById('resultContent');
      const badge = document.getElementById('statusBadge');

      if (success) {
        badge.className = 'step-badge completed';
        badge.innerHTML = '<i class="fa fa-check"></i> Verified';

        const patientName = data.profile ?
          `${data.profile.firstName || ''} ${data.profile.lastName || ''}` :
          'Patient';

        const abhaNumber = data.profile?.ABHANumber || data.abha_number || '';
        const abhaAddress = data.profile?.preferredAbhaAddress || '';

        content.innerHTML = `
          <div class="icon-circle success">
            <i class="fa fa-check"></i>
          </div>
          <h5 class="font-weight-bold" style="color:var(--gray-800);">ABHA Verified Successfully!</h5>
          <div class="text-left" style="max-width:400px;margin:16px auto;padding:16px;background:var(--gray-50);border-radius:10px;">
            <div><strong>Patient:</strong> ${escapeHtml(patientName)}</div>
            <div><strong>ABHA Number:</strong> <span style="font-family:monospace;">${escapeHtml(abhaNumber)}</span></div>
            ${abhaAddress ? `<div><strong>ABHA Address:</strong> ${escapeHtml(abhaAddress)}</div>` : ''}
          </div>
          <div class="d-flex justify-content-center" style="gap:10px;">
            <a href="${BASE}doctor/patient-profile.php?id=${data.patient_id || ''}" class="btn btn-primary-custom">
              <i class="fa fa-user mr-1"></i> View Profile
            </a>
            <a href="${BASE}doctor/my-patients.php" class="btn-outline-custom">
              <i class="fa fa-users mr-1"></i> All Patients
            </a>
          </div>
        `;

        // Update step 3 indicator
        document.querySelector('#stepIndicator3 .wc').textContent = '✓';
        document.querySelector('#stepIndicator3 .wl').textContent = 'Complete';
        document.querySelector('#stepIndicator3').className = 'wi done';
      } else {
        badge.className = 'step-badge failed';
        badge.innerHTML = '<i class="fa fa-times"></i> Failed';

        content.innerHTML = `
          <div class="icon-circle error">
            <i class="fa fa-times"></i>
          </div>
          <h5 class="font-weight-bold" style="color:var(--gray-800);">Verification Failed</h5>
          <p class="text-muted" style="font-size:.85rem;">${escapeHtml(data.error || 'An error occurred during verification')}</p>
          <button class="btn-outline-custom" onclick="goToStep(1)">
            <i class="fa fa-arrow-left mr-1"></i> Try Again
          </button>
        `;
      }
    }

    // ── Utility ──
    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // ── Init ──
    // Deep link from a patient profile: ?abha=<digits> → open on the ABHA Number
    // method with the number pre-filled, ready to send OTP.
    (function () {
      const pre = new URLSearchParams(location.search).get('abha');
      const digits = (pre || '').replace(/\D/g, '').slice(0, 14);
      if (digits.length === 14) {
        const numberTab = [...document.querySelectorAll('.method-tab')]
          .find(b => /setMethod\('number'/.test(b.getAttribute('onclick') || ''));
        if (numberTab) {
          setMethod('number', numberTab);
          const input = document.getElementById('mainInput');
          input.value = digits.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4');
          input.focus();
          return;
        }
      }
      // Default: set initial focus on the first (Aadhaar) method
      document.querySelector('.method-tab.active').click();
    })();
  </script>
</body>

</html>