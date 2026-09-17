<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Filters
$search_query = trim($_GET['search'] ?? '');
$doctor_filter = (int) ($_GET['doctor_id'] ?? 0);

// Every finalised OPD prescription for this patient — the doctor panel's
// patient-form.php (OPD) is what writes these rows; opd-slip.php (also OPD)
// generates the PDF linked from each row below.
$where = ["p.patient_id = ?", "p.status = 'final'"];
$params = [$user_id];
$types = "i";

if ($doctor_filter > 0) {
    $where[] = "p.doctor_id = ?";
    $params[] = $doctor_filter;
    $types .= "i";
}

if ($search_query !== '') {
    $where[] = "(d.name LIKE ? OR p.diagnosis LIKE ? OR p.chief_complaints LIKE ?)";
    $term = "%$search_query%";
    $params = array_merge($params, [$term, $term, $term]);
    $types .= "sss";
}

$where_sql = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT
        p.*,
        a.appointment_time,
        d.name AS doctor_name,
        d.specialization,
        d.degrees,
        d.profile_image,
        DATE_FORMAT(p.visit_date, '%d %M %Y') AS fmt_visit_date
    FROM prescriptions p
    JOIN appointments a ON a.id = p.appointment_id
    JOIN doctors d ON d.id = p.doctor_id
    $where_sql
    ORDER BY p.visit_date DESC, a.appointment_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_filtered = count($history);

