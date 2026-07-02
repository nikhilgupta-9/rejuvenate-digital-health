/**
 * ABDM ABHA Enrollment — Node.js CLI
 *
 * Flow:
 *   0. Fetch gateway access token
 *   1. Send OTP to Aadhaar-linked mobile  POST /enrollment/request/otp
 *   2. Verify OTP + create ABHA           POST /enrollment/enrol/byAadhaar
 *   3. Get ABHA address suggestions       GET  /enrollment/enrol/suggestion
 *   4. Set preferred ABHA address         POST /enrollment/enrol/abha-address
 *
 * Usage:
 *   node abha-enrol.js
 *
 * Dependencies (built-in only — no npm packages needed):
 *   node:crypto, node:https, node:readline
 */

'use strict';

const crypto   = require('node:crypto');
const https    = require('node:https');
const readline = require('node:readline');

/* ─────────────────────────────────────────────
   CONFIGURATION — fill in your sandbox creds
───────────────────────────────────────────── */
const CONFIG = {
  clientId:     process.env.ABDM_CLIENT_ID     || 'YOUR_CLIENT_ID',
  clientSecret: process.env.ABDM_CLIENT_SECRET || 'YOUR_CLIENT_SECRET',

  gatewayUrl: 'https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions',
  baseUrl:    'https://abhasbx.abdm.gov.in/abha/api/v3',
  xCmId:      'sbx',
};

/* ─────────────────────────────────────────────
   UTILITIES
───────────────────────────────────────────── */

/** Generate a random UUID v4. */
function uuid() {
  return crypto.randomUUID();
}

/** ISO-8601 UTC timestamp with milliseconds. */
function timestamp() {
  return new Date().toISOString().replace(/(\.\d{3})Z$/, '.000Z');
}

/**
 * Minimal HTTPS request helper.
 * Returns { status, headers, body } where body is already JSON-parsed if possible.
 */
function request(method, url, { headers = {}, body = null } = {}) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const opts = {
      method,
      hostname: parsed.hostname,
      port:     parsed.port || 443,
      path:     parsed.pathname + parsed.search,
      headers:  { 'Content-Type': 'application/json', Accept: 'application/json', ...headers },
    };

    if (body) {
      const raw = typeof body === 'string' ? body : JSON.stringify(body);
      opts.headers['Content-Length'] = Buffer.byteLength(raw);
    }

    const req = https.request(opts, res => {
      const chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let parsed;
        try { parsed = JSON.parse(text); } catch { parsed = text; }
        resolve({ status: res.statusCode, headers: res.headers, body: parsed });
      });
    });

    req.on('error', reject);
    if (body) req.write(typeof body === 'string' ? body : JSON.stringify(body));
    req.end();
  });
}

/** Build standard ABDM v3 headers (attach to every request). */
function abhaHeaders(accessToken, extra = {}) {
  return {
    Authorization:  `Bearer ${accessToken}`,
    'REQUEST-ID':   uuid(),
    TIMESTAMP:      timestamp(),
    'X-CM-ID':      CONFIG.xCmId,
    ...extra,
  };
}

/** Throw a readable error if the response status is not 2xx. */
function assertOk(res, label) {
  if (res.status >= 200 && res.status < 300) return;
  const detail = typeof res.body === 'object'
    ? (res.body?.details?.[0]?.message ?? res.body?.message ?? JSON.stringify(res.body))
    : String(res.body).slice(0, 200);
  throw new Error(`${label} failed (HTTP ${res.status}): ${detail}`);
}

/** Prompt user for input in the terminal. */
function prompt(question) {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  return new Promise(resolve => rl.question(question, ans => { rl.close(); resolve(ans.trim()); }));
}

/* ─────────────────────────────────────────────
   RSA ENCRYPTION
   Algorithm: RSA/ECB/PKCS1Padding (as specified)
   Key source: GET /abha/api/v3/certs
───────────────────────────────────────────── */

let _publicKeyPem = null; // cached for the session

/** Fetch and cache ABDM sandbox public key. */
async function fetchPublicKey(accessToken) {
  if (_publicKeyPem) return _publicKeyPem;

  const res = await request('GET', `${CONFIG.baseUrl}/certs`, {
    headers: abhaHeaders(accessToken),
  });
  assertOk(res, 'Fetch public cert');

  // Response shape: { publicKey: "<base64 DER>" }  OR  { keys: [{ n, e, ... }] } (JWK)
  const raw = res.body?.publicKey ?? res.body?.keys?.[0]?.x5c?.[0] ?? null;

  if (!raw) {
    throw new Error('Could not extract public key from /certs response: ' + JSON.stringify(res.body));
  }

  const clean = raw.replace(/\s+/g, '');
  _publicKeyPem = `-----BEGIN PUBLIC KEY-----\n${chunkB64(clean)}\n-----END PUBLIC KEY-----\n`;

  // Validate key is loadable
  crypto.createPublicKey(_publicKeyPem); // throws if invalid
  return _publicKeyPem;
}

