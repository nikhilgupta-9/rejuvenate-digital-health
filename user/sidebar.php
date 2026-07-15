<?php

/**
 * Patient Portal — Sidebar + Topbar
 * Set $sidebar_active before including (e.g. 'dashboard', 'bookings').
 * Requires $conn and $_SESSION['user_id'] to already be set.
 */
$sidebar_active = $sidebar_active ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch patient info for the sidebar header
$_u_sql  = "SELECT name, last_name, email, mobile, profile_pic, abha_id, abha_linked FROM users WHERE id = ?";
$_u_stmt = $conn->prepare($_u_sql);
$_u_stmt->bind_param('i', $user_id);
$_u_stmt->execute();
$_u = $_u_stmt->get_result()->fetch_assoc();

$_u_name    = htmlspecialchars(trim(($_u['name'] ?? 'Patient') . ' ' . ($_u['last_name'] ?? '')));
$_u_email   = htmlspecialchars($_u['email'] ?? '');
$_u_pic     = !empty($_u['profile_pic']) ? BASE_URL . 'assets/img/' . htmlspecialchars($_u['profile_pic']) : null;
$_u_initial = strtoupper(substr($_u_name, 0, 1)) ?: 'P';
$_u_abha_linked = !empty($_u['abha_linked']);

$_abha_pending = false;
if (!$_u_abha_linked) {
    $_ap = $conn->prepare("SELECT id FROM user_abha_requests WHERE user_id=? AND status='Pending' LIMIT 1");
    $_ap->bind_param('i', $user_id);
    $_ap->execute();
    $_abha_pending = (bool) $_ap->get_result()->fetch_assoc();
}

$_page_titles = [
    'dashboard'    => 'Dashboard',
    'profile'      => 'My Profile',
    'abha'         => 'My ABHA Health ID',
    'bookings'     => 'My Bookings',
    'reports'      => 'My Reports',
    'orders'       => 'My Supplement Order',
    'appointments' => 'My Doctor Appointments',
    'address'      => 'Manage Addresses',
    'help'         => 'Help & Contact Us',
];
$_page_title = $_page_titles[$sidebar_active] ?? 'Patient Portal';

$_menu = [
    'dashboard'    => ['icon' => 'fa fa-th-large',   'label' => 'Dashboard',              'url' => BASE_URL . 'user/user-dashboard.php',         'section' => 'Main'],
    'appointments' => ['icon' => 'fa fa-stethoscope', 'label' => 'My Doctor Appointments', 'url' => BASE_URL . 'user/my-doctor-appointments.php', 'section' => 'Health'],
    'bookings'     => ['icon' => 'fa fa-calendar-check-o', 'label' => 'My Bookings',       'url' => BASE_URL . 'user/my-bookings.php',            'section' => 'Health'],
    'abha'         => ['icon' => 'fa fa-id-card',    'label' => 'My ABHA Health ID',       'url' => BASE_URL . 'user/my-abha.php',                'section' => 'Health'],
    'reports'      => ['icon' => 'fa fa-file-text-o', 'label' => 'My Reports',             'url' => BASE_URL . 'user/my-reports.php',             'section' => 'Health'],
    'orders'       => ['icon' => 'fa fa-shopping-bag', 'label' => 'My Supplement Order',   'url' => BASE_URL . 'user/my-supplement-order.php',    'section' => 'Shop'],
    'address'      => ['icon' => 'fa fa-map-marker', 'label' => 'Manage Addresses',        'url' => BASE_URL . 'user/manage-address.php',         'section' => 'Shop'],
    'profile'      => ['icon' => 'fa fa-user',       'label' => 'My Profile',              'url' => BASE_URL . 'user/my-profile.php',             'section' => 'Account'],
    'help'         => ['icon' => 'fa fa-life-ring',  'label' => 'Help & Contact Us',       'url' => BASE_URL . 'user/help-and-contact.php',       'section' => 'Account'],
];
?>
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
        <div class="s-sub">Patient</div>
        <?php if ($_u_abha_linked): ?>
            <div style="margin-top:5px;">
                <span style="background:#02c9b8;border-radius:10px;padding:1px 6px;font-size:.6rem;font-weight:700;color:#fff;">
                    <i class="fa fa-check"></i> ABHA Linked
                </span>
            </div>
        <?php elseif ($_abha_pending): ?>
            <div style="margin-top:5px;">
                <span style="background:#d97706;border-radius:10px;padding:1px 6px;font-size:.6rem;font-weight:700;color:#fff;">
                    <i class="fa fa-clock-o"></i> ABHA Pending
                </span>
            </div>
        <?php else: ?>
            <div style="margin-top:5px;">
                <a href="<?= BASE_URL ?>user/my-abha.php"
                    style="font-size:.68rem;color:rgba(255,255,255,.55);text-decoration:underline;">+ Link ABHA ID</a>
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
    <div style="display:flex;align-items:center;">
        <button class="sidebar-toggler" id="patientSidebarToggle">
            <i class="fa fa-bars"></i>
        </button>
        <div>
            <div style="font-size:.95rem;font-weight:600;color:#1f2937;"><?= htmlspecialchars($_page_title) ?></div>
            <div style="font-size:.72rem;color:#9ca3af;"><?= date('l, d M Y') ?></div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
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
        <a href="<?= BASE_URL ?>" class="btn btn-sm btn-outline-primary" title="Visit Site">
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
