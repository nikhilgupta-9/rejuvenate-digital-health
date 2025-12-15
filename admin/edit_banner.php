<?php
session_start();
include "db-conn.php";

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$banner = null;

// Get banner ID from URL
$banner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing banner data
if ($banner_id > 0) {
    $stmt = $conn->prepare("SELECT id, banner_path, title, description, link_url, status, display_order, start_date, end_date FROM banners WHERE id = ?");
    $stmt->bind_param("i", $banner_id);
    $stmt->execute();
    $stmt->bind_result($id, $banner_path, $title, $description, $link_url, $status, $display_order, $start_date, $end_date);
    
    if ($stmt->fetch()) {
        $banner = [
            'id' => $id,
            'banner_path' => $banner_path,
            'title' => $title,
            'description' => $description,
            'link_url' => $link_url,
            'status' => $status,
            'display_order' => $display_order,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
    } else {
        $error = "Banner not found.";
    }
    
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $banner_id = intval($_POST['banner_id']);
    
    // Handle status update separately
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['update_status'];
        $stmt = $conn->prepare("UPDATE banners SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $banner_id);
        
        if ($stmt->execute()) {
            $success = "Banner status updated successfully.";
            $banner['status'] = $new_status; // Update local status
        } else {
            $error = "Error updating banner status.";
        }
        $stmt->close();
    }
    // Handle full banner update
    elseif (isset($_POST['update_banner'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $link_url = trim($_POST['link_url']);
        $display_order = intval($_POST['display_order']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        // Validate  fields
        if (empty($title)) {
            $error = "Title is .";
        } elseif (empty($link_url)) {
            $error = "Link URL is .";
        } elseif (!filter_var($link_url, FILTER_VALIDATE_URL)) {
            $error = "Please enter a valid URL.";
        } else {
            // Check if a new file was uploaded
            $new_banner_path = $banner['banner_path']; // Keep existing path by default
            
            if (!empty($_FILES['new_banner']['name'])) {
                $target_dir = "uploads/banners/";
                
                // Create directory if it doesn't exist
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_ext = strtolower(pathinfo($_FILES["new_banner"]["name"], PATHINFO_EXTENSION));
                $target_file = $target_dir . uniqid('banner_') . '.' . $file_ext;
                
                // Validate the file
                $check = getimagesize($_FILES["new_banner"]["tmp_name"]);
                if ($check === false) {
                    $error = "File is not an image.";
                } elseif ($_FILES["new_banner"]["size"] > 5000000) {
                    $error = "Sorry, your file is too large (max 5MB).";
                } elseif (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $error = "Only JPG, JPEG, PNG, GIF & WEBP files are allowed.";
                } else {
                    // Upload the new file
                    if (move_uploaded_file($_FILES["new_banner"]["tmp_name"], $target_file)) {
                        // Delete the old file
                        if (!empty($banner['banner_path']) && file_exists($banner['banner_path'])) {
                            unlink($banner['banner_path']);
                        }
                        $new_banner_path = $target_file;
                    } else {
                        $error = "Sorry, there was an error uploading your file.";
                    }
                }
            }
            
            if (empty($error)) {
                // Update database
                $stmt = $conn->prepare("UPDATE banners SET 
                    banner_path = ?, 
                    title = ?, 
                    description = ?, 
                    link_url = ?, 
                    display_order = ?, 
                    start_date = ?, 
                    end_date = ? 
                    WHERE id = ?");
                
                $stmt->bind_param("ssssissi", 
                    $new_banner_path,
                    $title,
                    $description,
                    $link_url,
                    $display_order,
                    $start_date,
                    $end_date,
                    $banner_id
                );
                
                if ($stmt->execute()) {
                    $success = "Banner updated successfully.";
                    // Update local banner data
                    $banner['banner_path'] = $new_banner_path;
                    $banner['title'] = $title;
                    $banner['description'] = $description;
                    $banner['link_url'] = $link_url;
                    $banner['display_order'] = $display_order;
                    $banner['start_date'] = $start_date;
                    $banner['end_date'] = $end_date;
                } else {
                    $error = "Error updating banner: " . $stmt->error;
                    // Delete the new file if DB update failed
                    if ($new_banner_path != $banner['banner_path'] && file_exists($new_banner_path)) {
                        unlink($new_banner_path);
                    }
                }
                $stmt->close();
            }
        }
    }
}

// If no banner found, redirect back
if (!$banner && $banner_id > 0) {
    header("Location: banners.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Banner | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    
    <style>
        .edit-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .banner-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .file-upload-input {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }
        
        .date-input-group {
            display: flex;
            gap: 15px;
        }
        
        .date-input-group .form-group {
            flex: 1;
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
                                        <h2 class="m-0">Edit Banner</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Back button -->
                                <a href="add-banner.php" class="btn btn-secondary mb-3">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Banners
                                </a>
                                
                                <!-- Display messages -->
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($error) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($success) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($banner): ?>
                                <div class="edit-card">
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $banner_id); ?>" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-4 text-center">
                                                    <h4>Current Banner</h4>
                                                    <img src="<?= htmlspecialchars($banner['banner_path']) ?>" 
                                                         alt="Current Banner" 
                                                         class="banner-preview"
                                                         id="currentBannerPreview">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Upload New Banner (Optional)</label>
                                                    <div class="file-upload btn btn-primary w-100">
                                                        <span><i class="fas fa-cloud-upload-alt me-2"></i>Choose New Banner Image</span>
                                                        <input type="file" name="new_banner" class="file-upload-input" 
                                                               accept="image/*" onchange="previewNewBanner(this)">
                                                    </div>
                                                    <div class="small text-muted">Allowed formats: JPG, JPEG, PNG, GIF, WEBP | Max size: 5MB</div>
                                                </div>
                                                
                                                <div class="mb-4 text-center" id="newBannerPreviewContainer" style="display:none;">
                                                    <h4>New Banner Preview</h4>
                                                    <img id="newBannerPreview" class="banner-preview">
                                                </div>
                                                
                                                <!-- Status Update Button -->
                                                <div class="mb-3">
                                                    <label class="form-label">Banner Status</label>
                                                    <div class="d-flex gap-2">
                                                        <button type="submit" name="update_status" value="active" 
                                                                class="btn btn-lg <?= $banner['status'] == 'active' ? 'btn-success' : 'btn-outline-success' ?> flex-grow-1">
                                                            <i class="fas fa-check-circle me-2"></i> Active
                                                        </button>
                                                        <button type="submit" name="update_status" value="inactive" 
                                                                class="btn btn-lg <?= $banner['status'] == 'inactive' ? 'btn-danger' : 'btn-outline-danger' ?> flex-grow-1">
                                                            <i class="fas fa-times-circle me-2"></i> Inactive
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="title" class="form-label">Title *</label>
                                                    <input type="text" class="form-control" id="title" name="title" 
                                                           value="<?=$banner['title'] ?>" >
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="description" class="form-label">Description</label>
                                                    <textarea class="form-control" id="description" name="description" 
                                                              rows="3"><?= $banner['description'] ?></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="link_url" class="form-label">Link URL *</label>
                                                    <input type="url" class="form-control" id="link_url" name="link_url" 
                                                           value="<?= $banner['link_url'] ?>" >
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="display_order" class="form-label">Display Order</label>
                                                    <input type="number" class="form-control" id="display_order" name="display_order" 
                                                           value="<?= $banner['display_order'] ?>">
                                                    <small class="text-muted">Lower numbers appear first</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Active Dates</label>
                                                    <div class="date-input-group">
                                                        <div class="form-group">
                                                            <label for="start_date">Start Date</label>
                                                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                                                   value="<?= $banner['start_date'] ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="end_date">End Date</label>
                                                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                                                   value="<?= $banner['end_date'] ?>">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">Leave empty for no expiration</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2 mt-4">
                                            <button type="submit" name="update_banner" class="btn btn-success btn-lg">
                                                <i class="fas fa-save me-2"></i> Update Banner
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <script>
        // Preview new banner before upload
        function previewNewBanner(input) {
            const previewContainer = document.getElementById('newBannerPreviewContainer');
            const newPreview = document.getElementById('newBannerPreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    newPreview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.style.display = 'none';
            }
        }
        
        // Confirm before leaving page if changes were made
        window.addEventListener('beforeunload', function(e) {
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput.files.length > 0) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>