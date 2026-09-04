<?php
/**
 * One-off data migration: copy ABHA identity out of the per-entity columns
 * (users / school_members / doctors) into the new `abha_accounts` table.
 *
 *   php database/migrate-abha-data.php            # DRY RUN — prints the plan, writes nothing
 *   php database/migrate-abha-data.php --commit   # actually insert/update abha_accounts
 *
 * Safe to re-run: rows are matched on (entity_type, entity_id) and only
 * inserted when missing / updated when a field actually differs.
 *
 * Prerequisite: run database/migration_abha_accounts.sql first.
 *
 * This script NEVER touches the old columns — dropping them is a separate,
 * later migration once the code has been repointed and verified.
 *
 * appointments.abha_number / prescriptions.abha_number are per-visit
 * SNAPSHOTS, not entity identity — reported here for awareness, not migrated.
 */

require __DIR__ . '/../config/connect.php';   // $conn

$COMMIT = in_array('--commit', $argv, true);

function out(string $s = ''): void { fwrite(STDOUT, $s . "\n"); }

/** digits -> XX-XXXX-XXXX-XXXX when exactly 14 digits, else trimmed input. */
function fmt_abha(?string $raw): ?string {
    $raw = trim((string) $raw);
    if ($raw === '') return null;
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . '-' . substr($d, 2, 4) . '-' . substr($d, 6, 4) . '-' . substr($d, 10, 4);
    }
    return $raw;
}

/* ── guard: table must exist ── */
if (!$conn->query("SHOW TABLES LIKE 'abha_accounts'")->num_rows) {
    out("ERROR: table `abha_accounts` does not exist.");
    out("Run:  mysql -u root " . ($_ENV['DB_NAME'] ?? '<db>') . " < database/migration_abha_accounts.sql");
    exit(1);
}

out(str_repeat('=', 64));
out($COMMIT ? "ABHA data migration — COMMIT MODE (will write)" : "ABHA data migration — DRY RUN (no writes)");
out(str_repeat('=', 64));
out();

/* ── source definitions ── */
$sources = [
    'patient' => [
        'table'   => 'users',
        'where'   => "(abha_id IS NOT NULL AND abha_id <> '') OR (abha_address IS NOT NULL AND abha_address <> '') OR abha_linked = 1 OR abha_verified = 1",
        'profile' => null,
    ],
    'school_member' => [
        'table'   => 'school_members',
        'where'   => "(abha_id IS NOT NULL AND abha_id <> '') OR (abha_address IS NOT NULL AND abha_address <> '') OR abha_linked = 1 OR abha_verified = 1",
        'profile' => 'abha_profile_data',
    ],
    'doctor' => [
        'table'   => 'doctors',
        'where'   => "(abha_id IS NOT NULL AND abha_id <> '')",
        'profile' => null,
    ],
];

