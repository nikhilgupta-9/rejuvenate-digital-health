<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

/* ── Ensure linkage/provenance columns exist (table itself is created by school/parent-consent.php) ── */
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS member_id INT DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS source ENUM('parent','doctor') NOT NULL DEFAULT 'parent'");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS recorded_by_doctor_id INT DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS linked_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS plan_id INT UNSIGNED DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS plan_name VARCHAR(120) DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS plan_price DECIMAL(10,2) DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending'");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS razorpay_order_id VARCHAR(64) DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS razorpay_payment_id VARCHAR(64) DEFAULT NULL");
$conn->query("ALTER TABLE parent_consent_forms ADD COLUMN IF NOT EXISTS paid_at DATETIME DEFAULT NULL");

/* ── Status update ── */
if (isset($_GET['set_status'], $_GET['id'])) {
    $new = in_array($_GET['set_status'], ['pending', 'reviewed', 'archived']) ? $_GET['set_status'] : 'pending';
    $id  = (int) $_GET['id'];
    $rev = $new === 'reviewed' ? ', reviewed_at = NOW()' : '';
    $u = $conn->prepare("UPDATE parent_consent_forms SET status = ?$rev WHERE id = ?");
    $u->bind_param('si', $new, $id);
    $u->execute();
    $_SESSION['success_message'] = 'Consent marked as ' . ucfirst($new) . '.';
    header('Location: parent-consents.php' . (isset($_GET['ret']) ? '?' . $_GET['ret'] : ''));
    exit;
}

$total    = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM parent_consent_forms"))['c'];
$pending  = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM parent_consent_forms WHERE status='pending'"))['c'];
$reviewed = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM parent_consent_forms WHERE status='reviewed'"))['c'];
$month    = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM parent_consent_forms WHERE YEAR(submitted_at)=YEAR(CURDATE()) AND MONTH(submitted_at)=MONTH(CURDATE())"))['c'];

$school_filter = (int) ($_GET['school_id'] ?? 0);
$status_filter = $_GET['status'] ?? 'all';
$source_filter = $_GET['source'] ?? 'all';
$search        = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($school_filter) $where .= " AND c.school_id = " . $school_filter;
if (in_array($status_filter, ['pending', 'reviewed', 'archived'])) $where .= " AND c.status = '" . $status_filter . "'";
if (in_array($source_filter, ['parent', 'doctor'])) $where .= " AND c.source = '" . $source_filter . "'";
if ($search !== '') {
    $q = mysqli_real_escape_string($conn, $search);
    $where .= " AND (c.student_name LIKE '%$q%' OR c.parent_name LIKE '%$q%' OR c.parent_mobile LIKE '%$q%' OR c.token LIKE '%$q%')";
}

