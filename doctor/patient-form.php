<?php
/**
 * Digital Prescription / OPD Note
 * Supports: Patient (appointment-based) AND Student (school_member-based)
 * ABDM-aligned: care_context_ref, ABHA number, HPR ID.
 */

function freqSelect($selected, $name) {
    $opts = ['','Once daily','Twice daily','Thrice daily','Four times/day','Every 6 hours','Every 8 hours','Every 12 hours','As needed (SOS)','Stat (immediately)','Weekly','At bedtime'];
    $html = '<select class="form-select" name="' . htmlspecialchars($name) . '">';
    foreach ($opts as $o) {
        $html .= '<option value="' . $o . '"' . ($o === $selected ? ' selected' : '') . '>' . ($o ?: '— Select —') . '</option>';
    }
    return $html . '</select>';
}
function routeSelect($selected, $name) {
    $opts = ['','Oral','IV','IM','SC','Topical','Inhaled','Sublingual','Rectal','Nasal'];
    $html = '<select class="form-select" name="' . htmlspecialchars($name) . '">';
    foreach ($opts as $o) {
        $html .= '<option value="' . $o . '"' . ($o === $selected ? ' selected' : '') . '>' . ($o ?: '— Select —') . '</option>';
    }
    return $html . '</select>';
}

include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];

