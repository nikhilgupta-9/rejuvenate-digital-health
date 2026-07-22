<?php
include "functions.php";

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$bulk_message = '';
$bulk_message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $bulk_message = 'Security check failed. Please try again.';
        $bulk_message_type = 'danger';
    } else {
        $selected_ids = array_values(array_unique(array_filter(
            array_map('intval', $_POST['selected_ids'] ?? []),
            fn($id) => $id > 0
        )));

        if (empty($selected_ids)) {
            $bulk_message = 'No departments were selected.';
            $bulk_message_type = 'warning';
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $count = count($selected_ids);

            switch ($_POST['bulk_action']) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE categories SET status = 1 WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$selected_ids);
                    $stmt->execute();
                    $bulk_message = "$count department(s) activated.";
                    $bulk_message_type = 'success';
                    break;

                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE categories SET status = 0 WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$selected_ids);
                    $stmt->execute();
                    $bulk_message = "$count department(s) deactivated.";
                    $bulk_message_type = 'success';
                    break;

                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$selected_ids);
                    $stmt->execute();
                    $bulk_message = "$count department(s) deleted.";
                    $bulk_message_type = 'success';
                    break;

                default:
                    $bulk_message = 'Unknown bulk action requested.';
                    $bulk_message_type = 'danger';
            }
        }
    }
}
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
        .table tbody tr.row-selected {
            background: #eef4ff;
        }
        .bulk-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 12px 16px;
            background: #f8f9ff;
            border: 1px solid #e5e9f7;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .bulk-toolbar .selected-count {
            font-weight: 600;
            color: #3498db;
        }
        #searchInput {
            max-width: 280px;
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
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h3 class="mb-0 fw-bold">Service Category Management</h3>
                                        <p class="text-muted mb-0 small">Manage medical departments for doctor categorization</p>
                                    </div>
                                    <div class="mt-2 mt-sm-0 d-flex align-items-center gap-2">
                                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search departments...">
                                        <a href="add-categories.php" class="btn_1">
                                            <i class="fas fa-plus me-2"></i>Add New Service
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">

                                <?php if ($bulk_message): ?>
                                    <div class="alert alert-<?= htmlspecialchars($bulk_message_type) ?> alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($bulk_message) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" id="bulkActionForm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="bulk_action" id="bulkActionInput" value="">

                                    <div class="bulk-toolbar">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="selectAllToolbar">
                                            <label class="form-check-label" for="selectAllToolbar">Select All</label>
                                        </div>
                                        <span class="selected-count"><span id="selectedCount">0</span> selected</span>

                                        <div class="btn-group ms-auto" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-success bulk-btn" data-action="activate" disabled>
                                                <i class="fas fa-check-circle me-1"></i>Activate
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary bulk-btn" data-action="deactivate" disabled>
                                                <i class="fas fa-ban me-1"></i>Deactivate
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger bulk-btn" data-action="delete" disabled>
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
                                    </div>

                                    <div class="QA_section">
                                        <div class="QA_table mb_30">
                                            <div class="table-responsive">
                                                <table class="table lms_table_active table-bordered table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th scope="col" width="40">
                                                                <input type="checkbox" class="form-check-input" id="selectAllHeader">
                                                            </th>
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
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <script>
        // The shared admin footer initializes `.lms_table_active` with
        // searching:false, which disables DataTables' filtering outright (not
        // just its search box UI) — so `.search()` on that instance is a no-op.
        // Re-init this one table with searching enabled so our own search box
        // can drive it, and hide the redundant auto-generated filter box it adds.
        document.addEventListener('DOMContentLoaded', function() {
            const dataTable = $('.lms_table_active').DataTable({
                destroy: true,
                bLengthChange: false,
                responsive: true,
                searching: true,
            });
            $('.dataTables_filter').hide();

            const searchInput = document.querySelector('#searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    dataTable.search(this.value).draw();
                });
            }

            // Tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

            // Bulk selection
            const rowCheckboxes = () => Array.from(document.querySelectorAll('.row-checkbox'));
            const selectAllHeader = document.getElementById('selectAllHeader');
            const selectAllToolbar = document.getElementById('selectAllToolbar');
            const selectedCountEl = document.getElementById('selectedCount');
            const bulkButtons = document.querySelectorAll('.bulk-btn');
            const bulkActionForm = document.getElementById('bulkActionForm');
            const bulkActionInput = document.getElementById('bulkActionInput');

            function updateToolbar() {
                const checked = rowCheckboxes().filter(cb => cb.checked);
                selectedCountEl.textContent = checked.length;
                bulkButtons.forEach(btn => btn.disabled = checked.length === 0);

                rowCheckboxes().forEach(cb => {
                    cb.closest('tr').classList.toggle('row-selected', cb.checked);
                });

                const all = rowCheckboxes();
                const allChecked = all.length > 0 && all.every(cb => cb.checked);
                selectAllHeader.checked = allChecked;
                selectAllToolbar.checked = allChecked;
            }

            function toggleAll(checked) {
                rowCheckboxes().forEach(cb => cb.checked = checked);
                updateToolbar();
            }

            selectAllHeader?.addEventListener('change', () => toggleAll(selectAllHeader.checked));
            selectAllToolbar?.addEventListener('change', () => toggleAll(selectAllToolbar.checked));

            document.querySelector('tbody')?.addEventListener('change', function(e) {
                if (e.target.classList.contains('row-checkbox')) updateToolbar();
            });

            bulkButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const checked = rowCheckboxes().filter(cb => cb.checked);
                    if (checked.length === 0) return;

                    const messages = {
                        activate: `Activate ${checked.length} selected department(s)?`,
                        deactivate: `Deactivate ${checked.length} selected department(s)?`,
                        delete: `Delete ${checked.length} selected department(s)? This cannot be undone.`,
                    };
                    if (!confirm(messages[action] || 'Apply this action to the selected departments?')) return;

                    bulkActionInput.value = action;
                    bulkActionForm.submit();
                });
            });

            // Rows get swapped in/out by DataTables on page/search changes, but
            // the <tbody> element itself persists, so the delegated 'change'
            // listener above keeps working — just refresh the toolbar's counts.
            dataTable.on('draw', updateToolbar);

            updateToolbar();
        });
    </script>
</body>
</html>
