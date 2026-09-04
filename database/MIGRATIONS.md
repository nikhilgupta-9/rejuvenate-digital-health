# Database migrations — manifest & run order

Every file in `database/` is idempotent (`CREATE TABLE IF NOT EXISTS`,
`ALTER … ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE`), so re-running the whole
set is safe. Run them **in the order below** on a fresh database — a few pairs
have a real dependency (noted inline).

There is a helper: `php database/run-migrations.php` applies every `.sql` in
this order and records what ran in a `schema_migrations` table (see bottom).

```
mysql -u root <db> < database/<file>.sql        # one file
php  database/run-migrations.php                 # all, tracked
php  database/run-migrations.php --dry-run       # list what would run
```

> CLI note (XAMPP): the system `php`/`mysql` may not find XAMPP's socket. Use
> `/Applications/XAMPP/xamppfiles/bin/php` and `/Applications/XAMPP/xamppfiles/bin/mysql`,
> or pass `--socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`.

---

## 0. Prerequisite — base schema (NOT in this folder)

The core tables predate the migration folder and come from the initial DB
dump / phpMyAdmin: `doctors`, `users`, `admin_user`, `appointments`,
`doctor_schedules`, `doctor_sessions`, `doctor_documents`, `doctor_reviews`,
`patient_documents`, `member_health_profiles`, `school_member_documents`,
`school_member_prescriptions`, `login_otps`, `sub_categories`, `products`,
`orders`, … A migration below will fail if its target/base table is missing.

## 1. Foundational modules

| # | File | Creates |
|---|------|---------|
| 1 | `school_module.sql` | `schools`, `school_users`, `school_members` (+ FKs) |
| 2 | `abdm_security.sql` | `abdm_audit_logs`, `abdm_audit_archive` |

## 2. Doctor module

| # | File | Notes |
|---|------|-------|
| 3 | `migration_doctor_abha.sql` | HPR cols on `doctors`; `jwt_refresh_tokens`; **`doctor_patients`** |
| 4 | `migration_doctor_profile_hpr.sql` | `hfr_id`, notify prefs; `hpr_verification_requests` |
| 5 | `migration_doctor_activation_gate.sql` | `grace_period_until` etc. on `doctors` |
| 6 | `migration_doctor_subscription_referral.sql` | `doctor_plans`, `doctor_subscriptions`, `doctor_referral_earnings` (FKs) |
| 7 | `migration_doctor_plans_marketing.sql` | marketing cols on `doctor_plans` — **after #6** |
| 8 | `migration_doctor_bank_settlement.sql` | `doctor_bank_accounts`, `appointment_settlements` (FKs) |
| 9 | `migration_doctor_password_security.sql` | `doctor_password_history`, `doctor_password_logs` (FK → `doctors`) — *was runtime in `doctor/change-password.php`* |
| 10 | `migration_doctor_health_profile_edit.sql` | cols on `member_health_profiles` |
| 11 | `migration_doctor_student_care.sql` | school-care link tables (FKs) |

## 3. Appointments

| # | File |
|---|------|
| 12 | `migration_appointment_booking.sql` |
| 13 | `migration_appointment_payment.sql` |
| 14 | `migration_appointment_rejection_reason.sql` |

## 4. Consultation / prescriptions

| # | File | Notes |
|---|------|-------|
| 15 | `migration_prescriptions.sql` | **base `prescriptions` table** — *was runtime in `doctor/patient-form.php`* |
| 16 | `migration_consultation_records.sql` | `report_findings` + `patient_documents.appointment_id` — **after #15** |

## 5. Patient

| # | File |
|---|------|
| 17 | `migration_patient_medical_info.sql` | allergies / conditions / medication cols on `users` |

## 6. ABHA

| # | File | Notes |
|---|------|-------|
| 18 | `migration_abha_module.sql` | `user_abha_requests`, `abha_link_requests` |
| 19 | `migration_abha_accounts.sql` | **`abha_accounts`** — normalised ABHA identity |
| 20 | `migration_abha_deprecate_legacy_columns.sql` | COMMENT-only; run **after** the data migration (below) |

## 7. Admin

| # | File | Notes |
|---|------|-------|
| 21 | `migration_admin_jwt_security.sql` | `login_rate_limits`, admin JWT cols |
| 22 | `migration_admin_rbac.sql` | `admin_roles`, `admin_permissions`, `admin_role_permissions` — *was runtime in `admin/user-roles.php` / `permissions.php`*; seed data still bootstraps from `admin/user-roles.php` on first load |
| 23 | `migration_admin_medical_records.sql` | `school_member_documents` (FKs), `patient_documents` upload cols |

## 8. School health

| # | File | Notes |
|---|------|-------|
| 24 | `migration_school_health_plans.sql` | **`school_health_plans`** — *was runtime in `admin/school-plans.php` + `school/parent-consent.php`* |
| 25 | `migration_parent_consent_forms.sql` | **`parent_consent_forms`** (+ all additive cols) — *was ~40 runtime `ALTER`s in `school/parent-consent.php` + `admin/parent-consents.php`* |
| 26 | `migration_student_medical_certificates.sql` | `school_member_certificates`, `school_member_prescriptions` (FKs) |
| 27 | `migration_share_token.sql` | `school_members.share_token` |

## 9. Telemedicine

| # | File | Notes |
|---|------|-------|
| 28 | `migration_telemedicine.sql` | `telemedicine_chat_messages` etc. |
| 29 | `migration_telemedicine_polling.sql` | `telemedicine_rooms`, `telemedicine_signals` |
| 30 | `migration_telemedicine_settings.sql` | **`telemedicine_settings`** — *was runtime in `admin/telemedicine-settings.php`* |

## 10. Misc

| # | File |
|---|------|
| 31 | `migration_whatsapp_otp.sql` | `registration_otps`, WhatsApp OTP cols |
| 32 | `migration_department_description.sql` | `sub_categories.description` |

## 11. Runtime backfills

| # | File | Notes |
|---|------|-------|
| 33 | `migration_runtime_column_backfills.sql` | `doctor_bank_accounts.branch_name/account_type` (needs #8), `school_member_prescriptions.vitals` (needs #26) |

## 12. Referential integrity — run LAST

| # | File | Notes |
|---|------|-------|
| 34 | `migration_core_foreign_keys.sql` | 30 foreign keys + 6 `INT UNSIGNED → INT(11)` type-alignment MODIFYs. Needs every table above to exist. Verified against live data (zero orphans) before writing. **RESTRICT** on clinical/health/identity refs, **CASCADE** on operational/session/workflow, **SET NULL** on optional attribution. The polymorphic `entity_id` columns (`jwt_refresh_tokens`, `abha_accounts`, `abdm_audit_logs`) and the audit-log subject refs get **no FK** by design. Ships with `admin/doctors-list.php` + `admin/delete-school.php` converted to soft-delete (`status='Inactive'`). |

---

## Data migrations (PHP, run manually after review)

| When | Command |
|------|---------|
| After #19 **and** the ABHA code (`lib/Abha.php`) is deployed | `php database/migrate-abha-data.php` → review → `--commit` — copies `users`/`school_members`/`doctors` ABHA columns into `abha_accounts`. Then run #20. |

See `database/ABHA_MIGRATION_NOTES.md`.

---

## `schema_migrations` tracking table

`run-migrations.php` creates and maintains:

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
  filename    VARCHAR(191) NOT NULL PRIMARY KEY,
  checksum    CHAR(64)     NOT NULL,
  applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

A file already in `schema_migrations` with a matching checksum is skipped.
If its checksum changed, the runner warns (edit migrations by adding a new
file, not by changing an applied one).
