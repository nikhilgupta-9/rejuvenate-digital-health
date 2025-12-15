<?php
include "db-conn.php";

// Check admin authentication
session_start();
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
    <title>Manage Testimonials | Admin Panel</title>
    <link rel="icon" href="img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --success-color: #4bb543;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .section-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(76, 201, 240, 0.25);
        }
        
        .btn-submit {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
        }
        
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .file-upload-label {
            display: block;
            padding: 0.75rem 1rem;
            border: 1px dashed #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .file-upload-label i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
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
        
        .rating-stars {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .rating-stars i {
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .rating-stars i.active {
            color: #ffc107;
        }
        
        .featured-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .featured-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .featured-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .featured-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .featured-slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .featured-slider:before {
            transform: translateX(26px);
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
                    <div class="col-lg-10">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0"><?php echo isset($_GET['edit']) ? 'Edit' : 'Add New'; ?> Testimonial</h2>
                                    </div>
                                    <div class="add_button ms-2">
                                        <a href="view-testimonials.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-list me-1"></i> View Testimonials
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="testimonial-card">
                                    <h3 class="section-title">Testimonial Details</h3>
                                    <form id="testimonialForm" action="functions.php" method="post" enctype="multipart/form-data">
                                        <?php if (isset($_GET['edit'])): 
                                            $testimonial_id = $_GET['edit'];
                                            $stmt = $conn->prepare("SELECT * FROM testimonials WHERE id = ?");
                                            $stmt->bind_param("i", $testimonial_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $testimonial = $result->fetch_assoc();
                                        ?>
                                            <input type="hidden" name="testimonial_id" value="<?php echo $testimonial_id; ?>">
                                        <?php endif; ?>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="client_name">Client Name*</label>
                                                <input type="text" class="form-control" name="client_name" id="client_name"
                                                    placeholder="Enter client name" 
                                                    value="<?php echo isset($testimonial) ? htmlspecialchars($testimonial['client_name']) : ''; ?>" 
                                                    required />
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="client_title">Client Title*</label>
                                                <input type="text" class="form-control" name="client_title" id="client_title"
                                                    placeholder="Enter client title/position" 
                                                    value="<?php echo isset($testimonial) ? htmlspecialchars($testimonial['client_title']) : ''; ?>" 
                                                    required />
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="client_company">Client Company</label>
                                                <input type="text" class="form-control" name="client_company" id="client_company"
                                                    placeholder="Enter client company" 
                                                    value="<?php echo isset($testimonial) ? htmlspecialchars($testimonial['client_company']) : ''; ?>" />
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="project_name">Project Name</label>
                                                <input type="text" class="form-control" name="project_name" id="project_name"
                                                    placeholder="Enter project name" 
                                                    value="<?php echo isset($testimonial) ? htmlspecialchars($testimonial['project_name']) : ''; ?>" />
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="project_date">Project Date</label>
                                                <input type="date" class="form-control" name="project_date" id="project_date"
                                                    value="<?php echo isset($testimonial) ? htmlspecialchars($testimonial['project_date']) : ''; ?>" />
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Rating*</label>
                                                <input type="hidden" name="rating" id="ratingValue" 
                                                    value="<?php echo isset($testimonial) ? $testimonial['rating'] : '5'; ?>" required>
                                                <div class="rating-stars" id="ratingStars">
                                                    <i class="fas fa-star" data-value="1"></i>
                                                    <i class="fas fa-star" data-value="2"></i>
                                                    <i class="fas fa-star" data-value="3"></i>
                                                    <i class="fas fa-star" data-value="4"></i>
                                                    <i class="fas fa-star" data-value="5"></i>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label" for="testimonial_text">Testimonial Text*</label>
                                                <textarea class="form-control" name="testimonial_text" id="testimonial_text" 
                                                    rows="4" placeholder="Enter the testimonial content" required><?php echo isset($testimonial) ? htmlspecialchars($testimonial['testimonial_text']) : ''; ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Featured Testimonial</label>
                                                <div class="d-flex align-items-center">
                                                    <label class="featured-toggle me-2">
                                                        <input type="checkbox" name="featured" <?php echo (isset($testimonial) && $testimonial['featured']) ? 'checked' : ''; ?>>
                                                        <span class="featured-slider"></span>
                                                    </label>
                                                    <span>Mark as featured</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="display_order">Display Order</label>
                                                <input type="number" class="form-control" name="display_order" id="display_order"
                                                    value="<?php echo isset($testimonial) ? htmlspecialchars($testimonial['display_order']) : '0'; ?>" />
                                                <small class="text-muted">Lower numbers appear first</small>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Client Photo</label>
                                                <div class="file-upload-wrapper">
                                                    <label for="clientPhoto" class="file-upload-label">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p class="mb-0">Click to upload or drag and drop</p>
                                                        <small class="text-muted">PNG, JPG up to 5MB</small>
                                                    </label>
                                                    <input type="file" class="form-control d-none" name="client_photo" 
                                                        id="clientPhoto" accept="image/*" onchange="previewImage(this)" />
                                                </div>
                                                <div class="image-preview-container" id="imagePreviewContainer">
                                                    <?php if (isset($testimonial) && !empty($testimonial['client_photo'])): ?>
                                                        <img id="imagePreview" class="image-preview" src="../uploads/testimonials/<?php echo htmlspecialchars($testimonial['client_photo']); ?>" />
                                                    <?php else: ?>
                                                        <img id="imagePreview" class="image-preview" />
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger mt-2" 
                                                        onclick="removeImage()">
                                                        <i class="fas fa-trash me-1"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="reset" class="btn btn-outline-secondary me-3">
                                                <i class="fas fa-undo me-1"></i> Reset
                                            </button>
                                            <button type="submit" class="btn btn-submit" name="<?php echo isset($_GET['edit']) ? 'update-testimonial' : 'add-testimonial'; ?>">
                                                <i class="fas fa-save me-1"></i> <?php echo isset($_GET['edit']) ? 'Update' : 'Save'; ?> Testimonial
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

        <?php include "footer.php"; ?>

        <script>
            // Initialize rating stars
            document.addEventListener('DOMContentLoaded', function() {
                const ratingValue = document.getElementById('ratingValue').value;
                const stars = document.querySelectorAll('#ratingStars i');
                
                // Set initial rating if editing
                if (ratingValue) {
                    highlightStars(ratingValue);
                }
                
                // Add click event to stars
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const value = this.getAttribute('data-value');
                        document.getElementById('ratingValue').value = value;
                        highlightStars(value);
                    });
                });
                
                // Show existing image preview if editing
                <?php if (isset($testimonial) && !empty($testimonial['client_photo'])): ?>
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                <?php endif; ?>
            });
            
            // Highlight stars based on rating
            function highlightStars(value) {
                const stars = document.querySelectorAll('#ratingStars i');
                stars.forEach(star => {
                    if (star.getAttribute('data-value') <= value) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }
            
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
                document.getElementById('clientPhoto').value = '';
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
            
            // Form validation
            
            
            // Drag and drop functionality
            const uploadLabel = document.querySelector('.file-upload-label');
            
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
                uploadLabel.classList.add('bg-light');
            }
            
            function unhighlight() {
                uploadLabel.classList.remove('bg-light');
            }
            
            uploadLabel.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                const input = document.getElementById('clientPhoto');
                
                if (files.length) {
                    input.files = files;
                    previewImage(input);
                }
            }
        </script>
    </section>
</body>
</html>

<script>
    // Add this to your form page if you want AJAX submission
document.getElementById('testimonialForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
    
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Show success message
            alert(data.message);
            
            // Redirect or refresh if needed
            if (form.querySelector('[name="update-testimonial"]')) {
                window.location.href = 'testimonials.php?edit=' + data.testimonial_id;
            } else {
                form.reset();
                document.getElementById('imagePreview').src = '';
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('An error occurred: ' + error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    });
});
</script>