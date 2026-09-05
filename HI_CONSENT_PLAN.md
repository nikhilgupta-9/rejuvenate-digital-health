# HI Consent Artefacts + Data-Sharing — Implementation Plan

**Status:** planning only, no code written yet.
**Depends on:** care-context linking (`lib/HipApi.php`, `abdm_care_context_links`) — already built (commit `f2c6988`).
**Scope of this doc:** the HIP data-sharing half of ABDM HIECM — receiving consent artefacts, receiving health-information requests, and delivering encrypted FHIR data to the requesting HIU.

> ⚠️ **No ABDM sandbox documentation / Postman collection is present in this repo.**
> Every endpoint path, payload field, header, and crypto parameter below is from
> general ABDM HIECM V3 knowledge and **must be verified against the official ABDM
> sandbox docs before coding.** Points that especially need confirmation are marked
> 🔴 **CONFIRM**.

---

## 0. Role recap — who does what

| Actor | In our platform | Responsibility in the consent/data flow |
|---|---|---|
| **HIP** (Health Information Provider) | **us** — we hold the prescriptions | *Receives* consent notifications + revocations; *receives* HI requests; *assembles + encrypts + pushes* FHIR data; reports transfer status. **Never grants/denies consent.** |
| **HIU** (Health Information User) | an external clinic/app (or, optionally, us in a second flow) | *Requests* consent; *requests* data; *receives* the encrypted bundle at its data-push URL |
| **CM** (Consent Manager) | the patient's ABHA / PHR app (ABHA app, Eka, PayTM Health, …) | Where the **patient** actually grants/denies/revokes. Signs the consent artefact. Notifies the HIP. |
| **Gateway** | `dev.abdm.gov.in/api/hiecm` | Routes every call; all async replies go back through it |

**Key correction to the brief:** as a HIP we do **not** "handle consent grant/deny".
The patient does that in their CM app. We *receive a signed consent artefact* once it
is already GRANTED, store it, and honour it (or reject the later data request if it is
expired/revoked/mismatched).

