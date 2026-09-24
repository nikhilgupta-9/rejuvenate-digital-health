<?php
/**
 * Admin → Lab & Diagnostic Bookings
 * Manage sample collections, test statuses, and upload patient diagnostic PDF reports.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/lib/Security.php';
require_once dirname(__DIR__) . '/lib/WhatsAppNotifier.php';
admin_jwt_guard();

$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');

// Handle status and report file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lab_status'])) {
    if (!isset($_POST['csrf_token']) || !Security::verifyCsrf($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid CSRF token.";
        header("Location: lab-bookings.php");
        exit();
    }

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? 'scheduled');
    $notes     = trim($_POST['notes'] ?? '');

    $reportFileRel = null;
    if (!empty($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['report_file']['tmp_name'];
        $origName = $_FILES['report_file']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $uploadDir = dirname(__DIR__) . '/uploads/lab_reports';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newFilename = 'report_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . '/' . $newFilename)) {
                $reportFileRel = 'uploads/lab_reports/' . $newFilename;
                $newStatus = 'report_uploaded'; // auto-advance if report uploaded
            }
        }
    }

    if ($bookingId > 0) {
        if ($reportFileRel) {
            $stmt = $conn->prepare("UPDATE lab_bookings SET status = ?, report_file = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('sssi', $newStatus, $reportFileRel, $notes, $bookingId);
        } else {
            $stmt = $conn->prepare("UPDATE lab_bookings SET status = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('ssi', $newStatus, $notes, $bookingId);
        }
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = "Booking status updated successfully!";

        // Send WhatsApp report ready notification
        if ($newStatus === 'report_uploaded' && $reportFileRel) {
            $bStmt = $conn->prepare("SELECT booking_uid, patient_name, patient_phone FROM lab_bookings WHERE id = ? LIMIT 1");
            $bStmt->bind_param('i', $bookingId);
            $bStmt->execute();
            $bk = $bStmt->get_result()->fetch_assoc();
            $bStmt->close();

            if ($bk && !empty($bk['patient_phone'])) {
                try {
                    $wa = new WhatsAppNotifier($conn);
                    $wa->sendEvent('report_ready', $bk['patient_phone'], [
                        'patient_name' => $bk['patient_name'],
                        'report_type'  => 'Diagnostic Lab Report',
                        'download_link'=> BASE_URL . $reportFileRel,
                    ], 'lab_booking', $bookingId);
                } catch (Throwable $e) {
                    error_log('[Lab Report Ready] WhatsApp error: ' . $e->getMessage());
                }
            }
        }
    }
    header("Location: lab-bookings.php");
    exit();
}

$where = "1=1";
if (in_array($status_filter, ['scheduled', 'sample_collected', 'processing', 'report_uploaded', 'cancelled'], true)) {
    $where .= " AND status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search !== '') {
    $q = $conn->real_escape_string($search);
    $where .= " AND (booking_uid LIKE '%$q%' OR patient_name LIKE '%$q%' OR patient_phone LIKE '%$q%' OR city LIKE '%$q%')";
}

$bookings = $conn->query("SELECT * FROM lab_bookings WHERE $where ORDER BY booking_date DESC, created_at DESC LIMIT 200");

// Counts
$c = fn($sql) => (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
$cnt_all        = $c("SELECT COUNT(*) c FROM lab_bookings");
$cnt_scheduled  = $c("SELECT COUNT(*) c FROM lab_bookings WHERE status='scheduled'");
$cnt_collected  = $c("SELECT COUNT(*) c FROM lab_bookings WHERE status='sample_collected'");
$cnt_uploaded   = $c("SELECT COUNT(*) c FROM lab_bookings WHERE status='report_uploaded'");

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Lab Bookings | Admin Panel</title>
    <?php include "links.php"; ?>
</head>
<body class="crm_body_bg">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0 fw-bold">Diagnostic Lab Bookings</h4>
                        <small class="text-muted">Manage sample collections, test schedules, and upload patient lab reports</small>
                    </div>
                    <a href="<?= BASE_URL ?>lab-tests.php" target="_blank" class="btn btn-primary btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i> Public Lab Page
                    </a>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= $success_message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Total Bookings</div><h3 class="fw-bold mb-0 text-primary"><?= $cnt_all ?></h3></div></div>
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Scheduled / Pending</div><h3 class="fw-bold mb-0 text-warning"><?= $cnt_scheduled ?></h3></div></div>
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Sample Collected</div><h3 class="fw-bold mb-0 text-info"><?= $cnt_collected ?></h3></div></div>
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Reports Uploaded</div><h3 class="fw-bold mb-0 text-success"><?= $cnt_uploaded ?></h3></div></div>
                </div>

                <!-- Table Card -->
                <div class="card border-0 shadow-sm p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="btn-group">
                            <a href="lab-bookings.php?status=all" class="btn btn-sm <?= $status_filter==='all'?'btn-primary':'btn-outline-secondary' ?>">All</a>
                            <a href="lab-bookings.php?status=scheduled" class="btn btn-sm <?= $status_filter==='scheduled'?'btn-primary':'btn-outline-secondary' ?>">Scheduled</a>
                            <a href="lab-bookings.php?status=sample_collected" class="btn btn-sm <?= $status_filter==='sample_collected'?'btn-primary':'btn-outline-secondary' ?>">Collected</a>
                            <a href="lab-bookings.php?status=report_uploaded" class="btn btn-sm <?= $status_filter==='report_uploaded'?'btn-primary':'btn-outline-secondary' ?>">Completed</a>
                        </div>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search booking / patient / phone" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking UID</th>
                                    <th>Patient</th>
                                    <th>Date & Slot</th>
                                    <th>Tests Booked</th>
                                    <th>Type & Location</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($bookings && $bookings->num_rows > 0): ?>
                                    <?php while ($b = $bookings->fetch_assoc()): 
                                        $tests = json_decode($b['tests_json'] ?? '[]', true) ?: [];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary"><?= htmlspecialchars($b['booking_uid']) ?></span>
                                            <div class="text-muted" style="font-size:.75rem;"><?= date('d M Y, h:i A', strtotime($b['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($b['patient_name']) ?></strong>
                                            <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($b['patient_phone']) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= date('d M Y', strtotime($b['booking_date'])) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($b['time_slot']) ?></div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <strong><?= count($tests) ?> test(s):</strong>
                                                <?= htmlspecialchars(implode(', ', array_map(fn($t)=>$t['test_name'], array_slice($tests, 0, 2)))) ?>
                                                <?= count($tests) > 2 ? '...' : '' ?>
                                            </div>
                                            <?php if (!empty($b['prescription_file'])): ?>
                                                <a href="<?= BASE_URL . htmlspecialchars($b['prescription_file']) ?>" target="_blank" class="small text-primary mt-1 d-inline-block">
                                                    <i class="fas fa-file-medical me-1"></i>View Uploaded Rx
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $b['collection_type']==='home_collection'?'bg-info text-dark':'bg-secondary text-white' ?> mb-1">
                                                <?= $b['collection_type']==='home_collection'?'Home Pickup':'Visit Lab' ?>
                                            </span>
                                            <div class="small text-muted text-truncate" style="max-width:180px;" title="<?= htmlspecialchars($b['address']) ?>">
                                                <?= htmlspecialchars($b['city']) ?> <?= $b['address'] ? '(' . htmlspecialchars($b['address']) . ')' : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>₹<?= number_format($b['total_amount'], 2) ?></strong>
                                            <div class="small text-muted"><?= strtoupper($b['payment_method']) ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $stClass = match($b['status']) {
                                                'scheduled'        => 'bg-warning text-dark',
                                                'sample_collected' => 'bg-info text-dark',
                                                'processing'       => 'bg-primary text-white',
                                                'report_uploaded'  => 'bg-success text-white',
                                                default            => 'bg-secondary text-white'
                                            };
                                            ?>
                                            <span class="badge <?= $stClass ?> px-2 py-1"><?= ucfirst(str_replace('_', ' ', $b['status'])) ?></span>
                                            <?php if (!empty($b['report_file'])): ?>
                                                <div><a href="<?= BASE_URL . htmlspecialchars($b['report_file']) ?>" target="_blank" class="small text-success fw-bold"><i class="fas fa-file-pdf me-1"></i>View Report</a></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openLabModal(<?= htmlspecialchars(json_encode($b)) ?>)">
                                                <i class="fas fa-edit me-1"></i>Manage
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center py-4 text-muted">No diagnostic bookings found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manage Booking Modal -->
        <div class="modal fade" id="labModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_lab_status" value="1">
                        <input type="hidden" name="booking_id" id="modalBookingId">
                        <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="modalBookingTitle">Manage Lab Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Booking Status</label>
                                <select name="status" id="modalBookingStatus" class="form-select">
                                    <option value="scheduled">Scheduled</option>
                                    <option value="sample_collected">Sample Collected</option>
                                    <option value="processing">Processing in Lab</option>
                                    <option value="report_uploaded">Report Uploaded / Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Upload Patient Diagnostic Report (PDF / Image)</label>
                                <input type="file" name="report_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Uploading a report will automatically mark the booking as Completed and notify the patient via WhatsApp.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Internal Technician Notes</label>
                                <textarea name="notes" id="modalBookingNotes" class="form-control" rows="2" placeholder="e.g. Sample collected by Phlebotomist Amit"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
        <script>
            function openLabModal(bk) {
                document.getElementById('modalBookingId').value = bk.id;
                document.getElementById('modalBookingTitle').innerText = 'Manage Booking #' + bk.booking_uid;
                document.getElementById('modalBookingStatus').value = bk.status;
                document.getElementById('modalBookingNotes').value = bk.notes || '';
                new bootstrap.Modal(document.getElementById('labModal')).show();
            }
        </script>
</body>
</html>
