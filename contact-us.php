<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$testimonials = testimonial();

// Helper function for time elapsed
function time_elapsed_string($datetime) {
    if (empty($datetime)) return 'Recently';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - Contact Us</title>
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
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Contact Us</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>Contact Us</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Info Section Start -->
    <section class="contact-info-section section-padding pt-5 pb-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="contact-info-box-items text-center p-4 border rounded-3 bg-white shadow-sm h-100">
                        <div class="icon mb-3">
                            <i class="far fa-phone-alt fs-1 text-light"></i>
                        </div>
                        <div class="content">
                            <h6 class="fw-bold">Call Us</h6>
                            <a href="tel:+91-<?= $contact['phone']?>" class="text-decoration-none d-block text-dark">
                                +91-<?= $contact['phone']?>
                            </a>
                            <a href="tel:+91-<?= $contact['wp_number']?>" class="text-decoration-none d-block text-dark">
                                +91-<?= $contact['wp_number']?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="contact-info-box-items text-center p-4 border rounded-3 bg-white shadow-sm h-100">
                        <div class="icon mb-3">
                            <i class="far fa-envelope fs-1 text-light"></i>
                        </div>
                        <div class="content">
                            <h6 class="fw-bold">Send Email</h6>
                            <a href="mailto:<?= $contact['email']?>" class="text-decoration-none text-dark">
                                <?= $contact['email']?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="contact-info-box-items text-center p-4 border rounded-3 bg-white shadow-sm h-100">
                        <div class="icon mb-3">
                            <i class="fal fa-map-marker-alt fs-1 text-light"></i>
                        </div>
                        <div class="content">
                            <h6 class="fw-bold">Location</h6>
                            <p class="mb-0"><?= $contact['address']?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            
        </div>
    </section>

    <!-- Contact Form Section Start -->
    <section class="contact-appointment-section section-padding">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="contact-appointment-image">
                        <img src="<?= BASE_URL?>assets/img/inner/contact/01.jpg" alt="Contact Us" class="img-fluid rounded-3 shadow">
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="contact-appointment-box p-4 border rounded-3 bg-white shadow-sm">
                        <h3 class="mb-4">Get In Touch</h3>
                        <form action="#" id="contactForm">
                            <div class="row g-3">
                                <div class="col-lg-6 wow fadeInUp" data-wow-delay=".3s">
                                    <div class="form-clt">
                                        <label class="form-label fw-semibold">Full Name</label>
                                        <input type="text" name="fname" class="form-control" placeholder="Your Name" required>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6 wow fadeInUp" data-wow-delay=".5s">
                                    <div class="form-clt">
                                        <label class="form-label fw-semibold">Email Address</label>
                                        <input type="email" name="email" class="form-control" placeholder="Your email" required>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6 wow fadeInUp" data-wow-delay=".3s">
                                    <div class="form-clt">
                                        <label class="form-label fw-semibold">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" placeholder="Your phone" required>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6 wow fadeInUp" data-wow-delay=".5s">
                                    <div class="form-clt">
                                        <label class="form-label fw-semibold">Subject</label>
                                        <select class="form-select" name="subject">
                                            <option value="">Select Subject</option>
                                            <option value="General Inquiry">General Inquiry</option>
                                            <option value="Support">Support</option>
                                            <option value="Feedback">Feedback</option>
                                            <option value="Partnership">Partnership</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-12 wow fadeInUp" data-wow-delay=".3s">
                                    <div class="form-clt">
                                        <label class="form-label fw-semibold">Your Message</label>
                                        <textarea name="message" class="form-control" rows="5" placeholder="Write your message..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-12 wow fadeInUp" data-wow-delay=".3s">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="far fa-paper-plane me-2"></i>
                                        Send Message
                                    </button>
                                </div>
                                
                                <div id="contactMessage" class="alert alert-success d-none"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section Start -->
    <section class="testimonial-section section-padding bg-light">
        <div class="container">
            <!-- Section Header -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="display-6 fw-bold">What Our Patients Say</h2>
                    <p class="text-muted">Real experiences from our valued patients</p>
                </div>
            </div>

            <!-- Testimonial Slider -->
            <div class="testimonial-right-item">
                <div class="swiper testimonial-slider-1">
                    <div class="swiper-wrapper">
                        <?php 
                        if (!empty($testimonials)) {
                            foreach ($testimonials as $testi) {
                                $rating = isset($testi['rating']) ? intval($testi['rating']) : 5;

                                 $hasImage = !empty($testi['client_photo']) && file_exists($testi['client_photo']);
                                        $firstLetter = strtoupper(substr(trim($testi['client_name']), 0, 1));
                        ?>
                            <div class="swiper-slide">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <!-- Header -->
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex gap-3">
                                                <?php if ($hasImage): ?>
                                                        <img src="<?= htmlspecialchars($testi['client_photo']) ?>"
                                                            alt="<?= htmlspecialchars($testi['client_name']) ?>"
                                                            class="reviewer-avatar">
                                                    <?php else: ?>
                                                        <div class="reviewer-avatar avatar-placeholder">
                                                            <?= htmlspecialchars($firstLetter) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                <div>
                                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($testi['client_name']) ?></h6>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($testi['client_title'] ?? 'Verified Patient') ?>
                                                        <?php if (!empty($testi['client_company'])): ?>
                                                            <span class="mx-1">•</span>
                                                            <?= htmlspecialchars($testi['client_company']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div>
                                                <img src="assets/img/home-5/testimonial/01.svg" alt="Google" style="width: 28px; height: 28px;">
                                            </div>
                                        </div>

                                        <!-- Rating Stars -->
                                        <div class="mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $rating ? 'text-warning' : 'text-secondary' ?>" 
                                                   style="font-size: 18px;"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2 text-muted small fw-semibold"><?= number_format($rating, 1) ?></span>
                                        </div>

                                        <!-- Review Text -->
                                        <p class="card-text fst-italic" style="font-size: 15px; line-height: 1.6;">
                                            “<?= htmlspecialchars($testi['testimonial_text']) ?>”
                                        </p>

                                        <!-- Footer -->
                                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                <?= time_elapsed_string($testi['project_date'] ?? $testi['created_at'] ?? '') ?>
                                            </small>
                                            <?php if (!empty($testi['featured']) && $testi['featured'] == 1): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    Featured
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            }
                        } else { 
                        ?>
                            <div class="swiper-slide">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body p-4 text-center">
                                        <p class="text-muted mb-0">No testimonials available yet.</p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Slider Navigation -->
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php")?>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>assets/js/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper
        document.addEventListener('DOMContentLoaded', function() {
            const swiper = new Swiper('.testimonial-slider-1', {
                slidesPerView: 1,
                spaceBetween: 20,
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                breakpoints: {
                    576: {
                        slidesPerView: 1,
                        spaceBetween: 20,
                    },
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 25,
                    },
                    992: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    },
                },
            });
        });
    </script>
</body>
</html>