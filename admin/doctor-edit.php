<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db-conn.php";
include_once "functions.php";

$doctor = null;
$success_message = $error_message = '';

// Fetch doctor data for editing
if (isset($_GET['id'])) {
    $doctor_id = intval($_GET['id']);
    $sql = "SELECT * FROM doctors WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $doctor = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Doctor not found!";
        header("Location: doctors-list.php");
        exit();
    }
} else {
    header("Location: doctors-list.php");
    exit();
}

// Get current doctor departments
$current_departments = [];
$dept_sql = "SELECT category_id FROM doctor_departments WHERE doctor_id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param('i', $doctor_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
while ($dept = $dept_result->fetch_assoc()) {
    $current_departments[] = $dept['category_id'];
}
$dept_stmt->close();

// Handle gallery image removal
if (isset($_POST['remove_gallery_image'])) {
    try {
        $image_to_remove = $_POST['remove_gallery_image'];
        $gallery_array = json_decode($doctor['gallery_images'], true) ?? [];
        
        // Remove the image from array
        $updated_gallery = array_filter($gallery_array, function($image) use ($image_to_remove) {
            return $image !== $image_to_remove;
        });
        
        // Delete the physical file
        if (file_exists($image_to_remove)) {
            unlink($image_to_remove);
        }
        
        $gallery_images_json = json_encode(array_values($updated_gallery));
        
        // Update database
        $stmt = $conn->prepare("UPDATE doctors SET gallery_images = ? WHERE id = ?");
        $stmt->bind_param('si', $gallery_images_json, $doctor_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Gallery image removed successfully!";
            // Refresh the page to show updated gallery
            header("Location: doctor-edit.php?id=" . $doctor_id);
            exit();
        } else {
            throw new Exception("Failed to remove gallery image");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['remove_gallery_image'])) {
    try {
        // Get form data
        $name = trim($_POST['name']);
        $degrees = trim($_POST['degrees']);
        $specialization = trim($_POST['specialization']);
        $experience_years = intval($_POST['experience_years']);
        $rating = floatval($_POST['rating']);
        $languages = trim($_POST['languages']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $short_bio = trim($_POST['short_bio']);
        $long_bio = trim($_POST['long_bio']);
        $education = trim($_POST['education']);
        $area_of_expertise = trim($_POST['area_of_expertise']);
        $status = $_POST['status'];
        $meta_title = trim($_POST['meta_title']);
        $meta_keywords = trim($_POST['meta_keywords']);
        $meta_description = trim($_POST['meta_description']);
        $slug_url = trim($_POST['slug_url']);
        $doctor_departments = $_POST['department'] ?? [];

        // Validate required fields
        if (empty($name) || empty($specialization)) {
            throw new Exception("Name and specialization are required");
        }

        // Validate departments
        if (empty($doctor_departments)) {
            throw new Exception("Please select at least one department");
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Handle profile image upload
        $profile_image_path = $_POST['current_profile_image'] ?? '';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/doctors/profile/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_type = $_FILES['profile_image']['type'];

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPG, PNG, GIF, and WEBP images are allowed");
            }
            
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'doctor-profile-' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload profile image");
            }
            
            // Delete old profile image if exists
            if (!empty($_POST['current_profile_image']) && file_exists($_POST['current_profile_image'])) {
                unlink($_POST['current_profile_image']);
            }
            
            $profile_image_path = $target_path;
        }

        // Handle gallery images upload
        $gallery_images_paths = [];
        if (!empty($_POST['current_gallery_images'])) {
            $gallery_images_paths = json_decode($_POST['current_gallery_images'], true) ?? [];
        }

        if (!empty($_FILES['gallery_images']['name'][0])) {
            $upload_dir = 'uploads/doctors/gallery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES['gallery_images']['type'][$key];
                    if (in_array($file_type, $allowed_types)) {
                        $file_ext = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                        $file_name = 'doctor-gallery-' . time() . '-' . $key . '.' . $file_ext;
                        $target_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            $gallery_images_paths[] = $target_path;
                        }
                    }
                }
            }
        }

        $gallery_images_json = json_encode($gallery_images_paths);

        // Start transaction
        $conn->begin_transaction();

        try {
            // Update doctor
            $stmt = $conn->prepare("UPDATE doctors SET 
                name = ?, degrees = ?, specialization = ?, experience_years = ?, 
                rating = ?, languages = ?, consultation_fee = ?, short_bio = ?, long_bio = ?, 
                profile_image = ?, gallery_images = ?, education = ?, area_of_expertise = ?, 
                status = ?, meta_title = ?, meta_keywords = ?, meta_description = ?, slug_url = ? 
                WHERE id = ?");
            
            $stmt->bind_param('ssssdsssssssssssssi', 
                $name, $degrees, $specialization, $experience_years, 
                $rating, $languages, $consultation_fee, $short_bio, $long_bio, 
                $profile_image_path, $gallery_images_json, $education, $area_of_expertise, 
                $status, $meta_title, $meta_keywords, $meta_description, $slug_url, $doctor_id);

            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->close();

            // Update doctor departments
            // First, remove existing departments
            $delete_stmt = $conn->prepare("DELETE FROM doctor_departments WHERE doctor_id = ?");
            $delete_stmt->bind_param('i', $doctor_id);
            if (!$delete_stmt->execute()) {
                throw new Exception("Failed to remove existing departments: " . $conn->error);
            }
            $delete_stmt->close();

            // Insert new departments
            if (!empty($doctor_departments)) {
                $valid_departments = [];
                
                // Validate each department ID before insertion
                foreach ($doctor_departments as $dept_id) {
                    $dept_id = intval($dept_id);
                    if ($dept_id > 0) {
                        // Check if category exists
                        $check_sql = "SELECT cate_id FROM sub_categories WHERE cate_id = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("i", $dept_id);
                        $check_stmt->execute();
                        $check_stmt->store_result();
                        
                        if ($check_stmt->num_rows > 0) {
                            $valid_departments[] = $dept_id;
                        }
                        $check_stmt->close();
                    }
                }
                
                // Insert only valid departments
                if (!empty($valid_departments)) {
                    $dept_stmt = $conn->prepare("INSERT INTO doctor_departments (doctor_id, category_id, added_on) VALUES (?, ?, NOW())");
                    
                    foreach ($valid_departments as $dept_id) {
                        $dept_stmt->bind_param('ii', $doctor_id, $dept_id);
                        if (!$dept_stmt->execute()) {
                            throw new Exception("Failed to insert department ID $dept_id: " . $conn->error);
                        }
                    }
                    $dept_stmt->close();
                } else {
                    throw new Exception("No valid departments found to associate with the doctor.");
                }
            }

            // Commit transaction
            $conn->commit();

            $_SESSION['success_message'] = "Doctor updated successfully with " . count($doctor_departments) . " department(s)!";
            header("Location: doctors-list.php");
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            throw new Exception($e->getMessage());
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get current gallery images
$current_gallery = [];
if (!empty($doctor['gallery_images'])) {
    $current_gallery = json_decode($doctor['gallery_images'], true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Doctor | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .doctor-form {
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
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px dashed #ddd;
            padding: 5px;
        }
        .gallery-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .gallery-item {
            position: relative;
            display: inline-block;
        }
        .gallery-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .remove-gallery-btn {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .remove-gallery-btn:hover {
            background: #c82333;
        }
        .file-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
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
        .file-upload-label {
            display: inline-block;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
        }
        .file-upload-label:hover {
            background-color: #e9ecef;
        }
        .btn-primary {
            background-color: #7367f0;
            border-color: #7367f0;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #5d50e6;
            border-color: #5d50e6;
        }
        .section-title {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #495057;
            font-weight: 600;
        }
        .current-image-section {
            margin-top: 10px;
        }
        .gallery-actions {
            margin-top: 10px;
        }
        .empty-gallery {
            color: #6c757d;
            font-style: italic;
        }
        .multi-select-dropdown {
            position: relative;
        }
        .multi-select-dropdown .dropdown-menu {
            max-height: 250px;
            overflow-y: auto;
            width: 100%;
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
                                <h2 class="mb-0">Edit Doctor</h2>
                                <a href="doctors-list.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="doctor-form">
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= $success_message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= $error_message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" enctype="multipart/form-data" id="doctorForm">
                                <input type="hidden" name="current_profile_image" value="<?= $doctor['profile_image'] ?>">
                                <input type="hidden" name="current_gallery_images" value='<?= $doctor['gallery_images'] ?>'>
                                
                                <!-- Basic Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Basic Information</h4>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Doctor Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($doctor['name']) ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Doctor UID</label>
                                        <input type="text" class="form-control" value="<?= $doctor['doctor_uid'] ?>" readonly>
                                        <small class="text-muted">Auto-generated unique identifier</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Degrees</label>
                                        <input type="text" class="form-control" name="degrees" placeholder="MBBS, MD, DGO" value="<?= htmlspecialchars($doctor['degrees']) ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Specialization <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="specialization" required placeholder="Cardiology, Neurology, etc." value="<?= htmlspecialchars($doctor['specialization']) ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Experience (Years)</label>
                                        <input type="number" class="form-control" name="experience_years" min="0" value="<?= $doctor['experience_years'] ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Rating</label>
                                        <input type="number" class="form-control" name="rating" min="0" max="5" step="0.1" value="<?= $doctor['rating'] ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Consultation Fee (₹)</label>
                                        <input type="number" class="form-control" name="consultation_fee" min="0" step="0.01" value="<?= $doctor['consultation_fee'] ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Departments <span class="text-danger">*</span></label>

                                        <div class="multi-select-dropdown">
                                            <button class="btn btn-light form-control text-start dropdown-toggle"
                                                type="button"
                                                id="departmentDropdown"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <span id="dropdownText">Select Departments</span>
                                            </button>

                                            <ul class="dropdown-menu p-3" style="max-height: 250px; overflow-y: auto;">
                                                <?php
                                                $cat_id = 20873;
                                                $departments = get_sub_category_doctors($cat_id);

                                                if (empty($departments)) {
                                                    echo '<li><span class="text-muted">No departments found</span></li>';
                                                } else {
                                                    foreach ($departments as $depart) {
                                                        $isChecked = in_array($depart['cate_id'], $current_departments);
                                                ?>
                                                        <li>
                                                            <label class="form-check mb-2 d-block">
                                                                <input class="form-check-input dept-checkbox"
                                                                    type="checkbox"
                                                                    name="department[]"
                                                                    value="<?= $depart['cate_id'] ?>"
                                                                    <?= $isChecked ? 'checked' : '' ?>>
                                                                <?= htmlspecialchars($depart['categories']) ?>
                                                            </label>
                                                        </li>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </div>

                                        <!-- Selected departments display -->
                                        <div id="selectedDepartments" class="mt-2">
                                            <?php
                                            if (!empty($current_departments)) {
                                                $selected_names = [];
                                                foreach ($departments as $depart) {
                                                    if (in_array($depart['cate_id'], $current_departments)) {
                                                        $selected_names[] = $depart['categories'];
                                                    }
                                                }
                                                foreach ($selected_names as $name) {
                                                    echo '<span class="badge bg-primary me-1 mb-1">' . $name . '</span>';
                                                }
                                            } else {
                                                echo '<small class="text-muted">No departments selected</small>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Languages</label>
                                        <input type="text" class="form-control" name="languages" placeholder="Hindi, English, Punjabi" value="<?= $doctor['languages'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="Active" <?= $doctor['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option value="Inactive" <?= $doctor['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Profile & Gallery Images Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Images</h4>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">Profile Image</label>
                                        <div class="file-upload mb-2">
                                            <label class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Choose New Profile Image
                                                <input type="file" name="profile_image" class="file-upload-input" accept="image/*">
                                            </label>
                                        </div>
                                        <?php if (!empty($doctor['profile_image'])): ?>
                                            <div class="current-image-section">
                                                <p class="mb-1">Current Image:</p>
                                                <img src="<?= $doctor['profile_image'] ?>" alt="Current Profile" class="image-preview">
                                            </div>
                                        <?php endif; ?>
                                        <div id="profilePreview"></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">Gallery Images</label>
                                        <div class="file-upload mb-2">
                                            <label class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Add More Gallery Images
                                                <input type="file" name="gallery_images[]" class="file-upload-input" accept="image/*" multiple>
                                            </label>
                                        </div>
                                        
                                        <div class="current-image-section">
                                            <p class="mb-1">Current Gallery Images:</p>
                                            <?php if (!empty($current_gallery)): ?>
                                                <div class="gallery-preview">
                                                    <?php foreach ($current_gallery as $gallery_image): ?>
                                                        <div class="gallery-item">
                                                            <img src="<?= $gallery_image ?>" alt="Gallery Image">
                                                            <button type="submit" 
                                                                    name="remove_gallery_image" 
                                                                    value="<?= $gallery_image ?>" 
                                                                    class="remove-gallery-btn"
                                                                    onclick="return confirm('Are you sure you want to remove this image?')"
                                                                    title="Remove Image">
                                                                ×
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="gallery-actions">
                                                    <small class="text-muted">Click the × button to remove images</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-gallery">
                                                    No gallery images added yet.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div id="galleryPreview" class="gallery-preview"></div>
                                    </div>
                                </div>

                                <!-- Bio Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Bio Information</h4>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Short Bio</label>
                                        <textarea class="form-control" name="short_bio" rows="3" placeholder="Brief introduction"><?= $doctor['short_bio'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Long Bio/Description</label>
                                        <textarea class="form-control" name="long_bio" id="longBio" rows="6"><?= $doctor['long_bio'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Education</label>
                                        <textarea class="form-control" name="education" rows="3" placeholder="Educational background"><?=$doctor['education'] ??'' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Area of Expertise</label>
                                        <textarea class="form-control" name="area_of_expertise" rows="3" placeholder="Specific areas of expertise"><?= $doctor['area_of_expertise'] ?? '' ?></textarea>
                                    </div>
                                </div>

                                <!-- SEO Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">SEO Information</h4>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" name="meta_title" value="<?= $doctor['meta_title'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Slug URL</label>
                                        <input type="text" class="form-control" name="slug_url" value="<?= $doctor['slug_url'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Keywords</label>
                                        <textarea class="form-control" name="meta_keywords" rows="2"><?= $doctor['meta_keywords'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Description</label>
                                        <textarea class="form-control" name="meta_description" rows="2"><?= $doctor['meta_description'] ?? '' ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-2"></i> Update Doctor
                                        </button>
                                        <a href="doctors-list.php" class="btn btn-outline-secondary">
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
    </section>

    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
    <script>
        CKEDITOR.replace('longBio');
        
        document.addEventListener("DOMContentLoaded", function() {
            const checkboxes = document.querySelectorAll('.dept-checkbox');
            const selectedContainer = document.getElementById('selectedDepartments');
            const dropdownText = document.getElementById('dropdownText');

            function updateSelectedDepartments() {
                const selected = [];
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        const label = cb.parentElement.textContent.trim();
                        selected.push(label);
                    }
                });

                if (selected.length > 0) {
                    selectedContainer.innerHTML = selected.map(dept =>
                        `<span class="badge bg-primary me-1 mb-1">${dept}</span>`
                    ).join('');
                    dropdownText.textContent = `${selected.length} department(s) selected`;
                } else {
                    selectedContainer.innerHTML = '<small class="text-muted">No departments selected</small>';
                    dropdownText.textContent = 'Select Departments';
                }
            }

            checkboxes.forEach(cb => {
                cb.addEventListener("change", updateSelectedDepartments);
            });

            // Initialize on load
            updateSelectedDepartments();

            // Prevent dropdown from closing when clicking checkboxes
            document.querySelector('.multi-select-dropdown .dropdown-menu').addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Preview profile image before upload
            document.querySelector('input[name="profile_image"]').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const previewContainer = document.getElementById('profilePreview');
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.innerHTML = `<p class="mb-1">New Image Preview:</p><img src="${e.target.result}" class="image-preview" alt="Profile Preview">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.innerHTML = '';
                }
            });
            
            // Preview gallery images
            document.querySelector('input[name="gallery_images[]"]').addEventListener('change', function(e) {
                const files = e.target.files;
                const previewContainer = document.getElementById('galleryPreview');
                previewContainer.innerHTML = '';
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '80px';
                        img.style.height = '80px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '4px';
                        img.style.border = '1px solid #ddd';
                        previewContainer.appendChild(img);
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Auto-generate slug from name
            document.querySelector('input[name="name"]').addEventListener('blur', function() {
                const name = this.value.trim();
                const slugInput = document.querySelector('input[name="slug_url"]');
                
                if (name && (!slugInput.value || slugInput.value === '')) {
                    const slug = name.toLowerCase()
                        .replace(/[^a-z0-9 -]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                    slugInput.value = slug;
                }
            });

            // Form validation
            document.getElementById('doctorForm').addEventListener('submit', function(e) {
                const selectedDepartments = document.querySelectorAll('.dept-checkbox:checked').length;
                if (selectedDepartments === 0) {
                    e.preventDefault();
                    alert('Please select at least one department');
                    return false;
                }
            });
        });
    </script>
</body>
</html>