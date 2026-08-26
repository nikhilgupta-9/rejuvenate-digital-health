/**
 * AbhaQrScanner — reusable camera QR scanner for ABHA verification (M1).
 *
 * Covers both M1 "Verification of ABHA Address" scan flows:
 *   - Scan User ABHA QR   (patient's ABHA card / app QR)
 *   - Scan Health Facility QR (HFR facility QR at a kiosk/desk)
 *
 * Uses the browser's native BarcodeDetector API — no external library or
 * CDN dependency needed. Falls back to a clear "not supported" message on
 * browsers without it (older Firefox/Safari); the caller's existing manual
 * entry field remains the fallback path.
 *
 * Usage:
 *   AbhaQrScanner.isSupported()
 *   AbhaQrScanner.open({
 *     title: 'Scan ABHA QR',
 *     onResult(parsed, rawText) { ... },   // parsed = {abha_number, abha_address, raw}
 *     onCancel() { ... }                    // optional
 *   });
 */
(function (window) {
  'use strict';

  function isSupported() {
    return 'BarcodeDetector' in window && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  }

  /**
   * Best-effort extraction of an ABHA number/address out of whatever text
   * the QR encodes. NDHM ABHA QR payloads are typically JSON with keys like
   * hidn (ABHA number) / hid or phr (ABHA address); Health Facility QR
   * payloads are a bare HFR id string. We handle both, and always keep the
   * raw text so the caller can fall back to a plain search-by-text.
   */
  function parsePayload(text) {
    const out = { abha_number: '', abha_address: '', facility_id: '', raw: text };

    let json = null;
    try { json = JSON.parse(text); } catch (e) { /* not JSON — fine */ }

    if (json && typeof json === 'object') {
      const num = json.hidn || json.healthIdNumber || json.ABHANumber || json.abhaNumber || '';
      const addr = json.hid || json.phr || json.healthId || json.abhaAddress || json.preferredAbhaAddress || '';
      out.abha_number = String(num || '').replace(/\D/g, '');
      out.abha_address = String(addr || '');
      return out;
    }

    const digits = text.replace(/\D/g, '');
    if (digits.length === 14) {
      out.abha_number = digits;
    } else if (text.includes('@')) {
      out.abha_address = text.trim();
    } else if (/^[A-Z0-9.]{4,20}$/i.test(text.trim())) {
      // Not an ABHA number/address — likely a Health Facility (HFR) QR.
      out.facility_id = text.trim();
    }
    return out;
  }

  let activeStream = null;

  function close(modal) {
    if (activeStream) {
      activeStream.getTracks().forEach(t => t.stop());
      activeStream = null;
    }
    if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
  }

  function open(opts) {
    opts = opts || {};
    const title = opts.title || 'Scan ABHA QR';

    if (!isSupported()) {
      if (typeof opts.onUnsupported === 'function') {
        opts.onUnsupported();
      } else {
        alert('QR scanning is not supported in this browser. Please enter the ABHA number/address manually.');
      }
      return;
    }

    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:99999;' +
      'display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
      <div style="background:#fff;border-radius:14px;padding:18px;max-width:360px;width:92%;text-align:center;font-family:inherit;">
        <div style="font-weight:700;font-size:.95rem;color:#1f2937;margin-bottom:10px;">${title}</div>
        <video autoplay playsinline muted style="width:100%;border-radius:10px;background:#000;"></video>
        <div style="font-size:.76rem;color:#6b7280;margin-top:8px;">Point the camera at the QR code</div>
        <div class="aqs-err" style="font-size:.78rem;color:#dc2626;margin-top:6px;display:none;"></div>
        <button type="button" class="aqs-cancel" style="margin-top:12px;border:1px solid #e5e7eb;background:#f9fafb;
          border-radius:8px;padding:6px 16px;font-size:.82rem;font-weight:600;color:#374151;">Cancel</button>
      </div>`;
    document.body.appendChild(modal);

    const video = modal.querySelector('video');
    const errEl = modal.querySelector('.aqs-err');

    function stop(reason) {
      close(modal);
      if (reason === 'cancel' && typeof opts.onCancel === 'function') opts.onCancel();
    }
    modal.querySelector('.aqs-cancel').addEventListener('click', () => stop('cancel'));

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(stream => {
        activeStream = stream;
        video.srcObject = stream;

        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        let stopped = false;

        (function scanLoop() {
          if (stopped) return;
          detector.detect(video).then(codes => {
            if (codes.length) {
              stopped = true;
              const raw = codes[0].rawValue || '';
              close(modal);
              if (typeof opts.onResult === 'function') opts.onResult(parsePayload(raw), raw);
              return;
            }
            requestAnimationFrame(scanLoop);
          }).catch(() => requestAnimationFrame(scanLoop));
        })();
      })
      .catch(err => {
        errEl.style.display = 'block';
        errEl.textContent = 'Could not access camera: ' + (err.message || err.name || 'permission denied');
      });
  }

  window.AbhaQrScanner = { isSupported, open, parsePayload };
})(window);
