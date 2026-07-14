<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE type='Student'"))['c'];
$total_teachers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE type='Teacher'"))['c'];
$total_staff    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE type='Staff'"))['c'];
$total_abha     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE abha_linked=1"))['c'];

$school_filter = intval($_GET['school_id'] ?? 0);
$type_filter   = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($school_filter) $where .= " AND m.school_id=" . $school_filter;
if ($type_filter !== 'all' && in_array($type_filter, ['Teacher','Student','Staff'])) {
    $where .= " AND m.type='" . mysqli_real_escape_string($conn, $type_filter) . "'";
}
if ($status_filter !== 'all' && in_array($status_filter, ['Active','Inactive','Pending'])) {
    $where .= " AND m.status='" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if ($search !== '') {
    $q = mysqli_real_escape_string($conn, $search);
    $where .= " AND (m.name LIKE '%$q%' OR m.member_uid LIKE '%$q%' OR m.email LIKE '%$q%')";
}

$members_result = mysqli_query($conn, "SELECT m.*, s.school_name, s.school_uid
    FROM school_members m JOIN schools s ON s.id = m.school_id
    $where ORDER BY m.created_at DESC LIMIT 300");

$schools_dd = mysqli_query($conn, "SELECT id, school_name FROM schools ORDER BY school_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | School Members</title>
    <?php include "links.php"; ?>
</head>
<body>
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">School Members</h4>
                        <small class="text-muted">All students, teachers and staff across every registered school</small>
                    </div>
                    <a href="add-school-member.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-plus me-1"></i> Add Member
                    </a>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <!-- Stat Boxes -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-blue">
                            <i class="fas fa-user-graduate big-icon"></i>
                            <div class="num"><?= $total_students ?></div>
                            <div class="lbl">Students</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-green">
                            <i class="fas fa-chalkboard-teacher big-icon"></i>
                            <div class="num"><?= $total_teachers ?></div>
                            <div class="lbl">Teachers</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-teal">
                            <i class="fas fa-user-tie big-icon"></i>
                            <div class="num"><?= $total_staff ?></div>
                            <div class="lbl">Staff</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-warn">
                            <i class="fas fa-link big-icon"></i>
                            <div class="num"><?= $total_abha ?></div>
                            <div class="lbl">ABHA Linked</div>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="filter-card">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Search</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, ID, email...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">School</label>
                            <select class="form-control form-control-sm" name="school_id">
                                <option value="0">All Schools</option>
                                <?php mysqli_data_seek($schools_dd, 0); while ($sc = mysqli_fetch_assoc($schools_dd)): ?>
                                    <option value="<?= $sc['id'] ?>" <?= $school_filter===(int)$sc['id']?'selected':'' ?>><?= htmlspecialchars($sc['school_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Type</label>
                            <select class="form-control form-control-sm" name="type">
                                <?php foreach (['all'=>'All Types','Student'=>'Students','Teacher'=>'Teachers','Staff'=>'Staff'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= $type_filter===$v?'selected':'' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Status</label>
                            <select class="form-control form-control-sm" name="status">
                                <?php foreach (['all'=>'All Status','Active'=>'Active','Inactive'=>'Inactive','Pending'=>'Pending'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= $status_filter===$v?'selected':'' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title">
                                <h3 class="m-0">All Members <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($members_result) ?></span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr style="background:#eaf4fd;border:1px solid #b3d4f0;">
                                        <th style="font-size:.75rem;text-transform:uppercase;">#</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Member</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">School</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Type</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Class / Designation</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Status</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">ABHA</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (mysqli_num_rows($members_result) === 0): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>No members found.
                                    </td></tr>
                                <?php endif; ?>
                                <?php $i=1; while ($m = mysqli_fetch_assoc($members_result)):
                                    $tc = ['Teacher'=>['#e8f5e9','#2e7d32'],'Student'=>['#e0f2fe','#0277bd'],'Staff'=>['#f3e5f5','#6a1b9a']][$m['type']] ?? ['#f5f5f5','#333'];
                                    $stc = ['Active'=>['#e8f5e9','#2e7d32'],'Inactive'=>['#f5f5f5','#6c757d'],'Pending'=>['#fff8e1','#f77f00']][$m['status']] ?? ['#f5f5f5','#333'];
                                ?>
                                <tr>
                                    <td><small class="text-muted"><?= $i++ ?></small></td>
                                    <td>
                                        <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($m['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($m['member_uid']) ?></small>
                                    </td>
                                    <td>
                                        <a href="school-view.php?id=<?= $m['school_id'] ?>" style="font-size:.82rem;"><?= htmlspecialchars($m['school_name']) ?></a><br>
                                        <small class="text-muted"><?= htmlspecialchars($m['school_uid']) ?></small>
                                    </td>
                                    <td><span style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>;border-radius:6px;padding:2px 10px;font-size:.72rem;font-weight:700;"><?= $m['type'] ?></span></td>
                                    <td style="font-size:.83rem;"><?= htmlspecialchars($m['type']==='Student'?($m['class']??'—'):($m['designation']??'—')) ?></td>
                                    <td><span style="background:<?= $stc[0] ?>;color:<?= $stc[1] ?>;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;"><?= $m['status'] ?></span></td>
                                    <td>
                                        <?php if ($m['abha_linked']): ?>
                                            <span style="background:#e8f5e9;color:#2e7d32;border-radius:6px;padding:2px 8px;font-size:.7rem;font-weight:700;"><i class="fas fa-link me-1"></i>Linked</span>
                                        <?php else: ?>
                                            <span style="color:#bbb;font-size:.75rem;">Not linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit-school-member.php?id=<?= $m['id'] ?>" class="tbl-action-btn bg-info text-white" title="Edit"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="tbl-action-btn bg-danger text-white" title="Remove"
                                            onclick="confirmDeleteMember(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Delete Member Confirmation Modal -->
        <div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Remove Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="delete-school-member.php">
                        <div class="modal-body">
                            <p>Remove <strong id="delMemberName"></strong>? This will also delete their health profile records.</p>
                            <input type="hidden" name="id" id="delMemberId">
                            <input type="hidden" name="redirect" value="school-members.php">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> Remove</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            function confirmDeleteMember(id, name) {
                document.getElementById('delMemberId').value = id;
                document.getElementById('delMemberName').textContent = name;
                new bootstrap.Modal(document.getElementById('deleteMemberModal')).show();
            }
        </script>
        <?php include "footer.php"; ?>
</body>
</html>
