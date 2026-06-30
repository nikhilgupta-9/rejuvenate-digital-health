<?php
include "db-conn.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Prevent SQL Injection

    // Delete Query
    $sql = "DELETE FROM categories WHERE cate_id = $id";
    $delete = mysqli_query($conn, $sql);

    if ($delete) {
        echo "<script> window.location.href='view-categories.php';</script>";
    } else {
        echo "<script>window.location.href='view-categories.php';</script>";
    }
} else {
    echo "<script>window.location.href='view-categories.php';</script>";
}
?>
