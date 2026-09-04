<?php
/**
 * Abha — the single access point for ABHA identity.
 *
 * Backed by the normalised `abha_accounts` table (one row per entity),
 * which is the AUTHORITATIVE source. See
 * database/migration_abha_accounts.sql and database/ABHA_MIGRATION_NOTES.md.
 *
 * TRANSITION: the deprecated per-entity columns
 *   users.{abha_id,abha_address,abha_linked,abha_linked_at,abha_verified}
 *   school_members.{ …same… , abha_profile_data}
 *   doctors.abha_id
 * are still written here (dual-write) so read sites that have not yet been
 * repointed keep showing correct data. A later migration drops those
 * columns and this dual-write.
 */
class Abha
{
    /** entity_type => legacy table + whether it has the full column set */
    private const LEGACY = [
        'patient'       => ['table' => 'users',          'full' => true],
        'school_member' => ['table' => 'school_members', 'full' => true],
        'doctor'        => ['table' => 'doctors',        'full' => false], // only abha_id
    ];

    /** Format 14 digits -> XX-XXXX-XXXX-XXXX; pass through anything else. */
    public static function formatNumber(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        $d = preg_replace('/\D/', '', $raw);
        if (strlen($d) === 14) {
            return substr($d, 0, 2) . '-' . substr($d, 2, 4) . '-' . substr($d, 6, 4) . '-' . substr($d, 10, 4);
        }
        return $raw;
    }

