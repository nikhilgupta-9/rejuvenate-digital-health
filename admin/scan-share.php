<?php
/**
 * Admin → ABDM Milestone 3: Scan & Share Queue & Counter Standee
 * Manage real-time OPD check-ins and generate facility counter QR codes.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/abdm.php';
require_once dirname(__DIR__) . '/lib/Security.php';
admin_jwt_guard();

$status_filter = $_GET['status'] ?? 'all';
$date_filter   = $_GET['date'] ?? date('Y-m-d');

// Update token status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_token_status'])) {
    if (!isset($_POST['csrf_token']) || !Security::verifyCsrf($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid CSRF token.";
        header("Location: scan-share.php");
        exit();
    }

    $tokenId = (int)($_POST['token_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? 'waiting');
    $valid = ['waiting', 'in_consultation', 'completed', 'cancelled'];

    if (in_array($newStatus, $valid, true) && $tokenId > 0) {
        $stmt = $conn->prepare("UPDATE abdm_scan_share_tokens SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $tokenId);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Token status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . "!";
        }
        $stmt->close();
    }
    header("Location: scan-share.php?date=" . urlencode($date_filter));
    exit();
}

$facilityId   = defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : 'IN0810000001';
$facilityName = defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health';

// Build Query
$where = ["DATE(created_at) = ?"];
$types = "s";
$params = [$date_filter];

if ($status_filter !== 'all') {
    $where[] = "status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

$sql = "SELECT * FROM abdm_scan_share_tokens WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tokens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Metrics for today
$mStmt = $conn->prepare("
    SELECT
      COUNT(*) as total,
      SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
      SUM(CASE WHEN status = 'in_consultation' THEN 1 ELSE 0 END) as in_progress,
      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM abdm_scan_share_tokens
    WHERE DATE(created_at) = ?
");
$mStmt->bind_param('s', $date_filter);
$mStmt->execute();
$metrics = $mStmt->get_result()->fetch_assoc();
$mStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>ABDM Scan &amp; Share | Admin Panel</title>
  <?php include "links.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <style>
    .token-badge { font-size: 1.1rem; font-weight: 700; padding: 6px 12px; border-radius: 8px; font-family: monospace; }
    .stat-card { border-left: 4px solid #0d6efd; transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-3px); }
    .qr-standee-box {
      background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
      border: 2px solid #0284c7;
      border-radius: 16px;
      padding: 24px;
      text-align: center;
    }
    @media print {
      body * { visibility: hidden; }
      #printableStandee, #printableStandee * { visibility: visible; }
      #printableStandee {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        display: block !important;
        border: none !important;
        box-shadow: none !important;
      }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body class="crm_body_bg">
  <?php include "header.php"; ?>
  <section class="main_content dashboard_part">
    <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>
    <div class="main_content_iner">
      <div class="container-fluid p-0 sm_padding_15px">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
      <div>
        <h3 class="fw-bold mb-1"><i class="fas fa-qrcode text-primary me-2"></i>ABDM Scan &amp; Share (Milestone 3)</h3>
        <p class="text-muted mb-0">Live OPD check-in queue &amp; facility counter QR standee generator</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#qrStandeeModal">
          <i class="fas fa-print me-1"></i>View &amp; Print Counter Standee
        </button>
      </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show no-print">
        <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Metrics Row -->
    <div class="row g-3 mb-4 no-print">
      <div class="col-md-3">
        <div class="card stat-card p-3" style="border-left-color: #0d6efd;">
          <div class="text-muted small">Total Checked-in Today</div>
          <div class="fs-3 fw-bold text-primary"><?= (int)$metrics['total'] ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card p-3" style="border-left-color: #f59e0b;">
          <div class="text-muted small">Waiting in Queue</div>
          <div class="fs-3 fw-bold text-warning"><?= (int)$metrics['waiting'] ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card p-3" style="border-left-color: #0284c7;">
          <div class="text-muted small">In Consultation</div>
          <div class="fs-3 fw-bold text-info"><?= (int)$metrics['in_progress'] ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card p-3" style="border-left-color: #10b981;">
          <div class="text-muted small">Consultations Completed</div>
          <div class="fs-3 fw-bold text-success"><?= (int)$metrics['completed'] ?></div>
        </div>
      </div>
    </div>

    <!-- Filter & Table Card -->
    <div class="card no-print">
      <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2 align-items-center">
          <div class="col-auto">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>" onchange="this.form.submit()">
          </div>
          <div class="col-auto">
            <select name="status" class="form-select" onchange="this.form.submit()">
              <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
              <option value="waiting" <?= $status_filter === 'waiting' ? 'selected' : '' ?>>Waiting</option>
              <option value="in_consultation" <?= $status_filter === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
              <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
          </div>
          <div class="col-auto ms-auto">
            <span class="text-muted small">Facility ID: <code><?= htmlspecialchars($facilityId) ?></code></span>
          </div>
        </form>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Token</th>
                <th>Patient Details</th>
                <th>ABHA Number</th>
                <th>ABHA Address</th>
                <th>Phone</th>
                <th>Check-in Time</th>
                <th>Status</th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($tokens)): ?>
                <tr>
                  <td colspan="8" class="text-center py-5 text-muted">
                    <i class="fas fa-qrcode fa-3x mb-3 d-block text-secondary opacity-50"></i>
                    No patients have checked in via Scan &amp; Share for this date.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($tokens as $t): ?>
                  <tr>
                    <td class="ps-3">
                      <span class="token-badge bg-primary text-white"><?= htmlspecialchars($t['token_number']) ?></span>
                    </td>
                    <td>
                      <div class="fw-bold"><?= htmlspecialchars($t['patient_name']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($t['gender'] ?: '—') ?> · <?= htmlspecialchars($t['dob'] ?: '—') ?></small>
                    </td>
                    <td>
                      <?php if ($t['abha_number']): ?>
                        <code class="fw-bold text-success"><?= htmlspecialchars($t['abha_number']) ?></code>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($t['abha_address']): ?>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($t['abha_address']) ?></span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['phone'] ?: '—') ?></td>
                    <td>
                      <small><?= date('h:i A', strtotime($t['created_at'])) ?></small>
                    </td>
                    <td>
                      <?php
                        $badges = [
                          'waiting'         => 'bg-warning text-dark',
                          'in_consultation' => 'bg-info text-white',
                          'completed'       => 'bg-success text-white',
                          'cancelled'       => 'bg-secondary text-white',
                        ];
                      ?>
                      <span class="badge <?= $badges[$t['status']] ?? 'bg-secondary' ?> px-2 py-1">
                        <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                      </span>
                    </td>
                    <td class="text-end pe-3">
                      <div class="dropdown d-inline">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                          Update Status
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                          <li>
                            <form method="POST">
                              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrfToken()) ?>">
                              <input type="hidden" name="token_id" value="<?= $t['id'] ?>">
                              <input type="hidden" name="status" value="in_consultation">
                              <button type="submit" name="update_token_status" class="dropdown-item"><i class="fas fa-stethoscope text-info me-2"></i>In Consultation</button>
                            </form>
                          </li>
                          <li>
                            <form method="POST">
                              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrfToken()) ?>">
                              <input type="hidden" name="token_id" value="<?= $t['id'] ?>">
                              <input type="hidden" name="status" value="completed">
                              <button type="submit" name="update_token_status" class="dropdown-item"><i class="fas fa-check text-success me-2"></i>Completed</button>
                            </form>
                          </li>
                          <li>
                            <form method="POST">
                              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrfToken()) ?>">
                              <input type="hidden" name="token_id" value="<?= $t['id'] ?>">
                              <input type="hidden" name="status" value="cancelled">
                              <button type="submit" name="update_token_status" class="dropdown-item text-danger"><i class="fas fa-times me-2"></i>Cancelled</button>
                            </form>
                          </li>
                        </ul>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Print Counter QR Standee -->
  <div class="modal fade" id="qrStandeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header no-print">
          <h5 class="modal-title fw-bold"><i class="fas fa-qrcode text-primary me-2"></i>ABDM Scan &amp; Share Standee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div id="printableStandee" class="qr-standee-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="badge bg-success px-3 py-2 fs-6">Ayushman Bharat Digital Mission (ABDM)</span>
              <span class="badge bg-primary px-3 py-2 fs-6">OPD Check-in Counter</span>
            </div>
            <h3 class="fw-bold text-dark mt-2 mb-1"><?= htmlspecialchars($facilityName) ?></h3>
            <p class="text-muted mb-3">Facility ID (HFR): <strong><?= htmlspecialchars($facilityId) ?></strong></p>

            <div class="p-3 my-3 bg-white rounded-3 shadow-sm d-inline-block">
              <div id="facilityCounterQr"></div>
            </div>

            <h5 class="fw-bold text-primary mt-2">Scan &amp; Share Your ABHA Profile</h5>
            <p class="text-dark small mb-0 px-md-4">
              Open your <strong>ABHA App</strong>, <strong>Aarogya Setu</strong>, or any ABDM-enabled PHR app, tap <strong>Scan QR</strong>, and verify your details to receive an instant OPD queue token.
            </p>
          </div>
        </div>
        <div class="modal-footer no-print">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary fw-semibold" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print Acrylic Standee (A4)
          </button>
        </div>
      </div>
    </div>
  </div>

      </div>
    </div>
    <?php include "footer.php"; ?>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const qrPayload = JSON.stringify({
        "facilityId": "<?= htmlspecialchars($facilityId) ?>",
        "facilityName": "<?= htmlspecialchars($facilityName) ?>",
        "context": "OPD"
      });

      const qrTarget = document.getElementById('facilityCounterQr');
      if (qrTarget) {
        new QRCode(qrTarget, {
          text: qrPayload,
          width: 220,
          height: 220,
          colorDark: "#0284c7",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
      }
    });
  </script>
</body>
</html>