$upsert = $conn->prepare(
    "INSERT INTO abha_accounts
        (entity_type, entity_id, abha_number, abha_address, linked, verified, linked_at, verified_at, source, profile_data)
     VALUES (?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        abha_number  = VALUES(abha_number),
        abha_address = VALUES(abha_address),
        linked       = VALUES(linked),
        verified     = VALUES(verified),
        linked_at    = COALESCE(abha_accounts.linked_at,   VALUES(linked_at)),
        verified_at  = COALESCE(abha_accounts.verified_at, VALUES(verified_at)),
        profile_data = COALESCE(VALUES(profile_data), abha_accounts.profile_data)"
);

$planInsert = $planUpdate = $planSkip = 0;
$rows = [];

foreach ($sources as $entityType => $def) {
    $tbl     = $def['table'];
    $profCol = $def['profile'];

    $cols = [];
    $rc = $conn->query("SHOW COLUMNS FROM `$tbl`");
    while ($c = $rc->fetch_assoc()) $cols[$c['Field']] = true;

    $select = "id"
        . ", " . (isset($cols['abha_id'])        ? 'abha_id'        : 'NULL AS abha_id')
        . ", " . (isset($cols['abha_address'])   ? 'abha_address'   : 'NULL AS abha_address')
        . ", " . (isset($cols['abha_linked'])    ? 'abha_linked'    : '0 AS abha_linked')
        . ", " . (isset($cols['abha_linked_at']) ? 'abha_linked_at' : 'NULL AS abha_linked_at')
        . ", " . (isset($cols['abha_verified'])  ? 'abha_verified'  : '0 AS abha_verified')
        . ", " . ($profCol && isset($cols[$profCol]) ? "`$profCol` AS profile_data" : 'NULL AS profile_data');

    $res = $conn->query("SELECT $select FROM `$tbl` WHERE " . $def['where']);
    if (!$res) { out("  ! query failed for $tbl: " . $conn->error); continue; }

    while ($src = $res->fetch_assoc()) {
        $entityId    = (int) $src['id'];
        $abhaNumber  = fmt_abha($src['abha_id']);
        $abhaAddress = trim((string) $src['abha_address']) ?: null;
        $linked      = (int) (!empty($src['abha_linked']) || $abhaNumber || $abhaAddress);
        $verified    = (int) !empty($src['abha_verified']);
        $linkedAt    = $src['abha_linked_at'] ?: null;
        $verifiedAt  = ($verified && $linkedAt) ? $linkedAt : null;
        $profile     = $src['profile_data'] ?: null;

        $chk = $conn->prepare("SELECT abha_number, abha_address, linked, verified
                               FROM abha_accounts WHERE entity_type = ? AND entity_id = ? LIMIT 1");
        $chk->bind_param('si', $entityType, $entityId);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        $chk->close();

        $action = 'INSERT';
        if ($existing) {
            $differs = ($existing['abha_number']  !== $abhaNumber)
                    || ($existing['abha_address'] !== $abhaAddress)
                    || ((int) $existing['linked']   !== $linked)
                    || ((int) $existing['verified'] !== $verified);
            $action = $differs ? 'UPDATE' : 'SKIP (identical)';
        }

        $rows[] = sprintf("%-22s %-20s %-26s %-6s %s",
            "$entityType#$entityId", $abhaNumber ?: '—', $abhaAddress ?: '—', "$linked/$verified", $action);

        if ($action === 'INSERT')      $planInsert++;
        elseif ($action === 'UPDATE')  $planUpdate++;
        else { $planSkip++; continue; }

        if ($COMMIT) {
            $source = 'migrated';
            $upsert->bind_param(
                'sissiissss',
                $entityType, $entityId, $abhaNumber, $abhaAddress,
                $linked, $verified, $linkedAt, $verifiedAt, $source, $profile
            );
            if (!$upsert->execute()) {
                out("  ! write failed for $entityType#$entityId: " . $upsert->error);
            }
        }
    }
}

out(sprintf("%-22s %-20s %-26s %-6s %s", 'ENTITY', 'ABHA NUMBER', 'ABHA ADDRESS', 'L/V', 'ACTION'));
out(str_repeat('-', 92));
foreach ($rows as $line) out($line);
out(str_repeat('-', 92));
out(sprintf("Plan: %d insert, %d update, %d skip (unchanged)", $planInsert, $planUpdate, $planSkip));
out();

/* ── informational: per-visit snapshots (NOT migrated) ── */
$apptCnt = (int) ($conn->query("SELECT COUNT(*) c FROM appointments WHERE abha_number IS NOT NULL AND abha_number <> ''")->fetch_assoc()['c'] ?? 0);
$rxCnt = 0;
if ($conn->query("SHOW TABLES LIKE 'prescriptions'")->num_rows
    && $conn->query("SHOW COLUMNS FROM prescriptions LIKE 'abha_number'")->num_rows) {
    $rxCnt = (int) ($conn->query("SELECT COUNT(*) c FROM prescriptions WHERE abha_number IS NOT NULL AND abha_number <> ''")->fetch_assoc()['c'] ?? 0);
}
out("Per-visit snapshots (left in place; source from abha_accounts going forward):");
out("  appointments.abha_number : $apptCnt row(s)");
out("  prescriptions.abha_number: $rxCnt row(s)");
out();
out($COMMIT ? ">>> COMMIT complete. Verify:  SELECT * FROM abha_accounts;"
            : ">>> DRY RUN — nothing written. Re-run with --commit to apply.");
