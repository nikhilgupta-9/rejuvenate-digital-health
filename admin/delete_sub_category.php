<?php
session_start();
include "db-conn.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Prevent SQL Injection

    // Delete Query
    $sql = "DELETE FROM sub_categories WHERE cate_id = $id";
    $delete = mysqli_query($conn, $sql);

    if ($delete) {
        echo "<script>alert('Sub Category deleted successfully'); window.location.href='view-sub-categories.php';</script>";
    } else {
        echo "<script>alert('Error deleting category'); window.location.href='view-sub-categories.php';</script>";
    }
} else {
    echo "<script>alert('Invalid Request'); window.location.href='view-sub-categories.php';</script>";
}
?>