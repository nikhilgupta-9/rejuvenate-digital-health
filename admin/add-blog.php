<?php
include "db-conn.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Generate slug from title
    $slug_url = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    
    // File upload handling
    $upload_success = false;
    $image_name = '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/blogs/";
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Get file info
        $fileName = basename($_FILES['image']['name']);
        $fileTmp = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $newFileName = uniqid('blog_', true) . '.' . $fileType;
        $uploadPath = $uploadDir . $newFileName;

        // Validate file
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileType, $allowedTypes)) {
            // Validate file size (5MB max)
            if ($fileSize <= 5000000) {
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    $upload_success = true;
                    $image_name = $newFileName;
                } else {
                    $_SESSION['error'] = "Failed to upload image. Check directory permissions.";
                }
            } else {
                $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG, WEBP & GIF files are allowed.";
        }
    } else {
        $_SESSION['error'] = "Please select a valid image file.";
    }
    
    // Only proceed with database insert if upload was successful or no file was uploaded
    if ($upload_success || empty($_FILES['image']['name'])) {
        // Insert into database with prepared statement
        $sql = "INSERT INTO blogs (title, content, slug_url, image, author, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssss", $title, $content, $slug_url, $image_name, $author, $status);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Blog added successfully!";
            header("Location: view-all-blog.php");
            exit();
        } else {
            $_SESSION['error'] = "Database error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add New Blog | WASA Engineering</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    
    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="text-center">Add New Blog</h2>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="white_card_body">
                                <!-- Display error/success messages -->
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                                <?php endif; ?>
                                
                                <div class="col-md-12 mb-4">
                                    <a href="view-all-blog.php" class="btn btn-danger">
                                        <i class="fas fa-list"></i> View All Blogs
                                    </a>
                                </div>
                                
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" class="p-4 shadow bg-white">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label class="form-label">Title:</label>
                                                    <input type="text" name="title" class="form-control" required 
                                                           value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Content:</label>
                                                    <textarea name="content" class="form-control" rows="10" id="editor" required>
                                                        <?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?>
                                                    </textarea>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Author:</label>
                                                    <input type="text" name="author" class="form-control" required
                                                           value="<?= isset($_POST['author']) ? htmlspecialchars($_POST['author']) : '' ?>">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Status:</label>
                                                    <select name="status" class="form-control select2" required>
                                                        <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>Draft</option>
                                                        <option value="published" <?= (!isset($_POST['status']) || (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : '') ?>>Published</option>
                                                        <option value="archived" <?= (isset($_POST['status']) && $_POST['status'] == 'archived') ? 'selected' : '' ?>>Archived</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Featured Image:</label>
                                                    <input type="file" name="image" class="form-control" required accept="image/*">
                                                    <small class="text-muted">Max size: 5MB (JPG, PNG, WEBP, GIF)</small>
                                                    <div class="mt-2">
                                                        <img id="imagePreview" src="#" alt="Image preview" style="max-width: 100%; display: none;">
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <button type="submit" class="btn btn-success btn-block">
                                                        <i class="fas fa-plus"></i> Add Blog
                                                    </button>
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
        </div>

        <?php include "footer.php"; ?>
        
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            // Initialize CKEditor
            CKEDITOR.replace('editor', {
                toolbar: [
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', 'Blockquote'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['Image', 'Table'] },
                    { name: 'document', items: ['Source'] }
                ],
                height: 500
            });
            
            // Initialize Select2
            $(document).ready(function() {
                $('.select2').select2({
                    minimumResultsForSearch: Infinity
                });
                
                // Image preview
                $('input[type="file"]').change(function(e) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview').attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(this.files[0]);
                });
            });
        </script>
    </section>
</body>
</html>