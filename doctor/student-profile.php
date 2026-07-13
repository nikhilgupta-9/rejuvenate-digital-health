<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$member_id = (int) ($_GET['id'] ?? 0);
if (!$member_id) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$stmt = $conn->prepare("SELECT m.*, s.school_name, s.id as school_id FROM school_members m JOIN schools s ON s.id = m.school_id WHERE m.id = ? LIMIT 1");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) {
    header('Location: ' . BASE_URL . 'doctor/school-students.php');
    exit;
}

$age = '';
if (!empty($m['dob'])) {
    try {
        $age = (new DateTime($m['dob']))->diff(new DateTime())->y;
    } catch (Exception $e) {
    }
}

$doc_types = ['Lab Report', 'Diagnostic Report', 'X-Ray / Imaging', 'Vaccination Certificate', 'Medical Certificate', 'Discharge Summary', 'Other'];

// Prescriptions written for this member
$rx_stmt = $conn->prepare("SELECT p.*, d.name as doctor_name FROM school_member_prescriptions p
    LEFT JOIN doctors d ON d.id = p.doctor_id WHERE p.member_id = ? ORDER BY p.created_at DESC LIMIT 50");
$rx_stmt->bind_param('i', $member_id);
$rx_stmt->execute();
$prescriptions = $rx_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Documents uploaded for this member
$doc_stmt = $conn->prepare("SELECT sd.*, d.name as doctor_name, au.first_name as admin_first, au.last_name as admin_last
    FROM school_member_documents sd
    LEFT JOIN doctors d ON d.id = sd.uploaded_by_doctor_id
    LEFT JOIN admin_user au ON au.id = sd.uploaded_by_admin_id
    WHERE sd.member_id = ? ORDER BY sd.uploaded_at DESC LIMIT 100");
$doc_stmt->bind_param('i', $member_id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sidebar_active = 'school-students';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($m['name']) ?> — Student Profile</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/style.css">
    <style>
        .profile-header {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            padding: 22px 24px;
            margin-bottom: 16px;
        }

        .p-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #0277bd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .p-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .p-sub {
            font-size: .84rem;
            color: #6b7280;
        }

        .rx-item,
        .doc-item {
            background: #fff;
            border: 1px solid #eef0f3;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
        }

        .rx-item .rx-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .rx-item .rx-text {
            white-space: pre-wrap;
            font-size: .85rem;
            color: #374151;
            background: #f9fafb;
            border-radius: 8px;
            padding: 10px 12px;
        }

        /* ── Tabs ── */
        .profile-tabs {
            display: flex;
            gap: 4px;
            background: #fff;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            flex-wrap: wrap;
        }

        .p-tab {
            padding: 9px 18px;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: .15s;
            user-select: none;
        }

        .p-tab:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .p-tab.active {
            background: #0277bd;
            color: #fff;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn .2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Cards / sections ── */
        .info-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            padding: 18px 20px;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: .95rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f3f4f6;
        }

        /* ── Form grid (Bootstrap 5 has no .form-row; provide our own) ── */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -8px;
            margin-left: -8px;
        }

        .form-row>[class*="col-"] {
            padding-right: 8px;
            padding-left: 8px;
        }

        .info-label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .doc-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #e0f2fe;
            color: #0277bd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .btn-primary-custom {
            background: #0277bd;
            border-color: #0277bd;
            color: #fff;
        }

        .btn-primary-custom:hover {
            background: #025a94;
            border-color: #025a94;
            color: #fff;
        }
    </style>
</head>

