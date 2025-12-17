<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// Start session and check doctor login
// session_start();
if (!isset($_SESSION['doctor_logged_in']) || $_SESSION['doctor_logged_in'] !== true) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$doctor_id = $_SESSION['doctor_id'];

// Get appointment details with patient information
if ($appointment_id > 0) {
    $appointment_sql = "
        SELECT 
            a.*,
            u.name as patient_name,
            u.mobile as patient_phone,
            u.dob,
            u.gender,
            TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as patient_age
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.doctor_id = ?
    ";
    
    $appointment_stmt = $conn->prepare($appointment_sql);
    $appointment_stmt->bind_param('ii', $appointment_id, $doctor_id);
    $appointment_stmt->execute();
    $appointment_result = $appointment_stmt->get_result();
    
    if ($appointment_result->num_rows === 1) {
        $appointment = $appointment_result->fetch_assoc();
        $patient = [
            'name' => $appointment['patient_name'],
            'phone' => $appointment['patient_phone'],
            'age' => $appointment['patient_age'],
            'gender' => $appointment['gender']
        ];
        $appointment_date = date('d/m/Y', strtotime($appointment['appointment_date']));
        $appointment_time = date('h:i A', strtotime($appointment['appointment_time']));
    } else {
        die("Appointment not found.");
    }
}

