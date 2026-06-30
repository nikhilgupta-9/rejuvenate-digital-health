<?php
$active_page ??= '';
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="school-sidebar" id="schoolSidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo"><?= strtoupper(substr($teacher_school, 0, 1)) ?></div>
    <div class="s-name"><?= htmlspecialchars($teacher_school) ?></div>
    <div class="s-sub">Teacher Portal</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" <?= $active_page==='dashboard' ? 'class="active"' : '' ?>>
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <div class="nav-label">Students</div>
    <a href="students.php" <?= $active_page==='students' ? 'class="active"' : '' ?>>
      <i class="fas fa-users"></i> My Students
    </a>
    <a href="health-records.php" <?= $active_page==='health' ? 'class="active"' : '' ?>>
      <i class="fas fa-heartbeat"></i> Health Records
    </a>
    <div class="nav-label">Account</div>
    <a href="profile.php" <?= $active_page==='profile' ? 'class="active"' : '' ?>>
      <i class="fas fa-user-circle"></i> My Profile
    </a>
    <a href="abha.php" <?= $active_page==='abha' ? 'class="active"' : '' ?>>
      <i class="fas fa-id-card"></i> My ABHA
    </a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </nav>
  <div class="sidebar-footer">
    <i class="fas fa-user-circle me-1"></i> <strong><?= htmlspecialchars($teacher_name) ?></strong>
  </div>
</aside>

<script>
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
