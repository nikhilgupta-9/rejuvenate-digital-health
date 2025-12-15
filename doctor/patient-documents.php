<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

session_start();
if (!isset($_SESSION['doctor_logged_in'])) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

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
</head>
<body>
    <?php include("../header.php") ?>
    
    <div class="container mt-4">
        <h2>Documents for <?= htmlspecialchars($patient['name']) ?></h2>
        
        <div class="row">
            <?php while ($doc = $docs_result->fetch_assoc()): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6><?= htmlspecialchars($doc['document_name']) ?></h6>
                            <small>Uploaded: <?= date('d/m/Y H:i', strtotime($doc['uploaded_at'])) ?></small><br>
                            <a href="<?= $site . $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                View Document
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <?php include("../footer.php") ?>
</body>
</html>