<?php
/**
 * Admin → Telemedicine → Call History
 * Every online appointment that has a video room, with its session lifecycle.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$status_filter = $_GET['status'] ?? 'all';
$where = "a.appointment_type = 'online' AND a.meeting_event_id IS NOT NULL";
if (in_array($status_filter, ['created', 'started', 'completed', 'cancelled'], true)) {
    $where .= " AND a.meeting_status = '" . $conn->real_escape_string($status_filter) . "'";
}

$rows = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.purpose, a.status,
           a.meeting_status, a.meeting_started_at, a.meeting_completed_at, a.meeting_created_at,
           d.name AS doctor_name, d.doctor_uid,
           COALESCE(u.name, a.patient_name) AS patient_name,
           (SELECT COUNT(*) FROM telemedicine_chat_messages c WHERE c.appointment_id = a.id) AS chat_count
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE $where
    ORDER BY COALESCE(a.meeting_started_at, a.meeting_created_at, a.appointment_date) DESC
    LIMIT 400
");

$c = fn($sql) => (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
$t_all       = $c("SELECT COUNT(*) c FROM appointments WHERE appointment_type='online' AND meeting_event_id IS NOT NULL");
$t_completed = $c("SELECT COUNT(*) c FROM appointments WHERE meeting_status='completed'");
$t_started   = $c("SELECT COUNT(*) c FROM appointments WHERE meeting_status='started'");
$t_msgs      = $c("SELECT COUNT(*) c FROM telemedicine_chat_messages");

$pill = ['created' => 'pill-muted', 'started' => 'pill-info', 'completed' => 'pill-success', 'cancelled' => 'pill-danger'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Video Call History | Admin Panel</title>
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
                        <h4 class="mb-0 fw-bold">Video Call History</h4>
                        <small class="text-muted">All telemedicine consultations and their session status</small>
                    </div>
                    <a href="telemedicine-settings.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-cog me-1"></i> Settings</a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-video big-icon"></i><div class="num"><?= $t_all ?></div><div class="lbl">Total Rooms</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-play-circle big-icon"></i><div class="num"><?= $t_started ?></div><div class="lbl">In Progress</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-double big-icon"></i><div class="num"><?= $t_completed ?></div><div class="lbl">Completed</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-comment-dots big-icon"></i><div class="num"><?= $t_msgs ?></div><div class="lbl">Chat Messages</div></div></div>
                </div>

                <div class="filter-card">
                    <div class="filter-buttons" style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php foreach (['all' => 'All', 'created' => 'Not Started', 'started' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $lbl): ?>
                            <a href="?status=<?= $k ?>" class="filter-btn <?= $status_filter === $k ? 'active' : '' ?>"><?= $lbl ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">Consultations <span class="badge bg-secondary ms-2"><?= $rows ? $rows->num_rows : 0 ?></span></h3></div></div></div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Patient</th>
                                        <th>Scheduled</th>
                                        <th>Session</th>
                                        <th>Started</th>
                                        <th>Ended</th>
                                        <th>Chat</th>
                                        <th>View</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$rows || $rows->num_rows === 0): ?>
                                    <tr class="empty-row"><td colspan="8"><i class="fas fa-video fa-3x mb-3 d-block opacity-25"></i>No video consultations found.</td></tr>
                                <?php else: while ($r = $rows->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Doctor">
                                            <div class="cell-title"><?= htmlspecialchars($r['doctor_name']) ?></div>
                                            <div class="cell-sub"><?= htmlspecialchars($r['doctor_uid']) ?></div>
                                        </td>
                                        <td data-label="Patient"><?= htmlspecialchars($r['patient_name'] ?: '—') ?></td>
                                        <td data-label="Scheduled"><span class="cell-sub"><?= date('d M Y', strtotime($r['appointment_date'])) ?> · <?= date('h:i A', strtotime($r['appointment_time'])) ?></span></td>
                                        <td data-label="Session"><span class="pill <?= $pill[$r['meeting_status']] ?? 'pill-muted' ?>"><?= ucfirst($r['meeting_status']) ?></span></td>
                                        <td data-label="Started"><span class="cell-sub"><?= $r['meeting_started_at'] ? date('d M, h:i A', strtotime($r['meeting_started_at'])) : '—' ?></span></td>
                                        <td data-label="Ended"><span class="cell-sub"><?= $r['meeting_completed_at'] ? date('d M, h:i A', strtotime($r['meeting_completed_at'])) : '—' ?></span></td>
                                        <td data-label="Chat"><?= (int) $r['chat_count'] ?></td>
                                        <td data-label="View">
                                            <button type="button" class="tbl-action-btn bg-primary text-white" data-rx-view="<?= (int) $r['id'] ?>" title="Chat transcript &amp; prescription"><i class="fas fa-eye"></i></button>
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
                        <h5 class="modal-title"><i class="fas fa-notes-medical me-2"></i>Consultation — Chat &amp; Prescription</h5>
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
            .catch(function () { body.innerHTML = '<div class="alert alert-danger">Could not load.</div>'; });
    });
})();
</script>
</body>
</html>
