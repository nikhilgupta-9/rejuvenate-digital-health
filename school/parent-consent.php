<?php
/**
 * Parent Health Checkup Consent Form
 * Public page — no login required.
 * Auto-creates parent_consent_forms table and saves submission.
 */
include_once __DIR__ . "/../config/connect.php";

/* ── Auto-create table ── */
$conn->query("
CREATE TABLE IF NOT EXISTS parent_consent_forms (
id INT UNSIGNED NOT NULL AUTO_INCREMENT,
token VARCHAR(64) NOT NULL UNIQUE,
school_id INT UNSIGNED DEFAULT NULL,
school_name_manual VARCHAR(200) DEFAULT NULL,
parent_name VARCHAR(150) NOT NULL,
relation ENUM('Father','Mother','Guardian','Other') NOT NULL DEFAULT 'Father',
parent_mobile VARCHAR(15) NOT NULL,
parent_email VARCHAR(150) DEFAULT NULL,
parent_aadhar_last4 CHAR(4) DEFAULT NULL,
student_name VARCHAR(150) NOT NULL,
student_dob DATE DEFAULT NULL,
student_gender ENUM('Male','Female','Other') DEFAULT NULL,
student_class VARCHAR(20) DEFAULT NULL,
student_section VARCHAR(10) DEFAULT NULL,
student_roll_no VARCHAR(30) DEFAULT NULL,
student_address TEXT DEFAULT NULL,
student_city VARCHAR(100) DEFAULT NULL,
student_state VARCHAR(100) DEFAULT NULL,
student_pincode VARCHAR(10) DEFAULT NULL,
student_abha_number VARCHAR(20) DEFAULT NULL,
student_abha_address VARCHAR(100) DEFAULT NULL,
blood_group VARCHAR(10) DEFAULT NULL,
known_allergies TEXT DEFAULT NULL,
existing_conditions TEXT DEFAULT NULL,
current_medications TEXT DEFAULT NULL,
consent_items JSON NOT NULL,
consent_given TINYINT(1) NOT NULL DEFAULT 0,
declaration_text TEXT DEFAULT NULL,
ip_address VARCHAR(45) DEFAULT NULL,
user_agent VARCHAR(255) DEFAULT NULL,
submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
status ENUM('pending','reviewed','archived') NOT NULL DEFAULT 'pending',
PRIMARY KEY (id),
KEY idx_school (school_id),
KEY idx_mobile (parent_mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ── Migrations: add columns to a table created before they existed ── */
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_address TEXT DEFAULT NULL AFTER student_roll_no");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_city VARCHAR(100) DEFAULT NULL AFTER student_address");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_state VARCHAR(100) DEFAULT NULL AFTER student_city");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_pincode VARCHAR(10) DEFAULT NULL AFTER student_state");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_abha_number VARCHAR(20) DEFAULT NULL AFTER student_pincode");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_abha_address VARCHAR(100) DEFAULT NULL AFTER student_abha_number");
/* ── Linkage / provenance: parent submission vs doctor point-of-care capture ── */
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS member_id INT DEFAULT NULL AFTER school_id");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS source ENUM('parent','doctor') NOT NULL DEFAULT 'parent' AFTER status");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS recorded_by_doctor_id INT DEFAULT NULL AFTER source");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS linked_at DATETIME DEFAULT NULL AFTER recorded_by_doctor_id");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL AFTER linked_at");
$conn->query("ALTER TABLE parent_consent_forms ADD INDEX IF NOT EXISTS idx_member (member_id)");

/* ── Header logo (same source as the rest of the site, with static fallback) ── */
$logo_src = BASE_URL . 'assets/img/logo/black-logo.svg';
$lr = $conn->query("SELECT logo_path FROM logos WHERE location='header' ORDER BY id DESC LIMIT 1");
if ($lr && $lr->num_rows) {
    $logo_src = BASE_URL . 'admin/uploads/' . $lr->fetch_assoc()['logo_path'];
}

$schools = [];
$sr = $conn->query("SELECT id, school_name FROM schools WHERE status='Active' ORDER BY school_name ASC");
if ($sr)
    $schools = $sr->fetch_all(MYSQLI_ASSOC);

