<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

require_once(__DIR__ . "/auth/guard.php");
$jwt_doctor = doctor_jwt_guard();
$doctor_id = (int) $jwt_doctor['sub'];
$doctor_name = $jwt_doctor['name'] ?? 'Doctor';

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
  header("Location: " . BASE_URL . "doctor-login.php");
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

$sidebar_active = 'about';
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
  <title>REJUVENATE Digital Health - About Doctor</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
  <style>
    /* ── Profile header ── */
    .profile-header {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
      padding: 24px 26px;
      margin-bottom: 18px;
    }

    .p-avatar-img {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--accent);
      flex-shrink: 0;
    }

    .p-avatar-fallback {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      background: var(--primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .p-name {
      font-size: 1.35rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 2px;
    }

    .p-sub {
      font-size: .86rem;
      color: #6b7280;
    }

    .p-fee {
      font-size: .86rem;
      color: #374151;
      margin-top: 6px;
    }

    .badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .2px;
    }

    .badge-ok {
      background: #dcfce7;
      color: #166534;
    }

    .badge-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .badge-info {
      background: #eff6ff;
      color: #1d4ed8;
    }

    .badge-star {
      background: #fef9c3;
      color: #854d0e;
    }

    /* ── Tabs (matches patient-profile.php) ── */
    .profile-tabs {
      display: flex;
      gap: 0;
      border-bottom: 2px solid #e5e7eb;
      margin-bottom: 18px;
      background: #fff;
      border-radius: 12px 12px 0 0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
      overflow-x: auto;
    }

    .p-tab {
      padding: 13px 20px;
      font-size: .84rem;
      font-weight: 600;
      color: #6b7280;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      white-space: nowrap;
      transition: .15s;
      flex-shrink: 0;
    }

    .p-tab:hover {
      color: var(--primary);
    }

    .p-tab.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
    }

    .tab-pane {
      display: none;
    }

    .tab-pane.active {
      display: block;
    }

    /* ── Info cards ── */
    .info-section {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
      padding: 18px 20px;
      margin-bottom: 14px;
    }

    .section-title {
      font-size: .8rem;
      font-weight: 700;
      color: #374151;
      margin-bottom: 12px;
    }

    .section-title i {
      color: var(--primary);
      margin-right: 5px;
    }

    .info-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: #9ca3af;
      margin-bottom: 2px;
    }

    .info-value {
      font-size: .9rem;
      color: #1f2937;
      font-weight: 500;
    }

    .info-row {
      padding: 9px 0;
      border-bottom: 1px solid #f3f4f6;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .bio-box {
      background: #f9fafb;
      border-left: 3px solid var(--accent);
      border-radius: 0 8px 8px 0;
      padding: 12px 14px;
      font-size: .86rem;
      color: #374151;
      line-height: 1.6;
    }

    .language-tag {
      display: inline-block;
      background: #f0f9ff;
      color: #0369a1;
      padding: 5px 13px;
      border-radius: 20px;
      margin: 2px;
      font-size: .78rem;
      font-weight: 600;
    }

    .gallery-image {
      width: 100%;
      height: 150px;
      object-fit: cover;
      border-radius: 10px;
      cursor: pointer;
      transition: .2s;
    }

    .gallery-image:hover {
      transform: scale(1.03);
      box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
    }

    .review-card {
      padding: 14px 16px;
      border-radius: 10px;
      background: #f9fafb;
      margin-bottom: 10px;
    }

    .rating-stars {
      color: #f59e0b;
      font-size: .86rem;
    }

    .cert-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 10px;
      background: #fff;
      border: 1px solid #e5e7eb;
      margin-bottom: 8px;
    }

    .cert-badge {
      background: #e0f2fe;
      color: #0369a1;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: .68rem;
      font-weight: 700;
    }

    .empty-note {
      text-align: center;
      padding: 28px 0;
      color: #9ca3af;
      font-size: .86rem;
    }

    .empty-note i {
      font-size: 1.8rem;
      display: block;
      margin-bottom: 8px;
      opacity: .5;
    }
  </style>
</head>

