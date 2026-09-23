<?php
/**
 * Create a school_health_memberships row for a paid plan purchase — the
 * one place that turns a successful payment into a 12-month membership
 * with its commission snapshot. Shared by school/parent-consent.php (plan
 * purchase bundled into a consent submission) and
 * school/student/membership.php (student self-service purchase) — see
 * database/migration_school_membership_phase2.sql.
 *
 * Returns the new membership id, or null when there's no member_id/school_id
 * to attribute it to yet (e.g. a manual-mode consent submission not linked
 * to a student — the plan payment still succeeds, it just isn't a formal
 * membership until a doctor/admin links the consent to a real
 * school_members row).
 */
function school_create_membership(mysqli $conn, ?int $memberId, ?int $schoolId, ?int $planId, ?string $planName, ?float $amountPaid, ?string $razorpayPaymentId): ?int
{
    if (!$memberId || !$schoolId || $amountPaid === null) {
        return null;
    }

    $settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('school_commission_percent','membership_refund_window_days')");
    while ($r = $res->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];
    $commissionPercent = (float) ($settings['school_commission_percent'] ?? 10);
    $refundDays        = (int) ($settings['membership_refund_window_days'] ?? 7);
    $commissionAmount  = round($amountPaid * $commissionPercent / 100, 2);

    $stmt = $conn->prepare("INSERT INTO school_health_memberships
        (member_id, school_id, plan_id, plan_name, plan_price, amount_paid, start_date, end_date, status,
         razorpay_payment_id, paid_at, commission_percent, commission_amount, commission_status,
         commission_release_at, refund_eligible_until)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 'active',
         ?, NOW(), ?, ?, 'held',
         DATE_ADD(NOW(), INTERVAL ? DAY), DATE_ADD(NOW(), INTERVAL ? DAY))");
    $stmt->bind_param(
        'iiisddsddii',
        $memberId, $schoolId, $planId, $planName, $amountPaid, $amountPaid,
        $razorpayPaymentId, $commissionPercent, $commissionAmount, $refundDays, $refundDays
    );
    $stmt->execute();
    return $stmt->insert_id ?: null;
}

/** The member's current active membership row, or null. */
function school_active_membership(mysqli $conn, int $memberId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM school_health_memberships
        WHERE member_id = ? AND status = 'active' AND end_date >= CURDATE()
        ORDER BY end_date DESC LIMIT 1");
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
