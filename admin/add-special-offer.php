<?php
session_start();
include "db-conn.php"; // Database Connection

// Handle Create & Update Offer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $offer_title = trim($_POST['offer_title']);
    $offer_description = trim($_POST['offer_description']);
    $status = ($_POST['status'] === "Active") ? "Active" : "Inactive"; // Ensure only valid values
    $product_id = !empty($_POST['product_id']) ? $_POST['product_id'] : NULL;

    $offer_image = $_FILES['offer_image']['name'];
    $target = 'uploads/' . basename($offer_image);

    // Check if updating an existing offer
    if (!empty($_POST['offer_id'])) {
        $offer_id = $_POST['offer_id'];

        if (!empty($offer_image)) {
            // Upload Image
            if (move_uploaded_file($_FILES['offer_image']['tmp_name'], $target)) {
                $stmt = $conn->prepare("UPDATE special_offers SET title=?, description=?, image_url=?, status=?, product_id=? WHERE id=?");
                $stmt->bind_param('sssiii', $offer_title, $offer_description, $target, $status, $product_id, $offer_id);
            } else {
                die("Image upload failed!");
            }
        } else {
            // Update without image
            $stmt = $conn->prepare("UPDATE special_offers SET title=?, description=?, status=?, product_id=? WHERE id=?");
            $stmt->bind_param('sssii', $offer_title, $offer_description, $status, $product_id, $offer_id);
        }
        
        if ($stmt->execute()) {
            echo "Special Offer updated successfully!";
        } else {
            echo "Error updating offer: " . $stmt->error;
        }

    } else {
        // Insert new offer
        if (!empty($offer_image) && move_uploaded_file($_FILES['offer_image']['tmp_name'], $target)) {
            $stmt = $conn->prepare("INSERT INTO special_offers (title, description, image_url, status, product_id) VALUES (?, ?, ?, ?, ?)");

            // Handle NULL values properly
            if ($product_id !== NULL) {
                $stmt->bind_param('ssssi', $offer_title, $offer_description, $target, $status, $product_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO special_offers (title, description, image_url, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $offer_title, $offer_description, $target, $status);
            }

            if ($stmt->execute()) {
                echo "Special Offer added successfully!";
            } else {
                echo "Error inserting offer: " . $stmt->error;
            }
        } else {
            die("Failed to upload image or no image provided!");
        }
    }
}

// Handle Delete Offer
if (isset($_GET['delete'])) {
    $offer_id = $_GET['delete'];

    // Fetch image path
    $img_query = $conn->prepare("SELECT image_url FROM special_offers WHERE id=?");
    $img_query->bind_param('i', $offer_id);
    $img_query->execute();
    $img_result = $img_query->get_result();

    if ($row = $img_result->fetch_assoc()) {
        $image_path = $row['image_url'];
        if (file_exists($image_path)) {
            unlink($image_path); // Delete image from server
        }
    }

    // Delete Offer
    $stmt = $conn->prepare("DELETE FROM special_offers WHERE id=?");
    $stmt->bind_param('i', $offer_id);
    if ($stmt->execute()) {
        echo "Offer deleted successfully!";
    } else {
        echo "Failed to delete offer: " . $stmt->error;
    }
}

// Fetch Offers
$offers = $conn->query("SELECT special_offers.*, products.pro_name 
                        FROM special_offers 
                        LEFT JOIN products ON special_offers.product_id = products.id 
                        ORDER BY special_offers.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Special Offers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= include "links.php"; ?>
</head>
<body class="crm_body_bg">
    <?= include "header.php"; ?>

    <section class="main_content dashboard_part large_header_bg">
        <div class="container mt-5">
            <h2 class="text-center">Manage Special Offers</h2>

            <!-- Add / Edit Offer Form -->
            <form action="" method="post" enctype="multipart/form-data" class="mb-5">
                <input type="hidden" name="offer_id" id="offer_id">
                <div class="row">
                    <div class="col-md-6">
                        <label>Offer Title:</label>
                        <input type="text" name="offer_title" id="offer_title" required class="form-control"><br>
                    </div>
                    <div class="col-md-6">
                        <label>Offer Description:</label>
                        <textarea name="offer_description" id="offer_description" required class="form-control"></textarea><br>
                    </div>
                    <div class="col-md-6">
                        <label>Offer Image:</label> <small class="text-danger">** image must be 750px X 300px</small>
                        <input type="file" name="offer_image" id="offer_image" class="form-control"><br>
                    </div>
                    <div class="col-md-6">
                        <label>Status:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select><br>
                    </div>
                    <!-- <div class="col-lg-6 col-md-12">
                        <label for="product_id" class="form-label fw-bold">Select Product:</label>
                        <?php $products = $conn->query("SELECT id, pro_name FROM products"); ?>
                        <select name="product_id" id="product_id" class="form-control select2">
                            <option value="">-- Select Product --</option>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <option value="<?= $product['id']; ?>"><?= htmlspecialchars($product['pro_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div> -->

                    <div class="col-md-12 text-center mt-3">
                        <button type="submit" class="btn btn-success">Save Offer</button>
                    </div>
                </div>
            </form>

            <!-- Display Current Offers -->
            <h2 class="text-center">Current Special Offers</h2>
            <div class="row">
                <?php while ($row = $offers->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <img src="<?= $row['image_url']; ?>" class="card-img-top" alt="Offer Image">
                            <div class="card-body">
                                <h5 class="card-title"><?= $row['title']; ?></h5>
                                <p class="card-text"><?= $row['description']; ?></p>
                                <p>Status: <b><?= $row['status']; ?></b></p>
                                <button class="btn btn-warning btn-sm" onclick="editOffer(<?= $row['id']; ?>, '<?= $row['title']; ?>', '<?= $row['description']; ?>', '<?= $row['status']; ?>')">Edit</button>
                                <a href="?delete=<?= $row['id']; ?>" class="btn btn-danger btn-sm">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <section class="mb-0" style="position: fixed; bottom: 0; width: 100%;">
    <?php require "footer.php"; ?>
</section>


    <script>
        function editOffer(id, title, description, status) {
            document.getElementById("offer_id").value = id;
            document.getElementById("offer_title").value = title;
            document.getElementById("offer_description").value = description;
            document.getElementById("status").value = status;
        }
    </script>
</body>
</html>
