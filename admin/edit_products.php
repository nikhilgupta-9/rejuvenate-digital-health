<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "db-conn.php";
include "functions.php";

if (!isset($_GET['edit_product_details'])) {
    die("Service ID is missing from the URL.");
}

$product_id = intval($_GET['edit_product_details']);

$stmt = $conn->prepare("SELECT * FROM products WHERE pro_id = ? LIMIT 1");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) die("Service not found.");

$categories_result = mysqli_query($conn, "SELECT * FROM `categories` WHERE status = 1 ORDER BY categories ASC");

$current_category_id = (int)$product['pro_cate'];
$subcategory_query   = $conn->prepare("SELECT * FROM `sub_categories` WHERE parent_id = ? AND status = 1 ORDER BY categories ASC");
$subcategory_query->bind_param('i', $current_category_id);
$subcategory_query->execute();
$subcategories_result = $subcategory_query->get_result();
$subcategory_query->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Medical Service | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .section-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            border-left: 4px solid #7367f0;
            padding: 24px 26px;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #7367f0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-label { font-weight: 500; color: #495057; margin-bottom: 6px; }
        .form-control, .form-select {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #7367f0;
            box-shadow: 0 0 0 3px rgba(115,103,240,.15);
        }
        .required-field::after { content: " *"; color: #f44336; }
        .price-wrap { position: relative; }
        .price-wrap .rupee {
            position: absolute; left: 13px; top: 11px;
            font-weight: 600; color: #495057; z-index: 1;
        }
        .price-wrap input { padding-left: 26px; }
        .img-thumb {
            width: 90px; height: 90px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .badge-active   { background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 20px; font-size:.8rem; }
        .badge-inactive { background: #ffebee; color: #c62828; padding: 4px 10px; border-radius: 20px; font-size:.8rem; }
    </style>
</head>
<body class="crm_body_bg">
<?php include "header.php"; ?>

<section class="main_content dashboard_part">
    <div class="container-fluid g-0">
        <div class="row">
            <div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div>
        </div>
    </div>

    <div class="main_content_iner">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="mb-0 fw-bold">Edit Medical Service</h2>
                            <p class="text-muted mb-0 small">Update the details of this medical service / listing</p>
                        </div>
                        <a href="show-products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Services
                        </a>
                    </div>
                </div>

                <div class="col-lg-12">
                    <form action="update-product.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="pro_id"          value="<?= $product['pro_id'] ?>">
                        <input type="hidden" name="current_images"  value="<?= htmlspecialchars($product['pro_img']) ?>">

                        <!-- ── 1. Service Information ── -->
                        <div class="section-card">
                            <div class="section-title"><i class="fas fa-stethoscope"></i> Service Information</div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">Service Name</label>
                                    <input type="text" class="form-control" name="pro_name"
                                        placeholder="e.g. General Consultation, Blood Test, ECG"
                                        value="<?= htmlspecialchars($product['pro_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">Doctor's Qualification</label>
                                    <input type="text" class="form-control" name="brand_name"
                                        placeholder="e.g. M.B.B.S., M.D., M.S."
                                        value="<?= htmlspecialchars($product['brand_name']) ?>" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label required-field">Service Summary</label>
                                    <textarea class="form-control" name="short_desc" id="short_desc" rows="3"
                                        placeholder="Brief summary shown in listings and search results" required><?= htmlspecialchars($product['short_desc']) ?></textarea>
                                </div>
                                <div class="col-md-12 mb-0">
                                    <label class="form-label required-field">Full Service Description</label>
                                    <textarea class="form-control" name="pro_desc" id="pro_desc" rows="5"
                                        placeholder="Detailed description: what the service includes, procedure, expected outcomes, etc." required><?= htmlspecialchars($product['description']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- ── 2. Category ── -->
                        <div class="section-card">
                            <div class="section-title"><i class="fas fa-tags"></i> Service Category</div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">Service Category</label>
                                    <select class="form-select" name="pro_cate" id="pro_cate" required onchange="loadSubcategories(this.value)">
                                        <option value="">— Select Category —</option>
                                        <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                            <option value="<?= $cat['cate_id'] ?>"
                                                <?= ($cat['cate_id'] == $product['pro_cate']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['categories']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Service Sub-Category</label>
                                    <select class="form-select" name="pro_sub_cate" id="subcate_id">
                                        <option value="">— Select Sub-Category —</option>
                                        <?php while ($sub = $subcategories_result->fetch_assoc()):
                                            $sub_id   = $sub['cate_id'] ?? $sub['id'];
                                            $sub_name = $sub['sub_cate_name'] ?? $sub['categories'];
                                        ?>
                                            <option value="<?= $sub_id ?>"
                                                <?= ($sub_id == $product['pro_sub_cate']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sub_name) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ── 3. Pricing ── -->
                        <div class="section-card">
                            <div class="section-title"><i class="fas fa-rupee-sign"></i> Pricing</div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Standard Price (MRP)</label>
                                    <div class="price-wrap">
                                        <span class="rupee">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="mrp"
                                            placeholder="0.00"
                                            value="<?= htmlspecialchars($product['mrp']) ?>" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Discounted / Sale Price</label>
                                    <div class="price-wrap">
                                        <span class="rupee">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="selling_price"
                                            placeholder="0.00"
                                            value="<?= htmlspecialchars($product['selling_price']) ?>" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bulk / Corporate Price</label>
                                    <div class="price-wrap">
                                        <span class="rupee">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="whole_sale_selling_price"
                                            placeholder="0.00"
                                            value="<?= htmlspecialchars($product['whole_sale_selling_price']) ?>" min="0">
                                    </div>
                                    <small class="text-muted">Optional — for corporate / bulk enquiries</small>
                                </div>
                            </div>
                        </div>

                        <!-- ── 4. Availability & Flags ── -->
                        <div class="section-card">
                            <div class="section-title"><i class="fas fa-calendar-check"></i> Availability &amp; Flags</div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label required-field">Total Slots / Stock</label>
                                    <input type="number" class="form-control" name="stock"
                                        placeholder="Max slots available"
                                        value="<?= htmlspecialchars($product['stock']) ?>" min="0" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label required-field">Quantity per Booking</label>
                                    <input type="number" class="form-control" name="qty"
                                        placeholder="Units per booking"
                                        value="<?= htmlspecialchars($product['qty']) ?>" min="0" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Show in Inquiry Form</label>
                                    <select class="form-select" name="new_arrival">
                                        <option value="0" <?= $product['new_arrival'] == 0 ? 'selected' : '' ?>>No</option>
                                        <option value="1" <?= $product['new_arrival'] == 1 ? 'selected' : '' ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Mark as Special Offer</label>
                                    <select class="form-select" name="trending">
                                        <option value="0" <?= $product['trending'] == 0 ? 'selected' : '' ?>>No</option>
                                        <option value="1" <?= $product['trending'] == 1 ? 'selected' : '' ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label required-field">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="1" <?= $product['status'] == 1 ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= $product['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ── 5. Service Image ── -->
                        <div class="section-card">
                            <div class="section-title"><i class="fas fa-images"></i> Service Image</div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Upload New Image</label>
                                    <input type="file" class="form-control" name="pro_img[]" multiple accept="image/*">
                                    <small class="text-muted">Upload to replace the existing image (JPEG / PNG, max 5 MB)</small>
                                </div>

                                <?php if (!empty($product['pro_img'])): ?>
                                    <div class="col-md-12">
                                        <label class="form-label">Current Image</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <?php foreach (explode(',', $product['pro_img']) as $img):
                                                $img = trim($img);
                                                if (empty($img)) continue; ?>
                                                <div class="text-center">
                                                    <img src="assets/img/uploads/<?= htmlspecialchars($img) ?>"
                                                        class="img-thumb" alt="service image">
                                                    <div class="form-check mt-1">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="remove_images[]"
                                                            value="<?= htmlspecialchars($img) ?>"
                                                            id="rm_<?= md5($img) ?>">
                                                        <label class="form-check-label small text-danger" for="rm_<?= md5($img) ?>">Remove</label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ── 6. SEO ── -->
                        <div class="section-card">
                            <div class="section-title"><i class="fas fa-search"></i> SEO Settings</div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Meta Title</label>
                                    <input type="text" class="form-control" name="meta_title" maxlength="60"
                                        placeholder="Page title for search engines (50–60 chars)"
                                        value="<?= htmlspecialchars($product['meta_title']) ?>">
                                    <small class="text-muted">Recommended: 50–60 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Meta Keywords</label>
                                    <input type="text" class="form-control" name="meta_key"
                                        placeholder="consultation, blood test, cardiology, ..."
                                        value="<?= htmlspecialchars($product['meta_key']) ?>">
                                    <small class="text-muted">Comma-separated keywords</small>
                                </div>
                                <div class="col-md-12 mb-0">
                                    <label class="form-label">Meta Description</label>
                                    <textarea class="form-control" name="meta_desc" rows="2"
                                        placeholder="Brief description for search result snippets (150–160 chars)"><?= htmlspecialchars($product['meta_desc']) ?></textarea>
                                    <small class="text-muted">Recommended: 150–160 characters</small>
                                </div>
                            </div>
                        </div>

                        <!-- ── Submit ── -->
                        <div class="d-flex gap-3 mb-4">
                            <button type="submit" name="update-product" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Update Service
                            </button>
                            <a href="show-products.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include "footer.php"; ?>

    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        CKEDITOR.replace('pro_desc');
        CKEDITOR.replace('short_desc');

        function loadSubcategories(cate_id) {
            if (!cate_id) {
                $('#subcate_id').html('<option value="">— Select Sub-Category —</option>');
                return;
            }
            $.ajax({
                url: 'get_subcategories.php',
                method: 'GET',
                data: { category_ids: cate_id, current_subcategory: '<?= $product['pro_sub_cate'] ?>' },
                success: function(data) { $('#subcate_id').html(data); },
                error:   function()    { $('#subcate_id').html('<option value="">Error loading sub-categories</option>'); }
            });
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const mrp   = parseFloat(document.querySelector('[name="mrp"]').value);
            const price = parseFloat(document.querySelector('[name="selling_price"]').value);
            if (price > mrp) {
                e.preventDefault();
                alert('Discounted price cannot be higher than the Standard Price (MRP).');
            }
        });
    </script>
</section>
</body>
</html>
