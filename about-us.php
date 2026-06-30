<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$logo    = get_header_logo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="Learn about REJUVENATE Digital Health — India's trusted digital health platform connecting patients with expert doctors through telemedicine, school health programs, and ABHA-linked health records.">
    <title>About Us | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>
<body>
<?php include "header.php" ?>

<!-- Breadcrumb Section -->
<div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
    <div class="container">
        <div class="page-heading">
            <div class="breadcrumb-items-area">
                <div class="breadcrumb-sub-title">
                    <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">About Us</h1>
                </div>
                <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                    <li><a href="<?= BASE_URL ?>">Home</a></li>
                    <li>//</li>
                    <li>About Us</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- About Section -->
<section class="about-section-2 section-padding pb-4 fix">
    <div class="container">
        <div class="about-wrapper-2">
            <div class="row g-4 align-items-center">

                <div class="col-lg-6">
                    <div class="about-image-items">
                        <img src="<?= BASE_URL ?>assets/img/inner/news/post-04.jpg" alt="About REJUVENATE Digital Health" class="img-fluid rounded">
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="about-content">
                        <div class="section-title text-start mb-4">
                            <span class="subtitle tz-sub-tilte tz-sub-anim text-uppercase tx-subTitle">Who We Are</span>
                            <h2 class="tx-title sec_title tz-itm-title tz-itm-anim">
                                REJUVENATE Digital Health — Your Trusted Online Healthcare Partner
                            </h2>
                        </div>
                        <p class="about-text">
                            <strong>REJUVENATE Digital Health</strong> is a comprehensive digital health platform built to bring quality, accessible, and affordable healthcare directly to your fingertips — anytime, anywhere across India.
                        </p>
                        <p class="about-text">
                            We connect patients with experienced, verified doctors through secure telemedicine consultations, manage school health programmes, facilitate ABHA (Ayushman Bharat Health Account) linkage, and empower individuals with complete digital health records — all on a single, easy-to-use platform.
                        </p>
                        <p class="about-text">
                            Our platform serves patients, school students, teachers, and healthcare professionals, offering them a unified portal to manage health, wellness, and medical records securely and efficiently.
                        </p>
                        <div class="row g-3 mt-2">
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check-circle text-primary fs-5"></i>
                                    <span>Verified Doctors</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check-circle text-primary fs-5"></i>
                                    <span>ABHA Integration</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check-circle text-primary fs-5"></i>
                                    <span>School Health Portal</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check-circle text-primary fs-5"></i>
                                    <span>Encrypted Health Records</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check-circle text-primary fs-5"></i>
                                    <span>24/7 Online Consultation</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check-circle text-primary fs-5"></i>
                                    <span>Discounted Lab Tests</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Mission Vision Values -->
