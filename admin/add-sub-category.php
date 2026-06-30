<?php
include "db-conn.php";

// Start session at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM `categories` WHERE status = 1 ORDER BY categories ASC";
$check = mysqli_query($conn, $sql);

function SlugUrl($string) {
    $slug = preg_replace('/[^a-zA-Z0-9 -]/', '', $string);
    $slug = str_replace(' ', '-', $slug);
    $slug = strtolower($slug);
    return $slug;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add Sub Department | Admin Panel</title>
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
            display: none;
            margin-top: 1rem;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
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

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Add New Sub Department</h2>
                                        <p class="text-muted mb-0">Add specialized medical sub-departments under main departments</p>
                                    </div>
                                    <div class="add_button ms-2">
                                        <a href="view-sub-categories.php" class="btn_1 btn-outline-primary">
                                            <i class="fas fa-list me-1"></i> View Sub Departments
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Success/Error Messages -->
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show mx-3 mt-2" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($_SESSION['success']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show mx-3 mt-2" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?= htmlspecialchars($_SESSION['error']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <div class="white_card_body">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title section-divider">Sub Department Information</h4>
                                        <form id="subCategoryForm" action="functions.php" method="post" enctype="multipart/form-data">
                                            <div class="row mb-3">
                                                <!-- Parent Department -->
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label d-block">Parent Department <span class="text-danger">*</span></label>
                                                        <select class="form-control nice-select" name="parent_id" required>
                                                            <option value="" selected disabled>Select Main Department</option>
                                                            <?php while ($row = mysqli_fetch_assoc($check)): ?>
                                                                <option value="<?= $row['cate_id'] ?>">
                                                                    <?= htmlspecialchars($row['categories']) ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                        <small class="form-text text-muted d-block">Choose the main medical department</small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Sub Department Name -->
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Sub Department Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" name="cate_name" 
                                                            placeholder="e.g., Pediatric Cardiology, Neuro Surgery" required>
                                                        <small class="form-text text-muted">Name of the specialized sub-department</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <!-- Meta Title -->
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Meta Title</label>
                                                        <input type="text" class="form-control" name="meta_title" 
                                                            placeholder="SEO friendly title for the sub-department">
                                                        <small class="form-text text-muted">Recommended: 50-60 characters</small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Meta Keywords -->
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Meta Keywords</label>
                                                        <input type="text" class="form-control" name="meta_key" 
                                                            placeholder="medical, healthcare, specialist, treatment">
                                                        <small class="form-text text-muted">Comma separated keywords for SEO</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <!-- Meta Description -->
                                                <div class="col-md-12">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Meta Description</label>
                                                        <textarea class="form-control" name="meta_desc" rows="3" 
                                                            placeholder="Brief description of the sub-department for search engines"></textarea>
                                                        <small class="form-text text-muted">Recommended: 150-160 characters</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <!-- Image Upload -->
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Sub Department Image</label>
                                                        <div class="file-upload-wrapper">
                                                            <label for="imageUpload" class="file-upload-label">
                                                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
                                                                <p class="mb-1">Click to upload sub-department image</p>
                                                                <small class="text-muted">PNG, JPG, GIF up to 5MB</small>
                                                            </label>
                                                            <input type="file" class="form-control d-none" name="imageUpload" 
                                                                id="imageUpload" accept="image/*" onchange="previewImage(this)" />
                                                        </div>
                                                        <div class="image-preview-container" id="imagePreviewContainer">
                                                            <img id="imagePreview" class="image-preview" />
                                                            <button type="button" class="btn btn-sm btn-danger mt-2" 
                                                                onclick="removeImage()">
                                                                <i class="fas fa-trash me-1"></i> Remove Image
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status -->
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-control nice-select" name="status">
                                                            <option value="1" selected>Active</option>
                                                            <option value="0">Inactive</option>
                                                        </select>
                                                        <small class="form-text text-muted">Set sub-department availability</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Submit Button -->
                                            <div class="d-flex justify-content-end mt-4">
                                                <a href="view-sub-categories.php" class="btn btn-secondary me-3">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn_1" name="add-sub-categories">
                                                    <i class="fas fa-plus me-1"></i> Add Sub Department
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
            
            // Form validation
            document.getElementById('subCategoryForm').addEventListener('submit', function(e) {
                const parentDepartment = document.querySelector('[name="parent_id"]');
                const departmentName = document.querySelector('[name="cate_name"]');
                
                if (!parentDepartment.value) {
                    e.preventDefault();
                    alert('Please select a parent department');
                    parentDepartment.focus();
                    return false;
                }
                
                if (!departmentName.value.trim()) {
                    e.preventDefault();
                    alert('Please enter a sub-department name');
                    departmentName.focus();
                    return false;
                }
                
                // Validate file size if image is selected
                const fileInput = document.getElementById('imageUpload');
                if (fileInput.files.length > 0) {
                    const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                    if (fileSize > 5) {
                        e.preventDefault();
                        alert('Image size must be less than 5MB');
                        return false;
                    }
                }
                
                return true;
            });

            // Enhanced file upload with drag and drop
            const uploadLabel = document.querySelector('.file-upload-label');
            const fileInput = document.getElementById('imageUpload');

            // Click event for file upload label
            uploadLabel.addEventListener('click', function() {
                fileInput.click();
            });

            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadLabel.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadLabel.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadLabel.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                uploadLabel.style.borderColor = '#4361ee';
                uploadLabel.style.backgroundColor = 'rgba(67, 97, 238, 0.1)';
            }
            
            function unhighlight() {
                uploadLabel.style.borderColor = '#dee2e6';
                uploadLabel.style.backgroundColor = '#f8f9fa';
            }
            
            uploadLabel.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    fileInput.files = files;
                    previewImage(fileInput);
                }
            }
        </script>
    </section>
</body>
</html>