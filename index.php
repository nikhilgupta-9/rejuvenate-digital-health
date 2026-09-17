<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();

// echo 10 / 0;
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
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
    <section class="hero-section hero-1 bg-cover-hero fix"
        style="background-image: url('assets/img/home-1/hero/bg-01.jpg');">
        <div class="container">
            <div class="row g-4 align-items-center ">
                <div class="col-lg-7">
                    <div class="hero-content pt-4">
                        <h1><span class="banner-tags">Online Doctor Consultation</span> from the <br> comfort of your
                            home</h1>
                        <p>Doctor Consultation starts from <span class="tags">Rs 149/-</span></p>
                        <div class="search_input mt-4">
                            <form class="d-flex" id="heroSearchForm">
                                <input type="search" name="search" id="heroSearchInput"
                                    class="form-control cutom_search"
                                    placeholder="Search by department or problem, e.g. Cardiology, chest pain..."
                                    autocomplete="off">
                                <button type="submit" class="btn btn-search"><i class="far fa-search"></i></button>
                            </form>
                        </div>

                        <div class="hero-book-btn mt-3">
                            <a href="<?= BASE_URL ?>book-appointment/" class="theme-btn">
                                <i class="far fa-calendar-check me-1"></i> Book an Appointment
                            </a>
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
            <div class="section-title d-flex flex-wrap justify-content-between align-items-end">
                <div>
                    <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">MEET WITH DOCTOR</span>
                    <h2 class="service-text">Consult Doctor by Speciality</h2>
                    <p>Select speciality to find relevant doctors</p>
                </div>
                <a href="<?= BASE_URL ?>departments/" class=" p-2 btn-esanjeevni dept-view-all-btn">
                    View All Departments <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="row g-4 pb-0 advance-wrap">

                <?php
                $department = get_sub_category_home();
                foreach ($department as $dept) {
                    ?>
                    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                        <div class="team-box-items mt-0">
                            <a href="<?= BASE_URL ?>department/<?= htmlspecialchars($dept['slug_url']) ?>/">
                                <div class="team-image">
                                    <?php if (!empty($dept['sub_cat_img'])): ?>
                                        <img src="<?= BASE_URL ?>admin/uploads/sub-category/<?= htmlspecialchars($dept['sub_cat_img']) ?>"
                                            alt="<?= htmlspecialchars($dept['categories']) ?>" class="img-fluid">
                                    <?php else: ?>
                                        <div class="dept-icon-fallback"><i class="fas fa-stethoscope"></i></div>
                                    <?php endif; ?>
                                    <span class="post-box">
                                        <?= htmlspecialchars(trim($dept['categories'])) ?>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                    <?php
                }
                ?>

            </div>

            <div class="text-center mt-5 d-lg-none">
                <a href="<?= BASE_URL ?>departments/" class="text-center p-2 btn-esanjeevni ">
                    View All Departments <i class="fas fa-arrow-right"></i>
                </a>
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
    <section class="about-section-2 section-padding pb-4 fix mt-2">
        <div class="container">
            <div class="about-wrapper-2">

                <!-- Row 1: Title and Description -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="about-content">
                            <div class="section-title text-start mb-0">
                                <span class="subtitle tz-sub-tilte tz-sub-anim text-uppercase tx-subTitle">ABOUT
                                    US</span>
                                <h2 class="tx-title sec_title tz-itm-title tz-itm-anim">
                                    Transforming Healthcare Through Digital Innovation
                                </h2>
                            </div>
                            <p class="about-text">
                                <strong>Rejuvenate Digital Health</strong> is dedicated to transforming healthcare
                                through innovative digital solutions that make medical services more accessible, secure,
                                and convenient. Our mission is to empower individuals, families, healthcare providers,
                                and institutions with technology-driven healthcare services that improve overall
                                well-being.
                            </p>
                            <p class="about-text">
                                Our platform offers a comprehensive range of digital health services, including online
                                doctor consultations, digital health records, preventive healthcare programs, wellness
                                monitoring, and continuous patient support. We are committed to delivering a seamless
                                healthcare experience with a strong focus on quality, privacy, and patient care.
                            </p>
                            <p class="about-text">
                                As part of India's Digital Health Mission, our platform is integrated with the
                                <strong>Ayushman Bharat Health Account (ABHA)</strong> ecosystem, enabling users to
                                create and link their ABHA ID, securely manage digital health records, and experience
                                interoperable healthcare services across participating healthcare providers.
                            </p>
                            <p class="about-text">
                                We also proudly conduct our <strong><a href="<?= BASE_URL ?>school-program.php"
                                        style="text-decoration: underline;" class="text-primary">School Digital Health
                                        Program</a></strong>, helping
                                educational institutions promote preventive healthcare through digital health screening,
                                health awareness initiatives, wellness monitoring, and timely medical guidance for
                                students and staff.
                            </p>
                            <div class="why-text">
                                <h3>💡 Why Choose Us?</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Features Grid (3 columns on desktop, 2 on tablet, 1 on mobile) -->
                <div class="row g-4 mt-3">

                    <!-- Feature 1 -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon1.png" alt="Digital Health Services" class="img-fluid">
                                <h2>Comprehensive Digital Health Services</h2>
                                <p>Access a wide range of digital healthcare solutions, including online consultations,
                                    preventive care, health monitoring, and patient support—all from one secure
                                    platform.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon2.png" alt="ABHA Integration" class="img-fluid">
                                <h2>ABHA Integrated Platform</h2>
                                <p>Seamlessly create, link, and manage your ABHA account for secure digital health
                                    records and a connected healthcare experience.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon3.png" alt="Expert Healthcare Professionals" class="img-fluid">
                                <h2>Qualified Healthcare Professionals</h2>
                                <p>Connect with India's top verified doctors across 20+ specialties for trusted medical
                                    guidance. Are you a doctor? <a href="<?=BASE_URL?>doctor-signup/"
                                        style="color:inherit; font-weight:600; text-decoration:underline;">Join our
                                        network</a> and take your practice digital.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon4.png" alt="School Digital Health Program" class="img-fluid">
                                <h2>School Digital Health Program</h2>
                                <p>Empowering educational institutions with digital health screening, wellness
                                    monitoring, health awareness, and preventive healthcare initiatives.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 5 -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon5.png" alt="Secure Health Records" class="img-fluid">
                                <h2>Secure Digital Health Records</h2>
                                <p>Maintain and access your health records securely with advanced encryption and privacy
                                    standards whenever you need them.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 6 -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="about-box-items">
                            <div class="number-content">
                                <img src="assets/img/icon6.png" alt="Secure Platform" class="img-fluid">
                                <h2>Trusted, Secure & Patient-Centric</h2>
                                <p>Built with industry-standard security and designed around patient privacy,
                                    reliability, and a seamless digital healthcare experience.</p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Row 3: Bottom Text -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="why-text">
                            <h3>Transforming Healthcare with Technology</h3>
                            <p>
                                At <strong>Rejuvenate Digital Health</strong>, we are committed to making quality
                                healthcare
                                accessible through innovative digital solutions. Whether you're an individual, a family,
                                a
                                healthcare provider, or an educational institution, our platform delivers secure,
                                efficient,
                                and technology-driven healthcare services that support better health outcomes for
                                everyone.
                            </p>
                        </div>
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
            <!-- Section Title -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title text-center">
                        <span class="subtitle text-uppercase">OUR SERVICES</span>
                        <h2 class="tx-title sec_title">
                            Empowering Healthcare Through Digital Innovation
                        </h2>
                        <p>
                            Delivering secure, accessible, and technology-driven healthcare solutions for individuals,
                            families, educational institutions, and healthcare providers.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="row g-4 mt-3">

                <!-- Feature 1 -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="feature-treatment-items item_right_1 h-100">
                        <div class="feature-icon-box">
                            <h3>Digital Health Services</h3>
                            <i class="flaticon-heartbeat"></i>
                        </div>
                        <p>
                            Access online healthcare services, teleconsultations, digital health records, preventive
                            care, and wellness support from a single, secure platform.
                        </p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="feature-treatment-items h-100">
                        <div class="feature-icon-box">
                            <h3>ABHA Integrated Healthcare</h3>
                            <i class="flaticon-social-care"></i>
                        </div>
                        <p>
                            Create, link, and manage your ABHA account to securely access digital health records and
                            enable seamless healthcare across participating providers.
                        </p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="feature-treatment-items item_left_1 h-100">
                        <div class="feature-icon-box">
                            <h3>School Digital Health Program</h3>
                            <i class="flaticon-health-insurance-1"></i>
                        </div>
                        <p>
                            Promote student wellness through digital health screening, health awareness programs,
                            preventive care, and continuous health monitoring in schools.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!--  Appointment Section Start -->
    <style>
        .appt-slot {
            border: 1.5px solid rgba(255, 255, 255, .35);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            background: rgba(255, 255, 255, .08);
            transition: .15s;
        }

        .appt-slot:hover {
            border-color: #fff;
        }

        .appt-slot.selected {
            background: #fff;
            color: #0C74C5;
            border-color: #fff;
        }

        .appt-slot.booked {
            opacity: .35;
            text-decoration: line-through;
            cursor: not-allowed;
        }

        #apptConsentModal .modal-content {
            border-radius: 14px;
        }
    </style>

    <section class="appointment-section">
        <div class="container">
            <div class="appointment-wrapper">
                <div class="row g-2">
                    <div class="col-lg-8">
                        <div class="appointment-items">
                            <span class="subtitle text-uppercase"
                                style="color:#fff;opacity:.8;font-size:.72rem;letter-spacing:.1em;display:inline-block;margin-bottom:6px;">ABDM-Compliant
                                Digital Health Platform</span>
                            <h3>Book An Appointment</h3>
                            <p style="color:rgba(255,255,255,.82);font-size:14px;margin-top:6px;margin-bottom:16px;">
                                Send a quick request — our team confirms your slot with a verified doctor. ABHA linking
                                &amp; consent are captured at your appointment.
                            </p>

                            <form id="appointmentForm" novalidate>
                                <div class="row">

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <p class="text-light">Name</p>
                                            <input type="text" class="form-control" name="name" placeholder="Your name"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <p class="text-light">Email</p>
                                            <input type="email" class="form-control" name="email"
                                                placeholder="Your email" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <p class="text-light">Phone</p>
                                            <input type="tel" class="form-control" name="phone" id="apptPhoneInput"
                                                placeholder="Your phone" inputmode="numeric" maxlength="10"
                                                pattern="[6-9][0-9]{9}"
                                                title="Enter a valid 10-digit mobile number starting with 6-9"
                                                required>
                                            <small id="apptPhoneError"
                                                style="display:none;color:#ffd7d7;font-size:12px;margin-top:4px;"></small>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Department</p>
                                            <select class="form-control w-100" name="department" id="apptDepartment"
                                                required>
                                                <option value="">Your department</option>
                                                <?php
                                                $book_dep = get_sub_category();
                                                foreach ($book_dep as $dep) {
                                                    ?>
                                                    <option value="<?= htmlspecialchars(trim($dep['categories'])) ?>"
                                                        data-slug="<?= htmlspecialchars($dep['slug_url']) ?>">
                                                        <?= $dep['categories'] ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Preferred Doctor <span
                                                    style="opacity:.75;font-weight:400;font-size:12px;">(optional)</span>
                                            </p>
                                            <select class="form-control w-100" name="doctor_id" id="apptDoctorSelect"
                                                disabled>
                                                <option value="">Select department first</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-clt">
                                            <p>Consultation Type</p>
                                            <select class="form-control w-100" name="appointment_type">
                                                <option value="online">Online Consultation</option>
                                                <option value="clinic">In-Clinic Visit</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <p class="text-light">Date</p>
                                            <input type="date" class="form-control" name="date" id="apptDateInput"
                                                min="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6" id="apptTimeWrap">
                                        <div class="form-group">
                                            <p class="text-light">Time</p>
                                            <input type="time" class="form-control" name="time" id="apptTimeInput"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-xl-12 d-none" id="apptSlotsWrap">
                                        <div class="form-group">
                                            <p class="text-light">Available slots with <span
                                                    id="apptSlotsDoctorName"></span></p>
                                            <div id="apptSchedHint"
                                                style="color:rgba(255,255,255,.75);font-size:12.5px;margin-bottom:8px;">
                                            </div>
                                            <div id="apptSlotsGrid" class="d-flex flex-wrap gap-2"></div>
                                            <input type="hidden" name="time" id="apptSlotTimeInput" value="">
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

                                    <div class="col-xl-12 text-center mt-2">
                                        <a href="<?= BASE_URL ?>book-appointment/"
                                            style="color:#fff;font-size:13px;text-decoration:underline;opacity:.9;">
                                            Prefer to choose your doctor &amp; time slot yourself? Use our guided
                                            Booking Wizard <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>

                                </div>
                            </form>

                        </div>

                    </div>
                    <div class="col-lg-4">
                        <div class="appointment-image" style="position:relative;">
                            <img src="assets/img/home-1/appointment-img.jpg" alt="img">
                            <div
                                style="position:absolute;left:16px;right:16px;bottom:16px;background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 8px 24px rgba(0,0,0,.18);">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                    <span
                                        style="width:32px;height:32px;border-radius:8px;background:#e9f9f0;color:#009f4d;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;"><i
                                            class="fas fa-id-card"></i></span>
                                    <span style="font-size:13px;font-weight:600;color:#1f2937;">ABHA / ABDM
                                        Integrated</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                    <span
                                        style="width:32px;height:32px;border-radius:8px;background:#e9f2fe;color:#0C74C5;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;"><i
                                            class="fas fa-user-md"></i></span>
                                    <span style="font-size:13px;font-weight:600;color:#1f2937;">Verified &amp;
                                        HPR-Registered Doctors</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span
                                        style="width:32px;height:32px;border-radius:8px;background:#fdf2e9;color:#e07e18;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;"><i
                                            class="fas fa-shield-alt"></i></span>
                                    <span style="font-size:13px;font-weight:600;color:#1f2937;">Secure &amp;
                                        Confidential Records</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Consent popup — shown before an appointment request is finalized -->
    <div class="modal fade" id="apptConsentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-signature me-2" style="color:#0C74C5;"></i>Consent
                        Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div
                        style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;font-size:.85rem;color:#92400e;">
                        <label class="d-flex gap-2 mb-0" style="cursor:pointer;">
                            <input type="checkbox" id="apptConsentCheckbox" style="margin-top:3px;flex-shrink:0;">
                            <span>
                                I agree to the telemedicine consultation terms and to my health records being
                                created / linked / shared through ABHA/ABDM as per applicable guidelines, and confirm
                                my details are correct.
                                <a href="<?= BASE_URL ?>terms-and-condition/" target="_blank"
                                    class="text-danger">Terms &amp; Privacy Policy</a>.
                                <details class="mt-2">
                                    <summary style="cursor:pointer;color:#92400e;font-size:.78rem;">Read full consent
                                        (English / हिन्दी)</summary>
                                    <div style="margin-top:6px;">
                                        I voluntarily consent to receive medical consultation through Telemedicine
                                        (Video Call, Audio Call, Chat or Digital Platform), understand that the
                                        doctor's advice will be based on the information and documents provided by
                                        me, agree to the secure storage and management of my digital health records,
                                        consent to the creation, linking, updating and sharing of my health records
                                        through ABHA/ABDM as per applicable guidelines, and confirm that the
                                        information provided by me is true and correct.
                                    </div>
                                    <div style="margin-top:8px;">
                                        मैं स्वेच्छा से टेलीमेडिसिन (वीडियो कॉल, ऑडियो कॉल, चैट या डिजिटल प्लेटफॉर्म)
                                        के माध्यम से चिकित्सा परामर्श प्राप्त करने, यह समझने कि चिकित्सक की सलाह मेरे
                                        द्वारा प्रदान की गई जानकारी एवं दस्तावेजों के आधार पर होगी, अपने डिजिटल
                                        स्वास्थ्य रिकॉर्ड के सुरक्षित संग्रहण एवं प्रबंधन, लागू दिशानिर्देशों के
                                        अनुसार ABHA/ABDM के माध्यम से स्वास्थ्य रिकॉर्ड के निर्माण, लिंकिंग, अद्यतन
                                        एवं साझा किए जाने तथा मेरे द्वारा प्रदान की गई जानकारी के सही एवं सत्य होने
                                        की पुष्टि हेतु अपनी सहमति प्रदान करता/करती हूँ।
                                    </div>
                                </details>
                            </span>
                        </label>
                    </div>
                    <div id="apptFeeNotice" class="d-none mt-3"
                        style="background:#eaf4fd;border:1px solid #bcdcf5;border-radius:10px;padding:12px 14px;font-size:.85rem;color:#1f2937;">
                        <i class="fas fa-shield-alt me-1" style="color:#0C74C5;"></i>
                        Consultation fee of <b id="apptFeeAmount"></b> is payable securely via Razorpay (cards / UPI /
                        netbanking) on the next step, before your appointment is confirmed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn text-white" id="apptConsentAgreeBtn" style="background:#0C74C5;"
                        disabled>Agree &amp; Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonial Section5 Start -->
    <!-- Time Table Section Start -->
    <section class="time-table-section-2 section-padding pt-4">
        <div class="container">
            <div class="time-table-wrapper-2">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <div class="time-content sticky-style">
                            <div class="section-title mb-0 text-start">
                                <h2 class="service-text tx-title sec_title  tz-itm-title tz-itm-anim">It is Easy of Our
                                    Working Steps for You</h2>
                            </div>
                            <p class="time-text wow fadeInUp" data-wow-delay=".2s">Crafting compelling digital
                                experiences that captivate audiences and drive meaningful connections. Our digital
                                agency combines innovation, strategy, and expertise to fuel your online success.</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="time-table-right-items">
                            <div class="time-box-items mt-0 wow fadeInUp" data-wow-delay=".3s">
                                <div class="time-table-content">
                                    <h3>Discuses with Patient</h3>
                                    <p class="mt-2">In every business year of this company we have created successful
                                        ventures with amazing companies.</p>
                                </div>
                                <h2 class="time-number">01</h2>
                            </div>
                            <div class="time-box-items mb-0 wow fadeInUp" data-wow-delay=".5s">
                                <h2 class="time-number">02</h2>
                                <div class="time-table-content">
                                    <h3>Make for Appointment</h3>
                                    <p class="mt-2">In every business year of this company we have created successful
                                        ventures with amazing companies.</p>
                                </div>
                            </div>
                            <div class="time-box-items wow fadeInUp" data-wow-delay=".7s">
                                <div class="time-table-content">
                                    <h3>Start The Treatment</h3>
                                    <p class="mt-2">In every business year of this company we have created successful
                                        ventures with amazing companies.</p>
                                </div>
                                <h2 class="time-number">03</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonial-section-1 section-padding pb-0 bg-cover fix"
        style="background-image: url(assets/img/home-1/testimonial/bg.jpg);">

        <div class="container">
            <div class="testimonial-wrapper-1">
                <div class="row g-4">

                    <div class="col-lg-12">
                        <div class="section-title-area">
                            <div class="section-title">
                                <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">OUR
                                    TESTIMONIAL</span>
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
                                                        <h5 class="reviewer-name">
                                                            <?= htmlspecialchars($testi['client_name']) ?>
                                                        </h5>
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
                                                <div class="rating-stars"
                                                    aria-label="Rating: <?= $rating ?> out of 5 stars">
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
                                <span class="subtitle tz-sub-tilte tz-sub-anim  text-uppercase tx-subTitle">OUR
                                    FAQS</span>
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
                                                        data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                                                        aria-expanded="false" aria-controls="<?= $collapseId ?>">
                                                        <?= $faq['question'] ?>
                                                    </button>
                                                </h5>
                                                <div id="<?= $collapseId ?>" class="accordion-collapse collapse"
                                                    aria-labelledby="<?= $headingId ?>" data-bs-parent="#accordion">
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

    <?php $home_blogs = get_blog_home(); ?>
    <?php if (!empty($home_blogs)): ?>
    <section class="cta-section section-padding pb-4 fix">
        <div class="container">
            <div class="section-title mb-4 text-center">
                <span class="subtitle tz-sub-tilte tz-sub-anim text-uppercase tx-subTitle">OUR BLOG</span>
                <h2 class="service-text">Latest From Our Health Blog</h2>
                <p>Tips, guides and updates from our care team</p>
            </div>
            <div class="row g-4">
                <?php foreach ($home_blogs as $post): ?>
                    <?php $home_has_image = !empty($post['image']) && file_exists(__DIR__ . '/admin/uploads/blogs/' . $post['image']); ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="team-box-items mt-0">
                            <a href="<?= BASE_URL ?>blogs/<?= htmlspecialchars($post['slug_url']) ?>/">
                                <div class="team-image">
                                    <?php if ($home_has_image): ?>
                                        <img src="<?= BASE_URL ?>admin/uploads/blogs/<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                                    <?php else: ?>
                                        <div class="dept-icon-fallback" style="aspect-ratio:1.4/1;background:#f0f6fb;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-notes-medical" style="font-size:2.6rem;color:#0C74C5;opacity:.55;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($post['category'])): ?>
                                        <span class="post-box"><?= htmlspecialchars($post['category']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="pt-3">
                                <h5 class="mb-1"><a href="<?= BASE_URL ?>blogs/<?= htmlspecialchars($post['slug_url']) ?>/" class="text-dark"><?= htmlspecialchars($post['title']) ?></a></h5>
                                <small class="text-muted"><?= date('d M Y', strtotime($post['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="<?= BASE_URL ?>blogs/" class="theme-btn">
                    <i class="far fa-chevron-right"></i>
                    View All Articles
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include("footer.php") ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById("heroSearchForm").addEventListener("submit", function (e) {
            e.preventDefault();
            const q = document.getElementById("heroSearchInput").value.trim();
            window.location.href = "<?= BASE_URL ?>departments/" + (q ? "?search=" + encodeURIComponent(q) : "");
        });

        (function () {
            const form = document.getElementById("appointmentForm");
            const messageBox = document.getElementById("formMessage");
            const loader = document.querySelector(".loader");
            const btnText = document.querySelector(".btn-text");

            const deptSelect = document.getElementById("apptDepartment");
            const doctorSelect = document.getElementById("apptDoctorSelect");
            const dateInput = document.getElementById("apptDateInput");
            const timeWrap = document.getElementById("apptTimeWrap");
            const timeInput = document.getElementById("apptTimeInput");
            const slotsWrap = document.getElementById("apptSlotsWrap");
            const slotsGrid = document.getElementById("apptSlotsGrid");
            const slotsDoctorName = document.getElementById("apptSlotsDoctorName");
            const schedHint = document.getElementById("apptSchedHint");
            const slotTimeInput = document.getElementById("apptSlotTimeInput");

            const phoneInput = document.getElementById("apptPhoneInput");
            const phoneError = document.getElementById("apptPhoneError");

            // ── Real-time phone validation — strips non-digits as the user
            // types and flags an invalid number immediately, so a bad number
            // is caught here rather than surfacing only after payment.
            function validatePhone() {
                const digits = phoneInput.value.replace(/\D/g, '').slice(0, 10);
                if (phoneInput.value !== digits) phoneInput.value = digits;

                if (digits.length === 0) {
                    phoneInput.setCustomValidity('');
                    phoneError.style.display = 'none';
                    return false;
                }
                const valid = /^[6-9]\d{9}$/.test(digits);
                if (valid) {
                    phoneInput.setCustomValidity('');
                    phoneError.style.display = 'none';
                } else {
                    phoneInput.setCustomValidity('Enter a valid 10-digit mobile number starting with 6-9');
                    phoneError.textContent = digits.length < 10
                        ? 'Mobile number must be 10 digits.'
                        : 'Mobile number must start with 6-9.';
                    phoneError.style.display = 'block';
                }
                return valid;
            }
            phoneInput.addEventListener('input', validatePhone);
            phoneInput.addEventListener('blur', validatePhone);

            const consentModalEl = document.getElementById("apptConsentModal");
            const consentModal = new bootstrap.Modal(consentModalEl);
            const consentCheckbox = document.getElementById("apptConsentCheckbox");
            const consentAgreeBtn = document.getElementById("apptConsentAgreeBtn");
            const feeNotice = document.getElementById("apptFeeNotice");
            const feeAmount = document.getElementById("apptFeeAmount");

            let selectedDoctorFee = 0;
            let selectedDoctorName = '';
            let scheduleData = null;
            let pendingFormData = null;

            function escHtml(s) {
                if (!s) return '';
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function switchToManualTime() {
                timeWrap.classList.remove('d-none');
                timeInput.setAttribute('name', 'time');
                timeInput.setAttribute('required', 'required');
                slotsWrap.classList.add('d-none');
                slotTimeInput.removeAttribute('name');
                slotTimeInput.value = '';
            }

            function switchToSlotTime() {
                timeWrap.classList.add('d-none');
                timeInput.removeAttribute('name');
                timeInput.removeAttribute('required');
                slotsWrap.classList.remove('d-none');
                slotTimeInput.setAttribute('name', 'time');
            }

            function resetDoctorSelection() {
                selectedDoctorFee = 0;
                selectedDoctorName = '';
                scheduleData = null;
                switchToManualTime();
            }

            // ── Department change -> load doctors for that department ──
            deptSelect.addEventListener("change", function () {
                const opt = deptSelect.options[deptSelect.selectedIndex];
                const slug = opt ? opt.dataset.slug : '';
                resetDoctorSelection();

                if (!slug) {
                    doctorSelect.disabled = true;
                    doctorSelect.innerHTML = '<option value="">Select department first</option>';
                    return;
                }
                doctorSelect.disabled = true;
                doctorSelect.innerHTML = '<option value="">Loading doctors…</option>';

                fetch("util/get-doctors-by-department.php?department=" + encodeURIComponent(slug))
                    .then(r => r.json())
                    .then(data => {
                        doctorSelect.innerHTML = '<option value="">Any Available Doctor</option>';
                        if (data.success && data.doctors.length) {
                            data.doctors.forEach(d => {
                                const o = document.createElement('option');
                                o.value = d.id;
                                o.textContent = 'Dr. ' + d.name + (d.consultation_fee > 0 ? ' — ₹' + Number(d.consultation_fee).toLocaleString('en-IN') : ' — Free');
                                o.dataset.fee = d.consultation_fee || 0;
                                o.dataset.name = d.name;
                                doctorSelect.appendChild(o);
                            });
                        }
                        doctorSelect.disabled = false;
                    })
                    .catch(() => {
                        doctorSelect.innerHTML = '<option value="">Any Available Doctor</option>';
                        doctorSelect.disabled = false;
                    });
            });

            // ── Doctor change -> load that doctor's schedule + slots ──
            doctorSelect.addEventListener("change", function () {
                const opt = doctorSelect.options[doctorSelect.selectedIndex];
                if (!doctorSelect.value) {
                    resetDoctorSelection();
                    return;
                }
                selectedDoctorFee = Number(opt.dataset.fee || 0);
                selectedDoctorName = opt.dataset.name || '';
                slotsDoctorName.textContent = 'Dr. ' + selectedDoctorName;
                switchToSlotTime();
                loadSchedule();
            });

            function loadSchedule() {
                schedHint.textContent = 'Loading schedule…';
                slotsGrid.innerHTML = '';
                slotTimeInput.value = '';

                fetch("util/get-doctor-schedule.php?doctor_id=" + doctorSelect.value)
                    .then(r => r.json())
                    .then(data => {
                        scheduleData = data;
                        schedHint.textContent = (data.success && data.summary) ? data.summary : '';
                        if (data.success && data.first_available && !dateInput.value) {
                            dateInput.value = data.first_available;
                        }
                        if (dateInput.value) loadSlots();
                    })
                    .catch(() => { schedHint.textContent = ''; });
            }

            dateInput.addEventListener("change", function () {
                if (doctorSelect.value) loadSlots();
            });

            function loadSlots() {
                const date = dateInput.value;
                if (!date || !doctorSelect.value) return;

                slotsGrid.innerHTML = '<div class="text-center py-2 w-100"><div class="spinner-border spinner-border-sm text-light"></div></div>';
                slotTimeInput.value = '';

                fetch(`util/get-available-slots.php?doctor_id=${doctorSelect.value}&date=${date}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success || !data.slots.length) {
                            let why = 'No slots available for this date. Try another day.';
                            if (scheduleData && scheduleData.success) {
                                const dow = new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                                const wd = scheduleData.week && scheduleData.week[dow];
                                if (wd && !wd.available) why = `Dr. ${escHtml(selectedDoctorName)} doesn't consult on ${dow}s.`;
                            }
                            slotsGrid.innerHTML = `<div style="color:rgba(255,255,255,.75);font-size:.82rem;">${why}</div>`;
                            return;
                        }
                        slotsGrid.innerHTML = data.slots.map(s => `
                            <div class="appt-slot ${s.booked ? 'booked' : ''}" data-time="${s.time}">${s.display}</div>
                        `).join('');
                        slotsGrid.querySelectorAll('.appt-slot:not(.booked)').forEach(el => {
                            el.addEventListener('click', () => {
                                slotsGrid.querySelectorAll('.appt-slot').forEach(s => s.classList.remove('selected'));
                                el.classList.add('selected');
                                slotTimeInput.value = el.dataset.time;
                            });
                        });
                    })
                    .catch(() => {
                        slotsGrid.innerHTML = '<div style="color:rgba(255,255,255,.75);font-size:.82rem;">Could not load slots.</div>';
                    });
            }

            // ── Consent popup ──
            consentCheckbox.addEventListener('change', function () {
                consentAgreeBtn.disabled = !this.checked;
            });

            form.addEventListener("submit", function (e) {
                e.preventDefault();
                messageBox.innerHTML = '';

                validatePhone();
                if (!form.reportValidity()) return;
                if (doctorSelect.value && !slotTimeInput.value) {
                    messageBox.innerHTML = '<div class="alert alert-danger">Please choose an available time slot.</div>';
                    return;
                }

                pendingFormData = new FormData(form);
                consentCheckbox.checked = false;
                consentAgreeBtn.disabled = true;

                if (doctorSelect.value && selectedDoctorFee > 0) {
                    feeAmount.textContent = '₹' + selectedDoctorFee.toLocaleString('en-IN');
                    feeNotice.classList.remove('d-none');
                } else {
                    feeNotice.classList.add('d-none');
                }
                consentModal.show();
            });

            consentAgreeBtn.addEventListener('click', function () {
                if (!consentCheckbox.checked || !pendingFormData) return;
                consentModal.hide();
                pendingFormData.set('consent_required', '1');
                pendingFormData.set('consent_given', '1');
                proceedToPaymentOrSubmit(pendingFormData);
            });

            // ── Payment (Razorpay) when a specific paid doctor was chosen, then finalize ──
            function proceedToPaymentOrSubmit(formData) {
                if (!doctorSelect.value || !selectedDoctorFee) {
                    finalizeBooking(formData);
                    return;
                }

                loader.classList.remove("d-none");
                btnText.textContent = "Preparing payment...";

                const orderData = new FormData();
                orderData.append('doctor_id', doctorSelect.value);

                fetch('util/create-razorpay-order.php', { method: 'POST', body: orderData })
                    .then(r => r.json())
                    .then(order => {
                        loader.classList.add("d-none");
                        btnText.textContent = "Make an Appointment";

                        if (!order.success) {
                            messageBox.innerHTML = `<div class="alert alert-danger">${order.message || 'Could not start the payment. Please try again.'}</div>`;
                            return;
                        }
                        if (!order.payment_required) {
                            finalizeBooking(formData);
                            return;
                        }

                        const rzp = new Razorpay({
                            key: order.key_id,
                            order_id: order.order_id,
                            amount: order.amount,
                            currency: order.currency,
                            name: 'Rejuvenate Digital Health',
                            description: 'Consultation with Dr. ' + (order.doctor_name || selectedDoctorName),
                            prefill: {
                                name: formData.get('name') || '',
                                email: formData.get('email') || '',
                                contact: formData.get('phone') || '',
                            },
                            theme: { color: '#0C74C5' },
                            handler: function (response) {
                                formData.append('razorpay_order_id', response.razorpay_order_id);
                                formData.append('razorpay_payment_id', response.razorpay_payment_id);
                                formData.append('razorpay_signature', response.razorpay_signature);
                                finalizeBooking(formData);
                            },
                            modal: {
                                ondismiss: function () {
                                    messageBox.innerHTML = '<div class="alert alert-danger">Payment was cancelled. Your appointment was not booked.</div>';
                                },
                            },
                        });
                        rzp.on('payment.failed', function () {
                            messageBox.innerHTML = '<div class="alert alert-danger">Payment failed. Please try again.</div>';
                        });
                        rzp.open();
                    })
                    .catch(() => {
                        loader.classList.add("d-none");
                        btnText.textContent = "Make an Appointment";
                        messageBox.innerHTML = '<div class="alert alert-danger">Network error while starting payment. Please try again.</div>';
                    });
            }

            function finalizeBooking(formData) {
                loader.classList.remove("d-none");
                btnText.textContent = "Booking...";

                fetch("util/appointment-handler.php", { method: "POST", body: formData })
                    .then(res => res.json())
                    .then(data => {
                        loader.classList.add("d-none");
                        btnText.textContent = "Make an Appointment";

                        if (data.status === "success") {
                            messageBox.innerHTML =
                                `<div class="alert alert-success">${data.message} Your reference: <b>${data.appointment_id}</b></div>`;
                            form.reset();
                            resetDoctorSelection();
                            doctorSelect.innerHTML = '<option value="">Select department first</option>';
                            doctorSelect.disabled = true;
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
            }
        })();


        // Initialize Swiper
        document.addEventListener('DOMContentLoaded', function () {
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