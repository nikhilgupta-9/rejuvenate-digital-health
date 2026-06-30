<?php
session_start();
include "functions.php";
?>
<!DOCTYPE html>
<html lang="zxx">
<head>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Sub Category Management | Admin Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
</head>

<body class="crm_body_bg">

<?php include "header.php"; ?>
<section class="main_content dashboard_part large_header_bg">

    <div class="container-fluid g-0">
        <div class="row">
            <div class="col-lg-12 p-0">
                <?php include "top_nav.php"; ?>
            </div>
        </div>
    </div>

    <div class="main_content_iner">
        <div class="container-fluid p-0">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="white_card card_height_100 mb_30">
                        <div class="card-header bg-white border-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0 fw-bold">Sub Category Management</h2>
                                    <p class="text-muted mb-0 small">Manage your product subcategories</p>
                                </div>
                                <div>
                                   <a href="add-sub-category.php" class="btn_1">
                                            <i class="fas fa-plus me-2"></i>Add New Sub Department
                                        </a>
                                </div>
                            </div>
                        </div>
                        <div class="white_card_body">
                            <div class="QA_section">
                                <div class="QA_table mb_30">
                                    <table class="table lms_table_active">
                                        <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Sub Category ID</th>
                                                <th scope="col">Sub Category Name</th>
                                                <th scope="col">Parent Category</th>
                                                <th scope="col">Slug URL</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Added On</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php echo get_Sub_Category(); ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-12"></div> -->
            </div>
        </div>
    </div>
</section>
<?php include "footer.php"; ?>


