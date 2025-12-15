<?php
// Include database connection
include 'db-conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_category'])) {
    // Get form data
    $cat_id = intval($_POST['cat_id']);
    $cate_name = trim($_POST['cate_name']);
    $slug_url = trim($_POST['slug_url']);
    $status = ($_POST['status'] == 'Active') ? 1 : 0;

    // Fetch the existing category to get the old image
    $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();

    if (!$category) {
        die("Category not found.");
    }

    $image_name = $category['image']; // Keep old image by default

    // Image upload handling
    if (!empty($_FILES['imageUpload']['name'])) {
        $upload_dir = "uploads/category/";
        $image_name = time() . "_" . basename($_FILES["imageUpload"]["name"]);
        $target_file = $upload_dir . $image_name;

        // Validate image type
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['imageUpload']['type'], $allowed_types)) {
            die("Invalid image format. Allowed: JPG, PNG, WEBP.");
        }

        // Move uploaded file
        if (move_uploaded_file($_FILES["imageUpload"]["tmp_name"], $target_file)) {
            // Delete old image if it exists
            if (!empty($category['image']) && file_exists($upload_dir . $category['image'])) {
                unlink($upload_dir . $category['image']);
            }
        } else {
            die("Image upload failed.");
        }
    }

    // Update query
    $update_query = "UPDATE categories SET categories = ?, slug_url = ?, status = ?, image = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssisi", $cate_name, $slug_url, $status, $image_name, $cat_id);

    if ($stmt->execute()) {
        echo "<script>alert('Category updated successfully!'); window.location.href='view-categories.php';</script>";
    } else {
        echo "Error updating category: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request.";
}
?>
