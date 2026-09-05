<?php

/**
 * HiRequest — DB side of ABDM HI health-information requests (Phase A).
 *
 * Owns `abha_hi_requests` (database/migration_abdm_hi_consent.sql). Phase A
 * only records the request as 'acknowledged' and keeps the HIU key material
 * for Phase B (FHIR build → Fidelius encrypt → POST to dataPushUrl).
 *
 * Static methods, `mysqli $conn` first — mirrors lib/HipLinking.php / HiConsent.
 */
class HiRequest
{
    /**
     * Record an acknowledged HI request (idempotent on transaction_id when
     * ABDM sends one).
     *
     * $f keys: transaction_id(?), request_id(?), consent_id,
     * date_range_from(?), date_range_to(?), data_push_url(?),
     * key_material (JSON string — HIU dhPublicKey + nonce + curve).
     *
     * @return int abha_hi_requests.id (0 on failure)
     */
    public static function insertAcknowledged(mysqli $conn, array $f): int
    {
        $params = [
            $f['transaction_id']  ?? null,
            $f['request_id']      ?? null,
            $f['consent_id']      ?? '',
            $f['date_range_from'] ?? null,
            $f['date_range_to']   ?? null,
            $f['data_push_url']   ?? null,
            $f['key_material']    ?? '{}',
        ];

        $st = $conn->prepare(
            "INSERT INTO abha_hi_requests
               (transaction_id, request_id, consent_id, status,
                date_range_from, date_range_to, data_push_url, key_material)
             VALUES (?, ?, ?, 'acknowledged', ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               status = 'acknowledged', request_id = VALUES(request_id),
               consent_id = VALUES(consent_id), date_range_from = VALUES(date_range_from),
               date_range_to = VALUES(date_range_to), data_push_url = VALUES(data_push_url),
               key_material = VALUES(key_material), error_detail = NULL"
        );
        $st->bind_param(str_repeat('s', count($params)), ...$params);
        $ok  = $st->execute();
        $id  = (int) $conn->insert_id;
        $st->close();
        // ON DUPLICATE ... UPDATE leaves insert_id 0 — still a success.
        return $ok ? ($id ?: 1) : 0;
    }

    public static function findByTransaction(mysqli $conn, string $txnId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abha_hi_requests WHERE transaction_id = ? LIMIT 1");
        $st->bind_param('s', $txnId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }
}
