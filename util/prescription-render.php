<?php
/**
 * util/prescription-render.php — shared read-only view of a finalised
 * consultation record (prescription + attached reports).
 *
 * Used by user/appointment-details.php and admin/all-appointment.php.
 * Emits its own scoped CSS (prefix .rxv-) once per request.
 *
 *   render_prescription_view($rxRow, $doctorRow, $patientRow, $documentRows, [
 *       'pdf_url'     => BASE_URL.'doctor/opd-slip.php?appointment_id='.$id,  // optional
 *       'doc_base'    => BASE_URL,     // prefix for attachment file_path links
 *       'title'       => 'Prescription & Reports',
 *   ]);
 *
 * $rx        : row from `prescriptions` (vitals + medications are JSON strings)
 * $doctor    : name, degrees, specialization, hpr_id, phone
 * $patient   : name, abha_number, abha_address, age/patient_age, gender
 * $documents : rows from `patient_documents` (document_name, description, file_path, file_type)
 */

if (!function_exists('render_prescription_view')) {

function _rxv_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }

function render_prescription_view(array $rx, array $doctor = [], array $patient = [], array $documents = [], array $opts = []): void
{
    static $cssDone = false;

    $base    = $opts['doc_base'] ?? (defined('BASE_URL') ? BASE_URL : '/');
    $title   = $opts['title']   ?? 'Prescription & Reports';
    $pdfUrl  = $opts['pdf_url'] ?? '';

    $vitals = [];
    $meds   = [];
    if (!empty($rx['vitals']))      { $vitals = json_decode($rx['vitals'], true) ?: []; }
    if (!empty($rx['medications'])) { $meds   = json_decode($rx['medications'], true) ?: []; }
    $meds = array_values(array_filter($meds, fn($m) => trim($m['name'] ?? '') !== ''));

    $age = $patient['patient_age'] ?? $patient['age'] ?? '';

    $vitalLabels = [
        'bp_systolic' => 'BP Sys', 'bp_diastolic' => 'BP Dia', 'pulse' => 'Pulse',
        'temperature' => 'Temp °F', 'spo2' => 'SpO₂ %', 'weight_kg' => 'Weight kg',
        'height_cm' => 'Height cm', 'rr' => 'Resp. Rate',
    ];
    $vitalShown = array_filter($vitalLabels, fn($k) => trim((string)($vitals[$k] ?? '')) !== '', ARRAY_FILTER_USE_KEY);

    if (!$cssDone) {
        $cssDone = true;
        ?>
<style>
.rxv-wrap{font-size:.88rem;color:#1f2937;}
.rxv-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:flex-start;
  border-bottom:2px solid #0C74C5;padding-bottom:10px;margin-bottom:14px;}
.rxv-head h3{margin:0;font-size:1rem;font-weight:700;color:#0C74C5;}
.rxv-meta{font-size:.76rem;color:#6b7280;}
.rxv-pill{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.rxv-pill.final{background:#d4edda;color:#155724;}
.rxv-pill.draft{background:#fff3cd;color:#856404;}
.rxv-sec{margin-bottom:12px;}
.rxv-sec > .rxv-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#0C74C5;margin-bottom:4px;}
.rxv-sec .rxv-val{white-space:pre-wrap;}
.rxv-vitals{display:flex;flex-wrap:wrap;gap:8px;}
.rxv-vitals .v{background:#f0f7ff;border:1px solid #d6e9fb;border-radius:8px;padding:5px 10px;font-size:.8rem;}
.rxv-vitals .v b{color:#0C74C5;}
.rxv-tbl{width:100%;border-collapse:collapse;font-size:.82rem;}
.rxv-tbl th{background:#eef5ff;color:#0C74C5;text-align:left;padding:6px 8px;font-size:.74rem;text-transform:uppercase;letter-spacing:.4px;}
.rxv-tbl td{padding:6px 8px;border-bottom:1px solid #eef2f7;vertical-align:top;}
.rxv-files{list-style:none;padding:0;margin:0;}
.rxv-files li{padding:7px 0;border-bottom:1px solid #eef2f7;}
.rxv-files a{color:#0C74C5;text-decoration:none;font-weight:600;}
.rxv-files a:hover{text-decoration:underline;}
.rxv-files .d{font-size:.76rem;color:#6b7280;}
.rxv-foot{margin-top:12px;padding-top:10px;border-top:1px dashed #d1d5db;font-size:.74rem;color:#6b7280;
  display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;}
@media print{.rxv-noprint{display:none!important;}}
</style>
        <?php
    }
    ?>
<div class="rxv-wrap">
  <div class="rxv-head">
    <div>
      <h3><i class="fa fa-file-medical me-1"></i><?= _rxv_e($title) ?></h3>
      <div class="rxv-meta">
        <?php if (!empty($rx['visit_date'])): ?>Visit: <?= _rxv_e(date('d M Y', strtotime($rx['visit_date']))) ?> &nbsp;·&nbsp;<?php endif; ?>
        Dr. <?= _rxv_e($doctor['name'] ?? '') ?><?php if (!empty($doctor['specialization'])): ?> (<?= _rxv_e($doctor['specialization']) ?>)<?php endif; ?>
        <?php if (!empty($doctor['hpr_id'])): ?> &nbsp;·&nbsp; HPR: <?= _rxv_e($doctor['hpr_id']) ?><?php endif; ?>
      </div>
    </div>
    <div class="text-end">
      <span class="rxv-pill <?= ($rx['status'] ?? 'draft') === 'final' ? 'final' : 'draft' ?>"><?= _rxv_e(ucfirst($rx['status'] ?? 'draft')) ?></span>
      <?php if ($pdfUrl): ?>
        <a href="<?= _rxv_e($pdfUrl) ?>" target="_blank" class="btn btn-sm btn-primary ms-1 rxv-noprint" style="background:#0C74C5;border-color:#0C74C5;">
          <i class="fa fa-download me-1"></i> PDF
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($patient['abha_number'])): ?>
  <div class="rxv-sec">
    <div class="rxv-lbl">ABHA</div>
    <div class="rxv-val"><?= _rxv_e($patient['abha_number']) ?><?php if (!empty($patient['abha_address'])): ?> &nbsp;·&nbsp; <?= _rxv_e($patient['abha_address']) ?><?php endif; ?></div>
  </div>
  <?php endif; ?>

  <?php if ($vitalShown): ?>
  <div class="rxv-sec">
    <div class="rxv-lbl">Vitals</div>
    <div class="rxv-vitals">
      <?php foreach ($vitalShown as $k => $label): ?>
        <span class="v"><b><?= _rxv_e($label) ?>:</b> <?= _rxv_e($vitals[$k]) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php foreach ([
      'chief_complaints' => 'Chief Complaints',
      'examination'      => 'Examination',
      'diagnosis'        => 'Diagnosis',
  ] as $f => $label): ?>
    <?php if (trim((string)($rx[$f] ?? '')) !== ''): ?>
    <div class="rxv-sec">
      <div class="rxv-lbl"><?= $label ?><?php if ($f === 'diagnosis' && !empty($rx['icd_codes'])): ?> <span class="text-muted">(ICD: <?= _rxv_e($rx['icd_codes']) ?>)</span><?php endif; ?></div>
      <div class="rxv-val"><?= _rxv_e($rx[$f]) ?></div>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if ($meds): ?>
  <div class="rxv-sec">
    <div class="rxv-lbl">Medications (Rx)</div>
    <div style="overflow-x:auto;">
    <table class="rxv-tbl">
      <thead><tr><th>Drug</th><th>Dose</th><th>Frequency</th><th>Duration</th><th>Route</th><th>Instructions</th></tr></thead>
      <tbody>
        <?php foreach ($meds as $m): ?>
        <tr>
          <td><?= _rxv_e($m['name'] ?? '') ?></td>
          <td><?= _rxv_e($m['dose'] ?? '') ?></td>
          <td><?= _rxv_e($m['frequency'] ?? '') ?></td>
          <td><?= _rxv_e($m['duration'] ?? '') ?></td>
          <td><?= _rxv_e($m['route'] ?? '') ?></td>
          <td><?= _rxv_e($m['instructions'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>

  <?php foreach ([
      'lab_tests'       => 'Lab Investigations',
      'radiology'       => 'Radiology / Imaging',
      'report_findings' => 'Report Findings / Results',
      'advice'          => 'Advice',
  ] as $f => $label): ?>
    <?php if (trim((string)($rx[$f] ?? '')) !== ''): ?>
    <div class="rxv-sec">
      <div class="rxv-lbl"><?= $label ?></div>
      <div class="rxv-val"><?= _rxv_e($rx[$f]) ?></div>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if (!empty($rx['follow_up_date']) || trim((string)($rx['follow_up_notes'] ?? '')) !== ''): ?>
  <div class="rxv-sec">
    <div class="rxv-lbl">Follow-up</div>
    <div class="rxv-val"><?php
      if (!empty($rx['follow_up_date'])) echo _rxv_e(date('d M Y', strtotime($rx['follow_up_date'])));
      if (!empty($rx['follow_up_notes'])) echo (!empty($rx['follow_up_date']) ? ' — ' : '') . _rxv_e($rx['follow_up_notes']);
    ?></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($documents)): ?>
  <div class="rxv-sec">
    <div class="rxv-lbl">Attached Reports</div>
    <ul class="rxv-files">
      <?php foreach ($documents as $d): ?>
      <li>
        <a href="<?= _rxv_e(rtrim($base,'/').'/'.ltrim($d['file_path'],'/')) ?>" target="_blank">
          <i class="fa fa-paperclip me-1"></i><?= _rxv_e($d['document_name'] ?? 'Document') ?>
        </a>
        <?php if (!empty($d['description'])): ?><div class="d"><?= _rxv_e($d['description']) ?></div><?php endif; ?>
        <?php if (!empty($d['uploaded_at'])): ?><div class="d"><?= _rxv_e(date('d M Y', strtotime($d['uploaded_at']))) ?></div><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="rxv-foot">
    <span><?php if (!empty($rx['care_context_ref'])): ?>Care Context: <?= _rxv_e($rx['care_context_ref']) ?><?php endif; ?></span>
    <span>Digitally generated · ABDM-aligned</span>
  </div>
</div>
<?php
}

}
