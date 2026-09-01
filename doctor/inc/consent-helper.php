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

/** True when an affirmative consent is on file for this member. */
function student_has_consent(mysqli $conn, int $member_id): bool
{
    $s = $conn->prepare("SELECT id FROM parent_consent_forms WHERE member_id = ? AND consent_given = 1 LIMIT 1");
    $s->bind_param('i', $member_id);
    $s->execute();
    return (bool) $s->get_result()->fetch_assoc();
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

/** Redirect a blocked save back to the consent tab with a message. */
function consent_gate_or_redirect(mysqli $conn, int $member_id): void
{
    if (student_has_consent($conn, $member_id)) {
        return;
    }
    $_SESSION['consent_required'] = 'Parent consent is required before you can record a checkup for this student. Please record the consent first.';
    header('Location: ' . BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#consent');
    exit;
}
