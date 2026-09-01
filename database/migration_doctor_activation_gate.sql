-- ============================================================
-- DOCTOR ACTIVATION GATE (verify -> subscribe -> publicly bookable)
--
-- Deliberately does NOT touch `doctors.status` — that column is used
-- by login/refresh-token checks and is the admin's manual enable/disable
-- switch. Auto-flipping it based on subscription state risks silently
-- undoing an admin's deliberate deactivation, and could lock a doctor
-- out of login entirely (not just gate their dashboard) via the
-- status-gated refresh-token flow in doctor/auth/guard.php.
--
-- Instead, "is this doctor allowed full access / publicly bookable" is
-- computed live (see lib/DoctorAccess.php) from:
--   is_verified = 1  AND  an active (paid, unexpired) doctor_subscriptions row
--   OR
--   still within grace_period_until (only doctors who had already been
--   registered 3+ months before this feature launched — see below)
--
-- `grace_period_until` is set ONCE here to a 7-day window from deploy
-- time, but ONLY for doctors who had already been members for 3+ months
-- at that point — established doctors, so the live site isn't disrupted
-- for them. A doctor who joined less than 3 months before this ran gets
-- no grace at all: they're gated exactly like a brand-new signup, same
-- as anyone registering after this migration (whose grace_period_until
-- is NULL, the column's default, from day one).
-- ============================================================

ALTER TABLE `doctors`
  ADD COLUMN `grace_period_until` DATETIME DEFAULT NULL AFTER `is_verified`;

-- One-time: grant a 7-day grace window only to doctors who already had
-- 3+ months of tenure (added_on) as of deploy time.
UPDATE `doctors`
SET `grace_period_until` = DATE_ADD(NOW(), INTERVAL 7 DAY)
WHERE `added_on` <= DATE_SUB(NOW(), INTERVAL 3 MONTH);
