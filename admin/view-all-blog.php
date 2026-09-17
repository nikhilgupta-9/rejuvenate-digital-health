<?php
require_once __DIR__ . '/auth/bootstrap.php';
require_once __DIR__ . '/../lib/Security.php';

$csrf = Security::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? '')) {
        $_SESSION['delete_message'] = [
            'status' => 'danger',
            'message' => 'Security token expired. Please try again.'
        ];
    } else {
        $delete_id = (int) $_POST['delete_id'];
        $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = ?");
        $stmt->bind_param('i', $delete_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $del = $conn->prepare("DELETE FROM blogs WHERE id = ?");
            $del->bind_param('i', $delete_id);
            if ($del->execute()) {
                if (!empty($row['image']) && file_exists(__DIR__ . '/uploads/blogs/' . $row['image'])) {
                    @unlink(__DIR__ . '/uploads/blogs/' . $row['image']);
                }
                $_SESSION['delete_message'] = ['status' => 'success', 'message' => 'Blog post deleted successfully!'];
            } else {
                error_log('[admin/view-all-blog] DB error: ' . $conn->error);
                $_SESSION['delete_message'] = ['status' => 'danger', 'message' => 'Could not delete the blog post. Please try again.'];
            }
        } else {
            $_SESSION['delete_message'] = ['status' => 'danger', 'message' => 'Blog post not found.'];
        }
    }

    header("Location: view-all-blog.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$status_filter = in_array($_GET['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_GET['status'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = "(title LIKE ? OR author LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($status_filter !== '') {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM blogs $whereSql");
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total / $per_page));

$listStmt = $conn->prepare("SELECT * FROM blogs $whereSql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$listParams = array_merge($params, [$per_page, $offset]);
$listStmt->bind_param($types . 'ii', ...$listParams);
$listStmt->execute();
$result = $listStmt->get_result();

$status_badges = [
    'published' => 'success',
    'draft'     => 'secondary',
    'archived'  => 'dark',
];
?>

<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Blog Management</title>
    <?php include "links.php"; ?>
    <style>
        .table-container {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        .table td,
        .table th {
            padding: 12px;
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
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
                <?php
                if (isset($_SESSION['delete_message'])) {
                    $alert_status = $_SESSION['delete_message']['status'];
                    $alert_message = $_SESSION['delete_message']['message'];
                    ?>
                    <div class="container mt-3">
                        <div class="alert alert-<?php echo $alert_status; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($alert_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['delete_message']);
                }
                if (isset($_SESSION['success'])) {
                    ?>
                    <div class="container mt-3">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <h2 class="mb-0 fw-bold">Blog Management</h2>
                                        <p class="text-muted mb-0 small"><?= $total ?> post<?= $total !== 1 ? 's' : '' ?> total</p>
                                    </div>
                                    <div>
                                        <a href="add-blog.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add New Blog
                                        </a>
                                    </div>
                                </div>
                                <form method="GET" class="row g-2 mt-3">
                                    <div class="col-md-5">
                                        <input type="text" name="search" class="form-control" placeholder="Search by title or author…" value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <select name="status" class="form-control">
                                            <option value="">All statuses</option>
                                            <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>>Published</option>
                                            <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                                    </div>
                                    <?php if ($search !== '' || $status_filter !== ''): ?>
                                        <div class="col-md-2">
                                            <a href="view-all-blog.php" class="btn btn-outline-secondary w-100">Clear</a>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="white_card_body">
                                <div class="QA_section">
                                    <div class="QA_table mb_30">
                                        <div class="table-responsive">
                                            <table
                                                class="table table-striped table-bordered text-center lms_table_active">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Date</th>
                                                        <th>Title</th>
                                                        <th>Category</th>
                                                        <th>Status</th>
                                                        <th>Image</th>
                                                        <th>Edit</th>
                                                        <th>Delete</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if ($result->num_rows === 0): ?>
                                                        <tr><td colspan="8" class="text-muted py-4">No blog posts found.</td></tr>
                                                    <?php endif; ?>
                                                    <?php
                                                    $sno = $offset + 1;
                                                    while ($row = $result->fetch_assoc()) {
                                                        $badge = $status_badges[$row['status']] ?? 'secondary';
                                                        ?>
                                                        <tr>
                                                            <td><?= $sno++ ?></td>
                                                            <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                                                            </td>
                                                            <td class="text-start"><?= htmlspecialchars($row['title']) ?></td>
                                                            <td><?= htmlspecialchars($row['category'] ?: '—') ?></td>
                                                            <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($row['status']) ?></span></td>
                                                            <td>
                                                                <?php if (!empty($row['image'])): ?>
                                                                    <img src="uploads/blogs/<?= htmlspecialchars($row['image']) ?>"
                                                                        style="max-width:100px;" class="img-thumbnail">
                                                                <?php else: ?>
                                                                    <span class="text-muted small">No image</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <a href="edit-blog.php?id=<?= $row['id'] ?>"
                                                                    class="btn btn-sm btn-outline-info">
                                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this blog?');" class="d-inline">
                                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        <i class="fa-solid fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <?php if ($total_pages > 1): ?>
                                            <?php
                                            $qs = '';
                                            if ($search !== '') $qs .= '&search=' . urlencode($search);
                                            if ($status_filter !== '') $qs .= '&status=' . urlencode($status_filter);
                                            ?>
                                            <nav>
                                                <ul class="pagination justify-content-center mb-0">
                                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $qs ?>">Previous</a>
                                                    </li>
                                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                            <a class="page-link" href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $qs ?>">Next</a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12"></div>
                </div>
            </div>
        </div>
    </section>
    <?php include "footer.php"; ?>

</body>

</html>
