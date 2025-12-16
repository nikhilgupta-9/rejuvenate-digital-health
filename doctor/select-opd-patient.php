<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

session_start();
if (!isset($_SESSION['doctor_logged_in'])) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];

// Get recent appointments
$appointments_sql = "
    SELECT 
        a.id as appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        u.id as patient_id,
        u.name as patient_name,
        u.mobile as patient_phone,
        u.gender,
        TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.doctor_id = ?
    AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 20
";

$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param('i', $doctor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Patient for OPD Slip</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .container { max-width: 1000px; }
        .card { border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .btn-generate { background: #28a745; color: white; }
        .btn-generate:hover { background: #218838; }
        .appointment-card { border-left: 4px solid #02c9b8; }
        .status-badge { font-size: 11px; padding: 3px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa fa-file-medical"></i> Generate OPD Slip</h4>
            </div>
            <div class="card-body">
                <!-- Quick Generate Form -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5>Quick Generate</h5>
                                <form method="GET" action="opd-slip.php" target="_blank">
                                    <div class="mb-3">
                                        <label class="form-label">Select Patient</label>
                                        <select name="patient_id" class="form-select" required>
                                            <option value="">-- Choose Patient --</option>
                                            <?php
                                            $patients_sql = "
                                                SELECT DISTINCT u.id, u.name, u.mobile 
                                                FROM users u
                                                INNER JOIN appointments a ON u.id = a.user_id
                                                WHERE a.doctor_id = ?
                                                ORDER BY u.name ASC
                                            ";
                                            $patients_stmt = $conn->prepare($patients_sql);
                                            $patients_stmt->bind_param('i', $doctor_id);
                                            $patients_stmt->execute();
                                            $patients_result = $patients_stmt->get_result();
                                            
                                            while ($p = $patients_result->fetch_assoc()):
                                            ?>
                                                <option value="<?= $p['id'] ?>">
                                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['mobile'] ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-generate">
                                        <i class="fa fa-print"></i> Generate OPD Slip
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5>Recent OPD Slips</h5>
                                <?php
                                $recent_slips_sql = "
                                    SELECT * FROM opd_records 
                                    WHERE doctor_id = ? 
                                    ORDER BY generated_at DESC 
                                    LIMIT 5
                                ";
                                $recent_slips_stmt = $conn->prepare($recent_slips_sql);
                                $recent_slips_stmt->bind_param('i', $doctor_id);
                                $recent_slips_stmt->execute();
                                $recent_slips_result = $recent_slips_stmt->get_result();
                                
                                if ($recent_slips_result->num_rows > 0):
                                    while ($slip = $recent_slips_result->fetch_assoc()):
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                        <div>
                                            <small>Slip #<?= $slip['slip_number'] ?></small><br>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($slip['generated_at'])) ?></small>
                                        </div>
                                        <a href="opd-slip.php?patient_id=<?= $slip['patient_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-redo"></i> Regenerate
                                        </a>
                                    </div>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <p class="text-muted">No OPD slips generated yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Appointments -->
                <h5 class="mb-3">Recent Appointments</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Age/Gender</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments_result->num_rows > 0): ?>
                                <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                                <tr class="appointment-card">
                                    <td>
                                        <?= date('d/m/Y', strtotime($apt['appointment_date'])) ?><br>
                                        <small><?= date('h:i A', strtotime($apt['appointment_time'])) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($apt['patient_name']) ?></strong>
                                    </td>
                                    <td><?= $apt['patient_phone'] ?></td>
                                    <td><?= $apt['patient_age'] ?>y / <?= $apt['gender'] ?></td>
                                    <td>
                                        <span class="status-badge badge bg-<?= 
                                            $apt['status'] == 'Completed' ? 'success' : 
                                            ($apt['status'] == 'Confirmed' ? 'info' : 'warning')
                                        ?>">
                                            <?= $apt['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="opd-slip.php?appointment_id=<?= $apt['appointment_id'] ?>" 
                                           target="_blank" class="btn btn-sm btn-generate">
                                            <i class="fa fa-file-medical"></i> OPD Slip
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No recent appointments found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-center">
                <a href="doctor-dashboard.php" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>