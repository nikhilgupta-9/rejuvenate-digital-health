# Rejuvenate Digital Health — CLAUDE.md

## Project Overview
PHP/MySQL telemedicine platform with multi-role user system (patients, doctors, school members, admins).
Database: **`rej_digital_health_db`** (name comes from `.env` `DB_NAME`; production/Hostinger uses `u950539402_reju_digi_beta`). **MariaDB 10.4**, **PHP 8.1+** (XAMPP dev ships 8.2, cPanel handler is `ea-php81`).
The project must comply with **ABDM / ABHA guidelines** as mandated by NHA India.

> **`PROJECT_STATUS.md`** (repo root) is the current single source of truth for what is built / half-built / broken. **`database/MIGRATIONS.md`** is the schema manifest.

---

## Tech Stack
- **Backend**: PHP 8.1+ (Composer used — `phpmailer`, `fpdf`, `phpdotenv`, `phpspreadsheet`, `ratchet`), raw PHP + MySQLi (no framework)
- **Frontend**: HTML, Bootstrap, custom CSS/JS
- **Database**: MariaDB 10.4 via MySQLi prepared statements; `utf8mb4` end to end
- **Auth**: **doctor + admin panels are on JWT** (`*/auth/guard.php`, refresh tokens in `jwt_refresh_tokens`); **patient + school panels still on PHP sessions** (`$_SESSION`)
- **Config**: `.env` (all secrets), `config/connect.php` (DB + `APP_ENV`/`APP_DEBUG`), `config/abdm.php` (ABDM — reads `.env`), `config/payment.php`, `config/whatsapp.php`
- **Migrations**: `database/*.sql` + `database/run-migrations.php` (tracked in `schema_migrations`); run before deploy — see `database/MIGRATIONS.md`

---

## Directory Structure
```
/
├── config/           connect.php (DB + APP_ENV), abdm.php, payment.php, whatsapp.php  (all read .env)
├── util/             auth-helper.php, function.php, appointment-handler.php, otp-service.php, mail_config.php
├── lib/              AbdmApi.php, HprApi.php, HipApi.php, Abha.php, AbhaPatientResolver.php, HprVerification.php, HipLinking.php, AuditLogger.php, JWT.php, Security.php, Validator.php, DoctorAccess.php, WhatsAppOtp.php
├── doctor/           Doctor panel (JWT — doctor/auth/guard.php)
├── user/             Patient panel (session — $_SESSION['logged_in'])
├── admin/            Admin panel (JWT — admin/auth/bootstrap.php; RBAC)
├── school/           School module (session — school/auth/auth.php)
├── database/         *.sql migrations + MIGRATIONS.md + run-migrations.php
└── uploads/          User/doctor media
```

---

## Current Data Model (Key Tables)

### `abha_accounts` — authoritative ABHA identity (one row per entity)
- `entity_type` ENUM('patient','school_member','doctor'), `entity_id`
- `abha_number` VARCHAR(17) — the 14-digit number, formatted `XX-XXXX-XXXX-XXXX`
- `abha_address`, `linked`, `verified`, `linked_at`, `verified_at`, `source`, `profile_data`
- Access via **`lib/Abha.php`** (`Abha::get / save / unlink / find / joinClause / selectAliases`).
- **Transition:** `users.abha_id` / `school_members.abha_id` / `doctors.abha_id` (+ `abha_address`, `abha_linked`, `abha_verified`) are **DEPRECATED**, still mirrored by `Abha::save()` and still read by ~20 not-yet-repointed display pages. Run `database/migrate-abha-data.php --commit` to populate, then finish repointing + drop the legacy columns. See `database/ABHA_MIGRATION_NOTES.md`.

### `doctors`
- `id`, `doctor_uid` (DOCxxxxxxx), `name`, `dob`, `gender`, `degrees`, `specialization`, `experience_years`, `rating`, `languages`
- `email`, `phone`, `password` (bcrypt), `status` ENUM('Active','Inactive'), `is_verified`, `is_approved`
- `login_attempts`, `is_locked`, `locked_until`, `grace_period_until` (activation gate)
- **HPR fields (exist):** `hpr_id`, `hfr_id`, `nmc_reg_number`, `council_name`, `year_of_registration`, `qualification_year`, `hpr_verified`, `hpr_verified_at`, `hpr_txn_id`, `hpr_requested_at`
- Auth: JWT (`doctor/auth/guard.php` sets `$_SESSION['doctor_id']` for legacy code); login → `doctor/auth/login-api.php`

