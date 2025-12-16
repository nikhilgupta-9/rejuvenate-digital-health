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
    <div class="offcanvas__info">
        <div class="offcanvas__wrapper">
            <div class="offcanvas__content">
                <div class="offcanvas__top mb-5 d-flex justify-content-between align-items-center">
                    <div class="offcanvas__logo">
                        <a href="<?= $site ?>">
                            <img src="<?= $site.$logo ?>" alt="logo-img">
                        </a>
                    </div>
                    <div class="offcanvas__close">
                        <button>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="mobile-menu fix mt-3"></div>
                <a href="<?= $site ?>contact-us.php" class="theme-btn">
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
                    <a href="https://abha.abdm.gov.in/abha/v3/" target="_blank" class="btn btn-topabha">Abha Card</a>
                    <a href="https://esanjeevani.mohfw.gov.in" target="_blank" class="btn btn-esanjeevni">E-Sanjeevani</a>
                </div>
            </div>
            <ul class="top-list">
                <li>
                    <i class="fal fa-user"></i>
                    <a href="<?= $site ?>user-login/">
                        <?php
                        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                            echo htmlspecialchars($_SESSION['user_name']);
                        } else {
                            echo "Login / Signup";
                        }
                        ?>
                    </a>
                </li>
                <li>
                    <i class="fal fa-user-md"></i>
                    <a href="<?= $site ?>doctor-login/">
                        <?php
                        if(isset($_SESSION['doctor_logged_in'])){
                            echo "Dr." . htmlspecialchars($doctor_name);
                        }else{
                            echo "Doctors / Login";
                        }
                        ?>
                        
                    </a>
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

<!-- Header Section Start -->
<header id="header-sticky" class="header-section header-1">
    <div class="container">
        <div class="mega-menu-wrapper">
            <div class="header-main">
                <div class="header-left">
                    <a href="<?= $site ?>" class="header-logo1">
                        <img src="<?= $site . $logo ?>" alt="logo-img">
                    </a>
                </div>
                <div class="header-right d-flex justify-content-end align-items-center">
                    <div class="mean__menu-wrapper">
                        <div class="main-menu">
                            <nav id="mobile-menu">
                                <ul>
                                    <li> <a href="<?= $site ?>">Home </a> </li>
                                   
                                    <li>
                                        <a href="#">
                                            Departments
                                            <i class="fas fa-chevron-down"></i>
                                        </a>
                                        <ul class="submenu">
                                            <?php
                                            $department = get_sub_category();
                                            foreach ($department as $dep) {
                                            ?>
                                                <li><a href="<?= $site ?>department/<?= $dep['slug_url'] ?>"><?= $dep['categories'] ?></a></li>
                                            <?php
                                            }
                                            ?>
                                            
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
                                            ?>
                                                <li><a href="<?= $site ?>doctor-profile/<?= $doc['slug_url'] ?>"><?= $doc['name'] ?> </a></li>
                                            <?php
                                            }
                                            ?>
                                            
                                            <li><a href="#">Timing of online clinic</a></li>
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
                                                <li><a href="<?= $site ?>online-services/<?= $ob['slug_url'] ?>"><?= $ob['pro_name'] ?></a></li>
                                            <?php } ?>
                                            
                                        </ul>
                                    </li>
                                    <li> <a href="<?= $site ?>about-us.php">About Us </a></li>
                                    <li> <a href="<?= $site ?>contact/">Contact Us </a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <div class="header__hamburger d-xl-none my-auto">
                        <div class="sidebar__toggle">
                            <i class="fal fa-bars"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- Hero Section Start -->