<?php
session_start();
include "db-conn.php";

// Handle image upload
if (isset($_POST['gallery-img'])) {
    // Validate and process upload
    if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/gallery/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $unique_name;
        
        // Validate image
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if(in_array($file_extension, $allowed_types)) {
            if($_FILES['image']['size'] <= $max_size) {
                if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    // Insert into database with prepared statement
                    $stmt = $conn->prepare("INSERT INTO gallery (image_name, image_path) VALUES (?, ?)");
                    $stmt->bind_param("ss", $_FILES['image']['name'], $target_file);
                    
                    if($stmt->execute()) {
                        $upload_success = "Image uploaded successfully!";
                    } else {
                        $upload_error = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $upload_error = "Error moving uploaded file.";
                }
            } else {
                $upload_error = "File too large. Maximum size is 5MB.";
            }
        } else {
            $upload_error = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
        }
    } else {
        $upload_error = "Error uploading file. Please try again.";
    }
}

// Handle image deletion
if(isset($_POST['delete_btn'])) {
    $image_id = intval($_POST['image_id']);
    
    // First get the image path
    $stmt = $conn->prepare("SELECT image_path FROM gallery WHERE id = ?");
    $stmt->bind_param("i", $image_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $image_path = $row['image_path'];
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->bind_param("i", $image_id);
        
        if($stmt->execute()) {
            // Delete the actual file
            if(file_exists($image_path)) {
                unlink($image_path);
            }
            $delete_success = "Image deleted successfully!";
        } else {
            $delete_error = "Error deleting image from database.";
        }
    } else {
        $delete_error = "Image not found in database.";
    }
    $stmt->close();
}

// Fetch all gallery images
$query = "SELECT * FROM gallery ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Gallery Management | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    
    <style>
        .gallery-container {
            padding: 20px;
        }
        .upload-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .gallery-item:hover {
            transform: translateY(-5px);
        }
        .gallery-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            cursor: pointer;
        }
        .gallery-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            padding: 10px;
            display: flex;
            justify-content: space-between;
        }
        .img-preview {
            max-width: 100%;
            max-height: 80vh;
        }
        .preview-modal .modal-dialog {
            max-width: 90%;
        }
        .alert {
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
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Gallery Management</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Success/Error Messages -->
                                <?php if(isset($upload_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $upload_success ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($upload_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $upload_error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($delete_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= $delete_success ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($delete_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= $delete_error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="gallery-container">
                                    <!-- Upload Section -->
                                    <div class="upload-section">
                                        <h4>Upload New Image</h4>
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="image" class="form-label">Select Image (Max 5MB)</label>
                                                <input class="form-control" type="file" name="image" id="image" accept="image/*" required>
                                            </div>
                                            <button type="submit" name="gallery-img" class="btn btn-primary">
                                                <i class="fas fa-upload me-2"></i> Upload Image
                                            </button>
                                            <a href="show-products.php" class="btn btn-secondary ms-2">
                                                <i class="fas fa-arrow-left me-2"></i> Back to Products
                                            </a>
                                        </form>
                                    </div>
                                    
                                    <!-- Gallery Display -->
                                    <h4 class="mt-5 mb-4">Gallery Images</h4>
                                    
                                    <?php if($result && mysqli_num_rows($result) > 0): ?>
                                        <div class="gallery-grid">
                                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                                <div class="gallery-item">
                                                    <img src="<?= htmlspecialchars($row['image_path']) ?>" 
                                                         alt="<?= htmlspecialchars($row['image_name']) ?>" 
                                                         class="gallery-img"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imagePreviewModal"
                                                         data-img-src="<?= htmlspecialchars($row['image_path']) ?>"
                                                         data-img-name="<?= htmlspecialchars($row['image_name']) ?>">
                                                    
                                                    <div class="gallery-actions">
                                                        <span class="text-white"><?= htmlspecialchars($row['image_name']) ?></span>
                                                        <form action="" method="post" onsubmit="return confirm('Are you sure you want to delete this image?');">
                                                            <input type="hidden" name="image_id" value="<?= $row['ID'] ?>">
                                                            <button type="submit" name="delete_btn" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            No images found in the gallery. Upload some images to get started.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <!-- Image Preview Modal -->
    <div class="modal fade preview-modal" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewImageTitle">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="img-preview" id="previewImage" alt="Preview">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="downloadImageBtn" download>
                        <i class="fas fa-download me-2"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize image preview modal
        document.addEventListener('DOMContentLoaded', function() {
            const previewModal = document.getElementById('imagePreviewModal');
            
            if (previewModal) {
                previewModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const imgSrc = button.getAttribute('data-img-src');
                    const imgName = button.getAttribute('data-img-name');
                    
                    const modalTitle = previewModal.querySelector('.modal-title');
                    const modalImage = previewModal.querySelector('.img-preview');
                    const downloadBtn = previewModal.querySelector('#downloadImageBtn');
                    
                    modalTitle.textContent = imgName;
                    modalImage.src = imgSrc;
                    modalImage.alt = imgName;
                    
                    // Set download attributes
                    downloadBtn.href = imgSrc;
                    downloadBtn.setAttribute('download', imgName);
                });
            }
        });
    </script>
</body>
</html>