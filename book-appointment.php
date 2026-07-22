<?php
include_once "config/connect.php";
include_once "util/function.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// Pre-fill for a logged-in patient
$logged_in_patient = null;
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name, last_name, email, mobile, abha_id FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $logged_in_patient = $stmt->get_result()->fetch_assoc();
}

$departments = get_sub_category();

// Optional deep-link: /book-appointment.php?department=cardiology&doctor_id=12
$pre_department = trim($_GET['department'] ?? '');
$pre_doctor_id  = intval($_GET['doctor_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="Book a doctor appointment online at Rejuvenate Digital Health — choose your department, doctor and a convenient time slot in a few clicks.">
  <title>Book an Appointment — REJUVENATE Digital Health</title>
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
    :root {
      --bk-primary: #0C74C5;
      --bk-primary-dk: #095e9f;
      --bk-accent: #02c9b8;
      --bk-ink: #1f2937;
      --bk-muted: #6b7280;
      --bk-border: #e5e7eb;
      --bk-bg: #f6f9fc;
    }

    .bk-shell {
      background: var(--bk-bg);
      padding: 40px 0 70px;
    }

    /* ── Stepper ── */
    .bk-stepper {
      display: flex;
      justify-content: center;
      gap: 6px;
      max-width: 760px;
      margin: 0 auto 34px;
      padding: 0 12px;
    }

    .bk-step {
      flex: 1;
      text-align: center;
      position: relative;
    }

    .bk-step .dot {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: #fff;
      border: 2px solid var(--bk-border);
      color: var(--bk-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .85rem;
      margin: 0 auto 6px;
      transition: .2s;
    }

    .bk-step .lbl {
      font-size: .72rem;
      color: var(--bk-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    .bk-step::after {
      content: '';
      position: absolute;
      top: 17px;
      left: 50%;
      width: 100%;
      height: 2px;
      background: var(--bk-border);
      z-index: -1;
    }

    .bk-step:last-child::after {
      display: none;
    }

    .bk-step.active .dot,
    .bk-step.done .dot {
      background: var(--bk-primary);
      border-color: var(--bk-primary);
      color: #fff;
    }

    .bk-step.active .lbl,
    .bk-step.done .lbl {
      color: var(--bk-primary);
    }

    .bk-step.done::after {
      background: var(--bk-primary);
    }

    /* ── Card shell ── */
    .bk-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 28px rgba(20, 40, 70, .08);
      padding: 30px;
      max-width: 900px;
      margin: 0 auto;
    }

    .bk-pane {
      display: none;
    }

    .bk-pane.active {
      display: block;
      animation: bkFade .25s ease;
    }

    @keyframes bkFade {
      from {
        opacity: 0;
        transform: translateY(6px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .bk-pane h4 {
      font-weight: 700;
      color: var(--bk-ink);
      margin-bottom: 4px;
    }

    .bk-pane .sub {
      color: var(--bk-muted);
      font-size: .88rem;
      margin-bottom: 22px;
    }

    /* ── Department grid ── */
    .bk-dept-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 14px;
    }

    .bk-dept-card {
      border: 2px solid var(--bk-border);
      border-radius: 12px;
      padding: 16px 12px;
      text-align: center;
      cursor: pointer;
      transition: .15s;
      background: #fff;
    }

    .bk-dept-card:hover {
      border-color: var(--bk-primary);
      transform: translateY(-2px);
    }

    .bk-dept-card.selected {
      border-color: var(--bk-primary);
      background: #eaf4fd;
    }

    .bk-dept-card .ic {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      background: #eef6fd;
      color: var(--bk-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-size: 1.15rem;
      overflow: hidden;
    }

    .bk-dept-card .ic img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .bk-dept-card .name {
      font-size: .85rem;
      font-weight: 600;
      color: var(--bk-ink);
    }

    /* ── Doctor cards ── */
    .bk-doctor-card {
      border: 2px solid var(--bk-border);
      border-radius: 14px;
      padding: 18px;
      cursor: pointer;
      transition: .15s;
      background: #fff;
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }

    .bk-doctor-card:hover {
      border-color: var(--bk-primary);
    }

    .bk-doctor-card.selected {
      border-color: var(--bk-primary);
      background: #eaf4fd;
    }

    .bk-doctor-card .avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--bk-primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.3rem;
      flex-shrink: 0;
      overflow: hidden;
    }

    .bk-doctor-card .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .bk-doctor-card .name {
      font-weight: 700;
      color: var(--bk-ink);
      font-size: .95rem;
    }

    .bk-doctor-card .hpr-badge {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      background: #e6f7ee;
      color: #16a34a;
      font-size: .65rem;
      font-weight: 700;
      border-radius: 20px;
      padding: 2px 8px;
      margin-left: 6px;
    }

    .bk-doctor-card .meta {
      font-size: .78rem;
      color: var(--bk-muted);
      margin-top: 2px;
    }

    .bk-doctor-card .fee {
      font-weight: 700;
      color: var(--bk-primary);
      font-size: .88rem;
      margin-top: 6px;
    }

    /* ── Time slots ── */
    .bk-slot-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 10px;
    }

    .bk-slot {
      border: 1.5px solid var(--bk-border);
      border-radius: 8px;
      padding: 9px 6px;
      text-align: center;
      font-size: .82rem;
      font-weight: 600;
      cursor: pointer;
      transition: .15s;
      color: var(--bk-ink);
      background: #fff;
    }

    .bk-slot:hover {
      border-color: var(--bk-primary);
    }

    .bk-slot.selected {
      background: var(--bk-primary);
      border-color: var(--bk-primary);
      color: #fff;
    }

    .bk-slot.booked {
      background: #f9fafb;
      color: #c1c7d0;
      cursor: not-allowed;
      text-decoration: line-through;
    }

    /* ── Buttons / nav ── */
    .bk-nav {
      display: flex;
      justify-content: space-between;
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid #f1f3f6;
    }

    .bk-btn {
      padding: 11px 26px;
      border-radius: 8px;
      font-weight: 600;
      font-size: .9rem;
      border: none;
      cursor: pointer;
      transition: .15s;
    }

    .bk-btn-primary {
      background: var(--bk-primary);
      color: #fff;
    }

    .bk-btn-primary:hover {
      background: var(--bk-primary-dk);
    }

    .bk-btn-primary:disabled {
      background: #b9d4ea;
      cursor: not-allowed;
    }

    .bk-btn-outline {
      background: #fff;
      border: 1.5px solid var(--bk-border);
      color: var(--bk-muted);
    }

    .bk-btn-outline:hover {
      border-color: var(--bk-primary);
      color: var(--bk-primary);
    }

    /* ── Summary strip (shown from step 3 onward) ── */
    .bk-summary {
      display: none;
      background: #eaf4fd;
      border-radius: 10px;
      padding: 10px 16px;
      font-size: .82rem;
      color: var(--bk-ink);
      margin-bottom: 22px;
      flex-wrap: wrap;
      gap: 4px 14px;
    }

    .bk-summary.show {
      display: flex;
    }

    .bk-summary b {
      color: var(--bk-primary);
    }

    /* ── Form fields (step 4) ── */
    .bk-field label {
      font-size: .82rem;
      font-weight: 600;
      color: var(--bk-ink);
      margin-bottom: 5px;
      display: block;
    }

    .bk-field .form-control,
    .bk-field .form-select {
      border-radius: 8px;
      border: 1.5px solid var(--bk-border);
      padding: 10px 14px;
      font-size: .9rem;
    }

    .bk-field .form-control:focus,
    .bk-field .form-select:focus {
      border-color: var(--bk-primary);
      box-shadow: 0 0 0 3px rgba(12, 116, 197, .12);
    }

    .bk-visit-toggle {
      display: flex;
      gap: 10px;
    }

    .bk-visit-toggle label {
      flex: 1;
      border: 1.5px solid var(--bk-border);
      border-radius: 8px;
      padding: 10px;
      text-align: center;
      font-size: .85rem;
      font-weight: 600;
      cursor: pointer;
      color: var(--bk-muted);
    }

    .bk-visit-toggle input {
      display: none;
    }

    .bk-visit-toggle input:checked+label {
      border-color: var(--bk-primary);
      background: #eaf4fd;
      color: var(--bk-primary);
    }

    .bk-consent {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 10px;
      padding: 14px 16px;
      font-size: .8rem;
      color: #92400e;
      margin: 20px 0;
    }

    /* ── Success ── */
    .bk-success {
      display: none;
      text-align: center;
      padding: 20px 10px 10px;
    }

    .bk-success .tick {
      width: 74px;
      height: 74px;
      border-radius: 50%;
      background: #e6f7ee;
      color: #16a34a;
      font-size: 2.1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 18px;
    }

    .bk-ref {
      display: inline-block;
      background: #f3f6fb;
      border-radius: 8px;
      padding: 6px 16px;
      font-weight: 700;
      color: var(--bk-primary);
      letter-spacing: .5px;
      margin: 10px 0 18px;
    }

    #bkFormError {
      display: none;
    }

    .bk-empty {
      text-align: center;
      padding: 40px 10px;
      color: var(--bk-muted);
    }

    @media (max-width: 576px) {
      .bk-card {
        padding: 20px 16px;
      }

      .bk-step .lbl {
        display: none;
      }
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
            <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Book an Appointment</h1>
          </div>
          <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
            <li><a href="<?= BASE_URL ?>">Home</a></li>
            <li>//</li>
            <li>Book an Appointment</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <section class="bk-shell">
    <div class="container">

      <!-- Stepper -->
      <div class="bk-stepper" id="bkStepper">
        <div class="bk-step active" data-step="1"><div class="dot">1</div><div class="lbl">Department</div></div>
        <div class="bk-step" data-step="2"><div class="dot">2</div><div class="lbl">Doctor</div></div>
        <div class="bk-step" data-step="3"><div class="dot">3</div><div class="lbl">Date &amp; Time</div></div>
        <div class="bk-step" data-step="4"><div class="dot">4</div><div class="lbl">Your Details</div></div>
      </div>

      <div class="bk-card">

        <div class="bk-summary" id="bkSummary"></div>

        <!-- STEP 1: Department -->
        <div class="bk-pane active" id="bkPane1">
          <h4>Choose a department</h4>
          <p class="sub">Pick the speciality that best matches what you need help with.</p>
          <div class="bk-dept-grid">
            <?php foreach ($departments as $dept): ?>
              <div class="bk-dept-card" data-slug="<?= htmlspecialchars($dept['slug_url']) ?>" data-name="<?= htmlspecialchars(trim($dept['categories'])) ?>">
                <div class="ic">
                  <?php if (!empty($dept['sub_cat_img'])): ?>
                    <img src="<?= BASE_URL ?>admin/uploads/sub-category/<?= htmlspecialchars($dept['sub_cat_img']) ?>" alt="">
                  <?php else: ?>
                    <i class="fas fa-stethoscope"></i>
                  <?php endif; ?>
                </div>
                <div class="name"><?= htmlspecialchars(trim($dept['categories'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="bk-nav">
            <span></span>
            <button type="button" class="bk-btn bk-btn-primary" id="bkNext1" disabled>Continue <i class="fas fa-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- STEP 2: Doctor -->
        <div class="bk-pane" id="bkPane2">
          <h4>Choose a doctor</h4>
          <p class="sub" id="bkDoctorSub">Available specialists in this department.</p>
          <div id="bkDoctorList"></div>
          <div class="bk-nav">
            <button type="button" class="bk-btn bk-btn-outline" data-back="1"><i class="fas fa-arrow-left me-1"></i> Back</button>
            <button type="button" class="bk-btn bk-btn-primary" id="bkNext2" disabled>Continue <i class="fas fa-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- STEP 3: Date & Time -->
        <div class="bk-pane" id="bkPane3">
          <h4>Pick a date &amp; time</h4>
          <p class="sub">Slots update in real time based on the doctor's current bookings.</p>
          <div class="row g-3 mb-3">
            <div class="col-md-5 bk-field">
              <label>Appointment Date</label>
              <input type="date" class="form-control" id="bkDate" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4 bk-field">
              <label>Consultation Mode</label>
              <select class="form-select" id="bkMode">
                <option value="online">Online Consultation</option>
                <option value="clinic">In-Clinic Visit</option>
              </select>
            </div>
          </div>
          <div id="bkSlotArea">
            <div class="bk-slot-grid" id="bkSlotGrid"></div>
          </div>
          <div class="bk-nav">
            <button type="button" class="bk-btn bk-btn-outline" data-back="2"><i class="fas fa-arrow-left me-1"></i> Back</button>
            <button type="button" class="bk-btn bk-btn-primary" id="bkNext3" disabled>Continue <i class="fas fa-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- STEP 4: Details + submit -->
        <div class="bk-pane" id="bkPane4">
          <h4>Your details</h4>
          <p class="sub">We'll use this to confirm your appointment.</p>

          <div id="bkFormError" class="alert alert-danger py-2" style="font-size:.85rem;"></div>

          <form id="bkForm">
            <div class="row g-3">
              <div class="col-md-6 bk-field">
                <label>Full Name *</label>
                <input type="text" class="form-control" name="name" required
                  value="<?= $logged_in_patient ? htmlspecialchars(trim($logged_in_patient['name'] . ' ' . $logged_in_patient['last_name'])) : '' ?>">
              </div>
              <div class="col-md-6 bk-field">
                <label>Email Address *</label>
                <input type="email" class="form-control" name="email" required
                  value="<?= $logged_in_patient ? htmlspecialchars($logged_in_patient['email']) : '' ?>">
              </div>
              <div class="col-md-6 bk-field">
                <label>Mobile Number *</label>
                <input type="text" class="form-control" name="phone" inputmode="numeric" maxlength="10" required
                  value="<?= $logged_in_patient ? htmlspecialchars($logged_in_patient['mobile']) : '' ?>">
              </div>
              <div class="col-md-6 bk-field">
                <label>ABHA Number <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" name="abha_number" placeholder="XX-XXXX-XXXX-XXXX"
                  value="<?= $logged_in_patient && !empty($logged_in_patient['abha_id']) ? htmlspecialchars($logged_in_patient['abha_id']) : '' ?>">
              </div>

              <div class="col-12 bk-field">
                <label>Who is this appointment for?</label>
                <div class="bk-visit-toggle">
                  <input type="radio" name="visit_person" id="bkVisitSelf" value="self" checked>
                  <label for="bkVisitSelf"><i class="fas fa-user me-1"></i> Myself</label>
                  <input type="radio" name="visit_person" id="bkVisitOther" value="other">
                  <label for="bkVisitOther"><i class="fas fa-user-friends me-1"></i> Someone else</label>
                </div>
              </div>
              <div class="col-md-6 bk-field d-none" id="bkVisitedNameWrap">
                <label>Patient's Name *</label>
                <input type="text" class="form-control" name="visited_person_name">
              </div>

              <div class="col-12 bk-field">
                <label>Notes for the doctor <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control" name="notes" rows="3" placeholder="Briefly describe your symptoms or reason for visit…"></textarea>
              </div>
            </div>

            <div class="bk-consent">
              <label class="d-flex gap-2 mb-0" style="cursor:pointer;">
                <input type="checkbox" name="consent_given" id="bkConsent" required style="margin-top:3px;">
                <span>
                   I voluntarily consent to receive medical consultation through Telemedicine (Video Call, Audio Call, Chat or Digital Platform), understand that the doctor's advice will be based on the information and documents provided by me, agree to the secure storage and management of my digital health records, consent to the creation, linking, updating and sharing of my health records through ABHA/ABDM as per applicable guidelines, and confirm that the information provided by me is true and correct.
                  <a href="<?= BASE_URL ?>terms-and-condition/" class="text-danger">Terms &amp; Privacy Policy</a>. *
                </span>
                <span>
                   मैं स्वेच्छा से टेलीमेडिसिन (वीडियो कॉल, ऑडियो कॉल, चैट या डिजिटल प्लेटफॉर्म) के माध्यम से चिकित्सा परामर्श प्राप्त करने, यह समझने कि चिकित्सक की सलाह मेरे द्वारा प्रदान की गई जानकारी एवं दस्तावेजों के आधार पर होगी, अपने डिजिटल स्वास्थ्य रिकॉर्ड के सुरक्षित संग्रहण एवं प्रबंधन, लागू दिशानिर्देशों के अनुसार ABHA/ABDM के माध्यम से स्वास्थ्य रिकॉर्ड के निर्माण, लिंकिंग, अद्यतन एवं साझा किए जाने तथा मेरे द्वारा प्रदान की गई जानकारी के सही एवं सत्य होने की पुष्टि हेतु अपनी सहमति प्रदान करता/करती हूँ।
                </span>
              </label>
            </div>

            <input type="hidden" name="department" id="bkFieldDepartment">
            <input type="hidden" name="doctor_id" id="bkFieldDoctorId">
            <input type="hidden" name="doctor_name" id="bkFieldDoctorName">
            <input type="hidden" name="date" id="bkFieldDate">
            <input type="hidden" name="time" id="bkFieldTime">
            <input type="hidden" name="appointment_type" id="bkFieldMode">
            <input type="hidden" name="consent_required" value="1">

            <div class="bk-nav">
              <button type="button" class="bk-btn bk-btn-outline" data-back="3"><i class="fas fa-arrow-left me-1"></i> Back</button>
              <button type="submit" class="bk-btn bk-btn-primary" id="bkSubmitBtn">
                <span id="bkSubmitText">Confirm Booking</span>
                <span class="spinner-border spinner-border-sm d-none ms-1" id="bkSubmitSpinner"></span>
              </button>
            </div>
          </form>
        </div>

        <!-- SUCCESS -->
        <div class="bk-success" id="bkSuccess">
          <div class="tick"><i class="fas fa-check"></i></div>
          <h4>Appointment Requested!</h4>
          <p class="text-muted mb-0">Your reference number is</p>
          <div class="bk-ref" id="bkRefNumber"></div>
          <p class="text-muted" style="max-width:420px;margin:0 auto;">
            We've sent the details to your email. Our team will confirm your slot shortly — you can also track
            this appointment from <a href="<?= BASE_URL ?>user-login/">your account</a>.
          </p>
          <a href="<?= BASE_URL ?>" class="bk-btn bk-btn-outline mt-3">Back to Home</a>
        </div>

      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
  <script>
    (function () {
      const BASE_URL = "<?= BASE_URL ?>";
      const state = { department: null, departmentName: null, doctor: null, date: null, time: null, mode: 'online' };
      let currentStep = 1;

      const steps = document.querySelectorAll('.bk-step');
      const panes = { 1: document.getElementById('bkPane1'), 2: document.getElementById('bkPane2'), 3: document.getElementById('bkPane3'), 4: document.getElementById('bkPane4') };
      const summary = document.getElementById('bkSummary');

      function goToStep(n) {
        currentStep = n;
        Object.keys(panes).forEach(k => panes[k].classList.toggle('active', Number(k) === n));
        steps.forEach(s => {
          const sn = Number(s.dataset.step);
          s.classList.toggle('active', sn === n);
          s.classList.toggle('done', sn < n);
        });
        summary.classList.toggle('show', n >= 2);
        renderSummary();
        window.scrollTo({ top: document.querySelector('.bk-card').offsetTop - 100, behavior: 'smooth' });
      }

      function renderSummary() {
        let html = '';
        if (state.departmentName) html += `<span><i class="fas fa-stethoscope me-1"></i>${state.departmentName}</span>`;
        if (state.doctor) html += `<span><i class="fas fa-user-md me-1"></i>Dr. ${state.doctor.name}</span>`;
        if (state.date) html += `<span><i class="fas fa-calendar me-1"></i>${state.date}</span>`;
        if (state.time) html += `<span><i class="fas fa-clock me-1"></i><b>${state.timeDisplay || state.time}</b></span>`;
        summary.innerHTML = html;
      }

      document.querySelectorAll('[data-back]').forEach(btn => {
        btn.addEventListener('click', () => goToStep(Number(btn.dataset.back)));
      });

      // ── STEP 1: department selection ──
      document.querySelectorAll('.bk-dept-card').forEach(card => {
        card.addEventListener('click', () => {
          document.querySelectorAll('.bk-dept-card').forEach(c => c.classList.remove('selected'));
          card.classList.add('selected');
          state.department = card.dataset.slug;
          state.departmentName = card.dataset.name;
          document.getElementById('bkNext1').disabled = false;
        });
      });
      document.getElementById('bkNext1').addEventListener('click', () => {
        document.getElementById('bkFieldDepartment').value = state.departmentName;
        loadDoctors();
        goToStep(2);
      });

      // ── STEP 2: doctor selection ──
      function loadDoctors() {
        const list = document.getElementById('bkDoctorList');
        list.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        document.getElementById('bkNext2').disabled = true;

        fetch(BASE_URL + 'util/get-doctors-by-department.php?department=' + encodeURIComponent(state.department))
          .then(r => r.json())
          .then(data => {
            if (!data.success || !data.doctors.length) {
              list.innerHTML = `<div class="bk-empty"><i class="fas fa-user-md fa-2x mb-2 d-block" style="opacity:.3;"></i>No doctors are currently listed for ${state.departmentName}. Please choose another department or contact us directly.</div>`;
              return;
            }
            list.innerHTML = data.doctors.map(d => `
              <div class="bk-doctor-card mb-3" data-id="${d.id}" data-name="${escHtml(d.name)}">
                <div class="avatar">${d.profile_image ? `<img src="${d.profile_image}" alt="">` : initials(d.name)}</div>
                <div style="flex:1;">
                  <div class="name">Dr. ${escHtml(d.name)} ${d.hpr_verified ? '<span class="hpr-badge"><i class="fas fa-check-circle"></i> HPR Verified</span>' : ''}</div>
                  <div class="meta">${escHtml(d.degrees || '')}${d.specialization ? ' · ' + escHtml(d.specialization) : ''}</div>
                  <div class="meta">${d.experience_years ? d.experience_years + ' yrs experience' : ''}${d.languages ? ' · ' + escHtml(d.languages) : ''}</div>
                  <div class="fee">₹${Number(d.consultation_fee || 0).toLocaleString('en-IN')} consultation fee</div>
                </div>
              </div>
            `).join('');

            list.querySelectorAll('.bk-doctor-card').forEach(card => {
              card.addEventListener('click', () => {
                list.querySelectorAll('.bk-doctor-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                state.doctor = { id: card.dataset.id, name: card.dataset.name };
                document.getElementById('bkNext2').disabled = false;
              });
            });

            // Deep-link: auto-select a specific doctor once the list has loaded
            if (window.__bkPreDoctorId) {
              const preCard = list.querySelector(`.bk-doctor-card[data-id="${window.__bkPreDoctorId}"]`);
              if (preCard) preCard.click();
              window.__bkPreDoctorId = null;
            }
          })
          .catch(() => {
            list.innerHTML = '<div class="bk-empty">Could not load doctors. Please try again.</div>';
          });
      }

      document.getElementById('bkNext2').addEventListener('click', () => {
        document.getElementById('bkFieldDoctorId').value = state.doctor.id;
        document.getElementById('bkFieldDoctorName').value = state.doctor.name;
        loadSlots();
        goToStep(3);
      });

      // ── STEP 3: date & time ──
      const dateInput = document.getElementById('bkDate');
      const modeSelect = document.getElementById('bkMode');
      dateInput.addEventListener('change', loadSlots);
      modeSelect.addEventListener('change', () => { state.mode = modeSelect.value; });

      function loadSlots() {
        const grid = document.getElementById('bkSlotGrid');
        state.date = dateInput.value;
        state.time = null;
        state.timeDisplay = null;
        document.getElementById('bkNext3').disabled = true;
        renderSummary();

        grid.innerHTML = '<div class="text-center py-4 w-100"><div class="spinner-border text-primary"></div></div>';

        fetch(BASE_URL + `util/get-available-slots.php?doctor_id=${state.doctor.id}&date=${state.date}`)
          .then(r => r.json())
          .then(data => {
            if (!data.success || !data.slots.length) {
              grid.innerHTML = '<div class="bk-empty w-100"><i class="fas fa-calendar-times fa-2x mb-2 d-block" style="opacity:.3;"></i>No slots available for this date. Try another date.</div>';
              return;
            }
            grid.innerHTML = data.slots.map(s => `
              <div class="bk-slot ${s.booked ? 'booked' : ''}" data-time="${s.time}" data-display="${s.display}">${s.display}</div>
            `).join('');

            grid.querySelectorAll('.bk-slot:not(.booked)').forEach(slot => {
              slot.addEventListener('click', () => {
                grid.querySelectorAll('.bk-slot').forEach(s => s.classList.remove('selected'));
                slot.classList.add('selected');
                state.time = slot.dataset.time;
                state.timeDisplay = slot.dataset.display;
                document.getElementById('bkNext3').disabled = false;
                renderSummary();
              });
            });
          })
          .catch(() => {
            grid.innerHTML = '<div class="bk-empty w-100">Could not load time slots. Please try again.</div>';
          });
      }

      document.getElementById('bkNext3').addEventListener('click', () => {
        document.getElementById('bkFieldDate').value = state.date;
        document.getElementById('bkFieldTime').value = state.time;
        document.getElementById('bkFieldMode').value = modeSelect.value;
        goToStep(4);
      });

      // ── STEP 4: visit-for toggle ──
      document.querySelectorAll('input[name="visit_person"]').forEach(r => {
        r.addEventListener('change', function () {
          const wrap = document.getElementById('bkVisitedNameWrap');
          const input = wrap.querySelector('input');
          if (this.value === 'other') {
            wrap.classList.remove('d-none');
            input.setAttribute('required', 'required');
          } else {
            wrap.classList.add('d-none');
            input.removeAttribute('required');
          }
        });
      });

      // ── STEP 4: submit ──
      document.getElementById('bkForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('bkFormError');
        errorBox.style.display = 'none';

        const btn = document.getElementById('bkSubmitBtn');
        const text = document.getElementById('bkSubmitText');
        const spinner = document.getElementById('bkSubmitSpinner');
        btn.disabled = true;
        text.textContent = 'Booking…';
        spinner.classList.remove('d-none');

        const formData = new FormData(this);

        fetch(BASE_URL + 'util/appointment-handler.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            btn.disabled = false;
            text.textContent = 'Confirm Booking';
            spinner.classList.add('d-none');

            if (data.status === 'success') {
              document.getElementById('bkRefNumber').textContent = data.appointment_id || '';
              document.querySelector('.bk-stepper').style.display = 'none';
              summary.classList.remove('show');
              panes[4].classList.remove('active');
              document.getElementById('bkSuccess').style.display = 'block';
            } else {
              errorBox.textContent = data.message || 'Something went wrong. Please try again.';
              errorBox.style.display = 'block';
              errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          })
          .catch(() => {
            btn.disabled = false;
            text.textContent = 'Confirm Booking';
            spinner.classList.add('d-none');
            errorBox.textContent = 'Network error. Please check your connection and try again.';
            errorBox.style.display = 'block';
          });
      });

      function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }
      function initials(name) {
        return (name || '').trim().charAt(0).toUpperCase() || '?';
      }

      // ── Deep-link support: /book-appointment.php?department=slug&doctor_id=12 ──
      <?php if ($pre_doctor_id): ?>
        window.__bkPreDoctorId = <?= (int) $pre_doctor_id ?>;
      <?php endif; ?>
      <?php if ($pre_department): ?>
        (function () {
          const preCard = document.querySelector('.bk-dept-card[data-slug="<?= addslashes($pre_department) ?>"]');
          if (preCard) preCard.click();
        })();
      <?php endif; ?>
    })();
  </script>
</body>

</html>
