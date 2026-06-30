<?php
include "db-conn.php";
// Delete brand
if (isset($_GET['deleteId'])) {
    $delete_id = intval($_GET['deleteId']);
    $stmt = $conn->prepare("DELETE FROM brands WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: view_brands.php");
    exit;
}

// Fetch all brands
$result = $conn->query("SELECT * FROM brands ORDER BY created_at DESC");

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

    <?php include "header.php"; ?>
    <section class="main_content dashboard_part large_header_bg">

    <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner ">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <h2>Manage Certificates</h2>
                        <a href="our-best-brand.php" class="btn btn-primary">Add New Certificate</a>
                        <br />
                        <br />

                        <table class="table table-bordered table-hover shadow-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Certificate Name</th>
                                    <th>Logo</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($brand = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($brand['id']); ?></td>
                                        <td><?= htmlspecialchars($brand['brand_name']); ?></td>
                                        <td>
                                            <img src="<?= htmlspecialchars($brand['logo_path']); ?>" alt="Brand Logo"
                                                class="img-thumbnail" style="max-width: 100px;">
                                        </td>
                                        <td>
                                            <a href="?deleteId=<?= $brand['id']; ?>" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#deleteModal<?= $brand['id']; ?>">🗑️ Delete</a>

                                            <!-- Delete Confirmation Modal -->
                                            <div class="modal fade" id="deleteModal<?= $brand['id']; ?>" tabindex="-1"
                                                aria-labelledby="deleteModalLabel<?= $brand['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"
                                                                id="deleteModalLabel<?= $brand['id']; ?>">
                                                                Confirm Deletion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete the brand
                                                            <strong><?= htmlspecialchars($brand['brand_name']); ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <a href="?delete_id=<?= $brand['id']; ?>"
                                                                class="btn btn-danger">Yes, Delete</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- End Modal -->

                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>