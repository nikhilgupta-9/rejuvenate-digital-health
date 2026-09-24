<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$contact = contact_us();
$user_id = (int)$_SESSION['user_id'];

// ABHA status
$abha_stmt = $conn->prepare("SELECT abha_id, abha_address, abha_linked, abha_verified FROM users WHERE id=?");
$abha_stmt->bind_param("i", $user_id); 
$abha_stmt->execute();
$abha_data = $abha_stmt->get_result()->fetch_assoc();
$abha_stmt->close();

$abha_pending_req = false;
if (empty($abha_data['abha_linked'])) {
    $aq = $conn->prepare("SELECT id FROM user_abha_requests WHERE user_id=? AND status='Pending' LIMIT 1");
    $aq->bind_param("i", $user_id); 
    $aq->execute();
    $abha_pending_req = (bool)$aq->get_result()->fetch_assoc();
    $aq->close();
}

// User statistics from database
$appointment_count = 0;
$pending_appointments = 0;
$reports_count = 0;
$orders_count = 0;
$pharmacy_count = 0;
$lab_count = 0;
$prescriptions_count = 0;

// Total appointments
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointment_count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Pending appointments
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_appointments = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Patient documents & reports count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_documents WHERE patient_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports_count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Supplement orders count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM supplement_orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Pharmacy medicine orders count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pharmacy_orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pharmacy_count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Lab test bookings count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lab_bookings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$lab_count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Finalised prescriptions count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ? AND status = 'final'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$prescriptions_count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

