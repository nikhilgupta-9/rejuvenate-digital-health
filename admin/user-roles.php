<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/util/function.php';
require_once 'db-conn.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  header('Location: auth/login.php');
  exit();
}

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

// ── Bootstrap DB tables ───────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(20) DEFAULT '#0C74C5',
    icon VARCHAR(50) DEFAULT 'fa-user-shield',
    is_system TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
    created_at DATETIME DEFAULT NOW()
)");

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

// ── Seed default roles if empty ───────────────────────────────────────────────
$roleCount = (int)$conn->query("SELECT COUNT(*) FROM admin_roles")->fetch_row()[0];
if ($roleCount === 0) {
  $conn->query("INSERT INTO admin_roles (name, display_name, description, color, icon, is_system) VALUES
        ('super_admin', 'Super Admin', 'Full unrestricted access to all system features and settings.', '#dc2626', 'fa-crown', 1),
        ('admin',       'Admin',       'Full access to all features except super admin management.',    '#0C74C5', 'fa-user-shield', 1),
        ('manager',     'Manager',     'Limited access for day-to-day operational management.',         '#16a34a', 'fa-user-tie', 1)");
}

// ── Seed default permissions if empty ────────────────────────────────────────
$permCount = (int)$conn->query("SELECT COUNT(*) FROM admin_permissions")->fetch_row()[0];
if ($permCount === 0) {
  $seeds = [
    ['view_dashboard',        'View Dashboard',          'Access the admin dashboard overview',                'Dashboard',       1],
    ['view_admins',           'View Admin Users',        'View the list of admin accounts',                    'User Management', 10],
    ['create_admin',          'Create Admin',            'Create new admin accounts',                          'User Management', 11],
    ['edit_admin',            'Edit Admin',              'Edit existing admin profiles',                       'User Management', 12],
    ['delete_admin',          'Delete Admin',            'Suspend or permanently delete admin accounts',       'User Management', 13],
    ['manage_roles',          'Manage Roles',            'Configure role permissions matrix',                  'User Management', 14],
    ['view_patients',         'View Patients',           'View the patient list and records',                  'Patients',        20],
    ['add_patient',           'Add Patient',             'Register new patients',                              'Patients',        21],
    ['edit_patient',          'Edit Patient',            'Edit patient profiles and medical records',          'Patients',        22],
    ['delete_patient',        'Delete Patient',          'Remove patient accounts',                            'Patients',        23],
    ['view_doctors',          'View Doctors',            'View the doctors list',                              'Doctors',         30],
    ['add_doctor',            'Add Doctor',              'Register new doctors',                               'Doctors',         31],
    ['edit_doctor',           'Edit Doctor',             'Edit doctor profiles and specializations',           'Doctors',         32],
    ['delete_doctor',         'Remove Doctor',           'Remove doctors from the system',                     'Doctors',         33],
    ['manage_schedules',      'Manage Schedules',        'Create and edit doctor availability schedules',      'Doctors',         34],
    ['view_appointments',     'View Appointments',       'View all appointment bookings',                      'Appointments',    40],
    ['manage_appointments',   'Manage Appointments',     'Confirm, reschedule, or update appointments',        'Appointments',    41],
    ['cancel_appointment',    'Cancel Appointment',      'Cancel patient appointments',                        'Appointments',    42],
    ['view_products',         'View Products',           'View the product catalog',                           'Products',        50],
    ['add_product',           'Add Product',             'Add new products to catalog',                        'Products',        51],
    ['edit_product',          'Edit Product',            'Edit product details and pricing',                   'Products',        52],
    ['delete_product',        'Delete Product',          'Remove products from catalog',                       'Products',        53],
    ['view_orders',           'View Orders',             'View all customer orders',                           'Orders',          60],
    ['process_order',         'Process Order',           'Update order status and manage fulfillment',         'Orders',          61],
    ['cancel_order',          'Cancel Order',            'Cancel customer orders',                             'Orders',          62],
    ['manage_banners',        'Manage Banners',          'Add, edit, or remove website banners',               'Content',         70],
    ['manage_gallery',        'Manage Gallery',          'Manage the photo gallery',                           'Content',         71],
    ['manage_blogs',          'Manage Blogs',            'Write and publish blog posts',                       'Content',         72],
    ['manage_faq',            'Manage FAQ',              'Edit the FAQ section',                               'Content',         73],
    ['manage_testimonials',   'Manage Testimonials',     'Add or edit customer testimonials',                  'Content',         74],
    ['view_schools',          'View Schools',            'View the schools list',                              'Schools',         80],
    ['approve_school',        'Approve School',          'Approve or reject school registrations',             'Schools',         81],
    ['edit_school',           'Edit School',             'Edit school details and settings',                   'Schools',         82],
    ['manage_members',        'Manage Members',          'Add and manage school health members',               'Schools',         83],
    ['view_abha',             'View ABHA',               'Access the ABHA Health ID management panel',         'ABHA',            90],
    ['process_abha_requests', 'Process ABHA Requests',   'Approve or reject ABHA link requests',               'ABHA',            91],
    ['view_reports',          'View Reports',            'Access reporting and analytics dashboards',          'Reports',        100],
    ['export_reports',        'Export Reports',          'Download and export data as CSV/Excel',              'Reports',        101],
    ['view_settings',         'View Settings',           'View system configuration settings',                'Settings',       110],
    ['manage_settings',       'Manage Settings',         'Modify system-wide configuration settings',         'Settings',       111],
  ];

  $ins = $conn->prepare("INSERT IGNORE INTO admin_permissions (name, display_name, description, module, sort_order) VALUES (?,?,?,?,?)");
  foreach ($seeds as $s) {
    $ins->bind_param('ssssi', $s[0], $s[1], $s[2], $s[3], $s[4]);
    $ins->execute();
  }
  $ins->close();

  // Fetch IDs after seeding
  $roles_map = [];
  $res = $conn->query("SELECT id, name FROM admin_roles");
  while ($r = $res->fetch_assoc()) $roles_map[$r['name']] = $r['id'];

  $all_perms = [];
  $res = $conn->query("SELECT id, name FROM admin_permissions");
  while ($p = $res->fetch_assoc()) $all_perms[$p['name']] = $p['id'];

  // super_admin: all
  $rp = $conn->prepare("INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) VALUES (?,?)");
  foreach ($all_perms as $perm_id) {
    $rp->bind_param('ii', $roles_map['super_admin'], $perm_id);
    $rp->execute();
  }

  // admin: all except manage_roles, delete_admin, manage_settings
  $admin_exclude = ['manage_roles', 'delete_admin', 'manage_settings'];
  foreach ($all_perms as $pname => $perm_id) {
    if (!in_array($pname, $admin_exclude)) {
      $rp->bind_param('ii', $roles_map['admin'], $perm_id);
      $rp->execute();
    }
  }

  // manager: limited set
  $manager_allowed = [
    'view_dashboard',
    'view_patients',
    'view_doctors',
    'view_appointments',
    'manage_appointments',
    'view_products',
    'view_orders',
    'view_schools',
    'view_abha',
    'view_reports',
    'manage_faq',
    'manage_testimonials',
  ];
  foreach ($manager_allowed as $pname) {
    if (isset($all_perms[$pname])) {
      $rp->bind_param('ii', $roles_map['manager'], $all_perms[$pname]);
      $rp->execute();
    }
  }
  $rp->close();
}

// ── AJAX: save permissions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  if (!$is_super) {
    echo json_encode(['success' => false, 'message' => 'Only Super Admins can modify permissions.']);
    exit();
  }
  if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit();
  }

  if ($_POST['action'] === 'save_permissions') {
    $role_id  = (int)($_POST['role_id']  ?? 0);
    $perm_ids = array_filter(array_map('intval', (array)($_POST['permissions'] ?? [])));

    if (!$role_id) {
      echo json_encode(['success' => false, 'message' => 'Invalid role.']);
      exit();
    }

    $del = $conn->prepare("DELETE FROM admin_role_permissions WHERE role_id = ?");
    $del->bind_param('i', $role_id);
    $del->execute();
    $del->close();

    if ($perm_ids) {
      $ins = $conn->prepare("INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) VALUES (?,?)");
      foreach ($perm_ids as $pid) {
        $ins->bind_param('ii', $role_id, $pid);
        $ins->execute();
      }
      $ins->close();
    }

    $upd = $conn->prepare("UPDATE admin_roles SET updated_at = NOW() WHERE id = ?");
    $upd->bind_param('i', $role_id);
    $upd->execute();
    $upd->close();

    echo json_encode(['success' => true, 'message' => 'Permissions saved successfully.']);
    exit();
  }

  echo json_encode(['success' => false, 'message' => 'Unknown action.']);
  exit();
}

