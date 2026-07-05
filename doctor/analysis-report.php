<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload   = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

// -- Patient Created: patients linked to this doctor --
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM doctor_patients WHERE doctor_id = ?");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$patients_total = (int) $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM doctor_patients WHERE doctor_id = ? AND DATE(added_at) = CURDATE() - INTERVAL 1 DAY");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$patients_yesterday = (int) $stmt->get_result()->fetch_assoc()['c'];

// -- ABHA Created: linked patients whose ABHA is linked --
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM doctor_patients dp
    JOIN users u ON u.id = dp.patient_id
    WHERE dp.doctor_id = ? AND u.abha_linked = 1
");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$abha_total = (int) $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM doctor_patients dp
    JOIN users u ON u.id = dp.patient_id
    WHERE dp.doctor_id = ? AND u.abha_linked = 1 AND DATE(u.abha_linked_at) = CURDATE() - INTERVAL 1 DAY
");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$abha_yesterday = (int) $stmt->get_result()->fetch_assoc()['c'];

// -- Document: files uploaded by this doctor for patients --
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM patient_documents WHERE doctor_id = ?");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$docs_total = (int) $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM patient_documents WHERE doctor_id = ? AND DATE(uploaded_at) = CURDATE() - INTERVAL 1 DAY");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$docs_yesterday = (int) $stmt->get_result()->fetch_assoc()['c'];

// -- Document Linked (proxy): documents belonging to a patient with at least one completed appointment with this doctor --
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM patient_documents pd
    WHERE pd.doctor_id = ? AND EXISTS (
        SELECT 1 FROM appointments a
        WHERE a.doctor_id = pd.doctor_id AND a.user_id = pd.patient_id AND a.status = 'completed'
    )
");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$docs_linked_total = (int) $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM patient_documents pd
    WHERE pd.doctor_id = ? AND DATE(pd.uploaded_at) = CURDATE() - INTERVAL 1 DAY AND EXISTS (
        SELECT 1 FROM appointments a
        WHERE a.doctor_id = pd.doctor_id AND a.user_id = pd.patient_id AND a.status = 'completed'
    )
");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$docs_linked_yesterday = (int) $stmt->get_result()->fetch_assoc()['c'];

// -- Token Details (proxy): appointments booked with this doctor --
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = ?");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$tokens_total = (int) $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = ? AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$tokens_yesterday = (int) $stmt->get_result()->fetch_assoc()['c'];

// -- Red Flag Document: not tracked yet --
$red_flag_total     = 0;
$red_flag_yesterday = 0;

$sidebar_active = 'analysis-report';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Analysis Report — Rejuvenate</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <style>
        .report-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
            padding: 24px;
            max-width: 700px;
            margin: 0 auto;
        }

        .report-date {
            text-align: center;
            font-style: italic;
            font-weight: 600;
            color: #92400e;
            margin: 16px 0;
        }

        .report-heading {
            font-size: 1.6rem;
            font-weight: 400;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .report-table th {
            background: #e5e7eb;
            font-weight: 700;
            text-align: center;
            padding: 12px 10px;
        }

        .report-table td {
            text-align: center;
            padding: 14px 10px;
            color: #4b5563;
        }

        .report-table td:first-child {
            text-align: left;
            color: #374151;
        }

        .report-table tbody tr:nth-child(odd) {
            background: #f9fafb;
        }

        .btn-red-flag {
            display: block;
            width: 100%;
            background: #28a745;
            color: #fff;
            font-weight: 700;
            letter-spacing: .5px;
            padding: 14px;
            border-radius: 6px;
            border: none;
            margin-top: 20px;
        }

        .btn-red-flag:hover {
            background: #218838;
            color: #fff;
        }
    </style>
</head>

<body>
    <main class="doctor-content">
        <div class="report-card">
            <div class="text-center">
                <button class="btn btn-outline-primary" onclick="location.reload();">Refresh</button>
            </div>
            <div class="report-date">Date : <?= date('n/j/Y g:i A') ?></div>

            <div class="report-heading">Overall</div>

            <div class="table-responsive">
                <table class="table report-table mb-0">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th>Total Till Date</th>
                            <th>Total Yesterday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Patient Created</td>
                            <td><?= $patients_total ?></td>
                            <td><?= $patients_yesterday ?></td>
                        </tr>
                        <tr>
                            <td>ABHA Created</td>
                            <td><?= $abha_total ?></td>
                            <td><?= $abha_yesterday ?></td>
                        </tr>
                        <tr>
                            <td>Document</td>
                            <td><?= $docs_total ?></td>
                            <td><?= $docs_yesterday ?></td>
                        </tr>
                        <tr>
                            <td>Document Linked</td>
                            <td><?= $docs_linked_total ?></td>
                            <td><?= $docs_linked_yesterday ?></td>
                        </tr>
                        <tr>
                            <td>Token Details</td>
                            <td><?= $tokens_total ?></td>
                            <td><?= $tokens_yesterday ?></td>
                        </tr>
                        <tr>
                            <td>Red Flag Document</td>
                            <td><?= $red_flag_total ?></td>
                            <td><?= $red_flag_yesterday ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button class="btn-red-flag" onclick="alert('Document flagging is not available yet.');">
                RED FLAG DOCUMENT
            </button>
        </div>
    </main>
</body>

</html>
