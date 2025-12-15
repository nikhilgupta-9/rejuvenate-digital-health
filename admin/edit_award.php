<?php
// Database connection
include "db-conn.php";

// Check if award ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: awards.php");
    exit();
}

$award_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch award data
$sql = "SELECT * FROM awards WHERE id = '$award_id'";
$result = mysqli_query($conn, $sql);
$award = mysqli_fetch_assoc($result);

if(!$award) {
    header("Location: awards.php");
    exit();
}

// Handle form submission
if(isset($_POST['update_award'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $award_date = mysqli_real_escape_string($conn, $_POST['award_date']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    $sql = "UPDATE awards SET title='$title', description='$description', award_date='$award_date', category='$category' WHERE id='$award_id'";
    
    // Handle image update if new image is uploaded
    if(!empty($_FILES["award_image"]["name"])) {
        // Delete old image
        if(file_exists($award['image_path'])) {
            unlink($award['image_path']);
        }
        
        // Upload new image
        $target_dir = "uploads/awards/";
        $image_name = time() . '_' . basename($_FILES["award_image"]["name"]);
        $target_file = $target_dir . $image_name;
        
        // Validate image
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES["award_image"]["tmp_name"]);
        if($check === false) {
            $update_error = "File is not an image.";
            $uploadOk = 0;
        }
        
        if ($_FILES["award_image"]["size"] > 5000000) {
            $update_error = "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "webp") {
            $update_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["award_image"]["tmp_name"], $target_file)) {
                $sql = "UPDATE awards SET title='$title', description='$description', image_path='$target_file', award_date='$award_date', category='$category' WHERE id='$award_id'";
            } else {
                $update_error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    if(mysqli_query($conn, $sql)) {
        $update_success = "Award updated successfully!";
        // Refresh award data
        $result = mysqli_query($conn, "SELECT * FROM awards WHERE id = '$award_id'");
        $award = mysqli_fetch_assoc($result);
    } else {
        $update_error = "Error updating award: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Award | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    
    <style>
        .edit-award-container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .award-form-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
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
            max-width: 300px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            margin-top: 20px;
        }
        .preview-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 10px;
            display: block;
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
                                        <h2 class="m-0">Edit Award</h2>
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

                                <div class="edit-award-container">
                                    <!-- Edit Award Form -->
                                    <div class="award-form-section">
                                        <h4 class="mb-4">Edit Award: <?= htmlspecialchars($award['title']) ?></h4>
                                        
                                        <!-- Current Image Preview -->
                                        <div class="current-image-container">
                                            <span class="preview-label">Current Image:</span>
                                            <img src="<?= htmlspecialchars($award['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($award['title']) ?>" 
                                                 class="current-image">
                                        </div>
                                        
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="title" class="form-label">Award Title *</label>
                                                        <input type="text" class="form-control" id="title" name="title" 
                                                               value="<?= htmlspecialchars($award['title']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="category" class="form-label">Category</label>
                                                        <input type="text" class="form-control" id="category" name="category" 
                                                               value="<?= htmlspecialchars($award['category']) ?>" 
                                                               placeholder="e.g., Excellence, Innovation, Quality">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($award['description']) ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="award_date" class="form-label">Award Date</label>
                                                        <input type="date" class="form-control" id="award_date" name="award_date" 
                                                               value="<?= $award['award_date'] ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="award_image" class="form-label">Update Image</label>
                                                        <input class="form-control" type="file" name="award_image" id="award_image" 
                                                               accept="image/*">
                                                        <div class="form-text">Leave empty to keep current image. Max file size: 5MB</div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-actions">
                                                <button type="submit" name="update_award" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Update Award
                                                </button>
                                                
                                                <a href="awards.php" class="btn btn-secondary">
                                                    <i class="fas fa-arrow-left me-2"></i> Back to Awards
                                                </a>
                                                
                                                <a href="show-products.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-box me-2"></i> Back to Products
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Award Information Card -->
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Award Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Created:</strong> <?= date('F j, Y g:i A', strtotime($award['created_at'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Last Updated:</strong> <?= date('F j, Y g:i A', strtotime($award['updated_at'])) ?></p>
                                                </div>
                                            </div>
                                            <p><strong>Image Path:</strong> <code><?= htmlspecialchars($award['image_path']) ?></code></p>
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

    <script>
        // Image preview for new image upload
        document.getElementById('award_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create new preview
                    const newPreview = document.createElement('div');
                    newPreview.className = 'current-image-container mt-3';
                    newPreview.innerHTML = `
                        <span class="preview-label">New Image Preview:</span>
                        <img src="${e.target.result}" class="current-image" alt="New image preview">
                    `;
                    
                    // Remove existing new preview if any
                    const existingNewPreview = document.querySelector('.current-image-container .preview-label:contains("New Image Preview")');
                    if (existingNewPreview) {
                        existingNewPreview.parentElement.remove();
                    }
                    
                    // Add new preview after file input
                    document.querySelector('.mb-3:last-child').appendChild(newPreview);
                }
                reader.readAsDataURL(file);
            }
        });

        // Add contains method for text search
        if (!String.prototype.contains) {
            String.prototype.contains = function(str) {
                return this.indexOf(str) !== -1;
            };
        }
    </script>
</body>
</html>