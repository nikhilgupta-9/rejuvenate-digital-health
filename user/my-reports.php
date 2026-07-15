<?php
include_once "../config/connect.php";
include_once "../util/function.php";

// session_start(); // Uncomment if not already started
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}


$contact = contact_us();
$user_id = $_SESSION['user_id'];

$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['name'] ?? 'Patient';

// Get patient details
$patient_sql = "SELECT * FROM users WHERE id = ?";
$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param('i', $patient_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();
$patient_data = $patient_result->fetch_assoc();

$patient_profile_image = !empty($patient_data['profile_pic']) ? $patient_data['profile_pic'] : 'assets/img/dummy.png';

// Handle document search and filter
$search_query = $_GET['search'] ?? '';
$doc_type_filter = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for documents
$where_conditions = ["patient_id = ?"];
$params = [$patient_id];
$types = "i";

// Add search filter
if (!empty($search_query)) {
    $where_conditions[] = "(document_name LIKE ? OR description LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add document type filter
if ($doc_type_filter != 'all' && !empty($doc_type_filter)) {
    $where_conditions[] = "document_type = ?";
    $params[] = $doc_type_filter;
    $types .= "s";
}

// Add date range filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(uploaded_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(uploaded_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get documents with doctor information
$documents_sql = "
    SELECT 
        pd.*,
        d.name as doctor_name,
        d.specialization,
        DATE_FORMAT(pd.uploaded_at, '%d/%m/%Y %h:%i %p') as formatted_date,
        DATE(pd.uploaded_at) as upload_date
    FROM patient_documents pd
    LEFT JOIN doctors d ON pd.doctor_id = d.id
    $where_sql
    ORDER BY pd.uploaded_at DESC
";

$documents_stmt = $conn->prepare($documents_sql);

if (!empty($params)) {
    $documents_stmt->bind_param($types, ...$params);
}

$documents_stmt->execute();
$documents_result = $documents_stmt->get_result();

// Get document statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_documents,
        COUNT(DISTINCT doctor_id) as total_doctors,
        GROUP_CONCAT(DISTINCT file_type) as doc_types
    FROM patient_documents 
    WHERE patient_id = ?
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $patient_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get distinct document types for filter dropdown
$types_sql = "SELECT DISTINCT file_type FROM patient_documents WHERE patient_id = ? AND file_type IS NOT NULL ORDER BY file_type";
$types_stmt = $conn->prepare($types_sql);
$types_stmt->bind_param('i', $patient_id);
$types_stmt->execute();
$types_result = $types_stmt->get_result();

// Get recent doctors who uploaded documents
$doctors_sql = "
    SELECT DISTINCT d.id, d.name, d.specialization, MAX(pd.uploaded_at) as last_upload
    FROM patient_documents pd
    JOIN doctors d ON pd.doctor_id = d.id
    WHERE pd.patient_id = ?
    GROUP BY d.id, d.name, d.specialization
    ORDER BY last_upload DESC
    LIMIT 5
";

$doctors_stmt = $conn->prepare($doctors_sql);
$doctors_stmt->bind_param('i', $patient_id);
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();

$contact = contact_us();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>My Medical Reports | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        .user_dash_box img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .user_dash_box img:hover {
            transform: scale(1.05);
        }

        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }

        .stats-card h3 {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }

        .document-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
        }

        .document-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .document-icon {
            font-size: 40px;
            color: #02c9b8;
            margin-bottom: 15px;
        }

        .doctor-badge {
            background: #e9f7fe;
            color: #31708f;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            height: fit-content;
        }

        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #02c9b8;
            color: white;
            padding-left: 20px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #02c9b8;
            margin-bottom: 15px;
        }

        .menu-btn {
            display: none;
            background: #02c9b8;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .empty-state-icon {
            font-size: 60px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .badge-type {
            background: #02c9b8;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
        }

        .file-size {
            font-size: 12px;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                height: 100vh;
                z-index: 1000;
                overflow-y: auto;
                background: white;
            }

            .sidebar.show {
                display: block;
            }

            .menu-btn {
                display: block;
            }

            .document-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <?php $sidebar_active = 'reports';
    include("sidebar.php"); ?>
    <main class="patient-content">
        <!-- Page Header -->
        <div class="profile-card shadow mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">My Medical Reports</h5>
                    <p class="text-muted">Access all your medical documents and reports in one place</p>
                </div>
                <div class="text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="fa fa-question-circle me-1"></i> Help
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <h3><?= $stats['total_documents'] ?? 0 ?></h3>
                    <p>Total Documents</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?= $stats['total_doctors'] ?? 0 ?></h3>
                    <p>Doctors</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3><?= $documents_result->num_rows ?></h3>
                    <p>Showing</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h3><?= $types_result->num_rows ?></h3>
                    <p>Document Types</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Documents</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by document name or description..."
                            value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Document Type</label>
                    <select name="type" class="form-select">
                        <option value="all" <?= $doc_type_filter == 'all' ? 'selected' : '' ?>>All Types</option>
                        <?php
                        $types_result->data_seek(0); // Reset pointer
                        while ($type_row = $types_result->fetch_assoc()):
                            if (!empty($type_row['document_type'])):
                                ?>
                                <option value="<?= $type_row['document_type'] ?>"
                                    <?= $doc_type_filter == $type_row['document_type'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type_row['document_type']) ?>
                                </option>
                                <?php
                            endif;
                        endwhile;
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="input-group">
                        <input type="date" name="date_from" class="form-control"
                            value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>"
                            placeholder="To">
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fa fa-filter me-1"></i> Filter
                    </button>
                    <a href="my-reports.php" class="btn btn-secondary">
                        <i class="fa fa-refresh"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Recent Doctors -->
        <?php if ($doctors_result->num_rows > 0): ?>
            <div class="profile-card shadow mb-4">
                <h5 class="mb-3"><i class="fa fa-user-md me-2"></i> Doctors Who Uploaded Reports</h5>
                <div class="row">
                    <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="doctor-badge mb-2">
                                    <?= htmlspecialchars($doctor['name']) ?>
                                </div>
                                <small class="text-muted"><?= $doctor['specialization'] ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Documents List -->
        <div class="profile-card shadow">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h5 class="mb-0">
                    <i class="fa fa-files-o me-2"></i>
                    My Medical Documents
                    <?php if ($search_query || $doc_type_filter != 'all' || $date_from || $date_to): ?>
                        <span class="badge bg-info ms-2">Filtered</span>
                    <?php endif; ?>
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="printReports()">
                        <i class="fa fa-print"></i> <span class="d-none d-sm-inline">Print List</span>
                    </button>
                    <button class="btn btn-outline-success" onclick="exportToCSV()">
                        <i class="fa fa-download"></i> <span class="d-none d-sm-inline">Export</span>
                    </button>
                </div>
            </div>

            <?php if ($documents_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="documentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Document Name</th>
                                <th class="d-none d-md-table-cell">Type</th>
                                <th class="d-none d-lg-table-cell">Doctor</th>
                                <th class="d-none d-sm-table-cell">Date</th>
                                <th class="d-none d-md-table-cell">Size</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $counter = 1;
                            $documents_result->data_seek(0);
                            while ($document = $documents_result->fetch_assoc()):
                                // Get file extension
                                $file_ext = pathinfo($document['document_name'], PATHINFO_EXTENSION);

                                // Get file size with error handling
                                $file_path = $document['file_path'];
                                if (file_exists($file_path) && is_file($file_path)) {
                                    $file_size_bytes = filesize($file_path);
                                    if ($file_size_bytes > 1024 * 1024) {
                                        $file_size = round($file_size_bytes / (1024 * 1024), 2) . ' MB';
                                    } elseif ($file_size_bytes > 1024) {
                                        $file_size = round($file_size_bytes / 1024, 2) . ' KB';
                                    } else {
                                        $file_size = $file_size_bytes . ' B';
                                    }
                                } else {
                                    $file_size = 'Unknown';
                                }

                                // Get icon based on file type
                                $icon_class = 'fa-file-o';
                                $icon_color = '#02c9b8';

                                if ($file_ext == 'pdf') {
                                    $icon_class = 'fa-file-pdf-o';
                                    $icon_color = '#e74c3c';
                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                    $icon_class = 'fa-file-word-o';
                                    $icon_color = '#2c5aa0';
                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                    $icon_class = 'fa-file-image-o';
                                    $icon_color = '#f39c12';
                                } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                    $icon_class = 'fa-file-excel-o';
                                    $icon_color = '#27ae60';
                                }
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fa <?= $icon_class ?> me-2"
                                                style="color: <?= $icon_color ?>; font-size: 1.2rem;"></i>
                                            <span class="text-truncate" style="max-width: 150px;"
                                                title="<?= htmlspecialchars($document['document_name']) ?>">
                                                <?= htmlspecialchars($document['document_name']) ?>
                                            </span>
                                            <?php if ($document['file_type']): ?>
                                                <span
                                                    class="badge badge-type ms-2 d-none d-md-inline"><?= $document['file_type'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php if ($document['file_type']): ?>
                                            <?= $document['file_type'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($document['doctor_name']): ?>
                                            Dr. <?= htmlspecialchars($document['doctor_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-sm-table-cell">
                                        <?= $document['formatted_date'] ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?= $file_size ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="../<?= $document['file_path'] ?>" class="btn btn-primary" target="_blank"
                                                download>
                                                <i class="fa fa-download"></i>
                                            </a>
                                            <a href="../<?= $document['file_path'] ?>" class="btn btn-info" target="_blank">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary"
                                                onclick="shareDocument('<?= htmlspecialchars($document['document_name']) ?>', '<?= $document['file_path'] ?>')">
                                                <i class="fa fa-share"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top gap-2">
                    <div class="text-muted small">
                        Showing <?= $documents_result->num_rows ?> document(s)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Previous</a>
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

            <?php else: ?>
                <div class="empty-state text-center py-5">
                    <div class="empty-state-icon mb-3">
                        <i class="fa fa-files-o fa-4x text-muted"></i>
                    </div>
                    <h4>No Documents Found</h4>
                    <p class="text-muted mb-4">
                        <?php if ($search_query || $doc_type_filter != 'all' || $date_from || $date_to): ?>
                            No documents match your search criteria.
                            <?php if ($search_query): ?>
                                <br><strong>Search:</strong> "<?= htmlspecialchars($search_query) ?>"
                            <?php endif; ?>
                        <?php else: ?>
                            You don't have any medical reports uploaded yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($search_query || $doc_type_filter != 'all' || $date_from || $date_to): ?>
                        <a href="my-reports.php" class="btn btn-primary">
                            <i class="fa fa-undo me-2"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include("inc/scripts.php") ?>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-question-circle me-2"></i> Medical Reports Help</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>About Medical Reports</h6>
                    <p>This section contains all your medical documents uploaded by doctors. You can:</p>
                    <ul>
                        <li>View and download your medical reports</li>
                        <li>Filter documents by type, date, or search</li>
                        <li>See which doctor uploaded each document</li>
                        <li>Print or export your document list</li>
                    </ul>
                    <h6>Supported File Types</h6>
                    <p>PDF, Word, Excel, Images (JPG, PNG)</p>
                    <h6>Need Help?</h6>
                    <p>Contact support if you have issues accessing your documents.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function printReports() {
            const printContent = document.getElementById('documentsGrid').innerHTML;
            const originalContent = document.body.innerHTML;

            document.body.innerHTML = `
                <html>
                    <head>
                        <title>Medical Reports - <?= htmlspecialchars($patient_name) ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            .document-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
                            .badge-type { background: #02c9b8; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; }
                            .doctor-badge { background: #e9f7fe; padding: 5px; border-radius: 3px; margin: 5px 0; }
                            h1 { color: #333; }
                            .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #02c9b8; padding-bottom: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h1>Medical Reports</h1>
                            <p>Patient: <?= htmlspecialchars($patient_name) ?></p>
                            <p>Printed on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        ${printContent}
                    </body>
                </html>
            `;

            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }

        function exportToCSV() {
            const rows = [];
            const headers = ['Document Name', 'Type', 'Doctor', 'Specialization', 'Upload Date', 'Description'];
            rows.push(headers.join(','));

            document.querySelectorAll('.document-card').forEach(card => {
                const name = card.querySelector('h6').textContent.trim();
                const type = card.querySelector('.badge-type')?.textContent.trim() || '';
                const doctor = card.querySelector('.doctor-badge')?.textContent.split('\n')[0].trim() || '';
                const specialization = card.querySelector('.doctor-badge small')?.textContent.trim() || '';
                const date = card.querySelector('small.text-muted').textContent.replace('📅 ', '').trim();
                const description = card.querySelector('p.small')?.textContent.trim() || '';

                const row = [
                    `"${name}"`,
                    `"${type}"`,
                    `"${doctor}"`,
                    `"${specialization}"`,
                    `"${date}"`,
                    `"${description}"`
                ];
                rows.push(row.join(','));
            });

            const csvContent = rows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', `medical-reports-${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function shareDocument(name, url) {
            if (navigator.share) {
                navigator.share({
                    title: name,
                    text: 'Check out this medical document',
                    url: url
                });
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Document link copied to clipboard!');
                });
            }
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>