<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload        = doctor_jwt_guard();
$doctor_id      = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$patient_id     = (int)($_GET['id'] ?? 0);
$just_added     = isset($_GET['new']);

if (!$patient_id) { header('Location: '.BASE_URL.'doctor/my-patients.php'); exit; }

// Verify doctor has access to this patient
$chk = $conn->prepare("SELECT 1 FROM doctor_patients WHERE doctor_id=? AND patient_id=? LIMIT 1");
$chk->bind_param('ii', $doctor_id, $patient_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    // Also allow via appointments (old data)
    $chk2 = $conn->prepare("SELECT 1 FROM appointments WHERE doctor_id=? AND user_id=? LIMIT 1");
    $chk2->bind_param('ii', $doctor_id, $patient_id);
    $chk2->execute();
    if (!$chk2->get_result()->fetch_row()) {
        header('Location: '.BASE_URL.'doctor/my-patients.php'); exit;
    }
}

// Load patient
$stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) { header('Location: '.BASE_URL.'doctor/my-patients.php'); exit; }

// Calculate age
$age = '';
if (!empty($p['dob'])) {
    try {
        $dob = new DateTime($p['dob']);
        $age = $dob->diff(new DateTime())->y;
    } catch(Exception $e){}
}

$full_name = trim(($p['name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
if (!$full_name) $full_name = 'Unknown Patient';

// Load appointments
$appts_stmt = $conn->prepare("
    SELECT a.*, TIME_FORMAT(a.appointment_time,'%h:%i %p') AS fmt_time,
           DATE_FORMAT(a.appointment_date,'%d %b %Y') AS fmt_date
    FROM appointments a
    WHERE a.user_id=? AND a.doctor_id=?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 50
");
$appts_stmt->bind_param('ii', $patient_id, $doctor_id);
$appts_stmt->execute();
$appointments = $appts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Load documents (if table exists)
$docs = [];
$doc_check = $conn->query("SHOW TABLES LIKE 'patient_documents'");
if ($doc_check->num_rows > 0) {
    $doc_stmt = $conn->prepare("SELECT * FROM patient_documents WHERE patient_id=? AND doctor_id=? ORDER BY uploaded_at DESC LIMIT 100");
    $doc_stmt->bind_param('ii', $patient_id, $doctor_id);
    $doc_stmt->execute();
    $docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$sidebar_active = 'patients';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($full_name) ?> — Patient Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
/* Profile header */
.profile-header{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:22px 24px;margin-bottom:16px;}
.p-avatar{width:72px;height:72px;border-radius:50%;background:#0C74C5;color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:1.7rem;font-weight:700;flex-shrink:0;}
.p-name{font-size:1.2rem;font-weight:700;color:#1f2937;margin-bottom:2px;}
.p-sub{font-size:.8rem;color:#6b7280;}
.abha-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;
  font-size:.72rem;font-weight:700;letter-spacing:.3px;}
.abha-ok{background:#dcfce7;color:#166534;}
.abha-no{background:#fef3c7;color:#92400e;}

/* Tabs */
.profile-tabs{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:18px;background:#fff;
  border-radius:12px 12px 0 0;box-shadow:0 2px 8px rgba(0,0,0,.05);overflow-x:auto;}
.p-tab{padding:13px 20px;font-size:.84rem;font-weight:600;color:#6b7280;cursor:pointer;
  border-bottom:3px solid transparent;white-space:nowrap;transition:.15s;flex-shrink:0;}
.p-tab:hover{color:#0C74C5;}
.p-tab.active{color:#0C74C5;border-bottom-color:#0C74C5;}
.tab-pane{display:none;} .tab-pane.active{display:block;}

/* Info cards */
.info-section{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);padding:18px 20px;margin-bottom:14px;}
.info-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9ca3af;margin-bottom:2px;}
.info-value{font-size:.9rem;color:#1f2937;font-weight:500;}
.info-row{padding:9px 0;border-bottom:1px solid #f3f4f6;}
.info-row:last-child{border-bottom:none;}

/* History table */
.appt-row{padding:12px 16px;border-radius:10px;background:#fff;border:1px solid #e5e7eb;margin-bottom:8px;}
.appt-status{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:700;}
.status-confirmed{background:#dcfce7;color:#166534;}
.status-pending{background:#fef3c7;color:#92400e;}
.status-cancelled{background:#fee2e2;color:#991b1b;}
.status-completed{background:#dbeafe;color:#1e40af;}

/* Documents */
.doc-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;
  background:#fff;border:1px solid #e5e7eb;margin-bottom:8px;}
.doc-icon{width:38px;height:38px;border-radius:8px;background:#f0f9ff;display:flex;
  align-items:center;justify-content:center;color:#0C74C5;font-size:1rem;flex-shrink:0;}

/* Success banner */
.success-banner{background:#dcfce7;border:1px solid #bbf7d0;border-radius:10px;
  padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:.86rem;}
</style>
</head>
<body>
<main class="doctor-content">

<?php if ($just_added): ?>
<div class="success-banner">
  <i class="fa fa-check-circle fa-lg" style="color:#16a34a;"></i>
  <div><strong>Patient added successfully!</strong> Their profile is now in your panel.</div>
</div>
<?php endif; ?>

<!-- Profile Header -->
<div class="profile-header">
  <div class="d-flex align-items-start" style="gap:16px;">
    <div class="p-avatar"><?= strtoupper(substr($full_name,0,1)) ?></div>
    <div style="flex:1;min-width:0;">
      <div class="p-name"><?= htmlspecialchars($full_name) ?></div>
      <div class="p-sub">
        <?php if ($age): ?><?= $age ?> yrs<?php endif; ?>
        <?php if (!empty($p['gender'])): ?> &nbsp;·&nbsp; <?= htmlspecialchars($p['gender']) ?><?php endif; ?>
        <?php if (!empty($p['mobile'])): ?> &nbsp;·&nbsp; <?= htmlspecialchars($p['mobile']) ?><?php endif; ?>
      </div>
      <div class="mt-2 d-flex flex-wrap" style="gap:6px;">
        <?php if (!empty($p['abha_id']) && $p['abha_verified']): ?>
          <span class="abha-badge abha-ok"><i class="fa fa-check-circle mr-1"></i>ABHA Verified &nbsp;<?= htmlspecialchars($p['abha_id']) ?></span>
        <?php elseif (!empty($p['abha_id'])): ?>
          <span class="abha-badge" style="background:#f0f9ff;color:#0369a1;"><i class="fa fa-id-card-o mr-1"></i><?= htmlspecialchars($p['abha_id']) ?></span>
        <?php else: ?>
          <span class="abha-badge abha-no"><i class="fa fa-exclamation-circle mr-1"></i>No ABHA Linked</span>
        <?php endif; ?>
        <?php if (!empty($p['blood_group'])): ?>
          <span class="badge badge-light" style="font-size:.72rem;padding:4px 8px;"><?= htmlspecialchars($p['blood_group']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex flex-wrap" style="gap:6px;">
      <?php if (empty($p['abha_id'])): ?>
        <a href="<?= BASE_URL ?>doctor/add-patient-abha.php" class="btn btn-sm btn-outline-primary">
          <i class="fa fa-link mr-1"></i> Link ABHA
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>doctor/my-patients.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-arrow-left mr-1"></i> All Patients
      </a>
    </div>
  </div>
</div>

<!-- Tabs -->
<div class="profile-tabs">
  <div class="p-tab active" onclick="showTab('info',this)"><i class="fa fa-user mr-1"></i> Information</div>
  <div class="p-tab" onclick="showTab('docs',this)"><i class="fa fa-file-text-o mr-1"></i> Documents &amp; Notes</div>
  <div class="p-tab" onclick="showTab('medical',this)"><i class="fa fa-heartbeat mr-1"></i> Medical Info</div>
  <div class="p-tab" onclick="showTab('history',this)"><i class="fa fa-history mr-1"></i> Patient History</div>
</div>

<!-- ── TAB: Information ── -->
<div class="tab-pane active" id="tab-info">
  <div class="row">
    <div class="col-md-6">
      <div class="info-section">
        <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:12px;">
          <i class="fa fa-user mr-1" style="color:#0C74C5;"></i> Personal Details
        </div>
        <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($full_name) ?></div></div>
        <div class="info-row"><div class="info-label">Gender</div><div class="info-value"><?= htmlspecialchars($p['gender']??'—') ?></div></div>
        <div class="info-row"><div class="info-label">Date of Birth</div>
          <div class="info-value">
            <?php if (!empty($p['dob']) && $p['dob'] !== '0000-00-00'): ?>
              <?= date('d M Y', strtotime($p['dob'])) ?> <?php if($age): ?><span class="text-muted">(<?= $age ?> yrs)</span><?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </div>
        </div>
        <div class="info-row"><div class="info-label">Blood Group</div><div class="info-value"><?= htmlspecialchars($p['blood_group']??'—') ?></div></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="info-section">
        <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:12px;">
          <i class="fa fa-phone mr-1" style="color:#0C74C5;"></i> Contact
        </div>
        <div class="info-row"><div class="info-label">Mobile</div><div class="info-value"><?= htmlspecialchars($p['mobile']??'—') ?></div></div>
        <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($p['email']??'—') ?></div></div>
        <div class="info-row"><div class="info-label">Address</div>
          <div class="info-value" style="font-size:.84rem;">
            <?php
              $addr_parts = array_filter([$p['address']??'', $p['city']??'', $p['state']??'', $p['zip_code']??'']);
              echo $addr_parts ? htmlspecialchars(implode(', ', $addr_parts)) : '—';
            ?>
          </div>
        </div>
        <?php if (!empty($p['emergency_contact'])): ?>
        <div class="info-row"><div class="info-label">Emergency Contact</div><div class="info-value"><?= htmlspecialchars($p['emergency_contact']) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($p['abha_id'])): ?>
  <div class="info-section">
    <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:12px;">
      <i class="fa fa-id-card-o mr-1" style="color:#02c9b8;"></i> ABHA Details
    </div>
    <div class="row">
      <div class="col-md-4"><div class="info-row"><div class="info-label">ABHA Number</div>
        <div class="info-value" style="font-family:monospace;"><?= htmlspecialchars($p['abha_id']) ?></div></div></div>
      <div class="col-md-4"><div class="info-row"><div class="info-label">ABHA Address</div>
        <div class="info-value" style="font-family:monospace;"><?= htmlspecialchars($p['abha_address']??'—') ?></div></div></div>
      <div class="col-md-4"><div class="info-row"><div class="info-label">Status</div>
        <div class="info-value">
          <?php if ($p['abha_verified']): ?>
            <span class="abha-badge abha-ok"><i class="fa fa-check-circle mr-1"></i>Verified</span>
          <?php else: ?>
            <span class="abha-badge abha-no">Unverified</span>
          <?php endif; ?>
        </div></div></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── TAB: Documents & Notes ── -->
<div class="tab-pane" id="tab-docs">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div style="font-size:.84rem;color:#374151;font-weight:600;" id="docCountLabel"><?= count($docs) ?> document(s)</div>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleUploadForm()">
      <i class="fa fa-upload mr-1"></i> Upload Document
    </button>
  </div>

  <div class="info-section" id="uploadForm" style="display:none;">
    <div id="uploadError" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>
    <div class="form-row">
      <div class="col-md-6 mb-2">
        <label class="info-label d-block mb-1">Document Type</label>
        <select id="docType" class="form-control form-control-sm">
          <option value="Prescription">Prescription</option>
          <option value="Lab Report">Lab Report</option>
          <option value="X-Ray">X-Ray</option>
          <option value="MRI Scan">MRI Scan</option>
          <option value="CT Scan">CT Scan</option>
          <option value="Medical Certificate">Medical Certificate</option>
          <option value="Discharge Summary">Discharge Summary</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="col-md-6 mb-2">
        <label class="info-label d-block mb-1">File</label>
        <input type="file" id="docFile" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
        <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">Max 10MB — PDF, DOC, DOCX, JPG, PNG</div>
      </div>
      <div class="col-md-12 mb-2">
        <label class="info-label d-block mb-1">Description (optional)</label>
        <textarea id="docDescription" class="form-control form-control-sm" rows="2"></textarea>
      </div>
      <div class="col-md-12">
        <button type="button" class="btn btn-sm btn-primary-custom" id="btnUploadDoc" onclick="uploadDocument()">
          <i class="fa fa-upload mr-1"></i> Upload
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleUploadForm()">Cancel</button>
      </div>
    </div>
  </div>

  <div id="docsList">
  <?php if (empty($docs)): ?>
    <div class="info-section text-center py-4" id="noDocsMsg">
      <i class="fa fa-folder-open-o fa-2x text-muted mb-2"></i>
      <div class="text-muted" style="font-size:.86rem;">No documents uploaded yet.</div>
    </div>
  <?php else: ?>
    <?php foreach ($docs as $doc): ?>
    <div class="doc-item">
      <div class="doc-icon"><i class="fa fa-file-text-o"></i></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:.88rem;color:#1f2937;"><?= htmlspecialchars($doc['document_name'] ?? 'Document') ?></div>
        <?php if (!empty($doc['description'])): ?>
        <div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($doc['description']) ?></div>
        <?php endif; ?>
        <div style="font-size:.76rem;color:#9ca3af;"><?= date('d M Y', strtotime($doc['uploaded_at']??'now')) ?></div>
      </div>
      <?php if (!empty($doc['file_path'])): ?>
      <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-eye"></i>
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</div>

<!-- ── TAB: Medical Information ── -->
<div class="tab-pane" id="tab-medical">
  <div class="row">
    <div class="col-md-6">
      <div class="info-section">
        <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:12px;">
          <i class="fa fa-heartbeat mr-1" style="color:#ef4444;"></i> Vitals &amp; Medical
        </div>
        <div class="info-row"><div class="info-label">Blood Group</div><div class="info-value"><?= htmlspecialchars($p['blood_group']??'—') ?></div></div>
        <div class="info-row"><div class="info-label">Identification</div>
          <div class="info-value">
            <?= htmlspecialchars($p['identification_type']??'—') ?>
            <?php if(!empty($p['identification_number'])): ?> — <?= htmlspecialchars($p['identification_number']) ?><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="info-section">
        <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:12px;">
          <i class="fa fa-phone mr-1" style="color:#ef4444;"></i> Emergency
        </div>
        <div class="info-row"><div class="info-label">Emergency Contact</div>
          <div class="info-value"><?= htmlspecialchars($p['emergency_contact']??'—') ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="info-section">
    <div style="font-size:.84rem;color:#9ca3af;text-align:center;padding:12px 0;">
      <i class="fa fa-info-circle mr-1"></i>
      Detailed medical history will be available after appointments with prescriptions.
    </div>
  </div>
</div>

<!-- ── TAB: Patient History ── -->
<div class="tab-pane" id="tab-history">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div style="font-size:.84rem;color:#374151;font-weight:600;">
      <?= count($appointments) ?> appointment(s) on record
    </div>
  </div>
  <?php if (empty($appointments)): ?>
    <div class="info-section text-center py-4">
      <i class="fa fa-calendar-o fa-2x text-muted mb-2"></i>
      <div class="text-muted" style="font-size:.86rem;">No appointments yet.</div>
    </div>
  <?php else: ?>
    <?php foreach ($appointments as $a): ?>
    <?php
      $status   = $a['status'] ?? 'pending';
      $sc       = ['confirmed'=>'status-confirmed','pending'=>'status-pending',
                   'cancelled'=>'status-cancelled','completed'=>'status-completed'][$status] ?? 'status-pending';
    ?>
    <div class="appt-row">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div style="font-weight:600;font-size:.9rem;color:#1f2937;">
            <?= htmlspecialchars($a['fmt_date']??date('d M Y', strtotime($a['appointment_date']))) ?>
            <span class="text-muted" style="font-size:.8rem;"><?= htmlspecialchars($a['fmt_time']??$a['appointment_time']??'') ?></span>
          </div>
          <?php if (!empty($a['purpose'])): ?>
            <div style="font-size:.8rem;color:#374151;margin-top:2px;"><?= htmlspecialchars($a['purpose']) ?></div>
          <?php endif; ?>
          <?php if (!empty($a['notes'])): ?>
            <div style="font-size:.78rem;color:#9ca3af;margin-top:2px;"><?= htmlspecialchars(substr($a['notes'],0,80)) ?></div>
          <?php endif; ?>
        </div>
        <div class="d-flex flex-column align-items-end" style="gap:4px;">
          <span class="appt-status <?= $sc ?>"><?= ucfirst($status) ?></span>
          <?php if (!empty($a['appointment_type'])): ?>
            <span style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($a['appointment_type']) ?></span>
          <?php endif; ?>
          <?php if (!empty($a['id'])): ?>
            <a href="<?= BASE_URL ?>doctor/patient-form.php?appointment_id=<?= (int)$a['id'] ?>"
               class="btn btn-xs btn-outline-secondary mt-1" style="font-size:.7rem;padding:2px 7px;">
              <i class="fa fa-file-text-o mr-1"></i>Form
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function showTab(name,el){
  document.querySelectorAll('.tab-pane').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.p-tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  el.classList.add('active');
}
// Auto-open tab from URL hash
const hash=location.hash.replace('#','');
if(hash){const btn=document.querySelector('[onclick*="\''+hash+'\'"]');if(btn)btn.click();}

// ── Document upload ──
function toggleUploadForm(){
  const form = document.getElementById('uploadForm');
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
  document.getElementById('uploadError').style.display = 'none';
}

function uploadDocument(){
  const fileInput = document.getElementById('docFile');
  const errEl = document.getElementById('uploadError');
  errEl.style.display = 'none';

  if (!fileInput.files.length) {
    errEl.textContent = 'Please select a file to upload.';
    errEl.style.display = 'block';
    return;
  }

  const fd = new FormData();
  fd.append('patient_id', <?= (int)$patient_id ?>);
  fd.append('document_type', document.getElementById('docType').value);
  fd.append('description', document.getElementById('docDescription').value);
  fd.append('document_file', fileInput.files[0]);

  const btn = document.getElementById('btnUploadDoc');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Uploading...';

  fetch('<?= BASE_URL ?>doctor/api/patient-document-upload.php', {
    method: 'POST',
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-upload mr-1"></i> Upload';

      if (!data.success) {
        errEl.textContent = data.error || 'Upload failed';
        errEl.style.display = 'block';
        return;
      }

      const noDocsMsg = document.getElementById('noDocsMsg');
      if (noDocsMsg) noDocsMsg.remove();

      const doc = data.doc;
      const item = document.createElement('div');
      item.className = 'doc-item';
      item.innerHTML = `
        <div class="doc-icon"><i class="fa fa-file-text-o"></i></div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:.88rem;color:#1f2937;">${escapeHtml(doc.document_name)}</div>
          ${doc.description ? `<div style="font-size:.78rem;color:#6b7280;">${escapeHtml(doc.description)}</div>` : ''}
          <div style="font-size:.76rem;color:#9ca3af;">${new Date(doc.uploaded_at).toLocaleDateString()}</div>
        </div>
        <a href="<?= BASE_URL ?>${doc.file_path}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-eye"></i></a>
      `;
      document.getElementById('docsList').prepend(item);

      const countLabel = document.getElementById('docCountLabel');
      const n = document.querySelectorAll('#docsList .doc-item').length;
      countLabel.textContent = n + ' document(s)';

      fileInput.value = '';
      document.getElementById('docDescription').value = '';
      toggleUploadForm();
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-upload mr-1"></i> Upload';
      errEl.textContent = 'Network error: ' + err.message;
      errEl.style.display = 'block';
    });
}

function escapeHtml(text){
  const div = document.createElement('div');
  div.textContent = text ?? '';
  return div.innerHTML;
}
</script>
</body>
</html>
