<?php

/**
 * HipLinking — DB side of ABDM HIP-Initiated Linking.
 *
 * Keeps lib/HipApi.php a pure API client. Owns:
 *   abdm_link_tokens        — per-patient link token (async via webhook)
 *   abdm_care_context_links — per-prescription care-context link
 *   abdm_webhook_log        — every raw callback, saved before processing
 * (database/migration_abdm_hip_linking.sql)
 *
 * Static methods, `mysqli $conn` first — mirrors lib/HprVerification.php.
 */
class HipLinking
{
    /** Link tokens are valid ~6 months. */
    public const TOKEN_TTL_SECONDS = 15552000; // 180 days

    /**
     * A fresh REQUEST-ID (UUID v4). Generated up front so the pending row and
     * the eventual HipApi call + webhook all key off the same value.
     */
    public static function newRequestId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /* ─────────────────── webhook side ─────────────────── */

    /**
     * Persist a raw callback BEFORE any parsing/validation. The payload is
     * length-capped so a flood can't blow up the row / table.
     *
     * @return int abdm_webhook_log.id
     */
    public static function logWebhook(mysqli $conn, ?string $requestId, string $callbackType, string $rawPayload): int
    {
        $rawPayload = mb_substr($rawPayload, 0, 65535);
        $requestId  = $requestId !== null ? mb_substr($requestId, 0, 64) : null;
        $callbackType = mb_substr($callbackType, 0, 60);

        $st = $conn->prepare(
            "INSERT INTO abdm_webhook_log (request_id, callback_type, raw_payload, processed)
             VALUES (?, ?, ?, 0)"
        );
        $st->bind_param('sss', $requestId, $callbackType, $rawPayload);
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return $id;
    }

    /** Mark a webhook-log row done (whether it caused a state change or not). */
    public static function markWebhookProcessed(mysqli $conn, int $logId): void
    {
        $st = $conn->prepare("UPDATE abdm_webhook_log SET processed = 1 WHERE id = ?");
        $st->bind_param('i', $logId);
        $st->execute();
        $st->close();
    }

    /**
     * Idempotency: has a prior callback for this request_id already moved the
     * target record to a terminal state?
     */
    public static function alreadyHandled(mysqli $conn, string $requestId): bool
    {
        $tok = self::findLinkTokenByRequest($conn, $requestId);
        if ($tok) {
            return in_array($tok['status'], ['received', 'expired'], true);
        }
        $cc = self::findCareContextByRequest($conn, $requestId);
        if ($cc) {
            return in_array($cc['status'], ['linked', 'failed'], true);
        }
        return false; // no matching pending record — caller rejects
    }

    public static function findLinkTokenByRequest(mysqli $conn, string $requestId): ?array
    {
        $st = $conn->prepare(
            "SELECT id, patient_id, abha_address, link_token, request_id, status, expires_at
             FROM abdm_link_tokens WHERE request_id = ? LIMIT 1"
        );
        $st->bind_param('s', $requestId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    public static function findCareContextByRequest(mysqli $conn, string $requestId): ?array
    {
        $st = $conn->prepare(
            "SELECT id, prescription_id, care_context_reference, hi_type, request_id, status
             FROM abdm_care_context_links WHERE request_id = ? LIMIT 1"
        );
        $st->bind_param('s', $requestId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /** linkToken callback → store the token, move pending → received. */
    public static function applyLinkToken(mysqli $conn, string $requestId, string $linkToken): bool
    {
        $st = $conn->prepare(
            "UPDATE abdm_link_tokens
             SET link_token = ?, status = 'received'
             WHERE request_id = ? AND status = 'pending'"
        );
        $st->bind_param('ss', $linkToken, $requestId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    /** linking-status callback → linked | failed (non-terminal rows only). */
    public static function applyLinkingStatus(mysqli $conn, string $requestId, bool $success): bool
    {
        $status = $success ? 'linked' : 'failed';
        $st = $conn->prepare(
            "UPDATE abdm_care_context_links
             SET status = ?, webhook_received_at = NOW()
             WHERE request_id = ? AND status = 'pending'"
        );
        $st->bind_param('ss', $status, $requestId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    /* ─────────────────── send side (used by Task 4) ─────────────────── */

    /** Record a generate-token request (status 'pending'). */
    public static function startLinkToken(mysqli $conn, int $patientId, string $abhaAddress, string $requestId, ?int $expiresAtTs = null): int
    {
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs ?? (time() + self::TOKEN_TTL_SECONDS));
        $st = $conn->prepare(
            "INSERT INTO abdm_link_tokens (patient_id, abha_address, request_id, status, expires_at)
             VALUES (?, ?, ?, 'pending', ?)"
        );
        $st->bind_param('isss', $patientId, $abhaAddress, $requestId, $expiresAt);
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return $id;
    }

    /** A usable (received, unexpired) link token for a patient, or null. */
    public static function activeLinkToken(mysqli $conn, int $patientId): ?array
    {
        $st = $conn->prepare(
            "SELECT id, link_token, abha_address, expires_at
             FROM abdm_link_tokens
             WHERE patient_id = ? AND status = 'received'
               AND link_token IS NOT NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $st->bind_param('i', $patientId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /** Record a link/carecontext request (status 'pending'). */
    public static function startCareContextLink(
        mysqli $conn,
        int $prescriptionId,
        string $referenceNumber,
        string $careContextReference,
        string $hiType,
        string $requestId
    ): int {
        $st = $conn->prepare(
            "INSERT INTO abdm_care_context_links
               (prescription_id, reference_number, care_context_reference, hi_type, request_id, status)
             VALUES (?, ?, ?, ?, ?, 'pending')"
        );
        $st->bind_param('issss', $prescriptionId, $referenceNumber, $careContextReference, $hiType, $requestId);
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return $id;
    }

    /** Has this prescription already been linked (or is a link in flight)? */
    public static function careContextLinkFor(mysqli $conn, int $prescriptionId): ?array
    {
        $st = $conn->prepare(
            "SELECT id, request_id, status, care_context_reference
             FROM abdm_care_context_links WHERE prescription_id = ? ORDER BY id DESC LIMIT 1"
        );
        $st->bind_param('i', $prescriptionId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /** Opportunistic housekeeping — expire link tokens past their window. */
    public static function expireStaleTokens(mysqli $conn): int
    {
        $conn->query("UPDATE abdm_link_tokens SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");
        return $conn->affected_rows;
    }
}