<body>
    <?php $sidebar_active = 'school-students';
    include(__DIR__ . "/inc/sidebar.php"); ?>

    <main class="doctor-content">

        <!-- Profile header -->
        <div class="profile-header d-flex align-items-center justify-content-between flex-wrap" style="gap:14px;">
            <div class="d-flex align-items-center" style="gap:16px;">
                <div class="p-avatar"><?= strtoupper(substr($m['name'], 0, 1)) ?></div>
                <div>
                    <div class="p-name"><?= htmlspecialchars($m['name']) ?></div>
                    <div class="p-sub">
                        <?= htmlspecialchars($m['member_uid']) ?> &bull; <?= htmlspecialchars($m['type']) ?> &bull; <?= htmlspecialchars($m['school_name']) ?>
                        <?php if ($age): ?> &bull; <?= $age ?> yrs<?php endif; ?>
                        <?php if ($m['gender']): ?> &bull; <?= htmlspecialchars($m['gender']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="school-students.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left me-1"></i> All Students
            </a>
        </div>

        <!-- Tabs -->
        <div class="profile-tabs">
            <div class="p-tab active" onclick="showTab('info',this)"><i class="fa fa-user me-1"></i> Information</div>
            <div class="p-tab" onclick="showTab('rx',this)"><i class="fa fa-file-medical me-1"></i> Prescriptions</div>
            <div class="p-tab" onclick="showTab('docs',this)"><i class="fa fa-file-text-o me-1"></i> Medical Reports</div>
        </div>

        <!-- ── TAB: Information ── -->
        <div class="tab-pane active" id="tab-info">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-section">
                        <div class="section-title"><i class="fa fa-id-card me-2" style="color:#0277bd;"></i>Basic Information</div>
                        <div class="form-row">
                            <div class="col-md-6 mb-2"><span class="info-label">Blood Group</span><div><?= htmlspecialchars($m['blood_group'] ?: 'Not set') ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Date of Birth</span><div><?= $m['dob'] ? date('d M Y', strtotime($m['dob'])) : 'Not set' ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Phone</span><div><?= htmlspecialchars($m['phone'] ?: 'Not set') ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Email</span><div><?= htmlspecialchars($m['email'] ?: 'Not set') ?></div></div>
                            <div class="col-md-12 mb-2"><span class="info-label">Address</span><div><?= htmlspecialchars($m['address'] ?: 'Not set') ?></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-section">
                        <div class="section-title"><i class="fa fa-graduation-cap me-2" style="color:#0277bd;"></i>
                            <?= $m['type'] === 'Student' ? 'Academic Details' : 'Employment Details' ?>
                        </div>
                        <div class="form-row">
                            <?php if ($m['type'] === 'Student'): ?>
                                <div class="col-md-6 mb-2"><span class="info-label">Class</span><div><?= htmlspecialchars($m['class'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Section</span><div><?= htmlspecialchars($m['section'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Roll No.</span><div><?= htmlspecialchars($m['roll_number'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Admission No.</span><div><?= htmlspecialchars($m['admission_number'] ?: '—') ?></div></div>
                            <?php else: ?>
                                <div class="col-md-6 mb-2"><span class="info-label">Employee ID</span><div><?= htmlspecialchars($m['employee_id'] ?: '—') ?></div></div>
                                <div class="col-md-6 mb-2"><span class="info-label">Designation</span><div><?= htmlspecialchars($m['designation'] ?: '—') ?></div></div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-2"><span class="info-label">ABHA Address</span><div><?= htmlspecialchars($m['abha_address'] ?: 'Not linked') ?></div></div>
                            <div class="col-md-6 mb-2"><span class="info-label">Status</span><div><?= htmlspecialchars($m['status']) ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB: Prescriptions ── -->
        <div class="tab-pane" id="tab-rx">
            <div class="info-section">
                <div class="section-title"><i class="fa fa-file-medical me-2" style="color:#0277bd;"></i>Write New Prescription</div>
                <?php if (isset($_SESSION['rx_error'])): ?>
                    <div class="alert alert-danger" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['rx_error']); unset($_SESSION['rx_error']); ?></div>
                <?php endif; ?>
                <form method="POST" action="save-student-prescription.php">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    <div class="form-row">
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control form-control-sm" placeholder="e.g. Seasonal flu, Viral fever">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Symptoms</label>
                            <textarea name="symptoms" class="form-control form-control-sm" rows="2" placeholder="Observed symptoms"></textarea>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="info-label d-block mb-1">Prescription (Rx) <span class="text-danger">*</span></label>
                            <textarea name="prescription_text" class="form-control form-control-sm" rows="5" required
                                placeholder="Medicine name, dosage, frequency, duration…"></textarea>
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="info-label d-block mb-1">Advice / Notes</label>
                            <textarea name="advice" class="form-control form-control-sm" rows="2" placeholder="Rest, diet, precautions…"></textarea>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="info-label d-block mb-1">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-sm btn-primary-custom"><i class="fa fa-check me-1"></i> Save Prescription</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="font-size:.84rem;color:#374151;font-weight:600;margin:16px 0 8px;">
                <?= count($prescriptions) ?> prescription(s)
            </div>

            <?php if (empty($prescriptions)): ?>
                <div class="info-section text-center py-4">
                    <i class="fa fa-file-medical fa-2x text-muted mb-2"></i>
                    <div class="text-muted" style="font-size:.86rem;">No prescriptions written yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($prescriptions as $rx): ?>
                    <div class="rx-item">
                        <div class="rx-head">
                            <div>
                                <div style="font-weight:600;font-size:.9rem;color:#1f2937;"><?= htmlspecialchars($rx['diagnosis'] ?: 'General Prescription') ?></div>
                                <div style="font-size:.74rem;color:#9ca3af;">
                                    Dr. <?= htmlspecialchars($rx['doctor_name'] ?? 'Unknown') ?> &bull; <?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?>
                                    <?php if ($rx['follow_up_date']): ?> &bull; Follow-up: <?= date('d M Y', strtotime($rx['follow_up_date'])) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex" style="gap:6px;">
                                <a href="print-student-prescription.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print"><i class="fa fa-print"></i></a>
                                <?php if ((int)$rx['doctor_id'] === $doctor_id): ?>
                                    <form method="POST" action="delete-student-prescription.php" onsubmit="return confirm('Delete this prescription?');" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $rx['id'] ?>">
                                        <input type="hidden" name="member_id" value="<?= $member_id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($rx['symptoms']): ?><div style="font-size:.8rem;color:#6b7280;margin-bottom:6px;"><strong>Symptoms:</strong> <?= htmlspecialchars($rx['symptoms']) ?></div><?php endif; ?>
                        <div class="rx-text"><?= htmlspecialchars($rx['prescription_text']) ?></div>
                        <?php if ($rx['advice']): ?><div style="font-size:.8rem;color:#6b7280;margin-top:6px;"><strong>Advice:</strong> <?= htmlspecialchars($rx['advice']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── TAB: Medical Reports ── -->
        <div class="tab-pane" id="tab-docs">
            <div class="info-section">
                <div class="section-title"><i class="fa fa-upload me-2" style="color:#0277bd;"></i>Upload Medical Report</div>
                <?php if (isset($_SESSION['doc_error'])): ?>
                    <div class="alert alert-danger" style="font-size:.82rem;"><?= htmlspecialchars($_SESSION['doc_error']); unset($_SESSION['doc_error']); ?></div>
                <?php endif; ?>
                <form method="POST" action="save-student-document.php" enctype="multipart/form-data">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    <div class="form-row">
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Report Title</label>
                            <input type="text" name="document_title" class="form-control form-control-sm" placeholder="e.g. Blood Test Report" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Report Type</label>
                            <select name="document_type" class="form-control form-control-sm">
                                <?php foreach ($doc_types as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">File</label>
                            <input type="file" name="document_file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">Max 10MB — PDF, DOC, DOCX, JPG, PNG</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="info-label d-block mb-1">Description (optional)</label>
                            <textarea name="description" class="form-control form-control-sm" rows="1"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-sm btn-primary-custom"><i class="fa fa-upload me-1"></i> Upload</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="font-size:.84rem;color:#374151;font-weight:600;margin:16px 0 8px;">
                <?= count($documents) ?> document(s)
            </div>

            <?php if (empty($documents)): ?>
                <div class="info-section text-center py-4">
                    <i class="fa fa-folder-open-o fa-2x text-muted mb-2"></i>
                    <div class="text-muted" style="font-size:.86rem;">No medical reports uploaded yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc):
                    $uploader = $doc['doctor_name'] ? 'Dr. ' . $doc['doctor_name'] : (trim(($doc['admin_first'] ?? '') . ' ' . ($doc['admin_last'] ?? '')) ?: 'Admin');
                ?>
                    <div class="doc-item d-flex align-items-center" style="gap:12px;">
                        <div class="doc-icon"><i class="fa fa-file-text-o"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:.88rem;color:#1f2937;"><?= htmlspecialchars($doc['document_name']) ?></div>
                            <?php if ($doc['description']): ?><div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($doc['description']) ?></div><?php endif; ?>
                            <div style="font-size:.76rem;color:#9ca3af;">
                                <?= date('d M Y', strtotime($doc['uploaded_at'])) ?> &bull; Uploaded by <?= htmlspecialchars($uploader) ?>
                            </div>
                        </div>
                        <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-eye"></i></a>
                        <?php if ((int)($doc['uploaded_by_doctor_id'] ?? 0) === $doctor_id): ?>
                            <form method="POST" action="delete-student-document.php" onsubmit="return confirm('Delete this document?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                <input type="hidden" name="member_id" value="<?= $member_id ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <script>
        function showTab(name, el) {
            document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.p-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            el.classList.add('active');
        }
        const hash = location.hash.replace('#', '') || new URLSearchParams(location.search).get('tab');
        if (hash) {
            const btn = document.querySelector('[onclick*="\'' + hash + '\'"]');
            if (btn) btn.click();
        }
    </script>
</body>

</html>
