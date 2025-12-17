<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: auth/login.php");
    exit();
}

$error_message = "";
$success_message = "";

// Fetch all patients
$patients_sql = "SELECT id, name, email, mobile, dob, gender FROM users WHERE status = 'active' ORDER BY name";
$patients_result = $conn->query($patients_sql);

// Fetch all active doctors (REMOVED schedule column)
$doctors_sql = "SELECT id, name, specialization, consultation_fee FROM doctors WHERE status = 'active' ORDER BY name";
$doctors_result = $conn->query($doctors_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $patient_id = intval($_POST['patient_id']);
    $doctor_id = intval($_POST['doctor_id']);
    $appointment_date = $conn->real_escape_string($_POST['appointment_date']);
    $appointment_time = $conn->real_escape_string($_POST['appointment_time']);
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $appointment_type = $conn->real_escape_string($_POST['appointment_type'] ?? 'clinic');

    // Validate required fields
    if (empty($patient_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($purpose)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Check if doctor is available at that time
        $check_sql = "
            SELECT id FROM appointments 
            WHERE doctor_id = ? 
            AND appointment_date = ? 
            AND appointment_time = ? 
            AND status IN ('pending', 'confirmed')
            LIMIT 1
        ";

        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Doctor is already booked for this time slot. Please choose another time.";
        } else {
            // Insert appointment
            $insert_sql = "
                INSERT INTO appointments 
                (user_id, doctor_id, appointment_date, appointment_time, purpose, notes, appointment_type, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())
            ";

            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iisssss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $purpose, $notes, $appointment_type);

            if ($stmt->execute()) {
                $appointment_id = $stmt->insert_id;

                // Get appointment details for email
                $details_sql = "
                    SELECT a.*, u.name as patient_name, u.email as patient_email, u.dob, u.mobile,
                           d.name as doctor_name, d.email as doctor_email, d.specialization, d.consultation_fee,
                           TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
                           DATE_FORMAT(a.appointment_date, '%d %M, %Y') as formatted_date
                    FROM appointments a
                    JOIN users u ON a.user_id = u.id
                    JOIN doctors d ON a.doctor_id = d.id
                    WHERE a.id = ?
                ";

                $details_stmt = $conn->prepare($details_sql);
                $details_stmt->bind_param("i", $appointment_id);
                $details_stmt->execute();
                $appointment_details = $details_stmt->get_result()->fetch_assoc();
                $details_stmt->close();

                // Send confirmation email to patient
                if ($appointment_details) {
                    $patient_email = $appointment_details['patient_email'];
                    $patient_name = $appointment_details['patient_name'];

                    // Send email notification
                    if (function_exists('send_appointment_confirmation_email')) {
                        $appointment_info = [
                            'appointment_id' => 'AP' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT),
                            'date' => $appointment_details['formatted_date'],
                            'time' => $appointment_details['formatted_time'],
                            'fee' => number_format($appointment_details['consultation_fee']),
                            'type' => ucfirst($appointment_details['appointment_type']),
                            'purpose' => $appointment_details['purpose']
                        ];

                        $doctor_info = [
                            'name' => $appointment_details['doctor_name'],
                            'specialization' => $appointment_details['specialization']
                        ];

                        send_appointment_confirmation_email(
                            $patient_email,
                            $patient_name,
                            $appointment_info,
                            $doctor_info
                        );
                    }
                }

                $success_message = "Appointment booked successfully! Appointment ID: AP" . str_pad($appointment_id, 6, '0', STR_PAD_LEFT);

                // Clear form data
                unset($_POST);
            } else {
                $error_message = "Failed to book appointment. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Book Appointment | Admin Panel</title>

    <?php include "links.php"; ?>

    <style>
        .booking-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .booking-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .booking-header h2 {
            color: #2c5aa0;
            font-weight: 600;
        }

        .booking-header p {
            color: #6c757d;
            margin-top: 10px;
        }

        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #2c5aa0;
        }

        .form-section h5 {
            color: #2c5aa0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .form-section h5 i {
            margin-right: 10px;
        }

        .required-label::after {
            content: " *";
            color: #dc3545;
        }

        .patient-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s;
        }

        .patient-card:hover {
            border-color: #2c5aa0;
            box-shadow: 0 2px 10px rgba(44, 90, 160, 0.1);
        }

        .patient-card.selected {
            border-color: #2c5aa0;
            background: rgba(44, 90, 160, 0.05);
        }

        .patient-info {
            display: flex;
            align-items: center;
        }

        .patient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #2c5aa0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            margin-right: 15px;
        }

        .patient-details h6 {
            margin-bottom: 5px;
            color: #212529;
        }

        .patient-details p {
            margin-bottom: 3px;
            color: #6c757d;
            font-size: 13px;
        }

        .doctor-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s;
        }

        .doctor-card:hover {
            border-color: #28a745;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.1);
        }

        .doctor-card.selected {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }

        .doctor-info {
            display: flex;
            align-items: center;
        }

        .doctor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #28a745;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
        }

        .doctor-details h6 {
            margin-bottom: 5px;
            color: #212529;
        }

        .doctor-details p {
            margin-bottom: 3px;
            color: #6c757d;
            font-size: 13px;
        }

        .doctor-fee {
            color: #dc3545;
            font-weight: bold;
            font-size: 14px;
        }

        .time-slots {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .time-slot {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            min-width: 80px;
        }

        .time-slot:hover {
            border-color: #2c5aa0;
            color: #2c5aa0;
        }

        .time-slot.selected {
            background: #2c5aa0;
            color: white;
            border-color: #2c5aa0;
        }

        .time-slot.unavailable {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .preview-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #dee2e6;
            margin-top: 20px;
        }

        .preview-header {
            color: #2c5aa0;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .preview-details .row {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f8f9fa;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .booking-container {
                padding: 15px;
            }

            .patient-info,
            .doctor-info {
                flex-direction: column;
                text-align: center;
            }

            .patient-avatar,
            .doctor-avatar {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .time-slots {
                justify-content: center;
            }
        }
    </style>
    <style>
        /* Custom styles for improved UI */
        .avatar-circle-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .avatar-circle-md {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
        }

        .doctor-card-select {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .doctor-card-select:hover {
            transform: translateY(-5px);
        }

        .doctor-card-select.selected .card {
            border-color: #28a745;
            border-width: 2px;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);
        }

        .patient-row {
            cursor: pointer;
        }

        .patient-row:hover {
            background-color: #f8f9fa;
        }

        .patient-row.selected {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .time-slot-card {
            cursor: pointer;
            transition: all 0.2s;
        }

        .time-slot-card:hover {
            transform: scale(1.02);
        }

        .time-slot-card.selected {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }

        .time-slot-card.booked {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .time-slot-card.booked .card-body {
            background-color: #f8d7da;
        }

        /* Custom scrollbar */
        .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "../admin/header.php"; ?>

    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "../admin/top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Book Appointment</h2>
                                        <p class="text-muted mb-0">Book appointment for a patient</p>
                                    </div>
                                    <div class="add_button">
                                        <a href="all-appointment.php" class="btn btn-outline-primary">
                                            <i class="fas fa-arrow-left me-2"></i> Back to Appointments
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <div class="booking-container">
                                    <!-- Success/Error Messages -->
                                    <?php if (isset($success_message)): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <?= $success_message ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($error_message)): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            <?= $error_message ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="" id="appointmentForm">
                                        <!-- Quick Stats -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="alert alert-info">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-users fa-2x me-3"></i>
                                                        <div>
                                                            <h6 class="mb-0">Total Patients: <span class="badge bg-primary"><?= $patients_result->num_rows ?></span></h6>
                                                            <small class="text-muted">Active patients in the system</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="alert alert-success">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user-md fa-2x me-3"></i>
                                                        <div>
                                                            <h6 class="mb-0">Available Doctors: <span class="badge bg-success"><?= $doctors_result->num_rows ?></span></h6>
                                                            <small class="text-muted">Active doctors for appointments</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Patient Selection - Improved for large datasets -->
                                        <div class="form-section">
                                            <h5><i class="fas fa-user-injured"></i> Select Patient</h5>

                                            <div class="row">
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label required-label">Patient Search</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                        <input type="text" class="form-control" id="patientSearch"
                                                            placeholder="Type to search patients by name, email, or phone...">
                                                        <button type="button" class="btn btn-outline-secondary" id="clearPatientSearch">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    <div class="form-text">Start typing to filter patients. Click on a patient to select.</div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Quick Filters</label>
                                                    <select class="form-select" id="patientFilter">
                                                        <option value="all">All Patients</option>
                                                        <option value="male">Male Patients</option>
                                                        <option value="female">Female Patients</option>
                                                        <option value="recent">Recently Added</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Selected Patient Info -->
                                            <div class="selected-patient-info alert alert-primary d-none" id="selectedPatientInfo">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">Selected Patient: <span id="selectedPatientName">None</span></h6>
                                                        <small id="selectedPatientDetails" class="text-muted"></small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="deselectPatient">
                                                        <i class="fas fa-times"></i> Change
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Patient List (Virtual Scroll for large datasets) -->
                                            <div class="patient-list-container">
                                                <div class="card">
                                                    <div class="card-header bg-light py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="small">
                                                                <i class="fas fa-users me-1"></i>
                                                                <span id="patientCount"><?= $patients_result->num_rows ?></span> patients found
                                                            </span>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="toggleCompactView">
                                                                <label class="form-check-label small" for="toggleCompactView">Compact View</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                                            <table class="table table-hover mb-0">
                                                                <thead class="table-light sticky-top">
                                                                    <tr>
                                                                        <th width="60">Select</th>
                                                                        <th>Patient Details</th>
                                                                        <th width="120">Contact</th>
                                                                        <th width="100">Age/Gender</th>
                                                                        <th width="100">Status</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="patientTableBody">
                                                                    <?php
                                                                    $patients_result->data_seek(0); // Reset pointer
                                                                    $count = 0;
                                                                    while ($patient = $patients_result->fetch_assoc()):
                                                                        $count++;
                                                                        $patient_age = date_diff(date_create($patient['dob']), date_create('today'))->y;
                                                                        $initials = strtoupper(substr($patient['name'], 0, 1));
                                                                        $patient_phone = $patient['mobile'] ?: 'Not provided';
                                                                    ?>
                                                                        <tr class="patient-row" data-patient-id="<?= $patient['id'] ?>"
                                                                            data-name="<?= htmlspecialchars($patient['name']) ?>"
                                                                            data-email="<?= htmlspecialchars($patient['email']) ?>"
                                                                            data-phone="<?= htmlspecialchars($patient_phone) ?>"
                                                                            data-age="<?= $patient_age ?>"
                                                                            data-gender="<?= $patient['gender'] ?>">
                                                                            <td>
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input patient-radio"
                                                                                        type="radio" name="patient_radio"
                                                                                        id="patient_<?= $patient['id'] ?>">
                                                                                    <label class="form-check-label" for="patient_<?= $patient['id'] ?>"></label>
                                                                                </div>
                                                                            </td>
                                                                            <td>
                                                                                <div class="d-flex align-items-center">
                                                                                    <div class="avatar-circle-sm bg-primary text-white me-3">
                                                                                        <?= $initials ?>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="fw-bold"><?= htmlspecialchars($patient['name']) ?></div>
                                                                                        <small class="text-muted"><?= htmlspecialchars($patient['email']) ?></small>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td>
                                                                                <small>
                                                                                    <i class="fas fa-phone me-1"></i><br>
                                                                                    <?= htmlspecialchars($patient_phone) ?>
                                                                                </small>
                                                                            </td>
                                                                            <td>
                                                                                <span class="badge bg-info"><?= $patient_age ?>y</span>
                                                                                <span class="badge bg-secondary"><?= $patient['gender'] ?></span>
                                                                            </td>
                                                                            <td>
                                                                                <span class="badge bg-success">Active</span>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endwhile; ?>

                                                                    <?php if ($count === 0): ?>
                                                                        <tr>
                                                                            <td colspan="5" class="text-center py-4">
                                                                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                                                                <p class="text-muted">No patients found</p>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer bg-light py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                Showing <span id="visiblePatients"><?= $count ?></span> of <?= $count ?> patients
                                                            </small>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-secondary" id="prevPatients" disabled>
                                                                    <i class="fas fa-chevron-left"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary" id="nextPatients" disabled>
                                                                    <i class="fas fa-chevron-right"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="patient_id" id="selectedPatientId" required>
                                        </div>

                                        <!-- Doctor Selection - Improved for large datasets -->
                                        <div class="form-section">
                                            <h5><i class="fas fa-user-md"></i> Select Doctor</h5>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-label">Doctor Search</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                        <input type="text" class="form-control" id="doctorSearch"
                                                            placeholder="Search by name, specialization, or expertise...">
                                                        <button type="button" class="btn btn-outline-secondary" id="clearDoctorSearch">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Specialization</label>
                                                    <select class="form-select" id="specializationFilter">
                                                        <option value="">All Specializations</option>
                                                        <?php
                                                        // Get unique specializations
                                                        $spec_sql = "SELECT DISTINCT specialization FROM doctors WHERE status = 'active' AND specialization IS NOT NULL ORDER BY specialization";
                                                        $spec_result = $conn->query($spec_sql);
                                                        while ($spec = $spec_result->fetch_assoc()):
                                                            if (!empty($spec['specialization'])):
                                                        ?>
                                                                <option value="<?= htmlspecialchars($spec['specialization']) ?>">
                                                                    <?= htmlspecialchars($spec['specialization']) ?>
                                                                </option>
                                                        <?php
                                                            endif;
                                                        endwhile;
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Sort By</label>
                                                    <select class="form-select" id="doctorSort">
                                                        <option value="name">Name (A-Z)</option>
                                                        <option value="name_desc">Name (Z-A)</option>
                                                        <option value="fee_low">Fee (Low to High)</option>
                                                        <option value="fee_high">Fee (High to Low)</option>
                                                        <option value="experience">Experience</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Selected Doctor Info -->
                                            <div class="selected-doctor-info alert alert-success d-none" id="selectedDoctorInfo">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">Selected Doctor: <span id="selectedDoctorName">None</span></h6>
                                                        <small id="selectedDoctorDetails" class="text-muted"></small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="deselectDoctor">
                                                        <i class="fas fa-times"></i> Change
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Doctor Cards Grid -->
                                            <div class="doctor-grid-container">
                                                <div class="row" id="doctorGrid">
                                                    <?php
                                                    $doctors_result->data_seek(0); // Reset pointer
                                                    $doctor_count = 0;
                                                    while ($doctor = $doctors_result->fetch_assoc()):
                                                        $doctor_count++;
                                                        $initials = strtoupper(substr($doctor['name'], 0, 1));
                                                    ?>
                                                        <div class="col-xl-4 col-lg-6 col-md-6 mb-3">
                                                            <div class="doctor-card-select"
                                                                data-doctor-id="<?= $doctor['id'] ?>"
                                                                data-name="<?= htmlspecialchars($doctor['name']) ?>"
                                                                data-specialization="<?= htmlspecialchars($doctor['specialization']) ?>"
                                                                data-fee="<?= $doctor['consultation_fee'] ?>">
                                                                <div class="card h-100 border">
                                                                    <div class="card-body">
                                                                        <div class="d-flex align-items-start">
                                                                            <div class="avatar-circle-md bg-success text-white me-3">
                                                                                <?= $initials ?>
                                                                            </div>
                                                                            <div class="flex-grow-1">
                                                                                <h6 class="card-title mb-1">
                                                                                    Dr. <?= htmlspecialchars($doctor['name']) ?>
                                                                                </h6>
                                                                                <p class="card-text small text-muted mb-2">
                                                                                    <i class="fas fa-stethoscope me-1"></i>
                                                                                    <?= htmlspecialchars($doctor['specialization']) ?>
                                                                                </p>
                                                                                <div class="d-flex justify-content-between align-items-center">
                                                                                    <span class="badge bg-danger">
                                                                                        <i class="fas fa-rupee-sign me-1"></i>
                                                                                        <?= number_format($doctor['consultation_fee']) ?>
                                                                                    </span>
                                                                                    <button type="button" class="btn btn-sm btn-outline-primary select-doctor-btn">
                                                                                        Select
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="card-footer bg-light py-2">
                                                                        <small class="text-muted">
                                                                            <i class="fas fa-clock me-1"></i>
                                                                            <span class="doctor-availability">Checking availability...</span>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endwhile; ?>

                                                    <?php if ($doctor_count === 0): ?>
                                                        <div class="col-12">
                                                            <div class="alert alert-warning text-center py-4">
                                                                <i class="fas fa-user-md fa-2x text-muted mb-3"></i>
                                                                <h5>No Doctors Available</h5>
                                                                <p class="text-muted">Please add doctors to the system first.</p>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Doctor Pagination -->
                                            <?php if ($doctor_count > 0): ?>
                                                <div class="row mt-3">
                                                    <div class="col-12">
                                                        <nav aria-label="Doctor pagination">
                                                            <ul class="pagination pagination-sm justify-content-center" id="doctorPagination">
                                                                <!-- Pagination will be generated by JavaScript -->
                                                            </ul>
                                                        </nav>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <input type="hidden" name="doctor_id" id="selectedDoctorId" required>
                                        </div>

                                        <!-- Appointment Details -->
                                        <div class="form-section">
                                            <h5><i class="fas fa-calendar-alt"></i> Appointment Details</h5>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label required-label">Appointment Date</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                        <input type="date" class="form-control" name="appointment_date"
                                                            id="appointmentDate" min="<?= date('Y-m-d') ?>" required>
                                                    </div>
                                                    <div class="form-text">Next available: <span id="nextAvailableDate"><?= date('Y-m-d') ?></span></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label required-label">Appointment Type</label>
                                                    <select class="form-select" name="appointment_type" id="appointmentType" required>
                                                        <option value="clinic">🏥 Clinic Visit</option>
                                                        <option value="video">📹 Video Consultation</option>
                                                        <option value="home">🏠 Home Visit</option>
                                                        <option value="followup">🔄 Follow-up Visit</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label required-label">Duration</label>
                                                    <select class="form-select" name="duration" id="appointmentDuration">
                                                        <option value="30">30 minutes</option>
                                                        <option value="45" selected>45 minutes</option>
                                                        <option value="60">1 hour</option>
                                                        <option value="90">1.5 hours</option>
                                                        <option value="120">2 hours</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Time Slots Selection -->
                                            <div class="mb-4">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <label class="form-label required-label mb-0">Select Time Slot</label>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" id="showBookedSlots">
                                                        <label class="form-check-label small" for="showBookedSlots">
                                                            Show booked slots
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="time-slots-container card">
                                                    <div class="card-header bg-light">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-6">
                                                                <small>
                                                                    <i class="fas fa-info-circle me-1"></i>
                                                                    Available time slots for <span id="selectedDateDisplay">selected date</span>
                                                                </small>
                                                            </div>
                                                            <div class="col-md-6 text-end">
                                                                <div class="btn-group btn-group-sm">
                                                                    <button type="button" class="btn btn-outline-secondary" id="prevTimeSlot">
                                                                        <i class="fas fa-chevron-left"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-secondary" id="nextTimeSlot">
                                                                        <i class="fas fa-chevron-right"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <div id="timeSlotsLoading" class="text-center py-5">
                                                            <div class="spinner-border text-primary" role="status">
                                                                <span class="visually-hidden">Loading...</span>
                                                            </div>
                                                            <p class="mt-2">Loading available time slots...</p>
                                                        </div>
                                                        <div id="timeSlotsGrid" class="row" style="display: none;">
                                                            <!-- Time slots will be loaded here -->
                                                        </div>
                                                        <div id="noTimeSlots" class="text-center py-5" style="display: none;">
                                                            <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                                                            <h5>No Time Slots Available</h5>
                                                            <p class="text-muted">No available time slots for the selected date.</p>
                                                            <button type="button" class="btn btn-outline-primary" id="refreshTimeSlots">
                                                                <i class="fas fa-sync me-1"></i> Refresh
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="appointment_time" id="selectedTimeSlot" required>
                                            </div>

                                            <!-- Appointment Details -->
                                            <div class="row">
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label required-label">Purpose of Visit</label>
                                                    <textarea class="form-control" name="purpose" id="purpose" rows="3"
                                                        placeholder="Please describe the reason for appointment in detail..."
                                                        maxlength="500" required></textarea>
                                                    <div class="form-text">
                                                        <span id="purposeCounter">0</span>/500 characters
                                                    </div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Priority</label>
                                                    <select class="form-select" name="priority">
                                                        <option value="normal">🟢 Normal</option>
                                                        <option value="urgent">🟡 Urgent</option>
                                                        <option value="emergency">🔴 Emergency</option>
                                                    </select>
                                                    <div class="form-text">Set appointment priority</div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Additional Notes (Optional)</label>
                                                <textarea class="form-control" name="notes" id="notes" rows="2"
                                                    placeholder="Any additional information, symptoms, or special instructions..."></textarea>
                                            </div>
                                        </div>

                                        <!-- Summary & Actions -->
                                        <div class="form-section">
                                            <h5><i class="fas fa-clipboard-check"></i> Summary & Confirmation</h5>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header bg-primary text-white">
                                                            <h6 class="mb-0"><i class="fas fa-receipt me-2"></i> Appointment Summary</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <table class="table table-sm table-borderless mb-0">
                                                                <tr>
                                                                    <td width="40%"><strong>Patient:</strong></td>
                                                                    <td id="summaryPatient">Not selected</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Doctor:</strong></td>
                                                                    <td id="summaryDoctor">Not selected</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Date & Time:</strong></td>
                                                                    <td id="summaryDateTime">Not selected</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Type:</strong></td>
                                                                    <td id="summaryType">Clinic Visit</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Duration:</strong></td>
                                                                    <td id="summaryDuration">45 minutes</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Consultation Fee:</strong></td>
                                                                    <td id="summaryFee">₹0</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Priority:</strong></td>
                                                                    <td id="summaryPriority">Normal</td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="card h-100">
                                                        <div class="card-header bg-secondary text-white">
                                                            <h6 class="mb-0"><i class="fas fa-cog me-2"></i> Actions</h6>
                                                        </div>
                                                        <div class="card-body d-flex flex-column justify-content-center">
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="sendEmailNotification" checked>
                                                                    <label class="form-check-label" for="sendEmailNotification">
                                                                        Send email notification to patient
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="sendSMSNotification">
                                                                    <label class="form-check-label" for="sendSMSNotification">
                                                                        Send SMS notification (if phone available)
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="addToCalendar">
                                                                    <label class="form-check-label" for="addToCalendar">
                                                                        Add to doctor's calendar
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="createFollowupReminder">
                                                                    <label class="form-check-label" for="createFollowupReminder">
                                                                        Create follow-up reminder
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Submit Buttons -->
                                        <div class="text-center mt-4">
                                            <div class="btn-group" role="group">
                                                <button type="reset" class="btn btn-lg btn-outline-secondary">
                                                    <i class="fas fa-redo me-2"></i> Clear Form
                                                </button>
                                                <button type="button" class="btn btn-lg btn-outline-primary" id="saveDraftBtn">
                                                    <i class="fas fa-save me-2"></i> Save as Draft
                                                </button>
                                                <button type="submit" class="btn btn-lg btn-primary">
                                                    <i class="fas fa-calendar-plus me-2"></i> Book Appointment Now
                                                </button>
                                            </div>

                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Appointment will be confirmed immediately. Patient will receive notification.
                                                </small>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <style>
                                /* Custom styles for improved UI */
                                .avatar-circle-sm {
                                    width: 40px;
                                    height: 40px;
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-weight: bold;
                                    font-size: 14px;
                                }

                                .avatar-circle-md {
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-weight: bold;
                                    font-size: 20px;
                                }

                                .doctor-card-select {
                                    cursor: pointer;
                                    transition: transform 0.2s;
                                }

                                .doctor-card-select:hover {
                                    transform: translateY(-5px);
                                }

                                .doctor-card-select.selected .card {
                                    border-color: #28a745;
                                    border-width: 2px;
                                    box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);
                                }

                                .patient-row {
                                    cursor: pointer;
                                }

                                .patient-row:hover {
                                    background-color: #f8f9fa;
                                }

                                .patient-row.selected {
                                    background-color: rgba(13, 110, 253, 0.1);
                                }

                                .time-slot-card {
                                    cursor: pointer;
                                    transition: all 0.2s;
                                }

                                .time-slot-card:hover {
                                    transform: scale(1.02);
                                }

                                .time-slot-card.selected {
                                    border-color: #28a745;
                                    background-color: rgba(40, 167, 69, 0.1);
                                }

                                .time-slot-card.booked {
                                    opacity: 0.5;
                                    cursor: not-allowed;
                                }

                                .time-slot-card.booked .card-body {
                                    background-color: #f8d7da;
                                }

                                /* Custom scrollbar */
                                .table-responsive::-webkit-scrollbar {
                                    width: 6px;
                                    height: 6px;
                                }

                                .table-responsive::-webkit-scrollbar-track {
                                    background: #f1f1f1;
                                    border-radius: 3px;
                                }

                                .table-responsive::-webkit-scrollbar-thumb {
                                    background: #c1c1c1;
                                    border-radius: 3px;
                                }

                                .table-responsive::-webkit-scrollbar-thumb:hover {
                                    background: #a8a8a8;
                                }

                                /* Loading skeleton */
                                .skeleton {
                                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                                    background-size: 200% 100%;
                                    animation: loading 1.5s infinite;
                                }

                                @keyframes loading {
                                    0% {
                                        background-position: 200% 0;
                                    }

                                    100% {
                                        background-position: -200% 0;
                                    }
                                }
                            </style>


                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "../admin/footer.php"; ?>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize variables
            let selectedPatient = null;
            let selectedDoctor = null;
            let selectedTimeSlot = null;
            let doctorsPerPage = 6;
            let currentDoctorPage = 1;
            let totalDoctors = <?= $doctor_count ?>;

            // Patient Selection
            const patientSearch = document.getElementById('patientSearch');
            const patientFilter = document.getElementById('patientFilter');
            const patientTableBody = document.getElementById('patientTableBody');
            const patientRows = document.querySelectorAll('.patient-row');
            const selectedPatientId = document.getElementById('selectedPatientId');
            const selectedPatientInfo = document.getElementById('selectedPatientInfo');
            const selectedPatientName = document.getElementById('selectedPatientName');
            const selectedPatientDetails = document.getElementById('selectedPatientDetails');
            const clearPatientSearch = document.getElementById('clearPatientSearch');
            const deselectPatient = document.getElementById('deselectPatient');

            // Doctor Selection
            const doctorSearch = document.getElementById('doctorSearch');
            const specializationFilter = document.getElementById('specializationFilter');
            const doctorSort = document.getElementById('doctorSort');
            const doctorGrid = document.getElementById('doctorGrid');
            const selectedDoctorId = document.getElementById('selectedDoctorId');
            const selectedDoctorInfo = document.getElementById('selectedDoctorInfo');
            const selectedDoctorName = document.getElementById('selectedDoctorName');
            const selectedDoctorDetails = document.getElementById('selectedDoctorDetails');
            const clearDoctorSearch = document.getElementById('clearDoctorSearch');
            const deselectDoctor = document.getElementById('deselectDoctor');

            // Appointment Details
            const appointmentDate = document.getElementById('appointmentDate');
            const appointmentType = document.getElementById('appointmentType');
            const appointmentDuration = document.getElementById('appointmentDuration');
            const purpose = document.getElementById('purpose');
            const purposeCounter = document.getElementById('purposeCounter');
            const notes = document.getElementById('notes');
            const selectedTimeSlotInput = document.getElementById('selectedTimeSlot');

            // Time Slots
            const timeSlotsLoading = document.getElementById('timeSlotsLoading');
            const timeSlotsGrid = document.getElementById('timeSlotsGrid');
            const noTimeSlots = document.getElementById('noTimeSlots');
            const selectedDateDisplay = document.getElementById('selectedDateDisplay');
            const refreshTimeSlots = document.getElementById('refreshTimeSlots');
            const showBookedSlots = document.getElementById('showBookedSlots');

            // Summary
            const summaryPatient = document.getElementById('summaryPatient');
            const summaryDoctor = document.getElementById('summaryDoctor');
            const summaryDateTime = document.getElementById('summaryDateTime');
            const summaryType = document.getElementById('summaryType');
            const summaryDuration = document.getElementById('summaryDuration');
            const summaryFee = document.getElementById('summaryFee');
            const summaryPriority = document.getElementById('summaryPriority');

            // Patient Search Functionality
            patientSearch.addEventListener('input', filterPatients);
            patientFilter.addEventListener('change', filterPatients);
            clearPatientSearch.addEventListener('click', function() {
                patientSearch.value = '';
                filterPatients();
            });

            function filterPatients() {
                const searchTerm = patientSearch.value.toLowerCase();
                const filter = patientFilter.value;
                let visibleCount = 0;

                patientRows.forEach(row => {
                    const name = row.getAttribute('data-name').toLowerCase();
                    const email = row.getAttribute('data-email').toLowerCase();
                    const phone = row.getAttribute('data-phone').toLowerCase();
                    const gender = row.getAttribute('data-gender').toLowerCase();
                    const age = parseInt(row.getAttribute('data-age'));

                    let matchesSearch = searchTerm === '' ||
                        name.includes(searchTerm) ||
                        email.includes(searchTerm) ||
                        phone.includes(searchTerm);

                    let matchesFilter = true;
                    if (filter === 'male') matchesFilter = gender === 'male';
                    if (filter === 'female') matchesFilter = gender === 'female';
                    if (filter === 'recent') {
                        // You might want to add created_at to your query and pass it as data attribute
                        matchesFilter = true; // Default for now
                    }

                    if (matchesSearch && matchesFilter) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                document.getElementById('visiblePatients').textContent = visibleCount;
            }

            // Patient Selection
            patientRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (e.target.type === 'radio' || e.target.type === 'checkbox') return;

                    // Deselect all rows
                    patientRows.forEach(r => {
                        r.classList.remove('selected');
                        const radio = r.querySelector('.patient-radio');
                        if (radio) radio.checked = false;
                    });

                    // Select this row
                    this.classList.add('selected');
                    const radio = this.querySelector('.patient-radio');
                    if (radio) radio.checked = true;

                    // Update selected patient
                    selectedPatient = {
                        id: this.getAttribute('data-patient-id'),
                        name: this.getAttribute('data-name'),
                        email: this.getAttribute('data-email'),
                        phone: this.getAttribute('data-phone'),
                        age: this.getAttribute('data-age'),
                        gender: this.getAttribute('data-gender')
                    };

                    selectedPatientId.value = selectedPatient.id;

                    // Update selected patient info
                    selectedPatientName.textContent = selectedPatient.name;
                    selectedPatientDetails.textContent =
                        `${selectedPatient.email} • ${selectedPatient.phone} • ${selectedPatient.age}y ${selectedPatient.gender}`;
                    selectedPatientInfo.classList.remove('d-none');

                    // Update summary
                    summaryPatient.textContent = selectedPatient.name;
                });
            });

            // Deselect patient
            deselectPatient.addEventListener('click', function() {
                selectedPatient = null;
                selectedPatientId.value = '';
                selectedPatientInfo.classList.add('d-none');
                patientRows.forEach(r => {
                    r.classList.remove('selected');
                    const radio = r.querySelector('.patient-radio');
                    if (radio) radio.checked = false;
                });
                summaryPatient.textContent = 'Not selected';
            });

            // Doctor Search and Filter
            doctorSearch.addEventListener('input', filterDoctors);
            specializationFilter.addEventListener('change', filterDoctors);
            doctorSort.addEventListener('change', sortDoctors);
            clearDoctorSearch.addEventListener('click', function() {
                doctorSearch.value = '';
                filterDoctors();
            });

            function filterDoctors() {
                const searchTerm = doctorSearch.value.toLowerCase();
                const specialization = specializationFilter.value.toLowerCase();

                document.querySelectorAll('.doctor-card-select').forEach(card => {
                    const name = card.getAttribute('data-name').toLowerCase();
                    const spec = card.getAttribute('data-specialization').toLowerCase();

                    let matchesSearch = searchTerm === '' ||
                        name.includes(searchTerm) ||
                        spec.includes(searchTerm);

                    let matchesSpecialization = specialization === '' ||
                        spec.includes(specialization);

                    if (matchesSearch && matchesSpecialization) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });

                updatePagination();
            }

            function sortDoctors() {
                const sortBy = doctorSort.value;
                const cards = Array.from(document.querySelectorAll('.doctor-card-select'));

                cards.sort((a, b) => {
                    switch (sortBy) {
                        case 'name':
                            return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                        case 'name_desc':
                            return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
                        case 'fee_low':
                            return parseFloat(a.getAttribute('data-fee')) - parseFloat(b.getAttribute('data-fee'));
                        case 'fee_high':
                            return parseFloat(b.getAttribute('data-fee')) - parseFloat(a.getAttribute('data-fee'));
                        default:
                            return 0;
                    }
                });

                cards.forEach(card => {
                    doctorGrid.appendChild(card);
                });

                updatePagination();
            }

            // Doctor Selection
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('select-doctor-btn')) {
                    const card = e.target.closest('.doctor-card-select');
                    selectDoctor(card);
                }
            });

            function selectDoctor(card) {
                // Deselect all doctors
                document.querySelectorAll('.doctor-card-select').forEach(c => {
                    c.classList.remove('selected');
                });

                // Select this doctor
                card.classList.add('selected');

                selectedDoctor = {
                    id: card.getAttribute('data-doctor-id'),
                    name: card.getAttribute('data-name'),
                    specialization: card.getAttribute('data-specialization'),
                    fee: card.getAttribute('data-fee')
                };

                selectedDoctorId.value = selectedDoctor.id;

                // Update selected doctor info
                selectedDoctorName.textContent = `Dr. ${selectedDoctor.name}`;
                selectedDoctorDetails.textContent =
                    `${selectedDoctor.specialization} • Consultation Fee: ₹${parseInt(selectedDoctor.fee).toLocaleString()}`;
                selectedDoctorInfo.classList.remove('d-none');

                // Update summary
                summaryDoctor.textContent = `Dr. ${selectedDoctor.name}`;
                summaryFee.textContent = `₹${parseInt(selectedDoctor.fee).toLocaleString()}`;

                // Load time slots if date is selected
                if (appointmentDate.value) {
                    loadTimeSlots();
                }
            }

            // Deselect doctor
            deselectDoctor.addEventListener('click', function() {
                selectedDoctor = null;
                selectedDoctorId.value = '';
                selectedDoctorInfo.classList.add('d-none');
                document.querySelectorAll('.doctor-card-select').forEach(c => {
                    c.classList.remove('selected');
                });
                summaryDoctor.textContent = 'Not selected';
                summaryFee.textContent = '₹0';

                // Clear time slots
                timeSlotsGrid.innerHTML = '';
                timeSlotsGrid.style.display = 'none';
                noTimeSlots.style.display = 'none';
                timeSlotsLoading.style.display = 'block';
            });

            // Date change handler
            appointmentDate.addEventListener('change', function() {
                const date = new Date(this.value);
                selectedDateDisplay.textContent = date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                if (selectedDoctor && this.value) {
                    loadTimeSlots();
                }
            });

            // Load time slots function
            function loadTimeSlots() {
                if (!selectedDoctor || !appointmentDate.value) {
                    return;
                }

                timeSlotsLoading.style.display = 'block';
                timeSlotsGrid.style.display = 'none';
                noTimeSlots.style.display = 'none';

                // Get selected duration
                const duration = appointmentDuration.value;

                fetch('get_time_slots.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `doctor_id=${selectedDoctor.id}&date=${appointmentDate.value}&duration=${duration}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        timeSlotsLoading.style.display = 'none';

                        if (data.success && data.timeSlots && data.timeSlots.length > 0) {
                            displayTimeSlots(data.timeSlots);
                        } else {
                            showNoTimeSlots();
                        }
                    })
                    .catch(error => {
                        timeSlotsLoading.style.display = 'none';
                        showNoTimeSlots();
                        console.error('Error loading time slots:', error);
                    });
            }

            function displayTimeSlots(slots) {
                timeSlotsGrid.innerHTML = '';
                timeSlotsGrid.style.display = 'block';
                noTimeSlots.style.display = 'none';

                slots.forEach(slot => {
                    const isBooked = slot.booked || false;
                    const colClass = 'col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3';

                    const slotCard = document.createElement('div');
                    slotCard.className = colClass;
                    slotCard.innerHTML = `
                <div class="time-slot-card card h-100 ${isBooked ? 'booked' : ''} ${slot.selected ? 'selected' : ''}" 
                     data-time="${slot.time}"
                     onclick="${isBooked ? '' : 'selectTimeSlot(this)'}">
                    <div class="card-body text-center">
                        <h5 class="card-title">${slot.display}</h5>
                        <p class="card-text small">
                            ${isBooked ? 
                                '<span class="badge bg-danger">Booked</span>' : 
                                '<span class="badge bg-success">Available</span>'
                            }
                        </p>
                        ${!isBooked ? 
                            `<p class="card-text small text-muted">
                                Duration: ${slot.duration || '45'} min
                            </p>` : ''
                        }
                    </div>
                </div>
            `;

                    timeSlotsGrid.appendChild(slotCard);
                });

                // Add click handlers to time slot cards
                document.querySelectorAll('.time-slot-card:not(.booked)').forEach(card => {
                    card.addEventListener('click', function() {
                        selectTimeSlot(this);
                    });
                });
            }

            function showNoTimeSlots() {
                timeSlotsGrid.style.display = 'none';
                noTimeSlots.style.display = 'block';
            }

            // Refresh time slots
            refreshTimeSlots.addEventListener('click', loadTimeSlots);

            // Select time slot
            window.selectTimeSlot = function(element) {
                if (element.classList.contains('booked')) return;

                // Deselect all time slots
                document.querySelectorAll('.time-slot-card').forEach(card => {
                    card.classList.remove('selected');
                });

                // Select this time slot
                element.classList.add('selected');
                selectedTimeSlot = element.getAttribute('data-time');
                selectedTimeSlotInput.value = selectedTimeSlot;

                // Update summary
                const timeDisplay = element.querySelector('.card-title').textContent;
                const dateDisplay = selectedDateDisplay.textContent;
                summaryDateTime.textContent = `${dateDisplay} at ${timeDisplay}`;
            };

            // Update purpose character counter
            purpose.addEventListener('input', function() {
                purposeCounter.textContent = this.value.length;
            });

            // Update summary when form changes
            appointmentType.addEventListener('change', function() {
                const typeText = this.options[this.selectedIndex].text;
                summaryType.textContent = typeText;
            });

            appointmentDuration.addEventListener('change', function() {
                const durationText = this.options[this.selectedIndex].text;
                summaryDuration.textContent = durationText;

                // Reload time slots with new duration
                if (selectedDoctor && appointmentDate.value) {
                    loadTimeSlots();
                }
            });

            document.querySelector('[name="priority"]').addEventListener('change', function() {
                const priorityText = this.options[this.selectedIndex].text;
                summaryPriority.textContent = priorityText;
            });

            // Show/hide booked slots
            showBookedSlots.addEventListener('change', function() {
                document.querySelectorAll('.time-slot-card.booked').forEach(card => {
                    card.style.display = this.checked ? 'block' : 'none';
                });
            });

            // Save draft functionality
            document.getElementById('saveDraftBtn').addEventListener('click', function() {
                // Implement save as draft functionality
                alert('Draft save functionality would be implemented here');
            });

            // Form validation
            document.getElementById('appointmentForm').addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    alert('Please complete all required fields before submitting.');
                }
            });

            function validateForm() {
                let isValid = true;

                // Check patient selection
                if (!selectedPatientId.value) {
                    document.querySelector('.patient-list-container').scrollIntoView({
                        behavior: 'smooth'
                    });
                    isValid = false;
                }

                // Check doctor selection
                if (!selectedDoctorId.value) {
                    document.querySelector('.doctor-grid-container').scrollIntoView({
                        behavior: 'smooth'
                    });
                    isValid = false;
                }

                // Check appointment details
                if (!appointmentDate.value || !selectedTimeSlotInput.value || !purpose.value.trim()) {
                    document.querySelector('.time-slots-container').scrollIntoView({
                        behavior: 'smooth'
                    });
                    isValid = false;
                }

                return isValid;
            }

            // Initialize
            filterPatients();
            filterDoctors();

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            appointmentDate.min = today;

            // Auto-close alerts
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>

</html>