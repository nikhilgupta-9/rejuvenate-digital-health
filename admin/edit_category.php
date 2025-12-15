<?php
session_start();
include "db-conn.php";
include "functions.php";

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get department id from URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $cat_id = intval($_GET['id']);
    
    // Fetch department details
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $department = $result->fetch_assoc();
    $stmt->close();
    
    if (!$department) {
        $_SESSION['error'] = "Department not found.";
        header("Location: view-categories.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid department ID.";
    header("Location: view-categories.php");
    exit();
}

// Process form submission for updating the department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_department'])) {
    $cate_name = trim($_POST['cate_name']);
    $meta_title = trim($_POST['meta_title']);
    $meta_desc = trim($_POST['meta_desc']);
    $meta_key = trim($_POST['meta_key']);
    $status = intval($_POST['status']);
    
    // Generate slug from category name
    $slug_url = generateSlug($cate_name);
    
    // Handle image upload
    $image_name = $department['image']; // Keep current image by default
    
    if (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/category/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $_FILES['imageUpload']['name'];
        $file_tmp = $_FILES['imageUpload']['tmp_name'];
        $file_size = $_FILES['imageUpload']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                $new_file_name = uniqid() . '_' . $slug_url . '.' . $file_ext;
                $upload_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Delete old image if exists
                    if (!empty($department['image']) && file_exists($upload_dir . $department['image'])) {
                        unlink($upload_dir . $department['image']);
                    }
                    $image_name = $new_file_name;
                }
            }
        }
    }
    
    // Update department in database
    $stmt = $conn->prepare("UPDATE categories SET categories = ?, meta_title = ?, meta_desc = ?, meta_key = ?, image = ?, slug_url = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssssii", $cate_name, $meta_title, $meta_desc, $meta_key, $image_name, $slug_url, $status, $cat_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Department updated successfully!";
        header("Location: view-categories.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating department: " . $conn->error;
    }
    $stmt->close();
}

