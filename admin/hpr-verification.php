<?php
include "functions.php"; // db-conn + admin_jwt_guard()

$rows = [];
$res = $conn->query("
    SELECT r.*, d.name AS doctor_name, d.email AS doctor_email, d.specialization, d.hpr_verified
    FROM hpr_verification_requests r
    JOIN doctors d ON d.id = r.doctor_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at ASC
");
if ($res) { while ($x = $res->fetch_assoc()) $rows[] = $x; }
?>
<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HPR Verification Requests | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
</head>
<body class="crm_body_bg">
<?php include "header.php"; ?>
<section class="main_content dashboard_part large_header_bg">
    <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

    <div class="main_content_iner">
        <div class="container-fluid p-0 sm_padding_15px">
            <div class="row justify-content-center"><div class="col-lg-12">
                <div class="white_card card_height_100 mb_30">
                    <div class="card-header bg-white border-0 py-3">
                        <h3 class="mb-0 fw-bold">HPR Verification Requests</h3>
                        <p class="text-muted mb-0 small">Doctors who submitted their HPR / NMC details for verification against the Health Professional Registry</p>
                    </div>
                    <div class="white_card_body">
                        <?php if (empty($rows)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-shield-alt fs-1 mb-2 d-block"></i>
                                No pending HPR verification requests.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Requested</th>
                                            <th>Doctor</th>
                                            <th>HPR ID</th>
                                            <th>HFR ID</th>
                                            <th>NMC Reg.</th>
                                            <th>State Council</th>
                                            <th>Year</th>
                                            <th>Note</th>
                                            <th style="width:150px;">Review</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><?= date('d M Y', strtotime($r['requested_at'])) ?></td>
                                                <td>
                                                    <a href="doctor-edit.php?id=<?= (int)$r['doctor_id'] ?>#verification" class="fw-semibold text-decoration-none">
                                                        Dr. <?= htmlspecialchars($r['doctor_name']) ?>
                                                    </a><br>
                                                    <span class="text-muted small"><?= htmlspecialchars($r['specialization'] ?: '') ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($r['hpr_id'] ?: '—') ?></td>
                                                <td><?= htmlspecialchars($r['hfr_id'] ?: '—') ?></td>
                                                <td><?= htmlspecialchars($r['nmc_reg_number'] ?: '—') ?></td>
                                                <td><?= htmlspecialchars($r['council_name'] ?: '—') ?></td>
                                                <td><?= htmlspecialchars($r['year_of_registration'] ?: '—') ?></td>
                                                <td class="small"><?= htmlspecialchars($r['doctor_note'] ?: '—') ?></td>
                                                <td>
                                                    <a href="doctor-edit.php?id=<?= (int)$r['doctor_id'] ?>&hpr_approve=1" class="btn btn-sm btn-success mb-1"
                                                       onclick="return confirm('Approve HPR verification for this doctor?')">Approve</a>
                                                    <a href="doctor-edit.php?id=<?= (int)$r['doctor_id'] ?>" class="btn btn-sm btn-outline-secondary">Open &amp; review</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div></div>
        </div>
    </div>
    <?php include "footer.php"; ?>
</section>
</body>
</html>
