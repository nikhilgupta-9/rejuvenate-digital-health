<?php
include "functions.php";

// Get testimonial id from URL
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $testimonial_id = $_GET['edit'];
    // Fetch testimonial details from the database
    $testimonial = get_testimonial_by_id($testimonial_id);
    if (!$testimonial) {
        echo "Testimonial not found.";
        exit;
    }
} else {
    echo "Invalid testimonial id.";
    exit;
}

// Process form submission for updating the testimonial
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve updated values from POST
    $client_name = $_POST['client_name'] ?? '';
    $client_title = $_POST['client_title'] ?? '';
    $client_company = $_POST['client_company'] ?? '';
    $testimonial_text = $_POST['testimonial_text'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    $project_name = $_POST['project_name'] ?? '';
    $project_date = $_POST['project_date'] ?? '';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $display_order = $_POST['display_order'] ?? 0;
    
    // Handle file upload
    $client_photo = $testimonial['client_photo']; // Keep existing photo by default
    
    if (!empty($_FILES['client_photo']['name'])) {
        $upload_dir = "uploads/testimonials/";
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true); // Create with read/write permissions
        }
        
        $file_name = basename($_FILES['client_photo']['name']);
        $target_path = $upload_dir . $file_name;
        
        // Check if file is an image
        $imageFileType = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['client_photo']['tmp_name'], $target_path)) {
                $client_photo = $file_name;
                // Delete old photo if it exists and is different
                if (!empty($testimonial['client_photo']) && $testimonial['client_photo'] != $file_name) {
                    @unlink($upload_dir . $testimonial['client_photo']);
                }
            } else {
                $_SESSION['error'] = "Error uploading file.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    // Update testimonial
    if (update_testimonial($testimonial_id, $client_name, $client_title, $client_company, 
                         $client_photo, $testimonial_text, $rating, $project_name, 
                         $project_date, $featured, $display_order)) {
        $_SESSION['success'] = "Testimonial updated successfully!";
        header("Location: view-testimonials.php"); // redirect back after update
        exit;
    } else {
        $_SESSION['error'] = "Error updating testimonial.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Testimonial</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .star-rating {
            font-size: 24px;
            color: #ffc107;
        }
        .star-rating i {
            cursor: pointer;
        }
        .preview-image {
            max-width: 150px;
            max-height: 150px;
            margin-bottom: 10px;
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
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Edit Testimonial</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="QA_section">
                                    <?php if (isset($_SESSION['error'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_SESSION['success'])): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="white_box_tittle list_header">
                                        <div class="box_right d-flex lms_block">
                                            <div class="add_button ms-2">
                                                <a href="view-testimonials.php" class="btn_1">Back to Testimonials</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="QA_table mb_30">
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <input type="hidden" name="testimonial_id" value="<?= htmlspecialchars($testimonial['id']) ?>">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Client Name *</label>
                                                    <input type="text" name="client_name" class="form-control" 
                                                           value="<?= htmlspecialchars($testimonial['client_name']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Client Title</label>
                                                    <input type="text" name="client_title" class="form-control" 
                                                           value="<?= htmlspecialchars($testimonial['client_title']) ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Client Company</label>
                                                    <input type="text" name="client_company" class="form-control" 
                                                           value="<?= htmlspecialchars($testimonial['client_company']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Project Name</label>
                                                    <input type="text" name="project_name" class="form-control" 
                                                           value="<?= htmlspecialchars($testimonial['project_name']) ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Project Date</label>
                                                    <input type="date" name="project_date" class="form-control" 
                                                           value="<?= htmlspecialchars($testimonial['project_date']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Display Order</label>
                                                    <input type="number" name="display_order" class="form-control" 
                                                           value="<?= htmlspecialchars($testimonial['display_order']) ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Rating *</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= ($i > $testimonial['rating']) ? '-empty' : '' ?>" 
                                                           data-rating="<?= $i ?>"></i>
                                                    <?php endfor; ?>
                                                    <input type="hidden" name="rating" id="rating" value="<?= $testimonial['rating'] ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Testimonial Text *</label>
                                                <textarea name="testimonial_text" class="form-control" rows="5" required><?= 
                                                    htmlspecialchars($testimonial['testimonial_text']) ?></textarea>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Client Photo</label>
                                                    <?php if (!empty($testimonial['client_photo'])): ?>
                                                        <img src="uploads/testimonials/<?= htmlspecialchars($testimonial['client_photo']) ?>" 
                                                             class="preview-image d-block mb-2">
                                                    <?php endif; ?>
                                                    <input type="file" name="client_photo" class="form-control" accept="image/*">
                                                    <small class="text-muted">Leave blank to keep existing photo</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch mt-4">
                                                        <input class="form-check-input" type="checkbox" name="featured" 
                                                               id="featured" <?= $testimonial['featured'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="featured">Featured Testimonial</label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Update Testimonial</button>
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
        // Star rating functionality
        document.querySelectorAll('.star-rating i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                document.getElementById('rating').value = rating;
                
                // Update star display
                document.querySelectorAll('.star-rating i').forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('fa-star-empty');
                        s.classList.add('fa-star');
                    } else {
                        s.classList.remove('fa-star');
                        s.classList.add('fa-star-empty');
                    }
                });
            });
        });
        
        // Image preview
        document.querySelector('input[name="client_photo"]').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const preview = document.querySelector('.preview-image') || 
                    document.createElement('img');
                
                if (!document.querySelector('.preview-image')) {
                    preview.className = 'preview-image d-block mb-2';
                    this.parentNode.insertBefore(preview, this);
                }
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>