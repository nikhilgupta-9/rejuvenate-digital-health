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
.cp-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}

/* Path selector */
.path-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:24px;}
.path-card{border:2px solid #e5e7eb;border-radius:14px;padding:20px 16px;cursor:pointer;transition:.2s;background:#fff;position:relative;display:flex;align-items:flex-start;gap:14px;}
.path-card:hover{border-color:#0C74C5;box-shadow:0 4px 18px rgba(12,116,197,.12);}
.path-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;flex-shrink:0;}
.path-title{font-size:.88rem;font-weight:700;color:#1f2937;margin-bottom:3px;}
.path-desc{font-size:.74rem;color:#6b7280;line-height:1.5;}
.path-badge{position:absolute;top:-1px;right:14px;font-size:.62rem;font-weight:700;padding:2px 8px;border-radius:0 0 6px 6px;letter-spacing:.5px;}

/* Step wizard */
.wizard{display:flex;margin-bottom:22px;position:relative;}
.wizard::before{content:'';position:absolute;top:18px;left:18px;right:18px;height:2px;background:#e5e7eb;z-index:0;}
.wi{flex:1;text-align:center;position:relative;z-index:1;}
.wc{width:36px;height:36px;border-radius:50%;border:2px solid #e5e7eb;background:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#9ca3af;transition:.25s;}
.wi.active .wc{border-color:#0C74C5;background:#0C74C5;color:#fff;}
.wi.done   .wc{border-color:#16a34a;background:#16a34a;color:#fff;}
.wl{display:block;font-size:.68rem;color:#9ca3af;margin-top:4px;font-weight:600;}
.wi.active .wl{color:#0C74C5;}
.wi.done   .wl{color:#16a34a;}
.sbody{display:none;}
.sbody.active{display:block;}

/* OTP boxes */
.otp-row{display:flex;gap:8px;justify-content:center;margin:14px 0;}
.otp-row input{width:46px;height:50px;border-radius:10px;border:2px solid #e5e7eb;text-align:center;
  font-size:1.3rem;font-weight:700;color:#1f2937;transition:.15s;}
.otp-row input:focus{border-color:#0C74C5;outline:none;box-shadow:0 0 0 3px rgba(12,116,197,.12);}

/* ABHA address chips */
.addr-chip{display:inline-block;padding:6px 12px;border-radius:20px;border:2px solid #e5e7eb;
  font-size:.78rem;cursor:pointer;margin:3px;transition:.15s;background:#f9fafb;font-family:monospace;}
.addr-chip:hover,.addr-chip.sel{border-color:#0C74C5;background:#0C74C5;color:#fff;}

/* Patient found card */
.found-card{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px 18px;}
.not-found-card{background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:16px 18px;}

.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;}
.photo-circle{width:76px;height:76px;border-radius:50%;border:2px dashed #d1d5db;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  cursor:pointer;background:#f9fafb;overflow:hidden;transition:.2s;}
.photo-circle:hover{border-color:#0C74C5;}
.photo-circle img{width:100%;height:100%;object-fit:cover;}

.back-btn{display:inline-flex;align-items:center;gap:7px;cursor:pointer;color:#6b7280;font-size:.82rem;font-weight:600;margin-bottom:14px;}
.back-btn:hover{color:#0C74C5;}

@media(max-width:600px){.path-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<main class="doctor-content">

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Add Patient</h5>
    <div style="font-size:.74rem;color:#9ca3af;">Choose how you want to add this patient to your list</div>
  </div>
  <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-sm btn-outline-secondary">
    <i class="fa fa-arrow-left mr-1"></i> Back
  </a>
</div>

<div class="row">
<div class="col-lg-8">

<!-- ════════════ PATH SELECTOR ════════════ -->
<div id="pathSelector">
  <div class="path-grid">

    <!-- Mobile Number -->
    <div class="path-card" onclick="selectPath('M')">
      <div class="path-icon" style="background:#0C74C5;"><i class="fa fa-phone"></i></div>
      <div>
        <div class="path-title">By Mobile Number</div>
        <div class="path-desc">Search by +91 mobile. Finds existing portal users instantly. If not found, create a new record.</div>
      </div>
    </div>

    <!-- Aadhaar → Create ABHA -->
    <div class="path-card" onclick="selectPath('B')">
      <div class="path-badge" style="background:#02c9b8;color:#fff;">ABDM</div>
      <div class="path-icon" style="background:#02c9b8;"><i class="fa fa-plus-circle"></i></div>
      <div>
        <div class="path-title">Create New ABHA</div>
        <div class="path-desc">Patient has no ABHA yet. Enter Aadhaar → OTP → ABHA created instantly on ABDM.</div>
      </div>
    </div>

    <!-- Has ABHA -->
    <div class="path-card" onclick="selectPath('A')">
      <div class="path-badge" style="background:#0C74C5;color:#fff;">RECOMMENDED</div>
      <div class="path-icon" style="background:#3b82f6;"><i class="fa fa-id-card-o"></i></div>
      <div>
        <div class="path-title">Patient Has ABHA</div>
        <div class="path-desc">Patient already has ABHA number / address. Verify with OTP and pull full profile from ABDM.</div>
      </div>
    </div>

    <!-- Manual -->
    <div class="path-card" onclick="selectPath('C')">
      <div class="path-icon" style="background:#e07e18;"><i class="fa fa-pencil"></i></div>
      <div>
        <div class="path-title">Fill Form Manually</div>
        <div class="path-desc">No Aadhaar, no ABHA. Fill basic details manually. ABHA can be linked later.</div>
      </div>
    </div>

  </div>
  <?php if (!ABDM_CONFIGURED): ?>
  <div class="alert alert-warning" style="border-radius:10px;font-size:.82rem;">
    <i class="fa fa-exclamation-triangle mr-2"></i>
    <strong>ABDM not configured.</strong> Set <code>ABDM_CLIENT_ID</code> and <code>ABDM_CLIENT_SECRET</code> in <code>.env</code>.
    Options B and C (ABHA flows) won't work until configured.
  </div>
  <?php endif; ?>
</div>

<!-- ════════════ PATH M — MOBILE SEARCH ════════════ -->
<div id="flowM" style="display:none;">
  <div class="back-btn" onclick="resetPath()"><i class="fa fa-arrow-left"></i> Choose different method</div>

  <div class="wizard" id="wizM">
    <div class="wi active" id="siM1"><div class="wc">1</div><span class="wl">Enter Mobile</span></div>
    <div class="wi"        id="siM2"><div class="wc">2</div><span class="wl">Review</span></div>
    <div class="wi"        id="siM3"><div class="wc">3</div><span class="wl">Done</span></div>
  </div>

  <!-- M-Step 1 -->
  <div class="sbody active" id="stepM1">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-phone mr-2" style="color:#0C74C5;"></i>Search by Mobile Number</div>
      <div class="form-group" style="max-width:300px;">
        <label class="form-label-sm">Patient's Mobile Number</label>
        <div class="input-group">
          <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
          <input type="text" id="mobileSearchInput" class="form-control" placeholder="9876543210" maxlength="10" inputmode="numeric">
        </div>
        <small class="text-muted">We'll check if this patient is already in the portal</small>
      </div>
      <button class="btn btn-primary" id="btnMobileSearch">
        <i class="fa fa-search mr-1"></i> Search
      </button>
      <div id="errM1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
    </div>
  </div>

  <!-- M-Step 2 -->
  <div class="sbody" id="stepM2">
    <div id="mobileResult"></div>
    <div class="d-flex mt-2" style="gap:10px;">
      <button class="btn btn-outline-secondary btn-sm" onclick="goStepM(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
    </div>
  </div>

  <!-- M-Step 3 (success) -->
  <div class="sbody" id="stepM3">
    <div id="stepM3Inner"></div>
  </div>
</div>

<!-- ════════════ PATH A — HAS ABHA ════════════ -->
<div id="flowA" style="display:none;">
  <div class="back-btn" onclick="resetPath()"><i class="fa fa-arrow-left"></i> Choose different method</div>
  <div style="font-size:.82rem;color:#374151;margin-bottom:14px;">
    <i class="fa fa-info-circle mr-1" style="color:#0C74C5;"></i>
    OTP will be sent to the mobile number registered with the patient's ABHA.
  </div>

  <div class="wizard">
    <div class="wi active" id="siA1"><div class="wc">1</div><span class="wl">Enter ABHA</span></div>
    <div class="wi"        id="siA2"><div class="wc">2</div><span class="wl">Verify OTP</span></div>
    <div class="wi"        id="siA3"><div class="wc">3</div><span class="wl">Confirm</span></div>
  </div>

  <div class="sbody active" id="stepA1">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-id-card-o mr-2" style="color:#3b82f6;"></i>Patient's ABHA</div>
      <div class="d-flex mb-3" style="gap:8px;">
        <button class="addr-chip sel" id="typeNumBtn" onclick="setAbhaType('number',this)">ABHA Number</button>
        <button class="addr-chip" id="typeAddrBtn" onclick="setAbhaType('address',this)">ABHA Address</button>
      </div>
      <div class="form-group">
        <label class="form-label-sm" id="aLabel">ABHA Number</label>
        <input type="text" id="aInput" class="form-control" placeholder="XX-XXXX-XXXX-XXXX" maxlength="17"
               style="font-family:monospace;font-size:.98rem;letter-spacing:1px;">
        <small class="text-muted" id="aHint">14 digits — found on patient's ABHA card or app</small>
      </div>
      <button class="btn btn-primary" id="btnASend">
        <i class="fa fa-paper-plane mr-1"></i> Send OTP
      </button>
      <div id="errA1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
    </div>
  </div>

  <div class="sbody" id="stepA2">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-mobile mr-2" style="color:#3b82f6;"></i>OTP from Patient</div>
      <p class="text-center" id="aSentMsg" style="font-size:.86rem;"></p>
      <div class="otp-row">
        <input type="text" class="otp-a" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-a" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-a" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-a" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-a" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-a" maxlength="1" inputmode="numeric">
      </div>
      <div class="d-flex justify-content-between">
        <button class="btn btn-link btn-sm p-0" id="btnAResend" disabled style="font-size:.76rem;">
          <i class="fa fa-refresh mr-1"></i>Resend OTP
        </button>
        <span id="timerA" style="font-size:.74rem;color:#9ca3af;"></span>
      </div>
      <div id="errA2" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
      <div class="d-flex mt-3" style="gap:10px;">
        <button class="btn btn-outline-secondary btn-sm" onclick="goStepA(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
        <button class="btn btn-primary" id="btnAVerify"><i class="fa fa-check mr-1"></i> Verify OTP</button>
      </div>
    </div>
  </div>

  <div class="sbody" id="stepA3">
    <div id="stepA3Inner">
      <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x" style="color:#0C74C5;"></i></div>
    </div>
  </div>
</div>

<!-- ════════════ PATH B — CREATE NEW ABHA ════════════ -->
<div id="flowB" style="display:none;">
  <div class="back-btn" onclick="resetPath()"><i class="fa fa-arrow-left"></i> Choose different method</div>
  <div class="wizard">
    <div class="wi active" id="siB1"><div class="wc">1</div><span class="wl">Aadhaar OTP</span></div>
    <div class="wi"        id="siB2"><div class="wc">2</div><span class="wl">Verify OTP</span></div>
    <div class="wi"        id="siB3"><div class="wc">3</div><span class="wl">ABHA Address</span></div>
    <div class="wi"        id="siB4"><div class="wc">4</div><span class="wl">Done</span></div>
  </div>

  <div class="sbody active" id="stepB1">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-id-card mr-2" style="color:#02c9b8;"></i>Patient's Aadhaar Number</div>
      <div class="alert alert-info" style="font-size:.78rem;border-radius:8px;">
        <i class="fa fa-lock mr-1"></i> Aadhaar is RSA-encrypted before sending to ABDM. It is <strong>never stored</strong> in our database.
      </div>
      <div class="form-group">
        <label class="form-label-sm">Aadhaar Number (12 digits)</label>
        <input type="password" id="bAadhaar" class="form-control" placeholder="•••• •••• ••••" maxlength="12" autocomplete="off"
               style="font-size:1.1rem;letter-spacing:3px;">
        <small class="text-muted">Patient must be present. OTP goes to their Aadhaar-linked mobile.</small>
      </div>
      <div class="form-group">
        <label class="form-label-sm">Mobile <small class="text-muted">(if different from Aadhaar-linked mobile)</small></label>
        <div class="input-group" style="max-width:240px;">
          <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
          <input type="text" id="bMobile" class="form-control" placeholder="9876543210" maxlength="10">
        </div>
      </div>
      <button class="btn btn-success" id="btnBSend">
        <i class="fa fa-paper-plane mr-1"></i> Send OTP to Aadhaar Mobile
      </button>
      <div id="errB1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
    </div>
  </div>

  <div class="sbody" id="stepB2">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-mobile mr-2" style="color:#02c9b8;"></i>Enter Aadhaar OTP</div>
      <p class="text-center" id="bSentMsg" style="font-size:.86rem;"></p>
      <div class="otp-row">
        <input type="text" class="otp-b" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-b" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-b" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-b" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-b" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-b" maxlength="1" inputmode="numeric">
      </div>
      <div class="d-flex justify-content-between">
        <button class="btn btn-link btn-sm p-0" id="btnBResend" disabled style="font-size:.76rem;">
          <i class="fa fa-refresh mr-1"></i>Resend OTP
        </button>
        <span id="timerB" style="font-size:.74rem;color:#9ca3af;"></span>
      </div>
      <div id="errB2" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
      <div class="d-flex mt-3" style="gap:10px;">
        <button class="btn btn-outline-secondary btn-sm" onclick="goStepB(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
        <button class="btn btn-success" id="btnBVerify"><i class="fa fa-check mr-1"></i> Verify OTP</button>
      </div>
    </div>
  </div>

  <div class="sbody" id="stepB3">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-at mr-2" style="color:#02c9b8;"></i>Choose ABHA Address</div>
      <p style="font-size:.82rem;color:#374151;">ABHA created! Pick a preferred address for this patient:</p>
      <div id="bSuggestions" class="mb-3"></div>
      <div class="form-group">
        <label class="form-label-sm">Custom address</label>
        <div class="input-group" style="max-width:280px;">
          <input type="text" id="bCustomAddr" class="form-control form-control-sm" placeholder="firstname.lastname">
          <div class="input-group-append"><span class="input-group-text">@abdm</span></div>
        </div>
      </div>
      <div id="errB3" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
      <div class="d-flex mt-3" style="gap:10px;">
        <button class="btn btn-outline-secondary btn-sm" onclick="goStepB(2)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
        <button class="btn btn-success" id="btnBConfirm"><i class="fa fa-check mr-1"></i> Confirm &amp; Save</button>
      </div>
    </div>
  </div>

  <div class="sbody" id="stepB4">
    <div id="stepB4Inner">
      <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x" style="color:#02c9b8;"></i></div>
    </div>
  </div>
</div>

<!-- ════════════ PATH C — MANUAL FORM ════════════ -->
<div id="flowC" style="display:none;">
  <div class="back-btn" onclick="resetPath()"><i class="fa fa-arrow-left"></i> Choose different method</div>
  <form id="manualForm">
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-pencil mr-2" style="color:#e07e18;"></i>Patient Details</div>
      <div class="d-flex" style="gap:18px;flex-wrap:wrap;align-items:flex-start;">
        <div>
          <label class="form-label-sm d-block">Photo</label>
          <div class="photo-circle" onclick="document.getElementById('photoInput').click()">
            <i class="fa fa-camera" style="font-size:1.1rem;color:#9ca3af;"></i>
            <span style="font-size:.6rem;color:#9ca3af;margin-top:2px;">Upload</span>
          </div>
          <input type="file" id="photoInput" accept="image/*" style="display:none;">
        </div>
        <div style="flex:1;min-width:240px;">
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label-sm">First Name <span class="text-danger">*</span></label><input type="text" name="first_name" id="f_fn" class="form-control form-control-sm" required></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label-sm">Middle Name</label><input type="text" name="middle_name" class="form-control form-control-sm"></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label-sm">Last Name</label><input type="text" name="last_name" class="form-control form-control-sm"></div></div>
          </div>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group">
                <label class="form-label-sm">Mobile <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
                  <input type="text" name="mobile" id="f_mob" class="form-control" placeholder="9876543210" maxlength="10" required>
                </div>
              </div>
            </div>
            <div class="col-md-7">
              <div class="form-group">
                <label class="form-label-sm">Email</label>
                <input type="email" name="email" id="f_email" class="form-control form-control-sm">
                <div class="form-check mt-1">
                  <input type="checkbox" class="form-check-input" id="noEmail">
                  <label for="noEmail" class="form-check-label" style="font-size:.74rem;color:#6b7280;">No email</label>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group"><label class="form-label-sm">Gender</label><select name="gender" class="form-control form-control-sm"><option value="">--</option><option>Male</option><option>Female</option><option>Other</option></select></div></div>
            <div class="col-md-3"><div class="form-group"><label class="form-label-sm">DOB</label><input type="text" name="dob" id="f_dob" class="form-control form-control-sm" placeholder="dd/mm/yyyy" maxlength="10"></div></div>
            <div class="col-md-2"><div class="form-group"><label class="form-label-sm">Age</label><input type="number" id="f_age" class="form-control form-control-sm" readonly style="background:#f9fafb;"></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label-sm">Blood Group</label><select name="blood_group" class="form-control form-control-sm"><option value="">--</option><?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option><?= $bg ?></option><?php endforeach; ?></select></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-map-marker mr-2" style="color:#e07e18;"></i>Address <span class="text-muted font-weight-normal" style="font-size:.72rem;">(optional)</span></div>
      <div class="row">
        <div class="col-md-4"><div class="form-group"><label class="form-label-sm">Pin Code</label><input type="text" name="pincode" class="form-control form-control-sm" maxlength="6"></div></div>
        <div class="col-md-4"><div class="form-group"><label class="form-label-sm">City</label><input type="text" name="city" class="form-control form-control-sm"></div></div>
        <div class="col-md-4"><div class="form-group"><label class="form-label-sm">State</label><input type="text" name="state" class="form-control form-control-sm"></div></div>
      </div>
      <div class="form-group"><label class="form-label-sm">Full Address</label><textarea name="address" class="form-control form-control-sm" rows="2"></textarea></div>
    </div>

    <div class="d-flex justify-content-end" style="gap:10px;">
      <button type="button" class="btn btn-outline-secondary" onclick="resetPath()">Cancel</button>
      <button type="submit" class="btn btn-primary" id="btnCreate">
        <i class="fa fa-user-plus mr-1"></i> Create Patient
      </button>
    </div>
  </form>
</div>

</div><!-- /col-lg-8 -->

<!-- RIGHT PANEL -->
<div class="col-lg-4">
  <div class="cp-card" style="position:sticky;top:80px;">
    <div class="cp-title"><i class="fa fa-question-circle mr-2"></i>Which Option?</div>

    <div class="p-3 mb-2" style="background:#eff6ff;border-radius:10px;">
      <div style="font-weight:700;color:#0C74C5;font-size:.82rem;margin-bottom:3px;"><i class="fa fa-phone mr-1"></i> By Mobile Number</div>
      <div style="font-size:.75rem;color:#6b7280;">Best for existing portal patients. Searches immediately — no ABDM needed.</div>
    </div>

    <div class="p-3 mb-2" style="background:#f0fdfa;border-radius:10px;">
      <div style="font-weight:700;color:#02c9b8;font-size:.82rem;margin-bottom:3px;"><i class="fa fa-plus-circle mr-1"></i> Create New ABHA</div>
      <div style="font-size:.75rem;color:#6b7280;">New patient, no ABHA. Enter Aadhaar → OTP → instant ABHA ID from ABDM.</div>
    </div>

    <div class="p-3 mb-2" style="background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;">
      <div style="font-weight:700;color:#3b82f6;font-size:.82rem;margin-bottom:3px;"><i class="fa fa-id-card-o mr-1"></i> Patient Has ABHA</div>
      <div style="font-size:.75rem;color:#6b7280;">Patient knows their ABHA number or address. Fetch full ABDM profile with OTP.</div>
    </div>

    <div class="p-3 mb-2" style="background:#fff7ed;border-radius:10px;">
      <div style="font-weight:700;color:#e07e18;font-size:.82rem;margin-bottom:3px;"><i class="fa fa-pencil mr-1"></i> Fill Manually</div>
      <div style="font-size:.75rem;color:#6b7280;">No Aadhaar, no ABHA. Just fill the form. ABHA can be added later.</div>
    </div>

    <hr>
    <div style="font-size:.72rem;color:#9ca3af;line-height:1.7;">
      <i class="fa fa-lock mr-1"></i> Aadhaar is RSA-encrypted and never stored.<br>
      <i class="fa fa-file-text-o mr-1"></i> All ABDM calls are audit-logged per NHA guidelines.
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

/* ── helpers ── */
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function showErr(id,msg){const e=document.getElementById(id);if(e){e.textContent=msg;e.style.display='block';}}
function hideErr(id){const e=document.getElementById(id);if(e)e.style.display='none';}
function toast(msg,type){
  const t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:.84rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);'+(type==='success'?'background:#16a34a;color:#fff;':'background:#dc2626;color:#fff;');
  t.textContent=msg;document.body.appendChild(t);setTimeout(()=>t.remove(),3500);
}
function timer(timerId,btnId,sec){
  const tel=document.getElementById(timerId),btn=document.getElementById(btnId);
  btn.disabled=true; let r=sec;
  const iv=setInterval(()=>{
    const m=String(Math.floor(r/60)).padStart(2,'0'),s=String(r%60).padStart(2,'0');
    tel.textContent='Resend in '+m+':'+s;
    if(--r<0){clearInterval(iv);tel.textContent='';btn.disabled=false;}
  },1000);return iv;
}
function wireOtp(sel){
  const d=document.querySelectorAll(sel);
  d.forEach((inp,i)=>{
    inp.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'');if(this.value&&i<d.length-1)d[i+1].focus();});
    inp.addEventListener('keydown',function(e){if(e.key==='Backspace'&&!this.value&&i>0)d[i-1].focus();});
    inp.addEventListener('paste',function(e){
      const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
      if(p.length>=6){e.preventDefault();p.substr(0,6).split('').forEach((c,j)=>{if(d[j])d[j].value=c;});d[Math.min(5,p.length-1)].focus();}
    });
  });
  return ()=>Array.from(d).map(i=>i.value).join('');
}
function goWiz(prefix,n,total){
  for(let i=1;i<=total;i++){
    const b=document.getElementById('sbody_'+prefix+i||'step'+prefix+i);
    if(b)b.classList.remove('active');
    const si=document.getElementById('si'+prefix+i);
    if(si){si.classList.remove('active','done');
      if(i<n){si.classList.add('done');si.querySelector('.wc').innerHTML='<i class="fa fa-check"></i>';}
      if(i===n){si.classList.add('active');si.querySelector('.wc').innerHTML=String(n);}
    }
  }
  document.querySelectorAll('#flow'+prefix+' .sbody').forEach((s,i)=>{
    s.classList.remove('active');
    if(i===n-1) s.classList.add('active');
  });
}

/* ── path selection ── */
window.selectPath=function(p){
  document.getElementById('pathSelector').style.display='none';
  ['M','A','B','C'].forEach(x=>document.getElementById('flow'+x).style.display='none');
  document.getElementById('flow'+p).style.display='block';
};
window.resetPath=function(){
  document.getElementById('pathSelector').style.display='block';
  ['M','A','B','C'].forEach(x=>document.getElementById('flow'+x).style.display='none');
};

/* ════════════ PATH M — MOBILE ════════════ */
function goStepM(n){goWiz('M',n,3);}

document.getElementById('mobileSearchInput').addEventListener('keydown',function(e){
  if(e.key==='Enter') document.getElementById('btnMobileSearch').click();
});
document.getElementById('btnMobileSearch').addEventListener('click',function(){
  const mobile=document.getElementById('mobileSearchInput').value.replace(/\D/g,'');
  if(mobile.length!==10){showErr('errM1','Please enter a valid 10-digit mobile number');return;}
  hideErr('errM1');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Searching…';

  fetch(BASE+'doctor/api/patient-search-mobile.php?mobile='+mobile)
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-search mr-1"></i> Search';
    if(!data.success){showErr('errM1',data.error||'Error');return;}
    const wrap=document.getElementById('mobileResult');

    if(data.found){
      const p=data.patient;
      const abhaLine=p.abha_number
        ?'<div style="font-size:.74rem;color:#15803d;font-family:monospace;margin-top:2px;"><i class="fa fa-id-card-o mr-1"></i>'+esc(p.abha_number)+'</div>'
        :'<div style="font-size:.74rem;color:#9ca3af;margin-top:2px;"><i class="fa fa-times-circle mr-1"></i>No ABHA linked</div>';
      const init=(p.name||'P').charAt(0).toUpperCase();
      wrap.innerHTML='<div class="found-card">'
        +'<div class="d-flex align-items-center" style="gap:14px;">'
        +'<div style="width:48px;height:48px;border-radius:50%;background:#0C74C5;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;flex-shrink:0;">'+init+'</div>'
        +'<div style="flex:1;">'
        +'<div style="font-weight:700;font-size:.95rem;color:#1f2937;">'+esc(p.name||'—')+'</div>'
        +'<div style="font-size:.76rem;color:#6b7280;">+91 '+esc(p.mobile)+(p.email?' · '+esc(p.email):'')+'</div>'
        +abhaLine
        +'</div></div>'
        +(p.already_linked
          ?'<div class="alert alert-success mt-2 mb-0" style="font-size:.78rem;border-radius:8px;"><i class="fa fa-check-circle mr-1"></i>Already in your patient list.</div>'
          :'<div class="alert alert-info mt-2 mb-0" style="font-size:.78rem;border-radius:8px;"><i class="fa fa-info-circle mr-1"></i>Found in portal — will be linked to your panel.</div>'
          +'<div class="mt-2"><button class="btn btn-success btn-sm" onclick="linkExisting('+p.id+')"><i class="fa fa-link mr-1"></i>Add to My Patients</button></div>'
        )
        +'</div>';
    } else {
      wrap.innerHTML='<div class="not-found-card">'
        +'<div style="font-weight:700;color:#9a3412;margin-bottom:6px;"><i class="fa fa-exclamation-circle mr-1"></i>Not found in portal</div>'
        +'<div style="font-size:.8rem;color:#c2410c;margin-bottom:12px;">No patient with mobile <strong>+91 '+esc(mobile)+'</strong> exists in the portal yet.</div>'
        +'<div style="font-size:.78rem;font-weight:600;color:#374151;margin-bottom:8px;">What would you like to do?</div>'
        +'<div class="d-flex flex-wrap" style="gap:8px;">'
        +'<button class="btn btn-success btn-sm" onclick="prefillManual(\''+esc(mobile)+'\')">'
        +'<i class="fa fa-pencil mr-1"></i>Create manually with this mobile</button>'
        +'<button class="btn btn-outline-secondary btn-sm" onclick="selectPath(\'B\')">'
        +'<i class="fa fa-plus-circle mr-1"></i>Create ABHA via Aadhaar</button>'
        +'</div></div>';
    }
    goStepM(2);
  }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-search mr-1"></i> Search';showErr('errM1','Network error: '+e.message);});
});

window.linkExisting=function(patientId){
  fetch(BASE+'doctor/api/patient-add.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({mode:'portal',patient_id:patientId})})
  .then(r=>r.json()).then(data=>{
    if(data.success){toast('Patient added to your list!','success');setTimeout(()=>window.location=BASE+'doctor/my-patients.php',1500);}
    else toast(data.error||'Failed','error');
  });
};
window.prefillManual=function(mobile){
  selectPath('C');
  setTimeout(()=>{document.getElementById('f_mob').value=mobile;document.getElementById('f_fn').focus();},100);
};

/* ════════════ PATH A — HAS ABHA ════════════ */
let aTxnId='', aType='number';
const getOtpA=wireOtp('.otp-a');

window.setAbhaType=function(type,btn){
  document.getElementById('typeNumBtn').classList.remove('sel');
  document.getElementById('typeAddrBtn').classList.remove('sel');
  btn.classList.add('sel');
  aType=type;
  const inp=document.getElementById('aInput');
  if(type==='number'){
    document.getElementById('aLabel').textContent='ABHA Number';
    inp.placeholder='XX-XXXX-XXXX-XXXX';inp.maxLength=17;inp.style.letterSpacing='1px';
    document.getElementById('aHint').textContent='14 digits on patient\'s ABHA card';
  } else {
    document.getElementById('aLabel').textContent='ABHA Address';
    inp.placeholder='name@abdm';inp.maxLength=60;inp.style.letterSpacing='normal';
    document.getElementById('aHint').textContent='e.g. john.doe@abdm';
  }
  inp.value='';
};

document.getElementById('aInput').addEventListener('input',function(){
  if(aType!=='number')return;
  let d=this.value.replace(/\D/g,'').substr(0,14),o=d;
  if(d.length>2)o=d.substr(0,2)+'-'+d.substr(2);
  if(d.length>6)o=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6);
  if(d.length>10)o=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6,4)+'-'+d.substr(10);
  this.value=o;
});

function goStepA(n){
  ['stepA1','stepA2','stepA3'].forEach((id,i)=>{
    const el=document.getElementById(id);if(el)el.classList.toggle('active',i===n-1);
  });
  ['siA1','siA2','siA3'].forEach((id,i)=>{
    const el=document.getElementById(id);if(!el)return;
    el.classList.remove('active','done');
    if(i+1<n){el.classList.add('done');el.querySelector('.wc').innerHTML='<i class="fa fa-check"></i>';}
    if(i+1===n){el.classList.add('active');el.querySelector('.wc').innerHTML=String(n);}
  });
}
window.goStepA=goStepA;

document.getElementById('btnASend').addEventListener('click',function(){
  const val=document.getElementById('aInput').value.trim();
  if(!val){showErr('errA1','Enter ABHA '+(aType==='number'?'number':'address'));return;}
  hideErr('errA1');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';
  fetch(BASE+'doctor/api/abha-otp-send.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({abha_input:val,type:aType})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP';
    if(!data.success){showErr('errA1',data.error||'Failed to send OTP');return;}
    aTxnId=data.txnId;
    document.getElementById('aSentMsg').innerHTML='<i class="fa fa-check-circle" style="color:#16a34a;margin-right:5px;"></i>'+esc(data.message);
    document.querySelectorAll('.otp-a').forEach(i=>i.value='');
    goStepA(2);timer('timerA','btnAResend',300);
  }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP';showErr('errA1','Network error');});
});
document.getElementById('btnAResend').addEventListener('click',()=>document.getElementById('btnASend').click());

document.getElementById('btnAVerify').addEventListener('click',function(){
  const otp=getOtpA();
  if(otp.length<6){showErr('errA2','Enter the complete 6-digit OTP');return;}
  if(!aTxnId){showErr('errA2','Session expired — resend OTP');return;}
  hideErr('errA2');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
  goStepA(3);
  fetch(BASE+'doctor/api/abha-otp-verify.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({txnId:aTxnId,otp:otp})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    const inner=document.getElementById('stepA3Inner');
    if(!data.success){
      inner.innerHTML='<div class="cp-card text-center py-4"><i class="fa fa-times-circle fa-3x text-danger"></i><div class="mt-2 font-weight-bold text-danger">'+esc(data.error||'Failed')+'</div><button class="btn btn-outline-secondary mt-3 btn-sm" onclick="goStepA(1)">Try Again</button></div>';
      return;
    }
    const p=data.profile,init=(p.name||'P').charAt(0).toUpperCase();
    inner.innerHTML='<div class="cp-card"><div class="cp-title"><i class="fa fa-check-circle mr-2" style="color:#16a34a;"></i>Profile Fetched from ABDM</div>'
      +'<div class="found-card"><div class="d-flex align-items-center" style="gap:14px;">'
      +'<div style="width:48px;height:48px;border-radius:50%;background:#0C74C5;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;">'+init+'</div>'
      +'<div><div style="font-weight:700;font-size:.95rem;">'+esc(p.name||'—')+'</div>'
      +'<div style="font-size:.74rem;color:#6b7280;">'+(p.mobile?'+91 '+esc(p.mobile):'')+(p.gender?' · '+esc(p.gender):'')+'</div>'
      +'<div style="font-size:.74rem;color:#15803d;font-family:monospace;margin-top:2px;">'+esc(p.abha_number||'')+'</div>'
      +'</div></div></div>'
      +'<div class="alert '+(data.is_new?'alert-success':'alert-info')+' mt-2" style="font-size:.78rem;border-radius:8px;">'
      +(data.is_new?'<i class="fa fa-user-plus mr-1"></i>New patient — will be created.':'<i class="fa fa-link mr-1"></i>Existing patient — ABHA data will be updated.')
      +'</div>'
      +'<div class="d-flex" style="gap:10px;">'
      +'<button class="btn btn-outline-secondary btn-sm" onclick="goStepA(1)">Cancel</button>'
      +'<button class="btn btn-success" onclick="finalRedirect()">'
      +'<i class="fa fa-check mr-1"></i>'+(data.is_new?'Create & Add':'Update & Add')+'</button>'
      +'</div></div>';
    window._pendingData=data;
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    document.getElementById('stepA3Inner').innerHTML='<div class="cp-card text-center py-3 text-danger">Network error<br><button class="btn btn-sm btn-outline-secondary mt-2" onclick="goStepA(2)">Back</button></div>';
  });
});
window.finalRedirect=function(){toast('Patient added successfully!','success');setTimeout(()=>window.location=BASE+'doctor/my-patients.php',1500);};

/* ════════════ PATH B — CREATE ABHA ════════════ */
let bTxnId='',bXToken='',bProfile={},bIsNew=true,bSelAddr='';
const getOtpB=wireOtp('.otp-b');

function goStepB(n){
  ['stepB1','stepB2','stepB3','stepB4'].forEach((id,i)=>{
    const el=document.getElementById(id);if(el)el.classList.toggle('active',i===n-1);
  });
  ['siB1','siB2','siB3','siB4'].forEach((id,i)=>{
    const el=document.getElementById(id);if(!el)return;
    el.classList.remove('active','done');
    if(i+1<n){el.classList.add('done');el.querySelector('.wc').innerHTML='<i class="fa fa-check"></i>';}
    if(i+1===n){el.classList.add('active');el.querySelector('.wc').innerHTML=String(n);}
  });
}
window.goStepB=goStepB;

document.getElementById('btnBSend').addEventListener('click',function(){
  const aadhaar=document.getElementById('bAadhaar').value.replace(/\D/g,'');
  if(aadhaar.length!==12){showErr('errB1','Aadhaar must be 12 digits');return;}
  hideErr('errB1');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';
  const mob=document.getElementById('bMobile').value.replace(/\D/g,'');
  fetch(BASE+'doctor/api/abha-enrol-otp.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({aadhaar:aadhaar,mobile:mob||''})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Aadhaar Mobile';
    if(!data.success){showErr('errB1',data.error||'Failed');return;}
    bTxnId=data.txnId;
    document.getElementById('bSentMsg').innerHTML='<i class="fa fa-check-circle" style="color:#16a34a;margin-right:5px;"></i>'+esc(data.message);
    document.querySelectorAll('.otp-b').forEach(i=>i.value='');
    goStepB(2);timer('timerB','btnBResend',300);
  }).catch(e=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Aadhaar Mobile';showErr('errB1','Network error');});
});
document.getElementById('btnBResend').addEventListener('click',()=>{goStepB(1);setTimeout(()=>document.getElementById('btnBSend').click(),100);});

document.getElementById('btnBVerify').addEventListener('click',function(){
  const otp=getOtpB();
  if(otp.length<6){showErr('errB2','Enter complete OTP');return;}
  hideErr('errB2');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
  const mob=document.getElementById('bMobile').value.replace(/\D/g,'');
  fetch(BASE+'doctor/api/abha-enrol-verify.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({txnId:bTxnId,otp:otp,mobile:mob||''})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    if(!data.success){showErr('errB2',data.error||'Failed');return;}
    bTxnId=data.txnId;bXToken=data.xToken;bProfile=data.profile;bIsNew=data.is_new_abha;
    const suggs=data.suggestions||[];
    const wrap=document.getElementById('bSuggestions');
    if(suggs.length){
      wrap.innerHTML='<div style="font-size:.76rem;font-weight:600;color:#374151;margin-bottom:6px;">ABDM Suggestions:</div>'
        +suggs.map(a=>'<span class="addr-chip" onclick="selAddr(this,\''+esc(a)+'\')">'+esc(a)+'</span>').join('');
    } else {
      wrap.innerHTML='<div style="font-size:.76rem;color:#9ca3af;">Enter a custom address below.</div>';
    }
    goStepB(3);
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';showErr('errB2','Network error');});
});

window.selAddr=function(chip,addr){
  document.querySelectorAll('.addr-chip').forEach(c=>c.classList.remove('sel'));
  chip.classList.add('sel');bSelAddr=addr;
  document.getElementById('bCustomAddr').value='';
};

document.getElementById('btnBConfirm').addEventListener('click',function(){
  const chosen=document.getElementById('bCustomAddr').value.trim()||bSelAddr;
  if(!chosen){showErr('errB3','Please pick or enter an ABHA address');return;}
  hideErr('errB3');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Saving…';
  goStepB(4);
  fetch(BASE+'doctor/api/abha-enrol-confirm.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({txnId:bTxnId,xToken:bXToken,chosen_address:chosen,profile:bProfile,is_new_abha:bIsNew})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Confirm & Save';
    const inner=document.getElementById('stepB4Inner');
    if(!data.success){
      inner.innerHTML='<div class="cp-card text-center py-4"><i class="fa fa-times-circle fa-3x text-danger"></i><div class="mt-2 font-weight-bold text-danger">'+esc(data.error||'Failed')+'</div><button class="btn btn-sm btn-outline-secondary mt-3" onclick="goStepB(3)">Back</button></div>';
      return;
    }
    inner.innerHTML='<div class="cp-card text-center py-3">'
      +'<i class="fa fa-check-circle fa-3x" style="color:#16a34a;"></i>'
      +'<div class="mt-3 font-weight-bold" style="font-size:1rem;color:#166534;">ABHA Created &amp; Patient Added!</div>'
      +'<div class="p-3 mt-3 d-inline-block" style="background:#f0fdf4;border-radius:10px;">'
      +'<div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;font-weight:700;">ABHA Number</div>'
      +'<div style="font-family:monospace;font-size:.95rem;color:#16a34a;font-weight:700;">'+esc(data.abha_number||'')+'</div>'
      +'<div style="font-size:.74rem;color:#6b7280;margin-top:2px;">'+esc(data.abha_address||'')+'</div>'
      +'</div>'
      +'<div class="mt-3"><a href="'+BASE+'doctor/my-patients.php" class="btn btn-success"><i class="fa fa-users mr-1"></i> Go to My Patients</a></div>'
      +'</div>';
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Confirm & Save';
    document.getElementById('stepB4Inner').innerHTML='<div class="cp-card text-center py-3 text-danger">Network error<br><button class="btn btn-sm btn-outline-secondary mt-2" onclick="goStepB(3)">Back</button></div>';
  });
});

/* ════════════ PATH C — MANUAL ════════════ */
document.getElementById('photoInput').addEventListener('change',function(){
  if(!this.files[0])return;
  const r=new FileReader();
  r.onload=e=>{const c=document.querySelector('.photo-circle');c.innerHTML='<img src="'+e.target.result+'" alt="">';};
  r.readAsDataURL(this.files[0]);
});
document.getElementById('noEmail').addEventListener('change',function(){
  document.getElementById('f_email').disabled=this.checked;
  if(this.checked)document.getElementById('f_email').value='';
});
document.getElementById('f_dob').addEventListener('input',function(){
  const p=this.value.split('/');if(p.length!==3||p[2].length<4)return;
  const d=new Date(p[2],p[1]-1,p[0]);if(isNaN(d))return;
  let age=new Date().getFullYear()-d.getFullYear();
  const t=new Date();
  if(t.getMonth()<d.getMonth()||(t.getMonth()===d.getMonth()&&t.getDate()<d.getDate()))age--;
  if(age>=0&&age<150)document.getElementById('f_age').value=age;
});

document.getElementById('manualForm').addEventListener('submit',function(e){
  e.preventDefault();
  const btn=document.getElementById('btnCreate');
  btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Creating…';
  const payload={
    first_name: document.getElementById('f_fn').value.trim(),
    middle_name:document.querySelector('[name="middle_name"]').value.trim(),
    last_name:  document.querySelector('[name="last_name"]').value.trim(),
    email:      document.getElementById('f_email').value.trim(),
    no_email:   document.getElementById('noEmail').checked,
    mobile:     document.getElementById('f_mob').value.trim(),
    gender:     document.querySelector('[name="gender"]').value,
    dob:        document.getElementById('f_dob').value.trim(),
    blood_group:document.querySelector('[name="blood_group"]').value,
    pincode:    document.querySelector('[name="pincode"]').value.trim(),
    city:       document.querySelector('[name="city"]').value.trim(),
    state:      document.querySelector('[name="state"]').value.trim(),
    address:    document.querySelector('[name="address"]').value.trim(),
    abha_number:'',abha_address:'',abha_verified:0,
  };
  fetch(BASE+'doctor/api/create-patient-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
  .then(r=>r.json()).then(data=>{
    if(data.success){toast(data.message||'Patient created!','success');setTimeout(()=>window.location=BASE+'doctor/my-patients.php',1500);}
    else{btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';toast(data.error||'Failed','error');}
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';toast('Network error','error');});
});

})();
</script>
</body>
</html>
