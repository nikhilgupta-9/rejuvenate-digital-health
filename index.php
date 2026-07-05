<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();

// echo 10 / 0;
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
                $department = get_sub_category_home();
                foreach ($department as $dept) {
                ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="team-box-items mt-0 ">
                            <a href="<?= BASE_URL ?>department/<?= $dept['slug_url'] ?>/">
                                <div class="team-image">
                                    <img src="<?= BASE_URL ?>admin/uploads/sub-category/<?= $dept['sub_cat_img'] ?>" alt="img">
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
                                <h2 class="tx-title sec_title tz-itm-title tz-itm-anim">
                                    Transforming Healthcare Through Digital Innovation
                                </h2>
                            </div>
                            <p class="about-text">
                                <strong>Rejuvenate Digital Health</strong> is dedicated to transforming healthcare through innovative digital solutions that make medical services more accessible, secure, and convenient. Our mission is to empower individuals, families, healthcare providers, and institutions with technology-driven healthcare services that improve overall well-being.
                            </p>

                            <p class="about-text">
                                Our platform offers a comprehensive range of digital health services, including online doctor consultations, digital health records, preventive healthcare programs, wellness monitoring, and continuous patient support. We are committed to delivering a seamless healthcare experience with a strong focus on quality, privacy, and patient care.
                            </p>

                            <p class="about-text">
                                As part of India's Digital Health Mission, our platform is integrated with the <strong>Ayushman Bharat Health Account (ABHA)</strong> ecosystem, enabling users to create and link their ABHA ID, securely manage digital health records, and experience interoperable healthcare services across participating healthcare providers.
                            </p>

                            <p class="about-text">
                                We also proudly conduct our <strong>School Digital Health Program</strong>, helping educational institutions promote preventive healthcare through digital health screening, health awareness initiatives, wellness monitoring, and timely medical guidance for students and staff.
                            </p>

                            <div class="why-text">
                                <h3>💡 Why Choose Us?</h3>
                            </div>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon1.png" alt="Digital Health Services">
                                <h2>Comprehensive Digital Health Services</h2>
                                <p>Access a wide range of digital healthcare solutions, including online consultations, preventive care, health monitoring, and patient support—all from one secure platform.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon2.png" alt="ABHA Integration">
                                <h2>ABHA Integrated Platform</h2>
                                <p>Seamlessly create, link, and manage your ABHA account for secure digital health records and a connected healthcare experience.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon3.png" alt="Expert Healthcare Professionals">
                                <h2>Qualified Healthcare Professionals</h2>
                                <p>Connect with experienced doctors and healthcare experts across multiple specialties for trusted medical guidance.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon4.png" alt="School Digital Health Program">
                                <h2>School Digital Health Program</h2>
                                <p>Empowering educational institutions with digital health screening, wellness monitoring, health awareness, and preventive healthcare initiatives.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon5.png" alt="Secure Health Records">
                                <h2>Secure Digital Health Records</h2>
                                <p>Maintain and access your health records securely with advanced encryption and privacy standards whenever you need them.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon6.png" alt="Secure Platform">
                                <h2>Trusted, Secure & Patient-Centric</h2>
                                <p>Built with industry-standard security and designed around patient privacy, reliability, and a seamless digital healthcare experience.</p>
                            </div>
                        </div>
                    </div>

                    <div class="why-text mt-4">
                        <h3>Transforming Healthcare with Technology</h3>
                        <p>
                            At <strong>Rejuvenate Digital Health</strong>, we are committed to making quality healthcare accessible through innovative digital solutions. Whether you're an individual, a family, a healthcare provider, or an educational institution, our platform delivers secure, efficient, and technology-driven healthcare services that support better health outcomes for everyone.
                        </p>
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
                                               <a href="' . BASE_URL . 'online-services/' . $product['slug_url'] . '" class="theme-btn mt-5">
                                                    <i class="far fa-chevron-right"></i>
                                                    More Details
                                                </a>

                                            </div>
                                            <div class="service-image">
                                                <img src="' . BASE_URL . 'admin/assets/img/uploads/' . $product['pro_img'] . '" 
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
                <span class="subtitle text-uppercase">OUR SERVICES</span>
                <h2 class="tx-title sec_title tz-itm-title tz-itm-anim">
                    Empowering Healthcare Through Digital Innovation
                </h2>
                <p>
                    Delivering secure, accessible, and technology-driven healthcare solutions for individuals, families, educational institutions, and healthcare providers.
                </p>
            </div>

            <div class="row">
                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="feature-treatment-items item_right_1">
                        <div class="feature-icon-box">
                            <h3>Digital Health Services</h3>
                            <i class="flaticon-heartbeat"></i>
                        </div>
                        <p>
                            Access online healthcare services, teleconsultations, digital health records, preventive care, and wellness support from a single, secure platform.
                        </p>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="feature-treatment-items">
                        <div class="feature-icon-box">
                            <h3>ABHA Integrated Healthcare</h3>
                            <i class="flaticon-social-care"></i>
                        </div>
                        <p>
                            Create, link, and manage your ABHA account to securely access digital health records and enable seamless healthcare across participating providers.
                        </p>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="feature-treatment-items item_left_1">
                        <div class="feature-icon-box">
                            <h3>School Digital Health Program</h3>
                            <i class="flaticon-health-insurance-1"></i>
                        </div>
                        <p>
                            Promote student wellness through digital health screening, health awareness programs, preventive care, and continuous health monitoring in schools.
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

                            <form id="appointmentForm">
                                <div class="row">

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Name</p>
                                            <input type="text" name="name" placeholder="Your name" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Email</p>
                                            <input type="email" name="email" placeholder="Your email" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Phone</p>
                                            <input type="text" name="phone" placeholder="Your phone" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Department</p>
                                            <select class="single-select w-100" name="department" required>
                                                <option value="">Your department</option>
                                                <?php foreach ($department as $dep) { ?>
                                                    <option value="<?= $dep['categories'] ?>">
                                                        <?= $dep['categories'] ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Date</p>
                                            <input type="date" name="date" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Time</p>
                                            <input type="time" name="time" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-12">
                                        <div class="form-clt">
                                            <button type="submit" class="theme-btn">
                                                <span class="btn-text">Make an Appointment</span>
                                                <span class="loader d-none"></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-xl-12 mt-3">
                                        <div id="formMessage"></div>
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
                                        // Calculate star rating
                                        $rating = isset($testi['rating']) ? intval($testi['rating']) : 5;
                                        $hasImage = !empty($testi['client_photo']) && file_exists($testi['client_photo']);
$firstLetter = strtoupper(substr(trim($testi['client_name']), 0, 1));
                                    ?>
                                        <div class="swiper-slide">
                                            <div class="google-review-card">
                                                <!-- Header with avatar, name, and Google icon -->
                                                <div class="reviewer-info">
    <?php if ($hasImage): ?>
        <img src="<?= htmlspecialchars($testi['client_photo']) ?>"
             alt="<?= htmlspecialchars($testi['client_name']) ?>"
             class="reviewer-avatar">
    <?php else: ?>
        <div class="reviewer-avatar avatar-placeholder">
            <?= htmlspecialchars($firstLetter) ?>
        </div>
    <?php endif; ?>

    <div class="reviewer-details">
        <h5 class="reviewer-name"><?= htmlspecialchars($testi['client_name']) ?></h5>
        <span class="reviewer-title">
            <?= htmlspecialchars($testi['client_title'] ?? 'Verified Patient') ?>
            <?php if (!empty($testi['client_company'])): ?>
                <span class="company-separator">•</span>
                <?= htmlspecialchars($testi['client_company']) ?>
            <?php endif; ?>
        </span>
    </div>
</div>

                                                <!-- Rating Stars -->
                                                <div class="rating-stars" aria-label="Rating: <?= $rating ?> out of 5 stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $rating ? 'active' : 'inactive' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="rating-text"><?= number_format($rating, 1) ?></span>
                                                </div>

                                                <!-- Review Content -->
                                                <p class="review-content">
                                                    “<?= htmlspecialchars($testi['testimonial_text']) ?>”
                                                </p>

                                                <!-- Project Info (Optional) -->
                                                <?php if (!empty($testi['project_name'])): ?>
                                                    <div class="project-info">
                                                        <i class="fas fa-tag"></i>
                                                        <span><?= htmlspecialchars($testi['project_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Footer with date -->
                                                
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                                <!-- Slider navigation (if needed) -->
                                <div class="swiper-button-next"><i class="fas fa-chevron-right"></i></div>
                                <div class="swiper-button-prev"><i class="fas fa-chevron-left"></i></div>
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
    <script>
        document.getElementById("appointmentForm").addEventListener("submit", function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const messageBox = document.getElementById("formMessage");
            const loader = document.querySelector(".loader");
            const btnText = document.querySelector(".btn-text");

            loader.classList.remove("d-none");
            btnText.textContent = "Sending...";

            fetch("util/appointment-handler.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    loader.classList.add("d-none");
                    btnText.textContent = "Make an Appointment";

                    if (data.status === "success") {
                        messageBox.innerHTML =
                            `<div class="alert alert-success">${data.message}</div>`;
                        form.reset();
                    } else {
                        messageBox.innerHTML =
                            `<div class="alert alert-danger">${data.message}</div>`;
                    }
                })
                .catch(() => {
                    loader.classList.add("d-none");
                    btnText.textContent = "Make an Appointment";
                    messageBox.innerHTML =
                        `<div class="alert alert-danger">Something went wrong.</div>`;
                });
        });


        // Initialize Swiper
        document.addEventListener('DOMContentLoaded', function() {
            const swiper = new Swiper('.testimonial-slider-1', {
                slidesPerView: 1,
                spaceBetween: 20,
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    640: {
                        slidesPerView: 1,
                        spaceBetween: 20,
                    },
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 25,
                    },
                    1024: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    },
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
            });
        });
    </script>

</body>

</html>