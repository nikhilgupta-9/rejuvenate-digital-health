<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// session_start();
if (!isset($_SESSION['doctor_logged_in']) || $_SESSION['doctor_logged_in'] !== true) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';

// Get doctor's profile image and details
$doctor_sql = "SELECT name, email, profile_image, phone FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor_data = $doctor_result->fetch_assoc();

$doctor_name = $doctor_data['name'] ?? 'Doctor';
$doctor_email = $doctor_data['email'] ?? '';
$doctor_profile_image = !empty($doctor_data['profile_image']) ? $doctor_data['profile_image'] : 'assets/img/dummy.png';
$doctor_phone = $doctor_data['phone'] ?? '';

$doctor_id = $_SESSION['doctor_id'];
$patient_id = intval($_GET['patient_id'] ?? 0);

// Get patient details
$patient_sql = "SELECT u.* FROM users u 
                INNER JOIN appointments a ON u.id = a.user_id 
                WHERE u.id = ? AND a.doctor_id = ? LIMIT 1";
$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param('ii', $patient_id, $doctor_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();
$patient = $patient_result->fetch_assoc();

// Get patient documents
$docs_sql = "SELECT * FROM patient_documents 
             WHERE patient_id = ? AND doctor_id = ? 
             ORDER BY uploaded_at DESC";
$docs_stmt = $conn->prepare($docs_sql);
$docs_stmt->bind_param('ii', $patient_id, $doctor_id);
$docs_stmt->execute();
$docs_result = $docs_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Patient Documents</title>
    <!-- Include your CSS files -->
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
</head>

<body>
    <?php include("../header.php") ?>

    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-5">
                <!-- Sidebar -->
                <div class="col-md-3">
                    <div class="sidebar" id="sidebarMenu">
                        <div class="text-center info-content">
                            <img src="<?= $doctor_profile_image ?>" class="userd-image">
                            <h5>Dr. <?= htmlspecialchars($doctor_name) ?></h5>
                            <p><?= htmlspecialchars($doctor_email) ?></p>
                            <p>Phone: <?= htmlspecialchars($doctor_phone) ?></p>
                            <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Profile</a>
                        </div>

                        <a href="<?= $site ?>doctor/doctor-dashboard.php">Dashboard</a>
                        <a href="<?= $site ?>doctor/my-patients.php" class="active">My Patients</a>
                        <a href="<?= $site ?>doctor/appointments.php">Appointments</a>
                        <a href="<?= $site ?>doctor/patient-form.pdf">Patient Form</a>
                        <a href="<?= $site ?>doctor/my-contact.php">Contact Us</a>
                        <a href="<?= $site ?>doctor/doctor-about.php">About Us</a>
                        <a href="<?= $site ?>doctor-logout.php">Logout</a>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>

                    <div class="row">
                        <?php while ($doc = $docs_result->fetch_assoc()): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card p-1">
                                    <h5>Documents for <?= htmlspecialchars($patient['name']) ?></h5>
                                    <div class="card-body">
                                        <h6><?= htmlspecialchars($doc['document_name']) ?></h6>
                                        <small>Uploaded: <?= date('d/m/Y H:i', strtotime($doc['uploaded_at'])) ?></small><br>
                                        <a href="<?=  $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                            View Document
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../footer.php") ?>
</body>

</html>