<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ".BASE_URL."login.php");
    exit();
}

$contact = contact_us();
$user_id = $_SESSION['user_id'];

// ABHA status
$abha_stmt = $conn->prepare("SELECT abha_id, abha_address, abha_linked, abha_verified FROM users WHERE id=?");
$abha_stmt->bind_param("i", $user_id); $abha_stmt->execute();
$abha_data = $abha_stmt->get_result()->fetch_assoc();
$abha_stmt->close();
$abha_pending_req = false;
if (empty($abha_data['abha_linked'])) {
    $aq = $conn->prepare("SELECT id FROM user_abha_requests WHERE user_id=? AND status='Pending' LIMIT 1");
    $aq->bind_param("i", $user_id); $aq->execute();
    $abha_pending_req = (bool)$aq->get_result()->fetch_assoc();
    $aq->close();
}

// Get user statistics from database
$appointment_count = 0;
$reports_count = 0;
$orders_count = 0;
$pending_appointments = 0;

// Get appointment count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $appointment_count = $row['count'];
}
$stmt->close();

// Get pending appointments count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $pending_appointments = $row['count'];
}
$stmt->close();

// Get reports count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM medical_reports WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $reports_count = $row['count'];
}
$stmt->close();

// Get orders count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM supplement_orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $orders_count = $row['count'];
}
$stmt->close();

