<?php
session_start();
include "db-conn.php";

// Define allowed logo locations
$logo_locations = [
    'header' => 'Website Header (Recommended: 300x100px)',
    'footer' => 'Website Footer (Recommended: 200x80px)',
    'favicon' => 'Browser Tab Icon (Recommended: 64x64px)',
    'mobile' => 'Mobile Header (Recommended: 150x50px)',
    'email' => 'Email Signature (Recommended: 300x100px)'
];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_dir = "uploads/logo/";
    $location = $_POST['location'] ?? 'header';

    // Validate location
    if (!array_key_exists($location, $logo_locations)) {
        $_SESSION['error'] = "Invalid logo location selected.";
    } else {
        // Create directory if not exists with proper permissions
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Generate unique filename
        $file_extension = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
        $new_filename = 'logo_' . $location . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        $uploadOk = 1;

        // Check if image file is a actual image
        $check = getimagesize($_FILES["logo"]["tmp_name"]);
        if ($check === false) {
            $_SESSION['error'] = "File is not an image.";
            $uploadOk = 0;
        }

        // Check MIME type
        $mime = mime_content_type($_FILES["logo"]["tmp_name"]);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            $_SESSION['error'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }

        // Check file size (500KB)
        if ($_FILES["logo"]["size"] > 500000) {
            $_SESSION['error'] = "Sorry, your file is too large (max 500KB).";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
                // First deactivate any existing logo for this location
                $deactivate_stmt = $conn->prepare("UPDATE logos SET is_active = 0 WHERE location = ?");
                $deactivate_stmt->bind_param("s", $location);
                $deactivate_stmt->execute();
                $deactivate_stmt->close();

                // Insert new logo into database
                $relative_path = 'logo/' . $new_filename;
                $stmt = $conn->prepare("INSERT INTO logos (logo_path, location, uploaded_at, is_active) VALUES (?, ?, NOW(), 1)");
                $stmt->bind_param("ss", $relative_path, $location);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "The logo for " . $logo_locations[$location] . " has been uploaded successfully.";
                    $_SESSION['active_tab'] = $location; // Store the active tab in session
                } else {
                    $_SESSION['error'] = "Database error: " . $conn->error;
                    if (file_exists($target_file)) {
                        unlink($target_file);
                    }
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Sorry, there was an error uploading your file.";
            }
        }
    }

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get messages from session
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
$active_tab = $_SESSION['active_tab'] ?? 'header';

// Clear messages from session
unset($_SESSION['error']);
unset($_SESSION['success']);
unset($_SESSION['active_tab']);

