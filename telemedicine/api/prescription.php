<?php
/**
 * In-call prescription — read + write, tied to the same signed join ticket
 * as the video room (no session/JWT re-check, exactly like poll.php/send.php).
 *
 *   GET  ?ticket=…                 → { success, rx, canWrite }
 *   POST ticket, status=draft|final, chief_complaints, diagnosis, advice,
 *        follow_up_date, bp_sys, bp_dia, pulse, temp, spo2, weight, height,
 *        med_name[], med_dose[], med_freq[], med_dur[], med_instr[]
 *        (doctor role only)         → { success, rx }
 *
 * Writes to the SAME `prescriptions` table the doctor panel and
 * admin → Prescriptions use — one row per appointment. On every write a
 * `prescription` signal is queued so the patient's poll loop shows it live.
 */
require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__, 2) . '/lib/JWT.php';
require_once dirname(__DIR__, 2) . '/lib/Settlement.php';

header('Content-Type: application/json');

$ticket = $_GET['ticket'] ?? $_POST['ticket'] ?? '';
try {
    $claims = JWT::verify($ticket, TELEMED_SECRET);
    if (($claims['purpose'] ?? '') !== 'telemed_join') {
        throw new RuntimeException('bad purpose');
    }
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please rejoin the call.']);
    exit;
}

$room          = (string) $claims['room'];
$role          = (string) $claims['role'];
$appointmentId = (int) $claims['appointment_id'];
$doctorEntity  = $role === 'doctor' ? (int) $claims['entity_id'] : 0;

/* The appointment + its patient — also the authority for doctor_id / patient_id. */
$as = $conn->prepare("
    SELECT a.id, a.doctor_id, a.user_id,
           d.name AS doctor_name, d.degrees, d.specialization, d.hpr_id, d.phone AS doctor_phone,
           u.name AS patient_name, u.abha_id AS abha_number, u.abha_address,
           TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) AS patient_age, u.gender
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE a.id = ? AND a.meeting_event_id = ? LIMIT 1
");
$as->bind_param('is', $appointmentId, $room);
$as->execute();
$appt = $as->get_result()->fetch_assoc();
if (!$appt) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Consultation not found.']);
    exit;
}

$doctorId  = (int) $appt['doctor_id'];
$patientId = (int) ($appt['user_id'] ?? 0);

