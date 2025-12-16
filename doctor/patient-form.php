<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// Start session and check doctor login
session_start();
if (!isset($_SESSION['doctor_logged_in']) || $_SESSION['doctor_logged_in'] !== true) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Get doctor's complete profile
$doctor_sql = "SELECT * FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();

if ($doctor_result->num_rows === 1) {
    $doctor = $doctor_result->fetch_assoc();
} else {
    die("Doctor not found.");
}

// Get patient information if appointment_id or patient_id is provided
$patient = null;
$appointment = null;

if ($appointment_id > 0) {
    // Get appointment with patient details
    $appointment_sql = "
        SELECT 
            a.*,
            u.id as patient_id,
            u.name as patient_name,
            u.email as patient_email,
            u.mobile as patient_phone,
            u.dob,
            u.gender,
            u.blood_group,
            u.identification_number,
            u.emergency_contact,
            TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age,
            ua.house_no, ua.colony_area, ua.landmark,
            ua.city, ua.state, ua.zip_code
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN user_addresses ua ON u.id = ua.user_id AND ua.is_default = 1
        WHERE a.id = ? AND a.doctor_id = ?
    ";
    
    $appointment_stmt = $conn->prepare($appointment_sql);
    $appointment_stmt->bind_param('ii', $appointment_id, $doctor_id);
    $appointment_stmt->execute();
    $appointment_result = $appointment_stmt->get_result();
    
    if ($appointment_result->num_rows === 1) {
        $appointment = $appointment_result->fetch_assoc();
        $patient = [
            'id' => $appointment['patient_id'],
            'name' => $appointment['patient_name'],
            'email' => $appointment['patient_email'],
            'phone' => $appointment['patient_phone'],
            'dob' => $appointment['dob'],
            'age' => $appointment['patient_age'],
            'gender' => $appointment['gender'],
            'blood_group' => $appointment['blood_group'],
            'address' => implode(', ', array_filter([
                $appointment['house_no'],
                $appointment['colony_area'],
                $appointment['landmark'],
                $appointment['city'],
                $appointment['state'],
                $appointment['zip_code']
            ]))
        ];
    }
} elseif ($patient_id > 0) {
    // Get patient directly
    $patient_sql = "
        SELECT 
            u.*,
            TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as age,
            ua.house_no, ua.colony_area, ua.landmark,
            ua.city, ua.state, ua.zip_code
        FROM users u
        LEFT JOIN user_addresses ua ON u.id = ua.user_id AND ua.is_default = 1
        WHERE u.id = ?
    ";
    
    $patient_stmt = $conn->prepare($patient_sql);
    $patient_stmt->bind_param('i', $patient_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    
    if ($patient_result->num_rows === 1) {
        $patient_data = $patient_result->fetch_assoc();
        $patient = [
            'id' => $patient_data['id'],
            'name' => $patient_data['name'],
            'email' => $patient_data['email'],
            'phone' => $patient_data['mobile'],
            'dob' => $patient_data['dob'],
            'age' => $patient_data['age'],
            'gender' => $patient_data['gender'],
            'blood_group' => $patient_data['blood_group'],
            'address' => implode(', ', array_filter([
                $patient_data['house_no'],
                $patient_data['colony_area'],
                $patient_data['landmark'],
                $patient_data['city'],
                $patient_data['state'],
                $patient_data['zip_code']
            ]))
        ];
    }
}

// Parse doctor degrees
$degrees = explode(',', $doctor['degrees']);
$primary_degree = trim($degrees[0] ?? '');
$secondary_degree = trim($degrees[1] ?? '');

// Current date
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Form - REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .no-print {
                display: none !important;
            }
            .prescription-form {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .form-actions {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .prescription-form {
            background: white;
            width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            border: 1px solid #ddd;
        }
        
        .hospital-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .hospital-name-hindi {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .hospital-name-english {
            font-size: 18px;
            color: #2c5aa0;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .registration-info {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .hospital-address {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .doctors-section {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .doctor-info {
            flex: 1;
            padding: 0 15px;
        }
        
        .doctor-name {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .doctor-degree {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .doctor-specialization {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .doctor-contact {
            font-size: 12px;
            color: #555;
        }
        
        .patient-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }
        
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .patient-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c5aa0;
        }
        
        .patient-date {
            font-size: 14px;
            color: #666;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .patient-field {
            font-size: 14px;
        }
        
        .patient-label {
            font-weight: bold;
            color: #333;
            margin-right: 5px;
        }
        
        .patient-value {
            color: #666;
        }
        
        .prescription-area {
            min-height: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            background: #fff;
        }
        
        .tests-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .test-item {
            font-size: 13px;
            padding: 5px;
            border-bottom: 1px dotted #ddd;
        }
        
        .ayushman-section {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #b8d4f0;
        }
        
        .ayushman-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .ayushman-desc {
            font-size: 14px;
            color: #333;
        }
        
        .footer-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #333;
            font-size: 12px;
            color: #666;
        }
        
        .timing {
            flex: 1;
        }
        
        .disclaimer {
            flex: 1;
            text-align: right;
            font-style: italic;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .doctor-signature {
            margin-top: 50px;
            text-align: right;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            display: inline-block;
            margin-top: 40px;
        }
        
        .signature-label {
            font-size: 14px;
            margin-top: 5px;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.1);
            z-index: -1;
            white-space: nowrap;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="prescription-form">
        <!-- Watermark -->
        <div class="watermark">REJUVENATE DIGITAL HEALTH</div>
        
        <!-- Hospital Header -->
        <div class="hospital-header">
            <div class="hospital-name-hindi">एस. एस. डी. हॉस्पिटल</div>
            <div class="hospital-name-english">S.S.D. SAMARPAN HOSPITAL</div>
            <div class="registration-info">Reg. No.: RMEE19325</div>
            <div class="hospital-address">
                सरकारी अस्पताल वाले पुल के नीचे पुराने घास कांटे के पास, सहारनपुर
            </div>
        </div>
        
        <!-- Doctors Information -->
        <div class="doctors-section">
            <div class="doctor-info">
                <div class="doctor-name">डॉ <?= htmlspecialchars($doctor['name']) ?></div>
                <div class="doctor-degree"><?= htmlspecialchars($primary_degree) ?></div>
                <div class="doctor-specialization">(<?= htmlspecialchars($doctor['specialization']) ?>)</div>
                <div class="doctor-contact">
                    मोबाइल: <?= htmlspecialchars($doctor['phone']) ?><br>
                    ई-मेल: <?= htmlspecialchars($doctor['email']) ?>
                </div>
            </div>
            
            <div class="doctor-info">
                <div class="doctor-name">डॉ आस्था चौहान</div>
                <div class="doctor-degree">बी. डी. एस.</div>
                <div class="doctor-specialization">(Dentist)</div>
                <div class="doctor-contact">
                    मोबाइल: 9876543210<br>
                    ई-मेल: dentist@ssdhospital.com
                </div>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="patient-section">
            <div class="patient-header">
                <div class="patient-title">Patient Information</div>
                <div class="patient-date">Dated: <span id="currentDate"><?= $current_date ?></span></div>
            </div>
            
            <?php if ($patient): ?>
                <div class="patient-details">
                    <div class="patient-field">
                        <span class="patient-label">Name:</span>
                        <span class="patient-value"><?= htmlspecialchars($patient['name']) ?></span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Age/Gender:</span>
                        <span class="patient-value"><?= $patient['age'] ?> Years / <?= $patient['gender'] ?></span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Phone:</span>
                        <span class="patient-value"><?= htmlspecialchars($patient['phone']) ?></span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Blood Group:</span>
                        <span class="patient-value"><?= $patient['blood_group'] ?: 'Not specified' ?></span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Address:</span>
                        <span class="patient-value"><?= htmlspecialchars($patient['address'] ?: 'Not specified') ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="patient-details">
                    <div class="patient-field">
                        <span class="patient-label">Name:</span>
                        <span class="patient-value">___________________________</span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Age/Gender:</span>
                        <span class="patient-value">______ Years / ___________</span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Phone:</span>
                        <span class="patient-value">___________________________</span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Blood Group:</span>
                        <span class="patient-value">___________________________</span>
                    </div>
                    <div class="patient-field">
                        <span class="patient-label">Address:</span>
                        <span class="patient-value">___________________________</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Prescription Area -->
        <div class="prescription-area">
            <div style="font-size: 16px; font-weight: bold; color: #2c5aa0; margin-bottom: 15px;">
                PRESCRIPTION / CLINICAL NOTES
            </div>
            <div style="min-height: 250px; line-height: 1.8; font-size: 14px;">
                <!-- This area can be filled by doctor -->
                <p>Chief Complaints:</p>
                <p>_________________________________________________</p>
                <p>_________________________________________________</p>
                
                <p>History of Present Illness:</p>
                <p>_________________________________________________</p>
                <p>_________________________________________________</p>
                
                <p>Examination Findings:</p>
                <p>_________________________________________________</p>
                <p>_________________________________________________</p>
                
                <p>Diagnosis:</p>
                <p>_________________________________________________</p>
                <p>_________________________________________________</p>
                
                <p>Treatment Advised:</p>
                <p>_________________________________________________</p>
                <p>_________________________________________________</p>
                
                <p>Advice:</p>
                <p>_________________________________________________</p>
                <p>_________________________________________________</p>
            </div>
        </div>
        
        <!-- Tests Section -->
        <div class="tests-section">
            <div class="test-item">CBC</div>
            <div class="test-item">RBS</div>
            <div class="test-item">BT, CT</div>
            <div class="test-item">L.F.T</div>
            
            <div class="test-item">Sr. Bilirubin</div>
            <div class="test-item">SGPT</div>
            <div class="test-item">SGOT</div>
            <div class="test-item">Alk. Phosphatase</div>
            
            <div class="test-item">K.F.T</div>
            <div class="test-item">Widal</div>
            <div class="test-item">MP</div>
            <div class="test-item">Blood Urea</div>
            
            <div class="test-item">S. Creatinine</div>
            <div class="test-item">S. Albumin</div>
            <div class="test-item">Blood Group</div>
            <div class="test-item">HIV</div>
            
            <div class="test-item">HB sag</div>
            <div class="test-item">HCV</div>
            <div class="test-item">Urine R/M</div>
            <div class="test-item">USG</div>
            
            <div class="test-item">Chest X-ray</div>
            <div class="test-item">Abdomen X-ray</div>
            <div class="test-item">ECG</div>
            <div class="test-item">Echo</div>
        </div>
        
        <!-- Ayushman Bharat Section -->
        <div class="ayushman-section">
            <div class="ayushman-title">आयुष्मान स्वास्थ्य कार्ड से इलाज की सुविधा</div>
            <div class="ayushman-desc">
                Ayushman Bharat Health Card treatment facility available
            </div>
        </div>
        
        <!-- Doctor Signature -->
        <div class="doctor-signature">
            <div class="signature-line"></div>
            <div class="signature-label">
                डॉ <?= htmlspecialchars($doctor['name']) ?><br>
                <?= htmlspecialchars($doctor['specialization']) ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer-section">
            <div class="timing">
                <strong>Timing:</strong><br>
                10.00 A.M. to 2.00 P.M.<br>
                6.00 P.M. to 8.00 P.M.
            </div>
            <div class="disclaimer">
                <strong>Disclaimer:</strong><br>
                NOT FOR MEDICO LEGAL PURPOSES
            </div>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="form-actions no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa fa-print"></i> Print Prescription
        </button>
        <a href="doctor-dashboard.php" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </a>
        <?php if ($patient): ?>
            <a href="add-prescription.php?patient_id=<?= $patient['id'] ?>&appointment_id=<?= $appointment_id ?>" 
               class="btn btn-success">
                <i class="fa fa-file-medical"></i> Add Prescription Details
            </a>
        <?php endif; ?>
        
        <!-- Patient Selector -->
        <div class="mt-3">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-6">
                    <label>Select Patient</label>
                    <select name="patient_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select Patient --</option>
                        <?php
                        // Get all patients for this doctor
                        $all_patients_sql = "
                            SELECT DISTINCT u.id, u.name, u.mobile 
                            FROM users u
                            INNER JOIN appointments a ON u.id = a.user_id
                            WHERE a.doctor_id = ?
                            ORDER BY u.name ASC
                        ";
                        $all_patients_stmt = $conn->prepare($all_patients_sql);
                        $all_patients_stmt->bind_param('i', $doctor_id);
                        $all_patients_stmt->execute();
                        $all_patients_result = $all_patients_stmt->get_result();
                        
                        while ($p = $all_patients_result->fetch_assoc()):
                        ?>
                            <option value="<?= $p['id'] ?>" <?= $patient && $patient['id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?> (<?= $p['mobile'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Or Select Appointment</label>
                    <select name="appointment_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select Appointment --</option>
                        <?php
                        // Get recent appointments
                        $recent_appointments_sql = "
                            SELECT a.id, u.name as patient_name, a.appointment_date, a.appointment_time
                            FROM appointments a
                            INNER JOIN users u ON a.user_id = u.id
                            WHERE a.doctor_id = ?
                            ORDER BY a.appointment_date DESC
                            LIMIT 20
                        ";
                        $recent_appointments_stmt = $conn->prepare($recent_appointments_sql);
                        $recent_appointments_stmt->bind_param('i', $doctor_id);
                        $recent_appointments_stmt->execute();
                        $recent_appointments_result = $recent_appointments_stmt->get_result();
                        
                        while ($apt = $recent_appointments_result->fetch_assoc()):
                        ?>
                            <option value="<?= $apt['id'] ?>" <?= $appointment_id == $apt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($apt['patient_name']) ?> - 
                                <?= date('d/m/Y', strtotime($apt['appointment_date'])) ?> 
                                <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Update date on page load
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
            const formattedDate = now.toLocaleDateString('en-IN', options);
            document.getElementById('currentDate').textContent = formattedDate;
        });
        
        // Print optimization
        window.onbeforeprint = function() {
            // Add any pre-print modifications here
        };
        
        window.onafterprint = function() {
            // Add any post-print actions here
        };
        
        // Auto-fill form if patient is selected
        function autoFillPatientDetails(patientId) {
            // You can implement AJAX call here to get patient details
            // For now, it will reload the page with patient_id parameter
            if (patientId) {
                window.location.href = 'patient-form.php?patient_id=' + patientId;
            }
        }
    </script>
</body>
</html>