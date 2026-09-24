<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$contact = contact_us();
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Help & ABDM Support | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .help-section { margin-bottom: 2rem; }
        .contact-card, .faq-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.75rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            padding: 1rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
        }
        .contact-icon {
            background: var(--primary);
            color: #fff;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .contact-icon.abdm-icon {
            background: linear-gradient(135deg, #0C74C5, #02c9b8);
        }
        .contact-details h5 {
            margin-bottom: 0.35rem;
            color: var(--primary);
            font-size: .95rem;
            font-weight: 700;
        }
        .contact-details p {
            margin-bottom: 0.25rem;
            font-size: .86rem;
            color: #4b5563;
        }
        .contact-details a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .contact-details a:hover {
            text-decoration: underline;
        }
        .faq-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }
        .faq-question {
            padding: 1rem 1.25rem;
            background: #f8fafc;
            border: none;
            width: 100%;
            text-align: left;
            font-weight: 600;
            font-size: .9rem;
            color: #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .faq-question:hover {
            background: #f1f5f9;
            color: var(--primary);
        }
        .faq-question[aria-expanded="true"] {
            background: var(--primary);
            color: #fff;
        }
        .faq-answer {
            padding: 1.2rem;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            font-size: .88rem;
            color: #4b5563;
            line-height: 1.6;
        }
        .working-hours {
            background: #eef7ff;
            padding: 0.85rem;
            border-radius: 8px;
            margin-top: 0.75rem;
            border-left: 3px solid var(--primary);
        }
        .working-hours h6 {
            color: var(--primary);
            margin-bottom: 0.4rem;
            font-size: .82rem;
            font-weight: 700;
        }
    </style>
</head>

