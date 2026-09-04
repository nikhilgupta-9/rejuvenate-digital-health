# PROJECT STATUS — Rejuvenate Digital Health

**Audit date:** 2026-09-04
**Branch:** `claude/rejuvenate-data-model-abha-x7gxdy`
**Scope:** Full codebase scan — `admin/`, `ajax/`, `config/`, `database/`, `doctor/`, `lib/`, `school/`, `telemedicine/`, `user/`, `util/`, `forgot-password/`, root `*.php`, `composer.*`, `CLAUDE.md`, `abdm-debug.php`.
**Nature:** Analysis only — no code was modified.

> This file is intended as the single source of truth for planning. Update it as modules move forward.

---

## 1. Executive Summary

| Area | State | One-line verdict |
|---|---|---|
| Public site (marketing pages, booking) | 🟢 Working | Large, functional, some giant single files |
| Patient (`user/`) panel | 🟡 Working, session-based | Feature-complete for MVP; JWT migration not started here |
| Doctor panel (`doctor/`) | 🟢 Mostly complete | JWT auth done & consistent; ABHA/HPR pieces partial |
| Admin panel (`admin/`) | 🟡 Working, mixed auth | JWT guard on ~50 pages; legacy/unguarded pages remain |
| School module (`school/`) | 🟡 Working, session-based | Own guard (`school/auth/auth.php`); not JWT |
| ABHA / ABDM integration | 🟠 Partial | Create + existing-ABHA login work on sandbox; **consent + data-exchange (HIP/HIU/care-context) not implemented** |
| HPR (doctor registry) | 🔴 Not integrated | Admin-manual review only; **no ABDM HPR API calls exist** |
| Telemedicine / WebRTC | 🟢 Working | HTTP-polling signalling, signed tickets; WS server deprecated |
| Security posture | 🟠 Improving | P0 items 1/2/4/5/6 fixed in code (2026-09-04); residual: git-history purge, secret rotation, GET-CSRF (§6) |
| Database schema | 🟠 Inconsistent | No FKs on core tables, naming drift, 10 tables created at runtime |

**Top 5 things to fix before any production/pilot use** (detail in §6):

1. ~~`config/abdm.php` hardcoded ABDM client ID + secret~~ ✅ fixed (reads `.env`). **Left:** rotate secret + purge git history.
2. ~~`abdm-debug.php` unauthenticated debug endpoint~~ ✅ deleted. **Left:** purge git history.
3. `.env` contains **live Razorpay keys**, a permanent WhatsApp token, SMTP password and the JWT secret — verify it is never web-served (the cPanel PHP handler block in `.htaccess` is commented out). **Still open.**
4. ~~`ajax/login-abdm.php` no CSRF / rate-limit / identity binding~~ ✅ fixed.
5. ~~~30 unguarded `admin/*.php` + doctor endpoints~~ ✅ fixed (`admin/auth/bootstrap.php` + `doctor_jwt_guard()`).
6. ~~`display_errors` on in prod + `$conn->error` echoed to client~~ ✅ fixed (env-gated; 14 leak sites genericised). **Left:** set `APP_ENV=production` in live `.env`.

---

## 2. Architecture vs CLAUDE.md — Discrepancies

| CLAUDE.md says | Actual code | Impact |
|---|---|---|
| DB `u950539402_reju_digi_beta` | `.env` → `rej_digital_health_db`; migrations reference **both** names | Confusion; migration headers inconsistent |
| "Auth currently: PHP sessions — migrating to JWT" | Doctor + Admin **are on JWT**; Patient + School still sessions | Migration is ~60% done, undocumented |
| `users.abha_number` (14-digit) is a "missing field" | Code stores the 14-digit number in **`users.abha_id`**; `appointments` uses a separate **`abha_number`** column | Naming drift (see §5) |
| "HPR verification via ABDM Aadhaar OTP flow" | No HPR endpoint in `lib/AbdmApi.php`; `hpr_verification_requests` table + `admin/hpr-verification.php` = **manual review** | HPR compliance is a stub |
| "Each appointment creates a care context entry" / "Data linked via care contexts" | `prescriptions.care_context_ref` is just `'CC-'.$appointment_id`; **nothing is pushed to ABDM** | No real HIP/link-record/consent flow |
| "JWT secret stored in `config/connect.php` as `JWT_SECRET`" | Defined there **from `.env`** (`JWT_SECRET` env var) — OK, but also re-`define()`d in `admin/auth/guard.php` | Minor duplication |
| "Refresh token stored in DB table `jwt_refresh_tokens`" | ✅ Implemented and rotated correctly for doctor + admin | Matches |
| Coding rule: "Always use prepared statements" | Mostly followed; `admin/functions.php` and a few legacy admin pages still use `mysqli_real_escape_string` (31 files) | Legacy debt, low active risk |

---

## 3. Module-by-Module Status

### 3.1 Root / Public site & shared auth

**Complete & working**
- Marketing pages: `index.php` (50KB), `about-us.php`, `doctor-network.php`, `e-cardiology.php` (department pages via `.htaccess` alias → `e-cardiology.php?alias=`), `faq.php`, legal pages, `sitemap.php`.
- `book-appointment.php` (46KB) — public appointment booking with Razorpay (`util/create-razorpay-order.php`, `util/appointment-handler.php`), slot generation, guest bookings.
- Unified login: `login.php` (34KB) → `process-login.php` → `util/auth-helper.php::findByIdentifier()` resolves patient / school-user / school-member / doctor across tables; role → session via `setRoleSession()`.
- Patient signup: `signup.php` → `process-signup.php` with WhatsApp+email OTP pre-verification (`util/otp-service.php`, `otp_consume_token`).
- Doctor signup: `doctor-signup.php` (22KB).
- School onboarding: `school-register.php`, `student-register.php`, `teacher-register.php` (+ thin `student-login.php` / `teacher-login.php` shims).
- OTP verify: `verify-otp.php` (email OTP for unverified-patient login gate).

