<?php
/**
 * Admin panel bootstrap — DB connection + JWT auth guard in ONE include.
 *
 * Put this at the very top of every admin page, before any output or
 * request handling:
 *
 *     require_once __DIR__ . '/auth/bootstrap.php';
 *
 * (from admin/*.php — adjust the relative prefix for deeper folders).
 *
 * It provides $conn + BASE_URL (via db-conn.php) and calls
 * admin_jwt_guard(), which redirects to auth/login.php when the caller
 * is not a signed-in admin. Safe to include even on pages that already
 * pull in db-conn.php / functions.php — everything here is require_once
 * and the guard is idempotent.
 */

require_once __DIR__ . '/../db-conn.php';   // $conn, BASE_URL, session_start()
require_once __DIR__ . '/guard.php';        // admin_jwt_guard()

admin_jwt_guard();
