<?php
/**
 * Branded prescription / OPD slip (PDF, built with FPDF).
 *
 *   opd-slip.php?appointment_id=<id>
 *
 * Rendered from the SAVED prescription (doctor/patient-form.php). Viewable by:
 *   - the treating doctor           (rdh_doctor_token JWT)
 *   - any admin                     (rdh_admin_token JWT)
 *   - the patient who owns the appt (session $_SESSION['logged_in'])
 * Patient / admin only see it once the doctor has set status = 'final'.
 */

include_once(__DIR__ . '/../config/connect.php');   // vendor/autoload (FPDF), session, $conn, BASE_URL, JWT_SECRET
include_once(__DIR__ . '/../util/function.php');     // get_header_logo()
require_once(__DIR__ . '/../lib/JWT.php');

// FPDF 1.82 trips PHP 8.2+ E_DEPRECATED (utf8_encode); any stray output aborts
// the PDF stream, so silence non-fatal notices for this endpoint.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

$ROOT = dirname(__DIR__);

/* ── who is asking? ─────────────────────────────────────────── */
$viewer_role = null;
$viewer_id   = 0;
$secret = defined('JWT_SECRET') ? JWT_SECRET : '';

if ($secret && !empty($_COOKIE['rdh_doctor_token'])) {
    try {
        $p = JWT::verify($_COOKIE['rdh_doctor_token'], $secret);
        if (($p['role'] ?? '') === 'doctor') { $viewer_role = 'doctor'; $viewer_id = (int)($p['sub'] ?? $p['doctor_id'] ?? 0); }
    } catch (Throwable $e) {}
}
if (!$viewer_role && $secret && !empty($_COOKIE['rdh_admin_token'])) {
    try {
        $p = JWT::verify($_COOKIE['rdh_admin_token'], $secret);
        if (($p['role'] ?? '') === 'admin') { $viewer_role = 'admin'; $viewer_id = (int)($p['sub'] ?? 0); }
    } catch (Throwable $e) {}
}
if (!$viewer_role && !empty($_SESSION['admin_logged_in'])) {
    $viewer_role = 'admin';
    $viewer_id   = (int)($_SESSION['admin_id'] ?? 0);
}
if (!$viewer_role && !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    $viewer_role = 'patient';
    $viewer_id   = (int)$_SESSION['user_id'];
}
if (!$viewer_role) { header('Location: ' . BASE_URL . 'login.php'); exit(); }

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

function opd_notice(string $msg): void
{
    echo '<!doctype html><meta charset="utf-8"><title>Prescription</title>'
       . '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:80px auto;text-align:center;color:#374151;">'
       . '<div style="font-size:44px;color:#0C74C5;">&#9877;</div><h2 style="color:#0C74C5;">Prescription</h2>'
       . '<p>' . htmlspecialchars($msg) . '</p><a href="javascript:history.back()" style="color:#0C74C5;">&larr; Go back</a></div>';
    exit();
}

if (!$appointment_id) opd_notice('No appointment specified.');

/* ── load appointment + patient + doctor ───────────────────── */
$as = $conn->prepare("
    SELECT a.id, a.user_id, a.doctor_id, a.appointment_date, a.appointment_time,
           u.name AS patient_name, u.last_name AS patient_last, u.mobile AS patient_phone,
           u.gender, u.blood_group, u.abha_id AS abha_number, u.abha_address,
           TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) AS patient_age,
           d.name AS doctor_name, d.degrees, d.specialization, d.phone AS doctor_phone,
           d.email AS doctor_email, d.hpr_id, d.nmc_reg_number
    FROM appointments a
    JOIN users u   ON a.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.id = ? LIMIT 1
");
$as->bind_param('i', $appointment_id);
$as->execute();
$row = $as->get_result()->fetch_assoc();
$as->close();
if (!$row) opd_notice('Appointment not found.');

if ($viewer_role === 'doctor'  && (int)$row['doctor_id'] !== $viewer_id) opd_notice('This appointment is not assigned to you.');
if ($viewer_role === 'patient' && (int)$row['user_id']   !== $viewer_id) opd_notice('This appointment does not belong to your account.');

$rs = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
$rs->bind_param('i', $appointment_id);
$rs->execute();
$rx = $rs->get_result()->fetch_assoc();
$rs->close();
if (!$rx) opd_notice('The doctor has not created a prescription for this visit yet.');
if ($viewer_role !== 'doctor' && ($rx['status'] ?? 'draft') !== 'final') {
    opd_notice('The prescription for this visit is not finalised yet. Please check back later.');
}

$att_s = $conn->prepare("SELECT document_name FROM patient_documents WHERE appointment_id = ? ORDER BY uploaded_at DESC");
$att_s->bind_param('i', $appointment_id);
$att_s->execute();
$attachments = $att_s->get_result()->fetch_all(MYSQLI_ASSOC);
$att_s->close();

