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
<title>Find Patient by Mobile — Rejuvenate</title>
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
.abha-card{border:2px solid #e5e7eb;border-radius:12px;padding:16px 18px;margin-bottom:10px;
  cursor:pointer;transition:.2s;background:#f9fafb;}
.abha-card:hover,.abha-card.selected{border-color:#02c9b8;background:#f0fffe;}
.abha-card.selected .abha-radio{background:#02c9b8;border-color:#02c9b8;}
.abha-card.selected .abha-radio::after{display:block;}
.abha-radio{width:18px;height:18px;border-radius:50%;border:2px solid #d1d5db;background:#fff;
  position:relative;flex-shrink:0;transition:.2s;}
.abha-radio::after{content:'';display:none;width:8px;height:8px;border-radius:50%;
  background:#fff;position:absolute;top:3px;left:3px;}
.profile-avatar{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb;}
.profile-avatar-placeholder{width:72px;height:72px;border-radius:50%;background:#e5e7eb;
  display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#9ca3af;}
.badge-verified{background:#dcfce7;color:#16a34a;border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:700;}
.badge-unverified{background:#fef3c7;color:#92400e;border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:700;}
.field-label{font-size:.72rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px;}
.field-val{font-size:.9rem;color:#1f2937;font-weight:500;}
.spinner-sm{display:inline-block;width:16px;height:16px;border:2px solid #f3f4f6;
  border-top-color:#02c9b8;border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
</style>
</head>
<body>
<main class="doctor-content">

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="<?= BASE_URL ?>doctor/add-patient.php" style="color:#6b7280;font-size:.82rem;font-weight:600;">
      <i class="fa fa-arrow-left mr-1"></i> Add Patient
    </a>
    <h5 class="mb-0 font-weight-bold mt-1" style="color:#1f2937;">Find Patient by Mobile</h5>
    <div style="font-size:.74rem;color:#9ca3af;">Look up existing ABHA accounts linked to a mobile number</div>
  </div>
</div>

<?php if (!ABDM_CONFIGURED): ?>
<div class="alert alert-danger"><i class="fa fa-times-circle mr-2"></i>ABDM not configured on this server.</div>
<?php else: ?>

<div class="row"><div class="col-lg-7">

<!-- Wizard steps -->
<div class="wizard">
  <div class="wi active" id="si1"><span class="wc">1</span><span class="wl">Mobile</span></div>
  <div class="wi" id="si2"><span class="wc">2</span><span class="wl">Select ABHA</span></div>
  <div class="wi" id="si3"><span class="wc">3</span><span class="wl">Verify OTP</span></div>
  <div class="wi" id="si4"><span class="wc">4</span><span class="wl">Profile</span></div>
</div>

<!-- ─── Step 1: Enter mobile ─── -->
<div class="sbody active" id="step1">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-mobile mr-1"></i> Patient Mobile Number</div>
    <div class="form-group mb-3">
      <label class="form-label-sm" style="font-size:.78rem;font-weight:600;color:#374151;">Mobile Number</label>
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text" style="background:#f9fafb;border-color:#e5e7eb;color:#6b7280;font-weight:600;">+91</span>
        </div>
        <input type="tel" id="mobileInput" class="form-control" placeholder="10-digit mobile" maxlength="10"
          style="border-color:#e5e7eb;font-size:.95rem;letter-spacing:1px;"
          inputmode="numeric" pattern="[0-9]{10}">
      </div>
      <div id="err1" class="text-danger mt-2" style="font-size:.8rem;display:none;"></div>
    </div>
    <button id="btnSearch" class="btn btn-block" style="background:#02c9b8;color:#fff;border-radius:10px;font-weight:600;">
      <i class="fa fa-search mr-1"></i> Search ABHA Accounts
    </button>
  </div>
</div>

<!-- ─── Step 2: Account list ─── -->
<div class="sbody" id="step2">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-id-card mr-1"></i> Select ABHA Account</div>
    <p style="font-size:.82rem;color:#6b7280;margin-bottom:14px;">
      The following ABHA accounts are linked to this mobile. Ask the patient to select theirs.
    </p>
    <div id="accountList"></div>
    <div id="err2" class="alert alert-danger mt-2" style="display:none;font-size:.82rem;"></div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-outline-secondary btn-sm" onclick="goStep(1)" style="border-radius:8px;">
        <i class="fa fa-arrow-left mr-1"></i> Back
      </button>
      <button id="btnSelectAbha" class="btn btn-sm" style="background:#02c9b8;color:#fff;border-radius:8px;font-weight:600;">
        <i class="fa fa-paper-plane mr-1"></i> Send OTP to Mobile
      </button>
    </div>
  </div>
</div>

<!-- ─── Step 3: OTP entry ─── -->
<div class="sbody" id="step3">
  <div class="cp-card">
    <div class="cp-title"><i class="fa fa-lock mr-1"></i> Verify OTP</div>
    <p id="otpSentMsg" style="font-size:.82rem;color:#6b7280;margin-bottom:8px;">OTP sent to patient's registered mobile.</p>
    <div class="otp-row">
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
    </div>
    <div id="err3" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>
    <div class="d-flex justify-content-between align-items-center mt-2">
      <small><span id="timerEl" style="color:#6b7280;font-size:.78rem;"></span></small>
      <button id="btnResendOtp" class="btn btn-link btn-sm p-0" style="font-size:.8rem;" disabled>Resend OTP</button>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-outline-secondary btn-sm" onclick="goStep(2)" style="border-radius:8px;">
        <i class="fa fa-arrow-left mr-1"></i> Back
      </button>
      <button id="btnVerifyOtp" class="btn btn-sm" style="background:#02c9b8;color:#fff;border-radius:8px;font-weight:600;">
        <i class="fa fa-check mr-1"></i> Verify OTP
      </button>
    </div>
  </div>
</div>

<!-- ─── Step 4: Profile ─── -->
<div class="sbody" id="step4">
  <div id="step4Inner">
    <div class="cp-card text-center py-4">
      <div class="spinner-sm" style="width:32px;height:32px;border-width:3px;"></div>
      <div class="mt-3 text-muted" style="font-size:.85rem;">Fetching ABHA profile…</div>
    </div>
  </div>
</div>

</div><!-- /col -->

<!-- Right panel: help -->
<div class="col-lg-5 d-none d-lg-block">
  <div class="cp-card" style="background:linear-gradient(135deg,#f0fffe 0%,#f9fafb 100%);">
    <div class="cp-title" style="color:#02c9b8;">About this flow</div>
    <div style="font-size:.82rem;color:#374151;line-height:1.7;">
      <div class="mb-2"><i class="fa fa-circle" style="color:#02c9b8;font-size:.5rem;vertical-align:middle;"></i>
        <strong>Step 1</strong> — Enter the patient's mobile number to find linked ABHA accounts.</div>
      <div class="mb-2"><i class="fa fa-circle" style="color:#02c9b8;font-size:.5rem;vertical-align:middle;"></i>
        <strong>Step 2</strong> — Patient selects their ABHA from the list (numbers are masked for privacy).</div>
      <div class="mb-2"><i class="fa fa-circle" style="color:#02c9b8;font-size:.5rem;vertical-align:middle;"></i>
        <strong>Step 3</strong> — An OTP is sent to the mobile; patient verifies identity.</div>
      <div class="mb-2"><i class="fa fa-circle" style="color:#02c9b8;font-size:.5rem;vertical-align:middle;"></i>
        <strong>Step 4</strong> — Full ABHA profile is shown. Save to link the patient to your panel.</div>
    </div>
    <hr style="border-color:#e5e7eb;">
    <div style="font-size:.76rem;color:#9ca3af;">
      <i class="fa fa-shield mr-1"></i> All data is fetched directly from ABDM sandbox with patient consent (OTP).
    </div>
  </div>
</div>
</div><!-- /row -->

<?php endif; ?>
</main>

<script>
const BASE = '<?= BASE_URL ?>';
let selectedIndex = 0;
let searchTxnId   = '';

/* ── OTP box keyboard wiring ── */
document.querySelectorAll('.otp-box').forEach((el,i,all)=>{
  el.addEventListener('input',()=>{
    el.value=el.value.replace(/\D/g,'').slice(-1);
    if(el.value&&all[i+1]) all[i+1].focus();
  });
  el.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!el.value&&all[i-1]) all[i-1].focus();});
  el.addEventListener('paste',e=>{
    const d=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    all.forEach((b,j)=>{b.value=d[j]||'';});
    all[Math.min(5,d.length-1)||0].focus();
    e.preventDefault();
  });
});
function getOtp(){return [...document.querySelectorAll('.otp-box')].map(e=>e.value).join('');}

function goStep(n){
  ['step1','step2','step3','step4'].forEach((id,i)=>
    document.getElementById(id).classList.toggle('active',i===n-1)
  );
  ['si1','si2','si3','si4'].forEach((id,i)=>{
    const el=document.getElementById(id);
    if(i<n-1)      el.className='wi done';
    else if(i===n-1) el.className='wi active';
    else             el.className='wi';
  });
}
function showErr(id,msg){const e=document.getElementById(id);e.style.display='block';e.textContent=msg;}
function hideErr(id){document.getElementById(id).style.display='none';}

function startTimer(s){
  document.getElementById('btnResendOtp').disabled=true;
  const el=document.getElementById('timerEl');let t=s;
  const iv=setInterval(()=>{
    el.textContent='Resend in '+t+'s';
    if(--t<0){clearInterval(iv);el.textContent='';document.getElementById('btnResendOtp').disabled=false;}
  },1000);
}

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

/* ── Step 1: Search by mobile ── */
document.getElementById('btnSearch').addEventListener('click',function(){
  const mob=document.getElementById('mobileInput').value.replace(/\D/g,'');
  if(!mob||mob.length!==10||!/^[6-9]/.test(mob)){
    showErr('err1','Enter a valid 10-digit Indian mobile number');return;
  }
  hideErr('err1');
  const btn=this;
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-sm mr-2"></span>Searching…';

  fetch(BASE+'doctor/api/abdm_search_mobile.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({mobile:mob})
  })
  .then(r=>r.json())
  .then(data=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-search mr-1"></i> Search ABHA Accounts';
    if(!data.success){showErr('err1',data.error||'Search failed');return;}
    if(!data.accounts||!data.accounts.length){
      showErr('err1','No ABHA accounts found linked to this mobile number.');return;
    }

    searchTxnId=data.txnId||'';
    renderAccountList(data.accounts);
    hideErr('err2');
    goStep(2);
  })
  .catch(()=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-search mr-1"></i> Search ABHA Accounts';
    showErr('err1','Network error — please retry');
  });
});

function renderAccountList(accounts){
  const wrap=document.getElementById('accountList');
  wrap.innerHTML=accounts.map((a,i)=>`
    <div class="abha-card d-flex align-items-center gap-3" onclick="selectAbhaCard(this,${a.index})" data-index="${a.index}">
      <div class="abha-radio"></div>
      <div style="flex:1;min-width:0;">
        <div style="font-family:monospace;font-size:.9rem;font-weight:700;color:#1f2937;">${esc(a.masked||a.ABHANumber)}</div>
        ${a.name?`<div style="font-size:.8rem;color:#6b7280;margin-top:2px;">${esc(a.name)}</div>`:''}
        ${a.gender?`<div style="font-size:.75rem;color:#9ca3af;">${esc(a.gender==='M'?'Male':a.gender==='F'?'Female':a.gender)}</div>`:''}
      </div>
      <i class="fa fa-chevron-right" style="color:#d1d5db;font-size:.75rem;"></i>
    </div>
  `).join('');
  /* Auto-select if only one */
  if(accounts.length===1) selectAbhaCard(wrap.querySelector('.abha-card'),accounts[0].index);
}

window.selectAbhaCard=function(el,idx){
  document.querySelectorAll('.abha-card').forEach(c=>c.classList.remove('selected'));
  el.classList.add('selected');
  selectedIndex=idx;
};

/* ── Step 2: Send OTP for selected index ── */
document.getElementById('btnSelectAbha').addEventListener('click',function(){
  if(!selectedIndex){showErr('err2','Please select an ABHA account first');return;}
  hideErr('err2');
  const btn=this;
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-sm mr-1"></span>Sending OTP…';

  fetch(BASE+'doctor/api/abdm_request_index_otp.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({index:selectedIndex,txnId:searchTxnId})
  })
  .then(r=>r.json())
  .then(data=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Mobile';
    if(!data.success){showErr('err2',data.error||'Failed to send OTP');return;}
    document.getElementById('otpSentMsg').textContent=
      data.message||'OTP sent to the registered mobile number.';
    document.querySelectorAll('.otp-box').forEach(b=>b.value='');
    goStep(3);
    startTimer(300);
  })
  .catch(()=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-paper-plane mr-1"></i> Send OTP to Mobile';
    showErr('err2','Network error — please retry');
  });
});

