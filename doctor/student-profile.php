<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$member_id = (int) ($_GET['id'] ?? 0);
if (!$member_id) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

// Require a recent OTP-verified identity check (set by doctor/api/school-lookup-verify.php)
$verified_until = $_SESSION['school_verified_members'][$member_id] ?? 0;
if ($verified_until < time()) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php?err=verify_required');
    exit;
}

$stmt = $conn->prepare("SELECT m.*, s.school_name, s.id as school_id FROM school_members m JOIN schools s ON s.id = m.school_id WHERE m.id = ? LIMIT 1");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$age = '';
if (!empty($m['dob'])) {
    try {
        $age = (new DateTime($m['dob']))->diff(new DateTime())->y;
    } catch (Exception $e) {
    }
}

// Health profile (editable by the doctor)
$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id = ?");
$hp_stmt->bind_param('i', $member_id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

$bmi = null;
if (!empty($hp['height_cm']) && !empty($hp['weight_kg'])) {
    $bmi = round($hp['weight_kg'] / (($hp['height_cm'] / 100) ** 2), 1);
}

$doc_types = ['Lab Report', 'Diagnostic Report', 'X-Ray / Imaging', 'Vaccination Certificate', 'Medical Certificate', 'Discharge Summary', 'Other'];

// Prescriptions written for this member
$rx_stmt = $conn->prepare("SELECT p.*, d.name as doctor_name FROM school_member_prescriptions p
    LEFT JOIN doctors d ON d.id = p.doctor_id WHERE p.member_id = ? ORDER BY p.created_at DESC LIMIT 50");
$rx_stmt->bind_param('i', $member_id);
$rx_stmt->execute();
$prescriptions = $rx_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Documents uploaded for this member
$doc_stmt = $conn->prepare("SELECT sd.*, d.name as doctor_name, au.first_name as admin_first, au.last_name as admin_last
    FROM school_member_documents sd
    LEFT JOIN doctors d ON d.id = sd.uploaded_by_doctor_id
    LEFT JOIN admin_user au ON au.id = sd.uploaded_by_admin_id
    WHERE sd.member_id = ? ORDER BY sd.uploaded_at DESC LIMIT 100");
$doc_stmt->bind_param('i', $member_id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Medical / leave certificates issued for this member
$cert_stmt = $conn->prepare("SELECT c.*, d.name as doctor_name FROM school_member_certificates c
    LEFT JOIN doctors d ON d.id = c.doctor_id WHERE c.member_id = ? ORDER BY c.created_at DESC LIMIT 50");
$cert_stmt->bind_param('i', $member_id);
$cert_stmt->execute();
$certificates = $cert_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$certificate_types = ['Medical Leave Certificate', 'Fitness / Fit-to-Join Certificate', 'Medical Certificate'];

$sidebar_active = 'school-students';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($m['name']) ?> — Student Profile</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/style.css">
    <style>
        .profile-header {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            padding: 22px 24px;
            margin-bottom: 16px;
        }

        .p-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #0277bd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .p-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .p-sub {
            font-size: .84rem;
            color: #6b7280;
        }

        .rx-item,
        .doc-item {
            background: #fff;
            border: 1px solid #eef0f3;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
        }

        .rx-item .rx-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .rx-item .rx-text {
            white-space: pre-wrap;
            font-size: .85rem;
            color: #374151;
            background: #f9fafb;
            border-radius: 8px;
            padding: 10px 12px;
        }

        /* ── Tabs ── */
        .profile-tabs {
            display: flex;
            gap: 4px;
            background: #fff;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            flex-wrap: wrap;
        }

        .p-tab {
            padding: 9px 18px;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: .15s;
            user-select: none;
        }

        .p-tab:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .p-tab.active {
            background: #0277bd;
            color: #fff;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn .2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Cards / sections ── */
        .info-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            padding: 18px 20px;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: .95rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f3f4f6;
        }

        /* ── Form grid (Bootstrap 5 has no .form-row; provide our own) ── */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -8px;
            margin-left: -8px;
        }

        .form-row>[class*="col-"] {
            padding-right: 8px;
            padding-left: 8px;
        }

        .info-label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .doc-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #e0f2fe;
            color: #0277bd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .btn-primary-custom {
            background: #0277bd;
            border-color: #0277bd;
            color: #fff;
        }

        .btn-primary-custom:hover {
            background: #025a94;
            border-color: #025a94;
            color: #fff;
        }
    </style>
</head>

<body>
    <?php $sidebar_active = 'school-students';
    include(__DIR__ . "/inc/sidebar.php"); ?>

    <main class="doctor-content">

        <!-- Profile header -->
        <div class="profile-header d-flex align-items-center justify-content-between flex-wrap" style="gap:14px;">
            <div class="d-flex align-items-center" style="gap:16px;">
                <div class="p-avatar"><?= strtoupper(substr($m['name'], 0, 1)) ?></div>
                <div>
                    <div class="p-name"><?= htmlspecialchars($m['name']) ?></div>
                    <div class="p-sub">
                        <?= htmlspecialchars($m['member_uid']) ?> &bull; <?= htmlspecialchars($m['type']) ?> &bull; <?= htmlspecialchars($m['school_name']) ?>
                        <?php if ($age): ?> &bull; <?= $age ?> yrs<?php endif; ?>
                        <?php if ($m['gender']): ?> &bull; <?= htmlspecialchars($m['gender']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="school-students.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left me-1"></i> All Students
            </a>
        </div>

        <!-- Tabs -->
        <div class="profile-tabs">
            <div class="p-tab active" onclick="showTab('info',this)"><i class="fa fa-user me-1"></i> Information</div>
            <div class="p-tab" onclick="showTab('health',this)"><i class="fa fa-heartbeat me-1"></i> Health Profile</div>
            <div class="p-tab" onclick="showTab('rx',this)"><i class="fa fa-file-medical me-1"></i> Prescriptions</div>
            <div class="p-tab" onclick="showTab('cert',this)"><i class="fa fa-certificate me-1"></i> Certificates</div>
            <div class="p-tab" onclick="showTab('docs',this)"><i class="fa fa-file-text-o me-1"></i> Medical Reports</div>
        </div>

        <!-- ── TAB: Information ── -->
        <div class="tab-pane active" id="tab-info">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-section">
                        <div class="section-title"><i class="fa fa-id-card me-2" style="color:#0277bd;"></i>Basic Information</div>
                        <div class="form-row">
                            <div class="col-md-6 mb-2"><span class="info-label">Blood Group</span><div><?= htmlspecialchars($m['blood_group'] ?: 'Not set') ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Date of Birth</span><div><?= $m['dob'] ? date('d M Y', strtotime($m['dob'])) : 'Not set' ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Phone</span><div><?= htmlspecialchars($m['phone'] ?: 'Not set') ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Email</span><div><?= htmlspecialchars($m['email'] ?: 'Not set') ?></div></div>
                            <div class="col-md-12 mb-2"><span class="info-label">Address</span><div><?= htmlspecialchars($m['address'] ?: 'Not set') ?></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-section">
                        <div class="section-title"><i class="fa fa-graduation-cap me-2" style="color:#0277bd;"></i>
                            <?= $m['type'] === 'Student' ? 'Academic Details' : 'Employment Details' ?>
                        </div>
                        <div class="form-row">
                            <?php if ($m['type'] === 'Student'): ?>
                                <div class="col-md-6 mb-2"><span class="info-label">Class</span><div><?= htmlspecialchars($m['class'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Section</span><div><?= htmlspecialchars($m['section'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Roll No.</span><div><?= htmlspecialchars($m['roll_number'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Admission No.</span><div><?= htmlspecialchars($m['admission_number'] ?: '—') ?></div></div>
                            <?php else: ?>
                                <div class="col-md-6 mb-2"><span class="info-label">Employee ID</span><div><?= htmlspecialchars($m['employee_id'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Designation</span><div><?= htmlspecialchars($m['designation'] ?: '—') ?></div></div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-2"><span class="info-label">ABHA Address</span><div><?= htmlspecialchars($m['abha_address'] ?: 'Not linked') ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Status</span><div><?= htmlspecialchars($m['status']) ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB: Health Profile ── -->
        <div class="tab-pane" id="tab-health">
            <div class="info-section">
                <div class="section-title"><i class="fa fa-heartbeat me-2" style="color:#dc2626;"></i>Edit Health Profile</div>
                <?php if (isset($_SESSION['health_success'])): ?>
                    <div class="alert alert-success" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['health_success']); unset($_SESSION['health_success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['health_error'])): ?>
                    <div class="alert alert-danger" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['health_error']); unset($_SESSION['health_error']); ?></div>
                <?php endif; ?>

                <?php if ($bmi): ?>
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-3">
                        <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:700;color:#0277bd;"><?= $hp['height_cm'] ?> <span style="font-size:.68rem;">cm</span></div>
                            <div style="font-size:.68rem;color:#9ca3af;">Height</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:700;color:#16a34a;"><?= $hp['weight_kg'] ?> <span style="font-size:.68rem;">kg</span></div>
                            <div style="font-size:.68rem;color:#9ca3af;">Weight</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:700;"><?= $bmi ?></div>
                            <div style="font-size:.68rem;color:#9ca3af;">BMI</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="save-student-health.php" id="healthForm">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">

                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:6px 0 10px;">Physical Measurements</div>
                    <div class="form-row">
                        <div class="col-md-3 mb-2">
                            <label class="info-label d-block mb-1">Blood Group</label>
                            <select name="blood_group" class="form-control form-control-sm">
                                <option value="">Not set</option>
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                    <option value="<?= $bg ?>" <?= ($m['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="info-label d-block mb-1">Height (cm)</label>
                            <input type="number" step="0.1" name="height_cm" id="heightInput" class="form-control form-control-sm"
                                value="<?= $hp['height_cm'] ?? '' ?>" placeholder="e.g. 150.0" oninput="calcBMI()">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="info-label d-block mb-1">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight_kg" id="weightInput" class="form-control form-control-sm"
                                value="<?= $hp['weight_kg'] ?? '' ?>" placeholder="e.g. 42.0" oninput="calcBMI()">
                            <div id="bmiLive" style="font-size:.7rem;margin-top:3px;"></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="info-label d-block mb-1">Vision</label>
                            <input type="text" name="vision" class="form-control form-control-sm" value="<?= htmlspecialchars($hp['vision'] ?? '') ?>" placeholder="e.g. 6/6 Normal">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="info-label d-block mb-1">Hearing</label>
                            <input type="text" name="hearing" class="form-control form-control-sm" value="<?= htmlspecialchars($hp['hearing'] ?? '') ?>" placeholder="e.g. Normal">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="info-label d-block mb-1">Dental</label>
                            <input type="text" name="dental" class="form-control form-control-sm" value="<?= htmlspecialchars($hp['dental'] ?? '') ?>" placeholder="e.g. Good">
                        </div>
                    </div>

                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:16px 0 10px;">Medical History</div>
                    <div class="form-row">
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Known Allergies</label>
                            <textarea name="known_allergies" class="form-control form-control-sm" rows="2" placeholder="e.g. Peanuts, Dust mites, Penicillin"><?= htmlspecialchars($hp['known_allergies'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Chronic Conditions</label>
                            <textarea name="chronic_conditions" class="form-control form-control-sm" rows="2" placeholder="e.g. Asthma, Diabetes"><?= htmlspecialchars($hp['chronic_conditions'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Current Medications</label>
                            <textarea name="current_medications" class="form-control form-control-sm" rows="2" placeholder="e.g. Salbutamol inhaler"><?= htmlspecialchars($hp['current_medications'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Vaccination Status</label>
                            <textarea name="vaccination_status" class="form-control form-control-sm" rows="2" placeholder="e.g. COVID-19 fully vaccinated, BCG, MMR"><?= htmlspecialchars($hp['vaccination_status'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:16px 0 10px;">Emergency Contact</div>
                    <div class="form-row">
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Contact Name</label>
                            <input type="text" name="emergency_contact" class="form-control form-control-sm" value="<?= htmlspecialchars($hp['emergency_contact'] ?? '') ?>" placeholder="Parent / Guardian name">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Contact Phone</label>
                            <input type="tel" name="emergency_phone" class="form-control form-control-sm" value="<?= htmlspecialchars($hp['emergency_phone'] ?? '') ?>" placeholder="+91 XXXXX XXXXX">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Additional Notes</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Any other health observations…"><?= htmlspecialchars($hp['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <?php if (!empty($hp['updated_at'])): ?>
                        <div style="font-size:.72rem;color:#9ca3af;margin-bottom:10px;">
                            <i class="fa fa-clock me-1"></i>Last updated <?= date('d M Y, h:i A', strtotime($hp['updated_at'])) ?>
                            <?= !empty($hp['last_updated_role']) ? ' by ' . ucfirst($hp['last_updated_role']) : '' ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm btn-primary-custom"><i class="fa fa-save me-1"></i> Save Health Profile</button>
                </form>
            </div>
        </div>

        <!-- ── TAB: Prescriptions ── -->
        <div class="tab-pane" id="tab-rx">
            <div class="info-section">
                <div class="section-title"><i class="fa fa-file-medical me-2" style="color:#0277bd;"></i>Write New Prescription</div>
                <?php if (isset($_SESSION['rx_error'])): ?>
                    <div class="alert alert-danger" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['rx_error']); unset($_SESSION['rx_error']); ?></div>
                <?php endif; ?>
                <form method="POST" action="save-student-prescription.php">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    <div class="form-row">
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control form-control-sm" placeholder="e.g. Seasonal flu, Viral fever">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Symptoms</label>
                            <textarea name="symptoms" class="form-control form-control-sm" rows="2" placeholder="Observed symptoms"></textarea>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Prescription (Rx) <span class="text-danger">*</span></label>
                            <textarea name="prescription_text" class="form-control form-control-sm" rows="5" required
                                placeholder="Medicine name, dosage, frequency, duration…"></textarea>
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="info-label d-block mb-1">Advice / Notes</label>
                            <textarea name="advice" class="form-control form-control-sm" rows="2" placeholder="Rest, diet, precautions…"></textarea>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="info-label d-block mb-1">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-sm btn-primary-custom"><i class="fa fa-check me-1"></i> Save Prescription</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="font-size:.84rem;color:#374151;font-weight:600;margin:16px 0 8px;">
                <?= count($prescriptions) ?> prescription(s)
            </div>

            <?php if (empty($prescriptions)): ?>
                <div class="info-section text-center py-4">
                    <i class="fa fa-file-medical fa-2x text-muted mb-2"></i>
                    <div class="text-muted" style="font-size:.86rem;">No prescriptions written yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($prescriptions as $rx): ?>
                    <div class="rx-item">
                        <div class="rx-head">
                            <div>
                                <div style="font-weight:600;font-size:.9rem;color:#1f2937;"><?= htmlspecialchars($rx['diagnosis'] ?: 'General Prescription') ?></div>
                                <div style="font-size:.74rem;color:#9ca3af;">
                                    Dr. <?= htmlspecialchars($rx['doctor_name'] ?? 'Unknown') ?> &bull; <?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?>
                                    <?php if ($rx['follow_up_date']): ?> &bull; Follow-up: <?= date('d M Y', strtotime($rx['follow_up_date'])) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex" style="gap:6px;">
                                <a href="print-student-prescription.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print"><i class="fa fa-print"></i></a>
                                <?php if ((int)$rx['doctor_id'] === $doctor_id): ?>
                                    <form method="POST" action="delete-student-prescription.php" onsubmit="return confirm('Delete this prescription?');" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $rx['id'] ?>">
                                        <input type="hidden" name="member_id" value="<?= $member_id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($rx['symptoms']): ?><div style="font-size:.8rem;color:#6b7280;margin-bottom:6px;"><strong>Symptoms:</strong> <?= htmlspecialchars($rx['symptoms']) ?></div><?php endif; ?>
                        <div class="rx-text"><?= htmlspecialchars($rx['prescription_text']) ?></div>
                        <?php if ($rx['advice']): ?><div style="font-size:.8rem;color:#6b7280;margin-top:6px;"><strong>Advice:</strong> <?= htmlspecialchars($rx['advice']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── TAB: Certificates ── -->
        <div class="tab-pane" id="tab-cert">
            <div class="info-section">
                <div class="section-title"><i class="fa fa-certificate me-2" style="color:#0277bd;"></i>Issue Medical / Leave Certificate</div>
                <?php if (isset($_SESSION['cert_error'])): ?>
                    <div class="alert alert-danger" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['cert_error']); unset($_SESSION['cert_error']); ?></div>
                <?php endif; ?>
                <form method="POST" action="save-student-certificate.php">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    <div class="form-row">
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Certificate Type</label>
                            <select name="certificate_type" class="form-control form-control-sm">
                                <?php foreach ($certificate_types as $t): ?><option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Reason / Diagnosis <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control form-control-sm" rows="3" required
                                placeholder="Medical condition or reason the certificate is being issued for…"></textarea>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="info-label d-block mb-1">Leave From</label>
                            <input type="date" name="leave_from" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="info-label d-block mb-1">Leave To</label>
                            <input type="date" name="leave_to" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="info-label d-block mb-1">Fit to Resume From</label>
                            <input type="date" name="fit_to_join_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Additional Remarks</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Optional notes…"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-sm btn-primary-custom"><i class="fa fa-certificate me-1"></i> Issue Certificate</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="font-size:.84rem;color:#374151;font-weight:600;margin:16px 0 8px;">
                <?= count($certificates) ?> certificate(s)
            </div>

            <?php if (empty($certificates)): ?>
                <div class="info-section text-center py-4">
                    <i class="fa fa-certificate fa-2x text-muted mb-2"></i>
                    <div class="text-muted" style="font-size:.86rem;">No certificates issued yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($certificates as $c): ?>
                    <div class="rx-item">
                        <div class="rx-head">
                            <div>
                                <div style="font-weight:600;font-size:.9rem;color:#1f2937;"><?= htmlspecialchars($c['certificate_type']) ?></div>
                                <div style="font-size:.74rem;color:#9ca3af;">
                                    Dr. <?= htmlspecialchars($c['doctor_name'] ?? 'Unknown') ?> &bull; <?= date('d M Y, h:i A', strtotime($c['created_at'])) ?>
                                    <?php if ($c['leave_from'] && $c['leave_to']): ?>
                                        &bull; Leave: <?= date('d M', strtotime($c['leave_from'])) ?> – <?= date('d M Y', strtotime($c['leave_to'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex" style="gap:6px;">
                                <a href="print-student-certificate.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print"><i class="fa fa-print"></i></a>
                                <?php if ((int)$c['doctor_id'] === $doctor_id): ?>
                                    <form method="POST" action="delete-student-certificate.php" onsubmit="return confirm('Delete this certificate?');" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="member_id" value="<?= $member_id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rx-text"><?= nl2br(htmlspecialchars($c['reason'])) ?></div>
                        <?php if ($c['remarks']): ?><div style="font-size:.8rem;color:#6b7280;margin-top:6px;"><strong>Remarks:</strong> <?= htmlspecialchars($c['remarks']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── TAB: Medical Reports ── -->
        <div class="tab-pane" id="tab-docs">
            <div class="info-section">
                <div class="section-title"><i class="fa fa-upload me-2" style="color:#0277bd;"></i>Upload Medical Report</div>
                <?php if (isset($_SESSION['doc_error'])): ?>
                    <div class="alert alert-danger" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['doc_error']); unset($_SESSION['doc_error']); ?></div>
                <?php endif; ?>
                <form method="POST" action="save-student-document.php" enctype="multipart/form-data">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    <div class="form-row">
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Report Title</label>
                            <input type="text" name="document_title" class="form-control form-control-sm" placeholder="e.g. Blood Test Report" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Report Type</label>
                            <select name="document_type" class="form-control form-control-sm">
                                <?php foreach ($doc_types as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">File</label>
                            <input type="file" name="document_file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">Max 10MB — PDF, DOC, DOCX, JPG, PNG</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Description (optional)</label>
                            <textarea name="description" class="form-control form-control-sm" rows="1"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-sm btn-primary-custom"><i class="fa fa-upload me-1"></i> Upload</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="font-size:.84rem;color:#374151;font-weight:600;margin:16px 0 8px;">
                <?= count($documents) ?> document(s)
            </div>

            <?php if (empty($documents)): ?>
                <div class="info-section text-center py-4">
                    <i class="fa fa-folder-open-o fa-2x text-muted mb-2"></i>
                    <div class="text-muted" style="font-size:.86rem;">No medical reports uploaded yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc):
                    $uploader = $doc['doctor_name'] ? 'Dr. ' . $doc['doctor_name'] : (trim(($doc['admin_first'] ?? '') . ' ' . ($doc['admin_last'] ?? '')) ?: 'Admin');
                ?>
                    <div class="doc-item d-flex align-items-center" style="gap:12px;">
                        <div class="doc-icon"><i class="fa fa-file-text-o"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:.88rem;color:#1f2937;"><?= htmlspecialchars($doc['document_name']) ?></div>
                            <?php if ($doc['description']): ?><div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($doc['description']) ?></div><?php endif; ?>
                            <div style="font-size:.76rem;color:#9ca3af;">
                                <?= date('d M Y', strtotime($doc['uploaded_at'])) ?> &bull; Uploaded by <?= htmlspecialchars($uploader) ?>
                            </div>
                        </div>
                        <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-eye"></i></a>
                        <?php if ((int)($doc['uploaded_by_doctor_id'] ?? 0) === $doctor_id): ?>
                            <form method="POST" action="delete-student-document.php" onsubmit="return confirm('Delete this document?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                <input type="hidden" name="member_id" value="<?= $member_id ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <script>
        function showTab(name, el) {
            document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.p-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            el.classList.add('active');
        }

        function calcBMI() {
            const h = parseFloat(document.getElementById('heightInput').value);
            const w = parseFloat(document.getElementById('weightInput').value);
            const el = document.getElementById('bmiLive');
            if (!el) return;
            if (h > 0 && w > 0) {
                const bmi = (w / ((h / 100) * (h / 100))).toFixed(1);
                let label, color;
                if (bmi < 18.5) { label = 'Underweight'; color = '#d97706'; }
                else if (bmi < 25) { label = 'Normal'; color = '#16a34a'; }
                else if (bmi < 30) { label = 'Overweight'; color = '#d97706'; }
                else { label = 'Obese'; color = '#dc2626'; }
                el.innerHTML = `<span style="color:${color};font-weight:700;">BMI: ${bmi}</span> <span style="color:#9ca3af;">— ${label}</span>`;
            } else {
                el.innerHTML = '';
            }
        }

        calcBMI();

        const hash = location.hash.replace('#', '') || new URLSearchParams(location.search).get('tab');
        if (hash) {
            const btn = document.querySelector('[onclick*="\'' + hash + '\'"]');
            if (btn) btn.click();
        }
    </script>
</body>

</html>
