<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$logo = get_header_logo();
?>
<button id="back-top" class="back-to-top">
    <i class="fas fa-long-arrow-up"></i>
</button>
<div class="fix-area">
    <div id="site-navigation-drawer" class="offcanvas__info" role="dialog" aria-label="Site navigation" aria-hidden="true">
        <div class="offcanvas__wrapper">
            <div class="offcanvas__content">
                <div class="offcanvas__top mb-5 d-flex justify-content-between align-items-center">
                    <div class="offcanvas__logo">
                        <a href="<?= BASE_URL ?>">
                            <img src="<?= BASE_URL.$logo ?>" alt="logo-img">
                        </a>
                    </div>
                    <div class="offcanvas__close">
                        <button>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="mobile-menu fix mt-3"></div>
                <div class="drawer-link-directory" aria-label="Important links">
                    <div class="drawer-link-group">
                        <h3>Important Links</h3>
                        <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i>Home</a>
                        <a href="<?= BASE_URL ?>book-appointment/"><i class="fas fa-calendar-check"></i>Book Appointment</a>
                        <a href="<?= BASE_URL ?>online-clinic/"><i class="fas fa-video"></i>Online Clinic</a>
                        <a href="<?= BASE_URL ?>doctor-network/"><i class="fas fa-user-md"></i>Doctor Network</a>
                        <a href="<?= BASE_URL ?>blogs/"><i class="fas fa-newspaper"></i>Blog</a>
                        <a href="<?= BASE_URL ?>school-program.php"><i class="fas fa-school"></i>School Programs</a>
                    </div>
                    <div class="drawer-link-group">
                        <h3>School Portal</h3>
                        <a href="<?= BASE_URL ?>school-register.php"><i class="fas fa-school"></i>Register Your School</a>
                        <a href="<?= BASE_URL ?>student-register.php"><i class="fas fa-user-graduate"></i>Student Registration</a>
                        <a href="<?= BASE_URL ?>teacher-register.php"><i class="fas fa-chalkboard-teacher"></i>Teacher Registration</a>
                        <a href="<?= BASE_URL ?>login.php"><i class="fas fa-sign-in-alt"></i>Login to Portal</a>
                    </div>
                    <div class="drawer-link-group">
                        <h3>About &amp; Support</h3>
                        <a href="<?= BASE_URL ?>about-us.php"><i class="fas fa-info-circle"></i>About Us</a>
                        <a href="<?= BASE_URL ?>contact-us.php"><i class="fas fa-envelope"></i>Contact Us</a>
                        <a href="<?= BASE_URL ?>faq.php"><i class="fas fa-question-circle"></i>FAQ</a>
                        <a href="<?= BASE_URL ?>privacy-policy.php"><i class="fas fa-user-shield"></i>Privacy Policy</a>
                        <a href="<?= BASE_URL ?>terms-and-condition.php"><i class="fas fa-file-contract"></i>Terms &amp; Conditions</a>
                        <a href="<?= BASE_URL ?>disclaimer.php"><i class="fas fa-file-alt"></i>Disclaimer</a>
                        <a href="<?= BASE_URL ?>legal-compliance.php"><i class="fas fa-balance-scale"></i>Legal Compliance</a>
                        <a href="<?= BASE_URL ?>sitemap.xml"><i class="fas fa-sitemap"></i>Sitemap</a>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>book-appointment/" class="theme-btn">
                    <i class="far fa-chevron-right"></i>
                    Appointment
                </a>
            </div>
        </div>
    </div>
</div>
<div class="offcanvas__overlay"></div>

