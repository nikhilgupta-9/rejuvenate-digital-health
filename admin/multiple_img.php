<?php
include "db-conn.php";

$_GET['id'];
$product_id = $_GET['id'];

$sql = "SELECT * FROM `products` where `pro_id` = '$product_id'";
$res = mysqli_query($conn, $sql);
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $id = $row['id'];
} else {
    die("Product does not exist.");
}

if(isset($_POST['delete_btn'])){
    // Get image ID safely
    $image_id = intval($_POST['image_id']); // Ensures it's an integer

    // Prepared Statement to prevent SQL Injection
    $stmt = $conn->prepare("DELETE FROM `product_images` WHERE `id` = ?");
    $stmt->bind_param("i", $image_id);  // "i" means integer binding
    $res2 = $stmt->execute();

    if($res2){
        echo "<script>alert('Image Deleted!!');</script>";
    } else {
        echo "<script>alert('Image is not deleted');</script>";
    }

    // Close statement
    $stmt->close();
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if files were uploaded
    // if(isset($_FILES['productImages1'])){
    //   $file_name = $_FILES['productImages1']['name'];
    //   $file_size = $_FILES['productImages1']['size'];
    //   $file_tmp = $_FILES['productImages1']['tmp_name'];
    //   $file_type = $_FILES['productImages1']['type'];

    //   if(move_uploaded_file($file_tmp, "assets/img/uploads/".$file_name)){
    //     $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
    //     $stmt->execute([$id, $file_name]);

//         echo "Image uploaded and saved successfully!";
//       }
        
//     } else {
//         die("No files were uploaded.");
//     }
// }
?>
<!DOCTYPE html>
<html lang="zxx">

<!-- Mirrored from demo.dashboardpack.com/sales-html/themefy_icon.html by HTTrack Website Copier/3.x [XR&CO'2014], Sun, 16 Apr 2023 14:08:14 GMT -->

<head>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Sales</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
</head>

<body class="crm_body_bg">

<?php  include "header.php"; ?>
    <section class="main_content dashboard_part large_header_bg">

        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0 ">
                    <div class="header_iner d-flex justify-content-between align-items-center">
                        <div class="sidebar_icon d-lg-none">
                            <i class="ti-menu"></i>
                        </div>
                        <div class="serach_field-area d-flex align-items-center">
                            <div class="search_inner">
                                <form action="#">
                                    <div class="search_field">
                                        <input type="text" placeholder="Search here...">
                                    </div>
                                    <button type="submit"> <img src="assets/img/icon/icon_search.svg" alt> </button>
                                </form>
                            </div>
                            <span class="f_s_14 f_w_400 ml_25 white_text text_white">Apps</span>
                        </div>
                        <div class="header_right d-flex justify-content-between align-items-center">
                            <div class="header_notification_warp d-flex align-items-center">
                                <li>
                                    <a class="bell_notification_clicker nav-link-notify" href="#"> <img src="assets/img/icon/bell.svg" alt>
                                    </a>

                                    <div class="Menu_NOtification_Wrap">
                                        <div class="notification_Header">
                                            <h4>Notifications</h4>
                                        </div>
                                        <div class="Notification_body">

                                            <div class="single_notify d-flex align-items-center">
                                                <div class="notify_thumb">
                                                    <a href="#"><img src="assets/img/staf/2.png" alt></a>
                                                </div>
                                                <div class="notify_content">
                                                    <a href="#">
                                                        <h5>Cool Marketing </h5>
                                                    </a>
                                                    <p>Lorem ipsum dolor sit amet</p>
                                                </div>
                                            </div>

                                            <div class="single_notify d-flex align-items-center">
                                                <div class="notify_thumb">
                                                    <a href="#"><img src="assets/img/staf/4.png" alt></a>
                                                </div>
                                                <div class="notify_content">
                                                    <a href="#">
                                                        <h5>Awesome packages</h5>
                                                    </a>
                                                    <p>Lorem ipsum dolor sit amet</p>
                                                </div>
                                            </div>

                                            <div class="single_notify d-flex align-items-center">
                                                <div class="notify_thumb">
                                                    <a href="#"><img src="assets/img/staf/3.png" alt></a>
                                                </div>
                                                <div class="notify_content">
                                                    <a href="#">
                                                        <h5>what a packages</h5>
                                                    </a>
                                                    <p>Lorem ipsum dolor sit amet</p>
                                                </div>
                                            </div>

                                            <div class="single_notify d-flex align-items-center">
                                                <div class="notify_thumb">
                                                    <a href="#"><img src="assets/img/staf/2.png" alt></a>
                                                </div>
                                                <div class="notify_content">
                                                    <a href="#">
                                                        <h5>Cool Marketing </h5>
                                                    </a>
                                                    <p>Lorem ipsum dolor sit amet</p>
                                                </div>
                                            </div>

                                            <div class="single_notify d-flex align-items-center">
                                                <div class="notify_thumb">
                                                    <a href="#"><img src="assets/img/staf/4.png" alt></a>
                                                </div>
                                                <div class="notify_content">
                                                    <a href="#">
                                                        <h5>Awesome packages</h5>
                                                    </a>
                                                    <p>Lorem ipsum dolor sit amet</p>
                                                </div>
                                            </div>

                                            <div class="single_notify d-flex align-items-center">
                                                <div class="notify_thumb">
                                                    <a href="#"><img src="assets/img/staf/3.png" alt></a>
                                                </div>
                                                <div class="notify_content">
                                                    <a href="#">
                                                        <h5>what a packages</h5>
                                                    </a>
                                                    <p>Lorem ipsum dolor sit amet</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="nofity_footer">
                                            <div class="submit_button text-center pt_20">
                                                <a href="#" class="btn_1">See More</a>
                                            </div>
                                        </div>
                                    </div>

                                </li>
                                <li>
                                    <a class="CHATBOX_open nav-link-notify" href="#"> <img src="assets/img/icon/msg.svg" alt> </a>
                                </li>
                            </div>
                            <div class="profile_info">
                                <img src="assets/img/client_img.png" alt="#">
                                <div class="profile_info_iner">
                                    <div class="profile_author_name">
                                        <p>Neurologist </p>
                                        <h5>Dr. Robar Smith</h5>
                                    </div>
                                    <div class="profile_info_details">
                                        <a href="#">My Profile </a>
                                        <a href="#">Settings</a>
                                        <a href="#">Log Out </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="main_content_iner ">
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h3 class="m-0">Category Data</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="QA_section">
                                    <div class="white_box_tittle list_header">
                                        <div class="box_right d-flex lms_block">
                                            <!-- <div class="serach_field_2">
                                                <div class="search_inner">
                                                    <form active="#">
                                                        <div class="search_field">
                                                            <input type="text" placeholder="Search content here...">
                                                        </div>
                                                        <button type="submit"> <i class="ti-search"></i> </button>
                                                    </form>
                                                </div>
                                            </div> -->
                                            <div class="add_button ms-2">
                                                <a href="show-products.php" data-bs-toggle="modal" data-bs-target="#addcategory"
                                                    class="btn_1">Back to Product</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="QA_table mb_30">

                                    <form action="upload.php" method="POST" enctype="multipart/form-data">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="productImages">Upload Product Images (Max 5)</label>
                                        
                                        <!-- FIXED: Correct Hidden Input -->
                                        <input type="hidden" name="product_id" value="<?=$id?>">

                                        <!-- FIXED: Use name="productImages1[]" for multiple uploads -->
                                        <input type="file" class="form-control" name="productImages1[]" id="productImages" multiple accept="image/*" />
                                        
                                        <small>You can upload up to 4 images.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Upload Images</button>
                                </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <h2>Uploaded Images</h2>
                        <?php
                        // Fetch product images from the database
                       // Fetch product images from the database
                            $product_id = 1; // Replace with the actual product ID
                            $query = "SELECT * FROM product_images WHERE product_id = $id";
                            $result = mysqli_query($conn, $query);

                           // Display the images with delete button and full-screen option
                             $sno = 1;
                            if ($result && mysqli_num_rows($result) > 0) {
                               
                                   ?>
                                   <table class="table table-striped">
                                        <thead>
                                            <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Image </th>
                                            <th scope="col">Delete</th>
                                            <th scope="col">Handle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                             while ($row = mysqli_fetch_assoc($result)) {
                                                $image_id = $row['id'];
                                                $image_path = htmlspecialchars($row['image_path']);
                                            ?>
                                            <tr>
                                            <th scope="row"><?=$sno++;?></th>
                                            <td>
                                            <img src="assets/img/uploads/<?=$image_path ?>" style="height: 80px; width: 80px; cursor: pointer;">
                                            </td>
                                            <td>
                                            <form action="" method="post">
                                                <input type="hidden" name="image_id" value="<?=$image_id?>">
                                                <button class="btn btn-danger" type="submit" name="delete_btn">Delete</button>
                                            </form>
                                            </td>
                                            <td>@mdo</td>
                                            </tr>
                                            <?php   } ?>
                                        </tbody>        
                                   </table>
                                   
                              <?php
                            } else {
                                echo "No images found.";
                            }

                            // Close the connection
                            mysqli_close($conn);
                        ?>
                    </div>
                </div>
            </div>
        </div>

       <?php  include "footer.php"; ?>

       <?php

       ?>
   

 