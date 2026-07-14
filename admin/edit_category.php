<?php
require_once __DIR__ . '/db-conn.php';
include "functions.php";
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

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
    
    // Validate required fields
    if (empty($cate_name)) {
        $_SESSION['error'] = "Department name is required.";
    } elseif (empty($meta_title)) {
        $_SESSION['error'] = "Meta title is required.";
    } else {
        // Generate slug from category name if slug_url field exists
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
                } else {
                    $_SESSION['error'] = "File size too large. Maximum 5MB allowed.";
                }
            } else {
                $_SESSION['error'] = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP";
            }
        }
        
        // Check if we have any error before updating
        if (!isset($_SESSION['error'])) {
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
    }
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
            display: none;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            object-fit: cover;
        }
        
        .current-image {
            max-width: 150px;
            max-height: 120px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            object-fit: cover;
        }
        
        .section-divider {
            border-bottom: 2px solid #4361ee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .current-image-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .alert {
            margin: 15px 20px 0;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
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

                            <!-- Display Success/Error Messages -->
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($_SESSION['success']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?= htmlspecialchars($_SESSION['error']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <div class="white_card_body">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title section-divider">Department Information</h4>
                                        
                                        <form method="post" action="" enctype="multipart/form-data" id="departmentForm">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="cate_name" class="form-label required-field">Department Name</label>
                                                        <input type="text" class="form-control" name="cate_name" id="cate_name"
                                                            value="<?= htmlspecialchars($department['categories']) ?>"
                                                            placeholder="e.g., Cardiology, Neurology" required>
                                                        <small class="form-text text-muted">Name of the medical department</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="cate_id" class="form-label">Category ID</label>
                                                        <input type="text" class="form-control" 
                                                            value="<?= htmlspecialchars($department['cate_id']) ?>" disabled>
                                                        <small class="form-text text-muted">Auto-generated unique identifier</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="meta_title" class="form-label required-field">Meta Title</label>
                                                        <input type="text" class="form-control" name="meta_title" id="meta_title"
                                                            value="<?= htmlspecialchars($department['meta_title']) ?>"
                                                            placeholder="SEO friendly title" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="meta_key" class="form-label">Meta Keywords</label>
                                                        <input type="text" class="form-control" name="meta_key" id="meta_key" 
                                                            value="<?= htmlspecialchars($department['meta_key']) ?>"
                                                            placeholder="medical, healthcare, doctor, treatment">
                                                        <small class="form-text text-muted">Comma separated keywords for SEO</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="status" class="form-label">Status</label>
                                                        <select id="status" name="status" class="form-control" required>
                                                            <option value="1" <?= $department['status'] == 1 ? 'selected' : '' ?>>Active</option>
                                                            <option value="0" <?= $department['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="meta_desc" class="form-label">Meta Description</label>
                                                        <textarea class="form-control" name="meta_desc" id="meta_desc" 
                                                            rows="3" placeholder="Brief description for search engines"><?= htmlspecialchars($department['meta_desc']) ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">Department Image</label>
                                                        
                                                        <!-- Current Image -->
                                                        <?php if (!empty($department['image'])): ?>
                                                            <div class="current-image-container">
                                                                <label class="form-label text-muted">Current Image:</label>
                                                                <div class="text-center">
                                                                    <img src="../uploads/category/<?= htmlspecialchars($department['image']) ?>" 
                                                                         alt="<?= htmlspecialchars($department['categories']) ?>" 
                                                                         class="current-image">
                                                                    <div class="mt-2">
                                                                        <small class="text-muted"><?= htmlspecialchars($department['image']) ?></small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="current-image-container text-center">
                                                                <i class="fas fa-image fa-3x text-muted mb-2"></i>
                                                                <p class="mb-0 text-muted">No image uploaded</p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- New Image Upload -->
                                                        <div class="file-upload-wrapper mt-3">
                                                            <label for="imageUpload" class="file-upload-label">
                                                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
                                                                <p class="mb-1">Click to upload new department image</p>
                                                                <small class="text-muted">PNG, JPG, GIF, WEBP up to 5MB</small>
                                                            </label>
                                                            <input type="file" class="form-control d-none" name="imageUpload" 
                                                                id="imageUpload" accept="image/*" onchange="previewImage(this)" />
                                                        </div>
                                                        
                                                        <div class="image-preview-container" id="imagePreviewContainer">
                                                            <p class="text-muted mb-2">New Image Preview:</p>
                                                            <img id="imagePreview" class="image-preview" alt="Preview">
                                                            <div class="mt-2">
                                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="removeImage()">
                                                                    <i class="fas fa-trash me-1"></i> Remove New Image
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
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
    </section>

    <script>
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
            const fileInput = document.getElementById('imageUpload');
            const previewContainer = document.getElementById('imagePreviewContainer');
            
            fileInput.value = '';
            previewContainer.style.display = 'none';
        }
        
        // Form validation
        document.getElementById('departmentForm').addEventListener('submit', function(e) {
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
        
        // Auto-generate slug (optional - display only)
        document.getElementById('cate_name').addEventListener('blur', function() {
            const departmentName = this.value.trim();
            if (departmentName) {
                const slug = departmentName
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                
                // Display slug if you have a field for it
                console.log('Generated slug:', slug);
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                setTimeout(function() {
                    bsAlert.close();
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>