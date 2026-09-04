<?php
/**
 * Parent Health Checkup Consent Form
 * Public page — no login required.
 */
include_once __DIR__ . "/../config/connect.php";

/* Schema:
 *   database/migration_parent_consent_forms.sql   (parent_consent_forms)
 *   database/migration_school_health_plans.sql     (school_health_plans)
 */

require_once __DIR__ . '/../config/payment.php';   // RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET
require_once __DIR__ . '/../util/mail_config.php'; // Mailer
require_once __DIR__ . '/../util/function.php';    // contact_us()

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

/* ── Health plans offered on this form (age-picked, then paid) ── */
$consent_plans = [];
$cpr = $conn->query("SELECT * FROM school_health_plans WHERE is_active = 1 AND show_on_consent = 1 ORDER BY sort_order ASC, price ASC, id ASC");
if ($cpr) {
    while ($r = $cpr->fetch_assoc()) {
        $r['feature_list'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $r['features']))));
        $consent_plans[] = $r;
    }
}
$plans_enabled = !empty($consent_plans);

/**
 * Age in whole years on a given date (defaults today). Returns null for a bad date.
 */
function pcf_age_from_dob(?string $dob): ?int
{
    if (!$dob) return null;
    $d = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$d || $d->format('Y-m-d') !== $dob) return null;
    return (int) $d->diff(new DateTime('today'))->y;
}

/**
 * The plan a child of this age is enrolled in — first match by sort_order.
 * $plans is the $consent_plans array. Returns the plan row or null.
 */
function pcf_plan_for_age(array $plans, ?int $age): ?array
{
    if ($age === null) return null;
    foreach ($plans as $p) {
        $min = ($p['age_min'] === null || $p['age_min'] === '') ? null : (int) $p['age_min'];
        $max = ($p['age_max'] === null || $p['age_max'] === '') ? null : (int) $p['age_max'];
        if (($min === null || $age >= $min) && ($max === null || $age <= $max)) return $p;
    }
    return null;
}

/**
 * Create a Razorpay order. Returns [orderArray|null, errorMessage].
 */
function pcf_create_razorpay_order(int $amountPaise, string $receipt, array $notes): array
{
    if (!defined('RAZORPAY_KEY_ID') || !RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
        error_log('[parent-consent] Razorpay keys not configured');
        return [null, 'Online payment is temporarily unavailable. Please try again later or contact the school.'];
    }
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'   => $amountPaise,
            'currency' => 'INR',
            'receipt'  => $receipt,
            'notes'    => $notes,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr || $httpCode !== 200) {
        error_log('[parent-consent] Razorpay order failed: ' . $curlErr . ' HTTP ' . $httpCode . ' ' . $response);
        return [null, 'Could not start the payment. Please try again.'];
    }
    $order = json_decode($response, true);
    if (empty($order['id'])) {
        error_log('[parent-consent] Razorpay unexpected order response: ' . $response);
        return [null, 'Could not start the payment. Please try again.'];
    }
    return [$order, ''];
}

$success = false;
$error = '';
$errors = [];          // field_key => message  (shown inline + in summary)
$token = '';

// Pre-select a school when the link is shared from the admin panel (?school_id=)
$prefill_school = (int) ($_GET['school_id'] ?? $_POST['school_id'] ?? 0);
// Deep-link from school-program.php ("Choose <plan>") — informational only; age still decides.
$prefill_plan = (int) ($_GET['plan'] ?? 0);

/* ── Resume an abandoned payment: ?resume=<token> ── */
$resume_row = null;
if (!empty($_GET['resume'])) {
    $rt = preg_replace('/[^a-f0-9]/', '', (string) $_GET['resume']);
    if (strlen($rt) === 32) {
        $rs = $conn->prepare("SELECT * FROM parent_consent_forms WHERE token = ? AND payment_status = 'pending' LIMIT 1");
        $rs->bind_param('s', $rt);
        $rs->execute();
        $resume_row = $rs->get_result()->fetch_assoc() ?: null;
    }
}

$pcf_action = $_POST['pcf_action'] ?? '';

