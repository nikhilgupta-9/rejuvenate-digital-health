<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sidebar_active = 'lab';

$bookings = $conn->query("
    SELECT * FROM lab_bookings 
    WHERE user_id = {$user_id} 
    ORDER BY booking_date DESC, created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Lab Bookings | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .lab-booking-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            transition: all .2s ease;
        }
        .lab-booking-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 14px rgba(12,116,197,.1);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-scheduled { background: #fef3c7; color: #92400e; }
        .badge-sample_collected { background: #e0f2fe; color: #0369a1; }
        .badge-processing { background: #eef2ff; color: #4338ca; }
        .badge-report_uploaded { background: #dcfce7; color: #166534; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="patient-body">
    <?php include "sidebar.php"; ?>

    <main class="patient-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="ap-h mb-1"><i class="fa fa-flask me-2 text-primary-theme"></i>My Lab Test Bookings</h1>
                <div class="ap-sub">Diagnostic investigations, pathology tests, and ABDM M2/M3 health reports</div>
            </div>
            <a href="<?= BASE_URL ?>lab-tests.php" class="btn btn-primary btn-sm">
                <i class="fa fa-plus me-1"></i> Book New Diagnostic Test
            </a>
        </div>

        <?php if ($bookings && $bookings->num_rows > 0): ?>
            <?php while ($bk = $bookings->fetch_assoc()): 
                $tests = json_decode($bk['tests_json'] ?? '[]', true) ?: [];
                $statusKey = $bk['status'] ?? 'scheduled';
                $badgeClass = 'badge-' . $statusKey;
                $statusLabel = match($statusKey) {
                    'scheduled'        => 'Scheduled',
                    'sample_collected' => 'Sample Collected',
                    'processing'       => 'Processing in Lab',
                    'report_uploaded'  => 'Report Ready',
                    'cancelled'        => 'Cancelled',
                    default            => ucfirst(str_replace('_', ' ', $statusKey))
                };
            ?>
            <div class="lab-booking-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 border-bottom pb-2">
                    <div>
                        <span class="fw-bold fs-6 text-primary-theme">
                            <i class="fa fa-hashtag me-1"></i><?= htmlspecialchars($bk['booking_uid']) ?>
                        </span>
                        <span class="text-muted small ms-2">
                            <i class="fa fa-calendar-check-o me-1"></i><?= date('d M Y', strtotime($bk['booking_date'])) ?>
                            <?php if (!empty($bk['time_slot'])): ?>
                                • <?= htmlspecialchars($bk['time_slot']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="status-badge <?= $badgeClass ?>">
                        <i class="fa fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i><?= $statusLabel ?>
                    </span>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="small text-muted mb-1 font-weight-bold">Patient & Collection Mode:</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($bk['patient_name']) ?></div>
                        <div class="small mt-1 text-primary-theme">
                            <i class="fa <?= $bk['collection_type'] === 'home_collection' ? 'fa-home' : 'fa-hospital-o' ?> me-1"></i>
                            <?= $bk['collection_type'] === 'home_collection' ? 'Home Sample Collection' : 'Visit Diagnostic Center' ?>
                        </div>
                        <?php if (!empty($bk['address'])): ?>
                            <div class="small text-muted mt-1">
                                <i class="fa fa-map-marker me-1"></i><?= htmlspecialchars($bk['address']) ?>, <?= htmlspecialchars($bk['city'] ?? '') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <div class="small text-muted mb-1 font-weight-bold">Investigations Booked:</div>
                        <?php if (!empty($tests)): ?>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($tests as $t): ?>
                                    <li class="mb-1">
                                        <i class="fa fa-check text-accent-theme me-1"></i>
                                        <strong><?= htmlspecialchars($t['test_name'] ?? $t['name'] ?? 'Diagnostic Test') ?></strong>
                                        <?php if (!empty($t['category'])): ?>
                                            <span class="text-muted">(<?= htmlspecialchars($t['category']) ?>)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="small text-muted">Standard Investigation Package</span>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3 text-md-end">
                        <div class="small text-muted mb-1 font-weight-bold">Total Fee:</div>
                        <div class="fs-5 fw-bold text-dark">₹<?= number_format($bk['total_amount'], 2) ?></div>
                        <div class="small text-muted text-uppercase"><?= htmlspecialchars($bk['payment_method']) ?> • <?= ucfirst($bk['payment_status'] ?? 'pending') ?></div>
                        
                        <?php if (!empty($bk['report_file'])): ?>
                            <div class="mt-3">
                                <a href="<?= BASE_URL . htmlspecialchars($bk['report_file']) ?>" target="_blank" class="btn btn-sm btn-success w-100">
                                    <i class="fa fa-download me-1"></i> Download PDF Report
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted mt-2">
                                <i class="fa fa-clock-o me-1"></i>Report pending analysis
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                <div style="width:70px;height:70px;background:#eaf4fd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--primary);font-size:1.8rem;">
                    <i class="fa fa-flask"></i>
                </div>
                <h5 class="fw-bold mb-2">No Lab Test Bookings Yet</h5>
                <p class="text-muted small mb-4 mx-auto" style="max-width:440px;">
                    You have not booked any pathology investigations or health packages yet. Schedule blood tests with doorstep sample collection easily.
                </p>
                <div>
                    <a href="<?= BASE_URL ?>lab-tests.php" class="btn btn-primary px-4">
                        <i class="fa fa-plus me-1"></i> Book Diagnostic Test Now
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include("inc/scripts.php"); ?>
</body>
</html>