/* Resend — repeat step 2 request */
document.getElementById('btnResendOtp').addEventListener('click',()=>
  document.getElementById('btnSelectAbha').click()
);

/* ── Step 3: Verify OTP ── */
document.getElementById('btnVerifyOtp').addEventListener('click',function(){
  const otp=getOtp();
  if(otp.length<6){showErr('err3','Enter the complete 6-digit OTP');return;}
  hideErr('err3');
  const btn=this;
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-sm mr-1"></span>Verifying…';

  fetch(BASE+'doctor/api/abdm_verify_login_otp.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({otp:otp})
  })
  .then(r=>r.json())
  .then(data=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    if(!data.success){showErr('err3',data.error||'OTP verification failed');return;}
    goStep(4);
    fetchProfile();
  })
  .catch(()=>{
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-check mr-1"></i> Verify OTP';
    showErr('err3','Network error — please retry');
  });
});

/* ── Step 4: Fetch + display profile ── */
function fetchProfile(){
  fetch(BASE+'doctor/api/abdm_get_profile.php',{
    headers:{'Content-Type':'application/json'}
  })
  .then(r=>r.json())
  .then(data=>{
    if(!data.success){
      document.getElementById('step4Inner').innerHTML=
        `<div class="cp-card text-center py-4 text-danger">
           <i class="fa fa-times-circle fa-2x mb-2"></i>
           <div>${esc(data.error||'Failed to fetch profile')}</div>
           <button class="btn btn-sm btn-outline-secondary mt-3" onclick="goStep(3)">
             <i class="fa fa-arrow-left mr-1"></i> Back
           </button>
         </div>`;
      return;
    }
    renderProfile(data.profile);
  })
  .catch(()=>{
    document.getElementById('step4Inner').innerHTML=
      `<div class="cp-card text-center py-4 text-danger">
         <i class="fa fa-times-circle fa-2x mb-2"></i>
         <div>Network error fetching profile.</div>
         <button class="btn btn-sm btn-outline-secondary mt-3" onclick="fetchProfile()">Retry</button>
       </div>`;
  });
}

