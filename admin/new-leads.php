<?php
session_start();
include "db-conn.php";

// Handle delete action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM inquiries WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success_msg = "Inquiry deleted successfully!";
    } else {
        $error_msg = "Error deleting inquiry: " . $conn->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Customer Inquiries | Sales Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    <!-- Additional CSS for this page -->
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        .message-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-cell:hover {
            white-space: normal;
            overflow: visible;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-new {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-read {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .action-btns {
            min-width: 100px;
        }

        .search-box {
            max-width: 300px;
            margin-bottom: 20px;
        }

        .pagination {
            justify-content: center;
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
                <!-- Success/Error Messages -->
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $error_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="row align-items-center">
                                    <div class="col-lg-6">
                                        <div class="main-title">
                                            <h2 class="m-0">Customer Inquiries</h2>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 text-end">
                                        <div class="d-flex justify-content-end">
                                            <div class="search-box me-2">
                                                <input type="text" class="form-control" id="searchInput"
                                                    placeholder="Search inquiries...">
                                            </div>
                                            <div class="filter-dropdown">
                                                <select class="form-select" id="statusFilter">
                                                    <option value="">All Statuses</option>
                                                    <option value="new">New</option>
                                                    <option value="read">Read</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Customer</th>
                                                <th scope="col">Contact</th>
                                                <th scope="col">Product Query</th>
                                                <th scope="col">Message</th>
                                                <th scope="col">Status</th>
                                                <th scope="col" class="action-btns">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $sno = 1;
                                            $sql = "SELECT * FROM inquiries ORDER BY created_at DESC";
                                            $result = mysqli_query($conn, $sql);

                                            if (mysqli_num_rows($result) > 0) {
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $status_class = $row['status'] == 'read' ? 'status-read' : 'status-new';
                                                    $status_text = $row['status'] == 'read' ? 'Read' : 'New';
                                                    ?>
                                                    <tr>
                                                        <td><?= $sno++ ?></td>
                                                        <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                                        <td>
                                                            <div><?= htmlspecialchars($row['email']) ?></div>
                                                            <div><?= htmlspecialchars($row['phone']) ?></div>
                                                        </td>
                                                        <td><?= htmlspecialchars($row['subject']) ?></td>
                                                        <td class="message-cell"
                                                            title="<?= htmlspecialchars($row['message']) ?>">
                                                            <?= htmlspecialchars($row['message']) ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge <?= $status_class ?>">
                                                                <?= $status_text ?>
                                                            </span>
                                                        </td>
                                                        <td class="action-btns">
                                                            <a href="view_inquiry.php?id=<?= $row['id'] ?>"
                                                                class="btn btn-sm btn-primary" title="View">
                                                                <i class="fa fa-eye"></i>
                                                            </a>
                                                            <!-- Reply via Email -->
                                                            <a href="mailto:developer.web2techsolution@gmail.com"
                                                                class="btn btn-sm btn-info" title="Reply">
                                                                <i class="fa fa-reply"></i>
                                                            </a>
                                                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                                                                title="Delete"
                                                                onclick="return confirm('Are you sure you want to delete this inquiry?');">
                                                                <i class="fa fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">No inquiries found.</td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <nav aria-label="Page navigation">
                                    <ul class="pagination mt-3">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" tabindex="-1"
                                                aria-disabled="true">Previous</a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                                        <li class="page-item">
                                            <a class="page-link" href="#">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <!-- JavaScript for enhanced functionality -->
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function () {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Status filter functionality
        document.getElementById('statusFilter').addEventListener('change', function () {
            const filterValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (filterValue === '') {
                    row.style.display = '';
                    return;
                }

                const status = row.querySelector('.status-badge').textContent.toLowerCase();
                row.style.display = status.includes(filterValue) ? '' : 'none';
            });
        });

        // Confirm before delete
        function confirmDelete() {
            return confirm('Are you sure you want to delete this inquiry?');
        }
    </script>
</body>

</html>