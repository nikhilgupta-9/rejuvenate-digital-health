<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload   = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

// Get every individual appointment (not aggregated) so each one can render
// as its own time-slotted event — list view / day view then behave like a
// real calendar instead of a single "N Appts" badge per day.
$sql = "
    SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.appointment_type,
        COALESCE(u.name, sm.name, a.patient_name, 'Patient') as patient_name,
        a.status,
        a.purpose
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN school_members sm ON a.school_member_id = sm.id
    WHERE a.doctor_id = ?
    AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY a.appointment_date, a.appointment_time
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$STATUS_COLORS = [
    'pending'   => '#f59e0b',
    'approved'  => '#0C74C5',
    'confirmed' => '#0C74C5',
    'completed' => '#10b981',
    'rejected'  => '#ef4444',
    'no_show'   => '#6b7280',
];

$calendar_events = [];
while ($row = $result->fetch_assoc()) {
    $start = $row['appointment_date'] . 'T' . $row['appointment_time'];
    $end   = date('Y-m-d\TH:i:s', strtotime($start . ' +30 minutes'));
    $status = strtolower($row['status']);
    $calendar_events[] = [
        'id'    => (int) $row['id'],
        'title' => $row['patient_name'],
        'start' => $start,
        'end'   => $end,
        'color' => $STATUS_COLORS[$status] ?? '#6b7280',
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointments Calendar — REJUVENATE Doctor Portal</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>doctor/assets/doctor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <style>
        .calendar-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 14px rgba(0,0,0,.06);
            padding: 24px;
            margin-bottom: 24px;
        }
        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0C74C5;
        }
        .fc .fc-button-primary {
            background-color: #0C74C5;
            border-color: #0C74C5;
            text-transform: capitalize;
            font-weight: 600;
            font-size: .85rem;
            border-radius: 6px;
        }
        .fc .fc-button-primary:hover, .fc .fc-button-primary:focus {
            background-color: #085a99;
            border-color: #085a99;
        }
        .fc .fc-button-primary:disabled {
            background-color: #a0c4e2;
            border-color: #a0c4e2;
        }
        .fc-daygrid-event {
            border-radius: 4px;
            padding: 2px 6px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
        }
        .fc-event {
            border: none;
            transition: transform .1s, box-shadow .1s;
        }
        .fc-event:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
            filter: brightness(.95);
        }
        .fc-timegrid-event {
            border-radius: 6px;
        }
        .fc .fc-day-today {
            background-color: rgba(12,116,197,.06) !important;
        }
        .fc .fc-timegrid-now-indicator-line,
        .fc .fc-timegrid-now-indicator-arrow {
            border-color: #ef4444;
        }
        .fc .fc-col-header-cell-cushion,
        .fc .fc-daygrid-day-number {
            color: #374151;
        }
        .cal-legend {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            font-size: .82rem;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        .cal-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        @media (max-width: 768px) {
            .calendar-card {
                padding: 14px;
            }
            .fc .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }
            .fc .fc-toolbar-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <?php
    $sidebar_active = 'reports';
    include __DIR__ . '/inc/sidebar.php';
    ?>
    <main class="doctor-content">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h1 class="h4 fw-bold mb-1" style="color:#0C74C5;">Appointments Calendar</h1>
                <p class="text-muted small mb-0">Overview of scheduled patient visits, OPD bookings and follow-ups</p>
            </div>
            <div class="d-flex gap-2">
                <a href="appointments.php" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-list me-1"></i> List View
                </a>
                <a href="manage-schedule.php" class="btn btn-primary btn-sm" style="background:#0C74C5;border-color:#0C74C5;">
                    <i class="fa fa-clock me-1"></i> Manage Timings
                </a>
            </div>
        </div>

        <div class="calendar-card">
            <div id="calendar"></div>

            <div class="cal-legend">
                <span class="text-muted fw-bold">Legend:</span>
                <span><span class="cal-legend-dot" style="background:#f59e0b;"></span> Pending Approval</span>
                <span><span class="cal-legend-dot" style="background:#0C74C5;"></span> Approved / Confirmed</span>
                <span><span class="cal-legend-dot" style="background:#10b981;"></span> Completed</span>
                <span><span class="cal-legend-dot" style="background:#ef4444;"></span> Rejected / Cancelled</span>
            </div>
        </div>
    </main>

    <!-- Event detail popup — same shape as Google Calendar's click-to-preview card -->
    <div class="modal fade" id="apptDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-calendar-check me-2" style="color:#0C74C5;"></i>Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="apptDetailBody">
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-spinner fa-spin me-1"></i> Loading…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var isMobile = window.innerWidth < 768;
            var detailModalEl = document.getElementById('apptDetailModal');
            var detailModal = new bootstrap.Modal(detailModalEl);
            var detailBody = document.getElementById('apptDetailBody');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: isMobile ? 'listWeek' : 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: isMobile ? 'listWeek,dayGridMonth' : 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: 'Today',
                    month: 'Month',
                    week: 'Week',
                    day: 'Day',
                    list: 'Schedule'
                },
                height: 'auto',
                nowIndicator: true,
                dayMaxEvents: true,
                slotMinTime: '07:00:00',
                slotMaxTime: '22:00:00',
                slotDuration: '00:30:00',
                allDaySlot: false,
                eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
                events: <?= json_encode($calendar_events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    detailBody.innerHTML = '<div class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Loading…</div>';
                    detailModal.show();
                    fetch('get-appointment-details.php?id=' + info.event.id)
                        .then(r => r.text())
                        .then(html => { detailBody.innerHTML = html; })
                        .catch(() => {
                            detailBody.innerHTML = '<div class="alert alert-danger">Could not load appointment details.</div>';
                        });
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>
