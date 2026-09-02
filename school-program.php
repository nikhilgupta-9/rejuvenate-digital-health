<?php
include_once "config/connect.php";
include_once "util/function.php";

// Fetch data for the page
$testimonials = testimonial();
$contact = contact_us();

/* ── School health plans (managed in Admin → School Health → Health Plans & Pricing) ── */
$sp_plans = [];
$sp_res = $conn->query("SELECT * FROM school_health_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC, id ASC");
if ($sp_res) {
    while ($r = $sp_res->fetch_assoc()) {
        $r['feature_list'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $r['features']))));
        $sp_plans[] = $r;
    }
}
function sp_age_label($min, $max): string
{
    $min = ($min === null || $min === '') ? null : (int) $min;
    $max = ($max === null || $max === '') ? null : (int) $max;
    if ($min === null && $max === null) return 'All ages';
    if ($min !== null && $max !== null) return "Age {$min}–{$max}";
    if ($min !== null) return "Age {$min}+";
    return "Up to age {$max}";
}

/* ── "Partner with us" enquiry → saved to `inquiries` (Admin → New Leads) ── */
$sp_lead_ok = false;
$sp_lead_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_enquiry'])) {
    $sp_name = trim($_POST['sp_name'] ?? '');
    $sp_email = trim($_POST['sp_email'] ?? '');
    $sp_phone = preg_replace('/\D/', '', $_POST['sp_phone'] ?? '');
    $sp_school = trim($_POST['sp_school'] ?? '');
    $sp_city = trim($_POST['sp_city'] ?? '');
    $sp_plan = trim($_POST['sp_plan'] ?? '');
    $sp_msg = trim($_POST['sp_message'] ?? '');

    if ($sp_name === '' || !filter_var($sp_email, FILTER_VALIDATE_EMAIL) || strlen($sp_phone) < 10) {
        $sp_lead_err = 'Please enter your name, a valid email and a 10-digit phone number.';
    } else {
        $subject = 'School Program Enquiry' . ($sp_school !== '' ? ' — ' . $sp_school : '');
        $body = "School: " . ($sp_school ?: '—')
            . "\nCity: " . ($sp_city ?: '—')
            . "\nInterested plan: " . ($sp_plan ?: '—')
            . "\n\n" . ($sp_msg ?: '(no message)');
        $phone11 = substr($sp_phone, 0, 11);
        $st = $conn->prepare("INSERT INTO inquiries (name, email, phone, subject, message) VALUES (?,?,?,?,?)");
        $st->bind_param('sssss', $sp_name, $sp_email, $phone11, $subject, $body);
        $sp_lead_ok = $st->execute();
        if (!$sp_lead_ok) {
            $sp_lead_err = 'Something went wrong. Please try again or call us directly.';
        }
        $st->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description"
        content="School Digital Health Program - Comprehensive health management for educational institutions">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* ── Align this landing page to the brand palette (#0C74C5 / #02c9b8) ── */
        .school-program-page {
            --sp-blue: #0c74c5;
            --sp-blue-dk: #0a5fa0;
            --sp-teal: #02c9b8;
            --sp-ink: #051828;
        }

        .school-program-page .text-primary {
            color: var(--sp-blue) !important;
        }

        .school-program-page .bg-primary {
            background-color: var(--sp-blue) !important;
        }

        .school-program-page .border-primary {
            border-color: var(--sp-blue) !important;
        }

        .school-program-page .text-success,
        .school-program-page .text-info {
            color: var(--sp-teal) !important;
        }

        .school-program-page .bg-success,
        .school-program-page .bg-info {
            background-color: var(--sp-teal) !important;
        }

        .school-program-page .text-warning {
            color: var(--sp-blue) !important;
        }

        .school-program-page .bg-warning {
            background-color: var(--sp-blue) !important;
        }

        .school-program-page .btn-outline-success {
            color: var(--sp-teal) !important;
            border-color: var(--sp-teal) !important;
        }

        .school-program-page .btn-outline-success:hover {
            background-color: var(--sp-teal) !important;
            color: #fff !important;
        }

        /* Pricing/plan colours now come from the DB (accent_color) per card. */
        .school-program-page [style*="#0C2340"] {
            background: var(--sp-ink) !important;
        }

        /* Anchor jump for the plans section clears the sticky site header */
        #plans { scroll-margin-top: 90px; }

        /* Plan cards */
        .school-program-page .sp-plan-card {
            transition: transform .18s ease, box-shadow .18s ease;
            overflow: visible;
            margin-top: 14px;
        }
        .school-program-page .sp-plan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(12, 116, 197, .15) !important;
        }
        .school-program-page .sp-plan-card.sp-plan-match {
            box-shadow: 0 0 0 2px var(--sp-teal), 0 12px 30px rgba(2, 201, 184, .18) !important;
        }
        #spAgeResult:empty {
            display: none;
        }

        /* Enquiry form */
        .sp-enquiry-card {
            background: #fff;
            border: 1px solid #e5e9f0;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(12, 116, 197, .08);
            padding: 28px;
        }

        .sp-enquiry-card .form-control,
        .sp-enquiry-card .form-select {
            border-radius: 10px;
        }

        @media (max-width: 575px) {
            .sp-enquiry-card {
                padding: 18px;
            }
        }
    </style>
