<?php
include_once "config/connect.php";
include_once "util/function.php";

// Fetch data for the page
$testimonials = testimonial();
$contact = contact_us();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="School Digital Health Program - Comprehensive health management for educational institutions">
    <title>REJUVENATE Digital Health - School Program</title>
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
    <?php include("header.php")?>

    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">School Digital Health Program</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>School Program</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero/Overview Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="pe-lg-5">
                        <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                            <i class="fas fa-graduation-cap me-2"></i>Education Health Initiative
                        </span>
                        <h2 class="display-5 fw-bold mb-4">Transforming School Health Management</h2>
                        <p class="lead text-muted mb-4">
                            REJUVENATE Digital Health brings comprehensive digital health solutions to schools, 
                            ensuring student wellness through innovative technology and streamlined healthcare management.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="#" class="btn btn-primary btn-lg">
                                <i class="fas fa-calendar-check me-2"></i>Get Started
                            </a>
                            <a href="#" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-play-circle me-2"></i>Watch Demo
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="<?= BASE_URL?>assets/img/school-program-hero.jpg" 
                             alt="School Health Program" 
                             class="img-fluid rounded-4 shadow-lg">
                        <div class="position-absolute bottom-0 end-0 bg-white p-3 rounded-3 shadow-sm m-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-school fs-3 text-success"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold">500+ Schools</h5>
                                    <small class="text-muted">Trusted nationwide</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section Start -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-6 col-lg-3">
                    <div class="p-4 bg-white rounded-3 shadow-sm">
                        <h3 class="display-4 fw-bold text-primary mb-2">10K+</h3>
                        <p class="text-muted mb-0">Students Enrolled</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-4 bg-white rounded-3 shadow-sm">
                        <h3 class="display-4 fw-bold text-success mb-2">95%</h3>
                        <p class="text-muted mb-0">Satisfaction Rate</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-4 bg-white rounded-3 shadow-sm">
                        <h3 class="display-4 fw-bold text-info mb-2">500+</h3>
                        <p class="text-muted mb-0">Partner Schools</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-4 bg-white rounded-3 shadow-sm">
                        <h3 class="display-4 fw-bold text-warning mb-2">24/7</h3>
                        <p class="text-muted mb-0">Health Support</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Program Features Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-star me-2"></i>Program Features
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Comprehensive Digital Health Solutions</h2>
                    <p class="text-muted">Empowering schools with cutting-edge health management tools</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow">
                        <div class="card-body p-4 text-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-id-card fs-1 text-primary"></i>
                            </div>
                            <h5 class="fw-bold">ABHA Integration</h5>
                            <p class="text-muted">Seamless integration with ABHA (Ayushman Bharat Health Account) for student health records</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow">
                        <div class="card-body p-4 text-center">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-heartbeat fs-1 text-success"></i>
                            </div>
                            <h5 class="fw-bold">Health Monitoring</h5>
                            <p class="text-muted">Real-time health monitoring and early warning systems for student wellness</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow">
                        <div class="card-body p-4 text-center">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-ambulance fs-1 text-info"></i>
                            </div>
                            <h5 class="fw-bold">Emergency Response</h5>
                            <p class="text-muted">Quick emergency response system with instant alert mechanisms</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow">
                        <div class="card-body p-4 text-center">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-file-medical fs-1 text-warning"></i>
                            </div>
                            <h5 class="fw-bold">Digital Records</h5>
                            <p class="text-muted">Secure digital health records accessible to authorized personnel</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow">
                        <div class="card-body p-4 text-center">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-hand-holding-heart fs-1 text-danger"></i>
                            </div>
                            <h5 class="fw-bold">Mental Health Support</h5>
                            <p class="text-muted">Counseling and mental health resources for students and staff</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow">
                        <div class="card-body p-4 text-center">
                            <div class="bg-purple bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-users fs-1 text-purple"></i>
                            </div>
                            <h5 class="fw-bold">Parent Portal</h5>
                            <p class="text-muted">Dedicated parent portal for monitoring child's health status</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section Start -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-cogs me-2"></i>How It Works
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Simple Implementation Process</h2>
                    <p class="text-muted">Get your school digital health program up and running in 4 easy steps</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 80px; height: 80px;">
                            <span class="text-white display-6 fw-bold">1</span>
                        </div>
                        <h5 class="fw-bold">Registration</h5>
                        <p class="text-muted small">Register your school in our digital health platform</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 80px; height: 80px;">
                            <span class="text-white display-6 fw-bold">2</span>
                        </div>
                        <h5 class="fw-bold">Student Onboarding</h5>
                        <p class="text-muted small">Add student profiles and create ABHA accounts</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-info rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 80px; height: 80px;">
                            <span class="text-white display-6 fw-bold">3</span>
                        </div>
                        <h5 class="fw-bold">Health Training</h5>
                        <p class="text-muted small">Train staff on using the digital health tools</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 80px; height: 80px;">
                            <span class="text-white display-6 fw-bold">4</span>
                        </div>
                        <h5 class="fw-bold">Go Live</h5>
                        <p class="text-muted small">Launch the program and start monitoring student health</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-4 bg-white rounded-3 shadow-sm text-center">
                                <i class="fas fa-shield-alt fs-1 text-primary mb-2"></i>
                                <h6 class="fw-bold">Data Security</h6>
                                <small class="text-muted">HIPAA compliant</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-4 bg-white rounded-3 shadow-sm text-center">
                                <i class="fas fa-mobile-alt fs-1 text-success mb-2"></i>
                                <h6 class="fw-bold">Mobile Access</h6>
                                <small class="text-muted">Access from anywhere</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-4 bg-white rounded-3 shadow-sm text-center">
                                <i class="fas fa-chart-line fs-1 text-info mb-2"></i>
                                <h6 class="fw-bold">Analytics</h6>
                                <small class="text-muted">Health insights</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-4 bg-white rounded-3 shadow-sm text-center">
                                <i class="fas fa-clock fs-1 text-warning mb-2"></i>
                                <h6 class="fw-bold">24/7 Support</h6>
                                <small class="text-muted">Always available</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-gem me-2"></i>Why Choose Us
                    </span>
                    <h2 class="display-6 fw-bold mb-4">Benefits for Your School</h2>
                    <ul class="list-unstyled">
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Improved Student Health Outcomes</h6>
                                <small class="text-muted">Early detection and intervention for better health</small>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Streamlined Health Management</h6>
                                <small class="text-muted">Digital records and automated processes</small>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Parent Satisfaction</h6>
                                <small class="text-muted">Transparent health monitoring for parents</small>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Compliance & Reporting</h6>
                                <small class="text-muted">Meet health regulations effortlessly</small>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section Start -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-comments me-2"></i>Testimonials
                    </span>
                    <h2 class="display-6 fw-bold mb-3">What School Administrators Say</h2>
                    <p class="text-muted">Real feedback from schools using our digital health program</p>
                </div>
            </div>
            <div class="row g-4">
                <?php 
                if (!empty($testimonials)) {
                    $school_testimonials = array_filter($testimonials, function($t) {
                        return strpos(strtolower($t['client_title'] ?? ''), 'school') !== false || 
                               strpos(strtolower($t['client_company'] ?? ''), 'school') !== false;
                    });
                    $testi_to_show = !empty($school_testimonials) ? $school_testimonials : $testimonials;
                    $testi_to_show = array_slice($testi_to_show, 0, 3);
                    
                    foreach ($testi_to_show as $testi) {
                        $rating = isset($testi['rating']) ? intval($testi['rating']) : 5;
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <img src="<?= !empty($testi['client_photo']) ? $testi['client_photo'] : 'assets/img/dummy.png' ?>" 
                                         alt="<?= htmlspecialchars($testi['client_name']) ?>" 
                                         class="rounded-circle" 
                                         style="width: 48px; height: 48px; object-fit: cover;">
                                    <div>
                                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($testi['client_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($testi['client_title'] ?? 'School Administrator') ?></small>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $rating ? 'text-warning' : 'text-secondary' ?>" style="font-size: 14px;"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="card-text fst-italic small">“<?= htmlspecialchars($testi['testimonial_text']) ?>”</p>
                            </div>
                        </div>
                    </div>
                <?php 
                    }
                } else { 
                ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No testimonials available yet.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <!-- CTA Section Start -->
    <section class="py-5 bg-primary">
        <div class="container">
            <div class="row g-4 align-items-center text-center text-lg-start">
                <div class="col-lg-8">
                    <h2 class="text-white display-6 fw-bold mb-3">Ready to Transform Your School Health Program?</h2>
                    <p class="text-white opacity-75 mb-0">Join 500+ schools already using our digital health solutions</p>
                </div>
                <div class="col-lg-4 text-center text-lg-end">
                    <a href="#" class="btn btn-light btn-lg px-5">
                        <i class="fas fa-arrow-right me-2"></i>Get Started
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-question-circle me-2"></i>FAQs
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Frequently Asked Questions</h2>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What is the School Digital Health Program?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    It's a comprehensive digital health management solution designed specifically for schools to monitor, track, and manage student health records using ABHA integration.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How does ABHA integration work for students?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Students can create ABHA accounts through our platform, allowing seamless health record management and access to government health schemes.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Is student data secure?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Yes, we follow strict data security protocols and comply with all health data protection regulations to ensure student privacy.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    What training is provided for school staff?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    We provide comprehensive training sessions, user manuals, and ongoing support to ensure smooth implementation and usage.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php")?>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>assets/js/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/swiper-bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>