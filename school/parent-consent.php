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

/* ── Full Student Health Assessment fields (based on the school Google Form) ── */
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_apaar_id VARCHAR(40) DEFAULT NULL AFTER student_roll_no");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS parent_aadhar_mobile VARCHAR(15) DEFAULT NULL AFTER parent_aadhar_last4");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS student_abha_status VARCHAR(20) DEFAULT NULL AFTER student_abha_address");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS height_cm DECIMAL(5,1) DEFAULT NULL AFTER blood_group");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(5,1) DEFAULT NULL AFTER height_cm");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS bmi DECIMAL(5,1) DEFAULT NULL AFTER weight_kg");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS health_data JSON DEFAULT NULL AFTER current_medications");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS file_id_proof VARCHAR(255) DEFAULT NULL AFTER health_data");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS file_eye_report VARCHAR(255) DEFAULT NULL AFTER file_id_proof");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS file_dental_report VARCHAR(255) DEFAULT NULL AFTER file_eye_report");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS file_vaccination_cert VARCHAR(255) DEFAULT NULL AFTER file_dental_report");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS file_medical_records VARCHAR(255) DEFAULT NULL AFTER file_vaccination_cert");

/**
 * Save one uploaded file from a public submission.
 * Whitelisted types only, random name, capped size, stored under
 * school/uploads/consent/ (which has an exec-off .htaccess).
 * Returns the web-relative path or null.
 */
