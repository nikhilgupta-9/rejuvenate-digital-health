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
  <title>Create New ABHA — Rejuvenate</title>
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
        <h5 class="mb-0 font-weight-bold mt-1" style="color:var(--gray-800);">Create New ABHA for Patient</h5>
        <div style="font-size:.74rem;color:var(--gray-400);">Verify Aadhaar via OTP — ABDM creates a new ABHA number
          automatically, or returns the existing one if this Aadhaar is already registered</div>
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
          <span class="wl">Aadhaar</span>
        </div>
        <div class="wi" id="stepIndicator2">
          <div class="wc">2</div>
          <span class="wl">OTP</span>
        </div>
        <div class="wi" id="stepIndicator3">
          <div class="wc">3</div>
          <span class="wl">Complete</span>
        </div>
      </div>

      <!-- Step 1: Aadhaar -->
      <div class="step-content active" id="step1">
        <div class="cp-card highlight">
          <div class="cp-title"><i class="fa fa-id-card mr-2" style="color:var(--primary);"></i>Patient's Aadhaar
            Number</div>

          <div class="alert alert-info alert-custom mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
            <i class="fa fa-lock mr-1"></i>
            Aadhaar is RSA-encrypted before sending to ABDM. <strong>Never stored</strong> in our database.
          </div>

          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.85rem;color:var(--gray-700);">
              Aadhaar Number <span style="color:var(--danger);">*</span>
            </label>
            <div class="input-group input-group-custom">
              <input type="password" id="aadhaarInput" class="form-control" placeholder="•••• •••• ••••"
                maxlength="12" autocomplete="off" inputmode="numeric">
            </div>
            <div class="form-hint">12-digit Aadhaar — OTP will be sent to the Aadhaar-linked mobile number</div>
          </div>

          <!-- Patient Consent (ABDM: required before Aadhaar-based ABHA enrolment) -->
          <div class="mb-3 p-3" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;max-height:110px;overflow-y:auto;font-size:.75rem;color:#374151;line-height:1.6;">
            <strong>Patient Consent</strong><br>
            The patient hereby declares that they are voluntarily sharing their Aadhaar number and demographic
            information issued by UIDAI, with the National Health Authority (NHA), for the sole purpose of creating
            an ABHA number. The patient understands their ABHA number may be used and shared for purposes notified
            by ABDM from time to time, including provision of healthcare services, and that personal identifiable
            information (Name, Address, Age, DOB, Gender, Photograph) may be made available to the treating
            healthcare professional.
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="aadhaar_consent" value="1">
            <label class="form-check-label font-weight-bold" for="aadhaar_consent" style="font-size:.82rem;">
              I confirm the patient has read and agreed to the above consent
            </label>
          </div>

          <button class="btn btn-primary-custom" id="btnSend">
            <i class="fa fa-paper-plane mr-1"></i> Send OTP
          </button>

          <div id="error1" class="alert alert-danger alert-custom mt-3" style="display:none;"></div>
        </div>
      </div>

      <!-- Step 2: OTP Entry -->
      <div class="step-content" id="step2">
        <div class="cp-card highlight">
          <div class="cp-title"><i class="fa fa-mobile mr-2" style="color:var(--primary);"></i>Enter Verification Code
          </div>

          <div class="text-center mb-3">
            <p class="text-muted" id="sentMsg" style="font-size:.86rem;margin:0;">
              <i class="fa fa-check-circle" style="color:var(--success);"></i> OTP sent to Aadhaar-registered mobile
            </p>
            <div style="font-size:.72rem;color:var(--gray-400);">Valid for 5 minutes</div>
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

          <div class="form-group">
            <label class="form-label" style="font-size:.85rem;font-weight:600;">Mobile Number for ABHA Communication
              <span style="color:var(--danger);">*</span></label>
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
              <i class="fa fa-check mr-1"></i> Verify &amp; Create ABHA
            </button>
          </div>
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
            <p class="text-muted" style="font-size:.85rem;">Please wait while ABDM processes this Aadhaar</p>
          </div>
        </div>
      </div>

    <?php endif; ?>
  </main>

  <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    let currentStep = 1;
    let txnId = '';
    let timerInterval = null;
    let timerSeconds = 300;

    const stepContent = {
      1: document.getElementById('step1'),
      2: document.getElementById('step2'),
      3: document.getElementById('step3')
    };

    const stepIndicators = {
      1: document.getElementById('stepIndicator1'),
      2: document.getElementById('stepIndicator2'),
      3: document.getElementById('stepIndicator3')
    };

    function goToStep(step) {
      Object.values(stepContent).forEach(el => el.classList.remove('active'));
      stepContent[step].classList.add('active');

      const steps = [1, 2, 3];
      steps.forEach(s => {
        const indicator = stepIndicators[s];
        indicator.className = 'wi';
        if (s < step) indicator.classList.add('done');
        else if (s === step) indicator.classList.add('active');
      });

      currentStep = step;

      const badge = document.getElementById('statusBadge');
      if (step === 1) {
        badge.className = 'step-badge pending';
        badge.innerHTML = '<i class="fa fa-clock-o"></i> Pending';
      } else if (step === 2) {
        badge.className = 'step-badge pending';
        badge.innerHTML = '<i class="fa fa-clock-o"></i> Verifying';
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
        if (data.length > 0) all[nextIndex].focus();
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
      const value = document.getElementById('aadhaarInput').value.trim();
      const clean = value.replace(/\D/g, '');

      if (clean.length !== 12) {
        showError('error1', 'Please enter a valid 12-digit Aadhaar number');
        return;
      }
      if (!document.getElementById('aadhaar_consent').checked) {
        showError('error1', 'Please confirm patient consent to continue');
        return;
      }

      hideError('error1');

      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Sending...';

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_otp', abha_input: value, type: 'aadhaar', consent: 1, _csrf: CSRF_TOKEN })
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
          sessionStorage.setItem('abdm_newabha_txnId', data.txnId);

          document.getElementById('sentMsg').innerHTML = `
            <i class="fa fa-check-circle" style="color:var(--success);"></i>
            ${data.message || 'OTP sent successfully'}
          `;

          document.querySelectorAll('.otp-box').forEach(el => {
            el.value = '';
            el.classList.remove('filled');
          });

          goToStep(2);
          startTimer(300);
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

    // ── Verify & Create ──
    document.getElementById('btnVerify').addEventListener('click', function () {
      const otp = getOTP();

      if (otp.length < 6) {
        showError('error2', 'Please enter the complete 6-digit OTP');
        return;
      }

      const commMobile = document.getElementById('commMobileInput').value.replace(/\D/g, '');
      if (commMobile.length !== 10) {
        showError('error2', 'Please enter a valid 10-digit mobile number for ABHA communication');
        return;
      }

      hideError('error2');

      if (!txnId) {
        txnId = sessionStorage.getItem('abdm_newabha_txnId') || '';
        if (!txnId) {
          showError('error2', 'Session expired — please resend OTP');
          return;
        }
      }

      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Verifying...';

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'verify_otp', txnId: txnId, otp: otp, type: 'aadhaar', mobile: commMobile, _csrf: CSRF_TOKEN })
      })
        .then(r => r.json())
        .then(data => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check mr-1"></i> Verify &amp; Create ABHA';

          if (!data.success) {
            showError('error2', data.error || 'OTP verification failed');
            return;
          }

          showResult(true, data);
        })
        .catch(err => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check mr-1"></i> Verify &amp; Create ABHA';
          showError('error2', 'Network error: ' + err.message);
        });
    });

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
        const isNew = data.is_new !== false;

        content.innerHTML = `
          <div class="icon-circle success">
            <i class="fa fa-check"></i>
          </div>
          <h5 class="font-weight-bold" style="color:var(--gray-800);">
            ${isNew ? 'New ABHA Created Successfully!' : 'ABHA Already Existed — Linked Successfully!'}
          </h5>
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

        document.querySelector('#stepIndicator3 .wc').textContent = '✓';
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

    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  </script>
</body>

</html>
