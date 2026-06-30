<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db-conn.php";

if (isset($_POST['update-product'])) {
    $pro_id = $_POST['pro_id'];
    $pro_name = $_POST['pro_name'];
    $pro_cate = $_POST['pro_cate'];
    $short_desc = $_POST['short_desc'];
    $description = $_POST['pro_desc'];
    $new_arrival = $_POST['new_arrival'];
    $trending = $_POST['trending'];
    $qty = $_POST['qty'];
    $whole_sale_price = $_POST['whole_sale_selling_price'];
    $mrp = $_POST['mrp'];
    $selling_price = $_POST['selling_price'];
    $stock = $_POST['stock'];
    $status = $_POST['status'];
    $meta_title = $_POST['meta_title'];
    $meta_desc = $_POST['meta_desc'];
    $meta_key = $_POST['meta_key'];
    $added_on = date('Y-m-d H:i:s');

    $target_dir = "assets/img/uploads/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $uploaded_images = [];

    if (isset($_FILES['pro_img']) && !empty($_FILES['pro_img']['name'][0])) {
        foreach ($_FILES['pro_img']['name'] as $key => $filename) {
            $tempname = $_FILES['pro_img']['tmp_name'][$key];
            $uniqueFilename = time() . "_" . rand(1000, 9999) . "_" . basename($filename);
            $target_file = $target_dir . $uniqueFilename;

            if (move_uploaded_file($tempname, $target_file)) {
                $uploaded_images[] = $uniqueFilename;
            } else {
                echo "Failed to upload image: $filename<br>";
            }
        }
    }

    if (!empty($uploaded_images)) {
        $pro_img = implode(',', $uploaded_images);
        $img_query = ", `pro_img` = ?";
    } else {
        $pro_img = null;
        $img_query = "";
    }

    $query = "UPDATE `products` SET `pro_name` = ?, `pro_cate` = ?, `short_desc` = ?, `description` = ?,
              `new_arrival` = ?, `trending` = ?, `qty` = ?, `mrp` = ?, `selling_price` = ?,
              `whole_sale_selling_price` = ?, `stock` = ?, `status` = ?,
              `meta_title` = ?, `meta_desc` = ?, `meta_key` = ?, `added_on` = ? $img_query
              WHERE `pro_id` = ?";

    $stmt = mysqli_prepare($conn, $query);

    if ($pro_img) {
        mysqli_stmt_bind_param($stmt, "sssssssssssssssssi", $pro_name, $pro_cate, $short_desc, $description, $new_arrival, $trending, $qty, $mrp, $selling_price, $whole_sale_price, $stock, $status, $meta_title, $meta_desc, $meta_key, $added_on, $pro_img, $pro_id);
    } else {
        mysqli_stmt_bind_param($stmt, "ssssssssssssssssi", $pro_name, $pro_cate, $short_desc, $description, $new_arrival, $trending, $qty, $mrp, $selling_price, $whole_sale_price, $stock, $status, $meta_title, $meta_desc, $meta_key, $added_on, $pro_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        echo "<script type='text/javascript'>
                alert('Product updated successfully.');
                window.location.href = 'show-products.php';
              </script>";
        exit;
    } else {
        echo "Error updating product: " . mysqli_error($conn);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
// echo "<pre>";
// print_r($_POST);
// echo "</pre>";
?>