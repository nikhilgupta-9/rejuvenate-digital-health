<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once dirname(__DIR__) . '/config/abdm.php';
require_once dirname(__DIR__) . '/lib/Security.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars(Security::csrfToken()) ?>">
  <title>ABHA Patient Verification (M1) — REJUVENATE</title>
  <!-- CSS Dependencies -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/doctor.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/style.css">

  <style>
    :root {
      --primary: #0C74C5;
      --primary-dk: #0a5fa0;
      --primary-light: #e0f2fe;
      --accent: #02c9b8;
      --accent-dk: #01a89a;
      --accent-light: #ccfbf1;
      --success: #16a34a;
      --warning: #f59e0b;
      --danger: #dc2626;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-500: #6b7280;
      --gray-700: #374151;
      --gray-800: #1f2937;
      --card-radius: 16px;
    }

    body {
      background-color: #f4f7fb;
      color: var(--gray-800);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    .abha-container {
      max-width: 740px;
      margin: 0 auto;
      padding-bottom: 40px;
    }

    /* Stepper Header */
    .stepper-wrapper {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin-bottom: 24px;
      background: #fff;
      padding: 16px 20px;
      border-radius: var(--card-radius);
      box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
    }

    .stepper-wrapper::before {
      content: '';
      position: absolute;
      top: 36px;
      left: 15%;
      right: 15%;
      height: 3px;
      background: var(--gray-200);
      z-index: 0;
    }

    .step-item {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      text-align: center;
    }

    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #fff;
      border: 2px solid var(--gray-300);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .88rem;
      color: var(--gray-500);
      transition: all .25s ease;
      box-shadow: 0 2px 5px rgba(0,0,0,.04);
    }

    .step-label {
      margin-top: 8px;
      font-size: .75rem;
      font-weight: 600;
      color: var(--gray-500);
      transition: color .25s ease;
    }

    .step-item.active .step-circle {
      border-color: var(--primary);
      background: var(--primary);
      color: #fff;
      box-shadow: 0 0 0 4px rgba(12, 116, 197, 0.2);
    }
    .step-item.active .step-label {
      color: var(--primary);
      font-weight: 700;
    }

    .step-item.done .step-circle {
      border-color: var(--success);
      background: var(--success);
      color: #fff;
    }
    .step-item.done .step-label {
      color: var(--success);
    }

    /* Verification Card */
    .v-card {
      background: #fff;
      border-radius: var(--card-radius);
      box-shadow: 0 3px 14px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      padding: 26px 28px;
      margin-bottom: 22px;
      position: relative;
    }

    .v-card.highlight {
      border-top: 4px solid var(--primary);
    }

    .v-card-title {
      font-size: .92rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: var(--primary);
      margin-bottom: 18px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Method Selector Pills */
    .method-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 20px;
    }

    .method-btn {
      background: #fff;
      border: 2px solid var(--gray-200);
      border-radius: 12px;
      padding: 12px 8px;
      text-align: center;
      cursor: pointer;
      transition: all .2s ease;
      color: var(--gray-700);
      font-size: .8rem;
      font-weight: 600;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      position: relative;
    }

    .method-btn i {
      font-size: 1.25rem;
      color: var(--gray-500);
      transition: color .2s ease;
    }

    .method-btn:hover {
      border-color: var(--primary);
      background: var(--primary-light);
      color: var(--primary);
    }
    .method-btn:hover i {
      color: var(--primary);
    }

    .method-btn.active {
      border-color: var(--primary);
      background: var(--primary-light);
      color: var(--primary);
      box-shadow: 0 3px 10px rgba(12, 116, 197, 0.15);
    }
    .method-btn.active i {
      color: var(--primary);
    }

    .method-badge {
      font-size: .58rem;
      background: var(--accent);
      color: #fff;
      padding: 1px 6px;
      border-radius: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
    }

    /* Input Group styling */
    .custom-input-group {
      position: relative;
      margin-bottom: 6px;
    }

    .custom-input-group input {
      font-size: 1.15rem;
      font-weight: 600;
      letter-spacing: 1.5px;
      padding: 12px 16px;
      border-radius: 10px;
      border: 2px solid var(--gray-200);
      transition: border-color .2s ease, box-shadow .2s ease;
      color: var(--gray-800);
      width: 100%;
    }

    .custom-input-group input:focus {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 0 0 3px rgba(12, 116, 197, 0.16);
    }

    .btn-qr-scan {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: var(--gray-100);
      border: 1px solid var(--gray-300);
      border-radius: 8px;
      color: var(--gray-700);
      padding: 6px 12px;
      font-size: .82rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
      transition: all .15s ease;
    }

    .btn-qr-scan:hover {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    /* ABDM Informed Consent Box */
    .consent-card {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      padding: 14px 16px;
      margin: 18px 0;
    }

    .consent-text {
      max-height: 85px;
      overflow-y: auto;
      font-size: .74rem;
      color: #166534;
      line-height: 1.5;
      padding-right: 6px;
      margin-bottom: 10px;
    }

    .consent-text::-webkit-scrollbar {
      width: 4px;
    }
    .consent-text::-webkit-scrollbar-thumb {
      background: #86efac;
      border-radius: 4px;
    }

    /* 6-box OTP Container */
    .otp-row {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 22px 0;
    }

    .otp-box-input {
      width: 48px;
      height: 54px;
      border-radius: 10px;
      border: 2px solid var(--gray-300);
      text-align: center;
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--primary);
      transition: all .15s ease;
      background: #fff;
    }

    .otp-box-input:focus {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 0 0 3px rgba(12, 116, 197, 0.18);
      transform: translateY(-2px);
    }

    .otp-box-input.filled {
      border-color: var(--success);
      background: #f0fdf4;
    }

    /* Buttons */
    .btn-primary-custom {
      background: var(--primary);
      border: 1px solid var(--primary);
      color: #fff;
      font-weight: 700;
      padding: 11px 24px;
      border-radius: 10px;
      font-size: .88rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all .2s ease;
      box-shadow: 0 3px 10px rgba(12, 116, 197, 0.25);
      cursor: pointer;
    }

    .btn-primary-custom:hover {
      background: var(--primary-dk);
      border-color: var(--primary-dk);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 5px 14px rgba(12, 116, 197, 0.35);
    }

    .btn-primary-custom:disabled {
      opacity: 0.65;
      cursor: not-allowed;
      transform: none;
    }

    .btn-outline-custom {
      background: #fff;
      border: 1.5px solid var(--gray-300);
      color: var(--gray-700);
      font-weight: 600;
      padding: 10px 20px;
      border-radius: 10px;
      font-size: .85rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all .2s ease;
      cursor: pointer;
      text-decoration: none;
    }

    .btn-outline-custom:hover {
      border-color: var(--gray-500);
      background: var(--gray-50);
      color: var(--gray-800);
      text-decoration: none;
    }

    /* Account Selection Cards */
    .account-select-card {
      border: 2px solid var(--gray-200);
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 12px;
      background: #fff;
      transition: all .2s ease;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .account-select-card:hover {
      border-color: var(--primary);
      background: #f8fbff;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(12, 116, 197, 0.1);
    }

    /* Official ABDM Digital Health Card Layout */
    .official-abha-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      border: 1px solid var(--gray-200);
      overflow: hidden;
      margin: 10px auto 24px;
      max-width: 480px;
      text-align: left;
      position: relative;
    }

    .abha-card-header {
      background: linear-gradient(135deg, #0C74C5 0%, #084c82 100%);
      color: #fff;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid #ff9933;
    }

    .abha-gov-badge {
      font-size: .65rem;
      text-transform: uppercase;
      letter-spacing: .8px;
      opacity: .9;
      font-weight: 600;
    }

    .abha-title {
      font-size: .95rem;
      font-weight: 800;
      letter-spacing: .4px;
      margin: 0;
    }

    .abha-card-body {
      padding: 20px;
      background: #ffffff;
      position: relative;
    }

    .abha-watermark {
      position: absolute;
      right: 15px;
      bottom: 15px;
      font-size: 8rem;
      opacity: 0.04;
      color: #0C74C5;
      pointer-events: none;
    }

    .abha-person-row {
      display: flex;
      gap: 16px;
      align-items: center;
      margin-bottom: 16px;
    }

    .abha-avatar-box {
      width: 72px;
      height: 72px;
      border-radius: 14px;
      background: #e0f2fe;
      border: 2px solid #bae6fd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      color: #0284c7;
      overflow: hidden;
      flex-shrink: 0;
    }

    .abha-avatar-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .abha-patient-name {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--gray-800);
      margin-bottom: 2px;
    }

    .abha-demographics {
      font-size: .78rem;
      color: var(--gray-500);
    }

    .abha-num-display {
      background: #f8fafc;
      border: 1.5px dashed #cbd5e1;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .abha-num-val {
      font-family: 'Consolas', 'Courier New', monospace;
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: 2px;
      color: #0C74C5;
    }

    .abha-addr-display {
      font-size: .84rem;
      color: #334155;
      font-weight: 600;
      margin-bottom: 6px;
    }

    .abha-verified-seal {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
    }

    /* Badges & Status */
    .step-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .step-badge.pending { background: #fef3c7; color: #92400e; }
    .step-badge.completed { background: #dcfce7; color: #166534; }
    .step-badge.failed { background: #fee2e2; color: #991b1b; }

    /* Mobile adjustments */
    @media (max-width: 767.98px) {
      .stepper-wrapper {
        padding: 12px 14px;
        margin-bottom: 16px;
      }
      .stepper-wrapper::before {
        top: 30px;
        left: 20%;
        right: 20%;
      }
      .step-circle {
        width: 34px;
        height: 34px;
        font-size: .8rem;
      }
      .step-label {
        font-size: .68rem;
        margin-top: 6px;
      }
      .method-grid {
        grid-template-columns: 1fr;
        gap: 8px;
      }
      .method-btn {
        flex-direction: row;
        justify-content: flex-start;
        padding: 12px 16px;
        gap: 12px;
      }
      .method-btn i {
        font-size: 1.1rem;
      }
      .v-card {
        padding: 18px 16px;
      }
      .otp-row {
        gap: 6px;
      }
      .otp-box-input {
        width: calc((100% - 30px) / 6);
        max-width: 44px;
        height: 48px;
        font-size: 1.2rem;
      }
      .abha-person-row {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body>
  <?php
  $sidebar_active = 'add-patient';
  include __DIR__ . '/inc/sidebar.php';
  ?>

  <main class="doctor-content">
    <div class="abha-container">

      <!-- Navigation & Title Header -->
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <a href="<?= BASE_URL ?>doctor/add-patient.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-2" style="font-size:.78rem;font-weight:600;">
            <i class="fa fa-arrow-left me-1"></i> Patient Registration Modes
          </a>
          <h1 style="font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:0;">
            ABDM Patient Verification &amp; Discovery (M1)
          </h1>
          <div style="font-size:.78rem;color:var(--gray-500);">
            National Health Authority &bull; Ayushman Bharat Health Account (ABHA) Verification Hub
          </div>
        </div>
        <div>
          <span class="step-badge pending" id="statusBadge">
            <i class="fa fa-clock"></i> Pending
          </span>
        </div>
      </div>

      <?php if (!ABDM_CONFIGURED): ?>
        <div class="alert alert-danger shadow-sm rounded-3">
          <i class="fa fa-times-circle me-2"></i> <strong>ABDM Configuration Missing:</strong> ABDM API credentials have not been configured on this server. Please contact your system administrator.
        </div>
      <?php else: ?>

        <!-- 3-Step Wizard Progress Bar -->
        <div class="stepper-wrapper">
          <div class="step-item active" id="stepIndicator1">
            <div class="step-circle"><i class="fa fa-id-card"></i></div>
            <span class="step-label">1. Identification</span>
          </div>
          <div class="step-item" id="stepIndicator2">
            <div class="step-circle"><i class="fa fa-mobile-screen"></i></div>
            <span class="step-label">2. OTP Authentication</span>
          </div>
          <div class="step-item" id="stepIndicator3">
            <div class="step-circle"><i class="fa fa-circle-check"></i></div>
            <span class="step-label">3. Verified Profile</span>
          </div>
        </div>

        <!-- Step 1: Patient Identity -->
        <div class="step-content" id="step1" style="display:block;">
          <div class="v-card highlight">
            <div class="v-card-title">
              <i class="fa fa-shield-halved"></i> Patient Authentication Modality
            </div>

            <!-- Verification Method Pills -->
            <label class="form-label fw-bold" style="font-size:.82rem;color:var(--gray-700);">Select Verification Mode:</label>
            <div class="method-grid">
              <button type="button" class="method-btn active" onclick="setMethod('aadhaar', this)">
                <i class="fa fa-id-card"></i>
                <span>Aadhaar OTP</span>
                <span class="method-badge">Recommended</span>
              </button>
              <button type="button" class="method-btn" onclick="setMethod('mobile', this)">
                <i class="fa fa-mobile-screen-button"></i>
                <span>Mobile OTP</span>
                <span class="badge bg-light text-muted" style="font-size:.6rem;">Direct Login</span>
              </button>
              <button type="button" class="method-btn" onclick="setMethod('number', this)">
                <i class="fa fa-barcode"></i>
                <span>14-Digit ABHA</span>
                <span class="badge bg-light text-muted" style="font-size:.6rem;">Card Number</span>
              </button>
            </div>

            <!-- Encryption & Compliance Note -->
            <div id="methodNote" class="alert alert-info py-2 px-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;font-size:.78rem;color:#1e40af;">
              <i class="fa fa-lock me-1"></i>
              <span id="methodNoteText">Aadhaar details are RSA-2048 encrypted before submission. In compliance with UIDAI &amp; ABDM rules, raw Aadhaar is <strong>never stored</strong>.</span>
            </div>

            <!-- Input Field -->
            <div class="form-group mb-3">
              <label class="form-label fw-bold" id="inputLabel" style="font-size:.84rem;color:var(--gray-700);">
                Aadhaar Number <span class="text-danger">*</span>
              </label>
              <div class="custom-input-group">
                <input type="password" id="mainInput" placeholder="•••• •••• ••••" maxlength="12" autocomplete="off">
                <button type="button" class="btn-qr-scan" id="btnScanAbhaQr" style="display:none;" title="Scan ABHA Card QR">
                  <i class="fa fa-qrcode"></i> Scan QR
                </button>
              </div>
              <div class="form-text" id="inputHint" style="font-size:.74rem;color:var(--gray-500);margin-top:4px;">
                12-digit Aadhaar — OTP sent to Aadhaar-linked registered mobile
              </div>
            </div>

            <!-- ABDM Informed Patient Consent (Aadhaar Mode Only) -->
            <div id="aadhaarConsentGroup" class="consent-card">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa fa-shield-check text-success"></i>
                <span class="fw-bold" style="font-size:.8rem;color:#166534;">Informed Patient Consent (ABDM Guidelines)</span>
              </div>
              <div class="consent-text">
                The patient hereby voluntarily authorizes the sharing of their Aadhaar number, name, gender, and date of birth with the National Health Authority (NHA) for the sole purpose of ABHA creation/verification and electronic health records linkage under the Ayushman Bharat Digital Mission (ABDM). The patient acknowledges their identifiable data is handled securely under the Digital Personal Data Protection Act.
              </div>
              <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="aadhaar_consent" value="1">
                <label class="form-check-label fw-bold" for="aadhaar_consent" style="font-size:.8rem;color:#166534;">
                  I confirm the patient has given informed consent as required under ABDM guidelines.
                </label>
              </div>
            </div>

            <button type="button" class="btn-primary-custom w-100 mt-2" id="btnSend">
              <i class="fa fa-paper-plane"></i> Send OTP to Patient
            </button>

            <div id="error1" class="alert alert-danger mt-3 py-2 px-3 rounded-3" style="display:none;font-size:.82rem;"></div>
            <div id="info1" class="alert alert-info mt-3 py-2 px-3 rounded-3" style="display:none;font-size:.82rem;"></div>
          </div>
        </div>

        <!-- Step 2: OTP Verification -->
        <div class="step-content" id="step2" style="display:none;">
          <div class="v-card highlight">
            <div class="v-card-title">
              <i class="fa fa-mobile-screen"></i> Enter Verification OTP
            </div>

            <div class="text-center mb-3">
              <div class="badge bg-success-subtle text-success py-2 px-3 rounded-pill fw-bold" id="sentMsg" style="font-size:.82rem;">
                <i class="fa fa-check-circle me-1"></i> OTP sent to registered mobile
              </div>
              <div class="text-muted mt-2" style="font-size:.74rem;" id="otpExpiryInfo">OTP valid for 5 minutes</div>
            </div>

            <!-- 6-digit OTP Inputs -->
            <div class="otp-row" id="otpContainer">
              <input type="text" class="otp-box-input" maxlength="1" inputmode="numeric" autofocus>
              <input type="text" class="otp-box-input" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-box-input" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-box-input" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-box-input" maxlength="1" inputmode="numeric">
              <input type="text" class="otp-box-input" maxlength="1" inputmode="numeric">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <button type="button" class="btn btn-link p-0 text-decoration-none fw-bold" id="btnResend" disabled style="font-size:.82rem;color:var(--primary);">
                <i class="fa fa-rotate-right me-1"></i> Resend OTP
              </button>
              <span class="text-muted fw-bold" id="timerEl" style="font-size:.78rem;">Resend in 300s</span>
            </div>

            <!-- Communication mobile for new Aadhaar enrolments -->
            <div class="form-group mb-3" id="commMobileGroup" style="display:none;">
              <label class="form-label fw-bold" style="font-size:.82rem;color:var(--gray-700);">
                Communication Mobile Number <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control" id="commMobileInput" maxlength="10" inputmode="numeric" placeholder="e.g. 9876543210" style="border-radius:10px;">
              <div class="form-text" style="font-size:.72rem;">10-digit mobile number linked to this ABHA for communications</div>
            </div>

            <div id="error2" class="alert alert-danger py-2 px-3 rounded-3" style="display:none;font-size:.82rem;"></div>

            <div class="d-flex gap-2 mt-4">
              <button type="button" class="btn-outline-custom flex-grow-1" onclick="goToStep(1)">
                <i class="fa fa-arrow-left me-1"></i> Back
              </button>
              <button type="button" class="btn-primary-custom flex-grow-1" id="btnVerify">
                <i class="fa fa-check-circle me-1"></i> Verify OTP
              </button>
            </div>
          </div>
        </div>

        <!-- Step 2b: Multiple ABHA Selection -->
        <div class="step-content" id="step2b" style="display:none;">
          <div class="v-card highlight">
            <div class="v-card-title">
              <i class="fa fa-users"></i> Multiple ABHA Profiles Discovered
            </div>
            <div class="alert alert-info py-2 px-3 rounded-3 mb-3" style="font-size:.8rem;">
              <i class="fa fa-circle-info me-1"></i> Multiple ABHA accounts were found linked to this mobile number. Please select the correct patient.
            </div>
            <div id="accountList"></div>
            <div id="error2b" class="alert alert-danger py-2 px-3 rounded-3 mt-3" style="display:none;font-size:.82rem;"></div>
          </div>
        </div>

        <!-- Step 3: Result & ABHA Card Display -->
        <div class="step-content" id="step3" style="display:none;">
          <div class="v-card text-center" id="resultCard">
            <div id="resultContent">
              <div class="spinner-border text-primary my-4" role="status"></div>
              <h5 class="fw-bold text-dark">Verifying with ABDM...</h5>
              <p class="text-muted" style="font-size:.85rem;">Communicating with National Health Authority sandbox</p>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>

  <script src="<?= BASE_URL ?>assets/js/abha-qr-scanner.js"></script>
  <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    // State variables
    let currentStep = 1;
    let verificationType = 'aadhaar';
    let txnId = '';
    let timerInterval = null;
    let timerSeconds = 300;

    // Step DOM elements
    const stepContent = {
      1: document.getElementById('step1'),
      2: document.getElementById('step2'),
      "2b": document.getElementById('step2b'),
      3: document.getElementById('step3')
    };

    const stepIndicators = {
      1: document.getElementById('stepIndicator1'),
      2: document.getElementById('stepIndicator2'),
      3: document.getElementById('stepIndicator3')
    };

    // Navigation function
    function goToStep(step) {
      Object.values(stepContent).forEach(el => {
        if (el) el.style.display = 'none';
      });

      if (stepContent[step]) {
        stepContent[step].style.display = 'block';
      }

      // Update 3 indicators
      const stepIdx = (step === '2b' || step === 2) ? 2 : (step === 3 ? 3 : 1);
      [1, 2, 3].forEach(s => {
        const ind = stepIndicators[s];
        if (!ind) return;
        ind.className = 'step-item';
        if (s < stepIdx) {
          ind.classList.add('done');
        } else if (s === stepIdx) {
          ind.classList.add('active');
        }
      });

      currentStep = step;

      const badge = document.getElementById('statusBadge');
      if (step === 1) {
        badge.className = 'step-badge pending';
        badge.innerHTML = '<i class="fa fa-clock"></i> Pending';
      } else if (step === 2 || step === "2b") {
        badge.className = 'step-badge pending';
        badge.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying';
      }
    }

    // Method Switcher
    function setMethod(type, btn) {
      document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      verificationType = type;

      const input = document.getElementById('mainInput');
      const note = document.getElementById('methodNoteText');
      const noteBox = document.getElementById('methodNote');
      const label = document.getElementById('inputLabel');
      const hint = document.getElementById('inputHint');
      const qrBtn = document.getElementById('btnScanAbhaQr');
      const consentGrp = document.getElementById('aadhaarConsentGroup');

      input.value = '';
      document.getElementById('error1').style.display = 'none';

      if (type === 'aadhaar') {
        label.innerHTML = 'Aadhaar Number <span class="text-danger">*</span>';
        input.placeholder = '•••• •••• ••••';
        input.maxLength = 12;
        input.type = 'password';
        input.inputMode = 'numeric';
        hint.textContent = '12-digit Aadhaar — OTP sent to Aadhaar-linked registered mobile';
        note.innerHTML = 'Aadhaar details are RSA-2048 encrypted before submission. In compliance with UIDAI &amp; ABDM rules, raw Aadhaar is <strong>never stored</strong>.';
        noteBox.style.display = 'block';
        qrBtn.style.display = 'none';
        consentGrp.style.display = 'block';
      } else if (type === 'mobile') {
        label.innerHTML = 'Registered Mobile Number <span class="text-danger">*</span>';
        input.placeholder = '9876543210';
        input.maxLength = 10;
        input.type = 'text';
        input.inputMode = 'numeric';
        hint.textContent = '10-digit mobile — OTP sent to this number to discover linked ABHAs';
        note.innerHTML = 'An OTP will be sent to this mobile number. If one or more ABHA accounts are linked, you can log in directly without Aadhaar.';
        noteBox.style.display = 'block';
        qrBtn.style.display = 'none';
        consentGrp.style.display = 'none';
      } else if (type === 'number') {
        label.innerHTML = '14-Digit ABHA Number <span class="text-danger">*</span>';
        input.placeholder = 'XX-XXXX-XXXX-XXXX';
        input.maxLength = 17;
        input.type = 'text';
        input.inputMode = 'numeric';
        hint.textContent = '14-digit ABHA number from patient card (e.g. 91-1234-5678-9012)';
        noteBox.style.display = 'none';
        qrBtn.style.display = 'inline-flex';
        consentGrp.style.display = 'none';
      }
    }

    // QR Scanner Trigger
    document.getElementById('btnScanAbhaQr')?.addEventListener('click', function () {
      AbhaQrScanner.open({
        title: 'Scan Patient ABHA QR',
        onResult(parsed) {
          const input = document.getElementById('mainInput');
          if (parsed.abha_number) {
            let digits = parsed.abha_number.replace(/\D/g, '').slice(0, 14);
            if (digits.length === 14) {
              input.value = digits.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4');
            } else {
              input.value = parsed.abha_number;
            }
          } else {
            showError('error1', 'Scanned QR code did not contain a valid 14-digit ABHA Number.');
          }
        },
        onUnsupported() {
          showError('error1', 'Camera barcode scanning is not supported on this browser. Please enter manually.');
        }
      });
    });

    // Formatting 14-digit ABHA number live
    document.getElementById('mainInput').addEventListener('input', function () {
      if (verificationType === 'number') {
        let digits = this.value.replace(/\D/g, '').slice(0, 14);
        let formatted = digits;
        if (digits.length > 2) formatted = digits.slice(0, 2) + '-' + digits.slice(2);
        if (digits.length > 6) formatted = digits.slice(0, 2) + '-' + digits.slice(2, 6) + '-' + digits.slice(6);
        if (digits.length > 10) formatted = digits.slice(0, 2) + '-' + digits.slice(2, 6) + '-' + digits.slice(6, 10) + '-' + digits.slice(10);
        this.value = formatted;
      }
    });

    // OTP Input Controls
    document.querySelectorAll('.otp-box-input').forEach((el, i, all) => {
      el.addEventListener('input', () => {
        el.value = el.value.replace(/\D/g, '').slice(-1);
        if (el.value) {
          el.classList.add('filled');
          if (all[i + 1]) all[i + 1].focus();
        } else {
          el.classList.remove('filled');
        }
      });

      el.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !el.value && all[i - 1]) {
          all[i - 1].focus();
        }
      });

      el.addEventListener('paste', e => {
        e.preventDefault();
        const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        all.forEach((box, idx) => {
          box.value = data[idx] || '';
          box.classList.toggle('filled', !!box.value);
        });
        const nextIdx = Math.min(data.length, all.length - 1);
        if (data.length > 0) all[nextIdx].focus();
      });
    });

    function getOTP() {
      return [...document.querySelectorAll('.otp-box-input')].map(e => e.value).join('');
    }

    function showError(id, message) {
      const el = document.getElementById(id);
      if (!el) return;
      el.innerHTML = '<i class="fa fa-circle-exclamation me-1"></i> ' + escapeHtml(message);
      el.style.display = 'block';
    }

    function hideError(id) {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    }

    // Countdown Timer
    function startTimer(seconds) {
      timerSeconds = seconds;
      const btn = document.getElementById('btnResend');
      const el = document.getElementById('timerEl');
      btn.disabled = true;

      if (timerInterval) clearInterval(timerInterval);

      timerInterval = setInterval(() => {
        timerSeconds--;
        if (timerSeconds <= 0) {
          clearInterval(timerInterval);
          el.textContent = '';
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-rotate-right me-1"></i> Resend OTP';
        } else {
          el.textContent = `Resend in ${timerSeconds}s`;
        }
      }, 1000);
    }

    // Send OTP Handler
    document.getElementById('btnSend').addEventListener('click', function () {
      const value = document.getElementById('mainInput').value.trim();

      if (verificationType === 'aadhaar') {
        const clean = value.replace(/\D/g, '');
        if (clean.length !== 12) {
          showError('error1', 'Please enter a valid 12-digit Aadhaar number.');
          return;
        }
        if (!document.getElementById('aadhaar_consent').checked) {
          showError('error1', 'Please confirm patient consent as required by ABDM guidelines.');
          return;
        }
      } else if (verificationType === 'mobile') {
        const clean = value.replace(/\D/g, '');
        if (clean.length !== 10) {
          showError('error1', 'Please enter a valid 10-digit mobile number.');
          return;
        }
      } else if (verificationType === 'number') {
        const clean = value.replace(/\D/g, '');
        if (clean.length !== 14) {
          showError('error1', 'Please enter a valid 14-digit ABHA card number.');
          return;
        }
      }

      hideError('error1');

      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Sending OTP...';

      const payload = {
        action: 'send_otp',
        abha_input: value,
        type: verificationType,
        consent: verificationType === 'aadhaar' ? 1 : undefined,
        _csrf: CSRF_TOKEN
      };

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-paper-plane me-1"></i> Send OTP to Patient';

        if (!data.success) {
          showError('error1', data.error || data.message || 'Failed to send OTP.');
          return;
        }

        txnId = data.txnId;
        sessionStorage.setItem('abdm_txnId', data.txnId);
        sessionStorage.setItem('abdm_input_type', verificationType);
        sessionStorage.setItem('abdm_input_value', value);

        document.getElementById('sentMsg').innerHTML = `
          <i class="fa fa-check-circle me-1"></i> ${data.message || 'OTP sent successfully to registered mobile'}
        `;

        document.querySelectorAll('.otp-box-input').forEach(el => {
          el.value = '';
          el.classList.remove('filled');
        });

        document.getElementById('commMobileGroup').style.display =
          verificationType === 'aadhaar' ? 'block' : 'none';

        goToStep(2);
        startTimer(300);
        document.querySelector('.otp-box-input').focus();
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-paper-plane me-1"></i> Send OTP to Patient';
        showError('error1', 'Network error: ' + err.message);
      });
    });

    document.getElementById('btnResend').addEventListener('click', function () {
      document.getElementById('btnSend').click();
    });

    // Verify OTP Handler
    document.getElementById('btnVerify').addEventListener('click', function () {
      const otp = getOTP();
      if (otp.length < 6) {
        showError('error2', 'Please enter the complete 6-digit OTP code.');
        return;
      }

      let commMobile = '';
      if (verificationType === 'aadhaar') {
        commMobile = document.getElementById('commMobileInput').value.replace(/\D/g, '');
        if (commMobile && commMobile.length !== 10) {
          showError('error2', 'Please enter a valid 10-digit mobile number for communication.');
          return;
        }
      }

      hideError('error2');

      if (!txnId) {
        txnId = sessionStorage.getItem('abdm_txnId') || '';
        if (!txnId) {
          showError('error2', 'Transaction session expired. Please resend OTP.');
          return;
        }
      }

      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Verifying...';

      const payload = {
        action: 'verify_otp',
        txnId: txnId,
        otp: otp,
        type: verificationType,
        _csrf: CSRF_TOKEN
      };

      if (verificationType === 'aadhaar') {
        payload.mobile = commMobile;
      } else if (verificationType === 'mobile') {
        payload.mobile = sessionStorage.getItem('abdm_input_value') || '';
      }

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check-circle me-1"></i> Verify OTP';

        if (!data.success) {
          showError('error2', data.error || data.message || 'OTP verification failed.');
          return;
        }

        if (data.needs_select) {
          txnId = data.txnId;
          window.pendingToken = data.t_token;
          renderAccountPicker(data.accounts);
          goToStep('2b');
          return;
        }

        showResult(true, data);
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check-circle me-1"></i> Verify OTP';
        showError('error2', 'Network communication error: ' + err.message);
      });
    });

    // Account Picker for multiple ABHA results
    function renderAccountPicker(accounts) {
      const list = document.getElementById('accountList');
      list.innerHTML = '';

      if (!accounts || accounts.length === 0) {
        list.innerHTML = '<div class="text-muted text-center py-3">No profiles found.</div>';
        return;
      }

      accounts.forEach(acc => {
        const card = document.createElement('div');
        card.className = 'account-select-card';
        const numFormatted = (acc.ABHANumber || '').replace(/(\d{2})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4') || acc.ABHANumber || '';
        card.innerHTML = `
          <div>
            <div class="fw-bold text-dark" style="font-size:.92rem;">${escapeHtml(acc.name || 'Unknown Patient')}</div>
            <div style="font-family:monospace;font-weight:700;color:var(--primary);font-size:.88rem;">${escapeHtml(numFormatted)}</div>
            ${acc.preferredAbhaAddress ? `<div style="font-size:.76rem;color:var(--gray-500);">${escapeHtml(acc.preferredAbhaAddress)}</div>` : ''}
          </div>
          <div>
            <button class="btn btn-sm btn-primary-custom" onclick="selectAccount('${acc.ABHANumber}', this)">
              <i class="fa fa-check me-1"></i> Select &amp; Link
            </button>
          </div>
        `;
        list.appendChild(card);
      });
    }

    function selectAccount(abhaNumber, btn) {
      hideError('error2b');
      const card = btn.closest('.account-select-card');
      card.style.opacity = '0.6';
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

      fetch(BASE + 'doctor/api/abdm-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'select_user',
          txnId: txnId,
          t_token: window.pendingToken,
          abha_number: abhaNumber,
          _csrf: CSRF_TOKEN
        })
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showError('error2b', data.error || 'Failed to select account');
          card.style.opacity = '1';
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check me-1"></i> Select &amp; Link';
          return;
        }
        showResult(true, data);
      })
      .catch(err => {
        showError('error2b', 'Network error: ' + err.message);
        card.style.opacity = '1';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check me-1"></i> Select &amp; Link';
      });
    }

    // Render Result: Official ABDM Health Card & Doctor Actions Hub
    function showResult(success, data) {
      goToStep(3);

      const content = document.getElementById('resultContent');
      const badge = document.getElementById('statusBadge');

      if (success) {
        badge.className = 'step-badge completed';
        badge.innerHTML = '<i class="fa fa-check-circle"></i> Verified';

        const p = data.profile || {};
        let patientName = p.name || '';
        if (!patientName && (p.firstName || p.lastName)) {
          patientName = `${p.firstName || ''} ${p.lastName || ''}`.trim();
        }
        if (!patientName) patientName = 'Verified Patient';

        let rawNum = data.abha_number || p.ABHANumber || p.abhaNumber || '';
        let abhaNumber = rawNum.replace(/\D/g, '');
        if (abhaNumber.length === 14) {
          abhaNumber = abhaNumber.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4');
        } else {
          abhaNumber = rawNum;
        }

        const abhaAddress = p.preferredAbhaAddress || p.phrAddress || '';
        const gender = p.gender === 'M' ? 'Male' : (p.gender === 'F' ? 'Female' : (p.gender || 'Not Specified'));
        const dob = p.dob || p.yearOfBirth || (p.dayOfBirth && p.monthOfBirth && p.yearOfBirth ? `${p.dayOfBirth}/${p.monthOfBirth}/${p.yearOfBirth}` : 'N/A');
        const photo = p.profilePhoto || p.photo || '';
        const mobile = p.mobile ? p.mobile.replace(/(\d{2})\d{4}(\d{4})/, '$1****$2') : '';

        content.innerHTML = `
          <div class="mb-3 text-center">
            <span class="badge bg-success-subtle text-success py-2 px-3 rounded-pill fw-bold" style="font-size:.85rem;">
              <i class="fa fa-circle-check me-1"></i> ABHA Record Successfully Verified &amp; Linked
            </span>
          </div>

          <!-- Official ABDM Digital Health Card -->
          <div class="official-abha-card">
            <div class="abha-card-header">
              <div>
                <div class="abha-gov-badge">National Health Authority &bull; Govt of India</div>
                <h6 class="abha-title"><i class="fa fa-heart-pulse me-1"></i> Ayushman Bharat Health Account</h6>
              </div>
              <span class="abha-verified-seal">
                <i class="fa fa-shield-check"></i> ABDM M1
              </span>
            </div>
            <div class="abha-card-body">
              <i class="fa fa-id-card abha-watermark"></i>
              <div class="abha-person-row">
                <div class="abha-avatar-box">
                  ${photo ? `<img src="data:image/jpeg;base64,${photo}" alt="Patient Photo">` : `<i class="fa fa-user"></i>`}
                </div>
                <div>
                  <div class="abha-patient-name">${escapeHtml(patientName)}</div>
                  <div class="abha-demographics">
                    <span>${escapeHtml(gender)}</span> &bull; <span>DOB/YOB: ${escapeHtml(dob)}</span>
                    ${mobile ? ` &bull; <span>Ph: ${escapeHtml(mobile)}</span>` : ''}
                  </div>
                </div>
              </div>

              <div class="abha-num-display">
                <div>
                  <div style="font-size:.62rem;text-transform:uppercase;color:var(--gray-500);font-weight:700;letter-spacing:.6px;">ABHA Number</div>
                  <div class="abha-num-val" id="resAbhaNum">${escapeHtml(abhaNumber)}</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="copyAbha('${escapeHtml(abhaNumber)}', this)" title="Copy ABHA">
                  <i class="fa fa-copy"></i>
                </button>
              </div>

              ${abhaAddress ? `
                <div class="abha-addr-display">
                  <span style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500);display:block;font-weight:700;">ABHA Address</span>
                  <i class="fa fa-at text-primary me-1"></i>${escapeHtml(abhaAddress)}
                </div>
              ` : ''}
            </div>
          </div>

          <!-- Doctor Action Hub -->
          <div class="mt-4 pt-3 border-top">
            <div class="fw-bold mb-3 text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.8px;">
              Next Clinical Actions for this Patient:
            </div>
            <div class="d-flex flex-wrap justify-content-center gap-2">
              <a href="${BASE}doctor/patient-profile.php?id=${data.patient_id || ''}" class="btn btn-primary-custom">
                <i class="fa fa-user-doctor me-1"></i> Patient Health Record
              </a>
              <a href="${BASE}doctor/patient-documents.php?patient_id=${data.patient_id || ''}" class="btn btn-outline-custom">
                <i class="fa fa-folder-open me-1"></i> Diagnostic &amp; Lab Reports
              </a>
              <a href="${BASE}doctor/patient-form.php?patient_id=${data.patient_id || ''}" class="btn btn-outline-custom">
                <i class="fa fa-stethoscope me-1"></i> Start Consultation
              </a>
              <a href="${BASE}doctor/my-patients.php" class="btn btn-outline-custom">
                <i class="fa fa-users me-1"></i> Patient Directory
              </a>
            </div>
            <div class="mt-3">
              <button type="button" class="btn btn-link text-muted text-decoration-none" onclick="location.reload()" style="font-size:.8rem;">
                <i class="fa fa-rotate me-1"></i> Verify Another Patient
              </button>
            </div>
          </div>
        `;

        // Mark all indicators done
        [1, 2, 3].forEach(s => {
          const ind = stepIndicators[s];
          if (ind) ind.className = 'step-item done';
        });
      } else {
        badge.className = 'step-badge failed';
        badge.innerHTML = '<i class="fa fa-times-circle"></i> Failed';

        content.innerHTML = `
          <div class="my-4 text-center">
            <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width:70px;height:70px;font-size:2rem;">
              <i class="fa fa-triangle-exclamation"></i>
            </div>
            <h5 class="fw-bold text-dark">ABHA Verification Failed</h5>
            <p class="text-muted" style="font-size:.85rem;max-width:400px;margin:0 auto 20px;">
              ${escapeHtml(data.error || 'An unexpected error occurred during ABDM verification. Please check the credentials and try again.')}
            </p>
            <button type="button" class="btn-outline-custom" onclick="goToStep(1)">
              <i class="fa fa-arrow-left me-1"></i> Try Again
            </button>
          </div>
        `;
      }
    }

    function copyAbha(val, btn) {
      navigator.clipboard.writeText(val).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check text-success"></i> Copied';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
      });
    }

    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Auto-fill from URL param ?abha=
    (function () {
      const pre = new URLSearchParams(location.search).get('abha');
      const digits = (pre || '').replace(/\D/g, '').slice(0, 14);
      if (digits.length === 14) {
        const numberTab = [...document.querySelectorAll('.method-btn')]
          .find(b => /setMethod\('number'/.test(b.getAttribute('onclick') || ''));
        if (numberTab) {
          setMethod('number', numberTab);
          const input = document.getElementById('mainInput');
          input.value = digits.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4');
          input.focus();
          return;
        }
      }
      document.querySelector('.method-btn.active').click();
    })();
  </script>
</body>
</html>