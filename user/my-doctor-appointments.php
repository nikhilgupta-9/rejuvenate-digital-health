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

// Paginate the (already filtered) list — keeps the page light and the mobile
// card view from turning into an endless scroll for patients with a long history.
$per_page = 10;
$total_filtered = count($appointments);
$total_pages = max(1, (int) ceil($total_filtered / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$appointments_page = array_slice($appointments, ($page - 1) * $per_page, $per_page);

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

// Preserves the other active filter when a link only changes one of them —
// same helper shape as doctor/appointments.php's appt_url()
function appt_url($overrides, $status_filter, $search_query)
{
    $params = array_filter([
        'status' => $overrides['status'] ?? $status_filter,
        'search' => $search_query,
        'page'   => $overrides['page'] ?? null,
    ], fn($v) => $v !== null && $v !== '' && $v !== 'all');
    return '?' . http_build_query($params);
}

// Status → colour map (shared by badges + stat chips) — same convention as
// the doctor panel's $STATUS_META in doctor/appointments.php
$STATUS_META = [
    'pending'   => ['Pending',   '#f59e0b', '#fff7e6'],
    'approved'  => ['Approved',  '#0C74C5', '#e7f2fb'],
    'completed' => ['Completed', '#0e7c5b', '#e6f6f0'],
    'rejected'  => ['Cancelled', '#dc2626', '#fdecec'],
    'no_show'   => ['No Show',   '#6b7280', '#f1f2f4'],
];
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
  <!-- .ap-* list styles (stat chips, table/cards, pagination) now live in user/assets/style.css -->
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
          
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h1 class="ap-h">My Appointments</h1>
              <div class="ap-sub">Your consultations with Rejuvenate doctors</div>
            </div>
            <a href="my-bookings.php" class="btn btn-primary btn-sm d-none d-lg-inline-flex align-items-center" style="background:var(--primary);border-color:var(--primary);">
              <i class="fa fa-plus me-1"></i> Book New Appointment
            </a>
          </div>

          <!-- Stat chips (click to filter) -->
          <div class="stat-row">
            <a class="stat-chip <?= $status_filter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars(appt_url(['status' => 'all'], 'all', $search_query)) ?>">
              <div class="sc-num" style="color:#1f2937;"><?= $total_appointments ?></div>
              <div class="sc-lbl">Total</div>
            </a>
            <a class="stat-chip <?= $status_filter === 'upcoming' ? 'active' : '' ?>" href="<?= htmlspecialchars(appt_url(['status' => 'upcoming'], $status_filter, $search_query)) ?>">
              <div class="sc-num" style="color:var(--accent-dk);"><?= $total_upcoming ?></div>
              <div class="sc-lbl">Upcoming</div>
            </a>
            <?php foreach (['pending' => $total_pending, 'approved' => $total_approved, 'completed' => $total_completed, 'rejected' => $total_rejected] as $key => $count): ?>
              <a class="stat-chip <?= $status_filter === $key ? 'active' : '' ?>" href="<?= htmlspecialchars(appt_url(['status' => $key], $status_filter, $search_query)) ?>">
                <div class="sc-num" style="color:<?= $STATUS_META[$key][1] ?>;"><?= $count ?></div>
                <div class="sc-lbl"><?= $STATUS_META[$key][0] ?></div>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="ap-panel">
            <div class="ap-panel-head">
              <h5 class="mb-0" style="font-size:1rem;font-weight:700;color:#1f2937;">
                <?= $total_filtered ?> appointment<?= $total_filtered === 1 ? '' : 's' ?>
              </h5>
              <form method="GET" action="" class="filter-bar">
                <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                  <?php foreach (['all' => 'All statuses', 'upcoming' => 'Upcoming', 'past' => 'Past', 'pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Cancelled'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $status_filter === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="search" class="form-control form-control-sm" style="width:200px;"
                    placeholder="Doctor, specialization, purpose…" value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary);border-color:var(--primary);">
                  <i class="fa fa-search"></i>
                </button>
                <?php if ($status_filter !== 'all' || $search_query !== ''): ?>
                  <a href="my-doctor-appointments.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php endif; ?>
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
                <a href="my-bookings.php" class="btn btn-primary mt-3" style="background:var(--primary);border-color:var(--primary);">
                  <i class="fa fa-calendar-plus"></i> Book Appointment
                </a>
              </div>
            <?php else: ?>
              <?php
              /* Reusable per-row action buttons — same shape as doctor/appointments.php's $render_actions */
              $render_actions = function ($appointment) {
                  ob_start(); ?>
                  <div class="btn-group btn-group-sm ap-actions">
                    <a href="appointment-details.php?id=<?= $appointment['id'] ?>" class="btn btn-outline-secondary" title="View Details">
                      <i class="fa fa-eye"></i>
                    </a>
                    <?php if ($appointment['status'] === 'completed'): ?>
                      <a href="<?= BASE_URL ?>doctor/opd-slip.php?appointment_id=<?= $appointment['id'] ?>" target="_blank" class="btn btn-outline-primary" title="Download OPD Slip / Prescription" style="color:var(--primary);border-color:var(--primary);">
                        <i class="fa fa-file-pdf"></i>
                      </a>
                    <?php endif; ?>
                    <?php if (in_array($appointment['status'], ['pending', 'approved'], true) && $appointment['appointment_status'] === 'upcoming'): ?>
                      <a href="?cancel_id=<?= $appointment['id'] ?>" class="btn btn-outline-danger" title="Cancel"
                         onclick="return confirm('Are you sure you want to cancel this appointment?')">
                        <i class="fa fa-times"></i>
                      </a>
                      <?php if ($appointment['status'] === 'pending'): ?>
                        <a href="?reschedule_id=<?= $appointment['id'] ?>" class="btn btn-outline-secondary" title="Reschedule">
                          <i class="fa fa-calendar-alt"></i>
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($appointment['appointment_type'] === 'online' && $appointment['meeting_status'] !== 'cancelled'): ?>
                      <?php if ($appointment['status'] === 'approved'): ?>
                        <a href="<?= BASE_URL ?>telemedicine/join.php?appointment_id=<?= $appointment['id'] ?>"
                           class="btn btn-primary" target="_blank" title="Join video call" style="background:var(--primary);border-color:var(--primary);">
                          <i class="fa fa-video"></i>
                        </a>
                      <?php elseif ($appointment['status'] === 'pending'): ?>
                        <span class="btn btn-outline-secondary disabled" title="Video call unlocks once the doctor approves this appointment">
                          <i class="fa fa-video"></i>
                        </span>
                      <?php endif; ?>
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
                      <th>Doctor</th>
                      <th>Date &amp; Time</th>
                      <th>Fee</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($appointments_page as $appointment): ?>
                      <?php
                      $has_avatar = !empty($appointment['profile_image']) && file_exists('../admin/' . $appointment['profile_image']);
                      $meta = $STATUS_META[$appointment['status']] ?? $STATUS_META['pending'];
                      $is_today = $appointment['date_only'] === date('Y-m-d');
                      $is_past  = $appointment['appointment_status'] === 'past';
                      ?>
                      <tr class="<?= $is_today ? 'is-today' : '' ?>">
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <?php if ($has_avatar): ?>
                              <img src="<?= BASE_URL ."admin/". htmlspecialchars($appointment['profile_image']) ?>" class="doctor-avatar" alt="">
                            <?php else: ?>
                              <span class="doctor-avatar"><i class="fa fa-user-md"></i></span>
                            <?php endif; ?>
                            <div>
                              <strong>Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></strong><br>
                              <small class="text-muted"><?= htmlspecialchars($appointment['specialization']) ?></small>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div style="font-weight:600;color:var(--primary);"><?= $appointment['formatted_time'] ?></div>
                          <small class="text-muted"><?= $appointment['formatted_date'] ?></small>
                          <?php if ($is_today): ?><span class="badge-status" style="background:#e7f2fb;color:var(--primary);">Today</span>
                          <?php elseif ($is_past): ?><span class="badge-status" style="background:#f1f2f4;color:#6b7280;">Past</span><?php endif; ?>
                        </td>
                        <td>₹<?= number_format($appointment['consultation_fee']) ?></td>
                        <td>
                          <span class="badge-status" style="background:<?= $meta[2] ?>;color:<?= $meta[1] ?>;"><?= $meta[0] ?></span>
                        </td>
                        <td><?= $render_actions($appointment) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- Mobile cards -->
              <div class="ap-cards-wrap">
                <?php foreach ($appointments_page as $appointment): ?>
                  <?php
                  $has_avatar = !empty($appointment['profile_image']) && file_exists('../admin/' . $appointment['profile_image']);
                  $meta = $STATUS_META[$appointment['status']] ?? $STATUS_META['pending'];
                  $is_today = $appointment['date_only'] === date('Y-m-d');
                  ?>
                  <div class="ap-card <?= $is_today ? 'is-today' : '' ?>">
                    <div class="ac-top">
                      <div class="d-flex align-items-center gap-2">
                        <?php if ($has_avatar): ?>
                          <img src="<?= BASE_URL .'admin/'. htmlspecialchars($appointment['profile_image']) ?>" class="doctor-avatar">
                        <?php else: ?>
                          <span class="doctor-avatar"><i class="fa fa-user-md"></i></span>
                        <?php endif; ?>
                        <div>
                          <strong>Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></strong>
                          <div class="ac-meta"><?= htmlspecialchars($appointment['specialization']) ?></div>
                        </div>
                      </div>
                      <span class="badge-status" style="background:<?= $meta[2] ?>;color:<?= $meta[1] ?>;"><?= $meta[0] ?></span>
                    </div>
                    <div class="ac-meta mt-2">
                      <i class="fa fa-clock-o me-1"></i><?= $appointment['formatted_time'] ?>
                      &nbsp;·&nbsp; <i class="fa fa-calendar me-1"></i><?= $appointment['formatted_date'] ?>
                      &nbsp;·&nbsp; <i class="fa fa-rupee-sign me-1"></i><?= number_format($appointment['consultation_fee']) ?>
                      <?php if ($is_today): ?> &nbsp;<span class="badge-status" style="background:#e7f2fb;color:var(--primary);">Today</span><?php endif; ?>
                    </div>
                    <?php if (!empty($appointment['purpose'])): ?>
                      <div class="ac-meta mt-1"><i class="fa fa-comment-o me-1"></i><?= htmlspecialchars(substr($appointment['purpose'], 0, 100)) ?><?= strlen($appointment['purpose']) > 100 ? '…' : '' ?></div>
                    <?php endif; ?>
                    <div class="ac-row"><?= $render_actions($appointment) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Results Count + Pagination -->
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="text-muted small">
                  Showing <?= count($appointments_page) ?> of <?= $total_filtered ?> appointment<?= $total_filtered !== 1 ? 's' : '' ?>
                  <?php if ($search_query): ?>
                    matching "<?= htmlspecialchars($search_query) ?>"
                  <?php endif; ?>
                </div>
                <?php if ($total_pages > 1): ?>
                  <nav aria-label="Appointments pagination">
                    <ul class="pagination pagination-sm mb-0">
                      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars(appt_url(['page' => $page - 1], $status_filter, $search_query)) ?>">Prev</a>
                      </li>
                      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                          <a class="page-link" href="<?= htmlspecialchars(appt_url(['page' => $p], $status_filter, $search_query)) ?>"><?= $p ?></a>
                        </li>
                      <?php endfor; ?>
                      <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars(appt_url(['page' => $page + 1], $status_filter, $search_query)) ?>">Next</a>
                      </li>
                    </ul>
                  </nav>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <a href="my-bookings.php" class="fab-add" title="Book new appointment"><i class="fa fa-plus"></i></a>
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