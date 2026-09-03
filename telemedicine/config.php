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

/*
 * ICE servers + poll interval.
 *
 * Base is public Google STUN (works for most home/office networks). A TURN
 * relay — needed for calls behind strict NAT / corporate firewalls / some
 * mobile carriers — is configured by the admin at
 * admin/telemedicine-settings.php and stored in `telemedicine_settings`.
 * We read it here if that table exists and a $conn is available (web
 * requests). The deprecated CLI signaling server has no $conn and just
 * gets the STUN-only default.
 */
$__telemed_ice = [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
];
$__telemed_poll = 3000;   // ms between polls — 3s keeps DB load low on shared hosting

if (isset($conn) && $conn instanceof mysqli) {
    try {
        $__r = @$conn->query("SELECT setting_key, setting_value FROM telemedicine_settings");
        if ($__r) {
            $__s = [];
            while ($__row = $__r->fetch_assoc()) {
                $__s[$__row['setting_key']] = $__row['setting_value'];
            }
            if (!empty($__s['turn_url'])) {
                $__turn = ['urls' => array_values(array_filter(array_map('trim', explode(',', $__s['turn_url']))))];
                if (isset($__s['turn_username']) && $__s['turn_username'] !== '') {
                    $__turn['username'] = $__s['turn_username'];
                }
                if (isset($__s['turn_credential']) && $__s['turn_credential'] !== '') {
                    $__turn['credential'] = $__s['turn_credential'];
                }
                $__telemed_ice[] = $__turn;
            }
            if (!empty($__s['extra_stun'])) {
                foreach (array_filter(array_map('trim', explode(',', $__s['extra_stun']))) as $__stun) {
                    $__telemed_ice[] = ['urls' => $__stun];
                }
            }
            if (!empty($__s['poll_interval_ms']) && (int) $__s['poll_interval_ms'] >= 500) {
                $__telemed_poll = (int) $__s['poll_interval_ms'];
            }
        }
    } catch (Throwable $e) {
        // telemedicine_settings not created yet — STUN-only defaults are fine.
    }
}

define('TELEMED_ICE_SERVERS', json_encode($__telemed_ice));
define('TELEMED_POLL_INTERVAL_MS', $__telemed_poll);
