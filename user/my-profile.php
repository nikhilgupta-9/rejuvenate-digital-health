<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";
include_once "function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$contact = contact_us();
$user_id = (int)$_SESSION['user_id'];

// Initialize variables
$user_data = [];
$success_message = '';
$error_message = '';
$errors = [];

if (isset($_GET['updated'])) {
    $success_message = "Your account profile has been updated successfully!";
}

// Fetch user data from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

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

$profile_pic_url = !empty($user_data['profile_pic']) ? BASE_URL . 'assets/img/' . htmlspecialchars($user_data['profile_pic']) : null;
$initials = strtoupper(substr($user_data['name'] ?? 'P', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Profile | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .error { color: #dc3545; font-size: 0.85em; margin-top: 0.25rem; }
        .is-invalid { border-color: #dc3545 !important; }
        .form-label { font-weight: 600; color: #374151; font-size: .84rem; margin-bottom: 0.4rem; }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(12,116,197,.12);
        }
        .avatar-box {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .avatar-img-preview {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
        }
        .avatar-fallback {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
        }
    </style>
</head>

<body class="patient-body">
    <?php $sidebar_active = 'profile'; include("sidebar.php"); ?>
    
    <main class="patient-content">
        <div class="profile-card shadow-sm border-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h1 class="ap-h mb-1"><i class="fa fa-user-circle me-2 text-primary-theme"></i>Account Profile</h1>
                    <div class="ap-sub">Manage your personal demographics, contact info, and security settings</div>
                </div>
                <a href="<?= BASE_URL ?>user/user-dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message && empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i><?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Picture Uploader Box -->
            <div class="avatar-box">
                <div id="avatarContainer">
                    <?php if ($profile_pic_url): ?>
                        <img src="<?= $profile_pic_url ?>" alt="Avatar" class="avatar-img-preview" id="profilePicImg">
                    <?php else: ?>
                        <div class="avatar-fallback" id="profilePicImg"><?= $initials ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($user_data['name'] ?? 'Patient') ?> <?= htmlspecialchars($user_data['last_name'] ?? '') ?></h5>
                    <p class="text-muted small mb-2">Registered Patient • <?= htmlspecialchars($user_data['email'] ?? '') ?></p>
                    <label class="btn btn-sm btn-outline-primary mb-0" style="cursor:pointer;">
                        <i class="fa fa-camera me-1"></i> Change Photo
                        <input type="file" id="profilePicInput" accept="image/jpeg,image/png,image/gif" style="display:none;">
                    </label>
                    <div id="uploadStatus" class="small mt-1 text-muted"></div>
                </div>
            </div>

            <!-- ABHA Digital Identity Status Banner -->
            <?php if (!empty($user_data['abha_linked']) && !empty($user_data['abha_id'])): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4" style="background:#eaf4fd;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;background:var(--primary);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                            <i class="fa fa-shield"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:.9rem;">
                                Ayushman Bharat Digital Mission (ABDM) Linked
                                <span class="badge bg-success ms-2 font-weight-bold" style="font-size:.65rem;">Verified M1</span>
                            </div>
                            <div class="small text-muted">
                                ABHA Number: <strong class="font-monospace text-dark"><?= htmlspecialchars($user_data['abha_id']) ?></strong>
                                <?php if (!empty($user_data['abha_address'])): ?>
                                    • Address: <strong><?= htmlspecialchars($user_data['abha_address']) ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>user/my-abha.php" class="btn btn-sm btn-primary">
                        <i class="fa fa-id-card me-1"></i> Manage ABHA ID
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>user/function.php" novalidate>
                <div class="row g-3">
                    <!-- First Name -->
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                               name="name" value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="error"><?= $errors['name'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Last Name -->
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" 
                               value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                    </div>

                    <!-- Mobile Number -->
                    <div class="col-md-6">
                        <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>" 
                               name="mobile" value="<?= htmlspecialchars($user_data['mobile'] ?? '') ?>" 
                               pattern="[0-9]{10}" maxlength="10" required>
                        <?php if (isset($errors['mobile'])): ?>
                            <div class="error"><?= $errors['mobile'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Email ID (Readonly) -->
                    <div class="col-md-6">
                        <label class="form-label">Email ID (Login Identifier)</label>
                        <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" readonly>
                        <small class="text-muted">Primary account email cannot be modified.</small>
                    </div>

                    <!-- Gender -->
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select class="form-select" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male" <?= ($user_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($user_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($user_data['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <!-- Date of Birth -->
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth</label>
                        <?php 
                        $dob_value = '';
                        if (!empty($user_data['dob']) && $user_data['dob'] != '0000-00-00') {
                            $dob_value = htmlspecialchars($user_data['dob']);
                        }
                        ?>
                        <input type="date" class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>" 
                               name="dob" id="dobInput" value="<?= $dob_value ?>">
                        <?php if (isset($errors['dob'])): ?>
                            <div class="error"><?= $errors['dob'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Age (Auto-calculated) -->
                    <div class="col-md-4">
                        <label class="form-label">Age</label>
                        <input type="text" class="form-control bg-light" id="ageInput" value="<?= $age ?>" readonly placeholder="Auto-calculated">
                        <small class="text-muted">Computed from Date of Birth</small>
                    </div>

                    <!-- Address -->
                    <div class="col-12">
                        <label class="form-label">Permanent Address</label>
                        <input type="text" class="form-control" name="address" 
                               value="<?= htmlspecialchars($user_data['address'] ?? '') ?>" 
                               placeholder="Street, flat, building name, locality">
                    </div>

                    <!-- City -->
                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" 
                               value="<?= htmlspecialchars($user_data['city'] ?? '') ?>">
                    </div>

                    <!-- State -->
                    <div class="col-md-4">
                        <label class="form-label">State</label>
                        <input type="text" class="form-control" name="state" 
                               value="<?= htmlspecialchars($user_data['state'] ?? '') ?>">
                    </div>

                    <!-- Zip Code -->
                    <div class="col-md-4">
                        <label class="form-label">Postal Pincode</label>
                        <input type="text" class="form-control" name="zip_code" 
                               value="<?= htmlspecialchars($user_data['zip_code'] ?? '') ?>" 
                               maxlength="10">
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4 fw-bold" name="profile_update">
                        <i class="fa fa-save me-1"></i> Update Profile
                    </button>
                    <a href="<?= BASE_URL ?>user/user-dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <?php include("inc/scripts.php"); ?>

    <script>
        // Auto-calculate age when date of birth changes
        const dobInput = document.getElementById('dobInput');
        const ageInput = document.getElementById('ageInput');
        
        function calculateAge(val) {
            if (!val) return;
            const dob = new Date(val);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            if (ageInput) ageInput.value = age >= 0 ? age : '';
        }

        if (dobInput) {
            dobInput.addEventListener('change', function() { calculateAge(this.value); });
            if (dobInput.value) calculateAge(dobInput.value);
        }

        // Mobile input validation
        const mobileInput = document.querySelector('input[name="mobile"]');
        if (mobileInput) {
            mobileInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
            });
        }

        // Profile Picture Upload via AJAX
        const picInput = document.getElementById('profilePicInput');
        const uploadStatus = document.getElementById('uploadStatus');
        const avatarContainer = document.getElementById('avatarContainer');

        if (picInput) {
            picInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;

                if (file.size > 2 * 1024 * 1024) {
                    alert('Please select an image smaller than 2MB.');
                    return;
                }

                uploadStatus.innerHTML = '<span class="text-primary"><i class="fa fa-spinner fa-spin me-1"></i> Uploading photo...</span>';
                
                const formData = new FormData();
                formData.append('profile_picture', file);

                fetch('<?= BASE_URL ?>user/update-profile-picture.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        uploadStatus.innerHTML = '<span class="text-success"><i class="fa fa-check me-1"></i> ' + data.message + '</span>';
                        avatarContainer.innerHTML = '<img src="<?= BASE_URL ?>assets/img/' + data.image_url + '" class="avatar-img-preview" alt="Avatar">';
                    } else {
                        const errMsg = (data.errors && data.errors.length) ? data.errors.join(', ') : 'Upload failed.';
                        uploadStatus.innerHTML = '<span class="text-danger"><i class="fa fa-times me-1"></i> ' + errMsg + '</span>';
                    }
                })
                .catch(err => {
                    uploadStatus.innerHTML = '<span class="text-danger">Upload error. Please try again.</span>';
                });
            });
        }
    </script>
</body>
</html>