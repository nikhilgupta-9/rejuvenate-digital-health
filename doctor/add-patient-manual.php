<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/util/otp-widget.php';
$payload        = doctor_jwt_guard();
$doctor_id      = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
$prefill_mobile = preg_replace('/\D/','',($_GET['mobile']??''));
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
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;}
.ap-mobile-hint{font-size:.72rem;color:#9ca3af;margin-top:3px;}
.abha-choice{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.abha-choice label{flex:1;min-width:150px;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 14px;
  cursor:pointer;font-size:.83rem;font-weight:600;color:#374151;margin:0;transition:.15s;}
.abha-choice input{margin-right:8px;}
.abha-choice input:checked + span{color:#0C74C5;}
.abha-choice label:has(input:checked){border-color:#0C74C5;background:#f0f7ff;}
#abhaFields{display:none;}
</style>
</head>
<body>
<?php include(__DIR__ . "/inc/sidebar.php"); ?>

<main class="doctor-content">

<div class="d-flex flex-wrap align-items-center justify-content-between mb-4" style="gap:10px;">
  <div>
    <a href="<?= BASE_URL ?>doctor/add-patient.php" class="text-decoration-none" style="color:#6b7280;font-size:.82rem;font-weight:600;">
      <i class="fa fa-arrow-left me-1"></i> Add Patient
    </a>
    <h5 class="mb-0 fw-bold mt-1" style="color:#1f2937;">Register Patient</h5>
    <div style="font-size:.74rem;color:#9ca3af;">Mobile verified by WhatsApp OTP · ABHA optional</div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <form id="manualForm">

      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-user me-2" style="color:#e07e18;"></i>Basic Information</div>
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
            <div class="ap-mobile-hint">
              Send a code to the patient's WhatsApp &amp; email, then enter what they read back.
            </div>
            <?php render_otp_widget([
              'role'            => 'patient',
              'mobile_field'    => 'mobile',
              'email_field'     => 'email',
              'name_field'      => 'first_name',
              'submit_selector' => '#btnCreate',
              'allow_existing'  => true,
              'send_url'        => BASE_URL . 'doctor/api/patient-otp-send.php',
              'verify_url'      => BASE_URL . 'doctor/api/patient-otp-verify.php',
            ]); ?>
          </div>
          <div class="col-md-7 mb-3">
            <label class="form-label-sm">Email</label>
            <input type="email" name="email" id="f_email" class="form-control form-control-sm">
            <div class="form-check mt-1">
              <input type="checkbox" class="form-check-input" id="noEmail">
              <label for="noEmail" class="form-check-label" style="font-size:.73rem;color:#6b7280;">No email</label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-6 col-md-3 mb-3">
            <label class="form-label-sm">Gender</label>
            <select name="gender" class="form-control form-control-sm">
              <option value="">--</option><option>Male</option><option>Female</option><option>Other</option>
            </select>
          </div>
          <div class="col-6 col-md-3 mb-3">
            <label class="form-label-sm">Date of Birth</label>
            <input type="text" name="dob" id="f_dob" class="form-control form-control-sm" placeholder="dd/mm/yyyy" maxlength="10">
          </div>
          <div class="col-6 col-md-2 mb-3">
            <label class="form-label-sm">Age</label>
            <input type="number" id="f_age" class="form-control form-control-sm" readonly style="background:#f9fafb;">
          </div>
          <div class="col-6 col-md-4 mb-3">
            <label class="form-label-sm">Blood Group</label>
            <select name="blood_group" class="form-control form-control-sm">
              <option value="">--</option>
              <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
              <option><?= $bg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-id-card me-2" style="color:#0C74C5;"></i>ABHA Health ID</div>
        <div class="abha-choice">
          <label><input type="radio" name="has_abha" value="no" checked><span>No ABHA yet</span></label>
          <label><input type="radio" name="has_abha" value="yes"><span>Patient has an ABHA</span></label>
        </div>
        <div id="abhaFields" class="row">
          <div class="col-md-6 mb-2">
            <label class="form-label-sm">ABHA Number</label>
            <input type="text" name="abha_number" id="f_abha_num" class="form-control form-control-sm"
                   placeholder="XX-XXXX-XXXX-XXXX" maxlength="17" autocomplete="off">
            <div class="ap-mobile-hint" id="abhaNumHint">14 digits, format 12-3456-7890-1234</div>
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label-sm">ABHA Address <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" name="abha_address" id="f_abha_addr" class="form-control form-control-sm"
                   placeholder="name@abdm" autocomplete="off">
          </div>
          <div class="col-12">
            <div class="ap-mobile-hint">
              <i class="fa fa-info-circle"></i> Recorded as provided. Live ABDM verification will run automatically
              once the ABHA integration is enabled.
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-map-marker me-2" style="color:#e07e18;"></i>Address <span class="text-muted fw-normal" style="font-size:.72rem;">(optional)</span></div>
        <div class="row">
          <div class="col-md-4 mb-3"><label class="form-label-sm">Pin Code</label>
            <input type="text" name="pincode" class="form-control form-control-sm" maxlength="6"></div>
          <div class="col-md-4 mb-3"><label class="form-label-sm">City</label>
            <input type="text" name="city" class="form-control form-control-sm"></div>
          <div class="col-md-4 mb-3"><label class="form-label-sm">State</label>
            <input type="text" name="state" class="form-control form-control-sm"></div>
        </div>
        <div class="mb-1">
          <label class="form-label-sm">Full Address</label>
          <textarea name="address" class="form-control form-control-sm" rows="2"></textarea>
        </div>
      </div>

      <div id="errForm" class="alert alert-danger" style="display:none;border-radius:8px;font-size:.84rem;"></div>
      <div class="d-flex flex-wrap" style="gap:10px;">
        <button type="submit" class="btn btn-primary fw-bold" id="btnCreate">
          <i class="fa fa-user-plus me-1"></i> Create Patient
        </button>
        <a href="<?= BASE_URL ?>doctor/add-patient.php" class="btn btn-outline-secondary">Cancel</a>
      </div>

    </form>
  </div>
</div>

</main>

<script>
const BASE='<?= BASE_URL ?>';

document.getElementById('noEmail').addEventListener('change',function(){
  document.getElementById('f_email').disabled=this.checked;
  if(this.checked)document.getElementById('f_email').value='';
});

// ABHA yes/no toggle
document.querySelectorAll('input[name=has_abha]').forEach(function(r){
  r.addEventListener('change',function(){
    document.getElementById('abhaFields').style.display = (this.value==='yes') ? 'flex' : 'none';
  });
});

// Auto-format ABHA number as XX-XXXX-XXXX-XXXX
document.getElementById('f_abha_num').addEventListener('input',function(){
  const d=this.value.replace(/\D/g,'').slice(0,14);
  this.value=d.replace(/(\d{2})(\d{0,4})(\d{0,4})(\d{0,4})/,function(_,a,b,c,e){
    return [a,b,c,e].filter(Boolean).join('-');
  });
  document.getElementById('abhaNumHint').textContent =
    (d.length && d.length!==14) ? d.length+' / 14 digits' : '14 digits, format 12-3456-7890-1234';
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
  btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin me-1"></i> Creating…';
  const fd=new FormData(this);
  const payload={};
  fd.forEach((v,k)=>payload[k]=v);
  payload.no_email=document.getElementById('noEmail').checked;

  const hasAbha=document.querySelector('input[name=has_abha]:checked').value==='yes';
  let abhaNum='';
  if(hasAbha){
    const digits=document.getElementById('f_abha_num').value.replace(/\D/g,'');
    if(digits.length!==14){
      btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus me-1"></i> Create Patient';
      const el=document.getElementById('errForm');el.style.display='block';
      el.textContent='Enter a valid 14-digit ABHA number, or choose "No ABHA yet".';
      return;
    }
    abhaNum=digits.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/,'$1-$2-$3-$4');
  }
  payload.abha_number=abhaNum;
  payload.abha_address=hasAbha?document.getElementById('f_abha_addr').value.trim():'';
  payload.abha_verified=0;

  fetch(BASE+'doctor/api/create-patient-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
  .then(r=>r.json()).then(data=>{
    if(data.success){
      window.location=BASE+'doctor/patient-profile.php?id='+data.patient_id+'&new=1';
    } else {
      btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus me-1"></i> Create Patient';
      const e=document.getElementById('errForm');e.style.display='block';e.textContent=data.error||'Failed';
    }
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus me-1"></i> Create Patient';});
});
</script>
</body>
</html>
