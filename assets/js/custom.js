const BASE_URL = "http://localhost/rejuvenate-digital-health/";

function verifyAbha() {
  const abhaId = document.querySelector('input[name="abha_id"]').value;
  if (!abhaId || abhaId.length < 14) {
    alert('Valid 14-digit ABHA number enter karo');
    return;
  }
  // Yahan ABDM sandbox API call hogi
  fetch(BASE_URL + 'ajax/abha-verify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ abha_id: abhaId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('abha-status').style.display = 'block';
    } else {
      alert('ABHA verification failed: ' + data.message);
    }
  });
}


// ABHA Step 1: OTP bhejo
function loginWithAbha() {
  const abhaId = document.getElementById('abha_login_id').value.trim();
  
  // Basic format check: 14 digits (with or without dashes)
  const clean = abhaId.replace(/-/g, '');
  if (clean.length !== 14 || isNaN(clean)) {
    showAbhaMsg('Valid 14-digit ABHA number enter karo', 'danger');
    return;
  }

  showAbhaMsg('OTP bheja ja raha hai...', 'info');

  fetch(BASE_URL+'ajax/abha-send-otp.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ abha_id: abhaId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('abha-otp-section').style.display = 'block';
      showAbhaMsg('OTP aapke registered mobile par bheja gaya', 'success');
      // txnId save karo verify ke liye
      document.getElementById('abha-otp-section').dataset.txn = data.txnId;
    } else {
      showAbhaMsg(data.message || 'OTP bhejne mein error', 'danger');
    }
  })
  .catch(() => showAbhaMsg('Server se connect nahi ho pa raha', 'danger'));
}

// ABHA Step 2: OTP verify karke login
function verifyAbhaLogin() {
  const otp   = document.getElementById('abha_otp').value.trim();
  const txnId = document.getElementById('abha-otp-section').dataset.txn;
  const abhaId = document.getElementById('abha_login_id').value.trim();

  if (otp.length !== 6) {
    showAbhaMsg('6-digit OTP enter karo', 'danger');
    return;
  }

  showAbhaMsg('Verify ho raha hai...', 'info');

  fetch(BASE_URL + 'ajax/abha-verify-login.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ abha_id: abhaId, otp: otp, txnId: txnId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showAbhaMsg('Login successful! Redirect ho raha hai...', 'success');
      window.location.href = data.redirect || BASE_URL + 'user/user-dashboard.php';
    } else {
      showAbhaMsg(data.message || 'OTP galat hai', 'danger');
    }
  })
  .catch(() => showAbhaMsg('Server error', 'danger'));
}

function showAbhaMsg(msg, type) {
  const el = document.getElementById('abha-msg');
  el.className = 'mt-2 small text-' + type;
  el.textContent = msg;
}


// Single function to handle both password fields
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}


function togglePasswordById(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (!passwordInput) return;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    } else {
        passwordInput.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
}