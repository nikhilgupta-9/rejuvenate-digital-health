<?php
include_once "../../config/connect.php";
include_once "auth.php";
require_once __DIR__ . '/../../lib/SchoolMembership.php';

/* Fetch full student details with school metadata */
$stmt = $conn->prepare("SELECT sm.*, s.school_name, s.logo AS school_logo, s.city AS school_city, s.state AS school_state, s.board AS school_board, s.school_uid 
                        FROM school_members sm 
                        JOIN schools s ON sm.school_id = s.id 
                        WHERE sm.id = ?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

/* Fetch member health profile */
$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id = ?");
$hp_stmt->bind_param('i', $student_id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

/* Digital Health ID Card — lazily generate a share token so the QR link
   can't be guessed (unlike sequential member_uid). */
if (empty($student['share_token'])) {
  $share_token = bin2hex(random_bytes(20));
  $tok_stmt = $conn->prepare("UPDATE school_members SET share_token = ? WHERE id = ?");
  $tok_stmt->bind_param('si', $share_token, $student_id);
  $tok_stmt->execute();
  $student['share_token'] = $share_token;
}
$health_card_url = rtrim(BASE_URL, '/') . '/school/student/health-card.php?t=' . $student['share_token'];

/* Active Membership status */
$active_membership = school_active_membership($conn, $student_id);

/* Count student clinical records */
$rx_cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM school_member_prescriptions WHERE member_id = ?");
$rx_cnt_stmt->bind_param('i', $student_id);
$rx_cnt_stmt->execute();
$rx_count = (int)($rx_cnt_stmt->get_result()->fetch_row()[0] ?? 0);

$doc_cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM school_member_documents WHERE member_id = ?");
$doc_cnt_stmt->bind_param('i', $student_id);
$doc_cnt_stmt->execute();
$doc_count = (int)($doc_cnt_stmt->get_result()->fetch_row()[0] ?? 0);

$cert_cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM school_member_certificates WHERE member_id = ?");
$cert_cnt_stmt->bind_param('i', $student_id);
$cert_cnt_stmt->execute();
$cert_count = (int)($cert_cnt_stmt->get_result()->fetch_row()[0] ?? 0);

$total_records = $rx_count + $doc_count + $cert_count;

/* BMI Calculation */
$bmi = null; $bmi_lbl = ''; $bmi_col = '#6b7280'; $bmi_badge = 'bg-secondary';
if ($hp && !empty($hp['height_cm']) && !empty($hp['weight_kg']) && (float)$hp['height_cm'] > 0) {
  $h_m = (float)$hp['height_cm'] / 100;
  $bmi = round((float)$hp['weight_kg'] / ($h_m ** 2), 1);
  if ($bmi < 18.5) {
    $bmi_lbl = 'Underweight'; $bmi_col = '#d97706'; $bmi_badge = 'bg-warning text-dark';
  } elseif ($bmi < 25) {
    $bmi_lbl = 'Normal'; $bmi_col = '#16a34a'; $bmi_badge = 'bg-success';
  } elseif ($bmi < 30) {
    $bmi_lbl = 'Overweight'; $bmi_col = '#ea580c'; $bmi_badge = 'bg-warning text-dark';
  } else {
    $bmi_lbl = 'Obese'; $bmi_col = '#dc2626'; $bmi_badge = 'bg-danger';
  }
}

$blood_group = !empty($student['blood_group']) ? $student['blood_group'] : (!empty($hp['blood_group']) ? $hp['blood_group'] : '');
/* Same source + same "nothing on file" behaviour as health.php/dashboard.php — never invent
   a name or silently substitute the student's own number as the emergency contact. */
$emergency_name = !empty($hp['emergency_contact_name']) ? $hp['emergency_contact_name'] : (!empty($hp['emergency_contact']) ? $hp['emergency_contact'] : '');
$emergency_phone = !empty($hp['emergency_contact_phone']) ? $hp['emergency_contact_phone'] : (!empty($hp['emergency_phone']) ? $hp['emergency_phone'] : '');

$success = $error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = $_POST['action'] ?? '';

  /* Update Profile Photo */
  if ($action === 'update_photo') {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg', 'jpeg', 'png', 'webp'];
      if (!in_array($ext, $allowed)) {
        $error = "Photo must be a JPG, PNG or WebP file.";
      } elseif ($_FILES['profile_pic']['size'] > 3 * 1024 * 1024) {
        $error = "Photo size must be under 3 MB.";
      } else {
        $upload_dir = __DIR__ . '/../../uploads/members/';
        if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0755, true);
        }
        $filename = 'student_' . $student_id . '_' . time() . '.' . $ext;
        $dest = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
          if (!empty($student['profile_pic']) && str_starts_with($student['profile_pic'], 'uploads/members/')) {
            $old = __DIR__ . '/../../' . $student['profile_pic'];
            if (file_exists($old)) @unlink($old);
          }
          $rel_path = 'uploads/members/' . $filename;
          $upd = $conn->prepare("UPDATE school_members SET profile_pic = ? WHERE id = ?");
          $upd->bind_param('si', $rel_path, $student_id);
          $upd->execute();
          $success = "Profile photo updated successfully.";
          $stmt->execute();
          $student = $stmt->get_result()->fetch_assoc();
        } else {
          $error = "Failed to upload photo. Please try again.";
        }
      }
    } else {
      $error = "Please select a valid image file.";
    }
  }

  /* Remove Profile Photo */
  if ($action === 'remove_photo') {
    if (!empty($student['profile_pic']) && str_starts_with($student['profile_pic'], 'uploads/members/')) {
      $old = __DIR__ . '/../../' . $student['profile_pic'];
      if (file_exists($old)) @unlink($old);
    }
    $upd = $conn->prepare("UPDATE school_members SET profile_pic = NULL WHERE id = ?");
    $upd->bind_param('i', $student_id);
    $upd->execute();
    $success = "Profile photo removed.";
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
  }

  /* Change Password */
  if ($action === 'change_password') {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $cnf = $_POST['confirm_password'] ?? '';

    if (!$student['password'] || !password_verify($cur, $student['password'])) {
      $error = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
      $error = "New password must be at least 6 characters.";
    } elseif ($new !== $cnf) {
      $error = "New passwords do not match.";
    } else {
      $hash = password_hash($new, PASSWORD_DEFAULT);
      $upd = $conn->prepare("UPDATE school_members SET password = ? WHERE id = ?");
      $upd->bind_param('si', $hash, $student_id);
      $upd->execute();
      $success = "Password updated successfully.";
      $stmt->execute();
      $student = $stmt->get_result()->fetch_assoc();
    }
  }

  /* Update Contact Details */
  if ($action === 'update_contact') {
    $phone = trim($_POST['phone'] ?? '');
    $parent_mobile = trim($_POST['parent_mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($phone && !preg_match('/^[6-9]\d{9}$/', $phone)) {
      $error = "Enter a valid 10-digit Indian mobile number for Student.";
    } elseif ($parent_mobile && !preg_match('/^[6-9]\d{9}$/', $parent_mobile)) {
      $error = "Enter a valid 10-digit Indian mobile number for Parent / Guardian.";
    } else {
      $upd = $conn->prepare("UPDATE school_members SET phone = ?, parent_mobile = ?, address = ? WHERE id = ?");
      $upd->bind_param('sssi', $phone, $parent_mobile, $address, $student_id);
      $upd->execute();
      $success = "Contact and emergency details updated successfully.";
      $stmt->execute();
      $student = $stmt->get_result()->fetch_assoc();
      $emergency_phone = !empty($hp['emergency_contact_phone']) ? $hp['emergency_contact_phone'] : (!empty($hp['emergency_phone']) ? $hp['emergency_phone'] : '');
    }
  }
}

