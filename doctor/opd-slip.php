<?php
// Use TCPDF for better Unicode support (Hindi text)
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');
// OR download TCPDF from: https://github.com/tecnickcom/TCPDF

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

// Parse doctor degrees
$degrees = explode(',', $doctor['degrees']);
$primary_degree = trim($degrees[0] ?? '');

// Get patient information from users table
$patient = null;
$appointment = null;

if ($appointment_id > 0) {
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
            'id' => $appointment['patient_id'],
            'name' => $appointment['patient_name'],
            'email' => $appointment['patient_email'],
            'phone' => $appointment['patient_phone'],
            'dob' => $appointment['dob'],
            'age' => $appointment['patient_age'],
            'gender' => $appointment['gender'],
            'blood_group' => $appointment['blood_group'],
        ];
    }
} elseif ($patient_id > 0) {
    $patient_sql = "
        SELECT 
            u.*,
            TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as age
        FROM users u
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
        ];
    }
}

// If no patient selected, show selection page
if (!$patient) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Select Patient for OPD Slip</title>
        <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
        <style>
            .container { max-width: 600px; margin: 50px auto; }
            .card { padding: 30px; }
            .patient-list { max-height: 400px; overflow-y: auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card shadow">
                <h3 class="mb-4">Generate OPD Slip</h3>
                <div class="patient-list">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Phone</th>
                                <th>Age/Gender</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $patients_sql = "
                                SELECT DISTINCT 
                                    u.id, 
                                    u.name, 
                                    u.mobile,
                                    u.gender,
                                    TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as age
                                FROM users u
                                INNER JOIN appointments a ON u.id = a.user_id
                                WHERE a.doctor_id = ?
                                ORDER BY u.name ASC
                            ";
                            $patients_stmt = $conn->prepare($patients_sql);
                            $patients_stmt->bind_param('i', $doctor_id);
                            $patients_stmt->execute();
                            $patients_result = $patients_stmt->get_result();
                            
                            if ($patients_result->num_rows > 0):
                                while ($p = $patients_result->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= $p['mobile'] ?></td>
                                <td><?= $p['age'] ?>y / <?= $p['gender'] ?></td>
                                <td>
                                    <a href="opd-slip.php?patient_id=<?= $p['id'] ?>" 
                                       target="_blank" class="btn btn-sm btn-primary">
                                        Generate OPD
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    No patients found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="doctor-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Create PDF using TCPDF (better Unicode support)
class OPD_PDF extends TCPDF {
    // Page header
    public function Header() {
        // Hospital name in Hindi
        $this->SetFont('dejavusans', 'B', 20);
        $this->Cell(0, 10, 'एस. एस. डी. हॉस्पिटल', 0, 1, 'C');
        
        // Hospital name in English
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 8, 'S.S.D. SAMARPAN HOSPITAL', 0, 1, 'C');
        
        // Registration number
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Reg. No.: RMEE19325', 0, 1, 'C');
        
        // Hospital address
        $this->SetFont('dejavusans', '', 10);
        $this->Cell(0, 6, 'सरकारी अस्पताल वाले पुल के नीचे पुराने घास कांटे के पास, सहारनपुर', 0, 1, 'C');
        
        // Line separator
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-25);
        
        // Timing information
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 6, 'Timing: 10.00 A.M. to 2.00 P.M. | 6.00 P.M. to 8.00 P.M.', 0, 1, 'L');
        
        // Disclaimer
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Disclaimer: NOT FOR MEDICO LEGAL PURPOSES', 0, 1, 'L');
        
        // Page number
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 6, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Create PDF
$pdf = new OPD_PDF('P', 'mm', 'A4');
$pdf->SetCreator('REJUVENATE Digital Health');
$pdf->SetAuthor('Dr. ' . $doctor['name']);
$pdf->SetTitle('OPD Slip - ' . $patient['name']);
$pdf->SetSubject('Medical Prescription');
$pdf->SetKeywords('OPD, Prescription, Medical');

// Add a page
$pdf->AddPage();

// Set font for English text
$pdf->SetFont('helvetica', '', 10);

// Doctor Information Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(95, 8, 'DOCTOR INFORMATION', 0, 0, 'L');
$pdf->Cell(95, 8, 'DOCTOR INFORMATION', 0, 1, 'L');

// Left doctor (main doctor)
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(95, 6, 'डॉ ' . $doctor['name'], 0, 0, 'L');

// Right doctor (fixed)
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(95, 6, 'डॉ आस्था चौहान', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(95, 6, $primary_degree, 0, 0, 'L');
$pdf->Cell(95, 6, 'B.D.S.', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(95, 6, '(' . $doctor['specialization'] . ')', 0, 0, 'L');
$pdf->Cell(95, 6, '(Dentist)', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(95, 6, 'Mobile: ' . $doctor['phone'], 0, 0, 'L');
$pdf->Cell(95, 6, 'Mobile: 9876543210', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(95, 6, 'Email: ' . $doctor['email'], 0, 0, 'L');
$pdf->Cell(95, 6, 'Email: dentist@ssdhospital.com', 0, 1, 'L');

$pdf->Ln(8);

// Patient Information Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'PATIENT INFORMATION', 0, 1, 'L');
$pdf->SetLineWidth(0.2);
$pdf->Line(10, $pdf->GetY()-2, 200, $pdf->GetY()-2);

// Create patient details table
$pdf->SetFont('helvetica', '', 10);

$patient_data = [
    ['Patient Name:', $patient['name']],
    ['Age / Gender:', $patient['age'] . ' Years / ' . $patient['gender']],
    ['Phone Number:', $patient['phone']],
    ['Blood Group:', $patient['blood_group'] ?: 'Not specified'],
    ['Email:', $patient['email']],
    ['Date:', date('d/m/Y')],
];

foreach ($patient_data as $row) {
    $pdf->Cell(40, 7, $row[0], 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, $row[1], 0, 1);
    $pdf->SetFont('helvetica', '', 10);
}

$pdf->Ln(8);

// Prescription Area
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'PRESCRIPTION / CLINICAL NOTES', 0, 1, 'C');

// Draw prescription box
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, $pdf->GetY(), 190, 100);

// Add prescription fields
$pdf->SetFont('helvetica', '', 10);
$y_start = $pdf->GetY();

$prescription_fields = [
    'Chief Complaints:' => 5,
    'History of Present Illness:' => 10,
    'Examination Findings:' => 10,
    'Diagnosis:' => 10,
    'Treatment Advised:' => 10,
    'Advice:' => 10,
];

foreach ($prescription_fields as $field => $height) {
    $pdf->SetXY(15, $y_start + 5);
    $pdf->Cell(0, 5, $field, 0, 1);
    $pdf->SetXY(15, $pdf->GetY());
    $pdf->Cell(180, $height, '', 'B', 1);
    $y_start += $height + 5;
}

$pdf->SetY($y_start + 10);

// Tests/Investigations Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'TESTS / INVESTIGATIONS RECOMMENDED', 0, 1, 'L');

// Tests list in 4 columns
$tests = [
    'CBC', 'RBS', 'BT, CT', 'L.F.T',
    'Sr. Bilirubin', 'SGPT', 'SGOT', 'Alk. Phosphatase',
    'K.F.T', 'Widal', 'MP', 'Blood Urea',
    'S. Creatinine', 'S. Albumin', 'Blood Group', 'HIV',
    'HB sag', 'HCV', 'Urine R/M', 'USG',
    'Chest X-ray', 'Abdomen X-ray', 'ECG', 'Echo'
];

$pdf->SetFont('helvetica', '', 9);
$col_width = 47.5;
$row_height = 6;
$x = 10;
$y = $pdf->GetY();

foreach ($tests as $index => $test) {
    if ($index > 0 && $index % 4 == 0) {
        $x = 10;
        $y += $row_height;
    }
    
    $pdf->SetXY($x, $y);
    $pdf->Cell($col_width, $row_height, '☐ ' . $test, 0, 0);
    $x += $col_width;
}

$pdf->SetY($y + $row_height + 8);

// Ayushman Bharat Section
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 8, 'आयुष्मान स्वास्थ्य कार्ड से इलाज की सुविधा', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Ayushman Bharat Health Card treatment facility available', 0, 1, 'C');

// Draw box around Ayushman section
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, $pdf->GetY() - 10, 190, 15);

$pdf->Ln(12);

// Doctor Signature Area
$pdf->SetY(240);
$pdf->SetLineWidth(0.5);
$pdf->Line(130, $pdf->GetY(), 200, $pdf->GetY());

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY(130, $pdf->GetY() + 2);
$pdf->Cell(70, 6, 'Dr. ' . $doctor['name'], 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(130);
$pdf->Cell(70, 6, $doctor['specialization'], 0, 1, 'C');

$pdf->SetFont('helvetica', '', 8);
$pdf->SetX(130);
$pdf->Cell(70, 6, 'Date: ' . date('d/m/Y'), 0, 1, 'C');

// Generate unique OPD number
$opd_number = 'OPD' . str_pad($patient['id'], 6, '0', STR_PAD_LEFT) . date('YmdHis');

// Add OPD number at bottom
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetY(-15);
$pdf->Cell(0, 6, 'OPD No: ' . $opd_number . ' | Generated by REJUVENATE Digital Health', 0, 0, 'C');

// Output PDF
$filename = 'OPD_Slip_' . str_replace(' ', '_', $patient['name']) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');

// Save OPD record to database
$save_opd_sql = "
    INSERT INTO opd_slips (
        opd_number,
        doctor_id,
        patient_id,
        appointment_id,
        doctor_name,
        patient_name,
        generated_at,
        ip_address
    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
";

// Create OPD slips table if not exists
$create_table_sql = "
    CREATE TABLE IF NOT EXISTS opd_slips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        opd_number VARCHAR(50) UNIQUE,
        doctor_id INT NOT NULL,
        patient_id INT NOT NULL,
        appointment_id INT NULL,
        doctor_name VARCHAR(255),
        patient_name VARCHAR(255),
        chief_complaints TEXT,
        diagnosis TEXT,
        treatment TEXT,
        tests_recommended TEXT,
        generated_at DATETIME,
        printed_at DATETIME NULL,
        ip_address VARCHAR(45),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (patient_id) REFERENCES users(id)
    )
";

// Execute create table
$conn->query($create_table_sql);

// Save OPD record
try {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $save_stmt = $conn->prepare($save_opd_sql);
    $save_stmt->bind_param(
        'siiisss',
        $opd_number,
        $doctor_id,
        $patient['id'],
        $appointment_id,
        $doctor['name'],
        $patient['name'],
        $ip_address
    );
    $save_stmt->execute();
} catch (Exception $e) {
    // Log error but continue
    error_log("Failed to save OPD record: " . $e->getMessage());
}