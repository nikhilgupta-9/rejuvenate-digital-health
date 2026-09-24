<?php

/**
 * Doctor Panel — Sidebar + Topbar
 * Set $sidebar_active before including (e.g. 'dashboard', 'patients', 'appointments')
 * Requires $doctor_id to be set (from JWT guard).
 */
$sidebar_active = $sidebar_active ?? '';
$doctor_id = $doctor_id ?? (int)($_SESSION['doctor_id'] ?? 0);

// Fetch doctor info for the sidebar header
$_d_sql  = "SELECT name, email, profile_image, specialization, hpr_id, hpr_verified, abha_id FROM doctors WHERE id = ?";
$_d_stmt = $conn->prepare($_d_sql);
$_d_stmt->bind_param('i', $doctor_id);
$_d_stmt->execute();
$_d = $_d_stmt->get_result()->fetch_assoc();

$_d_name    = htmlspecialchars($_d['name'] ?? ($doctor_name ?? 'Doctor'));
$_d_email   = htmlspecialchars($_d['email'] ?? '');
$_d_spec    = htmlspecialchars($_d['specialization'] ?? 'Doctor');
$_d_hpr_id  = htmlspecialchars($_d['hpr_id'] ?? '');
$_d_hpr_ver = (bool)($_d['hpr_verified'] ?? false);
$_d_pic     = !empty($_d['profile_image']) ? BASE_URL . htmlspecialchars($_d['profile_image']) : null;

$_page_titles = [
    'dashboard'        => 'Clinical Dashboard',
    'patient-form'     => 'Digital Prescription (OPD Note)',
    'opd-slips'        => 'Generate OPD Slip',
    'appointments'     => 'Appointments & Consultations',
    'schedule'         => 'Manage Schedule & Timings',
    'reports'          => 'Appointments Calendar',
    'patients'         => 'Patients Registry',
    'add-patient'      => 'Onboard Patient (ABHA M1)',
    'documents'        => 'Diagnostic & Lab Reports',
    'pending-uploads'  => 'ABHA Compliance Queue',
    'analysis-report'  => 'ABDM Analytics & Reports',
    'school-students'  => 'School Students Health',
    'contact'          => 'Doctor Profile & HPR ID',
    'earnings'         => 'Earnings & Settlements',
    'billing'          => 'Subscription & Billing History',
    'settings'         => 'Account Settings',
    'about'            => 'About & Guidelines',
    'delete-account'   => 'Delete Account',
];
$_page_title = $_page_titles[$sidebar_active] ?? 'Doctor Portal';

