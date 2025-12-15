<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$contact = contact_us();
$user_id = $_SESSION['user_id'];

// Initialize variables
$success_message = '';
$error_message = '';
$errors = [];

// Fetch user addresses from database
$addresses = [];
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}
$stmt->close();

// Handle form submission for adding new address
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input data
    $colony_area = trim($_POST['colony_area'] ?? '');
    $house_no = trim($_POST['house_no'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $address_type = $_POST['address_type'] ?? 'home';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    if (empty($colony_area)) {
        $errors['colony_area'] = "Colony/Area/Sector is required";
    }
    
    if (empty($house_no)) {
        $errors['house_no'] = "House/Flat number is required";
    }
    
    if (empty($zip_code)) {
        $errors['zip_code'] = "Pincode is required";
    } elseif (!preg_match('/^[1-9][0-9]{5}$/', $zip_code)) {
        $errors['zip_code'] = "Please enter a valid 6-digit pincode";
    }
    
    if (empty($city)) {
        $errors['city'] = "City is required";
    }
    
    if (empty($state)) {
        $errors['state'] = "State is required";
    }
    
    // If no errors, save address
    if (empty($errors)) {
        // If this is set as default, remove default from other addresses
        if ($is_default) {
            $update_stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Insert new address
        $insert_stmt = $conn->prepare("INSERT INTO user_addresses (user_id, address_type, house_no, colony_area, landmark, city, state, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("isssssssi", $user_id, $address_type, $house_no, $colony_area, $landmark, $city, $state, $zip_code, $is_default);
        
        if ($insert_stmt->execute()) {
            $success_message = "Address added successfully!";
            
            // Refresh addresses list
            $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $addresses = [];
            while ($row = $result->fetch_assoc()) {
                $addresses[] = $row;
            }
            $stmt->close();
        } else {
            $error_message = "Failed to add address. Please try again.";
        }
        $insert_stmt->close();
    } else {
        $error_message = "Please correct the errors below.";
    }
}

// Handle address deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Verify that the address belongs to the current user
    $check_stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $delete_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Address deleted successfully!";
            
            // Refresh addresses list
            $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $addresses = [];
            while ($row = $result->fetch_assoc()) {
                $addresses[] = $row;
            }
            $stmt->close();
        } else {
            $error_message = "Failed to delete address. Please try again.";
        }
        $delete_stmt->close();
    } else {
        $error_message = "Address not found or you don't have permission to delete it.";
    }
    $check_stmt->close();
    
    // Redirect to remove delete_id from URL
    header("Location: manage-address.php");
    exit();
}

