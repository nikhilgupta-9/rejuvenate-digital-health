<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/auth/guard.php");

// JWT guard — verifies token, auto-refreshes, redirects on failure
$jwt_doctor  = doctor_jwt_guard();
$doctor_id   = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

// Get doctor's profile details
$doctor_sql = "SELECT doctor_uid, name, email, profile_image, phone, specialization, experience_years, rating, hpr_id, hpr_verified FROM doctors WHERE id = ?";
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
$doctor_hpr_id = $doctor_data['hpr_id'] ?? '';
$doctor_hpr_verified = (bool)($doctor_data['hpr_verified'] ?? false);
$doctor_uid = $doctor_data['doctor_uid'] ?? '';

// Membership (subscription) status — latest paid row's expiry, if any
$sub_stmt = $conn->prepare("SELECT expires_at FROM doctor_subscriptions WHERE doctor_id = ? AND status = 'paid' ORDER BY expires_at DESC LIMIT 1");
$sub_stmt->bind_param('i', $doctor_id);
$sub_stmt->execute();
$sub_row = $sub_stmt->get_result()->fetch_assoc();
$membership_expires_at = $sub_row['expires_at'] ?? null;
$membership_active = $membership_expires_at && strtotime($membership_expires_at) > time();

$plan_row = $conn->query("SELECT id, name, price FROM doctor_plans WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch_assoc();

// Referral stats — doctors this doctor referred, and running commission balance
$referral_stats_stmt = $conn->prepare("
    SELECT
        (SELECT COUNT(*) FROM doctors WHERE referred_by = ?) AS referred_count,
        (SELECT COALESCE(SUM(commission_amount), 0) FROM doctor_referral_earnings WHERE referring_doctor_id = ?) AS total_earned
");
$referral_stats_stmt->bind_param('ii', $doctor_id, $doctor_id);
$referral_stats_stmt->execute();
$referral_stats = $referral_stats_stmt->get_result()->fetch_assoc();
$referral_link = BASE_URL . 'doctor-signup.php?ref=' . urlencode($doctor_uid);

/*
 * appointments.status is an ENUM('pending','approved','rejected','completed','no_show').
 * 'Confirmed'/'Cancelled' below are never-matching leftovers from before that enum existed —
 * fixed to the real values so these stats/queries aren't silently dead.
 */
// Get dashboard statistics
$stats_sql = "
    SELECT
        COUNT(DISTINCT u.id) as total_patients,
        COUNT(DISTINCT a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN DATE(a.appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today_appointments,
        SUM(CASE WHEN a.status = 'approved' AND DATE(a.appointment_date) = CURDATE() THEN 1 ELSE 0 END) as confirmed_today,
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
        u.id as patient_id,
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
    AND a.status IN ('pending', 'approved')
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
    AND a.status = 'completed'
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | REJUVENATE Doctor Portal</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* ── Page-level overrides ── */
    .appt-badge {
      padding: 3px 9px;
      border-radius: 10px;
      font-size: .72rem;
      font-weight: 600;
    }

    .appt-pending {
      background: #fff3cd;
      color: #856404;
    }

    .appt-approved {
      background: #d1ecf1;
      color: #0c5460;
    }

    .appt-completed {
      background: #d4edda;
      color: #155724;
    }

    .appt-rejected {
      background: #f8d7da;
      color: #721c24;
    }

    .appt-no_show {
      background: #e2e3e5;
      color: #383d41;
    }

    .pat-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      object-fit: cover;
      background: #e5e7eb;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      color: #6b7280;
      flex-shrink: 0;
    }

    .chart-wrap {
      height: 280px;
      position: relative;
    }

    /* ── Dashboard greeting header ── */
    .dash-header {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
      padding: 22px 26px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
    }

    .dash-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--accent);
      flex-shrink: 0;
    }

    .dash-avatar-fallback {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .dash-greeting {
      font-size: 1.15rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 2px;
    }

    .dash-sub {
      font-size: .84rem;
      color: #6b7280;
    }

    .badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
    }

    .badge-ok {
      background: #dcfce7;
      color: #166534;
    }

    .badge-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .badge-star {
      background: #fef9c3;
      color: #854d0e;
    }
  </style>
</head>

<body>
  <?php $sidebar_active = 'dashboard';
  include(__DIR__ . "/inc/sidebar.php"); ?>

  <main class="doctor-content">

    <!-- ── Greeting Header ── -->
    <div class="dash-header">
      <div class="d-flex align-items-center" style="gap:14px;">
        <?php if (!empty($doctor_data['profile_image'])): ?>
          <img src="<?= BASE_URL . htmlspecialchars($doctor_data['profile_image']) ?>" class="dash-avatar"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="dash-avatar-fallback" style="display:none;"><i class="fa fa-user"></i></div>
        <?php else: ?>
          <div class="dash-avatar-fallback"><i class="fa fa-user"></i></div>
        <?php endif; ?>
        <div>
          <div class="dash-greeting">Welcome back, Dr. <?= htmlspecialchars($doctor_name) ?></div>
          <div class="dash-sub">
            <?= htmlspecialchars($doctor_specialization ?: 'General Practice') ?>
            <?php if ($doctor_experience): ?> &nbsp;·&nbsp; <?= (int)$doctor_experience ?>+ yrs experience<?php endif; ?>
              &nbsp;·&nbsp; <?= date('l, d M Y') ?>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap" style="gap:8px;">
        <?php if ($doctor_hpr_verified): ?>
          <span class="badge-pill badge-ok"><i class="fa fa-check-circle"></i> HPR Verified</span>
        <?php else: ?>
          <span class="badge-pill badge-pending"><i class="fa fa-clock-o"></i> HPR Pending</span>
        <?php endif; ?>
        <?php if ($doctor_rating > 0): ?>
          <span class="badge-pill badge-star"><i class="fa fa-star"></i> <?= number_format($doctor_rating, 1) ?>/5.0</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Stats Row ── -->
    <p class="section-title">Overview</p>
    <div class="row g-3">
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-primary">
          <i class="fa fa-users bg-icon"></i>
          <div class="num"><?= $stats['total_patients'] ?? 0 ?></div>
          <div class="lbl">Patients</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-green">
          <i class="fa fa-calendar bg-icon"></i>
          <div class="num"><?= $stats['total_appointments'] ?? 0 ?></div>
          <div class="lbl">Appointments</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-orange">
          <i class="fa fa-clock-o bg-icon"></i>
          <div class="num"><?= $stats['today_appointments'] ?? 0 ?></div>
          <div class="lbl">Today</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-accent">
          <i class="fa fa-check-circle bg-icon"></i>
          <div class="num"><?= $stats['completed_appointments'] ?? 0 ?></div>
          <div class="lbl">Completed</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-purple">
          <i class="fa fa-hourglass-half bg-icon"></i>
          <div class="num"><?= $stats['pending_appointments'] ?? 0 ?></div>
          <div class="lbl">Pending</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-teal2">
          <i class="fa fa-file-text-o bg-icon"></i>
          <div class="num"><?= $stats['total_documents'] ?? 0 ?></div>
          <div class="lbl">Documents</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card card-blue2">
          <i class="fa fa-inr bg-icon"></i>
          <div class="num">₹<?= number_format($earnings['monthly_earnings'] ?? 0) ?></div>
          <div class="lbl">Earned This Month</div>
        </div>
      </div>
    </div>

    <!-- ── Quick Actions ── -->
    <p class="section-title">Quick Actions</p>
    <div class="row g-3">
      <?php
      $actions = [
        [BASE_URL . 'doctor/my-patients.php',    'bg-primary-theme text-white',  'fa fa-heartbeat',   'My Patients'],
        [BASE_URL . 'doctor/appointments.php',   'bg-accent-theme text-white',   'fa fa-calendar',    'Appointments'],
        [BASE_URL . 'doctor/appointments.php?date=' . date('Y-m-d'), 'bg-orange text-white', 'fa fa-clock', "Today's"],
        [BASE_URL . 'doctor/patient-form.php',   'bg-green text-white',          'fa fa-file', 'Patient Form'],
        [BASE_URL . 'doctor/appointments-calendar.php', 'bg-purple text-white',  'fa fa-chart-area',   'Reports'],
        [BASE_URL . 'doctor/change-password.php', 'bg-secondary text-white',      'fa fa-cog',         'Settings'],
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

    <!-- ── Membership & Referrals ── -->
    <p class="section-title mt-4">Membership &amp; Referrals</p>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="fa fa-id-card me-2" style="color:var(--primary)"></i>Membership</h6>
            <?php if ($membership_active): ?>
              <span class="badge-pill badge-ok mb-2"><i class="fa fa-check-circle"></i> Active</span>
              <p class="mb-2" style="font-size:.85rem;color:#6b7280;">Valid until <strong><?= date('d M Y', strtotime($membership_expires_at)) ?></strong></p>
            <?php else: ?>
              <span class="badge-pill badge-pending mb-2"><i class="fa fa-exclamation-circle"></i> <?= $membership_expires_at ? 'Expired' : 'Not subscribed' ?></span>
              <p class="mb-2" style="font-size:.85rem;color:#6b7280;">
                <?= $membership_expires_at ? 'Expired on ' . date('d M Y', strtotime($membership_expires_at)) . '.' : '' ?>
                <?php if ($plan_row): ?>Subscribe to <strong><?= htmlspecialchars($plan_row['name']) ?></strong> — ₹<?= number_format($plan_row['price']) ?>/<?= (int)($plan_row['billing_cycle_days'] ?? 30) === 30 ? 'month' : ((int)$plan_row['billing_cycle_days']) . ' days' ?>.<?php endif; ?>
              </p>
            <?php endif; ?>
            <?php if ($plan_row): ?>
              <button type="button" class="btn btn-sm bg-primary-theme text-white" id="btnSubscribe">
                <?= $membership_active ? 'Renew Early' : 'Subscribe Now' ?> — ₹<?= number_format($plan_row['price']) ?>
              </button>
              <div id="subscribeMsg" class="mt-2" style="font-size:.8rem;"></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="fa fa-users me-2" style="color:var(--primary)"></i>Refer a Doctor</h6>
            <p class="mb-2" style="font-size:.85rem;color:#6b7280;">
              Share your link — when a doctor you refer pays for their membership, you earn 10% of that payment.
            </p>
            <div class="input-group input-group-sm mb-2">
              <input type="text" class="form-control" id="referralLinkInput" value="<?= htmlspecialchars($referral_link) ?>" readonly>
              <button type="button" class="btn btn-outline-primary" id="btnCopyReferral"><i class="fa fa-copy"></i> Copy</button>
            </div>
            <div style="font-size:.85rem;color:#1f2937;">
              <strong><?= (int)($referral_stats['referred_count'] ?? 0) ?></strong> doctor<?= (int)($referral_stats['referred_count'] ?? 0) === 1 ? '' : 's' ?> referred
              &nbsp;·&nbsp; <strong>₹<?= number_format($referral_stats['total_earned'] ?? 0, 2) ?></strong> earned
              <span class="text-muted">(paid out manually by admin)</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Chart + Today's Appointments ── -->
    <div class="row g-3 mt-1">
      <div class="col-lg-8">

        <!-- Chart -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
          <div class="card-header bg-white border-0 pt-3 pb-2">
            <h6 class="fw-bold mb-0">
              <i class="fa fa-bar-chart me-2" style="color:var(--primary)"></i>
              Appointments — Last 6 Months
            </h6>
          </div>
          <div class="card-body">
            <div class="chart-wrap"><canvas id="appointmentsChart"></canvas></div>
          </div>
        </div>

        <!-- Today's Appointments -->
        <div class="card border-0 shadow-sm rounded-3">
          <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
            <h6 class="fw-bold mb-0">
              <i class="fa fa-calendar-check-o me-2" style="color:var(--primary)"></i>
              Today's Appointments
            </h6>
            <a href="appointments.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="font-size:.73rem;">Time</th>
                    <th style="font-size:.73rem;">Patient</th>
                    <th style="font-size:.73rem;">Status</th>
                    <th style="font-size:.73rem;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($today_result->num_rows == 0): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">No appointments today.</td>
                    </tr>
                  <?php endif; ?>
                  <?php while ($appt = $today_result->fetch_assoc()):
                    $st = $appt['status'] ?: 'pending';
                    $sc = 'appt-' . $st;
                    $status_label = ucfirst(str_replace('_', ' ', $st));
                  ?>
                    <tr>
                      <td><strong style="font-size:.82rem;"><?= date('h:i A', strtotime($appt['appointment_time'])) ?></strong></td>
                      <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                          <div class="pat-avatar">
                            <?php if (!empty($appt['patient_image'])): ?>
                              <img src="<?= BASE_URL . $appt['patient_image'] ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                              <i class="fa fa-user"></i>
                            <?php endif; ?>
                          </div>
                          <span style="font-size:.83rem;"><?= htmlspecialchars($appt['patient_name']) ?></span>
                        </div>
                      </td>
                      <td><span class="appt-badge <?= $sc ?>"><?= $status_label ?></span></td>
                      <td>
                        <a href="<?= BASE_URL ?>doctor/patient-profile.php?id=<?= (int)$appt['patient_id'] ?>"
                          class="btn btn-sm btn-outline-primary" title="View"><i class="fa fa-eye"></i></a>
                        <?php if ($appt['patient_phone']): ?>
                          <a href="tel:<?= $appt['patient_phone'] ?>" class="btn btn-sm btn-outline-success" title="Call">
                            <i class="fa fa-phone"></i>
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- Right column -->
      <div class="col-lg-4">

        <!-- Recent Patients -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
          <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
            <h6 class="fw-bold mb-0"><i class="fa fa-users me-2" style="color:var(--primary)"></i>Recent Patients</h6>
            <a href="my-patients.php" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <?php if ($recent_result->num_rows == 0): ?>
              <p class="text-muted text-center py-3">No patients yet.</p>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php while ($pat = $recent_result->fetch_assoc()):
                  $age = $pat['dob'] ? date_diff(date_create($pat['dob']), date_create('today'))->y : '—';
                ?>
                  <a href="patient-profile.php?id=<?= (int)$pat['id'] ?>"
                    class="list-group-item list-group-item-action"
                    style="display:flex;align-items:center;gap:10px;padding:10px 16px;">
                    <div class="pat-avatar" style="flex-shrink:0;">
                      <?php if (!empty($pat['profile_pic'])): ?>
                        <img src="<?= BASE_URL . $pat['profile_pic'] ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                      <?php else: ?>
                        <i class="fa fa-user"></i>
                      <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-size:.84rem;font-weight:600;"><?= htmlspecialchars($pat['name']) ?></div>
                      <div style="font-size:.73rem;color:#6b7280;"><?= $pat['gender'] ?> · <?= $age ?> yrs · <?= $pat['total_visits'] ?> visits</div>
                    </div>
                  </a>
                <?php endwhile; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="card border-0 shadow-sm rounded-3">
          <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
            <h6 class="fw-bold mb-0"><i class="fa fa-calendar me-2" style="color:var(--primary)"></i>Upcoming</h6>
            <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <?php if ($upcoming_result->num_rows == 0): ?>
              <p class="text-muted text-center py-3">No upcoming appointments.</p>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php while ($appt = $upcoming_result->fetch_assoc()):
                  $st = $appt['status'] ?: 'pending';
                  $sc = 'appt-' . $st;
                  $status_label = ucfirst(str_replace('_', ' ', $st));
                ?>
                  <div class="list-group-item" style="padding:10px 16px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                      <div>
                        <div style="font-size:.84rem;font-weight:600;"><?= htmlspecialchars($appt['patient_name']) ?></div>
                        <div style="font-size:.73rem;color:#6b7280;">
                          <?= date('d M', strtotime($appt['appointment_date'])) ?>
                          · <?= date('h:i A', strtotime($appt['appointment_time'])) ?>
                        </div>
                      </div>
                      <span class="appt-badge <?= $sc ?>"><?= $status_label ?></span>
                    </div>
                    <?php if ($appt['patient_phone']): ?>
                      <div style="margin-top:6px;">
                        <a href="tel:<?= $appt['patient_phone'] ?>" class="btn btn-sm btn-outline-primary">
                          <i class="fa fa-phone"></i> Call
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

  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var ctx = document.getElementById('appointmentsChart').getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?= json_encode($monthly_labels) ?>,
          datasets: [{
            label: 'Total',
            data: <?= json_encode($monthly_totals) ?>,
            borderColor: '#0C74C5',
            backgroundColor: 'rgba(12,116,197,.1)',
            borderWidth: 2,
            fill: true,
            tension: .3
          }, {
            label: 'Completed',
            data: <?= json_encode($monthly_completed) ?>,
            borderColor: '#02c9b8',
            backgroundColor: 'rgba(2,201,184,.1)',
            borderWidth: 2,
            fill: true,
            tension: .3
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
              position: 'top'
            },
            tooltip: {
              mode: 'index',
              intersect: false
            }
          }
        }
      });
    });
  </script>
  <script>
    document.getElementById('btnCopyReferral').addEventListener('click', function() {
      var input = document.getElementById('referralLinkInput');
      input.select();
      input.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(input.value).then(function() {
        var btn = document.getElementById('btnCopyReferral');
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check"></i> Copied';
        setTimeout(function() { btn.innerHTML = original; }, 1800);
      });
    });
  </script>
  <?php if ($plan_row): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
      document.getElementById('btnSubscribe').addEventListener('click', function() {
        var btn = this;
        var msg = document.getElementById('subscribeMsg');
        msg.textContent = '';
        btn.disabled = true;
        var originalLabel = btn.innerHTML;
        btn.innerHTML = 'Preparing payment…';

        fetch('<?= BASE_URL ?>doctor/create-subscription-order.php', { method: 'POST' })
          .then(function(r) { return r.json(); })
          .then(function(order) {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            if (!order.success) {
              msg.className = 'mt-2 text-danger';
              msg.textContent = order.message || 'Could not start the payment.';
              return;
            }

            var rzp = new Razorpay({
              key: order.key_id,
              order_id: order.order_id,
              amount: order.amount,
              currency: order.currency,
              name: 'Rejuvenate Digital Health',
              description: order.plan_name + ' — Doctor Membership',
              theme: { color: '#0C74C5' },
              handler: function(response) {
                var fd = new FormData();
                fd.append('razorpay_order_id', response.razorpay_order_id);
                fd.append('razorpay_payment_id', response.razorpay_payment_id);
                fd.append('razorpay_signature', response.razorpay_signature);
                fetch('<?= BASE_URL ?>doctor/verify-subscription-payment.php', { method: 'POST', body: fd })
                  .then(function(r) { return r.json(); })
                  .then(function(res) {
                    if (res.success) {
                      window.location.reload();
                    } else {
                      msg.className = 'mt-2 text-danger';
                      msg.textContent = res.message || 'Payment verification failed.';
                    }
                  });
              },
              modal: {
                ondismiss: function() {
                  msg.className = 'mt-2 text-muted';
                  msg.textContent = 'Payment was cancelled.';
                },
              },
            });
            rzp.on('payment.failed', function() {
              msg.className = 'mt-2 text-danger';
              msg.textContent = 'Payment failed. Please try again.';
            });
            rzp.open();
          })
          .catch(function() {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            msg.className = 'mt-2 text-danger';
            msg.textContent = 'Network error. Please try again.';
          });
      });
    </script>
  <?php endif; ?>
</body>

</html>