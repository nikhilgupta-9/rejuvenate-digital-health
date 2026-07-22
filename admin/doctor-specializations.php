<?php
include "functions.php";

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function slugify_specialization(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

$page_message = '';
$page_message_type = '';

// Add / Edit a single specialization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_specialization'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $spec_id = intval($_POST['spec_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($name === '') {
            $page_message = 'Specialization name is required.';
            $page_message_type = 'warning';
        } else {
            $slug = slugify_specialization($name);

            if ($spec_id > 0) {
                $stmt = $conn->prepare("UPDATE specializations SET name = ?, slug_url = ?, description = ?, status = ? WHERE id = ?");
                $stmt->bind_param('sssii', $name, $slug, $description, $status, $spec_id);
                if ($stmt->execute()) {
                    $page_message = 'Specialization updated successfully.';
                    $page_message_type = 'success';
                } else {
                    $page_message = $conn->errno === 1062
                        ? 'Another specialization with a matching name/slug already exists.'
                        : 'Failed to update specialization.';
                    $page_message_type = 'danger';
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO specializations (name, slug_url, description, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('sssi', $name, $slug, $description, $status);
                if ($stmt->execute()) {
                    $page_message = 'Specialization added successfully.';
                    $page_message_type = 'success';
                } else {
                    $page_message = $conn->errno === 1062
                        ? 'A specialization with that name already exists.'
                        : 'Failed to add specialization.';
                    $page_message_type = 'danger';
                }
            }
        }
    }
}

// Bulk activate / deactivate / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $selected_ids = array_values(array_unique(array_filter(
            array_map('intval', $_POST['selected_ids'] ?? []),
            fn($id) => $id > 0
        )));

        if (empty($selected_ids)) {
            $page_message = 'No specializations were selected.';
            $page_message_type = 'warning';
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $count = count($selected_ids);

            switch ($_POST['bulk_action']) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE specializations SET status = 1 WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$selected_ids);
                    $stmt->execute();
                    $page_message = "$count specialization(s) activated.";
                    $page_message_type = 'success';
                    break;

                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE specializations SET status = 0 WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$selected_ids);
                    $stmt->execute();
                    $page_message = "$count specialization(s) deactivated.";
                    $page_message_type = 'success';
                    break;

                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM specializations WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$selected_ids);
                    $stmt->execute();
                    $page_message = "$count specialization(s) deleted.";
                    $page_message_type = 'success';
                    break;

                default:
                    $page_message = 'Unknown bulk action requested.';
                    $page_message_type = 'danger';
            }
        }
    }
}

