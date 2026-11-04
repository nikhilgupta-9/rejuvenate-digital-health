<?php

/**
 * HiuConsent — DB side of ABDM Milestone 3, Consent flow (HIU role).
 *
 * Owns `abdm_hiu_consent_requests` and `abdm_hiu_consent_artefacts`
 * (database/migration_abdm_m3_hiu_consent.sql). Our own system is the
 * requester here — the reverse direction of M2's abdm_care_context_links,
 * which is why this is a separate table rather than reusing that one.
 *
 * Static methods, `mysqli $conn` first — mirrors lib/HipLinking.php / HiRequest.php.
 */
class HiuConsent
{
    /** A fresh REQUEST-ID (UUID v4), same shape as HipLinking::newRequestId(). */
    public static function newRequestId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * Record a new consent request (status 'requested') right before calling
     * ConsentApi::hiuConsentRequestInit(). $f keys: request_id, doctor_id,
     * patient_id(?), abha_address, hip_id(?), purpose_code, purpose_text,
     * hi_types (array), date_from, date_to, expiry_at(?).
     *
     * @return int abdm_hiu_consent_requests.id
     */
    public static function insertRequested(mysqli $conn, array $f): int
    {
        $doctorId  = $f['doctor_id'] ?? null;
        $patientId = $f['patient_id'] ?? null;
        $hipId     = $f['hip_id'] ?? null;
        $expiryAt  = $f['expiry_at'] ?? null;
        $hiTypes   = json_encode($f['hi_types'] ?? []);

        $st = $conn->prepare(
            "INSERT INTO abdm_hiu_consent_requests
               (request_id, doctor_id, patient_id, abha_address, hip_id,
                purpose_code, purpose_text, hi_types, date_from, date_to, expiry_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'requested')"
        );
        $st->bind_param(
            'siissssssss',
            $f['request_id'],
            $doctorId,
            $patientId,
            $f['abha_address'],
            $hipId,
            $f['purpose_code'],
            $f['purpose_text'],
            $hiTypes,
            $f['date_from'],
            $f['date_to'],
            $expiryAt
        );
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return $id;
    }

    public static function findByRequestId(mysqli $conn, string $requestId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_consent_requests WHERE request_id = ? LIMIT 1");
        $st->bind_param('s', $requestId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    public static function findById(mysqli $conn, int $id): ?array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_consent_requests WHERE id = ? LIMIT 1");
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    public static function findByConsentRequestId(mysqli $conn, string $consentRequestId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_consent_requests WHERE consent_request_id = ? LIMIT 1");
        $st->bind_param('s', $consentRequestId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /** on-init callback: attach ABDM's consentRequest.id to our row. */
    public static function attachConsentRequestId(mysqli $conn, string $requestId, string $consentRequestId): bool
    {
        $st = $conn->prepare(
            "UPDATE abdm_hiu_consent_requests SET consent_request_id = ?
             WHERE request_id = ? AND consent_request_id IS NULL"
        );
        $st->bind_param('ss', $consentRequestId, $requestId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    /** notify callback: GRANTED / DENIED / REVOKED (status is upper-cased by ABDM). */
    public static function applyNotifyStatus(mysqli $conn, string $consentRequestId, string $abdmStatus, ?string $reason = null): bool
    {
        $map = ['GRANTED' => 'granted', 'DENIED' => 'denied', 'REVOKED' => 'revoked'];
        $status = $map[strtoupper($abdmStatus)] ?? 'failed';

        $st = $conn->prepare(
            "UPDATE abdm_hiu_consent_requests SET status = ?, reason = ?
             WHERE consent_request_id = ?"
        );
        $st->bind_param('sss', $status, $reason, $consentRequestId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    /** One pending_fetch row per consent artefact id granted for this request. */
    public static function insertArtefactsPending(mysqli $conn, int $hiuConsentRequestId, array $consentIds): void
    {
        $st = $conn->prepare(
            "INSERT INTO abdm_hiu_consent_artefacts (hiu_consent_request_id, consent_id, status)
             VALUES (?, ?, 'pending_fetch')
             ON DUPLICATE KEY UPDATE hiu_consent_request_id = VALUES(hiu_consent_request_id)"
        );
        foreach ($consentIds as $cid) {
            $cid = trim((string) $cid);
            if ($cid === '') continue;
            $st->bind_param('is', $hiuConsentRequestId, $cid);
            $st->execute();
        }
        $st->close();
    }

    public static function findArtefactByConsentId(mysqli $conn, string $consentId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_consent_artefacts WHERE consent_id = ? LIMIT 1");
        $st->bind_param('s', $consentId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /** on-fetch callback: store the full artefact payload for the later data-flow phase. */
    public static function applyFetchedArtefact(mysqli $conn, string $consentId, array $rawArtefact): bool
    {
        $hipId = trim((string) ($rawArtefact['hip']['id'] ?? $rawArtefact['hip'] ?? ''));
        $json  = json_encode($rawArtefact);

        $st = $conn->prepare(
            "UPDATE abdm_hiu_consent_artefacts
             SET status = 'fetched', hip_id = ?, artefact_json = ?, fetched_at = NOW()
             WHERE consent_id = ?"
        );
        $st->bind_param('sss', $hipId, $json, $consentId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    /** Pending artefacts this request is still waiting to fetch. */
    public static function pendingArtefactsFor(mysqli $conn, int $hiuConsentRequestId): array
    {
        $st = $conn->prepare(
            "SELECT * FROM abdm_hiu_consent_artefacts WHERE hiu_consent_request_id = ? AND status = 'pending_fetch'"
        );
        $st->bind_param('i', $hiuConsentRequestId);
        $st->execute();
        return $st->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
