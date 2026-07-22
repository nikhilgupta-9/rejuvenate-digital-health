<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$tab = in_array($_GET['tab'] ?? '', ['patients','school']) ? $_GET['tab'] : 'patients';
$search = trim($_GET['q'] ?? '');

$total_patient_docs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM patient_documents"))['c'];
$total_school_docs  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_member_documents"))['c'];
$total_admin_docs   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM patient_documents WHERE uploaded_by_role='admin'"))['c'] + $total_school_docs;

if ($tab === 'patients') {
    $where = "WHERE 1=1";
    if ($search !== '') {
        $q = mysqli_real_escape_string($conn, $search);
        $where .= " AND (pd.document_name LIKE '%$q%' OR u.name LIKE '%$q%' OR u.mobile LIKE '%$q%')";
    }
    $records = mysqli_query($conn, "SELECT pd.*, u.name as patient_name, u.last_name as patient_last_name, u.mobile as patient_mobile,
        d.name as doctor_name, TRIM(CONCAT(au.first_name, ' ', au.last_name)) as admin_name
        FROM patient_documents pd
        JOIN users u ON u.id = pd.patient_id
        LEFT JOIN doctors d ON d.id = pd.doctor_id
        LEFT JOIN admin_user au ON au.id = pd.uploaded_by_admin_id
        $where ORDER BY pd.uploaded_at DESC LIMIT 300");
} else {
    $where = "WHERE 1=1";
    if ($search !== '') {
        $q = mysqli_real_escape_string($conn, $search);
        $where .= " AND (smd.document_name LIKE '%$q%' OR m.name LIKE '%$q%' OR s.school_name LIKE '%$q%')";
    }
    $records = mysqli_query($conn, "SELECT smd.*, m.name as member_name, m.type as member_type, m.member_uid,
        s.school_name, TRIM(CONCAT(au.first_name, ' ', au.last_name)) as admin_name
        FROM school_member_documents smd
        JOIN school_members m ON m.id = smd.member_id
        JOIN schools s ON s.id = smd.school_id
        LEFT JOIN admin_user au ON au.id = smd.uploaded_by_admin_id
        $where ORDER BY smd.uploaded_at DESC LIMIT 300");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Medical Records</title>
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
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Medical Records</h4>
                        <small class="text-muted">Upload and manage documents for patients and school members</small>
                    </div>
                    <a href="upload-medical-record.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload me-1"></i> Upload Record
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
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-box bg-stat-blue">
                            <i class="fas fa-file-medical big-icon"></i>
                            <div class="num"><?= $total_patient_docs ?></div>
                            <div class="lbl">Patient Documents</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-box bg-stat-teal">
                            <i class="fas fa-file-medical-alt big-icon"></i>
                            <div class="num"><?= $total_school_docs ?></div>
                            <div class="lbl">School Member Documents</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-box bg-stat-green">
                            <i class="fas fa-upload big-icon"></i>
                            <div class="num"><?= $total_admin_docs ?></div>
                            <div class="lbl">Uploaded by Admin</div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab==='patients'?'active':'' ?>" href="medical-records.php?tab=patients">
                            <i class="fas fa-user-injured me-1"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab==='school'?'active':'' ?>" href="medical-records.php?tab=school">
                            <i class="fas fa-user-graduate me-1"></i> School Members
                        </a>
                    </li>
                </ul>

                <!-- Search -->
                <div class="filter-card">
                    <form method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="tab" value="<?= $tab ?>">
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>"
                                placeholder="<?= $tab==='patients' ? 'Search by document title, patient name or mobile...' : 'Search by document title, member name or school...' ?>">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Search</button>
                        </div>
                        <?php if ($search): ?>
                        <div class="col-md-2">
                            <a href="medical-records.php?tab=<?= $tab ?>" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title">
                                <h3 class="m-0"><?= $tab==='patients' ? 'Patient Documents' : 'School Member Documents' ?> <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($records) ?></span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr style="background:#eaf4fd;border:1px solid #b3d4f0;">
                                        <th style="font-size:.75rem;text-transform:uppercase;">Document</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;"><?= $tab==='patients' ? 'Patient' : 'Member' ?></th>
                                        <?php if ($tab==='school'): ?><th style="font-size:.75rem;text-transform:uppercase;">School</th><?php endif; ?>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Uploaded By</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Date</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (mysqli_num_rows($records) === 0): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-file-medical fa-3x mb-3 d-block opacity-25"></i>No records found.
                                    </td></tr>
                                <?php endif; ?>
                                <?php while ($r = mysqli_fetch_assoc($records)):
                                    $ext = strtolower(pathinfo($r['file_path'], PATHINFO_EXTENSION));
                                    $icon = 'fa-file'; $color = '#6c757d';
                                    if ($ext === 'pdf') { $icon = 'fa-file-pdf'; $color = '#e74c3c'; }
                                    elseif (in_array($ext, ['jpg','jpeg','png'])) { $icon = 'fa-file-image'; $color = '#2980b9'; }
                                    elseif (in_array($ext, ['doc','docx'])) { $icon = 'fa-file-word'; $color = '#2c5aa0'; }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas <?= $icon ?>" style="color:<?= $color ?>;font-size:1.3rem;"></i>
                                            <div>
                                                <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($r['document_name']) ?></div>
                                                <?php if (!empty($r['description'])): ?>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($r['description'], 0, 60)) ?><?= strlen($r['description'])>60?'...':'' ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($tab==='patients'): ?>
                                            <div style="font-size:.85rem;"><?= htmlspecialchars(trim($r['patient_name'].' '.$r['patient_last_name'])) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($r['patient_mobile']) ?></small>
                                        <?php else: ?>
                                            <div style="font-size:.85rem;"><?= htmlspecialchars($r['member_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($r['member_type']) ?> &bull; <?= htmlspecialchars($r['member_uid']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($tab==='school'): ?>
                                        <td style="font-size:.83rem;"><?= htmlspecialchars($r['school_name']) ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($tab==='patients' && $r['doctor_name']): ?>
                                            <span style="font-size:.78rem;"><i class="fas fa-user-md me-1 text-info"></i>Dr. <?= htmlspecialchars($r['doctor_name']) ?></span>
                                        <?php elseif ($r['admin_name']): ?>
                                            <span style="font-size:.78rem;"><i class="fas fa-user-shield me-1 text-primary"></i><?= htmlspecialchars($r['admin_name']) ?></span>
                                        <?php else: ?>
                                            <span style="font-size:.78rem;" class="text-muted"><i class="fas fa-user-shield me-1"></i>Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= date('d M Y, h:i A', strtotime($r['uploaded_at'])) ?></small></td>
                                    <td>
                                        <a href="../<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="tbl-action-btn bg-primary text-white" title="View / Download"><i class="fas fa-eye"></i></a>
                                        <button type="button" class="tbl-action-btn bg-danger text-white" title="Delete"
                                            onclick="confirmDeleteRecord('<?= $tab ?>', <?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['document_name'])) ?>')">
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

        <!-- Delete Record Confirmation Modal -->
        <div class="modal fade" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="delete-medical-record.php">
                        <div class="modal-body">
                            <p>Delete <strong id="delRecordName"></strong>? The uploaded file will be permanently removed. This cannot be undone.</p>
                            <input type="hidden" name="id" id="delRecordId">
                            <input type="hidden" name="type" id="delRecordType">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            function confirmDeleteRecord(type, id, name) {
                document.getElementById('delRecordType').value = type;
                document.getElementById('delRecordId').value = id;
                document.getElementById('delRecordName').textContent = name;
                new bootstrap.Modal(document.getElementById('deleteRecordModal')).show();
            }
        </script>
        <?php include "footer.php"; ?>
</body>
</html>
