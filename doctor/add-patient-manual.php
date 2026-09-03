<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/util/otp-widget.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
$prefill_mobile = preg_replace('/\D/', '', ($_GET['mobile'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register Patient — Rejuvenate</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <style>
    /* ---- Page header ---- */
    .ap-head {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .ap-head h1 {
      font-size: 1.2rem;
      font-weight: 800;
      color: #1f2937;
      margin: 3px 0 0;
    }

    .ap-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #6b7280;
      font-size: .8rem;
      font-weight: 600;
      text-decoration: none;
    }

    .ap-back:hover {
      color: var(--primary, #0C74C5);
    }

    .ap-head .sub {
      font-size: .76rem;
      color: #9ca3af;
      margin-top: 3px;
    }

    /* ---- Form bits ---- */
    .form-label-sm {
      display: block;
      font-size: .78rem;
      color: #374151;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .ap-hint {
      font-size: .72rem;
      color: #9ca3af;
      margin-top: 4px;
    }

    .ap-otp-box {
      background: #f9fafb;
      border: 1px solid #eef1f4;
      border-radius: 10px;
      padding: 12px 14px;
      margin-top: 4px;
    }

    .ap-otp-box .ap-otp-label {
      font-size: .74rem;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 8px;
    }

    /* ---- ABHA yes/no choice ---- */
    .abha-choice {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    .abha-choice label {
      flex: 1 1 170px;
      display: flex;
      align-items: center;
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      padding: 11px 14px;
      cursor: pointer;
      font-size: .83rem;
      font-weight: 600;
      color: #374151;
      margin: 0;
      transition: .15s;
    }

    .abha-choice input {
      margin-right: 8px;
    }

    .abha-choice label:has(input:checked) {
      border-color: var(--primary, #0C74C5);
      background: #f0f7ff;
      color: var(--primary, #0C74C5);
    }

    #abhaFields {
      display: none;
    }

    /* ---- Side help card ---- */
    .ap-sidecard {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      padding: 18px;
      font-size: .83rem;
    }

    .ap-sidecard h2 {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: #6b7280;
      margin: 0 0 12px;
    }

    .ap-sidecard ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .ap-sidecard li {
      display: flex;
      gap: 9px;
      padding: 8px 0;
      color: #374151;
      line-height: 1.5;
      border-bottom: 1px solid #f3f4f6;
    }

    .ap-sidecard li:last-child {
      border-bottom: none;
    }

    .ap-sidecard li i {
      color: var(--accent, #02c9b8);
      margin-top: 3px;
      flex-shrink: 0;
    }

    /* ---- Sticky action bar ---- */
    .ap-savebar {
      position: sticky;
      bottom: 0;
      z-index: 20;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 12px 16px;
      margin-top: 4px;
      box-shadow: 0 -2px 12px rgba(0, 0, 0, .05);
    }

    @media (max-width: 991.98px) {
      .ap-side {
        margin-top: 18px;
      }
    }
  </style>
</head>

<body>
  <main class="doctor-content">

    <div class="ap-head">
      <div>
        <a href="<?= BASE_URL ?>doctor/add-patient.php" class="ap-back">
          <i class="fa fa-arrow-left"></i> Add Patient
        </a>
        <h1>Register Patient</h1>
        <div class="sub">Mobile verified by WhatsApp OTP · ABHA optional</div>
      </div>
    </div>

    <form id="manualForm">
      <div class="row">

        <div class="col-lg-8">

          <div class="form-section">
            <div class="form-section-title"><i class="fa fa-user me-2" style="color:#e07e18;"></i>Basic Information
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label-sm">First Name <span class="text-danger">*</span></label>
                <input type="text" name="first_name" id="f_fn" class="form-control form-control-sm" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label-sm">Middle Name</label>
                <input type="text" name="middle_name" class="form-control form-control-sm">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label-sm">Last Name</label>
                <input type="text" name="last_name" class="form-control form-control-sm">
              </div>
            </div>
            <div class="row">
              <div class="col-md-5 mb-3">
                <label class="form-label-sm">Mobile <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">+91</span>
                  <input type="text" name="mobile" id="f_mob" class="form-control" placeholder="9876543210"
                    maxlength="10" inputmode="numeric" value="<?= htmlspecialchars($prefill_mobile) ?>" required>
                </div>
                <div class="ap-hint">10-digit number — the OTP is sent here on WhatsApp.</div>
              </div>
              <div class="col-md-7 mb-3">
                <label class="form-label-sm">Email</label>
                <input type="email" name="email" id="f_email" class="form-control form-control-sm">
                <div class="form-check mt-1">
                  <input type="checkbox" class="form-check-input" id="noEmail">
                  <label for="noEmail" class="form-check-label" style="font-size:.73rem;color:#6b7280;">No email</label>
                </div>
              </div>
              <div class="col-12 mb-2">
                <div class="ap-otp-box">
                  <div class="ap-otp-label"><i class="fa fa-whatsapp me-1" style="color:#25d366;"></i>Verify patient's
                    mobile</div>
                  <div class="ap-hint" style="margin-top:0;margin-bottom:6px;">
                    Send a code to the patient's WhatsApp &amp; email, then enter what they read back.
                  </div>
                  <?php render_otp_widget([
                    'role' => 'patient',
                    'mobile_field' => 'mobile',
                    'email_field' => 'email',
                    'name_field' => 'first_name',
                    'submit_selector' => '#btnCreate',
                    'allow_existing' => true,
                    'send_url' => BASE_URL . 'doctor/api/patient-otp-send.php',
                    'verify_url' => BASE_URL . 'doctor/api/patient-otp-verify.php',
                  ]); ?>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-6 col-md-3 mb-3">
                <label class="form-label-sm">Gender</label>
                <select name="gender" class="form-control form-control-sm">
                  <option value="">--</option>
                  <option>Male</option>
                  <option>Female</option>
                  <option>Other</option>
                </select>
              </div>
              <div class="col-6 col-md-3 mb-3">
                <label class="form-label-sm">Date of Birth</label>
                <input type="text" name="dob" id="f_dob" class="form-control form-control-sm" placeholder="dd/mm/yyyy"
                  maxlength="10">
              </div>
              <div class="col-6 col-md-2 mb-3">
                <label class="form-label-sm">Age</label>
                <input type="number" id="f_age" class="form-control form-control-sm" readonly
                  style="background:#f9fafb;">
              </div>
              <div class="col-6 col-md-4 mb-3">
                <label class="form-label-sm">Blood Group</label>
                <select name="blood_group" class="form-control form-control-sm">
                  <option value="">--</option>
                  <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                    <option><?= $bg ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="fa fa-id-card me-2" style="color:#0C74C5;"></i>ABHA Health ID
            </div>
            <div class="abha-choice">
              <label><input type="radio" name="has_abha" value="no" checked><span>No ABHA yet</span></label>
              <label><input type="radio" name="has_abha" value="yes"><span>Patient has an ABHA</span></label>
            </div>
            <div id="abhaFields" class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label-sm">ABHA Number</label>
                <input type="text" name="abha_number" id="f_abha_num" class="form-control form-control-sm"
                  placeholder="XX-XXXX-XXXX-XXXX" maxlength="17" autocomplete="off">
                <div class="ap-hint" id="abhaNumHint">14 digits, format 12-3456-7890-1234</div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label-sm">ABHA Address <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="abha_address" id="f_abha_addr" class="form-control form-control-sm"
                  placeholder="name@abdm" autocomplete="off">
              </div>
              <div class="col-12">
                <div class="ap-hint">
                  <i class="fa fa-info-circle"></i> Recorded as provided. Live ABDM verification will run automatically
                  once the ABHA integration is enabled.
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="fa fa-map-marker me-2" style="color:#e07e18;"></i>Address <span
                class="text-muted fw-normal" style="font-size:.72rem;">(optional)</span></div>
            <div class="row">
              <div class="col-6 col-md-4 mb-3"><label class="form-label-sm">Pin Code</label>
                <input type="text" name="pincode" class="form-control form-control-sm" maxlength="6" inputmode="numeric">
              </div>
              <div class="col-6 col-md-4 mb-3"><label class="form-label-sm">City</label>
                <input type="text" name="city" class="form-control form-control-sm">
              </div>
              <div class="col-12 col-md-4 mb-3"><label class="form-label-sm">State</label>
                <input type="text" name="state" class="form-control form-control-sm">
              </div>
            </div>
            <div class="mb-1">
              <label class="form-label-sm">Full Address</label>
              <textarea name="address" class="form-control form-control-sm" rows="2"></textarea>
            </div>
          </div>

        </div>

        <div class="col-lg-4 ap-side">
          <div class="ap-sidecard">
            <h2><i class="fa fa-info-circle me-1" style="color:#0C74C5;"></i>How registration works</h2>
            <ul>
              <li><i class="fa fa-check-circle"></i><span>Fill the patient's details and enter their 10-digit
                  mobile.</span></li>
              <li><i class="fa fa-check-circle"></i><span>Send an OTP — it reaches the patient on WhatsApp and email.
                  They read the code back to you.</span></li>
              <li><i class="fa fa-check-circle"></i><span><strong>Has ABHA:</strong> add the 14-digit number now; it's
                  verified with ABDM later.</span></li>
              <li><i class="fa fa-check-circle"></i><span><strong>No ABHA:</strong> just register — ABHA can be linked
                  any time.</span></li>
              <li><i class="fa fa-shield"></i><span>Consent for this record is logged for ABDM compliance.</span></li>
            </ul>
          </div>
        </div>

      </div>

      <div class="row">
        <div class="col-lg-8">
          <div id="errForm" class="alert alert-danger"
            style="display:none;border-radius:8px;font-size:.84rem;"></div>
          <div class="ap-savebar">
            <button type="submit" class="btn btn-primary btn-sm fw-bold" id="btnCreate">
              <i class="fa fa-user-plus me-1"></i> Create Patient
            </button>
            <a href="<?= BASE_URL ?>doctor/add-patient.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <span class="ap-hint" style="margin:0 0 0 auto;">Patient gets portal access via OTP login.</span>
          </div>
        </div>
      </div>

    </form>

  </main>

  <script>
    const BASE = '<?= BASE_URL ?>';

    document.getElementById('noEmail').addEventListener('change', function () {
      document.getElementById('f_email').disabled = this.checked;
      if (this.checked) document.getElementById('f_email').value = '';
    });

    // ABHA yes/no toggle
    document.querySelectorAll('input[name=has_abha]').forEach(function (r) {
      r.addEventListener('change', function () {
        document.getElementById('abhaFields').style.display = (this.value === 'yes') ? 'flex' : 'none';
      });
    });

    // Auto-format ABHA number as XX-XXXX-XXXX-XXXX
    document.getElementById('f_abha_num').addEventListener('input', function () {
      const d = this.value.replace(/\D/g, '').slice(0, 14);
      this.value = d.replace(/(\d{2})(\d{0,4})(\d{0,4})(\d{0,4})/, function (_, a, b, c, e) {
        return [a, b, c, e].filter(Boolean).join('-');
      });
      document.getElementById('abhaNumHint').textContent =
        (d.length && d.length !== 14) ? d.length + ' / 14 digits' : '14 digits, format 12-3456-7890-1234';
    });

    document.getElementById('f_dob').addEventListener('input', function () {
      const p = this.value.split('/'); if (p.length !== 3 || p[2].length < 4) return;
      const d = new Date(p[2], p[1] - 1, p[0]); if (isNaN(d)) return;
      let age = new Date().getFullYear() - d.getFullYear();
      const t = new Date();
      if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) age--;
      if (age >= 0 && age < 150) document.getElementById('f_age').value = age;
    });

    document.getElementById('manualForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = document.getElementById('btnCreate');
      btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Creating…';
      const fd = new FormData(this);
      const payload = {};
      fd.forEach((v, k) => payload[k] = v);
      payload.no_email = document.getElementById('noEmail').checked;

      const hasAbha = document.querySelector('input[name=has_abha]:checked').value === 'yes';
      let abhaNum = '';
      if (hasAbha) {
        const digits = document.getElementById('f_abha_num').value.replace(/\D/g, '');
        if (digits.length !== 14) {
          btn.disabled = false; btn.innerHTML = '<i class="fa fa-user-plus me-1"></i> Create Patient';
          const el = document.getElementById('errForm'); el.style.display = 'block';
          el.textContent = 'Enter a valid 14-digit ABHA number, or choose "No ABHA yet".';
          return;
        }
        abhaNum = digits.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4');
      }
      payload.abha_number = abhaNum;
      payload.abha_address = hasAbha ? document.getElementById('f_abha_addr').value.trim() : '';
      payload.abha_verified = 0;

      fetch(BASE + 'doctor/api/create-patient-submit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(r => r.json()).then(data => {
          if (data.success) {
            const n = data.notify || {};
            const welcomed = (n.whatsapp || n.email) ? '&welcomed=1' : '';
            window.location = BASE + 'doctor/patient-profile.php?id=' + data.patient_id + '&new=1' + welcomed;
          } else {
            btn.disabled = false; btn.innerHTML = '<i class="fa fa-user-plus me-1"></i> Create Patient';
            const e = document.getElementById('errForm'); e.style.display = 'block'; e.textContent = data.error || 'Failed';
          }
        }).catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fa fa-user-plus me-1"></i> Create Patient'; });
    });
  </script>
</body>

</html>
