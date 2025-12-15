<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db-conn.php';

// Check if user is logged in
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// Get user data
$user_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT u.*, up.* FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user roles
$stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
$stmt->execute([$user_id]);
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $errors = [];
    
    // Update profile data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $country = trim($_POST['country']);
    $postal_code = trim($_POST['postal_code']);
    $bio = trim($_POST['bio']);
    
    // Handle file upload
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profiles/';
        $file_name = uniqid() . '_' . basename($_FILES['profile_image']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Check file type
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                // Delete old image if exists
                if ($profile_image && file_exists($profile_image)) {
                    unlink($profile_image);
                }
                $profile_image = $target_file;
            }
        } else {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    // Update database if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update user profile
            if ($user['user_id']) {
                // Profile exists, update it
                $stmt = $pdo->prepare("UPDATE user_profiles SET 
                    first_name = ?, last_name = ?, profile_image = ?, phone = ?, 
                    address = ?, city = ?, state = ?, country = ?, postal_code = ?, bio = ?
                    WHERE user_id = ?");
                $stmt->execute([
                    $first_name, $last_name, $profile_image, $phone,
                    $address, $city, $state, $country, $postal_code, $bio,
                    $user_id
                ]);
            } else {
                // Profile doesn't exist, insert it
                $stmt = $pdo->prepare("INSERT INTO user_profiles 
                    (user_id, first_name, last_name, profile_image, phone, 
                    address, city, state, country, postal_code, bio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id, $first_name, $last_name, $profile_image, $phone,
                    $address, $city, $state, $country, $postal_code, $bio
                ]);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Profile updated successfully!";
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-image-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 3px solid #ddd;
            position: relative;
        }
        .profile-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-image-upload {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.5);
            color: white;
            text-align: center;
            padding: 5px;
            cursor: pointer;
        }
        .profile-image-upload input {
            display: none;
        }
        .role-badge {
            font-size: 0.8rem;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Admin Profile Management</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?= $_SESSION['success_message'] ?>
                                <?php unset($_SESSION['success_message']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mb-4">
                            <div class="profile-image-container">
                                <img src="<?= $user['profile_image'] ?: 'https://via.placeholder.com/150'?>" alt="Profile Image" id="profileImagePreview">
                                <label class="profile-image-upload">
                                    <i class="fas fa-camera"></i> Change
                                    <input type="file" name="profile_image" id="profileImageInput" accept="image/*">
                                </label>
                            </div>
                            <h3><?= htmlspecialchars($user['first_name'] ?? '') ?> <?= htmlspecialchars($user['last_name'] ?? '') ?></h3>
                            <div class="mb-3">
                                <?php foreach ($roles as $role): ?>
                                    <span class="badge bg-secondary role-badge"><?= htmlspecialchars($role) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <form method="post" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" 
                                           value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" 
                                           value="<?= htmlspecialchars($user['country'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                           value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i> Save Profile
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview profile image before upload
        document.getElementById('profileImageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profileImagePreview').src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>