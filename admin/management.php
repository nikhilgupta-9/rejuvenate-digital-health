<?php
// Database connection
include "db-conn.php";

// Handle form submissions for adding new team member
if(isset($_POST['add_member'])) {
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
    
    // Handle image upload
    $target_dir = "uploads/management/";
    if(!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $image_name = time() . '_' . basename($_FILES["member_image"]["name"]);
    $target_file = $target_dir . $image_name;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["member_image"]["tmp_name"]);
    if($check !== false) {
        $uploadOk = 1;
    } else {
        $upload_error = "File is not an image.";
        $uploadOk = 0;
    }
    
    // Check file size (5MB max)
    if ($_FILES["member_image"]["size"] > 5000000) {
        $upload_error = "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        $upload_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["member_image"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO management_team (name, position, department, description, email, phone, image_path, facebook_url, twitter_url, linkedin_url, instagram_url, website_url, display_order, is_active) 
                    VALUES ('$name', '$position', '$department', '$description', '$email', '$phone', '$target_file', '$facebook', '$twitter', '$linkedin', '$instagram', '$website', '$display_order', '$is_active')";
            
            if(mysqli_query($conn, $sql)) {
                $upload_success = "Team member added successfully!";
            } else {
                $upload_error = "Error adding team member: " . mysqli_error($conn);
            }
        } else {
            $upload_error = "Sorry, there was an error uploading your file.";
        }
    }
}

// Handle member deletion
if(isset($_POST['delete_member'])) {
    $member_id = mysqli_real_escape_string($conn, $_POST['member_id']);
    
    // Get image path to delete file
    $sql = "SELECT image_path FROM management_team WHERE id = '$member_id'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    if($row && file_exists($row['image_path'])) {
        unlink($row['image_path']);
    }
    
    $delete_sql = "DELETE FROM management_team WHERE id = '$member_id'";
    if(mysqli_query($conn, $delete_sql)) {
        $delete_success = "Team member deleted successfully!";
    } else {
        $delete_error = "Error deleting team member: " . mysqli_error($conn);
    }
}

