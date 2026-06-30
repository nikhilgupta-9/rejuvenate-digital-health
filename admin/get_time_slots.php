<?php
session_start();
include_once "../config/connect.php";

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = intval($_POST['doctor_id']);
    $date = $conn->real_escape_string($_POST['date']);
    
    if (!$doctor_id || !$date) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }
    
    // Define available time slots (you can customize these)
    $timeSlots = [
        '09:00:00' => '09:00 AM',
        '09:30:00' => '09:30 AM',
        '10:00:00' => '10:00 AM',
        '10:30:00' => '10:30 AM',
        '11:00:00' => '11:00 AM',
        '11:30:00' => '11:30 AM',
        '12:00:00' => '12:00 PM',
        '14:00:00' => '02:00 PM',
        '14:30:00' => '02:30 PM',
        '15:00:00' => '03:00 PM',
        '15:30:00' => '03:30 PM',
        '16:00:00' => '04:00 PM',
        '16:30:00' => '04:30 PM',
        '17:00:00' => '05:00 PM',
        '17:30:00' => '05:30 PM'
    ];
    
    // Get booked appointments for this doctor on this date
    $bookedSlots = [];
    $check_sql = "
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status IN ('pending', 'confirmed')
    ";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bookedSlots[] = $row['appointment_time'];
    }
    $stmt->close();
    
    // Prepare response with time slots
    $availableSlots = [];
    foreach ($timeSlots as $time => $display) {
        $availableSlots[] = [
            'time' => $time,
            'display' => $display,
            'booked' => in_array($time, $bookedSlots)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'timeSlots' => $availableSlots
    ]);
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>