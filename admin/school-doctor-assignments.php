<?php
include "functions.php"; // includes db-conn.php + enforces admin_jwt_guard()

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

/* school_doctor_assignments schema: see database/migration_school_membership_phase2.sql
 * Formal authorization — doctor/api/school-lookup-search.php only lets a
 * doctor search students at schools they have an active row here for. */

$page_message = '';
$page_message_type = '';

/* ── Assign ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $school_id = (int) ($_POST['school_id'] ?? 0);
        $doctor_id = (int) ($_POST['doctor_id'] ?? 0);
        if (!$school_id || !$doctor_id) {
            $page_message = 'Choose both a school and a doctor.';
            $page_message_type = 'warning';
        } else {
            $stmt = $conn->prepare("INSERT INTO school_doctor_assignments (school_id, doctor_id, assigned_by, status)
                                    VALUES (?, ?, ?, 'active')
                                    ON DUPLICATE KEY UPDATE status = 'active', assigned_by = VALUES(assigned_by), assigned_at = NOW()");
            $stmt->bind_param('iii', $school_id, $doctor_id, $admin_id);
            $ok = $stmt->execute();
            $page_message = $ok ? 'Doctor assigned to school.' : 'Failed to assign doctor.';
            $page_message_type = $ok ? 'success' : 'danger';
        }
    }
}

/* ── Toggle active/inactive or remove ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['row_action'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $id = (int) ($_POST['assignment_id'] ?? 0);
        if ($_POST['row_action'] === 'deactivate') {
            $s = $conn->prepare("UPDATE school_doctor_assignments SET status='inactive' WHERE id=?");
            $s->bind_param('i', $id); $s->execute();
            $page_message = 'Assignment deactivated.'; $page_message_type = 'success';
        } elseif ($_POST['row_action'] === 'activate') {
            $s = $conn->prepare("UPDATE school_doctor_assignments SET status='active' WHERE id=?");
            $s->bind_param('i', $id); $s->execute();
            $page_message = 'Assignment reactivated.'; $page_message_type = 'success';
        } elseif ($_POST['row_action'] === 'remove') {
            $s = $conn->prepare("DELETE FROM school_doctor_assignments WHERE id=?");
            $s->bind_param('i', $id); $s->execute();
            $page_message = 'Assignment removed.'; $page_message_type = 'success';
        }
    }
}

/* ── Dropdown data ── */
$schools = [];
$res = $conn->query("SELECT id, school_name FROM schools WHERE status='Active' ORDER BY school_name ASC");
if ($res) while ($r = $res->fetch_assoc()) $schools[] = $r;

$doctors = [];
$res = $conn->query("SELECT id, name, specialization FROM doctors WHERE status='Active' ORDER BY name ASC");
if ($res) while ($r = $res->fetch_assoc()) $doctors[] = $r;

/* ── List ── */
$assignments = [];
$res = $conn->query("
    SELECT sda.*, s.school_name, d.name AS doctor_name, d.specialization
    FROM school_doctor_assignments sda
    JOIN schools s ON s.id = sda.school_id
    JOIN doctors d ON d.id = sda.doctor_id
    ORDER BY sda.status ASC, s.school_name ASC, d.name ASC
");
if ($res) while ($r = $res->fetch_assoc()) $assignments[] = $r;
?>
<!DOCTYPE html>
<html lang="zxx">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>School Doctor Assignments | Admin Panel</title>
    <?php include "links.php"; ?>
</head>
<body class="crm_body_bg">
<?php include "header.php"; ?>

<section class="main_content dashboard_part large_header_bg">
    <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

    <div class="main_content_iner">
        <div class="container-fluid p-0 sm_padding_15px">
            <div class="row justify-content-center">
                <div class="col-lg-12">

                    <div class="white_card card_height_100 mb_30">
                        <div class="card-header bg-white border-0 py-3">
                            <h3 class="mb-0 fw-bold">School Doctor Assignments</h3>
                            <p class="text-muted mb-0 small">A doctor can only search/access students at schools they're assigned to here — see <code>doctor/api/school-lookup-search.php</code>.</p>
                        </div>
                        <div class="white_card_body">

                            <?php if ($page_message): ?>
                                <div class="alert alert-<?= htmlspecialchars($page_message_type) ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($page_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="row g-2 align-items-end mb-4">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="assign" value="1">
                                <div class="col-md-5">
                                    <label class="form-label">School</label>
                                    <select name="school_id" class="form-select" required>
                                        <option value="">Select school…</option>
                                        <?php foreach ($schools as $s): ?>
                                            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['school_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Doctor</label>
                                    <select name="doctor_id" class="form-select" required>
                                        <option value="">Select doctor…</option>
                                        <?php foreach ($doctors as $d): ?>
                                            <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?><?= $d['specialization'] ? ' — ' . htmlspecialchars($d['specialization']) : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary w-100"><i class="fas fa-link me-1"></i> Assign</button>
                                </div>
                            </form>

                            <div class="QA_section"><div class="QA_table mb_30"><div class="table-responsive">
                                <table class="table table-hover tbl-admin tbl-cards">
                                    <thead>
                                        <tr>
                                            <th>School</th>
                                            <th>Doctor</th>
                                            <th>Assigned</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($assignments)): ?>
                                            <tr class="empty-row"><td colspan="5">
                                                <i class="fas fa-user-md fa-3x mb-3 d-block opacity-25"></i>
                                                No assignments yet. Assign a doctor to a school above.
                                            </td></tr>
                                        <?php else: foreach ($assignments as $a): ?>
                                            <tr>
                                                <td data-label="School"><?= htmlspecialchars($a['school_name']) ?></td>
                                                <td data-label="Doctor"><?= htmlspecialchars($a['doctor_name']) ?><?php if ($a['specialization']): ?><div class="cell-sub"><?= htmlspecialchars($a['specialization']) ?></div><?php endif; ?></td>
                                                <td data-label="Assigned"><?= date('d M Y', strtotime($a['assigned_at'])) ?></td>
                                                <td data-label="Status"><?= $a['status'] === 'active' ? '<span class="pill pill-success">Active</span>' : '<span class="pill pill-muted">Inactive</span>' ?></td>
                                                <td data-label="Action" class="text-end">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="assignment_id" value="<?= (int) $a['id'] ?>">
                                                        <?php if ($a['status'] === 'active'): ?>
                                                            <button type="submit" name="row_action" value="deactivate" class="btn btn-sm btn-outline-secondary">Deactivate</button>
                                                        <?php else: ?>
                                                            <button type="submit" name="row_action" value="activate" class="btn btn-sm btn-outline-success">Reactivate</button>
                                                        <?php endif; ?>
                                                        <button type="submit" name="row_action" value="remove" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this assignment?');">Remove</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div></div></div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php include "footer.php"; ?>
</section>
</body>
</html>