// Function to generate slug
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}
?>
<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Department | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    
    <style>
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .file-upload-label {
            display: block;
            padding: 1rem;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .file-upload-label:hover {
            border-color: #4361ee;
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .image-preview-container {
            margin-top: 1rem;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .current-image {
            max-width: 150px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        
        .section-divider {
            border-bottom: 2px solid #4361ee;
            padding-bottom: 10px;
            margin-bottom: 20px;
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

        <div class="main_content_iner ">
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Edit Department</h2>
                                        <p class="text-muted mb-0">Update medical department information</p>
                                    </div>
                                    <div class="add_button ms-2">
                                        <a href="view-categories.php" class="btn_1 btn-outline-primary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Departments
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Messages -->
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger mx-3 mt-2">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?= htmlspecialchars($_SESSION['error']) ?>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <div class="white_card_body">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title section-divider">Department Information</h4>
                                        <form method="post" action="" enctype="multipart/form-data">
                                            <input type="hidden" name="cat_id" value="<?= $department['id'] ?>">

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="cate_name" class="form-label">Department Name *</label>
                                                        <input type="text" class="form-control" name="cate_name" id="cate_name"
                                                            value="<?= htmlspecialchars($department['categories']) ?>"
                                                            placeholder="e.g., Cardiology, Neurology" required>
                                                        <small class="form-text text-muted">Name of the medical department</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="meta_title" class="form-label">Meta Title *</label>
                                                        <input type="text" class="form-control" name="meta_title" id="meta_title"
                                                            value="<?= htmlspecialchars($department['meta_title']) ?>"
                                                            placeholder="SEO friendly title" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="meta_key" class="form-label">Meta Keywords *</label>
                                                        <input type="text" class="form-control" name="meta_key" id="meta_key" 
                                                            value="<?= htmlspecialchars($department['meta_key']) ?>"
                                                            placeholder="medical, healthcare, doctor, treatment" required>
                                                        <small class="form-text text-muted">Comma separated keywords for SEO</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="meta_desc" class="form-label">Meta Description *</label>
                                                        <textarea class="form-control" name="meta_desc" id="meta_desc" 
                                                            rows="2" placeholder="Brief description for search engines" required><?= htmlspecialchars($department['meta_desc']) ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="status" class="form-label d-block">Status *</label>
                                                        <select id="status" name="status" class="form-control nice-select" required>
                                                            <option value="1" <?= $department['status'] == 1 ? 'selected' : '' ?>>Active</option>
                                                            <option value="0" <?= $department['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Department Image</label>
                                                        
                                                        <!-- Current Image -->
                                                        <?php if (!empty($department['image'])): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label text-muted">Current Image:</label>
                                                                <div>
                                                                    <img src="uploads/category/<?= htmlspecialchars($department['image']) ?>" 
                                                                         alt="<?= htmlspecialchars($department['categories']) ?>" 
                                                                         class="current-image">
                                                                    <div class="mt-1">
                                                                        <small class="text-muted"><?= htmlspecialchars($department['image']) ?></small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="mb-3">
                                                                <div class="text-muted">
                                                                    <i class="fas fa-hospital-symbol fa-2x mb-2"></i>
                                                                    <p class="mb-0">No image uploaded</p>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- New Image Upload -->
                                                        <div class="file-upload-wrapper">
                                                            <label for="imageUpload" class="file-upload-label">
                                                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
                                                                <p class="mb-1">Click to upload new department image</p>
                                                                <small class="text-muted">PNG, JPG, GIF up to 5MB</small>
                                                            </label>
                                                            <input type="file" class="form-control d-none" name="imageUpload" 
                                                                id="imageUpload" accept="image/*" onchange="previewImage(this)" />
                                                        </div>
                                                        <div class="image-preview-container" id="imagePreviewContainer">
                                                            <p class="text-muted mb-2">New Image Preview:</p>
                                                            <img id="imagePreview" class="image-preview" />
                                                            <button type="button" class="btn btn-sm btn-danger mt-2" 
                                                                onclick="removeImage()">
                                                                <i class="fas fa-trash me-1"></i> Remove New Image
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between mt-4">
                                                <a href="view-categories.php" class="btn btn-secondary">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn_1" name="update_department">
                                                    <i class="fas fa-save me-1"></i> Update Department
                                                </button>
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

        <!-- Include your existing JS libraries -->
        <script src="assets/js/jquery-3.7.1.min.js"></script>
        <script src="assets/vendors/niceselect/js/jquery.nice-select.min.js"></script>

        <script>
            // Initialize nice select
            $(document).ready(function() {
                $('.nice-select').niceSelect();
            });

            // Image preview functionality
            function previewImage(input) {
                const previewContainer = document.getElementById('imagePreviewContainer');
                const preview = document.getElementById('imagePreview');
                
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        previewContainer.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(input.files[0]);
                }
            }
            
            // Remove image selection
            function removeImage() {
                document.getElementById('imageUpload').value = '';
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
            
            // Auto-generate slug from department name
            document.getElementById('cate_name').addEventListener('blur', function() {
                const departmentName = this.value.trim();
                if (departmentName) {
                    // Simple slug generation (you can enhance this)
                    const slug = departmentName
                        .toLowerCase()
                        .replace(/[^a-z0-9 -]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                    
                    // If you have a slug_url field, uncomment below:
                    // document.getElementById('slug_url').value = slug;
                }
            });
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const departmentName = document.getElementById('cate_name').value.trim();
                const metaTitle = document.getElementById('meta_title').value.trim();
                
                if (!departmentName) {
                    e.preventDefault();
                    alert('Please enter department name');
                    document.getElementById('cate_name').focus();
                    return false;
                }
                
                if (!metaTitle) {
                    e.preventDefault();
                    alert('Please enter meta title');
                    document.getElementById('meta_title').focus();
                    return false;
                }
                
                return true;
            });
        </script>
    </section>
</body>
</html>