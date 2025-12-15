<?php
session_start();
include "db-conn.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

// Handle customer deletion
if (isset($_GET['delete'])) {
    $customer_id = intval($_GET['delete']);

    // Verify customer exists
    $check_sql = "SELECT id FROM users WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $customer_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);

    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        // Delete customer
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $customer_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            $_SESSION['success_message'] = "Customer deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete customer.";
        }
        mysqli_stmt_close($delete_stmt);
    } else {
        $_SESSION['error_message'] = "Customer not found.";
    }
    mysqli_stmt_close($check_stmt);

    header("Location: customer-management.php");
    exit();
}

// Handle status update
if (isset($_GET['toggle_status'])) {
    $customer_id = intval($_GET['toggle_status']);

    // Get current status
    $status_sql = "SELECT status FROM users WHERE id = ?";
    $status_stmt = mysqli_prepare($conn, $status_sql);
    mysqli_stmt_bind_param($status_stmt, "i", $customer_id);
    mysqli_stmt_execute($status_stmt);
    mysqli_stmt_bind_result($status_stmt, $current_status);
    mysqli_stmt_fetch($status_stmt);
    mysqli_stmt_close($status_stmt);

    // Toggle status
    $new_status = ($current_status === 'Active') ? 'Inactive' : 'Active';

    $update_sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $customer_id);

    if (mysqli_stmt_execute($update_stmt)) {
        $_SESSION['success_message'] = "Customer status updated to {$new_status}!";
    } else {
        $_SESSION['error_message'] = "Failed to update customer status.";
    }
    mysqli_stmt_close($update_stmt);

    header("Location: customer-management.php");
    exit();
}

// Handle email verification
if (isset($_GET['verify_email'])) {
    $customer_id = intval($_GET['verify_email']);

    $verify_sql = "UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "i", $customer_id);

    if (mysqli_stmt_execute($verify_stmt)) {
        $_SESSION['success_message'] = "Email verified successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to verify email.";
    }
    mysqli_stmt_close($verify_stmt);

    header("Location: customer-management.php");
    exit();
}

// Search and filter functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$verification_filter = $_GET['verification'] ?? '';

// Build query with filters
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($verification_filter)) {
    if ($verification_filter === 'verified') {
        $sql .= " AND email_verified = 1";
    } elseif ($verification_filter === 'unverified') {
        $sql .= " AND email_verified = 0";
    }
}

$sql .= " ORDER BY created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get total customers count for stats
$total_customers_sql = "SELECT COUNT(*) as total FROM users";
$active_customers_sql = "SELECT COUNT(*) as active FROM users WHERE status = 'Active'";
$verified_customers_sql = "SELECT COUNT(*) as verified FROM users WHERE email_verified = 1";

$total_result = mysqli_query($conn, $total_customers_sql);
$active_result = mysqli_query($conn, $active_customers_sql);
$verified_result = mysqli_query($conn, $verified_customers_sql);