    /** Full abha_accounts row for an entity, or null. */
    public static function get(mysqli $conn, string $entityType, int $entityId): ?array
    {
        $st = $conn->prepare("SELECT * FROM abha_accounts WHERE entity_type = ? AND entity_id = ? LIMIT 1");
        $st->bind_param('si', $entityType, $entityId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /**
     * Upsert ABHA for an entity (authoritative write to abha_accounts +
     * mirror to the deprecated legacy columns).
     *
     * @param array $f keys (all optional): abha_number, abha_address,
     *        linked (bool), verified (bool), source, profile_data
     */
    public static function save(mysqli $conn, string $entityType, int $entityId, array $f): void
    {
        if (!isset(self::LEGACY[$entityType])) {
            throw new InvalidArgumentException("Abha::save unknown entity_type '$entityType'");
        }

        // Merge the caller's fields over whatever is already stored, so a
        // partial update (e.g. address only) keeps linked/verified/etc.
        $cur = self::get($conn, $entityType, $entityId) ?: [];

        $number   = array_key_exists('abha_number', $f)  ? self::formatNumber($f['abha_number'])          : ($cur['abha_number']  ?? null);
        $address  = array_key_exists('abha_address', $f) ? (trim((string) $f['abha_address']) ?: null)    : ($cur['abha_address'] ?? null);
        $linked   = array_key_exists('linked', $f)       ? (int) (bool) $f['linked']                       : (int) ($cur['linked']   ?? 1);
        $verified = array_key_exists('verified', $f)     ? (int) (bool) $f['verified']                     : (int) ($cur['verified'] ?? 0);
        $source   = array_key_exists('source', $f)       ? ($f['source'] ?: null)                          : ($cur['source'] ?? null);
        $profile  = array_key_exists('profile_data', $f) ? ($f['profile_data'] ?: null)                    : ($cur['profile_data'] ?? null);

        $now        = date('Y-m-d H:i:s');
        $curLinkedAt   = $cur['linked_at']   ?? null;
        $curVerifiedAt = $cur['verified_at'] ?? null;
        $linkedAt   = $linked   ? ($curLinkedAt   ?: $now) : $curLinkedAt;
        $verifiedAt = $verified ? ($curVerifiedAt ?: $now) : null;

        $sql = "INSERT INTO abha_accounts
                    (entity_type, entity_id, abha_number, abha_address, linked, verified, linked_at, verified_at, source, profile_data)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    abha_number  = VALUES(abha_number),
                    abha_address = VALUES(abha_address),
                    linked       = VALUES(linked),
                    verified     = VALUES(verified),
                    linked_at    = VALUES(linked_at),
                    verified_at  = VALUES(verified_at),
                    source       = VALUES(source),
                    profile_data = VALUES(profile_data)";
        $st = $conn->prepare($sql);
        $st->bind_param(
            'sissiissss',
            $entityType, $entityId, $number, $address,
            $linked, $verified, $linkedAt, $verifiedAt, $source, $profile
        );
        $st->execute();
        $st->close();

        self::mirrorLegacy($conn, $entityType, $entityId);
    }

    /** Unlink / remove an entity's ABHA (both tables). */
    public static function unlink(mysqli $conn, string $entityType, int $entityId): void
    {
        $del = $conn->prepare("DELETE FROM abha_accounts WHERE entity_type = ? AND entity_id = ?");
        $del->bind_param('si', $entityType, $entityId);
        $del->execute();
        $del->close();

        $legacy = self::LEGACY[$entityType] ?? null;
        if (!$legacy) return;
        $tbl = $legacy['table'];
        if ($legacy['full']) {
            $conn->query("UPDATE `$tbl` SET abha_id = NULL, abha_address = NULL, abha_linked = 0, abha_verified = 0, abha_linked_at = NULL WHERE id = " . (int) $entityId);
        } else {
            $conn->query("UPDATE `$tbl` SET abha_id = NULL WHERE id = " . (int) $entityId);
        }
    }

    /**
     * Reverse lookup by ABHA number OR @-address.
     * @return array|null the abha_accounts row (has entity_type + entity_id)
     */
    public static function find(mysqli $conn, string $abhaNumberOrAddress): ?array
    {
        $needle = trim($abhaNumberOrAddress);
        if ($needle === '') return null;

        $digits = preg_replace('/\D/', '', $needle);
        $byNumber = (strpos($needle, '@') === false && strlen($digits) === 14);
        $num = $byNumber ? self::formatNumber($needle) : null;

        if ($byNumber) {
            $st = $conn->prepare("SELECT * FROM abha_accounts WHERE abha_number = ? LIMIT 1");
            $st->bind_param('s', $num);
        } else {
            $st = $conn->prepare("SELECT * FROM abha_accounts WHERE abha_address = ? LIMIT 1");
            $st->bind_param('s', $needle);
        }
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        if ($row) return $row;

        // TRANSITION fallback: abha_accounts not populated yet — look in the
        // deprecated per-entity columns. Remove with the legacy-column drop.
        return self::findLegacy($conn, $byNumber ? $num : $needle, $byNumber);
    }

    private static function findLegacy(mysqli $conn, string $value, bool $byNumber): ?array
    {
        $col = $byNumber ? 'abha_id' : 'abha_address';
        foreach (['patient' => 'users', 'school_member' => 'school_members', 'doctor' => 'doctors'] as $type => $tbl) {
            if ($type === 'doctor' && !$byNumber) continue; // doctors has no abha_address
            $st = $conn->prepare("SELECT id, abha_id, abha_address FROM `$tbl` WHERE `$col` = ? LIMIT 1");
            $st->bind_param('s', $value);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) {
                return [
                    'entity_type'  => $type,
                    'entity_id'    => (int) $r['id'],
                    'abha_number'  => self::formatNumber($r['abha_id']),
                    'abha_address' => $r['abha_address'] ?? null,
                    'linked'       => 1,
                    'verified'     => 0,
                    '_legacy'      => true,
                ];
            }
        }
        return null;
    }

    /**
     * `LEFT JOIN abha_accounts` clause for an entity query.
     * $entityAlias is the aliased base table (e.g. 'u' for users);
     * the join is aliased $joinAlias (default 'aa').
     */
    public static function joinClause(string $entityType, string $entityAlias, string $joinAlias = 'aa'): string
    {
        $et = preg_replace('/[^a-z_]/', '', $entityType);
        return " LEFT JOIN abha_accounts $joinAlias ON $joinAlias.entity_type = '$et' AND $joinAlias.entity_id = $entityAlias.id ";
    }

    /**
     * SELECT-list fragment that exposes the ABHA fields under the legacy
     * array keys the templates already use — so a read site only needs the
     * join + this fragment, no downstream template change.
     *
     * $legacyAlias: pass the base-table alias (e.g. 'u' for users, 'sm' for
     * school_members — NOT 'd', doctors lacks the extra columns) to also
     * fall back to that table's deprecated abha_* columns while the data is
     * still being migrated into abha_accounts.
     */
    public static function selectAliases(string $joinAlias = 'aa', ?string $legacyAlias = null): string
    {
        if ($legacyAlias !== null) {
            $L = preg_replace('/[^A-Za-z0-9_]/', '', $legacyAlias);
            return " COALESCE($joinAlias.abha_number,  $L.abha_id)         AS abha_number,
                     COALESCE($joinAlias.abha_number,  $L.abha_id)         AS abha_id,
                     COALESCE($joinAlias.abha_address, $L.abha_address)    AS abha_address,
                     COALESCE($joinAlias.linked,       $L.abha_linked, 0)  AS abha_linked,
                     COALESCE($joinAlias.verified,     $L.abha_verified, 0) AS abha_verified,
                     COALESCE($joinAlias.linked_at,    $L.abha_linked_at)  AS abha_linked_at ";
        }
        return " $joinAlias.abha_number AS abha_number,
                 $joinAlias.abha_number AS abha_id,
                 $joinAlias.abha_address AS abha_address,
                 COALESCE($joinAlias.linked, 0)   AS abha_linked,
                 COALESCE($joinAlias.verified, 0) AS abha_verified,
                 $joinAlias.linked_at AS abha_linked_at ";
    }

    /* ── internal ── */

    /** Copy the authoritative abha_accounts row down into the legacy columns. */
    private static function mirrorLegacy(mysqli $conn, string $entityType, int $entityId): void
    {
        $legacy = self::LEGACY[$entityType] ?? null;
        if (!$legacy) return;
        $row = self::get($conn, $entityType, $entityId);
        if (!$row) return;

        $tbl = $legacy['table'];
        if ($legacy['full']) {
            $st = $conn->prepare(
                "UPDATE `$tbl` SET abha_id = ?, abha_address = ?, abha_linked = ?, abha_verified = ?, abha_linked_at = ? WHERE id = ?"
            );
            $st->bind_param(
                'ssiisi',
                $row['abha_number'], $row['abha_address'],
                $row['linked'], $row['verified'], $row['linked_at'],
                $entityId
            );
        } else {
            $st = $conn->prepare("UPDATE `$tbl` SET abha_id = ? WHERE id = ?");
            $st->bind_param('si', $row['abha_number'], $entityId);
        }
        $st->execute();
        $st->close();
    }
}
