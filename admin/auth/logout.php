<?php
session_start();

// Revoke the refresh token server-side so a stolen cookie can't be replayed
// after logout.
if (!empty($_COOKIE['rdh_admin_refresh'])) {
    require __DIR__ . '/../db-conn.php';
    $hash = hash('sha256', $_COOKIE['rdh_admin_refresh']);
    $stmt = $conn->prepare("UPDATE jwt_refresh_tokens SET revoked=1, revoked_at=NOW() WHERE entity_type='admin' AND token_hash=?");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $conn->close();
}

setcookie('rdh_admin_token',   '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
setcookie('rdh_admin_refresh', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);

session_unset();
session_destroy();

header("Location: login.php");
exit();