// Recent appointments
$recent_appointments = [];
$stmt = $conn->prepare("
    SELECT a.*, d.name as doctor_name, d.specialization 
    FROM appointments a 
    LEFT JOIN doctors d ON a.doctor_id = d.id 
    WHERE a.user_id = ? 
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
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
    <title>Patient Dashboard | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .abha-banner-card {
            background: linear-gradient(135deg, #0C74C5 0%, #084c82 100%);
            border-radius: 16px;
            padding: 22px 24px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(12,116,197,.18);
            margin-bottom: 24px;
        }
        .abha-banner-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(2,201,184,.15);
            pointer-events: none;
        }
        .abha-num-tag {
            font-family: monospace;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: .08em;
            background: rgba(255,255,255,.14);
            padding: 4px 12px;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid rgba(255,255,255,.2);
        }
    </style>
</head>

<body class="patient-body">
    <?php $sidebar_active = 'dashboard'; include("sidebar.php"); ?>

    <main class="patient-content">

        <!-- Welcome Message -->
        <div class="welcome-message mb-4">
            <h3 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Patient') ?>! 👋</h3>
            <p class="mb-0 text-muted">Ayushman Bharat Digital Mission (ABDM) integrated healthcare dashboard. Manage consultations, e-prescriptions, and health records.</p>
        </div>

        <!-- ABHA Card Banner -->
        <?php if (!empty($abha_data['abha_linked']) && !empty($abha_data['abha_id'])): ?>
            <div class="abha-banner-card">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:54px;height:54px;background:rgba(255,255,255,.18);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;">
                            <i class="fa fa-id-card"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span style="font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;background:rgba(255,255,255,.2);padding:2px 8px;border-radius:4px;font-weight:700;">
                                    Ayushman Bharat Digital Mission
                                </span>
                                <span style="background:#02c9b8;border-radius:10px;padding:2px 8px;font-size:.65rem;font-weight:700;color:#fff;">
                                    <i class="fa fa-shield"></i> ABDM M1 Verified
                                </span>
                            </div>
                            <div class="abha-num-tag"><?= htmlspecialchars($abha_data['abha_id']) ?></div>
                            <?php if (!empty($abha_data['abha_address'])): ?>
                                <div class="mt-1" style="font-size:.82rem;color:rgba(255,255,255,.85);">
                                    <i class="fa fa-at me-1"></i><?= htmlspecialchars($abha_data['abha_address']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= BASE_URL ?>user/my-abha.php" class="btn btn-sm btn-light fw-bold">
                            <i class="fa fa-id-card-o me-1"></i> View Health ID
                        </a>
                        <a href="<?= BASE_URL ?>ajax/abdm-api.php?action=get_card_file" target="_blank" class="btn btn-sm btn-outline-light fw-bold">
                            <i class="fa fa-download me-1"></i> Download ABHA Card
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($abha_pending_req): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-center gap-3 p-3 mb-4">
                <div style="width:42px;height:42px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#d97706;flex-shrink:0;">
                    <i class="fa fa-clock-o"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold text-dark">ABHA Verification Request Pending</div>
                    <div class="small text-muted">Your request to link your Ayushman Bharat Health Account is being verified with the ABDM registry.</div>
                </div>
                <a href="<?= BASE_URL ?>user/my-abha.php" class="btn btn-sm btn-outline-warning">View Status</a>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-4" style="background:linear-gradient(135deg, #eef7ff 0%, #e0f2fe 100%);border-left:4px solid var(--primary) !important;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:48px;height:48px;background:var(--primary);color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                            <i class="fa fa-id-card"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark fs-6">Create or Link Your ABHA Health ID</div>
                            <div class="small text-muted">Join the Ayushman Bharat Digital Mission (ABDM). Access digital prescriptions, diagnostic records, and seamless check-in.</div>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>user/my-abha.php" class="btn btn-primary btn-sm px-3 fw-bold">
                        <i class="fa fa-plus me-1"></i> Link / Create ABHA ID
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <p class="section-title">Health Overview & Care Services</p>
        <div class="row g-3 g-md-3">
            <div class="col-6 col-sm-4 col-md-2">
                <div class="stat-card card-primary h-100 p-3">
                    <i class="fa fa-stethoscope bg-icon"></i>
                    <h3 class="fs-4"><?= $appointment_count ?></h3>
                    <p class="small mb-0">Consultations</p>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <div class="stat-card card-orange h-100 p-3">
                    <i class="fa fa-hourglass-half bg-icon"></i>
                    <h3 class="fs-4"><?= $pending_appointments ?></h3>
                    <p class="small mb-0">Pending Appts</p>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <div class="stat-card card-teal2 h-100 p-3">
                    <i class="fa fa-file-medical bg-icon"></i>
                    <h3 class="fs-4"><?= $prescriptions_count ?></h3>
                    <p class="small mb-0">Prescriptions</p>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <div class="stat-card card-teal h-100 p-3">
                    <i class="fa fa-flask bg-icon"></i>
                    <h3 class="fs-4"><?= $lab_count ?></h3>
                    <p class="small mb-0">Lab Bookings</p>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <div class="stat-card card-blue h-100 p-3">
                    <i class="fa fa-pills bg-icon"></i>
                    <h3 class="fs-4"><?= $pharmacy_count ?></h3>
                    <p class="small mb-0">Medicine Orders</p>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <div class="stat-card card-purple h-100 p-3">
                    <i class="fa fa-shopping-bag bg-icon"></i>
                    <h3 class="fs-4"><?= $orders_count ?></h3>
                    <p class="small mb-0">Supplements</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <p class="section-title mt-4">Quick Actions</p>
        <div class="row g-3">
            <?php
            $actions = [
                [BASE_URL . 'user/my-bookings.php',             'bg-primary-theme text-white', 'fa fa-calendar-plus',  'Book Consultation'],
                [BASE_URL . 'user/my-abha.php',                 'bg-accent-theme text-white',  'fa fa-id-card',        'ABHA Health ID'],
                [BASE_URL . 'user/my-doctor-appointments.php',   'bg-green text-white',         'fa fa-stethoscope',    'My Consultations'],
                [BASE_URL . 'user/my-lab-bookings.php',         'bg-teal text-white',          'fa fa-flask',          'Lab Test Bookings'],
                [BASE_URL . 'user/my-medicine-orders.php',      'bg-blue text-white',          'fa fa-pills',          'Medicine Orders'],
                [BASE_URL . 'user/medical-history.php',         'bg-primary-theme text-white', 'fa fa-notes-medical',  'Medical History'],
                [BASE_URL . 'user/health-profile.php',          'bg-accent-theme text-white',  'fa fa-heartbeat',      'Health Profile (PHR)'],
                [BASE_URL . 'user/my-reports.php',              'bg-orange text-white',        'fa fa-chart-area',     'Diagnostic Reports'],
                [BASE_URL . 'user/my-supplement-order.php',     'bg-purple text-white',        'fa fa-shopping-bag',   'Supplement Orders'],
                [BASE_URL . 'user/manage-address.php',          'bg-secondary text-white',     'fa fa-map-marker',     'Delivery Addresses'],
                [BASE_URL . 'user/my-profile.php',              'bg-primary-theme text-white', 'fa fa-user',           'Account Profile'],
                [BASE_URL . 'user/help-and-contact.php',        'bg-secondary text-white',     'fa fa-life-ring',      'Help & Support'],
            ];
            foreach ($actions as [$href, $cls, $icon, $title]):
            ?>
                <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                    <a href="<?= $href ?>" class="quick-action">
                        <div style="width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;"
                            class="<?= $cls ?>">
                            <i class="<?= $icon ?>" style="font-size:1.1rem;"></i>
                        </div>
                        <span class="small font-weight-bold"><?= $title ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Appointments -->
        <?php if (!empty($recent_appointments)): ?>
            <p class="section-title mt-4">Recent Consultations</p>
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
                    <h6 class="fw-bold mb-0">
                        <i class="fa fa-calendar-check-o me-2 text-primary-theme"></i>
                        Recent Consultations
                    </h6>
                    <a href="<?= BASE_URL ?>user/my-doctor-appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                    <th style="font-size:.73rem;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td style="font-size:.83rem;" class="fw-bold">
                                            Dr. <?= htmlspecialchars($appointment['doctor_name'] ?? 'Specialist') ?>
                                        </td>
                                        <td style="font-size:.83rem;" class="text-muted"><?= htmlspecialchars($appointment['specialization'] ?? 'General') ?></td>
                                        <td style="font-size:.83rem;"><?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></td>
                                        <td style="font-size:.83rem;"><?= date('h:i A', strtotime($appointment['appointment_time'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $appointment['status'] ?>">
                                                <?= ucfirst($appointment['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>user/appointment-details.php?id=<?= $appointment['id'] ?>" class="btn btn-sm btn-outline-primary" style="padding:2px 8px;font-size:.75rem;">
                                                Details
                                            </a>
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

    <?php include("inc/scripts.php"); ?>
</body>
</html>