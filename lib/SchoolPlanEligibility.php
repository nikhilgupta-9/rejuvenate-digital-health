<?php
/**
 * Shared age + class eligibility check for school_health_plans, used by
 * both the existing parent-consent plan picker (school/parent-consent.php)
 * and the student self-service membership purchase (school/student/membership.php).
 *
 * Plans are always age-banded (age_min/age_max, possibly both null = any
 * age). applicable_classes is an optional additional filter — a JSON array
 * of school_members.class values; null/empty means "no class restriction".
 */

/** Age in whole years on a given date (defaults today). Null for a bad/missing date. */
function school_age_from_dob(?string $dob): ?int
{
    if (!$dob) {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$d || $d->format('Y-m-d') !== $dob) {
        return null;
    }
    return (int) $d->diff(new DateTime('today'))->y;
}

/** True when this plan is open to this school_members row. */
function school_plan_eligible_for_member(array $plan, array $member): bool
{
    $age = school_age_from_dob($member['dob'] ?? null);
    $min = ($plan['age_min'] ?? null) === null || $plan['age_min'] === '' ? null : (int) $plan['age_min'];
    $max = ($plan['age_max'] ?? null) === null || $plan['age_max'] === '' ? null : (int) $plan['age_max'];

    if (($min !== null || $max !== null) && $age === null) {
        return false; // plan is age-restricted but we don't know the student's age
    }
    if ($min !== null && $age < $min) {
        return false;
    }
    if ($max !== null && $age > $max) {
        return false;
    }

    $classesJson = $plan['applicable_classes'] ?? null;
    if ($classesJson) {
        $classes = json_decode($classesJson, true);
        if (is_array($classes) && $classes) {
            $studentClass = trim((string) ($member['class'] ?? ''));
            if ($studentClass === '' || !in_array($studentClass, $classes, true)) {
                return false;
            }
        }
    }

    return true;
}

/** Filter a list of plan rows down to the ones this member qualifies for. */
function school_eligible_plans_for_member(array $plans, array $member): array
{
    return array_values(array_filter(
        $plans,
        fn(array $p) => school_plan_eligible_for_member($p, $member)
    ));
}