**Incomplete / half-implemented**
- `student-login.php` / `teacher-login.php` are 339-byte redirect stubs — the real flow is the unified `login.php`. Dead-ish weight.
- Patient "remember me" in `process-login.php`: sets `remember_token` cookie + plaintext `user_id` cookie, `UPDATE users SET remember_token='$token'` — **the cookie is never actually consumed anywhere** (no auto-login check on page load). Half-feature.
- Root `forgot-password.php` is **doctor-only** password reset; leaks account state ("not verified" / "not active") → **user enumeration**. Contrast with `admin/auth/forgot-password.php` which does it correctly (generic success message, CSRF, rate-limit).

**Security notes**
- `config/connect.php` sets `display_errors = 1` and `error_reporting(E_ALL)` unconditionally — **stack traces to browser in production**. Same in `admin/db-conn.php` and many admin pages (`ini_set('display_errors',1)` copy-pasted).
- `process-login.php` builds error HTML with `<strong>` from `school_name` — output is later echoed; check `login.php` escapes `login_errors` (it stores some values `htmlspecialchars`'d, some not — inconsistent).

---

### 3.2 Doctor panel (`doctor/`, `doctor-login.php`, `forgot-password/`)

**Complete & working**
- **JWT auth — consistent.** `doctor/auth/guard.php::doctor_jwt_guard()` used by 36 pages; `doctor/auth/login-api.php` issues access (15 min) + refresh (7 day, SHA-256 hashed, rotated) tokens in `HttpOnly; SameSite=Strict` cookies. `doctor-login.php` (root) is the form; supports email/phone + HPR-ID-format identifier.
- **Activation gate** (`lib/DoctorAccess.php`): doctor is "active" only within 7-day grace window OR `is_verified=1` + paid `doctor_subscriptions` row. Enforced in guard via `DOCTOR_GATE_ALLOWLIST`.
- Dashboard (`doctor-dashboard.php`, 42KB), My Patients, Appointments, Manage Schedule (`generate_doctor_slots()` shared with admin), Patient Form / prescription (`patient-form.php`, 72KB), OPD slip PDF (`opd-slip.php`, FPDF), Analysis Report, Earnings + bank settlement, Payment History, Subscriptions (`create-subscription-order.php` / `verify-subscription-payment.php`, Razorpay), Change Password, Delete Account (request→admin review).
- School-health flow: `school-students.php`, `student-profile.php`, parent-consent gate (`doctor/inc/consent-helper.php`), student certificates/prescriptions.
- Doctor-side ABHA patient onboarding: `doctor/api/abdm-api.php` (new, well-built — JWT + CSRF + rate-limit + `AuditLogger` + `Validator`), `doctor/api/create-patient-submit.php`, `add-patient-abha.php`, `add-patient-manual.php`, `add-patient-mobile.php`.

**Incomplete / half-implemented**
- `doctor/add-patient-new-abha.php` **and** `doctor/add-patient-abha.php` **and** `doctor/add-patient.php` **and** `doctor/add-patient-mobile.php` **and** `doctor/add-patient-manual.php` — five overlapping "add patient" screens. Unclear which is canonical.
- `my-contact.php` is where a doctor adds HPR ID, but there's **no verification** — it just writes `doctors.hpr_id`. The "Verified" badge in `doctor/inc/sidebar.php` keys off `doctors.hpr_verified`, which only an admin can set.
- `doctor/patient-form.php` **creates the `prescriptions` table at runtime** (`CREATE TABLE IF NOT EXISTS` at line 36) — no migration file exists for the single most important clinical table.
- `care_context_ref` is generated locally, never registered with ABDM (no HIP link / notify).
- `pending-uploads.php` ("ABHA Compliance" nav section) — a queue with no backend push target yet.

**Not guarded**
- `doctor/opd-slip.php` — does its own inline JWT check for doctor/admin/patient (acceptable), but `doctor/doctor-logout.php` and a couple of helpers rely on being harmless.
- `forgot-password/reset-password.php` (doctor) — separate 27KB implementation from `admin/auth/reset-password.php`; token is SHA-256 hashed in DB (good), but password policy / notification differ from the admin version.

---

### 3.3 Patient panel (`user/`)

**Complete & working**
- `user/user-dashboard.php`, `my-profile.php`, `my-bookings.php`, `my-doctor-appointments.php`, `appointment-details.php`, `my-reports.php`, `manage-address.php`, `update-profile-picture.php`, `my-supplement-order.php`, `help-and-contact.php`.
- `user/my-abha.php` — ABHA link/create UI, talks to `ajax/abdm-api.php`; manual fallback via `user_abha_requests` (admin approves in `admin/abha-management.php`).
- Guard: plain `$_SESSION['logged_in']` check copy-pasted at top of each page (not a shared include).

**Incomplete / half-implemented**
- No JWT (CLAUDE.md implies patients migrate too). Fine for now but inconsistent with doctor/admin.
- `user/function.php` exists as a second, patient-scoped function file alongside `util/function.php` — small, but another place helpers can diverge.
- Patient has **no consent-management UI** (CLAUDE.md Phase 2). Consent today is only the school parent-consent form, not ABDM HI consent.

---

### 3.4 Admin panel (`admin/`)

**Complete & working**
- **JWT auth** via `admin/auth/guard.php::admin_jwt_guard()` — 51 pages call it directly, and `admin/functions.php` calls it on include (covering ~15 more pages that POST to `functions.php` or include it).
- `admin/auth/login.php` — solid: CSRF, per-account lock (`admin_user.failed_attempts`/`locked_until`), **IP-based rate limit** (`login_rate_limits` table), `session_regenerate_id`, timing jitter, `AuditLogger`.
- `admin/auth/forgot-password.php` + `reset-password.php` — CSRF, generic messages, one-hour single-use token, strong password policy, notification email.
- Feature pages: dashboard with ABHA stats, all-appointments (rebuilt, AJAX action endpoints in `admin/ajax/`), doctors CRUD, schools approve/list/view, school members, school plans + payments, parent consents, prescriptions viewer, medical-records upload, settlements, permissions/roles, HPR verification queue, ABHA management, telemedicine settings (TURN/STUN), live consultations, video-call history.
- `admin/ajax/appointment-*.php` — all guarded.

**Incomplete / half-implemented**
- `admin/auth/auth.php` is a **0-byte file** — dead. Nothing includes it (the real guard is `guard.php`).
- `admin/auth/login-sessions.php` — legacy session-hijack guard (IP + UA + 2h timeout). Superseded by JWT; still present, unclear if referenced.
- Large parts of `admin/` are an **e-commerce theme** (`products.php`, `brand.php`, `orders.php`, `best-seller.php`, `make-deal-of-the-day.php`, `add-special-offer.php`, `invoice-generate.php`, "Tax Invoice", HTTrack-mirrored template comments) — not part of the health platform. Dead weight / attack surface.
- `admin/functions.php` (POST target + helper lib) still uses `mysqli_real_escape_string` throughout and `mt_rand()` IDs.

**Not guarded (no `admin_jwt_guard`, and do NOT include `functions.php`)**
- `admin/invoice-generate.php` — includes only `db-conn.php`. **Exposes invoice/PDF generation unauthenticated.**
- `admin/header.php` — falls back to a **session** check (`$_SESSION['admin_logged_in'] || $_SESSION['doctor_logged_in']`), not JWT. Pages that render `header.php` but skip the guard are protected only if the stale session flag logic holds.
- Confirm each of these individually: `about_us.php`, `add-blog.php`, `add-gallery.php`, `awards.php`, `home-items.php`, `manage-faq.php`, `management.php`, `new-leads.php`, `orders.php`, `show-products.php`, `today-appointments.php`, `update-product*.php`, `view-*` — most include `functions.php` (→ guarded), but the ones that only include `db-conn.php` are open. `today-appointments.php` includes `functions.php` → guarded.

---

### 3.5 School module (`school/`)

**Complete & working**
- Guard: `school/auth/auth.php` (session `school_logged_in`) used by 24 pages; separate `school/student/auth.php`, `school/teacher/auth.php`.
- Dashboards for school-admin / teacher / student; member management (add/edit/import via `phpspreadsheet`, `import-template.php`), health profiles, health cards with QR (`share_token`), certificates, records.
- **Parent consent** (`school/parent-consent.php`): consent form + age-locked health plan + Razorpay payment; consumed across school/admin/doctor panels; `parent_consent_forms` table.
- `school/request-password-link.php` + `school/set-password.php` — member credential setup.

**Incomplete / half-implemented**
- `school/parent-consent.php` **creates its table(s) at runtime** (`CREATE TABLE IF NOT EXISTS` x2) — `parent_consent_forms` has **no migration file** (only `school_module.sql` and `migration_appointment_booking.sql` mention "consent").
- Session-based, not JWT — a third auth model in the codebase.
- `school/student/abha.php`, `school/teacher/abha.php`, `school/health/abha.php` — three near-identical ABHA link screens.

---

### 3.6 ABHA / ABDM integration (`lib/AbdmApi.php`, `ajax/abdm-api.php`, `ajax/login-abdm.php`, `doctor/api/abdm-api.php`, `config/abdm.php`, `abdm-debug.php`)

`lib/AbdmApi.php` (46KB) is a genuinely thorough v3 client: OAuth gateway token (session-cached), RSA-OAEP encryption via `/profile/public/certificate`, UUID v4 request IDs, ISO-8601 timestamps, `X-CM-ID`, robust error extraction (`extractError`, `txnOk`).

**ABHA step matrix**

| Step | Endpoint(s) | Status | Notes |
|---|---|---|---|
| OAuth gateway token | `gateway/v3/sessions` | 🟢 Works | Cached 25 min in `$_SESSION` |
| Public cert / RSA encrypt | `/profile/public/certificate` | 🟢 Works | OAEP-SHA1 |
| **Create ABHA — Aadhaar OTP (M1)** | `/enrollment/request/otp` → `/enrollment/enrol/byAadhaar` | 🟢 Works on sandbox | `ajax/abdm-api.php` (patient) + `doctor/api/abdm-api.php` (doctor) |
| Create ABHA — mobile verify during enrol | `/enrollment/request/otp` (mobile-verify) → `/enrollment/auth/byAbdm` | 🟡 Coded, lightly exercised | |
| Create ABHA — Driving Licence (M3) | `/enrollment/request/otp` (dl-flow) → `/enrollment/enrol/byDocument` | 🟡 Coded, unverified | |
| **Existing-ABHA login** (number / mobile / aadhaar) | `/profile/login/request/otp` → `/profile/login/verify` → `/profile/login/verify/user` | 🟡 Works on sandbox with quirks | Extensive comments about Transfer-token → X-token exchange; still carries `TEMP DIAGNOSTIC` `error_log` lines |
| ABHA address suggest/set | `/enrollment/enrol/suggestion`, `/enrollment/enrol/abha-address`, `/profile/account/abha-address` | 🟡 Coded | |
| Fetch ABHA profile | `/profile/account` (X-token) | 🟡 Coded; "X-token expired" bug referenced in comments | |
| ABHA card (PNG/PDF) | `/profile/account/getAbhaCard` | 🟡 Coded | |
| Search by ABHA number / address / mobile | `/search/*`, `/profile/account/abha/search` | 🟠 "not available to this credential" (per code comments) | |
| **Consent request / grant (HI consent)** | — | 🔴 Not implemented | No consent-manager calls anywhere |
| **Care-context linking (HIP)** | — | 🔴 Not implemented | `care_context_ref` is local-only |
| **Health data push / fetch (HIP/HIU)** | — | 🔴 Not implemented | |
| **HPR (doctor registry)** | — | 🔴 Not implemented | `hpr_verification_requests` = manual admin review |

**Where it's stuck:** the mandatory NHA compliance surface — HI **consent artefacts**, **care-context** registration, and **HPR** — has no API layer at all. What exists is the ABHA *account* lifecycle (create + authenticate), largely validated only against the sandbox.

**Issues**
- `config/abdm.php`: **hardcoded** `ABDM_CLIENT_ID` / `ABDM_CLIENT_SECRET` (duplicates `.env`), and this file is **committed to git**. `abdm.php` also hardcodes `ABDM_ENV='sandbox'` ignoring `.env`'s `ABDM_ENV`.
- `abdm-debug.php`: **no auth**, `Content-Type: text/plain`, prints `ABDM_CLIENT_ID`, gateway URLs, fetches a live OAuth token, RSA-encrypts a hardcoded test Aadhaar, and fires a battery of live enrollment/login OTP probe requests with `SSL_VERIFYPEER=false`. Must be deleted or hard-gated.
- `lib/AbdmApi.php` logs a lot to `error_log` including full raw ABDM responses (`substr(...,0,500)`) and JWT claim peeks — response bodies can contain PII (name, address, masked mobile). `SSL_VERIFYPEER` is driven by `ABDM_SSL_VERIFY` (true) in the class, but `abdm-debug.php` overrides to false.
- Three parallel ABDM dispatchers with **inconsistent hardening**:
  - `doctor/api/abdm-api.php` — JWT + CSRF + rate-limit + audit ✅
  - `ajax/abdm-api.php` — session + CSRF + rate-limit + audit ✅
  - `ajax/login-abdm.php` — **session only, no CSRF, no rate-limit, no audit** ❌ — and it can log in *doctors* (`setRoleSession` handles `doctor`).
- `ajax/login-abdm.php` `confirm_abha_login`: after ABDM returns a token, it re-looks-up the account by the **user-supplied** ABHA id (from session) and logs them in. It never cross-checks that the ABDM-authenticated identity matches that ABHA number. On a sandbox with fixed/opaque OTPs this is a weak point; do not ship to production auth without binding the returned profile's ABHA number to the account.
- `abha_verified` / `abha_linked` are set to `1` by `saveAbha()` and `AbhaPatientResolver` on *any* successful sandbox response.

---

### 3.7 Telemedicine / WebRTC (`telemedicine/`, `admin/telemedicine-settings.php`, `admin/live-consultations.php`)

**Complete & working**
- **HTTP-polling signalling** (not WebSocket): `telemedicine/api/poll.php` (receive + presence heartbeat + one-time `ready`), `send.php` (offer/answer/ICE/chat/toggle-media/end-call), `end-session.php`, `prescription.php`.
- `telemedicine/join.php` — ownership check: doctor JWT → patient session → signed **guest token** (JWT, 90d, scoped to `appointment_id`); issues a 6h signed **room ticket** → `room.php`.
- `room.php` (16KB) accepts only the signed ticket; sender identity always taken from ticket claims, never POST body.
- ICE config from `telemedicine_settings` (admin-managed TURN + extra STUN), STUN-only default.
- `telemedicine_ensure_room()` called at booking time so join links ship in confirmation emails.
- `telemedicine/selftest.php` (14KB) diagnostic page; `README.md`.

**Incomplete / stability concerns**
- `telemedicine/signaling-server.php` + `SignalingServer.php` (Ratchet WS) — **explicitly deprecated**, kept "for reference". `cboden/ratchet` is still in `composer.json` purely for this dead code.
- Polling every 3s (configurable, min 500ms) against MySQL — `telemedicine_signals` table growth is managed by best-effort `DELETE` inside `poll.php`/`send.php`; under a flaky connection this is the main load risk on shared hosting.
- `telemedicine/config.php` `TELEMED_SECRET` falls back to the literal `'change-me-telemed-secret'` if `JWT_SECRET` is empty — a silent-downgrade footgun (all tickets forgeable).
- Error handling in `api/poll.php` / `send.php`: DB errors during signal insert are mostly unchecked (`$stmt->execute()` return ignored); a failed insert just silently drops a signal → call stalls with no user-facing error. `end-call` is deliberately ordered to post `call-ended` first (good).
- No TURN by default → calls behind symmetric NAT / strict firewalls will fail until an admin configures TURN; there's no in-call diagnostic surfaced to the user (only `selftest.php`).
- `room.js` reconnect logic references avoiding "ICE-candidate storm" — implies past instability under reconnply; verify current behaviour on real networks.

---

### 3.8 `lib/`

| File | Purpose | State |
|---|---|---|
| `AbdmApi.php` | ABDM v3 client | 🟢 Broad; carries temp diagnostics & heavy logging |
| `AbhaPatientResolver.php` | ABDM profile → `users` row (find-or-create) | 🟢 Good; dedups 4 old copies |
| `AuditLogger.php` | `abdm_audit_logs` writer, prohibited-key stripping, archival | 🟢 Good; only 9 call sites — coverage is thin |
| `JWT.php` | HS256 issue/verify/decode | 🟢 Correct (`hash_equals`, exp check). No `nbf`/`iss`/`aud` checks; `alg` not pinned on verify (only HS256 is ever produced, but verify doesn't reject `none`) |
| `Security.php` | sessions, CSRF, rate-limit, bcrypt, headers | 🟢 Solid, underused (many pages roll their own) |
| `Validator.php` | ABDM input validation (Aadhaar/ABHA/OTP/mobile) | 🟢 |
| `WhatsAppOtp.php` | Meta WhatsApp Cloud API OTP + account-credentials templates | 🟢 |
| `DoctorAccess.php` | activation gate | 🟢 |
| `Settlement.php` | doctor payout math | 🟢 small |

---

### 3.9 `util/`

| File | Notes |
|---|---|
| `function.php` (42KB, ~42 functions) | Grab-bag: logos, contact info, email senders, `generate_doctor_slots()`, appointment confirmation, prescription helpers. Single biggest "junk drawer". |
| `auth-helper.php` | `findByIdentifier` / `findByAbha` / `findByAadhaar` / `setRoleSession` / OTP + lock helpers. `incrementAttempts`/`resetAttempts` use string-interpolated `WHERE id=$id` (params are typed `int` in the signature → not injectable, but style violates the project rule). |
| `mail_config.php` (40KB) | PHPMailer wrapper + every transactional email template inline. Huge. |
| `otp-service.php` | pre-account WhatsApp+email OTP with single-use tokens (`otp_consume_token`). |
| `appointment-handler.php`, `create-razorpay-order.php`, `medicine-booking-handler.php`, `contact-handler.php` | POST handlers. |
| `get-available-slots.php`, `get-doctor-schedule.php`, `get-doctors-by-department.php` | AJAX GET endpoints — check they don't leak more than needed (they return doctor availability; low risk). |
| `doctor-plans-render.php`, `prescription-render.php`, `otp-widget.php`, `sitemap.php` | view partials. |

---

### 3.10 `ajax/`

| File | Auth | CSRF | Rate-limit | Audit |
|---|---|---|---|---|
| `abdm-api.php` | session (patient/student/teacher) | ✅ | ✅ 30/5min | ✅ |
| `login-abdm.php` | none (pre-login) | ❌ | ❌ | ❌ |
| `login-send-otp.php` / `login-verify-otp.php` | none (pre-login) | ❌ | check | partial |
| `register-send-otp.php` / `register-verify-otp.php` | none (pre-register) | ❌ | check | — |

---

## 4. Duplicate & Dead Code

**Dead / deprecated**
- `admin/auth/auth.php` — 0 bytes, unreferenced.
- `admin/auth/login-sessions.php` — legacy session guard, superseded by JWT.
- `admin/header-old.php` — old header.
- `telemedicine/signaling-server.php` + `telemedicine/SignalingServer.php` — deprecated WS server; drags in `cboden/ratchet`.
- `abdm-debug.php` — "remove after testing" (still present, still dangerous).
- `student-login.php` / `teacher-login.php` — 339-byte redirect stubs.
- Large `admin/` e-commerce surface (`products.php`, `brand.php`, `orders.php`, `best-seller.php`, `add-special-offer.php`, `invoice-generate.php`, `our-best-brand.php`, `make-deal-of-the-day.php`, `show-products*.php`, `view_brands.php`, `multiple_img.php`, `home-items.php`, …) — inherited theme, not used by the health product.
- `doctor/patient-form.pdf` — a 68KB binary PDF committed next to `patient-form.php`.
- `lib/AbdmApi.php` — `TEMP DIAGNOSTIC` / "X-token expired bug" `error_log` blocks left in `confirmAuth`, `getProfile`, `verifyUserLogin`.

**Duplicated logic**
- **Password reset** implemented 3–4 times with divergent quality: `admin/auth/forgot-password.php`+`reset-password.php` (best), root `forgot-password.php` + `forgot-password/reset-password.php` (doctor), `school/request-password-link.php`+`school/set-password.php`.
- **ABDM dispatchers** ×3: `ajax/abdm-api.php`, `ajax/login-abdm.php`, `doctor/api/abdm-api.php` — same OAuth/RSA client underneath but 3 different auth/CSRF/rate-limit stances.
- **"Add ABHA" screens** ×3 in school (`student/abha.php`, `teacher/abha.php`, `health/abha.php`) and ×5 "add patient" screens in `doctor/`.
- **`CREATE TABLE IF NOT EXISTS doctor_patients`** copied inline into `lib/AbhaPatientResolver.php`, `doctor/api/create-patient-submit.php` (×2), plus `migration_doctor_abha.sql`.
- **`ok()` / `fail()` JSON helpers** re-declared in `ajax/abdm-api.php`, `ajax/login-abdm.php`, `doctor/api/abdm-api.php` (each file-local, so no fatal — but 3 copies).
- **DB bootstrap** duplicated: `config/connect.php` vs `admin/db-conn.php` (near-identical Dotenv + mysqli).
- **`function.php`** ×3 scopes: `util/function.php`, `admin/functions.php`, `user/function.php`.
- **JWT guard** pattern duplicated between `doctor/auth/guard.php` and `admin/auth/guard.php` (~90% identical: `_try_refresh_*`, `_*_redirect_login`, cookie params).
- `display_errors=1` / `error_reporting(E_ALL)` copy-pasted into ~40 admin files.
- UUID v4 generation hand-rolled in both `lib/AbdmApi.php::uuid()` and `abdm-debug.php` (twice).

---

## 5. Database Schema Issues

**Migrations present** (`database/`, 24 files) — all `CREATE TABLE IF NOT EXISTS` / idempotent `ALTER … IF NOT EXISTS`. No down-migrations, no schema version tracking, no ordering manifest.

### 5.1 Naming inconsistency
| Concept | `users` / `school_members` | `appointments` | `prescriptions` (inline) | ABHA request tables |
|---|---|---|---|---|
| 14-digit ABHA number | `abha_id` VARCHAR(20/50) | `abha_number` VARCHAR(17) | `abha_number` | `abha_id` VARCHAR(20) |
| ABHA handle | `abha_address` | — | `abha_address` | `abha_address` |

- CLAUDE.md's data model text ("`users.abha_number` 14-digit missing") does **not** match the code, which uses `users.abha_id`.
- `doctor/inc/sidebar.php` / `rx-sidebar.php` read `$patient['abha_number']` — only works because the page query aliases `abha_id AS abha_number`. Fragile.
- `identification_type` ENUM uses `'Aadhar'` (misspelled) in `users`; school members use `aadhar_number`.
- `hpr_verification_requests.status` is lowercase `enum('pending','approved','rejected')`; most other status enums are Title-case (`'Pending','Approved','Rejected'`). `doctors.status` is `'Active'/'Inactive'`.

### 5.2 Missing foreign keys
- **Core tables have no FKs at all:** `doctors`, `users`, `appointments`, `doctor_patients`, `doctor_sessions`, `jwt_refresh_tokens`, `abdm_audit_logs`, `user_abha_requests`, `abha_link_requests`, `prescriptions`, `parent_consent_forms`, `login_otps`, `login_rate_limits`.
- FKs only appear in newer migrations: `school_module.sql` (7), `migration_doctor_subscription_referral.sql` (6), `migration_doctor_student_care.sql` (3), `migration_doctor_bank_settlement.sql` (3), `migration_student_medical_certificates.sql` (3), `migration_admin_medical_records.sql` (2), `migration_telemedicine.sql` (1).
- Result: orphaned rows are inevitable (e.g. `doctor_patients` rows survive doctor deletion — `admin/doctors-list.php` does a bare `DELETE FROM doctors WHERE id=?` with no cascade and **on a GET request with no CSRF**).

### 5.3 ABHA tables — normalization
- ABHA identity is **denormalized onto 3 tables** (`users`, `school_members`, `appointments`) with copies of number/address/linked/verified flags, plus 2 request tables (`user_abha_requests`, `abha_link_requests`) that are structurally identical but split by entity. A single `abha_accounts` (entity_type, entity_id, abha_number, abha_address, verified_at, linked_at, source) + one `abha_link_requests` would remove the drift.
- No table for ABDM **consent artefacts** or **care contexts** (required for HI compliance).
- No storage of the ABDM **transaction/X-token audit** beyond `abdm_audit_logs` free-form `extra_data` JSON.
- `abdm_audit_logs` is a wide "all events" table (30+ nullable columns for 6 event shapes) — works, but hard to query/verify for compliance reporting.

### 5.4 Schema created at runtime (no migration)
`CREATE TABLE IF NOT EXISTS` executed from PHP in: `doctor/patient-form.php` (**`prescriptions`**), `doctor/my-patients.php`, `doctor/change-password.php`, `doctor/api/create-patient-submit.php`, `lib/AbhaPatientResolver.php` (`doctor_patients`), `school/parent-consent.php` (**`parent_consent_forms`**), `admin/permissions.php`, `admin/user-roles.php`, `admin/school-plans.php`, `admin/telemedicine-settings.php`. → Prod schema depends on code execution order; fresh DB + first request races.

### 5.5 Other
- `config/connect.php` uses `$conn->set_charset("utf8")` (3-byte) while every table is `utf8mb4` — emoji / some scripts will corrupt on write.
- `.env` `DB_NAME=rej_digital_health_db`; CLAUDE.md + most migration headers say `u950539402_reju_digi_beta`. Pick one.

---

## 6. Security Red Flags (prioritized)

> **P0 remediation status (2026-09-04):** items 1, 2, 4, 5, 6 addressed in code this session — see the ✅ rows. Residual manual work (git-history purge, secret rotation, `.env` `APP_ENV`) is called out per-row.

| # | Severity | Finding | Location | Fix / Status |
|---|---|---|---|---|
| 1 | 🔴 Critical | ~~ABDM client ID + secret **hardcoded and committed**~~ | `config/abdm.php` | ✅ **FIXED** — now reads `ABDM_CLIENT_ID`/`ABDM_CLIENT_SECRET`/`ABDM_ENV`/`ABDM_SSL_VERIFY` from `.env` only, with `safeLoad()` guard. ⚠️ Still-to-do: rotate the ABDM secret + purge `config/abdm.php` from git history (old commits still contain it). |
| 2 | 🔴 Critical | ~~Unauthenticated debug endpoint dumps config, mints live OAuth token, fires live ABDM OTP probes~~ | `abdm-debug.php` | ✅ **FIXED** — file deleted (`git rm`). ⚠️ Still-to-do: purge from git history. |
| 3 | 🔴 Critical | Live Razorpay keys (`rzp_live_…`), permanent WhatsApp token, SMTP password, JWT secret in `.env`; `.htaccess` PHP handler block is **commented out** (dev note) — if deployed to a host without that handler, `.php` may serve as source | `.env`, `.htaccess` | ⏳ Confirm host executes PHP; ensure `.env` 404s over HTTP; rotate all keys that ever touched git history |
| 4 | 🔴 Critical | ~~ABHA/Aadhaar login (all roles incl. doctor) — no CSRF, no rate-limit, no audit, doesn't bind returned ABDM identity~~ | `ajax/login-abdm.php` | ✅ **FIXED** — `Security::verifyCsrf` on every action (token wired through `login.php` `apiPost`), per-IP rate limits (global 20/5min, OTP-send 5/10min, verify 10/10min), all attempts → `AuditLogger`, and `confirm_*` now **fail-closed** unless the ABDM-authenticated ABHA number equals `account.abha_id`. `session_regenerate_id(true)` on success. ⚠️ Fail-closed check may block the legacy sandbox flow → migrate it to the `doctor/api/abdm-api.php` Transfer-token → `verifyUserLogin` pattern (P1). |
| 5 | 🟠 High | ~~Unguarded admin + doctor endpoints~~ | `admin/*.php`, 5 `doctor/*.php` | ✅ **FIXED** — new `admin/auth/bootstrap.php` (`db-conn` + `guard` + `admin_jwt_guard()` in one include) added to the 32 previously-open/`header`-only admin pages (incl. `invoice-generate.php`, `upload.php`, `product_delete.php`, all `update-*`/`delete_*`). 5 doctor pages on the broken legacy `$_SESSION['doctor_logged_in']` check (`patient-details`, `patient-documents`, `delete-document`, `get-appointment-details`, `select-opd-patient`) converted to `doctor_jwt_guard()`. 3 empty 0-byte admin stubs deleted. Partials (`header.php`, `footer.php`, …) intentionally left; `doctor/opd-slip.php` keeps its own inline multi-role JWT check. |
| 6 | 🟠 High | ~~`display_errors=1` globally → stack traces / SQL errors to browser; `$conn->error` echoed to client~~ | `config/connect.php`, `admin/db-conn.php`, 12 files | ✅ **FIXED** — both DB bootstraps now derive `APP_ENV`/`APP_DEBUG` (from `.env` `APP_ENV`, else localhost-SITE heuristic) and set `display_errors` off + `log_errors` on in production; DB-connect failure message genericised. Redundant `ini_set('display_errors', 1)` stripped from 10 admin/root files. 14 client-facing `$conn->error`/`$stmt->error`/`mysqli_error()` leak sites (7 `doctor/api/*`, 7 `admin/*`) → generic user message + `error_log()`. ⚠️ Set `APP_ENV=production` in the live `.env` (localhost auto-detects as dev). |
| 7 | 🟠 High | State-changing actions on **GET without CSRF**: `admin/doctors-list.php?delete_id=`, `admin/doctor-plans.php`, category/banner deletes | multiple `admin/*.php` | ⏳ POST + CSRF token (not yet done — endpoints are now at least auth-guarded via #5) |
| 8 | 🟠 High | `abdm_audit_logs` coverage is thin (9 call sites) — NHA requires logging all auth/validation/access/anomaly events; patient `ajax/abdm-api.php` logs, but many flows don't | codebase-wide | Route all ABDM + PHI access through `AuditLogger` |
| 9 | 🟡 Medium | `AbdmApi` logs raw ABDM response bodies (may contain PII) to `error_log`; `*.log` is gitignored but sits on disk unencrypted | `lib/AbdmApi.php` many `error_log(... substr($response,0,500))` | Log status + txnId only; redact |
| 10 | 🟡 Medium | `telemedicine/config.php` silently falls back to `'change-me-telemed-secret'` if `JWT_SECRET` unset → all room tickets & guest links forgeable | `telemedicine/config.php` L17 | Fail hard if secret missing |
| 11 | 🟡 Medium | `lib/JWT.php::verify()` doesn't pin `alg` (won't reject `{"alg":"none"}` if a token with 3 parts and empty sig is crafted — actually `hash_equals` would fail, so low risk, but pin it anyway); no `iss`/`aud` | `lib/JWT.php` | Pin `alg=HS256`, add `iss` |
| 12 | 🟡 Medium | Password min length **6** for patient signup (`process-signup.php`), **8+complexity** for admin/doctor. Inconsistent, weak for health data | `process-signup.php` | Raise to 8 + basic complexity everywhere |
| 13 | 🟡 Medium | User enumeration in doctor `forgot-password.php` ("not verified"/"not active" messages) and `process-login.php` ("No account found") | root auth | Generic messages |
| 14 | 🟡 Medium | `remember_token` written but never verified; plaintext `user_id` cookie set | `process-login.php` | Remove or implement properly (HttpOnly, hashed, selector+validator) |
| 15 | 🟡 Medium | `set_charset("utf8")` vs `utf8mb4` tables — not security per se, but data-integrity | `config/connect.php` | `utf8mb4` |
| 16 | 🟢 Low | `admin/functions.php` + legacy admin pages use `mysqli_real_escape_string` + `mt_rand()` IDs | `admin/` | Migrate to prepared statements; `random_int` |
| 17 | 🟢 Low | `doctor/api/create-patient-submit.php` has no CSRF (sibling `abdm-api.php` does); `array_map('trim', $raw)` fatals on nested arrays | `doctor/api/create-patient-submit.php` | Add CSRF; guard against non-scalar |
| 18 | 🟢 Low | Doctor `login-api.php` comment claims "Rate limit: max 10 attempts per IP per 15 min" but **no such code exists** (only per-account lock) | `doctor/auth/login-api.php` L240 | Implement the IP limit like `admin/auth/login.php` |

**Good practices already in place** (keep): prepared statements almost everywhere; bcrypt cost 12; JWT refresh rotation + server-side revocation; `HttpOnly/Secure/SameSite=Strict` cookies; per-account lockout; `admin/auth/login.php` IP rate-limit + `session_regenerate_id` + timing jitter; telemedicine signed tickets with server-derived identity; `AuditLogger` prohibited-key stripping; `.env` + `vendor/` + `uploads/` gitignored.

---

## 7. WebRTC / Telemedicine — Detail

**Works:** signed join tickets, ownership enforcement (doctor JWT / patient session / guest JWT), polling signalling that survives shared hosting, admin TURN/STUN config, in-call chat persisted (`telemedicine_chat_messages`), prescription-during-call (`telemedicine/api/prescription.php`), call history + live-consultations admin views, `selftest.php`.

**Fragile / missing error handling:**
- Signal `INSERT` return values unchecked in `poll.php` / `send.php` → dropped signals fail silently.
- No user-visible "connection failed / no TURN" state — only the separate `selftest.php`.
- `telemedicine_signals` cleanup is opportunistic; a stuck/looping client can still bloat it between cleanups.
- `TELEMED_SECRET` weak-default fallback (see §6 #10).
- Deprecated Ratchet WS path still shipped (dead dep).
- `prescription.php` re-declares the `prescriptions` schema assumption (inserts `care_context_ref`) — coupled to `doctor/patient-form.php` creating the table.
- 6-hour ticket + 90-day guest token: generous; acceptable, but guest token has no revocation.

---

## 8. Dependencies (`composer.json` / `composer.lock`)

| Package | Declared | Used? | Note |
|---|---|---|---|
| `phpmailer/phpmailer` ^6.10 | ✅ | ✅ (4 files) | fine |
| `setasign/fpdf` ^1.8 | ✅ | ✅ (opd-slip, patient forms) | trips `E_DEPRECATED` on PHP 8.2+ (`utf8_encode`) — handled by silencing |
| `vlucas/phpdotenv` ^5.6 | ✅ | ✅ (5 files) | fine |
| `phpoffice/phpspreadsheet` ^5.9 | ✅ | ✅ (school member import, 2 files) | heavy; only for import |
| `cboden/ratchet` ^0.4.4 | ✅ | ❌ **only the deprecated WS server** | drop when `signaling-server.php` is deleted |

- **PHP version mismatch:** CLAUDE.md says PHP 7.2; `phpspreadsheet ^5.9` requires PHP 8.1+, `phpmailer ^6.10` prefers 8.x, and the `.htaccess` mentions `ea-php81`. Code uses `match()` (8.0+), `str_starts_with` (8.0+), constructor promotion, nullsafe. **The codebase is PHP 8.1+, not 7.2** — CLAUDE.md is stale.
- No `composer.json` `"require-dev"`, no linter/static analysis, no test suite anywhere in the repo.
- No lockfile-integrity / SCA in CI (no CI at all).

---

## 9. Recommended Priority Order

### P0 — before any external exposure
1. ✅ ~~Remove hardcoded ABDM creds from `config/abdm.php`~~ · ✅ ~~delete `abdm-debug.php`~~ · ⏳ **rotate ABDM secret, Razorpay keys, WhatsApp token, JWT secret** · ⏳ **purge git history** of `config/abdm.php` (old versions) + `abdm-debug.php`.
2. ⏳ Confirm production host executes PHP and that `.env` returns 404 over HTTP; set `APP_ENV=production` in the live `.env`.
3. ✅ ~~Add CSRF + rate-limit + audit + identity binding to `ajax/login-abdm.php`~~ — done; verify the fail-closed identity check works against whatever ABDM credentials go live (may need the Transfer-token flow from `doctor/api/abdm-api.php`).
4. ✅ ~~Sweep every `admin/*.php` and `doctor/*.php` endpoint for a guard; shared bootstrap~~ — `admin/auth/bootstrap.php` created + applied; 5 doctor pages moved to `doctor_jwt_guard()`.
5. ✅ ~~Env-gate `display_errors`; stop echoing `$conn->error`~~ — done in `config/connect.php` + `admin/db-conn.php` + 12 leak-site files.

### P1 — compliance & data model
6. Build the real ABDM **consent** + **care-context (HIP link/notify)** layer in `lib/AbdmApi.php` and new tables (`abha_consents`, `abha_care_contexts`). This is the actual NHA blocker.
7. Decide HPR: either integrate the ABDM HPR API or clearly document it as manual-review-only.
8. Normalize ABHA storage → one `abha_accounts` table; unify `abha_id`/`abha_number` naming; fix CLAUDE.md to match.
9. Add FKs to core tables; move all runtime `CREATE TABLE` into migrations; add a migration runner + `schema_migrations` table.
10. Fix `set_charset` → `utf8mb4`.

### P2 — consolidation
11. Extract one shared `jwt-guard` lib used by doctor + admin (and patient + school when they migrate).
12. One password-reset implementation, parameterized by role.
13. One ABDM dispatcher / one `add-patient` screen / one `abha-link` screen.
14. Delete the e-commerce admin surface and the deprecated Ratchet WS server (+ drop the dep).
15. Strip `TEMP DIAGNOSTIC` logging from `lib/AbdmApi.php`; reduce PII in logs.
16. Introduce PHPStan + a smoke-test suite + CI.

### P3 — polish
17. Implement or remove "remember me".
18. Consistent password policy (8+complexity) everywhere.
19. Generic auth error messages (kill user enumeration).
20. Telemedicine: check signal-insert results, surface a "call failed / configure TURN" state, harden `TELEMED_SECRET`.

---

## 10. Quick File-Count Reference

| Dir | `.php` files | Auth model |
|---|---|---|
| `admin/` | 119 | JWT (`admin_jwt_guard`) — partial coverage |
| `doctor/` | 67 | JWT (`doctor_jwt_guard`) — consistent |
| `school/` | 36 | Session (`school/auth/auth.php`) |
| `user/` | 14 | Session (`$_SESSION['logged_in']`) |
| `util/` | 15 | n/a (libs/handlers) |
| `lib/` | 9 | n/a |
| `ajax/` | 6 | mixed / none |
| `telemedicine/` | 11 | signed JWT tickets |
| `config/` | 4 | n/a |
| `database/` | 24 `.sql` | n/a |
| root | 37 | session + redirects |

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)
