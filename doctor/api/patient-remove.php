<?php
include_once(__DIR__ . "/../../config/connect.php");
require_once(__DIR__ . "/../auth/guard.php");

header('Content-Type: application/json');

$jwt = doctor_jwt_guard(true);
if (!$jwt) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$doctor_id  = (int)$jwt['sub'];
$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$patient_id = (int)($body['patient_id'] ?? 0);

if (!$patient_id) { echo json_encode(['success'=>false,'error'=>'Invalid patient']); exit; }

$stmt = $conn->prepare("DELETE FROM doctor_patients WHERE doctor_id = ? AND patient_id = ?");
$stmt->bind_param('ii', $doctor_id, $patient_id);
$stmt->execute();

echo json_encode(['success' => true]);
