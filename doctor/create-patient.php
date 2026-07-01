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
<title>Add Patient — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:22px;}
.cp-card-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}

/* ── Path selector cards ── */
.path-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;}
.path-card{border:2px solid #e5e7eb;border-radius:14px;padding:22px 16px;text-align:center;cursor:pointer;
  transition:.2s;background:#fff;position:relative;}
.path-card:hover{border-color:#0C74C5;box-shadow:0 4px 18px rgba(12,116,197,.12);}
.path-card.selected{border-color:#0C74C5;background:#eff6ff;}
.path-card .path-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;margin:0 auto 12px;color:#fff;}
.path-card .path-title{font-size:.9rem;font-weight:700;color:#1f2937;margin-bottom:4px;}
.path-card .path-desc{font-size:.74rem;color:#6b7280;line-height:1.5;}
.path-card .recommended{position:absolute;top:-1px;right:12px;background:#0C74C5;color:#fff;
  font-size:.62rem;font-weight:700;padding:2px 8px;border-radius:0 0 6px 6px;letter-spacing:.5px;}

/* ── Step wizard ── */
.step-wizard{display:flex;align-items:flex-start;gap:0;margin-bottom:24px;position:relative;}
.step-wizard::before{content:'';position:absolute;top:18px;left:18px;right:18px;height:2px;background:#e5e7eb;z-index:0;}
.step-item{flex:1;text-align:center;position:relative;z-index:1;}
.step-circle{width:36px;height:36px;border-radius:50%;border:2px solid #e5e7eb;background:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#9ca3af;transition:.25s;}
.step-item.active .step-circle{border-color:#0C74C5;background:#0C74C5;color:#fff;}
.step-item.done   .step-circle{border-color:#16a34a;background:#16a34a;color:#fff;}
.step-label{display:block;font-size:.7rem;color:#9ca3af;margin-top:5px;font-weight:600;}
.step-item.active .step-label{color:#0C74C5;}
.step-item.done   .step-label{color:#16a34a;}
.step-body{display:none;}
.step-body.active{display:block;}

/* ── OTP boxes ── */
.otp-row{display:flex;gap:8px;justify-content:center;margin:16px 0;}
.otp-row input{width:48px;height:52px;border-radius:10px;border:2px solid #e5e7eb;text-align:center;
  font-size:1.3rem;font-weight:700;color:#1f2937;transition:.15s;}
.otp-row input:focus{border-color:#0C74C5;outline:none;box-shadow:0 0 0 3px rgba(12,116,197,.12);}

/* ── ABHA address suggestion chips ── */
.addr-chip{display:inline-block;padding:7px 14px;border-radius:20px;border:2px solid #e5e7eb;
  font-size:.8rem;cursor:pointer;margin:4px;transition:.15s;background:#f9fafb;font-family:monospace;}
.addr-chip:hover{border-color:#0C74C5;background:#eff6ff;color:#0C74C5;}
.addr-chip.selected{border-color:#0C74C5;background:#0C74C5;color:#fff;}

/* ── Profile preview ── */
.profile-preview{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:18px;}
.pv-avatar{width:56px;height:56px;border-radius:50%;background:#0C74C5;color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;flex-shrink:0;overflow:hidden;}
.pv-avatar img{width:100%;height:100%;object-fit:cover;}

/* ── Manual form ── */
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;}
.photo-circle{width:80px;height:80px;border-radius:50%;border:2px dashed #d1d5db;
  display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;
  background:#f9fafb;overflow:hidden;transition:.2s;}
.photo-circle:hover{border-color:#0C74C5;}
.photo-circle img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.aadhaar-field{font-family:monospace;letter-spacing:2px;font-size:.95rem;}

@media(max-width:600px){.path-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<main class="doctor-content">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Add Patient</h5>
      <div style="font-size:.75rem;color:#9ca3af;">Choose how you want to add this patient</div>
    </div>
    <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-sm btn-outline-secondary">
      <i class="fa fa-arrow-left mr-1"></i> Back
    </a>
  </div>

  <div class="row">
    <div class="col-lg-8">

      <!-- ══════════════════════════════════
           PATH SELECTOR
           ══════════════════════════════════ -->
      <div id="pathSelector">
        <p style="font-size:.84rem;color:#374151;margin-bottom:14px;font-weight:600;">
          How does this patient want to connect?
        </p>
        <div class="path-grid">

          <!-- Path A: Has ABHA -->
          <div class="path-card" id="pathA" onclick="selectPath('A')">
            <div class="recommended">RECOMMENDED</div>
            <div class="path-icon" style="background:#0C74C5;"><i class="fa fa-id-card-o"></i></div>
            <div class="path-title">Patient Has ABHA</div>
            <div class="path-desc">Patient already has an ABHA number or address. Verify with OTP and fetch their profile.</div>
          </div>

          <!-- Path B: Create ABHA -->
          <div class="path-card" id="pathB" onclick="selectPath('B')">
            <div class="path-icon" style="background:#02c9b8;"><i class="fa fa-plus-circle"></i></div>
            <div class="path-title">Create New ABHA</div>
            <div class="path-desc">Patient is new to ABDM. Create their ABHA using Aadhaar OTP — takes 2 minutes.</div>
          </div>

          <!-- Path C: No ABHA -->
          <div class="path-card" id="pathC" onclick="selectPath('C')">
            <div class="path-icon" style="background:#e07e18;"><i class="fa fa-user-plus"></i></div>
            <div class="path-title">No ABHA / Skip</div>
            <div class="path-desc">Add patient manually without ABHA. They can link it later from their portal.</div>
          </div>

        </div>
      </div>

      <!-- ══════════════════════════════════
           PATH A — Has ABHA (OTP verify)
           ══════════════════════════════════ -->
      <div id="flowA" style="display:none;">
        <div class="d-flex align-items-center mb-3" style="gap:10px;">
          <button class="btn btn-sm btn-outline-secondary" onclick="resetPath()">
            <i class="fa fa-arrow-left"></i>
          </button>
          <div>
            <span style="font-weight:700;color:#0C74C5;font-size:.9rem;"><i class="fa fa-id-card-o mr-1"></i>Patient Has ABHA</span>
            <span style="font-size:.74rem;color:#9ca3af;display:block;">Verify identity via ABDM OTP</span>
          </div>
        </div>

        <div class="step-wizard" id="wizA">
          <div class="step-item active" id="siA1"><div class="step-circle">1</div><span class="step-label">Enter ABHA</span></div>
          <div class="step-item"        id="siA2"><div class="step-circle">2</div><span class="step-label">Verify OTP</span></div>
          <div class="step-item"        id="siA3"><div class="step-circle">3</div><span class="step-label">Confirm</span></div>
        </div>

        <!-- A-Step 1 -->
        <div class="step-body active" id="stepA1">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-search mr-2" style="color:#0C74C5;"></i>Enter Patient's ABHA</div>
            <div class="d-flex gap-2 mb-3">
              <button class="type-btn active" data-stype="number" onclick="setAbhaType(this,'number')">ABHA Number</button>
              <button class="type-btn ml-2" data-stype="address" onclick="setAbhaType(this,'address')">ABHA Address</button>
            </div>
            <div class="form-group">
              <label class="form-label-sm" id="aInputLabel">ABHA Number</label>
              <input type="text" id="aAbhaInput" class="form-control"
                     placeholder="XX-XXXX-XXXX-XXXX" maxlength="17"
                     style="font-family:monospace;font-size:1rem;letter-spacing:1px;">
              <small class="text-muted" id="aInputHint">14-digit ABHA number — patient can find it on their ABHA card</small>
            </div>
            <button class="btn btn-primary" id="btnASendOtp">
              <i class="fa fa-paper-plane mr-1"></i> Send OTP to Patient's Mobile
            </button>
            <div id="errA1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
          </div>
        </div>

        <!-- A-Step 2 -->
        <div class="step-body" id="stepA2">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-mobile mr-2" style="color:#0C74C5;"></i>Enter OTP</div>
            <p class="text-center" id="aSentMsg" style="font-size:.86rem;color:#374151;"></p>
            <div class="otp-row" id="otpRowA">
              <input type="text" class="otp-digit-a" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-a" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-a" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-a" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-a" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-a" maxlength="1" inputmode="numeric">
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <button class="btn btn-link btn-sm p-0" id="btnAResend" disabled style="font-size:.78rem;">
                <i class="fa fa-refresh mr-1"></i> Resend OTP
              </button>
              <span id="timerA" style="font-size:.76rem;color:#9ca3af;"></span>
            </div>
            <div id="errA2" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
            <div class="d-flex mt-3" style="gap:10px;">
              <button class="btn btn-outline-secondary btn-sm" onclick="goStepA(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
              <button class="btn btn-primary" id="btnAVerify"><i class="fa fa-check mr-1"></i> Verify OTP</button>
            </div>
          </div>
        </div>

        <!-- A-Step 3 -->
        <div class="step-body" id="stepA3">
          <div id="stepA3Inner">
            <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x" style="color:#0C74C5;"></i><div class="mt-2 text-muted small">Fetching profile from ABDM…</div></div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════
           PATH B — Create New ABHA (M1)
           ══════════════════════════════════ -->
      <div id="flowB" style="display:none;">
        <div class="d-flex align-items-center mb-3" style="gap:10px;">
          <button class="btn btn-sm btn-outline-secondary" onclick="resetPath()"><i class="fa fa-arrow-left"></i></button>
          <div>
            <span style="font-weight:700;color:#02c9b8;font-size:.9rem;"><i class="fa fa-plus-circle mr-1"></i>Create New ABHA</span>
            <span style="font-size:.74rem;color:#9ca3af;display:block;">Uses Aadhaar OTP — takes 2 minutes</span>
          </div>
        </div>

        <div class="step-wizard" id="wizB">
          <div class="step-item active" id="siBs1"><div class="step-circle">1</div><span class="step-label">Aadhaar OTP</span></div>
          <div class="step-item"        id="siBs2"><div class="step-circle">2</div><span class="step-label">Verify OTP</span></div>
          <div class="step-item"        id="siBs3"><div class="step-circle">3</div><span class="step-label">ABHA Address</span></div>
          <div class="step-item"        id="siBs4"><div class="step-circle">4</div><span class="step-label">Confirm</span></div>
        </div>

        <!-- B-Step 1 -->
        <div class="step-body active" id="stepB1">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-id-card mr-2" style="color:#02c9b8;"></i>Patient's Aadhaar Number</div>
            <div class="alert alert-info" style="font-size:.8rem;border-radius:8px;">
              <i class="fa fa-info-circle mr-2"></i>
              OTP will be sent to the mobile number linked with the patient's Aadhaar (not stored in our system).
              Aadhaar number is <strong>RSA-encrypted</strong> before sending to ABDM and never stored.
            </div>
            <div class="form-group">
              <label class="form-label-sm">Aadhaar Number</label>
              <input type="password" id="bAadhaarInput" class="form-control aadhaar-field"
                     placeholder="•••• •••• ••••" maxlength="12" autocomplete="off">
              <small class="text-muted">12-digit Aadhaar number. Patient must be present.</small>
            </div>
            <div class="form-group">
              <label class="form-label-sm">Alternate Mobile <small class="text-muted">(optional — if different from Aadhaar mobile)</small></label>
              <div class="input-group input-group-sm" style="max-width:250px;">
                <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
                <input type="text" id="bMobileInput" class="form-control" placeholder="9876543210" maxlength="10">
              </div>
            </div>
            <button class="btn btn-success" id="btnBSendOtp">
              <i class="fa fa-paper-plane mr-1"></i> Send OTP to Aadhaar Mobile
            </button>
            <div id="errB1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
          </div>
        </div>

        <!-- B-Step 2 -->
        <div class="step-body" id="stepB2">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-mobile mr-2" style="color:#02c9b8;"></i>Enter Aadhaar OTP</div>
            <p class="text-center" id="bSentMsg" style="font-size:.86rem;color:#374151;"></p>
            <div class="otp-row">
              <input type="text" class="otp-digit-b" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-b" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-b" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-b" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-b" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-digit-b" maxlength="1" inputmode="numeric">
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <button class="btn btn-link btn-sm p-0" id="btnBResend" disabled style="font-size:.78rem;">
                <i class="fa fa-refresh mr-1"></i> Resend OTP
              </button>
              <span id="timerB" style="font-size:.76rem;color:#9ca3af;"></span>
            </div>
            <div id="errB2" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
            <div class="d-flex mt-3" style="gap:10px;">
              <button class="btn btn-outline-secondary btn-sm" onclick="goStepB(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
              <button class="btn btn-success" id="btnBVerify"><i class="fa fa-check mr-1"></i> Verify OTP</button>
            </div>
          </div>
        </div>

        <!-- B-Step 3: Choose ABHA Address -->
        <div class="step-body" id="stepB3">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-at mr-2" style="color:#02c9b8;"></i>Choose ABHA Address</div>
            <p style="font-size:.84rem;color:#374151;">
              ABHA has been created! Now select a preferred ABHA address for this patient:
            </p>
            <div id="addrSuggestions" style="margin-bottom:14px;"></div>
            <div class="form-group">
              <label class="form-label-sm">Or enter a custom address</label>
              <div class="input-group" style="max-width:300px;">
                <input type="text" id="bCustomAddr" class="form-control form-control-sm" placeholder="yourname">
                <div class="input-group-append"><span class="input-group-text">@abdm</span></div>
              </div>
            </div>
            <div id="errB3" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
            <div class="d-flex mt-3" style="gap:10px;">
              <button class="btn btn-outline-secondary btn-sm" onclick="goStepB(2)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
              <button class="btn btn-success" id="btnBConfirmAddr"><i class="fa fa-check mr-1"></i> Confirm Address</button>
            </div>
          </div>
        </div>

        <!-- B-Step 4: Final Confirm -->
        <div class="step-body" id="stepB4">
          <div id="stepB4Inner">
            <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x" style="color:#02c9b8;"></i><div class="mt-2 text-muted small">Saving patient…</div></div>
          </div>
        </div>

      </div>

      <!-- ══════════════════════════════════
           PATH C — Manual Form
           ══════════════════════════════════ -->
      <div id="flowC" style="display:none;">
        <div class="d-flex align-items-center mb-3" style="gap:10px;">
          <button class="btn btn-sm btn-outline-secondary" onclick="resetPath()"><i class="fa fa-arrow-left"></i></button>
          <div>
            <span style="font-weight:700;color:#e07e18;font-size:.9rem;"><i class="fa fa-user-plus mr-1"></i>Add Manually</span>
            <span style="font-size:.74rem;color:#9ca3af;display:block;">No ABHA required — can link later</span>
          </div>
        </div>

        <form id="manualForm">
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-user-circle-o mr-2" style="color:#e07e18;"></i>Patient Details</div>
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
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="form-label-sm">First Name <span class="text-danger">*</span></label>
                      <input type="text" name="first_name" id="f_first_name" class="form-control form-control-sm" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="form-label-sm">Middle Name</label>
                      <input type="text" name="middle_name" class="form-control form-control-sm">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="form-label-sm">Last Name</label>
                      <input type="text" name="last_name" class="form-control form-control-sm">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label-sm">Mobile <span class="text-danger">*</span></label>
                      <div class="input-group input-group-sm">
                        <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
                        <input type="text" name="mobile" id="f_mobile" class="form-control" placeholder="9876543210" maxlength="10" required>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label-sm">Email</label>
                      <input type="email" name="email" id="f_email" class="form-control form-control-sm">
                      <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="noEmailChk">
                        <label class="form-check-label" for="noEmailChk" style="font-size:.74rem;color:#6b7280;">No email</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="form-label-sm">Gender</label>
                      <select name="gender" class="form-control form-control-sm">
                        <option value="">--</option><option>Male</option><option>Female</option><option>Other</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="form-label-sm">DOB</label>
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
                      <label class="form-label-sm">Blood Group</label>
                      <select name="blood_group" class="form-control form-control-sm">
                        <option value="">--</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option><?= $bg ?></option><?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-map-marker mr-2" style="color:#e07e18;"></i>Address</div>
            <div class="row">
              <div class="col-md-4"><div class="form-group"><label class="form-label-sm">Pin Code</label><input type="text" name="pincode" class="form-control form-control-sm" maxlength="6"></div></div>
              <div class="col-md-4"><div class="form-group"><label class="form-label-sm">City</label><input type="text" name="city" class="form-control form-control-sm"></div></div>
              <div class="col-md-4"><div class="form-group"><label class="form-label-sm">State</label><input type="text" name="state" class="form-control form-control-sm"></div></div>
            </div>
            <div class="form-group"><label class="form-label-sm">Full Address</label><textarea name="address" class="form-control form-control-sm" rows="2"></textarea></div>
          </div>

          <div class="d-flex justify-content-end" style="gap:10px;">
            <button type="button" class="btn btn-outline-secondary" onclick="resetPath()">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btnManualCreate">
              <i class="fa fa-user-plus mr-1"></i> Create Patient
            </button>
          </div>
        </form>
      </div>

    </div><!-- /col-lg-8 -->

    <!-- Info Panel -->
    <div class="col-lg-4">
      <div class="cp-card" style="position:sticky;top:80px;">
        <div class="cp-card-title"><i class="fa fa-question-circle mr-2" style="color:#0C74C5;"></i>Which Option?</div>
        <div style="font-size:.82rem;">

          <div class="mb-3 p-3" style="background:#eff6ff;border-radius:10px;">
            <div style="font-weight:700;color:#0C74C5;margin-bottom:5px;"><i class="fa fa-id-card-o mr-1"></i> Patient Has ABHA</div>
            <div style="font-size:.76rem;color:#6b7280;">Patient was already registered on ABDM / any hospital. They know their ABHA number (<code>XX-XXXX-XXXX-XXXX</code>) or address (<code>name@abdm</code>).</div>
          </div>

          <div class="mb-3 p-3" style="background:#f0fdfa;border-radius:10px;">
            <div style="font-weight:700;color:#02c9b8;margin-bottom:5px;"><i class="fa fa-plus-circle mr-1"></i> Create New ABHA</div>
            <div style="font-size:.76rem;color:#6b7280;">Patient has <strong>never registered</strong> on ABDM. We create their ABHA using Aadhaar OTP — they get a permanent health ID instantly.</div>
          </div>

          <div class="mb-3 p-3" style="background:#fff7ed;border-radius:10px;">
            <div style="font-weight:700;color:#e07e18;margin-bottom:5px;"><i class="fa fa-user-plus mr-1"></i> No ABHA / Skip</div>
            <div style="font-size:.76rem;color:#6b7280;">For patients who don't have Aadhaar or want to skip for now. They won't have access to digital health records until ABHA is linked.</div>
          </div>

          <hr>
          <div style="font-size:.72rem;color:#9ca3af;line-height:1.6;">
            <i class="fa fa-lock mr-1"></i> Aadhaar is RSA-encrypted before sending to ABDM and <strong>never stored</strong> in our database. All actions are logged per NHA audit guidelines.
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const BASE = '<?= BASE_URL ?>';

  /* ══════════════════════════════════════════
     PATH SELECTION
     ══════════════════════════════════════════ */
  window.selectPath = function(path){
    document.getElementById('pathSelector').style.display = 'none';
    ['A','B','C'].forEach(p => document.getElementById('flow'+p).style.display = 'none');
    document.getElementById('flow'+path).style.display = 'block';
  };
  window.resetPath = function(){
    document.getElementById('pathSelector').style.display = 'block';
    ['A','B','C'].forEach(p => document.getElementById('flow'+p).style.display = 'none');
  };

  /* ══════════════════════════════════════════
     SHARED UTILITIES
     ══════════════════════════════════════════ */
  function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function showErr(id,msg){ const e=document.getElementById(id); if(e){e.textContent=msg;e.style.display='block';} }
  function hideErr(id){ const e=document.getElementById(id); if(e) e.style.display='none'; }
  function showToast(msg,type){
    const t=document.createElement('div');
    t.style.cssText='position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:.84rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);'
      +(type==='success'?'background:#16a34a;color:#fff;':'background:#dc2626;color:#fff;');
    t.textContent=msg; document.body.appendChild(t); setTimeout(()=>t.remove(),3500);
  }
  function startTimer(timerId, btnId, seconds){
    const timerEl = document.getElementById(timerId);
    const btnEl   = document.getElementById(btnId);
    btnEl.disabled = true;
    let rem = seconds;
    const iv = setInterval(()=>{
      const m=String(Math.floor(rem/60)).padStart(2,'0'), s=String(rem%60).padStart(2,'0');
      timerEl.textContent='Resend in '+m+':'+s;
      if(--rem < 0){ clearInterval(iv); timerEl.textContent=''; btnEl.disabled=false; }
    },1000);
    return iv;
  }
  function wireOtpDigits(selector){
    const digits = document.querySelectorAll(selector);
    digits.forEach((inp,idx)=>{
      inp.addEventListener('input',function(){
        this.value=this.value.replace(/\D/g,'');
        if(this.value && idx<digits.length-1) digits[idx+1].focus();
      });
      inp.addEventListener('keydown',function(e){
        if(e.key==='Backspace'&&!this.value&&idx>0) digits[idx-1].focus();
      });
      inp.addEventListener('paste',function(e){
        const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
        if(p.length>=6){ e.preventDefault(); p.substr(0,6).split('').forEach((d,i)=>{if(digits[i])digits[i].value=d;}); digits[Math.min(5,p.length-1)].focus(); }
      });
    });
    return ()=>Array.from(digits).map(i=>i.value).join('');
  }
  function profileCard(p, isNew, label){
    const init=(p.name||'P').charAt(0).toUpperCase();
    const photo=p.photo?'<img src="data:image/png;base64,'+p.photo+'" alt="">':init;
    const gBadge=p.gender?'<span class="badge badge-light ml-1">'+escHtml(p.gender)+'</span>':'';
    const dob=p.dob?'<div style="font-size:.74rem;color:#6b7280;"><i class="fa fa-calendar mr-1"></i>'+escHtml(p.dob)+'</div>':'';
    const mob=p.mobile?'<div style="font-size:.76rem;color:#374151;margin-top:2px;"><i class="fa fa-phone mr-1 text-muted"></i>'+escHtml(p.mobile)+'</div>':'';
    return '<div class="profile-preview"><div class="d-flex align-items-center" style="gap:14px;">'
      +'<div class="pv-avatar">'+photo+'</div>'
      +'<div style="flex:1;">'
      +'<div style="font-weight:700;font-size:1rem;color:#1f2937;">'+escHtml(p.name||'—')+gBadge+'</div>'
      +dob+mob
      +'<div style="font-size:.74rem;color:#15803d;font-family:monospace;margin-top:3px;">'+escHtml(p.abha_number||'')+'</div>'
      +'<div style="font-size:.72rem;color:#6b7280;">'+escHtml(p.abha_address||'')+'</div>'
      +'</div>'
      +'<span style="background:#dcfce7;color:#16a34a;padding:5px 10px;border-radius:12px;font-size:.7rem;font-weight:700;white-space:nowrap;">'
      +'<i class="fa fa-check-circle mr-1"></i>ABDM Verified</span>'
      +'</div></div>'
      +'<div class="alert '+(isNew?'alert-success':'alert-info')+' mt-2 mb-0" style="font-size:.78rem;border-radius:8px;">'
      +(isNew?'<i class="fa fa-user-plus mr-2"></i><strong>New patient</strong> — will be created in portal.'
             :'<i class="fa fa-link mr-2"></i><strong>Existing patient found</strong> — ABHA data will be updated.')
      +'</div>';
  }

  /* ══════════════════════════════════════════
     PATH A — Has ABHA (OTP verify)
     ══════════════════════════════════════════ */
  let aTxnId='', aAbhaType='number';
  const getOtpA = wireOtpDigits('.otp-digit-a');

  function setAbhaType(btn, type){
    document.querySelectorAll('[data-stype]').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    aAbhaType = type;
    const inp=document.getElementById('aAbhaInput'), lbl=document.getElementById('aInputLabel'), hint=document.getElementById('aInputHint');
    if(type==='number'){
      lbl.textContent='ABHA Number'; inp.placeholder='XX-XXXX-XXXX-XXXX'; inp.maxLength=17; hint.textContent='14-digit ABHA number on patient\'s ABHA card';
    } else {
      lbl.textContent='ABHA Address'; inp.placeholder='name@abdm'; inp.maxLength=60; hint.textContent='ABHA address (e.g. john.doe@abdm)';
    }
    inp.value='';
  }
  window.setAbhaType = setAbhaType;

  document.getElementById('aAbhaInput').addEventListener('input',function(){
    if(aAbhaType!=='number') return;
    let d=this.value.replace(/\D/g,'').substr(0,14),out=d;
    if(d.length>2) out=d.substr(0,2)+'-'+d.substr(2);
    if(d.length>6) out=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6);
    if(d.length>10) out=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6,4)+'-'+d.substr(10);
    this.value=out;
  });

  function goStepA(n){
    document.querySelectorAll('#flowA .step-body').forEach(s=>s.classList.remove('active'));
    document.getElementById('stepA'+n).classList.add('active');
    ['siA1','siA2','siA3'].forEach((id,i)=>{
      const el=document.getElementById(id); el.classList.remove('active','done');
      if(i+1<n){el.classList.add('done');el.querySelector('.step-circle').innerHTML='<i class="fa fa-check"></i>';}
      if(i+1===n){el.classList.add('active');el.querySelector('.step-circle').innerHTML=n;}
    });
  }
  window.goStepA=goStepA;

  document.getElementById('btnASendOtp').addEventListener('click',function(){
    const val=document.getElementById('aAbhaInput').value.trim();
    if(!val){showErr('errA1','Please enter an ABHA '+(aAbhaType==='number'?'number':'address'));return;}
    hideErr('errA1');
    const btn=this; btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';
    fetch(BASE+'doctor/api/abha-otp-send.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({abha_input:val,type:aAbhaType})})
    .then(r=>r.json()).then(data=>{
      btn.disabled=false; btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Patient\'s Mobile';
      if(!data.success){showErr('errA1',data.error||'Failed to send OTP');return;}
      aTxnId=data.txnId;
      document.getElementById('aSentMsg').innerHTML='<i class="fa fa-check-circle" style="color:#16a34a;margin-right:6px;"></i>'+escHtml(data.message);
      document.querySelectorAll('.otp-digit-a').forEach(i=>i.value='');
      goStepA(2); startTimer('timerA','btnAResend',300);
    }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Patient\'s Mobile';showErr('errA1','Network error: '+e.message);});
  });

  document.getElementById('btnAResend').addEventListener('click',function(){
    document.getElementById('btnASendOtp').click();
  });

  document.getElementById('btnAVerify').addEventListener('click',function(){
    const otp=getOtpA();
    if(otp.length<6){showErr('errA2','Please enter the complete 6-digit OTP');return;}
    if(!aTxnId){showErr('errA2','Session expired — please resend OTP');return;}
    hideErr('errA2');
    const btn=this; btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
    goStepA(3);
    fetch(BASE+'doctor/api/abha-otp-verify.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({txnId:aTxnId,otp:otp})})
    .then(r=>r.json()).then(data=>{
      btn.disabled=false; btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
      const inner=document.getElementById('stepA3Inner');
      if(!data.success){
        inner.innerHTML='<div class="cp-card text-center py-4"><i class="fa fa-times-circle fa-3x text-danger"></i>'
          +'<div class="mt-2 font-weight-bold text-danger">'+escHtml(data.error||'Failed')+'</div>'
          +'<button class="btn btn-outline-secondary mt-3" onclick="goStepA(2)"><i class="fa fa-arrow-left mr-1"></i>Try Again</button></div>';
        return;
      }
      inner.innerHTML='<div class="cp-card">'
        +'<div class="cp-card-title"><i class="fa fa-check-circle mr-2" style="color:#16a34a;"></i>Ready to Add</div>'
        +profileCard(data.profile,data.is_new)
        +'<div class="d-flex mt-3" style="gap:10px;">'
        +'<button class="btn btn-outline-secondary" onclick="goStepA(1)"><i class="fa fa-times mr-1"></i>Cancel</button>'
        +'<button class="btn btn-success" style="min-width:160px;" onclick="finalSave('
        +JSON.stringify(data).replace(/"/g,'&quot;')+')">'
        +'<i class="fa fa-check mr-1"></i>'+(data.is_new?'Create & Add Patient':'Update & Add Patient')+'</button>'
        +'</div></div>';
    }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
      document.getElementById('stepA3Inner').innerHTML='<div class="cp-card text-center py-4 text-danger">Network error: '+escHtml(e.message)+'<br><button class="btn btn-outline-secondary mt-2" onclick="goStepA(2)">Back</button></div>';
    });
  });

  window.finalSave = function(data){
    showToast((data.is_new?'Patient created':'Patient updated')+' and added!','success');
    setTimeout(()=>window.location=BASE+'doctor/my-patients.php',1600);
  };

  /* ══════════════════════════════════════════
     PATH B — Create New ABHA (M1 Aadhaar OTP)
     ══════════════════════════════════════════ */
  let bTxnId='', bXToken='', bProfile={}, bIsNew=true;
  const getOtpB = wireOtpDigits('.otp-digit-b');
  let bSelectedAddr='';

  function goStepB(n){
    document.querySelectorAll('#flowB .step-body').forEach(s=>s.classList.remove('active'));
    document.getElementById('stepB'+n).classList.add('active');
    ['siBs1','siBs2','siBs3','siBs4'].forEach((id,i)=>{
      const el=document.getElementById(id); el.classList.remove('active','done');
      if(i+1<n){el.classList.add('done');el.querySelector('.step-circle').innerHTML='<i class="fa fa-check"></i>';}
      if(i+1===n){el.classList.add('active');el.querySelector('.step-circle').innerHTML=n;}
    });
  }
  window.goStepB=goStepB;

  document.getElementById('btnBSendOtp').addEventListener('click',function(){
    const aadhaar=document.getElementById('bAadhaarInput').value.replace(/\D/g,'');
    if(aadhaar.length!==12){showErr('errB1','Aadhaar must be exactly 12 digits');return;}
    hideErr('errB1');
    const btn=this; btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';
    const mob=document.getElementById('bMobileInput').value.replace(/\D/g,'');
    fetch(BASE+'doctor/api/abha-enrol-otp.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({aadhaar:aadhaar,mobile:mob||''})})
    .then(r=>r.json()).then(data=>{
      btn.disabled=false; btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Aadhaar Mobile';
      if(!data.success){showErr('errB1',data.error||'Failed');return;}
      bTxnId=data.txnId;
      document.getElementById('bSentMsg').innerHTML='<i class="fa fa-check-circle" style="color:#16a34a;margin-right:6px;"></i>'+escHtml(data.message);
      document.querySelectorAll('.otp-digit-b').forEach(i=>i.value='');
      goStepB(2); startTimer('timerB','btnBResend',300);
    }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Aadhaar Mobile';showErr('errB1','Network error: '+e.message);});
  });

  document.getElementById('btnBResend').addEventListener('click',function(){
    goStepB(1); setTimeout(()=>document.getElementById('btnBSendOtp').click(),100);
  });

  document.getElementById('btnBVerify').addEventListener('click',function(){
    const otp=getOtpB();
    if(otp.length<6){showErr('errB2','Enter the complete 6-digit OTP');return;}
    if(!bTxnId){showErr('errB2','Session expired — please resend');return;}
    hideErr('errB2');
    const btn=this; btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
    const mob=document.getElementById('bMobileInput').value.replace(/\D/g,'');
    fetch(BASE+'doctor/api/abha-enrol-verify.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({txnId:bTxnId,otp:otp,mobile:mob||''})})
    .then(r=>r.json()).then(data=>{
      btn.disabled=false; btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
      if(!data.success){showErr('errB2',data.error||'OTP failed');return;}
      bTxnId=data.txnId; bXToken=data.xToken; bProfile=data.profile; bIsNew=data.is_new_abha;
      // Show address suggestions
      const wrap=document.getElementById('addrSuggestions');
      const suggs=data.suggestions||[];
      if(suggs.length){
        wrap.innerHTML='<div style="font-size:.78rem;color:#374151;margin-bottom:8px;font-weight:600;">ABDM Suggestions:</div>'
          +suggs.map(a=>'<span class="addr-chip" onclick="selectAddr(this,\''+escHtml(a)+'\')">'+escHtml(a)+'</span>').join('');
      } else {
        wrap.innerHTML='<div style="font-size:.78rem;color:#9ca3af;">No suggestions available — enter a custom address below.</div>';
      }
      goStepB(3);
    }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';showErr('errB2','Network error: '+e.message);});
  });

  window.selectAddr = function(chip, addr){
    document.querySelectorAll('.addr-chip').forEach(c=>c.classList.remove('selected'));
    chip.classList.add('selected');
    bSelectedAddr = addr;
    document.getElementById('bCustomAddr').value='';
  };

  document.getElementById('btnBConfirmAddr').addEventListener('click',function(){
    const custom=document.getElementById('bCustomAddr').value.trim();
    const chosen = custom || bSelectedAddr;
    if(!chosen){showErr('errB3','Please select or enter an ABHA address');return;}
    hideErr('errB3');
    const btn=this; btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Confirming…';
    goStepB(4);
    fetch(BASE+'doctor/api/abha-enrol-confirm.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({txnId:bTxnId,xToken:bXToken,chosen_address:chosen,profile:bProfile,is_new_abha:bIsNew})})
    .then(r=>r.json()).then(data=>{
      btn.disabled=false; btn.innerHTML='<i class="fa fa-check mr-1"></i> Confirm Address';
      const inner=document.getElementById('stepB4Inner');
      if(!data.success){
        inner.innerHTML='<div class="cp-card text-center py-4"><i class="fa fa-times-circle fa-3x text-danger"></i><div class="mt-2 font-weight-bold text-danger">'+escHtml(data.error||'Failed')+'</div><button class="btn btn-outline-secondary mt-3" onclick="goStepB(3)">Back</button></div>';
        return;
      }
      inner.innerHTML='<div class="success-card cp-card">'
        +'<i class="fa fa-check-circle fa-3x" style="color:#16a34a;"></i>'
        +'<div class="mt-3 font-weight-bold" style="font-size:1.1rem;color:#166534;">ABHA Created Successfully!</div>'
        +'<div class="mt-2" style="font-size:.84rem;color:#374151;">'+escHtml(data.message)+'</div>'
        +'<div class="mt-3 p-3" style="background:#f0fdf4;border-radius:10px;display:inline-block;">'
        +'<div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;font-weight:700;margin-bottom:3px;">ABHA Number</div>'
        +'<div style="font-family:monospace;font-size:1rem;color:#16a34a;font-weight:700;">'+escHtml(data.abha_number||'')+'</div>'
        +'<div style="font-size:.76rem;color:#6b7280;margin-top:3px;">'+escHtml(data.abha_address||'')+'</div>'
        +'</div>'
        +'<div class="mt-3"><button class="btn btn-success" onclick="window.location=BASE+\'doctor/my-patients.php\'">'
        +'<i class="fa fa-users mr-1"></i> Go to My Patients</button></div></div>';
    }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Confirm Address';
      document.getElementById('stepB4Inner').innerHTML='<div class="cp-card text-center py-4 text-danger">Network error: '+escHtml(e.message)+'<br><button class="btn btn-outline-secondary mt-2" onclick="goStepB(3)">Back</button></div>';
    });
  });

  /* ══════════════════════════════════════════
     PATH C — Manual Form
     ══════════════════════════════════════════ */
  document.getElementById('photoInput').addEventListener('change',function(){
    if(!this.files[0]) return;
    const reader=new FileReader();
    reader.onload=e=>{
      const c=document.getElementById('photoCircle');
      c.innerHTML='<img src="'+e.target.result+'" alt="">';
    };
    reader.readAsDataURL(this.files[0]);
  });
  document.getElementById('noEmailChk').addEventListener('change',function(){
    document.getElementById('f_email').disabled=this.checked;
    if(this.checked) document.getElementById('f_email').value='';
  });
  document.getElementById('f_dob').addEventListener('input',function(){
    const p=this.value.split('/');
    if(p.length!==3||p[2].length<4) return;
    const d=new Date(p[2],p[1]-1,p[0]); if(isNaN(d)) return;
    let age=new Date().getFullYear()-d.getFullYear();
    const today=new Date();
    if(today.getMonth()<d.getMonth()||(today.getMonth()===d.getMonth()&&today.getDate()<d.getDate())) age--;
    if(age>=0&&age<150) document.getElementById('f_age').value=age;
  });

  document.getElementById('manualForm').addEventListener('submit',function(e){
    e.preventDefault();
    const btn=document.getElementById('btnManualCreate');
    btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Creating…';
    const payload={
      first_name:  document.getElementById('f_first_name').value.trim(),
      middle_name: document.querySelector('[name="middle_name"]').value.trim(),
      last_name:   document.querySelector('[name="last_name"]').value.trim(),
      email:       document.getElementById('f_email').value.trim(),
      no_email:    document.getElementById('noEmailChk').checked,
      mobile:      document.getElementById('f_mobile').value.trim(),
      gender:      document.querySelector('[name="gender"]').value,
      dob:         document.getElementById('f_dob').value.trim(),
      blood_group: document.querySelector('[name="blood_group"]').value,
      pincode:     document.querySelector('[name="pincode"]').value.trim(),
      city:        document.querySelector('[name="city"]').value.trim(),
      state:       document.querySelector('[name="state"]').value.trim(),
      address:     document.querySelector('[name="address"]').value.trim(),
      abha_number:'',abha_address:'',abha_verified:0,
    };
    fetch(BASE+'doctor/api/create-patient-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(r=>r.json()).then(data=>{
      if(data.success){showToast(data.message||'Patient created!','success');setTimeout(()=>window.location=BASE+'doctor/my-patients.php',1600);}
      else{btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';showToast(data.error||'Failed','error');}
    }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';showToast('Network error','error');});
  });

})();
</script>
</body>
</html>
