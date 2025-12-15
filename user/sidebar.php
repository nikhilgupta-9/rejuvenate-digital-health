<style>
    /* Sidebar */
    .sidebar {
        box-shadow: 0px 0px 6px #ccc;
        width: 100%;
        background: #ffffff;
        min-height: 100vh;
        padding: 20px;
        border-right: 1px solid #ddd;
        border-radius: 11px;
    }

    .sidebar .user-circle {
        width: 70px;
        height: 70px;
        background: #1b8bb4;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin-bottom: 10px;
    }

    .sidebar a {
        display: block;
        padding: 10px 15px;
        color: #333;
        text-decoration: none;
        font-size: 16px;
        font-weight: 500;
        border-bottom: 1px solid #eee;
        margin-bottom: 5px;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .sidebar a:hover {
        background: #f8f9fa;
        color: #1b8bb4;
    }

    .sidebar a.active {
        color: #ff5722;
        font-weight: bold;
        background: #fff3e0;
        border-left: 4px solid #ff5722;
    }

    /* Profile Card */
    .profile-card {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
    }

    .profile-image {
        width: 120px;
        height: 120px;
        border-radius: 100px;
        border: 6px solid #f29819;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 50px;
        color: #999;
        margin-bottom: 10px;
        position: relative;
    }

    .profile-image .camera-icon {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: #fff;
        border-radius: 50%;
        padding: 8px;
        border: 1px solid #ddd;
        cursor: pointer;
    }

    /* Mobile Sidebar Toggle */
    @media (max-width: 992px) {
        .sidebar {
            position: fixed;
            left: -360px;
            top: 0;
            height: 100%;
            z-index: 1111;
            transition: 0.3s;
            width: 280px !important;
        }

        .sidebar.show {
            left: 0;
        }

        .menu-btn {
            display: block !important;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1112;
            background: #1b8bb4;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
        }
    }

    .menu-btn {
        font-size: 26px;
        cursor: pointer;
        display: none;
    }

    .profile-card h4 {
        border-bottom: 1px solid #ccc;
        padding-bottom: 10px;
    }

    .userd-image {
        height: 80px;
        width: 80px;
        border-radius: 50%;
        border: 2px solid #0c74c5;
        object-fit: cover;
    }

    .info-content p {
        font-size: 14px;
        color: #000;
        padding: 0px;
        margin: 0px;
        line-height: 22px;
    }

    .user_dash_box {
        border: 1px solid #0270c3;
        border-radius: 20px;
        text-align: center;
        padding: 20px;
        box-shadow: 0px 0px 7px #ccc;
        cursor: pointer;
        margin: 10px 0px;
    }

    .user_dash_box img {
        height: 70px;
        margin-bottom: 10px;
    }

    .save-add {
        margin-top: 32px;
    }

    /* Logout button specific styles */
    .sidebar .btn-danger {
        display: block;
        width: 100%;
        padding: 10px;
        margin-top: 20px;
        text-align: center;
        border-radius: 5px;
    }
</style>

<!-- Mobile Menu Toggle Button -->
<button class="menu-btn" id="menuToggle">
    <i class="fas fa-bars"></i> Menu
</button>

<div class="sidebar" id="sidebarMenu">
    <?php
    // Get user data from database
    $user_data = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
    }
    ?>
    
    <div class="text-center info-content">
        <img src="<?= $site ?>assets/img/<?= !empty($user_data['profile_pic']) ? $user_data['profile_pic'] : 'dummy.png' ?>" 
             class="userd-image" alt="User Profile">
        <h5><?= htmlspecialchars($user_data['name'] ?? 'User') ?></h5>
        <p><?= htmlspecialchars($user_data['email'] ?? 'user@example.com') ?></p>
        <p>Phone: <?= htmlspecialchars($user_data['mobile'] ?? '+91 XXXXX XXXXX') ?></p>
        <a href="my-profile.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Info</a>
    </div>

    <?php
    // Get current page filename
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Define menu items with their URLs
    $menu_items = [
        'user-dashboard.php' => 'Dashboard',
        'my-profile.php' => 'My Profile',
        'my-bookings.php' => 'My Bookings',
        'my-reports.php' => 'My Reports',
        'my-supplement-order.php' => 'My Supplement Order',
        'my-doctor-appointments.php' => 'My Doctor Appointments',
        'manage-address.php' => 'Manage Addresses',
        'help-and-contact.php' => 'Help & Contact Us'
    ];
    
    // Generate menu links with active class
    foreach ($menu_items as $page => $title) {
        $active_class = ($current_page === $page) ? 'active' : '';
        echo "<a href=\"$page\" class=\"$active_class\">$title</a>";
    }
    
    // Logout link
    echo '<a href="'.$site.'logout.php" class="btn btn-danger mt-3">Logout</a>';
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebarMenu');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        }); 
        
        // Close sidebar when a menu item is clicked on mobile
        const menuLinks = sidebar.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('show');
                }
            });
        });
    }
});

// Also add the global toggleMenu function for the inline onclick
function toggleMenu() {
    const sidebar = document.getElementById('sidebarMenu');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}
</script>