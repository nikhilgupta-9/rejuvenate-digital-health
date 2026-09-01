<?php
require_once __DIR__ . '/db-conn.php';
include_once "functions.php";
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

/* Approve / Reject / Assign / Status / Delete are handled via AJAX
   (admin/ajax/appointment-*.php). This page only renders the list. */

$status_filter = $_GET['status'] ?? 'all';
$search_query  = trim($_GET['search'] ?? '');
$focus_id      = (int) ($_GET['focus'] ?? 0);

$valid_status = ['all', 'pending', 'approved', 'completed', 'no_show', 'rejected'];
if (!in_array($status_filter, $valid_status, true)) $status_filter = 'all';

$sql = "
    SELECT a.*,
           u.name  AS user_name, u.email AS user_email, u.dob, u.gender, u.id AS uid,
           d.name  AS doctor_name, d.specialization, d.consultation_fee, d.id AS did,
           TIME_FORMAT(a.appointment_time, '%h:%i %p') AS formatted_time,
           DATE_FORMAT(a.appointment_date, '%d %b %Y')  AS formatted_date,
           DATE_FORMAT(a.created_at, '%d %b %Y')        AS created_at_formatted
    FROM appointments a
    LEFT JOIN users   u ON a.user_id   = u.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    WHERE 1=1