// Handle set as default
if (isset($_GET['set_default'])) {
    $default_id = intval($_GET['set_default']);
    
    // Verify that the address belongs to the current user
    $check_stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $default_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        // Remove default from all addresses
        $reset_stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $reset_stmt->bind_param("i", $user_id);
        $reset_stmt->execute();
        $reset_stmt->close();
        
        // Set the selected address as default
        $default_stmt = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
        $default_stmt->bind_param("i", $default_id);
        
        if ($default_stmt->execute()) {
            $success_message = "Default address updated successfully!";
            
            // Refresh addresses list
            $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $addresses = [];
            while ($row = $result->fetch_assoc()) {
                $addresses[] = $row;
            }
            $stmt->close();
        } else {
            $error_message = "Failed to set default address. Please try again.";
        }
        $default_stmt->close();
    } else {
        $error_message = "Address not found or you don't have permission to modify it.";
    }
    $check_stmt->close();
    
    // Redirect to remove set_default from URL
    header("Location: manage-address.php");
    exit();
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
    <title>Manage Address | REJUVENATE Digital Health</title>
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
        .address-card { 
            border: 1px solid #e9ecef; 
            border-radius: 10px; 
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
            background: white;
        }
        .address-card.default { 
            border-color: #2c5aa0; 
            background: #f8f9fa;
        }
        .address-type-badge {
            background: #2c5aa0;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            text-transform: capitalize;
        }
        .default-badge {
            background: #28a745;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .address-actions .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
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
                        <h4 class="mb-4">Manage Address</h4>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $success_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Add New Address Form -->
                        <div class="mb-5">
                            <h5 class="mb-3">Add New Address</h5>
                            <form method="POST" action="" novalidate>
                                <div class="row mt-4">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Colony / Area / Sector <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?= isset($errors['colony_area']) ? 'is-invalid' : '' ?>" 
                                               name="colony_area" value="<?= htmlspecialchars($_POST['colony_area'] ?? '') ?>" required>
                                        <?php if (isset($errors['colony_area'])): ?>
                                            <div class="error"><?= $errors['colony_area'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">House No. / Flat No. / Building <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?= isset($errors['house_no']) ? 'is-invalid' : '' ?>" 
                                               name="house_no" value="<?= htmlspecialchars($_POST['house_no'] ?? '') ?>" required>
                                        <?php if (isset($errors['house_no'])): ?>
                                            <div class="error"><?= $errors['house_no'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Landmark</label>
                                        <input type="text" class="form-control" name="landmark" 
                                               value="<?= htmlspecialchars($_POST['landmark'] ?? '') ?>" 
                                               placeholder="Optional">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Pincode <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?= isset($errors['zip_code']) ? 'is-invalid' : '' ?>" 
                                               name="zip_code" value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>" 
                                               pattern="[1-9][0-9]{5}" maxlength="6" required>
                                        <?php if (isset($errors['zip_code'])): ?>
                                            <div class="error"><?= $errors['zip_code'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>" 
                                               name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
                                        <?php if (isset($errors['city'])): ?>
                                            <div class="error"><?= $errors['city'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">State <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?= isset($errors['state']) ? 'is-invalid' : '' ?>" 
                                               name="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>" required>
                                        <?php if (isset($errors['state'])): ?>
                                            <div class="error"><?= $errors['state'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Address Type</label>
                                        <select class="form-control" name="address_type">
                                            <option value="home" <?= ($_POST['address_type'] ?? '') === 'home' ? 'selected' : '' ?>>Home</option>
                                            <option value="office" <?= ($_POST['address_type'] ?? '') === 'office' ? 'selected' : '' ?>>Office</option>
                                            <option value="other" <?= ($_POST['address_type'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1" 
                                                   <?= isset($_POST['is_default']) ? 'checked' : (empty($addresses) ? 'checked' : '') ?>>
                                            <label class="form-check-label" for="is_default">
                                                Set as default address
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-warning">Save Address</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Existing Addresses -->
                        <div class="existing-addresses">
                            <h5 class="mb-3">Your Addresses</h5>
                            
                            <?php if (empty($addresses)): ?>
                                <div class="alert alert-info">
                                    You haven't added any addresses yet. Add your first address above.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($addresses as $address): ?>
                                        <div class="col-md-6">
                                            <div class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="address-type-badge">
                                                        <?= ucfirst($address['address_type']) ?>
                                                    </span>
                                                    <?php if ($address['is_default']): ?>
                                                        <span class="default-badge">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <p class="mb-1"><strong><?= htmlspecialchars($address['house_no']) ?></strong></p>
                                                <p class="mb-1"><?= htmlspecialchars($address['colony_area']) ?></p>
                                                <?php if (!empty($address['landmark'])): ?>
                                                    <p class="mb-1">Near: <?= htmlspecialchars($address['landmark']) ?></p>
                                                <?php endif; ?>
                                                <p class="mb-1"><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> - <?= htmlspecialchars($address['zip_code']) ?></p>
                                                
                                                <div class="address-actions mt-3">
                                                    <?php if (!$address['is_default']): ?>
                                                        <a href="manage-address.php?set_default=<?= $address['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                            Set as Default
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="manage-address.php?delete_id=<?= $address['id'] ?>" class="btn btn-outline-danger btn-sm" 
                                                       onclick="return confirm('Are you sure you want to delete this address?')">
                                                        Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
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
        
        // Pincode validation - allow only numbers and limit to 6 digits
        document.querySelector('input[name="zip_code"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
    </script>
</body>
</html>