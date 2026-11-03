<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once "../config/connect.php";
include_once "../util/function.php";
include_once "../lib/PatientHealthProfile.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$contact = contact_us();
$user_id = (int)$_SESSION['user_id'];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PatientHealthProfile::save($conn, $user_id, [
        'height_cm'                  => $_POST['height_cm'] ?? null,
        'weight_kg'                  => $_POST['weight_kg'] ?? null,
        'blood_group'                => $_POST['blood_group'] ?? null,
        'blood_pressure'             => $_POST['blood_pressure'] ?? null,
        'pulse_rate'                 => $_POST['pulse_rate'] ?? null,
        'vision_left'                => $_POST['vision_left'] ?? null,
        'vision_right'               => $_POST['vision_right'] ?? null,
        'known_allergies'            => $_POST['known_allergies'] ?? null,
        'chronic_conditions'         => $_POST['chronic_conditions'] ?? null,
        'current_medications'        => $_POST['current_medications'] ?? null,
        'past_surgeries'             => $_POST['past_surgeries'] ?? null,
        'disability'                 => $_POST['disability'] ?? null,
        'vaccination_details'        => $_POST['vaccination_details'] ?? null,
        'is_vaccinated'              => isset($_POST['is_vaccinated']) ? 1 : 0,
        'emergency_contact_name'     => $_POST['emergency_contact_name'] ?? null,
        'emergency_contact_phone'    => $_POST['emergency_contact_phone'] ?? null,
        'emergency_contact_relation' => $_POST['emergency_contact_relation'] ?? null,
        'last_checkup_date'          => $_POST['last_checkup_date'] ?: null,
        'next_checkup_date'          => $_POST['next_checkup_date'] ?: null,
        'checkup_notes'              => $_POST['checkup_notes'] ?? null,
        'insurance_provider'         => $_POST['insurance_provider'] ?? null,
        'insurance_number'           => $_POST['insurance_number'] ?? null,
    ], 'patient', $user_id);
    $success_message = 'Health profile updated successfully!';
}

$health = PatientHealthProfile::get($conn, $user_id);

