<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/../lib/Settlement.php");

require_once(__DIR__ . "/auth/guard.php");
$jwt_doctor  = doctor_jwt_guard();
$doctor_id   = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

// Get doctor's profile details
$doctor_sql = "SELECT name, email, profile_image, phone FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor_data = $doctor_result->fetch_assoc();

$doctor_name = $doctor_data['name'] ?? 'Doctor';
$doctor_email = $doctor_data['email'] ?? '';
$doctor_profile_image = !empty($doctor_data['profile_image']) ? $doctor_data['profile_image'] : 'assets/img/dummy.png';
$doctor_phone = $doctor_data['phone'] ?? '';

/*
 * appointments.status is an ENUM('pending','approved','rejected','completed','no_show').
 * Older code in this file used 'Confirmed'/'Cancelled', which aren't valid enum labels —
 * MySQL silently stored those as '' (blank), corrupting the row's status. Fixed to only
 * ever read/write the real enum values.
 */
$success_message = '';
$error_message = '';

// Handle appointment status updates
if (isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = $_POST['status'];
    $valid_statuses = ['pending', 'approved', 'rejected', 'completed', 'no_show'];

    if (!in_array($new_status, $valid_statuses, true)) {
        $error_message = "Invalid status value.";
    } else {
        // Verify appointment belongs to this doctor
        $check_sql = "SELECT id FROM appointments WHERE id = ? AND doctor_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('ii', $appointment_id, $doctor_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $update_sql = "UPDATE appointments SET status = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $new_status, $appointment_id);

            if ($update_stmt->execute()) {
                $success_message = "Appointment status updated successfully!";
                if ($new_status === 'completed') {
                    create_settlement_if_needed($conn, $appointment_id);
                }
            } else {
                $error_message = "Failed to update appointment status.";
            }
        } else {
            $error_message = "Appointment not found or unauthorized.";
        }
    }
}

