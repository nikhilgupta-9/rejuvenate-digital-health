/*
 * otp-widget.js — reusable WhatsApp + email mobile-OTP gate for registration forms.
 *
 * Render a container with util/otp-widget.php (or hand-write the markup) carrying:
 *   data-otp-widget
 *   data-role            patient | doctor | student | teacher | school_admin
 *   data-send-url        endpoint for { role, mobile, email, name } -> { success, debug_otp? }
 *   data-verify-url      endpoint for { role, mobile, otp } -> { success, token }
 *   data-mobile-field    name= of the mobile <input> in the same form
 *   data-email-field     (optional) name= of the email <input>
 *   data-name-field      (optional) name= of a name <input> for the email greeting
 *   data-submit-selector (optional) selector for the button to keep disabled until verified
 *   data-token-field     name= of the hidden <input> that receives the verify token
 *   data-allow-existing  (optional "1") treat "already registered" as pass-through (staff add-patient)
 *
 * On success the hidden token field is filled, the mobile field + Send button are
 * locked, and the submit button is enabled. Editing the mobile field resets everything.
 */
(function () {
  'use strict';

  function digits(s) { return (s || '').replace(/\D/g, ''); }

  function initWidget(box) {
    var form = box.closest('form');
    if (!form) return;

    var role       = box.getAttribute('data-role') || '';
    var sendUrl    = box.getAttribute('data-send-url');
    var verifyUrl  = box.getAttribute('data-verify-url');
    var mobileName = box.getAttribute('data-mobile-field') || 'mobile';
    var emailName  = box.getAttribute('data-email-field') || '';
    var nameName   = box.getAttribute('data-name-field') || '';
    var submitSel  = box.getAttribute('data-submit-selector') || '';
    var allowExist = box.getAttribute('data-allow-existing') === '1';
    var optional   = box.getAttribute('data-optional') === '1';

    var mobileEl = form.querySelector('[name="' + mobileName + '"]');
    var emailEl  = emailName ? form.querySelector('[name="' + emailName + '"]') : null;
    var nameEl   = nameName ? form.querySelector('[name="' + nameName + '"]') : null;
    var submitEl = submitSel ? form.querySelector(submitSel) : null;

    var sendBtn   = box.querySelector('.otp-w-send');
    var verifyRow = box.querySelector('.otp-w-verify');
    var codeEl    = box.querySelector('.otp-w-code');
    var verifyBtn = box.querySelector('.otp-w-verify-btn');
    var resendBtn = box.querySelector('.otp-w-resend');
    var timerEl   = box.querySelector('.otp-w-timer');
    var msgEl     = box.querySelector('.otp-w-msg');
    var tokenEl   = box.querySelector('input[type="hidden"]');

    var verified = false;
    var timer = null;

    function msg(text, kind) {
      msgEl.textContent = text || '';
      msgEl.className = 'otp-w-msg' + (kind ? ' otp-w-msg-' + kind : '');
    }

    function setSubmitEnabled(on) {
      if (submitEl) submitEl.disabled = !on;
    }

    function lockVerified() {
      verified = true;
      if (mobileEl) mobileEl.readOnly = true;
      sendBtn.disabled = true;
      sendBtn.style.display = 'none';
      verifyRow.style.display = 'none';
      msg('✓ Mobile number verified', 'ok');
      setSubmitEnabled(true);
    }

    function reset() {
      verified = false;
      if (tokenEl) tokenEl.value = '';
      if (mobileEl) mobileEl.readOnly = false;
      sendBtn.disabled = false;
      sendBtn.style.display = '';
      verifyRow.style.display = 'none';
      if (codeEl) codeEl.value = '';
      msg('');
      setSubmitEnabled(false);
      if (timer) { clearInterval(timer); timer = null; }
    }

    function startCooldown(secs) {
      var left = secs || 60;
      resendBtn.disabled = true;
      timerEl.textContent = left;
      if (timer) clearInterval(timer);
      timer = setInterval(function () {
        left--;
        timerEl.textContent = left > 0 ? left : 0;
        if (left <= 0) {
          clearInterval(timer); timer = null;
          resendBtn.disabled = false;
        }
      }, 1000);
    }

    function payloadBase() {
      return {
        role: role,
        mobile: digits(mobileEl && mobileEl.value),
        email: emailEl ? emailEl.value.trim() : '',
        name: nameEl ? nameEl.value.trim() : ''
      };
    }

    function post(url, body) {
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (r) { return r.json(); });
    }

    function doSend() {
      var mob = digits(mobileEl && mobileEl.value);
      if (mob.length !== 10) { msg('Enter a valid 10-digit mobile number', 'err'); return; }

      sendBtn.disabled = true;
      resendBtn.disabled = true;
      var original = sendBtn.textContent;
      sendBtn.textContent = 'Sending…';
      msg('');

      post(sendUrl, payloadBase()).then(function (d) {
        sendBtn.textContent = original;
        if (!d.success) {
          if (d.already_registered && allowExist) {
            // Staff flow: existing account will be linked without OTP.
            verified = true;
            verifyRow.style.display = 'none';
            sendBtn.disabled = true;
            sendBtn.style.display = 'none';
            msg(d.error || 'Existing patient — will be linked.', 'ok');
            setSubmitEnabled(true);
            return;
          }
          sendBtn.disabled = false;
          msg(d.error || 'Could not send code', 'err');
          return;
        }
        verifyRow.style.display = '';
        if (codeEl) { codeEl.focus(); }
        var via = [];
        if (d.channels && d.channels.whatsapp) via.push('WhatsApp');
        if (d.channels && d.channels.email) via.push('email');
        var line = 'Code sent' + (via.length ? ' via ' + via.join(' + ') : '') + '.';
        if (d.debug_otp) line += ' (dev code: ' + d.debug_otp + ')';
        msg(line, 'ok');
        startCooldown(60);
      }).catch(function () {
        sendBtn.textContent = original;
        sendBtn.disabled = false;
        msg('Network error. Try again.', 'err');
      });
    }

    function doVerify() {
      var code = digits(codeEl && codeEl.value);
      if (code.length !== 6) { msg('Enter the 6-digit code', 'err'); return; }

      verifyBtn.disabled = true;
      var original = verifyBtn.textContent;
      verifyBtn.textContent = 'Verifying…';

      var body = payloadBase();
      body.otp = code;

      post(verifyUrl, body).then(function (d) {
        verifyBtn.textContent = original;
        verifyBtn.disabled = false;
        if (!d.success) { msg(d.error || 'Verification failed', 'err'); return; }
        if (tokenEl) tokenEl.value = d.token || '';
        if (timer) { clearInterval(timer); timer = null; }
        lockVerified();
      }).catch(function () {
        verifyBtn.textContent = original;
        verifyBtn.disabled = false;
        msg('Network error. Try again.', 'err');
      });
    }

    sendBtn.addEventListener('click', doSend);
    verifyBtn.addEventListener('click', doVerify);
    resendBtn.addEventListener('click', function () { if (!resendBtn.disabled) doSend(); });
    if (codeEl) {
      codeEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doVerify(); } });
    }
    if (mobileEl) {
      mobileEl.addEventListener('input', function () { if (verified || tokenEl.value) reset(); });
    }

    // Block submit until verified — unless this widget is optional (server-side
    // enforces, e.g. admin add-customer where a manual override is allowed).
    if (!optional) {
      form.addEventListener('submit', function (e) {
        if (!verified && !(tokenEl && tokenEl.value)) {
          e.preventDefault();
          msg('Please verify the mobile number first', 'err');
          box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
      setSubmitEnabled(false);
    }
  }

  function boot() {
    var boxes = document.querySelectorAll('[data-otp-widget]');
    for (var i = 0; i < boxes.length; i++) initWidget(boxes[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
