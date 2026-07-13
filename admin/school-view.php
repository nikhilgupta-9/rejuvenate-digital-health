<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: auth/login.php");
    exit();
}
include "db-conn.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: schools-list.php");
    exit();
}

$school = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM schools WHERE id=$id"));
if (!$school) {
    header("Location: schools-list.php");
    exit();
}

$school_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM school_users WHERE school_id=$id ORDER BY id ASC LIMIT 1"));

$members_res = mysqli_query($conn, "SELECT type, COUNT(*) as cnt FROM school_members WHERE school_id=$id GROUP BY type");
$mc = ['Teacher' => 0, 'Student' => 0, 'Staff' => 0];
while ($r = mysqli_fetch_assoc($members_res))
    $mc[$r['type']] = $r['cnt'];

$type_filter = $_GET['type'] ?? 'all';
$member_search = trim($_GET['mq'] ?? '');
$m_where = "WHERE school_id=$id";
if ($type_filter !== 'all' && in_array($type_filter, ['Teacher', 'Student', 'Staff'])) {
    $m_where .= " AND type='" . mysqli_real_escape_string($conn, $type_filter) . "'";
}
if ($member_search !== '') {
    $mq = mysqli_real_escape_string($conn, $member_search);
    $m_where .= " AND (name LIKE '%$mq%' OR member_uid LIKE '%$mq%' OR email LIKE '%$mq%')";
}
$members_list = mysqli_query($conn, "SELECT * FROM school_members $m_where ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | <?= htmlspecialchars($school['school_name']) ?></title>
    <?php include "links.php"; ?>
    <style>
        /* page-specific only */
        .info-block {
            margin-bottom: 16px;
        }

        .info-block .lbl {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #888;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .info-block .val {
            font-size: .92rem;
            color: #333;
            font-weight: 500;
        }

        .school-avatar {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 12px;
        }

        .status-pill {
            border-radius: 20px;
            padding: 4px 14px;
            font-size: .75rem;
            font-weight: 700;
            display: inline-block;
        }

        .member-chip {
            border-radius: 6px;
            padding: 4px 10px;
            font-size: .75rem;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include "header.php"; ?>
        <section class="main_content dashboard_part">
            <div class="container-fluid g-0">
                <div class="row">
                    <div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div>
                </div>
            </div>

            <div class="main_content_iner">
                <div class="container-fluid p-0">

                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb" style="font-size:.8rem;">
                            <li class="breadcrumb"><a href="index.php">Dashboard  >  </a></li>
                            <li class="breadcrumb"><a href="schools-list.php">  Schools  ></a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($school['school_name']) ?></li>
                        </ol>
                    </nav>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); endif; ?>

                    <div class="row g-3">

                        <!-- Left Column -->
                        <div class="col-lg-4">

                            <!-- School Card -->
                            <div class="white_card mb_30">
                                <div class="white_card_body text-center pt-4 pb-3">
                                    <?php if (file_exists("../".$school['logo'])): ?>
                                        <img src="../<?= htmlspecialchars($school['logo']) ?>" class="school-avatar mb-3">
                                    <?php else: ?>
                                        <div class="school-avatar-text mx-auto mb-3">
                                            <?= strtoupper(substr($school['school_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($school['school_name']) ?></h5>
                                    <p class="text-muted mb-2" style="font-size:.8rem;">
                                        <?= htmlspecialchars($school['school_uid']) ?>
                                    </p>

                                    <?php
                                    $pill_colors = ['Active' => '#2dc653', 'Pending' => '#f77f00', 'Inactive' => '#6c757d', 'Rejected' => '#ef233c'];
                                    $pc = $pill_colors[$school['status']] ?? '#6c757d';
                                    ?>
                                    <span class="status-pill mb-3 d-inline-block"
                                        style="background:<?= $pc ?>20; color:<?= $pc ?>; border:1px solid <?= $pc ?>50;"><?= $school['status'] ?></span>

                                    <div class="mt-2 d-grid gap-2">
                                        <?php if ($school['status'] === 'Pending'): ?>
                                            <a href="school-approve.php?id=<?= $id ?>&action=approve"
                                                class="btn btn-success btn-sm"
                                                onclick="return confirm('Approve this school?')"><i
                                                    class="fas fa-check me-1"></i> Approve</a>
                                            <a href="school-approve.php?id=<?= $id ?>&action=reject"
                                                class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject?')"><i
                                                    class="fas fa-times me-1"></i> Reject</a>
                                        <?php elseif ($school['status'] === 'Active'): ?>
                                            <a href="school-approve.php?id=<?= $id ?>&action=deactivate"
                                                class="btn btn-warning btn-sm" onclick="return confirm('Deactivate?')"><i
                                                    class="fas fa-ban me-1"></i> Deactivate</a>
                                        <?php elseif ($school['status'] === 'Inactive'): ?>
                                            <a href="school-approve.php?id=<?= $id ?>&action=activate"
                                                class="btn btn-success btn-sm" onclick="return confirm('Activate?')"><i
                                                    class="fas fa-check-circle me-1"></i> Activate</a>
                                        <?php endif; ?>
                                        <a href="edit-school.php?id=<?= $id ?>"
                                            class="btn btn-outline-primary btn-sm"><i class="fas fa-edit me-1"></i> Edit
                                            School</a>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="confirmDeleteSchool(<?= $id ?>, '<?= htmlspecialchars(addslashes($school['school_name'])) ?>')">
                                            <i class="fas fa-trash me-1"></i> Delete School
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Member Stats -->
                            <div class="white_card mb_30">
                                <div class="white_card_header">
                                    <div class="main-title">
                                        <h3 class="m-0">Member Overview</h3>
                                    </div>
                                </div>
                                <div class="white_card_body">
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded mb-2"
                                        style="background:#e0f2fe;">
                                        <span style="font-size:.85rem;"><i class="fas fa-user-graduate me-2"
                                                style="color:#0277bd;"></i>Students</span>
                                        <span class="fw-bold" style="color:#0277bd;"><?= $mc['Student'] ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded mb-2"
                                        style="background:#e8f5e9;">
                                        <span style="font-size:.85rem;"><i class="fas fa-chalkboard-teacher me-2"
                                                style="color:#2e7d32;"></i>Teachers</span>
                                        <span class="fw-bold" style="color:#2e7d32;"><?= $mc['Teacher'] ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded"
                                        style="background:#f3e5f5;">
                                        <span style="font-size:.85rem;"><i class="fas fa-user-tie me-2"
                                                style="color:#6a1b9a;"></i>Staff</span>
                                        <span class="fw-bold" style="color:#6a1b9a;"><?= $mc['Staff'] ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Admin Account -->
                            <?php if ($school_user): ?>
                                <div class="white_card mb_30">
                                    <div class="white_card_header">
                                        <div class="main-title">
                                            <h3 class="m-0">School Admin</h3>
                                        </div>
                                    </div>
                                    <div class="white_card_body">
                                        <div class="info-block">
                                            <div class="lbl">Name</div>
                                            <div class="val"><?= htmlspecialchars($school_user['name']) ?></div>
                                        </div>
                                        <div class="info-block">
                                            <div class="lbl">Email</div>
                                            <div class="val"><?= htmlspecialchars($school_user['email']) ?></div>
                                        </div>
                                        <div class="info-block">
                                            <div class="lbl">Role</div>
                                            <div class="val"><?= ucfirst(str_replace('_', ' ', $school_user['role'])) ?>
                                            </div>
                                        </div>
                                        <div class="info-block">
                                            <div class="lbl">Last Login</div>
                                            <div class="val">
                                                <?= $school_user['last_login'] ? date('d M Y, h:i A', strtotime($school_user['last_login'])) : '<span class="text-muted">Never</span>' ?>
                                            </div>
                                        </div>
                                        <div class="info-block mb-0">
                                            <div class="lbl">Account Status</div>
                                            <div class="val">
                                                <span
                                                    style="background:<?= $school_user['status'] === 'Active' ? '#e8f5e9' : '#fce4ec' ?>; color:<?= $school_user['status'] === 'Active' ? '#2e7d32' : '#c62828' ?>; border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:700;"><?= $school_user['status'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                        <!-- Right Column -->
                        <div class="col-lg-8">

                            <!-- School Details -->
                            <div class="white_card mb_30">
                                <div class="white_card_header">
                                    <div class="main-title">
                                        <h3 class="m-0">School Details</h3>
                                    </div>
                                </div>
                                <div class="white_card_body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-block">
                                                <div class="lbl">Principal</div>
                                                <div class="val"><?= htmlspecialchars($school['principal_name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-block">
                                                <div class="lbl">Board</div>
                                                <div class="val"><span
                                                        class="badge bg-light text-dark border"><?= $school['board'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-block">
                                                <div class="lbl">Type</div>
                                                <div class="val"><?= $school['school_type'] ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-block">
                                                <div class="lbl">Email</div>
                                                <div class="val"><?= htmlspecialchars($school['email']) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-block">
                                                <div class="lbl">Phone</div>
                                                <div class="val"><?= htmlspecialchars($school['phone']) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-block">
                                                <div class="lbl">Reg. Number</div>
                                                <div class="val">
                                                    <?= $school['registration_number'] ? htmlspecialchars($school['registration_number']) : '<span class="text-muted fst-italic">Not provided</span>' ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-block">
                                                <div class="lbl">Registered On</div>
                                                <div class="val">
                                                    <?= date('d M Y, h:i A', strtotime($school['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="info-block">
                                                <div class="lbl">Address</div>
                                                <div class="val"><?= htmlspecialchars($school['address']) ?>,
                                                    <?= htmlspecialchars($school['city']) ?>,
                                                    <?= htmlspecialchars($school['state']) ?> –
                                                    <?= htmlspecialchars($school['pincode']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($school['approved_at']): ?>
                                            <div class="col-md-6">
                                                <div class="info-block mb-0">
                                                    <div class="lbl">Approved On</div>
                                                    <div class="val">
                                                        <?= date('d M Y, h:i A', strtotime($school['approved_at'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($school['rejection_reason']): ?>
                                            <div class="col-md-12 mt-2">
                                                <div class="alert alert-danger mb-0" style="font-size:.85rem;">
                                                    <strong>Rejection Reason:</strong>
                                                    <?= htmlspecialchars($school['rejection_reason']) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Members -->
                            <div class="white_card mb_30">
                                <div class="white_card_header">
                                    <div
                                        class="box_header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="main-title">
                                            <h3 class="m-0">Members <span
                                                    class="badge bg-secondary ms-2 text-light"><?= array_sum($mc) ?></span></h3>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                                                data-bs-toggle="dropdown">
                                                <i class="fas fa-user-plus me-1"></i> Add Member
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item"
                                                        href="add-school-member.php?school_id=<?= $id ?>&type=Student"><i
                                                            class="fas fa-user-graduate me-2 text-primary"></i>Add
                                                        Student</a></li>
                                                <li><a class="dropdown-item"
                                                        href="add-school-member.php?school_id=<?= $id ?>&type=Teacher"><i
                                                            class="fas fa-chalkboard-teacher me-2 text-success"></i>Add
                                                        Teacher</a></li>
                                                <li><a class="dropdown-item"
                                                        href="add-school-member.php?school_id=<?= $id ?>&type=Staff"><i
                                                            class="fas fa-user-tie me-2" style="color:#6a1b9a;"></i>Add
                                                        Staff</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="white_card_body">
                                    <form method="GET" class="row g-2 align-items-end mb-3">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" name="mq"
                                                value="<?= htmlspecialchars($member_search) ?>"
                                                placeholder="Search name, ID or email...">
                                        </div>
                                        <div class="col-md-4">
                                            <select class="form-control form-control-sm" name="type">
                                                <?php foreach (['all' => 'All Types', 'Student' => 'Students', 'Teacher' => 'Teachers', 'Staff' => 'Staff'] as $v => $l): ?>
                                                    <option value="<?= $v ?>" <?= $type_filter === $v ? 'selected' : '' ?>>
                                                        <?= $l ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-outline-secondary btn-sm w-100"><i
                                                    class="fas fa-filter me-1"></i>Filter</button>
                                        </div>
                                    </form>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr style="background:#eaf4fd;border:1px solid #b3d4f0;">
                                                    <th style="font-size:.72rem;text-transform:uppercase;">Name</th>
                                                    <th style="font-size:.72rem;text-transform:uppercase;">Type</th>
                                                    <th style="font-size:.72rem;text-transform:uppercase;">Class /
                                                        Designation</th>
                                                    <th style="font-size:.72rem;text-transform:uppercase;">Status</th>
                                                    <th style="font-size:.72rem;text-transform:uppercase;">ABHA</th>
                                                    <th style="font-size:.72rem;text-transform:uppercase;">Added</th>
                                                    <th style="font-size:.72rem;text-transform:uppercase;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($members_list) === 0): ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4 text-muted">No members
                                                            found.</td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php while ($m = mysqli_fetch_assoc($members_list)):
                                                    $tc = ['Teacher' => ['#e8f5e9', '#2e7d32'], 'Student' => ['#e0f2fe', '#0277bd'], 'Staff' => ['#f3e5f5', '#6a1b9a']][$m['type']] ?? ['#f5f5f5', '#333'];
                                                    $stc = ['Active' => ['#e8f5e9', '#2e7d32'], 'Inactive' => ['#f5f5f5', '#6c757d'], 'Pending' => ['#fff8e1', '#f77f00']][$m['status']] ?? ['#f5f5f5', '#333'];
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold" style="font-size:.85rem;">
                                                                <?= htmlspecialchars($m['name']) ?>
                                                            </div>
                                                            <small
                                                                class="text-muted"><?= htmlspecialchars($m['member_uid']) ?></small>
                                                        </td>
                                                        <td><span
                                                                style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>;border-radius:6px;padding:2px 10px;font-size:.72rem;font-weight:700;"><?= $m['type'] ?></span>
                                                        </td>
                                                        <td style="font-size:.83rem;">
                                                            <?= htmlspecialchars($m['type'] === 'Student' ? ($m['class'] ?? '—') : ($m['designation'] ?? '—')) ?>
                                                        </td>
                                                        <td><span
                                                                style="background:<?= $stc[0] ?>;color:<?= $stc[1] ?>;border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:700;"><?= $m['status'] ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($m['abha_linked']): ?>
                                                                <span
                                                                    style="background:#e8f5e9;color:#2e7d32;border-radius:6px;padding:2px 8px;font-size:.7rem;font-weight:700;"><i
                                                                        class="fas fa-link me-1"></i>Linked</span>
                                                            <?php else: ?>
                                                                <span style="color:#bbb;font-size:.75rem;">Not linked</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><small
                                                                class="text-muted"><?= date('d M Y', strtotime($m['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <a href="edit-school-member.php?id=<?= $m['id'] ?>"
                                                                class="tbl-action-btn bg-info text-white" title="Edit"><i
                                                                    class="fas fa-edit"></i></a>
                                                            <a href="upload-medical-record.php?for=school_member&member_id=<?= $m['id'] ?>"
                                                                class="tbl-action-btn bg-success text-white" title="Upload Medical Record"><i
                                                                    class="fas fa-file-medical"></i></a>
                                                            <button type="button"
                                                                class="tbl-action-btn bg-danger text-white" title="Remove"
                                                                onclick="confirmDeleteMember(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>', <?= $id ?>)">
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

                    <!-- Delete School Confirmation Modal -->
                    <div class="modal fade" id="deleteSchoolModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i
                                            class="fas fa-exclamation-triangle text-danger me-2"></i>Delete School</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="delete-school.php">
                                    <div class="modal-body">
                                        <p>Are you sure you want to permanently delete <strong
                                                id="delSchoolName"></strong>?</p>
                                        <div class="alert alert-warning mb-0" style="font-size:.85rem;">
                                            This will also delete its admin account, and <strong>all</strong> teachers,
                                            students, staff and their health records. This cannot be undone.
                                        </div>
                                        <input type="hidden" name="id" id="delSchoolId">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>
                                            Delete Permanently</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Member Confirmation Modal -->
                    <div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i
                                            class="fas fa-exclamation-triangle text-danger me-2"></i>Remove Member</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="delete-school-member.php">
                                    <div class="modal-body">
                                        <p>Remove <strong id="delMemberName"></strong> from this school? This will also
                                            delete their health profile records.</p>
                                        <input type="hidden" name="id" id="delMemberId">
                                        <input type="hidden" name="redirect" id="delMemberRedirect">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>
                                            Remove</button>
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
                        function confirmDeleteMember(id, name, schoolId) {
                            document.getElementById('delMemberId').value = id;
                            document.getElementById('delMemberName').textContent = name;
                            document.getElementById('delMemberRedirect').value = 'school-view.php?id=' + schoolId;
                            new bootstrap.Modal(document.getElementById('deleteMemberModal')).show();
                        }
                    </script>

                </div>
            </div>
            <?php include "footer.php"; ?>
</body>

</html>