<?php
error_reporting(E_ALL);

session_start();
include "db-conn.php";
include_once "functions.php";
require_once __DIR__ . '/../lib/DoctorAccess.php';

$doctor = null;
$success_message = $error_message = '';

// Pick up flash messages set by the verify/unverify actions below (they
// redirect back to this same page after acting, so the message has to
// survive via session rather than a local variable).
if (!empty($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (!empty($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch doctor data for editing
if (isset($_GET['id'])) {
    $doctor_id = intval($_GET['id']);
    $sql = "SELECT * FROM doctors WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $doctor = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Doctor not found!";
        header("Location: doctors-list.php");
        exit();
    }
} else {
    header("Location: doctors-list.php");
    exit();
}

// Handle doctor verify / unverify — same logic as doctors-list.php, but
// redirects back here so the admin can review documents/details and act
// on them from one page instead of doing it blind from the list.
if (isset($_GET['verify'])) {
    try {
        $admin_id = $_SESSION['admin_id'] ?? 1;
        $admin_stmt = $conn->prepare("SELECT username FROM admin_user WHERE id = ?");
        $admin_stmt->bind_param('i', $admin_id);
        $admin_stmt->execute();
        $admin_data = $admin_stmt->get_result()->fetch_assoc();
        $verified_by = $admin_data ? $admin_data['username'] : 'Administrator';

        $update_stmt = $conn->prepare("UPDATE doctors SET is_verified = 1, verified_at = NOW(), verified_by = ? WHERE id = ?");
        $update_stmt->bind_param('ii', $admin_id, $doctor_id);

        if ($update_stmt->execute()) {
            $mailSent = send_doctor_verification_email($doctor['email'], $doctor['name'], $verified_by);
            $_SESSION['success_message'] = "Doctor verified successfully!" . ($mailSent ? '' : " <small class='text-warning'>(Verification email failed to send)</small>");
        } else {
            throw new Exception("Failed to verify doctor");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}

if (isset($_GET['unverify'])) {
    $stmt = $conn->prepare("UPDATE doctors SET is_verified = 0, verified_at = NULL, verified_by = NULL WHERE id = ?");
    $stmt->bind_param('i', $doctor_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Doctor verification removed.";
    } else {
        $_SESSION['error_message'] = "Failed to remove verification.";
    }
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}

// HPR verification — approve / reject the doctor's HPR verification request
// (or toggle hpr_verified directly). Mirrors the doctor verify flow above.
if (isset($_GET['hpr_approve'])) {
    $conn->query("UPDATE doctors SET hpr_verified = 1, hpr_verified_at = NOW() WHERE id = " . $doctor_id);
    $conn->query("UPDATE hpr_verification_requests SET status = 'approved', reviewed_at = NOW(), reviewed_by = "
        . (int)($_SESSION['admin_id'] ?? 0) . " WHERE doctor_id = " . $doctor_id . " AND status = 'pending'");
    $_SESSION['success_message'] = "HPR verification approved.";
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}
if (isset($_GET['hpr_reject'])) {
    $note = trim($_POST['hpr_review_note'] ?? $_GET['note'] ?? '');
    $conn->query("UPDATE doctors SET hpr_verified = 0, hpr_verified_at = NULL WHERE id = " . $doctor_id);
    $rej = $conn->prepare("UPDATE hpr_verification_requests SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, review_note = ? WHERE doctor_id = ? AND status = 'pending'");
    $adm = (int)($_SESSION['admin_id'] ?? 0);
    $rej->bind_param('isi', $adm, $note, $doctor_id);
    $rej->execute();
    $_SESSION['success_message'] = "HPR verification request rejected.";
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}

// Per-document verification toggle (doctor_documents.is_verified)
if (isset($_GET['verify_doc'])) {
    $doc_id = intval($_GET['verify_doc']);
    $stmt = $conn->prepare("UPDATE doctor_documents SET is_verified = 1 WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param('ii', $doc_id, $doctor_id);
    $stmt->execute();
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}

if (isset($_GET['unverify_doc'])) {
    $doc_id = intval($_GET['unverify_doc']);
    $stmt = $conn->prepare("UPDATE doctor_documents SET is_verified = 0 WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param('ii', $doc_id, $doctor_id);
    $stmt->execute();
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}

// Bank account verification toggle
if (isset($_GET['verify_bank'])) {
    $admin_id = $_SESSION['admin_id'] ?? null;
    $stmt = $conn->prepare("UPDATE doctor_bank_accounts SET is_verified = 1, verified_at = NOW(), verified_by = ? WHERE doctor_id = ?");
    $stmt->bind_param('ii', $admin_id, $doctor_id);
    $stmt->execute();
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}
if (isset($_GET['unverify_bank'])) {
    $stmt = $conn->prepare("UPDATE doctor_bank_accounts SET is_verified = 0, verified_at = NULL, verified_by = NULL WHERE doctor_id = ?");
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    header("Location: doctor-edit.php?id=" . $doctor_id);
    exit();
}

// Uploaded documents for verification review
$docs_stmt = $conn->prepare("SELECT * FROM doctor_documents WHERE doctor_id = ? ORDER BY uploaded_at DESC");
$docs_stmt->bind_param('i', $doctor_id);
$docs_stmt->execute();
$doctor_documents = $docs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$docs_stmt->close();

// Payment / subscription history
$pay_stmt = $conn->prepare("
    SELECT ds.*, dp.name AS plan_name
    FROM doctor_subscriptions ds
    LEFT JOIN doctor_plans dp ON dp.id = ds.plan_id
    WHERE ds.doctor_id = ?
    ORDER BY ds.created_at DESC
");
$pay_stmt->bind_param('i', $doctor_id);
$pay_stmt->execute();
$doctor_payments = $pay_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pay_stmt->close();

// Bank account details (filled by the doctor themselves)
$bank_stmt = $conn->prepare("SELECT * FROM doctor_bank_accounts WHERE doctor_id = ? LIMIT 1");
$bank_stmt->bind_param('i', $doctor_id);
$bank_stmt->execute();
$doctor_bank = $bank_stmt->get_result()->fetch_assoc();
$bank_stmt->close();

// Settlement (T+2 payout) summary
$settle_sum_stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status = 'pending' THEN settlement_amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'settled' THEN settlement_amount ELSE 0 END), 0) AS settled_total
    FROM appointment_settlements WHERE doctor_id = ?
");
$settle_sum_stmt->bind_param('i', $doctor_id);
$settle_sum_stmt->execute();
$settlement_summary = $settle_sum_stmt->get_result()->fetch_assoc();

// Referring doctor's name, if this doctor signed up via a referral link
$referring_doctor_name = null;
if (!empty($doctor['referred_by'])) {
    $rd_stmt = $conn->prepare("SELECT name FROM doctors WHERE id = ?");
    $rd_stmt->bind_param('i', $doctor['referred_by']);
    $rd_stmt->execute();
    $rd_row = $rd_stmt->get_result()->fetch_assoc();
    $referring_doctor_name = $rd_row['name'] ?? null;
}

// Activation gate state — is this doctor actually publicly bookable right
// now? `status` alone doesn't tell you this anymore: it's never
// auto-flipped (see database/migration_doctor_activation_gate.sql), so a
// doctor can show status='Active' here while still being hidden from
// booking listings because they're unverified/unsubscribed.
$doc_in_grace = !empty($doctor['grace_period_until']) && strtotime($doctor['grace_period_until']) > time();
$doc_has_sub  = doctor_has_active_subscription($conn, $doctor_id);
$doc_bookable = doctor_qualifies_active($conn, $doctor);
$doc_sub_expiry_stmt = $conn->prepare("SELECT expires_at FROM doctor_subscriptions WHERE doctor_id = ? AND status = 'paid' ORDER BY expires_at DESC LIMIT 1");
$doc_sub_expiry_stmt->bind_param('i', $doctor_id);
$doc_sub_expiry_stmt->execute();
$doc_sub_expiry = $doc_sub_expiry_stmt->get_result()->fetch_assoc()['expires_at'] ?? null;

// Latest HPR verification request from the doctor (doctor/my-contact.php)
$hpr_req_stmt = $conn->prepare("SELECT * FROM hpr_verification_requests WHERE doctor_id = ? ORDER BY id DESC LIMIT 1");
$hpr_req_stmt->bind_param('i', $doctor_id);
$hpr_req_stmt->execute();
$hpr_request = $hpr_req_stmt->get_result()->fetch_assoc();

// Get current doctor departments
$current_departments = [];
$dept_sql = "SELECT category_id FROM doctor_departments WHERE doctor_id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param('i', $doctor_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
while ($dept = $dept_result->fetch_assoc()) {
    $current_departments[] = $dept['category_id'];
}
$dept_stmt->close();

// Handle gallery image removal
if (isset($_POST['remove_gallery_image'])) {
    try {
        $image_to_remove = $_POST['remove_gallery_image'];
        $gallery_array = json_decode($doctor['gallery_images'], true) ?? [];
        
        // Remove the image from array
        $updated_gallery = array_filter($gallery_array, function($image) use ($image_to_remove) {
            return $image !== $image_to_remove;
        });
        
        // Delete the physical file
        if (file_exists($image_to_remove)) {
            unlink($image_to_remove);
        }
        
        $gallery_images_json = json_encode(array_values($updated_gallery));
        
        // Update database
        $stmt = $conn->prepare("UPDATE doctors SET gallery_images = ? WHERE id = ?");
        $stmt->bind_param('si', $gallery_images_json, $doctor_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Gallery image removed successfully!";
            // Refresh the page to show updated gallery
            header("Location: doctor-edit.php?id=" . $doctor_id);
            exit();
        } else {
            throw new Exception("Failed to remove gallery image");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['remove_gallery_image'])) {
    try {
        // Get form data
        $name = trim($_POST['name']);
        $degrees = trim($_POST['degrees']);
        $specialization = trim($_POST['specialization']);
        $experience_years = intval($_POST['experience_years']);
        $rating = floatval($_POST['rating']);
        $languages = trim($_POST['languages']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $short_bio = trim($_POST['short_bio']);
        $long_bio = trim($_POST['long_bio']);
        $education = trim($_POST['education']);
        $area_of_expertise = trim($_POST['area_of_expertise']);
        $status = $_POST['status'];
        $meta_title = trim($_POST['meta_title']);
        $meta_keywords = trim($_POST['meta_keywords']);
        $meta_description = trim($_POST['meta_description']);
        $slug_url = trim($_POST['slug_url']);
        $doctor_departments = $_POST['department'] ?? [];

        // Validate required fields
        if (empty($name) || empty($specialization)) {
            throw new Exception("Name and specialization are required");
        }

        // Validate departments
        if (empty($doctor_departments)) {
            throw new Exception("Please select at least one department");
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Handle profile image upload
        $profile_image_path = $_POST['current_profile_image'] ?? '';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/doctors/profile/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_type = $_FILES['profile_image']['type'];

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPG, PNG, GIF, and WEBP images are allowed");
            }
            
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'doctor-profile-' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload profile image");
            }
            
            // Delete old profile image if exists
            if (!empty($_POST['current_profile_image']) && file_exists($_POST['current_profile_image'])) {
                unlink($_POST['current_profile_image']);
            }
            
            $profile_image_path = $target_path;
        }

        // Handle gallery images upload
        $gallery_images_paths = [];
        if (!empty($_POST['current_gallery_images'])) {
            $gallery_images_paths = json_decode($_POST['current_gallery_images'], true) ?? [];
        }

        if (!empty($_FILES['gallery_images']['name'][0])) {
            $upload_dir = 'uploads/doctors/gallery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES['gallery_images']['type'][$key];
                    if (in_array($file_type, $allowed_types)) {
                        $file_ext = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                        $file_name = 'doctor-gallery-' . time() . '-' . $key . '.' . $file_ext;
                        $target_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            $gallery_images_paths[] = $target_path;
                        }
                    }
                }
            }
        }

        $gallery_images_json = json_encode($gallery_images_paths);

        // Start transaction
        $conn->begin_transaction();

        try {
            // Update doctor
            $stmt = $conn->prepare("UPDATE doctors SET 
                name = ?, degrees = ?, specialization = ?, experience_years = ?, 
                rating = ?, languages = ?, consultation_fee = ?, short_bio = ?, long_bio = ?, 
                profile_image = ?, gallery_images = ?, education = ?, area_of_expertise = ?, 
                status = ?, meta_title = ?, meta_keywords = ?, meta_description = ?, slug_url = ? 
                WHERE id = ?");
            
            $stmt->bind_param('ssssdsssssssssssssi', 
                $name, $degrees, $specialization, $experience_years, 
                $rating, $languages, $consultation_fee, $short_bio, $long_bio, 
                $profile_image_path, $gallery_images_json, $education, $area_of_expertise, 
                $status, $meta_title, $meta_keywords, $meta_description, $slug_url, $doctor_id);

            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->close();

            // Update doctor departments
            // First, remove existing departments
            $delete_stmt = $conn->prepare("DELETE FROM doctor_departments WHERE doctor_id = ?");
            $delete_stmt->bind_param('i', $doctor_id);
            if (!$delete_stmt->execute()) {
                throw new Exception("Failed to remove existing departments: " . $conn->error);
            }
            $delete_stmt->close();

            // Insert new departments
            if (!empty($doctor_departments)) {
                $valid_departments = [];
                
                // Validate each department ID before insertion
                foreach ($doctor_departments as $dept_id) {
                    $dept_id = intval($dept_id);
                    if ($dept_id > 0) {
                        // Check if category exists
                        $check_sql = "SELECT cate_id FROM sub_categories WHERE cate_id = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("i", $dept_id);
                        $check_stmt->execute();
                        $check_stmt->store_result();
                        
                        if ($check_stmt->num_rows > 0) {
                            $valid_departments[] = $dept_id;
                        }
                        $check_stmt->close();
                    }
                }
                
                // Insert only valid departments
                if (!empty($valid_departments)) {
                    $dept_stmt = $conn->prepare("INSERT INTO doctor_departments (doctor_id, category_id, added_on) VALUES (?, ?, NOW())");
                    
                    foreach ($valid_departments as $dept_id) {
                        $dept_stmt->bind_param('ii', $doctor_id, $dept_id);
                        if (!$dept_stmt->execute()) {
                            throw new Exception("Failed to insert department ID $dept_id: " . $conn->error);
                        }
                    }
                    $dept_stmt->close();
                } else {
                    throw new Exception("No valid departments found to associate with the doctor.");
                }
            }

            // Commit transaction
            $conn->commit();

            $_SESSION['success_message'] = "Doctor updated successfully with " . count($doctor_departments) . " department(s)!";
            header("Location: doctors-list.php");
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            throw new Exception($e->getMessage());
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get current gallery images
$current_gallery = [];
if (!empty($doctor['gallery_images'])) {
    $current_gallery = json_decode($doctor['gallery_images'], true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Doctor | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .doctor-form {
            background: #fff;
            border: 1px solid #e7e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(20,23,40,.04);
            padding: 24px;
        }
        @media (max-width: 767.98px) { .doctor-form { padding: 16px; } }
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px dashed #ddd;
            padding: 5px;
        }
        .gallery-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .gallery-item {
            position: relative;
            display: inline-block;
        }
        .gallery-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .remove-gallery-btn {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .remove-gallery-btn:hover {
            background: #c82333;
        }
        .file-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-upload-label {
            display: inline-block;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
        }
        .file-upload-label:hover {
            background-color: #e9ecef;
        }
        .section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #6b7280;
            border-bottom: 1px solid #eef1f5;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .current-image-section {
            margin-top: 10px;
        }
        .gallery-actions {
            margin-top: 10px;
        }
        .empty-gallery {
            color: #6c757d;
            font-style: italic;
        }
        .multi-select-dropdown {
            position: relative;
        }
        .multi-select-dropdown .dropdown-menu {
            max-height: 250px;
            overflow-y: auto;
            width: 100%;
        }

        /* ---- compact 2-column edit layout ---- */
        .dr-edit-wrap { max-width: 1480px; }
        .dr-rail { position: sticky; top: 74px; }
        @media (max-width: 1199.98px) { .dr-rail { position: static; } }
        .doctor-form .section-title { margin-top: 22px; }
        .doctor-form .row:first-child .section-title,
        .doctor-form > .section-title:first-child { margin-top: 0; }
        .doctor-form hr { margin: 16px 0; border-color: #eef0f5; }

        .doc-avatar-lg {
            width: 52px; height: 52px; border-radius: 12px; object-fit: cover;
            background: #eef1f6; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .doc-avatar-lg i { color: #9aa0b4; font-size: 20px; }

        /* tighten the info rows in the rail — always single column */
        .dr-rail .mb-2 { margin-bottom: .3rem !important; font-size: .84rem; }
        .dr-rail h6 { font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #6b7089; margin-top: 14px; }
        .dr-rail .badge { font-weight: 600; }
        .dr-rail .row { --bs-gutter-x: 0; }
        .dr-rail .row > [class*="col-"] { flex: 0 0 100%; max-width: 100%; margin-bottom: .5rem; }
        .dr-rail hr { margin: 12px 0; }
        .dr-rail .btn-sm { font-size: .78rem; }
    </style>
</head>
<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid dr-edit-wrap">
                <div class="list-page-head">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($doctor['profile_image'])): ?>
                            <img src="<?= htmlspecialchars($doctor['profile_image']) ?>" alt="" class="doc-avatar-lg" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="doc-avatar-lg" style="display:none;"><i class="fas fa-user-md"></i></div>
                        <?php else: ?>
                            <div class="doc-avatar-lg"><i class="fas fa-user-md"></i></div>
                        <?php endif; ?>
                        <div>
                            <h4 class="mb-0 fw-bold"><?= htmlspecialchars($doctor['name']) ?></h4>
                            <div class="cell-sub"><?= htmlspecialchars($doctor['doctor_uid']) ?><?= $doctor['specialization'] ? ' · ' . htmlspecialchars($doctor['specialization']) : '' ?></div>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                <span class="pill <?= $doctor['is_verified'] ? 'pill-success' : 'pill-warn' ?>"><i class="fas fa-<?= $doctor['is_verified'] ? 'check-circle' : 'clock' ?>"></i><?= $doctor['is_verified'] ? 'Verified' : 'Unverified' ?></span>
                                <span class="pill <?= $doctor['status'] === 'Active' ? 'pill-success' : 'pill-muted' ?>"><?= htmlspecialchars($doctor['status']) ?></span>
                                <span class="pill <?= $doc_bookable ? 'pill-info' : 'pill-muted' ?>"><i class="fas fa-<?= $doc_bookable ? 'globe' : 'eye-slash' ?>"></i><?= $doc_bookable ? 'Bookable' : 'Not bookable' ?></span>
                            </div>
                        </div>
                    </div>
                    <a href="doctors-list.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= $success_message ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-xl-4 order-xl-2">
                        <div class="doctor-form dr-rail">
                            <h4 class="section-title">Verification &amp; Status</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($doctor['email'] ?: '—') ?></div>
                                    <div class="mb-2">
                                        <strong>Phone:</strong> <?= htmlspecialchars($doctor['phone'] ?: '—') ?>
                                        <?php if ($doctor['mobile_verified']): ?>
                                            <span class="badge bg-success ms-1"><i class="fas fa-check"></i> Mobile Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-1">Mobile Not Verified</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($referring_doctor_name): ?>
                                        <div class="mb-2"><strong>Referred By:</strong> Dr. <?= htmlspecialchars($referring_doctor_name) ?></div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <strong>HPR ID:</strong> <?= htmlspecialchars($doctor['hpr_id'] ?: 'Not provided') ?>
                                        <?php if ($doctor['hpr_verified']): ?>
                                            <span class="badge bg-success ms-1"><i class="fas fa-check"></i> HPR Verified</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($doctor['hfr_id'])): ?>
                                        <div class="mb-2"><strong>HFR ID:</strong> <?= htmlspecialchars($doctor['hfr_id']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($doctor['nmc_reg_number'])): ?>
                                        <div class="mb-2"><strong>NMC Reg. No.:</strong> <?= htmlspecialchars($doctor['nmc_reg_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($doctor['council_name'])): ?>
                                        <div class="mb-2"><strong>State Medical Council:</strong> <?= htmlspecialchars($doctor['council_name']) ?></div>
                                    <?php endif; ?>

                                    <?php if ($hpr_request && $hpr_request['status'] === 'pending'): ?>
                                        <div class="alert alert-warning py-2 px-3 mt-2" style="font-size:.86rem;">
                                            <strong><i class="fas fa-clock me-1"></i>HPR verification requested</strong>
                                            on <?= date('d M Y', strtotime($hpr_request['requested_at'])) ?>.
                                            <div class="mt-1">
                                                HPR: <?= htmlspecialchars($hpr_request['hpr_id'] ?: '—') ?> ·
                                                NMC: <?= htmlspecialchars($hpr_request['nmc_reg_number'] ?: '—') ?> ·
                                                Council: <?= htmlspecialchars($hpr_request['council_name'] ?: '—') ?> ·
                                                Year: <?= htmlspecialchars($hpr_request['year_of_registration'] ?: '—') ?>
                                            </div>
                                            <?php if (!empty($hpr_request['doctor_note'])): ?>
                                                <div class="mt-1 fst-italic">"<?= htmlspecialchars($hpr_request['doctor_note']) ?>"</div>
                                            <?php endif; ?>
                                            <form method="POST" action="doctor-edit.php?id=<?= $doctor_id ?>&hpr_reject=1" class="d-flex gap-2 mt-2">
                                                <a href="doctor-edit.php?id=<?= $doctor_id ?>&hpr_approve=1" class="btn btn-sm btn-success"
                                                   onclick="return confirm('Confirm you have checked this HPR ID against the registry?')">
                                                    <i class="fas fa-check me-1"></i>Approve HPR
                                                </a>
                                                <input type="text" name="hpr_review_note" class="form-control form-control-sm" placeholder="Reason (if rejecting)">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                            </form>
                                        </div>
                                    <?php elseif ($hpr_request && $hpr_request['status'] === 'rejected'): ?>
                                        <div class="text-muted mt-2" style="font-size:.82rem;">
                                            Last HPR request rejected <?= $hpr_request['reviewed_at'] ? 'on ' . date('d M Y', strtotime($hpr_request['reviewed_at'])) : '' ?>
                                            <?= $hpr_request['review_note'] ? '— ' . htmlspecialchars($hpr_request['review_note']) : '' ?>.
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($doctor['hpr_verified']): ?>
                                        <div class="mt-2"><a href="doctor-edit.php?id=<?= $doctor_id ?>&hpr_reject=1&note=Revoked+by+admin" class="btn btn-sm btn-outline-warning"
                                           onclick="return confirm('Remove HPR verified status?')"><i class="fas fa-times me-1"></i>Revoke HPR</a></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="mb-2">
                                        <strong>Verification Status:</strong><br>
                                        <?php if ($doctor['is_verified']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle"></i> Verified</span>
                                            <?php if ($doctor['verified_at']): ?>
                                                <small class="text-muted d-block">on <?= date('d M Y, h:i A', strtotime($doctor['verified_at'])) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($doctor['is_verified']): ?>
                                        <a href="doctor-edit.php?id=<?= $doctor_id ?>&unverify=1" class="btn btn-sm btn-outline-warning"
                                           onclick="return confirm('Remove verification for this doctor?')">
                                            <i class="fas fa-times-circle me-1"></i> Unverify Doctor
                                        </a>
                                    <?php else: ?>
                                        <a href="doctor-edit.php?id=<?= $doctor_id ?>&verify=1" class="btn btn-sm btn-success"
                                           onclick="return confirm('Verify this doctor? A confirmation email will be sent.')">
                                            <i class="fas fa-check-circle me-1"></i> Verify Doctor
                                        </a>
                                    <?php endif; ?>

                                    <div class="mb-2 mt-3">
                                        <strong>Publicly Bookable:</strong><br>
                                        <?php if ($doc_bookable): ?>
                                            <span class="badge bg-success"><i class="fas fa-globe"></i> Yes — shows up in booking</span>
                                            <small class="text-muted d-block">
                                                <?php if ($doc_in_grace): ?>
                                                    Grace period until <?= date('d M Y', strtotime($doctor['grace_period_until'])) ?>
                                                <?php elseif ($doc_has_sub): ?>
                                                    Subscribed until <?= date('d M Y', strtotime($doc_sub_expiry)) ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-eye-slash"></i> No — hidden from booking</span>
                                            <small class="text-muted d-block">
                                                <?= !$doctor['is_verified'] ? 'Awaiting admin verification.' : 'Verified, but no active subscription.' ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6 class="fw-bold mb-2">Bank Account Details</h6>
                                    <?php if (!$doctor_bank): ?>
                                        <p class="text-muted mb-0">Doctor hasn't added bank details yet.</p>
                                    <?php else: ?>
                                        <div class="mb-1"><strong>Holder:</strong> <?= htmlspecialchars($doctor_bank['account_holder_name']) ?></div>
                                        <div class="mb-1"><strong>A/C No.:</strong> <?= htmlspecialchars($doctor_bank['account_number']) ?></div>
                                        <div class="mb-1"><strong>IFSC:</strong> <?= htmlspecialchars($doctor_bank['ifsc_code']) ?> &middot; <?= htmlspecialchars($doctor_bank['bank_name']) ?></div>
                                        <?php if (!empty($doctor_bank['upi_id'])): ?>
                                            <div class="mb-1"><strong>UPI:</strong> <?= htmlspecialchars($doctor_bank['upi_id']) ?></div>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <?php if ($doctor_bank['is_verified']): ?>
                                                <span class="badge bg-success mb-1"><i class="fas fa-check-circle"></i> Verified</span><br>
                                                <a href="doctor-edit.php?id=<?= $doctor_id ?>&unverify_bank=1" class="btn btn-sm btn-outline-warning mt-1">Unverify Bank</a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary mb-1">Unverified</span><br>
                                                <a href="doctor-edit.php?id=<?= $doctor_id ?>&verify_bank=1" class="btn btn-sm btn-success mt-1">Verify Bank</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="fw-bold mb-2">Settlement Summary (T+2)</h6>
                                    <div class="mb-1"><strong>Pending:</strong> ₹<?= number_format($settlement_summary['pending_total'], 2) ?></div>
                                    <div class="mb-1"><strong>Total Settled:</strong> ₹<?= number_format($settlement_summary['settled_total'], 2) ?></div>
                                    <a href="settlements.php?status=pending" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="fas fa-list me-1"></i> View All Settlements
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8 order-xl-1">
                        <div class="doctor-form">
                            <form method="post" enctype="multipart/form-data" id="doctorForm">
                                <input type="hidden" name="current_profile_image" value="<?= $doctor['profile_image'] ?>">
                                <input type="hidden" name="current_gallery_images" value='<?= $doctor['gallery_images'] ?>'>
                                
                                <!-- Basic Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Basic Information</h4>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Doctor Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($doctor['name']) ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Doctor UID</label>
                                        <input type="text" class="form-control" value="<?= $doctor['doctor_uid'] ?>" readonly>
                                        <small class="text-muted">Auto-generated unique identifier</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Degrees</label>
                                        <input type="text" class="form-control" name="degrees" placeholder="MBBS, MD, DGO" value="<?= htmlspecialchars($doctor['degrees']) ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Specialization <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="specialization" required placeholder="Cardiology, Neurology, etc." value="<?= htmlspecialchars($doctor['specialization']) ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Experience (Years)</label>
                                        <input type="number" class="form-control" name="experience_years" min="0" value="<?= $doctor['experience_years'] ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Rating</label>
                                        <input type="number" class="form-control" name="rating" min="0" max="5" step="0.1" value="<?= $doctor['rating'] ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Consultation Fee (₹)</label>
                                        <input type="number" class="form-control" name="consultation_fee" min="0" step="0.01" value="<?= $doctor['consultation_fee'] ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Departments <span class="text-danger">*</span></label>

                                        <div class="multi-select-dropdown">
                                            <button class="btn btn-light form-control text-start dropdown-toggle"
                                                type="button"
                                                id="departmentDropdown"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <span id="dropdownText">Select Departments</span>
                                            </button>

                                            <ul class="dropdown-menu p-3" style="max-height: 250px; overflow-y: auto;">
                                                <?php
                                                $cat_id = 20873;
                                                $departments = get_sub_category_doctors($cat_id);

                                                if (empty($departments)) {
                                                    echo '<li><span class="text-muted">No departments found</span></li>';
                                                } else {
                                                    foreach ($departments as $depart) {
                                                        $isChecked = in_array($depart['cate_id'], $current_departments);
                                                ?>
                                                        <li>
                                                            <label class="form-check mb-2 d-block">
                                                                <input class="form-check-input dept-checkbox"
                                                                    type="checkbox"
                                                                    name="department[]"
                                                                    value="<?= $depart['cate_id'] ?>"
                                                                    <?= $isChecked ? 'checked' : '' ?>>
                                                                <?= htmlspecialchars($depart['categories']) ?>
                                                            </label>
                                                        </li>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </div>

                                        <!-- Selected departments display -->
                                        <div id="selectedDepartments" class="mt-2">
                                            <?php
                                            if (!empty($current_departments)) {
                                                $selected_names = [];
                                                foreach ($departments as $depart) {
                                                    if (in_array($depart['cate_id'], $current_departments)) {
                                                        $selected_names[] = $depart['categories'];
                                                    }
                                                }
                                                foreach ($selected_names as $name) {
                                                    echo '<span class="badge bg-primary me-1 mb-1">' . $name . '</span>';
                                                }
                                            } else {
                                                echo '<small class="text-muted">No departments selected</small>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Languages</label>
                                        <input type="text" class="form-control" name="languages" placeholder="Hindi, English, Punjabi" value="<?= $doctor['languages'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="Active" <?= $doctor['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option value="Inactive" <?= $doctor['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Profile & Gallery Images Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Images</h4>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">Profile Image</label>
                                        <div class="file-upload mb-2">
                                            <label class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Choose New Profile Image
                                                <input type="file" name="profile_image" class="file-upload-input" accept="image/*">
                                            </label>
                                        </div>
                                        <?php if (!empty($doctor['profile_image'])): ?>
                                            <div class="current-image-section">
                                                <p class="mb-1">Current Image:</p>
                                                <img src="<?= $doctor['profile_image'] ?>" alt="Current Profile" class="image-preview">
                                            </div>
                                        <?php endif; ?>
                                        <div id="profilePreview"></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">Gallery Images</label>
                                        <div class="file-upload mb-2">
                                            <label class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Add More Gallery Images
                                                <input type="file" name="gallery_images[]" class="file-upload-input" accept="image/*" multiple>
                                            </label>
                                        </div>
                                        
                                        <div class="current-image-section">
                                            <p class="mb-1">Current Gallery Images:</p>
                                            <?php if (!empty($current_gallery)): ?>
                                                <div class="gallery-preview">
                                                    <?php foreach ($current_gallery as $gallery_image): ?>
                                                        <div class="gallery-item">
                                                            <img src="<?= $gallery_image ?>" alt="Gallery Image">
                                                            <button type="submit" 
                                                                    name="remove_gallery_image" 
                                                                    value="<?= $gallery_image ?>" 
                                                                    class="remove-gallery-btn"
                                                                    onclick="return confirm('Are you sure you want to remove this image?')"
                                                                    title="Remove Image">
                                                                ×
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="gallery-actions">
                                                    <small class="text-muted">Click the × button to remove images</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-gallery">
                                                    No gallery images added yet.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div id="galleryPreview" class="gallery-preview"></div>
                                    </div>
                                </div>

                                <!-- Bio Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">Bio Information</h4>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Short Bio</label>
                                        <textarea class="form-control" name="short_bio" rows="3" placeholder="Brief introduction"><?= $doctor['short_bio'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Long Bio/Description</label>
                                        <textarea class="form-control" name="long_bio" id="longBio" rows="6"><?= $doctor['long_bio'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Education</label>
                                        <textarea class="form-control" name="education" rows="3" placeholder="Educational background"><?=$doctor['education'] ??'' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Area of Expertise</label>
                                        <textarea class="form-control" name="area_of_expertise" rows="3" placeholder="Specific areas of expertise"><?= $doctor['area_of_expertise'] ?? '' ?></textarea>
                                    </div>
                                </div>

                                <!-- SEO Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h4 class="section-title">SEO Information</h4>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" name="meta_title" value="<?= $doctor['meta_title'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Slug URL</label>
                                        <input type="text" class="form-control" name="slug_url" value="<?= $doctor['slug_url'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Keywords</label>
                                        <textarea class="form-control" name="meta_keywords" rows="2"><?= $doctor['meta_keywords'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Description</label>
                                        <textarea class="form-control" name="meta_description" rows="2"><?= $doctor['meta_description'] ?? '' ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-2"></i> Update Doctor
                                        </button>
                                        <a href="doctors-list.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-2"></i> Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-6">
                        <div class="doctor-form">
                            <h4 class="section-title">Uploaded Documents (<?= count($doctor_documents) ?>)</h4>
                            <?php if (empty($doctor_documents)): ?>
                                <p class="text-muted mb-0">No documents uploaded.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle tbl-admin">
                                        <thead><tr><th>Type</th><th>File</th><th>Uploaded</th><th>Status</th><th>Action</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($doctor_documents as $doc): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(strtoupper($doc['document_type'])) ?></td>
                                                <td><a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($doc['document_name']) ?></a></td>
                                                <td><span class="cell-sub"><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></span></td>
                                                <td><?= $doc['is_verified'] ? '<span class="pill pill-success">Verified</span>' : '<span class="pill pill-muted">Unverified</span>' ?></td>
                                                <td>
                                                    <?php if ($doc['is_verified']): ?>
                                                        <a href="doctor-edit.php?id=<?= $doctor_id ?>&unverify_doc=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary">Unmark</a>
                                                    <?php else: ?>
                                                        <a href="doctor-edit.php?id=<?= $doctor_id ?>&verify_doc=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-success">Mark Verified</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="doctor-form">
                            <h4 class="section-title">Payment History (<?= count($doctor_payments) ?>)</h4>
                            <?php if (empty($doctor_payments)): ?>
                                <p class="text-muted mb-0">No payments yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle tbl-admin">
                                        <thead><tr><th>Plan</th><th>Amount</th><th>Status</th><th>Period</th><th>Payment ID</th><th>Purchased On</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($doctor_payments as $pay):
                                            $payPill = [
                                                'paid'    => 'pill-success',
                                                'pending' => 'pill-warn',
                                                'failed'  => 'pill-danger',
                                            ][$pay['status']] ?? 'pill-muted';
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($pay['plan_name'] ?? ('Plan #' . $pay['plan_id'])) ?></td>
                                                <td>₹<?= number_format($pay['amount'], 2) ?></td>
                                                <td><span class="pill <?= $payPill ?>"><?= ucfirst($pay['status']) ?></span></td>
                                                <td>
                                                    <?php if ($pay['starts_at'] && $pay['expires_at']): ?>
                                                        <span class="cell-sub"><?= date('d M Y', strtotime($pay['starts_at'])) ?> – <?= date('d M Y', strtotime($pay['expires_at'])) ?></span>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td><small class="text-muted"><?= htmlspecialchars($pay['razorpay_payment_id'] ?: '—') ?></small></td>
                                                <td><span class="cell-sub"><?= date('d M Y, h:i A', strtotime($pay['created_at'])) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="doctor-form">
                            <h4 class="section-title">Appointments</h4>
                            <?php
                            $ap_scope = 'doctor';
                            $ap_id = (int) $doctor_id;
                            $ap_limit = 15;
                            include __DIR__ . '/inc/appointments-panel.php';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>

    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
    <script>
        CKEDITOR.replace('longBio');
        
        document.addEventListener("DOMContentLoaded", function() {
            const checkboxes = document.querySelectorAll('.dept-checkbox');
            const selectedContainer = document.getElementById('selectedDepartments');
            const dropdownText = document.getElementById('dropdownText');

            function updateSelectedDepartments() {
                const selected = [];
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        const label = cb.parentElement.textContent.trim();
                        selected.push(label);
                    }
                });

                if (selected.length > 0) {
                    selectedContainer.innerHTML = selected.map(dept =>
                        `<span class="badge bg-primary me-1 mb-1">${dept}</span>`
                    ).join('');
                    dropdownText.textContent = `${selected.length} department(s) selected`;
                } else {
                    selectedContainer.innerHTML = '<small class="text-muted">No departments selected</small>';
                    dropdownText.textContent = 'Select Departments';
                }
            }

            checkboxes.forEach(cb => {
                cb.addEventListener("change", updateSelectedDepartments);
            });

            // Initialize on load
            updateSelectedDepartments();

            // Prevent dropdown from closing when clicking checkboxes
            document.querySelector('.multi-select-dropdown .dropdown-menu').addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Preview profile image before upload
            document.querySelector('input[name="profile_image"]').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const previewContainer = document.getElementById('profilePreview');
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.innerHTML = `<p class="mb-1">New Image Preview:</p><img src="${e.target.result}" class="image-preview" alt="Profile Preview">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.innerHTML = '';
                }
            });
            
            // Preview gallery images
            document.querySelector('input[name="gallery_images[]"]').addEventListener('change', function(e) {
                const files = e.target.files;
                const previewContainer = document.getElementById('galleryPreview');
                previewContainer.innerHTML = '';
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '80px';
                        img.style.height = '80px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '4px';
                        img.style.border = '1px solid #ddd';
                        previewContainer.appendChild(img);
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Auto-generate slug from name
            document.querySelector('input[name="name"]').addEventListener('blur', function() {
                const name = this.value.trim();
                const slugInput = document.querySelector('input[name="slug_url"]');
                
                if (name && (!slugInput.value || slugInput.value === '')) {
                    const slug = name.toLowerCase()
                        .replace(/[^a-z0-9 -]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                    slugInput.value = slug;
                }
            });

            // Form validation
            document.getElementById('doctorForm').addEventListener('submit', function(e) {
                const selectedDepartments = document.querySelectorAll('.dept-checkbox:checked').length;
                if (selectedDepartments === 0) {
                    e.preventDefault();
                    alert('Please select at least one department');
                    return false;
                }
            });
        });
    </script>
</body>
</html>