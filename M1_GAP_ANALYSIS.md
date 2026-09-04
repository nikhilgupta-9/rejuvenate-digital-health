# ABDM M1 Endpoint Checklist — Gap Analysis

**Scope:** the official M1 checklist supplied (ABDM ABHA **v1 / v2** API — the legacy
`healthidsbx.abdm.gov.in/api/**v1**` + `/api/**v2**` surface) vs. what
[`lib/AbdmApi.php`](lib/AbdmApi.php) actually implements.

---

## 0. Headline finding — the code is on a different API generation

`lib/AbdmApi.php` is a clean-room **ABHA API v3** client (`abhasbx.abdm.gov.in/abha/api/v3`,
spec v1.3 July 2025). The checklist is the **v1/v2** milestone-1 API. ABDM **restructured
every route** between v2 → v3:

| Concept | Checklist (v1/v2) | This codebase (v3) |
|---|---|---|
| Registration (all modes) | `v1/registration/aadhaar/*`, `v2/registration/mobile/*`, `v2/document/*` | one `/enrollment/request/otp` + `/enrollment/enrol/by{Aadhaar,Document}` + `/enrollment/auth/byAbdm`, differentiated by a `scope` array (`abha-enrol` / `mobile-verify` / `dl-flow`) |
| Login | `v1/auth/init`, `v1/auth/confirmWith*`, `v1/auth/authPassword` | `/profile/login/request/otp` → `/profile/login/verify` → `/profile/login/verify/user` (Transfer-token → X-token exchange) |
| Cert for encryption | `v1/auth/cert` | `GET /profile/public/certificate` |
| Profile | `v1/account/profile`, `v1/account/qrCode` | `GET /profile/account`, `POST /profile/account/getAbhaCard` |
| Edit / password / mobile / email | `v1/account/change/**`, `v2/account/change/**`, `v2/account/email/**` | **none** |
| Delete / deactivate | `v2/account/profile/{delete,deactivate}` | `DELETE /profile/account/delete` only |
| Forgot ABHA | `v2/auth/reactivate*` | **none** |
| OAuth | (session token) | `POST {gateway}/gateway/v3/sessions` |

**Net effect:** *zero* checklist URLs match verbatim (all are v1/v2; the code is v3).
Roughly **60 %** of the checklist is functionally covered by a differently-shaped v3
call; the **account-management surface (password / mobile / email change, deactivate,
forgot-ABHA, logout, QR)** is entirely absent.

**Tested-status legend** (from `PROJECT_STATUS.md` §3.5 + inline code comments):
🟢 verified on sandbox · 🟡 coded, untested / lightly exercised · 🟠 coded but sandbox
rejects it with current credentials · 🔴 NOT IMPLEMENTED

---

## 1. REGISTRATION — Aadhaar

| Endpoint (checklist, v1) | Implemented? (method) | Status |
|---|---|---|
| `v1/auth/cert` | `getPublicCert()` → `GET /profile/public/certificate`; used by `rsaEncrypt()` | 🟢 **URL differs (v3 path, not `v1/auth/cert`)**, function identical. Verified — RSA encrypt/decrypt round-trips on sandbox. |
| `v1/registration/aadhaar/generateOtp` | `generateAadhaarOtp($aadhaar, $txnId='')` → `POST /enrollment/request/otp` `scope:["abha-enrol"]` | 🟢 **URL differs (v3 enrollment route)**. Verified on sandbox ("Create ABHA — Aadhaar OTP (M1) 🟢 Works"). |
| `v1/registration/aadhaar/verifyOTP` | `enrolByAadhaar($otp, $txnId, $mobile='')` → `POST /enrollment/enrol/byAadhaar` | 🟢 **URL differs (v3)**. Verified on sandbox — returns `ABHAProfile` + `tokens.token`. Empirically tuned (plain-text `mobile` field quirk documented in code). |
| `v1/registration/aadhaar/generateMobileOTP` | `generateMobileOtpForEnrollment($mobile, $txnId)` → `POST /enrollment/request/otp` `scope:["mobile-verify"]` | 🟡 **URL differs (v3)**. "Coded, lightly exercised" (§3.5). Used only when comm-mobile ≠ Aadhaar mobile. |
| `v1/registration/aadhaar/verifyMobileOTP` | `verifyMobileForEnrollment($otp, $txnId)` → `POST /enrollment/auth/byAbdm` `scope:["mobile-verify"]` | 🟡 **URL differs (v3)**. Coded, untested on sandbox. |
| `v1/registration/aadhaar/createHealthIdByAadhar` | — (folded into `enrolByAadhaar()`; v3 has no separate "create" step, the verify call creates the ABHA) | 🟢 covered by `enrolByAadhaar()`. No standalone method. |
| `v1/search/existsByHealthId` | — nearest is `searchByHealthId()` → `POST /search/searchByAbhaNumber` (different purpose: full lookup, not a boolean "exists" pre-check) | 🟠 **No dedicated "exists" check.** `searchByHealthId()` exists but sandbox returns *"not available to this credential"* (§3.5). |

