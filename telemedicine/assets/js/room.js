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
  let ws = null;
  let peerPresent = false;
  let isInitiator = false;
  let pendingCandidates = [];
  let callEnded = false;
  let micOn = true;
  let camOn = true;
  let wsReconnectAttempts = 0;

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

  async function makeOffer() {
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

  /* ── WebSocket signaling ── */
  function send(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(obj));
    }
  }

  function connectWs() {
    const url = cfg.wsUrl + '/?ticket=' + encodeURIComponent(cfg.ticket);
    ws = new WebSocket(url);

    ws.onopen = () => {
      wsReconnectAttempts = 0;
      setStatus('waiting', 'Waiting for the other participant…');
    };

    ws.onmessage = (evt) => {
      let msg;
      try { msg = JSON.parse(evt.data); } catch (e) { return; }
      handleServerMessage(msg);
    };

    ws.onclose = () => {
      if (callEnded) return;
      setStatus('waiting', 'Reconnecting…');
      wsReconnectAttempts++;
      const delay = Math.min(1000 * wsReconnectAttempts, 5000);
      setTimeout(connectWs, delay);
    };

    ws.onerror = () => {
      /* onclose will fire right after and handle reconnect */
    };
  }

  async function handleServerMessage(msg) {
    switch (msg.type) {
      case 'joined':
        break;

      case 'peer-status':
        peerPresent = !!msg.peerPresent;
        if (peerPresent) {
          waitingOverlay.classList.add('hidden');
        } else {
          waitingOverlay.classList.remove('hidden');
          teardownPeerConnection();
          setStatus('waiting', 'Waiting for the other participant…');
        }
        break;

      case 'ready':
        isInitiator = !!msg.initiator;
        if (isInitiator) await makeOffer();
        break;

      case 'offer':
        await handleOffer(msg.sdp);
        break;

      case 'answer':
        await handleAnswer(msg.sdp);
        break;

      case 'ice-candidate':
        await handleRemoteCandidate(msg.candidate);
        break;

      case 'chat':
        appendChatMessage(msg.from, msg.name, msg.message, msg.time);
        break;

      case 'peer-media':
        toast((msg.name || 'The other participant') + (msg.enabled ? ' turned on' : ' turned off') + ' their ' + msg.kind + '.');
        break;

      case 'call-ended':
        endCallUi(msg.by === cfg.role ? 'You ended the call.' : 'The call has ended.');
        break;

      case 'error':
        toast(msg.message || 'Something went wrong.');
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

  chatBtn.addEventListener('click', () => chatPanel.classList.toggle('open'));
  $('closeChatBtn').addEventListener('click', () => chatPanel.classList.remove('open'));

  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    send({ type: 'chat', message: text });
    chatInput.value = '';
  });

  endBtn.addEventListener('click', () => {
    send({ type: 'end-call' });
    endCallUi('You ended the call.');
  });

  function endCallUi(message) {
    if (callEnded) return;
    callEnded = true;
    setStatus('ended', 'Call ended');
    toast(message);
    teardownPeerConnection();
    if (localStream) {
      localStream.getTracks().forEach((t) => t.stop());
    }
    if (ws) {
      ws.onclose = null;
      ws.close();
    }
    setTimeout(() => {
      window.location.href = cfg.exitUrl;
    }, 1800);
  }

  window.addEventListener('beforeunload', () => {
    if (!callEnded && navigator.sendBeacon) {
      navigator.sendBeacon(
        'api/end-session.php',
        new Blob([JSON.stringify({ ticket: cfg.ticket })], { type: 'application/json' })
      );
    }
  });

  /* ── Boot ── */
  (async function boot() {
    try {
      await initMedia();
      connectWs();
    } catch (err) {
      // initMedia() already surfaced a toast + status update; stop boot here.
    }
  })();
})();
