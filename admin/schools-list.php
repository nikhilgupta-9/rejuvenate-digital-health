<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: auth/login.php"); exit(); }
include "db-conn.php";

$total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM schools"))['c'];
$pending  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM schools WHERE status='Pending'"))['c'];
$active   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM schools WHERE status='Active'"))['c'];
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM schools WHERE status='Rejected'"))['c'];

$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$where  = ($filter !== 'all') ? "WHERE s.status = '" . mysqli_real_escape_string($conn, $filter) . "'" : 'WHERE 1=1';
if ($search !== '') {
    $q = mysqli_real_escape_string($conn, $search);
    $where .= " AND (s.school_name LIKE '%$q%' OR s.email LIKE '%$q%' OR s.city LIKE '%$q%')";
}

$schools_result = mysqli_query($conn, "SELECT s.*,
    (SELECT COUNT(*) FROM school_members sm WHERE sm.school_id=s.id AND sm.type='Student') as student_count,
    (SELECT COUNT(*) FROM school_members sm WHERE sm.school_id=s.id AND sm.type='Teacher') as teacher_count,
    (SELECT COUNT(*) FROM school_members sm WHERE sm.school_id=s.id AND sm.type='Staff')   as staff_count
    FROM schools s $where ORDER BY s.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Schools Management</title>
    <?php include "links.php"; ?>
    <!-- styles in assets/css/colors/default.css -->
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

                <!-- Heading -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Schools Management</h4>
                        <small class="text-muted">Manage registered schools, approvals and health modules</small>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($pending > 0): ?>
                        <a href="schools-list.php?status=Pending" class="btn btn-danger btn-sm">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= $pending ?> Pending
                        </a>
                        <?php endif; ?>
                        <a href="add-school.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Add School
                        </a>
                    </div>
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
                            <i class="fas fa-school big-icon"></i>
                            <div class="num"><?= $total ?></div>
                            <div class="lbl">Total Schools</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-warn">
                            <i class="fas fa-clock big-icon"></i>
                            <div class="num"><?= $pending ?></div>
                            <div class="lbl">Pending Approval</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-green">
                            <i class="fas fa-check-circle big-icon"></i>
                            <div class="num"><?= $active ?></div>
                            <div class="lbl">Active Schools</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-red">
                            <i class="fas fa-times-circle big-icon"></i>
                            <div class="num"><?= $rejected ?></div>
                            <div class="lbl">Rejected</div>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="filter-card">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Search School</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email, city...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Status</label>
                            <select class="form-control form-control-sm" name="status">
                                <?php foreach (['all'=>'All Schools','Pending'=>'Pending','Active'=>'Active','Inactive'=>'Inactive','Rejected'=>'Rejected'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title">
                                <h3 class="m-0">All Schools <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($schools_result) ?></span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr style="background:#eaf4fd;border:1px solid #b3d4f0;">
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">#</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">School</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Contact</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Board / Type</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Members</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Status</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Registered</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (mysqli_num_rows($schools_result) === 0): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-school fa-3x mb-3 d-block opacity-25"></i>No schools found.
                                    </td></tr>
                                <?php endif; ?>
                                <?php $i=1; while ($s = mysqli_fetch_assoc($schools_result)): ?>
                                <tr>
                                    <td><small class="text-muted"><?= $i++ ?></small></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($s['logo']): ?>
                                                <img src="../<?= htmlspecialchars($s['logo']) ?>" class="school-logo-sm">
                                            <?php else: ?>
                                                <div class="school-initial"><?= strtoupper(substr($s['school_name'],0,1)) ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($s['school_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($s['school_uid']) ?> &bull; <?= htmlspecialchars($s['city']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:.82rem;"><?= htmlspecialchars($s['email']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($s['phone']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border" style="font-size:.72rem;"><?= $s['board'] ?></span><br>
                                        <small class="text-muted"><?= $s['school_type'] ?></small>
                                    </td>
                                    <td>
                                        <span title="Students" style="font-size:.75rem; background:#e0f2fe; color:#0277bd; border-radius:4px; padding:2px 7px; margin-right:3px;"><i class="fas fa-user-graduate"></i> <?= $s['student_count'] ?></span>
                                        <span title="Teachers" style="font-size:.75rem; background:#e8f5e9; color:#2e7d32; border-radius:4px; padding:2px 7px; margin-right:3px;"><i class="fas fa-chalkboard-teacher"></i> <?= $s['teacher_count'] ?></span>
                                        <span title="Staff"    style="font-size:.75rem; background:#f3e5f5; color:#6a1b9a; border-radius:4px; padding:2px 7px;"><i class="fas fa-user-tie"></i> <?= $s['staff_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php $colors=['Pending'=>'#ffc107','Active'=>'#2dc653','Inactive'=>'#6c757d','Rejected'=>'#ef233c']; $c=$colors[$s['status']]??'#6c757d'; ?>
                                        <span style="background:<?= $c ?>20; color:<?= $c ?>; border:1px solid <?= $c ?>40; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:600;"><?= $s['status'] ?></span>
                                    </td>
                                    <td><small class="text-muted"><?= date('d M Y', strtotime($s['created_at'])) ?></small></td>
                                    <td>
                                        <a href="school-view.php?id=<?= $s['id'] ?>" class="tbl-action-btn bg-primary text-white" title="View"><i class="fas fa-eye"></i></a>
                                        <a href="edit-school.php?id=<?= $s['id'] ?>" class="tbl-action-btn bg-info text-white" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php if ($s['status']==='Pending'): ?>
                                            <a href="school-approve.php?id=<?= $s['id'] ?>&action=approve" class="tbl-action-btn bg-success text-white" title="Approve" onclick="return confirm('Approve this school?')"><i class="fas fa-check"></i></a>
                                            <a href="school-approve.php?id=<?= $s['id'] ?>&action=reject"  class="tbl-action-btn bg-danger text-white"  title="Reject"  onclick="return confirm('Reject this school?')"><i class="fas fa-times"></i></a>
                                        <?php elseif ($s['status']==='Active'): ?>
                                            <a href="school-approve.php?id=<?= $s['id'] ?>&action=deactivate" class="tbl-action-btn bg-warning text-white" title="Deactivate" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                                        <?php elseif ($s['status']==='Inactive'): ?>
                                            <a href="school-approve.php?id=<?= $s['id'] ?>&action=activate" class="tbl-action-btn bg-success text-white" title="Activate" onclick="return confirm('Activate?')"><i class="fas fa-check-circle"></i></a>
                                        <?php endif; ?>
                                        <button type="button" class="tbl-action-btn bg-danger text-white" title="Delete"
                                            onclick="confirmDeleteSchool(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['school_name'])) ?>')">
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
        <?php include "footer.php"; ?>

        <!-- Delete School Confirmation Modal -->
        <div class="modal fade" id="deleteSchoolModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete School</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="delete-school.php">
                        <div class="modal-body">
                            <p>Are you sure you want to permanently delete <strong id="delSchoolName"></strong>?</p>
                            <div class="alert alert-warning mb-0" style="font-size:.85rem;">
                                This will also delete its admin account, and <strong>all</strong> teachers, students, staff and their health records. This cannot be undone.
                            </div>
                            <input type="hidden" name="id" id="delSchoolId">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> Delete Permanently</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            function confirmDeleteSchool(id, name) {
                document.getElementById('delSchoolId').value = id;
                document.getElementById('delSchoolName').textContent = name;
                new bootstrap.Modal(document.getElementById('deleteSchoolModal')).show();
            }
        </script>
</body>
</html>
