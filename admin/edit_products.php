<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "db-conn.php";
include "functions.php";

if (!isset($_GET['edit_product_details'])) {
    die("Product ID is missing from the URL.");
}

$product_id = intval($_GET['edit_product_details']);

// Fetch product details
$sql = "SELECT * FROM products WHERE pro_id = $product_id";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $product = mysqli_fetch_assoc($result);
} else {
    die("Product not found.");
}

// Fetch categories
$category_query = "SELECT * FROM `categories` WHERE status = 1 ORDER BY categories ASC";
$categories_result = mysqli_query($conn, $category_query);

// Get current product's category for subcategory loading
$current_category_id = $product['pro_cate'];

// Fetch subcategories for the current category
$subcategory_query = "SELECT * FROM `sub_categories` WHERE parent_id = '$current_category_id' AND status = 1 ORDER BY categories ASC";
$subcategories_result = mysqli_query($conn, $subcategory_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Product | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .product-form {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #7367f0;
            box-shadow: 0 0 0 3px rgba(115,103,240,.15);
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px 30px;
        }
        .main-title h2 {
            color: #2c2c2c;
            font-weight: 600;
        }
        .btn-primary {
            background-color: #7367f0;
            border-color: #7367f0;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .btn-primary:hover {
            background-color: #5d50e6;
            border-color: #5d50e6;
        }
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px dashed #ddd;
            padding: 5px;
        }
        .image-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            margin-right: 10px;
            margin-bottom: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        .price-input {
            position: relative;
        }
        .price-input .currency {
            position: absolute;
            left: 12px;
            top: 38px;
            font-weight: 500;
            color: #495057;
            z-index: 1;
        }
        .price-input input {
            padding-left: 25px;
        }
        .required-field::after {
            content: " *";
            color: #f44336;
        }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="page-header mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <h2 class="mb-0">Edit Product</h2>
                                <a href="show-products.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-12">
                        <div class="product-form">
                            <form action="update-product.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="pro_id" value="<?= $product['pro_id'] ?>">
                                <input type="hidden" name="current_images" value="<?= htmlspecialchars($product['pro_img']) ?>">
                                
                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label required-field">Product Name</label>
                                        <input type="text" class="form-control" name="pro_name" 
                                            value="<?= htmlspecialchars($product['pro_name']) ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label required-field">Brand Name</label>
                                        <input type="text" class="form-control" name="brand_name" 
                                            value="<?= htmlspecialchars($product['brand_name']) ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label required-field">Category</label>
                                        <select class="form-select" name="pro_cate" id="pro_cate" required onchange="loadSubcategories(this.value)">
                                            <option value="">Select Category</option>
                                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                                <option value="<?= $category['cate_id'] ?>" 
                                                    <?= ($category['cate_id'] == $product['pro_cate']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['categories']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Sub Category</label>
                                        <select class="form-select" name="pro_sub_cate" id="subcate_id">
                                            <option value="">Select Sub Category</option>
                                            <?php 
                                            if ($subcategories_result && mysqli_num_rows($subcategories_result) > 0):
                                                while ($subcategory = mysqli_fetch_assoc($subcategories_result)): 
                                                    // Determine the correct ID field to use
                                                    $subcat_id = isset($subcategory['cate_id']) ? $subcategory['cate_id'] : $subcategory['id'];
                                                    $subcat_name = isset($subcategory['sub_cate_name']) ? $subcategory['sub_cate_name'] : $subcategory['categories'];
                                            ?>
                                                <option value="<?= $subcat_id ?>" 
                                                    <?= ($subcat_id == $product['pro_sub_cate']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($subcat_name) ?>
                                                </option>
                                            <?php 
                                                endwhile;
                                            else: ?>
                                                <option value="">No subcategories available</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label required-field">Stock Quantity</label>
                                        <input type="number" class="form-control" name="stock" 
                                            value="<?= htmlspecialchars($product['stock']) ?>" min="0" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label required-field">Quantity</label>
                                        <input type="number" class="form-control" name="qty" 
                                            value="<?= htmlspecialchars($product['qty']) ?>" min="0" required>
                                    </div>
                                    
                                    <!-- Pricing -->
                                    <div class="col-md-4 mb-4 price-input">
                                        <label class="form-label required-field">MRP</label>
                                        <span class="currency">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="mrp" 
                                            value="<?= htmlspecialchars($product['mrp']) ?>" min="0" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-4 price-input">
                                        <label class="form-label required-field">Selling Price</label>
                                        <span class="currency">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="selling_price" 
                                            value="<?= htmlspecialchars($product['selling_price']) ?>" min="0" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-4 price-input">
                                        <label class="form-label">Wholesale Price</label>
                                        <span class="currency">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="whole_sale_selling_price" 
                                            value="<?= htmlspecialchars($product['whole_sale_selling_price']) ?>" min="0">
                                    </div>

                                    <!-- Flags -->
                                    <div class="col-md-4 mb-4">
                                        <label class="form-label">Show in Inquiry Form</label>
                                        <select class="form-select" name="new_arrival">
                                            <option value="0" <?= $product['new_arrival'] == 0 ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= $product['new_arrival'] == 1 ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-4">
                                        <label class="form-label">Mark as Special Offer</label>
                                        <select class="form-select" name="trending">
                                            <option value="0" <?= $product['trending'] == 0 ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= $product['trending'] == 1 ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-4">
                                        <label class="form-label required-field">Status</label>
                                        <select class="form-select" name="status" required>
                                            <option value="1" <?= $product['status'] == 1 ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= $product['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>

                                    <!-- Images -->
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Product Images</label>
                                        <input type="file" class="form-control" name="pro_img[]" multiple accept="image/*">
                                        <small class="text-muted">Select new images to replace existing ones (optional)</small>
                                        
                                        <?php if (!empty($product['pro_img'])): ?>
                                            <div class="mt-3">
                                                <label class="form-label">Current Image:</label>
                                                <div class="d-flex flex-wrap">
                                                    <?php 
                                                    // Handle single image or comma-separated images
                                                    $images = explode(',', $product['pro_img']);
                                                    foreach ($images as $img): 
                                                        if (!empty(trim($img))): ?>
                                                            <div class="position-relative me-2 mb-2">
                                                                <img src="assets/img/uploads/<?= htmlspecialchars(trim($img)) ?>" 
                                                                    class="image-thumbnail" 
                                                                  >
                                                                <div class="form-check mt-1">
                                                                    <input class="form-check-input" type="checkbox" name="remove_images[]" value="<?= htmlspecialchars(trim($img)) ?>" id="remove_<?= md5($img) ?>">
                                                                    <label class="form-check-label small" for="remove_<?= md5($img) ?>">
                                                                        Remove
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        <?php endif;
                                                    endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Descriptions -->
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label required-field">Short Description</label>
                                        <textarea class="form-control" name="short_desc" id="short_desc" rows="3" required><?= htmlspecialchars($product['short_desc']) ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label required-field">Product Description</label>
                                        <textarea class="form-control" name="pro_desc" id="pro_desc" rows="5" required><?= htmlspecialchars($product['description']) ?></textarea>
                                    </div>
                                    
                                    <!-- SEO -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" name="meta_title" 
                                            value="<?= htmlspecialchars($product['meta_title']) ?>">
                                        <small class="text-muted">Recommended: 50-60 characters</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Meta Keywords</label>
                                        <input type="text" class="form-control" name="meta_key" 
                                            value="<?= htmlspecialchars($product['meta_key']) ?>">
                                        <small class="text-muted">Comma separated keywords</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Meta Description</label>
                                        <textarea class="form-control" name="meta_desc" rows="2"><?= htmlspecialchars($product['meta_desc']) ?></textarea>
                                        <small class="text-muted">Recommended: 150-160 characters</small>
                                    </div>
                                    
                                    <!-- Submit -->
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="update-product" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-2"></i> Update Product
                                        </button>
                                        <a href="show-products.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-2"></i> Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            // Initialize CKEditor
            CKEDITOR.replace('pro_desc');
            CKEDITOR.replace('short_desc');
            
            function loadSubcategories(cate_id) {
                if (!cate_id) {
                    $('#subcate_id').html('<option value="">Select Sub Category</option>');
                    return;
                }
                
                $.ajax({
                    url: 'get_subcategories.php',
                    method: 'GET',
                    data: { 
                        category_ids: cate_id,
                        current_subcategory: '<?= $product['pro_sub_cate'] ?>'
                    },
                    success: function(data) {
                        $('#subcate_id').html(data);
                    },
                    error: function() {
                        $('#subcate_id').html('<option value="">Error loading subcategories</option>');
                    }
                });
            }

            // Price validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const mrp = parseFloat(document.querySelector('[name="mrp"]').value);
                const sellingPrice = parseFloat(document.querySelector('[name="selling_price"]').value);
                
                if (sellingPrice > mrp) {
                    e.preventDefault();
                    alert('Selling price cannot be higher than MRP');
                    return false;
                }
                
                return true;
            });
        </script>
    </body>
</html>