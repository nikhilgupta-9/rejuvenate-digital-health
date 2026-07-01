<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';
$payload        = doctor_jwt_guard();
$doctor_id      = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Patient — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.method-card{border:2px solid #e5e7eb;border-radius:16px;padding:28px 22px;cursor:pointer;
  transition:.2s;background:#fff;display:flex;align-items:flex-start;gap:18px;text-decoration:none;color:inherit;}
.method-card:hover{border-color:var(--c);box-shadow:0 6px 22px rgba(0,0,0,.1);transform:translateY(-2px);text-decoration:none;color:inherit;}
.m-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;color:#fff;flex-shrink:0;background:var(--c);}
.m-title{font-size:.95rem;font-weight:700;color:#1f2937;margin-bottom:4px;}
.m-desc{font-size:.78rem;color:#6b7280;line-height:1.5;}
.m-badge{display:inline-block;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:20px;
  background:var(--c);color:#fff;letter-spacing:.4px;margin-bottom:6px;}
</style>
</head>
<body>
<main class="doctor-content">

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0 font-weight-bold" style="color:#1f2937;">Add Patient</h5>
    <div style="font-size:.74rem;color:#9ca3af;">Choose how you want to add this patient to your panel</div>
  </div>
  <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-sm btn-outline-secondary">
    <i class="fa fa-arrow-left mr-1"></i> Back
  </a>
</div>

<div class="row">
<div class="col-lg-7">

  <!-- Primary recommendation -->
  <a href="<?= BASE_URL ?>doctor/add-patient-abha.php"
     class="method-card mb-3 d-flex" style="--c:#0C74C5;">
    <div class="m-icon"><i class="fa fa-id-card-o"></i></div>
    <div>
      <div class="m-badge">RECOMMENDED</div>
      <div class="m-title">Verify via Aadhaar / Mobile OTP</div>
      <div class="m-desc">Patient doesn't remember ABHA ID? Verify using Aadhaar OTP or mobile OTP — ABDM will find their existing ABHA and pull the full profile instantly.</div>
    </div>
  </a>

  <a href="<?= BASE_URL ?>doctor/add-patient-new.php"
     class="method-card mb-3 d-flex" style="--c:#02c9b8;">
    <div class="m-icon"><i class="fa fa-plus-circle"></i></div>
    <div>
      <div class="m-badge" style="background:#02c9b8;">NEW ABHA</div>
      <div class="m-title">Create New ABHA for Patient</div>
      <div class="m-desc">Patient has no ABHA yet. Enter their Aadhaar → OTP → ABHA number created on ABDM in seconds.</div>
    </div>
  </a>

  <a href="<?= BASE_URL ?>doctor/add-patient-mobile.php"
     class="method-card mb-3 d-flex" style="--c:#7c3aed;">
    <div class="m-icon"><i class="fa fa-phone"></i></div>
    <div>
      <div class="m-title">Search by Mobile Number</div>
      <div class="m-desc">Already in portal? Search by 10-digit mobile and link instantly without ABDM.</div>
    </div>
  </a>

  <a href="<?= BASE_URL ?>doctor/add-patient-manual.php"
     class="method-card mb-3 d-flex" style="--c:#e07e18;">
    <div class="m-icon"><i class="fa fa-pencil"></i></div>
    <div>
      <div class="m-title">Fill Form Manually</div>
      <div class="m-desc">No Aadhaar, no ABHA. Fill basic details manually. ABHA can be linked later.</div>
    </div>
  </a>

  <?php if (!ABDM_CONFIGURED): ?>
  <div class="alert alert-warning mt-2" style="border-radius:10px;font-size:.82rem;">
    <i class="fa fa-exclamation-triangle mr-2"></i>
    <strong>ABDM not configured.</strong> Set <code>ABDM_CLIENT_ID</code> and <code>ABDM_CLIENT_SECRET</code> in <code>.env</code>.
    ABHA options won't work until configured.
  </div>
  <?php endif; ?>

</div>
</div>
</main>
</body>
</html>
