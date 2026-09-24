<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/PatientHealthProfile.php';
require_once __DIR__ . '/../lib/Abha.php';
require_once __DIR__ . '/../config/abdm.php';
admin_jwt_guard();

$customer_id = intval($_GET['id'] ?? 0);

// Handle ABHA Link/Unlink from view-customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'link_user_abha') {
        $abha_raw = preg_replace('/\D/', '', trim($_POST['abha_number'] ?? ''));
        $abha_addr = trim($_POST['abha_address'] ?? '');
        $verified = isset($_POST['mark_verified']) ? 1 : 0;

        if (strlen($abha_raw) !== 14) {
            $_SESSION['error_message'] = "Invalid ABHA Number — must be exactly 14 numeric digits.";
        } else {
            $fmt = substr($abha_raw, 0, 2) . '-' . substr($abha_raw, 2, 4) . '-' . substr($abha_raw, 6, 4) . '-' . substr($abha_raw, 10, 4);
            if ($abha_addr && strpos($abha_addr, '@') === false) {
                $abha_addr .= '@abdm';
            }
            try {
                Abha::save($conn, 'patient', $customer_id, [
                    'abha_number'  => $fmt,
                    'abha_address' => $abha_addr,
                    'linked'       => 1,
                    'verified'     => $verified,
                    'source'       => 'admin',
                ]);
                $_SESSION['success_message'] = "ABHA ID {$fmt} successfully linked to patient!";
            } catch (Throwable $e) {
                $_SESSION['error_message'] = "Could not link ABHA: " . $e->getMessage();
            }
        }
        header("Location: view-customer.php?id={$customer_id}");
        exit();
    }

    if ($_POST['action'] === 'unlink_user_abha') {
        try {
            Abha::unlink($conn, 'patient', $customer_id);
            $_SESSION['success_message'] = "ABHA ID unlinked from patient.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = "Failed to unlink ABHA.";
        }
        header("Location: view-customer.php?id={$customer_id}");
        exit();
    }
}

// Fetch customer details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: all-customers.php");
    exit();
}

// Pending ABHA request if any
$pend_stmt = $conn->prepare("SELECT id, abha_id, abha_address, requested_at FROM user_abha_requests WHERE user_id = ? AND status = 'Pending' LIMIT 1");
$pend_stmt->bind_param('i', $customer_id);
$pend_stmt->execute();
$pending_req = $pend_stmt->get_result()->fetch_assoc();
$pend_stmt->close();