// Fetch all team members
$members_sql = "SELECT * FROM management_team ORDER BY display_order ASC, name ASC";
$members_result = mysqli_query($conn, $members_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Management Team | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    
    <style>
        .management-container {
            padding: 20px;
        }
        .member-form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        .member-card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
            border: 1px solid #e9ecef;
        }
        .member-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        .member-img-container {
            position: relative;
            height: 250px;
            overflow: hidden;
        }
        .member-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .member-card:hover .member-img {
            transform: scale(1.05);
        }
        .member-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background: #28a745;
            color: white;
        }
        .status-inactive {
            background: #6c757d;
            color: white;
        }
        .member-content {
            padding: 20px;
        }
        .member-name {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .member-position {
            font-size: 16px;
            color: #3498db;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .member-department {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 15px;
            padding: 4px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            display: inline-block;
        }
        .member-description {
            color: #5a6c7d;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .member-contact {
            margin-bottom: 15px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
            color: #6c757d;
        }
        .contact-item i {
            width: 20px;
            margin-right: 10px;
            color: #3498db;
        }
        .social-links {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .social-link {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .social-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .facebook { background: #3b5998; }
        .twitter { background: #1da1f2; }
        .linkedin { background: #0077b5; }
        .instagram { background: #e4405f; }
        .website { background: #28a745; }
        .member-actions {
            display: flex;
            gap: 10px;
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .nav-tabs .nav-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
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
                                        <h2 class="m-0">Management Team</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Success/Error Messages -->
                                <?php if(isset($upload_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $upload_success ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($upload_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $upload_error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($delete_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $delete_success ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="management-container">
                                    <!-- Add Member Form -->
                                    <div class="member-form-section">
                                        <h4 class="mb-4">Add New Team Member</h4>
                                        
                                        <ul class="nav nav-tabs mb-4" id="memberTab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Basic Info</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">Contact & Social</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Settings</button>
                                            </li>
                                        </ul>
                                        
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="tab-content" id="memberTabContent">
                                                <!-- Basic Information Tab -->
                                                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="name" class="form-label">Full Name *</label>
                                                                <input type="text" class="form-control" id="name" name="name" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="position" class="form-label">Position *</label>
                                                                <input type="text" class="form-control" id="position" name="position" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="department" class="form-label">Department</label>
                                                                <input type="text" class="form-control" id="department" name="department" 
                                                                       placeholder="e.g., Executive, Marketing, Sales">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="member_image" class="form-label">Profile Image *</label>
                                                                <input class="form-control" type="file" name="member_image" id="member_image" 
                                                                       accept="image/*" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Description/Bio</label>
                                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                                  placeholder="Brief description about the team member..."></textarea>
                                                    </div>
                                                </div>
                                                
                                                <!-- Contact & Social Tab -->
                                                <div class="tab-pane fade" id="contact" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="email" class="form-label">Email Address</label>
                                                                <input type="email" class="form-control" id="email" name="email">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="phone" class="form-label">Phone Number</label>
                                                                <input type="text" class="form-control" id="phone" name="phone">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <h6 class="section-title">Social Media Links</h6>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="facebook_url" class="form-label">Facebook</label>
                                                                <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                                                       placeholder="https://facebook.com/username">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="twitter_url" class="form-label">Twitter</label>
                                                                <input type="url" class="form-control" id="twitter_url" name="twitter_url" 
                                                                       placeholder="https://twitter.com/username">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="linkedin_url" class="form-label">LinkedIn</label>
                                                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                                                       placeholder="https://linkedin.com/in/username">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="instagram_url" class="form-label">Instagram</label>
                                                                <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                                                       placeholder="https://instagram.com/username">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="website_url" class="form-label">Website/Portfolio</label>
                                                        <input type="url" class="form-control" id="website_url" name="website_url" 
                                                               placeholder="https://example.com">
                                                    </div>
                                                </div>
                                                
                                                <!-- Settings Tab -->
                                                <div class="tab-pane fade" id="settings" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="display_order" class="form-label">Display Order</label>
                                                                <input type="number" class="form-control" id="display_order" name="display_order" 
                                                                       value="0" min="0">
                                                                <div class="form-text">Lower numbers appear first</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch mt-4">
                                                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                                                    <label class="form-check-label" for="is_active">Active Member</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2 mt-4">
                                                <button type="submit" name="add_member" class="btn btn-primary">
                                                    <i class="fas fa-user-plus me-2"></i>Add Team Member
                                                </button>
                                                
                                                <a href="show-products.php" class="btn btn-outline-secondary ms-auto">
                                                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Management Team Display -->
                                    <h4 class="mt-5 mb-4">Team Members (<?= mysqli_num_rows($members_result) ?>)</h4>
                                    
                                    <?php if($members_result && mysqli_num_rows($members_result) > 0): ?>
                                        <div class="management-grid">
                                            <?php while($member = mysqli_fetch_assoc($members_result)): ?>
                                                <div class="member-card">
                                                    <div class="member-img-container">
                                                        <img src="<?= htmlspecialchars($member['image_path']) ?>" 
                                                             alt="<?= htmlspecialchars($member['name']) ?>" 
                                                             class="member-img">
                                                        <span class="member-status <?= $member['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                            <?= $member['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="member-content">
                                                        <div class="member-name"><?= htmlspecialchars($member['name']) ?></div>
                                                        <div class="member-position"><?= htmlspecialchars($member['position']) ?></div>
                                                        
                                                        <?php if($member['department']): ?>
                                                            <div class="member-department"><?= htmlspecialchars($member['department']) ?></div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if($member['description']): ?>
                                                            <div class="member-description"><?= htmlspecialchars($member['description']) ?></div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="member-contact">
                                                            <?php if($member['email']): ?>
                                                                <div class="contact-item">
                                                                    <i class="fas fa-envelope"></i>
                                                                    <span><?= htmlspecialchars($member['email']) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($member['phone']): ?>
                                                                <div class="contact-item">
                                                                    <i class="fas fa-phone"></i>
                                                                    <span><?= htmlspecialchars($member['phone']) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Social Links -->
                                                        <div class="social-links">
                                                            <?php if($member['facebook_url']): ?>
                                                                <a href="<?= htmlspecialchars($member['facebook_url']) ?>" target="_blank" class="social-link facebook" title="Facebook">
                                                                    <i class="fab fa-facebook-f"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($member['twitter_url']): ?>
                                                                <a href="<?= htmlspecialchars($member['twitter_url']) ?>" target="_blank" class="social-link twitter" title="Twitter">
                                                                    <i class="fab fa-twitter"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($member['linkedin_url']): ?>
                                                                <a href="<?= htmlspecialchars($member['linkedin_url']) ?>" target="_blank" class="social-link linkedin" title="LinkedIn">
                                                                    <i class="fab fa-linkedin-in"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($member['instagram_url']): ?>
                                                                <a href="<?= htmlspecialchars($member['instagram_url']) ?>" target="_blank" class="social-link instagram" title="Instagram">
                                                                    <i class="fab fa-instagram"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($member['website_url']): ?>
                                                                <a href="<?= htmlspecialchars($member['website_url']) ?>" target="_blank" class="social-link website" title="Website">
                                                                    <i class="fas fa-globe"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="member-actions">
                                                            <a href="edit_management.php?id=<?= $member['id'] ?>" class="btn btn-warning btn-sm">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <form action="" method="post" onsubmit="return confirm('Are you sure you want to delete this team member?');">
                                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                                <button type="submit" name="delete_member" class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                            <span class="ms-auto text-muted small">
                                                                Order: <?= $member['display_order'] ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-users fa-2x mb-3"></i><br>
                                            No team members found. Add your first team member to get started!
                                        </div>
                                    <?php endif; ?>
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
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs
            var triggerTabList = [].slice.call(document.querySelectorAll('#memberTab button'))
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