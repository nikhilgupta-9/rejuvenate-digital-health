<?php
include "functions.php"; // includes db-conn.php + enforces admin_jwt_guard()

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* school_plans schema: see database/migration_school_subscription_referral.sql */

$page_message = '';
$page_message_type = '';

/* ── Add / Edit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $plan_id     = (int) ($_POST['plan_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $tier        = trim($_POST['tier'] ?? '');
        $tagline     = trim($_POST['tagline'] ?? '');
        $price       = (float) ($_POST['price'] ?? 0);
        $cycle_days  = (int) ($_POST['billing_cycle_days'] ?? 365) ?: 365;
        $max_students = ($_POST['max_students'] ?? '') === '' ? null : max(1, (int) $_POST['max_students']);
        $features    = trim($_POST['features'] ?? '');
        $accent      = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['accent_color'] ?? '') ? $_POST['accent_color'] : '#0C74C5';
        $sort        = (int) ($_POST['sort_order'] ?? 0);
        $popular     = isset($_POST['is_popular']) ? 1 : 0;
        $active      = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $page_message = 'Plan name is required.';
            $page_message_type = 'warning';
        } elseif ($price < 0) {
            $page_message = 'Price cannot be negative.';
            $page_message_type = 'warning';
        } elseif ($cycle_days < 1) {
            $page_message = 'Billing cycle must be at least 1 day.';
            $page_message_type = 'warning';
        } else {
            if ($plan_id > 0) {
                $stmt = $conn->prepare("UPDATE school_plans SET
                    name=?, tier=?, tagline=?, price=?, billing_cycle_days=?, max_students=?,
                    features=?, accent_color=?, sort_order=?, is_popular=?, is_active=?
                    WHERE id=?");
                $stmt->bind_param(
                    'sssdiisssiii',
                    $name, $tier, $tagline, $price, $cycle_days, $max_students,
                    $features, $accent, $sort, $popular, $active, $plan_id
                );
                $ok = $stmt->execute();
                $page_message = $ok ? 'Plan updated successfully.' : 'Failed to update plan.';
                $page_message_type = $ok ? 'success' : 'danger';
            } else {
                $stmt = $conn->prepare("INSERT INTO school_plans
                    (name, tier, tagline, price, billing_cycle_days, max_students, features, accent_color, sort_order, is_popular, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param(
                    'sssdiisssii',
                    $name, $tier, $tagline, $price, $cycle_days, $max_students,
                    $features, $accent, $sort, $popular, $active
                );
                $ok = $stmt->execute();
                $page_message = $ok ? 'Plan added successfully.' : 'Failed to add plan.';
                $page_message_type = $ok ? 'success' : 'danger';
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
                    $s = $conn->prepare("UPDATE school_plans SET is_active=1 WHERE id IN ($ph)");
                    $s->bind_param($types, ...$ids); $s->execute();
                    $page_message = "$count plan(s) activated."; $page_message_type = 'success';
                    break;
                case 'deactivate':
                    $s = $conn->prepare("UPDATE school_plans SET is_active=0 WHERE id IN ($ph)");
                    $s->bind_param($types, ...$ids); $s->execute();
                    $page_message = "$count plan(s) deactivated."; $page_message_type = 'success';
                    break;
                case 'delete':
                    $ok = 0; $blocked = 0;
                    foreach ($ids as $id) {
                        $used = (int) mysqli_fetch_assoc(mysqli_query(
                            $conn,
                            "SELECT COUNT(*) c FROM school_subscriptions WHERE plan_id = " . (int) $id
                        ))['c'];
                        if ($used > 0) { $blocked++; continue; }
                        $d = $conn->prepare("DELETE FROM school_plans WHERE id=?");
                        $d->bind_param('i', $id);
                        if ($d->execute()) $ok++;
                    }
                    if ($ok) { $page_message = "$ok plan(s) deleted."; $page_message_type = 'success'; }
                    if ($blocked) {
                        $page_message = ($ok ? "$page_message " : '')
                            . "$blocked plan(s) have schools subscribed to them and can't be deleted — deactivate them instead.";
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
           (SELECT COUNT(*) FROM school_subscriptions s WHERE s.plan_id = p.id AND s.status='active') AS active_count,
           (SELECT COUNT(*) FROM school_subscriptions s WHERE s.plan_id = p.id AND s.status='pending_approval') AS pending_count
    FROM school_plans p
    ORDER BY p.sort_order ASC, p.price ASC, p.id ASC
");
if ($res) { while ($r = $res->fetch_assoc()) $plans[] = $r; }
?>
<!DOCTYPE html>
<html lang="zxx">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>School Subscription Plans | Admin Panel</title>
    <?php include "links.php"; ?>
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
                                    <h3 class="mb-0 fw-bold">School Subscription Plans</h3>
                                    <p class="text-muted mb-0 small">Platform SaaS tiers a school itself subscribes to
                                        (shown on <code>school/subscription.php</code>) — separate from the per-student
                                        <a href="school-plans.php">Health Plans &amp; Pricing</a>. Every request, free
                                        or paid, needs approval on the <a href="school-subscriptions.php">Subscriptions</a> page.</p>
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
                                    <table class="table table-hover tbl-admin tbl-cards" id="shpTable">
                                        <thead>
                                            <tr>
                                                <th width="40"><input type="checkbox" class="form-check-input" id="selectAllHeader"></th>
                                                <th width="50">#</th>
                                                <th>Plan</th>
                                                <th width="110">Price</th>
                                                <th width="110">Max students</th>
                                                <th width="90">Popular</th>
                                                <th width="140">Active / Pending</th>
                                                <th width="90">Status</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($plans)): ?>
                                                <tr class="empty-row"><td colspan="9">
                                                    <i class="fas fa-layer-group fa-3x mb-3 d-block opacity-25"></i>
                                                    No plans yet. Add your first one above.
                                                </td></tr>
                                            <?php else: $sno = 1; foreach ($plans as $p): ?>
                                                <tr>
                                                    <td data-label="Select" class="text-center"><input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= (int) $p['id'] ?>"></td>
                                                    <td class="text-center"><?= $sno++ ?></td>
                                                    <td data-label="Plan">
                                                        <span class="cell-title">
                                                            <span class="shp-dot" style="background:<?= htmlspecialchars($p['accent_color']) ?>;"></span>
                                                            <?= htmlspecialchars($p['name']) ?>
                                                        </span>
                                                        <?php if (!empty($p['tier'])): ?><div class="cell-sub"><?= htmlspecialchars($p['tier']) ?></div><?php endif; ?>
                                                        <?php if (!empty($p['tagline'])): ?><div class="cell-sub fst-italic"><?= htmlspecialchars($p['tagline']) ?></div><?php endif; ?>
                                                    </td>
                                                    <td data-label="Price" class="text-center fw-semibold">&#8377;<?= number_format((float) $p['price']) ?><div class="cell-sub">per <?= (int) $p['billing_cycle_days'] ?> days</div></td>
                                                    <td data-label="Max students" class="text-center"><span class="pill pill-info"><?= $p['max_students'] === null ? 'Unlimited' : number_format((int) $p['max_students']) ?></span></td>
                                                    <td data-label="Popular" class="text-center"><?= $p['is_popular'] ? '<i class="fas fa-star text-warning" title="Most popular"></i>' : '<span class="text-muted">&mdash;</span>' ?></td>
                                                    <td data-label="Active / Pending" class="text-center">
                                                        <span class="pill pill-success" title="Active subscriptions"><?= (int) $p['active_count'] ?></span>
                                                        <span class="pill pill-muted" title="Pending approval"><?= (int) $p['pending_count'] ?></span>
                                                    </td>
                                                    <td data-label="Status" class="text-center"><?= $p['is_active'] ? '<span class="pill pill-success">Active</span>' : '<span class="pill pill-muted">Inactive</span>' ?></td>
                                                    <td data-label="Action" class="text-center">
                                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-circle p-2 edit-plan-btn"
                                                                data-bs-toggle="modal" data-bs-target="#planModal"
                                                                data-id="<?= (int) $p['id'] ?>"
                                                                data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                                                data-tier="<?= htmlspecialchars($p['tier'] ?? '', ENT_QUOTES) ?>"
                                                                data-tagline="<?= htmlspecialchars($p['tagline'] ?? '', ENT_QUOTES) ?>"
                                                                data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                                                                data-cycle="<?= (int) $p['billing_cycle_days'] ?>"
                                                                data-maxstudents="<?= $p['max_students'] === null ? '' : (int) $p['max_students'] ?>"
                                                                data-features="<?= htmlspecialchars($p['features'] ?? '', ENT_QUOTES) ?>"
                                                                data-accent="<?= htmlspecialchars($p['accent_color'], ENT_QUOTES) ?>"
                                                                data-sort="<?= (int) $p['sort_order'] ?>"
                                                                data-popular="<?= (int) $p['is_popular'] ?>"
                                                                data-active="<?= (int) $p['is_active'] ?>" title="Edit">
                                                                <i class="fas fa-pen fs-6"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-2 delete-plan-btn"
                                                                data-id="<?= (int) $p['id'] ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" title="Delete">
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
                            <input type="text" name="name" id="p_name" class="form-control" required placeholder="e.g. Growth">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Price (&#8377;) <span class="text-danger">*</span></label>
                            <input type="number" name="price" id="p_price" class="form-control" min="0" step="1" required placeholder="9999">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Tier badge</label>
                            <input type="text" name="tier" id="p_tier" class="form-control" maxlength="60" placeholder="e.g. Up to 500 students">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Billing cycle (days)</label>
                            <input type="number" name="billing_cycle_days" id="p_cycle" class="form-control" min="1" value="365">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tagline</label>
                            <input type="text" name="tagline" id="p_tagline" class="form-control" maxlength="200" placeholder="One-line pitch shown under the price">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max students <span class="text-muted small">(blank = unlimited)</span></label>
                            <input type="number" name="max_students" id="p_maxstudents" class="form-control" min="1" placeholder="e.g. 500">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Accent colour</label>
                            <input type="color" name="accent_color" id="p_accent" class="form-control form-control-color" value="#0C74C5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" id="p_sort" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Features <span class="text-muted small">(one per line)</span></label>
                            <textarea name="features" id="p_features" class="form-control" rows="6" placeholder="Digital Health ID for members&#10;Priority support&#10;Bulk member import"></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_popular" id="p_popular">
                                <label class="form-check-label" for="p_popular">Most popular</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="p_active" checked>
                                <label class="form-check-label" for="p_active">Active (visible to schools)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Every request a school submits for a plan — free or paid — lands as
                                <strong>Pending Approval</strong> on the <a href="school-subscriptions.php">Subscriptions</a> page
                                until an admin approves it. The student cap here is shown to the school as a soft
                                usage warning only; it does not block adding members.
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
    document.getElementById('p_cycle').value = 365;
    document.getElementById('p_accent').value = '#0C74C5';
    document.getElementById('p_sort').value = 0;
    document.getElementById('p_active').checked = true;
}

document.addEventListener('DOMContentLoaded', function () {
    let dataTable = null;
    if (window.jQuery && jQuery.fn.DataTable) {
        dataTable = $('#shpTable').DataTable({ destroy: true, bLengthChange: false, responsive: true, searching: true, columnDefs: [{ orderable: false, targets: [0, 8] }] });
        $('#shpTable_filter').hide();
    }
    const searchInput = document.querySelector('#searchInput');
    if (searchInput && dataTable) searchInput.addEventListener('keyup', function () { dataTable.search(this.value).draw(); });

    document.querySelectorAll('.edit-plan-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;
            document.getElementById('planModalTitle').textContent = 'Edit Plan';
            document.getElementById('plan_id').value = d.id;
            document.getElementById('p_name').value = d.name;
            document.getElementById('p_tier').value = d.tier;
            document.getElementById('p_tagline').value = d.tagline;
            document.getElementById('p_price').value = d.price;
            document.getElementById('p_cycle').value = d.cycle || 365;
            document.getElementById('p_maxstudents').value = d.maxstudents;
            document.getElementById('p_features').value = d.features;
            document.getElementById('p_accent').value = /^#[0-9a-fA-F]{6}$/.test(d.accent) ? d.accent : '#0C74C5';
            document.getElementById('p_sort').value = d.sort;
            document.getElementById('p_popular').checked = d.popular === '1';
            document.getElementById('p_active').checked = d.active === '1';
        });
    });

    document.querySelectorAll('.delete-plan-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm(`Delete "${this.dataset.name}"? Plans with schools subscribed can't be deleted — deactivate instead.`)) return;
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
        if (selectAllHeader) selectAllHeader.checked = allChecked;
        if (selectAllToolbar) selectAllToolbar.checked = allChecked;
    }
    function toggleAll(c) { rowCheckboxes().forEach(cb => cb.checked = c); updateToolbar(); }

    selectAllHeader?.addEventListener('change', () => toggleAll(selectAllHeader.checked));
    selectAllToolbar?.addEventListener('change', () => toggleAll(selectAllToolbar.checked));
    document.querySelector('#shpTable tbody')?.addEventListener('change', e => { if (e.target.classList.contains('row-checkbox')) updateToolbar(); });
    if (dataTable) dataTable.on('draw', updateToolbar);

    bulkButtons.forEach(btn => btn.addEventListener('click', function () {
        const action = this.dataset.action;
        const checked = rowCheckboxes().filter(cb => cb.checked);
        if (!checked.length) return;
        const m = {
            activate: `Activate ${checked.length} plan(s)?`,
            deactivate: `Deactivate ${checked.length} plan(s)?`,
            delete: `Delete ${checked.length} plan(s)? Plans with subscribed schools are skipped.`,
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
