<?php
require_once dirname(__DIR__) . '/util/function.php';
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$my_id   = (int)$_SESSION['admin_id'];
$my_role = '';
$stmt = $conn->prepare("SELECT role FROM admin_user WHERE id = ?");
$stmt->bind_param('i', $my_id);
$stmt->execute();
$stmt->bind_result($my_role);
$stmt->fetch();
$stmt->close();

$is_super = ($my_role === 'super_admin');

// ── CSRF ──────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── AJAX / POST actions (super_admin only) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  if (!$is_super) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
  }
  if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF error']);
    exit();
  }

  $target_id = (int)($_POST['target_id'] ?? 0);
  if (!$target_id || $target_id === $my_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid target']);
    exit();
  }

  $action = $_POST['action'];

  if ($action === 'toggle_status') {
    $conn->query("UPDATE admin_user SET status = IF(status='active','suspended','active') WHERE id = $target_id");
    $res = $conn->query("SELECT status FROM admin_user WHERE id = $target_id");
    $row = $res->fetch_assoc();
    echo json_encode(['success' => true, 'new_status' => $row['status']]);
    exit();
  }

  if ($action === 'delete') {
    // Soft-delete: mark suspended so history is preserved
    $conn->query("UPDATE admin_user SET status='suspended', reset_token=NULL, reset_token_expiry=NULL WHERE id = $target_id");
    echo json_encode(['success' => true]);
    exit();
  }

  if ($action === 'hard_delete') {
    $conn->query("DELETE FROM admin_user WHERE id = $target_id");
    echo json_encode(['success' => true]);
    exit();
  }

  echo json_encode(['success' => false, 'message' => 'Unknown action']);
  exit();
}