### `users` (patients)
- `id`, `name`, `last_name`, `email`, `mobile`, `password`, `dob`, `gender`, `blood_group`, `address`, `city`, `state`, `zip_code`
- `identification_type` ENUM('Aadhar','Passport','Driving License','None'), `identification_number`, `emergency_contact`
- `allergies`, `existing_condition`, `current_medication`, `medical_history`
- `abha_id`, `abha_address`, `abha_linked`, `abha_verified` — **DEPRECATED**, use `abha_accounts`
- `login_attempts`, `is_locked`, `locked_until`
- Auth: session `$_SESSION['logged_in']`, `$_SESSION['user_id']`

### `appointments`
- `id`, `user_id`, `doctor_id`, `appointment_date`, `appointment_time`
- `purpose`, `notes`, `appointment_type`, `visit_person`, `status`, `approved_by_admin`, `admin_verified_at`
- `abha_number` — per-visit ABHA **snapshot** (kept by design), not identity
- `meeting_*` columns — telemedicine room record
- FK: `user_id → users` / `doctor_id → doctors` (ON DELETE RESTRICT)
- `care_context_ref` (`CC-<appt>-<date>`) is our visit identifier; on a **finalised** prescription for an ABHA-linked patient it is queued for ABDM HIP-initiated linking (`lib/HipApi.php` + `scripts/abdm-hip-worker.php` + `telemedicine/api/abdm-webhook.php`) — see the HIP section below

### `prescriptions` — saved consultation / e-prescription
- `id`, `appointment_id` (UNIQUE), `doctor_id`, `patient_id`, `care_context_ref`, `visit_date`
- `chief_complaints`, `vitals` (JSON), `examination`, `diagnosis`, `icd_codes`, `medications` (JSON), `lab_tests`, `radiology`, `report_findings`, `advice`, `follow_up_*`
- `abha_number` (snapshot), `hpr_id` (snapshot), `status` ENUM('draft','final')
- FKs to appointments / doctors / users — all **ON DELETE RESTRICT**

### `abdm_audit_logs`
- Full ABDM audit trail: `event_type`, `log_type`, `entity_id`, `entity_type`
- `auth_modality`, `txn_id`, `aua_code`, `user_consent`, `auth_status`
- `auth_method`, `accessor_id`, `patient_id`, `ip_address`, `user_agent`

### Other Key Tables
- `admin_user` — super_admin / admin / manager; RBAC in `admin_roles` / `admin_permissions` / `admin_role_permissions`
- `jwt_refresh_tokens` — shared refresh-token store (doctor + admin), SHA-256 hashed, rotated
- `abdm_audit_logs` (+ `abdm_audit_archive`) — ABDM audit trail; `entity_id` is **polymorphic** (no FK), and rows deliberately outlive their subjects (NHA retention)
- `doctor_sessions`, `doctor_documents`, `doctor_reviews`, `doctor_gallery`, `doctor_bank_accounts`, `doctor_subscriptions`
- `login_otps`, `registration_otps`, `login_rate_limits`
- `schools`, `school_users`, `school_members`, `member_health_profiles`, `school_member_prescriptions/certificates/documents`
- `parent_consent_forms` — school parent-consent submissions (+ `school_health_plans`)
- `user_abha_requests`, `abha_link_requests` — manual ABHA-link approval workflow
- `hpr_verification_requests` — manual HPR review queue (fallback path)
- `hpr_verification_txns` — Aadhaar-flow HPR-ID verification transactions (`lib/HprApi.php`)
- `telemedicine_rooms / signals / chat_messages / settings` — WebRTC (HTTP-polling)
- `schema_migrations` — migration tracking (see `database/run-migrations.php`)

