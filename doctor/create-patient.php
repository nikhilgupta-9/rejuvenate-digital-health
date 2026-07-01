<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';

$payload   = doctor_jwt_guard();
$doctor_id = (int)$payload['doctor_id'];

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
/* ── page-level overrides ── */
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:22px;}
.cp-card-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}
.abha-search-wrap{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
.abha-search-wrap .form-group{margin-bottom:0;flex:1;min-width:200px;}
.abha-result-card{display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px 20px;margin-top:14px;}
.abha-result-card.error-state{background:#fff7ed;border-color:#fed7aa;}
.abha-avatar{width:56px;height:56px;border-radius:50%;background:#0C74C5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;flex-shrink:0;overflow:hidden;}
.abha-avatar img{width:100%;height:100%;object-fit:cover;}
.badge-verified{background:#dcfce7;color:#16a34a;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;}
.badge-unverified{background:#fef9c3;color:#ca8a04;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;}
.step-tabs{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:22px;}
.step-tab{padding:10px 22px;font-size:.84rem;font-weight:600;color:#9ca3af;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:.15s;}
.step-tab.active{color:#0C74C5;border-bottom-color:#0C74C5;}
.step-tab i{margin-right:7px;}
.tab-pane-inner{display:none;}
.tab-pane-inner.active{display:block;}
.abha-no-email-toggle{display:none;}
.photo-upload-circle{width:90px;height:90px;border-radius:50%;border:2px dashed #d1d5db;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;background:#f9fafb;transition:.2s;position:relative;overflow:hidden;}
.photo-upload-circle:hover{border-color:#0C74C5;background:#eff6ff;}
.photo-upload-circle img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;}
.photo-upload-circle span{font-size:.65rem;color:#9ca3af;margin-top:4px;text-align:center;line-height:1.3;}
.abha-type-switch{display:flex;gap:8px;margin-bottom:6px;}
.type-btn{padding:5px 14px;border-radius:20px;border:1px solid #d1d5db;background:#f9fafb;font-size:.78rem;font-weight:600;cursor:pointer;transition:.15s;color:#6b7280;}
.type-btn.active{background:#0C74C5;color:#fff;border-color:#0C74C5;}
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;}
.abha-hint{font-size:.7rem;color:#9ca3af;margin-top:3px;}
</style>
</head>
<body>
<main class="doctor-content">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Create New Patient</h5>
      <div style="font-size:.75rem;color:#9ca3af;">Add a patient to your list — via ABHA or manually</div>
    </div>
    <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-sm btn-outline-secondary">
      <i class="fa fa-arrow-left mr-1"></i> Back to Patients
    </a>
  </div>

  <div class="row">
    <!-- LEFT: ABHA Search + Form -->
    <div class="col-lg-8">

      <!-- Step Tabs -->
      <div class="step-tabs">
        <div class="step-tab active" data-tab="abha"><i class="fa fa-search"></i>Search by ABHA</div>
        <div class="step-tab" data-tab="manual"><i class="fa fa-user-plus"></i>Create Manually</div>
      </div>

      <!-- ── TAB 1: ABHA Search ── -->
      <div class="tab-pane-inner active" id="tab-abha">
        <div class="cp-card">
          <div class="cp-card-title"><i class="fa fa-id-card-o mr-2" style="color:#0C74C5;"></i>Search Patient by ABHA</div>

          <!-- Search type toggle -->
          <div class="abha-type-switch mb-3">
            <button class="type-btn active" data-stype="address">ABHA Address</button>
            <button class="type-btn" data-stype="number">ABHA Number</button>
          </div>

          <div class="abha-search-wrap">
            <div class="form-group">
              <label class="form-label-sm" id="searchLabel">ABHA Address</label>
              <div class="input-group">
                <input type="text" id="abhaSearchInput" class="form-control"
                       placeholder="name@abdm" autocomplete="off">
                <div class="input-group-append">
                  <span class="input-group-text" style="background:#e9ecef;color:#6b7280;font-size:.8rem;">@abdm</span>
                </div>
              </div>
              <div class="abha-hint" id="searchHint">Enter ABHA address (e.g. john.doe@abdm)</div>
            </div>
            <div class="form-group" style="flex:0;">
              <label class="form-label-sm">&nbsp;</label>
              <button class="btn btn-primary" id="btnAbhaSearch" style="min-width:90px;">
                <i class="fa fa-search mr-1"></i> Search
              </button>
            </div>
          </div>

          <!-- Result -->
          <div class="abha-result-card" id="abhaResultCard">
            <div id="abhaResultInner"></div>
          </div>
        </div>

        <!-- ABDM not configured notice -->
        <?php if (!ABDM_CONFIGURED): ?>
        <div class="alert alert-warning" style="border-radius:10px;font-size:.84rem;">
          <i class="fa fa-exclamation-triangle mr-2"></i>
          <strong>ABDM not configured.</strong> Set <code>ABDM_CLIENT_ID</code> and <code>ABDM_CLIENT_SECRET</code>
          in your <code>.env</code> file to enable live ABHA lookup.
          You can still create patients manually using the <strong>Create Manually</strong> tab.
        </div>
        <?php endif; ?>
      </div>

      <!-- ── TAB 2: Manual Form ── -->
      <div class="tab-pane-inner" id="tab-manual">
        <form id="createPatientForm">
          <!-- Photo + ABHA at top -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-user-circle-o mr-2" style="color:#0C74C5;"></i>Patient Identity</div>
            <div class="d-flex align-items-flex-start" style="gap:20px;flex-wrap:wrap;">
              <!-- Photo -->
              <div>
                <label class="form-label-sm d-block">Photo</label>
                <div class="photo-upload-circle" id="photoCircle" onclick="document.getElementById('photoInput').click()">
                  <i class="fa fa-camera" style="font-size:1.3rem;color:#9ca3af;"></i>
                  <span>Upload</span>
                  <img id="photoPreview" src="" alt="" style="display:none;">
                </div>
                <input type="file" id="photoInput" accept="image/*" style="display:none;">
              </div>
              <!-- ABHA IDs -->
              <div style="flex:1;min-width:260px;">
                <div class="row">
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label class="form-label-sm">ABHA Address</label>
                      <div class="input-group input-group-sm">
                        <input type="text" name="abha_address" id="f_abha_address" class="form-control" placeholder="name">
                        <div class="input-group-append"><span class="input-group-text">@abdm</span></div>
                      </div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label class="form-label-sm">ABHA Number</label>
                      <input type="text" name="abha_number" id="f_abha_number" class="form-control form-control-sm"
                             placeholder="XX-XXXX-XXXX-XXXX" maxlength="17">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="abhaVerifiedChk" name="abha_verified">
                      <label class="form-check-label" for="abhaVerifiedChk" style="font-size:.78rem;">
                        ABHA verified (fetched from ABDM)
                      </label>
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
                  <input type="email" name="email" id="f_email" class="form-control form-control-sm" placeholder="patient@email.com">
                  <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="noEmailChk">
                    <label class="form-check-label" for="noEmailChk" style="font-size:.76rem;color:#6b7280;">
                      Do not have an email
                    </label>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Mobile <span class="text-danger">*</span></label>
                  <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
                    <input type="text" name="mobile" id="f_mobile" class="form-control"
                           placeholder="9876543210" maxlength="10" required>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Blood Group</label>
                  <select name="blood_group" id="f_blood_group" class="form-control form-control-sm">
                    <option value="">-- Select --</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                    <option value="<?= $bg ?>"><?= $bg ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Gender</label>
                  <select name="gender" id="f_gender" class="form-control form-control-sm">
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="form-label-sm">Date of Birth</label>
                  <input type="text" name="dob" id="f_dob" class="form-control form-control-sm"
                         placeholder="dd/mm/yyyy" maxlength="10">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="form-label-sm">Age</label>
                  <input type="number" id="f_age" class="form-control form-control-sm" placeholder="--" readonly
                         style="background:#f9fafb;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">Aadhaar (last 4 digits, optional)</label>
                  <input type="text" name="aadhaar_last4" id="f_aadhaar" class="form-control form-control-sm"
                         placeholder="XXXX" maxlength="4">
                </div>
              </div>
            </div>
          </div>

          <!-- Address -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-map-marker mr-2" style="color:#0C74C5;"></i>Address Details</div>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label-sm">Pin Code</label>
                  <input type="text" name="pincode" id="f_pincode" class="form-control form-control-sm"
                         placeholder="110001" maxlength="6">
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
              <textarea name="address" id="f_address" class="form-control form-control-sm" rows="2"></textarea>
            </div>
          </div>

          <!-- Other -->
          <div class="cp-card">
            <div class="cp-card-title"><i class="fa fa-info-circle mr-2" style="color:#0C74C5;"></i>Additional Info</div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label-sm">Reference Doctor</label>
                  <input type="text" name="reference_doctor" id="f_ref_doctor" class="form-control form-control-sm"
                         placeholder="Referred by...">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label-sm">Unique ID</label>
                  <input type="text" id="f_unique_id" class="form-control form-control-sm" readonly
                         style="background:#f9fafb;font-family:monospace;font-size:.8rem;">
                </div>
              </div>
            </div>
          </div>

          <!-- Submit -->
          <div class="d-flex justify-content-end" style="gap:10px;">
            <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" id="btnCreate">
              <i class="fa fa-user-plus mr-1"></i> Create Patient
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- RIGHT: Info Panel -->
    <div class="col-lg-4">
      <div class="cp-card" style="position:sticky;top:80px;">
        <div class="cp-card-title"><i class="fa fa-info-circle mr-2" style="color:#0C74C5;"></i>How it Works</div>

        <div style="font-size:.82rem;color:#374151;line-height:1.7;">
          <div class="mb-3">
            <div style="font-weight:700;color:#0C74C5;margin-bottom:4px;">
              <i class="fa fa-search mr-1"></i> Via ABHA (Recommended)
            </div>
            <ol style="padding-left:18px;margin:0;color:#6b7280;font-size:.78rem;">
              <li>Enter patient's ABHA address or 14-digit number</li>
              <li>Click Search — profile auto-fetched from ABDM</li>
              <li>Click "Add to My Patients" to link them</li>
            </ol>
          </div>
          <div class="mb-3">
            <div style="font-weight:700;color:#0C74C5;margin-bottom:4px;">
              <i class="fa fa-user-plus mr-1"></i> Manual Creation
            </div>
            <ol style="padding-left:18px;margin:0;color:#6b7280;font-size:.78rem;">
              <li>Switch to "Create Manually" tab</li>
              <li>Fill in patient details</li>
              <li>ABHA ID optional at this stage</li>
              <li>Patient can link ABHA later</li>
            </ol>
          </div>
          <hr>
          <div style="font-size:.74rem;color:#9ca3af;">
            <i class="fa fa-lock mr-1"></i>
            ABHA numbers are never stored raw. All ABDM lookups are logged in the audit trail per NHA guidelines.
          </div>
        </div>

        <!-- ABHA format reference -->
        <div class="mt-3 p-3" style="background:#eff6ff;border-radius:10px;">
          <div style="font-size:.72rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
            ABHA Number Format
          </div>
          <div style="font-family:monospace;font-size:.88rem;color:#1d4ed8;letter-spacing:1px;">
            XX-XXXX-XXXX-XXXX
          </div>
          <div style="font-size:.7rem;color:#6b7280;margin-top:4px;">14 digits with hyphens</div>
        </div>

        <div class="mt-3 p-3" style="background:#f0fdf4;border-radius:10px;">
          <div style="font-size:.72rem;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
            ABHA Address Format
          </div>
          <div style="font-family:monospace;font-size:.88rem;color:#15803d;">
            name@abdm
          </div>
          <div style="font-size:.7rem;color:#6b7280;margin-top:4px;">Unique handle on ABDM</div>
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

  /* ── Tab switching ── */
  document.querySelectorAll('.step-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      document.querySelectorAll('.step-tab').forEach(function(t){ t.classList.remove('active'); });
      document.querySelectorAll('.tab-pane-inner').forEach(function(p){ p.classList.remove('active'); });
      tab.classList.add('active');
      document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
  });

  /* ── ABHA search type toggle ── */
  var searchType = 'address';
  document.querySelectorAll('.type-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.type-btn').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      searchType = btn.dataset.stype;
      var inp   = document.getElementById('abhaSearchInput');
      var label = document.getElementById('searchLabel');
      var hint  = document.getElementById('searchHint');
      var suffix = document.querySelector('.input-group-text');
      if(searchType === 'number'){
        label.textContent = 'ABHA Number';
        inp.placeholder   = '12-3456-7890-1234';
        hint.textContent  = 'Enter 14-digit ABHA number (format: XX-XXXX-XXXX-XXXX)';
        suffix.style.display = 'none';
      } else {
        label.textContent = 'ABHA Address';
        inp.placeholder   = 'name@abdm';
        hint.textContent  = 'Enter ABHA address (e.g. john.doe@abdm)';
        suffix.style.display = '';
      }
      document.getElementById('abhaResultCard').style.display = 'none';
    });
  });

  /* ── ABHA Number auto-format ── */
  document.getElementById('f_abha_number').addEventListener('input', function(){
    var d = this.value.replace(/\D/g,'');
    if(d.length > 14) d = d.substr(0,14);
    var out = '';
    if(d.length > 2)  out = d.substr(0,2)+'-'+d.substr(2);
    else               out = d;
    if(d.length > 6)  out = d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6);
    if(d.length > 10) out = d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6,4)+'-'+d.substr(10);
    this.value = out;
  });

  /* ── ABHA Search ── */
  document.getElementById('btnAbhaSearch').addEventListener('click', function(){
    var q = document.getElementById('abhaSearchInput').value.trim();
    if(!q){ alert('Please enter an ABHA ' + (searchType === 'number' ? 'number' : 'address')); return; }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Searching...';

    fetch(BASE + 'doctor/api/abha-search.php?type='+searchType+'&q='+encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-search mr-1"></i> Search';
        showAbhaResult(data);
      })
      .catch(function(err){
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-search mr-1"></i> Search';
        showAbhaResult({success:false, error:'Network error: '+err.message});
      });
  });

  function showAbhaResult(data){
    var card = document.getElementById('abhaResultCard');
    var inner = document.getElementById('abhaResultInner');
    card.style.display = 'block';
    card.classList.remove('error-state');

    if(!data.success || !data.found){
      card.classList.add('error-state');
      var msg = data.error || data.message || 'ABHA not found';
      inner.innerHTML = '<div class="d-flex align-items-center" style="gap:12px;">'
        + '<i class="fa fa-exclamation-triangle fa-2x" style="color:#f97316;"></i>'
        + '<div>'
        + '<div style="font-weight:700;color:#9a3412;font-size:.9rem;">Not Found</div>'
        + '<div style="font-size:.8rem;color:#c2410c;">' + escHtml(msg) + '</div>'
        + '<button class="btn btn-sm btn-outline-secondary mt-2" onclick="switchToManual()">'
        + '<i class="fa fa-user-plus mr-1"></i>Create Manually Instead</button>'
        + '</div></div>';
      return;
    }

    var p = data.profile;
    var initials = (p.name || 'P').charAt(0).toUpperCase();
    var photoHtml = p.photo
      ? '<img src="data:image/png;base64,'+p.photo+'" alt="">'
      : initials;
    var genderBadge = p.gender ? '<span class="badge badge-light ml-1">'+escHtml(p.gender)+'</span>' : '';
    var dobTxt = p.dob ? '  <span class="ml-2" style="color:#6b7280;font-size:.78rem;"><i class="fa fa-calendar mr-1"></i>'+escHtml(p.dob)+'</span>' : '';
    var abhaNum = p.abha_number ? '<div style="font-size:.76rem;color:#15803d;font-family:monospace;margin-top:3px;">'+escHtml(p.abha_number)+'</div>' : '';
    var abhaAddr = p.abha_address ? '<div style="font-size:.74rem;color:#6b7280;">'+escHtml(p.abha_address)+'</div>' : '';

    inner.innerHTML =
      '<div class="d-flex align-items-center" style="gap:14px;flex-wrap:wrap;">'
      + '<div class="abha-avatar" style="background:#0C74C5;">' + photoHtml + '</div>'
      + '<div style="flex:1;">'
      + '<div style="font-weight:700;font-size:1rem;color:#1f2937;">' + escHtml(p.name || 'Unknown') + genderBadge + '</div>'
      + dobTxt
      + abhaNum
      + abhaAddr
      + '</div>'
      + '<span class="badge-verified"><i class="fa fa-check-circle mr-1"></i>ABHA Verified</span>'
      + '</div>'
      + '<hr style="border-color:#bbf7d0;margin:12px 0;">'
      + '<div class="d-flex justify-content-between align-items-center" style="flex-wrap:wrap;gap:8px;">'
      + '<div style="font-size:.78rem;color:#166534;">Patient found in ABDM registry. Ready to add to your panel.</div>'
      + '<button class="btn btn-sm btn-success" onclick="addAbhaPatient('+JSON.stringify(p).replace(/"/g,'&quot;')+')">'
      + '<i class="fa fa-user-plus mr-1"></i>Add to My Patients</button>'
      + '</div>';
  }

  window.addAbhaPatient = function(profile){
    var btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i>Adding...';

    var payload = {
      first_name:    profile.first_name  || (profile.name||'').split(' ')[0],
      middle_name:   profile.middle_name || '',
      last_name:     profile.last_name   || (profile.name||'').split(' ').slice(2).join(' '),
      email:         profile.email       || '',
      mobile:        (profile.mobile||'').replace(/\D/g,''),
      gender:        profile.gender      || '',
      dob:           profile.dob         || '',
      blood_group:   '',
      abha_number:   profile.abha_number || '',
      abha_address:  profile.abha_address|| '',
      abha_verified: 1,
      pincode:       profile.pincode     || '',
      city:          profile.district    || '',
      state:         profile.state_name  || '',
      address:       '',
      no_email:      profile.email ? false : true,
    };

    if(!payload.mobile){
      // ABDM doesn't return mobile in search results — open manual tab pre-filled
      switchToManual(profile);
      return;
    }

    fetch(BASE + 'doctor/api/create-patient-submit.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if(data.success){
        showToast('Patient added successfully!', 'success');
        setTimeout(function(){ window.location = BASE + 'doctor/my-patients.php'; }, 1500);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-user-plus mr-1"></i>Add to My Patients';
        // Mobile missing — pre-fill manual form
        if(data.error && data.error.indexOf('mobile') !== -1){
          switchToManual(profile);
        } else {
          showToast(data.error || 'Failed to add patient', 'error');
        }
      }
    })
    .catch(function(err){
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-user-plus mr-1"></i>Add to My Patients';
      showToast('Network error', 'error');
    });
  };

  window.switchToManual = function(profile){
    document.querySelectorAll('.step-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.tab-pane-inner').forEach(function(p){ p.classList.remove('active'); });
    document.querySelector('[data-tab="manual"]').classList.add('active');
    document.getElementById('tab-manual').classList.add('active');
    if(profile) prefillForm(profile);
    document.getElementById('tab-manual').scrollIntoView({behavior:'smooth'});
  };

  function prefillForm(p){
    setVal('f_first_name',  p.first_name  || (p.name||'').split(' ')[0]);
    setVal('f_middle_name', p.middle_name || (p.name||'').split(' ')[1] || '');
    setVal('f_last_name',   p.last_name   || (p.name||'').split(' ').slice(2).join(' '));
    setVal('f_email',       p.email || '');
    setVal('f_abha_address', (p.abha_address||'').replace('@abdm',''));
    setVal('f_abha_number', p.abha_number || '');
    setVal('f_city',        p.district    || '');
    setVal('f_state',       p.state_name  || '');
    setVal('f_pincode',     p.pincode     || '');
    if(p.gender){
      var g = p.gender === 'M' ? 'Male' : (p.gender === 'F' ? 'Female' : p.gender);
      document.getElementById('f_gender').value = g;
    }
    if(p.dob) setVal('f_dob', p.dob);
    document.getElementById('abhaVerifiedChk').checked = true;
    if(p.photo){
      var prev = document.getElementById('photoPreview');
      prev.src = 'data:image/png;base64,' + p.photo;
      prev.style.display = 'block';
    }
    calcAge();
  }

  function setVal(id, val){ var el = document.getElementById(id); if(el) el.value = val; }

  /* ── Photo preview ── */
  document.getElementById('photoInput').addEventListener('change', function(){
    if(!this.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e){
      var prev = document.getElementById('photoPreview');
      prev.src = e.target.result;
      prev.style.display = 'block';
    };
    reader.readAsDataURL(this.files[0]);
  });

  /* ── No email toggle ── */
  document.getElementById('noEmailChk').addEventListener('change', function(){
    var emailInput = document.getElementById('f_email');
    emailInput.disabled = this.checked;
    if(this.checked) emailInput.value = '';
  });

  /* ── DOB → age ── */
  document.getElementById('f_dob').addEventListener('input', calcAge);
  function calcAge(){
    var dob = document.getElementById('f_dob').value;
    if(!dob) return;
    var parts = dob.split('/');
    if(parts.length !== 3) return;
    var d = new Date(parts[2], parts[1]-1, parts[0]);
    if(isNaN(d)) return;
    var today = new Date();
    var age = today.getFullYear() - d.getFullYear();
    var m = today.getMonth() - d.getMonth();
    if(m < 0 || (m===0 && today.getDate() < d.getDate())) age--;
    if(age >= 0 && age < 150) document.getElementById('f_age').value = age;
  }

  /* ── Generate Unique ID ── */
  (function(){
    var uid = 'PAT' + Date.now().toString(36).toUpperCase() + Math.random().toString(36).substr(2,4).toUpperCase();
    document.getElementById('f_unique_id').value = uid;
  })();

  /* ── Form submit ── */
  document.getElementById('createPatientForm').addEventListener('submit', function(e){
    e.preventDefault();
    var btn = document.getElementById('btnCreate');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Creating...';

    var abhaAddr = document.getElementById('f_abha_address').value.trim();
    if(abhaAddr && abhaAddr.indexOf('@') === -1) abhaAddr += '@abdm';

    var payload = {
      first_name:       document.getElementById('f_first_name').value.trim(),
      middle_name:      document.getElementById('f_middle_name').value.trim(),
      last_name:        document.getElementById('f_last_name').value.trim(),
      email:            document.getElementById('f_email').value.trim(),
      no_email:         document.getElementById('noEmailChk').checked,
      mobile:           document.getElementById('f_mobile').value.trim(),
      gender:           document.getElementById('f_gender').value,
      dob:              document.getElementById('f_dob').value.trim(),
      blood_group:      document.getElementById('f_blood_group').value,
      abha_address:     abhaAddr,
      abha_number:      document.getElementById('f_abha_number').value.trim(),
      abha_verified:    document.getElementById('abhaVerifiedChk').checked ? 1 : 0,
      pincode:          document.getElementById('f_pincode').value.trim(),
      city:             document.getElementById('f_city').value.trim(),
      state:            document.getElementById('f_state').value.trim(),
      address:          document.getElementById('f_address').value.trim(),
      reference_doctor: document.getElementById('f_ref_doctor').value.trim(),
      aadhaar_last4:    document.getElementById('f_aadhaar').value.trim(),
    };

    fetch(BASE + 'doctor/api/create-patient-submit.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if(data.success){
        showToast(data.message || 'Patient created!', 'success');
        setTimeout(function(){ window.location = BASE + 'doctor/my-patients.php'; }, 1600);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-user-plus mr-1"></i> Create Patient';
        showToast(data.error || 'Failed to create patient', 'error');
      }
    })
    .catch(function(err){
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-user-plus mr-1"></i> Create Patient';
      showToast('Network error: ' + err.message, 'error');
    });
  });

  /* ── Toast helper ── */
  function showToast(msg, type){
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;'
      + 'font-size:.84rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);animation:fadeIn .3s ease;'
      + (type==='success'?'background:#16a34a;color:#fff;':'background:#dc2626;color:#fff;');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3500);
  }

  /* ── HTML escape ── */
  function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
</script>
</body>
</html>