There is a *separate, optional* flow where **we act as a HIU** (a Rejuvenate doctor pulls
a patient's records from other facilities into our UI). That needs consent *request*
initiation + polling. It is called out in §6 but is **not** required for HIP milestone
compliance and should be a later phase.

---

## 1. Standard ABDM HI Consent + data flow (HIP side)

### 1A. Consent notification (inbound — CM → Gateway → us)

1. Patient opens their CM app, sees "Rejuvenate Digital Health", grants consent:
   *"Share Prescription + OPConsultation care-contexts dated 2024-01-01…2026-01-01
   with HIU `clinicX@hiu`, auto-expire 2026-06-01."*
2. CM builds a **consent artefact**, signs it (detached JWS), and calls the gateway.
3. Gateway → **our consent-notification callback**:
   🔴 CONFIRM path — expected shape `POST <callbackBase>/v3/hip/consent/notification`
   (older M2 used `/v0.5/consents/hip/notify`).
   Headers: `REQUEST-ID`, `TIMESTAMP`, `X-HIP-ID`, `X-CM-ID`, gateway auth (see §5).
   Body (representative — 🔴 CONFIRM every field):
   ```
   {
     "notification": {
       "consentId": "<uuid>",
       "consentDetail": {
         "consentId", "createdAt", "purpose": { "text","code","refUri" },
         "patient": { "id": "abhaAddress" },
         "hip": { "id": "<our HIP id>" },
         "hiu": { "id": "<requesting HIU>" },
         "careContexts": [ { "patientReference","careContextReference" } ],
         "hiTypes": [ "Prescription","OPConsultation", ... ],
         "permission": {
           "accessMode": "VIEW",
           "dateRange": { "from","to" },
           "dataEraseAt": "<expiry>",
           "frequency": { "unit","value","repeats" }
         }
       },
       "signature": "<detached JWS of consentDetail>",
       "status": "GRANTED"
     }
   }
   ```
4. **We must:**
   a. Save the raw body first (audit).
   b. Verify `signature` against the **CM's public certificate** (🔴 CONFIRM the cert
      endpoint — historically `GET {gateway}/v3/certs` or the CM's `/certs`).
   c. Resolve `patient.id` (abhaAddress) → local `users.id` via `abha_accounts`
      (`entity_type='patient'`). If unknown → still store, mark `patient_id` NULL,
      flag for review (we may legitimately not have that patient, in which case the
      later data request will be rejected).
   d. Cross-check `careContexts[].careContextReference` against our
      `abdm_care_context_links` / `prescriptions.care_context_ref` — we can only serve
      contexts we actually linked.
   e. Persist a row in **`abha_consents`** (schema §3), `status='granted'`.
   f. **ACK back through the gateway** — 🔴 CONFIRM path, expected
      `POST {gateway}/v3/hip/consent/on-notify`
      `{ "acknowledgement": { "consentId","status":"OK" }, "response": { "requestId": "<the REQUEST-ID we received>" } }`.
   g. Always return HTTP 200/202 to the inbound call quickly; do (b)–(f) either inline
      (fast) or hand to a worker.

### 1B. Consent revocation / expiry (inbound)

- CM notifies the HIP that `consentId` is `REVOKED` (patient pulled it) or `EXPIRED`.
  🔴 CONFIRM whether this is the same notification endpoint with `status: REVOKED` or a
  distinct `/consent/revoke` callback.
- We flip `abha_consents.status` → `revoked` / `expired`, set `revoked_at`, and from
  that moment **reject any HI request** referencing that consent.
- Self-expiry: a cron also expires rows whose `expiry_at < NOW()` even if the CM never
  notifies.

### 1C. Health-information request (inbound — HIU → Gateway → us)

1. HIU decides to fetch → calls gateway referencing `consentId`.
2. Gateway → **our HI-request callback**:
   🔴 CONFIRM path — expected `POST <callbackBase>/v3/hip/hi/request`
   (M2: `/v0.5/health-information/hip/request`).
   Body (representative — 🔴 CONFIRM):
   ```
   {
     "transactionId": "<uuid>",
     "requestId": "<uuid>",
     "hiRequest": {
       "consent": { "id": "<consentId>" },
       "dateRange": { "from","to" },
       "dataPushUrl": "<HIU endpoint we POST the bundle to>",
       "keyMaterial": {
         "cryptoAlg": "ECDH",
         "curve": "Curve25519",
         "dhPublicKey": { "expiry","parameters","keyValue": "<HIU X25519 pubkey>" },
         "nonce": "<HIU 32-byte nonce, base64>"
       }
     }
   }
   ```
3. **We must:**
   a. Save raw body.
   b. Look up `abha_consents` by `consent.id`. Reject (→ error `on-request`) if:
      missing / not `granted` / `expired` / `revoked` / `dateRange` outside the
      consent's permitted range / frequency budget exhausted.
   c. **Synchronously ACK** — 🔴 CONFIRM path
      `POST {gateway}/v3/hip/hi/on-request`
      `{ "hiRequest": { "transactionId","sessionStatus":"ACKNOWLEDGED" }, "response": { "requestId": "<inbound requestId>" } }`.
   d. Insert `abha_hi_requests` row, `status='received'`, store the HIU key material +
      data-push URL. Return 200/202.
   e. Hand off to the **data-push worker** (§4).

### 1D. Data assembly, encryption, push (outbound — us → HIU data-push URL)

Per `abha_hi_requests` row:
1. Resolve the consent → set of `careContextReference`s → our `prescriptions` rows for
   that patient within `dateRange`.
2. For each care context, build a **FHIR R4 document Bundle** (§4).
3. Generate an **ephemeral X25519 keypair + 32-byte nonce**. Derive the shared secret
   with the HIU's `dhPublicKey` (ECDH), run the ABDM KDF (🔴 CONFIRM: HKDF-SHA256 with
   salt derived from the two nonces; output = AES-256-GCM key or XChaCha20-Poly1305 key
   — ABDM has a dedicated "encryption/decryption" spec doc, get it).