$success = false;
$error = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_consent'])) {
    $school_id = (int) ($_POST['school_id'] ?? 0) ?: null;
    $school_manual = trim($_POST['school_name_manual'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $relation = in_array($_POST['relation'] ?? '', ['Father', 'Mother', 'Guardian', 'Other']) ? $_POST['relation'] : 'Father';
    $parent_mobile = preg_replace('/\D/', '', trim($_POST['parent_mobile'] ?? ''));
    $parent_email = trim($_POST['parent_email'] ?? '') ?: null;
    $aadhar_last4 = substr(preg_replace('/\D/', '', trim($_POST['parent_aadhar'] ?? '')), -4) ?: null;
    $student_name = trim($_POST['student_name'] ?? '');
    $student_dob = trim($_POST['student_dob'] ?? '') ?: null;
    $student_gender = in_array($_POST['student_gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['student_gender'] : null;
    $student_class = trim($_POST['student_class'] ?? '');
    $student_sec = trim($_POST['student_section'] ?? '');
    $student_roll = trim($_POST['student_roll_no'] ?? '');
    $student_address = trim($_POST['student_address'] ?? '') ?: null;
    $student_city = trim($_POST['student_city'] ?? '') ?: null;
    $student_state = trim($_POST['student_state'] ?? '') ?: null;
    $student_pincode = trim($_POST['student_pincode'] ?? '') ?: null;
    $blood_group = trim($_POST['blood_group'] ?? '') ?: null;
    $allergies = trim($_POST['known_allergies'] ?? '') ?: null;
    $conditions = trim($_POST['existing_conditions'] ?? '') ?: null;
    $medications = trim($_POST['current_medications'] ?? '') ?: null;

    /* ── Student Health ID (ABHA) — optional, validated to ABDM format ── */
    $abha_err = '';
    $student_abha_number = null;
    $student_abha_address = trim($_POST['student_abha_address'] ?? '') ?: null;
    $abha_digits = preg_replace('/\D/', '', trim($_POST['student_abha_number'] ?? ''));
    if ($abha_digits !== '') {
        if (strlen($abha_digits) !== 14) {
            $abha_err = 'ABHA number must be exactly 14 digits — or leave it blank if your child does not have one yet.';
        } else {
            $student_abha_number = substr($abha_digits, 0, 2) . '-' . substr($abha_digits, 2, 4) . '-' . substr($abha_digits, 6, 4) . '-' . substr($abha_digits, 10, 4);
        }
    }
    if (!$abha_err && $student_abha_address) {
        if (strpos($student_abha_address, '@') === false) $student_abha_address .= '@abdm';
        if (!preg_match('/^[a-zA-Z0-9._]{3,}@abdm$/', $student_abha_address)) {
            $abha_err = 'ABHA address must look like name@abdm (letters, numbers, dot or underscore).';
        }
    }

    $consent_keys = ['general_checkup', 'height_weight', 'vision_test', 'dental_check', 'blood_pressure', 'vaccination_check', 'mental_wellness', 'data_storage', 'data_share_doctor', 'data_share_school'];
    $consent_given_items = [];
    foreach ($consent_keys as $key) {
        $consent_given_items[$key] = isset($_POST['consent'][$key]) ? true : false;
    }
    $consent_json = json_encode($consent_given_items);
    $consent_given = isset($_POST['i_agree']) ? 1 : 0;

    if (!$parent_name || !$student_name || strlen($parent_mobile) < 10) {
        $error = 'Please fill in all required fields correctly.';
    } elseif ($abha_err) {
        $error = $abha_err;
    } elseif (!$consent_given) {
        $error = 'You must agree to the declaration to submit the form.';
    } else {
        $token = bin2hex(random_bytes(16));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $decl = "I, {$parent_name}, hereby give consent for the health checkup of my ward {$student_name}.";

        $ins = $conn->prepare("INSERT INTO parent_consent_forms (token,school_id,school_name_manual,parent_name,relation,parent_mobile,parent_email,parent_aadhar_last4,student_name,student_dob,student_gender,student_class,student_section,student_roll_no,student_address,student_city,student_state,student_pincode,student_abha_number,student_abha_address,blood_group,known_allergies,existing_conditions,current_medications,consent_items,consent_given,declaration_text,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $types = 'si' . str_repeat('s', 23) . 'isss'; // 29 params
        $ins->bind_param($types, $token, $school_id, $school_manual, $parent_name, $relation, $parent_mobile, $parent_email, $aadhar_last4, $student_name, $student_dob, $student_gender, $student_class, $student_sec, $student_roll, $student_address, $student_city, $student_state, $student_pincode, $student_abha_number, $student_abha_address, $blood_group, $allergies, $conditions, $medications, $consent_json, $consent_given, $decl, $ip, $ua);
        if ($ins->execute()) {
            $success = true;
        } else {
            $error = 'Submission failed. Please try again.';
        }
        $ins->close();
    }

}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Parent Consent Form | Rejuvenate Digital Health</title>
    <link rel="icon" href="<?= BASE_URL ?>assets/favicon/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/favicon/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>school/assets/school.css">
    <style>
        /* Theme aligned with the School Portal — White | #0C74C5 | #02c9b8, no gradients */
        :root {
            --primary: #0C74C5;
            --primary-dk: #0a5fa0;
            --accent: #02c9b8;
            --ink: #1f2937;
        }

        body {
            background: #f4f7fb;
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: .93rem;
            color: var(--ink);
        }

        .form-header {
            background: #fff;
            border-top: 4px solid var(--primary);
            border-bottom: 1px solid #e5e7eb;
            padding: 22px 24px 18px;
            text-align: center;
        }

        .form-header .brand-logo {
            height: 42px;
            width: auto;
            margin-bottom: 12px;
        }

        .form-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--primary);
        }

        .form-header p {
            font-size: .86rem;
            color: #6b7280;
            margin: 0;
        }

        .progress-bar-wrap {
            background: #e5e7eb;
            height: 4px;
            border-radius: 2px;
            margin-top: 14px;
            overflow: hidden;
            max-width: 760px;
            margin-left: auto;
            margin-right: auto;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            transition: width .3s;
            width: 0;
        }

        .consent-wrapper {
            max-width: 760px;
            margin: 0 auto;
            padding: 20px 20px 60px;
        }

        .form-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
            margin-bottom: 18px;
            overflow: hidden;
            padding: 0;
        }

        .section-head {
            background: #f8faff;
            border-bottom: 1px solid #e5e7eb;
            padding: 13px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .s-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .section-head h5 {
            margin: 0;
            font-size: .92rem;
            font-weight: 700;
            color: var(--ink);
        }

        .section-body {
            padding: 18px;
        }

        .form-label {
            font-size: .78rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            padding: 9px 12px;
            font-size: .88rem;
            transition: border-color .2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(12, 116, 197, .1);
            outline: none;
        }

        .req {
            color: #ef4444;
        }

        .hint {
            font-size: .72rem;
            color: #94a3b8;
            margin-top: 3px;
        }

        .abha-note {
            background: #eaf4fd;
            border: 1px solid #bfdbf6;
            border-radius: 8px;
            padding: 10px 13px;
            font-size: .78rem;
            color: #0a5fa0;
            margin-top: 10px;
        }

        .consent-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 13px;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            margin-bottom: 7px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }

        .consent-item:hover,
        .consent-item.checked {
            border-color: var(--accent);
            background: #f0fefe;
        }

        .consent-item input[type=checkbox] {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            flex-shrink: 0;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .consent-item .ci-title {
            font-weight: 600;
            font-size: .86rem;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .consent-item .ci-desc {
            font-size: .76rem;
            color: #64748b;
            margin-top: 2px;
        }

        .declaration-box {
            background: #fffbeb;
            border: 1.5px solid #f59e0b;
            border-radius: 10px;
            padding: 15px 17px;
            font-size: .83rem;
            color: #78350f;
            line-height: 1.7;
            margin-bottom: 14px;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-submit:hover {
            background: var(--primary-dk);
        }

        .success-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
            padding: 48px 28px;
            text-align: center;
            max-width: 500px;
            margin: 40px auto;
        }

        .success-icon {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            background: #d1fae5;
            color: #065f46;
            font-size: 2.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .token-chip {
            display: inline-block;
            background: #f1f5f9;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 8px 16px;
            font-family: monospace;
            font-size: .95rem;
            color: var(--ink);
            letter-spacing: .06em;
            margin: 10px 0;
        }

        .form-footer {
            text-align: center;
            padding: 14px;
            font-size: .73rem;
            color: #94a3b8;
        }

        @media (min-width: 992px) {
            .consent-wrapper { max-width: 820px; padding: 32px 20px 70px; }
            .form-header { padding: 30px 24px 24px; }
            .form-header h1 { font-size: 1.6rem; }
            .section-body { padding: 24px; }
        }

        @media (max-width: 767px) {
            .consent-wrapper { padding: 18px 16px 50px; }
            .form-header { padding: 20px 18px 16px; }
            .section-head { padding: 12px 15px; }
            .section-body { padding: 15px; }
            .declaration-box { padding: 13px 15px; }
        }

        @media (max-width: 575px) {
            .form-header { padding: 18px 14px 14px; }
            .form-header .brand-logo { height: 34px; }
            .form-header h1 { font-size: 1.15rem; }
            .form-header p { font-size: .78rem; }

            .consent-wrapper { padding: 16px 10px 44px; }

            .section-head { gap: 8px; padding: 11px 13px; }
            .s-num { width: 22px; height: 22px; font-size: .68rem; }
            .section-head h5 { font-size: .85rem; }

            .section-body { padding: 13px; }
            .row.g-3 { row-gap: .75rem !important; }

            .consent-item { padding: 9px 11px; }
            .consent-item .ci-title { font-size: .82rem; }
            .consent-item .ci-desc { font-size: .72rem; }

            .declaration-box { font-size: .8rem; padding: 12px 14px; }

            .d-flex.gap-2.mt-3 { flex-wrap: wrap; }
            .d-flex.gap-2.mt-3 .btn { flex: 1 1 auto; }

            .btn-submit { font-size: .92rem; padding: 13px; }

            .success-card { padding: 30px 16px; }
            .token-chip { font-size: .85rem; padding: 7px 12px; }

            .form-footer { font-size: .68rem; padding: 12px 16px; line-height: 1.6; }
        }
    </style>
</head>

<body>

    <div class="form-header">
        <img src="<?= htmlspecialchars($logo_src) ?>" alt="Rejuvenate Digital Health" class="brand-logo"
            onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/logo/logo.png';">
        <h1><i class="fas fa-file-signature me-2"></i>Parent Consent Form</h1>
        <p>Student Health Checkup — Please fill in all details carefully</p>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="pgBar"></div>
        </div>
    </div>

    <div class="consent-wrapper">

        <?php if ($success): ?>
            <div class="success-card">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h4 style="font-weight:700;color:var(--primary);">Consent Submitted!</h4>
                <p style="color:#64748b;font-size:.88rem;">Your consent has been recorded. Save your reference number below.
                </p>
                <div class="token-chip"><?= strtoupper(substr($token, 0, 8)) ?></div>
                <p style="font-size:.78rem;color:#94a3b8;">Show this to the school health team if asked.</p>
                <div
                    style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px;font-size:.81rem;color:#166534;margin-top:14px;text-align:left;">
                    <i class="fas fa-shield-alt me-1"></i> Data stored securely on ABHA-linked records as per ABDM
                    guidelines.
                </div>
                <a href="parent-consent.php"
                    style="display:inline-block;margin-top:18px;color:var(--primary);font-size:.84rem;"><i
                        class="fas fa-plus me-1"></i>Submit another form</a>
            </div>

        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="border-radius:10px;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="cForm" novalidate>
                <input type="hidden" name="submit_consent" value="1">

                <!-- 1. School -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">1</div>
                        <h5><i class="fas fa-school me-2" style="color:var(--primary)"></i>School Information</h5>
                    </div>
                    <div class="section-body">
                        <?php if (!empty($schools)): ?>
                            <div class="mb-3">
                                <label class="form-label">Select School</label>
                                <select name="school_id" class="form-select" id="schoolSel">
                                    <option value="">— Select your child's school —</option>
                                    <?php foreach ($schools as $sc): ?>
                                        <option value="<?= $sc['id'] ?>" <?= (($_POST['school_id'] ?? '') == $sc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sc['school_name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="0">Other (type below)</option>
                                </select>
                            </div>
                            <div id="manualWrap" style="display:none">
                                <label class="form-label">School Name</label>
                                <input type="text" name="school_name_manual" class="form-control"
                                    placeholder="Enter school name"
                                    value="<?= htmlspecialchars($_POST['school_name_manual'] ?? '') ?>">
                            </div>
                        <?php else: ?>
                            <label class="form-label">School Name <span class="req">*</span></label>
                            <input type="text" name="school_name_manual" class="form-control" placeholder="Enter school name"
                                value="<?= htmlspecialchars($_POST['school_name_manual'] ?? '') ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Parent -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">2</div>
                        <h5><i class="fas fa-user me-2" style="color:var(--primary)"></i>Parent / Guardian Details</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full Name <span class="req">*</span></label><input
                                    type="text" name="parent_name" class="form-control" required placeholder="e.g. Ramesh Kumar"
                                    value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Relation</label><select name="relation"
                                    class="form-select"><?php foreach (['Father', 'Mother', 'Guardian', 'Other'] as $r): ?>
                                        <option value="<?= $r ?>" <?= (($_POST['relation'] ?? 'Father') === $r) ? 'selected' : '' ?>>
                                            <?= $r ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-6"><label class="form-label">Mobile Number <span
                                        class="req">*</span></label><input type="tel" name="parent_mobile"
                                    class="form-control" required placeholder="10-digit number" maxlength="10"
                                    value="<?= htmlspecialchars($_POST['parent_mobile'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Email <span
                                        style="color:#94a3b8;font-weight:400">(optional)</span></label><input type="email"
                                    name="parent_email" class="form-control" placeholder="you@example.com"
                                    value="<?= htmlspecialchars($_POST['parent_email'] ?? '') ?>"></div>
                            <div class="col-md-6">
                                <label class="form-label">Aadhaar Last 4 Digits</label>
                                <input type="text" name="parent_aadhar" class="form-control" placeholder="XXXX"
                                    maxlength="4" value="<?= htmlspecialchars($_POST['parent_aadhar'] ?? '') ?>">
                                <div class="hint"><i class="fas fa-lock me-1"></i>Only last 4 digits stored</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Student -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">3</div>
                        <h5><i class="fas fa-child me-2" style="color:var(--primary)"></i>Student Details</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Student Full Name <span
                                        class="req">*</span></label><input type="text" name="student_name"
                                    class="form-control" required placeholder="e.g. Aryan Kumar"
                                    value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date"
                                    name="student_dob" class="form-control" max="<?= date('Y-m-d') ?>"
                                    value="<?= htmlspecialchars($_POST['student_dob'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Gender</label><select name="student_gender"
                                    class="form-select">
                                    <option value="">— Select —</option><?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                        <option value="<?= $g ?>" <?= (($_POST['student_gender'] ?? '') === $g) ? 'selected' : '' ?>>
                                            <?= $g ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-4"><label class="form-label">Class</label><input type="text"
                                    name="student_class" class="form-control" placeholder="e.g. 7"
                                    value="<?= htmlspecialchars($_POST['student_class'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Section</label><input type="text"
                                    name="student_section" class="form-control" placeholder="e.g. A"
                                    value="<?= htmlspecialchars($_POST['student_section'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Roll Number</label><input type="text"
                                    name="student_roll_no" class="form-control" placeholder="Roll / Admission No."
                                    value="<?= htmlspecialchars($_POST['student_roll_no'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label">Address Line <span
                                        style="color:#94a3b8;font-weight:400">(house no., street, locality)</span></label><textarea
                                    name="student_address" class="form-control" rows="2"
                                    placeholder="e.g. H.No. 24, Green Park Colony, Near City Hospital"><?= htmlspecialchars($_POST['student_address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-5"><label class="form-label">City / Town</label><input type="text"
                                    name="student_city" class="form-control" placeholder="e.g. Lucknow"
                                    value="<?= htmlspecialchars($_POST['student_city'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">State</label><input type="text"
                                    name="student_state" class="form-control" placeholder="e.g. Uttar Pradesh"
                                    value="<?= htmlspecialchars($_POST['student_state'] ?? '') ?>"></div>
                            <div class="col-md-3"><label class="form-label">PIN Code</label><input type="text"
                                    name="student_pincode" class="form-control" placeholder="e.g. 226001" maxlength="6"
                                    inputmode="numeric" pattern="[0-9]{6}"
                                    value="<?= htmlspecialchars($_POST['student_pincode'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Blood Group</label><select name="blood_group"
                                    class="form-select">
                                    <option value="">— Select if known —</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= (($_POST['blood_group'] ?? '') === $bg) ? 'selected' : '' ?>>
                                            <?= $bg ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                    </div>
                </div>

                <!-- 4. Student Health ID (ABHA) -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">4</div>
                        <h5><i class="fas fa-id-card me-2" style="color:var(--primary)"></i>Student Health ID (ABHA)
                            <span style="font-size:.75rem;font-weight:400;color:#94a3b8">(if available)</span></h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ABHA Number</label>
                                <input type="text" name="student_abha_number" class="form-control"
                                    placeholder="XX-XXXX-XXXX-XXXX" maxlength="17" inputmode="numeric"
                                    value="<?= htmlspecialchars($_POST['student_abha_number'] ?? '') ?>">
                                <div class="hint">14-digit Ayushman Bharat Health Account number.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ABHA Address</label>
                                <input type="text" name="student_abha_address" class="form-control"
                                    placeholder="name@abdm"
                                    value="<?= htmlspecialchars($_POST['student_abha_address'] ?? '') ?>">
                                <div class="hint">e.g. <code>aryan.kumar@abdm</code></div>
                            </div>
                        </div>
                        <div class="abha-note">
                            <i class="fas fa-info-circle me-1"></i>
                            Don't have an ABHA for your child yet? Leave this blank — the school health team will help
                            create one during the checkup, with your consent below.
                        </div>
                    </div>
                </div>

                <!-- 5. Medical History -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">5</div>
                        <h5><i class="fas fa-notes-medical me-2" style="color:var(--primary)"></i>Medical History <span
                                style="font-size:.75rem;font-weight:400;color:#94a3b8">(optional)</span></h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Known Allergies</label><input type="text"
                                    name="known_allergies" class="form-control"
                                    placeholder="e.g. Penicillin, Peanuts — or leave blank"
                                    value="<?= htmlspecialchars($_POST['known_allergies'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label">Existing Medical Conditions</label><input
                                    type="text" name="existing_conditions" class="form-control"
                                    placeholder="e.g. Asthma, Diabetes — or leave blank"
                                    value="<?= htmlspecialchars($_POST['existing_conditions'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label">Current Medications</label><input type="text"
                                    name="current_medications" class="form-control"
                                    placeholder="e.g. Salbutamol inhaler — or leave blank"
                                    value="<?= htmlspecialchars($_POST['current_medications'] ?? '') ?>"></div>
                        </div>
                    </div>
                </div>

                <!-- 6. Consent -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">6</div>
                        <h5><i class="fas fa-clipboard-check me-2" style="color:var(--primary)"></i>I Give Consent For</h5>
                    </div>
                    <div class="section-body">
                        <p style="font-size:.83rem;color:#64748b;margin-bottom:13px">Tick the services you allow for your
                            child. You may select all or specific ones.</p>
                        <?php
                        $items = [
                            'general_checkup' => ['fas fa-stethoscope', 'General Physical Checkup', 'Overall health assessment by the school doctor.'],
                            'height_weight' => ['fas fa-weight', 'Height, Weight & BMI', 'Growth monitoring and BMI calculation.'],
                            'vision_test' => ['fas fa-eye', 'Vision / Eyesight Screening', 'Basic vision test to detect any sight problems.'],
                            'dental_check' => ['fas fa-tooth', 'Dental Examination', 'Oral health check for cavities and hygiene.'],
                            'blood_pressure' => ['fas fa-heartbeat', 'Blood Pressure & Pulse Check', 'Cardiovascular health screening.'],
                            'vaccination_check' => ['fas fa-syringe', 'Vaccination Status Review', 'Checking immunisation records only — no injections without separate consent.'],
                            'mental_wellness' => ['fas fa-brain', 'Mental Wellness Screening', 'Basic questionnaire for emotional wellbeing — confidential.'],
                            'data_storage' => ['fas fa-database', 'Digital Health Record Storage', 'Store health data on ABHA-linked records as per ABDM guidelines.'],
                            'data_share_doctor' => ['fas fa-user-md', 'Share Data with School Doctor', 'Assigned doctor can view records to provide better care.'],
                            'data_share_school' => ['fas fa-school', 'Anonymised Data with School', 'Only aggregate, non-identifiable data for school health reports.'],
                        ];
                        foreach ($items as $key => [$icon, $title, $desc]): ?>
                            <label class="consent-item" id="ci<?= $key ?>">
                                <input type="checkbox" name="consent[<?= $key ?>]" value="1"
                                    onchange="toggleCI('<?= $key ?>',this.checked)"
                                    <?= isset($_POST['consent'][$key]) ? 'checked' : '' ?>>
                                <div>
                                    <div class="ci-title"><i class="<?= $icon ?>"
                                            style="color:var(--accent);width:15px"></i><?= $title ?></div>
                                    <div class="ci-desc"><?= $desc ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selAll(true)"><i
                                    class="fas fa-check-double me-1"></i>Select All</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selAll(false)">Clear
                                All</button>
                        </div>
                    </div>
                </div>

                <!-- 7. Declaration -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">7</div>
                        <h5><i class="fas fa-pen-fancy me-2" style="color:var(--primary)"></i>Declaration</h5>
                    </div>
                    <div class="section-body">
                        <div class="declaration-box">
                            <strong>Declaration:</strong> I, the undersigned parent/guardian, hereby give my informed
                            consent for my child's participation in the school health checkup programme conducted by
                            <strong>Rejuvenate Digital Health</strong> in association with the school. I confirm the
                            information provided is true and accurate. I understand that the health data collected will be
                            stored securely and used solely for the wellbeing of my child, in compliance with ABDM / ABHA
                            data privacy guidelines.
                        </div>
                        <label class="consent-item" style="border-color:#f59e0b;background:#fffbeb" id="ci_agree">
                            <input type="checkbox" name="i_agree" value="1" required
                                onchange="toggleCI('_agree',this.checked)" <?= isset($_POST['i_agree']) ? 'checked' : '' ?>>
                            <div style="font-size:.87rem;font-weight:600;color:#92400e">
                                <i class="fas fa-check-circle me-1" style="color:#f59e0b"></i>
                                I have read and understood the above declaration and I give my full consent. <span
                                    class="req">*</span>
                            </div>
                        </label>
                        <div class="hint" style="margin-top:8px"><i class="fas fa-lock me-1"></i>Aadhaar last 4 digits
                            only — full number never stored. Submission is secured.</div>
                    </div>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane me-2"></i>Submit Consent Form</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="form-footer"><i class="fas fa-shield-alt me-1"></i>Secured by Rejuvenate Digital Health &nbsp;|&nbsp; ABDM /
        ABHA Compliant &nbsp;|&nbsp; NHA India Guidelines</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const schoolSel = document.getElementById('schoolSel');
        const manualWrap = document.getElementById('manualWrap');
        if (schoolSel) {
            schoolSel.addEventListener('change', () => { if (manualWrap) manualWrap.style.display = schoolSel.value === '0' ? 'block' : 'none'; });
            if (schoolSel.value === '0' && manualWrap) manualWrap.style.display = 'block';
        }
        function toggleCI(key, checked) {
            const el = document.getElementById('ci' + key);
            if (el) el.classList.toggle('checked', checked);
        }
        function selAll(state) {
            document.querySelectorAll('.consent-item input[name^="consent"]').forEach(cb => {
                cb.checked = state;
                toggleCI(cb.closest('.consent-item').id.replace('ci', ''), state);
            });
        }
        function updateProgress() {
            const req = [...document.querySelectorAll('#cForm input[required]')];
            const filled = req.filter(i => i.type === 'checkbox' ? i.checked : i.value.trim() !== '').length;
            const bar = document.getElementById('pgBar');
            if (bar && req.length) bar.style.width = Math.round((filled / req.length) * 100) + '%';
        }
        // ABHA number auto-format: XX-XXXX-XXXX-XXXX
        const abhaInput = document.querySelector('input[name="student_abha_number"]');
        if (abhaInput) {
            abhaInput.addEventListener('input', () => {
                let d = abhaInput.value.replace(/\D/g, '').slice(0, 14);
                let out = d;
                if (d.length > 2) out = d.slice(0, 2) + '-' + d.slice(2);
                if (d.length > 6) out = d.slice(0, 2) + '-' + d.slice(2, 6) + '-' + d.slice(6);
                if (d.length > 10) out = d.slice(0, 2) + '-' + d.slice(2, 6) + '-' + d.slice(6, 10) + '-' + d.slice(10);
                abhaInput.value = out;
            });
        }
        document.querySelectorAll('#cForm input,#cForm select').forEach(el => el.addEventListener('change', updateProgress));
        document.querySelectorAll('.consent-item input:checked').forEach(cb => toggleCI(cb.closest('.consent-item').id.replace('ci', ''), true));
        updateProgress();
    </script>
</body>

</html>