<body>
  <main class="doctor-content">

    <!-- Profile Header -->
    <div class="profile-header">
      <div class="d-flex align-items-start flex-wrap" style="gap:18px;">
        <?php if (!empty($doctor['profile_image'])): ?>
          <img src="<?= BASE_URL . htmlspecialchars($doctor_profile_image) ?>" class="p-avatar-img">
        <?php else: ?>
          <div class="p-avatar-fallback"><?= strtoupper(substr($doctor['name'], 0, 1)) ?></div>
        <?php endif; ?>
        <div style="flex:1;min-width:200px;">
          <div class="p-name">Dr. <?= htmlspecialchars($doctor['name']) ?></div>
          <div class="p-sub"><?= htmlspecialchars($doctor['specialization']) ?> &nbsp;·&nbsp;
            <?= (int) $doctor['experience_years'] ?>+ yrs experience</div>
          <div class="mt-2 d-flex flex-wrap" style="gap:6px;">
            <?php if ($doctor['is_verified'] == 1): ?>
              <span class="badge-pill badge-ok"><i class="fa fa-check-circle"></i> Verified Doctor</span>
            <?php else: ?>
              <span class="badge-pill badge-pending"><i class="fa fa-clock-o"></i> Verification Pending</span>
            <?php endif; ?>
            <span class="badge-pill badge-info"><i class="fa fa-briefcase"></i> <?= $experience_level ?></span>
            <?php if ($doctor['rating'] > 0): ?>
              <span class="badge-pill badge-star"><i class="fa fa-star"></i>
                <?= number_format($doctor['rating'], 1) ?>/5.0</span>
            <?php endif; ?>
          </div>
          <div class="p-fee"><i class="fa fa-money-bill-wave mr-1" style="color:var(--primary);"></i> Consultation Fee:
            <strong>₹<?= number_format($doctor['consultation_fee'], 2) ?></strong></div>
        </div>
        <div>
          <a href="my-contact.php" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit mr-1"></i> Edit
            Profile</a>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-card card-primary">
          <i class="fa fa-briefcase bg-icon"></i>
          <div class="num"><?= (int) $doctor['experience_years'] ?>+</div>
          <div class="lbl">Years Experience</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card card-green">
          <i class="fa fa-calendar bg-icon"></i>
          <div class="num"><?= $doctor['total_appointments'] ?? 0 ?></div>
          <div class="lbl">Total Appointments</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card card-accent">
          <i class="fa fa-check-circle bg-icon"></i>
          <div class="num"><?= $doctor['completed_appointments'] ?? 0 ?></div>
          <div class="lbl">Completed</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card card-purple">
          <i class="fa fa-star bg-icon"></i>
          <div class="num"><?= $doctor['rating'] > 0 ? number_format($doctor['rating'], 1) : 'N/A' ?></div>
          <div class="lbl">Average Rating</div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Left Column -->
      <div class="col-md-8">
        <!-- Tabs -->
        <div class="profile-tabs">
          <div class="p-tab active" onclick="showAboutTab('overview',this)"><i class="fa fa-user mr-1"></i> Overview
          </div>
          <div class="p-tab" onclick="showAboutTab('gallery',this)"><i class="fa fa-image mr-1"></i> Gallery</div>
          <div class="p-tab" onclick="showAboutTab('reviews',this)"><i class="fa fa-star mr-1"></i> Reviews</div>
          <div class="p-tab" onclick="showAboutTab('certs',this)"><i class="fa fa-certificate mr-1"></i> Certificates
          </div>
        </div>

        <!-- Tab: Overview -->
        <div class="tab-pane active" id="abouttab-overview">
          <div class="info-section">
            <div class="section-title"><i class="fa fa-graduation-cap"></i> Degrees & Qualifications</div>
            <div class="info-value mb-2"><?= htmlspecialchars($doctor['degrees']) ?></div>
            <?php if (!empty($education_data) && is_array($education_data)): ?>
              <ul class="list-unstyled mb-0">
                <?php foreach ($education_data as $education): ?>
                  <li style="font-size:.86rem;color:#374151;padding:3px 0;"><i class="fa fa-graduation-cap mr-2"
                      style="color:var(--accent);"></i><?= htmlspecialchars($education) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="info-section">
            <div class="section-title"><i class="fa fa-stethoscope"></i> Area of Expertise</div>
            <div class="info-value"><?= htmlspecialchars($doctor['area_of_expertise']) ?: '—' ?></div>
          </div>

          <?php if (!empty($doctor['short_bio']) || !empty($doctor['long_bio'])): ?>
            <div class="info-section">
              <div class="section-title"><i class="fa fa-file-text-o"></i> Professional Summary</div>
              <?php if (!empty($doctor['short_bio'])): ?>
                <div class="info-value mb-2"><?= htmlspecialchars($doctor['short_bio']) ?></div>
              <?php endif; ?>
              <?php if (!empty($doctor['long_bio'])): ?>
                <div class="bio-box"><?= nl2br(htmlspecialchars($doctor['long_bio'])) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($languages_list)): ?>
            <div class="info-section">
              <div class="section-title"><i class="fa fa-language"></i> Languages Known</div>
              <div>
                <?php foreach ($languages_list as $language): ?>
                  <span class="language-tag"><?= htmlspecialchars($language) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Tab: Gallery -->
        <div class="tab-pane" id="abouttab-gallery">
          <div class="info-section">
            <?php if ($gallery_result->num_rows > 0 || !empty($gallery_images)): ?>
              <div class="row">
                <?php if ($gallery_result->num_rows > 0): ?>
                  <?php while ($gallery = $gallery_result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-3">
                      <img src="<?= BASE_URL . $gallery['image_path'] ?>" class="gallery-image"
                        onclick="openImageModal('<?= BASE_URL . $gallery['image_path'] ?>')">
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <?php foreach ($gallery_images as $image): ?>
                    <div class="col-md-4 mb-3">
                      <img src="<?= BASE_URL . $image ?>" class="gallery-image"
                        onclick="openImageModal('<?= BASE_URL . $image ?>')">
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="empty-note"><i class="fa fa-image"></i>No gallery images uploaded yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tab: Reviews -->
        <div class="tab-pane" id="abouttab-reviews">
          <div class="info-section">
            <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
              <?php while ($review = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                  <div class="d-flex align-items-center mb-2">
                    <?php if (!empty($review['patient_image'])): ?>
                      <img src="<?= BASE_URL . $review['patient_image'] ?>" class="rounded-circle mr-2" width="36"
                        height="36">
                    <?php endif; ?>
                    <div>
                      <strong style="font-size:.86rem;"><?= htmlspecialchars($review['patient_name']) ?></strong>
                      <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="fa fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <small class="text-muted ml-auto"><?= date('d M Y', strtotime($review['created_at'])) ?></small>
                  </div>
                  <div style="font-size:.86rem;color:#374151;"><?= htmlspecialchars($review['comment']) ?></div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="empty-note"><i class="fa fa-star-o"></i>No patient reviews yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tab: Certificates -->
        <div class="tab-pane" id="abouttab-certs">
          <div class="info-section">
            <?php if ($certificates_result->num_rows > 0): ?>
              <?php while ($cert = $certificates_result->fetch_assoc()): ?>
                <div class="cert-item">
                  <div>
                    <div style="font-weight:600;font-size:.88rem;color:#1f2937;">
                      <?= htmlspecialchars($cert['document_name']) ?></div>
                    <span class="cert-badge"><?= htmlspecialchars($cert['document_type']) ?></span>
                    <span style="font-size:.72rem;color:#9ca3af;margin-left:6px;">Uploaded
                      <?= date('d M Y', strtotime($cert['uploaded_at'])) ?></span>
                  </div>
                  <a href="<?= BASE_URL . $cert['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i
                      class="fa fa-download"></i></a>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="empty-note"><i class="fa fa-certificate"></i>No certificates or licenses uploaded yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="col-md-4">
        <div class="info-section">
          <div class="section-title"><i class="fa fa-address-card"></i> Contact Information</div>
          <div class="info-row">
            <div class="info-label">Email</div>
            <div class="info-value"><?= htmlspecialchars($doctor['email']) ?></div>
          </div>
          <div class="info-row">
            <div class="info-label">Phone</div>
            <div class="info-value"><?= htmlspecialchars($doctor['phone']) ?></div>
          </div>
          <?php if ($doctor['is_verified'] == 1 && !empty($doctor['verified_at'])): ?>
            <div class="info-row">
              <div class="info-label">Verification</div>
              <div class="info-value">
                Verified <?= date('d M Y', strtotime($doctor['verified_at'])) ?>
                <div style="font-size:.76rem;color:#9ca3af;">By
                  <?= htmlspecialchars($doctor['verified_by_admin'] ?? 'Administrator') ?></div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="info-section">
          <div class="section-title"><i class="fa fa-hospital-o"></i> Practice Details</div>
          <div class="info-row">
            <div class="info-label">Specialization</div>
            <div class="info-value"><?= htmlspecialchars($doctor['specialization']) ?></div>
          </div>
          <div class="info-row">
            <div class="info-label">Consultation Fee</div>
            <div class="info-value">₹<?= number_format($doctor['consultation_fee'], 2) ?></div>
          </div>
          <div class="info-row">
            <div class="info-label">Experience Level</div>
            <div class="info-value"><?= $experience_level ?></div>
          </div>
        </div>

        <div class="info-section">
          <div class="section-title"><i class="fa fa-bolt"></i> Quick Actions</div>
          <div class="d-flex flex-column" style="gap:8px;">
            <a href="my-contact.php" class="btn btn-sm btn-primary-custom"><i class="fa fa-edit mr-1"></i> Edit
              Profile</a>
            <a href="appointments.php" class="btn btn-sm btn-outline-secondary"><i class="fa fa-calendar mr-1"></i> View
              Appointments</a>
            <a href="my-patients.php" class="btn btn-sm btn-outline-secondary"><i class="fa fa-users mr-1"></i> My
              Patients</a>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa fa-print mr-1"></i>
              Print Profile</button>
          </div>
        </div>
      </div>
    </div>
  </main>

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
    function showAboutTab(name, el) {
      document.querySelectorAll('#abouttab-overview, #abouttab-gallery, #abouttab-reviews, #abouttab-certs').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.profile-tabs .p-tab').forEach(t => t.classList.remove('active'));
      document.getElementById('abouttab-' + name).classList.add('active');
      el.classList.add('active');
    }

    function openImageModal(imageSrc) {
      document.getElementById('modalImage').src = imageSrc;
      var modal = new bootstrap.Modal(document.getElementById('imageModal'));
      modal.show();
    }
  </script>
</body>

</html>