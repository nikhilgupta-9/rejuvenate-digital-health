<?php
session_start();
include "db-conn.php";

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add Category | Admin Panel</title>
    <link rel="icon" href="img/logo.png" type="image/png">

    <!-- Using your existing CSS libraries -->
    <link rel="stylesheet" href="assets/css/bootstrap1.min.css" />
    <link rel="stylesheet" href="assets/vendors/themefy_icon/themify-icons.css" />
    <link rel="stylesheet" href="assets/vendors/niceselect/css/nice-select.css" />
    <link rel="stylesheet" href="assets/vendors/owl_carousel/css/owl.carousel.css" />
    <link rel="stylesheet" href="assets/vendors/gijgo/gijgo.min.css" />
    <link rel="stylesheet" href="assets/vendors/font_awesome/css/all.min.css" />
    <link rel="stylesheet" href="assets/vendors/tagsinput/tagsinput.css" />
    <link rel="stylesheet" href="assets/vendors/datepicker/date-picker.css" />
    <link rel="stylesheet" href="assets/vendors/vectormap-home/vectormap-2.0.2.css" />
    <link rel="stylesheet" href="assets/vendors/scroll/scrollable.css" />
    <link rel="stylesheet" href="assets/vendors/datatable/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="assets/vendors/datatable/css/responsive.dataTables.min.css" />
    <link rel="stylesheet" href="assets/vendors/datatable/css/buttons.dataTables.min.css" />
    <link rel="stylesheet" href="assets/vendors/text_editor/summernote-bs4.css" />
    <link rel="stylesheet" href="assets/vendors/morris/morris.css">
    <link rel="stylesheet" href="assets/vendors/material_icon/material-icons.css" />
    <link rel="stylesheet" href="assets/css/metisMenu.css">
    <link rel="stylesheet" href="assets/css/style1.css" />
    <link rel="stylesheet" href="assets/css/colors/default.css" id="colorSkinCSS">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    
    <style>
        /* Minimal custom styles using your existing theme */
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
                                        <h2 class="m-0">Add New Category</h2>
                                        <p class="mb-0 text-muted">Add medical Categorys for doctor categorization</p>
                                    </div>
                                    <div class="add_button ms-2">
                                        <a href="view-categories.php" class="btn_1">
                                            <i class="fas fa-list me-1"></i> View Categorys
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Messages -->
                            <?php if (isset($_SESSION['errors'])): ?>
                                <div class="alert alert-danger mx-4">
                                    <ul class="mb-0">
                                        <?php foreach ($_SESSION['errors'] as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php unset($_SESSION['errors']); ?>
                            <?php endif; ?>

                            <!-- Success Message -->
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success mx-4">
                                    <?= htmlspecialchars($_SESSION['success']) ?>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <!-- Form Data Repopulation -->
                            <?php if (isset($_SESSION['form_data'])): ?>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const formData = <?= json_encode($_SESSION['form_data']) ?>;
                                    for (const key in formData) {
                                        const element = document.querySelector(`[name="${key}"]`);
                                        if (element) {
                                            element.value = formData[key];
                                        }
                                    }
                                });
                                </script>
                                <?php unset($_SESSION['form_data']); ?>
                            <?php endif; ?>

                            <div class="white_card_body">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title section-divider">Category Information</h4>
                                        <form id="categoryForm" action="functions.php" method="post" enctype="multipart/form-data">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="cate_name" class="form-label">Category Name *</label>
                                                        <input type="text" class="form-control" name="cate_name" id="cate_name"
                                                            placeholder="e.g.,Category" required />
                                                        <small class="form-text text-muted">Enter the medical Category name</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="meta_title" class="form-label">Meta Title *</label>
                                                        <input type="text" class="form-control" name="meta_title" id="meta_title"
                                                            placeholder="SEO friendly title" required />
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="meta_key" class="form-label">Meta Keywords *</label>
                                                        <input type="text" class="form-control" name="meta_key" id="meta_key" 
                                                            placeholder="medical, healthcare, doctor, treatment" required />
                                                        <small class="form-text text-muted">Comma separated keywords for SEO</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="meta_desc" class="form-label">Meta Description *</label>
                                                        <textarea class="form-control" name="meta_desc" id="meta_desc" 
                                                            rows="2" placeholder="Brief description for search engines" required></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label for="status" class="form-label d-block">Status *</label>
                                                        <select id="status" name="status" class="form-control nice-select" required>
                                                            <option value="1" selected>Active</option>
                                                            <option value="0">Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="common_input mb_15">
                                                        <label class="form-label">Category Image</label>
                                                        <div class="file-upload-wrapper">
                                                            <label for="imageUpload" class="file-upload-label">
                                                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
                                                                <p class="mb-1">Click to upload Category image</p>
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
                                            </div>
                                            
                                            <div class="d-flex justify-content-end mt-4">
                                                <button type="reset" class="btn btn-secondary me-3">
                                                    <i class="fas fa-undo me-1"></i> Reset
                                                </button>
                                                <button type="submit" class="btn_1" name="add-categories">
                                                    <i class="fas fa-save me-1"></i> Save Category
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
        <!-- <script src="assets/js/jquery-3.7.1.min.js"></script>
        <script src="assets/js/popper.min.js"></script>
        <script src="assets/js/bootstrap1.min.js"></script>
        <script src="assets/vendors/niceselect/js/jquery.nice-select.min.js"></script>
        <script src="assets/vendors/owl_carousel/js/owl.carousel.min.js"></script>
        <script src="assets/vendors/gijgo/gijgo.min.js"></script>
        <script src="assets/vendors/datatable/js/jquery.dataTables.min.js"></script>
        <script src="assets/vendors/datatable/js/dataTables.responsive.min.js"></script>
        <script src="assets/vendors/datatable/js/dataTables.buttons.min.js"></script>
        <script src="assets/vendors/text_editor/summernote-bs4.js"></script>
        <script src="assets/js/metisMenu.js"></script>
        <script src="assets/js/custom.js"></script> -->

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
            document.getElementById('categoryForm').addEventListener('submit', function(e) {
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