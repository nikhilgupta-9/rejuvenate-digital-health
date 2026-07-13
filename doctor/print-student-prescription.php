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

$stmt = $conn->prepare("SELECT p.*, m.name as student_name, m.member_uid, m.dob, m.gender, m.class, m.section,
        s.school_name, d.name as doctor_name, d.degrees, d.specialization, d.hpr_id
    FROM school_member_prescriptions p
    JOIN school_members m ON m.id = p.member_id
    JOIN schools s ON s.id = p.school_id
    JOIN doctors d ON d.id = p.doctor_id
    WHERE p.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
if (!$rx) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$age = '';
if (!empty($rx['dob'])) {
    try {
        $age = (new DateTime($rx['dob']))->diff(new DateTime())->y;
    } catch (Exception $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Prescription — <?= htmlspecialchars($rx['student_name']) ?></title>
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
            padding: 36px 40px;
            border-radius: 6px;
            box-shadow: 0 2px 12px rgba(0,0,0,.1);
        }
        .rx-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0C74C5;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .rx-header h2 { margin: 0 0 2px; color: #0C74C5; font-size: 1.25rem; }
        .rx-header .sub { font-size: .82rem; color: #6b7280; }
        .rx-header .hpr { font-size: .72rem; color: #16a34a; margin-top: 3px; }
        .rx-meta {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: .84rem;
        }
        .rx-meta .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; display: block; }
        .rx-section { margin-bottom: 18px; }
        .rx-section .stitle {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #0C74C5;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .rx-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: .9rem;
            white-space: pre-wrap;
            min-height: 60px;
        }
        .rx-symbol { font-size: 1.6rem; font-weight: 700; color: #0C74C5; margin-right: 6px; }
        .sign-row {
            margin-top: 50px;
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
        <div class="rx-header">
            <div>
                <h2>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></h2>
                <div class="sub"><?= htmlspecialchars($rx['degrees'] ?: '') ?><?= $rx['degrees'] && $rx['specialization'] ? ' — ' : '' ?><?= htmlspecialchars($rx['specialization'] ?: '') ?></div>
                <?php if ($rx['hpr_id']): ?><div class="hpr"><i class="fa fa-check-circle"></i> HPR: <?= htmlspecialchars($rx['hpr_id']) ?></div><?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:700;color:#0C74C5;">Rejuvenate Digital Health</div>
                <div class="sub">School Health Program</div>
            </div>
        </div>

        <div class="rx-meta">
            <div>
                <span class="lbl">Student</span>
                <strong><?= htmlspecialchars($rx['student_name']) ?></strong> (<?= htmlspecialchars($rx['member_uid']) ?>)
                <?= $age ? ' · ' . $age . ' yrs' : '' ?><?= $rx['gender'] ? ' · ' . htmlspecialchars($rx['gender']) : '' ?>
                <br><span style="font-size:.8rem;color:#6b7280;"><?= htmlspecialchars($rx['school_name']) ?><?= $rx['class'] ? ' — Class ' . htmlspecialchars($rx['class']) . htmlspecialchars($rx['section'] ? '-' . $rx['section'] : '') : '' ?></span>
            </div>
            <div style="text-align:right;">
                <span class="lbl">Date</span>
                <strong><?= date('d M Y', strtotime($rx['created_at'])) ?></strong>
                <?php if ($rx['follow_up_date']): ?><br><span style="font-size:.78rem;color:#6b7280;">Follow-up: <?= date('d M Y', strtotime($rx['follow_up_date'])) ?></span><?php endif; ?>
            </div>
        </div>

        <?php if ($rx['diagnosis']): ?>
        <div class="rx-section">
            <div class="stitle">Diagnosis</div>
            <div class="rx-box" style="min-height:auto;"><?= htmlspecialchars($rx['diagnosis']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($rx['symptoms']): ?>
        <div class="rx-section">
            <div class="stitle">Symptoms</div>
            <div class="rx-box" style="min-height:auto;"><?= htmlspecialchars($rx['symptoms']) ?></div>
        </div>
        <?php endif; ?>

        <div class="rx-section">
            <div class="stitle"><span class="rx-symbol">℞</span>Prescription</div>
            <div class="rx-box"><?= htmlspecialchars($rx['prescription_text']) ?></div>
        </div>

        <?php if ($rx['advice']): ?>
        <div class="rx-section">
            <div class="stitle">Advice</div>
            <div class="rx-box" style="min-height:auto;"><?= htmlspecialchars($rx['advice']) ?></div>
        </div>
        <?php endif; ?>

        <div class="sign-row">
            <div class="sign-box">
                <div class="sign-line"></div>
                <div style="font-size:.82rem;">Dr. <?= htmlspecialchars($rx['doctor_name']) ?></div>
            </div>
        </div>
    </div>
</body>

</html>