**Aadhaar registration verdict:** core create-flow 🟢 built & verified; mobile-verify
sub-flow 🟡 untested; no `existsByHealthId` pre-check.

---

## 2. REGISTRATION — Mobile

| Endpoint (checklist, v2) | Implemented? (method) | Status |
|---|---|---|
| `v2/registration/mobile/generateOtp` | `generateMobileOtp()` alias → `generateMobileOtpForEnrollment()` → `POST /enrollment/request/otp` `scope:["mobile-verify"]` | 🟡 **URL differs (v3)**. This is *mobile-verify during Aadhaar enrol*, **not** a standalone mobile-only registration. Lightly exercised. |
| `v2/registration/mobile/verifyOtp` | `verifyMobileOtpM2()` alias → `verifyMobileForEnrollment()` → `POST /enrollment/auth/byAbdm` | 🟡 **URL differs (v3)**. Coded, untested. |
| `v2/registration/mobile/createHidViaMobile` | `createAbhaMobile($txnId, $profile)` → `POST /enrollment/enrol/byDocument` `scope:["dl-flow"]` — **marked deprecated in code**, actually posts to the *document* route | 🟠 **Mismatch.** Method name says "mobile" but it hits `enrol/byDocument`. v3 has **no mobile-only ABHA creation** (ABDM removed it). Effectively 🔴 for true mobile-only registration. |
| `v2/registration/mobile/resendOtp` | — (resend is done by re-calling `generateAadhaarOtp`/`generateMobileOtpForEnrollment` with the previous `$txnId`) | 🟡 no dedicated method; resend-by-txnId pattern is supported by the generate methods. |

**Mobile registration verdict:** 🔴 **not a real capability.** v3 dropped mobile-only
ABHA creation; the code's `createAbhaMobile()` is a deprecated shim pointing at the
document endpoint. Only "verify a mobile during Aadhaar enrol" is coded (untested).

---

## 3. REGISTRATION — Driving Licence

| Endpoint (checklist, v2) | Implemented? (method) | Status |
|---|---|---|
| `v2/document/generate/mobile/otp` | `generateDlOtp($mobile, $txnId='')` → `POST /enrollment/request/otp` `scope:["dl-flow"]` | 🟡 **URL differs (v3)**. "Create ABHA — Driving Licence (M3) 🟡 Coded, unverified" (§3.5). |
| `v2/document/verify/mobile/otp` | `verifyDlOtp($otp, $txnId)` → `POST /enrollment/auth/byAbdm` `scope:["dl-flow"]` | 🟡 **URL differs (v3)**. Coded, unverified. |
| `v2/document/validate` | — (no standalone validate step; v3 validates inside `enrol/byDocument`) | 🟡 folded into `createAbhaDl()`. No separate method. |
| `v2/document` | `createAbhaDl($txnId, $dlData)` → `POST /enrollment/enrol/byDocument` `authMethods:["dl"]` | 🟡 **URL differs (v3)**. Coded, unverified. Expects `documentId`, name, dob, gender, address, front/back photo base64. |

**DL registration verdict:** 🟡 fully coded end-to-end, **never verified on sandbox.**

---

## 4. LOGIN (existing ABHA holder)

| Endpoint (checklist, v1) | Implemented? (method) | Status |
|---|---|---|
| `v1/auth/cert` | `getPublicCert()` (shared with registration) | 🟢 URL differs (v3), verified. |
| `v1/search/searchByHealthId` | `searchByHealthId($abhaNumber)` → `POST /search/searchByAbhaNumber` | 🟠 **URL differs (v3)**. Sandbox: *"not available to this credential"* (§3.5) — coded but non-functional with current creds. |
| `v1/auth/init` | `initAuth($loginId, $loginHint, $otpSystem, $scopes)` → `POST /profile/login/request/otp` | 🟡 **URL differs (v3)**. "Works on sandbox with quirks" (§3.5). Supports `loginHint` = mobile / abha-number / aadhaar. Bare `["abha-login"]` scope rejected — code auto-pairs with `mobile-verify`/`aadhaar-verify`. |
| `v1/auth/confirmWithAadhaarOtp` | `confirmWithAadhaarOtp($otp,$txnId)` alias → `confirmAuth($otp,$txnId,['abha-login','aadhaar-verify'])` → `POST /profile/login/verify` | 🟡 **URL differs (v3)**. Works with quirks; carries `T-token` header. |
| `v1/auth/confirmWithMobileOTP` | `confirmWithMobileOtp($otp,$txnId)` alias → `confirmAuth()` → `POST /profile/login/verify` | 🟡 **URL differs (v3)**. Works with quirks. **Plus a mandatory 3rd step not in the checklist:** `verifyUserLogin($txnId,$abhaNumber,$tToken)` → `POST /profile/login/verify/user` to swap the Transfer-token for the real X-token (documented "X-token expired / ABDM-1094" bug; still has `TEMP DIAGNOSTIC` `error_log` lines). |
| `v1/auth/authPassword` | — | 🔴 **NOT IMPLEMENTED.** No password-based ABHA login anywhere. |

