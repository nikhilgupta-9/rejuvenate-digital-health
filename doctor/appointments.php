<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// Start session and check doctor login
// session_start();
if (!isset($_SESSION['doctor_logged_in']) || $_SESSION['doctor_logged_in'] !== true) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];

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

// Handle appointment status updates
if (isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
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

// Handle appointment cancellation
if (isset($_GET['cancel_appointment'])) {
    $appointment_id = intval($_GET['cancel_appointment']);
    
    $cancel_sql = "UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND doctor_id = ?";
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
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? AND a.approved_by_admin = 1)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get appointments with patient details - UPDATED QUERY without appointment_uid
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
            WHEN a.status = 'Pending' THEN 1
            WHEN a.status = 'Confirmed' THEN 2
            WHEN a.status = 'Completed' THEN 3
            WHEN a.status = 'Cancelled' THEN 4
            ELSE 5
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
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today_count
    FROM appointments
    WHERE doctor_id = ?
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $doctor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
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
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .btn-upload {
            background-color: green;
            color: #fff;
            font-size: 12px;
        }
        .btn-upload:hover {
            background-color: darkgreen;
        }
        .btn-delete {
            font-size: 12px;
            background-color: red;
            color: #fff;
        }
        .btn-delete:hover {
            background-color: darkred;
        }
        .sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            height: fit-content;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #02c9b8;
            color: white;
        }
        .userd-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #02c9b8;
        }
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
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .menu-btn {
            display: none;
            background: #02c9b8;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
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
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .sidebar.show { display: block;         
                display: block;
                width: 280px;
                height: 100vh;}
            .menu-btn { display: block; }
            .table-responsive { font-size: 12px; }
        }
    </style>
</head>
<body>
    <?php include("../header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row mb-5">
                <!-- Sidebar -->
                <div class="col-md-3">
                    <div class="sidebar" id="sidebarMenu">
                        <div class="text-center info-content">
                            <img src="<?=  $doctor_profile_image ?>" class="userd-image">
                            <h5>Dr. <?= htmlspecialchars($doctor_name) ?></h5>
                            <p><?= htmlspecialchars($doctor_email) ?></p>
                            <p>Phone: <?= htmlspecialchars($doctor_phone) ?></p>
                            <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Profile</a>
                        </div>

                        <a href="<?= $site ?>doctor/doctor-dashboard.php">Dashboard</a>
                        <a href="<?= $site ?>doctor/my-patients.php">My Patients</a>
                        <a href="<?= $site ?>doctor/appointments.php" class="active">Appointments</a>
                        <a href="<?= $site ?>doctor/patient-form.php">Patient Form</a>
                        <a href="<?= $site ?>doctor/my-contact.php">Contact Us</a>
                        <a href="<?= $site ?>doctor/doctor-about.php">About Us</a>
                        <a href="<?= $site ?>doctor-logout.php">Logout</a>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
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
                                <h6>Confirmed</h6>
                                <h4 class="text-success"><?= $stats['confirmed_count'] ?? 0 ?></h4>
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
                                <h6>Cancelled</h6>
                                <h4 class="text-danger"><?= $stats['cancelled_count'] ?? 0 ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label>Status Filter</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Appointments</option>
                                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Confirmed" <?= $status_filter == 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Date Filter</label>
                                <div class="input-group">
                                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                                    <span class="input-group-text calendar-icon" onclick="showDatePicker()">
                                        <i class="fa fa-calendar"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
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
                            <!-- <a href="add-appointment.php" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus"></i> Add New Appointment
                            </a> -->
                        </div>
                        
                        <?php if ($appointments_result->num_rows == 0): ?>
                            <div class="text-center py-5">
                                <h5>No appointments found</h5>
                                <p class="text-muted">You don't have any appointments yet.</p>
                                <!-- <a href="add-appointment.php" class="btn btn-primary">
                                    <i class="fa fa-plus"></i> Create Your First Appointment
                                </a> -->
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
                                            $status_class = '';
                                            switch ($appointment['status']) {
                                                case 'Pending': $status_class = 'badge-pending'; break;
                                                case 'Confirmed': $status_class = 'badge-confirmed'; break;
                                                case 'Completed': $status_class = 'badge-completed'; break;
                                                case 'Cancelled': $status_class = 'badge-cancelled'; break;
                                                default: $status_class = 'badge-pending';
                                            }
                                            
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
                                                            <img src="<?= $site . $appointment['patient_image'] ?>" 
                                                                 class="patient-avatar" 
                                                                 onerror="this.src='<?= $site ?>assets/img/dummy.png'">
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
                                                                <?= $appointment['status'] == 'Completed' || $appointment['status'] == 'Cancelled' ? 'disabled' : '' ?>>
                                                            <option value="Pending" <?= $appointment['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Confirmed" <?= $appointment['status'] == 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                            <option value="Completed" <?= $appointment['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                            <option value="Cancelled" <?= $appointment['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                        </select>
                                                        <input type="hidden" name="update_status" value="1">
                                                    </form>
                                                    <div class="mt-1">
                                                        <span class="badge-status <?= $status_class ?>">
                                                            <?= $appointment['status'] ?>
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
                                                           <?= $appointment['status'] == 'Cancelled' || $appointment['status'] == 'Completed' ? 'disabled' : '' ?>>
                                                            <i class="fa fa-times"></i>
                                                        </a>
                                                        <a href="add-prescription.php?appointment_id=<?= $appointment['appointment_id'] ?>" 
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
                </div>
            </div>
        </div>
    </section>
    
    <?php include("../footer.php") ?>
    
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
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        
        function showDatePicker() {
            document.querySelector('input[name="date"]').showPicker();
        }
        
        // View appointment details
        function viewAppointmentDetails(appointmentId) {
            // Simple AJAX call to get appointment details
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
        
        // Auto-refresh appointments every 60 seconds (optional)
        // setInterval(function() {
        //     if (!document.hidden) {
        //         window.location.reload();
        //     }
        // }, 60000);
    </script>
</body>
</html>