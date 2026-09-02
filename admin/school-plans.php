<?php
include "functions.php"; // includes db-conn.php + enforces admin_jwt_guard()

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── Table (idempotent — also created by school/parent-consent.php consumers) ── */
$conn->query("
CREATE TABLE IF NOT EXISTS school_health_plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  tier VARCHAR(40) DEFAULT NULL,
  tagline VARCHAR(200) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  billing_label VARCHAR(40) NOT NULL DEFAULT 'per student / year',
  age_min TINYINT UNSIGNED DEFAULT NULL,
  age_max TINYINT UNSIGNED DEFAULT NULL,
  features TEXT DEFAULT NULL,
  accent_color VARCHAR(20) NOT NULL DEFAULT '#0C74C5',
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  show_on_consent TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_active (is_active, show_on_consent),
  KEY idx_age (age_min, age_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$page_message = '';
$page_message_type = '';

function _shp_age_label($min, $max): string
{
    if ($min === null && $max === null) return 'All ages';
    if ($min !== null && $max !== null) return $min . '–' . $max . ' yrs';
    if ($min !== null) return $min . '+ yrs';
    return 'up to ' . $max . ' yrs';
}

/* ── Add / Edit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $plan_id   = (int) ($_POST['plan_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $tier      = trim($_POST['tier'] ?? '');
        $tagline   = trim($_POST['tagline'] ?? '');
        $price     = (float) ($_POST['price'] ?? 0);
        $billing   = trim($_POST['billing_label'] ?? '') ?: 'per student / year';
        $age_min   = ($_POST['age_min'] ?? '') === '' ? null : max(0, min(120, (int) $_POST['age_min']));
        $age_max   = ($_POST['age_max'] ?? '') === '' ? null : max(0, min(120, (int) $_POST['age_max']));
        $features  = trim($_POST['features'] ?? '');
        $accent    = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['accent_color'] ?? '') ? $_POST['accent_color'] : '#0C74C5';
        $sort      = (int) ($_POST['sort_order'] ?? 0);
        $popular   = isset($_POST['is_popular']) ? 1 : 0;
        $onconsent = isset($_POST['show_on_consent']) ? 1 : 0;
        $active    = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $page_message = 'Plan name is required.';
            $page_message_type = 'warning';
        } elseif ($price < 0) {
            $page_message = 'Price cannot be negative.';
            $page_message_type = 'warning';
        } elseif ($age_min !== null && $age_max !== null && $age_min > $age_max) {
            $page_message = 'Age range: minimum cannot be greater than maximum.';
            $page_message_type = 'warning';
        } else {
            if ($plan_id > 0) {
                $stmt = $conn->prepare("UPDATE school_health_plans SET
                    name=?, tier=?, tagline=?, price=?, billing_label=?, age_min=?, age_max=?,
                    features=?, accent_color=?, sort_order=?, is_popular=?, show_on_consent=?, is_active=?
                    WHERE id=?");
                $stmt->bind_param(
                    'sssdsiissiiiii',
                    $name, $tier, $tagline, $price, $billing, $age_min, $age_max,
                    $features, $accent, $sort, $popular, $onconsent, $active, $plan_id
                );
                $ok = $stmt->execute();
                $page_message = $ok ? 'Plan updated successfully.' : 'Failed to update plan.';
                $page_message_type = $ok ? 'success' : 'danger';
            } else {
                $stmt = $conn->prepare("INSERT INTO school_health_plans
                    (name, tier, tagline, price, billing_label, age_min, age_max, features, accent_color, sort_order, is_popular, show_on_consent, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param(
                    'sssdsiissiiii',
                    $name, $tier, $tagline, $price, $billing, $age_min, $age_max,
                    $features, $accent, $sort, $popular, $onconsent, $active
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
                    $s = $conn->prepare("UPDATE school_health_plans SET is_active=1 WHERE id IN ($ph)");
                    $s->bind_param($types, ...$ids); $s->execute();
                    $page_message = "$count plan(s) activated."; $page_message_type = 'success';
                    break;
                case 'deactivate':
                    $s = $conn->prepare("UPDATE school_health_plans SET is_active=0 WHERE id IN ($ph)");
                    $s->bind_param($types, ...$ids); $s->execute();
                    $page_message = "$count plan(s) deactivated."; $page_message_type = 'success';
                    break;
                case 'delete':
                    $ok = 0; $blocked = 0;
                    foreach ($ids as $id) {
                        $used = (int) mysqli_fetch_assoc(mysqli_query(
                            $conn,
                            "SELECT COUNT(*) c FROM parent_consent_forms WHERE plan_id = " . (int) $id
                        ))['c'];
                        if ($used > 0) { $blocked++; continue; }
                        $d = $conn->prepare("DELETE FROM school_health_plans WHERE id=?");
                        $d->bind_param('i', $id);
                        if ($d->execute()) $ok++;
                    }
                    if ($ok) { $page_message = "$ok plan(s) deleted."; $page_message_type = 'success'; }
                    if ($blocked) {
                        $page_message = ($ok ? "$page_message " : '')
                            . "$blocked plan(s) are linked to submitted consent forms and can't be deleted — deactivate them instead.";
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
           (SELECT COUNT(*) FROM parent_consent_forms c WHERE c.plan_id = p.id AND c.payment_status='paid') AS paid_count
    FROM school_health_plans p
    ORDER BY p.sort_order ASC, p.price ASC, p.id ASC
");
if ($res) { while ($r = $res->fetch_assoc()) $plans[] = $r; }
?>
<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>School Health Plans &amp; Pricing | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
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
                                    <h3 class="mb-0 fw-bold">School Health Plans &amp; Pricing</h3>
                                    <p class="text-muted mb-0 small">Shown on <code>school-program.php</code>. Plans marked
                                        <strong>On consent form</strong> are auto-selected by the child's age when a parent
                                        submits <code>school/parent-consent.php</code>, then charged via Razorpay.</p>
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
                                                <th width="110">Age band</th>
                                                <th width="120">On consent form</th>
                                                <th width="90">Popular</th>
                                                <th width="110">Paid forms</th>
                                                <th width="90">Status</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($plans)): ?>
                                                <tr class="empty-row"><td colspan="10">
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
                                                    <td data-label="Price" class="text-center fw-semibold">&#8377;<?= number_format((float) $p['price']) ?><div class="cell-sub"><?= htmlspecialchars($p['billing_label']) ?></div></td>
                                                    <td data-label="Age band" class="text-center"><span class="pill pill-info"><?= htmlspecialchars(_shp_age_label($p['age_min'] === null ? null : (int) $p['age_min'], $p['age_max'] === null ? null : (int) $p['age_max'])) ?></span></td>
                                                    <td data-label="On consent form" class="text-center"><?= $p['show_on_consent'] ? '<span class="pill pill-success">Yes</span>' : '<span class="pill pill-muted">No</span>' ?></td>
                                                    <td data-label="Popular" class="text-center"><?= $p['is_popular'] ? '<i class="fas fa-star text-warning" title="Most popular"></i>' : '<span class="text-muted">&mdash;</span>' ?></td>
                                                    <td data-label="Paid forms" class="text-center"><span class="pill pill-muted"><?= (int) $p['paid_count'] ?></span></td>
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
                                                                data-billing="<?= htmlspecialchars($p['billing_label'], ENT_QUOTES) ?>"
                                                                data-agemin="<?= $p['age_min'] === null ? '' : (int) $p['age_min'] ?>"
                                                                data-agemax="<?= $p['age_max'] === null ? '' : (int) $p['age_max'] ?>"
                                                                data-features="<?= htmlspecialchars($p['features'] ?? '', ENT_QUOTES) ?>"
                                                                data-accent="<?= htmlspecialchars($p['accent_color'], ENT_QUOTES) ?>"
                                                                data-sort="<?= (int) $p['sort_order'] ?>"
                                                                data-popular="<?= (int) $p['is_popular'] ?>"
                                                                data-onconsent="<?= (int) $p['show_on_consent'] ?>"
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
                            <input type="text" name="name" id="p_name" class="form-control" required placeholder="e.g. Standard Plan">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Price (&#8377;) <span class="text-danger">*</span></label>
                            <input type="number" name="price" id="p_price" class="form-control" min="0" step="1" required placeholder="199">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Tier badge</label>
                            <input type="text" name="tier" id="p_tier" class="form-control" maxlength="40" placeholder="e.g. Health Screening &amp; Care Plan">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Billing label</label>
                            <input type="text" name="billing_label" id="p_billing" class="form-control" maxlength="40" value="per student / year">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tagline</label>
                            <input type="text" name="tagline" id="p_tagline" class="form-control" maxlength="200" placeholder="One-line pitch shown under the price">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Age from (yrs)</label>
                            <input type="number" name="age_min" id="p_agemin" class="form-control" min="0" max="120" placeholder="e.g. 9">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Age to (yrs)</label>
                            <input type="number" name="age_max" id="p_agemax" class="form-control" min="0" max="120" placeholder="e.g. 13">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Accent colour</label>
                            <input type="color" name="accent_color" id="p_accent" class="form-control form-control-color" value="#0C74C5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" id="p_sort" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Features <span class="text-muted small">(one per line)</span></label>
                            <textarea name="features" id="p_features" class="form-control" rows="6" placeholder="Digital Health ID with Photo&#10;Vaccination Tracking&#10;Vision &amp; Dental Screening"></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_on_consent" id="p_onconsent">
                                <label class="form-check-label" for="p_onconsent">On parent consent form</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_popular" id="p_popular">
                                <label class="form-check-label" for="p_popular">Most popular</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="p_active" checked>
                                <label class="form-check-label" for="p_active">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0 small">
                                <i class="fas fa-info-circle me-1"></i>
                                A parent's plan is chosen from the child's age. When two <strong>On consent form</strong>
                                plans overlap an age, the one with the lower <strong>sort order</strong> is used.
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
    document.getElementById('p_billing').value = 'per student / year';
    document.getElementById('p_accent').value = '#0C74C5';
    document.getElementById('p_sort').value = 0;
    document.getElementById('p_active').checked = true;
}

document.addEventListener('DOMContentLoaded', function () {
    let dataTable = null;
    if (window.jQuery && jQuery.fn.DataTable) {
        dataTable = $('#shpTable').DataTable({ destroy: true, bLengthChange: false, responsive: true, searching: true, columnDefs: [{ orderable: false, targets: [0, 9] }] });
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
            document.getElementById('p_billing').value = d.billing || 'per student / year';
            document.getElementById('p_agemin').value = d.agemin;
            document.getElementById('p_agemax').value = d.agemax;
            document.getElementById('p_features').value = d.features;
            document.getElementById('p_accent').value = /^#[0-9a-fA-F]{6}$/.test(d.accent) ? d.accent : '#0C74C5';
            document.getElementById('p_sort').value = d.sort;
            document.getElementById('p_popular').checked = d.popular === '1';
            document.getElementById('p_onconsent').checked = d.onconsent === '1';
            document.getElementById('p_active').checked = d.active === '1';
        });
    });

    document.querySelectorAll('.delete-plan-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm(`Delete "${this.dataset.name}"? Plans linked to submitted consent forms can't be deleted — deactivate instead.`)) return;
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
            delete: `Delete ${checked.length} plan(s)? Plans linked to consent forms are skipped.`,
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
