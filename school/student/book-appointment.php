<?php
/**
 * Student-initiated OPD booking request. Deliberately does NOT create an
 * appointment directly — it creates a school_booking_holds row and asks
 * the parent (via a signed link, lib/BookingApprovalToken.php) to confirm
 * consent and pay before the booking is real. See
 * database/migration_school_membership_phase2.sql and
 * school/parent-booking-approval.php, which is where the hold actually
 * turns into an appointments row.
 *
 * The 25% OPD membership discount is NOT applied here — that's Phase 3.
 * This charges the doctor's normal consultation fee (collected from the
 * parent on the approval page).
 */
include_once "../../config/connect.php";
include_once "auth.php";
require_once __DIR__ . '/../../util/function.php'; // get_sub_category(), get_favicon()
require_once __DIR__ . '/../../lib/BookingApprovalToken.php';
require_once __DIR__ . '/../../lib/WhatsAppOtp.php';

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$parent_mobile = trim($student['parent_mobile'] ?? '');

/* Lazily expire any hold past its window — no cron needed for this volume. */
$conn->query("UPDATE school_booking_holds SET status='expired'
              WHERE member_id=" . (int) $student_id . " AND status='awaiting_parent' AND expires_at < NOW()");

$page_error = '';

/* ── Create a hold ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_hold') {
    $doctorId   = (int) ($_POST['doctor_id'] ?? 0);
    $doctorName = trim($_POST['doctor_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $date       = trim($_POST['date'] ?? '');
    $time       = trim($_POST['time'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$parent_mobile) {
        $page_error = 'No parent/guardian mobile number is on file for you. Please ask your school admin to add it before booking.';
    } elseif (!$doctorId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        $page_error = 'Please choose a doctor, date and time.';
    } else {
        $chk = $conn->prepare("SELECT id, name, consultation_fee FROM doctors WHERE id=? AND status='Active' LIMIT 1");
        $chk->bind_param('i', $doctorId);
        $chk->execute();
        $doctor = $chk->get_result()->fetch_assoc();
        if (!$doctor) {
            $page_error = 'That doctor is not available. Please choose another.';
        } else {
            $timeFull = strlen($time) === 5 ? $time . ':00' : $time;
            $ins = $conn->prepare("INSERT INTO school_booking_holds
                (member_id, school_id, doctor_id, department, appointment_date, appointment_time, notes, status, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'awaiting_parent', DATE_ADD(NOW(), INTERVAL 72 HOUR))");
            $ins->bind_param('iiissss', $student_id, $student_school_id, $doctorId, $department, $date, $timeFull, $notes);
            $ins->execute();
            $holdId = $ins->insert_id;

            $token = booking_generate_token($holdId, 72);
            $approvalUrl = rtrim($_ENV['SITE'] ?? BASE_URL, '/') . '/school/parent-booking-approval.php?token=' . $token;
            $whenText = date('d M Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($timeFull));

            wa_send_booking_approval_link($parent_mobile, $student['name'], $doctor['name'] ?: $doctorName, $whenText, $approvalUrl);

            header('Location: book-appointment.php?sent=1');
            exit;
        }
    }
}

/* ── Recent requests, for transparency ── */
$holds = [];
$res = $conn->prepare("SELECT sbh.*, d.name AS doctor_name FROM school_booking_holds sbh
    JOIN doctors d ON d.id = sbh.doctor_id
    WHERE sbh.member_id=? ORDER BY sbh.requested_at DESC LIMIT 5");
$res->bind_param('i', $student_id);
$res->execute();
$holds = $res->get_result()->fetch_all(MYSQLI_ASSOC);

$departments = get_sub_category();
$statusLabel = ['awaiting_parent' => 'Waiting for parent', 'approved' => 'Booked', 'rejected' => 'Declined', 'expired' => 'Expired'];
$statusColor = ['awaiting_parent' => '#d97706', 'approved' => '#16a34a', 'rejected' => '#dc2626', 'expired' => '#6b7280'];
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book Appointment | <?= htmlspecialchars($student_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    :root { --primary: #0C74C5; --accent: #02c9b8; --slate-600: #475569; --slate-200: #e2e8f0; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }
    .s-body { padding: 0 0 20px; }
    /* Desktop: booking wizard on the left, requests/how-it-works on the right.
       Mobile/tablet: everything stacks full-width in the same reading order. */
    .book-col-main { min-width: 0; }
    .book-col-side { min-width: 0; }
    @media (max-width: 991.98px) {
      .book-col-main, .book-col-side { max-width: 640px; margin: 0 auto; }
    }

    /* Section cards (same language as health.php / abha.php) */
    .s-card { background: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; box-shadow: 0 1px 8px rgba(0,0,0,.06); }
    .s-card-title { font-size: .82rem; font-weight: 700; color: #1f2937; display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
    .s-card-title .ic-badge { width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .8rem; }

    .notice-parent { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 14px 16px; font-size: .82rem; color: #1e3a5f; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 10px; }
    .notice-parent i { color: var(--primary); margin-top: 2px; }

    .req-row { background: #f9fafb; border-radius: 10px; padding: 10px 14px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: .82rem; }
    .req-row:last-child { margin-bottom: 0; }
    .req-status { font-size: .68rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; color: #fff; white-space: nowrap; }

    .how-step { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 14px; }
    .how-step:last-child { margin-bottom: 0; }
    .how-num { width: 24px; height: 24px; border-radius: 50%; background: #eff6ff; color: var(--primary); font-size: .72rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .how-step p { margin: 0; font-size: .78rem; color: #6b7280; line-height: 1.45; }
    .how-step strong { color: #1f2937; font-size: .83rem; }

    /* Step progress bar */
    .wiz-progress { display: flex; align-items: center; margin-bottom: 22px; }
    .wiz-progress .wp-step { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 0 0 auto; }
    .wiz-progress .wp-circle {
      width: 30px; height: 30px; border-radius: 50%; background: #f1f5f9; color: #94a3b8;
      display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 800;
      border: 2px solid #e5e7eb; transition: all .2s;
    }
    .wiz-progress .wp-label { font-size: .64rem; font-weight: 600; color: #9ca3af; white-space: nowrap; }
    .wiz-progress .wp-line { flex: 1 1 auto; height: 2px; background: #e5e7eb; margin: 0 4px 18px; }
    .wiz-progress .wp-step.done .wp-circle { background: var(--primary); border-color: var(--primary); color: #fff; }
    .wiz-progress .wp-step.done .wp-label { color: var(--primary); }
    .wiz-progress .wp-step.current .wp-circle { border-color: var(--primary); color: var(--primary); box-shadow: 0 0 0 4px rgba(12,116,197,.12); }
    .wiz-progress .wp-step.current .wp-label { color: #1f2937; }
    .wiz-progress .wp-line.done { background: var(--primary); }

    .wiz-step { display: none; }
    .wiz-step.active { display: block; }
    .wiz-title { font-size: 1rem; font-weight: 700; margin-bottom: 4px; color: #1f2937; }
    .wiz-sub { font-size: .8rem; color: #6b7280; margin-bottom: 14px; }

    .grid-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .pick-card { position: relative; background: #fff; border: 2px solid #e5e7eb; border-radius: 12px; padding: 14px 10px; text-align: center; cursor: pointer; font-size: .78rem; font-weight: 600; color: #374151; transition: all .15s; }
    .pick-card:hover { border-color: #c7d2e5; background: #f9fafb; }
    .pick-card.sel { border-color: var(--primary); background: #eff6ff; color: var(--primary); }
    .pick-card i { display: block; font-size: 1.3rem; margin-bottom: 6px; color: var(--primary); }
    .pick-card.sel::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 6px; right: 8px; font-size: .68rem; color: var(--primary); }

    .doc-row { background: #fff; border: 2px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: all .15s; }
    .doc-row:hover { border-color: #c7d2e5; background: #f9fafb; }
    .doc-row.sel { border-color: var(--primary); background: #eff6ff; }
    .doc-row .dname { font-weight: 700; font-size: .88rem; color: #1f2937; }
    .doc-row .dspec { font-size: .74rem; color: #6b7280; }
    .doc-row .dfee { font-weight: 700; color: var(--primary); font-size: .85rem; white-space: nowrap; }

    .date-strip { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-bottom: 14px; }
    .date-chip { flex: 0 0 auto; width: 54px; text-align: center; border: 2px solid #e5e7eb; border-radius: 10px; padding: 8px 4px; cursor: pointer; font-size: .72rem; transition: all .15s; }
    .date-chip:not(.disabled):hover { border-color: #c7d2e5; background: #f9fafb; }
    .date-chip.sel { border-color: var(--primary); background: #eff6ff; color: var(--primary); font-weight: 700; }
    .date-chip.disabled { opacity: .35; pointer-events: none; }
    .date-chip .dow { font-weight: 700; }
    .date-chip .num { font-size: 1rem; font-weight: 800; }

    .time-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .time-chip { border: 2px solid #e5e7eb; border-radius: 8px; padding: 8px 4px; text-align: center; font-size: .76rem; cursor: pointer; transition: all .15s; }
    .time-chip:not(.booked):hover { border-color: #c7d2e5; background: #f9fafb; }
    .time-chip.sel { border-color: var(--primary); background: #eff6ff; color: var(--primary); font-weight: 700; }
    .time-chip.booked { opacity: .3; pointer-events: none; text-decoration: line-through; }

    /* Floating bottom bar — stays anchored to the viewport (not the card), same left-offset
       behaviour as .school-topbar so it slides with the sidebar on desktop and goes full-width
       on mobile. .wiz-step gets bottom padding so content never sits under it. */
    .wiz-nav {
      display: flex; justify-content: space-between; align-items: center;
      position: fixed; left: var(--sidebar-w); right: 0; bottom: 0;
      background: #fff; border-top: 1px solid #e5e7eb;
      padding: 12px 24px; z-index: 500;
      box-shadow: 0 -4px 16px rgba(0,0,0,.08);
      transition: left .3s ease;
    }
    .wiz-step { padding-bottom: 76px; }
    @media (max-width: 767.98px) {
      .wiz-nav { left: 0; padding: 10px 16px; }
    }
    .btn-wiz { border: none; border-radius: 10px; padding: 10px 18px; font-size: .85rem; font-weight: 700; transition: all .15s; }
    .btn-wiz-primary { background: var(--primary); color: #fff; }
    .btn-wiz-primary:hover:not(:disabled) { background: #0a5fa0; }
    .btn-wiz-primary:disabled { opacity: .4; }
    .btn-wiz-ghost { background: transparent; color: #6b7280; }
    .btn-wiz-ghost:hover { color: #1f2937; }

    @media (max-width: 480px) {
      .s-card { padding: 14px 16px; }
      .wiz-progress .wp-label { display: none; }
    }
  </style>
</head>
<body>
<?php $active_page = 'book'; $base_path = ''; include '../inc/sidebar-student.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;"><i class="fas fa-stethoscope me-2" style="color:var(--accent);"></i>Book Appointment</div>
      <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($student_school) ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">
<div class="s-body">

<?php if (isset($_GET['sent'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> Request sent! We've notified your parent/guardian — they need to approve it before your appointment is booked.</div>
<?php endif; ?>
<?php if ($page_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($page_error) ?></div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-7 book-col-main">

<div class="notice-parent">
    <i class="fas fa-info-circle"></i>
    <div>Booking a doctor yourself doesn't confirm it right away — your parent/guardian will get a link to
    confirm consent and complete payment first. Once they approve, your appointment is booked.</div>
</div>

<?php if (!$parent_mobile): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        No parent/guardian mobile number is on file. Please ask your school admin to add one before you can request a booking.
    </div>
<?php else: ?>

<div class="s-card">

    <!-- Step progress -->
    <div class="wiz-progress" id="wizProgress">
        <div class="wp-step current" data-step="1"><div class="wp-circle">1</div><div class="wp-label">Department</div></div>
        <div class="wp-line" data-line="1"></div>
        <div class="wp-step" data-step="2"><div class="wp-circle">2</div><div class="wp-label">Doctor</div></div>
        <div class="wp-line" data-line="2"></div>
        <div class="wp-step" data-step="3"><div class="wp-circle">3</div><div class="wp-label">Date &amp; Time</div></div>
        <div class="wp-line" data-line="3"></div>
        <div class="wp-step" data-step="4"><div class="wp-circle">4</div><div class="wp-label">Confirm</div></div>
    </div>

    <form method="POST" id="bookForm">
        <input type="hidden" name="action" value="create_hold">
        <input type="hidden" name="department" id="f_department">
        <input type="hidden" name="doctor_id" id="f_doctor_id">
        <input type="hidden" name="doctor_name" id="f_doctor_name">
        <input type="hidden" name="date" id="f_date">
        <input type="hidden" name="time" id="f_time">

        <!-- STEP 1: Department -->
        <div class="wiz-step active" id="step1">
            <div class="wiz-title">Choose a department</div>
            <div class="wiz-sub">Pick the speciality that best matches what you need.</div>
            <div class="grid-cards" id="deptGrid">
                <?php foreach ($departments as $dept): ?>
                    <div class="pick-card" data-slug="<?= htmlspecialchars($dept['slug_url']) ?>" data-name="<?= htmlspecialchars(trim($dept['categories'])) ?>">
                        <i class="fas fa-stethoscope"></i><?= htmlspecialchars(trim($dept['categories'])) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="wiz-nav"><span></span><button type="button" class="btn-wiz btn-wiz-primary" id="next1" disabled>Continue</button></div>
        </div>

        <!-- STEP 2: Doctor -->
        <div class="wiz-step" id="step2">
            <div class="wiz-title">Choose a doctor</div>
            <div class="wiz-sub" id="docSub">Specialists in this department.</div>
            <div id="doctorList"><div class="text-muted small">Loading…</div></div>
            <div class="wiz-nav"><button type="button" class="btn-wiz btn-wiz-ghost" onclick="goStep(1)">Back</button><button type="button" class="btn-wiz btn-wiz-primary" id="next2" disabled>Continue</button></div>
        </div>

        <!-- STEP 3: Date & Time -->
        <div class="wiz-step" id="step3">
            <div class="wiz-title">Choose date &amp; time</div>
            <div class="wiz-sub" id="schedSub">Doctor's availability.</div>
            <div class="date-strip" id="dateStrip"></div>
            <div class="time-grid" id="timeGrid"><div class="text-muted small">Pick a date to see available times.</div></div>
            <div class="wiz-nav"><button type="button" class="btn-wiz btn-wiz-ghost" onclick="goStep(2)">Back</button><button type="button" class="btn-wiz btn-wiz-primary" id="next3" disabled>Continue</button></div>
        </div>

        <!-- STEP 4: Notes & Submit -->
        <div class="wiz-step" id="step4">
            <div class="wiz-title">Anything the doctor should know?</div>
            <div class="wiz-sub">Optional — symptoms, reason for visit, etc.</div>
            <textarea name="notes" class="form-control mb-3" rows="4" placeholder="Optional notes"></textarea>
            <div class="alert alert-light border small">
                <strong id="summaryText"></strong>
            </div>
            <div class="wiz-nav"><button type="button" class="btn-wiz btn-wiz-ghost" onclick="goStep(3)">Back</button><button type="submit" class="btn-wiz btn-wiz-primary"><i class="fas fa-paper-plane me-1"></i> Send Request to Parent</button></div>
        </div>
    </form>
</div>
<?php endif; ?>

</div><!-- /.book-col-main -->
<div class="col-lg-5 book-col-side">

<?php if (!empty($holds)): ?>
    <div class="s-card">
        <div class="s-card-title" style="margin-bottom:14px;"><span class="ic-badge" style="background:#eaf4fd;color:#0C74C5;"><i class="fas fa-history"></i></span>Your Recent Requests</div>
        <?php foreach ($holds as $h): ?>
            <div class="req-row">
                <span>Dr. <?= htmlspecialchars($h['doctor_name']) ?> — <?= date('d M', strtotime($h['appointment_date'])) ?>, <?= date('h:i A', strtotime($h['appointment_time'])) ?></span>
                <span class="req-status" style="background:<?= $statusColor[$h['status']] ?? '#6b7280' ?>;"><?= $statusLabel[$h['status']] ?? ucfirst($h['status']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="s-card">
    <div class="s-card-title" style="margin-bottom:16px;"><span class="ic-badge" style="background:#eafaf1;color:#16a34a;"><i class="fas fa-circle-question"></i></span>How It Works</div>
    <div class="how-step">
        <div class="how-num">1</div>
        <p><strong>Pick a department, doctor, date &amp; time.</strong><br>Takes less than a minute.</p>
    </div>
    <div class="how-step">
        <div class="how-num">2</div>
        <p><strong>Your parent/guardian gets a link.</strong><br>Sent to the mobile number on file with your school.</p>
    </div>
    <div class="how-step">
        <div class="how-num">3</div>
        <p><strong>They approve &amp; pay.</strong><br>Your appointment is only booked once they confirm.</p>
    </div>
</div>

</div><!-- /.book-col-side -->
</div><!-- /.row -->

</div>
</main>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const state = { department: null, departmentName: null, doctorId: null, doctorName: null, date: null, time: null };

function goStep(n) {
    document.querySelectorAll('.wiz-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');

    document.querySelectorAll('#wizProgress .wp-step').forEach(s => {
        const step = parseInt(s.dataset.step, 10);
        s.classList.toggle('done', step < n);
        s.classList.toggle('current', step === n);
    });
    document.querySelectorAll('#wizProgress .wp-line').forEach(l => {
        l.classList.toggle('done', parseInt(l.dataset.line, 10) < n);
    });

    document.getElementById('bookForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.querySelectorAll('#deptGrid .pick-card').forEach(card => {
    card.addEventListener('click', function () {
        document.querySelectorAll('#deptGrid .pick-card').forEach(c => c.classList.remove('sel'));
        this.classList.add('sel');
        state.department = this.dataset.slug;
        state.departmentName = this.dataset.name;
        document.getElementById('f_department').value = state.departmentName;
        document.getElementById('next1').disabled = false;
    });
});

document.getElementById('next1').addEventListener('click', function () {
    document.getElementById('docSub').textContent = state.departmentName + ' specialists — tap one to continue.';
    const list = document.getElementById('doctorList');
    list.innerHTML = '<div class="text-muted small">Loading…</div>';
    goStep(2);
    fetch(BASE_URL + 'util/get-doctors-by-department.php?department=' + encodeURIComponent(state.department))
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.doctors.length) {
                list.innerHTML = '<div class="text-muted small">No doctors available in this department right now.</div>';
                return;
            }
            list.innerHTML = data.doctors.map(d => `
                <div class="doc-row" data-id="${d.id}" data-name="${d.name}">
                    <div>
                        <div class="dname">${d.name}</div>
                        <div class="dspec">${d.specialization || ''}</div>
                    </div>
                    <div class="dfee">${d.consultation_fee > 0 ? '₹' + d.consultation_fee : 'Free'}</div>
                </div>
            `).join('');
            list.querySelectorAll('.doc-row').forEach(row => {
                row.addEventListener('click', function () {
                    list.querySelectorAll('.doc-row').forEach(r => r.classList.remove('sel'));
                    this.classList.add('sel');
                    state.doctorId = this.dataset.id;
                    state.doctorName = this.dataset.name;
                    document.getElementById('f_doctor_id').value = state.doctorId;
                    document.getElementById('f_doctor_name').value = state.doctorName;
                    document.getElementById('next2').disabled = false;
                });
            });
        });
});

document.getElementById('next2').addEventListener('click', function () {
    document.getElementById('schedSub').textContent = 'Dr. ' + state.doctorName + '’s availability.';
    goStep(3);
    fetch(BASE_URL + 'util/get-doctor-schedule.php?doctor_id=' + state.doctorId)
        .then(r => r.json())
        .then(data => {
            const strip = document.getElementById('dateStrip');
            if (!data.success) { strip.innerHTML = '<div class="text-muted small">Could not load availability.</div>'; return; }
            strip.innerHTML = data.dates.map(d => `
                <div class="date-chip ${d.available ? '' : 'disabled'}" data-date="${d.date}">
                    <div class="dow">${d.dow}</div><div class="num">${d.day}</div>
                </div>
            `).join('');
            strip.querySelectorAll('.date-chip:not(.disabled)').forEach(chip => {
                chip.addEventListener('click', function () {
                    strip.querySelectorAll('.date-chip').forEach(c => c.classList.remove('sel'));
                    this.classList.add('sel');
                    state.date = this.dataset.date;
                    document.getElementById('f_date').value = state.date;
                    loadTimes();
                });
            });
        });
});

function loadTimes() {
    const grid = document.getElementById('timeGrid');
    grid.innerHTML = '<div class="text-muted small">Loading…</div>';
    document.getElementById('next3').disabled = true;
    fetch(BASE_URL + 'util/get-available-slots.php?doctor_id=' + state.doctorId + '&date=' + state.date)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.slots.length) {
                grid.innerHTML = '<div class="text-muted small">No slots available on this date.</div>';
                return;
            }
            grid.innerHTML = data.slots.map(s => `
                <div class="time-chip ${s.booked ? 'booked' : ''}" data-time="${s.time}">${s.display}</div>
            `).join('');
            grid.querySelectorAll('.time-chip:not(.booked)').forEach(chip => {
                chip.addEventListener('click', function () {
                    grid.querySelectorAll('.time-chip').forEach(c => c.classList.remove('sel'));
                    this.classList.add('sel');
                    state.time = this.dataset.time;
                    document.getElementById('f_time').value = state.time;
                    document.getElementById('next3').disabled = false;
                });
            });
        });
}

document.getElementById('next3').addEventListener('click', function () {
    document.getElementById('summaryText').textContent =
        'Dr. ' + state.doctorName + ' — ' + state.date + ' at ' + state.time + '. This request goes to your parent for approval and payment.';
    goStep(4);
});
</script>
</body>
</html>
