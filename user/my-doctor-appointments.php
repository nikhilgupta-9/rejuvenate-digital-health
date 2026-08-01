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

// Initialize variables
$success_message = '';
$error_message = '';
$search_query = '';
$status_filter = 'all';

// Handle search and filter
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search_query = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status'] ?? 'all';
}

// Build query for fetching appointments
$sql = "
    SELECT 
        a.*,
        d.name as doctor_name,
        d.specialization,
        d.degrees,
        d.consultation_fee,
        d.profile_image,
        d.experience_years,
        d.rating,
        d.phone as doctor_phone,
        d.email as doctor_email,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
        DATE_FORMAT(a.appointment_date, '%d/%m/%Y') as formatted_date,
        DATE_FORMAT(a.appointment_date, '%Y-%m-%d') as date_only,
        CASE 
            WHEN a.appointment_date < CURDATE() THEN 'past'
            WHEN a.appointment_date = CURDATE() AND a.appointment_time < CURTIME() THEN 'past'
            WHEN a.appointment_date > CURDATE() THEN 'upcoming'
            WHEN a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME() THEN 'upcoming'
            ELSE 'past'
        END as appointment_status
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.user_id = ?
";

$params = [$user_id];
$types = "i";

// Apply search filter
if (!empty($search_query)) {
    $sql .= " AND (d.name LIKE ? OR d.specialization LIKE ? OR a.purpose LIKE ?)";
    $search_term = "%$search_query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Apply status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'upcoming') {
        $sql .= " AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time > CURTIME()))";
    } elseif ($status_filter === 'past') {
        $sql .= " AND (a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))";
    } else {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

// Order by appointment date and time
$sql .= " ORDER BY 
    CASE 
        WHEN a.appointment_date > CURDATE() THEN 0
        WHEN a.appointment_date = CURDATE() AND a.appointment_time > CURTIME() THEN 1
        ELSE 2
    END,
    a.appointment_date DESC,
    a.appointment_time DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);

if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $params[0]);
}

$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
$total_appointments = 0;
$total_upcoming = 0;
$total_past = 0;
$total_pending = 0;
$total_approved = 0;
$total_completed = 0;
$total_rejected = 0;

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
    $total_appointments++;

    // Count by appointment type
    if ($row['appointment_status'] === 'upcoming') {
        $total_upcoming++;
    } else {
        $total_past++;
    }

    // Count by status — appointments.status is ENUM('pending','approved','rejected','completed','no_show')
    switch ($row['status']) {
        case 'pending':
            $total_pending++;
            break;
        case 'approved':
            $total_approved++;
            break;
        case 'completed':
            $total_completed++;
            break;
        case 'rejected':
            $total_rejected++;
            break;
    }
}

$stmt->close();

// Handle appointment cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    
    // Verify that the appointment belongs to the current user
    $check_stmt = $conn->prepare("SELECT id, appointment_date, appointment_time FROM appointments WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $cancel_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $check_stmt->bind_result($app_id, $app_date, $app_time);
        $check_stmt->fetch();
        
        // Check if appointment is in the future
        $appointment_datetime = strtotime("$app_date $app_time");
        $current_datetime = time();
        
        if ($appointment_datetime > $current_datetime) {
            // 'cancelled' isn't a valid status enum value — reuse 'rejected',
            // same as the doctor-side cancel action.
            $cancel_stmt = $conn->prepare("UPDATE appointments SET status = 'rejected' WHERE id = ?");
            $cancel_stmt->bind_param("i", $cancel_id);
            
            if ($cancel_stmt->execute()) {
                $success_message = "Appointment cancelled successfully!";
                
                // Refresh page to update counts
                header("Location: appointments.php?success=cancelled");
                exit();
            } else {
                $error_message = "Failed to cancel appointment. Please try again.";
            }
            $cancel_stmt->close();
        } else {
            $error_message = "Cannot cancel past appointments.";
        }
    } else {
        $error_message = "Appointment not found or you don't have permission to cancel it.";
    }
    $check_stmt->close();
}

