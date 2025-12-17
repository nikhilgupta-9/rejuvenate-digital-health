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
$doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';

// Get doctor's profile details
$doctor_sql = "SELECT name, email, profile_image, phone, specialization, experience_years, rating FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor_data = $doctor_result->fetch_assoc();

$doctor_name = $doctor_data['name'] ?? 'Doctor';
$doctor_email = $doctor_data['email'] ?? '';
$doctor_profile_image = !empty($doctor_data['profile_image']) ? $doctor_data['profile_image'] : 'assets/img/dummy.png';
$doctor_phone = $doctor_data['phone'] ?? '';
$doctor_specialization = $doctor_data['specialization'] ?? '';
$doctor_experience = $doctor_data['experience_years'] ?? 0;
$doctor_rating = $doctor_data['rating'] ?? 0;

// Get dashboard statistics
$stats_sql = "
    SELECT 
        COUNT(DISTINCT u.id) as total_patients,
        COUNT(DISTINCT a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'Pending' THEN 1 ELSE 0 END) as pending_appointments,
        SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN DATE(a.appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today_appointments,
        SUM(CASE WHEN a.status = 'Confirmed' AND DATE(a.appointment_date) = CURDATE() THEN 1 ELSE 0 END) as confirmed_today,
        (SELECT COUNT(*) FROM patient_documents WHERE doctor_id = ?) as total_documents
    FROM users u
    INNER JOIN appointments a ON u.id = a.user_id
    WHERE a.doctor_id = ?
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('ii', $doctor_id, $doctor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get today's appointments
$today_sql = "
    SELECT 
        a.*,
        u.name as patient_name,
        u.mobile as patient_phone,
        u.profile_pic as patient_image
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.doctor_id = ? 
    AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_time ASC
    LIMIT 5
";

$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param('i', $doctor_id);
$today_stmt->execute();
$today_result = $today_stmt->get_result();

// Get recent patients
$recent_patients_sql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.mobile,
        u.profile_pic,
        u.gender,
        u.dob,
        MAX(a.appointment_date) as last_visit,
        COUNT(a.id) as total_visits
    FROM users u
    INNER JOIN appointments a ON u.id = a.user_id
    WHERE a.doctor_id = ?
    GROUP BY u.id
    ORDER BY last_visit DESC
    LIMIT 5
";

$recent_stmt = $conn->prepare($recent_patients_sql);
$recent_stmt->bind_param('i', $doctor_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();

// Get upcoming appointments
$upcoming_sql = "
    SELECT 
        a.*,
        u.name as patient_name,
        u.mobile as patient_phone
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.doctor_id = ? 
    AND a.appointment_date > CURDATE()
    AND a.status IN ('Pending', 'Confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";

$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param('i', $doctor_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();

// Get monthly appointment stats for chart
$monthly_stats_sql = "
    SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
    FROM appointments
    WHERE doctor_id = ?
    AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month ASC
";

$monthly_stmt = $conn->prepare($monthly_stats_sql);
$monthly_stmt->bind_param('i', $doctor_id);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();

$monthly_data = [];
$monthly_labels = [];
$monthly_totals = [];
$monthly_completed = [];

while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
    $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_totals[] = $row['total'];
    $monthly_completed[] = $row['completed'];
}

// Get earnings if you have payment system
$earnings_sql = "
    SELECT 
        COALESCE(SUM(consultation_fee), 0) as total_earnings,
        COALESCE(SUM(CASE WHEN MONTH(appointment_date) = MONTH(CURDATE()) THEN consultation_fee ELSE 0 END), 0) as monthly_earnings
    FROM appointments a
    INNER JOIN doctors d ON a.doctor_id = d.id
    WHERE a.doctor_id = ? 
    AND a.status = 'Completed'
";

$earnings_stmt = $conn->prepare($earnings_sql);
$earnings_stmt->bind_param('i', $doctor_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();
$earnings = $earnings_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - Doctor Dashboard</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            height: 100%;
        }
        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #333535;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
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
        .welcome-card {
            background: linear-gradient(135deg, #02c9b8 0%, #0c74c5 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .appointment-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .sidebar.show { display: block; }
            .menu-btn { display: block; }
        }
    </style>
</head>
<body>
    <?php include("../header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
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

                        <a href="<?= $site ?>doctor/doctor-dashboard.php" class="active">Dashboard</a>
                        <a href="<?= $site ?>doctor/my-patients.php">My Patients</a>
                        <a href="<?= $site ?>doctor/appointments.php">Appointments</a>
                        <a href="<?= $site ?>doctor/patient-form.php">Patient Form</a>
                        <a href="<?= $site ?>doctor/my-contact.php">Contact Us</a>
                        <a href="<?= $site ?>doctor/doctor-about.php">About Us</a>
                        <a href="<?= $site ?>doctor/change-password.php">Change Password</a>
                        <a href="<?= $site ?>doctor-logout.php">Logout</a>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
                    <!-- Welcome Card -->
                    <div class="welcome-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3>Welcome back, Dr. <?= htmlspecialchars($doctor_name) ?>! 👋</h3>
                                <p class="mb-0">
                                    <?= htmlspecialchars($doctor_specialization) ?> | 
                                    <?= $doctor_experience ?>+ Years Experience | 
                                    Rating: <?= number_format($doctor_rating, 1) ?>/5
                                </p>
                                <small>Last login: <?= date('d F Y, h:i A') ?></small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="stat-card" style="background: rgba(255,255,255,0.2);">
                                    <div class="stat-number"><?= $stats['today_appointments'] ?? 0 ?></div>
                                    <div class="stat-label text-light">Today's Appointments</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-primary">
                                    <i class="fa fa-users"></i>
                                </div>
                                <div class="stat-number"><?= $stats['total_patients'] ?? 0 ?></div>
                                <div class="stat-label">Total Patients</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-success">
                                    <i class="fa fa-calendar-check"></i>
                                </div>
                                <div class="stat-number"><?= $stats['total_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Total Appointments</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-warning">
                                    <i class="fa fa-clock"></i>
                                </div>
                                <div class="stat-number"><?= $stats['pending_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-info">
                                    <i class="fa fa-file-medical"></i>
                                </div>
                                <div class="stat-number"><?= $stats['total_documents'] ?? 0 ?></div>
                                <div class="stat-label">Documents</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Appointments Chart -->
                            <div class="profile-card">
                                <h5 class="mb-3">Appointments Overview (Last 6 Months)</h5>
                                <div class="chart-container">
                                    <canvas id="appointmentsChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Today's Appointments -->
                            <div class="profile-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Today's Appointments</h5>
                                    <a href="appointments.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">
                                        View All
                                    </a>
                                </div>
                                
                                <?php if ($today_result->num_rows == 0): ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted">No appointments scheduled for today.</p>
                                        <!-- <a href="add-appointment.php" class="btn btn-sm btn-primary">
                                            <i class="fa fa-plus"></i> Add Appointment
                                        </a> -->
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Patient</th>
                                                    <th>Contact</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($appointment = $today_result->fetch_assoc()): ?>
                                                <?php
                                                $status_class = '';
                                                switch ($appointment['status']) {
                                                    case 'Pending': $status_class = 'badge-pending'; break;
                                                    case 'Confirmed': $status_class = 'badge-confirmed'; break;
                                                    case 'Completed': $status_class = 'badge-completed'; break;
                                                    case 'Cancelled': $status_class = 'badge-cancelled'; break;
                                                    default: $status_class = 'badge-pending';
                                                }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= date('h:i A', strtotime($appointment['appointment_time'])) ?></strong>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($appointment['patient_image'])): ?>
                                                                <img src="<?= $site . $appointment['patient_image'] ?>" 
                                                                     class="patient-avatar me-2"
                                                                     onerror="this.src='<?= $site ?>assets/img/dummy.png'">
                                                            <?php endif; ?>
                                                            <span><?= htmlspecialchars($appointment['patient_name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($appointment['patient_phone']): ?>
                                                            <a href="tel:<?= $appointment['patient_phone'] ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fa fa-phone"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="appointment-badge <?= $status_class ?>">
                                                            <?= $appointment['status'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="appointment-details.php?id=<?= $appointment['id'] ?>" 
                                                           class="btn btn-sm btn-info" title="View">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Quick Stats -->
                            <div class="profile-card mb-4">
                                <h5 class="mb-3">Quick Stats</h5>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="stat-number"><?= $stats['completed_appointments'] ?? 0 ?></div>
                                            <div class="stat-label">Completed</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="stat-number"><?= $stats['confirmed_today'] ?? 0 ?></div>
                                            <div class="stat-label">Confirmed Today</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <div class="stat-number">₹<?= number_format($earnings['total_earnings'] ?? 0, 0) ?></div>
                                            <div class="stat-label">Total Earnings</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <div class="stat-number">₹<?= number_format($earnings['monthly_earnings'] ?? 0, 0) ?></div>
                                            <div class="stat-label">This Month</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Patients -->
                            <div class="profile-card mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Recent Patients</h5>
                                    <a href="my-patients.php" class="btn btn-sm btn-outline-primary">
                                        View All
                                    </a>
                                </div>
                                
                                <?php if ($recent_result->num_rows == 0): ?>
                                    <p class="text-muted text-center">No patients yet.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php while ($patient = $recent_result->fetch_assoc()): 
                                            $age = $patient['dob'] ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'N/A';
                                        ?>
                                        <a href="patient-details.php?id=<?= $patient['id'] ?>" 
                                           class="list-group-item list-group-item-action d-flex align-items-center">
                                            <?php if (!empty($patient['profile_pic'])): ?>
                                                <img src="<?= $site . $patient['profile_pic'] ?>" 
                                                     class="patient-avatar me-3"
                                                     onerror="this.src='<?= $site ?>assets/img/dummy.png'">
                                            <?php else: ?>
                                                <div class="patient-avatar bg-light d-flex align-items-center justify-content-center me-3">
                                                    <i class="fa fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <strong><?= htmlspecialchars($patient['name']) ?></strong>
                                                <div class="small text-muted">
                                                    <?= $patient['gender'] ?> | <?= $age ?> yrs
                                                </div>
                                                <div class="small text-muted">
                                                    Visits: <?= $patient['total_visits'] ?>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Upcoming Appointments -->
                            <div class="profile-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Upcoming Appointments</h5>
                                    <a href="appointments.php" class="btn btn-sm btn-outline-primary">
                                        View All
                                    </a>
                                </div>
                                
                                <?php if ($upcoming_result->num_rows == 0): ?>
                                    <p class="text-muted text-center">No upcoming appointments.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php while ($appointment = $upcoming_result->fetch_assoc()): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?= htmlspecialchars($appointment['patient_name']) ?></strong>
                                                    <div class="small text-muted">
                                                        <?= date('d M', strtotime($appointment['appointment_date'])) ?> 
                                                        at <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                                                    </div>
                                                </div>
                                                <span class="appointment-badge <?= 
                                                    $appointment['status'] == 'Pending' ? 'badge-pending' : 'badge-confirmed'
                                                ?>">
                                                    <?= $appointment['status'] ?>
                                                </span>
                                            </div>
                                            <?php if ($appointment['patient_phone']): ?>
                                            <div class="mt-2">
                                                <a href="tel:<?= $appointment['patient_phone'] ?>" 
                                                   class="btn btn-sm btn-outline-primary btn-sm">
                                                    <i class="fa fa-phone"></i> Call
                                                </a>
                                                <a href="appointment-details.php?id=<?= $appointment['id'] ?>" 
                                                   class="btn btn-sm btn-outline-info btn-sm">
                                                    <i class="fa fa-eye"></i> View
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                   
                </div>
            </div>
        </div>
    </section>
    
    <?php include("../footer.php") ?>
    
    <script>
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        
        // Initialize appointments chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('appointmentsChart').getContext('2d');
            const appointmentsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($monthly_labels) ?>,
                    datasets: [{
                        label: 'Total Appointments',
                        data: <?= json_encode($monthly_totals) ?>,
                        borderColor: '#02c9b8',
                        backgroundColor: 'rgba(2, 201, 184, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }, {
                        label: 'Completed',
                        data: <?= json_encode($monthly_completed) ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
        });
        
        // Auto-refresh dashboard every 60 seconds
        setInterval(function() {
            if (!document.hidden) {
                // You can implement partial refresh here if needed
                // For now, just reload the page
                // window.location.reload();
            }
        }, 60000);
    </script>
</body>
</html>