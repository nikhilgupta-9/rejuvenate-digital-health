<?php
/**
 * Right sidebar for patient-form.php (shared by Patient + Student modes).
 * Expects: $doctor, $mode, $doctor_id, $patient (patient mode), $member (student mode),
 *          $existing_rx (patient mode), $today_appts, $today_students, $appointment_id, $member_id, $conn
 */
?>

<!-- Doctor Card -->
<div class="rx-card">
    <div class="rx-card-header <?= ($mode === 'student') ? 'student-header' : '' ?>">
        <i class="fa fa-user-md"></i> Prescribing Doctor
    </div>
    <div class="rx-card-body" style="font-size:.84rem;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div style="width:40px;height:40px;border-radius:50%;background:var(--rdh-blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">
                <i class="fa fa-user-md"></i>
            </div>
            <div>
                <div class="fw-bold">Dr. <?= htmlspecialchars($doctor['name'] ?? '') ?></div>
                <div class="text-muted small"><?= htmlspecialchars($doctor['degrees'] ?? '') ?></div>
            </div>
        </div>
        <div class="text-muted small"><?= htmlspecialchars($doctor['specialization'] ?? '') ?></div>
        <?php if (!empty($doctor['hpr_id'])): ?>
            <div class="text-success small mt-1"><i class="fa fa-check-circle me-1"></i>HPR: <?= htmlspecialchars($doctor['hpr_id']) ?></div>
        <?php else: ?>
            <div class="text-warning small mt-1"><i class="fa fa-exclamation-triangle me-1"></i>HPR not registered</div>
        <?php endif; ?>
        <div class="text-muted small"><?= htmlspecialchars($doctor['phone'] ?? '') ?></div>
    </div>
</div>

<!-- ABDM / Patient info (patient mode only) -->
<?php if ($mode === 'patient' && isset($patient)): ?>
<div class="rx-card">
    <div class="rx-card-header" style="background:linear-gradient(135deg,#025a52,var(--rdh-teal));">
        <i class="fa fa-shield-alt"></i> ABDM Compliance
    </div>
    <div class="rx-card-body" style="font-size:.82rem;">
        <?php if (!empty($patient['abha_number'])): ?>
            <div class="mb-2">
                <div class="text-muted">ABHA Number</div>
                <strong style="color:var(--rdh-teal);"><?= htmlspecialchars($patient['abha_number']) ?></strong>
            </div>
            <?php if (!empty($patient['abha_address'])): ?>
            <div class="mb-2">
                <div class="text-muted">ABHA Address</div>
                <strong><?= htmlspecialchars($patient['abha_address']) ?></strong>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.78rem;">
                Patient ABHA not linked. Records stored locally only.
                <a href="add-patient-abha.php" class="alert-link d-block mt-1">Link ABHA →</a>
            </div>
        <?php endif; ?>
        <?php if (isset($existing_rx) && $existing_rx): ?>
            <div class="mb-1">
                <div class="text-muted">Care Context</div>
                <strong class="text-break" style="color:var(--rdh-blue);font-size:.78rem;"><?= htmlspecialchars($existing_rx['care_context_ref']) ?></strong>
            </div>
            <div class="mb-1">
                <div class="text-muted">Status</div>
                <span class="badge-<?= $existing_rx['status'] ?>"><?= ucfirst($existing_rx['status']) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($rx_attachments)): ?>
            <div>
                <div class="text-muted">Report Attachments</div>
                <strong style="color:var(--rdh-blue);"><i class="fa fa-paperclip me-1"></i><?= count($rx_attachments) ?> file<?= count($rx_attachments) === 1 ? '' : 's' ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- School info (student mode only) -->
