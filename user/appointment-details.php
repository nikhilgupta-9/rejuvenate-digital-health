<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$appt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appt_id) {
    header("Location: my-doctor-appointments.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT
        a.*,
        d.name           AS doctor_name,
        d.specialization,
        d.degrees,
        d.consultation_fee,
        d.profile_image,
        d.experience_years,
        d.rating,
        d.phone          AS doctor_phone,
        d.email          AS doctor_email,
        d.languages,
        d.gender         AS doctor_gender,
        TIME_FORMAT(a.appointment_time, '%h:%i %p')      AS fmt_time,
        DATE_FORMAT(a.appointment_date, '%d %M %Y')      AS fmt_date,
        DATE_FORMAT(a.created_at,       '%d %M %Y %H:%i') AS fmt_booked
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.id = ? AND a.user_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $appt_id, $user_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    header("Location: my-doctor-appointments.php");
    exit();
}

$is_upcoming = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']) > time();
$can_cancel  = in_array($appt['status'], ['pending', 'confirmed']) && $is_upcoming;

/* ── Handle cancel ── */
$msg_success = '';
$msg_error   = '';

if (isset($_GET['cancel']) && $can_cancel) {
    $cs = $conn->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND user_id=?");
    $cs->bind_param('ii', $appt_id, $user_id);
    if ($cs->execute()) {
        header("Location: appointment-details.php?id={$appt_id}&cancelled=1");
        exit();
    } else {
        $msg_error = "Could not cancel the appointment. Please try again.";
    }
    $cs->close();
}

if (isset($_GET['cancelled'])) $msg_success = "Appointment cancelled successfully.";

$status_colors = [
    'pending'   => ['bg' => '#fff3cd', 'text' => '#856404', 'icon' => 'fa-clock'],
    'confirmed' => ['bg' => '#d4edda', 'text' => '#155724', 'icon' => 'fa-check-circle'],
    'completed' => ['bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => 'fa-check-double'],
    'cancelled' => ['bg' => '#f8d7da', 'text' => '#721c24', 'icon' => 'fa-ban'],
];
$sc = $status_colors[$appt['status']] ?? ['bg' => '#e9ecef', 'text' => '#495057', 'icon' => 'fa-question'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Details | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        :root {
            --rdh-blue: #2c5aa0;
            --rdh-teal: #02c9b8;
        }
        /* ── Layout ── */
        .detail-wrap   { background: #f4f7fb; min-height: 60vh; padding: 30px 0 50px; }
        .detail-card   {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,.07);
            overflow: hidden;
            margin-bottom: 22px;
        }
        .detail-card-header {
            background: linear-gradient(135deg, var(--rdh-blue), #4a7bc8);
            color: #fff;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-card-header h5 { margin: 0; font-weight: 600; font-size: 1rem; }
        .detail-card-body  { padding: 22px 24px; }

        /* ── Status banner ── */
        .status-banner {
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
        }
        .status-banner .sb-icon { font-size: 2rem; }
        .status-banner .sb-label { font-size: .78rem; text-transform: uppercase; font-weight: 700; opacity: .75; }
        .status-banner .sb-value { font-size: 1.25rem; font-weight: 700; }

        /* ── Doctor card ── */
        .doc-card {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .doc-avatar {
            width: 76px; height: 76px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--rdh-blue);
            flex-shrink: 0;
        }
        .doc-avatar-placeholder {
            width: 76px; height: 76px;
            border-radius: 50%;
            background: #e8f0fb;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: var(--rdh-blue);
            border: 3px solid var(--rdh-blue);
            flex-shrink: 0;
        }
        .doc-name  { font-size: 1.1rem; font-weight: 700; color: #2c2c2c; margin-bottom: 3px; }
        .doc-spec  { font-size: .88rem; color: var(--rdh-blue); font-weight: 600; margin-bottom: 2px; }
        .doc-deg   { font-size: .82rem; color: #6c757d; }
        .star-rating { color: #f4b400; font-size: .85rem; }

        /* ── Info rows ── */
        .info-row {
            display: flex;
            align-items: flex-start;
            padding: 11px 0;
            border-bottom: 1px solid #f0f0f0;
            gap: 14px;
        }
        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: #e8f0fb;
            color: var(--rdh-blue);
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem;
            flex-shrink: 0;
        }
        .info-label { font-size: .75rem; color: #9a9a9a; text-transform: uppercase; font-weight: 600; }
        .info-value { font-size: .92rem; color: #333; font-weight: 500; margin-top: 1px; }

        /* ── Action buttons ── */
        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 18px 24px;
            background: #f8f9fb;
            border-top: 1px solid #eee;
        }
        .btn-cancel-appt {
            background: #dc3545; color: #fff; border: none;
            padding: 10px 22px; border-radius: 8px; font-weight: 600;
            text-decoration: none; font-size: .9rem;
            transition: opacity .2s;
        }
        .btn-cancel-appt:hover { opacity: .85; color: #fff; text-decoration: none; }
        .btn-back {
            background: #f0f0f0; color: #495057; border: none;
            padding: 10px 22px; border-radius: 8px; font-weight: 600;
            text-decoration: none; font-size: .9rem;
        }
        .btn-back:hover { background: #e2e2e2; color: #333; text-decoration: none; }
        .btn-join {
            background: linear-gradient(135deg, var(--rdh-teal), #0aa898);
            color: #fff; border: none;
            padding: 10px 22px; border-radius: 8px; font-weight: 600;
            text-decoration: none; font-size: .9rem;
        }
        .btn-join:hover { opacity: .88; color: #fff; text-decoration: none; }

        /* ── Mobile ── */
        @media (max-width: 576px) {
            .detail-card-body { padding: 16px; }
            .detail-card-header { padding: 14px 16px; }
            .doc-card { flex-direction: column; align-items: flex-start; }
            .action-bar { padding: 14px 16px; }
            .status-banner { flex-direction: column; align-items: flex-start; gap: 6px; }
        }
    </style>
</head>
<body>
<?php include("../header.php") ?>

<div class="detail-wrap">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb" style="background:none;padding:0;font-size:.85rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="my-doctor-appointments.php">My Appointments</a></li>
                <li class="breadcrumb-item active">Appointment #<?= $appt_id ?></li>
            </ol>
        </nav>

        <!-- Alerts -->
        <?php if ($msg_success): ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="fa fa-check-circle me-2"></i><?= $msg_success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="fa fa-exclamation-circle me-2"></i><?= $msg_error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left column -->
            <div class="col-lg-8">

                <!-- Status banner -->
                <div class="status-banner" style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>;">
                    <div class="sb-icon"><i class="fa <?= $sc['icon'] ?>"></i></div>
                    <div>
                        <div class="sb-label">Appointment Status</div>
                        <div class="sb-value"><?= ucfirst($appt['status']) ?></div>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="sb-label">Appointment ID</div>
                        <div class="sb-value">#<?= $appt_id ?></div>
                    </div>
                </div>

                <!-- Doctor info -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fa fa-user-md"></i>
                        <h5>Doctor Information</h5>
                    </div>
                    <div class="detail-card-body">
                        <div class="doc-card">
                            <?php if (!empty($appt['profile_image'])): ?>
                                <img src="<?= BASE_URL . 'admin/' . htmlspecialchars($appt['profile_image']) ?>"
                                     alt="Dr. <?= htmlspecialchars($appt['doctor_name']) ?>"
                                     class="doc-avatar">
                            <?php else: ?>
                                <div class="doc-avatar-placeholder"><i class="fa fa-user-md"></i></div>
                            <?php endif; ?>
                            <div>
                                <div class="doc-name">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
                                <div class="doc-spec"><?= htmlspecialchars($appt['specialization']) ?></div>
                                <div class="doc-deg"><?= htmlspecialchars($appt['degrees']) ?></div>
                                <?php if (!empty($appt['rating'])): ?>
                                    <div class="star-rating mt-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa fa-star<?= $i <= round($appt['rating']) ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-1" style="font-size:.8rem;"><?= number_format($appt['rating'], 1) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <?php if (!empty($appt['experience_years'])): ?>
                            <div class="col-6 col-md-4">
                                <div class="info-label">Experience</div>
                                <div class="info-value"><?= (int)$appt['experience_years'] ?> yrs</div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($appt['languages'])): ?>
                            <div class="col-6 col-md-4">
                                <div class="info-label">Languages</div>
                                <div class="info-value"><?= htmlspecialchars($appt['languages']) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($appt['doctor_phone'])): ?>
                            <div class="col-6 col-md-4">
                                <div class="info-label">Contact</div>
                                <div class="info-value"><?= htmlspecialchars($appt['doctor_phone']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Appointment details -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fa fa-calendar-check"></i>
                        <h5>Appointment Details</h5>
                    </div>
                    <div class="detail-card-body">
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-calendar"></i></div>
                            <div>
                                <div class="info-label">Date</div>
                                <div class="info-value"><?= $appt['fmt_date'] ?></div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-clock"></i></div>
                            <div>
                                <div class="info-label">Time</div>
                                <div class="info-value"><?= $appt['fmt_time'] ?></div>
                            </div>
                        </div>
                        <?php if (!empty($appt['appointment_type'])): ?>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-stethoscope"></i></div>
                            <div>
                                <div class="info-label">Appointment Type</div>
                                <div class="info-value"><?= htmlspecialchars($appt['appointment_type']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($appt['visit_person'])): ?>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-user"></i></div>
                            <div>
                                <div class="info-label">Visiting For</div>
                                <div class="info-value"><?= htmlspecialchars($appt['visit_person']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($appt['purpose'])): ?>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-notes-medical"></i></div>
                            <div>
                                <div class="info-label">Purpose / Complaint</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($appt['purpose'])) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($appt['notes'])): ?>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-sticky-note"></i></div>
                            <div>
                                <div class="info-label">Notes</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($appt['notes'])) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-clock-o"></i></div>
                            <div>
                                <div class="info-label">Booked On</div>
                                <div class="info-value"><?= $appt['fmt_booked'] ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action bar -->
                    <div class="action-bar">
                        <a href="my-doctor-appointments.php" class="btn-back">
                            <i class="fa fa-arrow-left me-1"></i> Back
                        </a>

                        <?php if ($appt['status'] === 'confirmed' && $is_upcoming): ?>
                            <a href="#" class="btn-join">
                                <i class="fa fa-video me-1"></i> Join Consultation
                            </a>
                        <?php endif; ?>

                        <?php if ($can_cancel): ?>
                            <a href="appointment-details.php?id=<?= $appt_id ?>&cancel=1"
                               class="btn-cancel-appt"
                               onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                <i class="fa fa-ban me-1"></i> Cancel Appointment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right column -->
            <div class="col-lg-4">

                <!-- Fee summary -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fa fa-rupee-sign"></i>
                        <h5>Fee Summary</h5>
                    </div>
                    <div class="detail-card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Consultation Fee</span>
                            <strong>₹<?= number_format($appt['consultation_fee']) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Total</span>
                            <span class="fw-bold" style="color:var(--rdh-blue);font-size:1.1rem;">
                                ₹<?= number_format($appt['consultation_fee']) ?>
                            </span>
                        </div>
                        <?php if ($appt['status'] === 'completed'): ?>
                            <div class="mt-3 text-center">
                                <span style="background:#d4edda;color:#155724;padding:5px 14px;border-radius:20px;font-size:.82rem;font-weight:600;">
                                    <i class="fa fa-check me-1"></i> Payment Received
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick info -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fa fa-info-circle"></i>
                        <h5>Quick Info</h5>
                    </div>
                    <div class="detail-card-body" style="padding-top:14px;">
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-hashtag"></i></div>
                            <div>
                                <div class="info-label">Appointment ID</div>
                                <div class="info-value">#<?= $appt_id ?></div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-icon" style="background:#e8f5f4;color:var(--rdh-teal);"><i class="fa fa-heartbeat"></i></div>
                            <div>
                                <div class="info-label">Appointment Type</div>
                                <div class="info-value"><?= ucfirst($appt['appointment_type'] ?? 'Consultation') ?></div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-icon"><i class="fa fa-user-md"></i></div>
                            <div>
                                <div class="info-label">Specialization</div>
                                <div class="info-value"><?= htmlspecialchars($appt['specialization']) ?></div>
                            </div>
                        </div>
                        <?php if ($is_upcoming && $appt['status'] !== 'cancelled'): ?>
                        <div class="mt-3 p-3 rounded" style="background:#e8f0fb;">
                            <div class="info-label mb-1" style="color:var(--rdh-blue);">Time Until Appointment</div>
                            <div id="countdown" style="font-weight:700;color:var(--rdh-blue);font-size:1rem;"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Help note -->
                <div class="detail-card">
                    <div class="detail-card-body" style="font-size:.85rem;color:#6c757d;">
                        <i class="fa fa-info-circle me-2" style="color:var(--rdh-blue);"></i>
                        For queries or rescheduling requests, contact your doctor's clinic directly or use our
                        <a href="<?= BASE_URL ?>contact-us.php">support page</a>.
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include("../footer.php") ?>

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Countdown timer
    const apptDatetime = new Date('<?= $appt['appointment_date'] ?> <?= $appt['appointment_time'] ?>');
    const el = document.getElementById('countdown');
    if (el) {
        function updateCountdown() {
            const now  = new Date();
            const diff = apptDatetime - now;
            if (diff <= 0) { el.textContent = 'Appointment time has passed'; return; }
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000)  / 60000);
            el.textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
        }
        updateCountdown();
        setInterval(updateCountdown, 60000);
    }

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            try { new bootstrap.Alert(a).close(); } catch(e) {}
        });
    }, 5000);
</script>
</body>
</html>
