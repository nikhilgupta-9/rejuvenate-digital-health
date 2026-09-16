<?php
/**
 * POST { member_id } -> generates a signed per-student consent link
 * (lib/ConsentToken.php) and sends it via WhatsApp to the student's
 * registered school_members.parent_mobile. Always returns the link in
 * the response regardless of WhatsApp delivery status — the
 * 'parent_consent_request' Meta template isn't approved/seeded into
 * whatsapp_templates yet (see WHATSAPP_AUTO_BRIEF.md §4d), so schools
 * have a working copy/share tool today; WhatsApp auto-send activates
 * transparently once the template exists, no code change needed.
 */
include_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../lib/ConsentToken.php';
require_once __DIR__ . '/../lib/WhatsAppNotifier.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['school_logged_in']) || $_SESSION['school_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}
$school_id = (int) ($_SESSION['school_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$member_id = (int) ($_POST['member_id'] ?? 0);
if (!$member_id) {
    echo json_encode(['success' => false, 'message' => 'Missing student.']);
    exit;
}

$stmt = $conn->prepare("SELECT m.*, s.school_name FROM school_members m
                         JOIN schools s ON s.id = m.school_id
                         WHERE m.id = ? AND m.school_id = ? AND m.type = 'Student' LIMIT 1");
$stmt->bind_param('ii', $member_id, $school_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    echo json_encode(['success' => false, 'message' => 'Student not found.']);
    exit;
}
if (empty($member['parent_mobile'])) {
    echo json_encode(['success' => false, 'message' => 'Add a parent/guardian mobile number for this student first.']);
    exit;
}

$token = consent_generate_token((int) $member['id'], (int) $member['school_id']);
$link  = rtrim(BASE_URL, '/') . '/school/parent-consent.php?ctoken=' . $token;

$waResult = (new WhatsAppNotifier($conn))->sendEvent(
    'parent_consent_request',
    $member['parent_mobile'],
    [
        'student_name' => $member['name'],
        'school_name'  => $member['school_name'],
        'consent_link' => $link,
    ],
    'school_member',
    (int) $member['id']
);

echo json_encode([
    'success' => true,
    'link'    => $link,
    'wa_sent' => !empty($waResult['ok']),
]);