// ── Load data ─────────────────────────────────────────────────────────────────
$roles = [];
$res = $conn->query(
  "SELECT r.*,
        (SELECT COUNT(*) FROM admin_role_permissions rp WHERE rp.role_id = r.id) as perm_count,
        (SELECT COUNT(*) FROM admin_user au WHERE au.role = r.name AND au.status = 'active') as user_count
     FROM admin_roles r ORDER BY FIELD(r.name,'super_admin','admin','manager'), r.created_at"
);
while ($row = $res->fetch_assoc()) $roles[] = $row;

$permissions_by_module = [];
$res = $conn->query("SELECT * FROM admin_permissions ORDER BY module, sort_order, display_name");
while ($row = $res->fetch_assoc()) $permissions_by_module[$row['module']][] = $row;

$role_perms = [];
$res = $conn->query("SELECT role_id, permission_id FROM admin_role_permissions");
while ($row = $res->fetch_assoc()) $role_perms[$row['role_id']][$row['permission_id']] = true;

$total_perms   = (int)$conn->query("SELECT COUNT(*) FROM admin_permissions")->fetch_row()[0];
$total_modules = count($permissions_by_module);
$last_updated  = $conn->query("SELECT MAX(updated_at) FROM admin_roles")->fetch_row()[0];

$flash_success = $_SESSION['admin_success'] ?? '';
$flash_error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$favicon = get_favicon();

