<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/Abha.php';
require_once __DIR__ . '/../config/abdm.php';
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

    $status_sql = "SELECT status FROM users WHERE id = ?";
    $status_stmt = mysqli_prepare($conn, $status_sql);
    mysqli_stmt_bind_param($status_stmt, "i", $customer_id);
    mysqli_stmt_execute($status_stmt);
    mysqli_stmt_bind_result($status_stmt, $current_status);
    mysqli_stmt_fetch($status_stmt);
    mysqli_stmt_close($status_stmt);

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

// Handle ABHA Link from Admin Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_user_abha') {
    $uid = (int)($_POST['customer_id'] ?? 0);
    $abha_raw = preg_replace('/\D/', '', trim($_POST['abha_number'] ?? ''));
    $abha_addr = trim($_POST['abha_address'] ?? '');
    $verified = isset($_POST['mark_verified']) ? 1 : 0;

    if (strlen($abha_raw) !== 14) {
        $_SESSION['error_message'] = "Invalid ABHA Number — must be exactly 14 numeric digits.";
    } else {
        $fmt = substr($abha_raw, 0, 2) . '-' . substr($abha_raw, 2, 4) . '-' . substr($abha_raw, 6, 4) . '-' . substr($abha_raw, 10, 4);
        if ($abha_addr && strpos($abha_addr, '@') === false) {
            $abha_addr .= '@abdm';
        }
        try {
            Abha::save($conn, 'patient', $uid, [
                'abha_number'  => $fmt,
                'abha_address' => $abha_addr,
                'linked'       => 1,
                'verified'     => $verified,
                'source'       => 'admin',
            ]);
            $_SESSION['success_message'] = "ABHA ID {$fmt} successfully linked and verified for patient #{$uid}!";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = "Could not link ABHA: " . $e->getMessage();
        }
    }
    header("Location: all-customers.php");
    exit();
}

// Handle ABHA Unlink
if (isset($_GET['unlink_abha'])) {
    $uid = intval($_GET['unlink_abha']);
    try {
        Abha::unlink($conn, 'patient', $uid);
        $_SESSION['success_message'] = "ABHA ID unlinked from patient #{$uid}.";
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Failed to unlink ABHA.";
    }
    header("Location: all-customers.php");
    exit();
}

// Search and filter functionality
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$verification_filter = $_GET['verification'] ?? '';
$abha_filter = $_GET['abha_filter'] ?? '';

// Build query with filters
$sql = "
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM appointments WHERE user_id = u.id) AS appointment_count,
        (SELECT COUNT(*) FROM lab_bookings WHERE user_id = u.id) AS lab_count,
        (SELECT COUNT(*) FROM pharmacy_orders WHERE user_id = u.id) AS pharmacy_count,
        ar.id AS pending_abha_req_id
    FROM users u
    LEFT JOIN user_abha_requests ar ON ar.user_id = u.id AND ar.status = 'Pending'
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.abha_id LIKE ? OR u.abha_address LIKE ?)";
    $st = "%$search%";
    $params = array_merge($params, [$st, $st, $st, $st, $st, $st]);
    $types .= 'ssssss';
}

if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($verification_filter)) {
    if ($verification_filter === 'verified') {
        $sql .= " AND u.email_verified = 1";
    } elseif ($verification_filter === 'unverified') {
        $sql .= " AND u.email_verified = 0";
    }
}

if (!empty($abha_filter)) {
    if ($abha_filter === 'm1_verified') {
        $sql .= " AND u.abha_linked = 1 AND u.abha_verified = 1";
    } elseif ($abha_filter === 'linked') {
        $sql .= " AND u.abha_linked = 1";
    } elseif ($abha_filter === 'pending') {
        $sql .= " AND ar.id IS NOT NULL";
    } elseif ($abha_filter === 'unlinked') {
        $sql .= " AND (u.abha_linked = 0 OR u.abha_linked IS NULL)";
    }
}