// ── Stats ──────────────────────────────────────────────────────────────────
$stats = [];
$r = $conn->query("SELECT
    COUNT(*) total,
    SUM(role='super_admin') super_admins,
    SUM(role='admin') admins,
    SUM(role='manager') managers,
    SUM(status='active') active,
    SUM(status='suspended') suspended
FROM admin_user");
$stats = $r->fetch_assoc();

// ── Admin list ─────────────────────────────────────────────────────────────
$admins = [];
$result = $conn->query(
  "SELECT id, username, email, first_name, last_name, role, status,
            last_login, last_login_ip, created_at, failed_attempts
     FROM admin_user
     ORDER BY role='super_admin' DESC, created_at ASC"
);
while ($row = $result->fetch_assoc()) $admins[] = $row;

$favicon = get_favicon();

// Flash messages
$flash_success = $_SESSION['admin_success'] ?? '';
$flash_error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Admin Users | REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
  <?php include 'links.php'; ?>
  <style>
    :root {
      --pri: #0C74C5;
      --acc: #02c9b8;
    }

    .stat-card {
      border-radius: 12px;
      border: none;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    }

    .stat-card .icon-wrap {
      width: 46px;
      height: 46px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
    }

    .stat-val {
      font-size: 1.6rem;
      font-weight: 700;
      line-height: 1;
    }

    .stat-lbl {
      font-size: .78rem;
      color: #6b7280;
      margin-top: 2px;
    }

    .role-badge {
      font-size: .72rem;
      padding: .3rem .65rem;
      border-radius: 50px;
      font-weight: 600;
    }

    .role-super_admin {
      background: #fef2f2;
      color: #dc2626;
    }

    .role-admin {
      background: #eff6ff;
      color: #2563eb;
    }

    .role-manager {
      background: #f0fdf4;
      color: #16a34a;
    }

    .status-active {
      background: #dcfce7;
      color: #15803d;
    }

    .status-suspended {
      background: #fef3c7;
      color: #b45309;
    }

    .status-inactive {
      background: #f3f4f6;
      color: #6b7280;
    }

    .avatar-initials {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: var(--pri);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: .8rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .admin-row td {
      vertical-align: middle;
    }

    .action-btn {
      width: 30px;
      height: 30px;
      border-radius: 6px;
      border: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: .8rem;
      cursor: pointer;
      transition: .15s;
    }

    .action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
    }

    .page-header-bar {
      background: #fff;
      border-radius: 12px;
      padding: 18px 22px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
      margin-bottom: 20px;
    }
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

        <!-- Flash messages -->
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
        <div class="page-header-bar d-flex justify-content-between align-items-center">
          <div>
            <h4 class="mb-0 fw-700" style="color:#1f2937;">Admin User Management</h4>
            <p class="mb-0 mt-1" style="font-size:.82rem;color:#6b7280;">
              Manage all administrator accounts &amp; permissions
            </p>
          </div>
          <?php if ($is_super): ?>
            <a href="admin-create.php" class="btn btn-sm text-white fw-600"
              style="background:var(--pri);border-radius:8px;padding:8px 18px;">
              <i class="fas fa-plus me-2"></i>Create Admin
            </a>
          <?php endif; ?>
        </div>

        <!-- Stats row -->
        <div class="row g-3 mb-4">
          <?php
          $cards = [
            ['Total Admins',   $stats['total'],       'fas fa-users',        '#eff6ff', '#2563eb'],
            ['Super Admins',   $stats['super_admins'], 'fas fa-user-shield',  '#fef2f2', '#dc2626'],
            ['Admins',         $stats['admins'],       'fas fa-user-tie',    '#f0fdf4', '#16a34a'],
            ['Managers',       $stats['managers'],     'fas fa-user-cog',    '#fff7ed', '#ea580c'],
            ['Active',         $stats['active'],       'fas fa-circle-check', '#f0fdf4', '#16a34a'],
            ['Suspended',      $stats['suspended'],    'fas fa-ban',         '#fef9c3', '#b45309'],
          ];
          foreach ($cards as [$lbl, $val, $ico, $bg, $clr]): ?>
            <div class="col-6 col-md-4 col-lg-2">
              <div class="stat-card card p-3">
                <div class="d-flex align-items-center gap-3">
                  <div class="icon-wrap" style="background:<?= $bg ?>;color:<?= $clr ?>;">
                    <i class="<?= $ico ?>"></i>
                  </div>
                  <div>
                    <div class="stat-val" style="color:<?= $clr ?>;"><?= (int)$val ?></div>
                    <div class="stat-lbl"><?= $lbl ?></div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Admin Table -->
        <div class="white_card card_height_100 mb_30">
          <div class="white_card_header d-flex justify-content-between align-items-center">
            <div class="main-title">
              <h4 class="m-0">All Admin Users</h4>
            </div>
            <div class="d-flex gap-2">
              <input type="text" id="tableSearch" class="form-control form-control-sm"
                placeholder="Search..." style="width:200px;border-radius:8px;">
            </div>
          </div>
          <div class="white_card_body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="adminTable">
                <thead style="background:#f8fafc;font-size:.8rem;color:#6b7280;text-transform:uppercase;">
                  <tr>
                    <th class="px-3 py-3">#</th>
                    <th class="py-3">Admin</th>
                    <th class="py-3">Role</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Last Login</th>
                    <th class="py-3">Created</th>
                    <th class="py-3 text-end px-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($admins as $i => $a):
                    $initials = strtoupper(
                      substr($a['first_name'] ?? $a['username'], 0, 1) .
                        substr($a['last_name']  ?? '', 0, 1)
                    );
                    $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: $a['username'];
                    $is_me = ($a['id'] == $my_id);
                  ?>
                    <tr class="admin-row" data-search="<?= strtolower(htmlspecialchars($name . ' ' . $a['email'] . ' ' . $a['username'])) ?>">
                      <td class="px-3 py-3 text-muted" style="font-size:.8rem;"><?= $i + 1 ?></td>
                      <td class="py-3">
                        <div class="d-flex align-items-center gap-2">
                          <div class="avatar-initials"><?= $initials ?></div>
                          <div>
                            <div class="fw-600" style="font-size:.88rem;">
                              <?= htmlspecialchars($name) ?>
                              <?php if ($is_me): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:.65rem;">You</span>
                              <?php endif; ?>
                            </div>
                            <div style="font-size:.76rem;color:#9ca3af;"><?= htmlspecialchars($a['email']) ?></div>
                            <div style="font-size:.74rem;color:#9ca3af;">@<?= htmlspecialchars($a['username']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="py-3">
                        <span class="role-badge role-<?= $a['role'] ?>">
                          <?= ucfirst(str_replace('_', ' ', $a['role'])) ?>
                        </span>
                      </td>
                      <td class="py-3">
                        <span class="role-badge status-<?= $a['status'] ?>" id="status-badge-<?= $a['id'] ?>">
                          <?= ucfirst($a['status']) ?>
                        </span>
                        <?php if ($a['failed_attempts'] >= 3): ?>
                          <span class="ms-1" title="<?= $a['failed_attempts'] ?> failed attempts">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size:.75rem;"></i>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3" style="font-size:.8rem;color:#6b7280;">
                        <?php if ($a['last_login']): ?>
                          <?= date('d M Y', strtotime($a['last_login'])) ?>
                          <div style="font-size:.72rem;"><?= date('H:i', strtotime($a['last_login'])) ?></div>
                        <?php else: ?>
                          <span class="text-muted">Never</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3" style="font-size:.8rem;color:#6b7280;">
                        <?= date('d M Y', strtotime($a['created_at'])) ?>
                      </td>
                      <td class="py-3 text-end px-3">
                        <div class="d-flex justify-content-end gap-1">
                          <!-- Edit -->
                          <a href="admin-profile.php?id=<?= $a['id'] ?>" class="action-btn"
                            style="background:#eff6ff;color:#2563eb;" title="Edit">
                            <i class="fas fa-pencil-alt"></i>
                          </a>
                          <?php if ($is_super && !$is_me): ?>
                            <!-- Toggle status -->
                            <button class="action-btn btn-toggle-status"
                              data-id="<?= $a['id'] ?>"
                              data-status="<?= $a['status'] ?>"
                              style="background:<?= $a['status'] === 'active' ? '#fff7ed;color:#ea580c' : '#f0fdf4;color:#16a34a' ?>;"
                              title="<?= $a['status'] === 'active' ? 'Suspend' : 'Activate' ?>">
                              <i class="fas fa-<?= $a['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                            </button>
                            <!-- Delete -->
                            <button class="action-btn btn-delete" data-id="<?= $a['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                              style="background:#fef2f2;color:#dc2626;" title="Delete">
                              <i class="fas fa-trash"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'footer.php'; ?>
  </section>

  <!-- Delete confirm modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:12px;border:none;">
        <div class="modal-header border-0 pb-0">
          <div class="d-flex align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:1.1rem;">
              <i class="fas fa-trash"></i>
            </div>
            <div>
              <h5 class="modal-title mb-0">Delete Admin</h5>
              <div style="font-size:.8rem;color:#9ca3af;">This will suspend the account</div>
            </div>
          </div>
        </div>
        <div class="modal-body pt-3">
          <p class="mb-0">Are you sure you want to remove <strong id="deleteAdminName"></strong>?
            This will suspend their account and revoke all access.</p>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger btn-sm" id="confirmDelete">
            <i class="fas fa-trash me-1"></i>Remove Admin
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const CSRF = '<?= $_SESSION['csrf_token'] ?>';

    // Live search
    document.getElementById('tableSearch').addEventListener('input', function() {
      const q = this.value.toLowerCase();
      document.querySelectorAll('#adminTable tbody tr').forEach(tr => {
        tr.style.display = tr.dataset.search.includes(q) ? '' : 'none';
      });
    });

    // Toggle status
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = this.dataset.id;
        fetch('', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=toggle_status&target_id=${id}&csrf_token=${CSRF}`
          })
          .then(r => r.json())
          .then(d => {
            if (d.success) {
              const badge = document.getElementById('status-badge-' + id);
              const isNowActive = d.new_status === 'active';
              badge.className = `role-badge status-${d.new_status}`;
              badge.textContent = d.new_status.charAt(0).toUpperCase() + d.new_status.slice(1);
              this.style.background = isNowActive ? '#fff7ed' : '#f0fdf4';
              this.style.color = isNowActive ? '#ea580c' : '#16a34a';
              this.querySelector('i').className = isNowActive ? 'fas fa-ban' : 'fas fa-check';
              this.dataset.status = d.new_status;
              this.title = isNowActive ? 'Suspend' : 'Activate';
              showToast(isNowActive ? 'Admin activated.' : 'Admin suspended.', isNowActive ? 'success' : 'warning');
            } else {
              showToast(d.message || 'Error', 'danger');
            }
          });
      });
    });

    // Delete
    let deleteTarget = null;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function() {
        deleteTarget = this.dataset.id;
        document.getElementById('deleteAdminName').textContent = this.dataset.name;
        deleteModal.show();
      });
    });

    document.getElementById('confirmDelete').addEventListener('click', function() {
      if (!deleteTarget) return;
      fetch('', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `action=delete&target_id=${deleteTarget}&csrf_token=${CSRF}`
        })
        .then(r => r.json())
        .then(d => {
          deleteModal.hide();
          if (d.success) {
            const row = document.querySelector(`.btn-delete[data-id="${deleteTarget}"]`).closest('tr');
            row.style.opacity = '0.4';
            row.style.pointerEvents = 'none';
            showToast('Admin account suspended.', 'warning');
          } else {
            showToast(d.message || 'Error', 'danger');
          }
          deleteTarget = null;
        });
    });

    function showToast(msg, type = 'success') {
      const t = document.createElement('div');
      t.className = `alert alert-${type} shadow-sm`;
      t.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;min-width:260px;border-radius:10px;font-size:.85rem;';
      t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'} me-2"></i>${msg}`;
      document.body.appendChild(t);
      setTimeout(() => t.remove(), 3000);
    }
  </script>
</body>

</html>