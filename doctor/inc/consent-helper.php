<?php
/**
 * Parent-consent helpers shared by the doctor school-health flow.
 *
 * A doctor may not record a checkup (health profile, prescription or
 * certificate) for a school student until a parent consent is on file
 * for that member — either submitted by the parent via
 * school/parent-consent.php or captured by the doctor at point of care.
 */

/** The checkup services a parent consents to, keyed as stored in consent_items JSON. */
function consent_item_labels(): array
{
    return [
        'general_checkup'   => 'General Physical Checkup',
        'height_weight'     => 'Height, Weight & BMI',
        'vision_test'       => 'Vision / Eyesight Screening',
        'dental_check'      => 'Dental Examination',
        'blood_pressure'    => 'Blood Pressure & Pulse Check',
        'vaccination_check' => 'Vaccination Status Review',
        'mental_wellness'   => 'Mental Wellness Screening',
        'data_storage'      => 'Digital Health Record Storage',
        'data_share_doctor' => 'Share Data with School Doctor',
        'data_share_school' => 'Anonymised Data with School',
    ];
}

/** Latest consent row for a member, or null. */
function get_student_consent(mysqli $conn, int $member_id): ?array
{
    $s = $conn->prepare("SELECT * FROM parent_consent_forms WHERE member_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $s->bind_param('i', $member_id);
    $s->execute();
    return $s->get_result()->fetch_assoc() ?: null;
}

/**
 * True when a currently-valid consent is on file: given, not revoked, not
 * past its academic-year expiry, and — when it's tied to a paid plan —
 * that plan payment has gone through. A doctor-captured, point-of-care
 * consent (no plan_id) is not payment-gated.
 */
function student_has_consent(mysqli $conn, int $member_id): bool
{
    $s = $conn->prepare("SELECT id FROM parent_consent_forms
        WHERE member_id = ?
          AND consent_given = 1
          AND revoked = 0
          AND (expires_at IS NULL OR expires_at >= CURDATE())
          AND (plan_id IS NULL OR payment_status = 'paid')
        LIMIT 1");
    $s->bind_param('i', $member_id);
    $s->execute();
    return (bool) $s->get_result()->fetch_assoc();
}

/**
 * Human-readable reason the most recent consent on file (if any) isn't
 * currently valid — used to give the doctor a specific message instead of
 * a generic "consent required". Returns null when a valid consent exists.
 */
function student_consent_block_reason(mysqli $conn, int $member_id): ?string
{
    if (student_has_consent($conn, $member_id)) {
        return null;
    }
    $consent = get_student_consent($conn, $member_id);
    if (!$consent) {
        return 'No parent consent has been recorded for this student yet.';
    }
    if (!(int) $consent['consent_given']) {
        return 'The parent/guardian did not agree to the consent declaration.';
    }
    if ((int) $consent['revoked']) {
        return 'The parent/guardian has revoked this consent. A fresh consent is required before you can proceed.';
    }
    if (!empty($consent['expires_at']) && $consent['expires_at'] < date('Y-m-d')) {
        return 'This consent expired on ' . date('d M Y', strtotime($consent['expires_at'])) . ' (end of academic year). A fresh consent is required.';
    }
    if (!empty($consent['plan_id']) && $consent['payment_status'] !== 'paid') {
        return 'The school-health plan payment linked to this consent has not been completed yet.';
    }
    return 'Parent consent on file is not currently valid.';
}

/**
 * An unlinked parent submission that looks like it belongs to this member
 * (same school, same student name) — offered to the doctor to confirm & link.
 */
function find_unlinked_consent(mysqli $conn, int $school_id, string $student_name): ?array
{
    $s = $conn->prepare("SELECT * FROM parent_consent_forms
        WHERE member_id IS NULL AND consent_given = 1 AND school_id = ?
          AND LOWER(TRIM(student_name)) = LOWER(TRIM(?))
        ORDER BY submitted_at DESC LIMIT 1");
    $s->bind_param('is', $school_id, $student_name);
    $s->execute();
    return $s->get_result()->fetch_assoc() ?: null;
}

/** Redirect a blocked save back to the consent tab with a specific reason. */
function consent_gate_or_redirect(mysqli $conn, int $member_id): void
{
    $reason = student_consent_block_reason($conn, $member_id);
    if ($reason === null) {
        return;
    }
    $_SESSION['consent_required'] = $reason;
    header('Location: ' . BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#consent');
    exit;
}
