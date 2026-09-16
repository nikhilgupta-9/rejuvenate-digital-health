<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

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

    header("Location: all-customers.php");
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

    header("Location: all-customers.php");
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

    header("Location: all-customers.php");
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

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Customer Management | Admin Dashboard</title>

    <?php include "links.php"; ?>
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
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Customer Management</h4>
                        <small class="text-muted">All registered patient accounts — status, verification and records</small>
                    </div>
                    <a href="add-customer.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Add New Customer
                    </a>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i><?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-4"><div class="stat-box bg-stat-blue"><i class="fas fa-users big-icon"></i><div class="num"><?= $total_customers ?></div><div class="lbl">Total Customers</div></div></div>
                    <div class="col-6 col-lg-4"><div class="stat-box bg-stat-green"><i class="fas fa-user-check big-icon"></i><div class="num"><?= $active_customers ?></div><div class="lbl">Active Customers</div></div></div>
                    <div class="col-6 col-lg-4"><div class="stat-box bg-stat-teal"><i class="fas fa-shield-check big-icon"></i><div class="num"><?= $verified_customers ?></div><div class="lbl">Verified Customers</div></div></div>
                </div>

                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-6 col-lg-5">
                            <label class="form-label mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" name="search" placeholder="Name, email or mobile..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">All Status</option>
                                <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="Blocked" <?= $status_filter === 'Blocked' ? 'selected' : '' ?>>Blocked</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label mb-1">Verification</label>
                            <select class="form-select form-select-sm" name="verification">
                                <option value="">All</option>
                                <option value="verified" <?= $verification_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                                <option value="unverified" <?= $verification_filter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title"><h3 class="m-0">Customers <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($result) ?></span></h3></div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Verification</th>
                                        <th>Joined</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) === 0): ?>
                                        <tr class="empty-row"><td colspan="8">
                                            <i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>No customers found. Try adjusting your search filters.
                                        </td></tr>
                                    <?php endif; ?>
                                    <?php
                                    $no = 1;
                                    while ($row = mysqli_fetch_assoc($result)):
                                        $profile_pic = !empty($row['profile_pic']) ? BASE_URL . 'assets/img/' . $row['profile_pic'] : '';
                                        $status_pill = ['Active' => 'pill-success', 'Inactive' => 'pill-danger'][$row['status']] ?? 'pill-warn';
                                    ?>
                                    <tr>
                                        <td><span class="cell-sub"><?= $no++ ?></span></td>
                                        <td data-label="Customer">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($profile_pic): ?>
                                                    <img src="<?= htmlspecialchars($profile_pic) ?>" class="rounded-circle flex-shrink-0" style="width:38px;height:38px;object-fit:cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;color:var(--adm-primary);font-weight:700;font-size:.8rem;"><?= strtoupper(substr($row['name'], 0, 1)) ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="cell-title"><?= htmlspecialchars(trim($row['name'] . ' ' . $row['last_name'])) ?></div>
                                                    <div class="cell-sub">ID #<?= $row['id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Contact">
                                            <div class="cell-title" style="font-weight:500;"><?= htmlspecialchars($row['mobile']) ?></div>
                                            <?php if (!empty($row['emergency_contact'])): ?><div class="cell-sub">Emergency: <?= htmlspecialchars($row['emergency_contact']) ?></div><?php endif; ?>
                                        </td>
                                        <td data-label="Email">
                                            <div class="cell-title" style="font-weight:500;"><?= htmlspecialchars($row['email']) ?></div>
                                            <div class="cell-sub">Last login: <?= !empty($row['last_login']) ? date('d M Y', strtotime($row['last_login'])) : 'Never' ?></div>
                                        </td>
                                        <td data-label="Status">
                                            <a href="?toggle_status=<?= $row['id'] ?>" class="pill <?= $status_pill ?>" style="cursor:pointer;"
                                                onclick="return confirm('Change status to <?= $row['status'] === 'Active' ? 'Inactive' : 'Active' ?>?')"><?= htmlspecialchars($row['status']) ?></a>
                                        </td>
                                        <td data-label="Verification">
                                            <?php if ($row['email_verified']): ?>
                                                <span class="pill pill-success"><i class="fas fa-check-circle"></i>Verified</span>
                                            <?php else: ?>
                                                <a href="?verify_email=<?= $row['id'] ?>" class="pill pill-warn" style="cursor:pointer;" onclick="return confirm('Mark email as verified?')"><i class="fas fa-times-circle"></i>Unverified</a>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Joined"><span class="cell-sub"><?= date('d M Y', strtotime($row['created_at'])) ?> &middot; <?= date('h:i A', strtotime($row['created_at'])) ?></span></td>
                                        <td data-label="Actions">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                <a href="view-customer.php?id=<?= $row['id'] ?>" class="tbl-action-btn bg-primary text-white" title="View Details"><i class="fas fa-eye"></i></a>
                                                <a href="edit-customer.php?id=<?= $row['id'] ?>" class="tbl-action-btn bg-info text-white" title="Edit Customer"><i class="fas fa-edit"></i></a>
                                                <a href="upload-medical-record.php?for=patient&patient_id=<?= $row['id'] ?>" class="tbl-action-btn bg-success text-white" title="Upload Medical Record"><i class="fas fa-file-medical"></i></a>
                                                <a href="medical-records.php?tab=patients&q=<?= urlencode($row['name']) ?>" class="tbl-action-btn bg-secondary text-white" title="View Medical Records"><i class="fas fa-folder-open"></i></a>
                                                <a href="?delete=<?= $row['id'] ?>" class="tbl-action-btn bg-danger text-white" title="Delete Customer" onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include "footer.php"; ?>

        <script>
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        </script>
</body>

</html>