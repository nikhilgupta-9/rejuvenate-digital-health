<?php
/**
 * Admin Panel — ABDM Milestone 1 (M1) Operations Console
 *
 * Government of India National Health Authority (NHA) ABDM Milestone 1:
 * - ABHA Issuance / Creation via Aadhaar OTP (Enrollment)
 * - ABHA Verification & KYC Authentication (Number / Handle / Mobile / Aadhaar)
 * - Official ABHA Card Viewer & Instant Download (PDF & PNG)
 * - Walk-in Patient Registration & System Linking
 */

require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/Abha.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../config/abdm.php';
admin_jwt_guard();

$csrfToken = Security::csrfToken();
$isSandbox = defined('ABDM_ENV') && ABDM_ENV === 'sandbox';

// Quick stats for M1 coverage
$statPatients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total, SUM(abha_linked=1) as linked, SUM(abha_verified=1) as verified FROM users WHERE status='Active'"));
$statMembers  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total, SUM(abha_linked=1) as linked, SUM(abha_verified=1) as verified FROM school_members WHERE status='Active'"));

$totAll = (int)($statPatients['total'] ?? 0) + (int)($statMembers['total'] ?? 0);
$lnkAll = (int)($statPatients['linked'] ?? 0) + (int)($statMembers['linked'] ?? 0);
$verAll = (int)($statPatients['verified'] ?? 0) + (int)($statMembers['verified'] ?? 0);
$pctAll = $totAll > 0 ? round($verAll / $totAll * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ABDM Milestone 1 (M1) — ABHA Hub | Admin Panel</title>
    <?php include "links.php"; ?>
    <style>
        :root {
            --m1-primary: #0C74C5;
            --m1-green: #00875A;
            --m1-saffron: #FF7A00;
        }

        .m1-hero {
            background: linear-gradient(135deg, #0C74C5 0%, #00875A 100%);
            border-radius: 14px;
            color: #fff;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 14px rgba(12, 116, 197, 0.15);
        }

        .m1-hero .hero-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .m1-stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
            border-left: 4px solid var(--m1-primary);
        }

        .m1-nav-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .m1-tab-btn {
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 10px 20px;
            font-weight: 600;
            font-size: .92rem;
            color: #64748b;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all .2s ease;
        }

        .m1-tab-btn:hover {
            color: var(--m1-primary);
        }

        .m1-tab-btn.active {
            color: var(--m1-primary);
            border-bottom-color: var(--m1-primary);
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
        }

        /* Step wizard styles */
        .step-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 28px;
        }

        .step-tracker::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 20px;
            right: 20px;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-node {
            position: relative;
            z-index: 2;
            background: #fff;
            padding: 0 8px;
            text-align: center;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            font-weight: 700;
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            border: 2px solid #cbd5e1;
            transition: all .2s ease;
        }

        .step-node.active .step-circle {
            background: var(--m1-primary);
            color: #fff;
            border-color: var(--m1-primary);
            box-shadow: 0 0 0 4px rgba(12, 116, 197, 0.2);
        }

        .step-node.completed .step-circle {
            background: var(--m1-green);
            color: #fff;
            border-color: var(--m1-green);
        }

        .step-label {
            font-size: .75rem;
            font-weight: 600;
            color: #64748b;
        }

        .step-node.active .step-label {
            color: var(--m1-primary);
        }

        .step-node.completed .step-label {
            color: var(--m1-green);
        }

        /* Suggestion pills */
        .sug-pill {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            margin: 3px;
            transition: all .15s;
        }

        .sug-pill:hover, .sug-pill.selected {
            background: #00875A;
            color: #fff;
            border-color: #00875A;
        }

        /* Official ABHA Card mockup */
        .abha-card-wrap {
            max-width: 520px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #cbd5e1;
            margin: 0 auto;
        }

        .card-tricolor {
            height: 6px;
            background: linear-gradient(90deg, #FF7A00 33.3%, #ffffff 33.3%, #ffffff 66.6%, #138808 66.6%);
            border-bottom: 1px solid #e2e8f0;
        }

        .card-topbar {
            background: #f8fafc;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body class="crm_body_bg">
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <!-- ── Hero Banner ── -->
                <div class="m1-hero">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="hero-icon">
                                <i class="fa-solid fa-id-card-clip"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <h4 class="mb-0 text-white fw-bold">ABDM Milestone 1 (M1) — ABHA Central Hub</h4>
                                    <span class="badge bg-light text-dark fw-bold px-2 py-1" style="font-size:.7rem;">
                                        <i class="fa-solid fa-circle-check text-success me-1"></i>M1 CERTIFIED
                                    </span>
                                    <?php if ($isSandbox): ?>
                                        <span class="badge bg-warning text-dark px-2 py-1" style="font-size:.7rem;">
                                            <i class="fa-solid fa-flask me-1"></i>Sandbox Mode
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-0 text-white-50" style="font-size:.84rem;">
                                    Issue new ABHA digital health IDs via official Aadhaar OTP, verify existing ABHA accounts, discover patient profiles, and generate compliant NHA ABHA cards.
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if ($isSandbox): ?>
                                <button type="button" class="btn btn-sm btn-light fw-bold text-dark shadow-sm" onclick="runSandboxM1Simulation()">
                                    <i class="fa-solid fa-flask text-warning me-1"></i>Simulate M1 Test
                                </button>
                            <?php endif; ?>
                            <a href="abha-management.php" class="btn btn-sm btn-outline-light">
                                <i class="fa-solid fa-table-list me-1"></i>ABHA Dashboard &amp; Records
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ── Quick Metrics Row ── -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="m1-stat-card" style="border-left-color:#00875A;">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Verified ABHA Accounts</div>
                            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($verAll) ?></div>
                            <div class="text-muted mt-1" style="font-size:.74rem;">
                                <span class="text-success fw-bold"><i class="fa-solid fa-shield-halved me-1"></i><?= $pctAll ?>%</span> verified in ABDM registry
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="m1-stat-card" style="border-left-color:#0C74C5;">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Patients with ABHA</div>
                            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($statPatients['linked'] ?? 0) ?></div>
                            <div class="text-muted mt-1" style="font-size:.74rem;">
                                <?= number_format($statPatients['verified'] ?? 0) ?> verified with official OTP
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="m1-stat-card" style="border-left-color:#7c3aed;">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">School Health ABHA</div>
                            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($statMembers['linked'] ?? 0) ?></div>
                            <div class="text-muted mt-1" style="font-size:.74rem;">
                                <?= number_format($statMembers['verified'] ?? 0) ?> students / staff verified
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="m1-stat-card" style="border-left-color:#f59e0b;">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">ABDM Milestones Interlink</div>
                            <div class="d-flex gap-2 mt-2">
                                <a href="hip-linking.php" class="badge bg-primary text-white text-decoration-none">M2 HIP Link</a>
                                <a href="scan-share.php" class="badge bg-success text-white text-decoration-none">M3 Scan &amp; Share</a>
                                <a href="hpr-verification.php" class="badge bg-info text-white text-decoration-none">M4 HPR</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Interactive Module Tabs ── -->
                <div class="card border-0 shadow-sm" style="border-radius:14px;">
                    <div class="card-body p-4">
                        <div class="m1-nav-tabs">
                            <button type="button" class="m1-tab-btn active" id="tabBtnCreate" onclick="switchM1Tab('create')">
                                <i class="fa-solid fa-user-plus"></i> Create New ABHA (M1 Enrollment)
                            </button>
                            <button type="button" class="m1-tab-btn" id="tabBtnVerify" onclick="switchM1Tab('verify')">
                                <i class="fa-solid fa-shield-halved"></i> Verify &amp; Link Existing ABHA
                            </button>
                            <button type="button" class="m1-tab-btn" id="tabBtnCard" onclick="switchM1Tab('card')">
                                <i class="fa-solid fa-id-card"></i> Official ABHA Card Generator
                            </button>
                        </div>

                        <!-- ══════════════════════════════════════════════════
                             TAB 1: CREATE NEW ABHA (AADHAAR OTP WIZARD)
                             ══════════════════════════════════════════════════ -->
                        <div id="m1TabCreate">
                            <div class="step-tracker">
                                <div class="step-node active" id="trackNode1">
                                    <div class="step-circle" id="trackCirc1">1</div>
                                    <div class="step-label">Aadhaar</div>
                                </div>
                                <div class="step-node" id="trackNode2">
                                    <div class="step-circle" id="trackCirc2">2</div>
                                    <div class="step-label">Aadhaar OTP</div>
                                </div>
                                <div class="step-node" id="trackNode3">
                                    <div class="step-circle" id="trackCirc3">3</div>
                                    <div class="step-label">ABHA Address</div>
                                </div>
                                <div class="step-node" id="trackNode4">
                                    <div class="step-circle" id="trackCirc4">4</div>
                                    <div class="step-label">ABHA Card &amp; Profile</div>
                                </div>
                            </div>

                            <div id="m1CreateAlert" style="display:none;" class="alert mb-3"></div>

                            <!-- Wizard Step 1: Aadhaar Input -->
                            <div id="m1CreateStep1">
                                <div class="row justify-content-center">
                                    <div class="col-lg-7">
                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                            <div class="fw-bold mb-1 text-dark" style="font-size:.92rem;">
                                                <i class="fa-solid fa-fingerprint text-primary me-2"></i>Enter Patient Aadhaar Number
                                            </div>
                                            <p class="text-muted mb-0" style="font-size:.78rem;">
                                                A 6-digit OTP will be securely dispatched by the UIDAI gateway to the mobile number registered with this Aadhaar.
                                            </p>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold" style="font-size:.85rem;">
                                                12-Digit Aadhaar Number <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-lg text-center font-monospace"
                                                   id="create_aadhaar" placeholder="•••• •••• ••••" maxlength="14"
                                                   inputmode="numeric" style="letter-spacing:4px;font-size:1.3rem;">
                                            <small class="text-muted">Aadhaar number is used strictly for one-time OTP authentication and is never stored on our servers.</small>
                                        </div>

                                        <div class="form-check p-3 rounded-2 mb-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                            <input class="form-check-input" type="checkbox" id="create_consent" checked>
                                            <label class="form-check-label text-dark" for="create_consent" style="font-size:.8rem;line-height:1.45;">
                                                <strong>Patient Informed Consent:</strong> The patient has explicitly authorized the healthcare provider to use their Aadhaar number for UIDAI biometric/OTP authentication to generate or verify an Ayushman Bharat Health Account (ABHA).
                                            </label>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnCreateSendOtp" onclick="m1CreateSendOtp()">
                                                <i class="fa-solid fa-paper-plane me-1"></i> Send Aadhaar OTP
                                            </button>
                                            <?php if ($isSandbox): ?>
                                                <button type="button" class="btn btn-outline-warning" onclick="m1FillSandboxAadhaar()">
                                                    <i class="fa-solid fa-flask me-1"></i> Auto-fill Sandbox Test ID
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Wizard Step 2: OTP Verification -->
                            <div id="m1CreateStep2" style="display:none;">
                                <div class="row justify-content-center">
                                    <div class="col-lg-6">
                                        <div class="alert alert-info py-2 px-3 mb-3" style="font-size:.82rem;" id="createOtpNotice">
                                            <i class="fa-solid fa-mobile-screen me-2"></i>OTP sent to Aadhaar-registered mobile number.
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold" style="font-size:.85rem;">Enter 6-Digit Aadhaar OTP <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg text-center font-monospace"
                                                   id="create_otp" placeholder="• • • • • •" maxlength="6" inputmode="numeric"
                                                   style="letter-spacing:6px;font-size:1.4rem;">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold" style="font-size:.85rem;">Communication Mobile Number <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">+91</span>
                                                <input type="text" class="form-control" id="create_mobile" placeholder="10-digit mobile number" maxlength="10" inputmode="numeric">
                                            </div>
                                            <small class="text-muted">Used for healthcare notifications &amp; OPD receipts.</small>
                                        </div>

                                        <div class="d-flex align-items-center gap-2 mt-4">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="m1CreateBack(1)">
                                                <i class="fa-solid fa-arrow-left me-1"></i> Back
                                            </button>
                                            <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnCreateVerifyOtp" onclick="m1CreateVerifyOtp()">
                                                <i class="fa-solid fa-check me-1"></i> Verify OTP &amp; Continue
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Wizard Step 3: Set ABHA Address -->
                            <div id="m1CreateStep3" style="display:none;">
                                <div class="row justify-content-center">
                                    <div class="col-lg-7">
                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                            <div class="fw-bold mb-1 text-dark" style="font-size:.92rem;">
                                                <i class="fa-solid fa-at text-success me-2"></i>Choose Patient ABHA Address
                                            </div>
                                            <p class="text-muted mb-0" style="font-size:.78rem;">
                                                An ABHA address (e.g. <code>name@abdm</code>) is like an email address for health records, allowing patients to share lab reports and prescriptions seamlessly.
                                            </p>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold" style="font-size:.82rem;">Suggested Available Handles:</label>
                                            <div id="createSuggestionsList" class="mb-2"></div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold" style="font-size:.85rem;">Custom or Selected ABHA Handle:</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="create_chosen_address" placeholder="john.doe">
                                                <span class="input-group-text fw-semibold"><?= $isSandbox ? '@sbx' : '@abdm' ?></span>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-2 mt-4">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="m1CreateBack(2)">
                                                <i class="fa-solid fa-arrow-left me-1"></i> Back
                                            </button>
                                            <button type="button" class="btn btn-success px-4 fw-semibold" id="btnCreateSetAddress" onclick="m1CreateSetAddress()">
                                                <i class="fa-solid fa-check-circle me-1"></i> Confirm ABHA Address
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Wizard Step 4: Success & Profile Card -->
                            <div id="m1CreateStep4" style="display:none;">
                                <div class="alert alert-success d-flex align-items-center justify-content-between p-3 mb-4" style="border-radius:10px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-circle-check fa-2x text-success"></i>
                                        <div>
                                            <strong class="d-block" style="font-size:1rem;">ABHA Successfully Issued &amp; Verified!</strong>
                                            <span style="font-size:.78rem;">The patient's digital health identity is active in the National Health Authority registry.</span>
                                        </div>
                                    </div>
                                    <span class="badge bg-success px-3 py-2 fs-6">M1 ACTIVE</span>
                                </div>

                                <div class="row g-4 align-items-center">
                                    <div class="col-lg-6">
                                        <!-- ABHA Card Preview -->
                                        <div class="abha-card-wrap">
                                            <div class="card-tricolor"></div>
                                            <div class="card-topbar">
                                                <div>
                                                    <div class="fw-bold" style="font-size:.75rem;letter-spacing:.5px;color:#0f2240;">GOVERNMENT OF INDIA</div>
                                                    <div style="font-size:.65rem;color:#64748b;">National Health Authority • ABDM</div>
                                                </div>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.68rem;">ABHA M1</span>
                                            </div>
                                            <div class="p-3">
                                                <div class="d-flex gap-3">
                                                    <div style="width:72px;height:84px;border-radius:6px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                                                        <img id="cardPreviewPhoto" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:none;">
                                                        <i class="fa-solid fa-user fa-2x text-muted" id="cardPreviewFallback"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold fs-6 text-dark" id="cardPreviewName">—</div>
                                                        <div class="d-flex align-items-center gap-2 mt-1">
                                                            <span class="font-monospace fw-bold text-success" id="cardPreviewNum" style="font-size:1.1rem;letter-spacing:1px;">—</span>
                                                            <button type="button" class="btn btn-sm btn-link p-0 text-muted" onclick="copyText('cardPreviewNum')" title="Copy ABHA Number">
                                                                <i class="fa-regular fa-copy"></i>
                                                            </button>
                                                        </div>
                                                        <div class="font-monospace text-primary fw-semibold mt-1" id="cardPreviewAddr" style="font-size:.82rem;">—</div>
                                                        <div class="text-muted mt-2" style="font-size:.74rem;" id="cardPreviewMeta">—</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="p-2 bg-light text-center border-top" style="font-size:.7rem;color:#64748b;">
                                                <i class="fa-solid fa-shield-halved text-success me-1"></i>Digitally certified via Aadhaar KYC under ABDM guidelines
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="card border-0 bg-light p-3 rounded-3 mb-3">
                                            <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Patient Integration</h6>
                                            <p class="text-muted" style="font-size:.78rem;">
                                                The patient record is saved with verified ABHA status. You can download the physical ABHA card for the patient or register them for an appointment.
                                            </p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btnDownloadCardPdf" onclick="m1DownloadCard('pdf')">
                                                    <i class="fa-solid fa-file-pdf me-1"></i> Download ABHA Card (PDF)
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" id="btnDownloadCardPng" onclick="m1DownloadCard('png')">
                                                    <i class="fa-solid fa-image me-1"></i> Download ABHA Card (PNG)
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="m1ResetCreateWizard()">
                                                <i class="fa-solid fa-plus me-1"></i> Create Another ABHA
                                            </button>
                                            <a href="abha-management.php?portal=patients" class="btn btn-outline-dark btn-sm">
                                                <i class="fa-solid fa-users me-1"></i> View in Patients List
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══════════════════════════════════════════════════
                             TAB 2: VERIFY & LINK EXISTING ABHA
                             ══════════════════════════════════════════════════ -->
                        <div id="m1TabVerify" style="display:none;">
                            <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <div id="m1VerifyAlert" style="display:none;" class="alert mb-3"></div>

                                    <div class="p-3 bg-light rounded-3 mb-4 border">
                                        <div class="fw-bold mb-1 text-dark" style="font-size:.92rem;">
                                            <i class="fa-solid fa-shield-halved text-success me-2"></i>Authenticate &amp; Verify Existing ABHA
                                        </div>
                                        <p class="text-muted mb-0" style="font-size:.78rem;">
                                            Verify an existing patient's ABHA identity against the ABDM National Registry via Aadhaar or Mobile OTP. Upon verification, the verified badge is applied across all patient records and OPD receipts.
                                        </p>
                                    </div>

                                    <!-- Verification Method Selector -->
                                    <div class="d-flex gap-2 mb-3 flex-wrap">
                                        <button type="button" class="btn btn-outline-primary active flex-fill p-2 text-start" id="vTypeBtnNum" onclick="switchVerifyType('number')">
                                            <div class="fw-bold" style="font-size:.82rem;"><i class="fa-solid fa-hashtag me-1"></i>ABHA Number</div>
                                            <small class="text-muted">14-digit digital ID</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary flex-fill p-2 text-start" id="vTypeBtnAddr" onclick="switchVerifyType('address')">
                                            <div class="fw-bold" style="font-size:.82rem;"><i class="fa-solid fa-at me-1"></i>ABHA Address</div>
                                            <small class="text-muted">e.g. name@abdm</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary flex-fill p-2 text-start" id="vTypeBtnMob" onclick="switchVerifyType('mobile')">
                                            <div class="fw-bold" style="font-size:.82rem;"><i class="fa-solid fa-mobile-screen me-1"></i>Mobile OTP</div>
                                            <small class="text-muted">Find all accounts on phone</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary flex-fill p-2 text-start" id="vTypeBtnAadhaar" onclick="switchVerifyType('aadhaar')">
                                            <div class="fw-bold" style="font-size:.82rem;"><i class="fa-solid fa-fingerprint me-1"></i>Aadhaar OTP</div>
                                            <small class="text-muted">UIDAI authenticated</small>
                                        </button>
                                    </div>

                                    <!-- Input Form -->
                                    <div id="vFormStep1">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold" id="vInputLabel" style="font-size:.85rem;">
                                                Enter 14-Digit ABHA Number <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-lg font-monospace"
                                                   id="vInputVal" placeholder="XX-XXXX-XXXX-XXXX">
                                            <small class="text-muted" id="vInputHelp">Enter 14 digits with or without hyphens.</small>
                                        </div>

                                        <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnVerifySendOtp" onclick="m1VerifySendOtp()">
                                            <i class="fa-solid fa-magnifying-glass me-1"></i> Verify with ABDM
                                        </button>
                                    </div>

                                    <!-- OTP Prompt for Verify -->
                                    <div id="vFormStep2" style="display:none;" class="mt-4 pt-3 border-top">
                                        <div class="alert alert-info py-2 px-3 mb-3" style="font-size:.82rem;" id="vOtpMsg">
                                            <i class="fa-solid fa-shield me-2"></i>Enter OTP to confirm verification.
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold" style="font-size:.85rem;">Enter 6-Digit OTP</label>
                                            <input type="text" class="form-control text-center font-monospace"
                                                   id="vOtpInput" placeholder="• • • • • •" maxlength="6" inputmode="numeric"
                                                   style="letter-spacing:6px;font-size:1.3rem;">
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="m1ResetVerify()">
                                                <i class="fa-solid fa-arrow-left me-1"></i> Back
                                            </button>
                                            <button type="button" class="btn btn-success fw-semibold" id="btnVerifyConfirmOtp" onclick="m1VerifyConfirmOtp()">
                                                <i class="fa-solid fa-check me-1"></i> Confirm &amp; Link Profile
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Verification Success Result -->
                                    <div id="vFormStep3" style="display:none;" class="mt-4 pt-3 border-top">
                                        <div class="card border-0 shadow-sm p-3" style="background:#f0fdf4;border-left:4px solid #16a34a !important;">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                                                    <span class="fw-bold text-success fs-6" id="vResultStatus">ABHA Profile Verified</span>
                                                </div>
                                                <span class="badge bg-success">ABDM ACTIVE</span>
                                            </div>
                                            <div class="row g-2 mt-1" style="font-size:.83rem;">
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block" style="font-size:.7rem;">Name</span>
                                                    <strong class="text-dark" id="vResultName">—</strong>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block" style="font-size:.7rem;">ABHA Number</span>
                                                    <span class="font-monospace fw-bold text-dark" id="vResultNum">—</span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block" style="font-size:.7rem;">ABHA Address</span>
                                                    <span class="font-monospace text-primary" id="vResultAddr">—</span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block" style="font-size:.7rem;">Patient ID</span>
                                                    <span class="text-dark" id="vResultPid">—</span>
                                                </div>
                                            </div>
                                            <div class="mt-3 pt-2 border-top d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="m1DownloadCard('pdf')">
                                                    <i class="fa-solid fa-download me-1"></i> Download ABHA Card
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="m1ResetVerify()">
                                                    <i class="fa-solid fa-magnifying-glass me-1"></i> Verify Another
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══════════════════════════════════════════════════
                             TAB 3: OFFICIAL ABHA CARD GENERATOR & VIEWER
                             ══════════════════════════════════════════════════ -->
                        <div id="m1TabCard" style="display:none;">
                            <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <div class="p-3 bg-light rounded-3 mb-3 border">
                                        <div class="fw-bold mb-1 text-dark" style="font-size:.92rem;">
                                            <i class="fa-solid fa-id-card text-primary me-2"></i>Official NHA ABHA Card Generator (PNG &amp; PDF)
                                        </div>
                                        <p class="text-muted mb-0" style="font-size:.78rem;">
                                            Enter any Patient ID, Student Member ID, or ABHA Number to view and print their high-resolution official ABHA card with NHA tricolor branding and scan-and-share QR code.
                                        </p>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" id="cardLookupQuery" placeholder="Search by Patient Name, ID, or ABHA Number...">
                                        </div>
                                        <div class="col-sm-4">
                                            <button type="button" class="btn btn-primary w-100 fw-semibold" onclick="m1LookupCard()">
                                                <i class="fa-solid fa-eye me-1"></i> Generate Card
                                            </button>
                                        </div>
                                    </div>

                                    <div id="cardLookupResult" class="text-center py-4">
                                        <div class="text-muted" style="font-size:.85rem;">
                                            <i class="fa-solid fa-id-card-clip fa-3x text-muted mb-2 d-block opacity-50"></i>
                                            Enter an identifier above or click "Generate Card" to view the official card.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>
</div>

<script>
const M1_API = '<?= BASE_URL ?>admin/api/abdm-m1-api.php';
const CSRF_TOKEN = '<?= $csrfToken ?>';

let currentM1TxnId = '';
let currentM1XToken = '';
let currentM1Profile = null;

// Tab switcher
function switchM1Tab(tab) {
    document.getElementById('m1TabCreate').style.display = (tab === 'create') ? 'block' : 'none';
    document.getElementById('m1TabVerify').style.display = (tab === 'verify') ? 'block' : 'none';
    document.getElementById('m1TabCard').style.display   = (tab === 'card')   ? 'block' : 'none';

    document.getElementById('tabBtnCreate').className = 'm1-tab-btn ' + (tab === 'create' ? 'active' : '');
    document.getElementById('tabBtnVerify').className = 'm1-tab-btn ' + (tab === 'verify' ? 'active' : '');
    document.getElementById('tabBtnCard').className   = 'm1-tab-btn ' + (tab === 'card'   ? 'active' : '');
}

function showCreateAlert(msg, type = 'danger') {
    const el = document.getElementById('m1CreateAlert');
    el.className = 'alert alert-' + type + ' mb-3';
    el.innerHTML = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 7000);
}

function showVerifyAlert(msg, type = 'danger') {
    const el = document.getElementById('m1VerifyAlert');
    el.className = 'alert alert-' + type + ' mb-3';
    el.innerHTML = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 7000);
}

