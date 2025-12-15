<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db-conn.php";
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
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .badge-active {
            background-color: #28a745;
        }

        .badge-inactive {
            background-color: #dc3545;
        }

        .badge-verified {
            background-color: #28a745;
            color: white;
        }

        .badge-unverified {
            background-color: #6c757d;
            color: white;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background-color: #7367f0;
            color: white;
            border-color: #7367f0;
        }

        .filter-btn:hover {
            background-color: #f8f9fa;
        }
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
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30 p-4">
                            <div class="white_card_header">
                                <div class="page-header mb-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h2 class="mb-0">Doctors List</h2>
                                        <a href="add-doctor.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i> Add New Doctor
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages -->
                            <?php if (!empty($success_message)): ?>
                                <div class="col-12">
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $success_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($error_message)): ?>
                                <div class="col-12">
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $error_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Filter Buttons -->
                            <div class="col-12 mb-4">
                                <div class="filter-buttons">
                                    <a href="doctors-list.php?type=all" class="filter-btn <?= $type_filter === 'all' ? 'active' : '' ?>">All Doctors</a>
                                    <a href="doctors-list.php?type=verified" class="filter-btn <?= $type_filter === 'verified' ? 'active' : '' ?>">Verified</a>
                                    <a href="doctors-list.php?type=unverified" class="filter-btn <?= $type_filter === 'unverified' ? 'active' : '' ?>">Unverified</a>
                                    <a href="doctors-list.php?type=active" class="filter-btn <?= $type_filter === 'active' ? 'active' : '' ?>">Active</a>
                                    <a href="doctors-list.php?type=inactive" class="filter-btn <?= $type_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                                </div>
                            </div>

                            <!-- Doctors List -->
                            <div class="col-lg-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Profile</th>
                                                        <th>Name</th>
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
                                                        <tr>
                                                            <td colspan="10" class="text-center text-muted py-4">
                                                                No doctors found.
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($doctors_list as $doctor): ?>
                                                            <tr>
                                                                <td><?= $doctor['doctor_uid'] ?></td>
                                                                <td>
                                                                    <?php if (!empty($doctor['profile_image'])): ?>
                                                                        <img src="<?=$site ."admin/". $doctor['profile_image'] ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                                    <?php else: ?>
                                                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                                                            <i class="fas fa-user-md text-muted"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?= htmlspecialchars($doctor['name']) ?></td>
                                                                <td><?= htmlspecialchars($doctor['specialization']) ?></td>
                                                                <td><?= $doctor['experience_years'] ?> years</td>
                                                                <td>₹<?= $doctor['consultation_fee'] !== null
                                                                            ? number_format($doctor['consultation_fee'], 2)
                                                                            : '00.00' ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-warning text-dark">
                                                                        <i class="fas fa-star me-1"></i><?= $doctor['rating'] ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge <?= $doctor['status'] == 'Active' ? 'badge-active' : 'badge-inactive' ?>">
                                                                        <?= $doctor['status'] ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if ($doctor['is_verified']): ?>
                                                                        <span class="badge badge-verified verification-badge">
                                                                            <i class="fas fa-check-circle"></i> Verified
                                                                        </span>
                                                                        <?php if ($doctor['verified_at']): ?>
                                                                            <small class="d-block text-muted">
                                                                                <?= date('M j, Y', strtotime($doctor['verified_at'])) ?>
                                                                            </small>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-unverified verification-badge">
                                                                            <i class="fas fa-clock"></i> Pending
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="action-buttons">
                                                                        <a href="doctor-edit.php?id=<?= $doctor['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <?php if ($doctor['is_verified']): ?>
                                                                            <a href="doctors-list.php?unverify_id=<?= $doctor['id'] ?>" class="btn btn-sm btn-outline-warning"
                                                                                onclick="return confirm('Remove verification for this doctor?')" title="Unverify">
                                                                                <i class="fas fa-times-circle"></i>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <a href="doctors-list.php?verify_id=<?= $doctor['id'] ?>" class="btn btn-sm btn-outline-success"
                                                                                onclick="return confirm('Verify this doctor?')" title="Verify">
                                                                                <i class="fas fa-check-circle"></i>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                        <a href="doctors-list.php?delete_id=<?= $doctor['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                                            onclick="return confirm('Are you sure you want to delete this doctor?')" title="Delete">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
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
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>
</body>

</html>