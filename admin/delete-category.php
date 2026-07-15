<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

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