/* ── Shared: fetch + shape the current prescription row for this visit ── */
function pcx_load(mysqli $conn, int $appointmentId): ?array
{
    $s = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
    $s->bind_param('i', $appointmentId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return null;
    $row['vitals']      = json_decode($row['vitals'] ?? '[]', true) ?: [];
    $row['medications'] = array_values(array_filter(
        json_decode($row['medications'] ?? '[]', true) ?: [],
        fn($m) => trim($m['name'] ?? '') !== ''
    ));
    return $row;
}

/* ─────────────────────────── GET — load ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success'  => true,
        'canWrite' => $role === 'doctor',
        'rx'       => pcx_load($conn, $appointmentId),
        'doctor'   => [
            'name'           => $appt['doctor_name'],
            'degrees'        => $appt['degrees'],
            'specialization' => $appt['specialization'],
            'hpr_id'         => $appt['hpr_id'],
        ],
        'patient'  => [
            'name'         => $appt['patient_name'],
            'age'          => $appt['patient_age'],
            'gender'       => $appt['gender'],
            'abha_number'  => $appt['abha_number'],
            'abha_address' => $appt['abha_address'],
        ],
    ]);
    exit;
}

/* ─────────────────────────── POST — save (doctor only) ─────────────────────────── */
if ($role !== 'doctor' || $doctorEntity !== $doctorId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the consulting doctor can write the prescription.']);
    exit;
}

$status = in_array($_POST['status'] ?? '', ['draft', 'final'], true) ? $_POST['status'] : 'draft';

$vitals = json_encode([
    'bp_systolic'  => trim($_POST['bp_sys'] ?? ''),
    'bp_diastolic' => trim($_POST['bp_dia'] ?? ''),
    'pulse'        => trim($_POST['pulse']  ?? ''),
    'temperature'  => trim($_POST['temp']   ?? ''),
    'spo2'         => trim($_POST['spo2']   ?? ''),
    'weight_kg'    => trim($_POST['weight'] ?? ''),
    'height_cm'    => trim($_POST['height'] ?? ''),
]);

$names = $_POST['med_name']  ?? [];
$dose  = $_POST['med_dose']  ?? [];
$freq  = $_POST['med_freq']  ?? [];
$dur   = $_POST['med_dur']   ?? [];
$instr = $_POST['med_instr'] ?? [];
$meds  = [];
foreach ($names as $i => $n) {
    if (trim((string) $n) === '') continue;
    $meds[] = [
        'name'         => trim((string) $n),
        'dose'         => trim((string) ($dose[$i]  ?? '')),
        'frequency'    => trim((string) ($freq[$i]  ?? '')),
        'duration'     => trim((string) ($dur[$i]   ?? '')),
        'route'        => '',
        'instructions' => trim((string) ($instr[$i] ?? '')),
    ];
}
$medsJson = json_encode($meds);

$chief    = trim($_POST['chief_complaints'] ?? '');
$diag     = trim($_POST['diagnosis'] ?? '');
$advice   = trim($_POST['advice'] ?? '');
$fuDate   = trim($_POST['follow_up_date'] ?? '') ?: null;
$fuNotes  = trim($_POST['follow_up_notes'] ?? '');
$abhaNum  = trim((string) ($appt['abha_number'] ?? ''));
$hprId    = trim((string) ($appt['hpr_id'] ?? ''));

if ($status === 'final' && $diag === '' && count($meds) === 0) {
    echo json_encode(['success' => false, 'message' => 'Add a diagnosis or at least one medicine before you sign the prescription.']);
    exit;
}

$existing = $conn->query("SELECT id FROM prescriptions WHERE appointment_id = " . $appointmentId . " LIMIT 1")->fetch_assoc();

if ($existing) {
    $u = $conn->prepare("
        UPDATE prescriptions SET
          chief_complaints = ?, vitals = ?, diagnosis = ?, medications = ?,
          advice = ?, follow_up_date = ?, follow_up_notes = ?,
          abha_number = ?, hpr_id = ?, status = ?
        WHERE appointment_id = ? AND doctor_id = ?
    ");
    $u->bind_param(
        'ssssssssssii',
        $chief, $vitals, $diag, $medsJson,
        $advice, $fuDate, $fuNotes, $abhaNum, $hprId, $status,
        $appointmentId, $doctorId
    );
    $ok = $u->execute();
    $err = $u->error;
    $u->close();
} else {
    $careRef = 'CC-' . $appointmentId . '-' . date('Ymd');
    $vdate   = date('Y-m-d');
    $i = $conn->prepare("
        INSERT INTO prescriptions
          (appointment_id, doctor_id, patient_id, care_context_ref, visit_date,
           chief_complaints, vitals, diagnosis, medications, advice,
           follow_up_date, follow_up_notes, abha_number, hpr_id, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $i->bind_param(
        'iiisssssssssss',
        $appointmentId, $doctorId, $patientId, $careRef, $vdate,
        $chief, $vitals, $diag, $medsJson, $advice,
        $fuDate, $fuNotes, $abhaNum, $hprId, $status
    );
    $ok = $i->execute();
    $err = $i->error;
    $i->close();
}

if (!$ok) {
    error_log('[telemed prescription] save failed: ' . $err);
    echo json_encode(['success' => false, 'message' => 'Could not save the prescription. Please try again.']);
    exit;
}

/* When finalised, mark the visit complete + open the doctor's payout settlement —
   same behaviour as doctor/patient-form.php. */
if ($status === 'final') {
    $conn->query("UPDATE appointments SET status = 'completed' WHERE id = " . $appointmentId . " AND doctor_id = " . $doctorId . " AND status <> 'completed'");
    if (function_exists('create_settlement_if_needed')) {
        try { create_settlement_if_needed($conn, $appointmentId); } catch (Throwable $e) { error_log('[telemed prescription] settlement: ' . $e->getMessage()); }
    }
}

$rx = pcx_load($conn, $appointmentId);

/* Queue a signal so the patient's poll loop renders the update live. */
$sigPayload = json_encode([
    'status'      => $rx['status'] ?? $status,
    'updated_at'  => date('c'),
    'doctor_name' => $appt['doctor_name'],
]);
$sig = $conn->prepare("INSERT INTO telemedicine_signals (room, from_role, type, payload) VALUES (?, 'doctor', 'prescription', ?)");
$sig->bind_param('ss', $room, $sigPayload);
$sig->execute();
$sig->close();

echo json_encode(['success' => true, 'rx' => $rx]);
