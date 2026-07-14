<?php
require_once dirname(__DIR__) . '/util/function.php';
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$my_id = (int)$_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT role FROM admin_user WHERE id = ?");
$stmt->bind_param('i', $my_id);
$stmt->execute();
$stmt->bind_result($my_role);
$stmt->fetch();
$stmt->close();
$is_super = ($my_role === 'super_admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure tables exist (user-roles.php creates them, but be safe)
$conn->query("CREATE TABLE IF NOT EXISTS admin_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT '',
    module VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT NOW()
)");
$conn->query("CREATE TABLE IF NOT EXISTS admin_role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id)
)");

$errors  = [];
$success = '';

// ── AJAX actions (super_admin only) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$is_super) {
        echo json_encode(['success' => false, 'message' => 'Only Super Admins can manage permissions.']);
        exit();
    }
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF token mismatch.']);
        exit();
    }

    $action = $_POST['action'];

    // Add permission
    if ($action === 'add_permission') {
        $name    = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['name'] ?? '')));
        $display = trim($_POST['display_name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $module  = trim($_POST['module'] ?? '');
        $order   = (int)($_POST['sort_order'] ?? 0);

        if (!$name || !$display || !$module) {
            echo json_encode(['success' => false, 'message' => 'Name, display name, and module are required.']);
            exit();
        }

        $chk = $conn->prepare("SELECT id FROM admin_permissions WHERE name = ?");
        $chk->bind_param('s', $name);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => "Permission '$name' already exists."]);
            exit();
        }
        $chk->close();

        $ins = $conn->prepare("INSERT INTO admin_permissions (name, display_name, description, module, sort_order) VALUES (?,?,?,?,?)");
        $ins->bind_param('ssssi', $name, $display, $desc, $module, $order);
        $ins->execute();
        $new_id = $conn->insert_id;
        $ins->close();

        echo json_encode(['success' => true, 'message' => "Permission '$display' added.", 'id' => $new_id]);
        exit();
    }

    // Edit permission
    if ($action === 'edit_permission') {
        $id      = (int)($_POST['id'] ?? 0);
        $display = trim($_POST['display_name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $module  = trim($_POST['module'] ?? '');
        $order   = (int)($_POST['sort_order'] ?? 0);

        if (!$id || !$display || !$module) {
            echo json_encode(['success' => false, 'message' => 'Display name and module are required.']);
            exit();
        }

        $upd = $conn->prepare("UPDATE admin_permissions SET display_name=?, description=?, module=?, sort_order=? WHERE id=?");
        $upd->bind_param('sssii', $display, $desc, $module, $order, $id);
        $upd->execute();
        $upd->close();

        echo json_encode(['success' => true, 'message' => "Permission updated."]);
        exit();
    }

    // Delete permission
    if ($action === 'delete_permission') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit();
        }
        $del = $conn->prepare("DELETE FROM admin_role_permissions WHERE permission_id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        $del2 = $conn->prepare("DELETE FROM admin_permissions WHERE id = ?");
        $del2->bind_param('i', $id);
        $del2->execute();
        $del2->close();

        echo json_encode(['success' => true, 'message' => 'Permission deleted.']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// ── Load permissions ──────────────────────────────────────────────────────────
$permissions_by_module = [];
$all_permissions = [];
$res = $conn->query("SELECT p.*,
    (SELECT COUNT(*) FROM admin_role_permissions rp WHERE rp.permission_id = p.id) as role_count
    FROM admin_permissions p ORDER BY module, sort_order, display_name");
while ($row = $res->fetch_assoc()) {
    $permissions_by_module[$row['module']][] = $row;
    $all_permissions[] = $row;
}

// Distinct modules for dropdown
$modules = array_keys($permissions_by_module);

$total_perms = count($all_permissions);
$total_modules = count($permissions_by_module);

$flash_success = $_SESSION['admin_success'] ?? '';
$flash_error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$favicon = get_favicon();

$module_icons = [
    'Dashboard'       => ['icon' => 'fa-tachometer-alt', 'color' => '#3b82f6'],
    'User Management' => ['icon' => 'fa-user-cog',       'color' => '#8b5cf6'],
    'Patients'        => ['icon' => 'fa-users',           'color' => '#ef4444'],
    'Doctors'         => ['icon' => 'fa-user-md',         'color' => '#06b6d4'],
    'Appointments'    => ['icon' => 'fa-calendar-check',  'color' => '#f59e0b'],
    'Products'        => ['icon' => 'fa-box-open',        'color' => '#f97316'],
    'Orders'          => ['icon' => 'fa-shopping-cart',   'color' => '#ec4899'],
    'Content'         => ['icon' => 'fa-file-alt',        'color' => '#10b981'],
    'Schools'         => ['icon' => 'fa-school',          'color' => '#0d6efd'],
    'ABHA'            => ['icon' => 'fa-id-card',         'color' => '#00875a'],
    'Reports'         => ['icon' => 'fa-chart-bar',       'color' => '#d97706'],
    'Settings'        => ['icon' => 'fa-cog',             'color' => '#6b7280'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Permissions | REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
  <?php include 'links.php'; ?>
  <style>
    :root { --pri: #0C74C5; --acc: #02c9b8; }

    .page-header-bar { background: #fff; border-radius: 12px; padding: 18px 22px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }

    .stat-card { border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .stat-val { font-size: 1.6rem; font-weight: 700; line-height: 1; }
    .stat-lbl { font-size: .78rem; color: #6b7280; margin-top: 2px; }

    .module-block { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 18px; overflow: hidden; }
    .module-header-bar { padding: 12px 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f3f4f6; }
    .module-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .9rem; color: #fff; }
    .module-name { font-size: .88rem; font-weight: 700; color: #1f2937; }

    .perm-row { padding: 11px 18px; border-bottom: 1px solid #f9fafb; display: flex; align-items: center; gap: 12px; transition: .15s; }
    .perm-row:hover { background: #f8fafc; }
    .perm-row:last-child { border-bottom: none; }
    .perm-name-code { font-size: .83rem; font-weight: 600; color: #1f2937; }
    .perm-slug { font-size: .68rem; color: #9ca3af; font-family: monospace; }
    .perm-desc-text { font-size: .76rem; color: #6b7280; }

    .roles-badge { display: inline-flex; align-items: center; gap: 4px; background: #f0fdf4; color: #15803d; border-radius: 50px; padding: .2rem .55rem; font-size: .68rem; font-weight: 600; white-space: nowrap; }

    .action-btn { width: 28px; height: 28px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; cursor: pointer; transition: .15s; }
    .action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,.15); }

    .search-bar { border-radius: 8px; border: 1px solid #e5e7eb; padding: 8px 14px; font-size: .84rem; width: 280px; }
    .search-bar:focus { outline: none; border-color: var(--pri); box-shadow: 0 0 0 3px rgba(12,116,197,.12); }

    .toast-stack { position: fixed; top: 75px; right: 18px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
  </style>
</head>

<body class="crm_body_bg">
  <?php include 'header.php'; ?>

  <section class="main_content dashboard_part large_header_bg">
    <div class="container-fluid g-0">
      <div class="row">
        <div class="col-lg-12 p-0"><?php include 'top_nav.php'; ?></div>
      </div>
    </div>

    <div class="main_content_iner">
      <div class="container-fluid p-0 sm_padding_15px">

        <!-- Flash -->
        <?php if ($flash_success): ?>
          <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h4 class="mb-0 fw-700" style="color:#1f2937;"><i class="fas fa-key me-2" style="color:var(--pri);"></i>System Permissions</h4>
            <p class="mb-0 mt-1" style="font-size:.82rem;color:#6b7280;">
              All <?= $total_perms ?> permissions across <?= $total_modules ?> modules
            </p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="user-roles.php" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-shield-alt me-1"></i>Role Matrix
            </a>
            <?php if ($is_super): ?>
            <button class="btn btn-sm" style="background:var(--pri);color:#fff;" data-bs-toggle="modal" data-bs-target="#addPermModal">
              <i class="fas fa-plus me-1"></i>Add Permission
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Stats row -->
        <div class="row g-3 mb-4">
          <div class="col-sm-4">
            <div class="card stat-card p-3">
              <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-key"></i></div>
                <div>
                  <div class="stat-val"><?= $total_perms ?></div>
                  <div class="stat-lbl">Total Permissions</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="card stat-card p-3">
              <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-layer-group"></i></div>
                <div>
                  <div class="stat-val"><?= $total_modules ?></div>
                  <div class="stat-lbl">Permission Modules</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="card stat-card p-3">
              <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-user-shield"></i></div>
                <div>
                  <div class="stat-val"><?= count($permissions_by_module['User Management'] ?? []) ?></div>
                  <div class="stat-lbl">Admin Permissions</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Search + filter -->
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
          <input type="text" id="permSearch" class="search-bar" placeholder="Search permissions…">
          <select id="moduleFilter" class="form-select" style="width:200px;font-size:.84rem;">
            <option value="">All Modules</option>
            <?php foreach ($modules as $mod): ?>
            <option value="<?= htmlspecialchars($mod) ?>"><?= htmlspecialchars($mod) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Permissions by module -->
        <div id="permList">
          <?php foreach ($permissions_by_module as $module => $perms):
            $mi = $module_icons[$module] ?? ['icon' => 'fa-circle', 'color' => '#6b7280'];
          ?>
          <div class="module-block" data-module="<?= htmlspecialchars($module) ?>">
            <div class="module-header-bar">
              <div class="module-icon" style="background:<?= $mi['color'] ?>;"><i class="fas <?= $mi['icon'] ?>"></i></div>
              <div class="module-name"><?= htmlspecialchars($module) ?></div>
              <span class="badge ms-2" style="background:#e0e7ff;color:#3730a3;font-size:.68rem;"><?= count($perms) ?></span>
              <?php if ($is_super): ?>
              <button class="btn btn-sm ms-auto" style="background:#f0fdf4;color:#16a34a;font-size:.72rem;"
                      data-bs-toggle="modal" data-bs-target="#addPermModal" data-module="<?= htmlspecialchars($module) ?>">
                <i class="fas fa-plus me-1"></i>Add to module
              </button>
              <?php endif; ?>
            </div>

            <?php foreach ($perms as $perm): ?>
            <div class="perm-row" data-name="<?= strtolower($perm['display_name'] . ' ' . $perm['name']) ?>">
              <div style="flex:1;min-width:0;">
                <div class="perm-name-code"><?= htmlspecialchars($perm['display_name']) ?></div>
                <div class="perm-slug"><?= htmlspecialchars($perm['name']) ?></div>
              </div>
              <div style="flex:2;min-width:0;" class="d-none d-md-block">
                <div class="perm-desc-text"><?= htmlspecialchars($perm['description']) ?></div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="roles-badge"><i class="fas fa-shield-alt"></i><?= $perm['role_count'] ?> role<?= $perm['role_count'] != 1 ? 's' : '' ?></span>
                <?php if ($is_super): ?>
                <button class="action-btn btn-edit-perm" style="background:#eff6ff;color:#2563eb;"
                        data-id="<?= $perm['id'] ?>"
                        data-name="<?= htmlspecialchars($perm['name']) ?>"
                        data-display="<?= htmlspecialchars($perm['display_name']) ?>"
                        data-desc="<?= htmlspecialchars($perm['description']) ?>"
                        data-module="<?= htmlspecialchars($perm['module']) ?>"
                        data-order="<?= $perm['sort_order'] ?>"
                        title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="action-btn btn-del-perm" style="background:#fef2f2;color:#dc2626;"
                        data-id="<?= $perm['id'] ?>"
                        data-display="<?= htmlspecialchars($perm['display_name']) ?>"
                        title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <div id="noResults" class="text-center py-5 d-none">
          <i class="fas fa-search fa-2x text-muted mb-2"></i>
          <p class="text-muted">No permissions match your search.</p>
        </div>

      </div><!-- /container -->
    </div><!-- /main_content_iner -->
  </section>

  <!-- ── Add Permission Modal ─────────────────────────────────────────────── -->
  <?php if ($is_super): ?>
  <div class="modal fade" id="addPermModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header" style="background:linear-gradient(135deg,var(--pri),#0a5ea0);color:#fff;">
          <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Permission</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Permission Key <span class="text-danger">*</span></label>
            <input type="text" id="add-name" class="form-control" placeholder="e.g. view_reports (lowercase, underscores only)">
            <div class="form-text">Only lowercase letters, numbers, and underscores.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Display Name <span class="text-danger">*</span></label>
            <input type="text" id="add-display" class="form-control" placeholder="e.g. View Reports">
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Module <span class="text-danger">*</span></label>
            <input type="text" id="add-module" class="form-control" list="modules-list" placeholder="e.g. Reports">
            <datalist id="modules-list">
              <?php foreach ($modules as $mod): ?>
              <option value="<?= htmlspecialchars($mod) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Description</label>
            <input type="text" id="add-desc" class="form-control" placeholder="Short description of what this grants">
          </div>
          <div class="mb-2">
            <label class="form-label fw-600" style="font-size:.82rem;">Sort Order</label>
            <input type="number" id="add-order" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="btnAddPerm"><i class="fas fa-save me-1"></i>Add Permission</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Edit Permission Modal ────────────────────────────────────────────── -->
  <div class="modal fade" id="editPermModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header" style="background:linear-gradient(135deg,#4f46e5,#3730a3);color:#fff;">
          <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Permission</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="edit-id">
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Permission Key</label>
            <input type="text" id="edit-name" class="form-control" disabled style="background:#f3f4f6;">
            <div class="form-text">Key cannot be changed (it may be referenced in code).</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Display Name <span class="text-danger">*</span></label>
            <input type="text" id="edit-display" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Module <span class="text-danger">*</span></label>
            <input type="text" id="edit-module" class="form-control" list="modules-list">
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:.82rem;">Description</label>
            <input type="text" id="edit-desc" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label fw-600" style="font-size:.82rem;">Sort Order</label>
            <input type="number" id="edit-order" class="form-control" min="0">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="btnSaveEdit"><i class="fas fa-save me-1"></i>Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Delete Confirm Modal ─────────────────────────────────────────────── -->
  <div class="modal fade" id="delPermModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Permission</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p style="font-size:.85rem;">Delete <strong id="delPermName"></strong>? It will be removed from all roles immediately.</p>
          <input type="hidden" id="del-id">
        </div>
        <div class="modal-footer border-0 pt-0">
          <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger btn-sm" id="btnConfirmDel"><i class="fas fa-trash me-1"></i>Delete</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Toast stack -->
  <div class="toast-stack" id="toastStack"></div>

  <?php include 'footer.php'; ?>
  <script>
  (function () {
    const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

    // ── Search + filter ────────────────────────────────────────────────────────
    function applyFilters() {
      const q      = document.getElementById('permSearch').value.toLowerCase();
      const module = document.getElementById('moduleFilter').value;
      let anyVisible = false;

      document.querySelectorAll('.module-block').forEach(block => {
        const mod = block.dataset.module;
        const modMatch = !module || mod === module;
        let blockHasVisible = false;

        block.querySelectorAll('.perm-row').forEach(row => {
          const nameMatch = !q || row.dataset.name.includes(q);
          const show = modMatch && nameMatch;
          row.style.display = show ? '' : 'none';
          if (show) { blockHasVisible = true; anyVisible = true; }
        });

        block.style.display = blockHasVisible ? '' : 'none';
      });

      document.getElementById('noResults').classList.toggle('d-none', anyVisible);
    }

    document.getElementById('permSearch').addEventListener('input', applyFilters);
    document.getElementById('moduleFilter').addEventListener('change', applyFilters);

    // ── Add Modal: pre-fill module ─────────────────────────────────────────────
    <?php if ($is_super): ?>
    document.querySelectorAll('[data-bs-target="#addPermModal"][data-module]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('add-module').value = btn.dataset.module;
      });
    });
    document.getElementById('addPermModal').addEventListener('hidden.bs.modal', () => {
      ['add-name','add-display','add-module','add-desc'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('add-order').value = '0';
    });

    // Slug auto-format
    document.getElementById('add-name').addEventListener('input', function () {
      this.value = this.value.replace(/[^a-z0-9_]/g, '').toLowerCase();
    });

    // ── Add permission ──────────────────────────────────────────────────────────
    document.getElementById('btnAddPerm').addEventListener('click', function () {
      const body = new FormData();
      body.append('action',       'add_permission');
      body.append('csrf_token',   CSRF);
      body.append('name',         document.getElementById('add-name').value.trim());
      body.append('display_name', document.getElementById('add-display').value.trim());
      body.append('module',       document.getElementById('add-module').value.trim());
      body.append('description',  document.getElementById('add-desc').value.trim());
      body.append('sort_order',   document.getElementById('add-order').value);

      this.disabled = true;
      fetch(window.location.href, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
          showToast(data.success ? 'success' : 'danger', data.message);
          if (data.success) setTimeout(() => location.reload(), 800);
        })
        .catch(() => showToast('danger', 'Network error.'))
        .finally(() => this.disabled = false);
    });

    // ── Edit modal ─────────────────────────────────────────────────────────────
    const editModal = new bootstrap.Modal(document.getElementById('editPermModal'));
    document.querySelectorAll('.btn-edit-perm').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('edit-id').value      = btn.dataset.id;
        document.getElementById('edit-name').value    = btn.dataset.name;
        document.getElementById('edit-display').value = btn.dataset.display;
        document.getElementById('edit-module').value  = btn.dataset.module;
        document.getElementById('edit-desc').value    = btn.dataset.desc;
        document.getElementById('edit-order').value   = btn.dataset.order;
        editModal.show();
      });
    });

    document.getElementById('btnSaveEdit').addEventListener('click', function () {
      const body = new FormData();
      body.append('action',       'edit_permission');
      body.append('csrf_token',   CSRF);
      body.append('id',           document.getElementById('edit-id').value);
      body.append('display_name', document.getElementById('edit-display').value.trim());
      body.append('module',       document.getElementById('edit-module').value.trim());
      body.append('description',  document.getElementById('edit-desc').value.trim());
      body.append('sort_order',   document.getElementById('edit-order').value);

      this.disabled = true;
      fetch(window.location.href, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
          showToast(data.success ? 'success' : 'danger', data.message);
          if (data.success) setTimeout(() => location.reload(), 800);
        })
        .catch(() => showToast('danger', 'Network error.'))
        .finally(() => this.disabled = false);
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    const delModal = new bootstrap.Modal(document.getElementById('delPermModal'));
    document.querySelectorAll('.btn-del-perm').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('del-id').value      = btn.dataset.id;
        document.getElementById('delPermName').textContent = btn.dataset.display;
        delModal.show();
      });
    });

    document.getElementById('btnConfirmDel').addEventListener('click', function () {
      const body = new FormData();
      body.append('action',      'delete_permission');
      body.append('csrf_token',  CSRF);
      body.append('id',          document.getElementById('del-id').value);

      this.disabled = true;
      fetch(window.location.href, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
          showToast(data.success ? 'success' : 'danger', data.message);
          if (data.success) setTimeout(() => location.reload(), 800);
        })
        .catch(() => showToast('danger', 'Network error.'))
        .finally(() => this.disabled = false);
    });
    <?php endif; ?>

    // ── Toast ──────────────────────────────────────────────────────────────────
    function showToast(type, msg) {
      const stack = document.getElementById('toastStack');
      const t = document.createElement('div');
      t.className = `alert alert-${type} alert-dismissible shadow`;
      t.style.cssText = 'min-width:260px;max-width:360px;font-size:.84rem;';
      t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${msg}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
      stack.appendChild(t);
      setTimeout(() => t.remove(), 5000);
    }
  })();
  </script>
</body>
</html>
