<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

session_start();
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
$patient = $patient_result->fetch_assoc();

// Get patient appointments
$appointments_sql = "SELECT * FROM appointments 
                     WHERE user_id = ? AND doctor_id = ? 
                     ORDER BY appointment_date DESC";
$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param('ii', $patient_id, $doctor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
?>