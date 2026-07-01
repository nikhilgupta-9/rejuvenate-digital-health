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
<title>Verify Patient ABHA — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.cp-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:20px;}
.cp-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#6b7280;
  margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;}
.method-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;
  border:2px solid #e5e7eb;cursor:pointer;font-size:.8rem;font-weight:600;background:#fff;
  color:#374151;transition:.15s;margin:3px;}
.method-tab:hover,.method-tab.sel{border-color:#0C74C5;background:#0C74C5;color:#fff;}
.wizard{display:flex;margin-bottom:22px;position:relative;}
.wizard::before{content:'';position:absolute;top:18px;left:18px;right:18px;height:2px;background:#e5e7eb;z-index:0;}
.wi{flex:1;text-align:center;position:relative;z-index:1;}
.wc{width:36px;height:36px;border-radius:50%;border:2px solid #e5e7eb;background:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#9ca3af;transition:.25s;}
.wi.active .wc{border-color:#0C74C5;background:#0C74C5;color:#fff;}
.wi.done   .wc{border-color:#16a34a;background:#16a34a;color:#fff;}
.wl{display:block;font-size:.68rem;color:#9ca3af;margin-top:4px;font-weight:600;}
.wi.active .wl{color:#0C74C5;} .wi.done .wl{color:#16a34a;}
.sbody{display:none;} .sbody.active{display:block;}
.otp-row{display:flex;gap:8px;justify-content:center;margin:16px 0;}
.otp-row input{width:46px;height:52px;border-radius:10px;border:2px solid #e5e7eb;text-align:center;
  font-size:1.3rem;font-weight:700;transition:.15s;}
.otp-row input:focus{border-color:#0C74C5;outline:none;box-shadow:0 0 0 3px rgba(12,116,197,.12);}
.form-label-sm{font-size:.78rem;color:#374151;font-weight:600;margin-bottom:4px;display:block;}
.back-link{color:#6b7280;font-size:.82rem;font-weight:600;cursor:pointer;}
.back-link:hover{color:#0C74C5;}
</style>
</head>
<body>
<main class="doctor-content">

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="<?= BASE_URL ?>doctor/add-patient.php" class="back-link"><i class="fa fa-arrow-left mr-1"></i> Add Patient</a>
    <h5 class="mb-0 font-weight-bold mt-1" style="color:#1f2937;">Verify Existing ABHA</h5>
    <div style="font-size:.74rem;color:#9ca3af;">OTP sent to patient's ABHA-registered mobile</div>
  </div>
</div>

<?php if (!ABDM_CONFIGURED): ?>
<div class="alert alert-danger"><i class="fa fa-times-circle mr-2"></i>ABDM not configured on this server.</div>
<?php else: ?>

<div class="row"><div class="col-lg-7">

<div class="wizard">
  <div class="wi active" id="si1"><div class="wc">1</div><span class="wl">Verify Identity</span></div>
  <div class="wi"        id="si2"><div class="wc">2</div><span class="wl">Enter OTP</span></div>
  <div class="wi"        id="si3"><div class="wc">3</div><span class="wl">Profile</span></div>
</div>

<!-- Step 1: choose method + enter value -->
<div class="sbody active" id="step1">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-shield mr-2" style="color:#0C74C5;"></i>How to verify patient identity</div>
    <div class="mb-3">
      <button class="method-tab sel" onclick="setType('aadhaar',this)"><i class="fa fa-id-card"></i> Aadhaar OTP</button>
      <button class="method-tab"     onclick="setType('mobile',this)"><i class="fa fa-mobile"></i> Mobile OTP</button>
      <button class="method-tab"     onclick="setType('number',this)"><i class="fa fa-barcode"></i> ABHA Number</button>
      <button class="method-tab"     onclick="setType('address',this)"><i class="fa fa-at"></i> ABHA Address</button>
    </div>
    <div id="aadhaarNote" class="alert alert-info mb-3" style="font-size:.78rem;border-radius:8px;">
      <i class="fa fa-lock mr-1"></i> Aadhaar is RSA-encrypted before sending to ABDM. <strong>Never stored</strong> in our DB.
    </div>
    <div class="form-group">
      <label class="form-label-sm" id="inputLabel">Aadhaar Number (12 digits)</label>
      <div class="input-group" style="max-width:300px;">
        <div class="input-group-prepend" id="mobilePrefix" style="display:none;">
          <span class="input-group-text">+91</span>
        </div>
        <input type="password" id="mainInput" class="form-control" placeholder="•••• •••• ••••"
               maxlength="12" autocomplete="off" style="font-size:1rem;letter-spacing:2px;">
      </div>
      <small class="text-muted" id="inputHint">12-digit Aadhaar — OTP sent to Aadhaar-linked mobile</small>
    </div>
    <button class="btn btn-primary" id="btnSend">
      <i class="fa fa-paper-plane mr-1"></i> Send OTP
    </button>
    <div id="err1" class="alert alert-danger mt-3" style="display:none;border-radius:8px;font-size:.84rem;"></div>
  </div>
</div>

<!-- Step 2: OTP entry -->
<div class="sbody" id="step2">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-mobile mr-2" style="color:#0C74C5;"></i>Enter OTP</div>
    <p class="text-center text-muted" id="sentMsg" style="font-size:.86rem;"></p>
    <div class="otp-row">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
      <input type="text" class="otp-box" maxlength="1" inputmode="numeric">
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <button class="btn btn-link btn-sm p-0" id="btnResend" disabled style="font-size:.76rem;">
        <i class="fa fa-refresh mr-1"></i>Resend OTP
      </button>
      <span id="timerEl" style="font-size:.74rem;color:#9ca3af;"></span>
    </div>
    <div id="err2" class="alert alert-danger mt-2" style="display:none;border-radius:8px;font-size:.84rem;"></div>
    <div class="d-flex mt-3" style="gap:10px;">
      <button class="btn btn-outline-secondary btn-sm" onclick="goStep(1)"><i class="fa fa-arrow-left mr-1"></i>Back</button>
      <button class="btn btn-primary" id="btnVerify"><i class="fa fa-check mr-1"></i> Verify OTP</button>
    </div>
  </div>
</div>

<!-- Step 3: result -->
<div class="sbody" id="step3">
  <div id="step3Inner">
    <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x" style="color:#0C74C5;"></i></div>
  </div>
</div>

</div></div>

<?php endif; ?>
</main>

<script>
const BASE='<?= BASE_URL ?>';
let txnId='', vType='aadhaar';

/* ── OTP box wiring ── */
document.querySelectorAll('.otp-box').forEach((el,i,all)=>{
  el.addEventListener('input',()=>{el.value=el.value.replace(/\D/g,'').slice(-1);if(el.value&&all[i+1])all[i+1].focus();});
  el.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!el.value&&all[i-1])all[i-1].focus();});
  el.addEventListener('paste',e=>{
    const d=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    all.forEach((b,j)=>{b.value=d[j]||'';});all[Math.min(5,d.length-1)||0].focus();e.preventDefault();
  });
});
function getOtp(){return [...document.querySelectorAll('.otp-box')].map(e=>e.value).join('');}

/* ── Timer ── */
function startTimer(secs){
  document.getElementById('btnResend').disabled=true;
  const el=document.getElementById('timerEl');
  let t=secs;
  const iv=setInterval(()=>{
    el.textContent='Resend in '+t+'s';
    if(--t<0){clearInterval(iv);el.textContent='';document.getElementById('btnResend').disabled=false;}
  },1000);
}

/* ── Method switcher ── */
function setType(type,btn){
  document.querySelectorAll('.method-tab').forEach(b=>b.classList.remove('sel'));
  btn.classList.add('sel');
  vType=type;
  const inp=document.getElementById('mainInput');
  const pfx=document.getElementById('mobilePrefix');
  const note=document.getElementById('aadhaarNote');
  inp.value=''; inp.type='text'; inp.style.letterSpacing='';
  pfx.style.display='none'; note.style.display='none';
  if(type==='aadhaar'){
    document.getElementById('inputLabel').textContent='Aadhaar Number (12 digits)';
    inp.placeholder='•••• •••• ••••';inp.maxLength=12;inp.type='password';inp.style.letterSpacing='2px';
    document.getElementById('inputHint').textContent='OTP sent to Aadhaar-linked mobile';
    note.style.display='block';
  } else if(type==='mobile'){
    document.getElementById('inputLabel').textContent='Mobile Number';
    pfx.style.display='flex';inp.placeholder='9876543210';inp.maxLength=10;inp.inputMode='numeric';
    document.getElementById('inputHint').textContent='Mobile registered with patient\'s ABHA account';
  } else if(type==='number'){
    document.getElementById('inputLabel').textContent='ABHA Number';
    inp.placeholder='XX-XXXX-XXXX-XXXX';inp.maxLength=17;inp.style.letterSpacing='1px';
    document.getElementById('inputHint').textContent='14 digits on patient\'s ABHA card';
  } else {
    document.getElementById('inputLabel').textContent='ABHA Address';
    inp.placeholder='name@abdm';inp.maxLength=60;
    document.getElementById('inputHint').textContent='e.g. john.doe@abdm';
  }
}

/* ── ABHA number auto-format ── */
document.getElementById('mainInput').addEventListener('input',function(){
  if(vType!=='number')return;
  let d=this.value.replace(/\D/g,'').substr(0,14),o=d;
  if(d.length>2) o=d.substr(0,2)+'-'+d.substr(2);
  if(d.length>6) o=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6);
  if(d.length>10)o=d.substr(0,2)+'-'+d.substr(2,4)+'-'+d.substr(6,4)+'-'+d.substr(10);
  this.value=o;
});

function goStep(n){
  ['step1','step2','step3'].forEach((id,i)=>{
    document.getElementById(id).classList.toggle('active',i===n-1);
  });
  ['si1','si2','si3'].forEach((id,i)=>{
    const el=document.getElementById(id);
    if(i<n-1) el.className='wi done'; else if(i===n-1) el.className='wi active'; else el.className='wi';
  });
}

function showErr(id,msg){const el=document.getElementById(id);el.style.display='block';el.textContent=msg;}
function hideErr(id){document.getElementById(id).style.display='none';}

/* ── Send OTP ── */
document.getElementById('btnSend').addEventListener('click',function(){
  const val=document.getElementById('mainInput').value.trim();
  if(vType==='aadhaar'&&val.replace(/\D/g,'').length!==12){showErr('err1','Aadhaar must be 12 digits');return;}
  if(vType==='mobile'&&val.replace(/\D/g,'').length!==10){showErr('err1','Enter valid 10-digit mobile');return;}
  if(vType==='number'&&val.replace(/\D/g,'').length!==14){showErr('err1','ABHA number must be 14 digits');return;}
  if(vType==='address'&&!val){showErr('err1','Enter ABHA address');return;}
  hideErr('err1');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Sending…';
  fetch(BASE+'doctor/api/abha-otp-send.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({abha_input:val,type:vType})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP';
    if(!data.success){showErr('err1',data.error||'Failed to send OTP');return;}
    txnId=data.txnId;
    document.getElementById('sentMsg').textContent=data.message||'OTP sent to registered mobile.';
    document.querySelectorAll('.otp-box').forEach(i=>i.value='');
    goStep(2);startTimer(300);
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP';showErr('err1','Network error');});
});

document.getElementById('btnResend').addEventListener('click',()=>document.getElementById('btnSend').click());

/* ── Verify OTP ── */
document.getElementById('btnVerify').addEventListener('click',function(){
  const otp=getOtp();
  if(otp.length<6){showErr('err2','Enter the complete 6-digit OTP');return;}
  if(!txnId){showErr('err2','Session expired — resend OTP');return;}
  hideErr('err2');
  const btn=this;btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin mr-1"></i> Verifying…';
  fetch(BASE+'doctor/api/abha-otp-verify.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({txnId:txnId,otp:otp})})
  .then(r=>r.json()).then(data=>{
    btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    if(!data.success){showErr('err2',data.error||'OTP verification failed');return;}
    goStep(3);
    // Redirect straight to patient profile
    window.location=BASE+'doctor/patient-profile.php?id='+data.patient_id;
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';showErr('err2','Network error');});
});
</script>
</body>
</html>
