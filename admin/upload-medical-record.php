<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: auth/login.php"); exit(); }
include "db-conn.php";

$admin_id = $_SESSION['admin_id'] ?? 1;

$record_for   = in_array($_GET['for'] ?? '', ['patient','school_member']) ? $_GET['for'] : 'patient';
$pre_patient  = intval($_GET['patient_id'] ?? 0);
$pre_member   = intval($_GET['member_id'] ?? 0);

$doc_types = ['Lab Report','Diagnostic Report','Prescription','Vaccination Certificate','X-Ray / Imaging','Health Checkup','Discharge Summary','Other'];
$allowed_ext = ['pdf','doc','docx','jpg','jpeg','png'];
$max_size = 10 * 1024 * 1024; // 10MB

$patients_res = mysqli_query($conn, "SELECT id, name, last_name, email, mobile FROM users ORDER BY name ASC");
$members_res  = mysqli_query($conn, "SELECT m.id, m.name, m.type, m.member_uid, s.school_name
    FROM school_members m JOIN schools s ON s.id = m.school_id ORDER BY s.school_name ASC, m.name ASC");

$error = '';
$old = $_POST ?: ['record_for' => $record_for, 'patient_id' => $pre_patient, 'member_id' => $pre_member, 'document_type' => $doc_types[0]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $record_for   = $_POST['record_for'] ?? '';
        $patient_id   = intval($_POST['patient_id'] ?? 0);
        $member_id    = intval($_POST['member_id'] ?? 0);
        $doc_title    = trim($_POST['document_title'] ?? '');
        $doc_type     = trim($_POST['document_type'] ?? 'Other');
        $description  = trim($_POST['description'] ?? '');

        if (!in_array($record_for, ['patient','school_member'])) throw new Exception("Please choose who this record is for.");
        if (!$doc_title) throw new Exception("Document title is required.");
        if (!in_array($doc_type, $doc_types)) $doc_type = 'Other';

        if ($record_for === 'patient') {
            if (!$patient_id) throw new Exception("Please select a patient.");
            $chk = $conn->prepare("SELECT id FROM users WHERE id=?");
            $chk->bind_param('i', $patient_id); $chk->execute();
            if ($chk->get_result()->num_rows === 0) throw new Exception("Selected patient does not exist.");
        } else {
            if (!$member_id) throw new Exception("Please select a school member.");
            $chk = $conn->prepare("SELECT id, school_id FROM school_members WHERE id=?");
            $chk->bind_param('i', $member_id); $chk->execute();
            $member_row = $chk->get_result()->fetch_assoc();
            if (!$member_row) throw new Exception("Selected member does not exist.");
        }

        if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a file to upload.");
        }
        $file = $_FILES['document_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) throw new Exception("Invalid file type. Only PDF, DOC, DOCX, JPG, PNG allowed.");
        if ($file['size'] > $max_size) throw new Exception("File too large. Max size is 10MB.");

        $document_name = $doc_type . ' — ' . $doc_title;
        $file_type_mime = $file['type'] ?: $ext;

        if ($record_for === 'patient') {
            $upload_dir = '../uploads/patient_documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_file = 'patient_' . $patient_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_file)) throw new Exception("Failed to save uploaded file.");
            $db_path = 'uploads/patient_documents/' . $new_file;

            $ins = $conn->prepare("INSERT INTO patient_documents
                (patient_id, doctor_id, uploaded_by_role, uploaded_by_admin_id, document_name, description, file_path, file_type, uploaded_at)
                VALUES (?, NULL, 'admin', ?, ?, ?, ?, ?, NOW())");
            $ins->bind_param('iissss', $patient_id, $admin_id, $document_name, $description, $db_path, $file_type_mime);
            if (!$ins->execute()) { @unlink($upload_dir . $new_file); throw new Exception("Failed to save record: " . $conn->error); }

            $_SESSION['success_message'] = "Medical record uploaded for patient successfully.";
            header("Location: medical-records.php?tab=patients");
            exit();
        } else {
            $upload_dir = '../uploads/school_documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_file = 'member_' . $member_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_file)) throw new Exception("Failed to save uploaded file.");
            $db_path = 'uploads/school_documents/' . $new_file;

            $ins = $conn->prepare("INSERT INTO school_member_documents
                (member_id, school_id, document_name, document_type, description, file_path, file_type, uploaded_by_admin_id, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $ins->bind_param('iisssssi', $member_id, $member_row['school_id'], $document_name, $doc_type, $description, $db_path, $file_type_mime, $admin_id);
            if (!$ins->execute()) { @unlink($upload_dir . $new_file); throw new Exception("Failed to save record: " . $conn->error); }

            $_SESSION['success_message'] = "Medical record uploaded successfully.";
            header("Location: medical-records.php?tab=school");
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $old = $_POST;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Upload Medical Record</title>
    <?php include "links.php"; ?>
    <style>
        .doctor-form { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 30px; }
        .form-label { font-weight: 500; color: #495057; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 6px; padding: 10px 15px; border: 1px solid #e0e0e0; }
        .form-control:focus, .form-select:focus { border-color: #0C74C5; box-shadow: 0 0 0 3px rgba(12,116,197,.15); }
        .section-title { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px; color: #495057; font-weight: 600; }
        .type-pick { border: 2px solid #e5e7eb; border-radius: 10px; padding: 16px; text-align: center; cursor: pointer; transition: .15s; }
        .type-pick.active { border-color: #0C74C5; background: #eaf4fd; }
        .type-pick i { font-size: 1.6rem; display: block; margin-bottom: 8px; }
    </style>
</head>
<body class="crm_body_bg">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>
        <div class="main_content_iner">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="page-header mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <h2 class="mb-0">Upload Medical Record</h2>
                                <a href="medical-records.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Records
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-10">
                        <div class="doctor-form">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" id="recordForm">

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">Record For</h4></div>
                                    <div class="col-12 mb-3">
                                        <input type="hidden" name="record_for" id="recordForInput" value="<?= htmlspecialchars($old['record_for'] ?? 'patient') ?>">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="type-pick" data-for="patient" onclick="pickFor('patient')">
                                                    <i class="fas fa-user-injured" style="color:#c0392b;"></i> Patient
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="type-pick" data-for="school_member" onclick="pickFor('school_member')">
                                                    <i class="fas fa-user-graduate" style="color:#0277bd;"></i> School Member <small class="d-block text-muted">(Teacher / Student / Staff)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-8 mb-3" id="patientField">
                                        <label class="form-label">Patient <span class="text-danger">*</span></label>
                                        <select class="form-select" name="patient_id">
                                            <option value="">Select Patient</option>
                                            <?php mysqli_data_seek($patients_res, 0); while ($p = mysqli_fetch_assoc($patients_res)): ?>
                                                <option value="<?= $p['id'] ?>" <?= ($old['patient_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars(trim($p['name'] . ' ' . $p['last_name'])) ?>
                                                    <?= $p['mobile'] ? ' — ' . htmlspecialchars($p['mobile']) : '' ?>
                                                    <?= $p['email'] ? ' (' . htmlspecialchars($p['email']) . ')' : '' ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-8 mb-3" id="memberField">
                                        <label class="form-label">School Member <span class="text-danger">*</span></label>
                                        <select class="form-select" name="member_id">
                                            <option value="">Select Member</option>
                                            <?php mysqli_data_seek($members_res, 0); while ($m = mysqli_fetch_assoc($members_res)): ?>
                                                <option value="<?= $m['id'] ?>" <?= ($old['member_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($m['name']) ?> — <?= $m['type'] ?> (<?= htmlspecialchars($m['member_uid']) ?>) — <?= htmlspecialchars($m['school_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">Document Details</h4></div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Document Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="document_title" required placeholder="e.g. Blood Test Report - Jan 2026" value="<?= htmlspecialchars($old['document_title'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Document Type</label>
                                        <select class="form-select" name="document_type">
                                            <?php foreach ($doc_types as $t): ?>
                                                <option value="<?= $t ?>" <?= ($old['document_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Notes / Description</label>
                                        <textarea class="form-control" name="description" rows="3" placeholder="Optional notes about this record"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label d-block">File <span class="text-danger">*</span></label>
                                        <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                        <small class="text-muted">PDF, DOC, DOCX, JPG or PNG. Max 10MB.</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mt-2">
                                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-upload me-2"></i> Upload Record</button>
                                        <a href="medical-records.php" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i> Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>
    <script>
        function pickFor(type) {
            document.getElementById('recordForInput').value = type;
            document.querySelectorAll('.type-pick').forEach(el => el.classList.toggle('active', el.dataset.for === type));
            document.getElementById('patientField').style.display = type === 'patient' ? 'block' : 'none';
            document.getElementById('memberField').style.display = type === 'school_member' ? 'block' : 'none';
        }
        pickFor(document.getElementById('recordForInput').value || 'patient');
    </script>
</body>
</html>
