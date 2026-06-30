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
    <meta name="description" content="Legal Compliance — REJUVENATE Digital Health's commitment to Indian laws and regulations governing digital health services.">
    <title>Legal Compliance | REJUVENATE Digital Health</title>
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
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Legal Compliance</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>Legal Compliance</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal Compliance Content Section -->
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="contact-appointment-wrapper-5">
                <div class="row g-4">
                    <div class="col-lg-12">
                        <div class="contact-appointment-box">
                            <h3 class="mb-4">Legal &amp; Regulatory Compliance</h3>
                            <p class="text-muted"><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
                            <p>REJUVENATE Digital Health is committed to operating in full compliance with all applicable laws and regulations governing digital health services in India. This page outlines the key legislative frameworks under which we operate.</p>

                            <div class="legal-compliance-content">

                                <!-- 1 -->
                                <div class="mb-5">
                                    <h5>1. Information Technology Act, 2000 (IT Act)</h5>
                                    <p>REJUVENATE Digital Health complies with the <strong>Information Technology Act, 2000</strong> and its subsequent amendments, including the IT (Amendment) Act, 2008. Our obligations include:</p>
                                    <ul>
                                        <li>Maintaining reasonable security practices and procedures as mandated under Section 43A.</li>
                                        <li>Implementing IT (Reasonable Security Practices and Procedures and Sensitive Personal Data or Information) Rules, 2011 for handling sensitive personal data, including health records.</li>
                                        <li>Providing a clear Privacy Policy in compliance with Rule 4 of the SPDI Rules.</li>
                                        <li>Not disclosing sensitive personal data or information to third parties without prior written consent of the user, except where required by law.</li>
                                    </ul>
                                </div>

                                <!-- 2 -->
                                <div class="mb-5">
                                    <h5>2. Digital Personal Data Protection Act, 2023 (DPDP Act)</h5>
                                    <p>We align our data practices with the <strong>Digital Personal Data Protection Act, 2023 (DPDP Act)</strong>, India's landmark personal data protection legislation. Our commitments include:</p>
                                    <ul>
                                        <li><strong>Lawful Processing:</strong> Personal data is processed only for legitimate purposes with the explicit consent of the Data Principal (user).</li>
                                        <li><strong>Purpose Limitation:</strong> Data collected is used solely for the purpose for which it was obtained and is not retained beyond the required period.</li>
                                        <li><strong>Data Minimisation:</strong> We collect only the minimum personal data necessary for the delivery of our services.</li>
                                        <li><strong>Rights of Data Principal:</strong> Users have the right to access their data, correct inaccuracies, nominate a representative, and withdraw consent at any time.</li>
                                        <li><strong>Data Fiduciary Obligations:</strong> As a Data Fiduciary, we ensure data accuracy, implement appropriate security safeguards, and report any personal data breach to the Data Protection Board of India and affected users without undue delay.</li>
                                        <li><strong>Children's Data:</strong> We do not knowingly process personal data of children under the age of 18 without verifiable parental consent.</li>
                                    </ul>
                                </div>

                                <!-- 3 -->
                                <div class="mb-5">
                                    <h5>3. Telemedicine Practice Guidelines, 2020</h5>
                                    <p>All telehealth and online consultation services facilitated through this Portal comply with the <strong>Telemedicine Practice Guidelines issued by the Ministry of Health and Family Welfare (MoHFW), Government of India, in March 2020</strong>, as appended to the Indian Medical Council Act, 1956.</p>
                                    <ul>
                                        <li>Only Registered Medical Practitioners (RMPs) listed with the National Medical Register or State Medical Registers are permitted to provide online consultations through our platform.</li>
                                        <li>Doctors are responsible for maintaining patient confidentiality and adhering to professional ethical standards during telemedicine consultations.</li>
                                        <li>Prescriptions issued via telemedicine conform to applicable guidelines regarding drugs that may or may not be prescribed via teleconsultation.</li>
                                        <li>Patients are informed about the limitations of telemedicine, and emergencies are directed to in-person care.</li>
                                    </ul>
                                </div>

                                <!-- 4 -->
                                <div class="mb-5">
                                    <h5>4. National Medical Commission (NMC) Act, 2019</h5>
                                    <p>REJUVENATE Digital Health ensures that all medical professionals associated with our platform are verified against the <strong>National Medical Register</strong> maintained under the NMC Act, 2019. We do not allow unregistered or de-registered practitioners to offer services through our Portal. Any complaints regarding professional misconduct can be referred to the concerned State Medical Council or the National Medical Commission.</p>
                                </div>

                                <!-- 5 -->
                                <div class="mb-5">
                                    <h5>5. Ayushman Bharat Digital Mission (ABDM) &amp; ABHA Compliance</h5>
                                    <p>REJUVENATE Digital Health participates in and complies with the <strong>Ayushman Bharat Digital Mission (ABDM)</strong> framework governed by the National Health Authority (NHA):</p>
                                    <ul>
                                        <li>ABHA (Ayushman Bharat Health Account) creation and linkage is facilitated in accordance with NHA-prescribed processes.</li>
                                        <li>Patient health records shared through ABDM-linked systems adhere to the Health Data Management Policy published by the NHA.</li>
                                        <li>Consent for sharing health data is obtained electronically and in accordance with the Health Data Management Policy.</li>
                                        <li>We do not access or share ABDM-linked health records without explicit, informed, and revocable consent of the patient.</li>
                                    </ul>
                                </div>

                                <!-- 6 -->
                                <div class="mb-5">
                                    <h5>6. Clinical Establishments (Registration &amp; Regulation) Act, 2010</h5>
                                    <p>Where applicable, REJUVENATE Digital Health ensures that partner clinical establishments are duly registered under the <strong>Clinical Establishments Act, 2010</strong> or relevant State-level equivalents. We encourage partner hospitals and clinics to maintain their registrations in good standing and comply with the prescribed standards of services.</p>
                                </div>

                                <!-- 7 -->
                                <div class="mb-5">
                                    <h5>7. Consumer Protection Act, 2019</h5>
                                    <p>In accordance with the <strong>Consumer Protection Act, 2019</strong> and the <strong>Consumer Protection (E-Commerce) Rules, 2020</strong>, REJUVENATE Digital Health:</p>
                                    <ul>
                                        <li>Provides clear, accurate information about all services offered on this Portal.</li>
                                        <li>Does not engage in unfair or restrictive trade practices.</li>
                                        <li>Maintains a Grievance Redressal mechanism (see below) to address user complaints promptly.</li>
                                        <li>Ensures that all charges, fees, and service terms are disclosed transparently before the user commits to any service.</li>
                                    </ul>
                                </div>

                                <!-- 8 -->
                                <div class="mb-5">
                                    <h5>8. School Health Programme Compliance</h5>
                                    <p>The School Health Portal module of REJUVENATE Digital Health operates in alignment with:</p>
                                    <ul>
                                        <li><strong>National School Health Programme</strong> guidelines issued by MoHFW.</li>
                                        <li><strong>RTE Act, 2009</strong> provisions regarding student privacy and welfare.</li>
                                        <li><strong>POCSO Act, 2012</strong> — Health data of minors is treated with the highest level of confidentiality and protection.</li>
                                        <li>Data of students is accessible only to authorised school administrators and healthcare professionals assigned to the school.</li>
                                    </ul>
                                </div>

                                <!-- 9 -->
                                <div class="mb-5">
                                    <h5>9. Data Localisation &amp; Security Standards</h5>
                                    <p>All personal data and health records of Indian users are stored on servers located within the territory of India, in compliance with applicable data localisation requirements. We implement the following security standards:</p>
                                    <ul>
                                        <li>SSL/TLS encryption for all data in transit.</li>
                                        <li>Encrypted storage for sensitive health records and personally identifiable information (PII).</li>
                                        <li>Role-based access controls limiting data access to authorised personnel only.</li>
                                        <li>Regular security audits and vulnerability assessments.</li>
                                        <li>Incident response plan for data breaches, with mandatory notification to affected users and authorities.</li>
                                    </ul>
                                </div>

                                <!-- 10 -->
                                <div class="mb-5">
                                    <h5>10. Grievance Redressal Mechanism</h5>
                                    <p>In compliance with the IT Act, DPDP Act, and Consumer Protection Act, we have appointed a <strong>Grievance Officer</strong> to address any complaints or concerns regarding our services or data practices.</p>
                                    <div class="p-4 rounded mb-3" style="background: rgba(12,116,197,.1); border-left: 4px solid var(--theme-color);">
                                        <h6 class="mb-2"><i class="fas fa-user-shield me-2"></i>Grievance Officer</h6>
                                        <p class="mb-1"><strong>Organisation:</strong> REJUVENATE Digital Health</p>
                                        <p class="mb-1"><i class="far fa-envelope me-2"></i><a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a></p>
                                        <p class="mb-1"><i class="far fa-phone-alt me-2"></i><a href="tel:+91-<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a></p>
                                        <p class="mb-0"><strong>Response Time:</strong> We aim to acknowledge complaints within <strong>48 hours</strong> and resolve them within <strong>30 days</strong> of receipt.</p>
                                    </div>
                                    <p>Complaints may be submitted via email, phone, or our <a href="<?= BASE_URL ?>contact-us.php">Contact Us</a> page.</p>
                                </div>

                                <!-- 11 -->
                                <div class="mb-5">
                                    <h5>11. Compliance Updates</h5>
                                    <p>The legal and regulatory landscape for digital health in India is evolving rapidly. REJUVENATE Digital Health is committed to reviewing and updating its compliance practices as new laws, guidelines, or notifications are issued by relevant authorities including MoHFW, NHA, MeitY, NMC, and the Data Protection Board of India.</p>
                                    <p>This page will be updated whenever there are material changes to our compliance framework. We encourage users to check this page periodically.</p>
                                </div>

                                <!-- Cross-links -->
                                <div class="mb-3 p-4 rounded" style="background: rgba(12,116,197,.08); border-left: 4px solid var(--theme-color);">
                                    <h6 class="mb-2">Related Legal Documents</h6>
                                    <p class="mb-1"><i class="fas fa-chevron-right me-2 "></i><a href="<?= BASE_URL ?>privacy-policy.php" class="rjv-link">Privacy Policy</a> — How we collect, use, and protect your personal data.</p>
                                    <p class="mb-1"><i class="fas fa-chevron-right me-2 "></i><a href="<?= BASE_URL ?>terms-and-condition.php" class="rjv-link">Terms &amp; Conditions</a> — Rules governing your use of this Portal.</p>
                                    <p class="mb-0"><i class="fas fa-chevron-right me-2 "></i><a href="<?= BASE_URL ?>disclaimer.php" class="rjv-link">Disclaimer</a> — Important limitations regarding content on this Portal.</p>
                                </div>

                            </div><!-- /.legal-compliance-content -->
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
               
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>
</body>
</html>
