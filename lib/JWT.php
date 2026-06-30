<?php
/**
 * Minimal HS256 JWT — no external dependency.
 * Supports: issue, verify, refresh.
 */
class JWT
{
    private static function b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * Issue a signed JWT.
     * @param array  $payload  Claims (do NOT include iat/exp — passed separately)
     * @param string $secret
     * @param int    $ttl      Seconds until expiry
     */
    public static function issue(array $payload, string $secret, int $ttl = 900): string
    {
        $header  = self::b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;
        $payload['jti'] = bin2hex(random_bytes(16));
        $body    = self::b64url_encode(json_encode($payload));
        $sig     = self::b64url_encode(hash_hmac('sha256', "$header.$body", $secret, true));
        return "$header.$body.$sig";
    }

    /**
     * Verify and decode a JWT.
     * Throws RuntimeException on failure.
     * @return array Decoded payload
     */
    public static function verify(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Invalid JWT structure');

        [$header, $body, $sig] = $parts;
        $expected = self::b64url_encode(hash_hmac('sha256', "$header.$body", $secret, true));

        if (!hash_equals($expected, $sig)) throw new RuntimeException('Invalid JWT signature');

        $payload = json_decode(self::b64url_decode($body), true);
        if (!$payload) throw new RuntimeException('Invalid JWT payload');
        if (($payload['exp'] ?? 0) < time()) throw new RuntimeException('JWT expired');

        return $payload;
    }

    /**
     * Decode without verifying (for reading claims before full verify).
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Invalid JWT structure');
        $payload = json_decode(self::b64url_decode($parts[1]), true);
        if (!$payload) throw new RuntimeException('Invalid JWT payload');
        return $payload;
    }
}
