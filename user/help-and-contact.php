<?php
include_once "../config/connect.php";
include_once "../util/function.php";

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
    <title>Help & Contact Us | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .help-section {
            margin-bottom: 3rem;
        }
        .contact-card, .faq-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .contact-icon {
            background: #2c5aa0;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        .contact-details h5 {
            margin-bottom: 0.5rem;
            color: #2c5aa0;
        }
        .contact-details p {
            margin-bottom: 0.25rem;
        }
        .faq-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .faq-question {
            padding: 1.25rem;
            background: #f8f9fa;
            border: none;
            width: 100%;
            text-align: left;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .faq-question:hover {
            background: #e9ecef;
        }
        .faq-question[aria-expanded="true"] {
            background: #2c5aa0;
            color: white;
        }
        .faq-answer {
            padding: 1.25rem;
            background: white;
            border-top: 1px solid #e9ecef;
        }
        .accordion-button:not(.collapsed)::after {
            filter: brightness(0) invert(1);
        }
        .working-hours {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .working-hours h6 {
            color: #2c5aa0;
            margin-bottom: 0.5rem;
        }
    </style>
</head>

<body>
    <?php include("../header.php") ?>
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-3">
                   <?php include("sidebar.php") ?>
                </div>
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
                    <!-- Contact Details Section -->
                    <div class="help-section">
                        <div class="contact-card">
                            <h4 class="mb-4">Contact Details</h4>
                            <p class="text-muted mb-4">Get in touch with us through any of the following channels. We're here to help you!</p>
                            
                            <div class="contact-info-item">
                                <div class="contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Phone Numbers</h5>
                                    <p><strong>Primary:</strong> <a href="tel:+91<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a></p>
                                    <p><strong>WhatsApp:</strong> <a href="https://wa.me/91<?= $contact['wp_number'] ?>">+91-<?= $contact['wp_number'] ?></a></p>
                                    <p><strong>Emergency:</strong> <a href="tel:+91<?= $contact['wp_number'] ?>">+91-<?= $contact['wp_number'] ?></a></p>
                                </div>
                            </div>
                            
                            <div class="contact-info-item">
                                <div class="contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Email Address</h5>
                                    <p><strong>General Inquiries:</strong> <a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a></p>
                                    <p><strong>Support:</strong> <a href="mailto:support@rejuvenatehealth.com">support@rejuvenatehealth.com</a></p>
                                    <p><strong>Appointments:</strong> <a href="mailto:appointments@rejuvenatehealth.com">appointments@rejuvenatehealth.com</a></p>
                                </div>
                            </div>
                            
                            <div class="contact-info-item">
                                <div class="contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Office Address</h5>
                                    <p><?= $contact['address'] ?></p>
                                    <div class="working-hours">
                                        <h6>Working Hours</h6>
                                        <p class="mb-1"><strong>Monday - Friday:</strong> 9:00 AM - 6:00 PM</p>
                                        <p class="mb-1"><strong>Saturday:</strong> 9:00 AM - 2:00 PM</p>
                                        <p class="mb-0"><strong>Sunday:</strong> Emergency Services Only</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="contact-info-item">
                                <div class="contact-icon">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>24/7 Support</h5>
                                    <p>Our emergency support team is available 24/7 for urgent medical consultations and emergencies.</p>
                                    <p><strong>Emergency Hotline:</strong> <a href="tel:+91<?= $contact['wp_number'] ?>" class="text-danger">+91-<?= $contact['wp_number'] ?></a></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Section -->
                    <div class="help-section">
                        <div class="faq-card">
                            <h4 class="mb-4">Frequently Asked Questions</h4>
                            <p class="text-muted mb-4">Find quick answers to common questions about our services.</p>
                            
                            <div class="accordion" id="faqAccordion">
                                <!-- FAQ Item 1 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
                                        How do I book an appointment with a doctor?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>You can book an appointment in three ways:</p>
                                            <ol>
                                                <li>Online through our website by visiting the "Book Appointment" section</li>
                                                <li>Through our mobile app</li>
                                                <li>By calling our appointment helpline at +91-<?= $contact['phone'] ?></li>
                                            </ol>
                                            <p>You'll receive a confirmation SMS and email with your appointment details.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 2 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                        What should I do if I need to cancel or reschedule my appointment?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>You can cancel or reschedule your appointment:</p>
                                            <ul>
                                                <li>Through your patient dashboard under "My Appointments"</li>
                                                <li>By calling our support team at least 4 hours before your scheduled appointment</li>
                                                <li>Via WhatsApp message to +91-<?= $contact['wp_number'] ?></li>
                                            </ul>
                                            <p class="text-muted"><small>Note: Cancellations within 2 hours of appointment may be subject to a cancellation fee.</small></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 3 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                        How do I access my medical reports online?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>Your medical reports are available in the "My Reports" section of your patient dashboard. You can:</p>
                                            <ul>
                                                <li>View reports online</li>
                                                <li>Download PDF copies</li>
                                                <li>Share reports securely with other healthcare providers</li>
                                                <li>Access historical reports from previous visits</li>
                                            </ul>
                                            <p>Reports are typically available within 24 hours after your tests are completed.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 4 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                        What payment methods do you accept?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>We accept various payment methods for your convenience:</p>
                                            <ul>
                                                <li>Credit/Debit Cards (Visa, MasterCard, RuPay)</li>
                                                <li>Net Banking</li>
                                                <li>UPI Payments (Google Pay, PhonePe, PayTM)</li>
                                                <li>Digital Wallets</li>
                                                <li>Cash at the facility</li>
                                                <li>Health Insurance (TPA and cashless)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 5 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                                        How do I order supplements or medicines?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>You can order supplements and medicines through:</p>
                                            <ol>
                                                <li><strong>Online:</strong> Visit "My Supplement Order" in your dashboard</li>
                                                <li><strong>Prescription Upload:</strong> Upload your prescription and we'll deliver your medicines</li>
                                                <li><strong>Direct Purchase:</strong> Visit our in-house pharmacy</li>
                                                <li><strong>Delivery:</strong> We offer home delivery across the city</li>
                                            </ol>
                                            <p>Delivery usually takes 24-48 hours within the city limits.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 6 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                                        Is my medical information secure and private?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>Yes, we take your privacy and data security very seriously:</p>
                                            <ul>
                                                <li>All medical data is encrypted and stored securely</li>
                                                <li>We comply with HIPAA and Indian medical data protection standards</li>
                                                <li>Your information is never shared without your explicit consent</li>
                                                <li>Two-factor authentication for account access</li>
                                                <li>Regular security audits and updates</li>
                                            </ul>
                                            <p>You can review our complete Privacy Policy for more details.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 7 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7" aria-expanded="false" aria-controls="faq7">
                                        What should I do in case of a medical emergency?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>In case of a medical emergency:</p>
                                            <ul>
                                                <li><strong>Immediately call:</strong> +91-<?= $contact['wp_number'] ?> (24/7 Emergency Line)</li>
                                                <li>Visit our emergency department directly</li>
                                                <li>Use the emergency button in our mobile app for quick assistance</li>
                                                <li>Our emergency team will guide you through first aid steps if needed</li>
                                            </ul>
                                            <p class="text-danger"><strong>For life-threatening emergencies, please call your local emergency number (108) immediately.</strong></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- FAQ Item 8 -->
                                <div class="faq-item">
                                    <button class="faq-question accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8" aria-expanded="false" aria-controls="faq8">
                                        How do I update my personal information or address?
                                        <!-- <i class="fas fa-chevron-down"></i> -->
                                    </button>
                                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="faq-answer">
                                            <p>You can update your personal information easily:</p>
                                            <ul>
                                                <li>Go to "My Profile" in your dashboard to update basic information</li>
                                                <li>Visit "Manage Addresses" to add or update delivery addresses</li>
                                                <li>For legal name changes or major updates, please visit the facility with supporting documents</li>
                                                <li>Contact our support team if you need assistance with updates</li>
                                            </ul>
                                            <p>Keeping your information updated ensures smooth service delivery and communication.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include("../footer.php") ?>
    
    <script src="<?= $site ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        
        // Add smooth scrolling for FAQ items
        document.addEventListener('DOMContentLoaded', function() {
            const faqButtons = document.querySelectorAll('.faq-question');
            faqButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (this.getAttribute('aria-expanded') === 'true') {
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        icon.style.transform = 'rotate(180deg)';
                    }
                });
            });
        });
    </script>
</body>
</html>