/* ── Auto-create prescriptions table ── */
$conn->query("
    CREATE TABLE IF NOT EXISTS `prescriptions` (
      `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `appointment_id`   INT UNSIGNED NOT NULL,
      `doctor_id`        INT UNSIGNED NOT NULL,
      `patient_id`       INT UNSIGNED NOT NULL,
      `care_context_ref` VARCHAR(120) NOT NULL,
      `visit_date`       DATE NOT NULL,
      `chief_complaints` TEXT,
      `vitals`           JSON,
      `examination`      TEXT,
      `diagnosis`        TEXT,
      `icd_codes`        VARCHAR(500) DEFAULT NULL,
      `medications`      JSON,
      `lab_tests`        TEXT,
      `radiology`        TEXT,
      `advice`           TEXT,
      `follow_up_date`   DATE DEFAULT NULL,
      `follow_up_notes`  TEXT,
      `abha_number`      VARCHAR(20) DEFAULT NULL,
      `hpr_id`           VARCHAR(50) DEFAULT NULL,
      `status`           ENUM('draft','final') NOT NULL DEFAULT 'draft',
      `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`       DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_appointment` (`appointment_id`),
      KEY `idx_doctor`  (`doctor_id`),
      KEY `idx_patient` (`patient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ── Load doctor info ── */
$ds = $conn->prepare("SELECT name, degrees, specialization, phone, email, hpr_id FROM doctors WHERE id = ? LIMIT 1");
$ds->bind_param('i', $doctor_id);
$ds->execute();
$doctor = $ds->get_result()->fetch_assoc();
$ds->close();

/* ── Determine mode: patient | student ── */
$mode = (isset($_GET['mode']) && $_GET['mode'] === 'student') ? 'student' : 'patient';

/* ── PATIENT mode variables ── */
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$appointment    = null;
$patient        = null;
$existing_rx    = null;

/* ── STUDENT mode variables ── */
$member_id  = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$member     = null;
$student_rx = null;   // latest student prescription (for pre-fill)

/* ── Today's appointment list for sidebar ── */
$today = date('Y-m-d');
$ts = $conn->prepare("
    SELECT a.id, u.name, u.last_name, a.appointment_time
    FROM appointments a JOIN users u ON a.user_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = ?
      AND a.status IN ('approved','pending','confirmed')
    ORDER BY a.appointment_time ASC
");
$ts->bind_param('is', $doctor_id, $today);
$ts->execute();
$today_appts = $ts->get_result()->fetch_all(MYSQLI_ASSOC);
$ts->close();

/* ── Today's students list for sidebar ── */
$today_students = [];
$tss = $conn->prepare("
    SELECT m.id, m.name, m.school_id, s.school_name
    FROM school_members m JOIN schools s ON s.id = m.school_id
    ORDER BY m.name ASC LIMIT 20
");
$tss->execute();
$today_students = $tss->get_result()->fetch_all(MYSQLI_ASSOC);
$tss->close();

$save_success = '';
$save_error   = '';

/* ════════════════════════════════════════════
   PATIENT MODE — load appointment data
════════════════════════════════════════════ */
if ($mode === 'patient' && $appointment_id > 0) {
    $as = $conn->prepare("
        SELECT a.*,
               u.id           AS patient_id,
               u.name         AS patient_name,
               u.last_name    AS patient_last_name,
               u.mobile       AS patient_phone,
               u.email        AS patient_email,
               u.dob,
               u.gender,
               u.blood_group,
               u.abha_id      AS abha_number,
               u.abha_address,
               u.abha_linked,
               TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) AS patient_age
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.doctor_id = ?
        LIMIT 1
    ");
    $as->bind_param('ii', $appointment_id, $doctor_id);
    $as->execute();
    $appointment = $as->get_result()->fetch_assoc();
    $as->close();

    if (!$appointment) {
        header("Location: appointments.php");
        exit();
    }
    $patient = $appointment;

    $rx_s = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
    $rx_s->bind_param('i', $appointment_id);
    $rx_s->execute();
    $existing_rx = $rx_s->get_result()->fetch_assoc();
    $rx_s->close();
}

/* ── Report attachments for this visit ── */
$rx_attachments = [];
if ($mode === 'patient' && $appointment_id > 0 && $patient) {
    $att_s = $conn->prepare("
        SELECT id, document_name, document_type, description, file_path, file_type, uploaded_at
        FROM patient_documents
        WHERE patient_id = ? AND appointment_id = ?
        ORDER BY uploaded_at DESC
    ");
    $att_s->bind_param('ii', $patient['patient_id'], $appointment_id);
    $att_s->execute();
    $rx_attachments = $att_s->get_result()->fetch_all(MYSQLI_ASSOC);
    $att_s->close();
}

/* ════════════════════════════════════════════
   STUDENT MODE — load member data
════════════════════════════════════════════ */
if ($mode === 'student' && $member_id > 0) {
    $ms = $conn->prepare("
        SELECT m.*, s.school_name,
               TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) AS student_age
        FROM school_members m
        JOIN schools s ON s.id = m.school_id
        WHERE m.id = ? LIMIT 1
    ");
    $ms->bind_param('i', $member_id);
    $ms->execute();
    $member = $ms->get_result()->fetch_assoc();
    $ms->close();

    if (!$member) {
        header("Location: school-students.php");
        exit();
    }

    /* Load latest prescription for pre-fill */
    $srx = $conn->prepare("SELECT * FROM school_member_prescriptions WHERE member_id = ? ORDER BY created_at DESC LIMIT 1");
    $srx->bind_param('i', $member_id);
    $srx->execute();
    $student_rx = $srx->get_result()->fetch_assoc();
    $srx->close();
}

/* ════════════════════════════════════════════
   HANDLE SAVE — PATIENT
════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    $pid       = (int)($_POST['patient_id']    ?? 0);
    $appt_id   = (int)($_POST['appointment_id'] ?? 0);
    $rx_status = in_array($_POST['rx_status'] ?? '', ['draft','final']) ? $_POST['rx_status'] : 'draft';

    $vitals = json_encode([
        'bp_systolic'  => trim($_POST['bp_sys']   ?? ''),
        'bp_diastolic' => trim($_POST['bp_dia']   ?? ''),
        'pulse'        => trim($_POST['pulse']    ?? ''),
        'temperature'  => trim($_POST['temp']     ?? ''),
        'spo2'         => trim($_POST['spo2']     ?? ''),
        'weight_kg'    => trim($_POST['weight']   ?? ''),
        'height_cm'    => trim($_POST['height']   ?? ''),
        'rr'           => trim($_POST['rr']       ?? ''),
    ]);

    $med_names = $_POST['med_name']  ?? [];
    $med_dose  = $_POST['med_dose']  ?? [];
    $med_freq  = $_POST['med_freq']  ?? [];
    $med_dur   = $_POST['med_dur']   ?? [];
    $med_route = $_POST['med_route'] ?? [];
    $med_instr = $_POST['med_instr'] ?? [];
    $meds = [];
    foreach ($med_names as $i => $mn) {
        if (trim($mn) === '') continue;
        $meds[] = [
            'name'         => trim($mn),
            'dose'         => trim($med_dose[$i]  ?? ''),
            'frequency'    => trim($med_freq[$i]  ?? ''),
            'duration'     => trim($med_dur[$i]   ?? ''),
            'route'        => trim($med_route[$i] ?? ''),
            'instructions' => trim($med_instr[$i] ?? ''),
        ];
    }
    $medications_json = json_encode($meds);

    $care_ref = 'CC-' . $appt_id . '-' . date('Ymd');
    $abha_num = trim($_POST['abha_number'] ?? '');
    $hpr_id   = trim($doctor['hpr_id'] ?? '');
    $chief    = trim($_POST['chief_complaints'] ?? '');
    $exam     = trim($_POST['examination']      ?? '');
    $diag     = trim($_POST['diagnosis']        ?? '');
    $icd      = trim($_POST['icd_codes']        ?? '');
    $lab      = trim($_POST['lab_tests']        ?? '');
    $radio    = trim($_POST['radiology']        ?? '');
    $findings = trim($_POST['report_findings']  ?? '');
    $advice   = trim($_POST['advice']           ?? '');
    $fu_date  = trim($_POST['follow_up_date']   ?? '') ?: null;
    $fu_notes = trim($_POST['follow_up_notes']  ?? '');
    $vdate    = date('Y-m-d');

    if ($existing_rx) {
        $upd = $conn->prepare("
            UPDATE prescriptions SET
              chief_complaints=?, vitals=?, examination=?, diagnosis=?, icd_codes=?,
              medications=?, lab_tests=?, radiology=?, report_findings=?, advice=?,
              follow_up_date=?, follow_up_notes=?, abha_number=?, hpr_id=?, status=?
            WHERE appointment_id=? AND doctor_id=?
        ");
        $upd->bind_param('sssssssssssssssii',
            $chief, $vitals, $exam, $diag, $icd,
            $medications_json, $lab, $radio, $findings, $advice,
            $fu_date, $fu_notes, $abha_num, $hpr_id, $rx_status,
            $appt_id, $doctor_id
        );
        if ($upd->execute()) {
            $save_success = 'Prescription updated.';
            $existing_rx['status'] = $rx_status;
        } else {
            $save_error = 'Update failed: ' . $conn->error;
        }
        $upd->close();
    } else {
        $ins = $conn->prepare("
            INSERT INTO prescriptions
              (appointment_id, doctor_id, patient_id, care_context_ref, visit_date,
               chief_complaints, vitals, examination, diagnosis, icd_codes,
               medications, lab_tests, radiology, report_findings, advice,
               follow_up_date, follow_up_notes, abha_number, hpr_id, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $ins->bind_param('iiisssssssssssssssss',
            $appt_id, $doctor_id, $pid, $care_ref, $vdate,
            $chief, $vitals, $exam, $diag, $icd,
            $medications_json, $lab, $radio, $findings, $advice,
            $fu_date, $fu_notes, $abha_num, $hpr_id, $rx_status
        );
        if ($ins->execute()) {
            $save_success = 'Prescription saved. Care Context: ' . $care_ref;
            $rx_s2 = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
            $rx_s2->bind_param('i', $appt_id);
            $rx_s2->execute();
            $existing_rx = $rx_s2->get_result()->fetch_assoc();
            $rx_s2->close();
        } else {
            $save_error = 'Save failed: ' . $conn->error;
        }
        $ins->close();
    }

    if ($rx_status === 'final') {
        $conn->query("UPDATE appointments SET status='completed' WHERE id={$appt_id} AND doctor_id={$doctor_id}");
    }
}

/* ════════════════════════════════════════════
   HANDLE SAVE — STUDENT
════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_prescription'])) {
    $s_member_id  = (int)($_POST['member_id']         ?? 0);
    $s_school_id  = (int)($_POST['school_id']         ?? 0);
    $s_diagnosis  = trim($_POST['s_diagnosis']        ?? '');
    $s_symptoms   = trim($_POST['s_symptoms']         ?? '');
    $s_rx_text    = trim($_POST['s_prescription_text'] ?? '');
    $s_advice     = trim($_POST['s_advice']           ?? '');
    $s_fu_date    = trim($_POST['s_follow_up_date']   ?? '') ?: null;

    /* Student vitals */
    $s_vitals = json_encode([
        'bp_systolic'  => trim($_POST['s_bp_sys']  ?? ''),
        'bp_diastolic' => trim($_POST['s_bp_dia']  ?? ''),
        'pulse'        => trim($_POST['s_pulse']   ?? ''),
        'temperature'  => trim($_POST['s_temp']    ?? ''),
        'spo2'         => trim($_POST['s_spo2']    ?? ''),
        'weight_kg'    => trim($_POST['s_weight']  ?? ''),
        'height_cm'    => trim($_POST['s_height']  ?? ''),
    ]);

    /* Check if table has vitals column; add if missing */
    $conn->query("ALTER TABLE school_member_prescriptions ADD COLUMN IF NOT EXISTS vitals JSON DEFAULT NULL");

    if ($s_member_id && $s_rx_text) {
        $sins = $conn->prepare("INSERT INTO school_member_prescriptions
            (member_id, school_id, doctor_id, diagnosis, symptoms, prescription_text, advice, follow_up_date, vitals)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $sins->bind_param('iiissssss',
            $s_member_id, $s_school_id, $doctor_id,
            $s_diagnosis, $s_symptoms, $s_rx_text, $s_advice, $s_fu_date, $s_vitals
        );
        if ($sins->execute()) {
            $save_success = 'Student prescription saved successfully.';
            /* reload latest rx */
            $srx2 = $conn->prepare("SELECT * FROM school_member_prescriptions WHERE member_id = ? ORDER BY created_at DESC LIMIT 1");
            $srx2->bind_param('i', $s_member_id);
            $srx2->execute();
            $student_rx = $srx2->get_result()->fetch_assoc();
            $srx2->close();
        } else {
            $save_error = 'Save failed: ' . $conn->error;
        }
        $sins->close();
    } else {
        $save_error = 'Prescription text is required.';
    }
}

/* ── Decode saved JSON for pre-filling (patient) ── */
$rx_vitals = [];
$rx_meds   = [];
if ($existing_rx) {
    $rx_vitals = json_decode($existing_rx['vitals']      ?? '{}', true) ?: [];
    $rx_meds   = json_decode($existing_rx['medications'] ?? '[]', true) ?: [];
}
if (empty($rx_meds)) $rx_meds = [['name'=>'','dose'=>'','frequency'=>'','duration'=>'','route'=>'','instructions'=>'']];

$v  = fn($k) => htmlspecialchars($existing_rx[$k] ?? '');
$vt = fn($k) => htmlspecialchars($rx_vitals[$k]   ?? '');

/* Student vitals pre-fill */
$s_vt_data = [];
if ($student_rx && !empty($student_rx['vitals'])) {
    $s_vt_data = json_decode($student_rx['vitals'], true) ?: [];
}
$svt = fn($k) => htmlspecialchars($s_vt_data[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digital Prescription | REJUVENATE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/css/style.css">
    <style>
        :root {
            --rdh-blue: #2c5aa0;
            --rdh-teal: #02c9b8;
            --rdh-green: #0e7c5b;
        }
        body { background: #f0f4f8; font-size: .9rem; }

        /* ── Mode toggle tabs ── */
        .mode-tabs {
            display: flex;
            gap: 0;
            background: #e2e8f0;
            border-radius: 10px;
            padding: 4px;
        }
        .mode-tab {
            flex: 1;
            text-align: center;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: .85rem;
            cursor: pointer;
            color: #64748b;
            text-decoration: none;
            transition: all .2s;
        }
        .mode-tab.active-patient { background: var(--rdh-blue); color: #fff; }
        .mode-tab.active-student { background: var(--rdh-green); color: #fff; }
        .mode-tab:hover:not(.active-patient):not(.active-student) { background: #cbd5e1; color: #1e293b; }

        /* ── Section card ── */
        .rx-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            margin-bottom: 16px;
            overflow: hidden;
        }
        .rx-card-header {
            background: linear-gradient(135deg, var(--rdh-blue), #4a7bc8);
            color: #fff;
            padding: 11px 16px;
            font-weight: 600;
            font-size: .88rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rx-card-header.student-header {
            background: linear-gradient(135deg, var(--rdh-green), #16a34a);
        }
        .rx-card-body { padding: 16px; }

        /* ── Patient / Student banner ── */
        .patient-banner {
            background: linear-gradient(135deg, #1a2340, #0a4a8a);
            color: #fff;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .student-banner {
            background: linear-gradient(135deg, #064e3b, #065f46);
            color: #fff;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .pb-name  { font-size: 1.05rem; font-weight: 700; }
        .pb-meta  { font-size: .8rem; opacity: .8; }
        .pb-abha  {
            display: inline-block;
            background: rgba(2,201,184,.2);
            border: 1px solid var(--rdh-teal);
            color: var(--rdh-teal);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: .75rem;
            font-weight: 600;
            margin-top: 5px;
        }
        .pb-school {
            display: inline-block;
            background: rgba(16,185,129,.2);
            border: 1px solid #10b981;
            color: #6ee7b7;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: .75rem;
            font-weight: 600;
            margin-top: 5px;
        }

        /* ── Vitals grid ── */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        @media(max-width: 991px) { .vitals-grid { grid-template-columns: repeat(3,1fr); } }
        @media(max-width: 575px) { .vitals-grid { grid-template-columns: repeat(2,1fr); } }
        .vital-item label { font-size: .72rem; color: #888; font-weight: 600; margin-bottom: 3px; display: block; text-transform: uppercase; letter-spacing: .4px; }
        .vital-item input { font-size: .88rem; padding: 6px 10px; }

        /* ── Medication table ── */
        #med-table th { background: #e8f0fb; color: var(--rdh-blue); font-size: .78rem; font-weight: 600; white-space: nowrap; }
        #med-table td { vertical-align: middle; padding: 5px 7px; }
        #med-table input, #med-table select { font-size: .83rem; padding: 5px 7px; }
        .remove-med { background: none; border: none; color: #dc3545; font-size: 1rem; cursor: pointer; padding: 0 4px; }

        /* ── Mobile: medication cards ── */
        @media(max-width: 767px) {
            .med-table-wrap { display: none; }
            .med-cards-wrap { display: block; }
        }
        @media(min-width: 768px) {
            .med-table-wrap { display: block; }
            .med-cards-wrap { display: none; }
        }
        .med-card {
            background: #f8faff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
            position: relative;
        }
        .med-card label { font-size: .72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
        .med-card input, .med-card select { font-size: .85rem; }
        .med-card .remove-med-card { position: absolute; top: 8px; right: 8px; background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer; }

        /* ── Lab tests grid ── */
        .lab-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px 12px;
        }
        @media(max-width: 991px) { .lab-grid { grid-template-columns: repeat(3,1fr); } }
        @media(max-width: 575px) { .lab-grid { grid-template-columns: repeat(2,1fr); } }
        .lab-grid .form-check-label { font-size: .8rem; }

        /* ── Status badges ── */
        .badge-draft  { background:#fff3cd;color:#856404;padding:2px 8px;border-radius:10px;font-size:.73rem;font-weight:600; }
        .badge-final  { background:#d4edda;color:#155724;padding:2px 8px;border-radius:10px;font-size:.73rem;font-weight:600; }

        /* ── Action bar ── */
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 200;
            background: #fff;
            border-bottom: 2px solid #e2e8f0;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-bar .title { font-weight: 700; font-size: .95rem; color: var(--rdh-blue); }
        @media(max-width: 575px) {
            .action-bar .title { display: none; }
            .action-bar { gap: 6px; padding: 8px 10px; }
            .action-bar .btn { font-size: .78rem; padding: 5px 10px; }
        }

        /* ── Sidebar toggle on mobile ── */
        .sidebar-toggle-btn {
            display: none;
        }
        @media(max-width: 1199px) {
            .sidebar-toggle-btn { display: inline-flex; align-items: center; gap: 6px; }
            #rx-sidebar { transition: all .3s; }
            #rx-sidebar.collapsed { display: none; }
        }

        /* ── Selector card ── */
        .select-card {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: all .2s;
        }
        .select-card:hover { border-color: var(--rdh-blue); box-shadow: 0 4px 14px rgba(44,90,160,.12); }
        .select-card .sc-icon {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }

        /* ── Print styles ── */
        @media print {
            .no-print { display: none !important; }
            body { background: white; font-size: 11pt; }
            .rx-card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
            .action-bar { display: none !important; }
            .patient-banner, .student-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- ── Action Bar ── -->
<div class="action-bar no-print">
    <span class="title">
        <i class="fa fa-file-medical me-1"></i>
        <?= $mode === 'student' ? 'Student Prescription' : 'Digital Prescription' ?>
    </span>

    <?php if ($existing_rx && $mode === 'patient'): ?>
        <span class="badge-<?= $existing_rx['status'] ?>"><?= ucfirst($existing_rx['status']) ?></span>
    <?php endif; ?>

    <?php if ($mode === 'patient' && $appointment): ?>
        <button form="rx-form" name="rx_status" value="draft" type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-save me-1"></i> Save Draft
        </button>
        <button form="rx-form" name="rx_status" value="final" type="submit" class="btn btn-success btn-sm">
            <i class="fa fa-check me-1"></i> Finalise
        </button>
    <?php elseif ($mode === 'student' && $member): ?>
        <button form="student-rx-form" type="submit" class="btn btn-success btn-sm">
            <i class="fa fa-save me-1"></i> Save Prescription
        </button>
    <?php endif; ?>

    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fa fa-print me-1"></i> Print
    </button>
    <button type="button" class="btn btn-outline-info btn-sm sidebar-toggle-btn" onclick="toggleSidebar()">
        <i class="fa fa-columns"></i> Info
    </button>
    <a href="appointments.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="container-fluid py-3 px-2 px-md-3 px-xl-4">

    <!-- Alerts -->
    <?php if ($save_success): ?>
        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
            <i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($save_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($save_error): ?>
        <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($save_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── Mode Toggle ── -->
    <div class="mb-3 no-print">
        <div class="mode-tabs" style="max-width:400px;">
            <a href="patient-form.php<?= $appointment_id ? '?appointment_id='.$appointment_id : '' ?>"
               class="mode-tab <?= $mode === 'patient' ? 'active-patient' : '' ?>">
                <i class="fa fa-user me-1"></i> Patient
            </a>
            <a href="patient-form.php?mode=student<?= $member_id ? '&member_id='.$member_id : '' ?>"
               class="mode-tab <?= $mode === 'student' ? 'active-student' : '' ?>">
                <i class="fa fa-graduation-cap me-1"></i> Student
            </a>
        </div>
    </div>

    <!-- ════════════════ PATIENT PANEL ════════════════ -->
    <?php if ($mode === 'patient'): ?>

    <?php if (!$appointment): ?>
        <!-- No appointment selected -->
        <div class="rx-card">
            <div class="rx-card-header"><i class="fa fa-calendar-check"></i> Select Today's Patient</div>
            <div class="rx-card-body">
                <?php if (empty($today_appts)): ?>
                    <p class="text-muted mb-0">No approved appointments for today. <a href="appointments.php">View all appointments</a></p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($today_appts as $ta): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                                <a href="patient-form.php?appointment_id=<?= $ta['id'] ?>" class="select-card d-flex">
                                    <div class="sc-icon" style="background:#e8f0fb;color:var(--rdh-blue);">
                                        <i class="fa fa-user"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($ta['name'] . ' ' . $ta['last_name']) ?></div>
                                        <div class="text-muted small"><?= date('h:i A', strtotime($ta['appointment_time'])) ?></div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

    <!-- Patient Banner -->
    <div class="patient-banner">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="pb-name">
                    <?= htmlspecialchars(trim($patient['patient_name'] . ' ' . ($patient['patient_last_name'] ?? ''))) ?>
                </div>
                <div class="pb-meta mt-1">
                    <?= $patient['patient_age'] ?> yrs &nbsp;|&nbsp;
                    <?= htmlspecialchars($patient['gender'] ?? '—') ?>
                    <?php if (!empty($patient['blood_group'])): ?>&nbsp;|&nbsp; BG: <?= htmlspecialchars($patient['blood_group']) ?><?php endif; ?>
                    &nbsp;|&nbsp; <?= htmlspecialchars($patient['patient_phone'] ?? '') ?>
                </div>
                <?php if (!empty($patient['abha_number'])): ?>
                    <span class="pb-abha"><i class="fa fa-id-card me-1"></i>ABHA: <?= htmlspecialchars($patient['abha_number']) ?><?php if ($patient['abha_linked']): ?> <i class="fa fa-check-circle ms-1"></i><?php endif; ?></span>
                <?php else: ?>
                    <span class="pb-abha" style="border-color:#aaa;color:#aaa;">ABHA not linked</span>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="pb-meta"><i class="fa fa-calendar me-1"></i><?= date('d M Y', strtotime($appointment['appointment_date'])) ?> &nbsp;<?= date('h:i A', strtotime($appointment['appointment_time'])) ?></div>
                <div class="pb-meta mt-1"><i class="fa fa-hashtag me-1"></i>Appt #<?= $appointment_id ?></div>
                <?php if ($existing_rx): ?>
                    <div class="mt-1"><span class="badge-<?= $existing_rx['status'] ?>"><?= ucfirst($existing_rx['status']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PATIENT FORM -->
    <form id="rx-form" method="POST" action="">
        <input type="hidden" name="save_prescription" value="1">
        <input type="hidden" name="appointment_id"    value="<?= $appointment_id ?>">
        <input type="hidden" name="patient_id"        value="<?= $patient['patient_id'] ?>">
        <input type="hidden" name="abha_number"       value="<?= htmlspecialchars($patient['abha_number'] ?? '') ?>">

        <div class="row g-3">
            <div class="col-xl-9">

                <!-- Vitals -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-heartbeat"></i> Vital Signs</div>
                    <div class="rx-card-body">
                        <div class="vitals-grid">
                            <div class="vital-item">
                                <label>BP Systolic (mmHg)</label>
                                <input type="number" class="form-control" name="bp_sys" placeholder="120" value="<?= $vt('bp_systolic') ?>">
                            </div>
                            <div class="vital-item">
                                <label>BP Diastolic (mmHg)</label>
                                <input type="number" class="form-control" name="bp_dia" placeholder="80" value="<?= $vt('bp_diastolic') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Pulse (bpm)</label>
                                <input type="number" class="form-control" name="pulse" placeholder="72" value="<?= $vt('pulse') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Temperature (°F)</label>
                                <input type="text" class="form-control" name="temp" placeholder="98.6" value="<?= $vt('temperature') ?>">
                            </div>
                            <div class="vital-item">
                                <label>SpO₂ (%)</label>
                                <input type="number" class="form-control" name="spo2" placeholder="98" value="<?= $vt('spo2') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.1" class="form-control" name="weight" placeholder="65" value="<?= $vt('weight_kg') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Height (cm)</label>
                                <input type="number" class="form-control" name="height" placeholder="165" value="<?= $vt('height_cm') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Resp. Rate (br/min)</label>
                                <input type="number" class="form-control" name="rr" placeholder="16" value="<?= $vt('rr') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chief Complaints -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-comment-medical"></i> Chief Complaints</div>
                    <div class="rx-card-body">
                        <textarea name="chief_complaints" class="form-control" rows="3"
                            placeholder="e.g. Fever for 3 days, headache, body ache..."><?= $v('chief_complaints') ?></textarea>
                    </div>
                </div>

                <!-- Examination -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-stethoscope"></i> Examination Findings</div>
                    <div class="rx-card-body">
                        <textarea name="examination" class="form-control" rows="3"
                            placeholder="General condition, systemic examination findings..."><?= $v('examination') ?></textarea>
                    </div>
                </div>

                <!-- Diagnosis -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-diagnoses"></i> Diagnosis</div>
                    <div class="rx-card-body">
                        <textarea name="diagnosis" class="form-control" rows="2"
                            placeholder="Primary and secondary diagnoses..."><?= $v('diagnosis') ?></textarea>
                        <div class="mt-2">
                            <label class="form-label text-muted small fw-semibold">ICD-10 Codes <span class="fw-normal">(comma-separated)</span></label>
                            <input type="text" name="icd_codes" class="form-control"
                                placeholder="e.g. J06.9, K29.7" value="<?= $v('icd_codes') ?>">
                        </div>
                    </div>
                </div>

                <!-- Medications -->
                <div class="rx-card">
                    <div class="rx-card-header">
                        <i class="fa fa-pills"></i> Medications
                        <button type="button" onclick="addMedRow()" class="btn btn-sm ms-auto"
                            style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);padding:3px 10px;border-radius:6px;font-size:.8rem;">
                            <i class="fa fa-plus me-1"></i> Add
                        </button>
                    </div>
                    <div class="rx-card-body">
                        <!-- Desktop table -->
                        <div class="table-responsive med-table-wrap">
                            <table class="table mb-0" id="med-table">
                                <thead>
                                    <tr>
                                        <th style="min-width:150px;">Medicine</th>
                                        <th style="min-width:80px;">Dose</th>
                                        <th style="min-width:120px;">Frequency</th>
                                        <th style="min-width:85px;">Duration</th>
                                        <th style="min-width:95px;">Route</th>
                                        <th style="min-width:120px;">Instructions</th>
                                        <th style="width:36px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="med-tbody">
                                    <?php foreach ($rx_meds as $m): ?>
                                    <tr>
                                        <td><input type="text" class="form-control" name="med_name[]"  value="<?= htmlspecialchars($m['name'] ?? '') ?>" placeholder="Drug name"></td>
                                        <td><input type="text" class="form-control" name="med_dose[]"  value="<?= htmlspecialchars($m['dose'] ?? '') ?>" placeholder="500mg"></td>
                                        <td><?= freqSelect(($m['frequency'] ?? ''), 'med_freq[]') ?></td>
                                        <td><input type="text" class="form-control" name="med_dur[]"   value="<?= htmlspecialchars($m['duration'] ?? '') ?>" placeholder="5 days"></td>
                                        <td><?= routeSelect(($m['route'] ?? ''), 'med_route[]') ?></td>
                                        <td><input type="text" class="form-control" name="med_instr[]" value="<?= htmlspecialchars($m['instructions'] ?? '') ?>" placeholder="After food"></td>
                                        <td><button type="button" class="remove-med" onclick="removeRow(this, 'table')"><i class="fa fa-times-circle"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Mobile cards -->
                        <div id="med-cards" class="med-cards-wrap">
                            <?php foreach ($rx_meds as $idx => $m): ?>
                            <div class="med-card" id="mc_<?= $idx ?>">
                                <button type="button" class="remove-med-card" onclick="removeRow(this, 'card')"><i class="fa fa-times-circle"></i></button>
                                <div class="row g-2">
                                    <div class="col-6"><label>Medicine</label><input type="text" class="form-control" name="med_name[]" value="<?= htmlspecialchars($m['name'] ?? '') ?>" placeholder="Drug name"></div>
                                    <div class="col-6"><label>Dose</label><input type="text" class="form-control" name="med_dose[]" value="<?= htmlspecialchars($m['dose'] ?? '') ?>" placeholder="500mg"></div>
                                    <div class="col-6"><label>Frequency</label><?= freqSelect(($m['frequency'] ?? ''), 'med_freq[]') ?></div>
                                    <div class="col-6"><label>Duration</label><input type="text" class="form-control" name="med_dur[]" value="<?= htmlspecialchars($m['duration'] ?? '') ?>" placeholder="5 days"></div>
                                    <div class="col-6"><label>Route</label><?= routeSelect(($m['route'] ?? ''), 'med_route[]') ?></div>
                                    <div class="col-6"><label>Instructions</label><input type="text" class="form-control" name="med_instr[]" value="<?= htmlspecialchars($m['instructions'] ?? '') ?>" placeholder="After food"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Lab Tests -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-flask"></i> Lab Investigations</div>
                    <div class="rx-card-body">
                        <?php
                        $common_labs = ['CBC','RBS','FBS','HbA1c','LFT','KFT','Lipid Profile','Thyroid (TSH)','Urine R/M','Blood Group','HIV','HBsAg','HCV','Widal','MP Antigen','Uric Acid','Serum Electrolytes','BT / CT','PT / INR','Serum Albumin','CRP','ESR','Blood Culture','Urine Culture'];
                        $saved_labs = $existing_rx['lab_tests'] ?? '';
                        ?>
                        <div class="lab-grid mb-3">
                            <?php foreach ($common_labs as $lab): ?>
                                <div class="form-check">
                                    <input class="form-check-input lab-check" type="checkbox"
                                        id="lab_<?= md5($lab) ?>" value="<?= $lab ?>"
                                        <?= str_contains($saved_labs, $lab) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="lab_<?= md5($lab) ?>"><?= $lab ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="lab_tests" id="lab_tests_hidden" value="<?= htmlspecialchars($saved_labs) ?>">
                        <div>
                            <label class="form-label text-muted small fw-semibold">Additional Tests</label>
                            <input type="text" id="extra_lab" class="form-control" placeholder="Type additional tests, comma-separated">
                        </div>
                    </div>
                </div>

                <!-- Radiology -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-x-ray"></i> Radiology / Imaging</div>
                    <div class="rx-card-body">
                        <?php
                        $radio_opts  = ['Chest X-Ray','Abdomen X-Ray','USG Abdomen','USG Pelvis','USG Whole Abdomen','CECT Abdomen','CT Chest','MRI Brain','ECG','2D Echo','TMT'];
                        $saved_radio = $existing_rx['radiology'] ?? '';
                        ?>
                        <div class="lab-grid">
                            <?php foreach ($radio_opts as $ro): ?>
                                <div class="form-check">
                                    <input class="form-check-input radio-check" type="checkbox"
                                        id="rad_<?= md5($ro) ?>" value="<?= $ro ?>"
                                        <?= str_contains($saved_radio, $ro) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rad_<?= md5($ro) ?>"><?= $ro ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="radiology" id="radiology_hidden" value="<?= htmlspecialchars($saved_radio) ?>">
                    </div>
                </div>

                <!-- Reports & Attachments -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-folder-open"></i> Reports &amp; Attachments</div>
                    <div class="rx-card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Report Findings / Results</label>
                            <textarea name="report_findings" class="form-control" rows="3"
                                placeholder="e.g. Hb 11.2 g/dL (low), TLC 8,400, RBS 142 mg/dL; USG abdomen — mild fatty liver..."><?= $v('report_findings') ?></textarea>
                            <div class="text-muted small mt-1"><i class="fa fa-info-circle me-1"></i>Type the values / impressions here. Attach the actual report files below.</div>
                        </div>

                        <label class="form-label fw-semibold d-block">Attached Report Files</label>
                        <div id="rx-attach-list" class="mb-2">
                            <?php if (empty($rx_attachments)): ?>
                                <div class="text-muted small" id="rx-attach-empty">No files attached to this visit yet.</div>
                            <?php endif; ?>
                            <?php foreach ($rx_attachments as $att): ?>
                                <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1" data-doc-id="<?= (int)$att['id'] ?>">
                                    <a href="<?= BASE_URL . ltrim($att['file_path'], '/') ?>" target="_blank" class="text-truncate" style="max-width:75%;">
                                        <i class="fa fa-paperclip me-1"></i><?= htmlspecialchars($att['document_name']) ?>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 rx-attach-del" title="Remove">
                                        <i class="fa fa-times-circle"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($appointment): ?>
                        <div class="row g-2 align-items-end no-print">
                            <div class="col-sm-4">
                                <label class="form-label small text-muted mb-1">Type</label>
                                <select id="rx-att-type" class="form-select form-select-sm">
                                    <option>Lab Report</option>
                                    <option>Radiology / Scan</option>
                                    <option>Discharge Summary</option>
                                    <option>Referral</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label small text-muted mb-1">File (PDF / image, max 10 MB)</label>
                                <input type="file" id="rx-att-file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            </div>
                            <div class="col-sm-3">
                                <button type="button" id="rx-att-upload" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fa fa-upload me-1"></i> Upload
                                </button>
                            </div>
                            <div class="col-12"><div id="rx-att-msg" class="small mt-1"></div></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Advice & Follow-up -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-lightbulb"></i> Advice &amp; Follow-up</div>
                    <div class="rx-card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Advice / Instructions</label>
                            <textarea name="advice" class="form-control" rows="3"
                                placeholder="Diet advice, rest, lifestyle modifications, red flags..."><?= $v('advice') ?></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-semibold">Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="form-control"
                                    value="<?= htmlspecialchars($existing_rx['follow_up_date'] ?? '') ?>"
                                    min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-8 col-12">
                                <label class="form-label fw-semibold">Follow-up Notes</label>
                                <input type="text" name="follow_up_notes" class="form-control"
                                    placeholder="Review CBC, recheck BP, etc."
                                    value="<?= $v('follow_up_notes') ?>">
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /col-xl-9 -->

            <!-- Sidebar -->
            <div class="col-xl-3" id="rx-sidebar">
                <?php include __DIR__ . '/inc/rx-sidebar.php'; ?>
            </div>
        </div>
    </form>
    <?php endif; /* appointment loaded */ ?>

    <!-- ════════════════ STUDENT PANEL ════════════════ -->
    <?php elseif ($mode === 'student'): ?>

    <?php if (!$member): ?>
        <!-- No student selected -->
        <div class="rx-card">
            <div class="rx-card-header student-header"><i class="fa fa-graduation-cap"></i> Select Student</div>
            <div class="rx-card-body">
                <?php if (empty($today_students)): ?>
                    <p class="text-muted mb-0">No students found. <a href="school-students.php">View school students</a></p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($today_students as $stu): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                                <a href="patient-form.php?mode=student&member_id=<?= $stu['id'] ?>" class="select-card d-flex">
                                    <div class="sc-icon" style="background:#d1fae5;color:#065f46;">
                                        <i class="fa fa-user-graduate"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($stu['name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($stu['school_name']) ?></div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <a href="school-students.php" class="btn btn-outline-success btn-sm">
                            <i class="fa fa-search me-1"></i> Browse All Students
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

    <!-- Student Banner -->
    <div class="student-banner">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="pb-name"><?= htmlspecialchars($member['name']) ?></div>
                <div class="pb-meta mt-1">
                    <?php if ($member['student_age']): ?><?= $member['student_age'] ?> yrs &nbsp;|&nbsp;<?php endif; ?>
                    <?= htmlspecialchars($member['gender'] ?? '—') ?>
                    <?php if (!empty($member['class'])): ?>&nbsp;|&nbsp; Class: <?= htmlspecialchars($member['class']) ?><?php endif; ?>
                    <?php if (!empty($member['mobile'])): ?>&nbsp;|&nbsp; <?= htmlspecialchars($member['mobile']) ?><?php endif; ?>
                </div>
                <span class="pb-school"><i class="fa fa-school me-1"></i><?= htmlspecialchars($member['school_name']) ?></span>
            </div>
            <div class="text-end">
                <div class="pb-meta"><i class="fa fa-id-badge me-1"></i>Member #<?= $member_id ?></div>
                <?php if ($student_rx): ?>
                    <div class="pb-meta mt-1"><i class="fa fa-clock me-1"></i>Last Rx: <?= date('d M Y', strtotime($student_rx['created_at'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STUDENT FORM -->
    <form id="student-rx-form" method="POST" action="">
        <input type="hidden" name="save_student_prescription" value="1">
        <input type="hidden" name="member_id"  value="<?= $member_id ?>">
        <input type="hidden" name="school_id"  value="<?= (int)$member['school_id'] ?>">

        <div class="row g-3">
            <div class="col-xl-9">

                <!-- Student Vitals -->
                <div class="rx-card">
                    <div class="rx-card-header student-header"><i class="fa fa-heartbeat"></i> Vital Signs</div>
                    <div class="rx-card-body">
                        <div class="vitals-grid">
                            <div class="vital-item">
                                <label>BP Systolic</label>
                                <input type="number" class="form-control" name="s_bp_sys" placeholder="110" value="<?= $svt('bp_systolic') ?>">
                            </div>
                            <div class="vital-item">
                                <label>BP Diastolic</label>
                                <input type="number" class="form-control" name="s_bp_dia" placeholder="70" value="<?= $svt('bp_diastolic') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Pulse (bpm)</label>
                                <input type="number" class="form-control" name="s_pulse" placeholder="80" value="<?= $svt('pulse') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Temperature (°F)</label>
                                <input type="text" class="form-control" name="s_temp" placeholder="98.6" value="<?= $svt('temperature') ?>">
                            </div>
                            <div class="vital-item">
                                <label>SpO₂ (%)</label>
                                <input type="number" class="form-control" name="s_spo2" placeholder="99" value="<?= $svt('spo2') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.1" class="form-control" name="s_weight" placeholder="40" value="<?= $svt('weight_kg') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Height (cm)</label>
                                <input type="number" class="form-control" name="s_height" placeholder="150" value="<?= $svt('height_cm') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Symptoms -->
                <div class="rx-card">
                    <div class="rx-card-header student-header"><i class="fa fa-comment-medical"></i> Symptoms / Complaints</div>
                    <div class="rx-card-body">
                        <textarea name="s_symptoms" class="form-control" rows="3"
                            placeholder="Describe symptoms..."><?= htmlspecialchars($student_rx['symptoms'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Diagnosis -->
                <div class="rx-card">
                    <div class="rx-card-header student-header"><i class="fa fa-diagnoses"></i> Diagnosis</div>
                    <div class="rx-card-body">
                        <textarea name="s_diagnosis" class="form-control" rows="2"
                            placeholder="Clinical diagnosis..."><?= htmlspecialchars($student_rx['diagnosis'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Prescription Text -->
                <div class="rx-card">
                    <div class="rx-card-header student-header"><i class="fa fa-prescription"></i> Prescription</div>
                    <div class="rx-card-body">
                        <textarea name="s_prescription_text" class="form-control" rows="6"
                            placeholder="Write the full prescription here — medicines, doses, frequency, duration..."><?= htmlspecialchars($student_rx['prescription_text'] ?? '') ?></textarea>
                        <div class="text-muted small mt-1"><i class="fa fa-info-circle me-1"></i>Enter each medicine on a new line.</div>
                    </div>
                </div>

                <!-- Advice & Follow-up -->
                <div class="rx-card">
                    <div class="rx-card-header student-header"><i class="fa fa-lightbulb"></i> Advice &amp; Follow-up</div>
                    <div class="rx-card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Advice</label>
                            <textarea name="s_advice" class="form-control" rows="2"
                                placeholder="Rest, diet, activity restrictions..."><?= htmlspecialchars($student_rx['advice'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="form-label fw-semibold">Follow-up Date</label>
                            <input type="date" name="s_follow_up_date" class="form-control"
                                style="max-width:220px;"
                                value="<?= htmlspecialchars($student_rx['follow_up_date'] ?? '') ?>"
                                min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Past Prescriptions for this student -->
                <?php
                $past_srx = $conn->prepare("SELECT p.*, d.name as doctor_name FROM school_member_prescriptions p LEFT JOIN doctors d ON d.id = p.doctor_id WHERE p.member_id = ? ORDER BY p.created_at DESC LIMIT 5");
                $past_srx->bind_param('i', $member_id);
                $past_srx->execute();
                $past_student_rxs = $past_srx->get_result()->fetch_all(MYSQLI_ASSOC);
                $past_srx->close();
                ?>
                <?php if (!empty($past_student_rxs)): ?>
                <div class="rx-card">
                    <div class="rx-card-header student-header"><i class="fa fa-history"></i> Prescription History</div>
                    <div class="rx-card-body p-0">
                        <div class="accordion" id="pastRxAccordion">
                            <?php foreach ($past_student_rxs as $pidx => $prx): ?>
                            <div class="accordion-item border-0 border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $pidx > 0 ? 'collapsed' : '' ?> py-2 px-3" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#prx<?= $pidx ?>">
                                        <span class="fw-semibold"><?= date('d M Y', strtotime($prx['created_at'])) ?></span>
                                        <span class="ms-2 text-muted small">&mdash; Dr. <?= htmlspecialchars($prx['doctor_name'] ?? 'Unknown') ?></span>
                                    </button>
                                </h2>
                                <div id="prx<?= $pidx ?>" class="accordion-collapse collapse <?= $pidx === 0 ? 'show' : '' ?>">
                                    <div class="accordion-body py-2 px-3" style="font-size:.83rem;">
                                        <?php if ($prx['diagnosis']): ?><div><strong>Dx:</strong> <?= nl2br(htmlspecialchars($prx['diagnosis'])) ?></div><?php endif; ?>
                                        <?php if ($prx['symptoms']): ?><div class="mt-1"><strong>Symptoms:</strong> <?= nl2br(htmlspecialchars($prx['symptoms'])) ?></div><?php endif; ?>
                                        <?php if ($prx['prescription_text']): ?><div class="mt-1"><strong>Rx:</strong> <?= nl2br(htmlspecialchars($prx['prescription_text'])) ?></div><?php endif; ?>
                                        <?php if ($prx['advice']): ?><div class="mt-1 text-muted"><?= nl2br(htmlspecialchars($prx['advice'])) ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /col-xl-9 -->

            <!-- Sidebar -->
            <div class="col-xl-3" id="rx-sidebar">
                <?php include __DIR__ . '/inc/rx-sidebar.php'; ?>
            </div>
        </div>
    </form>

    <?php endif; /* member loaded */ ?>
    <?php endif; /* mode */ ?>

</div><!-- /container -->

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Medication helpers ── */
const freqOpts  = ['','Once daily','Twice daily','Thrice daily','Four times/day','Every 6 hours','Every 8 hours','Every 12 hours','As needed (SOS)','Stat (immediately)','Weekly','At bedtime'];
const routeOpts = ['','Oral','IV','IM','SC','Topical','Inhaled','Sublingual','Rectal','Nasal'];

function makeSelect(name, opts) {
    return '<select class="form-select" name="' + name + '">' +
        opts.map(o => '<option value="' + o + '">' + (o || '— Select —') + '</option>').join('') +
        '</select>';
}

let medCardIdx = <?= count($rx_meds) ?>;

function addMedRow() {
    /* Add to desktop table */
    const tbody = document.getElementById('med-tbody');
    if (tbody) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="form-control" name="med_name[]" placeholder="Drug name"></td>
            <td><input type="text" class="form-control" name="med_dose[]" placeholder="500mg"></td>
            <td>${makeSelect('med_freq[]', freqOpts)}</td>
            <td><input type="text" class="form-control" name="med_dur[]" placeholder="5 days"></td>
            <td>${makeSelect('med_route[]', routeOpts)}</td>
            <td><input type="text" class="form-control" name="med_instr[]" placeholder="After food"></td>
            <td><button type="button" class="remove-med" onclick="removeRow(this,'table')"><i class="fa fa-times-circle"></i></button></td>
        `;
        tbody.appendChild(tr);
    }
    /* Add to mobile cards */
    const cards = document.getElementById('med-cards');
    if (cards) {
        const div = document.createElement('div');
        div.className = 'med-card';
        div.innerHTML = `
            <button type="button" class="remove-med-card" onclick="removeRow(this,'card')"><i class="fa fa-times-circle"></i></button>
            <div class="row g-2">
                <div class="col-6"><label>Medicine</label><input type="text" class="form-control" name="med_name[]" placeholder="Drug name"></div>
                <div class="col-6"><label>Dose</label><input type="text" class="form-control" name="med_dose[]" placeholder="500mg"></div>
                <div class="col-6"><label>Frequency</label>${makeSelect('med_freq[]', freqOpts)}</div>
                <div class="col-6"><label>Duration</label><input type="text" class="form-control" name="med_dur[]" placeholder="5 days"></div>
                <div class="col-6"><label>Route</label>${makeSelect('med_route[]', routeOpts)}</div>
                <div class="col-6"><label>Instructions</label><input type="text" class="form-control" name="med_instr[]" placeholder="After food"></div>
            </div>
        `;
        cards.appendChild(div);
    }
    medCardIdx++;
}

function removeRow(btn, type) {
    if (type === 'table') btn.closest('tr').remove();
    else                  btn.closest('.med-card').remove();
}

/* ── Checkbox → hidden input sync ── */
function syncChecks(cls, hiddenId) {
    const checked = [...document.querySelectorAll('.' + cls + ':checked')].map(c => c.value);
    const extraEl = (cls === 'lab-check') ? document.getElementById('extra_lab') : null;
    let val = checked.join(', ');
    if (extraEl && extraEl.value.trim()) val += (val ? ', ' : '') + extraEl.value.trim();
    const h = document.getElementById(hiddenId);
    if (h) h.value = val;
}

document.querySelectorAll('.lab-check,.radio-check').forEach(cb =>
    cb.addEventListener('change', () => {
        syncChecks('lab-check',   'lab_tests_hidden');
        syncChecks('radio-check', 'radiology_hidden');
    })
);
const extraLabEl = document.getElementById('extra_lab');
if (extraLabEl) extraLabEl.addEventListener('input', () => syncChecks('lab-check', 'lab_tests_hidden'));

['rx-form','student-rx-form'].forEach(id => {
    document.getElementById(id)?.addEventListener('submit', () => {
        syncChecks('lab-check',   'lab_tests_hidden');
        syncChecks('radio-check', 'radiology_hidden');
    });
});

/* ── Sidebar toggle (mobile / tablet) ── */
function toggleSidebar() {
    const s = document.getElementById('rx-sidebar');
    if (s) s.classList.toggle('collapsed');
}

/* ── Report attachments (patient mode) ── */
(function () {
    const upBtn = document.getElementById('rx-att-upload');
    if (!upBtn) return;
    const BASE_URL = '<?= BASE_URL ?>';
    const PATIENT_ID = <?= (int)($patient['patient_id'] ?? 0) ?>;
    const APPT_ID = <?= (int)$appointment_id ?>;
    const listEl = document.getElementById('rx-attach-list');
    const msgEl  = document.getElementById('rx-att-msg');

    function msg(t, ok) { msgEl.textContent = t || ''; msgEl.className = 'small mt-1 ' + (ok ? 'text-success' : 'text-danger'); }

    function addRow(doc) {
        const empty = document.getElementById('rx-attach-empty');
        if (empty) empty.remove();
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1';
        row.dataset.docId = doc.id;
        row.innerHTML = '<a href="' + BASE_URL + doc.file_path.replace(/^\//, '') + '" target="_blank" class="text-truncate" style="max-width:75%;">'
            + '<i class="fa fa-paperclip me-1"></i>' + (doc.document_name || 'Document') + '</a>'
            + '<button type="button" class="btn btn-sm btn-link text-danger p-0 rx-attach-del" title="Remove"><i class="fa fa-times-circle"></i></button>';
        listEl.appendChild(row);
    }

    upBtn.addEventListener('click', function () {
        const fileEl = document.getElementById('rx-att-file');
        if (!fileEl.files.length) { msg('Choose a file first.', false); return; }
        const fd = new FormData();
        fd.append('patient_id', PATIENT_ID);
        fd.append('appointment_id', APPT_ID);
        fd.append('document_type', document.getElementById('rx-att-type').value);
        fd.append('document_file', fileEl.files[0]);
        upBtn.disabled = true; msg('Uploading…', true);
        fetch(BASE_URL + 'doctor/api/patient-document-upload.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                upBtn.disabled = false;
                if (!d.success) { msg(d.error || 'Upload failed', false); return; }
                addRow(d.doc); fileEl.value = ''; msg('Uploaded.', true);
            }).catch(() => { upBtn.disabled = false; msg('Network error', false); });
    });

    listEl.addEventListener('click', function (e) {
        const btn = e.target.closest('.rx-attach-del');
        if (!btn) return;
        const row = btn.closest('[data-doc-id]');
        if (!confirm('Remove this file?')) return;
        fetch(BASE_URL + 'doctor/api/patient-document-delete.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ doc_id: row.dataset.docId })
        }).then(r => r.json()).then(d => {
            if (d.success) row.remove(); else alert(d.error || 'Delete failed');
        });
    });
})();

/* ── Auto-dismiss alerts ── */
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        try { new bootstrap.Alert(a).close(); } catch(e) {}
    });
}, 5000);
</script>
</body>
</html>
