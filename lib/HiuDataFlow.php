<?php

/**
 * HiuDataFlow — DB side of ABDM Milestone 3, Data flow (HIU role).
 *
 * Owns `abdm_hiu_data_requests` and `abdm_hiu_received_records`
 * (database/migration_abdm_m3_hiu_data_flow.sql). Runs after a consent
 * artefact has been fetched (lib/HiuConsent.php) — this is what actually
 * asks the HIP for the records and stores what comes back.
 *
 * Static methods, `mysqli $conn` first — mirrors lib/HiuConsent.php.
 */
class HiuDataFlow
{
    public static function newRequestId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * Record a new data-flow request (status 'requested') right before
     * calling ConsentApi::hiuDataFlowRequest(). $f keys: artefact_id,
     * request_id, our_private_key (base64), our_nonce (base64),
     * date_from, date_to.
     *
     * @return int abdm_hiu_data_requests.id
     */
    public static function insertRequested(mysqli $conn, array $f): int
    {
        $st = $conn->prepare(
            "INSERT INTO abdm_hiu_data_requests
               (artefact_id, request_id, our_private_key, our_nonce, date_from, date_to, status)
             VALUES (?, ?, ?, ?, ?, ?, 'requested')"
        );
        $st->bind_param(
            'isssss',
            $f['artefact_id'],
            $f['request_id'],
            $f['our_private_key'],
            $f['our_nonce'],
            $f['date_from'],
            $f['date_to']
        );
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return $id;
    }

    public static function findByRequestId(mysqli $conn, string $requestId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_data_requests WHERE request_id = ? LIMIT 1");
        $st->bind_param('s', $requestId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    public static function findByTransactionId(mysqli $conn, string $transactionId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_data_requests WHERE transaction_id = ? LIMIT 1");
        $st->bind_param('s', $transactionId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /** on-request callback: attach ABDM's transactionId, or record the error. */
    public static function attachTransaction(mysqli $conn, string $requestId, ?string $transactionId, string $status, ?string $errorDetail = null): bool
    {
        $st = $conn->prepare(
            "UPDATE abdm_hiu_data_requests
             SET transaction_id = ?, status = ?, error_detail = ?
             WHERE request_id = ?"
        );
        $st->bind_param('ssss', $transactionId, $status, $errorDetail, $requestId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    public static function markStatus(mysqli $conn, string $transactionId, string $status, ?string $errorDetail = null): bool
    {
        $st = $conn->prepare(
            "UPDATE abdm_hiu_data_requests SET status = ?, error_detail = ? WHERE transaction_id = ?"
        );
        $st->bind_param('sss', $status, $errorDetail, $transactionId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        return $ok;
    }

    /** Store one decrypted FHIR bundle entry from the push. */
    public static function insertReceivedRecord(mysqli $conn, int $dataRequestId, ?string $careContextRef, string $fhirJson, string $hiStatus): int
    {
        $st = $conn->prepare(
            "INSERT INTO abdm_hiu_received_records (data_request_id, care_context_reference, fhir_bundle_json, hi_status)
             VALUES (?, ?, ?, ?)"
        );
        $st->bind_param('isss', $dataRequestId, $careContextRef, $fhirJson, $hiStatus);
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return $id;
    }

    public static function recordsForRequest(mysqli $conn, int $dataRequestId): array
    {
        $st = $conn->prepare("SELECT * FROM abdm_hiu_received_records WHERE data_request_id = ? ORDER BY id");
        $st->bind_param('i', $dataRequestId);
        $st->execute();
        return $st->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Records received for a given patient (joined through artefact → consent request). */
    public static function recordsForPatient(mysqli $conn, int $patientId): array
    {
        $st = $conn->prepare(
            "SELECT rr.*, dr.transaction_id, a.hip_id
             FROM abdm_hiu_received_records rr
             JOIN abdm_hiu_data_requests dr ON dr.id = rr.data_request_id
             JOIN abdm_hiu_consent_artefacts a ON a.id = dr.artefact_id
             JOIN abdm_hiu_consent_requests cr ON cr.id = a.hiu_consent_request_id
             WHERE cr.patient_id = ?
             ORDER BY rr.received_at DESC"
        );
        $st->bind_param('i', $patientId);
        $st->execute();
        return $st->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
