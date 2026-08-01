<?php
/**
 * Shared helper for creating/reusing a telemedicine room for an online
 * appointment. Called from two places:
 *  - util/function.php, right after a new appointment is inserted, so the
 *    join link can go out in the booking confirmation emails immediately.
 *  - telemedicine/join.php, as an idempotent fallback for appointments that
 *    somehow don't have a room yet (e.g. created before this existed).
 */

require_once __DIR__ . '/config.php'; // defines BASE_URL, TELEMED_SECRET (loads config/connect.php if needed)
require_once dirname(__DIR__) . '/lib/JWT.php';

/**
 * @return array{token:string,link:string}|null  null if the appointment
 *         doesn't exist or isn't an online consultation.
 */
function telemedicine_ensure_room(mysqli $conn, int $appointmentId): ?array
{
    $stmt = $conn->prepare("SELECT appointment_type, meeting_event_id FROM appointments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || $row['appointment_type'] !== 'online') {
        return null;
    }

    // This is the link that goes out in emails / dashboards. It's the
    // OWNERSHIP-CHECKING entry point (join.php), not room.php directly —
    // room.php only accepts a signed ticket, which join.php issues after
    // verifying the clicker is actually the doctor or patient on this
    // appointment.
    $joinLink = BASE_URL . 'telemedicine/join.php?appointment_id=' . $appointmentId;

    if (!empty($row['meeting_event_id'])) {
        return ['token' => $row['meeting_event_id'], 'link' => $joinLink];
    }

    $token = bin2hex(random_bytes(20));
    $upd = $conn->prepare("UPDATE appointments SET
        meeting_provider = 'rejuvenate-webrtc',
        meeting_event_id = ?,
        meeting_link = ?,
        meeting_status = 'created',
        meeting_created_at = NOW()
        WHERE id = ?");
    $upd->bind_param('ssi', $token, $joinLink, $appointmentId);
    $upd->execute();

    return ['token' => $token, 'link' => $joinLink];
}

/**
 * A guest patient (booked without an account — appointments.user_id IS NULL)
 * has no session to prove ownership with, so the plain join link is useless
 * to them: telemedicine/join.php would just bounce them to the login page
 * forever. Instead, mail them a link carrying a signed token scoped to this
 * exact appointment_id, which join.php accepts in place of a login.
 */
function telemedicine_guest_link(int $appointmentId): string
{
    $token = JWT::issue([
        'purpose'        => 'telemed_guest',
        'appointment_id' => $appointmentId,
    ], TELEMED_SECRET, 7776000); // 90 days — comfortably covers any future-dated booking

    return BASE_URL . 'telemedicine/join.php?appointment_id=' . $appointmentId . '&guest_token=' . urlencode($token);
}
