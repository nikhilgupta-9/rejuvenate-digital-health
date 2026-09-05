# HIP code vs ABDM Sandbox M2 spec v2.8 (Feb 2026) — mismatch report

**Files checked:** `lib/HipApi.php`, `telemedicine/api/abdm-webhook.php`,
`scripts/abdm-hip-worker.php` (+ the `hiType` value's origin in
`doctor/patient-form.php` / `lib/HipLinking.php`).
**Status:** report only — no code changed.

---

## A. Confirmed correct (no change)

| Item | Our code | Spec | ✓ |
|---|---|---|---|
| generateLinkToken endpoint | `POST {base}/v3/token/generate-token` → `…/api/hiecm/v3/token/generate-token` | `POST /api/hiecm/v3/token/generate-token` | ✅ |
| linkCareContext endpoint | `POST {base}/hip/v3/link/carecontext` | `POST /api/hiecm/hip/v3/link/carecontext` | ✅ |
| notifyCareContext endpoint | `POST {base}/hip/v3/link/context/notify` | `POST /api/hiecm/hip/v3/link/context/notify` | ✅ |
| X-LINK-TOKEN header on linkCareContext | sent via `$extra` in `http()` | required | ✅ |
| Required headers | `http()` always sends `Authorization: Bearer`, `REQUEST-ID`, `TIMESTAMP`, `X-CM-ID`, `X-HIP-ID` | same 5 required | ✅ |
| generateLinkToken body — `abhaAddress` / `name` / `gender` / `yearOfBirth` | present, `gender()` → `M`/`F`/`O`, `yearOfBirth` int | same | ✅ |
| "abhaAddress OR abhaNumber mandatory" | `abhaAddress` required in code; `abhaNumber` added only if 14 digits | one-of required | ✅ |
| linkCareContext `careContexts[]` items | `{referenceNumber, display}` | `{referenceNumber, display}` | ✅ |
| linkCareContext `count` | `count($ccs)` (= array length) | must equal careContexts length (else ABDM-1037) | ✅ |
| Link-token webhook — reads `linkToken` (top level) + `response.requestId` | `wh_link_token()` + `wh_request_id()` handle exactly these paths | `{ abhaAddress, linkToken, response:{requestId} }` | ✅ |

---

## B. Mismatches — fix required

