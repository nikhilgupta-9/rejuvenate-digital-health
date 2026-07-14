<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once dirname(__DIR__) . '/util/function.php';
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$my_id = (int)$_SESSION['admin_id'];

// Determine my role
$stmt = $conn->prepare("SELECT role FROM admin_user WHERE id = ?");
$stmt->bind_param('i', $my_id);
$stmt->execute();
$stmt->bind_result($my_role);
$stmt->fetch();
$stmt->close();

$is_super = ($my_role === 'super_admin');

// Which admin are we editing?
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : $my_id;

// Non-super can only edit themselves
if (!$is_super && $target_id !== $my_id) {
    $target_id = $my_id;
}

// Load target admin
$stmt = $conn->prepare(
    "SELECT id, username, email, first_name, last_name, role, status,
            last_login, last_login_ip, created_at, failed_attempts, notes, password_changed_at
     FROM admin_user WHERE id = ? LIMIT 1"
);
$stmt->bind_param('i', $target_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    $_SESSION['admin_error'] = 'Admin account not found.';
    header('Location: all-admin.php');
    exit();
}

$editing_self = ($target_id === $my_id);

// ── CSRF ──────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors  = [];
$success = '';

// ── POST: update profile ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $errors[] = 'Security token mismatch. Please refresh.';
    } else {
        $fn    = trim($_POST['first_name'] ?? '');
        $ln    = trim($_POST['last_name']  ?? '');
        $email = trim($_POST['email']       ?? '');
        $uname = trim($_POST['username']    ?? '');
        $notes = trim($_POST['notes']       ?? '');

        if (empty($fn))   $errors[] = 'First name is required.';
        if (empty($ln))   $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($uname) < 3) $errors[] = 'Username must be at least 3 characters.';

        // Role/status (super_admin only, and can't demote themselves)
        $new_role   = $admin['role'];
        $new_status = $admin['status'];
        if ($is_super && !$editing_self) {
            $allowed_roles = ['super_admin', 'admin', 'manager'];
            if (in_array($_POST['role'] ?? '', $allowed_roles)) {
                $new_role = $_POST['role'];
            }
            $allowed_status = ['active', 'inactive', 'suspended'];
            if (in_array($_POST['status'] ?? '', $allowed_status)) {
                $new_status = $_POST['status'];
            }
        }

        // Uniqueness (exclude current admin)
        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM admin_user WHERE email = ? AND id != ? LIMIT 1");
            $chk->bind_param('si', $email, $target_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'Email already used by another account.';
            $chk->close();

            $chk2 = $conn->prepare("SELECT id FROM admin_user WHERE username = ? AND id != ? LIMIT 1");
            $chk2->bind_param('si', $uname, $target_id);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) $errors[] = 'Username already taken.';
            $chk2->close();
        }

        if (empty($errors)) {
            $upd = $conn->prepare(
                "UPDATE admin_user SET first_name=?, last_name=?, email=?, username=?, role=?, status=?, notes=?, updated_at=NOW()
                 WHERE id=?"
            );
            $upd->bind_param('sssssssi', $fn, $ln, $email, $uname, $new_role, $new_status, $notes, $target_id);
            $upd->execute();
            $upd->close();

            // Update session if editing self
            if ($editing_self) {
                $_SESSION['admin_user'] = $uname;
            }

            $admin = array_merge($admin, [
                'first_name' => $fn,
                'last_name' => $ln,
                'email' => $email,
                'username' => $uname,
                'role' => $new_role,
                'status' => $new_status,
                'notes' => $notes,
            ]);
            $success = 'Profile updated successfully.';
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── POST: change password ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {

    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $errors[] = 'Security token mismatch. Please refresh.';
    } else {
        $cur_pw  = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']      ?? '';
        $new_pw2 = $_POST['new_password2']     ?? '';

        // Super admin editing another admin doesn't need current password
        $need_current = $editing_self || !$is_super;

        if ($need_current) {
            $stmt = $conn->prepare("SELECT password, failed_attempts, locked_until FROM admin_user WHERE id = ?");
            $stmt->bind_param('i', $target_id);
            $stmt->execute();
            $stmt->bind_result($hash, $fa, $lu);
            $stmt->fetch();
            $stmt->close();

            if ($lu && strtotime($lu) > time()) {
                $errors[] = 'Account locked. Try again later.';
            } elseif (!password_verify($cur_pw, $hash)) {
                $new_fa = $fa + 1;
                $lock = ($new_fa >= 5) ? date('Y-m-d H:i:s', time() + 1800) : null;
                $upd2 = $conn->prepare("UPDATE admin_user SET failed_attempts=?, locked_until=? WHERE id=?");
                $upd2->bind_param('isi', $new_fa, $lock, $target_id);
                $upd2->execute();
                $upd2->close();
                $left = max(0, 5 - $new_fa);
                $errors[] = 'Current password incorrect.' . ($left > 0 ? " ($left attempts left)" : ' Account locked 30 min.');
            }
        }

        if (empty($errors)) {
            $pw_errs = [];
            if (strlen($new_pw) < 8)                  $pw_errs[] = '8+ characters';
            if (!preg_match('/[A-Z]/', $new_pw))       $pw_errs[] = 'uppercase letter';
            if (!preg_match('/[a-z]/', $new_pw))       $pw_errs[] = 'lowercase letter';
            if (!preg_match('/[0-9]/', $new_pw))       $pw_errs[] = 'number';
            if (!preg_match('/[^A-Za-z0-9]/', $new_pw)) $pw_errs[] = 'special character';
            if ($new_pw !== $new_pw2)                  $pw_errs[] = 'passwords must match';

            if ($pw_errs) {
                $errors[] = 'Password requirements: ' . implode(', ', $pw_errs) . '.';
            } else {
                $new_hash = password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 12]);
                $upd3 = $conn->prepare(
                    "UPDATE admin_user SET password=?, password_changed_at=NOW(), failed_attempts=0, locked_until=NULL WHERE id=?"
                );
                $upd3->bind_param('si', $new_hash, $target_id);
                $upd3->execute();
                $upd3->close();

                $success = 'Password changed successfully.';
                error_log("Admin password changed for ID $target_id by admin ID $my_id");
            }
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$favicon = get_favicon();
$display_name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: $admin['username'];
$initials = strtoupper(
    substr($admin['first_name'] ?? $admin['username'], 0, 1) .
        substr($admin['last_name'] ?? '', 0, 1)
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Admin — <?= htmlspecialchars($display_name) ?> | REJUVENATE</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
    <?php include 'links.php'; ?>
    <style>
        :root {
            --pri: #0C74C5;
            --acc: #02c9b8;
        }

        .profile-card {
            border-radius: 14px;
            border: none;
            box-shadow: 0 2px 16px rgba(0, 0, 0, .08);
        }

        .avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--pri);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 auto 12px;
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

        .section-divider {
            border-top: 1px solid #f1f5f9;
            margin: 20px 0 18px;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            color: #6b7280;
            padding: 7px 0;
            border-bottom: 1px solid #f9fafb;
        }

        .meta-row:last-child {
            border-bottom: none;
        }

        .meta-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            color: #6b7280;
            flex-shrink: 0;
        }

        .pw-bar {
            height: 4px;
            border-radius: 2px;
            background: #e5e7eb;
            margin-top: 6px;
        }

        .pw-fill {
            height: 100%;
            border-radius: 2px;
            width: 0;
            transition: .3s;
        }

        .req-list {
            list-style: none;
            padding: 0;
            margin: 6px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .req-list li {
            font-size: .74rem;
            padding: 2px 8px;
            border-radius: 50px;
            background: #f1f5f9;
            color: #9ca3af;
            transition: .2s;
        }

        .req-list li.ok {
            background: #dcfce7;
            color: #15803d;
        }

        .req-list li.bad {
            background: #fef2f2;
            color: #dc2626;
        }

        .page-header-bar {
            background: #fff;
            border-radius: 12px;
            padding: 18px 22px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
            margin-bottom: 20px;
        }

        .tab-pane {
            padding-top: 4px;
        }

        .nav-tabs .nav-link {
            color: #6b7280;
            border: none;
            padding: 10px 18px;
            font-size: .88rem;
            font-weight: 600;
        }

        .nav-tabs .nav-link.active {
            color: var(--pri);
            border-bottom: 2px solid var(--pri);
            background: none;
        }

        .nav-tabs {
            border-bottom: 2px solid #f1f5f9;
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

                <!-- Page header -->
                <div class="page-header-bar d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-700" style="color:#1f2937;">
                            <?= $editing_self ? 'My Profile' : 'Edit Admin' ?>
                        </h4>
                        <p class="mb-0 mt-1" style="font-size:.82rem;color:#6b7280;">
                            <?= htmlspecialchars($display_name) ?> — <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>
                        </p>
                    </div>
                    <a href="all-admin.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                        <i class="fas fa-arrow-left me-1"></i>All Admins
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= implode(' ', array_map('htmlspecialchars', $errors)) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">

                    <!-- Left: Profile summary card -->
                    <div class="col-lg-3">
                        <div class="profile-card card p-4 text-center mb-3">
                            <div class="avatar-lg"><?= $initials ?></div>
                            <h6 class="fw-700 mb-1"><?= htmlspecialchars($display_name) ?></h6>
                            <div class="mb-2">
                                <span class="role-badge role-<?= $admin['role'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>
                                </span>
                            </div>
                            <span class="role-badge status-<?= $admin['status'] ?>">
                                <?= ucfirst($admin['status']) ?>
                            </span>
                            <?php if ($editing_self): ?>
                                <div class="mt-2" style="font-size:.75rem;color:#9ca3af;">That's you!</div>
                            <?php endif; ?>
                        </div>

                        <div class="profile-card card p-4">
                            <h6 class="fw-700 mb-3" style="color:#374151;font-size:.85rem;">Account Info</h6>
                            <div class="meta-row">
                                <div class="meta-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <div style="font-size:.7rem;color:#9ca3af;">Username</div>
                                    <div class="fw-600 text-dark">@<?= htmlspecialchars($admin['username']) ?></div>
                                </div>
                            </div>
                            <div class="meta-row">
                                <div class="meta-icon"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <div style="font-size:.7rem;color:#9ca3af;">Email</div>
                                    <div class="fw-600 text-dark" style="font-size:.8rem;word-break:break-all;"><?= htmlspecialchars($admin['email']) ?></div>
                                </div>
                            </div>
                            <div class="meta-row">
                                <div class="meta-icon"><i class="fas fa-clock"></i></div>
                                <div>
                                    <div style="font-size:.7rem;color:#9ca3af;">Last Login</div>
                                    <div class="fw-600 text-dark"><?= $admin['last_login'] ? date('d M Y H:i', strtotime($admin['last_login'])) : 'Never' ?></div>
                                </div>
                            </div>
                            <div class="meta-row">
                                <div class="meta-icon"><i class="fas fa-calendar"></i></div>
                                <div>
                                    <div style="font-size:.7rem;color:#9ca3af;">Created</div>
                                    <div class="fw-600 text-dark"><?= date('d M Y', strtotime($admin['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php if ($admin['password_changed_at']): ?>
                                <div class="meta-row">
                                    <div class="meta-icon"><i class="fas fa-key"></i></div>
                                    <div>
                                        <div style="font-size:.7rem;color:#9ca3af;">Password Changed</div>
                                        <div class="fw-600 text-dark"><?= date('d M Y', strtotime($admin['password_changed_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($admin['failed_attempts'] > 0): ?>
                                <div class="meta-row">
                                    <div class="meta-icon"><i class="fas fa-exclamation-triangle text-warning"></i></div>
                                    <div>
                                        <div style="font-size:.7rem;color:#9ca3af;">Failed Logins</div>
                                        <div class="fw-600 text-warning"><?= (int)$admin['failed_attempts'] ?> attempts</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Edit forms -->
                    <div class="col-lg-9">
                        <div class="profile-card card">
                            <div class="card-body p-4">

                                <!-- Tabs -->
                                <ul class="nav nav-tabs mb-4">
                                    <li class="nav-item">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProfile">
                                            <i class="fas fa-user me-2"></i>Profile
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPassword">
                                            <i class="fas fa-lock me-2"></i>Password
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content">

                                    <!-- ── Profile Tab ── -->
                                    <div class="tab-pane fade show active" id="tabProfile">
                                        <form method="post" action="?id=<?= $target_id ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="first_name"
                                                        value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="last_name"
                                                        value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                                        <input type="email" class="form-control" name="email"
                                                            value="<?= htmlspecialchars($admin['email']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">@</span>
                                                        <input type="text" class="form-control" name="username"
                                                            value="<?= htmlspecialchars($admin['username']) ?>"
                                                            required pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50">
                                                    </div>
                                                </div>

                                                <?php if ($is_super && !$editing_self): ?>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Role</label>
                                                        <select class="form-select" name="role">
                                                            <option value="super_admin" <?= $admin['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                            <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                            <option value="manager" <?= $admin['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status">
                                                            <option value="active" <?= $admin['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="inactive" <?= $admin['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            <option value="suspended" <?= $admin['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                        </select>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="col-12">
                                                    <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                                                    <textarea class="form-control" name="notes" rows="2" maxlength="500"
                                                        placeholder="Internal notes…"><?= htmlspecialchars($admin['notes'] ?? '') ?></textarea>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <button type="submit" name="update_profile" class="btn text-white fw-600"
                                                    style="background:var(--pri);border-radius:8px;padding:9px 22px;">
                                                    <i class="fas fa-save me-2"></i>Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- ── Password Tab ── -->
                                    <div class="tab-pane fade" id="tabPassword">
                                        <form method="post" action="?id=<?= $target_id ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                            <?php if ($editing_self || !$is_super): ?>
                                                <div class="mb-3">
                                                    <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="current_password"
                                                            required autocomplete="current-password" placeholder="Your current password">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            onclick="toggleVis2('current_password','ei3')">
                                                            <i class="fas fa-eye" id="ei3"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info mb-3" style="font-size:.83rem;border-radius:8px;">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    As Super Admin, you can set a new password without the current one.
                                                </div>
                                            <?php endif; ?>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="new_password" id="newPw"
                                                            required autocomplete="new-password" placeholder="New password"
                                                            oninput="evalPw2(this.value)">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            onclick="toggleVis2('newPw','ei4')">
                                                            <i class="fas fa-eye" id="ei4"></i>
                                                        </button>
                                                    </div>
                                                    <div class="pw-bar">
                                                        <div class="pw-fill" id="pwFill2"></div>
                                                    </div>
                                                    <ul class="req-list" id="reqs2">
                                                        <li id="r2-len">8+ chars</li>
                                                        <li id="r2-upper">Uppercase</li>
                                                        <li id="r2-lower">Lowercase</li>
                                                        <li id="r2-num">Number</li>
                                                        <li id="r2-special">Special char</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="new_password2" id="newPw2"
                                                            required autocomplete="new-password" placeholder="Repeat password"
                                                            oninput="checkMatch2()">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            onclick="toggleVis2('newPw2','ei5')">
                                                            <i class="fas fa-eye" id="ei5"></i>
                                                        </button>
                                                    </div>
                                                    <div id="matchMsg2" class="form-text mt-1"></div>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <button type="submit" name="change_password" id="pwSubmit"
                                                    class="btn text-white fw-600"
                                                    style="background:#dc2626;border-radius:8px;padding:9px 22px;" disabled>
                                                    <i class="fas fa-key me-2"></i>Change Password
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                </div><!-- /tab-content -->
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php include 'footer.php'; ?>
    </section>

    <script>
        const colors2 = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
        const checks2 = {
            'r2-len': v => v.length >= 8,
            'r2-upper': v => /[A-Z]/.test(v),
            'r2-lower': v => /[a-z]/.test(v),
            'r2-num': v => /[0-9]/.test(v),
            'r2-special': v => /[^A-Za-z0-9]/.test(v),
        };

        function evalPw2(val) {
            let score = 0;
            for (const [id, fn] of Object.entries(checks2)) {
                const el = document.getElementById(id);
                const ok = fn(val);
                el.className = ok ? 'ok' : (val.length ? 'bad' : '');
                if (ok) score++;
            }
            const fill = document.getElementById('pwFill2');
            fill.style.width = (score / 5 * 100) + '%';
            fill.style.background = colors2[score - 1] || '#e5e7eb';
            checkPwSubmit();
        }

        function checkMatch2() {
            const p1 = document.getElementById('newPw').value;
            const p2 = document.getElementById('newPw2').value;
            const msg = document.getElementById('matchMsg2');
            if (!p2) {
                msg.textContent = '';
            } else if (p1 === p2) {
                msg.textContent = '✓ Match';
                msg.style.color = '#16a34a';
            } else {
                msg.textContent = '✗ No match';
                msg.style.color = '#dc2626';
            }
            checkPwSubmit();
        }

        function checkPwSubmit() {
            const p1 = document.getElementById('newPw').value;
            const p2 = document.getElementById('newPw2').value;
            const allOk = Object.values(checks2).every(fn => fn(p1));
            document.getElementById('pwSubmit').disabled = !(allOk && p1 === p2 && p2.length > 0);
        }

        function toggleVis2(id, iconId) {
            const el = document.getElementById(id);
            const ic = document.getElementById(iconId);
            el.type = el.type === 'password' ? 'text' : 'password';
            ic.classList.toggle('fa-eye');
            ic.classList.toggle('fa-eye-slash');
        }

        // Keep active tab after form submission
        <?php if ($success && strpos($success, 'Password') !== false): ?>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelector('[data-bs-target="#tabPassword"]').click();
            });
        <?php endif; ?>
    </script>
</body>

</html>