<!-- Header Top Section Start -->
<div class="header-top-section">
    <div class="container">
        <div class="header-top-wrapper">
            <div class="top-right">
                <div class="abba-and-san">
                    <a href="https://abha.abdm.gov.in/abha/v3/login" target="_blank" class="btn btn-topabha">Abha Card</a>
                    <a href="https://esanjeevani.mohfw.gov.in/#/patient/signin" target="_blank" class="btn btn-esanjeevni">E-Sanjeevani</a>
                </div>
            </div>
            <ul class="top-list">
                <?php
                // If any role is already logged in, link straight to their dashboard
                // instead of showing the login picker.
                $rdh_logged_in = null;
                if (!empty($_SESSION['logged_in'])) {
                    $rdh_logged_in = ['icon' => 'fal fa-user', 'label' => $_SESSION['user_name'] ?? 'My Account', 'url' => BASE_URL . 'user/user-dashboard.php'];
                } elseif (!empty($_SESSION['school_logged_in'])) {
                    $rdh_logged_in = ['icon' => 'fal fa-school', 'label' => $_SESSION['school_user_name'] ?? 'School', 'url' => BASE_URL . 'school/dashboard.php'];
                } elseif (!empty($_SESSION['student_logged_in'])) {
                    $rdh_logged_in = ['icon' => 'fal fa-user-graduate', 'label' => $_SESSION['student_name'] ?? 'Student', 'url' => BASE_URL . 'school/student/dashboard.php'];
                } elseif (!empty($_SESSION['teacher_logged_in'])) {
                    $rdh_logged_in = ['icon' => 'fal fa-chalkboard-teacher', 'label' => $_SESSION['teacher_name'] ?? 'Teacher', 'url' => BASE_URL . 'school/teacher/dashboard.php'];
                }
                ?>
                <li>
                    <?php if ($rdh_logged_in): ?>
                        <i class="<?= $rdh_logged_in['icon'] ?>"></i>
                        <a href="<?= $rdh_logged_in['url'] ?>"><?= htmlspecialchars($rdh_logged_in['label']) ?></a>
                    <?php else: ?>
                        <i class="fal fa-user"></i>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginRoleModal">Login / Signup</a>
                    <?php endif; ?>
                </li>
                <li class="suport">
                    <i class="far fa-phone"></i>
                    <p>
                        Customer Support: +91-<?= $contact['phone'] ?>
                    </p>
                </li>
                <li class="suport">
                    <i class="fal fa-envelope"></i>
                    <p>
                        <?= $contact['email'] ?>
                    </p>
                </li>
            </ul>


        </div>
    </div>
</div>

<!-- Login Role Selector Modal -->
<div class="modal fade" id="loginRoleModal" tabindex="-1" aria-labelledby="loginRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content role-select-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="loginRoleModalLabel">Login as</h5>
                    <p class="text-muted mb-0" style="font-size:.85rem;">Select who you are to continue to the right login page</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-3">
                    <div class="col-6 col-sm-4">
                        <a href="<?= BASE_URL ?>user-login/" class="role-select-card">
                            <i class="fal fa-user"></i>
                            <span class="role-name">Patient</span>
                            <span class="role-desc">Book &amp; manage appointments</span>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4">
                        <a href="<?= BASE_URL ?>doctor-login/" class="role-select-card">
                            <i class="fal fa-user-md"></i>
                            <span class="role-name">Doctor</span>
                            <span class="role-desc">Doctor panel access</span>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4">
                        <a href="<?= BASE_URL ?>school-login.php" class="role-select-card">
                            <i class="fal fa-school"></i>
                            <span class="role-name">School</span>
                            <span class="role-desc">School admin login</span>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4">
                        <a href="<?= BASE_URL ?>student-login.php" class="role-select-card">
                            <i class="fal fa-user-graduate"></i>
                            <span class="role-name">Student</span>
                            <span class="role-desc">School student login</span>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4">
                        <a href="<?= BASE_URL ?>teacher-login.php" class="role-select-card">
                            <i class="fal fa-chalkboard-teacher"></i>
                            <span class="role-name">Teacher</span>
                            <span class="role-desc">School teacher login</span>
                        </a>
                    </div>
                </div>
                <p class="text-center text-muted mt-3 mb-0" style="font-size:.78rem;">
                    New patient? <a href="<?= BASE_URL ?>signup.php">Create an account</a>
                </p>
            </div>
        </div>
    </div>
