<?php
session_start();
include "db-conn.php";

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Delete testimonial
if (isset($_GET['deleteId'])) {
    $delete_id = intval($_GET['deleteId']);
    
    // First get the photo path to delete the file
    $stmt = $conn->prepare("SELECT client_photo FROM testimonials WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $testimonial = $result->fetch_assoc();
    
    if ($testimonial && !empty($testimonial['client_photo'])) {
        $photo_path = "../uploads/testimonials/" . $testimonial['client_photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }
    
    // Now delete the record
    $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['message'] = "Testimonial deleted successfully";
    header("Location: view-testimonials.php");
    exit();
}

// Fetch all testimonials
$result = $conn->query("SELECT * FROM testimonials ORDER BY display_order ASC, created_at DESC");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Manage Testimonials | Admin Panel</title>
    <link rel="icon" href="img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --success-color: #4bb543;
        }
        
        .testimonial-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #eee;
        }
        
        .rating-stars {
            color: #ffc107;
        }
        
        .featured-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .table thead th {
            background-color: var(--dark-color);
            color: white;
            border-bottom: none;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .status-active {
            color: var(--success-color);
        }
        
        .status-inactive {
            color: #dc3545;
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
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Manage Testimonials</h2>
                                    </div>
                                    <div class="add_button ms-2">
                                        <a href="add-testimonial.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i> Add New Testimonial
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <?php if (isset($_SESSION['message'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $_SESSION['message']; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['message']); ?>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Client</th>
                                                <th>Testimonial</th>
                                                <th>Rating</th>
                                                <th>Project</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($testimonial = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($testimonial['id']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($testimonial['client_photo'])): ?>
                                                                <img src="uploads/testimonials/<?= htmlspecialchars($testimonial['client_photo']); ?>" 
                                                                     alt="<?= htmlspecialchars($testimonial['client_name']); ?>" 
                                                                     class="testimonial-img me-3">
                                                            <?php else: ?>
                                                                <div class="testimonial-img bg-light d-flex align-items-center justify-content-center me-3">
                                                                    <i class="fas fa-user text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong><?= htmlspecialchars($testimonial['client_name']); ?></strong><br>
                                                                <small class="text-muted">
                                                                    <?= htmlspecialchars($testimonial['client_title']); ?>
                                                                    <?php if (!empty($testimonial['client_company'])): ?>
                                                                        at <?= htmlspecialchars($testimonial['client_company']); ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="text-truncate" style="max-width: 250px;">
                                                            <?= htmlspecialchars($testimonial['testimonial_text']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="rating-stars">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star<?= $i > $testimonial['rating'] ? '-half-alt' : ''; ?>"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1">(<?= $testimonial['rating']; ?>)</span>
                                                        </div>
                                                        <?php if ($testimonial['featured']): ?>
                                                            <span class="featured-badge mt-1 d-inline-block">Featured</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($testimonial['project_name'])): ?>
                                                            <strong><?= htmlspecialchars($testimonial['project_name']); ?></strong><br>
                                                            <?php if (!empty($testimonial['project_date'])): ?>
                                                                <small class="text-muted">
                                                                    <?= date('M d, Y', strtotime($testimonial['project_date'])); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $testimonial['featured'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?= $testimonial['featured'] ? 'Featured' : 'Standard'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="action-btns">
                                                        <a href="edit-testimonial.php?edit=<?= $testimonial['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal<?= $testimonial['id']; ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                        
                                                        <!-- Delete Confirmation Modal -->
                                                        <div class="modal fade" id="deleteModal<?= $testimonial['id']; ?>" tabindex="-1"
                                                            aria-labelledby="deleteModalLabel<?= $testimonial['id']; ?>" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="deleteModalLabel<?= $testimonial['id']; ?>">
                                                                            Confirm Deletion
                                                                        </h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        Are you sure you want to delete the testimonial from 
                                                                        <strong><?= htmlspecialchars($testimonial['client_name']); ?></strong>?
                                                                        <br><br>
                                                                        <strong>This action cannot be undone.</strong>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <a href="?deleteId=<?= $testimonial['id']; ?>" class="btn btn-danger">Delete</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>