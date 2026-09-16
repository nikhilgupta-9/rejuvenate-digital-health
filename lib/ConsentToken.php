<?php
/**
 * HMAC-signed, per-student, expiring token for the parent-consent link
 * (school/parent-consent.php?ctoken=...). Ties a link cryptographically to
 * one school_members row instead of a generic, school-wide link that had
 * no way to identify which student a submission was actually for — see
 * database/migration_parent_consent_secure_link.sql.
 *
 * Deliberately distinct from parent_consent_forms.token, an unrelated
 * Razorpay-payment-resume token created after submission — never confuse
 * the two or reuse that column/param name here.
 */

/**
 * @throws RuntimeException if CONSENT_SIGNING_KEY isn't configured — fails
 *         closed rather than ever signing with a weak/default secret.
 */
function consent_signing_key(): string
{
    $key = $_ENV['CONSENT_SIGNING_KEY'] ?? '';
    if ($key === '') {
        throw new RuntimeException('CONSENT_SIGNING_KEY is not configured in .env');
    }
    return $key;
}

function consent_generate_token(int $memberId, int $schoolId, int $ttlHours = 72): string
{
    $expires = time() + ($ttlHours * 3600);
    $payload = "{$memberId}.{$schoolId}.{$expires}";
    $sig = hash_hmac('sha256', $payload, consent_signing_key());
    return consent_b64url_encode("{$payload}.{$sig}");
}

/**
 * @return array{member_id:int,school_id:int}|null null on any invalid,
 *         tampered, or expired token — callers must re-fetch the member
 *         row themselves rather than trusting these ids alone.
 */
function consent_verify_token(string $token): ?array
{
    $decoded = consent_b64url_decode($token);
    if ($decoded === null) {
        return null;
    }

    $parts = explode('.', $decoded);
    if (count($parts) !== 4) {
        return null;
    }
    [$memberId, $schoolId, $expires, $sig] = $parts;

    if (!ctype_digit($memberId) || !ctype_digit($schoolId) || !ctype_digit($expires)) {
        return null;
    }

    $expected = hash_hmac('sha256', "{$memberId}.{$schoolId}.{$expires}", consent_signing_key());
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    if ((int) $expires < time()) {
        return null;
    }

    return ['member_id' => (int) $memberId, 'school_id' => (int) $schoolId];
}

/** URL-safe base64 — raw base64's +/= break when a link is shared via WhatsApp/QR. */
function consent_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function consent_b64url_decode(string $data): ?string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64, true);
    return $decoded === false ? null : $decoded;
}
