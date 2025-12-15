<?php
session_start();
include "db-conn.php";

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    // Validate inputs
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    
    // Validate required fields
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($_FILES['banner']['name'])) {
        $errors[] = "Banner image is required.";
    }
    
    // Validate dates if provided
    if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
        $errors[] = "End date must be after start date.";
    }
    
    // Process file upload if no errors
    if (empty($errors)) {
        $target_dir = "uploads/banners/";
        $file_ext = strtolower(pathinfo($_FILES["banner"]["name"], PATHINFO_EXTENSION));
        $target_file = $target_dir . uniqid('banner_') . '.' . $file_ext;
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES["banner"]["tmp_name"]);
        if ($check === false) {
            $errors[] = "File is not an image.";
        }
        
        // Check file size (5MB limit)
        if ($_FILES["banner"]["size"] > 5000000) {
            $errors[] = "Sorry, your file is too large (max 5MB).";
        }
        
        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_types)) {
            $errors[] = "Only JPG, JPEG, PNG, GIF & WEBP files are allowed.";
        }
        
        // If no errors, proceed with upload and database insert
        if (empty($errors)) {
            if (move_uploaded_file($_FILES["banner"]["tmp_name"], $target_file)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO banners 
                    (banner_path, title, description, link_url, status, display_order, uploaded_at, start_date, end_date) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                
                $stmt->bind_param("ssssiiss", 
                    $target_file, 
                    $title, 
                    $description, 
                    $link_url, 
                    $status, 
                    $display_order, 
                    $start_date, 
                    $end_date
                );
                
                if ($stmt->execute()) {
                    $success = "The banner has been uploaded successfully.";
                } else {
                    // Delete the uploaded file if DB insert fails
                    unlink($target_file);
                    $errors[] = "Sorry, there was an error saving to the database: " . $conn->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Sorry, there was an error uploading your file.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Banner Management | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --danger-color: #f72585;
            --success-color: #4bb543;
        }
        
        .upload-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(67, 97, 238, 0.05);
        }
        
        .upload-area i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .banner-preview {
            max-width: 100%;
            max-height: 200px;
            display: none;
            margin: 1rem auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .banner-table img {
            max-width: 300px;
            max-height: 100px;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .banner-table img:hover {
            transform: scale(1.05);
        }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .section-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
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
        
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(75, 181, 67, 0.2);
            color: #4bb543;
        }
        
        .status-inactive {
            background-color: rgba(247, 37, 133, 0.2);
            color: #f72585;
        }
        
        .date-range {
            font-size: 0.85rem;
            color: #6c757d;
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
                                        <h2 class="m-0">Banner Management</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Display messages -->
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($success) && !empty($success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($success) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Upload Form -->
                                <div class="upload-card">
                                    <h3 class="section-title">Upload New Banner</h3>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" id="bannerForm">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="title" class="form-label">Banner Title *</label>
                                                    <input type="text" class="form-control" id="title" name="title" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="description" class="form-label">Description</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="link_url" class="form-label">Link URL</label>
                                                    <input type="url" class="form-control" id="link_url" name="link_url" placeholder="https://example.com">
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status">
                                                        <option value="0">Active</option>
                                                        <option value="1">Inactive</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="display_order" class="form-label">Display Order</label>
                                                    <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="start_date" class="form-label">Start Date (optional)</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="end_date" class="form-label">End Date (optional)</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="upload-area" id="uploadArea">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <h5>Drag & Drop your banner image here</h5>
                                            <p class="text-muted">or click to browse files</p>
                                            <img id="bannerPreview" class="banner-preview" alt="Banner Preview">
                                        </div>
                                        <input type="file" name="banner" id="banner" accept="image/*" class="d-none" required>
                                        
                                        <div class="d-grid gap-2 mt-3">
                                            <button type="submit" name="submit" class="btn btn-primary">
                                                <i class="fas fa-upload me-2"></i> Upload Banner
                                            </button>
                                        </div>
                                        <div class="mt-2 text-muted small">
                                            <p>Allowed formats: JPG, JPEG, PNG, GIF, WEBP | Max size: 5MB</p>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Banner List -->
                                <div class="mt-5">
                                    <h3 class="section-title">Current Banners</h3>
                                    <div class="table-responsive">
                                        <table class="table banner-table">
                                            <thead>
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="25%">Banner</th>
                                                    <th width="20%">Details</th>
                                                    <th width="15%">Status</th>
                                                    <th width="15%">Dates</th>
                                                    <th width="20%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Fetch and display uploaded banners
                                                $banner_query = "SELECT * FROM banners ORDER BY display_order ASC, uploaded_at DESC";
                                                $banner_res = mysqli_query($conn, $banner_query);
                                                
                                                if ($banner_res && mysqli_num_rows($banner_res) > 0) {
                                                    $sno = 1;
                                                    while ($banner_row = mysqli_fetch_assoc($banner_res)) {
                                                        $status_class = $banner_row['status'] == 0 ? 'status-active' : 'status-inactive';
                                                        $status_text = $banner_row['status'] == 0 ? 'Active' : 'Inactive';
                                                        
                                                        // Format dates
                                                        $uploaded_date = date('M d, Y', strtotime($banner_row['uploaded_at']));
                                                        $start_date = $banner_row['start_date'] ? date('M d, Y', strtotime($banner_row['start_date'])) : 'Not set';
                                                        $end_date = $banner_row['end_date'] ? date('M d, Y', strtotime($banner_row['end_date'])) : 'Not set';
                                                        ?>
                                                        <tr>
                                                            <td><?= $sno++ ?></td>
                                                            <td>
                                                                <img src="<?= htmlspecialchars($banner_row['banner_path']??'') ?>" 
                                                                     alt="<?= htmlspecialchars($banner_row['title']) ?>" 
                                                                     class="img-fluid">
                                                            </td>
                                                            <td>
                                                                <h6 class="mb-1"><?= htmlspecialchars($banner_row['title']??' ') ?></h6>
                                                                <?php if (!empty($banner_row['description'])): ?>
                                                                    <p class="small text-muted mb-1"><?= htmlspecialchars($banner_row['description']) ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($banner_row['link_url'])): ?>
                                                                    <a href="<?= htmlspecialchars($banner_row['link_url']) ?>" target="_blank" class="small">View Link</a>
                                                                <?php endif; ?>
                                                                <div class="small mt-1">Order: <?= $banner_row['display_order'] ?></div>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="date-range">
                                                                    <div class="small"><strong>Start:</strong> <?= $start_date ?></div>
                                                                    <div class="small"><strong>End:</strong> <?= $end_date ?></div>
                                                                    <div class="small mt-1"><strong>Uploaded:</strong> <?= $uploaded_date ?></div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex gap-2">
                                                                    <a href="edit_banner.php?id=<?= $banner_row['id'] ?>" 
                                                                       class="btn btn-outline-primary btn-action">
                                                                        <i class="fas fa-edit"></i> Edit
                                                                    </a>
                                                                    <a href="delete_banner.php?id=<?= $banner_row['id'] ?>" 
                                                                       class="btn btn-outline-danger btn-action" 
                                                                       onclick="return confirm('Are you sure you want to delete this banner?')">
                                                                        <i class="fas fa-trash"></i> Delete
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <div class="d-flex flex-column align-items-center">
                                                                <i class="fas fa-image text-muted mb-2" style="font-size: 2rem;"></i>
                                                                <p class="text-muted">No banners uploaded yet</p>
                                                                <p class="text-muted small">Upload your first banner using the form above</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
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
        // File upload preview and drag-drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('banner');
            const preview = document.getElementById('bannerPreview');
            const form = document.getElementById('bannerForm');
            
            // Click on upload area triggers file input
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            // File input change event
            fileInput.addEventListener('change', function(e) {
                if (fileInput.files && fileInput.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(event) {
                        preview.src = event.target.result;
                        preview.style.display = 'block';
                        uploadArea.querySelector('h5').textContent = fileInput.files[0].name;
                        uploadArea.querySelector('p').textContent = (fileInput.files[0].size / 1024 / 1024).toFixed(2) + 'MB';
                    };
                    
                    reader.readAsDataURL(fileInput.files[0]);
                }
            });
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                uploadArea.classList.add('bg-light');
            }
            
            function unhighlight() {
                uploadArea.classList.remove('bg-light');
            }
            
            // Handle dropped files
            uploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    fileInput.files = files;
                    
                    // Trigger change event manually
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            }
            
            // Form validation
            form.addEventListener('submit', function(e) {
                if (!fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    alert('Please select a banner image to upload');
                    return;
                }
                
                const title = document.getElementById('title').value.trim();
                if (!title) {
                    e.preventDefault();
                    alert('Please enter a banner title');
                    return;
                }
                
                // Validate dates if provided
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                
                if (startDate && endDate) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    if (start > end) {
                        e.preventDefault();
                        alert('End date must be after start date');
                        return;
                    }
                }
            });
        });
    </script>
</body>
</html>