$_menu = [
    'dashboard'        => ['icon' => 'fa fa-th-large',          'label' => 'Dashboard',                'url' => BASE_URL . 'doctor/doctor-dashboard.php',      'section' => 'Main'],
    'patient-form'     => ['icon' => 'fa fa-edit',   'label' => 'OPD Consultation (Rx)',   'url' => BASE_URL . 'doctor/patient-form.php',          'section' => 'Clinical & OPD'],
    'opd-slips'        => ['icon' => 'fa-solid fa-file-lines',       'label' => 'Generate OPD Slip',        'url' => BASE_URL . 'doctor/select-opd-patient.php',    'section' => 'Clinical & OPD'],
    'appointments'     => ['icon' => 'fa fa-book-medical',      'label' => 'Appointments',             'url' => BASE_URL . 'doctor/appointments.php',          'section' => 'Clinical & OPD'],
    'schedule'         => ['icon' => 'fa-solid fa-clock',           'label' => 'Manage Schedule',          'url' => BASE_URL . 'doctor/manage-schedule.php',       'section' => 'Clinical & OPD'],
    'reports'          => ['icon' => 'fa fa-calendar',         'label' => 'Calendar',                 'url' => BASE_URL . 'doctor/appointments-calendar.php', 'section' => 'Clinical & OPD'],

    'patients'         => ['icon' => 'fa fa-heartbeat',         'label' => 'Patients Registry',        'url' => BASE_URL . 'doctor/my-patients.php',           'section' => 'ABDM & Patients'],
    'add-patient'      => ['icon' => 'fa fa-user-plus',         'label' => 'Onboard Patient (M1)',     'url' => BASE_URL . 'doctor/add-patient.php',           'section' => 'ABDM & Patients'],
    'documents'        => ['icon' => 'fa fa-folder-open',       'label' => 'Lab & Diagnostic Reports', 'url' => BASE_URL . 'doctor/patient-documents.php',    'section' => 'ABDM & Patients'],
    'pending-uploads'  => ['icon' => 'fa fa-cloud-upload',      'label' => 'ABHA Compliance Queue',    'url' => BASE_URL . 'doctor/pending-uploads.php',       'section' => 'ABDM & Patients'],
    'analysis-report'  => ['icon' => 'fa fa-chart-line',        'label' => 'Analysis Report',          'url' => BASE_URL . 'doctor/analysis-report.php',       'section' => 'ABDM & Patients'],

    'school-students'  => ['icon' => 'fa fa-graduation-cap',   'label' => 'School Students',          'url' => BASE_URL . 'doctor/school-students.php',       'section' => 'School Health'],

    'contact'          => ['icon' => 'fa fa-user-md',           'label' => 'Profile & HPR ID',         'url' => BASE_URL . 'doctor/my-contact.php',            'section' => 'Account & HPR'],
    'earnings'         => ['icon' => 'fa fa-inr',               'label' => 'Earnings & Bank',          'url' => BASE_URL . 'doctor/earnings.php',              'section' => 'Account & HPR'],
    'billing'          => ['icon' => 'fa fa-credit-card',       'label' => 'Payment History',          'url' => BASE_URL . 'doctor/payment-history.php',       'section' => 'Account & HPR'],
    'settings'         => ['icon' => 'fa fa-cog',               'label' => 'Settings',                 'url' => BASE_URL . 'doctor/account-settings.php',      'section' => 'Account & HPR'],
    'about'            => ['icon' => 'fa fa-info-circle',       'label' => 'About Us',                 'url' => BASE_URL . 'doctor/doctor-about.php',          'section' => 'Account & HPR'],
    'delete-account'   => ['icon' => 'fa fa-trash',             'label' => 'Delete Account',           'url' => BASE_URL . 'doctor/delete-account.php',        'section' => 'Account & HPR'],
];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/doctor.css">

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="doctorSidebarOverlay"></div>

<aside class="doctor-sidebar" id="doctorSidebar">

    <!-- Brand / Profile Header -->
    <div class="sidebar-brand">
        <?php if ($_d_pic): ?>
            <img src="<?= $_d_pic ?>" alt=""
                style="width:38px;height:38px;border-radius:8px;object-fit:cover;margin-bottom:8px;border:2px solid rgba(255,255,255,.3);"
                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="sidebar-logo" style="display:none;"><i class="fa fa-user"></i></div>
        <?php else: ?>
            <div class="sidebar-logo"><i class="fa fa-user"></i></div>
        <?php endif; ?>
        <div class="s-name">Dr. <?= $_d_name ?></div>
        <div class="s-sub"><?= $_d_spec ?></div>
        <?php if ($_d_hpr_id): ?>
            <div style="margin-top:5px;">
                <span style="font-size:.65rem;color:rgba(255,255,255,.5);">HPR</span>
                <span style="font-size:.7rem;color:rgba(255,255,255,.85);margin-left:4px;"><?= $_d_hpr_id ?>@hpr.abdm</span>
                <?php if ($_d_hpr_ver): ?>
                    <span style="background:#02c9b8;border-radius:10px;padding:1px 6px;font-size:.6rem;font-weight:700;color:#fff;margin-left:3px;">
                        <i class="fa fa-check"></i> Verified
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="margin-top:5px;">
                <a href="<?= BASE_URL ?>doctor/my-contact.php"
                    style="font-size:.68rem;color:rgba(255,255,255,.55);text-decoration:underline;">+ Add HPR ID</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <?php
        $prev_section = '';
        foreach ($_menu as $key => $item):
            if ($item['section'] !== $prev_section):
                echo '<div class="nav-label">' . htmlspecialchars($item['section']) . '</div>';
                $prev_section = $item['section'];
            endif;
            $is_active = ($sidebar_active === $key);
            $extra_style = ($key === 'delete-account') ? ' style="color:rgba(255,120,120,.85);"' : '';
        ?>
            <a href="<?= $item['url'] ?>" <?= $is_active ? ' class="active"' : '' ?><?= $extra_style ?>>
                <span class="nav-icon"><i class="<?= $item['icon'] ?>"></i></span>
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-label">Session</div>
        <a href="<?= BASE_URL ?>doctor/doctor-logout.php">
            <span class="nav-icon"><i class="fa fa-sign-out"></i></span>
            Logout
        </a>
    </nav>

    <div class="sidebar-footer">
        <i class="fa fa-user-circle" style="margin-right:5px;"></i><?= $_d_email ?>
    </div>
