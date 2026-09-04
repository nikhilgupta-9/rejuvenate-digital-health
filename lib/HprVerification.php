<?php

/**
 * HprVerification — DB side of the doctor HPR-ID verification flow.
 *
 * Keeps lib/HprApi.php a pure API client (it never touches the DB). This
 * class owns the `hpr_verification_txns` table
 * (database/migration_hpr_verification.sql):
 *
 *   id, doctor_id, txn_id (unique), status, created_at, expires_at
 *   status: pending -> authenticated -> verified   (or failed / expired)
 *
 * All methods are static and take `mysqli $conn` first, mirroring
 * lib/AbhaPatientResolver.php.
 */
class HprVerification
{
    /** Statuses that are terminal — never move a row out of these. */
    private const TERMINAL = ['verified', 'failed', 'expired'];

    /**
     * Record a freshly-generated Aadhaar link.
     *
     * @param int $expiresAtTs  unix timestamp (HprApi::generateAadhaarLink()
     *                          returns this as 'expiresAt' — link is 5 min).
     * @return int  hpr_verification_txns.id
     */
    public static function start(mysqli $conn, int $doctorId, string $txnId, int $expiresAtTs): int
    {
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);

        // A doctor can only have one live attempt — expire any older pending ones.
        $stale = $conn->prepare(
            "UPDATE hpr_verification_txns SET status = 'expired'
             WHERE doctor_id = ? AND status IN ('pending', 'authenticated')"
        );
        $stale->bind_param('i', $doctorId);
        $stale->execute();
        $stale->close();

        $ins = $conn->prepare(
            "INSERT INTO hpr_verification_txns (doctor_id, txn_id, status, expires_at)
             VALUES (?, ?, 'pending', ?)"
        );
        $ins->bind_param('iss', $doctorId, $txnId, $expiresAt);
        $ins->execute();
        $id = (int) $conn->insert_id;
        $ins->close();

        return $id;
    }

    /**
     * Move a txn to a new status. Refuses to change a row that is already
     * in a terminal state.
     *
     * @param string $status  authenticated | verified | failed | expired
     * @return bool  true if a row was actually updated
     */
    public static function setStatus(mysqli $conn, string $txnId, string $status): bool
    {
        $allowed = ['pending', 'authenticated', 'verified', 'failed', 'expired'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $terminalList = "'" . implode("','", self::TERMINAL) . "'";
        $upd = $conn->prepare(
            "UPDATE hpr_verification_txns
             SET status = ?
             WHERE txn_id = ? AND status NOT IN ($terminalList)"
        );
        $upd->bind_param('ss', $status, $txnId);
        $upd->execute();
        $changed = $upd->affected_rows > 0;
        $upd->close();

        return $changed;
    }

    /**
     * The most recent txn for a doctor (any status), or null.
     * Used to render the current state on doctor/my-contact.php.
     */
    public static function latest(mysqli $conn, int $doctorId): ?array
    {
        $s = $conn->prepare(
            "SELECT id, doctor_id, txn_id, status, created_at, expires_at
             FROM hpr_verification_txns
             WHERE doctor_id = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $s->bind_param('i', $doctorId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc() ?: null;
        $s->close();

        return $row;
    }

    /**
     * Fetch one txn by id, optionally scoped to a doctor (so one doctor
     * can't poll another's transaction). Returns null if not found / not theirs.
     */
    public static function get(mysqli $conn, string $txnId, ?int $doctorId = null): ?array
    {
        if ($doctorId !== null) {
            $s = $conn->prepare(
                "SELECT id, doctor_id, txn_id, status, created_at, expires_at
                 FROM hpr_verification_txns WHERE txn_id = ? AND doctor_id = ? LIMIT 1"
            );
            $s->bind_param('si', $txnId, $doctorId);
        } else {
            $s = $conn->prepare(
                "SELECT id, doctor_id, txn_id, status, created_at, expires_at
                 FROM hpr_verification_txns WHERE txn_id = ? LIMIT 1"
            );
            $s->bind_param('s', $txnId);
        }
        $s->execute();
        $row = $s->get_result()->fetch_assoc() ?: null;
        $s->close();

        return $row;
    }

    /** True once the link's 5-minute window has passed. */
    public static function isExpired(array $txn): bool
    {
        return !empty($txn['expires_at']) && strtotime($txn['expires_at']) < time();
    }

    /**
     * Bulk-expire any still-pending/authenticated txns whose window has
     * closed. Safe to call opportunistically (e.g. at the top of the poll
     * endpoint) or from cron.
     *
     * @return int rows expired
     */
    public static function expireStale(mysqli $conn): int
    {
        $conn->query(
            "UPDATE hpr_verification_txns
             SET status = 'expired'
             WHERE status IN ('pending', 'authenticated') AND expires_at < NOW()"
        );
        return $conn->affected_rows;
    }
}