// Get recent appointments
$recent_appointments = [];
$stmt = $conn->prepare("SELECT a.*, d.name as doctor_name, d.specialization 
                       FROM appointments a 
                       LEFT JOIN doctors d ON a.doctor_id = d.id 
                       WHERE a.user_id = ? 
                       ORDER BY a.appointment_date DESC 
                       LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_appointments[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>User Dashboard | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    .dashboard-stats {
        margin-bottom: 2rem;
    }
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        border-left: 4px solid #2c5aa0;
    }
    .stat-card h3 {
        color: #2c5aa0;
        margin-bottom: 0.5rem;
        font-size: 2rem;
    }
    .stat-card p {
        color: #666;
        margin-bottom: 0;
        font-weight: 500;
    }
    .user_dash_box {
        background: white;
        border-radius: 10px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #e9ecef;
    }
    .user_dash_box:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 25px rgba(0,0,0,0.15);
    }
    .user_dash_box img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 50%;
        margin-bottom: 1rem;
    }
    .user_dash_box h5 {
        color: #2c5aa0;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .user_dash_box a {
        text-decoration: none;
        color: inherit;
    }
    .recent-appointments {
        background: white;
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        margin-top: 2rem;
    }
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-confirmed { background: #d1ecf1; color: #0c5460; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .welcome-message {
        background: linear-gradient(135deg, #0C74C5, #0C74C5);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
  </style>
</head>

<body>
  <?php include("../header.php") ?>
  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="row mb-5">
        <div class="col-md-3">
          <?php include("sidebar.php") ?>
        </div>
        <!-- Main Content -->
        <div class="col-lg-9">
          <!-- Mobile Toggle Button -->
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
          
          <!-- Welcome Message -->
          <div class="welcome-message">
            <h3>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h3>
            <p class="mb-0">Here's your health dashboard. Manage your appointments, reports, and more.</p>
          </div>

          <!-- ABHA Card -->
          <?php if (!empty($abha_data['abha_linked']) && !empty($abha_data['abha_id'])): ?>
          <a href="my-abha.php" class="d-block text-decoration-none mb-3">
            <div style="background:#00875a;border-radius:14px;padding:16px 22px;color:#fff;display:flex;align-items:center;gap:16px;">
              <div style="width:48px;height:48px;background:rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;"><i class="fas fa-id-card"></i></div>
              <div style="flex:1;">
                <div style="font-size:.6rem;opacity:.7;text-transform:uppercase;letter-spacing:.1em;">Ayushman Bharat Health Account</div>
                <div style="font-family:monospace;font-size:1.05rem;font-weight:700;letter-spacing:.06em;"><?= htmlspecialchars($abha_data['abha_id']) ?></div>
                <?php if ($abha_data['abha_address']): ?><div style="font-size:.75rem;opacity:.8;"><?= htmlspecialchars($abha_data['abha_address']) ?></div><?php endif; ?>
              </div>
              <?php if ($abha_data['abha_verified']): ?>
              <span style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700;white-space:nowrap;"><i class="fas fa-shield-alt me-1"></i>Verified</span>
              <?php else: ?>
              <i class="fas fa-chevron-right" style="opacity:.6;"></i>
              <?php endif; ?>
            </div>
          </a>
          <?php elseif ($abha_pending_req): ?>
          <a href="my-abha.php" class="d-block text-decoration-none mb-3">
            <div style="background:#fff;border:2px dashed #fde68a;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;">
              <div style="width:40px;height:40px;background:#fffbeb;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#d97706;flex-shrink:0;"><i class="fas fa-hourglass-half"></i></div>
              <div><div style="font-size:.85rem;font-weight:700;color:#374151;">ABHA Request Pending</div><div style="font-size:.72rem;color:#6b7280;">Awaiting admin verification</div></div>
              <i class="fas fa-chevron-right ms-auto" style="color:#9ca3af;"></i>
            </div>
          </a>
          <?php else: ?>
          <a href="my-abha.php" class="d-block text-decoration-none mb-3">
            <div style="background:#fff;border:2px dashed #86efac;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;">
              <div style="width:40px;height:40px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#00875a;flex-shrink:0;"><i class="fas fa-id-card"></i></div>
              <div><div style="font-size:.85rem;font-weight:700;color:#374151;">Link Your ABHA Health ID</div><div style="font-size:.72rem;color:#6b7280;">Connect your Ayushman Bharat digital health ID</div></div>
              <span style="background:#00875a;color:#fff;border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:700;white-space:nowrap;margin-left:auto;">Link Now →</span>
            </div>
          </a>
          <?php endif; ?>

          <!-- Statistics Cards -->
          <div class="dashboard-stats">
            <div class="row">
              <div class="col-md-3">
                <div class="stat-card">
                  <h3><?= $appointment_count ?></h3>
                  <p>Total Appointments</p>
                </div>
              </div>
              <div class="col-md-3">
                <div class="stat-card">
                  <h3><?= $pending_appointments ?></h3>
                  <p>Pending Appointments</p>
                </div>
              </div>
              <div class="col-md-3">
                <div class="stat-card">
                  <h3><?= $reports_count ?></h3>
                  <p>Medical Reports</p>
                </div>
              </div>
              <div class="col-md-3">
                <div class="stat-card">
                  <h3><?= $orders_count ?></h3>
                  <p>Supplement Orders</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="profile-card shadow">
            <h4 class="mb-4">Quick Actions</h4>
            <div class="row mt-4">
              <div class="col-md-4">
                <div class="user_dash_box">
                  <a href="my-bookings.php">
                    <img src="<?= BASE_URL ?>assets/img/d1.jpeg" alt="My Bookings">
                    <h5>My Bookings</h5>
                    <small><?= $appointment_count ?> bookings</small>
                  </a>
                </div>
              </div>
              <div class="col-md-4">
                <div class="user_dash_box">
                  <a href="my-reports.php">
                    <img src="<?= BASE_URL ?>assets/img/d2.jpeg" alt="My Reports">
                    <h5>My Reports</h5>
                    <small><?= $reports_count ?> reports</small>
                  </a>
                </div>
              </div>
              <div class="col-md-4">
                <div class="user_dash_box">
                  <a href="my-supplement-order.php">
                    <img src="<?= BASE_URL ?>assets/img/d3.jpeg" alt="My Supplement Order">
                    <h5>My Supplement Order</h5>
                    <small><?= $orders_count ?> orders</small>
                  </a>
                </div>
              </div>
              <div class="col-md-4">
                <div class="user_dash_box">
                  <a href="my-doctor-appointments.php">
                    <img src="<?= BASE_URL ?>assets/img/d4.jpeg" alt="My Doctor Appointments">
                    <h5>My Doctor Appointments</h5>
                    <small><?= $pending_appointments ?> pending</small>
                  </a>
                </div>
              </div>
              <div class="col-md-4">
                <div class="user_dash_box">
                  <a href="manage-address.php">
                    <img src="<?= BASE_URL ?>assets/img/d5.jpeg" alt="Manage Addresses">
                    <h5>Manage Addresses</h5>
                    <small>Update delivery addresses</small>
                  </a>
                </div>
              </div>
              <div class="col-md-4">
                <div class="user_dash_box">
                  <a href="help-and-contact.php">
                    <img src="<?= BASE_URL ?>assets/img/d6.jpeg" alt="Help & Contact Us">
                    <h5>Help & Contact Us</h5>
                    <small>24/7 Support</small>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Appointments -->
          <?php if (!empty($recent_appointments)): ?>
          <div class="recent-appointments">
            <h4 class="mb-4">Recent Appointments</h4>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recent_appointments as $appointment): ?>
                  <tr>
                    <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($appointment['specialization'] ?? 'N/A') ?></td>
                    <td><?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></td>
                    <td><?= date('h:i A', strtotime($appointment['appointment_time'])) ?></td>
                    <td>
                      <span class="status-badge status-<?= $appointment['status'] ?>">
                        <?= ucfirst($appointment['status']) ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="text-center mt-3">
              <a href="my-doctor-appointments.php" class="btn btn-outline-primary">View All Appointments</a>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php include("../footer.php") ?>
  <script>
    function toggleMenu() {
      document.getElementById("sidebarMenu").classList.toggle("show");
    }
  </script>
</body>
</html>