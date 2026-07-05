<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

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
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
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
        a.created_at,
        u.id as patient_id,
        u.name as patient_name,
        u.email as patient_email,
        u.mobile as patient_phone,
        u.profile_pic as patient_image,
        u.gender,
        u.dob,
        u.blood_group,
        TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
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

function appt_url($overrides, $status_filter, $search_query) {
    $params = array_filter([
        'status' => $status_filter,
        'search' => $search_query,
        'date'   => $overrides['date'] ?? null,
    ], fn($v) => $v !== null && $v !== '' && $v !== 'all');
    return 'appointments.php?' . http_build_query($params);
}

$sidebar_active = 'appointments';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - Appointments</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <style>
        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        .stats-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            text-align: center;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-no_show { background: #e2e3e5; color: #383d41; }
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .calendar-icon {
            cursor: pointer;
            background: #02c9b8;
            color: white;
            padding: 8px 12px;
            border-radius: 0 5px 5px 0;
        }
        .appointment-time {
            font-weight: bold;
            color: #2c5aa0;
        }
        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        .status-dropdown {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        .appointment-id {
            font-size: 10px;
            color: #666;
            font-family: monospace;
        }

        /* ── Week-strip date navigator ── */
        .week-strip-card{background:linear-gradient(135deg,var(--primary),var(--primary-dk));border-radius:14px;padding:14px 16px;margin-bottom:20px;color:#fff;}
        .week-strip-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
        .week-strip-nav a{color:#fff;font-size:1.1rem;text-decoration:none;padding:4px 10px;}
        .week-strip-nav a:hover{opacity:.75;}
        .week-strip-label{font-weight:600;font-size:.92rem;}
        .week-strip-days{display:flex;justify-content:space-between;gap:6px;}
        .week-day{flex:1;text-align:center;text-decoration:none;color:rgba(255,255,255,.85);padding:8px 2px;border-radius:10px;transition:.15s;}
        .week-day:hover{background:rgba(255,255,255,.12);color:#fff;text-decoration:none;}
        .week-day .wd-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;}
        .week-day .wd-num{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;font-weight:600;font-size:.9rem;}
        .week-day.is-today .wd-num{border:2px solid #fff;}
        .week-day.is-selected .wd-num{background:#fff;color:var(--primary);}
        .week-strip-all{font-size:.74rem;color:rgba(255,255,255,.85);text-decoration:underline;}
        .week-strip-all:hover{color:#fff;}

        /* ── Floating add button ── */
        .fab-add{position:fixed;right:28px;bottom:28px;width:56px;height:56px;border-radius:50%;
          background:#e07e18;color:#fff;display:flex;align-items:center;justify-content:center;
          font-size:1.4rem;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:1040;text-decoration:none;transition:.15s;}
        .fab-add:hover{background:#c96b0f;color:#fff;transform:scale(1.05);}

        @media (max-width: 768px) {
            .table-responsive { font-size: 12px; }
            .week-day .wd-label { font-size:.6rem; }
            .week-day .wd-num { width:26px;height:26px;font-size:.8rem; }
        }
    </style>
</head>
<body>
    <main class="doctor-content">
        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Week-strip date navigator -->
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <h6>Total</h6>
                    <h4 class="text-primary"><?= $stats['total_appointments'] ?? 0 ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <h6>Today</h6>
                    <h4 class="text-info"><?= $stats['today_count'] ?? 0 ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <h6>Pending</h6>
                    <h4 class="text-warning"><?= $stats['pending_count'] ?? 0 ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <h6>Approved</h6>
                    <h4 class="text-success"><?= $stats['approved_count'] ?? 0 ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <h6>Completed</h6>
                    <h4 class="text-secondary"><?= $stats['completed_count'] ?? 0 ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <h6>Rejected</h6>
                    <h4 class="text-danger"><?= $stats['rejected_count'] ?? 0 ?></h4>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                <div class="col-md-4">
                    <label>Status Filter</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Appointments</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="no_show" <?= $status_filter == 'no_show' ? 'selected' : '' ?>>No Show</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label>Search Patient</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Name, Email or Phone"
                           value="<?= htmlspecialchars($search_query) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                    <a href="appointments.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
        <div class="profile-card shadow">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Appointments (<?= $appointments_result->num_rows ?>)</h4>
                <a href="add-appointment.php" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Add New Appointment
                </a>
            </div>

            <?php if ($appointments_result->num_rows == 0): ?>
                <div class="text-center py-5">
                    <h5>No appointments found</h5>
                    <p class="text-muted">
                        <?= !empty($date_filter) ? 'No appointments on ' . date('d M Y', strtotime($date_filter)) . '.' : "You don't have any appointments yet." ?>
                    </p>
                    <a href="add-appointment.php" class="btn btn-primary">
                        <i class="fa fa-plus"></i> Create an Appointment
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Appointment Date & Time</th>
                                <th>Purpose</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php while ($appointment = $appointments_result->fetch_assoc()): ?>
                                <?php
                                $status_class = 'badge-' . ($appointment['status'] ?: 'pending');
                                $status_label = ucfirst(str_replace('_', ' ', $appointment['status'] ?: 'pending'));

                                // Check if appointment is today
                                $is_today = date('Y-m-d') == date('Y-m-d', strtotime($appointment['appointment_date']));
                                $is_past = strtotime($appointment['appointment_date']) < strtotime(date('Y-m-d'));
                                ?>

                                <tr <?= $is_today ? 'style="background-color: #e8f4f8;"' : '' ?>>
                                    <td>
                                        <div class="appointment-id">APT<?= str_pad($appointment['appointment_id'], 6, '0', STR_PAD_LEFT) ?></div>
                                        <small class="text-muted">#<?= $counter ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($appointment['patient_image'])): ?>
                                                <img src="<?= BASE_URL . $appointment['patient_image'] ?>"
                                                     class="patient-avatar"
                                                     onerror="this.src='<?= BASE_URL ?>assets/img/dummy.png'">
                                            <?php else: ?>
                                                <div class="patient-avatar bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fa fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($appointment['patient_name']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= $appointment['gender'] ?? 'N/A' ?> |
                                                    <?= $appointment['patient_age'] ?? '?' ?> yrs
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="appointment-time">
                                            <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                                        </div>
                                        <div class="text-muted">
                                            <?= date('d/m/Y', strtotime($appointment['appointment_date'])) ?>
                                        </div>
                                        <?php if ($is_today): ?>
                                            <span class="badge bg-info">Today</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="badge bg-secondary">Past</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($appointment['purpose'] ?? 'General Consultation') ?></small><br>
                                        <small class="text-muted">
                                            Created: <?= date('d/m/y', strtotime($appointment['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($appointment['patient_phone']): ?>
                                            <a href="tel:<?= $appointment['patient_phone'] ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-phone"></i> Call
                                            </a><br>
                                        <?php endif; ?>
                                        <?php if ($appointment['patient_email']): ?>
                                            <a href="mailto:<?= $appointment['patient_email'] ?>"
                                               class="btn btn-sm btn-outline-secondary mt-1">
                                                <i class="fa fa-envelope"></i> Email
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['appointment_id'] ?>">
                                            <select name="status" class="status-dropdown"
                                                    onchange="this.form.submit()"
                                                    <?= in_array($appointment['status'], ['completed', 'rejected'], true) ? 'disabled' : '' ?>>
                                                <option value="pending" <?= $appointment['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="approved" <?= $appointment['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="completed" <?= $appointment['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="rejected" <?= $appointment['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                <option value="no_show" <?= $appointment['status'] == 'no_show' ? 'selected' : '' ?>>No Show</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        <div class="mt-1">
                                            <span class="badge-status <?= $status_class ?>">
                                                <?= $status_label ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-info"
                                                    onclick="viewAppointmentDetails(<?= $appointment['appointment_id'] ?>)"
                                                    title="View Details">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <a href="appointments.php?cancel_appointment=<?= $appointment['appointment_id'] ?>"
                                               class="btn btn-danger" title="Cancel"
                                               onclick="return confirm('Cancel this appointment?')"
                                               <?= in_array($appointment['status'], ['rejected', 'completed'], true) ? 'disabled' : '' ?>>
                                                <i class="fa fa-times"></i>
                                            </a>
                                            <a href="patient-form.php?appointment_id=<?= $appointment['appointment_id'] ?>"
                                               class="btn btn-success" title="Add Prescription">
                                                <i class="fa fa-file-medical"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php $counter++; ?>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <a href="add-appointment.php" class="fab-add" title="Add New Appointment">
        <i class="fa fa-plus"></i>
    </a>

    <!-- Modal for Appointment Details -->
    <div class="modal fade" id="appointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentDetails">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // View appointment details
        function viewAppointmentDetails(appointmentId) {
            fetch('get-appointment-details.php?id=' + appointmentId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('appointmentDetails').innerHTML = data;
                    var modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                    modal.show();
                })
                .catch(error => {
                    document.getElementById('appointmentDetails').innerHTML =
                        '<div class="alert alert-danger">Error loading appointment details</div>';
                    var modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                    modal.show();
                });
        }
    </script>
</body>
</html>
