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

// Get doctor's complete profile details
$doctor_sql = "
    SELECT 
        d.*,
        a.username as verified_by_admin,
        COUNT(DISTINCT ap.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN ap.status = 'Completed' THEN ap.id END) as completed_appointments
    FROM doctors d
    LEFT JOIN admin_user a ON d.verified_by = a.id
    LEFT JOIN appointments ap ON d.id = ap.doctor_id
    WHERE d.id = ?
    GROUP BY d.id
";

$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();

if ($doctor_result->num_rows === 1) {
    $doctor = $doctor_result->fetch_assoc();
} else {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

// Get doctor's gallery images if any
$gallery_sql = "SELECT * FROM doctor_gallery WHERE doctor_id = ? ORDER BY uploaded_at DESC";
$gallery_stmt = $conn->prepare($gallery_sql);
$gallery_stmt->bind_param('i', $doctor_id);
$gallery_stmt->execute();
$gallery_result = $gallery_stmt->get_result();

// Get doctor's reviews/ratings (if you have a reviews table)
$reviews_sql = "
    SELECT 
        r.*,
        u.name as patient_name,
        u.profile_pic as patient_image
    FROM doctor_reviews r
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.doctor_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
";

// Try to execute reviews query (if table exists)
$reviews_result = null;
try {
    $reviews_stmt = $conn->prepare($reviews_sql);
    $reviews_stmt->bind_param('i', $doctor_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
} catch (Exception $e) {
    // Reviews table might not exist
    $reviews_result = null;
}

// Get doctor's documents/certificates
$certificates_sql = "
    SELECT * FROM doctor_documents 
    WHERE doctor_id = ? 
    AND (document_type LIKE '%certificate%' OR document_type LIKE '%degree%' OR document_type LIKE '%license%')
    ORDER BY uploaded_at DESC
";
$certificates_stmt = $conn->prepare($certificates_sql);
$certificates_stmt->bind_param('i', $doctor_id);
$certificates_stmt->execute();
$certificates_result = $certificates_stmt->get_result();

// Parse gallery images if stored as JSON
$gallery_images = [];
if (!empty($doctor['gallery_images'])) {
    $gallery_images = json_decode($doctor['gallery_images'], true);
    if (!is_array($gallery_images)) {
        $gallery_images = [];
    }
}

// Parse education if stored as JSON
$education_data = [];
if (!empty($doctor['education'])) {
    $education_data = json_decode($doctor['education'], true);
    if (!is_array($education_data)) {
        $education_data = [$doctor['education']];
    }
}

// Parse languages if stored as string
$languages_list = [];
if (!empty($doctor['languages'])) {
    $languages_list = explode(',', $doctor['languages']);
    $languages_list = array_map('trim', $languages_list);
}

// Calculate experience level
$experience_level = '';
if ($doctor['experience_years'] >= 20) {
    $experience_level = 'Senior Expert';
} elseif ($doctor['experience_years'] >= 10) {
    $experience_level = 'Experienced Specialist';
} elseif ($doctor['experience_years'] >= 5) {
    $experience_level = 'Specialist';
} else {
    $experience_level = 'Practitioner';
}

// Get doctor's profile image or default
$doctor_profile_image = !empty($doctor['profile_image']) ? 
    $doctor['profile_image'] : 'assets/img/dummy.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - About Doctor</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        label {
            display: inline-block;
            font-size: 14px;
            font-weight: 600;
            color: #000;
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
        .menu-btn {
            display: none;
            background: #02c9b8;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .doctor-des {
            font-size: 16px;
            font-weight: 600;
            color: #02c9b8;
            margin-bottom: 5px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 5px;
        }
        .doctor-title {
            font-size: 22px;
            padding: 13px 13px;
            color: #02c9b8;
            border-bottom: 2px solid #02c9b8;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #02c9b8;
        }
        .badge-verified {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        .badge-experience {
            background-color: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        .language-tag {
            display: inline-block;
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 15px;
            margin: 2px;
            font-size: 13px;
        }
        .gallery-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .gallery-image:hover {
            transform: scale(1.05);
        }
        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }
        .certificate-badge {
            background: #17a2b8;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }
        .stat-box {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #02c9b8;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .sidebar.show { display: block; }
            .menu-btn { display: block; }
        }
    </style>
</head>
<body>
  <?php
                            $doctor_name = $doctor['name'];
                            ?>
    <?php include("../header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row mb-5">
                <!-- Sidebar -->
                <div class="col-md-3">
                    <div class="sidebar" id="sidebarMenu">
                        <div class="text-center info-content">
                            <img src="<?= $site . $doctor_profile_image ?>" class="userd-image">
                            <h5>Dr. <?= htmlspecialchars($doctor_name) ?></h5>
                            <p><?= htmlspecialchars($doctor['email']) ?></p>
                            <p>Phone: <?= htmlspecialchars($doctor['phone']) ?></p>
                            <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Profile</a>
                        </div>

                        <a href="<?= $site ?>doctor/doctor-dashboard.php">Dashboard</a>
                        <a href="<?= $site ?>doctor/my-patients.php">My Patients</a>
                        <a href="<?= $site ?>doctor/appointments.php">Appointments</a>
                        <a href="<?= $site ?>doctor/patient-form.pdf">Patient Form</a>
                        <a href="<?= $site ?>doctor/my-contact.php">Contact Us</a>
                        <a href="<?= $site ?>doctor/doctor-about.php" class="active">About Us</a>
                        <a href="<?= $site ?>doctor/doctor-logout.php">Logout</a>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
                    <!-- Doctor Profile Header -->
                    <div class="profile-card shadow mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <img src="<?= $site . $doctor_profile_image ?>" 
                                     class="userd-image" 
                                     style="width: 120px; height: 120px;">
                            </div>
                            <div class="col-md-9">
                                <h2 class="doctor-title mb-2">Dr. <?= htmlspecialchars($doctor['name']) ?></h2>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge-verified">
                                        <i class="fa fa-check-circle"></i> 
                                        <?= $doctor['is_verified'] == 1 ? 'Verified Doctor' : 'Verification Pending' ?>
                                    </span>
                                    <span class="badge-experience">
                                        <i class="fa fa-briefcase"></i> 
                                        <?= $doctor['experience_years'] ?>+ Years Experience
                                    </span>
                                    <?php if ($doctor['rating'] > 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fa fa-star"></i> <?= number_format($doctor['rating'], 1) ?>/5.0
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1"><strong>Specialization:</strong> <?= htmlspecialchars($doctor['specialization']) ?></p>
                                <p class="mb-1"><strong>Consultation Fee:</strong> ₹<?= number_format($doctor['consultation_fee'], 2) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6">
                            <div class="stat-box">
                                <div class="stat-number"><?= $doctor['experience_years'] ?>+</div>
                                <div class="stat-label">Years Experience</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-box">
                                <div class="stat-number"><?= $doctor['total_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Total Appointments</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-box">
                                <div class="stat-number"><?= $doctor['completed_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-box">
                                <div class="stat-number">
                                    <?php if ($doctor['rating'] > 0): ?>
                                        <?= number_format($doctor['rating'], 1) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </div>
                                <div class="stat-label">Average Rating</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Profile Content -->
                    <div class="row">
                        <!-- Left Column - Professional Info -->
                        <div class="col-md-8">
                            <div class="profile-card shadow mb-4">
                                <h4 class="mb-4">Professional Information</h4>
                                
                                <!-- Degrees & Qualifications -->
                                <div class="mb-3">
                                    <div class="doctor-des">Degrees & Qualifications</div>
                                    <p class="mb-2"><?= htmlspecialchars($doctor['degrees']) ?></p>
                                    <?php if (!empty($education_data) && is_array($education_data)): ?>
                                        <ul class="list-unstyled">
                                            <?php foreach ($education_data as $education): ?>
                                                <li><i class="fa fa-graduation-cap text-primary me-2"></i> <?= htmlspecialchars($education) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Area of Expertise -->
                                <div class="mb-3">
                                    <div class="doctor-des">Area of Expertise</div>
                                    <p><?= htmlspecialchars($doctor['area_of_expertise']) ?></p>
                                </div>
                                
                                <!-- Experience -->
                                <div class="mb-3">
                                    <div class="doctor-des">Professional Experience</div>
                                    <p>
                                        <strong><?= $experience_level ?></strong> with <?= $doctor['experience_years'] ?>+ years of experience in <?= htmlspecialchars($doctor['specialization']) ?>.
                                    </p>
                                    <?php if (!empty($doctor['long_bio'])): ?>
                                        <div class="info-box">
                                            <?= nl2br(htmlspecialchars($doctor['long_bio'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Short Bio -->
                                <?php if (!empty($doctor['short_bio'])): ?>
                                <div class="mb-3">
                                    <div class="doctor-des">Professional Summary</div>
                                    <p><?= htmlspecialchars($doctor['short_bio']) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Languages -->
                                <?php if (!empty($languages_list)): ?>
                                <div class="mb-3">
                                    <div class="doctor-des">Languages Known</div>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach ($languages_list as $language): ?>
                                            <span class="language-tag"><?= htmlspecialchars($language) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Certificates & Documents -->
                                <?php if ($certificates_result->num_rows > 0): ?>
                                <div class="mb-3">
                                    <div class="doctor-des">Certificates & Licenses</div>
                                    <div class="row">
                                        <?php while ($cert = $certificates_result->fetch_assoc()): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="info-box">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?= htmlspecialchars($cert['document_name']) ?></strong>
                                                        <div class="certificate-badge d-inline-block ms-2"><?= $cert['document_type'] ?></div>
                                                    </div>
                                                    <a href="<?= $site . $cert['file_path'] ?>" target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fa fa-download"></i>
                                                    </a>
                                                </div>
                                                <small class="text-muted">Uploaded: <?= date('d/m/Y', strtotime($cert['uploaded_at'])) ?></small>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Gallery Section -->
                            <?php if (!empty($gallery_images) || $gallery_result->num_rows > 0): ?>
                            <div class="profile-card shadow mb-4">
                                <h4 class="mb-4">Gallery</h4>
                                <div class="row">
                                    <?php 
                                    // Display gallery from database table
                                    if ($gallery_result->num_rows > 0): 
                                        while ($gallery = $gallery_result->fetch_assoc()): 
                                    ?>
                                        <div class="col-md-4 mb-3">
                                            <img src="<?= $site . $gallery['image_path'] ?>" 
                                                 class="gallery-image"
                                                 onclick="openImageModal('<?= $site . $gallery['image_path'] ?>')">
                                        </div>
                                    <?php 
                                        endwhile;
                                    // Display gallery from JSON field
                                    elseif (!empty($gallery_images)): 
                                        foreach ($gallery_images as $image): 
                                    ?>
                                        <div class="col-md-4 mb-3">
                                            <img src="<?= $site . $image ?>" 
                                                 class="gallery-image"
                                                 onclick="openImageModal('<?= $site . $image ?>')">
                                        </div>
                                    <?php 
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Reviews Section -->
                            <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
                            <div class="profile-card shadow">
                                <h4 class="mb-4">Patient Reviews</h4>
                                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                <div class="info-box mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <?php if (!empty($review['patient_image'])): ?>
                                            <img src="<?= $site . $review['patient_image'] ?>" 
                                                 class="rounded-circle me-2" width="40" height="40">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($review['patient_name']) ?></strong>
                                            <div class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted ms-auto"><?= date('d/m/Y', strtotime($review['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0"><?= htmlspecialchars($review['comment']) ?></p>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Right Column - Additional Info -->
                        <div class="col-md-4">
                            <!-- Contact Information -->
                            <div class="profile-card shadow mb-4">
                                <h5 class="mb-3">Contact Information</h5>
                                <div class="info-box mb-3">
                                    <p class="mb-1"><strong><i class="fa fa-envelope text-primary me-2"></i> Email</strong></p>
                                    <p class="mb-0"><?= htmlspecialchars($doctor['email']) ?></p>
                                </div>
                                <div class="info-box mb-3">
                                    <p class="mb-1"><strong><i class="fa fa-phone text-primary me-2"></i> Phone</strong></p>
                                    <p class="mb-0"><?= htmlspecialchars($doctor['phone']) ?></p>
                                </div>
                                <?php if ($doctor['is_verified'] == 1): ?>
                                <div class="info-box mb-3">
                                    <p class="mb-1"><strong><i class="fa fa-shield-alt text-success me-2"></i> Verification</strong></p>
                                    <p class="mb-0">
                                        Verified on <?= date('d/m/Y', strtotime($doctor['verified_at'])) ?><br>
                                        <small>By: <?= htmlspecialchars($doctor['verified_by_admin'] ?? 'Administrator') ?></small>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Practice Information -->
                            <div class="profile-card shadow mb-4">
                                <h5 class="mb-3">Practice Details</h5>
                                <div class="info-box mb-3">
                                    <p class="mb-1"><strong><i class="fa fa-stethoscope text-primary me-2"></i> Specialization</strong></p>
                                    <p class="mb-0"><?= htmlspecialchars($doctor['specialization']) ?></p>
                                </div>
                                <div class="info-box mb-3">
                                    <p class="mb-1"><strong><i class="fa fa-money-bill-wave text-primary me-2"></i> Consultation Fee</strong></p>
                                    <p class="mb-0">₹<?= number_format($doctor['consultation_fee'], 2) ?></p>
                                </div>
                                <div class="info-box mb-3">
                                    <p class="mb-1"><strong><i class="fa fa-briefcase text-primary me-2"></i> Experience Level</strong></p>
                                    <p class="mb-0"><?= $experience_level ?></p>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="profile-card shadow">
                                <h5 class="mb-3">Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <a href="my-contact.php" class="btn btn-primary">
                                        <i class="fa fa-edit me-2"></i> Edit Profile
                                    </a>
                                    <a href="appointments.php" class="btn btn-success">
                                        <i class="fa fa-calendar me-2"></i> View Appointments
                                    </a>
                                    <a href="my-patients.php" class="btn btn-info">
                                        <i class="fa fa-users me-2"></i> My Patients
                                    </a>
                                    <button class="btn btn-outline-primary" onclick="window.print()">
                                        <i class="fa fa-print me-2"></i> Print Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include("../footer.php") ?>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gallery Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        
        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            var modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
        
        // Initialize gallery swiper if you want carousel
        document.addEventListener('DOMContentLoaded', function() {
            // You can add a swiper carousel here if needed
            /* 
            var gallerySwiper = new Swiper('.gallery-swiper', {
                slidesPerView: 3,
                spaceBetween: 10,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                breakpoints: {
                    640: { slidesPerView: 2 },
                    768: { slidesPerView: 3 },
                    1024: { slidesPerView: 4 }
                }
            });
            */
        });
    </script>
</body>
</html>