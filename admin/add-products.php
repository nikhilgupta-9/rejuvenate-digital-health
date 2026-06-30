<?php
session_start();
include "db-conn.php";

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM `categories` WHERE status = 1 ORDER BY categories ASC";
$check = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add New Product | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    <style>
        .form-section {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            padding: 25px;
            border-left: 4px solid #4e73df;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            font-size: 1.3rem;
        }

        .form-control,
        .form-select {
            border-radius: 4px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }

        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }

        .btn-submit {
            background: #4e73df;
            border: none;
            padding: 10px 25px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: #3a5bbf;
            transform: translateY(-2px);
        }

        .preview-image-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .preview-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #eee;
        }

        .required-field::after {
            content: " *";
            color: #f44336;
        }

        .category-tags,
        .subcategory-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .category-tag,
        .subcategory-tag {
            background: #e9ecef;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .category-tag .remove-tag,
        .subcategory-tag .remove-tag {
            cursor: pointer;
            color: #6c757d;
            font-weight: bold;
        }

        .category-tag .remove-tag:hover,
        .subcategory-tag .remove-tag:hover {
            color: #dc3545;
        }

        .multiselect-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
            margin-top: 5px;
            display: none;
            position: absolute;
            background: white;
            z-index: 1000;
            width: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .multiselect-container.show {
            display: block;
        }

        .multiselect-option {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
        }

        .multiselect-option:hover {
            background-color: #f8f9fa;
        }

        .multiselect-option.selected {
            background-color: #4e73df;
            color: white;
        }

        .select-wrapper {
            position: relative;
        }

        .subcategory-section {
            display: none;
        }

        .subcategory-section.show {
            display: block;
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

        <div class="main_content_iner ">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Add New Product</h2>
                                        <p class="m-0 text-muted">Fill in the details below to add a new product to your inventory</p>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="card-body">
                                    <form id="productForm" action="functions.php" method="post" enctype="multipart/form-data">

                                        <!-- Basic Information Section -->
                                        <div class="form-section">
                                            <div class="section-title">
                                                <i class="fas fa-info-circle"></i> Basic Information
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field" for="proName">Product Name</label>
                                                    <input type="text" class="form-control" name="pro_name" id="proName" placeholder="Enter product name" required />
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field" for="brandName">Qualification</label>
                                                    <input type="text" class="form-control" name="brand_name" id="brandName" placeholder="Enter Qualification eg- M.B.B.S., ..." required />
                                                </div>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label" for="inputEmail4">Short Description</label>
                                                <textarea class="form-control" name="short_desc" required></textarea>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label" for="inputEmail4">Product Description</label>
                                                <textarea class="form-control" name="pro_desc" required></textarea>
                                            </div>
                                        </div>

                                        <!-- Category & Subcategory Section -->
                                        <div class="form-section">
                                            <div class="section-title">
                                                <i class="fas fa-tags"></i> Categories & Subcategories
                                            </div>

                                            <!-- Main Categories -->
                                            <div class="row mb-4">
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label required-field">Product Categories</label>
                                                    <div class="select-wrapper">
                                                        <input type="text" class="form-control" id="categorySearch"
                                                            placeholder="Search and select main categories..."
                                                            onfocus="showCategoryDropdown()">
                                                        <div class="multiselect-container" id="categoryDropdown">
                                                            <?php
                                                            $categories_result = mysqli_query($conn, "SELECT * FROM categories WHERE status = 1 ORDER BY categories ASC");
                                                            while ($category = mysqli_fetch_assoc($categories_result)):
                                                            ?>
                                                                <div class="multiselect-option"
                                                                    data-value="<?= $category['cate_id'] ?>"
                                                                    data-name="<?= htmlspecialchars($category['categories']) ?>"
                                                                    onclick="selectCategory(this)">
                                                                    <?= htmlspecialchars($category['categories']) ?>
                                                                </div>
                                                            <?php endwhile; ?>
                                                        </div>
                                                        <div class="category-tags" id="selectedCategories"></div>
                                                        <input type="hidden" name="pro_cate" id="pro_cate">
                                                    </div>
                                                    <small class="text-muted">Select one or more main categories</small>
                                                </div>
                                            </div>

                                            <!-- Subcategories -->
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <div class="subcategory-section" id="subcategorySection">
                                                        <label class="form-label">Product Subcategories</label>
                                                        <div class="select-wrapper">
                                                            <input type="text" class="form-control" id="subcategorySearch"
                                                                placeholder="Search and select subcategories..."
                                                                onfocus="showSubcategoryDropdown()">
                                                            <div class="multiselect-container" id="subcategoryDropdown">
                                                                <div class="text-muted p-3">Select main categories first to see subcategories</div>
                                                            </div>
                                                            <div class="subcategory-tags" id="selectedSubcategories"></div>
                                                            <input type="hidden" name="pro_sub_cate" id="pro_sub_cate">
                                                        </div>
                                                        <small class="text-muted">Select one or more subcategories (optional)</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field" for="stock">Stock Quantity</label>
                                                    <input type="number" class="form-control" name="stock" id="stock" placeholder="Available quantity" min="0" required />
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field" for="status">Status</label>
                                                    <select class="form-select" name="status" id="status" required>
                                                        <option value="1" selected>Active</option>
                                                        <option value="0">Inactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pricing Section -->
                                        <div class="form-section">
                                            <div class="section-title">
                                                <i class="fas fa-tag"></i> Pricing Information
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label required-field" for="mrp">Manufacturer's Price (MRP)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="mrp" id="mrp" placeholder="0.00" step="0.01" min="0" required />
                                                    </div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label required-field" for="sellingPrice">Selling Price</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="selling_price" id="sellingPrice" placeholder="0.00" step="0.01" min="0" required />
                                                    </div>
                                                </div>

                                                <!-- Add this in the Pricing Section after the wholesale price field -->
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label required-field" for="qty">Quantity</label>
                                                    <input type="number" class="form-control" name="qty" id="qty" placeholder="Enter quantity" min="0" required />
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label" for="newArrival">Show in Inquery Form</label>
                                                    <select class="form-select" name="new_arrival" id="newArrival">
                                                        <option value="0" selected>No</option>
                                                        <option value="1">Yes</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label" for="trending">Mark as Special Offer</label>
                                                    <select class="form-select" name="trending" id="trending">
                                                        <option value="0" selected>No</option>
                                                        <option value="1">Yes</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Media Section -->
                                        <div class="form-section">
                                            <div class="section-title">
                                                <i class="fas fa-images"></i> Product Images
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label required-field" for="pro_img">Upload Product Images</label>
                                                    <input type="file" class="form-control" name="pro_img[]" id="pro_img" multiple required />
                                                    <small class="text-muted">You can select multiple images (JPEG, PNG, max 5MB each)</small>
                                                    <div class="preview-image-container" id="imagePreview"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SEO Section -->
                                        <div class="form-section">
                                            <div class="section-title">
                                                <i class="fas fa-search"></i> SEO Settings
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label required-field" for="metaTitle">Meta Title</label>
                                                    <input type="text" class="form-control" name="meta_title" id="metaTitle" placeholder="Title for search engines (50-60 characters)" maxlength="60" required />
                                                    <small class="text-muted" id="titleCounter">0/60 characters</small>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field" for="metaKey">Meta Keywords</label>
                                                    <input type="text" class="form-control" name="meta_key" id="metaKey" placeholder="Comma separated keywords" required />
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field" for="metaDesc">Meta Description</label>
                                                    <input type="text" class="form-control" name="meta_desc" id="metaDesc" placeholder="Brief description for search results" required />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo me-2"></i>Reset Form
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-submit" name="add-product">
                                                <i class="fas fa-plus-circle me-2"></i>Add Product
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

        <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
        <script>
            // Initialize CKEditor
            CKEDITOR.replace('pro_desc');
            CKEDITOR.replace('short_desc');

            let selectedCategories = [];
            let selectedSubcategories = [];

            // Category functions
            function showCategoryDropdown() {
                document.getElementById('categoryDropdown').classList.add('show');
            }

            function hideCategoryDropdown() {
                document.getElementById('categoryDropdown').classList.remove('show');
            }

            function selectCategory(element) {
                const categoryId = element.getAttribute('data-value');
                const categoryName = element.getAttribute('data-name');

                if (!selectedCategories.find(cat => cat.id === categoryId)) {
                    selectedCategories.push({
                        id: categoryId,
                        name: categoryName
                    });
                    updateSelectedCategories();
                    loadSubcategoriesForSelectedCategories();
                }

                document.getElementById('categorySearch').value = '';
                hideCategoryDropdown();
            }

            function removeCategory(categoryId) {
                selectedCategories = selectedCategories.filter(cat => cat.id !== categoryId);
                updateSelectedCategories();
                loadSubcategoriesForSelectedCategories();
            }

            function updateSelectedCategories() {
                const container = document.getElementById('selectedCategories');
                const hiddenInput = document.getElementById('pro_cate');

                container.innerHTML = '';
                const categoryIds = [];

                selectedCategories.forEach(category => {
                    categoryIds.push(category.id);

                    const tag = document.createElement('div');
                    tag.className = 'category-tag';
                    tag.innerHTML = `
                        ${category.name}
                        <span class="remove-tag" onclick="removeCategory('${category.id}')">×</span>
                    `;
                    container.appendChild(tag);
                });

                hiddenInput.value = categoryIds.join(',');

                // Show/hide subcategory section
                if (selectedCategories.length > 0) {
                    document.getElementById('subcategorySection').classList.add('show');
                } else {
                    document.getElementById('subcategorySection').classList.remove('show');
                    selectedSubcategories = [];
                    updateSelectedSubcategories();
                }
            }

            // Subcategory functions
            function showSubcategoryDropdown() {
                if (selectedCategories.length === 0) {
                    alert('Please select main categories first');
                    return;
                }
                document.getElementById('subcategoryDropdown').classList.add('show');
            }

            function hideSubcategoryDropdown() {
                document.getElementById('subcategoryDropdown').classList.remove('show');
            }

            function selectSubcategory(element) {
                const subcategoryId = element.getAttribute('data-value');
                const subcategoryName = element.getAttribute('data-name');

                if (!selectedSubcategories.find(sub => sub.id === subcategoryId)) {
                    selectedSubcategories.push({
                        id: subcategoryId,
                        name: subcategoryName
                    });
                    updateSelectedSubcategories();
                }

                document.getElementById('subcategorySearch').value = '';
                hideSubcategoryDropdown();
            }

            function removeSubcategory(subcategoryId) {
                selectedSubcategories = selectedSubcategories.filter(sub => sub.id !== subcategoryId);
                updateSelectedSubcategories();
            }

            function updateSelectedSubcategories() {
                const container = document.getElementById('selectedSubcategories');
                const hiddenInput = document.getElementById('pro_sub_cate');

                container.innerHTML = '';
                const subcategoryIds = [];

                selectedSubcategories.forEach(subcategory => {
                    subcategoryIds.push(subcategory.id);

                    const tag = document.createElement('div');
                    tag.className = 'subcategory-tag';
                    tag.innerHTML = `
                        ${subcategory.name}
                        <span class="remove-tag" onclick="removeSubcategory('${subcategory.id}')">×</span>
                    `;
                    container.appendChild(tag);
                });

                hiddenInput.value = subcategoryIds.join(',');
            }

            // Load subcategories based on selected categories
            function loadSubcategoriesForSelectedCategories() {
                if (selectedCategories.length === 0) {
                    document.getElementById('subcategoryDropdown').innerHTML = '<div class="text-muted p-3">Select main categories first to see subcategories</div>';
                    selectedSubcategories = [];
                    updateSelectedSubcategories();
                    return;
                }

                const categoryIds = selectedCategories.map(cat => cat.id).join(',');

                fetch('get_subcategories.php?category_ids=' + categoryIds)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('subcategoryDropdown').innerHTML = data;
                    })
                    .catch(error => {
                        console.error('Error loading subcategories:', error);
                    });
            }

            // Search categories
            document.getElementById('categorySearch').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const options = document.querySelectorAll('#categoryDropdown .multiselect-option');

                options.forEach(option => {
                    const text = option.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });

            // Search subcategories
            document.getElementById('subcategorySearch').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const options = document.querySelectorAll('#subcategoryDropdown .multiselect-option');

                options.forEach(option => {
                    const text = option.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });

            // Hide dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.select-wrapper')) {
                    hideCategoryDropdown();
                    hideSubcategoryDropdown();
                }
            });

            // Image preview functionality
            document.getElementById('pro_img').addEventListener('change', function(event) {
                const previewContainer = document.getElementById('imagePreview');
                previewContainer.innerHTML = '';

                if (this.files) {
                    Array.from(this.files).forEach(file => {
                        const reader = new FileReader();

                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.classList.add('preview-image');
                            previewContainer.appendChild(img);
                        }

                        reader.readAsDataURL(file);
                    });
                }
            });

            // Character counter for meta title
            document.getElementById('metaTitle').addEventListener('input', function() {
                const counter = document.getElementById('titleCounter');
                counter.textContent = `${this.value.length}/60 characters`;

                if (this.value.length > 60) {
                    counter.style.color = 'red';
                } else {
                    counter.style.color = '#6c757d';
                }
            });

            // Form validation
            document.getElementById('productForm').addEventListener('submit', function(event) {
                const mrp = parseFloat(document.getElementById('mrp').value);
                const sellingPrice = parseFloat(document.getElementById('sellingPrice').value);

                if (selectedCategories.length === 0) {
                    alert('Please select at least one category');
                    event.preventDefault();
                    return;
                }

                if (sellingPrice > mrp) {
                    alert('Selling price cannot be higher than MRP');
                    event.preventDefault();
                }
            });
        </script>
    </section>
</body>

</html>