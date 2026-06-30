<?php
/**
 * Doctor Panel Sidebar — DriveCase-style
 * Requires: $conn, $doctor_id, $jwt_doctor (from guard)
 * Current page detection via $sidebar_active variable set before include.
 */

// Fetch full doctor profile for sidebar
$_sb = $conn->prepare("
    SELECT name, email, phone, profile_image, specialization,
           hpr_id, abha_id, hpr_verified
    FROM doctors WHERE id = ? LIMIT 1
");
$_sb->bind_param('i', $doctor_id);
$_sb->execute();
$_doc = $_sb->get_result()->fetch_assoc();

$_name    = htmlspecialchars($_doc['name'] ?? 'Doctor');
$_email   = htmlspecialchars($_doc['email'] ?? '');
$_hpr     = htmlspecialchars($_doc['hpr_id'] ?? '');
$_abha    = htmlspecialchars($_doc['abha_id'] ?? '');
$_img     = !empty($_doc['profile_image']) ? BASE_URL . $_doc['profile_image'] : BASE_URL . 'assets/img/dummy.png';
$_hprVerified = !empty($_doc['hpr_verified']);
$_active  = $sidebar_active ?? 'dashboard';

// Convert HPR ID to @hpr.abdm display format
$_hprDisplay = $_hpr ? $_hpr . '@hpr.abdm' : '';

$_menu = [
    'dashboard'   => ['icon' => 'fa-th-large',         'label' => 'Dashboard',              'url' => BASE_URL . 'doctor/doctor-dashboard.php'],
    'patients'    => ['icon' => 'fa-heartbeat',        'label' => 'Patients',               'url' => BASE_URL . 'doctor/my-patients.php'],
    'appointments'=> ['icon' => 'fa-clock-o',          'label' => 'Appointments',           'url' => BASE_URL . 'doctor/appointments.php'],
    'patient-form'=> ['icon' => 'fa-file-text-o',      'label' => 'Patient Form Report',    'url' => BASE_URL . 'doctor/patient-form.php'],
    'reports'     => ['icon' => 'fa-bar-chart',        'label' => 'Overall Analysis Report','url' => BASE_URL . 'doctor/appointments-calendar.php'],
    'settings'    => ['icon' => 'fa-cog',              'label' => 'Settings',               'url' => BASE_URL . 'doctor/change-password.php'],
    'contact'     => ['icon' => 'fa-phone',            'label' => 'Contact Us',             'url' => BASE_URL . 'doctor/my-contact.php'],
    'about'       => ['icon' => 'fa-info-circle',      'label' => 'About Us',               'url' => BASE_URL . 'doctor/doctor-about.php'],
];
?>

<!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
<div id="sidebarOverlay" onclick="closeSidebar()" style="
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1039;"></div>

<!-- ===== SIDEBAR ===== -->
<nav id="doctorSidebar" style="
    position:fixed; top:0; left:0; height:100vh; width:280px;
    background:#fff; z-index:1040; box-shadow:2px 0 16px rgba(0,0,0,.13);
    display:flex; flex-direction:column; transition:transform .28s ease;
    transform: translateX(-100%);
" aria-label="Doctor navigation">

    <!-- Profile Header -->
    <div style="
        background: linear-gradient(135deg, #0c74c5 0%, #0a5fa8 100%);
        padding: 28px 20px 22px; flex-shrink:0;
    ">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="position:relative; flex-shrink:0;">
                <img src="<?= $_img ?>" alt="<?= $_name ?>"
                     style="width:60px; height:60px; border-radius:50%; object-fit:cover;
                            border:2.5px solid rgba(255,255,255,.8);">
                <?php if ($_hprVerified): ?>
                <span title="HPR Verified" style="
                    position:absolute; bottom:0; right:0;
                    background:#22c55e; border-radius:50%; width:16px; height:16px;
                    border:2px solid #fff; display:flex; align-items:center; justify-content:center;">
                    <i class="fa fa-check" style="color:#fff; font-size:8px;"></i>
                </span>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:0;">
                <div style="color:#fff; font-weight:700; font-size:15px; line-height:1.2;
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= $_name ?>
                </div>
                <div style="color:rgba(255,255,255,.82); font-size:11.5px; margin-top:3px;
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= $_email ?>
                </div>
                <?php if ($_hprDisplay): ?>
                <div style="color:rgba(255,255,255,.75); font-size:11px; margin-top:2px;
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <i class="fa fa-id-card" style="font-size:10px;"></i>
                    <?= $_hprDisplay ?>
                </div>
                <?php else: ?>
                <a href="<?= BASE_URL ?>doctor/my-contact.php"
                   style="color:rgba(255,255,255,.65); font-size:11px; margin-top:2px; display:block;">
                    <i class="fa fa-plus-circle" style="font-size:10px;"></i> Add HPR ID
                </a>
                <?php endif; ?>
                <?php if ($_abha): ?>
                <div style="color:rgba(255,255,255,.75); font-size:11px; margin-top:2px;
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <i class="fa fa-hospital-o" style="font-size:10px;"></i>
                    <?= $_abha ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Menu Items -->
    <div style="flex:1; overflow-y:auto; padding:8px 0;">
        <?php foreach ($_menu as $key => $item): ?>
        <a href="<?= $item['url'] ?>"
           style="
               display:flex; align-items:center; gap:14px;
               padding:13px 22px; text-decoration:none;
               color: <?= $_active === $key ? '#0c74c5' : '#444' ?>;
               background: <?= $_active === $key ? '#e8f4fd' : 'transparent' ?>;
               border-right: <?= $_active === $key ? '3px solid #0c74c5' : '3px solid transparent' ?>;
               font-size:14px; font-weight: <?= $_active === $key ? '600' : '400' ?>;
               transition: background .15s, color .15s;
           "
           onmouseover="if('<?= $_active ?>'!='<?= $key ?>'){this.style.background='#f5f8ff';this.style.color='#0c74c5';}"
           onmouseout="if('<?= $_active ?>'!='<?= $key ?>'){this.style.background='transparent';this.style.color='#444';}">
            <i class="fa <?= $item['icon'] ?>"
               style="width:20px; text-align:center; font-size:16px; flex-shrink:0;"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>

        <div style="border-top:1px solid #f0f0f0; margin:8px 0;"></div>

        <!-- Logout -->
        <a href="<?= BASE_URL ?>doctor/doctor-logout.php"
           style="display:flex; align-items:center; gap:14px; padding:13px 22px;
                  text-decoration:none; color:#e53e3e; font-size:14px;
                  border-right:3px solid transparent; transition:background .15s;"
           onmouseover="this.style.background='#fff5f5';"
           onmouseout="this.style.background='transparent';">
            <i class="fa fa-sign-out" style="width:20px; text-align:center; font-size:16px;"></i>
            Logout
        </a>
    </div>

    <!-- Footer -->
    <div style="padding:12px 20px; border-top:1px solid #f0f0f0; flex-shrink:0;">
        <div style="font-size:10.5px; color:#aaa; text-align:center; line-height:1.5;">
            ABDM / ABHA Compliant Portal<br>
            &copy; <?= date('Y') ?> Rejuvenate Digital Health
        </div>
    </div>
</nav>

<!-- ===== TOP NAV BAR (mobile hamburger) ===== -->
<div id="doctorTopBar" style="
    position:sticky; top:0; z-index:1030;
    background:#fff; border-bottom:1px solid #e5e7eb;
    padding:10px 16px; display:flex; align-items:center; gap:12px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
">
    <button onclick="openSidebar()" style="
        background:none; border:none; cursor:pointer; padding:4px 6px;
        border-radius:6px; color:#0c74c5; font-size:20px; line-height:1;
    " aria-label="Open menu">☰</button>
    <span style="font-weight:700; font-size:15px; color:#0c74c5; flex:1;">
        Rejuvenate Digital Health
    </span>
    <img src="<?= $_img ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid #0c74c5;">
</div>

<!-- ===== DESKTOP LAYOUT SPACER ===== -->
<!-- On ≥992px the sidebar is always visible, content shifts right -->
<style>
    body { margin:0; padding:0; background:#f4f6fb; }

    /* Desktop: sidebar always open */
    @media (min-width: 992px) {
        #doctorSidebar  { transform: translateX(0) !important; }
        #sidebarOverlay { display:none !important; }
        #doctorTopBar   { display:none !important; }
        .doctor-content { margin-left:280px; }
    }

    /* Mobile: sidebar hidden by default */
    @media (max-width: 991px) {
        .doctor-content { margin-left:0; }
    }

    /* Scrollbar for sidebar */
    #doctorSidebar > div:nth-child(2) {
        scrollbar-width: thin;
        scrollbar-color: #ddd transparent;
    }
    #doctorSidebar > div:nth-child(2)::-webkit-scrollbar { width:4px; }
    #doctorSidebar > div:nth-child(2)::-webkit-scrollbar-thumb { background:#ddd; border-radius:2px; }
</style>

<script>
function openSidebar() {
    document.getElementById('doctorSidebar').style.transform = 'translateX(0)';
    document.getElementById('sidebarOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    if (window.innerWidth < 992) {
        document.getElementById('doctorSidebar').style.transform = 'translateX(-100%)';
        document.getElementById('sidebarOverlay').style.display = 'none';
        document.body.style.overflow = '';
    }
}
// Close on resize to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth >= 992) {
        document.getElementById('sidebarOverlay').style.display = 'none';
        document.body.style.overflow = '';
    }
});
</script>
