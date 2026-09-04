# ABHA storage normalisation — reference map

**Status:**
- **Task 1 done** — `abha_accounts` table (`migration_abha_accounts.sql`) + data-migration script (`migrate-abha-data.php`, dry-run verified, `--commit` pending manual run).
- **Task 2 partially done** — `lib/Abha.php` helper + all WRITE sites + all reverse-lookups repointed; dual-write keeps the legacy columns in sync so the remaining READ sites stay correct. See **Task 2 status** at the bottom.

## Rollout order

1. `mysql < database/migration_abha_accounts.sql`  (adds the table — already applied on dev)
2. Deploy the code (Task 2 changes) — `lib/Abha.php` has a legacy-column fallback so this is safe **before** step 3.
3. `php database/migrate-abha-data.php` → review → `--commit`  (copies existing rows in)
4. `mysql < database/migration_abha_deprecate_legacy_columns.sql`  (marks old columns DEPRECATED — cosmetic)
5. *(later)* finish repointing the deferred READ sites, then a `migration_drop_legacy_abha_columns.sql`.

## What changed in the schema

New table **`abha_accounts`** (`database/migration_abha_accounts.sql`) — one row per entity:

| column | note |
|---|---|
| `entity_type` | `ENUM('patient','school_member','doctor')` |
| `entity_id` | → `users.id` / `school_members.id` / `doctors.id` |
| `abha_number` | 14-digit, formatted `XX-XXXX-XXXX-XXXX` (was `abha_id` on users/school_members/doctors) |
| `abha_address` | `@sbx` / `@abdm` handle |
| `linked`, `verified` | booleans (mirror old `abha_linked` / `abha_verified`) |
| `linked_at`, `verified_at` | datetimes |
| `source` | provenance (`aadhaar_otp`, `manual`, `migrated`, …) |
| `profile_data` | last ABDM profile snapshot (was `school_members.abha_profile_data`) |
| `UNIQUE(entity_type, entity_id)` | one ABHA per entity |

**Deviations from the task spec** (all deliberate, to preserve existing data/semantics):
- added `linked` boolean (spec had only `linked_at`) — the live data has `abha_linked=1` with `abha_linked_at=NULL`, so linked-state can't be derived from the timestamp alone.
- added `profile_data` — `school_members.abha_profile_data` holds real JSON that would otherwise be lost.
- `id` is `BIGINT UNSIGNED`; no FK on `entity_id` (it targets 3 tables — enforced in app code).

## Old columns — deprecate, don't drop (Task 2)

Leave these in place, marked deprecated in a comment, as read-only fallback for one release:
`users.{abha_id, abha_address, abha_linked, abha_linked_at, abha_verified}`,
`school_members.{abha_id, abha_address, abha_linked, abha_linked_at, abha_verified, abha_profile_data}`,
`doctors.abha_id`.
A later `migration_drop_legacy_abha_columns.sql` removes them.

## Helper to add (Task 2)

Put in `lib/` (e.g. `lib/Abha.php`) or `util/function.php`:

- `abha_for(mysqli $conn, string $entityType, int $entityId): ?array` — read one row.
- `abha_save(mysqli $conn, string $entityType, int $entityId, array $fields): void` — upsert on `uq_entity`.
- `abha_find(mysqli $conn, string $abhaNumberOrAddress): ?array` — reverse lookup → `{entity_type, entity_id, …}`.
- SQL join snippet: `LEFT JOIN abha_accounts aa ON aa.entity_type='patient' AND aa.entity_id = u.id`
  then select `aa.abha_number`, `aa.abha_address`, `aa.linked AS abha_linked`, `aa.verified AS abha_verified`, `aa.linked_at AS abha_linked_at` — keeps the downstream array keys the templates already use.

---

## A. Entity ABHA **WRITES** → `abha_accounts` upsert

