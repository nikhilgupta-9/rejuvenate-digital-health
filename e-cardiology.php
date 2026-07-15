<?php
include_once "config/connect.php";
include_once "util/function.php";

$department = isset($_GET['alias']) ? get_department_by_slug($_GET['alias']) : null;
$department_name = $department['categories'] ?? 'Specialist';
// Rich, visitor-facing content shown on the page. meta_desc stays SEO-only.
$department_desc  = trim($department['description'] ?? '');
$department_meta_desc = trim($department['meta_desc'] ?? '') ?: ($department_desc ?: "Consult top $department_name specialists online at Rejuvenate Digital Health.");
$department_img   = !empty($department['sub_cat_img']) ? BASE_URL . 'admin/uploads/sub-category/' . $department['sub_cat_img'] : null;
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="<?= htmlspecialchars(mb_strimwidth($department_meta_desc, 0, 160, '…')) ?>">
  <title><?= htmlspecialchars(trim($department_name)) ?> Specialists — REJUVENATE Digital Health</title>
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
    .dept-intro-img {
      width: 100%;
      height: 100%;
      min-height: 140px;
      object-fit: cover;
      border-radius: 12px;
    }

    .dept-doctor-search {
      position: relative;
    }

    .dept-doctor-search input {
      border-radius: 50px;
      padding: 10px 18px 10px 38px;
      border: 1px solid #e5e7eb;
      width: 100%;
      font-size: .88rem;
    }

    .dept-doctor-search i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: .85rem;
    }
  </style>
</head>

