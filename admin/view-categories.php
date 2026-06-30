<?php
include "functions.php";
?>
<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Department Management | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    
    <style>
        /* Custom styles for department management */
        .badge_1 {
            background: #2ecc71;
            color: #fff;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge_2 {
            background: #e74c3c;
            color: #fff;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .btn-outline-primary {
            border: 1px solid #3498db;
            background: transparent;
            color: #3498db;
            transition: all 0.3s;
        }
        .btn-outline-primary:hover {
            background: #3498db;
            color: #fff;
            border-color: #3498db;
        }
        .btn-outline-danger {
            border: 1px solid #e74c3c;
            background: transparent;
            color: #e74c3c;
            transition: all 0.3s;
        }
        .btn-outline-danger:hover {
            background: #e74c3c;
            color: #fff;
            border-color: #e74c3c;
        }
        .rounded-circle.p-2 {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .rounded-circle.p-2 i {
            font-size: 12px;
        }
        .table tbody tr {
            transition: background 0.2s;
        }
        .table tbody tr:hover {
            background: #f8f9ff;
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
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h3 class="mb-0 fw-bold">Service Category Management</h3>
                                        <p class="text-muted mb-0 small">Manage medical departments for doctor categorization</p>
                                    </div>
                                    <div class="mt-2 mt-sm-0">
                                        <a href="add-categories.php" class="btn_1">
                                            <i class="fas fa-plus me-2"></i>Add New Service
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="QA_section">
                                    <div class="QA_table mb_30">
                                        <div class="table-responsive">
                                            <table class="table lms_table_active table-bordered table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th scope="col" width="50">#</th>
                                                        <th scope="col">Category ID</th>
                                                        <th scope="col">Department Name</th>
                                                        <th scope="col">Slug URL</th>
                                                        <th scope="col" width="100">Status</th>
                                                        <th scope="col" width="120">Added On</th>
                                                        <th scope="col" width="100">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php echo get_Category(); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        // Optional: Add search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('#searchInput');
            if(searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const value = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.lms_table_active tbody tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(value) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>