<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

require_once(__DIR__ . "/auth/guard.php");
$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

// Ensure doctor_patients table exists (graceful fallback if migration not yet run)
$conn->query("
    CREATE TABLE IF NOT EXISTS `doctor_patients` (
      `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `doctor_id`    INT UNSIGNED NOT NULL,
      `patient_id`   INT UNSIGNED NOT NULL,
      `added_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `added_via`    ENUM('appointment','manual','abha') NOT NULL DEFAULT 'manual',
      `abha_fetched` TINYINT(1)   NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_link` (`doctor_id`,`patient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Auto-import existing appointment patients into doctor_patients
$conn->query("
    INSERT IGNORE INTO doctor_patients (doctor_id, patient_id, added_via)
    SELECT DISTINCT a.doctor_id, a.user_id, 'appointment'
    FROM appointments a
    WHERE a.doctor_id = $doctor_id
");

// Search / filter
$search = trim($_GET['search'] ?? '');

// Fetch all patients linked to this doctor
$sql = "
    SELECT DISTINCT
        u.id            AS patient_id,
        u.name          AS first_name,
        u.last_name,
        u.email,
        u.mobile,
        u.profile_pic,
        u.gender,
        u.blood_group,
        u.dob,
        u.abha_address,
        u.abha_number,
        u.abha_linked,
        u.abha_verified,
        dp.added_via,
        dp.added_at,
        (SELECT COUNT(*) FROM appointments a
         WHERE a.user_id = u.id AND a.doctor_id = ?) AS total_appointments,
        (SELECT MAX(a2.appointment_date) FROM appointments a2
         WHERE a2.user_id = u.id AND a2.doctor_id = ?) AS last_appointment,
        (SELECT a3.status FROM appointments a3
         WHERE a3.user_id = u.id AND a3.doctor_id = ?
         ORDER BY a3.appointment_date DESC LIMIT 1) AS last_status
    FROM users u
    INNER JOIN doctor_patients dp ON dp.patient_id = u.id AND dp.doctor_id = ?
";
$types  = 'iiii';
$params = [$doctor_id, $doctor_id, $doctor_id, $doctor_id];

if ($search !== '') {
    $sql   .= " WHERE (u.name LIKE ? OR u.last_name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR u.abha_address LIKE ?)";
    $like   = "%$search%";
    $types .= 'sssss';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
$sql .= " ORDER BY dp.added_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = count($patients);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Patient List — REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    /* ── Patient List Page ───────────────────────────────────── */
    .pl-header {
      background: linear-gradient(135deg, #0c74c5 0%, #0a5fa8 100%);
      color: #fff;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-radius: 0 0 18px 18px;
      margin-bottom: 16px;
    }
    .pl-header-title { font-size: 18px; font-weight: 700; flex: 1; }
    .pl-count-badge {
      background: rgba(255,255,255,0.25);
      border: 2px solid #fff;
      color: #fff;
      font-weight: 700;
      font-size: 14px;
      width: 38px; height: 38px;
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
    }

    /* Search bar row */
    .pl-search-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 4px 14px;
    }
    .pl-search-wrap {
      flex: 1;
      position: relative;
    }
    .pl-search-wrap input {
      width: 100%;
      border: 1.5px solid #cdd8e8;
      border-radius: 10px;
      padding: 10px 40px 10px 14px;
      font-size: 14px;
      outline: none;
      background: #f5f8fd;
    }
    .pl-search-wrap input:focus { border-color: #0c74c5; background: #fff; }
    .pl-search-wrap .search-icon {
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%);
      color: #888; font-size: 16px; cursor: pointer;
    }
    .pl-btn-add {
      width: 42px; height: 42px; border-radius: 50%;
      background: #e74c3c; color: #fff;
      border: none; font-size: 22px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(231,76,60,.35);
    }
    .pl-btn-refresh {
      width: 38px; height: 38px; border-radius: 50%;
      background: transparent; color: #0c74c5;
      border: 2px solid #0c74c5; font-size: 16px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; flex-shrink: 0;
      text-decoration: none;
    }
    .pl-btn-refresh:hover { background: #0c74c5; color: #fff; }

    /* Patient card */
    .patient-card {
      background: #dce8f5;
      border-radius: 10px;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-left: 4px solid #e91e63;
      position: relative;
      transition: box-shadow .15s;
    }
    .patient-card:hover { box-shadow: 0 4px 14px rgba(12,116,197,.18); }
    .patient-avatar {
      width: 50px; height: 50px; border-radius: 50%;
      background: #7b8ea8;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; overflow: hidden;
    }
    .patient-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .patient-avatar .fa { font-size: 26px; color: #fff; }
    .patient-info { flex: 1; min-width: 0; }
    .patient-name {
      font-size: 15px; font-weight: 700; color: #1a2e44;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .patient-meta { font-size: 12.5px; color: #555; margin-top: 2px; line-height: 1.5; }
    .patient-abha { font-size: 12px; color: #0c74c5; margin-top: 1px; }
    .abha-badge {
      display: inline-block;
      background: #e8f5e9; color: #2e7d32;
      font-size: 11px; border-radius: 4px;
      padding: 1px 6px; margin-left: 4px;
    }
    .patient-menu-btn {
      background: none; border: none; cursor: pointer;
      color: #555; font-size: 20px; padding: 4px 6px;
      border-radius: 6px; flex-shrink: 0;
    }
    .patient-menu-btn:hover { background: rgba(0,0,0,.07); }

    .patient-dropdown {
      position: absolute; right: 14px; top: 54px;
      background: #fff; border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,.15);
      min-width: 160px; z-index: 1000;
      display: none;
    }
    .patient-dropdown.show { display: block; }
    .patient-dropdown a {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 16px; color: #333; text-decoration: none;
      font-size: 14px;
    }
    .patient-dropdown a:hover { background: #f0f6ff; }
    .patient-dropdown a.text-danger { color: #dc3545 !important; }
    .patient-dropdown hr { margin: 4px 0; border-color: #eee; }

    .pl-empty {
      text-align: center; padding: 60px 20px; color: #888;
    }
    .pl-empty .fa { font-size: 48px; color: #cdd8e8; margin-bottom: 12px; }

    /* ── Add Patient Modal ──────────────────────────────────── */
    .modal-header-blue {
      background: linear-gradient(135deg, #0c74c5 0%, #0a5fa8 100%);
      color: #fff; border-radius: 12px 12px 0 0;
      padding: 16px 20px;
    }
    .modal-header-blue .btn-close { filter: brightness(0) invert(1); }
    .search-result-card {
      border: 1.5px solid #cdd8e8; border-radius: 10px;
      padding: 12px; margin-top: 10px;
      display: flex; align-items: center; gap: 12px;
      background: #f5f8fd; cursor: pointer;
      transition: border-color .15s;
    }
    .search-result-card:hover, .search-result-card.selected {
      border-color: #0c74c5; background: #e8f2ff;
    }
    .search-result-card .sr-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: #7b8ea8; display: flex; align-items: center;
      justify-content: center; flex-shrink: 0;
    }
    .search-result-card .sr-avatar .fa { color: #fff; font-size: 22px; }
    .step-indicator {
      display: flex; gap: 8px; margin-bottom: 16px;
    }
    .step-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: #cdd8e8;
    }
    .step-dot.active { background: #0c74c5; }
  </style>
</head>
<body>
<?php $sidebar_active = 'patients'; include(__DIR__ . "/inc/sidebar.php"); ?>

<div class="doctor-content" style="min-height:100vh; padding:0 0 60px;">
  <div style="max-width:680px; margin:0 auto; padding:0 4px;">

    <!-- Blue header bar -->
    <div class="pl-header">
      <span class="pl-header-title">Patient List</span>
      <div class="pl-count-badge"><?= $total ?></div>
    </div>

    <!-- Search + Add + Refresh -->
    <div class="pl-search-row">
      <div class="pl-search-wrap">
        <form method="GET" action="" id="searchForm">
          <input type="text" name="search" id="searchInput"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search by name, mobile, ABHA…"
                 oninput="debounceSearch(this.value)">
          <span class="search-icon" onclick="document.getElementById('searchForm').submit()">
            <i class="fa fa-search"></i>
          </span>
        </form>
      </div>
      <button class="pl-btn-add" onclick="openAddModal()" title="Add Patient">
        <i class="fa fa-plus"></i>
      </button>
      <a href="my-patients.php" class="pl-btn-refresh" title="Refresh">
        <i class="fa fa-refresh"></i>
      </a>
    </div>

    <!-- Patient Cards -->
    <?php if ($total === 0): ?>
      <div class="pl-empty">
        <div><i class="fa fa-users"></i></div>
        <h5>No patients yet</h5>
        <p>Tap <strong>+</strong> to add a patient by mobile, email, or ABHA ID.</p>
      </div>
    <?php else: ?>
      <div id="patientList">
        <?php foreach ($patients as $p):
          $full_name  = trim(htmlspecialchars($p['first_name'] . ' ' . $p['last_name']));
          $mobile     = htmlspecialchars($p['mobile'] ?? '');
          $email      = htmlspecialchars($p['email'] ?? '');
          $abha_addr  = htmlspecialchars($p['abha_address'] ?? '');
          $pid        = (int)$p['patient_id'];
          $has_pic    = !empty($p['profile_pic']);
        ?>
        <div class="patient-card" id="pc-<?= $pid ?>">
          <div class="patient-avatar">
            <?php if ($has_pic): ?>
              <img src="<?= BASE_URL . htmlspecialchars($p['profile_pic']) ?>" alt="">
            <?php else: ?>
              <i class="fa fa-user"></i>
            <?php endif; ?>
          </div>
          <div class="patient-info">
            <div class="patient-name"><?= $full_name ?></div>
            <div class="patient-meta">
              <?= $mobile ?>
              <?php if ($email): ?>
                <br><?= $email ?>
              <?php endif; ?>
            </div>
            <?php if ($abha_addr): ?>
              <div class="patient-abha">
                ABHA: <?= $abha_addr ?>
                <?php if ($p['abha_verified']): ?>
                  <span class="abha-badge"><i class="fa fa-check"></i> Verified</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="patient-abha" style="color:#aaa;">ABHA: not linked</div>
            <?php endif; ?>
          </div>
          <button class="patient-menu-btn" onclick="toggleCardMenu(<?= $pid ?>, event)" title="Options">
            <i class="fa fa-bars"></i>
          </button>
          <div class="patient-dropdown" id="dd-<?= $pid ?>">
            <a href="patient-details.php?id=<?= $pid ?>">
              <i class="fa fa-eye text-primary"></i> View Details
            </a>
            <a href="appointments.php?patient_id=<?= $pid ?>">
              <i class="fa fa-calendar text-success"></i> Appointments
            </a>
            <a href="patient-documents.php?patient_id=<?= $pid ?>">
              <i class="fa fa-file-text-o text-info"></i> Documents
            </a>
            <hr>
            <a href="#" class="text-danger" onclick="removePatient(<?= $pid ?>); return false;">
              <i class="fa fa-times"></i> Remove
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── Add Patient Modal ─────────────────────────────────────── -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px; overflow:hidden; border:none;">
      <div class="modal-header-blue d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="fa fa-user-plus me-2"></i> Add Patient</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">

        <!-- Step 1: Search -->
        <div id="step1">
          <div class="step-indicator mb-3">
            <div class="step-dot active"></div>
            <div class="step-dot" id="dot2"></div>
          </div>
          <p class="text-muted mb-3" style="font-size:13.5px;">
            Enter the patient's <strong>mobile number, email, or ABHA address</strong>.<br>
            If they're already on the portal, we'll find them instantly.
          </p>
          <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa fa-search"></i></span>
            <input type="text" class="form-control" id="addSearchInput"
                   placeholder="Mobile / Email / ABHA address"
                   oninput="debouncePortalSearch(this.value)">
          </div>
          <div id="portalSearchResults"></div>
          <div id="notFoundHint" style="display:none;" class="mt-3">
            <div class="alert alert-warning py-2 mb-2" style="font-size:13px;">
              <i class="fa fa-info-circle"></i>
              No patient found on portal. You can add them via their <strong>ABHA ID or address</strong>.
            </div>
            <button class="btn btn-outline-primary w-100" onclick="showAbhaStep()">
              <i class="fa fa-id-card me-1"></i> Add via ABHA ID
            </button>
          </div>
        </div>

        <!-- Step 2: ABHA entry (shown when not in portal) -->
        <div id="step2" style="display:none;">
          <div class="step-indicator mb-3">
            <div class="step-dot"></div>
            <div class="step-dot active"></div>
          </div>
          <button class="btn btn-sm btn-link ps-0 mb-3" onclick="backToStep1()">
            <i class="fa fa-arrow-left"></i> Back
          </button>
          <p class="text-muted mb-3" style="font-size:13.5px;">
            Enter the patient's <strong>ABHA number</strong> (14-digit) or
            <strong>ABHA address</strong> (e.g. <code>name@abdm</code>).
            Their medical history will be fetched via ABDM.
          </p>
          <div class="mb-3">
            <label class="form-label fw-semibold">ABHA Number / Address</label>
            <input type="text" class="form-control" id="abhaInput"
                   placeholder="e.g. 91-1234-5678-9012 or name@abdm">
            <div class="form-text">ABHA number format: XX-XXXX-XXXX-XXXX</div>
          </div>
          <div id="abhaMsg"></div>
          <button class="btn btn-primary w-100 mt-2" onclick="addViaAbha()">
            <i class="fa fa-user-plus me-1"></i> Fetch &amp; Add Patient
          </button>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Confirm add portal patient -->
<div class="modal fade" id="confirmAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:14px; border:none;">
      <div class="modal-body p-4 text-center">
        <div id="confirmAvatar" style="
          width:64px;height:64px;border-radius:50%;
          background:#7b8ea8;margin:0 auto 12px;
          display:flex;align-items:center;justify-content:center;">
          <i class="fa fa-user" style="color:#fff;font-size:30px;"></i>
        </div>
        <h6 id="confirmName" class="fw-bold mb-1"></h6>
        <p id="confirmMeta" class="text-muted mb-0" style="font-size:13px;"></p>
        <p id="confirmAbha" class="text-primary mb-3" style="font-size:13px;"></p>
        <div id="alreadyLinkedNote" class="alert alert-info py-2" style="font-size:13px; display:none;">
          <i class="fa fa-check-circle"></i> Already in your patient list.
        </div>
        <div class="d-flex gap-2 mt-2" id="confirmActions">
          <button class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary flex-fill" id="confirmAddBtn" onclick="confirmAddPortal()">
            <i class="fa fa-user-plus me-1"></i> Add
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include("../footer.php") ?>

<script>
let _selectedPortalPatient = null;
let _searchTimer = null;
let _portalTimer = null;

// ── Live page search (debounced) ──────────────────────────────
function debounceSearch(val) {
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(() => {
    document.getElementById('searchForm').submit();
  }, 600);
}

// ── Dropdown menus ────────────────────────────────────────────
function toggleCardMenu(pid, e) {
  e.stopPropagation();
  document.querySelectorAll('.patient-dropdown.show').forEach(d => {
    if (d.id !== 'dd-' + pid) d.classList.remove('show');
  });
  document.getElementById('dd-' + pid).classList.toggle('show');
}
document.addEventListener('click', () => {
  document.querySelectorAll('.patient-dropdown.show').forEach(d => d.classList.remove('show'));
});

// ── Remove patient (from doctor's list only) ──────────────────
function removePatient(pid) {
  if (!confirm('Remove this patient from your list? This will not delete their account.')) return;
  fetch('<?= BASE_URL ?>doctor/api/patient-remove.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({patient_id: pid})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const el = document.getElementById('pc-' + pid);
      el && el.remove();
    } else {
      alert(d.error || 'Failed to remove patient.');
    }
  });
}

// ── Add modal ─────────────────────────────────────────────────
function openAddModal() {
  resetAddModal();
  new bootstrap.Modal(document.getElementById('addPatientModal')).show();
}

function resetAddModal() {
  document.getElementById('step1').style.display = '';
  document.getElementById('step2').style.display = 'none';
  document.getElementById('addSearchInput').value = '';
  document.getElementById('portalSearchResults').innerHTML = '';
  document.getElementById('notFoundHint').style.display = 'none';
  document.getElementById('abhaInput').value = '';
  document.getElementById('abhaMsg').innerHTML = '';
  document.getElementById('dot2').classList.remove('active');
  _selectedPortalPatient = null;
}

// ── Portal search ─────────────────────────────────────────────
function debouncePortalSearch(val) {
  clearTimeout(_portalTimer);
  document.getElementById('notFoundHint').style.display = 'none';
  document.getElementById('portalSearchResults').innerHTML = '';
  if (val.length < 3) return;
  _portalTimer = setTimeout(() => portalSearch(val), 500);
}

function portalSearch(q) {
  const box = document.getElementById('portalSearchResults');
  box.innerHTML = '<div class="text-center text-muted py-2"><i class="fa fa-spinner fa-spin"></i></div>';

  fetch('<?= BASE_URL ?>doctor/api/patient-search.php?q=' + encodeURIComponent(q))
    .then(r => r.json())
    .then(data => {
      box.innerHTML = '';
      if (!data.success) {
        box.innerHTML = '<div class="text-danger small mt-1">' + (data.error || 'Error') + '</div>';
        return;
      }
      if (!data.found || data.patients.length === 0) {
        document.getElementById('notFoundHint').style.display = '';
        return;
      }
      data.patients.forEach(p => {
        const card = document.createElement('div');
        card.className = 'search-result-card';
        card.innerHTML = `
          <div class="sr-avatar"><i class="fa fa-user"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:14px;">${escHtml(p.name)}</div>
            <div style="font-size:12.5px;color:#555;">${escHtml(p.mobile || '')} ${p.email ? '· ' + escHtml(p.email) : ''}</div>
            ${p.abha_address ? '<div style="font-size:12px;color:#0c74c5;">ABHA: ' + escHtml(p.abha_address) + '</div>' : ''}
          </div>
          ${p.already_linked ? '<span class="badge bg-success">Added</span>' : ''}
        `;
        card.onclick = () => showConfirmAdd(p);
        box.appendChild(card);
      });
    })
    .catch(() => { box.innerHTML = '<div class="text-danger small">Search failed.</div>'; });
}

function showConfirmAdd(p) {
  _selectedPortalPatient = p;
  document.getElementById('confirmName').textContent = p.name;
  document.getElementById('confirmMeta').textContent =
    [p.mobile, p.gender, p.blood_group].filter(Boolean).join(' · ');
  document.getElementById('confirmAbha').textContent = p.abha_address ? 'ABHA: ' + p.abha_address : '';

  const note = document.getElementById('alreadyLinkedNote');
  const btn  = document.getElementById('confirmAddBtn');
  if (p.already_linked) {
    note.style.display = '';
    btn.style.display = 'none';
  } else {
    note.style.display = 'none';
    btn.style.display = '';
  }

  bootstrap.Modal.getInstance(document.getElementById('addPatientModal'))?.hide();
  new bootstrap.Modal(document.getElementById('confirmAddModal')).show();
}

function confirmAddPortal() {
  if (!_selectedPortalPatient) return;
  const btn = document.getElementById('confirmAddBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding…';

  fetch('<?= BASE_URL ?>doctor/api/patient-add.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({mode: 'portal', patient_id: _selectedPortalPatient.id})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      bootstrap.Modal.getInstance(document.getElementById('confirmAddModal'))?.hide();
      window.location.reload();
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-user-plus me-1"></i> Add';
      alert(d.error || 'Failed to add patient.');
    }
  });
}

// ── ABHA step ─────────────────────────────────────────────────
function showAbhaStep() {
  document.getElementById('step1').style.display = 'none';
  document.getElementById('step2').style.display = '';
  document.getElementById('dot2').classList.add('active');
}

function backToStep1() {
  document.getElementById('step2').style.display = 'none';
  document.getElementById('step1').style.display = '';
  document.getElementById('dot2').classList.remove('active');
}

function addViaAbha() {
  const abha  = document.getElementById('abhaInput').value.trim();
  const msgEl = document.getElementById('abhaMsg');

  if (!abha) {
    msgEl.innerHTML = '<div class="alert alert-danger py-2">Please enter ABHA number or address.</div>';
    return;
  }

  msgEl.innerHTML = '<div class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Searching ABDM…</div>';

  fetch('<?= BASE_URL ?>doctor/api/patient-add.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({mode: 'abha', abha: abha})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const src = d.source === 'portal' ? 'Found on portal' :
                  d.source === 'abha_new' ? 'Added via ABHA — will sync when patient registers' : 'Added';
      msgEl.innerHTML = `<div class="alert alert-success py-2">
        <i class="fa fa-check-circle"></i> <strong>${escHtml(d.name)}</strong> added!<br>
        <small class="text-muted">${src}${d.note ? '<br>' + escHtml(d.note) : ''}</small>
      </div>`;
      setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('addPatientModal'))?.hide();
        window.location.reload();
      }, 1800);
    } else {
      msgEl.innerHTML = '<div class="alert alert-danger py-2"><i class="fa fa-times-circle"></i> ' +
        escHtml(d.error || 'Failed to add patient.') + '</div>';
    }
  })
  .catch(() => {
    msgEl.innerHTML = '<div class="alert alert-danger py-2">Network error. Please try again.</div>';
  });
}

function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