<body>
  <?php include("header.php") ?>
  <?php
  $doctors = get_doctor_byDepartment();
  ?>

  <!-- Breadcrumb Section Start -->
  <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
    <div class="container">
      <div class="page-heading">
        <div class="breadcrumb-items-area">
          <div class="breadcrumb-sub-title">
            <h1 class="text-white wow fadeInUp" data-wow-delay=".3s"><?= htmlspecialchars(trim($department_name)) ?></h1>
          </div>
          <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
            <li><a href="<?= BASE_URL ?>">Home</a></li>
            <li>//</li>
            <li><a href="<?= BASE_URL ?>departments/">Departments</a></li>
            <li>//</li>
            <li><?= htmlspecialchars(trim($department_name)) ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <section class="service-details-section section-padding pb-5">
    <div class="container">
      <div class="service-details-wrapper">
        <div class="row g-5">
          <div class="col-lg-8">

            <?php if ($department_desc || $department_img): ?>
              <div class="row mb-4 dept-intro">
                <?php if ($department_img): ?>
                  <div class="col-md-4 mb-3 mb-md-0">
                    <img src="<?= htmlspecialchars($department_img) ?>" alt="<?= htmlspecialchars($department_name) ?>" class="dept-intro-img">
                  </div>
                <?php endif; ?>
                <div class="<?= $department_img ? 'col-md-8' : 'col-md-12' ?>">
                  <h2 class="fw-bold mb-3" style="font-size:1.5rem;"><?= htmlspecialchars(trim($department_name)) ?></h2>
                  <?php if ($department_desc): ?>
                    <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($department_desc)) ?></p>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="row align-items-center">
              <div class="col-sm-6">
                <h4 class="fw-bold mb-3 mb-sm-4">
                  Top <?= htmlspecialchars(trim($department_name)) ?> Doctors Available
                </h4>
              </div>
              <?php if (!empty($doctors)): ?>
                <div class="col-sm-6 mb-3 mb-sm-4">
                  <div class="dept-doctor-search">
                    <i class="far fa-search"></i>
                    <input type="text" id="doctorSearch" class="form-control" placeholder="Search doctor by name…">
                  </div>
                </div>
              <?php endif; ?>
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
                    <a href="<?= BASE_URL ?>departments/" class="btn btn-primary px-4">
                      <i class="fas fa-arrow-left me-2"></i>Other Departments
                    </a>
                    <a href="<?= BASE_URL ?>contact/" class="btn btn-outline-dark px-4">
                      <i class="fas fa-question-circle me-2"></i>Get Help
                    </a>
                  </div>

                  <!-- Quick alternatives -->
                  <div class="quick-links">
                    <p class="small text-muted mb-2">Quick alternatives:</p>
                    <div class="d-flex justify-content-center gap-3">
                      <a href="<?= BASE_URL ?>departments/" class="small text-decoration-underline text-primary">All Departments</a>
                      <a href="<?= BASE_URL ?>contact/" class="small text-decoration-underline text-primary">Health Checkups</a>
                      <a href="<?= BASE_URL ?>departments/" class="small text-decoration-underline text-primary">Find Doctors</a>
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

              <div id="doctorList">
              <?php
              foreach ($doctors as $doctor) {
              ?>
                <div class="service-details-right-items doctor-card" data-name="<?= strtolower(htmlspecialchars($doctor['name'])) ?>">

                  <div class="team-box-items-2">
                    <div class="team-image">
                      <?php if (!empty($doctor['profile_image'])): ?>
                        <img src="<?= BASE_URL . "admin/" . $doctor['profile_image'] ?>" alt="Profile">
                      <?php else: ?>
                        <div style="width: 140px; height: 140px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                          <i class="fas fa-user-md text-muted h2"></i>
                        </div>
                      <?php endif; ?>
                      <div class="exp-badge"><?= $doctor['experience_years'] ?> Year of Exp.</div>
                    </div>
                    <div class="team-content">
                      <h3><a href="<?= BASE_URL ?>doctor-profile/<?= $doctor['slug_url'] ?>"><?= $doctor['name'] ?></a></h3>
                      <p><?= $doctor['degrees'] ?></p>
                      <p><b>Language known:</b></p>
                      <p><?= $doctor['languages'] ?></p>
                    </div>
                    <div class="creat-book">
                      <p>Consultancy Fee</p>
                      <div class="price">₹<?= $doctor['consultation_fee'] ?> <span class="old-price">₹1598</span></div>
                      <a href="<?= BASE_URL ?>doctor-profile/<?= $doctor['slug_url'] ?>" class="btn view-profile w-100 mt-2">View Profile</a>
                      <button type="button" class="btn book-btn w-100 mt-2"
                        onclick="bookWithDoctor(<?= (int) $doctor['id'] ?>, '<?= htmlspecialchars(addslashes($doctor['name'])) ?>')">
                        Book an Appointment
                      </button>
                      <small class="text-muted d-block mt-1">Get up to 100% cashback* <a href="<?= BASE_URL ?>terms-and-condition/" class="text-danger">T&C Apply</a></small>
                    </div>
                  </div>
                </div>
              <?php
              }
              ?>
              </div>
              <div class="text-center py-5 d-none" id="doctorNoResults">
                <i class="fas fa-search fa-2x text-muted mb-3 d-block" style="opacity:.3;"></i>
                <p class="text-muted mb-0">No doctors match your search.</p>
              </div>
            <?php endif; ?>


          </div>
          <div class="col-lg-4 ">
            <div class="service-details-sidebar sticky-style" id="bookingSidebar">
              <div class="sidebar-card">
                <h5>Book Your Appointment</h5>
                <p class="text-muted mb-3"><?= htmlspecialchars(trim($department_name)) ?> consultation — we'll confirm by phone/email.</p>

                <div id="bookingWithDoctor" class="d-none" style="background:#eef6ff;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:.85rem;">
                  <i class="fas fa-user-md me-1"></i> Booking with <strong id="bookingDoctorName"></strong>
                  <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="clearDoctorChoice()">change</button>
                </div>

                <form id="deptAppointmentForm">
                  <input type="hidden" name="department" value="<?= htmlspecialchars($department_name) ?>">
                  <input type="hidden" name="doctor_id" id="bookingDoctorId" value="">
                  <input type="hidden" name="doctor_name" id="bookingDoctorNameInput" value="">

                  <input type="text" name="name" class="form-control mb-2" placeholder="Full Name*" required>
                  <input type="email" name="email" class="form-control mb-2" placeholder="Email address*" required>
                  <input type="text" name="phone" class="form-control mb-2" placeholder="10-digit mobile no.*" required pattern="[6-9][0-9]{9}">
                  <div class="row g-2 mb-2">
                    <div class="col-6">
                      <input type="date" name="date" class="form-control" required>
                    </div>
                    <div class="col-6">
                      <input type="time" name="time" class="form-control" required>
                    </div>
                  </div>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="deptConsent" checked required>
                    <label class="form-check-label small" for="deptConsent">
                      You hereby affirm &amp; authorise Rejuvenate Digital Health to process the personal data as per the <a href="<?= BASE_URL ?>terms-and-condition/" class="text-danger">T&amp;C</a>.
                    </label>
                  </div>
                  <button type="submit" class="book-side-btn w-100">
                    <span class="btn-text">Book an Appointment</span>
                    <span class="loader d-none"></span>
                  </button>
                  <div id="deptFormMessage" class="mt-2"></div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
  <script>
    // ── Doctor search filter ──
    const doctorSearch = document.getElementById('doctorSearch');
    if (doctorSearch) {
      doctorSearch.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        const cards = document.querySelectorAll('.doctor-card');
        let visible = 0;
        cards.forEach(card => {
          const match = card.dataset.name.includes(q);
          card.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        const noResults = document.getElementById('doctorNoResults');
        if (noResults) noResults.classList.toggle('d-none', visible !== 0);
      });
    }

    // ── Sync a specific doctor into the booking form ──
    function bookWithDoctor(id, name) {
      document.getElementById('bookingDoctorId').value = id;
      document.getElementById('bookingDoctorNameInput').value = name;
      document.getElementById('bookingDoctorName').textContent = name;
      document.getElementById('bookingWithDoctor').classList.remove('d-none');
      document.getElementById('bookingSidebar').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function clearDoctorChoice() {
      document.getElementById('bookingDoctorId').value = '';
      document.getElementById('bookingDoctorNameInput').value = '';
      document.getElementById('bookingWithDoctor').classList.add('d-none');
    }

    // ── Real appointment booking — same backend as the homepage form ──
    const deptForm = document.getElementById('deptAppointmentForm');
    if (deptForm) {
      deptForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const form = this;
        const formData = new FormData(form);
        const messageBox = document.getElementById('deptFormMessage');
        const loader = form.querySelector('.loader');
        const btnText = form.querySelector('.btn-text');

        loader.classList.remove('d-none');
        btnText.textContent = 'Sending…';

        fetch('<?= BASE_URL ?>util/appointment-handler.php', {
          method: 'POST',
          body: formData
        })
          .then(res => res.json())
          .then(data => {
            loader.classList.add('d-none');
            btnText.textContent = 'Book an Appointment';

            if (data.status === 'success') {
              messageBox.innerHTML = `<div class="alert alert-success py-2 mb-0" style="font-size:.85rem;">${data.message}</div>`;
              form.reset();
              clearDoctorChoice();
            } else {
              messageBox.innerHTML = `<div class="alert alert-danger py-2 mb-0" style="font-size:.85rem;">${data.message}</div>`;
            }
          })
          .catch(() => {
            loader.classList.add('d-none');
            btnText.textContent = 'Book an Appointment';
            messageBox.innerHTML = `<div class="alert alert-danger py-2 mb-0" style="font-size:.85rem;">Something went wrong. Please try again.</div>`;
          });
      });
    }
  </script>
</body>

</html>