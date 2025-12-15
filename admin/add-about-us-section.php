<?php
include "db-conn.php";

// Initialize variables
$sections = [];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Handle section addition/update
        if (isset($_POST['save_section'])) {
            $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
            $title = trim($_POST['section_title']);
            $content = trim($_POST['section_content']);
            $order = (int)$_POST['section_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validate required fields
            if (empty($title) || empty($content)) {
                throw new Exception("Title and content are required");
            }

            // Handle file upload
            $image_path = null;
            if (isset($_FILES['section_image']) && $_FILES['section_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/about_us/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($_FILES['section_image']['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Only JPG, PNG, GIF, and WEBP images are allowed");
                }
                
                $file_ext = pathinfo($_FILES['section_image']['name'], PATHINFO_EXTENSION);
                $file_name = 'section-' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['section_image']['tmp_name'], $target_path)) {
                    $image_path = $target_path;
                } else {
                    throw new Exception("Failed to upload image");
                }
            }

            // Check if we're updating or inserting
            if ($section_id > 0) {
                // Update existing section
                $current_image = null;
                
                // Get current image if exists
                $stmt = $conn->prepare("SELECT image_url FROM about_us_sections WHERE id = ?");
                $stmt->bind_param('i', $section_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $current_image = $row['image_url'];
                }
                
                // If new image was uploaded or remove_image is checked
                if (!empty($image_path) || isset($_POST['remove_image'])) {
                    // Delete old image if it exists
                    if (!empty($current_image) && file_exists($current_image)) {
                        unlink($current_image);
                    }
                    
                    if (!empty($image_path)) {
                        $stmt = $conn->prepare("UPDATE about_us_sections SET section_title = ?, section_content = ?, image_url = ?, section_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param('sssiii', $title, $content, $image_path, $order, $is_active, $section_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE about_us_sections SET section_title = ?, section_content = ?, image_url = NULL, section_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param('ssiii', $title, $content, $order, $is_active, $section_id);
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE about_us_sections SET section_title = ?, section_content = ?, section_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param('ssiii', $title, $content, $order, $is_active, $section_id);
                }
            } else {
                // Insert new section
                $stmt = $conn->prepare("INSERT INTO about_us_sections (section_title, section_content, image_url, section_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param('sssii', $title, $content, $image_path, $order, $is_active);
            }
            
            if ($stmt->execute()) {
                $success_message = "Section saved successfully!";
            } else {
                throw new Exception("Database error: " . $conn->error);
            }
        }
        
        // Handle section deletion
        if (isset($_POST['delete_section'])) {
            $section_id = (int)$_POST['section_id'];
            
            // First get the image path to delete the file
            $stmt = $conn->prepare("SELECT image_url FROM about_us_sections WHERE id = ?");
            $stmt->bind_param('i', $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (!empty($row['image_url']) && file_exists($row['image_url'])) {
                    unlink($row['image_url']);
                }
            }
            
            // Now delete the section
            $stmt = $conn->prepare("DELETE FROM about_us_sections WHERE id = ?");
            $stmt->bind_param('i', $section_id);
            
            if ($stmt->execute()) {
                $success_message = "Section deleted successfully!";
            } else {
                throw new Exception("Failed to delete section");
            }
        }
        
        // Handle section ordering update
        if (isset($_POST['update_order'])) {
            if (!empty($_POST['order'])) {
                foreach ($_POST['order'] as $order => $section_id) {
                    $section_id = (int)$section_id;
                    $order = (int)$order + 1; // Start from 1 instead of 0
                    
                    $stmt = $conn->prepare("UPDATE about_us_sections SET section_order = ? WHERE id = ?");
                    $stmt->bind_param('ii', $order, $section_id);
                    $stmt->execute();
                }
                $success_message = "Section order updated successfully!";
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all sections ordered by their display order
$sql = "SELECT * FROM about_us_sections ORDER BY section_order ASC, created_at DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>About Us Sections | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .section-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .section-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .section-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            cursor: move;
        }
        .section-card .card-body {
            padding: 20px;
        }
        .section-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        .sortable-ghost {
            opacity: 0.5;
            background: #c8ebfb;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        .btn-section {
            border-radius: 6px;
            padding: 8px 15px;
            font-weight: 500;
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
                                <h2 class="mb-0">About Us Sections</h2>
                                
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-12">
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
                        
                        <!-- Add New Section Button -->
                        <div class="mb-4">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectionModal">
                                <i class="fas fa-plus me-2"></i>Add New Section
                            </button>
                            
                            <?php if (!empty($sections)): ?>
                            <button type="button" id="saveOrderBtn" class="btn btn-success ms-2">
                                <i class="fas fa-save me-2"></i>Save Order
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sections List -->
                        <div id="sectionsContainer" class="mb-5">
                            <?php if (empty($sections)): ?>
                                <div class="alert alert-info">
                                    No sections found. Add your first section using the button above.
                                </div>
                            <?php else: ?>
                                <form id="orderForm" method="post">
                                    <input type="hidden" name="update_order" value="1">
                                    <div id="sortableSections">
                                        <?php foreach ($sections as $section): ?>
                                            <div class="section-card mb-3" data-id="<?= $section['id'] ?>">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0">
                                                        <?= htmlspecialchars($section['section_title']) ?>
                                                        <span class="status-badge <?= $section['is_active'] ? 'status-active' : 'status-inactive' ?> ms-2">
                                                            <?= $section['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </h5>
                                                    <div>
                                                        <span class="badge bg-secondary me-2">Order: <?= $section['section_order'] ?></span>
                                                        <button type="button" class="btn btn-sm btn-outline-primary me-2 edit-section" 
                                                                data-id="<?= $section['id'] ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this section?');">
                                                            <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                                                            <input type="hidden" name="delete_section" value="1">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-8">
                                                            <div class="mb-3">
                                                                <?= nl2br(htmlspecialchars(substr($section['section_content'], 0, 200))) ?>
                                                                <?= strlen($section['section_content']) > 200 ? '...' : '' ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <?php if (!empty($section['image_url'])): ?>
                                                                <img src="<?= $section['image_url'] ?>" alt="Section Image" class="section-image">
                                                            <?php else: ?>
                                                                <span class="text-muted">No image</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="order[]" value="<?= $section['id'] ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Modal (Add/Edit) -->
        <div class="modal fade" id="sectionModal" tabindex="-1" aria-labelledby="sectionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="post" enctype="multipart/form-data" id="sectionForm">
                        <input type="hidden" name="section_id" id="sectionId" value="">
                        <input type="hidden" name="save_section" value="1">
                        
                        <div class="modal-header">
                            <h5 class="modal-title" id="sectionModalLabel">Add New Section</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Section Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="section_title" id="sectionTitle" required>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Section Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="section_content" id="sectionContent" rows="6" required></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" class="form-control" name="section_order" id="sectionOrder" min="1" value="1">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                        <label class="form-check-label" for="isActive">Active</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Section Image</label>
                                    <input type="file" class="form-control" name="section_image" id="sectionImage" accept="image/*">
                                    <small class="text-muted">Optional. Recommended size: 1200x800px</small>
                                    
                                    <div id="imagePreviewContainer" class="mt-3" style="display:none;">
                                        <label class="form-label">Current Image</label>
                                        <img id="imagePreview" src="" class="img-thumbnail" style="max-width: 200px;">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage">
                                            <label class="form-check-label" for="removeImage">
                                                Remove current image
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
        <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
        <script>
            // Initialize CKEditor
            CKEDITOR.replace('sectionContent');
            
            // Initialize sortable sections
            new Sortable(document.getElementById('sortableSections'), {
                animation: 150,
                ghostClass: 'sortable-ghost',
                handle: '.card-header',
                onEnd: function() {
                    // Update order numbers visually
                    document.querySelectorAll('#sortableSections .section-card').forEach((card, index) => {
                        const badge = card.querySelector('.badge.bg-secondary');
                        if (badge) {
                            badge.textContent = `Order: ${index + 1}`;
                        }
                    });
                }
            });
            
            // Save order button
            document.getElementById('saveOrderBtn').addEventListener('click', function() {
                document.getElementById('orderForm').submit();
            });
            
            // Edit section button
            document.querySelectorAll('.edit-section').forEach(button => {
                button.addEventListener('click', function() {
                    const sectionId = this.getAttribute('data-id');
                    
                    // Fetch section data via AJAX
                    fetch('get_section.php?id=' + sectionId)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('sectionId').value = data.id;
                            document.getElementById('sectionTitle').value = data.section_title;
                            CKEDITOR.instances.sectionContent.setData(data.section_content);
                            document.getElementById('sectionOrder').value = data.section_order;
                            document.getElementById('isActive').checked = data.is_active == 1;
                            
                            // Handle image preview
                            const previewContainer = document.getElementById('imagePreviewContainer');
                            const previewImage = document.getElementById('imagePreview');
                            
                            if (data.image_url) {
                                previewImage.src = data.image_url;
                                previewContainer.style.display = 'block';
                                document.getElementById('removeImage').checked = false;
                            } else {
                                previewContainer.style.display = 'none';
                            }
                            
                            // Update modal title
                            document.getElementById('sectionModalLabel').textContent = 'Edit Section';
                            
                            // Show modal
                            const modal = new bootstrap.Modal(document.getElementById('sectionModal'));
                            modal.show();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Failed to load section data');
                        });
                });
            });
            
            // Reset modal when adding new section
            document.getElementById('sectionModal').addEventListener('show.bs.modal', function(event) {
                if (!event.relatedTarget) {
                    // Reset form for new section
                    document.getElementById('sectionForm').reset();
                    document.getElementById('sectionId').value = '';
                    CKEDITOR.instances.sectionContent.setData('');
                    document.getElementById('imagePreviewContainer').style.display = 'none';
                    document.getElementById('sectionModalLabel').textContent = 'Add New Section';
                }
            });
            
            // Image preview for new uploads
            document.getElementById('sectionImage').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewImage = document.getElementById('imagePreview');
                        previewImage.src = e.target.result;
                        document.getElementById('imagePreviewContainer').style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            });
        </script>
    </body>
</html>