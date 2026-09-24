<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/../lib/Abha.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

$patient_id_filter = intval($_GET['patient_id'] ?? 0);
$type_filter       = trim($_GET['type'] ?? '');
$search_query      = trim($_GET['search'] ?? '');

$success_message = "";
$error_message   = "";

// Handle Document Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $upload_patient_id = intval($_POST['patient_id'] ?? 0);
    $upload_appt_id    = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $doc_name          = trim($_POST['document_name'] ?? '');
    $doc_type          = trim($_POST['document_type'] ?? 'Diagnostic Report (Lab)');
    $description       = trim($_POST['description'] ?? '');

    // Ownership check: must be linked in doctor_patients or appointments
    $chk = $conn->prepare("
        SELECT 1 FROM doctor_patients WHERE doctor_id = ? AND patient_id = ?
        UNION
        SELECT 1 FROM appointments WHERE doctor_id = ? AND user_id = ?
        LIMIT 1
    ");
    $chk->bind_param('iiii', $doctor_id, $upload_patient_id, $doctor_id, $upload_patient_id);
    $chk->execute();
    if (!$chk->get_result()->fetch_row()) {
        $error_message = "You can only upload diagnostic reports for patients linked to your clinical panel.";
    } elseif (!$doc_name) {
        $error_message = "Document name or test investigation title is required.";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== 0) {
        $error_message = "Please select a valid document or report file to upload.";
    } else {
        $file = $_FILES['document_file'];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file['type'], $allowed, true) || $file['size'] > 15728640) { // 15MB limit
            $error_message = "Invalid file. Allowed formats: PDF, JPG, PNG, WEBP, DOC, DOCX up to 15MB.";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $dir = dirname(__DIR__) . '/uploads/patient_documents/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $new_name = 'diag_' . $upload_patient_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . $new_name;
            $rel_path = 'uploads/patient_documents/' . $new_name;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $ins = $conn->prepare("
                    INSERT INTO patient_documents 
                        (patient_id, doctor_id, appointment_id, uploaded_by_role, document_name, document_type, description, file_path, file_type, uploaded_at)
                    VALUES (?, ?, ?, 'doctor', ?, ?, ?, ?, ?, NOW())
                ");
                $ftype = $file['type'];
                $ins->bind_param('iiisssss', $upload_patient_id, $doctor_id, $upload_appt_id, $doc_name, $doc_type, $description, $rel_path, $ftype);
                if ($ins->execute()) {
                    $success_message = "Diagnostic report successfully uploaded and linked to the patient's ABDM health record.";
                } else {
                    $error_message = "Database save failed: " . $conn->error;
                }
                $ins->close();
            } else {
                $error_message = "File upload failed on server. Please check folder write permissions.";
            }
        }
    }
}

// Handle Document Deletion
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $chk_del = $conn->prepare("SELECT file_path FROM patient_documents WHERE id = ? AND doctor_id = ? LIMIT 1");
    $chk_del->bind_param('ii', $del_id, $doctor_id);
    $chk_del->execute();
    $d_row = $chk_del->get_result()->fetch_assoc();
    $chk_del->close();

    if ($d_row) {
        $real_file = dirname(__DIR__) . '/' . ltrim($d_row['file_path'], '/');
        if (is_file($real_file)) @unlink($real_file);
        $del_stmt = $conn->prepare("DELETE FROM patient_documents WHERE id = ? AND doctor_id = ?");
        $del_stmt->bind_param('ii', $del_id, $doctor_id);
        $del_stmt->execute();
        $del_stmt->close();
        $success_message = "Document deleted successfully.";
    } else {
        $error_message = "Document not found or permission denied.";
    }
}

