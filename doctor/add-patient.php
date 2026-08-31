<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add Patient — Rejuvenate</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/style.css">
  <style>
    .ap-divider{display:flex;align-items:center;gap:12px;margin:26px 0 16px;color:#9ca3af;
      font-size:.72rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;}
    .ap-divider::before,.ap-divider::after{content:'';flex:1;height:1px;background:#e5e7eb;}
    .m-badge.soon{background:#9ca3af;}
    .ptype{border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;font-size:.82rem;color:#4b5563;background:#fbfbfd;}
    .ptype b{color:#1f2937;}
  </style>
</head>

<body>
  <main class="doctor-content">

    <div class="ap-header">
      <div>
        <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Add Patient</h5>
        <div class="ap-sub">Register a new patient or link one that is already on the portal</div>
      </div>
      <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn-back">
        <i class="fa fa-arrow-left mr-1"></i> Back
      </a>
    </div>

    <div class="row">
      <div class="col-lg-8">

        <div class="ptype mb-3">
          <i class="fa fa-info-circle mr-1" style="color:#0C74C5;"></i>
          Two kinds of patient: <b>with an ABHA</b> and <b>without an ABHA</b>. Both register the same way &mdash;
          verified by <b>WhatsApp OTP</b>. If the patient has an ABHA number, add it in the form; live ABDM
          verification will switch on once the integration is ready.
        </div>

        <!-- Primary: register with WhatsApp OTP -->
        <a href="<?= BASE_URL ?>doctor/add-patient-manual.php" class="method-card mb-3" style="--c:#0C74C5;">
          <div class="m-icon"><i class="fa fa-user-plus"></i></div>
          <div class="m-body">
            <div class="m-badge">RECOMMENDED</div>
            <div class="m-title">Register New Patient</div>
            <div class="m-desc">Fill the patient's details and verify their mobile with a WhatsApp OTP.
              Works whether or not they have an ABHA &mdash; add the ABHA number if they have one.</div>
            <div class="m-tags">
              <span class="text-success"><i class="fa fa-whatsapp"></i> WhatsApp OTP</span>
              <span class="text-info">ABHA optional</span>
              <span class="text-secondary">No ABHA needed</span>
            </div>
          </div>
          <div class="m-chevron"><i class="fa fa-chevron-right"></i></div>
        </a>

        <!-- Link an existing portal patient -->
        <a href="<?= BASE_URL ?>doctor/add-patient-mobile.php" class="method-card mb-3" style="--c:#7c3aed;">
          <div class="m-icon"><i class="fa fa-phone"></i></div>
          <div class="m-body">
            <div class="m-title">Search by Mobile Number</div>
            <div class="m-desc">Patient already registered on the portal? Search by their 10-digit mobile
              and link them to your panel instantly.</div>
            <div class="m-tags">
              <span><i class="fa fa-search"></i> Quick link</span>
            </div>
          </div>
          <div class="m-chevron"><i class="fa fa-chevron-right"></i></div>
        </a>

        <div class="ap-divider">ABHA verification</div>

        <!-- Coming soon: live ABDM verification -->
        <div class="method-card disabled mb-3" style="--c:#02c9b8;">
          <div class="m-icon"><i class="fa fa-id-card"></i></div>
          <div class="m-body">
            <div class="m-badge soon">COMING SOON</div>
            <div class="m-title">Verify / Create ABHA via ABDM</div>
            <div class="m-desc">Live Aadhaar-OTP and mobile-OTP ABHA verification, and new ABHA creation,
              through the ABDM gateway. Being integrated &mdash; for now, record the ABHA number
              directly on the registration form.</div>
            <div class="m-tags">
              <span><i class="fa fa-clock-o"></i> Aadhaar OTP</span>
              <span>Mobile OTP</span>
              <span>Create new ABHA</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Quick Info Sidebar -->
      <div class="col-lg-4">
        <div class="info-panel">
          <div class="info-head"><i class="fa fa-info-circle"></i> How it works</div>
          <div class="info-body">
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>Every patient is verified by a
                <strong>WhatsApp OTP</strong> sent to their mobile (and email)</span></div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>The OTP goes to the patient &mdash;
                they read the code back to you</span></div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span><strong>Has ABHA:</strong> enter the
                14-digit number on the form (stored now, verified later)</span></div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span><strong>No ABHA:</strong> just register
                &mdash; ABHA can be linked any time</span></div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>Already on the portal? Use
                <strong>Search by Mobile</strong> &mdash; no OTP needed</span></div>
            <div class="info-footer">
              <span><i class="fa fa-shield"></i> Secure</span>
              <span><i class="fa fa-lock"></i> Consent logged</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</body>

</html>
