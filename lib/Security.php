<?php
/**
 * Security — ABDM-compliant security controls.
 *
 * Covers:
 *  - Session management   (15-min idle, secure flags, no URL tokens)
 *  - Rate limiting         (5 attempts → 30-min lockout)
 *  - CSRF token            (per-session, rotated on login)
 *  - Token lifetime        (12h admin / 30h user — ABDM guideline)
 *  - Password hashing      (bcrypt, cost 12 — never MD5/SHA1)
 *  - Generic error text    (no stack traces / internals to client)
 */
class Security
{
    /* ── Tunable constants (ABDM guidelines) ── */
    const SESSION_IDLE_SECONDS    = 900;   // 15 minutes
    const MAX_LOGIN_ATTEMPTS      = 5;
    const LOCKOUT_MINUTES         = 30;
    const TOKEN_TTL_USER_SECONDS  = 108000; // 30 hours
    const TOKEN_TTL_ADMIN_SECONDS = 43200;  // 12 hours
    const BCRYPT_COST             = 12;

    /* ═══════════════════════════════════════════════
       SESSION MANAGEMENT
       ABDM: Idle timeout 15 min; Secure + HttpOnly cookie;
             no session ID in URLs; regenerate on login.
    ═══════════════════════════════════════════════ */

    /**
     * Configure and start a secure session.
     * Call ONCE at the top of every page (before any output).
     */
    public static function startSecureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,   // only over HTTPS in production
            'httponly' => true,        // no JS access (ABDM requirement)
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_only_cookies',    '1');
        ini_set('session.use_trans_sid',       '0'); // never in URLs
        ini_set('session.cookie_httponly',     '1');
        ini_set('session.gc_maxlifetime',      (string)self::SESSION_IDLE_SECONDS);
        ini_set('session.use_strict_mode',     '1');

        session_start();

        // Idle timeout check
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > self::SESSION_IDLE_SECONDS) {
                self::destroySession();
                return;
            }
        }
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Regenerate session ID on login (ABDM: new ID after authentication).
     * Keeps existing data, invalidates old ID.
     */
    public static function regenerateSession(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_activity'] = time();
        $_SESSION['_created']       = time();
    }

    /**
     * Fully destroy the session (logout).
     */
    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    /**
     * Check whether the current session is authenticated and not idle.
     * Returns false on expiry — caller should redirect to login.
     */
    public static function isSessionValid(string $flag = 'logged_in'): bool
    {
        if (empty($_SESSION[$flag])) return false;
        if (isset($_SESSION['_last_activity'])
            && time() - $_SESSION['_last_activity'] > self::SESSION_IDLE_SECONDS) {
            self::destroySession();
            return false;
        }
        $_SESSION['_last_activity'] = time();
        return true;
    }

    /* ═══════════════════════════════════════════════
       CSRF PROTECTION
       ABDM: Prevent cross-site request forgery on all state-changing requests.
    ═══════════════════════════════════════════════ */

    /** Generate (or return cached) CSRF token for the current session. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /** Validate CSRF token from request. Call before processing any POST. */
    public static function verifyCsrf(string $token): bool
    {
        $stored = $_SESSION['_csrf_token'] ?? '';
        return $stored !== '' && hash_equals($stored, $token);
    }

    /** Rotate CSRF token (call after login / privilege change). */
    public static function rotateCsrf(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    /* ═══════════════════════════════════════════════
       RATE LIMITING
       ABDM: Enforce per-user/IP limits; stricter on sensitive endpoints.
    ═══════════════════════════════════════════════ */

    /**
     * Check whether a key (IP, user ID, endpoint) is rate-limited.
     *
     * Uses PHP session-based sliding window.
     * $key       — unique string (e.g. "abdm_otp_127.0.0.1")
     * $maxHits   — max allowed hits in $windowSeconds
     * $windowSec — rolling window in seconds
     *
     * Returns true if limit exceeded (block the request).
     */
    public static function isRateLimited(string $key, int $maxHits = 5, int $windowSec = 300): bool
    {
        $now   = time();
        $store = $_SESSION['_rl'][$key] ?? [];

        // Drop entries outside the window
        $store = array_filter($store, fn($ts) => $ts > $now - $windowSec);

        if (count($store) >= $maxHits) return true;

        $store[] = $now;
        $_SESSION['_rl'][$key] = array_values($store);
        return false;
    }

    /**
     * Clear rate-limit counter for a key (e.g. after successful auth).
     */
    public static function clearRateLimit(string $key): void
    {
        unset($_SESSION['_rl'][$key]);
    }

    /**
     * Build a composite rate-limit key.
     * e.g. Security::rlKey('abdm_otp', $_SERVER['REMOTE_ADDR'])
     */
    public static function rlKey(string ...$parts): string
    {
        return implode('_', array_map('md5', $parts));
    }

    /* ═══════════════════════════════════════════════
       PASSWORD SECURITY
       ABDM: Bcrypt salted hashes; never MD5/SHA1; never store plain text.
    ═══════════════════════════════════════════════ */

    /** Hash a password with bcrypt (cost 12). */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /** Verify a password against a bcrypt hash. */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Check if the hash needs rehashing (cost upgrade). */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /* ═══════════════════════════════════════════════
       TOKEN LIFETIME (ABDM Guideline)
       Admin tokens: max 12 hours.
       User tokens : max 30 hours.
    ═══════════════════════════════════════════════ */

    /**
     * Store a token with its expiry in the session.
     * $role: 'admin' → 12 h TTL, everything else → 30 h TTL.
     */
    public static function storeToken(string $key, string $token, string $role = 'user'): void
    {
        $ttl = ($role === 'admin') ? self::TOKEN_TTL_ADMIN_SECONDS : self::TOKEN_TTL_USER_SECONDS;
        $_SESSION[$key]            = $token;
        $_SESSION[$key . '_exp']   = time() + $ttl;
    }

    /**
     * Retrieve a token if it is still within its lifetime.
     * Returns null if missing or expired.
     */
    public static function getToken(string $key): ?string
    {
        if (empty($_SESSION[$key]) || empty($_SESSION[$key . '_exp'])) return null;
        if (time() > (int)$_SESSION[$key . '_exp']) {
            unset($_SESSION[$key], $_SESSION[$key . '_exp']);
            return null;
        }
        return $_SESSION[$key];
    }

    /* ═══════════════════════════════════════════════
       SAFE ERROR RESPONSES
       ABDM: Never expose internals, stack traces, or account secrets.
    ═══════════════════════════════════════════════ */

    /**
     * Return a safe JSON error to the client.
     * $internalMsg is logged only; never sent to the browser.
     */
    public static function jsonError(string $userMsg, string $internalMsg = '', int $httpCode = 400): void
    {
        if ($internalMsg) {
            error_log('[SECURITY] ' . $internalMsg);
        }
        http_response_code($httpCode);
        echo json_encode(['success' => false, 'message' => $userMsg]);
        exit;
    }

    /**
     * Generic error message for auth failures.
     * ABDM: Never reveal whether username or password was wrong.
     */
    public static function genericAuthError(): string
    {
        return 'Invalid credentials. Please check your details and try again.';
    }

    /* ═══════════════════════════════════════════════
       MISCELLANEOUS HELPERS
    ═══════════════════════════════════════════════ */

    /** Get the real client IP (handles proxy headers). */
    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    /** Generate a cryptographically secure random string (hex). */
    public static function randomHex(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Constant-time string comparison (prevent timing attacks). */
    public static function safeEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /** Add security headers to the current response. */
    public static function setSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // Cache-control for sensitive pages
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
