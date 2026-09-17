<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../util/function.php';
admin_jwt_guard();

$csrf = Security::csrfToken();

// Initialize variables
$error = '';
$success = '';
$blog = [];

// Get blog data if ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $blog = $result->fetch_assoc();
    } else {
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
    if (!Security::verifyCsrf($_POST['_csrf'] ?? '')) {
        $error = "Security token expired. Please reload the page and try again.";
    } else {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $excerpt = trim($_POST['excerpt'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : $blog['status'];

        if (empty($title) || empty($content) || empty($author)) {
            $error = "Title, content and author are required fields.";
        } else {
            $slug = generate_unique_blog_slug($conn, $title, $id);

            $update_data = [
                'title'             => $title,
                'content'           => $content,
                'excerpt'           => $excerpt,
                'slug_url'          => $slug,
                'author'            => $author,
                'category'          => $category,
                'tags'              => $tags,
                'meta_title'        => $meta_title,
                'meta_description'  => $meta_description,
                'status'            => $status,
                'updated_at'        => date('Y-m-d H:i:s'),
            ];

            // Remove current image if requested (and no replacement is uploaded)
            $remove_image = isset($_POST['remove_image']) && !empty($_FILES['image']['name']) === false;

            // Handle image upload if provided
            if (!empty($_FILES['image']['name'])) {
                $upload_result = handleImageUpload($_FILES['image']);

                if ($upload_result['success']) {
                    $update_data['image'] = $upload_result['filename'];
                    if (!empty($blog['image'])) {
                        deleteImage($blog['image']);
                    }
                } else {
                    $error = $upload_result['message'];
                }
            } elseif ($remove_image) {
                if (!empty($blog['image'])) {
                    deleteImage($blog['image']);
                }
                $update_data['image'] = '';
            }

            if (empty($error)) {
                $set_parts = [];
                $params = [];
                $types = '';

                foreach ($update_data as $field => $value) {
                    $set_parts[] = "$field = ?";
                    $params[] = $value;
                    $types .= 's';
                }

                $params[] = $id;
                $types .= 'i';

                $query = "UPDATE blogs SET " . implode(', ', $set_parts) . " WHERE id = ?";

                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);

                if ($stmt->execute()) {
                    $success = "Blog updated successfully!";
                    $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $blog = $result->fetch_assoc();
                    $stmt->close();
                } else {
                    error_log('[admin/edit-blog] DB error: ' . $conn->error);
                    $error = "Could not update the blog post. Please try again.";
                }
            }
        }
    }
}

/**
 * Handle image upload with validation
 */
function handleImageUpload($file) {
    $uploadDir = "uploads/blogs/";
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $image_name = basename($file['name']);
    $image_tmp = $file['tmp_name'];
    $image_size = $file['size'];
    $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

    if (!in_array($image_ext, $allowed_types) || @getimagesize($image_tmp) === false) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, WEBP & GIF are allowed.'];
    }

    if ($image_size > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds maximum limit of 5MB.'];
    }

    $new_filename = uniqid('blog_', true) . '.' . $image_ext;
    $destination = $uploadDir . $new_filename;

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
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Admin | Edit Blog</title>

    <?php include "links.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($blog['id']); ?>">

                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="mb-3">
                                                        <label class="form-label">Title:</label>
                                                        <input type="text" name="title" class="form-control"
                                                            value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Excerpt <span class="text-muted small">(shown on blog cards)</span>:</label>
                                                        <textarea name="excerpt" class="form-control" rows="2" maxlength="300"><?php echo htmlspecialchars($blog['excerpt'] ?? ''); ?></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Content:</label>
                                                        <textarea name="content" class="form-control" rows="10" id="pro_desc" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                                                    </div>

                                                    <hr>
                                                    <h6 class="mb-3">SEO (optional)</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Meta Title:</label>
                                                        <input type="text" name="meta_title" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($blog['meta_title'] ?? ''); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Meta Description:</label>
                                                        <textarea name="meta_description" class="form-control" rows="2" maxlength="300"><?php echo htmlspecialchars($blog['meta_description'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Author:</label>
                                                        <input type="text" name="author" class="form-control" required value="<?php echo htmlspecialchars($blog['author'] ?? ''); ?>">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Category:</label>
                                                        <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($blog['category'] ?? ''); ?>">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Tags <span class="text-muted small">(comma separated)</span>:</label>
                                                        <input type="text" name="tags" class="form-control" value="<?php echo htmlspecialchars($blog['tags'] ?? ''); ?>">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Status:</label>
                                                        <select name="status" class="form-control" required>
                                                            <option value="draft" <?= $blog['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                            <option value="published" <?= $blog['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                                            <option value="archived" <?= $blog['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Current Image:</label><br>
                                                        <?php if (!empty($blog['image'])): ?>
                                                            <img src="uploads/blogs/<?php echo htmlspecialchars($blog['image']); ?>"
                                                                width="200" class="img-thumbnail mb-2">
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
                                                </div>
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

            // Uploading a new image supersedes "remove current image"
            document.querySelector('input[name="image"]')?.addEventListener('change', function () {
                var removeBox = document.getElementById('remove_image');
                if (removeBox && this.files.length) removeBox.checked = false;
            });
        </script>
    </section>
</body>
</html>