function renderProfile(p){
  const gender = p.gender==='M'?'Male':p.gender==='F'?'Female':(p.gender||'—');
  const verified = (p.verificationStatus||'').toUpperCase()==='VERIFIED';
  const badgeCls = verified?'badge-verified':'badge-unverified';
  const badgeTxt = verified?'<i class="fa fa-check mr-1"></i>KYC Verified':'Unverified';
  const photoHtml = p.photo
    ? `<img src="data:image/jpeg;base64,${p.photo}" class="profile-avatar" alt="Photo">`
    : `<div class="profile-avatar-placeholder"><i class="fa fa-user"></i></div>`;

  document.getElementById('step4Inner').innerHTML=`
    <div class="cp-card">
      <div class="cp-title"><i class="fa fa-id-badge mr-1"></i> ABHA Profile</div>
      <div class="d-flex align-items-center mb-4" style="gap:16px;">
        ${photoHtml}
        <div>
          <div style="font-size:1.1rem;font-weight:700;color:#1f2937;">${esc(p.name||'—')}</div>
          <div style="font-family:monospace;font-size:.82rem;color:#0C74C5;margin-top:2px;">${esc(p.ABHANumber||'—')}</div>
          <span class="${badgeCls} mt-1 d-inline-block">${badgeTxt}</span>
        </div>
      </div>
      <div class="row" style="row-gap:14px;">
        <div class="col-6">
          <div class="field-label">ABHA Address</div>
          <div class="field-val" style="font-family:monospace;">${esc(p.abhaAddress||'—')}</div>
        </div>
        <div class="col-6">
          <div class="field-label">Gender</div>
          <div class="field-val">${esc(gender)}</div>
        </div>
        <div class="col-6">
          <div class="field-label">Date of Birth</div>
          <div class="field-val">${esc(p.dob||'—')}</div>
        </div>
        <div class="col-6">
          <div class="field-label">Mobile</div>
          <div class="field-val">${p.mobile?esc(p.mobile.replace(/(\d{2})(\d{4})(\d{4})/,'$1****$3')):'—'}</div>
        </div>
        ${p.email?`<div class="col-12"><div class="field-label">Email</div>
          <div class="field-val">${esc(p.email)}</div></div>`:''}
        ${p.districtName||p.stateCode?`<div class="col-12"><div class="field-label">Location</div>
          <div class="field-val">${esc([p.districtName,p.stateCode].filter(Boolean).join(', '))}</div></div>`:''}
      </div>
      <div id="saveResult" class="mt-3"></div>
      <div class="d-flex gap-2 mt-4">
        <button class="btn btn-outline-secondary btn-sm" onclick="goStep(1);resetFlow();" style="border-radius:8px;">
          <i class="fa fa-search mr-1"></i> Search Another
        </button>
        <button id="btnSave" class="btn btn-sm" style="background:#16a34a;color:#fff;border-radius:8px;font-weight:600;"
          onclick="savePatient()">
          <i class="fa fa-user-plus mr-1"></i> Save to My Patients
        </button>
      </div>
    </div>
  `;
}

