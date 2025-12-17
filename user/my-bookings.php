<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// session_start(); // Uncomment if not already started
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ".$site."login.php");
    exit();
}

$contact = contact_us();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';

// Initialize variables
$success_message = '';
$error_message = '';
$errors = [];

// Fetch user's upcoming appointments (next 7 days)
$upcoming_appointments = [];
$appointments_stmt = $conn->prepare("
    SELECT 
        a.*,
        d.name as doctor_name,
        d.specialization,
        d.degrees,
        d.consultation_fee,
        d.profile_image,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
        DATE_FORMAT(a.appointment_date, '%d/%m/%Y') as formatted_date,
        DATE_FORMAT(a.appointment_date, '%W, %d %M %Y') as full_date,
        CASE 
            WHEN a.appointment_date > CURDATE() THEN 'upcoming'
            WHEN a.appointment_date = CURDATE() AND a.appointment_time > CURTIME() THEN 'today'
            ELSE 'past'
        END as appointment_status
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.user_id = ? 
    AND a.status IN ('confirmed', 'pending')
    AND (a.appointment_date >= CURDATE())
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
while ($row = $appointments_result->fetch_assoc()) {
    $upcoming_appointments[] = $row;
}
$appointments_stmt->close();

// Fetch popular doctors (with highest ratings)
$popular_doctors = [];
$doctors_stmt = $conn->prepare("
    SELECT id, name, specialization, consultation_fee, profile_image, 
           experience_years, rating, languages,
           IFNULL((
               SELECT COUNT(*) FROM appointments 
               WHERE doctor_id = doctors.id 
               AND appointment_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
           ), 0) as recent_bookings
    FROM doctors 
    WHERE status = 'Active' AND is_verified = 1
    ORDER BY rating DESC, recent_bookings DESC
    LIMIT 6
");
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();
while ($row = $doctors_result->fetch_assoc()) {
    $popular_doctors[] = $row;
}
$doctors_stmt->close();

// Handle appointment booking via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    header('Content-Type: application/json');
    
    // Sanitize and validate input data
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    
    // Validation
    $validation_errors = [];
    
    if ($doctor_id <= 0) {
        $validation_errors['doctor_id'] = "Please select a doctor";
    }
    
    if (empty($appointment_date)) {
        $validation_errors['appointment_date'] = "Please select appointment date";
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $validation_errors['appointment_date'] = "Appointment date cannot be in the past";
    }
    
    if (empty($appointment_time)) {
        $validation_errors['appointment_time'] = "Please select appointment time";
    }
    
    if (empty($purpose)) {
        $validation_errors['purpose'] = "Please mention the purpose of visit";
    } elseif (strlen($purpose) < 10) {
        $validation_errors['purpose'] = "Please provide more details about your visit";
    }
    
    // Check if time slot is available
    if (empty($validation_errors)) {
        $check_stmt = $conn->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? 
            AND appointment_date = ? 
            AND appointment_time = ? 
            AND status NOT IN ('cancelled', 'rejected')
        ");
        $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $validation_errors['appointment_time'] = "This time slot is already booked. Please choose another time.";
        }
        $check_stmt->close();
    }
    
    // If no errors, save appointment
    if (empty($validation_errors)) {
        $insert_stmt = $conn->prepare("
            INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, purpose, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $insert_stmt->bind_param("iisss", $user_id, $doctor_id, $appointment_date, $appointment_time, $purpose);
        
        if ($insert_stmt->execute()) {
            $appointment_id = $insert_stmt->insert_id;
            
            // Get appointment details for response
            $details_stmt = $conn->prepare("
                SELECT 
                    a.*,
                    d.name as doctor_name,
                    d.specialization,
                    d.consultation_fee,
                    TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
                    DATE_FORMAT(a.appointment_date, '%d %M, %Y') as formatted_date
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.id = ?
            ");
            $details_stmt->bind_param("i", $appointment_id);
            $details_stmt->execute();
            $appointment_details = $details_stmt->get_result()->fetch_assoc();
            $details_stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment booked successfully!',
                'appointment_id' => $appointment_id,
                'details' => $appointment_details
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'errors' => ['general' => 'Failed to book appointment. Please try again.']
            ]);
        }
        $insert_stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $validation_errors
        ]);
    }
    exit();
}

