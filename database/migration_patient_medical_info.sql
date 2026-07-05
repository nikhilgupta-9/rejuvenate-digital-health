-- Adds editable Medical Information fields to the patient (users) table,
-- backing the doctor panel's "Medical Information" tab.
ALTER TABLE users
  ADD COLUMN allergies TEXT NULL AFTER emergency_contact,
  ADD COLUMN existing_condition TEXT NULL AFTER allergies,
  ADD COLUMN current_medication TEXT NULL AFTER existing_condition,
  ADD COLUMN medical_history TEXT NULL AFTER current_medication;