// Current date
$current_date = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>एस. एस. डी. हॉस्पिटल - प्रिस्क्रिप्शन फॉर्म</title>
    <style>
        /* A4 Size Settings */
        @page {
            size: A4;
            margin: 0;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .prescription-form {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0.8cm !important;
                width: 100% !important;
                min-height: 100vh !important;
            }
        }
        
        body {
            font-family: 'Times New Roman', serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .prescription-form {
            background: white;
            width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto;
            padding: 0.8cm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            border: 1px solid #ddd;
        }
        
        /* Header Section - Blue Ink Style */
        .hospital-header {
            border-bottom: 2px solid #0066cc;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .hospital-name-hindi {
            font-size: 24px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }
        
        .hospital-name-english {
            font-size: 16px;
            color: #0066cc;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .hospital-address {
            font-size: 12px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .registration-info {
            position: absolute;
            top: 0.8cm;
            right: 0.8cm;
            font-size: 10px;
            color: #666;
        }
        
        /* Doctors Section */
        .doctors-section {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #0066cc;
            border-radius: 5px;
        }
        
        .doctor-info {
            flex: 1;
            padding: 0 15px;
            border-right: 1px dashed #0066cc;
        }
        
        .doctor-info:last-child {
            border-right: none;
        }
        
        .doctor-name {
            font-size: 14px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 3px;
        }
        
        .doctor-qualification {
            font-size: 12px;
            color: #333;
            margin-bottom: 3px;
        }
        
        .doctor-specialization {
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
            font-style: italic;
        }
        
        .doctor-contact {
            font-size: 10px;
            color: #555;
        }
        
        /* Date Section */
        .date-section {
            text-align: right;
            margin: 10px 0;
            font-size: 12px;
            color: #333;
        }
        
        /* Main Content Area */
        .main-content {
            display: flex;
            margin: 15px 0;
            min-height: 350px;
        }
        
        /* Tests Sidebar */
        .tests-sidebar {
            width: 220px;
            border-right: 1px solid #0066cc;
            padding-right: 15px;
            margin-right: 15px;
        }
        
        .tests-title {
            font-size: 13px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 8px;
            text-align: center;
            border-bottom: 1px solid #0066cc;
            padding-bottom: 3px;
        }
        
        .tests-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 11px;
            line-height: 1.6;
        }
        
        .tests-list li {
            margin-bottom: 3px;
            padding-left: 5px;
            border-bottom: 1px dotted #ddd;
            padding-bottom: 2px;
        }
        
        .test-sub-list {
            list-style: none;
            padding-left: 15px;
            margin: 2px 0;
            font-size: 10px;
        }
        
        /* Prescription Area */
        .prescription-area {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 10px;
            min-height: 350px;
        }
        
        .prescription-title {
            font-size: 14px;
            font-weight: bold;
            color: #0066cc;
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .prescription-content {
            font-size: 12px;
            line-height: 1.8;
            min-height: 300px;
        }
        
        /* Ayushman Section */
        .ayushman-section {
            background: #e8f4fd;
            border: 1px solid #b8d4f0;
            border-radius: 5px;
            padding: 8px;
            margin: 15px 0;
            text-align: center;
        }
        
        .ayushman-title {
            font-size: 13px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 2px;
        }
        
        .ayushman-desc {
            font-size: 11px;
            color: #333;
        }
        
        /* Footer */
        .footer-section {
            border-top: 2px solid #0066cc;
            padding-top: 10px;
            margin-top: 20px;
        }
        
        .timing-section {
            text-align: center;
            font-size: 11px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .disclaimer-section {
            text-align: center;
            font-size: 10px;
            color: #666;
            font-style: italic;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
        
        /* Controls */
        .controls-section {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        /* Appointment Selector */
        .appointment-selector {
            margin-top: 20px;
        }
        
        /* Print Optimizations */
        .prescription-form * {
            color: #000 !important;
        }
        
        /* Form Fields for Writing */
        .write-area {
            outline: none;
            border: none;
            width: 100%;
            font-family: 'Times New Roman', serif;
            font-size: 12px;
            line-height: 1.8;
            background: transparent;
        }
        
        .patient-info-line {
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .patient-label {
            font-weight: bold;
            color: #0066cc;
        }
    </style>
</head>
<body>
    <!-- Controls Section -->
    <div class="controls-section no-print">
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i> Print Prescription
        </button>
        <a href="doctor-dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <!-- Prescription Form -->
    <div class="prescription-form">
        <!-- Registration Number -->
        <div class="registration-info">
            Reg. No.: RMEE19325
        </div>
        
        <!-- Hospital Header -->
        <div class="hospital-header">
            <div class="hospital-name-hindi">एस. एस. डी. हॉस्पिटल</div>
            <div class="hospital-name-english">S.S.D. SAMARPAN HOSPITAL</div>
            <div class="hospital-address">
                सरकारी अस्पताल वाले पुल के नीचे पुराने घास कांटे के पास, सहारनपुर
            </div>
        </div>
        
        <!-- Doctors Information -->
        <div class="doctors-section">
            <!-- Left Doctor -->
            <div class="doctor-info">
                <div class="doctor-name">डॉ संजय चौहान</div>
                <div class="doctor-qualification">एम. डी. (जनरल मेडिसिन)</div>
                <div class="doctor-specialization">सामान्य रोग विशेषज्ञ</div>
                <div class="doctor-contact">
                    Mobile: 9319270957<br>
                    Email: ssdchospital.sre@gmail.com
                </div>
            </div>
            
            <!-- Right Doctor -->
            <div class="doctor-info">
                <div class="doctor-name">डॉ आस्था चौहान</div>
                <div class="doctor-qualification">बी. डी. एस.</div>
                <div class="doctor-specialization">Dentist</div>
                <div class="doctor-contact">
                    Mobile: 9876543210<br>
                    Email: dentist@ssdhospital.com
                </div>
            </div>
        </div>
        
        <!-- Date -->
        <div class="date-section">
            Dated: __________
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Tests Sidebar -->
            <div class="tests-sidebar">
                <div class="tests-title">MEDICAL TESTS</div>
                <ul class="tests-list">
                    <li>CBC</li>
                    <li>RBS</li>
                    <li>BT, CT</li>
                    <li>LFT
                        <ul class="test-sub-list">
                            <li>Sr. Bilirubin</li>
                            <li>SGPT</li>
                            <li>SGOT</li>
                            <li>Alk. Phosphatase</li>
                        </ul>
                    </li>
                    <li>KFT</li>
                    <li>Widal</li>
                    <li>MP</li>
                    <li>Blood Urea</li>
                    <li>Serum Creatinine</li>
                    <li>Serum Albumin</li>
                    <li>Blood Group</li>
                    <li>HIV</li>
                    <li>HBsAg</li>
                    <li>HCV</li>
                    <li>Urine (R, M)</li>
                    <li>USG</li>
                    <li>Chest X-ray</li>
                    <li>Abdomen X-ray</li>
                </ul>
            </div>
            
            <!-- Prescription Area -->
            <div class="prescription-area">
                <div class="prescription-title">PRESCRIPTION</div>
                <div class="prescription-content">
                    <?php if (isset($patient)): ?>
                        <!-- Patient Information -->
                        <div class="patient-info-line">
                            <span class="patient-label">Patient Name:</span> <?= htmlspecialchars($patient['name']) ?>
                        </div>
                        <div class="patient-info-line">
                            <span class="patient-label">Age/Sex:</span> <?= $patient['age'] ?> Years / <?= $patient['gender'] ?>
                        </div>
                        <div class="patient-info-line">
                            <span class="patient-label">Phone:</span> <?= htmlspecialchars($patient['phone']) ?>
                        </div>
                        <div class="patient-info-line">
                            <span class="patient-label">Appointment:</span> <?= $appointment_date ?> at <?= $appointment_time ?>
                        </div>
                    <?php else: ?>
                        <!-- Blank Patient Information -->
                        <div class="patient-info-line">
                            <span class="patient-label">Patient Name:</span> ______________________________
                        </div>
                        <div class="patient-info-line">
                            <span class="patient-label">Age/Sex:</span> ______ Years / ______________
                        </div>
                        <div class="patient-info-line">
                            <span class="patient-label">Phone:</span> ______________________________
                        </div>
                        <div class="patient-info-line">
                            <span class="patient-label">Appointment:</span> __/__/____ at __:__ __
                        </div>
                    <?php endif; ?>
                    
                    <hr style="border-top: 1px dashed #ddd; margin: 10px 0;">
                    
                    <!-- Prescription Writing Area -->
                    <div contenteditable="true" class="write-area" style="min-height: 200px;">
                        <p><strong>Chief Complaints:</strong></p>
                        <p>________________________________________________________</p>
                        <p>________________________________________________________</p>
                        
                        <p><strong>Examination Findings:</strong></p>
                        <p>________________________________________________________</p>
                        <p>________________________________________________________</p>
                        
                        <p><strong>Diagnosis:</strong></p>
                        <p>________________________________________________________</p>
                        <p>________________________________________________________</p>
                        
                        <p><strong>Treatment:</strong></p>
                        <p>________________________________________________________</p>
                        <p>________________________________________________________</p>
                        
                        <p><strong>Advice:</strong></p>
                        <p>________________________________________________________</p>
                        <p>________________________________________________________</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ayushman Bharat Section -->
        <div class="ayushman-section">
            <div class="ayushman-title">आयुष्मान स्वास्थ्य कार्ड से इलाज की सुविधा</div>
            <div class="ayushman-desc">Ayushman Bharat Health Card treatment facility available</div>
        </div>
        
        <!-- Footer -->
        <div class="footer-section">
            <div class="timing-section">
                <strong>Clinic Timings:</strong><br>
                10.00 A.M. to 2.00 P.M. &nbsp; | &nbsp; 6.00 P.M. to 8.00 P.M.
            </div>
            <div class="disclaimer-section">
                NOT FOR MEDICO LEGAL PURPOSES
            </div>
        </div>
    </div>
    
    <!-- Appointment Selector -->
    <div class="container mt-3 appointment-selector no-print">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-calendar-check"></i> Select Appointment
            </div>
            <div class="card-body">
                <?php
                // Get today's appointments for this doctor
                $today = date('Y-m-d');
                $today_appointments_sql = "
                    SELECT a.id, u.name as patient_name, a.appointment_time 
                    FROM appointments a
                    INNER JOIN users u ON a.user_id = u.id
                    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status = 'confirmed'
                    ORDER BY a.appointment_time ASC
                ";
                
                $today_stmt = $conn->prepare($today_appointments_sql);
                $today_stmt->bind_param('is', $doctor_id, $today);
                $today_stmt->execute();
                $today_result = $today_stmt->get_result();
                
                if ($today_result->num_rows > 0):
                ?>
                    <div class="mb-3">
                        <h6>Today's Appointments (<?= date('d/m/Y') ?>)</h6>
                        <div class="list-group">
                            <?php while ($appt = $today_result->fetch_assoc()): ?>
                                <a href="?appointment_id=<?= $appt['id'] ?>" 
                                   class="list-group-item list-group-item-action <?= $appointment_id == $appt['id'] ? 'active' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($appt['patient_name']) ?></h6>
                                        <small><?= date('h:i A', strtotime($appt['appointment_time'])) ?></small>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php
                // Get upcoming appointments
                $upcoming_sql = "
                    SELECT a.id, u.name as patient_name, a.appointment_date, a.appointment_time 
                    FROM appointments a
                    INNER JOIN users u ON a.user_id = u.id
                    WHERE a.doctor_id = ? AND a.appointment_date >= ? AND a.status = 'confirmed'
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC
                    LIMIT 10
                ";
                
                $upcoming_stmt = $conn->prepare($upcoming_sql);
                $upcoming_stmt->bind_param('is', $doctor_id, $today);
                $upcoming_stmt->execute();
                $upcoming_result = $upcoming_stmt->get_result();
                
                if ($upcoming_result->num_rows > 0):
                ?>
                    <div>
                        <h6>Upcoming Appointments</h6>
                        <div class="list-group">
                            <?php while ($appt = $upcoming_result->fetch_assoc()): ?>
                                <a href="?appointment_id=<?= $appt['id'] ?>" 
                                   class="list-group-item list-group-item-action <?= $appointment_id == $appt['id'] ? 'active' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($appt['patient_name']) ?></h6>
                                        <small><?= date('d/m/Y', strtotime($appt['appointment_date'])) ?></small>
                                    </div>
                                    <small class="text-muted"><?= date('h:i A', strtotime($appt['appointment_time'])) ?></small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($today_result->num_rows == 0 && $upcoming_result->num_rows == 0): ?>
                    <div class="alert alert-info">
                        No appointments found. Please select a patient from the dashboard.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap for controls -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <script>
        // Auto-fill current date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const formattedDate = today.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            
            // Set date in the date field
            const dateFields = document.querySelectorAll('.date-section');
            dateFields.forEach(field => {
                field.innerHTML = field.innerHTML.replace('__________', formattedDate);
            });
            
            // Auto-focus on prescription area if patient is selected
            <?php if (isset($patient)): ?>
                const writeArea = document.querySelector('.write-area');
                if (writeArea) {
                    // Move cursor to the beginning of the editable area
                    writeArea.focus();
                    const range = document.createRange();
                    range.selectNodeContents(writeArea);
                    range.collapse(true);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            <?php endif; ?>
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+P for print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
        
        // Print optimization
        window.onbeforeprint = function() {
            // Add any pre-print modifications here
            document.querySelectorAll('.write-area').forEach(el => {
                el.style.minHeight = 'auto';
            });
        };
    </script>
</body>
</html>