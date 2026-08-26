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
    <meta name="description" content="Rejuvenate Digital Health — India's school health platform empowering students, parents, teachers, doctors and government health systems through ABHA-linked digital health records.">
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
    <style>
        :root {
            --rdh-teal:  #02c9b8;
            --rdh-blue:  #0C74C5;
            --rdh-dark:  #1a2340;
            --rdh-light: #f4fbfb;
        }

        /* ── Hero ── */
        .about-hero {
            background: linear-gradient(135deg, var(--rdh-dark) 0%, #0a4a8a 50%, #025a52 100%);
            padding: 80px 0 60px;
            text-align: center;
            color: #fff;
        }
        .about-hero .hero-badge {
            display: inline-block;
            background: rgba(2,201,184,.18);
            border: 1px solid var(--rdh-teal);
            color: var(--rdh-teal);
            border-radius: 20px;
            font-size: .8rem;
            letter-spacing: .08em;
            padding: 4px 16px;
            margin-bottom: 18px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .about-hero h1 {
            font-size: 2.4rem;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 18px;
        }
        .about-hero h1 span { color: var(--rdh-teal); }
        .about-hero p {
            font-size: 1.05rem;
            opacity: .88;
            max-width: 700px;
            margin: 0 auto 28px;
            line-height: 1.7;
        }
        .about-hero .hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 40px;
            border-top: 1px solid rgba(255,255,255,.15);
            padding-top: 32px;
        }
        .about-hero .hero-stat strong {
            display: block;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--rdh-teal);
        }
        .about-hero .hero-stat span {
            font-size: .82rem;
            opacity: .78;
        }

        /* ── Connection diagram ── */
        .connections-section {
            background: #fff;
            padding: 70px 0 60px;
        }
        .connections-section .section-label {
            text-align: center;
            margin-bottom: 50px;
        }
        .connections-section .section-label h2 { font-size: 1.9rem; font-weight: 700; color: var(--rdh-dark); }
        .connections-section .section-label p  { color: #6c757d; max-width: 560px; margin: 10px auto 0; }

        .stakeholder-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) { .stakeholder-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .stakeholder-grid { grid-template-columns: 1fr; } }

        .stakeholder-card {
            background: var(--rdh-light);
            border: 1.5px solid #e0f5f3;
            border-radius: 14px;
            padding: 26px 20px;
            text-align: center;
            transition: box-shadow .25s, transform .25s, border-color .25s;
        }
        .stakeholder-card:hover {
            box-shadow: 0 8px 28px rgba(2,201,184,.18);
            transform: translateY(-4px);
            border-color: var(--rdh-teal);
        }
        .stakeholder-card .s-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 14px;
        }
        .stakeholder-card h5 { font-size: 1rem; font-weight: 700; color: var(--rdh-dark); margin-bottom: 6px; }
        .stakeholder-card p  { font-size: .84rem; color: #6c757d; margin: 0; }

        /* icon colour variants */
        .s-icon.schools   { background: #e8f9f8; color: var(--rdh-teal); }
        .s-icon.teachers  { background: #e8f0fa; color: var(--rdh-blue); }
        .s-icon.students  { background: #fff3e0; color: #f57c00; }
        .s-icon.parents   { background: #fce4ec; color: #e91e63; }
        .s-icon.doctors   { background: #e8f5e9; color: #2e7d32; }
        .s-icon.govt      { background: #ede7f6; color: #512da8; }

        /* centre hub */
        .hub-center {
            text-align: center;
            padding: 28px 0 8px;
        }
        .hub-center .hub-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--rdh-teal), var(--rdh-blue));
            color: #fff;
            border-radius: 30px;
            padding: 10px 28px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .03em;
            box-shadow: 0 4px 18px rgba(2,201,184,.35);
        }

        /* ── How We Help ── */
        .help-section {
            background: var(--rdh-light);
            padding: 70px 0 60px;
        }
        .help-section .section-label { text-align: center; margin-bottom: 48px; }
        .help-section .section-label h2 { font-size: 1.9rem; font-weight: 700; color: var(--rdh-dark); }
        .help-section .section-label p  { color: #6c757d; max-width: 540px; margin: 10px auto 0; }

        .help-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px 22px;
            height: 100%;
            border: 1.5px solid #e8f5f4;
            transition: box-shadow .25s, transform .25s;
        }
        .help-card:hover {
            box-shadow: 0 8px 28px rgba(2,201,184,.16);
            transform: translateY(-4px);
        }
        .help-card .h-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #e8f9f8, #d0f0f5);
            color: var(--rdh-teal);
        }
        .help-card h5 { font-size: .97rem; font-weight: 700; color: var(--rdh-dark); margin-bottom: 8px; }
        .help-card p  { font-size: .84rem; color: #6c757d; margin: 0; line-height: 1.6; }

        /* ── Why It Matters ── */
        .matters-section {
            background: #fff;
            padding: 70px 0 60px;
        }
        .matters-section .section-label { text-align: center; margin-bottom: 48px; }
        .matters-section .section-label h2 { font-size: 1.9rem; font-weight: 700; color: var(--rdh-dark); }

        .matter-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .matter-item:last-child { border-bottom: none; }
        .matter-item .m-num {
            width: 44px; height: 44px; flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--rdh-teal), var(--rdh-blue));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem;
        }
        .matter-item h5 { font-size: 1rem; font-weight: 700; color: var(--rdh-dark); margin: 0 0 5px; }
        .matter-item p  { font-size: .85rem; color: #6c757d; margin: 0; }

        .matters-visual {
            background: linear-gradient(135deg, var(--rdh-dark), #0a4a8a);
            border-radius: 18px;
            padding: 40px 30px;
            color: #fff;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .matters-visual h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; }
        .matters-visual p  { opacity: .82; font-size: .9rem; line-height: 1.7; }
        .matters-visual .mv-stat { margin-top: 28px; }
        .matters-visual .mv-stat strong { font-size: 2rem; color: var(--rdh-teal); display: block; }
        .matters-visual .mv-stat span  { font-size: .82rem; opacity: .75; }

        /* ── Government Alignment ── */
        .govt-section {
            background: var(--rdh-light);
            padding: 60px 0;
        }
        .govt-section .section-label { text-align: center; margin-bottom: 40px; }
        .govt-section .section-label h2 { font-size: 1.7rem; font-weight: 700; color: var(--rdh-dark); }
        .govt-section .section-label p  { color: #6c757d; }

        .govt-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 1.5px solid #e0f5f3;
            border-radius: 12px;
            padding: 22px 16px;
            text-align: center;
            transition: box-shadow .2s, border-color .2s;
        }
        .govt-badge:hover { box-shadow: 0 4px 18px rgba(2,201,184,.15); border-color: var(--rdh-teal); }
        .govt-badge .g-icon { font-size: 2rem; margin-bottom: 10px; }
        .govt-badge h6 { font-size: .82rem; font-weight: 700; color: var(--rdh-dark); margin: 0; line-height: 1.35; }

        /* ── Security Footer Section ── */
        .security-section {
            background: linear-gradient(135deg, var(--rdh-dark) 0%, #0a4a8a 60%, #025a52 100%);
            padding: 60px 0;
            color: #fff;
            text-align: center;
        }
        .security-section h2 { font-size: 2rem; font-weight: 700; margin-bottom: 8px; }
        .security-section h2 span { color: var(--rdh-teal); }
        .security-section .sec-sub { opacity: .8; margin-bottom: 40px; }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            max-width: 900px;
            margin: 0 auto 44px;
        }
        @media (max-width: 768px) { .security-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .security-grid { grid-template-columns: 1fr; } }

        .sec-item {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(2,201,184,.25);
            border-radius: 12px;
            padding: 22px 14px;
        }
        .sec-item .si-icon { font-size: 1.8rem; margin-bottom: 10px; color: var(--rdh-teal); }
        .sec-item p { font-size: .82rem; opacity: .82; margin: 0; line-height: 1.5; }

        .cta-final {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .btn-teal {
            background: var(--rdh-teal);
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: .92rem;
            text-decoration: none;
            transition: opacity .2s;
        }
        .btn-teal:hover { opacity: .88; color: #fff; text-decoration: none; }
        .btn-outline-white {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,.5);
            padding: 10px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: .92rem;
            text-decoration: none;
            transition: background .2s, border-color .2s;
        }
        .btn-outline-white:hover { background: rgba(255,255,255,.12); border-color: #fff; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<?php include "header.php" ?>

<!-- Breadcrumb -->
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

<!-- ══════════════════════════════════════════════
     HERO
══════════════════════════════════════════════ -->
<section class="about-hero">
    <div class="container">
        <div class="hero-badge">School Health Platform</div>
        <h1>Empowering Every Child.<br><span>Strengthening Every Future.</span></h1>
        <p>
            Rejuvenate Digital Health is India's dedicated school health platform — connecting schools, families, doctors,
            and government health systems to deliver proactive, data-driven healthcare for every student.
        </p>
        <div class="hero-stats">
            <div class="hero-stat">
                <strong>500+</strong>
                <span>Schools Onboarded</span>
            </div>
            <div class="hero-stat">
                <strong>1L+</strong>
                <span>Student Profiles</span>
            </div>
            <div class="hero-stat">
                <strong>200+</strong>
                <span>Verified Doctors</span>
            </div>
            <div class="hero-stat">
                <strong>ABHA</strong>
                <span>Linked &amp; Compliant</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     ONE PLATFORM. MANY CONNECTIONS.
══════════════════════════════════════════════ -->
<section class="connections-section">
    <div class="container">
        <div class="section-label">
            <h2>One Platform. Many Connections. Better Health for All.</h2>
            <p>We bring together every stakeholder in a child's health journey on a single, secure, integrated platform.</p>
        </div>

        <div class="stakeholder-grid">
            <div class="stakeholder-card">
                <div class="s-icon schools"><i class="fas fa-school"></i></div>
                <h5>Schools</h5>
                <p>Manage student health records, track vaccinations, monitor growth and trigger early interventions.</p>
            </div>
            <div class="stakeholder-card">
                <div class="s-icon teachers"><i class="fas fa-chalkboard-teacher"></i></div>
                <h5>Teachers &amp; Admins</h5>
                <p>Track, monitor &amp; take action early — flag health concerns and coordinate with parents in real time.</p>
            </div>
            <div class="stakeholder-card">
                <div class="s-icon students"><i class="fas fa-child"></i></div>
                <h5>Students</h5>
                <p>Healthy today, strong tomorrow — every student gets a lifelong ABHA-linked digital health record.</p>
            </div>
            <div class="stakeholder-card">
                <div class="s-icon parents"><i class="fas fa-users"></i></div>
                <h5>Parents</h5>
                <p>Real-time updates, complete peace of mind — receive instant alerts and access your child's health summary anytime.</p>
            </div>
            <a href="<?= BASE_URL ?>doctor-network.php" class="stakeholder-card" style="display:block; text-decoration:none;">
                <div class="s-icon doctors"><i class="fas fa-user-md"></i></div>
                <h5>Doctors</h5>
                <p>Clinical insights &amp; tele-consultations — provide specialist care without students missing school.</p>
            </a>
            <div class="stakeholder-card">
                <div class="s-icon govt"><i class="fas fa-landmark"></i></div>
                <h5>Government Health Systems</h5>
                <p>Stronger data for stronger nations — anonymised, aggregated insights to drive national health policy.</p>
            </div>
        </div>

        <div class="hub-center mt-4">
            <span class="hub-badge">&#9679;&nbsp; Rejuvenate Digital Health &nbsp;&#9679;</span>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     HOW WE HELP
══════════════════════════════════════════════ -->
<section class="help-section">
    <div class="container">
        <div class="section-label">
            <h2>How We Help</h2>
            <p>Purpose-built features that make school health management seamless, proactive and effective.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="help-card">
                    <div class="h-icon"><i class="fas fa-file-medical-alt"></i></div>
                    <h5>Digital Health Records</h5>
                    <p>Each student gets a secure, ABHA-linked digital health record that travels with them from kindergarten to graduation — complete, private, and always accessible.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="help-card">
                    <div class="h-icon"><i class="fas fa-syringe"></i></div>
                    <h5>Vaccination Tracking</h5>
                    <p>Never miss a dose. Automated reminders and a complete immunisation history ensure every child stays protected on schedule.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="help-card">
                    <div class="h-icon"><i class="fas fa-chart-line"></i></div>
                    <h5>Growth &amp; Development Monitoring</h5>
                    <p>Track BMI, height, weight, and developmental milestones. Visual growth charts help identify concerns early — before they become problems.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="help-card">
                    <div class="h-icon"><i class="fas fa-shield-alt"></i></div>
                    <h5>Preventive Healthcare</h5>
                    <p>Scheduled screenings, annual health check-ups, and early-warning alerts empower schools to act before illness disrupts learning.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="help-card">
                    <div class="h-icon"><i class="fas fa-video"></i></div>
                    <h5>Doctor Support &amp; Online Consultations</h5>
                    <p>Verified doctors available for tele-consultations — students get expert care without leaving campus. Prescriptions and notes saved directly to their health record.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="help-card">
                    <div class="h-icon"><i class="fas fa-brain"></i></div>
                    <h5>Data-Driven Insights</h5>
                    <p>Anonymised, school-level and district-level analytics give administrators and policymakers the intelligence to allocate resources where they matter most.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     WHY IT MATTERS
══════════════════════════════════════════════ -->
<section class="matters-section">
    <div class="container">
        <div class="section-label">
            <h2>Why It Matters</h2>
        </div>
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="matter-item">
                    <div class="m-num">1</div>
                    <div>
                        <h5>Healthier Students</h5>
                        <p>Early identification and timely interventions mean children stay healthier, miss fewer classes, and perform better academically.</p>
                    </div>
                </div>
                <div class="matter-item">
                    <div class="m-num">2</div>
                    <div>
                        <h5>Peace of Mind for Parents</h5>
                        <p>Parents receive instant alerts, can view their child's health records anytime, and connect with doctors directly — from any device.</p>
                    </div>
                </div>
                <div class="matter-item">
                    <div class="m-num">3</div>
                    <div>
                        <h5>Empowered Schools</h5>
                        <p>Schools replace paper forms and manual tracking with a single digital dashboard — saving time, reducing errors, and demonstrating duty of care.</p>
                    </div>
                </div>
                <div class="matter-item">
                    <div class="m-num">4</div>
                    <div>
                        <h5>Stronger Communities</h5>
                        <p>When children are healthy, families are more productive and communities thrive. Health data flowing between schools and clinics closes the gaps in community care.</p>
                    </div>
                </div>
                <div class="matter-item">
                    <div class="m-num">5</div>
                    <div>
                        <h5>Future-Ready Nation</h5>
                        <p>Aggregated, anonymised school health data feeds into national health programmes — helping India build evidence-based policies for a healthier next generation.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="matters-visual">
                    <h3>Building India's Healthiest Generation</h3>
                    <p>
                        Every data point captured, every vaccination tracked, every consultation conducted on our platform
                        is a step toward a healthier, stronger, and more resilient India.
                    </p>
                    <p>
                        We believe that school is where health habits form — and that technology should make it effortless
                        for every stakeholder to protect and nurture every child's wellbeing.
                    </p>
                    <div class="mv-stat">
                        <strong>2030</strong>
                        <span>Vision: Every school in India digitally health-connected</span>
                    </div>
                </div>
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
            <p>Built in compliance with — and in support of — India's flagship digital health and wellness programmes.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge">
                    <div class="g-icon">🏥</div>
                    <h6>Ayushman Bharat</h6>
                </div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge">
                    <div class="g-icon">💻</div>
                    <h6>Digital India</h6>
                </div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge">
                    <div class="g-icon">🔗</div>
                    <h6>ABDM / NDHM</h6>
                </div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge">
                    <div class="g-icon">🌾</div>
                    <h6>POSHAN Abhiyaan</h6>
                </div>
            </div>
            <div class="col-lg col-md-4 col-6">
                <div class="govt-badge">
                    <div class="g-icon">🏫</div>
                    <h6>School Health Programmes</h6>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     SECURITY / COMPLIANCE FOOTER
══════════════════════════════════════════════ -->
<section class="security-section">
    <div class="container">
        <h2>Secure. <span>Compliant.</span> Built for Scale.</h2>
        <p class="sec-sub">Your students' health data protected by enterprise-grade security and full regulatory compliance.</p>

        <div class="security-grid">
            <div class="sec-item">
                <div class="si-icon"><i class="fas fa-lock"></i></div>
                <p>End-to-End Encryption</p>
            </div>
            <div class="sec-item">
                <div class="si-icon"><i class="fas fa-user-shield"></i></div>
                <p>Role-Based Access Control</p>
            </div>
            <div class="sec-item">
                <div class="si-icon"><i class="fas fa-gavel"></i></div>
                <p>Data Protection Compliance (DPDP Act)</p>
            </div>
            <div class="sec-item">
                <div class="si-icon"><i class="fas fa-expand-arrows-alt"></i></div>
                <p>Scalable for Schools of All Sizes</p>
            </div>
        </div>

        <p style="font-size: 1.15rem; font-weight: 600; opacity: .9; margin-bottom: 28px;">
            Healthy Students. Stronger Nation.
        </p>

        <div class="cta-final">
            <a href="<?= BASE_URL ?>school-register.php" class="btn-teal">Register Your School</a>
            <a href="<?= BASE_URL ?>contact-us.php"       class="btn-outline-white">Get in Touch</a>
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
