<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// session_start();
if (!isset($_SESSION['doctor_logged_in'])) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$patient_id = intval($_GET['id'] ?? 0);

// Get patient full details with address
$patient_sql = "
    SELECT 
        u.*,
        ua.address_type, ua.house_no, ua.colony_area, ua.landmark, 
        ua.city as addr_city, ua.state as addr_state, ua.zip_code as addr_zip
    FROM users u
    LEFT JOIN user_addresses ua ON u.id = ua.user_id AND ua.is_default = 1
    WHERE u.id = ? AND EXISTS (
        SELECT 1 FROM appointments 
        WHERE user_id = u.id AND doctor_id = ?
    )
";

$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param('ii', $patient_id, $doctor_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();

if ($patient_result->num_rows === 0) {
    header("Location: my-patients.php");
    exit();
}

$patient = $patient_result->fetch_assoc();

// Calculate patient age
$dob = new DateTime($patient['dob']);
$now = new DateTime();
$age = $dob->diff($now)->y;

// Get patient appointments history
$appointments_sql = "
    SELECT 
        a.*,
        d.name as doctor_name,
        d.specialization,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
        DATE_FORMAT(a.appointment_date, '%d %M, %Y') as formatted_date,
        DATE_FORMAT(a.created_at, '%d/%m/%Y %h:%i %p') as created_at_formatted
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.user_id = ? AND a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param('ii', $patient_id, $doctor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Get patient documents
$documents_sql = "
    SELECT * FROM patient_documents 
    WHERE patient_id = ? AND doctor_id = ?
    ORDER BY uploaded_at DESC
";

$documents_stmt = $conn->prepare($documents_sql);
$documents_stmt->bind_param('ii', $patient_id, $doctor_id);
$documents_stmt->execute();
$documents_result = $documents_stmt->get_result();

// Get appointment statistics for this patient
$stats_sql = "
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments 
    WHERE user_id = ? AND doctor_id = ?
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('ii', $patient_id, $doctor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Handle file upload
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $file_type = $_FILES['document_file']['type'];
        $file_name = $_FILES['document_file']['name'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_size = $_FILES['document_file']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 10485760) { // 10MB max
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = 'patient_' . $patient_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_dir = '../uploads/patient_documents/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $document_type = $_POST['file_type'] ?? 'Other';
                $description = $conn->real_escape_string($_POST['description'] ?? '');
                
                $doc_sql = "INSERT INTO patient_documents (patient_id, doctor_id, document_name, file_path, file_type,  description) 
                           VALUES (?, ?, ?, ?, ?, ?)";
                $doc_stmt = $conn->prepare($doc_sql);
                $doc_stmt->bind_param('iissss', $patient_id, $doctor_id, $file_name, $upload_path, $file_type, $description);
                
                if ($doc_stmt->execute()) {
                    $success_message = "Document uploaded successfully!";
                    // Refresh documents list
                    $documents_stmt->execute();
                    $documents_result = $documents_stmt->get_result();
                } else {
                    $error_message = "Failed to save document record.";
                }
            } else {
                $error_message = "Failed to upload file.";
            }
        } else {
            $error_message = "Invalid file type or size too large (max 10MB). Only PDF, DOC, DOCX, JPG, PNG allowed.";
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Handle document deletion
if (isset($_GET['delete_document'])) {
    $doc_id = intval($_GET['delete_document']);
    
    // Verify ownership
    $check_sql = "SELECT file_path FROM patient_documents WHERE id = ? AND patient_id = ? AND doctor_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('iii', $doc_id, $patient_id, $doctor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $doc = $check_result->fetch_assoc();
        
        // Delete from database
        $delete_sql = "DELETE FROM patient_documents WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param('i', $doc_id);
        
        if ($delete_stmt->execute()) {
            // Delete physical file
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            $success_message = "Document deleted successfully!";
            // Refresh documents list
            $documents_stmt->execute();
            $documents_result = $documents_stmt->get_result();
        } else {
            $error_message = "Failed to delete document.";
        }
    }
}

// Get doctor details for sidebar
$doctor_sql = "SELECT name, email, profile_image, phone FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor_data = $doctor_result->fetch_assoc();

$doctor_name = $doctor_data['name'] ?? 'Doctor';
$doctor_email = $doctor_data['email'] ?? '';
$doctor_profile_image = !empty($doctor_data['profile_image']) ? $doctor_data['profile_image'] : 'assets/img/dummy.png';
$doctor_phone = $doctor_data['phone'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Patient Details | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .btn-upload {
            background-color: green;
            color: #fff;
            font-size: 12px;
        }
        .btn-upload:hover {
            background-color: darkgreen;
        }
        .btn-delete {
            font-size: 12px;
            background-color: red;
            color: #fff;
        }
        .btn-delete:hover {
            background-color: darkred;
        }
        .sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            height: fit-content;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #02c9b8;
            color: white;
        }
        .userd-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #02c9b8;
        }
        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        .stats-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px;
            margin-bottom: 15px;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .patient-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #02c9b8;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
        }
        .document-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .document-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .document-icon {
            font-size: 40px;
            color: #02c9b8;
        }
        .menu-btn {
            display: none;
            background: #02c9b8;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .sidebar.show { 
                display: block;
                width: 280px;
                height: 100vh;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1000;
                overflow-y: auto;
            }
            .menu-btn { display: block; }
            .table-responsive { font-size: 12px; }
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #02c9b8;
            border-bottom: 3px solid #02c9b8;
        }
    </style>
