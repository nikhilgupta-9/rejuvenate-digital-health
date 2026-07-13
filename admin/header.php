<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['doctor_logged_in'])) {
    header("Location: auth/login.php");
    exit();
}

// Determine user type
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_doctor = isset($_SESSION['doctor_logged_in']) && $_SESSION['doctor_logged_in'] === true;
$user_name = $is_admin ? ($_SESSION['admin_user'] ?? 'Admin') : ($_SESSION['doctor_name'] ?? 'Doctor');

// Pending-approval badge counts (admin only)
$abha_pend = 0;
$pending_schools = 0;
if ($is_admin && isset($conn)) {
    $abha_pend = (int)(mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT (SELECT COUNT(*) FROM user_abha_requests WHERE status='Pending')
               + (SELECT COUNT(*) FROM abha_link_requests WHERE status='Pending') as c"))['c'] ?? 0);
    $pending_schools = (int)(mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM schools WHERE status='Pending'"))['c'] ?? 0);
}
?>

<nav class="sidebar vertical-scroll dark_sidebar ps-container ps-theme-default ps-active-y">

    <div class="admin-profile text-center py-3">
        <a href="<?= $is_admin ? 'admin-profile.php' : 'index.php' ?>">
            <img src="assets/img/logo_icon.jpg" alt="Logo" class="rounded-circle" width="60">
            <h6 class="mt-2 text-white"><?= htmlspecialchars($user_name) ?></h6>
            <span class="badge bg-<?= $is_admin ? 'primary' : 'success' ?> mt-1">
                <?= $is_admin ? 'Administrator' : 'Telemedicine Doctor' ?>
            </span>
        </a>
    </div>

    <div class="search-box px-3">
        <input type="text" id="sidebarSearch" class="form-control" placeholder="Search menu...">
    </div>

    <ul id="sidebar_menu" class="sidebar-menu mt-3">

        <!-- ==================== MAIN ==================== -->
        <li class="menu-label">Main</li>
        <li>
            <a href="index.php"><i class="fas fa-tachometer-alt" style="color: #3498db;"></i> <span>Dashboard</span></a>
        </li>

        <?php if ($is_doctor): ?>
        <li><a href="my-profile.php"><i class="fas fa-user-circle" style="color: #3498db;"></i> <span>My Profile</span></a></li>
        <li><a href="my-schedule.php"><i class="fas fa-clock" style="color: #2ecc71;"></i> <span>My Schedule</span></a></li>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <!-- ==================== WEBSITE CONTENT ==================== -->
        <li class="menu-label">Website Content</li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-home" style="color: #e74c3c;"></i> <span>Home Management</span></a>
            <ul>
                <li><a href="home-items.php">Logo &amp; Site Settings</a></li>
                <li><a href="add-banner.php">Banner Management</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-layer-group" style="color: #2ecc71;"></i> <span>Categories</span></a>
            <ul>
                <li><a href="view-categories.php">Service Categories</a></li>
                <li><a href="view-sub-categories.php">Departments</a></li>
                <li><a href="manage-onilne-services.php">Online Services</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-box-open" style="color: #f39c12;"></i> <span>Products</span></a>
            <ul>
                <li><a href="add-products.php">Add Products</a></li>
                <li><a href="show-products.php">Show Products</a></li>
                <li><a href="show-products-review.php">Product Reviews</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-blog" style="color: #ff7f50;"></i> <span>Blog</span></a>
            <ul>
                <li><a href="view-all-blog.php">All Posts</a></li>
                <li><a href="add-blog.php">Add New Post</a></li>
            </ul>
        </li>

        <li><a href="add-gallery.php"><i class="fas fa-images" style="color: #8e44ad;"></i> <span>Gallery</span></a></li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-quote-left" style="color: #16a085;"></i> <span>Testimonials</span></a>
            <ul>
                <li><a href="add-testimonial.php">Add Testimonial</a></li>
                <li><a href="view-testimonials.php">View Testimonials</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-info-circle" style="color: #d35400;"></i> <span>About Us</span></a>
            <ul>
                <li><a href="about_us.php">About Content</a></li>
                <li><a href="our-team.php">Our Team</a></li>
                <li><a href="our-mission.php">Mission &amp; Vision</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ==================== HEALTHCARE OPERATIONS ==================== -->
        <li class="menu-label">Healthcare Operations</li>

        <?php if ($is_admin): ?>
        <li>
            <a class="has-arrow" href="#"><i class="fas fa-user-md" style="color: #44a6ad;"></i> <span>Doctors</span></a>
            <ul>
                <li><a href="doctors-list.php">All Doctors</a></li>
                <li><a href="add-doctor.php">Add New Doctor</a></li>
                <li><a href="doctor-schedule.php">Manage Schedules</a></li>
                <li><a href="doctor-specializations.php">Specializations</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-calendar-check" style="color: #009688;"></i> <span>Appointments</span></a>
            <ul>
                <li><a href="all-appointment.php">All Appointments</a></li>
                <li><a href="today-appointments.php">Today's Appointments</a></li>
                <li><a href="upcoming-appointments.php">Upcoming Appointments</a></li>
                <li><a href="completed-appointments.php">Completed</a></li>
                <li><a href="cancelled-appointments.php">Cancelled</a></li>
            </ul>
        </li>

        <?php if ($is_admin): ?>
        <li>
            <a class="has-arrow" href="#" style="color:#fff;">
                <i class="fas fa-id-card" style="color:#00875a;"></i>
                <span>ABHA Health ID</span>
                <?php if ($abha_pend > 0): ?>
                    <span class="badge bg-danger ms-1" style="font-size:10px;"><?= $abha_pend ?></span>
                <?php endif; ?>
            </a>
            <ul>
                <li><a href="abha-management.php"><i class="fas fa-tachometer-alt me-1"></i> ABHA Dashboard</a></li>
                <li><a href="abha-management.php?portal=patients"><i class="fas fa-users me-1"></i> Patient ABHA</a></li>
                <li><a href="abha-management.php?portal=school"><i class="fas fa-school me-1"></i> School ABHA</a></li>
                <li><a href="abha-management.php?tab=requests"><i class="fas fa-inbox me-1"></i> Link Requests <?php if ($abha_pend > 0): ?><span class="badge bg-danger ms-1" style="font-size:9px;"><?= $abha_pend ?></span><?php endif; ?></a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-video" style="color: #e74c3c;"></i> <span>Telemedicine</span></a>
            <ul>
                <?php if ($is_admin): ?>
                <li><a href="telemedicine-settings.php">System Settings</a></li>
                <?php endif; ?>
                <li><a href="live-consultations.php">Live Consultations</a></li>
                <li><a href="video-call-history.php">Call History</a></li>
                <li><a href="prescriptions.php">Prescriptions</a></li>
                <li><a href="e-prescriptions.php">E-Prescriptions</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-users" style="color: #c0392b;"></i> <span>Patients</span></a>
            <ul>
                <li><a href="all-customers.php">All Patients</a></li>
                <li><a href="medical-records.php?tab=patients">Medical Records</a></li>
                <li><a href="patient-history.php">Patient History</a></li>
                <?php if ($is_doctor): ?>
                <li><a href="my-patients.php">My Patients</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-notes-medical" style="color: #1abc9c;"></i> <span>Medical Records</span></a>
            <ul>
                <li><a href="medical-records.php">All Records</a></li>
                <li><a href="medical-records.php?tab=patients"><i class="fas fa-user-injured me-1"></i> Patient Records</a></li>
                <li><a href="medical-records.php?tab=school"><i class="fas fa-user-graduate me-1"></i> School Member Records</a></li>
                <?php if ($is_admin): ?>
                <li><a href="upload-medical-record.php"><i class="fas fa-upload me-1 text-primary"></i> Upload Record</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <?php if ($is_admin): ?>
        <!-- ==================== SCHOOL HEALTH ==================== -->
        <li class="menu-label">School Health</li>
        <li>
            <a class="has-arrow" href="#"><i class="fas fa-school" style="color: #0d6efd;"></i> <span>Schools</span>
                <?php if ($pending_schools > 0): ?>
                <span class="badge bg-danger ms-1" style="font-size:10px;"><?= $pending_schools ?></span>
                <?php endif; ?>
            </a>
            <ul>
                <li><a href="schools-list.php">All Schools</a></li>
                <li><a href="schools-list.php?status=Pending"><i class="fas fa-clock me-1 text-warning"></i> Pending Approvals <?php if ($pending_schools > 0): ?><span class="badge bg-danger ms-1" style="font-size:9px;"><?= $pending_schools ?></span><?php endif; ?></a></li>
                <li><a href="schools-list.php?status=Active"><i class="fas fa-check-circle me-1 text-success"></i> Active Schools</a></li>
                <li><a href="add-school.php"><i class="fas fa-plus me-1 text-primary"></i> Add School</a></li>
                <li><a href="school-members.php">School Members</a></li>
            </ul>
        </li>

        <!-- ==================== BUSINESS ==================== -->
        <li class="menu-label">Business</li>

        <li><a href="orders.php"><i class="fas fa-shopping-cart" style="color: #e67e22;"></i> <span>Orders</span></a></li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-envelope" style="color: #27ae60;"></i> <span>Contact</span></a>
            <ul>
                <li><a href="add_contact.php">Contact Information</a></li>
                <li><a href="new-leads.php">Inquiries / Leads</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-comments" style="color: #9b59b6;"></i> <span>Communications</span></a>
            <ul>
                <li><a href="video-consultation.php">Video Consultation</a></li>
                <li><a href="chat-with-patient.php">Chat with Patient</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="messages.php">Messages</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-chart-bar" style="color: #f39c12;"></i> <span>Reports</span></a>
            <ul>
                <li><a href="appointment-reports.php">Appointment Reports</a></li>
                <li><a href="patient-reports.php">Patient Reports</a></li>
                <li><a href="revenue-reports.php">Revenue Reports</a></li>
                <?php if ($is_doctor): ?>
                <li><a href="my-performance.php">My Performance</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <!-- ==================== SYSTEM ==================== -->
        <li class="menu-label">System</li>

        <li><a href="manage-faq.php"><i class="fas fa-question-circle" style="color: #b99c1b;"></i><span>FAQ's</span></a></li>

        <?php if ($is_admin): ?>
        <li>
            <a class="has-arrow" href="#"><i class="fas fa-user-cog" style="color: #7f8c8d;"></i> <span>User Management</span></a>
            <ul>
                <li><a href="all-admin.php">All Users</a></li>
                <li><a href="admin-create.php">Create Admin</a></li>
                <li><a href="user-roles.php">User Roles</a></li>
                <li><a href="permissions.php">Permissions</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="fas fa-cog" style="color: #95a5a6;"></i> <span>Settings</span></a>
            <ul>
                <li><a href="general-settings.php">General Settings</a></li>
                <li><a href="payment-settings.php">Payment Settings</a></li>
                <li><a href="email-settings.php">Email Settings</a></li>
                <li><a href="telemedicine-settings.php">Telemedicine Settings</a></li>
            </ul>
        </li>

        <li><a href="admin-profile.php"><i class="fas fa-id-badge" style="color: #3498db;"></i> <span>My Profile</span></a></li>
        <?php endif; ?>

        <li><a href="auth/logout.php"><i class="fas fa-sign-out-alt" style="color: #e74c3c;"></i> <span>Log Out</span></a></li>
    </ul>
</nav>

<script>
    document.getElementById('sidebarSearch').addEventListener('input', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('#sidebar_menu > li').forEach(item => {
            if (item.classList.contains('menu-label')) return;
            let text = item.textContent.toLowerCase();
            item.style.display = text.includes(filter) ? '' : 'none';
        });
        document.querySelectorAll('#sidebar_menu > li.menu-label').forEach(label => {
            let next = label.nextElementSibling, hasVisible = false;
            while (next && !next.classList.contains('menu-label')) {
                if (next.style.display !== 'none') { hasVisible = true; break; }
                next = next.nextElementSibling;
            }
            label.style.display = hasVisible ? '' : 'none';
        });
    });
</script>
