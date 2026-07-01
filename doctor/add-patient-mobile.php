<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload        = doctor_jwt_guard();
$doctor_id      = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add by Mobile — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:20px;}
.cp-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;
  margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}
.found-card{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:18px 20px;}
.not-found-card{background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:18px 20px;}
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;display:block;}
</style>
</head>
<body>
<main class="doctor-content">

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="<?= BASE_URL ?>doctor/add-patient.php" style="color:#6b7280;font-size:.82rem;font-weight:600;">
      <i class="fa fa-arrow-left mr-1"></i> Add Patient
    </a>
    <h5 class="mb-0 font-weight-bold mt-1" style="color:#1f2937;">Search by Mobile Number</h5>
  </div>
</div>

<div class="row"><div class="col-lg-6">

<div class="cp-card">
  <div class="cp-title"><i class="fa fa-search mr-2" style="color:#7c3aed;"></i>Patient's Mobile</div>
  <div class="form-group">
    <label class="form-label-sm">Mobile Number</label>
    <div class="input-group" style="max-width:280px;">
      <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
      <input type="text" id="mobileInput" class="form-control" placeholder="9876543210" maxlength="10" inputmode="numeric">
    </div>
  </div>
  <button class="btn btn-primary" id="btnSearch">
    <i class="fa fa-search mr-1"></i> Search
  </button>
  <div id="errSearch" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
</div>

<div id="resultArea"></div>

</div></div>
</main>

<script>
const BASE='<?= BASE_URL ?>';

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

document.getElementById('btnSearch').addEventListener('click',function(){
  const mob=document.getElementById('mobileInput').value.replace(/\D/g,'');
  if(mob.length!==10){document.getElementById('errSearch').style.display='block';
    document.getElementById('errSearch').textContent='Enter a valid 10-digit mobile number';return;}
  document.getElementById('errSearch').style.display='none';
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Searching…';
  fetch(BASE+'doctor/api/patient-search-mobile.php?mobile='+mob,{headers:{'Content-Type':'application/json'}})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-search mr-1"></i> Search';
    if(!data.success){document.getElementById('errSearch').style.display='block';
      document.getElementById('errSearch').textContent=data.error||'Search failed';return;}
    const area=document.getElementById('resultArea');
    if(data.found){
      const p=data.patient;
      if(p.already_linked){
        area.innerHTML='<div class="found-card">'
          +'<div class="d-flex align-items-center justify-content-between">'
          +'<div><div style="font-weight:700;font-size:.95rem;">'+esc(p.name)+'</div>'
          +'<div style="font-size:.8rem;color:#6b7280;">'+esc(p.mobile)+(p.email?' · '+esc(p.email):'')+'</div>'
          +(p.abha_number?'<div style="font-size:.76rem;color:#0C74C5;font-family:monospace;margin-top:2px;"><i class="fa fa-id-card-o mr-1"></i>'+esc(p.abha_number)+'</div>':'')
          +'</div>'
          +'<span class="badge badge-success">Already in panel</span>'
          +'</div>'
          +'<div class="mt-3">'
          +'<a href="'+BASE+'doctor/patient-profile.php?id='+p.id+'" class="btn btn-success btn-sm"><i class="fa fa-user mr-1"></i> View Profile</a>'
          +'</div></div>';
      } else {
        area.innerHTML='<div class="found-card">'
          +'<div style="font-weight:700;font-size:.95rem;">'+esc(p.name)+'</div>'
          +'<div style="font-size:.8rem;color:#6b7280;">'+esc(p.mobile)+(p.email?' · '+esc(p.email):'')+'</div>'
          +(p.abha_number?'<div style="font-size:.76rem;color:#0C74C5;font-family:monospace;margin-top:2px;"><i class="fa fa-id-card-o mr-1"></i>'+esc(p.abha_number)+'</div>':'')
          +'<div class="mt-3">'
          +'<button class="btn btn-primary btn-sm" id="btnLink" onclick="linkPatient('+p.id+')"><i class="fa fa-link mr-1"></i> Add to My Panel</button>'
          +'</div></div>';
      }
    } else {
      area.innerHTML='<div class="not-found-card">'
        +'<div style="font-weight:700;font-size:.9rem;color:#92400e;">No patient found with this mobile</div>'
        +'<div style="font-size:.82rem;color:#78350f;margin-top:6px;">This number is not registered in the portal.</div>'
        +'<div class="mt-3 d-flex" style="gap:8px;">'
        +'<a href="'+BASE+'doctor/add-patient-abha.php" class="btn btn-outline-primary btn-sm"><i class="fa fa-id-card-o mr-1"></i> Verify via ABHA</a>'
        +'<a href="'+BASE+'doctor/add-patient-manual.php?mobile='+mob+'" class="btn btn-outline-secondary btn-sm"><i class="fa fa-pencil mr-1"></i> Create Manually</a>'
        +'</div></div>';
    }
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-search mr-1"></i> Search';
    document.getElementById('errSearch').style.display='block';document.getElementById('errSearch').textContent='Network error';});
});

window.linkPatient=function(pid){
  const btn=document.getElementById('btnLink');
  btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Adding…';
  fetch(BASE+'doctor/api/create-patient-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({link_existing_id:pid})})
  .then(r=>r.json()).then(d=>{
    if(d.success) window.location=BASE+'doctor/patient-profile.php?id='+pid;
    else{btn.disabled=false;btn.innerHTML='<i class="fa fa-link mr-1"></i> Add to My Panel';alert(d.error||'Failed');}
  });
};
</script>
</body>
</html>
