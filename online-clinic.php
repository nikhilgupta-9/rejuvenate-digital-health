<?php
include_once "config/connect.php";
include_once "util/function.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// Pre-fill for a logged-in patient
$logged_in_patient = null;
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name, last_name, email, mobile, abha_id FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $logged_in_patient = $stmt->get_result()->fetch_assoc();
}

$departments = get_sub_category();
$contact = contact_us();
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/util/function.php'; } ?>
<head>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="Telemedicine at Rejuvenate Digital Health — consult verified, HPR-registered doctors over secure video from home, with ABHA-linked digital health records.">
  <title>Online Clinic — Telemedicine | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    :root {
      --bk-primary: #0C74C5;
      --bk-primary-dk: #095e9f;
      --bk-accent: #02c9b8;
      --bk-ink: #1f2937;
      --bk-muted: #6b7280;
      --bk-border: #e5e7eb;
      --bk-bg: #f6f9fc;
    }

    /* ── Telemedicine info sections ── */
    .tm-info-section {
      padding: 60px 0;
    }

    .tm-lede {
      max-width: 760px;
      margin: 0 auto 44px;
      text-align: center;
    }

    .tm-lede .subtitle {
      color: var(--bk-primary);
      font-weight: 700;
      font-size: .78rem;
      letter-spacing: .1em;
      text-transform: uppercase;
    }

    .tm-lede h1 {
      font-weight: 700;
      color: var(--bk-ink);
      margin: 8px 0 14px;
      font-size: 2rem;
    }

    .tm-lede p {
      color: var(--bk-muted);
      font-size: .98rem;
      line-height: 1.7;
    }

    .tm-benefit-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 8px;
    }

    .tm-benefit-card {
      background: #fff;
      border: 1px solid var(--bk-border);
      border-radius: 14px;
      padding: 22px 20px;
      text-align: left;
    }

    .tm-benefit-card .ic {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      background: #eaf4fd;
      color: var(--bk-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-bottom: 14px;
    }

    .tm-benefit-card h5 {
      font-weight: 700;
      color: var(--bk-ink);
      font-size: 1rem;
      margin-bottom: 6px;
    }

    .tm-benefit-card p {
      color: var(--bk-muted);
      font-size: .86rem;
      margin: 0;
      line-height: 1.6;
    }

    .tm-steps-section {
      background: var(--bk-bg);
      padding: 56px 0;
    }

    .tm-step-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 22px;
      counter-reset: tm-step;
    }

    .tm-step-card {
      position: relative;
      background: #fff;
      border-radius: 14px;
      padding: 26px 20px 20px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(20, 40, 70, .06);
    }

    .tm-step-card .num {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--bk-primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      margin: 0 auto 14px;
    }

    .tm-step-card h5 {
      font-weight: 700;
      color: var(--bk-ink);
      font-size: .95rem;
      margin-bottom: 6px;
    }

    .tm-step-card p {
      color: var(--bk-muted);
      font-size: .84rem;
      margin: 0;
      line-height: 1.6;
    }

    .tm-cta-strip {
      max-width: 900px;
      margin: 44px auto 0;
      text-align: center;
    }

    .tm-cta-strip .bk-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    /* ── Booking wizard (same system as the main booking page, locked to
         Online Consultation since this page is specifically telemedicine) ── */
    .bk-shell {
      background: var(--bk-bg);
      padding: 10px 0 70px;
    }

    .bk-stepper {
      display: flex;
      justify-content: center;
      gap: 6px;
      max-width: 760px;
      margin: 0 auto 34px;
      padding: 0 12px;
    }

    .bk-step {
      flex: 1;
      text-align: center;
      position: relative;
    }

    .bk-step .dot {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: #fff;
      border: 2px solid var(--bk-border);
      color: var(--bk-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .85rem;
      margin: 0 auto 6px;
      transition: .2s;
    }

    .bk-step .lbl {
      font-size: .72rem;
      color: var(--bk-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    .bk-step::after {
      content: '';
      position: absolute;
      top: 17px;
      left: 50%;
      width: 100%;
      height: 2px;
      background: var(--bk-border);
      z-index: -1;
    }

    .bk-step:last-child::after {
      display: none;
    }

    .bk-step.active .dot,
    .bk-step.done .dot {
      background: var(--bk-primary);
      border-color: var(--bk-primary);
      color: #fff;
    }

    .bk-step.active .lbl,
    .bk-step.done .lbl {
      color: var(--bk-primary);
    }

    .bk-step.done::after {
      background: var(--bk-primary);
    }

    .bk-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 28px rgba(20, 40, 70, .08);
      padding: 30px;
      max-width: 900px;
      margin: 0 auto;
    }

    .bk-pane {
      display: none;
    }

    .bk-pane.active {
      display: block;
      animation: bkFade .25s ease;
    }

    @keyframes bkFade {
      from {
        opacity: 0;
        transform: translateY(6px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .bk-pane h4 {
      font-weight: 700;
      color: var(--bk-ink);
      margin-bottom: 4px;
    }

    .bk-pane .sub {
      color: var(--bk-muted);
      font-size: .88rem;
      margin-bottom: 22px;
    }

    .bk-dept-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 14px;
    }

    .bk-dept-card {
      border: 2px solid var(--bk-border);
      border-radius: 12px;
      padding: 16px 12px;
      text-align: center;
      cursor: pointer;
      transition: .15s;
      background: #fff;
    }

    .bk-dept-card:hover {
      border-color: var(--bk-primary);
      transform: translateY(-2px);
    }

    .bk-dept-card.selected {
      border-color: var(--bk-primary);
      background: #eaf4fd;
    }

    .bk-dept-card .ic {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      background: #eef6fd;
      color: var(--bk-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-size: 1.15rem;
      overflow: hidden;
    }

    .bk-dept-card .ic img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .bk-dept-card .name {
      font-size: .85rem;
      font-weight: 600;
      color: var(--bk-ink);
    }

    .bk-doctor-card {
      border: 2px solid var(--bk-border);
      border-radius: 14px;
      padding: 18px;
      cursor: pointer;
      transition: .15s;
      background: #fff;
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }

    .bk-doctor-card:hover {
      border-color: var(--bk-primary);
    }

    .bk-doctor-card.selected {
      border-color: var(--bk-primary);
      background: #eaf4fd;
    }

    .bk-doctor-card .avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--bk-primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.3rem;
      flex-shrink: 0;
      overflow: hidden;
    }

    .bk-doctor-card .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .bk-doctor-card .name {
      font-weight: 700;
      color: var(--bk-ink);
      font-size: .95rem;
    }

    .bk-doctor-card .hpr-badge {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      background: #e6f7ee;
      color: #16a34a;
      font-size: .65rem;
      font-weight: 700;
      border-radius: 20px;
      padding: 2px 8px;
      margin-left: 6px;
    }

    .bk-doctor-card .meta {
      font-size: .78rem;
      color: var(--bk-muted);
      margin-top: 2px;
    }

    .bk-doctor-card .fee {
      font-weight: 700;
      color: var(--bk-primary);
      font-size: .88rem;
      margin-top: 6px;
    }

    .bk-slot-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 10px;
    }

    .bk-slot {
      border: 1.5px solid var(--bk-border);
      border-radius: 8px;
      padding: 9px 6px;
      text-align: center;
      font-size: .82rem;
      font-weight: 600;
      cursor: pointer;
      transition: .15s;
      color: var(--bk-ink);
      background: #fff;
    }

    .bk-slot:hover {
      border-color: var(--bk-primary);
    }

    .bk-slot.selected {
      background: var(--bk-primary);
      border-color: var(--bk-primary);
      color: #fff;
    }

    .bk-slot.booked {
      background: #f9fafb;
      color: #c1c7d0;
      cursor: not-allowed;
      text-decoration: line-through;
    }

    .bk-nav {
      display: flex;
      justify-content: space-between;
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid #f1f3f6;
    }

    .bk-btn {
      padding: 11px 26px;
      border-radius: 8px;
      font-weight: 600;
      font-size: .9rem;
      border: none;
      cursor: pointer;
      transition: .15s;
    }

    .bk-btn-primary {
      background: var(--bk-primary);
      color: #fff;
    }

    .bk-btn-primary:hover {
      background: var(--bk-primary-dk);
    }

    .bk-btn-primary:disabled {
      background: #b9d4ea;
      cursor: not-allowed;
    }

    .bk-btn-outline {
      background: #fff;
      border: 1.5px solid var(--bk-border);
      color: var(--bk-muted);
    }

    .bk-btn-outline:hover {
      border-color: var(--bk-primary);
      color: var(--bk-primary);
    }

    .bk-summary {
      display: none;
      background: #eaf4fd;
      border-radius: 10px;
      padding: 10px 16px;
      font-size: .82rem;
      color: var(--bk-ink);
      margin-bottom: 22px;
      flex-wrap: wrap;
      gap: 4px 14px;
    }

    .bk-summary.show {
      display: flex;
    }

    .bk-summary b {
      color: var(--bk-primary);
    }

    .bk-field label {
      font-size: .82rem;
      font-weight: 600;
      color: var(--bk-ink);
      margin-bottom: 5px;
      display: block;
    }

    .bk-field .form-control,
    .bk-field .form-select {
      border-radius: 8px;
      border: 1.5px solid var(--bk-border);
      padding: 10px 14px;
      font-size: .9rem;
    }

    .bk-field .form-control:focus,
    .bk-field .form-select:focus {
      border-color: var(--bk-primary);
      box-shadow: 0 0 0 3px rgba(12, 116, 197, .12);
    }

    .bk-visit-toggle {
      display: flex;
      gap: 10px;
    }

    .bk-visit-toggle label {
      flex: 1;
      border: 1.5px solid var(--bk-border);
      border-radius: 8px;
      padding: 10px;
      text-align: center;
      font-size: .85rem;
      font-weight: 600;
      cursor: pointer;
      color: var(--bk-muted);
    }

    .bk-visit-toggle input {
      display: none;
    }

    .bk-visit-toggle input:checked+label {
      border-color: var(--bk-primary);
      background: #eaf4fd;
      color: var(--bk-primary);
    }

    .bk-consent {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 10px;
      padding: 14px 16px;
      font-size: .8rem;
      color: #92400e;
      margin: 20px 0;
    }

    .bk-success {
      display: none;
      text-align: center;
      padding: 20px 10px 10px;
    }

    .bk-success .tick {
      width: 74px;
      height: 74px;
      border-radius: 50%;
      background: #e6f7ee;
      color: #16a34a;
      font-size: 2.1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 18px;
    }

    .bk-ref {
      display: inline-block;
      background: #f3f6fb;
      border-radius: 8px;
      padding: 6px 16px;
      font-weight: 700;
      color: var(--bk-primary);
      letter-spacing: .5px;
      margin: 10px 0 18px;
    }

    #bkFormError {
      display: none;
    }

    .bk-empty {
      text-align: center;
      padding: 40px 10px;
      color: var(--bk-muted);
    }

    .bk-sched-info {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #eaf4fd;
      border: 1px solid #cfe6fb;
      border-radius: 10px;
      padding: 9px 13px;
      font-size: .8rem;
      color: var(--bk-ink);
      margin-bottom: 14px;
    }
    .bk-sched-info i { color: var(--bk-primary); }

    .bk-date-strip {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding: 4px 2px 10px;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
    }
    .bk-date-strip::-webkit-scrollbar { height: 5px; }
    .bk-date-strip::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

    .bk-date-chip {
      flex: 0 0 auto;
      width: 62px;
      border: 1.5px solid var(--bk-border);
      border-radius: 12px;
      padding: 8px 4px;
      text-align: center;
      cursor: pointer;
      background: #fff;
      transition: .15s;
      user-select: none;
    }
    .bk-date-chip .d-dow { font-size: .66rem; font-weight: 700; text-transform: uppercase; color: var(--bk-muted); letter-spacing: .3px; }
    .bk-date-chip .d-day { font-size: 1.15rem; font-weight: 700; color: var(--bk-ink); line-height: 1.15; }
    .bk-date-chip .d-mon { font-size: .64rem; color: var(--bk-muted); text-transform: uppercase; }
    .bk-date-chip:hover { border-color: var(--bk-primary); }
    .bk-date-chip.selected { background: var(--bk-primary); border-color: var(--bk-primary); }
    .bk-date-chip.selected .d-dow,
    .bk-date-chip.selected .d-day,
    .bk-date-chip.selected .d-mon { color: #fff; }
    .bk-date-chip.disabled { opacity: .38; cursor: not-allowed; background: #f8fafc; }
    .bk-date-chip.disabled:hover { border-color: var(--bk-border); }

    .bk-more-date { margin-top: 12px; }
    .bk-more-date summary { font-size: .82rem; color: var(--bk-primary); cursor: pointer; font-weight: 600; }
    .bk-more-date[open] summary { margin-bottom: 8px; }

    .bk-consent details > summary { cursor: pointer; font-weight: 700; list-style: none; }
    .bk-consent details > summary::-webkit-details-marker { display: none; }
    .bk-consent details[open] { margin-top: 8px; }

    .tm-mode-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #eaf4fd;
      border: 1px solid #cfe6fb;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: .85rem;
      font-weight: 600;
      color: var(--bk-ink);
      margin-bottom: 3px;
    }
    .tm-mode-badge i { color: var(--bk-primary); }

    /* ══════════════ Telemedicine Hero ══════════════ */
    .tm-hero {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #0C74C5 0%, #095e9f 55%, #073f6c 100%);
      padding: 22px 0 60px;
    }

    .tm-hero-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(2px);
      opacity: .18;
      background: #fff;
      pointer-events: none;
      animation: tmBlobFloat 9s ease-in-out infinite;
    }
    .tm-hero-blob-1 { width: 260px; height: 260px; top: -80px; right: -60px; }
    .tm-hero-blob-2 { width: 180px; height: 180px; bottom: -60px; left: 6%; animation-delay: 2.4s; background: var(--bk-accent); opacity: .16; }

    @keyframes tmBlobFloat {
      0%, 100% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-18px) scale(1.06); }
    }

    .tm-hero-crumbs {
      list-style: none;
      display: flex;
      gap: 8px;
      padding: 0;
      margin: 0 0 22px;
      font-size: .8rem;
      color: rgba(255,255,255,.75);
    }
    .tm-hero-crumbs a { color: rgba(255,255,255,.9); text-decoration: none; }
    .tm-hero-crumbs a:hover { text-decoration: underline; }

    .tm-hero-content h1 {
      color: #fff;
      font-weight: 700;
      font-size: 2.5rem;
      line-height: 1.25;
      margin: 16px 0 16px;
    }
    .tm-hero-content h1 span { color: var(--bk-accent); }

    .tm-hero-content p {
      color: rgba(255,255,255,.85);
      font-size: 1rem;
      line-height: 1.7;
      max-width: 520px;
      margin-bottom: 26px;
    }

    .tm-hero-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.25);
      color: #fff;
      font-size: .78rem;
      font-weight: 600;
      padding: 7px 14px;
      border-radius: 30px;
      backdrop-filter: blur(4px);
    }

    .tm-pulse-dot {
      color: #4ade80 !important;
      font-size: .5rem !important;
      animation: tmPulse 1.6s ease-in-out infinite;
    }
    @keyframes tmPulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: .4; transform: scale(1.6); }
    }

    .tm-hero-actions {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 18px;
      margin-bottom: 22px;
    }

    .tm-hero-actions .bk-btn-primary {
      background: #fff;
      color: var(--bk-primary-dk);
      box-shadow: 0 10px 26px rgba(0,0,0,.18);
    }
    .tm-hero-actions .bk-btn-primary:hover {
      background: #eaf4fd;
      transform: translateY(-2px);
    }

    .tm-hero-call {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #fff;
      font-weight: 600;
      font-size: .9rem;
      text-decoration: none;
      border-bottom: 1px solid rgba(255,255,255,.4);
      padding-bottom: 2px;
      transition: .15s;
    }
    .tm-hero-call:hover { color: var(--bk-accent); border-color: var(--bk-accent); }

    .tm-hero-trust {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 20px;
    }
    .tm-hero-trust span {
      color: rgba(255,255,255,.85);
      font-size: .82rem;
      font-weight: 600;
    }
    .tm-hero-trust i { color: var(--bk-accent); margin-right: 4px; }

    .tm-hero-visual {
      position: relative;
      text-align: center;
    }
    .tm-hero-visual img {
      max-height: 420px;
      filter: drop-shadow(0 20px 34px rgba(0,0,0,.28));
      animation: tmVisualFloat 5s ease-in-out infinite;
    }
    @keyframes tmVisualFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-12px); }
    }

    .tm-float-chip {
      position: absolute;
      background: #fff;
      color: var(--bk-ink);
      font-size: .78rem;
      font-weight: 700;
      padding: 9px 16px;
      border-radius: 30px;
      box-shadow: 0 10px 24px rgba(20,40,70,.18);
      display: flex;
      align-items: center;
      gap: 7px;
      animation: tmChipFloat 4s ease-in-out infinite;
    }
    .tm-float-chip i { color: var(--bk-primary); }
    .tm-float-chip-1 { top: 8%; left: 0%; animation-delay: .2s; }
    .tm-float-chip-2 { top: 46%; right: 0%; animation-delay: 1.1s; }
    .tm-float-chip-3 { bottom: 10%; left: 12%; animation-delay: 2s; }
    @keyframes tmChipFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    /* ══════════════ Stats strip ══════════════ */
    .tm-stats-strip {
      background: #fff;
      padding: 0;
      margin-top: -34px;
      position: relative;
      z-index: 3;
    }
    .tm-stats-grid {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 34px rgba(20,40,70,.12);
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      max-width: 960px;
      margin: 0 auto;
      overflow: hidden;
    }
    .tm-stat-item {
      text-align: center;
      padding: 26px 12px;
      border-right: 1px solid var(--bk-border);
    }
    .tm-stat-item:last-child { border-right: none; }
    .tm-stat-item h3 {
      color: var(--bk-primary);
      font-weight: 700;
      font-size: 1.7rem;
      margin: 0 0 4px;
    }
    .tm-stat-item p {
      color: var(--bk-muted);
      font-size: .8rem;
      font-weight: 600;
      margin: 0;
      text-transform: uppercase;
      letter-spacing: .03em;
    }

    /* ══════════════ Benefit / step card interactivity ══════════════ */
    .tm-benefit-card {
      transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    }
    .tm-benefit-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 14px 30px rgba(20,40,70,.1);
      border-color: transparent;
    }
    .tm-benefit-card:hover .ic {
      background: var(--bk-primary);
      color: #fff;
      transform: scale(1.08) rotate(-4deg);
    }
    .tm-benefit-card .ic { transition: .25s; }

    .tm-step-card {
      transition: transform .25s ease, box-shadow .25s ease;
    }
    .tm-step-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 32px rgba(20,40,70,.12);
    }
    .tm-step-card:hover .num {
      background: var(--bk-accent);
    }
    .tm-step-card .num { transition: background .25s; }

    /* ══════════════ FAQ ══════════════ */
    .tm-faq-section { padding: 56px 0; }
    .tm-faq-wrap { max-width: 820px; margin: 0 auto; }
    .tm-faq-item {
      border: 1px solid var(--bk-border);
      border-radius: 12px !important;
      overflow: hidden;
      margin-bottom: 14px;
      background: #fff;
    }
    .tm-faq-item .accordion-button {
      font-weight: 700;
      font-size: .92rem;
      color: var(--bk-ink);
    }
    .tm-faq-item .accordion-button:not(.collapsed) {
      background: #eaf4fd;
      color: var(--bk-primary-dk);
      box-shadow: none;
    }
    .tm-faq-item .accordion-button:focus { box-shadow: none; }
    .tm-faq-item .accordion-body {
      color: var(--bk-muted);
      font-size: .86rem;
      line-height: 1.7;
    }

    /* ══════════════ Mobile sticky "Book Now" bar ══════════════ */
    .tm-sticky-bar {
      position: fixed;
      left: 0; right: 0; bottom: 0;
      z-index: 950;
      padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
      background: linear-gradient(135deg, #0C74C5, #095e9f);
      box-shadow: 0 -6px 20px rgba(0,0,0,.18);
      transform: translateY(120%);
      transition: transform .3s ease;
      display: none;
    }
    .tm-sticky-bar.show { transform: translateY(0); }
    .tm-sticky-bar a {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      color: #fff;
      font-weight: 700;
      font-size: .92rem;
      text-decoration: none;
    }

    @media (max-width: 991px) {
      .tm-stats-grid { grid-template-columns: repeat(2, 1fr); border-radius: 14px; }
      .tm-stat-item:nth-child(2) { border-right: none; }
      .tm-stat-item { border-bottom: 1px solid var(--bk-border); }
      .tm-stat-item:nth-last-child(-n+2) { border-bottom: none; }
      .tm-hero-visual { margin-top: 30px; }
      .tm-float-chip { font-size: .72rem; padding: 7px 12px; }
    }

    @media (max-width: 767px) {
      .tm-sticky-bar { display: block; }
      #back-top { bottom: 88px !important; }
    }

    @media (max-width: 576px) {
      .tm-hero { padding: 18px 0 46px; }
      .tm-hero-content h1 { font-size: 1.7rem; }
      .tm-hero-content p { font-size: .9rem; }
      .tm-hero-actions { flex-direction: column; align-items: flex-start; gap: 14px; }
      .tm-hero-actions .bk-btn { width: 100%; text-align: center; justify-content: center; }
      .tm-hero-trust { gap: 8px 14px; }
      .tm-hero-trust span { font-size: .74rem; }
      .tm-hero-visual img { max-height: 240px; }
      .tm-float-chip { font-size: .68rem; padding: 6px 10px; }
      .tm-float-chip-1 { top: 2%; left: -4%; }
      .tm-float-chip-2 { top: 42%; right: -4%; }
      .tm-float-chip-3 { bottom: 4%; left: 6%; }

      .tm-stats-grid { grid-template-columns: repeat(2, 1fr); margin: 0 12px; }
      .tm-stat-item { padding: 18px 8px; }
      .tm-stat-item h3 { font-size: 1.3rem; }
      .tm-stat-item p { font-size: .68rem; }

      .tm-step-grid { grid-template-columns: 1fr; }
      .tm-faq-section { padding: 36px 0; }

      .tm-info-section { padding: 40px 0; }
      .tm-lede h1 { font-size: 1.5rem; }
      .bk-shell { padding: 6px 0 90px; }
      .bk-card { padding: 18px 14px; }
      .bk-step .lbl { display: none; }
      .bk-stepper { margin-bottom: 22px; }

      .bk-dept-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .bk-dept-card { padding: 14px 8px; }
      .bk-slot-grid { grid-template-columns: repeat(auto-fill, minmax(78px, 1fr)); gap: 8px; }
      .bk-slot { padding: 12px 4px; font-size: .84rem; }
      .bk-doctor-card { padding: 14px; gap: 11px; }
      .bk-doctor-card .avatar { width: 48px; height: 48px; font-size: 1.05rem; }

      .bk-pane h4 { font-size: 1.12rem; }
      .bk-pane .sub { font-size: .82rem; margin-bottom: 16px; }

      /* The wizard's own step-nav only pins to the bottom of the screen once
         the user has actually scrolled into the booking section (tracked via
         body.tm-in-booking below) — this page has a hero/info/FAQ stack above
         the wizard, so pinning it unconditionally (as on the single-purpose
         book-appointment.php) would float a "Continue" bar over the hero. */
      body.tm-in-booking .bk-nav {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        margin: 0;
        padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
        background: #fff;
        border-top: 1px solid var(--bk-border);
        box-shadow: 0 -4px 18px rgba(20, 40, 70, .08);
        z-index: 40;
        gap: 10px;
      }
      .bk-nav .bk-btn { flex: 1; padding: 13px 10px; }
      .bk-nav span:empty { display: none; }
      body.tm-in-booking .bk-success .bk-nav { position: static; box-shadow: none; border: 0; }
    }
  </style>
</head>

<body>
  <?php include("header.php") ?>

  <!-- Telemedicine Hero -->
  <section class="tm-hero">
    <div class="tm-hero-blob tm-hero-blob-1"></div>
    <div class="tm-hero-blob tm-hero-blob-2"></div>
    <div class="container">
      <ul class="breadcrumb-items tm-hero-crumbs wow fadeInDown" data-wow-delay=".1s">
        <li><a href="<?= BASE_URL ?>">Home</a></li>
        <li>//</li>
        <li>Online Clinic</li>
      </ul>
      <div class="row align-items-center g-4">
        <div class="col-lg-6">
          <div class="tm-hero-content">
            <span class="tm-hero-tag wow fadeInUp" data-wow-delay=".2s"><i class="fas fa-circle tm-pulse-dot"></i> Doctors Online Now</span>
            <h1 class="wow fadeInUp" data-wow-delay=".3s">Consult a Doctor Online, <span>From Anywhere</span></h1>
            <p class="wow fadeInUp" data-wow-delay=".4s">
              Skip the travel and the waiting room. Talk to a verified, HPR-registered doctor over secure
              video, get your e-prescription instantly, and keep everything linked to your ABHA health record.
            </p>
            <div class="tm-hero-actions wow fadeInUp" data-wow-delay=".5s">
              <a href="#tmBooking" class="bk-btn bk-btn-primary tm-scroll-link"><i class="far fa-calendar-check me-1"></i> Book Video Consultation</a>
              <a href="tel:<?= htmlspecialchars($contact['phone'] ?? '') ?>" class="tm-hero-call"><i class="fas fa-phone-volume"></i> Call <?= htmlspecialchars($contact['phone'] ?? '') ?></a>
            </div>
            <div class="tm-hero-trust wow fadeInUp" data-wow-delay=".6s">
              <span><i class="fas fa-check-circle"></i> HPR Verified Doctors</span>
              <span><i class="fas fa-check-circle"></i> ABHA / ABDM Linked</span>
              <span><i class="fas fa-check-circle"></i> Secure Video</span>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="tm-hero-visual wow fadeInUp" data-wow-delay=".3s">
            <img src="<?= BASE_URL ?>assets/img/home-1/hero/hero-img.png" alt="Doctor available for online video consultation" class="img-fluid">
            <div class="tm-float-chip tm-float-chip-1"><i class="fas fa-video"></i> Live Video Consult</div>
            <div class="tm-float-chip tm-float-chip-2"><i class="fas fa-id-card"></i> ABHA Linked</div>
            <div class="tm-float-chip tm-float-chip-3"><i class="fas fa-star"></i> 4.8 / 5 Rating</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Trust stats strip -->
  <section class="tm-stats-strip">
    <div class="container">
      <div class="tm-stats-grid">
        <div class="tm-stat-item wow fadeInUp" data-wow-delay=".1s">
          <h3><span class="odometer" data-count="30">00</span>+</h3>
          <p>Verified Doctors</p>
        </div>
        <div class="tm-stat-item wow fadeInUp" data-wow-delay=".2s">
          <h3><span class="odometer" data-count="2">00</span>k+</h3>
          <p>Consultations Done</p>
        </div>
        <div class="tm-stat-item wow fadeInUp" data-wow-delay=".3s">
          <h3><span class="odometer" data-count="<?= count($departments) ?>">00</span>+</h3>
          <p>Departments Covered</p>
        </div>
        <div class="tm-stat-item wow fadeInUp" data-wow-delay=".4s">
          <h3><span class="odometer" data-count="24">00</span>/7</h3>
          <p>Online Booking</p>
        </div>
      </div>
    </div>
  </section>

  <!-- What is Telemedicine -->
  <section class="tm-info-section">
    <div class="container">
      <div class="tm-lede">
        <span class="subtitle">Consult From Anywhere</span>
        <h1>What is Telemedicine?</h1>
        <p>
          Telemedicine lets you consult a doctor over a secure video call instead of travelling to a clinic.
          You get the same medical attention — diagnosis, advice, prescriptions and follow-up — from wherever
          you are. Every consultation on our Online Clinic is delivered by verified, HPR-registered doctors,
          and your records are linked to your ABHA (Ayushman Bharat Health Account) so your health history
          stays with you, digitally and securely, as per NHA/ABDM guidelines.
        </p>
      </div>

      <div class="tm-benefit-grid">
        <div class="tm-benefit-card wow fadeInUp" data-wow-delay=".1s">
          <div class="ic"><i class="fas fa-laptop-medical"></i></div>
          <h5>Consult From Home</h5>
          <p>No travel, no waiting rooms. See a doctor over video from wherever you are, at your scheduled slot.</p>
        </div>
        <div class="tm-benefit-card wow fadeInUp" data-wow-delay=".2s">
          <div class="ic"><i class="fas fa-user-md"></i></div>
          <h5>Verified &amp; HPR-Registered Doctors</h5>
          <p>Every doctor on our panel is verified, with HPR registration checked against NHA's Aadhaar-linked registry.</p>
        </div>
        <div class="tm-benefit-card wow fadeInUp" data-wow-delay=".3s">
          <div class="ic"><i class="fas fa-id-card"></i></div>
          <h5>ABHA / ABDM Integrated</h5>
          <p>Your consultation and prescription are linked to your ABHA account, building one lifelong digital health record.</p>
        </div>
        <div class="tm-benefit-card wow fadeInUp" data-wow-delay=".4s">
          <div class="ic"><i class="fas fa-file-prescription"></i></div>
          <h5>Digital e-Prescription</h5>
          <p>Get your prescription, advice and follow-up instructions digitally — no paper to lose or misplace.</p>
        </div>
        <div class="tm-benefit-card wow fadeInUp" data-wow-delay=".5s">
          <div class="ic"><i class="fas fa-shield-alt"></i></div>
          <h5>Secure &amp; Confidential</h5>
          <p>Your consultation and health records are encrypted and accessible only to you and your treating doctor.</p>
        </div>
        <div class="tm-benefit-card wow fadeInUp" data-wow-delay=".6s">
          <div class="ic"><i class="fas fa-indian-rupee-sign"></i></div>
          <h5>Transparent Pricing</h5>
          <p>See each doctor's consultation fee upfront before you book — pay securely online, only when you confirm.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- How it works -->
  <section class="tm-steps-section">
    <div class="container">
      <div class="tm-lede" style="margin-bottom:36px;">
        <span class="subtitle">Simple &amp; Guided</span>
        <h1 style="font-size:1.6rem;">How Your Online Consultation Works</h1>
      </div>
      <div class="tm-step-grid">
        <div class="tm-step-card wow fadeInUp" data-wow-delay=".1s">
          <div class="num">1</div>
          <h5>Choose Department &amp; Doctor</h5>
          <p>Pick the speciality you need and choose from our verified, HPR-registered doctors.</p>
        </div>
        <div class="tm-step-card wow fadeInUp" data-wow-delay=".2s">
          <div class="num">2</div>
          <h5>Pick a Convenient Slot</h5>
          <p>See the doctor's real consulting hours and pick an open time slot that works for you.</p>
        </div>
        <div class="tm-step-card wow fadeInUp" data-wow-delay=".3s">
          <div class="num">3</div>
          <h5>Confirm &amp; Pay Securely</h5>
          <p>Share your details, give consent, and pay the consultation fee securely via Razorpay.</p>
        </div>
        <div class="tm-step-card wow fadeInUp" data-wow-delay=".4s">
          <div class="num">4</div>
          <h5>Video Consult &amp; e-Prescription</h5>
          <p>Join your video consultation at the scheduled time and receive your digital prescription, linked to your ABHA.</p>
        </div>
      </div>
      <div class="tm-cta-strip">
        <a href="#tmBooking" class="bk-btn bk-btn-primary tm-scroll-link">
          <i class="far fa-calendar-check"></i> Book Your Online Consultation
        </a>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="tm-faq-section">
    <div class="container">
      <div class="tm-lede" style="margin-bottom:30px;">
        <span class="subtitle">Good To Know</span>
        <h1 style="font-size:1.6rem;">Telemedicine — Frequently Asked Questions</h1>
      </div>
      <div class="tm-faq-wrap">
        <div class="accordion" id="tmFaqAccordion">
          <div class="accordion-item tm-faq-item wow fadeInUp" data-wow-delay=".1s">
            <h5 class="accordion-header" id="tmFaqH1">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tmFaqC1" aria-expanded="false" aria-controls="tmFaqC1">
                Do I need any special app for the video consultation?
              </button>
            </h5>
            <div id="tmFaqC1" class="accordion-collapse collapse" aria-labelledby="tmFaqH1" data-bs-parent="#tmFaqAccordion">
              <div class="accordion-body">No app download needed. Once your appointment is confirmed, you get a secure video call link by email — just open it in your browser at the scheduled time, on your phone, tablet or laptop.</div>
            </div>
          </div>
          <div class="accordion-item tm-faq-item wow fadeInUp" data-wow-delay=".2s">
            <h5 class="accordion-header" id="tmFaqH2">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tmFaqC2" aria-expanded="false" aria-controls="tmFaqC2">
                Is my ABHA number required to book?
              </button>
            </h5>
            <div id="tmFaqC2" class="accordion-collapse collapse" aria-labelledby="tmFaqH2" data-bs-parent="#tmFaqAccordion">
              <div class="accordion-body">No, ABHA is optional at booking. If you add it, your consultation and prescription get linked to your Ayushman Bharat Health Account automatically, per NHA/ABDM guidelines — building one lifelong digital health record.</div>
            </div>
          </div>
          <div class="accordion-item tm-faq-item wow fadeInUp" data-wow-delay=".3s">
            <h5 class="accordion-header" id="tmFaqH3">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tmFaqC3" aria-expanded="false" aria-controls="tmFaqC3">
                How and when do I pay the consultation fee?
              </button>
            </h5>
            <div id="tmFaqC3" class="accordion-collapse collapse" aria-labelledby="tmFaqH3" data-bs-parent="#tmFaqAccordion">
              <div class="accordion-body">You'll see the doctor's fee upfront before booking. Payment is collected securely via Razorpay (cards, UPI, netbanking) only at the final step, right before your slot is confirmed.</div>
            </div>
          </div>
          <div class="accordion-item tm-faq-item wow fadeInUp" data-wow-delay=".4s">
            <h5 class="accordion-header" id="tmFaqH4">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tmFaqC4" aria-expanded="false" aria-controls="tmFaqC4">
                Will I get a prescription after the call?
              </button>
            </h5>
            <div id="tmFaqC4" class="accordion-collapse collapse" aria-labelledby="tmFaqH4" data-bs-parent="#tmFaqAccordion">
              <div class="accordion-body">Yes — your doctor issues a digital e-prescription after the consultation, accessible from your account, with medicines, advice and any follow-up instructions.</div>
            </div>
          </div>
          <div class="accordion-item tm-faq-item wow fadeInUp" data-wow-delay=".5s">
            <h5 class="accordion-header" id="tmFaqH5">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tmFaqC5" aria-expanded="false" aria-controls="tmFaqC5">
                Can I book for a family member instead of myself?
              </button>
            </h5>
            <div id="tmFaqC5" class="accordion-collapse collapse" aria-labelledby="tmFaqH5" data-bs-parent="#tmFaqAccordion">
              <div class="accordion-body">Yes — on the details step, choose "Someone else" and add their name. The consultation is booked under your contact details but recorded for the patient you specify.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Booking wizard, locked to Online Consultation -->
  <section class="bk-shell" id="tmBooking">
    <div class="container">

      <div class="tm-lede" style="margin-bottom:28px;">
        <span class="subtitle">Online Clinic</span>
        <h1 style="font-size:1.6rem;">Book Your Telemedicine Consultation</h1>
      </div>

      <!-- Stepper -->
      <div class="bk-stepper" id="bkStepper">
        <div class="bk-step active" data-step="1"><div class="dot">1</div><div class="lbl">Department</div></div>
        <div class="bk-step" data-step="2"><div class="dot">2</div><div class="lbl">Doctor</div></div>
        <div class="bk-step" data-step="3"><div class="dot">3</div><div class="lbl">Date &amp; Time</div></div>
        <div class="bk-step" data-step="4"><div class="dot">4</div><div class="lbl">Your Details</div></div>
      </div>

      <div class="bk-card">

        <div class="bk-summary" id="bkSummary"></div>

        <!-- STEP 1: Department -->
        <div class="bk-pane active" id="bkPane1">
          <h4>Choose a department</h4>
          <p class="sub">Pick the speciality that best matches what you need help with.</p>
          <div class="bk-dept-grid">
            <?php foreach ($departments as $dept): ?>
              <div class="bk-dept-card" data-slug="<?= htmlspecialchars($dept['slug_url']) ?>" data-name="<?= htmlspecialchars(trim($dept['categories'])) ?>">
                <div class="ic">
                  <?php if (!empty($dept['sub_cat_img'])): ?>
                    <img src="<?= BASE_URL ?>admin/uploads/sub-category/<?= htmlspecialchars($dept['sub_cat_img']) ?>" alt="">
                  <?php else: ?>
                    <i class="fas fa-stethoscope"></i>
                  <?php endif; ?>
                </div>
                <div class="name"><?= htmlspecialchars(trim($dept['categories'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="bk-nav">
            <span></span>
            <button type="button" class="bk-btn bk-btn-primary" id="bkNext1" disabled>Continue <i class="fas fa-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- STEP 2: Doctor -->
        <div class="bk-pane" id="bkPane2">
          <h4>Choose a doctor</h4>
          <p class="sub" id="bkDoctorSub">Available specialists in this department for online video consultation.</p>
          <div id="bkDoctorList"></div>
          <div class="bk-nav">
            <button type="button" class="bk-btn bk-btn-outline" data-back="1"><i class="fas fa-arrow-left me-1"></i> Back</button>
            <button type="button" class="bk-btn bk-btn-primary" id="bkNext2" disabled>Continue <i class="fas fa-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- STEP 3: Date & Time -->
        <div class="bk-pane" id="bkPane3">
          <h4>Pick a date &amp; time</h4>
          <p class="sub">Only the doctor's consulting days &amp; hours are shown. Booked slots are greyed out.</p>

          <div class="tm-mode-badge"><i class="fas fa-video"></i> Online Video Consultation</div>

          <div class="bk-sched-info" id="bkSchedInfo" style="display:none;">
            <i class="fas fa-calendar-alt"></i> <span id="bkSchedText"></span>
          </div>

          <div class="bk-field mb-2">
            <label>Choose a day</label>
            <div class="bk-date-strip" id="bkDateStrip"></div>
            <details class="bk-more-date">
              <summary><i class="fas fa-calendar-day me-1"></i>Pick another date</summary>
              <input type="date" class="form-control" id="bkDate" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" style="max-width:220px;">
            </details>
          </div>

          <label class="bk-field" style="display:block;"><span style="font-size:.82rem;font-weight:600;color:var(--bk-ink);">Available time slots</span></label>
          <div id="bkSlotArea">
            <div class="bk-slot-grid" id="bkSlotGrid"></div>
          </div>
          <div class="bk-nav">
            <button type="button" class="bk-btn bk-btn-outline" data-back="2"><i class="fas fa-arrow-left me-1"></i> Back</button>
            <button type="button" class="bk-btn bk-btn-primary" id="bkNext3" disabled>Continue <i class="fas fa-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- STEP 4: Details + submit -->
        <div class="bk-pane" id="bkPane4">
          <h4>Your details</h4>
          <p class="sub">We'll use this to confirm your video consultation.</p>

          <div id="bkFormError" class="alert alert-danger py-2" style="font-size:.85rem;"></div>

          <form id="bkForm">
            <div class="row g-3">
              <div class="col-md-6 bk-field">
                <label>Full Name *</label>
                <input type="text" class="form-control" name="name" required
                  value="<?= $logged_in_patient ? htmlspecialchars(trim($logged_in_patient['name'] . ' ' . $logged_in_patient['last_name'])) : '' ?>">
              </div>
              <div class="col-md-6 bk-field">
                <label>Email Address *</label>
                <input type="email" class="form-control" name="email" required
                  value="<?= $logged_in_patient ? htmlspecialchars($logged_in_patient['email']) : '' ?>">
              </div>
              <div class="col-md-6 bk-field">
                <label>Mobile Number *</label>
                <input type="text" class="form-control" name="phone" inputmode="numeric" maxlength="10" required
                  value="<?= $logged_in_patient ? htmlspecialchars($logged_in_patient['mobile']) : '' ?>">
              </div>
              <div class="col-md-6 bk-field">
                <label>ABHA Number <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" name="abha_number" placeholder="XX-XXXX-XXXX-XXXX"
                  value="<?= $logged_in_patient && !empty($logged_in_patient['abha_id']) ? htmlspecialchars($logged_in_patient['abha_id']) : '' ?>">
              </div>

              <div class="col-12 bk-field">
                <label>Who is this consultation for?</label>
                <div class="bk-visit-toggle">
                  <input type="radio" name="visit_person" id="bkVisitSelf" value="self" checked>
                  <label for="bkVisitSelf"><i class="fas fa-user me-1"></i> Myself</label>
                  <input type="radio" name="visit_person" id="bkVisitOther" value="other">
                  <label for="bkVisitOther"><i class="fas fa-user-friends me-1"></i> Someone else</label>
                </div>
              </div>
              <div class="col-md-6 bk-field d-none" id="bkVisitedNameWrap">
                <label>Patient's Name *</label>
                <input type="text" class="form-control" name="visited_person_name">
              </div>

              <div class="col-12 bk-field">
                <label>Notes for the doctor <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control" name="notes" rows="3" placeholder="Briefly describe your symptoms or reason for consultation…"></textarea>
              </div>
            </div>

            <div class="bk-consent">
              <label class="d-flex gap-2 mb-0" style="cursor:pointer;">
                <input type="checkbox" name="consent_given" id="bkConsent" required style="margin-top:3px;">
                <span>
                  I agree to the telemedicine consultation terms and to my health records being created / linked / shared
                  through ABHA/ABDM as per applicable guidelines, and confirm my details are correct.
                  <a href="<?= BASE_URL ?>terms-and-condition/" class="text-danger">Terms &amp; Privacy Policy</a>. *
                  <details class="mt-1">
                    <summary style="cursor:pointer;color:#92400e;font-size:.78rem;">Read full consent (English / हिन्दी)</summary>
                    <div style="margin-top:6px;">
                      I voluntarily consent to receive medical consultation through Telemedicine (Video Call, Audio Call, Chat or Digital Platform), understand that the doctor's advice will be based on the information and documents provided by me, agree to the secure storage and management of my digital health records, consent to the creation, linking, updating and sharing of my health records through ABHA/ABDM as per applicable guidelines, and confirm that the information provided by me is true and correct.
                    </div>
                    <div style="margin-top:8px;">
                      मैं स्वेच्छा से टेलीमेडिसिन (वीडियो कॉल, ऑडियो कॉल, चैट या डिजिटल प्लेटफॉर्म) के माध्यम से चिकित्सा परामर्श प्राप्त करने, यह समझने कि चिकित्सक की सलाह मेरे द्वारा प्रदान की गई जानकारी एवं दस्तावेजों के आधार पर होगी, अपने डिजिटल स्वास्थ्य रिकॉर्ड के सुरक्षित संग्रहण एवं प्रबंधन, लागू दिशानिर्देशों के अनुसार ABHA/ABDM के माध्यम से स्वास्थ्य रिकॉर्ड के निर्माण, लिंकिंग, अद्यतन एवं साझा किए जाने तथा मेरे द्वारा प्रदान की गई जानकारी के सही एवं सत्य होने की पुष्टि हेतु अपनी सहमति प्रदान करता/करती हूँ।
                    </div>
                  </details>
                </span>
              </label>
            </div>

            <div class="bk-consent" id="bkFeeNotice" style="display:none;background:#eaf4fd;border-color:#bcdcf5;color:var(--bk-ink);">
              <i class="fas fa-shield-alt me-1" style="color:var(--bk-primary);"></i>
              Consultation fee of <b id="bkFeeAmount"></b> is payable securely via Razorpay (cards / UPI / netbanking) on the next step, before your appointment is confirmed.
            </div>

            <input type="hidden" name="department" id="bkFieldDepartment">
            <input type="hidden" name="doctor_id" id="bkFieldDoctorId">
            <input type="hidden" name="doctor_name" id="bkFieldDoctorName">
            <input type="hidden" name="date" id="bkFieldDate">
            <input type="hidden" name="time" id="bkFieldTime">
            <input type="hidden" name="appointment_type" id="bkFieldMode" value="online">
            <input type="hidden" name="consent_required" value="1">

            <div class="bk-nav">
              <button type="button" class="bk-btn bk-btn-outline" data-back="3"><i class="fas fa-arrow-left me-1"></i> Back</button>
              <button type="submit" class="bk-btn bk-btn-primary" id="bkSubmitBtn">
                <span id="bkSubmitText">Confirm Booking</span>
                <span class="spinner-border spinner-border-sm d-none ms-1" id="bkSubmitSpinner"></span>
              </button>
            </div>
          </form>
        </div>

        <!-- SUCCESS -->
        <div class="bk-success" id="bkSuccess">
          <div class="tick"><i class="fas fa-check"></i></div>
          <h4>Video Consultation Booked!</h4>
          <p class="text-muted mb-0">Your reference number is</p>
          <div class="bk-ref" id="bkRefNumber"></div>
          <p class="text-muted" style="max-width:420px;margin:0 auto;">
            We've sent the details to your email, along with the video call link once confirmed. You can also track
            this appointment from <a href="<?= BASE_URL ?>user-login/">your account</a>.
          </p>
          <a href="<?= BASE_URL ?>" class="bk-btn bk-btn-outline mt-3">Back to Home</a>
        </div>

      </div>
    </div>
  </section>

  <?php include("footer.php") ?>

  <!-- Mobile-only sticky "Book Now" bar -->
  <div class="tm-sticky-bar" id="tmStickyBar">
    <a href="#tmBooking"><i class="fas fa-video me-1"></i> Book Online Consultation</a>
  </div>

  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    (function () {
      const BASE_URL = "<?= BASE_URL ?>";
      const state = { department: null, departmentName: null, doctor: null, date: null, time: null, mode: 'online' };
      let currentStep = 1;

      const steps = document.querySelectorAll('.bk-step');
      const panes = { 1: document.getElementById('bkPane1'), 2: document.getElementById('bkPane2'), 3: document.getElementById('bkPane3'), 4: document.getElementById('bkPane4') };
      const summary = document.getElementById('bkSummary');

      function goToStep(n) {
        currentStep = n;
        Object.keys(panes).forEach(k => panes[k].classList.toggle('active', Number(k) === n));
        steps.forEach(s => {
          const sn = Number(s.dataset.step);
          s.classList.toggle('active', sn === n);
          s.classList.toggle('done', sn < n);
        });
        summary.classList.toggle('show', n >= 2);
        renderSummary();
        window.scrollTo({ top: document.querySelector('.bk-card').offsetTop - 100, behavior: 'smooth' });
      }

      function renderSummary() {
        let html = '';
        if (state.departmentName) html += `<span><i class="fas fa-stethoscope me-1"></i>${state.departmentName}</span>`;
        if (state.doctor) html += `<span><i class="fas fa-user-md me-1"></i>Dr. ${state.doctor.name}</span>`;
        if (state.date) html += `<span><i class="fas fa-calendar me-1"></i>${state.date}</span>`;
        if (state.time) html += `<span><i class="fas fa-clock me-1"></i><b>${state.timeDisplay || state.time}</b></span>`;
        summary.innerHTML = html;
      }

      document.querySelectorAll('[data-back]').forEach(btn => {
        btn.addEventListener('click', () => goToStep(Number(btn.dataset.back)));
      });

      // ── STEP 1: department selection ──
      document.querySelectorAll('.bk-dept-card').forEach(card => {
        card.addEventListener('click', () => {
          document.querySelectorAll('.bk-dept-card').forEach(c => c.classList.remove('selected'));
          card.classList.add('selected');
          state.department = card.dataset.slug;
          state.departmentName = card.dataset.name;
          document.getElementById('bkNext1').disabled = false;
        });
      });
      document.getElementById('bkNext1').addEventListener('click', () => {
        document.getElementById('bkFieldDepartment').value = state.departmentName;
        loadDoctors();
        goToStep(2);
      });

      // ── STEP 2: doctor selection ──
      function loadDoctors() {
        const list = document.getElementById('bkDoctorList');
        list.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        document.getElementById('bkNext2').disabled = true;

        fetch(BASE_URL + 'util/get-doctors-by-department.php?department=' + encodeURIComponent(state.department))
          .then(r => r.json())
          .then(data => {
            if (!data.success || !data.doctors.length) {
              list.innerHTML = `<div class="bk-empty"><i class="fas fa-user-md fa-2x mb-2 d-block" style="opacity:.3;"></i>No doctors are currently listed for ${state.departmentName}. Please choose another department or contact us directly.</div>`;
              return;
            }
            list.innerHTML = data.doctors.map(d => `
              <div class="bk-doctor-card mb-3" data-id="${d.id}" data-name="${escHtml(d.name)}" data-fee="${Number(d.consultation_fee || 0)}">
                <div class="avatar">${d.profile_image ? `<img src="${d.profile_image}" alt="">` : initials(d.name)}</div>
                <div style="flex:1;">
                  <div class="name">Dr. ${escHtml(d.name)} ${d.hpr_verified ? '<span class="hpr-badge"><i class="fas fa-check-circle"></i> HPR Verified</span>' : ''}</div>
                  <div class="meta">${escHtml(d.degrees || '')}${d.specialization ? ' · ' + escHtml(d.specialization) : ''}</div>
                  <div class="meta">${d.experience_years ? d.experience_years + ' yrs experience' : ''}${d.languages ? ' · ' + escHtml(d.languages) : ''}</div>
                  <div class="fee">₹${Number(d.consultation_fee || 0).toLocaleString('en-IN')} consultation fee</div>
                </div>
              </div>
            `).join('');

            list.querySelectorAll('.bk-doctor-card').forEach(card => {
              card.addEventListener('click', () => {
                list.querySelectorAll('.bk-doctor-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                state.doctor = { id: card.dataset.id, name: card.dataset.name, fee: Number(card.dataset.fee || 0) };
                document.getElementById('bkNext2').disabled = false;
              });
            });
          })
          .catch(() => {
            list.innerHTML = '<div class="bk-empty">Could not load doctors. Please try again.</div>';
          });
      }

      document.getElementById('bkNext2').addEventListener('click', () => {
        document.getElementById('bkFieldDoctorId').value = state.doctor.id;
        document.getElementById('bkFieldDoctorName').value = state.doctor.name;
        loadSchedule();
        goToStep(3);
      });

      // ── STEP 3: date & time (mode is fixed to Online — no mode selector) ──
      const dateInput = document.getElementById('bkDate');
      const dateStrip = document.getElementById('bkDateStrip');
      const schedInfo = document.getElementById('bkSchedInfo');
      const schedText = document.getElementById('bkSchedText');
      let scheduleData = null;

      dateInput.addEventListener('change', () => {
        dateStrip.querySelectorAll('.bk-date-chip').forEach(c => {
          c.classList.toggle('selected', c.dataset.date === dateInput.value && !c.classList.contains('disabled'));
        });
        loadSlots();
      });

      function loadSchedule() {
        dateStrip.innerHTML = '<div class="text-muted small py-2">Loading the doctor\'s schedule…</div>';
        schedInfo.style.display = 'none';
        document.getElementById('bkSlotGrid').innerHTML = '';
        document.getElementById('bkNext3').disabled = true;

        fetch(BASE_URL + 'util/get-doctor-schedule.php?doctor_id=' + state.doctor.id)
          .then(r => r.json())
          .then(data => {
            scheduleData = data;
            if (!data.success) { dateStrip.innerHTML = ''; loadSlots(); return; }

            if (data.summary) {
              schedText.textContent = data.summary;
              schedInfo.style.display = 'flex';
            }

            dateStrip.innerHTML = data.dates.map(d => `
              <div class="bk-date-chip ${d.available ? '' : 'disabled'}" data-date="${d.date}">
                <div class="d-dow">${d.is_today ? 'Today' : d.dow}</div>
                <div class="d-day">${d.day}</div>
                <div class="d-mon">${d.month}</div>
              </div>`).join('');

            dateStrip.querySelectorAll('.bk-date-chip:not(.disabled)').forEach(chip => {
              chip.addEventListener('click', () => {
                dateStrip.querySelectorAll('.bk-date-chip').forEach(c => c.classList.remove('selected'));
                chip.classList.add('selected');
                dateInput.value = chip.dataset.date;
                loadSlots();
              });
            });

            // Auto-pick the first available day
            const start = data.first_available || (data.dates[0] && data.dates[0].date);
            if (start) {
              const chip = dateStrip.querySelector(`.bk-date-chip[data-date="${start}"]:not(.disabled)`);
              if (chip) {
                chip.click();
                chip.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
                const first = data.dates.find(d => d.date === start);
                if (first && !first.is_today) {
                  const nice = new Date(start + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'short' });
                  schedText.innerHTML = data.summary + ' &nbsp;·&nbsp; <b>Next open day: ' + nice + '</b>';
                  schedInfo.style.display = 'flex';
                }
              } else { dateInput.value = start; loadSlots(); }
            } else {
              document.getElementById('bkSlotGrid').innerHTML =
                '<div class="bk-empty w-100"><i class="fas fa-calendar-times fa-2x mb-2 d-block" style="opacity:.3;"></i>This doctor isn\'t accepting bookings right now. Please choose another doctor.</div>';
            }
          })
          .catch(() => { dateStrip.innerHTML = ''; loadSlots(); });
      }

      function loadSlots() {
        const grid = document.getElementById('bkSlotGrid');
        state.date = dateInput.value;
        state.time = null;
        state.timeDisplay = null;
        document.getElementById('bkNext3').disabled = true;
        renderSummary();

        grid.innerHTML = '<div class="text-center py-4 w-100"><div class="spinner-border text-primary"></div></div>';

        fetch(BASE_URL + `util/get-available-slots.php?doctor_id=${state.doctor.id}&date=${state.date}`)
          .then(r => r.json())
          .then(data => {
            if (!data.success || !data.slots.length) {
              let why = 'No slots available for this date. Try another day.';
              if (scheduleData && scheduleData.success) {
                const dow = new Date(state.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                const wd = scheduleData.week && scheduleData.week[dow];
                if (wd && !wd.available) why = `Dr. ${state.doctor.name} doesn't consult on ${dow}s. Pick a highlighted day above.`;
                else why = 'No slots left for this day — they are booked or the consulting hours are over. Try another day.';
              }
              grid.innerHTML = `<div class="bk-empty w-100"><i class="fas fa-calendar-times fa-2x mb-2 d-block" style="opacity:.3;"></i>${why}</div>`;
              return;
            }
            grid.innerHTML = data.slots.map(s => `
              <div class="bk-slot ${s.booked ? 'booked' : ''}" data-time="${s.time}" data-display="${s.display}">${s.display}</div>
            `).join('');

            grid.querySelectorAll('.bk-slot:not(.booked)').forEach(slot => {
              slot.addEventListener('click', () => {
                grid.querySelectorAll('.bk-slot').forEach(s => s.classList.remove('selected'));
                slot.classList.add('selected');
                state.time = slot.dataset.time;
                state.timeDisplay = slot.dataset.display;
                document.getElementById('bkNext3').disabled = false;
                renderSummary();
              });
            });
          })
          .catch(() => {
            grid.innerHTML = '<div class="bk-empty w-100">Could not load time slots. Please try again.</div>';
          });
      }

      document.getElementById('bkNext3').addEventListener('click', () => {
        document.getElementById('bkFieldDate').value = state.date;
        document.getElementById('bkFieldTime').value = state.time;
        document.getElementById('bkFieldMode').value = 'online';

        const feeNotice = document.getElementById('bkFeeNotice');
        if (state.doctor.fee > 0) {
          document.getElementById('bkFeeAmount').textContent = '₹' + state.doctor.fee.toLocaleString('en-IN');
          feeNotice.style.display = 'block';
        } else {
          feeNotice.style.display = 'none';
        }

        goToStep(4);
      });

      // ── STEP 4: visit-for toggle ──
      document.querySelectorAll('input[name="visit_person"]').forEach(r => {
        r.addEventListener('change', function () {
          const wrap = document.getElementById('bkVisitedNameWrap');
          const input = wrap.querySelector('input');
          if (this.value === 'other') {
            wrap.classList.remove('d-none');
            input.setAttribute('required', 'required');
          } else {
            wrap.classList.add('d-none');
            input.removeAttribute('required');
          }
        });
      });

      // ── STEP 4: submit (with Razorpay payment step when the doctor has a fee) ──
      const errorBox = document.getElementById('bkFormError');
      const submitBtn = document.getElementById('bkSubmitBtn');
      const submitText = document.getElementById('bkSubmitText');
      const submitSpinner = document.getElementById('bkSubmitSpinner');

      function showError(msg) {
        resetSubmitBtn();
        errorBox.textContent = msg;
        errorBox.style.display = 'block';
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      function resetSubmitBtn() {
        submitBtn.disabled = false;
        submitText.textContent = 'Confirm Booking';
        submitSpinner.classList.add('d-none');
      }

      function busy(label) {
        submitBtn.disabled = true;
        submitText.textContent = label;
        submitSpinner.classList.remove('d-none');
      }

      function finalizeBooking(formData) {
        fetch(BASE_URL + 'util/appointment-handler.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            resetSubmitBtn();
            if (data.status === 'success') {
              document.getElementById('bkRefNumber').textContent = data.appointment_id || '';
              document.querySelector('.bk-stepper').style.display = 'none';
              summary.classList.remove('show');
              panes[4].classList.remove('active');
              document.getElementById('bkSuccess').style.display = 'block';
            } else {
              showError(data.message || 'Something went wrong. Please try again.');
            }
          })
          .catch(() => showError('Network error. Please check your connection and try again.'));
      }

      document.getElementById('bkForm').addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.style.display = 'none';
        busy('Booking…');

        const formData = new FormData(this);

        if (!state.doctor || !state.doctor.fee) {
          finalizeBooking(formData);
          return;
        }

        busy('Preparing payment…');
        const orderData = new FormData();
        orderData.append('doctor_id', state.doctor.id);

        fetch(BASE_URL + 'util/create-razorpay-order.php', { method: 'POST', body: orderData })
          .then(r => r.json())
          .then(order => {
            if (!order.success) {
              showError(order.message || 'Could not start the payment. Please try again.');
              return;
            }
            if (!order.payment_required) {
              finalizeBooking(formData);
              return;
            }

            resetSubmitBtn(); // Checkout has its own UI from here

            const rzp = new Razorpay({
              key: order.key_id,
              order_id: order.order_id,
              amount: order.amount,
              currency: order.currency,
              name: 'Rejuvenate Digital Health',
              description: 'Online consultation with Dr. ' + (order.doctor_name || state.doctor.name),
              prefill: {
                name: formData.get('name') || '',
                email: formData.get('email') || '',
                contact: formData.get('phone') || '',
              },
              theme: { color: '#0C74C5' },
              handler: function (response) {
                formData.append('razorpay_order_id', response.razorpay_order_id);
                formData.append('razorpay_payment_id', response.razorpay_payment_id);
                formData.append('razorpay_signature', response.razorpay_signature);
                busy('Booking…');
                finalizeBooking(formData);
              },
              modal: {
                ondismiss: function () {
                  showError('Payment was cancelled. Your appointment was not booked.');
                },
              },
            });
            rzp.on('payment.failed', function () {
              showError('Payment failed. Please try again.');
            });
            rzp.open();
          })
          .catch(() => showError('Network error while starting payment. Please try again.'));
      });

      function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }
      function initials(name) {
        return (name || '').trim().charAt(0).toUpperCase() || '?';
      }

      // ── Smooth-scroll for every "Book Now" style link on this page ──
      document.querySelectorAll('a[href="#tmBooking"]').forEach(a => {
        a.addEventListener('click', e => {
          e.preventDefault();
          document.getElementById('tmBooking').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });

      // ── Mobile sticky "Book Now" bar: appears once the hero has scrolled
      // out of view, hides once the booking card itself is on screen ──
      const stickyBar = document.getElementById('tmStickyBar');
      const heroEl = document.querySelector('.tm-hero');
      const bookingCard = document.querySelector('#tmBooking .bk-card');
      if (stickyBar && heroEl && bookingCard && 'IntersectionObserver' in window) {
        const heroObserver = new IntersectionObserver(([entry]) => {
          if (!entry.isIntersecting) stickyBar.classList.add('show');
          else stickyBar.classList.remove('show');
        }, { threshold: 0 });
        heroObserver.observe(heroEl);

        const bookingObserver = new IntersectionObserver(([entry]) => {
          document.body.classList.toggle('tm-in-booking', entry.isIntersecting);
          if (entry.isIntersecting) stickyBar.classList.remove('show');
        }, { threshold: 0.15 });
        bookingObserver.observe(bookingCard);
      }
    })();
  </script>
</body>

</html>