Extra (not on checklist, but built): `searchAbhaByMobile()` → `POST /profile/account/abha/search`,
and `requestIndexOtp()` → `POST /profile/login/request/otp` `loginHint:"index"` — for the
multi-ABHA-per-mobile case. 🟡 coded, untested.

**Login verdict:** OTP login (mobile / aadhaar / abha-number) 🟡 works with known quirks;
password login 🔴 missing; ABHA search 🟠 blocked by credentials.

---

## 5. FORGOT ABHA

| Endpoint (checklist, v1/v2) | Implemented? (method) | Status |
|---|---|---|
| `v1/auth/cert` | `getPublicCert()` | 🟢 (shared) |
| `v1/search/searchByHealthId` | `searchByHealthId()` | 🟠 blocked by credentials (see §4) |
| `v2/auth/reactivate/init` | — | 🔴 **NOT IMPLEMENTED** |
| `v2/auth/reactivate` | — | 🔴 **NOT IMPLEMENTED** |

**Forgot-ABHA verdict:** 🔴 **entirely absent.** No account reactivation / recovery flow.

---

## 6. PROFILE

| Endpoint (checklist, v1) | Implemented? (method) | Status |
|---|---|---|
| `v1/account/profile` | `getProfile($xToken)` → `GET /profile/account` (`X-token` header) | 🟡 **URL differs (v3)**. "Coded; 'X-token expired' bug referenced in comments" (§3.5). Has `TEMP DIAGNOSTIC` `error_log` lines dumping the full body + JWT claims. |
| `v1/account/qrCode` | — nearest is `getAbhaCard()` / `getAbhaCardPdf()` → `POST /profile/account/getAbhaCard` (returns the **card image/PDF**, not a bare QR) | 🟠 **No QR-code endpoint.** Card PNG/PDF 🟡 coded, untested. |

**Profile verdict:** profile-fetch 🟡 coded with an open X-token bug; standalone QR 🔴 missing.

---

## 7. EDIT PROFILE

### 7a. Password

| Endpoint (checklist, v1) | Implemented? | Status |
|---|---|---|
| `v1/account/change/passwd/generateAadhaarOTP` | — | 🔴 NOT IMPLEMENTED |
| `v1/account/change/passwd/byAadhaar` | — | 🔴 NOT IMPLEMENTED |
| `v1/account/change/password` | — | 🔴 NOT IMPLEMENTED |
| `v1/account/change/passwd/generateMobileOTP` | — | 🔴 NOT IMPLEMENTED |
| `v1/account/change/passwd/byMobile` | — | 🔴 NOT IMPLEMENTED |
| `v1/account/logout` | — | 🔴 NOT IMPLEMENTED |

### 7b. Mobile

| Endpoint (checklist, v2) | Implemented? | Status |
|---|---|---|
| `v2/account/change/mobile/new/generateOTP` | — | 🔴 NOT IMPLEMENTED |
| `v2/account/change/mobile/new/verifyOTP` | — | 🔴 NOT IMPLEMENTED |
| `v2/account/change/mobile/update/authentication` | — | 🔴 NOT IMPLEMENTED |
| `v2/account/change/mobile/aadhaar/generateOTP` | — | 🔴 NOT IMPLEMENTED |
| `v2/account/change/mobile/old/generateOTP` | — | 🔴 NOT IMPLEMENTED |

### 7c. Email

| Endpoint (checklist, v2) | Implemented? | Status |
|---|---|---|
| `v2/account/email/verification/auth/initiate/send` | — | 🔴 NOT IMPLEMENTED |
| `v2/account/email/verification/auth/verify` | — | 🔴 NOT IMPLEMENTED |

### 7d. What *is* built for editing (not on checklist)

