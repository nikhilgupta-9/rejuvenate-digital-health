<?php
include "db-conn.php";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $brand_name = trim($_POST['brand_name']);
    $errors = [];

    // Validate inputs
    if (empty($brand_name)) {
        $errors[] = "Brand name is required.";
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed_types = ['image/png', 'image/jpeg', 'image/webp'];
        $file_type = $_FILES['logo']['type'];

        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only PNG, JPEG, and WEBP formats are allowed.";
        }

        $upload_dir = "uploads/brands/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES['logo']['name']);
        $logo_path = $upload_dir . $file_name;

        if (empty($errors) && move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
            // Insert brand into the database
            $stmt = $conn->prepare("INSERT INTO brands (brand_name, logo_path) VALUES (?, ?)");
            $stmt->bind_param("ss", $brand_name, $logo_path);
            if ($stmt->execute()) {
                $success_message = "Brand added successfully!";
            } else {
                $errors[] = "Error occurred while adding the brand.";
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to upload logo.";
        }
    } else {
        $errors[] = "Brand logo is required.";
    }

    $conn->close();
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
                        <h2 class="mb-4 text-center">Add New Certificates</h2>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
                        <?php endif; ?>

                        <form action="" method="POST" enctype="multipart/form-data"
                            class="shadow-sm p-4 bg-white rounded">
                            <!-- Brand Name Input -->
                            <div class="mb-3">
                                <label for="brand_name" class="form-label">Certificate Name:</label>
                                <input type="text" name="brand_name" id="brand_name" class="form-control" required>
                            </div>

                            <!-- Logo Input -->
                            <div class="mb-3">
                                <label for="logo" class="form-label">Certificate (PNG, JPEG, WEBP):</label>
                                <input type="file" name="logo" id="logo" class="form-control"
                                    accept="image/png, image/jpeg, image/webp" required>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-primary">➕ Add Certificate</button>
                            <a href="view_brands.php" class="btn btn-secondary">📊 View All Certificates</a>
                        </form>

                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>