";
$params = [];
$types  = '';
if ($status_filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}
if ($search_query !== '') {
    $sql .= " AND (u.name LIKE ? OR a.patient_name LIKE ? OR d.name LIKE ? OR a.purpose LIKE ?)";
    $t = "%$search_query%";
    array_push($params, $t, $t, $t, $t);
    $types .= 'ssss';
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$doctors_result = $conn->query("SELECT id, name, specialization FROM doctors WHERE status = 'Active' ORDER BY name");

$counts = $conn->query("
    SELECT
        SUM(status='pending')   AS pending,
        SUM(status='approved')  AS approved,
        SUM(status='completed') AS completed,
        SUM(status='no_show')   AS no_show,
        SUM(status='rejected')  AS rejected,
        COUNT(*)                AS total
    FROM appointments
")->fetch_assoc();

$status_pill = [
    'pending' => 'pill-warn', 'approved' => 'pill-success', 'completed' => 'pill-info',
    'no_show' => 'pill-muted', 'rejected' => 'pill-danger',
];

/* Stat tiles double as status filters */
$tiles = [
    ['all',       'Total',     'total',     'bg-stat-blue',  'fa-calendar-check'],
    ['pending',   'Pending',   'pending',   'bg-stat-warn',  'fa-clock'],
    ['approved',  'Approved',  'approved',  'bg-stat-green', 'fa-check'],
    ['completed', 'Completed', 'completed', 'bg-stat-teal',  'fa-clipboard-check'],
    ['no_show',   'No Show',   'no_show',   'bg-stat-blue',  'fa-user-slash'],
    ['rejected',  'Rejected',  'rejected',  'bg-stat-red',   'fa-times'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointments | Admin Panel</title>
    <?php include "links.php"; ?>
    <!-- styles in assets/css/colors/default.css -->
</head>
<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="list-page-head page-header">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Appointments</h4>
                        <small class="text-muted">Approve, assign a doctor, and track every consultation</small>
                    </div>
                    <a href="book-appointment.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> New Appointment
                    </a>
                </div>

                <div id="ajaxAlerts"></div>

                <!-- Stat tiles / quick filters -->
                <div class="row g-3 mb-3">
                    <?php foreach ($tiles as [$key, $label, $ckey, $bg, $icon]): ?>
                        <div class="col-6 col-lg-2">
                            <a href="?status=<?= $key ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?>"
                               class="stat-box <?= $bg ?> d-block text-decoration-none<?= $status_filter === $key ? ' stat-box-active' : '' ?>">
                                <i class="fas <?= $icon ?> big-icon"></i>
                                <div class="num"><?= (int) ($counts[$ckey] ?? 0) ?></div>
                                <div class="lbl"><?= $label ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="filter-card">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="form-label mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" name="search"
                                   value="<?= htmlspecialchars($search_query) ?>"
                                   placeholder="Patient, doctor or purpose…">
                        </div>
                        <div class="col-8 col-lg-4">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                <?php foreach ($valid_status as $s): ?>
                                    <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4 col-lg-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Go</button>
                        </div>
                    </form>
                </div>

                <div class="white_card mb_30">
                    <div class="white_card_header">
                        <div class="box_header">
                            <div class="main-title"><h3 class="m-0">Appointments <span class="badge bg-secondary ms-2"><?= $result->num_rows ?></span></h3></div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>Ref</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>When</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                        <th>Booked</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$result->num_rows): ?>
                                    <tr class="empty-row"><td colspan="8">
                                        <i class="fas fa-calendar-times fa-3x mb-3 d-block opacity-25"></i>
                                        No appointments<?= $status_filter !== 'all' || $search_query ? ' match this filter' : ' yet' ?>.
                                        <?php if ($status_filter !== 'all' || $search_query): ?>
                                            <div class="mt-2"><a href="all-appointment.php" class="btn btn-sm btn-outline-primary">Clear filters</a></div>
                                        <?php endif; ?>
                                    </td></tr>
                                <?php endif; ?>
                                <?php while ($row = $result->fetch_assoc()):
                                    $st  = strtolower($row['status'] ?: 'pending');
                                    $ref = 'AP' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
                                    $pname = $row['user_name'] ?: $row['patient_name'];
                                    $age = !empty($row['dob']) && $row['dob'] !== '0000-00-00'
                                        ? date_diff(date_create($row['dob']), date_create('today'))->y . 'y' : '';
                                ?>
                                    <tr id="apptRow<?= $row['id'] ?>" data-status="<?= $st ?>" data-has-doctor="<?= $row['did'] ? '1' : '0' ?>" data-ref="<?= $ref ?>">
                                        <td data-label="Ref"><span class="cell-sub"><?= $ref ?></span></td>
                                        <td data-label="Patient">
                                            <?php if ($row['uid']): ?>
                                                <a href="view-customer.php?id=<?= (int) $row['uid'] ?>" class="cell-title"><?= htmlspecialchars($pname) ?></a>
                                            <?php else: ?>
                                                <span class="cell-title"><?= htmlspecialchars($pname ?: '—') ?></span>
                                            <?php endif; ?>
                                            <div class="cell-sub"><?= trim($age . ' ' . ($row['gender'] ?? '')) ?: 'Guest booking' ?></div>
                                        </td>
                                        <td data-label="Doctor" id="doctorCell<?= $row['id'] ?>">
                                            <?php if ($row['did']): ?>
                                                <a href="doctor-edit.php?id=<?= (int) $row['did'] ?>" class="cell-title">Dr. <?= htmlspecialchars($row['doctor_name']) ?></a>
                                                <div class="cell-sub"><?= htmlspecialchars($row['specialization'] ?? '') ?></div>
                                            <?php else: ?>
                                                <span class="pill pill-warn pill-sq">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="When">
                                            <div class="cell-title" style="font-weight:500;"><?= $row['formatted_date'] ?></div>
                                            <div class="cell-sub"><?= $row['formatted_time'] ?></div>
                                        </td>
                                        <td data-label="Fee">
                                            <?php if ($row['consultation_fee'] > 0): ?>
                                                <span class="pill pill-success pill-sq">₹<?= number_format((float) $row['consultation_fee']) ?></span>
                                            <?php else: ?>
                                                <span class="cell-sub">Direct</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="pill <?= $status_pill[$st] ?? 'pill-muted' ?>" id="statusBadge<?= $row['id'] ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></span>
                                        </td>
                                        <td data-label="Booked"><span class="cell-sub"><?= $row['created_at_formatted'] ?></span></td>
                                        <td data-label="Actions" class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end" id="actionsCell<?= $row['id'] ?>">
                                                <?= appt_actions($row['id'], $st, (bool) $row['did']) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>

    <!-- Shared: appointment details -->
    <div class="modal fade" id="apptDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i>Appointment details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="apptDetailBody">
                    <div class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shared: action (approve / reject / assign / complete / no_show) -->
    <div class="modal fade" id="apptActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="apptActionForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="apptActionTitle">Confirm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="apptActionBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="apptActionSubmit">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const BASE = '<?= BASE_URL ?>';
        const AJAX = BASE + 'admin/ajax/';
        const doctorsJson = <?php
            $dopts = [];
            if ($doctors_result) { $doctors_result->data_seek(0); while ($d = $doctors_result->fetch_assoc()) $dopts[] = $d; }
            echo json_encode($dopts);
        ?>;

        const detailModal = new bootstrap.Modal('#apptDetailModal');
        const actionModal  = new bootstrap.Modal('#apptActionModal');
        const actionForm   = document.getElementById('apptActionForm');
        let actionState = {};

        function banner(type, msg) {
            const w = document.getElementById('ajaxAlerts');
            const d = document.createElement('div');
            d.className = `alert alert-${type} alert-dismissible fade show`;
            d.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            w.appendChild(d);
            setTimeout(() => { try { bootstrap.Alert.getOrCreateInstance(d).close(); } catch (e) { d.remove(); } }, 5000);
        }

        function post(url, data) {
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data) }).then(r => r.json());
        }

        const PILL = { pending: 'pill-warn', approved: 'pill-success', completed: 'pill-info', no_show: 'pill-muted', rejected: 'pill-danger' };

        window.apptActions = function (id, status, hasDoctor) {
            const b = [];
            b.push(`<button class="btn btn-sm btn-outline-primary" data-appt-view="${id}" title="View"><i class="fas fa-eye"></i></button>`);
            if (status === 'pending') {
                b.push(`<button class="btn btn-sm btn-outline-success" data-appt-act="approve" data-id="${id}" title="Approve"><i class="fas fa-check"></i></button>`);
                b.push(`<button class="btn btn-sm btn-outline-danger" data-appt-act="reject" data-id="${id}" title="Reject"><i class="fas fa-times"></i></button>`);
            }
            if (status === 'approved') {
                b.push(`<button class="btn btn-sm btn-outline-info" data-appt-act="completed" data-id="${id}" title="Mark completed"><i class="fas fa-clipboard-check"></i></button>`);
                b.push(`<button class="btn btn-sm btn-outline-secondary" data-appt-act="no_show" data-id="${id}" title="Mark no-show"><i class="fas fa-user-slash"></i></button>`);
            }
            if (!hasDoctor) {
                b.push(`<button class="btn btn-sm btn-outline-warning" data-appt-act="assign" data-id="${id}" title="Assign doctor"><i class="fas fa-user-md"></i></button>`);
            }
            b.push(`<button class="btn btn-sm btn-outline-danger" data-appt-del="${id}" title="Delete"><i class="fas fa-trash"></i></button>`);
            return b.join('');
        };

        function refreshRow(id) {
            const row = document.getElementById('apptRow' + id);
            document.getElementById('actionsCell' + id).innerHTML =
                window.apptActions(id, row.dataset.status, row.dataset.hasDoctor === '1');
        }

        function setStatus(id, status, label) {
            const badge = document.getElementById('statusBadge' + id);
            badge.className = 'pill ' + (PILL[status] || 'pill-muted');
            badge.textContent = label;
            document.getElementById('apptRow' + id).dataset.status = status;
            refreshRow(id);
        }

        /* ---- open details ---- */
        function openDetails(id) {
            document.getElementById('apptDetailBody').innerHTML =
                '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>';
            detailModal.show();
            fetch(AJAX + 'appointment-details.php?id=' + id)
                .then(r => r.text())
                .then(html => { document.getElementById('apptDetailBody').innerHTML = html; })
                .catch(() => { document.getElementById('apptDetailBody').innerHTML = '<div class="alert alert-danger">Could not load details.</div>'; });
        }

        /* ---- open an action ---- */
        function openAction(kind, id) {
            const ref = document.getElementById('apptRow' + id).dataset.ref;
            actionState = { kind, id };
            const T = document.getElementById('apptActionTitle');
            const B = document.getElementById('apptActionBody');
            const S = document.getElementById('apptActionSubmit');

            if (kind === 'approve') {
                T.textContent = 'Approve ' + ref;
                B.innerHTML = '<p class="mb-0">Approve this appointment? A confirmation email goes to the patient (and doctor, if assigned).</p>';
                S.className = 'btn btn-success btn-sm'; S.textContent = 'Approve';
            } else if (kind === 'reject') {
                T.textContent = 'Reject ' + ref;
                B.innerHTML = '<label class="form-label">Reason (optional, shown to patient)</label>'
                    + '<textarea class="form-control form-control-sm" name="rejection_reason" rows="3"></textarea>';
                S.className = 'btn btn-danger btn-sm'; S.textContent = 'Reject';
            } else if (kind === 'assign') {
                T.textContent = 'Assign doctor — ' + ref;
                const opts = doctorsJson.map(d => `<option value="${d.id}">Dr. ${d.name}${d.specialization ? ' (' + d.specialization + ')' : ''}</option>`).join('');
                B.innerHTML = '<label class="form-label">Doctor</label>'
                    + `<select class="form-select form-select-sm" name="new_doctor_id" required><option value="">— select —</option>${opts}</select>`;
                S.className = 'btn btn-warning btn-sm'; S.textContent = 'Assign';
            } else if (kind === 'completed' || kind === 'no_show') {
                const nice = kind === 'completed' ? 'completed' : 'a no-show';
                T.textContent = 'Mark ' + ref;
                B.innerHTML = `<p class="mb-0">Mark this appointment as <strong>${nice}</strong>?</p>`;
                S.className = 'btn btn-primary btn-sm'; S.textContent = 'Confirm';
            }
            actionModal.show();
        }

        actionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const { kind, id } = actionState;
            const S = document.getElementById('apptActionSubmit');
            const fd = Object.fromEntries(new FormData(actionForm).entries());
            fd.appointment_id = id;

            let url;
            if (kind === 'approve') url = AJAX + 'appointment-approve.php';
            else if (kind === 'reject') url = AJAX + 'appointment-reject.php';
            else if (kind === 'assign') url = AJAX + 'appointment-assign-doctor.php';
            else { url = AJAX + 'appointment-status.php'; fd.status = kind; }

            const original = S.textContent;
            S.disabled = true; S.textContent = 'Working…';
            post(url, fd).then(data => {
                S.disabled = false; S.textContent = original;
                if (!data.success) { banner('danger', data.message || 'Something went wrong.'); return; }
                actionModal.hide();
                banner('success', data.message);
                actionForm.reset();

                if (kind === 'assign') {
                    document.getElementById('doctorCell' + id).innerHTML =
                        `<a href="doctor-edit.php?id=${data.doctor_id}" class="cell-title">Dr. ${data.doctor_name}</a><div class="cell-sub">${data.specialization || ''}</div>`;
                    document.getElementById('apptRow' + id).dataset.hasDoctor = '1';
                    refreshRow(id);
                } else {
                    setStatus(id, data.status, data.status_label);
                }
            }).catch(() => { S.disabled = false; S.textContent = original; banner('danger', 'Network error.'); });
        });

        /* ---- delegated clicks ---- */
        document.addEventListener('click', function (e) {
            const v = e.target.closest('[data-appt-view]');
            if (v) { openDetails(v.dataset.apptView); return; }
            const a = e.target.closest('[data-appt-act]');
            if (a) { openAction(a.dataset.apptAct, a.dataset.id); return; }
            const d = e.target.closest('[data-appt-del]');
            if (d) {
                const id = d.dataset.apptDel;
                if (!confirm('Delete this appointment permanently?')) return;
                post(AJAX + 'appointment-delete.php', { appointment_id: id }).then(res => {
                    if (!res.success) { banner('danger', res.message || 'Delete failed.'); return; }
                    banner('success', res.message);
                    const row = document.getElementById('apptRow' + id);
                    row.style.transition = 'opacity .25s'; row.style.opacity = '0';
                    setTimeout(() => row.remove(), 250);
                }).catch(() => banner('danger', 'Network error.'));
            }
        });

        /* ---- deep link ?focus=<id> ---- */
        <?php if ($focus_id): ?>
        if (document.getElementById('apptRow<?= $focus_id ?>')) openDetails(<?= $focus_id ?>);
        <?php endif; ?>
    })();
    </script>
