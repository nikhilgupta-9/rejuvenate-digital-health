<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';
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
</head>

<body>
  <main class="doctor-content">

    <div class="ap-header">
      <div>
        <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Add Patient</h5>
        <div class="ap-sub">Choose how you want to add this patient to your panel</div>
      </div>
      <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn-back">
        <i class="fa fa-arrow-left mr-1"></i> Back
      </a>
    </div>

    <div class="row">
      <div class="col-lg-8">

        <?php if (!ABDM_CONFIGURED): ?>
          <div class="alert alert-warning mb-3" style="border-radius:10px;font-size:.82rem;">
            <i class="fa fa-exclamation-triangle mr-2"></i>
            <strong>ABDM not configured.</strong> Set <code>ABDM_CLIENT_ID</code> and <code>ABDM_CLIENT_SECRET</code> in
            <code>.env</code>.
          </div>
        <?php endif; ?>

        <!-- Primary: ABHA Verification -->
        <a href="<?= BASE_URL ?>doctor/add-patient-abha.php" class="method-card mb-3" style="--c:#0C74C5;">
          <div class="m-icon"><i class="fa fa-id-card"></i></div>
          <div class="m-body">
            <div class="m-badge">RECOMMENDED</div>
            <div class="m-title">Verify Existing ABHA</div>
            <div class="m-desc">Patient already has ABHA? Verify using Aadhaar OTP or mobile OTP — ABDM will find their
              existing ABHA and pull the full profile instantly.</div>
            <div class="m-tags">
              <span class="text-success"><i class="fa fa-check-circle"></i> Aadhaar OTP</span>
              <span class="text-info">Mobile OTP</span>
              <span class="text-secondary">ABHA Number</span>
              <span class="text-secondary">ABHA Address</span>
            </div>
          </div>
          <div class="m-chevron"><i class="fa fa-chevron-right"></i></div>
        </a>

        <!-- Create New ABHA -->
        <a href="<?= BASE_URL ?>doctor/add-patient-new-abha.php" class="method-card mb-3" style="--c:#02c9b8;">
          <div class="m-icon"><i class="fa fa-plus-circle"></i></div>
          <div class="m-body">
            <div class="m-badge" style="background:#02c9b8;">NEW</div>
            <div class="m-title">Create New ABHA for Patient</div>
            <div class="m-desc">Patient has no ABHA yet. Enter their Aadhaar → OTP → Create new ABHA number on ABDM in
              seconds.</div>
            <div class="m-tags">
              <span class="text-success"><i class="fa fa-check-circle"></i> Aadhaar OTP required</span>
            </div>
          </div>
          <div class="m-chevron"><i class="fa fa-chevron-right"></i></div>
        </a>

        <!-- Mobile Search -->
        <a href="<?= BASE_URL ?>doctor/add-patient-mobile.php" class="method-card mb-3" style="--c:#7c3aed;">
          <div class="m-icon"><i class="fa fa-phone"></i></div>
          <div class="m-body">
            <div class="m-title">Search by Mobile Number</div>
            <div class="m-desc">Already in your portal? Search by 10-digit mobile and link instantly without ABDM
              verification.</div>
            <div class="m-tags">
              <span><i class="fa fa-search"></i> Quick search</span>
            </div>
          </div>
          <div class="m-chevron"><i class="fa fa-chevron-right"></i></div>
        </a>

        <!-- Manual Entry -->
        <a href="<?= BASE_URL ?>doctor/add-patient-manual.php" class="method-card mb-3" style="--c:#e07e18;">
          <div class="m-icon"><i class="fa fa-pencil"></i></div>
          <div class="m-body">
            <div class="m-title">Fill Form Manually</div>
            <div class="m-desc">No Aadhaar, no ABHA. Fill basic details manually. ABHA can be linked later.</div>
            <div class="m-tags">
              <span><i class="fa fa-user"></i> Manual entry</span>
            </div>
          </div>
          <div class="m-chevron"><i class="fa fa-chevron-right"></i></div>
        </a>

      </div>

      <!-- Quick Info Sidebar -->
      <div class="col-lg-4">
        <div class="info-panel">
          <div class="info-head"><i class="fa fa-info-circle"></i> How it works</div>
          <div class="info-body">
            <div class="info-item"><i class="fa fa-check-circle"></i> <span><strong>ABHA</strong> = Ayushman Bharat
                Health Account</span></div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>One ABHA per patient across India</span>
            </div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>Verify via Aadhaar OTP in under a
                minute</span></div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>Automatic profile pull from ABDM</span>
            </div>
            <div class="info-item"><i class="fa fa-check-circle"></i> <span>No duplicate ABHA creation</span></div>
            <div class="info-footer">
              <span><i class="fa fa-shield"></i> Secure</span>
              <span><i class="fa fa-lock"></i> Encrypted</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</body>

</html>