<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

require_once(__DIR__ . "/auth/guard.php");
$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

// Ensure doctor_patients table exists
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

// Auto-import existing appointment patients
$conn->query("
    INSERT IGNORE INTO doctor_patients (doctor_id, patient_id, added_via)
    SELECT DISTINCT a.doctor_id, a.user_id, 'appointment'
    FROM appointments a
    WHERE a.doctor_id = $doctor_id
");

$search = trim($_GET['search'] ?? '');

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
        u.abha_id,
        u.abha_linked,
        u.abha_verified,
        dp.added_via,
        dp.added_at,
        (SELECT COUNT(*) FROM appointments a  WHERE a.user_id = u.id AND a.doctor_id = ?) AS total_appointments,
        (SELECT MAX(a2.appointment_date) FROM appointments a2 WHERE a2.user_id = u.id AND a2.doctor_id = ?) AS last_appointment,
        (SELECT a3.status FROM appointments a3 WHERE a3.user_id = u.id AND a3.doctor_id = ? ORDER BY a3.appointment_date DESC LIMIT 1) AS last_status
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
  <title>My Patients — REJUVENATE Doctor Portal</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <style>
    .pat-avatar-sm {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: #7b8ea8;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      color: #fff;
      flex-shrink: 0;
      overflow: hidden;
    }

    .pat-avatar-sm img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .abha-pill {
      display: inline-flex;
      align-items: center;
      background: #e0f2fe;
      color: #0277bd;
      font-size: .7rem;
      border-radius: 20px;
      padding: 2px 8px;
      font-weight: 600;
    }

    .abha-pill.verified {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .abha-pill.none {
      background: #f3f4f6;
      color: #9ca3af;
    }

    .via-pill {
      font-size: .68rem;
      border-radius: 10px;
      padding: 2px 7px;
      font-weight: 600;
      background: #f3e5f5;
      color: #6a1b9a;
    }

    .via-pill.appointment {
      background: #e0f2fe;
      color: #0277bd;
    }

    .via-pill.abha {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .patient-row:hover {
      background: #f0f6ff;
    }

    /* Modal header */
    .modal-hdr {
      background: var(--primary, #0c74c5);
      color: #fff;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .modal-hdr .close-btn {
      background: rgba(255, 255, 255, .2);
      border: none;
      color: #fff;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      font-size: 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .modal-hdr .close-btn:hover {
      background: rgba(255, 255, 255, .35);
    }

    .search-result-card {
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px 14px;
      margin-top: 8px;
      display: flex;
      align-items: center;
      gap: 10px;
      background: #f9fafb;
      cursor: pointer;
      transition: .15s;
    }

    .search-result-card:hover {
      border-color: #0c74c5;
      background: #eaf4fd;
    }

    .step-dots {
      display: flex;
      gap: 6px;
      margin-bottom: 14px;
    }

    .step-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #d1d5db;
    }

    .step-dot.active {
      background: #0c74c5;
    }
  </style>
</head>

<body>
  <?php $sidebar_active = 'patients';
  include(__DIR__ . "/inc/sidebar.php"); ?>

  <main class="doctor-content">

  <!-- ── Page header row ── -->
  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
    <div>
      <p class="section-title" style="margin:0;">Patient List</p>
      <span style="font-size:.78rem; color:#6b7280;"><?= $total ?> patient<?= $total !== 1 ? 's' : '' ?> linked to your profile</span>
    </div>
    <a href="<?= BASE_URL ?>doctor/add-patient.php" class="btn btn-primary" style="display:flex;align-items:center;gap:6px;">
      <i class="fa fa-user-plus"></i> Add Patient
    </a>
  </div>

    <!-- ── Search + filter bar ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
      <div class="card-body" style="padding:14px 16px;">
        <form method="GET" action="" id="searchForm" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <div style="position:relative;flex:1;min-width:220px;">
            <input type="text" name="search" id="searchInput"
              class="form-control" style="padding-left:36px;"
              value="<?= htmlspecialchars($search) ?>"
              placeholder="Search name, mobile, email, ABHA…"
              oninput="debounceSearch(this.value)">
            <i class="fa fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;"></i>
          </div>
          <?php if ($search): ?>
            <a href="my-patients.php" class="btn btn-outline-secondary btn-sm">
              <i class="fa fa-times"></i> Clear
            </a>
          <?php endif; ?>
          <a href="my-patients.php" class="btn btn-outline-primary btn-sm" title="Refresh">
            <i class="fa fa-refresh"></i>
          </a>
        </form>
      </div>
    </div>

    <!-- ── Patient table ── -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
          <i class="fa fa-users me-2" style="color:var(--primary,#0c74c5);"></i>
          Patients
          <span style="background:#e0f2fe;color:#0277bd;border-radius:20px;padding:1px 10px;font-size:.72rem;font-weight:700;margin-left:6px;"><?= $total ?></span>
        </h6>
      </div>
      <div class="card-body p-0">

        <?php if ($total === 0): ?>
          <div style="text-align:center;padding:60px 20px;color:#6b7280;">
            <i class="fa fa-user-md" style="font-size:3rem;color:#d1d5db;display:block;margin-bottom:12px;"></i>
            <h6 style="font-weight:600;">No patients yet</h6>
            <p style="font-size:.84rem;">Click <strong>Add Patient</strong> to add by mobile, email, or ABHA ID.</p>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
              <i class="fa fa-user-plus me-1"></i> Add Patient
            </button>
          </div>

        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="patientTable">
              <thead class="table-light">
                <tr>
                  <th style="font-size:.73rem;width:38px;">#</th>
                  <th style="font-size:.73rem;">Patient</th>
                  <th style="font-size:.73rem;" class="d-none d-md-table-cell">Contact</th>
                  <th style="font-size:.73rem;" class="d-none d-sm-table-cell">ABHA</th>
                  <th style="font-size:.73rem;" class="d-none d-lg-table-cell">Last Visit</th>
                  <th style="font-size:.73rem;" class="d-none d-lg-table-cell">Visits</th>
                  <th style="font-size:.73rem;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($patients as $i => $p):
                  $full_name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                  $pid       = (int)$p['patient_id'];
                  $has_pic   = !empty($p['profile_pic']);
                  $age       = !empty($p['dob']) ? date_diff(date_create($p['dob']), date_create('today'))->y : null;
                ?>
                  <tr class="patient-row" id="pr-<?= $pid ?>">
                    <!-- # -->
                    <td style="font-size:.78rem;color:#9ca3af;"><?= $i + 1 ?></td>

                    <!-- Patient name + avatar -->
                    <td>
                      <div style="display:flex;align-items:center;gap:10px;">
                        <div class="pat-avatar-sm">
                          <?php if ($has_pic): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($p['profile_pic']) ?>" alt="">
                          <?php else: ?>
                            <i class="fa fa-user"></i>
                          <?php endif; ?>
                        </div>
                        <div>
                          <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($full_name) ?></div>
                          <div style="font-size:.72rem;color:#6b7280;">
                            <?= $p['gender'] ? htmlspecialchars($p['gender']) : '' ?>
                            <?= $age ? ' · ' . $age . ' yrs' : '' ?>
                            <?= $p['blood_group'] ? ' · ' . htmlspecialchars($p['blood_group']) : '' ?>
                          </div>
                          <!-- Show contact on mobile -->
                          <div class="d-md-none" style="font-size:.72rem;color:#6b7280;margin-top:2px;">
                            <?= htmlspecialchars($p['mobile'] ?? '') ?>
                          </div>
                        </div>
                      </div>
                    </td>

                    <!-- Contact -->
                    <td class="d-none d-md-table-cell" style="font-size:.82rem;">
                      <?php if ($p['mobile']): ?>
                        <div><?= htmlspecialchars($p['mobile']) ?></div>
                      <?php endif; ?>
                      <?php if ($p['email']): ?>
                        <div style="font-size:.72rem;color:#6b7280;"><?= htmlspecialchars($p['email']) ?></div>
                      <?php endif; ?>
                    </td>

                    <!-- ABHA -->
                    <td class="d-none d-sm-table-cell">
                      <?php if ($p['abha_address']): ?>
                        <span class="abha-pill <?= $p['abha_verified'] ? 'verified' : '' ?>">
                          <?php if ($p['abha_verified']): ?>
                            <i class="fa fa-check-circle" style="margin-right:3px;"></i>
                          <?php endif; ?>
                          <?= htmlspecialchars($p['abha_address']) ?>
                        </span>
                      <?php elseif ($p['abha_id']): ?>
                        <span class="abha-pill"><?= htmlspecialchars($p['abha_id']) ?></span>
                      <?php else: ?>
                        <span class="abha-pill none">Not linked</span>
                      <?php endif; ?>
                    </td>

                    <!-- Last visit -->
                    <td class="d-none d-lg-table-cell" style="font-size:.82rem;">
                      <?php if ($p['last_appointment']): ?>
                        <div><?= date('d M Y', strtotime($p['last_appointment'])) ?></div>
                        <?php if ($p['last_status']): ?>
                          <span style="font-size:.68rem;padding:1px 7px;border-radius:10px;font-weight:600;
                      background:<?= $p['last_status'] === 'Completed' ? '#d4edda' : ($p['last_status'] === 'Pending' ? '#fff3cd' : '#f8d7da') ?>;
                      color:<?= $p['last_status'] === 'Completed' ? '#155724' : ($p['last_status'] === 'Pending' ? '#856404' : '#721c24') ?>;">
                            <?= htmlspecialchars($p['last_status']) ?>
                          </span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span style="color:#9ca3af;font-size:.78rem;">—</span>
                      <?php endif; ?>
                    </td>

                    <!-- Visits count -->
                    <td class="d-none d-lg-table-cell" style="font-size:.84rem;font-weight:600;color:#0c74c5;">
                      <?= (int)$p['total_appointments'] ?>
                    </td>

                    <!-- Actions -->
                    <td>
                      <div style="display:flex;gap:4px;align-items:center;">
                        <a href="patient-profile.php?id=<?= $pid ?>"
                          class="btn btn-sm" title="View"
                          style="background:#e0f2fe;color:#0277bd;border:none;width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;">
                          <i class="fa fa-eye" style="font-size:.78rem;"></i>
                        </a>
                        <a href="appointments.php?patient_id=<?= $pid ?>"
                          class="btn btn-sm" title="Appointments"
                          style="background:#e8f5e9;color:#2e7d32;border:none;width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;">
                          <i class="fa fa-calendar" style="font-size:.78rem;"></i>
                        </a>
                        <div style="position:relative;">
                          <button onclick="toggleMenu(<?= $pid ?>, event)"
                            style="background:#f3f4f6;border:none;width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;cursor:pointer;color:#6b7280;">
                            <i class="fa fa-ellipsis-v" style="font-size:.78rem;"></i>
                          </button>
                          <div id="menu-<?= $pid ?>" style="display:none;position:absolute;right:0;top:34px;background:#fff;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.15);min-width:150px;z-index:100;">
                            <a href="patient-documents.php?patient_id=<?= $pid ?>"
                              style="display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:.82rem;color:#374151;text-decoration:none;">
                              <i class="fa fa-file-text-o" style="color:#6b7280;width:14px;"></i> Documents
                            </a>
                            <a href="patient-profile.php?id=<?= $pid ?>"
                              style="display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:.82rem;color:#374151;text-decoration:none;">
                              <i class="fa fa-user" style="color:#6b7280;width:14px;"></i> Full Profile
                            </a>
                            <div style="border-top:1px solid #f3f4f6;"></div>
                            <a href="#" onclick="removePatient(<?= $pid ?>); return false;"
                              style="display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:.82rem;color:#dc3545;text-decoration:none;">
                              <i class="fa fa-times" style="width:14px;"></i> Remove
                            </a>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </main>

  <!-- ══════════════════════════════════════════
     ADD PATIENT MODAL
══════════════════════════════════════════ -->
  <div class="modal fade" id="addPatientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 12px 40px rgba(0,0,0,.18);">

        <!-- Header -->
        <div class="modal-hdr">
          <div style="display:flex;align-items:center;gap:10px;">
            <i class="fa fa-user-plus" style="font-size:1rem;"></i>
            <span style="font-weight:700;font-size:1rem;">Add Patient</span>
          </div>
          <button class="close-btn" data-dismiss="modal" data-bs-dismiss="modal">
            <i class="fa fa-times"></i>
          </button>
        </div>

        <div class="modal-body" style="padding:20px 24px;">

          <!-- STEP 1 — search -->
          <div id="step1">
            <div class="step-dots">
              <div class="step-dot active"></div>
              <div class="step-dot" id="dot2"></div>
            </div>
            <p style="font-size:.84rem;color:#6b7280;margin-bottom:12px;">
              Search by <strong>mobile, email, or ABHA address</strong>. If already on the portal, we'll find them instantly.
            </p>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
              <div style="position:relative;flex:1;">
                <input type="text" id="addSearchInput" class="form-control"
                  placeholder="Mobile / Email / ABHA address"
                  style="padding-left:34px;"
                  oninput="debouncePortalSearch(this.value)">
                <i class="fa fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;"></i>
              </div>
            </div>
            <div id="portalSearchResults"></div>
            <div id="notFoundHint" style="display:none;margin-top:12px;">
              <div class="alert alert-warning" style="font-size:.82rem;padding:10px 14px;border-radius:8px;">
                <i class="fa fa-info-circle"></i> No match on portal.
                You can add via ABHA ID instead.
              </div>
              <button class="btn btn-outline-primary w-100" onclick="showAbhaStep()">
                <i class="fa fa-id-card" style="margin-right:6px;"></i> Add via ABHA ID
              </button>
            </div>
          </div>

          <!-- STEP 2 — ABHA -->
          <div id="step2" style="display:none;">
            <div class="step-dots">
              <div class="step-dot"></div>
              <div class="step-dot active"></div>
            </div>
            <button class="btn btn-sm btn-link" style="padding:0;margin-bottom:12px;" onclick="backToStep1()">
              <i class="fa fa-arrow-left"></i> Back
            </button>
            <p style="font-size:.84rem;color:#6b7280;margin-bottom:12px;">
              Enter the patient's <strong>ABHA number</strong> (14-digit) or
              <strong>ABHA address</strong> (e.g. <code>name@abdm</code>).
              Their records will be fetched from ABDM.
            </p>
            <label style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px;">ABHA Number / Address</label>
            <input type="text" class="form-control" id="abhaInput"
              placeholder="91-1234-5678-9012  or  name@abdm"
              style="margin-bottom:6px;">
            <div style="font-size:.72rem;color:#9ca3af;margin-bottom:12px;">Format: XX-XXXX-XXXX-XXXX</div>
            <div id="abhaMsg"></div>
            <button class="btn btn-primary w-100" onclick="addViaAbha()">
              <i class="fa fa-user-plus" style="margin-right:6px;"></i> Fetch &amp; Add Patient
            </button>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
     CONFIRM ADD (portal patient)
══════════════════════════════════════════ -->
  <div class="modal fade" id="confirmAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:340px;">
      <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 12px 40px rgba(0,0,0,.18);">
        <div class="modal-body" style="padding:28px 24px;text-align:center;">
          <div style="width:64px;height:64px;border-radius:50%;background:#7b8ea8;margin:0 auto 12px;
                    display:flex;align-items:center;justify-content:center;">
            <i class="fa fa-user" style="color:#fff;font-size:28px;"></i>
          </div>
          <h6 id="confirmName" style="font-weight:700;margin-bottom:4px;"></h6>
          <p id="confirmMeta" style="color:#6b7280;font-size:.8rem;margin-bottom:2px;"></p>
          <p id="confirmAbha" style="color:#0c74c5;font-size:.8rem;margin-bottom:14px;"></p>
          <div id="alreadyLinkedNote" class="alert alert-info" style="font-size:.8rem;padding:8px 12px;display:none;border-radius:8px;">
            <i class="fa fa-check-circle"></i> Already in your patient list.
          </div>
          <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn btn-secondary flex-fill" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary flex-fill" id="confirmAddBtn" onclick="confirmAddPortal()">
              <i class="fa fa-user-plus" style="margin-right:5px;"></i> Add
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include("../footer.php") ?>

  <script>
    var _sel = null,
      _st = null,
      _pt = null;

    // ── Live search on page ──────────────────────────────────────
    function debounceSearch(v) {
      clearTimeout(_st);
      _st = setTimeout(function() {
        document.getElementById('searchForm').submit();
      }, 600);
    }

    // ── Row action menu ──────────────────────────────────────────
    function toggleMenu(pid, e) {
      e.stopPropagation();
      document.querySelectorAll('[id^="menu-"]').forEach(function(m) {
        if (m.id !== 'menu-' + pid) m.style.display = 'none';
      });
      var m = document.getElementById('menu-' + pid);
      m.style.display = m.style.display === 'block' ? 'none' : 'block';
    }
    document.addEventListener('click', function() {
      document.querySelectorAll('[id^="menu-"]').forEach(function(m) {
        m.style.display = 'none';
      });
    });

    // ── Remove patient ───────────────────────────────────────────
    function removePatient(pid) {
      if (!confirm('Remove this patient from your list?')) return;
      fetch('<?= BASE_URL ?>doctor/api/patient-remove.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          patient_id: pid
        })
      }).then(r => r.json()).then(function(d) {
        if (d.success) {
          var row = document.getElementById('pr-' + pid);
          if (row) row.remove();
        } else {
          alert(d.error || 'Failed.');
        }
      });
    }

    // ── Add modal ────────────────────────────────────────────────
    function openAddModal() {
      resetAddModal();
      var el = document.getElementById('addPatientModal');
      if (typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(el).show();
      } else {
        // Bootstrap 4
        $(el).modal('show');
      }
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
      _sel = null;
    }

    // ── Portal search ────────────────────────────────────────────
    function debouncePortalSearch(v) {
      clearTimeout(_pt);
      document.getElementById('notFoundHint').style.display = 'none';
      document.getElementById('portalSearchResults').innerHTML = '';
      if (v.length < 3) return;
      _pt = setTimeout(function() {
        portalSearch(v);
      }, 500);
    }

    function portalSearch(q) {
      var box = document.getElementById('portalSearchResults');
      box.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:10px;"><i class="fa fa-spinner fa-spin"></i></div>';
      fetch('<?= BASE_URL ?>doctor/api/patient-search.php?q=' + encodeURIComponent(q))
        .then(function(r) {
          return r.json();
        })
        .then(function(data) {
          box.innerHTML = '';
          if (!data.success) {
            box.innerHTML = '<div style="color:#dc3545;font-size:.8rem;">' + esc(data.error || 'Error') + '</div>';
            return;
          }
          if (!data.found || !data.patients.length) {
            document.getElementById('notFoundHint').style.display = '';
            return;
          }
          data.patients.forEach(function(p) {
            var card = document.createElement('div');
            card.className = 'search-result-card';
            card.innerHTML =
              '<div style="width:40px;height:40px;border-radius:50%;background:#7b8ea8;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
              '<i class="fa fa-user" style="color:#fff;font-size:18px;"></i>' +
              '</div>' +
              '<div style="flex:1;min-width:0;">' +
              '<div style="font-weight:700;font-size:.84rem;">' + esc(p.name) + '</div>' +
              '<div style="font-size:.72rem;color:#6b7280;">' + esc(p.mobile || '') + (p.email ? ' · ' + esc(p.email) : '') + '</div>' +
              (p.abha_address ? '<div style="font-size:.7rem;color:#0c74c5;">ABHA: ' + esc(p.abha_address) + '</div>' : '') +
              '</div>' +
              (p.already_linked ? '<span style="background:#d4edda;color:#155724;font-size:.68rem;border-radius:10px;padding:2px 8px;font-weight:600;">Added</span>' : '');
            card.onclick = function() {
              showConfirmAdd(p);
            };
            box.appendChild(card);
          });
        })
        .catch(function() {
          box.innerHTML = '<div style="color:#dc3545;font-size:.8rem;">Search failed.</div>';
        });
    }

    function showConfirmAdd(p) {
      _sel = p;
      document.getElementById('confirmName').textContent = p.name;
      document.getElementById('confirmMeta').textContent = [p.mobile, p.gender, p.blood_group].filter(Boolean).join(' · ');
      document.getElementById('confirmAbha').textContent = p.abha_address ? 'ABHA: ' + p.abha_address : '';
      var note = document.getElementById('alreadyLinkedNote');
      var btn = document.getElementById('confirmAddBtn');
      note.style.display = p.already_linked ? '' : 'none';
      btn.style.display = p.already_linked ? 'none' : '';

      var addEl = document.getElementById('addPatientModal');
      var confEl = document.getElementById('confirmAddModal');
      if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getInstance(addEl) && bootstrap.Modal.getInstance(addEl).hide();
        new bootstrap.Modal(confEl).show();
      } else {
        $(addEl).modal('hide');
        $(confEl).modal('show');
      }
    }

    function confirmAddPortal() {
      if (!_sel) return;
      var btn = document.getElementById('confirmAddBtn');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding…';
      fetch('<?= BASE_URL ?>doctor/api/patient-add.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          mode: 'portal',
          patient_id: _sel.id
        })
      }).then(function(r) {
        return r.json();
      }).then(function(d) {
        if (d.success) {
          var confEl = document.getElementById('confirmAddModal');
          if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getInstance(confEl) && bootstrap.Modal.getInstance(confEl).hide();
          } else {
            $(confEl).modal('hide');
          }
          window.location.reload();
        } else {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-user-plus" style="margin-right:5px;"></i> Add';
          alert(d.error || 'Failed.');
        }
      });
    }

    // ── ABHA step ────────────────────────────────────────────────
    function showAbhaStep() {
      document.getElementById('step1').style.display = 'none';
      document.getElementById('step2').style.display = '';
      document.getElementById('dot2').classList.add('active');
    }

    function backToStep1() {
      document.getElementById('step2').style.display = 'none';
      document.getElementById('step1').style.display = '';
      document.getElementById('dot2').classList.remove('active');
      document.getElementById('abhaMsg').innerHTML = '';
    }

    function addViaAbha() {
      var abha = document.getElementById('abhaInput').value.trim();
      var msg = document.getElementById('abhaMsg');
      if (!abha) {
        msg.innerHTML = '<div class="alert alert-danger" style="font-size:.8rem;padding:8px 12px;border-radius:8px;">Please enter ABHA number or address.</div>';
        return;
      }
      msg.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:8px;"><i class="fa fa-spinner fa-spin"></i> Searching ABDM…</div>';
      fetch('<?= BASE_URL ?>doctor/api/patient-add.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          mode: 'abha',
          abha: abha
        })
      }).then(function(r) {
        return r.json();
      }).then(function(d) {
        if (d.success) {
          msg.innerHTML = '<div class="alert alert-success" style="font-size:.8rem;padding:8px 12px;border-radius:8px;"><i class="fa fa-check-circle"></i> <strong>' + esc(d.name) + '</strong> added!' + (d.note ? '<br><small>' + esc(d.note) + '</small>' : '') + '</div>';
          setTimeout(function() {
            var el = document.getElementById('addPatientModal');
            if (typeof bootstrap !== 'undefined') {
              bootstrap.Modal.getInstance(el) && bootstrap.Modal.getInstance(el).hide();
            } else {
              $(el).modal('hide');
            }
            window.location.reload();
          }, 1800);
        } else {
          msg.innerHTML = '<div class="alert alert-danger" style="font-size:.8rem;padding:8px 12px;border-radius:8px;"><i class="fa fa-times-circle"></i> ' + esc(d.error || 'Failed.') + '</div>';
        }
      }).catch(function() {
        msg.innerHTML = '<div class="alert alert-danger" style="font-size:.8rem;padding:8px 12px;border-radius:8px;">Network error. Try again.</div>';
      });
    }

    function esc(s) {
      if (!s) return '';
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
  </script>
</body>

</html>