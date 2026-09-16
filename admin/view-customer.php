<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/PatientHealthProfile.php';
admin_jwt_guard();

$customer_id = intval($_GET['id'] ?? 0);

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

// Recent medical records for this patient
$docs_stmt = $conn->prepare("SELECT pd.*, d.name as doctor_name
    FROM patient_documents pd LEFT JOIN doctors d ON d.id = pd.doctor_id
    WHERE pd.patient_id = ? ORDER BY pd.uploaded_at DESC LIMIT 10");
$docs_stmt->bind_param('i', $customer_id);
$docs_stmt->execute();
$medical_records = $docs_stmt->get_result();
$medical_records_count = $medical_records->num_rows;

// Calculate age from date of birth
$age = '';
if (!empty($customer['dob']) && $customer['dob'] != '0000-00-00') {
    $dob = new DateTime($customer['dob']);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
}

$health = PatientHealthProfile::get($conn, $customer_id);
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Customer Details | Admin Dashboard</title>
    
    <?php include "links.php"; ?>
    <style>
        .customer-profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255,255,255,0.3);
        }
        .info-card {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .info-card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c5aa0;
        }
        .info-card-body {
            padding: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }
        .detail-value {
            color: #6c757d;
            text-align: right;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-active {
            background-color: #e6f7ee;
            color: #28a745;
        }
        .badge-inactive {
            background-color: #fef0f0;
            color: #dc3545;
        }
        .badge-blocked {
            background-color: #fff3cd;
            color: #856404;
        }
        .badge-verified {
            background-color: #e6f7ee;
            color: #28a745;
        }
        .badge-unverified {
            background-color: #fff3cd;
            color: #856404;
        }
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
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
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <!-- Customer Profile Header -->
                        <div class="customer-profile-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center">
                                        <div class="me-4">
                                            <?php if (!empty($customer['profile_pic'])) { ?>
                                                        <img src="<?= BASE_URL . 'assets/img/' . $customer['profile_pic']  ?>"
                                                            class="img-fluid rounded-circle" style="width: 80px; height: 80px; object-fit: cover;"

                                                            alt="<?= htmlspecialchars($customer['name']) ?>">
                                                    <?php } else { ?>
                                                        <i class="fas fa-user-circle" style="font-size: 80px; color: #ccc;"></i>
                                                    <?php } ?>
                                        </div>
                                        <div>
                                            <h1 class="h2 mb-2"><?= htmlspecialchars($customer['name'] . ' ' . $customer['last_name']) ?></h1>
                                            <p class="mb-1 opacity-75">Customer ID: #<?= $customer['id'] ?></p>
                                            <p class="mb-0 opacity-75">Member since <?= date('F j, Y', strtotime($customer['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <div class="d-flex flex-column gap-2">
                                        <a href="all-customers.php" class="btn back-btn">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Customers
                                        </a>
                                        <a href="edit-customer.php?id=<?= $customer['id'] ?>" class="btn btn-light">
                                            <i class="fas fa-edit me-2"></i>Edit Customer
                                        </a>
                                        <a href="upload-medical-record.php?for=patient&patient_id=<?= $customer['id'] ?>" class="btn btn-light">
                                            <i class="fas fa-file-medical me-2"></i>Upload Medical Record
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-4" id="customerTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                                    <i class="fas fa-user me-1"></i> Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button">
                                    <i class="fas fa-heart-pulse me-1"></i> Health Profile
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="customerTabsContent">
                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-user me-2"></i>Personal Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Full Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['name'] . ' ' . $customer['last_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Email</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['email']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['mobile']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Date of Birth</span>
                                            <span class="detail-value">
                                                <?= !empty($customer['dob']) && $customer['dob'] != '0000-00-00' ? date('M j, Y', strtotime($customer['dob'])) : 'Not set' ?>
                                                <?= $age ? " ($age years)" : '' ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Gender</span>
                                            <span class="detail-value"><?= $customer['gender'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Blood Group</span>
                                            <span class="detail-value"><?= $customer['blood_group'] ?: 'Not set' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-cog me-2"></i>Account Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Account Status</span>
                                            <span class="detail-value">
                                                <span class="status-badge badge-<?= strtolower($customer['status']) ?>">
                                                    <?= $customer['status'] ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Email Verification</span>
                                            <span class="detail-value">
                                                <span class="status-badge <?= $customer['email_verified'] ? 'badge-verified' : 'badge-unverified' ?>">
                                                    <?= $customer['email_verified'] ? 'Verified' : 'Unverified' ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile Verification</span>
                                            <span class="detail-value">
                                                <span class="status-badge <?= $customer['mobile_verified'] ? 'badge-verified' : 'badge-unverified' ?>">
                                                    <?= $customer['mobile_verified'] ? 'Verified' : 'Unverified' ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Last Login</span>
                                            <span class="detail-value">
                                                <?= !empty($customer['last_login']) ? date('M j, Y g:i A', strtotime($customer['last_login'])) : 'Never logged in' ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Created</span>
                                            <span class="detail-value">
                                                <?= date('M j, Y g:i A', strtotime($customer['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Last Updated</span>
                                            <span class="detail-value">
                                                <?= date('M j, Y g:i A', strtotime($customer['updated_at'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-map-marker-alt me-2"></i>Address Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Address</span>
                                            <span class="detail-value"><?= $customer['address'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">City</span>
                                            <span class="detail-value"><?= $customer['city'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">State</span>
                                            <span class="detail-value"><?= $customer['state'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Zip Code</span>
                                            <span class="detail-value"><?= $customer['zip_code'] ?: 'Not set' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-id-card me-2"></i>Additional Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Identification Type</span>
                                            <span class="detail-value"><?= $customer['identification_type'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Identification Number</span>
                                            <span class="detail-value"><?= $customer['identification_number'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Emergency Contact</span>
                                            <span class="detail-value"><?= $customer['emergency_contact'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Remember Token</span>
                                            <span class="detail-value">
                                                <?= $customer['remember_token'] ? substr($customer['remember_token'], 0, 10) . '...' : 'Not set' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Medical Records -->
                            <div class="col-md-12">
                                <div class="info-card">
                                    <div class="info-card-header d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-file-medical me-2"></i>Medical Records <span class="badge bg-secondary ms-1"><?= $medical_records_count ?></span></span>
                                        <a href="upload-medical-record.php?for=patient&patient_id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-upload me-1"></i> Upload
                                        </a>
                                    </div>
                                    <div class="info-card-body p-0">
                                        <?php if ($medical_records_count === 0): ?>
                                            <p class="text-muted text-center py-4 mb-0">No medical records uploaded yet.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="font-size:.75rem;text-transform:uppercase;">Document</th>
                                                            <th style="font-size:.75rem;text-transform:uppercase;">Uploaded By</th>
                                                            <th style="font-size:.75rem;text-transform:uppercase;">Date</th>
                                                            <th style="font-size:.75rem;text-transform:uppercase;">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php while ($doc = $medical_records->fetch_assoc()): ?>
                                                        <tr>
                                                            <td style="font-size:.85rem;"><?= htmlspecialchars($doc['document_name']) ?></td>
                                                            <td style="font-size:.82rem;">
                                                                <?= $doc['doctor_name'] ? 'Dr. ' . htmlspecialchars($doc['doctor_name']) : '<span class="text-muted">Admin</span>' ?>
                                                            </td>
                                                            <td><small class="text-muted"><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></small></td>
                                                            <td>
                                                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php if ($medical_records_count >= 10): ?>
                                                <div class="text-center py-2">
                                                    <a href="medical-records.php?tab=patients&q=<?= urlencode($customer['name']) ?>" class="small">View all records &rarr;</a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Appointments -->
                            <div class="col-md-12">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <span><i class="fas fa-calendar-check me-2"></i>Appointments</span>
                                    </div>
                                    <div class="info-card-body p-2">
                                        <?php
                                        $ap_scope = 'user';
                                        $ap_id = (int) $customer['id'];
                                        $ap_limit = 10;
                                        include __DIR__ . '/inc/appointments-panel.php';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div><!-- /overview tab-pane -->

                        <!-- Health Profile Tab -->
                        <div class="tab-pane fade" id="health" role="tabpanel">
                            <div class="d-flex justify-content-end mb-3">
                                <a href="edit-customer.php?id=<?= $customer['id'] ?>#health" class="btn btn-sm btn-primary"><i class="fas fa-edit me-1"></i>Edit Health Profile</a>
                            </div>
                            <?php if (!$health): ?>
                                <div class="info-card">
                                    <div class="info-card-body text-center py-5 text-muted">
                                        <i class="fas fa-heart-pulse fa-3x mb-3 d-block opacity-25"></i>
                                        No health profile set up yet for this patient.<br>
                                        <a href="edit-customer.php?id=<?= $customer['id'] ?>#health">Add one now &rarr;</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="info-card-header"><i class="fas fa-heart-pulse me-2"></i>Vitals</div>
                                            <div class="info-card-body">
                                                <div class="detail-row"><span class="detail-label">Height</span><span class="detail-value"><?= $health['height_cm'] !== null ? $health['height_cm'] . ' cm' : 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Weight</span><span class="detail-value"><?= $health['weight_kg'] !== null ? $health['weight_kg'] . ' kg' : 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">BMI</span><span class="detail-value"><?= $health['bmi'] !== null ? $health['bmi'] : 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= $health['blood_group'] ?: 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Blood Pressure</span><span class="detail-value"><?= $health['blood_pressure'] ?: 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Pulse Rate</span><span class="detail-value"><?= $health['pulse_rate'] !== null ? $health['pulse_rate'] . ' /min' : 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Vision (L / R)</span><span class="detail-value"><?= ($health['vision_left'] ?: '—') . ' / ' . ($health['vision_right'] ?: '—') ?></span></div>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-card-header"><i class="fas fa-syringe me-2"></i>Vaccination</div>
                                            <div class="info-card-body">
                                                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">
                                                    <span class="status-badge <?= $health['is_vaccinated'] ? 'badge-verified' : 'badge-unverified' ?>"><?= $health['is_vaccinated'] ? 'Vaccinated' : 'Not vaccinated' ?></span>
                                                </span></div>
                                                <?php if ($health['vaccination_details']): ?><div class="detail-row"><span class="detail-label">Details</span><span class="detail-value"><?= nl2br(htmlspecialchars($health['vaccination_details'])) ?></span></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-card-header"><i class="fas fa-phone me-2"></i>Emergency Contact</div>
                                            <div class="info-card-body">
                                                <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?= htmlspecialchars($health['emergency_contact_name'] ?: 'Not set') ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($health['emergency_contact_phone'] ?: 'Not set') ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Relation</span><span class="detail-value"><?= htmlspecialchars($health['emergency_contact_relation'] ?: 'Not set') ?></span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="info-card-header"><i class="fas fa-notes-medical me-2"></i>Medical History</div>
                                            <div class="info-card-body">
                                                <div class="detail-row"><span class="detail-label">Known Allergies</span><span class="detail-value"><?= $health['known_allergies'] ? nl2br(htmlspecialchars($health['known_allergies'])) : 'None recorded' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Chronic Conditions</span><span class="detail-value"><?= $health['chronic_conditions'] ? nl2br(htmlspecialchars($health['chronic_conditions'])) : 'None recorded' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Current Medications</span><span class="detail-value"><?= $health['current_medications'] ? nl2br(htmlspecialchars($health['current_medications'])) : 'None recorded' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Past Surgeries</span><span class="detail-value"><?= $health['past_surgeries'] ? nl2br(htmlspecialchars($health['past_surgeries'])) : 'None recorded' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Disability</span><span class="detail-value"><?= $health['disability'] ? nl2br(htmlspecialchars($health['disability'])) : 'None recorded' ?></span></div>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-card-header"><i class="fas fa-calendar-check me-2"></i>Checkup Schedule</div>
                                            <div class="info-card-body">
                                                <div class="detail-row"><span class="detail-label">Last Checkup</span><span class="detail-value"><?= $health['last_checkup_date'] ? date('d M Y', strtotime($health['last_checkup_date'])) : 'Not set' ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Next Checkup</span><span class="detail-value"><?= $health['next_checkup_date'] ? date('d M Y', strtotime($health['next_checkup_date'])) : 'Not set' ?></span></div>
                                                <?php if ($health['checkup_notes']): ?><div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value"><?= nl2br(htmlspecialchars($health['checkup_notes'])) ?></span></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-card-header"><i class="fas fa-shield-halved me-2"></i>Insurance</div>
                                            <div class="info-card-body">
                                                <div class="detail-row"><span class="detail-label">Provider</span><span class="detail-value"><?= htmlspecialchars($health['insurance_provider'] ?: 'Not set') ?></span></div>
                                                <div class="detail-row"><span class="detail-label">Policy Number</span><span class="detail-value"><?= htmlspecialchars($health['insurance_number'] ?: 'Not set') ?></span></div>
                                            </div>
                                        </div>

                                        <div class="text-muted" style="font-size:.75rem;padding:4px 2px;">
                                            <i class="fas fa-clock me-1"></i>Last updated <?= date('d M Y, h:i A', strtotime($health['updated_at'])) ?> by <?= ucfirst($health['last_updated_role'] ?: 'unknown') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div><!-- /health tab-pane -->
                        </div><!-- /tab-content -->

                        <!-- Action Buttons -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="all-customers.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Customers
                                        </a>
                                    </div>
                                    <div>
                                        <a href="edit-customer.php?id=<?= $customer['id'] ?>" class="btn btn-primary me-2">
                                            <i class="fas fa-edit me-2"></i>Edit Customer
                                        </a>
                                        <a href="all-customers.php?delete=<?= $customer['id'] ?>"
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                            <i class="fas fa-trash me-2"></i>Delete Customer
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
        
        <script>
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        </script>
</body>

</html>