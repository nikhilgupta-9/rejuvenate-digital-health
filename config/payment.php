<?php
/**
 * Razorpay config. Included by anything that creates/verifies a payment:
 *  - util/create-razorpay-order.php
 *  - util/appointment-handler.php (signature verification)
 *  - webhook/razorpay.php (payment.captured reconciliation)
 *
 * Keys live in .env (never committed — see .gitignore) as
 * RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET. Get them from
 * https://dashboard.razorpay.com/app/keys (use the Test keys while
 * developing, Live keys only once the site is ready to take real payments).
 *
 * RAZORPAY_WEBHOOK_SECRET is a SEPARATE secret you set yourself when
 * creating the webhook at https://dashboard.razorpay.com/app/webhooks
 * (Settings -> Webhooks -> Add New Webhook), pointed at
 * https://<your-domain>/webhook/razorpay.php, subscribed to the
 * `payment.captured` event. Put the same value in .env.
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/connect.php';
}

define('RAZORPAY_KEY_ID',         $_ENV['RAZORPAY_KEY_ID']         ?? '');
define('RAZORPAY_KEY_SECRET',     $_ENV['RAZORPAY_KEY_SECRET']     ?? '');
define('RAZORPAY_WEBHOOK_SECRET', $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '');
