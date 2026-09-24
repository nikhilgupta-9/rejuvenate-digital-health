<?php
session_start();
include_once "../config/connect.php";
include_once "../config/abdm.php";
include_once "../util/function.php";
include_once "../lib/Security.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || empty($_SESSION['user_id'])) {
  header("Location: " . BASE_URL . "login.php");
  exit();
}

$contact = contact_us();
$user_id = (int)$_SESSION['user_id'];
$success = $error = '';

/* ─── Fetch user ─────────────────────────────────────────────────── */
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  unset($_SESSION['logged_in'], $_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
  session_destroy();
  header("Location: " . BASE_URL . "login.php?error=" . urlencode("User account not found. Please log in again."));
  exit();
}

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

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My ABHA Health ID | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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

              <?php if (!empty($user['abha_linked']) && !empty($user['abha_id'])): ?>
                <!-- ═══ LINKED ═══ -->
                <div class="abha-card-big">
                  <div style="position:relative;z-index:1;">
                    <div class="abha-lbl">Ayushman Bharat Health Account</div>
                    <div class="abha-num"><?= htmlspecialchars($user['abha_id'] ?? '') ?></div>
                    <?php if (!empty($user['abha_address'])): ?>
                      <div class="abha-addr"><i class="fas fa-at me-1" style="font-size:.7rem;"></i><?= htmlspecialchars($user['abha_address']) ?></div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
                      <div>
                        <div style="font-size:.82rem;font-weight:600;"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                        <?php if ($age): ?><div style="font-size:.7rem;opacity:.75;"><?= $age ?> · <?= htmlspecialchars($user['gender'] ?? '') ?> · <?= htmlspecialchars($user['blood_group'] ?? '') ?></div><?php endif; ?>
                      </div>
                      <?php if (!empty($user['abha_verified'])): ?>
                        <span class="abha-ver"><i class="fas fa-shield-alt"></i>Verified by ABDM</span>
                      <?php else: ?>
                        <span class="abha-ver" style="background:rgba(255,165,0,.25);border-color:rgba(255,165,0,.4);"><i class="fas fa-link"></i>Linked</span>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($user['abha_linked_at'])): ?>
                      <div style="font-size:.63rem;opacity:.55;margin-top:10px;"><i class="fas fa-calendar me-1"></i>Linked on <?= date('d M Y', strtotime($user['abha_linked_at'])) ?></div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="info-box">
                  <h6 class="fw-bold mb-3" style="font-size:.85rem;color:#374151;"><i class="fas fa-id-card me-2 text-success"></i>ABHA Details</h6>
                  <div class="info-row"><span class="info-lbl">ABHA Number</span><span class="info-val" style="font-family:monospace;color:#00875a;"><?= htmlspecialchars($user['abha_id'] ?? '') ?></span></div>
                  <div class="info-row"><span class="info-lbl">ABHA Address</span><span class="info-val"><?= htmlspecialchars($user['abha_address'] ?? '—') ?></span></div>
                  <div class="info-row">
                    <span class="info-lbl">Status</span>
                    <?php if (!empty($user['abha_verified'])): ?><span class="spill-b"><i class="fas fa-shield-alt me-1"></i>Verified</span>
                    <?php else: ?><span class="spill-g"><i class="fas fa-link me-1"></i>Linked</span><?php endif; ?>
                  </div>
                  <?php if (!empty($user['abha_linked_at'])): ?>
                    <div class="info-row"><span class="info-lbl">Linked On</span><span class="info-val"><?= date('d M Y', strtotime($user['abha_linked_at'])) ?></span></div>
                  <?php endif; ?>
                </div>

                <!-- ABHA Card Download -->
                <?php
                $isCardUnlocked = (!empty($_SESSION['abdm_card_verified_' . ($user['id'] ?? 0)]) && $_SESSION['abdm_card_verified_' . ($user['id'] ?? 0)] > time()) || Security::getToken('abdm_user_token');
                ?>
                <div class="info-box" id="abhaCardBox">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="font-size:.85rem;color:#374151;"><i class="fas fa-id-card me-2 text-success"></i>Official ABHA Card</h6>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.72rem;">ABDM M1 Certified</span>
                  </div>

                  <div id="cardAuthSection" style="<?= $isCardUnlocked ? 'display:none;' : '' ?>">
                    <p style="font-size:.81rem;color:#6b7280;margin-bottom:12px;">
                      <i class="fas fa-shield-alt me-1 text-success"></i>
                      Verify your identity to unlock and download your official government ABHA card. An OTP will be sent to your registered mobile number.
                    </p>
                    <div id="cardAlertBox" class="alert mb-2" style="display:none;"></div>
                    <div id="cardOtpRow" style="display:none;" class="mb-3">
                      <label class="form-label fw-semibold" style="font-size:.83rem;">Enter 6-digit OTP</label>
                      <div class="input-group">
                        <input type="text" class="form-control otp-input-big" id="card_otp_in" placeholder="• • • • • •" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                        <button class="btn fw-semibold" style="background:#00875a;color:#fff;font-size:.85rem;" id="btnCardVerifyOtp" onclick="cardVerifyOtp()">
                          <i class="fas fa-check-circle me-1"></i>Verify OTP &amp; Unlock
                        </button>
                      </div>
                      <div class="form-text text-muted" style="font-size:.76rem;">OTP sent via ABDM to your Aadhaar-linked registered mobile. Press Enter to verify.</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <button class="btn fw-semibold" style="background:#00875a;color:#fff;font-size:.83rem;" id="btnCardSendOtp" onclick="cardSendOtp()">
                        <i class="fas fa-mobile-alt me-1"></i>Send OTP
                      </button>
                      <span id="cardResendTimer" style="display:none;font-size:.78rem;color:#64748b;"></span>
                    </div>
                  </div>

                  <div id="cardDownloadSection" style="<?= $isCardUnlocked ? 'display:block;' : 'display:none;' ?>">
                    <div class="alert alert-success py-2 px-3 mb-3 d-flex align-items-center justify-content-between" style="font-size:.8rem;border-radius:10px;">
                      <span><i class="fas fa-check-circle me-2"></i>ABHA Identity Authenticated — your official card is ready.</span>
                      <button class="btn btn-sm btn-link text-muted p-0 text-decoration-none" onclick="resetCardAuth()"><i class="fas fa-lock me-1"></i>Lock</button>
                    </div>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                      <button class="btn fw-semibold" style="background:#00875a;color:#fff;font-size:.83rem;" onclick="downloadAbhaCard('png', event)">
                        <i class="fas fa-download me-1"></i>Download PNG Image
                      </button>
                      <button class="btn btn-outline-success fw-semibold" style="font-size:.83rem;" onclick="downloadAbhaCard('pdf', event)">
                        <i class="fas fa-file-pdf me-1"></i>Download Printable PDF
                      </button>
                    </div>
                    <div id="cardPreview"></div>
                  </div>
                </div>

                <!-- Standalone ABHA QR Code (M1 & M3 Scan & Share Ready) -->
                <div class="info-box" id="abhaQrBox">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="font-size:.85rem;color:#374151;"><i class="fas fa-qrcode me-2 text-success"></i>ABHA QR Code (Scan &amp; Share Ready)</h6>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.72rem;">ABDM M1 / M3</span>
                  </div>
                  <div class="text-center p-3" style="background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">
                    <div id="abhaStandaloneQr" style="display:inline-block;padding:12px;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);"></div>
                    <p class="text-muted mt-2 mb-2" style="font-size:.78rem;">
                      Scan this QR code at hospital / clinic reception counter for instant check-in via ABDM Scan &amp; Share.
                    </p>
                    <div class="d-flex justify-content-center gap-2 mt-2">
                      <button class="btn btn-sm btn-outline-success fw-semibold" onclick="downloadStandaloneQr()">
                        <i class="fas fa-download me-1"></i>Download QR Code (PNG)
                      </button>
                    </div>
                  </div>
                </div>

                <!-- ABHA Self-Service & Account Settings (M1) -->
                <div class="info-box" id="abhaSelfServiceBox">
                  <h6 class="fw-bold mb-2" style="font-size:.85rem;color:#374151;"><i class="fas fa-user-cog me-2 text-primary"></i>ABHA Account Self-Service (M1)</h6>
                  <p style="font-size:.8rem;color:#6b7280;margin-bottom:14px;">Manage your official ABDM registry profile, contact details, or account status directly.</p>
                  
                  <div class="row g-2">
                    <div class="col-sm-6">
                      <button class="btn btn-outline-primary btn-sm w-100 text-start py-2" data-bs-toggle="modal" data-bs-target="#modalUpdateMobile">
                        <i class="fas fa-phone-alt me-2"></i>Update Mobile Number
                      </button>
                    </div>
                    <div class="col-sm-6">
                      <button class="btn btn-outline-primary btn-sm w-100 text-start py-2" data-bs-toggle="modal" data-bs-target="#modalUpdateEmail">
                        <i class="fas fa-envelope me-2"></i>Update Email Address
                      </button>
                    </div>
                    <div class="col-sm-12 mt-2">
                      <button class="btn btn-outline-danger btn-sm w-100 text-start py-2" data-bs-toggle="modal" data-bs-target="#modalDeactivateAbha">
                        <i class="fas fa-user-slash me-2"></i>Deactivate ABHA Account
                      </button>
                    </div>
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

                    <?php if (defined('ABDM_ENV') && ABDM_ENV === 'sandbox'): ?>
                      <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:.8rem;border-radius:10px;">
                        <i class="fas fa-flask text-warning fs-5"></i>
                        <div>
                          <strong>ABDM Sandbox Environment:</strong> This system is currently connected to the ABDM Sandbox test registry. Real-world government ABHAs do not exist in the sandbox database. To test, please create an ABHA under the <strong>Create New ABHA</strong> tab.
                        </div>
                      </div>
                    <?php endif; ?>

                    <!-- Tab buttons -->
                    <div class="wizard-tab-btns mb-3">
                      <button class="active" id="btnTabLink" onclick="switchTab('link')">
                        <i class="fas fa-link me-1"></i>Link Existing ABHA
                      </button>
                      <button id="btnTabCreate" onclick="switchTab('create')">
                        <i class="fas fa-plus-circle me-1"></i>Create New ABHA
                      </button>
                      <button id="btnTabFind" onclick="switchTab('find')">
                        <i class="fas fa-search me-1"></i>Find ABHA Number
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
                          <div class="input-group">
                            <input type="text" class="form-control" id="link_abha_in"
                              placeholder="XX-XXXX-XXXX-XXXX" maxlength="19" oninput="fmtAbha(this,'prev_num')">
                            <button class="btn btn-outline-secondary" type="button" id="btnScanAbhaQr" title="Scan ABHA QR">
                              <i class="fas fa-qrcode"></i>
                            </button>
                          </div>
                          <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">14-digit ABHA ID or QR code</small>
                            <a href="javascript:void(0)" onclick="switchTab('find')" style="font-size:.76rem;color:#0C74C5;text-decoration:none;font-weight:600;">
                              <i class="fas fa-question-circle me-1"></i>Don't know your ABHA Number?
                            </a>
                          </div>
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
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Communication Mobile Number <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input type="text" class="form-control" id="create_comm_mobile"
                              placeholder="10-digit mobile" maxlength="10" inputmode="numeric"
                              value="<?= htmlspecialchars(substr(preg_replace('/\D/', '', $user['mobile'] ?? ''), -10)) ?>">
                          </div>
                          <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Required by ABDM for communication and notifications.</small>
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

                    <!-- ── TAB: Find ABHA Number ── -->
                    <div id="tabFind" style="display:none;">

                      <!-- Step F1: Choose method & Enter Identifier -->
                      <div id="stepF1">
                        <p class="step-indicator"><span class="cur">Step 1</span> of 3 — Search ABDM Registry</p>
                        <p style="font-size:.82rem;color:#4b5563;margin-bottom:12px;">
                          Forgot or don't know your 14-digit ABHA number? Search the ABDM database using your registered Mobile Number or Aadhaar Number.
                        </p>

                        <!-- Method Picker -->
                        <div class="d-flex gap-2 mb-3">
                          <button type="button" class="btn flex-fill text-start p-2" id="btnFindMethodMobile"
                            onclick="switchFindMethod('mobile')"
                            style="border:2px solid #0C74C5;background:#f0f9ff;border-radius:8px;">
                            <div class="fw-semibold" style="font-size:.82rem;color:#0369a1;"><i class="fas fa-mobile-alt me-1"></i>Mobile OTP</div>
                            <div style="font-size:.72rem;color:#6b7280;">Finds all ABHAs on your mobile</div>
                          </button>
                          <button type="button" class="btn flex-fill text-start p-2" id="btnFindMethodAadhaar"
                            onclick="switchFindMethod('aadhaar')"
                            style="border:2px solid #e5e7eb;background:#f9fafb;border-radius:8px;">
                            <div class="fw-semibold" style="font-size:.82rem;color:#374151;"><i class="fas fa-fingerprint me-1"></i>Aadhaar OTP</div>
                            <div style="font-size:.72rem;color:#6b7280;">UIDAI OTP verification</div>
                          </button>
                        </div>

                        <!-- Form: Mobile -->
                        <div id="findFormMobile">
                          <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                              <span class="input-group-text">+91</span>
                              <input type="text" class="form-control" id="find_mobile_in"
                                placeholder="10-digit mobile" maxlength="10" inputmode="numeric"
                                value="<?= htmlspecialchars(substr(preg_replace('/\D/', '', $user['mobile'] ?? ''), -10)) ?>">
                            </div>
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>An OTP will be sent to this mobile number by ABDM.</small>
                          </div>
                        </div>

                        <!-- Form: Aadhaar -->
                        <div id="findFormAadhaar" style="display:none;">
                          <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.84rem;">Aadhaar Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="find_aadhaar_in"
                              placeholder="12-digit Aadhaar number" maxlength="12" inputmode="numeric">
                            <small class="text-muted"><i class="fas fa-lock me-1"></i>Never stored; verified directly with UIDAI.</small>
                          </div>
                          <div class="form-check mb-3" style="font-size:.8rem;">
                            <input class="form-check-input" type="checkbox" id="find_aadhaar_consent" checked>
                            <label class="form-check-label text-muted" for="find_aadhaar_consent">
                              I consent to use my Aadhaar number for authentication via UIDAI to fetch my ABHA details as per ABDM guidelines.
                            </label>
                          </div>
                        </div>

                        <button class="btn w-100 fw-semibold" style="background:#0C74C5;color:#fff;" onclick="reqFindOtp()">
                          <i class="fas fa-paper-plane me-2"></i>Send OTP to Discover ABHA
                        </button>
                      </div>

                      <!-- Step F2: Enter OTP -->
                      <div id="stepF2" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 2</span> of 3 — Verify OTP</p>
                        <p id="findOtpMsg" style="font-size:.82rem;color:#374151;margin-bottom:14px;"></p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold" style="font-size:.84rem;">Enter 6-digit OTP</label>
                          <input type="text" class="form-control otp-input-big" id="find_otp_in"
                            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                        </div>
                        <div class="d-flex gap-2">
                          <button class="btn btn-outline-secondary" style="font-size:.82rem;" onclick="resetStep('find')">
                            <i class="fas fa-arrow-left me-1"></i>Back
                          </button>
                          <button class="btn flex-fill fw-semibold" style="background:#0C74C5;color:#fff;" onclick="verifyFindOtp()">
                            <i class="fas fa-search me-1"></i>Verify & Search ABHA
                          </button>
                        </div>
                      </div>

                      <!-- Step F3: Search Results -->
                      <div id="stepF3" style="display:none;">
                        <p class="step-indicator"><span class="cur">Step 3</span> of 3 — Discovered Accounts</p>
                        <div class="alert alert-success d-flex align-items-center mb-3 py-2 px-3" style="font-size:.84rem;">
                          <i class="fas fa-check-circle me-2 fa-lg text-success"></i>
                          <div>Found <strong id="findAccountsCount">0</strong> registered ABHA account(s).</div>
                        </div>

                        <div id="findResultsList"></div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                          <button class="btn btn-sm btn-outline-secondary" onclick="resetStep('find')">
                            <i class="fas fa-redo me-1"></i>Search Another Number
                          </button>
                          <button class="btn btn-sm btn-outline-primary" onclick="switchTab('create')">
                            <i class="fas fa-plus me-1"></i>Create New Instead
                          </button>
                        </div>
                      </div>

                    </div><!-- /tabFind -->

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
                <div class="info-row"><span class="info-lbl">Name</span><span class="info-val"><?= htmlspecialchars($user['name'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-lbl">Age / Gender</span><span class="info-val"><?= $age ?> · <?= htmlspecialchars($user['gender'] ?? '—') ?></span></div>
                <div class="info-row"><span class="info-lbl">Blood Group</span><span class="info-val"><?= htmlspecialchars($user['blood_group'] ?? '—') ?></span></div>
                <div class="info-row"><span class="info-lbl">ID Type</span><span class="info-val"><?= htmlspecialchars($user['identification_type'] ?? 'None') ?> <?= !empty($user['identification_number']) ? '· ' . htmlspecialchars($user['identification_number']) : '' ?></span></div>
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
  <script src="<?= BASE_URL ?>assets/js/abha-qr-scanner.js"></script>
  <script>
    /* ── QR scan: fill ABHA number/address from a scanned card/app QR ── */
    document.getElementById('btnScanAbhaQr')?.addEventListener('click', function () {
      AbhaQrScanner.open({
        title: 'Scan ABHA QR',
        onResult(parsed) {
          const input = document.getElementById('link_abha_in');
          if (parsed.abha_number) {
            input.value = parsed.abha_number;
          } else if (parsed.abha_address) {
            input.value = parsed.abha_address;
          } else {
            wAlert('QR did not contain a recognisable ABHA number or address.');
            return;
          }
          fmtAbha(input, 'prev_num');
        },
        onUnsupported() {
          wAlert('QR scanning is not supported in this browser. Please enter the ABHA number manually.');
        }
      });
    });

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
      const q = document.getElementById(which + '_captcha_q');
      if (!q) return;
      const a = Math.floor(Math.random() * 10) + 1;
      const b = Math.floor(Math.random() * 10) + 1;
      _captcha[which] = a + b;
      q.textContent = a + ' + ' + b + ' = ?';
      const ans = document.getElementById(which + '_captcha_ans');
      if (ans) ans.value = '';
    }

    function validateCaptcha(which) {
      const el = document.getElementById(which + '_captcha_ans');
      if (!el) return false;
      const ans = parseInt(el.value.trim(), 10);
      return ans === _captcha[which];
    }

    // Init captchas on load if elements are present
    if (document.getElementById('aadhaar_captcha_q')) {
      refreshCaptcha('aadhaar');
      refreshCaptcha('dl');
    }

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
      const wizard = document.getElementById('abhaWizard');
      if (!wizard) return; // Outside wizard context, do not touch steps

      const loader = document.getElementById('wLoader');
      if (loader) loader.style.display = show ? 'block' : 'none';
      const loaderMsg = document.getElementById('wLoaderMsg');
      if (loaderMsg && msg) loaderMsg.textContent = msg;
      // hide all step content while loading
      const steps = wizard.querySelectorAll('[id^="step"]');
      steps.forEach(s => {
        if (s.id !== 'wLoader') s.style.display = show ? 'none' : '';
      });
      if (!show) {
        // restore active step
        if (abdmFlow === 'link_existing' && typeof showLinkStep === 'function') showLinkStep(window._curLinkStep || 'L1');
        else if (abdmFlow === 'find_abha' && typeof showFindStep === 'function') showFindStep(window._curFindStep || 'F1');
        else if (typeof showCreateStep === 'function') showCreateStep(window._curCreateStep || 'C1');
      }
    }

    function wAlert(msg, type = 'danger') {
      const b = document.getElementById('wAlertBox');
      if (!b) {
        if (typeof cardAlert === 'function' && document.getElementById('cardAlertBox')) {
          cardAlert(msg, type);
        } else {
          alert(msg);
        }
        return;
      }
      b.className = 'alert alert-' + type + ' mb-3';
      b.textContent = msg;
      b.style.display = 'block';
      setTimeout(() => { if (b) b.style.display = 'none'; }, 6000);
    }

    /* ── Tab switching ── */
    function switchTab(tab) {
      const tabLink = document.getElementById('tabLink');
      const tabCreate = document.getElementById('tabCreate');
      const tabFind = document.getElementById('tabFind');
      if (tabLink) tabLink.style.display = tab === 'link' ? 'block' : 'none';
      if (tabCreate) tabCreate.style.display = tab === 'create' ? 'block' : 'none';
      if (tabFind) tabFind.style.display = tab === 'find' ? 'block' : 'none';

      const btnTabLink = document.getElementById('btnTabLink');
      const btnTabCreate = document.getElementById('btnTabCreate');
      const btnTabFind = document.getElementById('btnTabFind');
      if (btnTabLink) btnTabLink.classList.toggle('active', tab === 'link');
      if (btnTabCreate) btnTabCreate.classList.toggle('active', tab === 'create');
      if (btnTabFind) btnTabFind.classList.toggle('active', tab === 'find');

      if (tab === 'link') {
        abdmFlow = 'link_existing';
      } else if (tab === 'find') {
        abdmFlow = 'find_abha';
        if (typeof showFindStep === 'function') showFindStep(window._curFindStep || 'F1');
      } else {
        abdmFlow = 'create_aadhaar';
      }
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
      const fA = document.getElementById('createFormAadhaar');
      const fD = document.getElementById('createFormDL');
      if (fA) fA.style.display = method === 'aadhaar' ? 'block' : 'none';
      if (fD) fD.style.display = method === 'dl' ? 'block' : 'none';
      const btnA = document.getElementById('btnMethodAadhaar');
      const btnD = document.getElementById('btnMethodDL');
      if (btnA) {
        btnA.style.border     = method === 'aadhaar' ? '2px solid #00875a' : '2px solid #e5e7eb';
        btnA.style.background = method === 'aadhaar' ? '#f0fdf4' : '#f9fafb';
      }
      if (btnD) {
        btnD.style.border     = method === 'dl' ? '2px solid #1d4ed8' : '2px solid #e5e7eb';
        btnD.style.background = method === 'dl' ? '#eff6ff' : '#f9fafb';
      }
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
      else if (tab === 'find') showFindStep('F1');
      else showCreateStep('C1');
      document.getElementById('wAlertBox').style.display = 'none';
      abdmTxnId = '';
      findTxnId = '';
    }

    /* ── Find ABHA flow ── */
    let findMethod = 'mobile';
    let findTxnId = '';

    function switchFindMethod(method) {
      findMethod = method;
      const fMobile = document.getElementById('findFormMobile');
      const fAadhaar = document.getElementById('findFormAadhaar');
      const btnM = document.getElementById('btnFindMethodMobile');
      const btnA = document.getElementById('btnFindMethodAadhaar');
      if (fMobile) fMobile.style.display = method === 'mobile' ? 'block' : 'none';
      if (fAadhaar) fAadhaar.style.display = method === 'aadhaar' ? 'block' : 'none';
      if (btnM) {
        btnM.style.border = method === 'mobile' ? '2px solid #0C74C5' : '2px solid #e5e7eb';
        btnM.style.background = method === 'mobile' ? '#f0f9ff' : '#f9fafb';
      }
      if (btnA) {
        btnA.style.border = method === 'aadhaar' ? '2px solid #00875a' : '2px solid #e5e7eb';
        btnA.style.background = method === 'aadhaar' ? '#f0fdf4' : '#f9fafb';
      }
    }

    function showFindStep(step) {
      ['F1', 'F2', 'F3'].forEach(s => {
        const el = document.getElementById('step' + s);
        if (el) el.style.display = (step === s) ? 'block' : 'none';
      });
      const ss = document.getElementById('stepSuccess');
      if (ss && step !== 'Success') ss.style.display = 'none';
      if (step === 'F1') switchFindMethod(findMethod);
      window._curFindStep = step;
    }

    async function reqFindOtp() {
      let val = '';
      let consent = false;
      if (findMethod === 'mobile') {
        val = (document.getElementById('find_mobile_in')?.value || '').replace(/\D/g, '');
        if (val.length !== 10) {
          wAlert('Please enter a valid 10-digit mobile number');
          return;
        }
      } else {
        val = (document.getElementById('find_aadhaar_in')?.value || '').replace(/\D/g, '');
        if (val.length !== 12) {
          wAlert('Please enter a valid 12-digit Aadhaar number');
          return;
        }
        consent = document.getElementById('find_aadhaar_consent')?.checked;
        if (!consent) {
          wAlert('Consent is required to search using Aadhaar OTP');
          return;
        }
      }

      const res = await abdmPost('find_abha_request_otp', {
        auth_type: findMethod,
        auth_value: val,
        consent: consent ? 1 : 0
      });

      if (res.success) {
        findTxnId = res.txnId || '';
        document.getElementById('findOtpMsg').textContent = res.message || 'OTP sent successfully.';
        document.getElementById('find_otp_in').value = '';
        showFindStep('F2');
      } else {
        wAlert(res.message);
      }
    }

    async function verifyFindOtp() {
      const otp = (document.getElementById('find_otp_in')?.value || '').replace(/\D/g, '');
      if (otp.length !== 6) {
        wAlert('Please enter a valid 6-digit OTP');
        return;
      }
      const res = await abdmPost('find_abha_verify_otp', {
        otp: otp,
        txnId: findTxnId
      });

      if (res.success && res.accounts && res.accounts.length > 0) {
        renderDiscoveredAbhas(res.accounts);
        showFindStep('F3');
      } else {
        wAlert(res.message || 'No registered ABHA found for this detail.');
      }
    }

    function renderDiscoveredAbhas(accounts) {
      const container = document.getElementById('findResultsList');
      if (!container) return;
      document.getElementById('findAccountsCount').textContent = accounts.length;

      let html = '';
      accounts.forEach((acc) => {
        const num = acc.abha_number || '—';
        const addr = acc.abha_address || '—';
        const name = acc.name || 'ABHA Holder';
        const meta = [acc.gender, acc.yearOfBirth ? 'YOB: ' + acc.yearOfBirth : '', acc.status].filter(Boolean).join(' · ');

        html += `
          <div class="card mb-3 p-3 border shadow-sm" style="border-radius:12px;background:#fcfdfd;">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <div class="fw-bold text-dark" style="font-size:1rem;"><i class="fas fa-user-circle text-primary me-2"></i>${escapeHtml(name)}</div>
                <div class="mt-1" style="font-family:monospace;font-weight:700;color:#00875a;font-size:1.1rem;letter-spacing:1px;">
                  ${escapeHtml(num)}
                  <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-muted" title="Copy ABHA Number" onclick="copyToClipboard('${escapeHtml(num)}', this)">
                    <i class="far fa-copy"></i>
                  </button>
                </div>
                ${addr !== '—' ? `<div style="font-size:.82rem;color:#0C74C5;font-family:monospace;">${escapeHtml(addr)}</div>` : ''}
                <div style="font-size:.76rem;color:#6b7280;margin-top:3px;"><i class="fas fa-info-circle me-1"></i>${escapeHtml(meta)}</div>
              </div>
              <div class="d-flex flex-column gap-2 mt-2 mt-sm-0">
                <button type="button" class="btn btn-sm fw-semibold" style="background:#00875a;color:#fff;border-radius:8px;padding:6px 14px;"
                  onclick="linkFoundAbha('${escapeHtml(num)}', '${escapeHtml(addr)}')">
                  <i class="fas fa-link me-1"></i>Link to My Profile
                </button>
              </div>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    }

    async function linkFoundAbha(num, addr) {
      if (!confirm(`Are you sure you want to link ABHA Number ${num} to your profile?`)) return;
      const res = await abdmPost('find_abha_link_account', {
        abha_number: num,
        abha_address: addr
      });
      if (res.success) {
        document.getElementById('success_abha_num').textContent = res.abha_number || num;
        document.getElementById('success_abha_addr').textContent = res.abha_address || addr;
        const tabFind = document.getElementById('tabFind');
        if (tabFind) tabFind.style.display = 'none';
        const ss = document.getElementById('stepSuccess');
        if (ss) ss.style.display = 'block';
      } else {
        wAlert(res.message);
      }
    }

    function copyToClipboard(text, btn) {
      if (!navigator.clipboard) {
        const t = document.createElement('textarea');
        t.value = text;
        document.body.appendChild(t);
        t.select();
        document.execCommand('copy');
        document.body.removeChild(t);
      } else {
        navigator.clipboard.writeText(text);
      }
      if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => btn.innerHTML = orig, 1800);
      }
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
      });
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

      const defaultMob = '<?= htmlspecialchars(substr(preg_replace('/\D/', '', $user['mobile'] ?? ''), -10)) ?>';
      const res = await abdmPost('gen_aadhaar_otp', { aadhaar: raw, consent: 1, mobile: defaultMob });

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
      const mobEl = document.getElementById('create_comm_mobile');
      let mobile = mobEl ? mobEl.value.replace(/\D/g, '') : '';
      if (!mobile) {
        mobile = '<?= htmlspecialchars(substr(preg_replace('/\D/', '', $user['mobile'] ?? ''), -10)) ?>';
      }
      if (mobile.length !== 10) {
        wAlert('Please enter a valid 10-digit mobile number for communication');
        return;
      }

      const res = await abdmPost('create_abha', {
        abha_address: addr,
        mobile: mobile
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
    let _cardCooldownTimer = null;

    function cardAlert(msg, type = 'danger') {
      const b = document.getElementById('cardAlertBox');
      if (!b) return;
      b.className = 'alert alert-' + type + ' mb-2 py-2';
      b.textContent = msg;
      b.style.display = 'block';
    }

    function startCardCooldown(seconds = 60) {
      const sendBtn   = document.getElementById('btnCardSendOtp');
      const timerSpan = document.getElementById('cardResendTimer');
      if (!sendBtn) return;
      sendBtn.disabled = true;
      if (timerSpan) timerSpan.style.display = 'inline-block';
      let left = seconds;
      clearInterval(_cardCooldownTimer);
      _cardCooldownTimer = setInterval(() => {
        left--;
        if (left <= 0) {
          clearInterval(_cardCooldownTimer);
          sendBtn.disabled = false;
          sendBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Resend OTP';
          if (timerSpan) { timerSpan.style.display = 'none'; timerSpan.textContent = ''; }
        } else {
          sendBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Resend OTP (' + left + 's)';
          if (timerSpan) timerSpan.textContent = 'Resend available in ' + left + 's';
        }
      }, 1000);
    }

    async function cardSendOtp() {
      if (!_LINKED_ABHA) { cardAlert('No ABHA linked. Please link an ABHA first.', 'warning'); return; }

      const btn = document.getElementById('btnCardSendOtp');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending OTP…';
      }

      const res = await abdmPost('init_link', {
        abha_id:     _LINKED_ABHA,
        auth_method: 'MOBILE_OTP'
      });

      if (res.success) {
        _cardTxnId = res.txnId || '';
        const otpRow = document.getElementById('cardOtpRow');
        if (otpRow) otpRow.style.display = 'block';
        startCardCooldown(60);
        cardAlert('OTP sent successfully to your Aadhaar-linked registered mobile.', 'success');
        const otpIn = document.getElementById('card_otp_in');
        if (otpIn) {
          otpIn.value = '';
          otpIn.focus();
        }
      } else {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-mobile-alt me-1"></i>Send OTP';
        }
        cardAlert(res.message || 'Could not send OTP. Please try again.', 'danger');
      }
    }

    async function cardVerifyOtp() {
      const otpIn = document.getElementById('card_otp_in');
      const otp = (otpIn?.value || '').trim().replace(/\D/g, '');
      if (otp.length !== 6) {
        cardAlert('Please enter the 6-digit OTP received on your mobile.', 'warning');
        if (otpIn) otpIn.focus();
        return;
      }

      const btn = document.getElementById('btnCardVerifyOtp');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying…';
      }

      const res = await abdmPost('confirm_link_otp', {
        otp: otp,
        txnId: _cardTxnId,
        abha_id: _LINKED_ABHA
      });

      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Verify OTP &amp; Unlock';
      }

      if (res.success) {
        const authSec = document.getElementById('cardAuthSection');
        if (authSec) authSec.style.display = 'none';
        const dlSec = document.getElementById('cardDownloadSection');
        if (dlSec) dlSec.style.display = 'block';
        const alertBox = document.getElementById('cardAlertBox');
        if (alertBox) alertBox.style.display = 'none';
        // Auto-load inline card preview
        downloadAbhaCard('png', null, true);
      } else {
        cardAlert(res.message || 'Invalid or expired OTP. Please try again.', 'danger');
        if (otpIn) otpIn.focus();
      }
    }

    // Auto-verify on 6 digits or Enter key in OTP input
    document.addEventListener('DOMContentLoaded', () => {
      const otpIn = document.getElementById('card_otp_in');
      if (otpIn) {
        otpIn.addEventListener('keyup', (e) => {
          if (e.key === 'Enter' || otpIn.value.trim().length === 6) {
            cardVerifyOtp();
          }
        });
      }
      // If already unlocked on load, load card preview
      const dlSec = document.getElementById('cardDownloadSection');
      if (dlSec && dlSec.style.display !== 'none') {
        downloadAbhaCard('png', null, true);
      }
    });

    async function downloadAbhaCard(format, evt, isPreviewOnly = false) {
      let btn = null;
      let orig = '';
      if (!isPreviewOnly) {
        btn = (evt && evt.currentTarget) ? evt.currentTarget : (window.event ? window.event.currentTarget : document.activeElement);
        orig = btn ? btn.innerHTML : '';
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Downloading…';
        }
      }

      const res = await abdmPost('get_abha_card', { format });

      if (btn) {
        btn.disabled = false;
        btn.innerHTML = orig;
      }

      if (!res.success) {
        if (!isPreviewOnly) cardAlert(res.message || 'Download failed. Please try again.', 'danger');
        return;
      }

      try {
        const byteArr = Uint8Array.from(atob(res.data), c => c.charCodeAt(0));
        const mime = res.mimeType || (format === 'pdf' ? 'application/pdf' : 'image/png');
        const blob = new Blob([byteArr], { type: mime });

        if (!isPreviewOnly) {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'ABHA-Card-' + (_LINKED_ABHA || 'Official') + '.' + format;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        }

        // Show PNG preview inline
        if (format === 'png') {
          const preview = document.getElementById('cardPreview');
          if (preview) {
            preview.innerHTML = '<div class="text-center p-2" style="background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">'
              + '<p class="fw-semibold text-muted mb-2" style="font-size:.78rem;"><i class="fas fa-eye me-1"></i>Official ABHA Card Preview</p>'
              + '<img src="data:image/png;base64,' + res.data + '" style="max-width:100%;height:auto;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);border:1px solid #e2e8f0;" alt="ABHA Card">'
              + '</div>';
            preview.style.display = 'block';
          }
        }
      } catch (err) {
        console.error('Card processing error:', err);
        if (!isPreviewOnly) cardAlert('Error rendering ABHA card data: ' + err.message, 'danger');
      }
    }

    function resetCardAuth() {
      const authSec = document.getElementById('cardAuthSection');
      if (authSec) authSec.style.display = 'block';
      const dlSec = document.getElementById('cardDownloadSection');
      if (dlSec) dlSec.style.display = 'none';
      const preview = document.getElementById('cardPreview');
      if (preview) preview.style.display = 'none';
      const otpIn = document.getElementById('card_otp_in');
      if (otpIn) otpIn.value = '';
      const otpRow = document.getElementById('cardOtpRow');
      if (otpRow) otpRow.style.display = 'none';
      const sendBtn = document.getElementById('btnCardSendOtp');
      if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-mobile-alt me-1"></i>Send OTP';
      }
      const timerSpan = document.getElementById('cardResendTimer');
      if (timerSpan) { timerSpan.style.display = 'none'; timerSpan.textContent = ''; }
      clearInterval(_cardCooldownTimer);
      const alertBox = document.getElementById('cardAlertBox');
      if (alertBox) alertBox.style.display = 'none';
      _cardTxnId = '';
    }

    /* Init tab state only if wizard tabs are present */
    if (document.getElementById('tabLink')) {
      switchTab('link');
    }

    // Render Standalone QR Code if user is linked
    const qrContainer = document.getElementById('abhaStandaloneQr');
    if (qrContainer && typeof QRCode !== 'undefined' && _LINKED_ABHA) {
      try {
        const qrPayload = JSON.stringify({
          "hidn": _LINKED_ABHA,
          "hid": "<?= htmlspecialchars($user['abha_address'] ?? '', ENT_QUOTES) ?>",
          "name": "<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?>",
          "gender": "<?= htmlspecialchars($user['gender'] ?? '', ENT_QUOTES) ?>",
          "dob": "<?= htmlspecialchars($user['dob'] ?? '', ENT_QUOTES) ?>"
        });

        new QRCode(qrContainer, {
          text: qrPayload,
          width: 160,
          height: 160,
          colorDark: "#000000",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.M
        });
      } catch (e) {
        console.error("QR Code generation error:", e);
      }
    }

    function downloadStandaloneQr() {
      const img = document.querySelector('#abhaStandaloneQr img');
      if (img && img.src) {
        const a = document.createElement('a');
        a.href = img.src;
        a.download = 'ABHA-QR-Code.png';
        a.click();
      } else {
        const canvas = document.querySelector('#abhaStandaloneQr canvas');
        if (canvas) {
          const a = document.createElement('a');
          a.href = canvas.toDataURL('image/png');
          a.download = 'ABHA-QR-Code.png';
          a.click();
        }
      }
    }

    let _mobileTxnId = '', _emailTxnId = '';

    async function reqMobileUpdateOtp() {
      const mobile = document.getElementById('new_mobile_in').value.trim();
      if (mobile.length !== 10) { alert('Enter a valid 10-digit mobile number'); return; }
      const res = await abdmPost('req_mobile_update_otp', { new_mobile: mobile });
      if (res.success) {
        _mobileTxnId = res.txnId;
        document.getElementById('mobileOtpStep').style.display = 'block';
        document.getElementById('btnReqMobileOtp').style.display = 'none';
        document.getElementById('mobileAlert').className = 'alert alert-success';
        document.getElementById('mobileAlert').textContent = res.message || 'OTP sent!';
        document.getElementById('mobileAlert').style.display = 'block';
      } else {
        document.getElementById('mobileAlert').className = 'alert alert-danger';
        document.getElementById('mobileAlert').textContent = res.message || 'Error sending OTP';
        document.getElementById('mobileAlert').style.display = 'block';
      }
    }

    async function verifyMobileUpdateOtp() {
      const otp = document.getElementById('mobile_otp_in').value.trim();
      if (otp.length !== 6) { alert('Enter 6-digit OTP'); return; }
      const res = await abdmPost('verify_mobile_update_otp', { otp, txnId: _mobileTxnId });
      if (res.success) {
        alert(res.message);
        location.reload();
      } else {
        alert(res.message || 'OTP verification failed');
      }
    }

    async function reqEmailUpdateOtp() {
      const email = document.getElementById('new_email_in').value.trim();
      if (!email.includes('@')) { alert('Enter a valid email address'); return; }
      const res = await abdmPost('req_email_update_otp', { new_email: email });
      if (res.success) {
        _emailTxnId = res.txnId;
        document.getElementById('emailOtpStep').style.display = 'block';
        document.getElementById('btnReqEmailOtp').style.display = 'none';
        document.getElementById('emailAlert').className = 'alert alert-success';
        document.getElementById('emailAlert').textContent = res.message || 'OTP sent!';
        document.getElementById('emailAlert').style.display = 'block';
      } else {
        document.getElementById('emailAlert').className = 'alert alert-danger';
        document.getElementById('emailAlert').textContent = res.message || 'Error sending OTP';
        document.getElementById('emailAlert').style.display = 'block';
      }
    }

    async function verifyEmailUpdateOtp() {
      const otp = document.getElementById('email_otp_in').value.trim();
      if (otp.length !== 6) { alert('Enter 6-digit OTP'); return; }
      const res = await abdmPost('verify_email_update_otp', { otp, txnId: _emailTxnId });
      if (res.success) {
        alert(res.message);
        location.reload();
      } else {
        alert(res.message || 'OTP verification failed');
      }
    }

    async function confirmDeactivateAbha() {
      const reason = document.getElementById('deactivate_reason_in').value;
      if (!confirm('Are you sure you want to deactivate your ABHA account? You can reactivate it later with an OTP.')) return;
      const res = await abdmPost('deactivate_abha', { reason });
      if (res.success) {
        alert(res.message);
        location.reload();
      } else {
        alert(res.message || 'Deactivation failed. Please try again.');
      }
    }
  </script>

  <!-- Modal: Update Mobile -->
  <div class="modal fade" id="modalUpdateMobile" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-bold"><i class="fas fa-phone-alt me-2 text-primary"></i>Update Mobile Number (M1)</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="mobileAlert" class="alert" style="display:none;"></div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">New 10-Digit Mobile Number</label>
            <input type="tel" class="form-control" id="new_mobile_in" placeholder="Enter 10-digit mobile" maxlength="10">
          </div>
          <div id="mobileOtpStep" style="display:none;" class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Enter 6-Digit OTP</label>
            <input type="text" class="form-control otp-input-big" id="mobile_otp_in" placeholder="• • • • • •" maxlength="6">
            <button class="btn btn-primary w-100 mt-3 fw-semibold" onclick="verifyMobileUpdateOtp()"><i class="fas fa-check me-1"></i>Verify &amp; Update Mobile</button>
          </div>
          <button class="btn btn-primary w-100 fw-semibold" id="btnReqMobileOtp" onclick="reqMobileUpdateOtp()"><i class="fas fa-paper-plane me-1"></i>Send OTP to New Mobile</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Update Email -->
  <div class="modal fade" id="modalUpdateEmail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-bold"><i class="fas fa-envelope me-2 text-primary"></i>Update Email Address (M1)</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="emailAlert" class="alert" style="display:none;"></div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">New Email Address</label>
            <input type="email" class="form-control" id="new_email_in" placeholder="name@domain.com">
          </div>
          <div id="emailOtpStep" style="display:none;" class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Enter 6-Digit OTP</label>
            <input type="text" class="form-control otp-input-big" id="email_otp_in" placeholder="• • • • • •" maxlength="6">
            <button class="btn btn-primary w-100 mt-3 fw-semibold" onclick="verifyEmailUpdateOtp()"><i class="fas fa-check me-1"></i>Verify &amp; Update Email</button>
          </div>
          <button class="btn btn-primary w-100 fw-semibold" id="btnReqEmailOtp" onclick="reqEmailUpdateOtp()"><i class="fas fa-paper-plane me-1"></i>Send OTP to New Email</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Deactivate ABHA -->
  <div class="modal fade" id="modalDeactivateAbha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-bold text-danger"><i class="fas fa-user-slash me-2"></i>Deactivate ABHA Account (M1)</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p style="font-size:.85rem;color:#4b5563;">
            Deactivating your ABHA account stops active health information sharing and hides your profile from the ABDM registry. You can reactivate your account at any time by logging in with OTP.
          </p>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Reason for Deactivation</label>
            <select class="form-select" id="deactivate_reason_in">
              <option value="Temporary pause">Temporary pause / personal choice</option>
              <option value="Created duplicate ABHA">Created duplicate ABHA account</option>
              <option value="Privacy preference">Privacy preference</option>
              <option value="Other">Other reason</option>
            </select>
          </div>
          <button class="btn btn-danger w-100 fw-semibold" onclick="confirmDeactivateAbha()"><i class="fas fa-exclamation-triangle me-1"></i>Confirm Deactivation</button>
        </div>
      </div>
    </div>
  </div>
</body>

</html>