// Handle rescheduling
if (isset($_GET['reschedule_id'])) {
    $reschedule_id = intval($_GET['reschedule_id']);
    // You would redirect to a rescheduling page or open a modal
    // For now, just store the ID in session
    $_SESSION['reschedule_appointment_id'] = $reschedule_id;
    header("Location: my-bookings.php?reschedule=$reschedule_id");
    exit();
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'cancelled') {
        $success_message = "Appointment cancelled successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>My Appointments | REJUVENATE Digital Health</title>
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
  <style>
    /* Page-specific styles only — colors/stat-cards/status-badges/action-btn
       all come from the shared theme (user/assets/style.css, --primary #0C74C5
       / --accent #02c9b8), same palette the doctor panel uses. */

    /* ── Appointment cards (mobile) ── */
    .appointment-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 14px;
        background: #fff;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        transition: box-shadow .2s;
    }
    .appointment-card:hover { box-shadow: 0 6px 18px rgba(0, 0, 0, .1); }
    .appointment-card.upcoming { border-left: 4px solid var(--primary); }
    .appointment-card.past     { border-left: 4px solid #9ca3af; }

    /* ── Doctor avatar ── */
    .doctor-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
    }

    /* ── Filter card ── */
    .filter-card {
        background: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
    }

    /* ── Table ── */
    .booking-table {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
    }
    .booking-table table { margin: 0; }
    .booking-table th {
        background: var(--primary);
        color: #fff;
        border: none;
        padding: 13px 15px;
        font-weight: 600;
        font-size: .82rem;
    }
    .booking-table td { padding: 13px 15px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
    .booking-table tr:last-child td { border-bottom: none; }
    .booking-table tr:hover { background: #f8fafc; }

    /* Video-call hint for online appointments still awaiting approval */
    .video-pending-hint {
        font-size: .68rem;
        color: #9ca3af;
        white-space: nowrap;
    }

    /* ── Empty state ── */
    .no-appointments { text-align: center; padding: 50px 20px; color: #6b7280; }
    .no-appointments i { font-size: 2.4rem; color: #d1d5db; display: block; margin-bottom: 10px; }

    /* ── Status filter pills ── */
    .status-tabs .nav-link {
        color: #6b7280;
        font-weight: 600;
        font-size: .84rem;
        padding: 8px 16px;
        border-radius: 8px;
        margin-right: 5px;
        border: 1px solid transparent;
    }
    .status-tabs .nav-link.active {
        background: var(--primary);
        color: #fff;
    }

    /* ── Search box ── */
    .search-box { position: relative; }
    .search-box .form-control { padding-right: 40px; border-radius: 10px; }
    .search-box .search-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    @media (max-width: 768px) {
        .profile-card { padding: 1rem; }
        .filter-card { padding: 1rem; }
        .status-tabs .nav-link { padding: 7px 12px; font-size: .8rem; margin-bottom: 4px; }
        .appointment-card .doctor-avatar { width: 48px; height: 48px; }
        .appointment-card .appointment-actions { display: flex; gap: 6px; }
        .profile-card .d-flex.justify-content-between { flex-direction: column; gap: 10px; align-items: flex-start !important; }
        .profile-card .d-flex.justify-content-between .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .status-tabs .nav { flex-wrap: wrap; }
        .status-tabs .nav-link { font-size: .75rem; padding: 6px 10px; }
        .search-box .form-control { font-size: .88rem; }
    }
  </style>
</head>

<body>
  <?php $sidebar_active = 'appointments'; include("sidebar.php"); ?>
  <main class="patient-content">
          <!-- Success/Error Messages -->
          <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= $success_message ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>
          
          <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?= $error_message ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>
          
          <div class="profile-card shadow">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h4>My Appointments</h4>
              <a href="my-bookings.php" class="btn btn-primary">
                <i class="fa fa-calendar-plus"></i> Book New Appointment
              </a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4 g-3">
              <div class="col-md-3 col-6">
                <div class="stat-card card-primary">
                  <i class="fa fa-calendar-check bg-icon"></i>
                  <div class="num"><?= $total_appointments ?></div>
                  <div class="lbl">Total</div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="stat-card card-accent">
                  <i class="fa fa-hourglass-half bg-icon"></i>
                  <div class="num"><?= $total_upcoming ?></div>
                  <div class="lbl">Upcoming</div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="stat-card card-orange">
                  <i class="fa fa-clock bg-icon"></i>
                  <div class="num"><?= $total_pending ?></div>
                  <div class="lbl">Pending</div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="stat-card card-green">
                  <i class="fa fa-check-circle bg-icon"></i>
                  <div class="num"><?= $total_approved ?></div>
                  <div class="lbl">Approved</div>
                </div>
              </div>
            </div>

            <!-- Search and Filter -->
            <div class="filter-card mb-4">
              <form method="GET" action="" class="row g-3">
                <div class="col-md-8">
                  <div class="search-box">
                    <input type="text" class="form-control" name="search" placeholder="Search by doctor name, specialization or purpose..." value="<?= htmlspecialchars($search_query) ?>">
                    <i class="fa fa-search search-icon"></i>
                  </div>
                </div>
                <div class="col-md-4">
                  <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="past" <?= $status_filter === 'past' ? 'selected' : '' ?>>Past</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                  </select>
                </div>
                <div class="col-12">
                  <div class="status-tabs">
                    <ul class="nav nav-pills">
                      <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" href="?status=all">All</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'upcoming' ? 'active' : '' ?>" href="?status=upcoming">Upcoming</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'approved' ? 'active' : '' ?>" href="?status=approved">Approved</a>
                      </li>
                    </ul>
                  </div>
                </div>
              </form>
            </div>
            
            <!-- Appointments List -->
            <?php if (empty($appointments)): ?>
              <div class="no-appointments">
                <i class="fa fa-calendar-times"></i>
                <h4>No Appointments Found</h4>
                <p>
                  <?php if ($search_query || $status_filter !== 'all'): ?>
                    No appointments match your search criteria. Try different filters.
                  <?php else: ?>
                    You haven't booked any appointments yet. Book your first appointment now!
                  <?php endif; ?>
                </p>
                <a href="my-bookings.php" class="btn btn-primary mt-3">
                  <i class="fa fa-calendar-plus"></i> Book Appointment
                </a>
              </div>
            <?php else: ?>
              <!-- Table View -->
              <div class="booking-table mb-4 d-none d-md-block">
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th scope="col">#</th>
                        <th scope="col">Doctor</th>
                        <th scope="col">Specialization</th>
                        <th scope="col">Date & Time</th>
                        <th scope="col">Fee</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($appointments as $index => $appointment): ?>
                        <tr>
                          <th scope="row"><?= $index + 1 ?></th>
                          <td>
                            <div class="d-flex align-items-center">
                              <?php if (!empty($appointment['profile_image'])): ?>
                                <img src="<?= BASE_URL ."admin/". htmlspecialchars($appointment['profile_image']) ?>" 
                                     alt="Doctor" 
                                     class="doctor-avatar me-2">
                              <?php else: ?>
                                <div class="doctor-avatar me-2 bg-light d-flex align-items-center justify-content-center">
                                  <i class="fa fa-user-md text-muted"></i>
                                </div>
                              <?php endif; ?>
                              <div>
                                <strong>Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($appointment['degrees']) ?></small>
                              </div>
                            </div>
                          </td>
                          <td><?= htmlspecialchars($appointment['specialization']) ?></td>
                          <td>
                            <?= $appointment['formatted_date'] ?><br>
                            <small><?= $appointment['formatted_time'] ?></small>
                          </td>
                          <td>₹<?= number_format($appointment['consultation_fee']) ?></td>
                          <td>
                            <span class="status-badge status-<?= $appointment['status'] ?>">
                              <?= ucfirst($appointment['status']) ?>
                            </span>
                          </td>
                          <td>
                            <div class="btn-group btn-group-sm">
                              <a href="appointment-details.php?id=<?= $appointment['id'] ?>"
                                 class="btn btn-outline-secondary" title="View Details">
                                <i class="fa fa-eye"></i>
                              </a>

                              <?php if (in_array($appointment['status'], ['pending', 'approved'], true) && $appointment['appointment_status'] === 'upcoming'): ?>
                                <a href="?cancel_id=<?= $appointment['id'] ?>"
                                   class="btn btn-outline-danger"
                                   title="Cancel"
                                   onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                  <i class="fa fa-times"></i>
                                </a>

                                <?php if ($appointment['status'] === 'pending'): ?>
                                  <a href="?reschedule_id=<?= $appointment['id'] ?>"
                                     class="btn btn-outline-secondary"
                                     title="Reschedule">
                                    <i class="fa fa-calendar-alt"></i>
                                  </a>
                                <?php endif; ?>
                              <?php endif; ?>

                              <?php if ($appointment['appointment_type'] === 'online' && $appointment['meeting_status'] !== 'cancelled'): ?>
                                <?php if ($appointment['status'] === 'approved'): ?>
                                  <a href="<?= BASE_URL ?>telemedicine/join.php?appointment_id=<?= $appointment['id'] ?>"
                                     class="btn btn-success" title="Join Video Call" target="_blank">
                                    <i class="fa fa-video"></i>
                                  </a>
                                <?php elseif ($appointment['status'] === 'pending'): ?>
                                  <span class="btn btn-outline-secondary disabled" title="Video call unlocks once the doctor approves this appointment">
                                    <i class="fa fa-video"></i>
                                  </span>
                                <?php endif; ?>
                              <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              
              <!-- Card View for Mobile -->
              <div class="d-block d-md-none">
                <?php foreach ($appointments as $appointment): ?>
                  <div class="appointment-card <?= $appointment['appointment_status'] ?>">
                    <div class="d-flex align-items-start mb-3">
                      <?php if (!empty($appointment['profile_image']) && file_exists('../admin/'. htmlspecialchars($appointment['profile_image']))): ?>
                        <img src="<?= BASE_URL .'admin/'. htmlspecialchars($appointment['profile_image']) ?>" 
                             alt="Doctor" 
                             class="doctor-avatar me-3">
                      <?php else: ?>
                        <div class="doctor-avatar me-3 bg-light d-flex align-items-center justify-content-center">
                          <i class="fa fa-user-md text-muted"></i>
                        </div>
                      <?php endif; ?>
                      <div>
                        <h6 class="mb-1">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></h6>
                        <p class="mb-1 small text-muted">
                          <?= htmlspecialchars($appointment['specialization']) ?>
                        </p>
                        <p class="mb-0 small">
                          <?= htmlspecialchars($appointment['degrees']) ?>
                        </p>
                      </div>
                    </div>
                    
                    <div class="mb-3">
                      <p class="mb-1">
                        <i class="fa fa-calendar text-primary me-2"></i>
                        <strong><?= $appointment['formatted_date'] ?></strong>
                      </p>
                      <p class="mb-2">
                        <i class="fa fa-clock text-primary me-2"></i>
                        <?= $appointment['formatted_time'] ?>
                      </p>
                      <p class="mb-0">
                        <i class="fa fa-rupee-sign text-success me-2"></i>
                        <strong>₹<?= number_format($appointment['consultation_fee']) ?></strong>
                      </p>
                    </div>
                    
                    <?php if (!empty($appointment['purpose'])): ?>
                      <div class="mb-3">
                        <p class="mb-1 small text-muted">Purpose:</p>
                        <p class="mb-0 small"><?= htmlspecialchars(substr($appointment['purpose'], 0, 100)) ?><?= strlen($appointment['purpose']) > 100 ? '...' : '' ?></p>
                      </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="status-badge status-<?= $appointment['status'] ?>">
                        <?= ucfirst($appointment['status']) ?>
                      </span>
                      
                      <div class="appointment-actions btn-group btn-group-sm">
                        <a href="appointment-details.php?id=<?= $appointment['id'] ?>"
                           class="btn btn-outline-secondary">
                          <i class="fa fa-eye"></i>
                        </a>

                        <?php if (in_array($appointment['status'], ['pending', 'approved'], true) && $appointment['appointment_status'] === 'upcoming'): ?>
                          <a href="?cancel_id=<?= $appointment['id'] ?>"
                             class="btn btn-outline-danger"
                             onclick="return confirm('Are you sure you want to cancel this appointment?')">
                            <i class="fa fa-times"></i>
                          </a>
                        <?php endif; ?>

                        <?php if ($appointment['appointment_type'] === 'online' && $appointment['meeting_status'] !== 'cancelled'): ?>
                          <?php if ($appointment['status'] === 'approved'): ?>
                            <a href="<?= BASE_URL ?>telemedicine/join.php?appointment_id=<?= $appointment['id'] ?>"
                               class="btn btn-success" title="Join Video Call" target="_blank">
                              <i class="fa fa-video"></i>
                            </a>
                          <?php elseif ($appointment['status'] === 'pending'): ?>
                            <span class="btn btn-outline-secondary disabled" title="Video call unlocks once the doctor approves this appointment">
                              <i class="fa fa-video"></i>
                            </span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              
              <!-- Results Count -->
              <div class="mt-3 text-muted small">
                Showing <?= count($appointments) ?> appointment<?= count($appointments) !== 1 ? 's' : '' ?>
                <?php if ($search_query): ?>
                  matching "<?= htmlspecialchars($search_query) ?>"
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
  </main>
  <?php include("inc/scripts.php") ?>

  <script>
    // Auto-close alerts after 5 seconds
    setTimeout(() => {
      document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      });
    }, 5000);
    
    // Add confirmation for cancellation
    document.addEventListener('click', function(e) {
      if (e.target.closest('.cancel-btn') || (e.target.classList.contains('cancel-btn'))) {
        e.preventDefault();
        const url = e.target.closest('a').href || e.target.href;
        if (confirm('Are you sure you want to cancel this appointment?')) {
          window.location.href = url;
        }
      }
    });
    
    // Search functionality with debounce
    let searchTimeout;
    document.querySelector('input[name="search"]').addEventListener('input', function(e) {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        if (this.value.length >= 2 || this.value.length === 0) {
          this.closest('form').submit();
        }
      }, 500);
    });
  </script>
</body>
</html>