<?php
/**
 * Shared config for the telemedicine module.
 * Included by join.php / room.php (web requests, $conn already loaded)
 * AND by signaling-server.php (CLI process — no $conn, loads its own env).
 */

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/connect.php';
}

define('TELEMED_WS_SCHEME',    $_ENV['TELEMED_WS_SCHEME']    ?? 'ws');
define('TELEMED_WS_HOST',      $_ENV['TELEMED_WS_HOST']      ?? 'localhost');
define('TELEMED_WS_PORT',      (int) ($_ENV['TELEMED_WS_PORT'] ?? 8090));
define('TELEMED_WS_BIND_HOST', $_ENV['TELEMED_WS_BIND_HOST'] ?? '0.0.0.0');

// Ticket signing secret — reuse JWT_SECRET so we don't need a second one in .env.
define('TELEMED_SECRET', defined('JWT_SECRET') && JWT_SECRET ? JWT_SECRET : 'change-me-telemed-secret');

// Public STUN only for this MVP (see telemedicine/README.md for TURN notes).
define('TELEMED_ICE_SERVERS', json_encode([
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
]));
