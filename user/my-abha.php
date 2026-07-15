<?php
session_start();
include_once "../config/connect.php";
include_once "../config/abdm.php";
include_once "../util/function.php";
include_once "../lib/Security.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: " . BASE_URL . "login.php");
  exit();
}

$contact = contact_us();
$user_id = $_SESSION['user_id'];
$success = $error = '';

/* ─── Fetch user ─────────────────────────────────────────────────── */
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* Pending request */
$pend = $conn->prepare("SELECT * FROM user_abha_requests WHERE user_id=? AND status='Pending' ORDER BY requested_at DESC LIMIT 1");
$pend->bind_param('i', $user_id);
$pend->execute();
$pending_req = $pend->get_result()->fetch_assoc();

/* Request history */
$hist = $conn->prepare("SELECT * FROM user_abha_requests WHERE user_id=? ORDER BY requested_at DESC LIMIT 8");
$hist->bind_param('i', $user_id);
$hist->execute();
$history = $hist->get_result();

/* Medical records count */
$rpt = $conn->prepare("SELECT COUNT(*) as c FROM medical_reports WHERE user_id=?");
$rpt->bind_param('i', $user_id);
$rpt->execute();
$reports_count = $rpt->get_result()->fetch_assoc()['c'];

/* ─── POST handlers ──────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'request_link') {
    if ($user['abha_linked']) {
      $error = "Your ABHA is already linked.";
    } elseif ($pending_req) {
      $error = "You already have a pending request. Wait for admin to review.";
    } else {
      $abha_id   = trim($_POST['abha_id'] ?? '');
      $abha_addr = trim($_POST['abha_address'] ?? '');
      $user_consent = trim($_POST['user_consent']);
      $raw = preg_replace('/\D/', '', $abha_id);
      if (strlen($raw) !== 14) {
        $error = "Invalid ABHA number — must be 14 digits.";
      } else {
        $fmt = substr($raw, 0, 2) . '-' . substr($raw, 2, 4) . '-' . substr($raw, 6, 4) . '-' . substr($raw, 10, 4);
        if ($abha_addr && strpos($abha_addr, '@') === false) $abha_addr .= '@abdm';
        $ins = $conn->prepare("INSERT INTO user_abha_requests (user_id, abha_id, abha_address, status) VALUES (?,?,?,'Pending')");
        $ins->bind_param('iss', $user_id, $fmt, $abha_addr);
        if ($ins->execute()) {
          $success = "ABHA link request submitted! Our admin team will verify and link it within 24 hours.";
          $pend->execute();
          $pending_req = $pend->get_result()->fetch_assoc();
          $stmt->execute();
          $user = $stmt->get_result()->fetch_assoc();
        } else $error = "Could not submit request. Please try again.";
      }
    }
  }

  if ($action === 'cancel_request' && $pending_req) {
    $del = $conn->prepare("DELETE FROM user_abha_requests WHERE id=? AND user_id=? AND status='Pending'");
    $del->bind_param('ii', $pending_req['id'], $user_id);
    if ($del->execute()) {
      $success = "Request cancelled.";
      $pending_req = null;
    }
  }
}

$age = '';
if (!empty($user['dob']) && $user['dob'] !== '0000-00-00') {
  $age = date_diff(date_create($user['dob']), date_create())->y . ' yrs';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My ABHA Health ID | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
  <style>
    :root {
      --ab: #00875a;
      --primary: #0C74C5;
    }

    .abha-card-big {
      background: #00875a;
      border-radius: 20px;
      color: #fff;
      padding: 28px 26px;
      position: relative;
      overflow: hidden;
      margin-bottom: 22px;
    }

    .abha-card-big::before {
      content: '';
      position: absolute;
      top: -40px;
      right: -40px;
      width: 200px;
      height: 200px;
      background: rgba(255, 255, 255, .07);
      border-radius: 50%;
    }

    .abha-card-big::after {
      content: '';
      position: absolute;
      bottom: -60px;
      left: -30px;
      width: 240px;
      height: 240px;
      background: rgba(255, 255, 255, .04);
      border-radius: 50%;
    }

    .abha-num {
      font-family: monospace;
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: .12em;
      margin: 8px 0 4px;
    }

    .abha-lbl {
      font-size: .62rem;
      opacity: .7;
      text-transform: uppercase;
      letter-spacing: .1em;
    }

    .abha-addr {
      font-size: .85rem;
      opacity: .82;
    }

    .abha-ver {
      background: rgba(255, 255, 255, .2);
      border: 1px solid rgba(255, 255, 255, .3);
      border-radius: 20px;
      padding: 3px 12px;
      font-size: .72rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .empty-card {
      background: #f0fdf4;
      border: 2px dashed #86efac;
      border-radius: 20px;
      padding: 32px 24px;
      text-align: center;
      margin-bottom: 22px;
    }

    .pending-card {
      background: #fffbeb;
      border: 1.5px solid #fde68a;
      border-radius: 16px;
      padding: 18px 20px;
      margin-bottom: 16px;
    }

    .info-box {
      background: #fff;
      border-radius: 14px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
      margin-bottom: 16px;
    }

    .info-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 7px 0;
      border-bottom: 1px solid #f3f4f6;
    }

    .info-row:last-child {
      border: none;
    }

    .info-lbl {
      font-size: .76rem;
      color: #6b7280;
    }

    .info-val {
      font-size: .83rem;
      font-weight: 600;
      color: #111827;
      text-align: right;
    }

    .spill-g {
      background: #d1fae5;
      color: #065f46;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: .71rem;
      font-weight: 700;
    }

    .spill-y {
      background: #fef3c7;
      color: #92400e;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: .71rem;
      font-weight: 700;
    }

    .spill-b {
      background: #dbeafe;
      color: #1e40af;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: .71rem;
      font-weight: 700;
    }

    .req-form {
      background: #fff;
      border-radius: 14px;
      padding: 22px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
      margin-bottom: 16px;
      border-top: 3px solid #00875a;
    }

    .abha-mini {
      background: #00875a;
      border-radius: 10px;
      color: #fff;
      padding: 12px 16px;
      margin-bottom: 12px;
    }

    .abha-mini .mn {
      font-family: monospace;
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: .08em;
    }

    /* OTP Wizard */
    .wizard-tab-btns {
      display: flex;
      gap: 6px;
      margin-bottom: 16px;
    }

    .wizard-tab-btns button {
      flex: 1;
      padding: 9px 6px;
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      font-size: .8rem;
      font-weight: 600;
      color: #374151;
      cursor: pointer;
      transition: .15s;
    }

    .wizard-tab-btns button.active {
      background: #00875a;
      color: #fff;
      border-color: #00875a;
    }

    .otp-input-big {
      letter-spacing: .35em;
      font-size: 1.2rem;
      font-weight: 700;
      text-align: center;
      font-family: monospace;
    }

    .step-indicator {
      font-size: .72rem;
      color: #6b7280;
      margin-bottom: 12px;
    }

    .step-indicator .cur {
      color: #00875a;
      font-weight: 700;
    }

    .abha-success-card {
      background: #00875a;
      border-radius: 16px;
      color: #fff;
      padding: 22px;
      text-align: center;
      margin-bottom: 14px;
    }

    .abha-success-card .num {
      font-family: monospace;
      font-size: 1.3rem;
      font-weight: 700;
      letter-spacing: .1em;
      margin: 8px 0 4px;
    }

    .hist-item {
      padding: 10px 14px;
      border-radius: 10px;
      background: #f9fafb;
      margin-bottom: 8px;
      border-left: 3px solid #e5e7eb;
    }

    .hist-item.Approved {
      border-color: #16a34a;
      background: #f0fdf4;
    }

    .hist-item.Rejected {
      border-color: #dc2626;
      background: #fff5f5;
    }

    .hist-item.Pending {
      border-color: #d97706;
      background: #fffbeb;
    }

    .benefit-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 12px;
    }

    .benefit-item .bi {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .9rem;
      flex-shrink: 0;
    }
  </style>
