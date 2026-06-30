<?php

session_start();
include "db-conn.php";

// Your database connection code here
// $conn = mysqli_connect(...);

$pro_get = isset($_GET['delete']) ? intval($_GET['delete']) : 0;

if (isset($_GET['delete'])) {
    $sql_del = "DELETE FROM blogs WHERE id = $pro_get";
    $res_del = mysqli_query($conn, $sql_del);

    if ($res_del) {
        $_SESSION['delete_message'] = [
            'status' => 'success',
            'message' => 'Blog post deleted successfully!'
        ];
    } else {
        $_SESSION['delete_message'] = [
            'status' => 'danger',
            'message' => 'Error deleting blog post: ' . mysqli_error($conn)
        ];
    }

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Sales</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .table-container {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        .table td,
        .table th {
            padding: 12px;
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
    </style>
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

        <div class="container">
            <div class="row">
                
            </div>
        </div>
        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <?php
                // Display the message if it exists
                if (isset($_SESSION['delete_message'])) {
                    $alert_status = $_SESSION['delete_message']['status'];
                    $alert_message = $_SESSION['delete_message']['message'];
                    ?>
                    <div class="container mt-3">
                        <div class="alert alert-<?php echo $alert_status; ?> alert-dismissible fade show" role="alert">
                            <?php echo $alert_message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                    <?php
                    // Clear the message after displaying
                    unset($_SESSION['delete_message']);
                }
                ?>
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0 fw-bold">Blog Management</h2>
                                        <p class="text-muted mb-0 small">Manage your blog posts</p>
                                    </div>
                                    <div>
                                        <a href="add-blog.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add New Blog
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="QA_section">
                                    <div class="QA_table mb_30">
                                        <div class="table-responsive">
                                            <table
                                                class="table table-striped table-bordered text-center lms_table_active">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Date</th>
                                                        <th>Title</th>
                                                        <th>Content</th>
                                                        <th>Image</th>
                                                        <th>Edit</th>
                                                        <th>Delete</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sno = 1;
                                                    $sql = "SELECT * FROM `blogs` ORDER BY `created_at` DESC";
                                                    $result = mysqli_query($conn, $sql);
                                                    while ($row = mysqli_fetch_assoc($result)) {
                                                        ?>
                                                        <tr>
                                                            <td><?= $sno++ ?></td>
                                                            <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($row['title']) ?></td>
                                                            <td><?= substr(htmlspecialchars($row['content']), 0, 120) . '...' ?>
                                                            </td>
                                                            <td><img src="uploads/blogs/<?= $row['image'] ?>"
                                                                    style="max-width:100px;" class="img-thumbnail"></td>
                                                            <td>
                                                                <a href="edit-blog.php?id=<?= $row['id'] ?>"
                                                                    class="btn btn-sm btn-outline-info">
                                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <a href="?delete=<?= $row['id'] ?>"
                                                                    class="btn btn-sm btn-outline-danger"
                                                                    onclick="return confirm('Are you sure you want to delete this blog?')">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12"></div>
                </div>
            </div>
        </div>
    </section>
    <?php include "footer.php"; ?>

</body>

</html>