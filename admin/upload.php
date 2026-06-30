<?php
include "db-conn.php";

// Check if product ID is set
if (!isset($_POST['product_id'])) {
    die("Invalid Product ID.");
}

$product_id = intval($_POST['product_id']); // Ensure it's an integer

// Check if files were uploaded
if (isset($_FILES['productImages1']) && !empty($_FILES['productImages1']['name'][0])) {
    $upload_dir = "assets/img/uploads/"; // Define upload directory

    // Loop through each uploaded file
    foreach ($_FILES['productImages1']['name'] as $key => $file_name) {
        $file_tmp = $_FILES['productImages1']['tmp_name'][$key];
        $file_size = $_FILES['productImages1']['size'][$key];
        $file_error = $_FILES['productImages1']['error'][$key];

        // Validate file upload
        if ($file_error === 0) {
            // Generate unique file name
            $new_file_name = time() . "_" . basename($file_name);
            $upload_path = $upload_dir . $new_file_name;

            // Move file to upload directory
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Save file path in the database
                $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                $stmt->bind_param("is", $product_id, $new_file_name);
                $stmt->execute();
            } else {
                echo "Failed to upload $file_name.<br>";
            }
        } else {
            echo "Error uploading $file_name.<br>";
        }
    }

     // Redirect to a previous URL stored in `$_SERVER['HTTP_REFERER']`
            if (isset($_SERVER['HTTP_REFERER'])) {
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
            } else {
                echo "No previous page found!";
            }
            exit;
} else {
    die("No files were uploaded.");
}
?>
