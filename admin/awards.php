<?php
// Database connection
include "db-conn.php";

// Handle form submissions
if(isset($_POST['add_award'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $award_date = mysqli_real_escape_string($conn, $_POST['award_date']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    // Handle image upload
    $target_dir = "uploads/awards/";
    if(!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $image_name = time() . '_' . basename($_FILES["award_image"]["name"]);
    $target_file = $target_dir . $image_name;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["award_image"]["tmp_name"]);
    if($check !== false) {
        $uploadOk = 1;
    } else {
        $upload_error = "File is not an image.";
        $uploadOk = 0;
    }
    
    // Check file size (5MB max)
    if ($_FILES["award_image"]["size"] > 5000000) {
        $upload_error = "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        $upload_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["award_image"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO awards (title, description, image_path, award_date, category) 
                    VALUES ('$title', '$description', '$target_file', '$award_date', '$category')";
            
            if(mysqli_query($conn, $sql)) {
                $upload_success = "Award added successfully!";
            } else {
                $upload_error = "Error adding award: " . mysqli_error($conn);
            }
        } else {
            $upload_error = "Sorry, there was an error uploading your file.";
        }
    }
}

// Handle award deletion
if(isset($_POST['delete_award'])) {
    $award_id = mysqli_real_escape_string($conn, $_POST['award_id']);
    
    // Get image path to delete file
    $sql = "SELECT image_path FROM awards WHERE id = '$award_id'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    if($row && file_exists($row['image_path'])) {
        unlink($row['image_path']);
    }
    
    $delete_sql = "DELETE FROM awards WHERE id = '$award_id'";
    if(mysqli_query($conn, $delete_sql)) {
        $delete_success = "Award deleted successfully!";
    } else {
        $delete_error = "Error deleting award: " . mysqli_error($conn);
    }
}

// Fetch all awards
$awards_sql = "SELECT * FROM awards ORDER BY award_date DESC, created_at DESC";
$awards_result = mysqli_query($conn, $awards_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Awards Management | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
    
    <style>
        .awards-container {
            padding: 20px;
        }
        .award-form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .awards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .award-card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            background: white;
        }
        .award-card:hover {
            transform: translateY(-5px);
        }
        .award-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            cursor: pointer;
        }
        .award-content {
            padding: 15px;
        }
        .award-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .award-description {
            color: #666;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .award-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
            margin-bottom: 15px;
        }
        .award-actions {
            display: flex;
            gap: 10px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .current-image {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 4px;
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
                                        <h2 class="m-0">Awards Management</h2>
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

                                <div class="awards-container">
                                    <!-- Add Award Form -->
                                    <div class="award-form-section">
                                        <h4>Add New Award</h4>
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="title" class="form-label">Award Title *</label>
                                                        <input type="text" class="form-control" id="title" name="title" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="category" class="form-label">Category</label>
                                                        <input type="text" class="form-control" id="category" name="category" 
                                                               placeholder="e.g., Excellence, Innovation, Quality">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="award_date" class="form-label">Award Date</label>
                                                        <input type="date" class="form-control" id="award_date" name="award_date">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="award_image" class="form-label">Award Image *</label>
                                                        <input class="form-control" type="file" name="award_image" id="award_image" 
                                                               accept="image/*" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button type="submit" name="add_award" class="btn btn-primary">
                                                    <i class="fas fa-plus me-2"></i>Add Award
                                                </button>
                                                
                                                <a href="show-products.php" class="btn btn-outline-secondary ms-auto">
                                                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Awards Display -->
                                    <h4 class="mt-5 mb-4">Our Awards</h4>
                                    
                                    <?php if($awards_result && mysqli_num_rows($awards_result) > 0): ?>
                                        <div class="awards-grid">
                                            <?php while($award = mysqli_fetch_assoc($awards_result)): ?>
                                                <div class="award-card">
                                                    <img src="<?= htmlspecialchars($award['image_path']) ?>" 
                                                         alt="<?= htmlspecialchars($award['title']) ?>" 
                                                         class="award-img"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imagePreviewModal"
                                                         data-img-src="<?= htmlspecialchars($award['image_path']) ?>"
                                                         data-img-name="<?= htmlspecialchars($award['title']) ?>">
                                                    
                                                    <div class="award-content">
                                                        <div class="award-title"><?= htmlspecialchars($award['title']) ?></div>
                                                        <div class="award-description">
                                                            <?= htmlspecialchars(substr($award['description'], 0, 100)) ?>
                                                            <?= strlen($award['description']) > 100 ? '...' : '' ?>
                                                        </div>
                                                        <div class="award-meta">
                                                            <span><i class="fas fa-calendar me-1"></i> 
                                                                <?= $award['award_date'] ? date('M Y', strtotime($award['award_date'])) : 'Not set' ?>
                                                            </span>
                                                            <?php if($award['category']): ?>
                                                                <span><i class="fas fa-tag me-1"></i> <?= htmlspecialchars($award['category']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="award-actions">
                                                            <a href="edit_award.php?id=<?= $award['id'] ?>" class="btn btn-warning btn-sm">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <form action="" method="post" onsubmit="return confirm('Are you sure you want to delete this award?');">
                                                                <input type="hidden" name="award_id" value="<?= $award['id'] ?>">
                                                                <button type="submit" name="delete_award" class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-trophy fa-2x mb-3"></i><br>
                                            No awards found. Add your first award to get started!
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
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewImageTitle">Award Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="img-fluid" id="previewImage" alt="Preview">
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
                    const modalImage = previewModal.querySelector('#previewImage');
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