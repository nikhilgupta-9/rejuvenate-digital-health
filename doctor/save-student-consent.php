<?php
/**
 * Record (or link) a parent consent for a school student, from the doctor panel.
 *
 *  - action=link : attach an existing parent-submitted consent to this member
 *  - default     : capture a fresh consent at point of care (parent present)
 */
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
require_once __DIR__ . '/inc/consent-helper.php';

$payload   = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$member_id = (int) ($_POST['member_id'] ?? 0);
$action    = $_POST['action'] ?? 'record';
$redirect  = BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#consent';

if (!$member_id) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

/* Identity check must still be valid for this member (same gate as student-profile.php) */
if ((int) ($_SESSION['school_verified_members'][$member_id] ?? 0) < time()) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php?err=verify_required');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM school_members WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

/* ── Link an existing parent submission to this member ───────────────── */
if ($action === 'link') {
    $consent_id = (int) ($_POST['consent_id'] ?? 0);
    $upd = $conn->prepare("UPDATE parent_consent_forms
        SET member_id = ?, linked_at = NOW()
        WHERE id = ? AND member_id IS NULL AND school_id = ?");
    $upd->bind_param('iii', $member_id, $consent_id, $m['school_id']);
    $upd->execute();
    $_SESSION['consent_success'] = $upd->affected_rows
        ? 'Parent consent linked to this student.'
        : 'That consent could not be linked (already linked or not found).';
    header('Location: ' . $redirect);
    exit;
}

/* ── Capture a new consent at point of care ──────────────────────────── */
$parent_name   = trim($_POST['parent_name'] ?? '');
$relation      = in_array($_POST['relation'] ?? '', ['Father', 'Mother', 'Guardian', 'Other']) ? $_POST['relation'] : 'Father';
$parent_mobile = preg_replace('/\D/', '', trim($_POST['parent_mobile'] ?? ''));
$parent_email  = trim($_POST['parent_email'] ?? '') ?: null;
$aadhar_last4  = substr(preg_replace('/\D/', '', trim($_POST['parent_aadhar'] ?? '')), -4) ?: null;
$consent_given = isset($_POST['i_agree']) ? 1 : 0;

$consent_keys = array_keys(consent_item_labels());
$items = [];
foreach ($consent_keys as $k) {
    $items[$k] = isset($_POST['consent'][$k]);
}
$consent_json = json_encode($items);

if (!$parent_name || strlen($parent_mobile) < 10) {
    $_SESSION['consent_error'] = 'Parent/guardian name and a valid 10-digit mobile number are required.';
    header('Location: ' . $redirect);
    exit;
}
if (!$consent_given) {
    $_SESSION['consent_error'] = 'The parent/guardian must agree to the declaration before you can proceed.';
    header('Location: ' . $redirect);
    exit;
}

$token = bin2hex(random_bytes(16));
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$decl  = "I, {$parent_name} ({$relation}), give consent for the health checkup of {$m['name']}, "
       . "recorded at point of care by the attending school doctor.";

$student_dob    = $m['dob'] ?: null;
$student_gender = in_array($m['gender'] ?? '', ['Male', 'Female', 'Other']) ? $m['gender'] : null;
$student_class  = $m['class'] ?: null;
$student_sec    = $m['section'] ?: null;
$student_roll   = $m['roll_number'] ?: null;
$student_abha   = $m['abha_id'] ?: null;
$student_abha_a = $m['abha_address'] ?: null;
$blood_group    = $m['blood_group'] ?: null;

$ins = $conn->prepare("INSERT INTO parent_consent_forms
    (token, school_id, member_id, parent_name, relation, parent_mobile, parent_email, parent_aadhar_last4,
     student_name, student_dob, student_gender, student_class, student_section, student_roll_no,
     student_abha_number, student_abha_address, blood_group,
     consent_items, consent_given, declaration_text, ip_address, user_agent,
     status, source, recorded_by_doctor_id, linked_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'reviewed','doctor',?,NOW())");
$types = 'sii' . str_repeat('s', 15) . 'isssi'; // 3 + 15 + 5 = 23 params
$ins->bind_param(
    $types,
    $token, $m['school_id'], $member_id, $parent_name, $relation, $parent_mobile, $parent_email, $aadhar_last4,
    $m['name'], $student_dob, $student_gender, $student_class, $student_sec, $student_roll,
    $student_abha, $student_abha_a, $blood_group,
    $consent_json, $consent_given, $decl, $ip, $ua,
    $doctor_id
);

if ($ins->execute()) {
    $_SESSION['consent_success'] = 'Parent consent recorded. You can now proceed with the checkup.';
} else {
    $_SESSION['consent_error'] = 'Failed to save consent: ' . $conn->error;
}
header('Location: ' . $redirect);
exit;