| File / line | Current | Action |
|---|---|---|
| `ajax/abdm-api.php:71` `saveAbha()` | `UPDATE $table SET abha_id, abha_address, abha_linked, abha_linked_at, abha_verified WHERE id` (patient / school_member) | rewrite `saveAbha()` to `abha_save('patient'|'school_member', $id, [...])` |
| `ajax/abdm-api.php:566` | `UPDATE $entity_table SET abha_address=? WHERE id=?` | `abha_save(..., ['abha_address'=>...])` |
| `lib/AbhaPatientResolver.php:110` `resolveFromProfile()` | `UPDATE users SET abha_id=…, abha_address=…, abha_linked=1, abha_verified=1 …` | after resolving/creating the user, `abha_save('patient', $patientId, [...])` |
| `lib/AbhaPatientResolver.php:145` (new-patient `INSERT INTO users`) | inserts `abha_id, abha_address, abha_linked, abha_verified` inline | drop those cols from the INSERT; `abha_save()` right after `insert_id` |
| `doctor/api/create-patient-submit.php:146` `INSERT INTO users (… abha_id, abha_address, abha_linked, abha_verified …)` | inline | same pattern — insert the user without abha cols, then `abha_save('patient', $patient_id, …)` |
| `doctor/api/patient-add.php:93` `INSERT INTO users (… abha_address, abha_number …)` | **BUG: `users` has no `abha_number` column** — this endpoint currently throws | fix while repointing: insert user, then `abha_save()` |
| `admin/abha-management.php:25 / 35 / 51` | admin link / unlink / edit → `UPDATE users SET abha_*` | link/edit → `abha_save('patient', …)`; unlink → `DELETE FROM abha_accounts WHERE entity_type='patient' AND entity_id=?` (or set `linked=0`) |
| `school/health/abha.php:28 / 38 / 56` | same three, on `school_members` | same, `entity_type='school_member'` |

## B. Entity ABHA **READS / lookups** → read `abha_accounts` (JOIN or helper)

**Reverse lookups (by ABHA number / address):**
| File / line | Current |
|---|---|
| `util/auth-helper.php:173` `findByAbha()` | `SELECT * FROM users WHERE abha_id=?` |
| `util/auth-helper.php:180` `findByAbha()` | `… school_members sm WHERE sm.abha_id=?` |
| `lib/AbhaPatientResolver.php:87` | `SELECT id FROM users WHERE abha_id=?` |
| `doctor/api/patient-search.php:27,29` | `SELECT … abha_address, abha_id, abha_linked …` / `WHERE … OR abha_address=?` |
| `doctor/api/patient-search-mobile.php:23` | `u.abha_id AS abha_number, u.abha_address, u.abha_verified` |
| `doctor/api/school-lookup-search.php:57` | `WHERE sm.abha_id=? OR sm.abha_address=?` |
| `doctor/api/patient-add.php:38,70` | `SELECT … abha_address, abha_number FROM users` |

