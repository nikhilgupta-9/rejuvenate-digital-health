<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once "db-conn.php";
include_once "functions.php";

// Handle delete action
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        $stmt = $conn->prepare("DELETE FROM doctors WHERE id = ?");
        $stmt->bind_param('i', $delete_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Doctor deleted successfully!";
        } else {
            throw new Exception("Failed to delete doctor");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: doctors-list.php");
    exit();
}

// Handle verification actions
if (isset($_GET['verify_id'])) {
    try {
        $verify_id = intval($_GET['verify_id']);
        $admin_id = $_SESSION['admin_id'] ?? 1;

        // First get doctor details before updating
        $get_doctor_sql = "SELECT email, name FROM doctors WHERE id = ?";
        $get_stmt = $conn->prepare($get_doctor_sql);
        $get_stmt->bind_param('i', $verify_id);
        $get_stmt->execute();
        $doctor_result = $get_stmt->get_result();
        
        if ($doctor_result->num_rows === 1) {
            $doctor = $doctor_result->fetch_assoc();
            $doctor_email = $doctor['email'];
            $doctor_name = $doctor['name'];
            
            // Get admin name who is verifying
            $admin_sql = "SELECT username FROM admin_user WHERE id = ?";
            $admin_stmt = $conn->prepare($admin_sql);
            $admin_stmt->bind_param('i', $admin_id);
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            $admin_data = $admin_result->fetch_assoc();
            $verified_by = $admin_data ? $admin_data['username'] : 'Administrator';
            
            // Update doctor verification status
            $update_sql = "UPDATE doctors SET is_verified = 1, verified_at = NOW(), verified_by = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('ii', $admin_id, $verify_id);

            if ($update_stmt->execute()) {
                // Send verification email using your existing email infrastructure
                $mailSent = send_doctor_verification_email($doctor_email, $doctor_name, $verified_by);
                
                if ($mailSent) {
                    $_SESSION['success_message'] = "Doctor verified successfully! Verification email sent to Dr. $doctor_name.";
                } else {
                    $_SESSION['success_message'] = "Doctor verified successfully! <small class='text-warning'>(Verification email failed to send)</small>";
                }
            } else {
                throw new Exception("Failed to verify doctor");
            }
        } else {
            throw new Exception("Doctor not found");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: doctors-list.php");
    exit();
}

if (isset($_GET['unverify_id'])) {
    try {
        $unverify_id = intval($_GET['unverify_id']);

        $stmt = $conn->prepare("UPDATE doctors SET is_verified = 0, verified_at = NULL, verified_by = NULL WHERE id = ?");
        $stmt->bind_param('i', $unverify_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Doctor verification removed successfully!";
        } else {
            throw new Exception("Failed to remove verification");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: doctors-list.php");
    exit();
}

// Handle type filter
$type_filter = $_GET['type'] ?? 'all';
$where_conditions = [];
$params = [];
$types = '';

if ($type_filter === 'verified') {
    $where_conditions[] = "is_verified = 1";
} elseif ($type_filter === 'unverified') {
    $where_conditions[] = "is_verified = 0";
} elseif ($type_filter === 'active') {
    $where_conditions[] = "d.status = 'Active'";
} elseif ($type_filter === 'inactive') {
    $where_conditions[] = "d.status = 'Inactive'";
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Fetch doctors
$sql = "SELECT d.*, a.username as verified_by_name 
        FROM doctors d 
        LEFT JOIN admin_user a ON d.verified_by = a.id 
        $where_sql
        ORDER BY d.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$doctors_list = $result->fetch_all(MYSQLI_ASSOC);

// Summary counts (unfiltered)
$dc_total    = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM doctors"))['c'];
$dc_verified = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM doctors WHERE is_verified = 1"))['c'];
$dc_pending  = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM doctors WHERE is_verified = 0"))['c'];
$dc_active   = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM doctors WHERE status = 'Active'"))['c'];

// Get message from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Doctors List | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        /* page-specific only */
        .filter-buttons { display:flex; gap:8px; flex-wrap:wrap; }
        .doc-avatar {
            width:38px; height:38px; border-radius:9px; object-fit:cover; flex-shrink:0;
            background:#eef1f6; display:flex; align-items:center; justify-content:center;
        }
        .doc-avatar i { color:#9aa0b4; }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part">
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
                        <h4 class="mb-0 fw-bold">Doctors List</h4>
                        <small class="text-muted">Manage doctor profiles, verification and availability</small>
                    </div>
                    <a href="add-doctor.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Add New Doctor
                    </a>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-user-md big-icon"></i><div class="num"><?= $dc_total ?></div><div class="lbl">Total Doctors</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-double big-icon"></i><div class="num"><?= $dc_verified ?></div><div class="lbl">Verified</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-clock big-icon"></i><div class="num"><?= $dc_pending ?></div><div class="lbl">Pending Verification</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-user-check big-icon"></i><div class="num"><?= $dc_active ?></div><div class="lbl">Active</div></div></div>
                </div>

                <div class="filter-card">
                    <div class="filter-buttons">
                        <a href="doctors-list.php?type=all" class="filter-btn <?= $type_filter === 'all' ? 'active' : '' ?>">All Doctors</a>
                        <a href="doctors-list.php?type=verified" class="filter-btn <?= $type_filter === 'verified' ? 'active' : '' ?>">Verified</a>
                        <a href="doctors-list.php?type=unverified" class="filter-btn <?= $type_filter === 'unverified' ? 'active' : '' ?>">Unverified</a>
                        <a href="doctors-list.php?type=active" class="filter-btn <?= $type_filter === 'active' ? 'active' : '' ?>">Active</a>
                        <a href="doctors-list.php?type=inactive" class="filter-btn <?= $type_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                    </div>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title"><h3 class="m-0">Doctors <span class="badge bg-secondary ms-2"><?= count($doctors_list) ?></span></h3></div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Specialization</th>
                                        <th>Experience</th>
                                        <th>Fee</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Verification</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($doctors_list)): ?>
                                        <tr class="empty-row"><td colspan="8">
                                            <i class="fas fa-user-md fa-3x mb-3 d-block opacity-25"></i>No doctors found.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($doctors_list as $doctor): ?>
                                            <tr>
                                                <td data-label="Doctor">
                                                    <a href="doctor-edit.php?id=<?= $doctor['id'] ?>" class="text-decoration-none d-flex align-items-center" style="gap:10px;color:inherit;">
                                                        <?php if (!empty($doctor['profile_image'])): ?>
                                                            <img src="<?= BASE_URL . "admin/" . htmlspecialchars($doctor['profile_image']) ?>" alt="" class="doc-avatar"
                                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                            <div class="doc-avatar" style="display:none;"><i class="fas fa-user-md"></i></div>
                                                        <?php else: ?>
                                                            <div class="doc-avatar"><i class="fas fa-user-md"></i></div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="cell-title"><?= htmlspecialchars($doctor['name']) ?></div>
                                                            <div class="cell-sub"><?= htmlspecialchars($doctor['doctor_uid']) ?></div>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td data-label="Specialization"><?= htmlspecialchars($doctor['specialization'] ?: '—') ?></td>
                                                <td data-label="Experience"><?= (int) $doctor['experience_years'] ?> yrs</td>
                                                <td data-label="Fee">₹<?= $doctor['consultation_fee'] !== null ? number_format($doctor['consultation_fee'], 2) : '0.00' ?></td>
                                                <td data-label="Rating">
                                                    <span class="pill pill-warn"><i class="fas fa-star"></i><?= $doctor['rating'] ?: '0' ?></span>
                                                </td>
                                                <td data-label="Status">
                                                    <span class="pill <?= $doctor['status'] == 'Active' ? 'pill-success' : 'pill-muted' ?>"><?= htmlspecialchars($doctor['status']) ?></span>
                                                </td>
                                                <td data-label="Verification">
                                                    <?php if ($doctor['is_verified']): ?>
                                                        <span class="pill pill-success"><i class="fas fa-check-circle"></i>Verified</span>
                                                        <?php if ($doctor['verified_at']): ?><div class="cell-sub mt-1"><?= date('M j, Y', strtotime($doctor['verified_at'])) ?></div><?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="pill pill-warn"><i class="fas fa-clock"></i>Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Actions">
                                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                        <a href="doctor-edit.php?id=<?= $doctor['id'] ?>" class="tbl-action-btn bg-primary text-white" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <?php if ($doctor['is_verified']): ?>
                                                            <a href="doctors-list.php?unverify_id=<?= $doctor['id'] ?>" class="tbl-action-btn bg-warning text-dark" onclick="return confirm('Remove verification for this doctor?')" title="Unverify"><i class="fas fa-times-circle"></i></a>
                                                        <?php else: ?>
                                                            <a href="doctors-list.php?verify_id=<?= $doctor['id'] ?>" class="tbl-action-btn bg-success text-white" onclick="return confirm('Verify this doctor?')" title="Verify"><i class="fas fa-check-circle"></i></a>
                                                        <?php endif; ?>
                                                        <a href="doctors-list.php?delete_id=<?= $doctor['id'] ?>" class="tbl-action-btn bg-danger text-white" onclick="return confirm('Are you sure you want to delete this doctor?')" title="Delete"><i class="fas fa-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include "footer.php"; ?>
</body>

</html>