/** Split base64 into 64-char lines (PEM format requirement). */
function chunkB64(b64, size = 64) {
  return b64.match(/.{1,64}/g).join('\n');
}

/**
 * RSA encrypt plaintext using RSA/ECB/PKCS1Padding.
 * Returns base64-encoded cipher text.
 *
 * NOTE: The spec explicitly states PKCS1Padding (not OAEP).
 *       Node's publicEncrypt with RSA_PKCS1_PADDING constant matches this.
 */
function rsaEncrypt(plaintext, publicKeyPem) {
  const encrypted = crypto.publicEncrypt(
    { key: publicKeyPem, padding: crypto.constants.RSA_PKCS1_PADDING },
    Buffer.from(plaintext, 'utf8')
  );
  return encrypted.toString('base64');
}

/* ─────────────────────────────────────────────
   STEP 0 — Gateway access token
───────────────────────────────────────────── */
async function getAccessToken() {
  console.log('\n[Step 0] Fetching gateway access token…');

  const res = await request('POST', CONFIG.gatewayUrl, {
    headers: {
      'Content-Type': 'application/json',
      'REQUEST-ID':   uuid(),
      TIMESTAMP:      timestamp(),
      'X-CM-ID':      CONFIG.xCmId,
    },
    body: {
      clientId:     CONFIG.clientId,
      clientSecret: CONFIG.clientSecret,
      grantType:    'client_credentials',
    },
  });

  assertOk(res, 'Gateway token');
  const token = res.body?.accessToken;
  if (!token) throw new Error('No accessToken in gateway response: ' + JSON.stringify(res.body));
  console.log('  ✓ Access token obtained (expires in', res.body.expiresIn, 'seconds)');
  return token;
}

/* ─────────────────────────────────────────────
   STEP 1 — Send OTP to Aadhaar-linked mobile
───────────────────────────────────────────── */
async function sendAadhaarOtp(accessToken, aadhaar, publicKeyPem) {
  console.log('\n[Step 1] Sending OTP to Aadhaar-linked mobile…');

  const encryptedAadhaar = rsaEncrypt(aadhaar, publicKeyPem);

  const res = await request('POST', `${CONFIG.baseUrl}/enrollment/request/otp`, {
    headers: abhaHeaders(accessToken),
    body: {
      txnId:     '',
      scope:     ['abha-enrol'],
      loginHint: 'aadhaar',
      loginId:   encryptedAadhaar,
      otpSystem: 'aadhaar',
    },
  });

  assertOk(res, 'Send Aadhaar OTP');
  const txnId = res.body?.txnId;
  if (!txnId) throw new Error('No txnId in OTP response: ' + JSON.stringify(res.body));
  console.log('  ✓ OTP sent. txnId:', txnId);
  console.log('  Message:', res.body?.message ?? '(none)');
  return txnId;
}

/* ─────────────────────────────────────────────
   STEP 2 — Verify OTP + create ABHA
───────────────────────────────────────────── */
async function enrolByAadhaar(accessToken, txnId, otp, mobile, publicKeyPem) {
  console.log('\n[Step 2] Verifying OTP and creating ABHA…');

  const encryptedOtp    = rsaEncrypt(otp, publicKeyPem);
  const encryptedMobile = rsaEncrypt(mobile, publicKeyPem);

  const res = await request('POST', `${CONFIG.baseUrl}/enrollment/enrol/byAadhaar`, {
    headers: abhaHeaders(accessToken),
    body: {
      authData: {
        authMethods: ['otp'],
        otp: {
          txnId:    txnId,
          otpValue: encryptedOtp,
          mobile:   encryptedMobile,
        },
      },
      consent: {
        code:    'abha-enrollment',
        version: '1.4',
      },
    },
  });

  assertOk(res, 'Enrol by Aadhaar');

  const xToken  = res.body?.tokens?.token;
  const newTxn  = res.body?.txnId ?? txnId;
  const profile = res.body?.ABHAProfile ?? {};
  const isNew   = res.body?.isNew ?? true;

  if (!xToken) throw new Error('No X-token in enrolByAadhaar response: ' + JSON.stringify(res.body));

  console.log('  ✓ ABHA', isNew ? 'created' : 'already exists');
  console.log('  Message:', res.body?.message ?? '(none)');
  if (profile.ABHANumber) console.log('  ABHA Number:', profile.ABHANumber);
  if (profile.name)       console.log('  Name:',        profile.name);

  return { xToken, txnId: newTxn, profile, isNew };
}

/* ─────────────────────────────────────────────
   STEP 3 — Get ABHA address suggestions
───────────────────────────────────────────── */
async function getAddressSuggestions(accessToken, txnId) {
  console.log('\n[Step 3] Fetching ABHA address suggestions…');

  const res = await request('GET', `${CONFIG.baseUrl}/enrollment/enrol/suggestion`, {
    headers: abhaHeaders(accessToken, {
      // Spec says Transaction_Id header (capital T, underscore)
      Transaction_Id: txnId,
    }),
  });

  assertOk(res, 'Get address suggestions');

  const suggestions = res.body?.abhaAddressList ?? res.body?.suggestions ?? [];
  if (!suggestions.length) {
    console.log('  ! No suggestions returned. Raw response:', JSON.stringify(res.body));
    return [];
  }

  console.log('  ✓ Suggested addresses:');
  suggestions.forEach((addr, i) => console.log(`    [${i + 1}] ${addr}`));
  return suggestions;
}

