<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

// Initialize variables
$error = '';
$success = '';
$review = null;

// Get review ID from URL
$review_id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) : 0;

// Fetch review data
if ($review_id) {
    $stmt = $conn->prepare("SELECT * FROM product_reviews WHERE review_id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $review = $result->fetch_assoc();
    $stmt->close();

    if (!$review) {
        $error = "Review not found.";
    }
} else {
    $error = "Invalid review ID.";
}

// Handle review update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_review'])) {
    // Sanitize and validate input
    $rating = filter_input(INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT);
    $review_message = filter_input(INPUT_POST, 'review_message', FILTER_SANITIZE_STRING);
    $reviewer_name = filter_input(INPUT_POST, 'reviewer_name', FILTER_SANITIZE_STRING);
    $reviewer_email = filter_input(INPUT_POST, 'reviewer_email', FILTER_SANITIZE_EMAIL);
    $image_path = $review['reviewer_img']; // Keep existing image unless changed

    // Validate required fields
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } else if (empty($review_message)) {
        $error = "Review message cannot be empty.";
    }

    // Handle image upload if no errors so far
    if (empty($error)) {
        if (isset($_FILES['reviewer_img']) && $_FILES['reviewer_img']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/reviews/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_name = basename($_FILES["reviewer_img"]["name"]);
            $target_file = $target_dir . uniqid() . '_' . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            $check = getimagesize($_FILES["reviewer_img"]["tmp_name"]);
            if ($check === false) {
                $error = "File is not a valid image.";
            }

            if ($_FILES["reviewer_img"]["size"] > 2000000) {
                $error = "Sorry, your file is too large (max 2MB).";
            }

            $allowed_types = ["jpg", "png", "jpeg", "gif"];
            if (!in_array($imageFileType, $allowed_types)) {
                $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            }

            if (empty($error)) {
                // Delete old image if it exists
                if (!empty($image_path) && file_exists($image_path)) {
                    unlink($image_path);
                }

                if (move_uploaded_file($_FILES["reviewer_img"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            }
        }

        // Update database if no errors
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE product_reviews 
                                   SET rating = ?, review_message = ?, reviewer_name = ?, 
                                       reviewer_email = ?, reviewer_img = ?
                                   WHERE review_id = ?");
            $stmt->bind_param("issssi", $rating, $review_message, $reviewer_name, 
                              $reviewer_email, $image_path, $review_id);

            if ($stmt->execute()) {
                $success = "Review updated successfully!";
                // Refresh review data
                $stmt = $conn->prepare("SELECT * FROM product_reviews WHERE review_id = ?");
                $stmt->bind_param("i", $review_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $review = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Sorry, there was an error updating the review: " . $conn->error;
            }
        }
    }
}

// Fetch products for dropdown (if needed elsewhere)
$products_query = "SELECT * FROM products";
$products_result = mysqli_query($conn, $products_query);

// Optional: Display messages
if (!empty($error)) {
    echo "<div style='color: red;'>$error</div>";
}

if (!empty($success)) {
    echo "<div style='color: green;'>$success</div>";
}
?>


<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Review</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .rating-stars {
            color: #ffc107;
            font-size: 1.5em;
        }
        .form-container {
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .white_card_body {
            padding: 20px;
        }
        .rating-input {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .rating-input label {
            cursor: pointer;
            font-size: 1.5em;
        }
        .rating-input input[type="radio"] {
            display: none;
        }/* Star Rating Edit Styles */
.star-rating-edit {
    display: inline-block;
    font-size: 0; /* Remove whitespace between inline-block elements */
    unicode-bidi: bidi-override;
    direction: rtl; /* Right-to-left for proper hover highlighting */
}

.star-rating-edit input {
    display: none; /* Hide the radio buttons */
}

.star-rating-edit label {
    display: inline-block;
    font-size: 28px;
    padding: 0 3px;
    cursor: pointer;
    color: #ccc; /* Default empty star color */
    position: relative;
    transition: all 0.2s ease;
}

.star-rating-edit label:hover,
.star-rating-edit label:hover ~ label,
.star-rating-edit input:checked ~ label {
    color: #ffc107; /* Gold color for selected/hovered stars */
    transform: scale(1.1);
}

.star-rating-edit input:checked + label {
    color: #ffc107; /* Ensure the clicked star gets the color */
    animation: pulse 0.5s ease;
}

/* Small pulse animation when selecting */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1.1); }
}

/* Tooltip style for better UX */
.star-rating-edit label .star-icon {
    position: relative;
}

.star-rating-edit label .star-icon:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 14px;
    white-space: nowrap;
    z-index: 10;
    margin-bottom: 8px;
}

