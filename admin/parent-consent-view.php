<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: parent-consents.php');
    exit;
}

$stmt = $conn->prepare("SELECT c.*, s.school_name, s.school_uid, m.member_uid, m.id AS member_row_id, d.name AS doctor_name
    FROM parent_consent_forms c
    LEFT JOIN schools s ON s.id = c.school_id
    LEFT JOIN school_members m ON m.id = c.member_id
    LEFT JOIN doctors d ON d.id = c.recorded_by_doctor_id
    WHERE c.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
if (!$c) {
    header('Location: parent-consents.php');
    exit;
}

$labels = [
    'general_checkup'   => 'General Physical Checkup',
    'height_weight'     => 'Height, Weight & BMI',
    'vision_test'       => 'Vision / Eyesight Screening',
    'dental_check'      => 'Dental Examination',
    'blood_pressure'    => 'Blood Pressure & Pulse Check',
    'vaccination_check' => 'Vaccination Status Review',
    'mental_wellness'   => 'Mental Wellness Screening',
    'data_storage'      => 'Digital Health Record Storage',
    'data_share_doctor' => 'Share Data with School Doctor',
    'data_share_school' => 'Anonymised Data with School',
];
$items = json_decode($c['consent_items'] ?? '[]', true) ?: [];

function rowline($label, $val, $cols = 'col-md-6')
{
    $val = trim((string) $val);
    echo '<div class="' . $cols . ' mb-3"><div class="detail-label">' . htmlspecialchars($label) . '</div><div class="detail-val">'
        . ($val !== '' ? nl2br(htmlspecialchars($val)) : '<span class="text-muted">—</span>') . '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Consent — <?= htmlspecialchars($c['student_name']) ?></title>
    <?php include "links.php"; ?>
    <!-- styles in assets/css/colors/default.css -->
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
                        <h4 class="mb-0 fw-bold">Consent — <?= htmlspecialchars($c['student_name']) ?></h4>
                        <small class="text-muted">Reference <?= strtoupper(substr($c['token'], 0, 8)) ?> &bull; submitted <?= date('d M Y, h:i A', strtotime($c['submitted_at'])) ?></small>
                    </div>
                    <a href="parent-consents.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <div class="detail-card">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php $status_pill = ['pending' => 'pill-warn', 'reviewed' => 'pill-success', 'archived' => 'pill-muted']; ?>
                        <span class="pill <?= $status_pill[$c['status']] ?? 'pill-muted' ?>"><?= ucfirst($c['status']) ?></span>
                        <?php if ($c['source'] === 'doctor'): ?>
                            <span class="pill pill-purple"><i class="fas fa-user-md"></i>Recorded by <?= $c['doctor_name'] ? 'Dr. ' . htmlspecialchars($c['doctor_name']) : 'doctor' ?> at point of care</span>
                        <?php else: ?>
                            <span class="pill pill-info"><i class="fas fa-globe"></i>Submitted online by parent</span>
                        <?php endif; ?>
                        <?php if ($c['consent_given']): ?>
                            <span class="pill pill-success"><i class="fas fa-check"></i>Declaration agreed</span>
                        <?php else: ?>
                            <span class="pill pill-danger"><i class="fas fa-times"></i>Declaration NOT agreed</span>
                        <?php endif; ?>
                        <div class="ms-auto d-flex gap-2">
                            <?php if ($c['status'] !== 'reviewed'): ?>
                                <a href="parent-consents.php?set_status=reviewed&id=<?= $c['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Mark Reviewed</a>
                            <?php endif; ?>
                            <?php if ($c['status'] !== 'archived'): ?>
                                <a href="parent-consents.php?set_status=archived&id=<?= $c['id'] ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Archive this consent?')"><i class="fas fa-box-archive me-1"></i>Archive</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="detail-card">
                            <h5><i class="fas fa-user me-2"></i>Parent / Guardian</h5>
                            <div class="row">
                                <?php
                                rowline('Name', $c['parent_name']);
                                rowline('Relation', $c['relation']);
                                rowline('Mobile', $c['parent_mobile']);
                                rowline('Email', $c['parent_email']);
                                rowline('Aadhaar (last 4)', $c['parent_aadhar_last4'] ? 'XXXX-XXXX-' . $c['parent_aadhar_last4'] : '');
                                ?>
                            </div>
                        </div>

                        <div class="detail-card">
                            <h5><i class="fas fa-school me-2"></i>School</h5>
                            <div class="row">
                                <?php
                                rowline('School', $c['school_name'] ?: $c['school_name_manual']);
                                rowline('School ID', $c['school_uid']);
                                if ($c['member_uid']) {
                                    echo '<div class="col-md-6 mb-3"><div class="detail-label">Linked Member</div><div class="detail-val"><a href="' . htmlspecialchars(BASE_URL) . 'admin/school-view.php?id=' . (int) $c['school_id'] . '">' . htmlspecialchars($c['member_uid']) . '</a></div></div>';
                                } else {
                                    echo '<div class="col-md-6 mb-3"><div class="detail-label">Linked Member</div><div class="detail-val text-muted">Not linked to a school member yet</div></div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="detail-card">
                            <h5><i class="fas fa-child me-2"></i>Student</h5>
                            <div class="row">
                                <?php
                                rowline('Name', $c['student_name']);
                                rowline('Date of Birth', $c['student_dob'] && $c['student_dob'] !== '0000-00-00' ? date('d M Y', strtotime($c['student_dob'])) : '');
                                rowline('Gender', $c['student_gender']);
                                rowline('Class / Section', trim(($c['student_class'] ?? '') . ' ' . ($c['student_section'] ?? '')));
                                rowline('Roll No.', $c['student_roll_no']);
                                rowline('Blood Group', $c['blood_group']);
                                rowline('ABHA Number', $c['student_abha_number']);
                                rowline('ABHA Address', $c['student_abha_address']);
                                rowline('Address', trim(($c['student_address'] ?? '') . ' ' . ($c['student_city'] ?? '') . ' ' . ($c['student_state'] ?? '') . ' ' . ($c['student_pincode'] ?? '')));
                                ?>
                            </div>
                        </div>

                        <div class="detail-card">
                            <h5><i class="fas fa-notes-medical me-2"></i>Medical History (declared)</h5>
                            <div class="row">
                                <?php
                                rowline('Known Allergies', $c['known_allergies']);
                                rowline('Existing Conditions', $c['existing_conditions']);
                                rowline('Current Medications', $c['current_medications']);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-card">
                    <h5><i class="fas fa-clipboard-check me-2"></i>Consented Services (<?= count(array_filter($items)) ?>/<?= count($labels) ?>)</h5>
                    <div class="check-grid">
                        <?php foreach ($labels as $k => $lbl): $on = !empty($items[$k]); ?>
                            <div class="<?= $on ? 'ck' : 'ck-off' ?>">
                                <i class="fas fa-<?= $on ? 'check-circle' : 'times-circle' ?> me-1"></i><?= htmlspecialchars($lbl) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="detail-card">
                    <h5><i class="fas fa-file-contract me-2"></i>Declaration &amp; Audit</h5>
                    <div class="row">
                        <?php
                        rowline('Declaration Text', $c['declaration_text'], 'col-12');
                        rowline('Submitted At', date('d M Y, h:i A', strtotime($c['submitted_at'])));
                        rowline('Reviewed At', $c['reviewed_at'] ? date('d M Y, h:i A', strtotime($c['reviewed_at'])) : '');
                        rowline('IP Address', $c['ip_address']);
                        rowline('User Agent', $c['user_agent']);
                        ?>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
</body>
</html>
