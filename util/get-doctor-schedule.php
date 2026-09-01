<?php
/**
 * Public AJAX endpoint — GET ?doctor_id=<int>[&days=21]
 * Returns the doctor's weekly consulting pattern (from doctor_schedules) plus a
 * ready-to-render strip of the next N calendar dates, each flagged
 * available / unavailable so the booking UI stays in sync with the schedule.
 */
include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/function.php';

header('Content-Type: application/json');

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$span     = min(45, max(7, (int) ($_GET['days'] ?? 21)));

if ($doctorId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid doctor']);
    exit;
}

$chk = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
$chk->bind_param('i', $doctorId);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit;
}

$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

/* Weekly pattern */
$rows = [];
$cnt = $conn->query("SELECT COUNT(*) c FROM doctor_schedules WHERE doctor_id = " . $doctorId);
$configured = $cnt && (int) $cnt->fetch_assoc()['c'] > 0;

if ($configured) {
    $s = $conn->prepare("SELECT day_of_week, start_time, end_time, is_available
                         FROM doctor_schedules WHERE doctor_id = ?");
    $s->bind_param('i', $doctorId);
    $s->execute();
    $r = $s->get_result();
    while ($x = $r->fetch_assoc()) $rows[$x['day_of_week']] = $x;
}

$week      = [];
$rawEnd    = [];   // 'HH:MM:SS' end time per weekday, for the "today already over" check
foreach ($weekdays as $d) {
    if ($configured) {
        $row = $rows[$d] ?? null;
        $ok  = $row && (int) $row['is_available'] === 1;
        $week[$d] = [
            'available' => $ok,
            'start'     => $ok ? date('g:i A', strtotime($row['start_time'])) : null,
            'end'       => $ok ? date('g:i A', strtotime($row['end_time'])) : null,
        ];
        $rawEnd[$d] = $ok ? $row['end_time'] : null;
    } else {
        // No schedule configured — legacy default: every day, standard hours
        $week[$d]   = ['available' => true, 'start' => '9:00 AM', 'end' => '5:30 PM'];
        $rawEnd[$d] = '17:30:00';
    }
}

/* Human summary line */
$openDays = array_values(array_filter($weekdays, fn($d) => $week[$d]['available']));
if (count($openDays) === 7) {
    $daysTxt = 'All days';
} elseif ($openDays) {
    $daysTxt = implode(', ', array_map(fn($d) => substr($d, 0, 3), $openDays));
} else {
    $daysTxt = 'Not accepting bookings';
}
$hoursTxt = '';
foreach ($openDays as $d) {
    $hoursTxt = $week[$d]['start'] . ' – ' . $week[$d]['end'];
    break;
}
$summary = trim($daysTxt . ($hoursTxt ? '  ·  ' . $hoursTxt : ''));

/* Next N dates */
$dates = [];
$firstAvailable = null;
$today   = new DateTime('today');
$nowTime = date('H:i:s');
for ($i = 0; $i < $span; $i++) {
    $dt   = (clone $today)->modify("+$i day");
    $dow  = $dt->format('l');
    $avail = $week[$dow]['available'];
    // Today counts as available only if there's still time left before closing
    if ($avail && $i === 0 && $rawEnd[$dow] !== null && $nowTime >= $rawEnd[$dow]) {
        $avail = false;
    }
    if ($avail && !$firstAvailable) $firstAvailable = $dt->format('Y-m-d');
    $dates[] = [
        'date'      => $dt->format('Y-m-d'),
        'dow'       => substr($dow, 0, 3),
        'day'       => $dt->format('j'),
        'month'     => $dt->format('M'),
        'is_today'  => $i === 0,
        'available' => $avail,
    ];
}

echo json_encode([
    'success'         => true,
    'configured'      => $configured,
    'week'            => $week,
    'summary'         => $summary,
    'dates'           => $dates,
    'first_available' => $firstAvailable,
]);
