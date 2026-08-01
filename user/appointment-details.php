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
$can_join    = $appt['status'] === 'approved' && $appt['appointment_type'] === 'online' && $appt['meeting_status'] !== 'cancelled';
$can_join_pending = $appt['status'] === 'pending' && $appt['appointment_type'] === 'online' && $appt['meeting_status'] !== 'cancelled';
// appointments.status is ENUM('pending','approved','rejected','completed','no_show') —
// there's no 'confirmed'/'cancelled' value, so patient-side cancellation reuses 'rejected'
// (same value the doctor-side cancel action already writes).
$can_cancel  = in_array($appt['status'], ['pending', 'approved'], true) && $is_upcoming;

/* ── Handle cancel ── */
$msg_success = '';
$msg_error   = '';

if (isset($_GET['cancel']) && $can_cancel) {
    $cs = $conn->prepare("UPDATE appointments SET status='rejected' WHERE id=? AND user_id=?");
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

$status_icons = [
    'pending'   => 'fa-clock',
    'approved'  => 'fa-check-circle',
    'completed' => 'fa-check-double',
    'rejected'  => 'fa-ban',
    'no_show'   => 'fa-user-times',
];
$status_icon = $status_icons[$appt['status']] ?? 'fa-question';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Appointment Details | REJUVENATE Digital Health</title>
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
        /* Page-specific styles only — colors come from the shared theme
           (user/assets/style.css, --primary #0C74C5 / --accent #02c9b8). */
        .doc-card { display: flex; align-items: center; gap: 18px; }
        .doc-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            flex-shrink: 0;
        }
        .doc-avatar-placeholder {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: #eaf4fd;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: var(--primary);
            flex-shrink: 0;
        }
        .doc-name { font-size: 1.05rem; font-weight: 700; color: #1f2937; margin-bottom: 2px; }
        .doc-spec { font-size: .86rem; color: var(--primary); font-weight: 600; margin-bottom: 2px; }
        .doc-deg  { font-size: .8rem; color: #6b7280; }
        .star-rating { color: #f4b400; font-size: .82rem; }

        .action-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; padding-top: 18px; border-top: 1px solid #f3f4f6; }

        .countdown-box { background: #eaf4fd; border-radius: 10px; padding: 12px 16px; margin-top: 14px; }
        .countdown-box .lbl { font-size: .72rem; color: var(--primary); font-weight: 600; margin-bottom: 2px; }
        .countdown-box .val { font-weight: 700; color: var(--primary); font-size: 1rem; }

        @media (max-width: 576px) {
            .doc-card { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>

<body>
    <?php $sidebar_active = 'appointments'; include("sidebar.php"); ?>
    <main class="patient-content">

        <a href="my-doctor-appointments.php" class="btn btn-sm btn-outline-secondary mb-3">
            <i class="fa fa-arrow-left me-1"></i> Back to My Appointments
        </a>

        <?php if ($msg_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i><?= $msg_success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-2"></i><?= $msg_error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left column -->
            <div class="col-lg-8">

                <!-- Doctor info -->
                <div class="profile-card shadow">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">Appointment #<?= $appt_id ?></h4>
                        <span class="status-badge status-<?= $appt['status'] ?>">
                            <i class="fa <?= $status_icon ?> me-1"></i><?= ucfirst(str_replace('_', ' ', $appt['status'])) ?>
                        </span>
                    </div>

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
                                    <span class="text-muted ms-1" style="font-size:.78rem;"><?= number_format($appt['rating'], 1) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <?php if (!empty($appt['experience_years'])): ?>
                            <div class="col-6 col-md-4">
                                <div class="info-label">Experience</div>
                                <div class="info-val"><?= (int)$appt['experience_years'] ?> yrs</div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($appt['languages'])): ?>
                            <div class="col-6 col-md-4">
                                <div class="info-label">Languages</div>
                                <div class="info-val"><?= htmlspecialchars($appt['languages']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($appt['doctor_phone'])): ?>
                            <div class="col-6 col-md-4">
                                <div class="info-label">Contact</div>
                                <div class="info-val"><?= htmlspecialchars($appt['doctor_phone']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Appointment details -->
                <div class="profile-card shadow">
                    <div class="form-section-title"><i class="fa fa-calendar-check me-2"></i>Appointment Details</div>

                    <div class="info-row">
                        <div class="info-label">Date</div>
                        <div class="info-val"><?= $appt['fmt_date'] ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Time</div>
                        <div class="info-val"><?= $appt['fmt_time'] ?></div>
                    </div>
                    <?php if (!empty($appt['appointment_type'])): ?>
                        <div class="info-row">
                            <div class="info-label">Appointment Type</div>
                            <div class="info-val"><?= $appt['appointment_type'] === 'online' ? 'Online Consultation' : 'In-Clinic Visit' ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($appt['visit_person'])): ?>
                        <div class="info-row">
                            <div class="info-label">Visiting For</div>
                            <div class="info-val"><?= htmlspecialchars($appt['visit_person']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($appt['purpose'])): ?>
                        <div class="info-row">
                            <div class="info-label">Purpose / Complaint</div>
                            <div class="info-val"><?= nl2br(htmlspecialchars($appt['purpose'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($appt['notes'])): ?>
                        <div class="info-row">
                            <div class="info-label">Notes</div>
                            <div class="info-val"><?= nl2br(htmlspecialchars($appt['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">Booked On</div>
                        <div class="info-val"><?= $appt['fmt_booked'] ?></div>
                    </div>

                    <?php if ($can_join_pending): ?>
                        <div class="alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0" style="font-size:.85rem;">
                            <i class="fa fa-video"></i>
                            <div>This is an online consultation. The video call link will unlock here once your doctor approves the appointment.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Action bar -->
                    <div class="action-bar">
                        <?php if ($can_join): ?>
                            <a href="<?= BASE_URL ?>telemedicine/join.php?appointment_id=<?= $appt_id ?>" class="btn btn-success" target="_blank">
                                <i class="fa fa-video me-1"></i> Join Video Call
                            </a>
                        <?php endif; ?>

                        <?php if ($can_cancel): ?>
                            <a href="appointment-details.php?id=<?= $appt_id ?>&cancel=1"
                                class="btn btn-outline-danger"
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
                <div class="profile-card shadow">
                    <div class="form-section-title"><i class="fa fa-rupee-sign me-2"></i>Fee Summary</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Consultation Fee</span>
                        <strong>₹<?= number_format($appt['consultation_fee']) ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold text-primary-theme" style="font-size:1.1rem;">
                            ₹<?= number_format($appt['consultation_fee']) ?>
                        </span>
                    </div>
                    <?php if ($appt['status'] === 'completed'): ?>
                        <div class="mt-3 text-center">
                            <span class="status-badge status-approved">
                                <i class="fa fa-check me-1"></i> Payment Received
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick info -->
                <div class="profile-card shadow">
                    <div class="form-section-title"><i class="fa fa-info-circle me-2"></i>Quick Info</div>
                    <div class="info-row">
                        <div class="info-label">Appointment ID</div>
                        <div class="info-val">#<?= $appt_id ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Appointment Type</div>
                        <div class="info-val"><?= $appt['appointment_type'] === 'online' ? 'Online Consultation' : 'In-Clinic Visit' ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Specialization</div>
                        <div class="info-val"><?= htmlspecialchars($appt['specialization']) ?></div>
                    </div>

                    <?php if ($is_upcoming && !in_array($appt['status'], ['rejected', 'completed', 'no_show'], true)): ?>
                        <div class="countdown-box">
                            <div class="lbl">Time Until Appointment</div>
                            <div class="val" id="countdown"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Help note -->
                <div class="profile-card shadow" style="font-size:.85rem;color:#6b7280;">
                    <i class="fa fa-info-circle me-2 text-primary-theme"></i>
                    For queries or rescheduling requests, contact your doctor's clinic directly or use our
                    <a href="<?= BASE_URL ?>contact-us.php">support page</a>.
                </div>

            </div>
        </div>
    </main>
    <?php include("inc/scripts.php") ?>

    <script>
        // Countdown timer
        const apptDatetime = new Date('<?= $appt['appointment_date'] ?> <?= $appt['appointment_time'] ?>');
        const el = document.getElementById('countdown');
        if (el) {
            function updateCountdown() {
                const now = new Date();
                const diff = apptDatetime - now;
                if (diff <= 0) { el.textContent = 'Appointment time has passed'; return; }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                el.textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
            }
            updateCountdown();
            setInterval(updateCountdown, 60000);
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => {
                try { new bootstrap.Alert(a).close(); } catch (e) {}
            });
        }, 5000);
    </script>
</body>

</html>
