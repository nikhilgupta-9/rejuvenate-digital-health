<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';
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
<title>Create New ABHA — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:20px;}
.cp-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;
  margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}
.wizard{display:flex;margin-bottom:22px;position:relative;}
.wizard::before{content:'';position:absolute;top:18px;left:18px;right:18px;height:2px;background:#e5e7eb;z-index:0;}
.wi{flex:1;text-align:center;position:relative;z-index:1;}
.wc{width:36px;height:36px;border-radius:50%;border:2px solid #e5e7eb;background:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#9ca3af;transition:.25s;}
.wi.active .wc{border-color:#02c9b8;background:#02c9b8;color:#fff;}
.wi.done   .wc{border-color:#16a34a;background:#16a34a;color:#fff;}
.wl{display:block;font-size:.68rem;color:#9ca3af;margin-top:4px;font-weight:600;}
.wi.active .wl{color:#02c9b8;} .wi.done .wl{color:#16a34a;}
.sbody{display:none;} .sbody.active{display:block;}
.otp-row{display:flex;gap:8px;justify-content:center;margin:16px 0;}
.otp-row input{width:46px;height:52px;border-radius:10px;border:2px solid #e5e7eb;text-align:center;
  font-size:1.3rem;font-weight:700;transition:.15s;}
.otp-row input:focus{border-color:#02c9b8;outline:none;box-shadow:0 0 0 3px rgba(2,201,184,.12);}
.addr-chip{display:inline-block;padding:7px 14px;border-radius:20px;border:2px solid #e5e7eb;
  font-size:.8rem;cursor:pointer;margin:3px;transition:.15s;background:#f9fafb;font-family:monospace;}
.addr-chip:hover,.addr-chip.sel{border-color:#02c9b8;background:#02c9b8;color:#fff;}
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
    <h5 class="mb-0 font-weight-bold mt-1" style="color:#1f2937;">Create New ABHA</h5>
    <div style="font-size:.74rem;color:#9ca3af;">Patient has no ABHA yet — create via Aadhaar OTP</div>
  </div>
</div>

<?php if (!ABDM_CONFIGURED): ?>
<div class="alert alert-danger"><i class="fa fa-times-circle mr-2"></i>ABDM not configured on this server.</div>
<?php else: ?>

<div class="row"><div class="col-lg-7">

<div class="wizard">
  <div class="wi active" id="si1"><div class="wc">1</div><span class="wl">Aadhaar</span></div>
  <div class="wi"        id="si2"><div class="wc">2</div><span class="wl">Verify OTP</span></div>
  <div class="wi"        id="si3"><div class="wc">3</div><span class="wl">ABHA Address</span></div>
  <div class="wi"        id="si4"><div class="wc">4</div><span class="wl">Done</span></div>
</div>

<!-- Step 1: Aadhaar -->
<div class="sbody active" id="step1">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-id-card mr-2" style="color:#02c9b8;"></i>Patient's Aadhaar</div>
    <div class="alert alert-info" style="font-size:.78rem;border-radius:8px;">
      <i class="fa fa-lock mr-1"></i> Aadhaar is RSA-encrypted before sending to ABDM. <strong>Never stored</strong> in our database.
    </div>
    <div class="form-group">
      <label class="form-label-sm">Aadhaar Number <span class="text-danger">*</span></label>
      <input type="password" id="aadhaarInput" class="form-control" placeholder="•••• •••• ••••"
             maxlength="12" autocomplete="off" style="font-size:1.1rem;letter-spacing:3px;max-width:260px;">
      <small class="text-muted">Patient must be present. OTP goes to Aadhaar-linked mobile.</small>
    </div>
    <div class="form-group">
      <label class="form-label-sm">Communication Mobile <small class="text-muted">(you can enter in next step)</small></label>
      <div class="input-group" style="max-width:240px;">
        <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
        <input type="text" id="mobileInput" class="form-control" placeholder="9876543210" maxlength="10" inputmode="numeric">
      </div>
    </div>
    <button class="btn btn-success" id="btnSendOtp">
      <i class="fa fa-paper-plane mr-1"></i> Send OTP
    </button>
    <div id="err1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
  </div>
</div>

<!-- Step 2: OTP + mobile if missing -->
<div class="sbody" id="step2">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-mobile mr-2" style="color:#02c9b8;"></i>Enter Aadhaar OTP</div>
    <p class="text-center text-muted" id="sentMsg" style="font-size:.86rem;"></p>
    <div class="otp-row">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
    </div>
    <div class="d-flex justify-content-between align-items-center">
      <button class="btn btn-link btn-sm p-0" id="btnResend" disabled style="font-size:.76rem;">
        <i class="fa fa-refresh mr-1"></i>Resend OTP
      </button>
      <span id="timerEl" style="font-size:.74rem;color:#9ca3af;"></span>
    </div>
    <div class="form-group mt-3" id="mobileStep2Wrap">
      <label class="form-label-sm">Communication Mobile <span class="text-danger">*</span></label>
      <div class="input-group" style="max-width:240px;">
        <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
        <input type="text" id="mobileStep2" class="form-control" placeholder="9876543210" maxlength="10" inputmode="numeric">
      </div>
      <small class="text-muted">ABDM requires this for the ABHA account.</small>
    </div>
    <div id="err2" class="alert alert-danger mt-2" style="display:none;border-radius:8px;font-size:.84rem;"></div>
    <div class="d-flex mt-3" style="gap:10px;">
      <button class="btn btn-outline-secondary btn-sm" onclick="goStep(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
      <button class="btn btn-success" id="btnVerify"><i class="fa fa-check mr-1"></i> Verify OTP</button>
    </div>
  </div>
</div>

<!-- Step 3: Choose ABHA address -->
<div class="sbody" id="step3">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-at mr-2" style="color:#02c9b8;"></i>Choose ABHA Address</div>
    <p style="font-size:.84rem;color:#374151;">ABHA created! Pick a preferred address for this patient:</p>
    <div id="suggestions" class="mb-3"></div>
    <div class="form-group">
      <label class="form-label-sm">Or type a custom address</label>
      <input type="text" id="customAddr" class="form-control form-control-sm" placeholder="e.g. firstname.lastname@sbx" style="max-width:280px;">
      <small class="text-muted">Sandbox uses @sbx suffix. Production uses @abdm.</small>
    </div>
    <div id="err3" class="alert alert-danger mt-2" style="display:none;border-radius:8px;font-size:.84rem;"></div>
    <div class="d-flex mt-3" style="gap:10px;">
      <button class="btn btn-outline-secondary btn-sm" onclick="goStep(2)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
      <button class="btn btn-success" id="btnConfirm"><i class="fa fa-check mr-1"></i> Confirm &amp; Save</button>
    </div>
  </div>
</div>

<!-- Step 4: Done / redirecting -->
<div class="sbody" id="step4">
  <div id="step4Inner">
    <div class="cp-card text-center py-5">
      <i class="fa fa-spinner fa-spin fa-2x" style="color:#02c9b8;"></i>
      <div class="mt-2 text-muted" style="font-size:.86rem;">Saving patient…</div>
    </div>
  </div>
</div>

</div></div>
<?php endif; ?>
</main>

<script>
const BASE='<?= BASE_URL ?>';
let txnId='',xToken='',profile={},isNew=true,selAddr='';

/* OTP wiring */
document.querySelectorAll('.otp-box').forEach((el,i,all)=>{
  el.addEventListener('input',()=>{el.value=el.value.replace(/\D/g,'').slice(-1);if(el.value&&all[i+1])all[i+1].focus();});
  el.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!el.value&&all[i-1])all[i-1].focus();});
  el.addEventListener('paste',e=>{
    const d=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    all.forEach((b,j)=>{b.value=d[j]||'';});all[Math.min(5,d.length-1)||0].focus();e.preventDefault();
  });
});
function getOtp(){return [...document.querySelectorAll('.otp-box')].map(e=>e.value).join('');}

function startTimer(s){
  document.getElementById('btnResend').disabled=true;
  const el=document.getElementById('timerEl');let t=s;
  const iv=setInterval(()=>{el.textContent='Resend in '+t+'s';if(--t<0){clearInterval(iv);el.textContent='';document.getElementById('btnResend').disabled=false;}},1000);
}

function goStep(n){
  ['step1','step2','step3','step4'].forEach((id,i)=>document.getElementById(id).classList.toggle('active',i===n-1));
  ['si1','si2','si3','si4'].forEach((id,i)=>{
    const el=document.getElementById(id);
    if(i<n-1) el.className='wi done'; else if(i===n-1) el.className='wi active'; else el.className='wi';
  });
}
function showErr(id,msg){const el=document.getElementById(id);el.style.display='block';el.textContent=msg;}
function hideErr(id){document.getElementById(id).style.display='none';}

/* ── Step 1: Send OTP ── */
document.getElementById('btnSendOtp').addEventListener('click',function(){
  const aad=document.getElementById('aadhaarInput').value.replace(/\D/g,'');
  if(aad.length!==12){showErr('err1','Aadhaar must be 12 digits');return;}
  hideErr('err1');
  const mob=document.getElementById('mobileInput').value.replace(/\D/g,'');
  if(mob) document.getElementById('mobileStep2').value=mob;
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';
  fetch(BASE+'doctor/api/abdm_send_otp.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({aadhaar:aad,mobile:mob||''})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP';
    if(!data.success){showErr('err1',data.error||'Failed to send OTP');return;}
    txnId=data.txnId;
    document.getElementById('sentMsg').textContent=data.message||'OTP sent to Aadhaar-linked mobile.';
    document.querySelectorAll('.otp-box').forEach(i=>i.value='');
    goStep(2);startTimer(300);
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP';showErr('err1','Network error — please retry');});
});
document.getElementById('btnResend').addEventListener('click',()=>document.getElementById('btnSendOtp').click());

/* ── Step 2: Verify OTP ── */
document.getElementById('btnVerify').addEventListener('click',function(){
  const otp=getOtp();
  if(otp.length<6){showErr('err2','Enter the complete 6-digit OTP');return;}
  const mob=document.getElementById('mobileStep2').value.replace(/\D/g,'');
  if(mob.length!==10){showErr('err2','Enter a valid 10-digit mobile number');return;}
  hideErr('err2');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
  fetch(BASE+'doctor/api/abdm_verify_otp.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({otp:otp,mobile:mob})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    if(!data.success){showErr('err2',data.error||'OTP verification failed');return;}

    txnId=data.txnId; xToken=data.token; profile=data.ABHAProfile||{}; isNew=data.isNew!==false;

    if(!isNew){
      // ABHA already exists for this Aadhaar — save directly, skip address step
      goStep(4);
      fetch(BASE+'doctor/api/abdm_set_address.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({abhaAddress:profile.preferredAbhaAddress||profile.phrAddress||''})})
      .then(r=>r.json()).then(d=>{
        if(d.success){
          document.getElementById('step4Inner').innerHTML=
            '<div class="cp-card text-center py-4">'
            +'<i class="fa fa-check-circle fa-3x" style="color:#16a34a;"></i>'
            +'<div class="mt-2 font-weight-bold">ABHA Already Exists!</div>'
            +'<div class="text-muted mt-1" style="font-size:.84rem;">'+esc(d.abhaAddress||'')+'</div>'
            +'<a href="'+BASE+'doctor/patient-profile.php?id='+d.patient_id+'&new=1" class="btn btn-success mt-3">View Patient Profile</a>'
            +'</div>';
        } else {
          document.getElementById('step4Inner').innerHTML=
            '<div class="cp-card text-center py-4 text-danger">'
            +'<i class="fa fa-times-circle fa-2x"></i>'
            +'<div class="mt-2">'+esc(d.error||'Failed to save patient')+'</div>'
            +'<button class="btn btn-sm btn-outline-secondary mt-3" onclick="goStep(2)">Back</button>'
            +'</div>';
        }
      }).catch(()=>{
        document.getElementById('step4Inner').innerHTML='<div class="cp-card text-center py-4 text-danger">Network error saving patient.</div>';
      });
      return;
    }

    // New ABHA — fetch address suggestions then show step 3
    fetch(BASE+'doctor/api/abdm_suggestions.php',{headers:{'Content-Type':'application/json'}})
    .then(r=>r.json()).then(sData=>{
      const suggs=sData.suggestions||[];
      const wrap=document.getElementById('suggestions');
      wrap.innerHTML=suggs.length
        ?'<div style="font-size:.76rem;font-weight:600;color:#374151;margin-bottom:6px;">Suggested addresses:</div>'
          +suggs.map(a=>'<span class="addr-chip" onclick="pickAddr(this,\''+esc(a)+'\')">'+esc(a)+'</span>').join('')
        :'<div style="font-size:.76rem;color:#9ca3af;margin-bottom:8px;">No suggestions returned. Enter an address below.</div>';
      if(sData.warning) showErr('err3','Note: '+sData.warning);
      goStep(3);
    }).catch(()=>{
      document.getElementById('suggestions').innerHTML='<div style="font-size:.76rem;color:#9ca3af;">Could not load suggestions. Enter address manually.</div>';
      goStep(3);
    });

  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';showErr('err2','Network error — please retry');});
});

window.pickAddr=function(el,a){
  document.querySelectorAll('.addr-chip').forEach(c=>c.classList.remove('sel'));
  el.classList.add('sel');selAddr=a;
  document.getElementById('customAddr').value=a;
};

/* ── Step 3: Confirm address ── */
document.getElementById('btnConfirm').addEventListener('click',function(){
  const chosen=(document.getElementById('customAddr').value.trim()||selAddr||'').trim();
  if(!chosen){showErr('err3','Choose or enter an ABHA address');return;}
  if(!/^[a-zA-Z0-9._-]{3,}@(sbx|abdm)$/.test(chosen)){
    showErr('err3','Address must be like firstname.lastname@sbx (sandbox) or @abdm (production)');return;
  }
  hideErr('err3');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Saving…';
  goStep(4);
  fetch(BASE+'doctor/api/abdm_set_address.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({abhaAddress:chosen})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Confirm & Save';
    if(!data.success){
      goStep(3);showErr('err3',data.error||'Failed to set ABHA address');return;
    }
    document.getElementById('step4Inner').innerHTML=
      '<div class="cp-card text-center py-4">'
      +'<i class="fa fa-check-circle fa-3x" style="color:#16a34a;"></i>'
      +'<div class="mt-2 font-weight-bold">ABHA Created Successfully!</div>'
      +'<div class="text-muted mt-1" style="font-size:.84rem;">'+esc(data.abhaAddress||chosen)+'</div>'
      +(data.abhaNumber?'<div style="font-size:.78rem;color:#0C74C5;font-family:monospace;margin-top:4px;">'+esc(data.abhaNumber)+'</div>':'')
      +(data.patient_id?'<a href="'+BASE+'doctor/patient-profile.php?id='+data.patient_id+'&new=1" class="btn btn-success mt-3"><i class="fa fa-user mr-1"></i>View Patient Profile</a>':'')
      +'</div>';
  }).catch(()=>{goStep(3);showErr('err3','Network error — please retry');});
});

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>
</body>
</html>
