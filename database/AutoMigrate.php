<?php
/**
 * Zero-touch schema migrations for shared hosting (no SSH / no CLI `mysql`).
 *
 * database/run-migrations.php already does this well from a terminal, but it
 * shells out to the `mysql` client — not available on most shared hosting.
 * This is the same idea in pure PHP (mysqli only), run automatically on a
 * normal web request so a new migration file applies itself the next time
 * anyone opens the site — no manual phpMyAdmin / SSH step.
 *
 * Shares the same `schema_migrations` tracking table (filename + checksum) as
 * the CLI runner, so either one can be used interchangeably.
 *
 * Every file in database/*.sql must stay idempotent (CREATE TABLE IF NOT
 * EXISTS, etc.) per database/MIGRATIONS.md — this runner also tolerates the
 * same "already there" error codes so a half-applied file from the old
 * manual process doesn't get stuck retrying forever.
 */
class AutoMigrate
{
    /** 1050 table exists · 1060 dup column · 1061 dup key · 1091 can't DROP · 1022/1826 dup constraint/FK · 1359 trigger exists */
    private const BENIGN_ERRORS = [1050, 1060, 1061, 1091, 1022, 1826, 1359];

    /**
     * Apply every *.sql file in database/ that hasn't run yet, in filename
     * order. Never throws — a migration problem is logged, not fatal, so a
     * broken file can't take the whole site down on shared hosting.
     */
    public static function runPending(mysqli $conn): void
    {
        try {
            $dir = __DIR__;

            $conn->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `filename`   VARCHAR(191) NOT NULL PRIMARY KEY,
                `checksum`   CHAR(64)     NOT NULL,
                `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $applied = [];
            $res = $conn->query("SELECT filename, checksum FROM schema_migrations");
            while ($row = $res->fetch_assoc()) {
                $applied[$row['filename']] = $row['checksum'];
            }

            $files = glob("$dir/*.sql") ?: [];
            sort($files, SORT_STRING);

            foreach ($files as $path) {
                $file = basename($path);
                $sql = file_get_contents($path);
                if ($sql === false) continue;
                $sum = hash('sha256', $sql);

                if (isset($applied[$file]) && $applied[$file] === $sum) {
                    continue; // already applied, unchanged
                }
                if (isset($applied[$file])) {
                    // Checksum differs from what's recorded — per convention, migrations
                    // are never edited after being applied. Skip rather than re-run blindly,
                    // but log it: a silently-skipped fix is invisible on shared hosting
                    // (no SSH to notice it never ran).
                    error_log("[AutoMigrate] {$file}: checksum differs from the applied version — skipped. Add a NEW migration file instead of editing an applied one.");
                    continue;
                }

                self::applyFile($conn, $file, $path, $sql, $sum);
            }
        } catch (Throwable $e) {
            error_log('[AutoMigrate] runPending failed: ' . $e->getMessage());
        }
    }

    private static function applyFile(mysqli $conn, string $file, string $path, string $sql, string $sum): void
    {
        $statements = self::splitStatements($sql);
        $fatal = null;

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;

            try {
                $conn->query($stmt);
            } catch (mysqli_sql_exception $e) {
                if (in_array($e->getCode(), self::BENIGN_ERRORS, true)) {
                    continue; // already applied previously (manually or partially)
                }
                $fatal = "{$file}: [{$e->getCode()}] {$e->getMessage()} — statement: " . mb_substr($stmt, 0, 200);
                break;
            }
        }

        if ($fatal !== null) {
            error_log('[AutoMigrate] ' . $fatal . ' — will retry on next request');
            return; // not recorded, so it retries instead of silently getting stuck
        }

        $ins = $conn->prepare(
            "INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = NOW()"
        );
        $ins->bind_param('ss', $file, $sum);
        $ins->execute();
        $ins->close();
    }

    /**
     * Naive statement splitter — fine for plain CREATE/ALTER/INSERT files
     * (no DELIMITER blocks, no stored procedures). Strips full-line `--` and
     * `#` comments before splitting so a semicolon inside a comment can't
     * break a statement in half.
     */
    private static function splitStatements(string $sql): array
    {
        $lines = explode("\n", $sql);
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) continue;
            $clean[] = $line;
        }
        return array_filter(array_map('trim', explode(';', implode("\n", $clean))), fn($s) => $s !== '');
    }
}
