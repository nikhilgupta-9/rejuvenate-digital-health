<?php
/**
 * ⚠️ DEPRECATED — not used by the app anymore. room.php/room.js now use
 * HTTP-polling signaling (see telemedicine/api/poll.php + send.php)
 * instead of this WebSocket server, because it needs a persistent
 * background process on a custom port, which Hostinger shared/Business
 * hosting does not support. Kept only for reference in case a future VPS
 * deployment wants to switch back to true WebSocket signaling (it would
 * be lower-latency than polling). See telemedicine/README.md.
 *
 * If you do want to revive it, this is still a standalone, long-running
 * process — not a web page; run it from the CLI:
 *
 *   php telemedicine/signaling-server.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/SignalingServer.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$dbConfig = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'user' => $_ENV['DB_USERNAME'] ?? 'root',
    'pass' => $_ENV['DB_PASSWORD'] ?? '',
    'name' => $_ENV['DB_NAME'] ?? '',
];

$secret = $_ENV['JWT_SECRET'] ?? '';
if ($secret === '') {
    fwrite(STDERR, "JWT_SECRET is not set in .env — the signaling server cannot verify join tickets. Aborting.\n");
    exit(1);
}

$bindHost = $_ENV['TELEMED_WS_BIND_HOST'] ?? '0.0.0.0';
$port = (int) ($_ENV['TELEMED_WS_PORT'] ?? 8090);

$app = new \Telemedicine\SignalingServer($dbConfig, $secret);

$server = IoServer::factory(
    new HttpServer(new WsServer($app)),
    $port,
    $bindHost
);

fwrite(STDOUT, "Telemedicine signaling server listening on {$bindHost}:{$port} (Ctrl+C to stop)\n");
$server->run();