4. Encrypt each Bundle.
5. `POST <dataPushUrl>` — 🔴 CONFIRM shape:
   ```
   {
     "pageNumber": 1, "pageCount": 1, "transactionId": "<txn>",
     "entries": [
       { "content": "<base64 ciphertext>", "media": "application/fhir+json",
         "checksum": "<md5/sha256>", "careContextReference": "<ref>" }
     ],
     "keyMaterial": {
       "cryptoAlg":"ECDH","curve":"Curve25519",
       "dhPublicKey": { "expiry","parameters","keyValue":"<our X25519 pubkey>" },
       "nonce": "<our nonce>"
     }
   }
   ```
   Large result sets → paginate (`pageNumber`/`pageCount`).
6. **Notify transfer status to the gateway** — 🔴 CONFIRM path
   `POST {gateway}/v3/hip/hi/notify` (M2: `/health-information/notify`):
   ```
   {
     "notification": {
       "consentId","transactionId","doneAt",
       "notifier": { "type": "HIP", "id": "<our HIP id>" },
       "statusNotification": {
         "sessionStatus": "TRANSFERRED" | "FAILED" | "PARTIALLY_TRANSFERRED",
         "hipId": "<our HIP id>",
         "statusResponses": [
           { "careContextReference": "<ref>", "hiStatus": "OK" | "ERRORED", "description": "" }
         ]
       }
     }
   }
   ```
7. Zeroise the ephemeral private key. Update `abha_hi_requests.status`.

### 1E. Error paths (HIP → gateway)

Consent missing / expired / revoked / not-ours / careContext mismatch / assembly failure
/ HIU push URL unreachable → send `on-request` (for pre-ACK failures) or the status
`notify` with `sessionStatus: FAILED` + per-context `hiStatus: ERRORED`. Never leave the
gateway waiting.

---

## 2. Can the existing webhook handle this? — **No. Add separate endpoint(s).**

`telemedicine/api/abdm-webhook.php` today:

| Aspect | Care-context webhook (exists) | Consent / HI-request (new) |
|---|---|---|
| Who initiates | **We** do (`abdm-hip-worker.php` calls ABDM) | **CM / HIU** do — cold, unsolicited |
| Security gate | `requestId` = a server UUID we generated and stored `pending`; unguessable capability token | We never generated a requestId → **must verify the gateway request signature / JWS + the consent-artefact signature + IP allowlist** |
| Response contract | just HTTP 200 | must **POST a structured `on-notify` / `on-request` back to the gateway** with the inbound `requestId` |
| Payload | tiny (`{requestId, linkToken}` / status) | consent artefacts, FHIR-referencing HI requests, HIU key material |
| Callback types | `linkToken`, `linking-status` | `consent-notification`, `consent-revocation`, `hi-request` |

Reusing the same file would mean bolting a second, incompatible auth model onto a
security-critical file that is already working. **Recommendation:**

- **New:** `telemedicine/api/abdm-consent-webhook.php` — 1A + 1B.
- **New:** `telemedicine/api/abdm-hi-request.php` — 1C (ACK + enqueue only).
- **New lib:** `lib/AbdmGatewaySignature.php` — verify the gateway's request signature /
  JWS and the CM detached-JWS on the consent artefact; fetch + cache CM/gateway certs.
- **Shared, refactored out of the existing webhook:** raw-first logging, 256 KB cap,
  per-IP rate limit, IP allowlist, `?k=` secret → `lib/AbdmWebhookGuard.php`, used by
  all three endpoints. `abdm_webhook_log` gains a `channel` column
  (`carecontext` | `consent` | `hi-request`) — or a sibling `abdm_hiecm_log`.

🔴 **CONFIRM — callback URL registration:** ABDM's sandbox may only let you register
**one callback base URL**, with ABDM defining the sub-paths it appends. If so, the three
files become **handlers behind one front controller** `telemedicine/api/abdm-callback.php`
that routes on `REQUEST_URI` suffix or a payload `type` discriminator. Decide this the
moment we have the sandbox facility-registration screen.

