<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload        = doctor_jwt_guard();
$doctor_id      = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
$prefill_mobile = preg_replace('/\D/','',($_GET['mobile']??''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Patient Manually — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:20px;}
.cp-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;
  margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;}
</style>
</head>
<body>
<main class="doctor-content">

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="<?= BASE_URL ?>doctor/add-patient.php" style="color:#6b7280;font-size:.82rem;font-weight:600;">
      <i class="fa fa-arrow-left mr-1"></i> Add Patient
    </a>
    <h5 class="mb-0 font-weight-bold mt-1" style="color:#1f2937;">Add Patient Manually</h5>
    <div style="font-size:.74rem;color:#9ca3af;">ABHA can be linked later</div>
  </div>
</div>

<div class="row"><div class="col-lg-8">
<form id="manualForm">

<div class="cp-card">
  <div class="cp-title"><i class="fa fa-user mr-2" style="color:#e07e18;"></i>Basic Information</div>
  <div class="row">
    <div class="col-md-4"><div class="form-group">
      <label class="form-label-sm">First Name <span class="text-danger">*</span></label>
      <input type="text" name="first_name" id="f_fn" class="form-control form-control-sm" required>
    </div></div>
    <div class="col-md-4"><div class="form-group">
      <label class="form-label-sm">Middle Name</label>
      <input type="text" name="middle_name" class="form-control form-control-sm">
    </div></div>
    <div class="col-md-4"><div class="form-group">
      <label class="form-label-sm">Last Name</label>
      <input type="text" name="last_name" class="form-control form-control-sm">
    </div></div>
  </div>
  <div class="row">
    <div class="col-md-5"><div class="form-group">
      <label class="form-label-sm">Mobile <span class="text-danger">*</span></label>
      <div class="input-group input-group-sm">
        <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
        <input type="text" name="mobile" id="f_mob" class="form-control" placeholder="9876543210"
               maxlength="10" value="<?= htmlspecialchars($prefill_mobile) ?>" required>
      </div>
    </div></div>
    <div class="col-md-7"><div class="form-group">
      <label class="form-label-sm">Email</label>
      <input type="email" name="email" id="f_email" class="form-control form-control-sm">
      <div class="form-check mt-1">
        <input type="checkbox" class="form-check-input" id="noEmail">
        <label for="noEmail" class="form-check-label" style="font-size:.73rem;color:#6b7280;">No email</label>
      </div>
    </div></div>
  </div>
  <div class="row">
    <div class="col-md-3"><div class="form-group">
      <label class="form-label-sm">Gender</label>
      <select name="gender" class="form-control form-control-sm">
        <option value="">--</option><option>Male</option><option>Female</option><option>Other</option>
      </select>
    </div></div>
    <div class="col-md-3"><div class="form-group">
      <label class="form-label-sm">Date of Birth</label>
      <input type="text" name="dob" id="f_dob" class="form-control form-control-sm" placeholder="dd/mm/yyyy" maxlength="10">
    </div></div>
    <div class="col-md-2"><div class="form-group">
      <label class="form-label-sm">Age</label>
      <input type="number" id="f_age" class="form-control form-control-sm" readonly style="background:#f9fafb;">
    </div></div>
    <div class="col-md-4"><div class="form-group">
      <label class="form-label-sm">Blood Group</label>
      <select name="blood_group" class="form-control form-control-sm">
        <option value="">--</option>
        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
        <option><?= $bg ?></option>
        <?php endforeach; ?>
      </select>
    </div></div>
  </div>
</div>

<div class="cp-card">
  <div class="cp-title"><i class="fa fa-map-marker mr-2" style="color:#e07e18;"></i>Address <span class="text-muted font-weight-normal" style="font-size:.72rem;">(optional)</span></div>
  <div class="row">
    <div class="col-md-4"><div class="form-group"><label class="form-label-sm">Pin Code</label>
      <input type="text" name="pincode" class="form-control form-control-sm" maxlength="6"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="form-label-sm">City</label>
      <input type="text" name="city" class="form-control form-control-sm"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="form-label-sm">State</label>
      <input type="text" name="state" class="form-control form-control-sm"></div></div>
  </div>
  <div class="form-group">
    <label class="form-label-sm">Full Address</label>
    <textarea name="address" class="form-control form-control-sm" rows="2"></textarea>
  </div>
</div>

<div id="errForm" class="alert alert-danger" style="display:none;border-radius:8px;font-size:.84rem;"></div>
<button type="submit" class="btn btn-warning font-weight-bold" id="btnCreate" style="color:#fff;">
  <i class="fa fa-user-plus mr-1"></i> Create Patient
</button>
<a href="<?= BASE_URL ?>doctor/add-patient.php" class="btn btn-outline-secondary ml-2">Cancel</a>

</form>
</div></div>
</main>

<script>
const BASE='<?= BASE_URL ?>';

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
  const fd=new FormData(this);
  const payload={};
  fd.forEach((v,k)=>payload[k]=v);
  payload.no_email=document.getElementById('noEmail').checked;
  payload.abha_number='';payload.abha_address='';payload.abha_verified=0;
  fetch(BASE+'doctor/api/create-patient-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
  .then(r=>r.json()).then(data=>{
    if(data.success){
      window.location=BASE+'doctor/patient-profile.php?id='+data.patient_id+'&new=1';
    } else {
      btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';
      const e=document.getElementById('errForm');e.style.display='block';e.textContent=data.error||'Failed';
    }
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Create Patient';});
});
</script>
</body>
</html>