$has_photo = !empty($student['profile_pic']) && file_exists(__DIR__ . '/../../' . $student['profile_pic']);
$photo_src = $has_photo ? (BASE_URL . $student['profile_pic']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../../util/function.php'; } ?>
<head>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Profile &amp; Digital Health ID | <?= htmlspecialchars($student['school_name'] ?? $student_school) ?></title>
  
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">

  <style>
    :root {
      /* Matches school.css (the site-wide theme) exactly — this page must not drift from it. */
      --primary: #0C74C5;
      --primary-dk: #0a5fa0;
      --primary-rgb: 12, 116, 197;
      --accent: #02c9b8;
      --accent-dk: #01a89a;
      --navy-900: #06233d;
      --navy-800: #0a3356;
      --slate-50: #f8fafc;
      --slate-100: #f1f5f9;
      --slate-200: #e2e8f0;
      --slate-600: #475569;
      --slate-700: #334155;
      --slate-800: #1f2937;
    }

    /* Page chrome (headings, cards, buttons) uses the same font as the rest of the student
       portal. 'Plus Jakarta Sans' / 'JetBrains Mono' are kept only for the digital ID card
       artwork itself (.id-card-*, #idCardPrintSheet, #idCardFrontExport/#idCardBackExport),
       which is deliberately styled like a real plastic ID card. */
    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background-color: #f4f7fb;
      color: var(--slate-800);
      margin: 0;
    }

    .school-content {
      padding: 24px;
      max-width: 1400px;
      margin-left: var(--sidebar-w);
      margin-top: var(--topbar-h);
      min-height: calc(100vh - var(--topbar-h));
    }

    /* Page Header Banner — flat theme color, no gradient (matches dashboard.php / school.css) */
    .profile-hero-card {
      background: var(--primary);
      border-radius: 18px;
      color: #fff;
      padding: 24px 28px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 30px -5px rgba(12, 116, 197, 0.35);
      margin-bottom: 24px;
    }
    .profile-hero-card::after {
      content: '';
      position: absolute;
      right: -50px;
      top: -50px;
      width: 240px;
      height: 240px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
      pointer-events: none;
    }
    .profile-hero-card::before {
      content: '';
      position: absolute;
      left: 30%;
      bottom: -60px;
      width: 200px;
      height: 200px;
      background: rgba(2, 201, 184, 0.12);
      border-radius: 50%;
      pointer-events: none;
    }

    .hero-avatar-wrapper {
      position: relative;
      width: 78px;
      height: 78px;
      flex-shrink: 0;
    }
    .hero-avatar {
      width: 78px;
      height: 78px;
      border-radius: 20px;
      object-fit: cover;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 800;
      color: #fff;
      border: 3px solid rgba(255, 255, 255, 0.85);
      box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }
    .hero-avatar-upload-btn {
      position: absolute;
      right: -4px;
      bottom: -4px;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #02c9b8;
      color: #fff;
      border: 2px solid #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .75rem;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(0,0,0,0.25);
      transition: all 0.2s;
    }
    .hero-avatar-upload-btn:hover {
      background: #019487;
      transform: scale(1.1);
    }

    .hero-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .73rem;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.18);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      color: #fff;
    }

    /* Stat Overview Cards */
    .stat-metric-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px 20px;
      border: 1px solid #e9edf3;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      display: flex;
      align-items: center;
      gap: 16px;
      transition: transform 0.2s, box-shadow 0.2s;
      height: 100%;
    }
    .stat-metric-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(12, 116, 197, 0.08);
    }
    .stat-metric-icon {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      flex-shrink: 0;
    }

    /* Professional UI Cards */
    .pro-card {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e9edf3;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
      margin-bottom: 22px;
      overflow: hidden;
    }
    .pro-card-header {
      padding: 16px 22px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fafbfc;
    }
    .pro-card-title {
      font-size: .88rem;
      font-weight: 700;
      color: var(--slate-800);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 9px;
      letter-spacing: .2px;
    }
    .pro-card-body {
      padding: 22px;
    }

    /* ========================================================
       WORLD-CLASS DIGITAL HEALTH ID CARD (FRONT & BACK)
       Standard CR80 Credit Card Ratio ~ 85.6mm x 54mm
       ======================================================== */
    .id-card-stage {
      perspective: 1200px;
      width: 100%;
      max-width: 500px;
      margin: 0 auto 16px;
    }
    .id-card-3d {
      position: relative;
      width: 100%;
      aspect-ratio: 1.586 / 1;
      min-height: 315px;
      transition: transform 0.75s cubic-bezier(0.34, 1.56, 0.64, 1);
      transform-style: preserve-3d;
    }
    .id-card-3d.flipped {
      transform: rotateY(180deg);
    }

    /* Light-mode card — flat white, theme-colored border & accents, no gradients
       (matches school.css: "Theme: White | #0C74C5 | #02c9b8, no gradients"). */
    .id-card-face {
      position: absolute;
      inset: 0;
      border-radius: 20px;
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 18px 20px 14px;
      color: var(--slate-800);
      background: #ffffff;
      border: 1px solid var(--slate-200);
      box-shadow: 0 16px 36px -10px rgba(12, 116, 197, 0.25);
      user-select: none;
    }

    .id-card-face.id-card-back {
      transform: rotateY(180deg);
      background: var(--slate-50);
    }

    /* Flat corner accents (solid colour, low opacity — no gradient) */
    .id-card-face::before {
      content: '';
      position: absolute;
      right: -40px; top: -40px;
      width: 140px; height: 140px;
      background: rgba(12, 116, 197, 0.06);
      border-radius: 50%;
      pointer-events: none;
    }
    .id-card-face::after {
      content: '';
      position: absolute;
      left: -30px; bottom: -40px;
      width: 120px; height: 120px;
      background: rgba(2, 201, 184, 0.07);
      border-radius: 50%;
      pointer-events: none;
    }

    /* Card Header Bar */
    .id-card-hdr {
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--slate-200);
      padding-bottom: 10px;
    }
    .id-brand-box {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .id-brand-logo-img {
      height: 26px;
      width: auto;
    }
    .id-brand-text {
      line-height: 1.1;
    }
    .id-brand-title {
      font-size: .86rem;
      font-weight: 800;
      letter-spacing: .6px;
      color: var(--slate-800);
    }
    .id-brand-sub {
      font-size: .56rem;
      font-weight: 700;
      letter-spacing: 1.1px;
      color: var(--accent-dk);
      text-transform: uppercase;
    }

    .id-chip-contactless {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .id-chip-svg {
      width: 38px;
      height: 28px;
      filter: drop-shadow(0 1px 3px rgba(0,0,0,0.15));
    }
    .id-nfc-icon {
      font-size: 1.1rem;
      color: var(--slate-600);
      transform: rotate(90deg);
    }

    /* School Affiliation Strip */
    .id-school-strip {
      position: relative;
      z-index: 2;
      background: rgba(12, 116, 197, 0.07);
      border-radius: 8px;
      padding: 4px 10px;
      margin: 6px 0 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: .67rem;
      font-weight: 600;
      color: var(--slate-700);
      border: 1px solid rgba(12, 116, 197, 0.12);
    }

    /* Card Middle Content (Front) */
    .id-card-mid {
      position: relative;
      z-index: 2;
      display: flex;
      gap: 14px;
      align-items: center;
      flex: 1;
    }
    .id-photo-col {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }
    .id-photo-box {
      width: 72px;
      height: 82px;
      border-radius: 12px;
      overflow: hidden;
      border: 2px solid var(--primary);
      box-shadow: 0 4px 10px rgba(12,116,197,0.15);
      background: #eaf4fd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--primary);
    }
    .id-photo-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .id-blood-tag {
      background: #dc2626;
      color: #fff;
      font-size: .62rem;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      box-shadow: 0 2px 6px rgba(220, 38, 38, 0.4);
      white-space: nowrap;
    }

    .id-info-col {
      flex: 1;
      min-width: 0;
    }
    .id-stu-name {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--slate-800);
      line-height: 1.2;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .id-stu-role-badge {
      display: inline-block;
      font-size: .56rem;
      font-weight: 800;
      letter-spacing: .8px;
      text-transform: uppercase;
      background: #02c9b8;
      color: #06233d;
      padding: 2px 7px;
      border-radius: 6px;
      margin-bottom: 6px;
    }

    .id-meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3px 8px;
      font-size: .66rem;
    }
    .id-meta-item {
      display: flex;
      gap: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .id-meta-lbl {
      color: var(--slate-600);
      font-weight: 500;
    }
    .id-meta-val {
      color: var(--slate-800);
      font-weight: 700;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Card Footer Bar (Front) */
    .id-card-ftr {
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid var(--slate-200);
      padding-top: 8px;
      margin-top: 4px;
    }
    .id-uid-chip {
      font-family: 'JetBrains Mono', monospace;
      font-size: .68rem;
      font-weight: 700;
      color: var(--slate-800);
      background: rgba(12, 116, 197, 0.07);
      padding: 3px 8px;
      border-radius: 6px;
      border: 1px solid rgba(12, 116, 197, 0.15);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .id-hologram-seal {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: .56rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--accent-dk);
      background: rgba(2, 201, 184, 0.1);
      border: 1px solid rgba(2, 201, 184, 0.3);
      border-radius: 20px;
      padding: 3px 9px;
    }

    /* ========================================================
       CARD BACK SIDE — EMERGENCY MEDICAL PROFILE & QR
       ======================================================== */
    .id-back-body {
      position: relative;
      z-index: 2;
      display: flex;
      gap: 16px;
      align-items: center;
      flex: 1;
      margin: 8px 0;
    }
    .id-qr-box {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: 12px;
      padding: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    }
    .id-qr-box #cardBackQr, .id-qr-box #cardBackQrExport {
      line-height: 0;
    }
    .id-qr-label {
      font-size: .52rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: #06233d;
      margin-top: 5px;
    }

    .id-emergency-details {
      flex: 1;
      min-width: 0;
    }
    .id-emg-title {
      font-size: .68rem;
      font-weight: 800;
      letter-spacing: .6px;
      text-transform: uppercase;
      color: var(--accent-dk);
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .id-emg-row {
      display: flex;
      justify-content: space-between;
      font-size: .65rem;
      padding: 3px 0;
      border-bottom: 1px dashed var(--slate-200);
    }
    .id-emg-row:last-child {
      border-bottom: none;
    }
    .id-emg-lbl {
      color: var(--slate-600);
    }
    .id-emg-val {
      font-weight: 700;
      color: var(--slate-800);
      text-align: right;
    }

    .id-back-disclaimer {
      font-size: .53rem;
      color: var(--slate-600);
      text-align: center;
      line-height: 1.3;
      border-top: 1px solid var(--slate-200);
      padding-top: 6px;
      margin-top: 4px;
    }

    /* ID Card Action Buttons */
    .id-action-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 20px;
    }
    .id-action-bar .btn {
      font-size: .78rem;
      font-weight: 600;
      border-radius: 10px;
      padding: 8px 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    /* Card Side Toggle Pills */
    .side-toggle-group {
      display: inline-flex;
      background: #e2e8f0;
      border-radius: 30px;
      padding: 3px;
      margin-bottom: 14px;
    }
    .side-toggle-btn {
      border: none;
      background: transparent;
      padding: 5px 16px;
      border-radius: 25px;
      font-size: .75rem;
      font-weight: 700;
      color: var(--slate-700);
      cursor: pointer;
      transition: all 0.2s;
    }
    .side-toggle-btn.active {
      background: var(--primary);
      color: #fff;
      box-shadow: 0 2px 8px rgba(12, 116, 197, 0.3);
    }

    /* Emergency Vitals Box */
    .vital-tile {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px;
      text-align: center;
    }
    .vital-tile-lbl {
      font-size: .65rem;
      font-weight: 600;
      color: var(--slate-600);
      text-transform: uppercase;
      margin-bottom: 3px;
    }
    .vital-tile-val {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--slate-800);
    }

    /* Password strength bar */
    .pw-strength-track {
      height: 4px;
      background: #e2e8f0;
      border-radius: 2px;
      margin-top: 6px;
      overflow: hidden;
    }
    .pw-strength-bar {
      height: 100%;
      width: 0%;
      transition: width 0.3s, background-color 0.3s;
    }

    /* Toast Notification */
    .copy-toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #0f172a;
      color: #fff;
      border-radius: 12px;
      padding: 12px 20px;
      font-size: .84rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      z-index: 9999;
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      pointer-events: none;
    }
    .copy-toast.show {
      transform: translateY(0);
      opacity: 1;
    }

    /* ========================================================
       PRINT READY TEMPLATE (Formatted for Plastic Badge / A4)
       ======================================================== */
    #idCardPrintSheet {
      display: none;
    }

    @media print {
      body * {
        visibility: hidden !important;
      }
      #idCardPrintSheet, #idCardPrintSheet * {
        visibility: visible !important;
      }
      #idCardPrintSheet {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: #fff;
        padding: 20px;
      }
      .school-sidebar, .school-topbar, .btn, .alert {
        display: none !important;
      }
    }
  </style>
