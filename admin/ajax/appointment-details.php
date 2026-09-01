<?php
/**
 * Returns the HTML body for the shared "Appointment details" modal on
 * admin/all-appointment.php (and the dashboard panels). One modal, populated
 * on demand — instead of N pre-rendered modals in the page.
 */
require_once __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../../util/prescription-render.php';

if (!admin_jwt_guard(true)) {
    http_response_code(401);
    exit('<div class="alert alert-danger m-0">Session expired. Please reload.</div>');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    exit('<div class="alert alert-danger m-0">Missing appointment id.</div>');
}

$stmt = $conn->prepare("
    SELECT a.*,
           u.name AS user_name, u.email AS user_email, u.mobile AS user_mobile,
           u.dob, u.gender, u.abha_id AS abha_number, u.abha_address,
           d.name AS doctor_name, d.email AS doctor_email, d.phone AS doctor_phone,
           d.specialization, d.consultation_fee, d.degrees, d.hpr_id,
           TIME_FORMAT(a.appointment_time, '%h:%i %p') AS f_time,
           DATE_FORMAT(a.appointment_date, '%d %M %Y (%W)') AS f_date,
           DATE_FORMAT(a.created_at, '%d %M %Y, %h:%i %p') AS f_created
    FROM appointments a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN doctors d ON d.id = a.doctor_id
    WHERE a.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();

if (!$a) {
    exit('<div class="alert alert-danger m-0">Appointment not found.</div>');
}

$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$ref = 'AP' . str_pad($a['id'], 6, '0', STR_PAD_LEFT);
$patient_name = $a['user_name'] ?: $a['patient_name'];
$patient_mail = $a['user_email'] ?: $a['patient_email'];
$patient_phone = $a['user_mobile'] ?: $a['patient_phone'];
$age = !empty($a['dob']) && $a['dob'] !== '0000-00-00'
    ? date_diff(date_create($a['dob']), date_create('today'))->y . ' yrs' : '—';

$status_pill = [
    'pending' => 'pill-warn', 'approved' => 'pill-success', 'completed' => 'pill-info',
    'no_show' => 'pill-muted', 'rejected' => 'pill-danger',
][strtolower($a['status'])] ?? 'pill-muted';

$pay_pill = [
    'paid' => 'pill-success', 'pending' => 'pill-warn', 'failed' => 'pill-danger',
    'refunded' => 'pill-info', 'not_required' => 'pill-muted',
][$a['payment_status']] ?? 'pill-muted';
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <span class="pill <?= $status_pill ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])) ?></span>
    <span class="pill pill-muted pill-sq"><?= $e($ref) ?></span>
    <?php if ($a['payment_status'] && $a['payment_status'] !== 'not_required'): ?>
        <span class="pill <?= $pay_pill ?> pill-sq">Payment: <?= ucfirst($a['payment_status']) ?><?= $a['payment_amount'] > 0 ? ' · ₹' . number_format((float) $a['payment_amount']) : '' ?></span>
    <?php endif; ?>
    <?php if (!empty($a['meeting_link'])): ?>
        <a href="<?= $e($a['meeting_link']) ?>" target="_blank" rel="noopener" class="pill pill-info pill-sq"><i class="fas fa-video me-1"></i>Join meeting</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="detail-card h-100">
            <h5><i class="fas fa-user me-2"></i>Patient</h5>
            <div class="row">
                <div class="col-12 mb-2"><div class="detail-label">Name</div><div class="detail-val">
                    <?php if ($a['user_id']): ?><a href="view-customer.php?id=<?= (int) $a['user_id'] ?>"><?= $e($patient_name) ?></a>
                    <?php else: ?><?= $e($patient_name) ?> <span class="cell-sub">(guest booking)</span><?php endif; ?>
                </div></div>
                <div class="col-6 mb-2"><div class="detail-label">Age / Gender</div><div class="detail-val"><?= $e($age) ?> / <?= $e($a['gender'] ?: '—') ?></div></div>
                <div class="col-6 mb-2"><div class="detail-label">Visit for</div><div class="detail-val"><?= $a['visit_person'] === 'other' ? $e($a['visited_person_name'] ?: 'Someone else') : 'Self' ?></div></div>
                <div class="col-6 mb-2"><div class="detail-label">Phone</div><div class="detail-val"><?= $e($patient_phone ?: '—') ?></div></div>
                <div class="col-6 mb-2"><div class="detail-label">Email</div><div class="detail-val"><?= $e($patient_mail ?: '—') ?></div></div>
                <?php if (!empty($a['abha_number'])): ?>
                <div class="col-12 mb-2"><div class="detail-label">ABHA</div><div class="detail-val"><?= $e($a['abha_number']) ?><?= $a['abha_address'] ? ' · ' . $e($a['abha_address']) : '' ?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="detail-card h-100">
            <h5><i class="fas fa-user-md me-2"></i>Doctor</h5>
            <?php if ($a['doctor_id']): ?>
            <div class="row">
                <div class="col-12 mb-2"><div class="detail-label">Name</div><div class="detail-val"><a href="doctor-edit.php?id=<?= (int) $a['doctor_id'] ?>">Dr. <?= $e($a['doctor_name']) ?></a></div></div>
                <div class="col-6 mb-2"><div class="detail-label">Specialization</div><div class="detail-val"><?= $e($a['specialization'] ?: '—') ?></div></div>
                <div class="col-6 mb-2"><div class="detail-label">Fee</div><div class="detail-val"><?= $a['consultation_fee'] > 0 ? '₹' . number_format((float) $a['consultation_fee']) : 'Direct booking' ?></div></div>
                <div class="col-6 mb-2"><div class="detail-label">HPR ID</div><div class="detail-val"><?= $e($a['hpr_id'] ?: '—') ?></div></div>
                <div class="col-6 mb-2"><div class="detail-label">Phone</div><div class="detail-val"><?= $e($a['doctor_phone'] ?: '—') ?></div></div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>No doctor assigned yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12">
        <div class="detail-card">
            <h5><i class="fas fa-calendar-check me-2"></i>Appointment</h5>
            <div class="row">
                <div class="col-md-4 mb-2"><div class="detail-label">Date</div><div class="detail-val"><?= $e($a['f_date']) ?></div></div>
                <div class="col-md-4 mb-2"><div class="detail-label">Time</div><div class="detail-val"><?= $e($a['f_time']) ?></div></div>
                <div class="col-md-4 mb-2"><div class="detail-label">Type</div><div class="detail-val"><?= $e($a['appointment_type'] ?: 'Consultation') ?></div></div>
                <div class="col-md-4 mb-2"><div class="detail-label">Booked on</div><div class="detail-val"><?= $e($a['f_created']) ?></div></div>
                <?php if ($a['appointment_type'] === 'online'): ?>
                <div class="col-md-4 mb-2"><div class="detail-label">Video session</div><div class="detail-val">
                    <?= $e(ucfirst($a['meeting_status'] ?: 'not created')) ?>
                    <?php if (!empty($a['meeting_started_at'])): ?><br><span class="cell-sub">Started <?= date('d M, h:i A', strtotime($a['meeting_started_at'])) ?><?php if (!empty($a['meeting_completed_at'])): ?> · Ended <?= date('h:i A', strtotime($a['meeting_completed_at'])) ?><?php endif; ?></span><?php endif; ?>
                </div></div>
                <?php endif; ?>
                <div class="col-md-4 mb-2"><div class="detail-label">Admin verified</div><div class="detail-val"><?= $a['admin_verified_at'] ? date('d M Y, h:i A', strtotime($a['admin_verified_at'])) : 'No' ?></div></div>
                <div class="col-md-4 mb-2"><div class="detail-label">Consent</div><div class="detail-val"><?= $a['consent_given'] ? 'Given' . ($a['consent_at'] ? ' · ' . date('d M Y', strtotime($a['consent_at'])) : '') : 'Not recorded' ?></div></div>
                <div class="col-12 mb-1"><div class="detail-label">Purpose of visit</div><div class="detail-val"><?= nl2br($e($a['purpose'] ?: '—')) ?></div></div>
                <?php if (!empty($a['notes'])): ?><div class="col-12"><div class="detail-label">Notes</div><div class="detail-val"><?= nl2br($e($a['notes'])) ?></div></div><?php endif; ?>
                <?php if (!empty($a['rejection_reason'])): ?><div class="col-12 mt-2"><div class="alert alert-danger mb-0"><strong>Rejection reason:</strong> <?= $e($a['rejection_reason']) ?></div></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
/* ── Video consultation chat transcript ── */
$chat = [];
if ($tbl_chat = $conn->query("SHOW TABLES LIKE 'telemedicine_chat_messages'")->num_rows) {
    $cq = $conn->prepare("SELECT sender_role, sender_id, message, sent_at
                          FROM telemedicine_chat_messages WHERE appointment_id = ? ORDER BY sent_at ASC");
    $cq->bind_param('i', $a['id']);
    $cq->execute();
    $chat = $cq->get_result()->fetch_all(MYSQLI_ASSOC);
}
if ($chat):
    $docLabel = 'Dr. ' . ($a['doctor_name'] ?: 'Doctor');
    $patLabel = $patient_name ?: 'Patient';
?>
<div class="detail-card mt-3">
    <h5><i class="fas fa-comments me-2"></i>Video Consultation Chat <span class="cell-sub">(<?= count($chat) ?> messages)</span></h5>
    <div style="max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding-right:4px;">
        <?php foreach ($chat as $m):
            $isDoc = $m['sender_role'] === 'doctor';
            $who   = $isDoc ? $docLabel : $patLabel;
        ?>
        <div style="align-self:<?= $isDoc ? 'flex-start' : 'flex-end' ?>;max-width:78%;">
            <div style="font-size:.68rem;font-weight:700;color:#9aa0b4;margin-bottom:2px;<?= $isDoc ? '' : 'text-align:right;' ?>">
                <?= $e($who) ?> · <?= date('d M, h:i A', strtotime($m['sent_at'])) ?>
            </div>
            <div style="background:<?= $isDoc ? '#f1f3f9' : '#e7f1fb' ?>;padding:7px 11px;border-radius:10px;font-size:.85rem;white-space:pre-wrap;word-break:break-word;">
                <?= $e($m['message']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif ($a['appointment_type'] === 'online'): ?>
<div class="detail-card mt-3">
    <h5><i class="fas fa-comments me-2"></i>Video Consultation Chat</h5>
    <p class="text-muted mb-0">No chat messages were exchanged during this consultation.</p>
</div>
<?php endif; ?>

<?php
/* ── Consultation record (prescription) ── */
$rx = null;
$rx_draft = false;
if ($a['doctor_id']) {
    $q = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? ORDER BY (status='final') DESC, id DESC LIMIT 1");
    $q->bind_param('i', $a['id']);
    $q->execute();
    $rx = $q->get_result()->fetch_assoc();
    if ($rx && $rx['status'] !== 'final') { $rx_draft = true; }
}
if ($rx):
    $dl = $conn->prepare("SELECT document_name, document_type, description, file_path, file_type, uploaded_at
                          FROM patient_documents WHERE appointment_id = ? ORDER BY uploaded_at DESC");
    $dl->bind_param('i', $a['id']);
    $dl->execute();
    $rx_docs = $dl->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="detail-card mt-3">
    <h5><i class="fas fa-file-medical me-2"></i>Consultation Record &amp; Prescription</h5>
    <?php if ($rx_draft): ?>
        <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.85rem;">
            <i class="fas fa-exclamation-triangle me-1"></i> The doctor has <strong>not finalised</strong> this prescription yet — shown below as a draft.
        </div>
    <?php endif; ?>
    <?php render_prescription_view(
        $rx,
        ['name' => $a['doctor_name'], 'degrees' => $a['degrees'], 'specialization' => $a['specialization'], 'hpr_id' => $a['hpr_id'], 'phone' => $a['doctor_phone']],
        ['name' => $patient_name, 'abha_number' => $a['abha_number'], 'abha_address' => $a['abha_address'], 'patient_age' => $age, 'gender' => $a['gender']],
        $rx_docs,
        ['doc_base' => BASE_URL, 'pdf_url' => BASE_URL . 'doctor/opd-slip.php?appointment_id=' . $a['id']]
    ); ?>
</div>
<?php elseif ($a['doctor_id'] && in_array($a['status'], ['completed', 'approved'], true)): ?>
<div class="detail-card mt-3">
    <h5><i class="fas fa-file-medical me-2"></i>Consultation Record &amp; Prescription</h5>
    <p class="text-muted mb-0">The doctor has not written a prescription for this appointment yet.</p>
</div>
<?php endif; ?>
