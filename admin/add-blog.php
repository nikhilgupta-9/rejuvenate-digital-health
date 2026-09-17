<?php
require_once __DIR__ . '/auth/bootstrap.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../util/function.php';

$csrf = Security::csrfToken();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = "Security token expired. Please try again.";
        header("Location: add-blog.php");
        exit();
    }

    // Validate and sanitize inputs
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'draft';

    if ($title === '' || $content === '' || $author === '') {
        $_SESSION['error'] = "Title, content and author are required fields.";
        header("Location: add-blog.php");
        exit();
    }

    // Generate a unique slug from the title
    $slug_url = generate_unique_blog_slug($conn, $title);

    // File upload handling
    $upload_success = false;
    $image_name = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/blogs/";

        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Get file info
        $fileName = basename($_FILES['image']['name']);
        $fileTmp = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Generate unique filename
        $newFileName = uniqid('blog_', true) . '.' . $fileType;
        $uploadPath = $uploadDir . $newFileName;

        // Validate file: extension AND real image content (blocks a
        // disguised non-image file renamed with an image extension)
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileType, $allowedTypes) && @getimagesize($fileTmp) !== false) {
            // Validate file size (5MB max)
            if ($fileSize <= 5000000) {
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    $upload_success = true;
                    $image_name = $newFileName;
                } else {
                    $_SESSION['error'] = "Failed to upload image. Check directory permissions.";
                }
            } else {
                $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG, WEBP & GIF files are allowed.";
        }
    } else {
        $_SESSION['error'] = "Please select a valid image file.";
    }

    // Only proceed with database insert if upload was successful or no file was uploaded
    if ($upload_success || empty($_FILES['image']['name'])) {
        $sql = "INSERT INTO blogs
                (title, content, excerpt, slug_url, image, author, category, tags, meta_title, meta_description, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "sssssssssss",
            $title,
            $content,
            $excerpt,
            $slug_url,
            $image_name,
            $author,
            $category,
            $tags,
            $meta_title,
            $meta_description,
            $status
        );

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Blog added successfully!";
            header("Location: view-all-blog.php");
            exit();
        } else {
            error_log('[admin/add-blog] DB error: ' . mysqli_error($conn));
            $_SESSION['error'] = "Could not save the blog post. Please try again.";
        }
    }

    header("Location: add-blog.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add New Blog | REJUVENATE Digital Health</title>

    <?php include "links.php"; ?>

    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
    <!-- Select2 for better dropdowns -->
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
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="text-center">Add New Blog</h2>
                                    </div>
                                </div>
                            </div>

                            <div class="white_card_body">
                                <!-- Display error/success messages -->
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                                <?php endif; ?>

                                <div class="col-md-12 mb-4">
                                    <a href="view-all-blog.php" class="btn btn-danger">
                                        <i class="fas fa-list"></i> View All Blogs
                                    </a>
                                </div>

                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" class="p-4 shadow bg-white">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label class="form-label">Title:</label>
                                                    <input type="text" name="title" class="form-control" required
                                                           value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Excerpt <span class="text-muted small">(short summary shown on blog cards — optional, auto-generated from content if left blank)</span>:</label>
                                                    <textarea name="excerpt" class="form-control" rows="2" maxlength="300"><?= isset($_POST['excerpt']) ? htmlspecialchars($_POST['excerpt']) : '' ?></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Content:</label>
                                                    <textarea name="content" class="form-control" rows="10" id="editor" required>
                                                        <?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?>
                                                    </textarea>
                                                </div>

                                                <hr>
                                                <h6 class="mb-3">SEO (optional — falls back to Title / Excerpt if left blank)</h6>
                                                <div class="mb-3">
                                                    <label class="form-label">Meta Title:</label>
                                                    <input type="text" name="meta_title" class="form-control" maxlength="255"
                                                           value="<?= isset($_POST['meta_title']) ? htmlspecialchars($_POST['meta_title']) : '' ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Meta Description:</label>
                                                    <textarea name="meta_description" class="form-control" rows="2" maxlength="300"><?= isset($_POST['meta_description']) ? htmlspecialchars($_POST['meta_description']) : '' ?></textarea>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Author:</label>
                                                    <input type="text" name="author" class="form-control" required
                                                           value="<?= isset($_POST['author']) ? htmlspecialchars($_POST['author']) : '' ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Category:</label>
                                                    <input type="text" name="category" class="form-control" placeholder="e.g. Wellness, Telemedicine"
                                                           value="<?= isset($_POST['category']) ? htmlspecialchars($_POST['category']) : '' ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Tags <span class="text-muted small">(comma separated)</span>:</label>
                                                    <input type="text" name="tags" class="form-control" placeholder="e.g. diabetes, diet, prevention"
                                                           value="<?= isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : '' ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Status:</label>
                                                    <select name="status" class="form-control select2" required>
                                                        <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>Draft</option>
                                                        <option value="published" <?= (!isset($_POST['status']) || (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : '') ?>>Published</option>
                                                        <option value="archived" <?= (isset($_POST['status']) && $_POST['status'] == 'archived') ? 'selected' : '' ?>>Archived</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Featured Image:</label>
                                                    <input type="file" name="image" class="form-control" required accept="image/*">
                                                    <small class="text-muted">Max size: 5MB (JPG, PNG, WEBP, GIF)</small>
                                                    <div class="mt-2">
                                                        <img id="imagePreview" src="#" alt="Image preview" style="max-width: 100%; display: none;">
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <button type="submit" class="btn btn-success btn-block">
                                                        <i class="fas fa-plus"></i> Add Blog
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            // Initialize CKEditor
            CKEDITOR.replace('editor', {
                toolbar: [
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', 'Blockquote'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['Image', 'Table'] },
                    { name: 'document', items: ['Source'] }
                ],
                height: 500
            });

            // Initialize Select2
            $(document).ready(function() {
                $('.select2').select2({
                    minimumResultsForSearch: Infinity
                });

                // Image preview
                $('input[type="file"]').change(function(e) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview').attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(this.files[0]);
                });
            });
        </script>
    </section>
</body>
</html>
