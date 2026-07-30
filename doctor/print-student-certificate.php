<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$stmt = $conn->prepare("SELECT c.*, m.name as student_name, m.member_uid, m.dob, m.gender, m.class, m.section,
        s.school_name, d.name as doctor_name, d.degrees, d.specialization, d.hpr_id, d.council_name, d.nmc_reg_number
    FROM school_member_certificates c
    JOIN school_members m ON m.id = c.member_id
    JOIN schools s ON s.id = c.school_id
    JOIN doctors d ON d.id = c.doctor_id
    WHERE c.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
if (!$cert) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$age = '';
if (!empty($cert['dob'])) {
    try {
        $age = (new DateTime($cert['dob']))->diff(new DateTime())->y;
    } catch (Exception $e) {
    }
}

$days = null;
if ($cert['leave_from'] && $cert['leave_to']) {
    $days = (new DateTime($cert['leave_from']))->diff(new DateTime($cert['leave_to']))->days + 1;
}

$cert_no = 'MC-' . str_pad($cert['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($cert['certificate_type']) ?> — <?= htmlspecialchars($cert['student_name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eee;
            margin: 0;
            padding: 20px;
            color: #1f2937;
        }
        .sheet {
            background: #fff;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 46px;
            border-radius: 6px;
            box-shadow: 0 2px 12px rgba(0,0,0,.1);
            border: 1px solid #e5e7eb;
        }
        .cert-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px double #0C74C5;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .cert-header h2 { margin: 0 0 2px; color: #0C74C5; font-size: 1.25rem; }
        .cert-header .sub { font-size: .82rem; color: #6b7280; }
        .cert-header .hpr { font-size: .72rem; color: #16a34a; margin-top: 3px; }
        .cert-no { font-size: .74rem; color: #9ca3af; font-family: monospace; }
        .cert-title {
            text-align: center;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #1f2937;
            margin: 0 0 26px;
            text-decoration: underline;
            text-underline-offset: 6px;
        }
        .cert-body { font-size: .96rem; line-height: 1.9; text-align: justify; }
        .cert-body strong { color: #0C74C5; }
        .cert-meta {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 22px 0;
            font-size: .84rem;
        }
        .cert-meta .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; display: block; }
        .remarks-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: .88rem;
            white-space: pre-wrap;
            margin-top: 18px;
        }
        .sign-row {
            margin-top: 60px;
            display: flex;
            justify-content: flex-end;
        }
        .sign-box { text-align: center; }
        .sign-line { border-top: 1px solid #333; width: 220px; margin-bottom: 4px; }
        .print-bar { max-width: 800px; margin: 0 auto 14px; text-align: right; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border-radius: 0; max-width: 100%; }
            .print-bar { display: none; }
        }
    </style>
</head>

<body>
    <div class="print-bar">
        <button onclick="window.print()" style="padding:8px 18px;border-radius:6px;border:none;background:#0C74C5;color:#fff;cursor:pointer;">
            <i class="fa fa-print"></i> Print
        </button>
    </div>

    <div class="sheet">
        <div class="cert-header">
            <div>
                <h2>Dr. <?= htmlspecialchars($cert['doctor_name']) ?></h2>
                <div class="sub"><?= htmlspecialchars($cert['degrees'] ?: '') ?><?= $cert['degrees'] && $cert['specialization'] ? ' — ' : '' ?><?= htmlspecialchars($cert['specialization'] ?: '') ?></div>
                <?php if ($cert['nmc_reg_number']): ?><div class="sub">NMC Reg. No: <?= htmlspecialchars($cert['nmc_reg_number']) ?><?= $cert['council_name'] ? ' (' . htmlspecialchars($cert['council_name']) . ')' : '' ?></div><?php endif; ?>
                <?php if ($cert['hpr_id']): ?><div class="hpr"><i class="fa fa-check-circle"></i> HPR: <?= htmlspecialchars($cert['hpr_id']) ?></div><?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:700;color:#0C74C5;">Rejuvenate Digital Health</div>
                <div class="sub">School Health Program</div>
                <div class="cert-no" style="margin-top:6px;">Certificate No: <?= $cert_no ?></div>
            </div>
        </div>

        <div class="cert-title"><?= htmlspecialchars($cert['certificate_type']) ?></div>

        <div class="cert-body">
            <p>This is to certify that <strong><?= htmlspecialchars($cert['student_name']) ?></strong>
                (<?= htmlspecialchars($cert['member_uid']) ?>)<?= $age ? ', aged ' . $age . ' years' : '' ?><?= $cert['gender'] ? ', ' . htmlspecialchars($cert['gender']) : '' ?>,
                a <?= htmlspecialchars($cert['school_name']) ?> student<?= $cert['class'] ? ' of Class ' . htmlspecialchars($cert['class']) . htmlspecialchars($cert['section'] ? '-' . $cert['section'] : '') : '' ?>,
                was examined by me and found to be suffering from / requiring care for:
                <strong><?= nl2br(htmlspecialchars($cert['reason'])) ?></strong>.</p>

            <?php if ($cert['leave_from'] && $cert['leave_to']): ?>
            <p>Based on this examination, <?= htmlspecialchars($cert['student_name']) ?> is advised medical leave / rest
                from <strong><?= date('d M Y', strtotime($cert['leave_from'])) ?></strong>
                to <strong><?= date('d M Y', strtotime($cert['leave_to'])) ?></strong>
                <?= $days ? ' (' . $days . ' day' . ($days > 1 ? 's' : '') . ')' : '' ?>.</p>
            <?php endif; ?>

            <?php if ($cert['fit_to_join_date']): ?>
            <p>The student is expected to be fit to resume regular school activities from
                <strong><?= date('d M Y', strtotime($cert['fit_to_join_date'])) ?></strong> onwards.</p>
            <?php endif; ?>
        </div>

        <div class="cert-meta">
            <div>
                <span class="lbl">Student</span>
                <strong><?= htmlspecialchars($cert['student_name']) ?></strong> (<?= htmlspecialchars($cert['member_uid']) ?>)
            </div>
            <div style="text-align:right;">
                <span class="lbl">Date Issued</span>
                <strong><?= date('d M Y', strtotime($cert['created_at'])) ?></strong>
            </div>
        </div>

        <?php if ($cert['remarks']): ?>
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.6px;color:#0C74C5;font-weight:700;margin-bottom:6px;">Additional Remarks</div>
            <div class="remarks-box"><?= nl2br(htmlspecialchars($cert['remarks'])) ?></div>
        </div>
        <?php endif; ?>

        <div class="sign-row">
            <div class="sign-box">
                <div class="sign-line"></div>
                <div style="font-size:.82rem;">Dr. <?= htmlspecialchars($cert['doctor_name']) ?></div>
                <div style="font-size:.72rem;color:#9ca3af;">Signature &amp; Seal</div>
            </div>
        </div>
    </div>
</body>

</html>