function pcf_save_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['size'] <= 0 || $f['size'] > 6 * 1024 * 1024) {   // 6 MB cap
        return null;
    }
    $allowed = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime = function_exists('finfo_file')
        ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])
        : ($f['type'] ?? '');
    if (!isset($allowed[$ext]) || ($mime && $mime !== $allowed[$ext])) {
        return null;
    }
    $dir = __DIR__ . '/uploads/consent';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        return null;
    }
    return 'school/uploads/consent/' . $name;
}

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
    $student_apaar = trim($_POST['student_apaar_id'] ?? '') ?: null;
    $student_address = trim($_POST['student_address'] ?? '') ?: null;
    $student_city = trim($_POST['student_city'] ?? '') ?: null;
    $student_state = trim($_POST['student_state'] ?? '') ?: null;
    $student_pincode = trim($_POST['student_pincode'] ?? '') ?: null;
    $parent_aadhar_mobile = preg_replace('/\D/', '', trim($_POST['parent_aadhar_mobile'] ?? '')) ?: null;
    $blood_group = trim($_POST['blood_group'] ?? '') ?: null;
    $allergies = trim($_POST['known_allergies'] ?? '') ?: null;
    $conditions = trim($_POST['existing_conditions'] ?? '') ?: null;
    $medications = trim($_POST['current_medications'] ?? '') ?: null;

    $abha_status = in_array($_POST['student_abha_status'] ?? '', ['Generated', 'Not Generated'], true) ? $_POST['student_abha_status'] : null;

    /* ── Height / weight / BMI ── */
    $height_cm = is_numeric($_POST['height_cm'] ?? '') ? (float) $_POST['height_cm'] : null;
    $weight_kg = is_numeric($_POST['weight_kg'] ?? '') ? (float) $_POST['weight_kg'] : null;
    $bmi = ($height_cm && $weight_kg) ? round($weight_kg / (($height_cm / 100) ** 2), 1) : null;

    /* ── Structured clinical answers (Google Form sections 5–10) → one JSON column ── */
    $g = fn($k) => trim($_POST[$k] ?? '') ?: null;
    $garr = fn($k) => array_values(array_filter(array_map('trim', (array) ($_POST[$k] ?? []))));
    $health_arr = [
        'eye' => [
            'uses_glasses'      => $g('eye_uses_glasses'),
            'glasses_in_use'    => $g('eye_glasses_in_use'),
            'glasses_power'     => $g('eye_glasses_power'),
            'conditions'        => $g('eye_conditions'),
            'last_ophthal_exam' => $g('eye_last_exam'),
            'exam_remarks'      => $g('eye_exam_remarks'),
        ],
        'dental' => [
            'present_condition' => $g('dental_condition'),
            'cavities'          => $g('dental_cavities'),
            'bleeding_gums'     => $g('dental_bleeding'),
            'discoloration'     => $g('dental_discolor'),
            'toothache'         => $g('dental_toothache'),
            'alignment_ok'      => $g('dental_alignment'),
            'hygiene_habits'    => $g('dental_hygiene'),
            'brush_frequency'   => $g('dental_brush_freq'),
        ],
        'immunization' => [
            'vaccination_status' => $g('imm_vaccination'),
            'deworming_taken'    => $g('imm_deworming'),
            'deworming_where'    => $g('imm_deworming_where'),
        ],
        'allergy' => [
            'has_allergy' => $g('allergy_has'),
            'types'       => $g('allergy_types'),
            'other_type'  => $g('allergy_other'),
            'detail'      => $g('allergy_detail'),
        ],
        'chronic' => [
            'has_chronic' => $g('chronic_has'),
            'type'        => $g('chronic_type'),
            'detail'      => $g('chronic_detail'),
            'additional'  => $g('additional_medical'),
        ],
        'surgical' => [
            'had_surgery'            => $g('surg_had'),
            'surgery_detail'         => $g('surg_detail'),
            'hospitalized'           => $g('surg_hospitalized'),
            'hospitalization_reason' => $g('surg_hosp_reason'),
            'record_available'       => $g('surg_record_available'),
        ],
        'nutrition' => [
            'dietary_pref'      => $g('nut_diet'),
            'adequate_food'     => $g('nut_adequate'),
            'physical_activity' => $g('nut_activity'),
            'screen_time'       => $g('nut_screen'),
        ],
    ];
    $health_json = json_encode($health_arr, JSON_UNESCAPED_UNICODE);

    /* Mirror the structured allergy / chronic answers into the flat legacy
       columns so existing admin list views stay meaningful. */
    if (!$allergies && ($health_arr['allergy']['has_allergy'] ?? '') === 'Yes') {
        $allergies = trim(implode(', ', (array) $health_arr['allergy']['types'])
            . ' ' . ($health_arr['allergy']['other_type'] ?? '')
            . ' ' . ($health_arr['allergy']['detail'] ?? '')) ?: 'Yes (unspecified)';
    }
    if (!$conditions && ($health_arr['chronic']['has_chronic'] ?? '') === 'Yes') {
        $conditions = trim(($health_arr['chronic']['type'] ?? '') . ' — ' . ($health_arr['chronic']['detail'] ?? ''), ' —')
            ?: 'Yes (unspecified)';
    }

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

        $row = [
            'token'                => $token,
            'school_id'            => $school_id,
            'school_name_manual'   => $school_manual ?: null,
            'parent_name'          => $parent_name,
            'relation'             => $relation,
            'parent_mobile'        => $parent_mobile,
            'parent_email'         => $parent_email,
            'parent_aadhar_last4'  => $aadhar_last4,
            'parent_aadhar_mobile' => $parent_aadhar_mobile,
            'student_name'         => $student_name,
            'student_dob'          => $student_dob,
            'student_gender'       => $student_gender,
            'student_class'        => $student_class ?: null,
            'student_section'      => $student_sec ?: null,
            'student_roll_no'      => $student_roll ?: null,
            'student_apaar_id'     => $student_apaar,
            'student_address'      => $student_address,
            'student_city'         => $student_city,
            'student_state'        => $student_state,
            'student_pincode'      => $student_pincode,
            'student_abha_number'  => $student_abha_number,
            'student_abha_address' => $student_abha_address,
            'student_abha_status'  => $abha_status,
            'blood_group'          => $blood_group,
            'height_cm'            => $height_cm,
            'weight_kg'            => $weight_kg,
            'bmi'                  => $bmi,
            'known_allergies'      => $allergies,
            'existing_conditions'  => $conditions,
            'current_medications'  => $medications,
            'health_data'          => $health_json,
            'file_id_proof'        => pcf_save_upload('file_id_proof'),
            'file_eye_report'      => pcf_save_upload('file_eye_report'),
            'file_dental_report'   => pcf_save_upload('file_dental_report'),
            'file_vaccination_cert' => pcf_save_upload('file_vaccination_cert'),
            'file_medical_records' => pcf_save_upload('file_medical_records'),
            'consent_items'        => $consent_json,
            'consent_given'        => $consent_given,
            'declaration_text'     => $decl,
            'ip_address'           => $ip,
            'user_agent'           => $ua,
        ];

        $cols  = array_keys($row);
        $ph    = implode(',', array_fill(0, count($cols), '?'));
        $types = '';
        $vals  = [];
        foreach ($row as $val) {
            $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            $vals[] = $val;
        }
        $ins = $conn->prepare("INSERT INTO parent_consent_forms (`" . implode('`,`', $cols) . "`) VALUES ($ph)");
        $ins->bind_param($types, ...$vals);
        if ($ins->execute()) {
            $success = true;
        } else {
            $error = 'Submission failed. Please try again.';
        }
        $ins->close();
    }

}