---

## 3. `abha_consents` schema (proposed — new migration `migration_abdm_hi_consent.sql`)

### 3.1 `abha_consents` — one row per consent artefact

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `consent_id` | VARCHAR(64) | **UNIQUE** — the CM artefact id (UUID) |
| `consent_request_id` | VARCHAR(64) NULL | set only when *we* (HIU) initiated (§6); NULL for HIP-inbound |
| `role` | ENUM('hip','hiu') | our side in this consent — almost always `hip` for now |
| `status` | ENUM('requested','granted','denied','revoked','expired') | |
| `patient_id` | INT(11) NULL | resolved `users.id`; NULL until/if resolvable |
| `abha_address` | VARCHAR(100) | patient handle from the artefact |
| `hiu_id` | VARCHAR(100) | requesting HIU id |
| `hiu_name` | VARCHAR(150) NULL | |
| `hip_id` | VARCHAR(100) | us (`ABDM_HIP_ID`) when `role='hip'` |
| `purpose_code` | VARCHAR(20) | `CAREMGT` / `BTG` / `PUBHLTH` / `HPAYMT` / `DSRCH` / `SELF` |
| `purpose_text` | VARCHAR(150) NULL | |
| `hi_types` | JSON | `["Prescription","OPConsultation","DiagnosticReport",...]` |
| `access_mode` | VARCHAR(20) | `VIEW` / `STORE` / `QUERY` / `STREAM` |
| `date_range_from` | DATETIME | clinical data window — lower bound |
| `date_range_to` | DATETIME | upper bound |
| `expiry_at` | DATETIME | `permission.dataEraseAt` / consent validity end |
| `frequency_unit` | VARCHAR(10) NULL | `HOUR`/`DAY`/`WEEK`/`MONTH`/`YEAR` |
| `frequency_value` | INT NULL | |
| `frequency_repeats` | INT NULL | how many pulls the HIU may do |
| `pulls_used` | INT NOT NULL DEFAULT 0 | incremented per served HI request; enforce against `frequency_repeats` |
| `care_contexts` | JSON NULL | `[{patientReference, careContextReference}]` from the artefact |
| `cm_id` | VARCHAR(40) | `sbx` / consent-manager id |
| `signature` | TEXT NULL | detached JWS from the CM (retained for audit) |
| `raw_artefact` | JSON | full artefact exactly as received |
| `granted_at` | DATETIME NULL | |
| `revoked_at` | DATETIME NULL | |
| `last_hi_request_at` | DATETIME NULL | |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

Indexes: `UNIQUE(consent_id)`, `KEY(patient_id)`, `KEY(status, expiry_at)`,
`KEY(abha_address)`, `KEY(hiu_id)`.

FK: `patient_id → users.id` **ON DELETE RESTRICT** (identity/clinical ref — matches the
policy in `migration_core_foreign_keys.sql`). Column is nullable, FK still fine.

**Retention:** consent artefacts must outlive routine deletion (NHA). `raw_artefact` +
`signature` are the legal record — never CASCADE these away with the patient (RESTRICT
already prevents patient hard-delete; app already soft-deletes).

