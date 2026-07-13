<?php
include_once "../../config/connect.php";
include_once "../../config/abdm.php";
include_once "auth.php";

$success = $error = '';

/* ─── Fetch student data ──────────────────────────────────────────── */
$stmt = $conn->prepare("SELECT sm.*, s.school_name, s.city, s.state FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

/* Health profile */
$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
$hp_stmt->bind_param('i', $student_id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

/* Pending link request */
$pend_stmt = $conn->prepare("SELECT * FROM abha_link_requests WHERE member_id=? AND status='Pending' ORDER BY requested_at DESC LIMIT 1");
$pend_stmt->bind_param('i', $student_id);
$pend_stmt->execute();
$pending_req = $pend_stmt->get_result()->fetch_assoc();

/* Previous requests history */
$hist_stmt = $conn->prepare("SELECT * FROM abha_link_requests WHERE member_id=? ORDER BY requested_at DESC LIMIT 5");
$hist_stmt->bind_param('i', $student_id);
$hist_stmt->execute();
$req_history = $hist_stmt->get_result();

/* ─── POST: submit ABHA link request ────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_link') {
        if ($student['abha_linked']) {
            $error = "Your ABHA is already linked.";
        } elseif ($pending_req) {
            $error = "You already have a pending ABHA link request. Please wait for school admin to review it.";
        } else {
            $abha_id   = trim($_POST['abha_id'] ?? '');
            $abha_addr = trim($_POST['abha_address'] ?? '');

            $raw = preg_replace('/\D/', '', $abha_id);
            if (strlen($raw) !== 14) {
                $error = "Invalid ABHA number. Must be exactly 14 digits.";
            } else {
                $abha_fmt = substr($raw,0,2).'-'.substr($raw,2,4).'-'.substr($raw,6,4).'-'.substr($raw,10,4);
                if ($abha_addr && strpos($abha_addr,'@') === false) $abha_addr .= '@abdm';

                $ins = $conn->prepare("INSERT INTO abha_link_requests (member_id, school_id, abha_id, abha_address, status) VALUES (?,?,?,?,'Pending')");
                $ins->bind_param('iiss', $student_id, $student_school_id, $abha_fmt, $abha_addr);
                if ($ins->execute()) {
                    $success = "ABHA link request submitted! Your school admin will review and approve it shortly.";
                    // re-fetch pending
                    $pend_stmt->execute();
                    $pending_req = $pend_stmt->get_result()->fetch_assoc();
                    // re-fetch fresh
                    $stmt->execute();
                    $student = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = "Could not submit request. Please try again.";
                }
            }
        }
    }

    if ($action === 'cancel_request' && $pending_req) {
        $del = $conn->prepare("DELETE FROM abha_link_requests WHERE id=? AND member_id=? AND status='Pending'");
        $del->bind_param('ii', $pending_req['id'], $student_id);
        if ($del->execute()) {
            $success = "ABHA link request cancelled.";
            $pending_req = null;
        }
    }
}

/* Compute BMI */
$bmi = null; $bmi_lbl = ''; $bmi_col = '#6b7280';
if (!empty($hp['height_cm']) && !empty($hp['weight_kg'])) {
    $bmi = round($hp['weight_kg'] / (($hp['height_cm']/100)**2), 1);
    if ($bmi < 18.5)     { $bmi_lbl = 'Underweight'; $bmi_col = '#d97706'; }
    elseif ($bmi < 25)   { $bmi_lbl = 'Normal';      $bmi_col = '#16a34a'; }
    elseif ($bmi < 30)   { $bmi_lbl = 'Overweight';  $bmi_col = '#ea580c'; }
    else                 { $bmi_lbl = 'Obese';        $bmi_col = '#dc2626'; }
}

/* Age */
$age = '';
if ($student['dob']) {
    $diff = date_diff(date_create($student['dob']), date_create());
    $age  = $diff->y . ' yrs';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My ABHA | <?= htmlspecialchars($student_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root { --primary:#0C74C5; --accent:#02c9b8; --ab-green:#00875a; }
    body { font-family:'Segoe UI',system-ui,sans-serif; background:#f4f7fb; margin:0; }

    /* Topnav */
    .s-topnav {
      background: var(--primary);
      color: #fff;
      padding: 0 16px;
      height: 58px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 10px rgba(12,116,197,.3);
    }
    .s-topnav .brand { font-size:.9rem; font-weight:700; }
    .s-topnav .sub   { font-size:.65rem; opacity:.75; }

    .s-body { max-width:760px; margin:0 auto; padding:18px 14px 50px; }

    /* ABHA card */
    .abha-card {
      background: var(--ab-green);
      border-radius: 18px;
      color: #fff;
      padding: 24px 22px;
      position: relative;
      overflow: hidden;
      margin-bottom: 20px;
    }
    .abha-card::before {
      content: '';
      position: absolute;
      top: -30px; right: -30px;
      width: 160px; height: 160px;
      background: rgba(255,255,255,.07);
      border-radius: 50%;
    }
    .abha-card::after {
      content: '';
      position: absolute;
      bottom: -50px; left: -20px;
      width: 200px; height: 200px;
      background: rgba(255,255,255,.05);
      border-radius: 50%;
    }
    .abha-num {
      font-size: 1.35rem;
      font-family: monospace;
      font-weight: 700;
      letter-spacing: .1em;
      margin: 8px 0 4px;
    }
    .abha-addr { font-size:.82rem; opacity:.8; }
    .abha-lbl  { font-size:.6rem; opacity:.65; text-transform:uppercase; letter-spacing:.1em; }
    .abha-verified-badge {
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(255,255,255,.2);
      border: 1px solid rgba(255,255,255,.3);
      border-radius: 20px;
      padding: 3px 10px;
      font-size: .7rem;
      font-weight: 700;
    }

    /* Not linked state */
    .abha-empty {
      background: #f0fdf4;
      border: 2px dashed #86efac;
      border-radius: 18px;
      padding: 30px 22px;
      text-align: center;
      margin-bottom: 20px;
    }

    /* Info card */
    .info-card { background:#fff; border-radius:14px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:14px; }
    .info-card h6 { font-size:.82rem; font-weight:700; color:#374151; margin-bottom:12px; }
    .info-row { display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6; }
    .info-row:last-child { border-bottom:none; padding-bottom:0; }
    .info-label { font-size:.76rem; color:#6b7280; }
    .info-val   { font-size:.82rem; font-weight:600; color:#111827; text-align:right; }

    /* Status pill */
    .spill-linked   { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
    .spill-unlinked { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
    .spill-verified { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
    .spill-pending  { background:#ffedd5; color:#9a3412; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }

    /* Req form */
    .req-form-card { background:#fff; border-radius:14px; padding:22px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:14px; border-top:3px solid var(--ab-green); }
    /* Wizard */
    .wiz-tabs { display:flex;gap:6px;margin-bottom:14px; }
    .wiz-tabs button { flex:1;padding:8px 4px;border:1.5px solid #e5e7eb;border-radius:10px;background:#fff;font-size:.75rem;font-weight:600;color:#374151;cursor:pointer;transition:.15s; }
    .wiz-tabs button.active { background:#00875a;color:#fff;border-color:#00875a; }
    .otp-big { letter-spacing:.35em;font-size:1.2rem;font-weight:700;text-align:center;font-family:monospace; }
    .step-ind { font-size:.7rem;color:#6b7280;margin-bottom:10px; }
    .step-ind .cur { color:#00875a;font-weight:700; }
    .abha-ok-card { background:#00875a;border-radius:14px;color:#fff;padding:18px;text-align:center;margin-bottom:12px; }
    .abha-ok-card .num { font-family:monospace;font-size:1.2rem;font-weight:700;letter-spacing:.1em;margin:6px 0 2px; }

    /* ABHA preview mini */
    .abha-preview-mini {
      background: var(--ab-green);
      border-radius: 10px; color:#fff; padding:12px 16px; margin-bottom:12px; font-size:.8rem;
    }
    .abha-preview-mini .pnum { font-family:monospace; font-size:.95rem; font-weight:700; letter-spacing:.08em; }

    /* Health summary */
    .health-pill { background:#f0f7ff; border-radius:10px; padding:10px 14px; text-align:center; }
    .health-pill .h-num { font-size:1.1rem; font-weight:700; color:#0C74C5; }
    .health-pill .h-lbl { font-size:.65rem; color:#6b7280; }

    /* Req history */
    .hist-item { padding:10px 14px; border-radius:10px; background:#f9fafb; margin-bottom:8px; border-left:3px solid #e5e7eb; }
    .hist-item.approved { border-color:#16a34a; background:#f0fdf4; }
    .hist-item.rejected { border-color:#dc2626; background:#fff5f5; }
    .hist-item.pending  { border-color:#d97706; background:#fffbeb; }

    /* Bottom nav */
    .s-bottomnav { position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid #e5e7eb; display:flex; z-index:99; }
    .s-bottomnav a { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:10px 4px; text-decoration:none; color:#6b7280; font-size:.6rem; font-weight:600; gap:3px; }
    .s-bottomnav a i { font-size:1.1rem; }
    .s-bottomnav a.active { color:var(--primary); }
  </style>
</head>
<body>

<!-- Top nav -->
<div class="s-topnav">
  <div>
    <div class="brand"><i class="fas fa-id-card me-2" style="color:#86efac;"></i>My ABHA</div>
    <div class="sub"><?= htmlspecialchars($student_school) ?></div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="dashboard.php" class="text-white text-decoration-none" style="font-size:.8rem;opacity:.8;"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    <a href="logout.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;font-size:.75rem;">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>
</div>

<div class="s-body">

  <?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3" style="border-radius:12px;">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius:12px;">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <?php if ($student['abha_linked'] && $student['abha_id']): ?>
  <!-- ═══ ABHA LINKED — show health card ═══ -->
  <div class="abha-card">
    <div style="position:relative;z-index:1;">
      <div class="abha-lbl">Ayushman Bharat Health Account</div>
      <div class="abha-num"><?= htmlspecialchars($student['abha_id']) ?></div>
      <?php if ($student['abha_address']): ?>
        <div class="abha-addr"><i class="fas fa-at me-1" style="font-size:.7rem;"></i><?= htmlspecialchars($student['abha_address']) ?></div>
      <?php endif; ?>
      <div class="mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div style="font-size:.75rem;font-weight:600;"><?= htmlspecialchars($student['name']) ?></div>
          <div style="font-size:.68rem;opacity:.75;"><?= htmlspecialchars($student_uid) ?></div>
        </div>
        <?php if ($student['abha_verified']): ?>
          <span class="abha-verified-badge"><i class="fas fa-shield-alt"></i>Verified</span>
        <?php else: ?>
          <span class="abha-verified-badge" style="background:rgba(255,165,0,.2);border-color:rgba(255,165,0,.4);">
            <i class="fas fa-link"></i>Linked
          </span>
        <?php endif; ?>
      </div>
      <?php if ($student['abha_linked_at']): ?>
        <div style="font-size:.63rem;opacity:.6;margin-top:10px;">
          <i class="fas fa-calendar me-1"></i>Linked on <?= date('d M Y', strtotime($student['abha_linked_at'])) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ABHA Info -->
  <div class="info-card">
    <h6><i class="fas fa-id-card me-2 text-success"></i>ABHA Details</h6>
    <div class="info-row">
      <span class="info-label">ABHA Number</span>
      <span class="info-val" style="font-family:monospace;color:#00875a;"><?= htmlspecialchars($student['abha_id']) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">ABHA Address</span>
      <span class="info-val"><?= $student['abha_address'] ? htmlspecialchars($student['abha_address']) : '—' ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Status</span>
      <span>
        <?php if ($student['abha_verified']): ?>
          <span class="spill-verified"><i class="fas fa-shield-alt me-1"></i>Verified</span>
        <?php else: ?>
          <span class="spill-linked"><i class="fas fa-link me-1"></i>Linked</span>
        <?php endif; ?>
      </span>
    </div>
    <?php if ($student['abha_linked_at']): ?>
    <div class="info-row">
      <span class="info-label">Linked On</span>
      <span class="info-val"><?= date('d M Y', strtotime($student['abha_linked_at'])) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <!-- ═══ ABHA NOT LINKED ═══ -->
  <div class="abha-empty">
    <div style="width:60px;height:60px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.6rem;color:#00875a;">
      <i class="fas fa-id-card"></i>
    </div>
    <h5 style="color:#374151;font-size:.95rem;margin-bottom:6px;">ABHA Not Linked</h5>
    <p style="font-size:.78rem;color:#6b7280;margin-bottom:0;">
      Link or create your ABHA with OTP verification — instant, no admin wait needed.
    </p>
  </div>

  <?php if (ABDM_CONFIGURED): ?>
  <!-- ═══ ABDM LIVE OTP WIZARD ═══ -->
  <div class="req-form-card" id="abhaWizard">

    <div id="wAlertBox" style="display:none;" class="alert mb-3"></div>

    <div class="wiz-tabs">
      <button class="active" id="btnTabLink"   onclick="switchTab('link')"><i class="fas fa-link me-1"></i>Link Existing</button>
      <button id="btnTabCreate" onclick="switchTab('create')"><i class="fas fa-plus-circle me-1"></i>Create New</button>
    </div>

    <!-- Link Existing -->
    <div id="tabLink">
      <div id="stepL1">
        <p class="step-ind"><span class="cur">Step 1</span> of 2 — Enter ABHA ID</p>
        <div class="abha-preview-mini">
          <div style="font-size:.57rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px;">Preview</div>
          <div class="pnum" id="prev_num">XX-XXXX-XXXX-XXXX</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">ABHA Number <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="link_abha_in" placeholder="XX-XXXX-XXXX-XXXX"
            maxlength="19" oninput="fmtAbha(this,'prev_num')">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">Auth Method</label>
          <select class="form-select" id="link_auth_method" style="font-size:.83rem;">
            <option value="MOBILE_OTP">Mobile OTP</option>
            <option value="AADHAAR_OTP">Aadhaar OTP</option>
          </select>
        </div>
        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="initLink()">
          <i class="fas fa-arrow-right me-1"></i>Send OTP
        </button>
      </div>
      <div id="stepL2" style="display:none;">
        <p class="step-ind"><span class="cur">Step 2</span> of 2 — Verify OTP</p>
        <p id="linkOtpMsg" style="font-size:.8rem;color:#374151;margin-bottom:12px;"></p>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">6-digit OTP</label>
          <input type="text" class="form-control otp-big" id="link_otp_in"
            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" onclick="resetStep('link')"><i class="fas fa-arrow-left me-1"></i>Back</button>
          <button class="btn flex-fill fw-semibold btn-sm" style="background:#00875a;color:#fff;" onclick="confirmLinkOtp()">
            <i class="fas fa-check me-1"></i>Verify & Link
          </button>
        </div>
      </div>
    </div><!-- /tabLink -->

    <!-- Create New (Aadhaar M1) -->
    <div id="tabCreate" style="display:none;">
      <div id="stepC1">
        <p class="step-ind"><span class="cur">Step 1</span> of 3 — Aadhaar verification</p>
        <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;font-size:.74rem;color:#065f46;padding:8px 12px;">
          <i class="fas fa-shield-alt me-1"></i>Aadhaar is RSA-encrypted before sending. Never stored.
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">Aadhaar Number</label>
          <input type="text" class="form-control" id="create_aadhaar"
            placeholder="XXXX XXXX XXXX" maxlength="14" inputmode="numeric"
            oninput="this.value=this.value.replace(/\D/g,'').substring(0,12).replace(/(.{4})/g,'$1 ').trim()">
        </div>
        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="genAadhaarOtp()">
          <i class="fas fa-mobile-alt me-1"></i>Send OTP
        </button>
      </div>
      <div id="stepC2" style="display:none;">
        <p class="step-ind"><span class="cur">Step 2</span> of 3 — Verify OTP</p>
        <p id="createOtpMsg" style="font-size:.8rem;color:#374151;margin-bottom:12px;"></p>
        <div class="mb-3">
          <input type="text" class="form-control otp-big" id="create_aadhaar_otp"
            placeholder="• • • • • •" maxlength="6" inputmode="numeric">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" onclick="resetStep('create')"><i class="fas fa-arrow-left"></i></button>
          <button class="btn flex-fill btn-sm fw-semibold" style="background:#00875a;color:#fff;" onclick="verifyAadhaarOtp()">Verify OTP</button>
        </div>
      </div>
      <div id="stepC2b" style="display:none;">
        <p class="step-ind"><span class="cur">Step 2b</span> — Provide mobile number</p>
        <div class="mb-3">
          <div class="input-group">
            <span class="input-group-text">+91</span>
            <input type="text" class="form-control" id="create_mobile" placeholder="10-digit mobile" maxlength="10" inputmode="numeric">
          </div>
        </div>
        <button class="btn w-100 btn-sm fw-semibold" style="background:#00875a;color:#fff;" onclick="genLinkedMobileOtp()">Send Mobile OTP</button>
      </div>
      <div id="stepC2c" style="display:none;">
        <p class="step-ind"><span class="cur">Step 2c</span> — Verify mobile OTP</p>
        <div class="mb-3">
          <input type="text" class="form-control otp-big" id="create_mobile_otp" placeholder="• • • • • •" maxlength="6" inputmode="numeric">
        </div>
        <button class="btn w-100 btn-sm fw-semibold" style="background:#00875a;color:#fff;" onclick="verifyLinkedMobileOtp()">Verify OTP</button>
      </div>
      <div id="stepC3" style="display:none;">
        <p class="step-ind"><span class="cur">Step 3</span> of 3 — Create ABHA</p>
        <div class="abha-preview-mini mb-3">
          <div style="font-weight:700;font-size:.9rem;" id="create_profile_name">—</div>
          <div style="font-size:.73rem;opacity:.8;" id="create_profile_info">—</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem;">ABHA Address <small class="text-muted fw-normal">(optional)</small></label>
          <div class="input-group">
            <input type="text" class="form-control" id="create_abha_addr" placeholder="yourname">
            <span class="input-group-text" style="font-size:.83rem;">@abdm</span>
          </div>
        </div>
        <button class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;" onclick="createAbha()">
          <i class="fas fa-id-card me-1"></i>Create My ABHA
        </button>
      </div>
    </div><!-- /tabCreate -->

    <!-- Success -->
    <div id="stepSuccess" style="display:none;">
      <div class="abha-ok-card">
        <i class="fas fa-check-circle fa-2x mb-1"></i>
        <div style="font-size:.68rem;opacity:.8;text-transform:uppercase;letter-spacing:.08em;">ABHA Linked</div>
        <div class="num" id="success_abha_num">—</div>
        <div style="font-size:.77rem;opacity:.82;" id="success_abha_addr"></div>
        <div style="font-size:.7rem;opacity:.7;margin-top:6px;"><i class="fas fa-shield-alt me-1"></i>Verified by ABDM</div>
      </div>
      <button class="btn w-100 btn-sm" style="background:#0C74C5;color:#fff;" onclick="window.location.reload()">
        <i class="fas fa-sync me-1"></i>View ABHA Card
      </button>
    </div>

    <!-- Loader -->
    <div id="wLoader" style="display:none;text-align:center;padding:16px 0;">
      <div class="spinner-border text-success" style="width:1.8rem;height:1.8rem;"></div>
      <div style="font-size:.78rem;color:#6b7280;margin-top:6px;" id="wLoaderMsg">Please wait…</div>
    </div>

  </div><!-- /abhaWizard -->

  <?php else: ?>
  <!-- Fallback manual request -->
  <?php if ($pending_req): ?>
  <div class="info-card mb-3" style="border-left:4px solid #d97706;">
    <div class="d-flex align-items-center gap-2 mb-2">
      <i class="fas fa-hourglass-half text-warning"></i>
      <h6 class="mb-0" style="color:#92400e;">Link Request Pending</h6>
    </div>
    <div class="info-row"><span class="info-label">ABHA Submitted</span><span class="info-val" style="font-family:monospace;color:#00875a;"><?= htmlspecialchars($pending_req['abha_id']) ?></span></div>
    <div class="info-row"><span class="info-label">Submitted On</span><span class="info-val"><?= date('d M Y, h:i A', strtotime($pending_req['requested_at'])) ?></span></div>
    <div class="mt-3">
      <form method="POST">
        <input type="hidden" name="action" value="cancel_request">
        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel?')">
          <i class="fas fa-times me-1"></i>Cancel Request
        </button>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="req-form-card">
    <h6 class="fw-bold mb-1"><i class="fas fa-link me-2 text-success"></i>Request ABHA Linking</h6>
    <div class="abha-preview-mini">
      <div class="pnum" id="prev_num">XX-XXXX-XXXX-XXXX</div>
      <div style="font-size:.72rem;opacity:.8;margin-top:3px;" id="prev_addr">address@abdm</div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="request_link">
      <div class="mb-3">
        <input type="text" class="form-control" name="abha_id" id="abha_id_input"
          placeholder="XX-XXXX-XXXX-XXXX" maxlength="19" oninput="fmtAbha(this,'prev_num')" required>
      </div>
      <div class="mb-3">
        <div class="input-group">
          <input type="text" class="form-control" name="abha_address" id="abha_addr_input"
            placeholder="yourname" oninput="fmtAddr(this,'prev_addr')">
          <span class="input-group-text" style="font-size:.85rem;">@abdm</span>
        </div>
      </div>
      <button type="submit" class="btn w-100 fw-semibold" style="background:#00875a;color:#fff;">
        <i class="fas fa-paper-plane me-2"></i>Submit Link Request
      </button>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; /* ABDM_CONFIGURED */ ?>
  <?php endif; /* end abha_linked */ ?>

  <!-- ═══ Health Summary ═══ -->
  <?php if ($hp): ?>
  <div class="info-card">
    <h6><i class="fas fa-heartbeat me-2 text-danger"></i>Health Summary</h6>
    <div class="row g-2 mb-3">
      <?php
        $hs = [
          ['Height', $hp['height_cm'] ? $hp['height_cm'].' cm' : '—', 'fas fa-ruler-vertical', '#0C74C5'],
          ['Weight', $hp['weight_kg'] ? $hp['weight_kg'].' kg' : '—', 'fas fa-weight',          '#7c3aed'],
          ['Blood',  $hp['blood_group'] ?: '—',                        'fas fa-tint',             '#dc2626'],
          ['BMI',    $bmi ? $bmi.' ('.$bmi_lbl.')' : '—',             'fas fa-chart-bar',        $bmi_col],
        ];
        foreach ($hs as [$lbl, $val, $icon, $col]):
      ?>
      <div class="col-6">
        <div class="health-pill">
          <i class="<?= $icon ?> mb-1" style="color:<?= $col ?>;font-size:1.1rem;"></i>
          <div class="h-num" style="color:<?= $col ?>;font-size:.95rem;"><?= htmlspecialchars($val) ?></div>
          <div class="h-lbl"><?= $lbl ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($hp['known_allergies']): ?>
    <div class="info-row">
      <span class="info-label"><i class="fas fa-allergies me-1 text-warning"></i>Allergies</span>
      <span class="info-val" style="max-width:55%;word-break:break-word;"><?= htmlspecialchars($hp['known_allergies']) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($hp['chronic_conditions']): ?>
    <div class="info-row">
      <span class="info-label"><i class="fas fa-notes-medical me-1 text-danger"></i>Conditions</span>
      <span class="info-val" style="max-width:55%;word-break:break-word;"><?= htmlspecialchars($hp['chronic_conditions']) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($hp['is_vaccinated']): ?>
    <div class="info-row">
      <span class="info-label"><i class="fas fa-syringe me-1 text-success"></i>Vaccination</span>
      <span class="info-val"><span style="color:#16a34a;font-size:.78rem;"><i class="fas fa-check-circle me-1"></i>Vaccinated</span></span>
    </div>
    <?php endif; ?>
    <?php if ($hp['emergency_contact_name'] || $hp['emergency_contact']): ?>
    <?php $ec_name = $hp['emergency_contact_name'] ?: $hp['emergency_contact']; $ec_phone = $hp['emergency_contact_phone'] ?: $hp['emergency_phone']; ?>
    <div class="info-row">
      <span class="info-label"><i class="fas fa-phone-alt me-1 text-primary"></i>Emergency</span>
      <span class="info-val"><?= htmlspecialchars($ec_name) ?><?= $ec_phone ? ' · '.$ec_phone : '' ?></span>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="info-card text-center" style="color:#6b7280;">
    <i class="fas fa-heartbeat fa-2x mb-2" style="opacity:.3;"></i><br>
    <span style="font-size:.82rem;">Health profile not yet filled. Contact your school admin.</span>
  </div>
  <?php endif; ?>

  <!-- ═══ Request History ═══ -->
  <?php if ($req_history->num_rows > 0): ?>
  <div class="info-card">
    <h6><i class="fas fa-history me-2 text-muted"></i>Request History</h6>
    <?php while ($rh = $req_history->fetch_assoc()): ?>
    <div class="hist-item <?= strtolower($rh['status']) ?>">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div style="font-family:monospace;font-size:.82rem;font-weight:700;"><?= htmlspecialchars($rh['abha_id'] ?: '—') ?></div>
          <div style="font-size:.68rem;color:#6b7280;"><?= date('d M Y, h:i A', strtotime($rh['requested_at'])) ?></div>
        </div>
        <?php
          $sc = ['Approved'=>'spill-linked','Rejected'=>'spill-unlinked','Pending'=>'spill-pending'][$rh['status']] ?? 'spill-pending';
          $si = ['Approved'=>'check-circle','Rejected'=>'times-circle','Pending'=>'hourglass-half'][$rh['status']] ?? 'clock';
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

  <!-- ═══ About ABHA ═══ -->
  <div class="info-card" style="border-left:4px solid #00875a;">
    <h6><i class="fas fa-info-circle me-2 text-success"></i>What is ABHA?</h6>
    <p style="font-size:.77rem;color:#4b5563;line-height:1.6;margin-bottom:10px;">
      <strong>Ayushman Bharat Health Account (ABHA)</strong> is a 14-digit unique digital health ID issued by India's National Health Authority under the Ayushman Bharat Digital Mission (ABDM).
    </p>
    <ul style="font-size:.75rem;color:#6b7280;line-height:1.8;padding-left:18px;margin-bottom:10px;">
      <li>Links all your health records across hospitals & clinics digitally</li>
      <li>Enables paperless, consent-based sharing of health data</li>
      <li>Works with all ABDM-integrated healthcare providers</li>
      <li>Free to create at <a href="https://healthid.ndhm.gov.in/" target="_blank">healthid.ndhm.gov.in</a></li>
    </ul>
    <a href="https://healthid.ndhm.gov.in/" target="_blank" class="btn btn-sm w-100" style="background:#00875a;color:#fff;font-size:.78rem;">
      <i class="fas fa-external-link-alt me-2"></i>Create / View ABHA on ABDM Portal
    </a>
  </div>

</div><!-- /.s-body -->

<!-- Bottom nav -->
<nav class="s-bottomnav">
  <a href="dashboard.php"><i class="fas fa-home"></i>Home</a>
  <a href="health.php"><i class="fas fa-heartbeat"></i>Health</a>
  <a href="records.php"><i class="fas fa-file-medical"></i>Records</a>
  <a href="abha.php" class="active"><i class="fas fa-id-card"></i>ABHA</a>
  <a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Formatters ─────────────────────────────────────────── */
function fmtAbha(el, previewId) {
  let v = el.value.replace(/\D/g,'').substring(0,14);
  let out = v.length > 0 ? v.substring(0,2) : '';
  if (v.length > 2)  out += '-' + v.substring(2,6);
  if (v.length > 6)  out += '-' + v.substring(6,10);
  if (v.length > 10) out += '-' + v.substring(10,14);
  el.value = out;
  if (previewId) {
    const p = document.getElementById(previewId);
    if (p) p.textContent = out || 'XX-XXXX-XXXX-XXXX';
  }
}
function fmtAddr(el, previewId) {
  const addr = el.value.replace('@abdm','').trim();
  if (previewId) {
    const p = document.getElementById(previewId);
    if (p) p.textContent = addr ? addr+'@abdm' : 'address@abdm';
  }
}

<?php if (ABDM_CONFIGURED): ?>
/* ── ABDM Wizard (student portal) ──────────────────────── */
const AJAX_URL = '../../ajax/abdm-api.php';

async function abdmPost(action, body = {}) {
  setLoader(true);
  try {
    const r = await fetch(AJAX_URL, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action, ...body}),
    });
    return await r.json();
  } catch(e) {
    return {success:false, message:'Network error. Please try again.'};
  } finally {
    setLoader(false);
  }
}

function setLoader(show) {
  document.getElementById('wLoader').style.display = show ? 'block' : 'none';
}

function wAlert(msg, type = 'danger') {
  const b = document.getElementById('wAlertBox');
  b.className = 'alert alert-' + type + ' mb-3';
  b.textContent = msg;
  b.style.display = 'block';
  setTimeout(() => b.style.display='none', 7000);
}

function switchTab(tab) {
  document.getElementById('tabLink').style.display   = tab === 'link'   ? 'block' : 'none';
  document.getElementById('tabCreate').style.display = tab === 'create' ? 'block' : 'none';
  document.getElementById('btnTabLink').classList.toggle('active',   tab === 'link');
  document.getElementById('btnTabCreate').classList.toggle('active', tab === 'create');
}

function showCreate(step) {
  ['C1','C2','C2b','C2c','C3'].forEach(s => {
    const el = document.getElementById('step' + s);
    if (el) el.style.display = s === step ? 'block' : 'none';
  });
}

function resetStep(tab) {
  if (tab === 'link') {
    document.getElementById('stepL1').style.display = 'block';
    document.getElementById('stepL2').style.display = 'none';
  } else {
    showCreate('C1');
  }
  document.getElementById('wAlertBox').style.display = 'none';
}

/* Link existing */
async function initLink() {
  const abhaRaw = document.getElementById('link_abha_in').value.replace(/\D/g,'');
  const authMethod = document.getElementById('link_auth_method').value;
  if (abhaRaw.length !== 14) { wAlert('Enter valid 14-digit ABHA number'); return; }
  const res = await abdmPost('init_link', {
    abha_id: document.getElementById('link_abha_in').value,
    auth_method: authMethod
  });
  if (res.success) {
    const m = authMethod === 'AADHAAR_OTP' ? 'your Aadhaar mobile' : 'your registered mobile';
    document.getElementById('linkOtpMsg').textContent = `OTP sent to ${m}. Valid 10 min.`;
    document.getElementById('stepL1').style.display = 'none';
    document.getElementById('stepL2').style.display = 'block';
  } else { wAlert(res.message); }
}

async function confirmLinkOtp() {
  const otp = document.getElementById('link_otp_in').value.trim();
  if (otp.length < 6) { wAlert('Enter 6-digit OTP'); return; }
  const res = await abdmPost('confirm_link_otp', {otp});
  if (res.success) {
    document.getElementById('success_abha_num').textContent  = res.abha_id      || '';
    document.getElementById('success_abha_addr').textContent = res.abha_address || '';
    document.getElementById('stepL2').style.display    = 'none';
    document.getElementById('stepSuccess').style.display = 'block';
  } else { wAlert(res.message); }
}

/* Create new (Aadhaar M1) */
async function genAadhaarOtp() {
  const raw = document.getElementById('create_aadhaar').value.replace(/\D/g,'');
  if (raw.length !== 12) { wAlert('Enter 12-digit Aadhaar'); return; }
  const res = await abdmPost('gen_aadhaar_otp', {aadhaar: raw});
  if (res.success) {
    document.getElementById('createOtpMsg').textContent = `OTP sent to ${res.maskedMobile || 'your mobile'}. Valid 10 min.`;
    showCreate('C2');
  } else { wAlert(res.message); }
}

async function verifyAadhaarOtp() {
  const otp = document.getElementById('create_aadhaar_otp').value.trim();
  if (otp.length < 6) { wAlert('Enter 6-digit OTP'); return; }
  const res = await abdmPost('verify_aadhaar_otp', {otp});
  if (res.success) {
    if (!res.mobileLinked) {
      showCreate('C2b');
    } else {
      document.getElementById('create_profile_name').textContent = res.name || '—';
      document.getElementById('create_profile_info').textContent =
        [res.gender, res.yearOfBirth ? 'b. '+res.yearOfBirth : ''].filter(Boolean).join(' · ');
      showCreate('C3');
    }
  } else { wAlert(res.message); }
}

async function genLinkedMobileOtp() {
  const mobile = document.getElementById('create_mobile').value.replace(/\D/g,'');
  if (mobile.length !== 10) { wAlert('Enter valid 10-digit mobile'); return; }
  const res = await abdmPost('gen_linked_mobile_otp', {mobile});
  if (res.success) { showCreate('C2c'); }
  else { wAlert(res.message); }
}

async function verifyLinkedMobileOtp() {
  const otp = document.getElementById('create_mobile_otp').value.trim();
  if (otp.length < 6) { wAlert('Enter 6-digit OTP'); return; }
  const res = await abdmPost('verify_linked_mobile_otp', {otp});
  if (res.success) { showCreate('C3'); }
  else { wAlert(res.message); }
}

async function createAbha() {
  const addr = document.getElementById('create_abha_addr').value.trim();
  const res  = await abdmPost('create_abha', {abha_address: addr});
  if (res.success) {
    document.getElementById('success_abha_num').textContent  = res.abha_id      || '';
    document.getElementById('success_abha_addr').textContent = res.abha_address || '';
    ['C1','C2','C2b','C2c','C3'].forEach(s => {
      const el = document.getElementById('step'+s);
      if (el) el.style.display = 'none';
    });
    document.getElementById('stepSuccess').style.display = 'block';
  } else { wAlert(res.message); }
}

switchTab('link');
<?php endif; ?>
</script>
</body>
</html>