// Pagination
$per_page = 10;
$total_pages = max(1, (int) ceil($total_filtered / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$history_page = array_slice($history, ($page - 1) * $per_page, $per_page);

// Stat chips — computed from the patient's full (unfiltered) history
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_rx,
        COUNT(DISTINCT doctor_id) AS total_doctors,
        SUM(CASE WHEN YEAR(visit_date) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS this_year
    FROM prescriptions
    WHERE patient_id = ? AND status = 'final'
");
$stats_stmt->bind_param('i', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Doctor filter dropdown — only doctors this patient actually has history with
$doc_list_stmt = $conn->prepare("
    SELECT DISTINCT d.id, d.name
    FROM prescriptions p
    JOIN doctors d ON d.id = p.doctor_id
    WHERE p.patient_id = ? AND p.status = 'final'
    ORDER BY d.name
");
$doc_list_stmt->bind_param('i', $user_id);
$doc_list_stmt->execute();
$doctor_options = $doc_list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$doc_list_stmt->close();

// Preserves the other active filter when a link only changes one of them
function mh_url($overrides, $doctor_filter, $search_query)
{
    $params = array_filter([
        'doctor_id' => $overrides['doctor_id'] ?? ($doctor_filter ?: null),
        'search'    => $search_query,
        'page'      => $overrides['page'] ?? null,
    ], fn($v) => $v !== null && $v !== '' && $v !== 0);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Medical History | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>user/assets/style.css">
    <style>
        /* Page-specific only — .ap-*, stat-chip, pagination etc. all come
           from the shared user/assets/style.css. */
        .mh-dx { font-size: .84rem; color: #374151; }
        .mh-meds-badge { background: #eef5ff; color: var(--primary); font-size: .7rem; font-weight: 700; border-radius: 20px; padding: 2px 9px; display: inline-block; }
    </style>
</head>

<body>
    <?php $sidebar_active = 'medical-history'; include("sidebar.php"); ?>
    <main class="patient-content">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="ap-h">Medical History</h1>
                <div class="ap-sub">Every finalised prescription from your OPD consultations</div>
            </div>
        </div>

        <!-- Stat chips -->
        <div class="stat-row">
            <div class="stat-chip">
                <div class="sc-num" style="color:#1f2937;"><?= (int) ($stats['total_rx'] ?? 0) ?></div>
                <div class="sc-lbl">Total Prescriptions</div>
            </div>
            <div class="stat-chip">
                <div class="sc-num" style="color:var(--primary);"><?= (int) ($stats['total_doctors'] ?? 0) ?></div>
                <div class="sc-lbl">Doctors Consulted</div>
            </div>
            <div class="stat-chip">
                <div class="sc-num" style="color:var(--accent-dk);"><?= (int) ($stats['this_year'] ?? 0) ?></div>
                <div class="sc-lbl">This Year</div>
            </div>
        </div>

        <div class="ap-panel">
            <div class="ap-panel-head">
                <h5 class="mb-0" style="font-size:1rem;font-weight:700;color:#1f2937;">
                    <?= $total_filtered ?> record<?= $total_filtered === 1 ? '' : 's' ?>
                </h5>
                <form method="GET" action="" class="filter-bar">
                    <?php if (!empty($doctor_options)): ?>
                        <select name="doctor_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                            <option value="0">All doctors</option>
                            <?php foreach ($doctor_options as $doc): ?>
                                <option value="<?= (int) $doc['id'] ?>" <?= $doctor_filter === (int) $doc['id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control form-control-sm" style="width:200px;"
                        placeholder="Doctor, diagnosis, complaints…" value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary);border-color:var(--primary);">
                        <i class="fa fa-search"></i>
                    </button>
                    <?php if ($doctor_filter || $search_query !== ''): ?>
                        <a href="medical-history.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($history)): ?>
                <div class="no-appointments">
                    <i class="fa fa-file-medical"></i>
                    <h4>No Medical History Yet</h4>
                    <p>
                        <?php if ($search_query || $doctor_filter): ?>
                            No records match your search criteria. Try different filters.
                        <?php else: ?>
                            Once a doctor finalises a prescription after your consultation, it will appear here.
                        <?php endif; ?>
                    </p>
                    <a href="my-doctor-appointments.php" class="btn btn-primary mt-3" style="background:var(--primary);border-color:var(--primary);">
                        <i class="fa fa-calendar-check"></i> View My Appointments
                    </a>
                </div>
            <?php else: ?>
                <?php
                $render_history_actions = function ($rx) {
                    ob_start(); ?>
                    <div class="btn-group btn-group-sm ap-actions">
                        <a href="appointment-details.php?id=<?= (int) $rx['appointment_id'] ?>" class="btn btn-outline-secondary" title="View Details">
                            <i class="fa fa-eye"></i>
                        </a>
                        <a href="<?= BASE_URL ?>doctor/opd-slip.php?appointment_id=<?= (int) $rx['appointment_id'] ?>" class="btn btn-outline-primary" title="Download PDF" target="_blank">
                            <i class="fa fa-download"></i>
                        </a>
                    </div>
                    <?php return ob_get_clean();
                };
                ?>

                <!-- Desktop table -->
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Visit Date</th>
                                <th>Diagnosis</th>
                                <th>Medicines</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_page as $rx): ?>
                                <?php
                                $has_avatar = !empty($rx['profile_image']) && file_exists('../admin/' . $rx['profile_image']);
                                $meds = json_decode($rx['medications'] ?? '', true) ?: [];
                                $meds_count = count(array_filter($meds, fn($m) => trim($m['name'] ?? '') !== ''));
                                $dx = trim($rx['diagnosis'] ?: $rx['chief_complaints'] ?: '');
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($has_avatar): ?>
                                                <img src="<?= BASE_URL . "admin/" . htmlspecialchars($rx['profile_image']) ?>" class="doctor-avatar" alt="">
                                            <?php else: ?>
                                                <span class="doctor-avatar"><i class="fa fa-user-md"></i></span>
                                            <?php endif; ?>
                                            <div>
                                                <strong>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($rx['specialization']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($rx['fmt_visit_date']) ?></td>
                                    <td class="mh-dx"><?= htmlspecialchars(mb_strimwidth($dx, 0, 60, '…')) ?: '—' ?></td>
                                    <td><?php if ($meds_count > 0): ?><span class="mh-meds-badge"><?= $meds_count ?> medicine<?= $meds_count === 1 ? '' : 's' ?></span><?php else: ?>—<?php endif; ?></td>
                                    <td><?= $render_history_actions($rx) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="ap-cards-wrap">
                    <?php foreach ($history_page as $rx): ?>
                        <?php
                        $has_avatar = !empty($rx['profile_image']) && file_exists('../admin/' . $rx['profile_image']);
                        $meds = json_decode($rx['medications'] ?? '', true) ?: [];
                        $meds_count = count(array_filter($meds, fn($m) => trim($m['name'] ?? '') !== ''));
                        $dx = trim($rx['diagnosis'] ?: $rx['chief_complaints'] ?: '');
                        ?>
                        <div class="ap-card">
                            <div class="ac-top">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($has_avatar): ?>
                                        <img src="<?= BASE_URL . 'admin/' . htmlspecialchars($rx['profile_image']) ?>" class="doctor-avatar">
                                    <?php else: ?>
                                        <span class="doctor-avatar"><i class="fa fa-user-md"></i></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></strong>
                                        <div class="ac-meta"><?= htmlspecialchars($rx['specialization']) ?></div>
                                    </div>
                                </div>
                                <?php if ($meds_count > 0): ?><span class="mh-meds-badge"><?= $meds_count ?> med<?= $meds_count === 1 ? '' : 's' ?></span><?php endif; ?>
                            </div>
                            <div class="ac-meta mt-2"><i class="fa fa-calendar me-1"></i><?= htmlspecialchars($rx['fmt_visit_date']) ?></div>
                            <?php if ($dx !== ''): ?>
                                <div class="mh-dx mt-1"><i class="fa fa-stethoscope me-1"></i><?= htmlspecialchars(mb_strimwidth($dx, 0, 100, '…')) ?></div>
                            <?php endif; ?>
                            <div class="ac-row"><?= $render_history_actions($rx) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Results Count + Pagination -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div class="text-muted small">
                        Showing <?= count($history_page) ?> of <?= $total_filtered ?> record<?= $total_filtered !== 1 ? 's' : '' ?>
                        <?php if ($search_query): ?>matching "<?= htmlspecialchars($search_query) ?>"<?php endif; ?>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Medical history pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(mh_url(['page' => $page - 1], $doctor_filter, $search_query)) ?>">Prev</a>
                                </li>
                                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= htmlspecialchars(mh_url(['page' => $p], $doctor_filter, $search_query)) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(mh_url(['page' => $page + 1], $doctor_filter, $search_query)) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include("inc/scripts.php") ?>
</body>
</html>