| # | Sev | Where | Our code | Spec requires | Effect if unfixed |
|---|-----|-------|----------|---------------|-------------------|
| 1 | 🔴 **CRITICAL** | `HipApi::linkCareContext()` L152 + worker L119 + `patient-form.php` L302 | `hiType` passed **verbatim**; the value in flight is `"Prescription"` (PascalCase, from `patient-form.php` → `abdm_care_context_links.hi_type` → worker) | **UPPERCASE, no spaces**: `PRESCRIPTION`, `DIAGNOSTICREPORT`, `OPCONSULTATION`, `DISCHARGESUMMARY`, `IMMUNIZATIONRECORD`, `HEALTHDOCUMENTRECORD`, `WELLNESSRECORD` | **ABDM-9999 "Invalid HIType"** on every link call — the whole flow is dead |
| 2 | 🔴 **CRITICAL** | `HipApi::notifyCareContext()` L197-204 + worker L131 | same `"Prescription"` value reused | `notify` wants **PascalCase** with value-specific caps (`OPConsultation`, not `OPCONSULTATION`; `Prescription` OK). **Different convention from #1.** | wrong casing for anything except plain `Prescription`; `OPConsultation` etc. rejected |
| 3 | 🔴 High | `HipApi::linkCareContext()` L148-155 | `"patient"` is a **single JSON object** (`'patient' => ['referenceNumber'=>…]`) | `"patient"` is an **array of objects**: `"patient": [{ referenceNumber, display, careContexts[], hiType, count }]` | malformed body → ABDM-1064 / ABDM-9999 |
| 4 | 🔴 High | `HipApi::notifyCareContext()` L203 | `careContext` = `{ referenceNumber: … }` | `careContext` = `{ patientReference: …, careContextReference: … }` — **different key names + a second field we don't send** | notify body rejected; `notifyCareContext()` has no `patientReference` param at all |
| 5 | 🔴 High | `telemedicine/api/abdm-webhook.php` (whole file) | **one endpoint, no path routing** — `wh_callback_type()` guesses from payload keys (`linkToken` → linkToken; `acknowledgement`/`status`/`error` → linking-status) | ABDM POSTs **three distinct paths**: `/api/v3/hip/token/on-generate-token`, `/api/v3/link/on_carecontext` (underscore), `/api/v3/links/context/on-notify` | if ABDM calls the standard sub-paths off a registered bridge URL, our `abdm-webhook.php` receives **nothing**; even reached directly, the care-context + notify callback bodies are unhandled |
| 6 | 🟠 Med | `abdm-webhook.php` `wh_callback_type()` / handlers | only `linkToken` + a generic `linking-status` are handled | `/api/v3/link/on_carecontext` and `/api/v3/links/context/on-notify` each have their own body shape (not verified) | care-context link results + notify acks never recorded → rows stay `pending` forever → worker gives up at 48h |
| 7 | 🟠 Med | `HipApi::extractError()` L309-337 | generic shapes + HTTP-status switch only | map **ABDM-1030, 1016, 1064, 1092, 1090, 1062, 1038, 1066, 1063, 1037, 1027, 1207, 9999** to readable text | operators see "ABDM rejected the linking request" with no idea it's e.g. a 24-h rate-block (1027) or a duplicate token (1092) |
| 8 | 🟠 Med | `abdm-webhook.php` (link-token callback) | reads `linkToken` only; ignores an `error` field | **ABDM-1207** (demographic details invalid) arrives **async in the token-generate callback's `error` field** | a failed token generation looks like "still waiting" — row never moves off `pending` |
| 9 | 🟡 Low | `HipApi::linkCareContext()` L157-159 + `generateLinkToken()` L87-88 | `abhaNumber` sent **formatted** `XX-XXXX-XXXX-XXXX` (`AbdmApi::formatAbhaNumber()`) | spec text says `<14-digit>` for generate-token; linkCareContext just shows `"abhaNumber": "..."` | if the token was minted from a bare 14-digit and we send formatted on link (or vice-versa) → **ABDM-1062** "ABHA number mismatch with link token". Needs the format pinned identically on both calls. |
| 10 | 🟡 Low | `HipApi::notifyCareContext()` L206 | `hip` = `{ id, name }` | spec shows `hip` = `{ id }` only | extra field — probably ignored, but not per spec |
| 11 | 🟡 Low | `scripts/abdm-hip-worker.php` L131 | `notifyCareContext()` gets no patient reference | notify `careContext.patientReference` is required (#4) | can't build a compliant notify body until the method signature + worker call add it |
| 12 | 🟡 Info | `abdm-webhook.php` `wh_request_id()` | checks `response.requestId`, `requestId`, `resp.requestId`, `acknowledgement.requestId` | link-token callback uses `response.requestId` ✅; the other two callbacks' requestId location is unverified | fine for link-token; re-check once #5/#6 add real routing |

---

## C. Casing reference (from the spec, for the fix)

| Care-context type | `linkCareContext.hiType` (UPPERCASE) | `notifyCareContext.hiTypes[]` (PascalCase) |
|---|---|---|
| Prescription | `PRESCRIPTION` | `Prescription` |
| Diagnostic report | `DIAGNOSTICREPORT` | `DiagnosticReport` |
| OP consultation | `OPCONSULTATION` | `OPConsultation` |
| Discharge summary | `DISCHARGESUMMARY` | `DischargeSummary` |
| Immunization record | `IMMUNIZATIONRECORD` | `ImmunizationRecord` |
| Health document record | `HEALTHDOCUMENTRECORD` | `HealthDocumentRecord` |
| Wellness record | `WELLNESSRECORD` | `WellnessRecord` |

→ fix needs **one canonical map** (`Prescription` → both forms) applied in `HipApi`,
not raw pass-through. Store the canonical PascalCase key in
`abdm_care_context_links.hi_type` and let `HipApi` derive the uppercase form.

---

## D. Webhook routing — the structural question

Our `abdm-webhook.php` is registered as a single URL and branches on payload keys.
The spec names three fixed callback paths. Two ways this is actually wired in the
sandbox — **confirm which from the M2 doc's onboarding section**:

1. **Bridge base URL** — we register `https://host/abdm` and ABDM appends
   `/api/v3/hip/token/on-generate-token` etc. → we need a **front controller** that
   routes on `REQUEST_URI` (Apache rewrite `/abdm/api/v3/... → dispatcher.php`), with
   three handlers.
2. **Per-callback URL** — we register each of the three URLs separately → three
   small endpoint files, shared guard lib.

Either way the current single-file / classify-by-keys design does not match. The
security layer inside `abdm-webhook.php` (raw-first log, size cap, rate limit, IP
allowlist, `?k=` secret, always-200, requestId gate) is reusable as-is.

---

## E. Fix sizing (when we do it)

| Fix | Effort |
|---|---|
| #1 #2 #11 — canonical hiType map + `notifyCareContext` gets `patientReference` | small — `HipApi` + worker + one column-value normalisation |
| #3 #4 #10 — body-shape corrections (`patient[]`, `careContext` keys, drop `hip.name`) | small — `HipApi` only |
| #7 #8 — ABDM-XXXX codes in `extractError()` + parse callback `error` field | small |
| #5 #6 #12 — webhook routing to the 3 paths + care-context/notify handlers | **medium** — depends on the routing model (§D); needs the callback body shapes from the doc |
| #9 — pin `abhaNumber` format across generate + link | trivial once the expected format is confirmed |
