<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$search = trim($_GET['search'] ?? '');
$school_filter = intval($_GET['school_id'] ?? 0);

$where = "WHERE m.type = 'Student'";
$params = [];
$types = '';
if ($search !== '') {
    $where .= " AND (m.name LIKE ? OR m.member_uid LIKE ? OR s.school_name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($school_filter) {
    $where .= " AND m.school_id = ?";
    $params[] = $school_filter;
    $types .= 'i';
}

$sql = "SELECT m.*, s.school_name
    FROM school_members m JOIN schools s ON s.id = m.school_id
    $where ORDER BY m.name ASC LIMIT 300";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = count($students);

$schools_dd = $conn->query("SELECT id, school_name FROM schools ORDER BY school_name ASC");

$sidebar_active = 'school-students';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Students — REJUVENATE Doctor Portal</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <style>
        .pat-avatar-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #0277bd;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .type-pill {
            display: inline-flex;
            align-items: center;
            font-size: .7rem;
            border-radius: 20px;
            padding: 2px 10px;
            font-weight: 700;
        }

        .abha-pill {
            display: inline-flex;
            align-items: center;
            background: #e0f2fe;
            color: #0277bd;
            font-size: .7rem;
            border-radius: 20px;
            padding: 2px 8px;
            font-weight: 600;
        }

        .abha-pill.verified {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .abha-pill.none {
            background: #f3f4f6;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <?php $sidebar_active = 'school-students';
    include(__DIR__ . "/inc/sidebar.php"); ?>

    <main class="doctor-content">

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div>
                <p class="section-title" style="margin:0;">Students</p>
                <span style="font-size:.78rem; color:#6b7280;"><?= $total ?> student<?= $total !== 1 ? 's' : '' ?> found</span>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-body" style="padding:14px 16px;">
                <form method="GET" action="" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <div style="position:relative;flex:1;min-width:220px;">
                        <input type="text" name="search" class="form-control" style="padding-left:36px;"
                            value="<?= htmlspecialchars($search) ?>" placeholder="Search name, ID or school…">
                        <i class="fa fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;"></i>
                    </div>
                    <select name="school_id" class="form-control" style="max-width:220px;">
                        <option value="0">All Schools</option>
                        <?php while ($sc = $schools_dd->fetch_assoc()): ?>
                            <option value="<?= $sc['id'] ?>" <?= $school_filter === (int)$sc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sc['school_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button class="btn btn-primary btn-sm"><i class="fa fa-filter me-1"></i> Filter</button>
                    <?php if ($search || $school_filter): ?>
                        <a href="school-students.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">
                    <i class="fa fa-user-graduate me-2" style="color:var(--primary,#0c74c5);"></i>
                    Students
                    <span style="background:#e0f2fe;color:#0277bd;border-radius:20px;padding:1px 10px;font-size:.72rem;font-weight:700;margin-left:6px;"><?= $total ?></span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($total === 0): ?>
                    <div style="text-align:center;padding:60px 20px;color:#6b7280;">
                        <i class="fa fa-user-graduate" style="font-size:3rem;color:#d1d5db;display:block;margin-bottom:12px;"></i>
                        <h6 style="font-weight:600;">No students found</h6>
                        <p style="font-size:.84rem;">Try a different search or clear the filter.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="font-size:.73rem;width:38px;">#</th>
                                    <th style="font-size:.73rem;">Student</th>
                                    <th style="font-size:.73rem;" class="d-none d-md-table-cell">School</th>
                                    <th style="font-size:.73rem;" class="d-none d-sm-table-cell">Class</th>
                                    <th style="font-size:.73rem;" class="d-none d-sm-table-cell">ABHA</th>
                                    <th style="font-size:.73rem;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $i => $m):
                                    $age = !empty($m['dob']) ? date_diff(date_create($m['dob']), date_create('today'))->y : null;
                                ?>
                                    <tr>
                                        <td style="font-size:.78rem;color:#9ca3af;"><?= $i + 1 ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div class="pat-avatar-sm"><?= strtoupper(substr($m['name'], 0, 1)) ?></div>
                                                <div>
                                                    <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($m['name']) ?></div>
                                                    <div style="font-size:.72rem;color:#6b7280;">
                                                        <?= htmlspecialchars($m['member_uid']) ?>
                                                        <?= $m['gender'] ? ' · ' . htmlspecialchars($m['gender']) : '' ?>
                                                        <?= $age ? ' · ' . $age . ' yrs' : '' ?>
                                                    </div>
                                                    <div class="d-md-none" style="font-size:.72rem;color:#6b7280;margin-top:2px;">
                                                        <?= htmlspecialchars($m['school_name']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell" style="font-size:.82rem;"><?= htmlspecialchars($m['school_name']) ?></td>
                                        <td class="d-none d-sm-table-cell" style="font-size:.82rem;">
                                            <?= htmlspecialchars(trim(($m['class'] ?? '') . ' ' . ($m['section'] ?? ''))) ?: '—' ?>
                                        </td>
                                        <td class="d-none d-sm-table-cell">
                                            <?php if ($m['abha_address']): ?>
                                                <span class="abha-pill <?= $m['abha_verified'] ? 'verified' : '' ?>">
                                                    <?php if ($m['abha_verified']): ?><i class="fa fa-check-circle" style="margin-right:3px;"></i><?php endif; ?>
                                                    ABHA
                                                </span>
                                            <?php else: ?>
                                                <span class="abha-pill none">Not linked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="student-profile.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Profile"><i class="fa fa-eye"></i></a>
                                            <a href="student-profile.php?id=<?= $m['id'] ?>&tab=rx" class="btn btn-sm btn-primary" title="Prescribe"><i class="fa fa-file-medical"></i></a>
                                            <a href="student-profile.php?id=<?= $m['id'] ?>&tab=docs" class="btn btn-sm btn-outline-secondary" title="Upload Report"><i class="fa fa-upload"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

</body>

</html>
