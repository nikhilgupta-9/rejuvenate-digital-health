<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);
$patient_id = (int) ($_GET['id'] ?? 0);
$just_added = isset($_GET['new']);

if (!$patient_id) {
  header('Location: ' . BASE_URL . 'doctor/my-patients.php');
  exit;
}

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
    header('Location: ' . BASE_URL . 'doctor/my-patients.php');
    exit;
  }
}

// Load patient
$stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) {
  header('Location: ' . BASE_URL . 'doctor/my-patients.php');
  exit;
}

// Calculate age
$age = '';
if (!empty($p['dob'])) {
  try {
    $dob = new DateTime($p['dob']);
    $age = $dob->diff(new DateTime())->y;
  } catch (Exception $e) {
  }
}

$full_name = trim(($p['name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
if (!$full_name)
  $full_name = 'Unknown Patient';

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
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/style.css">
  <style>
    /* Profile header */
    .profile-header {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
      padding: 22px 24px;
      margin-bottom: 16px;
    }

    .p-avatar {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: #0C74C5;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.7rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .p-name {
      font-size: 1.2rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 2px;
    }

    .p-sub {
      font-size: .8rem;
      color: #6b7280;
    }

    .abha-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .3px;
    }

    .abha-ok {
      background: #dcfce7;
      color: #166534;
    }

    .abha-no {
      background: #fef3c7;
      color: #92400e;
    }

    /* Tabs */
    .profile-tabs {
      display: flex;
      gap: 0;
      border-bottom: 2px solid #e5e7eb;
      margin-bottom: 18px;
      background: #fff;
      border-radius: 12px 12px 0 0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
      overflow-x: auto;
    }

    .p-tab {
      padding: 13px 20px;
      font-size: .84rem;
      font-weight: 600;
      color: #6b7280;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      white-space: nowrap;
      transition: .15s;
      flex-shrink: 0;
    }

    .p-tab:hover {
      color: #0C74C5;
    }

    .p-tab.active {
      color: #0C74C5;
      border-bottom-color: #0C74C5;
    }

    .tab-pane {
      display: none;
    }

    .tab-pane.active {
      display: block;
    }

    /* Info cards */
    .info-section {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
      padding: 18px 20px;
      margin-bottom: 14px;
    }

    .info-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: #9ca3af;
      margin-bottom: 2px;
    }

    .info-value {
      font-size: .9rem;
      color: #1f2937;
      font-weight: 500;
    }

    .info-row {
      padding: 9px 0;
      border-bottom: 1px solid #f3f4f6;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    /* History table */
    .appt-row {
      padding: 12px 16px;
      border-radius: 10px;
      background: #fff;
      border: 1px solid #e5e7eb;
      margin-bottom: 8px;
    }

    .appt-status {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: .7rem;
      font-weight: 700;
    }

    .status-confirmed {
      background: #dcfce7;
      color: #166534;
    }

    .status-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-cancelled {
      background: #fee2e2;
      color: #991b1b;
    }

    .status-completed {
      background: #dbeafe;
      color: #1e40af;
    }

    /* Documents */
    .doc-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      border-radius: 10px;
      background: #fff;
      border: 1px solid #e5e7eb;
      margin-bottom: 8px;
    }

    .doc-icon {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #f0f9ff;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #0C74C5;
      font-size: 1rem;
      flex-shrink: 0;
    }

    /* Success banner */
    .success-banner {
      background: #dcfce7;
      border: 1px solid #bbf7d0;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: .86rem;
    }
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
        <div class="p-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
        <div style="flex:1;min-width:0;">
          <div class="p-name"><?= htmlspecialchars($full_name) ?></div>
          <div class="p-sub">
            <?php if ($age): ?>   <?= $age ?> yrs<?php endif; ?>
            <?php if (!empty($p['gender'])): ?> &nbsp;·&nbsp; <?= htmlspecialchars($p['gender']) ?><?php endif; ?>
            <?php if (!empty($p['mobile'])): ?> &nbsp;·&nbsp; <?= htmlspecialchars($p['mobile']) ?><?php endif; ?>
          </div>
          <div class="mt-2 d-flex flex-wrap" style="gap:6px;">
            <?php if (!empty($p['abha_id']) && $p['abha_verified']): ?>
              <span class="abha-badge abha-ok"><i class="fa fa-check-circle mr-1"></i>ABHA Verified
                &nbsp;<?= htmlspecialchars($p['abha_id']) ?></span>
            <?php elseif (!empty($p['abha_id'])): ?>
              <span class="abha-badge" style="background:#f0f9ff;color:#0369a1;"><i
                  class="fa fa-id-card-o mr-1"></i><?= htmlspecialchars($p['abha_id']) ?></span>
            <?php else: ?>
              <span class="abha-badge abha-no"><i class="fa fa-exclamation-circle mr-1"></i>No ABHA Linked</span>
            <?php endif; ?>
            <?php if (!empty($p['blood_group'])): ?>
              <span class="badge badge-light"
                style="font-size:.72rem;padding:4px 8px;"><?= htmlspecialchars($p['blood_group']) ?></span>
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
      <div class="p-tab" onclick="showTab('docs',this)"><i class="fa fa-file-text-o mr-1"></i> Documents &amp; Notes
      </div>
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
            <div class="info-row">
              <div class="info-label">Full Name</div>
              <div class="info-value"><?= htmlspecialchars($full_name) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Gender</div>
              <div class="info-value"><?= htmlspecialchars($p['gender'] ?? '—') ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Date of Birth</div>
              <div class="info-value">
                <?php if (!empty($p['dob']) && $p['dob'] !== '0000-00-00'): ?>
                  <?= date('d M Y', strtotime($p['dob'])) ?>   <?php if ($age): ?><span class="text-muted">(<?= $age ?>
                      yrs)</span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </div>
            </div>
            <div class="info-row">
              <div class="info-label">Blood Group</div>
              <div class="info-value"><?= htmlspecialchars($p['blood_group'] ?? '—') ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-section">
            <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:12px;">
              <i class="fa fa-phone mr-1" style="color:#0C74C5;"></i> Contact
            </div>
            <div class="info-row">
              <div class="info-label">Mobile</div>
              <div class="info-value"><?= htmlspecialchars($p['mobile'] ?? '—') ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Email</div>
              <div class="info-value"><?= htmlspecialchars($p['email'] ?? '—') ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Address</div>
              <div class="info-value" style="font-size:.84rem;">
                <?php
                $addr_parts = array_filter([$p['address'] ?? '', $p['city'] ?? '', $p['state'] ?? '', $p['zip_code'] ?? '']);
                echo $addr_parts ? htmlspecialchars(implode(', ', $addr_parts)) : '—';
                ?>
              </div>
            </div>
            <?php if (!empty($p['emergency_contact'])): ?>
              <div class="info-row">
                <div class="info-label">Emergency Contact</div>
                <div class="info-value"><?= htmlspecialchars($p['emergency_contact']) ?></div>
              </div>
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
            <div class="col-md-4">
              <div class="info-row">
                <div class="info-label">ABHA Number</div>
                <div class="info-value" style="font-family:monospace;"><?= htmlspecialchars($p['abha_id']) ?></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="info-row">
                <div class="info-label">ABHA Address</div>
                <div class="info-value" style="font-family:monospace;"><?= htmlspecialchars($p['abha_address'] ?? '—') ?>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                  <?php if ($p['abha_verified']): ?>
                    <span class="abha-badge abha-ok"><i class="fa fa-check-circle mr-1"></i>Verified</span>
                  <?php else: ?>
                    <span class="abha-badge abha-no">Unverified</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="info-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div style="font-size:.8rem;font-weight:700;color:#374151;">
            <i class="fa fa-history mr-1" style="color:#0C74C5;"></i> Appointment History
          </div>
          <div style="font-size:.8rem;color:#9ca3af;"><?= count($appointments) ?> appointment(s) on record</div>
        </div>
        <?php if (empty($appointments)): ?>
          <div class="text-center py-4">
            <i class="fa fa-calendar-o fa-2x text-muted mb-2"></i>
            <div class="text-muted" style="font-size:.86rem;">No appointments yet.</div>
          </div>
        <?php else: ?>
          <?php foreach ($appointments as $a): ?>
            <?php
            $status = $a['status'] ?? 'pending';
            $sc = [
              'confirmed' => 'status-confirmed',
              'pending' => 'status-pending',
              'cancelled' => 'status-cancelled',
              'completed' => 'status-completed'
            ][$status] ?? 'status-pending';
            ?>
            <div class="appt-row">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div style="font-weight:600;font-size:.9rem;color:#1f2937;">
                    <?= htmlspecialchars($a['fmt_date'] ?? date('d M Y', strtotime($a['appointment_date']))) ?>
                    <span class="text-muted"
                      style="font-size:.8rem;"><?= htmlspecialchars($a['fmt_time'] ?? $a['appointment_time'] ?? '') ?></span>
                  </div>
                  <?php if (!empty($a['purpose'])): ?>
                    <div style="font-size:.8rem;color:#374151;margin-top:2px;"><?= htmlspecialchars($a['purpose']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($a['notes'])): ?>
                    <div style="font-size:.78rem;color:#9ca3af;margin-top:2px;">
                      <?= htmlspecialchars(substr($a['notes'], 0, 80)) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="d-flex flex-column align-items-end" style="gap:4px;">
                  <span class="appt-status <?= $sc ?>"><?= ucfirst($status) ?></span>
                  <?php if (!empty($a['appointment_type'])): ?>
                    <span style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($a['appointment_type']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($a['id'])): ?>
                    <a href="<?= BASE_URL ?>doctor/patient-form.php?appointment_id=<?= (int) $a['id'] ?>"
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
    </div>

    <!-- ── TAB: Documents & Notes ── -->
    <div class="tab-pane" id="tab-docs">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div style="font-size:.84rem;color:#374151;font-weight:600;" id="docCountLabel"><?= count($docs) ?> document(s)
        </div>
        <div class="docs-toolbar mb-0">
          <button type="button" title="Sort by date" onclick="toggleDocSort()"><i class="fa fa-sort" id="sortIcon"></i></button>
          <button type="button" title="Upload document" onclick="toggleUploadForm()"><i class="fa fa-plus"></i></button>
        </div>
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
            <input type="file" id="docFile" class="form-control form-control-sm"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
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
            <div class="doc-item" data-doc-id="<?= (int) $doc['id'] ?>" data-uploaded="<?= htmlspecialchars($doc['uploaded_at'] ?? '') ?>">
              <div class="doc-icon"><i class="fa fa-file-text-o"></i></div>
              <div style="flex:1;min-width:0;">
                <div class="doc-name" style="font-weight:600;font-size:.88rem;color:#1f2937;">
                  <?= htmlspecialchars($doc['document_name'] ?? 'Document') ?>
                </div>
                <div class="doc-desc" style="font-size:.78rem;color:#6b7280;<?= empty($doc['description']) ? 'display:none;' : '' ?>">
                  <?= htmlspecialchars($doc['description'] ?? '') ?>
                </div>
                <div style="font-size:.76rem;color:#9ca3af;"><?= date('d M Y', strtotime($doc['uploaded_at'] ?? 'now')) ?>
                </div>
              </div>
              <?php if (!empty($doc['file_path'])): ?>
                <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank"
                  class="btn btn-sm btn-outline-secondary doc-view-link">
                  <i class="fa fa-eye"></i>
                </a>
              <?php endif; ?>
              <button type="button" class="doc-menu-btn" onclick="toggleDocActions(this)"><i class="fa fa-bars"></i></button>

              <div class="doc-actions-row">
                <button type="button" class="doc-action-btn" onclick="openEditDocModal(this)">
                  <i class="fa fa-pencil"></i> Edit
                </button>
                <button type="button" class="doc-action-btn" onclick="openShareDocModal(this)">
                  <i class="fa fa-share-alt"></i> Share
                </button>
                <button type="button" class="doc-action-btn" onclick="copyDocLink(this)">
                  <i class="fa fa-link"></i> Send Link
                </button>
                <button type="button" class="doc-action-btn danger" onclick="removeDocument(this)">
                  <i class="fa fa-trash"></i> Remove
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <button type="button" class="floating-add-btn" title="Upload document" onclick="toggleUploadForm(); document.getElementById('tab-docs').scrollIntoView({behavior:'smooth'});">
      <i class="fa fa-plus"></i>
    </button>

    <!-- Edit Document Modal (plain vanilla-JS overlay — no Bootstrap JS on this page) -->
    <div class="cm-overlay" id="editDocModal">
      <div class="cm-box">
        <div class="cm-header">
          <h6 class="mb-0 font-weight-bold">Edit Document</h6>
          <button type="button" class="cm-close" onclick="closeModal('editDocModal')">&times;</button>
        </div>
        <div class="cm-body">
          <div id="editDocError" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>
          <div class="form-field">
            <label>Document Name</label>
            <input type="text" id="editDocName" class="form-control">
          </div>
          <div class="form-field mb-0">
            <label>Description</label>
            <textarea id="editDocDescription" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="cm-footer">
          <button type="button" class="btn btn-outline-secondary" onclick="closeModal('editDocModal')">Cancel</button>
          <button type="button" class="btn btn-primary-custom" id="btnSaveDocEdit" onclick="saveDocEdit()">Save</button>
        </div>
      </div>
    </div>

    <!-- Share Document Modal -->
    <div class="cm-overlay" id="shareDocModal">
      <div class="cm-box">
        <div class="cm-header">
          <h6 class="mb-0 font-weight-bold">Share Document</h6>
          <button type="button" class="cm-close" onclick="closeModal('shareDocModal')">&times;</button>
        </div>
        <div class="cm-body">
          <div id="shareDocError" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>
          <div id="shareDocSuccess" class="alert alert-success" style="display:none;font-size:.82rem;"></div>
          <div class="form-field">
            <label>Recipient Email</label>
            <input type="email" id="shareDocEmail" class="form-control" placeholder="patient@example.com"
              value="<?= htmlspecialchars($p['email'] ?? '') ?>">
          </div>
          <div class="form-field mb-0">
            <label>Deliver via</label>
            <div class="gender-options">
              <label><input type="radio" name="shareChannel" value="email" checked> Email</label>
              <label style="color:#9ca3af;"><input type="radio" name="shareChannel" value="sms" disabled> SMS (not configured)</label>
              <label style="color:#9ca3af;"><input type="radio" name="shareChannel" value="whatsapp" disabled> WhatsApp (not configured)</label>
            </div>
          </div>
        </div>
        <div class="cm-footer">
          <button type="button" class="btn btn-outline-secondary" onclick="closeModal('shareDocModal')">Cancel</button>
          <button type="button" class="btn btn-primary-custom" id="btnSendShare" onclick="sendDocShare()">
            <i class="fa fa-paper-plane mr-1"></i> Send
          </button>
        </div>
      </div>
    </div>

    <!-- ── TAB: Medical Information ── -->
    <div class="tab-pane" id="tab-medical">
      <div class="info-section">
        <div class="section-title"><i class="fa fa-heartbeat mr-2" style="color:#ef4444;"></i>Medical Information</div>

        <div id="medicalError" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>
        <div id="medicalSuccess" class="alert alert-success" style="display:none;font-size:.82rem;"></div>

        <div class="form-field">
          <label>1) Allergies</label>
          <textarea id="medAllergies" class="form-control" rows="2"
            placeholder="Enter data"><?= htmlspecialchars($p['allergies'] ?? '') ?></textarea>
        </div>
        <div class="form-field">
          <label>2) Existing Condition</label>
          <textarea id="medExistingCondition" class="form-control" rows="2"
            placeholder="Enter data"><?= htmlspecialchars($p['existing_condition'] ?? '') ?></textarea>
        </div>
        <div class="form-field">
          <label>3) Current Medication</label>
          <textarea id="medCurrentMedication" class="form-control" rows="2"
            placeholder="Enter data"><?= htmlspecialchars($p['current_medication'] ?? '') ?></textarea>
        </div>
        <div class="form-field">
          <label>4) Medical History</label>
          <textarea id="medHistory" class="form-control" rows="3"
            placeholder="Enter data"><?= htmlspecialchars($p['medical_history'] ?? '') ?></textarea>
        </div>

        <button type="button" class="btn btn-success" style="min-width:120px;" id="btnSaveMedical"
          onclick="saveMedicalInfo()">SAVE</button>
      </div>
    </div>

    <!-- ── TAB: Patient History (Health Card) ── -->
    <div class="tab-pane" id="tab-history">
      <div class="info-section">
        <div class="d-flex justify-content-between align-items-center">
          <div class="section-title mb-0"><i class="fa fa-id-card mr-2" style="color:#0C74C5;"></i>Health Card</div>
        </div>

        <div id="demoError" class="alert alert-danger mt-3" style="display:none;font-size:.82rem;"></div>
        <div id="demoSuccess" class="alert alert-success mt-3" style="display:none;font-size:.82rem;"></div>

        <div style="font-size:.8rem;font-weight:700;color:#6b7280;margin:18px 0 12px;">Profile Information</div>

        <div class="form-field">
          <label>1) Name</label>
          <input type="text" id="demoName" class="form-control" value="<?= htmlspecialchars($full_name) ?>">
        </div>

        <div class="form-field">
          <label>2) Gender</label>
          <div class="gender-options">
            <label><input type="radio" name="demoGender" value="Male" <?= ($p['gender'] ?? '') === 'Male' ? 'checked' : '' ?>> Male</label>
            <label><input type="radio" name="demoGender" value="Female" <?= ($p['gender'] ?? '') === 'Female' ? 'checked' : '' ?>> Female</label>
            <label><input type="radio" name="demoGender" value="Other" <?= ($p['gender'] ?? '') === 'Other' ? 'checked' : '' ?>> Other</label>
          </div>
        </div>

        <div class="form-field">
          <label>3) Blood Group</label>
          <div class="bg-grid">
            <?php foreach (['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $bg): ?>
              <label>
                <input type="radio" name="demoBloodGroup" value="<?= $bg ?>"
                  <?= ($p['blood_group'] ?? '') === $bg ? 'checked' : '' ?>> <?= $bg ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-field">
          <label>4) Email</label>
          <input type="email" id="demoEmail" class="form-control" placeholder="Enter data"
            value="<?= htmlspecialchars($p['email'] ?? '') ?>">
        </div>

        <div class="form-field">
          <label>5) Phone No</label>
          <input type="text" id="demoPhone" class="form-control" maxlength="10" inputmode="numeric"
            value="<?= htmlspecialchars($p['mobile'] ?? '') ?>">
        </div>

        <div class="d-flex" style="gap:10px;">
          <button type="button" class="btn btn-success" style="min-width:110px;" id="btnSaveDemo"
            onclick="saveDemographics()">SAVE</button>
          <a href="<?= BASE_URL ?>doctor/api/patient-demographics-pdf.php?patient_id=<?= (int) $patient_id ?>"
            class="btn btn-outline-success" style="min-width:110px;">SAVE AS PDF</a>
        </div>
      </div>
    </div>

    <script>
      function showTab(name, el) {
        document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.p-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        el.classList.add('active');
      }
      // Auto-open tab from URL hash
      const hash = location.hash.replace('#', '');
      if (hash) { const btn = document.querySelector('[onclick*="\'' + hash + '\'"]'); if (btn) btn.click(); }

      // ── Document upload ──
      function toggleUploadForm() {
        const form = document.getElementById('uploadForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        document.getElementById('uploadError').style.display = 'none';
      }

      function uploadDocument() {
        const fileInput = document.getElementById('docFile');
        const errEl = document.getElementById('uploadError');
        errEl.style.display = 'none';

        if (!fileInput.files.length) {
          errEl.textContent = 'Please select a file to upload.';
          errEl.style.display = 'block';
          return;
        }

        const fd = new FormData();
        fd.append('patient_id', <?= (int) $patient_id ?>);
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
            item.dataset.docId = doc.id;
            item.dataset.uploaded = doc.uploaded_at;
            item.innerHTML = `
        <div class="doc-icon"><i class="fa fa-file-text-o"></i></div>
        <div style="flex:1;min-width:0;">
          <div class="doc-name" style="font-weight:600;font-size:.88rem;color:#1f2937;">${escapeHtml(doc.document_name)}</div>
          <div class="doc-desc" style="font-size:.78rem;color:#6b7280;${doc.description ? '' : 'display:none;'}">${escapeHtml(doc.description || '')}</div>
          <div style="font-size:.76rem;color:#9ca3af;">${new Date(doc.uploaded_at).toLocaleDateString()}</div>
        </div>
        <a href="<?= BASE_URL ?>${doc.file_path}" target="_blank" class="btn btn-sm btn-outline-secondary doc-view-link"><i class="fa fa-eye"></i></a>
        <button type="button" class="doc-menu-btn" onclick="toggleDocActions(this)"><i class="fa fa-bars"></i></button>
        <div class="doc-actions-row">
          <button type="button" class="doc-action-btn" onclick="openEditDocModal(this)"><i class="fa fa-pencil"></i> Edit</button>
          <button type="button" class="doc-action-btn" onclick="openShareDocModal(this)"><i class="fa fa-share-alt"></i> Share</button>
          <button type="button" class="doc-action-btn" onclick="copyDocLink(this)"><i class="fa fa-link"></i> Send Link</button>
          <button type="button" class="doc-action-btn danger" onclick="removeDocument(this)"><i class="fa fa-trash"></i> Remove</button>
        </div>
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

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
      }

      const PATIENT_ID = <?= (int) $patient_id ?>;

      function showField(id, msg) {
        const el = document.getElementById(id);
        el.textContent = msg;
        el.style.display = 'block';
      }
      function hideField(id) {
        document.getElementById(id).style.display = 'none';
      }

      // ── Medical Information ──
      function saveMedicalInfo() {
        hideField('medicalError');
        hideField('medicalSuccess');

        const btn = document.getElementById('btnSaveMedical');
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch('<?= BASE_URL ?>doctor/api/patient-medical-info-save.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            patient_id: PATIENT_ID,
            allergies: document.getElementById('medAllergies').value,
            existing_condition: document.getElementById('medExistingCondition').value,
            current_medication: document.getElementById('medCurrentMedication').value,
            medical_history: document.getElementById('medHistory').value
          })
        })
          .then(r => r.json())
          .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (!data.success) { showField('medicalError', data.error || 'Failed to save'); return; }
            showField('medicalSuccess', 'Medical information saved successfully.');
          })
          .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showField('medicalError', 'Network error: ' + err.message);
          });
      }

      // ── Health Card / Demographics ──
      function saveDemographics() {
        hideField('demoError');
        hideField('demoSuccess');

        const genderEl = document.querySelector('input[name="demoGender"]:checked');
        const bgEl = document.querySelector('input[name="demoBloodGroup"]:checked');

        const btn = document.getElementById('btnSaveDemo');
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch('<?= BASE_URL ?>doctor/api/patient-demographics-save.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            patient_id: PATIENT_ID,
            name: document.getElementById('demoName').value,
            gender: genderEl ? genderEl.value : '',
            blood_group: bgEl ? bgEl.value : '',
            email: document.getElementById('demoEmail').value,
            mobile: document.getElementById('demoPhone').value
          })
        })
          .then(r => r.json())
          .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (!data.success) { showField('demoError', data.error || 'Failed to save'); return; }
            showField('demoSuccess', 'Profile saved successfully.');
          })
          .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showField('demoError', 'Network error: ' + err.message);
          });
      }

      // ── Document sort (client-side, by upload date) ──
      let docSortDesc = true;
      function toggleDocSort() {
        docSortDesc = !docSortDesc;
        const list = document.getElementById('docsList');
        const items = [...list.querySelectorAll('.doc-item')];
        items.sort((a, b) => {
          const da = new Date(a.dataset.uploaded || 0).getTime();
          const db = new Date(b.dataset.uploaded || 0).getTime();
          return docSortDesc ? db - da : da - db;
        });
        items.forEach(el => list.appendChild(el));
        document.getElementById('sortIcon').className = docSortDesc ? 'fa fa-sort-amount-desc' : 'fa fa-sort-amount-asc';
      }

      // ── Per-document action row ──
      function toggleDocActions(btn) {
        const row = btn.closest('.doc-item').querySelector('.doc-actions-row');
        document.querySelectorAll('.doc-actions-row.open').forEach(r => { if (r !== row) r.classList.remove('open'); });
        row.classList.toggle('open');
      }

      function docFilePath(item) {
        const link = item.querySelector('.doc-view-link');
        return link ? link.getAttribute('href') : '';
      }

      // ── Modal helpers ──
      function openModal(id) { document.getElementById(id).classList.add('show'); }
      function closeModal(id) { document.getElementById(id).classList.remove('show'); }
      document.querySelectorAll('.cm-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('show'); });
      });

      // ── Edit document ──
      let editingDocId = null;
      function openEditDocModal(btn) {
        const item = btn.closest('.doc-item');
        editingDocId = item.dataset.docId;
        document.getElementById('editDocName').value = item.querySelector('.doc-name').textContent.trim();
        document.getElementById('editDocDescription').value = item.querySelector('.doc-desc').textContent.trim();
        hideField('editDocError');
        openModal('editDocModal');
      }

      function saveDocEdit() {
        hideField('editDocError');
        const name = document.getElementById('editDocName').value.trim();
        const description = document.getElementById('editDocDescription').value.trim();
        if (!name) { showField('editDocError', 'Document name is required.'); return; }

        const btn = document.getElementById('btnSaveDocEdit');
        btn.disabled = true;

        fetch('<?= BASE_URL ?>doctor/api/patient-document-update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ doc_id: editingDocId, document_name: name, description: description })
        })
          .then(r => r.json())
          .then(data => {
            btn.disabled = false;
            if (!data.success) { showField('editDocError', data.error || 'Failed to save'); return; }

            const item = document.querySelector(`.doc-item[data-doc-id="${editingDocId}"]`);
            if (item) {
              item.querySelector('.doc-name').textContent = name;
              const descEl = item.querySelector('.doc-desc');
              descEl.textContent = description;
              descEl.style.display = description ? '' : 'none';
            }
            closeModal('editDocModal');
          })
          .catch(err => {
            btn.disabled = false;
            showField('editDocError', 'Network error: ' + err.message);
          });
      }

      // ── Share document via email ──
      let sharingDocId = null;
      function openShareDocModal(btn) {
        const item = btn.closest('.doc-item');
        sharingDocId = item.dataset.docId;
        hideField('shareDocError');
        hideField('shareDocSuccess');
        openModal('shareDocModal');
      }

      function sendDocShare() {
        hideField('shareDocError');
        hideField('shareDocSuccess');
        const email = document.getElementById('shareDocEmail').value.trim();

        const btn = document.getElementById('btnSendShare');
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch('<?= BASE_URL ?>doctor/api/patient-document-share-email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ doc_id: sharingDocId, recipient_email: email })
        })
          .then(r => r.json())
          .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (!data.success) { showField('shareDocError', data.error || 'Failed to send'); return; }
            showField('shareDocSuccess', data.message || 'Sent successfully.');
          })
          .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showField('shareDocError', 'Network error: ' + err.message);
          });
      }

      // ── Copy shareable link ──
      function copyDocLink(btn) {
        const item = btn.closest('.doc-item');
        const url = docFilePath(item);
        if (!url) return;
        const absoluteUrl = new URL(url, window.location.origin).href;

        navigator.clipboard.writeText(absoluteUrl).then(() => {
          const original = btn.innerHTML;
          btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
          setTimeout(() => { btn.innerHTML = original; }, 1500);
        }).catch(() => {
          window.prompt('Copy this link:', absoluteUrl);
        });
      }

      // ── Remove document ──
      function removeDocument(btn) {
        if (!confirm('Remove this document? This cannot be undone.')) return;

        const item = btn.closest('.doc-item');
        const docId = item.dataset.docId;

        fetch('<?= BASE_URL ?>doctor/api/patient-document-delete.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ doc_id: docId })
        })
          .then(r => r.json())
          .then(data => {
            if (!data.success) { alert(data.error || 'Failed to remove document'); return; }

            item.remove();
            const countLabel = document.getElementById('docCountLabel');
            const n = document.querySelectorAll('#docsList .doc-item').length;
            countLabel.textContent = n + ' document(s)';

            if (n === 0) {
              document.getElementById('docsList').innerHTML = `
                <div class="info-section text-center py-4" id="noDocsMsg">
                  <i class="fa fa-folder-open-o fa-2x text-muted mb-2"></i>
                  <div class="text-muted" style="font-size:.86rem;">No documents uploaded yet.</div>
                </div>
              `;
            }
          })
          .catch(err => alert('Network error: ' + err.message));
      }
    </script>
</body>

</html>