/* ── Tiny render helpers for the assessment fields ── */
function pcf_old($k, $d = '') { return htmlspecialchars((string) ($_POST[$k] ?? $d), ENT_QUOTES); }

function pcf_radio(string $name, array $opts): string
{
    $cur = (string) ($_POST[$name] ?? '');
    $h = '<div class="opt-row">';
    foreach ($opts as $o) {
        $h .= '<label class="form-check"><input class="form-check-input" type="radio" name="' . $name . '" value="'
            . htmlspecialchars($o, ENT_QUOTES) . '"' . ($cur === (string) $o ? ' checked' : '')
            . '> <span class="form-check-label">' . htmlspecialchars($o) . '</span></label>';
    }
    return $h . '</div>';
}

function pcf_checks(string $name, array $opts): string
{
    $cur = array_map('strval', (array) ($_POST[$name] ?? []));
    $h = '<div class="opt-row">';
    foreach ($opts as $o) {
        $h .= '<label class="form-check"><input class="form-check-input" type="checkbox" name="' . $name . '[]" value="'
            . htmlspecialchars($o, ENT_QUOTES) . '"' . (in_array((string) $o, $cur, true) ? ' checked' : '')
            . '> <span class="form-check-label">' . htmlspecialchars($o) . '</span></label>';
    }
    return $h . '</div>';
}

