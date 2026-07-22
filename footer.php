<?php
include_once "config/connect.php";
include_once "util/function.php";
$contact = contact_us();
$logo    = get_header_logo();
?>

<!-- ── Newsletter ── -->
<section class="rjv-newsletter">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <span class="rjv-nl-label">Our Newsletter</span>
        <h3 class="rjv-nl-title">Never Miss a Health Update — Subscribe Today</h3>
      </div>
      <div class="col-lg-6">
        <form class="rjv-nl-form" onsubmit="return false;">
          <input type="email" placeholder="Enter your email address">
          <button type="submit"><i class="fas fa-paper-plane me-1"></i>Subscribe</button>
        </form>
        <p class="rjv-nl-note">By subscribing you agree to our <a href="<?= BASE_URL ?>privacy-policy.php">Privacy Policy</a></p>
      </div>
    </div>
  </div>
</section>

<!-- ── Main Footer ── -->
<footer class="rjv-footer">

  <!-- Wave divider -->
  <!-- <div class="rjv-footer-wave">
    <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,30 C360,60 1080,0 1440,30 L1440,0 L0,0 Z" fill="#ffffff"/>
    </svg>
  </div> -->

  <div class="container-fluid rjv-footer-body p-3">
    <div class="row g-5 py-4">

      <!-- Col 1: Brand -->
      <div class="col-xxl-4 col-xl-4 col-lg-5 col-md-6">
        <a href="<?= BASE_URL ?>" class="rjv-footer-logo">
          <img src="<?= BASE_URL . $logo ?>" alt="Rejuvenate Digital Health">
        </a>
        <p class="rjv-footer-about">
          Providing high-quality medical care with a patient-centered approach — combining expert doctors, modern equipment, and advanced digital health solutions for better outcomes.
        </p>

        <div class="rjv-contact-badges">
          <a href="tel:+91<?= $contact['phone'] ?>" class="rjv-badge">
            <span class="rjv-badge-icon"><i class="fas fa-phone-alt"></i></span>
            <div>
              <small>Call Us</small>
              <strong>+91-<?= $contact['phone'] ?></strong>
            </div>
          </a>
          <a href="mailto:<?= $contact['email'] ?>" class="rjv-badge">
            <span class="rjv-badge-icon"><i class="fas fa-envelope"></i></span>
            <div>
              <small>Email Us</small>
              <strong><?= $contact['email'] ?></strong>
            </div>
          </a>
        </div>

        <div class="rjv-socials">
          <?php if (!empty($contact['facebook'])): ?>
            <a href="<?= $contact['facebook'] ?>" title="Facebook" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
          <?php endif; ?>
          <?php if (!empty($contact['twitter'])): ?>
            <a href="<?= $contact['twitter'] ?>" title="Twitter" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
          <?php endif; ?>
          <?php if (!empty($contact['instagram'])): ?>
            <a href="<?= $contact['instagram'] ?>" title="YouTube" target="_blank" rel="noopener"><i class="fab fa-youtube"></i></a>
          <?php endif; ?>
          <?php if (!empty($contact['linkdin'])): ?>
            <a href="<?= $contact['linkdin'] ?>" title="LinkedIn" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
          <?php endif; ?>
          <?php if (!empty($contact['wp_number'])): ?>
            <a href="https://wa.me/91<?= preg_replace('/\D/', '', $contact['wp_number']) ?>" title="WhatsApp" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Col 2: Services -->
      <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-6 col-sm-6">
        <h4 class="rjv-footer-heading">Our Services</h4>
        <ul class="rjv-footer-links">
          <?php foreach (get_online_book(5) as $svc): ?>
            <li><a href="<?= BASE_URL ?>online-services/<?= $svc['slug_url'] ?>"><i class="fas fa-chevron-right"></i><?= htmlspecialchars($svc['pro_name']) ?></a></li>
          <?php endforeach; ?>
          <?php foreach (get_sub_category_home() as $dep): ?>
            <li><a href="<?= BASE_URL ?>department/<?= $dep['slug_url'] ?>"><i class="fas fa-chevron-right"></i><?= htmlspecialchars($dep['categories']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Col 3: School Portal + Quick Links -->
      <div class="col-xxl-3 col-xl-3 col-lg-4 col-md-6 col-sm-6">
        <h4 class="rjv-footer-heading">School Health Portal</h4>
        <ul class="rjv-footer-links">
          <li><a href="<?= BASE_URL ?>school-register.php"><i class="fas fa-school"></i>Register Your School</a></li>
          <li><a href="<?= BASE_URL ?>student-register.php"><i class="fas fa-user-graduate"></i>Student Registration</a></li>
          <li><a href="<?= BASE_URL ?>teacher-register.php"><i class="fas fa-chalkboard-teacher"></i>Teacher Registration</a></li>
          <li>
            <a href="<?= BASE_URL ?>login.php">
              <i class="fas fa-sign-in-alt"></i>
              <?php if (!empty($_SESSION['school_logged_in'])): ?>
                <?= htmlspecialchars($_SESSION['school_name']) ?> (Dashboard)
              <?php elseif (!empty($_SESSION['student_logged_in'])): ?>
                <?= htmlspecialchars($_SESSION['student_name']) ?> (Dashboard)
              <?php elseif (!empty($_SESSION['teacher_logged_in'])): ?>
                <?= htmlspecialchars($_SESSION['teacher_name']) ?> (Dashboard)
              <?php else: ?>
                Login to Portal
              <?php endif; ?>
            </a>
          </li>
        </ul>

        <h4 class="rjv-footer-heading mt-4">Quick Links</h4>
        <ul class="rjv-footer-links">
          <li><a href="<?= BASE_URL ?>about-us.php"><i class="fas fa-chevron-right"></i>About Us</a></li>
          <li><a href="<?= BASE_URL ?>contact-us.php"><i class="fas fa-chevron-right"></i>Contact Us</a></li>
          <li><a href="<?= BASE_URL ?>privacy-policy.php"><i class="fas fa-chevron-right"></i>Privacy Policy</a></li>
          <li><a href="<?= BASE_URL ?>terms-and-condition.php"><i class="fas fa-chevron-right"></i>Terms &amp; Conditions</a></li>
          <li><a href="<?= BASE_URL ?>disclaimer.php"><i class="fas fa-chevron-right"></i>Disclaimer</a></li>
          <li><a href="<?= BASE_URL ?>legal-compliance.php"><i class="fas fa-chevron-right"></i>Legal Compliance</a></li>
          <li><a href="<?= BASE_URL ?>faq.php"><i class="fas fa-chevron-right"></i>FAQ's</a></li>
        </ul>
      </div>

      <!-- Col 4: Find Us -->
      <div class="col-xxl-3 col-xl-3 col-lg-5 col-md-6">
        <h4 class="rjv-footer-heading">Find Us</h4>
        <div class="rjv-contact-list">
          <!-- <div class="rjv-cl-item">
            <div class="rjv-cl-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="rjv-cl-text">
              <span>Our Address</span>
              <p><?= nl2br(htmlspecialchars($contact['address'])) ?></p>
            </div>
          </div> -->
          <div class="rjv-cl-item">
            <div class="rjv-cl-icon"><i class="fas fa-phone-alt"></i></div>
            <div class="rjv-cl-text">
              <span>Phone Numbers</span>
              <p>
                <a href="tel:+91<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a><br>
                <a href="tel:+91<?= $contact['wp_number'] ?>">+91-<?= $contact['wp_number'] ?></a>
              </p>
            </div>
          </div>
          <div class="rjv-cl-item">
            <div class="rjv-cl-icon"><i class="fas fa-clock"></i></div>
            <div class="rjv-cl-text">
              <span>Working Hours</span>
              <p>Mon – Sat: 9:00 AM – 7:00 PM<br>Sunday: By Appointment</p>
            </div>
          </div>
          <div class="rjv-cl-item">
            <div class="rjv-cl-icon"><i class="fas fa-envelope"></i></div>
            <div class="rjv-cl-text">
              <span>Email Address</span>
              <p><a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a></p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="rjv-footer-divider"></div>

  <!-- Bottom bar -->
  <div class="rjv-footer-bottom">
    <div class="container">
      <div class="rjv-footer-bottom-inner">
        <p>&copy; <?= date('Y') ?> <strong>Rejuvenate Digital Health</strong>. All Rights Reserved.</p>
        <div class="rjv-footer-bottom-links">
          <a href="<?= BASE_URL ?>privacy-policy.php">Privacy Policy</a>
          <span>|</span>
          <a href="<?= BASE_URL ?>terms-and-condition.php">Terms</a>
          <span>|</span>
          <a href="<?= BASE_URL ?>sitemap.xml">Sitemap</a>
        </div>
        <p class="rjv-footer-tagline">Designed & Developed <i class="fas fa-heart" style="color:#e74c3c;"></i> By <a href="https://nikhilworks.com/" class="text-light">Nikhil Works</a></p>
      </div>
    </div>
  </div>

</footer>

<!-- Back to Top -->
<!-- <button class="rjv-back-top" id="rjvBackTop" title="Back to top">
  <i class="fas fa-chevron-up"></i>
</button> -->

<script src="<?= BASE_URL ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/jquery.nice-select.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/odometer.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/jquery.appear.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/swiper-bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/jquery.meanmenu.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/jquery.magnific-popup.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/wow.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/gsap.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/ScrollTrigger.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/SplitText.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/splitType.js"></script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/custom.js"></script>

<script>
(function () {
  var btn = document.getElementById('rjvBackTop');
  if (!btn) return;
  window.addEventListener('scroll', function () {
    btn.classList.toggle('show', window.scrollY > 300);
  });
  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();
</script>