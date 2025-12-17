<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ".$site."login.php");
    exit();
}


$contact = contact_us();
$user_id = $_SESSION['user_id'];

// Initialize variables
$success_message = '';
$error_message = '';
$errors = [];

// Fetch user's appointments
$appointments = [];
$stmt = $conn->prepare("
    SELECT 
        a.*,
        d.name as doctor_name,
        d.specialization,
        d.degrees,
        d.consultation_fee,
        d.profile_image,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
        DATE_FORMAT(a.appointment_date, '%d/%m/%Y') as formatted_date
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.user_id = ? 
    ORDER BY 
        CASE 
            WHEN a.appointment_date > CURDATE() THEN 0
            WHEN a.appointment_date = CURDATE() AND a.appointment_time > CURTIME() THEN 1
            ELSE 2
        END,
        a.appointment_date DESC,
        a.appointment_time DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();

// Fetch available doctors
$doctors = [];
$doctor_stmt = $conn->prepare("
    SELECT id, name, specialization, degrees, consultation_fee, profile_image, 
           experience_years, rating, languages
    FROM doctors 
    WHERE status = 'active' AND is_approved = 1
    ORDER BY name ASC
");
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
while ($row = $doctor_result->fetch_assoc()) {
    $doctors[] = $row;
}
$doctor_stmt->close();

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    // Sanitize and validate input data
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    
    // Validation
    if ($doctor_id <= 0) {
        $errors['doctor_id'] = "Please select a doctor";
    }
    
    if (empty($appointment_date)) {
        $errors['appointment_date'] = "Please select appointment date";
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $errors['appointment_date'] = "Appointment date cannot be in the past";
    }
    
    if (empty($appointment_time)) {
        $errors['appointment_time'] = "Please select appointment time";
    }
    
    if (empty($purpose)) {
        $errors['purpose'] = "Please mention the purpose of visit";
    } elseif (strlen($purpose) < 10) {
        $errors['purpose'] = "Please provide more details about your visit";
    }
    
    // Check if time slot is available
    if (empty($errors)) {
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
            $errors['appointment_time'] = "This time slot is already booked. Please choose another time.";
        }
        $check_stmt->close();
    }
    
    // If no errors, save appointment
    if (empty($errors)) {
        $insert_stmt = $conn->prepare("
            INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, purpose, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $insert_stmt->bind_param("iisss", $user_id, $doctor_id, $appointment_date, $appointment_time, $purpose);
        
        if ($insert_stmt->execute()) {
            $success_message = "Appointment booked successfully!";
            
            // Refresh appointments list
            $stmt = $conn->prepare("
                SELECT 
                    a.*,
                    d.name as doctor_name,
                    d.specialization,
                    d.degrees,
                    d.consultation_fee,
                    d.profile_image,
                    TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
                    DATE_FORMAT(a.appointment_date, '%d/%m/%Y') as formatted_date
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.user_id = ? 
                ORDER BY 
                    CASE 
                        WHEN a.appointment_date > CURDATE() THEN 0
                        WHEN a.appointment_date = CURDATE() AND a.appointment_time > CURTIME() THEN 1
                        ELSE 2
                    END,
                    a.appointment_date DESC,
                    a.appointment_time DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointments = [];
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
            unset($_POST);
            $stmt->close();
        } else {
            $error_message = "Failed to book appointment. Please try again.";
        }
        $insert_stmt->close();
    } else {
        $error_message = "Please correct the errors below.";
    }
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
            
            // Refresh appointments list
            $stmt = $conn->prepare("
                SELECT 
                    a.*,
                    d.name as doctor_name,
                    d.specialization,
                    d.degrees,
                    d.consultation_fee,
                    d.profile_image,
                    TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
                    DATE_FORMAT(a.appointment_date, '%d/%m/%Y') as formatted_date
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.user_id = ? 
                ORDER BY 
                    CASE 
                        WHEN a.appointment_date > CURDATE() THEN 0
                        WHEN a.appointment_date = CURDATE() AND a.appointment_time > CURTIME() THEN 1
                        ELSE 2
                    END,
                    a.appointment_date DESC,
                    a.appointment_time DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointments = [];
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
            $stmt->close();
        } else {
            $error_message = "Failed to cancel appointment. Please try again.";
        }
        $cancel_stmt->close();
    } else {
        $error_message = "Appointment not found or you don't have permission to cancel it.";
    }
    $check_stmt->close();
    
    // Redirect to remove cancel_id from URL
    header("Location: book-appointment.php");
    exit();
}

// Get time slots for selected doctor and date (for AJAX)
if (isset($_GET['get_time_slots']) && isset($_GET['doctor_id']) && isset($_GET['date'])) {
    $doctor_id = intval($_GET['doctor_id']);
    $date = $conn->real_escape_string($_GET['date']);
    
    // Get doctor's working hours (you might want to add this to doctors table)
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
    <link rel="stylesheet" href="<?= $site ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .error { color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
        .is-invalid { border-color: #dc3545; }
        .alert { border-radius: 8px; }
        .profile-card { padding: 2rem; }
        .form-label { font-weight: 500; color: #333; margin-bottom: 0.5rem; }
        .appointment-card { 
            border: 1px solid #e9ecef; 
            border-radius: 10px; 
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
            background: white;
            transition: all 0.3s ease;
        }
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .appointment-card.upcoming { 
            border-left: 4px solid #28a745;
        }
        .appointment-card.pending { 
            border-left: 4px solid #ffc107;
        }
        .appointment-card.completed { 
            border-left: 4px solid #17a2b8;
        }
        .appointment-card.cancelled { 
            border-left: 4px solid #dc3545;
            opacity: 0.7;
        }
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .doctor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2c5aa0;
        }
        .time-slot {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            min-width: 100px;
        }
        .time-slot:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .time-slot.selected {
            background: #2c5aa0;
            color: white;
            border-color: #2c5aa0;
        }
        .time-slot.booked {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .sidebar { position: sticky; top: 20px; }
        .doctor-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .doctor-card:hover {
            border-color: #2c5aa0;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .doctor-card.selected {
            border-color: #2c5aa0;
            background: #f0f7ff;
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.2);
        }
        .consultation-fee {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1rem;
        }
        .rating {
            color: #ffc107;
            margin-right: 5px;
        }
        .mobile-menu-btn {
            display: none;
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .sidebar { display: none; }
            .sidebar.show { display: block; }
            .doctor-avatar { width: 60px; height: 60px; }
            .time-slot { min-width: 80px; padding: 6px 10px; }
        }
        
        .no-appointments {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        .no-appointments i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include("../header.php") ?>
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-3">
                   <?php include("sidebar.php") ?>
                </div>
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <button class="mobile-menu-btn" onclick="toggleMenu()">☰ Menu</button>
                    
                    <div class="profile-card shadow">
                        <h4 class="mb-4">Book Appointment</h4>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $success_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Book New Appointment Form -->
                        <div class="mb-5">
                            <h5 class="mb-3">Book New Appointment</h5>
                            <form method="POST" action="" novalidate>
                                <div class="row mt-4">
                                    <!-- Step 1: Select Doctor -->
                                    <div class="col-12 mb-4">
                                        <label class="form-label">Select Doctor <span class="text-danger">*</span></label>
                                        <?php if (!empty($doctors)): ?>
                                            <div class="row">
                                                <?php foreach ($doctors as $doctor): ?>
                                                    <div class="col-md-6">
                                                        <div class="doctor-card" onclick="selectDoctor(<?= $doctor['id'] ?>)">
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($doctor['profile_image'])): ?>
                                                                    <img src="<?= $site ."admin/". htmlspecialchars($doctor['profile_image']) ?>" 
                                                                         alt="<?= htmlspecialchars($doctor['name']) ?>" 
                                                                         class="doctor-avatar me-3">
                                                                <?php else: ?>
                                                                    <div class="doctor-avatar me-3 bg-light d-flex align-items-center justify-content-center">
                                                                        <i class="fa fa-user-md text-muted fa-2x"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div style="flex: 1;">
                                                                    <h6 class="mb-1">Dr. <?= htmlspecialchars($doctor['name']) ?></h6>
                                                                    <p class="mb-1 text-muted small">
                                                                        <?= htmlspecialchars($doctor['specialization']) ?>
                                                                    </p>
                                                                    <p class="mb-1 small">
                                                                        <span class="rating">
                                                                            <i class="fa fa-star"></i> <?= number_format($doctor['rating'], 1) ?>
                                                                        </span>
                                                                        <span class="text-muted">•</span>
                                                                        <span><?= $doctor['experience_years'] ?>+ years exp.</span>
                                                                    </p>
                                                                    <p class="mb-0 consultation-fee">
                                                                        ₹<?= number_format($doctor['consultation_fee']) ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <input type="radio" name="doctor_id" value="<?= $doctor['id'] ?>" 
                                                                   class="d-none doctor-radio" 
                                                                   id="doctor<?= $doctor['id'] ?>" 
                                                                   <?= (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doctor['id']) ? 'checked' : '' ?>
                                                                   required>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (isset($errors['doctor_id'])): ?>
                                                <div class="error"><?= $errors['doctor_id'] ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                No doctors available at the moment. Please check back later.
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Step 2: Select Date & Time -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                        <input type="date" 
                                               class="form-control <?= isset($errors['appointment_date']) ? 'is-invalid' : '' ?>" 
                                               name="appointment_date" 
                                               id="appointment_date"
                                               value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>"
                                               min="<?= $min_date ?>"
                                               max="<?= $max_date ?>"
                                               onchange="loadTimeSlots()"
                                               required>
                                        <?php if (isset($errors['appointment_date'])): ?>
                                            <div class="error"><?= $errors['appointment_date'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Available Time Slots <span class="text-danger">*</span></label>
                                        <div id="timeSlotsContainer">
                                            <div class="alert alert-info small">
                                                Select a doctor and date to view available time slots
                                            </div>
                                        </div>
                                        <input type="hidden" name="appointment_time" id="selected_time" 
                                               value="<?= htmlspecialchars($_POST['appointment_time'] ?? '') ?>">
                                        <?php if (isset($errors['appointment_time'])): ?>
                                            <div class="error"><?= $errors['appointment_time'] ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Step 3: Purpose -->
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Purpose of Visit <span class="text-danger">*</span></label>
                                        <textarea class="form-control <?= isset($errors['purpose']) ? 'is-invalid' : '' ?>" 
                                                  name="purpose" 
                                                  rows="4" 
                                                  placeholder="Please describe the reason for your appointment. This helps the doctor prepare for your visit."
                                                  required><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                                        <?php if (isset($errors['purpose'])): ?>
                                            <div class="error"><?= $errors['purpose'] ?></div>
                                        <?php endif; ?>
                                        <div class="form-text">
                                            Please provide details about your symptoms, concerns, or the reason for your visit.
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <button type="submit" name="book_appointment" class="btn btn-warning btn-lg">
                                            <i class="fa fa-calendar-check"></i> Book Appointment
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- My Appointments -->
                        <div class="my-appointments">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>My Appointments</h5>
                                <span class="badge bg-primary"><?= count($appointments) ?> Total</span>
                            </div>
                            
                            <?php if (empty($appointments)): ?>
                                <div class="no-appointments">
                                    <i class="fa fa-calendar-times"></i>
                                    <h4>No Appointments Yet</h4>
                                    <p>You haven't booked any appointments yet. Book your first appointment above!</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($appointments as $appointment): 
                                        $status_class = strtolower($appointment['status']);
                                    ?>
                                        <div class="col-md-6">
                                            <div class="appointment-card <?= $status_class ?>">
                                                <div class="d-flex align-items-start mb-3">
                                                    <?php if (!empty($appointment['profile_image'])): ?>
                                                        <img src="<?= $site ."admin/". htmlspecialchars($appointment['profile_image']) ?>" 
                                                             alt="Doctor" 
                                                             class="doctor-avatar me-3">
                                                    <?php else: ?>
                                                        <div class="doctor-avatar me-3 bg-light d-flex align-items-center justify-content-center">
                                                            <i class="fa fa-user-md text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-1"> <?= htmlspecialchars($appointment['doctor_name']) ?></h6>
                                                        <p class="mb-1 small text-muted">
                                                            <?= htmlspecialchars($appointment['specialization']) ?>
                                                        </p>
                                                        <p class="mb-1 small">
                                                            <?= htmlspecialchars($appointment['degrees']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <p class="mb-1">
                                                        <i class="fa fa-calendar text-primary me-2"></i>
                                                        <strong><?= $appointment['formatted_date'] ?></strong>
                                                    </p>
                                                    <p class="mb-2">
                                                        <i class="fa fa-clock text-primary me-2"></i>
                                                        <?= $appointment['formatted_time'] ?>
                                                    </p>
                                                    <p class="mb-0 small">
                                                        <i class="fa fa-rupee-sign text-success me-2"></i>
                                                        Consultation Fee: ₹<?= number_format($appointment['consultation_fee']) ?>
                                                    </p>
                                                </div>
                                                
                                                <?php if (!empty($appointment['purpose'])): ?>
                                                    <div class="mb-3">
                                                        <p class="mb-1 small text-muted">Purpose:</p>
                                                        <p class="mb-0 small"><?= htmlspecialchars(substr($appointment['purpose'], 0, 100)) ?><?= strlen($appointment['purpose']) > 100 ? '...' : '' ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="status-badge status-<?= $status_class ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                    
                                                    <div class="appointment-actions">
                                                        <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                                            <a href="book-appointment.php?cancel_id=<?= $appointment['id'] ?>" 
                                                               class="btn btn-sm btn-outline-danger" 
                                                               onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                                <i class="fa fa-times"></i> Cancel
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($appointment['status'] == 'confirmed'): ?>
                                                            <a href="#" class="btn btn-sm btn-outline-primary">
                                                                <i class="fa fa-video"></i> Join
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include("../footer.php") ?>
    
    <script src="<?= $site ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedDoctorId = null;
        let selectedTime = null;
        
        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        // Select doctor
        function selectDoctor(doctorId) {
            // Remove selected class from all doctor cards
            document.querySelectorAll('.doctor-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            const selectedCard = document.querySelector(`.doctor-card[onclick*="${doctorId}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
            
            // Set radio button as checked
            document.getElementById(`doctor${doctorId}`).checked = true;
            selectedDoctorId = doctorId;
            
            // Clear time slots if date is already selected
            const dateInput = document.getElementById('appointment_date');
            if (dateInput.value) {
                loadTimeSlots();
            }
        }
        
        // Load time slots based on selected doctor and date
        function loadTimeSlots() {
            const dateInput = document.getElementById('appointment_date');
            const date = dateInput.value;
            
            if (!selectedDoctorId || !date) {
                return;
            }
            
            // Show loading
            const container = document.getElementById('timeSlotsContainer');
            container.innerHTML = '<div class="alert alert-info small">Loading available time slots...</div>';
            
            // Fetch available time slots via AJAX
            fetch(`my-bookings.php?get_time_slots=1&doctor_id=${selectedDoctorId}&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        container.innerHTML = '<div class="alert alert-warning small">No available time slots for this date. Please select another date.</div>';
                        document.getElementById('selected_time').value = '';
                        return;
                    }
                    
                    container.innerHTML = '';
                    data.forEach(slot => {
                        const timeSlot = document.createElement('div');
                        timeSlot.className = 'time-slot';
                        timeSlot.textContent = slot.time;
                        timeSlot.dataset.value = slot.value;
                        timeSlot.onclick = () => selectTimeSlot(timeSlot, slot.value);
                        container.appendChild(timeSlot);
                    });
                    
                    // If there was a previously selected time, try to select it
                    const previousTime = document.getElementById('selected_time').value;
                    if (previousTime) {
                        const timeSlot = Array.from(container.querySelectorAll('.time-slot'))
                            .find(slot => slot.dataset.value === previousTime);
                        if (timeSlot) {
                            selectTimeSlot(timeSlot, previousTime);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading time slots:', error);
                    container.innerHTML = '<div class="alert alert-danger small">Error loading time slots. Please try again.</div>';
                });
        }
        
        // Select time slot
        function selectTimeSlot(element, timeValue) {
            // Remove selected class from all time slots
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Add selected class to clicked slot
            element.classList.add('selected');
            selectedTime = timeValue;
            document.getElementById('selected_time').value = selectedTime;
        }
        
        // Form validation before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const doctorSelected = document.querySelector('input[name="doctor_id"]:checked');
            const dateSelected = document.getElementById('appointment_date').value;
            const timeSelected = document.getElementById('selected_time').value;
            const purpose = document.querySelector('textarea[name="purpose"]').value.trim();
            
            let errors = [];
            
            if (!doctorSelected) {
                errors.push('Please select a doctor');
            }
            
            if (!dateSelected) {
                errors.push('Please select appointment date');
            }
            
            if (!timeSelected) {
                errors.push('Please select appointment time');
            }
            
            if (!purpose) {
                errors.push('Please mention the purpose of visit');
            } else if (purpose.length < 10) {
                errors.push('Please provide more details about your visit (minimum 10 characters)');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please correct the following errors:\n\n' + errors.join('\n'));
            }
        });
        
        // Initialize doctor selection if form was submitted with errors
        document.addEventListener('DOMContentLoaded', function() {
            const selectedDoctorRadio = document.querySelector('input[name="doctor_id"]:checked');
            if (selectedDoctorRadio) {
                selectDoctor(selectedDoctorRadio.value);
            }
            
            // If time was selected, select it
            const selectedTimeValue = document.getElementById('selected_time').value;
            if (selectedTimeValue) {
                const timeSlots = document.querySelectorAll('.time-slot');
                timeSlots.forEach(slot => {
                    if (slot.dataset.value === selectedTimeValue) {
                        selectTimeSlot(slot, selectedTimeValue);
                    }
                });
            }
            
            // Auto-close alerts after 5 seconds
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