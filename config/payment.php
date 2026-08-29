<?php
/**
 * Razorpay config. Included by anything that creates/verifies a payment:
 *  - util/create-razorpay-order.php
 *  - util/appointment-handler.php (signature verification)
 *
 * Keys live in .env (never committed — see .gitignore) as
 * RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET. Get them from
 * https://dashboard.razorpay.com/app/keys (use the Test keys while
 * developing, Live keys only once the site is ready to take real payments).
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/connect.php';
}

define('RAZORPAY_KEY_ID',     $_ENV['RAZORPAY_KEY_ID']     ?? '');
define('RAZORPAY_KEY_SECRET', $_ENV['RAZORPAY_KEY_SECRET'] ?? '');
