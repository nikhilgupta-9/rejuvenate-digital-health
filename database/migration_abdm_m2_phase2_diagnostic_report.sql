-- ABDM M2 Phase 2: let abdm_care_context_links also point at a lab booking's
-- report (lab_bookings), not only a prescription or an uploaded document —
-- reuses the existing table instead of adding a new one (Phase 1 already
-- generalised it once for documents; this extends the same pattern).
ALTER TABLE abdm_care_context_links
  ADD COLUMN IF NOT EXISTS lab_booking_id INT(10) UNSIGNED NULL AFTER document_id,
  ADD KEY IF NOT EXISTS idx_lab_booking (lab_booking_id),
  ADD CONSTRAINT fk_cclink_lab FOREIGN KEY IF NOT EXISTS (lab_booking_id)
    REFERENCES lab_bookings (id) ON DELETE CASCADE;

ALTER TABLE abdm_care_context_links
  DROP CONSTRAINT IF EXISTS chk_cclink_one_source;

ALTER TABLE abdm_care_context_links
  ADD CONSTRAINT chk_cclink_one_source CHECK (
    (prescription_id IS NOT NULL AND document_id IS NULL AND lab_booking_id IS NULL) OR
    (prescription_id IS NULL AND document_id IS NOT NULL AND lab_booking_id IS NULL) OR
    (prescription_id IS NULL AND document_id IS NULL AND lab_booking_id IS NOT NULL)
  );