</body>
</html>
<?php
/* Actions cell — mirrored by window.apptActions() in JS */
function appt_actions(int $id, string $status, bool $hasDoctor): string
{
    $b = ['<button class="btn btn-sm btn-outline-primary" data-appt-view="' . $id . '" title="View"><i class="fas fa-eye"></i></button>'];
    if ($status === 'pending') {
        $b[] = '<button class="btn btn-sm btn-outline-success" data-appt-act="approve" data-id="' . $id . '" title="Approve"><i class="fas fa-check"></i></button>';
        $b[] = '<button class="btn btn-sm btn-outline-danger" data-appt-act="reject" data-id="' . $id . '" title="Reject"><i class="fas fa-times"></i></button>';
    }
    if ($status === 'approved') {
        $b[] = '<button class="btn btn-sm btn-outline-info" data-appt-act="completed" data-id="' . $id . '" title="Mark completed"><i class="fas fa-clipboard-check"></i></button>';
        $b[] = '<button class="btn btn-sm btn-outline-secondary" data-appt-act="no_show" data-id="' . $id . '" title="Mark no-show"><i class="fas fa-user-slash"></i></button>';
    }
    if (!$hasDoctor) {
        $b[] = '<button class="btn btn-sm btn-outline-warning" data-appt-act="assign" data-id="' . $id . '" title="Assign doctor"><i class="fas fa-user-md"></i></button>';
    }
    $b[] = '<button class="btn btn-sm btn-outline-danger" data-appt-del="' . $id . '" title="Delete"><i class="fas fa-trash"></i></button>';
    return implode('', $b);
}