$total_customers = mysqli_fetch_assoc($total_result)['total'];
$active_customers = mysqli_fetch_assoc($active_result)['active'];
$verified_customers = mysqli_fetch_assoc($verified_result)['verified'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Customer Management | Admin Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    <style>
        .customer-table {
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(145deg, #98c5ffff, #d3e8fdff);
            /* Soft medical blue/white */
            border: 1px solid #278ff7ff;
            color: black;
            border-radius: 25px 0 0 25px;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .table td {
            vertical-align: middle;
        }

        .action-icon {
            transition: all 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .action-icon:hover {
            transform: scale(1.1);
            background: rgba(0, 0, 0, 0.05);
        }

        .page-title {
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }

        .page-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }

        .badge-active {
            background-color: #e6f7ee;
            color: #28a745;
        }

        .badge-inactive {
            background-color: #fef0f0;
            color: #dc3545;
        }

        .badge-blocked {
            background-color: #fff3cd;
            color: #856404;
        }

        .verification-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-verified {
            background-color: #e6f7ee;
            color: #28a745;
        }

        .badge-unverified {
            background-color: #fff3cd;
            color: #856404;
        }

        .stats-card {
            background: linear-gradient(145deg, #98c5ffff, #d3e8fdff);
            /* Soft medical blue/white */
            border: 1px solid #278ff7ff;
            color: black;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }

        .action-dropdown {
            min-width: 150px;
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

        <div class="main_content_iner">
            <div class="container-fluid p-0">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?= $total_customers ?></div>
                            <div class="stats-label">Total Customers</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?= $active_customers ?></div>
                            <div class="stats-label">Active Customers</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?= $verified_customers ?></div>
                            <div class="stats-label">Verified Customers</div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" name="status">
                                    <option value="">All Status</option>
                                    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="Blocked" <?= $status_filter === 'Blocked' ? 'selected' : '' ?>>Blocked</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" name="verification">
                                    <option value="">All Verification</option>
                                    <option value="verified" <?= $verification_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                                    <option value="unverified" <?= $verification_filter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="row align-items-center justify-content-between flex-wrap">
                                    <div class="col-lg-4">
                                        <h2 class="page-title">Customer Management</h2>
                                    </div>
                                    <div class="col-lg-4 text-lg-end">
                                        <a href="add-customer.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add New Customer
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="table-responsive customer-table">
                                    <table class="table table-hover">
                                        <thead class="table-header">
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Customer</th>
                                                <th scope="col">Contact</th>
                                                <th scope="col">Email</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Verification</th>
                                                <th scope="col">Joined</th>
                                                <th scope="col" class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            if (mysqli_num_rows($result) > 0) {
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $profile_pic = !empty($row['profile_pic'])
                                                        ? $site . 'assets/img/' . $row['profile_pic']
                                                        : '';

                                            ?>
                                                    <tr>
                                                        <th scope="row"><?= $no++ ?></th>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="me-3">
                                                                    <?php if (!empty($profile_pic)) { ?>
                                                                        <img src="<?= $profile_pic ?>" class="img-fluid rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                                                                    <?php } else { ?>
                                                                        <i class="fas fa-user-circle" style="font-size: 80px; color: #ccc;"></i>
                                                                    <?php } ?>

                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0"><?= htmlspecialchars($row['name'] . ' ' . $row['last_name']) ?></h6>
                                                                    <small class="text-muted">ID: #<?= $row['id'] ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div><?= $row['mobile'] ?></div>
                                                            <?php if (!empty($row['emergency_contact'])): ?>
                                                                <small class="text-muted">Emergency: <?= $row['emergency_contact'] ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div><?= $row['email'] ?></div>
                                                            <small class="text-muted">
                                                                Last Login: <?= !empty($row['last_login']) ? date('M j, Y', strtotime($row['last_login'])) : 'Never' ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <a href="?toggle_status=<?= $row['id'] ?>"
                                                                class="status-badge <?= 'badge-' . strtolower($row['status']) ?>"
                                                                onclick="return confirm('Change status to <?= $row['status'] === 'Active' ? 'Inactive' : 'Active' ?>?')">
                                                                <?= $row['status'] ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?php if ($row['email_verified']): ?>
                                                                <span class="verification-badge badge-verified">
                                                                    <i class="fas fa-check-circle me-1"></i>Verified
                                                                </span>
                                                            <?php else: ?>
                                                                <a href="?verify_email=<?= $row['id'] ?>"
                                                                    class="verification-badge badge-unverified"
                                                                    onclick="return confirm('Mark email as verified?')">
                                                                    <i class="fas fa-times-circle me-1"></i>Unverified
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                                            <small class="text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></small>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                                    type="button"
                                                                    data-bs-toggle="dropdown"
                                                                    aria-expanded="false">
                                                                    Actions
                                                                </button>
                                                                <ul class="dropdown-menu action-dropdown">
                                                                    <li>
                                                                        <a class="dropdown-item" href="view-customer.php?id=<?= $row['id'] ?>">
                                                                            <i class="fas fa-eye me-2"></i>View Details
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="edit-customer.php?id=<?= $row['id'] ?>">
                                                                            <i class="fas fa-edit me-2"></i>Edit Customer
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <hr class="dropdown-divider">
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger"
                                                                            href="?delete=<?= $row['id'] ?>"
                                                                            onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                                                            <i class="fas fa-trash me-2"></i>Delete Customer
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-5">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <img src="assets/img/no-data.svg" alt="No data" style="width: 120px; opacity: 0.7;">
                                                            <h5 class="mt-3 text-muted">No customers found</h5>
                                                            <p class="text-muted">Try adjusting your search filters</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php
                                            }
                                            // mysqli_stmt_close($stmt);
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script>
            // Initialize tooltips
            $(document).ready(function() {
                $('[data-bs-toggle="tooltip"]').tooltip();
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        </script>
</body>

</html>