<?php
/**
 * Search patient by email or mobile.
 * Returns portal match or prompts for ABHA fallback.
 */
include_once(__DIR__ . "/../../config/connect.php");
require_once(__DIR__ . "/../auth/guard.php");

header('Content-Type: application/json');

$jwt = doctor_jwt_guard(true); // true = return null instead of redirect
if (!$jwt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$doctor_id = (int)$jwt['sub'];
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 3) {
    echo json_encode(['success' => false, 'error' => 'Enter at least 3 characters']);
    exit;
}

// Search in portal users
$sql = "SELECT id, name, last_name, email, mobile, abha_address, abha_id, abha_linked, gender, blood_group, profile_pic
        FROM users
        WHERE email = ? OR mobile = ? OR abha_address = ?
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $query, $query, $query);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    // Check if already linked to this doctor
    $link_sql = "SELECT id FROM doctor_patients WHERE doctor_id = ? AND patient_id = ?";
    $link_stmt = $conn->prepare($link_sql);
    $link_stmt->bind_param('ii', $doctor_id, $row['id']);
    $link_stmt->execute();
    $already_linked = $link_stmt->get_result()->num_rows > 0;

    $patients[] = [
        'id'           => $row['id'],
        'name'         => trim($row['name'] . ' ' . $row['last_name']),
        'email'        => $row['email'],
        'mobile'       => $row['mobile'],
        'abha_address' => $row['abha_address'],
        'abha_id'  => $row['abha_id'],
        'abha_linked'  => (bool)$row['abha_linked'],
        'gender'       => $row['gender'],
        'blood_group'  => $row['blood_group'],
        'profile_pic'  => $row['profile_pic'],
        'already_linked' => $already_linked,
    ];
}

echo json_encode([
    'success'  => true,
    'found'    => count($patients) > 0,
    'patients' => $patients,
]);
