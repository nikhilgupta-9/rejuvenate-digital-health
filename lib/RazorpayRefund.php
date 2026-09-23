<?php
/**
 * Razorpay refund API call — POST /v1/payments/{payment_id}/refund.
 *
 * First refund code in the repo (order creation is duplicated in
 * util/create-razorpay-order.php and school/parent-consent.php, but neither
 * ever refunds). Same curl + Basic-auth shape as those, kept here as a
 * single shared function since a refund call site needs to exist in at
 * least two places (admin membership cancel now, appointment cancel later)
 * and shouldn't be copy-pasted a third time.
 *
 * @param string $paymentId   the razorpay_payment_id being refunded
 * @param int    $amountPaise amount to refund, in paise (full or partial)
 * @param array  $notes       optional key/value notes stored on the refund
 * @return array{success:bool,refund_id:?string,status:?string,message:?string}
 */
function razorpay_refund_payment(string $paymentId, int $amountPaise, array $notes = []): array
{
    require_once __DIR__ . '/../config/payment.php';

    if (!RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
        error_log('[Razorpay] RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET not configured in .env');
        return ['success' => false, 'refund_id' => null, 'status' => null, 'message' => 'Payment gateway is not configured.'];
    }
    if ($paymentId === '' || $amountPaise <= 0) {
        return ['success' => false, 'refund_id' => null, 'status' => null, 'message' => 'Invalid refund request.'];
    }

    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount' => $amountPaise,
            'speed'  => 'normal',
            'notes'  => $notes,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode >= 300) {
        error_log('[Razorpay] Refund failed for payment ' . $paymentId . ': ' . $curlErr . ' | HTTP ' . $httpCode . ' | ' . $response);
        return ['success' => false, 'refund_id' => null, 'status' => null, 'message' => 'The refund could not be processed. Please try again or contact support.'];
    }

    $refund = json_decode($response, true);
    if (empty($refund['id'])) {
        error_log('[Razorpay] Unexpected refund response for payment ' . $paymentId . ': ' . $response);
        return ['success' => false, 'refund_id' => null, 'status' => null, 'message' => 'The refund could not be processed. Please try again or contact support.'];
    }

    return [
        'success'   => true,
        'refund_id' => $refund['id'],
        'status'    => $refund['status'] ?? 'processed',
        'message'   => null,
    ];
}