$vitals = json_decode($rx['vitals'] ?? '{}', true) ?: [];
$meds   = array_values(array_filter(json_decode($rx['medications'] ?? '[]', true) ?: [], fn($m) => trim($m['name'] ?? '') !== ''));

$logo_rel  = get_header_logo();
$logo_path = $logo_rel ? $ROOT . '/' . ltrim($logo_rel, '/') : '';
if ($logo_path && !is_file($logo_path)) $logo_path = '';

$patient_full = trim($row['patient_name'] . ' ' . ($row['patient_last'] ?? ''));
$care_ref     = $rx['care_context_ref'] ?: ('CC-' . $appointment_id);
$slip_number  = 'RX' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT) . date('ymd');

/* ═══════════════════════════ PDF ═══════════════════════════ */
function enc($s): string { return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)$s) ?: ''; }

class Rx_PDF extends FPDF
{
    public $rdh_logo = '';
    public $rdh_care = '';
    public $rdh_gen  = '';

    function Header()
    {
        if ($this->rdh_logo) {
            $this->Image($this->rdh_logo, 12, 9, 0, 13);
        }
        $this->SetY(10);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetTextColor(12, 116, 197);
        $this->Cell(0, 7, 'REJUVENATE Digital Health', 0, 1, 'R');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 4, 'Digital Prescription  |  ABDM / ABHA aligned', 0, 1, 'R');
        $this->SetDrawColor(12, 116, 197);
        $this->SetLineWidth(0.5);
        $this->Line(12, 26, 198, 26);
        $this->SetTextColor(0, 0, 0);
        $this->SetY(30);
    }

    function Footer()
    {
        $this->SetY(-16);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->SetY(-13);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->MultiCell(0, 3.6, enc('Care Context: ' . $this->rdh_care . '   |   Generated: ' . $this->rdh_gen
            . '   |   Digitally generated - not valid for medico-legal use without doctor signature'), 0, 'C');
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new Rx_PDF('P', 'mm', 'A4');
$pdf->rdh_logo = $logo_path;
$pdf->rdh_care = $care_ref;
$pdf->rdh_gen  = date('d M Y, h:i A');
$pdf->SetAutoPageBreak(true, 20);
$pdf->AliasNbPages();
$pdf->SetTitle(enc('Prescription - ' . $patient_full));
$pdf->SetAuthor(enc('Dr. ' . $row['doctor_name']));
$pdf->AddPage();

$h = function ($t) use ($pdf) {
    $pdf->Ln(1.5);
    $pdf->SetFont('Helvetica', 'B', 9.5);
    $pdf->SetTextColor(12, 116, 197);
    $pdf->Cell(0, 5.5, enc(strtoupper($t)), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', '', 9.5);
};
$para = function ($t) use ($pdf) {
    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->MultiCell(0, 5, enc($t), 0, 'L');
};

/* Doctor + Patient blocks */
$pdf->SetFont('Helvetica', 'B', 10.5);
$pdf->Cell(96, 5.5, enc('Dr. ' . $row['doctor_name']), 0, 0, 'L');
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell(0, 5.5, enc($patient_full), 0, 1, 'L');

$pdf->SetFont('Helvetica', '', 8.5);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(96, 4.6, enc(trim(($row['degrees'] ?? '') . '  ' . ($row['specialization'] ? '(' . $row['specialization'] . ')' : ''))), 0, 0, 'L');
$pdf->Cell(0, 4.6, enc(trim(($row['patient_age'] !== null ? $row['patient_age'] . ' yrs' : '') . '  |  ' . ($row['gender'] ?: '-')
    . ($row['blood_group'] ? '  |  ' . $row['blood_group'] : ''))), 0, 1, 'L');

$pdf->Cell(96, 4.6, enc(($row['hpr_id'] ? 'HPR: ' . $row['hpr_id'] : 'HPR: not registered')
    . ($row['nmc_reg_number'] ? '   NMC: ' . $row['nmc_reg_number'] : '')), 0, 0, 'L');
$pdf->Cell(0, 4.6, enc($row['abha_number'] ? 'ABHA: ' . $row['abha_number'] : 'ABHA: not linked'), 0, 1, 'L');

$pdf->Cell(96, 4.6, enc(trim(($row['doctor_phone'] ?? '') . '  ' . ($row['doctor_email'] ?? ''))), 0, 0, 'L');
$pdf->Cell(0, 4.6, enc(trim(($row['patient_phone'] ?? '') . ($row['abha_address'] ? '  ' . $row['abha_address'] : ''))), 0, 1, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(1);
$pdf->SetFont('Helvetica', '', 8.5);
$pdf->Cell(0, 4.6, enc('Visit Date: ' . date('d M Y', strtotime($rx['visit_date'] ?: $row['appointment_date']))
    . '     Appt #' . $appointment_id . '     Slip: ' . $slip_number), 0, 1, 'L');
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(12, $pdf->GetY() + 1, 198, $pdf->GetY() + 1);
$pdf->Ln(2);

/* Clinical */
if (trim($rx['chief_complaints'] ?? '') !== '') { $h('Chief Complaints'); $para($rx['chief_complaints']); }

$vlabels = ['bp_systolic'=>'BP Sys','bp_diastolic'=>'BP Dia','pulse'=>'Pulse','temperature'=>'Temp F',
    'spo2'=>'SpO2 %','weight_kg'=>'Wt kg','height_cm'=>'Ht cm','rr'=>'RR'];
$vparts = [];
foreach ($vlabels as $k => $lbl) { if (trim((string)($vitals[$k] ?? '')) !== '') $vparts[] = $lbl . ': ' . $vitals[$k]; }
if ($vparts) { $h('Vitals'); $para(implode('    ', $vparts)); }

if (trim($rx['examination'] ?? '') !== '') { $h('Examination'); $para($rx['examination']); }
if (trim($rx['diagnosis'] ?? '') !== '') {
    $h('Diagnosis');
    $para($rx['diagnosis'] . ($rx['icd_codes'] ? "\nICD-10: " . $rx['icd_codes'] : ''));
}

/* Rx table */
if ($meds) {
    $h('Rx  -  Medications');
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetFillColor(238, 245, 255);
    $w = [44, 20, 30, 24, 18, 40];
    foreach (['Drug','Dose','Frequency','Duration','Route','Instructions'] as $i => $c) {
        $pdf->Cell($w[$i], 6, enc($c), 1, 0, 'L', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 8);
    foreach ($meds as $m) {
        $cells = [$m['name'] ?? '', $m['dose'] ?? '', $m['frequency'] ?? '', $m['duration'] ?? '', $m['route'] ?? '', $m['instructions'] ?? ''];
        foreach ($cells as $i => $c) $pdf->Cell($w[$i], 5.5, enc($c), 1, ($i === 5 ? 1 : 0), 'L');
    }
    $pdf->Ln(1);
}

if (trim($rx['lab_tests'] ?? '') !== '')       { $h('Lab Investigations Advised'); $para($rx['lab_tests']); }
if (trim($rx['radiology'] ?? '') !== '')       { $h('Radiology / Imaging Advised'); $para($rx['radiology']); }
if (trim($rx['report_findings'] ?? '') !== '') { $h('Report Findings / Results'); $para($rx['report_findings']); }
if (trim($rx['advice'] ?? '') !== '')          { $h('Advice'); $para($rx['advice']); }

if (!empty($rx['follow_up_date']) || trim($rx['follow_up_notes'] ?? '') !== '') {
    $h('Follow-up');
    $para(trim((!empty($rx['follow_up_date']) ? date('d M Y', strtotime($rx['follow_up_date'])) : '')
        . (!empty($rx['follow_up_notes']) ? '  -  ' . $rx['follow_up_notes'] : '')));
}
if ($attachments) {
    $h('Reports Attached');
    $para(implode("\n", array_map(fn($a) => '- ' . $a['document_name'], $attachments)));
}

/* Signature */
$pdf->Ln(12);
$y = max($pdf->GetY(), 235);
$pdf->SetY($y);
$pdf->SetDrawColor(120, 120, 120);
$pdf->Line(135, $y + 8, 198, $y + 8);
$pdf->SetXY(135, $y + 9);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell(63, 4.5, enc('Dr. ' . $row['doctor_name']), 0, 1, 'C');
$pdf->SetX(135);
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->Cell(63, 4, enc(trim(($row['specialization'] ?? '') . ($row['hpr_id'] ? '  |  HPR ' . $row['hpr_id'] : ''))), 0, 1, 'C');

/* persist to opd_records (best effort) */
try {
    $diag  = $rx['diagnosis'] ?? '';
    $treat = '';
    foreach ($meds as $m) $treat .= trim(($m['name'] ?? '') . ' ' . ($m['dose'] ?? '') . ' ' . ($m['frequency'] ?? '')) . "\n";
    $tests = trim(($rx['lab_tests'] ?? '') . ' ' . ($rx['radiology'] ?? ''));
    $adv   = $rx['advice'] ?? '';
    $pid   = (int)$row['user_id'];
    $did   = (int)$row['doctor_id'];
    $up = $conn->prepare("
        INSERT INTO opd_records (doctor_id, patient_id, appointment_id, slip_number, diagnosis, treatment, advice, tests_recommended, generated_at)
        VALUES (?,?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE diagnosis=VALUES(diagnosis), treatment=VALUES(treatment),
            advice=VALUES(advice), tests_recommended=VALUES(tests_recommended), generated_at=NOW()
    ");
    $up->bind_param('iiisssss', $did, $pid, $appointment_id, $slip_number, $diag, $treat, $adv, $tests);
    $up->execute();
} catch (Throwable $e) {
    error_log('opd-slip persist failed: ' . $e->getMessage());
}

$pdf->Output('I', 'Prescription_' . preg_replace('/[^A-Za-z0-9]+/', '_', $patient_full) . '_' . date('Ymd_His') . '.pdf');
