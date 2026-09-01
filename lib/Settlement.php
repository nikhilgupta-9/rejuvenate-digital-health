<?php
/**
 * Doctor payment settlement (T+2).
 *
 * Whenever an appointment's status becomes 'completed' AND it was
 * actually paid for (appointments.payment_status = 'paid'), a settlement
 * row is created here: gross amount minus the platform's commission,
 * due 2 days after completion. Settling itself is a manual admin action
 * (bank transfer done offline, then marked here) — see admin/settlements.php.
 *
 * Called from every place appointments.status can be set to 'completed':
 * doctor/appointments.php, doctor/patient-form.php, admin/today-appointments.php.
 */

const SETTLEMENT_COMMISSION_RATE_PERCENT = 10.00; // platform's cut
const SETTLEMENT_DAYS = 2;                          // T+2

/**
 * Idempotent — safe to call every time an appointment is marked
 * completed, even repeatedly (e.g. status toggled back and forth).
 */
function create_settlement_if_needed(mysqli $conn, int $appointmentId): void
{
    $stmt = $conn->prepare("SELECT id, doctor_id, status, payment_status, payment_amount FROM appointments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();

    if (!$appt || $appt['status'] !== 'completed' || $appt['payment_status'] !== 'paid' || empty($appt['doctor_id'])) {
        return;
    }

    $gross = (float) $appt['payment_amount'];
    if ($gross <= 0) {
        return;
    }

    $rate       = SETTLEMENT_COMMISSION_RATE_PERCENT;
    $commission = round($gross * $rate / 100, 2);
    $net        = round($gross - $commission, 2);
    $dueDate    = date('Y-m-d', strtotime('+' . SETTLEMENT_DAYS . ' days'));
    $doctorId   = (int) $appt['doctor_id'];

    $ins = $conn->prepare("INSERT IGNORE INTO appointment_settlements
        (appointment_id, doctor_id, gross_amount, commission_rate, commission_amount, settlement_amount, status, due_date)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
    $ins->bind_param('iidddds', $appointmentId, $doctorId, $gross, $rate, $commission, $net, $dueDate);
    $ins->execute();
}