function savePatient(){
  const btn=document.getElementById('btnSave');
  if(!btn) return;
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-sm mr-1"></span>Saving…';

  fetch(BASE+'doctor/api/abdm_save_patient.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:'{}'
  })
  .then(r=>r.json())
  .then(data=>{
    const el=document.getElementById('saveResult');
    if(data.success){
      el.innerHTML=`<div class="alert alert-success py-2 mb-0" style="font-size:.83rem;">
        <i class="fa fa-check-circle mr-1"></i>
        ${esc(data.message||'Patient saved.')}
        ${data.patient_id
          ?` <a href="${BASE}doctor/patient-profile.php?id=${data.patient_id}&new=1"
               class="font-weight-bold ml-2">View Profile &rarr;</a>`:''}
      </div>`;
      btn.remove();
    } else {
      el.innerHTML=`<div class="alert alert-danger py-2 mb-0" style="font-size:.83rem;">
        <i class="fa fa-times-circle mr-1"></i>${esc(data.error||'Save failed')}
      </div>`;
      btn.disabled=false;
      btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Save to My Patients';
    }
  })
  .catch(()=>{
    document.getElementById('saveResult').innerHTML=
      `<div class="alert alert-danger py-2 mb-0" style="font-size:.83rem;">Network error — please retry.</div>`;
    btn.disabled=false;
    btn.innerHTML='<i class="fa fa-user-plus mr-1"></i> Save to My Patients';
  });
}

function resetFlow(){
  selectedIndex=0;
  searchTxnId='';
  document.getElementById('mobileInput').value='';
  document.getElementById('accountList').innerHTML='';
  document.querySelectorAll('.otp-box').forEach(b=>b.value='');
  document.getElementById('step4Inner').innerHTML=
    '<div class="cp-card text-center py-4"><div class="spinner-sm" style="width:32px;height:32px;border-width:3px;"></div></div>';
  ['err1','err2','err3'].forEach(id=>hideErr(id));
}
</script>
</body>
</html>
