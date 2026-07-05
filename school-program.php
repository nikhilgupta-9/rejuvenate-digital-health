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

    <!-- Health ID & Certificates Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-id-card me-2"></i>Digital Health ID &amp; Certificates
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Everything Your Child's Health Record Needs</h2>
                    <p class="text-muted">One digital profile — Health ID, vaccination status, deworming record and
                        blood group, always accessible to school and parents.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 border-top border-4 border-primary">
                        <div class="card-body p-4 text-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-id-badge fs-1 text-primary"></i>
                            </div>
                            <h5 class="fw-bold">Health ID Card</h5>
                            <p class="text-muted small mb-0">Photo ID with Student ID, class, DOB, gender, blood group,
                                address and emergency contact — verified by the school nurse.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 border-top border-4 border-success">
                        <div class="card-body p-4 text-center">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-syringe fs-1 text-success"></i>
                            </div>
                            <h5 class="fw-bold">Vaccination Certificate</h5>
                            <p class="text-muted small mb-0">Full immunization record — BCG, OPV, DPT, Hepatitis B, MMR,
                                Typhoid, Tetanus — verified by the Medical Officer.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #7c3aed !important;">
                        <div class="card-body p-4 text-center">
                            <div class="rounded-circle p-3 d-inline-block mb-3" style="background:rgba(124,58,237,.1);">
                                <i class="fas fa-pills fs-1" style="color:#7c3aed;"></i>
                            </div>
                            <h5 class="fw-bold">Deworming Certificate</h5>
                            <p class="text-muted small mb-0">Records Albendazole dosage, date administered and next
                                dose due, as per the school health program.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 border-top border-4 border-danger">
                        <div class="card-body p-4 text-center">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-tint fs-1 text-danger"></i>
                            </div>
                            <h5 class="fw-bold">Blood Group Certificate</h5>
                            <p class="text-muted small mb-0">Lab-verified blood group on record for every student —
                                critical information available instantly in an emergency.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Health Tips Section Start -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-success bg-opacity-10 text-success mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-leaf me-2"></i>Health Tips for Students
                    </span>
                    <h2 class="display-6 fw-bold mb-3">A Healthy Body, A Healthy Mind, A Happy Life</h2>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $health_tips = [
                    ['icon' => 'fa-apple-alt', 'color' => '#16a34a', 'title' => 'Eat Healthy', 'desc' => 'Eat balanced meals with fruits, vegetables, whole grains and proteins.'],
                    ['icon' => 'fa-tint', 'color' => '#0C74C5', 'title' => 'Drink Water', 'desc' => 'Drink plenty of water. It keeps you active and energized.'],
                    ['icon' => 'fa-running', 'color' => '#ef4444', 'title' => 'Be Active', 'desc' => 'Exercise daily. Play outdoor games and stay fit.'],
                    ['icon' => 'fa-hands-wash', 'color' => '#02c9b8', 'title' => 'Wash Hands', 'desc' => 'Wash hands with soap before eating and after using the toilet.'],
                    ['icon' => 'fa-moon', 'color' => '#1e3a8a', 'title' => 'Sleep Well', 'desc' => 'Get 8-10 hours of sleep daily for better growth and concentration.'],
                    ['icon' => 'fa-tooth', 'color' => '#db2777', 'title' => 'Keep Clean', 'desc' => 'Keep your body, clothes, hair and surroundings clean.'],
                    ['icon' => 'fa-shield-alt', 'color' => '#f59e0b', 'title' => 'Stay Safe', 'desc' => 'Follow safety rules. Say NO to junk food, tobacco and other harmful habits.'],
                ];
                foreach ($health_tips as $tip):
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card border-0 shadow-sm h-100 text-center">
                            <div class="card-body p-3">
                                <i class="fas <?= $tip['icon'] ?> fs-2 mb-2" style="color:<?= $tip['color'] ?>;"></i>
                                <h6 class="fw-bold mb-1"><?= $tip['title'] ?></h6>
                                <small class="text-muted"><?= $tip['desc'] ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- School Health Plans (Pricing) Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-shield-alt me-2"></i>School Health Plans
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Every Child. Every School. Every Future.</h2>
                    <p class="text-muted">Choose the plan that fits your school's needs — all prices are per student,
                        per year.</p>
                </div>
            </div>
            <div class="row g-4 align-items-stretch">
                <!-- Basic Plan -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="badge bg-success mb-3 align-self-start">Basic Health ID Plan</span>
                            <h4 class="fw-bold mb-0">Basic Plan</h4>
                            <div class="mb-3">
                                <span class="display-5 fw-bold text-success">₹49</span>
                                <span class="text-muted">/ student / year</span>
                            </div>
                            <ul class="list-unstyled mb-4 flex-grow-1">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Digital Health ID
                                    with Photo</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Basic Health
                                    Record (School Entry Profile)</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Vaccination
                                    Tracking</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Deworming
                                    Certificate</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Basic Health
                                    Tips (Hygiene + Nutrition PDF)</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Annual Basic
                                    Health Summary</li>
                            </ul>
                            <p class="text-success small fw-semibold mb-3">Perfect start for every child's health
                                journey.</p>
                            <a href="#contact" class="btn btn-outline-success mt-auto">Choose Basic</a>
                        </div>
                    </div>
                </div>

                <!-- Standard Plan -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow h-100" style="border-top:4px solid #f59e0b !important;">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="badge mb-3 align-self-start" style="background:#f59e0b;">Health Screening
                                &amp; Care Plan</span>
                            <h4 class="fw-bold mb-0">Standard Plan</h4>
                            <div class="mb-3">
                                <span class="display-5 fw-bold" style="color:#f59e0b;">₹199</span>
                                <span class="text-muted">/ student / year</span>
                            </div>
                            <ul class="list-unstyled mb-4 flex-grow-1">
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Includes
                                    all features of the ₹49 Plan</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Vision
                                    Screening Report</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Dental
                                    Check-up Report</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Height /
                                    Weight / BMI Tracking</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Hemoglobin
                                    / Anemia Screening</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Parent
                                    Alert System (Deficiency, Low BMI etc.)</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Basic
                                    Teleconsultation (Limited)</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color:#f59e0b;"></i>Nutrition
                                    Guidance Report</li>
                            </ul>
                            <p class="small fw-semibold mb-3" style="color:#f59e0b;">Early detection today, healthy
                                tomorrow.</p>
                            <a href="#contact" class="btn mt-auto text-white" style="background:#f59e0b;">Choose
                                Standard</a>
                        </div>
                    </div>
                </div>

                <!-- Premium Plan -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow h-100" style="border-top:4px solid #7c3aed !important;">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="badge mb-3 align-self-start" style="background:#7c3aed;">Complete Health
                                &amp; Wellness Plan</span>
                            <h4 class="fw-bold mb-0">Premium Plan</h4>
                            <div class="mb-3">
                                <span class="display-5 fw-bold" style="color:#7c3aed;">₹299</span>
                                <span class="text-muted">/ student / year</span>
                            </div>
                            <p class="small fw-semibold" style="color:#7c3aed;">Includes all features of the ₹199
                                Plan, plus:</p>
                            <ul class="list-unstyled mb-3 flex-grow-1" style="font-size:.88rem;">
                                <li class="fw-bold mt-2 mb-1">1. Mental &amp; Adolescent Health</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Stress &amp; Anxiety
                                    Assessment (Self Score)</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Sleep Tracking
                                    Guidance</li>
                                <li class="ms-3 mb-2"><i class="fas fa-check text-muted me-2"></i>Screen Time &amp;
                                    Digital Addiction Report</li>

                                <li class="fw-bold mt-2 mb-1">2. Advanced Medical Support</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Unlimited
                                    Teleconsultation (Basic Doctors)</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Priority Doctor
                                    Response</li>
                                <li class="ms-3 mb-2"><i class="fas fa-check text-muted me-2"></i>Follow-up
                                    Reminders</li>

                                <li class="fw-bold mt-2 mb-1">3. Nutrition &amp; Lifestyle Program</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Personalized Diet
                                    Chart (Age-Based)</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Anemia Prevention
                                    Program</li>
                                <li class="ms-3 mb-2"><i class="fas fa-check text-muted me-2"></i>Fitness &amp;
                                    Activity Tracking Guidance</li>

                                <li class="fw-bold mt-2 mb-1">4. School Health Intelligence</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>School-level Health
                                    Dashboard</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Class-wise Health
                                    Report Analytics</li>
                                <li class="ms-3 mb-2"><i class="fas fa-check text-muted me-2"></i>Risk Identification
                                    (Low BMI, Anemia Risk Group)</li>

                                <li class="fw-bold mt-2 mb-1">5. Career &amp; Wellness Guidance</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Stress Handling for
                                    Exams</li>
                                <li class="ms-3 mb-1"><i class="fas fa-check text-muted me-2"></i>Study-Life Balance
                                    Tips</li>
                                <li class="ms-3"><i class="fas fa-check text-muted me-2"></i>Career Awareness &amp;
                                    Health Link (Focus + Mental Clarity)</li>
                            </ul>
                            <p class="small fw-semibold mb-3" style="color:#7c3aed;">Healthy Body. Healthy Mind.
                                Bright Future.</p>
                            <a href="#contact" class="btn mt-auto text-white" style="background:#7c3aed;">Choose
                                Premium</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Plans for Different Age Groups Section Start -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-layer-group me-2"></i>Plans for Different Age Groups
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Age-Appropriate Health Focus</h2>
                </div>
            </div>
            <div class="row g-4">
                <!-- Age 6-8 -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <span class="badge bg-success mb-2">AGE GROUP 6-8 YEARS (CLASS 6 TO 8)</span>
                            <h5 class="fw-bold mb-3">Healthy Growth Plan for Students</h5>
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                <?php foreach (['Growth Monitoring', 'Hygiene & Clean Habits', 'Vision & Dental Care', 'Nutrition Support', 'Healthy Habits for a Strong Future'] as $focus): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2"><?= $focus ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr class="small text-muted">
                                            <th>Plan Benefits for Age 6-8</th>
                                            <th class="text-center">₹49<br>Basic</th>
                                            <th class="text-center">₹199<br>Standard</th>
                                            <th class="text-center">₹299<br>Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php
                                        $age68 = [
                                            ['Digital Health ID & Records', true, true, true],
                                            ['Growth Tracking (Height, Weight, BMI)', true, true, true],
                                            ['Vision & Dental Screening', false, true, true],
                                            ['Nutrition Guidance', true, true, true],
                                            ['Anemia Screening', false, true, true],
                                            ['Parent Alerts', false, true, true],
                                            ['Mental Wellness & Healthy Habits', false, true, true],
                                            ['Teleconsultation', false, true, true],
                                        ];
                                        foreach ($age68 as $row):
                                        ?>
                                            <tr>
                                                <td><?= $row[0] ?></td>
                                                <td class="text-center"><?= $row[1] ? '<i class="fas fa-check text-success"></i>' : '' ?></td>
                                                <td class="text-center"><?= $row[2] ? '<i class="fas fa-check text-success"></i>' : '' ?></td>
                                                <td class="text-center"><?= $row[3] ? '<i class="fas fa-check text-success"></i>' : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-success fw-semibold small mt-3 mb-0">Good habits today, strong children
                                tomorrow.</p>
                        </div>
                    </div>
                </div>

                <!-- Age 9-12 -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <span class="badge bg-primary mb-2">AGE GROUP 9-12 YEARS (CLASS 9 TO 12)</span>
                            <h5 class="fw-bold mb-3">Smart Health for Smart Future</h5>
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                <?php foreach (['Mental Health Support', 'Anemia & Nutrition Check', 'Stress & Sleep Management', 'Career Pressure Guidance', 'Healthy Lifestyle for Better Performance'] as $focus): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2"><?= $focus ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr class="small text-muted">
                                            <th>Plan Benefits for Age 9-12</th>
                                            <th class="text-center">₹49<br>Basic</th>
                                            <th class="text-center">₹199<br>Standard</th>
                                            <th class="text-center">₹299<br>Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php
                                        $age912 = [
                                            ['Digital Health ID & Records', true, true, true],
                                            ['Anemia Screening & Nutrition Support', true, true, true],
                                            ['Mental Health Assessment', false, true, true],
                                            ['Stress & Sleep Guidance', false, true, true],
                                            ['Lifestyle & Screen Time Report', false, true, true],
                                            ['Teleconsultation (Unlimited in Premium)', false, true, true],
                                            ['Career & Wellness Guidance', false, false, true],
                                            ['School Health Dashboard & Analytics', false, false, true],
                                        ];
                                        foreach ($age912 as $row):
                                        ?>
                                            <tr>
                                                <td><?= $row[0] ?></td>
                                                <td class="text-center"><?= $row[1] ? '<i class="fas fa-check text-success"></i>' : '' ?></td>
                                                <td class="text-center"><?= $row[2] ? '<i class="fas fa-check text-success"></i>' : '' ?></td>
                                                <td class="text-center"><?= $row[3] ? '<i class="fas fa-check text-success"></i>' : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-primary fw-semibold small mt-3 mb-0">Healthy mind. Strong body. Bright
                                future.</p>
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

    <!-- Why Choose Us Bar Section Start -->
    <section class="py-4" style="background:#0C2340;" id="contact">
        <div class="container">
            <div class="row align-items-center g-4 text-center text-lg-start">
                <div class="col-lg-3">
                    <h6 class="text-white fw-bold mb-0"><i class="fas fa-shield-check me-2 text-success"></i>Why
                        Choose REJUVENATE Digital Health?</h6>
                </div>
                <div class="col-lg-9">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3">
                            <i class="fas fa-school text-white-50 d-block mb-1"></i>
                            <small class="text-white">Trusted by Schools<br>Across India</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <i class="fas fa-id-card text-white-50 d-block mb-1"></i>
                            <small class="text-white">Digital Health<br>ID &amp; Records</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <i class="fas fa-user-md text-white-50 d-block mb-1"></i>
                            <small class="text-white">Expert Doctors &amp;<br>Health Professionals</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <i class="fas fa-lock text-white-50 d-block mb-1"></i>
                            <small class="text-white">Safe, Secure<br>&amp; Confidential</small>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="border-secondary my-3">
            <div class="row align-items-center text-center text-lg-start g-3">
                <div class="col-lg-3">
                    <a href="tel:+91-<?= $contact['phone'] ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-phone-alt me-1"></i> Contact Us Today!
                    </a>
                </div>
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap justify-content-center justify-content-lg-end gap-4">
                        <span class="text-white-50 small"><i class="fas fa-phone-alt me-1"></i>
                            +91-<?= htmlspecialchars($contact['phone'] ?? '') ?></span>
                        <span class="text-white-50 small"><i class="fas fa-envelope me-1"></i>
                            <?= htmlspecialchars($contact['email'] ?? '') ?></span>
                        <span class="text-white-50 small"><i class="fas fa-map-marker-alt me-1"></i>
                            <?= htmlspecialchars($contact['address'] ?? '') ?></span>
                    </div>
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