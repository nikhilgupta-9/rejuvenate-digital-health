<?php

/**
 * Doctor Panel — Sidebar + Topbar
 * Set $sidebar_active before including (e.g. 'dashboard', 'patients', 'appointments')
 * Requires $doctor_id to be set (from JWT guard).
 */
$sidebar_active = $sidebar_active ?? '';

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
    'dashboard'        => 'Dashboard',
    'patients'         => 'My Patients',
    'appointments'     => 'Appointments',
    'patient-form'     => 'Patient Form Report',
    'reports'          => 'Calender',
    'analysis-report'  => 'Analysis Report',
    'school-students'  => 'Students',
    'settings'         => 'Change Password',
    'pending-uploads'  => 'Pending Uploads',
    'contact'          => 'Contact Us',
    'about'            => 'About Us',
    'delete-account'   => 'Delete Account',
];
$_page_title = $_page_titles[$sidebar_active] ?? 'Doctor Panel';

$_menu = [
    'dashboard'        => ['icon' => 'fa fa-th-large',    'label' => 'Dashboard',                'url' => BASE_URL . 'doctor/doctor-dashboard.php',      'section' => 'Main'],
    'patients'         => ['icon' => 'fa fa-heartbeat',   'label' => 'My Patients',               'url' => BASE_URL . 'doctor/my-patients.php',           'section' => 'Clinical'],
    'appointments'     => ['icon' => 'fa fa-book-medical',    'label' => 'Appointments',              'url' => BASE_URL . 'doctor/appointments.php',          'section' => 'Clinical'],
    'patient-form'     => ['icon' => 'fa fa-chart-area', 'label' => 'Patient Form Report',       'url' => BASE_URL . 'doctor/patient-form.php',          'section' => 'Clinical'],
    'reports'          => ['icon' => 'fa fa-calendar',   'label' => 'Calender',   'url' => BASE_URL . 'doctor/appointments-calendar.php', 'section' => 'Clinical'],
    'analysis-report'  => ['icon' => 'fa fa-chart-line',    'label' => 'Analysis Report',           'url' => BASE_URL . 'doctor/analysis-report.php',       'section' => 'Clinical'],
    'school-students'  => ['icon' => 'fa fa-user-graduate', 'label' => 'Students',                'url' => BASE_URL . 'doctor/school-students.php',       'section' => 'School Health'],
    'pending-uploads'  => ['icon' => 'fa fa-cloud-upload', 'label' => 'Pending Uploads',           'url' => BASE_URL . 'doctor/pending-uploads.php',       'section' => 'ABHA Compliance'],
    'settings'         => ['icon' => 'fa fa-cog',         'label' => 'Settings',                  'url' => BASE_URL . 'doctor/change-password.php',       'section' => 'Account'],
    'contact'          => ['icon' => 'fa fa-phone',       'label' => 'Contact Us',                'url' => BASE_URL . 'doctor/my-contact.php',            'section' => 'Account'],
    'about'            => ['icon' => 'fa fa-info-circle', 'label' => 'About Us',                  'url' => BASE_URL . 'doctor/doctor-about.php',          'section' => 'Account'],
    'delete-account'   => ['icon' => 'fa fa-trash',       'label' => 'Delete Account',            'url' => BASE_URL . 'doctor/delete-account.php',        'section' => 'Account'],
];
?>
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
    <div style="display:flex;align-items:center;gap:10px;">
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
            <span class="hpr-badge"><i class="fa fa-check-circle" style="margin-right:4px;"></i>HPR</span>
        <?php else: ?>
            <span class="hpr-badge hpr-unverified"><i class="fa fa-exclamation-circle" style="margin-right:4px;"></i>HPR Pending</span>
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