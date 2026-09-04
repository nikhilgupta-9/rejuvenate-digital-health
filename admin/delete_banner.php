<?php
require_once __DIR__ . '/auth/bootstrap.php';
ob_start();
error_reporting(E_ALL);
include "db-conn.php";

// Get banner ID from URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Fetch banner path from database
    $sql = "SELECT banner_path FROM banners WHERE id = $id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $banner_path = $row['banner_path'];

        // Delete banner file from uploads directory
        if (file_exists($banner_path)) {
            unlink($banner_path); // Delete the file
        }

        // Delete banner from database
        $delete_sql = "DELETE FROM banners WHERE id = $id";
        if ($conn->query($delete_sql) === TRUE) {
            echo "Banner deleted successfully.";
        } else {
            error_log('[admin/delete_banner] DB error: ' . $conn->error);
            echo "Could not delete the banner. Please try again.";
        }
    } else {
        echo "Banner not found.";
    }
} else {
    echo "Invalid request.";
}

$conn->close();

if (isset($_SERVER['HTTP_REFERER'])) {
    header("Location: add-banner.php");
    exit;
} else {
    echo "No previous page found!";
}
ob_end_flush();

?>
