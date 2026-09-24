<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sidebar_active = 'pharmacy';

$orders = $conn->query("
    SELECT * FROM pharmacy_orders 
    WHERE user_id = {$user_id} 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Medicine Orders | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .order-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            transition: all .2s ease;
        }
        .order-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 14px rgba(12,116,197,.1);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-placed { background: #fef3c7; color: #92400e; }
        .badge-verified { background: #e0f2fe; color: #0369a1; }
        .badge-dispatched { background: #eef2ff; color: #4338ca; }
        .badge-delivered { background: #dcfce7; color: #166534; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="patient-body">
    <?php include "sidebar.php"; ?>

    <main class="patient-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="ap-h mb-1"><i class="fa fa-pills me-2 text-primary-theme"></i>My Medicine Orders</h1>
                <div class="ap-sub">Prescription pharmacy deliveries, verified medications, and doorstep dispatch</div>
            </div>
            <a href="<?= BASE_URL ?>book-your-medicine.php" class="btn btn-primary btn-sm">
                <i class="fa fa-plus me-1"></i> Order New Medicines
            </a>
        </div>

        <?php if ($orders && $orders->num_rows > 0): ?>
            <?php while ($ord = $orders->fetch_assoc()): 
                $items = json_decode($ord['items_json'] ?? '[]', true) ?: [];
                $statusKey = $ord['order_status'] ?? 'placed';
                $badgeClass = 'badge-' . $statusKey;
                $statusLabel = match($statusKey) {
                    'placed'     => 'Order Placed',
                    'verified'   => 'Verified by Pharmacist',
                    'dispatched' => 'Dispatched',
                    'delivered'  => 'Delivered',
                    'cancelled'  => 'Cancelled',
                    default      => ucfirst($statusKey)
                };
            ?>
            <div class="order-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 border-bottom pb-2">
                    <div>
                        <span class="fw-bold fs-6 text-primary-theme">
                            <i class="fa fa-receipt me-1"></i><?= htmlspecialchars($ord['order_number']) ?>
                        </span>
                        <span class="text-muted small ms-2">
                            <i class="fa fa-calendar me-1"></i>Placed on <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                        </span>
                    </div>
                    <span class="status-badge <?= $badgeClass ?>">
                        <i class="fa fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i><?= $statusLabel ?>
                    </span>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="small text-muted mb-1 font-weight-bold">Delivering To:</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($ord['patient_name']) ?></div>
                        <div class="small text-muted mt-1">
                            <i class="fa fa-map-marker me-1"></i><?= htmlspecialchars($ord['delivery_address']) ?>, <?= htmlspecialchars($ord['city']) ?> 
                            <?php if (!empty($ord['zip_code'])): ?>(<?= htmlspecialchars($ord['zip_code']) ?>)<?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <i class="fa fa-phone me-1"></i><?= htmlspecialchars($ord['patient_phone']) ?>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="small text-muted mb-1 font-weight-bold">Prescription & Medications:</div>
                        <?php if (!empty($ord['prescription_file'])): ?>
                            <div class="mb-2">
                                <a href="<?= BASE_URL . htmlspecialchars($ord['prescription_file']) ?>" target="_blank" class="small text-primary-theme text-decoration-none fw-bold">
                                    <i class="fa fa-file-pdf me-1"></i> View Uploaded Prescription
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($items)): ?>
                            <ul class="list-unstyled mb-0 small text-muted">
                                <?php foreach ($items as $it): ?>
                                    <li class="mb-1">
                                        <i class="fa fa-check-circle text-accent-theme me-1"></i>
                                        <strong><?= htmlspecialchars($it['name']) ?></strong>
                                        <?php if (!empty($it['strength'])): ?>
                                            <span>(<?= htmlspecialchars($it['strength']) ?>)</span>
                                        <?php endif; ?>
                                        <span class="badge bg-light text-dark ms-1">Qty: <?= (int)$it['qty'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif (empty($ord['prescription_file'])): ?>
                            <span class="small text-muted">Prescription upload order</span>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3 text-md-end">
                        <div class="small text-muted mb-1 font-weight-bold">Total Bill:</div>
                        <div class="fs-5 fw-bold text-dark">₹<?= number_format($ord['total_amount'], 2) ?></div>
                        <div class="small text-muted text-uppercase">
                            <?= htmlspecialchars($ord['payment_method']) ?> • <?= ucfirst($ord['payment_status'] ?? 'pending') ?>
                        </div>
                        <?php if (!empty($ord['tracking_number'])): ?>
                            <div class="mt-2 text-primary-theme small fw-bold">
                                <i class="fa fa-truck me-1"></i>Track: <?= htmlspecialchars($ord['tracking_number']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                <div style="width:70px;height:70px;background:#eaf4fd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--primary);font-size:1.8rem;">
                    <i class="fa fa-pills"></i>
                </div>
                <h5 class="fw-bold mb-2">No Medicine Orders Yet</h5>
                <p class="text-muted small mb-4 mx-auto" style="max-width:440px;">
                    You haven't placed any pharmacy orders yet. Easily upload a doctor's prescription or order verified medications for home delivery.
                </p>
                <div>
                    <a href="<?= BASE_URL ?>book-your-medicine.php" class="btn btn-primary px-4">
                        <i class="fa fa-plus me-1"></i> Order Medicines Now
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include("inc/scripts.php"); ?>
</body>
</html>
