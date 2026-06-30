<?php
include "db-conn.php";

// Check if user is logged in and has admin privileges (add your own auth check)
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit();
// }

// Initialize variables
$error = '';
$success = '';
$blog = [];

// Get blog data if ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $blog = $result->fetch_assoc();
    } else {
        $error = "Blog post not found.";
        header("Location: view-all-blog.php");
        exit();
    }
    $stmt->close();
} else {
    header("Location: view-all-blog.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // Validate and sanitize input
    $id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    // Validate required fields
    if (empty($title) || empty($content)) {
        $error = "Title and content are required fields.";
    } else {
        // Generate slug
        $slug = generateSlug($title);
        
        // Initialize update data
        $update_data = [
            'title' => $title,
            'content' => $content,
            'slug_url' => $slug,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Handle image upload if provided
        if (!empty($_FILES['image']['name'])) {
            $upload_result = handleImageUpload($_FILES['image'], $id, $conn);
            
            if ($upload_result['success']) {
                $update_data['image'] = $upload_result['filename'];
                
                // Delete old image if exists
                if (!empty($blog['image'])) {
                    deleteImage($blog['image']);
                }
            } else {
                $error = $upload_result['message'];
            }
        }
        
        // Only proceed if no errors
        if (empty($error)) {
            // Build the update query
            $set_parts = [];
            $params = [];
            $types = '';
            
            foreach ($update_data as $field => $value) {
                $set_parts[] = "$field = ?";
                $params[] = $value;
                $types .= 's'; // All fields are strings in this case
            }
            
            $params[] = $id;
            $types .= 'i';
            
            $query = "UPDATE blogs SET " . implode(', ', $set_parts) . " WHERE id = ?";
            
            // Execute the update
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $success = "Blog updated successfully!";
                // Refresh the blog data
                $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $blog = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Error updating blog: " . $conn->error;
            }
        }
    }
}

/**
 * Generate a URL-friendly slug from a string
 */
function generateSlug($string) {
    $slug = strtolower($string);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', ' ', $slug);
    $slug = preg_replace('/\s/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Handle image upload with validation
 */
function handleImageUpload($file, $blog_id, $conn) {
    $uploadDir = "uploads/blogs/";
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Get file info
    $image_name = basename($file['name']);
    $image_tmp = $file['tmp_name'];
    $image_size = $file['size'];
    $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
    
    // Validate file
    if (!in_array($image_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, WEBP & GIF are allowed.'];
    }
    
    if ($image_size > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds maximum limit of 5MB.'];
    }
    
    // Generate unique filename
    $new_filename = uniqid('blog_' . $blog_id . '_', true) . '.' . $image_ext;
    $destination = $uploadDir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($image_tmp, $destination)) {
        return ['success' => true, 'filename' => $new_filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image.'];
    }
}

/**
 * Delete an image file
 */
function deleteImage($filename) {
    $uploadDir = "uploads/blogs/";
    $filepath = $uploadDir . $filename;
    
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Admin | Edit Blog</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <?php include "links.php"; ?>
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
                        <div class="col-lg-12">
                            <div class="white_card card_height_100 mb_30">
                                <div class="white_card_header">
                                    <div class="box_header m-0">
                                        <div class="main-title">
                                            <h2 class="text-center">Update Blog</h2>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="white_card_body">
                                    <div class="card-body">
                                        <?php if (!empty($error)): ?>
                                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($success)): ?>
                                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" enctype="multipart/form-data" class="p-4 shadow bg-white">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($blog['id']); ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Title:</label>
                                                <input type="text" name="title" class="form-control" 
                                                    value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Content:</label>
                                                <textarea name="content" class="form-control" rows="5" id="pro_desc" required>
                                                    <?php echo htmlspecialchars($blog['content']); ?>
                                                </textarea>
                                            </div>
                                        
                                            <div class="mb-3">
                                                <label class="form-label">Current Image:</label><br>
                                                <?php if (!empty($blog['image'])): ?>
                                                    <img src="uploads/blogs/<?php echo htmlspecialchars($blog['image']); ?>" 
                                                        width="300" class="img-thumbnail mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                                        <label class="form-check-label" for="remove_image">Remove current image</label>
                                                    </div>
                                                <?php else: ?>
                                                    <p>No image uploaded</p>
                                                <?php endif; ?>
                                            </div>
                                        
                                            <div class="mb-3">
                                                <label class="form-label">Upload New Image (optional):</label>
                                                <input type="file" name="image" class="form-control" accept="image/*">
                                                <small class="text-muted">Max size: 5MB. Allowed formats: JPG, PNG, WEBP, GIF</small>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <a href="view-all-blog.php" class="btn btn-secondary">Cancel</a>
                                                <button type="submit" name="update" class="btn btn-success">Update Blog</button>
                                            </div>
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
        
        <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
        <script>
            CKEDITOR.replace('pro_desc', {
                toolbar: [
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Blockquote'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['Image', 'Table'] },
                    { name: 'tools', items: ['Maximize'] },
                    { name: 'document', items: ['Source'] }
                ],
                height: 300
            });
        </script>
    </section>
</body>
</html>