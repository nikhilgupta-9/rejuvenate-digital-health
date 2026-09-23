<?php
/**
 * HMAC-signed, expiring token for the parent booking-approval link
 * (school/parent-booking-approval.php?token=...). Ties the link to one
 * school_booking_holds row — a student-initiated OPD booking request that
 * is not a real appointment yet (see database/migration_school_membership_phase2.sql).
 *
 * Deliberately its own signing key, separate from lib/ConsentToken.php —
 * a consent link and a payment-approval link must never be interchangeable.
 */

/**
 * @throws RuntimeException if BOOKING_SIGNING_KEY isn't configured — fails
 *         closed rather than ever signing with a weak/default secret.
 */
function booking_signing_key(): string
{
    $key = $_ENV['BOOKING_SIGNING_KEY'] ?? '';
    if ($key === '') {
        throw new RuntimeException('BOOKING_SIGNING_KEY is not configured in .env');
    }
    return $key;
}

function booking_generate_token(int $holdId, int $ttlHours = 72): string
{
    $expires = time() + ($ttlHours * 3600);
    $payload = "{$holdId}.{$expires}";
    $sig = hash_hmac('sha256', $payload, booking_signing_key());
    return booking_b64url_encode("{$payload}.{$sig}");
}

/** @return int|null the hold_id, or null on any invalid, tampered, or expired token. */
function booking_verify_token(string $token): ?int
{
    $decoded = booking_b64url_decode($token);
    if ($decoded === null) {
        return null;
    }

    $parts = explode('.', $decoded);
    if (count($parts) !== 3) {
        return null;
    }
    [$holdId, $expires, $sig] = $parts;

    if (!ctype_digit($holdId) || !ctype_digit($expires)) {
        return null;
    }

    $expected = hash_hmac('sha256', "{$holdId}.{$expires}", booking_signing_key());
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    if ((int) $expires < time()) {
        return null;
    }

    return (int) $holdId;
}

/** URL-safe base64 — raw base64's +/= break when a link is shared via WhatsApp/QR. */
function booking_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function booking_b64url_decode(string $data): ?string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64, true);
    return $decoded === false ? null : $decoded;
}
