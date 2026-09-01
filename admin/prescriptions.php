<?php
/**
 * Admin → Prescriptions
 * Every consultation record a doctor has written (draft + final), across
 * all appointments. "View" opens the full record + chat in the shared
 * appointment-details modal.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');

$where = "1=1";
if (in_array($status_filter, ['draft', 'final'], true)) {
    $where .= " AND p.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search !== '') {
    $q = $conn->real_escape_string($search);
    $where .= " AND (d.name LIKE '%$q%' OR u.name LIKE '%$q%' OR a.patient_name LIKE '%$q%' OR p.diagnosis LIKE '%$q%')";
}

$rows = $conn->query("
    SELECT p.id, p.appointment_id, p.visit_date, p.diagnosis, p.medications, p.status, p.created_at,
           p.follow_up_date,
           d.name AS doctor_name, d.specialization,
           COALESCE(u.name, a.patient_name) AS patient_name,
           a.appointment_type, a.appointment_date
    FROM prescriptions p
    JOIN doctors d      ON d.id = p.doctor_id
    JOIN appointments a ON a.id = p.appointment_id
    LEFT JOIN users u   ON u.id = a.user_id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT 500
");

$c = fn($sql) => (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
$t_all   = $c("SELECT COUNT(*) c FROM prescriptions");
$t_final = $c("SELECT COUNT(*) c FROM prescriptions WHERE status='final'");
$t_draft = $c("SELECT COUNT(*) c FROM prescriptions WHERE status='draft'");
$t_month = $c("SELECT COUNT(*) c FROM prescriptions WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Prescriptions | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
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
                        <h4 class="mb-0 fw-bold">Prescriptions</h4>
                        <small class="text-muted">Consultation records written by doctors — diagnosis, medications, advice</small>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-file-medical big-icon"></i><div class="num"><?= $t_all ?></div><div class="lbl">Total Records</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-double big-icon"></i><div class="num"><?= $t_final ?></div><div class="lbl">Finalised</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-pen big-icon"></i><div class="num"><?= $t_draft ?></div><div class="lbl">Draft</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-calendar big-icon"></i><div class="num"><?= $t_month ?></div><div class="lbl">This Month</div></div></div>
                </div>

                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-8 col-lg-4">
                            <label class="form-label mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Doctor, patient, diagnosis...">
                        </div>
                        <div class="col-4 col-lg-3">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (['all' => 'All', 'final' => 'Finalised', 'draft' => 'Draft'] as $v => $l): ?>
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
                    <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">Records <span class="badge bg-secondary ms-2"><?= $rows ? $rows->num_rows : 0 ?></span></h3></div></div></div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Diagnosis</th>
                                        <th>Medicines</th>
                                        <th>Visit</th>
                                        <th>Status</th>
                                        <th>View</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$rows || $rows->num_rows === 0): ?>
                                    <tr class="empty-row"><td colspan="7"><i class="fas fa-file-medical fa-3x mb-3 d-block opacity-25"></i>No prescriptions found.</td></tr>
                                <?php else: while ($r = $rows->fetch_assoc()):
                                    $meds = json_decode($r['medications'] ?? '[]', true) ?: [];
                                    $meds = array_values(array_filter($meds, fn($m) => trim($m['name'] ?? '') !== ''));
                                    $medNames = array_slice(array_map(fn($m) => $m['name'], $meds), 0, 3);
                                ?>
                                    <tr>
                                        <td data-label="Patient"><div class="cell-title"><?= htmlspecialchars($r['patient_name'] ?: '—') ?></div></td>
                                        <td data-label="Doctor">
                                            <div class="cell-title"><?= htmlspecialchars($r['doctor_name']) ?></div>
                                            <div class="cell-sub"><?= htmlspecialchars($r['specialization'] ?: '') ?></div>
                                        </td>
                                        <td data-label="Diagnosis"><?= htmlspecialchars($r['diagnosis'] ?: '—') ?></td>
                                        <td data-label="Medicines">
                                            <?php if ($medNames): ?>
                                                <span class="cell-sub"><?= htmlspecialchars(implode(', ', $medNames)) ?><?= count($meds) > 3 ? ' +' . (count($meds) - 3) : '' ?></span>
                                            <?php else: ?><span class="cell-sub">—</span><?php endif; ?>
                                        </td>
                                        <td data-label="Visit"><span class="cell-sub"><?= $r['visit_date'] ? date('d M Y', strtotime($r['visit_date'])) : date('d M Y', strtotime($r['created_at'])) ?></span></td>
                                        <td data-label="Status"><span class="pill <?= $r['status'] === 'final' ? 'pill-success' : 'pill-warn' ?>"><?= ucfirst($r['status']) ?></span></td>
                                        <td data-label="View">
                                            <button type="button" class="tbl-action-btn bg-primary text-white" data-rx-view="<?= (int) $r['appointment_id'] ?>" title="View full record"><i class="fas fa-eye"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal fade" id="rxModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-file-medical me-2"></i>Consultation Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="rxModalBody"></div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

<script>
(function () {
    var modal = new bootstrap.Modal(document.getElementById('rxModal'));
    var body  = document.getElementById('rxModalBody');
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-rx-view]');
        if (!btn) return;
        body.innerHTML = '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>';
        modal.show();
        fetch('ajax/appointment-details.php?id=' + btn.dataset.rxView)
            .then(function (r) { return r.text(); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function () { body.innerHTML = '<div class="alert alert-danger">Could not load the record.</div>'; });
    });
})();
</script>
</body>
</html>