<?php if ($mode === 'student' && isset($member)): ?>
<div class="rx-card">
    <div class="rx-card-header student-header"><i class="fa fa-school"></i> School Info</div>
    <div class="rx-card-body" style="font-size:.83rem;">
        <div class="mb-1"><div class="text-muted">School</div><strong><?= htmlspecialchars($member['school_name']) ?></strong></div>
        <?php if (!empty($member['class'])): ?><div class="mb-1"><div class="text-muted">Class</div><strong><?= htmlspecialchars($member['class']) ?></strong></div><?php endif; ?>
        <?php if (!empty($member['section'])): ?><div class="mb-1"><div class="text-muted">Section</div><strong><?= htmlspecialchars($member['section']) ?></strong></div><?php endif; ?>
        <?php if (!empty($member['roll_no'])): ?><div class="mb-1"><div class="text-muted">Roll No.</div><strong><?= htmlspecialchars($member['roll_no']) ?></strong></div><?php endif; ?>
        <?php if (!empty($member['parent_name'])): ?><div class="mb-1"><div class="text-muted">Parent</div><strong><?= htmlspecialchars($member['parent_name']) ?></strong></div><?php endif; ?>
        <?php if (!empty($member['parent_mobile'])): ?><div><div class="text-muted">Parent Mobile</div><strong><?= htmlspecialchars($member['parent_mobile']) ?></strong></div><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Past prescriptions (patient mode) -->
<?php if ($mode === 'patient' && isset($patient) && isset($appointment_id)): ?>
<?php
$prev_s = $conn->prepare("
    SELECT id, visit_date, care_context_ref, status, diagnosis
    FROM prescriptions
    WHERE patient_id = ? AND appointment_id != ?
    ORDER BY visit_date DESC LIMIT 5
");
$prev_s->bind_param('ii', $patient['patient_id'], $appointment_id);
$prev_s->execute();
$prev_rx = $prev_s->get_result()->fetch_all(MYSQLI_ASSOC);
$prev_s->close();
?>
<?php if (!empty($prev_rx)): ?>
<div class="rx-card">
    <div class="rx-card-header"><i class="fa fa-history"></i> Past Prescriptions</div>
    <div class="rx-card-body p-0">
        <ul class="list-group list-group-flush" style="font-size:.79rem;">
            <?php foreach ($prev_rx as $pr): ?>
                <li class="list-group-item py-2 px-3">
                    <div class="fw-semibold"><?= date('d M Y', strtotime($pr['visit_date'])) ?></div>
                    <div class="text-muted text-truncate"><?= htmlspecialchars($pr['diagnosis'] ?: '—') ?></div>
                    <span class="badge-<?= $pr['status'] ?>"><?= ucfirst($pr['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Today's patient queue -->
<?php if (!empty($today_appts)): ?>
<div class="rx-card no-print">
    <div class="rx-card-header"><i class="fa fa-list-ul"></i> Today's Patients</div>
    <div class="rx-card-body p-0">
        <ul class="list-group list-group-flush" style="font-size:.79rem;max-height:220px;overflow-y:auto;">
            <?php foreach ($today_appts as $ta): ?>
                <li class="list-group-item py-2 px-3 <?= (isset($appointment_id) && $ta['id'] == $appointment_id) ? 'active' : '' ?>">
                    <a href="patient-form.php?appointment_id=<?= $ta['id'] ?>"
                       class="<?= (isset($appointment_id) && $ta['id'] == $appointment_id) ? 'text-white' : 'text-dark' ?> text-decoration-none d-flex justify-content-between">
                        <span><?= htmlspecialchars($ta['name'] . ' ' . $ta['last_name']) ?></span>
                        <small class="ms-1 flex-shrink-0"><?= date('h:i A', strtotime($ta['appointment_time'])) ?></small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Student quick links -->
<?php if ($mode === 'student' && !empty($today_students)): ?>
<div class="rx-card no-print">
    <div class="rx-card-header student-header"><i class="fa fa-users"></i> Recent Students</div>
    <div class="rx-card-body p-0">
        <ul class="list-group list-group-flush" style="font-size:.79rem;max-height:220px;overflow-y:auto;">
            <?php foreach (array_slice($today_students, 0, 10) as $stu): ?>
                <li class="list-group-item py-2 px-3 <?= (isset($member_id) && $stu['id'] == $member_id) ? 'active' : '' ?>">
                    <a href="patient-form.php?mode=student&member_id=<?= $stu['id'] ?>"
                       class="<?= (isset($member_id) && $stu['id'] == $member_id) ? 'text-white' : 'text-dark' ?> text-decoration-none d-flex justify-content-between">
                        <span><?= htmlspecialchars($stu['name']) ?></span>
                        <small class="text-truncate ms-1" style="max-width:80px;"><?= htmlspecialchars($stu['school_name']) ?></small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
