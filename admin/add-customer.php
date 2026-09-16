<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();
require_once dirname(__DIR__) . '/util/otp-service.php';
require_once dirname(__DIR__) . '/util/otp-widget.php';
require_once dirname(__DIR__) . '/lib/Abha.php';

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input data
    $name = trim($_POST['name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $abha_number_raw = preg_replace('/\D/', '', trim($_POST['abha_number'] ?? ''));
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
    $mobile_manual_override = isset($_POST['mobile_verified']);   // "mark verified manually" checkbox
    $mobile_verify_token = $_POST['mobile_verify_token'] ?? '';
    $mobile_verified = 0;

    // Validation
    if (empty($name)) {
        $errors['name'] = "First name is required";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Valid email is required";
    } else {
        // Check if email already exists
        $check_email_sql = "SELECT id FROM users WHERE email = ?";
        $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
        mysqli_stmt_bind_param($check_email_stmt, "s", $email);
        mysqli_stmt_execute($check_email_stmt);
        mysqli_stmt_store_result($check_email_stmt);
        if (mysqli_stmt_num_rows($check_email_stmt) > 0) {
            $errors['email'] = "Email already registered";
        }
        mysqli_stmt_close($check_email_stmt);
    }

    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors['mobile'] = "Please enter a valid 10-digit mobile number";
    } else {
        // Check if mobile already exists
        $check_mobile_sql = "SELECT id FROM users WHERE mobile = ?";
        $check_mobile_stmt = mysqli_prepare($conn, $check_mobile_sql);
        mysqli_stmt_bind_param($check_mobile_stmt, "s", $mobile);
        mysqli_stmt_execute($check_mobile_stmt);
        mysqli_stmt_store_result($check_mobile_stmt);
        if (mysqli_stmt_num_rows($check_mobile_stmt) > 0) {
            $errors['mobile'] = "Mobile number already registered";
        }
        mysqli_stmt_close($check_mobile_stmt);

        // Mobile verification: OTP (code sent to the patient's WhatsApp/email and
        // read back to the admin) or an explicit manual override.
        if (!isset($errors['mobile'])) {
            if (otp_consume_token('patient', $mobile, $mobile_verify_token)) {
                $mobile_verified = 1;
            } elseif ($mobile_manual_override) {
                $mobile_verified = 1;
                try {
                    (new AuditLogger($conn))->logValidationFailure(
                        'mobile_verified',
                        'manual override by admin #' . (int)($_SESSION['admin_id'] ?? 0),
                        0,
                        'patient'
                    );
                } catch (\Throwable $e) { /* non-fatal */ }
            } else {
                $errors['mobile'] = 'Verify the patient\'s mobile via OTP, or tick "mark verified manually".';
            }
        }
    }

    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }

    if ($abha_number_raw !== '' && strlen($abha_number_raw) !== 14) {
        $errors['abha_number'] = "ABHA number must be exactly 14 digits, or leave it blank";
    }

    // If no errors, create customer
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $insert_sql = "INSERT INTO users (name, last_name, email, mobile, password, gender, dob, address, city, state, zip_code, blood_group, identification_type, identification_number, emergency_contact, status, email_verified, mobile_verified, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "ssssssssssssssssii", 
            $name, $last_name, $email, $mobile, $hashed_password, $gender, $dob, $address, 
            $city, $state, $zip_code, $blood_group, $identification_type, $identification_number, 
            $emergency_contact, $status, $email_verified, $mobile_verified);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $customer_id = mysqli_insert_id($conn);

            if ($abha_number_raw !== '') {
                try {
                    Abha::save($conn, 'patient', $customer_id, [
                        'abha_number' => $abha_number_raw,
                        'linked'      => 1,
                        'verified'    => 0,
                        'source'      => 'admin',
                    ]);
                } catch (\Throwable $e) {
                    error_log('[admin/add-customer] Abha::save failed: ' . $e->getMessage());
                }
            }

            $success_message = "Customer created successfully! Customer ID: #" . $customer_id;

            // Clear form data
            $_POST = [];
        } else {
            $errors['general'] = "Failed to create customer. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add New Customer | Admin Dashboard</title>
    
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
                        <h4 class="mb-0 fw-bold">Add New Customer</h4>
                        <small class="text-muted">Create a patient account — same details captured as patient self-registration</small>
                    </div>
                    <a href="all-customers.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Customers
                    </a>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i><?= $errors['general'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate id="addCustomerForm">

                    <div class="detail-card">
                        <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                       name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= $errors['name'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name"
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                       name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= $errors['email'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>"
                                       name="mobile" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>"
                                       pattern="[0-9]{10}" maxlength="10" inputmode="numeric" required>
                                <?php if (isset($errors['mobile'])): ?><div class="invalid-feedback d-block"><?= $errors['mobile'] ?></div><?php endif; ?>
                                <div class="hint" style="font-size:.75rem;color:#94a3b8;margin-top:4px;">Send a code to the patient's WhatsApp &amp; email and enter what they read back — or use the manual override under Account Settings.</div>
                                <?php render_otp_widget([
                                    'role'           => 'patient',
                                    'mobile_field'   => 'mobile',
                                    'email_field'    => 'email',
                                    'name_field'     => 'name',
                                    'allow_existing' => true,
                                    'optional'       => true,
                                    'send_url'       => BASE_URL . 'admin/patient-otp-send.php',
                                    'verify_url'     => BASE_URL . 'admin/patient-otp-verify.php',
                                ]); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                       name="password" id="password" required>
                                <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= $errors['password'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                       name="confirm_password" id="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback d-block"><?= $errors['confirm_password'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob"
                                       value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Blood Group</label>
                                <select class="form-select" name="blood_group">
                                    <option value="">Select Blood Group</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h5><i class="fas fa-id-card-clip me-2"></i>ABHA Health ID <span class="text-muted fw-normal text-uppercase" style="font-size:.68rem;letter-spacing:.3px;">(Optional)</span></h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ABHA Number (14-digit)</label>
                                <input type="text" class="form-control <?= isset($errors['abha_number']) ? 'is-invalid' : '' ?>"
                                       name="abha_number" maxlength="17" placeholder="XX-XXXX-XXXX-XXXX"
                                       value="<?= htmlspecialchars($_POST['abha_number'] ?? '') ?>">
                                <?php if (isset($errors['abha_number'])): ?><div class="invalid-feedback d-block"><?= $errors['abha_number'] ?></div><?php endif; ?>
                                <div class="hint" style="font-size:.75rem;color:#94a3b8;margin-top:4px;">Captured as-is — not verified with ABDM here. Verify later from ABHA Management.</div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>Address Information</h5>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address"
                                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                       placeholder="Enter complete address">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city"
                                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state"
                                       value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Zip Code</label>
                                <input type="text" class="form-control" name="zip_code"
                                       value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>"
                                       maxlength="15">
                            </div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h5><i class="fas fa-address-card me-2"></i>Additional Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Identification Type</label>
                                <select class="form-select" name="identification_type">
                                    <option value="">Select Type</option>
                                    <option value="Aadhar" <?= ($_POST['identification_type'] ?? '') === 'Aadhar' ? 'selected' : '' ?>>Aadhar</option>
                                    <option value="Passport" <?= ($_POST['identification_type'] ?? '') === 'Passport' ? 'selected' : '' ?>>Passport</option>
                                    <option value="Driving License" <?= ($_POST['identification_type'] ?? '') === 'Driving License' ? 'selected' : '' ?>>Driving License</option>
                                    <option value="None" <?= ($_POST['identification_type'] ?? '') === 'None' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Identification Number</label>
                                <input type="text" class="form-control" name="identification_number"
                                       value="<?= htmlspecialchars($_POST['identification_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" name="emergency_contact"
                                       value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>"
                                       maxlength="20">
                            </div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h5><i class="fas fa-gear me-2"></i>Account Settings</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Account Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="Active" <?= ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= ($_POST['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="Blocked" <?= ($_POST['status'] ?? '') === 'Blocked' ? 'selected' : '' ?>>Blocked</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="email_verified" id="email_verified" value="1"
                                           <?= isset($_POST['email_verified']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="email_verified">Email Verified</label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="mobile_verified" id="mobile_verified" value="1"
                                           <?= isset($_POST['mobile_verified']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="mobile_verified">
                                        Mark mobile verified manually <small class="text-muted">(override — logged)</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-4">
                        <a href="all-customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Customer
                        </button>
                    </div>
                </form>

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

            // Password confirmation check
            document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        </script>
</body>

</html>