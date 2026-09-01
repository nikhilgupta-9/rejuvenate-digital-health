<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
require_once(__DIR__ . "/auth/guard.php");

$jwt_doctor = doctor_jwt_guard();
$doctor_id  = (int) ($jwt_doctor['doctor_id'] ?? $jwt_doctor['sub'] ?? 0);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$slot_options = [10, 15, 20, 30, 45, 60];

$success_message = '';
$error_message   = '';

/* ── Save weekly schedule ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $errors = [];
    $saved  = 0;

    foreach ($days as $day) {
        $available = isset($_POST['available'][$day]) ? 1 : 0;
        $start     = trim($_POST['start_time'][$day] ?? '');
        $end       = trim($_POST['end_time'][$day] ?? '');
        $dur       = (int) ($_POST['slot_duration'][$day] ?? 30);
        if (!in_array($dur, $slot_options, true)) $dur = 30;

        // Nothing entered and the day is off → make sure no stale row keeps it "open"
        if (!$available && $start === '' && $end === '') {
            $del = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
            $del->bind_param('is', $doctor_id, $day);
            $del->execute();
            continue;
        }

        // Times are required whenever the row is kept
        if ($start === '' || $end === '') {
            if ($available) $errors[] = "$day: enter both start and end time.";
            continue;
        }
        if (strtotime($end) <= strtotime($start)) {
            $errors[] = "$day: end time must be after start time.";
            continue;
        }

        $start_s = $start . ':00';
        $end_s   = $end . ':00';

        $chk = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
        $chk->bind_param('is', $doctor_id, $day);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();

        if ($row) {
            $u = $conn->prepare("UPDATE doctor_schedules
                SET start_time = ?, end_time = ?, slot_duration_minutes = ?, is_available = ?
                WHERE id = ?");
            $u->bind_param('ssiii', $start_s, $end_s, $dur, $available, $row['id']);
            $u->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO doctor_schedules
                (doctor_id, day_of_week, start_time, end_time, slot_duration_minutes, is_available)
                VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param('isssii', $doctor_id, $day, $start_s, $end_s, $dur, $available);
            $ins->execute();
        }
        $saved++;
    }

    if ($errors) {
        $error_message = implode(' ', $errors);
    }
    if ($saved && !$errors) {
        $success_message = 'Weekly schedule updated. Your booking slots now follow these hours.';
    } elseif ($saved) {
        $success_message = 'Some days were saved.';
    }
}

/* ── Load current schedule ── */
$schedule = [];
$sres = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ?");
$sres->bind_param('i', $doctor_id);
$sres->execute();
$r = $sres->get_result();
while ($row = $r->fetch_assoc()) {
    $schedule[$row['day_of_week']] = $row;
}

/** How many slots a day yields — quick preview. */
function slot_count(string $start, string $end, int $dur): int
{
    $c = strtotime($start);
    $e = strtotime($end);
    if ($c === false || $e === false || $dur < 5) return 0;
    $n = 0;
    while ($c + $dur * 60 <= $e) {
        $n++;
        $c += $dur * 60;
    }
    return $n;
}

