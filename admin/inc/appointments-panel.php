<?php
/**
 * Reusable appointments list for admin detail pages (patient / doctor).
 *
 * Set before include:
 *   $ap_scope = 'user' | 'doctor'      which column to filter on
 *   $ap_id    = (int) entity id
 *   $ap_limit = (int) optional, default 12
 * Requires: $conn, and the shared admin CSS (default.css) already loaded.
 */
$ap_scope = ($ap_scope ?? 'user') === 'doctor' ? 'doctor' : 'user';
$ap_id    = (int) ($ap_id ?? 0);
$ap_limit = (int) ($ap_limit ?? 12);
$ap_col   = $ap_scope === 'doctor' ? 'a.doctor_id' : 'a.user_id';
$ap_other = $ap_scope === 'doctor' ? 'Patient' : 'Doctor';

$ap_stmt = $conn->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.purpose,
           a.payment_status, a.payment_amount,
           u.id AS uid, u.name AS user_name, a.patient_name,
           d.id AS did, d.name AS doctor_name, d.specialization
    FROM appointments a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN doctors d ON d.id = a.doctor_id
    WHERE $ap_col = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT ?
");
$ap_stmt->bind_param('ii', $ap_id, $ap_limit);
$ap_stmt->execute();
$ap_rows = $ap_stmt->get_result();

$ap_total = 0;
$ap_cnt = $conn->prepare("SELECT COUNT(*) c FROM appointments a WHERE $ap_col = ?");
$ap_cnt->bind_param('i', $ap_id);
$ap_cnt->execute();
$ap_total = (int) $ap_cnt->get_result()->fetch_assoc()['c'];

$ap_pill = [
    'pending' => 'pill-warn', 'approved' => 'pill-success', 'completed' => 'pill-info',
    'no_show' => 'pill-muted', 'rejected' => 'pill-danger',
];
?>
<div class="table-responsive">
    <table class="table tbl-admin tbl-cards mb-0">
        <thead>
            <tr>
                <th>Ref</th>
                <th><?= $ap_other ?></th>
                <th>When</th>
                <th>Status</th>
                <th>Payment</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$ap_rows->num_rows): ?>
            <tr class="empty-row"><td colspan="6"><i class="fas fa-calendar-times fa-2x mb-2 d-block opacity-25"></i>No appointments yet.</td></tr>
        <?php else: while ($r = $ap_rows->fetch_assoc()):
            $st  = strtolower($r['status'] ?: 'pending');
            $ref = 'AP' . str_pad($r['id'], 6, '0', STR_PAD_LEFT);
        ?>
            <tr>
                <td data-label="Ref"><span class="cell-sub"><?= $ref ?></span></td>
                <td data-label="<?= $ap_other ?>">
                    <?php if ($ap_scope === 'doctor'): ?>
                        <?php if ($r['uid']): ?>
                            <a href="view-customer.php?id=<?= (int) $r['uid'] ?>" class="cell-title"><?= htmlspecialchars($r['user_name']) ?></a>
                        <?php else: ?>
                            <span class="cell-title"><?= htmlspecialchars($r['user_name'] ?: $r['patient_name'] ?: '—') ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($r['did']): ?>
                            <a href="doctor-edit.php?id=<?= (int) $r['did'] ?>" class="cell-title">Dr. <?= htmlspecialchars($r['doctor_name']) ?></a>
                            <div class="cell-sub"><?= htmlspecialchars($r['specialization'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="pill pill-warn pill-sq">Unassigned</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td data-label="When">
                    <div class="cell-title" style="font-weight:500;"><?= date('d M Y', strtotime($r['appointment_date'])) ?></div>
                    <div class="cell-sub"><?= date('h:i A', strtotime($r['appointment_time'])) ?></div>
                </td>
                <td data-label="Status"><span class="pill <?= $ap_pill[$st] ?? 'pill-muted' ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></span></td>
                <td data-label="Payment">
                    <?php if ($r['payment_status'] === 'paid'): ?>
                        <span class="pill pill-success pill-sq">₹<?= number_format((float) $r['payment_amount']) ?></span>
                    <?php elseif (in_array($r['payment_status'], ['pending', 'failed'], true)): ?>
                        <span class="pill pill-warn pill-sq"><?= ucfirst($r['payment_status']) ?></span>
                    <?php else: ?>
                        <span class="cell-sub">—</span>
                    <?php endif; ?>
                </td>
                <td data-label="">
                    <a href="all-appointment.php?status=all&focus=<?= (int) $r['id'] ?>" class="tbl-action-btn bg-primary text-white" title="Open"><i class="fas fa-arrow-up-right-from-square"></i></a>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
<?php if ($ap_total > $ap_rows->num_rows): ?>
    <div class="text-center mt-2">
        <a href="all-appointment.php?status=all&search=<?= $ap_scope === 'doctor' ? '' : '' ?>" class="btn btn-sm btn-outline-primary">
            View all <?= $ap_total ?> appointments
        </a>
    </div>
<?php endif; ?>
