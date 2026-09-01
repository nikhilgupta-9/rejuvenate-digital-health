<?php
include "functions.php";
require_once __DIR__ . '/../lib/Settlement.php';

// send_appointment_confirmation_email() reads $GLOBALS['site'] for the login link —
// defined here so approving an appointment from this page doesn't emit an
// "undefined array key" warning (functions.php's email helpers rely on it existing).
$site = defined('BASE_URL') ? BASE_URL : '';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_message = '';
$page_message_type = '';

function csrf_ok(): bool
{
    return ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    if (!csrf_ok()) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $appointment_id = intval($_POST['approve_id']);
        $admin_id = $_SESSION['admin_id'] ?? null;

        $stmt = $conn->prepare("UPDATE appointments SET status = 'approved', approved_by_admin = 1, admin_verified_at = NOW(), admin_verified_by = ? WHERE id = ?");
        $stmt->bind_param('ii', $admin_id, $appointment_id);

        if ($stmt->execute()) {
            $page_message = 'Appointment approved.';
            $page_message_type = 'success';

            $info_stmt = $conn->prepare("
                SELECT a.*, u.name AS user_name, u.email AS user_email,
                       d.name AS doctor_name, d.email AS doctor_email, d.specialization, d.consultation_fee,
                       TIME_FORMAT(a.appointment_time, '%h:%i %p') AS formatted_time,
                       DATE_FORMAT(a.appointment_date, '%d %M, %Y') AS formatted_date
                FROM appointments a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                WHERE a.id = ?
            ");
            $info_stmt->bind_param('i', $appointment_id);
            $info_stmt->execute();
            $appt = $info_stmt->get_result()->fetch_assoc();

            if ($appt) {
                $patient_email = $appt['user_email'] ?? $appt['patient_email'];
                $patient_name = $appt['user_name'] ?? $appt['patient_name'];

                if (!empty($patient_email) && !empty($appt['doctor_name'])) {
                    $appointment_details = [
                        'appointment_id' => 'AP' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT),
                        'date' => $appt['formatted_date'],
                        'time' => $appt['formatted_time'],
                        'fee' => number_format($appt['consultation_fee'] ?? 0),
                        'type' => $appt['appointment_type'] ?: 'Clinic Visit',
                        'purpose' => $appt['purpose'],
                    ];
                    $doctor_details = [
                        'name' => $appt['doctor_name'],
                        'specialization' => $appt['specialization'],
                    ];

                    if (send_appointment_confirmation_email($patient_email, $patient_name, $appointment_details, $doctor_details)) {
                        $page_message = 'Appointment approved and confirmation email sent to patient.';
                    } else {
                        $page_message = 'Appointment approved but confirmation email failed to send.';
                    }

                    if (!empty($appt['doctor_email'])) {
                        $patient_details = [
                            'name' => $patient_name,
                            'age' => 'N/A',
                            'gender' => 'N/A',
                            'phone' => $appt['patient_phone'] ?? 'Not provided',
                            'email' => $patient_email,
                        ];
                        send_appointment_assignment_email($appt['doctor_email'], $appt['doctor_name'], $appointment_details, $patient_details);
                    }
                }
            }
        } else {
            $page_message = 'Failed to approve appointment.';
            $page_message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
    if (!csrf_ok()) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $appointment_id = intval($_POST['reject_id']);
        $reason = trim($_POST['reject_reason'] ?? '');
        // appointments has no dedicated rejection_reason column — record it in notes instead.
        $note_suffix = $reason !== '' ? ("\n[Rejected: " . $reason . ']') : '';

        $stmt = $conn->prepare("UPDATE appointments SET status = 'rejected', notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
        $stmt->bind_param('si', $note_suffix, $appointment_id);

        if ($stmt->execute()) {
            $page_message = 'Appointment rejected.';
            $page_message_type = 'success';
        } else {
            $page_message = 'Failed to reject appointment.';
            $page_message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_id'])) {
    if (!csrf_ok()) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $appointment_id = intval($_POST['complete_id']);
        $stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        create_settlement_if_needed($conn, $appointment_id);
        $page_message = 'Appointment marked as completed.';
        $page_message_type = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noshow_id'])) {
    if (!csrf_ok()) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $appointment_id = intval($_POST['noshow_id']);
        $stmt = $conn->prepare("UPDATE appointments SET status = 'no_show' WHERE id = ?");
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $page_message = 'Appointment marked as no-show.';
        $page_message_type = 'success';
    }
}

$today_label = date('l, d F Y');

$list_stmt = $conn->prepare("
    SELECT a.*, u.name AS user_name, u.email AS user_email, u.dob, u.gender,
           d.name AS doctor_name, d.specialization, d.consultation_fee, d.email AS doctor_email,
           TIME_FORMAT(a.appointment_time, '%h:%i %p') AS formatted_time
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    WHERE a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC
");
$list_stmt->execute();
$appointments = $list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$counts = ['pending' => 0, 'approved' => 0, 'completed' => 0, 'rejected' => 0, 'no_show' => 0, 'total' => count($appointments)];
foreach ($appointments as $a) {
    if (isset($counts[$a['status']])) $counts[$a['status']]++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Today's Appointments | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>

    <style>
        .status-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:uppercase; display:inline-block; min-width:80px; text-align:center; }
        .status-pending   { background:#fff3cd; color:#856404; border:1px solid #ffd97a; }
        .status-approved  { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .status-completed { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
        .status-rejected  { background:#f5f5f5; color:#666;    border:1px solid #e9ecef; }
        .status-no_show   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .stats-card { text-align:center; padding:15px; border-radius:8px; color:#fff; margin-bottom:15px; }
        .stats-card.pending   { background:#ea580c; }
        .stats-card.approved  { background:#16a34a; }
        .stats-card.completed { background:#0891b2; }
        .stats-card.rejected  { background:#6b7280; }
        .stats-card.total     { background:#0C74C5; }
        .stats-number { font-size:24px; font-weight:700; margin-bottom:5px; }
        .table tbody tr:hover { background:rgba(12,116,197,.04); }
        .table td { vertical-align:middle; }
        #searchInput { max-width: 280px; }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h3 class="mb-0 fw-bold">Today's Appointments</h3>
                                        <p class="text-muted mb-0 small"><?= htmlspecialchars($today_label) ?></p>
                                    </div>
                                    <div class="mt-2 mt-sm-0 d-flex align-items-center gap-2">
                                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search today's appointments...">
                                        <a href="all-appointment.php" class="btn_1">
                                            <i class="fas fa-list me-2"></i>All Appointments
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">

                                <?php if ($page_message): ?>
                                    <div class="alert alert-<?= htmlspecialchars($page_message_type) ?> alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($page_message) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="row mb-4">
                                    <div class="col-md-3 col-6">
                                        <div class="stats-card pending">
                                            <div class="stats-number"><?= $counts['pending'] ?></div>
                                            <div>Pending</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="stats-card approved">
                                            <div class="stats-number"><?= $counts['approved'] ?></div>
                                            <div>Approved</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="stats-card completed">
                                            <div class="stats-number"><?= $counts['completed'] ?></div>
                                            <div>Completed</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="stats-card total">
                                            <div class="stats-number"><?= $counts['total'] ?></div>
                                            <div>Total Today</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table lms_table_active table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="100">Time</th>
                                                <th>Patient</th>
                                                <th>Doctor</th>
                                                <th width="100">Status</th>
                                                <th>Purpose</th>
                                                <th width="220" class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($appointments)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="fas fa-calendar-day fs-1 text-muted mb-2"></i>
                                                            <span class="text-muted">No appointments scheduled for today.</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($appointments as $row):
                                                $patient_name = $row['user_name'] ?? $row['patient_name'];
                                                $patient_email = $row['user_email'] ?? $row['patient_email'];
                                            ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($row['formatted_time']) ?></strong></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($patient_name ?? 'N/A') ?></strong>
                                                        <?php if (!empty($row['patient_phone'])): ?>
                                                            <div class="text-muted small"><?= htmlspecialchars($row['patient_phone']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['doctor_name']): ?>
                                                            <strong>Dr. <?= htmlspecialchars($row['doctor_name']) ?></strong>
                                                            <div class="text-muted small"><?= htmlspecialchars($row['specialization'] ?? '') ?></div>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Not Assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?= htmlspecialchars($row['status']) ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($row['purpose'] ?? '') ?>">
                                                            <?= htmlspecialchars($row['purpose'] ?? '') ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <button type="button" class="btn btn-sm btn-outline-info view-btn"
                                                                data-bs-toggle="modal" data-bs-target="#viewModal"
                                                                data-id="AP<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?>"
                                                                data-patient="<?= htmlspecialchars($patient_name ?? 'N/A', ENT_QUOTES) ?>"
                                                                data-email="<?= htmlspecialchars($patient_email ?? '', ENT_QUOTES) ?>"
                                                                data-doctor="<?= htmlspecialchars($row['doctor_name'] ? 'Dr. ' . $row['doctor_name'] : 'Not assigned', ENT_QUOTES) ?>"
                                                                data-time="<?= htmlspecialchars($row['formatted_time'], ENT_QUOTES) ?>"
                                                                data-purpose="<?= htmlspecialchars($row['purpose'] ?? '', ENT_QUOTES) ?>"
                                                                data-status="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status'])), ENT_QUOTES) ?>"
                                                                title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>

                                                            <?php if ($row['status'] === 'pending'): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-success approve-btn" data-id="<?= (int) $row['id'] ?>" title="Approve">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger reject-btn" data-id="<?= (int) $row['id'] ?>" title="Reject">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            <?php elseif ($row['status'] === 'approved'): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-primary complete-btn" data-id="<?= (int) $row['id'] ?>" title="Mark Completed">
                                                                    <i class="fas fa-check-double"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary noshow-btn" data-id="<?= (int) $row['id'] ?>" title="Mark No-Show">
                                                                    <i class="fas fa-user-slash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <!-- Shared view-details modal, populated from the clicked row's data-* attributes -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Appointment <span id="vm_id"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2"><div class="col-4"><strong>Patient:</strong></div><div class="col-8" id="vm_patient"></div></div>
                    <div class="row mb-2"><div class="col-4"><strong>Email:</strong></div><div class="col-8" id="vm_email"></div></div>
                    <div class="row mb-2"><div class="col-4"><strong>Doctor:</strong></div><div class="col-8" id="vm_doctor"></div></div>
                    <div class="row mb-2"><div class="col-4"><strong>Time:</strong></div><div class="col-8" id="vm_time"></div></div>
                    <div class="row mb-2"><div class="col-4"><strong>Status:</strong></div><div class="col-8" id="vm_status"></div></div>
                    <div class="mt-3">
                        <strong>Purpose:</strong>
                        <div class="border rounded p-3 mt-2" id="vm_purpose"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject-reason modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="reject_id" id="reject_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Reason (optional)</label>
                        <textarea name="reject_reason" class="form-control" rows="3" placeholder="Let the patient know why, if useful internally"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden single-action forms for approve / complete / no-show -->
    <form method="POST" id="approveForm" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="approve_id" id="approve_id" value="">
    </form>
    <form method="POST" id="completeForm" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="complete_id" id="complete_id" value="">
    </form>
    <form method="POST" id="noshowForm" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="noshow_id" id="noshow_id" value="">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // The shared admin footer initializes `.lms_table_active` with
            // searching:false, which disables DataTables' filtering outright (not
            // just its search box UI) — so `.search()` on that instance is a no-op.
            // Re-init this one table with searching enabled so our own search box
            // can drive it, and hide the redundant auto-generated filter box it adds.
            const dataTable = $('.lms_table_active').DataTable({
                destroy: true,
                bLengthChange: false,
                responsive: true,
                searching: true,
            });
            $('.dataTables_filter').hide();

            const searchInput = document.querySelector('#searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    dataTable.search(this.value).draw();
                });
            }

            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('vm_id').textContent = this.dataset.id;
                    document.getElementById('vm_patient').textContent = this.dataset.patient;
                    document.getElementById('vm_email').textContent = this.dataset.email || 'N/A';
                    document.getElementById('vm_doctor').textContent = this.dataset.doctor;
                    document.getElementById('vm_time').textContent = this.dataset.time;
                    document.getElementById('vm_status').textContent = this.dataset.status;
                    document.getElementById('vm_purpose').textContent = this.dataset.purpose || '(none provided)';
                });
            });

            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!confirm('Approve this appointment? A confirmation email will be sent to the patient.')) return;
                    document.getElementById('approve_id').value = this.dataset.id;
                    document.getElementById('approveForm').submit();
                });
            });

            document.querySelectorAll('.reject-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('reject_id').value = this.dataset.id;
                    new bootstrap.Modal(document.getElementById('rejectModal')).show();
                });
            });

            document.querySelectorAll('.complete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!confirm('Mark this appointment as completed?')) return;
                    document.getElementById('complete_id').value = this.dataset.id;
                    document.getElementById('completeForm').submit();
                });
            });

            document.querySelectorAll('.noshow-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!confirm('Mark this appointment as a no-show?')) return;
                    document.getElementById('noshow_id').value = this.dataset.id;
                    document.getElementById('noshowForm').submit();
                });
            });

            dataTable.on('draw', function() {
                // re-bind delegated-style handlers for rows DataTables swapped in
                document.querySelectorAll('.view-btn:not([data-bound])').forEach(btn => {
                    btn.setAttribute('data-bound', '1');
                    btn.addEventListener('click', function() {
                        document.getElementById('vm_id').textContent = this.dataset.id;
                        document.getElementById('vm_patient').textContent = this.dataset.patient;
                        document.getElementById('vm_email').textContent = this.dataset.email || 'N/A';
                        document.getElementById('vm_doctor').textContent = this.dataset.doctor;
                        document.getElementById('vm_time').textContent = this.dataset.time;
                        document.getElementById('vm_status').textContent = this.dataset.status;
                        document.getElementById('vm_purpose').textContent = this.dataset.purpose || '(none provided)';
                    });
                });
            });
        });
    </script>
</body>
</html>
