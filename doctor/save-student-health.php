<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$member_id = (int) ($_POST['member_id'] ?? 0);
$redirect  = BASE_URL . 'doctor/student-profile.php?id=' . $member_id . '#health';

if (!$member_id) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$stmt = $conn->prepare("SELECT school_id FROM school_members WHERE id = ?");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$blood_group         = trim($_POST['blood_group'] ?? '');
$height_cm            = isset($_POST['height_cm']) && $_POST['height_cm'] !== '' ? (float)$_POST['height_cm'] : null;
$weight_kg            = isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;
$vision                = trim($_POST['vision'] ?? '');
$hearing               = trim($_POST['hearing'] ?? '');
$dental                = trim($_POST['dental'] ?? '');
$known_allergies       = trim($_POST['known_allergies'] ?? '');
$chronic_conditions    = trim($_POST['chronic_conditions'] ?? '');
$current_medications   = trim($_POST['current_medications'] ?? '');
$vaccination_status    = trim($_POST['vaccination_status'] ?? '');
$emergency_contact     = trim($_POST['emergency_contact'] ?? '');
$emergency_phone       = trim($_POST['emergency_phone'] ?? '');
$notes                 = trim($_POST['notes'] ?? '');

// Blood group lives on school_members, not member_health_profiles
if ($blood_group !== '') {
    $bg = $conn->prepare("UPDATE school_members SET blood_group=? WHERE id=?");
    $bg->bind_param('si', $blood_group, $member_id);
    $bg->execute();
}

$hp_check = $conn->prepare("SELECT id FROM member_health_profiles WHERE member_id=?");
$hp_check->bind_param('i', $member_id);
$hp_check->execute();
$exists = $hp_check->get_result()->fetch_assoc();

if ($exists) {
    $upd = $conn->prepare("UPDATE member_health_profiles SET
        height_cm=?, weight_kg=?, vision=?, hearing=?, dental=?,
        known_allergies=?, chronic_conditions=?, current_medications=?, vaccination_status=?,
        emergency_contact=?, emergency_phone=?, notes=?,
        last_updated_by=?, last_updated_role='doctor', updated_at=NOW()
        WHERE member_id=?");
    $upd->bind_param(
        'ddssssssssssii',
        $height_cm, $weight_kg, $vision, $hearing, $dental,
        $known_allergies, $chronic_conditions, $current_medications, $vaccination_status,
        $emergency_contact, $emergency_phone, $notes,
        $doctor_id, $member_id
    );
    $ok = $upd->execute();
} else {
    $ins = $conn->prepare("INSERT INTO member_health_profiles
        (member_id, school_id, height_cm, weight_kg, vision, hearing, dental,
         known_allergies, chronic_conditions, current_medications, vaccination_status,
         emergency_contact, emergency_phone, notes, last_updated_by, last_updated_role)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'doctor')");
    $ins->bind_param(
        'iiddssssssssssi',
        $member_id, $member['school_id'], $height_cm, $weight_kg, $vision, $hearing, $dental,
        $known_allergies, $chronic_conditions, $current_medications, $vaccination_status,
        $emergency_contact, $emergency_phone, $notes, $doctor_id
    );
    $ok = $ins->execute();
}

if (!$ok) {
    $_SESSION['health_error'] = 'Failed to save health profile: ' . $conn->error;
    header('Location: ' . $redirect);
    exit;
}

$_SESSION['health_success'] = 'Health profile updated.';
header('Location: ' . $redirect);
exit;
