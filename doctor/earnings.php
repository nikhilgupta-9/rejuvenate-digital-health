<?php
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../util/function.php';

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int) ($jwt_doctor['sub'] ?? $jwt_doctor['doctor_id'] ?? 0);

$sidebar_active = 'earnings';
require_once __DIR__ . '/inc/sidebar.php';

$bank_success = '';
$bank_error   = '';

// Save / update bank details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bank'])) {
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $account_number      = trim($_POST['account_number'] ?? '');
    $ifsc_code            = strtoupper(trim($_POST['ifsc_code'] ?? ''));
    $bank_name            = trim($_POST['bank_name'] ?? '');
    $upi_id               = trim($_POST['upi_id'] ?? '');

    if ($account_holder_name === '' || $account_number === '' || $ifsc_code === '' || $bank_name === '') {
        $bank_error = 'Account holder name, account number, IFSC code and bank name are required.';
    } elseif (!preg_match('/^\d{9,18}$/', $account_number)) {
        $bank_error = 'Enter a valid account number (9–18 digits).';
    } elseif (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
        $bank_error = 'Enter a valid IFSC code (e.g. HDFC0001234).';
    } elseif ($upi_id !== '' && !preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $upi_id)) {
        $bank_error = 'Enter a valid UPI ID (e.g. name@bank), or leave it blank.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO doctor_bank_accounts (doctor_id, account_holder_name, account_number, ifsc_code, bank_name, upi_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                account_holder_name = VALUES(account_holder_name),
                account_number = VALUES(account_number),
                ifsc_code = VALUES(ifsc_code),
                bank_name = VALUES(bank_name),
                upi_id = VALUES(upi_id),
                is_verified = 0, verified_at = NULL, verified_by = NULL
        ");
        $upiParam = $upi_id !== '' ? $upi_id : null;
        $stmt->bind_param('isssss', $doctor_id, $account_holder_name, $account_number, $ifsc_code, $bank_name, $upiParam);
        if ($stmt->execute()) {
            $bank_success = 'Bank details saved. Our team will verify them before your next settlement.';
        } else {
            $bank_error = 'Could not save bank details. Please try again.';
        }
    }
}

// Current bank details
$bank_stmt = $conn->prepare("SELECT * FROM doctor_bank_accounts WHERE doctor_id = ? LIMIT 1");
$bank_stmt->bind_param('i', $doctor_id);
$bank_stmt->execute();
$bank = $bank_stmt->get_result()->fetch_assoc();

// Settlement summary
$sum_stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status = 'pending' THEN settlement_amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'settled' THEN settlement_amount ELSE 0 END), 0) AS settled_total
    FROM appointment_settlements WHERE doctor_id = ?
");
$sum_stmt->bind_param('i', $doctor_id);
$sum_stmt->execute();
$summary = $sum_stmt->get_result()->fetch_assoc();