$rows = mysqli_query($conn, "SELECT c.*, s.school_name, s.school_uid, m.member_uid, d.name AS doctor_name
    FROM parent_consent_forms c
    LEFT JOIN schools s ON s.id = c.school_id
    LEFT JOIN school_members m ON m.id = c.member_id
    LEFT JOIN doctors d ON d.id = c.recorded_by_doctor_id
    $where ORDER BY c.submitted_at DESC LIMIT 400");

$schools_all = [];
$sa = mysqli_query($conn, "SELECT id, school_name FROM schools WHERE status = 'Active' ORDER BY school_name ASC");
if ($sa) $schools_all = mysqli_fetch_all($sa, MYSQLI_ASSOC);
$schools_dd = $schools_all; // filter dropdown reuses the same list

$consent_form_url = rtrim(BASE_URL, '/') . '/school/parent-consent.php';
$qs = http_build_query(array_filter(['school_id' => $school_filter ?: null, 'status' => $status_filter !== 'all' ? $status_filter : null, 'source' => $source_filter !== 'all' ? $source_filter : null, 'q' => $search ?: null]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Parent Consents</title>
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

                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">Parent Consent Forms</h4>
                        <small class="text-muted">Health-checkup consents submitted by parents and recorded by doctors</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#shareFormModal">
                            <i class="fas fa-share-nodes me-1"></i> Share Consent Form
                        </button>
                        <a href="<?= htmlspecialchars($consent_form_url) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-external-link-alt me-1"></i> Open Form
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-file-signature big-icon"></i><div class="num"><?= $total ?></div><div class="lbl">Total Consents</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-clock big-icon"></i><div class="num"><?= $pending ?></div><div class="lbl">Pending Review</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-double big-icon"></i><div class="num"><?= $reviewed ?></div><div class="lbl">Reviewed</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-calendar big-icon"></i><div class="num"><?= $month ?></div><div class="lbl">This Month</div></div></div>
                </div>

                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-6 col-lg-3">
                            <label class="form-label mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Student, parent, mobile, token...">
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label mb-1">School</label>
                            <select class="form-select form-select-sm" name="school_id">
                                <option value="0">All Schools</option>
                                <?php foreach ($schools_all as $sc): ?>
                                    <option value="<?= $sc['id'] ?>" <?= $school_filter === (int) $sc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sc['school_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (['all' => 'All', 'pending' => 'Pending', 'reviewed' => 'Reviewed', 'archived' => 'Archived'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $status_filter === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label mb-1">Source</label>
                            <select class="form-select form-select-sm" name="source">
                                <?php foreach (['all' => 'All', 'parent' => 'Parent (online)', 'doctor' => 'Doctor (in person)'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $source_filter === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title"><h3 class="m-0">Consents <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($rows) ?></span></h3></div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Parent / Guardian</th>
                                        <th>School</th>
                                        <th>Plan / Payment</th>
                                        <th>Source</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (mysqli_num_rows($rows) === 0): ?>
                                    <tr class="empty-row"><td colspan="9">
                                        <i class="fas fa-file-signature fa-3x mb-3 d-block opacity-25"></i>No consent forms found.
                                    </td></tr>
                                <?php endif; ?>
                                <?php
                                $status_pill = ['pending' => 'pill-warn', 'reviewed' => 'pill-success', 'archived' => 'pill-muted'];
                                $pay_pill    = ['paid' => 'pill-success', 'pending' => 'pill-warn', 'failed' => 'pill-danger'];
                                $i = 1;
                                while ($c = mysqli_fetch_assoc($rows)):
                                ?>
                                <tr>
                                    <td><span class="cell-sub"><?= $i++ ?></span></td>
                                    <td data-label="Student">
                                        <div class="cell-title"><?= htmlspecialchars($c['student_name']) ?></div>
                                        <div class="cell-sub"><?= $c['member_uid'] ? htmlspecialchars($c['member_uid']) : 'Not linked to a member' ?></div>
                                    </td>
                                    <td data-label="Parent / Guardian">
                                        <div class="cell-title" style="font-weight:500;"><?= htmlspecialchars($c['parent_name']) ?> <span class="cell-sub">(<?= htmlspecialchars($c['relation'] ?? '') ?>)</span></div>
                                        <div class="cell-sub"><?= htmlspecialchars($c['parent_mobile']) ?></div>
                                    </td>
                                    <td data-label="School"><span class="cell-title" style="font-weight:500;"><?= $c['school_name'] ? htmlspecialchars($c['school_name']) : htmlspecialchars($c['school_name_manual'] ?: '—') ?></span></td>
                                    <td data-label="Plan / Payment">
                                        <?php if (!empty($c['plan_id']) || $c['plan_name']): ?>
                                            <div class="cell-title" style="font-weight:500;"><?= htmlspecialchars($c['plan_name'] ?: 'Plan') ?></div>
                                            <div class="cell-sub">
                                                <span class="pill pill-sq <?= $pay_pill[$c['payment_status']] ?? 'pill-muted' ?>"><?= ucfirst($c['payment_status'] ?: 'pending') ?></span>
                                                <?php if ($c['plan_price'] !== null): ?> &#8377;<?= number_format((float) $c['plan_price']) ?><?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="cell-sub">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Source">
                                        <?php if ($c['source'] === 'doctor'): ?>
                                            <span class="pill pill-sq pill-purple"><i class="fas fa-user-md"></i>Doctor</span>
                                            <?php if ($c['doctor_name']): ?><div class="cell-sub mt-1">Dr. <?= htmlspecialchars($c['doctor_name']) ?></div><?php endif; ?>
                                        <?php else: ?>
                                            <span class="pill pill-sq pill-info"><i class="fas fa-globe"></i>Parent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Submitted"><span class="cell-sub"><?= date('d M Y', strtotime($c['submitted_at'])) ?> &middot; <?= date('h:i A', strtotime($c['submitted_at'])) ?></span></td>
                                    <td data-label="Status">
                                        <span class="pill <?= $status_pill[$c['status']] ?? 'pill-muted' ?>"><?= ucfirst($c['status']) ?></span>
                                        <?php if (!$c['consent_given']): ?><div class="cell-sub mt-1" style="color:#b91c1c;">Not agreed</div><?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        <a href="parent-consent-view.php?id=<?= $c['id'] ?>" class="tbl-action-btn bg-primary text-white" title="View"><i class="fas fa-eye"></i></a>
                                        <?php if ($c['status'] !== 'reviewed'): ?>
                                            <a href="?set_status=reviewed&id=<?= $c['id'] ?>&ret=<?= urlencode($qs) ?>" class="tbl-action-btn bg-success text-white" title="Mark reviewed"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        <?php if ($c['status'] !== 'archived'): ?>
                                            <a href="?set_status=archived&id=<?= $c['id'] ?>&ret=<?= urlencode($qs) ?>" class="tbl-action-btn bg-secondary text-white" title="Archive" onclick="return confirm('Archive this consent?')"><i class="fas fa-box-archive"></i></a>
                                        <?php endif; ?>
                                        </div>
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

<!-- ── Share Consent Form ── -->
<div class="modal fade" id="shareFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-share-nodes me-2"></i>Share the parent consent form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label mb-1">Pre-fill a school <span class="text-muted">(optional)</span></label>
                <select class="form-select form-select-sm mb-3" id="shareSchool">
                    <option value="">— parents pick the school themselves —</option>
                    <?php foreach ($schools_all as $sc): ?>
                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['school_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label mb-1">Shareable link</label>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" class="form-control" id="shareLink" readonly value="<?= htmlspecialchars($consent_form_url) ?>">
                    <button class="btn btn-outline-secondary" type="button" id="shareCopyBtn"><i class="fas fa-copy"></i> Copy</button>
                </div>

                <div class="text-center">
                    <div id="shareQr" class="d-inline-block p-2 bg-white border rounded"></div>
                    <div class="small text-muted mt-1">Scan to open the form on a phone</div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                    <a href="#" id="shareWhatsApp" target="_blank" class="btn btn-sm btn-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
                    <a href="#" id="shareEmail" class="btn btn-sm btn-outline-primary"><i class="fas fa-envelope me-1"></i> Email</a>
                    <button type="button" id="shareQrDownload" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i> Download QR</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const baseUrl = <?= json_encode($consent_form_url) ?>;
    const modal   = document.getElementById('shareFormModal');
    if (!modal) return;

    const schoolSel = document.getElementById('shareSchool');
    const linkInput = document.getElementById('shareLink');
    const qrBox     = document.getElementById('shareQr');
    const waLink    = document.getElementById('shareWhatsApp');
    const mailLink  = document.getElementById('shareEmail');
    let qr = null;

    function currentUrl() {
        const sid = schoolSel.value;
        return sid ? baseUrl + '?school_id=' + encodeURIComponent(sid) : baseUrl;
    }

    function refresh() {
        const url = currentUrl();
        linkInput.value = url;

        qrBox.innerHTML = '';
        qr = new QRCode(qrBox, { text: url, width: 190, height: 190, correctLevel: QRCode.CorrectLevel.M });

        const msg = 'Please fill the student health checkup consent form: ' + url;
        waLink.href = 'https://wa.me/?text=' + encodeURIComponent(msg);
        mailLink.href = 'mailto:?subject=' + encodeURIComponent('Student Health Checkup — Consent Form')
            + '&body=' + encodeURIComponent(msg);
    }

    modal.addEventListener('shown.bs.modal', refresh);
    schoolSel.addEventListener('change', refresh);

    document.getElementById('shareCopyBtn').addEventListener('click', function () {
        navigator.clipboard.writeText(linkInput.value).then(() => {
            this.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(() => { this.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1800);
        }).catch(() => { linkInput.select(); document.execCommand('copy'); });
    });

    document.getElementById('shareQrDownload').addEventListener('click', function () {
        const img = qrBox.querySelector('img') || qrBox.querySelector('canvas');
        if (!img) return;
        const src = img.tagName === 'IMG' ? img.src : img.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = src;
        a.download = 'consent-form-qr.png';
        document.body.appendChild(a); a.click(); a.remove();
    });
})();
</script>
</body>
</html>
