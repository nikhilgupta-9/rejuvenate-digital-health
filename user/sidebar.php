<?php

/**
 * Patient Portal — Sidebar + Topbar
 * Set $sidebar_active before including (e.g. 'dashboard', 'bookings').
 * Requires $conn and $_SESSION['user_id'] to already be set.
 */
$sidebar_active = $sidebar_active ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch patient info for the sidebar header
$_u_sql  = "SELECT name, last_name, email, mobile, profile_pic, abha_id, abha_address, abha_linked, abha_verified FROM users WHERE id = ?";
$_u_stmt = $conn->prepare($_u_sql);
$_u_stmt->bind_param('i', $user_id);
$_u_stmt->execute();
$_u = $_u_stmt->get_result()->fetch_assoc();

$_u_name    = htmlspecialchars($_u['name'] ?? 'Patient');
$_u_email   = htmlspecialchars($_u['email'] ?? '');
$_u_pic     = !empty($_u['profile_pic']) ? BASE_URL . 'assets/img/' . htmlspecialchars($_u['profile_pic']) : null;
$_u_initial = strtoupper(substr($_u_name, 0, 1)) ?: 'P';
$_u_abha_linked = !empty($_u['abha_linked']);
$_u_abha_id = htmlspecialchars($_u['abha_id'] ?? '');

$_abha_pending = false;
if (!$_u_abha_linked) {
    $_ap = $conn->prepare("SELECT id FROM user_abha_requests WHERE user_id=? AND status='Pending' LIMIT 1");
    $_ap->bind_param('i', $user_id);
    $_ap->execute();
    $_abha_pending = (bool) $_ap->get_result()->fetch_assoc();
}

$_page_titles = [
    'dashboard'       => 'Patient Dashboard',
    'profile'         => 'Account Profile',
    'health'          => 'Personal Health Record (PHR)',
    'abha'            => 'ABDM ABHA Health ID & Card',
    'bookings'        => 'Book Doctor Consultation',
    'reports'         => 'Diagnostic & Lab Reports',
    'orders'          => 'My Supplement Orders',
    'pharmacy'        => 'My Medicine Orders',
    'lab'             => 'My Lab Test Bookings',
    'appointments'    => 'My Doctor Consultations',
    'medical-history' => 'Clinical & Medical History',
    'address'         => 'Manage Delivery Addresses',
    'help'            => 'Help & ABDM Support',
];
$_page_title = $_page_titles[$sidebar_active] ?? 'Patient Portal';

$_menu = [
    'dashboard'       => ['icon' => 'fa fa-th-large',     'label' => 'Dashboard',                 'url' => BASE_URL . 'user/user-dashboard.php',         'section' => 'Main'],
    'abha'            => ['icon' => 'fa fa-id-card',      'label' => 'ABHA Health ID & Card',     'url' => BASE_URL . 'user/my-abha.php',                'section' => 'Digital Health (ABDM)'],
    'health'          => ['icon' => 'fa fa-heartbeat',    'label' => 'Health Profile (PHR)',      'url' => BASE_URL . 'user/health-profile.php',         'section' => 'Digital Health (ABDM)'],
    'medical-history' => ['icon' => 'fa fa-file-medical', 'label' => 'Medical & Clinical History','url' => BASE_URL . 'user/medical-history.php',      'section' => 'Digital Health (ABDM)'],
    'reports'         => ['icon' => 'fa fa-chart-area',   'label' => 'Diagnostic Reports',       'url' => BASE_URL . 'user/my-reports.php',             'section' => 'Digital Health (ABDM)'],
    'appointments'    => ['icon' => 'fa fa-stethoscope',  'label' => 'My Consultations',          'url' => BASE_URL . 'user/my-doctor-appointments.php', 'section' => 'Consultations'],
    'bookings'        => ['icon' => 'fa fa-calendar-plus','label' => 'Book Consultation',         'url' => BASE_URL . 'user/my-bookings.php',            'section' => 'Consultations'],
    'pharmacy'        => ['icon' => 'fa fa-pills',        'label' => 'Medicine Orders',           'url' => BASE_URL . 'user/my-medicine-orders.php',    'section' => 'Pharmacy & Labs'],
    'lab'             => ['icon' => 'fa fa-flask',        'label' => 'Lab Test Bookings',         'url' => BASE_URL . 'user/my-lab-bookings.php',        'section' => 'Pharmacy & Labs'],
    'orders'          => ['icon' => 'fa fa-shopping-bag', 'label' => 'Supplement Orders',         'url' => BASE_URL . 'user/my-supplement-order.php',    'section' => 'Pharmacy & Labs'],
    'address'         => ['icon' => 'fa fa-map-marker',   'label' => 'Saved Addresses',           'url' => BASE_URL . 'user/manage-address.php',         'section' => 'Account & Support'],
    'profile'         => ['icon' => 'fa fa-user',         'label' => 'Account Profile',           'url' => BASE_URL . 'user/my-profile.php',             'section' => 'Account & Support'],
    'help'            => ['icon' => 'fa fa-life-ring',    'label' => 'Help & ABDM Support',       'url' => BASE_URL . 'user/help-and-contact.php',       'section' => 'Account & Support'],
];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="patientSidebarOverlay"></div>

