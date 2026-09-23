<?php
/**
 * Razorpay order creation — POST /v1/orders.
 *
 * Same curl + Basic-auth shape already duplicated in
 * util/create-razorpay-order.php and inline in school/parent-consent.php
 * (pcf_create_razorpay_order()). Neither of those is touched — this is a
 * shared helper for new call sites only (school/student/membership.php,
 * school/parent-booking-approval.php) so Phase 2 doesn't add a third copy.
 *
 * @return array{success:bool,order_id:?string,amount:?int,message:?string}
 */
function razorpay_create_order(int $amountPaise, string $receipt, array $notes = []): array
{
    require_once __DIR__ . '/../config/payment.php';

    if (!RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
        error_log('[Razorpay] RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET not configured in .env');
        return ['success' => false, 'order_id' => null, 'amount' => null, 'message' => 'Online payment is temporarily unavailable. Please try again later.'];
    }
    if ($amountPaise <= 0) {
        return ['success' => false, 'order_id' => null, 'amount' => null, 'message' => 'Invalid amount.'];
    }

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'   => $amountPaise,
            'currency' => 'INR',
            'receipt'  => $receipt,
            'notes'    => $notes,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        error_log('[Razorpay] Order creation failed: ' . $curlErr . ' | HTTP ' . $httpCode . ' | ' . $response);
        return ['success' => false, 'order_id' => null, 'amount' => null, 'message' => 'Could not start the payment. Please try again.'];
    }

    $order = json_decode($response, true);
    if (empty($order['id'])) {
        error_log('[Razorpay] Unexpected order response: ' . $response);
        return ['success' => false, 'order_id' => null, 'amount' => null, 'message' => 'Could not start the payment. Please try again.'];
    }

    return ['success' => true, 'order_id' => $order['id'], 'amount' => $amountPaise, 'message' => null];
}

/** hash_equals()-safe check of a Razorpay checkout callback's signature. */
function razorpay_verify_signature(string $orderId, string $paymentId, string $signature): bool
{
    require_once __DIR__ . '/../config/payment.php';
    if (!RAZORPAY_KEY_SECRET) {
        return false;
    }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    return hash_equals($expected, $signature);
}
