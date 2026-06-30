# Rejuvenate Digital Health — CLAUDE.md

## Project Overview
PHP/MySQL telemedicine platform with multi-role user system (patients, doctors, school members, admins).  
Database: `u950539402_reju_digi_beta` (MariaDB 11.8, PHP 7.2).  
The project must comply with **ABDM / ABHA guidelines** as mandated by NHA India.

---

## Tech Stack
- **Backend**: PHP 7.2 (no Composer, no frameworks — raw PHP + MySQLi)
- **Frontend**: HTML, Bootstrap, custom CSS/JS
- **Database**: MariaDB via MySQLi prepared statements
- **Auth currently**: PHP sessions (`$_SESSION`) — **migrating to JWT**
- **Config**: `config/connect.php` (DB), `config/abdm.php` (ABDM credentials)

---

## Directory Structure
```
/
├── config/           connect.php (DB), abdm.php (ABDM)
├── util/             auth-helper.php, function.php, appointment-handler.php
├── lib/              AbdmApi.php, AuditLogger.php, Security.php, Validator.php
├── doctor/           Doctor panel pages (session-based, migrating to JWT)
├── user/             Patient panel pages
├── admin/            Admin panel (role-based permissions)
├── school/           School module (school_admin / teacher / student)
└── uploads/          User/doctor media
```

---

## Current Data Model (Key Tables)

### `doctors`
- `id`, `doctor_uid` (DOCxxxxxxx), `name`, `dob`, `gender`
- `degrees`, `specialization`, `experience_years`, `rating`, `languages`
- `email`, `phone`, `password` (bcrypt)
- `status` ENUM('Active','Inactive'), `is_verified`, `is_approved`
- `login_attempts`, `is_locked`, `locked_until`
- Auth: session `$_SESSION['doctor_logged_in']`, `$_SESSION['doctor_id']`
- **Missing ABHA fields**: HPR ID, HFR ID, NMC registration number

### `users` (patients)
- `id`, `name`, `last_name`, `email`, `mobile`, `password`
- `dob`, `gender`, `blood_group`, `address`, `city`, `state`, `zip_code`
- `identification_type` ENUM('Aadhar','Passport','Driving License','None')
- `identification_number`, `emergency_contact`
- `abha_id`, `abha_address`, `abha_linked`, `abha_verified`
- `login_attempts`, `is_locked`, `locked_until`
- Auth: session `$_SESSION['logged_in']`, `$_SESSION['user_id']`
- **Missing ABHA fields**: abha_number (14-digit), proper consent timestamp

### `appointments`
- `id`, `user_id`, `doctor_id`, `appointment_date`, `appointment_time`
- `purpose`, `notes`, `appointment_type`, `visit_person`, `status`
- `approved_by_admin`, `admin_verified_at`
- **Missing ABHA fields**: care_context_reference, health_info_type

### `abdm_audit_logs`
- Full ABDM audit trail: `event_type`, `log_type`, `entity_id`, `entity_type`
- `auth_modality`, `txn_id`, `aua_code`, `user_consent`, `auth_status`
- `auth_method`, `accessor_id`, `patient_id`, `ip_address`, `user_agent`

### Other Key Tables
- `admin_user` — Super admin / admin / manager with role-based permissions
- `doctor_sessions` — Doctor session tracking
- `login_otps` — OTP for login (email/mobile)
- `doctor_documents`, `doctor_reviews`, `doctor_gallery`
- `school_members`, `school_users`, `schools` — School health module
- `user_abha_requests`, `abha_link_requests` — ABHA linking workflows

---

## ABHA / ABDM Compliance Requirements

### What ABHA Is
ABHA = Ayushman Bharat Health Account (14-digit Health ID).  
ABDM = Ayushman Bharat Digital Mission (NHA India).  
Every patient and doctor must have an ABHA for digital health record exchange.

### Doctor Compliance (HPR)
Doctors must be registered on **HPR (Health Professional Registry)**:
- `hpr_id` — HPR registration number (e.g., `27-1234-5678-9012`)
- `hfr_id` — Health Facility Registry ID (if clinic-based)
- `nmc_reg_number` — NMC (National Medical Commission) registration
- `council_name` — State Medical Council name
- `year_of_registration` — Year of NMC registration
- `qualification_year` — Year of degree completion
- Doctors must verify via ABDM Aadhaar OTP flow before HPR registration

### Patient Compliance (ABHA)
- `abha_number` — 14-digit ABHA number (format: `XX-XXXX-XXXX-XXXX`)
- `abha_address` — @abdm handle (e.g., `name@abdm`)
- `abha_verified` — must be 1 before accessing health records
- Patient consent required for every health data access (logged in `abdm_audit_logs`)
- Data linked via **care contexts** (each visit = one care context)

### Auth — JWT Migration Plan
Current: PHP sessions. Target: **JWT (JSON Web Tokens)**.
- JWT issued on login, stored in `HttpOnly` cookie + `localStorage`
- Payload: `{ user_id, role, abha_linked, exp, iat, jti }`
- Refresh token stored in DB table `jwt_refresh_tokens`
- Doctor JWT must include `hpr_verified` claim
- Token expiry: Access=15min, Refresh=7days
- All ABDM API calls must include doctor's HPR token