// Check ABHA status
$u_stmt = $conn->prepare("SELECT abha_id, abha_address, abha_linked FROM users WHERE id = ?");
$u_stmt->bind_param('i', $user_id);
$u_stmt->execute();
$user_abha = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Health Record (PHR) | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .phr-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
            border: 1px solid #f1f5f9;
        }
        .phr-card-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f3f4f6;
        }
        .phr-card-head .phr-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dk));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .phr-card-head h2 {
            font-size: .95rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        .phr-card-head small {
            display: block;
            font-size: .72rem;
            color: #9ca3af;
            margin-top: 2px;
        }
        .phr-header-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dk));
            color: #fff;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }
        .phr-header-banner h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 4px;
        }
        .phr-header-banner .ap-sub {
            color: rgba(255, 255, 255, .82);
            font-size: .82rem;
        }
        .phr-header-banner .btn-outline-secondary {
            color: #fff;
            border-color: rgba(255, 255, 255, .5);
            background: rgba(255, 255, 255, .08);
        }
        .phr-header-banner .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, .18);
            border-color: #fff;
            color: #fff;
        }
        .form-label {
            font-weight: 600;
            font-size: .84rem;
            color: #374151;
            margin-bottom: 0.35rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(12,116,197,.12);
        }
        .phr-save-bar {
            background: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        @media (max-width: 575.98px) {
            .phr-card, .phr-save-bar {
                padding: 16px 14px;
                border-radius: 12px;
            }
            .phr-header-banner {
                padding: 18px;
                border-radius: 12px;
            }
            .phr-header-banner .btn-outline-secondary {
                width: 100%;
                text-align: center;
            }
            .phr-save-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .phr-save-bar .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="patient-body">
    <?php $sidebar_active = 'health'; include("sidebar.php"); ?>

    <main class="patient-content">
        <div class="phr-header-banner">
            <div>
                <h1><i class="fa fa-heartbeat me-2"></i>Personal Health Record (PHR)</h1>
                <div class="ap-sub">ABDM-compliant biometric vitals, medical history, immunizations, and clinical notes</div>
            </div>
            <a href="<?= BASE_URL ?>user/user-dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($user_abha['abha_linked']) && !empty($user_abha['abha_id'])): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4" style="background:#eaf4fd;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:38px;height:38px;background:var(--primary);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                            <i class="fa fa-shield"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:.88rem;">
                                Linked with Ayushman Bharat Health Account (ABHA)
                            </div>
                            <div class="small text-muted">
                                ABHA Number: <strong class="font-monospace text-dark"><?= htmlspecialchars($user_abha['abha_id']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-success" style="font-size:.7rem;"><i class="fa fa-check me-1"></i>PHR Sync Ready</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <!-- Vitals & Biometrics -->
                <div class="phr-card">
                    <div class="phr-card-head">
                        <span class="phr-icon"><i class="fa fa-stethoscope"></i></span>
                        <div>
                            <h2>Vital Signs & Biometric Indicators</h2>
                            <small>Height, weight, BMI, blood group and basic vitals</small>
                        </div>
                    </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" step="0.1" min="0" class="form-control" name="height_cm" id="hpHeight" value="<?= htmlspecialchars($health['height_cm'] ?? '') ?>" placeholder="e.g. 175">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" min="0" class="form-control" name="weight_kg" id="hpWeight" value="<?= htmlspecialchars($health['weight_kg'] ?? '') ?>" placeholder="e.g. 70">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Body Mass Index (BMI)</label>
                        <input type="text" class="form-control bg-light" id="hpBmi" value="<?= $health['bmi'] ?? '' ?>" readonly placeholder="Auto-calculated">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Blood Group</label>
                        <select class="form-select" name="blood_group">
                            <option value="">Select</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= ($health['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Blood Pressure (mmHg)</label>
                        <input type="text" class="form-control" name="blood_pressure" placeholder="e.g. 120/80" value="<?= htmlspecialchars($health['blood_pressure'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Pulse Rate (bpm)</label>
                        <input type="number" class="form-control" name="pulse_rate" placeholder="e.g. 72" value="<?= htmlspecialchars($health['pulse_rate'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Vision — Left (OS)</label>
                        <input type="text" class="form-control" name="vision_left" placeholder="e.g. 6/6" value="<?= htmlspecialchars($health['vision_left'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Vision — Right (OD)</label>
                        <input type="text" class="form-control" name="vision_right" placeholder="e.g. 6/6" value="<?= htmlspecialchars($health['vision_right'] ?? '') ?>">
                    </div>
                </div>
                </div>

                <!-- Clinical History -->
                <div class="phr-card">
                    <div class="phr-card-head">
                        <span class="phr-icon"><i class="fa fa-notes-medical"></i></span>
                        <div>
                            <h2>Clinical History & Chronic Conditions</h2>
                            <small>Allergies, chronic conditions, medications and surgical history</small>
                        </div>
                    </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Known Allergies (Food / Drug / Environmental)</label>
                        <textarea class="form-control" name="known_allergies" rows="2" placeholder="e.g. Penicillin, Peanuts, Pollen"><?= htmlspecialchars($health['known_allergies'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Chronic Conditions / Morbidities</label>
                        <textarea class="form-control" name="chronic_conditions" rows="2" placeholder="e.g. Hypertension, Type 2 Diabetes, Asthma"><?= htmlspecialchars($health['chronic_conditions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ongoing Medications & Dosage</label>
                        <textarea class="form-control" name="current_medications" rows="2" placeholder="e.g. Metformin 500mg OD, Telmisartan 40mg"><?= htmlspecialchars($health['current_medications'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Past Surgeries & Hospitalizations</label>
                        <textarea class="form-control" name="past_surgeries" rows="2" placeholder="e.g. Appendectomy (2020), Knee Arthroscopy (2022)"><?= htmlspecialchars($health['past_surgeries'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Physical or Neurological Disability (if any)</label>
                        <input type="text" class="form-control" name="disability" value="<?= htmlspecialchars($health['disability'] ?? '') ?>" placeholder="None or specify details">
                    </div>
                </div>
                </div>

                <!-- Vaccination -->
                <div class="phr-card">
                    <div class="phr-card-head">
                        <span class="phr-icon"><i class="fa fa-syringe"></i></span>
                        <div>
                            <h2>Immunization & Vaccination Records</h2>
                            <small>Vaccination status and dose history</small>
                        </div>
                    </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="is_vaccinated" id="is_vaccinated" value="1" <?= !empty($health['is_vaccinated']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="is_vaccinated">
                                Fully Vaccinated (COVID-19 / Adult Schedule)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Vaccination History & Doses</label>
                        <input type="text" class="form-control" name="vaccination_details" value="<?= htmlspecialchars($health['vaccination_details'] ?? '') ?>" placeholder="e.g. Covishield Dose 1 & 2 + Precautionary, Hepatitis B">
                    </div>
                </div>
                </div>

                <!-- Emergency Contact -->
                <div class="phr-card">
                    <div class="phr-card-head">
                        <span class="phr-icon"><i class="fa fa-phone-square"></i></span>
                        <div>
                            <h2>Emergency Caregiver Contact</h2>
                            <small>Who to contact in a medical emergency</small>
                        </div>
                    </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Contact Person Name</label>
                        <input type="text" class="form-control" name="emergency_contact_name" value="<?= htmlspecialchars($health['emergency_contact_name'] ?? '') ?>" placeholder="Name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact Mobile Number</label>
                        <input type="text" class="form-control" name="emergency_contact_phone" maxlength="15" value="<?= htmlspecialchars($health['emergency_contact_phone'] ?? '') ?>" placeholder="10-digit number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Relationship to Patient</label>
                        <input type="text" class="form-control" name="emergency_contact_relation" value="<?= htmlspecialchars($health['emergency_contact_relation'] ?? '') ?>" placeholder="e.g. Spouse, Parent, Sibling">
                    </div>
                </div>
                </div>

                <!-- Checkup Schedule -->
                <div class="phr-card">
                    <div class="phr-card-head">
                        <span class="phr-icon"><i class="fa fa-calendar-alt"></i></span>
                        <div>
                            <h2>Routine Health Checkup Schedule</h2>
                            <small>Track past and upcoming checkups</small>
                        </div>
                    </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Last Comprehensive Checkup</label>
                        <input type="date" class="form-control" name="last_checkup_date" value="<?= htmlspecialchars($health['last_checkup_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Next Scheduled Checkup</label>
                        <input type="date" class="form-control" name="next_checkup_date" value="<?= htmlspecialchars($health['next_checkup_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Physician Notes / Advice</label>
                        <input type="text" class="form-control" name="checkup_notes" value="<?= htmlspecialchars($health['checkup_notes'] ?? '') ?>" placeholder="Brief observations">
                    </div>
                </div>
                </div>

                <!-- Insurance -->
                <div class="phr-card">
                    <div class="phr-card-head">
                        <span class="phr-icon"><i class="fa fa-shield-alt"></i></span>
                        <div>
                            <h2>Health Insurance & ABHA PM-JAY Details</h2>
                            <small>Insurance or government scheme coverage</small>
                        </div>
                    </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Insurance Provider / TPA</label>
                        <input type="text" class="form-control" name="insurance_provider" value="<?= htmlspecialchars($health['insurance_provider'] ?? '') ?>" placeholder="e.g. Star Health, HDFC ERGO, Ayushman PM-JAY">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Policy / Member ID Number</label>
                        <input type="text" class="form-control" name="insurance_number" value="<?= htmlspecialchars($health['insurance_number'] ?? '') ?>" placeholder="e.g. POL-987654321">
                    </div>
                </div>
                </div>

                <div class="phr-save-bar">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa fa-save me-1"></i> Save Health Profile (PHR)
                    </button>
                    <a href="<?= BASE_URL ?>user/user-dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php if ($health): ?>
                        <div class="text-muted ms-auto" style="font-size:.76rem;">
                            <i class="fa fa-clock-o me-1"></i>Last updated <?= date('d M Y, h:i A', strtotime($health['updated_at'])) ?> by <?= $health['last_updated_role'] === 'patient' ? 'you' : ucfirst($health['last_updated_role'] ?: 'healthcare provider') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
    </main>

    <?php include("inc/scripts.php"); ?>

    <script>
        (function () {
            const ht = document.getElementById('hpHeight');
            const wt = document.getElementById('hpWeight');
            const bmiEl = document.getElementById('hpBmi');
            if (!ht || !wt || !bmiEl) return;
            const calc = () => {
                const h = parseFloat(ht.value), w = parseFloat(wt.value);
                bmiEl.value = (h > 0 && w > 0) ? (w / ((h / 100) ** 2)).toFixed(2) : '';
            };
            ht.addEventListener('input', calc);
            wt.addEventListener('input', calc);
        })();
    </script>
</body>
</html>