</head>

<body>
  <?php $active_page = 'profile'; $base_path = ''; include '../inc/sidebar-student.php'; ?>

  <!-- Topbar -->
  <div class="school-topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div>
        <div style="font-size:1rem;font-weight:700;color:var(--slate-800);">
          <i class="fas fa-id-badge text-primary-theme me-2"></i>My Profile &amp; Digital Health ID
        </div>
        <div style="font-size:.73rem;color:#64748b;">
          <?= htmlspecialchars($student['school_name'] ?? $student_school) ?> &middot; Student Account
        </div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="health.php" class="btn btn-sm btn-outline-primary d-none d-md-inline-flex align-items-center gap-1">
        <i class="fas fa-heartbeat"></i><span>Health Profile</span>
      </a>
      <div class="rounded-circle avatar-circle" style="width:34px;height:34px;font-size:.85rem;flex-shrink:0;">
        <?= strtoupper(substr($student['name'] ?? $student_name, 0, 1)) ?>
      </div>
      <a href="logout.php" class="btn btn-sm btn-outline-danger" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>

  <main class="school-content">

    <!-- Breadcrumb -->
    <nav class="mb-3" aria-label="breadcrumb">
      <ol class="breadcrumb mb-0" style="font-size:.78rem;">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i>Dashboard</a></li>
        <li class="breadcrumb-item active text-primary fw-semibold" aria-current="page">Student Profile &amp; ID Card</li>
      </ol>
    </nav>

    <!-- Alerts -->
    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert" style="font-size:.85rem;background:#ecfdf5;color:#065f46;">
        <i class="fas fa-check-circle me-2 text-success"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert" style="font-size:.85rem;background:#fef2f2;color:#991b1b;">
        <i class="fas fa-exclamation-circle me-2 text-danger"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Student Hero Banner -->
    <div class="profile-hero-card">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="hero-avatar-wrapper">
            <?php if ($has_photo): ?>
              <img src="<?= $photo_src ?>" alt="Photo" class="hero-avatar">
            <?php else: ?>
              <div class="hero-avatar"><?= strtoupper(substr($student['name'] ?? $student_name, 0, 1)) ?></div>
            <?php endif; ?>
            <button type="button" class="hero-avatar-upload-btn" data-bs-toggle="modal" data-bs-target="#photoModal" title="Change Photo">
              <i class="fas fa-camera"></i>
            </button>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <h2 class="fw-bold mb-0 text-white" style="font-size:1.05rem;"><?= htmlspecialchars($student['name'] ?? $student_name) ?></h2>
              <span class="badge bg-white text-primary fw-bold" style="font-size:.68rem;">STUDENT</span>
              <?php if (!empty($student['blood_group'])): ?>
                <span class="badge bg-danger text-white fw-bold" style="font-size:.68rem;"><i class="fas fa-tint me-1"></i><?= htmlspecialchars($student['blood_group']) ?></span>
              <?php endif; ?>
            </div>
            <div class="text-white-50 mt-1" style="font-size:.82rem;">
              <?php if (!empty($student['class'])): ?>Class <?= htmlspecialchars($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) ?><?php endif; ?>
              <?php if (!empty($student['roll_number'])): ?> &middot; Roll: <?= htmlspecialchars($student['roll_number']) ?><?php endif; ?>
              &middot; <?= htmlspecialchars($student['school_name'] ?? $student_school) ?>
            </div>
            <div class="mt-2 d-flex gap-2 flex-wrap">
              <span class="hero-badge-pill"><i class="fas fa-fingerprint text-accent"></i><?= htmlspecialchars($student['member_uid'] ?? $student_uid) ?></span>
              <?php if (!empty($student['abha_linked'])): ?>
                <span class="hero-badge-pill" style="background:rgba(0,135,90,0.35);border-color:rgba(0,135,90,0.5);">
                  <i class="fas fa-shield-alt text-success"></i>ABHA Linked
                </span>
              <?php else: ?>
                <a href="abha.php" class="hero-badge-pill text-white text-decoration-none" style="background:rgba(217,119,6,0.35);border-color:rgba(217,119,6,0.5);">
                  <i class="fas fa-plus-circle text-warning"></i>Link ABHA
                </a>
              <?php endif; ?>
              <?php if ($active_membership): ?>
                <span class="hero-badge-pill" style="background:rgba(2,201,184,0.35);border-color:rgba(2,201,184,0.5);">
                  <i class="fas fa-crown text-warning"></i>Health Pass Active
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-light btn-sm fw-bold px-3 py-2 text-primary" onclick="flipCard()">
            <i class="fas fa-sync-alt me-1"></i>Flip ID Card
          </button>
          <button type="button" class="btn btn-outline-light btn-sm fw-semibold px-3 py-2" onclick="printCard()">
            <i class="fas fa-print me-1"></i>Print Badge
          </button>
        </div>
      </div>
    </div>

    <!-- 4 Stats Overview Row -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="stat-metric-card">
          <div class="stat-metric-icon" style="background:#e0f2fe;color:#0284c7;">
            <i class="fas fa-heartbeat"></i>
          </div>
          <div>
            <div class="text-muted text-uppercase" style="font-size:.65rem;font-weight:700;letter-spacing:.6px;">Blood Group</div>
            <div class="fw-bold fs-5 text-dark"><?= $blood_group ?: '<span class="text-muted" style="font-size:.85rem;">Not set</span>' ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-metric-card">
          <div class="stat-metric-icon" style="background:#e8fdf8;color:#02c9b8;">
            <i class="fas fa-file-medical-alt"></i>
          </div>
          <div>
            <div class="text-muted text-uppercase" style="font-size:.65rem;font-weight:700;letter-spacing:.6px;">Medical Records</div>
            <div class="fw-bold fs-5 text-dark"><?= $total_records ?> <span style="font-size:.72rem;font-weight:500;" class="text-muted">Files</span></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-metric-card">
          <div class="stat-metric-icon" style="background:#fef3c7;color:#d97706;">
            <i class="fas fa-id-card"></i>
          </div>
          <div>
            <div class="text-muted text-uppercase" style="font-size:.65rem;font-weight:700;letter-spacing:.6px;">ABHA Account</div>
            <div class="fw-bold fs-6 text-dark text-truncate" style="max-width:140px;">
              <?= !empty($student['abha_linked']) ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Linked</span>' : '<span class="text-warning"><i class="fas fa-clock me-1"></i>Pending</span>' ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-metric-card">
          <div class="stat-metric-icon" style="background:#f3e8ff;color:#9333ea;">
            <i class="fas fa-shield-halved"></i>
          </div>
          <div>
            <div class="text-muted text-uppercase" style="font-size:.65rem;font-weight:700;letter-spacing:.6px;">Membership</div>
            <div class="fw-bold fs-6 text-dark text-truncate" style="max-width:140px;">
              <?= $active_membership ? htmlspecialchars($active_membership['plan_name'] ?? 'Active') : 'Standard Pass' ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="row g-4">

      <!-- LEFT COLUMN: Professional Digital Health ID Card Experience -->
      <div class="col-lg-5 col-xl-5">

        <!-- ID Card Card Container -->
        <div class="pro-card">
          <div class="pro-card-header">
            <h3 class="pro-card-title">
              <i class="fas fa-address-card text-primary"></i>Official Health ID Card
            </h3>
            <!-- Front / Back Switcher -->
            <div class="side-toggle-group">
              <button type="button" class="side-toggle-btn active" id="btnSideFront" onclick="showCardSide('front')">Front</button>
              <button type="button" class="side-toggle-btn" id="btnSideBack" onclick="showCardSide('back')">Back (QR)</button>
            </div>
          </div>

          <div class="pro-card-body text-center">

            <!-- 3D FLIP ID CARD STAGE -->
            <div class="id-card-stage">
              <div class="id-card-3d" id="healthIdCard">

                <!-- ════════ FRONT SIDE ════════ -->
                <div class="id-card-face id-card-front" id="cardFrontFace">
                  <!-- Header: Branding & Smart Chip -->
                  <div class="id-card-hdr">
                    <div class="id-brand-box">
                      <img src="<?= BASE_URL . get_header_logo() ?>" alt="Rejuvenate Digital Health" class="id-brand-logo-img">
                    </div>

                    <!-- Smart Metallic Chip & NFC Wave -->
                    <div class="id-chip-contactless">
                      <svg class="id-chip-svg" viewBox="0 0 42 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="42" height="32" rx="4" fill="url(#chipGold)"/>
                        <path d="M0 16h14m14 0h14M14 6v20m14-20v20M14 10h14M14 22h14" stroke="#8a6914" stroke-width="1.3" stroke-linecap="round"/>
                        <defs>
                          <linearGradient id="chipGold" x1="0" y1="0" x2="42" y2="32" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#f7d468"/>
                            <stop offset="0.5" stop-color="#fdba74"/>
                            <stop offset="1" stop-color="#d4af37"/>
                          </linearGradient>
                        </defs>
                      </svg>
                      <i class="fas fa-wifi id-nfc-icon" title="NFC Contactless Enabled"></i>
                    </div>
                  </div>

                  <!-- School Strip -->
                  <div class="id-school-strip">
                    <span class="text-truncate">
                      <i class="fas fa-school text-accent me-1"></i><?= htmlspecialchars($student['school_name'] ?? $student_school) ?>
                    </span>
                    <span class="badge bg-white text-dark py-1 px-2" style="font-size:.56rem;">
                      <?= htmlspecialchars($student['school_board'] ?? 'CBSE') ?>
                    </span>
                  </div>

                  <!-- Middle: Photo & Student Bio -->
                  <div class="id-card-mid">
                    <div class="id-photo-col">
                      <div class="id-photo-box">
                        <?php if ($has_photo): ?>
                          <img src="<?= $photo_src ?>" alt="Photo">
                        <?php else: ?>
                          <?= strtoupper(substr($student['name'] ?? $student_name, 0, 1)) ?>
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($student['blood_group'])): ?>
                        <div class="id-blood-tag">
                          <i class="fas fa-tint"></i><?= htmlspecialchars($student['blood_group']) ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="id-info-col text-start">
                      <div class="id-stu-name"><?= htmlspecialchars($student['name'] ?? $student_name) ?></div>
                      <span class="id-stu-role-badge">STUDENT HEALTH PASS</span>

                      <div class="id-meta-grid">
                        <div class="id-meta-item">
                          <span class="id-meta-lbl">Class:</span>
                          <span class="id-meta-val"><?= htmlspecialchars($student['class'] ? ($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) : '—') ?></span>
                        </div>
                        <div class="id-meta-item">
                          <span class="id-meta-lbl">Roll No:</span>
                          <span class="id-meta-val"><?= htmlspecialchars($student['roll_number'] ?? '—') ?></span>
                        </div>
                        <div class="id-meta-item">
                          <span class="id-meta-lbl">DOB:</span>
                          <span class="id-meta-val"><?= !empty($student['dob']) ? date('d M Y', strtotime($student['dob'])) : '—' ?></span>
                        </div>
                        <div class="id-meta-item">
                          <span class="id-meta-lbl">Gender:</span>
                          <span class="id-meta-val"><?= htmlspecialchars($student['gender'] ?? '—') ?></span>
                        </div>
                        <?php if (!empty($student['parent_mobile'])): ?>
                          <div class="id-meta-item" style="grid-column: span 2;">
                            <span class="id-meta-lbl">Parent:</span>
                            <span class="id-meta-val">+91 <?= htmlspecialchars($student['parent_mobile']) ?></span>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <!-- Footer: Monospace UID & Verified Watermark -->
                  <div class="id-card-ftr">
                    <div class="id-uid-chip">
                      <span class="text-accent">UID:</span><?= htmlspecialchars($student['member_uid'] ?? $student_uid) ?>
                    </div>
                    <?php if (!empty($student['abha_id'])): ?>
                      <div class="id-hologram-seal" style="background:rgba(0,135,90,0.3);border-color:rgba(0,135,90,0.45);">
                        <i class="fas fa-shield-alt text-success"></i>ABHA: <?= htmlspecialchars($student['abha_id']) ?>
                      </div>
                    <?php else: ?>
                      <div class="id-hologram-seal">
                        <i class="fas fa-check-shield text-accent"></i>VERIFIED HEALTH PASS
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- ════════ BACK SIDE ════════ -->
                <div class="id-card-face id-card-back" id="cardBackFace">
                  <!-- Header: Emergency Directory -->
                  <div class="id-card-hdr">
                    <div class="id-brand-box">
                      <div class="id-brand-text">
                        <div class="id-brand-title">EMERGENCY CLINICAL PROFILE</div>
                        <div class="id-brand-sub">REJUVENATE DIGITAL HEALTH NETWORK</div>
                      </div>
                    </div>
                    <div class="badge bg-danger text-white py-1 px-2" style="font-size:.6rem;">
                      <i class="fas fa-ambulance me-1"></i>FIRST RESPONDER
                    </div>
                  </div>

                  <!-- Back Body: Scannable QR & Vitals -->
                  <div class="id-back-body">
                    <!-- High Contrast QR Code -->
                    <div class="id-qr-box">
                      <div id="cardBackQr"></div>
                      <div class="id-qr-label"><i class="fas fa-qrcode me-1"></i>SCAN FOR PROFILE</div>
                    </div>

                    <!-- Clinical Vitals & Emergency Contacts -->
                    <div class="id-emergency-details text-start">
                      <div class="id-emg-title">
                        <i class="fas fa-notes-medical"></i>Medical &amp; Contact Alert
                      </div>
                      <div class="id-emg-row">
                        <span class="id-emg-lbl">Blood Group</span>
                        <span class="id-emg-val text-danger fw-bold"><?= $blood_group ?: 'Not recorded' ?></span>
                      </div>
                      <div class="id-emg-row">
                        <span class="id-emg-lbl">Emergency Contact</span>
                        <span class="id-emg-val"><?= $emergency_name ? htmlspecialchars($emergency_name) : 'Not recorded' ?></span>
                      </div>
                      <div class="id-emg-row">
                        <span class="id-emg-lbl">Contact Phone</span>
                        <span class="id-emg-val text-accent"><?= $emergency_phone ? ('+91 ' . htmlspecialchars($emergency_phone)) : 'Not recorded' ?></span>
                      </div>
                      <div class="id-emg-row">
                        <span class="id-emg-lbl">Allergies</span>
                        <span class="id-emg-val text-truncate" style="max-width:120px;"><?= !empty($hp['known_allergies']) ? htmlspecialchars($hp['known_allergies']) : 'None reported' ?></span>
                      </div>
                      <div class="id-emg-row">
                        <span class="id-emg-lbl">Conditions</span>
                        <span class="id-emg-val text-truncate" style="max-width:120px;"><?= !empty($hp['chronic_conditions']) ? htmlspecialchars($hp['chronic_conditions']) : 'None recorded' ?></span>
                      </div>
                    </div>
                  </div>

                  <!-- Footer Disclaimer -->
                  <div class="id-back-disclaimer">
                    In emergency, medical professionals &amp; school authorities may scan the QR code to access life-saving health data.<br>
                    <strong>24x7 Helpline:</strong> support@rejuvenatedigitalhealth.com &middot; <strong>rejuvenatedigitalhealth.com</strong>
                  </div>
                </div>

              </div>
            </div>

            <!-- ID Card Action Buttons -->
            <div class="id-action-bar justify-content-center">
              <button type="button" class="btn btn-primary" onclick="flipCard()">
                <i class="fas fa-sync-alt"></i>Flip Card
              </button>
              <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                  <i class="fas fa-download"></i>Download
                </button>
                <ul class="dropdown-menu shadow-sm" style="font-size:.82rem;">
                  <li><a class="dropdown-item" href="javascript:void(0)" onclick="downloadCardFace('front')"><i class="fas fa-image me-2 text-primary"></i>Download Front Side (PNG)</a></li>
                  <li><a class="dropdown-item" href="javascript:void(0)" onclick="downloadCardFace('back')"><i class="fas fa-qrcode me-2 text-accent"></i>Download Back Side (QR)</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="javascript:void(0)" onclick="downloadCombinedBadge()"><i class="fas fa-id-card me-2 text-success"></i>Download Full Badge (Front + Back)</a></li>
                </ul>
              </div>
              <button type="button" class="btn btn-outline-secondary" onclick="printCard()">
                <i class="fas fa-print"></i>Print Badge
              </button>
              <button type="button" class="btn btn-outline-dark" id="copyShareLinkBtn">
                <i class="fas fa-link"></i>Copy Link
              </button>
              <a href="<?= htmlspecialchars($health_card_url) ?>" target="_blank" class="btn btn-outline-info">
                <i class="fas fa-external-link-alt"></i>View Emergency Page
              </a>
            </div>

            <div class="alert alert-light border text-start p-3 mb-0" style="font-size:.75rem;border-radius:12px;background:#f9fafb;">
              <div class="d-flex gap-2">
                <i class="fas fa-info-circle text-primary mt-1"></i>
                <div>
                  <strong>Official Health Pass:</strong> Carry a printed card in the student's backpack or school bag. Scanning the QR code gives first responders and doctors instant access to critical blood group and allergy details during emergencies.
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Quick Emergency Vitals Snapshot -->
        <div class="pro-card">
          <div class="pro-card-header">
            <h4 class="pro-card-title">
              <i class="fas fa-heart-pulse text-danger"></i>Emergency Vitals Snapshot
            </h4>
            <a href="health.php" class="btn btn-sm btn-link text-primary text-decoration-none fw-semibold" style="font-size:.78rem;">
              Full Health Profile &rarr;
            </a>
          </div>
          <div class="pro-card-body">
            <div class="row g-2 mb-3">
              <div class="col-4">
                <div class="vital-tile">
                  <div class="vital-tile-lbl">Height</div>
                  <div class="vital-tile-val"><?= !empty($hp['height_cm']) ? htmlspecialchars($hp['height_cm']) . '<span style="font-size:.7rem;font-weight:500;"> cm</span>' : '—' ?></div>
                </div>
              </div>
              <div class="col-4">
                <div class="vital-tile">
                  <div class="vital-tile-lbl">Weight</div>
                  <div class="vital-tile-val"><?= !empty($hp['weight_kg']) ? htmlspecialchars($hp['weight_kg']) . '<span style="font-size:.7rem;font-weight:500;"> kg</span>' : '—' ?></div>
                </div>
              </div>
              <div class="col-4">
                <div class="vital-tile">
                  <div class="vital-tile-lbl">BMI Index</div>
                  <div class="vital-tile-val" style="color:<?= $bmi_col ?>;">
                    <?= $bmi ?: '—' ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($bmi): ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.72rem;">
                  <span class="text-muted">Weight Category:</span>
                  <span class="badge <?= $bmi_badge ?>" style="font-size:.7rem;"><?= $bmi_lbl ?></span>
                </div>
              </div>
            <?php endif; ?>

            <div class="p-3 rounded-3 mb-2" style="background:#f8fafc;border:1px solid #edf2f7;font-size:.8rem;">
              <div class="mb-2">
                <span class="text-muted d-block" style="font-size:.68rem;font-weight:700;text-transform:uppercase;">Known Allergies:</span>
                <span class="fw-semibold text-dark"><?= !empty($hp['known_allergies']) ? nl2br(htmlspecialchars($hp['known_allergies'])) : '<span class="text-muted">None recorded</span>' ?></span>
              </div>
              <div>
                <span class="text-muted d-block" style="font-size:.68rem;font-weight:700;text-transform:uppercase;">Chronic Conditions:</span>
                <span class="fw-semibold text-dark"><?= !empty($hp['chronic_conditions']) ? nl2br(htmlspecialchars($hp['chronic_conditions'])) : '<span class="text-muted">None recorded</span>' ?></span>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background:#ecfdf5;font-size:.78rem;color:#065f46;">
              <div><i class="fas fa-shield-alt text-success me-1"></i>Verified by School Health Records</div>
              <a href="records.php" class="text-success fw-bold text-decoration-none">View Prescriptions</a>
            </div>
          </div>
        </div>

      </div>


      <!-- RIGHT COLUMN: Student Academic Details, Contact Form & Security -->
      <div class="col-lg-7 col-xl-7">

        <!-- Personal & Academic Details Card -->
        <div class="pro-card">
          <div class="pro-card-header">
            <h3 class="pro-card-title">
              <i class="fas fa-graduation-cap text-primary"></i>Academic &amp; Enrollment Information
            </h3>
            <span class="badge bg-light text-muted border" style="font-size:.7rem;">Official Record</span>
          </div>
          <div class="pro-card-body">
            <div class="row g-3">
              <div class="col-sm-6">
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
                  <div class="text-muted" style="font-size:.7rem;font-weight:600;text-transform:uppercase;">School Institution</div>
                  <div class="fw-bold text-dark mt-1" style="font-size:.88rem;"><?= htmlspecialchars($student['school_name'] ?? $student_school) ?></div>
                  <div class="text-muted" style="font-size:.74rem;"><?= htmlspecialchars($student['school_city'] ? ($student['school_city'] . ', ' . ($student['school_state'] ?? '')) : '') ?></div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
                  <div class="text-muted" style="font-size:.7rem;font-weight:600;text-transform:uppercase;">Class &amp; Section</div>
                  <div class="fw-bold text-dark mt-1" style="font-size:.88rem;">
                    <?= $student['class'] ? ('Class ' . htmlspecialchars($student['class'] . ($student['section'] ? ' (Section ' . $student['section'] . ')' : ''))) : '—' ?>
                  </div>
                  <div class="text-muted" style="font-size:.74rem;">Board: <?= htmlspecialchars($student['school_board'] ?? 'CBSE') ?></div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
                  <div class="text-muted" style="font-size:.7rem;font-weight:600;text-transform:uppercase;">Roll Number &amp; Admission</div>
                  <div class="fw-bold text-dark mt-1" style="font-size:.88rem;">Roll No: <?= htmlspecialchars($student['roll_number'] ?? '—') ?></div>
                  <div class="text-muted" style="font-size:.74rem;">Admission: <?= htmlspecialchars($student['admission_number'] ?? '—') ?></div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
                  <div class="text-muted" style="font-size:.7rem;font-weight:600;text-transform:uppercase;">Member Unique UID</div>
                  <div class="fw-bold text-dark mt-1 font-monospace" style="font-size:.88rem;"><?= htmlspecialchars($student['member_uid'] ?? $student_uid) ?></div>
                  <div class="text-muted" style="font-size:.74rem;">Registered: <?= !empty($student['created_at']) ? date('d M Y', strtotime($student['created_at'])) : '—' ?></div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
                  <div class="text-muted" style="font-size:.7rem;font-weight:600;text-transform:uppercase;">Date of Birth &amp; Gender</div>
                  <div class="fw-bold text-dark mt-1" style="font-size:.88rem;">
                    <?= !empty($student['dob']) ? date('d M Y', strtotime($student['dob'])) : '—' ?>
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem;"><?= htmlspecialchars($student['gender'] ?? '—') ?></span>
                  </div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
                  <div class="text-muted" style="font-size:.7rem;font-weight:600;text-transform:uppercase;">Account Status &amp; Last Login</div>
                  <div class="fw-bold text-dark mt-1" style="font-size:.88rem;">
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                  </div>
                  <div class="text-muted" style="font-size:.74rem;">
                    <?= !empty($student['last_login']) ? date('d M Y, h:i A', strtotime($student['last_login'])) : 'First login' ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Contact & Emergency Communication Form -->
        <div class="pro-card">
          <div class="pro-card-header">
            <h3 class="pro-card-title">
              <i class="fas fa-phone-alt text-success"></i>Contact &amp; Emergency Details
            </h3>
            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;">Editable</span>
          </div>
          <div class="pro-card-body">
            <form method="POST">
              <input type="hidden" name="action" value="update_contact">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.82rem;">Student Account Email</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control bg-light" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" readonly>
                  </div>
                  <div class="form-text" style="font-size:.7rem;">Email is your account identifier and cannot be changed directly.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.82rem;">Student Mobile Number</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light text-muted">+91</span>
                    <input type="tel" class="form-control" name="phone" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                  </div>
                  <div class="form-text" style="font-size:.7rem;">For SMS notifications &amp; login OTP.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-danger" style="font-size:.82rem;">
                    <i class="fas fa-star-of-life me-1" style="font-size:.5rem;vertical-align:middle;"></i>Parent / Guardian Mobile
                  </label>
                  <div class="input-group">
                    <span class="input-group-text bg-light text-muted">+91</span>
                    <input type="tel" class="form-control border-danger-subtle" name="parent_mobile" inputmode="numeric" maxlength="10" placeholder="10-digit parent mobile" value="<?= htmlspecialchars($student['parent_mobile'] ?? '') ?>" required>
                  </div>
                  <div class="form-text text-danger" style="font-size:.7rem;">Printed on Health ID card for medical emergencies.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.82rem;">Residential Address</label>
                  <input type="text" class="form-control" name="address" placeholder="City, State, Pincode" value="<?= htmlspecialchars($student['address'] ?? '') ?>">
                  <div class="form-text" style="font-size:.7rem;">Your residential address.</div>
                </div>
              </div>

              <div class="mt-4 pt-2 border-top d-flex justify-content-end">
                <button type="submit" class="btn btn-success fw-bold px-4 py-2" style="font-size:.84rem;">
                  <i class="fas fa-save me-1"></i>Save Contact Information
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- ABHA Ayushman Bharat Integration Card -->
        <div class="pro-card" style="border-left:4px solid #00875a;">
          <div class="pro-card-header" style="background:#f6fcf8;">
            <h3 class="pro-card-title text-success" style="color:#00875a !important;">
              <i class="fas fa-id-card"></i>Ayushman Bharat Health Account (ABHA)
            </h3>
            <?php if (!empty($student['abha_linked'])): ?>
              <span class="badge bg-success" style="font-size:.72rem;"><i class="fas fa-shield-alt me-1"></i>Linked &amp; Active</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark" style="font-size:.72rem;">Not Linked</span>
            <?php endif; ?>
          </div>
          <div class="pro-card-body">
            <?php if (!empty($student['abha_linked']) && !empty($student['abha_id'])): ?>
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 rounded-3" style="background:#e8f5e9;border:1px solid #c8e6c9;">
                <div>
                  <div class="text-muted text-uppercase" style="font-size:.66rem;font-weight:700;">14-Digit ABHA Health Number</div>
                  <div class="fw-bold font-monospace fs-5 text-dark"><?= htmlspecialchars($student['abha_id']) ?></div>
                  <?php if (!empty($student['abha_address'])): ?>
                    <div class="text-muted" style="font-size:.76rem;">Address: <?= htmlspecialchars($student['abha_address']) ?></div>
                  <?php endif; ?>
                </div>
                <a href="abha.php" class="btn btn-sm btn-outline-success fw-bold">
                  <i class="fas fa-id-card me-1"></i>View ABHA Card
                </a>
              </div>
            <?php else: ?>
              <p class="text-muted mb-3" style="font-size:.82rem;">
                Connect your Government of India Ayushman Bharat Health Account (ABHA) to digitally store and share health records nationwide across hospitals and labs.
              </p>
              <a href="abha.php" class="btn btn-success btn-sm fw-bold px-4 py-2">
                <i class="fas fa-link me-1"></i>Link Your ABHA Account Now
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Account Security & Password Card -->
        <?php if (!empty($student['password'])): ?>
          <div class="pro-card" style="border-left:4px solid #dc2626;">
            <div class="pro-card-header">
              <h3 class="pro-card-title">
                <i class="fas fa-key text-danger"></i>Account Security &amp; Password
              </h3>
            </div>
            <div class="pro-card-body">
              <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                  <label class="form-label fw-bold" style="font-size:.82rem;">Current Password</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="current_password" id="cur_pw" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('cur_pw', this)">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold" style="font-size:.82rem;">New Password</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light text-muted"><i class="fas fa-key"></i></span>
                      <input type="password" class="form-control" name="new_password" id="new_pw" minlength="6" required oninput="evaluatePasswordStrength(this.value)">
                      <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('new_pw', this)">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                    <div class="pw-strength-track">
                      <div class="pw-strength-bar" id="pwStrengthBar"></div>
                    </div>
                    <div class="form-text" id="pwStrengthText" style="font-size:.68rem;">Minimum 6 characters.</div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" style="font-size:.82rem;">Confirm New Password</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light text-muted"><i class="fas fa-check-double"></i></span>
                      <input type="password" class="form-control" name="confirm_password" id="cnf_pw" minlength="6" required>
                      <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('cnf_pw', this)">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                    <div class="form-text" style="font-size:.68rem;">Re-type the new password.</div>
                  </div>
                </div>

                <div class="mt-4 pt-2 border-top d-flex justify-content-end">
                  <button type="submit" class="btn btn-danger fw-bold px-4 py-2" style="font-size:.84rem;">
                    <i class="fas fa-shield-alt me-1"></i>Update Password
                  </button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </main>

  <!-- Photo Upload / Manage Modal -->
  <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="photoModalLabel"><i class="fas fa-camera text-primary me-2"></i>Update Profile Photo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center p-4">
          <div class="mb-3">
            <div id="modalPhotoPreview" style="width:110px;height:110px;border-radius:24px;margin:0 auto;overflow:hidden;background:#f1f5f9;border:3px solid #0C74C5;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#0C74C5;font-weight:bold;">
              <?php if ($has_photo): ?>
                <img src="<?= $photo_src ?>" style="width:100%;height:100%;object-fit:cover;" alt="Preview">
              <?php else: ?>
                <?= strtoupper(substr($student['name'] ?? $student_name, 0, 1)) ?>
              <?php endif; ?>
            </div>
          </div>

          <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
            <input type="hidden" name="action" value="update_photo">
            <div class="mb-3 text-start">
              <label class="form-label fw-bold" style="font-size:.82rem;">Select Photo (JPG, PNG, WebP up to 3MB)</label>
              <input type="file" class="form-control" name="profile_pic" id="photoFileInput" accept=".jpg,.jpeg,.png,.webp" required onchange="previewSelectedPhoto(this)">
            </div>
            <div class="d-flex gap-2 justify-content-center mt-3">
              <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="fas fa-upload me-1"></i>Upload Photo
              </button>
            </div>
          </form>

          <?php if ($has_photo): ?>
            <hr class="my-3">
            <form method="POST">
              <input type="hidden" name="action" value="remove_photo">
              <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to remove your profile picture?');">
                <i class="fas fa-trash-alt me-1"></i>Remove Current Photo
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Clipboard Toast -->
  <div class="copy-toast" id="copyToast">
    <i class="fas fa-check-circle text-success fs-5"></i>
    <span id="copyToastMsg">Health Card Link copied to clipboard!</span>
  </div>

  <!-- ════════════════════════════════════════════════════════
       HIDDEN OFF-SCREEN EXPORT WRAPPER FOR html2canvas
       Ensures 100% bug-free, upright, crystal clear 3x renders
       ════════════════════════════════════════════════════════ -->
  <div id="idCardExportWrapper" style="position:fixed;left:-9999px;top:0;z-index:-1000;pointer-events:none;">

    <!-- Clone Front Export -->
    <div id="idCardFrontExport" style="width:480px;height:303px;border-radius:20px;padding:18px 20px 14px;color:#1f2937;background:#ffffff;border:1px solid #e2e8f0;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;font-family:'Plus Jakarta Sans',sans-serif;">
      <!-- Header -->
      <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding-bottom:10px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <img src="<?= BASE_URL . get_header_logo() ?>" alt="Rejuvenate Digital Health" style="height:26px;width:auto;">
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <svg width="36" height="26" viewBox="0 0 42 32" fill="none">
            <rect width="42" height="32" rx="4" fill="#d4af37"/>
            <path d="M0 16h14m14 0h14M14 6v20m14-20v20M14 10h14M14 22h14" stroke="#8a6914" stroke-width="1.3"/>
          </svg>
          <i class="fas fa-wifi" style="transform:rotate(90deg);font-size:1rem;color:#475569;"></i>
        </div>
      </div>

      <!-- School Banner -->
      <div style="background:rgba(12,116,197,0.07);border-radius:6px;padding:3px 8px;margin:5px 0 8px;display:flex;justify-content:space-between;font-size:.65rem;font-weight:600;color:#334155;">
        <span><i class="fas fa-school text-accent me-1"></i><?= htmlspecialchars($student['school_name'] ?? $student_school) ?></span>
        <span><?= htmlspecialchars($student['school_board'] ?? 'CBSE') ?></span>
      </div>

      <!-- Mid -->
      <div style="display:flex;gap:14px;align-items:center;flex:1;">
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
          <div style="width:70px;height:80px;border-radius:12px;overflow:hidden;border:2px solid #0C74C5;background:#eaf4fd;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:bold;color:#0C74C5;">
            <?php if ($has_photo): ?>
              <img src="<?= $photo_src ?>" style="width:100%;height:100%;object-fit:cover;" alt="Photo">
            <?php else: ?>
              <?= strtoupper(substr($student['name'] ?? $student_name, 0, 1)) ?>
            <?php endif; ?>
          </div>
          <?php if (!empty($student['blood_group'])): ?>
            <div style="background:#dc2626;color:#fff;font-size:.6rem;font-weight:800;padding:2px 8px;border-radius:10px;">
              <?= htmlspecialchars($student['blood_group']) ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font-size:1.1rem;font-weight:800;margin-bottom:3px;color:#1f2937;"><?= htmlspecialchars($student['name'] ?? $student_name) ?></div>
          <div style="display:inline-block;background:#02c9b8;color:#06233d;font-size:.56rem;font-weight:800;padding:2px 6px;border-radius:4px;margin-bottom:6px;">STUDENT HEALTH PASS</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 6px;font-size:.68rem;color:#1f2937;">
            <div><span style="color:#64748b;">Class:</span> <strong><?= htmlspecialchars($student['class'] ? ($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) : '—') ?></strong></div>
            <div><span style="color:#64748b;">Roll No:</span> <strong><?= htmlspecialchars($student['roll_number'] ?? '—') ?></strong></div>
            <div><span style="color:#64748b;">DOB:</span> <strong><?= !empty($student['dob']) ? date('d M Y', strtotime($student['dob'])) : '—' ?></strong></div>
            <div><span style="color:#64748b;">Gender:</span> <strong><?= htmlspecialchars($student['gender'] ?? '—') ?></strong></div>
            <?php if (!empty($student['parent_mobile'])): ?>
              <div style="grid-column:span 2;"><span style="color:#64748b;">Parent:</span> <strong>+91 <?= htmlspecialchars($student['parent_mobile']) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #e2e8f0;padding-top:6px;font-size:.68rem;">
        <div style="font-family:'JetBrains Mono',monospace;background:rgba(12,116,197,0.07);color:#1f2937;padding:2px 6px;border-radius:4px;">
          UID: <?= htmlspecialchars($student['member_uid'] ?? $student_uid) ?>
        </div>
        <div style="font-size:.58rem;font-weight:700;color:#334155;">
          REJUVENATE DIGITAL HEALTH &middot; VERIFIED PASS
        </div>
      </div>
    </div>

    <!-- Clone Back Export -->
    <div id="idCardBackExport" style="width:480px;height:303px;border-radius:20px;padding:18px 20px 14px;color:#1f2937;background:#f8fafc;border:1px solid #e2e8f0;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;font-family:'Plus Jakarta Sans',sans-serif;">
      <!-- Header -->
      <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding-bottom:10px;">
        <div>
          <div style="font-size:.84rem;font-weight:800;letter-spacing:.6px;color:#1f2937;">EMERGENCY CLINICAL PROFILE</div>
          <div style="font-size:.56rem;font-weight:700;letter-spacing:1px;color:#01a89a;">REJUVENATE DIGITAL HEALTH NETWORK</div>
        </div>
        <div style="background:#dc2626;color:#fff;font-size:.58rem;font-weight:800;padding:2px 8px;border-radius:4px;">
          FIRST RESPONDER ACCESS
        </div>
      </div>

      <!-- Body -->
      <div style="display:flex;gap:16px;align-items:center;flex:1;margin:8px 0;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
          <div id="cardBackQrExport"></div>
          <div style="font-size:.52rem;font-weight:800;color:#06233d;margin-top:4px;">SCAN FOR PROFILE</div>
        </div>
        <div style="flex:1;font-size:.66rem;color:#1f2937;">
          <div style="color:#01a89a;font-weight:800;margin-bottom:6px;text-transform:uppercase;">Critical Medical Alerts</div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed #e2e8f0;padding:3px 0;">
            <span style="color:#64748b;">Blood Group:</span> <strong style="color:#dc2626;"><?= $blood_group ?: 'Not recorded' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed #e2e8f0;padding:3px 0;">
            <span style="color:#64748b;">Emergency Contact:</span> <strong><?= $emergency_name ? htmlspecialchars($emergency_name) : 'Not recorded' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed #e2e8f0;padding:3px 0;">
            <span style="color:#64748b;">Contact Phone:</span> <strong style="color:#01a89a;"><?= $emergency_phone ? ('+91 ' . htmlspecialchars($emergency_phone)) : 'Not recorded' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed #e2e8f0;padding:3px 0;">
            <span style="color:#64748b;">Allergies:</span> <strong><?= !empty($hp['known_allergies']) ? htmlspecialchars($hp['known_allergies']) : 'None reported' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:3px 0;">
            <span style="color:#64748b;">Conditions:</span> <strong><?= !empty($hp['chronic_conditions']) ? htmlspecialchars($hp['chronic_conditions']) : 'None recorded' ?></strong>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div style="border-top:1px solid #e2e8f0;padding-top:6px;font-size:.52rem;color:#64748b;text-align:center;">
        In emergency, scan QR code for authentic health history &middot; 24x7 Helpline: support@rejuvenatedigitalhealth.com
      </div>
    </div>

    <!-- Combined Full Badge Export (Front + Back side-by-side) -->
    <div id="idCardBadgeExport" style="width:1000px;padding:24px;background:#f1f5f9;display:flex;gap:24px;align-items:center;justify-content:center;box-sizing:border-box;">
      <!-- Front clone container injected via JS or rendered -->
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       PRINT SHEET: A4 / LETTER ID CARD PRINT CUT-OUT TEMPLATE
       ════════════════════════════════════════════════════════ -->
  <div id="idCardPrintSheet">
    <div style="max-width:800px;margin:0 auto;text-align:center;font-family:'Plus Jakarta Sans',sans-serif;">
      <div style="margin-bottom:20px;border-bottom:2px solid #0C74C5;padding-bottom:12px;">
        <h2 style="color:#0C74C5;margin:0 0 4px;font-size:1.5rem;">REJUVENATE DIGITAL HEALTH</h2>
        <div style="font-size:.85rem;color:#475569;">Official Student Health Identity Pass &middot; Print &amp; Lamination Template</div>
      </div>

      <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:30px;">
        <!-- Front Print Preview -->
        <div style="border:1px dashed #94a3b8;padding:6px;border-radius:16px;">
          <div style="font-size:.7rem;font-weight:bold;color:#64748b;margin-bottom:4px;">✂ FRONT SIDE (Cut along dashed line)</div>
          <div id="printFrontTarget"></div>
        </div>

        <!-- Back Print Preview -->
        <div style="border:1px dashed #94a3b8;padding:6px;border-radius:16px;">
          <div style="font-size:.7rem;font-weight:bold;color:#64748b;margin-bottom:4px;">✂ BACK SIDE (Cut along dashed line)</div>
          <div id="printBackTarget"></div>
        </div>
      </div>

      <div style="border:1px solid #cbd5e1;background:#f8fafc;border-radius:12px;padding:16px;text-align:left;font-size:.82rem;color:#334155;">
        <h4 style="font-size:.9rem;color:#0C74C5;margin-top:0;"><i class="fas fa-info-circle me-1"></i>Instructions for School / Parents:</h4>
        <ol style="margin:0;padding-left:20px;line-height:1.5;">
          <li>Print this page on high quality photo paper or cardstock (100% scale / Actual Size).</li>
          <li>Cut both front and back cards along the dashed guidelines.</li>
          <li>Place them back-to-back and laminate with a standard PVC pouch (85.6mm &times; 54mm).</li>
          <li>Keep this card in the student's backpack or wallet. In any medical emergency, first responders can scan the QR code for authentic medical history.</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

  <script>
    const healthCardUrl = <?= json_encode($health_card_url) ?>;
    const studentName   = <?= json_encode($student['name'] ?? $student_name) ?>;
    const studentUid    = <?= json_encode($student['member_uid'] ?? $student_uid) ?>;

    // Generate QR Code on Card Back
    new QRCode(document.getElementById('cardBackQr'), {
      text: healthCardUrl,
      width: 96,
      height: 96,
      colorDark: '#06233d',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });

    // Generate QR Code on Hidden Export Clone
    new QRCode(document.getElementById('cardBackQrExport'), {
      text: healthCardUrl,
      width: 96,
      height: 96,
      colorDark: '#06233d',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });

    // Flip Card 3D
    function flipCard() {
      const card = document.getElementById('healthIdCard');
      const isFlipped = card.classList.toggle('flipped');
      document.getElementById('btnSideFront').classList.toggle('active', !isFlipped);
      document.getElementById('btnSideBack').classList.toggle('active', isFlipped);
    }

    function showCardSide(side) {
      const card = document.getElementById('healthIdCard');
      if (side === 'front') {
        card.classList.remove('flipped');
        document.getElementById('btnSideFront').classList.add('active');
        document.getElementById('btnSideBack').classList.remove('active');
      } else {
        card.classList.add('flipped');
        document.getElementById('btnSideFront').classList.remove('active');
        document.getElementById('btnSideBack').classList.add('active');
      }
    }

    // Copy Health Link
    document.getElementById('copyShareLinkBtn').addEventListener('click', function() {
      navigator.clipboard.writeText(healthCardUrl).then(() => {
        showToast('Digital Health Pass link copied to clipboard!');
      });
    });

    function showToast(msg) {
      const toast = document.getElementById('copyToast');
      const msgElem = document.getElementById('copyToastMsg');
      msgElem.textContent = msg;
      toast.classList.add('show');
      setTimeout(() => { toast.classList.remove('show'); }, 2400);
    }

    // Download Single Face (Front or Back) at 3x scale
    function downloadCardFace(side) {
      const elemId = (side === 'front') ? 'idCardFrontExport' : 'idCardBackExport';
      const filename = `rejuvenate-health-id-${studentUid}-${side}.png`;
      const target = document.getElementById(elemId);

      showToast(`Generating high-res ${side} card PNG...`);

      html2canvas(target, {
        scale: 3,
        useCORS: true,
        allowTaint: true,
        backgroundColor: null,
        logging: false
      }).then(canvas => {
        const link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL('image/png');
        link.click();
      });
    }

    // Download Combined Full Badge (Front + Back)
    function downloadCombinedBadge() {
      const badgeContainer = document.getElementById('idCardBadgeExport');
      const frontClone = document.getElementById('idCardFrontExport').cloneNode(true);
      const backClone = document.getElementById('idCardBackExport').cloneNode(true);

      badgeContainer.innerHTML = '';
      badgeContainer.appendChild(frontClone);
      badgeContainer.appendChild(backClone);

      showToast('Rendering full health badge PNG...');

      html2canvas(badgeContainer, {
        scale: 3,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#f1f5f9',
        logging: false
      }).then(canvas => {
        const link = document.createElement('a');
        link.download = `rejuvenate-health-badge-${studentUid}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
      });
    }

    // Print Ready Template
    function printCard() {
      const frontTarget = document.getElementById('printFrontTarget');
      const backTarget  = document.getElementById('printBackTarget');

      frontTarget.innerHTML = '';
      backTarget.innerHTML = '';

      const fClone = document.getElementById('idCardFrontExport').cloneNode(true);
      const bClone = document.getElementById('idCardBackExport').cloneNode(true);

      frontTarget.appendChild(fClone);
      backTarget.appendChild(bClone);

      window.print();
    }

    // Toggle Password Visibility
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    }

    // Live Password Strength Meter
    function evaluatePasswordStrength(val) {
      const bar = document.getElementById('pwStrengthBar');
      const txt = document.getElementById('pwStrengthText');
      if (!bar) return;

      let score = 0;
      if (val.length >= 6) score += 25;
      if (val.length >= 8) score += 25;
      if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
      if (/\d/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

      bar.style.width = score + '%';
      if (score <= 25) {
        bar.style.backgroundColor = '#dc2626';
        txt.textContent = 'Weak password';
        txt.style.color = '#dc2626';
      } else if (score <= 50) {
        bar.style.backgroundColor = '#ea580c';
        txt.textContent = 'Moderate password';
        txt.style.color = '#ea580c';
      } else if (score <= 75) {
        bar.style.backgroundColor = '#d97706';
        txt.textContent = 'Good password';
        txt.style.color = '#d97706';
      } else {
        bar.style.backgroundColor = '#16a34a';
        txt.textContent = 'Strong password';
        txt.style.color = '#16a34a';
      }
    }

    // Photo Preview Modal
    function previewSelectedPhoto(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const previewBox = document.getElementById('modalPhotoPreview');
          previewBox.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;" alt="Preview">`;
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  </script>
</body>
</html>
