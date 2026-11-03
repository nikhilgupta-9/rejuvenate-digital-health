<?php
include_once(__DIR__ . "/../config/connect.php");
require_once __DIR__ . "/auth/guard.php";

if (!doctor_jwt_guard(true)) {
    http_response_code(401);
    die("Unauthorized access.");
}

$payload = doctor_jwt_guard(true);
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? $_SESSION['doctor_id'] ?? 0);
$appointment_id = intval($_GET['id'] ?? 0);

// Get appointment details
$sql = "
    SELECT 
        a.*,
        COALESCE(u.name, sm.name, a.patient_name, 'Patient') as patient_name,
        COALESCE(u.email, sm.email, a.patient_email) as patient_email,
        COALESCE(u.mobile, sm.phone, a.patient_phone) as patient_phone,
        COALESCE(u.profile_pic, sm.profile_pic) as patient_image,
        COALESCE(u.dob, sm.dob) as dob,
        COALESCE(u.gender, sm.gender, '—') as gender,
        COALESCE(u.blood_group, sm.blood_group) as blood_group,
        u.identification_number,
        u.emergency_contact,
        d.name as doctor_name,
        d.specialization,
        d.phone as doctor_phone,
        ua.house_no, ua.colony_area, ua.landmark,
        ua.city, ua.state, ua.zip_code
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN school_members sm ON a.school_member_id = sm.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN user_addresses ua ON u.id = ua.user_id AND ua.is_default = 1
    WHERE a.id = ? AND a.doctor_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $appointment_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $appointment = $result->fetch_assoc();
    $patient_age = '—';
    if (!empty($appointment['dob'])) {
        try {
            $dobObj = new DateTime($appointment['dob']);
            $patient_age = $dobObj->diff(new DateTime('today'))->y . ' years';
        } catch (Throwable $e) {}
    }
    ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Patient Information</h6>
                </div>
                <div class="card-body">
                    <?php if ($appointment['patient_image']): ?>
                        <img src="<?= BASE_URL . $appointment['patient_image'] ?>" 
                             class="rounded-circle mb-3" width="80" height="80" 
                             onerror="this.src='<?= BASE_URL ?>assets/img/dummy.png'">
                    <?php endif; ?>
                    <p><strong>Name:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
                    <p><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($appointment['patient_phone']) ?>"><?= htmlspecialchars($appointment['patient_phone']) ?></a></p>
                    <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($appointment['patient_email']) ?>"><?= htmlspecialchars($appointment['patient_email']) ?></a></p>
                    <p><strong>Gender:</strong> <?= htmlspecialchars($appointment['gender'] ?? '—') ?></p>
                    <p><strong>Age:</strong> <?= $patient_age ?></p>
                    <p><strong>Blood Group:</strong> <?= $appointment['blood_group'] ?? 'Not specified' ?></p>
                    <p><strong>Emergency Contact:</strong> <?= $appointment['emergency_contact'] ?? 'Not specified' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Appointment Details</h6>
                </div>
                <div class="card-body">
                    <p><strong>Appointment ID:</strong> #<?= $appointment_id ?></p>
                    <p><strong>Date:</strong> <?= date('d F, Y', strtotime($appointment['appointment_date'])) ?></p>
                    <p><strong>Time:</strong> <?= date('h:i A', strtotime($appointment['appointment_time'])) ?></p>
                    <p><strong>Purpose:</strong> <?= htmlspecialchars($appointment['purpose'] ?: 'General Consultation') ?></p>
                    <p><strong>Status:</strong> 
                        <?php
                        $st = strtolower($appointment['status'] ?? '');
                        $badgeClass = ($st === 'pending') ? 'warning' :
                                      (($st === 'approved' || $st === 'confirmed') ? 'info' :
                                      (($st === 'completed') ? 'success' : 'secondary'));
                        ?>
                        <span class="badge bg-<?= $badgeClass ?>">
                            <?= ucfirst($appointment['status']) ?>
                        </span>
                    </p>
                    <p><strong>Created:</strong> <?= date('d/m/Y H:i', strtotime($appointment['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($appointment['house_no'] || $appointment['city']): ?>
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Patient Address</h6>
        </div>
        <div class="card-body">
            <p>
                <?php 
                $address_parts = [];
                if ($appointment['house_no']) $address_parts[] = $appointment['house_no'];
                if ($appointment['colony_area']) $address_parts[] = $appointment['colony_area'];
                if ($appointment['landmark']) $address_parts[] = "Near " . $appointment['landmark'];
                if ($appointment['city']) $address_parts[] = $appointment['city'];
                if ($appointment['state']) $address_parts[] = $appointment['state'];
                if ($appointment['zip_code']) $address_parts[] = $appointment['zip_code'];
                
                echo implode(', ', array_filter($address_parts));
                ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="mt-3 text-center">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fa fa-print"></i> Print Details
        </button>
        <a href="patient-form.php?appointment_id=<?= $appointment_id ?>" class="btn btn-success">
            <i class="fa fa-file-medical"></i> Open Prescription
        </a>
    </div>
    <?php
} else {
    echo '<div class="alert alert-danger">Appointment not found or you don\'t have permission to view it.</div>';
}
?>