<?php
require_once __DIR__ . '/db-conn.php';
include_once "functions.php";
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

// Approve / Reject / Assign Doctor / Delete are all handled via AJAX now
// (see admin/ajax/appointment-*.php) — this page only renders the list.

// Fetch all appointments with filters
$status_filter = $_GET['status'] ?? 'pending';
$search_query = $_GET['search'] ?? '';

$sql = "
    SELECT 
        a.*,
        u.name as user_name,
        u.email as user_email,
        u.dob,
        u.gender,
        d.name as doctor_name,
        d.specialization,
        d.consultation_fee,
        d.email as doctor_email,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
        DATE_FORMAT(a.appointment_date, '%d/%m/%Y') as formatted_date,
        DATE_FORMAT(a.created_at, '%d/%m/%Y %h:%i %p') as created_at_formatted
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_query)) {
    $sql .= " AND (u.name LIKE ? OR d.name LIKE ? OR a.purpose LIKE ?)";
    $search_term = "%$search_query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch available doctors for assignment
$doctors_sql = "SELECT id, name, specialization FROM doctors WHERE status = 'Active' ORDER BY name";
$doctors_result = $conn->query($doctors_sql);

// Count appointments by status — appointments.status is
// ENUM('pending','approved','rejected','completed','no_show'); there's no 'cancelled'.
$count_sql = "
    SELECT
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(*) as total
    FROM appointments
";
$count_result = $conn->query($count_sql);
$counts = $count_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Appointment Management | Admin Panel</title>

    <?php include "links.php"; ?>

    <style>
        /* page-specific only */
        .appointment-actions  { padding:15px; border-top:1px solid #e9ecef; background:#f8f9fa; display:flex; justify-content:space-between; gap:10px; }
        .status-badge         { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:uppercase; display:inline-block; min-width:80px; text-align:center; }
        .status-pending       { background:#fff3cd; color:#856404; border:1px solid #ffd97a; }
        .status-confirmed,
        .status-approved      { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .status-completed     { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
        .status-cancelled     { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .status-rejected      { background:#f5f5f5; color:#666;    border:1px solid #e9ecef; }
        .detail-row           { display:flex; justify-content:space-between; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f8f9fa; }
        .detail-label         { font-weight:600; color:#495057; }
        .detail-value         { color:#212529; }
        .stats-card           { text-align:center; padding:15px; border-radius:8px; color:#fff; margin-bottom:15px; }
        .stats-card.pending   { background:#ea580c; }
        .stats-card.confirmed { background:#16a34a; }
        .stats-card.completed { background:#0891b2; }
        .stats-card.no-show   { background:#6b7280; }
        .stats-number         { font-size:24px; font-weight:700; margin-bottom:5px; }
        .modal-details .detail-row { border-bottom:1px solid #dee2e6; padding:10px 0; }
        .empty-state          { background:#f8f9fa; border-radius:10px; border:2px dashed #dee2e6; }
        .empty-state-icon     { opacity:.5; }
        .selected-row         { background:rgba(12,116,197,.08) !important; }
        .modal-header         { background: #0C74C5; }
        .modal-body .card-header { background:#f8f9fa; font-weight:600; border-bottom:2px solid #0C74C5; }
        .table td             { vertical-align:middle; padding:12px 8px; }
        .table tbody tr:hover { background:rgba(12,116,197,.04); }
        @media (max-width:768px) {
            .table-responsive { border:1px solid #dee2e6; border-radius:8px; overflow-x:auto; }
            .table th,.table td { white-space:nowrap; min-width:100px; }
            .btn-group { flex-wrap:wrap; gap:5px; }
        }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "../admin/header.php"; ?>

    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "../admin/top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Appointment Management</h2>
                                    </div>
                                    <div>
                                        <a href="book-appointment.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i> Create New Appointment
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Success/Error Messages -->
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $success_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $error_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="appointment-container">
                                    <!-- Statistics -->
                                    <div class="row mb-4">
                                        <div class="col-md-2 col-6">
                                            <div class="stats-card pending">
                                                <div class="stats-number"><?= $counts['pending'] ?? 0 ?></div>
                                                <div>Pending</div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-6">
                                            <div class="stats-card confirmed">
                                                <div class="stats-number"><?= $counts['approved'] ?? 0 ?></div>
                                                <div>Approved</div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-6">
                                            <div class="stats-card completed">
                                                <div class="stats-number"><?= $counts['completed'] ?? 0 ?></div>
                                                <div>Completed</div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-6">
                                            <div class="stats-card no-show">
                                                <div class="stats-number"><?= $counts['no_show'] ?? 0 ?></div>
                                                <div>No Show</div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-6">
                                            <div class="stats-card">
                                                <div class="stats-number"><?= $counts['rejected'] ?? 0 ?></div>
                                                <div>Rejected</div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-6">
                                            <div class="stats-card" style="background:#0C74C5;">
                                                <div class="stats-number"><?= $counts['total'] ?? 0 ?></div>
                                                <div>Total</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Filter Section -->
                                    <div class="filter-section">
                                        <form method="GET" action="" class="row g-3">
                                            <div class="col-md-8">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="search"
                                                        placeholder="Search by patient name, doctor name or purpose..."
                                                        value="<?= htmlspecialchars($search_query) ?>">
                                                    <button class="btn btn-primary" type="submit">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select" name="status" onchange="this.form.submit()">
                                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="no_show" <?= $status_filter === 'no_show' ? 'selected' : '' ?>>No Show</option>
                                                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                </select>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Appointments Table -->
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="80">ID</th>
                                                        <th>Patient</th>
                                                        <th>Doctor</th>
                                                        <th width="120">Date & Time</th>
                                                        <th width="100">Fee</th>
                                                        <th width="100">Status</th>
                                                        <th width="180">Purpose</th>
                                                        <th width="120">Booked On</th>
                                                        <th width="180" class="text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($row = $result->fetch_assoc()):
                                                        $status_class = strtolower($row['status']);
                                                        $patient_age = !empty($row['dob'])
                                                            ? date_diff(date_create($row['dob']), date_create('today'))->y
                                                            : 'N/A';
                                                    ?>
                                                        <tr id="apptRow<?= $row['id'] ?>" data-status="<?= $status_class ?>" data-has-doctor="<?= $row['doctor_id'] ? '1' : '0' ?>">
                                                            <td>
                                                                <span class="badge bg-dark">AP<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="me-2">
                                                                        <i class="fas fa-user-circle fa-lg text-primary"></i>
                                                                    </div>
                                                                    <div>
                                                                        <strong><?= htmlspecialchars($row['user_name'] ?? $row['patient_name']) ?></strong>
                                                                        <div class="text-muted small">
                                                                            <?= $patient_age ?>y / <?= $row['gender'] ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td id="doctorCell<?= $row['id'] ?>">
                                                                <?php if ($row['doctor_name']): ?>
                                                                    <div>
                                                                        <strong>Dr. <?= htmlspecialchars($row['doctor_name']) ?></strong>
                                                                        <div class="text-muted small">
                                                                            <?= htmlspecialchars($row['specialization']) ?>
                                                                        </div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark">Not Assigned</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="text-nowrap">
                                                                    <div><strong><?= $row['formatted_date'] ?></strong></div>
                                                                    <small class="text-muted"><?= $row['formatted_time'] ?></small>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-success">₹<?= isset($row['consultation_fee']) 
                                                                    ? number_format($row['consultation_fee']) 
                                                                    : 'Direct Booking' ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge status-<?= $status_class ?>" id="statusBadge<?= $row['id'] ?>">
                                                                    <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="text-truncate" style="max-width: 180px;"
                                                                    data-bs-toggle="tooltip"
                                                                    data-bs-title="<?= htmlspecialchars($row['purpose']) ?>">
                                                                    <?= htmlspecialchars(substr($row['purpose'], 0, 30)) ?>
                                                                    <?= strlen($row['purpose']) > 30 ? '...' : '' ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted"><?= $row['created_at_formatted'] ?></small>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex justify-content-center gap-2" id="actionsCell<?= $row['id'] ?>">
                                                                    <!-- Quick View Button -->
                                                                    <button type="button" class="btn btn-sm btn-outline-info"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#viewModal<?= $row['id'] ?>"
                                                                        title="View Details">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>

                                                                    <?php if ($row['status'] === 'pending'): ?>
                                                                        <!-- Approve Button -->
                                                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#approveModal<?= $row['id'] ?>"
                                                                            title="Approve">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                        <!-- Reject Button -->
                                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#rejectModal<?= $row['id'] ?>"
                                                                            title="Reject">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    <?php endif; ?>

                                                                    <?php if (!$row['doctor_id']): ?>
                                                                        <!-- Assign Doctor Button -->
                                                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#assignDoctorModal<?= $row['id'] ?>"
                                                                            title="Assign Doctor">
                                                                            <i class="fas fa-user-md"></i>
                                                                        </button>
                                                                    <?php endif; ?>

                                                                    <!-- Delete Button -->
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-outline-danger btn-delete-appointment"
                                                                        data-id="<?= $row['id'] ?>"
                                                                        title="Delete">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>

                                                        <!-- View Details Modal -->
                                                        <div class="modal fade" id="viewModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header bg-primary text-white">
                                                                        <h5 class="modal-title">
                                                                            <i class="fas fa-calendar-check me-2"></i>
                                                                            Appointment Details - AP<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?>
                                                                        </h5>
                                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="row">
                                                                            <div class="col-md-6">
                                                                                <div class="card mb-3">
                                                                                    <div class="card-header bg-light">
                                                                                        <h6 class="mb-0">Patient Information</h6>
                                                                                    </div>
                                                                                    <div class="card-body">
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Name:</strong></div>
                                                                                            <div class="col-8"><?= htmlspecialchars($row['user_name'] ?? $row['patient_name']) ?></div>
                                                                                        </div>
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Age/Gender:</strong></div>
                                                                                            <div class="col-8"><?= $patient_age ?> years / <?= $row['gender'] ?></div>
                                                                                        </div>
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Email:</strong></div>
                                                                                            <div class="col-8"><?= htmlspecialchars($row['user_email'] ?? $row['patient_email']) ?></div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <div class="card mb-3">
                                                                                    <div class="card-header bg-light">
                                                                                        <h6 class="mb-0">Doctor Information</h6>
                                                                                    </div>
                                                                                    <div class="card-body">
                                                                                        <?php if ($row['doctor_name']): ?>
                                                                                            <div class="row mb-2">
                                                                                                <div class="col-4"><strong>Name:</strong></div>
                                                                                                <div class="col-8">Dr. <?= htmlspecialchars($row['doctor_name']) ?></div>
                                                                                            </div>
                                                                                            <div class="row mb-2">
                                                                                                <div class="col-4"><strong>Specialization:</strong></div>
                                                                                                <div class="col-8"><?= htmlspecialchars($row['specialization']) ?></div>
                                                                                            </div>
                                                                                            <div class="row mb-2">
                                                                                                <div class="col-4"><strong>Email:</strong></div>
                                                                                                <div class="col-8"><?= htmlspecialchars($row['doctor_email'] ?? '') ?></div>
                                                                                            </div>
                                                                                        <?php else: ?>
                                                                                            <div class="alert alert-warning mb-0">
                                                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                                                Doctor not assigned yet
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="card">
                                                                            <div class="card-header bg-light">
                                                                                <h6 class="mb-0">Appointment Details</h6>
                                                                            </div>
                                                                            <div class="card-body">
                                                                                <div class="row">
                                                                                    <div class="col-md-6">
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Date:</strong></div>
                                                                                            <div class="col-8"><?= $row['formatted_date'] ?></div>
                                                                                        </div>
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Time:</strong></div>
                                                                                            <div class="col-8"><?= $row['formatted_time'] ?></div>
                                                                                        </div>
                                                                                       <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Fee:</strong></div>

                                                                                            <?php if (!empty($row['consultation_fee'])) { ?>
                                                                                                <div class="col-8">₹<?= number_format((float)$row['consultation_fee']) ?></div>
                                                                                            <?php } else { ?>
                                                                                                <div class="col-8">
                                                                                                    <span class="badge bg-warning text-dark">Direct Booking</span>
                                                                                                </div>
                                                                                            <?php } ?>
                                                                                        </div>

                                                                                    </div>
                                                                                    <div class="col-md-6">
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Status:</strong></div>
                                                                                            <div class="col-8">
                                                                                                <span class="status-badge status-<?= $status_class ?>">
                                                                                                    <?= ucfirst($row['status']) ?>
                                                                                                </span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="row mb-2">
                                                                                            <div class="col-4"><strong>Booked On:</strong></div>
                                                                                            <div class="col-8"><?= $row['created_at_formatted'] ?></div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="mt-3">
                                                                                    <strong>Purpose of Visit:</strong>
                                                                                    <div class="border rounded p-3 mt-2 bg-dark">
                                                                                        <?= nl2br(htmlspecialchars($row['purpose'])) ?>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                        <?php if ($row['status'] === 'pending'): ?>
                                                                            <button type="button" class="btn btn-success"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#approveModal<?= $row['id'] ?>"
                                                                                data-bs-dismiss="modal">
                                                                                <i class="fas fa-check me-1"></i> Approve
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Approve Modal -->
                                                        <div class="modal fade" id="approveModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form class="ajax-appt-form" data-action="approve">
                                                                        <div class="modal-header bg-success text-white">
                                                                            <h5 class="modal-title">
                                                                                <i class="fas fa-check-circle me-2"></i>
                                                                                Approve Appointment
                                                                            </h5>
                                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="alert alert-info">
                                                                                <i class="fas fa-info-circle me-2"></i>
                                                                                <strong>Appointment Details:</strong><br>
                                                                                <strong>Patient:</strong> <?= htmlspecialchars($row['user_name'] ?? $row['patient_name']) ?><br>
                                                                                <strong>Date:</strong> <?= $row['formatted_date'] ?><br>
                                                                                <strong>Time:</strong> <?= $row['formatted_time'] ?>
                                                                            </div>
                                                                            <p>Are you sure you want to approve this appointment? A confirmation email will be sent to the patient (and doctor, if assigned).</p>
                                                                            <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-success">
                                                                                <i class="fas fa-check me-1"></i> Approve Appointment
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Reject Modal -->
                                                        <div class="modal fade" id="rejectModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form class="ajax-appt-form" data-action="reject">
                                                                        <div class="modal-header bg-danger text-white">
                                                                            <h5 class="modal-title">
                                                                                <i class="fas fa-times-circle me-2"></i>
                                                                                Reject Appointment
                                                                            </h5>
                                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="alert alert-warning">
                                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                                <strong>Appointment Details:</strong><br>
                                                                                <strong>Patient:</strong> <?= htmlspecialchars($row['user_name'] ?? $row['patient_name']) ?><br>
                                                                                <strong>Date:</strong> <?= $row['formatted_date'] ?><br>
                                                                                <strong>Time:</strong> <?= $row['formatted_time'] ?>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label for="rejectionReason<?= $row['id'] ?>" class="form-label">
                                                                                    <strong>Reason for Rejection (Optional):</strong>
                                                                                </label>
                                                                                <textarea class="form-control" id="rejectionReason<?= $row['id'] ?>"
                                                                                    name="rejection_reason" rows="3"
                                                                                    placeholder="Please provide a reason for rejection..."></textarea>
                                                                                <div class="form-text">
                                                                                    This will be visible to the patient if provided.
                                                                                </div>
                                                                            </div>
                                                                            <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-danger">
                                                                                <i class="fas fa-times me-1"></i> Reject Appointment
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Assign Doctor Modal -->
                                                        <div class="modal fade" id="assignDoctorModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form method="POST" action="">
                                                                        <div class="modal-header bg-warning text-dark">
                                                                            <h5 class="modal-title">
                                                                                <i class="fas fa-user-md me-2"></i>
                                                                                Assign Doctor
                                                                            </h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="alert alert-info">
                                                                                <i class="fas fa-info-circle me-2"></i>
                                                                                <strong>Appointment Details:</strong><br>
                                                                                <strong>Patient:</strong> <?= htmlspecialchars($row['user_name']) ?><br>
                                                                                <strong>Date:</strong> <?= $row['formatted_date'] ?><br>
                                                                                <strong>Time:</strong> <?= $row['formatted_time'] ?>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label for="doctorSelect<?= $row['id'] ?>" class="form-label">
                                                                                    <strong>Select Doctor:</strong>
                                                                                </label>
                                                                                <select class="form-select" id="doctorSelect<?= $row['id'] ?>" name="new_doctor_id" required>
                                                                                    <option value="">-- Select Doctor --</option>
                                                                                    <?php
                                                                                    $doctors_result->data_seek(0); // Reset pointer
                                                                                    while ($doctor = $doctors_result->fetch_assoc()):
                                                                                    ?>
                                                                                        <option value="<?= $doctor['id'] ?>"
                                                                                            <?= ($doctor['id'] == $row['doctor_id']) ? 'selected' : '' ?>>
                                                                                            Dr. <?= htmlspecialchars($doctor['name']) ?>
                                                                                            (<?= htmlspecialchars($doctor['specialization']) ?>)
                                                                                        </option>
                                                                                    <?php endwhile; ?>
                                                                                </select>
                                                                            </div>
                                                                            <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" name="assign_doctor" class="btn btn-warning text-dark">
                                                                                <i class="fas fa-user-md me-1"></i> Assign Doctor
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    <?php endwhile; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="9" class="text-center text-muted">
                                                            Showing <?= $result->num_rows ?> appointment<?= $result->num_rows !== 1 ? 's' : '' ?>
                                                            <?php if ($search_query): ?>
                                                                matching "<?= htmlspecialchars($search_query) ?>"
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                    <?php else: ?>
                                        <div class="empty-state text-center py-5">
                                            <div class="empty-state-icon mb-4">
                                                <i class="fas fa-calendar-times fa-4x text-muted"></i>
                                            </div>
                                            <h4 class="text-muted mb-3">No Appointments Found</h4>
                                            <p class="text-muted mb-4">
                                                <?php if ($search_query || $status_filter !== 'all'): ?>
                                                    No appointments match your search criteria.
                                                    <?php if ($search_query): ?>
                                                        <br><strong>Search:</strong> "<?= htmlspecialchars($search_query) ?>"
                                                    <?php endif; ?>
                                                    <?php if ($status_filter !== 'all'): ?>
                                                        <br><strong>Status:</strong> <?= ucfirst($status_filter) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    There are no appointments in the system yet.
                                                <?php endif; ?>
                                            </p>
                                            <div class="d-flex justify-content-center gap-3">
                                                <?php if ($search_query || $status_filter !== 'all'): ?>
                                                    <a href="?status=all" class="btn btn-outline-primary">
                                                        <i class="fas fa-undo me-2"></i> Clear Filters
                                                    </a>
                                                <?php endif; ?>
                                                <a href="book-appointment.php" class="btn btn-primary">
                                                    <i class="fas fa-plus me-2"></i> Create New Appointment
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>



                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "../admin/footer.php"; ?>
    </section>

    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });

        // Row selection functionality
        let selectedRows = new Set();

        function selectAllRows() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = true;
                selectedRows.add(cb.value);
            });
            updateSelectedCount();
        }

        function deselectAllRows() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
                selectedRows.delete(cb.value);
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = selectedRows.size;

            const container = document.getElementById('selectedAppointments');
            container.innerHTML = '';

            if (selectedRows.size > 0) {
                selectedRows.forEach(id => {
                    const div = document.createElement('div');
                    div.className = 'selected-appointment d-flex justify-content-between align-items-center mb-2';
                    div.innerHTML = `
                    <span>Appointment ID: AP${String(id).padStart(6, '0')}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSelected('${id}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<div class="text-muted text-center py-3">No appointments selected</div>';
            }
        }

        function removeSelected(id) {
            const checkbox = document.querySelector(`.row-checkbox[value="${id}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            selectedRows.delete(id);
            updateSelectedCount();
        }

        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
                if (source.checked) {
                    selectedRows.add(checkbox.value);
                } else {
                    selectedRows.delete(checkbox.value);
                }
            });
            updateSelectedCount();
        }

        function updateSelected(checkbox) {
            if (checkbox.checked) {
                selectedRows.add(checkbox.value);
            } else {
                selectedRows.delete(checkbox.value);
            }
            updateSelectedCount();

            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.row-checkbox');
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            document.getElementById('selectAll').checked = allChecked;
        }
    </script>
    <script>
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>