</div>
<style>
    .role-select-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        height: 100%;
        padding: 22px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        color: #374151;
        text-decoration: none;
        transition: .2s ease;
    }

    .role-select-card:hover,
    .role-select-card:focus {
        border-color: #0C74C5;
        background: #f0f7ff;
        color: #0C74C5;
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(12, 116, 197, .15);
        text-decoration: none;
    }

    .role-select-card i {
        font-size: 1.9rem;
        color: #0C74C5;
        margin-bottom: 8px;
    }

    .role-select-card .role-name {
        display: block;
        font-weight: 700;
        font-size: .9rem;
    }

    .role-select-card .role-desc {
        display: block;
        font-size: .7rem;
        color: #9ca3af;
        margin-top: 2px;
    }

    .role-select-card:hover .role-desc {
        color: #5b9fd6;
    }

    @media (max-width: 400px) {
        .role-select-card {
            padding: 16px 6px;
        }

        .role-select-card i {
            font-size: 1.5rem;
        }
    }
</style>

<!-- Header Section Start -->
<header id="header-sticky" class="header-section header-1">
    <div class="container">
        <div class="mega-menu-wrapper">
            <div class="header-main">
                <div class="header-left">
                    <a href="<?= BASE_URL ?>" class="header-logo1">
                        <img src="<?= BASE_URL . $logo ?>" alt="logo-img">
                    </a>
                </div>
                <div class="header-right d-flex justify-content-end align-items-center">
                    <div class="mean__menu-wrapper">
                        <div class="main-menu">
                            <nav id="mobile-menu">
                                <ul>
                                    <li> <a href="<?= BASE_URL ?>">Home </a> </li>
                                   
                                    <li>
                                        <a href="#">
                                            Departments
                                            <i class="fas fa-chevron-down"></i>
                                        </a>
                                        <ul class="submenu">
                                            <li><a href="<?=BASE_URL?>departments">All Departments</a></li>
                                            <?php
                                            $department = get_sub_category();
                                            foreach ($department as $dep) {
                                            ?>
                                                <li><a href="<?= BASE_URL ?>department/<?= $dep['slug_url'] ?>"><?= $dep['categories'] ?></a></li>
                                            <?php
                                            }
                                            ?>
                                            
                                        </ul>
                                    </li>
                                    

                                    <li>
                                        <a href="#">
                                            Book Online
                                            <i class="fas fa-chevron-down"></i>
                                        </a>
                                        <ul class="submenu">
                                            <?php
                                            $online_book = get_online_book($limit = 15);
                                            foreach ($online_book as $ob) {
                                            ?>
                                                <li><a href="<?= BASE_URL ?>online-services/<?= $ob['slug_url'] ?>"><?= $ob['pro_name'] ?></a></li>
                                            <?php } ?>
                                            
                                        </ul>
                                    </li>
                                    <li>
                                        <a href="#">
                                            Our panel
                                            <i class="fas fa-chevron-down"></i>
                                        </a>
                                        <ul class="submenu">
                                            <?php
                                            $doctors = getDoctors();
                                            foreach ($doctors as $doc) {
                                                // Fixed: Added slashes '/Dr/' around the pattern
                                                if (preg_match('/Dr/', $doc['name'])) { 
                                            ?>
                                                    <li><a href="<?= BASE_URL ?>doctor-profile/<?= $doc['slug_url'] ?>"><?= $doc['name'] ?></a></li>
                                            <?php
                                                } else {
                                            ?>
                                                    <li><a href="<?= BASE_URL ?>doctor-profile/<?= $doc['slug_url'] ?>">Dr. <?= $doc['name'] ?></a></li>
                                            <?php
                                                }
                                            }
                                            ?>
                                            
                                            <li><a href="<?= BASE_URL ?>online-clinic/">Online Clinic (Telemedicine)</a></li>
                                        </ul>
                                    </li>
                                    <li> <a href="<?= BASE_URL ?>about-us.php">About Us </a></li>
                                    <li> <a href="<?= BASE_URL ?>doctor-network/">Doctor Network </a></li>
                                    <li> <a href="<?= BASE_URL ?>blogs/">Blog </a></li>
                                    <!-- <li> <a href="<?= BASE_URL ?>contact/">Contact Us </a></li> -->
                                    <li> <a href="<?= BASE_URL ?>school-program.php">School Programs </a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <div class="header__hamburger my-auto">
                        <button type="button" class="sidebar__toggle" aria-label="Open navigation menu" aria-controls="site-navigation-drawer" aria-expanded="false">
                            <i class="fal fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- Hero Section Start -->