# HIP linking — our code vs the authoritative ABDM M2/HIP reference

**Purpose:** compare the committed HIP implementation (`lib/HipApi.php`,
`telemedicine/api/abdm-webhook.php`, `scripts/abdm-hip-worker.php`,
`migration_abdm_hip_linking.sql` — commit `f2c6988`) against the endpoint list from
the new authoritative ABDM reference.

**Bottom line:** the committed code implemented a **token-based** linking model
(`generate-token` → `X-LINK-TOKEN` → `link/carecontext`). The authoritative reference
describes the **OTP deep-link** model (`links/link/init` → SMS → patient OTP →
`links/link/confirm` → `links/link/on-confirm`). These are different flows. Almost every
endpoint path in our code is wrong, and the DB table that models the link token
(`abdm_link_tokens`) models a concept the real flow doesn't have. **This needs a full
rewrite of the linking flow**, not a patch. ~55–60% of the surrounding scaffolding is
reusable (see §3).

> Caveat: this comparison is against a paraphrased reference, not the ABDM PDF/Postman
> itself. Confirm the exact request/response bodies and the `X-token`-requiring calls
> from the source before rewriting.

---

## 1. Comparison table

| # | Our method / endpoint | Real ABDM endpoint (reference) | Match? | Action needed |
|---|---|---|---|---|
| 1 | `AbdmApi::getAccessToken()` → `POST …/hiecm/gateway/v3/sessions` | `POST /gateway/v3/sessions` | ✅ **MATCH** | none — HipApi correctly reuses this |
| 2 | `ABDM_HIECM_BASE_URL = https://dev.abdm.gov.in/api/hiecm` | base `https://dev.abdm.gov.in/api/hiecm` | ✅ **MATCH** | none |
| 3 | `HipApi::generateLinkToken()` → `POST /v3/token/generate-token` | *(no such step)* linking starts at `POST /links/link/init` (body `abhaAddress, hipId, patient{id, referenceNumber, display, careContexts[], hiType, count}`) | ❌ **MISMATCH** — wrong endpoint *and* wrong model. The reference flow has no "generate link token" step. | Replace with `linkInit()` → `POST {base}/links/link/init`. Move the `careContexts[]` + `patient{}` payload (currently in `linkCareContext`) into this call. |
| 4 | `HipApi::linkCareContext()` → `POST /hip/v3/link/carecontext` + header `X-LINK-TOKEN` | *(no equivalent)* — care contexts are submitted inside `/links/link/init`; the final linked list is returned by us in `/links/link/on-confirm` | ❌ **MISMATCH** — endpoint, header, and model all wrong | Delete. Fold the care-context list into `linkInit()` (send) and a new `linkOnConfirm()` (final ack). |
| 5 | `HipApi::notifyCareContext()` → `POST /hip/v3/link/context/notify` | `POST /sms/notify2` — send the patient the deep-link SMS. (`/sms/notify` is deprecated → 404.) | ❌ **MISMATCH** — different purpose; and if we *did* mean the SMS, our path would 404 | Replace with `smsNotify2()` → `POST {base}/sms/notify2`, called right after `linkInit()`. |
| 6 | — | **incoming** `/links/link/on-init` → carries `linkRefNumber` | ❌ **MISSING** | New webhook route: store `linkRefNumber` against our pending link request (keyed by our `REQUEST-ID`). |
| 7 | — | **incoming** `/links/link/confirm` → patient has submitted the OTP; we verify it | ❌ **MISSING** | New webhook route: validate the OTP / token, then trigger #8. |
| 8 | — | **outgoing** `/links/link/on-confirm` → we send the final linked care-context list | ❌ **MISSING** | New `HipApi::linkOnConfirm()` → `POST {base}/links/link/on-confirm`. |
| 9 | — | **incoming** `/health-information/hip/request` → HIU wants data (`consent.id, dateRange, dataPushUrl, keyMaterial`) | ❌ **MISSING** | The data-sharing half (see `HI_CONSENT_PLAN.md` §1C/§4). New endpoint + worker. |
| 10 | — | **outgoing** `/health-information/notify` → tell the gateway the push status | ❌ **MISSING** | New `HipApi::hiNotify()` (or `HiuDataApi`). |
| 11 | — | **incoming** `/consents/hip/notify` → patient revoked a consent | ❌ **MISSING** | New endpoint → flip `abha_consents.status` = revoked (see `HI_CONSENT_PLAN.md`). |
| 12 | `abdm-webhook.php` — one endpoint, routes by **payload keys** (`linkToken` / `linking-status`) | reference has **distinct callback paths** (`on-init`, `confirm`, `hip/request`, `hip/notify`) | ❌ **MISMATCH** | Route by request path (front controller) or register separate callback URLs — 🔴 confirm which the sandbox allows. Current key-sniffing classifier is guesswork and doesn't cover any real callback type. |
| 13 | `HipApi::http()` sends `Authorization, REQUEST-ID, TIMESTAMP, X-CM-ID, X-HIP-ID` | same **plus `X-token`** (lowercase `t`) on *some* calls | ⚠️ **PARTIAL** | Add `X-token` support to `http()`; 🔴 confirm exactly which calls need it (likely `links/link/confirm` handling and/or `health-information` calls). Note: not the same as the `T-token` fix in `AbdmApi::verifyUserLogin()`. |
| 14 | `abdm_link_tokens` table (`link_token`, `status pending/received/expired`, `expires_at` 6mo) | reference uses `linkRefNumber` + a patient **OTP**, no long-lived token | ❌ **MISMATCH** — models a concept that doesn't exist in this flow | Replace with `abdm_link_requests` (`link_ref_number`, `request_id`, `otp_txn`, `status init/sms_sent/otp_pending/confirmed/failed/expired`, short TTL). |
| 15 | `abdm_care_context_links` (`request_id` UNIQUE, `status pending/linked/failed`) | valid concept — one row per prescription to link — but the `confirm`/`on-confirm` callbacks key off `linkRefNumber`, not our `request_id` | ⚠️ **PARTIAL** | Add `link_ref_number` column + index; link many care-contexts to one link request; otherwise keep. |
| 16 | `abdm_webhook_log` (raw body, `callback_type`, `processed`) | generic raw-first audit log | ✅ **MATCH** | Keep. Add a `channel` column (`linking` / `hi-request` / `consent`) for the new endpoints. |
| 17 | Worker: `generateLinkToken()` → wait for token webhook → `linkCareContext(token)` → `notifyCareContext()` | `linkInit()` → `smsNotify2()` → **wait for patient OTP** (`links/link/confirm` callback) → `linkOnConfirm()` | ❌ **MISMATCH** | Rewrite worker: submit `init` + SMS, then it's **patient-driven** (they open the SMS deep link and enter OTP) — the worker's job shrinks to firing `init`+`sms` and reconciling stale/failed requests. `on-confirm` is triggered from the `confirm` webhook, not the cron. |
| 18 | `HipLinking` methods: `startLinkToken` / `activeLinkToken` / `applyLinkToken` | no link-token lifecycle in the real flow | ❌ **MISMATCH** | Replace with `startLinkRequest` / `findByLinkRef` / `markSmsSent` / `markConfirmed` / `markFailed`. `logWebhook` / `markWebhookProcessed` / idempotency helpers stay. |

