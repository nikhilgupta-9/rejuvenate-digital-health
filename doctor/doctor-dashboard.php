<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// Start session and check doctor login
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

// Handle file upload for patients
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_file'])) {
    $patient_id = intval($_POST['patient_id']);
    
    if (isset($_FILES['patient_file']) && $_FILES['patient_file']['error'] == 0) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['patient_file']['type'];
        $file_name = $_FILES['patient_file']['name'];
        $file_tmp = $_FILES['patient_file']['tmp_name'];
        $file_size = $_FILES['patient_file']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 5242880) { // 5MB max
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = 'patient_doc_' . $patient_id . '_' . time() . '.' . $file_ext;
            $upload_path = '../uploads/patient_documents/' . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Insert document record into database
                $doc_sql = "INSERT INTO patient_documents (patient_id, doctor_id, document_name, file_path, file_type) 
                           VALUES (?, ?, ?, ?, ?)";
                $doc_stmt = $conn->prepare($doc_sql);
                $doc_stmt->bind_param('iisss', $patient_id, $doctor_id, $file_name, $upload_path, $file_type);
                
                if ($doc_stmt->execute()) {
                    $success_message = "Document uploaded successfully!";
                } else {
                    $error_message = "Failed to save document record.";
                }
            } else {
                $error_message = "Failed to upload file.";
            }
        } else {
            $error_message = "Invalid file type or size too large (max 5MB). Only PDF, JPG, PNG allowed.";
        }
    }
}