<section class="section-padding fix" style="background: var(--bg); margin-bottom: 2rem;">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="about-box-items h-100">
                    <div class="number-content text-center">
                        <div class="mb-3">
                            <i class="fas fa-bullseye fa-3x" style="color: var(--theme-color);"></i>
                        </div>
                        <h4>Our Mission</h4>
                        <p>To make quality healthcare accessible and affordable to every Indian — from urban centres to rural communities — by leveraging digital technology, telemedicine, and seamless health record management.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="about-box-items h-100">
                    <div class="number-content text-center">
                        <div class="mb-3">
                            <i class="fas fa-eye fa-3x" style="color: var(--theme-color);"></i>
                        </div>
                        <h4>Our Vision</h4>
                        <p>To become India's most trusted digital health ecosystem — where every patient, student, doctor, and school institution can manage health proactively with confidence, privacy, and dignity.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="about-box-items h-100">
                    <div class="number-content text-center">
                        <div class="mb-3">
                            <i class="fas fa-heart fa-3x" style="color: var(--theme-color);"></i>
                        </div>
                        <h4>Our Values</h4>
                        <p>We are guided by compassion, transparency, patient privacy, and clinical excellence. Every feature we build and every decision we make puts the health and trust of our users first.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="section-padding fix">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <div class="section-title">
                    <span class="subtitle tz-sub-tilte text-uppercase tx-subTitle">Why Us</span>
                    <h2 class="tx-title sec_title tz-itm-title">Why Choose REJUVENATE Digital Health?</h2>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="about-box-items">
                    <div class="number-content">
                        <img src="<?= BASE_URL ?>assets/img/icon1.png" alt="Convenient">
                        <h4>Convenient &amp; Time-Saving</h4>
                        <p>No more waiting rooms or long travel times. Consult your doctor through secure video or chat consultations — from home, office, or anywhere.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="about-box-items">
                    <div class="number-content">
                        <img src="<?= BASE_URL ?>assets/img/icon2.png" alt="Affordable">
                        <h4>Affordable Healthcare</h4>
                        <p>Enjoy discounted online consultations, special prices on lab investigations, and exclusive discounts on medicines — healthcare that fits your budget.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="about-box-items">
                    <div class="number-content">
                        <img src="<?= BASE_URL ?>assets/img/icon3.png" alt="Trusted Doctors">
                        <h4>Trusted Medical Experts</h4>
                        <p>Get advice from qualified and verified doctors across multiple specialties. All doctors on our platform are registered with the National Medical Commission.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="about-box-items">
                    <div class="number-content">
                        <img src="<?= BASE_URL ?>assets/img/icon4.png" alt="Diagnostics">
                        <h4>Easy Access to Diagnostics</h4>
                        <p>Book lab tests online at partner diagnostic centres and get reports directly on your personal health dashboard — no paperwork, no delays.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="about-box-items">
                    <div class="number-content">
                        <img src="<?= BASE_URL ?>assets/img/icon5.png" alt="Medicine">
                        <h4>Hassle-Free Medicine Delivery</h4>
                        <p>Order prescribed medicines at discounted rates — delivered right to your doorstep through our trusted pharmacy network across India.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="about-box-items">
                    <div class="number-content">
                        <img src="<?= BASE_URL ?>assets/img/icon6.png" alt="Secure">
                        <h4>Safe, Secure &amp; Confidential</h4>
                        <p>Your health data and medical records are fully encrypted, DPDP Act compliant, and protected with industry-grade security. Your privacy is our priority.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="section-padding fix" style="background: var(--bg);">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <div class="section-title">
                    <span class="subtitle tz-sub-tilte text-uppercase tx-subTitle">Services</span>
                    <h2 class="tx-title sec_title tz-itm-title">What We Offer</h2>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="contact-info-box-items">
                    <div class="icon"><i class="fas fa-video"></i></div>
                    <div class="content">
                        <h6>Telemedicine Consultations</h6>
                        <p>Secure online doctor consultations via video and chat with NMC-verified specialists.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="contact-info-box-items">
                    <div class="icon"><i class="fas fa-school"></i></div>
                    <div class="content">
                        <h6>School Health Portal</h6>
                        <p>Dedicated module for schools to manage student and teacher health records, BMI, and wellness tracking.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="contact-info-box-items">
                    <div class="icon"><i class="fas fa-id-card"></i></div>
                    <div class="content">
                        <h6>ABHA Health ID</h6>
                        <p>Create and link your Ayushman Bharat Health Account to maintain a lifelong digital health record.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="contact-info-box-items">
                    <div class="icon"><i class="fas fa-flask"></i></div>
                    <div class="content">
                        <h6>Lab Test Booking</h6>
                        <p>Book diagnostic tests at partner labs and access reports digitally on your health dashboard.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section-padding fix">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="section-title mb-4">
                    <span class="subtitle tz-sub-tilte text-uppercase tx-subTitle">Get Started</span>
                    <h2 class="tx-title sec_title tz-itm-title">Ready to Take Control of Your Health?</h2>
                    <p class="mt-3">Your health deserves convenience, quality, and care — all in one place. Join thousands of patients, schools, and healthcare professionals who trust REJUVENATE Digital Health.</p>
                </div>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="<?= BASE_URL ?>signup.php" class="theme-btn px-3 pt-2">Register as Patient</a>
                    <a href="<?= BASE_URL ?>school-register.php" class="btn btn-esanjeevni">Register Your School</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Info Section -->
<section class="contact-info-section section-padding pt-5 pb-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <div class="contact-info-box-items">
                    <div class="icon"><i class="far fa-phone-alt"></i></div>
                    <div class="content">
                        <h6>Call Us</h6>
                        <a href="tel:+91-<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a><br>
                        <a href="tel:+91-<?= $contact['wp_number'] ?>">+91-<?= $contact['wp_number'] ?></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="contact-info-box-items">
                    <div class="icon"><i class="far fa-envelope"></i></div>
                    <div class="content">
                        <h6>Send Email</h6>
                        <a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</section>

<?php include "footer.php" ?>
</body>
</html>