---

## 2. Verdict — **full rewrite of the linking flow**

| Piece | Disposition |
|---|---|
| `lib/HipApi.php` — the 3 public methods | **Rewrite.** All 3 endpoints wrong. New: `linkInit()`, `smsNotify2()`, `linkOnConfirm()` (+ later `hiNotify()`). |
| `lib/HipApi.php` — `http()`, `token()`, `ok/fail`, `extractError()`, `logSafe()`, `uuid()`, `timestamp()`, `gender()` | **Keep** (~180 lines). Add `X-token` to `http()`. |
| `telemedicine/api/abdm-webhook.php` — security layers (§1–5: POST-only, size cap, rate limit, IP allowlist, `?k=` secret, raw-first log, always-200) | **Keep**, extract into `lib/AbdmWebhookGuard.php`. |
| `telemedicine/api/abdm-webhook.php` — routing + `wh_*` classifiers + link-token/linking-status handlers | **Rewrite.** Route by path; handle `on-init` / `confirm`. |
| `scripts/abdm-hip-worker.php` — cron scaffold, `--dry-run`, PII-safe `out()`, give-up sweep, the `abdm_care_context_links ⨝ prescriptions ⨝ users ⨝ abha_accounts` query | **Keep** the shape; **rewrite** the per-row action (init+sms instead of generate-token+link). |
| `migration_abdm_hip_linking.sql` — `abdm_link_tokens` | **Replace** with `abdm_link_requests`. |
| `migration_abdm_hip_linking.sql` — `abdm_care_context_links` | **Alter** — add `link_ref_number`; relax `request_id` uniqueness / add a link-request FK. |
| `migration_abdm_hip_linking.sql` — `abdm_webhook_log` | **Keep**; add `channel`. |
| `doctor/patient-form.php` — queues a `pending` row on finalised prescription | **Keep** the trigger point; the row it writes changes shape slightly (link request vs care-context link). |
| `config/abdm.php` — `ABDM_HIECM_BASE_URL`, `ABDM_HIP_ID`, `ABDM_HIP_NAME`, webhook secret/allowlist | **Keep** — all correct. |

**Reusable ≈ 55–60%** (transport, security, cron scaffold, config, trigger point,
raw-log table). **Rewrite ≈ 40–45%** (every endpoint path, the link-request lifecycle
table + helpers, webhook routing, worker action).

Since the migration is already applied to the dev DB and tracked in `schema_migrations`,
the replacement needs either a new migration that `DROP`s `abdm_link_tokens` +
`ALTER`s the others, or (cleaner, since no prod data) revising
`migration_abdm_hip_linking.sql` in place + re-running with a checksum bump.

---

## 3. Why the committed version was wrong

`HipApi.php` was written against the sandbox with placeholder HIP creds and only ever
got an HTTP 400 on `generate-token` — the path and the flow model were never validated
against a working call or an authoritative doc (noted in the build summary). The
`/v3/token/generate-token` + `/hip/v3/link/carecontext` + `X-LINK-TOKEN` shape looks
like it came from a partial/older linking spec (or was inferred). It should not be
relied on. The care-context *concept* and the async-webhook *architecture* are sound;
the specific endpoints are not.
