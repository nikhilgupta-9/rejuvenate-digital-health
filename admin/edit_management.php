<?php
// Database connection
include "db-conn.php";

// Check if member ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: management.php");
    exit();
}

$member_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch member data
$sql = "SELECT * FROM management_team WHERE id = '$member_id'";
$result = mysqli_query($conn, $sql);
$member = mysqli_fetch_assoc($result);

if(!$member) {
    header("Location: management.php");
    exit();
}

// Handle form submission
if(isset($_POST['update_member'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $display_order = mysqli_real_escape_string($conn, $_POST['display_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Social links
    $facebook = mysqli_real_escape_string($conn, $_POST['facebook_url']);
    $twitter = mysqli_real_escape_string($conn, $_POST['twitter_url']);
    $linkedin = mysqli_real_escape_string($conn, $_POST['linkedin_url']);
    $instagram = mysqli_real_escape_string($conn, $_POST['instagram_url']);
    $website = mysqli_real_escape_string($conn, $_POST['website_url']);
    
    $sql = "UPDATE management_team SET name='$name', position='$position', department='$department', description='$description', email='$email', phone='$phone', facebook_url='$facebook', twitter_url='$twitter', linkedin_url='$linkedin', instagram_url='$instagram', website_url='$website', display_order='$display_order', is_active='$is_active' WHERE id='$member_id'";
    
    // Handle image update if new image is uploaded
    if(!empty($_FILES["member_image"]["name"])) {
        // Delete old image
        if(file_exists($member['image_path'])) {
            unlink($member['image_path']);
        }
        
        // Upload new image
        $target_dir = "uploads/management/";
        $image_name = time() . '_' . basename($_FILES["member_image"]["name"]);
        $target_file = $target_dir . $image_name;
        
        // Validate image
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES["member_image"]["tmp_name"]);
        if($check === false) {
            $update_error = "File is not an image.";
            $uploadOk = 0;
        }
        
        if ($_FILES["member_image"]["size"] > 5000000) {
            $update_error = "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $update_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["member_image"]["tmp_name"], $target_file)) {
                $sql = "UPDATE management_team SET name='$name', position='$position', department='$department', description='$description', email='$email', phone='$phone', image_path='$target_file', facebook_url='$facebook', twitter_url='$twitter', linkedin_url='$linkedin', instagram_url='$instagram', website_url='$website', display_order='$display_order', is_active='$is_active' WHERE id='$member_id'";
            } else {
                $update_error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    if(mysqli_query($conn, $sql)) {
        $update_success = "Team member updated successfully!";
        // Refresh member data
        $result = mysqli_query($conn, "SELECT * FROM management_team WHERE id = '$member_id'");
        $member = mysqli_fetch_assoc($result);
    } else {
        $update_error = "Error updating team member: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Team Member | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    
    <style>
        .edit-management-container {
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        .current-image-container {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        .current-image {
            max-width: 180px;
            max-height: 250px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3498db;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .member-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .info-item {
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-item i {
            width: 20px;
            margin-right: 10px;
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
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Edit Team Member</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Success/Error Messages -->
                                <?php if(isset($update_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $update_success ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($update_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $update_error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="edit-management-container">
                                    <!-- Member Info Summary -->
                                    <div class="member-info-card">
                                        <div class="row align-items-center">
                                            <div class="col-md-3 text-center">
                                                <img src="<?= htmlspecialchars($member['image_path']) ?>" 
                                                     alt="<?= htmlspecialchars($member['name']) ?>" 
                                                     class="current-image">
                                            </div>
                                            <div class="col-md-9">
                                                <h3 class="text-white"><?= htmlspecialchars($member['name']) ?></h3>
                                                <p class="mb-2 text-info"><?= htmlspecialchars($member['position']) ?></p>
                                                <?php if($member['department']): ?>
                                                    <p class="mb-2"><i class="fas fa-building"></i> <?= htmlspecialchars($member['department']) ?></p>
                                                <?php endif; ?>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <?php if($member['email']): ?>
                                                            <div class="info-item"><i class="fas fa-envelope"></i> <?= htmlspecialchars($member['email']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if($member['phone']): ?>
                                                            <div class="info-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($member['phone']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-item"><i class="fas fa-sort-numeric-down"></i> Display Order: <?= $member['display_order'] ?></div>
                                                        <div class="info-item"><i class="fas fa-circle"></i> Status: 
                                                            <span class="badge bg-<?= $member['is_active'] ? 'success' : 'secondary' ?>">
                                                                <?= $member['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Member Form -->
                                    <div class="member-form-section">
                                        <h4 class="mb-4">Edit Member Details</h4>
                                        
                                        <ul class="nav nav-tabs mb-4" id="editMemberTab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="edit-basic-tab" data-bs-toggle="tab" data-bs-target="#edit-basic" type="button" role="tab">Basic Info</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="edit-contact-tab" data-bs-toggle="tab" data-bs-target="#edit-contact" type="button" role="tab">Contact & Social</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="edit-settings-tab" data-bs-toggle="tab" data-bs-target="#edit-settings" type="button" role="tab">Settings</button>
                                            </li>
                                        </ul>
                                        
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="tab-content" id="editMemberTabContent">
                                                <!-- Basic Information Tab -->
                                                <div class="tab-pane fade show active" id="edit-basic" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="name" class="form-label">Full Name *</label>
                                                                <input type="text" class="form-control" id="name" name="name" 
                                                                       value="<?= htmlspecialchars($member['name']) ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="position" class="form-label">Position *</label>
                                                                <input type="text" class="form-control" id="position" name="position" 
                                                                       value="<?= htmlspecialchars($member['position']) ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="department" class="form-label">Department</label>
                                                                <input type="text" class="form-control" id="department" name="department" 
                                                                       value="<?= htmlspecialchars($member['department']) ?>"
                                                                       placeholder="e.g., Executive, Marketing, Sales">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="member_image" class="form-label">Update Profile Image</label>
                                                                <input class="form-control" type="file" name="member_image" id="member_image" 
                                                                       accept="image/*">
                                                                <div class="form-text">Leave empty to keep current image</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Description/Bio</label>
                                                        <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($member['description']) ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <!-- Contact & Social Tab -->
                                                <div class="tab-pane fade" id="edit-contact" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="email" class="form-label">Email Address</label>
                                                                <input type="email" class="form-control" id="email" name="email"
                                                                       value="<?= htmlspecialchars($member['email']) ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="phone" class="form-label">Phone Number</label>
                                                                <input type="text" class="form-control" id="phone" name="phone"
                                                                       value="<?= htmlspecialchars($member['phone']) ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <h6 class="section-title">Social Media Links</h6>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="facebook_url" class="form-label">Facebook</label>
                                                                <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                                                       value="<?= htmlspecialchars($member['facebook_url']) ?>"
                                                                       placeholder="https://facebook.com/username">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="twitter_url" class="form-label">Twitter</label>
                                                                <input type="url" class="form-control" id="twitter_url" name="twitter_url" 
                                                                       value="<?= htmlspecialchars($member['twitter_url']) ?>"
                                                                       placeholder="https://twitter.com/username">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="linkedin_url" class="form-label">LinkedIn</label>
                                                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                                                       value="<?= htmlspecialchars($member['linkedin_url']) ?>"
                                                                       placeholder="https://linkedin.com/in/username">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="instagram_url" class="form-label">Instagram</label>
                                                                <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                                                       value="<?= htmlspecialchars($member['instagram_url']) ?>"
                                                                       placeholder="https://instagram.com/username">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="website_url" class="form-label">Website/Portfolio</label>
                                                        <input type="url" class="form-control" id="website_url" name="website_url" 
                                                               value="<?= htmlspecialchars($member['website_url']) ?>"
                                                               placeholder="https://example.com">
                                                    </div>
                                                </div>
                                                
                                                <!-- Settings Tab -->
                                                <div class="tab-pane fade" id="edit-settings" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="display_order" class="form-label">Display Order</label>
                                                                <input type="number" class="form-control" id="display_order" name="display_order" 
                                                                       value="<?= $member['display_order'] ?>" min="0">
                                                                <div class="form-text">Lower numbers appear first</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch mt-4">
                                                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                                           <?= $member['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="is_active">Active Member</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="card mt-4">
                                                        <div class="card-header bg-light">
                                                            <h6 class="mb-0">Member Statistics</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p class="mb-1"><strong>Created:</strong> <?= date('F j, Y g:i A', strtotime($member['created_at'])) ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p class="mb-1"><strong>Last Updated:</strong> <?= date('F j, Y g:i A', strtotime($member['updated_at'])) ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2 mt-4">
                                                <button type="submit" name="update_member" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Update Member
                                                </button>
                                                
                                                <a href="management.php" class="btn btn-secondary">
                                                    <i class="fas fa-arrow-left me-2"></i> Back to Management
                                                </a>
                                                
                                                <a href="show-products.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-box me-2"></i> Back to Products
                                                </a>
                                            </div>
                                        </form>
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

    <script>
        // Tab functionality for edit page
        document.addEventListener('DOMContentLoaded', function() {
            var triggerTabList = [].slice.call(document.querySelectorAll('#editMemberTab button'))
            triggerTabList.forEach(function (triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl)
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault()
                    tabTrigger.show()
                })
            });
        });
    </script>
</body>
</html>