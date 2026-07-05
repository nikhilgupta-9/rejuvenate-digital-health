<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload   = doctor_jwt_guard();
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

// Doctor's own HPR verification status
$d_stmt = $conn->prepare("SELECT hpr_id, nmc_reg_number, council_name, hpr_verified, hpr_verified_at FROM doctors WHERE id = ?");
$d_stmt->bind_param('i', $doctor_id);
$d_stmt->execute();
$doctor = $d_stmt->get_result()->fetch_assoc();

// Patients linked to this doctor whose ABHA is linked-but-unverified, or who have
// an active pending ABHA linking request.
$p_stmt = $conn->prepare("
    SELECT u.id, u.name, u.last_name, u.mobile, u.abha_id, u.abha_address,
           u.abha_linked, u.abha_verified, uar.status AS request_status, uar.requested_at
    FROM doctor_patients dp
    JOIN users u ON dp.patient_id = u.id
    LEFT JOIN user_abha_requests uar ON uar.user_id = u.id AND uar.status = 'Pending'
    WHERE dp.doctor_id = ?
      AND ((u.abha_linked = 1 AND u.abha_verified = 0) OR uar.id IS NOT NULL)
    ORDER BY u.name
");
$p_stmt->bind_param('i', $doctor_id);
$p_stmt->execute();
$pending_patients = $p_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sidebar_active = 'pending-uploads';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pending Uploads — Rejuvenate</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
<style>
.info-section{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);padding:18px 20px;margin-bottom:16px;}
.section-title{font-size:.85rem;font-weight:700;color:#374151;margin-bottom:12px;}
.section-title i{color:#0C74C5;margin-right:6px;}
.badge-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;}
.badge-ok{background:#dcfce7;color:#166534;}
.badge-pending{background:#fef3c7;color:#92400e;}
.hpr-card{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.hpr-detail{font-size:.84rem;color:#374151;}
.hpr-detail strong{color:#1f2937;}
.patient-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;
  border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;flex-wrap:wrap;}
.patient-name{font-weight:600;font-size:.9rem;color:#1f2937;}
.patient-meta{font-size:.76rem;color:#9ca3af;}
.empty-note{text-align:center;padding:28px 0;color:#9ca3af;font-size:.86rem;}
.empty-note i{font-size:1.8rem;display:block;margin-bottom:8px;opacity:.5;}
</style>
</head>
<body>
<main class="doctor-content">

  <div class="info-section">
    <div class="section-title"><i class="fa fa-id-card-o"></i> Your HPR Verification</div>
    <div class="hpr-card">
      <div class="hpr-detail">
        <?php if (!empty($doctor['hpr_id'])): ?>
          <div>HPR ID: <strong><?= htmlspecialchars($doctor['hpr_id']) ?></strong></div>
          <?php if (!empty($doctor['nmc_reg_number'])): ?>
            <div>NMC Reg. No: <strong><?= htmlspecialchars($doctor['nmc_reg_number']) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($doctor['council_name'])): ?>
            <div>Council: <strong><?= htmlspecialchars($doctor['council_name']) ?></strong></div>
          <?php endif; ?>
        <?php else: ?>
          <div>No HPR ID on file yet.</div>
        <?php endif; ?>
      </div>
      <div>
        <?php if ($doctor['hpr_verified']): ?>
          <span class="badge-pill badge-ok"><i class="fa fa-check-circle"></i> Verified<?= $doctor['hpr_verified_at'] ? ' on ' . date('d M Y', strtotime($doctor['hpr_verified_at'])) : '' ?></span>
        <?php else: ?>
          <span class="badge-pill badge-pending"><i class="fa fa-clock-o"></i> Verification Pending</span>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>doctor/my-contact.php" class="btn btn-sm btn-outline-primary ml-2">
          <i class="fa fa-edit mr-1"></i> <?= empty($doctor['hpr_id']) ? 'Add HPR Details' : 'Update HPR Details' ?>
        </a>
      </div>
    </div>
  </div>

  <div class="info-section">
    <div class="section-title"><i class="fa fa-heartbeat"></i> Patients — Pending ABHA Verification</div>
    <?php if (empty($pending_patients)): ?>
      <div class="empty-note"><i class="fa fa-check-circle"></i>No pending ABHA verifications. All linked patients are up to date.</div>
    <?php else: ?>
      <?php foreach ($pending_patients as $p): ?>
        <?php $full_name = trim(($p['name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: 'Unknown Patient'; ?>
        <div class="patient-row">
          <div>
            <div class="patient-name"><?= htmlspecialchars($full_name) ?></div>
            <div class="patient-meta">
              <?= htmlspecialchars($p['mobile'] ?? '—') ?>
              <?php if (!empty($p['abha_id'])): ?> &nbsp;·&nbsp; <span style="font-family:monospace;"><?= htmlspecialchars($p['abha_id']) ?></span><?php endif; ?>
            </div>
          </div>
          <div>
            <?php if ($p['request_status'] === 'Pending'): ?>
              <span class="badge-pill badge-pending"><i class="fa fa-clock-o"></i> Link Request Pending<?= $p['requested_at'] ? ' since ' . date('d M Y', strtotime($p['requested_at'])) : '' ?></span>
            <?php else: ?>
              <span class="badge-pill badge-pending"><i class="fa fa-clock-o"></i> Linked — Not Yet Verified</span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>doctor/patient-profile.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary ml-2">
              <i class="fa fa-eye mr-1"></i> View
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
