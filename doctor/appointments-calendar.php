<?php
include_once(__DIR__ . "/../config/connect.php");
session_start();

if (!isset($_SESSION['doctor_logged_in'])) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];

// Get appointments for calendar
$sql = "
    SELECT 
        a.appointment_date,
        a.appointment_time,
        u.name as patient_name,
        a.status,
        a.purpose,
        COUNT(*) as count
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.doctor_id = ? 
    AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY a.appointment_date, a.status
    ORDER BY a.appointment_date
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments_by_date = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['appointment_date'];
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = [];
    }
    $appointments_by_date[$date][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointments Calendar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
</head>
<body>
    <div id="calendar"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: [
                    <?php foreach ($appointments_by_date as $date => $apps): ?>
                    {
                        title: '<?= count($apps) ?> Appointments',
                        start: '<?= $date ?>',
                        color: '<?= 
                            array_filter($apps, function($a) { return $a['status'] == 'Pending'; }) ? '#ffc107' :
                            (array_filter($apps, function($a) { return $a['status'] == 'Confirmed'; }) ? '#0dcaf0' :
                            '#198754')
                        ?>',
                        url: 'appointments.php?date=<?= $date ?>'
                    },
                    <?php endforeach; ?>
                ]
            });
            calendar.render();
        });
    </script>
</body>
</html>