### Foreign keys
`database/migration_core_foreign_keys.sql` added 30 FKs (see `database/MIGRATIONS.md` #34). Policy: **RESTRICT** on clinical/health/identity refs (prescriptions, consent forms, `school_members.school_id`, `doctor_patients.doctor_id`), **CASCADE** on operational/session/workflow, **SET NULL** on optional attribution. Hard-delete of a `doctors` / `schools` row is now blocked → `admin/doctors-list.php` and `admin/delete-school.php` do **soft-delete** (`status='Inactive'`).

---

## ABHA / ABDM Compliance Requirements

### What ABHA Is
ABHA = Ayushman Bharat Health Account (14-digit Health ID).  
ABDM = Ayushman Bharat Digital Mission (NHA India).  
Every patient and doctor must have an ABHA for digital health record exchange.

### Doctor Compliance (HPR) — **Aadhaar-flow verification implemented**
Fields on `doctors`: `hpr_id`, `hfr_id`, `nmc_reg_number`, `council_name`, `year_of_registration`, `qualification_year`, `hpr_verified`, `hpr_verified_at`, `hpr_verification_source` (`aadhaar_hpr_api` | `admin_review`), `hpr_txn_id`, `hpr_requested_at`.

**Primary path — ABDM HPR API** (`lib/HprApi.php` + `lib/HprVerification.php` + `doctor/api/hpr-verify.php`, wired into `doctor/my-contact.php`):

1. doctor saves their claimed `hpr_id`, clicks **Verify HPR ID with Aadhaar**
2. `generateAadhaarLink()` → 5-min NHA link opens in a new tab; txn saved in `hpr_verification_txns` (`pending`)
3. browser polls `checkAadhaarAuthStatus()` (~3.5 s) → `authenticated` once the doctor completes Aadhaar OTP on the NHA page
4. `verifyOTP()` (demographics **transient** — never stored/logged) → `checkHpIdAccountExist()`
5. **fail-closed match**: the `hprIdNumber` linked to that Aadhaar must `hash_equals` the doctor's saved `hpr_id` (separator-insensitive). `"new": true` from HPR → `hpr_account_not_found` (doctor must register on the HPR portal first). Only on an exact match → `hpr_verified = 1`, `hpr_verification_source = 'aadhaar_hpr_api'`.
   Every attempt (success + fail) logged via `AuditLogger::logAbhaAuth(…, 'HPR_AADHAAR', …)`.

**Fallback path — manual review:** doctor submits HPR/NMC details → `hpr_verification_requests` → an admin approves in `admin/hpr-verification.php`.

Needs `.env` `ABDM_HPR_CLIENT_ID` / `ABDM_HPR_CLIENT_SECRET` (separate HPR app registration; blank ⇒ the Aadhaar button is hidden, only the manual path shows).

### Patient Compliance (ABHA)
- `abha_number` — 14-digit, format `XX-XXXX-XXXX-XXXX` — stored in **`abha_accounts.abha_number`**
- `abha_address` — `@abdm` / `@sbx` handle
- `verified` must be 1 before accessing health records
- **Built:** ABDM **HIP-initiated care-context linking** (M3, async — see the HIP section).
- **Not built:** ABDM HI **consent artefacts** and the **HIU / data-request-fulfilment** side (responding to a CM's data request). `abdm_audit_logs` coverage is still thin (~9 + the HPR/HIP call sites).

### Auth — status
- **Doctor:** JWT done. `doctor/auth/login-api.php` → access (15 min) + refresh (7 d, hashed, rotated) in `HttpOnly; Secure; SameSite=Strict` cookies (`rdh_doctor_token` / `rdh_doctor_refresh`). Guard: `doctor/auth/guard.php::doctor_jwt_guard()`. Activation gate in `lib/DoctorAccess.php`.
- **Admin:** JWT done. `admin/auth/login.php` (CSRF + IP rate-limit + `session_regenerate_id`). Guard: `admin/auth/guard.php`, applied via `admin/auth/bootstrap.php`.
- **Patient / School:** still `$_SESSION` (`process-login.php` → `util/auth-helper.php::setRoleSession()`). ABHA/Aadhaar login via `ajax/login-abdm.php` (CSRF + rate-limit + fail-closed identity check).
- JWT secret: **`.env` `JWT_SECRET`** (exposed as `JWT_SECRET` constant by `config/connect.php`).

### ABDM ABHA API (`lib/AbdmApi.php`)
- OAuth gateway: `https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions`; ABHA v3 sandbox `https://abhasbx.abdm.gov.in/abha/api/v3`
- Credentials from **`.env`** (`ABDM_CLIENT_ID` / `ABDM_CLIENT_SECRET` / `ABDM_ENV`) via `config/abdm.php`
- **Working (sandbox):** OAuth token, RSA cert, ABHA create (Aadhaar OTP), existing-ABHA login (number/mobile/aadhaar)
- **Coded, lightly tested:** DL enrolment, mobile-verify, ABHA address, profile fetch, ABHA card
- Dispatchers: `ajax/abdm-api.php` (patient), `doctor/api/abdm-api.php` (doctor), `ajax/login-abdm.php` (login)

### ABDM HPR API (`lib/HprApi.php`)
- Gateway session `https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions`; HPR sandbox `https://apihspsbx.abdm.gov.in/v4/int`
- Credentials from **`.env`** (`ABDM_HPR_CLIENT_ID` / `ABDM_HPR_CLIENT_SECRET` — separate app) via `config/abdm.php`; `ABDM_HPR_CONFIGURED` gates the feature
- Same conventions as `AbdmApi` (session token cache in `$_SESSION['hpr_*']`, UUID `REQUEST-ID`, ISO-8601 `TIMESTAMP`, `X-CM-ID`), but returns `['success','data','error','code']` and logs **PII-safe** (txnId + status only — never demographics)
- Methods: `generateSession`, `getPublicCertificate` (future-proofing, unused by the flow), `generateAadhaarLink`, `checkAadhaarAuthStatus` (raw-boolean response), `verifyOTP` (transient demographics), `checkHpIdAccountExist` (primary — `"new": true` ⇒ no account)
- Scope: verify an **existing** HPR ID only. Dispatcher: `doctor/api/hpr-verify.php` (`start` / `poll` / `finish`). DB: `lib/HprVerification.php` + `hpr_verification_txns`.

### ABDM HIP-Initiated Linking (`lib/HipApi.php`) — M3, HIECM V3, async

Links a patient's finalised prescription (care context) to their ABHA so a CM/HIU can later request it. The V3 flow is asynchronous — each call returns a `requestId`, ABDM replies to our webhook.

- Base `https://dev.abdm.gov.in/api/hiecm`; reuses the ABHA gateway session token (`ABDM_CLIENT_ID/SECRET`). `X-HIP-ID` from `.env` `ABDM_HIP_ID`; `ABDM_HIP_CONFIGURED` gates the feature (blank ⇒ care contexts stay local).
- Same conventions as `AbdmApi`; returns `['success','data','error','code']`; **PII-safe** logging (requestId + HTTP code only).
- Methods: `generateLinkToken` → `POST /v3/token/generate-token`; `linkCareContext` → `POST /hip/v3/link/carecontext` (`X-LINK-TOKEN`); `notifyCareContext` → `POST /hip/v3/link/context/notify`. Each accepts an optional caller-supplied `requestId`.
- **Flow:** `doctor/patient-form.php` (on `status='final'` + ABHA patient) drops a `pending` row in `abdm_care_context_links` → **`scripts/abdm-hip-worker.php`** (cron ~5 min) ensures a link token (`abdm_link_tokens`), then calls `linkCareContext` + `notifyCareContext` → **`telemedicine/api/abdm-webhook.php`** (public, no login) records the async result.
- **Webhook security:** the real gate is the `requestId` — a server-generated UUID v4 never exposed to any client, matched against a `pending` row we created. Plus: raw body saved *before* parsing (`abdm_webhook_log`), 256 KB cap, per-IP rate limit, optional `?k=<ABDM_WEBHOOK_SECRET>` + IP allowlist, idempotency check, always 200.
- DB: `lib/HipLinking.php` + `abdm_link_tokens` / `abdm_care_context_links` / `abdm_webhook_log` (`migration_abdm_hip_linking.sql`).

---

## Development Priority

**See `PROJECT_STATUS.md` §9 for the live prioritised plan.** Summary of where things stand:

- **Phase 1 — Doctor Panel:** JWT auth ✅, HPR verification ✅ (ABDM HPR Aadhaar flow + manual fallback), dashboard/patients ✅, care-context linking ✅ (HIP M3, async).
- **Phase 2 — Patient Panel:** ABHA create/link (sandbox) ✅, session auth (JWT not migrated). Consent-management UI ❌.
- **Phase 3 — Admin Panel:** ABHA-request approval ✅, HPR review queue ✅, audit-log viewer ❌.
- **Cross-cutting done (2026-09):** P0 security fixes, `abha_accounts` normalisation, migrations + runner, 30 FKs, `utf8mb4`, HPR verification, HIP-initiated care-context linking.
- **Biggest gap for NHA compliance:** HI **consent artefacts** and the **HIU / data-request side** (fulfilling a CM's data request) have no API layer yet.

---

## Auth entry points (actual)
- `process-login.php` — unified login for **patient / school** roles → `util/auth-helper.php::setRoleSession()` (`findByIdentifier()`, `findByAbha()` → now via `lib/Abha.php`)
- `doctor/auth/login-api.php` — doctor JWT login (POST JSON); `doctor/auth/guard.php` guards every doctor page
- `admin/auth/login.php` — admin JWT login; `admin/auth/bootstrap.php` (`= db-conn + guard + admin_jwt_guard()`) at the top of every admin page
- `school/auth/auth.php` — school session guard (`$_SESSION['school_logged_in']`)
- `ajax/login-abdm.php` — ABHA / Aadhaar OTP login (patient + doctor); CSRF + rate-limit + fail-closed identity check
- Doctor cookies: `rdh_doctor_token` / `rdh_doctor_refresh`. Admin: `rdh_admin_token` / `rdh_admin_refresh`.

---

## Database migrations
All schema lives in `database/*.sql`. **Run before deploy:**
```
php database/run-migrations.php            # applies pending, tracked in schema_migrations
php database/run-migrations.php --dry-run
```
Canonical order + rationale: **`database/MIGRATIONS.md`** (34 files). ABHA data copy (one-off, manual): `php database/migrate-abha-data.php --commit` after review.
Do **not** `CREATE TABLE` / `ALTER TABLE` at runtime in PHP — add a migration file instead.

---

## Coding Rules
- Always use MySQLi **prepared statements** — no raw string interpolation in SQL
- Passwords: `password_hash(..., PASSWORD_BCRYPT, ['cost'=>12])`
- All ABDM + PHI access must be logged via `lib/AuditLogger.php`
- ABHA identity: read/write through **`lib/Abha.php`**, never the deprecated `*.abha_id` columns
- Secrets: **`.env` only** (never hardcode; `config/*.php` read from `$_ENV`)
- `JWT_SECRET` is an `.env` var, surfaced as a constant by `config/connect.php`
- Never store raw Aadhaar — last 4 digits + consent log only
- JWT cookies: `HttpOnly` + `Secure` + `SameSite=Strict`
- Errors: `APP_DEBUG` gates on-screen display; never echo `$conn->error` to the client
- New schema → a `database/*.sql` migration (idempotent: `IF NOT EXISTS`), added to `MIGRATIONS.md` + `run-migrations.php` `$ORDER`

---

## ABHA Number Format
- 14 digits: `XX-XXXX-XXXX-XXXX` — validate `/^\d{2}-\d{4}-\d{4}-\d{4}$/`; format with `Abha::formatNumber()` / `AbdmApi::formatAbhaNumber()`
- ABHA address: `[a-zA-Z0-9._]{3,}@<suffix>` where suffix is `sbx` on sandbox, `abdm` on production
- Naming: the old schema called the 14-digit number `abha_id` on `users`/`school_members`/`doctors` and `abha_number` on `appointments`/`prescriptions`. Canonical is now **`abha_accounts.abha_number`**; the snapshot columns keep the name `abha_number`.

---

## Key Contacts / Identifiers
- App doctor (test): `iamnikhilgupta9@gmail.com` / `DOC20251215023925774`
- ABDM sandbox client id: `SBXID_038789` (`.env` `ABDM_CLIENT_ID`; also used as AUA code)
- HPR sandbox doctor handle: `sanjay8273@hpr.abdm`
- MySQL socket (XAMPP dev): `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock` — CLI needs `/Applications/XAMPP/xamppfiles/bin/php`
