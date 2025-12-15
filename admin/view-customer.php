<?php
session_start();
include "db-conn.php";

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$customer_id = intval($_GET['id'] ?? 0);

// Fetch customer details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: customer-management.php");
    exit();
}

// Calculate age from date of birth
$age = '';
if (!empty($customer['dob']) && $customer['dob'] != '0000-00-00') {
    $dob = new DateTime($customer['dob']);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Customer Details | Admin Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    <style>
        .customer-profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255,255,255,0.3);
        }
        .info-card {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .info-card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c5aa0;
        }
        .info-card-body {
            padding: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }
        .detail-value {
            color: #6c757d;
            text-align: right;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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
        .badge-verified {
            background-color: #e6f7ee;
            color: #28a745;
        }
        .badge-unverified {
            background-color: #fff3cd;
            color: #856404;
        }
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
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

                <div class="row">
                    <div class="col-12">
                        <!-- Customer Profile Header -->
                        <div class="customer-profile-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center">
                                        <div class="me-4">
                                            <?php if (!empty($customer['profile_pic'])) { ?>
                                                        <img src="<?= $site . 'assets/img/' . $customer['profile_pic']  ?>"
                                                            class="img-fluid rounded-circle" style="width: 80px; height: 80px; object-fit: cover;"

                                                            alt="<?= htmlspecialchars($customer['name']) ?>">
                                                    <?php } else { ?>
                                                        <i class="fas fa-user-circle" style="font-size: 80px; color: #ccc;"></i>
                                                    <?php } ?>
                                        </div>
                                        <div>
                                            <h1 class="h2 mb-2"><?= htmlspecialchars($customer['name'] . ' ' . $customer['last_name']) ?></h1>
                                            <p class="mb-1 opacity-75">Customer ID: #<?= $customer['id'] ?></p>
                                            <p class="mb-0 opacity-75">Member since <?= date('F j, Y', strtotime($customer['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <div class="d-flex flex-column gap-2">
                                        <a href="all-customers.php" class="btn back-btn">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Customers
                                        </a>
                                        <a href="edit-customer.php?id=<?= $customer['id'] ?>" class="btn btn-light">
                                            <i class="fas fa-edit me-2"></i>Edit Customer
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-user me-2"></i>Personal Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Full Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['name'] . ' ' . $customer['last_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Email</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['email']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile</span>
                                            <span class="detail-value"><?= htmlspecialchars($customer['mobile']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Date of Birth</span>
                                            <span class="detail-value">
                                                <?= !empty($customer['dob']) && $customer['dob'] != '0000-00-00' ? date('M j, Y', strtotime($customer['dob'])) : 'Not set' ?>
                                                <?= $age ? " ($age years)" : '' ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Gender</span>
                                            <span class="detail-value"><?= $customer['gender'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Blood Group</span>
                                            <span class="detail-value"><?= $customer['blood_group'] ?: 'Not set' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-cog me-2"></i>Account Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Account Status</span>
                                            <span class="detail-value">
                                                <span class="status-badge badge-<?= strtolower($customer['status']) ?>">
                                                    <?= $customer['status'] ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Email Verification</span>
                                            <span class="detail-value">
                                                <span class="status-badge <?= $customer['email_verified'] ? 'badge-verified' : 'badge-unverified' ?>">
                                                    <?= $customer['email_verified'] ? 'Verified' : 'Unverified' ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile Verification</span>
                                            <span class="detail-value">
                                                <span class="status-badge <?= $customer['mobile_verified'] ? 'badge-verified' : 'badge-unverified' ?>">
                                                    <?= $customer['mobile_verified'] ? 'Verified' : 'Unverified' ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Last Login</span>
                                            <span class="detail-value">
                                                <?= !empty($customer['last_login']) ? date('M j, Y g:i A', strtotime($customer['last_login'])) : 'Never logged in' ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Created</span>
                                            <span class="detail-value">
                                                <?= date('M j, Y g:i A', strtotime($customer['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Last Updated</span>
                                            <span class="detail-value">
                                                <?= date('M j, Y g:i A', strtotime($customer['updated_at'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-map-marker-alt me-2"></i>Address Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Address</span>
                                            <span class="detail-value"><?= $customer['address'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">City</span>
                                            <span class="detail-value"><?= $customer['city'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">State</span>
                                            <span class="detail-value"><?= $customer['state'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Zip Code</span>
                                            <span class="detail-value"><?= $customer['zip_code'] ?: 'Not set' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <i class="fas fa-id-card me-2"></i>Additional Information
                                    </div>
                                    <div class="info-card-body">
                                        <div class="detail-row">
                                            <span class="detail-label">Identification Type</span>
                                            <span class="detail-value"><?= $customer['identification_type'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Identification Number</span>
                                            <span class="detail-value"><?= $customer['identification_number'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Emergency Contact</span>
                                            <span class="detail-value"><?= $customer['emergency_contact'] ?: 'Not set' ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Remember Token</span>
                                            <span class="detail-value">
                                                <?= $customer['remember_token'] ? substr($customer['remember_token'], 0, 10) . '...' : 'Not set' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="all-customers.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Customers
                                        </a>
                                    </div>
                                    <div>
                                        <a href="edit-customer.php?id=<?= $customer['id'] ?>" class="btn btn-primary me-2">
                                            <i class="fas fa-edit me-2"></i>Edit Customer
                                        </a>
                                        <a href="customer-management.php?delete=<?= $customer['id'] ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                            <i class="fas fa-trash me-2"></i>Delete Customer
                                        </a>
                                    </div>
                                </div>
                            </div>
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