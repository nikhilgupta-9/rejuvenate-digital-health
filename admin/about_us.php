<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db-conn.php";

// Initialize variables
$title = '';
$desc = '';
$image = '';
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        
        // Validate required fields
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required");
        }

        // Handle file upload
        // Handle file upload
$image_path = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/about_us/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = $_FILES['image']['type'];

    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Only JPG, PNG, GIF, and WEBP images are allowed");
    }
    
    $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $file_name = 'about-us-' . time() . '.' . $file_ext;
    $target_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
        throw new Exception("Failed to upload image");
    }
    
    $image_path = $target_path;
}

        // Check if an entry already exists
        $sql = "SELECT * FROM about_us LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            // Update existing entry
            $row = mysqli_fetch_assoc($result);
            $current_image = $row['image_url'];
            
            if (!empty($image_path)) {
                // Delete old image if it exists
                if (!empty($current_image) && file_exists($current_image)) {
                    unlink($current_image);
                }
                $stmt = $conn->prepare("UPDATE about_us SET title = ?, content = ?, image_url = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('sssi', $title, $content, $image_path, $row['id']);
            } else {
                $stmt = $conn->prepare("UPDATE about_us SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('ssi', $title, $content, $row['id']);
            }
        } else {
            // Insert new entry
            $stmt = $conn->prepare("INSERT INTO about_us (title, content, image_url, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->bind_param('sss', $title, $content, $image_path);
        }
        
        if ($stmt->execute()) {
            $success_message = "About Us content saved successfully!";
        } else {
            throw new Exception("Database error: " . $conn->error);
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch the latest 'About Us' data
$sql = "SELECT * FROM about_us ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $title = $row['title'];
    $desc = $row['content'];
    $image = $row['image_url'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>About Us Management | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .about-form {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #7367f0;
            box-shadow: 0 0 0 3px rgba(115,103,240,.15);
        }
        .image-preview {
            max-width: 300px;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 4px;
            border: 1px dashed #ddd;
            padding: 5px;
        }
        .file-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-upload-label {
            display: inline-block;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
        }
        .file-upload-label:hover {
            background-color: #e9ecef;
        }
        .btn-primary {
            background-color: #7367f0;
            border-color: #7367f0;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #5d50e6;
            border-color: #5d50e6;
        }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="page-header mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <h2 class="mb-0">About Us Content</h2>
                               
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-12">
                        <div class="about-form">
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= $success_message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= $error_message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="title" required value="<?= htmlspecialchars($title) ?>">
                                    </div>
                                    
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Content <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="content" id="aboutContent" rows="8" required><?= htmlspecialchars($desc) ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Featured Image</label>
                                        <div class="file-upload mb-3">
                                            <label class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Choose Image
                                                <input type="file" name="image" class="file-upload-input" accept="image/*">
                                            </label>
                                            <small class="d-block text-muted mt-1">Recommended size: 1200x800px (JPG, PNG, GIF, WEBP)</small>
                                        </div>
                                        
                                        <?php if (!empty($image)): ?>
                                            <div class="current-image">
                                                <p class="mb-2">Current Image:</p>
                                                <img src="<?= $image ?>" alt="Current About Us Image" class="image-preview">
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage">
                                                    <label class="form-check-label" for="removeImage">
                                                        Remove current image
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-2"></i> Save Changes
                                        </button>
                                        <a href="dashboard.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-2"></i> Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
</section>
        <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
        <script>
            CKEDITOR.replace('aboutContent');
            
            // Preview image before upload
            document.querySelector('input[name="image"]').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let preview = document.querySelector('.image-preview');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.className = 'image-preview';
                            document.querySelector('.file-upload').after(preview);
                        }
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        </script>
    </body>
</html>