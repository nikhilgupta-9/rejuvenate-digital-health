<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/../lib/Abha.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

// Get recent appointments (last 30 days and upcoming)
$appointments_sql = "
    SELECT 
        a.id as appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        u.id as patient_id,
        u.name as patient_name,
        u.last_name as patient_last_name,
        u.mobile as patient_phone,
        u.gender,
        u.profile_pic,
        " . Abha::selectAliases('aa', 'u') . ",
        TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age,
        p.id as prescription_id,
        p.status as rx_status
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    " . Abha::joinClause('patient', 'u', 'aa') . "
    LEFT JOIN prescriptions p ON p.appointment_id = a.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 30
";

$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param('i', $doctor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Get recent finalised OPD records
$recent_slips_sql = "
    SELECT 
        o.id,
        o.appointment_id,
        o.patient_id,
        o.slip_number,
        o.diagnosis,
        o.generated_at,
        u.name as patient_name,
        u.last_name as patient_last_name,
        u.mobile as patient_phone,
        " . Abha::selectAliases('aa', 'u') . "
    FROM opd_records o
    INNER JOIN users u ON o.patient_id = u.id
    " . Abha::joinClause('patient', 'u', 'aa') . "
    WHERE o.doctor_id = ?
    ORDER BY o.generated_at DESC
    LIMIT 15
";
$recent_slips_stmt = $conn->prepare($recent_slips_sql);
$recent_slips_stmt->bind_param('i', $doctor_id);
$recent_slips_stmt->execute();
$recent_slips_result = $recent_slips_stmt->get_result();

// Get list of all linked patients for quick select dropdown
$all_patients_sql = "
    SELECT DISTINCT u.id, u.name, u.last_name, u.mobile, " . Abha::selectAliases('aa', 'u') . "
    FROM users u
    INNER JOIN doctor_patients dp ON dp.patient_id = u.id
    " . Abha::joinClause('patient', 'u', 'aa') . "
    WHERE dp.doctor_id = ?
    ORDER BY u.name ASC
";
$all_patients_stmt = $conn->prepare($all_patients_sql);
$all_patients_stmt->bind_param('i', $doctor_id);
$all_patients_stmt->execute();
$all_patients = $all_patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate OPD Slip & Prescriptions | REJUVENATE Doctor Portal</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/doctor.css">
    <style>
        .opd-header-card {
            background: linear-gradient(135deg, #0C74C5 0%, #084c82 100%);
            color: #fff;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(12,116,197,.2);
        }
        .opd-stat-box {
            background: rgba(255,255,255,.12);
            border-radius: 10px;
            padding: 12px 16px;
            backdrop-filter: blur(4px);
        }
        .abha-pill {
            display: inline-flex;
            align-items: center;
            background: #e0f2fe;
            color: #0277bd;
            font-size: .72rem;
            border-radius: 20px;
            padding: 2px 8px;
            font-weight: 600;
        }
        .abha-pill.verified {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .pat-avatar-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e5e7eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: #6b7280;
            flex-shrink: 0;
            overflow: hidden;
        }
        .status-badge {
            font-size: .72rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved, .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php
    $sidebar_active = 'opd-slips';
    include __DIR__ . '/inc/sidebar.php';
    ?>

    <main class="doctor-content">
        <!-- Header Banner -->
        <div class="opd-header-card">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <span class="badge mb-2" style="background:#02c9b8;color:#fff;font-weight:700;font-size:.72rem;letter-spacing:.4px;">
                        <i class="fa fa-shield me-1"></i> ABDM M2 COMPLIANT
                    </span>
                    <h4 class="fw-bold mb-1">Generate OPD Consultation Slip & Prescriptions</h4>
                    <p class="mb-0 text-white-50" style="font-size:.85rem;">
                        Author digital prescriptions with clinical diagnoses, vitals, ICD-10 codes, medications, and lab test orders. Prints standard NHA-aligned OPD slips with your Doctor HPR ID and Patient 14-digit ABHA.
                    </p>
                </div>
                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                    <a href="<?= BASE_URL ?>doctor/patient-form.php" class="btn btn-light fw-bold text-primary shadow-sm me-2">
                        <i class="fa fa-pencil-square-o me-1"></i> New Digital Prescription
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Patient Picker & Actions -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-2 text-dark">
                            <i class="fa fa-search me-1" style="color:#0C74C5;"></i> Quick Search Patient for OPD Note
                        </h6>
                        <p class="text-muted" style="font-size:.8rem;">Select any linked patient to author an OPD prescription or review previous slips.</p>
                        
                        <form method="GET" action="patient-form.php" class="d-flex gap-2">
                            <select name="appointment_id" class="form-select form-select-sm" required>
                                <option value="">-- Choose Patient / Recent Appointment --</option>
                                <?php
                                $appointments_result->data_seek(0);
                                while ($p = $appointments_result->fetch_assoc()):
                                    $pname = trim($p['patient_name'] . ' ' . ($p['patient_last_name'] ?? ''));
                                    $pdate = date('d M Y', strtotime($p['appointment_date']));
                                    $abha_tag = !empty($p['abha_number']) ? (' [ABHA: ' . Abha::formatNumber($p['abha_number']) . ']') : '';
                                ?>
                                    <option value="<?= (int)$p['appointment_id'] ?>">
                                        <?= htmlspecialchars($pname) ?> (<?= htmlspecialchars($p['patient_phone']) ?>) — <?= $pdate ?><?= $abha_tag ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm px-3 text-nowrap" style="background:#0C74C5;border-color:#0C74C5;">
                                <i class="fa fa-stethoscope me-1"></i> Open Rx
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-2 text-dark">
                            <i class="fa fa-graduation-cap me-1" style="color:#16a34a;"></i> School Student OPD Consultation
                        </h6>
                        <p class="text-muted" style="font-size:.8rem;">Author health screening notes and pediatric prescriptions for students under the School Health Program.</p>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL ?>doctor/patient-form.php?mode=student" class="btn btn-success btn-sm">
                                <i class="fa fa-user-graduate me-1"></i> Student Digital Rx
                            </a>
                            <a href="<?= BASE_URL ?>doctor/school-students.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-search me-1"></i> Search Student Registry
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Appointments Ready for OPD Slip -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa fa-calendar-check-o me-2" style="color:#0C74C5;"></i>
                    Recent Consultations &amp; Appointments
                </h6>
                <a href="appointments.php" class="btn btn-sm btn-outline-primary" style="font-size:.78rem;">
                    View All Appointments &rarr;
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="font-size:.73rem;">Date &amp; Time</th>
                                <th style="font-size:.73rem;">Patient Details</th>
                                <th style="font-size:.73rem;">ABHA Identity</th>
                                <th style="font-size:.73rem;">Appointment Status</th>
                                <th style="font-size:.73rem;">Rx Status</th>
                                <th style="font-size:.73rem;text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $appointments_result->data_seek(0);
                            if ($appointments_result->num_rows === 0): 
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No recent consultations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($apt = $appointments_result->fetch_assoc()): 
                                    $p_full = trim($apt['patient_name'] . ' ' . ($apt['patient_last_name'] ?? ''));
                                    $st = strtolower($apt['status'] ?: 'pending');
                                    $rx_st = strtolower($apt['rx_status'] ?? '');
                                    $abha_num = !empty($apt['abha_number']) ? $apt['abha_number'] : (!empty($apt['abha_id']) ? $apt['abha_id'] : '');
                                ?>
                                    <tr>
                                        <td style="font-size:.82rem;">
                                            <strong><?= date('d M Y', strtotime($apt['appointment_date'])) ?></strong><br>
                                            <span class="text-muted" style="font-size:.73rem;"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="pat-avatar-sm">
                                                    <?php if (!empty($apt['profile_pic'])): ?>
                                                        <img src="<?= BASE_URL . htmlspecialchars($apt['profile_pic']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                                    <?php else: ?>
                                                        <i class="fa fa-user"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight:600;font-size:.85rem;color:#1f2937;">
                                                        <a href="patient-profile.php?id=<?= (int)$apt['patient_id'] ?>" style="color:inherit;text-decoration:none;">
                                                            <?= htmlspecialchars($p_full) ?>
                                                        </a>
                                                    </div>
                                                    <div style="font-size:.73rem;color:#6b7280;">
                                                        <?= htmlspecialchars($apt['patient_phone']) ?>
                                                        <?= $apt['patient_age'] ? ' &bull; ' . $apt['patient_age'] . ' yrs' : '' ?>
                                                        <?= !empty($apt['gender']) ? ' &bull; ' . htmlspecialchars($apt['gender']) : '' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($abha_num): ?>
                                                <span class="abha-pill <?= !empty($apt['abha_verified']) ? 'verified' : '' ?>">
                                                    <i class="fa fa-id-card me-1"></i> <?= Abha::formatNumber($abha_num) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:.73rem;">Unlinked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $st ?>"><?= ucfirst($st) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($rx_st === 'final'): ?>
                                                <span class="badge bg-success" style="font-size:.72rem;">Finalised</span>
                                            <?php elseif ($rx_st === 'draft'): ?>
                                                <span class="badge bg-warning text-dark" style="font-size:.72rem;">Draft</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size:.72rem;">Not Started</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_URL ?>doctor/patient-form.php?appointment_id=<?= (int)$apt['appointment_id'] ?>" 
                                                   class="btn btn-outline-primary" title="Edit Prescription Note">
                                                    <i class="fa fa-pencil me-1"></i> Prescribe
                                                </a>
                                                <?php if ($rx_st === 'final'): ?>
                                                    <a href="<?= BASE_URL ?>doctor/opd-slip.php?appointment_id=<?= (int)$apt['appointment_id'] ?>" 
                                                       target="_blank" class="btn btn-primary" title="Print/Download OPD Slip PDF">
                                                        <i class="fa fa-print me-1"></i> OPD Slip
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Generated OPD Records -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa fa-file-text-o me-2" style="color:#0C74C5;"></i>
                    Recent Finalised OPD Slips
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="font-size:.73rem;">Slip #</th>
                                <th style="font-size:.73rem;">Patient</th>
                                <th style="font-size:.73rem;">Diagnosis / Clinical Impression</th>
                                <th style="font-size:.73rem;">Generated On</th>
                                <th style="font-size:.73rem;text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_slips_result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No finalised OPD slips generated yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($slip = $recent_slips_result->fetch_assoc()): 
                                    $sp_name = trim($slip['patient_name'] . ' ' . ($slip['patient_last_name'] ?? ''));
                                    $s_abha = !empty($slip['abha_number']) ? $slip['abha_number'] : (!empty($slip['abha_id']) ? $slip['abha_id'] : '');
                                ?>
                                    <tr>
                                        <td>
                                            <strong style="font-size:.82rem;color:#0C74C5;"><?= htmlspecialchars($slip['slip_number']) ?></strong>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;font-size:.84rem;"><?= htmlspecialchars($sp_name) ?></div>
                                            <?php if ($s_abha): ?>
                                                <span class="text-muted" style="font-size:.72rem;">ABHA: <?= Abha::formatNumber($s_abha) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:.82rem;max-width:280px;">
                                            <?= htmlspecialchars(mb_strimwidth($slip['diagnosis'] ?? 'Clinical examination recorded', 0, 80, '...')) ?>
                                        </td>
                                        <td style="font-size:.82rem;color:#6b7280;">
                                            <?= date('d M Y, h:i A', strtotime($slip['generated_at'])) ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="<?= BASE_URL ?>doctor/opd-slip.php?appointment_id=<?= (int)$slip['appointment_id'] ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-download me-1"></i> Print / PDF
                                            </a>
                                            <a href="<?= BASE_URL ?>doctor/patient-form.php?appointment_id=<?= (int)$slip['appointment_id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</body>
</html>