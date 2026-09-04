-- ============================================================
-- Migration: core foreign keys
-- Run once on: rej_digital_health_db (MariaDB 10.4)
--   mysql -u root rej_digital_health_db < database/migration_core_foreign_keys.sql
--
-- Adds referential integrity that the schema never had. Verified against the
-- live data first: zero orphan rows in every relationship below.
--
-- ON DELETE policy:
--   RESTRICT  — clinical / health / identity data. Never auto-delete; the
--               app must soft-delete (status='Inactive') instead.
--   CASCADE   — operational / session / transient-workflow rows.
--   SET NULL  — optional attribution (reviewer / plan / recorded-by).
--
-- NOT covered (deliberately): the polymorphic entity_id columns on
--   jwt_refresh_tokens, abha_accounts, abdm_audit_logs (they point at
--   different tables by entity_type — no single-column FK possible), and
--   abdm_audit_logs must outlive its subjects for NHA audit compliance.
--
-- App changes shipped alongside this migration:
--   admin/doctors-list.php  -> soft-delete (was: hard DELETE FROM doctors)
--   admin/delete-school.php -> soft-delete (was: hard DELETE FROM schools)
-- ============================================================

-- ── 1. Type alignment (FK needs identical type + signedness on both sides).
--       These columns were INT UNSIGNED; the parent PKs are INT(11) signed.
--       All values are small positive ids -> lossless. (MODIFY is idempotent.)
ALTER TABLE `doctor_patients`
  MODIFY COLUMN `doctor_id`  INT(11) NOT NULL,
  MODIFY COLUMN `patient_id` INT(11) NOT NULL;

ALTER TABLE `prescriptions`
  MODIFY COLUMN `appointment_id` INT(11) NOT NULL,
  MODIFY COLUMN `doctor_id`      INT(11) NOT NULL,
  MODIFY COLUMN `patient_id`     INT(11) NOT NULL;

ALTER TABLE `parent_consent_forms`
  MODIFY COLUMN `school_id` INT(11) DEFAULT NULL;

-- ── 2. RESTRICT — clinical / health / identity ───────────────────────────
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_rx_appt`    FOREIGN KEY IF NOT EXISTS (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rx_doctor`  FOREIGN KEY IF NOT EXISTS (`doctor_id`)      REFERENCES `doctors`(`id`)      ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rx_patient` FOREIGN KEY IF NOT EXISTS (`patient_id`)     REFERENCES `users`(`id`)        ON DELETE RESTRICT;

ALTER TABLE `parent_consent_forms`
  ADD CONSTRAINT `fk_pcf_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_pcf_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`)        ON DELETE RESTRICT;

ALTER TABLE `school_members`
  ADD CONSTRAINT `fk_sm_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE RESTRICT;

ALTER TABLE `doctor_patients`
  ADD CONSTRAINT `fk_dp_doctor` FOREIGN KEY IF NOT EXISTS (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE RESTRICT;

ALTER TABLE `member_health_profiles`
  ADD CONSTRAINT `fk_mhp_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT;

ALTER TABLE `school_member_prescriptions`
  ADD CONSTRAINT `fk_smrx_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_smrx_doctor` FOREIGN KEY IF NOT EXISTS (`doctor_id`) REFERENCES `doctors`(`id`)        ON DELETE RESTRICT;

ALTER TABLE `school_member_certificates`
  ADD CONSTRAINT `fk_smc_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_smc_doctor` FOREIGN KEY IF NOT EXISTS (`doctor_id`) REFERENCES `doctors`(`id`)        ON DELETE RESTRICT;

ALTER TABLE `school_member_documents`
  ADD CONSTRAINT `fk_smd_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE RESTRICT;

-- ── 3. CASCADE — operational / session / transient ──────────────────────
ALTER TABLE `doctor_sessions`
  ADD CONSTRAINT `fk_dsess_doctor` FOREIGN KEY IF NOT EXISTS (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE;

ALTER TABLE `doctor_patients`
  ADD CONSTRAINT `fk_dp_patient` FOREIGN KEY IF NOT EXISTS (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `user_abha_requests`
  ADD CONSTRAINT `fk_uar_user` FOREIGN KEY IF NOT EXISTS (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `abha_link_requests`
  ADD CONSTRAINT `fk_alr_member` FOREIGN KEY IF NOT EXISTS (`member_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alr_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`)        ON DELETE CASCADE;

ALTER TABLE `school_users`
  ADD CONSTRAINT `fk_su_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

ALTER TABLE `teacher_student_assignments`
  ADD CONSTRAINT `fk_tsa_teacher` FOREIGN KEY IF NOT EXISTS (`teacher_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tsa_student` FOREIGN KEY IF NOT EXISTS (`student_id`) REFERENCES `school_members`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tsa_school`  FOREIGN KEY IF NOT EXISTS (`school_id`)  REFERENCES `schools`(`id`)        ON DELETE CASCADE;

ALTER TABLE `member_health_profiles`
  ADD CONSTRAINT `fk_mhp_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

ALTER TABLE `school_member_prescriptions`
  ADD CONSTRAINT `fk_smrx_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

ALTER TABLE `school_member_certificates`
  ADD CONSTRAINT `fk_smc_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

ALTER TABLE `school_member_documents`
  ADD CONSTRAINT `fk_smd_school` FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- ── 4. SET NULL — optional attribution (all columns nullable) ───────────
ALTER TABLE `parent_consent_forms`
  ADD CONSTRAINT `fk_pcf_plan`   FOREIGN KEY IF NOT EXISTS (`plan_id`)               REFERENCES `school_health_plans`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pcf_recdoc` FOREIGN KEY IF NOT EXISTS (`recorded_by_doctor_id`) REFERENCES `doctors`(`id`)              ON DELETE SET NULL;

ALTER TABLE `user_abha_requests`
  ADD CONSTRAINT `fk_uar_reviewer` FOREIGN KEY IF NOT EXISTS (`reviewed_by`) REFERENCES `admin_user`(`id`) ON DELETE SET NULL;

ALTER TABLE `abha_link_requests`
  ADD CONSTRAINT `fk_alr_reviewer` FOREIGN KEY IF NOT EXISTS (`reviewed_by`) REFERENCES `school_users`(`id`) ON DELETE SET NULL;
