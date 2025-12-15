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
    <meta name="description" content="Terms and Conditions for REJUVENATE Digital Health - Understand the rules and guidelines for using our digital health portal.">
    <title>Terms and Conditions | REJUVENATE Digital Health</title>
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
<style>
    p{
        color: #fff;
    }
    ul li{
        color: #fff;
    }
</style>
<body>
    <?php include("header.php")?>
    
    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= $site?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Terms and Conditions</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li>
                            <a href="<?= $site?>">Home</a>
                        </li>
                        <li>//</li>
                        <li>Terms and Conditions</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Content Section Start -->
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="contact-appointment-wrapper-5">
                <div class="row g-4">
                    <div class="col-lg-12">
                        <div class="contact-appointment-box">
                            <h3 class="mb-4">REJUVENATE Digital Health Terms and Conditions</h3>
                            <p class="text-muted"><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
                            
                            <div class="terms-content">
                                <div class="mb-5">
                                    <h5>1. Acceptance of Terms</h5>
                                    <p>By accessing and using the REJUVENATE Digital Health portal ("the Portal"), you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, you must not use our Portal.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>2. Description of Service</h5>
                                    <p>REJUVENATE Digital Health provides a digital platform that facilitates communication between patients and healthcare providers. The Portal allows patients to share health information and enables healthcare professionals to manage assigned duties related to patient care.</p>
                                    <p><strong>Important:</strong> Our Portal is designed to supplement, not replace, the relationship between you and your healthcare providers. It does not provide medical advice, diagnosis, or treatment.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>3. User Accounts</h5>
                                    <p><strong>A. Eligibility:</strong> To create an account, you must be at least 18 years of age or have parental/guardian consent. You must provide accurate, current, and complete information during the registration process.</p>
                                    <p><strong>B. Account Security:</strong> You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>
                                    <p><strong>C. Account Termination:</strong> We reserve the right to suspend or terminate your account at our sole discretion if we believe you have violated these Terms and Conditions.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>4. User Responsibilities</h5>
                                    <p>As a user of our Portal, you agree to:</p>
                                    <ul>
                                        <li>Provide accurate and complete information about yourself and your health status</li>
                                        <li>Use the Portal only for lawful purposes and in accordance with these Terms</li>
                                        <li>Not attempt to gain unauthorized access to any part of the Portal</li>
                                        <li>Not use the Portal to transmit any malicious code or engage in any activity that could damage, disable, or impair the Portal</li>
                                        <li>Not use the Portal for any commercial purposes without our express written consent</li>
                                        <li>Comply with all applicable laws and regulations</li>
                                    </ul>
                                </div>

                                <div class="mb-5">
                                    <h5>5. Medical Disclaimer</h5>
                                    <p>The content provided through the REJUVENATE Digital Health Portal, including any communications with healthcare providers, is for informational purposes only and is not intended to replace the relationship between you and your physician or other healthcare provider.</p>
                                    <p><strong>Emergency Situations:</strong> The Portal is not designed for emergency medical situations. If you are experiencing a medical emergency, please call your local emergency services immediately.</p>
                                    <p>Always seek the advice of your physician or other qualified health provider with any questions you may have regarding a medical condition.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>6. Intellectual Property</h5>
                                    <p>All content, features, and functionality of the REJUVENATE Digital Health Portal, including but not limited to text, graphics, logos, and software, are the exclusive property of REJUVENATE Digital Health and are protected by international copyright, trademark, and other intellectual property laws.</p>
                                    <p>You may not reproduce, distribute, modify, create derivative works of, publicly display, or otherwise use any content from our Portal without our express written permission.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>7. Privacy and Data Protection</h5>
                                    <p>Your use of our Portal is also governed by our Privacy Policy, which explains how we collect, use, and protect your personal and health information. By using our Portal, you consent to the practices described in our Privacy Policy.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>8. Limitation of Liability</h5>
                                    <p>To the fullest extent permitted by law, REJUVENATE Digital Health, its affiliates, and their respective officers, directors, employees, and agents shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, use, or other intangible losses, resulting from:</p>
                                    <ul>
                                        <li>Your access to or use of or inability to access or use the Portal</li>
                                        <li>Any conduct or content of any third party on the Portal</li>
                                        <li>Any content obtained from the Portal</li>
                                        <li>Unauthorized access, use, or alteration of your transmissions or content</li>
                                    </ul>
                                </div>

                                <div class="mb-5">
                                    <h5>9. Indemnification</h5>
                                    <p>You agree to defend, indemnify, and hold harmless REJUVENATE Digital Health and its affiliates, and their respective officers, directors, employees, and agents from and against any claims, liabilities, damages, judgments, awards, losses, costs, expenses, or fees (including reasonable attorneys' fees) arising out of or relating to your violation of these Terms and Conditions or your use of the Portal.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>10. Modifications to Terms</h5>
                                    <p>We reserve the right, at our sole discretion, to modify or replace these Terms and Conditions at any time. If a revision is material, we will provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
                                    <p>By continuing to access or use our Portal after those revisions become effective, you agree to be bound by the revised terms.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>11. Governing Law</h5>
                                    <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of India, without regard to its conflict of law provisions.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>12. Dispute Resolution</h5>
                                    <p>Any dispute arising from these Terms and Conditions or your use of the Portal shall be resolved through informal negotiations. If the dispute cannot be resolved informally, it shall be submitted to binding arbitration in accordance with the Indian Arbitration and Conciliation Act, 1996.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>13. Severability</h5>
                                    <p>If any provision of these Terms and Conditions is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>14. Contact Information</h5>
                                    <p>If you have any questions about these Terms and Conditions, please contact us:</p>
                                    <div class="contact-info">
                                        <p><strong>REJUVENATE Digital Health</strong></p>
                                        <p><i class="far fa-phone-alt me-2"></i> <a href="tel:+91-<?= $contact['phone']?>">+91-<?= $contact['phone']?></a></p>
                                        <p><i class="far fa-envelope me-2"></i> <a href="mailto:<?= $contact['email']?>"><?= $contact['email']?></a></p>
                                        <p><i class="fal fa-map-marker-alt me-2"></i> <?= $contact['address']?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact-info Section Start -->
    <section class="contact-info-section section-padding pt-5 pb-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <div class="contact-info-box-items">
                        <div class="icon">
                            <i class="far fa-phone-alt"></i>
                        </div>
                        <div class="content">
                            <h6>Call Us</h6>
                            <a href="tel:+91-<?= $contact['phone']?>">+91-<?= $contact['phone']?></a><br>
                            <a href="tel:+91-<?= $contact['wp_number']?>">+91-<?= $contact['wp_number']?></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-info-box-items">
                        <div class="icon">
                            <i class="far fa-envelope"></i>
                        </div>
                        <div class="content">
                            <h6>Send Email</h6>
                            <a href="mailto:<?= $contact['email']?>"><?= $contact['email']?></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-info-box-items">
                        <div class="icon">
                            <i class="fal fa-map-marker-alt"></i>
                        </div>
                        <div class="content">
                            <h6>Location</h6>
                            <?= $contact['address']?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php")?>
</body>
</html>