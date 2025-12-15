<?php
include_once "config/connect.php";
include_once "util/function.php";

$pro_details = fetch_product_details();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <!-- <title><?= $pro_details['meta_title'] ?> | REJUVENATE Digital Health </title> -->
    <title> REJUVENATE Digital Health </title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
</head>

<body>
    <?php include("header.php") ?>
    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= $site ?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s"><?= $pro_details['pro_name'] ?></h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li>
                            <a href="<?= $site ?>">
                                Home
                            </a>
                        </li>
                        <li>
                            //
                        </li>
                        <li>
                            <?= $pro_details['pro_name'] ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- Team Details Section Start -->
    <section class="team-details-section section-padding fix">
        <div class="container">
            <div class="team-details-wrapper">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-5 wow fadeInUp" data-wow-delay=".3s">
                        <div class="team-image">
                            <img src="<?= $site ?>admin/assets/img/uploads/<?= $pro_details['pro_img'] ?>" alt="img">
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="team-right-content">
                            <h2><?= $pro_details['pro_name'] ?></h2>
                            <?= $pro_details['short_desc'] ?>

                            <?= $pro_details['description'] ?>
                            <h6>💊 Book your <?= $pro_details['pro_name'] ?> today and experience the smarter way to stay healthy!</h6>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
    <!--  Appointment Section Start -->
    <section class="appointment-section">
        <div class="container">
            <div class="appointment-wrapper">
                <div class="row g-2">
                    <div class="col-lg-12">
                        <div class="appointment-items">
                            <h3>Book Your Medicine</h3>
                            <form action="#">
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Name</p>
                                            <input type="text" placeholder="Your name">
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Email</p>
                                            <input type="text" placeholder="Your email">
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Phone</p>
                                            <input type="text" placeholder="Your phone">
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Date</p>
                                            <input type="date" placeholder="Your date">
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Subject</p>
                                            <input type="text" placeholder="Your Subject">
                                        </div>
                                    </div>
                                    <div class="col-xl-4">
                                        <div class="form-clt">
                                            <button class="theme-btn" type="submit">
                                                Book Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
    <!-- Testimonial Section5 Start -->
    <!-- About Section Start -->
    <section class="about-section-2 section-padding pb-4 fix">
        <div class="container">
            <div class="about-wrapper-2">
                <div class="row">

                    <div class="col-lg-12">
                        <div class="about-content">
                            <div class="section-title text-start mb-0">
                                <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">ABOUT US</span>
                                <h2 class="tx-title sec_title  tz-itm-title tz-itm-anim">Welcome to REJUVINATE DIGITAL HEALTH – Your Trusted Online Healthcare Partner.</h2>
                            </div>
                            <p class="about-text"><b>REJUVINATE DIGITAL HEALTH</b> welcomes you to tech Era of Digital Online Health support for you and your precious family members.</p>
                            <p class="about-text">At <b>REJUVINATE DIGITAL HEALTH</b>, we bring quality healthcare to your fingertips — anytime, anywhere. Our Online Telemedicine Consultation Platform connects you directly with experienced doctors from the comfort of your home.</p>

                            <div class="why-text">
                                <h3>💡 Why Choose Us?</h3>
                            </div>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="<?= $site ?>assets/img/icon1.png">
                                <h2>Convenient & Time-Saving</h2>
                                <p>No more waiting rooms or long travel times. Consult your doctor online through secure video or chat consultations.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="<?= $site ?>assets/img/icon2.png">
                                <h2>Affordable Healthcare</h2>
                                <p>Enjoy discounted online consultations, special prices on lab investigations, and exclusive discounts on medicines.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="<?= $site ?>assets/img/icon3.png">
                                <h2>Trusted Medical Experts</h2>
                                <p>Get advice from qualified and experienced doctors across various specialties.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="<?= $site ?>assets/img/icon4.png">
                                <h2>Easy Access to Diagnostics</h2>
                                <p>Book lab tests online at partner diagnostic centers and get reports directly on your dashboard.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="<?= $site ?>assets/img/icon5.png">
                                <h2>Hassle-Free Medicine Delivery</h2>
                                <p>Order prescribed medicines at discounted rates — delivered right to your doorstep.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="<?= $site ?>assets/img/icon6.png">
                                <h2> Safe, Secure & Confidential</h2>
                                <p>Your health data and medical records are fully encrypted and protected.</p>
                            </div>
                        </div>
                    </div>
                    <div class="why-text">
                        <h3>🩺 Get Started Today</h3>
                        <p>Your health deserves convenience, quality, and care — all in one place.
                            Sign up now and experience the future of healthcare with REJUVINATE DIGITAL HEALTH </p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <?php include("footer.php") ?>
</body>

</html>