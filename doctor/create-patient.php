<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';

$payload   = doctor_jwt_guard();
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Patient — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
/* page styles */
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:22px;}
.cp-card-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}
.mode-tabs{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:22px;}
.mode-tab{padding:10px 22px;font-size:.84rem;font-weight:600;color:#9ca3af;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:.15s;}
.mode-tab.active{color:#0C74C5;border-bottom-color:#0C74C5;}
.mode-tab i{margin-right:7px;}
.tab-pane-inner{display:none;}
.tab-pane-inner.active{display:block;}

/* 3-step wizard */
.step-wizard{display:flex;align-items:flex-start;gap:0;margin-bottom:24px;position:relative;}
.step-wizard::before{content:'';position:absolute;top:18px;left:18px;right:18px;height:2px;background:#e5e7eb;z-index:0;}
.step-item{flex:1;text-align:center;position:relative;z-index:1;}
.step-circle{width:36px;height:36px;border-radius:50%;border:2px solid #e5e7eb;background:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#9ca3af;
  transition:.25s;}
.step-item.active .step-circle{border-color:#0C74C5;background:#0C74C5;color:#fff;}
.step-item.done .step-circle{border-color:#16a34a;background:#16a34a;color:#fff;}
.step-label{display:block;font-size:.7rem;color:#9ca3af;margin-top:5px;font-weight:600;}
.step-item.active .step-label{color:#0C74C5;}
.step-item.done .step-label{color:#16a34a;}

.step-body{display:none;}
.step-body.active{display:block;}

/* ABHA search input */
.abha-type-switch{display:flex;gap:8px;margin-bottom:10px;}
.type-btn{padding:5px 14px;border-radius:20px;border:1px solid #d1d5db;background:#f9fafb;font-size:.78rem;font-weight:600;cursor:pointer;transition:.15s;color:#6b7280;}
.type-btn.active{background:#0C74C5;color:#fff;border-color:#0C74C5;}

/* Profile preview card */
.profile-preview{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:18px 20px;}
.profile-preview .avatar{width:60px;height:60px;border-radius:50%;background:#0C74C5;color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;flex-shrink:0;overflow:hidden;}
.profile-preview .avatar img{width:100%;height:100%;object-fit:cover;}

/* OTP input */
.otp-input-row{display:flex;gap:8px;justify-content:center;margin:16px 0;}
.otp-input-row input{width:48px;height:52px;border-radius:10px;border:2px solid #e5e7eb;text-align:center;
  font-size:1.3rem;font-weight:700;color:#1f2937;transition:.15s;}
.otp-input-row input:focus{border-color:#0C74C5;outline:none;box-shadow:0 0 0 3px rgba(12,116,197,.12);}

/* success card */
.success-card{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:28px;text-align:center;}

/* form helpers */
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;}
.photo-circle{width:80px;height:80px;border-radius:50%;border:2px dashed #d1d5db;display:flex;flex-direction:column;
  align-items:center;justify-content:center;cursor:pointer;background:#f9fafb;overflow:hidden;transition:.2s;}
.photo-circle:hover{border-color:#0C74C5;}
.photo-circle img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
</style>
</head>
<body>
<main class="doctor-content">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Add Patient</h5>
      <div style="font-size:.75rem;color:#9ca3af;">Link via ABHA (recommended) or create manually</div>
    </div>
    <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-sm btn-outline-secondary">
      <i class="fa fa-arrow-left mr-1"></i> Back
    </a>
  </div>

  <div class="row">
    <div class="col-lg-8">

      <!-- Mode Tabs -->
      <div class="mode-tabs">
        <div class="mode-tab active" data-mode="abha"><i class="fa fa-id-card-o"></i>Link via ABHA</div>
        <div class="mode-tab" data-mode="manual"><i class="fa fa-user-plus"></i>Create Manually</div>
      </div>

      <!-- ══════════════════════════════════════════
           MODE A — ABHA LINK (3-step wizard)
           ══════════════════════════════════════════ -->
      <div class="tab-pane-inner active" id="mode-abha">

        <?php if (!ABDM_CONFIGURED): ?>
        <div class="alert alert-warning" style="border-radius:10px;font-size:.84rem;">
          <i class="fa fa-exclamation-triangle mr-2"></i>
          <strong>ABDM not configured.</strong> Add <code>ABDM_CLIENT_ID</code> and
          <code>ABDM_CLIENT_SECRET</code> to your <code>.env</code> file.
          Use <strong>Create Manually</strong> tab for now.
        </div>
        <?php else: ?>

        <!-- Step indicator -->
        <div class="step-wizard" id="stepWizard">
          <div class="step-item active" id="si1">
            <div class="step-circle">1</div>
            <span class="step-label">Enter ABHA</span>
          </div>
          <div class="step-item" id="si2">
            <div class="step-circle">2</div>
            <span class="step-label">Verify OTP</span>
          </div>
          <div class="step-item" id="si3">
            <div class="step-circle">3</div>
            <span class="step-label">Confirm</span>
          </div>
        </div>

        <!-- Step 1: Enter ABHA -->
        <div class="step-body active" id="step1">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-search mr-2" style="color:#0C74C5;"></i>Enter Patient's ABHA</div>

            <div class="abha-type-switch">
              <button class="type-btn active" data-stype="number">ABHA Number</button>
              <button class="type-btn" data-stype="address">ABHA Address</button>
            </div>

            <div class="form-group">
              <label class="form-label-sm" id="abhaInputLabel">ABHA Number</label>
              <input type="text" id="abhaInputField" class="form-control"
                     placeholder="XX-XXXX-XXXX-XXXX" maxlength="17"
                     style="font-family:monospace;font-size:1rem;letter-spacing:1px;">
              <small class="text-muted" id="abhaInputHint">14-digit ABHA number (format: XX-XXXX-XXXX-XXXX)</small>
            </div>

            <div class="d-flex" style="gap:10px;">
              <button class="btn btn-primary" id="btnSendOtp" style="min-width:140px;">
                <i class="fa fa-paper-plane mr-1"></i> Send OTP
              </button>
              <div style="font-size:.78rem;color:#6b7280;align-self:center;">
                OTP will be sent to patient's ABHA-registered mobile
              </div>
            </div>

            <div id="step1Error" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
          </div>
        </div>

        <!-- Step 2: OTP Verification -->
        <div class="step-body" id="step2">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-mobile mr-2" style="color:#0C74C5;"></i>Verify OTP</div>

            <div class="text-center mb-3">
              <div id="otpSentMsg" style="font-size:.88rem;color:#374151;margin-bottom:6px;"></div>
              <div style="font-size:.76rem;color:#9ca3af;">Ask the patient to share the OTP received on their registered mobile</div>
            </div>

            <!-- 6-box OTP input -->
            <div class="otp-input-row">
              <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
              <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
              <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
              <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
              <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
              <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2">
              <button class="btn btn-link btn-sm p-0" id="btnResendOtp" style="font-size:.78rem;">
                <i class="fa fa-refresh mr-1"></i> Resend OTP
              </button>
              <span id="otpTimer" style="font-size:.76rem;color:#9ca3af;"></span>
            </div>

            <div id="step2Error" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>

            <div class="d-flex mt-3" style="gap:10px;">
              <button class="btn btn-outline-secondary" onclick="goStep(1)"><i class="fa fa-arrow-left mr-1"></i> Back</button>
              <button class="btn btn-primary" id="btnVerifyOtp" style="min-width:130px;">
                <i class="fa fa-check mr-1"></i> Verify OTP
              </button>
            </div>
          </div>
        </div>

        <!-- Step 3: Confirm & Save -->
        <div class="step-body" id="step3">
          <div id="step3Loading" class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x" style="color:#0C74C5;"></i>
            <div style="margin-top:10px;color:#6b7280;font-size:.88rem;">Fetching profile from ABDM…</div>
          </div>
          <div id="step3Content" style="display:none;"></div>
        </div>

        <?php endif; ?>
      </div><!-- /mode-abha -->

      <!-- ══════════════════════════════════════════
           MODE B — MANUAL
           ══════════════════════════════════════════ -->
      <div class="tab-pane-inner" id="mode-manual">
        <form id="manualForm">
          <!-- Photo + ABHA -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-user-circle-o mr-2" style="color:#0C74C5;"></i>Patient Identity</div>
            <div class="d-flex" style="gap:20px;flex-wrap:wrap;align-items:flex-start;">
              <div>
                <label class="form-label-sm d-block">Photo</label>
                <div class="photo-circle" id="photoCircle" onclick="document.getElementById('photoInput').click()">
                  <i class="fa fa-camera" style="font-size:1.2rem;color:#9ca3af;"></i>
                  <span style="font-size:.6rem;color:#9ca3af;margin-top:2px;">Upload</span>
                </div>
                <input type="file" id="photoInput" accept="image/*" style="display:none;">
              </div>
              <div style="flex:1;min-width:240px;">
                <div class="row">
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label class="form-label-sm">ABHA Address <small class="text-muted">(optional)</small></label>
                      <div class="input-group input-group-sm">
                        <input type="text" name="abha_address" id="f_abha_address" class="form-control" placeholder="name">
                        <div class="input-group-append"><span class="input-group-text">@abdm</span></div>
                      </div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label class="form-label-sm">ABHA Number <small class="text-muted">(optional)</small></label>
                      <input type="text" name="abha_number" id="f_abha_number" class="form-control form-control-sm"
                             placeholder="XX-XXXX-XXXX-XXXX" maxlength="17">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Personal Info -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-user mr-2" style="color:#0C74C5;"></i>Personal Information</div>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">First Name <span class="text-danger">*</span></label>
                  <input type="text" name="first_name" id="f_first_name" class="form-control form-control-sm" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">Middle Name</label>
                  <input type="text" name="middle_name" id="f_middle_name" class="form-control form-control-sm">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">Last Name</label>
                  <input type="text" name="last_name" id="f_last_name" class="form-control form-control-sm">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label-sm">Email</label>
                  <input type="email" name="email" id="f_email" class="form-control form-control-sm">
                  <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="noEmailChk">
                    <label class="form-check-label" for="noEmailChk" style="font-size:.76rem;color:#6b7280;">No email address</label>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Mobile <span class="text-danger">*</span></label>
                  <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
                    <input type="text" name="mobile" id="f_mobile" class="form-control" placeholder="9876543210" maxlength="10" required>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Blood Group</label>
                  <select name="blood_group" id="f_blood_group" class="form-control form-control-sm">
                    <option value="">--</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                    <option><?= $bg ?></option><?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Gender</label>
                  <select name="gender" id="f_gender" class="form-control form-control-sm">
                    <option value="">--</option>
                    <option>Male</option><option>Female</option><option>Other</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Date of Birth</label>
                  <input type="text" name="dob" id="f_dob" class="form-control form-control-sm" placeholder="dd/mm/yyyy" maxlength="10">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="form-label-sm">Age</label>
                  <input type="number" id="f_age" class="form-control form-control-sm" readonly style="background:#f9fafb;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">Aadhaar Last 4 <small class="text-muted">(optional)</small></label>
                  <input type="text" name="aadhaar_last4" class="form-control form-control-sm" placeholder="XXXX" maxlength="4">
                </div>
              </div>
            </div>
          </div>

          <!-- Address -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-map-marker mr-2" style="color:#0C74C5;"></i>Address</div>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">Pin Code</label>
                  <input type="text" name="pincode" id="f_pincode" class="form-control form-control-sm" maxlength="6">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">City</label>
                  <input type="text" name="city" id="f_city" class="form-control form-control-sm">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">State</label>
                  <input type="text" name="state" id="f_state" class="form-control form-control-sm">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label-sm">Full Address</label>
              <textarea name="address" class="form-control form-control-sm" rows="2"></textarea>
            </div>
          </div>

          <!-- Other -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-info-circle mr-2" style="color:#0C74C5;"></i>Other</div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label-sm">Reference Doctor</label>
                  <input type="text" name="reference_doctor" class="form-control form-control-sm">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label-sm">Unique ID</label>
                  <input type="text" id="f_uid" class="form-control form-control-sm" readonly style="background:#f9fafb;font-family:monospace;">
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end" style="gap:10px;">
            <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" id="btnManualCreate">
              <i class="fa fa-user-plus mr-1"></i> Create Patient
            </button>
          </div>
        </form>
      </div><!-- /mode-manual -->

    </div><!-- col-lg-8 -->

    <!-- Right Info Panel -->
    <div class="col-lg-4">
      <div class="cp-card" style="position:sticky;top:80px;">
        <div class="cp-card-title"><i class="fa fa-info-circle mr-2" style="color:#0C74C5;"></i>How it Works</div>
        <div style="font-size:.82rem;color:#374151;">

          <div class="mb-3">
            <div style="font-weight:700;color:#0C74C5;margin-bottom:6px;"><i class="fa fa-id-card-o mr-1"></i> Via ABHA (3 steps)</div>
            <div class="d-flex align-items-start mb-2" style="gap:10px;">
              <span style="background:#0C74C5;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;">1</span>
              <div style="font-size:.78rem;color:#6b7280;">Enter patient's ABHA number or address</div>
            </div>
            <div class="d-flex align-items-start mb-2" style="gap:10px;">
              <span style="background:#0C74C5;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;">2</span>
              <div style="font-size:.78rem;color:#6b7280;">OTP sent to patient's Aadhaar-registered mobile — patient shares it</div>
            </div>
            <div class="d-flex align-items-start mb-2" style="gap:10px;">
              <span style="background:#0C74C5;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;">3</span>
              <div style="font-size:.78rem;color:#6b7280;">Full profile fetched from ABDM and saved to portal</div>
            </div>
          </div>

          <div class="mb-3">
            <div style="font-weight:700;color:#0C74C5;margin-bottom:6px;"><i class="fa fa-user-plus mr-1"></i> Without ABHA</div>
            <div style="font-size:.78rem;color:#6b7280;">
              Use "Create Manually" tab. Fill basic details — name, mobile, gender, DOB.
              Patient can link ABHA later from their portal.
            </div>
          </div>

          <div class="mb-2">
            <div style="font-weight:700;color:#0C74C5;margin-bottom:6px;"><i class="fa fa-refresh mr-1"></i> Existing Patient</div>
            <div style="font-size:.78rem;color:#6b7280;">
              If mobile/email already registered, the ABHA link flow will update their existing
              record with ABHA data instead of creating a duplicate.
            </div>
          </div>

          <hr>
          <div style="font-size:.72rem;color:#9ca3af;">
            <i class="fa fa-lock mr-1"></i> All ABDM calls are logged per NHA audit guidelines. Aadhaar is never stored.
          </div>
        </div>

        <div class="mt-3 p-3" style="background:#eff6ff;border-radius:10px;">
          <div style="font-size:.7rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;margin-bottom:4px;">ABHA Number</div>
          <div style="font-family:monospace;color:#1d4ed8;">XX-XXXX-XXXX-XXXX</div>
        </div>
        <div class="mt-2 p-3" style="background:#f0fdf4;border-radius:10px;">
          <div style="font-size:.7rem;font-weight:700;color:#15803d;text-transform:uppercase;margin-bottom:4px;">ABHA Address</div>
          <div style="font-family:monospace;color:#15803d;">name@abdm</div>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const BASE = '<?= BASE_URL ?>';
  let currentTxnId   = '';
  let currentAbhaInput = '';
  let currentAbhaType  = 'number';
  let otpTimerInterval = null;

  /* ── Mode tabs ── */
  document.querySelectorAll('.mode-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-pane-inner').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('mode-'+tab.dataset.mode).classList.add('active');
    });
  });

  /* ── ABHA type toggle ── */
  var searchType = 'number';
  document.querySelectorAll('.type-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      searchType = btn.dataset.stype;
      const inp   = document.getElementById('abhaInputField');
      const label = document.getElementById('abhaInputLabel');
      const hint  = document.getElementById('abhaInputHint');
      if(searchType === 'number'){
        label.textContent = 'ABHA Number';
        inp.placeholder   = 'XX-XXXX-XXXX-XXXX';
        inp.maxLength     = 17;
        hint.textContent  = '14-digit ABHA number';
      } else {
        label.textContent = 'ABHA Address';
        inp.placeholder   = 'name@abdm';
        inp.maxLength     = 60;
        hint.textContent  = 'ABHA address handle (e.g. john.doe@abdm)';
      }
      inp.value = '';
    });
  });

  /* ── ABHA number auto-format ── */
  document.getElementById('abhaInputField').addEventListener('input', function(){
    if(searchType !== 'number') return;
    let d = this.value.replace(/\D/g,'').substr(0,14);
    let out = d;
    if(d.length > 2)  out = d.substr(0,2)+'-'+d.substr(2);
    if(d.length > 6)  out = d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6);
    if(d.length > 10) out = d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6,4)+'-'+d.substr(10);
    this.value = out;
  });

  /* ── Step navigation ── */
  window.goStep = function(n){
    document.querySelectorAll('.step-body').forEach(s => s.classList.remove('active'));
    document.getElementById('step'+n).classList.add('active');
    for(let i=1;i<=3;i++){
      const si = document.getElementById('si'+i);
      si.classList.remove('active','done');
      if(i < n) si.classList.add('done');
      if(i === n) si.classList.add('active');
    }
    document.getElementById('si'+n).querySelector('.step-circle').innerHTML =
      n > 1 ? n : n;
    // fix done circle icons
    for(let i=1;i<n;i++){
      document.getElementById('si'+i).querySelector('.step-circle').innerHTML = '<i class="fa fa-check"></i>';
    }
  };

  /* ── Step 1: Send OTP ── */
  document.getElementById('btnSendOtp').addEventListener('click', sendOtp);

  function sendOtp(isResend){
    const inp = document.getElementById('abhaInputField');
    const val = inp.value.trim();
    if(!val){ showErr('step1Error','Please enter an ABHA '+(searchType==='number'?'number':'address')); return; }
    hideErr('step1Error');

    const btn = document.getElementById(isResend ? 'btnResendOtp' : 'btnSendOtp');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';

    currentAbhaInput = val;
    currentAbhaType  = searchType;

    fetch(BASE+'doctor/api/abha-otp-send.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({abha_input: val, type: searchType})
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = orig;
      if(!data.success){ showErr('step1Error', data.error || 'Failed to send OTP'); return; }
      currentTxnId = data.txnId;
      document.getElementById('otpSentMsg').innerHTML =
        '<i class="fa fa-check-circle" style="color:#16a34a;margin-right:6px;"></i>'
        +'OTP sent — <strong>'+escHtml(data.message)+'</strong>';
      clearOtpInputs();
      goStep(2);
      startOtpTimer(300);
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = orig;
      showErr('step1Error','Network error: '+err.message);
    });
  }

  /* ── OTP digit inputs ── */
  const otpDigits = document.querySelectorAll('.otp-digit');
  otpDigits.forEach(function(inp, idx){
    inp.addEventListener('input', function(){
      this.value = this.value.replace(/\D/g,'');
      if(this.value && idx < otpDigits.length - 1) otpDigits[idx+1].focus();
      if(getOtp().length === 6) document.getElementById('btnVerifyOtp').focus();
    });
    inp.addEventListener('keydown', function(e){
      if(e.key==='Backspace' && !this.value && idx > 0) otpDigits[idx-1].focus();
    });
    inp.addEventListener('paste', function(e){
      const pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
      if(pasted.length >= 6){
        e.preventDefault();
        pasted.substr(0,6).split('').forEach((d,i) => { if(otpDigits[i]) otpDigits[i].value = d; });
        otpDigits[Math.min(5, pasted.length-1)].focus();
      }
    });
  });

  function getOtp(){ return Array.from(otpDigits).map(i=>i.value).join(''); }
  function clearOtpInputs(){ otpDigits.forEach(i=>{ i.value=''; }); otpDigits[0].focus(); }

  /* ── OTP timer ── */
  function startOtpTimer(seconds){
    clearInterval(otpTimerInterval);
    document.getElementById('btnResendOtp').disabled = true;
    const el = document.getElementById('otpTimer');
    let remaining = seconds;
    function tick(){
      const m = String(Math.floor(remaining/60)).padStart(2,'0');
      const s = String(remaining%60).padStart(2,'0');
      el.textContent = 'Resend in '+m+':'+s;
      if(--remaining < 0){
        clearInterval(otpTimerInterval);
        el.textContent = '';
        document.getElementById('btnResendOtp').disabled = false;
      }
    }
    tick();
    otpTimerInterval = setInterval(tick, 1000);
  }

  document.getElementById('btnResendOtp').addEventListener('click', function(){
    goStep(1);
    setTimeout(function(){ sendOtp(true); }, 100);
  });

  /* ── Step 2: Verify OTP ── */
  document.getElementById('btnVerifyOtp').addEventListener('click', function(){
    const otp = getOtp();
    if(otp.length < 6){ showErr('step2Error','Please enter the complete 6-digit OTP'); return; }
    if(!currentTxnId){ showErr('step2Error','Session expired — please resend OTP'); return; }
    hideErr('step2Error');

    this.disabled = true;
    this.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
    const btn = this;

    goStep(3);

    fetch(BASE+'doctor/api/abha-otp-verify.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({txnId: currentTxnId, otp: otp, abha_input: currentAbhaInput, type: currentAbhaType})
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check mr-1"></i> Verify OTP';
      clearInterval(otpTimerInterval);

      document.getElementById('step3Loading').style.display = 'none';
      const content = document.getElementById('step3Content');
      content.style.display = 'block';

      if(!data.success){
        // show error in step3, allow going back
        content.innerHTML = errorCard(data.error || 'OTP verification failed', function(){
          goStep(2);
          content.innerHTML='';
          document.getElementById('step3Loading').style.display='block';
          content.style.display='none';
        });
        return;
      }

      const p = data.profile;
      const initials = (p.name||'P').charAt(0).toUpperCase();
      const gBadge   = p.gender ? '<span class="badge badge-light ml-1">'+escHtml(p.gender)+'</span>' : '';
      const dobLine  = p.dob ? '<div style="font-size:.76rem;color:#6b7280;margin-top:2px;"><i class="fa fa-calendar mr-1"></i>'+escHtml(p.dob)+'</div>' : '';

      content.innerHTML =
        '<div class="cp-card">'
        +'<div class="cp-card-title"><i class="fa fa-check-circle mr-2" style="color:#16a34a;"></i>Patient Verified — Ready to Add</div>'
        +'<div class="profile-preview">'
        +'  <div class="d-flex align-items-center" style="gap:16px;">'
        +'    <div class="avatar">'+initials+'</div>'
        +'    <div style="flex:1;">'
        +'      <div style="font-weight:700;font-size:1rem;color:#1f2937;">'+escHtml(p.name||'—')+gBadge+'</div>'
        +dobLine
        +'      <div style="font-size:.76rem;color:#15803d;font-family:monospace;margin-top:3px;">'+escHtml(p.abha_number||'')+'</div>'
        +'      <div style="font-size:.74rem;color:#6b7280;">'+escHtml(p.abha_address||'')+'</div>'
        +'    </div>'
        +'    <span class="badge" style="background:#dcfce7;color:#16a34a;padding:5px 10px;border-radius:12px;font-size:.72rem;">'
        +'      <i class="fa fa-check-circle mr-1"></i>ABDM Verified'
        +'    </span>'
        +'  </div>'
        +'  <hr style="border-color:#bbf7d0;margin:12px 0;">'
        +'  <div class="row" style="font-size:.78rem;color:#374151;">'
        +(p.mobile?'<div class="col-sm-4"><i class="fa fa-phone mr-1 text-muted"></i>'+escHtml(p.mobile)+'</div>':'')
        +(p.email?'<div class="col-sm-4"><i class="fa fa-envelope mr-1 text-muted"></i>'+escHtml(p.email)+'</div>':'')
        +'  </div>'
        +'</div>'
        +'<div class="mt-3">'
        +'  <div class="alert alert-info" style="font-size:.8rem;border-radius:8px;">'
        +(data.is_new
          ? '<i class="fa fa-user-plus mr-2"></i><strong>New patient</strong> — will be created in portal and linked to your panel.'
          : '<i class="fa fa-link mr-2"></i><strong>Existing patient found</strong> — ABHA data updated and linked to your panel.')
        +'  </div>'
        +'  <div class="d-flex" style="gap:10px;">'
        +'    <button class="btn btn-outline-secondary" onclick="resetWizard()"><i class="fa fa-times mr-1"></i>Cancel</button>'
        +'    <button class="btn btn-success" onclick="confirmSave('+JSON.stringify(data).replace(/"/g,'&quot;')+')" style="min-width:160px;">'
        +'      <i class="fa fa-check mr-1"></i>'+(data.is_new?'Create & Add Patient':'Update & Add Patient')+'</button>'
        +'  </div>'
        +'</div>'
        +'</div>';
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check mr-1"></i> Verify OTP';
      document.getElementById('step3Loading').style.display = 'none';
      document.getElementById('step3Content').style.display = 'block';
      document.getElementById('step3Content').innerHTML = errorCard('Network error: '+err.message, function(){
        goStep(2);
      });
    });
  });

  window.confirmSave = function(data){
    // Already saved by abha-otp-verify.php — just redirect
    showToast((data.is_new?'Patient created':'Patient updated')+' and added to your panel!', 'success');
    setTimeout(() => window.location = BASE+'doctor/my-patients.php', 1600);
  };

  window.resetWizard = function(){
    currentTxnId = '';
    clearOtpInputs();
    clearInterval(otpTimerInterval);
    document.getElementById('step3Content').innerHTML = '';
    document.getElementById('step3Content').style.display = 'none';
    document.getElementById('step3Loading').style.display = 'block';
    goStep(1);
  };

  function errorCard(msg, backFn){
    return '<div class="cp-card"><div style="text-align:center;padding:20px 0;">'
      +'<i class="fa fa-exclamation-circle fa-3x" style="color:#dc2626;"></i>'
      +'<div style="margin-top:12px;font-weight:700;color:#9a3412;font-size:.95rem;">Verification Failed</div>'
      +'<div style="margin-top:6px;font-size:.84rem;color:#c2410c;">'+escHtml(msg)+'</div>'
      +'<button class="btn btn-outline-secondary mt-3" onclick="resetWizard()"><i class="fa fa-arrow-left mr-1"></i>Try Again</button>'
      +'</div></div>';
  }

  /* ── Manual form ── */
  document.getElementById('f_uid').value =
    'PAT'+ Date.now().toString(36).toUpperCase() + Math.random().toString(36).substr(2,4).toUpperCase();

  document.getElementById('photoInput').addEventListener('change', function(){
    if(!this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      const circle = document.getElementById('photoCircle');
      circle.innerHTML = '<img src="'+e.target.result+'" alt="">';
    };
    reader.readAsDataURL(this.files[0]);
  });

  document.getElementById('noEmailChk').addEventListener('change', function(){
    document.getElementById('f_email').disabled = this.checked;
    if(this.checked) document.getElementById('f_email').value = '';
  });

  document.getElementById('f_dob').addEventListener('input', function(){
    const parts = this.value.split('/');
    if(parts.length !== 3 || parts[2].length < 4) return;
    const d = new Date(parts[2], parts[1]-1, parts[0]);
    if(isNaN(d)) return;
    const today = new Date();
    let age = today.getFullYear() - d.getFullYear();
    if(today.getMonth()<d.getMonth()||(today.getMonth()===d.getMonth()&&today.getDate()<d.getDate())) age--;
    if(age >= 0 && age < 150) document.getElementById('f_age').value = age;
  });

  document.getElementById('f_abha_number').addEventListener('input', function(){
    let d = this.value.replace(/\D/g,'').substr(0,14);
    let out = d;
    if(d.length>2)  out=d.substr(0,2)+'-'+d.substr(2);
    if(d.length>6)  out=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6);
    if(d.length>10) out=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6,4)+'-'+d.substr(10);
    this.value = out;
  });

  document.getElementById('manualForm').addEventListener('submit', function(e){
    e.preventDefault();
    const btn = document.getElementById('btnManualCreate');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Creating…';

    let abhaAddr = document.getElementById('f_abha_address').value.trim();
    if(abhaAddr && abhaAddr.indexOf('@')===-1) abhaAddr += '@abdm';

    const payload = {
      first_name:    document.getElementById('f_first_name').value.trim(),
      middle_name:   document.getElementById('f_middle_name').value.trim(),
      last_name:     document.getElementById('f_last_name').value.trim(),
      email:         document.getElementById('f_email').value.trim(),
      no_email:      document.getElementById('noEmailChk').checked,
      mobile:        document.getElementById('f_mobile').value.trim(),
      gender:        document.getElementById('f_gender').value,
      dob:           document.getElementById('f_dob').value.trim(),
      blood_group:   document.getElementById('f_blood_group').value,
      abha_address:  abhaAddr,
      abha_number:   document.getElementById('f_abha_number').value.trim(),
      abha_verified: 0,
      pincode:       document.getElementById('f_pincode').value.trim(),
      city:          document.getElementById('f_city').value.trim(),
      state:         document.getElementById('f_state').value.trim(),
      address:       document.querySelector('[name="address"]').value.trim(),
    };

    fetch(BASE+'doctor/api/create-patient-submit.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    })
    .then(r=>r.json())
    .then(data=>{
      if(data.success){
        showToast(data.message||'Patient created!','success');
        setTimeout(()=>window.location=BASE+'doctor/my-patients.php',1600);
      } else {
        btn.disabled=false;
        btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';
        showToast(data.error||'Failed','error');
      }
    })
    .catch(err=>{
      btn.disabled=false;
      btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';
      showToast('Network error: '+err.message,'error');
    });
  });

  /* ── Helpers ── */
  function showErr(id, msg){ const e=document.getElementById(id); e.textContent=msg; e.style.display='block'; }
  function hideErr(id){ document.getElementById(id).style.display='none'; }

  function showToast(msg, type){
    const t = document.createElement('div');
    t.style.cssText='position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;'
      +'border-radius:10px;font-size:.84rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);'
      +(type==='success'?'background:#16a34a;color:#fff;':'background:#dc2626;color:#fff;');
    t.textContent=msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),3500);
  }

  function escHtml(s){
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
</script>
</body>
</html>
