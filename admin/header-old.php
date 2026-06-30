<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: auth/login.php");
    exit();
}
?>

<nav class="sidebar vertical-scroll dark_sidebar ps-container ps-theme-default ps-active-y">
    <!-- <div class="logo d-flex align-items-center justify-content-between py-3 px-3">
        <a href="index.php">
            <img src="assets/img/logo_icon.jpg" alt="Admin Logo" class="logo-img" style="max-width: 100px;">
        </a>
        <div class="sidebar_close_icon d-lg-none">
            <i class="ti-close"></i>
        </div>
    </div> -->

    <div class="admin-profile text-center py-3">
    <a href="index.php">
        <img src="assets/img/logo_icon.jpg" alt="Admin Avatar" class="rounded-circle" width="60">
        <h6 class="mt-2 text-white">Admin Name</h6>
    </a>
    </div>
    
    <div class="search-box px-3">
        <input type="text" id="sidebarSearch" class="form-control" placeholder="Search...">
    </div>

    <ul id="sidebar_menu" class="sidebar-menu mt-3">
        <li><a href="index.php"><i class="ti-dashboard"></i> <span>Dashboard</span></a></li>

        <li>
            <a class="has-arrow" href="#"><i class="ti-home"></i> <span>Home Content</span></a>
            <ul>
                <li><a href="home-items.php">Add Logo</a></li>
                <li><a href="add-banner.php">Add Banners</a></li>
            </ul>
        </li>
        
        <li>
            <a class="has-arrow" href="#"><i class="ti-layout-grid2"></i> <span>Categories</span></a>
            <ul>
                <li><a href="add-categories.php">Add Category</a></li>
                <li><a href="view-categories.php">View Categories</a></li>
                <li><a href="add-sub-category.php">Add Sub Category</a></li>
                <li><a href="view-sub-categories.php">View Sub Categories</a></li>
            </ul>
        </li>
        
        <li>
            <a class="has-arrow" href="#"><i class="ti-package"></i> <span>Products</span></a>
            <ul>
                <li><a href="add-products.php">Add Products</a></li>
                <li><a href="show-products.php">Show Products</a></li>
                <li><a href="show-products-review.php">Product Reviews</a></li>
                <!--<li><a href="make-deal-of-the-day.php">Deal of the Day</a></li>-->
                <!--<li><a href="best-seller.php">Best Seller</a></li>-->
            </ul>
        </li>
        
        <!-- <li><a href="about_us.php"><i class="ti-info"></i> <span>About Us</span></a></li> -->
        <li>
            <a class="has-arrow" href="#"><i class="ti-notepad"></i> <span>Blogs & News</span></a>
            <ul>
                <li><a href="add-blog.php">Add Blog</a></li>
                <li><a href="view-all-blog.php">View Blogs</a></li>
            </ul>
        </li>
        <li>
            <a class="has-arrow" href="#"><i class="ti-notepad"></i> <span>Best Brand</span></a>
            <ul>
                <li><a href="our-best-brand.php">Add Brands</a></li>
                <li><a href="view_brands.php">View Brands</a></li>
            </ul>
        </li>

        <li>
            <a class="has-arrow" href="#"><i class="ti-info"></i> <span>About Us Page</span></a>
            <ul>
                <li><a href="about_us.php">Add About</a></li>
                <li><a href="add-about-us-section.php">Add About sections</a></li>
            </ul>
        </li>
        
        <li>
            <a class="has-arrow" href="#"><i class="ti-email"></i> <span>Contact Page</span></a>
            <ul>
                <li><a href="add_contact.php">Edit Contact Info</a></li>
                <li><a href="new-leads.php">Inquiries</a></li>
            </ul>
        </li>
        
        <li><a href="add-gallery.php"><i class="ti-gallery"></i> <span>Gallery</span></a></li>
        
        <li>
            <a class="has-arrow" href="#"><i class="ti-user"></i> <span>Customers</span></a>
            <ul>
                <li><a href="all-customers.php">All Customers</a></li>
            </ul>
        </li>
        
        <!-- <li><a href="invoice-generate.php"><i class="ti-receipt"></i> <span>Generate Invoice</span></a></li> -->
        
        <li><a href="orders.php"><i class="ti-shopping-cart"></i> <span>Orders</span></a></li>
        
        <li>
            <a class="has-arrow" href="#"><i class="ti-settings"></i> <span>Users</span></a>
            <ul>
                <li><a href="all-admin.php">All Users</a></li>
                <li><a href="admin-create.php">Create Admin</a></li>
            </ul>
        </li>
        
        <li><a href="auth/logout.php"><i class="ti-power-off"></i> <span>Log Out</span></a></li>
    </ul>
</nav>

<script>
document.getElementById('sidebarSearch').addEventListener('input', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#sidebar_menu li').forEach(item => {
        let text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