// Handle appointment cancellation
if (isset($_GET['cancel_appointment'])) {
    $appointment_id = intval($_GET['cancel_appointment']);

    $cancel_sql = "UPDATE appointments SET status = 'rejected' WHERE id = ? AND doctor_id = ?";
    $cancel_stmt = $conn->prepare($cancel_sql);
    $cancel_stmt->bind_param('ii', $appointment_id, $doctor_id);

    if ($cancel_stmt->execute()) {
        $success_message = "Appointment cancelled successfully!";
    } else {
        $error_message = "Failed to cancel appointment.";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build query for appointments
$where_conditions = ["a.doctor_id = ?"];
$params = [$doctor_id];
$types = "i";

if ($status_filter != 'all' && !empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    // COALESCE so guest bookings (no `users` row, user_id IS NULL) are
    // still searchable via the patient_* columns stored on appointments.
    $where_conditions[] = "(COALESCE(u.name, a.patient_name) LIKE ? OR COALESCE(u.email, a.patient_email) LIKE ? OR COALESCE(u.mobile, a.patient_phone) LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get appointments with patient details
$appointments_sql = "
    SELECT
        a.id as appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.purpose,
        a.status,
        a.appointment_type,
        a.meeting_status,
        a.created_at,
        u.id as patient_id,
        COALESCE(u.name, a.patient_name) as patient_name,
        COALESCE(u.email, a.patient_email) as patient_email,
        COALESCE(u.mobile, a.patient_phone) as patient_phone,
        u.profile_pic as patient_image,
        u.gender,
        u.dob,
        u.blood_group,
        TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    $where_sql
    ORDER BY
        CASE
            WHEN a.status = 'pending' THEN 1
            WHEN a.status = 'approved' THEN 2
            WHEN a.status = 'completed' THEN 3
            WHEN a.status = 'no_show' THEN 4
            WHEN a.status = 'rejected' THEN 5
            ELSE 6
        END,
        a.appointment_date DESC,
        a.appointment_time ASC
";

$appointments_stmt = $conn->prepare($appointments_sql);

if (!empty($params)) {
    $appointments_stmt->bind_param($types, ...$params);
}

$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);

// Get appointment statistics
$stats_sql = "
    SELECT
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today_count
    FROM appointments
    WHERE doctor_id = ?
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $doctor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// ── Week-strip date navigator ──
$selected_date = $date_filter ?: date('Y-m-d');
$sel_dt = new DateTime($selected_date);
$dow = (int)$sel_dt->format('N'); // 1=Mon ... 7=Sun
$week_start_dt = (clone $sel_dt)->modify('-' . ($dow - 1) . ' days');
$week_days = [];
for ($i = 0; $i < 7; $i++) {
    $week_days[] = (clone $week_start_dt)->modify("+{$i} days");
}
$prev_week = (clone $week_start_dt)->modify('-7 days')->format('Y-m-d');
$next_week = (clone $week_start_dt)->modify('+7 days')->format('Y-m-d');

function appt_url($overrides, $status_filter, $search_query)
{
    $params = array_filter([
        'status' => $overrides['status'] ?? $status_filter,
        'search' => $search_query,
        'date'   => $overrides['date'] ?? null,
    ], fn($v) => $v !== null && $v !== '' && $v !== 'all');
    return 'appointments.php?' . http_build_query($params);
}

$sidebar_active = 'appointments';
require_once __DIR__ . '/inc/sidebar.php';

/* Status → colour map (shared by badges + week strip) */
$STATUS_META = [
    'pending'   => ['Pending',   '#f59e0b', '#fff7e6'],
    'approved'  => ['Approved',  '#0C74C5', '#e7f2fb'],
    'completed' => ['Completed', '#0e7c5b', '#e6f6f0'],
    'rejected'  => ['Cancelled', '#dc2626', '#fdecec'],
    'no_show'   => ['No Show',   '#6b7280', '#f1f2f4'],
];
function stat_card_link($key, $status_filter, $search_query)
{
    return appt_url(['status' => $key], $status_filter, $search_query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointments — REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/doctor.css">
    <style>
        .ap-h { font-size: 1.15rem; font-weight: 700; color: #1f2937; margin: 0; }
        .ap-sub { font-size: .8rem; color: #9ca3af; }

        /* ── Week strip ── */
        .week-strip-card { background: linear-gradient(135deg, var(--primary), var(--primary-dk)); border-radius: 14px; padding: 12px 14px; margin-bottom: 16px; color: #fff; }
        .week-strip-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .week-strip-nav a { color: #fff; text-decoration: none; padding: 2px 10px; font-size: 1rem; }
        .week-strip-label { font-weight: 600; font-size: .9rem; }
        .week-strip-days { display: flex; gap: 4px; }
        .week-day { flex: 1; text-align: center; text-decoration: none; color: rgba(255,255,255,.85); padding: 6px 2px; border-radius: 10px; transition: .15s; }
        .week-day:hover { background: rgba(255,255,255,.14); color: #fff; }
        .week-day .wd-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 3px; }
        .week-day .wd-num { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; font-weight: 600; font-size: .85rem; }
        .week-day.is-today .wd-num { border: 2px solid #fff; }
        .week-day.is-selected .wd-num { background: #fff; color: var(--primary); }
        .week-strip-all { font-size: .72rem; color: rgba(255,255,255,.9); text-decoration: underline; }

        /* ── Stat chips ── */
        .stat-row { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 16px; -webkit-overflow-scrolling: touch; }
        .stat-chip { flex: 1 0 96px; min-width: 96px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px 12px; text-decoration: none; transition: .15s; }
        .stat-chip:hover { border-color: var(--primary); box-shadow: 0 4px 14px rgba(12,116,197,.12); transform: translateY(-2px); }
        .stat-chip.active { border-color: var(--primary); background: #f0f7ff; }
        .stat-chip .sc-num { font-size: 1.35rem; font-weight: 800; line-height: 1; }
        .stat-chip .sc-lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; margin-top: 4px; }

        /* ── Card ── */
        .ap-panel { background: #fff; border-radius: 14px; box-shadow: 0 2px 14px rgba(17,24,39,.06); padding: 18px; }
        .ap-panel-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; }
        .filter-bar .form-control, .filter-bar .form-select { font-size: .85rem; }

        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; display: inline-block; }
        .patient-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; background: #eef2f7; display: flex; align-items: center; justify-content: center; color: #9ca3af; flex-shrink: 0; }
        .status-dropdown { padding: 3px 6px; border-radius: 6px; font-size: .78rem; border: 1px solid #d1d5db; max-width: 130px; }
        .ap-actions .btn { padding: 4px 9px; font-size: .8rem; }

        /* desktop table vs mobile cards */
        .ap-table-wrap { display: none; }
        .ap-cards-wrap { display: block; }
        @media (min-width: 768px) {
            .ap-table-wrap { display: block; }
            .ap-cards-wrap { display: none; }
        }
        table.ap-table { width: 100%; border-collapse: collapse; }
        table.ap-table th { background: #f0f7ff; color: var(--primary); font-size: .72rem; text-transform: uppercase; letter-spacing: .4px; padding: 9px 10px; text-align: left; white-space: nowrap; }
        table.ap-table td { padding: 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; font-size: .86rem; }
        table.ap-table tr.is-today td { background: #f0f7ff; }

        .ap-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; }
        .ap-card.is-today { border-color: var(--primary); background: #f6fbff; }
        .ap-card .ac-top { display: flex; gap: 10px; align-items: flex-start; justify-content: space-between; }
        .ap-card .ac-meta { font-size: .78rem; color: #6b7280; }
        .ap-card .ac-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 10px; }

        .fab-add { position: fixed; right: 20px; bottom: 20px; width: 52px; height: 52px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; box-shadow: 0 4px 14px rgba(0,0,0,.22); z-index: 1040; text-decoration: none; }
        .fab-add:hover { background: var(--accent-dk); color: #fff; }
        @media (min-width: 992px) { .fab-add { display: none; } }
    </style>
</head>

<body>
    <main class="doctor-content">

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-1"></i><?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-1"></i><?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="ap-h">Appointments</h1>
                <div class="ap-sub">Manage your patient consultations</div>
            </div>
            <a href="add-appointment.php" class="btn btn-primary btn-sm d-none d-lg-inline-flex align-items-center" style="background:var(--primary);border-color:var(--primary);">
                <i class="fa fa-plus me-1"></i> New Appointment
            </a>
        </div>

        <!-- Week strip -->
        <div class="week-strip-card">
            <div class="week-strip-nav">
                <a href="<?= appt_url(['date' => $prev_week], $status_filter, $search_query) ?>"><i class="fa fa-chevron-left"></i></a>
                <div class="week-strip-label"><?= $week_start_dt->format('M Y') ?></div>
                <a href="<?= appt_url(['date' => $next_week], $status_filter, $search_query) ?>"><i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="week-strip-days">
                <?php foreach ($week_days as $d): ?>
                    <?php
                    $d_str = $d->format('Y-m-d');
                    $is_today = $d_str === date('Y-m-d');
                    $is_selected = !empty($date_filter) && $d_str === $date_filter;
                    ?>
                    <a class="week-day<?= $is_today ? ' is-today' : '' ?><?= $is_selected ? ' is-selected' : '' ?>"
                        href="<?= appt_url(['date' => $d_str], $status_filter, $search_query) ?>">
                        <span class="wd-label"><?= $d->format('D') ?></span>
                        <span class="wd-num"><?= $d->format('j') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($date_filter)): ?>
                <div class="text-center mt-2">
                    <a class="week-strip-all" href="<?= appt_url(['date' => ''], $status_filter, $search_query) ?>">Show all dates</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stat chips (click to filter) -->
        <div class="stat-row">
            <a class="stat-chip <?= $status_filter === 'all' ? 'active' : '' ?>" href="<?= appt_url(['status' => 'all'], 'all', $search_query) ?>">
                <div class="sc-num" style="color:#1f2937;"><?= (int)($stats['total_appointments'] ?? 0) ?></div>
                <div class="sc-lbl">Total</div>
            </a>
            <a class="stat-chip" href="<?= appt_url(['date' => date('Y-m-d')], $status_filter, $search_query) ?>">
                <div class="sc-num" style="color:var(--accent-dk);"><?= (int)($stats['today_count'] ?? 0) ?></div>
                <div class="sc-lbl">Today</div>
            </a>
            <?php foreach (['pending' => 'pending_count', 'approved' => 'approved_count', 'completed' => 'completed_count', 'rejected' => 'rejected_count'] as $key => $col): ?>
                <a class="stat-chip <?= $status_filter === $key ? 'active' : '' ?>" href="<?= appt_url(['status' => $key], $status_filter, $search_query) ?>">
                    <div class="sc-num" style="color:<?= $STATUS_META[$key][1] ?>;"><?= (int)($stats[$col] ?? 0) ?></div>
                    <div class="sc-lbl"><?= $STATUS_META[$key][0] ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="ap-panel">
            <div class="ap-panel-head">
                <h5 class="mb-0" style="font-size:1rem;font-weight:700;color:#1f2937;">
                    <?= count($appointments) ?> appointment<?= count($appointments) === 1 ? '' : 's' ?>
                </h5>
                <form method="GET" action="" class="filter-bar">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                    <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <?php foreach (['all' => 'All statuses', 'pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Cancelled', 'no_show' => 'No Show'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= $status_filter === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" class="form-control form-control-sm" style="width:170px;"
                        placeholder="Name / phone / email" value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary);border-color:var(--primary);">
                        <i class="fa fa-search"></i>
                    </button>
                    <?php if ($status_filter !== 'all' || $search_query !== '' || $date_filter !== ''): ?>
                        <a href="appointments.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="text-center py-5">
                    <i class="fa fa-calendar-o" style="font-size:2.4rem;color:#d1d5db;"></i>
                    <h6 class="mt-3 mb-1">No appointments found</h6>
                    <p class="text-muted" style="font-size:.85rem;">
                        <?= !empty($date_filter) ? 'Nothing on ' . date('d M Y', strtotime($date_filter)) . '.' : 'Adjust the filters or add a new appointment.' ?>
                    </p>
                    <a href="add-appointment.php" class="btn btn-primary btn-sm" style="background:var(--primary);border-color:var(--primary);">
                        <i class="fa fa-plus me-1"></i> Add Appointment
                    </a>
                </div>
            <?php else: ?>

                <?php
                /* Reusable per-row bits */
                $render_status_form = function ($a) {
                    $disabled = in_array($a['status'], ['completed', 'rejected'], true) ? 'disabled' : '';
                    ob_start(); ?>
                    <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                        <input type="hidden" name="update_status" value="1">
                        <select name="status" class="status-dropdown" onchange="this.form.submit()" <?= $disabled ?>>
                            <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Cancelled', 'no_show' => 'No Show'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $a['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php return ob_get_clean();
                };
                $render_actions = function ($a) {
                    ob_start(); ?>
                    <div class="btn-group btn-group-sm ap-actions">
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#apView<?= $a['appointment_id'] ?>" title="Details">
                            <i class="fa fa-eye"></i>
                        </button>
                        <a href="patient-form.php?appointment_id=<?= $a['appointment_id'] ?>" class="btn btn-outline-success" title="Prescription">
                            <i class="fa fa-file-medical"></i>
                        </a>
                        <?php if ($a['status'] === 'approved' && $a['appointment_type'] === 'online' && $a['meeting_status'] !== 'cancelled'): ?>
                            <a href="<?= BASE_URL ?>telemedicine/join.php?appointment_id=<?= $a['appointment_id'] ?>" class="btn btn-primary" target="_blank" title="Join video call" style="background:var(--primary);border-color:var(--primary);">
                                <i class="fa fa-video"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!in_array($a['status'], ['rejected', 'completed'], true)): ?>
                            <a href="appointments.php?cancel_appointment=<?= $a['appointment_id'] ?>" class="btn btn-outline-danger"
                                onclick="return confirm('Cancel this appointment?')" title="Cancel">
                                <i class="fa fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php return ob_get_clean();
                };
                ?>

                <!-- Desktop table -->
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Date &amp; Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $a): ?>
                                <?php
                                $meta = $STATUS_META[$a['status']] ?? $STATUS_META['pending'];
                                $is_today = date('Y-m-d') === date('Y-m-d', strtotime($a['appointment_date']));
                                $is_past  = strtotime($a['appointment_date']) < strtotime(date('Y-m-d'));
                                ?>
                                <tr class="<?= $is_today ? 'is-today' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($a['patient_image'])): ?>
                                                <img src="<?= BASE_URL . $a['patient_image'] ?>" class="patient-avatar" alt="">
                                            <?php else: ?>
                                                <span class="patient-avatar"><i class="fa fa-user"></i></span>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($a['patient_name'] ?? 'Unknown') ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($a['gender'] ?? '—') ?> · <?= $a['patient_age'] !== null ? $a['patient_age'] . ' yrs' : '—' ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;color:var(--primary);"><?= date('h:i A', strtotime($a['appointment_time'])) ?></div>
                                        <small class="text-muted"><?= date('d M Y', strtotime($a['appointment_date'])) ?></small>
                                        <?php if ($is_today): ?><span class="badge-status" style="background:#e7f2fb;color:var(--primary);">Today</span>
                                        <?php elseif ($is_past): ?><span class="badge-status" style="background:#f1f2f4;color:#6b7280;">Past</span><?php endif; ?>
                                    </td>
                                    <td style="max-width:200px;">
                                        <small><?= htmlspecialchars($a['purpose'] ?: 'General consultation') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-status" style="background:<?= $meta[2] ?>;color:<?= $meta[1] ?>;"><?= $meta[0] ?></span>
                                        <div class="mt-1"><?= $render_status_form($a) ?></div>
                                    </td>
                                    <td><?= $render_actions($a) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="ap-cards-wrap">
                    <?php foreach ($appointments as $a): ?>
                        <?php
                        $meta = $STATUS_META[$a['status']] ?? $STATUS_META['pending'];
                        $is_today = date('Y-m-d') === date('Y-m-d', strtotime($a['appointment_date']));
                        ?>
                        <div class="ap-card <?= $is_today ? 'is-today' : '' ?>">
                            <div class="ac-top">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($a['patient_image'])): ?>
                                        <img src="<?= BASE_URL . $a['patient_image'] ?>" class="patient-avatar">
                                    <?php else: ?>
                                        <span class="patient-avatar"><i class="fa fa-user"></i></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($a['patient_name'] ?? 'Unknown') ?></strong>
                                        <div class="ac-meta"><?= htmlspecialchars($a['gender'] ?? '—') ?> · <?= $a['patient_age'] !== null ? $a['patient_age'] . ' yrs' : '—' ?></div>
                                    </div>
                                </div>
                                <span class="badge-status" style="background:<?= $meta[2] ?>;color:<?= $meta[1] ?>;"><?= $meta[0] ?></span>
                            </div>
                            <div class="ac-meta mt-2">
                                <i class="fa fa-clock-o me-1"></i><?= date('h:i A', strtotime($a['appointment_time'])) ?>
                                &nbsp;·&nbsp; <i class="fa fa-calendar me-1"></i><?= date('d M Y', strtotime($a['appointment_date'])) ?>
                                <?php if ($is_today): ?> &nbsp;<span class="badge-status" style="background:#e7f2fb;color:var(--primary);">Today</span><?php endif; ?>
                            </div>
                            <?php if (!empty($a['purpose'])): ?>
                                <div class="ac-meta mt-1"><i class="fa fa-comment-o me-1"></i><?= htmlspecialchars($a['purpose']) ?></div>
                            <?php endif; ?>
                            <div class="ac-row">
                                <?= $render_status_form($a) ?>
                                <?= $render_actions($a) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>
    </main>

    <a href="add-appointment.php" class="fab-add" title="New appointment"><i class="fa fa-plus"></i></a>

    <!-- Per-appointment detail modals -->
    <?php foreach ($appointments as $a): ?>
        <?php $meta = $STATUS_META[$a['status']] ?? $STATUS_META['pending']; ?>
        <div class="modal fade" id="apView<?= $a['appointment_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background:var(--primary);color:#fff;">
                        <h5 class="modal-title" style="font-size:1rem;">
                            <i class="fa fa-calendar-check me-2"></i>APT<?= str_pad($a['appointment_id'], 6, '0', STR_PAD_LEFT) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="font-size:.88rem;">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <?php if (!empty($a['patient_image'])): ?>
                                <img src="<?= BASE_URL . $a['patient_image'] ?>" class="patient-avatar" style="width:48px;height:48px;">
                            <?php else: ?>
                                <span class="patient-avatar" style="width:48px;height:48px;font-size:1.1rem;"><i class="fa fa-user"></i></span>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:700;"><?= htmlspecialchars($a['patient_name'] ?? 'Unknown') ?></div>
                                <div class="text-muted" style="font-size:.8rem;">
                                    <?= htmlspecialchars($a['gender'] ?? '—') ?> · <?= $a['patient_age'] !== null ? $a['patient_age'] . ' yrs' : '—' ?>
                                    <?php if (!empty($a['blood_group'])): ?> · <?= htmlspecialchars($a['blood_group']) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><div class="text-muted small">Date</div><div><?= date('d M Y', strtotime($a['appointment_date'])) ?></div></div>
                            <div class="col-6"><div class="text-muted small">Time</div><div><?= date('h:i A', strtotime($a['appointment_time'])) ?></div></div>
                            <div class="col-6"><div class="text-muted small">Type</div><div><?= $a['appointment_type'] === 'online' ? 'Online consultation' : 'In-clinic visit' ?></div></div>
                            <div class="col-6"><div class="text-muted small">Status</div><div><span class="badge-status" style="background:<?= $meta[2] ?>;color:<?= $meta[1] ?>;"><?= $meta[0] ?></span></div></div>
                            <?php if (!empty($a['patient_phone'])): ?>
                                <div class="col-6"><div class="text-muted small">Phone</div><div><a href="tel:<?= htmlspecialchars($a['patient_phone']) ?>"><?= htmlspecialchars($a['patient_phone']) ?></a></div></div>
                            <?php endif; ?>
                            <?php if (!empty($a['patient_email'])): ?>
                                <div class="col-6"><div class="text-muted small">Email</div><div class="text-truncate"><a href="mailto:<?= htmlspecialchars($a['patient_email']) ?>"><?= htmlspecialchars($a['patient_email']) ?></a></div></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($a['purpose'])): ?>
                            <div class="mt-3"><div class="text-muted small">Purpose / Complaint</div><div class="border rounded p-2 mt-1"><?= nl2br(htmlspecialchars($a['purpose'])) ?></div></div>
                        <?php endif; ?>
                        <div class="text-muted small mt-3">Booked <?= date('d M Y', strtotime($a['created_at'])) ?></div>
                    </div>
                    <div class="modal-footer">
                        <a href="patient-form.php?appointment_id=<?= $a['appointment_id'] ?>" class="btn btn-success btn-sm">
                            <i class="fa fa-file-medical me-1"></i> Prescription
                        </a>
                        <a href="opd-slip.php?appointment_id=<?= $a['appointment_id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-download me-1"></i> Rx PDF
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>
