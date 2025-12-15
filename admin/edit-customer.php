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

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input data
    $name = trim($_POST['name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $blood_group = $_POST['blood_group'] ?? '';
    $identification_type = $_POST['identification_type'] ?? '';
    $identification_number = trim($_POST['identification_number'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    $mobile_verified = isset($_POST['mobile_verified']) ? 1 : 0;

    // Validation
    if (empty($name)) {
        $errors['name'] = "First name is required";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Valid email is required";
    } else {
        // Check if email already exists for other users
        $check_email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
        mysqli_stmt_bind_param($check_email_stmt, "si", $email, $customer_id);
        mysqli_stmt_execute($check_email_stmt);
        mysqli_stmt_store_result($check_email_stmt);
        if (mysqli_stmt_num_rows($check_email_stmt) > 0) {
            $errors['email'] = "Email already registered with another account";
        }
        mysqli_stmt_close($check_email_stmt);
    }

    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors['mobile'] = "Please enter a valid 10-digit mobile number";
    } else {
        // Check if mobile already exists for other users
        $check_mobile_sql = "SELECT id FROM users WHERE mobile = ? AND id != ?";
        $check_mobile_stmt = mysqli_prepare($conn, $check_mobile_sql);
        mysqli_stmt_bind_param($check_mobile_stmt, "si", $mobile, $customer_id);
        mysqli_stmt_execute($check_mobile_stmt);
        mysqli_stmt_store_result($check_mobile_stmt);
        if (mysqli_stmt_num_rows($check_mobile_stmt) > 0) {
            $errors['mobile'] = "Mobile number already registered with another account";
        }
        mysqli_stmt_close($check_mobile_stmt);
    }

    // If no errors, update customer
    if (empty($errors)) {
        $update_sql = "UPDATE users SET name = ?, last_name = ?, email = ?, mobile = ?, gender = ?, dob = ?, address = ?, city = ?, state = ?, zip_code = ?, blood_group = ?, identification_type = ?, identification_number = ?, emergency_contact = ?, status = ?, email_verified = ?, mobile_verified = ?, updated_at = NOW() WHERE id = ?";

        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param(
            $update_stmt,
            "sssssssssssssssiii",
            $name,
            $last_name,
            $email,
            $mobile,
            $gender,
            $dob,
            $address,
            $city,
            $state,
            $zip_code,
            $blood_group,
            $identification_type,
            $identification_number,
            $emergency_contact,
            $status,
            $email_verified,
            $mobile_verified,
            $customer_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "Customer updated successfully!";

            // Refresh customer data
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $customer_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $customer = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        } else {
            $errors['general'] = "Failed to update customer. Please try again.";
        }
        mysqli_stmt_close($update_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Customer | Admin Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    <style>
        .form-card {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .form-card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c5aa0;
        }

        .form-card-body {
            padding: 20px;
        }

        .error {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }

        .is-invalid {
            border-color: #dc3545;
        }

        .customer-header {
            background: linear-gradient(145deg, #98c5ffff, #d3e8fdff);
            /* Soft medical blue/white */
            border: 1px solid #8cc3faff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 85, 150, 0.08);
            /* Soft hospital blue shadow */
            color: #2d4a60;
        }

        .customer-header h4 {
            font-weight: 600;
            color: #1f3b58;
            /* Deep medical blue */
        }

        .customer-header p {
            color: #4e6b88;
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
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="row align-items-center justify-content-between flex-wrap">
                                    <div class="col-lg-4">
                                        <h2 class="page-title">Edit Customer</h2>
                                    </div>
                                    <div class="col-lg-4 text-lg-end">
                                        <a href="view-customer.php?id=<?= $customer['id'] ?>" class="btn btn-outline-info me-2">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a>
                                        <a href="all-customers.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Customers
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Customer Header -->
                                <div class="customer-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <?php if (!empty($customer['profile_pic'])) { ?>
                                                        <img src="<?= $site . 'assets/img/' . $customer['profile_pic']  ?>"
                                                            class="img-fluid rounded-circle" style="width: 80px; height: 80px; object-fit: cover;"

                                                            alt="<?= htmlspecialchars($customer['name']) ?>">
                                                    <?php } else { ?>
                                                        <i class="fas fa-user-circle" style="font-size: 80px; color: #ccc;"></i>
                                                    <?php } ?>
                                                </div>
                                                <div>
                                                    <h4 class="mb-1"><?= htmlspecialchars($customer['name'] . ' ' . $customer['last_name']) ?></h4>
                                                    <p class="mb-0 opacity-75">Customer ID: #<?= $customer['id'] ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <p class="mb-1">Member since <?= date('M j, Y', strtotime($customer['created_at'])) ?></p>
                                            <p class="mb-0">Last updated: <?= date('M j, Y', strtotime($customer['updated_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>


                                <?php if ($success_message): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $success_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($errors['general'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $errors['general'] ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="" novalidate>
                                    <!-- Personal Information -->
                                    <div class="form-card">
                                        <div class="form-card-header">
                                            <i class="fas fa-user me-2"></i>Personal Information
                                        </div>
                                        <div class="form-card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                                        name="name" value="<?= htmlspecialchars($customer['name']) ?>" required>
                                                    <?php if (isset($errors['name'])): ?>
                                                        <div class="error"><?= $errors['name'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" name="last_name"
                                                        value="<?= htmlspecialchars($customer['last_name']) ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                                        name="email" value="<?= htmlspecialchars($customer['email']) ?>" required>
                                                    <?php if (isset($errors['email'])): ?>
                                                        <div class="error"><?= $errors['email'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>"
                                                        name="mobile" value="<?= htmlspecialchars($customer['mobile']) ?>"
                                                        pattern="[0-9]{10}" maxlength="10" required>
                                                    <?php if (isset($errors['mobile'])): ?>
                                                        <div class="error"><?= $errors['mobile'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Gender</label>
                                                    <select class="form-control" name="gender">
                                                        <option value="">Select Gender</option>
                                                        <option value="Male" <?= $customer['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                                        <option value="Female" <?= $customer['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                                        <option value="Other" <?= $customer['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Date of Birth</label>
                                                    <input type="date" class="form-control" name="dob"
                                                        value="<?= $customer['dob'] && $customer['dob'] != '0000-00-00' ? $customer['dob'] : '' ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Blood Group</label>
                                                    <select class="form-control" name="blood_group">
                                                        <option value="">Select Blood Group</option>
                                                        <option value="A+" <?= $customer['blood_group'] === 'A+' ? 'selected' : '' ?>>A+</option>
                                                        <option value="A-" <?= $customer['blood_group'] === 'A-' ? 'selected' : '' ?>>A-</option>
                                                        <option value="B+" <?= $customer['blood_group'] === 'B+' ? 'selected' : '' ?>>B+</option>
                                                        <option value="B-" <?= $customer['blood_group'] === 'B-' ? 'selected' : '' ?>>B-</option>
                                                        <option value="AB+" <?= $customer['blood_group'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                                        <option value="AB-" <?= $customer['blood_group'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                                        <option value="O+" <?= $customer['blood_group'] === 'O+' ? 'selected' : '' ?>>O+</option>
                                                        <option value="O-" <?= $customer['blood_group'] === 'O-' ? 'selected' : '' ?>>O-</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Address Information -->
                                    <div class="form-card">
                                        <div class="form-card-header">
                                            <i class="fas fa-map-marker-alt me-2"></i>Address Information
                                        </div>
                                        <div class="form-card-body">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="address"
                                                        value="<?= htmlspecialchars($customer['address']) ?>"
                                                        placeholder="Enter complete address">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">City</label>
                                                    <input type="text" class="form-control" name="city"
                                                        value="<?= htmlspecialchars($customer['city']) ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">State</label>
                                                    <input type="text" class="form-control" name="state"
                                                        value="<?= htmlspecialchars($customer['state']) ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Zip Code</label>
                                                    <input type="text" class="form-control" name="zip_code"
                                                        value="<?= htmlspecialchars($customer['zip_code']) ?>"
                                                        maxlength="15">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Additional Information -->
                                    <div class="form-card">
                                        <div class="form-card-header">
                                            <i class="fas fa-id-card me-2"></i>Additional Information
                                        </div>
                                        <div class="form-card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Identification Type</label>
                                                    <select class="form-control" name="identification_type">
                                                        <option value="">Select Type</option>
                                                        <option value="Aadhar" <?= $customer['identification_type'] === 'Aadhar' ? 'selected' : '' ?>>Aadhar</option>
                                                        <option value="Passport" <?= $customer['identification_type'] === 'Passport' ? 'selected' : '' ?>>Passport</option>
                                                        <option value="Driving License" <?= $customer['identification_type'] === 'Driving License' ? 'selected' : '' ?>>Driving License</option>
                                                        <option value="None" <?= $customer['identification_type'] === 'None' ? 'selected' : '' ?>>None</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Identification Number</label>
                                                    <input type="text" class="form-control" name="identification_number"
                                                        value="<?= htmlspecialchars($customer['identification_number']) ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Emergency Contact</label>
                                                    <input type="text" class="form-control" name="emergency_contact"
                                                        value="<?= htmlspecialchars($customer['emergency_contact']) ?>"
                                                        maxlength="20">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Account Settings -->
                                    <div class="form-card">
                                        <div class="form-card-header">
                                            <i class="fas fa-cog me-2"></i>Account Settings
                                        </div>
                                        <div class="form-card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Account Status</label>
                                                    <select class="form-control" name="status" required>
                                                        <option value="Active" <?= $customer['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="Inactive" <?= $customer['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        <option value="Blocked" <?= $customer['status'] === 'Blocked' ? 'selected' : '' ?>>Blocked</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" name="email_verified" id="email_verified" value="1"
                                                            <?= $customer['email_verified'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="email_verified">
                                                            Email Verified
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" name="mobile_verified" id="mobile_verified" value="1"
                                                            <?= $customer['mobile_verified'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="mobile_verified">
                                                            Mobile Verified
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submit Buttons -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between">
                                                <a href="customer-management.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-times me-2"></i>Cancel
                                                </a>
                                                <div>
                                                    <a href="view-customer.php?id=<?= $customer['id'] ?>" class="btn btn-outline-info me-2">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Update Customer
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script>
            // Mobile number validation
            document.querySelector('input[name="mobile"]').addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        </script>
</body>

</html>