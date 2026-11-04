<?php
/**
 * Admin → ABDM HIU Consent Requests (Milestone 3, Consent flow)
 *
 * Read-only operational visibility for the HIU role — consent requests our
 * doctors raise to pull a patient's records from OTHER providers
 * (lib/HiuConsent.php + lib/ConsentApi.php + the telemedicine/api/abdm-hiu-consent-*.php
 * callbacks). Mirrors admin/hip-linking.php's style for the M2 HIP side.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/abdm.php';
admin_jwt_guard();

$tbl = fn($t) => (bool) $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'")->num_rows;
$tables_ok = $tbl('abdm_hiu_consent_requests') && $tbl('abdm_hiu_consent_artefacts');

$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');

$requests = [];
$s_requested = $s_granted = $s_denied = $s_failed = 0;

if ($tables_ok) {
    $where = "1=1";
    if (in_array($status_filter, ['requested', 'granted', 'denied', 'revoked', 'failed'], true)) {
        $where .= " AND cr.status = '" . $conn->real_escape_string($status_filter) . "'";
    }
    if ($search !== '') {
        $q = $conn->real_escape_string($search);
        $where .= " AND (cr.abha_address LIKE '%$q%' OR u.name LIKE '%$q%' OR d.name LIKE '%$q%')";
    }

    $requests = $conn->query("
        SELECT cr.*, u.name AS patient_name, u.last_name AS patient_last_name, d.name AS doctor_name,
               (SELECT COUNT(*) FROM abdm_hiu_consent_artefacts a WHERE a.hiu_consent_request_id = cr.id) AS artefact_count,
               (SELECT COUNT(*) FROM abdm_hiu_consent_artefacts a WHERE a.hiu_consent_request_id = cr.id AND a.status = 'fetched') AS fetched_count,
               (SELECT COUNT(*) FROM abdm_hiu_received_records rr
                  JOIN abdm_hiu_data_requests dr ON dr.id = rr.data_request_id
                  JOIN abdm_hiu_consent_artefacts a2 ON a2.id = dr.artefact_id
                  WHERE a2.hiu_consent_request_id = cr.id) AS records_received
        FROM abdm_hiu_consent_requests cr
        LEFT JOIN users u ON u.id = cr.patient_id
        LEFT JOIN doctors d ON d.id = cr.doctor_id
        WHERE $where
        ORDER BY cr.created_at DESC
        LIMIT 300
    ")->fetch_all(MYSQLI_ASSOC);

    $c = fn($st) => (int) ($conn->query("SELECT COUNT(*) c FROM abdm_hiu_consent_requests WHERE status='$st'")->fetch_assoc()['c'] ?? 0);
    $s_requested = $c('requested');
    $s_granted   = $c('granted');
    $s_denied    = $c('denied') + $c('revoked');
    $s_failed    = $c('failed');
}

function hiu_status_badge(string $s): string
{
    $map = [
        'granted'   => 'badge bg-success',
        'requested' => 'badge bg-warning text-dark',
        'denied'    => 'badge bg-danger',
        'revoked'   => 'badge bg-danger',
        'failed'    => 'badge bg-secondary',
    ];
    return '<span class="' . ($map[$s] ?? 'badge bg-light text-dark') . '">' . htmlspecialchars(ucfirst($s)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>ABDM HIU Consent Requests | Admin Panel</title>
    <?php include "links.php"; ?>
</head>
<body>
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">ABDM HIU Consent Requests</h4>
                        <small class="text-muted">Milestone 3 — doctors requesting a patient's records held by other providers</small>
                    </div>
                    <a href="hip-linking.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-link me-1"></i> HIP Linking (M2)</a>
                </div>

                <?php if (!$tables_ok): ?>
                    <div class="alert alert-warning"><i class="fas fa-database me-2"></i>The HIU consent tables are not present yet — they apply automatically on the next request (database/migration_abdm_m3_hiu_consent.sql via the auto-migrator).</div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-hourglass-half big-icon"></i><div class="num"><?= $s_requested ?></div><div class="lbl">Awaiting Patient</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-circle big-icon"></i><div class="num"><?= $s_granted ?></div><div class="lbl">Granted</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-times-circle big-icon"></i><div class="num"><?= $s_denied ?></div><div class="lbl">Denied / Revoked</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-exclamation-triangle big-icon"></i><div class="num"><?= $s_failed ?></div><div class="lbl">Failed</div></div></div>
                </div>

                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-8 col-lg-4">
                            <label class="form-label mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Patient, doctor, ABHA address...">
                        </div>
                        <div class="col-4 col-lg-3">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (['all' => 'All', 'requested' => 'Awaiting Patient', 'granted' => 'Granted', 'denied' => 'Denied', 'revoked' => 'Revoked', 'failed' => 'Failed'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $status_filter === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">Requests <span class="badge bg-secondary ms-2"><?= count($requests) ?></span></h3></div></div></div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>hiTypes</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th>Artefacts Fetched</th>
                                        <th>Records Received</th>
                                        <th>Requested</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$requests): ?>
                                    <tr class="empty-row"><td colspan="8"><i class="fas fa-exchange-alt fa-3x mb-3 d-block opacity-25"></i>No ABDM consent requests found.</td></tr>
                                <?php else: foreach ($requests as $r):
                                    $hts = json_decode($r['hi_types'] ?? '[]', true) ?: [];
                                ?>
                                    <tr>
                                        <td data-label="Patient"><div class="cell-title"><?= htmlspecialchars(trim(($r['patient_name'] ?? '') . ' ' . ($r['patient_last_name'] ?? '')) ?: '—') ?></div><div class="cell-sub mono" style="font-size:.75rem;"><?= htmlspecialchars($r['abha_address']) ?></div></td>
                                        <td data-label="Doctor"><?= htmlspecialchars($r['doctor_name'] ?? '—') ?></td>
                                        <td data-label="hiTypes" style="font-size:.78rem;"><?= htmlspecialchars(implode(', ', $hts)) ?></td>
                                        <td data-label="Date Range" style="font-size:.78rem;"><?= date('d M Y', strtotime($r['date_from'])) ?> – <?= date('d M Y', strtotime($r['date_to'])) ?></td>
                                        <td data-label="Status"><?= hiu_status_badge($r['status']) ?><?= $r['reason'] ? '<div class="cell-sub" style="font-size:.72rem;">' . htmlspecialchars($r['reason']) . '</div>' : '' ?></td>
                                        <td data-label="Artefacts"><?= (int) $r['fetched_count'] ?> / <?= (int) $r['artefact_count'] ?></td>
                                        <td data-label="Records Received"><?= (int) $r['records_received'] ?></td>
                                        <td data-label="Requested"><small class="text-muted"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></small></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>
</div>
</body>
</html>