function apiPost(action, payload) {
    return fetch(M1_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action: action, _csrf: CSRF_TOKEN }, payload || {}))
    }).then(r => r.json());
}

/* ══════════════════════════════════════════════════
   CREATE WIZARD LOGIC
   ══════════════════════════════════════════════════ */
const createAadhaarInput = document.getElementById('create_aadhaar');
if (createAadhaarInput) {
    createAadhaarInput.addEventListener('input', function () {
        const d = this.value.replace(/\D/g, '').slice(0, 12);
        this.value = d.replace(/(\d{4})(\d{0,4})(\d{0,4})/, (_, a, b, c) => [a, b, c].filter(Boolean).join(' '));
    });
}

function m1FillSandboxAadhaar() {
    createAadhaarInput.value = '9999 8888 7777';
    document.getElementById('create_consent').checked = true;
}

function m1CreateSendOtp() {
    const aadhaar = createAadhaarInput.value.replace(/\D/g, '');
    const consent = document.getElementById('create_consent').checked;

    if (aadhaar.length !== 12) {
        showCreateAlert('Please enter a valid 12-digit Aadhaar number.');
        return;
    }
    if (!consent) {
        showCreateAlert('Patient consent is required to send Aadhaar OTP.');
        return;
    }

    const btn = document.getElementById('btnCreateSendOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Requesting UIDAI OTP…';

    apiPost('create_send_otp', { aadhaar: aadhaar, consent: 1 })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Send Aadhaar OTP';
            if (res.success) {
                currentM1TxnId = res.txnId;
                document.getElementById('createOtpNotice').innerHTML = '<i class="fa-solid fa-mobile-screen me-2"></i>' + (res.message || 'OTP sent to Aadhaar-registered mobile number.');
                m1SetWizardStep(2);
                document.getElementById('create_otp').focus();
            } else {
                showCreateAlert(res.message || 'Failed to send Aadhaar OTP.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Send Aadhaar OTP';
            showCreateAlert('Network error while requesting OTP.');
        });
}

function m1CreateVerifyOtp() {
    const otp = document.getElementById('create_otp').value.replace(/\D/g, '');
    const mobile = document.getElementById('create_mobile').value.replace(/\D/g, '');

    if (otp.length !== 6) {
        showCreateAlert('Please enter a 6-digit OTP.');
        return;
    }
    if (mobile.length !== 10) {
        showCreateAlert('Please enter a valid 10-digit communication mobile number.');
        return;
    }

    const btn = document.getElementById('btnCreateVerifyOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Verifying with ABDM…';

    apiPost('create_verify_otp', { otp: otp, txnId: currentM1TxnId, mobile: mobile })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Verify OTP &amp; Continue';
            if (res.success) {
                currentM1Profile = res.profile;
                currentM1XToken = res.xToken || '';
                currentM1TxnId = res.txnId || currentM1TxnId;

                // Render suggestions
                const sugDiv = document.getElementById('createSuggestionsList');
                sugDiv.innerHTML = '';
                const sugs = res.suggestions || [];
                if (sugs.length === 0) {
                    const fallbackName = (res.profile.name || 'user').toLowerCase().replace(/[^a-z0-9]/g, '');
                    sugs.push(fallbackName + Math.floor(Math.random() * 899 + 100));
                }
                sugs.forEach(s => {
                    const span = document.createElement('span');
                    span.className = 'sug-pill';
                    span.textContent = s;
                    span.onclick = function() {
                        document.querySelectorAll('.sug-pill').forEach(p => p.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('create_chosen_address').value = s;
                    };
                    sugDiv.appendChild(span);
                });

                document.getElementById('create_chosen_address').value = sugs[0] || '';
                m1SetWizardStep(3);
            } else {
                showCreateAlert(res.message || 'OTP verification failed.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Verify OTP &amp; Continue';
            showCreateAlert('Network error while verifying OTP.');
        });
}

function m1CreateSetAddress() {
    const addr = document.getElementById('create_chosen_address').value.trim();
    if (!addr) {
        showCreateAlert('Please select or type an ABHA handle.');
        return;
    }

    const btn = document.getElementById('btnCreateSetAddress');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Setting Address…';

    apiPost('create_set_address', { txnId: currentM1TxnId, abha_address: addr, xToken: currentM1XToken })
        .then(res => {
            if (res.success) {
                if (currentM1Profile) currentM1Profile.abha_address = res.abha_address;
                // Finalize patient save in database
                return apiPost('create_confirm_patient', { profile: currentM1Profile });
            } else {
                throw new Error(res.message || 'Could not set this address.');
            }
        })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Confirm ABHA Address';
            if (res && res.success) {
                m1RenderStep4(res);
                m1SetWizardStep(4);
            } else {
                showCreateAlert(res.message || 'Failed confirming patient record.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Confirm ABHA Address';
            showCreateAlert(err.message || 'Error configuring ABHA address.');
        });
}

function m1RenderStep4(data) {
    const prof = currentM1Profile || data.profile || {};
    document.getElementById('cardPreviewName').textContent = prof.name || 'ABHA Holder';
    document.getElementById('cardPreviewNum').textContent  = data.abha_number || prof.abha_number || '—';
    document.getElementById('cardPreviewAddr').textContent = data.abha_address || prof.abha_address || '—';
    document.getElementById('cardPreviewMeta').textContent = [prof.gender, prof.dob ? 'DOB: ' + prof.dob : '', prof.mobile ? 'Mob: ' + prof.mobile : ''].filter(Boolean).join(' · ');
}

function m1SetWizardStep(step) {
    for (let i = 1; i <= 4; i++) {
        document.getElementById('m1CreateStep' + i).style.display = (i === step) ? 'block' : 'none';
        const node = document.getElementById('trackNode' + i);
        if (i < step) {
            node.className = 'step-node completed';
            document.getElementById('trackCirc' + i).innerHTML = '<i class="fa-solid fa-check"></i>';
        } else if (i === step) {
            node.className = 'step-node active';
            document.getElementById('trackCirc' + i).textContent = i;
        } else {
            node.className = 'step-node';
            document.getElementById('trackCirc' + i).textContent = i;
        }
    }
}

function m1CreateBack(step) {
    m1SetWizardStep(step);
}

function m1ResetCreateWizard() {
    currentM1TxnId = '';
    currentM1XToken = '';
    currentM1Profile = null;
    createAadhaarInput.value = '';
    document.getElementById('create_otp').value = '';
    document.getElementById('create_mobile').value = '';
    document.getElementById('create_chosen_address').value = '';
    m1SetWizardStep(1);
}

/* ══════════════════════════════════════════════════
   VERIFY EXISTING ABHA LOGIC
   ══════════════════════════════════════════════════ */
let verifyMode = 'number';
let verifyTxnId = '';

function switchVerifyType(type) {
    verifyMode = type;
    ['Num', 'Addr', 'Mob', 'Aadhaar'].forEach(k => {
        const btn = document.getElementById('vTypeBtn' + k);
        if (btn) btn.className = 'btn btn-outline-secondary flex-fill p-2 text-start';
    });

    const activeMap = {
        number: { btn: 'vTypeBtnNum', label: 'Enter 14-Digit ABHA Number', placeholder: 'XX-XXXX-XXXX-XXXX', help: '14-digit national digital health ID' },
        address: { btn: 'vTypeBtnAddr', label: 'Enter ABHA Address Handle', placeholder: 'username@abdm', help: 'Official ABDM address handle' },
        mobile: { btn: 'vTypeBtnMob', label: 'Enter 10-Digit Mobile Number', placeholder: '9876543210', help: 'Discovers all ABHA accounts registered under this mobile number' },
        aadhaar: { btn: 'vTypeBtnAadhaar', label: 'Enter 12-Digit Aadhaar Number', placeholder: '•••• •••• ••••', help: 'Aadhaar-linked verification' }
    };

    const cfg = activeMap[type] || activeMap.number;
    document.getElementById(cfg.btn).className = 'btn btn-outline-primary active flex-fill p-2 text-start';
    document.getElementById('vInputLabel').innerHTML = cfg.label + ' <span class="text-danger">*</span>';
    document.getElementById('vInputVal').placeholder = cfg.placeholder;
    document.getElementById('vInputHelp').textContent = cfg.help;
    document.getElementById('vInputVal').value = '';
}

function m1VerifySendOtp() {
    const val = document.getElementById('vInputVal').value.trim();
    if (!val) {
        showVerifyAlert('Please enter your ABHA identifier.');
        return;
    }

    const btn = document.getElementById('btnVerifySendOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Checking Registry…';

    apiPost('verify_send_otp', { type: verifyMode, input: val })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> Verify with ABDM';
            if (res.success) {
                if (res.isAddressOnly) {
                    // Address verified directly without OTP
                    document.getElementById('vFormStep1').style.display = 'none';
                    document.getElementById('vFormStep3').style.display = 'block';
                    document.getElementById('vResultStatus').textContent = 'ABHA Address Active & Verified';
                    document.getElementById('vResultNum').textContent = res.abha_number || 'Linked';
                    document.getElementById('vResultAddr').textContent = res.abha_address;
                    document.getElementById('vResultName').textContent = res.name || 'ABHA Holder';
                    document.getElementById('vResultPid').textContent = 'Verified in ABDM';
                } else {
                    verifyTxnId = res.txnId;
                    document.getElementById('vFormStep1').style.display = 'none';
                    document.getElementById('vFormStep2').style.display = 'block';
                    document.getElementById('vOtpMsg').textContent = res.message || 'OTP sent successfully.';
                    document.getElementById('vOtpInput').focus();
                }
            } else {
                showVerifyAlert(res.message || 'Could not initiate verification.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> Verify with ABDM';
            showVerifyAlert('Network error during verification.');
        });
}

function m1VerifyConfirmOtp() {
    const otp = document.getElementById('vOtpInput').value.replace(/\D/g, '');
    if (otp.length !== 6) {
        showVerifyAlert('Please enter a 6-digit OTP.');
        return;
    }

    const btn = document.getElementById('btnVerifyConfirmOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Verifying…';

    apiPost('verify_confirm_otp', { otp: otp, txnId: verifyTxnId })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Confirm &amp; Link Profile';
            if (res.success) {
                document.getElementById('vFormStep2').style.display = 'none';
                document.getElementById('vFormStep3').style.display = 'block';
                const p = res.profile || {};
                document.getElementById('vResultStatus').textContent = 'ABHA Authenticated & Verified';
                document.getElementById('vResultName').textContent = p.name || 'ABHA Holder';
                document.getElementById('vResultNum').textContent  = res.abha_number || p.abha_number || '—';
                document.getElementById('vResultAddr').textContent = res.abha_address || p.abha_address || '—';
                document.getElementById('vResultPid').textContent  = '#' + res.patient_id + (res.is_new ? ' (New Patient Created)' : ' (Existing Linked)');
                currentM1Profile = p;
            } else {
                showVerifyAlert(res.message || 'OTP verification failed.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Confirm &amp; Link Profile';
            showVerifyAlert('Network error while verifying OTP.');
        });
}

function m1ResetVerify() {
    verifyTxnId = '';
    document.getElementById('vInputVal').value = '';
    document.getElementById('vOtpInput').value = '';
    document.getElementById('vFormStep1').style.display = 'block';
    document.getElementById('vFormStep2').style.display = 'none';
    document.getElementById('vFormStep3').style.display = 'none';
}

/* ══════════════════════════════════════════════════
   CARD GENERATION & DOWNLOAD
   ══════════════════════════════════════════════════ */
function m1DownloadCard(format = 'pdf') {
    const btn = (format === 'pdf') ? document.getElementById('btnDownloadCardPdf') : document.getElementById('btnDownloadCardPng');
    if (btn) btn.disabled = true;

    apiPost('get_card', { format: format, card_data: currentM1Profile })
        .then(res => {
            if (btn) btn.disabled = false;
            if (res.success && res.data) {
                const link = document.createElement('a');
                link.href = 'data:' + res.mimeType + ';base64,' + res.data;
                link.download = res.filename || ('ABHA-Card.' + format);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                alert(res.message || 'Could not download card.');
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            alert('Error downloading ABHA card.');
        });
}

function m1LookupCard() {
    const q = document.getElementById('cardLookupQuery').value.trim();
    if (!q) {
        alert('Please enter a Patient Name, ID, or ABHA Number.');
        return;
    }

    const resDiv = document.getElementById('cardLookupResult');
    resDiv.innerHTML = '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i><div class="mt-2 text-muted">Generating card preview...</div>';

    apiPost('get_card', { format: 'png', patient_id: parseInt(q) || 0, card_data: { abha_number: q, name: q } })
        .then(res => {
            if (res.success && res.data) {
                resDiv.innerHTML = `
                    <div class="d-inline-block text-center shadow-lg rounded-3 p-2 bg-white border">
                        <img src="data:${res.mimeType};base64,${res.data}" class="img-fluid rounded-2 mb-3" style="max-width:480px;" alt="ABHA Card">
                        <div class="d-flex justify-content-center gap-2">
                            <a href="data:${res.mimeType};base64,${res.data}" download="${res.filename}" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-download me-1"></i> Download PNG
                            </a>
                        </div>
                    </div>
                `;
            } else {
                resDiv.innerHTML = '<div class="alert alert-warning py-2 px-3 d-inline-block"><i class="fa-solid fa-circle-exclamation me-1"></i> ' + (res.message || 'Patient or ABHA not found.') + '</div>';
            }
        })
        .catch(() => {
            resDiv.innerHTML = '<div class="alert alert-danger py-2 px-3 d-inline-block">Network error generating card.</div>';
        });
}

function runSandboxM1Simulation() {
    if (!confirm('Run 1-click ABDM Milestone 1 Simulation? This will simulate instant Aadhaar enrollment & generate a verified test patient.')) {
        return;
    }
    apiPost('sandbox_simulate_m1', { name: 'Aarav Sharma' })
        .then(res => {
            if (res.success) {
                currentM1Profile = res.profile;
                switchM1Tab('create');
                m1RenderStep4(res);
                m1SetWizardStep(4);
            } else {
                alert(res.message || 'Simulation failed.');
            }
        })
        .catch(() => alert('Network error running simulation.'));
}

function copyText(id) {
    const el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent.trim());
    alert('Copied: ' + el.textContent.trim());
}
</script>
</body>
</html>
