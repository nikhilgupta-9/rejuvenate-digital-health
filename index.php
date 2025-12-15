<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health</title>
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
    <section class="hero-section hero-1 bg-cover fix" style="background-image: url('assets/img/home-1/hero/bg-01.jpg');">
        <div class="container">
            <div class="row g-4 align-items-center ">
                <div class="col-lg-7">
                    <div class="hero-content pt-4">
                        <h1><span class="banner-tags">Online Doctor Consultation</span> from the <br> comfort of your home</h1>
                        <p>Doctor Consultation starts from <span class="tags">Rs 149/-</span></p>
                        <div class="search_input mt-4">
                            <form class="d-flex">
                                <input type="search" class="form-control cutom_search" placeholder="Search Departments...">
                                <button type="search" class="btn btn-search"><i class="far fa-search"></i></button>
                            </form>
                        </div>

                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-image">
                        <img src="assets/img/home-1/hero/hero-img.png" alt="img" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Cta Section Start -->
    <section class="cta-section color-bg-1 pt-4 pb-5 fix">
        <div class="container">
            <div class="section-title">
                <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">MEET WITH DOCTOR</span>
                <h2 class="service-text">Consult Doctor by Speciality</h2>
                <p>Select speciality to find relevant doctors</p>
            </div>
            <div class="row g-4 pb-0 advance-wrap">

                <?php
                $department = get_sub_category();
                foreach ($department as $dept) {
                ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="team-box-items mt-0 ">
                            <a href="<?= $site ?>department/<?= $dept['slug_url'] ?>/">
                                <div class="team-image">
                                    <img src="<?= $site ?>admin/uploads/sub-category/<?= $dept['sub_cat_img'] ?>" alt="img">
                                    <span class="post-box">
                                        <?= $dept['categories'] ?>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php
                }
                ?>

            </div>

        </div>
    </section>

    <!-- Video Section Start -->
    <div class="vedio-bg-section fix bg-cover">
        <div class="counter-section">
            <div class="container">
                <div class="counter-wrapper zoom-effect-style">
                    <div class="counter-items wow fadeInUp" data-wow-delay=".2s">
                        <div class="icon">
                            <img src="assets/img/home-1/counter/icon-01.png" alt="img">
                        </div>
                        <div class="content">
                            <h2><span class="odometer" data-count="2">00</span>k</h2>
                            <p>Happy Patients</p>
                        </div>
                    </div>
                    <div class="counter-items wow fadeInUp" data-wow-delay=".4s">
                        <div class="icon">
                            <img src="assets/img/home-1/counter/icon-02.png" alt="img">
                        </div>
                        <div class="content">
                            <h2><span class="odometer" data-count="30">00</span>+</h2>
                            <p>Doctors</p>
                        </div>
                    </div>
                    <div class="counter-items wow fadeInUp" data-wow-delay=".6s">
                        <div class="icon">
                            <img src="assets/img/home-1/counter/icon-03.png" alt="img">
                        </div>
                        <div class="content">
                            <h2><span class="odometer" data-count="12">00</span>+</h2>
                            <p>Awards Winning</p>
                        </div>
                    </div>
                    <div class="counter-items wow fadeInUp" data-wow-delay=".8s">
                        <div class="icon">
                            <img src="assets/img/home-1/counter/icon-04.png" alt="img">
                        </div>
                        <div class="content">
                            <h2><span class="odometer" data-count="10">00</span>+</h2>
                            <p>Years of Experience</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>


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
                                <img src="assets/img/icon1.png">
                                <h2>Convenient & Time-Saving</h2>
                                <p>No more waiting rooms or long travel times. Consult your doctor online through secure video or chat consultations.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon2.png">
                                <h2>Affordable Healthcare</h2>
                                <p>Enjoy discounted online consultations, special prices on lab investigations, and exclusive discounts on medicines.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon3.png">
                                <h2>Trusted Medical Experts</h2>
                                <p>Get advice from qualified and experienced doctors across various specialties.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon4.png">
                                <h2>Easy Access to Diagnostics</h2>
                                <p>Book lab tests online at partner diagnostic centers and get reports directly on your dashboard.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon5.png">
                                <h2>Hassle-Free Medicine Delivery</h2>
                                <p>Order prescribed medicines at discounted rates — delivered right to your doorstep.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon6.png">
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


    <!-- Service Section Start -->
    <section class="service-section pt-4 pb-4 section-bg-2 fix">
        <div class="service-shape-1">
            <img src="assets/img/home-1/service/shape-1.png" alt="img">
        </div>
        <div class="service-shape-2">
            <img src="assets/img/home-1/service/shape-2.png" alt="img">
        </div>
        <div class="service-shape-3">
            <img src="assets/img/home-1/service/shape-3.png" alt="img">
        </div>
        <div class="container">

            <div class="service-wrapper">
                <div class="row">
                    <div class="col-lg-4">
                        <ul class="nav" id="serviceTabs">
                            <?php
                            $products = get_online_book($limit = 5);
                            $is_first = true;
                            foreach ($products as $index => $product) {
                                $active_class = $is_first ? 'active' : '';
                                echo '
                                    <li class="nav-item">
                                        <a href="#thumb' . $product['id'] . '" data-bs-toggle="tab" class="nav-link ' . $active_class . '">
                                            ' . $product['pro_name'] . ' <i class="far fa-chevron-right"></i>
                                        </a>
                                    </li>
                                    ';
                                $is_first = false;
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="col-lg-8">
                        <div class="tab-content" id="serviceContent">
                            <?php
                            $is_first = true;
                            foreach ($products as $product) {
                                $active_class = $is_first ? 'show active' : '';
                                echo '
                                    <div id="thumb' . $product['id'] . '" class="tab-pane fade ' . $active_class . '">
                                        <div class="service-box-items">
                                            <div class="service-icon-box">
                                                <div class="icon">
                                                    <i class="flaticon-good-heart"></i>
                                                </div>
                                                <h3>
                                                    <a href="' . $product['slug_url'] . '">' . $product['pro_name'] . '</a>
                                                </h3>
                                                <p>' . $product['short_desc'] . '</p>
                                               <a href="' . $site . 'online-services/' . $product['slug_url'] . '" class="theme-btn mt-5">
                                                    <i class="far fa-chevron-right"></i>
                                                    More Details
                                                </a>

                                            </div>
                                            <div class="service-image">
                                                <img src="' . $site . 'admin/assets/img/uploads/' . $product['pro_img'] . '" 
                                                    alt="' . $product['pro_name'] . '">
                                                <span class="post-box">' . $product['pro_name'] . '</span>
                                            </div>
                                        </div>
                                    </div>
';

                                $is_first = false;
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature Section Start -->
    <section class="feature-treatment-section pt-4 pb-4 fix section-bg-3">
        <div class="feature-shape-1">
            <img src="assets/img/home-1/feature/shape-01.png" alt="img">
        </div>

        <div class="container">
            <div class="section-title text-center">

                <h2 class="tx-title sec_title  tz-itm-title tz-itm-anim">We Are Specialize in Medical <br> Care For Yor Treatment</h2>
            </div>
            <div class="row">
                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="feature-treatment-items item_right_1">
                        <div class="feature-icon-box">
                            <h3>Your Wellness Is Our Mission</h3>
                            <i class="flaticon-heartbeat"></i>
                        </div>
                        <p>
                            A brief statement outlining the purpose and mission of the clinic. This can include the commitment
                        </p>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="feature-treatment-items">
                        <div class="feature-icon-box">
                            <h3>Trusted Care for <br> Every Life</h3>
                            <i class="flaticon-social-care"></i>
                        </div>
                        <p>
                            A brief statement outlining the purpose and mission of the clinic. This can include the commitment
                        </p>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="feature-treatment-items item_left_1">
                        <div class="feature-icon-box">
                            <h3>Health Solutions You <br> Can Trust</h3>
                            <i class="flaticon-health-insurance-1"></i>
                        </div>
                        <p>
                            A brief statement outlining the purpose and mission of the clinic. This can include the commitment
                        </p>
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
                    <div class="col-lg-8">
                        <div class="appointment-items">
                            <h3>Book An Appointment</h3>
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
                                            <p>Department</p>
                                            <div class="form">
                                                <select class="single-select w-100">
                                                    <option>Your department</option>
                                                     <?php
                                                    foreach ($department as $dep){
                                                    ?>
                                                    <option value="<?= $dep['categories'] ?>"> <?= $dep['categories'] ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
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
                                            <p>Time</p>
                                            <input type="time" placeholder="Your time">
                                        </div>
                                    </div>
                                    <div class="col-xl-12">
                                        <div class="form-clt">
                                            <button class="theme-btn" type="submit">
                                                Make an Appointment
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="appointment-image">
                            <img src="assets/img/home-1/appointment-img.jpg" alt="img">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Testimonial Section5 Start -->
    <!-- Time Table Section Start -->
    <section class="time-table-section-2 section-padding pt-4">
        <div class="container">
            <div class="time-table-wrapper-2">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <div class="time-content sticky-style">
                            <div class="section-title mb-0 text-start">
                                <h2 class="service-text tx-title sec_title  tz-itm-title tz-itm-anim">It is Easy of Our Working Steps for You</h2>
                            </div>
                            <p class="time-text wow fadeInUp" data-wow-delay=".2s">Crafting compelling digital experiences that captivate audiences and drive meaningful connections. Our digital agency combines innovation, strategy, and expertise to fuel your online success.</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="time-table-right-items">
                            <div class="time-box-items mt-0 wow fadeInUp" data-wow-delay=".3s">
                                <div class="time-table-content">
                                    <h3>Discuses with Patient</h3>
                                    <p class="mt-2">In every business year of this company we have created successful ventures with amazing companies.</p>
                                </div>
                                <h2 class="time-number">01</h2>
                            </div>
                            <div class="time-box-items mb-0 wow fadeInUp" data-wow-delay=".5s">
                                <h2 class="time-number">02</h2>
                                <div class="time-table-content">
                                    <h3>Make for Appointment</h3>
                                    <p class="mt-2">In every business year of this company we have created successful ventures with amazing companies.</p>
                                </div>
                            </div>
                            <div class="time-box-items wow fadeInUp" data-wow-delay=".7s">
                                <div class="time-table-content">
                                    <h3>Start The Treatment</h3>
                                    <p class="mt-2">In every business year of this company we have created successful ventures with amazing companies.</p>
                                </div>
                                <h2 class="time-number">03</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonial-section-1 section-padding pb-0 bg-cover fix" style="background-image: url(assets/img/home-1/testimonial/bg.jpg);">

        <div class="container">
            <div class="testimonial-wrapper-1">
                <div class="row g-4">

                    <div class="col-lg-12">
                        <div class="section-title-area">
                            <div class="section-title">
                                <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">OUR TESTIMONIAL</span>
                                <h2 class="tx-title sec_title  tz-itm-title tz-itm-anim">
                                    Our Clients Feedbacks
                                </h2>
                            </div>
                            <div class="array-button-2">
                                <button class="array-prev"><i class="fas fa-chevron-left"></i></button>
                                <button class="array-next"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="testimonial-right-item pb-4">
                            <div class="swiper testimonial-slider-1">
                                <div class="swiper-wrapper">
                                    <?php
                                    $testimonial = testimonial();
                                    foreach ($testimonial as $testi) {
                                    ?>
                                        <div class="swiper-slide">
                                            <div class="testimonial-box-item-1">
                                                <div class="client-image">
                                                    <img src="assets/img/dummy.png" alt="img">
                                                    <div class="star">
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                </div>
                                                <div class="testimonial-content">
                                                    <p>
                                                        “<?= $testi['testimonial_text'] ?>”
                                                    </p>
                                                    <div class="info-item">
                                                        <div class="info-content">
                                                            <h5><?= $testi['client_name'] ?></h5>
                                                            <span>Happy Patient</span>
                                                        </div>
                                                        <div class="icon">
                                                            <img src="assets/img/home-5/testimonial/01.svg" alt="img">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Faq Section Start -->
    <section class="faq-section section-padding pb-4">
        <div class="container">
            <div class="faq-wrapper-1">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <div class="faq-content sticky-style">
                            <div class="section-title mb-0 text-start">
                                <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">OUR FAQS</span>
                                <h2 class="tx-title sec_title  tz-itm-title tz-itm-anim">
                                    Most Popular Frequently Asked Questions About Us
                                </h2>
                            </div>
                            <div class="faq-button ">
                                <a href="tel:<?= $contact['phone'] ?>" class="theme-btn">
                                    <i class="far fa-chevron-right"></i>
                                    Contact With Us
                                </a>
                                <div class="icon-items">
                                    <div class="icon">
                                        <i class="flaticon-support"></i>
                                    </div>
                                    <div class="content">
                                        <p>Call Emergency</p>
                                        <h4><a href="tel:9319270957">+91-<?= $contact['phone'] ?></a></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="faq-items ">
                            <div class="faq-accordion">
                                <div class="accordion" id="accordion">
                                    <div class="accordion" id="accordion">
                                        <?php
                                        $faqs = faq_home();
                                        $index = 0;
                                        foreach ($faqs as $faq) {
                                            $index++;
                                            $headingId = "heading" . $index;
                                            $collapseId = "collapse" . $index;
                                        ?>
                                            <div class="accordion-item mb-3">
                                                <h5 class="accordion-header" id="<?= $headingId ?>">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#<?= $collapseId ?>"
                                                        aria-expanded="false"
                                                        aria-controls="<?= $collapseId ?>">
                                                        <?= $faq['question'] ?>
                                                    </button>
                                                </h5>
                                                <div id="<?= $collapseId ?>" class="accordion-collapse collapse"
                                                    aria-labelledby="<?= $headingId ?>"
                                                    data-bs-parent="#accordion">
                                                    <div class="accordion-body">
                                                        <?= $faq['answer'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>
</body>

</html>