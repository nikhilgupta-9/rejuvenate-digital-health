<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

if (isset($_GET['delete_id'])) {
    $review_id = (int) $_GET['delete_id']; // Sanitize to ensure it's an integer
    $sql_del = "DELETE FROM `product_reviews` WHERE `review_id` = $review_id";
    $res = mysqli_query($conn, $sql_del);

    if ($res) {
        $deleted = "Review deleted successfully.";
        // Optional: Redirect back to the list page
        // header("Location: reviews_list.php");
        // exit;
    } else {
        echo "Error deleting review: " . mysqli_error($conn);
    }
}


// Initialize variables
$error = '';
$success = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    // Sanitize and validate input
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT);
    $review_message = filter_input(INPUT_POST, 'review_message', FILTER_SANITIZE_STRING);
    $reviewer_name = filter_input(INPUT_POST, 'reviewer_name', FILTER_SANITIZE_STRING);
    $reviewer_email = filter_input(INPUT_POST, 'reviewer_email', FILTER_SANITIZE_EMAIL);
    $image_path = '';

    // Validate required fields
    if (empty($product_id)) {
        $error = "Please select a product.";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } elseif (empty($review_message) || empty($reviewer_name) || empty($reviewer_email)) {
        $error = "Please fill in all required fields.";
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

            // Check if image file is a actual image
            $check = getimagesize($_FILES["reviewer_img"]["tmp_name"]);
            if ($check === false) {
                $error = "File is not a valid image.";
            }

            // Check file size (max 2MB)
            if ($_FILES["reviewer_img"]["size"] > 2000000) {
                $error = "Sorry, your file is too large (max 2MB).";
            }

            // Allow certain file formats
            $allowed_types = ["jpg", "png", "jpeg", "gif"];
            if (!in_array($imageFileType, $allowed_types)) {
                $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            }

            if (empty($error)) {
                if (move_uploaded_file($_FILES["reviewer_img"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            }
        }
        // Image is not required, so we proceed even if no image was uploaded

        // Insert into database if no errors
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO product_reviews (product_id, rating, review_message, reviewer_name, reviewer_email, reviewver_img, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iissss", $product_id, $rating, $review_message, $reviewer_name, $reviewer_email, $image_path);

            if ($stmt->execute()) {
                $success = "Thank you for your review!";
                // Clear form if needed
                $_POST = array();
            } else {
                $error = "Sorry, there was an error submitting your review: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch existing reviews for display
$reviews_query = "SELECT * FROM product_reviews ORDER BY created_at DESC";
$reviews_result = mysqli_query($conn, $reviews_query);
?>


<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Product Reviews</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .rating-stars {
            color: #ffc107;
            font-size: 1.5em;
        }
        .review-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .review-header {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
        }
        @media (min-width: 768px) {
            .review-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        .reviewer-info {
            margin-bottom: 5px;
        }
        @media (min-width: 768px) {
            .reviewer-info {
                margin-bottom: 0;
            }
        }
        .reviewer-name {
            font-weight: bold;
            margin-right: 10px;
        }
        .review-date {
            color: #777;
            font-size: 0.9em;
        }
       .star-rating {
    display: flex;
    flex-direction: row-reverse; /* This makes the stars highlight properly on hover */
    justify-content: flex-end; /* Align to the left */
    gap: 5px;
    font-size: 1.5em;
}

.star-rating input {
    display: none; /* Hide the radio buttons */
}

.star-rating label {
    color: #ddd; /* Default empty star color */
    cursor: pointer;
    transition: color 0.2s;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffc107; /* Gold color for selected stars */
}

.star-rating input:checked + label {
    color: #ffc107; /* Ensure the clicked star gets the color */
}

/* Optional: Add animation for hover */
.star-rating label:hover {
    transform: scale(1.2);
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
        @media (max-width: 767px) {
            .white_card_body {
                padding: 15px;
            }
            .review-card {
                padding: 10px;
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
                                        <h2 class="m-0">Product Reviews Management</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                               
                                
                                <div class="form-container">
                                    <h4>Submit Your Review</h4>
                                    <!-- Display error/success messages -->
                                    <?php if (!empty($error)): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>
                                    <!-- Display error/success messages -->
                                    <?php if (!empty($deleted)): ?>
                                        <div class="alert alert-danger"><?php echo $deleted; ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($success)): ?>
                                        <div class="alert alert-success"><?php echo $success; ?></div>
                                    <?php endif; ?>
                                    
                                    <form method="post" enctype="multipart/form-data">
                                        <!-- Remove the action attribute to process in the same file -->
                                        <div class="mb-3">
                                            <label for="product_id" class="form-label">Product</label>
                                            <select class="form-control" id="product_id" name="product_id" required>
                                                <option value="">-- SELECT PRODUCTS --</option>
                                                <?php
                                                $sql_pro = "SELECT * FROM products";
                                                $res_pro = mysqli_query($conn, $sql_pro);
                                                while($row_pro = mysqli_fetch_assoc($res_pro)){
                                                    $selected = (isset($_POST['product_id']) && $_POST['product_id'] == $row_pro['pro_id']) ? 'selected' : '';
                                                    echo '<option value="'.$row_pro['pro_id'].'" '.$selected.'>'.$row_pro['pro_name'].'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Rating</label>
                                            <div class="star-rating">
                                                <?php 
                                                // Get current rating (from POST or from existing review)
                                                $current_rating = isset($_POST['rating']) ? $_POST['rating'] : (isset($review['rating']) ? $review['rating'] : 0);
                                                
                                                // Display stars from 5 to 1
                                                for ($i = 5; $i >= 1; $i--): 
                                                ?>
                                                    <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                                        <?php echo ($current_rating == $i) ? 'checked' : ''; ?>
                                                        <?php echo ($i == 5 && empty($current_rating)) ? 'required' : ''; ?>>
                                                    <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> stars">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted">Click to rate from 1 (worst) to 5 (best)</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="review_message" class="form-label">Your Review</label>
                                            <textarea class="form-control" id="review_message" name="review_message" rows="3" required><?php echo isset($_POST['review_message']) ? htmlspecialchars($_POST['review_message']) : ''; ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="reviewer_name" class="form-label">Your Name</label>
                                            <input type="text" class="form-control" id="reviewer_name" name="reviewer_name" 
                                                value="<?php echo isset($_POST['reviewer_name']) ? htmlspecialchars($_POST['reviewer_name']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="reviewer_email" class="form-label">Your Email</label>
                                            <input type="email" class="form-control" id="reviewer_email" name="reviewer_email" 
                                                value="<?php echo isset($_POST['reviewer_email']) ? htmlspecialchars($_POST['reviewer_email']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="reviewer_img" class="form-label">Image (Optional)</label>
                                            <input type="file" class="form-control" id="reviewer_img" name="reviewer_img">
                                            <small class="text-muted">Max size: 2MB (JPG, PNG, GIF)</small>
                                        </div>
                                        
                                        <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                    </form>
                                </div>
                                
                                <h4>Customer Reviews</h4>
                                <?php if (mysqli_num_rows($reviews_result) > 0): ?>
                                    <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                                        <div class="review-card">
                                            <div class="review-header">
                                                <div class="reviewer-info">
                                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                                                    <span class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></span>
                                                </div>
                                                <div class="rating-container">
                                                    <div class="rating-stars">
                                                        <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <p><?php echo nl2br(htmlspecialchars($review['review_message'])); ?></p>
                                            <a href="edit_review.php?id=<?php echo nl2br(htmlspecialchars($review['review_id'])); ?>"><i class="fas fa-edit"></i></p>
                                            <a href="?delete_id=<?php echo htmlspecialchars($review['review_id']); ?>" 
                                               onclick="return confirm('Are you sure you want to delete this review?');">
                                               <i class="fas fa-trash-alt text-danger"></i>
                                            </a>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p>No reviews yet. Be the first to review!</p>
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
<script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>

</body>
</html>
<script>
    CKEDITOR.replace('pro_desc')
    CKEDITOR.replace('short_desc')
</script>
