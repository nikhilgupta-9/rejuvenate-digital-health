<?php
session_start();
include "db-conn.php";
include "functions.php";

// Validate and get sub-category ID
$sub_cat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($sub_cat_id <= 0) {
    $_SESSION['error'] = 'Invalid Sub-Category ID';
    header("Location: sub-categories.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $parent_id = mysqli_real_escape_string($conn, $_POST['parent_id']);
    $cate_id = mysqli_real_escape_string($conn, $_POST['cate_id']);
    $categories = mysqli_real_escape_string($conn, $_POST['categories']);
    $meta_title = mysqli_real_escape_string($conn, $_POST['meta_title']);
    $meta_desc = mysqli_real_escape_string($conn, $_POST['meta_desc']);
    $meta_key = mysqli_real_escape_string($conn, $_POST['meta_key']);
    $slug_url = mysqli_real_escape_string($conn, $_POST['slug_url']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $added_on = date('Y-m-d H:i:s');

    // Handle file upload
    $imageName = null;
    $uploadDir = 'uploads/sub-category/';
    
    if (!empty($_FILES['imageUpload']['name'])) {
        $fileTmpPath = $_FILES['imageUpload']['tmp_name'];
        $fileName = $_FILES['imageUpload']['name'];
        $fileSize = $_FILES['imageUpload']['size'];
        $fileType = $_FILES['imageUpload']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imageName = $newFileName;
            } else {
                $_SESSION['error'] = 'File upload failed';
            }
        } else {
            $_SESSION['error'] = 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions);
        }
    }

    // Update query
    $updateQuery = "UPDATE sub_categories SET 
        parent_id = '$parent_id',
        cate_id = '$cate_id',
        categories = '$categories',
        meta_title = '$meta_title',
        meta_desc = '$meta_desc',
        meta_key = '$meta_key',
        slug_url = '$slug_url',
        status = '$status',
        added_on = '$added_on'";
    
    if ($imageName) {
        $updateQuery .= ", sub_cat_img = '$imageName'";
    }
    
    $updateQuery .= " WHERE cate_id = '$sub_cat_id'";
    
    if (mysqli_query($conn, $updateQuery)) {
        $_SESSION['success'] = 'Sub-category updated successfully';
        // header("Location: edit_sub_category.php?id=$sub_cat_id");
        // exit();
    } else {
        $_SESSION['error'] = 'Error updating sub-category: ' . mysqli_error($conn);
    }
}

// Fetch sub-category data
$sql = "SELECT * FROM sub_categories WHERE cate_id = '$sub_cat_id'";
$result = mysqli_query($conn, $sql);
$subcategory = mysqli_fetch_assoc($result);

if (!$subcategory) {
    $_SESSION['error'] = 'Sub-category not found';
    header("Location: sub-categories.php");
    exit();
}

// Fetch parent categories for dropdown
$parentCategories = [];
$parentSql = "SELECT cate_id, categories FROM categories ORDER BY categories ASC";
$parentResult = mysqli_query($conn, $parentSql);
while ($row = mysqli_fetch_assoc($parentResult)) {
    $parentCategories[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Sub-Category | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    <style>
        .form-section {
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
        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px 30px;
        }
        .main-title h2 {
            color: #2c2c2c;
            font-weight: 600;
        }
        .btn-primary {
            background-color: #7367f0;
            border-color: #7367f0;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .btn-primary:hover {
            background-color: #5d50e6;
            border-color: #5d50e6;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 4px;
            border: 1px dashed #ddd;
            padding: 5px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        .alert {
            border-radius: 6px;
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
                                <h2 class="mb-0">Edit Sub-Category</h2>
                                <!-- <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="sub-categories.php">Sub-Categories</a></li>
                                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
                                    </ol>
                                </nav> -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-12">
                        <div class="form-section">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= $_SESSION['error'] ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= $_SESSION['success'] ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>
                            
                            <form id="subCategoryForm" method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Parent Category -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Parent Category <span class="text-danger">*</span></label>
                                        <select class="form-select" name="parent_id" required>
                                            <option value="">Select Parent Category</option>
                                            <?php foreach ($parentCategories as $parent): ?>
                                                <option value="<?= $parent['cate_id'] ?>" <?= ($parent['cate_id'] == $subcategory['parent_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($parent['categories']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Category ID -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Category ID <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="cate_id" 
                                            value="<?= htmlspecialchars($subcategory['cate_id']) ?>" required>
                                    </div>
                                    
                                    <!-- Sub-Category Name -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Sub-Category Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="categories" 
                                            value="<?= htmlspecialchars($subcategory['categories']) ?>" required>
                                    </div>
                                    
                                    <!-- Slug URL -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Slug URL <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="slug_url" 
                                            value="<?= htmlspecialchars($subcategory['slug_url']) ?>" required>
                                    </div>
                                    
                                    <!-- Meta Title -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" name="meta_title" 
                                            value="<?= htmlspecialchars($subcategory['meta_title']) ?>">
                                        <small class="text-muted">Recommended: 50-60 characters</small>
                                    </div>
                                    
                                    <!-- Meta Keywords -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Meta Keywords</label>
                                        <input type="text" class="form-control" name="meta_key" 
                                            value="<?= htmlspecialchars($subcategory['meta_key']) ?>">
                                        <small class="text-muted">Comma separated keywords</small>
                                    </div>
                                    
                                    <!-- Meta Description -->
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Meta Description</label>
                                        <textarea class="form-control" name="meta_desc" rows="3"><?= htmlspecialchars($subcategory['meta_desc']) ?></textarea>
                                        <small class="text-muted">Recommended: 150-160 characters</small>
                                    </div>
                                    
                                    <!-- Image Upload -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Sub-Category Image</label>
                                        <?php if (!empty($subcategory['sub_cat_img'])): ?>
                                            <div class="mb-3">
                                                <img src="<?=$site?>admin/uploads/sub-category/<?= htmlspecialchars($subcategory['sub_cat_img']) ?>" 
                                                    alt="Current Image" class="image-preview">
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage">
                                                    <label class="form-check-label" for="removeImage">
                                                        Remove current image
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="imageUpload" id="imageUpload" accept="image/*">
                                        <small class="text-muted">Allowed formats: JPG, PNG, GIF, WEBP (Max 2MB)</small>
                                    </div>
                                    
                                    <!-- Status -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" name="status" required>
                                            <option value="1" <?= ($subcategory['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= ($subcategory['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Submit Button -->
                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Update Sub-Category
                                        </button>
                                        <a href="sub-categories.php" class="btn btn-outline-secondary ms-2">
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

        <script>
            // Image preview functionality
            document.getElementById('imageUpload').addEventListener('change', function(e) {
                const preview = document.querySelector('.image-preview');
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (!preview) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        document.getElementById('imageUpload').after(img);
                    } else {
                        preview.src = e.target.result;
                    }
                }
                
                if (file) {
                    reader.readAsDataURL(file);
                }
            });

            // Form validation
            document.getElementById('subCategoryForm').addEventListener('submit', function(e) {
                const requiredFields = document.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        </script>
    </body>
</html>