// Handle patient deletion
if (isset($_GET['delete_patient'])) {
    $patient_id = intval($_GET['delete_patient']);
    
    // Check if patient belongs to this doctor
    $check_sql = "SELECT u.id FROM users u 
                  INNER JOIN appointments a ON u.id = a.user_id 
                  WHERE u.id = ? AND a.doctor_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $patient_id, $doctor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Soft delete - update status
        $delete_sql = "UPDATE appointments SET status = 'Cancelled' 
                      WHERE user_id = ? AND doctor_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param('ii', $patient_id, $doctor_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Patient appointment cancelled successfully!";
        } else {
            $error_message = "Failed to cancel patient appointment.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query for patients
$where_conditions = ["a.doctor_id = ?"];
$params = [$doctor_id];
$types = "i";

if ($status_filter != 'all' && !empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get patients with their latest appointment
$patients_sql = "
    SELECT 
        u.id as patient_id,
        u.name as patient_name,
        u.email as patient_email,
        u.mobile as patient_phone,
        u.profile_pic as patient_image,
        u.blood_group,
        u.gender,
        u.dob,
        a.id as appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.purpose,
        a.status as appointment_status,
        a.created_at as appointment_created,
        MAX(a.appointment_date) as latest_appointment,
        COUNT(DISTINCT a.id) as total_appointments
    FROM users u
    INNER JOIN appointments a ON u.id = a.user_id
    $where_sql
    GROUP BY u.id, u.name, u.email, u.mobile
    ORDER BY latest_appointment DESC, u.name ASC
";

$patients_stmt = $conn->prepare($patients_sql);

if (!empty($params)) {
    $patients_stmt->bind_param($types, ...$params);
}

$patients_stmt->execute();
$patients_result = $patients_stmt->get_result();

// Get document counts for each patient
$patient_docs = [];
while ($patient = $patients_result->fetch_assoc()) {
    $doc_sql = "SELECT COUNT(*) as doc_count FROM patient_documents 
                WHERE patient_id = ? AND doctor_id = ?";
    $doc_stmt = $conn->prepare($doc_sql);
    $doc_stmt->bind_param('ii', $patient['patient_id'], $doctor_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    $doc_data = $doc_result->fetch_assoc();
    
    $patient['document_count'] = $doc_data['doc_count'] ?? 0;
    $patient_docs[] = $patient;
}

// Get appointment statistics
$stats_sql = "
    SELECT 
        COUNT(DISTINCT u.id) as total_patients,
        SUM(CASE WHEN a.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN a.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM users u
    INNER JOIN appointments a ON u.id = a.user_id
    WHERE a.doctor_id = ?
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $doctor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>REJUVENATE Digital Health - My Patients</title>
  <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
  <style>
    .btn-upload {
      background-color: green;
      color: #fff;
      font-size: 12px;
    }
    .btn-upload:hover {
      background-color: darkgreen;
    }
    .btn-delete {
      font-size: 12px;
      background-color: red;
      color: #fff;
    }
    .btn-delete:hover {
      background-color: darkred;
    }
    .sidebar {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 10px;
      height: fit-content;
    }
    .sidebar a {
      display: block;
      padding: 10px 15px;
      margin: 5px 0;
      color: #333;
      text-decoration: none;
      border-radius: 5px;
    }
    .sidebar a:hover, .sidebar a.active {
      background: #02c9b8;
      color: white;
    }
    .userd-image {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #02c9b8;
    }
    .profile-card {
      background: white;
      padding: 25px;
      border-radius: 10px;
      border: 1px solid #dee2e6;
    }
    .stats-card {
      background: white;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      margin-bottom: 15px;
    }
    .badge-status {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    .badge-pending { background: #fff3cd; color: #856404; }
    .badge-completed { background: #d4edda; color: #155724; }
    .badge-cancelled { background: #f8d7da; color: #721c24; }
    .filter-section {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    .menu-btn {
      display: none;
      background: #02c9b8;
      color: white;
      padding: 10px 15px;
      border-radius: 5px;
      cursor: pointer;
      margin-bottom: 15px;
    }
    @media (max-width: 768px) {
      .sidebar { display: none; }
      .sidebar.show { display: block; }
      .menu-btn { display: block; }
      .table-responsive { font-size: 12px; }
    }
  </style>
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
              <img src="<?= $site . $doctor_profile_image ?>" class="userd-image">
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
          
          <!-- Statistics Cards -->
          <div class="row mb-4">
            <div class="col-md-3">
              <div class="stats-card text-center">
                <h5>Total Patients</h5>
                <h3 class="text-primary"><?= $stats['total_patients'] ?? 0 ?></h3>
              </div>
            </div>
            <div class="col-md-3">
              <div class="stats-card text-center">
                <h5>Pending</h5>
                <h3 class="text-warning"><?= $stats['pending_count'] ?? 0 ?></h3>
              </div>
            </div>
            <div class="col-md-3">
              <div class="stats-card text-center">
                <h5>Completed</h5>
                <h3 class="text-success"><?= $stats['completed_count'] ?? 0 ?></h3>
              </div>
            </div>
            <div class="col-md-3">
              <div class="stats-card text-center">
                <h5>Cancelled</h5>
                <h3 class="text-danger"><?= $stats['cancelled_count'] ?? 0 ?></h3>
              </div>
            </div>
          </div>
          
          <!-- Filter Section -->
          <div class="filter-section">
            <form method="GET" action="" class="row g-3">
              <div class="col-md-4">
                <label>Filter by Status</label>
                <select name="status" class="form-select">
                  <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                  <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                  <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
              </div>
              <div class="col-md-4">
                <label>Search Patients</label>
                <input type="text" name="search" class="form-control" placeholder="Name, Email or Phone" value="<?= htmlspecialchars($search_query) ?>">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                <a href="my-patients.php" class="btn btn-secondary">Reset</a>
              </div>
            </form>
          </div>
          
          <!-- Patients Table -->
          <div class="profile-card shadow">
            <h4 class="mb-4">My Patients (<?= count($patient_docs) ?>)</h4>
            
            <?php if (count($patient_docs) == 0): ?>
              <div class="text-center py-5">
                <h5>No patients found</h5>
                <p class="text-muted">You don't have any patients yet.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Sr.</th>
                      <th>Patient Info</th>
                      <th>Contact</th>
                      <th>Last Appointment</th>
                      <th>Status</th>
                      <th>Documents</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($patient_docs as $index => $patient): ?>
                      <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                          <strong><?= htmlspecialchars($patient['patient_name']) ?></strong><br>
                          <small class="text-muted">
                            <?= $patient['gender'] ?? 'N/A' ?> | 
                            <?= $patient['blood_group'] ? 'Blood: ' . $patient['blood_group'] : '' ?>
                          </small>
                        </td>
                        <td>
                          <?php if ($patient['patient_email']): ?>
                            <div><?= htmlspecialchars($patient['patient_email']) ?></div>
                          <?php endif; ?>
                          <?php if ($patient['patient_phone']): ?>
                            <a href="tel:<?= $patient['patient_phone'] ?>"><?= $patient['patient_phone'] ?></a>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($patient['appointment_date']): ?>
                            <?= date('d/m/Y', strtotime($patient['appointment_date'])) ?><br>
                            <small><?= date('h:i A', strtotime($patient['appointment_time'])) ?></small>
                          <?php else: ?>
                            <span class="text-muted">No appointment</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php 
                          $status_class = '';
                          switch ($patient['appointment_status']) {
                              case 'Pending': $status_class = 'badge-pending'; break;
                              case 'Completed': $status_class = 'badge-completed'; break;
                              case 'Cancelled': $status_class = 'badge-cancelled'; break;
                              default: $status_class = 'badge-pending';
                          }
                          ?>
                          <span class="badge-status <?= $status_class ?>">
                            <?= $patient['appointment_status'] ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($patient['document_count'] > 0): ?>
                            <a href="patient-documents.php?patient_id=<?= $patient['patient_id'] ?>" 
                               class="btn btn-sm btn-info" title="View Documents">
                              <i class="fa fa-file"></i> (<?= $patient['document_count'] ?>)
                            </a>
                          <?php else: ?>
                            <span class="text-muted">No docs</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <!-- File Upload Form -->
                          <form method="POST" action="" enctype="multipart/form-data" class="d-inline">
                            <input type="hidden" name="patient_id" value="<?= $patient['patient_id'] ?>">
                            <input type="file" name="patient_file" id="file_<?= $patient['patient_id'] ?>" 
                                   style="display: none;" onchange="this.form.submit()">
                            <button type="button" class="btn btn-upload btn-sm" 
                                    onclick="document.getElementById('file_<?= $patient['patient_id'] ?>').click()">
                              <i class="fa fa-upload"></i> Upload
                            </button>
                            <input type="hidden" name="upload_file" value="1">
                          </form>
                          
                          <a href="my-patients.php?delete_patient=<?= $patient['patient_id'] ?>" 
                             class="btn btn-delete btn-sm"
                             onclick="return confirm('Are you sure you want to cancel this patient appointment?')">
                            <i class="fa fa-trash"></i> Cancel
                          </a>
                          
                          <a href="patient-details.php?id=<?= $patient['patient_id'] ?>" 
                             class="btn btn-info btn-sm" title="View Details">
                            <i class="fa fa-eye"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
  
  <?php include("../footer.php") ?>
  
  <!-- Modal for Document Upload -->
  <div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Upload Patient Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="patient_id" id="modal_patient_id">
            <div class="mb-3">
              <label>Select Document</label>
              <input type="file" name="patient_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
              <small class="text-muted">Max file size: 5MB (PDF, JPG, PNG only)</small>
            </div>
            <button type="submit" name="upload_file" class="btn btn-primary">Upload</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    function toggleMenu() {
      document.getElementById("sidebarMenu").classList.toggle("show");
    }
    
    function showUploadModal(patientId, patientName) {
      document.getElementById('modal_patient_id').value = patientId;
      document.getElementById('uploadModalLabel').innerText = 'Upload Document for ' + patientName;
      new bootstrap.Modal(document.getElementById('uploadModal')).show();
    }
  </script>
</body>
</html>