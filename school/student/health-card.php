<?php
include_once "../../config/connect.php";

/* Public page — no login required. Anyone with the QR/link (e.g. in an
   emergency) can view this card, so only emergency-relevant info is shown. */

$token = trim($_GET['t'] ?? '');
$member = null;
$hp = null;

if ($token !== '') {
    $stmt = $conn->prepare("SELECT sm.*, s.school_name, s.city, s.state FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.share_token=? AND sm.status='Active' LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    if ($member) {
        $hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
        $hp_stmt->bind_param('i', $member['id']);
        $hp_stmt->execute();
        $hp = $hp_stmt->get_result()->fetch_assoc();
    }
}

$bmi = null; $bmi_lbl = ''; $bmi_col = '#6b7280';
if ($hp && !empty($hp['height_cm']) && !empty($hp['weight_kg'])) {
    $bmi = round($hp['weight_kg'] / (($hp['height_cm']/100)**2), 1);
    if      ($bmi < 18.5) { $bmi_lbl = 'Underweight'; $bmi_col = '#d97706'; }
    elseif  ($bmi < 25)   { $bmi_lbl = 'Normal';       $bmi_col = '#16a34a'; }
    elseif  ($bmi < 30)   { $bmi_lbl = 'Overweight';   $bmi_col = '#ea580c'; }
    else                  { $bmi_lbl = 'Obese';         $bmi_col = '#dc2626'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $member ? htmlspecialchars($member['name']) . ' | Health Card' : 'Health Card' ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root { --primary: #0C74C5; --accent: #02c9b8; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }
    .s-body { max-width: 560px; margin: 0 auto; padding: 22px 14px 40px; }

    .banner {
      background: var(--primary); color: #fff; border-radius: 14px;
      padding: 14px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
      font-size: .8rem; font-weight: 600;
    }
    .banner i { font-size: 1.1rem; }

    .card-hero {
      background: linear-gradient(135deg, var(--primary), #085a99);
      border-radius: 18px; color: #fff; padding: 24px 22px; margin-bottom: 16px;
      display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden;
    }
    .card-hero::after { content:''; position:absolute; right:-40px; top:-40px; width:170px; height:170px; background:rgba(255,255,255,.07); border-radius:50%; }
    .avatar-lg { width:66px; height:66px; border-radius:16px; object-fit:cover; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:700; flex-shrink:0; position:relative; z-index:1; }

    .s-card { background:#fff; border-radius:12px; padding:16px 18px; margin-bottom:14px; box-shadow:0 1px 6px rgba(0,0,0,.07); }
    .s-card-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#6b7280; display:flex; align-items:center; gap:7px; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #f3f4f6; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .info-box { background: #f9fafb; border-radius: 8px; padding: 10px 12px; }
    .info-box .lbl { font-size: .67rem; color: #9ca3af; margin-bottom: 2px; }
    .info-box .val { font-size: .86rem; font-weight: 600; color: #1f2937; }
    .blood-chip { display:inline-flex; align-items:center; padding:6px 16px; border-radius:20px; font-size:.85rem; font-weight:700; background:#dc2626; color:#fff; }
    .text-block { font-size: .85rem; color: #374151; background: #f9fafb; border-radius: 8px; padding: 10px 12px; white-space: pre-wrap; }
  </style>
</head>
<body>
<div class="s-body">

  <?php if (!$member): ?>

    <div class="s-card text-center" style="padding:50px 20px;margin-top:40px;">
      <i class="fas fa-qrcode fa-2x mb-3 d-block" style="color:#0C74C5;opacity:.3;"></i>
      <div class="fw-semibold" style="color:#374151;margin-bottom:6px;">Invalid or Expired Card</div>
      <p style="font-size:.82rem;color:#9ca3af;margin-bottom:0;">This health card link is not valid. Please ask the student's school for an updated card.</p>
    </div>

  <?php else: ?>

    <div class="banner">
      <i class="fas fa-triangle-exclamation"></i>
      <div>Emergency Health Information &mdash; for use by medical staff, teachers or first responders only.</div>
    </div>

    <!-- Hero -->
    <div class="card-hero">
      <?php if (!empty($member['profile_pic']) && file_exists('../../' . $member['profile_pic'])): ?>
        <img class="avatar-lg" src="../../<?= htmlspecialchars($member['profile_pic']) ?>" alt="photo">
      <?php else: ?>
        <div class="avatar-lg"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
      <?php endif; ?>
      <div style="position:relative;z-index:1;flex:1;min-width:0;">
        <div style="font-size:1.15rem;font-weight:700;"><?= htmlspecialchars($member['name']) ?></div>
        <div style="font-size:.8rem;opacity:.85;margin-top:2px;">
          <?php if ($member['class']): ?>Class <?= htmlspecialchars($member['class'] . ($member['section'] ? ' '.$member['section'] : '')) ?><?php endif; ?>
          <?php if ($member['roll_number']): ?> &middot; Roll: <?= htmlspecialchars($member['roll_number']) ?><?php endif; ?>
        </div>
        <div style="font-size:.75rem;opacity:.75;margin-top:2px;"><?= htmlspecialchars($member['school_name']) ?></div>
        <div style="font-size:.68rem;opacity:.6;margin-top:4px;font-family:monospace;"><?= htmlspecialchars($member['member_uid']) ?></div>
      </div>
      <?php if (!empty($member['blood_group'])): ?>
        <div style="position:relative;z-index:1;text-align:center;flex-shrink:0;">
          <div style="background:rgba(255,255,255,.2);border-radius:12px;padding:8px 14px;">
            <div style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($member['blood_group']) ?></div>
            <div style="font-size:.6rem;opacity:.8;">BLOOD GRP</div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$hp): ?>
      <div class="s-card text-center" style="padding:30px 20px;">
        <i class="fas fa-heartbeat fa-2x mb-3 d-block" style="color:#0C74C5;opacity:.25;"></i>
        <p style="font-size:.82rem;color:#9ca3af;margin-bottom:0;">No detailed health profile has been recorded for this student yet.</p>
      </div>
    <?php else: ?>

      <!-- Medical History -->
      <div class="s-card" style="border-left:4px solid #dc2626;">
        <div class="s-card-title"><i class="fas fa-notes-medical" style="color:#dc2626;"></i>Known Allergies &amp; Conditions</div>
        <div class="mb-3">
          <div style="font-size:.68rem;color:#9ca3af;margin-bottom:3px;">Known Allergies</div>
          <div class="text-block"><?= $hp['known_allergies'] ? nl2br(htmlspecialchars($hp['known_allergies'])) : 'None recorded' ?></div>
        </div>
        <div class="mb-3">
          <div style="font-size:.68rem;color:#9ca3af;margin-bottom:3px;">Chronic Conditions</div>
          <div class="text-block"><?= $hp['chronic_conditions'] ? nl2br(htmlspecialchars($hp['chronic_conditions'])) : 'None recorded' ?></div>
        </div>
        <div>
          <div style="font-size:.68rem;color:#9ca3af;margin-bottom:3px;">Current Medications</div>
          <div class="text-block"><?= $hp['current_medications'] ? nl2br(htmlspecialchars($hp['current_medications'])) : 'None recorded' ?></div>
        </div>
      </div>

      <?php if ($bmi): ?>
      <div class="s-card">
        <div class="s-card-title"><i class="fas fa-weight text-primary"></i>Physical Measurements</div>
        <div class="info-grid">
          <div class="info-box"><div class="lbl">Height</div><div class="val"><?= htmlspecialchars($hp['height_cm']) ?> cm</div></div>
          <div class="info-box"><div class="lbl">Weight</div><div class="val"><?= htmlspecialchars($hp['weight_kg']) ?> kg</div></div>
          <div class="info-box"><div class="lbl">BMI</div><div class="val" style="color:<?= $bmi_col ?>;"><?= $bmi ?> &middot; <?= $bmi_lbl ?></div></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($hp['vaccination_status'])): ?>
      <div class="s-card">
        <div class="s-card-title"><i class="fas fa-syringe" style="color:#16a34a;"></i>Vaccination Status</div>
        <div class="text-block"><?= nl2br(htmlspecialchars($hp['vaccination_status'])) ?></div>
      </div>
      <?php endif; ?>

      <!-- Emergency Contact -->
      <div class="s-card" style="border-left:4px solid #d97706;">
        <div class="s-card-title"><i class="fas fa-phone-alt" style="color:#d97706;"></i>Emergency Contact</div>
        <div class="info-grid">
          <div class="info-box"><div class="lbl">Guardian / Parent</div><div class="val"><?= $hp['emergency_contact'] ? htmlspecialchars($hp['emergency_contact']) : 'Not recorded' ?></div></div>
          <div class="info-box"><div class="lbl">Phone</div><div class="val"><?= $hp['emergency_phone'] ? htmlspecialchars($hp['emergency_phone']) : 'Not recorded' ?></div></div>
        </div>
        <?php if ($hp['emergency_phone']): ?>
          <a href="tel:<?= htmlspecialchars($hp['emergency_phone']) ?>" class="btn btn-warning btn-sm fw-semibold mt-3 w-100">
            <i class="fas fa-phone me-1"></i>Call Emergency Contact
          </a>
        <?php endif; ?>
      </div>

    <?php endif; ?>

    <?php if (!empty($member['abha_linked']) && !empty($member['abha_id'])): ?>
      <div class="s-card" style="border-left:4px solid #00875a;">
        <div class="s-card-title"><i class="fas fa-id-card" style="color:#00875a;"></i>ABHA (Ayushman Bharat Health Account)</div>
        <div style="font-family:monospace;font-size:.9rem;font-weight:700;color:#1f2937;"><?= htmlspecialchars($member['abha_id']) ?></div>
        <?php if ($member['abha_address']): ?><div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($member['abha_address']) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>

    <div style="text-align:center;font-size:.7rem;color:#9ca3af;padding:10px 0;">
      <i class="fas fa-lock me-1"></i>Shared via a private digital health ID card &middot; Rejuvenate Digital Health
    </div>

  <?php endif; ?>

</div>
</body>
</html>
