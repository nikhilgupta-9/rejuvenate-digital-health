<?php
// School Admin Sidebar — include in every school admin page
// $active_page should be set before include (e.g., 'dashboard', 'members', 'add', 'health', 'abha', 'profile')
$active_page = $active_page ?? '';
?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="school-sidebar" id="schoolSidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo"><?= strtoupper(substr($school_name, 0, 1)) ?></div>
    <div class="s-name"><?= htmlspecialchars($school_name) ?></div>
    <div class="s-sub">School Portal</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="<?= $base_path ?? '../' ?>dashboard.php" <?= $active_page==='dashboard' ? 'class="active"' : '' ?>>
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <div class="nav-label">Members</div>
    <?php
    // Pending self-registrations badge
    $_pend_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE school_id=$school_id AND status='Pending'");
    $_pend_c = mysqli_fetch_assoc($_pend_q)['c'] ?? 0;
    ?>
    <a href="<?= $base_path ?? '../' ?>members/list.php" <?= $active_page==='members' ? 'class="active"' : '' ?>>
      <i class="fas fa-users"></i> All Members
      <?php if ($_pend_c > 0): ?>
        <span style="background:#ea580c;color:#fff;border-radius:10px;padding:1px 7px;font-size:.65rem;font-weight:700;margin-left:auto;"><?= $_pend_c ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $base_path ?? '../' ?>members/add.php" <?= $active_page==='add' ? 'class="active"' : '' ?>>
      <i class="fas fa-user-plus"></i> Add Member
    </a>
    <a href="<?= $base_path ?? '../' ?>members/import.php" <?= $active_page==='import' ? 'class="active"' : '' ?>>
      <i class="fas fa-file-import"></i> Bulk Import
    </a>
    <a href="<?= $base_path ?? '../' ?>members/list.php?type=Student">
      <i class="fas fa-user-graduate"></i> Students
    </a>
    <a href="<?= $base_path ?? '../' ?>members/list.php?type=Teacher">
      <i class="fas fa-chalkboard-teacher"></i> Teachers
    </a>
    <a href="<?= $base_path ?? '../' ?>members/list.php?type=Staff">
      <i class="fas fa-user-tie"></i> Staff
    </a>
    <div class="nav-label">Health</div>
    <a href="<?= $base_path ?? '../' ?>health/records.php" <?= $active_page==='health' ? 'class="active"' : '' ?>>
      <i class="fas fa-heartbeat"></i> Health Records
    </a>
    <a href="<?= $base_path ?? '../' ?>health/abha.php" <?= $active_page==='abha' ? 'class="active"' : '' ?>>
      <i class="fas fa-id-card"></i> ABHA Management
    </a>
    <div class="nav-label">Account</div>
    <a href="<?= $base_path ?? '../' ?>profile.php" <?= $active_page==='profile' ? 'class="active"' : '' ?>>
      <i class="fas fa-cog"></i> School Profile
    </a>
    <a href="<?= $base_path ?? '../' ?>auth/logout.php">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>
  <div class="sidebar-footer">
    <i class="fas fa-user-circle me-1"></i> <strong><?= htmlspecialchars($school_user_name) ?></strong>
  </div>
</aside>

<script>
// Mobile sidebar toggle (inline so it works before DOMContentLoaded)
document.addEventListener('DOMContentLoaded', function() {
  var toggler = document.getElementById('sidebarToggle');
  var sidebar  = document.getElementById('schoolSidebar');
  var overlay  = document.getElementById('sidebarOverlay');
  if (!toggler) return;
  toggler.addEventListener('click', function() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  overlay.addEventListener('click', function() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  });
});
</script>