function pcf_select(string $name, array $opts, string $ph = '— Select —'): string
{
    $cur = (string) ($_POST[$name] ?? '');
    $h = '<select name="' . $name . '" class="form-select"><option value="">' . htmlspecialchars($ph) . '</option>';
    foreach ($opts as $o) {
        $h .= '<option value="' . htmlspecialchars($o, ENT_QUOTES) . '"' . ($cur === (string) $o ? ' selected' : '') . '>'
            . htmlspecialchars($o) . '</option>';
    }
    return $h . '</select>';
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

        /* ── Assessment field helpers ── */
        .opt-row { display: flex; flex-wrap: wrap; gap: 8px 18px; padding-top: 2px; }
        .opt-row .form-check { padding-left: 1.6em; margin: 0; }
        .opt-row .form-check-label { font-size: .87rem; }
        .sub-head {
            font-size: .72rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase;
            color: var(--primary); margin: 4px 0 2px; display: flex; align-items: center; gap: 6px;
        }
        .q-label { font-size: .87rem; font-weight: 600; color: #374151; margin-bottom: 3px; display: block; }
        .cond { display: none; }
        .cond.show { display: block; }
        .file-drop {
            border: 1.5px dashed #cbd5e1; border-radius: 10px; padding: 12px 14px;
            font-size: .82rem; color: #64748b; background: #f8fafc;
        }
        .file-drop input[type=file] { font-size: .8rem; }

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
        <h1><i class="fas fa-file-signature me-2"></i>Student Health Assessment &amp; Consent</h1>
        <p>Please fill in your child's details and health information carefully</p>
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

            <form method="POST" id="cForm" enctype="multipart/form-data" novalidate>
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
                                    maxlength="4" inputmode="numeric" value="<?= pcf_old('parent_aadhar') ?>">
                                <div class="hint"><i class="fas fa-lock me-1"></i>Only last 4 digits stored — full Aadhaar is never saved</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Aadhaar-linked Mobile Number</label>
                                <input type="tel" name="parent_aadhar_mobile" class="form-control" placeholder="10-digit number"
                                    maxlength="10" inputmode="numeric" value="<?= pcf_old('parent_aadhar_mobile') ?>">
                                <div class="hint">Mobile number registered with the parent/guardian's Aadhaar.</div>
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
                                    value="<?= pcf_old('student_roll_no') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Student ID / APAAR ID</label><input type="text"
                                    name="student_apaar_id" class="form-control" placeholder="APAAR / Student ID"
                                    value="<?= pcf_old('student_apaar_id') ?>"></div>
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
                                    value="<?= pcf_old('student_pincode') ?>"></div>
                            <div class="col-12">
                                <div class="file-drop">
                                    <label class="q-label mb-1"><i class="fas fa-paperclip me-1" style="color:var(--primary)"></i>Aadhaar Card / Birth Certificate <span style="font-weight:400;color:#94a3b8">(optional — JPG / PNG / PDF, max 6 MB)</span></label>
                                    <input type="file" name="file_id_proof" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                </div>
                            </div>
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
                            <div class="col-12">
                                <label class="q-label">ABHA Status</label>
                                <?= pcf_radio('student_abha_status', ['Generated', 'Not Generated']) ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ABHA Number</label>
                                <input type="text" name="student_abha_number" class="form-control"
                                    placeholder="XX-XXXX-XXXX-XXXX" maxlength="17" inputmode="numeric"
                                    value="<?= pcf_old('student_abha_number') ?>">
                                <div class="hint">14-digit Ayushman Bharat Health Account number.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ABHA Address</label>
                                <input type="text" name="student_abha_address" class="form-control"
                                    placeholder="name@abdm"
                                    value="<?= pcf_old('student_abha_address') ?>">
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

                <!-- 5. General Health -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">5</div>
                        <h5><i class="fas fa-weight-scale me-2" style="color:var(--primary)"></i>General Health</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-3"><label class="form-label">Height (cm)</label>
                                <input type="number" step="0.1" min="0" name="height_cm" id="ht" class="form-control" value="<?= pcf_old('height_cm') ?>"></div>
                            <div class="col-6 col-md-3"><label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.1" min="0" name="weight_kg" id="wt" class="form-control" value="<?= pcf_old('weight_kg') ?>"></div>
                            <div class="col-6 col-md-3"><label class="form-label">BMI</label>
                                <input type="text" id="bmi" class="form-control" readonly placeholder="auto"></div>
                            <div class="col-6 col-md-3"><label class="form-label">Blood Group</label>
                                <?= pcf_select('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], '— if known —') ?></div>
                        </div>
                    </div>
                </div>

                <!-- 6. Eye Health -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">6</div>
                        <h5><i class="fas fa-eye me-2" style="color:var(--primary)"></i>Eye Health</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="q-label">Does the student use glasses?</label>
                                <?= pcf_radio('eye_uses_glasses', ['Yes', 'No']) ?></div>
                            <div class="col-12 cond" data-when="eye_uses_glasses" data-eq="Yes">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="q-label">Are the glasses currently being used?</label>
                                        <?= pcf_radio('eye_glasses_in_use', ['Yes', 'No', 'Occasionally']) ?></div>
                                    <div class="col-md-6"><label class="form-label">Power / number of glasses</label>
                                        <input type="text" name="eye_glasses_power" class="form-control" value="<?= pcf_old('eye_glasses_power') ?>"></div>
                                </div>
                            </div>
                            <div class="col-12"><label class="q-label">Does the student have any type of the following conditions?</label>
                                <?= pcf_radio('eye_conditions', ['Squint', 'Watery eyes / excessive tearing', 'Recurrent or excessive rubbing of eyes', 'Other', 'None']) ?></div>
                            <div class="col-md-6"><label class="q-label">Last examined by an ophthalmologist?</label>
                                <?= pcf_radio('eye_last_exam', ['Date confirmed', 'Date not confirmed']) ?></div>
                            <div class="col-md-6"><label class="form-label">Date / remarks</label>
                                <input type="text" name="eye_exam_remarks" class="form-control" placeholder="e.g. Jan 2025 — normal" value="<?= pcf_old('eye_exam_remarks') ?>"></div>
                            <div class="col-12">
                                <div class="file-drop">
                                    <label class="q-label mb-1"><i class="fas fa-paperclip me-1" style="color:var(--primary)"></i>Eye examination report <span style="font-weight:400;color:#94a3b8">(optional)</span></label>
                                    <input type="file" name="file_eye_report" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. Dental Health -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">7</div>
                        <h5><i class="fas fa-tooth me-2" style="color:var(--primary)"></i>Dental Health</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="q-label">Present dental condition</label>
                                <?= pcf_radio('dental_condition', ['Normal', 'Abnormal']) ?></div>
                            <div class="col-12 cond" data-when="dental_condition" data-eq="Abnormal">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="q-label">Cavities</label><?= pcf_radio('dental_cavities', ['Yes', 'No']) ?></div>
                                    <div class="col-md-6"><label class="q-label">Bleeding from gums</label><?= pcf_radio('dental_bleeding', ['Yes', 'No']) ?></div>
                                    <div class="col-md-6"><label class="q-label">Discoloration of teeth</label><?= pcf_radio('dental_discolor', ['Yellow', 'Black']) ?></div>
                                    <div class="col-md-6"><label class="q-label">Toothache</label><?= pcf_radio('dental_toothache', ['Yes', 'No']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6"><label class="q-label">Proper alignment of teeth?</label><?= pcf_radio('dental_alignment', ['Yes', 'No']) ?></div>
                            <div class="col-md-6"><label class="q-label">Dental hygiene habits</label><?= pcf_radio('dental_hygiene', ['Brushing', 'Flossing', 'Both', 'Neither']) ?></div>
                            <div class="col-md-6"><label class="q-label">How many times does the student brush per day?</label><?= pcf_radio('dental_brush_freq', ['Once', 'Twice', 'Three or more']) ?></div>
                            <div class="col-12">
                                <div class="file-drop">
                                    <label class="q-label mb-1"><i class="fas fa-paperclip me-1" style="color:var(--primary)"></i>Dental examination report <span style="font-weight:400;color:#94a3b8">(optional)</span></label>
                                    <input type="file" name="file_dental_report" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8. Immunization -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">8</div>
                        <h5><i class="fas fa-syringe me-2" style="color:var(--primary)"></i>Immunization</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="q-label">Vaccination status</label><?= pcf_radio('imm_vaccination', ['Vaccinated', 'Not Vaccinated']) ?></div>
                            <div class="col-md-6"><label class="q-label">Has the student taken deworming medicine?</label><?= pcf_radio('imm_deworming', ['Yes', 'No']) ?></div>
                            <div class="col-12 cond" data-when="imm_deworming" data-eq="Yes">
                                <label class="q-label">If yes, given where?</label><?= pcf_radio('imm_deworming_where', ['In school', 'By local doctor']) ?>
                            </div>
                            <div class="col-12">
                                <div class="file-drop">
                                    <label class="q-label mb-1"><i class="fas fa-paperclip me-1" style="color:var(--primary)"></i>Vaccination certificate <span style="font-weight:400;color:#94a3b8">(if available)</span></label>
                                    <input type="file" name="file_vaccination_cert" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 9. Medical History & Allergies -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">9</div>
                        <h5><i class="fas fa-notes-medical me-2" style="color:var(--primary)"></i>Medical History &amp; Allergies</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="q-label">Does the student have any known allergy?</label><?= pcf_radio('allergy_has', ['Yes', 'No']) ?></div>
                            <div class="col-12 cond" data-when="allergy_has" data-eq="Yes">
                                <div class="row g-3">
                                    <div class="col-12"><label class="q-label">If yes, type of allergy</label><?= pcf_radio('allergy_types', ['Medicine', 'Dust', 'Smoke', 'Food', 'None', 'Other']) ?></div>
                                    <div class="col-md-6"><label class="form-label">Other type (if any)</label><input type="text" name="allergy_other" class="form-control" value="<?= pcf_old('allergy_other') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Detail of allergy</label><input type="text" name="allergy_detail" class="form-control" value="<?= pcf_old('allergy_detail') ?>"></div>
                                </div>
                            </div>
                            <div class="col-12"><label class="q-label">Does the student have any chronic (long-duration) illness?</label><?= pcf_radio('chronic_has', ['Yes', 'No']) ?></div>
                            <div class="col-12 cond" data-when="chronic_has" data-eq="Yes">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="q-label">Type of chronic illness</label><?= pcf_radio('chronic_type', ['Asthma', 'Diabetes', 'Seizure', 'Other', 'None']) ?></div>
                                    <div class="col-12"><label class="form-label">Details of chronic disease</label><textarea name="chronic_detail" class="form-control" rows="2"><?= pcf_old('chronic_detail') ?></textarea></div>
                                </div>
                            </div>
                            <div class="col-12"><label class="form-label">Current medications <span style="font-weight:400;color:#94a3b8">(optional)</span></label>
                                <input type="text" name="current_medications" class="form-control" placeholder="e.g. Salbutamol inhaler" value="<?= pcf_old('current_medications') ?>"></div>
                            <div class="col-12"><label class="form-label">Additional medical details <span style="font-weight:400;color:#94a3b8">(optional)</span></label>
                                <textarea name="additional_medical" class="form-control" rows="2"><?= pcf_old('additional_medical') ?></textarea></div>
                        </div>
                    </div>
                </div>

                <!-- 10. Surgical & Hospitalization History -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">10</div>
                        <h5><i class="fas fa-hospital me-2" style="color:var(--primary)"></i>Surgical &amp; Hospitalization History</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="q-label">History of any surgery?</label><?= pcf_radio('surg_had', ['Yes', 'No']) ?></div>
                            <div class="col-12 cond" data-when="surg_had" data-eq="Yes">
                                <label class="form-label">Name / type of surgery <span style="font-weight:400;color:#94a3b8">(plain language is fine)</span></label>
                                <input type="text" name="surg_detail" class="form-control" value="<?= pcf_old('surg_detail') ?>">
                            </div>
                            <div class="col-12"><label class="q-label">Was the student ever admitted to a hospital?</label><?= pcf_radio('surg_hospitalized', ['Yes', 'No']) ?></div>
                            <div class="col-12 cond" data-when="surg_hospitalized" data-eq="Yes">
                                <label class="form-label">Reason for hospital admission</label>
                                <textarea name="surg_hosp_reason" class="form-control" rows="2"><?= pcf_old('surg_hosp_reason') ?></textarea>
                            </div>
                            <div class="col-12"><label class="q-label">Is a patient / medical record available?</label><?= pcf_radio('surg_record_available', ['Yes', 'No']) ?></div>
                            <div class="col-12">
                                <div class="file-drop">
                                    <label class="q-label mb-1"><i class="fas fa-paperclip me-1" style="color:var(--primary)"></i>Medical records <span style="font-weight:400;color:#94a3b8">(optional)</span></label>
                                    <input type="file" name="file_medical_records" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 11. Nutrition & Dietary Habits -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">11</div>
                        <h5><i class="fas fa-utensils me-2" style="color:var(--primary)"></i>Nutrition &amp; Dietary Habits</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="q-label">Dietary preference</label><?= pcf_radio('nut_diet', ['Vegetarian', 'Non vegetarian', 'Other']) ?></div>
                            <div class="col-md-6"><label class="q-label">Is adequate food provided?</label><?= pcf_radio('nut_adequate', ['Yes', 'No']) ?></div>
                            <div class="col-12"><label class="q-label">Daily physical activity</label><?= pcf_radio('nut_activity', ['Less than 30 minutes', '30 minutes to 60 minutes', 'More than 60 minutes', 'No regular physical activity']) ?></div>
                            <div class="col-12"><label class="q-label">Daily screen time</label><?= pcf_radio('nut_screen', ['Less than 1 hour', '1 hour to 2 hours', '2 hours to 4 hours', 'More than 4 hours']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- 12. Consent -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">12</div>
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

                <!-- 13. Declaration -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">13</div>
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

        /* ── BMI auto-calc ── */
        const ht = document.getElementById('ht'), wt = document.getElementById('wt'), bmiEl = document.getElementById('bmi');
        function calcBmi() {
            const h = parseFloat(ht.value), w = parseFloat(wt.value);
            bmiEl.value = (h > 0 && w > 0) ? (w / ((h / 100) ** 2)).toFixed(1) : '';
        }
        if (ht && wt) { ht.addEventListener('input', calcBmi); wt.addEventListener('input', calcBmi); calcBmi(); }

        /* ── Conditional "If yes" blocks ── */
        function syncConds() {
            document.querySelectorAll('.cond[data-when]').forEach(box => {
                const name = box.dataset.when, want = box.dataset.eq;
                const picked = document.querySelector('input[name="' + name + '"]:checked');
                box.classList.toggle('show', !!picked && picked.value === want);
            });
        }
        document.querySelectorAll('#cForm input[type=radio]').forEach(r => r.addEventListener('change', syncConds));
        syncConds();
    </script>
</body>

</html>
