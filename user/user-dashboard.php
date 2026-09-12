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

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
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
  <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
</head>

<body>
  <?php $sidebar_active = 'dashboard'; include("sidebar.php"); ?>
  <main class="patient-content">

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
          <p class="section-title">Overview</p>
          <div class="row g-3 g-md-4">
              <div class="col-12 col-sm-6 col-md-3">
                  <div class="stat-card card-primary h-100">
                      <i class="fa fa-calendar bg-icon"></i>
                      <h3><?= $appointment_count ?></h3>
                      <p>Total Appointments</p>
                  </div>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                  <div class="stat-card card-orange h-100">
                      <i class="fa fa-hourglass-half bg-icon"></i>
                      <h3><?= $pending_appointments ?></h3>
                      <p>Pending Appointments</p>
                  </div>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                  <div class="stat-card card-teal2 h-100">
                      <i class="fa fa-file-text-o bg-icon"></i>
                      <h3><?= $reports_count ?></h3>
                      <p>Medical Reports</p>
                  </div>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                  <div class="stat-card card-purple h-100">
                      <i class="fa fa-shopping-bag bg-icon"></i>
                      <h3><?= $orders_count ?></h3>
                      <p>Supplement Orders</p>
                  </div>
              </div>
          </div>

          <!-- Quick Actions -->
          <p class="section-title mt-4">Quick Actions</p>
          <div class="row g-3">
            <?php
            $actions = [
                [BASE_URL . 'user/my-bookings.php',             'bg-primary-theme text-white', 'fa fa-calendar',       'My Bookings'],
                [BASE_URL . 'user/my-reports.php',               'bg-accent-theme text-white',  'fa fa-file-text-o',    'My Reports'],
                [BASE_URL . 'user/my-supplement-order.php',      'bg-orange text-white',        'fa fa-shopping-bag',   'Supplement Order'],
                [BASE_URL . 'user/my-doctor-appointments.php',   'bg-green text-white',         'fa fa-stethoscope',    'Doctor Appointments'],
                [BASE_URL . 'user/manage-address.php',           'bg-purple text-white',        'fa fa-map-marker',     'Manage Addresses'],
                [BASE_URL . 'user/help-and-contact.php',         'bg-secondary text-white',     'fa fa-life-ring',      'Help & Contact'],
            ];
            foreach ($actions as [$href, $cls, $icon, $title]):
            ?>
              <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                <a href="<?= $href ?>" class="quick-action">
                  <div style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;"
                    class="<?= $cls ?>">
                    <i class="<?= $icon ?>" style="font-size:1.1rem;"></i>
                  </div>
                  <span><?= $title ?></span>
                </a>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Recent Appointments -->
          <?php if (!empty($recent_appointments)): ?>
          <p class="section-title mt-4">Recent Appointments</p>
          <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
              <h6 class="fw-bold mb-0">
                <i class="fa fa-calendar-check-o me-2" style="color:var(--primary)"></i>
                Recent Appointments
              </h6>
              <a href="my-doctor-appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="font-size:.73rem;">Doctor</th>
                      <th style="font-size:.73rem;">Specialization</th>
                      <th style="font-size:.73rem;">Date</th>
                      <th style="font-size:.73rem;">Time</th>
                      <th style="font-size:.73rem;">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recent_appointments as $appointment): ?>
                    <tr>
                      <td style="font-size:.83rem;"><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                      <td style="font-size:.83rem;"><?= htmlspecialchars($appointment['specialization'] ?? 'N/A') ?></td>
                      <td style="font-size:.83rem;"><?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></td>
                      <td style="font-size:.83rem;"><?= date('h:i A', strtotime($appointment['appointment_time'])) ?></td>
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
            </div>
          </div>
          <?php endif; ?>
  </main>
  <?php include("inc/scripts.php") ?>
</body>
</html>