**Row fetch for display (add the LEFT JOIN, alias back to old keys):**
`doctor/my-patients.php:47‑50,63` · `doctor/pending-uploads.php:16‑22` · `doctor/analysis-report.php:22,31` (uses `abha_linked` + `DATE(abha_linked_at)`) · `doctor/patient-form.php:129‑131` · `doctor/opd-slip.php:69` · `doctor/patient-details.php:601‑608` · `doctor/patient-profile.php:326‑434` · `doctor/student-profile.php:426` · `doctor/save-student-consent.php:96‑97` · `user/user-dashboard.php:16` · `user/sidebar.php:12` · `user/appointment-details.php:106` · `book-appointment.php:10` (prefill) · `admin/ajax/appointment-details.php:24` · `admin/school-view.php:435` · `school/student/dashboard.php:210‑224` · `school/student/profile.php:313,468` · `school/student/health-card.php:175‑179` · `school/teacher/profile.php:118,266,276` · `school/teacher/students.php:144` · `school/members/list.php:204` · `school/members/view.php` · `telemedicine/room.php:53` · `telemedicine/api/prescription.php:43` · `signup.php:173` (form repopulate) · `doctor/inc/sidebar.php:11` (`doctors.abha_id` — doctor's own, rarely populated)

## C. Aggregate stats (`SUM(abha_linked=1)` / `WHERE abha_linked=1`) → rewrite against `abha_accounts`

| File / line |
|---|
| `admin/index.php:19‑20, 27‑28` (patients + members linked/verified) |
| `admin/abha-management.php:71, 74, 102, 113‑123` (counts + list filters) |
| `admin/school-members.php:9` |
| `school/dashboard.php:8` · `school/teacher/dashboard.php:19` |
| `school/health/abha.php:80‑99, 103‑107` (counts + tabbed list) |

Pattern: `SELECT COUNT(*) FROM abha_accounts WHERE entity_type='school_member' AND linked=1 AND entity_id IN (SELECT id FROM school_members WHERE school_id=?)` — or JOIN.

## D. Per-visit / per-form **SNAPSHOT** columns — keep, but populate from `abha_accounts` at write time

These are point-in-time copies, not identity — correct to keep denormalised:

| Column | Written at | Files |
|---|---|---|
| `appointments.abha_number` | booking | `util/appointment-handler.php:72‑78`, `book-appointment.php` / `e-cardiology.php` form field |
| `prescriptions.abha_number` | consultation save | `util/function.php:1042,1075`, `doctor/patient-form.php:259,281`, `telemedicine/api/prescription.php:177` |
| `parent_consent_forms.student_abha_number` / `student_abha_address` / `student_abha_status` | consent submit | `school/parent-consent.php:530‑532` (+ runtime ALTERs → Task 3) |

Task 2 change: where these are **written**, source the value from `abha_accounts` (via the helper) instead of `users.abha_id` / `$_POST`. Where **read for display** (`util/prescription-render.php`, `doctor/opd-slip.php`, `user/appointment-details.php`, `admin/parent-consent-view.php:168‑169`, `admin/ajax/appointment-details.php`) — no change needed, the snapshot column stays.

## E. Fragile alias `abha_id AS abha_number` → use the real column

Every one of these becomes `aa.abha_number` once the JOIN is in:
`doctor/patient-form.php:129` · `doctor/opd-slip.php:69` · `doctor/api/patient-search-mobile.php:23` · `telemedicine/room.php:53` · `telemedicine/api/prescription.php:43` · `user/appointment-details.php:106` · `admin/ajax/appointment-details.php:24`

`doctor/inc/rx-sidebar.php` consumes `$patient['abha_number']` produced by that alias in `patient-form.php` — keep the array key, change only its source.

## F. NO DB change (display / JS / form field names / pure functions)

- Form `<input name="abha_number">`: `book-appointment.php:728`, `e-cardiology.php:289`
- JS payload / response handling: `doctor/add-patient-*.php`, `school/student/abha.php`, `school/teacher/abha.php`, `user/my-abha.php` (they read `res.abha_id` from the AJAX JSON — keep the JSON key stable, or update both ends together)
- `lib/Validator.php` — `isValidAbhaNumber()`, `formatAbhaNumber()` (keep as-is; reuse for `abha_number` normalisation)

## G. Bugs surfaced while mapping

1. **`doctor/api/patient-add.php:93`** — `INSERT INTO users (… abha_number …)` references a column that does not exist on `users` (`SHOW COLUMNS FROM users` → it's `abha_id`). The endpoint fails on any ABHA-number add. Fix during Task 2.
2. **`doctor/patient-form.php:54`** — the runtime-created `prescriptions` table declares its own `abha_number VARCHAR(20)` (snapshot). Fine as a snapshot; the runtime `CREATE TABLE` itself is Task 3.
3. `school/parent-consent.php:55‑68` — runtime `ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_abha_*` — Task 3.

---

## Task 2 status (2026-09-04)

### ✅ Done — repointed to `abha_accounts` via `lib/Abha.php`

**Helper** — `lib/Abha.php`: `get()`, `save()` (upsert + legacy mirror), `unlink()`,
`find()` (reverse lookup, with legacy-column fallback), `joinClause()`,
`selectAliases($join, $legacyAlias=null)` (aliases back to `abha_id`/`abha_linked`/…,
optional COALESCE onto the legacy columns so reads work pre-migration).

**Writes (all 8):**
- `ajax/abdm-api.php` — `saveAbha()` + `update_abha_address` case
- `lib/AbhaPatientResolver.php` — `resolveFromProfile()` update + insert paths
- `doctor/api/create-patient-submit.php` — new-user insert
- `doctor/api/patient-add.php` — **also fixed the `INSERT … abha_number` bug** (column didn't exist)
- `admin/abha-management.php` — link / unlink / approve-request
- `school/health/abha.php` — link / unlink / approve-request (+ added school-scope check that the old inline SQL had via `AND school_id=?`)

**Reverse lookups:**
- `util/auth-helper.php::findByAbha()` → `Abha::find()`
- `lib/AbhaPatientResolver.php` (abha_id match) → `Abha::find()`
- `doctor/api/patient-search.php`, `patient-search-mobile.php` → JOIN + `selectAliases('aa','u')`
- `doctor/api/school-lookup-search.php` (abha case) → `Abha::find()`

**Fragile alias fixed (the prescription path):**
- `doctor/patient-form.php` and `doctor/opd-slip.php` — `u.abha_id AS abha_number` replaced with `Abha::joinClause()` + `Abha::selectAliases('aa','u')`

**Legacy columns:** marked DEPRECATED via `migration_abha_deprecate_legacy_columns.sql`
(comment only). Still written by `Abha::save()` (dual-write) so the deferred read
sites below stay correct.

### ⏳ Deferred — safe, because dual-write keeps the legacy columns authoritative-equivalent

Do these in the same PR as the eventual `migration_drop_legacy_abha_columns.sql`,
using the same `Abha::joinClause()` + `Abha::selectAliases($j,$legacyAlias)` pattern
(or `Abha::get()` for single-entity pages):

- **Aggregate stats** (`SUM(abha_linked=1)` / `WHERE abha_linked=1`): `admin/index.php`,
  `admin/abha-management.php` (counts + list filters), `admin/school-members.php`,
  `school/dashboard.php`, `school/teacher/dashboard.php`, `school/health/abha.php`
- **Display row-fetch pages** (Group B of the map above): `doctor/my-patients.php`,
  `doctor/pending-uploads.php`, `doctor/analysis-report.php`, `doctor/patient-details.php`,
  `doctor/patient-profile.php`, `doctor/student-profile.php`, `doctor/save-student-consent.php`,
  `user/user-dashboard.php`, `user/sidebar.php`, `user/appointment-details.php`,
  `book-appointment.php`, `admin/ajax/appointment-details.php`, `admin/school-view.php`,
  `school/student/{dashboard,profile,health-card}.php`, `school/teacher/{profile,students}.php`,
  `school/members/{list,view}.php`, `telemedicine/room.php`, `telemedicine/api/prescription.php`,
  `signup.php`, `doctor/inc/sidebar.php`
- Remaining `abha_id AS abha_number` aliases: `telemedicine/room.php`,
  `telemedicine/api/prescription.php`, `user/appointment-details.php`,
  `admin/ajax/appointment-details.php`

### Snapshot columns — unchanged by design
`appointments.abha_number`, `prescriptions.abha_number`,
`parent_consent_forms.student_abha_*` — point-in-time copies; keep. Their *write*
value now ultimately traces back to `abha_accounts` through the repointed read on
the page that captures them.
