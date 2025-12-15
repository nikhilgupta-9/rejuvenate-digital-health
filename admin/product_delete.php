<?php
include "db-conn.php";

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // Validate input to prevent SQL injection
    if (!empty($delete_id) && is_numeric($delete_id)) {
        // Prepare and execute the DELETE query
        $sql = "DELETE FROM `products` WHERE `pro_id` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $delete_id);

        if ($stmt->execute()) {
            // Redirect to the products page
            header('Location: show-products.php');
            exit();
        } else {
            echo "Failed to delete product.";
        }

        $stmt->close();
    } else {
        echo "Invalid product ID.";
    }
}

$conn->close();
?>