// Fetch all current logos
$logos_query = "SELECT * FROM logos WHERE is_active = 1 ORDER BY location";
$logos_result = mysqli_query($conn, $logos_query);
$current_logos = [];
while ($row = mysqli_fetch_assoc($logos_result)) {
    $current_logos[$row['location']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Logo Management | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .logo-preview-container {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: #f9f9f9;
        }

        .location-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }

        .location-tabs .nav-link.active {
            color: #4361ee;
            border-bottom: 2px solid #4361ee;
            background: transparent;
        }

        .logo-dimensions {
            font-size: 0.8rem;
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
            <div class="container-fluid p-0 p-sm-3">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30" style="border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                            <div class="white_card_header" style="border-bottom: 1px solid #f1f1f1;">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0" style="font-size: 1.5rem; font-weight: 600;">Logo Management</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body p-3 p-sm-4">
                                <!-- Upload New Logo Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="logo-container p-3" style="border: 1px dashed #ddd; border-radius: 8px;">
                                            <h4 class="card-title mb-3" style="font-size: 1.2rem; font-weight: 500;">Upload New Logo</h4>

                                            <?php if (isset($error)): ?>
                                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" style="font-size: 0.9rem;">
                                                    <?php echo $error; ?>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (isset($success)): ?>
                                                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="font-size: 0.9rem;">
                                                    <?php echo $success; ?>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            <?php endif; ?>

                                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="location" class="form-label mb-2" style="font-weight: 500;">Logo Location</label>
                                                        <select class="form-select" name="location" id="location" required>
                                                            <?php foreach ($logo_locations as $key => $value): ?>
                                                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="logo" class="form-label mb-2" style="font-weight: 500;">Select Logo Image</label>
                                                        <div class="upload-area position-relative" style="border: 2px dashed #4361ee;border-radius: 8px;padding: 1.5rem;text-align: center;cursor: pointer;display: flow;align-items: center;" onclick="document.getElementById('logo').click()">
                                                            <i class="fas fa-cloud-upload-alt mb-2" style="font-size: 1.5rem; color: #4361ee;"></i>
                                                            <p class="mb-1" style="color: #4361ee; font-size: 0.9rem;">Click to browse or drag & drop</p>
                                                            <p class="text-muted" style="font-size: 0.75rem;">Supports: JPG, PNG, JPEG, GIF, WEBP</p>
                                                            <input type="file" name="logo" id="logo" accept="image/*" required style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;">
                                                        </div>
                                                        <div class="file-info mt-2" id="fileInfo" style="font-size: 0.85rem; color: #666;">No file chosen</div>
                                                        <small class="form-text text-muted" style="font-size: 0.75rem;">Max size: 500KB</small>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-primary w-100 py-2" style="background: #4361ee; border: none; font-weight: 500;">
                                                    <i class="fas fa-upload me-2"></i>Upload Logo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Current Logos Section - Simplified -->
                                <div class="row">
                                    <div class="col-12">
                                        <h4 class="mb-3">Current Logos</h4>

                                        <div class="row">
                                            <?php foreach ($logo_locations as $key => $value): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="card h-100">
                                                        <div class="card-header bg-light">
                                                            <h5 class="mb-0"><?php echo ucfirst($key); ?> Logo</h5>
                                                        </div>
                                                        <div class="card-body text-center">
                                                            <?php if (isset($current_logos[$key])):
                                                                $logo_image = $current_logos[$key]['logo_path'];
                                                                if (strpos($logo_image, 'uploads/') === false) {
                                                                    $logo_image = 'uploads/' . $logo_image;
                                                                }
                                                            ?>
                                                                <?php if (file_exists($logo_image)): ?>
                                                                    <img src="<?php echo htmlspecialchars($logo_image); ?>"
                                                                        alt="<?php echo ucfirst($key); ?> Logo"
                                                                        class="img-fluid mb-3"
                                                                        style="max-height: 100px;">
                                                                <?php else: ?>
                                                                    <div class="text-muted">
                                                                        <i class="fas fa-image fa-3x mb-2"></i>
                                                                        <p>Logo image not found</p>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <p class="small text-muted mb-1">
                                                                    <strong>Uploaded:</strong>
                                                                    <?php echo date('M d, Y', strtotime($current_logos[$key]['uploaded_at'])); ?>
                                                                </p>
                                                            <?php else: ?>
                                                                <div class="text-muted">
                                                                    <i class="fas fa-image fa-3x mb-2"></i>
                                                                    <p>No logo uploaded</p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-footer bg-white">
                                                            <small class="text-muted"><?php echo $value; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
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
        // Display selected file name
        document.getElementById('logo').addEventListener('change', function(e) {
            var fileName = e.target.files[0] ? e.target.files[0].name : "No file chosen";
            document.getElementById('fileInfo').textContent = fileName;
            document.getElementById('fileInfo').style.color = '#4361ee';
            document.getElementById('fileInfo').style.fontWeight = '500';
        });

        // Drag and drop functionality
        const uploadArea = document.querySelector('.upload-area');
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
            uploadArea.style.borderColor = '#3a0ca3';
            uploadArea.style.backgroundColor = 'rgba(67, 97, 238, 0.05)';
        }

        function unhighlight() {
            uploadArea.style.borderColor = '#4361ee';
            uploadArea.style.backgroundColor = 'transparent';
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('logo').files = files;
            const event = new Event('change');
            document.getElementById('logo').dispatchEvent(event);
        }

        // Activate the correct tab based on URL hash or session
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($active_tab)): ?>
                // Activate the tab that was just uploaded to
                var tabTrigger = new bootstrap.Tab(document.getElementById('<?php echo $active_tab; ?>-tab'));
                tabTrigger.show();
            <?php endif; ?>
        });
    </script>
</body>

</html>