<body class="patient-body">
    <?php $sidebar_active = 'help'; include("sidebar.php"); ?>

    <main class="patient-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="ap-h mb-1"><i class="fa fa-life-ring me-2 text-primary-theme"></i>Help & ABDM Support</h1>
                <div class="ap-sub">Patient care assistance, ABDM National Health Authority helpline, and FAQs</div>
            </div>
            <a href="<?= BASE_URL ?>user/user-dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <div class="row g-4">
            <!-- Left: Contact Details -->
            <div class="col-lg-5">
                <div class="contact-card">
                    <h5 class="fw-bold mb-3"><i class="fa fa-phone-square me-2 text-primary-theme"></i>Healthcare & Emergency Support</h5>
                    
                    <!-- Phone Support -->
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="fa fa-phone"></i>
                        </div>
                        <div class="contact-details">
                            <h5>Helpline & Call Center</h5>
                            <p><strong>General Support:</strong> <a href="tel:+91<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a></p>
                            <p><strong>WhatsApp Care:</strong> <a href="https://wa.me/91<?= $contact['wp_number'] ?>" target="_blank">+91-<?= $contact['wp_number'] ?></a></p>
                        </div>
                    </div>

                    <!-- ABDM NHA Official Helpline -->
                    <div class="contact-info-item">
                        <div class="contact-icon abdm-icon">
                            <i class="fa fa-id-card"></i>
                        </div>
                        <div class="contact-details">
                            <h5>ABDM (Ayushman Bharat) Support</h5>
                            <p><strong>NHA National Toll-Free:</strong> <a href="tel:14477">14477</a> or <a href="tel:1800114477">1800-11-4477</a></p>
                            <p><strong>ABDM Official Portal:</strong> <a href="https://abdm.gov.in" target="_blank">abdm.gov.in</a></p>
                            <p class="small text-muted mb-0">For ABHA card verification, Aadhaar OTP issues, and PHR federated registry queries.</p>
                        </div>
                    </div>

                    <!-- Email Support -->
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="fa fa-envelope"></i>
                        </div>
                        <div class="contact-details">
                            <h5>Email Inquiries</h5>
                            <p><strong>Patient Support:</strong> <a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a></p>
                            <p><strong>Consultations:</strong> <a href="mailto:appointments@rejuvenatehealth.com">appointments@rejuvenatehealth.com</a></p>
                        </div>
                    </div>

                    <!-- Office & Center -->
                    <div class="contact-info-item mb-0">
                        <div class="contact-icon">
                            <i class="fa fa-map-marker"></i>
                        </div>
                        <div class="contact-details">
                            <h5>Clinic & Center Address</h5>
                            <p><?= htmlspecialchars($contact['address']) ?></p>
                            <div class="working-hours">
                                <h6>Operational Hours</h6>
                                <p class="mb-1 small"><strong>Mon – Fri:</strong> 9:00 AM – 8:00 PM</p>
                                <p class="mb-1 small"><strong>Saturday:</strong> 9:00 AM – 4:00 PM</p>
                                <p class="mb-0 small"><strong>Sunday:</strong> Teleconsultation & Emergency Only</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: FAQs -->
            <div class="col-lg-7">
                <div class="faq-card">
                    <h5 class="fw-bold mb-3"><i class="fa fa-question-circle me-2 text-primary-theme"></i>Frequently Asked Questions</h5>
                    <p class="text-muted small mb-4">Answers regarding Ayushman Bharat Health Account (ABHA), digital records, doctor appointments, and medicines.</p>

                    <div class="accordion" id="faqAccordion">
                        <!-- FAQ 1 -->
                        <div class="faq-item">
                            <button class="faq-question accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                                What is an ABHA Number and how does it benefit me?
                            </button>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    An <strong>Ayushman Bharat Health Account (ABHA)</strong> is a 14-digit unique identifier issued by the National Health Authority (NHA), Government of India. It creates a digital footprint of your health records, enabling doctors and diagnostics across India to securely access your prescriptions and lab reports with your consent, without carrying physical papers.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 2 -->
                        <div class="faq-item">
                            <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                How can I download my official ABHA Card?
                            </button>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    Navigate to <a href="<?= BASE_URL ?>user/my-abha.php">My ABHA Health ID</a> in your dashboard. If your account is verified, click the <strong>"Download ABHA Card (PDF)"</strong> or <strong>"Print PNG Card"</strong> buttons. You can also generate an OTP on your linked Aadhaar or mobile number to fetch your official NHA card.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 3 -->
                        <div class="faq-item">
                            <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                How do I book an OPD consultation or teleconsultation?
                            </button>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    Visit <a href="<?= BASE_URL ?>user/my-bookings.php">Book Consultation</a>. Select the medical department, choose your preferred doctor, select an available date & time slot, and confirm your details. You can opt for an in-clinic visit or secure online video consultation.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 4 -->
                        <div class="faq-item">
                            <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                Is my medical health data private and secured?
                            </button>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    Yes. Rejuvenate Digital Health strictly adheres to the <strong>Digital Personal Data Protection (DPDP) Act 2023</strong> and ABDM M1/M2/M3 security frameworks. All medical reports, prescriptions, and health records are end-to-end encrypted. No healthcare facility or doctor can view your records without your explicit, OTP-based or digital consent.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 5 -->
                        <div class="faq-item">
                            <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                                How do doorstep lab test bookings and home sample collections work?
                            </button>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    When you book a diagnostic test through <a href="<?= BASE_URL ?>user/my-lab-bookings.php">My Lab Bookings</a>, a certified phlebotomist will visit your registered address at the scheduled time slot to collect blood or biological samples. Once analyzed, your verified PDF diagnostic report will be uploaded directly to your portal and linked to your ABHA profile.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 6 -->
                        <div class="faq-item">
                            <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                                How do I track my medicine and supplement orders?
                            </button>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    You can check the real-time status of your prescription orders under <a href="<?= BASE_URL ?>user/my-medicine-orders.php">Medicine Orders</a>, and nutritional orders under <a href="<?= BASE_URL ?>user/my-supplement-order.php">Supplement Orders</a>. Courier tracking numbers and dispatch details appear as soon as the pharmacy dispatches your package.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include("inc/scripts.php"); ?>
</body>
</html>