// Live count of doctors currently tagged with each specialization — doctors.specialization
// is free text (often comma-separated, inconsistent casing), so this is a substring match
// against real doctor records, not a hardcoded number.
$specializations = [];
$result = $conn->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM doctors d WHERE d.specialization LIKE CONCAT('%', s.name, '%')) AS doctor_count
    FROM specializations s
    ORDER BY s.id DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $specializations[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Specializations | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>

    <style>
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
        .badge_3 {
            background: #eef4ff;
            color: #3498db;
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
                                        <h3 class="mb-0 fw-bold">Specializations</h3>
                                        <p class="text-muted mb-0 small">Canonical list of medical specialties doctors can be tagged with</p>
                                    </div>
                                    <div class="mt-2 mt-sm-0 d-flex align-items-center gap-2">
                                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search specializations...">
                                        <button type="button" class="btn_1" data-bs-toggle="modal" data-bs-target="#specModal" onclick="openAddModal()">
                                            <i class="fas fa-plus me-2"></i>Add Specialization
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">

                                <?php if ($page_message): ?>
                                    <div class="alert alert-<?= htmlspecialchars($page_message_type) ?> alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($page_message) ?>
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
                                                            <th scope="col">Name</th>
                                                            <th scope="col">Slug URL</th>
                                                            <th scope="col" width="110">Doctors</th>
                                                            <th scope="col" width="100">Status</th>
                                                            <th scope="col" width="120">Added On</th>
                                                            <th scope="col" width="100">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($specializations)): ?>
                                                            <tr>
                                                                <td colspan="8" class="text-center py-4">
                                                                    <div class="d-flex flex-column align-items-center">
                                                                        <i class="fas fa-stethoscope fs-1 text-muted mb-2"></i>
                                                                        <span class="text-muted">No specializations found. Add your first one above.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php else: $sno = 1; foreach ($specializations as $spec): ?>
                                                            <tr>
                                                                <td class="text-center">
                                                                    <input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= (int) $spec['id'] ?>">
                                                                </td>
                                                                <td class="text-center"><?= $sno++ ?></td>
                                                                <td class="fw-semibold"><?= htmlspecialchars($spec['name']) ?></td>
                                                                <td><span class="text-muted"><?= htmlspecialchars($spec['slug_url']) ?></span></td>
                                                                <td class="text-center">
                                                                    <span class="badge_3"><?= (int) $spec['doctor_count'] ?> doctor<?= $spec['doctor_count'] == 1 ? '' : 's' ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?= $spec['status'] == 1 ? '<span class="badge_1">Active</span>' : '<span class="badge_2">Inactive</span>' ?>
                                                                </td>
                                                                <td class="text-center"><?= date('d M Y', strtotime($spec['added_on'])) ?></td>
                                                                <td class="text-center">
                                                                    <div class="d-flex justify-content-center gap-2">
                                                                        <button type="button"
                                                                            class="btn btn-sm btn-outline-primary rounded-circle p-2 edit-spec-btn"
                                                                            data-bs-toggle="modal" data-bs-target="#specModal"
                                                                            data-id="<?= (int) $spec['id'] ?>"
                                                                            data-name="<?= htmlspecialchars($spec['name'], ENT_QUOTES) ?>"
                                                                            data-description="<?= htmlspecialchars($spec['description'] ?? '', ENT_QUOTES) ?>"
                                                                            data-status="<?= (int) $spec['status'] ?>"
                                                                            title="Edit">
                                                                            <i class="fas fa-pen fs-6"></i>
                                                                        </button>
                                                                        <button type="button"
                                                                            class="btn btn-sm btn-outline-danger rounded-circle p-2 delete-spec-btn"
                                                                            data-id="<?= (int) $spec['id'] ?>"
                                                                            data-name="<?= htmlspecialchars($spec['name'], ENT_QUOTES) ?>"
                                                                            title="Delete">
                                                                            <i class="fas fa-trash fs-6"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; endif; ?>
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

    <!-- Add / Edit Modal -->
    <div class="modal fade" id="specModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="specForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="save_specialization" value="1">
                    <input type="hidden" name="spec_id" id="spec_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="specModalTitle">Add Specialization</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="spec_name" class="form-control" required placeholder="e.g. Cardiology">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="spec_description" class="form-control" rows="3" placeholder="Optional short description"></textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" id="spec_status" checked>
                            <label class="form-check-label" for="spec_status">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden form used to submit a single-row delete through the same bulk-delete path -->
    <form method="POST" id="singleDeleteForm" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="bulk_action" value="delete">
        <input type="hidden" name="selected_ids[]" id="singleDeleteId" value="">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('specModalTitle').textContent = 'Add Specialization';
            document.getElementById('spec_id').value = '';
            document.getElementById('spec_name').value = '';
            document.getElementById('spec_description').value = '';
            document.getElementById('spec_status').checked = true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // The shared admin footer initializes `.lms_table_active` with
            // searching:false, which disables DataTables' filtering outright (not
            // just its search box UI) — so `.search()` on that instance is a no-op.
            // Re-init this one table with searching enabled so our own search box
            // can drive it, and hide the redundant auto-generated filter box it adds.
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

            // Edit modal population
            document.querySelectorAll('.edit-spec-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('specModalTitle').textContent = 'Edit Specialization';
                    document.getElementById('spec_id').value = this.dataset.id;
                    document.getElementById('spec_name').value = this.dataset.name;
                    document.getElementById('spec_description').value = this.dataset.description;
                    document.getElementById('spec_status').checked = this.dataset.status === '1';
                });
            });

            // Single-row delete
            document.querySelectorAll('.delete-spec-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!confirm(`Delete "${this.dataset.name}"? This cannot be undone.`)) return;
                    document.getElementById('singleDeleteId').value = this.dataset.id;
                    document.getElementById('singleDeleteForm').submit();
                });
            });

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

            // Rows get swapped in/out by DataTables on page/search changes, but
            // the <tbody> element itself persists, so the delegated 'change'
            // listener above keeps working — just refresh the toolbar's counts.
            dataTable.on('draw', updateToolbar);

            bulkButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const checked = rowCheckboxes().filter(cb => cb.checked);
                    if (checked.length === 0) return;

                    const messages = {
                        activate: `Activate ${checked.length} selected specialization(s)?`,
                        deactivate: `Deactivate ${checked.length} selected specialization(s)?`,
                        delete: `Delete ${checked.length} selected specialization(s)? This cannot be undone.`,
                    };
                    if (!confirm(messages[action] || 'Apply this action to the selected specializations?')) return;

                    bulkActionInput.value = action;
                    bulkActionForm.submit();
                });
            });

            updateToolbar();
        });
    </script>
</body>
</html>