/* ═══════════════════════════════════════════════════════════════
   AJAX: verify a completed Razorpay payment → mark paid + email
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pcf_action === 'verify_payment') {
    header('Content-Type: application/json');
    $rpOrderId   = trim($_POST['razorpay_order_id'] ?? '');
    $rpPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
    $rpSignature = trim($_POST['razorpay_signature'] ?? '');
    $formToken   = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['token'] ?? ''));

    if (!$rpOrderId || !$rpPaymentId || !$rpSignature || strlen($formToken) !== 32) {
        echo json_encode(['success' => false, 'message' => 'Payment was not completed. Please try again.']);
        exit;
    }
    if (!defined('RAZORPAY_KEY_SECRET') || !RAZORPAY_KEY_SECRET) {
        echo json_encode(['success' => false, 'message' => 'Payment verification is unavailable right now. If money was deducted it will auto-refund — please contact the school.']);
        exit;
    }
    $expected = hash_hmac('sha256', $rpOrderId . '|' . $rpPaymentId, RAZORPAY_KEY_SECRET);
    if (!hash_equals($expected, $rpSignature)) {
        error_log('[parent-consent] signature mismatch order=' . $rpOrderId);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted it will be refunded automatically — please contact the school.']);
        exit;
    }

    $ps = $conn->prepare("SELECT * FROM parent_consent_forms WHERE token = ? AND razorpay_order_id = ? AND payment_status = 'pending' LIMIT 1");
    $ps->bind_param('ss', $formToken, $rpOrderId);
    $ps->execute();
    $pcf = $ps->get_result()->fetch_assoc();
    if (!$pcf) {
        // Idempotency: already verified is still a success for the user.
        $chk = $conn->prepare("SELECT id FROM parent_consent_forms WHERE token = ? AND payment_status = 'paid' LIMIT 1");
        $chk->bind_param('s', $formToken);
        $chk->execute();
        if ($chk->get_result()->num_rows) {
            echo json_encode(['success' => true, 'ref' => strtoupper(substr($formToken, 0, 8))]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Could not match this payment to your form. Please contact the school with your reference number.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE parent_consent_forms SET payment_status = 'paid', razorpay_payment_id = ?, paid_at = NOW() WHERE id = ?");
    $upd->bind_param('si', $rpPaymentId, $pcf['id']);
    $upd->execute();

    /* ── School name for the emails ── */
    $schoolName = $pcf['school_name_manual'] ?: 'your school';
    if (!empty($pcf['school_id'])) {
        $sn = $conn->query("SELECT school_name FROM schools WHERE id = " . (int) $pcf['school_id']);
        if ($sn && $sn->num_rows) $schoolName = $sn->fetch_assoc()['school_name'];
    }
    $ref      = strtoupper(substr($formToken, 0, 8));
    $amount   = number_format((float) $pcf['plan_price']);
    $adminUrl = rtrim($_ENV['SITE'] ?? BASE_URL, '/') . '/admin/parent-consent-view.php?id=' . (int) $pcf['id'];

    try {
        $mailer = new Mailer();

        /* 1. Parent */
        if (!empty($pcf['parent_email'])) {
            $pHtml = "
                <p>Hello <strong>" . htmlspecialchars($pcf['parent_name']) . "</strong>,</p>
                <p>Payment received &mdash; <strong>" . htmlspecialchars($pcf['student_name']) . "</strong> is now enrolled in the
                school health programme.</p>
                <div style='background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
                  <strong>Reference:</strong> {$ref}<br>
                  <strong>Plan:</strong> " . htmlspecialchars($pcf['plan_name']) . "<br>
                  <strong>Amount paid:</strong> &#8377;{$amount}<br>
                  <strong>Payment ID:</strong> " . htmlspecialchars($rpPaymentId) . "<br>
                  <strong>School:</strong> " . htmlspecialchars($schoolName) . "
                </div>
                <p>Your consent and your child's health details have been recorded securely as per ABDM / ABHA
                guidelines. The school health team will schedule the checkup and share updates with you.</p>
                <p style='font-size:13px;color:#6b7280;'>Keep the reference number above for any queries.</p>
            ";
            $pText = "Hello {$pcf['parent_name']},\n\nPayment received. {$pcf['student_name']} is enrolled in the school health programme.\n\n"
                . "Reference: {$ref}\nPlan: {$pcf['plan_name']}\nAmount paid: Rs {$amount}\nPayment ID: {$rpPaymentId}\nSchool: {$schoolName}\n\n"
                . "Your consent and health details are recorded securely. The school health team will schedule the checkup.";
            $mailer->sendCustom($pcf['parent_email'], $pcf['parent_name'], 'Payment Received — ' . $pcf['student_name'] . ' enrolled', $pHtml, $pText);
        }

        /* 2. Admin */
        $contact    = function_exists('contact_us') ? contact_us() : null;
        $adminEmail = $contact['email'] ?? (defined('MAIL_USERNAME') ? MAIL_USERNAME : 'support@rejuvenatedigitalhealth.com');
        $aHtml = "
            <p>A parent has completed a paid health-programme enrolment.</p>
            <table style='width:100%;border-collapse:collapse;font-size:14px;'>
              <tr><td style='padding:8px 0;font-weight:bold;width:40%;'>Reference</td><td>{$ref}</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>Student</td><td>" . htmlspecialchars($pcf['student_name']) . "</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>Parent</td><td>" . htmlspecialchars($pcf['parent_name']) . " (" . htmlspecialchars($pcf['parent_mobile']) . ")</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>School</td><td>" . htmlspecialchars($schoolName) . "</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>Plan</td><td>" . htmlspecialchars($pcf['plan_name']) . "</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>Amount</td><td>&#8377;{$amount}</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>Razorpay Payment ID</td><td>" . htmlspecialchars($rpPaymentId) . "</td></tr>
              <tr><td style='padding:8px 0;font-weight:bold;'>Order ID</td><td>" . htmlspecialchars($rpOrderId) . "</td></tr>
            </table>
            <p style='margin-top:16px;'><a href='{$adminUrl}'>Open the consent record in the admin panel &rarr;</a></p>
        ";
        $aText = "Paid health-programme enrolment\n\nReference: {$ref}\nStudent: {$pcf['student_name']}\nParent: {$pcf['parent_name']} ({$pcf['parent_mobile']})\n"
            . "School: {$schoolName}\nPlan: {$pcf['plan_name']}\nAmount: Rs {$amount}\nPayment ID: {$rpPaymentId}\nOrder ID: {$rpOrderId}\n\n{$adminUrl}";
        $mailer->sendCustom($adminEmail, 'Admin', 'Paid enrolment — ' . $pcf['student_name'] . ' (' . $schoolName . ')', $aHtml, $aText);
    } catch (Throwable $mailErr) {
        error_log('[parent-consent] payment email failed: ' . $mailErr->getMessage());
    }

    echo json_encode(['success' => true, 'ref' => $ref]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════
   AJAX: create a fresh Razorpay order for an abandoned (pending) row
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pcf_action === 'resume_pay') {
    header('Content-Type: application/json');
    $rt = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['resume_token'] ?? ''));
    if (strlen($rt) !== 32) {
        echo json_encode(['success' => false, 'message' => 'Invalid resume link.']);
        exit;
    }
    $rs = $conn->prepare("SELECT * FROM parent_consent_forms WHERE token = ? AND payment_status = 'pending' LIMIT 1");
    $rs->bind_param('s', $rt);
    $rs->execute();
    $pr = $rs->get_result()->fetch_assoc();
    if (!$pr || $pr['plan_price'] === null) {
        echo json_encode(['success' => false, 'message' => 'This payment link is no longer valid — it may already be paid.']);
        exit;
    }
    if ((float) $pr['plan_price'] <= 0) {
        $conn->query("UPDATE parent_consent_forms SET payment_status='paid', paid_at=NOW() WHERE id=" . (int) $pr['id']);
        echo json_encode(['success' => true, 'free' => true, 'ref' => strtoupper(substr($rt, 0, 8))]);
        exit;
    }
    $amountPaise = (int) round(((float) $pr['plan_price']) * 100);
    [$order, $orderErr] = pcf_create_razorpay_order(
        $amountPaise,
        'pcf_' . (int) $pr['id'] . '_' . time(),
        ['consent_id' => (int) $pr['id'], 'plan_id' => (int) $pr['plan_id'], 'purpose' => 'school_health_consent_resume']
    );
    if (!$order) {
        echo json_encode(['success' => false, 'message' => $orderErr]);
        exit;
    }
    $oid = $order['id'];
    $uo = $conn->prepare("UPDATE parent_consent_forms SET razorpay_order_id = ? WHERE id = ?");
    $uo->bind_param('si', $oid, $pr['id']);
    $uo->execute();
    echo json_encode([
        'success'   => true,
        'key_id'    => RAZORPAY_KEY_ID,
        'order_id'  => $order['id'],
        'amount'    => $amountPaise,
        'currency'  => 'INR',
        'plan_name' => $pr['plan_name'],
        'token'     => $rt,
        'ref'       => strtoupper(substr($rt, 0, 8)),
        'prefill'   => ['name' => $pr['parent_name'], 'email' => $pr['parent_email'] ?? '', 'contact' => $pr['parent_mobile']],
    ]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════
   AJAX: validate the form + create a Razorpay order (pending row)
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($pcf_action === 'create_order' || isset($_POST['submit_consent']))) {
    $is_ajax = $pcf_action === 'create_order';
    $resume_token = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['resume_token'] ?? ''));
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

    /* ─────────────────────────────────────────────
       Field-by-field validation. Every failure is
       recorded against a field key so the form can
       highlight the exact input and list what's
       missing — the parent never gets a vague
       "fill all fields correctly" dead end.
    ───────────────────────────────────────────── */
    $raw_aadhar        = preg_replace('/\D/', '', trim($_POST['parent_aadhar'] ?? ''));
    $raw_student_dob   = trim($_POST['student_dob'] ?? '');

    // 1. School
    if (!empty($schools)) {
        if (!$school_id && !$school_manual) {
            $errors['school'] = 'Please select your child\'s school. If it is not listed, choose "Other" and type the name.';
        }
    } elseif (!$school_manual) {
        $errors['school'] = 'Please enter the school name.';
    }

    // 2. Parent / guardian
    if (mb_strlen($parent_name) < 2) {
        $errors['parent_name'] = 'Please enter the parent / guardian\'s full name.';
    }
    if (!preg_match('/^[6-9]\d{9}$/', $parent_mobile)) {
        $errors['parent_mobile'] = 'Enter a valid 10-digit mobile number (starting with 6, 7, 8 or 9).';
    }
    if ($parent_email !== null && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        $errors['parent_email'] = 'This email address does not look valid. Correct it or leave the field blank.';
    }
    if ($raw_aadhar !== '' && strlen($raw_aadhar) !== 4) {
        $errors['parent_aadhar'] = 'Enter only the last 4 digits of the Aadhaar number.';
    }
    if ($parent_aadhar_mobile !== null && !preg_match('/^[6-9]\d{9}$/', $parent_aadhar_mobile)) {
        $errors['parent_aadhar_mobile'] = 'The Aadhaar-linked mobile must be a valid 10-digit number, or leave it blank.';
    }

    // 3. Student
    if (mb_strlen($student_name) < 2) {
        $errors['student_name'] = 'Please enter the student\'s full name.';
    }
    if ($raw_student_dob === '' && $plans_enabled) {
        $errors['student_dob'] = 'Date of birth is required — the health plan and fee are set from your child\'s age.';
    } elseif ($raw_student_dob !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $raw_student_dob);
        if (!$d || $d->format('Y-m-d') !== $raw_student_dob) {
            $errors['student_dob'] = 'Enter the date of birth in a valid format.';
        } elseif ($d > new DateTime('today')) {
            $errors['student_dob'] = 'The date of birth cannot be in the future.';
        } elseif ($d < new DateTime('-25 years')) {
            $errors['student_dob'] = 'Please re-check the date of birth.';
        }
    }
    if ($student_pincode !== null && !preg_match('/^\d{6}$/', $student_pincode)) {
        $errors['student_pincode'] = 'PIN code must be exactly 6 digits, or leave it blank.';
    }

    // 4. Health ID (ABHA) — $abha_err was set earlier
    if ($abha_err) {
        $errors['student_abha'] = $abha_err;
    }

    // 5. General health — only validated when a value is entered
    if ($height_cm !== null && ($height_cm < 30 || $height_cm > 250)) {
        $errors['height_cm'] = 'Enter the height in centimetres (between 30 and 250), or leave it blank.';
    }
    if ($weight_kg !== null && ($weight_kg < 5 || $weight_kg > 200)) {
        $errors['weight_kg'] = 'Enter the weight in kilograms (between 5 and 200), or leave it blank.';
    }

    // 12 & 13. Consent
    if (!in_array(true, $consent_given_items, true)) {
        $errors['consent'] = 'Please tick at least one health service you are giving consent for.';
    }
    if (!$consent_given) {
        $errors['i_agree'] = 'You must tick the declaration checkbox before submitting the form.';
    }

    /* ── Resolve the plan from the child's age (server-authoritative) ── */
    $pcf_age  = pcf_age_from_dob($raw_student_dob ?: null);
    $pcf_plan = $plans_enabled ? pcf_plan_for_age($consent_plans, $pcf_age) : null;
    if ($plans_enabled && !$errors && !$pcf_plan) {
        $errors['student_dob'] = 'We could not match a health plan to age ' . (int) $pcf_age
            . '. Please check the date of birth, or contact the school.';
    }

    if ($errors) {
        $msg = 'Please complete the highlighted field'
            . (count($errors) > 1 ? 's' : '') . ' — ' . count($errors) . ' item'
            . (count($errors) > 1 ? 's need' : ' needs') . ' your attention.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg, 'errors' => $errors]);
            exit;
        }
        $error = $msg;
    } else {
        /* Reuse a pending row when resuming an abandoned payment, else insert fresh. */
        $existing = null;
        if ($resume_token && strlen($resume_token) === 32) {
            $es = $conn->prepare("SELECT id, token FROM parent_consent_forms WHERE token = ? AND payment_status = 'pending' LIMIT 1");
            $es->bind_param('s', $resume_token);
            $es->execute();
            $existing = $es->get_result()->fetch_assoc() ?: null;
        }
        $token = $existing['token'] ?? bin2hex(random_bytes(16));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $decl = "I, {$parent_name}, hereby give consent for the health checkup of my ward {$student_name}.";

        $plan_id    = $pcf_plan ? (int) $pcf_plan['id'] : null;
        $plan_name  = $pcf_plan['name'] ?? null;
        $plan_price = $pcf_plan ? (float) $pcf_plan['price'] : null;

        $row = [
            'token'                => $token,
            'school_id'            => $school_id,
            'school_name_manual'   => $school_manual ?: null,
            'plan_id'              => $plan_id,
            'plan_name'            => $plan_name,
            'plan_price'           => $plan_price,
            'payment_status'       => 'pending',
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
        $types = '';
        $vals  = [];
        foreach ($row as $val) {
            $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            $vals[] = $val;
        }

        $consent_id = 0;
        if ($existing) {
            $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
            $u = $conn->prepare("UPDATE parent_consent_forms SET $set, razorpay_order_id = NULL WHERE id = ?");
            $existing_id = (int) $existing['id'];
            $upvals = array_merge($vals, [$existing_id]);
            $u->bind_param($types . 'i', ...$upvals);
            $u->execute();
            $consent_id = (int) $existing['id'];
            $u->close();
        } else {
            $ph  = implode(',', array_fill(0, count($cols), '?'));
            $ins = $conn->prepare("INSERT INTO parent_consent_forms (`" . implode('`,`', $cols) . "`) VALUES ($ph)");
            $ins->bind_param($types, ...$vals);
            if (!$ins->execute()) {
                $ins->close();
                $out = ['success' => false, 'message' => 'Could not save the form. Please try again.'];
                if ($is_ajax) { header('Content-Type: application/json'); echo json_encode($out); exit; }
                $error = $out['message'];
                goto pcf_done;
            }
            $consent_id = (int) $ins->insert_id;
            $ins->close();
        }

        /* ── Free plan (₹0) — nothing to charge, mark paid straight away ── */
        if ($plan_price !== null && $plan_price <= 0) {
            $conn->query("UPDATE parent_consent_forms SET payment_status='paid', paid_at=NOW() WHERE id=" . $consent_id);
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'free' => true, 'ref' => strtoupper(substr($token, 0, 8))]);
                exit;
            }
            $success = true;
            goto pcf_done;
        }

        /* ── Create the Razorpay order ── */
        $amountPaise = (int) round(((float) $plan_price) * 100);
        [$order, $orderErr] = pcf_create_razorpay_order(
            $amountPaise,
            'pcf_' . $consent_id . '_' . time(),
            ['consent_id' => $consent_id, 'plan_id' => (int) $plan_id, 'purpose' => 'school_health_consent']
        );
        if (!$order) {
            $out = ['success' => false, 'message' => $orderErr, 'token' => $token];
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode($out); exit; }
            $error = $orderErr;
            goto pcf_done;
        }
        $oid = $order['id'];
        $uo = $conn->prepare("UPDATE parent_consent_forms SET razorpay_order_id = ? WHERE id = ?");
        $uo->bind_param('si', $oid, $consent_id);
        $uo->execute();

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'  => true,
                'key_id'   => RAZORPAY_KEY_ID,
                'order_id' => $order['id'],
                'amount'   => $amountPaise,
                'currency' => 'INR',
                'plan_name' => $plan_name,
                'token'    => $token,
                'ref'      => strtoupper(substr($token, 0, 8)),
                'prefill'  => [
                    'name'    => $parent_name,
                    'email'   => $parent_email ?? '',
                    'contact' => $parent_mobile,
                ],
            ]);
            exit;
        }
        // Non-AJAX (JS disabled): the wizard can't run without JS anyway.
        $error = 'Please enable JavaScript to complete the secure payment.';
    }

    pcf_done:
}