// Recent medical records
$docs_stmt = $conn->prepare("
    SELECT pd.*, d.name as doctor_name
    FROM patient_documents pd 
    LEFT JOIN doctors d ON d.id = pd.doctor_id
    WHERE pd.patient_id = ? 
    ORDER BY pd.uploaded_at DESC 
    LIMIT 15
");
$docs_stmt->bind_param('i', $customer_id);
$docs_stmt->execute();
$medical_records = $docs_stmt->get_result();

// Appointments & OPD Prescriptions
$appts_stmt = $conn->prepare("
    SELECT a.*, d.name AS doctor_name, d.specialization, p.id AS prescription_id
    FROM appointments a
    LEFT JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN prescriptions p ON p.appointment_id = a.id AND p.status = 'final'
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 15
");
$appts_stmt->bind_param('i', $customer_id);
$appts_stmt->execute();
$appointments = $appts_stmt->get_result();

// Lab Bookings
$labs_stmt = $conn->prepare("
    SELECT * FROM lab_bookings 
    WHERE user_id = ? 
    ORDER BY booking_date DESC, created_at DESC 
    LIMIT 15
");
$labs_stmt->bind_param('i', $customer_id);
$labs_stmt->execute();
$lab_bookings = $labs_stmt->get_result();

// Pharmacy Orders
$pharm_stmt = $conn->prepare("
    SELECT * FROM pharmacy_orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 15
");
$pharm_stmt->bind_param('i', $customer_id);
$pharm_stmt->execute();
$pharmacy_orders = $pharm_stmt->get_result();

// Calculate age from date of birth
$age = '';
if (!empty($customer['dob']) && $customer['dob'] != '0000-00-00') {
    $dob = new DateTime($customer['dob']);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
}

$health = PatientHealthProfile::get($conn, $customer_id);
$abha_formatted = Abha::formatNumber($customer['abha_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Patient 360° Profile & ABDM | Admin Dashboard</title>
    
    <?php include "links.php"; ?>
    <style>
        .customer-profile-header {
            background: linear-gradient(135deg, #0C74C5 0%, #084c82 100%);
            color: white;
            border-radius: 15px;
            padding: 26px 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(12,116,197,.15);
        }
        .abha-hero-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }
        .info-card {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            background: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }
        .info-card-header {
            background: #f8fafc;
            padding: 14px 18px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 700;
            color: #0C74C5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .info-card-body { padding: 18px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #495057; min-width: 140px; font-size: .86rem; }
        .detail-value { color: #1f2937; text-align: right; font-size: .88rem; font-weight: 500; }
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        .back-btn:hover { background: rgba(255,255,255,0.3); color: white; }
    </style>
</head>

<body class="crm_body_bg">

    <?php include "header.php"; ?>
    <section class="main_content dashboard_part large_header_bg">

        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Customer Profile Header Banner -->
                <div class="customer-profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center">
                                <div class="me-4 flex-shrink-0">
                                    <?php if (!empty($customer['profile_pic'])): ?>
                                        <img src="<?= BASE_URL . 'assets/img/' . $customer['profile_pic'] ?>"
                                             class="img-fluid rounded-circle" style="width: 76px; height: 76px; object-fit: cover; border: 3px solid rgba(255,255,255,0.5);"
                                             alt="<?= htmlspecialchars($customer['name']) ?>">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold fs-2" style="width: 76px; height: 76px;">
                                            <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h1 class="h3 mb-1 text-white fw-bold"><?= htmlspecialchars($customer['name'] . ' ' . $customer['last_name']) ?></h1>
                                    <p class="mb-1 opacity-90 small">Patient ID: #<?= $customer['id'] ?> • <?= htmlspecialchars($customer['email']) ?> • <?= htmlspecialchars($customer['mobile']) ?></p>
                                    <p class="mb-0 opacity-75 small"><i class="fas fa-calendar-alt me-1"></i>Member since <?= date('F j, Y', strtotime($customer['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                            <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                <a href="all-customers.php" class="btn back-btn btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Patients
                                </a>
                                <a href="edit-customer.php?id=<?= $customer['id'] ?>" class="btn btn-light btn-sm fw-bold">
                                    <i class="fas fa-edit me-1"></i> Edit Profile
                                </a>
                                <a href="upload-medical-record.php?for=patient&patient_id=<?= $customer['id'] ?>" class="btn btn-light btn-sm fw-bold">
                                    <i class="fas fa-upload me-1"></i> Upload Document
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABHA Digital Identity Box -->
                <div class="abha-hero-box">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:48px;height:48px;background:rgba(12,116,197,.1);color:var(--primary,#0C74C5);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="small fw-bold text-muted text-uppercase" style="letter-spacing:.05em;">Ayushman Bharat Digital Mission (ABDM)</span>
                                    <?php if (!empty($customer['abha_linked'])): ?>
                                        <span class="badge bg-success"><i class="fas fa-shield-alt me-1"></i>M1 Verified</span>
                                    <?php elseif ($pending_req): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Verification Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unlinked</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($customer['abha_linked']) && !empty($customer['abha_id'])): ?>
                                    <div class="fs-5 fw-bold font-monospace text-dark"><?= htmlspecialchars($abha_formatted) ?></div>
                                    <?php if (!empty($customer['abha_address'])): ?>
                                        <div class="small text-muted"><i class="fas fa-at me-1"></i><?= htmlspecialchars($customer['abha_address']) ?></div>
                                    <?php endif; ?>
                                <?php elseif ($pending_req): ?>
                                    <div class="text-dark small">
                                        Patient submitted ABHA ID: <strong><?= htmlspecialchars($pending_req['abha_id']) ?></strong>
                                        <?php if (!empty($pending_req['abha_address'])): ?> (<?= htmlspecialchars($pending_req['abha_address']) ?>)<?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small">No ABHA number is currently linked to this patient account.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ABHA Actions for Admin -->
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!empty($customer['abha_linked'])): ?>
                                <a href="<?= BASE_URL ?>ajax/abdm-api.php?action=get_card_file&user_id=<?= $customer['id'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="fas fa-download me-1"></i> Download ABHA Card (PDF)
                                </a>
                                <a href="<?= BASE_URL ?>ajax/abdm-api.php?action=get_card_png&user_id=<?= $customer['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-image me-1"></i> Print PNG
                                </a>
                                <form method="POST" action="view-customer.php?id=<?= $customer['id'] ?>" class="d-inline" onsubmit="return confirm('Unlink ABHA from this patient?');">
                                    <input type="hidden" name="action" value="unlink_user_abha">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-unlink me-1"></i> Unlink ABHA
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#linkAbhaModal">
                                    <i class="fas fa-link me-1"></i> Link ABHA ID to Patient
                                </button>
                                <?php if ($pending_req): ?>
                                    <a href="abha-management.php?tab=requests" class="btn btn-sm btn-warning">
                                        <i class="fas fa-tasks me-1"></i> Review Request in Registry
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="customerTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                            <i class="fas fa-user me-1"></i> Overview & Demographics
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button">
                            <i class="fas fa-heartbeat me-1"></i> ABDM PHR Health Profile
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="appts-tab" data-bs-toggle="tab" data-bs-target="#appts" type="button">
                            <i class="fas fa-stethoscope me-1"></i> Consultations & OPD Slips (<?= $appointments->num_rows ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="labs-tab" data-bs-toggle="tab" data-bs-target="#labs" type="button">
                            <i class="fas fa-flask me-1"></i> Lab Investigations (<?= $lab_bookings->num_rows ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="pharm-tab" data-bs-toggle="tab" data-bs-target="#pharm" type="button">
                            <i class="fas fa-pills me-1"></i> Pharmacy Orders (<?= $pharmacy_orders->num_rows ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button">
                            <i class="fas fa-folder-open me-1"></i> Documents (<?= $medical_records->num_rows ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="customerTabsContent">
                    
                    <!-- TAB 1: OVERVIEW -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <span><i class="fas fa-id-card me-2"></i>Personal Demographics</span>
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Full Name</span>
                                            <span class="detail-value fw-bold"><?= htmlspecialchars(trim($customer['name'] . ' ' . $customer['last_name'])) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile Number</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['mobile']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Email ID</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['email']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Gender</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['gender'] ?: 'Not specified') ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Date of Birth / Age</span>
                                            <span class="detail-value">
                                                <?= !empty($customer['dob']) && $customer['dob'] != '0000-00-00' ? date('d M Y', strtotime($customer['dob'])) : 'Not set' ?>
                                                <?= $age ? " ($age yrs)" : '' ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Blood Group</span>
                                            <span class="detail-value fw-bold text-danger"><?= htmlspecialchars($customer['blood_group'] ?: 'Not set') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <span><i class="fas fa-map-marker-alt me-2"></i>Permanent Address & Contact</span>
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Street / Colony</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['address'] ?: 'Not set') ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">City</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['city'] ?: 'Not set') ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">State</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['state'] ?: 'Not set') ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Postal Pincode</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['zip_code'] ?: 'Not set') ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Status</span>
                                            <span class="detail-value">
                                                <span class="badge <?= $customer['status'] === 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= $customer['status'] ?></span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Email Verified</span>
                                            <span class="detail-value">
                                                <span class="badge <?= $customer['email_verified'] ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $customer['email_verified'] ? 'Verified' : 'Unverified' ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: ABDM PHR HEALTH PROFILE -->
                    <div class="tab-pane fade" id="health" role="tabpanel">
                        <?php if (!$health): ?>
                            <div class="info-card">
                                <div class="info-card-body text-center py-5 text-muted">
                                    <i class="fas fa-heartbeat fa-3x mb-3 d-block opacity-25"></i>
                                    No ABDM health profile vitals registered yet for this patient.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="info-card-header"><span><i class="fas fa-stethoscope me-2"></i>Biometric Vitals</span></div>
                                        <div class="info-card-body">
                                            <div class="detail-row"><span class="detail-label">Height</span><span class="detail-value"><?= $health['height_cm'] ? $health['height_cm'] . ' cm' : '—' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Weight</span><span class="detail-value"><?= $health['weight_kg'] ? $health['weight_kg'] . ' kg' : '—' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">BMI</span><span class="detail-value"><?= $health['bmi'] ?: '—' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Blood Pressure</span><span class="detail-value"><?= $health['blood_pressure'] ?: '—' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Pulse Rate</span><span class="detail-value"><?= $health['pulse_rate'] ? $health['pulse_rate'] . ' /min' : '—' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Vision (L / R)</span><span class="detail-value"><?= ($health['vision_left'] ?: '—') . ' / ' . ($health['vision_right'] ?: '—') ?></span></div>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-card-header"><span><i class="fas fa-syringe me-2"></i>Vaccination Status</span></div>
                                        <div class="info-card-body">
                                            <div class="detail-row"><span class="detail-label">Vaccinated</span><span class="detail-value"><?= $health['is_vaccinated'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></span></div>
                                            <?php if (!empty($health['vaccination_details'])): ?>
                                                <div class="detail-row"><span class="detail-label">Vaccine Details</span><span class="detail-value"><?= htmlspecialchars($health['vaccination_details']) ?></span></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="info-card-header"><span><i class="fas fa-notes-medical me-2"></i>Clinical & Medical History</span></div>
                                        <div class="info-card-body">
                                            <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($health['known_allergies'] ?: 'None recorded') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Chronic Conditions</span><span class="detail-value"><?= htmlspecialchars($health['chronic_conditions'] ?: 'None recorded') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Current Medications</span><span class="detail-value"><?= htmlspecialchars($health['current_medications'] ?: 'None recorded') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Past Surgeries</span><span class="detail-value"><?= htmlspecialchars($health['past_surgeries'] ?: 'None recorded') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Disability</span><span class="detail-value"><?= htmlspecialchars($health['disability'] ?: 'None recorded') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Insurance Provider</span><span class="detail-value"><?= htmlspecialchars($health['insurance_provider'] ?: 'None') ?></span></div>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-card-header"><span><i class="fas fa-phone-alt me-2"></i>Emergency Contact</span></div>
                                        <div class="info-card-body">
                                            <div class="detail-row"><span class="detail-label">Contact Name</span><span class="detail-value"><?= htmlspecialchars($health['emergency_contact_name'] ?: 'Not set') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Contact Phone</span><span class="detail-value"><?= htmlspecialchars($health['emergency_contact_phone'] ?: 'Not set') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">Relation</span><span class="detail-value"><?= htmlspecialchars($health['emergency_contact_relation'] ?: 'Not set') ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 3: CONSULTATIONS & OPD SLIPS -->
                    <div class="tab-pane fade" id="appts" role="tabpanel">
                        <div class="white_card mb_30">
                            <div class="white_card_body pt-3">
                                <?php if ($appointments->num_rows === 0): ?>
                                    <div class="text-center py-4 text-muted">No consultations recorded for this patient.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Doctor</th>
                                                    <th>Specialization</th>
                                                    <th>Date & Time</th>
                                                    <th>Fee</th>
                                                    <th>Status</th>
                                                    <th class="text-end">OPD Prescription</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($ap = $appointments->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="fw-bold">Dr. <?= htmlspecialchars($ap['doctor_name'] ?? 'Doctor') ?></td>
                                                        <td class="text-muted small"><?= htmlspecialchars($ap['specialization'] ?? 'Specialist') ?></td>
                                                        <td><?= date('d M Y', strtotime($ap['appointment_date'])) ?> • <?= htmlspecialchars($ap['appointment_time']) ?></td>
                                                        <td class="fw-bold">₹<?= number_format($ap['consultation_fee'], 2) ?></td>
                                                        <td>
                                                            <span class="badge <?= $ap['status'] === 'approved' ? 'bg-success' : ($ap['status'] === 'completed' ? 'bg-primary' : 'bg-secondary') ?>">
                                                                <?= ucfirst($ap['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if (!empty($ap['prescription_id'])): ?>
                                                                <a href="<?= BASE_URL ?>doctor/opd-slip.php?appointment_id=<?= $ap['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-pdf me-1"></i> View OPD Slip
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="small text-muted">Pending Consultation</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: LAB INVESTIGATIONS -->
                    <div class="tab-pane fade" id="labs" role="tabpanel">
                        <div class="white_card mb_30">
                            <div class="white_card_body pt-3">
                                <?php if ($lab_bookings->num_rows === 0): ?>
                                    <div class="text-center py-4 text-muted">No diagnostic lab bookings recorded for this patient.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Booking UID</th>
                                                    <th>Booking Date</th>
                                                    <th>Collection Type</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Report</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($lb = $lab_bookings->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($lb['booking_uid']) ?></td>
                                                        <td><?= date('d M Y', strtotime($lb['booking_date'])) ?> (<?= htmlspecialchars($lb['time_slot']) ?>)</td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border">
                                                                <?= $lb['collection_type'] === 'home_collection' ? 'Home Sample' : 'Visit Center' ?>
                                                            </span>
                                                        </td>
                                                        <td class="fw-bold">₹<?= number_format($lb['total_amount'], 2) ?></td>
                                                        <td><span class="badge bg-info text-dark"><?= ucfirst(str_replace('_', ' ', $lb['status'])) ?></span></td>
                                                        <td class="text-end">
                                                            <?php if (!empty($lb['report_file'])): ?>
                                                                <a href="<?= BASE_URL . htmlspecialchars($lb['report_file']) ?>" target="_blank" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-download me-1"></i> Download Report
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="small text-muted">Processing</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: PHARMACY MEDICINE ORDERS -->
                    <div class="tab-pane fade" id="pharm" role="tabpanel">
                        <div class="white_card mb_30">
                            <div class="white_card_body pt-3">
                                <?php if ($pharmacy_orders->num_rows === 0): ?>
                                    <div class="text-center py-4 text-muted">No medicine orders recorded for this patient.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Order Number</th>
                                                    <th>Date</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                    <th>Tracking Number</th>
                                                    <th class="text-end">Prescription</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($po = $pharmacy_orders->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="fw-bold font-monospace"><?= htmlspecialchars($po['order_number']) ?></td>
                                                        <td><?= date('d M Y, h:i A', strtotime($po['created_at'])) ?></td>
                                                        <td class="fw-bold">₹<?= number_format($po['total_amount'], 2) ?></td>
                                                        <td><span class="badge bg-primary"><?= ucfirst($po['order_status']) ?></span></td>
                                                        <td><?= !empty($po['tracking_number']) ? htmlspecialchars($po['tracking_number']) : '—' ?></td>
                                                        <td class="text-end">
                                                            <?php if (!empty($po['prescription_file'])): ?>
                                                                <a href="<?= BASE_URL . htmlspecialchars($po['prescription_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-pdf me-1"></i> Prescription
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="small text-muted">Direct Order</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 6: MEDICAL DOCUMENTS -->
                    <div class="tab-pane fade" id="docs" role="tabpanel">
                        <div class="white_card mb_30">
                            <div class="white_card_body pt-3">
                                <?php if ($medical_records->num_rows === 0): ?>
                                    <div class="text-center py-4 text-muted">No documents uploaded for this patient.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Document Name</th>
                                                    <th>Doctor / Uploader</th>
                                                    <th>Date</th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($doc = $medical_records->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="fw-bold"><?= htmlspecialchars($doc['document_name']) ?></td>
                                                        <td class="text-muted small">
                                                            <?= $doc['doctor_name'] ? 'Dr. ' . htmlspecialchars($doc['doctor_name']) : 'Admin / Clinic' ?>
                                                        </td>
                                                        <td><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></td>
                                                        <td class="text-end">
                                                            <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye me-1"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /tab-content -->

            </div>
        </div>

        <!-- Modal: Link ABHA -->
        <div class="modal fade" id="linkAbhaModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="view-customer.php?id=<?= $customer['id'] ?>" class="modal-content">
                    <input type="hidden" name="action" value="link_user_abha">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-id-card me-2 text-primary"></i>Link ABHA to <?= htmlspecialchars($customer['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">14-Digit ABHA Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" name="abha_number" id="viewAbhaInput" placeholder="91-XXXX-XXXX-XXXX" maxlength="17" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ABHA Address / PHR Address</label>
                            <input type="text" class="form-control" name="abha_address" placeholder="username@abdm or username@sbx">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="mark_verified" id="viewMarkVerified" value="1" checked>
                            <label class="form-check-label fw-bold" for="viewMarkVerified">
                                Mark as ABDM M1 Verified
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> Link ABHA</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script>
            // Auto-format 14-digit ABHA input
            var abhaInp = document.getElementById('viewAbhaInput');
            if (abhaInp) {
                abhaInp.addEventListener('input', function() {
                    var v = this.value.replace(/\D/g, '').slice(0, 14);
                    if (v.length > 10) {
                        this.value = v.slice(0, 2) + '-' + v.slice(2, 6) + '-' + v.slice(6, 10) + '-' + v.slice(10, 14);
                    } else if (v.length > 6) {
                        this.value = v.slice(0, 2) + '-' + v.slice(2, 6) + '-' + v.slice(6);
                    } else if (v.length > 2) {
                        this.value = v.slice(0, 2) + '-' + v.slice(2);
                    } else {
                        this.value = v;
                    }
                });
            }

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(a) {
                    var bsAlert = new bootstrap.Alert(a);
                    bsAlert.close();
                });
            }, 5000);
        </script>
</body>
</html>