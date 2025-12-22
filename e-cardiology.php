<?php
include_once "config/connect.php";
include_once "util/function.php";

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>REJUVENATE Digital Health</title>
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

<body>
  <?php include("header.php") ?>
  <?php
  $doctors = get_doctor_byDepartment();
  ?>
  <section class="offer-banner container-fluid">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-9">
          <div class="offer-text">
            <h2>Get up to 70% off on all pathology health test & packages</h2>
            <ul class="offer-points">
              <li>State-of-the-art labs</li>
              <li>Smart reporting</li>
              <li>Free home sample collection</li>
            </ul>
          </div>
        </div>
        <div class="col-md-3">
          <div class="offer-img">
            <img src="<?= $site ?>assets/img/doc.svg" alt="">
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="service-details-section section-padding pb-5">
    <div class="container">
      <div class="service-details-wrapper">
        <div class="row g-5">
          <div class="col-lg-8">

            <div class="row">
              <h4 class="fw-bold mb-4">
                Top
                <?php
                if (!empty($doctors) && !empty($doctors[0]['depart_name'])) {
                  echo htmlspecialchars($doctors[0]['depart_name']);
                } else {
                  echo "Specialist";
                }
                ?>
                Doctors Available
              </h4>
            </div>
            <?php if (empty($doctors)): ?>
              <div class="no-doctors-minimal text-center py-5">
                <div class="empty-state-wrapper">
                  <!-- Clean illustration -->
                  <div class="mb-4">
                    <div class="empty-icon-placeholder mx-auto"
                      style="width: 120px; height: 120px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                      <i class="fas fa-user-md fa-3x text-muted"></i>
                    </div>
                  </div>

                  <!-- Concise messaging -->
                  <h4 class="fw-semibold text-dark mb-3">No Specialists Available</h4>
                  <p class="text-muted mb-4" style="max-width: 400px; margin: 0 auto;">
                    We're enhancing our specialist network for this department. Please check back soon or explore other options.
                  </p>

                  <!-- Primary actions -->
                  <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mb-4">
                    <a href="<?= $site ?>" class="btn btn-primary px-4">
                      <i class="fas fa-arrow-left me-2"></i>Other Departments
                    </a>
                    <a href="<?= $site ?>contact/" class="btn btn-outline-dark px-4">
                      <i class="fas fa-question-circle me-2"></i>Get Help
                    </a>
                  </div>

                  <!-- Quick alternatives -->
                  <div class="quick-links">
                    <p class="small text-muted mb-2">Quick alternatives:</p>
                    <div class="d-flex justify-content-center gap-3">
                      <a href="<?= $site ?>" class="small text-decoration-underline text-primary">Telemedicine</a>
                      <a href="<?= $site ?>" class="small text-decoration-underline text-primary">Health Checkups</a>
                      <a href="<?= $site ?>" class="small text-decoration-underline text-primary">Find Doctors</a>
                    </div>
                  </div>
                </div>
              </div>

              <style>
                .no-doctors-minimal {
                  background: #fafbfc;
                  border: 1px solid #eaeef2;
                  border-radius: 16px;
                  padding: 3rem 2rem;
                }

                .empty-icon-placeholder {
                  border: 2px dashed #dee2e6;
                }
              </style>
            <?php else: ?>

              <?php
              foreach ($doctors as $doctor) {
              ?>
                <div class="service-details-right-items">

                  <div class="team-box-items-2">
                    <div class="team-image">
                      <!-- <img src="<?= $site ?>admin/<?= $doctor['profile_img'] ?>" alt="img"> -->
                      <?php if (!empty($doctor['profile_image'])): ?>
                        <img src="<?= $site . "admin/" . $doctor['profile_image'] ?>" alt="Profile">
                      <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                          <i class="fas fa-user-md text-muted"></i>
                        </div>
                      <?php endif; ?>
                      <div class="exp-badge"><?= $doctor['experience_years'] ?> Year of Exp.</div>
                    </div>
                    <div class="team-content">
                      <h3><a href="<?= $site ?>doctor-profile/<?= $doctor['slug_url'] ?>"><?= $doctor['name'] ?></a></h3>
                      <p><?= $doctor['degrees'] ?></p>
                      <p><b>Language known:</b></p>
                      <p><?= $doctor['languages'] ?></p>
                    </div>
                    <div class="creat-book">
                      <p>Consultancy Fee</p>
                      <div class="price">₹<?= $doctor['consultation_fee'] ?> <span class="old-price">₹1598</span></div>
                      <a href="<?= $site ?>doctor-profile/<?= $doctor['slug_url'] ?>" class="btn view-profile w-100 mt-2">View Profile</a>
                      <a href="<?= $site ?>contact/" class="btn book-btn w-100 mt-2">Book an Appointment</a>
                      <small class="text-muted d-block mt-1">Get up to 100% cashback* <a href="<?= $site ?>terms-and-condition/" class="text-danger">T&C Apply</a></small>
                    </div>
                  </div>
                </div>
              <?php
              }
              ?>
            <?php endif; ?>


          </div>
          <div class="col-lg-4 ">
            <div class="service-details-sidebar sticky-style">
              <div class="sidebar-card offer-card">
                <h5>Book an Appointment with Expert Dietitians</h5>
                <p class="text-primary mb-1">Diet Consultation @Rs.299 Only!</p>
                <ul class="ps-3 mb-2">
                  <li>With over 50+ expert Dietitians</li>
                  <li>Get personalized diet plan</li>
                </ul>
                <a href="<?= $site ?>contact/" class="btn book-btn w-100">Book Now</a>
              </div>
              <div class="sidebar-card">
                <h5>We are Here to Help!</h5>
                <p class="text-muted mb-3">Get instant call back in few mins</p>
                <form>
                  <input type="text" class="form-control mb-2" placeholder="Full Name*" required>
                  <input type="text" class="form-control mb-2" placeholder="Enter 10-digit mobile no.*" required>
                  <select class="form-select mb-2">
                    <option>Gurgaon</option>
                    <option>Delhi</option>
                    <option>Noida</option>
                  </select>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" checked>
                    <label class="form-check-label small">
                      You hereby affirm & authorise Healthians to process the personal data as per the <a href="<?= $site ?>terms-and-condition/" class="text-danger">T&C</a>.
                    </label>
                  </div>
                  <button class="book-side-btn">Book an Appointment</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
</body>

</html>