### ABDM API Integration (lib/AbdmApi.php)
- Sandbox base: `https://sandbox.abdm.gov.in/`
- Production: `https://live.abdm.gov.in/`
- Auth: OAuth2 client_credentials (`config/abdm.php`)
- Key flows: Aadhaar OTP → ABHA creation, Mobile OTP → ABHA linking
- All calls logged to `abdm_audit_logs`

---

## Development Priority (Step-by-Step Plan)

### Phase 1 — Doctor Panel (CURRENT FOCUS)
**Goal**: Make doctor login/panel fully ABHA-compliant with JWT auth.

**Step 1.1 — Doctor Login with JWT** (`doctor-login.php`)
- Replace session-based auth with JWT
- Add ABHA/HPR login option alongside email/password
- Issue JWT on success; store in HttpOnly cookie
- Log login event to `abdm_audit_logs`

**Step 1.2 — Doctor Profile — HPR Fields**
- Add `hpr_id`, `nmc_reg_number`, `council_name`, `year_of_registration` to `doctors` table
- Doctor profile page must show HPR verification status
- HPR verification via ABDM Aadhaar OTP flow

**Step 1.3 — Doctor Dashboard**
- Show HPR verification badge
- Show linked patients with ABHA status
- Appointments must reference care contexts

**Step 1.4 — Patient Records (ABDM Care Context)**
- Each appointment creates a care context entry
- Doctor can only access health records with patient's active consent
- Consent status shown on patient card

### Phase 2 — Patient Panel
- Patient ABHA creation/linking flow
- Health records linked to ABHA care contexts
- Consent management UI

### Phase 3 — Admin Panel
- ABHA linking request approval
- HPR verification dashboard
- Audit log viewer

---

## Auth Flow (Target — JWT)

```
POST /api/auth/doctor-login
  → validate credentials (email+password OR HPR ID+OTP)
  → check is_verified, is_approved, not locked
  → generate JWT { doctor_id, role:'doctor', hpr_verified, exp }
  → generate refresh_token → store in jwt_refresh_tokens
  → set HttpOnly cookie 'rdh_token'
  → log to abdm_audit_logs (event_type: 'login_success')
  → return { token, doctor: {id, name, hpr_verified} }
```

```
Doctor panel pages:
  → read JWT from cookie
  → verify signature + expiry
  → if expired → try refresh token
  → if invalid → redirect to doctor-login.php
```

---

## Current Auth Entry Points
- `process-login.php` — unified login handler (all roles)
- `util/auth-helper.php` — `findByIdentifier()`, `setRoleSession()`, OTP utils
- `doctor/doctor-dashboard.php` — checks `$_SESSION['doctor_logged_in']`
- `admin/auth/auth.php` — admin session guard
- `school/auth/auth.php` — school session guard

---

## Database Migrations Needed (Doctor Phase)
```sql
-- Add HPR fields to doctors table
ALTER TABLE doctors
  ADD COLUMN hpr_id VARCHAR(20) DEFAULT NULL AFTER doctor_uid,
  ADD COLUMN nmc_reg_number VARCHAR(50) DEFAULT NULL,
  ADD COLUMN council_name VARCHAR(100) DEFAULT NULL,
  ADD COLUMN year_of_registration YEAR DEFAULT NULL,
  ADD COLUMN qualification_year YEAR DEFAULT NULL,
  ADD COLUMN hpr_verified TINYINT(1) DEFAULT 0,
  ADD COLUMN hpr_verified_at DATETIME DEFAULT NULL,
  ADD COLUMN hpr_txn_id VARCHAR(100) DEFAULT NULL;

-- JWT refresh tokens table
CREATE TABLE jwt_refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(20) NOT NULL,  -- 'doctor', 'patient', 'admin'
  entity_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  revoked TINYINT(1) DEFAULT 0,
  revoked_at DATETIME DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Coding Rules
- Always use MySQLi **prepared statements** — no raw string interpolation in SQL
- Passwords: `password_hash(..., PASSWORD_BCRYPT, ['cost'=>12])`
- All ABDM interactions must be logged via `lib/AuditLogger.php`
- JWT secret stored in `config/connect.php` as `JWT_SECRET` constant
- Never store raw Aadhaar — store only last 4 digits + consent log
- HttpOnly + Secure + SameSite=Strict cookies for JWT
- Session variables kept for admin panel (not migrating admin to JWT yet)

---

## ABHA Number Format
- 14 digits: `XX-XXXX-XXXX-XXXX`
- Validate with: `/^\d{2}-\d{4}-\d{4}-\d{4}$/`
- ABHA address: `[a-zA-Z0-9._]{3,}@abdm`

---

## Key Contacts / Identifiers in DB
- App doctor (test): `iamnikhilgupta9@gmail.com` / `DOC20251215023925774`
- ABDM Sandbox AUA code: `SBXID_038789` (from audit logs)
- HPR sandbox doctor: `sanjay8273@hpr.abdm` (shown in screenshot)
