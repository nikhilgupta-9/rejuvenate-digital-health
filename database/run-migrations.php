<?php
/**
 * Minimal migration runner.
 *
 *   php database/run-migrations.php            apply every pending migration
 *   php database/run-migrations.php --dry-run  list what would run, change nothing
 *   php database/run-migrations.php --force    re-apply even if the checksum changed
 *
 * Order is the canonical list below (mirrors database/MIGRATIONS.md).
 *
 * Each file is piped to the `mysql` client (so DELIMITER / triggers / the
 * PREPARE-EXECUTE index idiom all work), then recorded in `schema_migrations`
 * with its sha256 — a second run is a no-op. Edit migrations by ADDING a new
 * file, never by changing one that has already been applied.
 *
 * Connection comes from .env (via config/connect.php). The `mysql` binary is
 * auto-detected; override with  MYSQL_BIN=/path/to/mysql  or  --mysql=/path.
 * XAMPP CLI: run with /Applications/XAMPP/xamppfiles/bin/php.
 */

require __DIR__ . '/../config/connect.php';   // $conn + $_ENV

$DRY   = in_array('--dry-run', $argv, true);
$FORCE = in_array('--force', $argv, true);
$DIR   = __DIR__;

function arg_val(array $argv, string $name): ?string {
    foreach ($argv as $a) if (str_starts_with($a, "--$name=")) return substr($a, strlen("--$name="));
    return null;
}

/** Canonical run order — see database/MIGRATIONS.md for the rationale. */
$ORDER = [
    'school_module.sql',
    'abdm_security.sql',
    'migration_doctor_abha.sql',
    'migration_doctor_profile_hpr.sql',
    'migration_doctor_activation_gate.sql',
    'migration_doctor_subscription_referral.sql',
    'migration_doctor_plans_marketing.sql',
    'migration_doctor_bank_settlement.sql',
    'migration_doctor_password_security.sql',
    'migration_doctor_health_profile_edit.sql',
    'migration_doctor_student_care.sql',
    'migration_appointment_booking.sql',
    'migration_appointment_payment.sql',
    'migration_appointment_rejection_reason.sql',
    'migration_prescriptions.sql',
    'migration_consultation_records.sql',
    'migration_patient_medical_info.sql',
    'migration_abha_module.sql',
    'migration_abha_accounts.sql',
    'migration_abha_deprecate_legacy_columns.sql',
    'migration_admin_jwt_security.sql',
    'migration_admin_rbac.sql',
    'migration_admin_medical_records.sql',
    'migration_school_health_plans.sql',
    'migration_parent_consent_forms.sql',
    'migration_student_medical_certificates.sql',
    'migration_share_token.sql',
    'migration_telemedicine.sql',
    'migration_telemedicine_polling.sql',
    'migration_telemedicine_settings.sql',
    'migration_whatsapp_otp.sql',
    'migration_department_description.sql',
    'migration_runtime_column_backfills.sql',
    // 12. referential integrity — LAST (needs every table + type fix in place)
    'migration_core_foreign_keys.sql',
];

function out(string $s = ''): void { fwrite(STDOUT, $s . "\n"); }

/* ── locate the mysql client ── */
$mysqlBin = arg_val($argv, 'mysql') ?: getenv('MYSQL_BIN') ?: null;
if (!$mysqlBin) {
    foreach ([
        '/Applications/XAMPP/xamppfiles/bin/mysql',
        '/Applications/XAMPP/xamppfiles/bin/mariadb',
        '/opt/homebrew/opt/mysql-client/bin/mysql',
        'mysql',
    ] as $cand) {
        $probe = ($cand === 'mysql') ? 'command -v mysql' : "test -x " . escapeshellarg($cand) . " && echo " . escapeshellarg($cand);
        $r = trim((string) @shell_exec($probe . ' 2>/dev/null'));
        if ($r !== '') { $mysqlBin = ($cand === 'mysql') ? $r : $cand; break; }
    }
}
if (!$mysqlBin) {
    out("! Could not find the `mysql` client. Set MYSQL_BIN=/path/to/mysql or pass --mysql=/path.");
    exit(1);
}

