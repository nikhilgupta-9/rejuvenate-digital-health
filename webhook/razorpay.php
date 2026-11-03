<?php
/**
 * webhook/razorpay.php — Reconciliation webhook for Razorpay payments.
 *
 * PUBLIC endpoint (Razorpay's servers are the caller — no login/session).
 * Register in the Razorpay Dashboard: Settings -> Webhooks -> Add New
 * Webhook -> URL: https://<domain>/webhook/razorpay.php, event:
 * `payment.captured`. Put the webhook secret you choose there into .env as
 * RAZORPAY_WEBHOOK_SECRET (see config/payment.php).
 *
 * WHY THIS EXISTS: appointment booking used to depend entirely on the
 * browser's Razorpay Checkout `handler` callback calling
 * util/appointment-handler.php after a successful payment. If the tab is
 * killed/backgrounded while the user is in a UPI app, or the network drops
 * on that one fetch(), Razorpay has already captured the money but the
 * appointment never gets created. This endpoint is the server-side safety
 * net: Razorpay calls it independently of the browser, so the booking gets
 * created either way.
 *
 * ── Security model ──
 *  1. POST only.
 *  2. Body size cap (256 KB) before touching the DB.
 *  3. Raw body is ALWAYS captured, then the signature is verified via
 *     hash_equals(HMAC-SHA256(raw_body, RAZORPAY_WEBHOOK_SECRET), header).
 *     This is Razorpay's documented webhook auth model — no secret, no
 *     processing.
 *  4. Idempotency — appointments.razorpay_payment_id has a UNIQUE key
 *     (see database/migration_razorpay_webhook.sql). If the browser path
 *     already created the appointment for this payment, the insert here
 *     hits a duplicate-key error and we treat that as success (no-op).
 *  5. Fail-open on 200 — every path returns 200 quickly; a non-2xx makes
 *     Razorpay retry (with backoff) for up to ~24h, so a transient DB
 *     error on our side still gets reconciled automatically on retry.
 *  6. Data-only — payload values are always bound as params; nothing from
 *     the body is executed or concatenated into SQL.
 */

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../util/function.php'; // send_appointment_email()

const RAZORPAY_WEBHOOK_MAX_BYTES = 262144; // 256 KB

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/**
 * Log the outcome and respond. httpCode defaults to 200 (processed, don't
 * retry); pass 500 for a transient failure so Razorpay retries later.
 */
function rzp_ack(string $event, ?string $orderId, ?string $paymentId, string $result, int $httpCode = 200): void
{
    global $conn;
    try {
        $raw = file_get_contents('php://input', false, null, 0, RAZORPAY_WEBHOOK_MAX_BYTES);
        $log = $conn->prepare("INSERT INTO razorpay_webhook_log (event, razorpay_order_id, razorpay_payment_id, result, raw_body) VALUES (?,?,?,?,?)");
        $log->bind_param('sssss', $event, $orderId, $paymentId, $result, $raw);
        $log->execute();
    } catch (Throwable $e) {
        // Logging must never block the ack.
    }
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => $result]);
    exit;
}

// 1. POST only — anything else is ignored quietly.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(200);
    exit;
}

// 2. Body size cap, raw body captured BEFORE any parsing.
$rawBody = file_get_contents('php://input', false, null, 0, RAZORPAY_WEBHOOK_MAX_BYTES + 1);
if ($rawBody === false || strlen($rawBody) > RAZORPAY_WEBHOOK_MAX_BYTES) {
    rzp_ack('unknown', null, null, 'body_too_large');
}

// 3. Signature verification — THE real gate.
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if (!RAZORPAY_WEBHOOK_SECRET || !$signature) {
    rzp_ack('unknown', null, null, 'webhook_not_configured');
}
$expected = hash_hmac('sha256', $rawBody, RAZORPAY_WEBHOOK_SECRET);
if (!hash_equals($expected, $signature)) {
    error_log('[razorpay-webhook] signature mismatch');
    rzp_ack('unknown', null, null, 'signature_invalid');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    rzp_ack('unknown', null, null, 'bad_json');
}

$event = (string) ($payload['event'] ?? 'unknown');
$paymentEntity = $payload['payload']['payment']['entity'] ?? null;
$orderId   = $paymentEntity['order_id'] ?? null;
$paymentId = $paymentEntity['id'] ?? null;

