<?php
require_once __DIR__ . '/auth/guard.php';

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int) ($jwt_doctor['sub'] ?? $jwt_doctor['doctor_id'] ?? 0);

$sidebar_active = 'billing';
require_once __DIR__ . '/inc/sidebar.php';

$sub_stmt = $conn->prepare("
    SELECT ds.*, dp.name AS plan_name, dp.billing_cycle_days
    FROM doctor_subscriptions ds
    LEFT JOIN doctor_plans dp ON dp.id = ds.plan_id
    WHERE ds.doctor_id = ?
    ORDER BY ds.created_at DESC
");
$sub_stmt->bind_param('i', $doctor_id);
$sub_stmt->execute();
$subscriptions = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_badge = [
    'paid'   => ['bg-ok',      'fa-check-circle',   'Paid'],
    'pending' => ['bg-pending', 'fa-clock-o',        'Pending'],
    'failed' => ['bg-red',     'fa-times-circle',   'Failed'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment History | REJUVENATE Doctor Portal</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <style>
    .bg-ok { background: #dcfce7; color: #166534; }
    .bg-pending { background: #fef3c7; color: #92400e; }
    .bg-red { background: #fee2e2; color: #991b1b; }
    .ph-badge { padding: 3px 10px; border-radius: 10px; font-size: .72rem; font-weight: 600; }
  </style>
</head>
<body>
<?php include(__DIR__ . "/inc/sidebar.php"); ?>

<main class="doctor-content">
  <p class="section-title">Payment History</p>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
      <?php if (empty($subscriptions)): ?>
        <p class="text-muted text-center py-5 mb-0">No payment history yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="font-size:.73rem;">Plan</th>
                <th style="font-size:.73rem;">Amount</th>
                <th style="font-size:.73rem;">Status</th>
                <th style="font-size:.73rem;">Period</th>
                <th style="font-size:.73rem;">Payment ID</th>
                <th style="font-size:.73rem;">Purchased On</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subscriptions as $sub):
                $b = $status_badge[$sub['status']] ?? ['bg-secondary text-white', 'fa-question-circle', ucfirst($sub['status'])];
              ?>
                <tr>
                  <td style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($sub['plan_name'] ?? 'Plan #' . $sub['plan_id']) ?></td>
                  <td style="font-size:.85rem;">₹<?= number_format($sub['amount'], 2) ?></td>
                  <td><span class="ph-badge <?= $b[0] ?>"><i class="fa <?= $b[1] ?>"></i> <?= $b[2] ?></span></td>
                  <td style="font-size:.8rem;color:#6b7280;">
                    <?php if ($sub['starts_at'] && $sub['expires_at']): ?>
                      <?= date('d M Y', strtotime($sub['starts_at'])) ?> – <?= date('d M Y', strtotime($sub['expires_at'])) ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td style="font-size:.78rem;color:#9ca3af;"><?= htmlspecialchars($sub['razorpay_payment_id'] ?: '—') ?></td>
                  <td style="font-size:.8rem;color:#6b7280;"><?= date('d M Y, h:i A', strtotime($sub['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