.star-rating-edit label .star-icon:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
    margin-bottom: 3px;
    z-index: 11;
}
        .current-image {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        @media (max-width: 767px) {
            .white_card_body {
                padding: 15px;
            }
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
        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-10 col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Edit Review</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($review): ?>
                                <div class="form-container">
                                    <form method="post" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="product_id" class="form-label">Product</label>
                                            <select class="form-control" id="product_id" name="product_id" disabled>
                                                <option value="">-- SELECT PRODUCTS --</option>
                                                <?php while($product = mysqli_fetch_assoc($products_result)): ?>
                                                    <option value="<?php echo $product['pro_id']; ?>" 
                                                        <?php echo ($review['product_id'] == $product['pro_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($product['pro_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <!-- Hidden input to submit the product_id (since the select is disabled) -->
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($review['product_id']); ?>">
                                        </div>

                                        
                                       <div class="mb-3">
                                            <label class="form-label">Rating</label>
                                            <div class="star-rating-edit">
                                                <?php 
                                                // Get current rating from the review being edited
                                                $current_rating = isset($review['rating']) ? $review['rating'] : 0;
                                                
                                                // Display 5 stars
                                                for ($i = 1; $i <= 5; $i++): 
                                                ?>
                                                    <input type="radio" id="edit-star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                                        <?php echo ($current_rating == $i) ? 'checked' : ''; ?>>
                                                    <label for="edit-star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i != 1 ? 's' : ''; ?>">
                                                        <span class="star-icon">★</span>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted d-block mt-1">Click to change rating (1 = Poor, 5 = Excellent)</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="review_message" class="form-label">Review Message</label>
                                            <textarea class="form-control" id="review_message" name="review_message" rows="3" required><?php 
                                                echo isset($review['review_message']) ? htmlspecialchars($review['review_message']) : ''; 
                                            ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="reviewer_name" class="form-label">Reviewer Name</label>
                                            <input type="text" class="form-control" id="reviewer_name" name="reviewer_name" 
                                                value="<?php echo isset($review['reviewer_name']) ? htmlspecialchars($review['reviewer_name']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="reviewer_email" class="form-label">Reviewer Email</label>
                                            <input type="email" class="form-control" id="reviewer_email" name="reviewer_email" 
                                                value="<?php echo isset($review['reviewer_email']) ? htmlspecialchars($review['reviewer_email']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="reviewer_img" class="form-label">Reviewer Image (Optional)</label>
                                            <?php if (!empty($review['reviewver_img'])): ?>
                                                <div>
                                                    <p>Current Image:</p>
                                                    <img src="<?php echo htmlspecialchars($review['reviewver_img']); ?>" class="current-image">
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                                        <label class="form-check-label" for="remove_image">
                                                            Remove current image
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" class="form-control mt-2" id="reviewer_img" name="reviewer_img">
                                            <small class="text-muted">Max size: 2MB (JPG, PNG, GIF). Leave empty to keep current image.</small>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" name="update_review" class="btn btn-primary">Update Review</button>
                                            <a href="show-products-review.php" class="btn btn-secondary">Back to Reviews</a>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
   </section>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
       integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
       crossorigin="anonymous"></script>
</body>
</html>