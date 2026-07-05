<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

require_once(__DIR__ . "/auth/guard.php");
$jwt_doctor  = doctor_jwt_guard();
$doctor_id   = (int)$jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';
$success_message = '';
$error_message = '';

// Get doctor's current details
$doctor_sql = "SELECT * FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();

if ($doctor_result->num_rows === 1) {
    $doctor = $doctor_result->fetch_assoc();
} else {
    header("Location: " . BASE_URL . "doctor-login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $gender = trim($_POST['gender']);
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $degrees = trim($_POST['degrees'] ?? $doctor['degrees']);
        $specialization = trim($_POST['specialization'] ?? $doctor['specialization']);
        $experience_years = intval($_POST['experience_years'] ?? $doctor['experience_years']);
        $consultation_fee = floatval($_POST['consultation_fee'] ?? $doctor['consultation_fee']);
        $languages = trim($_POST['languages'] ?? $doctor['languages']);
        $short_bio = trim($_POST['short_bio'] ?? $doctor['short_bio']);
        $long_bio = trim($_POST['long_bio'] ?? $doctor['long_bio']);
        $area_of_expertise = trim($_POST['area_of_expertise'] ?? $doctor['area_of_expertise']);
        $hpr_id = trim($_POST['hpr_id'] ?? $doctor['hpr_id']);
        $nmc_reg_number = trim($_POST['nmc_reg_number'] ?? $doctor['nmc_reg_number']);
        $council_name = trim($_POST['council_name'] ?? $doctor['council_name']);
        $year_of_registration = !empty($_POST['year_of_registration']) ? (int)$_POST['year_of_registration'] : null;
        $qualification_year = !empty($_POST['qualification_year']) ? (int)$_POST['qualification_year'] : null;

        // Handle profile image upload
        $profile_image = $doctor['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $file_type = $_FILES['profile_image']['type'];
            $file_name = $_FILES['profile_image']['name'];
            $file_tmp = $_FILES['profile_image']['tmp_name'];
            $file_size = $_FILES['profile_image']['size'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= 2097152) { // 2MB max
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_file_name = 'doctor_' . $doctor_id . '_' . time() . '.' . $file_ext;
                // Filesystem path (absolute) for writing the file...
                $upload_dir = dirname(__DIR__) . '/uploads/doctor_profile/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $dest_path = $upload_dir . $new_file_name;
                // ...but store a project-root-relative path in the DB, since it's
                // rendered elsewhere as BASE_URL . $profile_image.
                $db_path = 'uploads/doctor_profile/' . $new_file_name;

                if (move_uploaded_file($file_tmp, $dest_path)) {
                    // Delete old profile image if exists and not default
                    if (!empty($doctor['profile_image']) &&
                        $doctor['profile_image'] != 'assets/img/dummy.png') {
                        $old_path = dirname(__DIR__) . '/' . ltrim($doctor['profile_image'], '/.');
                        if (file_exists($old_path)) unlink($old_path);
                    }
                    $profile_image = $db_path;
                }
            }
        }
        
        // Handle document uploads
        $uploaded_docs = [];
        if (!empty($_FILES['documents']['name'][0])) {
            $doc_count = count($_FILES['documents']['name']);
            
            for ($i = 0; $i < $doc_count; $i++) {
                if ($_FILES['documents']['error'][$i] == 0) {
                    $allowed_doc_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                    $doc_type = $_FILES['documents']['type'][$i];
                    $doc_name = $_FILES['documents']['name'][$i];
                    $doc_tmp = $_FILES['documents']['tmp_name'][$i];
                    $doc_size = $_FILES['documents']['size'][$i];
                    
                    if (in_array($doc_type, $allowed_doc_types) && $doc_size <= 5242880) { // 5MB max
                        $doc_ext = pathinfo($doc_name, PATHINFO_EXTENSION);
                        $new_doc_name = 'doc_' . $doctor_id . '_' . time() . '_' . $i . '.' . $doc_ext;
                        $doc_upload_path = '../uploads/doctor_documents/' . $new_doc_name;
                        
                        // Create directory if it doesn't exist
                        if (!file_exists('../uploads/doctor_documents/')) {
                            mkdir('../uploads/doctor_documents/', 0777, true);
                        }
                        
                        if (move_uploaded_file($doc_tmp, $doc_upload_path)) {
                            $uploaded_docs[] = [
                                'name' => $doc_name,
                                'path' => $doc_upload_path,
                                'type' => $doc_type
                            ];
                        }
                    }
                }
            }
        }
        
        // Calculate age from DOB if provided
        $age = null;
        if ($dob) {
            $dob_date = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($dob_date)->y;
        }
        
        // Update doctor information
        $update_sql = "
            UPDATE doctors SET
                name = ?,
                phone = ?,
                email = ?,
                gender = ?,
                dob = ?,
                age = ?,
                degrees = ?,
                specialization = ?,
                experience_years = ?,
                consultation_fee = ?,
                languages = ?,
                short_bio = ?,
                long_bio = ?,
                area_of_expertise = ?,
                profile_image = ?,
                hpr_id = ?,
                nmc_reg_number = ?,
                council_name = ?,
                year_of_registration = ?,
                qualification_year = ?
            WHERE id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            'sssssissidssssssssiii',
            $name, $phone, $email, $gender, $dob, $age,
            $degrees, $specialization, $experience_years, $consultation_fee,
            $languages, $short_bio, $long_bio, $area_of_expertise,
            $profile_image, $hpr_id, $nmc_reg_number, $council_name,
            $year_of_registration, $qualification_year, $doctor_id
        );
        
        if ($update_stmt->execute()) {
            // Save uploaded documents to database
            if (!empty($uploaded_docs)) {
                $doc_sql = "INSERT INTO doctor_documents (doctor_id, document_type, document_name, file_path) 
                           VALUES (?, ?, ?, ?)";
                $doc_stmt = $conn->prepare($doc_sql);
                
                foreach ($uploaded_docs as $doc) {
                    $doc_type_name = '';
                    if (strpos($doc['type'], 'pdf') !== false) {
                        $doc_type_name = 'PDF Document';
                    } elseif (strpos($doc['type'], 'image') !== false) {
                        $doc_type_name = 'Certificate/ID';
                    } else {
                        $doc_type_name = 'Other Document';
                    }
                    
                    $doc_stmt->bind_param('isss', $doctor_id, $doc_type_name, $doc['name'], $doc['path']);
                    $doc_stmt->execute();
                }
            }
            
            // Update session data
            $_SESSION['doctor_name'] = $name;
            
            $success_message = "Profile updated successfully!";
            
            // Refresh doctor data
            $doctor_stmt->execute();
            $doctor_result = $doctor_stmt->get_result();
            $doctor = $doctor_result->fetch_assoc();
            
        } else {
            throw new Exception("Failed to update profile. Please try again.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get doctor's documents
$docs_sql = "SELECT * FROM doctor_documents WHERE doctor_id = ? ORDER BY uploaded_at DESC";
$docs_stmt = $conn->prepare($docs_sql);
$docs_stmt->bind_param('i', $doctor_id);
$docs_stmt->execute();
$docs_result = $docs_stmt->get_result();

// Format date for input field
$formatted_dob = $doctor['dob'] ? date('Y-m-d', strtotime($doctor['dob'])) : '';
$doctor_age = $doctor['age'] ?? '';

// Get doctor's profile image or default
$doctor_profile_image = !empty($doctor['profile_image']) ?
    $doctor['profile_image'] : 'assets/img/dummy.png';

$sidebar_active = 'contact';
require_once __DIR__ . '/inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - My Contact</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <style>
        label {
            display: inline-block;
            font-size: 14px;
            font-weight: 600;
            color: #000;
            margin-bottom: 5px;
        }
        .userd-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #02c9b8;
            cursor: pointer;
        }
        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        .profile-image-container {
            position: relative;
            display: inline-block;
        }
        .profile-image-overlay {
            position: absolute;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            color: white;
            width: 100%;
            text-align: center;
            padding: 5px;
            font-size: 12px;
            border-radius: 0 0 50% 50%;
            cursor: pointer;
        }
        .document-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <main class="doctor-content">
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


                    <!-- Profile Form -->
                    <div class="profile-card shadow">
                        <h4 class="mb-4">My Contact Details</h4>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <!-- Hidden profile image input -->
                            <input type="file" name="profile_image" id="profileImage" 
                                   accept="image/*" style="display: none;" 
                                   onchange="previewProfileImage(this)">
                            
                            <div class="row mt-4">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <label>Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" 
                                           placeholder="Dr. Your Name" required
                                           value="<?= htmlspecialchars($doctor['name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label>Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" 
                                           placeholder="Your phone number" required
                                           value="<?= htmlspecialchars($doctor['phone']) ?>">
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Email ID <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" 
                                           placeholder="Your email" required
                                           value="<?= htmlspecialchars($doctor['email']) ?>">
                                    <small class="text-muted">This is your login email</small>
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label>Gender</label>
                                    <select class="form-control" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?= $doctor['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $doctor['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= $doctor['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mt-3">
                                    <label>Date of Birth</label>
                                    <input type="date" class="form-control" name="dob" 
                                           value="<?= $formatted_dob ?>">
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label>Age</label>
                                    <input type="text" class="form-control" name="age" 
                                           placeholder="Auto-calculated" readonly
                                           value="<?= $doctor_age ?>">
                                </div>
                                
                                <!-- Professional Information -->
                                <div class="col-md-6 mt-3">
                                    <label>Degrees <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="degrees" 
                                           placeholder="MBBS, MD, etc." required
                                           value="<?= htmlspecialchars($doctor['degrees']) ?>">
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label>Specialization <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="specialization" 
                                           placeholder="Cardiology, Neurology, etc." required
                                           value="<?= htmlspecialchars($doctor['specialization']) ?>">
                                </div>
                                
                                <div class="col-md-6 mt-3">
                                    <label>Years of Experience</label>
                                    <input type="number" class="form-control" name="experience_years" 
                                           min="0" max="60" 
                                           value="<?= $doctor['experience_years'] ?>">
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label>Consultation Fee (₹)</label>
                                    <input type="number" class="form-control" name="consultation_fee" 
                                           min="0" step="50"
                                           value="<?= $doctor['consultation_fee'] ?>">
                                </div>
                                
                                <div class="col-md-6 mt-3">
                                    <label>Languages Known</label>
                                    <input type="text" class="form-control" name="languages" 
                                           placeholder="English, Hindi, etc."
                                           value="<?= htmlspecialchars($doctor['languages']) ?>">
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label>Area of Expertise</label>
                                    <input type="text" class="form-control" name="area_of_expertise"
                                           placeholder="Specific expertise areas"
                                           value="<?= htmlspecialchars($doctor['area_of_expertise']) ?>">
                                </div>

                                <!-- ABDM / HPR Compliance -->
                                <div class="col-md-12 mt-4">
                                    <h5 class="mb-1" style="color:#0C74C5;">ABDM / HPR Compliance</h5>
                                    <small class="text-muted d-block mb-2">
                                        Required for the Health Professional Registry (HPR). These fields are self-reported —
                                        <?php if ($doctor['hpr_verified']): ?>
                                            <span class="badge bg-success"><i class="fa fa-check-circle"></i> HPR Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fa fa-clock"></i> Verification Pending</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label>HPR ID</label>
                                    <input type="text" class="form-control" name="hpr_id"
                                           placeholder="XX-XXXX-XXXX-XXXX"
                                           value="<?= htmlspecialchars($doctor['hpr_id'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label>NMC Registration Number</label>
                                    <input type="text" class="form-control" name="nmc_reg_number"
                                           placeholder="National Medical Commission reg. no."
                                           value="<?= htmlspecialchars($doctor['nmc_reg_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label>State Medical Council</label>
                                    <input type="text" class="form-control" name="council_name"
                                           placeholder="e.g. Delhi Medical Council"
                                           value="<?= htmlspecialchars($doctor['council_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label>Year of NMC Registration</label>
                                    <input type="number" class="form-control" name="year_of_registration"
                                           min="1950" max="<?= date('Y') ?>"
                                           value="<?= htmlspecialchars($doctor['year_of_registration'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label>Qualification Year</label>
                                    <input type="number" class="form-control" name="qualification_year"
                                           min="1950" max="<?= date('Y') ?>"
                                           value="<?= htmlspecialchars($doctor['qualification_year'] ?? '') ?>">
                                </div>

                                <!-- Bio Sections -->
                                <div class="col-md-12 mt-3">
                                    <label>Short Bio (for listings)</label>
                                    <textarea class="form-control" name="short_bio" rows="3" 
                                              placeholder="Brief introduction about yourself (max 200 characters)"><?= htmlspecialchars($doctor['short_bio']) ?></textarea>
                                </div>
                                
                                <div class="col-md-12 mt-3">
                                    <label>Detailed Bio</label>
                                    <textarea class="form-control" name="long_bio" rows="5" 
                                              placeholder="Detailed professional background, achievements, etc."><?= htmlspecialchars($doctor['long_bio']) ?></textarea>
                                </div>
                                
                                <!-- Document Upload -->
                                <div class="col-md-12 mt-3">
                                    <label>Upload Documents (Certificates, IDs, etc.)</label>
                                    <input type="file" class="form-control" name="documents[]" 
                                           multiple accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Max 5MB per file. PDF, JPG, PNG only.</small>
                                </div>
                                
                                <!-- Current Documents -->
                                <?php if ($docs_result->num_rows > 0): ?>
                                <div class="col-md-12 mt-3">
                                    <label>Current Documents</label>
                                    <div class="row">
                                        <?php while ($doc = $docs_result->fetch_assoc()): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="document-card">
                                                <small><strong><?= htmlspecialchars($doc['document_name']) ?></strong></small><br>
                                                <small class="text-muted"><?= $doc['document_type'] ?></small><br>
                                                <small class="text-muted">Uploaded: <?= date('d/m/Y', strtotime($doc['uploaded_at'])) ?></small><br>
                                                <a href="<?= BASE_URL . $doc['file_path'] ?>" target="_blank" 
                                                   class="btn btn-sm btn-outline-primary mt-1">
                                                    <i class="fa fa-eye"></i> View
                                                </a>
                                                <a href="delete-document.php?id=<?= $doc['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger mt-1"
                                                   onclick="return confirm('Delete this document?')">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Submit Button -->
                                <div class="col-md-12 mt-4">
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="fa fa-save"></i> Save Changes
                                    </button>
                                    <a href="<?= BASE_URL ?>doctor/doctor-dashboard.php" class="btn btn-secondary px-4">
                                        Cancel
                                    </a>
                                    
                                    <!-- Change Password Link -->
                                    <a href="change-password.php" class="btn btn-outline-primary float-end">
                                        <i class="fa fa-key"></i> Change Password
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Account Security Section -->
                    <div class="profile-card shadow mt-4">
                        <h5 class="mb-3">Account Security</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Last Login:</strong> 
                                    <?= $doctor['last_login'] ? date('d/m/Y H:i', strtotime($doctor['last_login'])) : 'Never' ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Account Status:</strong> 
                                    <span class="badge bg-<?= $doctor['status'] == 'Active' ? 'success' : 'warning' ?>">
                                        <?= $doctor['status'] ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Verification Status:</strong> 
                                    <?php if ($doctor['is_verified'] == 1): ?>
                                        <span class="badge bg-success">
                                            <i class="fa fa-check-circle"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="fa fa-clock"></i> Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-12 mt-2">
                                <a href="account-settings.php" class="btn btn-outline-info btn-sm">
                                    <i class="fa fa-cog"></i> Advanced Account Settings
                                </a>
                            </div>
                        </div>
                    </div>
    </main>

    <script>
        
        // Preview profile image before upload
        function previewProfileImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImagePreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Auto-calculate age from DOB
        document.addEventListener('DOMContentLoaded', function() {
            var dobInput = document.querySelector('input[name="dob"]');
            var ageInput = document.querySelector('input[name="age"]');
            
            if (dobInput && ageInput) {
                dobInput.addEventListener('change', function() {
                    if (this.value) {
                        var dob = new Date(this.value);
                        var today = new Date();
                        var age = today.getFullYear() - dob.getFullYear();
                        var monthDiff = today.getMonth() - dob.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                            age--;
                        }
                        
                        ageInput.value = age;
                    } else {
                        ageInput.value = '';
                    }
                });
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            var phone = document.querySelector('input[name="phone"]').value;
            var email = document.querySelector('input[name="email"]').value;
            
            // Phone validation (10 digits)
            var phoneRegex = /^[0-9]{10}$/;
            if (!phoneRegex.test(phone.replace(/\D/g, ''))) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number.');
                return false;
            }
            
            // Email validation
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>