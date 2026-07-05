<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload   = doctor_jwt_guard();
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $appointment_type = trim($_POST['appointment_type'] ?? 'Clinic Visit');
    $purpose = trim($_POST['purpose'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$patient_id || !$appointment_date || !$appointment_time) {
        $error_message = 'Patient, date, and time are required.';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error_message = 'Appointment date cannot be in the past.';
    } else {
        // Verify this patient is linked to the doctor
        $chk = $conn->prepare("SELECT u.name, u.email, u.mobile FROM doctor_patients dp JOIN users u ON dp.patient_id = u.id WHERE dp.doctor_id = ? AND dp.patient_id = ? LIMIT 1");
        $chk->bind_param('ii', $doctor_id, $patient_id);
        $chk->execute();
        $patient = $chk->get_result()->fetch_assoc();

        if (!$patient) {
            $error_message = 'That patient is not linked to your panel.';
        } else {
            // Doctor is creating this directly, so it's already approved.
            $ins = $conn->prepare("
                INSERT INTO appointments
                    (user_id, doctor_id, appointment_date, appointment_time, purpose, notes,
                     appointment_type, patient_name, patient_email, patient_phone, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
            ");
            $ins->bind_param(
                'iissssssss',
                $patient_id, $doctor_id, $appointment_date, $appointment_time, $purpose, $notes,
                $appointment_type, $patient['name'], $patient['email'], $patient['mobile']
            );

            if ($ins->execute()) {
                header('Location: ' . BASE_URL . 'doctor/appointments.php?date=' . urlencode($appointment_date));
                exit;
            } else {
                $error_message = 'Failed to create appointment: ' . $conn->error;
            }
        }
    }
}

// Patients linked to this doctor
$patients_stmt = $conn->prepare("
    SELECT u.id, u.name, u.mobile
    FROM doctor_patients dp
    JOIN users u ON dp.patient_id = u.id
    WHERE dp.doctor_id = ?
    ORDER BY u.name
");
$patients_stmt->bind_param('i', $doctor_id);
$patients_stmt->execute();
$patients = $patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$preselect_patient = (int)($_GET['patient_id'] ?? 0);
$preselect_date = $_GET['date'] ?? '';

$sidebar_active = 'appointments';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Appointment — Rejuvenate</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
.profile-card{background:#fff;padding:25px;border-radius:10px;border:1px solid #dee2e6;max-width:640px;}
</style>
</head>
<body>
<main class="doctor-content">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Add New Appointment</h4>
    <a href="appointments.php" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left mr-1"></i> Back</a>
  </div>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <div class="profile-card shadow">
    <?php if (empty($patients)): ?>
      <div class="text-center py-4">
        <i class="fa fa-users fa-2x text-muted mb-2"></i>
        <p class="text-muted">You don't have any patients linked to your panel yet.</p>
        <a href="add-patient.php" class="btn btn-primary btn-sm"><i class="fa fa-plus mr-1"></i> Add a Patient</a>
      </div>
    <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label>Patient <span class="text-danger">*</span></label>
          <select name="patient_id" class="form-control" required>
            <option value="">-- Select Patient --</option>
            <?php foreach ($patients as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $preselect_patient === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['mobile'] ?? '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="col-md-6 form-group">
            <label>Date <span class="text-danger">*</span></label>
            <input type="date" name="appointment_date" class="form-control" required
                   min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($preselect_date) ?>">
          </div>
          <div class="col-md-6 form-group">
            <label>Time <span class="text-danger">*</span></label>
            <input type="time" name="appointment_time" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label>Appointment Type</label>
          <select name="appointment_type" class="form-control">
            <option value="Clinic Visit">Clinic Visit</option>
            <option value="Video Consultation">Video Consultation</option>
            <option value="Follow-up">Follow-up</option>
          </select>
        </div>
        <div class="form-group">
          <label>Purpose</label>
          <input type="text" name="purpose" class="form-control" placeholder="e.g. General consultation">
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-check mr-1"></i> Create Appointment</button>
        <a href="appointments.php" class="btn btn-outline-secondary">Cancel</a>
      </form>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
