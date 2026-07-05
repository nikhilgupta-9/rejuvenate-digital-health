<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$success_message = '';
$error_message = '';

$d_stmt = $conn->prepare("SELECT name, email, password FROM doctors WHERE id = ?");
$d_stmt->bind_param('i', $doctor_id);
$d_stmt->execute();
$doctor = $d_stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $password = trim($_POST['password'] ?? '');
  $reason = trim($_POST['reason'] ?? '');

  if (!$password) {
    $error_message = 'Please enter your password to confirm.';
  } elseif (!password_verify($password, $doctor['password'])) {
    $error_message = 'Incorrect password.';
  } else {
    $chk = $conn->prepare("SELECT id FROM doctor_deletion_requests WHERE doctor_id = ? AND status = 'Pending' LIMIT 1");
    $chk->bind_param('i', $doctor_id);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
      $error_message = 'You already have a pending deletion request.';
    } else {
      $ins = $conn->prepare("INSERT INTO doctor_deletion_requests (doctor_id, reason, status) VALUES (?, ?, 'Pending')");
      $ins->bind_param('is', $doctor_id, $reason);
      if ($ins->execute()) {
        $success_message = 'Your account deletion request has been submitted. An administrator will review it shortly.';
      } else {
        $error_message = 'Failed to submit request: ' . $conn->error;
      }
    }
  }
}

// Current status
$req_stmt = $conn->prepare("SELECT * FROM doctor_deletion_requests WHERE doctor_id = ? ORDER BY requested_at DESC LIMIT 5");
$req_stmt->bind_param('i', $doctor_id);
$req_stmt->execute();
$requests = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$has_pending = false;
foreach ($requests as $r) {
  if ($r['status'] === 'Pending') {
    $has_pending = true;
    break;
  }
}

$sidebar_active = 'delete-account';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Delete Account — Rejuvenate</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <style>
    .info-section {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
      padding: 20px 22px;
      margin-bottom: 16px;
      max-width: 640px;
    }

    .section-title {
      font-size: .9rem;
      font-weight: 700;
      color: #374151;
      margin-bottom: 6px;
    }

    .section-title i {
      color: #dc3545;
      margin-right: 6px;
    }

    .warn-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 10px;
      padding: 14px 16px;
      font-size: .84rem;
      color: #7f1d1d;
      margin-bottom: 16px;
    }

    .badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
    }

    .badge-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .badge-rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    .badge-approved {
      background: #dcfce7;
      color: #166534;
    }

    .hist-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #f3f4f6;
      font-size: .82rem;
    }

    .hist-row:last-child {
      border-bottom: none;
    }
  </style>
</head>

<body>
  <main class="doctor-content">

    <div class="info-section">
      <div class="section-title"><i class="fa fa-exclamation-triangle"></i> Delete My Account</div>
      <div class="warn-box">
        <i class="fa fa-info-circle mr-1"></i>
        This submits a deactivation request — your account is <strong>not</strong> deleted immediately.
        An administrator reviews every request before your profile, patients, and appointment history are affected.
      </div>

      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <?php if ($has_pending): ?>
        <div class="alert alert-warning">
          <i class="fa fa-clock-o mr-1"></i> You already have a pending deletion request awaiting admin review.
        </div>
      <?php else: ?>
        <form method="POST">
          <div class="form-group">
            <label>Reason (optional)</label>
            <textarea name="reason" class="form-control" rows="3"
              placeholder="Let us know why you're leaving..."></textarea>
          </div>
          <div class="form-group">
            <label>Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required
              placeholder="Enter your current password">
          </div>
          <button type="submit" class="btn btn-danger"
            onclick="return confirm('Are you sure you want to request account deletion?');">
            <i class="fa fa-trash mr-1"></i> Submit Deletion Request
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($requests)): ?>
      <div class="info-section">
        <div class="section-title" style="color:#374151;"><i class="fa fa-history" style="color:#0C74C5;"></i> Request
          History</div>
        <?php foreach ($requests as $r): ?>
          <div class="hist-row">
            <div>
              Requested <?= date('d M Y', strtotime($r['requested_at'])) ?>
              <?php if (!empty($r['reason'])): ?>
                <div class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars($r['reason']) ?></div><?php endif; ?>
            </div>
            <div>
              <?php if ($r['status'] === 'Pending'): ?>
                <span class="badge-pill badge-pending">Pending</span>
              <?php elseif ($r['status'] === 'Approved'): ?>
                <span class="badge-pill badge-approved">Approved</span>
              <?php else: ?>
                <span class="badge-pill badge-rejected">Rejected</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</body>

</html>