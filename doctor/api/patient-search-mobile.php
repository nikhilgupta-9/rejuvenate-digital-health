<?php
/**
 * Search patient in portal DB by mobile number.
 * GET ?mobile=9876543210
 * Returns { found, patient: {id, name, mobile, email, abha_number, abha_address, abha_verified, already_linked} }
 */
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/lib/Abha.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$mobile = preg_replace('/\D/', '', trim($_GET['mobile'] ?? ''));
if (strlen($mobile) !== 10) {
    echo json_encode(['success'=>false,'error'=>'Enter a valid 10-digit mobile number']); exit;
}

$stmt = $conn->prepare("
    SELECT u.id, u.name, u.mobile, u.email, u.gender, u.dob,
           " . Abha::selectAliases('aa', 'u') . ",
           (SELECT COUNT(*) FROM doctor_patients dp WHERE dp.doctor_id=? AND dp.patient_id=u.id) AS already_linked
    FROM users u
    " . Abha::joinClause('patient', 'u', 'aa') . "
    WHERE u.mobile = ?
    LIMIT 1
");
$stmt->bind_param('is', $doctor_id, $mobile);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success'=>true,'found'=>false]); exit;
}

echo json_encode([
    'success' => true,
    'found'   => true,
    'patient' => [
        'id'             => (int)$row['id'],
        'name'           => $row['name'],
        'mobile'         => $row['mobile'],
        'email'          => $row['email'],
        'gender'         => $row['gender'],
        'dob'            => $row['dob'],
        'abha_number'    => $row['abha_number'],
        'abha_address'   => $row['abha_address'],
        'abha_verified'  => (bool)$row['abha_verified'],
        'already_linked' => (bool)$row['already_linked'],
    ],
]);