</head>

<body>
    <?php include("../header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row mb-5">
                <!-- Sidebar -->
                <div class="col-md-3">
                    <div class="sidebar" id="sidebarMenu">
                        <div class="text-center info-content">
                            <img src="<?= $doctor_profile_image ?>" class="userd-image">
                            <h5>Dr. <?= htmlspecialchars($doctor_name) ?></h5>
                            <p><?= htmlspecialchars($doctor_email) ?></p>
                            <p>Phone: <?= htmlspecialchars($doctor_phone) ?></p>
                            <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Profile</a>
                        </div>

                        <a href="<?= $site ?>doctor/doctor-dashboard.php">Dashboard</a>
                        <a href="<?= $site ?>doctor/my-patients.php">My Patients</a>
                        <a href="<?= $site ?>doctor/appointments.php">Appointments</a>
                        <a href="<?= $site ?>doctor/patient-form.pdf">Patient Form</a>
                        <a href="<?= $site ?>doctor/my-contact.php">Contact Us</a>
                        <a href="<?= $site ?>doctor/doctor-about.php">About Us</a>
                        <a href="<?= $site ?>doctor-logout.php">Logout</a>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
                    <!-- Patient Header -->
                    <div class="profile-card shadow mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if (!empty($patient['profile_pic'])): ?>
                                    <img src="<?= $patient['profile_pic'] ?>" class="patient-avatar">
                                <?php else: ?>
                                    <div class="patient-avatar bg-secondary text-white d-flex align-items-center justify-content-center">
                                        <i class="fa fa-user fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h2><?= htmlspecialchars($patient['name']) ?></h2>
                                <p class="text-muted mb-1">
                                    <i class="fa fa-id-card me-2"></i>
                                    Patient ID: P<?= str_pad($patient['id'], 6, '0', STR_PAD_LEFT) ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <i class="fa fa-user me-2"></i>
                                    <?= $patient['gender'] ?> | <?= $age ?> years
                                </p>
                                <?php if ($patient['blood_group']): ?>
                                    <p class="text-muted mb-1">
                                        <i class="fa fa-tint me-2"></i>
                                        Blood Group: <?= $patient['blood_group'] ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-muted">
                                    <i class="fa fa-calendar me-2"></i>
                                    DOB: <?= date('d/m/Y', strtotime($patient['dob'])) ?>
                                </p>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="my-patients.php" class="btn btn-outline-primary">
                                    <i class="fa fa-arrow-left me-1"></i> Back to Patients
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-6">
                            <div class="stats-card text-center">
                                <h6>Total Appointments</h6>
                                <h3 class="text-primary"><?= $stats['total_appointments'] ?? 0 ?></h3>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stats-card text-center">
                                <h6>Pending</h6>
                                <h3 class="text-warning"><?= $stats['pending'] ?? 0 ?></h3>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stats-card text-center">
                                <h6>Confirmed</h6>
                                <h3 class="text-info"><?= $stats['confirmed'] ?? 0 ?></h3>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stats-card text-center">
                                <h6>Completed</h6>
                                <h3 class="text-success"><?= $stats['completed'] ?? 0 ?></h3>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stats-card text-center">
                                <h6>Cancelled</h6>
                                <h3 class="text-danger"><?= $stats['cancelled'] ?? 0 ?></h3>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stats-card text-center">
                                <h6>Documents</h6>
                                <h3 class="text-purple"><?= $documents_result->num_rows ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                                <i class="fa fa-user me-1"></i> Profile
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button">
                                <i class="fa fa-calendar me-1"></i> Appointments (<?= $appointments_result->num_rows ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                                <i class="fa fa-file me-1"></i> Documents (<?= $documents_result->num_rows ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button">
                                <i class="fa fa-upload me-1"></i> Upload Document
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="patientTabsContent">
                        <!-- Profile Tab -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel">
                            <div class="profile-card shadow">
                                <div class="row">
                                    <!-- Personal Information -->
                                    <div class="col-md-6">
                                        <h5 class="mb-3"><i class="fa fa-user-circle me-2"></i> Personal Information</h5>
                                        <div class="info-card">
                                            <div class="d-flex mb-2">
                                                <span class="info-label">Full Name:</span>
                                                <span><?= htmlspecialchars($patient['name']) ?></span>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <span class="info-label">Email:</span>
                                                <span><?= htmlspecialchars($patient['email']) ?></span>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <span class="info-label">Phone:</span>
                                                <span><?= htmlspecialchars($patient['mobile']) ?></span>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <span class="info-label">Date of Birth:</span>
                                                <span><?= date('d/m/Y', strtotime($patient['dob'])) ?></span>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <span class="info-label">Age:</span>
                                                <span><?= $age ?> years</span>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <span class="info-label">Gender:</span>
                                                <span><?= $patient['gender'] ?></span>
                                            </div>
                                            <?php if ($patient['blood_group']): ?>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">Blood Group:</span>
                                                    <span><?= $patient['blood_group'] ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($patient['emergency_contact']): ?>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">Emergency Contact:</span>
                                                    <span><?= htmlspecialchars($patient['emergency_contact']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Address Information -->
                                    <div class="col-md-6">
                                        <h5 class="mb-3"><i class="fa fa-home me-2"></i> Address Information</h5>
                                        <div class="info-card">
                                            <?php if ($patient['house_no']): ?>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">Address Type:</span>
                                                    <span><?= $patient['address_type'] ?? 'Home' ?></span>
                                                </div>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">House No:</span>
                                                    <span><?= htmlspecialchars($patient['house_no']) ?></span>
                                                </div>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">Colony/Area:</span>
                                                    <span><?= htmlspecialchars($patient['colony_area']) ?></span>
                                                </div>
                                                <?php if ($patient['landmark']): ?>
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">Landmark:</span>
                                                        <span><?= htmlspecialchars($patient['landmark']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">City:</span>
                                                    <span><?= htmlspecialchars($patient['addr_city'] ?? $patient['city']) ?></span>
                                                </div>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">State:</span>
                                                    <span><?= htmlspecialchars($patient['addr_state'] ?? $patient['state']) ?></span>
                                                </div>
                                                <div class="d-flex mb-2">
                                                    <span class="info-label">ZIP Code:</span>
                                                    <span><?= $patient['addr_zip'] ?? $patient['zip_code'] ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                                    No address information available.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Identification Information -->
                                        <?php if ($patient['identification_type'] || $patient['identification_number']): ?>
                                            <h5 class="mb-3 mt-4"><i class="fa fa-id-card me-2"></i> Identification</h5>
                                            <div class="info-card">
                                                <?php if ($patient['identification_type']): ?>
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">ID Type:</span>
                                                        <span><?= $patient['identification_type'] ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($patient['identification_number']): ?>
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">ID Number:</span>
                                                        <span><?= htmlspecialchars($patient['identification_number']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Account Status -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5 class="mb-3"><i class="fa fa-info-circle me-2"></i> Account Status</h5>
                                        <div class="info-card">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">Email Verified:</span>
                                                        <span class="badge <?= $patient['email_verified'] ? 'bg-success' : 'bg-warning' ?>">
                                                            <?= $patient['email_verified'] ? 'Verified' : 'Pending' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">Phone Verified:</span>
                                                        <span class="badge <?= $patient['mobile_verified'] ? 'bg-success' : 'bg-warning' ?>">
                                                            <?= $patient['mobile_verified'] ? 'Verified' : 'Pending' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">Status:</span>
                                                        <span class="badge <?= $patient['status'] == 'active' ? 'bg-success' : 'bg-danger' ?>">
                                                            <?= ucfirst($patient['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="d-flex mb-2">
                                                        <span class="info-label">Member Since:</span>
                                                        <span><?= date('d/m/Y', strtotime($patient['created_at'])) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Appointments Tab -->
                        <div class="tab-pane fade" id="appointments" role="tabpanel">
                            <div class="profile-card shadow">
                                <h5 class="mb-4"><i class="fa fa-calendar me-2"></i> Appointment History</h5>
                                
                                <?php if ($appointments_result->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Date & Time</th>
                                                    <th>Type</th>
                                                    <th>Purpose</th>
                                                    <th>Fee</th>
                                                    <th>Status</th>
                                                    <th>Booked On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($appointment = $appointments_result->fetch_assoc()): 
                                                    $status_class = strtolower($appointment['status']);
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-dark">AP<?= str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) ?></span>
                                                        </td>
                                                        <td>
                                                            <div><?= $appointment['formatted_date'] ?></div>
                                                            <small class="text-muted"><?= $appointment['formatted_time'] ?></small>
                                                        </td>
                                                        <td><?= ucfirst($appointment['appointment_type'] ?? 'Clinic') ?></td>
                                                        <td>
                                                            <div class="text-truncate" style="max-width: 150px;">
                                                                <?= htmlspecialchars($appointment['purpose']) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success">₹<?= number_format($appointment['consultation_fee'] ?? 0) ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status badge-<?= $status_class ?>">
                                                                <?= ucfirst($appointment['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?= $appointment['created_at_formatted'] ?></small>
                                                        </td>
                                                        <td>
                                                            <!-- <a href="appointment-details.php?id=<?= $appointment['id'] ?>" 
                                                               class="btn btn-sm btn-info" title="View Details">
                                                                <i class="fa fa-eye"></i>
                                                            </a> -->
                                                            <button type="button" class="btn btn-info" 
                                                                onclick="viewAppointmentDetails(<?= $appointment['id'] ?>)"
                                                                title="View Details">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fa fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <h5>No Appointments Found</h5>
                                        <p class="text-muted">This patient has no appointment history with you.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <div class="profile-card shadow">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="mb-0"><i class="fa fa-file me-2"></i> Patient Documents</h5>
                                    <span class="badge bg-primary"><?= $documents_result->num_rows ?> documents</span>
                                </div>
                                
                                <?php if ($documents_result->num_rows > 0): ?>
                                    <div class="row">
                                        <?php while ($document = $documents_result->fetch_assoc()): 
                                            $file_ext = pathinfo($document['document_name'], PATHINFO_EXTENSION);
                                            $file_size = filesize($document['file_path']) ? round(filesize($document['file_path']) / 1024, 2) . ' KB' : 'Unknown';
                                        ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="document-card">
                                                    <div class="d-flex align-items-start mb-3">
                                                        <div class="me-3">
                                                            <?php if ($file_ext == 'pdf'): ?>
                                                                <i class="fa fa-file-pdf-o document-icon"></i>
                                                            <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                                                <i class="fa fa-file-word-o document-icon"></i>
                                                            <?php elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                                <i class="fa fa-file-image-o document-icon"></i>
                                                            <?php else: ?>
                                                                <i class="fa fa-file-o document-icon"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1 text-truncate" title="<?= htmlspecialchars($document['document_name']) ?>">
                                                                <?= htmlspecialchars($document['document_name']) ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?= $document['file_type'] ?> • <?= $file_size ?>
                                                            </small>
                                                            <?php if ($document['description']): ?>
                                                                <p class="mt-2 small text-muted"><?= htmlspecialchars($document['description']) ?></p>
                                                            <?php endif; ?>
                                                            <small class="text-muted">
                                                                Uploaded: <?= date('d/m/Y', strtotime($document['uploaded_at'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <a href="<?= $document['file_path'] ?>" 
                                                           class="btn btn-sm btn-primary" target="_blank" download>
                                                            <i class="fa fa-download me-1"></i> Download
                                                        </a>
                                                        <a href="?id=<?= $patient_id ?>&delete_document=<?= $document['id'] ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this document?')">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fa fa-file-text-o fa-3x text-muted mb-3"></i>
                                        <h5>No Documents Found</h5>
                                        <p class="text-muted">No documents have been uploaded for this patient yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Upload Tab -->
                        <div class="tab-pane fade" id="upload" role="tabpanel">
                            <div class="profile-card shadow">
                                <h5 class="mb-4"><i class="fa fa-upload me-2"></i> Upload New Document</h5>
                                
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Document Type</label>
                                            <select name="file_type" class="form-select" required>
                                                <option value="">Select Document Type</option>
                                                <option value="Prescription">Prescription</option>
                                                <option value="Lab Report">Lab Report</option>
                                                <option value="X-Ray">X-Ray</option>
                                                <option value="MRI Scan">MRI Scan</option>
                                                <option value="CT Scan">CT Scan</option>
                                                <option value="Medical Certificate">Medical Certificate</option>
                                                <option value="Discharge Summary">Discharge Summary</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Document File</label>
                                            <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                            <small class="text-muted">Max file size: 10MB (PDF, DOC, DOCX, JPG, PNG allowed)</small>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Description (Optional)</label>
                                            <textarea name="description" class="form-control" rows="3" placeholder="Add a description for this document..."></textarea>
                                        </div>
                                        
                                        <div class="col-md-12">
                                            <button type="submit" name="upload_document" class="btn btn-primary">
                                                <i class="fa fa-upload me-2"></i> Upload Document
                                            </button>
                                            <a href="?id=<?= $patient_id ?>" class="btn btn-secondary">
                                                <i class="fa fa-times me-2"></i> Cancel
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Modal for Appointment Details -->
    <div class="modal fade" id="appointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentDetails">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <?php include("../footer.php") ?>
    
    <script>
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        function viewAppointmentDetails(appointmentId) {
            // Simple AJAX call to get appointment details
            fetch('get-appointment-details.php?id=' + appointmentId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('appointmentDetails').innerHTML = data;
                    var modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                    modal.show();
                })
                .catch(error => {
                    document.getElementById('appointmentDetails').innerHTML = 
                        '<div class="alert alert-danger">Error loading appointment details</div>';
                    var modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                    modal.show();
                });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebarMenu');
            const menuBtn = document.querySelector('.menu-btn');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuBtn.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>