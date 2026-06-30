<?php
session_start();
include('db-conn.php'); // Include your database connection

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
    $product_id = $_POST['product_id'];

    // Query to get the current deal status
    $query = "SELECT deal_of_the_day FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($current_deal_status);
    $stmt->fetch();
    $stmt->close();

    // Toggle the deal status (TRUE becomes FALSE, FALSE becomes TRUE)
    $new_deal_status = !$current_deal_status;

    // Update the deal_of_the_day column in the database
    $update_query = "UPDATE products SET deal_of_the_day = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $new_deal_status, $product_id);

    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Deal status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating deal status.";
    }

    $update_stmt->close();
}
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


        <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo $_SESSION['success']; ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php elseif (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error']; ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
        <div class="main_content_iner ">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                    <table class="table table-striped">
  <thead>
    <tr>
      <th scope="col">#</th>
      <th scope="col">Product Name</th>
      <th scope="col">Category ID</th>
      <th scope="col">Image</th>
      <th scope="col">MRP</th>
      <th scope="col">Selling Price</th>
      <th scope="col text-center">Deal of the day</th>
      <th scope="col text-center">Delete</th>
    </tr>
  </thead>
  <tbody>
  	<?php
     $sql = "SELECT * FROM `products`";
     $result = mysqli_query($conn, $sql);
     while($row = mysqli_fetch_assoc($result)){
  	?>
    <tr>
      <th scope="row"><?=$row['pro_id']?></th>
      <td><?=$row['pro_name']?></td>
      <td><?=$row['pro_cate']?></td>
      <td><img src="assets/img/uploads/<?=$row['pro_img']?>" alt="" style="height:100px; width:100px;"></td>
      <th><?=$row['mrp']?></th>
      <td><?=$row['selling_price']?></td>
      <td>
                    <?php echo $row['deal_of_the_day'] ? 'Yes' : 'No'; ?>
                </td>
                <td>
                    <!-- Toggle button to change deal_of_the_day status -->
                    <form action="" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-toggle bt-primary" name="toggleBtn">
                            <?php echo $row['deal_of_the_day'] ? 'Remove Deal' : 'Set as Deal of the Day'; ?>
                        </button>
                    </form>
                </td>      
                <!-- <form action="" method="post">
      <td class=""><a href="product_delete.php?delete=<?=$row['pro_id']?>"><i class="fa-solid fa-trash text-danger fs-3"></i></a></td>
      </form> -->
    </tr>
   <?php
}
   ?>
  </tbody>
</table>
                    </div>
                    
                </div>
            </div>
        </div>

       <?php  include "footer.php"; ?>

       <?php
if (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
    $product_id = $_POST['product_id'];

    // Query to get the current deal status
    $query = "SELECT deal_of_the_day FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($current_deal_status);
    $stmt->fetch();
    $stmt->close();

    // Toggle the deal status (TRUE becomes FALSE, FALSE becomes TRUE)
    $new_deal_status = !$current_deal_status;

    // Update the deal_of_the_day column in the database
    $update_query = "UPDATE products SET deal_of_the_day = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $new_deal_status, $product_id);

    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Deal status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating deal status.";
    }

    $update_stmt->close();
} else {
    $_SESSION['error'] = "Invalid product ID.";
}

       ?>