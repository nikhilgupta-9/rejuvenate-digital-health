<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db-conn.php";
include_once "functions.php";

$success_message = $error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

        // Generate doctor UID
        $doctor_uid = 'DOC' . date('YmdHis') . rand(100, 999);

        // Handle profile image upload
        $profile_image_path = '';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/doctors/profile/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
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

            $profile_image_path = $target_path;
        }

        // Handle gallery images upload
        $gallery_images_paths = [];
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

        // Start transaction for data consistency
        $conn->begin_transaction();

        try {
            // Insert doctor
            $stmt = $conn->prepare("INSERT INTO doctors (
                doctor_uid, name, degrees, specialization, experience_years, rating, languages, 
                consultation_fee, short_bio, long_bio, profile_image, gallery_images, education, 
                area_of_expertise, status, meta_title, meta_keywords, meta_description, slug_url,
                added_on, is_verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)");

            $stmt->bind_param(
                'ssssidsssssssssssss',
                $doctor_uid,
                $name,
                $degrees,
                $specialization,
                $experience_years,
                $rating,
                $languages,
                $consultation_fee,
                $short_bio,
                $long_bio,
                $profile_image_path,
                $gallery_images_json,
                $education,
                $area_of_expertise,
                $status,
                $meta_title,
                $meta_keywords,
                $meta_description,
                $slug_url
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to insert doctor: " . $conn->error);
            }

            $doctor_id = $stmt->insert_id;
            $stmt->close();

            // Insert doctor departments
            if (!empty($doctor_departments)) {
                $dept_stmt = $conn->prepare("INSERT INTO doctor_departments (doctor_id, category_id, added_on) VALUES (?, ?, NOW())");

                foreach ($doctor_departments as $dept_id) {
                    $dept_id = intval($dept_id);
                    if ($dept_id > 0) {
                        $dept_stmt->bind_param('ii', $doctor_id, $dept_id);
                        if (!$dept_stmt->execute()) {
                            throw new Exception("Failed to insert department: " . $conn->error);
                        }
                    }
                }
                $dept_stmt->close();
            }

            // Commit transaction
            $conn->commit();

            $_SESSION['success_message'] = "Doctor added successfully with " . count($doctor_departments) . " department(s)!";
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add Doctor | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .doctor-form {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #7367f0;
            box-shadow: 0 0 0 3px rgba(115, 103, 240, .15);
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

        .gallery-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
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
                                <h2 class="mb-0">Add New Doctor</h2>
                                <a href="doctors-list.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="doctor-form">
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= $error_message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" id="doctorForm">
                                <!-- Basic Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Basic Information</h4>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Doctor Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Degrees</label>
                                        <input type="text" class="form-control" name="degrees" placeholder="MBBS, MD, DGO" value="<?= htmlspecialchars($_POST['degrees'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Specialization <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="specialization" required placeholder="Cardiology, Neurology, etc." value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Languages</label>
                                        <input type="text" class="form-control" name="languages" placeholder="Hindi, English, Punjabi" value="<?= htmlspecialchars($_POST['languages'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Experience (Years)</label>
                                        <input type="number" class="form-control" name="experience_years" min="0" value="<?= $_POST['experience_years'] ?? '' ?>">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Rating</label>
                                        <input type="number" class="form-control" name="rating" min="0" max="5" step="0.1" value="<?= $_POST['rating'] ?? '' ?>">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Consultation Fee (₹)</label>
                                        <input type="number" class="form-control" name="consultation_fee" min="0" step="0.01" value="<?= $_POST['consultation_fee'] ?? '' ?>">
                                    </div>

                                    <style>
                                        .multi-select-dropdown {
                                            position: relative;
                                        }

                                        .multi-select-dropdown .dropdown-menu {
                                            max-height: 250px;
                                            overflow-y: auto;
                                            width: 100%;
                                        }
                                    </style>

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
                                                ?>
                                                        <li>
                                                            <label class="form-check mb-2 d-block">
                                                                <input class="form-check-input dept-checkbox"
                                                                    type="checkbox"
                                                                    name="department[]"
                                                                    value="<?= $depart['cate_id'] ?>">
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
                                            <small class="text-muted">No departments selected</small>
                                        </div>
                                    </div>



                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="Active" <?= ($_POST['status'] ?? '') == 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option value="Inactive" <?= ($_POST['status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
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
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Choose Profile Image
                                                <input type="file" name="profile_image" class="file-upload-input" accept="image/*">
                                            </label>
                                        </div>
                                        <div id="profilePreview"></div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">Gallery Images</label>
                                        <div class="file-upload mb-2">
                                            <label class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Choose Gallery Images
                                                <input type="file" name="gallery_images[]" class="file-upload-input" accept="image/*" multiple>
                                            </label>
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
                                        <textarea class="form-control" name="short_bio" rows="3" placeholder="Brief introduction"><?= htmlspecialchars($_POST['short_bio'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Long Bio/Description</label>
                                        <textarea class="form-control" name="long_bio" id="longBio" rows="6"><?= htmlspecialchars($_POST['long_bio'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Education</label>
                                        <textarea class="form-control" name="education" rows="3" placeholder="Educational background"><?= htmlspecialchars($_POST['education'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Area of Expertise</label>
                                        <textarea class="form-control" name="area_of_expertise" rows="3" placeholder="Specific areas of expertise"><?= htmlspecialchars($_POST['area_of_expertise'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <!-- SEO Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">SEO Information</h4>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" name="meta_title" value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Slug URL</label>
                                        <input type="text" class="form-control" name="slug_url" value="<?= htmlspecialchars($_POST['slug_url'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Keywords</label>
                                        <textarea class="form-control" name="meta_keywords" rows="2"><?= htmlspecialchars($_POST['meta_keywords'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Description</label>
                                        <textarea class="form-control" name="meta_description" rows="2"><?= htmlspecialchars($_POST['meta_description'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-2"></i> Add Doctor
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
                        previewContainer.innerHTML = `<img src="${e.target.result}" class="image-preview" alt="Profile Preview">`;
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

        // Initialize CKEditor
        CKEDITOR.replace('longBio');
    </script>
</body>

</html>