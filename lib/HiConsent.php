<?php

/**
 * HiConsent — DB side of ABDM HI Consent (Phase A: receive + acknowledge).
 *
 * Keeps telemedicine/api/abdm-consent-webhook.php a thin receiver. Owns
 * `abha_consents` (database/migration_abdm_hi_consent.sql).
 *
 * Static methods, `mysqli $conn` first — mirrors lib/HipLinking.php.
 */
class HiConsent
{
    /**
     * GRANTED consent-notify → insert or refresh, idempotent on consent_id
     * (a re-notify just overwrites — ABDM may resend).
     *
     * $f keys: consent_id, patient_id(?int), abha_address, hiu_id,
     * purpose_text, purpose_code, hi_types(JSON string), date_range_from,
     * date_range_to, data_erase_at (all DATETIME strings or null),
     * frequency_unit, frequency_value(?int), frequency_repeats(?int),
     * signature, grant_acknowledgement, raw_payload (JSON string).
     */
    public static function upsertGranted(mysqli $conn, array $f): bool
    {
        $params = [
            $f['consent_id']            ?? '',
            $f['patient_id']            ?? null,
            $f['abha_address']          ?? '',
            $f['hiu_id']                ?? '',
            $f['purpose_text']          ?? '',
            $f['purpose_code']          ?? '',
            $f['hi_types']              ?? null,
            $f['date_range_from']       ?? null,
            $f['date_range_to']         ?? null,
            $f['data_erase_at']         ?? null,
            $f['frequency_unit']        ?? '',
            $f['frequency_value']       ?? null,
            $f['frequency_repeats']     ?? null,
            $f['signature']             ?? '',
            $f['grant_acknowledgement'] ?? '',
            $f['raw_payload']           ?? '{}',
        ];
        // NULL binds fine regardless of declared type, so infer purely for
        // readability — every param is safe as a string bind.
        $types = str_repeat('s', count($params));

        $st = $conn->prepare(
            "INSERT INTO abha_consents
               (consent_id, status, patient_id, abha_address, hiu_id, purpose_text, purpose_code,
                hi_types, date_range_from, date_range_to, data_erase_at,
                frequency_unit, frequency_value, frequency_repeats,
                signature, grant_acknowledgement, raw_payload)
             VALUES (?, 'granted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               status = 'granted', patient_id = VALUES(patient_id), abha_address = VALUES(abha_address),
               hiu_id = VALUES(hiu_id), purpose_text = VALUES(purpose_text), purpose_code = VALUES(purpose_code),
               hi_types = VALUES(hi_types), date_range_from = VALUES(date_range_from),
               date_range_to = VALUES(date_range_to), data_erase_at = VALUES(data_erase_at),
               frequency_unit = VALUES(frequency_unit), frequency_value = VALUES(frequency_value),
               frequency_repeats = VALUES(frequency_repeats), signature = VALUES(signature),
               grant_acknowledgement = VALUES(grant_acknowledgement), raw_payload = VALUES(raw_payload)"
        );
        $st->bind_param($types, ...$params);
        $ok = $st->execute();
        $st->close();
        return (bool) $ok;
    }

    /**
     * REVOKED consent-notify → flip an EXISTING row to revoked. Per spec,
     * does NOT create a row if the consentId is unknown to us.
     */
    public static function markRevoked(mysqli $conn, string $consentId): bool
    {
        $st = $conn->prepare("SELECT id, status FROM abha_consents WHERE consent_id = ? LIMIT 1");
        $st->bind_param('s', $consentId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) return false; // unknown consentId — nothing to revoke

        $upd = $conn->prepare("UPDATE abha_consents SET status = 'revoked' WHERE consent_id = ?");
        $upd->bind_param('s', $consentId);
        $upd->execute();
        $upd->close();
        return true;
    }

    public static function findByConsentId(mysqli $conn, string $consentId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abha_consents WHERE consent_id = ? LIMIT 1");
        $st->bind_param('s', $consentId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /**
     * Can a HI data request against this consent be served?
     * granted + not past data_erase_at.
     *
     * @return array{consent:?array, ok:bool, reason:string}
     */
    public static function servable(mysqli $conn, string $consentId): array
    {
        $c = self::findByConsentId($conn, $consentId);
        if (!$c) {
            return ['consent' => null, 'ok' => false, 'reason' => 'consent not found'];
        }
        if (($c['status'] ?? '') !== 'granted') {
            return ['consent' => $c, 'ok' => false, 'reason' => 'consent ' . ($c['status'] ?? 'invalid')];
        }
        if (!empty($c['data_erase_at']) && strtotime((string) $c['data_erase_at']) < time()) {
            return ['consent' => $c, 'ok' => false, 'reason' => 'consent expired'];
        }
        return ['consent' => $c, 'ok' => true, 'reason' => 'ok'];
    }
}
