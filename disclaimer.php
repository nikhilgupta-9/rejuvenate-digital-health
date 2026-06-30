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
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="Disclaimer for REJUVENATE Digital Health — important information about the limitations of our digital health portal and the content provided.">
    <title>Disclaimer | REJUVENATE Digital Health</title>
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
        body{
            color: #fff;
        }
    </style>
</head>
<body>
    <?php include("header.php") ?>

    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Disclaimer</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>Disclaimer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Disclaimer Content Section -->
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="contact-appointment-wrapper-5">
                <div class="row g-4">
                    <div class="col-lg-12">
                        <div class="contact-appointment-box">
                            <h3 class="mb-4">REJUVENATE Digital Health — Disclaimer</h3>
                            <p class="text-muted"><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>

                            <div class="disclaimer-content">

                                <div class="mb-5">
                                    <h5>1. General Information Only</h5>
                                    <p>The information provided on the REJUVENATE Digital Health portal ("Portal") is intended for general informational purposes only. All content, including but not limited to text, graphics, images, and other material, is not a substitute for professional medical advice, diagnosis, or treatment.</p>
                                    <p>Always seek the advice of your physician, qualified health provider, or other licensed healthcare professional with any questions you may have regarding a medical condition, before making any healthcare decisions.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>2. No Doctor–Patient Relationship</h5>
                                    <p>Use of this Portal does not create a doctor–patient or therapist–patient relationship between you and REJUVENATE Digital Health or any of its affiliated doctors. Communications made through this Portal, including appointment bookings, queries submitted via contact forms, or messages sent to healthcare professionals, do not establish such a relationship.</p>
                                    <p>A formal doctor–patient relationship is established only after a healthcare professional has agreed to undertake your case and you have had a formal consultation with that professional.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>3. Medical Emergency</h5>
                                    <p>This Portal is <strong>not</strong> designed for medical emergencies. If you or anyone else is experiencing a life-threatening situation or a medical emergency, please:</p>
                                    <ul>
                                        <li>Call your local emergency number (e.g., 112 or 108 in India) immediately.</li>
                                        <li>Go directly to the nearest hospital emergency room.</li>
                                        <li>Do not rely on this Portal for urgent medical assistance.</li>
                                    </ul>
                                </div>

                                <div class="mb-5">
                                    <h5>4. Accuracy of Information</h5>
                                    <p>While REJUVENATE Digital Health makes reasonable efforts to provide accurate, up-to-date, and complete information on this Portal, we make no representations or warranties of any kind, express or implied, regarding the accuracy, reliability, completeness, or timeliness of any information on the Portal.</p>
                                    <p>Medical knowledge evolves rapidly. Content that was accurate at the time of publication may become outdated. REJUVENATE Digital Health is not obligated to update any information on the Portal and expressly disclaims all liability for errors or omissions in its content.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>5. School Health Portal Disclaimer</h5>
                                    <p>The School Health Portal section of this website is designed to facilitate health record management for educational institutions, students, and teachers. Please note:</p>
                                    <ul>
                                        <li>Health data entered by school staff or teachers is for administrative and wellness monitoring purposes only and does not constitute a clinical diagnosis.</li>
                                        <li>BMI, health assessments, and related metrics are indicative and should be interpreted by a qualified healthcare professional.</li>
                                        <li>School administrators are responsible for ensuring that data entered into the School Health Portal is accurate and used in accordance with applicable laws and school policies.</li>
                                        <li>REJUVENATE Digital Health is not liable for decisions made based solely on information available within the School Health Portal.</li>
                                    </ul>
                                </div>

                                <div class="mb-5">
                                    <h5>6. ABHA / Health ID Information</h5>
                                    <p>Information related to the Ayushman Bharat Health Account (ABHA) is provided for informational assistance only. REJUVENATE Digital Health acts as a facilitator for ABHA linkage and does not represent the National Health Authority (NHA) or any government body. For official ABHA-related queries, please visit the official NHA website.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>7. Third-Party Links and Services</h5>
                                    <p>This Portal may contain links to third-party websites or services that are not owned or controlled by REJUVENATE Digital Health. We have no control over, and assume no responsibility for, the content, privacy policies, or practices of any third-party websites. We strongly advise you to review the privacy policy and terms of service of every site you visit.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>8. Testimonials and Reviews</h5>
                                    <p>Any testimonials or reviews displayed on this Portal represent individual experiences of specific patients or users. These are individual results and results may vary. REJUVENATE Digital Health does not claim that all users will achieve the same or similar outcomes.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>9. Limitation of Liability</h5>
                                    <p>To the maximum extent permitted by applicable law, REJUVENATE Digital Health, its directors, employees, partners, agents, and suppliers shall not be liable for any indirect, incidental, special, consequential, or punitive damages — including without limitation, loss of data, loss of profits, or loss of goodwill — arising from:</p>
                                    <ul>
                                        <li>Your access to, use of, or inability to use the Portal.</li>
                                        <li>Any reliance on information, content, or materials available on the Portal.</li>
                                        <li>Unauthorized access to or alteration of your transmissions or data.</li>
                                        <li>Any other matter relating to the Portal.</li>
                                    </ul>
                                </div>

                                <div class="mb-5">
                                    <h5>10. Changes to This Disclaimer</h5>
                                    <p>REJUVENATE Digital Health reserves the right to modify or replace this Disclaimer at any time without prior notice. It is your responsibility to review this page periodically. Continued use of the Portal after changes are posted constitutes your acceptance of the updated Disclaimer.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>11. Governing Law</h5>
                                    <p>This Disclaimer is governed by and construed in accordance with the laws of India. Any disputes arising in relation to this Disclaimer shall be subject to the exclusive jurisdiction of the courts of India.</p>
                                </div>

                                <div class="mb-5">
                                    <h5>12. Contact Us</h5>
                                    <p>If you have any questions or concerns about this Disclaimer, please contact us:</p>
                                    <div class="contact-info">
                                        <p><strong>REJUVENATE Digital Health</strong></p>
                                        <p><i class="far fa-phone-alt me-2"></i> <a href="tel:+91-<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a></p>
                                        <p><i class="far fa-envelope me-2"></i> <a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a></p>
                                        <p><i class="fal fa-map-marker-alt me-2"></i> <?= $contact['address'] ?></p>
                                    </div>
                                </div>

                                <!-- Cross-links to other legal pages -->
                                <div class="mb-3 p-4 rounded" style="background: var(--bg); border-left: 4px solid var(--theme-color);">
                                    <h6 class="mb-2">Related Legal Documents</h6>
                                    <p class="mb-1 text-dark"><i class="fas fa-chevron-right me-2 text-primary"></i><a href="<?= BASE_URL ?>privacy-policy.php" class="rjv-link">Privacy Policy</a> — How we collect, use, and protect your data.</p>
                                    <p class="mb-0 text-dark"><i class="fas fa-chevron-right me-2 text-primary"></i><a href="<?= BASE_URL ?>terms-and-condition.php" class="rjv-link">Terms &amp; Conditions</a> — Rules governing your use of this Portal.</p>
                                </div>

                            </div><!-- /.disclaimer-content -->
                        </div>
                    </div>
                </div>
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
                <!-- <div class="col-lg-4">
                    <div class="contact-info-box-items">
                        <div class="icon"><i class="fal fa-map-marker-alt"></i></div>
                        <div class="content">
                            <h6>Location</h6>
                            <?= $contact['address'] ?>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>
</body>
</html>