// Stats for this doctor
$stats_sql = "
    SELECT 
        COUNT(*) as total_docs,
        SUM(CASE WHEN document_type LIKE '%Lab%' THEN 1 ELSE 0 END) as lab_count,
        SUM(CASE WHEN document_type LIKE '%Prescription%' THEN 1 ELSE 0 END) as rx_count,
        SUM(CASE WHEN document_type LIKE '%Radiology%' OR document_type LIKE '%Scan%' OR document_type LIKE '%Ray%' OR document_type LIKE '%MRI%' THEN 1 ELSE 0 END) as scan_count,
        COUNT(DISTINCT patient_id) as patients_with_docs
    FROM patient_documents
    WHERE doctor_id = ?
";
$st_stmt = $conn->prepare($stats_sql);
$st_stmt->bind_param('i', $doctor_id);
$st_stmt->execute();
$doc_stats = $st_stmt->get_result()->fetch_assoc();
$st_stmt->close();

// Build documents query with filters
$query = "
    SELECT 
        pd.*,
        u.name as patient_name,
        u.last_name as patient_last_name,
        u.mobile as patient_phone,
        u.gender,
        " . Abha::selectAliases('aa', 'u') . ",
        TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age
    FROM patient_documents pd
    INNER JOIN users u ON pd.patient_id = u.id
    " . Abha::joinClause('patient', 'u', 'aa') . "
    WHERE pd.doctor_id = ?
";
$types = 'i';
$params = [$doctor_id];