// Settlement history
$settle_stmt = $conn->prepare("
    SELECT s.*, a.appointment_date, a.patient_name, u.name AS patient_user_name
    FROM appointment_settlements s
    JOIN appointments a ON a.id = s.appointment_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE s.doctor_id = ?
    ORDER BY s.created_at DESC
");
$settle_stmt->bind_param('i', $doctor_id);
$settle_stmt->execute();
$settlements = $settle_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Earnings &amp; Bank Details | REJUVENATE Doctor Portal</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <style>
    .earn-stat { border-radius: 12px; padding: 18px 20px; color: #fff; }
    .earn-stat .num { font-size: 1.6rem; font-weight: 700; }
    .earn-stat .lbl { font-size: .78rem; opacity: .9; }
    .st-badge { padding: 3px 10px; border-radius: 10px; font-size: .72rem; font-weight: 600; }
    .st-pending { background: #fef3c7; color: #92400e; }
    .st-settled { background: #dcfce7; color: #166534; }
    .bank-verified { background: #dcfce7; color: #166534; }
    .bank-unverified { background: #f3f4f6; color: #6b7280; }
  </style>
</head>
<body>
<?php include(__DIR__ . "/inc/sidebar.php"); ?>

<main class="doctor-content">
  <p class="section-title">Earnings &amp; Bank Details</p>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="earn-stat" style="background:#e07e18;">
        <div class="num">₹<?= number_format($summary['pending_total'], 2) ?></div>
        <div class="lbl">Pending Settlement</div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="earn-stat" style="background:#198754;">
        <div class="num">₹<?= number_format($summary['settled_total'], 2) ?></div>
        <div class="lbl">Total Settled</div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body">
          <h6 class="fw-bold mb-1"><i class="fa fa-bank me-2" style="color:var(--primary);"></i>Bank Account Details</h6>
          <p class="text-muted" style="font-size:.8rem;">
            Payments are settled here <strong>2 days (T+2)</strong> after each completed, paid consultation.
          </p>

          <?php if ($bank): ?>
            <div class="mb-3">
              <?php if ($bank['is_verified']): ?>
                <span class="st-badge bank-verified"><i class="fa fa-check-circle"></i> Verified</span>
              <?php else: ?>
                <span class="st-badge bank-unverified"><i class="fa fa-clock-o"></i> Pending verification</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($bank_success): ?>
            <div class="alert alert-success py-2" style="font-size:.85rem;"><?= htmlspecialchars($bank_success) ?></div>
          <?php endif; ?>
          <?php if ($bank_error): ?>
            <div class="alert alert-danger py-2" style="font-size:.85rem;"><?= htmlspecialchars($bank_error) ?></div>
          <?php endif; ?>

          <form method="POST">
            <input type="hidden" name="save_bank" value="1">
            <div class="mb-2">
              <label class="form-label" style="font-size:.8rem;font-weight:600;">Account Holder Name</label>
              <input type="text" name="account_holder_name" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($bank['account_holder_name'] ?? '') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label" style="font-size:.8rem;font-weight:600;">Account Number</label>
              <input type="text" name="account_number" class="form-control form-control-sm" inputmode="numeric"
                     value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>" required>
            </div>
            <div class="row">
              <div class="col-6 mb-2">
                <label class="form-label" style="font-size:.8rem;font-weight:600;">IFSC Code</label>
                <input type="text" name="ifsc_code" class="form-control form-control-sm" style="text-transform:uppercase;"
                       value="<?= htmlspecialchars($bank['ifsc_code'] ?? '') ?>" required>
              </div>
              <div class="col-6 mb-2">
                <label class="form-label" style="font-size:.8rem;font-weight:600;">Bank Name</label>
                <input type="text" name="bank_name" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" style="font-size:.8rem;font-weight:600;">UPI ID <span class="text-muted fw-normal">(optional)</span></label>
              <input type="text" name="upi_id" class="form-control form-control-sm" placeholder="name@bank"
                     value="<?= htmlspecialchars($bank['upi_id'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm fw-bold">
              <i class="fa fa-save me-1"></i> Save Bank Details
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-3 pb-2">
          <h6 class="fw-bold mb-0"><i class="fa fa-inr me-2" style="color:var(--primary);"></i>Settlement History</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($settlements)): ?>
            <p class="text-muted text-center py-5 mb-0">No settlements yet — they appear here once a paid consultation is marked completed.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="font-size:.73rem;">Patient</th>
                    <th style="font-size:.73rem;">Gross</th>
                    <th style="font-size:.73rem;">Commission (10%)</th>
                    <th style="font-size:.73rem;">Net Payout</th>
                    <th style="font-size:.73rem;">Status</th>
                    <th style="font-size:.73rem;">Due (T+2)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($settlements as $s): ?>
                    <tr>
                      <td style="font-size:.83rem;"><?= htmlspecialchars($s['patient_user_name'] ?: $s['patient_name'] ?: '—') ?></td>
                      <td style="font-size:.83rem;">₹<?= number_format($s['gross_amount'], 2) ?></td>
                      <td style="font-size:.83rem;color:#6b7280;">₹<?= number_format($s['commission_amount'], 2) ?></td>
                      <td style="font-size:.83rem;font-weight:600;">₹<?= number_format($s['settlement_amount'], 2) ?></td>
                      <td>
                        <?php if ($s['status'] === 'settled'): ?>
                          <span class="st-badge st-settled"><i class="fa fa-check"></i> Settled</span>
                        <?php else: ?>
                          <span class="st-badge st-pending"><i class="fa fa-clock-o"></i> Pending</span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size:.8rem;color:#6b7280;"><?= date('d M Y', strtotime($s['due_date'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
</body>
</html>