### 3.2 `abha_hi_requests` — one row per health-information request

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `transaction_id` | VARCHAR(64) | **UNIQUE** — gateway `transactionId` |
| `request_id` | VARCHAR(64) | inbound gateway `requestId` (echo in every reply) |
| `consent_id` | VARCHAR(64) | → `abha_consents.consent_id` (logical FK; see note) |
| `status` | ENUM('received','acknowledged','assembling','pushing','transferred','partial','failed') | |
| `date_range_from` / `date_range_to` | DATETIME | the window this request asked for (⊆ consent range) |
| `hiu_data_push_url` | VARCHAR(500) | where we POST the bundle |
| `hiu_key_material` | JSON | HIU `dhPublicKey` + `nonce` |
| `hip_public_key` | VARCHAR(120) NULL | our ephemeral X25519 public key (base64) |
| `hip_nonce` | VARCHAR(64) NULL | our nonce (base64) |
| `care_context_count` | INT DEFAULT 0 | |
| `pushed_ok_count` | INT DEFAULT 0 | |
| `pushed_err_count` | INT DEFAULT 0 | |
| `error_code` | VARCHAR(40) NULL | |
| `error_detail` | VARCHAR(255) NULL | |
| `received_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| `acknowledged_at` / `completed_at` | DATETIME NULL | |

Indexes: `UNIQUE(transaction_id)`, `KEY(consent_id)`, `KEY(status, received_at)`.

> **No hard FK on `consent_id`** → keep it logical (like `abdm_audit_logs.entity_id`).
> An HI request can arrive referencing a consent we rejected/never stored; we still need
> to log it and reply with an error. Validate in code, not by constraint.

**The ephemeral private key is never persisted.** Hold it in the worker process for the
lifetime of the push, then zeroise. If the push must survive a restart, store it
**encrypted** with an app key and wipe on completion — decide during build.

### 3.3 (Optional) `abha_hi_request_items` — per-care-context granularity

`id, hi_request_id, care_context_reference, prescription_id, fhir_bundle_checksum,
status ENUM('pending','pushed','errored'), error_detail, pushed_at`.
Needed for accurate per-context `statusResponses[]` back to the gateway and for
retrying a single failed context without re-pushing the whole set. Recommend building
it from the start.

### 3.4 `abdm_webhook_log` change

Add `channel VARCHAR(20) NOT NULL DEFAULT 'carecontext'` (`carecontext`|`consent`|`hi-request`)
so all three inbound flows share one raw-first audit table. Idempotent `ALTER … ADD
COLUMN IF NOT EXISTS`. Register in `run-migrations.php` `$ORDER` + `MIGRATIONS.md`.

---

## 4. HIU data-request fulfilment — how the health data actually gets delivered

This is the **largest** piece and where our current data model is furthest from ABDM.

### 4.1 Components to build

| Component | File (proposed) | Job |
|---|---|---|
| Gateway-signature verifier | `lib/AbdmGatewaySignature.php` | verify inbound gateway/CM signatures; fetch + cache certs |
| Consent store/logic | `lib/HiConsent.php` | CRUD on `abha_consents`; `isServable(consentId, dateRange)`; frequency-budget check; expiry sweep |
| HI-request store | `lib/HiRequest.php` | CRUD on `abha_hi_requests` (+ items); state machine |
| **FHIR bundle builder** | `lib/FhirBundleBuilder.php` | `prescriptions` row → FHIR R4 document Bundle |
| **ABDM crypto** | `lib/AbdmCrypto.php` | X25519 ECDH + ABDM KDF + AEAD encrypt; checksum |
| Data-push worker | `scripts/abdm-hi-push-worker.php` | cron: assemble → encrypt → POST to HIU → `notify` gateway |
| Gateway client | extend `lib/HipApi.php` **or** new `lib/HiuDataApi.php` | `on-notify`, `on-request`, `notify` calls (reuses ABHA gateway session token) |

### 4.2 FHIR mapping — the real work

Our `prescriptions` table is bespoke columns + JSON (`vitals`, `medications`,
`icd_codes`, …). ABDM requires **NDHM FHIR R4 Implementation Guide** profiles. For a
prescription care context the Bundle (type `document`) needs at minimum:

- `Composition` (type = *Prescription record*) tying it together
- `Patient` (from `users` + `abha_accounts` — name, gender, dob, ABHA)
- `Practitioner` + `PractitionerRole` (from `doctors` — name, `hpr_id`)
- `Organization` (Rejuvenate — needs our HFR facility id)
- `MedicationRequest` per drug (from `prescriptions.medications` JSON — needs
  dose/route/frequency normalised; ideally SNOMED/`RxNorm`-ish codes but text is
  accepted)
- `Condition` per diagnosis (`diagnosis` / `icd_codes` → ICD-10)
- `Binding`/`DocumentReference` if we attach the rendered PDF (`opd-slip` style)

Other `hi_types` (`OPConsultation`, `DiagnosticReport`) = separate profiles, more
resources. **Recommend: support `Prescription` only in phase 1**, since that is what our
care-context linking already registers.

🔴 CONFIRM: exact NDHM FHIR IG version, whether the sandbox validates against the IG
(rejects non-conformant bundles) or just stores, and required vs optional resources.

### 4.3 Crypto

🔴 CONFIRM against the ABDM **"Encryption/Decryption of Health Information"** spec:
- Curve: X25519 (Curve25519). PHP: libsodium `sodium_crypto_scalarmult`.
- KDF: HKDF-SHA256. Salt = function of the two nonces (XOR? concat? — the spec is exact).
- AEAD: historically **AES-256-GCM**; some versions use **XChaCha20-Poly1305**.
- Output framing: `content` = base64(ciphertext) with the AEAD tag appended; `checksum`
  algorithm (MD5 vs SHA-256) 🔴 CONFIRM.
- Nonce length (32 bytes typical) and `dhPublicKey.parameters` string format.

### 4.4 Worker loop (`abdm-hi-push-worker.php`)

```
for each abha_hi_requests where status in ('received','assembling','pushing'):
    guard ABDM_HIP_CONFIGURED
    consent = HiConsent::get(row.consent_id)
    if not consent.isServable(row.dateRange): -> notify FAILED, status='failed'; continue
    contexts = consent care-contexts ∩ prescriptions in dateRange for patient
    build FHIR bundle per context  (FhirBundleBuilder)
    ephemeral X25519 keypair + nonce (AbdmCrypto)
    encrypt each bundle
    POST hiu_data_push_url  { entries[], keyMaterial }   (paginate if large)
    per-context status -> abha_hi_request_items
    POST {gateway}/…/notify  { statusNotification: TRANSFERRED|PARTIALLY_TRANSFERRED|FAILED }
    zeroise private key; status='transferred'/'partial'/'failed'; pulls_used++