$sql .= " ORDER BY u.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Statistics
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'];
$active_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as active FROM users WHERE status = 'Active'"))['active'];
$verified_abha_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as abha_count FROM users WHERE abha_linked = 1"))['abha_count'];
$pending_abha_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as req_count FROM user_abha_requests WHERE status = 'Pending'"))['req_count'];
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Customer & ABHA Management | Admin Dashboard</title>

    <?php include "links.php"; ?>
    <style>
        .abha-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
        }
        .abha-pill-verified { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .abha-pill-pending  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .abha-pill-none     { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
        
        .care-metric-chip {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 600;
            text-decoration: none;
            margin-right: 3px;
        }
        .care-metric-chip:hover {
            background: var(--primary, #0C74C5);
            color: #fff;
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
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Customer & Patient Management</h4>
                        <small class="text-muted">ABDM Ayushman Bharat integrated patient registry — manage ABHA IDs, clinical history, diagnostics & care</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="abha-management.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-id-card me-1"></i> ABHA Registry
                        </a>
                        <a href="add-customer.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Add New Patient
                        </a>
                    </div>
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

                <!-- Statistics Bar -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="stat-box bg-stat-blue">
                            <i class="fas fa-users big-icon"></i>
                            <div class="num"><?= $total_customers ?></div>
                            <div class="lbl">Total Patients</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="stat-box bg-stat-green">
                            <i class="fas fa-user-check big-icon"></i>
                            <div class="num"><?= $active_customers ?></div>
                            <div class="lbl">Active Accounts</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="stat-box bg-stat-teal">
                            <i class="fas fa-shield-alt big-icon"></i>
                            <div class="num"><?= $verified_abha_customers ?></div>
                            <div class="lbl">ABDM M1 Verified</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="stat-box" style="background:#e07e18;color:#fff;">
                            <i class="fas fa-hourglass-half big-icon"></i>
                            <div class="num"><?= $pending_abha_requests ?></div>
                            <div class="lbl">Pending ABHA Requests</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card mb-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Search Patient or ABHA</label>
                            <input type="text" class="form-control form-control-sm" name="search" placeholder="Name, mobile, email, ABHA ID or @address..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">ABDM / ABHA</label>
                            <select class="form-select form-select-sm" name="abha_filter">
                                <option value="">All ABHA Status</option>
                                <option value="m1_verified" <?= $abha_filter === 'm1_verified' ? 'selected' : '' ?>>ABDM M1 Verified</option>
                                <option value="linked" <?= $abha_filter === 'linked' ? 'selected' : '' ?>>Linked ABHA</option>
                                <option value="pending" <?= $abha_filter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                <option value="unlinked" <?= $abha_filter === 'unlinked' ? 'selected' : '' ?>>Unlinked</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">Account Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">All Status</option>
                                <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="Blocked" <?= $status_filter === 'Blocked' ? 'selected' : '' ?>>Blocked</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">Email Verified</label>
                            <select class="form-select form-select-sm" name="verification">
                                <option value="">All</option>
                                <option value="verified" <?= $verification_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                                <option value="unverified" <?= $verification_filter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-search me-1"></i>Filter</button>
                            <?php if ($search || $status_filter || $verification_filter || $abha_filter): ?>
                                <a href="all-customers.php" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="fas fa-undo"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Customers Table -->
                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title">
                                <h3 class="m-0">Registered Patients & ABHA Registry <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($result) ?></span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Patient Profile</th>
                                        <th>ABDM / ABHA Identity</th>
                                        <th>Contact Details</th>
                                        <th>Care Services</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th class="text-end">Actions & Controls</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) === 0): ?>
                                        <tr class="empty-row"><td colspan="8">
                                            <i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>No patients found matching your search criteria.
                                        </td></tr>
                                    <?php endif; ?>
                                    <?php
                                    $no = 1;
                                    while ($row = mysqli_fetch_assoc($result)):
                                        $profile_pic = !empty($row['profile_pic']) ? BASE_URL . 'assets/img/' . $row['profile_pic'] : '';
                                        $status_pill = ['Active' => 'pill-success', 'Inactive' => 'pill-danger'][$row['status']] ?? 'pill-warn';
                                        $has_abha = !empty($row['abha_linked']);
                                        $abha_formatted = Abha::formatNumber($row['abha_id'] ?? '');
                                    ?>
                                    <tr>
                                        <td><span class="cell-sub"><?= $no++ ?></span></td>
                                        
                                        <!-- Patient Info -->
                                        <td data-label="Patient">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($profile_pic): ?>
                                                    <img src="<?= htmlspecialchars($profile_pic) ?>" class="rounded-circle flex-shrink-0" style="width:40px;height:40px;object-fit:cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;color:var(--adm-primary);font-weight:700;font-size:.85rem;border:1px solid #e2e8f0;">
                                                        <?= strtoupper(substr($row['name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="view-customer.php?id=<?= $row['id'] ?>" class="cell-title text-decoration-none fw-bold text-dark">
                                                        <?= htmlspecialchars(trim($row['name'] . ' ' . $row['last_name'])) ?>
                                                    </a>
                                                    <div class="cell-sub">
                                                        ID #<?= $row['id'] ?>
                                                        <?php if (!empty($row['gender'])): ?> • <?= htmlspecialchars($row['gender']) ?><?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- ABHA Status -->
                                        <td data-label="ABHA Identity">
                                            <?php if ($has_abha && !empty($row['abha_id'])): ?>
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <span class="abha-badge-pill abha-pill-verified">
                                                        <i class="fas fa-shield-alt"></i> ABDM M1
                                                    </span>
                                                    <span class="fw-bold text-dark font-monospace small"><?= htmlspecialchars($abha_formatted) ?></span>
                                                </div>
                                                <?php if (!empty($row['abha_address'])): ?>
                                                    <div class="small text-muted"><i class="fas fa-at me-1"></i><?= htmlspecialchars($row['abha_address']) ?></div>
                                                <?php endif; ?>
                                                <div class="mt-1">
                                                    <a href="<?= BASE_URL ?>ajax/abdm-api.php?action=get_card_file&user_id=<?= $row['id'] ?>" target="_blank" class="small text-primary text-decoration-none fw-bold" title="Download Official ABDM ABHA Card">
                                                        <i class="fas fa-download me-1"></i> ABHA Card
                                                    </a>
                                                </div>
                                            <?php elseif (!empty($row['pending_abha_req_id'])): ?>
                                                <a href="abha-management.php?tab=requests" class="abha-badge-pill abha-pill-pending text-decoration-none" title="Click to review request">
                                                    <i class="fas fa-clock"></i> Verification Pending
                                                </a>
                                                <div class="small text-muted mt-1">Request #<?= $row['pending_abha_req_id'] ?></div>
                                            <?php else: ?>
                                                <span class="abha-badge-pill abha-pill-none">
                                                    <i class="fas fa-id-card"></i> Not Linked
                                                </span>
                                                <div class="mt-1">
                                                    <button type="button" class="btn btn-link p-0 small text-decoration-none" style="font-size:.72rem;" onclick="openLinkModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars($row['mobile']) ?>')">
                                                        + Link ABHA
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Contact -->
                                        <td data-label="Contact">
                                            <div class="cell-title fw-semibold"><i class="fas fa-phone-alt me-1 text-muted small"></i><?= htmlspecialchars($row['mobile']) ?></div>
                                            <div class="cell-sub" style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                <i class="fas fa-envelope me-1 text-muted small"></i><?= htmlspecialchars($row['email']) ?>
                                            </div>
                                            <?php if (!empty($row['city'])): ?>
                                                <div class="cell-sub"><i class="fas fa-map-marker-alt me-1 text-muted small"></i><?= htmlspecialchars($row['city']) ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Care Services -->
                                        <td data-label="Care Services">
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="appointments.php?search=<?= urlencode($row['mobile']) ?>" class="care-metric-chip" title="Consultations / OPD">
                                                    <i class="fas fa-stethoscope me-1 text-primary"></i><?= (int)$row['appointment_count'] ?> Appts
                                                </a>
                                                <a href="lab-bookings.php?search=<?= urlencode($row['mobile']) ?>" class="care-metric-chip" title="Pathology & Lab Tests">
                                                    <i class="fas fa-flask me-1 text-info"></i><?= (int)$row['lab_count'] ?> Labs
                                                </a>
                                                <a href="pharmacy-orders.php?search=<?= urlencode($row['mobile']) ?>" class="care-metric-chip" title="Medicine Orders">
                                                    <i class="fas fa-pills me-1 text-success"></i><?= (int)$row['pharmacy_count'] ?> Meds
                                                </a>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td data-label="Status">
                                            <a href="?toggle_status=<?= $row['id'] ?>" class="pill <?= $status_pill ?>" style="cursor:pointer;"
                                                onclick="return confirm('Change status to <?= $row['status'] === 'Active' ? 'Inactive' : 'Active' ?>?')">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </a>
                                        </td>

                                        <!-- Joined -->
                                        <td data-label="Joined">
                                            <span class="cell-sub"><?= date('d M Y', strtotime($row['created_at'])) ?></span>
                                        </td>

                                        <!-- Actions -->
                                        <td data-label="Actions" class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                <a href="view-customer.php?id=<?= $row['id'] ?>" class="tbl-action-btn bg-primary text-white" title="View Customer 360° Profile & ABDM PHR">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <?php if ($has_abha): ?>
                                                    <a href="<?= BASE_URL ?>ajax/abdm-api.php?action=get_card_file&user_id=<?= $row['id'] ?>" target="_blank" class="tbl-action-btn text-white" style="background:#02c9b8;" title="Download ABDM Official ABHA Card">
                                                        <i class="fas fa-id-card"></i>
                                                    </a>
                                                    <a href="?unlink_abha=<?= $row['id'] ?>" class="tbl-action-btn bg-warning text-dark" title="Unlink ABHA" onclick="return confirm('Unlink ABHA from this patient?');">
                                                        <i class="fas fa-unlink"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="tbl-action-btn bg-info text-white border-0" title="Link ABHA ID" onclick="openLinkModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars($row['mobile']) ?>')">
                                                        <i class="fas fa-link"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <a href="edit-customer.php?id=<?= $row['id'] ?>" class="tbl-action-btn bg-secondary text-white" title="Edit Patient Details">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?= $row['id'] ?>" class="tbl-action-btn bg-danger text-white" title="Delete Patient" onclick="return confirm('Are you sure you want to delete this patient account?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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

        <!-- Modal: Quick Link ABHA -->
        <div class="modal fade" id="linkAbhaModal" tabindex="-1" aria-labelledby="linkAbhaModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="all-customers.php" class="modal-content">
                    <input type="hidden" name="action" value="link_user_abha">
                    <input type="hidden" name="customer_id" id="modalCustomerId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="linkAbhaModalLabel"><i class="fas fa-id-card me-2 text-primary"></i>Link ABHA to Patient</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Patient Name</label>
                            <input type="text" class="form-control" id="modalCustomerName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">14-Digit ABHA Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" name="abha_number" id="modalAbhaNumber" placeholder="91-XXXX-XXXX-XXXX" maxlength="17" required>
                            <small class="text-muted">Enter 14 digits with or without hyphens</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ABHA Address / PHR Address</label>
                            <input type="text" class="form-control" name="abha_address" placeholder="username@abdm or username@sbx">
                            <small class="text-muted">Optional: Government PHR address</small>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="mark_verified" id="markVerified" value="1" checked>
                            <label class="form-check-label fw-bold" for="markVerified">
                                Mark as ABDM M1 Verified
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> Save & Link ABHA</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script>
            function openLinkModal(id, name, mobile) {
                document.getElementById('modalCustomerId').value = id;
                document.getElementById('modalCustomerName').value = name + ' (' + mobile + ')';
                var modal = new bootstrap.Modal(document.getElementById('linkAbhaModal'));
                modal.show();
            }

            // Auto-format 14-digit ABHA input
            document.getElementById('modalAbhaNumber').addEventListener('input', function(e) {
                var v = this.value.replace(/\D/g, '').slice(0, 14);
                if (v.length > 10) {
                    this.value = v.slice(0, 2) + '-' + v.slice(2, 6) + '-' + v.slice(6, 10) + '-' + v.slice(10, 14);
                } else if (v.length > 6) {
                    this.value = v.slice(0, 2) + '-' + v.slice(2, 6) + '-' + v.slice(6);
                } else if (v.length > 2) {
                    this.value = v.slice(0, 2) + '-' + v.slice(2);
                } else {
                    this.value = v;
                }
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(a) {
                    var bsAlert = new bootstrap.Alert(a);
                    bsAlert.close();
                });
            }, 5000);
        </script>
</body>
</html>