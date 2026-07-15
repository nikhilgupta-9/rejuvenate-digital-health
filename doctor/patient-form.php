<?php
/**
 * Digital Prescription / OPD Note
 * ABDM-aligned: saves to `prescriptions` table, generates care_context_ref,
 * records vitals, medications (JSON), lab tests, diagnosis, advice, follow-up.
 */

include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];

/* ── Auto-create table if not exists ── */
$conn->query("
    CREATE TABLE IF NOT EXISTS `prescriptions` (
      `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `appointment_id`      INT UNSIGNED NOT NULL,
      `doctor_id`           INT UNSIGNED NOT NULL,
      `patient_id`          INT UNSIGNED NOT NULL,
      `care_context_ref`    VARCHAR(120)  NOT NULL,
      `visit_date`          DATE          NOT NULL,
      `chief_complaints`    TEXT,
      `vitals`              JSON,
      `examination`         TEXT,
      `diagnosis`           TEXT,
      `icd_codes`           VARCHAR(500)  DEFAULT NULL,
      `medications`         JSON,
      `lab_tests`           TEXT,
      `radiology`           TEXT,
      `advice`              TEXT,
      `follow_up_date`      DATE          DEFAULT NULL,
      `follow_up_notes`     TEXT,
      `abha_number`         VARCHAR(20)   DEFAULT NULL,
      `hpr_id`              VARCHAR(50)   DEFAULT NULL,
      `status`              ENUM('draft','final') NOT NULL DEFAULT 'draft',
      `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`          DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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

/* ── Appointment ID ── */
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$appointment = null;
$patient     = null;
$existing_rx = null;

if ($appointment_id > 0) {
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

    /* Check if prescription already saved */
    $rx_s = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
    $rx_s->bind_param('i', $appointment_id);
    $rx_s->execute();
    $existing_rx = $rx_s->get_result()->fetch_assoc();
    $rx_s->close();
}

/* ── Handle SAVE ── */
$save_success = '';
$save_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    $pid        = (int)($_POST['patient_id'] ?? 0);
    $appt_id    = (int)($_POST['appointment_id'] ?? 0);
    $rx_status  = in_array($_POST['rx_status'] ?? '', ['draft','final']) ? $_POST['rx_status'] : 'draft';

    /* Vitals as JSON */
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

    /* Medications as JSON */
    $med_names  = $_POST['med_name']  ?? [];
    $med_dose   = $_POST['med_dose']  ?? [];
    $med_freq   = $_POST['med_freq']  ?? [];
    $med_dur    = $_POST['med_dur']   ?? [];
    $med_route  = $_POST['med_route'] ?? [];
    $med_instr  = $_POST['med_instr'] ?? [];
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

    /* Care context reference */
    $care_ref = 'CC-' . $appt_id . '-' . date('Ymd');

    /* ABHA + HPR */
    $abha_num = trim($_POST['abha_number'] ?? '');
    $hpr_id   = trim($doctor['hpr_id'] ?? '');

    /* Fields */
    $chief    = trim($_POST['chief_complaints'] ?? '');
    $exam     = trim($_POST['examination']      ?? '');
    $diag     = trim($_POST['diagnosis']        ?? '');
    $icd      = trim($_POST['icd_codes']        ?? '');
    $lab      = trim($_POST['lab_tests']        ?? '');
    $radio    = trim($_POST['radiology']        ?? '');
    $advice   = trim($_POST['advice']           ?? '');
    $fu_date  = trim($_POST['follow_up_date']   ?? '') ?: null;
    $fu_notes = trim($_POST['follow_up_notes']  ?? '');
    $vdate    = date('Y-m-d');

    if ($existing_rx) {
        $upd = $conn->prepare("
            UPDATE prescriptions SET
              chief_complaints=?, vitals=?, examination=?, diagnosis=?, icd_codes=?,
              medications=?, lab_tests=?, radiology=?, advice=?,
              follow_up_date=?, follow_up_notes=?, abha_number=?, hpr_id=?, status=?
            WHERE appointment_id=? AND doctor_id=?
        ");
        $upd->bind_param('ssssssssssssssii',
            $chief, $vitals, $exam, $diag, $icd,
            $medications_json, $lab, $radio, $advice,
            $fu_date, $fu_notes, $abha_num, $hpr_id, $rx_status,
            $appt_id, $doctor_id
        );
        if ($upd->execute()) {
            $save_success = 'Prescription updated successfully.';
            $existing_rx = array_merge($existing_rx, ['status' => $rx_status]);
        } else {
            $save_error = 'Update failed: ' . $conn->error;
        }
        $upd->close();
    } else {
        $ins = $conn->prepare("
            INSERT INTO prescriptions
              (appointment_id, doctor_id, patient_id, care_context_ref, visit_date,
               chief_complaints, vitals, examination, diagnosis, icd_codes,
               medications, lab_tests, radiology, advice,
               follow_up_date, follow_up_notes, abha_number, hpr_id, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $ins->bind_param('iiissssssssssssssss',
            $appt_id, $doctor_id, $pid, $care_ref, $vdate,
            $chief, $vitals, $exam, $diag, $icd,
            $medications_json, $lab, $radio, $advice,
            $fu_date, $fu_notes, $abha_num, $hpr_id, $rx_status
        );
        if ($ins->execute()) {
            $save_success = 'Prescription saved. Care Context: ' . $care_ref;
            /* Re-fetch */
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

    /* Mark appointment completed if finalised */
    if ($rx_status === 'final') {
        $conn->query("UPDATE appointments SET status='completed' WHERE id={$appt_id} AND doctor_id={$doctor_id}");
    }
}

/* ── Decode saved JSON for pre-filling ── */
$rx_vitals = [];
$rx_meds   = [];
if ($existing_rx) {
    $rx_vitals = json_decode($existing_rx['vitals']      ?? '{}', true) ?: [];
    $rx_meds   = json_decode($existing_rx['medications'] ?? '[]', true) ?: [];
}
if (empty($rx_meds)) $rx_meds = [['name'=>'','dose'=>'','frequency'=>'','duration'=>'','route'=>'','instructions'=>'']];

$v = fn($k) => htmlspecialchars($existing_rx[$k] ?? '');
$vt = fn($k) => htmlspecialchars($rx_vitals[$k] ?? '');

/* ── Today's appointment list for selector ── */
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
        }
        body { background: #f0f4f8; font-size: .9rem; }

        /* ── Section card ── */
        .rx-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .rx-card-header {
            background: linear-gradient(135deg, var(--rdh-blue), #4a7bc8);
            color: #fff;
            padding: 12px 18px;
            font-weight: 600;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rx-card-body { padding: 18px; }

        /* ── Patient summary banner ── */
        .patient-banner {
            background: linear-gradient(135deg, #1a2340, #0a4a8a);
            color: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }
        .patient-banner .pb-name  { font-size: 1.1rem; font-weight: 700; }
        .patient-banner .pb-meta  { font-size: .8rem; opacity: .8; }
        .patient-banner .pb-abha  {
            background: rgba(2,201,184,.2);
            border: 1px solid var(--rdh-teal);
            color: var(--rdh-teal);
            border-radius: 20px;
            padding: 3px 12px;
            font-size: .78rem;
            font-weight: 600;
        }
        .patient-banner .care-ctx {
            margin-left: auto;
            font-size: .75rem;
            opacity: .75;
            text-align: right;
        }

        /* ── Vitals grid ── */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        @media(max-width:768px){ .vitals-grid { grid-template-columns: repeat(2,1fr); } }
        .vital-item label { font-size: .75rem; color: #888; font-weight: 600; margin-bottom: 3px; display: block; }
        .vital-item input { font-size: .9rem; }

        /* ── Medication table ── */
        #med-table th { background: #e8f0fb; color: var(--rdh-blue); font-size: .8rem; font-weight: 600; }
        #med-table td { vertical-align: middle; padding: 6px 8px; }
        #med-table input, #med-table select { font-size: .85rem; padding: 5px 8px; }
        .remove-med { background: none; border: none; color: #dc3545; font-size: 1.1rem; cursor: pointer; }

        /* ── Lab tests checkboxes ── */
        .lab-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px 14px;
        }
        @media(max-width:768px){ .lab-grid { grid-template-columns: repeat(2,1fr); } }
        .lab-grid .form-check-label { font-size: .82rem; }

        /* ── Status badge ── */
        .badge-draft  { background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:600; }
        .badge-final  { background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:600; }

        /* ── Action bar ── */
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-bar .title { font-weight: 700; font-size: 1rem; color: var(--rdh-blue); flex: 1; }

        /* ── Print styles ── */
        @media print {
            .no-print { display: none !important; }
            body { background: white; font-size: 11pt; }
            .rx-card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
            .action-bar { display: none !important; }
            .patient-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- ── Action Bar ── -->
<div class="action-bar no-print">
    <span class="title"><i class="fa fa-file-medical me-2"></i>Digital Prescription</span>

    <?php if ($existing_rx): ?>
        <span class="badge-<?= $existing_rx['status'] ?>"><?= ucfirst($existing_rx['status']) ?></span>
        <span class="text-muted small">Care Ctx: <?= htmlspecialchars($existing_rx['care_context_ref']) ?></span>
    <?php endif; ?>

    <button form="rx-form" name="rx_status" value="draft"  type="submit" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-save me-1"></i> Save Draft
    </button>
    <button form="rx-form" name="rx_status" value="final"  type="submit" class="btn btn-success btn-sm">
        <i class="fa fa-check me-1"></i> Finalise
    </button>
    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fa fa-print me-1"></i> Print
    </button>
    <a href="appointments.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="container-fluid py-3 px-3 px-md-4">

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

    <?php if (!$appointment): ?>
        <!-- No appointment selected — show selector -->
        <div class="rx-card">
            <div class="rx-card-header"><i class="fa fa-calendar-check"></i> Select Appointment</div>
            <div class="rx-card-body">
                <?php if (empty($today_appts)): ?>
                    <p class="text-muted">No approved appointments for today. <a href="appointments.php">View all appointments</a></p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($today_appts as $ta): ?>
                            <div class="col-md-4 col-sm-6">
                                <a href="patient-form.php?appointment_id=<?= $ta['id'] ?>" class="text-decoration-none">
                                    <div class="border rounded p-3 h-100 d-flex align-items-center gap-3"
                                         style="transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
                                        <div style="width:42px;height:42px;border-radius:50%;background:#e8f0fb;display:flex;align-items:center;justify-content:center;color:var(--rdh-blue);font-size:1.2rem;flex-shrink:0;">
                                            <i class="fa fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="color:#222;"><?= htmlspecialchars($ta['name'] . ' ' . $ta['last_name']) ?></div>
                                            <div class="text-muted small"><?= date('h:i A', strtotime($ta['appointment_time'])) ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

    <!-- ── Patient Banner ── -->
    <div class="patient-banner">
        <div>
            <div class="pb-name">
                <?= htmlspecialchars(trim($patient['patient_name'] . ' ' . ($patient['patient_last_name'] ?? ''))) ?>
            </div>
            <div class="pb-meta">
                <?= $patient['patient_age'] ?> yrs &nbsp;|&nbsp;
                <?= htmlspecialchars($patient['gender'] ?? '—') ?> &nbsp;|&nbsp;
                <?php if (!empty($patient['blood_group'])): ?>BG: <?= htmlspecialchars($patient['blood_group']) ?> &nbsp;|&nbsp;<?php endif; ?>
                <?= htmlspecialchars($patient['patient_phone'] ?? '') ?>
            </div>
            <?php if (!empty($patient['abha_number'])): ?>
                <div class="pb-abha mt-1">
                    <i class="fa fa-id-card me-1"></i> ABHA: <?= htmlspecialchars($patient['abha_number']) ?>
                    <?php if ($patient['abha_linked']): ?><i class="fa fa-check-circle ms-1"></i><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="pb-abha mt-1" style="border-color:#aaa;color:#aaa;">ABHA not linked</div>
            <?php endif; ?>
        </div>
        <div>
            <div class="pb-meta">
                <i class="fa fa-calendar me-1"></i>
                <?= date('d M Y', strtotime($appointment['appointment_date'])) ?>
                &nbsp;<?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
            </div>
            <div class="pb-meta mt-1">
                <i class="fa fa-tag me-1"></i> Appt #<?= $appointment_id ?>
            </div>
        </div>
        <?php if ($existing_rx): ?>
            <div class="care-ctx">
                Care Context<br>
                <strong style="color:var(--rdh-teal);"><?= htmlspecialchars($existing_rx['care_context_ref']) ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── FORM ── -->
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
                                <label>BP (Systolic mmHg)</label>
                                <input type="number" class="form-control" name="bp_sys"    placeholder="120" value="<?= $vt('bp_systolic') ?>">
                            </div>
                            <div class="vital-item">
                                <label>BP (Diastolic mmHg)</label>
                                <input type="number" class="form-control" name="bp_dia"    placeholder="80"  value="<?= $vt('bp_diastolic') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Pulse (bpm)</label>
                                <input type="number" class="form-control" name="pulse"     placeholder="72"  value="<?= $vt('pulse') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Temperature (°F)</label>
                                <input type="text"   class="form-control" name="temp"      placeholder="98.6" value="<?= $vt('temperature') ?>">
                            </div>
                            <div class="vital-item">
                                <label>SpO₂ (%)</label>
                                <input type="number" class="form-control" name="spo2"      placeholder="98"  value="<?= $vt('spo2') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.1" class="form-control" name="weight" placeholder="65" value="<?= $vt('weight_kg') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Height (cm)</label>
                                <input type="number" class="form-control" name="height"    placeholder="165" value="<?= $vt('height_cm') ?>">
                            </div>
                            <div class="vital-item">
                                <label>Resp. Rate (br/min)</label>
                                <input type="number" class="form-control" name="rr"        placeholder="16"  value="<?= $vt('rr') ?>">
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
                            <label class="form-label text-muted small">ICD-10 Codes <span class="text-muted">(optional, comma-separated)</span></label>
                            <input type="text" name="icd_codes" class="form-control"
                                placeholder="e.g. J06.9, K29.7"
                                value="<?= $v('icd_codes') ?>">
                        </div>
                    </div>
                </div>

                <!-- Medications -->
                <div class="rx-card">
                    <div class="rx-card-header">
                        <i class="fa fa-pills"></i> Medications
                        <button type="button" onclick="addMedRow()" class="btn btn-sm ms-auto"
                            style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);padding:3px 12px;border-radius:6px;">
                            <i class="fa fa-plus me-1"></i> Add Row
                        </button>
                    </div>
                    <div class="rx-card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" id="med-table">
                                <thead>
                                    <tr>
                                        <th style="min-width:160px;">Medicine Name</th>
                                        <th style="min-width:90px;">Dose</th>
                                        <th style="min-width:120px;">Frequency</th>
                                        <th style="min-width:90px;">Duration</th>
                                        <th style="min-width:100px;">Route</th>
                                        <th style="min-width:130px;">Instructions</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="med-tbody">
                                    <?php foreach ($rx_meds as $m): ?>
                                    <tr>
                                        <td><input type="text" class="form-control" name="med_name[]"  value="<?= htmlspecialchars($m['name'] ?? '') ?>"  placeholder="Drug name"></td>
                                        <td><input type="text" class="form-control" name="med_dose[]"  value="<?= htmlspecialchars($m['dose'] ?? '') ?>"  placeholder="500mg"></td>
                                        <td>
                                            <select class="form-select" name="med_freq[]">
                                                <?php
                                                $freqs = ['','Once daily','Twice daily','Thrice daily','Four times/day','Every 6 hours','Every 8 hours','Every 12 hours','As needed (SOS)','Stat (immediately)','Weekly','At bedtime'];
                                                foreach ($freqs as $f): ?>
                                                    <option value="<?= $f ?>" <?= ($m['frequency'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?: '— Select —' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="text" class="form-control" name="med_dur[]"   value="<?= htmlspecialchars($m['duration'] ?? '') ?>" placeholder="5 days"></td>
                                        <td>
                                            <select class="form-select" name="med_route[]">
                                                <?php
                                                $routes = ['','Oral','IV','IM','SC','Topical','Inhaled','Sublingual','Rectal','Nasal'];
                                                foreach ($routes as $r): ?>
                                                    <option value="<?= $r ?>" <?= ($m['route'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?: '— Select —' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="text" class="form-control" name="med_instr[]" value="<?= htmlspecialchars($m['instructions'] ?? '') ?>" placeholder="After food"></td>
                                        <td><button type="button" class="remove-med" onclick="this.closest('tr').remove()"><i class="fa fa-times-circle"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Lab Tests -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-flask"></i> Investigations — Lab Tests</div>
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
                            <label class="form-label text-muted small">Additional / Custom Tests</label>
                            <input type="text" id="extra_lab" class="form-control" placeholder="Type additional tests, comma-separated">
                        </div>
                    </div>
                </div>

                <!-- Radiology -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-x-ray"></i> Radiology / Imaging</div>
                    <div class="rx-card-body">
                        <?php
                        $radio_opts = ['Chest X-Ray','Abdomen X-Ray','USG Abdomen','USG Pelvis','USG Whole Abdomen','CECT Abdomen','CT Chest','MRI Brain','ECG','2D Echo','TMT'];
                        $saved_radio = $existing_rx['radiology'] ?? '';
                        ?>
                        <div class="lab-grid mb-2">
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

                <!-- Advice & Follow-up -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-lightbulb"></i> Advice &amp; Follow-up</div>
                    <div class="rx-card-body">
                        <div class="mb-3">
                            <label class="form-label">Advice / Instructions to Patient</label>
                            <textarea name="advice" class="form-control" rows="3"
                                placeholder="Diet advice, rest, lifestyle modifications, red flags to watch for..."><?= $v('advice') ?></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="form-control"
                                    value="<?= htmlspecialchars($existing_rx['follow_up_date'] ?? '') ?>"
                                    min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Follow-up Notes</label>
                                <input type="text" name="follow_up_notes" class="form-control"
                                    placeholder="Review CBC, recheck BP, etc."
                                    value="<?= $v('follow_up_notes') ?>">
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /col-xl-9 -->

            <!-- ── Right sidebar ── -->
            <div class="col-xl-3">

                <!-- Doctor card -->
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-user-md"></i> Prescribing Doctor</div>
                    <div class="rx-card-body" style="font-size:.85rem;">
                        <strong>Dr. <?= htmlspecialchars($doctor['name'] ?? '') ?></strong><br>
                        <span class="text-muted"><?= htmlspecialchars($doctor['degrees'] ?? '') ?></span><br>
                        <span><?= htmlspecialchars($doctor['specialization'] ?? '') ?></span><br>
                        <?php if (!empty($doctor['hpr_id'])): ?>
                            <span class="text-success small"><i class="fa fa-check-circle me-1"></i>HPR: <?= htmlspecialchars($doctor['hpr_id']) ?></span><br>
                        <?php else: ?>
                            <span class="text-warning small"><i class="fa fa-exclamation-triangle me-1"></i>HPR not registered</span><br>
                        <?php endif; ?>
                        <span class="text-muted small"><?= htmlspecialchars($doctor['phone'] ?? '') ?></span>
                    </div>
                </div>

                <!-- ABHA / ABDM compliance -->
                <div class="rx-card">
                    <div class="rx-card-header" style="background:linear-gradient(135deg,#025a52,var(--rdh-teal));">
                        <i class="fa fa-shield-alt"></i> ABDM Compliance
                    </div>
                    <div class="rx-card-body" style="font-size:.82rem;">
                        <?php if (!empty($patient['abha_number'])): ?>
                            <div class="mb-2">
                                <div class="text-muted">ABHA Number</div>
                                <strong style="color:var(--rdh-teal);"><?= htmlspecialchars($patient['abha_number']) ?></strong>
                            </div>
                            <?php if (!empty($patient['abha_address'])): ?>
                            <div class="mb-2">
                                <div class="text-muted">ABHA Address</div>
                                <strong><?= htmlspecialchars($patient['abha_address']) ?></strong>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.8rem;">
                                Patient ABHA not linked. Records will be stored locally only.
                                <a href="find-patient-mobile.php" class="alert-link d-block mt-1">Link ABHA →</a>
                            </div>
                        <?php endif; ?>
                        <?php if ($existing_rx): ?>
                            <div class="mb-1">
                                <div class="text-muted">Care Context Ref</div>
                                <strong class="text-break" style="color:var(--rdh-blue);"><?= htmlspecialchars($existing_rx['care_context_ref']) ?></strong>
                            </div>
                            <div>
                                <div class="text-muted">Record Status</div>
                                <span class="badge-<?= $existing_rx['status'] ?>"><?= ucfirst($existing_rx['status']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Previous prescriptions -->
                <?php
                $prev_s = $conn->prepare("
                    SELECT p.id, p.visit_date, p.care_context_ref, p.status, p.diagnosis
                    FROM prescriptions p
                    WHERE p.patient_id = ? AND p.appointment_id != ?
                    ORDER BY p.visit_date DESC LIMIT 5
                ");
                $prev_s->bind_param('ii', $patient['patient_id'], $appointment_id);
                $prev_s->execute();
                $prev_rx = $prev_s->get_result()->fetch_all(MYSQLI_ASSOC);
                $prev_s->close();
                ?>
                <?php if (!empty($prev_rx)): ?>
                <div class="rx-card">
                    <div class="rx-card-header"><i class="fa fa-history"></i> Past Prescriptions</div>
                    <div class="rx-card-body p-0">
                        <ul class="list-group list-group-flush" style="font-size:.8rem;">
                            <?php foreach ($prev_rx as $pr): ?>
                                <li class="list-group-item py-2 px-3">
                                    <div class="fw-semibold"><?= date('d M Y', strtotime($pr['visit_date'])) ?></div>
                                    <div class="text-muted text-truncate" style="max-width:180px;"><?= htmlspecialchars($pr['diagnosis'] ?: '—') ?></div>
                                    <span class="badge-<?= $pr['status'] ?>"><?= ucfirst($pr['status']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Today's queue -->
                <?php if (!empty($today_appts)): ?>
                <div class="rx-card no-print">
                    <div class="rx-card-header"><i class="fa fa-list-ul"></i> Today's Queue</div>
                    <div class="rx-card-body p-0">
                        <ul class="list-group list-group-flush" style="font-size:.8rem;">
                            <?php foreach ($today_appts as $ta): ?>
                                <li class="list-group-item py-2 px-3 <?= $ta['id'] == $appointment_id ? 'active' : '' ?>">
                                    <a href="patient-form.php?appointment_id=<?= $ta['id'] ?>"
                                       class="<?= $ta['id'] == $appointment_id ? 'text-white' : 'text-dark' ?> text-decoration-none d-flex justify-content-between">
                                        <span><?= htmlspecialchars($ta['name'] . ' ' . $ta['last_name']) ?></span>
                                        <small><?= date('h:i A', strtotime($ta['appointment_time'])) ?></small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /col-xl-3 -->
        </div><!-- /row -->
    </form>

    <?php endif; /* appointment loaded */ ?>

</div><!-- /container -->

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Add medication row ── */
const freqOpts  = ['','Once daily','Twice daily','Thrice daily','Four times/day','Every 6 hours','Every 8 hours','Every 12 hours','As needed (SOS)','Stat (immediately)','Weekly','At bedtime'];
const routeOpts = ['','Oral','IV','IM','SC','Topical','Inhaled','Sublingual','Rectal','Nasal'];

function makeSelect(name, opts) {
    return '<select class="form-select" name="' + name + '">' +
        opts.map(o => '<option value="' + o + '">' + (o || '— Select —') + '</option>').join('') +
        '</select>';
}

function addMedRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control" name="med_name[]"  placeholder="Drug name"></td>
        <td><input type="text" class="form-control" name="med_dose[]"  placeholder="500mg"></td>
        <td>${makeSelect('med_freq[]',  freqOpts)}</td>
        <td><input type="text" class="form-control" name="med_dur[]"   placeholder="5 days"></td>
        <td>${makeSelect('med_route[]', routeOpts)}</td>
        <td><input type="text" class="form-control" name="med_instr[]" placeholder="After food"></td>
        <td><button type="button" class="remove-med" onclick="this.closest('tr').remove()"><i class="fa fa-times-circle"></i></button></td>
    `;
    document.getElementById('med-tbody').appendChild(tr);
    tr.querySelector('input').focus();
}

/* ── Sync checkbox → hidden input ── */
function syncChecks(cls, hiddenId) {
    const checked  = [...document.querySelectorAll('.' + cls + ':checked')].map(c => c.value);
    const extraEl  = (cls === 'lab-check') ? document.getElementById('extra_lab') : null;
    let   val      = checked.join(', ');
    if (extraEl && extraEl.value.trim()) val += (val ? ', ' : '') + extraEl.value.trim();
    document.getElementById(hiddenId).value = val;
}

document.querySelectorAll('.lab-check,.radio-check').forEach(cb => {
    cb.addEventListener('change', () => {
        syncChecks('lab-check',   'lab_tests_hidden');
        syncChecks('radio-check', 'radiology_hidden');
    });
});
const extraLabEl = document.getElementById('extra_lab');
if (extraLabEl) extraLabEl.addEventListener('input', () => syncChecks('lab-check', 'lab_tests_hidden'));

/* ── Before submit: sync checkboxes once more ── */
document.getElementById('rx-form')?.addEventListener('submit', function() {
    syncChecks('lab-check',   'lab_tests_hidden');
    syncChecks('radio-check', 'radiology_hidden');
});

/* ── Auto-dismiss alerts ── */
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        try { new bootstrap.Alert(a).close(); } catch(e) {}
    });
}, 5000);
</script>
</body>
</html>
