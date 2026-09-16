<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";
include_once "../lib/PatientHealthProfile.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$contact = contact_us();
$user_id = $_SESSION['user_id'];
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
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>My Health Profile | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
</head>

<body>
    <?php $sidebar_active = 'health'; include("sidebar.php"); ?>
    <main class="patient-content">
        <div class="profile-card shadow">
            <h4 class="mb-4"><i class="fa fa-heartbeat me-2"></i>My Health Profile</h4>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <div class="form-section-title">Vitals</div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" step="0.1" min="0" class="form-control" name="height_cm" id="hpHeight" value="<?= htmlspecialchars($health['height_cm'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" min="0" class="form-control" name="weight_kg" id="hpWeight" value="<?= htmlspecialchars($health['weight_kg'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">BMI</label>
                        <input type="text" class="form-control" id="hpBmi" value="<?= $health['bmi'] ?? '' ?>" readonly placeholder="auto">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Blood Group</label>
                        <select class="form-control" name="blood_group">
                            <option value="">Select</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= ($health['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" class="form-control" name="blood_pressure" placeholder="e.g. 120/80" value="<?= htmlspecialchars($health['blood_pressure'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Pulse Rate (/min)</label>
                        <input type="number" class="form-control" name="pulse_rate" value="<?= htmlspecialchars($health['pulse_rate'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Vision — Left</label>
                        <input type="text" class="form-control" name="vision_left" value="<?= htmlspecialchars($health['vision_left'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Vision — Right</label>
                        <input type="text" class="form-control" name="vision_right" value="<?= htmlspecialchars($health['vision_right'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section-title mt-2">Medical History</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Known Allergies</label>
                        <textarea class="form-control" name="known_allergies" rows="2"><?= htmlspecialchars($health['known_allergies'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Chronic Conditions</label>
                        <textarea class="form-control" name="chronic_conditions" rows="2" placeholder="e.g. Asthma, Diabetes"><?= htmlspecialchars($health['chronic_conditions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Current Medications</label>
                        <textarea class="form-control" name="current_medications" rows="2"><?= htmlspecialchars($health['current_medications'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Past Surgeries</label>
                        <textarea class="form-control" name="past_surgeries" rows="2"><?= htmlspecialchars($health['past_surgeries'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Disability</label>
                        <input type="text" class="form-control" name="disability" value="<?= htmlspecialchars($health['disability'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section-title mt-2">Vaccination</div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_vaccinated" id="is_vaccinated" value="1" <?= !empty($health['is_vaccinated']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_vaccinated">Vaccinated</label>
                        </div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Vaccination Details</label>
                        <input type="text" class="form-control" name="vaccination_details" value="<?= htmlspecialchars($health['vaccination_details'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section-title mt-2">Emergency Contact</div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="emergency_contact_name" value="<?= htmlspecialchars($health['emergency_contact_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="emergency_contact_phone" maxlength="15" value="<?= htmlspecialchars($health['emergency_contact_phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Relation</label>
                        <input type="text" class="form-control" name="emergency_contact_relation" value="<?= htmlspecialchars($health['emergency_contact_relation'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section-title mt-2">Checkup Schedule</div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Last Checkup</label>
                        <input type="date" class="form-control" name="last_checkup_date" value="<?= htmlspecialchars($health['last_checkup_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Next Checkup</label>
                        <input type="date" class="form-control" name="next_checkup_date" value="<?= htmlspecialchars($health['next_checkup_date'] ?? '') ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="checkup_notes" rows="2"><?= htmlspecialchars($health['checkup_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-section-title mt-2">Insurance</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Provider</label>
                        <input type="text" class="form-control" name="insurance_provider" value="<?= htmlspecialchars($health['insurance_provider'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Policy Number</label>
                        <input type="text" class="form-control" name="insurance_number" value="<?= htmlspecialchars($health['insurance_number'] ?? '') ?>">
                    </div>
                </div>

                <?php if ($health): ?>
                <div class="text-muted mb-3" style="font-size:.78rem;">
                    <i class="fa fa-clock-o me-1"></i>Last updated <?= date('d M Y, h:i A', strtotime($health['updated_at'])) ?> by <?= $health['last_updated_role'] === 'patient' ? 'you' : ucfirst($health['last_updated_role'] ?: 'unknown') ?>
                </div>
                <?php endif; ?>

                <div class="text-start mt-4">
                    <button type="submit" class="btn btn-warning px-4">Save Health Profile</button>
                    <a href="user-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    <?php include("inc/scripts.php") ?>

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