$module_icons = [
  'Dashboard'       => 'fa-tachometer-alt',
  'User Management' => 'fa-user-cog',
  'Patients'        => 'fa-users',
  'Doctors'         => 'fa-user-md',
  'Appointments'    => 'fa-calendar-check',
  'Products'        => 'fa-box-open',
  'Orders'          => 'fa-shopping-cart',
  'Content'         => 'fa-file-alt',
  'Schools'         => 'fa-school',
  'ABHA'            => 'fa-id-card',
  'Reports'         => 'fa-chart-bar',
  'Settings'        => 'fa-cog',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>User Roles & Permissions | REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
  <?php include 'links.php'; ?>
  <style>
    :root {
      --pri: #0C74C5;
      --acc: #02c9b8;
    }

    .page-header-bar {
      background: #fff;
      border-radius: 12px;
      padding: 18px 22px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
      margin-bottom: 20px;
    }

    .stat-card {
      border-radius: 12px;
      border: none;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    }

    .stat-icon {
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

    .role-card {
      border-radius: 14px;
      border: 2px solid #e5e7eb;
      background: #fff;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
      transition: .2s;
      cursor: default;
    }

    .role-card.active-tab {
      border-color: var(--pri);
      box-shadow: 0 4px 18px rgba(12, 116, 197, .18);
    }

    .role-icon {
      width: 52px;
      height: 52px;
      border-radius: 13px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
    }

    .role-badge {
      font-size: .7rem;
      padding: .25rem .6rem;
      border-radius: 50px;
      font-weight: 600;
    }

    .matrix-wrap {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
      overflow: hidden;
      margin-bottom: 80px;
    }

    .matrix-scroll {
      overflow-x: auto;
    }

    table.matrix {
      width: 100%;
      border-collapse: collapse;
      min-width: 700px;
    }

    table.matrix thead th {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #f8fafc;
      padding: 14px 16px;
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6b7280;
      border-bottom: 2px solid #e5e7eb;
    }

    table.matrix thead th.perm-col {
      text-align: left;
      min-width: 240px;
    }

    table.matrix thead th.role-col {
      text-align: center;
      width: 120px;
    }

    table.matrix tbody tr:hover td {
      background: #f9fafb;
    }

    table.matrix tbody td {
      padding: 10px 16px;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }

    table.matrix tbody td.role-col {
      text-align: center;
    }

    tr.mod-header td {
      background: linear-gradient(90deg, #eff6ff, #f0fdf4);
      padding: 8px 16px;
    }

    .mod-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #2563eb;
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .perm-name {
      font-size: .84rem;
      font-weight: 600;
      color: #1f2937;
    }

    .perm-desc {
      font-size: .71rem;
      color: #9ca3af;
      margin-top: 2px;
    }

    input.perm-check {
      width: 17px;
      height: 17px;
      cursor: pointer;
      accent-color: var(--pri);
    }

    .sel-all {
      font-size: .66rem;
      color: var(--pri);
      cursor: pointer;
      text-decoration: underline;
      display: block;
      margin-top: 4px;
      white-space: nowrap;
    }

    .save-fab {
      position: fixed;
      bottom: 28px;
      right: 28px;
      z-index: 200;
      border-radius: 50px;
      padding: 13px 30px;
      font-weight: 600;
      font-size: .92rem;
      box-shadow: 0 6px 22px rgba(12, 116, 197, .35);
    }

    .toast-stack {
      position: fixed;
      top: 75px;
      right: 18px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 8px;
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

    @media (max-width: 768px) {
      .save-fab {
        bottom: 16px;
        right: 16px;
        padding: 11px 20px;
      }
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
            <h4 class="mb-0 fw-700" style="color:#1f2937;"><i class="fas fa-shield-alt me-2" style="color:var(--pri);"></i>User Roles &amp; Permissions</h4>
            <p class="mb-0 mt-1" style="font-size:.82rem;color:#6b7280;">
              Configure what each role can access and do within the system
              <?php if ($last_updated): ?>
                &mdash; Last updated <?= date('d M Y, g:i a', strtotime($last_updated)) ?>
              <?php endif; ?>
            </p>
          </div>
          <div class="d-flex gap-2">
            <a href="permissions.php" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-list me-1"></i>All Permissions
            </a>
            <?php if ($is_super): ?>
              <a href="admin-create.php" class="btn btn-sm" style="background:var(--pri);color:#fff;">
                <i class="fas fa-user-plus me-1"></i>Create Admin
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Stats row -->
        <div class="row g-3 mb-4">
          <div class="col-sm-4">
            <div class="card stat-card p-3">
              <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-user-shield"></i></div>
                <div>
                  <div class="stat-val"><?= count($roles) ?></div>
                  <div class="stat-lbl">Total Roles</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="card stat-card p-3">
              <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-key"></i></div>
                <div>
                  <div class="stat-val"><?= $total_perms ?></div>
                  <div class="stat-lbl">Permissions (<?= $total_modules ?> modules)</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="card stat-card p-3">
              <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#fff7ed;color:#d97706;"><i class="fas fa-users-cog"></i></div>
                <div>
                  <div class="stat-val"><?= array_sum(array_column($roles, 'user_count')) ?></div>
                  <div class="stat-lbl">Active Admin Users</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Role cards -->
        <div class="row g-3 mb-4">
          <?php foreach ($roles as $role):
            $roleColors = [
              'super_admin' => ['bg' => '#fef2f2', 'fg' => '#dc2626', 'ico_bg' => '#dc2626'],
              'admin'       => ['bg' => '#eff6ff', 'fg' => '#2563eb', 'ico_bg' => '#0C74C5'],
              'manager'     => ['bg' => '#f0fdf4', 'fg' => '#16a34a', 'ico_bg' => '#16a34a'],
            ];
            $rc = $roleColors[$role['name']] ?? ['bg' => '#f3f4f6', 'fg' => '#374151', 'ico_bg' => '#6b7280'];
          ?>
            <div class="col-md-4">
              <div class="role-card p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="role-icon" style="background:<?= $rc['ico_bg'] ?>;color:#fff;">
                    <i class="fas <?= htmlspecialchars($role['icon']) ?>"></i>
                  </div>
                  <div>
                    <div class="fw-700" style="font-size:1rem;color:#1f2937;"><?= htmlspecialchars($role['display_name']) ?></div>
                    <code style="font-size:.72rem;color:#6b7280;"><?= htmlspecialchars($role['name']) ?></code>
                  </div>
                  <?php if ($role['is_system']): ?>
                    <span class="ms-auto badge bg-secondary" style="font-size:.65rem;">System</span>
                  <?php endif; ?>
                </div>
                <p class="mb-3" style="font-size:.8rem;color:#6b7280;line-height:1.5;"><?= htmlspecialchars($role['description']) ?></p>
                <div class="d-flex gap-3">
                  <div class="text-center">
                    <div class="fw-700" style="font-size:1.2rem;color:<?= $rc['ico_bg'] ?>;"><?= $role['perm_count'] ?></div>
                    <div style="font-size:.7rem;color:#6b7280;">Permissions</div>
                  </div>
                  <div class="vr"></div>
                  <div class="text-center">
                    <div class="fw-700" style="font-size:1.2rem;color:#374151;"><?= $role['user_count'] ?></div>
                    <div style="font-size:.7rem;color:#6b7280;">Active Users</div>
                  </div>
                  <div class="ms-auto">
                    <a href="all-admin.php?role=<?= urlencode($role['name']) ?>" class="btn btn-sm" style="background:<?= $rc['bg'] ?>;color:<?= $rc['fg'] ?>;font-size:.72rem;">
                      <i class="fas fa-users me-1"></i>View Users
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Permission Matrix -->
        <div class="matrix-wrap">
          <div class="d-flex justify-content-between align-items-center p-3 border-bottom" style="background:#f8fafc;">
            <div>
              <h6 class="mb-0 fw-700" style="color:#1f2937;"><i class="fas fa-table me-2" style="color:var(--pri);"></i>Permission Matrix</h6>
              <p class="mb-0 mt-1" style="font-size:.74rem;color:#6b7280;">
                <?php if ($is_super): ?>Click checkboxes to grant or revoke permissions, then save per role.<?php else: ?>Read-only — only Super Admins can modify permissions.<?php endif; ?>
              </p>
            </div>
            <?php if ($is_super): ?>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-danger" id="btnClearAll" title="Deselect all (current role tab)">
                  <i class="fas fa-times me-1"></i>Clear All
                </button>
                <button class="btn btn-sm btn-outline-success" id="btnCheckAll" title="Select all (current role tab)">
                  <i class="fas fa-check-double me-1"></i>Check All
                </button>
              </div>
            <?php endif; ?>
          </div>

          <!-- Role tabs -->
          <ul class="nav nav-tabs px-3 pt-2" id="roleTabs">
            <?php foreach ($roles as $i => $role): ?>
              <li class="nav-item">
                <a class="nav-link <?= $i === 0 ? 'active' : '' ?>" href="#" data-role-id="<?= $role['id'] ?>" data-role-name="<?= htmlspecialchars($role['name']) ?>">
                  <i class="fas <?= htmlspecialchars($role['icon']) ?> me-1"></i>
                  <?= htmlspecialchars($role['display_name']) ?>
                  <span class="badge ms-1 role-<?= $role['name'] ?>" style="font-size:.65rem;" id="permCount-<?= $role['id'] ?>"><?= $role['perm_count'] ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="matrix-scroll">
            <?php foreach ($roles as $i => $role): ?>
              <div class="role-panel" id="panel-<?= $role['id'] ?>" style="<?= $i !== 0 ? 'display:none;' : '' ?>">
                <table class="matrix">
                  <thead>
                    <tr>
                      <th class="perm-col">Permission</th>
                      <th style="min-width:200px;color:#6b7280;">Description</th>
                      <th class="role-col" style="color:<?= htmlspecialchars($role['color']) ?>;">
                        <i class="fas <?= htmlspecialchars($role['icon']) ?> me-1"></i><?= htmlspecialchars($role['display_name']) ?>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($permissions_by_module as $module => $perms):
                      $modIcon = $module_icons[$module] ?? 'fa-circle';
                    ?>
                      <tr class="mod-header">
                        <td colspan="3">
                          <div class="mod-label">
                            <i class="fas <?= $modIcon ?>"></i>
                            <?= htmlspecialchars($module) ?>
                            <span class="badge" style="background:#dbeafe;color:#1d4ed8;font-size:.65rem;"><?= count($perms) ?> permissions</span>
                            <?php if ($is_super): ?>
                              <span class="ms-2" style="font-size:.68rem;">
                                <a href="#" class="text-success text-decoration-none mod-check-all" data-role="<?= $role['id'] ?>" data-module="<?= htmlspecialchars($module) ?>">
                                  <i class="fas fa-check-square me-1"></i>All
                                </a>
                                &nbsp;
                                <a href="#" class="text-danger text-decoration-none mod-uncheck-all" data-role="<?= $role['id'] ?>" data-module="<?= htmlspecialchars($module) ?>">
                                  <i class="fas fa-times me-1"></i>None
                                </a>
                              </span>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                      <?php foreach ($perms as $perm):
                        $checked = isset($role_perms[$role['id']][$perm['id']]);
                      ?>
                        <tr>
                          <td>
                            <div class="perm-name"><?= htmlspecialchars($perm['display_name']) ?></div>
                            <code style="font-size:.67rem;color:#9ca3af;"><?= htmlspecialchars($perm['name']) ?></code>
                          </td>
                          <td>
                            <span class="perm-desc"><?= htmlspecialchars($perm['description']) ?></span>
                          </td>
                          <td class="role-col">
                            <?php if ($is_super): ?>
                              <input type="checkbox" class="perm-check"
                                data-role-id="<?= $role['id'] ?>"
                                data-perm-id="<?= $perm['id'] ?>"
                                data-module="<?= htmlspecialchars($module) ?>"
                                <?= $checked ? 'checked' : '' ?>>
                            <?php else: ?>
                              <i class="fas fa-<?= $checked ? 'check-circle text-success' : 'times-circle text-danger' ?>" style="font-size:.9rem;"></i>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php if ($is_super): ?>
                  <div class="p-3 border-top d-flex justify-content-between align-items-center" style="background:#f8fafc;">
                    <span style="font-size:.78rem;color:#6b7280;">
                      <span id="checkedCount-<?= $role['id'] ?>">0</span> of <?= $total_perms ?> permissions granted
                    </span>
                    <button class="btn btn-primary btn-save-role" data-role-id="<?= $role['id'] ?>" data-role-name="<?= htmlspecialchars($role['display_name']) ?>">
                      <i class="fas fa-save me-1"></i>Save <?= htmlspecialchars($role['display_name']) ?> Permissions
                    </button>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /container -->
    </div><!-- /main_content_iner -->
  </section>

  <!-- Toast stack -->
  <div class="toast-stack" id="toastStack"></div>

  <?php include 'footer.php'; ?>
  <script>
    (function() {
      const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

      // ── Tab switching ──────────────────────────────────────────────────────────
      document.querySelectorAll('#roleTabs .nav-link').forEach(tab => {
        tab.addEventListener('click', e => {
          e.preventDefault();
          document.querySelectorAll('#roleTabs .nav-link').forEach(t => t.classList.remove('active'));
          tab.classList.add('active');
          const rid = tab.dataset.roleId;
          document.querySelectorAll('.role-panel').forEach(p => p.style.display = 'none');
          document.getElementById('panel-' + rid).style.display = '';
          updateCount(rid);
        });
      });

      // ── Update checked count ───────────────────────────────────────────────────
      function updateCount(roleId) {
        const panel = document.getElementById('panel-' + roleId);
        if (!panel) return;
        const total = panel.querySelectorAll('input.perm-check').length;
        const checked = panel.querySelectorAll('input.perm-check:checked').length;
        const el = document.getElementById('checkedCount-' + roleId);
        if (el) el.textContent = checked;
        const badge = document.getElementById('permCount-' + roleId);
        if (badge) badge.textContent = checked;
      }

      // Init counts
      document.querySelectorAll('.role-panel').forEach(panel => {
        const rid = panel.id.replace('panel-', '');
        updateCount(rid);
      });

      // Update on checkbox change
      document.querySelectorAll('input.perm-check').forEach(cb => {
        cb.addEventListener('change', () => updateCount(cb.dataset.roleId));
      });

      // ── Module all/none ────────────────────────────────────────────────────────
      document.querySelectorAll('.mod-check-all').forEach(a => {
        a.addEventListener('click', e => {
          e.preventDefault();
          const {
            role,
            module
          } = a.dataset;
          document.querySelectorAll(`input.perm-check[data-role-id="${role}"][data-module="${module}"]`)
            .forEach(cb => cb.checked = true);
          updateCount(role);
        });
      });
      document.querySelectorAll('.mod-uncheck-all').forEach(a => {
        a.addEventListener('click', e => {
          e.preventDefault();
          const {
            role,
            module
          } = a.dataset;
          document.querySelectorAll(`input.perm-check[data-role-id="${role}"][data-module="${module}"]`)
            .forEach(cb => cb.checked = false);
          updateCount(role);
        });
      });

      // ── Check All / Clear All buttons ─────────────────────────────────────────
      const activeRoleId = () => document.querySelector('#roleTabs .nav-link.active')?.dataset.roleId;

      document.getElementById('btnCheckAll')?.addEventListener('click', () => {
        const rid = activeRoleId();
        document.querySelectorAll(`#panel-${rid} input.perm-check`).forEach(cb => cb.checked = true);
        updateCount(rid);
      });
      document.getElementById('btnClearAll')?.addEventListener('click', () => {
        const rid = activeRoleId();
        document.querySelectorAll(`#panel-${rid} input.perm-check`).forEach(cb => cb.checked = false);
        updateCount(rid);
      });

      // ── Save ──────────────────────────────────────────────────────────────────
      document.querySelectorAll('.btn-save-role').forEach(btn => {
        btn.addEventListener('click', function() {
          const roleId = this.dataset.roleId;
          const roleName = this.dataset.roleName;
          const panel = document.getElementById('panel-' + roleId);
          const permIds = [...panel.querySelectorAll('input.perm-check:checked')].map(cb => cb.dataset.permId);

          const orig = this.innerHTML;
          this.disabled = true;
          this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

          const body = new FormData();
          body.append('action', 'save_permissions');
          body.append('csrf_token', CSRF);
          body.append('role_id', roleId);
          permIds.forEach(p => body.append('permissions[]', p));

          fetch(window.location.href, {
              method: 'POST',
              body
            })
            .then(r => r.json())
            .then(data => {
              showToast(data.success ? 'success' : 'danger', data.message);
              if (data.success) updateCount(roleId);
            })
            .catch(() => showToast('danger', 'Network error. Please try again.'))
            .finally(() => {
              this.disabled = false;
              this.innerHTML = orig;
            });
        });
      });

      // ── Toast ─────────────────────────────────────────────────────────────────
      function showToast(type, msg) {
        const stack = document.getElementById('toastStack');
        const t = document.createElement('div');
        t.className = `alert alert-${type} alert-dismissible shadow`;
        t.style.cssText = 'min-width:280px;max-width:360px;font-size:.84rem;';
        t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${msg}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
        stack.appendChild(t);
        setTimeout(() => t.remove(), 5000);
      }
    })();
  </script>
</body>

</html>