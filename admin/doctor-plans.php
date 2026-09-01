<?php
include "functions.php"; // includes db-conn.php + enforces admin_jwt_guard()

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_message = '';
$page_message_type = '';

function _dp_cycle_label(int $days): string
{
    return [30 => 'Monthly', 90 => 'Quarterly', 180 => '6 Months', 365 => 'Yearly'][$days] ?? ($days . ' days');
}

/* ── Add / Edit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $plan_id   = (int)($_POST['plan_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $tagline   = trim($_POST['tagline'] ?? '');
        $price     = (float)($_POST['price'] ?? 0);
        $cycle     = (int)($_POST['billing_cycle_days'] ?? 30);
        $features  = trim($_POST['features'] ?? '');
        $emin      = ($_POST['est_patients_min'] ?? '') === '' ? null : max(0, (int)$_POST['est_patients_min']);
        $emax      = ($_POST['est_patients_max'] ?? '') === '' ? null : max(0, (int)$_POST['est_patients_max']);
        $sort      = (int)($_POST['sort_order'] ?? 0);
        $highlight = isset($_POST['is_highlighted']) ? 1 : 0;
        $active    = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $page_message = 'Plan name is required.';
            $page_message_type = 'warning';
        } elseif ($price < 0) {
            $page_message = 'Price cannot be negative.';
            $page_message_type = 'warning';
        } elseif ($cycle < 1) {
            $page_message = 'Billing cycle must be at least 1 day.';
            $page_message_type = 'warning';
        } elseif ($emin !== null && $emax !== null && $emin > $emax) {
            $page_message = 'Estimated patients: minimum cannot be greater than maximum.';
            $page_message_type = 'warning';
        } else {
            if ($plan_id > 0) {
                $stmt = $conn->prepare("UPDATE doctor_plans SET
                    name=?, tagline=?, price=?, billing_cycle_days=?, features=?,
                    est_patients_min=?, est_patients_max=?, sort_order=?, is_highlighted=?, is_active=?
                    WHERE id=?");
                $stmt->bind_param('ssdisiiiiii', $name, $tagline, $price, $cycle, $features,
                    $emin, $emax, $sort, $highlight, $active, $plan_id);
                if ($stmt->execute()) {
                    $page_message = 'Plan updated successfully.';
                    $page_message_type = 'success';
                } else {
                    $page_message = 'Failed to update plan.';
                    $page_message_type = 'danger';
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO doctor_plans
                    (name, tagline, price, billing_cycle_days, features, est_patients_min, est_patients_max, sort_order, is_highlighted, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssdisiiiii', $name, $tagline, $price, $cycle, $features,
                    $emin, $emax, $sort, $highlight, $active);
                if ($stmt->execute()) {
                    $page_message = 'Plan added successfully.';
                    $page_message_type = 'success';
                } else {
                    $page_message = 'Failed to add plan.';
                    $page_message_type = 'danger';
                }
            }
        }
    }
}

/* ── Bulk activate / deactivate / delete ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $_POST['selected_ids'] ?? []), fn($id) => $id > 0
        )));
        if (empty($ids)) {
            $page_message = 'No plans were selected.';
            $page_message_type = 'warning';
        } else {
            $ph    = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $count = count($ids);

            switch ($_POST['bulk_action']) {
                case 'activate':
                    $s = $conn->prepare("UPDATE doctor_plans SET is_active=1 WHERE id IN ($ph)");
                    $s->bind_param($types, ...$ids); $s->execute();
                    $page_message = "$count plan(s) activated."; $page_message_type = 'success';
                    break;
                case 'deactivate':
                    $s = $conn->prepare("UPDATE doctor_plans SET is_active=0 WHERE id IN ($ph)");
                    $s->bind_param($types, ...$ids); $s->execute();
                    $page_message = "$count plan(s) deactivated."; $page_message_type = 'success';
                    break;
                case 'delete':
                    $ok = 0; $blocked = 0;
                    foreach ($ids as $id) {
                        $d = $conn->prepare("DELETE FROM doctor_plans WHERE id=?");
                        $d->bind_param('i', $id);
                        if ($d->execute()) {
                            $ok++;
                        } elseif ($conn->errno === 1451) {
                            $blocked++;
                        }
                    }
                    if ($ok)      { $page_message = "$ok plan(s) deleted."; $page_message_type = 'success'; }
                    if ($blocked) {
                        $page_message = ($ok ? "$page_message " : '')
                            . "$blocked plan(s) have subscriptions and can't be deleted — deactivate them instead.";
                        $page_message_type = $ok ? 'warning' : 'danger';
                    }
                    break;
                default:
                    $page_message = 'Unknown bulk action.'; $page_message_type = 'danger';
            }
        }
    }
}

/* ── List ── */
$plans = [];
$res = $conn->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM doctor_subscriptions ds
            WHERE ds.plan_id = p.id AND ds.status='paid' AND ds.expires_at > NOW()) AS active_subs
    FROM doctor_plans p
    ORDER BY p.sort_order ASC, p.price ASC, p.id ASC
