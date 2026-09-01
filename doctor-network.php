<?php
include_once "config/connect.php";
include_once "util/function.php";
require_once __DIR__ . '/util/doctor-plans-render.php';

$contact = contact_us();
$logo    = get_header_logo();

$dn_plans = [];
$dn_res = $conn->query("SELECT * FROM doctor_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC, id ASC");
if ($dn_res) { while ($r = $dn_res->fetch_assoc()) $dn_plans[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="Join the Rejuvenate Digital Health Doctor Network — grow your practice, reach more patients, and stay fully ABHA/ABDM compliant with HPR-linked digital health records.">
    <title>Doctor Network | REJUVENATE Digital Health</title>
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

<!-- Breadcrumb -->
<div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
    <div class="container">
        <div class="page-heading">
            <div class="breadcrumb-items-area">
                <div class="breadcrumb-sub-title">
                    <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Doctor Network</h1>
                </div>
                <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                    <li><a href="<?= BASE_URL ?>">Home</a></li>
                    <li>//</li>
                    <li>Doctor Network</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     HERO
══════════════════════════════════════════════ -->
<section class="dn-hero">
    <div class="container">
        <div class="hero-badge">For Doctors</div>
        <h1 class="text-light">Grow Your Practice.<br>Serve More Patients — <span>The ABHA-Compliant Way.</span></h1>
        <p>
            Rejuvenate Digital Health's Doctor Network connects verified doctors with patients, schools and
            government health systems on one secure, ABHA/ABDM-aligned platform — so you can focus on medicine
            while we handle visibility, compliance and digital records.
        </p>
        <div class="hero-stats">
            <div class="hero-stat"><strong>200+</strong><span>Verified Doctors</span></div>
            <div class="hero-stat"><strong>25+</strong><span>Specializations</span></div>
            <div class="hero-stat"><strong>5+</strong><span>Partner Schools</span></div>
            <div class="hero-stat"><strong>ABHA</strong><span>&amp; HPR Ready</span></div>
        </div>
        <div class="cta-row mt-4">
            <a href="<?= BASE_URL ?>doctor-signup.php" class="btn-teal">Join the Doctor Network</a>
            <a href="<?= BASE_URL ?>doctor-login.php" class="btn-outline-white">Doctor Login</a>
            <a href="<?= BASE_URL ?>doctor-network/#membership" class="btn-outline-white">Membership</a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     WHAT IS REJUVENATE DIGITAL HEALTH
══════════════════════════════════════════════ -->
<section class="intro-section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-7 intro-text">
                <span class="eyebrow" style="color:var(--rdh-teal); font-weight:700; font-size:.8rem; letter-spacing:.08em; text-transform:uppercase;">Who We Are</span>
                <h2 style="font-size:1.9rem; font-weight:700; color:var(--rdh-dark); margin:10px 0 18px;">What is Rejuvenate Digital Health?</h2>
                <p>
                    Rejuvenate Digital Health is India's digital healthcare platform built to connect
                    <strong>doctors, patients, schools and government health systems</strong> on a single, secure ecosystem.
                    Read more about our journey on the <a href="<?= BASE_URL ?>about-us.php">About Us</a> page.
                </p>
                <p>
                    We enable patients to book consultations, manage digital health records and access verified doctors
                    across <a href="<?= BASE_URL ?>departments.php">every major medical department</a> — while giving doctors a
                    trusted, compliant channel to reach more patients without spending on marketing or building their own
                    ABHA integration from scratch.
                </p>
                <p>
                    Our platform is designed from the ground up to align with the <strong>Ayushman Bharat Digital Mission (ABDM)</strong>
                    guidelines issued by the National Health Authority (NHA), so every doctor and patient interaction on
                    Rejuvenate contributes to a secure, portable, nationwide digital health record.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="intro-panel">
                    <h4><i class="fas fa-bullseye me-2"></i>What Makes Us Different</h4>
                    <ul>
                        <li>Built specifically to comply with ABHA / ABDM / HPR guidelines</li>
                        <li>Direct pipeline of patients from our School Health Program</li>
                        <li>In-person, online and tele-consultation support in one dashboard</li>
                        <li>Every consultation logged as a verifiable care context</li>
                        <li>No hidden fees for onboarding or profile listing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     ROLE OF A DOCTOR ON THE PLATFORM
══════════════════════════════════════════════ -->
<section class="card-section alt">
    <div class="container">
        <div class="section-label">
            <span class="eyebrow">Your Role</span>
            <h2>What Doctors Do on Rejuvenate</h2>
            <p>As a Rejuvenate-listed doctor, you become a trusted care provider inside a compliant, connected health network.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-stethoscope"></i></div>
                    <h5>In-Clinic &amp; Online Consultations</h5>
                    <p>Accept appointments booked through the platform — in person at your clinic or via secure video consultation.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-file-prescription"></i></div>
                    <h5>Digital Prescriptions &amp; Notes</h5>
                    <p>Issue prescriptions and clinical notes digitally, saved directly to the patient's ABHA-linked health record.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-school"></i></div>
                    <h5>School Health Screenings</h5>
                    <p>Participate in <a href="<?= BASE_URL ?>school-program/" class="text-primary fw- bold text-decoration-underline">School Health Program</a> check-ups, reaching students who need specialist attention early.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-notes-medical"></i></div>
                    <h5>Care Context Contribution</h5>
                    <p>Every visit you record becomes a care context linked to the patient's ABHA number — building their lifelong medical history.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-heartbeat"></i></div>
                    <h5>Preventive Guidance</h5>
                    <p>Advise patients and school students on preventive care, follow-ups and vaccination schedules.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-id-badge"></i></div>
                    <h5>Verified Digital Identity</h5>
                    <p>Maintain a verified profile with your degrees, specialization, NMC registration and HPR ID visible to patients.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     HOW WE PROMOTE DOCTORS
══════════════════════════════════════════════ -->
<section class="card-section">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="section-label" style="text-align:left; margin-bottom:30px;">
                    <span class="eyebrow">Visibility &amp; Growth</span>
                    <h2>How Rejuvenate Promotes Your Practice</h2>
                </div>
                <div class="process-item">
                    <div class="p-num">1</div>
                    <div>
                        <h5>Featured Doctor Listings</h5>
                        <p>Top-rated and highly active doctors are featured on the homepage and department pages for maximum visibility.</p>
                    </div>
                </div>
                <div class="process-item">
                    <div class="p-num">2</div>
                    <div>
                        <h5>Specialization-Based Discovery</h5>
                        <p>Your profile appears whenever a patient searches or browses <a href="<?= BASE_URL ?>departments.php">departments</a> matching your specialization.</p>
                    </div>
                </div>
                <div class="process-item">
                    <div class="p-num">3</div>
                    <div>
                        <h5>Ratings &amp; Patient Reviews</h5>
                        <p>Genuine patient reviews build your public reputation and improve your ranking within your specialty.</p>
                    </div>
                </div>
                <div class="process-item">
                    <div class="p-num">4</div>
                    <div>
                        <h5>School &amp; Institutional Empanelment</h5>
                        <p>Get empanelled with partner schools for health drives — exposing your practice to hundreds of families at once.</p>
                    </div>
                </div>
                <div class="process-item">
                    <div class="p-num">5</div>
                    <div>
                        <h5>Verified &amp; HPR Badges</h5>
                        <p>"Verified" and "HPR Registered" badges signal trust to patients and improve conversion from profile views to bookings.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="side-visual">
                    <h3 class="text-light">Marketing, Handled For You</h3>
                    <p>
                        Instead of spending on ads, brochures or a personal website, your verified profile works around the
                        clock — discoverable by patients, parents and schools searching for trusted care.
                    </p>
                    <p>
                        We invest in SEO, department pages and school partnerships so that patient discovery keeps growing —
                        you focus on consultations, we bring the visibility.
                    </p>
                    <div class="sv-stat">
                        <strong>Zero</strong>
                        <span>Marketing cost to get discovered by new patients</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     HOW DOCTORS GET PATIENTS
══════════════════════════════════════════════ -->
<section class="card-section alt">
    <div class="container">
        <div class="section-label">
            <span class="eyebrow">Patient Flow</span>
            <h2>How You Receive Patients on Rejuvenate</h2>
            <p>Multiple channels bring patients to your profile — all bookings are managed in one dashboard.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-calendar-check"></i></div>
                    <h5>Online Appointment Booking</h5>
                    <p>Patients discover and <a href="<?= BASE_URL ?>book-appointment.php" class="text-primary fw- bold text-decoration-underline">book appointments</a> with you directly — no phone calls or manual scheduling needed.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-video"></i></div>
                    <h5>Tele-Consultations</h5>
                    <p>Reach patients beyond your city through secure video consultations, expanding your practice's geography.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-user-graduate"></i></div>
                    <h5>School Health Program Referrals</h5>
                    <p>Students flagged during school screenings are referred to specialists on the platform — a steady stream of family patients.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-share-square"></i></div>
                    <h5>Patient Referrals</h5>
                    <p>Satisfied patients refer family and friends directly through their account — trust compounds over time.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-hospital-user"></i></div>
                    <h5>Existing ABHA Records</h5>
                    <p>Patients with an existing ABHA account can link past records instantly, giving you full context from the first visit.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="rdh-card">
                    <div class="h-icon"><i class="fas fa-search"></i></div>
                    <h5>Department &amp; Search Discovery</h5>
                    <p>Ranked visibility on <a href="<?= BASE_URL ?>departments/" class="text-primary fw- bold text-decoration-underline">department pages</a> means patients actively searching for your specialty find you first.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     ABHA / ABDM GOVERNMENT COMPLIANCE
══════════════════════════════════════════════ -->
<section class="intro-section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 order-lg-2">
                <div class="intro-panel" style="background: var(--rdh-dark);">
                    <h4><i class="fas fa-shield-alt me-2"></i>Compliance Handled For You</h4>
                    <ul>
                        <li>HPR (Health Professional Registry) ID linked to your profile</li>
                        <li>NMC registration &amp; state council verified before approval</li>
                        <li>Patient consent captured before every health record access</li>
                        <li>Every ABDM interaction logged in a tamper-evident audit trail</li>
                        <li>Aadhaar data never stored raw — only masked, consented references</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-7 order-lg-1 intro-text">
                <span class="eyebrow" style="color:var(--rdh-teal); font-weight:700; font-size:.8rem; letter-spacing:.08em; text-transform:uppercase;">Regulatory Alignment</span>
                <h2 style="font-size:1.9rem; font-weight:700; color:var(--rdh-dark); margin:10px 0 18px;">Built Around ABHA &amp; ABDM Guidelines</h2>
                <p>
                    Rejuvenate Digital Health is architected to follow the <strong>Ayushman Bharat Digital Mission (ABDM)</strong>
                    framework mandated by the National Health Authority (NHA), India. Every patient on the platform is
                    encouraged to create and link a 14-digit <strong>ABHA (Ayushman Bharat Health Account)</strong>, and every
                    consultation you provide becomes a verifiable <strong>care context</strong> tied to that patient's lifelong
                    digital health record.
                </p>
                <p>
                    On the doctor side, we align with the <strong>Health Professional Registry (HPR)</strong> — doctors are
                    verified against their NMC registration, council name and qualification before their profile goes live,
                    and can complete HPR registration through an Aadhaar-based OTP flow.
                </p>
                <p>
                    Access to any patient's health records requires the patient's active digital consent, and every access
                    event is recorded in our audit log — the same accountability standard NHA expects from every ABDM-linked
                    facility. Full details are available on our
                    <a href="<?= BASE_URL ?>legal-compliance.php">Legal Compliance</a> and
                    <a href="<?= BASE_URL ?>privacy-policy.php">Privacy Policy</a> pages.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     VISION & MISSION
══════════════════════════════════════════════ -->
<section class="vm-section">
    <div class="container">
        <div class="section-label">
            <span class="eyebrow">Why We Exist</span>
            <h2>Our Vision &amp; Mission</h2>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="vm-card vision">
                    <div class="vm-icon"><i class="fas fa-eye"></i></div>
                    <h3>Our Vision</h3>
                    <p>
                        To build India's most trusted, ABHA-compliant digital health network — where every doctor,
                        patient, school and government health system is connected through one secure, transparent
                        platform, and every citizen carries a lifelong, portable digital health record.
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="vm-card mission">
                    <div class="vm-icon"><i class="fas fa-rocket"></i></div>
                    <h3>Our Mission</h3>
                    <p>
                        To empower doctors with a compliant, zero-hassle way to grow their practice; to give patients
                        and students easy access to verified specialists; and to support India's national digital
                        health mission by making ABDM adoption simple, secure and rewarding for every stakeholder.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     BENEFITS FOR DOCTORS
══════════════════════════════════════════════ -->
<section class="card-section alt">
    <div class="container">
        <div class="section-label">
            <span class="eyebrow">Why Join</span>
            <h2>What's In It for You</h2>
            <p>Real, practical benefits for doctors who join the Rejuvenate network.</p>
        </div>
        <div class="benefit-grid">
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>Wider Patient Reach</h6><p>Access patients from your city, partner schools and tele-consultation seekers nationwide.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>No Marketing Spend</h6><p>Your listing, ratings and SEO-optimised profile bring patients without ad budgets.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>ABDM Compliance Built-In</h6><p>No need to build your own HPR or ABHA integration — we handle it for you.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>Flexible Consultations</h6><p>Choose in-clinic, online, or both — set your own available hours.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>Secure Patient Records</h6><p>Consent-based, encrypted access keeps every patient interaction compliant and safe.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>Professional Recognition</h6><p>Verified and HPR badges build long-term digital credibility and patient trust.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>School Health Priority</h6><p>Get first access to school health drives and institutional empanelment opportunities.</p></div>
            </div>
            <div class="benefit-item">
                <div class="b-check"><i class="fas fa-check"></i></div>
                <div><h6>Simple Dashboard</h6><p>Manage appointments, prescriptions and patient history from a single doctor panel.</p></div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     GOVERNMENT ALIGNMENT
══════════════════════════════════════════════ -->
<section class="govt-section">
    <div class="container">
        <div class="section-label">
            <h2>Aligned with India's National Health Initiatives</h2>
            <p>Built in compliance with — and in support of — India's flagship digital health programmes.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge"><div class="g-icon">🏥</div><h6>Ayushman Bharat</h6></div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge"><div class="g-icon">🔗</div><h6>ABDM / NDHM</h6></div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge"><div class="g-icon">🩺</div><h6>HPR Registry</h6></div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge"><div class="g-icon">📜</div><h6>NMC Registered</h6></div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge"><div class="g-icon">💻</div><h6>Digital India</h6></div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     MEMBERSHIP PLANS
══════════════════════════════════════════════ -->
<?php if (!empty($dn_plans)): ?>
<section class="card-section alt" id="membership">
    <div class="container">
        <div class="section-label">
            <span class="eyebrow">Membership</span>
            <h2>Simple Plans, Built for Growing Practices</h2>
            <p>Pick the membership length that suits you — Monthly, Quarterly, 6-Month or Yearly. Longer plans
               cost less per month and keep your verified profile discoverable to patients across India for longer.</p>
        </div>
        <?php render_doctor_plan_cards($dn_plans, ['cta_mode' => 'link', 'signup_url' => BASE_URL . 'doctor-signup.php', 'compact' => true]); ?>
        <div class="text-center mt-4">
            <a href="<?= BASE_URL ?>doctor-plans/" class="btn-teal">See full plan details &amp; FAQ →</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     FINAL CTA
══════════════════════════════════════════════ -->
<section class="dn-cta-section">
    <div class="container">
        <h2>Ready to <span>Grow Your Practice?</span></h2>
        <p class="cta-sub">Join a compliant, patient-connected doctor network built for India's digital health future. Learn more <a href="<?= BASE_URL ?>about-us.php" style="color:#fff; text-decoration:underline;">about Rejuvenate</a> or explore our <a href="<?= BASE_URL ?>faq.php" style="color:#fff; text-decoration:underline;">FAQs</a> before you join.</p>
        <div class="cta-final">
            <a href="<?= BASE_URL ?>doctor-signup.php" class="btn-teal">Register as a Doctor</a>
            <a href="<?= BASE_URL ?>doctor-login.php" class="btn-outline-white">Doctor Login</a>
            <a href="<?= BASE_URL ?>contact-us.php" class="btn-outline-white">Contact Us</a>
        </div>
    </div>
</section>


<?php include "footer.php" ?>
</body>
</html>
