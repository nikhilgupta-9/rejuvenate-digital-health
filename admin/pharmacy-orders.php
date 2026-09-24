<?php
/**
 * Admin → Pharmacy Orders
 * Manage medicine delivery requests, verify prescriptions, and track dispatch status.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/lib/Security.php';
require_once dirname(__DIR__) . '/lib/WhatsAppNotifier.php';
admin_jwt_guard();

$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');

// Handle status & tracking update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    if (!isset($_POST['csrf_token']) || !Security::verifyCsrf($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid CSRF token.";
        header("Location: pharmacy-orders.php");
        exit();
    }

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['order_status'] ?? 'placed');
    $tracking = trim($_POST['tracking_number'] ?? '');
    $totalAmount = (float)($_POST['total_amount'] ?? 0.00);

    $valid = ['placed', 'verified', 'dispatched', 'delivered', 'cancelled'];
    if (in_array($newStatus, $valid, true) && $orderId > 0) {
        $stmt = $conn->prepare("UPDATE pharmacy_orders SET order_status = ?, tracking_number = ?, total_amount = ? WHERE id = ?");
        $stmt->bind_param('ssdi', $newStatus, $tracking, $totalAmount, $orderId);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Order status updated to " . ucfirst($newStatus) . "!";

            // Fetch order details for WhatsApp notification
            $oStmt = $conn->prepare("SELECT order_number, patient_name, patient_phone FROM pharmacy_orders WHERE id = ? LIMIT 1");
            $oStmt->bind_param('i', $orderId);
            $oStmt->execute();
            $order = $oStmt->get_result()->fetch_assoc();
            $oStmt->close();

            if ($order && !empty($order['patient_phone'])) {
                try {
                    $wa = new WhatsAppNotifier($conn);
                    if ($newStatus === 'dispatched') {
                        $wa->sendEvent('pharmacy_order_dispatched', $order['patient_phone'], [
                            'patient_name' => $order['patient_name'],
                            'order_number' => $order['order_number'],
                            'tracking'     => $tracking ?: 'Dispatched with local courier',
                        ], 'pharmacy_order', $orderId);
                    } elseif ($newStatus === 'delivered') {
                        $wa->sendEvent('pharmacy_order_delivered', $order['patient_phone'], [
                            'patient_name' => $order['patient_name'],
                            'order_number' => $order['order_number'],
                        ], 'pharmacy_order', $orderId);
                    }
                } catch (Throwable $e) {
                    error_log('[Pharmacy Order] WhatsApp alert error: ' . $e->getMessage());
                }
            }
        }
        $stmt->close();
    }
    header("Location: pharmacy-orders.php");
    exit();
}

$where = "1=1";
if (in_array($status_filter, ['placed', 'verified', 'dispatched', 'delivered', 'cancelled'], true)) {
    $where .= " AND order_status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search !== '') {
    $q = $conn->real_escape_string($search);
    $where .= " AND (order_number LIKE '%$q%' OR patient_name LIKE '%$q%' OR patient_phone LIKE '%$q%' OR city LIKE '%$q%')";
}

$orders = $conn->query("SELECT * FROM pharmacy_orders WHERE $where ORDER BY created_at DESC LIMIT 200");

// Counts
$c = fn($sql) => (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
$cnt_all        = $c("SELECT COUNT(*) c FROM pharmacy_orders");
$cnt_placed     = $c("SELECT COUNT(*) c FROM pharmacy_orders WHERE order_status='placed'");
$cnt_verified   = $c("SELECT COUNT(*) c FROM pharmacy_orders WHERE order_status='verified'");
$cnt_dispatched = $c("SELECT COUNT(*) c FROM pharmacy_orders WHERE order_status='dispatched'");
$cnt_delivered  = $c("SELECT COUNT(*) c FROM pharmacy_orders WHERE order_status='delivered'");

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Pharmacy Orders | Admin Panel</title>
    <?php include "links.php"; ?>
</head>
<body class="crm_body_bg">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0 fw-bold">Pharmacy & Medicine Orders</h4>
                        <small class="text-muted">Review prescriptions, verify medicine requests, and update dispatch status</small>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= $success_message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Summary Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Total Orders</div><h3 class="fw-bold mb-0 text-primary"><?= $cnt_all ?></h3></div></div>
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">New / Placed</div><h3 class="fw-bold mb-0 text-warning"><?= $cnt_placed ?></h3></div></div>
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Dispatched</div><h3 class="fw-bold mb-0 text-info"><?= $cnt_dispatched ?></h3></div></div>
                    <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="text-muted small">Delivered</div><h3 class="fw-bold mb-0 text-success"><?= $cnt_delivered ?></h3></div></div>
                </div>

                <!-- Filters & Table -->
                <div class="card border-0 shadow-sm p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="btn-group">
                            <a href="pharmacy-orders.php?status=all" class="btn btn-sm <?= $status_filter==='all'?'btn-primary':'btn-outline-secondary' ?>">All</a>
                            <a href="pharmacy-orders.php?status=placed" class="btn btn-sm <?= $status_filter==='placed'?'btn-primary':'btn-outline-secondary' ?>">New Placed</a>
                            <a href="pharmacy-orders.php?status=verified" class="btn btn-sm <?= $status_filter==='verified'?'btn-primary':'btn-outline-secondary' ?>">Verified</a>
                            <a href="pharmacy-orders.php?status=dispatched" class="btn btn-sm <?= $status_filter==='dispatched'?'btn-primary':'btn-outline-secondary' ?>">Dispatched</a>
                            <a href="pharmacy-orders.php?status=delivered" class="btn btn-sm <?= $status_filter==='delivered'?'btn-primary':'btn-outline-secondary' ?>">Delivered</a>
                        </div>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search order / patient / phone" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Patient</th>
                                    <th>Prescription / Items</th>
                                    <th>Delivery Details</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($orders && $orders->num_rows > 0): ?>
                                    <?php while ($ord = $orders->fetch_assoc()): 
                                        $items = json_decode($ord['items_json'] ?? '[]', true) ?: [];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary"><?= htmlspecialchars($ord['order_number']) ?></span>
                                            <div class="text-muted" style="font-size:.75rem;"><?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($ord['patient_name']) ?></strong>
                                            <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($ord['patient_phone']) ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($ord['prescription_file'])): ?>
                                                <a href="<?= BASE_URL . htmlspecialchars($ord['prescription_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-1">
                                                    <i class="fas fa-file-medical me-1"></i>View Uploaded Rx
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($ord['prescription_id'])): ?>
                                                <a href="<?= BASE_URL ?>doctor/opd-slip.php?appointment_id=<?= (int)$ord['prescription_id'] ?>" target="_blank" class="btn btn-sm btn-outline-info mb-1">
                                                    <i class="fas fa-notes-medical me-1"></i>Consultation Slip
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($items)): ?>
                                                <div class="small text-muted"><strong><?= count($items) ?> item(s):</strong> 
                                                    <?= htmlspecialchars(implode(', ', array_map(fn($it)=>$it['name'], array_slice($items, 0, 3)))) ?>
                                                    <?= count($items) > 3 ? '...' : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small text-truncate" style="max-width:200px;" title="<?= htmlspecialchars($ord['delivery_address']) ?>"><?= htmlspecialchars($ord['delivery_address']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($ord['city']) ?> (<?= htmlspecialchars($ord['zip_code']) ?>)</div>
                                        </td>
                                        <td>
                                            <strong>₹<?= number_format($ord['total_amount'], 2) ?></strong>
                                            <div class="small text-muted"><?= strtoupper($ord['payment_method']) ?> (<?= ucfirst($ord['payment_status']) ?>)</div>
                                        </td>
                                        <td>
                                            <?php 
                                            $stClass = match($ord['order_status']) {
                                                'placed'     => 'bg-warning text-dark',
                                                'verified'   => 'bg-info text-dark',
                                                'dispatched' => 'bg-primary text-white',
                                                'delivered'  => 'bg-success text-white',
                                                default      => 'bg-secondary text-white'
                                            };
                                            ?>
                                            <span class="badge <?= $stClass ?> px-2 py-1"><?= ucfirst($ord['order_status']) ?></span>
                                            <?php if (!empty($ord['tracking_number'])): ?>
                                                <div class="small text-muted mt-1">Track: <?= htmlspecialchars($ord['tracking_number']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openUpdateModal(<?= htmlspecialchars(json_encode($ord)) ?>)">
                                                <i class="fas fa-edit me-1"></i>Update
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No pharmacy orders found matching the filter.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Modal -->
        <div class="modal fade" id="orderModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="update_order_status" value="1">
                        <input type="hidden" name="order_id" id="modalOrderId">
                        <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="modalOrderTitle">Update Order</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Order Status</label>
                                <select name="order_status" id="modalStatus" class="form-select">
                                    <option value="placed">Placed (Pending Review)</option>
                                    <option value="verified">Verified by Pharmacist</option>
                                    <option value="dispatched">Dispatched / Out for Delivery</option>
                                    <option value="delivered">Delivered Successfully</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Total Final Price (₹)</label>
                                <input type="number" step="0.01" name="total_amount" id="modalAmount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Courier / Delivery Tracking Number</label>
                                <input type="text" name="tracking_number" id="modalTracking" class="form-control" placeholder="e.g. DTDC-88491024 / Local Boy: Ramesh">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
        <script>
            function openUpdateModal(ord) {
                document.getElementById('modalOrderId').value = ord.id;
                document.getElementById('modalOrderTitle').innerText = 'Update Order #' + ord.order_number;
                document.getElementById('modalStatus').value = ord.order_status;
                document.getElementById('modalAmount').value = ord.total_amount;
                document.getElementById('modalTracking').value = ord.tracking_number || '';
                new bootstrap.Modal(document.getElementById('orderModal')).show();
            }
        </script>
</body>
</html>