// Handle appointment cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    
    // Verify that the appointment belongs to the current user
    $check_stmt = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $cancel_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $cancel_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $cancel_stmt->bind_param("i", $cancel_id);
        
        if ($cancel_stmt->execute()) {
            $success_message = "Appointment cancelled successfully!";
        } else {
            $error_message = "Failed to cancel appointment. Please try again.";
        }
        $cancel_stmt->close();
    } else {
        $error_message = "Appointment not found or you don't have permission to cancel it.";
    }
    $check_stmt->close();
    
    // Redirect to remove cancel_id from URL
    header("Location: my-bookings.php");
    exit();
}

// Get time slots for selected doctor and date (for AJAX)
if (isset($_GET['get_time_slots']) && isset($_GET['doctor_id']) && isset($_GET['date'])) {
    $doctor_id = intval($_GET['doctor_id']);
    $date = $conn->real_escape_string($_GET['date']);
    
    // Get doctor's working hours
    $working_hours = [
        'start' => '09:00:00',
        'end' => '18:00:00',
        'break_start' => '13:00:00',
        'break_end' => '14:00:00'
    ];
    
    // Get booked slots
    $booked_slots = [];
    $slot_stmt = $conn->prepare("
        SELECT TIME_FORMAT(appointment_time, '%H:%i') as time_slot 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status NOT IN ('cancelled', 'rejected')
    ");
    $slot_stmt->bind_param("is", $doctor_id, $date);
    $slot_stmt->execute();
    $slot_result = $slot_stmt->get_result();
    while ($row = $slot_result->fetch_assoc()) {
        $booked_slots[] = $row['time_slot'];
    }
    $slot_stmt->close();
    
    // Generate available time slots (30 minute intervals)
    $available_slots = [];
    $start_time = strtotime($working_hours['start']);
    $end_time = strtotime($working_hours['end']);
    $break_start = strtotime($working_hours['break_start']);
    $break_end = strtotime($working_hours['break_end']);
    
    for ($time = $start_time; $time < $end_time; $time += 1800) { // 1800 seconds = 30 minutes
        if ($time >= $break_start && $time < $break_end) {
            continue; // Skip break time
        }
        
        $time_slot = date('H:i', $time);
        if (!in_array($time_slot, $booked_slots)) {
            $available_slots[] = [
                'time' => date('h:i A', $time),
                'value' => date('H:i:s', $time)
            ];
        }
    }
    
    echo json_encode($available_slots);
    exit();
}

// Calculate min and max dates for appointment
$min_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+30 days'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>Book Appointment | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        /* Global Styles */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin: 5px 0;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .sidebar a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background: #2c5aa0;
            color: white;
            transform: translateX(5px);
        }
        
        .user-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2c5aa0;
            margin-bottom: 10px;
        }
        
        .mobile-menu-btn {
            display: none;
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Main Content */
        .main-content {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
        }
        
        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #2c5aa0, #4a7bc8);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 32px;
            margin: 0 0 5px 0;
            font-weight: 700;
        }
        
        .stat-card p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* Quick Booking Form */
        .booking-form-container {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            background:#e1e1e1;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
        }
        
        .booking-form-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .booking-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
            opacity: 0.9;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255,255,255,0.95);
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }
        
        .form-select {
            appearance: none;
            background: rgba(255,255,255,0.95) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232c5aa0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat right 15px center;
            background-size: 12px;
            padding-right: 40px;
        }
        
        .time-slots-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .time-slot {
            background: rgba(255,255,255,0.95);
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 10px 5px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            color: #2c5aa0;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background: white;
            border-color: rgba(255,255,255,0.5);
        }
        
        .time-slot.selected {
            background: #ffcc00;
            color: #333;
            border-color: #ffcc00;
        }
        
        .time-slot.booked {
            background: rgba(255,255,255,0.5);
            color: #999;
            cursor: not-allowed;
        }
        
        .btn-book {
            grid-column: span 2;
            background: #ffcc00;
            color: #333;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-book:hover {
            background: #ffd633;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Doctor Cards */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .doctor-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid #eee;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .doctor-header {
            padding: 20px;
            text-align: center;
            background: #0dcaf021;;
        }
        
        .doctor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 10px;
        }
        
        .doctor-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c5aa0;
            margin: 0 0 5px 0;
        }
        
        .doctor-specialization {
            font-size: 13px;
            color: #666;
            margin: 0 0 10px 0;
        }
        
        .doctor-body {
            padding: 15px;
        }
        
        .doctor-info {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
            color: #555;
        }
        
        .doctor-info i {
            color: #2c5aa0;
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        .doctor-footer {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .consultation-fee {
            font-size: 18px;
            font-weight: 600;
            color: #28a745;
        }
        
        .btn-select {
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-select:hover {
            background: #4a7bc8;
            transform: translateY(-1px);
        }
        
        /* Appointments List */
        .appointments-list {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .appointment-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
            margin-bottom: 15px;
            transition: all 0.2s;
        }
        
        .appointment-item:hover {
            background: #e9ecef;
        }
        
        .appointment-date {
            text-align: center;
            min-width: 70px;
            margin-right: 15px;
        }
        
        .appointment-day {
            font-size: 24px;
            font-weight: 700;
            color: #2c5aa0;
            line-height: 1;
        }
        
        .appointment-month {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .appointment-details {
            flex: 1;
        }
        
        .appointment-doctor {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 5px 0;
        }
        
        .appointment-time {
            font-size: 13px;
            color: #666;
            margin: 0 0 5px 0;
        }
        
        .appointment-purpose {
            font-size: 13px;
            color: #555;
            margin: 0;
        }
        
        .appointment-status {
            margin-left: 15px;
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #2c5aa0 0%, #4a7bc8 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            border: none;
            padding: 20px;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-close-white {
            filter: invert(1);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar { 
                display: none; 
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                overflow-y: auto;
                border-radius: 0;
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }
            
            .sidebar.show { display: block; }
            
            .mobile-menu-btn { display: block; }
            
            .main-content { padding: 15px; }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .booking-form {
                grid-template-columns: 1fr;
            }
            
            .btn-book {
                grid-column: span 1;
            }
            
            .doctors-grid {
                grid-template-columns: 1fr;
            }
            
            .appointment-item {
                flex-direction: column;
                text-align: center;
            }
            
            .appointment-date {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .appointment-status {
                margin-left: 0;
                margin-top: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card h3 { font-size: 28px; }
            .booking-form-title { font-size: 18px; }
            .doctor-name { font-size: 16px; }
            .consultation-fee { font-size: 16px; }
        }
        
        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Error States */
        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .has-error .form-control {
            border-color: #dc3545;
        }
        
        .has-error .error-message {
            display: block;
        }
    </style>
</head>

<body>
    <?php include("../header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row mb-5">
                 <!-- Sidebar -->
               <div class="col-md-3">
                   <?php include("sidebar.php") ?>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <div class="main-content">
                        <!-- Page Header -->
                        <div class="mb-4">
                            <h2 class="page-title">Book Appointment</h2>
                            <p class="page-subtitle">Quick and easy appointment booking with top doctors</p>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h3><?= count($upcoming_appointments) ?></h3>
                                <p>Upcoming Appointments</p>
                            </div>
                            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <h3><?= count($popular_doctors) ?></h3>
                                <p>Available Doctors</p>
                            </div>
                            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                <h3>24/7</h3>
                                <p>Support Available</p>
                            </div>
                            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <h3>30</h3>
                                <p>Days in Advance</p>
                            </div>
                        </div>
                        
                        <!-- Quick Booking Form -->
                        <div class="booking-form-container">
                            <h5 class="booking-form-title">Quick Book Appointment</h5>
                            <form id="quickBookingForm" class="booking-form">
                                <div class="form-group">
                                    <label class="form-label">Select Doctor</label>
                                    <select class="form-control form-select" id="doctorSelect" required>
                                        <option value="">Choose Doctor</option>
                                        <?php foreach ($popular_doctors as $doctor): ?>
                                            <option value="<?= $doctor['id'] ?>" data-fee="<?= $doctor['consultation_fee'] ?>">
                                                Dr. <?= htmlspecialchars($doctor['name']) ?> - <?= $doctor['specialization'] ?> (₹<?= number_format($doctor['consultation_fee']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="error-message" id="doctorError"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Appointment Date</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="appointmentDate" 
                                           min="<?= $min_date ?>" 
                                           max="<?= $max_date ?>"
                                           required>
                                    <div class="error-message" id="dateError"></div>
                                </div>
                                
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label">Available Time Slots</label>
                                    <div id="timeSlotsContainer" class="time-slots-container">
                                        <div class="time-slot booked" style="grid-column: span 4; cursor: default;">
                                            Select doctor and date first
                                        </div>
                                    </div>
                                    <input type="hidden" id="selectedTime">
                                    <div class="error-message" id="timeError"></div>
                                </div>
                                
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label">Purpose of Visit</label>
                                    <textarea class="form-control" 
                                              id="purpose" 
                                              rows="2" 
                                              placeholder="Briefly describe the reason for your appointment..."
                                              required></textarea>
                                    <div class="error-message" id="purposeError"></div>
                                </div>
                                
                                <button type="submit" class="btn-book" id="bookBtn">
                                    <i class="fa fa-calendar-check"></i> Book Appointment
                                </button>
                            </form>
                        </div>
                        
                        <!-- Popular Doctors -->
                        <div class="mb-4">
                            <h4 class="mb-3" style="color: #2c5aa0;">Popular Doctors</h4>
                            <div class="doctors-grid" id="doctorsGrid">
                                <?php foreach ($popular_doctors as $doctor): ?>
                                    <div class="doctor-card">
                                        <div class="doctor-header">
                                            <?php if (!empty($doctor['profile_image'])): ?>
                                                <img src="<?= $site . 'admin/' . htmlspecialchars($doctor['profile_image']) ?>" 
                                                     alt="Dr. <?= htmlspecialchars($doctor['name']) ?>" 
                                                     class="doctor-avatar">
                                            <?php else: ?>
                                                <div class="doctor-avatar bg-light d-flex align-items-center justify-content-center mx-auto">
                                                    <i class="fa fa-user-md text-muted fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h5 class="doctor-name">Dr. <?= htmlspecialchars($doctor['name']) ?></h5>
                                            <p class="doctor-specialization"><?= htmlspecialchars($doctor['specialization']) ?></p>
                                        </div>
                                        
                                        <div class="doctor-body">
                                            <div class="doctor-info">
                                                <i class="fa fa-graduation-cap"></i>
                                                <span><?= $doctor['experience_years'] ?>+ years experience</span>
                                            </div>
                                            <div class="doctor-info">
                                                <i class="fa fa-star"></i>
                                                <span><?= number_format($doctor['rating'], 1) ?> Rating</span>
                                            </div>
                                            <div class="doctor-info">
                                                <i class="fa fa-language"></i>
                                                <span><?= $doctor['languages'] ?: 'English' ?></span>
                                            </div>
                                            <div class="doctor-info">
                                                <i class="fa fa-history"></i>
                                                <span><?= $doctor['recent_bookings'] ?> recent bookings</span>
                                            </div>
                                        </div>
                                        
                                        <div class="doctor-footer">
                                            <div class="consultation-fee">
                                                ₹<?= number_format($doctor['consultation_fee']) ?>
                                            </div>
                                            <button class="btn-select" onclick="selectDoctor(<?= $doctor['id'] ?>, '<?= htmlspecialchars($doctor['name']) ?>', <?= $doctor['consultation_fee'] ?>)">
                                                <i class="fa fa-check"></i> Select
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Upcoming Appointments -->
                        <?php if (!empty($upcoming_appointments)): ?>
                            <div class="appointments-list">
                                <h4 class="mb-3" style="color: #2c5aa0;">Upcoming Appointments</h4>
                                <?php foreach ($upcoming_appointments as $appointment): 
                                    $status_class = strtolower($appointment['status']);
                                ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <div class="appointment-day">
                                                <?= date('d', strtotime($appointment['appointment_date'])) ?>
                                            </div>
                                            <div class="appointment-month">
                                                <?= date('M', strtotime($appointment['appointment_date'])) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="appointment-details">
                                            <h5 class="appointment-doctor">
                                                Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                                            </h5>
                                            <p class="appointment-time">
                                                <i class="fa fa-clock"></i> <?= $appointment['formatted_time'] ?>
                                            </p>
                                            <p class="appointment-purpose">
                                                <?= htmlspecialchars(substr($appointment['purpose'], 0, 50)) ?>
                                                <?= strlen($appointment['purpose']) > 50 ? '...' : '' ?>
                                            </p>
                                        </div>
                                        
                                        <div class="appointment-status">
                                            <span class="badge-status badge-<?= $status_class ?>">
                                                <?= ucfirst($appointment['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include("../footer.php") ?>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Booked Successfully!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="fa fa-check-circle text-success fa-4x"></i>
                    </div>
                    <h4 class="mb-3" id="successDoctorName"></h4>
                    <p class="mb-2" id="successDate"></p>
                    <p class="mb-2" id="successTime"></p>
                    <p class="mb-3" id="successPurpose"></p>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> Appointment ID: <strong id="successAppointmentId"></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="my-doctor-appointments.php" class="btn btn-primary">View All Appointments</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= $site ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedDoctor = null;
        let selectedTimeValue = null;
        
        // Toggle mobile menu
        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuBtn.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Select doctor from popular doctors list
        function selectDoctor(doctorId, doctorName, fee) {
            const doctorSelect = document.getElementById('doctorSelect');
            doctorSelect.value = doctorId;
            selectedDoctor = doctorId;
            
            // Clear errors
            clearError('doctorError');
            document.getElementById('doctorSelect').parentElement.classList.remove('has-error');
            
            // Update UI to show selected doctor
            document.querySelectorAll('.doctor-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Highlight the selected doctor card
            const doctorCards = document.querySelectorAll('.btn-select');
            doctorCards.forEach(btn => {
                if (btn.getAttribute('onclick').includes(doctorId)) {
                    btn.closest('.doctor-card').classList.add('selected');
                    btn.innerHTML = '<i class="fa fa-check"></i> Selected';
                    btn.classList.add('selected');
                }
            });
            
            // Clear time slots if date is selected
            const dateInput = document.getElementById('appointmentDate');
            if (dateInput.value) {
                loadTimeSlots();
            }
            
            // Show a toast notification
            showToast(`Dr. ${doctorName} selected`);
        }
        
        // Load time slots when date is selected
        document.getElementById('appointmentDate').addEventListener('change', loadTimeSlots);
        
        // Load available time slots
        async function loadTimeSlots() {
            const doctorId = document.getElementById('doctorSelect').value;
            const date = document.getElementById('appointmentDate').value;
            
            if (!doctorId || !date) {
                return;
            }
            
            // Show loading state
            const container = document.getElementById('timeSlotsContainer');
            container.innerHTML = '<div class="time-slot booked" style="grid-column: span 4;">Loading time slots...</div>';
            
            try {
                const response = await fetch(`my-bookings.php?get_time_slots=1&doctor_id=${doctorId}&date=${date}`);
                const timeSlots = await response.json();
                
                if (timeSlots.length === 0) {
                    container.innerHTML = '<div class="time-slot booked" style="grid-column: span 4;">No available slots for this date</div>';
                    return;
                }
                
                container.innerHTML = '';
                timeSlots.forEach(slot => {
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-slot';
                    timeSlot.textContent = slot.time;
                    timeSlot.dataset.value = slot.value;
                    timeSlot.onclick = () => selectTimeSlot(timeSlot, slot.value);
                    container.appendChild(timeSlot);
                });
                
                // Clear previous selection
                selectedTimeValue = null;
                document.getElementById('selectedTime').value = '';
                clearError('timeError');
                
            } catch (error) {
                console.error('Error loading time slots:', error);
                container.innerHTML = '<div class="time-slot booked" style="grid-column: span 4;">Error loading slots</div>';
            }
        }
        
        // Select time slot
        function selectTimeSlot(element, timeValue) {
            // Remove selected class from all time slots
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Add selected class to clicked slot
            element.classList.add('selected');
            selectedTimeValue = timeValue;
            document.getElementById('selectedTime').value = selectedTimeValue;
            
            // Clear error
            clearError('timeError');
        }
        
        // Clear error message
        function clearError(errorId) {
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.parentElement.classList.remove('has-error');
            }
        }
        
        // Show error message
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.textContent = message;
                field.parentElement.classList.add('has-error');
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Handle form submission
        document.getElementById('quickBookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Get form data
            const doctorId = document.getElementById('doctorSelect').value;
            const appointmentDate = document.getElementById('appointmentDate').value;
            const appointmentTime = document.getElementById('selectedTime').value;
            const purpose = document.getElementById('purpose').value.trim();
            
            // Clear previous errors
            ['doctorError', 'dateError', 'timeError', 'purposeError'].forEach(clearError);
            
            // Validate form
            let isValid = true;
            
            if (!doctorId) {
                showError('doctorError', 'Please select a doctor');
                isValid = false;
            }
            
            if (!appointmentDate) {
                showError('dateError', 'Please select appointment date');
                isValid = false;
            }
            
            if (!appointmentTime) {
                showError('timeError', 'Please select appointment time');
                isValid = false;
            }
            
            if (!purpose) {
                showError('purposeError', 'Please mention the purpose of visit');
                isValid = false;
            } else if (purpose.length < 10) {
                showError('purposeError', 'Please provide more details (minimum 10 characters)');
                isValid = false;
            }
            
            if (!isValid) {
                return;
            }
            
            // Disable submit button and show loading
            const submitBtn = document.getElementById('bookBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Booking...';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('doctor_id', doctorId);
                formData.append('appointment_date', appointmentDate);
                formData.append('appointment_time', appointmentTime);
                formData.append('purpose', purpose);
                formData.append('book_appointment', '1');
                
                const response = await fetch('my-bookings.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success modal
                    document.getElementById('successDoctorName').textContent = 
                        `Appointment with Dr. ${result.details.doctor_name}`;
                    document.getElementById('successDate').textContent = 
                        `Date: ${result.details.formatted_date}`;
                    document.getElementById('successTime').textContent = 
                        `Time: ${result.details.formatted_time}`;
                    document.getElementById('successPurpose').textContent = 
                        `Purpose: ${result.details.purpose.substring(0, 50)}${result.details.purpose.length > 50 ? '...' : ''}`;
                    document.getElementById('successAppointmentId').textContent = 
                        `AP${String(result.appointment_id).padStart(6, '0')}`;
                    
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Reset form
                    this.reset();
                    selectedDoctor = null;
                    selectedTimeValue = null;
                    document.getElementById('timeSlotsContainer').innerHTML = 
                        '<div class="time-slot booked" style="grid-column: span 4;">Select doctor and date first</div>';
                    
                    // Reset doctor cards
                    document.querySelectorAll('.doctor-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    document.querySelectorAll('.btn-select').forEach(btn => {
                        btn.innerHTML = '<i class="fa fa-check"></i> Select';
                        btn.classList.remove('selected');
                    });
                    
                } else {
                    // Show validation errors
                    if (result.errors) {
                        Object.entries(result.errors).forEach(([field, message]) => {
                            const fieldMap = {
                                'doctor_id': 'doctorError',
                                'appointment_date': 'dateError',
                                'appointment_time': 'timeError',
                                'purpose': 'purposeError',
                                'general': null
                            };
                            
                            if (fieldMap[field]) {
                                showError(fieldMap[field], message);
                            } else if (field === 'general') {
                                showToast(message, 'error');
                            }
                        });
                    }
                }
                
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            } finally {
                // Restore submit button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Auto-select today's date
        document.getElementById('appointmentDate').value = '<?= date('Y-m-d') ?>';
        
        // Close modal and refresh page
        document.getElementById('successModal').addEventListener('hidden.bs.modal', function () {
            location.reload();
        });
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .doctor-card.selected {
                border-color: #2c5aa0;
                box-shadow: 0 5px 20px rgba(44, 90, 160, 0.2);
            }
            
            .btn-select.selected {
                background: #28a745;
            }
            
            .toast-notification {
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>