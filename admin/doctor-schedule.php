<?php
include "functions.php";

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Active doctors for the picker
$doctors = [];
$dres = $conn->query("SELECT id, name, specialization FROM doctors WHERE status = 'Active' ORDER BY name ASC");
if ($dres) {
    while ($row = $dres->fetch_assoc()) {
        $doctors[] = $row;
    }
}

$selected_doctor_id = (int) ($_GET['doctor_id'] ?? 0);
$selected_doctor = null;
$schedules_by_day = [];

if ($selected_doctor_id) {
    $stmt = $conn->prepare("SELECT id, name, specialization FROM doctors WHERE id = ? AND status = 'Active'");
    $stmt->bind_param("i", $selected_doctor_id);
    $stmt->execute();
    $selected_doctor = $stmt->get_result()->fetch_assoc();

    if ($selected_doctor) {
        $sstmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ?");
        $sstmt->bind_param("i", $selected_doctor_id);
        $sstmt->execute();
        $sres = $sstmt->get_result();
        while ($row = $sres->fetch_assoc()) {
            $schedules_by_day[$row['day_of_week']] = $row;
        }
    } else {
        $selected_doctor_id = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Doctor Schedule Management | Admin Dashboard</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    <!-- styles in assets/css/colors/default.css -->
</head>

<body class="crm_body_bg">

    <?php include "header.php"; ?>
    <section class="main_content dashboard_part large_header_bg">

        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h2 class="mb-0 fw-bold">Doctor Schedule Management</h2>
                                        <p class="text-muted mb-0 small">Set each doctor's weekly consulting hours and slot length — used to generate available booking slots</p>
                                    </div>
                                    <div class="mt-2 mt-sm-0">
                                        <select id="doctorSelect" class="form-control nice-select">
                                            <option value="">Select a doctor…</option>
                                            <?php foreach ($doctors as $doc): ?>
                                                <option value="<?= (int) $doc['id'] ?>" <?= $selected_doctor_id === (int) $doc['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($doc['name']) ?><?= $doc['specialization'] ? ' — ' . htmlspecialchars($doc['specialization']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">

                                <!-- Success/Error Messages -->
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show mx-3 mt-2" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?= htmlspecialchars($_SESSION['success']) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['success']); ?>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show mx-3 mt-2" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?= htmlspecialchars($_SESSION['error']) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['error']); ?>
                                <?php endif; ?>

                                <?php if (!$selected_doctor_id): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-user-md fa-3x text-muted mb-3 d-block" style="opacity:.3;"></i>
                                        <h5 class="text-muted">Select a doctor above to view or edit their weekly schedule</h5>
                                    </div>
                                <?php else: ?>

                                    <div class="d-flex align-items-center mb-3 mx-1">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                                            <i class="fas fa-user-md text-primary"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?= htmlspecialchars($selected_doctor['name']) ?></h5>
                                            <small class="text-muted"><?= htmlspecialchars($selected_doctor['specialization'] ?: 'General Practice') ?></small>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover tbl-admin tbl-cards schedule-table">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Day</th>
                                                    <th scope="col">Consulting Hours</th>
                                                    <th scope="col">Slot Duration</th>
                                                    <th scope="col">Availability</th>
                                                    <th scope="col">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($days as $day): $sc = $schedules_by_day[$day] ?? null; ?>
                                                    <tr>
                                                        <td data-label="Day" class="day-label"><?= $day ?></td>
                                                        <td data-label="Consulting Hours">
                                                            <?php if ($sc): ?>
                                                                <?= date('h:i A', strtotime($sc['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($sc['end_time'])) ?>
                                                            <?php else: ?>
                                                                <span class="not-configured">Not configured</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="Slot Duration"><?= $sc ? (int) $sc['slot_duration_minutes'] . ' min' : '—' ?></td>
                                                        <td data-label="Availability">
                                                            <?php if ($sc): ?>
                                                                <span class="pill <?= $sc['is_available'] ? 'pill-success' : 'pill-danger' ?>">
                                                                    <?= $sc['is_available'] ? 'Available' : 'Unavailable' ?>
                                                                </span>
                                                            <?php else: ?>
                                                                &mdash;
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="Action">
                                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal-<?= $day ?>">
                                                                <i class="fas <?= $sc ? 'fa-edit' : 'fa-plus' ?> me-1"></i><?= $sc ? 'Edit' : 'Add' ?>
                                                            </button>
                                                            <?php if ($sc): ?>
                                                                <form method="POST" action="functions.php" class="d-inline" onsubmit="return confirm('Remove the <?= $day ?> schedule for this doctor?');">
                                                                    <input type="hidden" name="delete_doctor_schedule" value="1">
                                                                    <input type="hidden" name="schedule_id" value="<?= (int) $sc['id'] ?>">
                                                                    <input type="hidden" name="doctor_id" value="<?= $selected_doctor_id ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <!-- Edit/Add Modal -->
                                                    <div class="modal fade" id="scheduleModal-<?= $day ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="POST" action="functions.php">
                                                                    <div class="modal-header bg-primary text-white">
                                                                        <h5 class="modal-title"><i class="fas fa-clock me-2"></i><?= $day ?> Schedule</h5>
                                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="save_doctor_schedule" value="1">
                                                                        <input type="hidden" name="doctor_id" value="<?= $selected_doctor_id ?>">
                                                                        <input type="hidden" name="day_of_week" value="<?= $day ?>">

                                                                        <div class="row mb-3">
                                                                            <div class="col-6">
                                                                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                                                                <input type="time" name="start_time" class="form-control" value="<?= $sc ? substr($sc['start_time'], 0, 5) : '09:00' ?>" required>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="form-label">End Time <span class="text-danger">*</span></label>
                                                                                <input type="time" name="end_time" class="form-control" value="<?= $sc ? substr($sc['end_time'], 0, 5) : '17:00' ?>" required>
                                                                            </div>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Slot Duration</label>
                                                                            <select name="slot_duration_minutes" class="form-control">
                                                                                <?php foreach ([15, 20, 30, 45, 60] as $mins): ?>
                                                                                    <option value="<?= $mins ?>" <?= ($sc ? (int) $sc['slot_duration_minutes'] : 30) === $mins ? 'selected' : '' ?>>
                                                                                        <?= $mins ?> minutes
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>

                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="is_available" id="avail-<?= $day ?>" <?= (!$sc || $sc['is_available']) ? 'checked' : '' ?>>
                                                                            <label class="form-check-label" for="avail-<?= $day ?>">Doctor is available on this day</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-primary">
                                                                            <i class="fas fa-save me-1"></i>Save Schedule
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include "footer.php"; ?>

    <script>
        document.getElementById('doctorSelect').addEventListener('change', function () {
            window.location.href = 'doctor-schedule.php' + (this.value ? '?doctor_id=' + this.value : '');
        });
    </script>
</body>

</html>
