<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor  = doctor_jwt_guard();
$doctor_id   = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';
$success_message = '';
$error_message   = '';
$info_message    = '';

/* ── Current doctor row ── */
$doctor_stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ? LIMIT 1");
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor = $doctor_stmt->get_result()->fetch_assoc();
if (!$doctor) { header("Location: " . BASE_URL . "doctor-login.php"); exit(); }

/* ── Latest HPR verification request ── */
$hpr_req_stmt = $conn->prepare("SELECT * FROM hpr_verification_requests WHERE doctor_id = ? ORDER BY id DESC LIMIT 1");
$hpr_req_stmt->bind_param('i', $doctor_id);
$hpr_req_stmt->execute();
$hpr_request = $hpr_req_stmt->get_result()->fetch_assoc();
$hpr_pending = $hpr_request && $hpr_request['status'] === 'pending';

/* ── Save ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_profile']) || isset($_POST['submit_hpr_request']))) {
    try {
        $name             = trim($_POST['name'] ?? $doctor['name']);
        $phone            = preg_replace('/\D/', '', $_POST['phone'] ?? $doctor['phone']);
        $email            = trim($_POST['email'] ?? $doctor['email']);
        $gender           = trim($_POST['gender'] ?? $doctor['gender']);
        $dob              = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $degrees          = trim($_POST['degrees'] ?? $doctor['degrees']);
        $specialization   = trim($_POST['specialization'] ?? $doctor['specialization']);
        $experience_years = (int)($_POST['experience_years'] ?? $doctor['experience_years']);
        $consultation_fee = (float)($_POST['consultation_fee'] ?? $doctor['consultation_fee']);
        $languages        = trim($_POST['languages'] ?? $doctor['languages']);
        $short_bio        = trim($_POST['short_bio'] ?? $doctor['short_bio']);
        $long_bio         = trim($_POST['long_bio'] ?? $doctor['long_bio']);
        $area_of_expertise = trim($_POST['area_of_expertise'] ?? $doctor['area_of_expertise']);
        $hpr_id           = trim($_POST['hpr_id'] ?? $doctor['hpr_id']);
        $hfr_id           = trim($_POST['hfr_id'] ?? $doctor['hfr_id']);
        $nmc_reg_number   = trim($_POST['nmc_reg_number'] ?? $doctor['nmc_reg_number']);
        $council_name     = trim($_POST['council_name'] ?? $doctor['council_name']);
        $year_of_registration = !empty($_POST['year_of_registration']) ? (int)$_POST['year_of_registration'] : null;
        $qualification_year   = !empty($_POST['qualification_year']) ? (int)$_POST['qualification_year'] : null;

        if ($name === '')  throw new Exception('Full name is required.');
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) throw new Exception('Enter a valid 10-digit mobile number.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Enter a valid email address.');
        // ABDM HPR ID format: NN-NNNN-NNNN-NNNN (14 digits). Optional — only checked if filled.
        if ($hpr_id !== '' && !preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $hpr_id)) {
            throw new Exception('HPR ID must be in the format 27-1234-5678-9012 (14 digits).');
        }

        /* Profile image */
        $profile_image = $doctor['profile_image'];
        if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            if (in_array($_FILES['profile_image']['type'], $allowed, true) && $_FILES['profile_image']['size'] <= 2097152) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $dir = dirname(__DIR__) . '/uploads/doctor_profile/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'doctor_' . $doctor_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                    if (!empty($doctor['profile_image']) && $doctor['profile_image'] !== 'assets/img/dummy.png') {
                        $old = dirname(__DIR__) . '/' . ltrim($doctor['profile_image'], '/.');
                        if (is_file($old)) @unlink($old);
                    }
                    $profile_image = 'uploads/doctor_profile/' . $fname;
                }
            } else {
                throw new Exception('Profile photo must be JPG / PNG / GIF / WEBP under 2 MB.');
            }
        }

        /* Documents */
        $uploaded_docs = [];
        if (!empty($_FILES['documents']['name'][0])) {
            $dir = dirname(__DIR__) . '/uploads/doctor_documents/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['documents']['name'] as $i => $orig) {
                if ($_FILES['documents']['error'][$i] !== 0) continue;
                $type = $_FILES['documents']['type'][$i];
                if (!in_array($type, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'], true)) continue;
                if ($_FILES['documents']['size'][$i] > 5242880) continue;
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $fn   = 'doc_' . $doctor_id . '_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $dir . $fn)) {
                    $uploaded_docs[] = [
                        'name' => $orig,
                        'path' => 'uploads/doctor_documents/' . $fn,
                        'label' => strpos($type, 'pdf') !== false ? 'PDF Document' : 'Certificate / ID',
                    ];
                }
            }
        }

        /* Age from DOB */
        $age = null;
        if ($dob) { $age = (new DateTime())->diff(new DateTime($dob))->y; }

        $upd = $conn->prepare("
            UPDATE doctors SET
              name=?, phone=?, email=?, gender=?, dob=?, age=?,
              degrees=?, specialization=?, experience_years=?, consultation_fee=?,
              languages=?, short_bio=?, long_bio=?, area_of_expertise=?, profile_image=?,
              hpr_id=?, hfr_id=?, nmc_reg_number=?, council_name=?,
              year_of_registration=?, qualification_year=?
            WHERE id=?
        ");
        $upd->bind_param('sssssissidsssssssssiii',
            $name, $phone, $email, $gender, $dob, $age,
            $degrees, $specialization, $experience_years, $consultation_fee,
            $languages, $short_bio, $long_bio, $area_of_expertise, $profile_image,
            $hpr_id, $hfr_id, $nmc_reg_number, $council_name,
            $year_of_registration, $qualification_year, $doctor_id
        );
        if (!$upd->execute()) throw new Exception('Could not save your profile. Please try again.');

        foreach ($uploaded_docs as $d) {
            $ds = $conn->prepare("INSERT INTO doctor_documents (doctor_id, document_type, document_name, file_path) VALUES (?,?,?,?)");
            $ds->bind_param('isss', $doctor_id, $d['label'], $d['name'], $d['path']);
            $ds->execute();
        }

        $_SESSION['doctor_name'] = $name;
        $success_message = 'Profile saved.';

        /* HPR verification request */
        if (isset($_POST['submit_hpr_request'])) {
            if ($doctor['hpr_verified']) {
                $info_message = 'Your HPR is already verified — no request needed.';
            } elseif ($hpr_pending) {
                $info_message = 'You already have an HPR verification request under review.';
            } elseif ($hpr_id === '' || $nmc_reg_number === '') {
                $error_message = 'Enter at least your HPR ID and NMC Registration Number before submitting for verification.';
            } else {
                $note = trim($_POST['hpr_note'] ?? '');
                $ins = $conn->prepare("INSERT INTO hpr_verification_requests
                    (doctor_id, hpr_id, hfr_id, nmc_reg_number, council_name, year_of_registration, doctor_note)
                    VALUES (?,?,?,?,?,?,?)");
                $ins->bind_param('issssis', $doctor_id, $hpr_id, $hfr_id, $nmc_reg_number, $council_name, $year_of_registration, $note);
                $ins->execute();
                $conn->query("UPDATE doctors SET hpr_requested_at = NOW() WHERE id = " . $doctor_id);
                $success_message = 'HPR verification request submitted. Our compliance team will review it against the HPR registry.';
            }
        }

        /* Reload */
        $doctor_stmt->execute();
        $doctor = $doctor_stmt->get_result()->fetch_assoc();
        $hpr_req_stmt->execute();
        $hpr_request = $hpr_req_stmt->get_result()->fetch_assoc();
        $hpr_pending = $hpr_request && $hpr_request['status'] === 'pending';

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/* ── Documents list ── */
$docs = $conn->prepare("SELECT * FROM doctor_documents WHERE doctor_id = ? ORDER BY uploaded_at DESC");
$docs->bind_param('i', $doctor_id);
$docs->execute();
$documents = $docs->get_result()->fetch_all(MYSQLI_ASSOC);

$formatted_dob = $doctor['dob'] ? date('Y-m-d', strtotime($doctor['dob'])) : '';
$photo = !empty($doctor['profile_image']) ? $doctor['profile_image'] : 'assets/img/dummy.png';

/* ── Verification checklist ── */
$checks = [
    'Account verified by admin' => (bool)$doctor['is_verified'],
    'Mobile number verified'    => (bool)$doctor['mobile_verified'],
    'HPR / ABDM verified'       => (bool)$doctor['hpr_verified'],
    'At least one document'     => count($documents) > 0,
];
$done  = count(array_filter($checks));
$total = count($checks);
$pct   = $total ? round($done / $total * 100) : 0;

$sidebar_active = 'contact';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile — REJUVENATE Doctor Portal</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
<style>
    .mp-head{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:18px;}
    .mp-head h1{font-size:1.2rem;font-weight:800;color:#1f2937;margin:0;}
    .mp-head .sub{font-size:.82rem;color:#9ca3af;}
    .mp-chips{display:flex;flex-wrap:wrap;gap:6px;}
    .mp-chip{font-size:.72rem;font-weight:700;border-radius:20px;padding:4px 11px;display:inline-flex;align-items:center;gap:5px;}
    .mp-chip.ok{background:#dcfce7;color:#166534;}
    .mp-chip.no{background:#fef3c7;color:#92400e;}
    .form-label-sm{font-size:.78rem;font-weight:600;color:#374151;margin-bottom:4px;display:block;}
    .mp-photo-wrap{position:relative;width:104px;height:104px;margin:0 auto;cursor:pointer;}
    .mp-photo{width:104px;height:104px;border-radius:50%;object-fit:cover;border:3px solid #02c9b8;}
    .mp-photo-edit{position:absolute;right:2px;bottom:2px;width:30px;height:30px;border-radius:50%;background:#0C74C5;color:#fff;
        display:flex;align-items:center;justify-content:center;font-size:.8rem;border:2px solid #fff;}
    .verified-pill{font-size:.7rem;font-weight:700;border-radius:20px;padding:2px 8px;}
    .verified-pill.ok{background:#dcfce7;color:#166534;}
    .verified-pill.no{background:#f3f4f6;color:#6b7280;}
    .hpr-banner{border-radius:10px;padding:14px 16px;font-size:.85rem;display:flex;gap:10px;align-items:flex-start;margin-bottom:16px;}
    .hpr-banner.v-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;}
    .hpr-banner.v-pending{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
    .hpr-banner.v-none{background:#f8fafc;border:1px solid #e5e7eb;color:#475569;}
    .hpr-banner i{font-size:1.1rem;margin-top:1px;}
    .mp-note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:11px 14px;font-size:.8rem;color:#1e40af;}
    .checklist{list-style:none;padding:0;margin:0;}
    .checklist li{display:flex;align-items:center;gap:9px;padding:7px 0;font-size:.83rem;color:#374151;border-bottom:1px solid #f3f4f6;}
    .checklist li:last-child{border-bottom:none;}
    .checklist .ci{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.66rem;flex-shrink:0;}
    .checklist .ci.ok{background:#dcfce7;color:#166534;}
    .checklist .ci.no{background:#f3f4f6;color:#9ca3af;}
    .progress-thin{height:7px;border-radius:20px;background:#f1f5f9;overflow:hidden;margin:6px 0 12px;}
    .progress-thin span{display:block;height:100%;background:linear-gradient(90deg,#0C74C5,#02c9b8);}
    .doc-tile{border:1px solid #e5e7eb;border-radius:9px;padding:11px 13px;font-size:.78rem;}
    .mp-savebar{position:sticky;bottom:0;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px 16px;
        display:flex;gap:10px;flex-wrap:wrap;align-items:center;box-shadow:0 -2px 12px rgba(0,0,0,.05);margin-top:8px;z-index:20;}
    @media(max-width:991px){.mp-side{margin-top:20px;}}
</style>
</head>
<body>
<main class="doctor-content">

    <div class="mp-head">
        <div>
            <h1>My Profile</h1>
            <div class="sub">Your public listing, professional details and ABDM / HPR registration</div>
        </div>
        <div class="mp-chips">
            <span class="mp-chip <?= $doctor['is_verified'] ? 'ok' : 'no' ?>">
                <i class="fa fa-<?= $doctor['is_verified'] ? 'check-circle' : 'clock' ?>"></i>
                <?= $doctor['is_verified'] ? 'Account Verified' : 'Verification Pending' ?>
            </span>
            <span class="mp-chip <?= $doctor['hpr_verified'] ? 'ok' : 'no' ?>">
                <i class="fa fa-shield"></i> HPR <?= $doctor['hpr_verified'] ? 'Verified' : 'Not Verified' ?>
            </span>
            <span class="mp-chip <?= $doctor['mobile_verified'] ? 'ok' : 'no' ?>">
                <i class="fa fa-mobile"></i> Mobile <?= $doctor['mobile_verified'] ? 'Verified' : 'Unverified' ?>
            </span>
        </div>
    </div>

    <?php foreach (['success' => $success_message, 'danger' => $error_message, 'info' => $info_message] as $k => $m): ?>
        <?php if ($m): ?>
            <div class="alert alert-<?= $k ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($m) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="mpForm">
    <div class="row">
        <div class="col-lg-8">

            <!-- ── Identity ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-id-card mr-2" style="color:#0C74C5;"></i>Identity &amp; Photo</div>
                <input type="file" name="profile_image" id="mpPhotoInput" accept="image/*" hidden onchange="mpPreview(this)">
                <div class="text-center mb-3">
                    <div class="mp-photo-wrap" onclick="document.getElementById('mpPhotoInput').click()">
                        <img id="mpPhotoPreview" class="mp-photo" src="<?= BASE_URL . htmlspecialchars($photo) ?>" alt="Profile photo">
                        <span class="mp-photo-edit"><i class="fa fa-camera"></i></span>
                    </div>
                    <div class="text-muted mt-2" style="font-size:.72rem;">Click to change · JPG / PNG / WEBP · max 2 MB</div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required value="<?= htmlspecialchars($doctor['name']) ?>">
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <label class="form-label-sm">Gender</label>
                        <select name="gender" class="form-control form-control-sm">
                            <?php foreach (['', 'Male', 'Female', 'Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($doctor['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?: '—' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <label class="form-label-sm">Date of Birth</label>
                        <input type="date" name="dob" id="mpDob" class="form-control form-control-sm" value="<?= $formatted_dob ?>">
                    </div>
                </div>
            </div>

            <!-- ── Contact ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-phone mr-2" style="color:#0C74C5;"></i>Contact</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">
                            Mobile Number <span class="text-danger">*</span>
                            <span class="verified-pill <?= $doctor['mobile_verified'] ? 'ok' : 'no' ?>">
                                <?= $doctor['mobile_verified'] ? 'verified' : 'unverified' ?>
                            </span>
                        </label>
                        <input type="tel" name="phone" class="form-control form-control-sm" maxlength="10" inputmode="numeric"
                               required value="<?= htmlspecialchars($doctor['phone']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm" required value="<?= htmlspecialchars($doctor['email']) ?>">
                        <div class="text-muted" style="font-size:.72rem;">This is your login email.</div>
                    </div>
                </div>
            </div>

            <!-- ── Professional ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-stethoscope mr-2" style="color:#0C74C5;"></i>Professional Details</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">Degrees <span class="text-danger">*</span></label>
                        <input type="text" name="degrees" class="form-control form-control-sm" required placeholder="MBBS, MD (Medicine)" value="<?= htmlspecialchars($doctor['degrees']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">Specialization <span class="text-danger">*</span></label>
                        <input type="text" name="specialization" class="form-control form-control-sm" required placeholder="Cardiology" value="<?= htmlspecialchars($doctor['specialization']) ?>">
                    </div>
                    <div class="col-md-4 col-6 mb-3">
                        <label class="form-label-sm">Years of Experience</label>
                        <input type="number" name="experience_years" class="form-control form-control-sm" min="0" max="60" value="<?= (int)$doctor['experience_years'] ?>">
                    </div>
                    <div class="col-md-4 col-6 mb-3">
                        <label class="form-label-sm">Consultation Fee (₹)</label>
                        <input type="number" name="consultation_fee" class="form-control form-control-sm" min="0" step="50" value="<?= (int)$doctor['consultation_fee'] ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label-sm">Languages Known</label>
                        <input type="text" name="languages" class="form-control form-control-sm" placeholder="English, Hindi" value="<?= htmlspecialchars($doctor['languages']) ?>">
                    </div>
                    <div class="col-12 mb-1">
                        <label class="form-label-sm">Area of Expertise</label>
                        <input type="text" name="area_of_expertise" class="form-control form-control-sm" placeholder="Interventional cardiology, heart failure, preventive cardiology" value="<?= htmlspecialchars($doctor['area_of_expertise']) ?>">
                    </div>
                </div>
            </div>

            <!-- ── HPR / ABDM ── -->
            <div class="form-section" id="hpr">
                <div class="form-section-title"><i class="fa fa-shield mr-2" style="color:#0C74C5;"></i>HPR / ABDM Registration</div>

                <?php if ($doctor['hpr_verified']): ?>
                    <div class="hpr-banner v-ok">
                        <i class="fa fa-check-circle"></i>
                        <div><strong>HPR Verified.</strong>
                            <?php if (!empty($doctor['hpr_verified_at'])): ?> Verified on <?= date('d M Y', strtotime($doctor['hpr_verified_at'])) ?>.<?php endif; ?>
                            Your profile shows the verified HPR badge to patients.</div>
                    </div>
                <?php elseif ($hpr_pending): ?>
                    <div class="hpr-banner v-pending">
                        <i class="fa fa-clock"></i>
                        <div><strong>Verification under review.</strong> Submitted <?= date('d M Y', strtotime($hpr_request['requested_at'])) ?>.
                            Our compliance team is verifying your details against the Health Professional Registry.</div>
                    </div>
                <?php else: ?>
                    <div class="hpr-banner v-none">
                        <i class="fa fa-info-circle"></i>
                        <div><strong>Not verified yet.</strong> Fill in your HPR &amp; NMC details below and submit for verification.
                            <?php if ($hpr_request && $hpr_request['status'] === 'rejected'): ?>
                                <span class="text-danger d-block mt-1">Previous request was not approved<?= $hpr_request['review_note'] ? ': ' . htmlspecialchars($hpr_request['review_note']) : '.' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">HPR ID</label>
                        <input type="text" name="hpr_id" id="mpHpr" class="form-control form-control-sm" maxlength="17"
                               placeholder="27-1234-5678-9012" value="<?= htmlspecialchars($doctor['hpr_id'] ?? '') ?>">
                        <div class="text-muted" style="font-size:.72rem;">14-digit Health Professional Registry ID.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">HFR ID <span class="text-muted fw-normal">(if clinic-based)</span></label>
                        <input type="text" name="hfr_id" class="form-control form-control-sm" placeholder="Health Facility Registry ID" value="<?= htmlspecialchars($doctor['hfr_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">NMC Registration Number</label>
                        <input type="text" name="nmc_reg_number" class="form-control form-control-sm" placeholder="National Medical Commission reg. no." value="<?= htmlspecialchars($doctor['nmc_reg_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label-sm">State Medical Council</label>
                        <input type="text" name="council_name" class="form-control form-control-sm" placeholder="e.g. Uttar Pradesh Medical Council" value="<?= htmlspecialchars($doctor['council_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <label class="form-label-sm">Year of NMC Registration</label>
                        <input type="number" name="year_of_registration" class="form-control form-control-sm" min="1950" max="<?= date('Y') ?>" value="<?= htmlspecialchars($doctor['year_of_registration'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <label class="form-label-sm">Year of Qualification</label>
                        <input type="number" name="qualification_year" class="form-control form-control-sm" min="1950" max="<?= date('Y') ?>" value="<?= htmlspecialchars($doctor['qualification_year'] ?? '') ?>">
                    </div>
                    <?php if (!$doctor['hpr_verified'] && !$hpr_pending): ?>
                    <div class="col-12 mb-3">
                        <label class="form-label-sm">Note to reviewer <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="hpr_note" class="form-control form-control-sm" rows="2" placeholder="Anything our compliance team should know"></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mp-note">
                    <i class="fa fa-info-circle mr-1"></i>
                    Under <strong>ABDM</strong> guidelines, Health Professional Registry verification is done through an
                    Aadhaar-based OTP flow with the National Health Authority. Create or check your HPR ID at
                    <a href="https://hpr.abdm.gov.in/" target="_blank" rel="noopener">hpr.abdm.gov.in</a>. We never store your
                    Aadhaar number — only your HPR / NMC identifiers and a consent record.
                    <?php if (!$doctor['hpr_verified'] && !$hpr_pending): ?>
                        <div class="mt-2">
                            <button type="submit" name="submit_hpr_request" value="1" class="btn btn-sm btn-primary" style="background:#0C74C5;border-color:#0C74C5;">
                                <i class="fa fa-shield mr-1"></i> Save &amp; Submit for HPR Verification
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Bio ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-user mr-2" style="color:#0C74C5;"></i>About You</div>
                <div class="mb-3">
                    <label class="form-label-sm">Short Bio <span class="text-muted fw-normal">(shown in listings)</span></label>
                    <textarea name="short_bio" id="mpShort" class="form-control form-control-sm" rows="2" maxlength="240"
                              placeholder="One or two lines patients see first"><?= htmlspecialchars($doctor['short_bio']) ?></textarea>
                    <div class="text-muted text-end" style="font-size:.7rem;"><span id="mpShortCount">0</span>/240</div>
                </div>
                <div>
                    <label class="form-label-sm">Detailed Bio</label>
                    <textarea name="long_bio" class="form-control form-control-sm" rows="5"
                              placeholder="Background, training, memberships, notable work…"><?= htmlspecialchars($doctor['long_bio']) ?></textarea>
                </div>
            </div>

            <!-- ── Documents ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-folder-open mr-2" style="color:#0C74C5;"></i>Documents</div>
                <label class="form-label-sm">Upload certificates / registration proof / ID</label>
                <input type="file" name="documents[]" class="form-control form-control-sm" multiple accept=".pdf,.jpg,.jpeg,.png">
                <div class="text-muted" style="font-size:.72rem;">PDF / JPG / PNG · max 5 MB each. Admin reviews and marks each document verified.</div>

                <?php if ($documents): ?>
                    <div class="row mt-3 g-2">
                        <?php foreach ($documents as $d): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="doc-tile">
                                    <div class="fw-semibold text-truncate"><?= htmlspecialchars($d['document_name']) ?></div>
                                    <div class="text-muted"><?= htmlspecialchars($d['document_type']) ?> ·
                                        <?= !empty($d['is_verified']) ? '<span class="text-success">verified</span>' : '<span class="text-warning">pending</span>' ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.7rem;">Uploaded <?= date('d M Y', strtotime($d['uploaded_at'])) ?></div>
                                    <div class="mt-1 d-flex gap-1">
                                        <a href="<?= BASE_URL . htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fa fa-eye"></i></a>
                                        <a href="delete-document.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2"
                                           onclick="return confirm('Delete this document?')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mp-savebar">
                <button type="submit" name="save_profile" value="1" class="btn btn-success">
                    <i class="fa fa-save mr-1"></i> Save Changes
                </button>
                <a href="<?= BASE_URL ?>doctor/doctor-dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                <a href="<?= BASE_URL ?>doctor/account-settings.php" class="btn btn-outline-primary ml-auto">
                    <i class="fa fa-cog mr-1"></i> Account Settings
                </a>
            </div>

        </div>

        <!-- ── Side rail ── -->
        <div class="col-lg-4 mp-side">
            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-tasks mr-2" style="color:#0C74C5;"></i>Profile Completeness</div>
                <div class="d-flex justify-content-between" style="font-size:.8rem;">
                    <span class="text-muted"><?= $done ?> of <?= $total ?> done</span>
                    <span class="fw-bold" style="color:#0C74C5;"><?= $pct ?>%</span>
                </div>
                <div class="progress-thin"><span style="width:<?= $pct ?>%"></span></div>
                <ul class="checklist">
                    <?php foreach ($checks as $label => $ok): ?>
                        <li>
                            <span class="ci <?= $ok ? 'ok' : 'no' ?>"><i class="fa fa-<?= $ok ? 'check' : 'circle-o' ?>"></i></span>
                            <?= htmlspecialchars($label) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="form-section">
                <div class="form-section-title"><i class="fa fa-link mr-2" style="color:#0C74C5;"></i>Quick Links</div>
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>doctor/account-settings.php" class="btn btn-outline-secondary btn-sm text-start"><i class="fa fa-cog mr-2"></i>Account Settings</a>
                    <a href="<?= BASE_URL ?>doctor/change-password.php" class="btn btn-outline-secondary btn-sm text-start"><i class="fa fa-key mr-2"></i>Change Password</a>
                    <a href="<?= BASE_URL ?>doctor-plans/" class="btn btn-outline-secondary btn-sm text-start"><i class="fa fa-id-card-o mr-2"></i>Membership Plans</a>
                    <a href="<?= BASE_URL ?>doctor/my-contact.php#hpr" class="btn btn-outline-secondary btn-sm text-start"><i class="fa fa-shield mr-2"></i>HPR Registration</a>
                </div>
            </div>

            <div class="form-section" style="font-size:.8rem;color:#6b7280;">
                <i class="fa fa-info-circle mr-1" style="color:#0C74C5;"></i>
                Fields marked <span class="text-danger">*</span> are required. Verification badges (Account, HPR, Documents)
                are set by our team after review — you provide the details, we verify against the registries.
            </div>
        </div>
    </div>
    </form>
</main>

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script>
function mpPreview(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => document.getElementById('mpPhotoPreview').src = e.target.result;
        r.readAsDataURL(input.files[0]);
    }
}

// Short-bio counter
const mpShort = document.getElementById('mpShort');
const mpShortCount = document.getElementById('mpShortCount');
function syncShort(){ mpShortCount.textContent = mpShort.value.length; }
mpShort.addEventListener('input', syncShort); syncShort();

// HPR ID auto-format XX-XXXX-XXXX-XXXX
const mpHpr = document.getElementById('mpHpr');
mpHpr.addEventListener('input', function () {
    const d = this.value.replace(/\D/g, '').slice(0, 14);
    this.value = d.replace(/(\d{2})(\d{0,4})(\d{0,4})(\d{0,4})/, (_, a, b, c, e) => [a, b, c, e].filter(Boolean).join('-'));
});

// Client validation
document.getElementById('mpForm').addEventListener('submit', function (e) {
    const phone = document.querySelector('[name=phone]').value.replace(/\D/g, '');
    if (!/^[6-9]\d{9}$/.test(phone)) { e.preventDefault(); alert('Enter a valid 10-digit mobile number.'); return; }
    const hpr = mpHpr.value.trim();
    if (hpr && !/^\d{2}-\d{4}-\d{4}-\d{4}$/.test(hpr)) { e.preventDefault(); alert('HPR ID must look like 27-1234-5678-9012.'); return; }
});

// DOB -> nothing to compute visibly (age stored server-side)
</script>
</body>
</html>