/* ── Tiny render helpers for the assessment fields ── */
function pcf_old($k, $d = '') { return htmlspecialchars((string) ($_POST[$k] ?? $d), ENT_QUOTES); }

/* Inline validation error under a field (empty string when the field is OK). */
function pcf_err(string $key): string
{
    global $errors;
    return isset($errors[$key])
        ? '<div class="field-error"><i class="fas fa-circle-exclamation"></i> ' . htmlspecialchars($errors[$key]) . '</div>'
        : '';
}

/* " is-invalid" class fragment for an <input>/<select> that failed validation. */
function pcf_inv(string $key): string
{
    global $errors;
    return isset($errors[$key]) ? ' is-invalid' : '';
}

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
    <?php if ($plans_enabled): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>
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

        /* ── Step tabs ── */
        .step-nav {
            display: flex;
            gap: 6px;
            max-width: 760px;
            margin: 0 auto 18px;
            overflow-x: auto;
            padding-bottom: 4px;
            -webkit-overflow-scrolling: touch;
        }
        .step-tab {
            flex: 1 0 auto;
            display: flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            padding: 8px 12px;
            font-size: .78rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color .2s, color .2s, background .2s;
        }
        .step-tab .step-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .step-tab.active {
            border-color: var(--primary);
            color: var(--primary);
            background: #f0f7ff;
        }
        .step-tab.active .step-dot { background: var(--primary); color: #fff; }
        .step-tab.done .step-dot { background: var(--accent); color: #fff; }
        .step-tab.has-error { border-color: #ef4444; color: #b91c1c; }
        .step-tab.has-error .step-dot { background: #ef4444; color: #fff; }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeStep .25s ease; }
        @keyframes fadeStep { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        /* ── Inline field errors ── */
        .field-error {
            color: #b91c1c;
            font-size: .76rem;
            font-weight: 600;
            margin-top: 4px;
            display: flex;
            align-items: flex-start;
            gap: 5px;
        }
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #ef4444 !important;
            background: #fef2f2;
        }
        .opt-row.is-invalid,
        .consent-item.is-invalid { outline: 1.5px solid #ef4444; outline-offset: 3px; border-radius: 8px; }
        .section-body.is-invalid { outline: 1.5px solid #ef4444; outline-offset: -1px; border-radius: 8px; }

        /* ── Wizard nav buttons ── */
        .wizard-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 760px;
            margin: 4px auto 0;
        }
        .wz-count { flex: 1; text-align: center; font-size: .78rem; color: #94a3b8; font-weight: 600; }
        .btn-wz {
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, opacity .2s;
        }
        .btn-wz-back { background: #eef2f7; color: #475569; }
        .btn-wz-back:hover { background: #e2e8f0; }
        .btn-wz-next { background: var(--primary); color: #fff; }
        .btn-wz-next:hover { background: var(--primary-dk); }
        .btn-wz-submit { background: #059669; color: #fff; }
        .btn-wz-submit:hover { background: #047857; }

        @media (max-width: 575px) {
            .step-tab .step-txt { display: none; }
            .step-tab { flex: 1 1 auto; justify-content: center; padding: 9px; }
            .wizard-nav { flex-wrap: wrap; }
            .btn-wz { flex: 1 1 auto; }
            .wz-count { order: -1; flex-basis: 100%; margin-bottom: 4px; }
        }

        /* ── Plan summary bar + plan card + payment ── */
        .plan-summary-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            max-width: 760px;
            margin: 0 auto 14px;
            padding: 10px 16px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            font-size: .85rem;
            color: #065f46;
        }
        .plan-summary-price { font-weight: 800; white-space: nowrap; }

        .plan-card { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px; }
        .plan-card.filled { border-color: var(--accent); background: #f0fefe; }
        .plan-card .pc-top { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .plan-card .pc-name { font-size: 1rem; font-weight: 800; }
        .plan-card .pc-tier { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .plan-card .pc-price { font-size: 1.35rem; font-weight: 800; }
        .plan-card .pc-price small { font-size: .72rem; font-weight: 600; color: #64748b; }
        .plan-card .pc-age { font-size: .74rem; color: #64748b; margin-top: 2px; }
        .plan-card ul { list-style: none; padding: 0; margin: 12px 0 0; }
        .plan-card li { font-size: .82rem; padding: 3px 0 3px 22px; position: relative; }
        .plan-card li::before { content: "\f058"; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; left: 0; color: var(--accent); }
        .plan-card .pc-lock { margin-top: 12px; font-size: .74rem; color: #94a3b8; }

        .pcf-spinner {
            display: inline-block; width: 15px; height: 15px; vertical-align: -2px;
            border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%;
            animation: pcfSpin .7s linear infinite;
        }
        .pcf-spinner-lg { width: 34px; height: 34px; border-width: 3px; border-color: rgba(12,116,197,.25); border-top-color: var(--primary); }
        @keyframes pcfSpin { to { transform: rotate(360deg); } }

        #payOverlay {
            position: fixed; inset: 0; z-index: 3000;
            background: rgba(15,23,42,.55); backdrop-filter: blur(2px);
            display: flex; align-items: center; justify-content: center;
        }
        .pay-overlay-box {
            background: #fff; border-radius: 14px; padding: 28px 34px; text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.25); max-width: 320px;
        }
        .pay-overlay-box p { margin: 14px 0 0; font-size: .9rem; font-weight: 600; color: var(--ink); }

        .btn-wz-submit[disabled] { opacity: .8; cursor: progress; }
        #btnSubmit .btn-spin { display: inline-flex; align-items: center; gap: 8px; }
        .pcf-resume-note {
            background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px;
            padding: 12px 15px; font-size: .82rem; color: #78350f; margin-top: 14px;
        }
        .pcf-resume-note code { background: #fff; border: 1px solid #fcd34d; border-radius: 5px; padding: 1px 6px; word-break: break-all; }
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

        <?php if ($resume_row): ?>
            <?php
            $rr_amount = number_format((float) $resume_row['plan_price']);
            ?>
            <div class="success-card" style="max-width:460px;">
                <div class="success-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-clock"></i></div>
                <h4 style="font-weight:700;color:var(--primary);">Complete your payment</h4>
                <p style="color:#64748b;font-size:.86rem;">Your consent form for
                    <strong><?= htmlspecialchars($resume_row['student_name']) ?></strong> is saved. Finish the payment to submit it.</p>
                <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin:14px 0;font-size:.85rem;line-height:1.9;text-align:left;">
                    <strong>Reference:</strong> <?= strtoupper(substr($resume_row['token'], 0, 8)) ?><br>
                    <strong>Plan:</strong> <?= htmlspecialchars($resume_row['plan_name']) ?><br>
                    <strong>Amount:</strong> &#8377;<?= $rr_amount ?>
                </div>
                <button type="button" id="resumePayBtn" class="btn-submit" data-token="<?= htmlspecialchars($resume_row['token'], ENT_QUOTES) ?>">
                    <span class="btn-label"><i class="fas fa-lock me-1"></i>Pay &#8377;<?= $rr_amount ?> now</span>
                    <span class="btn-spin" hidden><span class="pcf-spinner"></span> Please wait…</span>
                </button>
                <p style="font-size:.75rem;color:#94a3b8;margin-top:12px;">Secured by Razorpay. You will get an email once payment is confirmed.</p>
            </div>
            <div id="payOverlay" hidden>
                <div class="pay-overlay-box">
                    <span class="pcf-spinner pcf-spinner-lg"></span>
                    <p id="payOverlayMsg">Confirming your payment…</p>
                </div>
            </div>

        <?php elseif ($success): ?>
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
                <div class="alert alert-danger mb-3" style="border-radius:10px;" id="errBox">
                    <div class="d-flex align-items-center gap-2 fw-semibold">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                    <?php if ($errors): ?>
                        <ul class="mb-0 mt-2" style="padding-left:1.1rem;font-size:.83rem;">
                            <?php foreach ($errors as $k => $msg): ?>
                                <li><a href="#" class="err-jump" data-field="<?= htmlspecialchars($k, ENT_QUOTES) ?>"
                                        style="color:#b91c1c;"><?= htmlspecialchars($msg) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Step navigation -->
            <div class="step-nav" id="stepNav">
                <?php
                $steps = [
                    ['fa-school', 'School &amp; Parent'],
                    ['fa-child', 'Student'],
                    ['fa-heart-pulse', 'Health Screening'],
                    ['fa-notes-medical', 'Medical History'],
                    [$plans_enabled ? 'fa-credit-card' : 'fa-clipboard-check', $plans_enabled ? 'Consent &amp; Pay' : 'Consent'],
                ];
                foreach ($steps as $i => [$ic, $lbl]): ?>
                    <button type="button" class="step-tab<?= $i === 0 ? ' active' : '' ?>" data-step="<?= $i ?>">
                        <span class="step-dot"><?= $i + 1 ?></span>
                        <span class="step-txt"><i class="fas <?= $ic ?> me-1"></i><?= $lbl ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if ($plans_enabled): ?>
            <div class="plan-summary-bar" id="planSummary" hidden>
                <span><i class="fas fa-layer-group me-2"></i>Your plan: <strong id="planSummaryName">—</strong></span>
                <span class="plan-summary-price" id="planSummaryPrice">—</span>
            </div>
            <?php endif; ?>

            <?php
            $plans_json = [];
            foreach ($consent_plans as $p) {
                $plans_json[] = [
                    'id'     => (int) $p['id'],
                    'name'   => $p['name'],
                    'tier'   => $p['tier'],
                    'price'  => (float) $p['price'],
                    'billing' => $p['billing_label'],
                    'age_min' => $p['age_min'] === null ? null : (int) $p['age_min'],
                    'age_max' => $p['age_max'] === null ? null : (int) $p['age_max'],
                    'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', $p['accent_color'] ?? '') ? $p['accent_color'] : '#0C74C5',
                    'features' => $p['feature_list'],
                ];
            }
            $resume_dob = $resume_row['student_dob'] ?? '';
            ?>
            <form method="POST" id="cForm" enctype="multipart/form-data" novalidate
                data-plans='<?= htmlspecialchars(json_encode($plans_json), ENT_QUOTES) ?>'
                data-plans-enabled="<?= $plans_enabled ? '1' : '0' ?>"
                data-resume-token="<?= htmlspecialchars($resume_row['token'] ?? '', ENT_QUOTES) ?>">
                <input type="hidden" name="submit_consent" value="1">
                <input type="hidden" name="pcf_action" id="pcfAction" value="create_order">
                <input type="hidden" name="resume_token" value="<?= htmlspecialchars($resume_row['token'] ?? '', ENT_QUOTES) ?>">

                <div class="tab-pane active" data-step="0"><!-- STEP 1: School & Parent -->

                <!-- 1. School -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">1</div>
                        <h5><i class="fas fa-school me-2" style="color:var(--primary)"></i>School Information</h5>
                    </div>
                    <div class="section-body">
                        <?php if (!empty($schools)): ?>
                            <div class="mb-3">
                                <label class="form-label">Select School <span class="req">*</span></label>
                                <select name="school_id" class="form-select<?= pcf_inv('school') ?>" id="schoolSel">
                                    <option value="">— Select your child's school —</option>
                                    <?php foreach ($schools as $sc): ?>
                                        <option value="<?= $sc['id'] ?>" <?= ($prefill_school == $sc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sc['school_name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="0">Other (type below)</option>
                                </select>
                                <?= pcf_err('school') ?>
                            </div>
                            <div id="manualWrap" style="display:none">
                                <label class="form-label">School Name</label>
                                <input type="text" name="school_name_manual" class="form-control"
                                    placeholder="Enter school name"
                                    value="<?= htmlspecialchars($_POST['school_name_manual'] ?? '') ?>">
                            </div>
                        <?php else: ?>
                            <label class="form-label">School Name <span class="req">*</span></label>
                            <input type="text" name="school_name_manual" class="form-control<?= pcf_inv('school') ?>" placeholder="Enter school name"
                                value="<?= htmlspecialchars($_POST['school_name_manual'] ?? '') ?>">
                            <?= pcf_err('school') ?>
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
                                    type="text" name="parent_name" class="form-control<?= pcf_inv('parent_name') ?>" required placeholder="e.g. Ramesh Kumar"
                                    value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>"><?= pcf_err('parent_name') ?></div>
                            <div class="col-md-6"><label class="form-label">Relation</label><select name="relation"
                                    class="form-select"><?php foreach (['Father', 'Mother', 'Guardian', 'Other'] as $r): ?>
                                        <option value="<?= $r ?>" <?= (($_POST['relation'] ?? 'Father') === $r) ? 'selected' : '' ?>>
                                            <?= $r ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-6"><label class="form-label">Mobile Number <span
                                        class="req">*</span></label><input type="tel" name="parent_mobile"
                                    class="form-control<?= pcf_inv('parent_mobile') ?>" required placeholder="10-digit number" maxlength="10" inputmode="numeric"
                                    value="<?= htmlspecialchars($_POST['parent_mobile'] ?? '') ?>"><?= pcf_err('parent_mobile') ?></div>
                            <div class="col-md-6"><label class="form-label">Email <span
                                        style="color:#94a3b8;font-weight:400">(optional)</span></label><input type="email"
                                    name="parent_email" class="form-control<?= pcf_inv('parent_email') ?>" placeholder="you@example.com"
                                    value="<?= htmlspecialchars($_POST['parent_email'] ?? '') ?>"><?= pcf_err('parent_email') ?></div>
                            <div class="col-md-6">
                                <label class="form-label">Aadhaar Last 4 Digits</label>
                                <input type="text" name="parent_aadhar" class="form-control<?= pcf_inv('parent_aadhar') ?>" placeholder="XXXX"
                                    maxlength="4" inputmode="numeric" value="<?= pcf_old('parent_aadhar') ?>">
                                <div class="hint"><i class="fas fa-lock me-1"></i>Only last 4 digits stored — full Aadhaar is never saved</div>
                                <?= pcf_err('parent_aadhar') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Aadhaar-linked Mobile Number</label>
                                <input type="tel" name="parent_aadhar_mobile" class="form-control<?= pcf_inv('parent_aadhar_mobile') ?>" placeholder="10-digit number"
                                    maxlength="10" inputmode="numeric" value="<?= pcf_old('parent_aadhar_mobile') ?>">
                                <div class="hint">Mobile number registered with the parent/guardian's Aadhaar.</div>
                                <?= pcf_err('parent_aadhar_mobile') ?>
                            </div>
                        </div>
                    </div>
                </div>

                </div><!-- /STEP 1 -->

                <div class="tab-pane" data-step="1"><!-- STEP 2: Student -->

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
                                    class="form-control<?= pcf_inv('student_name') ?>" required placeholder="e.g. Aryan Kumar"
                                    value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>"><?= pcf_err('student_name') ?></div>
                            <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date"
                                    name="student_dob" class="form-control<?= pcf_inv('student_dob') ?>" max="<?= date('Y-m-d') ?>"
                                    value="<?= htmlspecialchars($_POST['student_dob'] ?? '') ?>"><?= pcf_err('student_dob') ?></div>
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
                                    name="student_pincode" class="form-control<?= pcf_inv('student_pincode') ?>" placeholder="e.g. 226001" maxlength="6"
                                    inputmode="numeric" pattern="[0-9]{6}"
                                    value="<?= pcf_old('student_pincode') ?>"><?= pcf_err('student_pincode') ?></div>
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
                                <input type="text" name="student_abha_number" class="form-control<?= pcf_inv('student_abha') ?>"
                                    placeholder="XX-XXXX-XXXX-XXXX" maxlength="17" inputmode="numeric"
                                    value="<?= pcf_old('student_abha_number') ?>">
                                <div class="hint">14-digit Ayushman Bharat Health Account number.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ABHA Address</label>
                                <input type="text" name="student_abha_address" class="form-control<?= pcf_inv('student_abha') ?>"
                                    placeholder="name@abdm"
                                    value="<?= pcf_old('student_abha_address') ?>">
                                <div class="hint">e.g. <code>aryan.kumar@abdm</code></div>
                            </div>
                            <div class="col-12"><?= pcf_err('student_abha') ?></div>
                        </div>
                        <div class="abha-note">
                            <i class="fas fa-info-circle me-1"></i>
                            Don't have an ABHA for your child yet? Leave this blank — the school health team will help
                            create one during the checkup, with your consent below.
                        </div>
                    </div>
                </div>

                </div><!-- /STEP 2 -->

                <div class="tab-pane" data-step="2"><!-- STEP 3: Health Screening -->

                <!-- 5. General Health -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">5</div>
                        <h5><i class="fas fa-weight-scale me-2" style="color:var(--primary)"></i>General Health</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-3"><label class="form-label">Height (cm)</label>
                                <input type="number" step="0.1" min="0" name="height_cm" id="ht" class="form-control<?= pcf_inv('height_cm') ?>" value="<?= pcf_old('height_cm') ?>"><?= pcf_err('height_cm') ?></div>
                            <div class="col-6 col-md-3"><label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.1" min="0" name="weight_kg" id="wt" class="form-control<?= pcf_inv('weight_kg') ?>" value="<?= pcf_old('weight_kg') ?>"><?= pcf_err('weight_kg') ?></div>
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

                </div><!-- /STEP 3 -->

                <div class="tab-pane" data-step="3"><!-- STEP 4: Medical History -->

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

                </div><!-- /STEP 4 -->

                <div class="tab-pane" data-step="4"><!-- STEP 5: Consent, Plan & Payment -->

                <?php if ($plans_enabled): ?>
                <!-- Health plan (auto-selected from the child's age, locked) -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num"><i class="fas fa-credit-card"></i></div>
                        <h5><i class="fas fa-layer-group me-2" style="color:var(--primary)"></i>Your Health Plan</h5>
                    </div>
                    <div class="section-body">
                        <p style="font-size:.83rem;color:#64748b;margin-bottom:12px">
                            This plan is selected automatically from your child's age and cannot be changed here.
                            The fee is per student, per year.
                        </p>
                        <div id="planCard" class="plan-card">
                            <div class="plan-card-empty text-muted" style="font-size:.85rem;">
                                <i class="fas fa-circle-info me-1"></i>Enter your child's date of birth in step 2 to see your plan.
                            </div>
                        </div>
                        <div class="field-error" id="planErr" style="display:none"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 12. Consent -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="s-num">12</div>
                        <h5><i class="fas fa-clipboard-check me-2" style="color:var(--primary)"></i>I Give Consent For</h5>
                    </div>
                    <div class="section-body">
                        <p style="font-size:.83rem;color:#64748b;margin-bottom:13px">Tick the services you allow for your
                            child. Select at least one — you may tick all or specific ones. <span class="req">*</span></p>
                        <?= pcf_err('consent') ?>
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
                        <label class="consent-item<?= pcf_inv('i_agree') ?>" style="border-color:#f59e0b;background:#fffbeb" id="ci_agree">
                            <input type="checkbox" name="i_agree" value="1" required
                                onchange="toggleCI('_agree',this.checked)" <?= isset($_POST['i_agree']) ? 'checked' : '' ?>>
                            <div style="font-size:.87rem;font-weight:600;color:#92400e">
                                <i class="fas fa-check-circle me-1" style="color:#f59e0b"></i>
                                I have read and understood the above declaration and I give my full consent. <span
                                    class="req">*</span>
                            </div>
                        </label>
                        <?= pcf_err('i_agree') ?>
                        <div class="hint" style="margin-top:8px"><i class="fas fa-lock me-1"></i>Aadhaar last 4 digits
                            only — full number never stored. Submission is secured.</div>
                    </div>
                </div>

                </div><!-- /STEP 5 -->

                <!-- Wizard navigation -->
                <div class="wizard-nav">
                    <button type="button" class="btn-wz btn-wz-back" id="btnBack" style="display:none">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </button>
                    <div class="wz-count" id="wzCount"></div>
                    <button type="button" class="btn-wz btn-wz-next" id="btnNext">
                        Next<i class="fas fa-arrow-right ms-1"></i>
                    </button>
                    <button type="submit" class="btn-wz btn-wz-submit" id="btnSubmit" style="display:none">
                        <span class="btn-label"><i class="fas fa-<?= $plans_enabled ? 'lock' : 'paper-plane' ?> me-1"></i><?= $plans_enabled ? 'Proceed to Secure Payment' : 'Submit Consent Form' ?></span>
                        <span class="btn-spin" hidden><span class="pcf-spinner"></span> Please wait…</span>
                    </button>
                </div>
            </form>

            <!-- payment overlay -->
            <div id="payOverlay" hidden>
                <div class="pay-overlay-box">
                    <span class="pcf-spinner pcf-spinner-lg"></span>
                    <p id="payOverlayMsg">Confirming your payment…</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-footer"><i class="fas fa-shield-alt me-1"></i>Secured by Rejuvenate Digital Health &nbsp;|&nbsp; ABDM /
        ABHA Compliant &nbsp;|&nbsp; NHA India Guidelines</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const form = document.getElementById('cForm');
        if (form) { /* skip all form wiring on the success screen */
        const schoolSel = document.getElementById('schoolSel');
        const manualWrap = document.getElementById('manualWrap');
        if (schoolSel) {
            const syncManual = () => { if (manualWrap) manualWrap.style.display = schoolSel.value === '0' ? 'block' : 'none'; };
            schoolSel.addEventListener('change', syncManual);
            syncManual();
        }
        window.toggleCI = function (key, checked) {
            const el = document.getElementById('ci' + key);
            if (el) el.classList.toggle('checked', checked);
        };
        window.selAll = function (state) {
            document.querySelectorAll('.consent-item input[name^="consent"]').forEach(cb => {
                cb.checked = state;
                toggleCI(cb.closest('.consent-item').id.replace('ci', ''), state);
            });
            if (state) clearError('consent[general_checkup]');
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
        // Keep numeric-only fields clean as the parent types
        document.querySelectorAll('input[inputmode="numeric"], input[name="parent_mobile"]').forEach(el => {
            el.addEventListener('input', () => {
                const max = parseInt(el.getAttribute('maxlength') || '0', 10);
                let v = el.value.replace(/\D/g, '');
                if (max > 0) v = v.slice(0, max);
                el.value = v;
            });
        });
        document.querySelectorAll('.consent-item input:checked').forEach(cb => toggleCI(cb.closest('.consent-item').id.replace('ci', ''), true));

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

        /* ═══════════════════════════════════════════
           Tab wizard + client-side validation
        ═══════════════════════════════════════════ */
        const panes = [...document.querySelectorAll('.tab-pane')];
        const tabs = [...document.querySelectorAll('.step-tab')];
        const btnBack = document.getElementById('btnBack');
        const btnNext = document.getElementById('btnNext');
        const btnSubmit = document.getElementById('btnSubmit');
        const wzCount = document.getElementById('wzCount');
        const pgBar = document.getElementById('pgBar');
        const TOTAL = panes.length;
        let step = 0;

        // Field-level error helpers ---------------------------------
        function fieldEl(name) { return form.querySelector('[name="' + name + '"]'); }
        function setError(name, msg) {
            const el = fieldEl(name) || fieldEl(name + '[]');
            if (!el) return;
            let anchor = el;
            if (el.type === 'radio' || el.type === 'checkbox') {
                anchor = el.closest('.opt-row') || el.closest('.consent-item') || el.closest('.section-body') || el.parentElement;
                anchor.classList.add('is-invalid');
            } else {
                el.classList.add('is-invalid');
            }
            const host = anchor.closest('.col-12,.col-md-6,.col-md-5,.col-md-4,.col-md-3,.col-6,.mb-3,.section-body') || anchor.parentElement;
            if (host && !host.querySelector('.field-error.js-err')) {
                const d = document.createElement('div');
                d.className = 'field-error js-err';
                d.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + msg;
                anchor.after(d);
            }
        }
        function clearError(name) {
            const el = fieldEl(name) || fieldEl(name + '[]');
            if (el) {
                el.classList.remove('is-invalid');
                const r = el.closest('.opt-row'); if (r) r.classList.remove('is-invalid');
                const host = el.closest('.col-12,.col-md-6,.col-md-5,.col-md-4,.col-md-3,.col-6,.mb-3,.section-body');
                if (host) host.querySelectorAll('.field-error').forEach(n => n.remove());
            }
        }
        function clearAllErrors(pane) {
            (pane || document).querySelectorAll('.field-error').forEach(n => n.remove());
            (pane || document).querySelectorAll('.is-invalid').forEach(n => n.classList.remove('is-invalid'));
        }

        // Validation rules per step --------------------------------
        const isMobile = v => /^[6-9]\d{9}$/.test(v.replace(/\D/g, ''));

        function validateStep(s) {
            const pane = panes[s];
            clearAllErrors(pane);
            const errs = [];
            const add = (name, msg) => { errs.push(name); setError(name, msg); };
            const val = n => { const e = fieldEl(n); return e ? e.value.trim() : ''; };

            if (s === 0) {
                if (schoolSel) {
                    if (!schoolSel.value) add('school_id', 'Please select your child\'s school.');
                    else if (schoolSel.value === '0' && !val('school_name_manual')) add('school_name_manual', 'Please type the school name.');
                } else if (!val('school_name_manual')) {
                    add('school_name_manual', 'Please enter the school name.');
                }
                if (val('parent_name').length < 2) add('parent_name', 'Please enter the parent / guardian\'s full name.');
                if (!isMobile(val('parent_mobile'))) add('parent_mobile', 'Enter a valid 10-digit mobile number (starting 6-9).');
                const em = val('parent_email');
                if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) add('parent_email', 'This email address does not look valid.');
                const a4 = val('parent_aadhar').replace(/\D/g, '');
                if (a4 && a4.length !== 4) add('parent_aadhar', 'Enter only the last 4 digits of the Aadhaar number.');
                const am = val('parent_aadhar_mobile');
                if (am && !isMobile(am)) add('parent_aadhar_mobile', 'Enter a valid 10-digit number, or leave it blank.');
            }

            if (s === 1) {
                if (val('student_name').length < 2) add('student_name', 'Please enter the student\'s full name.');
                const dob = val('student_dob');
                if (dob) {
                    const d = new Date(dob), today = new Date(); today.setHours(0, 0, 0, 0);
                    const min = new Date(); min.setFullYear(min.getFullYear() - 25);
                    if (isNaN(d)) add('student_dob', 'Enter a valid date of birth.');
                    else if (d > today) add('student_dob', 'Date of birth cannot be in the future.');
                    else if (d < min) add('student_dob', 'Please re-check the date of birth.');
                }
                const pin = val('student_pincode').replace(/\D/g, '');
                if (pin && pin.length !== 6) add('student_pincode', 'PIN code must be 6 digits, or leave it blank.');
                const abha = val('student_abha_number').replace(/\D/g, '');
                if (abha && abha.length !== 14) add('student_abha_number', 'ABHA number must be exactly 14 digits, or leave it blank.');
                let aaddr = val('student_abha_address');
                if (aaddr) {
                    if (aaddr.indexOf('@') === -1) aaddr += '@abdm';
                    if (!/^[a-zA-Z0-9._]{3,}@abdm$/.test(aaddr)) add('student_abha_address', 'ABHA address must look like name@abdm.');
                }
            }

            if (s === 2) {
                const h = parseFloat(val('height_cm'));
                if (val('height_cm') && (isNaN(h) || h < 30 || h > 250)) add('height_cm', 'Enter height in cm (30–250), or leave it blank.');
                const w = parseFloat(val('weight_kg'));
                if (val('weight_kg') && (isNaN(w) || w < 5 || w > 200)) add('weight_kg', 'Enter weight in kg (5–200), or leave it blank.');
            }

            if (s === 4) {
                const anyConsent = form.querySelectorAll('.consent-item input[name^="consent"]:checked').length > 0;
                if (!anyConsent) add('consent[general_checkup]', 'Please tick at least one health service you consent to.');
                if (!fieldEl('i_agree').checked) add('i_agree', 'You must tick the declaration checkbox to submit the form.');
            }

            tabs[s].classList.toggle('has-error', errs.length > 0);
            return errs.length === 0 ? null : errs;
        }

        // Navigation ----------------------------------------------
        function showStep(s, opts) {
            opts = opts || {};
            step = Math.max(0, Math.min(TOTAL - 1, s));
            panes.forEach((p, i) => p.classList.toggle('active', i === step));
            tabs.forEach((t, i) => {
                t.classList.toggle('active', i === step);
                t.classList.toggle('done', i < step);
            });
            btnBack.style.display = step === 0 ? 'none' : '';
            btnNext.style.display = step === TOTAL - 1 ? 'none' : '';
            btnSubmit.style.display = step === TOTAL - 1 ? '' : 'none';
            wzCount.textContent = 'Step ' + (step + 1) + ' of ' + TOTAL;
            if (pgBar) pgBar.style.width = Math.round(((step + 1) / TOTAL) * 100) + '%';
            if (!opts.noScroll) window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        btnNext.addEventListener('click', () => {
            const bad = validateStep(step);
            if (bad) { focusFirst(bad); return; }
            showStep(step + 1);
        });
        btnBack.addEventListener('click', () => showStep(step - 1));

        tabs.forEach((t, i) => t.addEventListener('click', () => {
            if (i > step) {                     // moving forward — validate everything in between
                for (let k = step; k < i; k++) {
                    const bad = validateStep(k);
                    if (bad) { showStep(k, { noScroll: true }); focusFirst(bad); return; }
                }
            }
            showStep(i);
        }));

        function focusFirst(names) {
            const first = fieldEl(names[0]) || fieldEl(names[0] + '[]');
            if (first) {
                const box = first.closest('.field-error') ? first : first;
                (box.closest('.form-section') || box).scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (first.focus) try { first.focus({ preventScroll: true }); } catch (e) { }
            }
        }

        // Clear a field's error as soon as the parent fixes it
        function clearSchoolError() {
            const b = form.querySelector('[name="school_id"], [name="school_name_manual"]');
            const host = b && b.closest('.section-body');
            if (host) { host.querySelectorAll('.field-error').forEach(n => n.remove()); host.querySelectorAll('.is-invalid').forEach(n => n.classList.remove('is-invalid')); }
        }
        form.addEventListener('input', e => {
            if (!e.target.name) return;
            clearError(e.target.name.replace('[]', ''));
            if (e.target.name === 'school_name_manual') clearSchoolError();
        });
        form.addEventListener('change', e => {
            if (!e.target.name) return;
            clearError(e.target.name.replace('[]', ''));
            if (e.target.name === 'school_id') clearSchoolError();
            if (e.target.name.indexOf('consent[') === 0) clearError('consent[general_checkup]');
        });

        /* ═══════════════════════════════════════════
           Health plan (age-picked) + Razorpay payment
        ═══════════════════════════════════════════ */
        const PLANS = (() => { try { return JSON.parse(form.dataset.plans || '[]'); } catch (e) { return []; } })();
        const PLANS_ON = form.dataset.plansEnabled === '1';
        const RESUME_TOKEN = form.dataset.resumeToken || '';
        const planCardEl = document.getElementById('planCard');
        const planErrEl = document.getElementById('planErr');
        const planSummary = document.getElementById('planSummary');
        const planSummaryName = document.getElementById('planSummaryName');
        const planSummaryPrice = document.getElementById('planSummaryPrice');
        const dobEl = fieldEl('student_dob');
        let currentPlan = null;

        const rupee = n => '₹' + Number(n).toLocaleString('en-IN');
        function ageFromDob(v) {
            if (!v) return null;
            const d = new Date(v), t = new Date();
            if (isNaN(d) || d > t) return null;
            let a = t.getFullYear() - d.getFullYear();
            const m = t.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--;
            return a;
        }
        function planForAge(age) {
            if (age === null) return null;
            return PLANS.find(p =>
                (p.age_min == null || age >= p.age_min) && (p.age_max == null || age <= p.age_max)
            ) || null;
        }
        function renderPlan() {
            if (!PLANS_ON) return;
            const age = ageFromDob(dobEl ? dobEl.value : '');
            currentPlan = planForAge(age);
            if (!currentPlan) {
                planCardEl.className = 'plan-card';
                planCardEl.innerHTML = '<div class="plan-card-empty text-muted" style="font-size:.85rem;">'
                    + (age === null
                        ? '<i class="fas fa-circle-info me-1"></i>Enter your child’s date of birth in step 2 to see your plan.'
                        : '<i class="fas fa-triangle-exclamation me-1 text-warning"></i>No preset plan for age ' + age
                          + '. Our team will confirm the right plan — please contact the school.')
                    + '</div>';
                if (planSummary) planSummary.hidden = true;
                updateSubmitLabel();
                return;
            }
            const p = currentPlan, ac = p.accent || '#0C74C5';
            planCardEl.className = 'plan-card filled';
            planCardEl.style.setProperty('--accent', ac);
            planCardEl.innerHTML =
                '<div class="pc-top">'
                + '<div><div class="pc-name">' + esc(p.name) + '</div>'
                + (p.tier ? '<div class="pc-tier" style="color:' + ac + '">' + esc(p.tier) + '</div>' : '')
                + '<div class="pc-age">Auto-selected for age ' + age + '</div></div>'
                + '<div class="pc-price" style="color:' + ac + '">' + rupee(p.price) + ' <small>/ ' + esc(p.billing || 'year') + '</small></div>'
                + '</div>'
                + (p.features && p.features.length ? '<ul>' + p.features.map(f => '<li>' + esc(f) + '</li>').join('') + '</ul>' : '')
                + '<div class="pc-lock"><i class="fas fa-lock me-1"></i>Locked to your child’s age. Payment is required to submit.</div>';
            if (planSummary) {
                planSummary.hidden = false;
                planSummaryName.textContent = p.name;
                planSummaryPrice.textContent = rupee(p.price);
            }
            updateSubmitLabel();
        }
        function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
        function updateSubmitLabel() {
            if (!PLANS_ON) return;
            const lbl = btnSubmit.querySelector('.btn-label');
            if (lbl) lbl.innerHTML = currentPlan
                ? '<i class="fas fa-lock me-1"></i>Proceed to Secure Payment · ' + rupee(currentPlan.price)
                : '<i class="fas fa-lock me-1"></i>Proceed to Secure Payment';
        }
        if (dobEl) dobEl.addEventListener('change', renderPlan);
        renderPlan();

        function busy(on, msg) {
            btnSubmit.disabled = on;
            btnSubmit.querySelector('.btn-label').hidden = on;
            const spin = btnSubmit.querySelector('.btn-spin');
            spin.hidden = !on;
            if (on && msg) spin.lastChild.textContent = ' ' + msg;
        }
        function overlay(on, msg) {
            const o = document.getElementById('payOverlay');
            if (!o) return;
            if (msg) document.getElementById('payOverlayMsg').textContent = msg;
            o.hidden = !on;
        }
        function applyServerErrors(errs) {
            const stepMap = {
                school: 0, parent_name: 0, parent_mobile: 0, parent_email: 0, parent_aadhar: 0, parent_aadhar_mobile: 0,
                student_name: 1, student_dob: 1, student_pincode: 1, student_abha: 1,
                height_cm: 2, weight_kg: 2, consent: 4, i_agree: 4
            };
            let firstStep = null;
            Object.keys(errs).forEach(k => {
                const name = k === 'consent' ? 'consent[general_checkup]' : (k === 'student_abha' ? 'student_abha_number' : (k === 'school' ? (schoolSel ? 'school_id' : 'school_name_manual') : k));
                setError(name, errs[k]);
                const st = stepMap[k];
                if (st != null && firstStep === null) firstStep = st;
            });
            if (firstStep !== null) showStep(firstStep);
        }
        function showResumeNote(token) {
            const url = location.origin + location.pathname + '?resume=' + token;
            let note = document.getElementById('pcfResumeNote');
            if (!note) {
                note = document.createElement('div');
                note.id = 'pcfResumeNote';
                note.className = 'pcf-resume-note';
                document.querySelector('.wizard-nav').after(note);
            }
            note.innerHTML = '<i class="fas fa-clock me-1"></i>Payment not completed. Your form is saved — '
                + 'resume anytime from this link:<br><code>' + esc(url) + '</code> '
                + '<button type="button" class="btn btn-sm btn-outline-warning mt-2" id="pcfResumeCopy"><i class="fas fa-copy me-1"></i>Copy link</button>';
            document.getElementById('pcfResumeCopy').addEventListener('click', () => {
                navigator.clipboard?.writeText(url);
                document.getElementById('pcfResumeCopy').innerHTML = '<i class="fas fa-check me-1"></i>Copied';
            });
        }
        function showSuccess(ref, plan, amount) {
            const wrap = document.querySelector('.consent-wrapper');
            const paid = plan ? ('<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px;font-size:.82rem;color:#166534;margin-top:14px;text-align:left;">'
                + '<strong>' + esc(plan) + '</strong>' + (amount != null ? ' — ' + rupee(amount) + ' paid' : '') + '<br>Consent + payment recorded. The school health team will schedule the checkup.</div>') : '';
            wrap.innerHTML =
                '<div class="success-card">'
                + '<div class="success-icon"><i class="fas fa-check"></i></div>'
                + '<h4 style="font-weight:700;color:var(--primary);">All done!</h4>'
                + '<p style="color:#64748b;font-size:.88rem;">Your consent has been submitted. Save your reference number.</p>'
                + '<div class="token-chip">' + esc(ref) + '</div>'
                + '<p style="font-size:.78rem;color:#94a3b8;">Show this to the school health team if asked. A confirmation email is on its way.</p>'
                + paid
                + '<a href="parent-consent.php" style="display:inline-block;margin-top:18px;color:var(--primary);font-size:.84rem;"><i class="fas fa-plus me-1"></i>Submit another form</a>'
                + '</div>';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openRazorpay(data) {
            const opts = {
                key: data.key_id,
                order_id: data.order_id,
                amount: data.amount,
                currency: data.currency || 'INR',
                name: 'Rejuvenate Digital Health',
                description: (data.plan_name || 'School Health Plan') + ' — ' + (fieldEl('student_name')?.value || 'Student'),
                prefill: data.prefill || {},
                theme: { color: '#0C74C5' },
                handler: function (resp) {
                    overlay(true, 'Confirming your payment…');
                    const fd = new FormData();
                    fd.append('pcf_action', 'verify_payment');
                    fd.append('razorpay_order_id', resp.razorpay_order_id);
                    fd.append('razorpay_payment_id', resp.razorpay_payment_id);
                    fd.append('razorpay_signature', resp.razorpay_signature);
                    fd.append('token', data.token);
                    fetch(location.pathname, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(v => {
                            overlay(false);
                            if (v.success) showSuccess(v.ref, data.plan_name, data.amount / 100);
                            else { alert(v.message || 'Payment could not be confirmed.'); showResumeNote(data.token); busy(false); }
                        })
                        .catch(() => { overlay(false); alert('Network error while confirming payment.'); showResumeNote(data.token); busy(false); });
                },
                modal: {
                    ondismiss: function () { busy(false); showResumeNote(data.token); }
                }
            };
            try {
                const rz = new Razorpay(opts);
                rz.on('payment.failed', function (r) {
                    busy(false);
                    alert('Payment failed: ' + (r.error && r.error.description ? r.error.description : 'please try again.'));
                    showResumeNote(data.token);
                });
                rz.open();
            } catch (e) {
                busy(false);
                alert('Could not open the payment window. Please try again.');
            }
        }

        function submitFlow() {
            // validate every step
            for (let s = 0; s < TOTAL; s++) {
                const bad = validateStep(s);
                if (bad) { showStep(s); focusFirst(bad); return; }
            }
            if (PLANS_ON && !currentPlan) {
                showStep(TOTAL - 1);
                if (planErrEl) { planErrEl.style.display = ''; planErrEl.innerHTML = '<i class="fas fa-circle-exclamation"></i> We could not match a plan to your child’s age. Please check the date of birth in step 2 or contact the school.'; }
                return;
            }
            busy(true, PLANS_ON ? 'Creating secure payment…' : 'Submitting…');
            const fd = new FormData(form);
            fd.set('pcf_action', 'create_order');
            if (RESUME_TOKEN) fd.set('resume_token', RESUME_TOKEN);
            fetch(location.pathname, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        busy(false);
                        if (d.errors) applyServerErrors(d.errors);
                        else alert(d.message || 'Something went wrong. Please try again.');
                        return;
                    }
                    if (d.free) { showSuccess(d.ref, currentPlan ? currentPlan.name : null, 0); return; }
                    openRazorpay(d);
                })
                .catch(() => { busy(false); alert('Network error. Please check your connection and try again.'); });
        }

        form.addEventListener('submit', e => { e.preventDefault(); submitFlow(); });

        // "Jump to field" links inside the server-side error summary
        document.querySelectorAll('.err-jump').forEach(a => a.addEventListener('click', e => {
            e.preventDefault();
            const name = a.dataset.field;
            // map server field keys → the step that contains them
            const stepOf = {
                school: 0, parent_name: 0, parent_mobile: 0, parent_email: 0, parent_aadhar: 0, parent_aadhar_mobile: 0,
                student_name: 1, student_dob: 1, student_pincode: 1, student_abha: 1,
                height_cm: 2, weight_kg: 2,
                consent: 4, i_agree: 4
            };
            showStep(stepOf[name] ?? 0);
            const target = form.querySelector('.is-invalid, .field-error');
            if (target) (target.closest('.form-section') || target).scrollIntoView({ behavior: 'smooth', block: 'center' });
        }));

        // If the server bounced back with errors, open the first step that has one
        <?php if ($errors):
            $stepMap = ['school'=>0,'parent_name'=>0,'parent_mobile'=>0,'parent_email'=>0,'parent_aadhar'=>0,'parent_aadhar_mobile'=>0,'student_name'=>1,'student_dob'=>1,'student_pincode'=>1,'student_abha'=>1,'height_cm'=>2,'weight_kg'=>2,'consent'=>4,'i_agree'=>4];
            $firstBad = 0;
            foreach (array_keys($errors) as $k) { if (isset($stepMap[$k])) { $firstBad = $stepMap[$k]; break; } }
        ?>
        showStep(<?= (int) $firstBad ?>, { noScroll: true });
        <?php else: ?>
        showStep(0, { noScroll: true });
        <?php endif; ?>
        } /* end if (form) */

        /* ── Resume-payment screen (?resume=<token>) ── */
        const resumeBtn = document.getElementById('resumePayBtn');
        if (resumeBtn) {
            const token = resumeBtn.dataset.token;
            const oEl = () => document.getElementById('payOverlay');
            const setOverlay = (on, msg) => { const o = oEl(); if (!o) return; if (msg) document.getElementById('payOverlayMsg').textContent = msg; o.hidden = !on; };
            const setBusy = on => {
                resumeBtn.disabled = on;
                resumeBtn.querySelector('.btn-label').hidden = on;
                resumeBtn.querySelector('.btn-spin').hidden = !on;
            };
            const done = (ref) => {
                document.querySelector('.consent-wrapper').innerHTML =
                    '<div class="success-card"><div class="success-icon"><i class="fas fa-check"></i></div>'
                    + '<h4 style="font-weight:700;color:var(--primary);">Payment complete!</h4>'
                    + '<p style="color:#64748b;font-size:.88rem;">Your consent form has been submitted. A confirmation email is on its way.</p>'
                    + '<div class="token-chip">' + ref + '</div>'
                    + '<a href="parent-consent.php" style="display:inline-block;margin-top:18px;color:var(--primary);font-size:.84rem;"><i class="fas fa-plus me-1"></i>Submit another form</a></div>';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };
            resumeBtn.addEventListener('click', () => {
                setBusy(true);
                const fd = new FormData();
                fd.append('pcf_action', 'resume_pay');
                fd.append('resume_token', token);
                fetch(location.pathname, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { setBusy(false); alert(d.message || 'Could not start the payment.'); return; }
                        if (d.free) { done(d.ref); return; }
                        const rz = new Razorpay({
                            key: d.key_id, order_id: d.order_id, amount: d.amount, currency: d.currency || 'INR',
                            name: 'Rejuvenate Digital Health', description: d.plan_name || 'School Health Plan',
                            prefill: d.prefill || {}, theme: { color: '#0C74C5' },
                            handler: function (resp) {
                                setOverlay(true, 'Confirming your payment…');
                                const v = new FormData();
                                v.append('pcf_action', 'verify_payment');
                                v.append('razorpay_order_id', resp.razorpay_order_id);
                                v.append('razorpay_payment_id', resp.razorpay_payment_id);
                                v.append('razorpay_signature', resp.razorpay_signature);
                                v.append('token', d.token);
                                fetch(location.pathname, { method: 'POST', body: v })
                                    .then(r => r.json())
                                    .then(x => { setOverlay(false); if (x.success) done(x.ref); else { alert(x.message || 'Payment could not be confirmed.'); setBusy(false); } })
                                    .catch(() => { setOverlay(false); alert('Network error while confirming payment.'); setBusy(false); });
                            },
                            modal: { ondismiss: () => setBusy(false) }
                        });
                        rz.on('payment.failed', r => { setBusy(false); alert('Payment failed: ' + (r.error?.description || 'please try again.')); });
                        rz.open();
                    })
                    .catch(() => { setBusy(false); alert('Network error. Please try again.'); });
            });
        }
    </script>
</body>

</html>
