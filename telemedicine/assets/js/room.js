(function () {
  const cfg = window.TELEMED_CONFIG;
  const $ = (id) => document.getElementById(id);

  const localVideo = $('localVideo');
  const remoteVideo = $('remoteVideo');
  const waitingOverlay = $('waitingOverlay');
  const callStatus = $('callStatus');
  const chatPanel = $('chatPanel');
  const chatMessages = $('chatMessages');
  const chatForm = $('chatForm');
  const chatInput = $('chatInput');
  const micBtn = $('micBtn');
  const camBtn = $('camBtn');
  const chatBtn = $('chatBtn');
  const endBtn = $('endBtn');

  let localStream = null;
  let pc = null;
  let peerPresent = null; // null = unknown yet (before the first poll response)
  let pendingCandidates = [];
  let callEnded = false;
  let micOn = true;
  let camOn = true;

  // ── Polling state ──
  let lastId = 0;
  let pollTimer = null;
  let pollInFlight = false;

  function toast(msg) {
    let el = document.querySelector('.rt-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'rt-toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 2600);
  }

  function setStatus(state, text) {
    callStatus.className = 'call-status ' + state;
    callStatus.innerHTML = '<i class="fas fa-circle"></i> ' + text;
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function appendChatMessage(fromRole, name, message, time) {
    const mine = fromRole === cfg.role;
    const row = document.createElement('div');
    row.className = 'chat-msg ' + (mine ? 'me' : 'them');
    row.innerHTML = (mine ? '' : '<strong>' + escapeHtml(name) + '</strong><br>') +
      escapeHtml(message) + '<span class="cm-time">' + time + '</span>';
    chatMessages.appendChild(row);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  /* ── Media ── */
  async function initMedia() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      localVideo.srcObject = localStream;
    } catch (err) {
      let message = 'Camera/microphone access is required to join the call.';
      if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        message = 'No camera or microphone was found on this device.';
      } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        message = 'Camera/microphone permission was denied.';
      } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
        message = 'Your camera/microphone is already in use by another application.';
      }
      toast(message);
      setStatus('ended', 'Media access denied');
      throw err;
    }
  }

  /* ── Peer connection ── */
  function createPeerConnection() {
    pc = new RTCPeerConnection({ iceServers: cfg.iceServers });

    localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));

    pc.onicecandidate = (e) => {
      if (e.candidate) {
        send({ type: 'ice-candidate', candidate: e.candidate });
      }
    };

    pc.ontrack = (e) => {
      if (remoteVideo.srcObject !== e.streams[0]) {
        remoteVideo.srcObject = e.streams[0];
      }
      waitingOverlay.classList.add('hidden');
      setStatus('connected', 'Connected');
    };

    pc.onconnectionstatechange = () => {
      if (!pc) return;
      if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
        toast('Connection interrupted — trying to recover…');
      }
    };

    return pc;
  }

  function pcHealthy() {
    return pc && ['connecting', 'connected'].includes(pc.connectionState);
  }

  async function makeOffer() {
    // Don't renegotiate a call that's already up — a duplicate 'ready'
    // (e.g. after a brief poll delay) would otherwise fire a whole new
    // ICE-candidate storm into the signaling table.
    if (pcHealthy()) return;
    if (!pc) createPeerConnection();
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    send({ type: 'offer', sdp: offer });
  }

  async function handleOffer(sdp) {
    if (!pc) createPeerConnection();
    await pc.setRemoteDescription(new RTCSessionDescription(sdp));
    await flushPendingCandidates();
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    send({ type: 'answer', sdp: answer });
  }

  async function handleAnswer(sdp) {
    if (!pc) return;
    await pc.setRemoteDescription(new RTCSessionDescription(sdp));
    await flushPendingCandidates();
  }

  async function flushPendingCandidates() {
    for (const c of pendingCandidates) {
      try { await pc.addIceCandidate(new RTCIceCandidate(c)); } catch (e) {}
    }
    pendingCandidates = [];
  }

  async function handleRemoteCandidate(candidate) {
    if (pc && pc.remoteDescription && pc.remoteDescription.type) {
      try { await pc.addIceCandidate(new RTCIceCandidate(candidate)); } catch (e) {}
    } else {
      pendingCandidates.push(candidate);
    }
  }

  function teardownPeerConnection() {
    if (pc) {
      pc.close();
      pc = null;
    }
    pendingCandidates = [];
    remoteVideo.srcObject = null;
  }

  /* ── HTTP-polling signaling (no WebSocket — works on plain shared hosting) ──
   * The browser sends signals via POST (send()) and picks up the peer's
   * signals by polling every cfg.pollIntervalMs (poll()). Video/audio itself
   * still flows directly peer-to-peer over WebRTC — only the handshake and
   * chat go through this relay. */
  function send(obj) {
    const payload = Object.assign({}, obj);
    delete payload.type;

    const fd = new FormData();
    fd.append('ticket', cfg.ticket);
    fd.append('type', obj.type);
    fd.append('payload', JSON.stringify(payload));

    return fetch(cfg.sendUrl, { method: 'POST', body: fd })
      .then((r) => r.json())
      .catch(() => ({ success: false }));
  }

  function startPolling() {
    pollOnce();
    pollTimer = setInterval(pollOnce, cfg.pollIntervalMs);
  }

  function stopPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
  }

  function pollOnce() {
    if (pollInFlight || callEnded) return;
    pollInFlight = true;
    fetch(cfg.pollUrl + '?ticket=' + encodeURIComponent(cfg.ticket) + '&since=' + lastId)
      .then((r) => r.json())
      .then(handlePollResponse)
      .catch(() => { /* transient network hiccup — next tick retries */ })
      .finally(() => { pollInFlight = false; });
  }

  async function handlePollResponse(res) {
    if (callEnded) return;

    if (!res || !res.success) {
      if (res && res.message) toast(res.message);
      stopPolling();
      setStatus('ended', 'Session expired');
      return;
    }

    if (res.lastId) lastId = res.lastId;

    const wasPresent = peerPresent;
    peerPresent = !!res.peerPresent;
    if (peerPresent !== wasPresent) {
      if (peerPresent) {
        waitingOverlay.classList.add('hidden');
      } else if (!pcHealthy()) {
        // Peer's heartbeat lapsed AND we don't have a live media connection —
        // treat it as a real drop. If the call is actually up, a short poll
        // delay under load must NOT tear it down.
        waitingOverlay.classList.remove('hidden');
        teardownPeerConnection();
        setStatus('waiting', 'Waiting for the other participant…');
      }
    }

    // Sequential, in id order — an offer must finish setting the remote
    // description before any ice-candidate that follows it is processed.
    for (const msg of (res.messages || [])) {
      await handleSignal(msg);
    }
  }

  async function handleSignal(msg) {
    const p = msg.payload || {};
    switch (msg.type) {
      case 'ready':
        // Doctor is always the WebRTC offer initiator, by convention.
        // makeOffer() itself no-ops if the call is already healthy.
        if (cfg.role === 'doctor') await makeOffer();
        break;

      case 'offer':
        await handleOffer(p.sdp);
        break;

      case 'answer':
        await handleAnswer(p.sdp);
        break;

      case 'ice-candidate':
        await handleRemoteCandidate(p.candidate);
        break;

      case 'chat':
        appendChatMessage(p.from, p.name, p.message, p.time);
        break;

      case 'peer-media':
        toast((p.name || 'The other participant') + (p.enabled ? ' turned on' : ' turned off') + ' their ' + p.kind + '.');
        break;

      case 'prescription':
        onPrescriptionSignal(p);
        break;

      case 'call-ended':
        endCallUi(p.by === cfg.role ? 'You ended the call.' : 'The call has ended.');
        break;
    }
  }

  /* ── Controls ── */
  micBtn.addEventListener('click', () => {
    if (!localStream) return;
    micOn = !micOn;
    localStream.getAudioTracks().forEach((t) => (t.enabled = micOn));
    micBtn.classList.toggle('off', !micOn);
    micBtn.innerHTML = '<i class="fas fa-microphone' + (micOn ? '' : '-slash') + '"></i>';
    send({ type: 'toggle-media', kind: 'audio', enabled: micOn });
  });

  camBtn.addEventListener('click', () => {
    if (!localStream) return;
    camOn = !camOn;
    localStream.getVideoTracks().forEach((t) => (t.enabled = camOn));
    camBtn.classList.toggle('off', !camOn);
    camBtn.innerHTML = '<i class="fas fa-video' + (camOn ? '' : '-slash') + '"></i>';
    send({ type: 'toggle-media', kind: 'video', enabled: camOn });
  });

  /* ── Side panels (chat / doctor / patient-rx) — one open at a time ── */
  const panels = () => Array.from(document.querySelectorAll('.side-panel'));
  function openPanel(id) {
    panels().forEach((el) => el.classList.toggle('open', el.id === id));
    document.body.classList.toggle('panel-open', !!id);
  }
  function closePanels() { openPanel(null); }
  document.querySelectorAll('.ctrl-btn[data-panel]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.panel;
      const isOpen = document.getElementById(id).classList.contains('open');
      openPanel(isOpen ? null : id);
      document.querySelectorAll('.ctrl-btn[data-panel]').forEach((b) =>
        b.classList.toggle('active', b.dataset.panel === id && !isOpen));
    });
  });
  document.querySelectorAll('[data-close-panel]').forEach((b) =>
    b.addEventListener('click', () => {
      closePanels();
      document.querySelectorAll('.ctrl-btn[data-panel]').forEach((x) => x.classList.remove('active'));
    }));

  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    chatInput.value = '';
    send({ type: 'chat', message: text }).then((res) => {
      if (res && res.echo) {
        appendChatMessage(res.echo.from, res.echo.name, res.echo.message, res.echo.time);
      }
    });
  });

  endBtn.addEventListener('click', () => {
    send({ type: 'end-call' });
    endCallUi('You ended the call.');
  });

  function endCallUi(message) {
    if (callEnded) return;
    callEnded = true;
    stopPolling();
    setStatus('ended', 'Call ended');
    toast(message);
    teardownPeerConnection();
    if (localStream) {
      localStream.getTracks().forEach((t) => t.stop());
    }
    setTimeout(() => {
      window.location.href = cfg.exitUrl;
    }, 1800);
  }

  window.addEventListener('beforeunload', () => {
    if (!callEnded && navigator.sendBeacon) {
      navigator.sendBeacon(
        cfg.endSessionUrl,
        new Blob([JSON.stringify({ ticket: cfg.ticket })], { type: 'application/json' })
      );
    }
  });

  /* ═══════════════════════════════════════════
     Prescription — doctor writes, patient views live
  ═══════════════════════════════════════════ */
  const RX_FIELDS = ['med_name', 'med_dose', 'med_freq', 'med_dur', 'med_instr'];

  function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function rxToast(m) { toast(m); }

  /* ---- Doctor side ---- */
  function initDoctorRx() {
    const form = $('rxForm');
    if (!form) return;
    const medsWrap = $('rxMeds');
    const pill = $('rxStatusPill');
    const hint = $('rxSaveHint');
    let seedMeds = [];
    try { seedMeds = JSON.parse(($('rxSeed') || {}).textContent || '{}').meds || []; } catch (e) {}

    function medRow(m) {
      m = m || {};
      const row = document.createElement('div');
      row.className = 'rx-med';
      row.innerHTML =
        '<input name="med_name"  placeholder="Medicine"     value="' + esc(m.name) + '">' +
        '<input name="med_dose"  placeholder="Dose"         value="' + esc(m.dose) + '">' +
        '<input name="med_freq"  placeholder="Frequency"    value="' + esc(m.frequency) + '">' +
        '<input name="med_dur"   placeholder="Duration"     value="' + esc(m.duration) + '">' +
        '<input name="med_instr" placeholder="Instructions" value="' + esc(m.instructions) + '">' +
        '<button type="button" class="rx-med-del" title="Remove"><i class="fas fa-trash"></i></button>';
      row.querySelector('.rx-med-del').addEventListener('click', () => {
        row.remove();
        if (!medsWrap.children.length) medRowAdd();
      });
      medsWrap.appendChild(row);
    }
    function medRowAdd(m) { medRow(m); }

    (seedMeds.length ? seedMeds : [{}]).forEach(medRow);
    $('rxAddMed').addEventListener('click', () => medRowAdd());

    let dirty = false, autosaveTimer = null;
    form.addEventListener('input', () => {
      dirty = true;
      hint.textContent = 'Unsaved changes';
      clearTimeout(autosaveTimer);
      // Long debounce — the draft only needs to survive an accidental tab
      // close, not stream every keystroke to the server.
      autosaveTimer = setTimeout(() => { if (dirty) saveRx('draft', true); }, 15000);
    });

    function saveRx(status, silent) {
      const fd = new FormData(form);
      fd.append('ticket', cfg.ticket);
      fd.append('status', status);
      const btns = form.querySelectorAll('.rx-btn');
      btns.forEach((b) => (b.disabled = true));
      hint.textContent = status === 'final' ? 'Signing…' : 'Saving…';
      fetch(cfg.rxUrl, { method: 'POST', body: fd })
        .then((r) => r.json())
        .then((res) => {
          btns.forEach((b) => (b.disabled = false));
          if (!res.success) { hint.textContent = ''; rxToast(res.message || 'Could not save.'); return; }
          dirty = false;
          const st = (res.rx && res.rx.status) || status;
          pill.className = 'rx-pill ' + st;
          pill.textContent = st.charAt(0).toUpperCase() + st.slice(1);
          hint.textContent = 'Saved ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          if (!silent) rxToast(status === 'final' ? 'Prescription signed & sent to the patient.' : 'Draft saved.');
        })
        .catch(() => { btns.forEach((b) => (b.disabled = false)); hint.textContent = ''; rxToast('Network error while saving.'); });
    }

    $('rxSaveDraft').addEventListener('click', () => saveRx('draft'));
    $('rxFinal').addEventListener('click', () => {
      if (confirm('Sign and send this prescription to the patient? They will see it immediately and it will be saved to their records.')) {
        saveRx('final');
      }
    });
  }

  /* ---- Patient side ---- */
  function renderRxView(rx, doctorName) {
    const box = $('rxView');
    if (!box) return;
    if (!rx) { return; }
    const v = rx.vitals || {};
    const meds = rx.medications || [];
    const vitalBits = [
      v.bp_systolic && v.bp_diastolic ? 'BP ' + esc(v.bp_systolic) + '/' + esc(v.bp_diastolic) : '',
      v.pulse ? 'Pulse ' + esc(v.pulse) : '',
      v.temperature ? 'Temp ' + esc(v.temperature) + '°F' : '',
      v.spo2 ? 'SpO₂ ' + esc(v.spo2) + '%' : '',
      v.weight_kg ? 'Wt ' + esc(v.weight_kg) + 'kg' : '',
    ].filter(Boolean).join(' · ');

    box.innerHTML =
      '<div class="rxv">' +
      '<div class="rxv-badge ' + esc(rx.status) + '">' + (rx.status === 'final' ? 'Signed prescription' : 'Draft — may still change') + '</div>' +
      (doctorName ? '<div class="rxv-doc">Dr. ' + esc(doctorName) + '</div>' : '') +
      (rx.chief_complaints ? '<div class="rxv-sec"><span>Complaints</span>' + esc(rx.chief_complaints) + '</div>' : '') +
      (rx.diagnosis ? '<div class="rxv-sec"><span>Diagnosis</span>' + esc(rx.diagnosis) + '</div>' : '') +
      (vitalBits ? '<div class="rxv-sec"><span>Vitals</span>' + vitalBits + '</div>' : '') +
      (meds.length
        ? '<div class="rxv-sec"><span>Medicines</span><table class="rxv-meds">' +
          meds.map((m) =>
            '<tr><td>' + esc(m.name) + '</td><td>' + esc(m.dose || '') + '</td><td>' + esc(m.frequency || '') +
            '</td><td>' + esc(m.duration || '') + '</td></tr>' +
            (m.instructions ? '<tr class="rxv-instr"><td colspan="4">' + esc(m.instructions) + '</td></tr>' : '')
          ).join('') + '</table></div>'
        : '') +
      (rx.advice ? '<div class="rxv-sec"><span>Advice</span>' + esc(rx.advice) + '</div>' : '') +
      (rx.follow_up_date && rx.follow_up_date !== '0000-00-00'
        ? '<div class="rxv-sec"><span>Follow-up</span>' + esc(rx.follow_up_date) + '</div>' : '') +
      (cfg.apptDetailsUrl
        ? '<a class="rxv-link" href="' + cfg.apptDetailsUrl + '" target="_blank" rel="noopener">Open in my records <i class="fas fa-arrow-up-right-from-square"></i></a>'
        : '') +
      '</div>';
  }

  function fetchRx(then) {
    fetch(cfg.rxUrl + '?ticket=' + encodeURIComponent(cfg.ticket))
      .then((r) => r.json())
      .then((res) => { if (res.success) then(res); })
      .catch(() => {});
  }

  function onPrescriptionSignal(p) {
    // Patient only — the doctor renders from their own save response.
    if (cfg.role === 'doctor') return;
    const btn = $('rxBtn');
    fetchRx((res) => {
      renderRxView(res.rx, (res.doctor && res.doctor.name) || (p && p.doctor_name));
      if (btn) {
        btn.style.display = '';
        btn.classList.add('has-rx');
      }
      openPanel('rxPanel');
      rxToast((p && p.status === 'final' ? 'Prescription received from ' : 'Draft prescription from ')
        + ((p && p.doctor_name) ? 'Dr. ' + p.doctor_name : 'your doctor'));
    });
  }

  initDoctorRx();
  // Patient: if a prescription already exists (e.g. page reload mid-call), show the button.
  if (cfg.role === 'patient') {
    fetchRx((res) => {
      if (res.rx) {
        renderRxView(res.rx, res.doctor && res.doctor.name);
        const btn = $('rxBtn');
        if (btn) { btn.style.display = ''; btn.classList.add('has-rx'); }
      }
    });
  }

  /* ── Boot ── */
  (async function boot() {
    try {
      await initMedia();
      startPolling();
    } catch (err) {
      // initMedia() already surfaced a toast + status update; stop boot here.
    }
  })();
})();
