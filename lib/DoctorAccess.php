<?php
/**
 * Doctor activation gate — verify -> subscribe -> full access.
 *
 * A doctor is "qualified" (publicly bookable, full dashboard access)
 * when EITHER:
 *   - they're still within their (one-time, 7-day) grace_period_until
 *     window — existing doctors only, so the live site wasn't disrupted
 *     when this feature launched, OR
 *   - is_verified = 1 AND they have an active (paid, unexpired)
 *     doctor_subscriptions row.
 *
 * Deliberately does NOT read/write `doctors.status` — see
 * database/migration_doctor_activation_gate.sql for why.
 */

/** Does this doctor have a currently-paid, unexpired subscription? */
function doctor_has_active_subscription(mysqli $conn, int $doctorId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM doctor_subscriptions WHERE doctor_id = ? AND status = 'paid' AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}

/**
 * @param array $doctor  Must include: id, is_verified, grace_period_until
 */
function doctor_qualifies_active(mysqli $conn, array $doctor): bool
{
    if (!empty($doctor['grace_period_until']) && strtotime($doctor['grace_period_until']) > time()) {
        return true;
    }
    return !empty($doctor['is_verified']) && doctor_has_active_subscription($conn, (int) $doctor['id']);
}

/**
 * Raw SQL condition to embed in a public listing query's WHERE clause,
 * for the cases where fetching the doctor row into PHP first just to
 * call doctor_qualifies_active() would mean an extra round trip per row.
 * $alias is the `doctors` table alias used in that query (default 'd').
 */
function doctor_active_sql_condition(string $alias = 'd'): string
{
    return "(
        ({$alias}.grace_period_until IS NOT NULL AND {$alias}.grace_period_until > NOW())
        OR (
            {$alias}.is_verified = 1
            AND EXISTS (
                SELECT 1 FROM doctor_subscriptions ds
                WHERE ds.doctor_id = {$alias}.id AND ds.status = 'paid' AND ds.expires_at > NOW()
            )
        )
    )";
}