/* ─────────────────────────────────────────────
   STEP 4 — Set preferred ABHA address
───────────────────────────────────────────── */
async function setAbhaAddress(accessToken, txnId, abhaAddress) {
  console.log('\n[Step 4] Setting ABHA address:', abhaAddress);

  const res = await request('POST', `${CONFIG.baseUrl}/enrollment/enrol/abha-address`, {
    headers: abhaHeaders(accessToken),
    body: {
      txnId:       txnId,
      abhaAddress: abhaAddress,
      preferred:   1,
    },
  });

  assertOk(res, 'Set ABHA address');

  const finalAddr = res.body?.abhaAddress ?? res.body?.preferredAbhaAddress ?? abhaAddress;
  console.log('  ✓ ABHA address set:', finalAddr);
  console.log('  Response:', JSON.stringify(res.body, null, 2));
  return finalAddr;
}

/* ─────────────────────────────────────────────
   MAIN
───────────────────────────────────────────── */
async function main() {
  console.log('═══════════════════════════════════════════');
  console.log('  ABDM ABHA Enrollment — Sandbox');
  console.log('═══════════════════════════════════════════');

  if (CONFIG.clientId === 'YOUR_CLIENT_ID') {
    console.error('\nERROR: Set ABDM_CLIENT_ID and ABDM_CLIENT_SECRET env vars before running.\n');
    process.exit(1);
  }

  /* ── Collect inputs ── */
  const aadhaar = await prompt('\nEnter Aadhaar number (12 digits): ');
  if (!/^\d{12}$/.test(aadhaar)) {
    console.error('Invalid Aadhaar — must be exactly 12 digits.'); process.exit(1);
  }

  const mobile = await prompt('Enter communication mobile (10 digits): ');
  if (!/^\d{10}$/.test(mobile)) {
    console.error('Invalid mobile — must be exactly 10 digits.'); process.exit(1);
  }

  try {
    /* 0. Access token */
    const accessToken = await getAccessToken();

    /* Fetch RSA public key (used to encrypt Aadhaar, OTP, mobile) */
    console.log('\n[Prereq] Fetching ABDM public certificate…');
    const publicKeyPem = await fetchPublicKey(accessToken);
    console.log('  ✓ Public key loaded');

    /* 1. Send OTP */
    const txnId1 = await sendAadhaarOtp(accessToken, aadhaar, publicKeyPem);

    /* Collect OTP from user */
    const otp = await prompt('\nEnter OTP received on Aadhaar-linked mobile: ');
    if (!/^\d{4,8}$/.test(otp)) {
      console.error('Invalid OTP.'); process.exit(1);
    }

    /* 2. Create ABHA */
    const { xToken, txnId: txnId2, profile, isNew } = await enrolByAadhaar(
      accessToken, txnId1, otp, mobile, publicKeyPem
    );

    if (!isNew) {
      console.log('\n  ℹ Account already exists for this Aadhaar.');
      console.log('  Existing ABHA Number:', profile.ABHANumber ?? '(check profile)');
    }

    /* 3. Address suggestions */
    const suggestions = await getAddressSuggestions(accessToken, txnId2);

    /* Let user pick or type a custom address */
    let chosenAddress;
    if (suggestions.length > 0) {
      const pick = await prompt(`\nPick a number [1-${suggestions.length}] or type custom address: `);
      const idx  = parseInt(pick, 10);
      chosenAddress = (!isNaN(idx) && idx >= 1 && idx <= suggestions.length)
        ? suggestions[idx - 1]
        : pick.trim();
    } else {
      chosenAddress = await prompt('\nNo suggestions received. Enter ABHA address manually (e.g. john.doe@sbx): ');
    }

    if (!chosenAddress) { console.error('No ABHA address entered.'); process.exit(1); }

    /* 4. Set address */
    const finalAddress = await setAbhaAddress(accessToken, txnId2, chosenAddress);

    /* ── Summary ── */
    console.log('\n═══════════════════════════════════════════');
    console.log('  ABHA Enrollment Complete!');
    console.log('═══════════════════════════════════════════');
    console.log('  ABHA Number :', profile.ABHANumber ?? '(see profile)');
    console.log('  ABHA Address:', finalAddress);
    console.log('  Name        :', profile.name ?? [profile.firstName, profile.lastName].filter(Boolean).join(' ') || '(see profile)');
    console.log('  X-Token     :', xToken.slice(0, 20) + '…  (use for profile API calls)');
    console.log('───────────────────────────────────────────');
    console.log('  Next: use X-Token with GET /profile/account to fetch full profile.');
    console.log('═══════════════════════════════════════════\n');

  } catch (err) {
    console.error('\n✗ ERROR:', err.message);
    process.exit(1);
  }
}

main();