if ($patient_id_filter > 0) {
    $query .= " AND pd.patient_id = ?";
    $types .= 'i';
    $params[] = $patient_id_filter;
}
if ($type_filter !== '' && $type_filter !== 'all') {
    $query .= " AND pd.document_type LIKE ?";
    $types .= 's';
    $params[] = '%' . $type_filter . '%';
}
if ($search_query !== '') {
    $query .= " AND (pd.document_name LIKE ? OR pd.description LIKE ? OR u.name LIKE ? OR u.last_name LIKE ? OR u.mobile LIKE ?)";
    $like = '%' . $search_query . '%';
    $types .= 'sssss';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
$query .= " ORDER BY pd.uploaded_at DESC LIMIT 150";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load doctor's patient list for upload modal & filter dropdown
$pat_sql = "
    SELECT DISTINCT u.id, u.name, u.last_name, u.mobile, " . Abha::selectAliases('aa', 'u') . "
    FROM users u
    INNER JOIN doctor_patients dp ON dp.patient_id = u.id
    " . Abha::joinClause('patient', 'u', 'aa') . "
    WHERE dp.doctor_id = ?
    ORDER BY u.name ASC
";
$pat_stmt = $conn->prepare($pat_sql);
$pat_stmt->bind_param('i', $doctor_id);
$pat_stmt->execute();
$doctor_patients = $pat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pat_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostic &amp; Lab Reports (ABDM M3) — REJUVENATE Doctor</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/doctor.css">

    <style>
        :root {
            --primary: #0C74C5;
            --primary-dk: #0a5fa0;
            --primary-light: #e0f2fe;
            --accent: #02c9b8;
            --accent-dk: #01a89a;
            --accent-light: #ccfbf1;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-700: #374151;
            --gray-800: #1f2937;
        }

        body {
            background-color: #f4f7fb;
            color: var(--gray-800);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Stat Cards */
        .doc-stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .doc-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }
        .doc-stat-num { font-size: 1.5rem; font-weight: 800; color: #1f2937; line-height: 1; }
        .doc-stat-lbl { font-size: .72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-top: 5px; }
        .doc-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* Category Badges (ABDM Standard HI-Types) */
        .cat-badge {
            font-size: .72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .cat-lab { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .cat-rx { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .cat-scan { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .cat-discharge { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .cat-other { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

        /* ABHA Pill Badge */
        .abha-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: .68rem;
            border-radius: 16px;
            padding: 2px 8px;
            font-weight: 700;
            border: 1px solid #bae6fd;
            text-decoration: none;
        }
        .abha-pill:hover {
            color: #0C74C5;
            text-decoration: none;
        }

        /* Search Filter Card */
        .filter-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.04);
            padding: 16px 18px;
            margin-bottom: 20px;
        }

        /* Table Card */
        .docs-main-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Mobile Card view for reports */
        .mobile-doc-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .mobile-doc-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        /* File Icon Preview Container */
        .file-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        /* Modal Enhancements */
        .modal-header-themed {
            background: linear-gradient(135deg, #0C74C5 0%, #0a5fa0 100%);
            color: #fff;
            border-bottom: none;
        }

        .drop-zone-wrapper {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all .2s ease;
        }
        .drop-zone-wrapper:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        @media (max-width: 767.98px) {
            .doc-stat-card {
                padding: 12px 14px;
            }
            .doc-stat-num {
                font-size: 1.3rem;
            }
            .doc-stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1.05rem;
            }
        }
    </style>
</head>
<body>
    <?php
    $sidebar_active = 'documents';
    include __DIR__ . '/inc/sidebar.php';
    ?>

    <main class="doctor-content">
        <!-- Top Title & Action Bar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <h1 style="font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:0;">
                    <i class="fa fa-folder-open text-primary me-2"></i> Diagnostic &amp; Lab Investigations
                </h1>
                <div style="font-size:.78rem;color:#6b7280;margin-top:2px;">
                    Ayushman Bharat Digital Mission (ABDM) Milestone 3 (M3) Compliant Health Records &amp; Diagnostic Repository
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#uploadDocModal" style="background:#0C74C5;border-color:#0C74C5;border-radius:10px;font-size:.85rem;box-shadow:0 3px 10px rgba(12,116,197,0.25);">
                    <i class="fa fa-cloud-arrow-up"></i> Upload Diagnostic / Lab Report
                </button>
            </div>
        </div>

        <!-- Flash alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="fa fa-circle-check me-2"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="fa fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="doc-stat-card" style="border-left-color:#0C74C5;">
                    <div>
                        <div class="doc-stat-num"><?= (int)($doc_stats['total_docs'] ?? 0) ?></div>
                        <div class="doc-stat-lbl">Total Documents</div>
                    </div>
                    <div class="doc-stat-icon" style="background:#e0f2fe;color:#0C74C5;">
                        <i class="fa fa-folder-closed"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="doc-stat-card" style="border-left-color:#02c9b8;">
                    <div>
                        <div class="doc-stat-num"><?= (int)($doc_stats['lab_count'] ?? 0) ?></div>
                        <div class="doc-stat-lbl">Lab Tests</div>
                    </div>
                    <div class="doc-stat-icon" style="background:#ccfbf1;color:#0f766e;">
                        <i class="fa fa-flask-vial"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="doc-stat-card" style="border-left-color:#16a34a;">
                    <div>
                        <div class="doc-stat-num"><?= (int)($doc_stats['rx_count'] ?? 0) ?></div>
                        <div class="doc-stat-lbl">Prescriptions</div>
                    </div>
                    <div class="doc-stat-icon" style="background:#dcfce7;color:#15803d;">
                        <i class="fa fa-file-prescription"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="doc-stat-card" style="border-left-color:#8b5cf6;">
                    <div>
                        <div class="doc-stat-num"><?= (int)($doc_stats['scan_count'] ?? 0) ?></div>
                        <div class="doc-stat-lbl">Scans &amp; Imaging</div>
                    </div>
                    <div class="doc-stat-icon" style="background:#ede9fe;color:#6d28d9;">
                        <i class="fa fa-x-ray"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="filter-card">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               value="<?= htmlspecialchars($search_query) ?>" 
                               placeholder="Search investigation, patient, mobile...">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <select name="patient_id" class="form-select form-select-sm" style="border-radius:8px;">
                        <option value="">All Linked Patients (<?= count($doctor_patients) ?>)</option>
                        <?php foreach ($doctor_patients as $dp): ?>
                            <option value="<?= (int)$dp['id'] ?>" <?= $patient_id_filter === (int)$dp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim($dp['name'] . ' ' . ($dp['last_name'] ?? ''))) ?> (<?= htmlspecialchars($dp['mobile']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <select name="type" class="form-select form-select-sm" style="border-radius:8px;">
                        <option value="">All ABDM Document Types</option>
                        <option value="Lab" <?= $type_filter === 'Lab' ? 'selected' : '' ?>>Diagnostic Report (Lab)</option>
                        <option value="Prescription" <?= $type_filter === 'Prescription' ? 'selected' : '' ?>>Prescription Document</option>
                        <option value="Scan" <?= $type_filter === 'Scan' ? 'selected' : '' ?>>Radiology / Scans (X-Ray, MRI, CT)</option>
                        <option value="Discharge" <?= $type_filter === 'Discharge' ? 'selected' : '' ?>>Discharge Summary</option>
                        <option value="Other" <?= $type_filter === 'Other' ? 'selected' : '' ?>>Clinical Notes &amp; Other</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold" style="background:#0C74C5;border-color:#0C74C5;border-radius:8px;">
                        <i class="fa fa-filter me-1"></i> Filter
                    </button>
                    <?php if ($search_query !== '' || $patient_id_filter > 0 || $type_filter !== ''): ?>
                        <a href="patient-documents.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;" title="Reset Filters">
                            <i class="fa fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Main Documents Content Card -->
        <div class="docs-main-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                <div class="fw-bold" style="font-size:.92rem;color:var(--gray-800);">
                    <i class="fa fa-clipboard-check text-primary me-2"></i> Linked Medical Records &amp; Diagnostic Files
                    <span class="badge bg-light text-dark border ms-2" style="font-size:.75rem;"><?= count($documents) ?> Records</span>
                </div>
                <?php if ($patient_id_filter > 0): ?>
                    <span class="badge bg-primary-subtle text-primary fw-bold" style="font-size:.74rem;">Filtered by Patient #<?= $patient_id_filter ?></span>
                <?php endif; ?>
            </div>

            <!-- 1. Desktop Table View (>= 768px) -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="font-size:.75rem;font-weight:700;color:#6b7280;padding:12px 16px;">Investigation / Document</th>
                            <th style="font-size:.75rem;font-weight:700;color:#6b7280;">ABDM Category</th>
                            <th style="font-size:.75rem;font-weight:700;color:#6b7280;">Patient Details</th>
                            <th style="font-size:.75rem;font-weight:700;color:#6b7280;">Recorded Date</th>
                            <th style="font-size:.75rem;font-weight:700;color:#6b7280;text-align:right;padding-right:16px;">Clinical Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <div class="my-3">
                                        <i class="fa fa-folder-open text-secondary fa-3x mb-2 d-block opacity-50"></i>
                                        <div class="fw-bold" style="font-size:.92rem;">No diagnostic reports or medical documents found.</div>
                                        <div class="text-muted" style="font-size:.78rem;margin-top:4px;">Upload an investigation report or adjust your search filter criteria.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): 
                                $pname = trim($doc['patient_name'] . ' ' . ($doc['patient_last_name'] ?? ''));
                                $doc_ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                $is_pdf = $doc_ext === 'pdf';
                                $is_img = in_array($doc_ext, ['jpg','jpeg','png','webp','gif']);
                                $type_str = $doc['document_type'] ?: 'Diagnostic Report (Lab)';
                                
                                $cat_class = 'cat-other';
                                if (stripos($type_str, 'Lab') !== false) $cat_class = 'cat-lab';
                                elseif (stripos($type_str, 'Prescription') !== false) $cat_class = 'cat-rx';
                                elseif (stripos($type_str, 'Scan') !== false || stripos($type_str, 'Ray') !== false || stripos($type_str, 'MRI') !== false || stripos($type_str, 'Radiology') !== false) $cat_class = 'cat-scan';
                                elseif (stripos($type_str, 'Discharge') !== false) $cat_class = 'cat-discharge';
                                
                                $file_url = BASE_URL . ltrim($doc['file_path'], '/');
                                $s_abha = !empty($doc['abha_number']) ? $doc['abha_number'] : (!empty($doc['abha_id']) ? $doc['abha_id'] : '');
                            ?>
                                <tr>
                                    <td style="padding:12px 16px;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="file-icon-box" style="background:<?= $is_pdf ? '#fee2e2' : ($is_img ? '#dcfce7' : '#e0f2fe') ?>;color:<?= $is_pdf ? '#dc2626' : ($is_img ? '#16a34a' : '#0284c7') ?>;">
                                                <i class="fa <?= $is_pdf ? 'fa-file-pdf' : ($is_img ? 'fa-file-image' : 'fa-file-lines') ?>"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight:700;font-size:.88rem;color:#1f2937;">
                                                    <a href="<?= $file_url ?>" target="_blank" class="text-dark text-decoration-none">
                                                        <?= htmlspecialchars($doc['document_name']) ?>
                                                    </a>
                                                </div>
                                                <?php if (!empty($doc['description'])): ?>
                                                    <div style="font-size:.74rem;color:#6b7280;max-width:320px;">
                                                        <?= htmlspecialchars(mb_strimwidth($doc['description'], 0, 75, '...')) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="cat-badge <?= $cat_class ?>">
                                            <i class="fa <?= stripos($type_str, 'Lab') !== false ? 'fa-flask' : (stripos($type_str, 'Prescription') !== false ? 'fa-file-medical' : (stripos($type_str, 'Scan') !== false ? 'fa-x-ray' : 'fa-clipboard-list')) ?>"></i>
                                            <?= htmlspecialchars($type_str) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;font-size:.85rem;">
                                            <a href="patient-profile.php?id=<?= (int)$doc['patient_id'] ?>" class="text-primary text-decoration-none">
                                                <?= htmlspecialchars($pname) ?>
                                            </a>
                                        </div>
                                        <div style="font-size:.74rem;color:#6b7280;">
                                            <span><i class="fa fa-phone me-1" style="font-size:.68rem;"></i><?= htmlspecialchars($doc['patient_phone']) ?></span>
                                            <?= $doc['patient_age'] ? ' &bull; ' . $doc['patient_age'] . ' yrs' : '' ?>
                                        </div>
                                        <?php if ($s_abha): ?>
                                            <div class="mt-1">
                                                <span class="abha-pill">
                                                    <i class="fa fa-id-card"></i> ABHA: <?= Abha::formatNumber($s_abha) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.8rem;color:#6b7280;">
                                        <div><i class="fa fa-calendar-day me-1" style="font-size:.72rem;"></i><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></div>
                                        <div style="font-size:.7rem;color:#9ca3af;"><?= date('h:i A', strtotime($doc['uploaded_at'])) ?></div>
                                    </td>
                                    <td style="text-align:right;padding-right:16px;">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($is_img): ?>
                                                <button type="button" class="btn btn-outline-primary" onclick="previewImage('<?= $file_url ?>', '<?= htmlspecialchars(addslashes($doc['document_name'])) ?>')" title="Quick View">
                                                    <i class="fa fa-eye"></i> View
                                                </button>
                                            <?php else: ?>
                                                <a href="<?= $file_url ?>" target="_blank" class="btn btn-outline-primary" title="Open Document">
                                                    <i class="fa fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= $file_url ?>" download class="btn btn-outline-secondary" title="Download">
                                                <i class="fa fa-download"></i>
                                            </a>
                                            <a href="patient-documents.php?delete_id=<?= (int)$doc['id'] ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this document permanently?')" 
                                               title="Delete Record">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. Mobile Responsive Card View (< 768px) -->
            <div class="d-block d-md-none p-3">
                <?php if (empty($documents)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fa fa-folder-open text-secondary fa-3x mb-2 d-block opacity-50"></i>
                        <div class="fw-bold" style="font-size:.9rem;">No diagnostic documents found.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): 
                        $pname = trim($doc['patient_name'] . ' ' . ($doc['patient_last_name'] ?? ''));
                        $doc_ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                        $is_pdf = $doc_ext === 'pdf';
                        $is_img = in_array($doc_ext, ['jpg','jpeg','png','webp','gif']);
                        $type_str = $doc['document_type'] ?: 'Diagnostic Report (Lab)';
                        
                        $cat_class = 'cat-other';
                        if (stripos($type_str, 'Lab') !== false) $cat_class = 'cat-lab';
                        elseif (stripos($type_str, 'Prescription') !== false) $cat_class = 'cat-rx';
                        elseif (stripos($type_str, 'Scan') !== false || stripos($type_str, 'Ray') !== false || stripos($type_str, 'MRI') !== false) $cat_class = 'cat-scan';
                        elseif (stripos($type_str, 'Discharge') !== false) $cat_class = 'cat-discharge';
                        
                        $file_url = BASE_URL . ltrim($doc['file_path'], '/');
                        $s_abha = !empty($doc['abha_number']) ? $doc['abha_number'] : (!empty($doc['abha_id']) ? $doc['abha_id'] : '');
                    ?>
                        <div class="mobile-doc-card">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <span class="cat-badge <?= $cat_class ?>" style="font-size:.68rem;">
                                    <?= htmlspecialchars($type_str) ?>
                                </span>
                                <span style="font-size:.7rem;color:#9ca3af;">
                                    <i class="fa fa-clock me-1"></i><?= date('d M Y', strtotime($doc['uploaded_at'])) ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="file-icon-box" style="width:34px;height:34px;font-size:1rem;background:<?= $is_pdf ? '#fee2e2' : ($is_img ? '#dcfce7' : '#e0f2fe') ?>;color:<?= $is_pdf ? '#dc2626' : ($is_img ? '#16a34a' : '#0284c7') ?>;">
                                    <i class="fa <?= $is_pdf ? 'fa-file-pdf' : ($is_img ? 'fa-file-image' : 'fa-file-lines') ?>"></i>
                                </div>
                                <div style="font-weight:700;font-size:.88rem;color:#1f2937;">
                                    <?= htmlspecialchars($doc['document_name']) ?>
                                </div>
                            </div>

                            <?php if (!empty($doc['description'])): ?>
                                <p class="text-muted mb-2" style="font-size:.74rem;">
                                    <?= htmlspecialchars($doc['description']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="p-2 rounded bg-light mb-3" style="font-size:.75rem;">
                                <div class="fw-bold text-dark mb-1">
                                    <i class="fa fa-user me-1 text-primary"></i> <?= htmlspecialchars($pname) ?> 
                                    <span class="text-muted fw-normal">(<?= htmlspecialchars($doc['patient_phone']) ?>)</span>
                                </div>
                                <?php if ($s_abha): ?>
                                    <div>
                                        <span class="abha-pill">
                                            <i class="fa fa-id-card"></i> ABHA: <?= Abha::formatNumber($s_abha) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <?php if ($is_img): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" onclick="previewImage('<?= $file_url ?>', '<?= htmlspecialchars(addslashes($doc['document_name'])) ?>')">
                                        <i class="fa fa-eye me-1"></i> View
                                    </button>
                                <?php else: ?>
                                    <a href="<?= $file_url ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="fa fa-eye me-1"></i> View
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $file_url ?>" download class="btn btn-sm btn-outline-secondary px-3">
                                    <i class="fa fa-download"></i>
                                </a>
                                <a href="patient-documents.php?delete_id=<?= (int)$doc['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger px-3" 
                                   onclick="return confirm('Are you sure you want to delete this document?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABDM M3 Upload Document Modal -->
        <div class="modal fade" id="uploadDocModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow" style="border-radius:16px;overflow:hidden;">
                    <div class="modal-header modal-header-themed p-3 px-4">
                        <div>
                            <h6 class="modal-title fw-bold text-white mb-0">
                                <i class="fa fa-cloud-arrow-up me-2"></i> Upload Diagnostic / Lab Report
                            </h6>
                            <div style="font-size:.72rem;color:rgba(255,255,255,0.85);">ABDM Milestone 3 Health Information Provider (HIP)</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="modal-body p-4">
                            <!-- Select Patient -->
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size:.82rem;">Select Linked Patient <span class="text-danger">*</span></label>
                                <select name="patient_id" class="form-select form-select-sm" required style="border-radius:8px;">
                                    <option value="">-- Choose Patient --</option>
                                    <?php foreach ($doctor_patients as $dp): ?>
                                        <option value="<?= (int)$dp['id'] ?>" <?= $patient_id_filter === (int)$dp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim($dp['name'] . ' ' . ($dp['last_name'] ?? ''))) ?> (<?= htmlspecialchars($dp['mobile']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Document Title -->
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size:.82rem;">Investigation / Document Title <span class="text-danger">*</span></label>
                                <input type="text" name="document_name" class="form-control form-control-sm" placeholder="e.g. Complete Blood Count (CBC) with ESR" required style="border-radius:8px;">
                            </div>

                            <!-- ABDM Standard Category -->
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size:.82rem;">ABDM Standard HI-Type Category <span class="text-danger">*</span></label>
                                <select name="document_type" class="form-select form-select-sm" required style="border-radius:8px;">
                                    <option value="Diagnostic Report (Lab)">Diagnostic Report (Lab Investigation)</option>
                                    <option value="Prescription Document">Prescription Document</option>
                                    <option value="Diagnostic Report (Radiology / Imaging)">Diagnostic Report (Radiology / Imaging - X-Ray, CT, MRI)</option>
                                    <option value="Discharge Summary">Discharge Summary</option>
                                    <option value="Clinical Examination Note">Clinical Examination Note</option>
                                    <option value="Other Medical Record">Other Medical Record</option>
                                </select>
                            </div>

                            <!-- File Upload Area -->
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size:.82rem;">Attach File <span class="text-danger">*</span></label>
                                <input type="file" name="document_file" id="docFileInput" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required style="border-radius:8px;">
                                <div class="form-text" style="font-size:.72rem;">Supported formats: PDF, JPG, PNG, WEBP, DOCX (Max size: 15MB)</div>
                            </div>

                            <!-- Doctor Findings / Clinical Notes -->
                            <div class="mb-2">
                                <label class="form-label fw-bold" style="font-size:.82rem;">Clinical Findings / Doctor Impression (Optional)</label>
                                <textarea name="description" rows="2" class="form-control form-control-sm" placeholder="Summary of findings, normal/abnormal test indicators..." style="border-radius:8px;"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light p-3 px-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                            <button type="submit" name="upload_document" class="btn btn-sm btn-primary px-4 fw-bold" style="background:#0C74C5;border-color:#0C74C5;border-radius:8px;">
                                <i class="fa fa-cloud-arrow-up me-1"></i> Upload &amp; Link Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Image Preview Lightbox Modal -->
        <div class="modal fade" id="previewImageModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow" style="border-radius:14px;overflow:hidden;">
                    <div class="modal-header py-2 px-3 bg-dark text-white">
                        <h6 class="modal-title" id="previewTitle" style="font-size:.88rem;font-weight:700;">Document Preview</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center p-2 bg-light">
                        <img id="previewImgSrc" src="" alt="Report Preview" style="max-height:75vh;max-width:100%;object-fit:contain;border-radius:8px;">
                    </div>
                    <div class="modal-footer py-2 px-3 bg-white justify-content-between">
                        <a id="previewDownloadLink" href="" download class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-download me-1"></i> Download Original
                        </a>
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Bootstrap Bundle JS -->
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(url, title) {
            document.getElementById('previewImgSrc').src = url;
            document.getElementById('previewTitle').textContent = title || 'Document Preview';
            document.getElementById('previewDownloadLink').href = url;
            const modal = new bootstrap.Modal(document.getElementById('previewImageModal'));
            modal.show();
        }
    </script>
</body>
</html>