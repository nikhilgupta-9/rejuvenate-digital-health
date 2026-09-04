<?php
/**
 * Look up ONE student by phone / Aadhaar / ABHA / email and send an
 * identity-verification OTP to the student's registered email.
 * No student data is returned here — only a confirmation hint + masked email.
 */
include_once(__DIR__ . "/../../config/connect.php");
require_once(__DIR__ . "/../auth/guard.php");
require_once(__DIR__ . "/../../util/auth-helper.php");

header('Content-Type: application/json');

$jwt = doctor_jwt_guard(true);
if (!$jwt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$type  = trim($input['type'] ?? '');
$value = trim($input['value'] ?? '');

if ($value === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter a value to search.']);
    exit;
}

switch ($type) {
    case 'phone':
        $digits = preg_replace('/\D/', '', $value);
        if (!preg_match('/^[6-9]\d{9}$/', $digits)) {
            echo json_encode(['success' => false, 'error' => 'Enter a valid 10-digit mobile number.']);
            exit;
        }
        $col = 'sm.phone';
        $bindVal = $digits;
        break;

    case 'aadhar':
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 12) {
            echo json_encode(['success' => false, 'error' => 'Enter a valid 12-digit Aadhaar number.']);
            exit;
        }
        $col = "REPLACE(sm.aadhar_number,' ','')";
        $bindVal = $digits;
        break;

    case 'abha':
        if (strlen($value) < 4) {
            echo json_encode(['success' => false, 'error' => 'Enter a valid ABHA number or address.']);
            exit;
        }
        // Resolve the ABHA (number or name@abdm address) via abha_accounts
        // (Abha::find falls back to the legacy columns during migration).
        $hit = Abha::find($conn, $value);
        if ($hit && $hit['entity_type'] === 'school_member') {
            $stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON s.id=sm.school_id
                WHERE sm.id=? AND sm.type='Student' AND sm.status='Active' LIMIT 1");
            $stmt->bind_param('i', $hit['entity_id']);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $rows = [];
        }
        $col = null;
        break;

    case 'email':
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Enter a valid email address.']);
            exit;
        }
        $col = 'sm.email';
        $bindVal = $value;
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid search type.']);
        exit;
}

if ($col !== null) {
    $stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON s.id=sm.school_id
        WHERE sm.type='Student' AND sm.status='Active' AND $col=? LIMIT 2");
    $stmt->bind_param('s', $bindVal);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if (count($rows) === 0) {
    echo json_encode(['success' => false, 'error' => 'No student found matching that information.']);
    exit;
}
if (count($rows) > 1) {
    echo json_encode(['success' => false, 'error' => 'Multiple students matched. Please search using a more specific identifier (e.g. email).']);
    exit;
}

$student = $rows[0];

if (empty($student['email']) || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'This student has no valid email on file to verify identity. Please contact the school admin.']);
    exit;
}

$otp = generateOtp();
storeAndSendOtp($conn, 'school_doctor_lookup', (int)$student['id'], $otp, '', $student['email'], $student['name']);

$_SESSION['school_lookup_member_id'] = (int)$student['id'];
$_SESSION['school_lookup_attempts']  = 0;
$_SESSION['school_lookup_started']   = time();

function mask_email(string $email): string
{
    [$local, $domain] = explode('@', $email, 2) + ['', ''];
    $visible = min(2, strlen($local));
    $masked  = substr($local, 0, $visible) . str_repeat('*', max(1, strlen($local) - $visible));
    return $masked . '@' . $domain;
}

echo json_encode([
    'success'     => true,
    'name'        => $student['name'],
    'school_name' => $student['school_name'],
    'member_uid'  => $student['member_uid'],
    'masked_email' => mask_email($student['email']),
]);