");
if ($res) { while ($r = $res->fetch_assoc()) $plans[] = $r; }
?>
<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Doctor Subscription Plans | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .badge_1{background:#2ecc71;color:#fff;padding:5px 12px;border-radius:30px;font-size:11px;font-weight:600;}
        .badge_2{background:#e74c3c;color:#fff;padding:5px 12px;border-radius:30px;font-size:11px;font-weight:600;}
        .badge_3{background:#eef4ff;color:#3498db;padding:5px 12px;border-radius:30px;font-size:11px;font-weight:600;}
        .btn-outline-primary{border:1px solid #3498db;background:transparent;color:#3498db;transition:all .3s;}
        .btn-outline-primary:hover{background:#3498db;color:#fff;}
        .btn-outline-danger{border:1px solid #e74c3c;background:transparent;color:#e74c3c;transition:all .3s;}
        .btn-outline-danger:hover{background:#e74c3c;color:#fff;}
        .rounded-circle.p-2{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;}
        .rounded-circle.p-2 i{font-size:12px;}
        .table tbody tr{transition:background .2s;}
        .table tbody tr:hover{background:#f8f9ff;}
        .table tbody tr.row-selected{background:#eef4ff;}
        .bulk-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 16px;background:#f8f9ff;
            border:1px solid #e5e9f7;border-radius:8px;margin-bottom:16px;}
        .bulk-toolbar .selected-count{font-weight:600;color:#3498db;}
        #searchInput{max-width:280px;}
        .cycle-preset.active{background:#3498db;color:#fff;border-color:#3498db;}
    </style>
</head>
<body class="crm_body_bg">

<?php include "header.php"; ?>

<section class="main_content dashboard_part large_header_bg">
    <div class="container-fluid g-0">
        <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
    </div>

    <div class="main_content_iner">
        <div class="container-fluid p-0 sm_padding_15px">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="white_card card_height_100 mb_30">
                        <div class="card-header bg-white border-0 py-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h3 class="mb-0 fw-bold">Doctor Subscription Plans</h3>
                                    <p class="text-muted mb-0 small">Membership plans doctors can pay for &mdash; shown on the Doctor Network &amp; /doctor-plans/ pages</p>
                                </div>
                                <div class="mt-2 mt-sm-0 d-flex align-items-center gap-2">
                                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search plans...">
                                    <button type="button" class="btn_1" data-bs-toggle="modal" data-bs-target="#planModal" onclick="openAddModal()">
                                        <i class="fas fa-plus me-2"></i>Add Plan
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="white_card_body">

                            <?php if ($page_message): ?>
                                <div class="alert alert-<?= htmlspecialchars($page_message_type) ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($page_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                                        <button type="button" class="btn btn-sm btn-outline-success bulk-btn" data-action="activate" disabled><i class="fas fa-check-circle me-1"></i>Activate</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary bulk-btn" data-action="deactivate" disabled><i class="fas fa-ban me-1"></i>Deactivate</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger bulk-btn" data-action="delete" disabled><i class="fas fa-trash me-1"></i>Delete</button>
                                    </div>
                                </div>

                                <div class="QA_section"><div class="QA_table mb_30"><div class="table-responsive">
                                    <table class="table lms_table_active table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="40"><input type="checkbox" class="form-check-input" id="selectAllHeader"></th>
                                                <th width="50">#</th>
                                                <th>Plan</th>
                                                <th width="110">Price</th>
                                                <th width="110">Cycle</th>
                                                <th width="130">Est. Patients</th>
                                                <th width="110">Subscribers</th>
                                                <th width="90">Status</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($plans)): ?>
                                                <tr><td colspan="9" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="fas fa-id-card fs-1 text-muted mb-2"></i>
                                                        <span class="text-muted">No plans yet. Add your first one above.</span>
                                                    </div>
                                                </td></tr>
                                            <?php else: $sno = 1; foreach ($plans as $p): ?>
                                                <tr>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= (int)$p['id'] ?>"></td>
                                                    <td class="text-center"><?= $sno++ ?></td>
                                                    <td>
                                                        <span class="fw-semibold"><?= htmlspecialchars($p['name']) ?></span>
                                                        <?php if ($p['is_highlighted']): ?> <i class="fas fa-star text-warning" title="Highlighted"></i><?php endif; ?>
                                                        <?php if (!empty($p['tagline'])): ?><br><span class="text-muted small"><?= htmlspecialchars($p['tagline']) ?></span><?php endif; ?>
                                                    </td>
                                                    <td class="text-center">&#8377;<?= number_format((float)$p['price']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars(_dp_cycle_label((int)$p['billing_cycle_days'])) ?><br><span class="text-muted small"><?= (int)$p['billing_cycle_days'] ?>d</span></td>
                                                    <td class="text-center">
                                                        <?php if ($p['est_patients_min'] !== null && $p['est_patients_max'] !== null): ?>
                                                            <span class="badge_3"><?= (int)$p['est_patients_min'] ?>&ndash;<?= (int)$p['est_patients_max'] ?>/mo</span>
                                                        <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                                                    </td>
                                                    <td class="text-center"><span class="badge_3"><?= (int)$p['active_subs'] ?> active</span></td>
                                                    <td class="text-center"><?= $p['is_active'] ? '<span class="badge_1">Active</span>' : '<span class="badge_2">Inactive</span>' ?></td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-circle p-2 edit-plan-btn"
                                                                data-bs-toggle="modal" data-bs-target="#planModal"
                                                                data-id="<?= (int)$p['id'] ?>"
                                                                data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                                                data-tagline="<?= htmlspecialchars($p['tagline'] ?? '', ENT_QUOTES) ?>"
                                                                data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                                                                data-cycle="<?= (int)$p['billing_cycle_days'] ?>"
                                                                data-features="<?= htmlspecialchars($p['features'] ?? '', ENT_QUOTES) ?>"
                                                                data-emin="<?= $p['est_patients_min'] === null ? '' : (int)$p['est_patients_min'] ?>"
                                                                data-emax="<?= $p['est_patients_max'] === null ? '' : (int)$p['est_patients_max'] ?>"
                                                                data-sort="<?= (int)$p['sort_order'] ?>"
                                                                data-highlight="<?= (int)$p['is_highlighted'] ?>"
                                                                data-active="<?= (int)$p['is_active'] ?>" title="Edit">
                                                                <i class="fas fa-pen fs-6"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-2 delete-plan-btn"
                                                                data-id="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" title="Delete">
                                                                <i class="fas fa-trash fs-6"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div></div></div>
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
<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="planForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="save_plan" value="1">
                <input type="hidden" name="plan_id" id="plan_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="planModalTitle">Add Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="p_name" class="form-control" required placeholder="e.g. Quarterly Membership">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Price (&#8377;) <span class="text-danger">*</span></label>
                            <input type="number" name="price" id="p_price" class="form-control" min="0" step="1" required placeholder="2499">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tagline</label>
                            <input type="text" name="tagline" id="p_tagline" class="form-control" maxlength="150" placeholder="One-line pitch shown under the plan name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Billing Cycle (days) <span class="text-danger">*</span></label>
                            <input type="number" name="billing_cycle_days" id="p_cycle" class="form-control" min="1" required value="30">
                            <div class="btn-group btn-group-sm mt-2" role="group">
                                <button type="button" class="btn btn-outline-secondary cycle-preset" data-days="30">Monthly</button>
                                <button type="button" class="btn btn-outline-secondary cycle-preset" data-days="90">Quarterly</button>
                                <button type="button" class="btn btn-outline-secondary cycle-preset" data-days="180">6 Months</button>
                                <button type="button" class="btn btn-outline-secondary cycle-preset" data-days="365">Yearly</button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" id="p_sort" class="form-control" value="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_highlighted" id="p_highlight">
                                <label class="form-check-label" for="p_highlight">Highlight</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Features <span class="text-muted small">(one per line)</span></label>
                            <textarea name="features" id="p_features" class="form-control" rows="5" placeholder="Verified public profile&#10;Online + in-clinic bookings&#10;Digital prescriptions"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estimated Patients / month &mdash; min</label>
                            <input type="number" name="est_patients_min" id="p_emin" class="form-control" min="0" placeholder="optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estimated Patients / month &mdash; max</label>
                            <input type="number" name="est_patients_max" id="p_emax" class="form-control" min="0" placeholder="optional">
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Patient numbers appear on the site as an <strong>estimated potential range, not a guarantee</strong>. Leave both blank to hide the reach line for this plan.
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="p_active" checked>
                                <label class="form-check-label" for="p_active">Active (visible on the site)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="singleDeleteForm" class="d-none">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="bulk_action" value="delete">
    <input type="hidden" name="selected_ids[]" id="singleDeleteId" value="">
</form>

<script>
function openAddModal() {
    document.getElementById('planModalTitle').textContent = 'Add Plan';
    document.getElementById('planForm').reset();
    document.getElementById('plan_id').value = '';
    document.getElementById('p_cycle').value = 30;
    document.getElementById('p_sort').value = 0;
    document.getElementById('p_active').checked = true;
    document.getElementById('p_highlight').checked = false;
    syncCyclePresets();
}

function syncCyclePresets() {
    const v = String(document.getElementById('p_cycle').value);
    document.querySelectorAll('.cycle-preset').forEach(b => b.classList.toggle('active', b.dataset.days === v));
}

document.addEventListener('DOMContentLoaded', function () {
    const dataTable = $('.lms_table_active').DataTable({ destroy: true, bLengthChange: false, responsive: true, searching: true });
    $('.dataTables_filter').hide();
    const searchInput = document.querySelector('#searchInput');
    if (searchInput) searchInput.addEventListener('keyup', function () { dataTable.search(this.value).draw(); });

    document.querySelectorAll('.cycle-preset').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('p_cycle').value = this.dataset.days;
            syncCyclePresets();
        });
    });
    document.getElementById('p_cycle').addEventListener('input', syncCyclePresets);

    document.querySelectorAll('.edit-plan-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;
            document.getElementById('planModalTitle').textContent = 'Edit Plan';
            document.getElementById('plan_id').value = d.id;
            document.getElementById('p_name').value = d.name;
            document.getElementById('p_tagline').value = d.tagline;
            document.getElementById('p_price').value = d.price;
            document.getElementById('p_cycle').value = d.cycle;
            document.getElementById('p_features').value = d.features;
            document.getElementById('p_emin').value = d.emin;
            document.getElementById('p_emax').value = d.emax;
            document.getElementById('p_sort').value = d.sort;
            document.getElementById('p_highlight').checked = d.highlight === '1';
            document.getElementById('p_active').checked = d.active === '1';
            syncCyclePresets();
        });
    });

    document.querySelectorAll('.delete-plan-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm(`Delete "${this.dataset.name}"? Plans with subscriptions can't be deleted — deactivate instead.`)) return;
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
        bulkButtons.forEach(b => b.disabled = checked.length === 0);
        rowCheckboxes().forEach(cb => cb.closest('tr').classList.toggle('row-selected', cb.checked));
        const all = rowCheckboxes();
        const allChecked = all.length > 0 && all.every(cb => cb.checked);
        selectAllHeader.checked = allChecked; selectAllToolbar.checked = allChecked;
    }
    function toggleAll(c) { rowCheckboxes().forEach(cb => cb.checked = c); updateToolbar(); }

    selectAllHeader?.addEventListener('change', () => toggleAll(selectAllHeader.checked));
    selectAllToolbar?.addEventListener('change', () => toggleAll(selectAllToolbar.checked));
    document.querySelector('tbody')?.addEventListener('change', e => { if (e.target.classList.contains('row-checkbox')) updateToolbar(); });
    dataTable.on('draw', updateToolbar);

    bulkButtons.forEach(btn => btn.addEventListener('click', function () {
        const action = this.dataset.action;
        const checked = rowCheckboxes().filter(cb => cb.checked);
        if (!checked.length) return;
        const m = {
            activate: `Activate ${checked.length} plan(s)?`,
            deactivate: `Deactivate ${checked.length} plan(s)?`,
            delete: `Delete ${checked.length} plan(s)? Plans with subscriptions are skipped.`,
        };
        if (!confirm(m[action] || 'Apply this action?')) return;
        bulkActionInput.value = action;
        bulkActionForm.submit();
    }));

    updateToolbar();
});
</script>
</body>
</html>