</head>

<body>
  <?php $sidebar_active = 'abha'; include("sidebar.php"); ?>
  <main class="patient-content">

          <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
              <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
              <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <div class="row">
            <div class="col-lg-7">

              <?php if ($user['abha_linked'] && $user['abha_id']): ?>
                <!-- ═══ LINKED ═══ -->
                <div class="abha-card-big">
                  <div style="position:relative;z-index:1;">
                    <div class="abha-lbl">Ayushman Bharat Health Account</div>
                    <div class="abha-num"><?= htmlspecialchars($user['abha_id']) ?></div>
                    <?php if ($user['abha_address']): ?>
                      <div class="abha-addr"><i class="fas fa-at me-1" style="font-size:.7rem;"></i><?= htmlspecialchars($user['abha_address']) ?></div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
                      <div>
                        <div style="font-size:.82rem;font-weight:600;"><?= htmlspecialchars($user['name']) ?></div>
                        <?php if ($age): ?><div style="font-size:.7rem;opacity:.75;"><?= $age ?> · <?= $user['gender'] ?: '' ?> · <?= $user['blood_group'] ?: '' ?></div><?php endif; ?>
                      </div>
                      <?php if ($user['abha_verified']): ?>
                        <span class="abha-ver"><i class="fas fa-shield-alt"></i>Verified by ABDM</span>
                      <?php else: ?>
                        <span class="abha-ver" style="background:rgba(255,165,0,.25);border-color:rgba(255,165,0,.4);"><i class="fas fa-link"></i>Linked</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($user['abha_linked_at']): ?>
                      <div style="font-size:.63rem;opacity:.55;margin-top:10px;"><i class="fas fa-calendar me-1"></i>Linked on <?= date('d M Y', strtotime($user['abha_linked_at'])) ?></div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="info-box">
                  <h6 class="fw-bold mb-3" style="font-size:.85rem;color:#374151;"><i class="fas fa-id-card me-2 text-success"></i>ABHA Details</h6>
                  <div class="info-row"><span class="info-lbl">ABHA Number</span><span class="info-val" style="font-family:monospace;color:#00875a;"><?= htmlspecialchars($user['abha_id']) ?></span></div>
                  <div class="info-row"><span class="info-lbl">ABHA Address</span><span class="info-val"><?= $user['abha_address'] ?: '—' ?></span></div>
                  <div class="info-row">
                    <span class="info-lbl">Status</span>
                    <?php if ($user['abha_verified']): ?><span class="spill-b"><i class="fas fa-shield-alt me-1"></i>Verified</span>
                    <?php else: ?><span class="spill-g"><i class="fas fa-link me-1"></i>Linked</span><?php endif; ?>
                  </div>
                  <?php if ($user['abha_linked_at']): ?>
                    <div class="info-row"><span class="info-lbl">Linked On</span><span class="info-val"><?= date('d M Y', strtotime($user['abha_linked_at'])) ?></span></div>
                  <?php endif; ?>
                </div>

                <!-- ABHA Card Download -->
                <div class="info-box" id="abhaCardBox">
                  <h6 class="fw-bold mb-3" style="font-size:.85rem;color:#374151;"><i class="fas fa-download me-2 text-success"></i>Download ABHA Card</h6>
                  <div id="cardAuthSection">
                    <p style="font-size:.81rem;color:#6b7280;margin-bottom:12px;">
                      <i class="fas fa-shield-alt me-1 text-success"></i>
                      Verify your identity to download your official ABHA card. An OTP will be sent to your Aadhaar-linked mobile.
                    </p>
                    <div id="cardOtpRow" style="display:none;" class="mb-3">
                      <label class="form-label fw-semibold" style="font-size:.83rem;">Enter 6-digit OTP</label>
                      <input type="text" class="form-control otp-input-big" id="card_otp_in" placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                    </div>
                    <div id="cardAlertBox" class="alert mb-2" style="display:none;"></div>
                    <div class="d-flex gap-2">
                      <button class="btn fw-semibold" style="background:#00875a;color:#fff;font-size:.83rem;" id="btnCardSendOtp" onclick="cardSendOtp()"><i class="fas fa-mobile-alt me-1"></i>Send OTP</button>
                      <button class="btn fw-semibold d-none" style="background:#0C74C5;color:#fff;font-size:.83rem;" id="btnCardVerifyOtp" onclick="cardVerifyOtp()"><i class="fas fa-check me-1"></i>Verify OTP</button>
                    </div>
                  </div>
                  <div id="cardDownloadSection" style="display:none;">
                    <p style="font-size:.8rem;color:#16a34a;margin-bottom:12px;"><i class="fas fa-check-circle me-1"></i>Authenticated — download your ABHA card below.</p>
                    <div class="d-flex gap-2 flex-wrap">
                      <button class="btn fw-semibold" style="background:#00875a;color:#fff;font-size:.83rem;" onclick="downloadAbhaCard('png')"><i class="fas fa-image me-1"></i>Download PNG</button>
                      <button class="btn btn-outline-success fw-semibold" style="font-size:.83rem;" onclick="downloadAbhaCard('pdf')"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
                      <button class="btn btn-outline-secondary fw-semibold" style="font-size:.83rem;" onclick="resetCardAuth()"><i class="fas fa-lock me-1"></i>Lock</button>
                    </div>
                    <div id="cardPreview" style="display:none;margin-top:14px;"></div>
                  </div>
                </div>

              <?php else: ?>
                <!-- ═══ NOT LINKED ═══ -->
                <div class="empty-card">
                  <div style="width:64px;height:64px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.8rem;color:#00875a;">
                    <i class="fas fa-id-card"></i>
                  </div>
                  <h5 style="color:#374151;font-size:1rem;margin-bottom:6px;">ABHA Not Linked</h5>
                  <p style="font-size:.8rem;color:#6b7280;margin:0;">Link or create your Ayushman Bharat Health Account (ABHA) with OTP verification — instant, no admin wait time.</p>
                </div>

                <?php if (ABDM_CONFIGURED): ?>
                  <!-- ═══ ABDM LIVE OTP WIZARD ═══ -->
                  <div class="req-form" id="abhaWizard">

                    <!-- Alert placeholder -->
                    <div id="wAlertBox" style="display:none;" class="alert mb-3"></div>

                    <!-- Tab buttons -->
                    <div class="wizard-tab-btns mb-3">
                      <button class="active" id="btnTabLink" onclick="switchTab('link')">
                        <i class="fas fa-link me-1"></i>Link Existing ABHA
                      </button>
                      <button id="btnTabCreate" onclick="switchTab('create')">
                        <i class="fas fa-plus-circle me-1"></i>Create New ABHA
                      </button>
                    </div>

                    <!-- ── TAB: Link Existing ── -->
                    <div id="tabLink">

                      <!-- Step L1: Enter ABHA number -->
                      <div id="stepL1">
                        <p class="step-indicator"><span class="cur">Step 1</span> of 2 — Enter your ABHA ID</p>
                        <div class="abha-mini">
                          <div style="font-size:.57rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">ABHA Preview</div>
                          <div class="mn" id="prev_num">XX-XXXX-XXXX-XXXX</div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">ABHA Number <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" id="link_abha_in"
                            placeholder="XX-XXXX-XXXX-XXXX" maxlength="19" oninput="fmtAbha(this,'prev_num')">
                          <small class="text-muted">Your 14-digit Ayushman Bharat Health ID</small>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Auth Method</label>
                          <select class="form-select" id="link_auth_method" style="font-size:.85rem;">
                            <option value="MOBILE_OTP">Mobile OTP (recommended)</option>
                            <option value="AADHAAR_OTP">Aadhaar OTP</option>
                          </select>
                        </div>
                        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="initLink()">
                          <i class="fas fa-arrow-right me-2"></i>Continue — Send OTP
                        </button>
                      </div>

                      <!-- Step L2: Enter OTP -->
                      <div id="stepL2" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 2</span> of 2 — Verify OTP</p>
                        <p id="linkOtpMsg" style="font-size:.82rem;color:#374151;margin-bottom:14px;"></p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Enter 6-digit OTP</label>
                          <input type="text" class="form-control otp-input-big" id="link_otp_in"
                            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                        </div>
                        <div class="d-flex gap-2">
                          <button class="btn btn-outline-secondary" style="font-size:.82rem;" onclick="resetStep('link')">
                            <i class="fas fa-arrow-left me-1"></i>Back
                          </button>
                          <button class="btn flex-fill fw-semibold" style="background:#00875a;color:#fff;" onclick="confirmLinkOtp()">
                            <i class="fas fa-check me-1"></i>Verify & Link ABHA
                          </button>
                        </div>
                      </div>

                    </div><!-- /tabLink -->

                    <!-- ── TAB: Create New ABHA ── -->
                    <div id="tabCreate" style="display:none;">

                      <!-- Step C1: Choose method + forms -->
                      <div id="stepC1">
                        <p class="step-indicator"><span class="cur">Step 1</span> of 3 — Choose verification method</p>

                        <!-- Method selector buttons -->
                        <div class="d-flex gap-2 mb-3">
                          <button type="button" id="btnMethodAadhaar" onclick="switchCreateMethod('aadhaar')"
                            style="flex:1;padding:14px 8px;border:2px solid #00875a;border-radius:12px;background:#f0fdf4;cursor:pointer;text-align:center;transition:.15s;">
                            <div style="font-size:1.4rem;margin-bottom:4px;"><i class="fas fa-id-card"></i></div>
                            <div style="font-size:.82rem;font-weight:700;color:#065f46;">Aadhaar Card</div>
                            <div style="font-size:.68rem;color:#6b7280;margin-top:2px;">OTP to Aadhaar-linked mobile</div>
                          </button>
                          <button type="button" id="btnMethodDL" onclick="switchCreateMethod('dl')"
                            style="flex:1;padding:14px 8px;border:2px solid #e5e7eb;border-radius:12px;background:#f9fafb;cursor:pointer;text-align:center;transition:.15s;">
                            <div style="font-size:1.4rem;margin-bottom:4px;"><i class="fas fa-car-side"></i></div>
                            <div style="font-size:.82rem;font-weight:700;color:#374151;">Driving Licence</div>
                            <div style="font-size:.68rem;color:#6b7280;margin-top:2px;">OTP to your mobile number</div>
                          </button>
                        </div>

                        <!-- Aadhaar form -->
                        <div id="createFormAadhaar">
                          <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;font-size:.78rem;color:#065f46;">
                            <i class="fas fa-shield-alt me-2"></i>Your Aadhaar number is RSA-encrypted before leaving your device. It is never stored.
                          </div>
                          <!-- Aadhaar number with eye toggle -->
                          <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Aadhaar Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                              <input type="password" class="form-control" id="create_aadhaar"
                                placeholder="XXXX XXXX XXXX" maxlength="14" inputmode="numeric"
                                oninput="this.value=this.value.replace(/\D/g,'').substring(0,12).replace(/(.{4})/g,'$1 ').trim()"
                                autocomplete="off">
                              <button type="button" class="input-group-text bg-white border-start-0" onclick="toggleEye('create_aadhaar','eyeA')" tabindex="-1">
                                <i class="fas fa-eye" id="eyeA" style="color:#6b7280;font-size:.85rem;"></i>
                              </button>
                            </div>
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Please ensure mobile number is linked with Aadhaar for OTP.</small>
                          </div>
                          <!-- Terms & Conditions -->
                          <div class="mb-3 p-3" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;max-height:110px;overflow-y:auto;font-size:.75rem;color:#374151;line-height:1.6;">
                            <strong>Terms and Conditions</strong><br>
                            I hereby declare that I am voluntarily sharing my Aadhaar number and demographic information issued by UIDAI, with National Health Authority (NHA) for the sole purpose of creation of ABHA number. I understand that my ABHA number can be used and shared for purposes as may be notified by ABDM from time to time including provision of healthcare services. Further, I am aware that my personal identifiable information (Name, Address, Age, Date of Birth, Gender and Photograph) may be made available to the treating healthcare professional.
                          </div>
                          <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="aadhaar_consent" value="1">
                            <label class="form-check-label fw-semibold" for="aadhaar_consent" style="font-size:.82rem;">
                              I agree to the above Terms and Conditions
                            </label>
                          </div>
                          <!-- Captcha -->
                          <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Captcha <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                              <div id="aadhaar_captcha_q" style="font-size:1rem;font-weight:700;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:7px 14px;letter-spacing:.08em;font-family:monospace;min-width:80px;text-align:center;"></div>
                              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshCaptcha('aadhaar')" title="Refresh"><i class="fas fa-sync-alt"></i></button>
                              <input type="text" class="form-control" id="aadhaar_captcha_ans" placeholder="Enter answer" maxlength="4" inputmode="numeric" style="max-width:120px;">
                            </div>
                          </div>
                          <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="genAadhaarOtp()">
                            <i class="fas fa-mobile-alt me-2"></i>Send OTP to Aadhaar Mobile
                          </button>
                        </div>

                        <!-- Driving Licence form -->
                        <div id="createFormDL" style="display:none;">
                          <div class="alert" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:.78rem;color:#1e40af;">
                            <i class="fas fa-info-circle me-2"></i>Enter your Driving Licence details. ABDM will verify and send an OTP to your mobile.
                          </div>
                          <!-- DL number with eye toggle -->
                          <div class="mb-2">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Driving Licence Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                              <input type="password" class="form-control" id="dl_number" placeholder="e.g. MH0120200012345"
                                autocomplete="off" oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')">
                              <button type="button" class="input-group-text bg-white border-start-0" onclick="toggleEye('dl_number','eyeDL')" tabindex="-1">
                                <i class="fas fa-eye" id="eyeDL" style="color:#6b7280;font-size:.85rem;"></i>
                              </button>
                            </div>
                            <small class="text-muted">State code + RTO code + Year + Number (no spaces)</small>
                          </div>
                          <div class="row g-2 mb-2">
                            <div class="col-6">
                              <label class="form-label fw-semibold" style="font-size:.84rem;">Date of Birth <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" id="dl_dob" style="font-size:.85rem;">
                            </div>
                            <div class="col-6">
                              <label class="form-label fw-semibold" style="font-size:.84rem;">Gender <span class="text-danger">*</span></label>
                              <select class="form-select" id="dl_gender" style="font-size:.85rem;">
                                <option value="">Select</option>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                                <option value="O">Other</option>
                              </select>
                            </div>
                          </div>
                          <div class="mb-2">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                              <span class="input-group-text">+91</span>
                              <input type="text" class="form-control" id="dl_mobile"
                                placeholder="10-digit mobile" maxlength="10" inputmode="numeric">
                            </div>
                            <small class="text-muted">OTP will be sent to this number</small>
                          </div>
                          <!-- Terms & Conditions -->
                          <div class="mb-3 p-3 mt-2" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;max-height:110px;overflow-y:auto;font-size:.75rem;color:#374151;line-height:1.6;">
                            <strong>Terms and Conditions</strong><br>
                            I hereby declare that I am voluntarily sharing my Driving Licence information for the purpose of creating my ABHA (Ayushman Bharat Health Account) number. I consent to the National Health Authority (NHA) using this information solely to establish and maintain my digital health identity under the Ayushman Bharat Digital Mission (ABDM). I understand that my health data will be shared only with my consent as per ABDM guidelines.
                          </div>
                          <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="dl_consent" value="1">
                            <label class="form-check-label fw-semibold" for="dl_consent" style="font-size:.82rem;">
                              I agree to the above Terms and Conditions
                            </label>
                          </div>
                          <!-- Captcha -->
                          <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Captcha <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                              <div id="dl_captcha_q" style="font-size:1rem;font-weight:700;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:7px 14px;letter-spacing:.08em;font-family:monospace;min-width:80px;text-align:center;"></div>
                              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshCaptcha('dl')" title="Refresh"><i class="fas fa-sync-alt"></i></button>
                              <input type="text" class="form-control" id="dl_captcha_ans" placeholder="Enter answer" maxlength="4" inputmode="numeric" style="max-width:120px;">
                            </div>
                          </div>
                          <button class="btn w-100 fw-semibold" style="background:#1d4ed8;color:#fff;" onclick="genDlOtp()">
                            <i class="fas fa-mobile-alt me-2"></i>Verify DL &amp; Send OTP
                          </button>
                        </div>
                      </div>

                      <!-- Step DL2: Verify DL OTP -->
                      <div id="stepDL2" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 2</span> of 3 — Verify OTP</p>
                        <p id="dlOtpMsg" style="font-size:.82rem;color:#374151;margin-bottom:14px;"></p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Enter 6-digit OTP</label>
                          <input type="text" class="form-control otp-input-big" id="dl_otp_in"
                            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                        </div>
                        <div class="d-flex gap-2">
                          <button class="btn btn-outline-secondary" style="font-size:.82rem;" onclick="resetStep('create')">
                            <i class="fas fa-arrow-left me-1"></i>Back
                          </button>
                          <button class="btn flex-fill fw-semibold" style="background:#1d4ed8;color:#fff;" onclick="verifyDlOtp()">
                            <i class="fas fa-check me-1"></i>Verify OTP
                          </button>
                        </div>
                      </div>

                      <!-- Step DL3: Choose ABHA address & create -->
                      <div id="stepDL3" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 3</span> of 3 — Create your ABHA</p>
                        <div class="abha-mini mb-3">
                          <div style="font-size:.57rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Profile from Driving Licence</div>
                          <div style="font-weight:700;font-size:.95rem;" id="dl_profile_name">—</div>
                          <div style="font-size:.75rem;opacity:.8;" id="dl_profile_info">—</div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">ABHA Address <small class="text-muted fw-normal">(optional)</small></label>
                          <div class="input-group">
                            <input type="text" class="form-control" id="dl_abha_addr"
                              placeholder="yourname" oninput="fmtAddr(this,'prev_addr_dl')">
                            <span class="input-group-text">@abdm</span>
                          </div>
                          <small class="text-muted" id="prev_addr_dl" style="font-family:monospace;color:#00875a;">address@abdm</small>
                        </div>
                        <button class="btn w-100 fw-semibold" style="background:#1d4ed8;color:#fff;" onclick="createAbhaDL()">
                          <i class="fas fa-id-card me-2"></i>Create My ABHA Health ID
                        </button>
                      </div>

                      <!-- Step C2: Verify Aadhaar OTP -->
                      <div id="stepC2" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 2</span> of 3 — Verify Aadhaar OTP</p>
                        <p id="createOtpMsg" style="font-size:.82rem;color:#374151;margin-bottom:14px;"></p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Enter 6-digit OTP</label>
                          <input type="text" class="form-control otp-input-big" id="create_aadhaar_otp"
                            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                        </div>
                        <div class="d-flex gap-2">
                          <button class="btn btn-outline-secondary" style="font-size:.82rem;" onclick="resetStep('create')">
                            <i class="fas fa-arrow-left me-1"></i>Back
                          </button>
                          <button class="btn flex-fill fw-semibold" style="background:#00875a;color:#fff;" onclick="verifyAadhaarOtp()">
                            <i class="fas fa-check me-1"></i>Verify OTP
                          </button>
                        </div>
                      </div>

                      <!-- Step C2b: Provide mobile (if not linked to Aadhaar) -->
                      <div id="stepC2b" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 2b</span> — Your Aadhaar has no linked mobile</p>
                        <p style="font-size:.8rem;color:#374151;margin-bottom:14px;">Please enter a mobile number to receive your ABHA OTP.</p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Mobile Number</label>
                          <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input type="text" class="form-control" id="create_mobile"
                              placeholder="10-digit mobile" maxlength="10" inputmode="numeric">
                          </div>
                        </div>
                        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="genLinkedMobileOtp()">
                          <i class="fas fa-mobile-alt me-2"></i>Send Mobile OTP
                        </button>
                      </div>

                      <!-- Step C2c: Verify mobile OTP -->
                      <div id="stepC2c" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 2c</span> — Verify mobile OTP</p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Enter 6-digit OTP</label>
                          <input type="text" class="form-control otp-input-big" id="create_mobile_otp"
                            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                        </div>
                        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="verifyLinkedMobileOtp()">
                          <i class="fas fa-check me-1"></i>Verify OTP
                        </button>
                      </div>

                      <!-- Step C3: Choose ABHA address & create -->
                      <div id="stepC3" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 3</span> of 3 — Create your ABHA</p>
                        <div class="abha-mini mb-3">
                          <div style="font-size:.57rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Profile from Aadhaar</div>
                          <div style="font-weight:700;font-size:.95rem;" id="create_profile_name">—</div>
                          <div style="font-size:.75rem;opacity:.8;" id="create_profile_info">—</div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">ABHA Address <small class="text-muted fw-normal">(optional)</small></label>
                          <div class="input-group">
                            <input type="text" class="form-control" id="create_abha_addr"
                              placeholder="yourname" oninput="fmtAddr(this,'prev_addr_c')">
                            <span class="input-group-text">@abdm</span>
                          </div>
                          <small class="text-muted" id="prev_addr_c" style="font-family:monospace;color:#00875a;">address@abdm</small>
                        </div>
                        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="createAbha()">
                          <i class="fas fa-id-card me-2"></i>Create My ABHA Health ID
                        </button>
                      </div>

                    </div><!-- /tabCreate -->

                    <!-- ── SUCCESS (both flows) ── -->
                    <div id="stepSuccess" style="display:none;">
                      <div class="abha-success-card">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <div style="font-size:.7rem;opacity:.8;text-transform:uppercase;letter-spacing:.08em;">ABHA Successfully Linked</div>
                        <div class="num" id="success_abha_num">—</div>
                        <div style="font-size:.8rem;opacity:.85;" id="success_abha_addr"></div>
                        <div style="font-size:.75rem;opacity:.75;margin-top:8px;"><i class="fas fa-shield-alt me-1"></i>Verified by ABDM</div>
                      </div>
                      <button class="btn w-100" style="background:#0C74C5;color:#fff;" onclick="window.location.reload()">
                        <i class="fas fa-sync me-2"></i>View My ABHA Card
                      </button>
                    </div>

                    <!-- Loading spinner -->
                    <div id="wLoader" style="display:none;text-align:center;padding:20px 0;">
                      <div class="spinner-border text-success" role="status" style="width:2rem;height:2rem;"></div>
                      <div style="font-size:.8rem;color:#6b7280;margin-top:8px;" id="wLoaderMsg">Please wait…</div>
                    </div>

                  </div><!-- /abhaWizard -->

                <?php else: ?>
                  <!-- Fallback: manual request (if ABDM not configured) -->
                  <?php if ($pending_req): ?>
                    <div class="pending-card">
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-hourglass-half text-warning fa-lg"></i>
                        <strong style="color:#92400e;">Link Request Pending Review</strong>
                      </div>
                      <div class="info-row"><span class="info-lbl">ABHA Submitted</span><span class="info-val" style="font-family:monospace;color:#00875a;"><?= htmlspecialchars($pending_req['abha_id']) ?></span></div>
                      <?php if ($pending_req['abha_address']): ?>
                        <div class="info-row"><span class="info-lbl">ABHA Address</span><span class="info-val"><?= htmlspecialchars($pending_req['abha_address']) ?></span></div>
                      <?php endif; ?>
                      <div class="info-row"><span class="info-lbl">Submitted On</span><span class="info-val"><?= date('d M Y, h:i A', strtotime($pending_req['requested_at'])) ?></span></div>
                      <p style="font-size:.75rem;color:#6b7280;margin-top:10px;margin-bottom:8px;">Our admin team will verify and link your ABHA within 24 hours.</p>
                      <form method="POST">
                        <input type="hidden" name="action" value="cancel_request">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this request?')">
                          <i class="fas fa-times me-1"></i>Cancel Request
                        </button>
                      </form>
                    </div>
                  <?php else: ?>
                    <div class="req-form">
                      <h6 class="fw-bold mb-1"><i class="fas fa-link me-2 text-success"></i>Link Your ABHA</h6>
                      <p style="font-size:.76rem;color:#6b7280;margin-bottom:16px;">Enter your 14-digit ABHA Health ID. Our team will verify it and link it to your account.</p>
                      <div class="abha-mini">
                        <div style="font-size:.57rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Preview</div>
                        <div class="mn" id="prev_num">XX-XXXX-XXXX-XXXX</div>
                        <div style="font-size:.73rem;opacity:.8;margin-top:3px;" id="prev_addr">address@abdm</div>
                      </div>
                      <form method="POST">
                        <input type="hidden" name="action" value="request_link">
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">ABHA Number <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" name="abha_id" id="abha_in"
                            placeholder="XX-XXXX-XXXX-XXXX" maxlength="19" oninput="fmtAbha(this,'prev_num')" required>
                          <small class="text-muted">14-digit Ayushman Bharat Health ID · <a href="https://healthid.ndhm.gov.in/" target="_blank">Create one free →</a></small>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">ABHA Address <small class="text-muted fw-normal">(optional)</small></label>
                          <div class="input-group">
                            <input type="text" class="form-control" name="abha_address" id="addr_in" placeholder="yourname" oninput="fmtAddr(this,'prev_addr')">
                            <span class="input-group-text">@abdm</span>
                          </div>
                        </div>
                        <button type="submit" class="btn w-100" style="background:#00875a;color:#fff;">
                          <i class="fas fa-paper-plane me-2"></i>Submit Link Request
                        </button>
                      </form>
                    </div>
                  <?php endif; ?>
                <?php endif; /* ABDM_CONFIGURED */ ?>
              <?php endif; /* end abha_linked */ ?>

              <!-- Request History -->
              <?php if ($history->num_rows > 0): ?>
                <div class="info-box">
                  <h6 class="fw-bold mb-3" style="font-size:.85rem;color:#374151;"><i class="fas fa-history me-2 text-muted"></i>Request History</h6>
                  <?php while ($rh = $history->fetch_assoc()): ?>
                    <div class="hist-item <?= $rh['status'] ?>">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <div style="font-family:monospace;font-size:.83rem;font-weight:700;"><?= htmlspecialchars($rh['abha_id'] ?: '—') ?></div>
                          <div style="font-size:.68rem;color:#6b7280;"><?= date('d M Y, h:i A', strtotime($rh['requested_at'])) ?></div>
                        </div>
                        <?php
                        $sc = ['Approved' => 'spill-g', 'Rejected' => 'spill-y', 'Pending' => 'spill-b'][$rh['status']] ?? 'spill-b';
                        $si = ['Approved' => 'check-circle', 'Rejected' => 'times-circle', 'Pending' => 'hourglass-half'][$rh['status']] ?? 'clock';
                        ?>
                        <span class="<?= $sc ?>"><i class="fas fa-<?= $si ?> me-1"></i><?= $rh['status'] ?></span>
                      </div>
                      <?php if ($rh['notes'] && $rh['status'] === 'Rejected'): ?>
                        <div style="font-size:.72rem;color:#dc2626;margin-top:4px;"><i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($rh['notes']) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endwhile; ?>
                </div>
              <?php endif; ?>

            </div><!-- /col-7 -->

            <div class="col-lg-5">
              <!-- My Health snapshot -->
              <div class="info-box">
                <h6 class="fw-bold mb-3" style="font-size:.85rem;color:#374151;"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</h6>
                <div class="info-row"><span class="info-lbl">Name</span><span class="info-val"><?= htmlspecialchars($user['name']) ?></span></div>
                <div class="info-row"><span class="info-lbl">Age / Gender</span><span class="info-val"><?= $age ?> · <?= $user['gender'] ?: '—' ?></span></div>
                <div class="info-row"><span class="info-lbl">Blood Group</span><span class="info-val"><?= $user['blood_group'] ?: '—' ?></span></div>
                <div class="info-row"><span class="info-lbl">ID Type</span><span class="info-val"><?= $user['identification_type'] ?: 'None' ?> <?= $user['identification_number'] ? '· ' . $user['identification_number'] : '' ?></span></div>
                <div class="info-row"><span class="info-lbl">Medical Reports</span><span class="info-val"><?= $reports_count ?></span></div>
                <a href="my-profile.php" class="btn btn-sm btn-outline-primary w-100 mt-2" style="font-size:.78rem;"><i class="fas fa-edit me-1"></i>Edit Profile</a>
              </div>

              <!-- About ABHA -->
              <div class="info-box" style="border-left:4px solid #00875a;">
                <h6 class="fw-bold mb-3" style="font-size:.85rem;color:#374151;"><i class="fas fa-info-circle me-2 text-success"></i>What is ABHA?</h6>
                <?php $benefits = [
                  ['fas fa-id-card', '#d1fae5', '#00875a', '14-digit unique digital health identity for every Indian citizen'],
                  ['fas fa-link', '#dbeafe', '#1e40af', 'Links all your health records from hospitals, clinics & labs'],
                  ['fas fa-shield-alt', '#f3e5f5', '#7c3aed', 'Consent-based, secure sharing of your health data'],
                  ['fas fa-hospital', '#fff3e0', '#d97706', 'Works across all ABDM-integrated healthcare providers'],
                  ['fas fa-heartbeat', '#fff1f2', '#dc2626', 'Free to create via Aadhaar or mobile OTP'],
                ];
                foreach ($benefits as [$ic, $bg, $col, $txt]): ?>
                  <div class="benefit-item">
                    <div class="bi" style="background:<?= $bg ?>;color:<?= $col ?>;"><i class="<?= $ic ?>"></i></div>
                    <p style="font-size:.76rem;color:#4b5563;margin:0;line-height:1.5;"><?= $txt ?></p>
                  </div>
                <?php endforeach; ?>
                <a href="https://healthid.ndhm.gov.in/" target="_blank" class="btn btn-sm w-100 mt-2" style="background:#00875a;color:#fff;font-size:.78rem;">
                  <i class="fas fa-external-link-alt me-2"></i>Create / View ABHA on ABDM Portal
                </a>
              </div>

              <!-- e-Sanjeevani link -->
              <div class="info-box" style="border-left:4px solid #0C74C5;">
                <h6 class="fw-bold mb-2" style="font-size:.85rem;"><i class="fas fa-video me-2 text-primary"></i>Telemedicine Services</h6>
                <p style="font-size:.76rem;color:#6b7280;margin-bottom:10px;">Book a teleconsultation with our doctors or use India's free e-Sanjeevani OPD service.</p>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="<?= BASE_URL ?>book-appointment.php" class="btn btn-sm btn-primary" style="font-size:.75rem;"><i class="fas fa-calendar-plus me-1"></i>Book Appointment</a>
                  <a href="https://esanjeevani.mohfw.gov.in/#/patient/signin" target="_blank" class="btn btn-sm btn-outline-success" style="font-size:.75rem;"><i class="fas fa-stethoscope me-1"></i>e-Sanjeevani</a>
                </div>
              </div>
            </div><!-- /col-5 -->

          </div><!-- /row -->
  </main>
  <?php include("inc/scripts.php") ?>
  <script>
    /* ── Formatters ──────────────────────────────────────────────────── */
    function fmtAbha(el, previewId) {
      let v = el.value.replace(/\D/g, '').substring(0, 14);
      let out = v.length > 0 ? v.substring(0, 2) : '';
      if (v.length > 2) out += '-' + v.substring(2, 6);
      if (v.length > 6) out += '-' + v.substring(6, 10);
      if (v.length > 10) out += '-' + v.substring(10, 14);
      el.value = out;
      if (previewId) {
        const p = document.getElementById(previewId);
        if (p) p.textContent = out || 'XX-XXXX-XXXX-XXXX';
      }
    }

    function fmtAddr(el, previewId) {
      const addr = el.value.replace('@abdm', '').trim();
      if (previewId) {
        const p = document.getElementById(previewId);
        if (p) p.textContent = addr ? addr + '@abdm' : 'address@abdm';
      }
    }

    /* ── Eye toggle ── */
    function toggleEye(inputId, iconId) {
      const inp  = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      if (!inp) return;
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }

    /* ── Math Captcha ── */
    const _captcha = { aadhaar: 0, dl: 0 };

    function refreshCaptcha(which) {
      const a = Math.floor(Math.random() * 10) + 1;
      const b = Math.floor(Math.random() * 10) + 1;
      _captcha[which] = a + b;
      document.getElementById(which + '_captcha_q').textContent = a + ' + ' + b + ' = ?';
      const ans = document.getElementById(which + '_captcha_ans');
      if (ans) ans.value = '';
    }

    function validateCaptcha(which) {
      const ans = parseInt(document.getElementById(which + '_captcha_ans').value.trim(), 10);
      return ans === _captcha[which];
    }

    // Init captchas on load
    refreshCaptcha('aadhaar');
    refreshCaptcha('dl');

    /* ── ABDM Wizard ─────────────────────────────────────────────────── */
    const AJAX_URL    = '<?= BASE_URL ?>ajax/abdm-api.php';
    const _CSRF       = '<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, "UTF-8") ?>';
    const _LINKED_ABHA = '<?= htmlspecialchars($user["abha_id"] ?? "", ENT_QUOTES, "UTF-8") ?>';
    let abdmTxnId = '',
      abdmFlow = '';

    async function abdmPost(action, body = {}) {
      setLoader(true, 'Please wait…');
      try {
        const r = await fetch(AJAX_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action, _csrf: _CSRF, ...body }),
        });
        return await r.json();
      } catch (e) {
        return { success: false, message: 'Network error. Please try again.' };
      } finally {
        setLoader(false);
      }
    }

    function setLoader(show, msg = '') {
      document.getElementById('wLoader').style.display = show ? 'block' : 'none';
      document.getElementById('wLoaderMsg').textContent = msg;
      // hide all step content while loading
      const steps = document.querySelectorAll('#abhaWizard [id^="step"]');
      steps.forEach(s => {
        if (s.id !== 'wLoader') s.style.display = show ? 'none' : '';
      });
      if (!show) {
        // restore active step
        if (abdmFlow === 'link_existing') showLinkStep(window._curLinkStep || 'L1');
        else showCreateStep(window._curCreateStep || 'C1');
      }
    }

    function wAlert(msg, type = 'danger') {
      const b = document.getElementById('wAlertBox');
      b.className = 'alert alert-' + type + ' mb-3';
      b.textContent = msg;
      b.style.display = 'block';
      setTimeout(() => b.style.display = 'none', 6000);
    }

    /* ── Tab switching ── */
    function switchTab(tab) {
      document.getElementById('tabLink').style.display = tab === 'link' ? 'block' : 'none';
      document.getElementById('tabCreate').style.display = tab === 'create' ? 'block' : 'none';
      document.getElementById('btnTabLink').classList.toggle('active', tab === 'link');
      document.getElementById('btnTabCreate').classList.toggle('active', tab === 'create');
      abdmFlow = tab === 'link' ? 'link_existing' : 'create_aadhaar';
    }

    function showLinkStep(step) {
      ['L1', 'L2'].forEach(s => {
        const el = document.getElementById('stepL' + s.substring(1));
        if (el) el.style.display = (step === s) ? 'block' : 'none';
      });
      // also hide stepSuccess
      const ss = document.getElementById('stepSuccess');
      if (ss) ss.style.display = 'none';
      window._curLinkStep = step;
    }

    let createMethod = 'aadhaar';

    function switchCreateMethod(method) {
      createMethod = method;
      document.getElementById('createFormAadhaar').style.display = method === 'aadhaar' ? 'block' : 'none';
      document.getElementById('createFormDL').style.display      = method === 'dl'      ? 'block' : 'none';
      const btnA = document.getElementById('btnMethodAadhaar');
      const btnD = document.getElementById('btnMethodDL');
      btnA.style.border     = method === 'aadhaar' ? '2px solid #00875a' : '2px solid #e5e7eb';
      btnA.style.background = method === 'aadhaar' ? '#f0fdf4' : '#f9fafb';
      btnD.style.border     = method === 'dl' ? '2px solid #1d4ed8' : '2px solid #e5e7eb';
      btnD.style.background = method === 'dl' ? '#eff6ff' : '#f9fafb';
    }

    function showCreateStep(step) {
      ['C1','C2','C2b','C2c','C3','DL2','DL3'].forEach(s => {
        const el = document.getElementById('step' + s);
        if (el) el.style.display = step === s ? 'block' : 'none';
      });
      const ss = document.getElementById('stepSuccess');
      if (ss) ss.style.display = step === 'Success' ? 'block' : 'none';
      if (step === 'C1') switchCreateMethod(createMethod);
      window._curCreateStep = step;
    }

    function resetStep(tab) {
      if (tab === 'link') showLinkStep('L1');
      else showCreateStep('C1');
      document.getElementById('wAlertBox').style.display = 'none';
      abdmTxnId = '';
    }

    /* ── DL flow ── */
    async function genDlOtp() {
      const dlNum  = document.getElementById('dl_number').value.trim();
      const dob    = document.getElementById('dl_dob').value;
      const gender = document.getElementById('dl_gender').value;
      const mobile = document.getElementById('dl_mobile').value.replace(/\D/g,'');
      if (!dlNum)               { wAlert('Enter your Driving Licence number'); return; }
      if (!dob)                 { wAlert('Enter your date of birth'); return; }
      if (!gender)              { wAlert('Select your gender'); return; }
      if (mobile.length !== 10) { wAlert('Enter a valid 10-digit mobile number'); return; }
      if (!document.getElementById('dl_consent').checked) { wAlert('Please agree to the Terms and Conditions to continue.'); return; }
      if (!validateCaptcha('dl')) { wAlert('Captcha answer is incorrect. Please try again.'); refreshCaptcha('dl'); return; }
      const res = await abdmPost('gen_dl_otp', { dl_number: dlNum, dob, gender, mobile });
      if (res.success) {
        abdmTxnId = res.txnId || '';
        document.getElementById('dlOtpMsg').textContent =
          `OTP sent to +91-${mobile.substring(0,2)}XXXXXX${mobile.substring(8)}. Valid for 10 minutes.`;
        showCreateStep('DL2');
      } else { wAlert(res.message); }
    }

    async function verifyDlOtp() {
      const otp = document.getElementById('dl_otp_in').value.trim();
      if (otp.length < 6) { wAlert('Enter 6-digit OTP'); return; }
      const res = await abdmPost('verify_dl_otp', { otp });
      if (res.success) {
        abdmTxnId = res.txnId || '';
        document.getElementById('dl_profile_name').textContent = res.name || '—';
        document.getElementById('dl_profile_info').textContent =
          [res.gender, res.yearOfBirth ? 'b. ' + res.yearOfBirth : ''].filter(Boolean).join(' · ');
        showCreateStep('DL3');
      } else { wAlert(res.message); }
    }

    async function createAbhaDL() {
      const addr = document.getElementById('dl_abha_addr').value.trim();
      const res  = await abdmPost('create_abha_dl', { abha_address: addr });
      if (res.success) {
        document.getElementById('success_abha_num').textContent  = res.abha_id      || '';
        document.getElementById('success_abha_addr').textContent = res.abha_address || '';
        showCreateStep('Success');
      } else { wAlert(res.message); }
    }

    /* ── Link existing ABHA ── */
    async function initLink() {
      const abhaId = document.getElementById('link_abha_in').value.replace(/\D/g, '');
      const authMethod = document.getElementById('link_auth_method').value;
      if (abhaId.length !== 14) {
        wAlert('Enter a valid 14-digit ABHA number');
        return;
      }

      const res = await abdmPost('init_link', {
        abha_id: document.getElementById('link_abha_in').value,
        auth_method: authMethod
      });

      if (res.success) {
        abdmTxnId = res.txnId || '';
        const m = authMethod === 'AADHAAR_OTP' ? 'your Aadhaar-linked mobile' : 'your registered mobile';
        document.getElementById('linkOtpMsg').textContent = `OTP has been sent to ${m}. Valid for 10 minutes.`;
        showLinkStep('L2');
      } else {
        wAlert(res.message);
      }
    }

    async function confirmLinkOtp() {
      const otp = document.getElementById('link_otp_in').value.trim();
      if (otp.length < 6) {
        wAlert('Enter 6-digit OTP');
        return;
      }

      const res = await abdmPost('confirm_link_otp', {
        otp
      });

      if (res.success) {
        document.getElementById('success_abha_num').textContent = res.abha_id || '';
        document.getElementById('success_abha_addr').textContent = res.abha_address || '';
        showLinkStep('Success');
        document.getElementById('stepSuccess').style.display = 'block';
        // Hide L steps
        ['L1', 'L2'].forEach(s => {
          const el = document.getElementById('stepL' + s.substring(1));
          if (el) el.style.display = 'none';
        });
      } else {
        wAlert(res.message);
        setLoader(false);
      }
    }

    /* ── Create ABHA (M1 Aadhaar flow) ── */
    async function genAadhaarOtp() {
      const raw = document.getElementById('create_aadhaar').value.replace(/\D/g, '');
      if (raw.length !== 12) { wAlert('Enter valid 12-digit Aadhaar number'); return; }
      if (!document.getElementById('aadhaar_consent').checked) { wAlert('Please agree to the Terms and Conditions to continue.'); return; }
      if (!validateCaptcha('aadhaar')) { wAlert('Captcha answer is incorrect. Please try again.'); refreshCaptcha('aadhaar'); return; }

      const res = await abdmPost('gen_aadhaar_otp', { aadhaar: raw, consent: 1 });

      if (res.success) {
        abdmTxnId = res.txnId || '';
        const mm = res.maskedMobile || 'your registered mobile';
        document.getElementById('createOtpMsg').textContent = `OTP sent to ${mm}. Valid for 10 minutes.`;
        showCreateStep('C2');
      } else {
        wAlert(res.message);
      }
    }

    async function verifyAadhaarOtp() {
      const otp = document.getElementById('create_aadhaar_otp').value.trim();
      if (otp.length < 6) {
        wAlert('Enter 6-digit OTP');
        return;
      }

      const res = await abdmPost('verify_aadhaar_otp', {
        otp
      });

      if (res.success) {
        abdmTxnId = res.txnId || '';
        if (!res.mobileLinked) {
          showCreateStep('C2b');
        } else {
          // Aadhaar mobile is linked — go to create step
          document.getElementById('create_profile_name').textContent = res.name || '—';
          document.getElementById('create_profile_info').textContent = [res.gender, res.yearOfBirth ? 'b. ' + res.yearOfBirth : ''].filter(Boolean).join(' · ');
          showCreateStep('C3');
        }
      } else {
        wAlert(res.message);
      }
    }

    async function genLinkedMobileOtp() {
      const mobile = document.getElementById('create_mobile').value.replace(/\D/g, '');
      if (mobile.length !== 10) {
        wAlert('Enter valid 10-digit mobile');
        return;
      }

      const res = await abdmPost('gen_linked_mobile_otp', {
        mobile
      });

      if (res.success) {
        abdmTxnId = res.txnId || '';
        showCreateStep('C2c');
      } else {
        wAlert(res.message);
      }
    }

    async function verifyLinkedMobileOtp() {
      const otp = document.getElementById('create_mobile_otp').value.trim();
      if (otp.length < 6) {
        wAlert('Enter 6-digit OTP');
        return;
      }

      const res = await abdmPost('verify_linked_mobile_otp', {
        otp
      });

      if (res.success) {
        abdmTxnId = res.txnId || '';
        showCreateStep('C3');
      } else {
        wAlert(res.message);
      }
    }

    async function createAbha() {
      const addr = document.getElementById('create_abha_addr').value.trim();
      const res = await abdmPost('create_abha', {
        abha_address: addr
      });

      if (res.success) {
        document.getElementById('success_abha_num').textContent = res.abha_id || '';
        document.getElementById('success_abha_addr').textContent = res.abha_address || '';
        // Hide all create steps, show success
        ['C1', 'C2', 'C2b', 'C2c', 'C3'].forEach(s => {
          const el = document.getElementById('step' + s);
          if (el) el.style.display = 'none';
        });
        document.getElementById('stepSuccess').style.display = 'block';
      } else {
        wAlert(res.message);
      }
    }

    /* ════════════════════════════════════════════════════
       ABHA CARD DOWNLOAD — needs user to re-auth with OTP
    ════════════════════════════════════════════════════ */

    let _cardTxnId = '';

    function cardAlert(msg, type = 'danger') {
      const b = document.getElementById('cardAlertBox');
      b.className = 'alert alert-' + type + ' mb-2 py-2';
      b.textContent = msg;
      b.style.display = 'block';
    }

    async function cardSendOtp() {
      if (!_LINKED_ABHA) { cardAlert('No ABHA linked. Please link an ABHA first.'); return; }

      const btn = document.getElementById('btnCardSendOtp');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending…';

      // Use the user's linked ABHA number (known from PHP) to request OTP via abdm mobile
      const res = await abdmPost('init_link', {
        abha_id:     _LINKED_ABHA,
        auth_method: 'MOBILE_OTP'
      });

      btn.disabled = false;
      if (res.success) {
        _cardTxnId = res.txnId || '';
        document.getElementById('cardOtpRow').style.display = 'block';
        document.getElementById('btnCardVerifyOtp').classList.remove('d-none');
        btn.innerHTML = '<i class="fas fa-redo me-1"></i>Resend OTP';
        cardAlert('OTP sent to your Aadhaar-linked mobile number.', 'success');
      } else {
        btn.innerHTML = '<i class="fas fa-mobile-alt me-1"></i>Send OTP';
        cardAlert(res.message || 'Could not send OTP. Please try again.');
      }
    }

    async function cardVerifyOtp() {
      const otp = document.getElementById('card_otp_in').value.trim();
      if (otp.length !== 6) { cardAlert('Enter the 6-digit OTP'); return; }

      const btn = document.getElementById('btnCardVerifyOtp');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying…';

      const res = await abdmPost('confirm_link_otp', { otp });

      if (res.success) {
        document.getElementById('cardAuthSection').style.display    = 'none';
        document.getElementById('cardDownloadSection').style.display = 'block';
        document.getElementById('cardAlertBox').style.display        = 'none';
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Verify OTP';
        cardAlert(res.message || 'Invalid OTP. Please try again.');
      }
    }

    async function downloadAbhaCard(format) {
      const btn = event.currentTarget;
      const orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Downloading…';

      const res = await abdmPost('get_abha_card', { format });

      btn.disabled = false;
      btn.innerHTML = orig;

      if (!res.success) { cardAlert(res.message || 'Download failed. Please try again.'); return; }

      // Convert base64 to blob and trigger download
      const byteArr = Uint8Array.from(atob(res.data), c => c.charCodeAt(0));
      const blob    = new Blob([byteArr], { type: res.mimeType });
      const url     = URL.createObjectURL(blob);
      const a       = document.createElement('a');
      a.href     = url;
      a.download = 'ABHA-Card.' + format;
      a.click();
      URL.revokeObjectURL(url);

      // Show PNG preview inline
      if (format === 'png') {
        const preview = document.getElementById('cardPreview');
        preview.innerHTML = '<img src="data:image/png;base64,' + res.data + '" style="max-width:100%;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.15);" alt="ABHA Card">';
        preview.style.display = 'block';
      }
    }

    function resetCardAuth() {
      document.getElementById('cardAuthSection').style.display     = 'block';
      document.getElementById('cardDownloadSection').style.display  = 'none';
      document.getElementById('cardPreview').style.display          = 'none';
      document.getElementById('card_otp_in').value                  = '';
      document.getElementById('cardOtpRow').style.display           = 'none';
      document.getElementById('btnCardVerifyOtp').classList.add('d-none');
      document.getElementById('btnCardSendOtp').disabled            = false;
      document.getElementById('btnCardSendOtp').innerHTML           = '<i class="fas fa-mobile-alt me-1"></i>Send OTP';
      document.getElementById('cardAlertBox').style.display         = 'none';
      _cardTxnId = '';
    }

    /* Init tab state */
    switchTab('link');
  </script>
</body>

</html>