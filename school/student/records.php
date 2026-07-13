<?php
include_once "../../config/connect.php";
include_once "auth.php";

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Prescriptions written by doctors for this student (read-only)
$rx_stmt = $conn->prepare("SELECT p.*, d.name as doctor_name, d.specialization
    FROM school_member_prescriptions p
    LEFT JOIN doctors d ON d.id = p.doctor_id
    WHERE p.member_id = ? ORDER BY p.created_at DESC");
$rx_stmt->bind_param('i', $student_id);
$rx_stmt->execute();
$prescriptions = $rx_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Medical reports/documents uploaded by doctor or admin for this student (read-only)
$doc_stmt = $conn->prepare("SELECT sd.*, d.name as doctor_name, au.first_name as admin_first, au.last_name as admin_last
    FROM school_member_documents sd
    LEFT JOIN doctors d ON d.id = sd.uploaded_by_doctor_id
    LEFT JOIN admin_user au ON au.id = sd.uploaded_by_admin_id
    WHERE sd.member_id = ? ORDER BY sd.uploaded_at DESC");
$doc_stmt->bind_param('i', $student_id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$active_tab = ($_GET['tab'] ?? '') === 'reports' ? 'reports' : 'rx';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Records | <?= htmlspecialchars($student_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root { --primary: #0C74C5; --accent: #02c9b8; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }

    .s-topnav {
      background: var(--primary); color: #fff; padding: 0 16px; height: 58px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 10px rgba(12,116,197,.3);
    }
    .s-topnav .brand { font-size: .9rem; font-weight: 700; }
    .s-topnav .sub   { font-size: .68rem; opacity: .75; }

    .s-body { max-width: 700px; margin: 0 auto; padding: 18px 14px 90px; }

    /* Tab toggle */
    .rec-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
    .rec-tab {
      flex: 1; text-align: center; padding: 10px 12px; border-radius: 12px;
      background: #fff; box-shadow: 0 1px 6px rgba(0,0,0,.07);
      font-size: .82rem; font-weight: 600; color: #6b7280; cursor: pointer;
      text-decoration: none; display: block;
    }
    .rec-tab i { margin-right: 6px; }
    .rec-tab.active { background: var(--primary); color: #fff; box-shadow: 0 3px 10px rgba(12,116,197,.3); }

    .s-card { background: #fff; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; box-shadow: 0 1px 6px rgba(0,0,0,.07); }

    .rx-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; gap: 10px; }
    .rx-text {
      white-space: pre-wrap; font-size: .86rem; color: #374151;
      background: #f9fafb; border-radius: 8px; padding: 12px 14px; margin-top: 4px;
    }
    .rx-symbol { font-size: 1.3rem; font-weight: 700; color: var(--primary); margin-right: 4px; }

    .doc-row { display: flex; align-items: center; gap: 12px; }
    .doc-icon {
      width: 40px; height: 40px; border-radius: 10px; background: #e0f2fe; color: var(--primary);
      display: flex; align-items: center; justify-content: center; font-size: 1.05rem; flex-shrink: 0;
    }

    .empty-state { text-align: center; padding: 50px 20px; color: #9ca3af; }
    .empty-state i { font-size: 2.2rem; margin-bottom: 10px; display: block; opacity: .4; }

    .s-bottomnav {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #fff; border-top: 1px solid #e5e7eb;
      display: flex; z-index: 99;
      box-shadow: 0 -2px 10px rgba(0,0,0,.06);
    }
    .s-bottomnav a {
      flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 9px 4px; text-decoration: none; color: #9ca3af;
      font-size: .58rem; font-weight: 600; gap: 3px; transition: color .15s;
    }
    .s-bottomnav a i { font-size: 1.05rem; }
    .s-bottomnav a.active { color: var(--primary); }
  </style>
</head>
<body>

<nav class="s-topnav">
  <div>
    <div class="brand"><i class="fas fa-file-medical me-2" style="color:var(--accent);"></i>My Records</div>
    <div class="sub"><?= htmlspecialchars($student_school) ?></div>
  </div>
  <a href="logout.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:none;font-size:.76rem;">
    <i class="fas fa-sign-out-alt me-1"></i><span class="d-none d-sm-inline">Logout</span>
  </a>
</nav>

<div class="s-body">

  <div class="rec-tabs">
    <div class="rec-tab <?= $active_tab === 'rx' ? 'active' : '' ?>" onclick="showTab('rx')">
      <i class="fas fa-file-medical"></i>Prescriptions <span style="opacity:.75;">(<?= count($prescriptions) ?>)</span>
    </div>
    <div class="rec-tab <?= $active_tab === 'reports' ? 'active' : '' ?>" onclick="showTab('reports')">
      <i class="fas fa-file-alt"></i>Reports <span style="opacity:.75;">(<?= count($documents) ?>)</span>
    </div>
  </div>

  <!-- ── PRESCRIPTIONS ── -->
  <div id="pane-rx" style="<?= $active_tab === 'rx' ? '' : 'display:none;' ?>">
    <?php if (empty($prescriptions)): ?>
      <div class="s-card empty-state">
        <i class="fas fa-file-medical"></i>
        No prescriptions yet.<br>Your school doctor's prescriptions will appear here.
      </div>
    <?php else: ?>
      <?php foreach ($prescriptions as $rx): ?>
        <div class="s-card">
          <div class="rx-head">
            <div>
              <div style="font-weight:700;font-size:.92rem;color:#1f2937;"><?= htmlspecialchars($rx['diagnosis'] ?: 'General Prescription') ?></div>
              <div style="font-size:.72rem;color:#9ca3af;">
                Dr. <?= htmlspecialchars($rx['doctor_name'] ?? 'Unknown') ?><?= $rx['specialization'] ? ' · ' . htmlspecialchars($rx['specialization']) : '' ?>
                <br><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?>
              </div>
            </div>
            <?php if ($rx['follow_up_date']): ?>
              <span style="background:#fff7ed;color:#c2410c;border-radius:20px;padding:4px 10px;font-size:.66rem;font-weight:700;white-space:nowrap;">
                <i class="fas fa-calendar-check me-1"></i>Follow-up <?= date('d M', strtotime($rx['follow_up_date'])) ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if ($rx['symptoms']): ?>
            <div style="font-size:.78rem;color:#6b7280;margin-bottom:4px;"><strong>Symptoms:</strong> <?= htmlspecialchars($rx['symptoms']) ?></div>
          <?php endif; ?>
          <div class="rx-text"><span class="rx-symbol">℞</span><?= htmlspecialchars($rx['prescription_text']) ?></div>
          <?php if ($rx['advice']): ?>
            <div style="font-size:.78rem;color:#6b7280;margin-top:8px;"><strong>Advice:</strong> <?= htmlspecialchars($rx['advice']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ── MEDICAL REPORTS ── -->
  <div id="pane-reports" style="<?= $active_tab === 'reports' ? '' : 'display:none;' ?>">
    <?php if (empty($documents)): ?>
      <div class="s-card empty-state">
        <i class="fas fa-file-alt"></i>
        No medical reports yet.<br>Lab reports, X-rays and other documents will appear here.
      </div>
    <?php else: ?>
      <?php foreach ($documents as $doc):
        $uploader = $doc['doctor_name'] ? 'Dr. ' . $doc['doctor_name'] : (trim(($doc['admin_first'] ?? '') . ' ' . ($doc['admin_last'] ?? '')) ?: 'School Admin');
      ?>
        <div class="s-card doc-row">
          <div class="doc-icon"><i class="fas fa-file-alt"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:.86rem;color:#1f2937;"><?= htmlspecialchars($doc['document_name']) ?></div>
            <?php if ($doc['description']): ?>
              <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($doc['description']) ?></div>
            <?php endif; ?>
            <div style="font-size:.72rem;color:#9ca3af;">
              <?= date('d M Y', strtotime($doc['uploaded_at'])) ?> &middot; <?= htmlspecialchars($uploader) ?>
            </div>
          </div>
          <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank"
            class="btn btn-sm" style="background:#e0f2fe;color:var(--primary);border:none;flex-shrink:0;">
            <i class="fas fa-eye"></i>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div style="text-align:center;font-size:.7rem;color:#9ca3af;padding:10px 0;">
    <i class="fas fa-lock me-1"></i>Only your doctor and school admin can add records here.
  </div>

</div>

<!-- Bottom Navigation -->
<nav class="s-bottomnav">
  <a href="dashboard.php"><i class="fas fa-home"></i>Home</a>
  <a href="health.php"><i class="fas fa-heartbeat"></i>Health</a>
  <a href="records.php" class="active"><i class="fas fa-file-medical"></i>Records</a>
  <a href="abha.php"><i class="fas fa-id-card"></i>ABHA</a>
  <a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showTab(name) {
  document.getElementById('pane-rx').style.display = name === 'rx' ? '' : 'none';
  document.getElementById('pane-reports').style.display = name === 'reports' ? '' : 'none';
  document.querySelectorAll('.rec-tab').forEach(t => t.classList.remove('active'));
  event.currentTarget.classList.add('active');
  history.replaceState(null, '', 'records.php?tab=' + name);
}
</script>
</body>
</html>
