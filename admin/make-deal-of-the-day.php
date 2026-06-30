<?php
include "db-conn.php";
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
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <table class="table table-striped">
 <thead>
    <tr>
        <th scope="col">#</th>
        <th scope="col">Product Name</th>
        <th scope="col">Category ID</th>
        <th scope="col">Subcategory</th>
        <th scope="col">MRP</th>
        <th scope="col">Selling Price</th>
        <th scope="col">Deal of the Day</th>
        <th scope="col">Disable</th>
    </tr>
</thead>
<tbody>
    <?php
    $sql = "SELECT * FROM `products`";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $is_deal = $row['is_deal'];
        $is_disabled = $row['is_disabled'];
    ?>
        <tr id="product-<?= $row['pro_id'] ?>">
            <th scope="row"><?= $row['pro_id'] ?></th>
            <td><?= $row['pro_name'] ?></td>
            <td><?= $row['pro_cate'] ?></td>
            <td><?= $row['pro_img'] ?></td>
            <th><?= $row['mrp'] ?></th>
            <td><?= $row['selling_price'] ?></td>
            <!-- Deal of the Day Button -->
            <td class="text-center">
                <button 
                    class="btn btn-sm <?= $is_deal ? 'btn-success' : 'btn-outline-success' ?>" 
                    onclick="toggleDeal(<?= $row['pro_id'] ?>)">
                    <?= $is_deal ? 'Marked as Deal' : 'Mark as Deal' ?>
                </button>
            </td>
            <!-- Disable Button -->
            <td class="text-center">
                <button 
                    class="btn btn-sm <?= $is_disabled ? 'btn-danger' : 'btn-outline-danger' ?>" 
                    onclick="toggleDisable(<?= $row['pro_id'] ?>)">
                    <?= $is_disabled ? 'Disabled' : 'Disable' ?>
                </button>
            </td>
           
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


       <script>
function toggleDeal(productId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_product_status.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.status === "success") {
                // Update button text and style
                var button = document.querySelector(`#product-${productId} td:nth-child(7) button`);
                button.textContent = response.newStatus ? 'Marked as Deal' : 'Mark as Deal';
                button.className = response.newStatus ? 'btn btn-sm btn-success' : 'btn btn-sm btn-outline-success';
            } else {
                alert(response.message);
            }
        }
    };

    xhr.send("action=toggle_deal&pro_id=" + encodeURIComponent(productId));
}

function toggleDisable(productId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_product_status.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.status === "success") {
                // Update button text and style
                var button = document.querySelector(`#product-${productId} td:nth-child(8) button`);
                button.textContent = response.newStatus ? 'Disabled' : 'Disable';
                button.className = response.newStatus ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-outline-danger';
            } else {
                alert(response.message);
            }
        }
    };

    xhr.send("action=toggle_disable&pro_id=" + encodeURIComponent(productId));
}
</script>