$sidebar_active = 'schedule';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Schedule — REJUVENATE Doctor Portal</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
<style>
    .ms-head h1 { font-size: 1.2rem; font-weight: 800; color: #1f2937; margin: 0; }
    .ms-head .sub { font-size: .82rem; color: #9ca3af; }
    .sched-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07); padding: 18px 20px; }
    .day-row { display: grid; grid-template-columns: 150px 1fr 1fr 150px 130px; gap: 12px; align-items: end;
               padding: 14px 0; border-bottom: 1px solid #f3f4f6; }
    .day-row:last-child { border-bottom: none; }
    .day-row .d-name { font-weight: 700; color: #1f2937; font-size: .9rem; }
    .day-row.is-off { opacity: .55; }
    .day-row label.mini { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #9ca3af; display: block; margin-bottom: 3px; }
    .day-row .form-control, .day-row .form-select { font-size: .85rem; padding: 6px 10px; border-radius: 8px; }
    .day-row .slot-preview { font-size: .72rem; color: #6b7280; }
    .avail-toggle { display: flex; align-items: center; gap: 7px; font-size: .82rem; font-weight: 600; color: #374151; }
    .avail-toggle input { width: 2.2em; height: 1.15em; }
    @media (max-width: 800px) {
        .day-row { grid-template-columns: 1fr 1fr; }
        .day-row .d-name-wrap { grid-column: 1 / -1; }
        .day-row .slot-preview-wrap { grid-column: 1 / -1; }
    }
</style>
</head>
<body>
<main class="doctor-content">

    <div class="ms-head mb-3">
        <h1>Manage Schedule</h1>
        <div class="sub">Set your weekly consulting hours &amp; slot length — patients can only book inside these windows</div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="sched-card">
        <input type="hidden" name="save_schedule" value="1">

        <?php foreach ($days as $day): $sc = $schedule[$day] ?? null;
            $on    = $sc ? (int) $sc['is_available'] === 1 : ($day !== 'Sunday');
            $start = $sc ? substr($sc['start_time'], 0, 5) : ($day === 'Sunday' ? '' : '09:00');
            $end   = $sc ? substr($sc['end_time'], 0, 5) : ($day === 'Sunday' ? '' : '17:00');
            $dur   = $sc ? (int) $sc['slot_duration_minutes'] : 30;
            $preview = ($start && $end) ? slot_count($start . ':00', $end . ':00', $dur) : 0;
        ?>
        <div class="day-row<?= $on ? '' : ' is-off' ?>" data-day="<?= $day ?>">
            <div class="d-name-wrap">
                <span class="d-name"><?= $day ?></span>
                <label class="avail-toggle mt-2">
                    <input type="checkbox" class="form-check-input avail-cb" name="available[<?= $day ?>]" value="1" <?= $on ? 'checked' : '' ?>>
                    <span class="cb-text"><?= $on ? 'Available' : 'Off' ?></span>
                </label>
            </div>
            <div>
                <label class="mini">Start</label>
                <input type="time" class="form-control" name="start_time[<?= $day ?>]" value="<?= htmlspecialchars($start) ?>">
            </div>
            <div>
                <label class="mini">End</label>
                <input type="time" class="form-control" name="end_time[<?= $day ?>]" value="<?= htmlspecialchars($end) ?>">
            </div>
            <div>
                <label class="mini">Slot length</label>
                <select class="form-select" name="slot_duration[<?= $day ?>]">
                    <?php foreach ($slot_options as $m): ?>
                        <option value="<?= $m ?>" <?= $dur === $m ? 'selected' : '' ?>><?= $m ?> min</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="slot-preview-wrap">
                <label class="mini">Slots/day</label>
                <div class="slot-preview"><span class="pv-num"><?= $preview ?></span> slots</div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Schedule</button>
        </div>
    </form>

    <p class="text-muted mt-3" style="font-size:.8rem;">
        <i class="fa fa-info-circle me-1"></i>
        Leave a day <strong>Off</strong> to stop taking bookings that day. Existing appointments are not affected.
    </p>

</main>

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.day-row').forEach(row => {
        const cb    = row.querySelector('.avail-cb');
        const start = row.querySelector('input[name^="start_time"]');
        const end   = row.querySelector('input[name^="end_time"]');
        const sel   = row.querySelector('select');
        const pv    = row.querySelector('.pv-num');
        const txt   = row.querySelector('.cb-text');

        function recalc() {
            row.classList.toggle('is-off', !cb.checked);
            txt.textContent = cb.checked ? 'Available' : 'Off';
            let n = 0;
            if (start.value && end.value) {
                const s = Date.parse('1970-01-01T' + start.value + ':00');
                const e = Date.parse('1970-01-01T' + end.value + ':00');
                const d = parseInt(sel.value, 10) * 60000;
                if (e > s && d > 0) { let c = s; while (c + d <= e) { n++; c += d; } }
            }
            pv.textContent = n;
        }
        [cb, start, end, sel].forEach(el => el.addEventListener('input', recalc));
    });
</script>
</body>
</html>
