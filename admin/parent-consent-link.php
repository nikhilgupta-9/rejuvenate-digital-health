<?php
/**
 * One-time cleanup tool (Phase 4): link a consent submitted through the old
 * generic school-wide link (school/parent-consent.php with no ?ctoken=) to
 * the real school_members row it belongs to.
 *
 * The generic link never knows which student it's for, so
 * parent_consent_forms.member_id stays NULL until a human matches the
 * parent's free-typed name/DOB/class against the school's member list.
 * Same UPDATE shape as doctor/save-student-consent.php's "link" action.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$id  = (int) ($_GET['id'] ?? 0);
$ret = $_GET['ret'] ?? '';
if (!$id) {
    header('Location: parent-consents.php');
    exit;
}

/* ── Link action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = (int) $_POST['member_id'];
    $school_id = (int) ($_POST['school_id'] ?? 0);

    $chk = $conn->prepare("SELECT id, school_id FROM school_members WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $member_id);
    $chk->execute();
    $target = $chk->get_result()->fetch_assoc();

    if (!$target || (int) $target['school_id'] !== $school_id) {
        $_SESSION['error_message'] = 'That student does not belong to the selected school.';
    } else {
        $upd = $conn->prepare("UPDATE parent_consent_forms
            SET member_id = ?, school_id = ?, linked_at = NOW()
            WHERE id = ? AND member_id IS NULL");
        $upd->bind_param('iii', $member_id, $school_id, $id);
        $upd->execute();
        $_SESSION['success_message'] = $upd->affected_rows
            ? 'Consent linked to the school member.'
            : 'Could not link — this consent may already be linked.';
    }
    header('Location: parent-consents.php' . ($ret ? '?' . $ret : ''));
    exit;
}

$stmt = $conn->prepare("SELECT c.*, s.school_name
    FROM parent_consent_forms c
    LEFT JOIN schools s ON s.id = c.school_id
    WHERE c.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) {
    header('Location: parent-consents.php');
    exit;
}
if ($c['member_id']) {
    $_SESSION['success_message'] = 'This consent is already linked to a school member.';
    header('Location: parent-consent-view.php?id=' . $id);
    exit;
}

$schools_all = [];
$sa = mysqli_query($conn, "SELECT id, school_name FROM schools WHERE status = 'Active' ORDER BY school_name ASC");
if ($sa) $schools_all = mysqli_fetch_all($sa, MYSQLI_ASSOC);

$search_school = (int) ($_GET['sschool'] ?? $c['school_id'] ?? 0);
$search_q      = trim($_GET['sq'] ?? $c['student_name'] ?? '');

$candidates = [];
if ($search_school && $search_q !== '') {
    $q = mysqli_real_escape_string($conn, $search_q);
    $res = mysqli_query($conn, "SELECT id, member_uid, name, type, class, section, roll_number, dob, parent_mobile
        FROM school_members
        WHERE school_id = $search_school AND type = 'Student'
          AND (name LIKE '%$q%' OR roll_number LIKE '%$q%')
        ORDER BY name ASC LIMIT 30");
    if ($res) $candidates = mysqli_fetch_all($res, MYSQLI_ASSOC);
} elseif ($search_school) {
    $res = mysqli_query($conn, "SELECT id, member_uid, name, type, class, section, roll_number, dob, parent_mobile
        FROM school_members WHERE school_id = $search_school AND type = 'Student' ORDER BY name ASC LIMIT 30");
    if ($res) $candidates = mysqli_fetch_all($res, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Link Consent to Student</title>
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
                        <h4 class="mb-0 fw-bold">Link Consent to a Student</h4>
                        <small class="text-muted">Submitted via the old generic link — pick the matching school member below</small>
                    </div>
                    <a href="parent-consents.php<?= $ret ? '?' . htmlspecialchars($ret) : '' ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>

                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php unset($_SESSION['error_message']); endif; ?>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header"><div class="box_header"><h3 class="m-0">As submitted by the parent</h3></div></div>
                    <div class="white_card_body">
                        <div class="row g-3">
                            <div class="col-md-3"><div class="detail-label">Student name</div><div class="detail-val"><?= htmlspecialchars($c['student_name']) ?></div></div>
                            <div class="col-md-3"><div class="detail-label">Date of birth</div><div class="detail-val"><?= $c['student_dob'] && $c['student_dob'] !== '0000-00-00' ? date('d M Y', strtotime($c['student_dob'])) : '—' ?></div></div>
                            <div class="col-md-2"><div class="detail-label">Class / Section</div><div class="detail-val"><?= htmlspecialchars(trim(($c['student_class'] ?? '') . ' ' . ($c['student_section'] ?? '')) ?: '—') ?></div></div>
                            <div class="col-md-2"><div class="detail-label">Roll No.</div><div class="detail-val"><?= htmlspecialchars($c['student_roll_no'] ?: '—') ?></div></div>
                            <div class="col-md-2"><div class="detail-label">School (declared)</div><div class="detail-val"><?= htmlspecialchars($c['school_name'] ?: $c['school_name_manual'] ?: '—') ?></div></div>
                            <div class="col-md-3"><div class="detail-label">Parent name</div><div class="detail-val"><?= htmlspecialchars($c['parent_name']) ?></div></div>
                            <div class="col-md-3"><div class="detail-label">Parent mobile</div><div class="detail-val"><?= htmlspecialchars($c['parent_mobile']) ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header"><div class="box_header"><h3 class="m-0">Find the matching student</h3></div></div>
                    <div class="white_card_body">
                        <form method="GET" class="row g-3 align-items-end mb-3">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="ret" value="<?= htmlspecialchars($ret) ?>">
                            <div class="col-6 col-lg-4">
                                <label class="form-label mb-1">School</label>
                                <select class="form-select form-select-sm" name="sschool" required>
                                    <option value="">— Select school —</option>
                                    <?php foreach ($schools_all as $sc): ?>
                                        <option value="<?= $sc['id'] ?>" <?= $search_school === (int) $sc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sc['school_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-lg-5">
                                <label class="form-label mb-1">Search by name or roll no.</label>
                                <input type="text" class="form-control form-control-sm" name="sq" value="<?= htmlspecialchars($search_q) ?>" placeholder="Student name or roll number">
                            </div>
                            <div class="col-12 col-lg-3">
                                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Search</button>
                            </div>
                        </form>

                        <?php if (!$search_school): ?>
                            <div class="text-muted text-center py-4">Select a school to search its students.</div>
                        <?php elseif (!$candidates): ?>
                            <div class="text-muted text-center py-4"><i class="fas fa-user-slash fa-2x mb-2 d-block opacity-25"></i>No matching student found. Try a different name/roll number, or check the student has been added to Members first.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover tbl-admin mb-0">
                                    <thead><tr>
                                        <th>Name</th><th>Class / Section</th><th>Roll No.</th><th>DOB</th><th>Parent Mobile</th><th></th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($candidates as $m): ?>
                                        <tr>
                                            <td><div class="cell-title"><?= htmlspecialchars($m['name']) ?></div><div class="cell-sub"><?= htmlspecialchars($m['member_uid']) ?></div></td>
                                            <td><?= htmlspecialchars(trim(($m['class'] ?? '') . ' ' . ($m['section'] ?? '')) ?: '—') ?></td>
                                            <td><?= htmlspecialchars($m['roll_number'] ?: '—') ?></td>
                                            <td><?= $m['dob'] ? date('d M Y', strtotime($m['dob'])) : '—' ?></td>
                                            <td><?= htmlspecialchars($m['parent_mobile'] ?: '—') ?></td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Link this consent to ' + <?= json_encode($m['name']) ?> + '?')">
                                                    <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                                    <input type="hidden" name="school_id" value="<?= $search_school ?>">
                                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-link me-1"></i>Link</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
</body>
</html>
