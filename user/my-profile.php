<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";
include_once "function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$contact = contact_us();
$user_id = $_SESSION['user_id'];

// Initialize variables
$user_data = [];
$success_message = '';
$error_message = '';
$errors = [];

// Fetch user data from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

$stmt->close();

// Debug: Check if data is fetched
if (!$user_data) {
    $error_message = "User data not found!";
}

// Calculate age from date of birth
$age = '';
if (!empty($user_data['dob']) && $user_data['dob'] != '0000-00-00') {
    $dob = new DateTime($user_data['dob']);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Edit Profile | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .error { color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
        .is-invalid { border-color: #dc3545; }
        .alert { border-radius: 8px; }
        .profile-card { padding: 2rem; }
        .form-label { font-weight: 500; color: #333; margin-bottom: 0.5rem; }
        .sidebar { position: sticky; top: 20px; }
    </style>
</head>

<body>
    <?php include("../header.php") ?>
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-3">
                   <?php include("sidebar.php") ?>
                </div>
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
                    <div class="profile-card shadow">
                        <h4 class="mb-4">Edit Profile</h4>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $success_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message && empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= $site ?>user/function.php" novalidate>

                        
                            <div class="row mt-4">
                                <!-- First Name -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                           name="name" value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="error"><?= $errors['name'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Last Name -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                                </div>

                                <!-- Mobile Number -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>" 
                                           name="mobile" value="<?= htmlspecialchars($user_data['mobile'] ?? '') ?>" 
                                           pattern="[0-9]{10}" maxlength="10" required>
                                    <?php if (isset($errors['mobile'])): ?>
                                        <div class="error"><?= $errors['mobile'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Email ID (Readonly) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email ID</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" readonly>
                                    <small class="text-muted">Email cannot be changed</small>
                                </div>

                                <!-- Gender -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-control" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?= ($user_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($user_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($user_data['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>

                                <!-- Date of Birth -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <?php 
                                    $dob_value = '';
                                    if (!empty($user_data['dob']) && $user_data['dob'] != '0000-00-00') {
                                        $dob_value = htmlspecialchars($user_data['dob']);
                                    }
                                    ?>
                                    <input type="date" class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>" 
                                           name="dob" value="<?= $dob_value ?>">
                                    <?php if (isset($errors['dob'])): ?>
                                        <div class="error"><?= $errors['dob'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Age (Auto-calculated) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Age</label>
                                    <input type="text" class="form-control" value="<?= $age ?>" readonly>
                                    <small class="text-muted">Auto-calculated from date of birth</small>
                                </div>
                                

                                <!-- Address -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" 
                                           value="<?= htmlspecialchars($user_data['address'] ?? '') ?>" 
                                           placeholder="Enter your complete address">
                                </div>

                                <!-- City -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" 
                                           value="<?= htmlspecialchars($user_data['city'] ?? '') ?>">
                                </div>

                                <!-- State -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" name="state" 
                                           value="<?= htmlspecialchars($user_data['state'] ?? '') ?>">
                                </div>

                                <!-- Zip Code -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Zip Code</label>
                                    <input type="text" class="form-control" name="zip_code" 
                                           value="<?= htmlspecialchars($user_data['zip_code'] ?? '') ?>" 
                                           maxlength="15">
                                </div>
                            </div>

                            <div class="text-start mt-4">
                                <button type="submit" class="btn btn-warning px-4" name="profile_update">Update Profile</button>
                                <a href="user-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include("../footer.php") ?>
    
    <script src="<?= $site ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        
        // Auto-calculate age when date of birth changes
        document.querySelector('input[name="dob"]').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            // Find the age input field
            const ageInput = document.querySelector('input[type="text"][readonly]');
            if (ageInput) {
                ageInput.value = age > 0 ? age : '';
            }
        });
        
        // Mobile number validation
        document.querySelector('input[name="mobile"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });

        // Initialize age on page load if DOB is present
        document.addEventListener('DOMContentLoaded', function() {
            const dobInput = document.querySelector('input[name="dob"]');
            if (dobInput && dobInput.value) {
                dobInput.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>