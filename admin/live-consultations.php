<?php
/**
 * Admin → Telemedicine → Live Consultations
 * Rooms with a recent presence heartbeat — i.e. a call happening right now
 * (or one side sitting in the waiting room). Auto-refreshes every 15s.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$rows = $conn->query("
    SELECT tr.room, tr.appointment_id, tr.doctor_name, tr.patient_name,
           tr.doctor_last_seen, tr.patient_last_seen, tr.ready_sent, tr.created_at,
           a.appointment_date, a.appointment_time, a.purpose, a.meeting_status,
           d.name AS doc_full,
           (SELECT COUNT(*) FROM telemedicine_chat_messages c WHERE c.appointment_id = tr.appointment_id) AS chat_count
    FROM telemedicine_rooms tr
    JOIN appointments a ON a.id = tr.appointment_id
    JOIN doctors d ON d.id = a.doctor_id
    WHERE tr.doctor_last_seen  >= (NOW() - INTERVAL 40 SECOND)
       OR tr.patient_last_seen >= (NOW() - INTERVAL 40 SECOND)
    ORDER BY GREATEST(COALESCE(tr.doctor_last_seen,0), COALESCE(tr.patient_last_seen,0)) DESC
");

function presence_pill($lastSeen)
{
    if (!$lastSeen) return '<span class="pill pill-muted">Absent</span>';
    $age = time() - strtotime($lastSeen);
    if ($age <= 10) return '<span class="pill pill-success">Present</span>';
    if ($age <= 40) return '<span class="pill pill-warn">Idle ' . $age . 's</span>';
    return '<span class="pill pill-muted">Gone</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta http-equiv="refresh" content="15">
    <title>Live Consultations | Admin Panel</title>
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
                        <h4 class="mb-0 fw-bold">Live Consultations</h4>
                        <small class="text-muted">Video rooms with an active presence heartbeat &middot; auto-refreshes every 15s</small>
                    </div>
                    <a href="video-call-history.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-history me-1"></i> Call History</a>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0"><i class="fas fa-circle text-success me-2" style="font-size:.6rem;vertical-align:middle;"></i>Active Now <span class="badge bg-secondary ms-2"><?= $rows ? $rows->num_rows : 0 ?></span></h3></div></div></div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Patient</th>
                                        <th>Scheduled</th>
                                        <th>Doc Presence</th>
                                        <th>Pat Presence</th>
                                        <th>Connected</th>
                                        <th>Chat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$rows || $rows->num_rows === 0): ?>
                                    <tr class="empty-row"><td colspan="7"><i class="fas fa-mug-hot fa-3x mb-3 d-block opacity-25"></i>No consultations in progress right now.</td></tr>
                                <?php else: while ($r = $rows->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Doctor"><div class="cell-title"><?= htmlspecialchars($r['doc_full']) ?></div></td>
                                        <td data-label="Patient"><?= htmlspecialchars($r['patient_name'] ?: '—') ?></td>
                                        <td data-label="Scheduled"><span class="cell-sub"><?= date('d M', strtotime($r['appointment_date'])) ?> · <?= date('h:i A', strtotime($r['appointment_time'])) ?></span></td>
                                        <td data-label="Doctor presence"><?= presence_pill($r['doctor_last_seen']) ?></td>
                                        <td data-label="Patient presence"><?= presence_pill($r['patient_last_seen']) ?></td>
                                        <td data-label="Connected"><?= $r['ready_sent'] ? '<span class="pill pill-success">Yes</span>' : '<span class="pill pill-muted">Waiting</span>' ?></td>
                                        <td data-label="Chat"><?= (int) $r['chat_count'] ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
</body>
</html>
