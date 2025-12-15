<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$logo = get_header_logo();
?>
<!-- Cta Newsletter Section Start -->
<section class="cta-newsletter-section section-bg-2 fix">
    <div class="container">
        <div class="cta-newsletter-wrapper text-center text-xl-start">
            <div class="row g-4 align-items-center">
                <div class="col-xl-6">
                    <div class="section-title mb-0">
                        <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">OUR NEWSLETTER</span>
                        <h2 class="tx-title sec_title  tz-itm-title tz-itm-anim">
                            Join Our Newsletter To Never Miss Out On Health Updates.
                        </h2>
                    </div>
                </div>
                <div class="col-xl-6 ">
                    <div class="form-content">
                        <form action="#">
                            <input type="text" placeholder="Enter your e-mail">
                            <button class="arrow-icon" type="submit">
                                <i class="far fa-arrow-right"></i>
                            </button>
                        </form>
                        <p>By subscribing, you’re accept <a href="<?= $site ?>privacy-policy.php">Privacy Policy</a></p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
<!-- Footer Section Start -->
<footer class="footer-section section-bg-2 fix">
    <div class="container">
        <div class="footer-widget-wrapper">
            <div class="row">
                <div class="col-xxl-3 col-xl-4 colo-lg-4 col-md-6 col-sm-12">
                    <div class="single-footer-widget style-bg-white">
                        <div class="widget-head">
                            <a href="<?= $site ?>" class="footer-logo">
                                <img src="<?= $site . $logo ?>" alt="img">
                            </a>
                        </div>
                        <div class="footer-content">
                            <p class="text-dark">
                                Our mission is to provide high-quality medical care with a patient-centered approach, combining expert doctors, modern equipment, and advanced digital solutions to ensure better health outcomes.
                            </p>

                            <div class="icon">
                                <img src="<?= $site ?>assets/img/home-1/hero/feature-3.png" alt="img">
                                <div class="content">
                                    <p>
                                        For Booking
                                    </p>
                                    <h4><a href="tel:+91<?= $contact['wp_number'] ?>">+91-<?= $contact['wp_number'] ?></a></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-5 colo-lg-4 col-md-6 ps-lg-5 col-sm-12">
                    <div class="single-footer-widget-area">
                        <div class="single-footer-widget">
                            <div class="widget-head">
                                <h3> Online Clinic </h3>
                            </div>
                            <ul class="list-area">
                                <?php
                                $online_clinik = get_online_book($limit = 6);
                                foreach ($online_clinik as $online) {
                                ?>
                                    <li><a href="<?= $site ?>online-services/<?= $online['slug_url'] ?>"> <?= $online['pro_name'] ?></a></li>
                                <?php } ?>
                                <!-- <li><a href="#">E-Dispensary</a></li>
                                <li><a href="#">EMR</a></li>
                                <li><a href="#">E-Ambulance</a></li>
                                <li><a href="#">E-Emergency</a></li> -->
                            </ul>
                        </div>
                        <div class="single-footer-widget">
                            <div class="widget-head">
                                <h3>Our Panel</h3>
                            </div>
                            <ul class="list-area">
                                <?php
                                $doctors = getDoctors();
                                foreach ($doctors as $doc) {
                                ?>
                                    <li><a href="<?= $site ?>doctor-profile/<?= $doc['slug_url'] ?>"><?= $doc['name'] ?> </a></li>
                                <?php
                                }
                                ?>
                                <!-- <li><a href="#">Dr saini</a></li>
                                <li><a href="#">Dr Ansuman</a></li>
                                <li><a href="#">Dr khayyum</a></li> -->
                                <li><a href="#">Psychiatry </a></li>
                                <li><a href="#">Timing of online clinic</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-3 colo-lg-4 col-md-6 ps-lg-5 col-sm-6">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <h3> Departments</h3>
                        </div>
                        <ul class="list-area">
                            <?php
                            $department = get_sub_category();
                            foreach ($department as $dep) {
                            ?>
                                <li><a href="<?= $site ?>department/<?= $dep['slug_url'] ?>"><?= $dep['categories'] ?></a></li>
                            <?php
                            }
                            ?>
                            <!-- <li><a href="">E-Cardiology</a></li>
                            <li><a href="">E- Orthopedics</a></li>
                            <li><a href="">E-Neurology</a></li>
                            <li><a href="">E-ENT (Ear, Nose, Throat)</a></li>
                            <li><a href="">E-Pathology & Lab Services</a></li>
                            <li><a href="">E-Emergency</a></li> -->
                        </ul>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-3 colo-lg-3 col-md-6">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <h3>Contact Us</h3>
                        </div>
                        <div class="footer-content">

                            <ul class="footer-contect">
                                <li>
                                    <div class="icon">
                                        <i class="fas fa-mobile"></i>
                                    </div>
                                    <div class="content">
                                        <p><a href="tel:+91<?= $contact['phone'] ?>"> +91-<?= $contact['phone'] ?></a></p>
                                        <p><a href="tel:+91-<?= $contact['wp_number'] ?>"> +91-<?= $contact['wp_number'] ?></a></p>
                                    </div>
                                </li>
                                <li>
                                    <div class="icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="content">
                                        <p><a href="mailto:<?= $contact['email'] ?>"> <?= $contact['email'] ?></a></p>
                                    </div>
                                </li>
                                <li>
                                    <div class="icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="content">
                                        <p> <?= $contact['address'] ?></p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom wow fadeInUp">
        <div class="container">
            <div class="footer-bottom-wrapper">
                <p>Copyright © Rejuvenate Digital Health 2025. All Rights Reserved.</p>
                <div class="social-icon d-flex align-items-center">
                    <a href="<?= $contact['facebook'] ?>"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?= $contact['twitter'] ?>"><i class="fab fa-twitter"></i></a>
                    <a href="<?= $contact['instagram'] ?>"><i class="fab fa-youtube"></i></a>
                    <a href="<?= $contact['linkdin'] ?>"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </div>
</footer>
<script src="<?= $site ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?= $site ?>assets/js/jquery.nice-select.min.js"></script>
<script src="<?= $site ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= $site ?>assets/js/odometer.min.js"></script>
<script src="<?= $site ?>assets/js/jquery.appear.min.js"></script>
<script src="<?= $site ?>assets/js/swiper-bundle.min.js"></script>
<script src="<?= $site ?>assets/js/jquery.meanmenu.min.js"></script>
<script src="<?= $site ?>assets/js/jquery.magnific-popup.min.js"></script>
<script src="<?= $site ?>assets/js/wow.min.js"></script>
<script src="<?= $site ?>assets/js/gsap.min.js"></script>
<script src="<?= $site ?>assets/js/ScrollTrigger.min.js"></script>
<script src="<?= $site ?>assets/js/SplitText.min.js"></script>
<script src="<?= $site ?>assets/js/splitType.js"></script>
<script src="<?= $site ?>assets/js/main.js"></script>