// Only `payment.captured` creates a booking — everything else is just logged.
if ($event !== 'payment.captured' || !$orderId || !$paymentId) {
    rzp_ack($event, $orderId, $paymentId, 'ignored_event');
}

// 4a. Already booked via the browser path? No-op.
$exists = $conn->prepare("SELECT id FROM appointments WHERE razorpay_payment_id = ? LIMIT 1");
$exists->bind_param('s', $paymentId);
$exists->execute();
if ($exists->get_result()->fetch_assoc()) {
    rzp_ack($event, $orderId, $paymentId, 'already_exists');
}

// 4b. Find the pending booking captured at order-creation time.
$pend = $conn->prepare("SELECT * FROM razorpay_pending_bookings WHERE razorpay_order_id = ? LIMIT 1");
$pend->bind_param('s', $orderId);
$pend->execute();
$pending = $pend->get_result()->fetch_assoc();

if (!$pending) {
    error_log('[razorpay-webhook] no pending booking for order ' . $orderId);
    rzp_ack($event, $orderId, $paymentId, 'pending_not_found');
}

$data = json_decode($pending['payload'], true);
if (!is_array($data)) {
    error_log('[razorpay-webhook] corrupt pending payload for order ' . $orderId);
    rzp_ack($event, $orderId, $paymentId, 'bad_payload');
}

// Re-derive the required fields the same way appointment-handler.php does
// — the webhook is a fallback for the SAME flow, not a separate one.
$missing = array_diff(['name', 'email', 'phone', 'department', 'date', 'time'], array_keys(array_filter($data, fn($v) => $v !== null && $v !== '')));
if ($missing) {
    error_log('[razorpay-webhook] pending payload missing fields for order ' . $orderId . ': ' . implode(',', $missing));
    rzp_ack($event, $orderId, $paymentId, 'incomplete_payload');
}

$doctorId = !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null;

// Same slot-collision guard as appointment-handler.php — best-effort, the
// UNIQUE key on razorpay_payment_id is the hard guarantee against this
// specific payment being booked twice.
if ($doctorId) {
    $chk = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending','approved') LIMIT 1");
    $chk->bind_param('iss', $doctorId, $data['date'], $data['time']);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        // The slot got taken by someone else between order-creation and
        // this webhook. The captured amount still needs a human to sort
        // out (refund or manual reschedule) — leave it logged, don't book.
        error_log('[razorpay-webhook] slot no longer available for order ' . $orderId . ', payment ' . $paymentId . ' — needs manual refund/reschedule');
        rzp_ack($event, $orderId, $paymentId, 'slot_taken_needs_manual_review');
    }
}

$data['payment_status']      = 'paid';
$data['payment_amount']      = round(((int) ($paymentEntity['amount'] ?? $pending['amount_paise'])) / 100, 2);
$data['razorpay_order_id']   = $orderId;
$data['razorpay_payment_id'] = $paymentId;
$data['razorpay_signature']  = null; // not applicable here — this request is authenticated via the webhook signature instead

$appointmentId = send_appointment_email($data);

if ($appointmentId) {
    $upd = $conn->prepare("UPDATE razorpay_pending_bookings SET status = 'converted' WHERE razorpay_order_id = ?");
    $upd->bind_param('s', $orderId);
    $upd->execute();

    try {
        require_once __DIR__ . '/../lib/AuditLogger.php';
        (new AuditLogger($conn))->logDataAccess(
            $data['user_id'] ?? 0,
            'patient',
            $data['user_id'] ?? 0,
            'appointment_booking',
            'Appointment booked via Razorpay webhook reconciliation (browser never completed the booking after payment)'
        );
    } catch (Throwable $e) {
    }

    rzp_ack($event, $orderId, $paymentId, 'booked');
}

// A UNIQUE-key race with the browser path lands here as a generic insert
// failure — check once more before giving up, since that race means the
// booking actually exists now.
$exists->execute();
if ($exists->get_result()->fetch_assoc()) {
    rzp_ack($event, $orderId, $paymentId, 'already_exists_race');
}

error_log('[razorpay-webhook] send_appointment_email() failed for order ' . $orderId . ', payment ' . $paymentId);
rzp_ack($event, $orderId, $paymentId, 'insert_failed', 500); // let Razorpay retry — this payment still needs a booking
