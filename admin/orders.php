<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_id'], $_POST['update_status'])) {
    $update_order_id = mysqli_real_escape_string($conn, $_POST['update_order_id']);
    $update_status = mysqli_real_escape_string($conn, $_POST['update_status']);

    $update_sql = "UPDATE orders_new SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ss", $update_status, $update_order_id);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Order #$update_order_id status updated to " . ucfirst($update_status);
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to update order status";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['selected_orders'])) {
    $selected_orders = $_POST['selected_orders'];
    $bulk_status = mysqli_real_escape_string($conn, $_POST['bulk_status']);
    
    $placeholders = implode(',', array_fill(0, count($selected_orders), '?'));
    $types = str_repeat('s', count($selected_orders));
    
    $bulk_sql = "UPDATE orders_new SET status = ? WHERE order_id IN ($placeholders)";
    $stmt = $conn->prepare($bulk_sql);
    $stmt->bind_param("s".$types, $bulk_status, ...$selected_orders);
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Bulk updated ".count($selected_orders)." orders to " . ucfirst($bulk_status);
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to perform bulk action";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query with filters
$sql = "SELECT * FROM `orders_new` WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (order_id LIKE ? OR first_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$sql .= " ORDER BY `id` DESC LIMIT 100";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Order Management | Admin Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-light: #5d7ce0;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --light-bg: #f8f9fc;
            --dark-text: #5a5c69;
            --muted-text: #858796;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark-text);
            font-family: 'Nunito', sans-serif;
        }

        .order-card {
            background: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border: none;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            border-radius: 0.35rem 0.35rem 0 0 !important;
        }

        .card-title {
            font-weight: 700;
            color: var(--dark-text);
            font-size: 1.25rem;
        }

        .table-responsive-container {
            max-height: 600px;
            overflow: auto;
        }

        .table-responsive {
            min-width: 100%;
        }

        .table-responsive table {
            border-radius: 0.35rem;
            overflow: hidden;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            padding: 0.75rem;
            border: 1px solid rgba(137, 139, 141, 0.15);
        }

        .status-badge {
            padding: 0.35rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .badge-pending {
            background-color: rgba(246, 194, 62, 0.2);
            color: var(--warning-color);
        }

        .badge-delivered {
            background-color: rgba(28, 200, 138, 0.2);
            color: var(--success-color);
        }

        .badge-cancelled {
            background-color: rgba(231, 74, 59, 0.2);
            color: var(--danger-color);
        }

        .badge-processing {
            background-color: rgba(54, 185, 204, 0.2);
            color: var(--info-color);
        }

        .action-btn {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: var(--muted-text);
            margin: 0 2px;
        }

        .action-btn:hover {
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
            transform: scale(1.1);
        }

        .amount-cell {
            font-weight: 700;
            color: var(--dark-text);
            white-space: nowrap;
        }

        .customer-name {
            font-weight: 600;
            color: var(--dark-text);
        }

        .customer-email {
            color: var(--primary-color);
            font-size: 0.85rem;
            word-break: break-all;
        }

        .date-cell {
            white-space: nowrap;
            font-size: 0.85rem;
            color: var(--muted-text);
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            padding-left: 2.5rem;
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted-text);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .pagination .page-link {
            color: var(--primary-color);
            border-radius: 0.35rem;
            margin: 0 0.25rem;
            border: none;
            min-width: 2.25rem;
            text-align: center;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .filter-section {
            background: #f8f9fc;
            padding: 1rem;
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted-text);
            margin-bottom: 0.25rem;
        }

        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .select-all-checkbox {
            margin-right: 0.5rem;
        }

        .order-checkbox {
            margin: 0;
        }

        .product-list {
            max-width: 300px;
            white-space: normal;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
        }

        .product-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-qty {
            color: var(--muted-text);
            margin-left: 0.5rem;
        }

        .location-cell {
            max-width: 200px;
            white-space: normal;
        }

        @media (max-width: 992px) {
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .table thead {
                display: none;
            }

            .table tr {
                display: block;
                margin-bottom: 1rem;
                border-radius: 0.35rem;
                box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            }

            .table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
                padding: 0.75rem;
            }

            .table td:before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--muted-text);
                margin-right: 1rem;
                flex: 1;
            }

            .table td>div {
                flex: 2;
                text-align: right;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .search-box {
                margin-top: 1rem;
                max-width: 100%;
                width: 100%;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body class="crm_body_bg">

    <?php include "header.php"; ?>
    <section class="main_content dashboard_part large_header_bg">

        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner ">
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <?php if (isset($_SESSION['flash_message'])): ?>
                            <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show" role="alert">
                                <?= $_SESSION['flash_message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
                        <?php endif; ?>
                        
                        <div class="white_card card_height_100 mb_30">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                                <h4 class="card-title mb-0">Order Management</h4>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="search-box me-2">
                                        <form method="GET" action="">
                                            <i class="fas fa-search search-icon"></i>
                                            <input type="text" class="form-control" name="search" placeholder="Search orders..." 
                                                   value="<?= htmlspecialchars($search) ?>">
                                        </form>
                                    </div>
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-2"></i>Export
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Filter Section -->
                            <div class="filter-section">
                                <form method="GET" action="">
                                    <div class="filter-row">
                                        <div class="filter-group">
                                            <label class="filter-label">Status</label>
                                            <select name="status" class="form-select form-select-sm">
                                                <option value="">All Statuses</option>
                                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="filter-group">
                                            <label class="filter-label">Date From</label>
                                            <input type="date" name="date_from" class="form-control form-control-sm" 
                                                   value="<?= htmlspecialchars($date_from) ?>">
                                        </div>
                                        
                                        <div class="filter-group">
                                            <label class="filter-label">Date To</label>
                                            <input type="date" name="date_to" class="form-control form-control-sm" 
                                                   value="<?= htmlspecialchars($date_to) ?>">
                                        </div>
                                        
                                        <div class="filter-group">
                                            <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="white_card_body">
                                <div class="QA_section">
                                    <!-- Bulk Actions -->
                                    <form method="POST" action="" id="bulk-action-form">
                                        <div class="bulk-actions">
                                            <div class="form-check select-all-checkbox">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                                <label class="form-check-label" for="select-all">Select All</label>
                                            </div>
                                            
                                            <select name="bulk_status" class="form-select form-select-sm" style="width: 150px;">
                                                <option value="">Bulk Actions</option>
                                                <option value="pending">Mark as Pending</option>
                                                <option value="processing">Mark as Processing</option>
                                                <option value="delivered">Mark as Delivered</option>
                                                <option value="cancelled">Mark as Cancelled</option>
                                            </select>
                                            
                                            <button type="submit" name="bulk_action" value="1" class="btn btn-sm btn-outline-primary">
                                                Apply
                                            </button>
                                        </div>
                                        
                                        <div class="QA_table mb_30">
                                            <div class="table-responsive-container">
                                                <table class="table table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th width="40px"></th>
                                                            <th scope="col">Action</th>
                                                            <th scope="col">Order ID</th>
                                                            <th scope="col">Status</th>
                                                            <th scope="col">Update Status</th>
                                                            <th scope="col">Products</th>
                                                            <th scope="col">Customer</th>
                                                            <th scope="col">Contact Info</th>
                                                            <th scope="col">Location</th>
                                                            <th scope="col">Payment</th>
                                                            <th scope="col">Amount</th>
                                                            <th scope="col">Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                                <?php
                                                                $statusClass = '';
                                                                switch (strtolower($row['status'])) {
                                                                    case 'delivered':
                                                                        $statusClass = 'badge-delivered';
                                                                        break;
                                                                    case 'cancelled':
                                                                        $statusClass = 'badge-cancelled';
                                                                        break;
                                                                    case 'processing':
                                                                        $statusClass = 'badge-processing';
                                                                        break;
                                                                    default:
                                                                        $statusClass = 'badge-pending';
                                                                }
                                                                
                                                                $orderDate = date("M d, Y", strtotime($row['created_at']));
                                                                
                                                                // Parse products (assuming it's stored as JSON)
                                                                $products = json_decode($row['products'], true);
                                                                ?>
                                                                <tr>
                                                                    <td>
                                                                        <input type="checkbox" name="selected_orders[]" 
                                                                               value="<?= htmlspecialchars($row['order_id']) ?>" 
                                                                               class="form-check-input order-checkbox">
                                                                    </td>
                                                                    <td data-label="Action">
                                                                        <div class="d-flex">
                                                                            <!-- Generate Invoice -->
                                                                            <a href="generate_invoice.php?order_id=<?= htmlspecialchars($row['order_id']) ?>" 
                                                                               class="action-btn" data-bs-toggle="tooltip" 
                                                                               title="Generate Invoice">
                                                                                <i class="fas fa-file-invoice-dollar text-success"></i>
                                                                            </a>
                                                                            
                                                                            <!-- Edit Icon -->
                                                                            <a href="edit_order.php?id=<?= htmlspecialchars($row['id']) ?>" 
                                                                               class="action-btn" 
                                                                               title="Edit Order">
                                                                                <i class="fas fa-edit text-primary"></i>
                                                                            </a>
                                                                            
                                                                            <!-- Delete Icon -->
                                                                            <a href="delete_order.php?id=<?= htmlspecialchars($row['order_id']) ?>" 
                                                                               class="action-btn" 
                                                                               title="Delete Order"
                                                                               onclick="return confirm('Are you sure you want to delete this order?');">
                                                                                <i class="fas fa-trash-alt text-danger"></i>
                                                                            </a>
                                                                        </div>
                                                                    </td>
                                                                    <td data-label="Order ID">
                                                                        <a href="order_details.php?id=<?= htmlspecialchars($row['order_id']) ?>" 
                                                                           class="text-primary fw-bold">
                                                                            <?= htmlspecialchars($row['order_id']) ?>
                                                                        </a>
                                                                    </td>
                                                                    <td data-label="Status">
                                                                        <span class="status-badge <?= $statusClass ?>">
                                                                            <?= ucfirst($row['status']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td data-label="Update Status">
                                                                        <form method="post" action="" class="mb-0">
                                                                            <input type="hidden" name="update_order_id" 
                                                                                   value="<?= htmlspecialchars($row['order_id']) ?>">
                                                                            <select name="update_status" 
                                                                                    class="form-select form-select-sm border-0 shadow-sm" 
                                                                                    onchange="this.form.submit()">
                                                                                <?php
                                                                                $statuses = ['pending', 'processing', 'delivered', 'cancelled'];
                                                                                foreach ($statuses as $status) {
                                                                                    $selected = (strtolower($row['status']) === $status) ? 'selected' : '';
                                                                                    echo "<option value='$status' $selected>" . ucfirst($status) . "</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </form>
                                                                    </td>
                                                                    <td data-label="Products" class="product-list">
                                                                        <?php if (is_array($products)): ?>
                                                                            <?php foreach ($products as $product): ?>
                                                                                <div class="product-item">
                                                                                    <span class="product-name">
                                                                                        <?= htmlspecialchars($product['name'] ?? 'N/A') ?>
                                                                                    </span>
                                                                                    <span class="product-qty">
                                                                                        (Qty: <?= htmlspecialchars($product['quantity'] ?? 1) ?>)
                                                                                    </span>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <?= htmlspecialchars($row['products']) ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td data-label="Customer">
                                                                        <div class="customer-name">
                                                                            <?= htmlspecialchars($row['first_name']) ?>
                                                                        </div>
                                                                    </td>
                                                                    <td data-label="Contact Info">
                                                                        <a href="tel:<?= htmlspecialchars($row['phone']) ?>" 
                                                                           class="customer-email d-block">
                                                                            <?= htmlspecialchars($row['phone']) ?>
                                                                        </a>
                                                                        <a href="mailto:<?= htmlspecialchars($row['email']) ?>" 
                                                                           class="customer-email">
                                                                            <?= htmlspecialchars($row['email']) ?>
                                                                        </a>
                                                                    </td>
                                                                    <td data-label="Location" class="location-cell">
                                                                        <div>
                                                                            <?= htmlspecialchars($row['address']) ?>, 
                                                                            <?= htmlspecialchars($row['city']) ?>,
                                                                            <?= htmlspecialchars($row['state']) ?>,
                                                                            <?= htmlspecialchars($row['postal_code']) ?>
                                                                        </div>
                                                                        <small class="text-muted">
                                                                            <?= htmlspecialchars($row['country']) ?>
                                                                        </small>
                                                                    </td>
                                                                    <td data-label="Payment">
                                                                        <?= ucfirst(htmlspecialchars($row['payment_method'])) ?>
                                                                    </td>
                                                                    <td data-label="Amount" class="amount-cell">
                                                                        Rs. <?= number_format($row['order_total'], 2) ?>
                                                                    </td>
                                                                    <td data-label="Date" class="date-cell">
                                                                        <?= $orderDate ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endwhile; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="12" class="text-center py-5">
                                                                    <div class="d-flex flex-column align-items-center">
                                                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                                        <h5 class="text-muted">No Orders Found</h5>
                                                                        <p class="text-muted">Try adjusting your search or filters</p>
                                                                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-primary mt-2">
                                                                            Reset Filters
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <!-- Pagination would go here -->
                                    <!-- <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-end">
                                            <li class="page-item disabled">
                                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                            </li>
                                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                                            <li class="page-item">
                                                <a class="page-link" href="#">Next</a>
                                            </li>
                                        </ul>
                                    </nav> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize tooltips
        $(document).ready(function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
            
            // Date picker
            flatpickr("input[type=date]", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
            
            // Select all checkbox
            $('#select-all').change(function() {
                $('.order-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Bulk action form validation
            $('#bulk-action-form').submit(function(e) {
                const selectedCount = $('.order-checkbox:checked').length;
                const action = $('select[name="bulk_status"]').val();
                
                if (selectedCount === 0) {
                    alert('Please select at least one order to perform bulk action.');
                    e.preventDefault();
                    return false;
                }
                
                if (!action) {
                    alert('Please select a bulk action to perform.');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Real-time search (optional)
            $('.search-box input').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchTerm) > -1);
                });
            });
        });
    </script>
</body>
</html>