/* ── connection args for the client ── */
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = (string) ($_ENV['DB_PASSWORD'] ?? '');
$name = $_ENV['DB_NAME'] ?? '';
$sock = ini_get('mysqli.default_socket') ?: '';

$cliArgs = ['-u', $user, '-h', $host, '--force'];   // --force: keep going past errors, we classify them ourselves
if ($pass !== '') $cliArgs[] = "-p{$pass}";
if ($sock)        { $cliArgs[] = '--socket'; $cliArgs[] = $sock; }
$cliArgs[] = $name;
$cliPrefix = escapeshellarg($mysqlBin) . ' ' . implode(' ', array_map('escapeshellarg', $cliArgs));

// "already there" errors — expected when re-running against a DB migrated
// before the runner, or an older non-idempotent migration file.
//   1050 table exists · 1060 dup column · 1061 dup key name · 1091 can't DROP
//   1022/1826 dup constraint/FK · 1359 trigger exists · 1826 dup FK
$BENIGN = [1050, 1060, 1061, 1091, 1022, 1826, 1359];

/* ── tracking table ── */
$conn->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `filename`   VARCHAR(191) NOT NULL PRIMARY KEY,
  `checksum`   CHAR(64)     NOT NULL,
  `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$applied = [];
$r = $conn->query("SELECT filename, checksum FROM schema_migrations");
while ($row = $r->fetch_assoc()) $applied[$row['filename']] = $row['checksum'];

$missing = array_diff(array_map('basename', glob("$DIR/*.sql")), $ORDER);
if ($missing) { out('! Not in the run order (add to $ORDER): ' . implode(', ', $missing)); out(); }

out($DRY ? "=== DRY RUN (mysql: $mysqlBin) ===" : "=== applying migrations (mysql: $mysqlBin) ===");
$ran = 0; $skipped = 0;

foreach ($ORDER as $file) {
    $path = "$DIR/$file";
    if (!is_file($path)) { out("  MISSING  $file"); continue; }

    $sum = hash('sha256', file_get_contents($path));
    if (isset($applied[$file]) && !$FORCE) {
        if ($applied[$file] === $sum) { $skipped++; continue; }
        out("  CHANGED  $file — checksum differs from the applied version. Prefer a NEW migration file; use --force to re-run.");
        continue;
    }

    if ($DRY) { out("  would run  $file"); $ran++; continue; }

    out("  running  $file ...");
    $cmd = $cliPrefix . ' < ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $lines, $code);
    $output = trim(implode("\n", $lines));
    $lines = [];

    // Classify each "ERROR NNNN ..." line the client printed (--force keeps going).
    $fatal = [];
    $benignSeen = 0;
    if (preg_match_all('/^ERROR\s+(\d+)\b.*$/m', $output, $m, PREG_SET_ORDER)) {
        foreach ($m as $err) {
            if (in_array((int) $err[1], $BENIGN, true)) $benignSeen++;
            else $fatal[] = $err[0];
        }
    }

    if ($fatal) {
        out("  ! FAILED:");
        foreach ($fatal as $l) out("      $l");
        out("  Stopped. Fix the file and re-run (already-applied files are skipped).");
        exit(1);
    }
    if ($benignSeen) out("  ~ $benignSeen statement(s) already applied — skipped");
    elseif ($output !== '') foreach (explode("\n", $output) as $l) out("      $l");

    $st = $conn->prepare("INSERT INTO schema_migrations (filename, checksum) VALUES (?,?)
                          ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), applied_at=NOW()");
    $st->bind_param('ss', $file, $sum);
    $st->execute();
    $ran++;
}

out();
out($DRY ? "Would run $ran, skip $skipped (already applied)."
         : "Done — applied $ran, skipped $skipped.");