give-up rule: requests stuck > N hours -> FAILED + notify
```

Cron alongside `abdm-hip-worker.php`.

---

## 5. What needs official ABDM doc / sandbox confirmation (consolidated)

| # | Item | Why it blocks coding |
|---|---|---|
| 1 | **Callback URL model** — one registered base URL with ABDM-defined sub-paths, or multiple registerable paths? | Determines 1 front-controller vs 3 endpoint files (§2) |
| 2 | Exact inbound paths: consent-notification, consent-revocation, HI-request | Route/handler wiring |
| 3 | Exact outbound gateway paths: `consent/on-notify`, `hi/on-request`, `hi/notify` (+ HIU-side if §6) | `HipApi`/`HiuDataApi` methods |
| 4 | Full payload schemas for all of the above (field names, nesting, enums) | Every DB column + parser |
| 5 | **Inbound request authentication** — how does the gateway prove a callback is really ABDM? (JWS in a header? `X-HMAC`? mTLS? signature over body?) | `AbdmGatewaySignature.php` — the entire security gate for §2 |
| 6 | **Consent-artefact signature** — algorithm, which cert signs it, where to fetch the CM public cert, detached vs attached JWS | signature verification |
| 7 | **Encryption spec** — curve, KDF (salt derivation), AEAD algorithm, nonce length, `checksum` algo, `dhPublicKey.parameters` format | `AbdmCrypto.php` — cannot guess this |
| 8 | **NDHM FHIR IG version** + whether the sandbox validates bundles | `FhirBundleBuilder.php` scope |
| 9 | Data-push request shape to the HIU (`pageNumber`/`pageCount`, `entries[]`, media type, error semantics) | worker push step |
| 10 | Sandbox prerequisites: HIP/HIU + **HFR facility** registration, M2/M3 milestone enablement for our credential, do we get a test CM + test HIU to exercise against | nothing works end-to-end without this |
| 11 | Whether care-context linking (M3, done) and consent/data (M2) share the same `ABDM_CLIENT_ID` or need a separate app registration | `.env` / `config/abdm.php` |
| 12 | Consent purpose codes + `hiTypes` values our HIP must accept/reject | `HiConsent::isServable` logic |
| 13 | Idempotency + retry expectations from the gateway (does it resend on non-2xx? how many times? dedupe key?) | webhook idempotency design |
| 14 | Timeout budgets — how fast must the synchronous ACK be; SLA for the async data push | inline-vs-worker split |

**Where to get it:** ABDM Sandbox portal → *HIECM (Gateway) V3* API docs + Postman
collection; the **"ABDM Encryption/Decryption"** and **NDHM FHIR Implementation Guide**
documents; the sandbox facility-onboarding guide. Until items 5, 6, 7 are in hand,
**do not write** `AbdmGatewaySignature.php` or `AbdmCrypto.php` — they cannot be
guessed and getting them wrong fails silently or leaks data.

---

## 6. Optional later phase — us as a HIU (pull external records in)

Not needed for HIP compliance. If a Rejuvenate doctor should see a patient's records
from *other* facilities:

- `POST {gateway}/v3/hiu/consent/request` → poll / receive `on-request` → on GRANT,
  receive the consent artefact (same `abha_consents`, `role='hiu'`)
- `POST {gateway}/v3/hiu/hi/request` → stand up **our own data-push receiver**
  (`telemedicine/api/abdm-hi-receive.php`) → decrypt (same `AbdmCrypto`) → render
- New table `abha_hiu_data` for the fetched bundles + a viewer in the doctor panel

Reuses ~60% of the HIP-side libs (crypto, consent store, signature). Sequence it after
the HIP side is certified.

---

## 7. Suggested build order

| Phase | Deliverable | Compliance value | Blocked on |
|---|---|---|---|
| **1** | `abha_consents` + `abha_hi_requests` migration; `AbdmWebhookGuard` refactor; `abdm-consent-webhook.php` (receive + verify + store + ACK); `HiConsent` lib; expiry cron | Consent artefacts received, stored, acknowledged, revocable — *partial HIP milestone*, independently testable | doc items 1, 2, 4, 5, 6 |
| **2** | `abdm-hi-request.php` (ACK + enqueue); `HiRequest` lib; error `on-request` paths | HI requests accepted + rejected correctly | doc items 3, 4, 13, 14 |
| **3** | `FhirBundleBuilder` (Prescription profile only); `AbdmCrypto`; `abdm-hi-push-worker.php`; gateway `notify` | **Full HIP data-sharing** for prescriptions | doc items 7, 8, 9 + sandbox test HIU |
| **4** | `OPConsultation` / `DiagnosticReport` FHIR profiles | Broader `hiTypes` coverage | phase 3 done |
| **5** | HIU side (§6) | Inbound records in doctor UI | phase 3 certified |

Phase 1 is the sensible next commit and can proceed as soon as doc items 1, 2, 4, 5, 6
are confirmed. Phases 3+ are multi-week (FHIR mapping + crypto + certification).

---

## 8. Config / `.env` additions (anticipated)

```
ABDM_HIU_ID=                     # if we also act as HIU (phase 5)
ABDM_HFR_FACILITY_ID=            # our HFR facility id — needed in FHIR Organization
ABDM_HIECM_CONSENT_CONFIGURED=   # derived: ABDM_CONFIGURED && ABDM_HIP_ID && facility id
ABDM_CM_CERT_URL=                # or discovered from the gateway — 🔴 CONFIRM
ABDM_DATA_PUSH_TIMEOUT=30
```

`config/abdm.php` already has the `ABDM_HIECM_BASE_URL` / `ABDM_HIP_ID` /
`ABDM_WEBHOOK_SECRET` / `ABDM_WEBHOOK_ALLOWED_IPS` blocks this builds on.