</aside>

<!-- Top Bar -->
<div class="doctor-topbar">
    <div style="display:flex;align-items:center;">
        <button class="sidebar-toggler" id="doctorSidebarToggle">
            <i class="fa fa-bars"></i>
        </button>
        <div>
            <div style="font-size:.95rem;font-weight:600;color:#1f2937;"><?= htmlspecialchars($_page_title) ?></div>
            <div style="font-size:.72rem;color:#9ca3af;"><?= date('l, d M Y') ?></div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        <a href="<?= BASE_URL ?>doctor/patient-form.php" class="btn btn-sm btn-primary d-none d-sm-inline-flex align-items-center" style="background:#0C74C5;border-color:#0C74C5;gap:5px;font-size:.78rem;font-weight:600;">
            <i class="fa fa-pencil-square-o"></i> OPD (Rx)
        </a>
        <a href="<?= BASE_URL ?>doctor/add-patient.php" class="btn btn-sm btn-outline-primary d-none d-md-inline-flex align-items-center" style="gap:5px;font-size:.78rem;font-weight:600;">
            <i class="fa fa-user-plus"></i> New ABHA (M1)
        </a>
        <div style="text-align:right;display:none;" id="doctorTopbarName">
            <span style="font-weight:600;font-size:.82rem;display:block;">Dr. <?= $_d_name ?></span>
            <span style="font-size:.7rem;color:#9ca3af;"><?= $_d_spec ?></span>
        </div>
        <div class="avatar-circle" style="width:34px;height:34px;font-size:.85rem;flex-shrink:0;overflow:hidden;">
            <?php if ($_d_pic): ?>
                <img src="<?= $_d_pic ?>" alt="" style="width:100%;height:100%;object-fit:cover;"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block';">
                <i class="fa fa-user" style="display:none;"></i>
            <?php else: ?>
                <i class="fa fa-user"></i>
            <?php endif; ?>
        </div>
        <?php if ($_d_hpr_ver): ?>
            <span class="hpr-badge" title="ABDM Healthcare Professional Registry Verified"><i class="fa fa-check-circle" style="margin-right:4px;"></i>HPR Verified</span>
        <?php else: ?>
            <a href="<?= BASE_URL ?>doctor/my-contact.php" class="hpr-badge hpr-unverified" title="Click to verify HPR ID" style="text-decoration:none;"><i class="fa fa-exclamation-circle" style="margin-right:4px;"></i>HPR Pending</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>doctor/doctor-logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
            <i class="fa fa-sign-out"></i>
        </a>
    </div>
</div>

<script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            var toggler = document.getElementById('doctorSidebarToggle');
            var sidebar = document.getElementById('doctorSidebar');
            var overlay = document.getElementById('doctorSidebarOverlay');
            // Show doctor name on medium+ screens
            var nameEl = document.getElementById('doctorTopbarName');
            if (nameEl && window.innerWidth >= 768) nameEl.style.display = 'block';
            if (!toggler) return;
            toggler.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            });
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            });
        });
    })();
</script>