</head>

<body class="school-program-page">
    <?php include("header.php") ?>

    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover"
        style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
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
                        <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
                            <i class="fas fa-graduation-cap me-2"></i>Education Health Initiative
                        </span>

                        <h2 class="display-5 fw-bold mb-4">Transforming School Health Management</h2>

                        <p class="lead text-muted mb-4">
                            REJUVENATE Digital Health brings comprehensive digital health solutions to schools,
                            ensuring student wellness through innovative technology and streamlined healthcare
                            management.
                        </p>

                        <div class="d-flex flex-wrap gap-3">
                            <!-- Get Started: dropdown with the 3 registration types -->
                            <div class="dropdown">
                                <button class="btn btn-primary btn-lg dropdown-toggle" type="button"
                                    id="getStartedDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-calendar-check me-2"></i>Get Started
                                </button>
                                <ul class="dropdown-menu shadow" aria-labelledby="getStartedDropdown">
                                    <li>
                                        <a class="dropdown-item py-2" href="<?= BASE_URL ?>school-register.php">
                                            <i class="fas fa-school me-2 text-primary"></i>School Registration
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item py-2" href="<?= BASE_URL ?>student-register.php">
                                            <i class="fas fa-user-graduate me-2 text-primary"></i>Student Registration
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item py-2" href="<?= BASE_URL ?>teacher-register.php">
                                            <i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Teacher
                                            Registration
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <a href="demo.php" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-play-circle me-2"></i>Watch Demo
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="<?= BASE_URL ?>assets/img/school-plan.jpeg" alt="School Health Program"
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
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
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
                                <i class="fas fa-id-badge fs-1 text-light"></i>
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
                                <i class="fas fa-syringe fs-1 text-light"></i>
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
                    <span class="badge bg-success bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
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
    <section class="py-5" id="plans">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-shield-alt me-2"></i>School Health Plans
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Every Child. Every School. Every Future.</h2>
                    <p class="text-muted">Choose the plan that fits your child — all prices are per student, per year.
                        When a parent submits the consent form, the plan is picked automatically from the child's age.</p>
                </div>
            </div>

            <?php if (empty($sp_plans)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-layer-group fs-1 opacity-25 d-block mb-2"></i>
                    Plans will appear here once they are added in the admin panel.
                </div>
            <?php else: ?>
                <!-- Age finder -->
                <div class="row justify-content-center mb-4">
                    <div class="col-lg-7">
                        <div class="p-3 p-md-4 bg-white rounded-4 shadow-sm border">
                            <label class="fw-semibold mb-2 d-block"><i class="fas fa-wand-magic-sparkles me-2 text-primary"></i>Find your child's plan</label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="text-muted small">My child is</span>
                                <input type="number" id="spAgeInput" class="form-control" min="1" max="25" style="width:90px"
                                    placeholder="age" inputmode="numeric">
                                <span class="text-muted small">years old</span>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="spAgeClear" hidden>
                                    <i class="fas fa-xmark me-1"></i>Show all
                                </button>
                            </div>
                            <div id="spAgeResult" class="mt-2 small"></div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 align-items-stretch justify-content-center" id="spPlanGrid">
                    <?php foreach ($sp_plans as $p):
                        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $p['accent_color'] ?? '') ? $p['accent_color'] : '#0C74C5';
                        ?>
                        <div class="col-lg-4 col-md-6 sp-plan-col"
                            data-plan-id="<?= (int) $p['id'] ?>"
                            data-age-min="<?= $p['age_min'] === null ? '' : (int) $p['age_min'] ?>"
                            data-age-max="<?= $p['age_max'] === null ? '' : (int) $p['age_max'] ?>">
                            <div class="card border-0 shadow-sm h-100 sp-plan-card position-relative"
                                style="border-top:4px solid <?= $accent ?> !important;">
                                <?php if (!empty($p['is_popular'])): ?>
                                    <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill px-3 py-2"
                                        style="background:<?= $accent ?>;">
                                        <i class="fas fa-star me-1"></i>Most Popular
                                    </span>
                                <?php endif; ?>
                                <div class="card-body p-4 d-flex flex-column">
                                    <?php if (!empty($p['tier'])): ?>
                                        <span class="badge mb-3 align-self-start" style="background:<?= $accent ?>1a;color:<?= $accent ?>;">
                                            <?= htmlspecialchars($p['tier']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <h4 class="fw-bold mb-0"><?= htmlspecialchars($p['name']) ?></h4>
                                    <div class="mb-1">
                                        <span class="display-5 fw-bold" style="color:<?= $accent ?>;">&#8377;<?= number_format((float) $p['price']) ?></span>
                                        <span class="text-muted">/ <?= htmlspecialchars($p['billing_label']) ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <span class="badge bg-light text-dark border"><i class="fas fa-child me-1" style="color:<?= $accent ?>"></i><?= htmlspecialchars(sp_age_label($p['age_min'], $p['age_max'])) ?></span>
                                    </div>
                                    <?php if (!empty($p['tagline'])): ?>
                                        <p class="small fw-semibold mb-3" style="color:<?= $accent ?>;"><?= htmlspecialchars($p['tagline']) ?></p>
                                    <?php endif; ?>
                                    <ul class="list-unstyled mb-4 flex-grow-1">
                                        <?php foreach ($p['feature_list'] as $f): ?>
                                            <li class="mb-2 d-flex"><i class="fas fa-check-circle me-2 mt-1" style="color:<?= $accent ?>"></i><span><?= htmlspecialchars($f) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a href="<?= BASE_URL ?>school/parent-consent.php?plan=<?= (int) $p['id'] ?>"
                                        class="btn mt-auto text-white fw-semibold" style="background:<?= $accent ?>;">
                                        Choose <?= htmlspecialchars($p['name']) ?><i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="text-center text-muted small mt-4 mb-0">
                    <i class="fas fa-lock me-1"></i>Secure payment via Razorpay &nbsp;·&nbsp; parent &amp; admin get an email confirmation
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works Section Start -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
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
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
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

    <!-- Partner / Enquiry Section Start -->
    <section class="py-5 bg-light" id="contact">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-handshake me-2"></i>Partner With Us
                    </span>
                    <h2 class="display-6 fw-bold mb-3">Bring the Health Program to Your School</h2>
                    <p class="text-muted mb-4">Share a few details and our school-programs team will get in touch within
                        one working day to walk you through onboarding, plans and pricing.</p>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Free onboarding &amp;
                            staff training</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>ABHA-linked digital health
                            records</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>No setup cost — pay per
                            student / year</li>
                    </ul>
                    <?php if (!empty($contact['phone'])): ?>
                        <a href="tel:+91-<?= htmlspecialchars($contact['phone']) ?>" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-phone-alt me-2"></i>+91-<?= htmlspecialchars($contact['phone']) ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-lg-7">
                    <div class="sp-enquiry-card">
                        <?php if ($sp_lead_ok): ?>
                            <div class="text-center py-4">
                                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width:64px;height:64px;">
                                    <i class="fas fa-check fs-3 text-success"></i>
                                </div>
                                <h4 class="fw-bold mb-1">Thank you!</h4>
                                <p class="text-muted mb-0">Your enquiry has reached our school-programs team. We'll contact
                                    you shortly.</p>
                            </div>
                        <?php else: ?>
                            <h4 class="fw-bold mb-3">Request a Callback</h4>
                            <?php if ($sp_lead_err): ?>
                                <div class="alert alert-danger py-2 px-3"><i
                                        class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($sp_lead_err) ?></div>
                            <?php endif; ?>
                            <form method="POST" action="#contact" class="row g-3">
                                <input type="hidden" name="sp_enquiry" value="1">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Your Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="sp_name" class="form-control" required
                                        value="<?= htmlspecialchars($_POST['sp_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">School Name</label>
                                    <input type="text" name="sp_school" class="form-control"
                                        value="<?= htmlspecialchars($_POST['sp_school'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Email <span
                                            class="text-danger">*</span></label>
                                    <input type="email" name="sp_email" class="form-control" required
                                        value="<?= htmlspecialchars($_POST['sp_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Phone <span
                                            class="text-danger">*</span></label>
                                    <input type="tel" name="sp_phone" class="form-control" required maxlength="10"
                                        inputmode="numeric" value="<?= htmlspecialchars($_POST['sp_phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">City</label>
                                    <input type="text" name="sp_city" class="form-control"
                                        value="<?= htmlspecialchars($_POST['sp_city'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Interested Plan</label>
                                    <select name="sp_plan" class="form-select">
                                        <?php $planOpt = $_POST['sp_plan'] ?? '';
                                        foreach (['', 'Basic Plan', 'Standard Plan', 'Premium Plan', 'Not sure yet'] as $po): ?>
                                            <option value="<?= htmlspecialchars($po) ?>" <?= $planOpt === $po ? 'selected' : '' ?>>
                                                <?= $po === '' ? '— Select —' : htmlspecialchars($po) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Message</label>
                                    <textarea name="sp_message" class="form-control" rows="3"
                                        placeholder="Approx. number of students, preferred start date, questions…"><?= htmlspecialchars($_POST['sp_message'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary px-4"><i
                                            class="fas fa-paper-plane me-2"></i>Submit Enquiry</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section Start -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
                        <i class="fas fa-shield-alt me-2"></i>Why Choose REJUVENATE Digital Health?
                    </span>
                    <h2 class="display-6 fw-bold mb-3">A Health Partner Schools Can Trust</h2>
                    <p class="text-muted">Built for Indian schools — ABHA-linked health records, verified medical
                        professionals and complete data privacy.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-school fs-2 text-light"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Trusted by Schools</h6>
                            <p class="text-muted small mb-0">500+ institutions across India</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-id-card fs-2 text-light"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Digital Health ID &amp; Records</h6>
                            <p class="text-muted small mb-0">Every visit stored as an ABHA care context</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-user-md fs-2 text-light"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Expert Doctors</h6>
                            <p class="text-muted small mb-0">HPR-verified health professionals</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="fas fa-lock fs-2 text-light"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Safe &amp; Confidential</h6>
                            <p class="text-muted small mb-0">Consent-based access, fully audited</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($contact['phone']) || !empty($contact['email']) || !empty($contact['address'])): ?>
                <div class="text-center mt-5">
                    <a href="tel:+91-<?= htmlspecialchars($contact['phone'] ?? '') ?>"
                        class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-phone-alt me-2"></i>Contact Us Today
                    </a>
                    <div class="d-flex flex-wrap justify-content-center gap-4 mt-3 text-muted small">
                        <?php if (!empty($contact['phone'])): ?>
                            <span><i class="fas fa-phone-alt me-2 text-primary"></i>+91-<?= htmlspecialchars($contact['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($contact['email'])): ?>
                            <span><i class="fas fa-envelope me-2 text-primary"></i><?= htmlspecialchars($contact['email']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($contact['address'])): ?>
                            <span><i class="fas fa-map-marker-alt me-2 text-primary"></i><?= htmlspecialchars($contact['address']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section Start -->
    <section class="py-5 bg-primary">
        <div class="container">
            <div class="row g-4 align-items-center justify-content-between text-center text-lg-start">
                <div class="col-lg-7">
                    <h2 class="text-white display-6 fw-bold mb-2">Ready to Transform Your School Health Program?</h2>
                    <p class="text-white-50 mb-0">Join 500+ schools already using our digital health solutions.</p>
                </div>
                <div class="col-lg-4 text-center text-lg-end">
                    <a href="#contact" class="btn btn-light btn-lg px-4 fw-semibold">
                        Get Started<i class="fas fa-arrow-right ms-2"></i>
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
                    <span class="badge bg-primary bg-opacity-10 text-light mb-3 px-3 py-2 fs-6 fw-semibold">
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
                                <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq1">
                                    What is the School Digital Health Program?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    It's a comprehensive digital health management solution designed specifically for
                                    schools to monitor, track, and manage student health records using ABHA integration.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How does ABHA integration work for students?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Students can create ABHA accounts through our platform, allowing seamless health
                                    record management and access to government health schemes.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Is student data secure?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Yes, we follow strict data security protocols and comply with all health data
                                    protection regulations to ensure student privacy.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faq4">
                                    What training is provided for school staff?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    We provide comprehensive training sessions, user manuals, and ongoing support to
                                    ensure smooth implementation and usage.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>assets/js/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/swiper-bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>

    <script>
        /* ── Age finder: highlight the plan band matching the child's age ── */
        (function () {
            const input = document.getElementById('spAgeInput');
            if (!input) return;
            const clearBtn = document.getElementById('spAgeClear');
            const result = document.getElementById('spAgeResult');
            const cols = Array.from(document.querySelectorAll('.sp-plan-col'));

            function apply() {
                const age = parseInt(input.value, 10);
                if (!input.value || isNaN(age)) { reset(); return; }
                let matched = 0, matchName = '';
                cols.forEach(col => {
                    const min = col.dataset.ageMin === '' ? null : parseInt(col.dataset.ageMin, 10);
                    const max = col.dataset.ageMax === '' ? null : parseInt(col.dataset.ageMax, 10);
                    const ok = (min === null || age >= min) && (max === null || age <= max);
                    col.style.display = ok ? '' : 'none';
                    col.querySelector('.sp-plan-card')?.classList.toggle('sp-plan-match', ok);
                    if (ok) { matched++; if (!matchName) matchName = col.querySelector('h4')?.textContent.trim() || ''; }
                });
                clearBtn.hidden = false;
                result.innerHTML = matched
                    ? '<span class="text-success"><i class="fas fa-circle-check me-1"></i>Recommended for age ' + age + ': <strong>' + matchName + '</strong>' + (matched > 1 ? ' and ' + (matched - 1) + ' more' : '') + '</span>'
                    : '<span class="text-muted">No preset plan for age ' + age + ' yet — our team will confirm the right plan for you.</span>';
            }
            function reset() {
                cols.forEach(col => { col.style.display = ''; col.querySelector('.sp-plan-card')?.classList.remove('sp-plan-match'); });
                clearBtn.hidden = true;
                result.textContent = '';
            }
            input.addEventListener('input', apply);
            clearBtn.addEventListener('click', () => { input.value = ''; reset(); input.focus(); });
        })();
    </script>
</body>

</html>