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

    /* ── Department booking form ── */
    .dept-booking-card .dept-lbl { font-size: .78rem; font-weight: 600; color: #1f2937; margin-bottom: 3px; display: block; }
    .dept-booking-card .form-control, .dept-booking-card .form-select { border-radius: 8px; border: 1.5px solid #e5e7eb; padding: 8px 12px; font-size: .87rem; }
    .dept-booking-card .form-control:focus, .dept-booking-card .form-select:focus { border-color: #0C74C5; box-shadow: 0 0 0 3px rgba(12,116,197,.12); }
    .dept-doc-chip { background: #eef6ff; border-radius: 8px; padding: 8px 12px; margin-bottom: 12px; font-size: .84rem; }
    .dept-doc-hint { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 8px; padding: 8px 12px; margin-bottom: 12px; font-size: .8rem; }
    .dept-visit-toggle { display: flex; gap: 8px; }
    .dept-visit-toggle label { flex: 1; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 7px; text-align: center; font-size: .82rem; font-weight: 600; color: #6b7280; cursor: pointer; margin: 0; }
    .dept-visit-toggle input { display: none; }
    .dept-visit-toggle input:checked + span, .dept-visit-toggle label:has(input:checked) { border-color: #0C74C5; background: #eaf4fd; color: #0C74C5; }
    .dept-slot-area { border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 8px; min-height: 48px; }
    .dept-slot-hint { font-size: .78rem; color: #9ca3af; text-align: center; padding: 6px 0; }
    .dept-slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); gap: 6px; }
    .dept-slot { border: 1.5px solid #e5e7eb; border-radius: 6px; padding: 6px 4px; text-align: center; font-size: .76rem; font-weight: 600; cursor: pointer; color: #1f2937; }
    .dept-slot:hover { border-color: #0C74C5; }
    .dept-slot.selected { background: #0C74C5; border-color: #0C74C5; color: #fff; }
    .dept-slot.booked { background: #f9fafb; color: #c1c7d0; cursor: not-allowed; text-decoration: line-through; }
    .dept-consent { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; font-size: .74rem; color: #92400e; margin: 12px 0; line-height: 1.5; }
    .dept-fee-notice { background: #eaf4fd; border: 1px solid #bcdcf5; border-radius: 8px; padding: 10px 12px; font-size: .78rem; color: #1f2937; margin-bottom: 12px; }
    .dept-fee-notice i { color: #0C74C5; }
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
                    <div class="doc-profile-inner-body1">
                    <div class="team-image col-sm-4">
                      <?php if (!empty($doctor['profile_image']) && file_exists(BASE_URL . "admin/" . $doctor['profile_image'])): ?>
                        <img src="<?= BASE_URL . "admin/" . $doctor['profile_image'] ?>" alt="Profile">
                      <?php else: ?>
                        <div class="doctor-default-img">
                          <i class="fas fa-user-md text-muted h2"></i>
                        </div>
                      <?php endif; ?>
                      <div class="exp-badge"><?= $doctor['experience_years'] ?> Year of Exp.</div>
                    </div>
                    <div class="team-content col-sm-4">
                      <h3><a href="<?= BASE_URL ?>doctor-profile/<?= $doctor['slug_url'] ?>"><?= $doctor['name'] ?></a></h3>
                      <p><?= $doctor['degrees'] ?></p>
                      <p><b>Language known:</b></p>
                      <p><?= $doctor['languages'] ?></p>
                    </div>
                    </div>
                    <div class="creat-book col-sm-4">
                      <p>Consultancy Fee</p>
                      <div class="price">₹<?= $doctor['consultation_fee'] ?> <span class="old-price">₹1598</span></div>
                      <a href="<?= BASE_URL ?>doctor-profile/<?= $doctor['slug_url'] ?>" class="btn view-profile w-100 mt-2">View Profile</a>
                      <button type="button" class="btn book-btn w-100 mt-2"
                        onclick="bookWithDoctor(<?= (int) $doctor['id'] ?>, '<?= htmlspecialchars(addslashes($doctor['name'])) ?>', <?= (float) ($doctor['consultation_fee'] ?? 0) ?>)">
                        Book an Appointment
                      </button>
                      <small class="doctor-t-c-text">Get up to 100% cashback* <a href="<?= BASE_URL ?>terms-and-condition/" class="text-danger">T&C Apply</a></small>
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
              <div class="sidebar-card dept-booking-card">
                <h5 class="mb-1">Book Your Appointment</h5>
                <p class="text-muted mb-3" style="font-size:.85rem;"><?= htmlspecialchars(trim($department_name)) ?> consultation — pick a doctor, choose a slot, confirm.</p>

                <div id="bookingWithDoctor" class="d-none dept-doc-chip">
                  <i class="fas fa-user-md me-1"></i> Booking with <strong id="bookingDoctorName"></strong>
                  <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="clearDoctorChoice()">change</button>
                </div>
                <div id="bookingNoDoctor" class="dept-doc-hint">
                  <i class="fas fa-hand-pointer me-1"></i> Choose a doctor from the list, then fill in your details here.
                </div>

                <form id="deptAppointmentForm" novalidate>
                  <input type="hidden" name="department" value="<?= htmlspecialchars($department_name) ?>">
                  <input type="hidden" name="doctor_id" id="bookingDoctorId" value="">
                  <input type="hidden" name="doctor_name" id="bookingDoctorNameInput" value="">
                  <input type="hidden" name="consent_required" value="1">
                  <input type="hidden" name="consent_given" id="deptConsentHidden" value="">
                  <input type="hidden" name="time" id="deptTimeHidden" value="">

                  <div class="mb-2">
                    <label class="dept-lbl">Full Name *</label>
                    <input type="text" name="name" class="form-control" required
                      value="<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '' ?>">
                  </div>
                  <div class="mb-2">
                    <label class="dept-lbl">Email Address *</label>
                    <input type="email" name="email" class="form-control" required
                      value="<?= isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '' ?>">
                  </div>
                  <div class="mb-2">
                    <label class="dept-lbl">Mobile Number *</label>
                    <input type="text" name="phone" class="form-control" inputmode="numeric" maxlength="10" required pattern="[6-9][0-9]{9}">
                  </div>
                  <div class="mb-2">
                    <label class="dept-lbl">ABHA Number <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="abha_number" class="form-control" placeholder="XX-XXXX-XXXX-XXXX">
                  </div>

                  <div class="mb-2">
                    <label class="dept-lbl">Who is this appointment for?</label>
                    <div class="dept-visit-toggle">
                      <label><input type="radio" name="visit_person" value="self" checked> Myself</label>
                      <label><input type="radio" name="visit_person" value="other"> Someone else</label>
                    </div>
                  </div>
                  <div class="mb-2 d-none" id="deptVisitedWrap">
                    <label class="dept-lbl">Patient's Name *</label>
                    <input type="text" name="visited_person_name" class="form-control">
                  </div>

                  <div class="row g-2 mb-2">
                    <div class="col-7">
                      <label class="dept-lbl">Date *</label>
                      <input type="date" name="date" id="deptDate" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-5">
                      <label class="dept-lbl">Mode</label>
                      <select name="appointment_type" id="deptMode" class="form-control">
                        <option value="online">Online</option>
                        <option value="clinic">In-Clinic</option>
                      </select>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="dept-lbl">Available Time Slots *</label>
                    <div id="deptSlotArea" class="dept-slot-area">
                      <div class="dept-slot-hint">Select a doctor &amp; date to see open slots.</div>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="dept-lbl">Notes for the doctor <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Briefly describe your symptoms…"></textarea>
                  </div>

                  <div class="dept-consent">
                    <label class="d-flex gap-2 mb-0" style="cursor:pointer;">
                      <input type="checkbox" id="deptConsent" style="margin-top:3px;flex-shrink:0;">
                      <span>I voluntarily consent to receive medical consultation through Telemedicine (Video/Audio Call, Chat or Digital Platform), understand the doctor's advice is based on the information I provide, agree to secure storage of my digital health records, consent to the creation / linking / updating / sharing of my health records through ABHA/ABDM per applicable guidelines, and confirm the information given is true. <a href="<?= BASE_URL ?>terms-and-condition/" class="text-danger">Terms &amp; Privacy Policy</a>. *
                        <br><span class="d-block mt-1" style="opacity:.85;">मैं स्वेच्छा से टेलीमेडिसिन के माध्यम से चिकित्सा परामर्श प्राप्त करने, अपने डिजिटल स्वास्थ्य रिकॉर्ड के सुरक्षित संग्रहण, ABHA/ABDM के माध्यम से स्वास्थ्य रिकॉर्ड के निर्माण/लिंकिंग/साझाकरण तथा दी गई जानकारी के सत्य होने की पुष्टि हेतु सहमति देता/देती हूँ।</span>
                      </span>
                    </label>
                  </div>

                  <div class="dept-fee-notice d-none" id="deptFeeNotice">
                    <i class="fas fa-shield-alt me-1"></i>
                    Consultation fee of <b id="deptFeeAmount"></b> is payable securely via Razorpay (cards / UPI / netbanking) before your appointment is confirmed.
                  </div>

                  <button type="submit" class="book-side-btn w-100" id="deptSubmitBtn">
                    <span class="btn-text">Confirm Booking</span>
                    <span class="spinner-border spinner-border-sm d-none ms-1" id="deptSpinner"></span>
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
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    (function () {
      const BASE_URL = '<?= BASE_URL ?>';

      // ── Doctor search filter ──
      const doctorSearch = document.getElementById('doctorSearch');
      if (doctorSearch) {
        doctorSearch.addEventListener('input', function () {
          const q = this.value.trim().toLowerCase();
          let visible = 0;
          document.querySelectorAll('.doctor-card').forEach(card => {
            const match = card.dataset.name.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
          });
          const noResults = document.getElementById('doctorNoResults');
          if (noResults) noResults.classList.toggle('d-none', visible !== 0);
        });
      }

      const form       = document.getElementById('deptAppointmentForm');
      const msgBox      = document.getElementById('deptFormMessage');
      const submitBtn   = document.getElementById('deptSubmitBtn');
      const btnText     = submitBtn ? submitBtn.querySelector('.btn-text') : null;
      const spinner     = document.getElementById('deptSpinner');
      const slotArea    = document.getElementById('deptSlotArea');
      const dateInput   = document.getElementById('deptDate');
      const timeHidden  = document.getElementById('deptTimeHidden');
      const feeNotice   = document.getElementById('deptFeeNotice');

      const state = { doctorId: '', doctorName: '', fee: 0 };

      function msg(html, kind) {
        msgBox.innerHTML = html
          ? `<div class="alert alert-${kind} py-2 mb-0" style="font-size:.83rem;">${html}</div>` : '';
      }
      function busy(label) { submitBtn.disabled = true; if (btnText) btnText.textContent = label; spinner.classList.remove('d-none'); }
      function idle() { submitBtn.disabled = false; if (btnText) btnText.textContent = 'Confirm Booking'; spinner.classList.add('d-none'); }

      // ── Doctor selection (from the cards) ──
      window.bookWithDoctor = function (id, name, fee) {
        state.doctorId = String(id);
        state.doctorName = name;
        state.fee = Number(fee || 0);
        document.getElementById('bookingDoctorId').value = id;
        document.getElementById('bookingDoctorNameInput').value = name;
        document.getElementById('bookingDoctorName').textContent = name;
        document.getElementById('bookingWithDoctor').classList.remove('d-none');
        document.getElementById('bookingNoDoctor').classList.add('d-none');
        feeNotice.classList.toggle('d-none', !(state.fee > 0));
        if (state.fee > 0) document.getElementById('deptFeeAmount').textContent = '₹' + state.fee.toLocaleString('en-IN');
        document.getElementById('bookingSidebar').scrollIntoView({ behavior: 'smooth', block: 'start' });
        loadSlots();
      };
      window.clearDoctorChoice = function () {
        state.doctorId = ''; state.doctorName = ''; state.fee = 0;
        document.getElementById('bookingDoctorId').value = '';
        document.getElementById('bookingDoctorNameInput').value = '';
        document.getElementById('bookingWithDoctor').classList.add('d-none');
        document.getElementById('bookingNoDoctor').classList.remove('d-none');
        feeNotice.classList.add('d-none');
        slotArea.innerHTML = '<div class="dept-slot-hint">Select a doctor &amp; date to see open slots.</div>';
        timeHidden.value = '';
      };

      // ── Slot loading ──
      function loadSlots() {
        timeHidden.value = '';
        if (!state.doctorId) { slotArea.innerHTML = '<div class="dept-slot-hint">Choose a doctor first.</div>'; return; }
        const date = dateInput.value;
        if (!date) return;
        slotArea.innerHTML = '<div class="dept-slot-hint">Loading slots…</div>';
        fetch(BASE_URL + `util/get-available-slots.php?doctor_id=${state.doctorId}&date=${date}`)
          .then(r => r.json())
          .then(data => {
            if (!data.success || !data.slots || !data.slots.length) {
              slotArea.innerHTML = '<div class="dept-slot-hint">No slots available for this date. Try another date.</div>';
              return;
            }
            slotArea.innerHTML = '<div class="dept-slot-grid">' + data.slots.map(s =>
              `<div class="dept-slot ${s.booked ? 'booked' : ''}" data-time="${s.time}">${s.display}</div>`).join('') + '</div>';
            slotArea.querySelectorAll('.dept-slot:not(.booked)').forEach(sl => {
              sl.addEventListener('click', () => {
                slotArea.querySelectorAll('.dept-slot').forEach(x => x.classList.remove('selected'));
                sl.classList.add('selected');
                timeHidden.value = sl.dataset.time;
              });
            });
          })
          .catch(() => { slotArea.innerHTML = '<div class="dept-slot-hint">Could not load slots. Please try again.</div>'; });
      }
      if (dateInput) dateInput.addEventListener('change', loadSlots);

      // ── Visit-for toggle ──
      form.querySelectorAll('input[name="visit_person"]').forEach(r => {
        r.addEventListener('change', function () {
          const wrap = document.getElementById('deptVisitedWrap');
          const input = wrap.querySelector('input');
          const other = this.value === 'other';
          wrap.classList.toggle('d-none', !other);
          if (other) input.setAttribute('required', 'required'); else input.removeAttribute('required');
        });
      });

      // ── Submit → (payment) → appointment-handler ──
      function finalizeBooking(fd) {
        busy('Booking…');
        fetch(BASE_URL + 'util/appointment-handler.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(data => {
            idle();
            if (data.status === 'success') {
              form.querySelectorAll('input,select,textarea,button').forEach(el => el.disabled = true);
              msg(`Appointment requested! Reference <b>${data.appointment_id || ''}</b>. We've emailed the details — our team will confirm your slot shortly.`, 'success');
            } else {
              msg(data.message || 'Something went wrong. Please try again.', 'danger');
            }
          })
          .catch(() => { idle(); msg('Network error. Please try again.', 'danger'); });
      }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        msg('');

        if (!state.doctorId) { msg('Please choose a doctor from the list first.', 'danger'); return; }
        if (!form.name.value.trim() || !form.email.value.trim() || !/^[6-9]\d{9}$/.test(form.phone.value.trim())) {
          msg('Please fill your name, a valid email and a valid 10-digit mobile number.', 'danger'); return;
        }
        if (!timeHidden.value) { msg('Please select an available time slot.', 'danger'); return; }
        if (form.visit_person.value === 'other' && !form.visited_person_name.value.trim()) {
          msg("Please enter the patient's name.", 'danger'); return;
        }
        if (!document.getElementById('deptConsent').checked) { msg('Please provide consent to proceed.', 'danger'); return; }
        const abha = form.abha_number.value.trim();
        if (abha && !/^\d{2}-\d{4}-\d{4}-\d{4}$/.test(abha)) { msg('ABHA number format should be XX-XXXX-XXXX-XXXX.', 'danger'); return; }

        document.getElementById('deptConsentHidden').value = '1';
        const fd = new FormData(form);

        if (!(state.fee > 0)) { finalizeBooking(fd); return; }

        busy('Preparing payment…');
        const orderData = new FormData();
        orderData.append('doctor_id', state.doctorId);
        fetch(BASE_URL + 'util/create-razorpay-order.php', { method: 'POST', body: orderData })
          .then(r => r.json())
          .then(order => {
            if (!order.success) { idle(); msg(order.message || 'Could not start payment. Please try again.', 'danger'); return; }
            if (!order.payment_required) { finalizeBooking(fd); return; }
            idle();
            const rzp = new Razorpay({
              key: order.key_id, order_id: order.order_id, amount: order.amount, currency: order.currency,
              name: 'Rejuvenate Digital Health',
              description: 'Consultation with Dr. ' + (order.doctor_name || state.doctorName),
              prefill: { name: form.name.value, email: form.email.value, contact: form.phone.value },
              theme: { color: '#0C74C5' },
              handler: function (resp) {
                fd.append('razorpay_order_id', resp.razorpay_order_id);
                fd.append('razorpay_payment_id', resp.razorpay_payment_id);
                fd.append('razorpay_signature', resp.razorpay_signature);
                finalizeBooking(fd);
              },
              modal: { ondismiss: function () { msg('Payment was cancelled. Your appointment was not booked.', 'danger'); } },
            });
            rzp.on('payment.failed', function () { msg('Payment failed. Please try again.', 'danger'); });
            rzp.open();
          })
          .catch(() => { idle(); msg('Network error while starting payment. Please try again.', 'danger'); });
      });

      // Deep-link: /department/<slug>?doctor_id=<id>  → preselect that doctor
      <?php if (!empty($_GET['doctor_id'])): ?>
      (function () {
        const btn = document.querySelector('.doctor-card [onclick*="bookWithDoctor(<?= (int) $_GET['doctor_id'] ?>,"]');
        if (btn) btn.click();
      })();
      <?php endif; ?>
    })();
  </script>
</body>

</html>