| v3 method | Route | Status |
|---|---|---|
| `updateProfile($xToken, $fields)` | `PUT /profile/account/update` | 🟡 coded, untested — generic name/email/address/photo update |
| `getEnrollmentAbhaAddressSuggestions()` / `setEnrollmentAbhaAddress()` | `GET /enrollment/enrol/suggestion`, `POST /enrollment/enrol/abha-address` | 🟡 coded ("ABHA address suggest/set 🟡 Coded", §3.5) |
| `getAbhaAddressSuggestions()` / `updateAbhaAddress()` | `GET/PUT /profile/account/abha-address[/suggestions]` | 🟡 coded, untested |

**Edit-profile verdict:** 🔴 **every checklist item (password / mobile / email change,
logout) is missing.** Only a generic `updateProfile` + ABHA-address management exist,
all untested.

---

## 8. DELETE / DEACTIVATE ABHA

| Endpoint (checklist, v2) | Implemented? (method) | Status |
|---|---|---|
| `v2/account/aadhaar/generateOTP` | — (no pre-delete OTP step) | 🔴 NOT IMPLEMENTED |
| `v2/account/profile/delete` | `deleteAccount($xToken, $reason='')` → `DELETE /profile/account/delete` | 🟡 **URL differs (v3)**. Coded, untested. **No OTP confirmation step** — checklist expects `aadhaar/generateOTP` first. |
| `v2/account/mobile/generateOTP` | — | 🔴 NOT IMPLEMENTED |
| `v2/account/profile/deactivate` | — (`deleteAccount()` hits `/delete`, not `/deactivate`) | 🔴 **NOT IMPLEMENTED** — only hard delete, no reversible deactivate. |

**Delete/deactivate verdict:** a bare `deleteAccount()` exists (untested, no OTP gate);
deactivate + OTP-confirmation flow 🔴 missing.

---

## 9. Summary scoreboard

| Section | Checklist items | 🟢 verified | 🟡 coded/untested | 🟠 blocked/partial | 🔴 missing |
|---|---:|---:|---:|---:|---:|
| Registration — Aadhaar | 7 | 3 | 3 | 1 | 0 |
| Registration — Mobile | 4 | 0 | 2 | 1 | 1 (effectively 4 — no real mobile-only reg) |
| Registration — DL | 4 | 0 | 4 | 0 | 0 |
| Login | 6 | 1 | 3 | 1 | 1 |
| Forgot ABHA | 4 | 1 | 0 | 1 | 2 |
| Profile | 2 | 0 | 1 | 1 | 0 |
| Edit — Password | 6 | 0 | 0 | 0 | 6 |
| Edit — Mobile | 5 | 0 | 0 | 0 | 5 |
| Edit — Email | 2 | 0 | 0 | 0 | 2 |
| Delete / Deactivate | 4 | 0 | 1 | 0 | 3 |
| **Total** | **44** | **~9** | **~17** | **~6** | **~18** |

### Biggest gaps (priority order)

1. **All account self-service editing** (password, mobile, email change, logout) — 13
   endpoints, 0 coded. Needed for a compliant "Edit ABHA" screen.
2. **Forgot / reactivate ABHA** — 0 coded. Users who lose access have no recovery path.
3. **Deactivate (reversible) + OTP-gated delete** — only an unconfirmed hard delete exists.
4. **Password login** (`authPassword`) — 0 coded.
5. **Standalone QR code** (`v1/account/qrCode`) — only the full card image exists.
6. **`existsByHealthId`** pre-check — 0 coded (and `search*` is credential-blocked).

### Caveats on "verified"

- Only the **Aadhaar-OTP create** path and **OAuth + RSA cert** are genuinely
  sandbox-verified.
- **Login** "works with quirks" — the Transfer-token → X-token exchange
  (`verifyUserLogin`) still carries `TEMP DIAGNOSTIC` `error_log` lines and an
  unresolved "X-token expired" note; treat as 🟡 until those are removed.
- Everything in §2, §3, §7d, §8 is **coded but has never returned a success on the
  sandbox** per the audit.
- `search*` (used by login step 1 and `existsByHealthId`) is **rejected by the current
  sandbox credential** — a credentials/entitlement issue, not a code bug.

### Version note (applies to every row)

**No checklist URL matches the code verbatim.** The checklist is ABHA **v1/v2**; every
implemented call is ABHA **v3** (`/abha/api/v3/...`). Where this doc says "URL differs
(v3)", the *function* is equivalent but the route, request body shape (`scope` arrays),
and auth headers (`T-token`, `X-token`, `REQUEST-ID`, `X-CM-ID`) are all v3-specific. If
the compliance requirement is literally "these v1/v2 endpoints must be called", **none**
are met; if it is "this capability must exist", use the status columns above.