<aside class="patient-sidebar" id="patientSidebar">

    <!-- Brand / Profile Header -->
    <div class="sidebar-brand">
        <?php if ($_u_pic): ?>
            <img src="<?= $_u_pic ?>" alt=""
                style="width:38px;height:38px;border-radius:8px;object-fit:cover;margin-bottom:8px;border:2px solid rgba(255,255,255,.3);">
        <?php else: ?>
            <div class="sidebar-logo"><?= $_u_initial ?></div>
        <?php endif; ?>
        <div class="s-name"><?= $_u_name ?></div>
        <div class="s-sub">Patient Account</div>
        <?php if ($_u_abha_linked): ?>
            <div style="margin-top:6px;">
                <span style="background:#02c9b8;border-radius:10px;padding:2px 8px;font-size:.62rem;font-weight:700;color:#fff;display:inline-block;">
                    <i class="fa fa-shield"></i> ABDM Verified
                </span>
                <?php if (!empty($_u_abha_id)): ?>
                    <div style="font-size:.65rem;color:rgba(255,255,255,.75);margin-top:2px;font-family:monospace;letter-spacing:.3px;">
                        <?= $_u_abha_id ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($_abha_pending): ?>
            <div style="margin-top:6px;">
                <span style="background:#d97706;border-radius:10px;padding:2px 8px;font-size:.62rem;font-weight:700;color:#fff;display:inline-block;">
                    <i class="fa fa-clock-o"></i> ABHA Pending
                </span>
            </div>
        <?php else: ?>
            <div style="margin-top:6px;">
                <a href="<?= BASE_URL ?>user/my-abha.php"
                    style="font-size:.68rem;color:rgba(255,255,255,.75);background:rgba(255,255,255,.15);border-radius:6px;padding:2px 8px;display:inline-block;text-decoration:none;">
                    <i class="fa fa-id-card"></i> Link ABHA ID
                </a>
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
        ?>
            <a href="<?= $item['url'] ?>"<?= $is_active ? ' class="active"' : '' ?>>
                <span class="nav-icon"><i class="<?= $item['icon'] ?>"></i></span>
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-label">Session</div>
        <a href="<?= BASE_URL ?>logout.php">
            <span class="nav-icon"><i class="fa fa-sign-out"></i></span>
            Logout
        </a>
    </nav>

    <div class="sidebar-footer">
        <i class="fa fa-user-circle" style="margin-right:5px;"></i><?= $_u_email ?>
    </div>
</aside>

<!-- Top Bar -->
<div class="patient-topbar">
    <div class="topbar-left">
        <button class="sidebar-toggler" id="patientSidebarToggle">
            <i class="fa fa-bars"></i>
        </button>
        <div class="topbar-title-wrap">
            <div class="topbar-title"><?= htmlspecialchars($_page_title) ?></div>
            <div class="topbar-date"><?= date('l, d M Y') ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <div style="text-align:right;display:none;" id="patientTopbarName">
            <span style="font-weight:600;font-size:.82rem;display:block;"><?= $_u_name ?></span>
            <span style="font-size:.7rem;color:#9ca3af;">Patient</span>
        </div>
        <div class="avatar-circle" style="width:34px;height:34px;font-size:.85rem;flex-shrink:0;overflow:hidden;">
            <?php if ($_u_pic): ?>
                <img src="<?= $_u_pic ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= $_u_initial ?>
            <?php endif; ?>
        </div>
        <a href="<?= BASE_URL ?>" class="btn btn-sm btn-outline-primary topbar-home-btn" title="Visit Site">
            <i class="fa fa-home"></i>
        </a>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
            <i class="fa fa-sign-out"></i>
        </a>
    </div>
</div>

<script>
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            var toggler = document.getElementById('patientSidebarToggle');
            var sidebar = document.getElementById('patientSidebar');
            var overlay = document.getElementById('patientSidebarOverlay');
            var nameEl = document.getElementById('patientTopbarName');
            if (nameEl && window.innerWidth >= 768) nameEl.style.display = 'block';
            if (!toggler) return;
            toggler.addEventListener('click', function () {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            });
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            });
        });
    })();
</script>
