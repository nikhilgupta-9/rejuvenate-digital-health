<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sidebar_active = 'orders';

$orders_query = $conn->prepare("
    SELECT * FROM supplement_orders 
    WHERE user_id = ? 
    ORDER BY order_date DESC, id DESC
");
$orders_query->bind_param('i', $user_id);
$orders_query->execute();
$orders = $orders_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Supplement Orders | REJUVENATE Digital Health</title>
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
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #e0f2fe; color: #0369a1; }
        .badge-shipped { background: #eef2ff; color: #4338ca; }
        .badge-delivered { background: #dcfce7; color: #166534; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>

<body class="patient-body">
    <?php include("sidebar.php"); ?>

    <main class="patient-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="ap-h mb-1"><i class="fa fa-shopping-bag me-2 text-primary-theme"></i>My Supplement Orders</h1>
                <div class="ap-sub">Ayurvedic formulations, dietary supplements, and nutraceutical products</div>
            </div>
            <a href="<?= BASE_URL ?>shop.php" class="btn btn-primary btn-sm">
                <i class="fa fa-shopping-cart me-1"></i> Shop Supplements
            </a>
        </div>

        <?php if ($orders && $orders->num_rows > 0): ?>
            <?php while ($ord = $orders->fetch_assoc()): 
                $items = json_decode($ord['items'] ?? '[]', true) ?: [];
                $statusKey = $ord['status'] ?? 'pending';
                $badgeClass = 'badge-' . $statusKey;
                $statusLabel = match($statusKey) {
                    'pending'   => 'Order Placed',
                    'confirmed' => 'Confirmed',
                    'shipped'   => 'Shipped',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                    default     => ucfirst($statusKey)
                };
            ?>
            <div class="order-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 border-bottom pb-2">
                    <div>
                        <span class="fw-bold fs-6 text-primary-theme">
                            <i class="fa fa-hashtag me-1"></i><?= htmlspecialchars($ord['order_number']) ?>
                        </span>
                        <span class="text-muted small ms-2">
                            <i class="fa fa-calendar me-1"></i>Ordered on <?= date('d M Y, h:i A', strtotime($ord['order_date'] ?? 'now')) ?>
                        </span>
                    </div>
                    <span class="status-badge <?= $badgeClass ?>">
                        <i class="fa fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i><?= $statusLabel ?>
                    </span>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="small text-muted mb-1 font-weight-bold">Shipping Address:</div>
                        <div class="small text-dark">
                            <i class="fa fa-map-marker text-muted me-1"></i>
                            <?= nl2br(htmlspecialchars($ord['shipping_address'])) ?>
                        </div>
                        <?php if (!empty($ord['delivery_date'])): ?>
                            <div class="small text-success mt-1 fw-bold">
                                <i class="fa fa-truck me-1"></i>Est. Delivery: <?= date('d M Y', strtotime($ord['delivery_date'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <div class="small text-muted mb-1 font-weight-bold">Items Ordered:</div>
                        <?php if (!empty($items)): ?>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($items as $it): ?>
                                    <li class="mb-1">
                                        <i class="fa fa-cube text-accent-theme me-1"></i>
                                        <strong><?= htmlspecialchars($it['name'] ?? $it['title'] ?? 'Supplement') ?></strong>
                                        <?php if (!empty($it['quantity']) || !empty($it['qty'])): ?>
                                            <span class="badge bg-light text-dark ms-1">Qty: <?= (int)($it['quantity'] ?? $it['qty']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="small text-muted">Nutraceutical Supplements Package</span>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3 text-md-end">
                        <div class="small text-muted mb-1 font-weight-bold">Total Amount:</div>
                        <div class="fs-5 fw-bold text-dark">₹<?= number_format($ord['total_amount'], 2) ?></div>
                        <div class="small text-muted">Online / COD</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                <div style="width:70px;height:70px;background:#eaf4fd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--primary);font-size:1.8rem;">
                    <i class="fa fa-shopping-bag"></i>
                </div>
                <h5 class="fw-bold mb-2">No Supplement Orders Yet</h5>
                <p class="text-muted small mb-4 mx-auto" style="max-width:440px;">
                    Explore our range of doctor-recommended herbal supplements, immunity boosters, and clinical nutraceuticals.
                </p>
                <div>
                    <a href="<?= BASE_URL ?>shop.php" class="btn btn-primary px-4">
                        <i class="fa fa-shopping-cart me-1"></i> Browse Supplements Store
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include("inc/scripts.php"); ?>
</body>
</html>
